<?php
declare(strict_types=1);
// Guard test: no migrations from HTTP (audit task 12). MigrationService::run()
// must only ever execute at deploy time (tools/migrate.php, the last
// .cpanel.yml step) or from CLI/test tooling — never from a web request, where
// it ran before auth (and, on the Stripe webhook, before signature checks).
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only.');}

$root=dirname(__DIR__);
$failures=[];
$check=function(bool $ok,string $label)use(&$failures){echo ($ok?'PASS':'FAIL').' '.$label."\n";if(!$ok)$failures[]=$label;};

$bad=[];
foreach(glob($root.'/public/*.php') as $file){
    $src=file_get_contents($file);
    if(str_contains($src,'MigrationService::run()'))$bad[]=basename($file);
}
$check($bad===[] ,'no public/*.php file calls MigrationService::run()'.($bad?' — still present in: '.implode(', ',$bad):''));
$migrate=file_get_contents($root.'/tools/migrate.php');
$check(str_contains($migrate,"if(PHP_SAPI!=='cli')"),'tools/migrate.php still has its CLI-only guard');
$check(str_contains($migrate,'MigrationService::run()'),'tools/migrate.php still runs migrations (deploy-time path intact)');
if(is_file($root.'/.cpanel.yml')){
    $cpanel=file_get_contents($root.'/.cpanel.yml');
    $check(str_contains($cpanel,'tools/migrate.php'),'.cpanel.yml still runs tools/migrate.php as a deploy step');
}else{echo "SKIP .cpanel.yml still runs tools/migrate.php (not present on this server)\n";}

if($failures){echo 'FAILURES: '.implode('; ',$failures)."\n";exit(1);}
echo "no_migrations_http_guard_test: all checks passed.\n";
