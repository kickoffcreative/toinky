<?php
declare(strict_types=1);
// Guard test: atomic decision resolution + transaction ownership (audit
// tasks 2, 3, 6). A decision resolves exactly once (concurrent resolutions
// serialize on the row claim); lobby creation is atomic (a failed create
// leaves no orphan simulation); start is idempotent; a retried lobby
// creation with the same idempotency key reuses the lobby.
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only.');}

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/BehaviorService.php';
require_once $root.'/app/CausalService.php';
require_once $root.'/app/CharacterAutomationService.php';
require_once $root.'/app/CommerceService.php';
require_once $root.'/app/LobbyService.php';
require_once $root.'/app/SimulationService.php';

$pdo=db();
$failures=[];
$check=function(bool $ok,string $label)use(&$failures){echo ($ok?'PASS':'FAIL').' '.$label."\n";if(!$ok)$failures[]=$label;};
$admin=$pdo->query('SELECT * FROM users WHERE is_admin=1 ORDER BY user_id LIMIT 1')->fetch();
if(!$admin)throw new RuntimeException('Atomicity test needs an administrator fixture.');
$adminId=(int)$admin['user_id'];

$plot=$pdo->prepare("SELECT plot_id FROM plots WHERE code='EMPTY_THRONE' AND status IN ('draft','active') LIMIT 1");
$plot->execute();$plotId=(int)$plot->fetchColumn();
if(!$plotId){echo "SKIP: no EMPTY_THRONE plot fixture.\n";exit(0);}
$ver=$pdo->prepare('SELECT plot_version_id FROM plot_versions WHERE plot_id=? ORDER BY version_no DESC LIMIT 1');
$ver->execute([$plotId]);$versionId=$ver->fetchColumn();
$versionId=$versionId===false?null:(int)$versionId;

// --- helpers -------------------------------------------------------------
$cleanupSim=function(int $sid)use($pdo){
    $c=$pdo->prepare('SELECT character_id FROM characters WHERE simulation_id=?');$c->execute([$sid]);$ids=array_column($c->fetchAll(),'character_id');
    if($ids){$in=str_repeat('?,',count($ids)-1).'?';
        foreach(['character_stats','character_knowledge','character_history'] as $t){$d=$pdo->prepare("DELETE FROM $t WHERE character_id IN ($in)");$d->execute($ids);}
        $d=$pdo->prepare("DELETE FROM decisions WHERE character_id IN ($in)");$d->execute($ids);
        $d=$pdo->prepare("DELETE FROM decision_options WHERE decision_id IN (SELECT decision_id FROM decisions WHERE character_id IN ($in))");$d->execute($ids);
    }
    $pdo->prepare('DELETE FROM simulation_events WHERE simulation_id=?')->execute([$sid]);
    $pdo->prepare('DELETE FROM relationships WHERE simulation_id=?')->execute([$sid]);
    $pdo->prepare('DELETE FROM characters WHERE simulation_id=?')->execute([$sid]);
    $pdo->prepare('DELETE FROM simulation_factors WHERE simulation_id=?')->execute([$sid]);
    $pdo->prepare('DELETE FROM simulation_state WHERE simulation_id=?')->execute([$sid]);
    $pdo->prepare('DELETE FROM simulations WHERE simulation_id=?')->execute([$sid]);
};
$cleanupGame=function(int $gid)use($pdo){
    $pdo->prepare('DELETE FROM host_occasion_ledger WHERE game_id=?')->execute([$gid]);
    $pdo->prepare('DELETE FROM game_players WHERE game_id=?')->execute([$gid]);
    $pdo->prepare('DELETE FROM games WHERE game_id=?')->execute([$gid]);
};
// Synthetic decision fixture: a simulation + one OPEN decision + one event.
// Returns [sid, decision_id, character_id, event_id].
$makeDecision=function()use($pdo,$plotId,$versionId){
    $sid=SimulationService::create('EMPTY_THRONE',300,[],$versionId);
    $ch=$pdo->prepare('SELECT character_id FROM characters WHERE simulation_id=? ORDER BY character_id LIMIT 1');$ch->execute([$sid]);$cid=(int)$ch->fetchColumn();
    if(!$cid)throw new RuntimeException('No character cast in the test simulation.');
    $pdo->prepare('INSERT INTO simulation_events(simulation_id,event_second,phase,event_type,actor_character_id,event_code,headline,event_text,visibility) VALUES(?,0,"EXPOSITION","WORLD",NULL,"TEST_EVENT","Test event","A test event.","WORLD")')->execute([$sid]);
    $eventId=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO decisions(simulation_id,character_id,event_id,prompt_text,status,opens_at_second,closes_at_second) VALUES(?,?,?,?,"OPEN",0,100000)')->execute([$sid,$cid,$eventId,'Test decision']);
    $did=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO decision_options(decision_id,option_code,label,sort_order) VALUES(?,?,?,1)')->execute([$did,'TEST_OPTION','Test option']);
    return [$sid,$did,$cid,$eventId];
};
$applyOption=new ReflectionMethod('BehaviorService','applyOption');
$applyOption->setAccessible(true);
$syntheticOpt=['option_code'=>'TEST_OPTION','result_text'=>'The test option was chosen.','label'=>'Test option','template_option_id'=>0];

$testSids=[];$testGids=[];
try{
    // --- 1. applyOption resolves exactly once ---------------------------
    [$sid,$did,$cid,$eventId]=$makeDecision();$testSids[]=$sid;
    $d=$pdo->prepare('SELECT * FROM decisions WHERE decision_id=?');$d->execute([$did]);$drow=$d->fetch();
    $out1=$applyOption->invoke(null,$sid,$drow,$syntheticOpt,0,'PLAYER');
    $check($out1!==null&&$out1['status']==='CHOSEN'&&$out1['chosen_option_code']==='TEST_OPTION','applyOption first resolution returns CHOSEN/TEST_OPTION');
    // Second resolution of the same decision, same option: idempotent — the
    // existing outcome is returned and nothing is applied twice.
    $d->execute([$did]);$drow=$d->fetch();
    $out2=$applyOption->invoke(null,$sid,$drow,$syntheticOpt,0,'PLAYER');
    $check($out2!==null&&$out2['status']==='CHOSEN'&&$out2['chosen_option_code']==='TEST_OPTION','applyOption second resolution (same option) returns the existing outcome');
    $h=$pdo->prepare("SELECT COUNT(*) FROM character_history WHERE simulation_id=? AND character_id=? AND history_type='DECISION'");$h->execute([$sid,$cid]);
    $check((int)$h->fetchColumn()===1,'exactly one DECISION history row after two resolutions');
    $h=$pdo->prepare("SELECT COUNT(*) FROM simulation_events WHERE simulation_id=? AND event_type='PLAYER' AND event_code LIKE 'PLAYER_ACTION%'");$h->execute([$sid]);
    $check((int)$h->fetchColumn()===1,'exactly one PLAYER_ACTION world log after two resolutions');
    $st=$pdo->prepare('SELECT status,chosen_option_code FROM decisions WHERE decision_id=?');$st->execute([$did]);$row=$st->fetch();
    $check($row['status']==='CHOSEN'&&$row['chosen_option_code']==='TEST_OPTION','decision row is CHOSEN/TEST_OPTION after two resolutions');

    // --- 2. expire claims atomically; a chosen decision is never expired --
    BehaviorService::expire($sid,100000);
    $st->execute([$did]);$row=$st->fetch();
    $check($row['status']==='CHOSEN','expire() does not overwrite a decision already CHOSEN');
    $h=$pdo->prepare("SELECT COUNT(*) FROM character_history WHERE simulation_id=? AND character_id=? AND history_type='NO_ACTION'");$h->execute([$sid,$cid]);
    $check((int)$h->fetchColumn()===0,'no NO_ACTION history for a decision that was chosen first');

    // --- 3. expire + no-action marker are exactly-once -------------------
    [$sid2,$did2,$cid2,$eventId2]=$makeDecision();$testSids[]=$sid2;
    // expire() uses a strict closes_at_second < second predicate (the same
    // convention advance() relies on — its completion path calls
    // expire($sid,$next+1)), so the fixture decision closing at 100000
    // expires at 100001, not at 100000.
    BehaviorService::expire($sid2,100001);
    BehaviorService::expire($sid2,100001);
    $st->execute([$did2]);$row=$st->fetch();
    $check($row['status']==='NO_ACTION','expired decision is NO_ACTION');
    $h=$pdo->prepare("SELECT COUNT(*) FROM character_history WHERE simulation_id=? AND character_id=? AND history_type='NO_ACTION'");$h->execute([$sid2,$cid2]);
    $check((int)$h->fetchColumn()===1,'exactly one NO_ACTION history row after two expire() calls');
    CausalService::applyNoActionDecision($sid2,$did2);
    CausalService::applyNoActionDecision($sid2,$did2);
    $m=$pdo->prepare("SELECT COUNT(*) FROM simulation_state WHERE simulation_id=? AND state_key=?");$m->execute([$sid2,'NO_ACTION_EFFECT_'.$did2]);
    $check((int)$m->fetchColumn()===1,'no-action marker exists exactly once after two applyNoActionDecision() calls');
    $h=$pdo->prepare("SELECT COUNT(*) FROM simulation_events WHERE simulation_id=? AND event_code=?");$h->execute([$sid2,'NO_ACTION_EFFECT_'.$did2]);
    $check((int)$h->fetchColumn()<=1,'at most one NO_ACTION_EFFECT world event after two applyNoActionDecision() calls');

    // --- 4. createGame is atomic: a failed create leaves no orphan -------
    // Use a non-admin user with no occasions (commerce enabled). If commerce
    // is disabled this check is skipped — the atomicity then has no ledger
    // failure to trigger it.
    if(CommerceService::enabled()){
        $u=$pdo->query('SELECT user_id FROM users WHERE is_admin=0 ORDER BY user_id LIMIT 1')->fetch();
        if($u){
            $uid=(int)$u['user_id'];
            // Drain this user's available occasions for the test by spending
            // nothing — instead assert via a user who cannot host: use a
            // bogus duration so the create fails AFTER the simulation would
            // have been created in the old code path.
            $before=(int)$pdo->query('SELECT COUNT(*) FROM simulations')->fetchColumn();
            $thrown=false;
            try{LobbyService::createGame($uid,'EMPTY_THRONE',999999);}catch(Throwable $e){$thrown=true;}
            $after=(int)$pdo->query('SELECT COUNT(*) FROM simulations')->fetchColumn();
            $check($thrown,'createGame with an unavailable duration throws');
            $check($after===$before,'no orphan simulation after a failed createGame');
        }else{echo "SKIP: no non-admin user fixture for the orphan test.\n";}
    }else{echo "SKIP: commerce disabled, orphan test not applicable.\n";}

    // --- 5. idempotency key reuses the lobby -----------------------------
    $key='guard-test-'.bin2hex(random_bytes(8));
    $state1=LobbyService::createGame($adminId,'EMPTY_THRONE',300,$key);
    $gid1=(int)$state1['game']['game_id'];$testGids[]=$gid1;
    $sid1=(int)$state1['game']['simulation_id'];$testSids[]=$sid1;
    $state2=LobbyService::createGame($adminId,'EMPTY_THRONE',300,$key);
    $gid2=(int)$state2['game']['game_id'];
    $check($gid2===$gid1,'createGame with the same idempotency key returns the same lobby');
    $cnt=$pdo->prepare('SELECT COUNT(*) FROM games WHERE idempotency_key=? AND deleted_at_utc IS NULL');$cnt->execute([$key]);
    $check((int)$cnt->fetchColumn()===1,'exactly one game row for the idempotency key');
    $cnt=$pdo->prepare('SELECT COUNT(*) FROM simulations WHERE simulation_id=?');$cnt->execute([$sid1]);
    $check((int)$cnt->fetchColumn()===1,'exactly one simulation for the idempotency key');

    // --- 6. startGame is idempotent --------------------------------------
    LobbyService::startGame($gid1,$adminId);
    LobbyService::startGame($gid1,$adminId);
    $g=$pdo->prepare('SELECT status FROM games WHERE game_id=?');$g->execute([$gid1]);
    $check($g->fetchColumn()==='RUNNING','game is RUNNING after two startGame() calls');
    $s=$pdo->prepare('SELECT status FROM simulations WHERE simulation_id=?');$s->execute([$sid1]);
    $check($s->fetchColumn()==='RUNNING','simulation is RUNNING after two startGame() calls');
    if(CommerceService::enabled()){
        $c=$pdo->prepare("SELECT COUNT(*) FROM host_occasion_ledger WHERE game_id=? AND entry_type='CONSUME'");$c->execute([$gid1]);
        $check((int)$c->fetchColumn()<=1,'at most one CONSUME ledger entry after two startGame() calls');
    }
}catch(Throwable $e){
    $failures[]='unexpected exception: '.$e->getMessage();
    echo 'FAIL unexpected exception: '.$e->getMessage()."\n";
}finally{
    foreach($testGids as $gid)$cleanupGame($gid);
    foreach($testSids as $sid)$cleanupSim($sid);
    echo "cleanup done.\n";
}
if($failures){echo 'FAILURES: '.implode('; ',$failures)."\n";exit(1);}
echo "decision_atomicity_guard_test: all checks passed.\n";
