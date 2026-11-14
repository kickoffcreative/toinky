<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
$source=dirname(__DIR__);$config=(string)getenv('GAME2_DATABASE_CONFIG');if($config===''||!is_file($config))throw new RuntimeException('Explicit isolated test database required.');
$cfg=require $config;$probe=new PDO($cfg['dsn'],$cfg['username'],$cfg['password']);if(!str_ends_with((string)$probe->query('SELECT DATABASE()')->fetchColumn(),'_test'))throw new RuntimeException('Use a database ending in _test.');unset($probe);
$fixture=sys_get_temp_dir().'/toinky-image-'.bin2hex(random_bytes(8));foreach(['','/app','/config','/public','/public/uploads','/public/uploads/profiles','/public/uploads/story-media','/public/uploads/parallax','/sessions'] as $part)mkdir($fixture.$part,0700);
foreach(glob($source.'/app/*.php')?:[] as $file)copy($file,$fixture.'/app/'.basename($file));copy($source.'/public/theme-media.php',$fixture.'/public/theme-media.php');copy($source.'/config/privacy.php',$fixture.'/config/privacy.php');symlink($source.'/database',$fixture.'/database');
putenv('GAME2_BASE_URL=http://127.0.0.1');ini_set('session.save_path',$fixture.'/sessions');require_once $fixture.'/app/bootstrap.php';require_once $fixture.'/app/AuthService.php';require_once $fixture.'/app/MigrationService.php';$pdo=db();MigrationService::run();AuthService::boot();
$id=0;$plot=0;$parallax=0;$server=null;$checks=0;$failed=false;$stamp=bin2hex(random_bytes(8));
$check=static function(bool $ok,string $label)use(&$checks):void{if(!$ok)throw new RuntimeException($label);$checks++;};
try{
 $pdo->prepare('INSERT INTO users(email,display_name,password_hash,email_verified_at_utc) VALUES(?,?,?,UTC_TIMESTAMP())')->execute(['image-'.$stamp.'@example.invalid','Image Fixture',password_hash($stamp,PASSWORD_DEFAULT)]);$id=(int)$pdo->lastInsertId();
 $_SESSION=['user_id'=>$id,'session_version'=>1];SessionService::register($id);$cookie=session_id();session_write_close();
 $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');$photo='public/uploads/profiles/'.$stamp.'.png';file_put_contents($fixture.'/'.$photo,$png);chmod($fixture.'/'.$photo,0640);$pdo->prepare('UPDATE users SET profile_image_path=? WHERE user_id=?')->execute([$photo,$id]);
 $art='public/uploads/story-media/'.$stamp.'.png';file_put_contents($fixture.'/'.$art,$png);chmod($fixture.'/'.$art,0640);
 $pdo->prepare('INSERT INTO plots(code,title,archetype,genre,setting_name,author_user_id,status) VALUES(?,"Image Fixture","Mystery","Mystery","Test shore",?,"active")')->execute(['IMAGE_'.$stamp,$id]);$plot=(int)$pdo->lastInsertId();
 $pdo->prepare('INSERT INTO plot_media_assets(plot_id,uploaded_by_user_id,asset_code,placement,title_text,file_path,mime_type,file_size_bytes,alt_text) VALUES(?,?,"FIXTURE","PRIMARY","Image Fixture",?,"image/png",?,"Fixture")')->execute([$plot,$id,$art,strlen($png)]);
 $layer='public/uploads/parallax/'.$stamp.'.png';file_put_contents($fixture.'/'.$layer,$png);$pdo->prepare('INSERT INTO site_parallax_layers(label_text,image_path,mime_type,updated_by_user_id) VALUES("Image Fixture",?,"image/png",?)')->execute([$layer,$id]);$parallax=(int)$pdo->lastInsertId();
 $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr);$address=stream_socket_get_name($socket,false);fclose($socket);
 $server=proc_open([PHP_BINARY,'-d','opcache.jit=0','-d','opcache.jit_buffer_size=0','-d','sendmail_path=/bin/false','-d','session.save_path='.$fixture.'/sessions','-S',$address,'-t',$fixture.'/public'],[0=>['pipe','r'],1=>['file',$fixture.'/server.log','a'],2=>['file',$fixture.'/server.log','a']],$pipes,$fixture);if(!is_resource($server))throw new RuntimeException('Image fixture did not start');fclose($pipes[0]);
 for($i=0;$i<40;$i++){$c=@stream_socket_client('tcp://'.$address,$errno,$errstr,0.1);if($c){fclose($c);break;}usleep(100000);}
 $http=static function(string $path,string $session='')use($address):array{$body=file_get_contents('http://'.$address.'/theme-media.php?path='.rawurlencode($path),false,stream_context_create(['http'=>['ignore_errors'=>true,'follow_location'=>0,'header'=>'Cookie: '.AuthService::SESSION_NAME.'='.$session."\r\n",'timeout'=>10]]));return [$body===false?'':$body,$http_response_header??[]];};
 [$body,$headers]=$http($photo,$cookie);$check($body===$png&&str_contains($headers[0]??'','200')&&str_contains(implode(' ',$headers),'Content-Type: image/png'),'Signed-in profile image streams with restrictive disk permissions');$check(str_contains(implode(' ',$headers),'Cache-Control: no-store'),'Profile image is not publicly cached');
 [, $headers]=$http($photo);$check(str_contains($headers[0]??'','404'),'Anonymous profile image access refused');
 foreach([$art,$layer] as $path){[$body,$headers]=$http($path);$check($body===$png&&str_contains($headers[0]??'','200'),'Referenced public legacy image still streams');}
 $pdo->prepare('UPDATE plots SET status="draft" WHERE plot_id=?')->execute([$plot]);[, $headers]=$http($art);$check(str_contains($headers[0]??'','404'),'Unpublished legacy artwork hidden from guests');[$body,$headers]=$http($art,$cookie);$check($body===$png&&str_contains(implode(' ',$headers),'Cache-Control: no-store'),'Draft author can preview artwork privately');
 $unreferenced='public/uploads/profiles/unreferenced.png';file_put_contents($fixture.'/'.$unreferenced,$png);[, $headers]=$http($unreferenced,$cookie);$check(str_contains($headers[0]??'','404'),'Unreferenced upload is not served');
 $poly='public/uploads/profiles/polyglot.php.jpg';$payload='<?php file_put_contents('.var_export($fixture.'/executed',true).',"bad"); ?>';file_put_contents($fixture.'/'.$poly,$png.$payload);$pdo->prepare('UPDATE users SET profile_image_path=? WHERE user_id=?')->execute([$poly,$id]);[$body,$headers]=$http($poly,$cookie);$check($body===$png.$payload&&!is_file($fixture.'/executed'),'PHP image endpoint streams bytes without executing embedded code');
 file_put_contents($fixture.'/'.$poly,$payload);[, $headers]=$http($poly,$cookie);$check(str_contains($headers[0]??'','404')&&!is_file($fixture.'/executed'),'Non-image disguised with image extension refused');
 $check(!preg_match('/Warning:|Fatal error:|Uncaught /',(string)file_get_contents($fixture.'/server.log')),'Image endpoint has no PHP warnings');
}catch(Throwable $e){$failed=true;fwrite(STDERR,'FAIL: image access '.$e->getMessage()."\n");}
finally{
 if(is_resource($server)){proc_terminate($server);proc_close($server);}if($parallax)$pdo->prepare('DELETE FROM site_parallax_layers WHERE parallax_layer_id=?')->execute([$parallax]);if($plot)$pdo->prepare('DELETE FROM plots WHERE plot_id=?')->execute([$plot]);if($id)$pdo->prepare('DELETE FROM users WHERE user_id=?')->execute([$id]);if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
 $walk=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($walk as $entry){if($entry->isLink()||$entry->isFile())unlink($entry->getPathname());else rmdir($entry->getPathname());}rmdir($fixture);
}
echo "$checks upload image access checks ".($failed?'before failure':'passed')."\n";exit($failed?1:0);
