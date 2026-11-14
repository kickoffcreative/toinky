<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
$base=rtrim((string)($argv[1]??'http://127.0.0.1'),'/');if(!in_array(parse_url($base,PHP_URL_HOST),['127.0.0.1','localhost'],true))throw new RuntimeException('Use the disposable local Apache fixture.');
$http=static function(string $path)use($base):array{$body=@file_get_contents($base.$path,false,stream_context_create(['http'=>['ignore_errors'=>true,'follow_location'=>0,'timeout'=>5]]));return [$body===false?'':$body,$http_response_header??[]];};
$ready=false;for($i=0;$i<30;$i++){[$body]=$http('/engine.php');if($body==='PHP_HANDLER_ACTIVE'){$ready=true;break;}usleep(100000);}
$checks=0;$check=static function(bool $ok,string $label)use(&$checks):void{if(!$ok)throw new RuntimeException($label);$checks++;};
$check($ready,'Real Apache PHP handler is active');[$body]=$http('/control.php.jpg');$check($body==='DOUBLE_HANDLER_ACTIVE','Control demonstrates inherited double-extension PHP execution');
foreach(['/install.php','/app/probe.php','/config/probe.php','/database/probe.php','/tools/probe.php','/public/probe.php'] as $path){[$body,$headers]=$http($path);$check(preg_match('/\s(?:403|404)\s/',$headers[0]??'')===1&&!str_contains($body,'PRIVATE_EXECUTED'),'Private path/installer refused: '.$path);}
foreach(['payload.php','payload.phtml','payload.php8','payload.phar','payload.cgi','.htaccess'] as $name){[$body,$headers]=$http('/upload-policy/'.$name);$check(preg_match('/\s(?:403|404)\s/',$headers[0]??'')===1&&!str_contains($body,'UPLOAD_EXECUTED'),'Upload executable/config refused: '.$name);}
[$body,$headers]=$http('/upload-policy/pixel.png');$check(str_contains($headers[0]??'','200')&&str_starts_with($body,"\x89PNG"),'Allowed image still streams');
[$body,$headers]=$http('/upload-policy/polyglot.php.jpg');$check(str_contains($headers[0]??'','200')&&str_starts_with($body,"\x89PNG")&&str_contains($body,'<?php'),'Double-extension image remains static bytes');
$check(!is_file('/tmp/toinky-upload-executed'),'No upload payload executed');
[$body,$headers]=$http('/public/uploads/profiles/pixel.png');$check(str_contains($headers[0]??'','200')&&$body==='IMAGE_ROUTE','Legacy image path reaches PHP compatibility route');
echo "$checks Apache upload execution checks passed\n";
