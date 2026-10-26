<?php
// Task 14 (P2) guard test: tools/simulate.php --reset must refuse unless the
// configured database is a local disposable test database. The guard checks
// the DSN the config actually points at (database name ends in _test, host
// 127.0.0.1/localhost) -- not the config filename. Only refusal paths are
// exercised here, so no tables are ever dropped.
if(PHP_SAPI!=='cli'){fwrite(STDERR,"CLI only.\n");exit(2);}
$root=dirname(__DIR__);
$fail=0;
function check($label,$cond){global $fail;if($cond){echo "PASS: {$label}\n";}else{echo "FAIL: {$label}\n";$fail++;}}
function runReset($root,$configPath){
    $cmd='GAME2_DATABASE_CONFIG='.escapeshellarg($configPath).' php '.escapeshellarg($root.'/tools/simulate.php').' --reset --runs=1 --plot=EMPTY_THRONE 2>&1';
    $out=[];$code=0;exec($cmd,$out,$code);
    return ['code'=>$code,'output'=>implode("\n",$out)];
}
$tmp=sys_get_temp_dir().'/toinky-reset-guard-'.getmypid();
@mkdir($tmp);
$configs=[
    'prod_name'=>['dsn'=>'mysql:host=127.0.0.1;port=3306;dbname=game2;charset=utf8mb4'],
    'remote_host'=>['dsn'=>'mysql:host=db.example.com;port=3306;dbname=game2_test;charset=utf8mb4'],
    'no_dbname'=>['dsn'=>'mysql:host=127.0.0.1;port=3306;charset=utf8mb4'],
];
$paths=[];
foreach($configs as$name=>$cfg){
    $p=$tmp.'/'.$name.'.php';
    file_put_contents($p,"<?php return ['dsn'=>'".$cfg['dsn']."','username'=>'root','password'=>'root'];\n");
    $paths[$name]=$p;
}
try{
    $r=runReset($root,$paths['prod_name']);
    check('--reset refuses a database named game2',$r['code']===2&&str_contains($r['output'],'not a disposable test database'));
    $r=runReset($root,$paths['remote_host']);
    check('--reset refuses a remote host',$r['code']===2&&str_contains($r['output'],'not local'));
    $r=runReset($root,$paths['no_dbname']);
    check('--reset refuses a config with no database name',$r['code']===2&&str_contains($r['output'],'not a disposable test database'));
    $r=runReset($root,$tmp.'/does-not-exist.php');
    check('--reset refuses a missing config file',$r['code']===2&&str_contains($r['output'],'not found'));
    $src=file_get_contents($root.'/tools/simulate.php');
    check('simulate.php checks the configured database name',$src!==false&&str_contains($src,"str_ends_with(\$dbName,'_test')"));
    check('simulate.php checks the configured host',$src!==false&&str_contains($src,"['127.0.0.1','localhost']"));
    check('simulate.php no longer uses the filename-only check',$src!==false&&!str_contains($src,'str_contains(basename('));
}finally{
    foreach($paths as$p)@unlink($p);
    @rmdir($tmp);
}
echo $fail?"FAILURES: {$fail}\n":"ALL PASS\n";
exit($fail?1:0);
