<?php
require_once __DIR__.'/UserFacingException.php';
require_once __DIR__.'/EmailTemplateService.php';
require_once __DIR__.'/SecurityHeaders.php';
require_once __DIR__.'/PrivacyService.php';
require_once __DIR__.'/AccountSecurityService.php';
require_once __DIR__.'/RateLimitService.php';
require_once __DIR__.'/BotProtectionService.php';
require_once __DIR__.'/SessionService.php';
require_once __DIR__.'/TotpService.php';
final class AuthService
{
    public const SESSION_NAME='__Host-toinky';
    public static function boot(): void
    {
        if(session_status()===PHP_SESSION_ACTIVE)return;
        ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');ini_set('session.use_trans_sid','0');
        session_name(self::SESSION_NAME);
        session_set_cookie_params(['lifetime'=>0,'path'=>'/','domain'=>'','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
        session_start();
    }
    public static function csrfToken(): string
    {
        self::boot();if(empty($_SESSION['csrf_token']))$_SESSION['csrf_token']=bin2hex(random_bytes(24));return(string)$_SESSION['csrf_token'];
    }
    public static function requireCsrf(): void
    {
        self::boot();$sent=(string)($_POST['csrf_token']??'');
        if($sent===''||!hash_equals((string)($_SESSION['csrf_token']??''),$sent))throw new UserFacingException('Your session expired. Refresh and try again.');
    }
    public static function setting(string $key,string $default=''): string
    {
        try{$q=db()->prepare('SELECT setting_value FROM platform_settings WHERE setting_key=?');$q->execute([$key]);$value=$q->fetchColumn();return$value===false?$default:(string)$value;}catch(Throwable $ignored){return$default;}
    }
    public static function currentUser(): ?array
    {
        self::boot();$id=(int)($_SESSION['user_id']??0);if($id<=0)return null;SecurityHeaders::noStore();
        $q=db()->prepare('SELECT user_id,email,display_name,is_admin,is_super_admin,status,email_verified_at_utc,session_version,terms_version,privacy_version,policy_accepted_at_utc,system_identity_key,created_at_utc,last_login_at_utc FROM users WHERE user_id=?');$q->execute([$id]);$u=$q->fetch();
        if(!$u||$u['status']!=='ACTIVE'||$u['system_identity_key']!==null||(int)($_SESSION['session_version']??0)!==(int)$u['session_version']||!SessionService::valid($u)){self::logout();return null;}return$u;
    }
    public static function register(string $email,string $name,string $password,?string $inviteToken=null,?string $gameInviteToken=null,array $privacy=[]): array
    {
        PrivacyService::requireRegistrationConsent($privacy);RateLimitService::registration();
        $email=strtolower(trim($email));$name=trim($name);
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||$email==='privacy-anonymous@toinky.invalid')throw new UserFacingException('Enter a valid email address.');
        if(mb_strlen($name)<2||mb_strlen($name)>120)throw new UserFacingException('Display name must be between 2 and 120 characters.');
        if(strlen($password)<10)throw new UserFacingException('Password must contain at least 10 characters.');
        $pdo=db();$invite=null;$gameInvite=null;
        // Use the same expensive password work and outward completion for both
        // new and existing addresses. Never sign a registrant in automatically.
        $passwordHash=password_hash($password,PASSWORD_DEFAULT);
        if($inviteToken){
            $q=$pdo->prepare('SELECT * FROM user_invitations WHERE token_hash=? AND delivered_at_utc IS NOT NULL AND accepted_at_utc IS NULL AND expires_at_utc>UTC_TIMESTAMP()');$q->execute([hash('sha256',$inviteToken)]);$invite=$q->fetch();
            if(!$invite||strtolower((string)$invite['email'])!==$email)throw new UserFacingException('The invitation is invalid, expired, or belongs to another email.');
        }
        if($gameInviteToken){$q=$pdo->prepare('SELECT gi.game_invitation_id,gi.game_id,gi.email,g.status,p.max_players,(SELECT COUNT(*) FROM game_players gp WHERE gp.game_id=g.game_id) player_count FROM game_invitations gi JOIN games g ON g.game_id=gi.game_id JOIN simulations s ON s.simulation_id=g.simulation_id JOIN plots p ON p.plot_id=s.plot_id WHERE gi.token_hash=? AND gi.delivered_at_utc IS NOT NULL AND gi.accepted_at_utc IS NULL AND gi.expires_at_utc>UTC_TIMESTAMP() AND g.deleted_at_utc IS NULL');$q->execute([hash('sha256',$gameInviteToken)]);$gameInvite=$q->fetch();if(!$gameInvite||strtolower((string)$gameInvite['email'])!==$email||$gameInvite['status']!=='LOBBY')throw new UserFacingException('The game invitation is invalid, expired, or belongs to another email.');if((int)$gameInvite['player_count']>=(int)$gameInvite['max_players'])throw new UserFacingException('That game lobby is already full.');}
        $existing=$pdo->prepare('SELECT user_id FROM users WHERE email=?');$existing->execute([$email]);
        if($existing->fetchColumn())return self::existingRegistrationNotice($email);
        // Only a successfully emailed, address-matched invitation proves mailbox ownership.
        $pdo->beginTransaction();try{$pdo->prepare('INSERT INTO users(email,password_hash,display_name,email_verified_at_utc) VALUES(?,?,?,?)')->execute([$email,$passwordHash,$name,($invite||$gameInvite)?gmdate('Y-m-d H:i:s'):null]);$id=(int)$pdo->lastInsertId();PrivacyService::recordConsent($id,$privacy);PrivacyService::rememberEmail($id,$email);if($invite)$pdo->prepare('UPDATE user_invitations SET accepted_at_utc=UTC_TIMESTAMP() WHERE invitation_id=?')->execute([$invite['invitation_id']]);$gameId=0;if($gameInvite){$gameId=(int)$gameInvite['game_id'];$pdo->prepare('INSERT INTO game_players(game_id,user_id) VALUES(?,?)')->execute([$gameId,$id]);$pdo->prepare('UPDATE game_invitations SET accepted_by_user_id=?,accepted_at_utc=UTC_TIMESTAMP() WHERE game_invitation_id=?')->execute([$id,$gameInvite['game_invitation_id']]);}$pdo->commit();}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();if($e instanceof PDOException&&(int)($e->errorInfo[1]??0)===1062){$existing->execute([$email]);if($existing->fetchColumn())return self::existingRegistrationNotice($email);}throw$e;}
        unset($_SESSION['privacy_age_eligible_until']);if(!$invite&&!$gameInvite)AccountSecurityService::requestVerification($id);$q=$pdo->prepare('SELECT user_id,email,display_name,email_verified_at_utc FROM users WHERE user_id=?');$q->execute([$id]);$u=$q->fetch()?:[];self::sendTemplatedMessage($email,'WELCOME',['recipient_name'=>$name,'action_url'=>self::baseUrl().'/?view=login','action_label'=>'Sign in to Toinky','message'=>'Your Toinky account is ready. Explore stories, host a game, and build shared worlds.'],null,$id);$u['_game_id']=$gameId;return$u;
    }
    private static function existingRegistrationNotice(string $email): array
    {
        unset($_SESSION['privacy_age_eligible_until']);
        try{RateLimitService::hit('REGISTER_NOTICE',$email,3,3600,3600);self::sendAccountMessage($email,'Toinky Registration Notice','Someone submitted a registration using your email address. Your existing account and password were not changed. If this was you, open Toinky directly to sign in or use the password-reset page.','REGISTRATION_NOTICE');}catch(RateLimitException $ignored){}
        return [];
    }
    public static function login(string $email,string $password): array
    {
        RateLimitService::checkSignIn($email);
        $q=db()->prepare('SELECT * FROM users WHERE email=? LIMIT 1');$q->execute([strtolower(trim($email))]);$u=$q->fetch();
        // A valid fixed dummy hash keeps unknown-account failures on the same password_verify path.
        $valid=password_verify($password,$u?(string)$u['password_hash']:'$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if(!$u||!$valid){RateLimitService::failedSignIn($email);throw new UserFacingException('Email or password is incorrect.');}
        if($u['status']==='DISABLED')throw new UserFacingException('This account has been deactivated. Contact support if you would like to reactivate it.');
        if($u['status']!=='ACTIVE')throw new UserFacingException('Email or password is incorrect.');
        if(password_needs_rehash((string)$u['password_hash'],PASSWORD_DEFAULT))db()->prepare('UPDATE users SET password_hash=? WHERE user_id=? AND password_hash=? AND session_version=?')->execute([password_hash($password,PASSWORD_DEFAULT),$u['user_id'],$u['password_hash'],$u['session_version']]);
        if(self::superAdminNeedsTwoFactor($u)){
            self::boot();SessionService::revokeCurrent();session_regenerate_id(true);unset($_SESSION['user_id'],$_SESSION['session_version'],$_SESSION['view_as']);
            $_SESSION['csrf_token']=bin2hex(random_bytes(24));$_SESSION['two_factor_pending_user_id']=(int)$u['user_id'];$_SESSION['two_factor_pending_version']=(int)$u['session_version'];$_SESSION['two_factor_started_at']=time();$_SESSION['two_factor_failed_attempts']=0;
            $_SESSION['two_factor_method']=TotpService::enabled((int)$u['user_id'])&&TotpService::available()?'totp':'email';
            if($_SESSION['two_factor_method']==='email')self::issueTwoFactorCode((int)$u['user_id']);
            return ['two_factor_required'=>true,'user_id'=>(int)$u['user_id'],'email'=>(string)$u['email']];
        }
        self::loginUserId((int)$u['user_id'],(int)$u['session_version']);return self::currentUser()??[];
    }
    public static function isSuperAdmin(int $userId): bool
    {
        $q=db()->prepare('SELECT is_super_admin FROM users WHERE user_id=?');$q->execute([$userId]);return (int)$q->fetchColumn()===1;
    }
    public static function superAdminNeedsTwoFactor(array $user): bool
    {
        return (int)($user['is_admin']??0)===1||(int)($user['is_super_admin']??0)===1||TotpService::enabled((int)($user['user_id']??0));
    }
    public static function pendingTwoFactorUserId(): int
    {
        self::boot();$id=(int)($_SESSION['two_factor_pending_user_id']??0);
        if($id<=0||(int)($_SESSION['two_factor_started_at']??0)<=time()-600||(int)($_SESSION['two_factor_failed_attempts']??0)>=5){self::clearPendingFactor();return 0;}
        $q=db()->prepare('SELECT session_version FROM users WHERE user_id=? AND status="ACTIVE"');$q->execute([$id]);$version=$q->fetchColumn();
        if($version===false||(int)$version!==(int)($_SESSION['two_factor_pending_version']??0)){self::clearPendingFactor();return 0;}return $id;
    }
    public static function issueTwoFactorCode(int $userId): void
    {
        RateLimitService::codeResend($userId);
        $q=db()->prepare('SELECT email,display_name FROM users WHERE user_id=? AND status="ACTIVE" AND system_identity_key IS NULL');$q->execute([$userId]);$u=$q->fetch();
        if(!$u)throw new UserFacingException('Account not found.');
        db()->prepare('UPDATE user_two_factor_codes SET used_at_utc=UTC_TIMESTAMP() WHERE user_id=? AND used_at_utc IS NULL AND expires_at_utc>UTC_TIMESTAMP()')->execute([$userId]);
        $code=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
        db()->prepare('INSERT INTO user_two_factor_codes(user_id,code_hash,email_hash,expires_at_utc) VALUES(?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 10 MINUTE))')->execute([$userId,hash('sha256',$code),hash('sha256',strtolower(trim((string)$u['email'])))]);
        $sent=self::sendTemplatedMessage((string)$u['email'],'TWO_FACTOR_CODE',['recipient_name'=>(string)($u['display_name']??'there'),'action_url'=>self::baseUrl().'/?view=login','action_label'=>'Sign in to Toinky','message'=>'Your Toinky sign-in code is '.$code.'. Enter it to finish signing in.','expiry_text'=>'This code expires in 10 minutes.'],null,$userId);
        if(!$sent)throw new UserFacingException('We could not send the sign-in code. Try again in a few minutes.');
    }
    public static function resendTwoFactorCode(int $userId): void
    {
        if(self::pendingTwoFactorUserId()!==$userId)throw new UserFacingException('Your sign-in session expired. Sign in again.');
        self::issueTwoFactorCode($userId);$_SESSION['two_factor_method']='email';
    }
    public static function verifyTwoFactorCode(int $userId,string $code,string $method='email'): array
    {
        if(self::pendingTwoFactorUserId()!==$userId)throw new UserFacingException('Your sign-in session expired. Sign in again.');
        if(!in_array($method,['email','totp','recovery'],true))throw new UserFacingException('Choose a valid second-factor method.');
        $expectedVersion=(int)$_SESSION['two_factor_pending_version'];$passwordAt=(int)$_SESSION['two_factor_started_at'];
        if($method!=='email'){
            if(!TotpService::verify($userId,$code,$method==='recovery')){$_SESSION['two_factor_failed_attempts']=(int)($_SESSION['two_factor_failed_attempts']??0)+1;if($_SESSION['two_factor_failed_attempts']>=5)self::clearPendingFactor();throw new UserFacingException('That code is incorrect or expired. Sign in again if you have used five attempts.');}
            db()->prepare('UPDATE user_two_factor_codes SET used_at_utc=UTC_TIMESTAMP() WHERE user_id=? AND used_at_utc IS NULL')->execute([$userId]);
            self::clearPendingFactor();self::loginUserId($userId,$expectedVersion,true,$passwordAt);return self::currentUser()??[];
        }
        RateLimitService::codeAttempt($userId);
        $code=trim($code);$pdo=db();$pdo->beginTransaction();$valid=false;$void=false;
        try{
            $account=$pdo->prepare('SELECT email,session_version FROM users WHERE user_id=? AND status="ACTIVE" AND system_identity_key IS NULL FOR UPDATE');$account->execute([$userId]);$mailbox=$account->fetch();
            $q=$pdo->prepare('SELECT user_two_factor_code_id,code_hash,email_hash,failed_attempts FROM user_two_factor_codes WHERE user_id=? AND used_at_utc IS NULL AND expires_at_utc>UTC_TIMESTAMP() ORDER BY user_two_factor_code_id DESC LIMIT 1 FOR UPDATE');$q->execute([$userId]);$row=$q->fetch();
            if($row){
                $valid=$mailbox&&(int)$mailbox['session_version']===$expectedVersion&&preg_match('/^[0-9]{6}$/D',$code)===1&&hash_equals((string)$row['code_hash'],hash('sha256',$code))&&$row['email_hash']!==null&&hash_equals((string)$row['email_hash'],hash('sha256',strtolower(trim((string)$mailbox['email']))));
                $failures=(int)$row['failed_attempts']+($valid?0:1);$void=$failures>=5;
                $pdo->prepare('UPDATE user_two_factor_codes SET failed_attempts=?,used_at_utc=IF(?,UTC_TIMESTAMP(),NULL) WHERE user_two_factor_code_id=?')->execute([$failures,($valid||$void)?1:0,$row['user_two_factor_code_id']]);
            }else $void=true;
            if($valid)$pdo->prepare('UPDATE users SET email_verified_at_utc=COALESCE(email_verified_at_utc,UTC_TIMESTAMP()) WHERE user_id=?')->execute([$userId]);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        if(!$valid){$_SESSION['two_factor_failed_attempts']=(int)($_SESSION['two_factor_failed_attempts']??0)+1;if($void||$_SESSION['two_factor_failed_attempts']>=5)self::clearPendingFactor();throw new UserFacingException('That code is incorrect or expired. Sign in again if you have used five attempts.');}
        self::clearPendingFactor();self::loginUserId($userId,$expectedVersion,true,$passwordAt);return self::currentUser()??[];
    }
    private static function clearPendingFactor(): void
    {
        foreach(['two_factor_pending_user_id','two_factor_pending_version','two_factor_started_at','two_factor_failed_attempts','two_factor_method'] as $key)unset($_SESSION[$key]);
    }
    private static function loginUserId(int $id,int $expectedVersion,bool $mfa=false,?int $passwordAt=null): void
    {
        $q=db()->prepare('SELECT session_version FROM users WHERE user_id=? AND status="ACTIVE"');$q->execute([$id]);$version=$q->fetchColumn();
        if($version===false||(int)$version!==$expectedVersion)throw new UserFacingException('Your sign-in session expired. Sign in again.');
        self::boot();SessionService::revokeCurrent();session_regenerate_id(true);self::clearPendingFactor();unset($_SESSION['view_as']);$_SESSION['user_id']=$id;$_SESSION['session_version']=$expectedVersion;$_SESSION['csrf_token']=bin2hex(random_bytes(24));SessionService::register($id,$mfa,$passwordAt);db()->prepare('UPDATE users SET last_login_at_utc=UTC_TIMESTAMP() WHERE user_id=?')->execute([$id]);
    }
    public static function logout(): void
    {
        self::boot();try{SessionService::revokeCurrent();}catch(Throwable $e){Logger::exception($e,'Session revocation failed during sign-out');}finally{$_SESSION=[];if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);}session_destroy();}
    }
    public static function deactivateAccount(int $userId): void
    {
        $q=db()->prepare('SELECT is_admin,is_super_admin,status FROM users WHERE user_id=?');$q->execute([$userId]);$u=$q->fetch();
        if(!$u||$u['status']!=='ACTIVE')throw new UserFacingException('Your account is not active.');
        if((int)($u['is_super_admin']??0)===1)throw new UserFacingException('Super Admin accounts cannot be deactivated.');
        if((int)$u['is_admin']===1)throw new UserFacingException('Administrator accounts cannot be deactivated here.');
        $pdo=db();$own=!$pdo->inTransaction();if($own)$pdo->beginTransaction();try{$pdo->prepare('UPDATE users SET status="DISABLED" WHERE user_id=? AND status="ACTIVE"')->execute([$userId]);SessionService::revokeAll($userId);if($own)$pdo->commit();}catch(Throwable $e){if($own&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    public static function requireLogin(): array
    {
        $u=self::currentUser();if(!$u){header('Location: ./?view=login');exit;}
        if(PrivacyService::needsConsent($u)){
            $script=basename((string)($_SERVER['SCRIPT_NAME']??''));$action=(string)($_POST['action']??'');
            $privacyProfile=$script==='profile.php'&&(($_SERVER['REQUEST_METHOD']??'GET')==='GET'||in_array($action,['export_data','delete_account','withdraw_consent','update_display_name','upload_profile_photo','request_email_change','confirm_password','sign_out_everywhere','revoke_session','begin_authenticator','enable_authenticator','replace_recovery_codes','disable_authenticator'],true));
            $consentPage=in_array($script,['index.php',''],true)&&($_GET['view']??'')==='consent';
            if(!$privacyProfile&&!$consentPage){header('Cache-Control: no-store');header('Location: ./?view=consent',true,303);exit;}
        }
        return$u;
    }
    public static function requireAdmin(): array
    {
        $u=self::requireLogin();if((int)$u['is_admin']!==1){http_response_code(403);exit('Administrator access is required.');}return$u;
    }
    public static function requireSuperAdmin(): array
    {
        $user=self::requireLogin();if((int)$user['is_super_admin']!==1){http_response_code(403);exit('Super Admin access is required.');}return $user;
    }
    public static function createInvitation(string $email,int $adminId): array
    {
        AccountSecurityService::requireVerifiedEmail($adminId);RateLimitService::invitation($adminId,$email);
        $email=strtolower(trim($email));if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new UserFacingException('Enter a valid email address.');
        $token=bin2hex(random_bytes(24));db()->prepare('INSERT INTO user_invitations(email,token_hash,invited_by_user_id,expires_at_utc) VALUES(?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 7 DAY))')->execute([$email,hash('sha256',$token),$adminId]);
        $id=(int)db()->lastInsertId();
        $q=db()->prepare('SELECT 1 FROM users WHERE email=?');$q->execute([$email]);
        if($q->fetchColumn()){
            $sent=self::sendAccountMessage($email,'You Were Invited To Toinky','Someone invited this email address to Toinky. You already have an account; open Toinky directly to sign in. Your account, password and memberships have not changed.','INVITATION',$adminId);
            if($sent)db()->prepare('UPDATE user_invitations SET delivered_at_utc=UTC_TIMESTAMP() WHERE invitation_id=?')->execute([$id]);
            return ['sent'=>$sent];
        }
        $url=self::baseUrl().'/?view=register&invite='.rawurlencode($token);$sent=self::sendTemplatedMessage($email,'INVITATION',['recipient_name'=>'there','action_url'=>$url,'action_label'=>'Create your account','message'=>'An administrator invited you to create a Toinky account.','expiry_text'=>'This link expires in 7 days.'],null,$adminId);if($sent)db()->prepare('UPDATE user_invitations SET delivered_at_utc=UTC_TIMESTAMP() WHERE invitation_id=?')->execute([$id]);return['sent'=>$sent];
    }
    public static function createPasswordResetForEmail(string $email): array
    {
        return AccountSecurityService::requestPasswordReset($email);
    }
    public static function resetPassword(string $token,string $password): void
    {
        AccountSecurityService::resetPassword($token,$password);
    }
    public static function hasRole(int $userId,string $roleCode): bool
    {
        $q=db()->prepare('SELECT 1 FROM user_platform_roles ur JOIN platform_roles r ON r.platform_role_id=ur.platform_role_id WHERE ur.user_id=? AND r.role_code=? AND r.scope_type="PLATFORM"');
        $q->execute([$userId,strtoupper(trim($roleCode))]);return(bool)$q->fetchColumn();
    }
    /* View-as preview: an admin may preview the site as a Player or a
    Writer. Presentation only -- the session flag changes what menus and
    data the templates show, never the real role. Every authorization
    check (requireAdmin, requireWriter, hasRole, deactivateAccount) keeps
    reading the real user row, so a preview cannot grant or remove access. */
    public static function viewAsMode(): ?string
    {
        self::boot();$mode=(string)($_SESSION['view_as']??'');return in_array($mode,['PLAYER','WRITER'],true)?$mode:null;
    }
    public static function setViewAs(string $mode): void
    {
        self::boot();$mode=strtoupper(trim($mode));if(in_array($mode,['PLAYER','WRITER'],true)){$_SESSION['view_as']=$mode;}else{unset($_SESSION['view_as']);}
    }
    public static function displayIsAdmin(array $user): bool
    {
        return (int)($user['is_admin']??0)===1&&self::viewAsMode()===null;
    }
    public static function displayIsWriter(array $user): bool
    {
        return ((int)($user['is_admin']??0)===1||self::hasRole((int)$user['user_id'],'WRITER'))&&self::viewAsMode()!=='PLAYER';
    }
    public static function displayIsSuperAdmin(array $user): bool
    {
        return (int)($user['is_super_admin']??0)===1&&self::viewAsMode()===null;
    }
    public static function requireWriter(): array
    {
        $u=self::requireLogin();if((int)$u['is_admin']!==1&&!self::hasRole((int)$u['user_id'],'WRITER')){http_response_code(403);exit('Story Writer access is required.');}return$u;
    }
    public static function setRole(int $userId,string $roleCode,bool $enabled,int $assignedBy): void
    {
        if(PHP_SAPI!=='cli')SessionService::requireRecentPassword($assignedBy);
        $q=db()->prepare('SELECT platform_role_id FROM platform_roles WHERE role_code=? AND scope_type="PLATFORM"');$q->execute([strtoupper(trim($roleCode))]);$roleId=(int)$q->fetchColumn();if($roleId<1)throw new UserFacingException('Unknown platform role.');
        $pdo=db();$own=!$pdo->inTransaction();if($own)$pdo->beginTransaction();try{
            $q=$pdo->prepare('SELECT user_id FROM users WHERE user_id=? AND system_identity_key IS NULL FOR UPDATE');$q->execute([$userId]);if(!$q->fetchColumn())throw new UserFacingException('That account cannot be changed.');
            if($enabled){$q=$pdo->prepare('INSERT IGNORE INTO user_platform_roles(user_id,platform_role_id,assigned_by_user_id) VALUES(?,?,?)');$q->execute([$userId,$roleId,$assignedBy]);}
            else{$q=$pdo->prepare('DELETE FROM user_platform_roles WHERE user_id=? AND platform_role_id=?');$q->execute([$userId,$roleId]);}
            if($q->rowCount()>0)SessionService::revokeAll($userId);if($own)$pdo->commit();
        }catch(Throwable $e){if($own&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    public static function toggleAccountFlag(int $actor,int $target,string $flag): void
    {
        $admin=self::requireAdmin();if((int)$admin['user_id']!==$actor)throw new UserFacingException('Administrator access is required.');SessionService::requireRecentPassword($actor);
        if(!in_array($flag,['status','is_admin'],true))throw new InvalidArgumentException('Invalid account flag.');
        if($actor===$target)throw new UserFacingException('You cannot disable or remove administrator access from your own account.');
        $pdo=db();$pdo->beginTransaction();try{$q=$pdo->prepare('SELECT is_super_admin,system_identity_key FROM users WHERE user_id=? FOR UPDATE');$q->execute([$target]);$user=$q->fetch();
            if(!$user||$user['system_identity_key']!==null)throw new UserFacingException('That account cannot be changed.');if((int)$user['is_super_admin']===1)throw new UserFacingException('Super Admin access cannot be disabled or removed.');
            $sql=$flag==='status'?'UPDATE users SET status=IF(status="ACTIVE","DISABLED","ACTIVE") WHERE user_id=?':'UPDATE users SET is_admin=IF(is_admin=1,0,1) WHERE user_id=?';$pdo->prepare($sql)->execute([$target]);SessionService::revokeAll($target);$pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    public static function requestWriterAccess(int $userId,string $reason=''): void
    {
        if(self::hasRole($userId,'WRITER'))throw new UserFacingException('You already have Story Writer access.');$q=db()->prepare('SELECT 1 FROM writer_access_requests WHERE user_id=? AND status="PENDING"');$q->execute([$userId]);if($q->fetchColumn())throw new UserFacingException('Your Story Writer request is already awaiting review.');db()->prepare('INSERT INTO writer_access_requests(user_id,request_text) VALUES(?,?)')->execute([$userId,trim($reason)?:null]);
    }
    public static function reviewWriterRequest(int $requestId,bool $approve,int $adminId,string $notes=''): void
    {
        $pdo=db();$q=$pdo->prepare('SELECT user_id,status FROM writer_access_requests WHERE writer_request_id=?');$q->execute([$requestId]);$r=$q->fetch();if(!$r||$r['status']!=='PENDING')throw new UserFacingException('That Story Writer request is no longer pending.');$pdo->beginTransaction();try{$pdo->prepare('UPDATE writer_access_requests SET status=?,reviewed_by_user_id=?,reviewer_notes=?,reviewed_at_utc=UTC_TIMESTAMP() WHERE writer_request_id=?')->execute([$approve?'APPROVED':'DECLINED',$adminId,trim($notes)?:null,$requestId]);if($approve)self::setRole((int)$r['user_id'],'WRITER',true,$adminId);$pdo->commit();}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }
    public static function createGameInvitation(string $email,int $gameId,int $senderUserId): array
    {
        AccountSecurityService::requireVerifiedEmail($senderUserId);RateLimitService::invitation($senderUserId,$email);
        $email=strtolower(trim($email));if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new UserFacingException('Enter a valid email address.');$q=db()->prepare('SELECT g.join_code,g.status,p.title,p.max_players,u.display_name host_name,(SELECT COUNT(*) FROM game_players gp WHERE gp.game_id=g.game_id) player_count FROM games g JOIN simulations s ON s.simulation_id=g.simulation_id JOIN plots p ON p.plot_id=s.plot_id JOIN users u ON u.user_id=g.host_user_id WHERE g.game_id=? AND g.host_user_id=? AND g.deleted_at_utc IS NULL');$q->execute([$gameId,$senderUserId]);$game=$q->fetch();if(!$game||$game['status']!=='LOBBY')throw new UserFacingException('Only the host can invite players while the lobby is open.');if((int)$game['player_count']>=(int)$game['max_players'])throw new UserFacingException('The lobby is already full.');$token=bin2hex(random_bytes(24));db()->prepare('INSERT INTO game_invitations(game_id,email,token_hash,invited_by_user_id,expires_at_utc) VALUES(?,?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 7 DAY))')->execute([$gameId,$email,hash('sha256',$token),$senderUserId]);$id=(int)db()->lastInsertId();$url=self::baseUrl().'/?view=register&game_invite='.rawurlencode($token);$sent=self::sendTemplatedMessage($email,'GAME_INVITATION',['recipient_name'=>'there','action_url'=>$url,'action_label'=>'Join the story','message'=>$game['host_name'].' invited you to play “'.$game['title'].'” on Toinky. Game code: '.$game['join_code'].'.','story_title'=>$game['title'],'game_code'=>$game['join_code'],'expiry_text'=>'This link expires in 7 days.'],$gameId,$senderUserId);if($sent)db()->prepare('UPDATE game_invitations SET delivered_at_utc=UTC_TIMESTAMP() WHERE game_invitation_id=?')->execute([$id]);return['sent'=>$sent];
    }
    public static function baseUrl(): string
    {
        $env=rtrim(trim((string)getenv('GAME2_BASE_URL')),'/');if($env!=='')return$env;
        $secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';$host=(string)($_SERVER['HTTP_HOST']??'localhost');$path=rtrim(str_replace('\\','/',dirname((string)($_SERVER['SCRIPT_NAME']??'/'))),'/');
        return($secure?'https':'http').'://'.$host.($path==='/'?'':$path);
    }
    public static function sendAccountMessage(string $to,string $subject,string $body,string $type='ACCOUNT',?int $senderId=null): bool
    {
        return self::send($to,$subject,$body,$type,null,$senderId);
    }
    public static function sendTemplatedMessage(string $to,string $type,array $data=[],?int $gameId=null,?int $senderId=null): bool
    {
        return self::send($to,'','',$type,$gameId,$senderId,$data);
    }
    private static function send(string $to,string $subject,string $body,string $type='ACCOUNT',?int $gameId=null,?int $senderId=null,array $templateData=[]): bool
    {
        $htmlBody='';$from=trim((string)getenv('GAME2_MAIL_FROM'));$fromName='Toinky';$error=null;$sent=false;
        try{
            if($templateData!==[]){$composed=EmailTemplateService::compose($type,$templateData);$subject=$composed['subject'];$body=$composed['text'];$htmlBody=$composed['html'];}
            if($from===''){try{$q=db()->query('SELECT setting_key,setting_value FROM platform_settings WHERE setting_key IN("MAIL_FROM_ADDRESS","MAIL_FROM_NAME")');foreach($q->fetchAll()as$r){if($r['setting_key']==='MAIL_FROM_ADDRESS')$from=trim((string)$r['setting_value']);if($r['setting_key']==='MAIL_FROM_NAME')$fromName=trim((string)$r['setting_value']);}}catch(Throwable$ignored){}}if($from==='')$from='accounts@toinky.com';
            if(trim((string)getenv('GAME2_SMTP_HOST'))!==''&&trim((string)getenv('GAME2_SMTP_USERNAME'))!==''&&trim((string)getenv('GAME2_SMTP_PASSWORD'))!=='')$sent=self::sendViaSmtp($to,$subject,$body,$from,$fromName,$htmlBody);
            else{$boundary=$htmlBody===''?null:'toinky-'.bin2hex(random_bytes(8));$mailBody=$boundary===null?$body:self::multipartBody($body,$htmlBody,$boundary);$sent=@mail($to,$subject,$mailBody,self::mailHeaders($from,$fromName,$boundary));}
        }catch(Throwable$e){$error=public_error($e);}
        if(!$sent&&$error===null)$error='Mail sender returned false';
        try{db()->prepare('INSERT INTO email_delivery_log(recipient_email,message_type,related_game_id,sent_by_user_id,delivery_status,error_text) VALUES(?,?,?,?,?,?)')->execute([$to,$type,$gameId,$senderId,$sent?'SENT':'FAILED',$sent?null:$error]);}catch(Throwable$ignored){}return$sent;
    }
    private static function mailHeaders(string $from,string $fromName,?string $boundary=null): string
    {
        $content=$boundary===null?'text/plain; charset=UTF-8':'multipart/alternative; boundary="'.$boundary.'"';return "From: ".self::formatAddress($from,$fromName)."\r\nReply-To: ".$from."\r\nMIME-Version: 1.0\r\nContent-Type: ".$content;
    }
    private static function formatAddress(string $email,string $name=''): string
    {
        $name=str_replace(["\r","\n"],'',trim($name));$email=str_replace(["\r","\n"],'',trim($email));if($name==='')return$email;return '"'.str_replace('"','\"',$name).'" <'.$email.'>';
    }
    private static function sendViaSmtp(string $to,string $subject,string $body,string $from,string $fromName,string $htmlBody=''): bool
    {
        $host=trim((string)getenv('GAME2_SMTP_HOST'));$port=(int)(getenv('GAME2_SMTP_PORT')?:465);$user=trim((string)getenv('GAME2_SMTP_USERNAME'));$pass=(string)getenv('GAME2_SMTP_PASSWORD');$timeout=15;
        $remote=($port===465?'ssl://':'').$host.':'.$port;$socket=@stream_socket_client($remote,$errno,$errstr,$timeout,STREAM_CLIENT_CONNECT);if(!$socket)throw new RuntimeException('SMTP connection failed: '.$errstr);
        stream_set_timeout($socket,$timeout);
        $read=static function()use($socket):string{$response='';while(($line=fgets($socket,515))!==false){$response.=$line;if(strlen($line)>=4&&$line[3]===' ')break;}return$response;};
        $expect=static function(array$codes,string$step)use($read):string{$response=$read();$code=substr($response,0,3);if(!in_array($code,$codes,true))throw new RuntimeException('SMTP '.$step.' failed: '.trim($response));return$response;};
        $send=static function(string$command)use($socket):void{fwrite($socket,$command."\r\n");};
        $expect(['220'],'greeting');$helo=(string)($_SERVER['SERVER_NAME']??'toinky.com');$send('EHLO '.$helo);$expect(['250'],'EHLO');
        if($port===587){$send('STARTTLS');$expect(['220'],'STARTTLS');if(!stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new UserFacingException('SMTP TLS negotiation failed.');$send('EHLO '.$helo);$expect(['250'],'EHLO after STARTTLS');}
        $send('AUTH LOGIN');$expect(['334'],'AUTH LOGIN');$send(base64_encode($user));$expect(['334'],'SMTP username');$send(base64_encode($pass));$expect(['235'],'SMTP password');
        $send('MAIL FROM:<'.$from.'>');$expect(['250'],'MAIL FROM');$send('RCPT TO:<'.$to.'>');$expect(['250','251'],'RCPT TO');$send('DATA');$expect(['354'],'DATA');
        $boundary='toinky-'.bin2hex(random_bytes(8));$headers=['From: '.self::formatAddress($from,$fromName),'Reply-To: '.$from,'To: '.$to,'Subject: '.self::encodeHeader($subject),'MIME-Version: 1.0'];if($htmlBody===''){$headers[]='Content-Type: text/plain; charset=UTF-8';$headers[]='Content-Transfer-Encoding: 8bit';$message=implode("\r\n",$headers)."\r\n\r\n".self::smtpBody($body);}else{$headers[]='Content-Type: multipart/alternative; boundary="'.$boundary.'"';$message=implode("\r\n",$headers)."\r\n\r\n".self::multipartBody($body,$htmlBody,$boundary);}$message.="\r\n.";$send($message);$expect(['250'],'message body');$send('QUIT');fclose($socket);return true;
    }
    private static function multipartBody(string $text,string $html,string $boundary): string{return'--'.$boundary."\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n".self::smtpBody($text)."\r\n--".$boundary."\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n".self::smtpBody($html)."\r\n--".$boundary.'--';}
    private static function encodeHeader(string $value): string
    {
        return preg_match('/[^\x20-\x7E]/',$value)?'=?UTF-8?B?'.base64_encode($value).'?=':$value;
    }
    private static function smtpBody(string $body): string
    {
        $body=str_replace(["\r\n","\r"],"\n",$body);$lines=explode("\n",$body);foreach($lines as&$line){if(str_starts_with($line,'.'))$line='.'.$line;}unset($line);return implode("\r\n",$lines);
    }
}
