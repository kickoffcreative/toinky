<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once __DIR__.'/../app/BackupStreamService.php';$checks=0;
$check=static function(bool $ok,string $label)use(&$checks):void{if(!$ok)throw new RuntimeException($label);$checks++;};
$stream=static function(string $value){$f=fopen('php://temp','w+b');fwrite($f,$value);rewind($f);return $f;};
$key=sodium_crypto_secretstream_xchacha20poly1305_keygen();$last='';
foreach(['','single record',random_bytes(2097251)] as $plain){$in=$stream($plain);$out=$stream('');BackupStreamService::encrypt($in,$out,$key);rewind($out);$cipher=stream_get_contents($out);$last=$cipher;$restored=$stream('');rewind($out);BackupStreamService::decrypt($out,$restored,$key);rewind($restored);$check(stream_get_contents($restored)===$plain,'Authenticated round trip preserves complete stream');fclose($in);fclose($out);fclose($restored);}
$changed=$last;$changed[80]=chr(ord($changed[80])^1);
foreach([[$changed,$key],[substr($last,0,-1),$key],[$last.'extra',$key],[$last,random_bytes(32)],[substr($last,0,40),$key]] as [$cipher,$testKey]){$in=$stream($cipher);$out=$stream('');$rejected=false;try{BackupStreamService::decrypt($in,$out,$testKey);}catch(Throwable $e){$rejected=true;}rewind($out);$check($rejected&&stream_get_contents($out)==='','Tamper, truncation, wrong key or trailing data releases no plaintext');fclose($in);fclose($out);}
$directory=sys_get_temp_dir().'/toinky-backup-key-'.bin2hex(random_bytes(8));mkdir($directory,0700);$path=$directory.'/backup.key';
$run=static function(array $command):array{$p=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($p))throw new RuntimeException('Key fixture process failed');fclose($pipes[0]);$out=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);return [proc_close($p),$out];};
try{
 [$code,$out]=$run([PHP_BINARY,'-d','opcache.jit=0','-d','opcache.jit_buffer_size=0',__DIR__.'/create_backup_key.php',$path]);$created=BackupStreamService::readKeyFile($path);$check($code===0&&strlen($created)===32&&!str_contains($out,base64_encode($created))&&(fileperms($path)&0777)===0600,'Independent owner-only backup key created without disclosure');
 $hash=hash_file('sha256',$path);[$code]=$run([PHP_BINARY,'-d','opcache.jit=0','-d','opcache.jit_buffer_size=0',__DIR__.'/create_backup_key.php',$path]);$check($code===0&&hash_file('sha256',$path)===$hash,'Existing backup key is never overwritten');
 chmod($path,0644);$rejected=false;try{BackupStreamService::readKeyFile($path);}catch(Throwable $e){$rejected=true;}$check($rejected,'Readable backup key refused');chmod($path,0600);
 chmod($directory,0777);$rejected=false;try{BackupStreamService::readKeyFile($path);}catch(Throwable $e){$rejected=true;}$check($rejected,'Writable backup key parent refused');chmod($directory,0700);
 symlink($path,$directory.'/linked.key');$rejected=false;try{BackupStreamService::readKeyFile($directory.'/linked.key');}catch(Throwable $e){$rejected=true;}$check($rejected,'Linked backup key refused');unlink($directory.'/linked.key');
}finally{if(is_file($path))unlink($path);rmdir($directory);}
echo "$checks backup-stream checks passed\n";
