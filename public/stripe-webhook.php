<?php
$root=dirname(__DIR__);require_once $root.'/app/bootstrap.php';require_once $root.'/app/StripeService.php';require_once $root.'/app/CommerceService.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);echo json_encode(['error'=>'Method not allowed.']);exit;}
$payload=(string)file_get_contents('php://input');
$signature=(string)($_SERVER['HTTP_STRIPE_SIGNATURE']??'');
$secret=StripeService::webhookSecret();
if($secret===''){http_response_code(500);echo json_encode(['error'=>'Webhook is not configured.']);exit;}
if(!StripeService::verifySignature($payload,$signature,$secret)){http_response_code(400);echo json_encode(['error'=>'Invalid signature.']);exit;}
$event=json_decode($payload,true);
if(!is_array($event)||!isset($event['type'])){http_response_code(400);echo json_encode(['error'=>'Invalid event.']);exit;}
try{CommerceService::handleWebhookEvent($event);}catch(Throwable $e){http_response_code(500);echo json_encode(['error'=>'Handler failed.']);exit;}
echo json_encode(['received'=>true]);
