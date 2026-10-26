<?php
$root=dirname(__DIR__);require_once $root.'/app/bootstrap.php';require_once $root.'/app/AuthService.php';require_once $root.'/app/UserProfileService.php';AuthService::boot();$user=AuthService::requireLogin();
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
try{if($_SERVER['REQUEST_METHOD']!=='POST')throw new RuntimeException('POST is required.');AuthService::requireCsrf();$anchor=UserProfileService::saveInkyPosition((int)$user['user_id'],(string)($_POST['anchor_position']??''));echo json_encode(['ok'=>true,'anchor_position'=>$anchor]);}
catch(Throwable $e){http_response_code(422);echo json_encode(['ok'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);}

