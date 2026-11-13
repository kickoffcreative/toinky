<?php
$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/AuthService.php';
AuthService::boot();AuthService::requireAdmin();
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){http_response_code(405);header('Allow: POST');exit('POST is required.');}
try{AuthService::requireCsrf();}catch(Throwable $e){http_response_code(403);exit('Your preview session expired. Refresh the builder.');}
// Only this authenticated, CSRF-checked endpoint may be framed by our own origin.
// Force the existing preview action so this route cannot dispatch account/game actions.
$_POST['action']='builder_preview';
SecurityHeaders::allowSameOriginPreview();
require __DIR__.'/index.php';
