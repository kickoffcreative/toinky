<?php
final class MediaLibraryService
{
    public const TYPES=['BACKGROUND','DIVIDER','CARD','PRIMARY','CHARACTER','OPENING','SCENE','TURNING_POINT','OUTCOME','ENDING','PARALLAX','OTHER'];
    private const VERSION_CODES=['THUMBNAIL','MEDIUM','LARGE'];
    private const DEFAULT_SETTINGS=[
        'MEDIA_MAX_UPLOAD_MB'=>'10',
        'MEDIA_MASTER_MAX_PX'=>'1920',
        'MEDIA_THUMBNAIL_SIZE_PX'=>'150',
        'MEDIA_GENERATE_MEDIUM'=>'0',
        'MEDIA_MEDIUM_MAX_PX'=>'250',
        'MEDIA_MEDIUM_MODE'=>'LONGEST',
        'MEDIA_GENERATE_LARGE'=>'0',
        'MEDIA_LARGE_MAX_PX'=>'600',
        'MEDIA_LARGE_MODE'=>'LONGEST',
        'MEDIA_JPEG_QUALITY'=>'86',
        'MEDIA_PNG_COMPRESSION'=>'7',
    ];

    public static function settings(): array
    {
        $settings=self::DEFAULT_SETTINGS;
        try{
            $q=db()->query("SELECT setting_key,setting_value FROM platform_settings WHERE setting_key LIKE 'MEDIA_%'");
            foreach($q->fetchAll()as$row)if(array_key_exists($row['setting_key'],self::DEFAULT_SETTINGS))$settings[$row['setting_key']]=(string)$row['setting_value'];
        }catch(Throwable $ignored){}
        return[
            'max_upload_mb'=>self::clampInt($settings['MEDIA_MAX_UPLOAD_MB'],1,50,10),
            'master_max_px'=>self::clampInt($settings['MEDIA_MASTER_MAX_PX'],250,3840,1920),
            'thumbnail_size_px'=>self::clampInt($settings['MEDIA_THUMBNAIL_SIZE_PX'],64,600,150),
            'generate_medium'=>$settings['MEDIA_GENERATE_MEDIUM']==='1',
            'medium_max_px'=>self::clampInt($settings['MEDIA_MEDIUM_MAX_PX'],80,1920,250),
            'medium_mode'=>self::dimensionMode($settings['MEDIA_MEDIUM_MODE']),
            'generate_large'=>$settings['MEDIA_GENERATE_LARGE']==='1',
            'large_max_px'=>self::clampInt($settings['MEDIA_LARGE_MAX_PX'],120,3840,600),
            'large_mode'=>self::dimensionMode($settings['MEDIA_LARGE_MODE']),
            'jpeg_quality'=>self::clampInt($settings['MEDIA_JPEG_QUALITY'],50,95,86),
            'png_compression'=>self::clampInt($settings['MEDIA_PNG_COMPRESSION'],0,9,7),
        ];
    }

    public static function saveSettings(array $admin,array $fields): void
    {
        if((int)($admin['is_admin']??0)!==1)throw new RuntimeException('Administrator access is required.');
        $values=[
            'MEDIA_MAX_UPLOAD_MB'=>(string)self::postedInt($fields,'max_upload_mb',1,50,10),
            'MEDIA_MASTER_MAX_PX'=>(string)self::postedInt($fields,'master_max_px',250,3840,1920),
            'MEDIA_THUMBNAIL_SIZE_PX'=>(string)self::postedInt($fields,'thumbnail_size_px',64,600,150),
            'MEDIA_GENERATE_MEDIUM'=>!empty($fields['generate_medium'])?'1':'0',
            'MEDIA_MEDIUM_MAX_PX'=>(string)self::postedInt($fields,'medium_max_px',80,1920,250),
            'MEDIA_MEDIUM_MODE'=>self::dimensionMode((string)($fields['medium_mode']??'LONGEST')),
            'MEDIA_GENERATE_LARGE'=>!empty($fields['generate_large'])?'1':'0',
            'MEDIA_LARGE_MAX_PX'=>(string)self::postedInt($fields,'large_max_px',120,3840,600),
            'MEDIA_LARGE_MODE'=>self::dimensionMode((string)($fields['large_mode']??'LONGEST')),
            'MEDIA_JPEG_QUALITY'=>(string)self::postedInt($fields,'jpeg_quality',50,95,86),
            'MEDIA_PNG_COMPRESSION'=>(string)self::postedInt($fields,'png_compression',0,9,7),
        ];
        $q=db()->prepare('INSERT INTO platform_settings(setting_key,setting_value,updated_by_user_id) VALUES(?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by_user_id=VALUES(updated_by_user_id)');
        foreach($values as$key=>$value)$q->execute([$key,$value,(int)$admin['user_id']]);
    }

    public static function visibleTo(array $user,?string $type=null): array
    {
        $sql='SELECT m.*,u.display_name uploader_name FROM writer_media_assets m LEFT JOIN users u ON u.user_id=m.uploaded_by_user_id WHERE m.active=1';$args=[];
        if((int)($user['is_admin']??0)!==1){$sql.=' AND m.uploaded_by_user_id=?';$args[]=(int)$user['user_id'];}
        if($type!==null&&$type!==''){$sql.=' AND m.media_type=?';$args[]=strtoupper($type);}
        $sql.=' ORDER BY m.media_type,m.title_text,m.media_id';$q=db()->prepare($sql);$q->execute($args);return$q->fetchAll();
    }

    public static function page(array $user,int $page=1,int $perPage=25): array
    {
        $perPage=in_array($perPage,[25,50,100],true)?$perPage:25;$page=max(1,$page);$where=' WHERE m.active=1';$args=[];
        if((int)($user['is_admin']??0)!==1){$where.=' AND m.uploaded_by_user_id=?';$args[]=(int)$user['user_id'];}
        $q=db()->prepare('SELECT COUNT(*) FROM writer_media_assets m'.$where);$q->execute($args);$total=(int)$q->fetchColumn();$pages=max(1,(int)ceil($total/$perPage));$page=min($page,$pages);$offset=($page-1)*$perPage;
        $q=db()->prepare('SELECT m.*,u.display_name uploader_name FROM writer_media_assets m LEFT JOIN users u ON u.user_id=m.uploaded_by_user_id'.$where.' ORDER BY m.created_at_utc DESC,m.media_id DESC LIMIT '.$perPage.' OFFSET '.$offset);$q->execute($args);
        return['items'=>$q->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages,'per_page'=>$perPage];
    }

    public static function upload(array $user,array $fields,array $file): int
    {
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Choose an image to upload.');
        $settings=self::settings();$inputSize=(int)($file['size']??0);$limit=$settings['max_upload_mb']*1024*1024;
        if($inputSize<1||$inputSize>$limit)throw new RuntimeException('Source images must be '.$settings['max_upload_mb'].' MB or smaller.');
        $tmp=(string)($file['tmp_name']??'');$info=@getimagesize($tmp);$mime=(string)($info['mime']??'');$width=(int)($info[0]??0);$height=(int)($info[1]??0);
        $extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];if(!isset($extensions[$mime])||$width<1||$height<1)throw new RuntimeException('Upload a valid JPEG, PNG, or WebP image.');
        if($width*$height>80000000)throw new RuntimeException('The source image has too many pixels to process safely.');
        $type=strtoupper(trim((string)($fields['media_type']??'OTHER')));if(!in_array($type,self::TYPES,true))throw new RuntimeException('Choose a valid media type.');
        $title=trim((string)($fields['title_text']??''));$alt=trim((string)($fields['alt_text']??''));if($title===''||$alt==='')throw new RuntimeException('Title and alternative text are required.');
        $directory=dirname(__DIR__).'/public/uploads/media-library/'.(int)$user['user_id'];if(!is_dir($directory)&&!mkdir($directory,0775,true)&&!is_dir($directory))throw new RuntimeException('The media directory could not be created.');
        $basename=strtolower($type).'-'.bin2hex(random_bytes(12));$filename=$basename.'.'.$extensions[$mime];$target=$directory.'/'.$filename;$path='public/uploads/media-library/'.(int)$user['user_id'].'/'.$filename;
        $created=[];$mediaId=0;
        try{
            [$finalWidth,$finalHeight]=self::storeResized($tmp,$target,$mime,$width,$height,$settings['master_max_px'],$settings);$created[]=$path;
            $size=(int)filesize($target);$cropSize=min($finalWidth,$finalHeight);$cropX=(int)(($finalWidth-$cropSize)/2);$cropY=(int)(($finalHeight-$cropSize)/2);
            $thumbnailName=$basename.'-thumb.'.$extensions[$mime];$thumbnailPath='public/uploads/media-library/'.(int)$user['user_id'].'/'.$thumbnailName;$thumbnailTarget=$directory.'/'.$thumbnailName;
            self::createThumbnail($target,$thumbnailTarget,$mime,$finalWidth,$finalHeight,$cropX,$cropY,$cropSize,$settings['thumbnail_size_px'],$settings);$created[]=$thumbnailPath;
            $q=db()->prepare('INSERT INTO writer_media_assets(uploaded_by_user_id,media_type,title_text,alt_text,file_path,thumbnail_path,thumbnail_crop_x,thumbnail_crop_y,thumbnail_crop_size,mime_type,width_px,height_px,file_size_bytes) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $q->execute([(int)$user['user_id'],$type,$title,$alt,$path,$thumbnailPath,$cropX,$cropY,$cropSize,$mime,$finalWidth,$finalHeight,$size]);$mediaId=(int)db()->lastInsertId();
            self::upsertVersion($mediaId,'THUMBNAIL',$thumbnailPath,$mime,$settings['thumbnail_size_px'],$settings['thumbnail_size_px'],(int)filesize($thumbnailTarget),$cropX,$cropY,$cropSize,$cropSize);
            self::generateOptionalVersions($mediaId,$path,$basename,$extensions[$mime],$mime,$finalWidth,$finalHeight,$settings);
            return$mediaId;
        }catch(Throwable $e){if($mediaId>0){try{db()->prepare('DELETE FROM writer_media_assets WHERE media_id=?')->execute([$mediaId]);}catch(Throwable $ignored){}}foreach($created as$filePath)self::unlinkUpload($filePath);throw$e;}
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
        $targetWidth=(int)($fields['resize_width']??0);$targetHeight=(int)($fields['resize_height']??0);if($targetWidth<1&&$targetHeight<1){$targetWidth=$cropWidth;$targetHeight=$cropHeight;}elseif($targetWidth<1)$targetWidth=max(1,(int)round($cropWidth*$targetHeight/$cropHeight));elseif($targetHeight<1)$targetHeight=max(1,(int)round($cropHeight*$targetWidth/$cropWidth));$settings=self::settings();if($targetWidth>$settings['master_max_px']||$targetHeight>$settings['master_max_px'])throw new RuntimeException('Resized media may not exceed '.$settings['master_max_px'].' pixels on either side.');
        self::writeResampled($source,$source.'.processing',(string)$m['mime_type'],$x,$y,$cropWidth,$cropHeight,$targetWidth,$targetHeight,$settings);
        if(!rename($source.'.processing',$source)){@unlink($source.'.processing');throw new RuntimeException('The processed image could not be saved.');}
        self::afterMasterChanged((int)$m['media_id'],(string)$m['file_path'],(string)$m['mime_type'],$targetWidth,$targetHeight,(int)filesize($source),$settings);
    }

    public static function resize(array $user,int $mediaId,array $fields): void
    {
        $m=self::requireVisible($user,$mediaId);$width=(int)$m['width_px'];$height=(int)$m['height_px'];$mode=(string)($fields['resize_mode']??'pixels');
        if($mode==='scale'){$scale=max(1,min(400,(int)($fields['scale_percent']??100)))/100;$targetWidth=max(1,(int)round($width*$scale));$targetHeight=max(1,(int)round($height*$scale));}
        else{$targetWidth=(int)($fields['resize_width']??0);$targetHeight=(int)($fields['resize_height']??0);if($targetWidth<1&&$targetHeight<1)throw new RuntimeException('Enter a width, height, or scale amount.');if($targetWidth<1)$targetWidth=max(1,(int)round($width*$targetHeight/$height));if($targetHeight<1)$targetHeight=max(1,(int)round($height*$targetWidth/$width));}
        self::cropResize($user,$mediaId,['crop_x'=>0,'crop_y'=>0,'crop_width'=>$width,'crop_height'=>$height,'resize_width'=>$targetWidth,'resize_height'=>$targetHeight]);
    }

    public static function regenerateThumbnail(array $user,int $mediaId,array $fields): void
    {
        $m=self::requireVisible($user,$mediaId);$source=self::absolutePath((string)$m['file_path']);if(!is_file($source))throw new RuntimeException('The processed image file is missing.');$width=(int)$m['width_px'];$height=(int)$m['height_px'];$size=(int)($fields['thumbnail_crop_size']??min($width,$height));$x=max(0,(int)($fields['thumbnail_crop_x']??0));$y=max(0,(int)($fields['thumbnail_crop_y']??0));if($size<1||$x+$size>$width||$y+$size>$height)throw new RuntimeException('The thumbnail square must stay inside the processed image.');$settings=self::settings();$path=(string)($m['thumbnail_path']??'');if($path===''){$extension=pathinfo((string)$m['file_path'],PATHINFO_EXTENSION);$path=preg_replace('/\.'.$extension.'$/i','-thumb.'.$extension,(string)$m['file_path']);}self::createThumbnail($source,self::absolutePath($path),(string)$m['mime_type'],$width,$height,$x,$y,$size,$settings['thumbnail_size_px'],$settings);db()->prepare('UPDATE writer_media_assets SET thumbnail_path=?,thumbnail_crop_x=?,thumbnail_crop_y=?,thumbnail_crop_size=? WHERE media_id=?')->execute([$path,$x,$y,$size,$mediaId]);self::upsertVersion($mediaId,'THUMBNAIL',$path,(string)$m['mime_type'],$settings['thumbnail_size_px'],$settings['thumbnail_size_px'],(int)filesize(self::absolutePath($path)),$x,$y,$size,$size);
    }

    public static function resetThumbnail(array $user,int $mediaId): void
    {
        $m=self::requireVisible($user,$mediaId);$size=min((int)$m['width_px'],(int)$m['height_px']);self::regenerateThumbnail($user,$mediaId,['thumbnail_crop_x'=>(int)(((int)$m['width_px']-$size)/2),'thumbnail_crop_y'=>(int)(((int)$m['height_px']-$size)/2),'thumbnail_crop_size'=>$size]);
    }

    public static function regenerateVersions(array $user,int $mediaId): void
    {
        $m=self::requireVisible($user,$mediaId);$settings=self::settings();self::deleteVersions($mediaId,['MEDIUM','LARGE']);self::generateOptionalVersions($mediaId,(string)$m['file_path'],pathinfo((string)$m['file_path'],PATHINFO_FILENAME),pathinfo((string)$m['file_path'],PATHINFO_EXTENSION),(string)$m['mime_type'],(int)$m['width_px'],(int)$m['height_px'],$settings);
    }

    public static function versionsByMediaIds(array $user,array $mediaIds): array
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$mediaIds),fn($id)=>$id>0)));if(!$ids)return[];
        $placeholders=implode(',',array_fill(0,count($ids),'?'));$args=$ids;$owner='';
        if((int)($user['is_admin']??0)!==1){$owner=' AND m.uploaded_by_user_id=?';$args[]=(int)$user['user_id'];}
        $q=db()->prepare('SELECT v.* FROM writer_media_versions v JOIN writer_media_assets m ON m.media_id=v.media_id AND m.active=1 WHERE v.media_id IN('.$placeholders.')'.$owner.' ORDER BY v.media_id,FIELD(v.version_code,"THUMBNAIL","MEDIUM","LARGE")');$q->execute($args);$out=[];
        foreach($q->fetchAll()as$row)$out[(int)$row['media_id']][$row['version_code']]=$row;return$out;
    }

    public static function delete(array $user,int $mediaId): void
    {
        $m=self::requireVisible($user,$mediaId);$versions=[];try{$versions=self::versionsByMediaIds($user,[$mediaId])[$mediaId]??[];}catch(Throwable $ignored){}$pdo=db();$pdo->beginTransaction();try{$pdo->prepare('UPDATE plot_media_assets SET active=0 WHERE library_media_id=?')->execute([$mediaId]);$pdo->prepare('DELETE FROM site_parallax_layers WHERE media_id=?')->execute([$mediaId]);$pdo->prepare('UPDATE genre_themes SET background_image_path=IF(background_media_id=?,NULL,background_image_path),divider_image_path=IF(divider_media_id=?,NULL,divider_image_path),card_image_path=IF(card_media_id=?,NULL,card_image_path),background_media_id=IF(background_media_id=?,NULL,background_media_id),divider_media_id=IF(divider_media_id=?,NULL,divider_media_id),card_media_id=IF(card_media_id=?,NULL,card_media_id)')->execute([$mediaId,$mediaId,$mediaId,$mediaId,$mediaId,$mediaId]);$pdo->prepare('UPDATE site_page_sections SET image_media_id=IF(image_media_id=?,NULL,image_media_id),background_media_id=IF(background_media_id=?,NULL,background_media_id)')->execute([$mediaId,$mediaId]);self::cleanPageRowsForMedia($mediaId);$pdo->prepare('DELETE FROM writer_media_assets WHERE media_id=?')->execute([$mediaId]);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
        foreach([(string)$m['file_path'],(string)($m['thumbnail_path']??'')]as$filePath)self::unlinkUpload($filePath);
        foreach($versions as$version)self::unlinkUpload((string)$version['file_path']);
    }

    public static function resetAll(array $admin): void
    {
        if((int)($admin['is_admin']??0)!==1)throw new RuntimeException('Administrator access is required.');
        $paths=[];
        foreach(db()->query('SELECT file_path,thumbnail_path FROM writer_media_assets')->fetchAll()as$row){$paths[]=(string)$row['file_path'];if(!empty($row['thumbnail_path']))$paths[]=(string)$row['thumbnail_path'];}
        try{foreach(db()->query('SELECT file_path FROM writer_media_versions')->fetchAll(PDO::FETCH_COLUMN)as$path)$paths[]=(string)$path;}catch(Throwable $ignored){}
        foreach(db()->query("SELECT file_path FROM plot_media_assets WHERE library_media_id IS NULL AND file_path LIKE 'public/uploads/story-media/%'")->fetchAll(PDO::FETCH_COLUMN)as$path)$paths[]=(string)$path;
        $pdo=db();$pdo->beginTransaction();
        try{
            $pdo->exec('UPDATE site_page_sections SET image_media_id=NULL,background_media_id=NULL');
            self::cleanPageRowsForMedia(null);
            $pdo->exec('DELETE FROM site_parallax_layers WHERE media_id IS NOT NULL');
            $pdo->exec('UPDATE genre_themes SET background_media_id=NULL,divider_media_id=NULL,card_media_id=NULL,background_image_path=NULL,divider_image_path=NULL,card_image_path=NULL');
            $pdo->exec('DELETE FROM plot_media_assets');
            $pdo->exec('DELETE FROM writer_media_assets');
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
        foreach(array_unique($paths)as$path)self::unlinkUpload($path);
    }

    private static function afterMasterChanged(int $mediaId,string $path,string $mime,int $width,int $height,int $size,array $settings): void
    {
        $cropSize=min($width,$height);$cropX=(int)(($width-$cropSize)/2);$cropY=(int)(($height-$cropSize)/2);
        $q=db()->prepare('SELECT thumbnail_path FROM writer_media_assets WHERE media_id=?');$q->execute([$mediaId]);$thumbPath=(string)$q->fetchColumn();
        if($thumbPath!=='')self::createThumbnail(self::absolutePath($path),self::absolutePath($thumbPath),$mime,$width,$height,$cropX,$cropY,$cropSize,$settings['thumbnail_size_px'],$settings);
        db()->prepare('UPDATE writer_media_assets SET width_px=?,height_px=?,file_size_bytes=?,thumbnail_crop_x=?,thumbnail_crop_y=?,thumbnail_crop_size=? WHERE media_id=?')->execute([$width,$height,$size,$cropX,$cropY,$cropSize,$mediaId]);
        db()->prepare('UPDATE plot_media_assets SET file_path=?,mime_type=?,file_size_bytes=? WHERE library_media_id=?')->execute([$path,$mime,$size,$mediaId]);
        if($thumbPath!=='')self::upsertVersion($mediaId,'THUMBNAIL',$thumbPath,$mime,$settings['thumbnail_size_px'],$settings['thumbnail_size_px'],(int)filesize(self::absolutePath($thumbPath)),$cropX,$cropY,$cropSize,$cropSize);
        self::deleteVersions($mediaId,['MEDIUM','LARGE']);self::generateOptionalVersions($mediaId,$path,pathinfo($path,PATHINFO_FILENAME),pathinfo($path,PATHINFO_EXTENSION),$mime,$width,$height,$settings);
    }

    private static function generateOptionalVersions(int $mediaId,string $path,string $basename,string $extension,string $mime,int $width,int $height,array $settings): void
    {
        foreach([['MEDIUM','generate_medium','medium_max_px','medium_mode'],['LARGE','generate_large','large_max_px','large_mode']]as$config){
            [$code,$flag,$maxKey,$modeKey]=$config;if(!$settings[$flag])continue;
            [$targetWidth,$targetHeight]=self::scaledDimensions($width,$height,$settings[$maxKey],$settings[$modeKey]);$versionPath=preg_replace('/\.'.preg_quote($extension,'/').'$/i','-'.strtolower($code).'.'.$extension,$path);
            self::writeScaled(self::absolutePath($path),self::absolutePath($versionPath),$mime,$width,$height,$targetWidth,$targetHeight,$settings);
            self::upsertVersion($mediaId,$code,$versionPath,$mime,$targetWidth,$targetHeight,(int)filesize(self::absolutePath($versionPath)));
        }
    }

    private static function deleteVersions(int $mediaId,array $codes): void
    {
        $placeholders=implode(',',array_fill(0,count($codes),'?'));$q=db()->prepare('SELECT file_path FROM writer_media_versions WHERE media_id=? AND version_code IN('.$placeholders.')');$q->execute(array_merge([$mediaId],$codes));foreach($q->fetchAll(PDO::FETCH_COLUMN)as$path)self::unlinkUpload((string)$path);
        db()->prepare('DELETE FROM writer_media_versions WHERE media_id=? AND version_code IN('.$placeholders.')')->execute(array_merge([$mediaId],$codes));
    }

    private static function upsertVersion(int $mediaId,string $code,string $path,string $mime,int $width,int $height,int $size,?int $cropX=null,?int $cropY=null,?int $cropWidth=null,?int $cropHeight=null): void
    {
        if(!in_array($code,self::VERSION_CODES,true))return;
        try{$q=db()->prepare('INSERT INTO writer_media_versions(media_id,version_code,file_path,mime_type,width_px,height_px,file_size_bytes,crop_x,crop_y,crop_width,crop_height) VALUES(?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE file_path=VALUES(file_path),mime_type=VALUES(mime_type),width_px=VALUES(width_px),height_px=VALUES(height_px),file_size_bytes=VALUES(file_size_bytes),crop_x=VALUES(crop_x),crop_y=VALUES(crop_y),crop_width=VALUES(crop_width),crop_height=VALUES(crop_height)');$q->execute([$mediaId,$code,$path,$mime,$width,$height,$size,$cropX,$cropY,$cropWidth,$cropHeight]);}catch(Throwable $ignored){}
    }

    private static function storeResized(string $source,string $target,string $mime,int $width,int $height,int $maxPx,array $settings): array
    {
        [$newWidth,$newHeight]=self::scaledDimensions($width,$height,$maxPx,'LONGEST');
        if($newWidth===$width&&$newHeight===$height){if(!move_uploaded_file($source,$target)&&!rename($source,$target))throw new RuntimeException('The image could not be saved.');return[$width,$height];}
        self::writeScaled($source,$target,$mime,$width,$height,$newWidth,$newHeight,$settings);return[$newWidth,$newHeight];
    }

    private static function writeScaled(string $source,string $target,string $mime,int $width,int $height,int $targetWidth,int $targetHeight,array $settings): void
    {
        self::writeResampled($source,$target,$mime,0,0,$width,$height,$targetWidth,$targetHeight,$settings);
    }

    private static function writeResampled(string $source,string $target,string $mime,int $x,int $y,int $cropWidth,int $cropHeight,int $targetWidth,int $targetHeight,array $settings): void
    {
        if(!function_exists('imagecreatetruecolor'))throw new RuntimeException('This server needs PHP GD to resize images.');
        $load=['image/jpeg'=>'imagecreatefromjpeg','image/png'=>'imagecreatefrompng','image/webp'=>'imagecreatefromwebp'][$mime]??null;$save=['image/jpeg'=>'imagejpeg','image/png'=>'imagepng','image/webp'=>'imagewebp'][$mime]??null;if(!$load||!$save)throw new RuntimeException('This media format cannot be processed.');
        $src=@$load($source);if(!$src)throw new RuntimeException('The image could not be decoded.');$dst=imagecreatetruecolor($targetWidth,$targetHeight);
        if($mime!=='image/jpeg'){imagealphablending($dst,false);imagesavealpha($dst,true);$transparent=imagecolorallocatealpha($dst,0,0,0,127);imagefilledrectangle($dst,0,0,$targetWidth,$targetHeight,$transparent);}
        imagecopyresampled($dst,$src,0,0,$x,$y,$targetWidth,$targetHeight,$cropWidth,$cropHeight);
        $ok=$mime==='image/png'?$save($dst,$target,$settings['png_compression']):$save($dst,$target,$settings['jpeg_quality']);
        imagedestroy($src);imagedestroy($dst);if(!$ok)throw new RuntimeException('The processed image could not be saved.');
    }

    private static function createThumbnail(string $source,string $target,string $mime,int $width,int $height,int $x,int $y,int $size,int $targetSize,array $settings): void
    {
        self::writeResampled($source,$target.'.processing',$mime,$x,$y,$size,$size,$targetSize,$targetSize,$settings);
        if(!rename($target.'.processing',$target)){@unlink($target.'.processing');throw new RuntimeException('The thumbnail could not be saved.');}
    }

    private static function scaledDimensions(int $width,int $height,int $maxPx,string $mode): array
    {
        if($mode==='WIDTH')$scale=min(1,$maxPx/$width);
        elseif($mode==='HEIGHT')$scale=min(1,$maxPx/$height);
        else $scale=min(1,$maxPx/max($width,$height));
        return[max(1,(int)round($width*$scale)),max(1,(int)round($height*$scale))];
    }

    private static function postedInt(array $fields,string $key,int $min,int $max,int $default): int{return self::clampInt($fields[$key]??$default,$min,$max,$default);}
    private static function clampInt($value,int $min,int $max,int $default): int{$int=filter_var($value,FILTER_VALIDATE_INT);if($int===false)$int=$default;return max($min,min($max,(int)$int));}
    private static function dimensionMode(string $mode): string{$mode=strtoupper(trim($mode));return in_array($mode,['WIDTH','HEIGHT','LONGEST'],true)?$mode:'LONGEST';}
    private static function absolutePath(string $path): string{return dirname(__DIR__).'/'.ltrim(str_replace('\\','/',$path),'/');}
    private static function unlinkUpload(string $path): void{if(!str_starts_with($path,'public/uploads/'))return;$target=self::absolutePath($path);if(is_file($target))@unlink($target);}

    private static function cleanPageRowsForMedia(?int $mediaId): void
    {
        $q=db()->query('SELECT site_page_section_id,rows_json FROM site_page_sections WHERE rows_json IS NOT NULL AND rows_json <> ""');
        $update=db()->prepare('UPDATE site_page_sections SET rows_json=? WHERE site_page_section_id=?');
        foreach($q->fetchAll()as$row){
            $rows=json_decode((string)$row['rows_json'],true);
            if(!is_array($rows))continue;
            $changed=false;
            self::clearMediaIds($rows,$mediaId,$changed);
            if($changed)$update->execute([json_encode($rows,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),(int)$row['site_page_section_id']]);
        }
    }

    private static function clearMediaIds(array&$value,?int $mediaId,bool&$changed): void
    {
        foreach($value as$key=>&$item){
            if(is_array($item)){self::clearMediaIds($item,$mediaId,$changed);continue;}
            if(!is_string($key)||!str_ends_with($key,'media_id'))continue;
            $current=(int)$item;
            if($current>0&&($mediaId===null||$current===$mediaId)){$item=null;$changed=true;}
        }
        unset($item);
    }
}
