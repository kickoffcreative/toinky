<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);
if(($argv[1]??'')!=='--worker'){
    $dir=sys_get_temp_dir().'/toinky-a09-'.bin2hex(random_bytes(8));mkdir($dir,0700);file_put_contents($dir.'/test-marker','local');
    putenv('TOINKY_ACCOUNT_LINK_TEST_DIR='.$dir);putenv('GAME2_SMTP_HOST=');putenv('GAME2_SMTP_USERNAME=');putenv('GAME2_SMTP_PASSWORD=');putenv('GAME2_BASE_URL=http://localhost');putenv('GAME2_PRIVACY_APPROVED=1');
    $transport=escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/fixtures/account_link_test_mail.php');
    $process=proc_open([PHP_BINARY,'-d','sendmail_path='.$transport,'-d','session.save_path='.$dir,__FILE__,'--worker'],[0=>['pipe','r'],1=>STDOUT,2=>STDERR],$pipes,$root);
    if(!is_resource($process))throw new RuntimeException('Could not start isolated guard.');fclose($pipes[0]);$status=proc_close($process);
    foreach(glob($dir.'/*')?:[] as $file)if(is_file($file))unlink($file);rmdir($dir);exit($status);
}
$config=(string)getenv('GAME2_DATABASE_CONFIG');if($config===''||!is_file($config))throw new RuntimeException('Explicit test database configuration required.');
$cfg=require $config;$probe=new PDO($cfg['dsn'],$cfg['username'],$cfg['password']);if(!str_ends_with((string)$probe->query('SELECT DATABASE()')->fetchColumn(),'_test'))throw new RuntimeException('Use a database ending in _test.');unset($probe);
require $root.'/app/bootstrap.php';require $root.'/app/MigrationService.php';require $root.'/app/AccountEmailService.php';require $root.'/app/UserProfileService.php';require $root.'/app/PrivacyDataService.php';
$pdo=db();MigrationService::run();AuthService::boot();$dir=(string)getenv('TOINKY_ACCOUNT_LINK_TEST_DIR');$stamp=bin2hex(random_bytes(6));$users=[];$checks=[];$failed=false;$server=null;
$_SERVER['REMOTE_ADDR']='2001:db8::'.substr($stamp,0,4);$throttles=$pdo->query('SELECT throttle_key FROM auth_throttle')->fetchAll(PDO::FETCH_COLUMN);
$check=static function(bool $ok,string $label)use(&$checks):void{if(!$ok)throw new RuntimeException($label);$checks[]=$label;};
$reject=static function(callable $fn,string $label)use($check):void{try{$fn();}catch(UserFacingException $e){$check(true,$label);return;}throw new RuntimeException($label);};
$scalar=static function(string $sql,array $args=[])use($pdo):mixed{$q=$pdo->prepare($sql);$q->execute($args);return $q->fetchColumn();};
$make=static function(string $suffix)use($pdo,$stamp,&$users):int{
    $pdo->prepare('INSERT INTO users(email,display_name,password_hash,email_verified_at_utc) VALUES(?,?,?,UTC_TIMESTAMP())')->execute(['a09-'.$stamp.'-'.$suffix.'@example.invalid','Fixture '.$suffix,password_hash('Local-Email-Guard-Only',PASSWORD_DEFAULT)]);$id=(int)$pdo->lastInsertId();$users[]=$id;
    $c=PrivacyService::config();PrivacyService::recordConsent($id,['accept_policies'=>'1','terms_version'=>$c['terms_version'],'privacy_version'=>$c['privacy_version']]);return $id;
};
$mail=static function(callable $fn)use($dir):array{file_put_contents($dir.'/mail.jsonl','');$result=$fn();return [$result,(string)file_get_contents($dir.'/mail.jsonl')];};
$token=static function(string $mail,string $key):string{if(!preg_match('/(?:[?&]|&amp;)'.preg_quote($key,'/').'=?([a-f0-9]{48})/',$mail,$m))throw new RuntimeException('Expected captured token absent');return $m[1];};
$session=static function(int $id)use($scalar):array{if(session_status()===PHP_SESSION_ACTIVE)session_write_close();session_id(bin2hex(random_bytes(16)));AuthService::boot();$_SESSION=['csrf_token'=>bin2hex(random_bytes(24))];if($id>0){$_SESSION['user_id']=$id;$_SESSION['session_version']=(int)$scalar('SELECT session_version FROM users WHERE user_id=?',[$id]);SessionService::register($id,true);}$result=[session_id(),$_SESSION['csrf_token']];session_write_close();return $result;};
try{
    $sender=$make('sender');$peer=$make('peer');$peerEmail=(string)$scalar('SELECT email FROM users WHERE user_id=?',[$peer]);$fresh='a09-'.$stamp.'-new@example.invalid';
    $reject(static fn()=>AccountEmailService::request($sender,$fresh,''),'Email change requires current password');
    $check(!(int)$scalar('SELECT COUNT(*) FROM user_email_change_tokens WHERE user_id=?',[$sender]),'Missing password creates no pending change');
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr);$address=stream_socket_get_name($socket,false);fclose($socket);
    $transport=escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/fixtures/account_link_test_mail.php');
    $server=proc_open([PHP_BINARY,'-d','sendmail_path='.$transport,'-d','session.save_path='.$dir,'-S',$address,'-t',$root.'/public'],[0=>['pipe','r'],1=>['file',$dir.'/server.log','a'],2=>['file',$dir.'/server.log','a']],$pipes,$root);fclose($pipes[0]);
    $ready=false;for($i=0;$i<40;$i++){$c=@stream_socket_client('tcp://'.$address,$errno,$errstr,0.1);if($c){fclose($c);$ready=true;break;}usleep(100000);}$check($ready,'Local HTTP fixture starts');
    $testIp='127.'.hexdec(substr($stamp,0,2)).'.'.hexdec(substr($stamp,2,2)).'.'.hexdec(substr($stamp,4,2));
    $http=static function(string $path,string $cookie,array $post=[])use($address,$testIp):array{$o=['ignore_errors'=>true,'follow_location'=>0,'timeout'=>20,'header'=>'Cookie: '.AuthService::SESSION_NAME.'='.$cookie."\r\n"];if($post){$o['method']='POST';$o['header'].="Content-Type: application/x-www-form-urlencoded\r\n";$o['content']=http_build_query($post);}$body=file_get_contents('http://'.$address.$path,false,stream_context_create(['http'=>$o,'socket'=>['bindto'=>$testIp.':0']]));return [$body===false?'':$body,$http_response_header??[]];};
    $normalize=static fn(string $s):string=>preg_replace('/nonce="[^"]+"/','nonce="NORMALIZED"',$s);
    [$cookie,$csrf]=$session($sender);
    foreach(['request_email_change','request_friend','invite_player'] as $action){
        $out=[];
        foreach([$peerEmail,$fresh] as $email){
            [$body,$headers]=$http('/profile.php',$cookie,['action'=>$action,'csrf_token'=>$csrf,'email'=>$email,'new_email'=>$email,'current_password'=>'Local-Email-Guard-Only']);
            $out[]=[$headers[0]??'',$normalize($body)];
        }
        $check($out[0]===$out[1]&&str_contains($out[0][0],'200'),'Byte-identical normalized HTTP body/status: '.$action);
        $expected=match($action){'request_email_change'=>AccountEmailService::REQUEST_NOTICE,'request_friend'=>AccountEmailService::FRIEND_NOTICE,default=>AccountEmailService::INVITE_NOTICE};
        $check(str_contains($out[0][1],$expected),'Expected conditional notice: '.$action);
    }
    $check((int)$scalar('SELECT COUNT(*) FROM user_friendships WHERE requested_by_user_id=?',[$sender])===1,'Unknown friend creates no relationship');
    $check(UserProfileService::friends($sender)===[],'Outgoing pending target identity is hidden');
    $incoming=UserProfileService::friends($peer);$check(count($incoming)===1&&(int)$incoming[0]['friend_user_id']===$sender,'Recipient can see incoming request');
    $export='';PrivacyDataService::streamExport($sender,'Local-Email-Guard-Only',static function(string $chunk)use(&$export):void{$export.=$chunk;});$data=json_decode($export,true,512,JSON_THROW_ON_ERROR);
    $check(($data['datasets']['friends']??null)===[],'Export cannot reveal unaccepted outgoing target');
    $check(!in_array('EMAIL_CHANGE',array_column($data['datasets']['email_delivery'],'message_type'),true)&&!in_array('INVITATION_NOTICE',array_column($data['datasets']['email_delivery'],'message_type'),true),'Delivery export hides address-availability clues');
    $check(str_contains((string)file_get_contents($dir.'/mail.jsonl'),'You Were Invited To Toinky'),'Existing account invite privately notifies mailbox owner');
    UserProfileService::respondToFriend($peer,(int)$incoming[0]['friendship_id'],true);$check(count(UserProfileService::acceptedFriends($sender))===1,'Accepted friendships remain available');
    // Use separate disposable accounts for recovery cases, avoiding incidental per-user throttles.
    $id=$make('recover');$old=(string)$scalar('SELECT email FROM users WHERE user_id=?',[$id]);$new='a09-'.$stamp.'-changed@example.invalid';
    [$oldCookie]=$session($id);
    [, $message]=$mail(static fn()=>AccountEmailService::request($id,$new,'Local-Email-Guard-Only'));$verify=$token($message,'verify_email');
    $check($scalar('SELECT token_hash FROM user_email_change_tokens WHERE user_id=?',[$id])===hash('sha256',$verify),'Verification token persisted only as hash');
    [, $notice]=$mail(static fn()=>AccountEmailService::confirm($id,$verify));$undo=$token($notice,'undo_email');
    $check($scalar('SELECT email FROM users WHERE user_id=?',[$id])===$new,'Confirmation activates new email');
    $check(str_contains($notice,$old)&&!str_contains($notice,'To: '.$new),'Recovery notice targets old mailbox');
    $check((int)$scalar('SELECT COUNT(*) FROM user_sessions WHERE user_id=? AND revoked_at_utc IS NULL',[$id])===0,'Email confirmation revokes every session');
    $check((int)$scalar('SELECT TIMESTAMPDIFF(SECOND,verified_at_utc,undo_expires_at_utc) FROM user_email_change_tokens WHERE user_id=?',[$id])===604800,'Undo expires seven days after confirmation');
    $check($scalar('SELECT undo_hash FROM user_email_change_tokens WHERE user_id=?',[$id])===hash('sha256',$undo),'Undo token persisted only as hash');
    $reject(static fn()=>AccountEmailService::confirm($id,$verify),'Verification cannot be replayed');
    // A scanner GET only stores the bearer and redirects to a clean CSRF form.
    [$guestCookie,$guestCsrf]=$session($peer);
    [$body,$headers]=$http('/?view=undo_email&undo_email='.$undo,$guestCookie);$check(str_contains(implode(' ',$headers),'Location: ./?view=undo_email')&&!str_contains($body,$undo),'Undo GET strips bearer without performing recovery');
    [$body]=$http('/?view=undo_email',$guestCookie);$check(str_contains($body,'Restore Previous Email')&&!str_contains($body,$undo)&&$scalar('SELECT email FROM users WHERE user_id=?',[$id])===$new,'Clean recovery page requires explicit action');
    [$guestCookie,$guestCsrf]=$session(0);$http('/?view=undo_email&undo_email='.$undo,$guestCookie);
    [$body]=$http('/?view=undo_email',$guestCookie);$check(str_contains($body,'Restore Previous Email'),'Recovery form works without sign-in');
    [$body]=$http('/?view=undo_email',$guestCookie,['action'=>'undo_email','csrf_token'=>'bad']);$check($scalar('SELECT email FROM users WHERE user_id=?',[$id])===$new,'Undo rejects invalid CSRF');
    $pdo->prepare('INSERT INTO user_totp_credentials(user_id,encrypted_secret,enabled_at_utc,recovery_hashes_json) VALUES(?,?,UTC_TIMESTAMP(),?)')->execute([$id,'fixture encrypted placeholder','[]']);
    [$attackerCookie]=$session($id);
    [, $headers]=$http('/?view=undo_email',$guestCookie,['action'=>'undo_email','csrf_token'=>$guestCsrf]);$check(str_contains(implode(' ',$headers),'email_restored=1'),'Explicit CSRF-protected recovery completes');
    $check($scalar('SELECT email FROM users WHERE user_id=?',[$id])===$old,'Undo restores old email');
    $check(!password_verify('Local-Email-Guard-Only',(string)$scalar('SELECT password_hash FROM users WHERE user_id=?',[$id])),'Undo invalidates compromised password');
    $check(!(int)$scalar('SELECT COUNT(*) FROM user_totp_credentials WHERE user_id=?',[$id]),'Undo removes authenticator and recovery credentials');
    $check(!(int)$scalar('SELECT COUNT(*) FROM user_sessions WHERE user_id=? AND revoked_at_utc IS NULL',[$id]),'Undo revokes sessions created after confirmation');
    $reject(static fn()=>AccountEmailService::undo($undo),'Undo cannot be replayed');
    [$body,$headers]=$http('/profile.php',$attackerCookie);$check(str_contains(implode(' ',$headers),'view=login'),'Revoked session cannot open protected profile');
    [, $resetMail]=$mail(static fn()=>AccountSecurityService::requestPasswordReset($old));$reset=$token($resetMail,'token');AccountSecurityService::resetPassword($reset,'Recovered-Email-Guard-Only');
    $check(password_verify('Recovered-Email-Guard-Only',(string)$scalar('SELECT password_hash FROM users WHERE user_id=?',[$id])),'Restored mailbox can recover access with a new password');
    $case=$make('expired');$next='a09-'.$stamp.'-expired-new@example.invalid';[, $m]=$mail(static fn()=>AccountEmailService::request($case,$next,'Local-Email-Guard-Only'));[, $m]=$mail(static fn()=>AccountEmailService::confirm($case,$token($m,'verify_email')));$expired=$token($m,'undo_email');
    $pdo->prepare('UPDATE user_email_change_tokens SET undo_expires_at_utc=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE user_id=?')->execute([$case]);$reject(static fn()=>AccountEmailService::undo($expired),'Expired undo is refused');
    $case=$make('failed');[, $m]=$mail(static fn()=>AccountEmailService::request($case,'a09-'.$stamp.'-failed-new@example.invalid','Local-Email-Guard-Only'));file_put_contents($dir.'/fail-mail','1');[, $m]=$mail(static fn()=>AccountEmailService::confirm($case,$token($m,'verify_email')));unlink($dir.'/fail-mail');$reject(static fn()=>AccountEmailService::undo($token($m,'undo_email')),'Undelivered undo cannot be used');
    $case=$make('later');[, $m]=$mail(static fn()=>AccountEmailService::request($case,'a09-'.$stamp.'-later-one@example.invalid','Local-Email-Guard-Only'));[, $m]=$mail(static fn()=>AccountEmailService::confirm($case,$token($m,'verify_email')));$firstUndo=$token($m,'undo_email');
    [, $m]=$mail(static fn()=>AccountEmailService::request($case,'a09-'.$stamp.'-later-two@example.invalid','Local-Email-Guard-Only'));AccountEmailService::confirm($case,$token($m,'verify_email'));$reject(static fn()=>AccountEmailService::undo($firstUndo),'A later completed change supersedes older undo');
    $case=$make('conflict');$prior=(string)$scalar('SELECT email FROM users WHERE user_id=?',[$case]);$proposed='a09-'.$stamp.'-conflict-new@example.invalid';
    [, $m]=$mail(static fn()=>AccountEmailService::request($case,$proposed,'Local-Email-Guard-Only'));[, $m]=$mail(static fn()=>AccountEmailService::confirm($case,$token($m,'verify_email')));$conflictUndo=$token($m,'undo_email');
    $other=$make('claimed');$pdo->prepare('UPDATE users SET email=? WHERE user_id=?')->execute([$prior,$other]);
    $reject(static fn()=>AccountEmailService::undo($conflictUndo),'Recovery cannot steal an old address claimed by another account');
    $check($scalar('SELECT email FROM users WHERE user_id=?',[$case])===$proposed&&$scalar('SELECT email FROM users WHERE user_id=?',[$other])===$prior,'Address-conflict refusal preserves both owners');
    $case=$make('password');[, $m]=$mail(static fn()=>AccountEmailService::request($case,'a09-'.$stamp.'-password-new@example.invalid','Local-Email-Guard-Only'));$pending=$token($m,'verify_email');
    AccountSecurityService::changePassword($case,'Local-Email-Guard-Only','Changed-Email-Guard-Only');$reject(static fn()=>AccountEmailService::confirm($case,$pending),'Password change invalidates pending email changes');
    $case=$make('disabled');$disabledEmail=(string)$scalar('SELECT email FROM users WHERE user_id=?',[$case]);$pdo->prepare('UPDATE users SET status="DISABLED" WHERE user_id=?')->execute([$case]);
    try{AuthService::login($disabledEmail,'incorrect');throw new LogicException('Incorrect password accepted');}catch(UserFacingException $e){$check($e->getMessage()==='Email or password is incorrect.','Disabled account is hidden before password proof');}
    try{AuthService::login($disabledEmail,'Local-Email-Guard-Only');throw new LogicException('Disabled account accepted');}catch(UserFacingException $e){$check(str_contains($e->getMessage(),'deactivated'),'Correct password permits deactivation notice');}
    $serverLog=(string)file_get_contents($dir.'/server.log');
    if(preg_match_all('/(?:Warning:|Fatal error:|Uncaught )[^\r\n]*/',$serverLog,$warnings))foreach(array_slice($warnings[0],0,5) as $warning)fwrite(STDERR,preg_replace('/[a-f0-9]{32,}|[\w.+-]+@[\w.-]+/i','[redacted]',substr($warning,0,500))."\n");
    $check(!preg_match('/Warning:|Fatal error:|Uncaught /',$serverLog),'HTTP fixture logs have no PHP warnings or uncaught errors');
}catch(Throwable $e){$failed=true;fwrite(STDERR,'FAIL: A-09 '.$e->getMessage()."\n".$e->getTraceAsString()."\n");}
finally{
    if(is_resource($server)){proc_terminate($server);proc_close($server);}if($pdo->inTransaction())$pdo->rollBack();if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
    foreach($users as $id){$pdo->prepare('DELETE FROM email_delivery_log WHERE sent_by_user_id=? OR recipient_email LIKE ?')->execute([$id,'a09-'.$stamp.'%']);$pdo->prepare('DELETE FROM user_invitations WHERE invited_by_user_id=?')->execute([$id]);$pdo->prepare('DELETE FROM users WHERE user_id=?')->execute([$id]);}
    foreach(array_diff($pdo->query('SELECT throttle_key FROM auth_throttle')->fetchAll(PDO::FETCH_COLUMN),$throttles) as $key)$pdo->prepare('DELETE FROM auth_throttle WHERE throttle_key=?')->execute([$key]);
}
echo count($checks).' email-privacy checks '.($failed?'before failure':'passed')."\n";exit($failed?1:0);
