<?php
declare(strict_types=1);
// Guard test: empty-cast protection (audit task 15). A story with no active
// player characters must not start (create() throws), and a run whose
// snapshot has no characters must carry an integrity warning. Historical
// runs are flagged, never rewritten — this test only asserts the gate and
// the warning, it changes no historical data.
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only.');}

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/SimulationService.php';
require_once $root.'/app/GenericStateService.php';
require_once $root.'/tools/SimulationHarness.php';

$pdo=db();
$failures=[];
$check=function(bool $ok,string $label)use(&$failures){echo ($ok?'PASS':'FAIL').' '.$label."\n";if(!$ok)$failures[]=$label;};

$plot=$pdo->prepare("SELECT plot_id,code FROM plots WHERE code='EMPTY_THRONE' AND status IN ('draft','active') LIMIT 1");
$plot->execute();$prow=$plot->fetch();
if(!$prow){echo "SKIP: no EMPTY_THRONE plot fixture.\n";exit(0);}
$plotId=(int)$prow['plot_id'];$plotCode=(string)$prow['code'];

// --- 1. create() throws when the plot has no active player characters ---
// Temporarily deactivate every role for the plot, attempt a create, then
// restore the roles in a finally block. Runs against the disposable test
// database only; the plot is restored before the test exits.
$roles=$pdo->prepare('SELECT plot_role_id,active,simulation_active FROM plot_roles WHERE plot_id=?');$roles->execute([$plotId]);$saved=$roles->fetchAll();
try{
    $pdo->prepare('UPDATE plot_roles SET active=0,simulation_active=0 WHERE plot_id=?')->execute([$plotId]);
    $before=(int)$pdo->query('SELECT COUNT(*) FROM simulations WHERE plot_id='.$plotId)->fetchColumn();
    $thrown=false;$message='';
    try{SimulationService::create($plotCode,300,[],null);}catch(Throwable $e){$thrown=true;$message=$e->getMessage();}
    $after=(int)$pdo->query('SELECT COUNT(*) FROM simulations WHERE plot_id='.$plotId)->fetchColumn();
    $check($thrown,'create() throws for a plot with no active player characters');
    $check(str_contains($message,'no active player characters'),'create() refusal names the missing player characters');
    $check($after===$before,'a refused create leaves no new simulation behind');
}finally{
    foreach($saved as $r)$pdo->prepare('UPDATE plot_roles SET active=?,simulation_active=? WHERE plot_role_id=?')->execute([(int)$r['active'],(int)$r['simulation_active'],(int)$r['plot_role_id']]);
    echo "roles restored.\n";
}

// --- 2. integrityWarnings() flags an empty cast --------------------------
// integrityWarnings is private; call it via reflection with a synthetic
// snapshot (COMPLETE, no factors, no characters) and a non-empty resolution
// so the empty-cast warning is the only one expected.
$ref=new ReflectionMethod('SimulationHarness','integrityWarnings');
$ref->setAccessible(true);
$snapshot=['simulation'=>['status'=>'COMPLETE'],'factors'=>[],'characters'=>[]];
$warnings=$ref->invoke(null,0,$snapshot,[],'Some ending text.');
$check(in_array('Empty cast: no characters were present.',(array)$warnings,true),'integrityWarnings() flags an empty cast');
$check(!in_array('Simulation did not reach COMPLETE.',(array)$warnings,true),'a COMPLETE empty-cast run is not also flagged as incomplete');
$check(!in_array('No FINAL_STATE resolution text was generated.',(array)$warnings,true),'a run with resolution text is not also flagged as unresolved');

// A non-empty cast must not carry the empty-cast warning.
$snapshot['characters']=[['actor_type'=>'PLAYER','name'=>'Test Hero'],['actor_type'=>'NPC','name'=>'Test Guide']];
$warnings=$ref->invoke(null,0,$snapshot,[],'Some ending text.');
$check(!in_array('Empty cast: no characters were present.',(array)$warnings,true),'a non-empty cast carries no empty-cast warning');

if($failures){echo 'FAILURES: '.implode('; ',$failures)."\n";exit(1);}
echo "cast_gate_guard_test: all checks passed.\n";
