<?php
final class SecurityKeyService
{
    public static function path(): string
    {
        $configured=trim((string)getenv('GAME2_SECURITY_KEY_FILE'));
        if($configured!=='')return $configured;
        $root=realpath(dirname(__DIR__));if(!$root)throw new RuntimeException('Application path is unavailable.');
        return dirname($root).'/.toinky-private/'.hash('sha256',str_replace('\\','/',$root)).'.key';
    }
    public static function outsideApplication(string $path): bool
    {
        $root=realpath(dirname(__DIR__));$parent=realpath(dirname($path));if(!$root||!$parent)return false;
        $root=str_replace('\\','/',$root);$candidate=str_replace('\\','/',$parent).'/'.basename($path);
        if(DIRECTORY_SEPARATOR==='\\'){$root=strtolower($root);$candidate=strtolower($candidate);}
        return $candidate!==$root&&!str_starts_with($candidate,rtrim($root,'/').'/');
    }
    public static function get(): ?string
    {
        $configured=(string)getenv('GAME2_SECURITY_KEY');
        if($configured!==''){$key=base64_decode($configured,true);return is_string($key)&&strlen($key)===32?$key:null;}
        $path=self::path();
        clearstatcache(true,$path);
        if(!is_file($path)||is_link($path)||!is_readable($path)||!self::outsideApplication($path))return null;
        $mode=fileperms($path);if(DIRECTORY_SEPARATOR==='/'&&($mode===false||($mode&0077)!==0))return null;
        $encoded=@file_get_contents($path,false,null,0,256);if(!is_string($encoded))return null;
        $key=base64_decode(trim($encoded),true);return is_string($key)&&strlen($key)===32?$key:null;
    }
}
