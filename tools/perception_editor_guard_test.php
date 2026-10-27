<?php
declare(strict_types=1);
// Guard test: perceptions editor (Oct 27, 2026).
// savePerception validation + edit, workspace/snapshot coverage, the pinned
// engine read (onWorldEvent on a pinned simulation), and the delete guards
// (event delete blocked while a perception references it; perception delete
// removes the row). All fixture rows live inside one transaction that rolls
// back, so nothing persists on the live install or the CI database.
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only.');}

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/StoryAuthoringService.php';
require_once $root.'/app/SimulationService.php';
require_once $root.'/app/BehaviorService.php';
require_once $root.'/app/SimulationContent.php';

$pdo=db();
$failures=[];
$check=function(bool $ok,string $label)use(&$failures){echo ($ok?'PASS':'FAIL').' '.$label."\n";if(!$ok)$failures[]=$label;};

$plot=$pdo->prepare("SELECT plot_id,code FROM plots WHERE code='EMPTY_THRONE' AND status IN ('draft','active') LIMIT 1");
$plot->execute();$plotRow=$plot->fetch();
if(!$plotRow){echo "SKIP: no EMPTY_THRONE plot fixture.\n";exit(0);}
$plotId=(int)$plotRow['plot_id'];$plotCode=(string)$plotRow['code'];
$user=$pdo->query('SELECT user_id FROM users ORDER BY is_admin DESC,user_id LIMIT 1')->fetch();
if(!$user)throw new RuntimeException('Perception editor test needs a user fixture.');
$writer=['user_id'=>(int)$user['user_id'],'is_admin'=>1];

$role=$pdo->prepare('SELECT * FROM plot_roles WHERE plot_id=? AND active=1 AND simulation_active=1 ORDER BY plot_role_id LIMIT 1');
$role->execute([$plotId]);$roleRow=$role->fetch();
if(!$roleRow){echo "SKIP: no playable role on EMPTY_THRONE.\n";exit(0);}
$roleCode=(string)$roleRow['role_code'];
$loc=$pdo->prepare('SELECT label_text FROM plot_locations WHERE plot_id=? ORDER BY plot_location_id LIMIT 1');
$loc->execute([$plotId]);$location=$loc->fetchColumn();
$location=$location===false?null:(string)$location;

$pdo->beginTransaction();
try{
    $code='PERCEPTION_TEST_'.strtoupper(bin2hex(random_bytes(4)));
    $eventId=StoryAuthoringService::saveWorldEvent($writer,$plotId,[
        'trigger_percent'=>1,'phase'=>'EXPOSITION','event_code'=>$code,
        'headline'=>'Perception test','event_text'=>'Temporary test event.','sort_order'=>999,'active'=>1,
    ]);
    $check($eventId>0,'scratch event created');

    $base=['event_code'=>$code,'role_code'=>$roleCode,'history_type'=>'OBSERVED',
        'title'=>'The archive breathes','perception_text'=>'Dust hangs in the sealed air.',
        'location_after'=>$location??''];
    $perId=StoryAuthoringService::savePerception($writer,$plotId,$base);
    $check($perId>0,'perception created');
    $row=$pdo->prepare('SELECT * FROM plot_event_perceptions WHERE perception_id=?');
    $row->execute([$perId]);$saved=$row->fetch();
    $check($saved&&$saved['title']==='The archive breathes'&&(string)$saved['role_code']===$roleCode,'perception row stores title + role');
    $check($location===null||(string)$saved['location_after']===$location,'perception row stores the move target');

    $throws=function(array $input,string $fragment)use($writer,$plotId){
        try{StoryAuthoringService::savePerception($writer,$plotId,$input);return false;}
        catch(RuntimeException $e){return str_contains($e->getMessage(),$fragment);}
    };
    $check($throws(array_merge($base,['event_code'=>'NO_SUCH_EVENT']),'Choose an event'),'save rejects an unknown event');
    $check($throws(array_merge($base,['role_code'=>'NO_SUCH_ROLE']),'Choose a character'),'save rejects an unknown character');
    $check($throws(array_merge($base,['history_type'=>'DAYDREAM']),'perception type'),'save rejects a bad perception type');
    $check($throws(array_merge($base,['location_after'=>'NOWHERE_AT_ALL']),'Add the location first'),'save rejects an undefined move target');
    $check($throws(array_merge($base,['perception_id'=>0]),'already has a perception'),'save rejects a duplicate (event, character) pair');
    $check($throws(array_merge($base,['title'=>'  ']),'needs a title'),'save rejects an empty title');
    $check($throws(array_merge($base,['perception_text'=>'']),'needs text'),'save rejects empty text');

    // Stable-code lock: renaming the event is blocked while a perception
    // references it (PERCEPTIONS usage).
    $locked=false;
    try{
        StoryAuthoringService::saveWorldEvent($writer,$plotId,[
            'plot_world_event_id'=>$eventId,'trigger_percent'=>1,'phase'=>'EXPOSITION',
            'event_code'=>$code.'_RENAMED','headline'=>'Perception test','event_text'=>'Temporary test event.','sort_order'=>999,'active'=>1,
        ]);
    }catch(RuntimeException $e){$locked=str_contains($e->getMessage(),'Stable code is locked')&&str_contains($e->getMessage(),'PERCEPTIONS');}
    $check($locked,'event rename is locked while a perception references it');

    // Edit keeps the row, changes the title, keeps the move.
    StoryAuthoringService::savePerception($writer,$plotId,array_merge($base,[
        'perception_id'=>$perId,'title'=>'The archive exhales',
    ]));
    $row->execute([$perId]);$saved=$row->fetch();
    $check($saved&&$saved['title']==='The archive exhales','perception edit updates the title');
    $check($location===null||(string)$saved['location_after']===$location,'perception edit keeps the move target');

    // Workspace + snapshot coverage.
    $w=StoryAuthoringService::workspace($plotId);
    $inWorkspace=false;foreach($w['perceptions'] as$p)if((int)$p['perception_id']===$perId)$inWorkspace=true;
    $check($inWorkspace,'workspace() carries the perception');
    $snapshot=json_encode(['plot'=>StoryAuthoringService::story($plotId),'content'=>$w],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $vq=$pdo->prepare('SELECT COALESCE(MAX(version_no),0)+1 FROM plot_versions WHERE plot_id=?');$vq->execute([$plotId]);$versionNo=(int)$vq->fetchColumn();
    $pdo->prepare('INSERT INTO plot_versions(plot_id,version_no,snapshot_json,submitted_by_user_id,workflow_status,reviewed_by_user_id,reviewed_at_utc) VALUES(?,?,?,?,\'PUBLISHED\',?,UTC_TIMESTAMP())')
        ->execute([$plotId,$versionNo,$snapshot,$writer['user_id'],$writer['user_id']]);
    $versionId=(int)$pdo->lastInsertId();
    $snap=SimulationContent::forVersion($versionId);
    $inSnapshot=false;foreach(($snap['content']['perceptions']??[]) as$p)if((int)$p['perception_id']===$perId)$inSnapshot=true;
    $check($inSnapshot,'version snapshot carries the perception');

    // Pinned engine read: a simulation pinned to this version perceives
    // through the snapshot, writes the history beat, and moves the character.
    if($location!==null){
        $sid=SimulationService::create($plotCode,300,[],$versionId);
        $check($sid>0,'pinned simulation created');
        $chars=$pdo->prepare('SELECT character_id,current_location FROM characters WHERE simulation_id=? AND plot_role_id=?');
        $chars->execute([$sid,(int)$roleRow['plot_role_id']]);$ch=$chars->fetch();
        $check($ch!==false,'pinned simulation has the role in its cast');
        if($ch!==false){
            BehaviorService::onWorldEvent($sid,$eventId,$code,0);
            $h=$pdo->prepare('SELECT COUNT(*) FROM character_history WHERE simulation_id=? AND character_id=? AND title=?');
            $h->execute([$sid,(int)$ch['character_id'],'The archive exhales']);
            $check((int)$h->fetchColumn()===1,'pinned onWorldEvent writes the perception history beat');
            $chars->execute([$sid,(int)$roleRow['plot_role_id']]);$ch=$chars->fetch();
            $check($ch&& (string)$ch['current_location']===$location,'pinned onWorldEvent moves the character to the perception target');
        }
        // Clean up the pinned simulation inside the transaction.
        $c=$pdo->prepare('SELECT character_id FROM characters WHERE simulation_id=?');$c->execute([$sid]);$ids=array_column($c->fetchAll(),'character_id');
        if($ids){$in=str_repeat('?,',count($ids)-1).'?';
            foreach(['character_stats','character_knowledge','character_history'] as$t){$d=$pdo->prepare("DELETE FROM $t WHERE character_id IN ($in)");$d->execute($ids);}
        }
        $pdo->prepare('DELETE FROM relationships WHERE simulation_id=?')->execute([$sid]);
        $pdo->prepare('DELETE FROM characters WHERE simulation_id=?')->execute([$sid]);
        $pdo->prepare('DELETE FROM simulation_factors WHERE simulation_id=?')->execute([$sid]);
        $pdo->prepare('DELETE FROM simulation_state WHERE simulation_id=?')->execute([$sid]);
        $pdo->prepare('DELETE FROM simulations WHERE simulation_id=?')->execute([$sid]);
    }else{
        echo "SKIP: no location on EMPTY_THRONE, pinned engine move untested.\n";
    }

    // Clearing the move target stores NULL.
    StoryAuthoringService::savePerception($writer,$plotId,array_merge($base,[
        'perception_id'=>$perId,'title'=>'The archive exhales','location_after'=>'',
    ]));
    $row->execute([$perId]);$saved=$row->fetch();
    $check($saved&&$saved['location_after']===null,'clearing the move target stores NULL');

    // Event delete is blocked while the perception references it.
    $blocked=false;
    try{StoryAuthoringService::deleteEntity($writer,$plotId,'event',$eventId);}
    catch(RuntimeException $e){$blocked=str_contains($e->getMessage(),'Perception');}
    $check($blocked,'event delete is blocked while a perception references it');

    // Perception delete removes the row; the event delete then unblocks
    // (not performed — the scratch event rolls back with the transaction).
    StoryAuthoringService::deleteEntity($writer,$plotId,'perception',$perId);
    $row->execute([$perId]);
    $check($row->fetch()===false,'perception delete removes the row');
}finally{
    if($pdo->inTransaction())$pdo->rollBack();
}

if($failures){echo 'FAILURES: '.implode('; ',$failures)."\n";exit(1);}
echo "All perception editor checks passed.\n";
