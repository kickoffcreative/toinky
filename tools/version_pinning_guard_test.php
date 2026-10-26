<?php
declare(strict_types=1);
// Guard test: content version pinning (audit task 5, Phase 1).
// Simulations record the version they were created from plus a workspace
// checksum; hosted games fail closed without a published version; drift
// (a later draft edit) is detected by the throttled drift check.
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only.');}

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/SimulationService.php';
require_once $root.'/app/LobbyService.php';

$pdo=db();
$failures=[];
$check=function(bool $ok,string $label)use(&$failures){echo ($ok?'PASS':'FAIL').' '.$label."\n";if(!$ok)$failures[]=$label;};
$admin=$pdo->query('SELECT * FROM users WHERE is_admin=1 ORDER BY user_id LIMIT 1')->fetch();
if(!$admin)throw new RuntimeException('Version pinning test needs an administrator fixture.');
$adminId=(int)$admin['user_id'];

$plot=$pdo->prepare("SELECT plot_id FROM plots WHERE code='EMPTY_THRONE' AND status IN ('draft','active') LIMIT 1");
$plot->execute();$plotId=(int)$plot->fetchColumn();
if(!$plotId){echo "SKIP: no EMPTY_THRONE plot fixture.\n";exit(0);}
$ver=$pdo->prepare('SELECT plot_version_id,snapshot_json FROM plot_versions WHERE plot_id=? ORDER BY version_no DESC LIMIT 1');
$ver->execute([$plotId]);$vrow=$ver->fetch();
if(!$vrow){echo "SKIP: no submitted version for EMPTY_THRONE.\n";exit(0);}
$versionId=(int)$vrow['plot_version_id'];
$snapArr=json_decode((string)$vrow['snapshot_json'],true);
$expectedChecksum=hash('sha256',json_encode($snapArr['content']??[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

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

$sidPinned=0;$sidLegacy=0;
$eventId=0;$eventHeadline=null;$savedPointer=null;
try{
    // (i) Pinned creation records the version and the workspace checksum.
    $sidPinned=SimulationService::create('EMPTY_THRONE',300,[],$versionId);
    $check($sidPinned>0,'pinned simulation created');
    $row=$pdo->prepare('SELECT plot_version_id,content_checksum FROM simulations WHERE simulation_id=?');$row->execute([$sidPinned]);$r=$row->fetch();
    $check((int)$r['plot_version_id']===$versionId,'pinned simulation records the version id');
    $check((string)$r['content_checksum']===$expectedChecksum,'pinned simulation records the workspace checksum');

    // (ii) Legacy creation (no version) records NULLs.
    $sidLegacy=SimulationService::create('EMPTY_THRONE',300,[]);
    $row->execute([$sidLegacy]);$r=$row->fetch();
    $check($r['plot_version_id']===null&&$r['content_checksum']===null,'legacy simulation stays unpinned (NULL version + checksum)');

    // (iii) Drift detection: change a draft field, expect drift; restore it.
    $ev=$pdo->prepare('SELECT plot_world_event_id,headline FROM plot_world_events WHERE plot_id=? ORDER BY plot_world_event_id LIMIT 1');
    $ev->execute([$plotId]);$erow=$ev->fetch();
    if($erow){
        $eventId=(int)$erow['plot_world_event_id'];$eventHeadline=$erow['headline'];
        $pdo->prepare('UPDATE plot_world_events SET headline=? WHERE plot_world_event_id=?')->execute(['DRIFT TEST — '.$eventHeadline,$eventId]);
        $drift=SimulationService::checkVersionDrift($sidPinned);
        $check($drift!==null,'drift detected after a draft edit');
        $check($drift!==null&&(int)$drift['plot_version_id']===$versionId,'drift report names the pinned version');
        $pdo->prepare('UPDATE plot_world_events SET headline=? WHERE plot_world_event_id=?')->execute([$eventHeadline,$eventId]);
        $eventId=0;$eventHeadline=null;
    }else{echo "SKIP: no world event to edit for the drift check.\n";}

    // (iv) Hosted creation fails closed when no published version is recorded.
    $ptr=$pdo->prepare('SELECT published_plot_version_id FROM plots WHERE plot_id=?');$ptr->execute([$plotId]);$savedPointer=$ptr->fetchColumn();
    $pdo->prepare('UPDATE plots SET published_plot_version_id=NULL WHERE plot_id=?')->execute([$plotId]);
    $failed=false;$msg='';
    try{LobbyService::createGame($adminId,'EMPTY_THRONE',3600);}
    catch(Throwable $e){$failed=true;$msg=$e->getMessage();}
    $check($failed,'createGame fails closed with no published version');
    $check(strpos($msg,'no published version')!==false,'fail-closed message names the missing published version');
    $pdo->prepare('UPDATE plots SET published_plot_version_id=? WHERE plot_id=?')->execute([$savedPointer,$plotId]);
    $savedPointer=null;

    // (v) Backfill sanity: report the published pointer for each active story.
    $act=$pdo->query("SELECT code,published_plot_version_id FROM plots WHERE status='active' ORDER BY code")->fetchAll();
    foreach($act as $a)echo 'INFO active story '.(string)$a['code'].' published_plot_version_id='.($a['published_plot_version_id']===null?'NULL':(string)$a['published_plot_version_id'])."\n";
}catch(Throwable $e){
    $failures[]='unexpected exception: '.$e->getMessage();
    echo 'FAIL unexpected exception: '.$e->getMessage()."\n";
}finally{
    if($eventId&&$eventHeadline!==null)$pdo->prepare('UPDATE plot_world_events SET headline=? WHERE plot_world_event_id=?')->execute([$eventHeadline,$eventId]);
    if($savedPointer!==null||true){$ptr=$pdo->prepare('SELECT published_plot_version_id FROM plots WHERE plot_id=?');$ptr->execute([$plotId]);if($ptr->fetchColumn()===null&&$savedPointer!==null)$pdo->prepare('UPDATE plots SET published_plot_version_id=? WHERE plot_id=?')->execute([$savedPointer,$plotId]);}
    if($sidPinned)$cleanupSim($sidPinned);
    if($sidLegacy)$cleanupSim($sidLegacy);
    echo "cleanup done.\n";
}
if($failures){echo 'FAILURES: '.implode('; ',$failures)."\n";exit(1);}
echo "version_pinning_guard_test: all checks passed.\n";
