<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once __DIR__.'/../app/bootstrap.php';require_once __DIR__.'/../app/ServerSecretsService.php';
if(in_array('--path',$argv,true)){echo ServerSecretsService::path()."\n";exit;}
try{$apply=in_array('--apply',$argv,true);$result=ServerSecretsService::migrate($apply);echo json_encode(['applied'=>$apply]+$result,JSON_THROW_ON_ERROR)."\n";}
catch(Throwable $e){Logger::exception($e,'Provider credential migration failed');fwrite(STDERR,"Provider credential migration failed; no credentials printed. Resolve conflicts, permissions or destination configuration privately.\n");exit(1);}
