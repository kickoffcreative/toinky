<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);require_once $root.'/app/bootstrap.php';require_once $root.'/app/MigrationService.php';require_once $root.'/app/AuthService.php';require_once $root.'/app/LobbyService.php';
$pdo=db();if(!str_ends_with((string)$pdo->query('SELECT DATABASE()')->fetchColumn(),'_test'))throw new RuntimeException('Use an isolated _test database.');MigrationService::run();
$stamp=bin2hex(random_bytes(8));$keys=[];$ids=[];$checks=0;$case=0;$failed=false;
// Real independent connections contend on the same key; no production bypass exists.
if(($argv[1]??'')==='--hit'){
    RateLimitService::hit('GUARD_CONCURRENT',(string)($argv[2]??''),100,600,600);exit;
}
$log=tempnam(sys_get_temp_dir(),'toinky-rate-');$oldLog=ini_get('error_log');ini_set('error_log',$log);
$check=static function(bool $ok,string $label)use(&$checks):void{if(!$ok)throw new RuntimeException($label);$checks++;};
$limited=static function(callable $fn)use($check):void{$blocked=false;try{$fn();}catch(RateLimitException $e){$blocked=$e->getMessage()==='Too many attempts. Try again later.';}$check($blocked,'Fixed throttle refusal required');};
$allKeys=static fn():array=>$pdo->query('SELECT throttle_key FROM auth_throttle')->fetchAll(PDO::FETCH_COLUMN);
$remember=static function(array $before)use($allKeys,&$keys):array{$created=array_values(array_diff($allKeys(),$before));$keys=array_values(array_unique(array_merge($keys,$created)));return $created;};
$expire=static function(array $owned,int $window=3600)use($pdo):void{foreach($owned as $key)$pdo->prepare('UPDATE auth_throttle SET window_start_utc=DATE_SUB(UTC_TIMESTAMP(),INTERVAL ? SECOND),locked_until_utc=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE throttle_key=?')->execute([$window+1,$key]);};
$exercise=static function(string $label,callable $fn,int $max,int $window=3600)use(&$case,$stamp,$check,$limited,$allKeys,$remember,$expire):void{
    $_SERVER['REMOTE_ADDR']='2001:db8:'.dechex(++$case).'::'.substr($stamp,0,4);$before=$allKeys();
    try{for($i=0;$i<$max;$i++)$fn($i);$check(true,$label.' allows its configured budget');$limited(static fn()=>$fn($max));}
    finally{$owned=$remember($before);}
    $expire($owned,$window);$fn($max+1);$check(true,$label.' clears after window and cooldown');$remember($before);
};
try{
    $_SERVER['REMOTE_ADDR']='2001:db8::1';$_SERVER['HTTP_X_FORWARDED_FOR']='192.0.2.255';$check(RateLimitService::clientIp()==='2001:db8::1','Forwarded IP headers ignored');
    $email=$stamp.'@example.invalid';
    $exercise('Sign-in account',static function(int $i)use($email){RateLimitService::checkSignIn($email);RateLimitService::failedSignIn($email);},5,900);
    $exercise('Sign-in IP',static function(int $i)use($stamp){$email=$stamp.'-'.$i.'@example.invalid';RateLimitService::checkSignIn($email);RateLimitService::failedSignIn($email);},20,900);
    $exercise('Reset email',static fn(int $i)=>RateLimitService::resetRequest('reset-'.$email),3);
    $exercise('Reset IP',static fn(int $i)=>RateLimitService::resetRequest('reset-'.$i.'-'.$email),10);
    $exercise('Registration IP',static fn(int $i)=>RateLimitService::registration(),5);
    $exercise('Invitation sender',static fn(int $i)=>RateLimitService::invitation(81001,'sender-'.$i.'-'.$email),20,86400);
    $exercise('Invitation recipient across senders',static fn(int $i)=>RateLimitService::invitation(82000+$i,'recipient-'.$email),5,86400);
    $exercise('Invitation IP',static fn(int $i)=>RateLimitService::invitation(83000+$i,'ip-'.$i.'-'.$email),100,86400);
    foreach([
        ['Join user',static fn(int $i)=>RateLimitService::failedJoin(84001),10,600],
        ['Join IP',static fn(int $i)=>RateLimitService::failedJoin(85000+$i),40,600],
        ['Friend request user',static fn(int $i)=>RateLimitService::friendRequest(86001),10],
        ['Friend request IP',static fn(int $i)=>RateLimitService::friendRequest(87000+$i),40],
        ['Email change user',static fn(int $i)=>RateLimitService::emailChange(88001),10],
        ['Email change IP',static fn(int $i)=>RateLimitService::emailChange(89000+$i),40],
        ['AI user',static fn(int $i)=>RateLimitService::ai(90001),30,600],
        ['AI IP',static fn(int $i)=>RateLimitService::ai(91000+$i),120,600],
        ['Sign-in code user',static fn(int $i)=>RateLimitService::codeAttempt(92001),5,600],
        ['Sign-in code IP',static fn(int $i)=>RateLimitService::codeAttempt(93000+$i),20,600],
        ['Code resend user',static fn(int $i)=>RateLimitService::codeResend(94001),5],
        ['Code resend IP',static fn(int $i)=>RateLimitService::codeResend(95000+$i),20],
        ['Verification resend',static fn(int $i)=>RateLimitService::verification(96001),3],
        ['Administrator reset',static fn(int $i)=>RateLimitService::adminReset(97001),20],
        ['Reset token',static fn(int $i)=>RateLimitService::resetAttempt('guard-'.$stamp),5,900]
    ] as $policy){[$label,$fn,$max]=$policy;$exercise($label,$fn,$max,$policy[3]??3600);}
    // Repeated account lockouts grow without lengthening a lock on every blocked request.
    $action='SIGN_IN_ACCOUNT';$subject='growth-'.$stamp;$key=RateLimitService::key($action,$subject);$keys[]=$key;
    RateLimitService::hit($action,$subject,1,900,900);$expire([$key],900);RateLimitService::hit($action,$subject,1,900,900);
    $q=$pdo->prepare('SELECT TIMESTAMPDIFF(SECOND,UTC_TIMESTAMP(),locked_until_utc) duration FROM auth_throttle WHERE throttle_key=?');$q->execute([$key]);$check((int)$q->fetchColumn()>=1798,'Repeated sign-in lockout grows to 30 minutes');
    // Real sign-in path: a correct password is refused during an existing failure lock.
    $_SERVER['REMOTE_ADDR']='2001:db8:ff::'.substr($stamp,0,4);$before=$allKeys();
    $pdo->prepare('INSERT INTO users(email,password_hash,display_name,email_verified_at_utc) VALUES(?,?,?,UTC_TIMESTAMP())')->execute(['login-'.$email,password_hash('Correct-Guard-Password',PASSWORD_DEFAULT),'Rate Guard']);$id=(int)$pdo->lastInsertId();$ids[]=$id;
    for($i=0;$i<5;$i++){try{AuthService::login('login-'.$email,'Wrong-Guard-Password');throw new LogicException('Wrong password accepted');}catch(RuntimeException $e){if($e instanceof RateLimitException)throw $e;}}
    $limited(static fn()=>AuthService::login('login-'.$email,'Correct-Guard-Password'));$remember($before);
    // Five wrong codes void the actual stored code, not just this PHP session.
    $pdo->prepare('INSERT INTO user_two_factor_codes(user_id,code_hash,expires_at_utc) VALUES(?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 10 MINUTE))')->execute([$id,hash('sha256','123456')]);$codeId=(int)$pdo->lastInsertId();
    AuthService::boot();$_SESSION['two_factor_pending_user_id']=$id;$_SESSION['two_factor_pending_version']=1;$before=$allKeys();
    for($i=0;$i<5;$i++){try{AuthService::verifyTwoFactorCode($id,'000000');throw new LogicException('Wrong code accepted');}catch(RuntimeException $e){if($e instanceof RateLimitException)throw $e;}}
    $q=$pdo->prepare('SELECT failed_attempts,used_at_utc FROM user_two_factor_codes WHERE user_two_factor_code_id=?');$q->execute([$codeId]);$row=$q->fetch();$check((int)$row['failed_attempts']===5&&$row['used_at_utc']!==null,'Fifth wrong code atomically voids code');
    $denied=false;try{AuthService::verifyTwoFactorCode($id,'123456');}catch(RuntimeException $e){$denied=true;}$check($denied&&AuthService::currentUser()===null,'Sixth code attempt refused even with correct code');$remember($before);
    $_SESSION['two_factor_pending_user_id']=$id;$_SESSION['two_factor_pending_version']=1;$limited(static fn()=>AuthService::verifyTwoFactorCode($id,'123456'));
    // Invalid join attempts persist after their operation transaction rolls back.
    $before=$allKeys();for($i=0;$i<10;$i++){try{LobbyService::joinByCode($id,'MISSING');throw new LogicException('Invalid join accepted');}catch(RuntimeException $e){if($e instanceof RateLimitException)throw $e;}}
    $limited(static fn()=>LobbyService::joinByCode($id,'MISSING'));$remember($before);
    $before=$allKeys();$children=[];
    for($i=0;$i<12;$i++){$process=proc_open([PHP_BINARY,__FILE__,'--hit',$stamp],[0=>['pipe','r'],1=>['file','/dev/null','a'],2=>['file','/dev/null','a']],$pipes,$root);if(!is_resource($process))throw new RuntimeException('Could not start concurrency worker');fclose($pipes[0]);$children[]=$process;}
    foreach($children as $process)$check(proc_close($process)===0,'Concurrent counter worker completes');$remember($before);
    $q=$pdo->prepare('SELECT attempts FROM auth_throttle WHERE throttle_key=?');$q->execute([RateLimitService::key('GUARD_CONCURRENT',$stamp)]);$check((int)$q->fetchColumn()===12,'Atomic upsert loses no concurrent attempts');
    foreach($keys as $key)$check(preg_match('/^[a-f0-9]{64}$/D',$key)===1,'Stored key is SHA-256');
    $logs=file_get_contents($log);$check(str_contains($logs,'[cid=')&&str_contains($logs,'Account throttle lockout')&&!str_contains($logs,$email)&&!str_contains($logs,'2001:db8'),'Lockouts log correlation id and no raw email or IP');
    foreach(['public/inky-action.php','public/inky-starter.php'] as $file)$check(str_contains(file_get_contents($root.'/'.$file),'RateLimitService::ai('),'Both AI endpoints enforce shared budget');
    $check(str_contains(file_get_contents($root.'/tools/prune_expired_tokens.php'),"DELETE FROM auth_throttle"),'Pruning includes inactive old throttle rows');
    echo 'PASS: A-02 '.$checks." checks, every configured limit and cooldown, atomic counters and real auth/code/join paths.\n";
}catch(Throwable $e){fwrite(STDERR,'FAIL: A-02 '.$e->getMessage()."\n");$failed=true;}
finally{
    foreach($keys as $key)$pdo->prepare('DELETE FROM auth_throttle WHERE throttle_key=?')->execute([$key]);
    foreach($ids as $id)$pdo->prepare('DELETE FROM users WHERE user_id=?')->execute([$id]);
    if(session_status()===PHP_SESSION_ACTIVE){$_SESSION=[];session_destroy();}
    ini_set('error_log',(string)$oldLog);if(is_file($log))unlink($log);
}
if($failed)exit(1);
