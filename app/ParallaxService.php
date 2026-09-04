<?php
final class ParallaxService
{
    private const MAX_WIDTH=1920;
    private const MAX_HEIGHT=1080;
    private const MAX_BYTES=20*1024*1024;

    public static function all(): array
    {
        $layers=db()->query('SELECT * FROM site_parallax_layers ORDER BY sort_order,parallax_layer_id')->fetchAll();
        foreach($layers as&$layer){
            $path=(string)$layer['image_path'];
            if(str_starts_with($path,'public/'))$layer['image_path']=substr($path,7);
        }
        unset($layer);
        return$layers;
    }

    public static function add(array $user,array $fields,array $file): int
    {
        $values=self::validateFields($fields);
        $stored=self::storeImage($file);
        try{
            $sort=(int)db()->query('SELECT COALESCE(MAX(sort_order),0)+10 FROM site_parallax_layers')->fetchColumn();
            $q=db()->prepare('INSERT INTO site_parallax_layers(label_text,image_path,mime_type,width_px,height_px,file_size_bytes,offset_left_px,offset_right_px,offset_top_px,offset_bottom_px,movement_percent,sort_order,updated_by_user_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $q->execute([$values['label_text'],$stored['path'],$stored['mime'],$stored['width'],$stored['height'],$stored['size'],$values['offset_left_px'],$values['offset_right_px'],$values['offset_top_px'],$values['offset_bottom_px'],$values['movement_percent'],$sort,(int)$user['user_id']]);
            return(int)db()->lastInsertId();
        }catch(Throwable $e){self::deleteUploadedFile($stored['path']);throw$e;}
    }

    public static function update(array $user,int $layerId,array $fields,?array $file=null): void
    {
        $layer=self::requireLayer($layerId);$values=self::validateFields($fields);$stored=null;
        if($file&&($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$stored=self::storeImage($file);
        try{
            if($stored){
                $q=db()->prepare('UPDATE site_parallax_layers SET label_text=?,image_path=?,mime_type=?,width_px=?,height_px=?,file_size_bytes=?,offset_left_px=?,offset_right_px=?,offset_top_px=?,offset_bottom_px=?,movement_percent=?,updated_by_user_id=? WHERE parallax_layer_id=?');
                $q->execute([$values['label_text'],$stored['path'],$stored['mime'],$stored['width'],$stored['height'],$stored['size'],$values['offset_left_px'],$values['offset_right_px'],$values['offset_top_px'],$values['offset_bottom_px'],$values['movement_percent'],(int)$user['user_id'],$layerId]);
                self::deleteUploadedFile((string)$layer['image_path']);
            }else{
                $q=db()->prepare('UPDATE site_parallax_layers SET label_text=?,offset_left_px=?,offset_right_px=?,offset_top_px=?,offset_bottom_px=?,movement_percent=?,updated_by_user_id=? WHERE parallax_layer_id=?');
                $q->execute([$values['label_text'],$values['offset_left_px'],$values['offset_right_px'],$values['offset_top_px'],$values['offset_bottom_px'],$values['movement_percent'],(int)$user['user_id'],$layerId]);
            }
        }catch(Throwable $e){if($stored)self::deleteUploadedFile($stored['path']);throw$e;}
    }

    public static function remove(int $layerId): void
    {
        $layer=self::requireLayer($layerId);
        db()->prepare('DELETE FROM site_parallax_layers WHERE parallax_layer_id=?')->execute([$layerId]);
        self::deleteUploadedFile((string)$layer['image_path']);
    }

    public static function reorder(string $order): void
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',explode(',',$order)),fn($id)=>$id>0)));
        $existing=array_map('intval',db()->query('SELECT parallax_layer_id FROM site_parallax_layers ORDER BY sort_order,parallax_layer_id')->fetchAll(PDO::FETCH_COLUMN));
        $sorted=$ids;$existingSorted=$existing;sort($sorted);sort($existingSorted);
        if($sorted!==$existingSorted)throw new RuntimeException('The layer order changed before it could be saved. Refresh and try again.');
        db()->beginTransaction();
        try{$q=db()->prepare('UPDATE site_parallax_layers SET sort_order=? WHERE parallax_layer_id=?');foreach($ids as$index=>$id)$q->execute([($index+1)*10,$id]);db()->commit();}
        catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw$e;}
    }

    private static function requireLayer(int $layerId): array
    {
        $q=db()->prepare('SELECT * FROM site_parallax_layers WHERE parallax_layer_id=?');$q->execute([$layerId]);$layer=$q->fetch();
        if(!$layer)throw new RuntimeException('That parallax layer no longer exists.');return$layer;
    }

    private static function validateFields(array $fields): array
    {
        $label=trim((string)($fields['label_text']??''));if($label==='')throw new RuntimeException('Enter a layer name.');if(mb_strlen($label)>120)throw new RuntimeException('Layer names may not exceed 120 characters.');
        $values=['label_text'=>$label];
        foreach(['offset_left_px','offset_right_px','offset_top_px','offset_bottom_px']as$key){$value=filter_var($fields[$key]??0,FILTER_VALIDATE_INT);if($value===false||$value < -1920||$value > 1920)throw new RuntimeException('Offsets must be whole pixels between -1920 and 1920.');$values[$key]=(int)$value;}
        $movement=filter_var($fields['movement_percent']??0,FILTER_VALIDATE_FLOAT);if($movement===false||$movement < -100||$movement > 100)throw new RuntimeException('Movement must be between -100% and 100%.');$values['movement_percent']=round((float)$movement,2);
        return$values;
    }

    private static function storeImage(array $file): array
    {
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Choose a PNG or JPG image to upload.');
        $inputSize=(int)($file['size']??0);if($inputSize<1||$inputSize>self::MAX_BYTES)throw new RuntimeException('Source images must be 20 MB or smaller.');
        $tmp=(string)($file['tmp_name']??'');$info=@getimagesize($tmp);$mime=(string)($info['mime']??'');$width=(int)($info[0]??0);$height=(int)($info[1]??0);
        $extensions=['image/jpeg'=>'jpg','image/png'=>'png'];if(!isset($extensions[$mime])||$width<1||$height<1)throw new RuntimeException('Upload a valid PNG or JPG image.');
        if($width*$height>80000000)throw new RuntimeException('The source image has too many pixels to process safely.');
        $directory=dirname(__DIR__).'/public/uploads/parallax';if(!is_dir($directory)&&!mkdir($directory,0775,true)&&!is_dir($directory))throw new RuntimeException('The parallax upload directory could not be created.');
        $filename='layer-'.bin2hex(random_bytes(12)).'.'.$extensions[$mime];$target=$directory.'/'.$filename;
        [$finalWidth,$finalHeight]=self::storeOptimized($tmp,$target,$mime,$width,$height);
        return['path'=>'public/uploads/parallax/'.$filename,'mime'=>$mime,'width'=>$finalWidth,'height'=>$finalHeight,'size'=>(int)filesize($target)];
    }

    private static function storeOptimized(string $source,string $target,string $mime,int $width,int $height): array
    {
        $scale=min(1,self::MAX_WIDTH/$width,self::MAX_HEIGHT/$height);$newWidth=max(1,(int)round($width*$scale));$newHeight=max(1,(int)round($height*$scale));
        if(!function_exists('imagecreatetruecolor')){
            if($scale<1)throw new RuntimeException('This server needs PHP GD to resize images above 1920×1080.');
            if(!move_uploaded_file($source,$target)&&!rename($source,$target))throw new RuntimeException('The image could not be saved.');return[$width,$height];
        }
        $load=$mime==='image/png'?'imagecreatefrompng':'imagecreatefromjpeg';$src=@$load($source);if(!$src)throw new RuntimeException('The image could not be decoded.');$dst=imagecreatetruecolor($newWidth,$newHeight);
        if($mime==='image/png'){imagealphablending($dst,false);imagesavealpha($dst,true);$transparent=imagecolorallocatealpha($dst,0,0,0,127);imagefilledrectangle($dst,0,0,$newWidth,$newHeight,$transparent);}
        imagecopyresampled($dst,$src,0,0,0,0,$newWidth,$newHeight,$width,$height);
        $ok=$mime==='image/png'?imagepng($dst,$target,7):imagejpeg($dst,$target,86);imagedestroy($src);imagedestroy($dst);
        if(!$ok)throw new RuntimeException('The optimized image could not be saved.');return[$newWidth,$newHeight];
    }

    private static function deleteUploadedFile(string $path): void
    {
        if(!str_starts_with($path,'public/uploads/parallax/'))return;
        $name=basename($path);$target=dirname(__DIR__).'/public/uploads/parallax/'.$name;if(is_file($target))@unlink($target);
    }
}
