<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$source=dirname(__DIR__);
$databaseConfig=(string)getenv('GAME2_DATABASE_CONFIG');
if($databaseConfig===''||!is_file($databaseConfig))throw new RuntimeException('Explicit isolated test database configuration required.');
$cfg=require $databaseConfig;
$probe=new PDO($cfg['dsn'],$cfg['username'],$cfg['password']);
if(!str_ends_with((string)$probe->query('SELECT DATABASE()')->fetchColumn(),'_test'))throw new RuntimeException('Use a database ending in _test.');
$fixture=sys_get_temp_dir().'/toinky-privacy-'.bin2hex(random_bytes(8));
foreach(['','/app','/config','/public','/public/uploads','/public/uploads/profiles','/sessions'] as $part)mkdir($fixture.$part,0700);
foreach(['app','public'] as $part)foreach(glob($source.'/'.$part.'/*.php')?:[] as $file)copy($file,$fixture.'/'.$part.'/'.basename($file));
foreach(['privacy.php','ai-policy.php'] as $file)copy($source.'/config/'.$file,$fixture.'/config/'.$file);
symlink($source.'/database',$fixture.'/database');
ini_set('session.save_path',$fixture.'/sessions');
putenv('GAME2_PRIVACY_APPROVED=1');putenv('GAME2_AI_PROVIDER=deterministic');
require $fixture.'/app/bootstrap.php';require $fixture.'/app/AuthService.php';require $fixture.'/app/PrivacyDataService.php';require $fixture.'/app/PrivacyRetentionService.php';require $fixture.'/app/MigrationService.php';
$pdo=db();MigrationService::run();AuthService::boot();
$users=[];$ledger=[];$purchases=[];$ai=[];$mail=[];$checks=[];$server=null;$sid=0;$plot=0;$failed=false;$stamp=bin2hex(random_bytes(6));$password='Privacy-Guard-Only-Password';
$_SERVER['REMOTE_ADDR']='2001:db8::'.substr($stamp,0,4);$_SERVER['SERVER_ADDR']='127.0.0.1';
$check=static function(bool $ok,string $name)use(&$checks):void{if(!$ok)throw new RuntimeException($name);$checks[]=$name;};
$reject=static function(callable $call,string $name)use($check):void{try{$call();}catch(PrivacyException|RateLimitException $e){$check(true,$name);return;}throw new RuntimeException('Not refused: '.$name);};
$scalar=static function(string $sql,array $args=[] )use($pdo):mixed{$q=$pdo->prepare($sql);$q->execute($args);return $q->fetchColumn();};
$makeUser=static function(string $label,bool $super=false)use($pdo,&$users,$stamp,$password):int{$pdo->prepare('INSERT INTO users(email,password_hash,display_name,is_super_admin,email_verified_at_utc) VALUES(?,?,?,?,UTC_TIMESTAMP())')->execute(['privacy-'.$stamp.'-'.$label.'@example.invalid',password_hash($password,PASSWORD_DEFAULT),'Privacy '.$label,(int)$super]);$id=(int)$pdo->lastInsertId();$users[]=$id;PrivacyService::rememberEmail($id,'privacy-'.$stamp.'-'.$label.'@example.invalid');return $id;};
$consent=static function():array{$c=PrivacyService::config();return ['accept_policies'=>'1','terms_version'=>$c['terms_version'],'privacy_version'=>$c['privacy_version']];};
$resetPasswordLimit=static function(int $id)use($pdo):void{$pdo->prepare('DELETE FROM auth_throttle WHERE throttle_key=?')->execute([RateLimitService::key('PRIVACY_PASSWORD_USER',(string)$id)]);};
$export=static function(int $id)use($password):array{$out='';PrivacyDataService::streamExport($id,$password,static function(string $chunk)use(&$out):void{$out.=$chunk;});return json_decode($out,true,512,JSON_THROW_ON_ERROR);};
try{
    $owner=$makeUser('owner');$peer=$makeUser('peer');$super=$makeUser('super',true);
    $pdo->prepare('INSERT INTO plots(code,title,archetype,genre,setting_name,author_user_id,scenario_text) VALUES(?,"Privacy fixture","Mystery","Mystery","Test shore",?,"Shared fictional story survives")')->execute(['PRIVACY_'.$stamp,$owner]);$plot=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO simulations(plot_id,duration_seconds) VALUES(?,300)')->execute([$plot]);$sid=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO games(simulation_id,host_user_id,join_code,play_duration_seconds) VALUES(?,?,?,300)')->execute([$sid,$owner,strtoupper($stamp)]);$game=(int)$pdo->lastInsertId();
    foreach([$owner,$peer] as $id)$pdo->prepare('INSERT INTO game_players(game_id,user_id) VALUES(?,?)')->execute([$game,$id]);
    $pdo->prepare('INSERT INTO story_ratings(game_id,user_id,rating,review_text) VALUES(?,?,8,"Personal fixture review")')->execute([$game,$owner]);
    putenv('GAME2_PRIVACY_APPROVED=0');$reject(static fn()=>PrivacyService::requireRegistrationConsent($consent()),'Unreviewed policy blocks registration');putenv('GAME2_PRIVACY_APPROVED=1');
    $today=new DateTimeImmutable('2026-11-13',new DateTimeZone('UTC'));
    $reject(static fn()=>PrivacyService::checkAge('2013-11-14',$today),'Under thirteen refused');
    $check(!PrivacyService::ageEligible()&&!isset($_SESSION['birth_date']),'Underage session stores no birth date or eligibility');
    $reject(static fn()=>PrivacyService::checkAge('1990-01-01',$today),'Retry cookie prevents immediate age resubmission');unset($_COOKIE[PrivacyService::AGE_BLOCK_COOKIE]);
    foreach(['2026-02-30','2027-01-01','not-a-date'] as $date)$reject(static fn()=>PrivacyService::checkAge($date,$today),'Invalid or future birth date refused: '.$date);
    PrivacyService::checkAge('2013-11-13',$today);$check(PrivacyService::ageEligible(),'Exactly thirteen eligible');
    $reject(static fn()=>PrivacyService::requireRegistrationConsent([]),'Unticked consent refused');
    $stale=$consent();$stale['privacy_version']='old';$reject(static fn()=>PrivacyService::requireRegistrationConsent($stale),'Stale policy version refused');
    $check(!str_contains(PrivacyService::consentFields(),' checked'),'Consent checkbox is not preselected');
    PrivacyService::recordConsent($owner,$consent());
    $q=$pdo->prepare('SELECT * FROM users WHERE user_id=?');$q->execute([$owner]);$row=$q->fetch();
    $check(!PrivacyService::needsConsent($row)&&(int)$scalar('SELECT COUNT(*) FROM user_policy_acceptances WHERE user_id=?',[$owner])===1,'Versioned consent and audit recorded');
    $row['privacy_version']='older';$check(PrivacyService::needsConsent($row),'Changed policy requires renewed consent');
    $reject(static fn()=>PrivacyDataService::streamExport($owner,'wrong',static function(string $s):void{}),'Export requires current password');
    $data=$export($owner);$encoded=json_encode($data,JSON_THROW_ON_ERROR);
    $check((int)$data['profile']['user_id']===$owner&&count($data['datasets'])>=20,'Export returns account-owned datasets');
    $check(count($data['datasets']['games'])===1&&count($data['datasets']['authored_story_content'])===1&&count($data['datasets']['ratings'])===1,'Export includes owned game, authored content and review');
    $check(!str_contains($encoded,'password_hash')&&!str_contains($encoded,'token_hash')&&!str_contains($encoded,'Privacy peer'),'Export excludes credentials and unrelated profile');
    $check($pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY)===true,'Streaming export restores connection buffering');
    // Exercise address ownership boundaries, including a reassigned address.
    $shared='privacy-'.$stamp.'-reassigned@example.invalid';
    $pdo->prepare('INSERT INTO user_email_history(user_id,email,valid_from_utc,valid_until_utc) VALUES(?,?,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 DAY),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 DAY))')->execute([$owner,$shared]);
    $pdo->prepare('UPDATE users SET email=? WHERE user_id=?')->execute([$shared,$peer]);PrivacyService::rememberEmail($peer,$shared);
    foreach([7,1] as $days){$pdo->prepare('INSERT INTO email_delivery_log(recipient_email,message_type,delivery_status,created_at_utc) VALUES(?, ?,"SENT",DATE_SUB(UTC_TIMESTAMP(),INTERVAL ? DAY))')->execute([$shared,'PRIVACY_GUARD_'.$days,$days]);$mail[$days]=(int)$pdo->lastInsertId();}
    $data=$export($owner);$check(count(array_filter($data['datasets']['email_delivery'],static fn(array $r):bool=>str_starts_with($r['message_type'],'PRIVACY_GUARD_')))===1,'Export respects former email ownership dates');
    foreach([$owner,$peer] as $id){$pdo->prepare('INSERT INTO host_purchases(user_id,stripe_checkout_session_id,stripe_payment_intent_id,status,amount_cents) VALUES(?,?,?,"PAID",500)')->execute([$id,'cs_privacy_'.$id,'pi_privacy_'.$id]);$purchases[$id]=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO host_occasion_ledger(user_id,entry_type,occasions,created_by_user_id,note) VALUES(?,"FREE_FIRST",1,?,?)')->execute([$id,$owner,'Private fixture note']);$ledger[$id]=(int)$pdo->lastInsertId();}
    foreach([[$owner,1],[$peer,1],[null,0]] as [$id,$version]){$pdo->prepare('INSERT INTO ai_reasoner_interactions(provider,model_name,request_started_at_utc,request_json,response_json,actor_user_id,actor_attribution_version,total_token_count) VALUES("deterministic","privacy-test",UTC_TIMESTAMP(),?, ?,?,?,7)')->execute(['{"private":"fixture"}','{"answer":"fixture"}',$id,$version]);$ai[]=(int)$pdo->lastInsertId();}
    $photo=$fixture.'/public/uploads/profiles/user-'.$owner.'-0123456789abcdef.png';file_put_contents($photo,'fixture');$peerPhoto=$fixture.'/public/uploads/profiles/user-'.$peer.'-0123456789abcdef.png';file_put_contents($peerPhoto,'peer fixture');
    $pdo->prepare('UPDATE users SET profile_image_path=? WHERE user_id=?')->execute(['public/uploads/profiles/'.basename($photo),$owner]);
    $resetPasswordLimit($owner);$reject(static fn()=>PrivacyDataService::eraseAccount($owner,'wrong'),'Wrong password cannot erase');
    $reject(static fn()=>PrivacyDataService::eraseAccount($super,$password),'Super Admin cannot erase ownership');
    $pdo->prepare('UPDATE users SET privacy_erasure_hold_until_utc=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY) WHERE user_id=?')->execute([$owner]);
    $reject(static fn()=>PrivacyDataService::eraseAccount($owner,$password),'Explicit erasure hold is respected');$check(is_file($photo),'Refused erasure preserves profile file');
    $pdo->prepare('UPDATE users SET privacy_erasure_hold_until_utc=NULL WHERE user_id=?')->execute([$owner]);
    $pdo->exec('CREATE TABLE privacy_guard_unknown_identity(unknown_id INT PRIMARY KEY,owner_user_id BIGINT UNSIGNED NULL)');
    try{$reject(static fn()=>PrivacyDataService::eraseAccount($owner,$password),'Unknown identity schema blocks erasure');}finally{$pdo->exec('DROP TABLE privacy_guard_unknown_identity');}
    $pdo->exec('CREATE TRIGGER privacy_guard_rollback BEFORE DELETE ON users FOR EACH ROW BEGIN IF OLD.user_id='.$owner.' THEN SIGNAL SQLSTATE "45000" SET MESSAGE_TEXT="privacy guard rollback"; END IF; END');
    try{$resetPasswordLimit($owner);$refused=false;try{PrivacyDataService::eraseAccount($owner,$password);}catch(PDOException $e){$refused=true;}$check($refused&&is_file($photo)&&(bool)$scalar('SELECT 1 FROM users WHERE user_id=?',[$owner]),'Late database failure rolls back identity and restores staged profile photo');}
    finally{$pdo->exec('DROP TRIGGER privacy_guard_rollback');}
    $resetPasswordLimit($owner);PrivacyDataService::eraseAccount($owner,$password);
    $anon=(int)$scalar('SELECT user_id FROM users WHERE system_identity_key="PRIVACY_ANONYMOUS"');
    $check(!$scalar('SELECT 1 FROM users WHERE user_id=?',[$owner]),'Original identity deleted');
    $check((int)$scalar('SELECT host_user_id FROM games WHERE game_id=?',[$game])===$anon&&(bool)$scalar('SELECT 1 FROM game_players WHERE game_id=? AND user_id=?',[$game,$peer])&&(int)$scalar('SELECT author_user_id FROM plots WHERE plot_id=?',[$plot])===$anon&&$scalar('SELECT scenario_text FROM plots WHERE plot_id=?',[$plot])==='Shared fictional story survives','Shared game, other membership and fiction survive without original attribution');
    $check(!$scalar('SELECT 1 FROM story_ratings WHERE user_id=?',[$owner]),'Personal rating and review removed');
    $check(!is_file($photo)&&is_file($peerPhoto),'Erasure removes only own profile photos');
    $check(!$scalar('SELECT 1 FROM user_email_history WHERE user_id=?',[$owner])&&!$scalar('SELECT 1 FROM user_policy_acceptances WHERE user_id=?',[$owner]),'Identity history and consent records deleted');
    $check(!$scalar('SELECT 1 FROM email_delivery_log WHERE email_delivery_id=?',[$mail[7]])&&(bool)$scalar('SELECT 1 FROM email_delivery_log WHERE email_delivery_id=?',[$mail[1]]),'Erasure preserves new email owner delivery records');
    $q=$pdo->prepare('SELECT user_id,stripe_checkout_session_id,stripe_payment_intent_id,amount_cents FROM host_purchases WHERE purchase_id=?');$q->execute([$purchases[$owner]]);$purchase=$q->fetch();
    $check((int)$purchase['user_id']===$anon&&$purchase['stripe_checkout_session_id']===null&&$purchase['stripe_payment_intent_id']===null&&(int)$purchase['amount_cents']===500,'Financial amounts retained with account and provider identity removed');
    $check($scalar('SELECT entry_type FROM host_occasion_ledger WHERE ledger_id=?',[$ledger[$owner]])==='FREE_FIRST_ERASED'&&(int)$scalar('SELECT free_first_key FROM host_occasion_ledger WHERE ledger_id=?',[$ledger[$peer]])===$peer,'Generated free-grant uniqueness preserved for remaining players');
    $check($scalar('SELECT request_json FROM ai_reasoner_interactions WHERE ai_interaction_id=?',[$ai[0]])===null&&$scalar('SELECT request_json FROM ai_reasoner_interactions WHERE ai_interaction_id=?',[$ai[2]])===null&&$scalar('SELECT request_json FROM ai_reasoner_interactions WHERE ai_interaction_id=?',[$ai[1]])!==null,'Own and legacy AI text erased while other attributed text remains');
    // A second erasure must not collide with the shared anonymous ledger identity.
    $resetPasswordLimit($peer);PrivacyDataService::eraseAccount($peer,$password);$check(!$scalar('SELECT 1 FROM users WHERE user_id=?',[$peer]),'Multiple erasures preserve financial uniqueness');
    $disabled=$makeUser('disabled');$legacy=$makeUser('legacy');$held=$makeUser('held');
    foreach([$disabled,$held] as $id)PrivacyService::recordConsent($id,$consent());
    foreach([$disabled,$legacy,$held] as $id)$pdo->prepare('UPDATE users SET status="DISABLED",updated_at_utc=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 400 DAY) WHERE user_id=?')->execute([$id]);
    $pdo->prepare('UPDATE users SET privacy_erasure_hold_until_utc=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 10 DAY) WHERE user_id=?')->execute([$held]);
    $pdo->prepare('UPDATE ai_reasoner_interactions SET request_started_at_utc=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 91 DAY),actor_user_id=?,request_json="old" WHERE ai_interaction_id=?')->execute([$legacy,$ai[1]]);
    $pdo->prepare('INSERT INTO email_delivery_log(recipient_email,message_type,delivery_status,created_at_utc) VALUES(?,"PRIVACY_GUARD_OLD","SENT",DATE_SUB(UTC_TIMESTAMP(),INTERVAL 91 DAY))')->execute(['privacy-'.$stamp.'-old@example.invalid']);$mail[91]=(int)$pdo->lastInsertId();
    $dry=PrivacyRetentionService::run();$check(!$dry['apply']&&(bool)$scalar('SELECT 1 FROM email_delivery_log WHERE email_delivery_id=?',[$mail[91]])&&(bool)$scalar('SELECT 1 FROM users WHERE user_id=?',[$disabled]),'Retention dry run makes no changes');
    PrivacyRetentionService::run(true);
    $check(!$scalar('SELECT 1 FROM email_delivery_log WHERE email_delivery_id=?',[$mail[91]]),'Retention removes delivery logs older than ninety days');
    $check($scalar('SELECT request_json FROM ai_reasoner_interactions WHERE ai_interaction_id=?',[$ai[1]])===null&&$scalar('SELECT actor_user_id FROM ai_reasoner_interactions WHERE ai_interaction_id=?',[$ai[1]])===null&&(int)$scalar('SELECT total_token_count FROM ai_reasoner_interactions WHERE ai_interaction_id=?',[$ai[1]])===7,'Retention clears old AI text and identity while keeping usage metrics');
    $check(!$scalar('SELECT 1 FROM users WHERE user_id=?',[$disabled])&&(bool)$scalar('SELECT 1 FROM users WHERE user_id=?',[$legacy])&&(bool)$scalar('SELECT 1 FROM users WHERE user_id=?',[$held]),'Scheduled erasure excludes legacy unconsented accounts and active holds');
    PrivacyRetentionService::run(true);$check(true,'Repeated retention apply is safe');
    // Real HTTP requests exercise consent routing, CSRF and ownership at entry points.
    $web=$makeUser('web');
    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();session_id('');AuthService::boot();
    $_SESSION=['user_id'=>$web,'session_version'=>1,'csrf_token'=>bin2hex(random_bytes(24))];SessionService::register((int)$_SESSION['user_id'],true);$webCookie=session_id();$csrf=$_SESSION['csrf_token'];session_write_close();
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr);$address=stream_socket_get_name($socket,false);fclose($socket);
    $server=proc_open([PHP_BINARY,'-d','sendmail_path=/bin/false','-d','session.save_path='.$fixture.'/sessions','-S',$address,'-t',$fixture.'/public'],[0=>['pipe','r'],1=>['file',$fixture.'/server.log','a'],2=>['file',$fixture.'/server.log','a']],$pipes,$fixture);if(!is_resource($server))throw new RuntimeException('HTTP fixture failed');fclose($pipes[0]);
    $ready=false;for($i=0;$i<40;$i++){$connection=@stream_socket_client('tcp://'.$address,$errno,$errstr,0.1);if($connection){fclose($connection);$ready=true;break;}usleep(100000);}$check($ready,'Privacy HTTP fixture starts');
    $http=static function(string $path,string $cookie='',array $post=[])use($address):array{$options=['ignore_errors'=>true,'follow_location'=>0,'timeout'=>20,'header'=>'Cookie: '.AuthService::SESSION_NAME.'='.$cookie."\r\n"];if($post){$options['method']='POST';$options['header'].="Content-Type: application/x-www-form-urlencoded\r\n";$options['content']=http_build_query($post);}$body=file_get_contents('http://'.$address.$path,false,stream_context_create(['http'=>$options]));return [$body===false?'':$body,$http_response_header??[]];};
    [$body,$headers]=$http('/writer.php',$webCookie);$check(str_contains(implode("\n",$headers),'Location: ./?view=consent'),'Stale policy gates signed-in writer route');
    [$body]=$http('/?view=privacy',$webCookie);$check(str_contains($body,'accounts@toinky.com')&&str_contains($body,'Privacy Policy'),'Legal notice remains accessible before consent');
    [$body]=$http('/?view=consent',$webCookie,['action'=>'accept_policies']+$consent());$check(str_contains($body,'Your session expired.')&&$scalar('SELECT privacy_version FROM users WHERE user_id=?',[$web])===null,'Consent POST requires CSRF');
    [$body,$headers]=$http('/?view=consent',$webCookie,['action'=>'accept_policies','csrf_token'=>$csrf]+$consent());$check(str_contains(implode("\n",$headers),'Location: ./')&&$scalar('SELECT privacy_version FROM users WHERE user_id=?',[$web])===PrivacyService::config()['privacy_version'],'Valid HTTP consent updates account versions');
    [$body]=$http('/profile.php',$webCookie,['action'=>'export_data','current_password'=>$password]);$check(!str_starts_with($body,'{"export_version"'),'HTTP export rejects missing CSRF');
    [$body,$headers]=$http('/profile.php',$webCookie,['action'=>'export_data','csrf_token'=>$csrf,'current_password'=>$password,'user_id'=>$super]);$decoded=json_decode($body,true,512,JSON_THROW_ON_ERROR);$check((int)$decoded['profile']['user_id']===$web&&str_contains(strtolower(implode("\n",$headers)),'cache-control: no-store'),'HTTP export ignores forged owner ID and prevents caching');
    [$body]=$http('/profile.php',$webCookie,['action'=>'delete_account','current_password'=>$password]);$check((bool)$scalar('SELECT 1 FROM users WHERE user_id=?',[$web]),'HTTP deletion rejects missing CSRF');
    [$body,$headers]=$http('/profile.php',$webCookie,['action'=>'delete_account','csrf_token'=>$csrf,'current_password'=>$password]);$check(!$scalar('SELECT 1 FROM users WHERE user_id=?',[$web])&&str_contains(implode("\n",$headers),'account_deleted=1'),'HTTP deletion erases current user and signs out');
    [$body,$headers]=$http('/?view=register');$guest='';foreach($headers as $header)if(preg_match('/^Set-Cookie: __Host-toinky=([^;]+)/i',$header,$m))$guest=$m[1];
    $check(str_contains($body,'name="birth_date"')&&!str_contains($body,'name="display_name"'),'Age screen precedes personal registration fields');
    preg_match('/name="csrf_token" value="([^"]+)"/',$body,$m);$guestCsrf=$m[1]??'';
    [$body,$headers]=$http('/?view=register',$guest,['action'=>'privacy_age_check','csrf_token'=>$guestCsrf,'birth_date'=>'1990-01-01']);
    [$body]=$http('/?view=register',$guest);$check(str_contains($body,'name="display_name"')&&str_contains($body,'name="accept_policies" value="1" required>'),'Eligible HTTP registration offers unticked consent');
}catch(Throwable $e){$failed=true;fwrite(STDERR,get_class($e).': '.$e->getMessage()."\n".$e->getTraceAsString()."\n");}
finally{
    if(is_resource($server)){proc_terminate($server);proc_close($server);}
    if($pdo->inTransaction())$pdo->rollBack();
    if($sid)$pdo->prepare('DELETE FROM simulations WHERE simulation_id=?')->execute([$sid]);
    if($plot)$pdo->prepare('DELETE FROM plots WHERE plot_id=?')->execute([$plot]);
    foreach($ai as $id)$pdo->prepare('DELETE FROM ai_reasoner_interactions WHERE ai_interaction_id=?')->execute([$id]);
    foreach($ledger as $id)$pdo->prepare('DELETE FROM host_occasion_ledger WHERE ledger_id=?')->execute([$id]);
    foreach($purchases as $id)$pdo->prepare('DELETE FROM host_purchases WHERE purchase_id=?')->execute([$id]);
    foreach($mail as $id)$pdo->prepare('DELETE FROM email_delivery_log WHERE email_delivery_id=?')->execute([$id]);
    foreach($users as $id){$resetPasswordLimit($id);$pdo->prepare('DELETE FROM users WHERE user_id=?')->execute([$id]);}
    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
    // Do not follow the symlink to the real schema directory.
    $walk=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($walk as $entry){if($entry->isLink()||$entry->isFile())unlink($entry->getPathname());else rmdir($entry->getPathname());}rmdir($fixture);
}
foreach($checks as $name)echo 'PASS '.$name."\n";echo count($checks).' privacy checks '.($failed?'before failure':'passed')."\n";exit($failed?1:0);
