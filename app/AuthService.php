<?php
final class AuthService
{
    public static function boot(): void
    {
        if(session_status()===PHP_SESSION_ACTIVE)return;
        $secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
        session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
        session_start();
    }
    public static function csrfToken(): string
    {
        self::boot();if(empty($_SESSION['csrf_token']))$_SESSION['csrf_token']=bin2hex(random_bytes(24));return(string)$_SESSION['csrf_token'];
    }
    public static function requireCsrf(): void
    {
        self::boot();$sent=(string)($_POST['csrf_token']??'');
        if($sent===''||!hash_equals((string)($_SESSION['csrf_token']??''),$sent))throw new RuntimeException('Your session expired. Refresh and try again.');
    }
    public static function currentUser(): ?array
    {
        self::boot();$id=(int)($_SESSION['user_id']??0);if($id<=0)return null;
        $q=db()->prepare('SELECT user_id,email,display_name,is_admin,status,created_at_utc,last_login_at_utc FROM users WHERE user_id=?');$q->execute([$id]);$u=$q->fetch();
        if(!$u||$u['status']!=='ACTIVE'){self::logout();return null;}return$u;
    }
    public static function register(string $email,string $name,string $password,?string $inviteToken=null): array
    {
        $email=strtolower(trim($email));$name=trim($name);
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid email address.');
        if(mb_strlen($name)<2||mb_strlen($name)>120)throw new RuntimeException('Display name must be between 2 and 120 characters.');
        if(strlen($password)<10)throw new RuntimeException('Password must contain at least 10 characters.');
        $pdo=db();$invite=null;
        if($inviteToken){
            $q=$pdo->prepare('SELECT * FROM user_invitations WHERE token_hash=? AND accepted_at_utc IS NULL AND expires_at_utc>UTC_TIMESTAMP()');$q->execute([hash('sha256',$inviteToken)]);$invite=$q->fetch();
            if(!$invite||strtolower((string)$invite['email'])!==$email)throw new RuntimeException('The invitation is invalid, expired, or belongs to another email.');
        }
        $pdo->prepare('INSERT INTO users(email,password_hash,display_name,email_verified_at_utc) VALUES(?,?,?,?)')->execute([$email,password_hash($password,PASSWORD_DEFAULT),$name,$invite?gmdate('Y-m-d H:i:s'):null]);
        $id=(int)$pdo->lastInsertId();if($invite)$pdo->prepare('UPDATE user_invitations SET accepted_at_utc=UTC_TIMESTAMP() WHERE invitation_id=?')->execute([$invite['invitation_id']]);
        self::loginUserId($id);return self::currentUser()??[];
    }
    public static function login(string $email,string $password): array
    {
        $q=db()->prepare('SELECT * FROM users WHERE email=? LIMIT 1');$q->execute([strtolower(trim($email))]);$u=$q->fetch();
        if(!$u||$u['status']!=='ACTIVE'||!password_verify($password,(string)$u['password_hash']))throw new RuntimeException('Email or password is incorrect.');
        if(password_needs_rehash((string)$u['password_hash'],PASSWORD_DEFAULT))db()->prepare('UPDATE users SET password_hash=? WHERE user_id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$u['user_id']]);
        self::loginUserId((int)$u['user_id']);return self::currentUser()??[];
    }
    private static function loginUserId(int $id): void
    {
        self::boot();session_regenerate_id(true);$_SESSION['user_id']=$id;$_SESSION['csrf_token']=bin2hex(random_bytes(24));db()->prepare('UPDATE users SET last_login_at_utc=UTC_TIMESTAMP() WHERE user_id=?')->execute([$id]);
    }
    public static function logout(): void
    {
        self::boot();$_SESSION=[];if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);}session_destroy();
    }
    public static function requireLogin(): array
    {
        $u=self::currentUser();if(!$u){header('Location: ./?view=login');exit;}return$u;
    }
    public static function requireAdmin(): array
    {
        $u=self::requireLogin();if((int)$u['is_admin']!==1){http_response_code(403);exit('Administrator access is required.');}return$u;
    }
    public static function createInvitation(string $email,int $adminId): array
    {
        $email=strtolower(trim($email));if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid email address.');
        $token=bin2hex(random_bytes(24));db()->prepare('INSERT INTO user_invitations(email,token_hash,invited_by_user_id,expires_at_utc) VALUES(?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 7 DAY))')->execute([$email,hash('sha256',$token),$adminId]);
        $url=self::baseUrl().'/?view=register&invite='.rawurlencode($token);$sent=self::send($email,'You are invited to Toinky',"Create your account:\n\n".$url."\n\nThis link expires in 7 days.");return['url'=>$url,'sent'=>$sent];
    }
    public static function createPasswordResetForEmail(string $email): ?array
    {
        $q=db()->prepare('SELECT user_id,email FROM users WHERE email=? AND status="ACTIVE"');$q->execute([strtolower(trim($email))]);$u=$q->fetch();if(!$u)return null;
        $token=bin2hex(random_bytes(24));db()->prepare('INSERT INTO password_reset_tokens(user_id,token_hash,expires_at_utc) VALUES(?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 HOUR))')->execute([$u['user_id'],hash('sha256',$token)]);
        $url=self::baseUrl().'/?view=reset&token='.rawurlencode($token);$sent=self::send((string)$u['email'],'Reset your Toinky password',"Reset your password:\n\n".$url."\n\nThis link expires in 2 hours.");return['url'=>$url,'sent'=>$sent];
    }
    public static function resetPassword(string $token,string $password): void
    {
        if(strlen($password)<10)throw new RuntimeException('Password must contain at least 10 characters.');
        $pdo=db();$q=$pdo->prepare('SELECT * FROM password_reset_tokens WHERE token_hash=? AND used_at_utc IS NULL AND expires_at_utc>UTC_TIMESTAMP()');$q->execute([hash('sha256',$token)]);$r=$q->fetch();if(!$r)throw new RuntimeException('This reset link is invalid or expired.');
        $pdo->beginTransaction();try{$pdo->prepare('UPDATE users SET password_hash=? WHERE user_id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$r['user_id']]);$pdo->prepare('UPDATE password_reset_tokens SET used_at_utc=UTC_TIMESTAMP() WHERE password_reset_id=?')->execute([$r['password_reset_id']]);$pdo->commit();}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }
    public static function baseUrl(): string
    {
        $env=rtrim(trim((string)getenv('GAME2_BASE_URL')),'/');if($env!=='')return$env;
        $secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';$host=(string)($_SERVER['HTTP_HOST']??'localhost');$path=rtrim(str_replace('\\','/',dirname((string)($_SERVER['SCRIPT_NAME']??'/'))),'/');
        return($secure?'https':'http').'://'.$host.($path==='/'?'':$path);
    }
    private static function send(string $to,string $subject,string $body): bool
    {
        $from=trim((string)getenv('GAME2_MAIL_FROM'));if($from==='')$from='no-reply@'.preg_replace('/:\\d+$/','',(string)($_SERVER['HTTP_HOST']??'localhost'));
        return @mail($to,$subject,$body,"From: Toinky <".$from.">\r\nContent-Type: text/plain; charset=UTF-8");
    }
}
