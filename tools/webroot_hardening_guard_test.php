<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
$root=dirname(__DIR__);require_once __DIR__.'/deploy_manifest.php';require_once $root.'/app/UploadPermissions.php';
$temp=sys_get_temp_dir().'/toinky-a11-'.bin2hex(random_bytes(8));mkdir($temp,0700);$checks=0;$failed=false;
$check=static function(bool $ok,string $label)use(&$checks):void{if(!$ok)throw new RuntimeException($label);$checks++;};
$run=static function(array $command):array{$p=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($p))throw new RuntimeException('Fixture process failed');fclose($pipes[0]);$out=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);return [proc_close($p),$out];};
try{
 $manifest=buildManifest($root);$check(!isset($manifest['install.php'])&&!isset($manifest['config/database.example.php']),'Installer and database examples excluded');
 foreach(array_keys($manifest) as $path)$check(!preg_match('~(?:_test\.php|_smoke\.php|\.example(?:\.php)?|docker-compose\.test\.yml)$~',$path)&&!str_starts_with($path,'tools/fixtures/'),'No test/example deployment: '.$path);
 foreach(['app','config','database','tools','public'] as $part)$check(is_file($root.'/'.$part.'/.htaccess')&&trim((string)file_get_contents($root.'/'.$part.'/.htaccess'))==='Require all denied','Private directory has independent deny rule: '.$part);
 $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/tools',FilesystemIterator::SKIP_DOTS));
 foreach($it as $file)if($file->isFile()&&$file->getExtension()==='php'){
  $source=(string)file_get_contents($file->getPathname());$guard=strpos($source,'PHP_SAPI');preg_match('/\b(?:require|include)(?:_once)?\s/',$source,$m,PREG_OFFSET_CAPTURE);$include=$m[0][1]??PHP_INT_MAX;
  $check($guard!==false&&$guard<$include,'CLI guard precedes includes: '.$file->getBasename());
 }
 foreach(['app/StorySimulationBatchService.php','app/FocusedOpportunitySimulationService.php','public/lab.php'] as $path)$check(!str_contains((string)file_get_contents($root.'/'.$path),'/tools/SimulationHarness.php'),'Web simulation uses app library: '.$path);
 $upload=$root.'/public/uploads/.htaccess';$rules=(string)file_get_contents($upload);$check(str_contains($rules,'SetHandler none')&&str_contains($rules,'RemoveHandler')&&str_contains($rules,'Require all denied')&&str_contains($rules,'jpe?g|png|webp'),'Upload policy disables handlers and limits image extensions');
 foreach(['app','config','database','tools','public','public/uploads','assets'] as $part)mkdir($temp.'/'.$part,0777,true);
 file_put_contents($temp.'/config/database.php','private fixture');chmod($temp.'/config/database.php',0600);
 file_put_contents($temp.'/public/uploads/keep.png','user content');chmod($temp.'/public/uploads/keep.png',0666);
 file_put_contents($temp.'/assets/keep.png','public asset');chmod($temp.'/assets/keep.png',0644);
 [$code]=$run([PHP_BINARY,__DIR__.'/deploy_runtime_tools.php',$temp]);$check($code===0&&is_file($temp.'/tools/migrate.php')&&!is_file($temp.'/tools/webroot_hardening_guard_test.php'),'Runtime copier includes commands but omits tests');
 foreach($manifest as $path=>$hash){$origin=str_starts_with($path,'assets/')?'public/'.$path:$path;$directory=dirname($temp.'/'.$path);if(!is_dir($directory))mkdir($directory,0750,true);if(!copy($root.'/'.$origin,$temp.'/'.$path))throw new RuntimeException('Staged runtime copy failed.');}
 file_put_contents($temp.'/install.php','old installer');file_put_contents($temp.'/tools/stale_test.php','old test');file_put_contents($temp.'/config/database.example.php','old example');file_put_contents($temp.'/docker-compose.test.yml','old test compose');
 $result=runManifest($root,$temp,true);$check(!is_file($temp.'/install.php')&&!is_file($temp.'/tools/stale_test.php')&&!is_file($temp.'/config/database.example.php')&&!is_file($temp.'/docker-compose.test.yml'),'Manifest removes stale installer/tests/examples');
 $check($result['missing']===[]&&$result['mismatched']===[],'Complete staged runtime manifest verifies');
 $check(file_get_contents($temp.'/config/database.php')==='private fixture'&&file_get_contents($temp.'/public/uploads/keep.png')==='user content','Cleanup preserves private config and uploaded content');
 file_put_contents($temp.'/assets/keep.png','public asset');chmod($temp.'/assets/keep.png',0644);
 [$code]=$run([PHP_BINARY,__DIR__.'/harden_storage.php',$temp]);clearstatcache();$check($code===0&&(fileperms($temp.'/public/uploads/keep.png')&0777)===0666,'Permission dry run changes nothing');
 [$code]=$run([PHP_BINARY,__DIR__.'/harden_storage.php',$temp,'--apply']);clearstatcache();$check($code===0&&(fileperms($temp.'/public/uploads/keep.png')&0777)===0640,'Permission apply caps uploaded files');
 $check((fileperms($temp.'/config/database.php')&0777)===0600&&(fileperms($temp.'/assets/keep.png')&0777)===0644,'Hardening preserves stricter credentials and public assets');
 UploadPermissions::directory($temp.'/new-upload');file_put_contents($temp.'/new-upload/a.png','fixture');chmod($temp.'/new-upload/a.png',0666);UploadPermissions::file($temp.'/new-upload/a.png');clearstatcache();$check((fileperms($temp.'/new-upload')&0777)===0750&&(fileperms($temp.'/new-upload/a.png')&0777)===0640,'New upload permissions are bounded');
 symlink($temp.'/assets',$temp.'/public/linked');[$code]=$run([PHP_BINARY,__DIR__.'/harden_storage.php',$temp,'--apply']);$check($code!==0,'Hardener refuses linked storage');unlink($temp.'/public/linked');
}catch(Throwable $e){$failed=true;fwrite(STDERR,'FAIL: A-11 '.$e->getMessage()."\n");}
finally{$walk=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($walk as $entry){if($entry->isLink()||$entry->isFile())unlink($entry->getPathname());else rmdir($entry->getPathname());}rmdir($temp);}
echo "$checks web-root hardening checks ".($failed?'before failure':'passed')."\n";exit($failed?1:0);
