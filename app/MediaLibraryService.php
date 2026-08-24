<?php
final class MediaLibraryService
{
    public const TYPES=['BACKGROUND','DIVIDER','CARD','CHARACTER','OPENING','SCENE','TURNING_POINT','OUTCOME','ENDING','OTHER'];

    public static function visibleTo(array $user,?string $type=null): array
    {
        $sql='SELECT m.*,u.display_name uploader_name FROM writer_media_assets m LEFT JOIN users u ON u.user_id=m.uploaded_by_user_id WHERE m.active=1';$args=[];
        if((int)($user['is_admin']??0)!==1){$sql.=' AND m.uploaded_by_user_id=?';$args[]=(int)$user['user_id'];}
        if($type!==null&&$type!==''){$sql.=' AND m.media_type=?';$args[]=strtoupper($type);}
        $sql.=' ORDER BY m.media_type,m.title_text,m.media_id';$q=db()->prepare($sql);$q->execute($args);return$q->fetchAll();
    }

    public static function upload(array $user,array $fields,array $file): int
    {
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Choose an image to upload.');
        $inputSize=(int)($file['size']??0);if($inputSize<1||$inputSize>10*1024*1024)throw new RuntimeException('Source images must be 10 MB or smaller.');
        $tmp=(string)($file['tmp_name']??'');$info=@getimagesize($tmp);$mime=(string)($info['mime']??'');$width=(int)($info[0]??0);$height=(int)($info[1]??0);
        $extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];if(!isset($extensions[$mime])||$width<1||$height<1)throw new RuntimeException('Upload a valid JPEG, PNG, or WebP image.');
        $type=strtoupper(trim((string)($fields['media_type']??'OTHER')));if(!in_array($type,self::TYPES,true))throw new RuntimeException('Choose a valid media type.');
        $title=trim((string)($fields['title_text']??''));$alt=trim((string)($fields['alt_text']??''));if($title===''||$alt==='')throw new RuntimeException('Title and alternative text are required.');
        $directory=dirname(__DIR__).'/public/uploads/media-library/'.(int)$user['user_id'];if(!is_dir($directory)&&!mkdir($directory,0775,true)&&!is_dir($directory))throw new RuntimeException('The media directory could not be created.');
        $filename=strtolower($type).'-'.bin2hex(random_bytes(12)).'.'.$extensions[$mime];$target=$directory.'/'.$filename;
        [$finalWidth,$finalHeight]=self::storeResized($tmp,$target,$mime,$width,$height);$size=(int)filesize($target);
        try{$q=db()->prepare('INSERT INTO writer_media_assets(uploaded_by_user_id,media_type,title_text,alt_text,file_path,mime_type,width_px,height_px,file_size_bytes) VALUES(?,?,?,?,?,?,?,?,?)');$q->execute([$user['user_id'],$type,$title,$alt,'public/uploads/media-library/'.(int)$user['user_id'].'/'.$filename,$mime,$finalWidth,$finalHeight,$size]);return(int)db()->lastInsertId();}
        catch(Throwable $e){@unlink($target);throw$e;}
    }

    public static function requireVisible(array $user,int $mediaId,?string $requiredType=null): array
    {
        $q=db()->prepare('SELECT * FROM writer_media_assets WHERE media_id=? AND active=1');$q->execute([$mediaId]);$m=$q->fetch();if(!$m)throw new RuntimeException('That media item is unavailable.');
        if((int)($user['is_admin']??0)!==1&&(int)$m['uploaded_by_user_id']!==(int)$user['user_id'])throw new RuntimeException('You may only use media that you uploaded.');
        if($requiredType!==null&&$m['media_type']!==$requiredType)throw new RuntimeException('Choose a '.$requiredType.' image for this field.');return$m;
    }

    public static function deactivate(array $user,int $mediaId): void
    {
        $m=self::requireVisible($user,$mediaId);db()->prepare('UPDATE writer_media_assets SET active=0 WHERE media_id=?')->execute([$m['media_id']]);
    }

    private static function storeResized(string $source,string $target,string $mime,int $width,int $height): array
    {
        $scale=min(1,1080/max($width,$height));$newWidth=max(1,(int)round($width*$scale));$newHeight=max(1,(int)round($height*$scale));
        if($scale===1.0){if(!move_uploaded_file($source,$target)&&!rename($source,$target))throw new RuntimeException('The image could not be saved.');return[$width,$height];}
        if(!function_exists('imagecreatetruecolor'))throw new RuntimeException('This server needs the PHP GD extension to resize images larger than 1080 pixels.');
        $load=['image/jpeg'=>'imagecreatefromjpeg','image/png'=>'imagecreatefrompng','image/webp'=>'imagecreatefromwebp'][$mime];$save=['image/jpeg'=>'imagejpeg','image/png'=>'imagepng','image/webp'=>'imagewebp'][$mime];$src=@$load($source);if(!$src)throw new RuntimeException('The image could not be decoded.');$dst=imagecreatetruecolor($newWidth,$newHeight);
        if($mime!=='image/jpeg'){imagealphablending($dst,false);imagesavealpha($dst,true);$transparent=imagecolorallocatealpha($dst,0,0,0,127);imagefilledrectangle($dst,0,0,$newWidth,$newHeight,$transparent);}
        imagecopyresampled($dst,$src,0,0,0,0,$newWidth,$newHeight,$width,$height);$ok=$mime==='image/png'?$save($dst,$target,7):$save($dst,$target,86);imagedestroy($src);imagedestroy($dst);if(!$ok)throw new RuntimeException('The resized image could not be saved.');return[$newWidth,$newHeight];
    }
}
