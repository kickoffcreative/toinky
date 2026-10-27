<?php
declare(strict_types=1);
// Guard test: SimulationContent pinned reads (audit task 5, Phase 2).
// A pinned simulation reads world events, decision templates/options and
// ending fragments from its version snapshot even after the live draft is
// edited; a legacy (unpinned) simulation follows the live edit.
// auditVersion() fails closed on a snapshot missing a workspace key.
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only.');}

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/SimulationService.php';
require_once $root.'/app/BehaviorService.php';
require_once $root.'/app/ResolutionService.php';
require_once $root.'/app/StoryAuthoringService.php';

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

$sidPinned=0;$sidLegacy=0;
$eventId=0;$eventHeadline=null;$optionId=0;$optionLabel=null;
$fragId=0;$fragText=null;$savedHeadline=null;$testVersionId=0;
try{
    // One pinned and one legacy simulation from the same story.
    $sidPinned=SimulationService::create('EMPTY_THRONE',300,[],$versionId);
    $sidLegacy=SimulationService::create('EMPTY_THRONE',300,[]);
    $check($sidPinned>0&&$sidLegacy>0,'pinned + legacy simulations created');

    // (i)+(ii) Event headline + decision option label: pinned reads the
    // snapshot, legacy follows the live edit.
    $ev=$pdo->prepare('SELECT e.plot_world_event_id,e.event_code,e.trigger_percent,e.phase,e.headline,t.template_id,t.role_code FROM plot_world_events e JOIN plot_decision_templates t ON t.event_code=e.event_code AND t.active=1 JOIN plot_roles r ON r.plot_id=e.plot_id AND r.role_code=t.role_code AND r.active=1 AND r.simulation_active=1 WHERE e.plot_id=? AND e.active=1 AND e.trigger_percent>0 AND e.trigger_percent<90 AND (SELECT COUNT(*) FROM plot_decision_template_options o WHERE o.template_id=t.template_id AND o.active=1)>=2 ORDER BY e.trigger_percent,e.plot_world_event_id,t.template_id LIMIT 1');
    $ev->execute([$plotId]);$erow=$ev->fetch();
    if($erow){
        $opt=$pdo->prepare('SELECT o.template_option_id,o.label FROM plot_decision_template_options o WHERE o.template_id=? AND o.active=1 AND NOT EXISTS (SELECT 1 FROM plot_option_conditions c WHERE c.template_option_id=o.template_option_id) ORDER BY o.sort_order,o.template_option_id LIMIT 1');
        $opt->execute([$erow['template_id']]);$orow=$opt->fetch();
        if(!$orow){echo "SKIP: no condition-free option for the fixture template.\n";}
        else{
            $eventId=(int)$erow['plot_world_event_id'];$eventHeadline=$erow['headline'];
            $optionId=(int)$orow['template_option_id'];$optionLabel=$orow['label'];
            $pdo->prepare('UPDATE plot_world_events SET headline=? WHERE plot_world_event_id=?')->execute(['GUARD TEST EDIT '.$eventHeadline,$eventId]);
            $pdo->prepare('UPDATE plot_decision_template_options SET label=? WHERE template_option_id=?')->execute(['GUARD TEST OPTION LABEL',$optionId]);
            $second=(int)round((float)$erow['trigger_percent']*300/100);
            SimulationService::advance($sidPinned,$second);
            SimulationService::advance($sidLegacy,$second);
            $h=$pdo->prepare('SELECT headline FROM simulation_events WHERE simulation_id=? AND event_code=?');$h->execute([$sidPinned,$erow['event_code']]);$pinnedHeadline=$h->fetchColumn();
            $h->execute([$sidLegacy,$erow['event_code']]);$legacyHeadline=$h->fetchColumn();
            $check($pinnedHeadline===$eventHeadline,'pinned simulation fires the snapshot event headline');
            $check($legacyHeadline==='GUARD TEST EDIT '.$eventHeadline,'legacy simulation fires the edited live headline');
            $ol=$pdo->prepare('SELECT DISTINCT o.label FROM decision_options o JOIN decisions d ON d.decision_id=o.decision_id JOIN simulation_events se ON se.event_id=d.event_id WHERE d.simulation_id=? AND se.event_code=?');$ol->execute([$sidPinned,$erow['event_code']]);$pinnedLabels=array_column($ol->fetchAll(),'label');
            $ol->execute([$sidLegacy,$erow['event_code']]);$legacyLabels=array_column($ol->fetchAll(),'label');
            $check(in_array($optionLabel,$pinnedLabels,true),'pinned decision offers the snapshot option label');
            $check(!in_array('GUARD TEST OPTION LABEL',$pinnedLabels,true),'pinned decision ignores the live option-label edit');
            $check(in_array('GUARD TEST OPTION LABEL',$legacyLabels,true),'legacy decision offers the edited option label');
            $pdo->prepare('UPDATE plot_world_events SET headline=? WHERE plot_world_event_id=?')->execute([$eventHeadline,$eventId]);
            $pdo->prepare('UPDATE plot_decision_template_options SET label=? WHERE template_option_id=?')->execute([$optionLabel,$optionId]);
            $eventId=0;$eventHeadline=null;$optionId=0;$optionLabel=null;
        }
    }else{echo "SKIP: no EMPTY_THRONE event with a cast decision template.\n";}

    // (i)+(ii) Ending fragment + headline: pinned renders the snapshot,
    // legacy renders the live edit.
    $sec=$pdo->prepare('SELECT section_code FROM plot_resolution_fragments WHERE plot_id=? AND active=1 GROUP BY section_code ORDER BY MIN(section_order),section_code LIMIT 1');
    $sec->execute([$plotId]);$section=$sec->fetchColumn();
    if($section){
        $m=new ReflectionMethod(ResolutionService::class,'pickFragmentRow');$m->setAccessible(true);
        $frag=$m->invoke(null,$sidLegacy,$plotId,(string)$section);
        if(!$frag||str_contains((string)$frag['fragment_text'],'{')){echo "SKIP: no deterministic ending fragment without placeholders.\n";}
        else{
            $fragId=(int)$frag['resolution_fragment_id'];$fragText=(string)$frag['fragment_text'];
            $hq=$pdo->prepare('SELECT resolution_headline FROM plots WHERE plot_id=?');$hq->execute([$plotId]);$savedHeadline=$hq->fetchColumn();
            // The pinned headline comes from the snapshot's plot row (engine
            // default when empty), not from the live draft — the live value
            // may have changed since the version was submitted.
            $snapHeadline=trim((string)($snapArr['plot']['resolution_headline']??''))?:'What Actually Remains';
            $pdo->prepare('UPDATE plot_resolution_fragments SET fragment_text=? WHERE resolution_fragment_id=?')->execute(['GUARD TEST ENDING MARKER',$fragId]);
            $pdo->prepare('UPDATE plots SET resolution_headline=? WHERE plot_id=?')->execute(['GUARD TEST HEADLINE MARKER',$plotId]);
            $ins=$pdo->prepare("INSERT INTO simulation_events(simulation_id,event_second,phase,event_type,event_code,headline,event_text,visibility) VALUES(?,0,'RESOLUTION','SYSTEM','FINAL_STATE','','','DIRECTOR')");
            $ins->execute([$sidPinned]);$ins->execute([$sidLegacy]);
            ResolutionService::refresh($sidPinned);
            ResolutionService::refresh($sidLegacy);
            $e=$pdo->prepare("SELECT headline,event_text FROM simulation_events WHERE simulation_id=? AND event_code='FINAL_STATE'");$e->execute([$sidPinned]);$pr=$e->fetch();
            $e->execute([$sidLegacy]);$lr=$e->fetch();
            $check($pr&&str_contains((string)$pr['event_text'],$fragText),'pinned ending keeps the snapshot fragment text');
            $check($pr&&!str_contains((string)$pr['event_text'],'GUARD TEST ENDING MARKER'),'pinned ending ignores the live fragment edit');
            $check($lr&&str_contains((string)$lr['event_text'],'GUARD TEST ENDING MARKER'),'legacy ending uses the edited fragment text');
            $check($pr&&$pr['headline']===$snapHeadline,'pinned ending keeps the snapshot headline');
            $check($lr&&$lr['headline']==='GUARD TEST HEADLINE MARKER','legacy ending uses the edited headline');
            $pdo->prepare('UPDATE plot_resolution_fragments SET fragment_text=? WHERE resolution_fragment_id=?')->execute([$fragText,$fragId]);
            $pdo->prepare('UPDATE plots SET resolution_headline=? WHERE plot_id=?')->execute([$savedHeadline,$plotId]);
            $fragId=0;$fragText=null;$savedHeadline=null;
        }
    }else{echo "SKIP: no active resolution fragments for EMPTY_THRONE.\n";}

    // (iii) auditVersion() fails closed on a snapshot missing a key.
    $maxv=$pdo->prepare('SELECT MAX(version_no) FROM plot_versions WHERE plot_id=?');$maxv->execute([$plotId]);$nextNo=(int)$maxv->fetchColumn()+1;
    $stripped=$snapArr;unset($stripped['content']['events']);
    $adminId=$pdo->query('SELECT user_id FROM users WHERE is_admin=1 ORDER BY user_id LIMIT 1')->fetchColumn();if(!$adminId)throw new RuntimeException('SimulationContent test needs an administrator fixture.');
    $ins=$pdo->prepare('INSERT INTO plot_versions(plot_id,version_no,workflow_status,snapshot_json,submitted_by_user_id) VALUES(?,?,\'SUBMITTED\',?,?)');
    $ins->execute([$plotId,$nextNo,json_encode($stripped,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(int)$adminId]);
    $testVersionId=(int)$pdo->lastInsertId();
    $threw=false;$msg='';
    try{StoryAuthoringService::auditVersion($testVersionId);}
    catch(Throwable $e){$threw=true;$msg=$e->getMessage();}
    $check($threw,'auditVersion throws on a snapshot missing a key');
    $check(str_contains($msg,'events'),'auditVersion names the missing key');
    $pdo->prepare('DELETE FROM plot_versions WHERE plot_version_id=?')->execute([$testVersionId]);
    $testVersionId=0;
}catch(Throwable $e){
    $failures[]='unexpected exception: '.$e->getMessage();
    echo 'FAIL unexpected exception: '.$e->getMessage()."\n";
}finally{
    if($eventId&&$eventHeadline!==null)$pdo->prepare('UPDATE plot_world_events SET headline=? WHERE plot_world_event_id=?')->execute([$eventHeadline,$eventId]);
    if($optionId&&$optionLabel!==null)$pdo->prepare('UPDATE plot_decision_template_options SET label=? WHERE template_option_id=?')->execute([$optionLabel,$optionId]);
    if($fragId&&$fragText!==null)$pdo->prepare('UPDATE plot_resolution_fragments SET fragment_text=? WHERE resolution_fragment_id=?')->execute([$fragText,$fragId]);
    if($savedHeadline!==null)$pdo->prepare('UPDATE plots SET resolution_headline=? WHERE plot_id=?')->execute([$savedHeadline,$plotId]);
    if($testVersionId)$pdo->prepare('DELETE FROM plot_versions WHERE plot_version_id=?')->execute([$testVersionId]);
    if($sidPinned)$cleanupSim($sidPinned);
    if($sidLegacy)$cleanupSim($sidLegacy);
    echo "cleanup done.\n";
}
if($failures){echo 'FAILURES: '.implode('; ',$failures)."\n";exit(1);}
echo "simulation_content_guard_test: all checks passed.\n";
