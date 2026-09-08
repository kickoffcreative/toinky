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
    public static function setting(string $key,string $default=''): string
    {
        try{$q=db()->prepare('SELECT setting_value FROM platform_settings WHERE setting_key=?');$q->execute([$key]);$value=$q->fetchColumn();return$value===false?$default:(string)$value;}catch(Throwable $ignored){return$default;}
    }
    public static function captchaMode(): string
    {
        $mode=strtoupper(self::setting('SECURITY_CAPTCHA_MODE','BUILTIN'));return in_array($mode,['BUILTIN','VISUAL','GOOGLE','OFF'],true)?$mode:'BUILTIN';
    }
    public static function builtInCaptchaQuestion(): string
    {
        self::boot();if(!isset($_SESSION['login_captcha_answer'])){$a=random_int(2,9);$b=random_int(1,9);$_SESSION['login_captcha_answer']=$a+$b;$_SESSION['login_captcha_question']='What is '.$a.' + '.$b.'?';}return(string)$_SESSION['login_captcha_question'];
    }
    public static function visualCaptchaCode(): string
    {
        self::boot();if(!empty($_GET['captcha_refresh']))unset($_SESSION['visual_captcha_code'],$_SESSION['visual_captcha_created']);if(empty($_SESSION['visual_captcha_code'])||empty($_SESSION['visual_captcha_created'])||time()-(int)$_SESSION['visual_captcha_created']>600){$alphabet='ABCDEFGHJKLMNPQRSTUVWXYZ23456789';$code='';for($i=0;$i<6;$i++)$code.=$alphabet[random_int(0,strlen($alphabet)-1)];$_SESSION['visual_captcha_code']=$code;$_SESSION['visual_captcha_created']=time();}return(string)$_SESSION['visual_captcha_code'];
    }
    public static function verifyLoginCaptcha(array $post): void
    {
        $mode=self::captchaMode();if($mode==='OFF')return;if($mode==='BUILTIN'){self::boot();$expected=(string)($_SESSION['login_captcha_answer']??'');unset($_SESSION['login_captcha_answer'],$_SESSION['login_captcha_question']);if($expected===''||!hash_equals($expected,trim((string)($post['captcha_answer']??''))))throw new RuntimeException('The security answer was incorrect. Try the new question.');return;}if($mode==='VISUAL'){self::boot();$expected=strtoupper((string)($_SESSION['visual_captcha_code']??''));unset($_SESSION['visual_captcha_code'],$_SESSION['visual_captcha_created']);$answer=strtoupper(preg_replace('/\s+/','',trim((string)($post['captcha_answer']??''))));if($expected===''||!hash_equals($expected,$answer))throw new RuntimeException('The image security code was incorrect. Try the new image.');return;}
        $token=trim((string)($post['g-recaptcha-response']??''));$secret=self::setting('GOOGLE_RECAPTCHA_SECRET_KEY');if($token===''||$secret==='')throw new RuntimeException('Google reCAPTCHA is not fully configured or was not completed.');
        $payload=http_build_query(['secret'=>$secret,'response'=>$token,'remoteip'=>$_SERVER['REMOTE_ADDR']??'']);$response=false;if(function_exists('curl_init')){$curl=curl_init('https://www.google.com/recaptcha/api/siteverify');curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10]);$response=curl_exec($curl);curl_close($curl);}else{$response=@file_get_contents('https://www.google.com/recaptcha/api/siteverify',false,stream_context_create(['http'=>['method'=>'POST','header'=>'Content-Type: application/x-www-form-urlencoded','content'=>$payload,'timeout'=>10]]));}$result=is_string($response)?json_decode($response,true):null;if(empty($result['success']))throw new RuntimeException('Google reCAPTCHA verification failed. Please try again.');
    }
    public static function currentUser(): ?array
    {
        self::boot();$id=(int)($_SESSION['user_id']??0);if($id<=0)return null;
        $q=db()->prepare('SELECT user_id,email,display_name,is_admin,status,created_at_utc,last_login_at_utc FROM users WHERE user_id=?');$q->execute([$id]);$u=$q->fetch();
        if(!$u||$u['status']!=='ACTIVE'){self::logout();return null;}return$u;
    }
    public static function register(string $email,string $name,string $password,?string $inviteToken=null,?string $gameInviteToken=null): array
    {
        $email=strtolower(trim($email));$name=trim($name);
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid email address.');
        if(mb_strlen($name)<2||mb_strlen($name)>120)throw new RuntimeException('Display name must be between 2 and 120 characters.');
        if(strlen($password)<10)throw new RuntimeException('Password must contain at least 10 characters.');
        $pdo=db();$invite=null;$gameInvite=null;
        if($inviteToken){
            $q=$pdo->prepare('SELECT * FROM user_invitations WHERE token_hash=? AND accepted_at_utc IS NULL AND expires_at_utc>UTC_TIMESTAMP()');$q->execute([hash('sha256',$inviteToken)]);$invite=$q->fetch();
            if(!$invite||strtolower((string)$invite['email'])!==$email)throw new RuntimeException('The invitation is invalid, expired, or belongs to another email.');
        }
        if($gameInviteToken){$q=$pdo->prepare('SELECT gi.game_invitation_id,gi.game_id,gi.email,g.status,p.max_players,(SELECT COUNT(*) FROM game_players gp WHERE gp.game_id=g.game_id) player_count FROM game_invitations gi JOIN games g ON g.game_id=gi.game_id JOIN simulations s ON s.simulation_id=g.simulation_id JOIN plots p ON p.plot_id=s.plot_id WHERE gi.token_hash=? AND gi.accepted_at_utc IS NULL AND gi.expires_at_utc>UTC_TIMESTAMP() AND g.deleted_at_utc IS NULL');$q->execute([hash('sha256',$gameInviteToken)]);$gameInvite=$q->fetch();if(!$gameInvite||strtolower((string)$gameInvite['email'])!==$email||$gameInvite['status']!=='LOBBY')throw new RuntimeException('The game invitation is invalid, expired, or belongs to another email.');if((int)$gameInvite['player_count']>=(int)$gameInvite['max_players'])throw new RuntimeException('That game lobby is already full.');}
        $pdo->beginTransaction();try{$pdo->prepare('INSERT INTO users(email,password_hash,display_name,email_verified_at_utc) VALUES(?,?,?,?)')->execute([$email,password_hash($password,PASSWORD_DEFAULT),$name,($invite||$gameInvite)?gmdate('Y-m-d H:i:s'):null]);$id=(int)$pdo->lastInsertId();if($invite)$pdo->prepare('UPDATE user_invitations SET accepted_at_utc=UTC_TIMESTAMP() WHERE invitation_id=?')->execute([$invite['invitation_id']]);$gameId=0;if($gameInvite){$gameId=(int)$gameInvite['game_id'];$pdo->prepare('INSERT INTO game_players(game_id,user_id) VALUES(?,?)')->execute([$gameId,$id]);$pdo->prepare('UPDATE game_invitations SET accepted_by_user_id=?,accepted_at_utc=UTC_TIMESTAMP() WHERE game_invitation_id=?')->execute([$id,$gameInvite['game_invitation_id']]);}$pdo->commit();}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
        self::loginUserId($id);$u=self::currentUser()??[];$u['_game_id']=$gameId;return$u;
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
    public static function hasRole(int $userId,string $roleCode): bool
    {
        $q=db()->prepare('SELECT 1 FROM user_platform_roles ur JOIN platform_roles r ON r.platform_role_id=ur.platform_role_id WHERE ur.user_id=? AND r.role_code=? AND r.scope_type="PLATFORM"');
        $q->execute([$userId,strtoupper(trim($roleCode))]);return(bool)$q->fetchColumn();
    }
    public static function requireWriter(): array
    {
        $u=self::requireLogin();if((int)$u['is_admin']!==1&&!self::hasRole((int)$u['user_id'],'WRITER')){http_response_code(403);exit('Story Writer access is required.');}return$u;
    }
    public static function setRole(int $userId,string $roleCode,bool $enabled,int $assignedBy): void
    {
        $q=db()->prepare('SELECT platform_role_id FROM platform_roles WHERE role_code=? AND scope_type="PLATFORM"');$q->execute([strtoupper(trim($roleCode))]);$roleId=(int)$q->fetchColumn();if($roleId<1)throw new RuntimeException('Unknown platform role.');
        if($enabled)db()->prepare('INSERT IGNORE INTO user_platform_roles(user_id,platform_role_id,assigned_by_user_id) VALUES(?,?,?)')->execute([$userId,$roleId,$assignedBy]);
        else db()->prepare('DELETE FROM user_platform_roles WHERE user_id=? AND platform_role_id=?')->execute([$userId,$roleId]);
    }
    public static function requestWriterAccess(int $userId,string $reason=''): void
    {
        if(self::hasRole($userId,'WRITER'))throw new RuntimeException('You already have Story Writer access.');$q=db()->prepare('SELECT 1 FROM writer_access_requests WHERE user_id=? AND status="PENDING"');$q->execute([$userId]);if($q->fetchColumn())throw new RuntimeException('Your Story Writer request is already awaiting review.');db()->prepare('INSERT INTO writer_access_requests(user_id,request_text) VALUES(?,?)')->execute([$userId,trim($reason)?:null]);
    }
    public static function reviewWriterRequest(int $requestId,bool $approve,int $adminId,string $notes=''): void
    {
        $pdo=db();$q=$pdo->prepare('SELECT user_id,status FROM writer_access_requests WHERE writer_request_id=?');$q->execute([$requestId]);$r=$q->fetch();if(!$r||$r['status']!=='PENDING')throw new RuntimeException('That Story Writer request is no longer pending.');$pdo->beginTransaction();try{$pdo->prepare('UPDATE writer_access_requests SET status=?,reviewed_by_user_id=?,reviewer_notes=?,reviewed_at_utc=UTC_TIMESTAMP() WHERE writer_request_id=?')->execute([$approve?'APPROVED':'DECLINED',$adminId,trim($notes)?:null,$requestId]);if($approve)self::setRole((int)$r['user_id'],'WRITER',true,$adminId);$pdo->commit();}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }
    public static function createGameInvitation(string $email,int $gameId,int $senderUserId): array
    {
        $email=strtolower(trim($email));if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid email address.');$q=db()->prepare('SELECT g.join_code,g.status,p.title,p.max_players,u.display_name host_name,(SELECT COUNT(*) FROM game_players gp WHERE gp.game_id=g.game_id) player_count FROM games g JOIN simulations s ON s.simulation_id=g.simulation_id JOIN plots p ON p.plot_id=s.plot_id JOIN users u ON u.user_id=g.host_user_id WHERE g.game_id=? AND g.host_user_id=? AND g.deleted_at_utc IS NULL');$q->execute([$gameId,$senderUserId]);$game=$q->fetch();if(!$game||$game['status']!=='LOBBY')throw new RuntimeException('Only the host can invite players while the lobby is open.');if((int)$game['player_count']>=(int)$game['max_players'])throw new RuntimeException('The lobby is already full.');$token=bin2hex(random_bytes(24));db()->prepare('INSERT INTO game_invitations(game_id,email,token_hash,invited_by_user_id,expires_at_utc) VALUES(?,?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 7 DAY))')->execute([$gameId,$email,hash('sha256',$token),$senderUserId]);$url=self::baseUrl().'/?view=register&game_invite='.rawurlencode($token);$body=$game['host_name'].' invited you to play “'.$game['title'].'” on Toinky.'."\n\nJoin the lobby: ".$url."\nGame code: ".$game['join_code']."\n\nThis link expires in 7 days.";$sent=self::send($email,'You are invited to a Toinky story',$body,'GAME_INVITATION',$gameId,$senderUserId);return['url'=>$url,'sent'=>$sent];
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
    private static function send(string $to,string $subject,string $body,string $type='ACCOUNT',?int $gameId=null,?int $senderId=null): bool
    {
        $from=trim((string)getenv('GAME2_MAIL_FROM'));$fromName='Toinky';if($from===''){try{$q=db()->query('SELECT setting_key,setting_value FROM platform_settings WHERE setting_key IN("MAIL_FROM_ADDRESS","MAIL_FROM_NAME")');foreach($q->fetchAll()as$r){if($r['setting_key']==='MAIL_FROM_ADDRESS')$from=trim((string)$r['setting_value']);if($r['setting_key']==='MAIL_FROM_NAME')$fromName=trim((string)$r['setting_value']);}}catch(Throwable$ignored){}}if($from==='')$from='accounts@toinky.com';
        $error=null;$sent=false;
        try{
            if(trim((string)getenv('GAME2_SMTP_HOST'))!==''&&trim((string)getenv('GAME2_SMTP_USERNAME'))!==''&&trim((string)getenv('GAME2_SMTP_PASSWORD'))!=='')$sent=self::sendViaSmtp($to,$subject,$body,$from,$fromName);
            else $sent=@mail($to,$subject,$body,self::mailHeaders($from,$fromName));
        }catch(Throwable$e){$error=$e->getMessage();}
        if(!$sent&&$error===null)$error='Mail sender returned false';
        try{db()->prepare('INSERT INTO email_delivery_log(recipient_email,message_type,related_game_id,sent_by_user_id,delivery_status,error_text) VALUES(?,?,?,?,?,?)')->execute([$to,$type,$gameId,$senderId,$sent?'SENT':'FAILED',$sent?null:$error]);}catch(Throwable$ignored){}return$sent;
    }
    private static function mailHeaders(string $from,string $fromName): string
    {
        return "From: ".self::formatAddress($from,$fromName)."\r\nReply-To: ".$from."\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8";
    }
    private static function formatAddress(string $email,string $name=''): string
    {
        $name=trim($name);$email=trim($email);if($name==='')return$email;return '"'.str_replace('"','\"',$name).'" <'.$email.'>';
    }
    private static function sendViaSmtp(string $to,string $subject,string $body,string $from,string $fromName): bool
    {
        $host=trim((string)getenv('GAME2_SMTP_HOST'));$port=(int)(getenv('GAME2_SMTP_PORT')?:465);$user=trim((string)getenv('GAME2_SMTP_USERNAME'));$pass=(string)getenv('GAME2_SMTP_PASSWORD');$timeout=15;
        $remote=($port===465?'ssl://':'').$host.':'.$port;$socket=@stream_socket_client($remote,$errno,$errstr,$timeout,STREAM_CLIENT_CONNECT);if(!$socket)throw new RuntimeException('SMTP connection failed: '.$errstr);
        stream_set_timeout($socket,$timeout);
        $read=static function()use($socket):string{$response='';while(($line=fgets($socket,515))!==false){$response.=$line;if(strlen($line)>=4&&$line[3]===' ')break;}return$response;};
        $expect=static function(array$codes,string$step)use($read):string{$response=$read();$code=substr($response,0,3);if(!in_array($code,$codes,true))throw new RuntimeException('SMTP '.$step.' failed: '.trim($response));return$response;};
        $send=static function(string$command)use($socket):void{fwrite($socket,$command."\r\n");};
        $expect(['220'],'greeting');$helo=(string)($_SERVER['SERVER_NAME']??'toinky.com');$send('EHLO '.$helo);$expect(['250'],'EHLO');
        if($port===587){$send('STARTTLS');$expect(['220'],'STARTTLS');if(!stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('SMTP TLS negotiation failed.');$send('EHLO '.$helo);$expect(['250'],'EHLO after STARTTLS');}
        $send('AUTH LOGIN');$expect(['334'],'AUTH LOGIN');$send(base64_encode($user));$expect(['334'],'SMTP username');$send(base64_encode($pass));$expect(['235'],'SMTP password');
        $send('MAIL FROM:<'.$from.'>');$expect(['250'],'MAIL FROM');$send('RCPT TO:<'.$to.'>');$expect(['250','251'],'RCPT TO');$send('DATA');$expect(['354'],'DATA');
        $headers=['From: '.self::formatAddress($from,$fromName),'Reply-To: '.$from,'To: '.$to,'Subject: '.self::encodeHeader($subject),'MIME-Version: 1.0','Content-Type: text/plain; charset=UTF-8','Content-Transfer-Encoding: 8bit'];
        $message=implode("\r\n",$headers)."\r\n\r\n".self::smtpBody($body)."\r\n.";$send($message);$expect(['250'],'message body');$send('QUIT');fclose($socket);return true;
    }
    private static function encodeHeader(string $value): string
    {
        return preg_match('/[^\x20-\x7E]/',$value)?'=?UTF-8?B?'.base64_encode($value).'?=':$value;
    }
    private static function smtpBody(string $body): string
    {
        $body=str_replace(["\r\n","\r"],"\n",$body);$lines=explode("\n",$body);foreach($lines as&$line){if(str_starts_with($line,'.'))$line='.'.$line;}unset($line);return implode("\r\n",$lines);
    }
}
