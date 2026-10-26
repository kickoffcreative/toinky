<?php
$root=dirname(__DIR__);require_once $root.'/app/bootstrap.php';require_once $root.'/app/AuthService.php';require_once $root.'/app/AiConnectionService.php';AuthService::boot();AuthService::requireLogin();
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
try{$status=AiConnectionService::status();echo json_encode(['ok'=>true,'online'=>(bool)($status['online']??false),'message'=>!empty($status['online'])?'Inky is ready.':'Inky is resting right now.'],JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);}
catch(Throwable $e){http_response_code(503);echo json_encode(['ok'=>false,'online'=>false,'message'=>'Inky is resting right now.']);}

