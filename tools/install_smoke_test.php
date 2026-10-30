<?php
declare(strict_types=1);
// Smoke test: fresh install (audit task 14). Runs install.php end to end
// against a DISPOSABLE test database and asserts the .installed marker and
// the schema tables exist. Refuses to run unless the database config is a
// test config (filename contains 'test'), so it can never touch production.
// Cleans up after itself: removes .installed and config/database.php (and
// restores a pre-existing config/database.php if one was present).
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only.');}

$root=dirname(__DIR__);
$failures=[];
$check=function(bool $ok,string $label)use(&$failures){echo ($ok?'PASS':'FAIL').' '.$label."\n";if(!$ok)$failures[]=$label;};

$configPath=trim((string)getenv('GAME2_DATABASE_CONFIG'));
if($configPath==='')$configPath=$root.'/config/database.test.php.example';
$check(str_contains(basename($configPath),'test'),'refusing to run the install smoke test unless the database config is a test config');
if(!str_contains(basename($configPath),'test')){echo "SKIP: GAME2_DATABASE_CONFIG is not a test config.\n";exit(0);}
$cfg=require $configPath;
$dsn=(string)$cfg['dsn'];$user=(string)$cfg['username'];$pass=(string)$cfg['password'];
preg_match('/host=([^;]+)/',$dsn,$m);$host=$m[1]??'127.0.0.1';
preg_match('/port=([^;]+)/',$dsn,$mp);$port=$mp[1]??'3306';
preg_match('/dbname=([^;]+)/',$dsn,$m);$dbname=$m[1]??'';
if($dbname===''){echo "SKIP: could not parse a database name from the test config.\n";exit(0);}

$marker=$root.'/.installed';
$configFile=$root.'/config/database.php';
$hadMarker=is_file($marker);
$hadConfig=is_file($configFile);
$configBackup=$hadConfig?file_get_contents($configFile):null;
if($hadMarker)unlink($marker);
if($hadConfig)unlink($configFile);
try{
    // Drop any leftover tables from a previous run so the install is fresh.
    $pdo=new PDO($dsn,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table)$pdo->exec('DROP TABLE IF EXISTS `'.$table.'`');
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

    $_SERVER['REQUEST_METHOD']='POST';
    $_POST=['db_host'=>$host,'db_port'=>$port,'db_name'=>$dbname,'db_user'=>$user,'db_password'=>$pass];
    ob_start();
    require $root.'/install.php';
    ob_end_clean();

    $check(is_file($marker),'.installed marker exists after install');
    $markerBody=is_file($marker)?file_get_contents($marker):'';
    $check(str_contains($markerBody,'Toinky installed'),'marker body says Toinky installed');
    $check(is_file($configFile),'config/database.php was written by the installer');
    $tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $check(count($tables)>0,'schema tables exist after install ('.count($tables).' tables)');
    $check(in_array('plots',$tables,true),'plots table exists after install');
}finally{
    if(is_file($marker))unlink($marker);
    if(is_file($configFile))unlink($configFile);
    if($hadConfig&&$configBackup!==null)file_put_contents($configFile,$configBackup,LOCK_EX);
    echo "cleanup done.\n";
}
if($failures){echo 'FAILURES: '.implode('; ',$failures)."\n";exit(1);}
echo "install_smoke_test: all checks passed.\n";
