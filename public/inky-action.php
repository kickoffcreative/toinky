<?php
try{$root=dirname(__DIR__);require_once $root.'/app/bootstrap.php';require_once $root.'/app/AuthService.php';require_once $root.'/app/InkyCowriterService.php';AuthService::boot();$writer=AuthService::requireWriter();header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new UserFacingException('POST is required.');
    AuthService::requireCsrf();
    RateLimitService::ai((int)$writer['user_id']);
    $suggestionContext=json_decode((string)($_POST['suggestion_context']??''),true);
    if(!is_array($suggestionContext))$suggestionContext=[];
    $result=InkyCowriterService::run($writer,(int)($_POST['plot_id']??0),trim((string)($_POST['action']??'')),['target_type'=>trim((string)($_POST['target_type']??'')),'target_code'=>trim((string)($_POST['target_code']??'')),'suggestion_context'=>$suggestionContext]);
    echo json_encode(['ok'=>true]+$result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
}
catch(Throwable $e){
    $message=public_error($e);
    http_response_code($e instanceof RateLimitException?429:($e instanceof UserFacingException?422:500));
    echo json_encode(['ok'=>false,'message'=>$message],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
}
