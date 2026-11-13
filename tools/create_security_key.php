<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/app/SecurityKeyService.php';
try{
    if((string)getenv('GAME2_SECURITY_KEY')!==''){
        if(SecurityKeyService::get()===null)throw new RuntimeException('The server-managed encryption key is invalid. Correct it privately; no replacement was generated.');
        echo "Server-managed encryption key is configured.\n";exit;
    }
    $path=SecurityKeyService::path();$directory=dirname($path);$default=trim((string)getenv('GAME2_SECURITY_KEY_FILE'))==='';
    if(!is_dir($directory)){
        if(!$default||!is_dir(dirname($directory))||!mkdir($directory,0700))throw new RuntimeException('Private encryption-key directory is unavailable. Configure a private path outside the application directory.');
    }
    if(is_link($directory)||!SecurityKeyService::outsideApplication($path)||is_link($path))throw new RuntimeException('Encryption-key storage must be a private regular file outside the application directory.');
    if(file_exists($path)){
        if(SecurityKeyService::get()===null)throw new RuntimeException('Existing encryption key or permissions require private operator review. It was not overwritten.');
        echo "Existing server encryption key preserved.\n";exit;
    }
    $oldMask=umask(0077);$file=false;
    try{$file=fopen($path,'x');if($file===false)throw new RuntimeException('Encryption key could not be created exclusively.');$body=base64_encode(random_bytes(32))."\n";
        if(fwrite($file,$body)!==strlen($body)||!fflush($file)||!chmod($path,0600))throw new RuntimeException('Private encryption-key creation did not complete. Review the file privately before retrying.');
    }finally{if(is_resource($file))fclose($file);umask($oldMask);}
    echo "Private server encryption key created; value was not printed.\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
