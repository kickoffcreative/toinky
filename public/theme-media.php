<?php
$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';

$mediaId=(int)($_GET['id']??0);
if($mediaId<1){http_response_code(404);exit;}

$q=db()->prepare("SELECT file_path,mime_type FROM writer_media_assets WHERE media_id=? AND active=1 AND media_type IN ('BACKGROUND','DIVIDER','CARD')");
$q->execute([$mediaId]);
$media=$q->fetch();
if(!$media){http_response_code(404);exit;}

$stored=ltrim(str_replace('\\','/',(string)$media['file_path']),'/');
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
