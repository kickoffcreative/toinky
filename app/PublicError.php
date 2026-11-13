<?php
require_once __DIR__.'/UserFacingException.php';
require_once __DIR__.'/Logger.php';

/** The only boundary allowed to expose a deliberate exception message. */
function public_error(Throwable $exception): string
{
    if($exception instanceof UserFacingException)return $exception->getMessage();
    static $reported=null;
    $reported??=new WeakMap();
    if(isset($reported[$exception]))return $reported[$exception];
    $reference=function_exists('correlationId')?correlationId():bin2hex(random_bytes(8));
    if(!preg_match('/^[a-f0-9]{16}$/D',$reference))$reference=bin2hex(random_bytes(8));
    Logger::exception($exception,'Unexpected request failure',['reference'=>$reference]);
    return $reported[$exception]='Something went wrong. Reference: '.$reference;
}

/** Internal retry/idempotency classification without returning provider text. */
function exception_category(Throwable $exception): string
{
    $message=strtolower($exception->getMessage());
    if($exception instanceof PDOException&&($exception->errorInfo[1]??0)==1062&&str_contains($message,'uniq_game_idempotency'))return 'duplicate_game_idempotency';
    if($exception instanceof JsonException||str_contains($message,'structured response')||str_contains($message,'json')||str_contains($message,'syntax error'))return 'json';
    if(str_contains($message,'timeout')||str_contains($message,'timed out'))return 'timeout';
    if(preg_match('/\bhttp\s+([45][0-9]{2})\b/',$message,$match))return 'http '.$match[1];
    if(str_contains($message,'not enabled'))return 'not enabled';
    if(str_contains($message,'cannot be reached'))return 'cannot be reached';
    return 'unknown';
}

/** Last resort for failures outside an entry point's ordinary try/catch. */
function public_exception_response(Throwable $exception): void
{
    $message=public_error($exception);$json=false;
    foreach(headers_list() as $header)if(stripos($header,'Content-Type: application/json')===0)$json=true;
    if(!headers_sent()){
        http_response_code(500);header('Cache-Control: no-store');
        header('Content-Type: '.($json?'application/json':'text/plain').'; charset=utf-8');
    }
    echo $json?json_encode(['ok'=>false,'message'=>$message],JSON_INVALID_UTF8_SUBSTITUTE):$message;
}
