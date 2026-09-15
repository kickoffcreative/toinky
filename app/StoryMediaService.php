<?php
require_once __DIR__.'/GenericStateService.php';
require_once __DIR__.'/MediaLibraryService.php';

final class StoryMediaService
{
    public static function canEdit(array $user,int $plotId): bool
    {
        if((int)($user['is_admin']??0)===1)return true;
        if(!AuthService::hasRole((int)$user['user_id'],'WRITER'))return false;
        $q=db()->prepare('SELECT 1 FROM plots WHERE plot_id=? AND author_user_id=?');
        $q->execute([$plotId,$user['user_id']]);
        return(bool)$q->fetchColumn();
    }

    public static function upload(array $user,int $plotId,array $fields,array $file): int
    {
        if(!self::canEdit($user,$plotId))throw new RuntimeException('You may only add artwork to stories you own.');
        $placement=strtoupper(trim((string)($fields['placement']??'')));
        $placements=['PRIMARY','CHARACTER','OPENING','SCENE','TURNING_POINT','OUTCOME','ENDING','OTHER'];
        if(!in_array($placement,$placements,true))throw new RuntimeException('Choose a valid image placement.');
        $alt=trim((string)($fields['alt_text']??''));if($alt==='')throw new RuntimeException('Alternative text is required.');
        $code=strtoupper(preg_replace('/[^A-Z0-9]+/','_',trim((string)($fields['asset_code']??''))));$code=trim($code,'_');
        if($code==='')$code=$placement.'_'.strtoupper(bin2hex(random_bytes(4)));
        $title=self::nullable($fields['title_text']??null)?:str_replace('_',' ',ucwords(strtolower($code),'_'));
        $mediaId=MediaLibraryService::upload($user,['media_type'=>$placement,'title_text'=>$title,'alt_text'=>$alt],$file);
        try{return self::attachFromLibrary($user,$plotId,array_merge($fields,['library_media_id'=>$mediaId,'asset_code'=>$code,'placement'=>$placement,'title_text'=>$title,'alt_text'=>$alt]));}
        catch(Throwable $e){try{MediaLibraryService::delete($user,$mediaId);}catch(Throwable $ignored){}throw$e;}
    }

    public static function attachFromLibrary(array $user,int $plotId,array $fields): int
    {
        if(!self::canEdit($user,$plotId))throw new RuntimeException('You may only add artwork to stories you own.');
        $placement=strtoupper(trim((string)($fields['placement']??'')));$placements=['PRIMARY','CHARACTER','OPENING','SCENE','TURNING_POINT','OUTCOME','ENDING','OTHER'];if(!in_array($placement,$placements,true))throw new RuntimeException('Choose a valid image placement.');
        self::validateStoryValueCondition($plotId,$fields);
        $media=MediaLibraryService::requireVisible($user,(int)($fields['library_media_id']??0),$placement==='OTHER'?null:$placement);
        $code=strtoupper(preg_replace('/[^A-Z0-9]+/','_',trim((string)($fields['asset_code']??''))));$code=trim($code,'_');if($code==='')$code=$placement.'_'.strtoupper(bin2hex(random_bytes(4)));
        $values=[$plotId,$user['user_id'],$media['media_id'],$code,$placement,self::nullable($fields['role_code']??null),self::nullable($fields['event_code']??null),self::nullableInt($fields['scene_second']??null),self::nullable($fields['condition_state_key']??null),self::nullable($fields['condition_operator']??null),self::nullable($fields['condition_value']??null),self::nullable($fields['title_text']??null)?:$media['title_text'],$media['alt_text'],self::nullable($fields['caption_text']??null),$media['file_path'],$media['mime_type'],$media['file_size_bytes'],(int)($fields['sort_order']??100)];
        db()->beginTransaction();try{if($placement==='PRIMARY')db()->prepare('UPDATE plot_media_assets SET active=0 WHERE plot_id=? AND placement="PRIMARY"')->execute([$plotId]);$q=db()->prepare('INSERT INTO plot_media_assets(plot_id,uploaded_by_user_id,library_media_id,asset_code,placement,role_code,event_code,scene_second,condition_state_key,condition_operator,condition_value,title_text,alt_text,caption_text,file_path,mime_type,file_size_bytes,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE uploaded_by_user_id=VALUES(uploaded_by_user_id),library_media_id=VALUES(library_media_id),placement=VALUES(placement),role_code=VALUES(role_code),event_code=VALUES(event_code),scene_second=VALUES(scene_second),condition_state_key=VALUES(condition_state_key),condition_operator=VALUES(condition_operator),condition_value=VALUES(condition_value),title_text=VALUES(title_text),alt_text=VALUES(alt_text),caption_text=VALUES(caption_text),file_path=VALUES(file_path),mime_type=VALUES(mime_type),file_size_bytes=VALUES(file_size_bytes),sort_order=VALUES(sort_order),active=1');$q->execute($values);$id=(int)db()->lastInsertId();if($id<1){$q=db()->prepare('SELECT media_asset_id FROM plot_media_assets WHERE plot_id=? AND asset_code=?');$q->execute([$plotId,$code]);$id=(int)$q->fetchColumn();}db()->commit();return$id;}catch(Throwable$e){if(db()->inTransaction())db()->rollBack();throw$e;}
    }

    public static function deactivate(array $user,int $assetId): void
    {
        $q=db()->prepare('SELECT plot_id FROM plot_media_assets WHERE media_asset_id=?');$q->execute([$assetId]);$plotId=(int)$q->fetchColumn();
        if($plotId<1||!self::canEdit($user,$plotId))throw new RuntimeException('You may not change that image.');
        db()->prepare('UPDATE plot_media_assets SET active=0 WHERE media_asset_id=?')->execute([$assetId]);
    }

    public static function updateAttachment(array $user,int $plotId,int $assetId,array $fields): void
    {
        if(!self::canEdit($user,$plotId))throw new RuntimeException('You may only change artwork in stories you own.');
        $q=db()->prepare('SELECT * FROM plot_media_assets WHERE media_asset_id=? AND plot_id=?');$q->execute([$assetId,$plotId]);$current=$q->fetch();if(!$current)throw new RuntimeException('Story artwork not found.');
        $placement=strtoupper(trim((string)($fields['placement']??$current['placement'])));$placements=['PRIMARY','CHARACTER','OPENING','SCENE','TURNING_POINT','OUTCOME','ENDING','OTHER'];if(!in_array($placement,$placements,true))throw new RuntimeException('Choose a valid image placement.');
        self::validateStoryValueCondition($plotId,$fields);$mediaId=(int)($fields['library_media_id']??0);$media=null;if($mediaId>0)$media=MediaLibraryService::requireVisible($user,$mediaId,$placement==='OTHER'?null:$placement);
        $values=[$media?(int)$user['user_id']:(int)$current['uploaded_by_user_id'],$mediaId?:$current['library_media_id'],$placement,self::nullable($fields['role_code']??null),self::nullable($fields['event_code']??null),self::nullableInt($fields['scene_second']??null),self::nullable($fields['condition_state_key']??null),self::nullable($fields['condition_operator']??null),self::nullable($fields['condition_value']??null),self::nullable($fields['title_text']??null)?:($media['title_text']??$current['title_text']),$media['alt_text']??$current['alt_text'],self::nullable($fields['caption_text']??null),$media['file_path']??$current['file_path'],$media['mime_type']??$current['mime_type'],$media['file_size_bytes']??$current['file_size_bytes'],(int)($fields['sort_order']??100),$assetId,$plotId];
        db()->beginTransaction();try{if($placement==='PRIMARY')db()->prepare('UPDATE plot_media_assets SET active=0 WHERE plot_id=? AND placement="PRIMARY" AND media_asset_id<>?')->execute([$plotId,$assetId]);db()->prepare('UPDATE plot_media_assets SET uploaded_by_user_id=?,library_media_id=?,placement=?,role_code=?,event_code=?,scene_second=?,condition_state_key=?,condition_operator=?,condition_value=?,title_text=?,alt_text=?,caption_text=?,file_path=?,mime_type=?,file_size_bytes=?,sort_order=?,active=1 WHERE media_asset_id=? AND plot_id=?')->execute($values);db()->commit();}catch(Throwable$e){if(db()->inTransaction())db()->rollBack();throw$e;}
    }

    public static function setCharacterImage(array $user,int $plotId,string $roleCode,$mediaChoice): void
    {
        if(!self::canEdit($user,$plotId))throw new RuntimeException('You may only change character images in stories you own.');
        $roleCode=strtoupper(trim($roleCode));$q=db()->prepare('SELECT 1 FROM plot_roles WHERE plot_id=? AND role_code=?');$q->execute([$plotId,$roleCode]);if(!$q->fetchColumn())throw new RuntimeException('Save the character before assigning an image.');
        if($mediaChoice==='__KEEP__')return;
        db()->prepare('UPDATE plot_media_assets SET active=0 WHERE plot_id=? AND placement="CHARACTER" AND role_code=?')->execute([$plotId,$roleCode]);
        if($mediaChoice==='__REMOVE__'||$mediaChoice===''||$mediaChoice===null)return;
        self::attachFromLibrary($user,$plotId,['placement'=>'CHARACTER','role_code'=>$roleCode,'library_media_id'=>(int)$mediaChoice,'asset_code'=>'CHARACTER_'.$roleCode,'sort_order'=>10]);
    }

    public static function forPlot(int $plotId): array
    {
        $q=db()->prepare('SELECT m.*,u.display_name uploader_name FROM plot_media_assets m JOIN users u ON u.user_id=m.uploaded_by_user_id WHERE m.plot_id=? ORDER BY m.placement,m.sort_order,m.media_asset_id');
        $q->execute([$plotId]);return$q->fetchAll();
    }

    public static function forSimulation(int $simulationId,?int $characterId=null): array
    {
        $q=db()->prepare('SELECT s.plot_id,pr.role_code FROM simulations s LEFT JOIN characters c ON c.simulation_id=s.simulation_id AND c.character_id=? LEFT JOIN plot_roles pr ON pr.plot_role_id=c.plot_role_id WHERE s.simulation_id=?');
        $q->execute([$characterId,$simulationId]);$context=$q->fetch();if(!$context)return[];
        $q=db()->prepare('SELECT m.*,COALESCE(m.scene_second,pe.event_second) resolved_scene_second FROM plot_media_assets m LEFT JOIN plot_world_events pe ON pe.plot_id=m.plot_id AND pe.event_code=m.event_code WHERE m.plot_id=? AND m.active=1 AND (m.role_code IS NULL OR m.role_code="" OR m.role_code=?) ORDER BY m.sort_order,m.media_asset_id');
        $q->execute([$context['plot_id'],$context['role_code']??'']);$out=[];
        foreach($q->fetchAll()as$asset)if(self::conditionMatches($simulationId,$asset))$out[]=$asset;
        return$out;
    }

    public static function characterImagesForPlot(int $plotId): array
    {
        $q=db()->prepare('SELECT m.role_code,COALESCE(l.thumbnail_path,m.file_path) file_path,m.alt_text,m.title_text FROM plot_media_assets m LEFT JOIN writer_media_assets l ON l.media_id=m.library_media_id AND l.active=1 WHERE m.plot_id=? AND m.placement="CHARACTER" AND m.active=1 ORDER BY m.sort_order,m.media_asset_id');$q->execute([$plotId]);$images=[];foreach($q->fetchAll()as$image)if(!isset($images[$image['role_code']]))$images[$image['role_code']]=$image;return$images;
    }

    public static function characterImagesForSimulation(int $simulationId): array
    {
        $q=db()->prepare('SELECT plot_id FROM simulations WHERE simulation_id=?');$q->execute([$simulationId]);$plotId=(int)$q->fetchColumn();return$plotId?self::characterImagesForPlot($plotId):[];
    }

    public static function characterImagesByCharacterForSimulation(int $simulationId): array
    {
        $q=db()->prepare('SELECT c.character_id,COALESCE(l.thumbnail_path,m.file_path) file_path,m.alt_text,m.title_text FROM characters c JOIN plot_roles pr ON pr.plot_role_id=c.plot_role_id JOIN plot_media_assets m ON m.plot_id=pr.plot_id AND m.role_code=pr.role_code AND m.placement="CHARACTER" AND m.active=1 LEFT JOIN writer_media_assets l ON l.media_id=m.library_media_id AND l.active=1 WHERE c.simulation_id=? ORDER BY m.sort_order,m.media_asset_id');$q->execute([$simulationId]);$images=[];foreach($q->fetchAll()as$image)if(!isset($images[(int)$image['character_id']]))$images[(int)$image['character_id']]=$image;return$images;
    }

    private static function conditionMatches(int $simulationId,array $asset): bool
    {
        $key=trim((string)($asset['condition_state_key']??''));if($key==='')return true;
        $state=GenericStateService::getByKey($simulationId,$key);$op=(string)($asset['condition_operator']??'EQ');$want=(string)($asset['condition_value']??'');
        if($op==='EXISTS')return(bool)$state['exists'];if($op==='NOT_EXISTS')return!$state['exists'];if(!$state['exists'])return false;
        $actual=(string)$state['value'];$values=array_map('trim',explode(',',$want));
        if($op==='NEQ')return$actual!==$want;if($op==='IN')return in_array($actual,$values,true);if($op==='NOT_IN')return!in_array($actual,$values,true);return$actual===$want;
    }

    private static function nullable($value): ?string {$value=trim((string)$value);return$value===''?null:$value;}
    private static function nullableInt($value): ?int {$value=trim((string)$value);return$value===''?null:max(0,(int)$value);}
    private static function validateStoryValueCondition(int$plotId,array$fields):void{$key=trim((string)($fields['condition_state_key']??''));$operator=trim((string)($fields['condition_operator']??''));if($key===''&&$operator==='')return;if($key===''||$operator==='')throw new RuntimeException('Artwork conditions need both a Story Value and an operator.');$q=db()->prepare('SELECT label_text,value_type,allowed_values_text FROM plot_state_definitions WHERE plot_id=? AND target_code=? LIMIT 1');$q->execute([$plotId,$key]);$definition=$q->fetch();if(!$definition)throw new RuntimeException('Choose an artwork Story Value managed in Story Framework.');if(in_array($operator,['EXISTS','NOT_EXISTS'],true))return;$value=trim((string)($fields['condition_value']??''));if($value==='')throw new RuntimeException($definition['label_text'].' artwork condition needs a value.');if($definition['value_type']==='NUMERIC'&&!is_numeric($value))throw new RuntimeException($definition['label_text'].' artwork condition needs a numerical value.');if($definition['value_type']!=='NUMERIC'&&in_array($operator,['LT','LTE','GT','GTE'],true))throw new RuntimeException($operator.' is only available for Numerical Story Values.');if($definition['value_type']==='PRESET'){$allowed=preg_split('/\r?\n/',trim((string)$definition['allowed_values_text']))?:[];foreach(array_map('trim',explode(',',$value))as$item)if(!in_array($item,$allowed,true))throw new RuntimeException('Choose an allowed Preset Value for '.$definition['label_text'].'.');}}
}
