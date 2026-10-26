<?php
declare(strict_types=1);
// Guard test: authoring must never delete historical gameplay (audit task 4).
// Deleting a role that has ever been cast must be rejected; retiring it must
// exclude it from future games while past games keep their characters/history.
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only.');}

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/StoryAuthoringService.php';
require_once $root.'/app/SimulationService.php';

$pdo=db();
$failures=[];
$check=function(bool $ok,string $label)use(&$failures){echo ($ok?'PASS':'FAIL').' '.$label."\n";if(!$ok)$failures[]=$label;};
$admin=$pdo->query('SELECT * FROM users WHERE is_admin=1 ORDER BY user_id LIMIT 1')->fetch();
if(!$admin)throw new RuntimeException('Authoring test needs an administrator fixture.');
$admin=['user_id'=>(int)$admin['user_id'],'is_admin'=>1];

$plot=$pdo->prepare("SELECT plot_id FROM plots WHERE code='EMPTY_THRONE' AND status IN ('draft','active') LIMIT 1");
$plot->execute();$plotId=(int)$plot->fetchColumn();
if(!$plotId){echo "SKIP: no EMPTY_THRONE plot fixture.\n";exit(0);}
// Need one free NPC slot (multiplayer allows 1 active NPC) and a valid location.
$npcCount=(int)$pdo->query("SELECT COUNT(*) FROM plot_roles WHERE plot_id=$plotId AND role_type='NPC' AND active=1 AND simulation_active=1")->fetchColumn();
$loc=$pdo->prepare('SELECT label_text FROM plot_locations WHERE plot_id=? ORDER BY plot_location_id LIMIT 1');$loc->execute([$plotId]);$location=(string)$loc->fetchColumn();
if($npcCount>=1||$location===''){echo "SKIP: fixture needs a free NPC slot and a location (npc=$npcCount, location='$location').\n";exit(0);}

$cleanupSim=function(int $sid)use($pdo){
    $c=$pdo->prepare('SELECT character_id FROM characters WHERE simulation_id=?');$c->execute([$sid]);$ids=array_column($c->fetchAll(),'character_id');
    if($ids){$in=str_repeat('?,',count($ids)-1).'?';
        foreach(['character_stats','character_knowledge','character_history'] as $t){$d=$pdo->prepare("DELETE FROM $t WHERE character_id IN ($in)");$d->execute($ids);}
    }
    $pdo->prepare('DELETE FROM relationships WHERE simulation_id=?')->execute([$sid]);
    $pdo->prepare('DELETE FROM characters WHERE simulation_id=?')->execute([$sid]);
    $pdo->prepare('DELETE FROM simulation_factors WHERE simulation_id=?')->execute([$sid]);
    $pdo->prepare('DELETE FROM simulation_state WHERE simulation_id=?')->execute([$sid]);
    $pdo->prepare('DELETE FROM simulations WHERE simulation_id=?')->execute([$sid]);
};
$cleanupRole=function(int $roleId)use($pdo,$plotId){
    $pdo->prepare('DELETE FROM plot_roles WHERE plot_role_id=? AND plot_id=?')->execute([$roleId,$plotId]);
};

$roleId=0;$sid1=0;$sid2=0;$role2=0;
try{
    // (i) Create a test role and cast it in a simulation.
    $roleId=StoryAuthoringService::saveCharacter($admin,$plotId,[
        'plot_role_id'=>0,'role_code'=>'GUARDTEST','name'=>'Guard Test','role_type'=>'NPC',
        'starting_location'=>$location,'goal_text'=>'Guard the test.','fear_text'=>'',
        'public_description'=>'A test-only NPC.','private_knowledge'=>'',
        'pronoun_set'=>'IT','simulation_active'=>1,'active'=>1,
    ]);
    $check($roleId>0,'test role created');
    $sid1=SimulationService::create('EMPTY_THRONE',300,[]);
    $check($sid1>0,'test simulation 1 created');
    $cast=$pdo->prepare('SELECT COUNT(*) FROM characters WHERE simulation_id=? AND plot_role_id=?');$cast->execute([$sid1,$roleId]);
    $check((int)$cast->fetchColumn()===1,'test role is cast in simulation 1');
    $histBefore=$pdo->prepare('SELECT COUNT(*) FROM character_history WHERE simulation_id=?');$histBefore->execute([$sid1]);$histBefore=(int)$histBefore->fetchColumn();
    $check($histBefore>0,'simulation 1 has character history');

    // (i) Deleting the cast role must be rejected and leave runtime data intact.
    $rejected=false;$msg='';
    try{StoryAuthoringService::deleteEntity($admin,$plotId,'character',$roleId);}
    catch(Throwable $e){$rejected=true;$msg=$e->getMessage();}
    $check($rejected,'deleteEntity rejects a cast role');
    $check(strpos($msg,'Retire')!==false,'rejection message points at Retire');
    $cast->execute([$sid1,$roleId]);
    $check((int)$cast->fetchColumn()===1,'cast character survives the rejected delete');
    $histAfter=$pdo->prepare('SELECT COUNT(*) FROM character_history WHERE simulation_id=?');$histAfter->execute([$sid1]);
    $check((int)$histAfter->fetchColumn()===$histBefore,'character history survives the rejected delete');
    $roleStill=$pdo->prepare('SELECT COUNT(*) FROM plot_roles WHERE plot_role_id=? AND plot_id=?');$roleStill->execute([$roleId,$plotId]);
    $check((int)$roleStill->fetchColumn()===1,'role row survives the rejected delete');

    // (ii) Retire: excluded from a new simulation, old simulation untouched.
    StoryAuthoringService::retireCharacter($admin,$plotId,$roleId);
    $flags=$pdo->prepare('SELECT active, simulation_active FROM plot_roles WHERE plot_role_id=?');$flags->execute([$roleId]);$f=$flags->fetch();
    $check((int)$f['active']===0&&(int)$f['simulation_active']===0,'retired role is inactive in both flags');
    $sid2=SimulationService::create('EMPTY_THRONE',300,[]);
    $cast->execute([$sid2,$roleId]);
    $check((int)$cast->fetchColumn()===0,'retired role is excluded from a new simulation');
    $cast->execute([$sid1,$roleId]);
    $check((int)$cast->fetchColumn()===1,'old simulation still has the retired role cast');
    $histAfter->execute([$sid1]);
    $check((int)$histAfter->fetchColumn()===$histBefore,'old simulation history unchanged after retire');

    // (iii) A role with no runtime characters deletes cleanly.
    // The first test role already occupies NPC slot 1 (uq_plot_role is
    // UNIQUE(plot_id,role_type,slot_no)), so the second role needs the next
    // free NPC slot.
    $slotQ=$pdo->prepare('SELECT COALESCE(MAX(slot_no),0)+1 FROM plot_roles WHERE plot_id=? AND role_type=?');$slotQ->execute([$plotId,'NPC']);$freeSlot=(int)$slotQ->fetchColumn();
    $role2=StoryAuthoringService::saveCharacter($admin,$plotId,[
        'plot_role_id'=>0,'role_code'=>'GUARDTEST2','name'=>'Guard Test 2','role_type'=>'NPC',
        'slot_no'=>$freeSlot,
        'starting_location'=>$location,'goal_text'=>'','fear_text'=>'',
        'public_description'=>'A second test-only NPC.','private_knowledge'=>'',
        'pronoun_set'=>'IT','simulation_active'=>1,'active'=>1,
    ]);
    StoryAuthoringService::deleteEntity($admin,$plotId,'character',$role2);
    $roleStill->execute([$role2,$plotId]);
    $check((int)$roleStill->fetchColumn()===0,'role with no runtime characters deletes cleanly');
    $role2=0;

    // (iv) Whole-story delete is still blocked while simulations exist.
    $blocked=false;
    try{StoryAuthoringService::deleteStory($admin,$plotId);}
    catch(Throwable $e){$blocked=true;}
    $check($blocked,'deleteStory stays blocked while simulations exist');
}catch(Throwable $e){
    $failures[]='unexpected exception: '.$e->getMessage();
    echo 'FAIL unexpected exception: '.$e->getMessage()."\n";
}finally{
    // Cleanup: remove test simulations, then the test role (now uncast).
    if($sid2)$cleanupSim($sid2);
    if($sid1)$cleanupSim($sid1);
    if($roleId)$cleanupRole($roleId);
    if($role2)$cleanupRole($role2);
    echo "cleanup done.\n";
}
if($failures){echo 'FAILURES: '.implode('; ',$failures)."\n";exit(1);}
echo "authoring_runtime_guard_test: all checks passed.\n";
