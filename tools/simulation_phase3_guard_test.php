<?php
declare(strict_types=1);
// Guard test: SimulationContent pinned fail-closed (audit task 5, Phase 3).
// (i) A pinned simulation built from a version whose snapshot is missing a
// content key FAILS CLOSED (throws naming the simulation and the missing
// key) instead of reading the live draft; a legacy simulation advances
// normally. (ii) A pinned opportunity decision's options appear in
// SimulationService::snapshot()'s open-decision list, and resolving an
// option code that is not in the pinned version throws instead of reading
// live. (iii) A legacy simulation still executes from the live tables
// (smoke regression).
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only.');}

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/SimulationService.php';
require_once $root.'/app/BehaviorService.php';

$pdo=db();
$failures=[];
$check=function(bool $ok,string $label)use(&$failures){echo ($ok?'PASS':'FAIL').' '.$label."\n";if(!$ok)$failures[]=$label;};

$plot=$pdo->prepare("SELECT plot_id FROM plots WHERE code='EMPTY_THRONE' AND status IN ('draft','active') LIMIT 1");
$plot->execute();$plotId=(int)$plot->fetchColumn();
if(!$plotId){echo "SKIP: no EMPTY_THRONE plot fixture.\n";exit(0);}
$ver=$pdo->prepare('SELECT plot_version_id,snapshot_json FROM plot_versions WHERE plot_id=? ORDER BY version_no DESC LIMIT 1');
$ver->execute([$plotId]);$vrow=$ver->fetch();
if(!$vrow){echo "SKIP: no submitted version for EMPTY_THRONE.\n";exit(0);}
$versionId=(int)$vrow['plot_version_id'];
$snapArr=json_decode((string)$vrow['snapshot_json'],true);
if(!is_array($snapArr['content']??null)){echo "SKIP: submitted version snapshot has no content.\n";exit(0);}

$cleanupSim=function(int $sid)use($pdo){
    $d=$pdo->prepare('SELECT decision_id FROM decisions WHERE simulation_id=?');$d->execute([$sid]);$dids=array_column($d->fetchAll(),'decision_id');
    if($dids){$in=str_repeat('?,',count($dids)-1).'?';$pdo->prepare("DELETE FROM decision_options WHERE decision_id IN ($in)")->execute($dids);}
    $pdo->prepare('DELETE FROM decisions WHERE simulation_id=?')->execute([$sid]);
    $c=$pdo->prepare('SELECT character_id FROM characters WHERE simulation_id=?');$c->execute([$sid]);$ids=array_column($c->fetchAll(),'character_id');
    if($ids){$in=str_repeat('?,',count($ids)-1).'?';
        foreach(['character_stats','character_knowledge','character_history'] as $t){$pdo->prepare("DELETE FROM $t WHERE character_id IN ($in)")->execute($ids);}
    }
    $pdo->prepare('DELETE FROM simulation_dynamic_opportunities WHERE simulation_id=?')->execute([$sid]);
    $pdo->prepare('DELETE FROM simulation_factor_history WHERE simulation_id=?')->execute([$sid]);
    $pdo->prepare('DELETE FROM simulation_events WHERE simulation_id=?')->execute([$sid]);
    $pdo->prepare('DELETE FROM relationships WHERE simulation_id=?')->execute([$sid]);
    $pdo->prepare('DELETE FROM characters WHERE simulation_id=?')->execute([$sid]);
    $pdo->prepare('DELETE FROM simulation_factors WHERE simulation_id=?')->execute([$sid]);
    $pdo->prepare('DELETE FROM simulation_state WHERE simulation_id=?')->execute([$sid]);
    $pdo->prepare('DELETE FROM simulations WHERE simulation_id=?')->execute([$sid]);
};

$sidPinned=0;$sidLegacy=0;$sidStripped=0;$testVersionId=0;
try{
    // (i) Fail closed on a snapshot missing a content key. Build the
    // synthetic version the way the Phase 2 test does (strip a key, real
    // submitted_by_user_id from an admin fixture, delete it in finally).
    $maxv=$pdo->prepare('SELECT MAX(version_no) FROM plot_versions WHERE plot_id=?');$maxv->execute([$plotId]);$nextNo=(int)$maxv->fetchColumn()+1;
    $stripped=$snapArr;unset($stripped['content']['events']);
    $adminId=$pdo->query('SELECT user_id FROM users WHERE is_admin=1 ORDER BY user_id LIMIT 1')->fetchColumn();if(!$adminId)throw new RuntimeException('Simulation Phase 3 test needs an administrator fixture.');
    $ins=$pdo->prepare('INSERT INTO plot_versions(plot_id,version_no,workflow_status,snapshot_json,submitted_by_user_id) VALUES(?,?,\'SUBMITTED\',?,?)');
    $ins->execute([$plotId,$nextNo,json_encode($stripped,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(int)$adminId]);
    $testVersionId=(int)$pdo->lastInsertId();
    $sidStripped=SimulationService::create('EMPTY_THRONE',300,[],$testVersionId);
    $sidLegacy=SimulationService::create('EMPTY_THRONE',300,[]);
    $check($sidStripped>0&&$sidLegacy>0,'stripped pinned + legacy simulations created');
    $threw=false;$msg='';
    try{SimulationService::advance($sidStripped,60);}
    catch(Throwable $e){$threw=true;$msg=$e->getMessage();}
    $check($threw,'pinned simulation with a missing snapshot key throws instead of reading live');
    $check(str_contains($msg,'events'),'fail-closed error names the missing content key');
    $check(str_contains($msg,(string)$sidStripped),'fail-closed error names the simulation');
    $legacyThrew=false;
    try{SimulationService::advance($sidLegacy,60);}
    catch(Throwable $e){$legacyThrew=true;$failures[]='legacy advance threw: '.$e->getMessage();}
    $check(!$legacyThrew,'legacy simulation advances without a fail-closed error');

    // (ii) Pinned opportunity decision options in snapshot()'s
    // open-decision list.
    $sidPinned=SimulationService::create('EMPTY_THRONE',300,[],$versionId);
    $opportunities=array_values(array_filter($snapArr['content']['opportunities']??[],static fn($o)=>(int)($o['active']??1)===1));
    $picked=null;$pickedCodes=[];
    foreach($opportunities as$opp){
        $codes=array_values(array_unique(array_column(array_filter($snapArr['content']['dynamic_options']??[],static fn($x)=>(int)$x['dynamic_opportunity_id']===(int)$opp['dynamic_opportunity_id']&&(int)($x['active']??1)===1),'option_code')));
        if(!$codes)continue;
        $cq=$pdo->prepare('SELECT c.character_id FROM characters c JOIN plot_roles pr ON pr.plot_role_id=c.plot_role_id WHERE c.simulation_id=? AND pr.role_code=? LIMIT 1');
        $cq->execute([$sidPinned,(string)$opp['role_code']]);$cid=(int)$cq->fetchColumn();
        if(!$cid)continue;
        $picked=$opp;$pickedCodes=$codes;$pickedCid=$cid;break;
    }
    if(!$picked){echo "SKIP: no active opportunity with a cast character in the pinned simulation.\n";}
    else{
        $oppCode=(string)$picked['opportunity_code'];
        $ev=$pdo->prepare("INSERT INTO simulation_events(simulation_id,event_second,phase,event_type,event_code,headline,event_text,visibility) VALUES(?,0,'RISING_ACTION','SYSTEM',?,'Phase 3 guard fixture',?,'PRIVATE')");
        $ev->execute([$sidPinned,$oppCode,(string)$picked['prompt_text']]);$eventId=(int)$pdo->lastInsertId();
        $dq=$pdo->prepare('INSERT INTO decisions(simulation_id,character_id,event_id,prompt_text,status,opens_at_second,closes_at_second) VALUES(?, ?, ?, ?, "OPEN", 0, 100000)');
        $dq->execute([$sidPinned,$pickedCid,$eventId,(string)$picked['prompt_text']]);$decisionId=(int)$pdo->lastInsertId();
        $snap=SimulationService::snapshot($sidPinned);
        $found=null;
        foreach($snap['characters'] as$ch){if((int)$ch['character_id']!==$pickedCid)continue;foreach($ch['open_decisions'] as$dec)if((int)$dec['decision_id']===$decisionId)$found=$dec;}
        $check($found!==null,'pinned opportunity decision appears in snapshot() open decisions');
        $shownCodes=$found!==null?array_column($found['options']??[],'option_code'):[];
        $check($shownCodes!==[],'pinned opportunity decision carries snapshot options');
        $check(empty(array_diff($shownCodes,$pickedCodes)),'snapshot options are the pinned version options');
        // Resolving an option code that is not in the pinned version fails
        // closed instead of falling through to the live draft.
        $m=new ReflectionMethod(BehaviorService::class,'decisionOption');$m->setAccessible(true);
        $drow=$pdo->prepare('SELECT * FROM decisions WHERE decision_id=?');$drow->execute([$decisionId]);$d=$drow->fetch();
        $missThrew=false;$missMsg='';
        try{$m->invoke(null,$sidPinned,$d,$decisionId,'GUARD_TEST_BOGUS_OPTION',['plot_id'=>$plotId]);}
        catch(Throwable $e){$missThrew=true;$missMsg=$e->getMessage();}
        $check($missThrew,'pinned decisionOption miss throws instead of reading live');
        $check(str_contains($missMsg,'Decision option not found'),'pinned decisionOption miss names the failure');
        $valid=$m->invoke(null,$sidPinned,$d,$decisionId,(string)$pickedCodes[0],['plot_id'=>$plotId]);
        $check(is_array($valid)&&(string)($valid['option_code']??'')===(string)$pickedCodes[0],'pinned decisionOption resolves a snapshot option');
    }

    // (iii) Legacy smoke: a legacy simulation still executes from the live
    // tables.
    $ev=$pdo->prepare('SELECT event_code,trigger_percent FROM plot_world_events WHERE plot_id=? AND active=1 AND trigger_percent>0 ORDER BY trigger_percent,plot_world_event_id LIMIT 1');
    $ev->execute([$plotId]);$erow=$ev->fetch();
    if(!$erow){echo "SKIP: no live world event fixture for the legacy smoke check.\n";}
    else{
        $second=(int)round((float)$erow['trigger_percent']*300/100);
        SimulationService::advance($sidLegacy,$second);
        $h=$pdo->prepare('SELECT COUNT(*) FROM simulation_events WHERE simulation_id=? AND event_code=?');$h->execute([$sidLegacy,$erow['event_code']]);
        $check((int)$h->fetchColumn()>0,'legacy simulation fires its world event from the live tables');
    }
}catch(Throwable $e){
    $failures[]='unexpected exception: '.$e->getMessage();
    echo 'FAIL unexpected exception: '.$e->getMessage()."\n";
}finally{
    if($testVersionId)$pdo->prepare('DELETE FROM plot_versions WHERE plot_version_id=?')->execute([$testVersionId]);
    if($sidPinned)$cleanupSim($sidPinned);
    if($sidLegacy)$cleanupSim($sidLegacy);
    if($sidStripped)$cleanupSim($sidStripped);
    echo "cleanup done.\n";
}
if($failures){echo 'FAILURES: '.implode('; ',$failures)."\n";exit(1);}
echo "simulation_phase3_guard_test: all checks passed.\n";
