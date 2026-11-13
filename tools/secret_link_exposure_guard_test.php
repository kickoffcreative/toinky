<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);
// Re-execute with an isolated mail transport, never the developer/server mail setup.
if(($argv[1]??'')!=='--worker'){
    $dir=sys_get_temp_dir().'/toinky-a01-'.bin2hex(random_bytes(8));mkdir($dir,0700);file_put_contents($dir.'/test-marker','local');
    putenv('TOINKY_ACCOUNT_LINK_TEST_DIR='.$dir);putenv('GAME2_SMTP_HOST=');putenv('GAME2_SMTP_USERNAME=');putenv('GAME2_SMTP_PASSWORD=');putenv('GAME2_BASE_URL=http://localhost');
    $transport=escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/fixtures/account_link_test_mail.php');
    $process=proc_open([PHP_BINARY,'-d','sendmail_path='.$transport,'-d','session.save_path='.$dir,__FILE__,'--worker'],[0=>['pipe','r'],1=>STDOUT,2=>STDERR],$pipes,$root);
    if(!is_resource($process))throw new RuntimeException('Could not start isolated guard.');fclose($pipes[0]);$exit=proc_close($process);
    foreach(glob($dir.'/*')?:[] as $file)if(is_file($file))unlink($file);rmdir($dir);exit($exit);
}
require_once $root.'/app/bootstrap.php';require_once $root.'/app/MigrationService.php';require_once $root.'/app/AuthService.php';require_once $root.'/app/UserProfileService.php';require_once $root.'/app/LobbyService.php';
$pdo=db();$name=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if(!str_ends_with($name,'_test'))throw new RuntimeException('Use an isolated database ending in _test.');
MigrationService::run();
$dir=(string)getenv('TOINKY_ACCOUNT_LINK_TEST_DIR');$users=[];$sid=0;$server=null;$results=[];$stamp=bin2hex(random_bytes(6));$_SERVER['REMOTE_ADDR']='2001:db8::'.substr($stamp,0,4);$_SERVER['SERVER_ADDR']='127.0.0.1';
$a01ThrottleBefore=$pdo->query('SELECT throttle_key FROM auth_throttle')->fetchAll(PDO::FETCH_COLUMN);
$testClientIp='127.'.hexdec(substr($stamp,0,2)).'.'.hexdec(substr($stamp,2,2)).'.'.hexdec(substr($stamp,4,2));
$failed=false;
putenv('GAME2_PRIVACY_APPROVED=1');
$register=static function(...$args):array{PrivacyService::checkAge('1990-01-01');while(count($args)<5)$args[]=null;$cfg=PrivacyService::config();$args[]=['accept_policies'=>'1','terms_version'=>$cfg['terms_version'],'privacy_version'=>$cfg['privacy_version']];return AuthService::register(...$args);};
$check=static function(bool $ok,string $label)use(&$results):void{if(!$ok)throw new RuntimeException($label);$results[]=$label;};
$reject=static function(callable $fn)use($check):void{$denied=false;try{$fn();}catch(RuntimeException $e){$denied=true;}$check($denied,'Unauthorized or invalid operation refused');};
$capture=static function(callable $fn)use($dir):array{file_put_contents($dir.'/mail.jsonl','');$result=$fn();$mail=[];foreach(file($dir.'/mail.jsonl',FILE_IGNORE_NEW_LINES)?:[] as $line)$mail[]=json_decode($line,true)['body'];return [$result,implode("\n",$mail)];};
$token=static function(string $mail,string $key):string{if(!preg_match('/(?:[?&]|&amp;)'.preg_quote($key,'/').'=?([a-f0-9]{48})/',$mail,$m))throw new RuntimeException('Test mail did not contain expected link.');return $m[1];};
$makeSession=static function(int $id,?int $version=1)use($dir):array{
    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();$cookie=bin2hex(random_bytes(16));session_id($cookie);AuthService::boot();$cookie=session_id();$_SESSION=['user_id'=>$id,'csrf_token'=>bin2hex(random_bytes(24))];if($version!==null)$_SESSION['session_version']=$version;if($id>0)SessionService::register($id,true);$csrf=$_SESSION['csrf_token'];session_write_close();return [$cookie,$csrf];
};
try{
    foreach([[0,0,1],[1,0,1],[1,1,1],[0,0,0]] as $i=>$roles){
        $email='a01-'.$stamp.'-'.$i.'@example.invalid';
        $pdo->prepare('INSERT INTO users(email,password_hash,display_name,is_admin,is_super_admin,email_verified_at_utc,last_login_at_utc) VALUES(?,?,?,?,?, ?,UTC_TIMESTAMP())')->execute([$email,password_hash('Original-Test-Password',PASSWORD_DEFAULT),'A01 Fixture '.$i,$roles[0],$roles[1],$roles[2]?gmdate('Y-m-d H:i:s'):null]);$users[]=(int)$pdo->lastInsertId();$cfg=PrivacyService::config();PrivacyService::recordConsent((int)$pdo->lastInsertId(),['accept_policies'=>'1','terms_version'=>$cfg['terms_version'],'privacy_version'=>$cfg['privacy_version']]);
    }
    $email=static fn(int $i):string=>'a01-'.$stamp.'-'.$i.'@example.invalid';
    [$sent,$mail]=$capture(static fn()=>AuthService::createPasswordResetForEmail($email(0)));
    $check($sent===['sent'=>true],'Reset returns delivery status only');$check(AuthService::createPasswordResetForEmail('a01-unknown-'.$stamp.'@example.invalid')===['sent'=>false],'Unknown reset account returns status only');$firstToken=$token($mail,'token');
    $q=$pdo->prepare('SELECT TIMESTAMPDIFF(SECOND,created_at_utc,expires_at_utc) lifetime,token_hash FROM password_reset_tokens WHERE user_id=? ORDER BY password_reset_id DESC LIMIT 1');$q->execute([$users[0]]);$row=$q->fetch();
    $check((int)$row['lifetime']===1800&&hash_equals($row['token_hash'],hash('sha256',$firstToken)),'Reset expires after 30 minutes and stores only its hash');
    [$secondSession]=$makeSession($users[0]);
    [$sent,$mail]=$capture(static fn()=>AuthService::createPasswordResetForEmail($email(0)));$secondToken=$token($mail,'token');
    [$unused,$changedMail]=$capture(static fn()=>AuthService::resetPassword($firstToken,'Replacement-Test-Password'));
    $check(str_contains($changedMail,'Your Toinky password was changed'),'Password change notice attempted');
    $reject(static fn()=>AuthService::resetPassword($secondToken,'Should-Not-Be-Accepted'));
    session_id($secondSession);$check(AuthService::currentUser()===null,'Second pre-reset session is revoked on next authenticated request');
    $q=$pdo->prepare('SELECT COUNT(*) FROM password_reset_tokens WHERE user_id=? AND used_at_utc IS NULL');$q->execute([$users[0]]);$check((int)$q->fetchColumn()===0,'All outstanding reset tokens consumed together');
    $reject(static fn()=>AccountSecurityService::adminPasswordReset($users[1],$users[2]));
    $reject(static fn()=>AccountSecurityService::adminPasswordReset($users[1],$users[1]));
    $reject(static fn()=>AccountSecurityService::adminPasswordReset($users[0],$users[1]));
    $q=$pdo->prepare('SELECT COUNT(*) FROM account_security_audit WHERE event_code="ADMIN_PASSWORD_RESET" AND outcome="DENIED" AND actor_user_id IN (?,?,?)');$q->execute([$users[0],$users[1],$users[2]]);$check((int)$q->fetchColumn()===3,'Denied privileged resets have durable audit records');
    [$sent,$mail]=$capture(static fn()=>AccountSecurityService::adminPasswordReset($users[2],$users[1]));
    $check($sent===['sent'=>true]&&str_contains($mail,'administrator requested'),'Super Admin reset emails target and separate administrator notice');
    $reject(static fn()=>LobbyService::createGame($users[3],'NOT_A_STORY',300));
    $reject(static fn()=>AuthService::createInvitation('a01-invite-'.$stamp.'@example.invalid',$users[3]));
    $reject(static fn()=>UserProfileService::requestFriend($users[3],$email(0)));
    [$sent,$mail]=$capture(static fn()=>AuthService::createInvitation('a01-invite-'.$stamp.'@example.invalid',$users[1]));
    $check($sent===['sent'=>true],'Account invitation returns status only');$inviteToken=$token($mail,'invite');
    [$registered]=$capture(static fn()=>$register('a01-invite-'.$stamp.'@example.invalid','Invited Fixture','Invited-Test-Password',$inviteToken));$users[]=(int)$registered['user_id'];
    $check(!empty($registered['email_verified_at_utc']),'Address-matched successfully emailed invitation verifies mailbox');
    [$ordinary,$mail]=$capture(static fn()=>$register('a01-ordinary-'.$stamp.'@example.invalid','Ordinary Fixture','Ordinary-Test-Password'));$users[]=(int)$ordinary['user_id'];
    $check(empty($ordinary['email_verified_at_utc']),'Ordinary registration remains unverified');$verifyToken=$token($mail,'token');
    AccountSecurityService::verifyEmail($verifyToken);AccountSecurityService::requireVerifiedEmail((int)$ordinary['user_id']);$check(true,'Emailed verification unlocks verified-only actions');
    $reject(static fn()=>AccountSecurityService::verifyEmail($verifyToken));
    // A pending invitation created before the migration/on-screen fallback has no delivery proof.
    $legacyToken=bin2hex(random_bytes(24));$pdo->prepare('INSERT INTO user_invitations(email,token_hash,invited_by_user_id,expires_at_utc) VALUES(?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 7 DAY))')->execute(['a01-legacy-'.$stamp.'@example.invalid',hash('sha256',$legacyToken),$users[1]]);
    $reject(static fn()=>$register('a01-legacy-'.$stamp.'@example.invalid','Legacy Fixture','Legacy-Test-Password',$legacyToken));
    // A game fixture is needed for the actual lobby mail response, not a simulated playthrough.
    foreach(['BehaviorService','CausalService','DynamicOpportunityService','CharacterAutomationService','StateSyncService','SimulationService'] as $service)require_once $root.'/app/'.$service.'.php';
    $sid=SimulationService::create('EMPTY_THRONE',300,[]);$pdo->prepare('INSERT INTO games(simulation_id,host_user_id,join_code,play_duration_seconds) VALUES(?,?,?,300)')->execute([$sid,$users[1],strtoupper(substr($stamp,0,8))]);$gid=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO game_players(game_id,user_id) VALUES(?,?)')->execute([$gid,$users[1]]);
    [$sent,$mail]=$capture(static fn()=>AuthService::createGameInvitation('a01-game-'.$stamp.'@example.invalid',$gid,$users[1]));$check($sent===['sent'=>true],'Game invitation returns status only');$gameToken=$token($mail,'game_invite');
    [$gameUser]=$capture(static fn()=>$register('a01-game-'.$stamp.'@example.invalid','Invited Game Fixture','Invited-Game-Password',null,$gameToken));$users[]=(int)$gameUser['user_id'];$check(!empty($gameUser['email_verified_at_utc'])&&(int)$gameUser['_game_id']===$gid,'Successfully emailed game invitation verifies matching address and joins lobby');
    $reject(static fn()=>$register('a01-other-'.$stamp.'@example.invalid','Wrong Address','Wrong-Address-Password',null,$gameToken));
    // Verification is bound to the mailbox at issuance, not just a user id.
    [$result,$mail]=$capture(static fn()=>AccountSecurityService::requestVerification($users[3]));$staleVerify=$token($mail,'token');
    $pdo->prepare('UPDATE users SET email=? WHERE user_id=?')->execute(['a01-snapshot-'.$stamp.'@example.invalid',$users[3]]);
    $reject(static fn()=>AccountSecurityService::verifyEmail($staleVerify));$pdo->prepare('UPDATE users SET email=? WHERE user_id=?')->execute([$email(3),$users[3]]);
    // Force delivery failure for each secret-link producer.
    file_put_contents($dir.'/fail-mail','1');
    foreach([
        static fn()=>AuthService::createPasswordResetForEmail($email(0)),
        static fn()=>AuthService::createInvitation('a01-failed-'.$stamp.'@example.invalid',$users[1]),
        static fn()=>AuthService::createGameInvitation('a01-failed-game-'.$stamp.'@example.invalid',$gid,$users[1]),
        static fn()=>UserProfileService::requestEmailChange(['user_id'=>$users[0],'email'=>$email(0)],'a01-changed-'.$stamp.'@example.invalid'),
        static fn()=>AccountSecurityService::requestVerification($users[3])
    ] as $producer){[$result,$mail]=$capture($producer);$check($result===['sent'=>false],'Failed delivery returns no secret');}
    [$result,$mail]=$capture(static fn()=>AccountSecurityService::adminPasswordReset($users[2],$users[1]));
    $check($result===['sent'=>false]&&str_contains($mail,'administrator requested'),'Administrator reset notice attempted even when reset delivery fails');
    [$result,$mail]=$capture(static fn()=>AuthService::createPasswordResetForEmail($email(3)));$failedToken=$token($mail,'token');$reject(static fn()=>AuthService::resetPassword($failedToken,'Failed-Mail-Password'));
    [$result,$mail]=$capture(static fn()=>AuthService::createInvitation('a01-undelivered-'.$stamp.'@example.invalid',$users[1]));$undeliveredInvite=$token($mail,'invite');
    $_SERVER['REMOTE_ADDR']='2001:db8:1::'.substr($stamp,0,4);
    $reject(static fn()=>$register('a01-undelivered-'.$stamp.'@example.invalid','Undelivered Fixture','Undelivered-Password',$undeliveredInvite));
    // HTTP regression checks render the real admin, profile and lobby response paths.
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr);$address=stream_socket_get_name($socket,false);fclose($socket);
    $transport=escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/fixtures/account_link_test_mail.php');
    $server=proc_open([PHP_BINARY,'-d','sendmail_path='.$transport,'-d','session.save_path='.$dir,'-S',$address,'-t',$root.'/public'],[0=>['pipe','r'],1=>['file',$dir.'/server.log','a'],2=>['file',$dir.'/server.log','a']],$pipes,$root);fclose($pipes[0]);
    $ready=false;for($i=0;$i<50;$i++){$probe=@stream_socket_client('tcp://'.$address,$errno,$errstr,0.1);if($probe){fclose($probe);$ready=true;break;}usleep(100000);}$check($ready,'Local HTTP server starts');
    $http=static function(string $path,string $cookie,array $data=[])use($address,$testClientIp):array{
        $headers='Cookie: '.AuthService::SESSION_NAME.'='.$cookie."\r\n";$options=['ignore_errors'=>true,'follow_location'=>0,'timeout'=>20,'header'=>$headers];
        if($data){$options['method']='POST';$options['header'].="Content-Type: application/x-www-form-urlencoded\r\n";$options['content']=http_build_query($data);}
        $body=file_get_contents('http://'.$address.$path,false,stream_context_create(['http'=>$options,'socket'=>['bindto'=>$testClientIp.':0']]));return [$body===false?'':$body,$http_response_header??[]];
    };
    foreach([
        ['/admin.php?view=accounts',$users[1],['action'=>'reset_user','user_id'=>$users[0]]],
        ['/admin.php?view=invitations',$users[1],['action'=>'invite','email'=>'a01-http-'.$stamp.'@example.invalid']],
        ['/profile.php',$users[1],['action'=>'send_password_reset']],
        ['/profile.php',$users[1],['action'=>'invite_player','email'=>'a01-http-profile-'.$stamp.'@example.invalid']],
        ['/profile.php',$users[1],['action'=>'request_email_change','new_email'=>'a01-http-change-'.$stamp.'@example.invalid']]
    ] as [$path,$uid,$data]){
        [$cookie,$csrf]=$makeSession($uid);$data['csrf_token']=$csrf;file_put_contents($dir.'/mail.jsonl','');[$body,$headers]=$http($path,$cookie,$data);
        $check(str_contains($headers[0]??'','200')&&str_contains($body,'Email could not be sent. Try again later.'),'Real response renders fixed mail failure');
        $mail=file_get_contents($dir.'/mail.jsonl');preg_match_all('/[a-f0-9]{48}/',$mail,$matches);foreach($matches[0] as $secret)$check(!str_contains($body,$secret),'Secret never appears in rendered response');
    }
    [$cookie,$csrf]=$makeSession($users[1]);[$body,$headers]=$http('/lobby.php?game='.$gid,$cookie,['action'=>'invite_player','emails'=>'a01-http-game-'.$stamp.'@example.invalid','csrf_token'=>$csrf]);
    $check(str_contains($headers[0]??'','302'),'Lobby failure uses redirect');[$body]=$http('/lobby.php?game='.$gid,$cookie);$check(str_contains($body,'Email could not be sent. Try again later.')&&!str_contains($body,'Share these secure links'),'Lobby failure renders fixed notice without invitation link');
    [$cookie]=$makeSession($users[0],1);[$body,$headers]=$http('/profile.php',$cookie);$check(str_contains($headers[0]??'','302'),'Second-browser HTTP request after reset redirects to login');
    [$cookie]=$makeSession($users[1]);[$body,$headers]=$http('/?view=reset&token='.$firstToken,$cookie);$check(str_contains($headers[0]??'','302')&&!str_contains($body,$firstToken),'Incoming reset token redirects without response exposure');[$body]=$http('/?view=reset',$cookie);$check(!str_contains($body,$firstToken),'Reset form contains no hidden bearer token');
    // Account existence and throttle state must not change the forgot-password response.
    [$cookie,$csrf]=$makeSession(0,null);[$forgotPage]=$http('/?view=forgot',$cookie);if(!preg_match('/name="auth_form_token" value="([^"]+)"/',$forgotPage,$formMatch))throw new RuntimeException('Forgot-password form protection field missing');$formToken=html_entity_decode($formMatch[1],ENT_QUOTES,'UTF-8');usleep(2100000);$forgot=['auth_form_token'=>$formToken,'action'=>'forgot','email'=>'a01-forgot-'.$stamp.'@example.invalid','csrf_token'=>$csrf];
    $normalizeNonce=static fn(string $html):string=>(string)preg_replace('/nonce="[^"]+"/','nonce="per-request"',$html);
    [$ordinaryForgot]=$http('/?view=forgot',$cookie,$forgot);for($i=0;$i<3;$i++)[$limitedForgot]=$http('/?view=forgot',$cookie,$forgot);
    $check($normalizeNonce($ordinaryForgot)===$normalizeNonce($limitedForgot),'Forgot-password response is identical when email throttle is exhausted');
    $forgot['email']='a01-other-forgot-'.$stamp.'@example.invalid';[$unknownForgot]=$http('/?view=forgot',$cookie,$forgot);
    $check($normalizeNonce($ordinaryForgot)===$normalizeNonce($unknownForgot),'Forgot-password response is identical across unknown account addresses');
    unlink($dir.'/fail-mail');
    [$cookie,$csrf]=$makeSession($users[1]);[$body,$headers]=$http('/profile.php',$cookie,['action'=>'change_password','current_password'=>'Original-Test-Password','new_password'=>'Http-Replacement-Password','csrf_token'=>$csrf]);
    $check(str_contains($headers[0]??'','302')&&str_contains(implode(' ', $headers),'password_changed=1'),'Profile changes password with current password and signs out');
    $reject(static fn()=>AccountSecurityService::changePassword($users[1],'Incorrect-Password','New-Current-Password'));
    [$unused,$mail]=$capture(static fn()=>AccountSecurityService::changePassword($users[2],'Original-Test-Password','New-Current-Password'));$check(str_contains($mail,'Your Toinky password was changed'),'Current-password change sends security notice');
    echo 'PASS: A-01 '.count($results)." security and HTTP checks.\n";
}catch(Throwable $e){fwrite(STDERR,'FAIL: A-01 '.$e->getMessage()."\n");$failed=true;}
finally{
    if(is_resource($server)){proc_terminate($server);proc_close($server);}
    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
    if($sid)$pdo->prepare('DELETE FROM simulations WHERE simulation_id=?')->execute([$sid]);
    foreach(array_diff($pdo->query('SELECT throttle_key FROM auth_throttle')->fetchAll(PDO::FETCH_COLUMN),$a01ThrottleBefore) as $key)$pdo->prepare('DELETE FROM auth_throttle WHERE throttle_key=?')->execute([$key]);
    foreach($users as $id){$pdo->prepare('DELETE FROM user_invitations WHERE invited_by_user_id=?')->execute([$id]);$pdo->prepare('DELETE FROM account_security_audit WHERE actor_user_id=? OR target_user_id=?')->execute([$id,$id]);$pdo->prepare('DELETE FROM users WHERE user_id=?')->execute([$id]);}
}

if($failed)exit(1);
