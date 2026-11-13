<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$source=dirname(__DIR__);$databaseConfig=(string)getenv('GAME2_DATABASE_CONFIG');
if($databaseConfig===''||!is_file($databaseConfig))throw new RuntimeException('Explicit isolated test database configuration required.');
$cfg=require $databaseConfig;$probe=new PDO($cfg['dsn'],$cfg['username'],$cfg['password']);
if(!str_ends_with((string)$probe->query('SELECT DATABASE()')->fetchColumn(),'_test'))throw new RuntimeException('Use a database ending in _test.');
$fixture=sys_get_temp_dir().'/toinky-public-error-'.bin2hex(random_bytes(8));
foreach(['','/app','/config','/public','/sessions'] as $part)mkdir($fixture.$part,0700);
foreach(['app','public'] as $part)foreach(glob($source.'/'.$part.'/*.php')?:[] as $file)copy($file,$fixture.'/'.$part.'/'.basename($file));
foreach(['privacy.php','ai-policy.php'] as $file)copy($source.'/config/'.$file,$fixture.'/config/'.$file);
symlink($source.'/database',$fixture.'/database');file_put_contents($fixture.'/test-marker','local');
ini_set('session.save_path',$fixture.'/sessions');ini_set('error_log',$fixture.'/diagnostics.log');
putenv('GAME2_PRIVACY_APPROVED=1');putenv('TOINKY_ACCOUNT_LINK_TEST_DIR='.$fixture);putenv('GAME2_SMTP_HOST=');putenv('GAME2_SMTP_USERNAME=');putenv('GAME2_SMTP_PASSWORD=');putenv('GAME2_BASE_URL=http://localhost');putenv('GAME2_AI_PROVIDER=deterministic');
require $fixture.'/app/bootstrap.php';require $fixture.'/app/AuthService.php';require $fixture.'/app/MigrationService.php';
$pdo=db();MigrationService::run();AuthService::boot();
$users=[];$server=null;$checks=[];$failed=false;$stamp=bin2hex(random_bytes(6));$canary='SQL_CANARY_password_token_email_body_'.$stamp;
$throttlesBefore=$pdo->query('SELECT throttle_key FROM auth_throttle')->fetchAll(PDO::FETCH_COLUMN);
$check=static function(bool $ok,string $name)use(&$checks):void{if(!$ok)throw new RuntimeException($name);$checks[]=$name;};
$scalar=static function(string $sql,array $params=[])use($pdo):mixed{$q=$pdo->prepare($sql);$q->execute($params);return $q->fetchColumn();};
try{
    $check(ini_get('display_errors')==='0'&&ini_get('log_errors')==='1','Bootstrap suppresses browser diagnostics and enables private logging');
    $check(public_error(new UserFacingException('Choose a character.'))==='Choose a character.','Reviewed validation remains readable');
    $unknown=new RuntimeException($canary);$message=public_error($unknown);
    $check(preg_match('/^Something went wrong\. Reference: [a-f0-9]{16}$/D',$message)===1&&public_error($unknown)===$message,'Unexpected exceptions return stable reference-only message');
    $check(!str_contains((string)file_get_contents($fixture.'/diagnostics.log'),$canary),'Private exception diagnostics omit raw secret-bearing messages');
    $check(exception_category(new RuntimeException('AI HTTP 503 private body '.$canary))==='http 503'&&exception_category(new JsonException($canary))==='json','Internal retry classification returns categories without provider text');
    $check(!preg_match('/->getMessage\s*\(/',(string)file_get_contents($source.'/install.php')),'Installer errors also use the safe boundary');
    foreach(glob($source.'/public/*.php')?:[] as $file)$check(!preg_match('/->getMessage\s*\(/',(string)file_get_contents($file)),'No raw exception display in '.basename($file));
    foreach(glob($source.'/app/*.php')?:[] as $file)if(basename($file)!=='PublicError.php')$check(!preg_match('/->getMessage\s*\(/',(string)file_get_contents($file)),'Exception message access confined to helper: '.basename($file));
    $pdo->prepare('INSERT INTO users(email,password_hash,display_name,email_verified_at_utc) VALUES(?,?,?,UTC_TIMESTAMP())')->execute(['error-'.$stamp.'@example.invalid',password_hash('Local-Error-Guard-Only',PASSWORD_DEFAULT),'Error Fixture']);$web=(int)$pdo->lastInsertId();$users[]=$web;
    $c=PrivacyService::config();PrivacyService::recordConsent($web,['accept_policies'=>'1','terms_version'=>$c['terms_version'],'privacy_version'=>$c['privacy_version']]);
    $_SESSION=['user_id'=>$web,'session_version'=>1,'csrf_token'=>bin2hex(random_bytes(24))];$cookie=session_id();$csrf=$_SESSION['csrf_token'];session_write_close();
    $pdo->exec('CREATE TRIGGER public_error_guard_users BEFORE UPDATE ON users FOR EACH ROW BEGIN IF NEW.user_id='.$web.' THEN SIGNAL SQLSTATE "45000" SET MESSAGE_TEXT="'.$canary.'"; END IF; END');
    $pdo->exec('CREATE TRIGGER public_error_guard_preferences BEFORE INSERT ON user_inky_preferences FOR EACH ROW BEGIN IF NEW.user_id='.$web.' THEN SIGNAL SQLSTATE "45000" SET MESSAGE_TEXT="'.$canary.'"; END IF; END');
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr);$address=stream_socket_get_name($socket,false);fclose($socket);
    $transport=escapeshellarg(PHP_BINARY).' '.escapeshellarg($source.'/tools/fixtures/account_link_test_mail.php');
    $server=proc_open([PHP_BINARY,'-d','sendmail_path='.$transport,'-d','session.save_path='.$fixture.'/sessions','-d','error_log='.$fixture.'/server-errors.log','-S',$address,'-t',$fixture.'/public'],[0=>['pipe','r'],1=>['file',$fixture.'/server.log','a'],2=>['file',$fixture.'/server.log','a']],$pipes,$fixture);if(!is_resource($server))throw new RuntimeException('Could not start local HTTP fixture');fclose($pipes[0]);
    $ready=false;for($i=0;$i<40;$i++){$connection=@stream_socket_client('tcp://'.$address,$errno,$errstr,0.1);if($connection){fclose($connection);$ready=true;break;}usleep(100000);}$check($ready,'Local error HTTP fixture starts');
    $testIp='127.'.hexdec(substr($stamp,0,2)).'.'.hexdec(substr($stamp,2,2)).'.'.hexdec(substr($stamp,4,2));
    $http=static function(string $path,string $session='',array $post=[])use($address,$testIp):array{$options=['ignore_errors'=>true,'follow_location'=>0,'timeout'=>20,'header'=>'Cookie: '.AuthService::SESSION_NAME.'='.$session."\r\n"];if($post){$options['method']='POST';$options['header'].="Content-Type: application/x-www-form-urlencoded\r\n";$options['content']=http_build_query($post);}$body=file_get_contents('http://'.$address.$path,false,stream_context_create(['http'=>$options,'socket'=>['bindto'=>$testIp.':0']]));return [$body===false?'':$body,$http_response_header??[]];};
    [$body]=$http('/profile.php',$cookie,['action'=>'update_display_name','display_name'=>'Updated Fixture','csrf_token'=>$csrf]);
    $check(str_contains($body,'Something went wrong. Reference:')&&!str_contains($body,$canary)&&!str_contains($body,'SQLSTATE'),'Real profile database failure returns safe HTML');
    [$body,$headers]=$http('/inky-preferences.php',$cookie,['anchor_position'=>'LOWER_LEFT','csrf_token'=>$csrf]);$data=json_decode($body,true,512,JSON_THROW_ON_ERROR);
    $check(($data['ok']??true)===false&&preg_match('/^Something went wrong\. Reference: [a-f0-9]{16}$/D',$data['message']??'')===1&&!str_contains($body,$canary),'Real JSON database failure returns safe reference');
    $logs=(string)@file_get_contents($fixture.'/server-errors.log').(string)file_get_contents($fixture.'/server.log');
    $check(str_contains($logs,'PDOException')&&str_contains($logs,'driver_code')&&!str_contains($logs,$canary),'Private HTTP diagnostics contain location/type without SQL values or raw text');
    $pdo->exec('DROP TRIGGER public_error_guard_users');$pdo->exec('DROP TRIGGER public_error_guard_preferences');
    [$body]=$http('/inky-preferences.php',$cookie,['anchor_position'=>'not-a-position','csrf_token'=>$csrf]);$data=json_decode($body,true,512,JSON_THROW_ON_ERROR);$check(($data['message']??'')==='Choose a valid Inky position.','Deliberate JSON validation still explains how to recover');
    // Both registration cases use the same signed age/consent form and outcome.
    [$body,$headers]=$http('/?view=register');$guest='';foreach($headers as $header)if(preg_match('/^Set-Cookie: __Host-toinky=([^;]+)/i',$header,$m))$guest=$m[1];
    $field=static function(string $html,string $name):string{if(!preg_match('/name="'.preg_quote($name,'/').'" value="([^"]*)"/',$html,$m))throw new RuntimeException('Required fixture field missing: '.$name);return html_entity_decode($m[1],ENT_QUOTES,'UTF-8');};
    $guestCsrf=$field($body,'csrf_token');$email='error-register-'.$stamp.'@example.invalid';$outcomes=[];$mailCounts=[];$originalHash='';
    for($attempt=0;$attempt<5;$attempt++){
        $http('/?view=register',$guest,['action'=>'privacy_age_check','csrf_token'=>$guestCsrf,'birth_date'=>'1990-01-01']);[$form]=$http('/?view=register',$guest);usleep(2100000);
        [$body,$headers]=$http('/?view=register',$guest,['action'=>'register','csrf_token'=>$guestCsrf,'auth_form_token'=>$field($form,'auth_form_token'),'company_website'=>'','email'=>$email,'display_name'=>'Registration Fixture','password'=>'Local-Registration-Guard-Only','accept_policies'=>'1','terms_version'=>$c['terms_version'],'privacy_version'=>$c['privacy_version']]);
        $location='';foreach($headers as $header)if(str_starts_with($header,'Location:'))$location=$header;
        $outcomes[]=[$headers[0]??'',$location,$body];
        $id=(int)$scalar('SELECT user_id FROM users WHERE email=?',[$email]);if($id&&!in_array($id,$users,true))$users[]=$id;
        $mailCounts[]=count(file($fixture.'/mail.jsonl',FILE_IGNORE_NEW_LINES)?:[]);
        if($attempt===0)$originalHash=(string)$scalar('SELECT password_hash FROM users WHERE user_id=?',[$id]);
        [$profile,$profileHeaders]=$http('/profile.php',$guest);$check(str_contains(implode("\n",$profileHeaders),'Location: ./?view=login'),'Registration does not create an authenticated session: '.$attempt);
    }
    $check($outcomes[0]===$outcomes[1]&&str_contains($outcomes[0][1],'registration_submitted=1'),'Existing and new emails have identical HTTP registration completion');
    $check(count(array_unique(array_map('serialize',$outcomes)))===1&&$mailCounts[3]===$mailCounts[4],'Recipient notice throttle preserves generic completion while limiting mail');
    $check((int)$scalar('SELECT COUNT(*) FROM users WHERE email=?',[$email])===1,'Duplicate registration does not replace the account');
    $check(hash_equals($originalHash,(string)$scalar('SELECT password_hash FROM users WHERE email=?',[$email])),'Duplicate registration leaves the existing password unchanged');
    $check(str_contains((string)file_get_contents($fixture.'/mail.jsonl'),'Someone submitted a registration'),'Existing mailbox owner receives private registration notice');
    [$body]=$http('/?view=login&registration_submitted=1',$guest);$check(str_contains($body,'If this registration can be completed')&&!str_contains($body,$email),'Completion notice does not reveal address existence');
    file_put_contents($fixture.'/public/uncaught-fixture.php','<?php require dirname(__DIR__)."/app/bootstrap.php"; db()->query("SELECT * FROM public_error_canary_missing_table");');
    [$body,$headers]=$http('/uncaught-fixture.php');$check(str_contains($headers[0]??'','500')&&preg_match('/^Something went wrong\. Reference: [a-f0-9]{16}$/D',$body)===1&&!str_contains($body,'public_error_canary_missing_table'),'Uncaught database failure also uses safe bootstrap response');
}catch(Throwable $e){$failed=true;fwrite(STDERR,get_class($e).': '.$e->getMessage()."\n".$e->getTraceAsString()."\n");}
finally{
    if(is_resource($server)){proc_terminate($server);proc_close($server);}
    $pdo->exec('DROP TRIGGER IF EXISTS public_error_guard_users');$pdo->exec('DROP TRIGGER IF EXISTS public_error_guard_preferences');
    if($pdo->inTransaction())$pdo->rollBack();
    foreach($users as $id){$pdo->prepare('DELETE FROM email_delivery_log WHERE sent_by_user_id=? OR recipient_email IN(SELECT email FROM users WHERE user_id=?)')->execute([$id,$id]);$pdo->prepare('DELETE FROM users WHERE user_id=?')->execute([$id]);}
    foreach(array_diff($pdo->query('SELECT throttle_key FROM auth_throttle')->fetchAll(PDO::FETCH_COLUMN),$throttlesBefore) as $key)$pdo->prepare('DELETE FROM auth_throttle WHERE throttle_key=?')->execute([$key]);
    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
    $walk=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($walk as $entry){if($entry->isLink()||$entry->isFile())unlink($entry->getPathname());else rmdir($entry->getPathname());}rmdir($fixture);
}
echo count($checks).' public-error checks '.($failed?'before failure':'passed')."\n";exit($failed?1:0);
