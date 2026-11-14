<?php
require_once __DIR__.'/AuthService.php';
/** Email links carry random bearer tokens; only their hashes are persisted. */
final class AccountEmailService
{
    public const REQUEST_NOTICE='If that address can be used, we sent a verification link.';
    public const INVITE_NOTICE='If that address can receive an invitation, we sent one.';
    public const FRIEND_NOTICE='Request sent if that player exists.';

    public static function request(int $id,string $email,string $password): array
    {
        $verified=PHP_SAPI==='cli'?PrivacyService::confirmPassword($id,$password):SessionService::confirmPassword($id,$password);
        RateLimitService::emailChange($id);
        $email=strtolower(trim($email));
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new UserFacingException('Enter a valid email address.');
        $pdo=db();$pdo->beginTransaction();
        try{
            $q=$pdo->prepare('SELECT email,password_hash,status,system_identity_key FROM users WHERE user_id=? FOR UPDATE');$q->execute([$id]);$user=$q->fetch();
            if(!$user||$user['status']!=='ACTIVE'||$user['system_identity_key']!==null||!hash_equals($verified['password_hash'],$user['password_hash']))throw new UserFacingException('Confirm your current password and retry.');
            $q=$pdo->prepare('SELECT 1 FROM users WHERE email=?');$q->execute([$email]);
            if($q->fetchColumn()){$pdo->commit();return ['sent'=>false];}
            $token=bin2hex(random_bytes(24));
            $pdo->prepare('UPDATE user_email_change_tokens SET expires_at_utc=UTC_TIMESTAMP() WHERE user_id=? AND verified_at_utc IS NULL')->execute([$id]);
            $pdo->prepare('INSERT INTO user_email_change_tokens(user_id,old_email,new_email,token_hash,expires_at_utc) VALUES(?,?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 HOUR))')->execute([$id,$user['email'],$email,hash('sha256',$token)]);
            $changeId=(int)$pdo->lastInsertId();$pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        $sent=AuthService::sendTemplatedMessage($email,'EMAIL_CHANGE',['recipient_name'=>'there','action_url'=>AuthService::baseUrl().'/profile.php?verify_email='.rawurlencode($token),'action_label'=>'Verify email address','message'=>'Confirm this email address for your Toinky account. Confirmation signs out all sessions.','expiry_text'=>'This link expires in 2 hours.'],null,$id);
        if($sent)$pdo->prepare('UPDATE user_email_change_tokens SET delivered_at_utc=UTC_TIMESTAMP() WHERE email_change_token_id=?')->execute([$changeId]);
        return ['sent'=>$sent];
    }

    public static function confirm(int $id,string $token): void
    {
        RateLimitService::hit('EMAIL_CONFIRM_USER',(string)$id,10,900,900);
        $pdo=db();$pdo->beginTransaction();
        try{
            $q=$pdo->prepare('SELECT email,status FROM users WHERE user_id=? AND system_identity_key IS NULL FOR UPDATE');$q->execute([$id]);$user=$q->fetch();
            $q=$pdo->prepare('SELECT * FROM user_email_change_tokens WHERE user_id=? AND token_hash=? AND delivered_at_utc IS NOT NULL AND verified_at_utc IS NULL AND expires_at_utc>UTC_TIMESTAMP() FOR UPDATE');$q->execute([$id,hash('sha256',$token)]);$change=$q->fetch();
            if(!$user||$user['status']!=='ACTIVE'||!$change||$change['old_email']!==$user['email'])throw new UserFacingException('This email verification link is invalid or expired.');
            $q=$pdo->prepare('SELECT 1 FROM users WHERE email=? AND user_id<>?');$q->execute([$change['new_email'],$id]);
            if($q->fetchColumn())throw new UserFacingException('This email verification link cannot be used. Request a new change.');
            self::moveEmail($id,$user['email'],$change['new_email']);
            $undo=bin2hex(random_bytes(24));
            $pdo->prepare('UPDATE user_email_change_tokens SET verified_at_utc=UTC_TIMESTAMP(),undo_hash=?,undo_expires_at_utc=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 7 DAY) WHERE email_change_token_id=?')->execute([hash('sha256',$undo),$change['email_change_token_id']]);
            self::invalidateAccess($id);$pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if($e instanceof PDOException&&$e->getCode()==='23000')throw new UserFacingException('This email verification link cannot be used. Request a new change.');throw $e;}
        $sent=AuthService::sendAccountMessage($user['email'],'Your Toinky Email Changed',"Your Toinky account email was changed. All sessions were signed out. If you did not make this change, restore your previous email within seven days:\n\n".AuthService::baseUrl().'/?view=undo_email&undo_email='.rawurlencode($undo)."\n\nRestoring the email signs out all sessions, removes authenticator/recovery codes, and requires a new password through your restored mailbox. For help, contact accounts@toinky.com.",'EMAIL_CHANGE_NOTICE',$id);
        if($sent)$pdo->prepare('UPDATE user_email_change_tokens SET undo_delivered_at_utc=UTC_TIMESTAMP() WHERE email_change_token_id=? AND undone_at_utc IS NULL')->execute([$change['email_change_token_id']]);
    }

    public static function undo(string $token): void
    {
        RateLimitService::hit('EMAIL_UNDO_IP',RateLimitService::clientIp(),10,900,900);
        $pdo=db();$hash=hash('sha256',$token);$q=$pdo->prepare('SELECT user_id FROM user_email_change_tokens WHERE undo_hash=?');$q->execute([$hash]);$id=(int)$q->fetchColumn();
        if($id<1)throw new UserFacingException('This email recovery link is invalid or expired.');
        $pdo->beginTransaction();
        try{
            $q=$pdo->prepare('SELECT email FROM users WHERE user_id=? AND system_identity_key IS NULL FOR UPDATE');$q->execute([$id]);$user=$q->fetch();
            $q=$pdo->prepare('SELECT * FROM user_email_change_tokens WHERE user_id=? AND undo_hash=? AND undo_delivered_at_utc IS NOT NULL AND undo_expires_at_utc>UTC_TIMESTAMP() AND undone_at_utc IS NULL FOR UPDATE');$q->execute([$id,$hash]);$change=$q->fetch();
            $q=$pdo->prepare('SELECT MAX(email_change_token_id) FROM user_email_change_tokens WHERE user_id=? AND verified_at_utc IS NOT NULL');$q->execute([$id]);$latest=(int)$q->fetchColumn();
            if(!$user||!$change||$latest!==(int)$change['email_change_token_id']||$change['new_email']!==$user['email'])throw new UserFacingException('This email recovery link is invalid or expired.');
            $q=$pdo->prepare('SELECT 1 FROM users WHERE email=? AND user_id<>?');$q->execute([$change['old_email'],$id]);
            if($q->fetchColumn())throw new UserFacingException('This email recovery needs help from accounts@toinky.com.');
            self::moveEmail($id,$user['email'],$change['old_email']);
            $pdo->prepare('UPDATE users SET password_hash=? WHERE user_id=?')->execute(['!',$id]);
            $pdo->prepare('DELETE FROM user_totp_credentials WHERE user_id=?')->execute([$id]);
            $pdo->prepare('UPDATE user_email_change_tokens SET undone_at_utc=UTC_TIMESTAMP() WHERE email_change_token_id=?')->execute([$change['email_change_token_id']]);
            self::invalidateAccess($id);$pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if($e instanceof PDOException&&$e->getCode()==='23000')throw new UserFacingException('This email recovery needs help from accounts@toinky.com.');throw $e;}
        AuthService::sendAccountMessage($change['old_email'],'Your Toinky Email Was Restored','Your previous email was restored. All sessions and authenticator/recovery codes were revoked. Open Toinky directly and use Forgot Password to choose a new password. Disabled accounts still require support assistance.','EMAIL_RESTORED_NOTICE',$id);
    }

    private static function moveEmail(int $id,string $old,string $new): void
    {
        PrivacyService::rememberEmail($id,$old);
        db()->prepare('UPDATE user_email_history SET valid_until_utc=UTC_TIMESTAMP() WHERE user_id=? AND valid_until_utc IS NULL')->execute([$id]);
        PrivacyService::rememberEmail($id,$new);
        db()->prepare('UPDATE users SET email=?,email_verified_at_utc=UTC_TIMESTAMP() WHERE user_id=?')->execute([$new,$id]);
    }

    private static function invalidateAccess(int $id): void
    {
        foreach(['password_reset_tokens','user_email_verification_tokens','user_two_factor_codes'] as $table)db()->prepare('UPDATE '.$table.' SET used_at_utc=COALESCE(used_at_utc,UTC_TIMESTAMP()) WHERE user_id=?')->execute([$id]);
        db()->prepare('UPDATE user_email_change_tokens SET expires_at_utc=UTC_TIMESTAMP() WHERE user_id=? AND verified_at_utc IS NULL')->execute([$id]);
        SessionService::revokeAll($id);
    }
}
