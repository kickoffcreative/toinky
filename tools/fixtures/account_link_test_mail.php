<?php
// Local regression transport. No socket or real mail delivery is possible.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$dir=(string)getenv('TOINKY_ACCOUNT_LINK_TEST_DIR');
if($dir===''||!is_dir($dir)||!is_file($dir.'/test-marker'))exit(1);
$body=stream_get_contents(STDIN);
file_put_contents($dir.'/mail.jsonl',json_encode(['body'=>$body])."\n",FILE_APPEND|LOCK_EX);
exit(is_file($dir.'/fail-mail')?1:0);
