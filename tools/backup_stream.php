<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
ini_set('display_errors','0');ini_set('zend.exception_ignore_args','1');require_once __DIR__.'/../app/BackupStreamService.php';
try{
    $mode=(string)($argv[1]??'');$key=BackupStreamService::readKeyFile((string)($argv[2]??''));
    if($mode==='encrypt')BackupStreamService::encrypt(STDIN,STDOUT,$key);elseif($mode==='decrypt')BackupStreamService::decrypt(STDIN,STDOUT,$key);else throw new RuntimeException('Choose encrypt or decrypt.');
    sodium_memzero($key);
}catch(Throwable $e){fwrite(STDERR,"Backup operation failed. No unauthenticated restore data was released. Check mode, key, permissions and input privately.\n");exit(1);}
