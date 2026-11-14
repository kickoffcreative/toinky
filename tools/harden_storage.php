<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
$root=realpath((string)($argv[1]??''));
if(!$root||file_exists($root.'/.git'))throw new RuntimeException('Use a real deployment directory outside a Git checkout.');
$apply=in_array('--apply',array_slice($argv,2),true);$count=0;
foreach(['app','config','database','tools','public'] as $part){
    $directory=$root.'/'.$part;if(!is_dir($directory))continue;
    if(is_link($directory))throw new RuntimeException('Linked private directories require manual review.');
    $items=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
    foreach($items as $item){
        // Uploaded/user files are preserved. This changes mode only, never ownership or contents.
        if($item->isLink())throw new RuntimeException('Linked private storage requires manual review.');
        $resolved=realpath($item->getPathname());
        if(!$resolved||!str_starts_with($resolved,$root.DIRECTORY_SEPARATOR))throw new RuntimeException('Private storage escaped deployment directory.');
        if($apply&&!chmod($resolved,($item->getPerms()&0777)&($item->isDir()?0750:0640)))throw new RuntimeException('Private storage permissions could not be set.');$count++;
    }
    if($apply&&!chmod($directory,(fileperms($directory)&0777)&0750))throw new RuntimeException('Private directory permissions could not be set.');$count++;
}
echo ($apply?'Applied':'Would apply')." private storage modes to $count entries. Public root wrappers/assets and external security keys are outside this operation.\n";
