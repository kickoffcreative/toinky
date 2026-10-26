<?php
declare(strict_types=1);
// Guard test: request shutdown must never mutate simulation state (audit task 1).
// An unauthenticated/ordinary read request carrying a simulation id must cause
// zero canonical simulation changes. Sync runs only via authorized commands
// (LobbyService::tick, Lab, SimulationService::create/advance).
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only.');}

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/SimulationService.php';

$pdo=db();
$failures=[];
$check=function(bool $ok,string $label)use(&$failures){echo ($ok?'PASS':'FAIL').' '.$label."\n";if(!$ok)$failures[]=$label;};

// 1. Static guard: bootstrap.php must not register a shutdown function.
$bootstrap=file_get_contents($root.'/app/bootstrap.php');
$check(strpos($bootstrap,'register_shutdown_function')===false,'bootstrap.php registers no shutdown function');

// 2. Behavioural guard: a fresh subprocess that only includes bootstrap.php
//    with $_GET[simulation] set must not change any canonical simulation state.
$plot=$pdo->query("SELECT code FROM plots WHERE code='EMPTY_THRONE' AND status IN ('draft','active') LIMIT 1")->fetchColumn();
if(!$plot){echo "SKIP behavioural check: no EMPTY_THRONE plot fixture.\n";exit(empty($failures)?0:1);}
$sid=SimulationService::create('EMPTY_THRONE',300,[]);
$check($sid>0,'test simulation created');

$snapshot=function(int $sid)use($pdo): string{
    $parts=[];
    $row=$pdo->prepare('SELECT * FROM simulations WHERE simulation_id=?');$row->execute([$sid]);$parts[]='sim:'.json_encode($row->fetch());
    foreach(['simulation_state','simulation_factors'] as $t){
        $q=$pdo->prepare("SELECT * FROM $t WHERE simulation_id=? ORDER BY 1,2");
        $q->execute([$sid]);$parts[]=$t.':'.json_encode($q->fetchAll());
    }
    $chars=$pdo->prepare('SELECT * FROM characters WHERE simulation_id=? ORDER BY character_id');$chars->execute([$sid]);$charRows=$chars->fetchAll();$parts[]='chars:'.json_encode($charRows);
    $cids=array_column($charRows,'character_id');
    if($cids){
        $in=str_repeat('?,',count($cids)-1).'?';
        foreach(['character_stats'=>'character_id','character_knowledge'=>'character_id','character_history'=>'character_id'] as $t=>$col){
            $q=$pdo->prepare("SELECT * FROM $t WHERE $col IN ($in) ORDER BY 1,2");$q->execute($cids);$parts[]=$t.':'.json_encode($q->fetchAll());
        }
        $q=$pdo->prepare("SELECT * FROM relationships WHERE simulation_id=? ORDER BY from_character_id,to_character_id");$q->execute([$sid]);$parts[]='rels:'.json_encode($q->fetchAll());
    }
    return md5(implode(chr(30),$parts));
};
$before=$snapshot($sid);

// Subprocess: simulate an unauthenticated read request that carries a simulation id.
$probe=$root.'/tools/.shutdown_probe.php';
file_put_contents($probe,"<?php\n\$_GET['simulation']=".((int)$sid).";\nrequire ".var_export($root.'/app/bootstrap.php',true).";\n");
$cmd='php '.escapeshellarg($probe).' 2>&1';
$out=[];$code=0;exec($cmd,$out,$code);
@unlink($probe);
$check($code===0,'shutdown-probe subprocess exits cleanly'.($code!==0?' (output: '.implode(' ',$out).')':''));
$after=$snapshot($sid);
$check($before===$after,'unauthenticated read with simulation id leaves canonical state unchanged');

// 3. Cleanup the test simulation (children first, then the simulation row).
try{
    $cids=$pdo->prepare('SELECT character_id FROM characters WHERE simulation_id=?');$cids->execute([$sid]);$ids=array_column($cids->fetchAll(),'character_id');
    if($ids){$in=str_repeat('?,',count($ids)-1).'?';
        foreach(['character_stats','character_knowledge','character_history'] as $t){$d=$pdo->prepare("DELETE FROM $t WHERE character_id IN ($in)");$d->execute($ids);}
    }
    $pdo->prepare('DELETE FROM relationships WHERE simulation_id=?')->execute([$sid]);
    $pdo->prepare('DELETE FROM characters WHERE simulation_id=?')->execute([$sid]);
    $pdo->prepare('DELETE FROM simulation_factors WHERE simulation_id=?')->execute([$sid]);
    $pdo->prepare('DELETE FROM simulation_state WHERE simulation_id=?')->execute([$sid]);
    $pdo->prepare('DELETE FROM simulations WHERE simulation_id=?')->execute([$sid]);
    echo "cleanup done.\n";
}catch(Throwable $e){echo 'cleanup warning: '.$e->getMessage()."\n";}

if($failures){echo 'FAILURES: '.implode('; ',$failures)."\n";exit(1);}
echo "shutdown_no_write_guard_test: all checks passed.\n";
