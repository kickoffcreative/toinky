<?php
$root=dirname(__DIR__);require_once $root.'/app/bootstrap.php';require_once $root.'/app/MigrationService.php';MigrationService::run();require_once $root.'/app/AuthService.php';require_once $root.'/app/AiConnectionService.php';AuthService::boot();AuthService::requireLogin();
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
try{echo json_encode(['ok'=>true]+AiConnectionService::status(),JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);}
catch(Throwable $e){http_response_code(503);echo json_encode(['ok'=>false,'online'=>false,'message'=>'Inky cannot check the AI service right now.']);}

