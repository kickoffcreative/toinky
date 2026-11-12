<?php
/** Account links are delivered only by email. Never return their bearer tokens. */
final class AccountSecurityService
{
    public static function requireVerifiedEmail(int $userId): void
    {
        $q=db()->prepare('SELECT 1 FROM users WHERE user_id=? AND status="ACTIVE" AND email_verified_at_utc IS NOT NULL');
        $q->execute([$userId]);
        if(!$q->fetchColumn())throw new RuntimeException('Verify your email in My Profile before hosting, inviting players, or sending friend requests.');
    }

    public static function requestPasswordReset(string $email): array
    {
        try{RateLimitService::resetRequest($email);}catch(RateLimitException $e){return ['sent'=>false];}
        $q=db()->prepare('SELECT user_id,email FROM users WHERE email=? AND status="ACTIVE"');
        $q->execute([strtolower(trim($email))]);$user=$q->fetch();
        if(!$user)return ['sent'=>false];
        return self::deliverReset($user,self::prepareReset($user));
    }

    private static function prepareReset(array $user): array
    {
        $token=bin2hex(random_bytes(24));
        db()->prepare('INSERT INTO password_reset_tokens(user_id,token_hash,expires_at_utc) VALUES(?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 MINUTE))')->execute([$user['user_id'],hash('sha256',$token)]);
        return ['id'=>(int)db()->lastInsertId(),'token'=>$token];
    }

    private static function deliverReset(array $user,array $reset): array
    {
        $token=$reset['token'];$id=$reset['id'];
        $sent=AuthService::sendTemplatedMessage((string)$user['email'],'PASSWORD_RESET',[
            'recipient_name'=>'there','action_url'=>AuthService::baseUrl().'/?view=reset&token='.rawurlencode($token),
            'action_label'=>'Reset your password','message'=>'Use the button below to choose a new password.',
            'expiry_text'=>'This link expires in 30 minutes.'
        ]);
        if($sent)db()->prepare('UPDATE password_reset_tokens SET delivered_at_utc=UTC_TIMESTAMP() WHERE password_reset_id=?')->execute([$id]);
        return ['sent'=>$sent];
    }

    public static function adminPasswordReset(int $actorId,int $targetId): array
    {
        try{RateLimitService::adminReset($actorId);}catch(RateLimitException $e){self::audit($actorId,$targetId,'ADMIN_PASSWORD_RESET','THROTTLED');throw $e;}
        $pdo=db();$pdo->beginTransaction();
        try{
            // Lock both roles in a stable order. The audit and permission decision commit together.
            $q=$pdo->prepare('SELECT user_id,email,is_admin,is_super_admin,status FROM users WHERE user_id IN (?,?) ORDER BY user_id FOR UPDATE');
            $q->execute([$actorId,$targetId]);$rows=[];foreach($q->fetchAll() as $row)$rows[(int)$row['user_id']]=$row;
            $actor=$rows[$actorId]??null;$target=$rows[$targetId]??null;
            $allowed=$actor&&$target&&$actor['status']==='ACTIVE'&&$target['status']==='ACTIVE'
                &&((int)$actor['is_admin']===1||(int)$actor['is_super_admin']===1)
                &&(((int)$target['is_admin']!==1&&(int)$target['is_super_admin']!==1)||(int)$actor['is_super_admin']===1);
            $reset=$allowed?self::prepareReset($target):null;
            self::audit($actorId,$targetId,'ADMIN_PASSWORD_RESET',$allowed?'AUTHORIZED':'DENIED');
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        if(!$allowed)throw new RuntimeException('You cannot request a password reset for that account.');
        // Separate notice is attempted even when sending the reset message fails.
        try{return self::deliverReset($target,$reset);}
        finally{
            $sent=AuthService::sendAccountMessage((string)$target['email'],'Administrator Requested A Password Reset',
                'A Toinky administrator requested a password reset for your account. Your password has not changed. If this was unexpected, contact support.','ADMIN_RESET_NOTICE',$actorId);
            self::audit($actorId,$targetId,'ADMIN_RESET_NOTICE',$sent?'SENT':'FAILED');
        }
    }

    public static function resetPassword(string $token,string $password): void
    {
        RateLimitService::resetAttempt($token);self::validatePassword($password);$pdo=db();
        $q=$pdo->prepare('SELECT user_id FROM password_reset_tokens WHERE token_hash=?');$q->execute([hash('sha256',$token)]);$id=(int)$q->fetchColumn();
        if($id<1)throw new RuntimeException('This reset link is invalid or expired.');
        $pdo->beginTransaction();
        try{
            // Serialize all reset tokens for this account before checking/consuming one.
            $q=$pdo->prepare('SELECT email,status FROM users WHERE user_id=? FOR UPDATE');$q->execute([$id]);$user=$q->fetch();
            $q=$pdo->prepare('SELECT password_reset_id FROM password_reset_tokens WHERE user_id=? AND token_hash=? AND delivered_at_utc IS NOT NULL AND used_at_utc IS NULL AND expires_at_utc>UTC_TIMESTAMP() FOR UPDATE');$q->execute([$id,hash('sha256',$token)]);
            if(!$user||$user['status']!=='ACTIVE'||!$q->fetchColumn())throw new RuntimeException('This reset link is invalid or expired.');
            self::replacePassword($id,$password);$pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        self::passwordChangedNotice((string)$user['email']);
    }

    public static function changePassword(int $userId,string $currentPassword,string $newPassword): void
    {
        self::validatePassword($newPassword);$pdo=db();$pdo->beginTransaction();
        try{
            $q=$pdo->prepare('SELECT password_hash,email,status FROM users WHERE user_id=? FOR UPDATE');$q->execute([$userId]);$user=$q->fetch();
            if(!$user||$user['status']!=='ACTIVE'||!password_verify($currentPassword,(string)$user['password_hash']))throw new RuntimeException('Your current password is incorrect.');
            self::replacePassword($userId,$newPassword);$pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        self::passwordChangedNotice((string)$user['email']);
    }

    private static function replacePassword(int $id,string $password): void
    {
        // Caller holds the account lock. Password, all reset tokens and every session change atomically.
        db()->prepare('UPDATE users SET password_hash=?,session_version=session_version+1 WHERE user_id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$id]);
        db()->prepare('UPDATE password_reset_tokens SET used_at_utc=UTC_TIMESTAMP() WHERE user_id=? AND used_at_utc IS NULL')->execute([$id]);
        db()->prepare('UPDATE user_two_factor_codes SET used_at_utc=UTC_TIMESTAMP() WHERE user_id=? AND used_at_utc IS NULL')->execute([$id]);
    }
    private static function validatePassword(string $password): void
    {
        if(strlen($password)<10)throw new RuntimeException('Password must contain at least 10 characters.');
    }
    private static function passwordChangedNotice(string $email): void
    {
        AuthService::sendAccountMessage($email,'Your Toinky Password Changed','Your Toinky password was changed and all existing sessions were signed out. If you did not make this change, contact support.','PASSWORD_CHANGED');
    }
    private static function audit(int $actor,int $target,string $event,string $outcome): void
    {
        db()->prepare('INSERT INTO account_security_audit(actor_user_id,target_user_id,event_code,outcome,correlation_id) VALUES(?,?,?,?,?)')->execute([$actor,$target,$event,$outcome,correlationId()]);
    }

    public static function requestVerification(int $userId): array
    {
        try{RateLimitService::verification($userId);}catch(RateLimitException $e){return ['sent'=>false];}
        $q=db()->prepare('SELECT email,email_verified_at_utc FROM users WHERE user_id=? AND status="ACTIVE"');$q->execute([$userId]);$user=$q->fetch();
        if(!$user||$user['email_verified_at_utc']!==null)return ['sent'=>false];
        $token=bin2hex(random_bytes(24));
        db()->prepare('INSERT INTO user_email_verification_tokens(user_id,email_hash,token_hash,expires_at_utc) VALUES(?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 24 HOUR))')->execute([$userId,hash('sha256',strtolower((string)$user['email'])),hash('sha256',$token)]);
        $id=(int)db()->lastInsertId();
        $sent=AuthService::sendAccountMessage((string)$user['email'],'Verify Your Toinky Email',
            'Verify your email before hosting, inviting players, or sending friend requests: '.AuthService::baseUrl().'/?view=verify_email&token='.rawurlencode($token).' This link expires in 24 hours.','EMAIL_VERIFICATION',$userId);
        if($sent)db()->prepare('UPDATE user_email_verification_tokens SET delivered_at_utc=UTC_TIMESTAMP() WHERE verification_token_id=?')->execute([$id]);
        return ['sent'=>$sent];
    }

    public static function verifyEmail(string $token): void
    {
        $pdo=db();$q=$pdo->prepare('SELECT user_id FROM user_email_verification_tokens WHERE token_hash=?');$q->execute([hash('sha256',$token)]);$id=(int)$q->fetchColumn();
        $pdo->beginTransaction();
        try{
            $q=$pdo->prepare('SELECT email,status FROM users WHERE user_id=? FOR UPDATE');$q->execute([$id]);$user=$q->fetch();
            $q=$pdo->prepare('SELECT verification_token_id,email_hash FROM user_email_verification_tokens WHERE user_id=? AND token_hash=? AND delivered_at_utc IS NOT NULL AND used_at_utc IS NULL AND expires_at_utc>UTC_TIMESTAMP() FOR UPDATE');$q->execute([$id,hash('sha256',$token)]);$row=$q->fetch();
            if(!$user||$user['status']!=='ACTIVE'||!$row||!hash_equals((string)$row['email_hash'],hash('sha256',strtolower((string)$user['email']))))throw new RuntimeException('This email verification link is invalid or expired.');
            $pdo->prepare('UPDATE users SET email_verified_at_utc=UTC_TIMESTAMP() WHERE user_id=?')->execute([$id]);
            $pdo->prepare('UPDATE user_email_verification_tokens SET used_at_utc=UTC_TIMESTAMP() WHERE user_id=? AND used_at_utc IS NULL')->execute([$id]);$pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
}
