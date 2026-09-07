<?php
$traceFile=dirname(__DIR__).'/../inky_request_trace.log';$trace=static function(string$stage,array$context=[])use($traceFile):void{@file_put_contents($traceFile,gmdate('c').' '.json_encode(['endpoint'=>'inky-action','stage'=>$stage,'context'=>$context],JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE).PHP_EOL,FILE_APPEND|LOCK_EX);};$trace('start',['method'=>$_SERVER['REQUEST_METHOD']??'','action'=>(string)($_POST['action']??''),'has_csrf'=>isset($_POST['csrf_token'])]);
try{$root=dirname(__DIR__);require_once $root.'/app/bootstrap.php';require_once $root.'/app/MigrationService.php';MigrationService::run();require_once $root.'/app/AuthService.php';require_once $root.'/app/InkyCowriterService.php';AuthService::boot();$writer=AuthService::requireWriter();header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new RuntimeException('POST is required.');
    AuthService::requireCsrf();
    $suggestionContext=json_decode((string)($_POST['suggestion_context']??''),true);
    if(!is_array($suggestionContext))$suggestionContext=[];
    $result=InkyCowriterService::run($writer,(int)($_POST['plot_id']??0),trim((string)($_POST['action']??'')),['target_type'=>trim((string)($_POST['target_type']??'')),'target_code'=>trim((string)($_POST['target_code']??'')),'suggestion_context'=>$suggestionContext]);
    $trace('success',['user_id'=>(int)$writer['user_id']]);echo json_encode(['ok'=>true]+$result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
}
catch(Throwable $e){
    $detail=$e->getMessage();
    $trace('failure',['class'=>get_class($e),'message'=>$detail]);
    error_log('Inky action failed: '.$detail);
    $normalized=strtolower($detail);
    $message=str_contains($normalized,'timed out')||str_contains($normalized,'timeout')?'Inky’s full review took too long. Please try again; the review now has a longer processing window.':(str_contains($normalized,'json')||str_contains($normalized,'structured response')||str_contains($normalized,'did not include')?'Inky received an incomplete review response. Please try the review once more.':(preg_match('/ai http (404|408|429|5[0-9]{2})/i',$detail)?'Inky’s review service is temporarily unavailable. Please try again shortly.':'Inky could not finish that review. The failure was recorded for diagnosis.'));
    http_response_code(422);
    echo json_encode(['ok'=>false,'message'=>$message],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
}
