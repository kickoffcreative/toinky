<?php
require_once __DIR__.'/UserFacingException.php';
final class SessionService
{
    public static function limits(array $user): array
    {
        $privileged=(int)($user['is_admin']??0)===1||(int)($user['is_super_admin']??0)===1;
        if(!$privileged){$q=db()->prepare('SELECT 1 FROM user_platform_roles u JOIN platform_roles r ON r.platform_role_id=u.platform_role_id WHERE u.user_id=? AND r.role_code="WRITER"');$q->execute([(int)$user['user_id']]);$privileged=(bool)$q->fetchColumn();}
        return $privileged?['idle'=>1800,'absolute'=>43200]:['idle'=>86400,'absolute'=>2592000];
    }
    public static function hash(): string
    {
        if(session_status()!==PHP_SESSION_ACTIVE||session_id()==='')throw new LogicException('An active session is required.');
        return hash('sha256',session_id());
    }
    /** Called only after credential/factor verification, or by isolated fixtures. */
    public static function register(int $id,bool $mfa=false,?int $passwordAt=null): void
    {
        $q=db()->prepare('SELECT user_id,is_admin,is_super_admin,EXISTS(SELECT 1 FROM user_totp_credentials t WHERE t.user_id=users.user_id AND t.enabled_at_utc IS NOT NULL) has_totp FROM users WHERE user_id=? AND status="ACTIVE" AND system_identity_key IS NULL');$q->execute([$id]);$user=$q->fetch();
        if(!$user)throw new UserFacingException('Your sign-in session expired. Sign in again.');
        if(((int)$user['is_admin']===1||(int)$user['is_super_admin']===1||(int)$user['has_totp']===1)&&!$mfa)throw new UserFacingException('Complete the second-factor check before signing in.');
        $now=time();$limits=self::limits($user);$passwordAt??=$now;
        if($passwordAt>$now||$passwordAt<$now-600)throw new UserFacingException('Your sign-in session expired. Sign in again.');
        $agent=mb_strcut(mb_convert_encoding((string)($_SERVER['HTTP_USER_AGENT']??''),'UTF-8','UTF-8'),0,190,'UTF-8');$agent=(string)preg_replace('/[\x00-\x1f\x7f]/','',$agent);
        db()->prepare('INSERT INTO user_sessions(session_hash,user_id,created_at_utc,last_seen_at_utc,absolute_expires_at_utc,mfa_verified_at_utc,password_confirmed_at_utc,user_agent) VALUES(?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP(),?,?,?,?)')->execute([self::hash(),$id,gmdate('Y-m-d H:i:s',$now+$limits['absolute']),$mfa?gmdate('Y-m-d H:i:s',$now):null,gmdate('Y-m-d H:i:s',$passwordAt),$agent]);
    }
    public static function valid(array $user): bool
    {
        $hash=self::hash();$id=(int)$user['user_id'];$q=db()->prepare('SELECT s.*,EXISTS(SELECT 1 FROM user_totp_credentials t WHERE t.user_id=s.user_id AND t.enabled_at_utc IS NOT NULL) has_totp FROM user_sessions s WHERE session_hash=? AND user_id=? AND revoked_at_utc IS NULL');$q->execute([$hash,$id]);$row=$q->fetch();if(!$row)return false;
        $now=time();$limits=self::limits($user);$created=strtotime($row['created_at_utc'].' UTC');$seen=strtotime($row['last_seen_at_utc'].' UTC');
        $expired=$created===false||$seen===false||$created>$now+30||$seen>$now+30||$created+$limits['absolute']<=$now||$seen+$limits['idle']<=$now||strtotime($row['absolute_expires_at_utc'].' UTC')<=$now;
        $needsFactor=(int)($user['is_admin']??0)===1||(int)($user['is_super_admin']??0)===1||(int)$row['has_totp']===1;
        if($expired||($needsFactor&&empty($row['mfa_verified_at_utc']))){self::revokeCurrent();return false;}
        if($seen<$now-60)db()->prepare('UPDATE user_sessions SET last_seen_at_utc=UTC_TIMESTAMP() WHERE session_hash=? AND user_id=? AND revoked_at_utc IS NULL')->execute([$hash,$id]);
        return true;
    }
    public static function revokeCurrent(): void
    {
        if(session_status()!==PHP_SESSION_ACTIVE||session_id()==='')return;
        db()->prepare('UPDATE user_sessions SET revoked_at_utc=COALESCE(revoked_at_utc,UTC_TIMESTAMP()) WHERE session_hash=?')->execute([self::hash()]);
        unset($_SESSION['view_as']);
    }
    public static function revokeAll(int $id,bool $incrementVersion=true): void
    {
        $pdo=db();$own=!$pdo->inTransaction();if($own)$pdo->beginTransaction();
        try{if($incrementVersion)$pdo->prepare('UPDATE users SET session_version=session_version+1 WHERE user_id=?')->execute([$id]);$pdo->prepare('UPDATE user_sessions SET revoked_at_utc=COALESCE(revoked_at_utc,UTC_TIMESTAMP()) WHERE user_id=?')->execute([$id]);if($own)$pdo->commit();}
        catch(Throwable $e){if($own&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
        if((int)($_SESSION['user_id']??0)===$id)unset($_SESSION['view_as']);
    }
    public static function revokeOwned(int $id,int $sessionId): void
    {
        db()->prepare('UPDATE user_sessions SET revoked_at_utc=COALESCE(revoked_at_utc,UTC_TIMESTAMP()) WHERE user_session_id=? AND user_id=?')->execute([$sessionId,$id]);
    }
    public static function listing(int $id): array
    {
        $q=db()->prepare('SELECT user_id,is_admin,is_super_admin FROM users WHERE user_id=?');$q->execute([$id]);$user=$q->fetch();if(!$user)return [];$limits=self::limits($user);
        $q=db()->prepare('SELECT user_session_id,user_agent,created_at_utc,last_seen_at_utc,absolute_expires_at_utc,revoked_at_utc,(session_hash=?) is_current FROM user_sessions WHERE user_id=? ORDER BY created_at_utc DESC,user_session_id DESC LIMIT 100');$q->execute([self::hash(),$id]);$rows=$q->fetchAll();
        foreach($rows as &$row)$row['is_expired']=min(strtotime($row['absolute_expires_at_utc'].' UTC'),strtotime($row['created_at_utc'].' UTC')+$limits['absolute'],strtotime($row['last_seen_at_utc'].' UTC')+$limits['idle'])<=time();unset($row);return $rows;
    }
    public static function recentPassword(int $id): bool
    {
        if((int)($_SESSION['user_id']??0)!==$id||session_status()!==PHP_SESSION_ACTIVE)return false;
        $q=db()->prepare('SELECT 1 FROM user_sessions s JOIN users u ON u.user_id=s.user_id WHERE s.session_hash=? AND s.user_id=? AND u.status="ACTIVE" AND u.system_identity_key IS NULL AND u.session_version=? AND s.revoked_at_utc IS NULL AND s.absolute_expires_at_utc>UTC_TIMESTAMP() AND s.password_confirmed_at_utc>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 MINUTE)');$q->execute([self::hash(),$id,(int)($_SESSION['session_version']??0)]);return (bool)$q->fetchColumn();
    }
    public static function confirmPassword(int $id,string $password): void
    {
        $user=AuthService::currentUser();if(!$user||(int)$user['user_id']!==$id)throw new UserFacingException('Sign in again before confirming your password.');
        PrivacyService::confirmPassword($id,$password);
        db()->prepare('UPDATE user_sessions SET password_confirmed_at_utc=UTC_TIMESTAMP() WHERE session_hash=? AND user_id=? AND revoked_at_utc IS NULL')->execute([self::hash(),$id]);
    }
    public static function requireRecentPassword(int $id): void
    {
        if(!self::recentPassword($id))throw new UserFacingException('Confirm your password in My Profile, then retry this change. Password confirmation lasts ten minutes.');
    }
}
