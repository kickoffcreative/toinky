<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$source=dirname(__DIR__);$config=(string)getenv('GAME2_DATABASE_CONFIG');
if($config===''||!is_file($config))throw new RuntimeException('Explicit isolated test database configuration required.');
$cfg=require $config;$probe=new PDO($cfg['dsn'],$cfg['username'],$cfg['password']);if(!str_ends_with((string)$probe->query('SELECT DATABASE()')->fetchColumn(),'_test'))throw new RuntimeException('Use a database ending in _test.');unset($probe);
$fixture=sys_get_temp_dir().'/toinky-a10-'.bin2hex(random_bytes(8));foreach(['','/app','/public','/config','/sessions'] as $part)mkdir($fixture.$part,0700);
foreach(glob($source.'/app/*.php')?:[] as $file)copy($file,$fixture.'/app/'.basename($file));copy($source.'/public/profile.php',$fixture.'/public/profile.php');copy($source.'/config/privacy.php',$fixture.'/config/privacy.php');symlink($source.'/database',$fixture.'/database');
$siteConfig=$fixture.'/config/app.php';$savedBase=getenv('GAME2_BASE_URL');$savedConfig=getenv('GAME2_APP_CONFIG');putenv('GAME2_BASE_URL=');putenv('GAME2_APP_CONFIG='.$siteConfig);
file_put_contents($siteConfig,'<?php return '.var_export(['base_url'=>'https://canonical.example.test/story','allowed_hosts'=>['canonical.example.test','alias.example.test']],true).';');
// Capture the actual CommerceService request without any network or Stripe key.
file_put_contents($fixture.'/app/StripeService.php', <<<'PHP'
<?php
final class StripeService {
 public static array $request=[];
 public static function configured():bool{return true;}
 public static function mode():string{return 'TEST';}
 public static function publishableKey():string{return '';}
 public static function secretKey():string{return 'fixture';}
 public static function webhookSecret():string{return '';}
 public static function createCheckoutSession(array $request,string $key):array{self::$request=$request;return ['id'=>'cs_fixture_'.bin2hex(random_bytes(8)),'url'=>'https://checkout.stripe.com/fixture'];}
}
PHP);
file_put_contents($fixture.'/public/probe.php','<?php require dirname(__DIR__)."/app/bootstrap.php"; require dirname(__DIR__)."/app/AuthService.php"; echo AuthService::baseUrl();');
require_once $fixture.'/app/bootstrap.php';require_once $fixture.'/app/AuthService.php';require_once $fixture.'/app/CommerceService.php';require_once $fixture.'/app/MigrationService.php';$pdo=db();MigrationService::run();
$checks=[];$failed=false;$server=null;$id=0;$savedEnabled=null;$hadEnabled=false;$capturedEnabled=false;
$check=static function(bool $ok,string $name)use(&$checks):void{if(!$ok)throw new RuntimeException($name);$checks[]=$name;};
$reject=static function(callable $fn,string $name)use($check):void{try{$fn();}catch(RuntimeException $e){$check(true,$name);return;}throw new RuntimeException($name);};
$start=static function()use(&$server,$fixture,$check):string{
 $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr);$address=stream_socket_get_name($socket,false);fclose($socket);
 $server=proc_open([PHP_BINARY,'-d','opcache.jit=0','-d','opcache.jit_buffer_size=0','-d','sendmail_path=/bin/false','-d','session.save_path='.$fixture.'/sessions','-S',$address,'-t',$fixture.'/public'],[0=>['pipe','r'],1=>['file',$fixture.'/server.log','a'],2=>['file',$fixture.'/server.log','a']],$pipes,$fixture);if(!is_resource($server))throw new RuntimeException('Could not start local host fixture');fclose($pipes[0]);
 for($i=0;$i<40;$i++){$c=@stream_socket_client('tcp://'.$address,$errno,$errstr,0.1);if($c){fclose($c);return $address;}usleep(100000);}throw new RuntimeException('Host fixture did not start');
};
$http=static function(string $address,string $host,string $path='/probe.php',string $extra=''):array{$body=file_get_contents('http://'.$address.$path,false,stream_context_create(['http'=>['ignore_errors'=>true,'follow_location'=>0,'timeout'=>15,'header'=>'Host: '.$host."\r\n".$extra]]));return [$body===false?'':$body,$http_response_header??[]];};
try{
 $_SERVER['HTTP_HOST']='attacker.example.invalid';$_SERVER['SERVER_NAME']='attacker.example.invalid';$_SERVER['HTTPS']='off';
 $check(AuthService::baseUrl()==='https://canonical.example.test/story','Account URL ignores request origin');
 $mail=EmailTemplateService::compose('PASSWORD_RESET',['action_url'=>AuthService::baseUrl().'/?view=reset']);
 $check(str_contains($mail['html'],'https://canonical.example.test/story/assets/toinky-logo.png')&&!str_contains(json_encode($mail),'attacker.example.invalid'),'Email links and logo use canonical address');
 $q=$pdo->query('SELECT setting_value FROM platform_settings WHERE setting_key="COMMERCE_ENABLED"');$savedEnabled=$q->fetchColumn();$hadEnabled=$savedEnabled!==false;$capturedEnabled=true;
 $pdo->exec('INSERT INTO platform_settings(setting_key,setting_value) VALUES("COMMERCE_ENABLED","1") ON DUPLICATE KEY UPDATE setting_value="1"');
 $pdo->prepare('INSERT INTO users(email,display_name,password_hash,email_verified_at_utc) VALUES(?,?,?,UTC_TIMESTAMP())')->execute(['a10-'.bin2hex(random_bytes(8)).'@example.invalid','URL Fixture',password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT)]);$id=(int)$pdo->lastInsertId();
 CommerceService::startPurchase($id,'trio');$check(str_starts_with(StripeService::$request['success_url'],'https://canonical.example.test/story/?view=buy&result=success&purchase=')&&StripeService::$request['cancel_url']==='https://canonical.example.test/story/?view=buy&result=cancelled','Actual checkout request uses canonical return URLs');
 $count=(int)$pdo->query('SELECT COUNT(*) FROM host_purchases WHERE user_id='.$id)->fetchColumn();putenv('GAME2_BASE_URL=http://unsafe.example.test');
 $reject(static fn()=>CommerceService::startPurchase($id,'trio'),'Invalid site URL prevents checkout');$check((int)$pdo->query('SELECT COUNT(*) FROM host_purchases WHERE user_id='.$id)->fetchColumn()===$count,'Invalid URL creates no pending purchase');
 foreach(['http://unsafe.example.test','https://user:password@example.test','https://example.test/?query=1','https://example.test/#part','https://example.test:0','https://example.test/path/../other','https://example.test/path%0d%0a','https://example.test\\@attacker.test',"https://example.test\r\nInjected: value"] as $bad){putenv('GAME2_BASE_URL='.$bad);$reject(static fn()=>AppUrlService::baseUrl(),'Invalid configured URL refused: '.count($checks));}
 putenv('GAME2_BASE_URL=https://override.example.test/base/');$check(AppUrlService::baseUrl()==='https://override.example.test/base','Explicit environment URL overrides file');
 putenv('GAME2_BASE_URL=');putenv('GAME2_APP_CONFIG='.$fixture.'/missing.php');$reject(static fn()=>AppUrlService::baseUrl(),'Missing config has no Host fallback');putenv('GAME2_APP_CONFIG='.$siteConfig);
 $address=$start();
 foreach(['canonical.example.test','CANONICAL.EXAMPLE.TEST','alias.example.test','canonical.example.test:443'] as $host){[$body,$headers]=$http($address,$host);$check(str_contains($headers[0]??'','200')&&$body==='https://canonical.example.test/story','Configured host accepted with canonical result: '.$host);}
 foreach(['attacker.example.invalid','canonical.example.test.attacker.invalid','canonical.example.test.','canonical.example.test:9999','canonical.example.test,attacker.invalid','user@canonical.example.test'] as $host){[$body,$headers]=$http($address,$host,'/profile.php');$check(str_contains($headers[0]??'','400')&&$body==='Invalid request host.','Forged Host refused before protected page: '.$host);}
 [$body,$headers]=$http($address,'attacker.example.invalid','/probe.php',"X-Forwarded-Host: canonical.example.test\r\nForwarded: host=canonical.example.test;proto=https\r\n");$check(str_contains($headers[0]??'','400'),'Forwarded headers cannot authorize an unknown Host');
 [$body,$headers]=$http($address,'canonical.example.test','/probe.php',"X-Forwarded-Host: attacker.example.invalid\r\nX-Forwarded-Proto: http\r\n");$check(str_contains($headers[0]??'','200')&&$body==='https://canonical.example.test/story','Forwarded origin cannot poison links on an accepted host');
 file_put_contents($siteConfig,'<?php return [];');[$body,$headers]=$http($address,'canonical.example.test');$check(str_contains($headers[0]??'','503')&&$body==='The site is temporarily unavailable.','Missing URL fails closed over HTTP');
 $log=(string)file_get_contents($fixture.'/server.log');$check(str_contains($log,'Rejected request host')&&str_contains($log,'Site address configuration unavailable')&&!str_contains($log,'attacker.example.invalid'),'Host/config failures logged without raw hostile header');
 $check(preg_match('/\[cid=[a-f0-9]{16}\].*Rejected request host/',$log)===1&&!preg_match('/Warning:|Fatal error:|Uncaught /',$log),'Early rejection has request ID and no PHP warnings');
 foreach(glob($source.'/app/*.php')?:[] as $file)if(basename($file)!=='AppUrlService.php')$check(!str_contains((string)file_get_contents($file),'HTTP_HOST'),'No request Host used outside guard: '.basename($file));
}catch(Throwable $e){$failed=true;fwrite(STDERR,'FAIL: A-10 '.$e->getMessage()."\n".$e->getTraceAsString()."\n");}
finally{
 if(is_resource($server)){proc_terminate($server);proc_close($server);}if($id){$pdo->prepare('DELETE FROM host_purchases WHERE user_id=?')->execute([$id]);$pdo->prepare('DELETE FROM users WHERE user_id=?')->execute([$id]);}
 if($capturedEnabled){if($hadEnabled)$pdo->prepare('UPDATE platform_settings SET setting_value=? WHERE setting_key="COMMERCE_ENABLED"')->execute([$savedEnabled]);else $pdo->exec('DELETE FROM platform_settings WHERE setting_key="COMMERCE_ENABLED"');}
 putenv($savedBase===false?'GAME2_BASE_URL':'GAME2_BASE_URL='.$savedBase);putenv($savedConfig===false?'GAME2_APP_CONFIG':'GAME2_APP_CONFIG='.$savedConfig);
 $walk=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($walk as $entry){if($entry->isLink()||$entry->isFile())unlink($entry->getPathname());else rmdir($entry->getPathname());}rmdir($fixture);
}
echo count($checks).' site-address checks '.($failed?'before failure':'passed')."\n";exit($failed?1:0);
