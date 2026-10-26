<?php
declare(strict_types=1);
// Guard test: Inky draft-dependency endpoint (audit task 7). The Story Writer
// JS fetches relative 'inky-draft-dependencies.php', which resolves to the
// ROOT because the hosting document is /writer.php — so a root wrapper must
// exist and be deployed, or the widget silently renders stale page-load data.
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only.');}

$root=dirname(__DIR__);
$failures=[];
$check=function(bool $ok,string $label)use(&$failures){echo ($ok?'PASS':'FAIL').' '.$label."\n";if(!$ok)$failures[]=$label;};

$wrapper=$root.'/inky-draft-dependencies.php';
$check(file_exists($wrapper),'root inky-draft-dependencies.php exists');
$src=file_exists($wrapper)?file_get_contents($wrapper):'';
$check(str_contains($src,"require __DIR__.'/public/inky-draft-dependencies.php'"),'root wrapper requires the public/ endpoint');
$check(str_contains($src,"require __DIR__.'/app/runtime_check.php'"),'root wrapper requires runtime_check.php first');
$check(file_exists($root.'/public/inky-draft-dependencies.php'),'public/inky-draft-dependencies.php exists');
// .cpanel.yml is not copied to the deployed directory, so on the live server
// this file is absent — skip the check there (CI runs it from the checkout,
// where the file exists). An unguarded read dies under strict_types.
if(is_file($root.'/.cpanel.yml')){
    $cpanel=file_get_contents($root.'/.cpanel.yml');
    $check(substr_count($cpanel,'inky-draft-dependencies.php')>=2,'.cpanel.yml names inky-draft-dependencies.php in both the copy list and the chmod list');
}else{echo "SKIP .cpanel.yml names inky-draft-dependencies.php (not present on this server)\n";}
// The deploy copies public/assets to /assets and removes the public/assets
// shadow tree, so on the live server the JS lives at /assets, not
// /public/assets. Accept either layout.
$jsPath=file_exists($root.'/public/assets/toinky-inky-draft.js')?$root.'/public/assets/toinky-inky-draft.js':$root.'/assets/toinky-inky-draft.js';
$js=file_exists($jsPath)?file_get_contents($jsPath):'';
$check(str_contains($js,"fetch('inky-draft-dependencies.php'"),'JS still fetches the relative endpoint (root wrapper is the fix, not a JS change)');

if($failures){echo 'FAILURES: '.implode('; ',$failures)."\n";exit(1);}
echo "inky_draft_endpoint_guard_test: all checks passed.\n";
