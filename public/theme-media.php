<?php
$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/AuthService.php';
AuthService::boot();
$viewer=AuthService::currentUser();
$viewerId=(int)($viewer['user_id']??0);
$viewerIsAdmin=(int)($viewer['is_admin']??0)===1;

$mediaId=(int)($_GET['id']??0);
$path=trim((string)($_GET['path']??''));
if($mediaId<1&&$path===''){http_response_code(404);exit;}

if($mediaId>0){
    $q=db()->prepare("SELECT m.file_path,m.thumbnail_path,m.mime_type FROM writer_media_assets m WHERE m.media_id=? AND m.active=1 AND m.mime_type IN ('image/jpeg','image/png','image/webp') AND (m.media_type IN ('BACKGROUND','DIVIDER','CARD') OR m.uploaded_by_user_id=? OR ?=1 OR EXISTS(SELECT 1 FROM site_page_sections s JOIN site_pages p ON p.site_page_id=s.site_page_id AND p.active=1 WHERE s.active=1 AND (s.image_media_id=m.media_id OR s.background_media_id=m.media_id OR s.rows_json LIKE CONCAT('%\"image_media_id\":',m.media_id,'%') OR s.rows_json LIKE CONCAT('%\"background_media_id\":',m.media_id,'%') OR s.rows_json LIKE CONCAT('%\"column_background_media_id\":',m.media_id,'%') OR s.rows_json LIKE CONCAT('%\"image_media_id\": ',m.media_id,'%') OR s.rows_json LIKE CONCAT('%\"background_media_id\": ',m.media_id,'%') OR s.rows_json LIKE CONCAT('%\"column_background_media_id\": ',m.media_id,'%'))))");
    $q->execute([$mediaId,$viewerId,$viewerIsAdmin?1:0]);
}else{
    $q=db()->prepare('SELECT m.file_path,m.thumbnail_path,m.mime_type,m.uploaded_by_user_id FROM writer_media_assets m WHERE (m.file_path=? OR m.thumbnail_path=?) AND m.active=1 AND (EXISTS(SELECT 1 FROM site_page_sections s JOIN site_pages p ON p.site_page_id=s.site_page_id AND p.active=1 WHERE s.active=1 AND (s.image_media_id=m.media_id OR s.background_media_id=m.media_id)) OR EXISTS(SELECT 1 FROM genre_themes t JOIN story_genres g ON g.genre_id=t.genre_id AND g.active=1 WHERE t.background_media_id=m.media_id OR t.divider_media_id=m.media_id OR t.card_media_id=m.media_id) OR EXISTS(SELECT 1 FROM plot_media_assets pm JOIN plots p ON p.plot_id=pm.plot_id AND p.status="active" WHERE pm.active=1 AND pm.library_media_id=m.media_id) OR m.uploaded_by_user_id=? OR ?=1)');
    $q->execute([$path,$path,$viewerId,$viewerIsAdmin?1:0]);
}
$media=$q->fetch();
if(!$media){http_response_code(404);exit;}

$stored=ltrim(str_replace('\\','/',(string)($mediaId>0?$media['file_path']:$path)),'/');
$file=realpath($root.'/'.$stored);
$allowedRoots=[realpath($root.'/public/uploads'),realpath($root.'/public/assets')];
$allowed=false;
if($file&&is_file($file)){
    foreach($allowedRoots as$allowedRoot){
        if($allowedRoot&&str_starts_with($file,$allowedRoot.DIRECTORY_SEPARATOR)){$allowed=true;break;}
    }
}
if(!$allowed){http_response_code(404);exit;}

$mime=(string)$media['mime_type'];
if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)){$mime='application/octet-stream';}
header('Content-Type: '.$mime);
header('Content-Length: '.filesize($file));
header('Cache-Control: public, max-age=3600');
header('X-Content-Type-Options: nosniff');
readfile($file);
