<?php
final class MediaLibraryService
{
    public const TYPES=['BACKGROUND','DIVIDER','CARD','PRIMARY','CHARACTER','OPENING','SCENE','TURNING_POINT','OUTCOME','ENDING','OTHER'];

    public static function visibleTo(array $user,?string $type=null): array
    {
        $sql='SELECT m.*,u.display_name uploader_name FROM writer_media_assets m LEFT JOIN users u ON u.user_id=m.uploaded_by_user_id WHERE m.active=1';$args=[];
        if((int)($user['is_admin']??0)!==1){$sql.=' AND m.uploaded_by_user_id=?';$args[]=(int)$user['user_id'];}
        if($type!==null&&$type!==''){$sql.=' AND m.media_type=?';$args[]=strtoupper($type);}
        $sql.=' ORDER BY m.media_type,m.title_text,m.media_id';$q=db()->prepare($sql);$q->execute($args);return$q->fetchAll();
    }

    public static function page(array $user,int $page=1,int $perPage=20): array
    {
        $perPage=in_array($perPage,[20,50],true)?$perPage:20;$page=max(1,$page);$where=' WHERE m.active=1';$args=[];
        if((int)($user['is_admin']??0)!==1){$where.=' AND m.uploaded_by_user_id=?';$args[]=(int)$user['user_id'];}
        $q=db()->prepare('SELECT COUNT(*) FROM writer_media_assets m'.$where);$q->execute($args);$total=(int)$q->fetchColumn();$pages=max(1,(int)ceil($total/$perPage));$page=min($page,$pages);$offset=($page-1)*$perPage;
        $q=db()->prepare('SELECT m.*,u.display_name uploader_name FROM writer_media_assets m LEFT JOIN users u ON u.user_id=m.uploaded_by_user_id'.$where.' ORDER BY m.created_at_utc DESC,m.media_id DESC LIMIT '.$perPage.' OFFSET '.$offset);$q->execute($args);
        return['items'=>$q->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages,'per_page'=>$perPage];
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
        $basename=strtolower($type).'-'.bin2hex(random_bytes(12));$filename=$basename.'.'.$extensions[$mime];$thumbnailName=$basename.'-thumb.'.$extensions[$mime];$target=$directory.'/'.$filename;$thumbnailTarget=$directory.'/'.$thumbnailName;
        try{[$finalWidth,$finalHeight]=self::storeResized($tmp,$target,$mime,$width,$height);$cropSize=min($finalWidth,$finalHeight);$cropX=(int)(($finalWidth-$cropSize)/2);$cropY=(int)(($finalHeight-$cropSize)/2);self::createThumbnail($target,$thumbnailTarget,$mime,$finalWidth,$finalHeight,$cropX,$cropY,$cropSize);$size=(int)filesize($target);$path='public/uploads/media-library/'.(int)$user['user_id'].'/'.$filename;$thumbnailPath='public/uploads/media-library/'.(int)$user['user_id'].'/'.$thumbnailName;$q=db()->prepare('INSERT INTO writer_media_assets(uploaded_by_user_id,media_type,title_text,alt_text,file_path,thumbnail_path,thumbnail_crop_x,thumbnail_crop_y,thumbnail_crop_size,mime_type,width_px,height_px,file_size_bytes) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');$q->execute([$user['user_id'],$type,$title,$alt,$path,$thumbnailPath,$cropX,$cropY,$cropSize,$mime,$finalWidth,$finalHeight,$size]);return(int)db()->lastInsertId();}
        catch(Throwable $e){@unlink($target);@unlink($thumbnailTarget);throw$e;}
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

    public static function update(array $user,int $mediaId,array $fields): void
    {
        $m=self::requireVisible($user,$mediaId);$type=strtoupper(trim((string)($fields['media_type']??'')));if(!in_array($type,self::TYPES,true))throw new RuntimeException('Choose a valid media type.');$title=trim((string)($fields['title_text']??''));$alt=trim((string)($fields['alt_text']??''));if($title===''||$alt==='')throw new RuntimeException('Title and alternative text are required.');if(mb_strlen($title)>190||mb_strlen($alt)>500)throw new RuntimeException('Media title or alternative text is too long.');
        db()->beginTransaction();try{db()->prepare('UPDATE writer_media_assets SET media_type=?,title_text=?,alt_text=? WHERE media_id=?')->execute([$type,$title,$alt,$mediaId]);db()->prepare('UPDATE plot_media_assets SET title_text=?,alt_text=? WHERE library_media_id=?')->execute([$title,$alt,$mediaId]);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw$e;}
    }

    public static function cropResize(array $user,int $mediaId,array $fields): void
    {
        $m=self::requireVisible($user,$mediaId);if(!function_exists('imagecreatetruecolor'))throw new RuntimeException('This server needs PHP GD for cropping and resizing.');$source=self::absolutePath((string)$m['file_path']);if(!is_file($source))throw new RuntimeException('The source image file is missing.');$width=(int)$m['width_px'];$height=(int)$m['height_px'];$x=max(0,(int)($fields['crop_x']??0));$y=max(0,(int)($fields['crop_y']??0));$cropWidth=(int)($fields['crop_width']??$width);$cropHeight=(int)($fields['crop_height']??$height);if($cropWidth<1||$cropHeight<1||$x+$cropWidth>$width||$y+$cropHeight>$height)throw new RuntimeException('Crop dimensions must stay inside the current image.');
        $targetWidth=(int)($fields['resize_width']??0);$targetHeight=(int)($fields['resize_height']??0);if($targetWidth<1&&$targetHeight<1){$targetWidth=$cropWidth;$targetHeight=$cropHeight;}elseif($targetWidth<1)$targetWidth=max(1,(int)round($cropWidth*$targetHeight/$cropHeight));elseif($targetHeight<1)$targetHeight=max(1,(int)round($cropHeight*$targetWidth/$cropWidth));if($targetWidth>1920||$targetHeight>1920)throw new RuntimeException('Resized media may not exceed 1920 pixels on either side.');
        $mime=(string)$m['mime_type'];$load=['image/jpeg'=>'imagecreatefromjpeg','image/png'=>'imagecreatefrompng','image/webp'=>'imagecreatefromwebp'][$mime]??null;$save=['image/jpeg'=>'imagejpeg','image/png'=>'imagepng','image/webp'=>'imagewebp'][$mime]??null;if(!$load||!$save)throw new RuntimeException('This media format cannot be processed.');$src=@$load($source);if(!$src)throw new RuntimeException('The image could not be decoded.');$dst=imagecreatetruecolor($targetWidth,$targetHeight);if($mime!=='image/jpeg'){imagealphablending($dst,false);imagesavealpha($dst,true);$transparent=imagecolorallocatealpha($dst,0,0,0,127);imagefilledrectangle($dst,0,0,$targetWidth,$targetHeight,$transparent);}imagecopyresampled($dst,$src,0,0,$x,$y,$targetWidth,$targetHeight,$cropWidth,$cropHeight);$temporary=$source.'.processing';$ok=$mime==='image/png'?$save($dst,$temporary,7):$save($dst,$temporary,86);imagedestroy($src);imagedestroy($dst);if(!$ok||!rename($temporary,$source)){@unlink($temporary);throw new RuntimeException('The processed image could not be saved.');}$size=(int)filesize($source);$thumbPath=(string)($m['thumbnail_path']??'');if($thumbPath!==''){$thumbTarget=self::absolutePath($thumbPath);$thumbSize=min($targetWidth,$targetHeight);self::createThumbnail($source,$thumbTarget,$mime,$targetWidth,$targetHeight,(int)(($targetWidth-$thumbSize)/2),(int)(($targetHeight-$thumbSize)/2),$thumbSize);}db()->prepare('UPDATE writer_media_assets SET width_px=?,height_px=?,file_size_bytes=?,thumbnail_crop_x=?,thumbnail_crop_y=?,thumbnail_crop_size=? WHERE media_id=?')->execute([$targetWidth,$targetHeight,$size,(int)(($targetWidth-min($targetWidth,$targetHeight))/2),(int)(($targetHeight-min($targetWidth,$targetHeight))/2),min($targetWidth,$targetHeight),$mediaId]);db()->prepare('UPDATE plot_media_assets SET file_size_bytes=? WHERE library_media_id=?')->execute([$size,$mediaId]);
    }

    public static function regenerateThumbnail(array $user,int $mediaId,array $fields): void
    {
        $m=self::requireVisible($user,$mediaId);$source=self::absolutePath((string)$m['file_path']);if(!is_file($source))throw new RuntimeException('The processed image file is missing.');$width=(int)$m['width_px'];$height=(int)$m['height_px'];$size=(int)($fields['thumbnail_crop_size']??min($width,$height));$x=max(0,(int)($fields['thumbnail_crop_x']??0));$y=max(0,(int)($fields['thumbnail_crop_y']??0));if($size<1||$x+$size>$width||$y+$size>$height)throw new RuntimeException('The thumbnail square must stay inside the processed image.');$path=(string)($m['thumbnail_path']??'');if($path===''){$extension=pathinfo((string)$m['file_path'],PATHINFO_EXTENSION);$path=preg_replace('/\.'.$extension.'$/i','-thumb.'.$extension,(string)$m['file_path']);}self::createThumbnail($source,self::absolutePath($path),(string)$m['mime_type'],$width,$height,$x,$y,$size);db()->prepare('UPDATE writer_media_assets SET thumbnail_path=?,thumbnail_crop_x=?,thumbnail_crop_y=?,thumbnail_crop_size=? WHERE media_id=?')->execute([$path,$x,$y,$size,$mediaId]);
    }

    public static function delete(array $user,int $mediaId): void
    {
        $m=self::requireVisible($user,$mediaId);$pdo=db();$pdo->beginTransaction();try{$pdo->prepare('UPDATE plot_media_assets SET active=0 WHERE library_media_id=?')->execute([$mediaId]);$pdo->prepare('UPDATE genre_themes SET background_image_path=IF(background_media_id=?,NULL,background_image_path),divider_image_path=IF(divider_media_id=?,NULL,divider_image_path),card_image_path=IF(card_media_id=?,NULL,card_image_path),background_media_id=IF(background_media_id=?,NULL,background_media_id),divider_media_id=IF(divider_media_id=?,NULL,divider_media_id),card_media_id=IF(card_media_id=?,NULL,card_media_id)')->execute([$mediaId,$mediaId,$mediaId,$mediaId,$mediaId,$mediaId]);$pdo->prepare('DELETE FROM writer_media_assets WHERE media_id=?')->execute([$mediaId]);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}foreach([(string)$m['file_path'],(string)($m['thumbnail_path']??'')]as$filePath)if(str_starts_with($filePath,'public/uploads/media-library/')){$path=self::absolutePath($filePath);if(is_file($path))@unlink($path);}
    }

    private static function storeResized(string $source,string $target,string $mime,int $width,int $height): array
    {
        $scale=min(1,1920/max($width,$height));$newWidth=max(1,(int)round($width*$scale));$newHeight=max(1,(int)round($height*$scale));
        if($scale>=1){if(!move_uploaded_file($source,$target)&&!rename($source,$target))throw new RuntimeException('The image could not be saved.');return[$width,$height];}
        if(!function_exists('imagecreatetruecolor'))throw new RuntimeException('This server needs the PHP GD extension to resize images larger than 1920 pixels.');
        $load=['image/jpeg'=>'imagecreatefromjpeg','image/png'=>'imagecreatefrompng','image/webp'=>'imagecreatefromwebp'][$mime];$save=['image/jpeg'=>'imagejpeg','image/png'=>'imagepng','image/webp'=>'imagewebp'][$mime];$src=@$load($source);if(!$src)throw new RuntimeException('The image could not be decoded.');$dst=imagecreatetruecolor($newWidth,$newHeight);
        if($mime!=='image/jpeg'){imagealphablending($dst,false);imagesavealpha($dst,true);$transparent=imagecolorallocatealpha($dst,0,0,0,127);imagefilledrectangle($dst,0,0,$newWidth,$newHeight,$transparent);}
        imagecopyresampled($dst,$src,0,0,0,0,$newWidth,$newHeight,$width,$height);$ok=$mime==='image/png'?$save($dst,$target,7):$save($dst,$target,86);imagedestroy($src);imagedestroy($dst);if(!$ok)throw new RuntimeException('The resized image could not be saved.');return[$newWidth,$newHeight];
    }
    private static function createThumbnail(string $source,string $target,string $mime,int $width,int $height,int $x,int $y,int $size): void
    {
        if(!function_exists('imagecreatetruecolor'))throw new RuntimeException('This server needs PHP GD to create thumbnails.');$load=['image/jpeg'=>'imagecreatefromjpeg','image/png'=>'imagecreatefrompng','image/webp'=>'imagecreatefromwebp'][$mime]??null;$save=['image/jpeg'=>'imagejpeg','image/png'=>'imagepng','image/webp'=>'imagewebp'][$mime]??null;if(!$load||!$save)throw new RuntimeException('This media format cannot create a thumbnail.');$src=@$load($source);if(!$src)throw new RuntimeException('The processed image could not be decoded for its thumbnail.');$dst=imagecreatetruecolor(150,150);if($mime!=='image/jpeg'){imagealphablending($dst,false);imagesavealpha($dst,true);$transparent=imagecolorallocatealpha($dst,0,0,0,127);imagefilledrectangle($dst,0,0,150,150,$transparent);}imagecopyresampled($dst,$src,0,0,$x,$y,150,150,$size,$size);$temporary=$target.'.processing';$ok=$mime==='image/png'?$save($dst,$temporary,7):$save($dst,$temporary,86);imagedestroy($src);imagedestroy($dst);if(!$ok||!rename($temporary,$target)){@unlink($temporary);throw new RuntimeException('The thumbnail could not be saved.');}
    }
    private static function absolutePath(string $path): string{return dirname(__DIR__).'/'.ltrim(str_replace('\\','/',$path),'/');}
}
