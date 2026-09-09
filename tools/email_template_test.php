<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/MigrationService.php';
MigrationService::run();
require_once $root.'/app/EmailTemplateService.php';

$templates=EmailTemplateService::all();
$expected=['WELCOME','INVITATION','PASSWORD_RESET','GAME_INVITATION','EMAIL_CHANGE'];
$keys=array_column($templates,'template_key');
if($keys!==$expected)throw new RuntimeException('Email template order or coverage is incorrect.');
foreach($expected as$key){$message=EmailTemplateService::compose($key,['recipient_name'=>'Test Writer','action_url'=>'https://toinky.com/test','action_label'=>'Open Toinky','message'=>'A controlled terminal test message.','story_title'=>'Test Story','game_code'=>'TEST123','expiry_text'=>'This is a test.']);if($message['subject']===''||$message['html']===''||$message['text']==='')throw new RuntimeException($key.' did not compose all message formats.');if(str_contains($message['html'],'{{')||str_contains($message['text'],'{{'))throw new RuntimeException($key.' contains an unresolved template key.');}
echo 'PASS: email templates compose branded HTML and plain-text messages.'.PHP_EOL;
