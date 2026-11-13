<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
// Exercise the cache boundary without installing APCu or making an outbound request.
if(!function_exists('apcu_fetch')){
    define('TOINKY_DIAGNOSTIC_FAKE_APCU',true);
    function apcu_fetch(string $key,?bool &$success=null):mixed{$GLOBALS['diagnostic_cache_reads']=($GLOBALS['diagnostic_cache_reads']??0)+1;$success=isset($GLOBALS['diagnostic_cached']);return $GLOBALS['diagnostic_cached']??false;}
    function apcu_store(string $key,mixed $value,int $ttl=0):bool{return true;}
}
$root=dirname(__DIR__);require_once $root.'/app/bootstrap.php';require_once $root.'/app/AuthService.php';require_once $root.'/app/AiConnectionService.php';require_once $root.'/app/AiSettingsService.php';
$pdo=db();if(!str_ends_with((string)$pdo->query('SELECT DATABASE()')->fetchColumn(),'_test')||is_file($root.'/config/ai.php'))throw new RuntimeException('Use isolated _test database and checkout without private AI configuration.');
putenv('GAME2_AI_ENDPOINT=https://unapproved.invalid');putenv('GAME2_AI_PROVIDER=deterministic');
$dir=sys_get_temp_dir().'/toinky-ai-diagnostic-'.bin2hex(random_bytes(8));mkdir($dir,0700);ini_set('session.save_path',$dir);$users=[];$sessions=[];$server=null;$failed=false;$checks=0;
$check=static function(bool $ok,string $label)use(&$checks):void{if(!$ok)throw new RuntimeException($label);$checks++;};
try{
    $format=new ReflectionMethod(AiSettingsService::class,'diagnosticDetail');
    $cases=[
        [['diagnostic_code'=>'HTTP_ERROR','http_status'=>401,'basic_auth_configured'=>false],'no complete server Basic Authentication'],
        [['diagnostic_code'=>'HTTP_ERROR','http_status'=>401,'basic_auth_configured'=>true],'rejected the configured credentials'],
        [['diagnostic_code'=>'HTTP_ERROR','http_status'=>403],'denied access'],
        [['diagnostic_code'=>'HTTP_ERROR','http_status'=>404],'API path is unavailable'],
        [['diagnostic_code'=>'HTTP_ERROR','http_status'=>302],'redirect refused'],
        [['diagnostic_code'=>'HTTP_ERROR','http_status'=>503],'HTTP 503'],
        [['diagnostic_code'=>'HTTP_ERROR','http_status'=>999999],'No valid HTTP response'],
        [['diagnostic_code'=>'TRANSPORT_ERROR','curl_errno'=>6],'DNS lookup failed'],
        [['diagnostic_code'=>'TRANSPORT_ERROR','curl_errno'=>7],'could not connect'],
        [['diagnostic_code'=>'TRANSPORT_ERROR','curl_errno'=>28],'timed out'],
        [['diagnostic_code'=>'TRANSPORT_ERROR','curl_errno'=>60],'do not disable verification'],
        [['diagnostic_code'=>'TRANSPORT_ERROR','curl_errno'=>99999],'private server log'],
        [['diagnostic_code'=>'READY','online'=>true],'not inference'],
        [['diagnostic_code'=>'NOT_CONFIGURED'],'No AI endpoint'],
        [['diagnostic_code'=>'PROVIDER_DISABLED'],'provider is disabled'],
        [['diagnostic_code'=>'DESTINATION_REFUSED'],'public DNS validation'],
        [['diagnostic_code'=>'CURL_UNAVAILABLE'],'does not provide cURL'],
        [['diagnostic_code'=>'TRANSPORT_SETUP'],'secure transport options'],
        [['diagnostic_code'=>'EMPTY_RESPONSE'],'empty response'],
        [['diagnostic_code'=>'INVALID_RESPONSE'],'expected JSON'],
        [['diagnostic_code'=>'MODEL_UNAVAILABLE'],'not found'],
        [['diagnostic_code'=>'unknown-secret-value'],'fresh check'],
    ];
    foreach($cases as [$status,$expected]){
        $status+=['message'=>'private-backend-secret','error'=>'private-backend-secret','body'=>'<script>private-backend-secret</script>','password'=>'private-backend-secret'];
        $detail=$format->invoke(null,$status);$check(str_contains($detail,$expected),'Diagnostic class: '.$expected);$check(!str_contains($detail,'private-backend-secret')&&!str_contains($detail,'unknown-secret-value')&&!str_contains($detail,'<script>'),'Untrusted backend fields never rendered');
    }
    foreach([[0,0],[1,0],[1,1]] as $role){
        $pdo->prepare('INSERT INTO users(email,password_hash,display_name,is_admin,is_super_admin,email_verified_at_utc) VALUES(?,?,?,?,?,UTC_TIMESTAMP())')->execute(['ai-diag-'.bin2hex(random_bytes(8)).'@example.invalid','!','AI Diagnostic Fixture',$role[0],$role[1]]);$id=(int)$pdo->lastInsertId();$users[]=$id;
        session_id('');AuthService::boot();$_SESSION=['user_id'=>$id,'session_version'=>1,'csrf_token'=>bin2hex(random_bytes(24))];SessionService::register((int)$_SESSION['user_id'],true);$sessions[]=[session_id(),$_SESSION['csrf_token']];session_write_close();
        if(!$role[1]){$denied=false;try{AiSettingsService::diagnostics($id,true);}catch(RuntimeException $e){$denied=$e->getMessage()==='Super Admin access is required.';}$check($denied,'Service rejects non-Super-Admin diagnostics');}
    }
    if(defined('TOINKY_DIAGNOSTIC_FAKE_APCU')){
        $GLOBALS['diagnostic_cached']=['online'=>true,'message'=>'cached-fixture'];$reads=(int)($GLOBALS['diagnostic_cache_reads']??0);
        $check(AiConnectionService::status()['message']==='cached-fixture','Normal availability check may reuse cache');
        $status=AiConnectionService::status(true);$check(($GLOBALS['diagnostic_cache_reads']??0)===$reads+1,'Fresh test does not read cached availability');$check(($status['diagnostic_code']??'')==='DESTINATION_REFUSED','Fresh test validates the actual configured destination');unset($GLOBALS['diagnostic_cached']);
    }
    $result=AiSettingsService::diagnostics($users[2],true);$check($result['status']['diagnostic_code']==='DESTINATION_REFUSED','Super Admin receives approved safe category without outbound AI call');
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr);$address=stream_socket_get_name($socket,false);fclose($socket);
    $server=proc_open([PHP_BINARY,'-d','sendmail_path=/bin/false','-d','session.save_path='.$dir,'-S',$address,'-t',$root.'/public'],[0=>['pipe','r'],1=>['file',$dir.'/server.log','a'],2=>['file',$dir.'/server.log','a']],$pipes,$root);if(!is_resource($server))throw new RuntimeException('HTTP fixture failed');fclose($pipes[0]);
    $ready=false;for($i=0;$i<50;$i++){$probe=@stream_socket_client('tcp://'.$address,$errno,$errstr,0.1);if($probe){fclose($probe);$ready=true;break;}usleep(100000);}$check($ready,'Real HTTP fixture starts');
    $http=static function(string $path,array $session,?array $post=null)use($address):array{$options=['ignore_errors'=>true,'follow_location'=>0,'timeout'=>15,'header'=>'Cookie: '.AuthService::SESSION_NAME.'='.$session[0]."\r\n"];if($post!==null){$options['method']='POST';$options['header'].="Content-Type: application/x-www-form-urlencoded\r\n";$options['content']=http_build_query($post);}$body=file_get_contents('http://'.$address.$path,false,stream_context_create(['http'=>$options]));return [$body===false?'':$body,$http_response_header??[]];};
    foreach([0,1] as $role){[$body,$h]=$http('/ai-settings.php',$sessions[$role]);$check(str_contains($h[0]??'','403')&&!str_contains($body,'Diagnostic</'),'Detailed AI page denies player and ordinary admin');}
    [$body,$h]=$http('/ai-settings.php',$sessions[2]);$check(str_contains($h[0]??'','200')&&str_contains($body,'public DNS validation'),'Super Admin sees fixed diagnostic guidance');
    [$body]=$http('/ai-settings.php',$sessions[2],['action'=>'test_ai_connection','csrf_token'=>$sessions[2][1]]);$check(str_contains($body,'Connection test completed.')&&str_contains($body,'public DNS validation'),'CSRF-checked diagnostic POST returns fresh safe result');
    [$body]=$http('/ai-settings.php',$sessions[2],['action'=>'test_ai_connection','csrf_token'=>'wrong']);$check(!str_contains($body,'Connection test completed.'),'Invalid CSRF cannot authorize fresh test');
    [$body]=$http('/inky-status.php',$sessions[0]);$json=json_decode($body,true,512,JSON_THROW_ON_ERROR);$check(array_keys($json)===['ok','online','message']&&$json['message']==='Inky is resting right now.','Player API exposes only generic availability');
    foreach(['diagnostic_code','curl_errno','http_status','unapproved.invalid','basic_auth_configured'] as $key)$check(!str_contains($body,$key),'Player response excludes diagnostic detail: '.$key);
    echo 'PASS: AI diagnostics '.$checks." classification, cache, privilege and real HTTP checks; no outbound AI request.\n";
}catch(Throwable $e){fwrite(STDERR,'FAIL: AI diagnostics '.$e->getMessage()."\n");$failed=true;}
finally{
    if(is_resource($server)){proc_terminate($server);proc_close($server);}if(session_status()===PHP_SESSION_ACTIVE)session_write_close();foreach($users as $id)$pdo->prepare('DELETE FROM users WHERE user_id=?')->execute([$id]);foreach(glob($dir.'/*')?:[] as $file)if(is_file($file))unlink($file);rmdir($dir);
}
if($failed)exit(1);
