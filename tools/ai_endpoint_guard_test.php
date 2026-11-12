<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);require_once $root.'/app/bootstrap.php';require_once $root.'/app/MigrationService.php';require_once $root.'/app/AuthService.php';
$pdo=db();if(!str_ends_with((string)$pdo->query('SELECT DATABASE()')->fetchColumn(),'_test'))throw new RuntimeException('Use an isolated _test database.');
if(is_file($root.'/config/ai.php'))throw new RuntimeException('Use an isolated checkout without server-owned AI configuration.');
MigrationService::run();$checks=0;$ids=[];$failed=false;$stamp=bin2hex(random_bytes(8));$originalEndpoint=getenv('GAME2_AI_ENDPOINT');$originalSettings=$pdo->query('SELECT setting_key,setting_value FROM ai_reasoner_settings WHERE setting_key IN ("provider","ollama_endpoint")')->fetchAll();
$log=tempnam(sys_get_temp_dir(),'toinky-ai-policy-');$oldLog=ini_get('error_log');ini_set('error_log',$log);
$check=static function(bool $ok,string $label)use(&$checks):void{if(!$ok)throw new RuntimeException($label);$checks++;};
$deny=static function(callable $fn,string $label)use($check):void{$denied=false;try{$fn();}catch(RuntimeException $e){$denied=true;}$check($denied,$label);};
try{
    $check(AiEndpointPolicy::allowedHosts()===['toinky.com'],'Owner-approved hostname is deployed policy');
    foreach(['http://toinky.com','https://127.0.0.1','https://169.254.169.254','https://[::1]','https://localhost','https://not-approved.invalid','https://toinky.com.evil.invalid','https://toinky.com@evil.invalid','https://evil.invalid@toinky.com','https://toinky.com:8443','https://toinky.com?token=forbidden','https://toinky.com#fragment',"https://toinky.com\\@evil.invalid",'https://2130706433','file:///etc/passwd'] as $url)$deny(static fn()=>AiEndpointPolicy::validateUrl($url),'Unsafe URL rejected before DNS or credentials');
    $target=AiEndpointPolicy::validateUrl('https://TOINKY.COM/ai/');$check($target['url']==='https://toinky.com/ai','Approved URL canonicalized');
    $deny(static fn()=>AiEndpointPolicy::validateUrl('https://toinky.com',[]),'Empty allowlist fails closed');
    foreach(['0.0.0.0','10.2.3.4','100.64.0.1','127.0.0.1','169.254.169.254','172.16.0.1','192.168.1.1','192.0.2.1','198.18.0.1','198.51.100.1','203.0.113.1','224.0.0.1','255.255.255.255','::1','::ffff:127.0.0.1','64:ff9b::7f00:1','fc00::1','fe80::1','ff02::1','2001:db8::1','2002:7f00:1::','3fff::1','not-an-ip'] as $ip)$deny(static fn()=>AiEndpointPolicy::publicAddresses([$ip]),'Private, reserved or non-global DNS result refused');
    $deny(static fn()=>AiEndpointPolicy::publicAddresses([]),'Unresolved hostname refused');
    $deny(static fn()=>AiEndpointPolicy::publicAddresses(['8.8.8.8','127.0.0.1']),'Mixed public/private DNS answers refused');
    $public=['8.8.8.8','2606:4700:4700::1111'];$check(AiEndpointPolicy::publicAddresses($public)===$public,'Public IPv4 and IPv6 supported');
    if(!extension_loaded('curl'))throw new RuntimeException('cURL extension required for transport guard.');
    $target['addresses']=$public;$options=AiEndpointPolicy::curlOptions($target);
    $check($options[CURLOPT_FOLLOWLOCATION]===false&&$options[CURLOPT_MAXREDIRS]===0,'Redirect following disabled');
    $check($options[CURLOPT_SSL_VERIFYPEER]===true&&$options[CURLOPT_SSL_VERIFYHOST]===2,'TLS peer and hostname verification retained');
    $check($options[CURLOPT_PROXY]==='','Environment proxy cannot bypass address validation');
    $check($options[CURLOPT_RESOLVE]===['toinky.com:443:8.8.8.8,[2606:4700:4700::1111]'],'cURL pins validated IPv4 and IPv6 without another lookup');
    $protocolOption=defined('CURLOPT_PROTOCOLS_STR')?constant('CURLOPT_PROTOCOLS_STR'):CURLOPT_PROTOCOLS;
    $check($options[$protocolOption]===(defined('CURLOPT_PROTOCOLS_STR')?'https':CURLPROTO_HTTPS),'Only HTTPS protocol permitted');
    // Construct a cURL handle but never execute or send headers. Unsupported options fail this guard.
    $handle=curl_init($target['url']);$check(curl_setopt_array($handle,$options),'Actual cURL accepts all transport options');curl_close($handle);
    foreach([[1,0],[1,1]] as $i=>$role){$pdo->prepare('INSERT INTO users(email,password_hash,display_name,is_admin,is_super_admin,email_verified_at_utc) VALUES(?,?,?, ?,?,UTC_TIMESTAMP())')->execute(['ai-policy-'.$stamp.'-'.$i.'@example.invalid','!','AI Policy Fixture',$role[0],$role[1]]);$ids[]=(int)$pdo->lastInsertId();}
    $deny(static fn()=>AiSettingsService::save($ids[0],['ai_endpoint'=>'https://toinky.com/ai']),'Ordinary admin cannot save AI settings');
    foreach(['http://toinky.com','https://127.0.0.1','https://169.254.169.254','https://unapproved.invalid'] as $url)$deny(static fn()=>AiSettingsService::save($ids[1],['ai_endpoint'=>$url]),'Super Admin saving unsafe endpoint is refused');
    // No configured endpoint must never fall back to a public tunnel or localhost.
    putenv('GAME2_AI_ENDPOINT=');$pdo->exec('DELETE FROM ai_reasoner_settings WHERE setting_key IN ("provider","ollama_endpoint")');$pdo->prepare('INSERT INTO ai_reasoner_settings(setting_key,setting_value) VALUES("provider","ollama")')->execute();
    $check(AiSettingsService::settings()['endpoint']==='','No built-in endpoint');
    $check(AiConnectionService::status()['message']==='AI service is not configured.','Unconfigured status is fixed without network call');
    $before=(int)$pdo->query('SELECT COUNT(*) FROM ai_reasoner_interactions')->fetchColumn();
    $deny(static fn()=>AiConnectionService::completeJson('GUARD','Synthetic system','Synthetic prompt'),'Unconfigured completion fails closed');
    putenv('GAME2_AI_ENDPOINT=https://unapproved.invalid');
    $status=AiConnectionService::status();$check(!$status['online']&&!str_contains($status['message'],'unapproved.invalid'),'Unapproved destination yields fixed public status');
    $deny(static fn()=>AiConnectionService::completeJson('GUARD','Synthetic system','Synthetic prompt'),'Writer completion refuses unapproved destination');
    $deny(static fn()=>(new OllamaStoryReasoner())->selectOption([], [['option_code'=>'ONLY']]),'Game reasoner refuses same unapproved destination');
    $check((int)$pdo->query('SELECT COUNT(*) FROM ai_reasoner_interactions')->fetchColumn()===$before,'Refusal occurs before prompt logging or transport');
    putenv('GAME2_AI_ENDPOINT=https://toinky.com/owner-api');$check(AiSettingsService::settings()['endpoint']==='https://toinky.com/owner-api','Server endpoint takes precedence over database');
    foreach(['app/AiConnectionService.php','app/OllamaStoryReasoner.php'] as $file){$source=file_get_contents($root.'/'.$file);$check(str_contains($source,'AiEndpointPolicy::resolve(')&&str_contains($source,'AiEndpointPolicy::curlOptions('),'Every AI transport uses shared resolution and pinning');$check(!preg_match('/CURLOPT_FOLLOWLOCATION\s*=>\s*true/',$source),'No transport enables redirects');$check(str_contains($source,'if(!curl_setopt_array('),'Transport setup failure is refused before execution');}
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app',FilesystemIterator::SKIP_DOTS));foreach($iterator as $file)if($file->getExtension()==='php')$check(stripos(file_get_contents($file->getPathname()),'ngrok')===false,'No tunnel default or token name remains in app');
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/public',FilesystemIterator::SKIP_DOTS));foreach($iterator as $file)if($file->getExtension()==='php')$check(stripos(file_get_contents($file->getPathname()),'ngrok')===false,'No tunnel default remains in public PHP');
    $check(str_contains(file_get_contents($root.'/public/ai-settings.php'),'AuthService::requireSuperAdmin()'),'AI settings screen requires Super Admin');
    $check(str_contains(file_get_contents($root.'/.cpanel.yml'),'config/ai-policy.php')&&str_contains(file_get_contents($root.'/tools/deploy_manifest.php'),"'config/ai-policy.php'"),'Deployment copies and verifies host policy');
    echo 'PASS: A-04 '.$checks." endpoint, address, transport and privilege checks; no outbound AI request made.\n";
}catch(Throwable $e){fwrite(STDERR,'FAIL: A-04 '.$e->getMessage()."\n");$failed=true;}
finally{
    foreach($ids as $id)$pdo->prepare('DELETE FROM users WHERE user_id=?')->execute([$id]);
    $pdo->exec('DELETE FROM ai_reasoner_settings WHERE setting_key IN ("provider","ollama_endpoint")');foreach($originalSettings as $row)$pdo->prepare('INSERT INTO ai_reasoner_settings(setting_key,setting_value) VALUES(?,?)')->execute([$row['setting_key'],$row['setting_value']]);
    putenv($originalEndpoint===false?'GAME2_AI_ENDPOINT':'GAME2_AI_ENDPOINT='.$originalEndpoint);ini_set('error_log',(string)$oldLog);if(is_file($log))unlink($log);
}
if($failed)exit(1);
