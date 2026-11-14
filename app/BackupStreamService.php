<?php
require_once __DIR__.'/SecurityKeyService.php';
final class BackupStreamService
{
    private const MAGIC="TOINKY_BACKUP_V1\n";
    private const CHUNK=65536;
    public static function readKeyFile(string $path): string
    {
        clearstatcache(true,$path);
        if(is_link($path)||!is_file($path)||!is_readable($path)||!SecurityKeyService::outsideApplication($path)||(DIRECTORY_SEPARATOR==='/'&&((fileperms($path)&0077)!==0||(fileperms(dirname($path))&0022)!==0)))throw new RuntimeException('Backup key file unavailable or unsafe.');
        $key=base64_decode(trim((string)file_get_contents($path,false,null,0,256)),true);
        if(!is_string($key)||strlen($key)!==32)throw new RuntimeException('Invalid backup key.');return $key;
    }
    private static function available(string $key): void
    {
        if(!function_exists('sodium_crypto_secretstream_xchacha20poly1305_init_push')||strlen($key)!==32)throw new RuntimeException('Backup encryption requires Sodium and a valid independent key.');
    }
    public static function encrypt($input,$output,#[\SensitiveParameter] string $key): void
    {
        self::available($key);[$state,$header]=sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
        try{
            self::write($output,self::MAGIC.$header);
            while(!feof($input)){$plain=fread($input,self::CHUNK);if($plain===false)throw new RuntimeException('Backup input failed.');if($plain===''){if(feof($input))break;throw new RuntimeException('Backup input stalled.');}$cipher=sodium_crypto_secretstream_xchacha20poly1305_push($state,$plain);self::write($output,pack('N',strlen($cipher)).$cipher);}
            $final=sodium_crypto_secretstream_xchacha20poly1305_push($state,'','',SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);self::write($output,pack('N',strlen($final)).$final);
        }finally{sodium_memzero($state);}
    }
    public static function decrypt($input,$output,#[\SensitiveParameter] string $key): void
    {
        self::available($key);if(self::read($input,strlen(self::MAGIC))!==self::MAGIC)throw new RuntimeException('Invalid backup format.');
        $state=sodium_crypto_secretstream_xchacha20poly1305_init_pull(self::read($input,SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES),$key);
        $old=umask(0077);try{$staging=tmpfile();}finally{umask($old);}if(!$staging)throw new RuntimeException('Private restore staging unavailable.');
        try{
            if(DIRECTORY_SEPARATOR==='/'&&((fstat($staging)['mode']??0)&0077)!==0)throw new RuntimeException('Unsafe restore staging permissions.');
            $final=false;
            while(!$final){$length=unpack('Nsize',self::read($input,4))['size'];if($length<SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES||$length>self::CHUNK+SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES)throw new RuntimeException('Invalid backup frame.');
                $result=sodium_crypto_secretstream_xchacha20poly1305_pull($state,self::read($input,$length));if($result===false)throw new RuntimeException('Backup authentication failed.');[$plain,$tag]=$result;self::write($staging,$plain);$final=$tag===SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
            }
            if(fread($input,1)!=='')throw new RuntimeException('Unexpected data after final backup frame.');
            if(!fflush($staging)||!rewind($staging)||stream_copy_to_stream($staging,$output)===false)throw new RuntimeException('Restore output failed.');
        }finally{sodium_memzero($state);fclose($staging);}
    }
    private static function read($input,int $size): string
    {
        $data='';while(strlen($data)<$size){$next=fread($input,$size-strlen($data));if($next===false||$next==='')throw new RuntimeException('Truncated or unreadable backup.');$data.=$next;}return $data;
    }
    private static function write($output,string $data): void
    {
        $offset=0;$size=strlen($data);while($offset<$size){$n=fwrite($output,substr($data,$offset));if($n===false||$n===0)throw new RuntimeException('Backup output failed.');$offset+=$n;}
    }
}
