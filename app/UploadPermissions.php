<?php
final class UploadPermissions
{
    public static function directory(string $path): void
    {
        if(is_link($path))throw new RuntimeException('Linked upload directories are unavailable.');
        if(!is_dir($path)&&!mkdir($path,0750,true)&&!is_dir($path))throw new RuntimeException('Upload directory could not be created.');
        if(!chmod($path,(fileperms($path)&0777)&0750))throw new RuntimeException('Upload directory permissions could not be set.');
    }
    public static function file(string $path): void
    {
        if(is_link($path)||!is_file($path)||!chmod($path,(fileperms($path)&0777)&0640))throw new RuntimeException('Upload file permissions could not be set.');
    }
}
