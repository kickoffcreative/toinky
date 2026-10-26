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
            if((int)($layer['media_id']??0)>0)$layer['image_path']='theme-media.php?id='.(int)$layer['media_id'];
            elseif(str_starts_with($path,'public/')&&!str_starts_with($path,'public/uploads/'))$layer['image_path']=substr($path,7);
        }
        unset($layer);
        return$layers;
    }

    public static function add(array $user,array $fields,array $file): int
    {
        $values=self::validateFields($fields);
        $stored=self::storeImage($user,$fields,$file);
        try{
            $sort=(int)db()->query('SELECT COALESCE(MAX(sort_order),0)+10 FROM site_parallax_layers')->fetchColumn();
            $q=db()->prepare('INSERT INTO site_parallax_layers(label_text,image_path,media_id,mime_type,width_px,height_px,file_size_bytes,offset_left_px,offset_right_px,offset_top_px,offset_bottom_px,movement_percent,sort_order,updated_by_user_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $q->execute([$values['label_text'],$stored['path'],$stored['media_id'],$stored['mime'],$stored['width'],$stored['height'],$stored['size'],$values['offset_left_px'],$values['offset_right_px'],$values['offset_top_px'],$values['offset_bottom_px'],$values['movement_percent'],$sort,(int)$user['user_id']]);
            return(int)db()->lastInsertId();
        }catch(Throwable $e){if(!empty($stored['media_id']))MediaLibraryService::delete(['user_id'=>(int)$user['user_id'],'is_admin'=>1],(int)$stored['media_id']);else self::deleteUploadedFile($stored['path']);throw$e;}
    }

    public static function update(array $user,int $layerId,array $fields,?array $file=null): void
    {
        $layer=self::requireLayer($layerId);$values=self::validateFields($fields);$stored=null;
        if($file&&($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$stored=self::storeImage($user,$fields,$file);
        try{
            if($stored){
                $q=db()->prepare('UPDATE site_parallax_layers SET label_text=?,image_path=?,media_id=?,mime_type=?,width_px=?,height_px=?,file_size_bytes=?,offset_left_px=?,offset_right_px=?,offset_top_px=?,offset_bottom_px=?,movement_percent=?,updated_by_user_id=? WHERE parallax_layer_id=?');
                $q->execute([$values['label_text'],$stored['path'],$stored['media_id'],$stored['mime'],$stored['width'],$stored['height'],$stored['size'],$values['offset_left_px'],$values['offset_right_px'],$values['offset_top_px'],$values['offset_bottom_px'],$values['movement_percent'],(int)$user['user_id'],$layerId]);
                self::deleteLayerMedia($user,$layer);
            }else{
                $q=db()->prepare('UPDATE site_parallax_layers SET label_text=?,offset_left_px=?,offset_right_px=?,offset_top_px=?,offset_bottom_px=?,movement_percent=?,updated_by_user_id=? WHERE parallax_layer_id=?');
                $q->execute([$values['label_text'],$values['offset_left_px'],$values['offset_right_px'],$values['offset_top_px'],$values['offset_bottom_px'],$values['movement_percent'],(int)$user['user_id'],$layerId]);
            }
        }catch(Throwable $e){if($stored){if(!empty($stored['media_id']))MediaLibraryService::delete(['user_id'=>(int)$user['user_id'],'is_admin'=>1],(int)$stored['media_id']);else self::deleteUploadedFile($stored['path']);}throw$e;}
    }

    public static function remove(int $layerId): void
    {
        $layer=self::requireLayer($layerId);
        db()->prepare('DELETE FROM site_parallax_layers WHERE parallax_layer_id=?')->execute([$layerId]);
        self::deleteLayerMedia(['user_id'=>(int)($layer['updated_by_user_id']??0),'is_admin'=>1],$layer);
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

    private static function storeImage(array $user,array $fields,array $file): array
    {
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Choose a PNG or JPG image to upload.');
        $tmp=(string)($file['tmp_name']??'');$info=@getimagesize($tmp);$mime=(string)($info['mime']??'');$width=(int)($info[0]??0);$height=(int)($info[1]??0);
        if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)||$width<1||$height<1)throw new RuntimeException('Upload a valid JPEG, PNG, or WebP image.');
        $title=trim((string)($fields['label_text']??'Parallax layer'));
        $mediaId=MediaLibraryService::upload($user,['media_type'=>'PARALLAX','title_text'=>$title,'alt_text'=>$title.' parallax layer'],$file);
        $media=MediaLibraryService::requireVisible(['user_id'=>(int)$user['user_id'],'is_admin'=>1],$mediaId);
        return['media_id'=>$mediaId,'path'=>$media['file_path'],'mime'=>$media['mime_type'],'width'=>(int)$media['width_px'],'height'=>(int)$media['height_px'],'size'=>(int)$media['file_size_bytes']];
    }

    private static function deleteUploadedFile(string $path): void
    {
        if(str_starts_with($path,'public/uploads/parallax/')){$name=basename($path);$target=dirname(__DIR__).'/public/uploads/parallax/'.$name;if(is_file($target))@unlink($target);return;}
        if(str_starts_with($path,'public/uploads/media-library/')){$target=dirname(__DIR__).'/'.ltrim(str_replace('\\','/',$path),'/');if(is_file($target))@unlink($target);}
    }

    private static function deleteLayerMedia(array $user,array $layer): void
    {
        $mediaId=(int)($layer['media_id']??0);
        if($mediaId>0){try{MediaLibraryService::delete(['user_id'=>(int)($user['user_id']??0),'is_admin'=>1],$mediaId);return;}catch(Throwable $ignored){}}
        self::deleteUploadedFile((string)$layer['image_path']);
    }
}
