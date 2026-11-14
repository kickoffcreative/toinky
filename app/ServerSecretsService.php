<?php
require_once __DIR__.'/SecurityKeyService.php';
require_once __DIR__.'/Logger.php';
final class ServerSecretsService
{
    public const DATABASE_KEYS=['STRIPE_SECRET_KEY','STRIPE_WEBHOOK_SECRET','GOOGLE_RECAPTCHA_SECRET_KEY','RECAPTCHA_SECRET_KEY','SMTP_PASSWORD','AI_BASIC_PASSWORD','AI_BEARER_TOKEN','AI_ACCESS_CLIENT_SECRET'];
    public static function path(): string
    {
        $configured=trim((string)getenv('GAME2_SECRETS_FILE'));if($configured!=='')return $configured;
        $root=realpath(dirname(__DIR__));if(!$root)throw new RuntimeException('Application path unavailable.');
        return dirname($root).'/.toinky-private/'.hash('sha256',str_replace('\\','/',$root)).'.secrets.php';
    }
    public static function get(string $key): string
    {
        if(!preg_match('/^[A-Z][A-Z0-9_]{1,80}$/D',$key))throw new InvalidArgumentException('Invalid credential identifier.');
        $value=(string)getenv('GAME2_'.$key);if(trim($value)!=='')return trim($value);
        $config=self::read();$value=$config[$key]??'';if(!is_string($value))throw new RuntimeException('Invalid server credential configuration.');return trim($value);
    }
    public static function read(): array
    {
        $path=self::path();if(!file_exists($path))return [];
        clearstatcache(true,$path);
        if(is_link($path)||!is_file($path)||!is_readable($path)||!SecurityKeyService::outsideApplication($path)||(DIRECTORY_SEPARATOR==='/'&&((fileperms($path)&0077)!==0||(fileperms(dirname($path))&0022)!==0)))throw new RuntimeException('Server credential file is unavailable or unsafe.');
        $data=require $path;if(!is_array($data))throw new RuntimeException('Invalid server credential configuration.');return $data;
    }
    /** CLI-only, count-only dry run by default. Provider values never leave this process. */
    public static function migrate(bool $apply=false): array
    {
        if(PHP_SAPI!=='cli')throw new LogicException('Credential migration is CLI only.');
        $path=self::path();$directory=dirname($path);
        if(!is_dir($directory)&&$apply&&!mkdir($directory,0700,true)&&!is_dir($directory))throw new RuntimeException('Private credential directory could not be created.');
        if(!is_dir($directory)){
            $parent=realpath(dirname($directory));$root=realpath(dirname(__DIR__));if(!$parent||!$root||str_starts_with(str_replace('\\','/',$directory),str_replace('\\','/',$root).'/'))throw new RuntimeException('Credential destination must be outside the application.');
        }elseif(is_link($directory)||!SecurityKeyService::outsideApplication($path)||(DIRECTORY_SEPARATOR==='/'&&(fileperms($directory)&0022)!==0))throw new RuntimeException('Credential destination must be private and outside the application.');
        $lock=null;if($apply){if(is_link($path.'.lock'))throw new RuntimeException('Linked credential lock refused.');$lock=fopen($path.'.lock','c');if(!$lock||!chmod($path.'.lock',0600)||!flock($lock,LOCK_EX))throw new RuntimeException('Credential migration lock unavailable.');}
        $pdo=db();$pdo->beginTransaction();$temporary=null;$fileInstalled=false;$aiLoaderAttempted=false;$previous=null;$aiOriginal=null;$aiPath=dirname(__DIR__).'/config/ai.php';
        try{
            $data=self::read();$previous=is_file($path)?file_get_contents($path):null;$before=$data;
            $marks=implode(',',array_fill(0,count(self::DATABASE_KEYS),'?'));$q=$pdo->prepare('SELECT setting_key,setting_value FROM platform_settings WHERE setting_key IN('.$marks.') FOR UPDATE');$q->execute(self::DATABASE_KEYS);$rows=$q->fetchAll();
            foreach($rows as $row){$key=$row['setting_key'];$value=(string)$row['setting_value'];if($value==='')continue;if(isset($data[$key])&&$data[$key]!==$value)throw new RuntimeException('Legacy and private credentials conflict. Resolve the configuration privately before deployment.');$data[$key]=$value;}
            if(is_file($aiPath)){
                if(is_link($aiPath))throw new RuntimeException('Linked legacy AI configuration requires manual migration.');
                $source=(string)file_get_contents($aiPath);
                if(str_contains($source,'TOINKY_SERVER_SECRETS_LOADER')&&$previous===null)throw new RuntimeException('Existing AI loader requires its original private credential file. Configure the preserved destination before migration.');
                if(!str_contains($source,'TOINKY_SERVER_SECRETS_LOADER')){
                    $cfg=require $aiPath;if(!is_array($cfg))throw new RuntimeException('Invalid legacy AI configuration.');
                    if(isset($data['ai'])&&$data['ai']!==$cfg)throw new RuntimeException('Legacy AI configuration conflicts with private configuration. Resolve privately.');
                    $data['ai']=$cfg;$aiOriginal=$source;
                }
            }
            $result=['legacy_database_rows'=>count($rows),'legacy_ai_file'=>$aiOriginal!==null,'private_file_changed'=>$data!==$before||$previous===null];
            if(!$apply){$pdo->rollBack();return $result;}
            $version=gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));
            if($aiOriginal!==null)self::writeExclusive($path.'.legacy-ai-'.$version,$aiOriginal);
            if($result['private_file_changed']){
                if($previous!==null)self::writeExclusive($path.'.previous-'.$version,$previous);
                $temporary=$path.'.new-'.bin2hex(random_bytes(8));self::writeExclusive($temporary,"<?php\n// Server-owned credentials; never commit or place under the public root.\nreturn ".var_export($data,true).";\n");
                if(!rename($temporary,$path))throw new RuntimeException('Private credential file could not be installed.');$temporary=null;$fileInstalled=true;
            }
            if($aiOriginal!==null){
                $loader="<?php\n// TOINKY_SERVER_SECRETS_LOADER: credential values live outside the web root.\nreturn (require ".var_export($path,true).")[\"ai\"] ?? [];\n";
                self::replace($aiPath,$loader);$aiLoaderAttempted=true;
            }
            $pdo->prepare('DELETE FROM platform_settings WHERE setting_key IN('.$marks.')')->execute(self::DATABASE_KEYS);$pdo->commit();return $result;
        }catch(Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            try{if($aiOriginal!==null&&$aiLoaderAttempted)self::replace($aiPath,$aiOriginal);}catch(Throwable $restore){Logger::exception($restore,'Private AI rollback requires manual recovery');}
            try{if($fileInstalled){if($previous===null){if(!unlink($path))throw new RuntimeException('Private credential rollback failed.');}else self::replace($path,$previous);}}catch(Throwable $restore){Logger::exception($restore,'Private credential rollback requires manual recovery');}
            if($temporary!==null&&is_file($temporary))unlink($temporary);throw $e;
        }finally{if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}}
    }
    private static function writeExclusive(string $path,string $content): void
    {
        $old=umask(0077);try{$handle=fopen($path,'x');}finally{umask($old);}if(!$handle)throw new RuntimeException('Private credential version could not be created.');
        try{if(!chmod($path,0600)||fwrite($handle,$content)!==strlen($content)||!fflush($handle))throw new RuntimeException('Private credential version could not be written.');}finally{fclose($handle);}
    }
    private static function replace(string $path,string $content): void
    {
        $temporary=$path.'.new-'.bin2hex(random_bytes(8));
        try{self::writeExclusive($temporary,$content);if(!rename($temporary,$path))throw new RuntimeException('Private configuration replacement failed.');}
        finally{if(is_file($temporary))unlink($temporary);}
    }
}
