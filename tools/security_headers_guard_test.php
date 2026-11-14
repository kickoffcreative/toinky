<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
set_error_handler(static function(int $level,string $message,string $file,int $line):bool{if(!(error_reporting()&$level))return false;throw new ErrorException($message,0,$level,$file,$line);});
putenv('GAME2_BASE_URL=http://127.0.0.1');
$root=dirname(__DIR__);require_once $root.'/app/bootstrap.php';require_once $root.'/app/AuthService.php';
$pdo=db();if(!str_ends_with((string)$pdo->query('SELECT DATABASE()')->fetchColumn(),'_test'))throw new RuntimeException('Use an isolated _test database.');
$dir=sys_get_temp_dir().'/toinky-headers-'.bin2hex(random_bytes(8));mkdir($dir,0700);mkdir($dir.'/sessions',0700);mkdir($dir.'/config',0700);
ini_set('session.save_path',$dir.'/sessions');$users=[];$server=null;$checks=0;$failed=false;
$check=static function(bool $ok,string $label)use(&$checks):void{if(!$ok)throw new RuntimeException($label);$checks++;};
$session=static function(int $id):array{if(session_status()===PHP_SESSION_ACTIVE)session_write_close();session_id('');AuthService::boot();$_SESSION=['user_id'=>$id,'session_version'=>1,'csrf_token'=>bin2hex(random_bytes(24))];SessionService::register((int)$_SESSION['user_id'],true);$result=[session_id(),$_SESSION['csrf_token']];session_write_close();return $result;};
try{
    foreach(glob($root.'/*.php') as $file)copy($file,$dir.'/'.basename($file));
    symlink($root.'/app',$dir.'/app');symlink($root.'/public',$dir.'/public');
    file_put_contents($dir.'/.installed','test');file_put_contents($dir.'/config/database.php','<?php return require '.var_export((string)getenv('GAME2_DATABASE_CONFIG'),true).';');
    foreach([0,1] as $admin){$pdo->prepare('INSERT INTO users(email,password_hash,display_name,is_admin,is_super_admin,email_verified_at_utc,last_login_at_utc) VALUES(?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())')->execute(['headers-'.bin2hex(random_bytes(8)).'@example.invalid',password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT),'Headers Fixture',$admin,$admin]);$users[]=(int)$pdo->lastInsertId();}
    [$player]=$session($users[0]);[$admin,$csrf]=$session($users[1]);
    $check(ini_get('session.use_strict_mode')==='1'&&ini_get('session.use_only_cookies')==='1'&&ini_get('session.use_trans_sid')==='0','Strict cookie-only sessions required');
    $start=static function(string $mode)use(&$server,$dir,$check):string{
        putenv('GAME2_CSP_MODE='.$mode);putenv('GAME2_AI_ENDPOINT=https://unapproved.invalid');putenv('GAME2_AI_PROVIDER=deterministic');putenv('GAME2_SMTP_HOST=');
        $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr);$address=stream_socket_get_name($socket,false);fclose($socket);
        $server=proc_open([PHP_BINARY,'-d','sendmail_path=/bin/false','-d','session.save_path='.$dir.'/sessions','-S',$address,'-t',$dir],[0=>['pipe','r'],1=>['file',$dir.'/server.log','a'],2=>['file',$dir.'/server.log','a']],$pipes,$dir);if(!is_resource($server))throw new RuntimeException('HTTP fixture failed');fclose($pipes[0]);
        $ready=false;for($i=0;$i<50;$i++){$probe=@stream_socket_client('tcp://'.$address,$errno,$errstr,0.1);if($probe){fclose($probe);$ready=true;break;}usleep(100000);}$check($ready,'HTTP fixture starts');return $address;
    };
    $http=static function(string $address,string $path,string $cookie='',?array $post=null):array{
        $options=['ignore_errors'=>true,'follow_location'=>0,'timeout'=>20,'header'=>'Cookie: '.AuthService::SESSION_NAME.'='.$cookie."\r\n"];
        if($post!==null){$options['method']='POST';$options['header'].="Content-Type: application/x-www-form-urlencoded\r\n";$options['content']=http_build_query($post);}
        $body=file_get_contents('http://'.$address.$path,false,stream_context_create(['http'=>$options]));$headers=[];
        foreach($http_response_header??[] as $line){if(str_contains($line,':')){[$key,$value]=explode(':',$line,2);$headers[strtolower($key)]=trim($value);}else $headers['status']=$line;}
        return [$body===false?'':$body,$headers];
    };
    $verify=static function(array $headers,string $label)use($check):void{
        foreach(['strict-transport-security'=>'max-age=31536000; includeSubDomains','x-content-type-options'=>'nosniff','referrer-policy'=>'strict-origin-when-cross-origin','permissions-policy'=>'camera=(), microphone=(), geolocation=()','x-frame-options'=>'DENY'] as $key=>$expected)$check(($headers[$key]??'')===$expected,$label.' header '.$key);
        $check(str_contains($headers['content-security-policy']??'',"frame-ancestors 'none'"),$label.' cannot be framed');
    };
    foreach(['report-only','enforce'] as $mode){
        $address=$start($mode);
        foreach(glob($root.'/*.php') as $file){$path='/'.basename($file);[$body,$headers]=$http($address,$path);$verify($headers,$mode.' '.$path);}
        [$body,$headers]=$http($address,'/?view=login');$verify($headers,'Login');
        $cookie=$headers['set-cookie']??'';$check(str_starts_with($cookie,'__Host-toinky=')&&str_contains($cookie,'path=/')&&stripos($cookie,'secure')!==false&&stripos($cookie,'HttpOnly')!==false&&str_contains($cookie,'SameSite=Lax')&&!preg_match('/;\s*domain=/i',$cookie),'Host-only Secure HttpOnly Lax cookie');
        [, $fixed]=$http($address,'/?view=login','attacker-selected-session-id');$check(str_starts_with($fixed['set-cookie']??'','__Host-toinky=')&&!str_contains($fixed['set-cookie']??'','attacker-selected-session-id'),'Unissued session identifiers are replaced');
        $policy=$headers[$mode==='enforce'?'content-security-policy':'content-security-policy-report-only']??'';
        $check(preg_match("/'nonce-([^']+)'/",$policy,$match)===1,'Fresh nonce in policy');$nonce=$match[1];
        $check(!str_contains($policy,"script-src 'unsafe-inline'")&&!preg_match("/script-src[^;]*'unsafe-eval'/",$policy)&&str_contains($policy,"script-src-attr 'none'"),'Script inline/eval not permitted');
        preg_match_all('/<(?:script|style)\b([^>]*)>/i',$body,$tags);foreach($tags[1] as $attributes)$check(str_contains($attributes,'nonce="'.$nonce.'"'),'Authored script/style has matching nonce');
        [, $next]=$http($address,'/?view=login');$check(!str_contains($next[$mode==='enforce'?'content-security-policy':'content-security-policy-report-only']??'',$nonce),'Nonce differs on next response');
        foreach(['/?view=dashboard','/admin.php','/writer.php','/profile.php','/page-builder.php','/lobby.php','/play.php'] as $path){[$page,$h]=$http($address,$path,$admin);$verify($h,$path);$check(($h['cache-control']??'')==='no-store','Authenticated response cannot be stored: '.$path);$check(!str_contains($h['status']??'','500'),'Authenticated page does not fail: '.$path);$p=$h[$mode==='enforce'?'content-security-policy':'content-security-policy-report-only']??'';preg_match("/'nonce-([^']+)'/",$p,$n);preg_match_all('/<(?:script|style)\b([^>]*)>/i',$page,$authored);foreach($authored[1] as $attrs)$check(isset($n[1])&&str_contains($attrs,'nonce="'.$n[1].'"'),'Authenticated script/style matches nonce: '.$path);}
        [, $h]=$http($address,'/builder-preview.php',$admin);$check(str_contains($h['status']??'','405'),'Preview requires POST');$verify($h,'Preview GET');
        [, $h]=$http($address,'/builder-preview.php',$player,['csrf_token'=>$csrf]);$check(str_contains($h['status']??'','403'),'Player cannot preview');$verify($h,'Player preview');
        [, $h]=$http($address,'/builder-preview.php',$admin,['csrf_token'=>'wrong']);$check(str_contains($h['status']??'','403'),'Preview rejects invalid CSRF');$verify($h,'Invalid preview');
        [$page,$h]=$http($address,'/builder-preview.php?view=home',$admin,['csrf_token'=>$csrf,'rows_payload'=>'[]','section_id'=>0]);
        $check(str_contains($h['status']??'','200')&&($h['x-frame-options']??'')==='SAMEORIGIN'&&str_contains($h['content-security-policy']??'',"frame-ancestors 'self'"),'Only authorized POST preview permits same-origin framing');
        $check(($h['cache-control']??'')==='no-store','Preview is not cached');
        proc_terminate($server);proc_close($server);$server=null;
    }
    $manifest=json_decode(file_get_contents($root.'/public/assets/fonts/manifest.json'),true,512,JSON_THROW_ON_ERROR);
    $check(count($manifest['sources'])===103&&count($manifest['families'])===34,'Complete local catalogue includes all fonts and licenses');
    foreach($manifest['sources'] as $path=>$asset){$file=$root.'/public/assets/fonts/'.$path;$check(is_file($file)&&hash_file('sha256',$file)===$asset['sha256'],'Vendored font/license integrity: '.$path);if(str_ends_with($path,'.woff2'))$check(substr(file_get_contents($file),0,4)==='wOF2','Font is WOFF2');}
    $pixi=json_decode(file_get_contents($root.'/public/assets/vendor/pixi-csp.source.json'),true,512,JSON_THROW_ON_ERROR);$check(hash_file('sha256',$root.'/public/assets/vendor/pixi-csp.min.js')===$pixi['sha256'],'Pixi CSP module matches recorded source');
    echo 'PASS: A-05 '.$checks." HTTP, cookie, nonce, preview and asset-integrity checks.\n";
}catch(Throwable $e){fwrite(STDERR,'FAIL: A-05 '.$e->getMessage()."\n");$failed=true;}
finally{
    if(is_resource($server)){proc_terminate($server);proc_close($server);}if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
    foreach($users as $id)$pdo->prepare('DELETE FROM users WHERE user_id=?')->execute([$id]);
    foreach(['app','public'] as $link)if(is_link($dir.'/'.$link))unlink($dir.'/'.$link);
    foreach(['sessions','config'] as $sub){foreach(glob($dir.'/'.$sub.'/*')?:[] as $file)unlink($file);rmdir($dir.'/'.$sub);}
    foreach(glob($dir.'/*')?:[] as $file)if(is_file($file))unlink($file);if(is_file($dir.'/.installed'))unlink($dir.'/.installed');rmdir($dir);
}
if($failed)exit(1);
