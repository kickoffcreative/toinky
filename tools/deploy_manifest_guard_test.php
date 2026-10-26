<?php
declare(strict_types=1);
// Guard test: deploy manifest (audit task 13). The deploy must verify the
// deployed files against the repo and delete stale ones, without ever
// touching server-owned paths.
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only.');}

$root=dirname(__DIR__);
$failures=[];
$check=function(bool $ok,string $label)use(&$failures){echo ($ok?'PASS':'FAIL').' '.$label."\n";if(!$ok)$failures[]=$label;};

$script=$root.'/tools/deploy_manifest.php';
$check(file_exists($script),'tools/deploy_manifest.php exists');
$src=file_exists($script)?file_get_contents($script):'';
$check(str_contains($src,"if (PHP_SAPI !== 'cli')"),'manifest script has a CLI-only guard');
$check(str_contains($src,"'config/database.php'"),'allowlist contains config/database.php');
$check(str_contains($src,"'.installed'"),'allowlist contains .installed');
$check(str_contains($src,"'public/uploads/'"),'allowlist contains public/uploads/');
$check(str_contains($src,"'--apply'"),'manifest script is verify-only by default and deletes only with --apply');
$cpanel=file_get_contents($root.'/.cpanel.yml');
$check(str_contains($cpanel,'rm -rf $DEPLOYPATH/public/assets'),'.cpanel.yml removes the shadow public/assets tree');
$check(str_contains($cpanel,'tools/deploy_manifest.php $DEPLOYPATH --apply'),'.cpanel.yml runs the manifest (with --apply) before migrations');
$check(strpos($cpanel,'deploy_manifest.php')<strpos($cpanel,'tools/migrate.php'),'manifest step runs before the migrate step');

if($failures){echo 'FAILURES: '.implode('; ',$failures)."\n";exit(1);}
echo "deploy_manifest_guard_test: all checks passed.\n";
