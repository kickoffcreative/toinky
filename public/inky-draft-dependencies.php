<?php
declare(strict_types=1);

ini_set('display_errors','0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try{
    $root=dirname(__DIR__);
    require_once $root.'/app/bootstrap.php';
    require_once $root.'/app/MigrationService.php';
    MigrationService::run();
    require_once $root.'/app/AuthService.php';
    require_once $root.'/app/StoryAuthoringService.php';
    require_once $root.'/app/InkyGuideService.php';
    AuthService::boot();
    $writer=AuthService::currentUser();
    if(!$writer)throw new RuntimeException('Your Story Writer session expired. Refresh the page and try again.');
    if((int)$writer['is_admin']!==1&&!AuthService::hasRole((int)$writer['user_id'],'WRITER'))throw new RuntimeException('Story Writer access is required.');
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new RuntimeException('POST is required.');
    AuthService::requireCsrf();
    $plotId=(int)($_POST['plot_id']??0);
    $progress=InkyGuideService::loadProgress($writer,$plotId);
    $accepted=(array)($progress['accepted_proposals']??[]);
    $draftSnapshot=StoryAuthoringService::inkyDraftSnapshot($plotId,$accepted);
    echo json_encode(['ok'=>true,'draft_dependencies'=>$draftSnapshot['dependencies'],'draft_entries'=>$draftSnapshot['entries']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
}catch(Throwable$e){
    error_log('Inky draft dependency refresh failed ['.get_class($e).']: '.$e->getMessage());
    http_response_code(422);
    echo json_encode(['ok'=>false,'message'=>'The draft dependency information could not be refreshed.'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
}
