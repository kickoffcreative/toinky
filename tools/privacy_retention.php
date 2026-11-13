<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/app/bootstrap.php';require_once dirname(__DIR__).'/app/PrivacyRetentionService.php';
try{echo json_encode(PrivacyRetentionService::run(in_array('--apply',$argv,true)),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";}
catch(Throwable $e){fwrite(STDERR,"Privacy retention stopped. Review the private server log before retrying.\n");Logger::error('Privacy retention failed',['exception'=>get_class($e)]);exit(1);}
