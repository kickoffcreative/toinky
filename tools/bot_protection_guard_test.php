<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
putenv('GAME2_BASE_URL=http://127.0.0.1');
$root=dirname(__DIR__);require_once $root.'/app/bootstrap.php';require_once $root.'/app/AuthService.php';require_once $root.'/app/MigrationService.php';
$pdo=db();if(!str_ends_with((string)$pdo->query('SELECT DATABASE()')->fetchColumn(),'_test'))throw new RuntimeException('Use an isolated _test database.');MigrationService::run();
$throttleBefore=$pdo->query('SELECT throttle_key FROM auth_throttle')->fetchAll(PDO::FETCH_COLUMN);
$checks=0;$failed=false;$server=null;$stamp=bin2hex(random_bytes(8));$dir=sys_get_temp_dir().'/toinky-bot-'.$stamp;mkdir($dir,0700);ini_set('session.save_path',$dir);
$check=static function(bool $ok,string $label)use(&$checks):void{if(!$ok)throw new RuntimeException($label);$checks++;};
$refused=static function(string $action,array $post)use($check):void{$denied=false;try{BotProtectionService::verify($action,$post);}catch(RuntimeException $e){$denied=true;}$check($denied,'Invalid bot-protection input refused');};
try{
    AuthService::boot();$tokens=[];foreach(['login','register','forgot'] as $action){$tokens[$action]=BotProtectionService::token($action);$check($tokens[$action]===BotProtectionService::token($action),'Token remains stable for same form and session');$refused($action,[]);$refused($action,['auth_form_token'=>$tokens[$action]]);}
    $refused('register',['auth_form_token'=>$tokens['login']]);
    $refused('forgot',['auth_form_token'=>substr($tokens['forgot'],0,-1).(str_ends_with($tokens['forgot'],'0')?'1':'0')]);
    $refused('login',['auth_form_token'=>['not-a-string']]);$refused('login',['auth_form_token'=>$tokens['login'],'company_website'=>['invalid']]);
    $refused('login',['auth_form_token'=>$tokens['login'],'company_website'=>'https://example.invalid']);
    // An authenticated test session can construct a valid signature for clock-boundary fixtures.
    $dated=static function(int $age):string{$body=((int)floor(microtime(true)*1000)-$age).'.'.str_repeat('a',32);return $body.'.'.hash_hmac('sha256',"login\0".session_id()."\0".$body,(string)$_SESSION['auth_form_secret']);};
    $refused('login',['auth_form_token'=>$dated(1800001)]);$refused('login',['auth_form_token'=>$dated(-10000)]);
    usleep(2100000);foreach($tokens as $action=>$token){BotProtectionService::verify($action,['auth_form_token'=>$token]);$check(true,'Valid aged signed form accepted without a puzzle');}
    $fields=BotProtectionService::fields('login');$check(str_contains($fields,'hidden aria-hidden="true"')&&str_contains($fields,'tabindex="-1"'),'Honeypot is excluded from keyboard and accessibility tree');
    $secret=$_SESSION['auth_form_secret'];$_SESSION['auth_form_secret']=bin2hex(random_bytes(32));$refused('login',['auth_form_token'=>$tokens['login']]);$_SESSION['auth_form_secret']=$secret;
    session_write_close();
    putenv('GAME2_SMTP_HOST=');putenv('GAME2_SMTP_USERNAME=');putenv('GAME2_SMTP_PASSWORD=');
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr);$address=stream_socket_get_name($socket,false);fclose($socket);
    $server=proc_open([PHP_BINARY,'-d','sendmail_path=/bin/false','-d','session.save_path='.$dir,'-S',$address,'-t',$root.'/public'],[0=>['pipe','r'],1=>['file',$dir.'/server.log','a'],2=>['file',$dir.'/server.log','a']],$pipes,$root);if(!is_resource($server))throw new RuntimeException('Could not start test HTTP server');fclose($pipes[0]);
    $ready=false;for($i=0;$i<50;$i++){$probe=@stream_socket_client('tcp://'.$address,$errno,$errstr,0.1);if($probe){fclose($probe);$ready=true;break;}usleep(100000);}$check($ready,'Local HTTP fixture starts');
    $testClientIp='127.'.hexdec(substr($stamp,0,2)).'.'.hexdec(substr($stamp,2,2)).'.'.hexdec(substr($stamp,4,2));
    $http=static function(string $path,string $cookie='',array $post=[])use($address,$testClientIp):array{
        $options=['ignore_errors'=>true,'follow_location'=>0,'timeout'=>15,'header'=>'Cookie: '.AuthService::SESSION_NAME.'='.$cookie."\r\n"];
        if($post){$options['method']='POST';$options['header'].="Content-Type: application/x-www-form-urlencoded\r\n";$options['content']=http_build_query($post);}
        $body=file_get_contents('http://'.$address.$path,false,stream_context_create(['http'=>$options,'socket'=>['bindto'=>$testClientIp.':0']]));return [$body===false?'':$body,$http_response_header??[]];
    };
    $value=static function(string $html,string $name):string{if(!preg_match('/name="'.preg_quote($name,'/').'" value="([^"]+)"/',$html,$match))throw new RuntimeException('Expected hidden form field missing');return html_entity_decode($match[1],ENT_QUOTES,'UTF-8');};
    [$page,$headers]=$http('/?view=login');$cookie='';foreach($headers as $header)if(preg_match('/^Set-Cookie: __Host-toinky=([^;]+)/i',$header,$m))$cookie=$m[1];$check($cookie!=='','Browser receives session cookie');$pages=['login'=>$page];
    foreach(['register','forgot'] as $action)[$pages[$action]]=$http('/?view='.$action,$cookie);
    foreach($pages as $action=>$html){$check(str_contains($html,'name="auth_form_token"')&&str_contains($html,'name="company_website"'),'Every public auth form contains protection fields');$check(!preg_match('/captcha_answer|g-recaptcha|recaptcha\/api\.js|captcha-image\.php|What is [0-9]/i',$html),'No cognitive puzzle or third-party challenge rendered');}
    $data=['action'=>'register','csrf_token'=>$value($pages['register'],'csrf_token'),'email'=>'bot-'.$stamp.'@example.invalid','display_name'=>'Bot Fixture','password'=>'Never-Creates-An-Account'];
    [$body]=$http('/?view=register',$cookie,$data);$check(str_contains($body,'This form expired.'),'Actual registration without signed form token is refused');
    $data['auth_form_token']=$value($pages['login'],'auth_form_token');[$body]=$http('/?view=register',$cookie,$data);$check(str_contains($body,'This form expired.'),'Login token cannot authorize a registration post');
    $data['auth_form_token']=$value($pages['register'],'auth_form_token');$data['company_website']='filled';[$body]=$http('/?view=register',$cookie,$data);$check(str_contains($body,'This form expired.'),'Actual registration honeypot is refused');
    $q=$pdo->prepare('SELECT COUNT(*) FROM users WHERE email=?');$q->execute([$data['email']]);$check((int)$q->fetchColumn()===0,'Rejected scripted registration creates no account');
    usleep(2100000);$forgot=['action'=>'forgot','csrf_token'=>$value($pages['forgot'],'csrf_token'),'auth_form_token'=>$value($pages['forgot'],'auth_form_token'),'email'=>'unknown-bot-'.$stamp.'@example.invalid'];
    [$body]=$http('/?view=forgot',$cookie,$forgot);$check(str_contains($body,'If that account exists, a password-reset email has been sent.'),'Valid form reaches normal generic forgot-password flow');
    [$body,$headers]=$http('/captcha-image.php',$cookie);$check(str_contains($headers[0]??'','404')&&!str_contains($body,'<svg'),'Retired image puzzle endpoint returns 404');
    $auth=file_get_contents($root.'/app/AuthService.php');$check(!str_contains($auth,'siteverify')&&!str_contains($auth,"'remoteip'"),'Authentication sends no reCAPTCHA verification or remote IP to Google');
    $admin=file_get_contents($root.'/public/admin.php');$check(!str_contains($admin,'name="captcha_mode"')&&!str_contains($admin,'name="recaptcha_secret_key"'),'Admin cannot re-enable retired puzzle modes');
    echo 'PASS: A-03 '.$checks." signed-form, timing, honeypot and real HTTP checks; no puzzle required.\n";
}catch(Throwable $e){fwrite(STDERR,'FAIL: A-03 '.$e->getMessage()."\n");$failed=true;}
finally{
    if(is_resource($server)){proc_terminate($server);proc_close($server);}
    if(session_status()===PHP_SESSION_ACTIVE){$_SESSION=[];session_destroy();}
    foreach(array_diff($pdo->query('SELECT throttle_key FROM auth_throttle')->fetchAll(PDO::FETCH_COLUMN),$throttleBefore) as $key)$pdo->prepare('DELETE FROM auth_throttle WHERE throttle_key=?')->execute([$key]);
    foreach(glob($dir.'/*')?:[] as $file)if(is_file($file))unlink($file);rmdir($dir);
}
if($failed)exit(1);
