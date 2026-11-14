<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once __DIR__.'/deploy_manifest.php';
$source=dirname(__DIR__);$target=realpath((string)($argv[1]??''));
if(!$target||$target===realpath($source)||is_link($target.'/tools'))throw new RuntimeException('Use a separate, real deployment directory.');
foreach(buildManifest($source) as $rel=>$hash){
    if(!str_starts_with($rel,'tools/'))continue;
    $directory=dirname($target.'/'.$rel);
    if(!is_dir($directory)&&!mkdir($directory,0750,true)&&!is_dir($directory))throw new RuntimeException('Could not create runtime tools directory.');
    if(is_link($target.'/'.$rel)||!copy($source.'/'.$rel,$target.'/'.$rel)||!chmod($target.'/'.$rel,0640)||!chmod($directory,0750))throw new RuntimeException('Could not copy runtime tool.');
}
echo "Runtime tools copied without test fixtures.\n";
