<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);require_once $root.'/app/bootstrap.php';require_once $root.'/app/MigrationService.php';MigrationService::run();
$email=strtolower(trim((string)($argv[1]??'')));if(!filter_var($email,FILTER_VALIDATE_EMAIL)){fwrite(STDERR,"Usage: php tools/promote_admin.php user@example.com\n");exit(2);}
$q=db()->prepare('UPDATE users SET is_admin=1,status="ACTIVE" WHERE email=?');$q->execute([$email]);if($q->rowCount()<1){fwrite(STDERR,"No account found for {$email}. Register the account first.\n");exit(1);}echo "Administrator access granted to {$email}.\n";
