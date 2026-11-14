<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
ini_set('display_errors','0');ini_set('zend.exception_ignore_args','1');require_once __DIR__.'/../app/BackupStreamService.php';
try{
    $path=(string)($argv[1]??'');if($path===''||!is_dir(dirname($path))||is_link($path)||!SecurityKeyService::outsideApplication($path)||(DIRECTORY_SEPARATOR==='/'&&(fileperms(dirname($path))&0022)!==0))throw new RuntimeException('Choose an existing private directory outside the application.');
    if(is_file($path)){BackupStreamService::readKeyFile($path);echo "Existing backup key is valid; unchanged.\n";exit;}
    if(!function_exists('sodium_crypto_secretstream_xchacha20poly1305_keygen'))throw new RuntimeException('Sodium unavailable.');
    $old=umask(0077);try{$file=fopen($path,'x');}finally{umask($old);}if(!$file)throw new RuntimeException('Backup key file could not be created.');
    try{$key=sodium_crypto_secretstream_xchacha20poly1305_keygen();$encoded=base64_encode($key)."\n";if(!chmod($path,0600)||fwrite($file,$encoded)!==strlen($encoded)||!fflush($file))throw new RuntimeException('Backup key file could not be written.');sodium_memzero($key);}finally{fclose($file);}
    echo "Independent backup key created. Keep a separately protected off-server copy. No key printed.\n";
}catch(Throwable $e){fwrite(STDERR,"Backup key setup failed. Choose a private external directory and verify its permissions. No key printed.\n");exit(1);}
