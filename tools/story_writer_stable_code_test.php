<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/app/bootstrap.php';
require_once dirname(__DIR__).'/app/StoryAuthoringService.php';

$pdo=db();
$plot=$pdo->query("SELECT p.plot_id FROM plots p WHERE EXISTS(SELECT 1 FROM plot_world_events e JOIN plot_decision_templates d ON d.plot_id=e.plot_id AND d.event_code=e.event_code WHERE e.plot_id=p.plot_id) ORDER BY p.plot_id LIMIT 1")->fetch();
$user=$pdo->query('SELECT user_id FROM users ORDER BY is_admin DESC,user_id LIMIT 1')->fetch();
if(!$plot||!$user)throw new RuntimeException('Stable-code test needs a seeded story and user.');

$plotId=(int)$plot['plot_id'];
$writer=['user_id'=>(int)$user['user_id'],'is_admin'=>1];
$referenced=$pdo->prepare('SELECT e.* FROM plot_world_events e WHERE e.plot_id=? AND EXISTS(SELECT 1 FROM plot_decision_templates d WHERE d.plot_id=e.plot_id AND d.event_code=e.event_code) LIMIT 1');
$referenced->execute([$plotId]);
$event=$referenced->fetch();
if(!$event)throw new RuntimeException('Stable-code test could not find a referenced event.');

$pdo->beginTransaction();
try{
    $locked=false;
    try{
        StoryAuthoringService::saveWorldEvent($writer,$plotId,[
            'plot_world_event_id'=>(int)$event['plot_world_event_id'],
            'event_second'=>(int)$event['event_second'],
            'phase'=>$event['phase'],
            'event_code'=>$event['event_code'].'_RENAMED',
            'headline'=>$event['headline'],
            'event_text'=>$event['event_text'],
            'sort_order'=>(int)$event['sort_order'],
            'active'=>(int)$event['active'],
        ]);
    }catch(RuntimeException $error){$locked=str_contains($error->getMessage(),'Stable code is locked')&&str_contains($error->getMessage(),'DECISIONS');}
    if(!$locked)throw new RuntimeException('A referenced event code was not dependency-locked.');

    $temporary='STABLE_CODE_TEST_'.strtoupper(bin2hex(random_bytes(4)));
    $eventId=StoryAuthoringService::saveWorldEvent($writer,$plotId,['event_second'=>1,'phase'=>'EXPOSITION','event_code'=>$temporary,'headline'=>'Stable code test','event_text'=>'Temporary test event.','sort_order'=>999,'active'=>1]);
    StoryAuthoringService::saveWorldEvent($writer,$plotId,['plot_world_event_id'=>$eventId,'event_second'=>1,'phase'=>'EXPOSITION','event_code'=>$temporary.'_RENAMED','headline'=>'Stable code test','event_text'=>'Temporary test event.','sort_order'=>999,'active'=>1]);
    $pdo->rollBack();
    echo "Story Writer stable-code locking passed.\n";
}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw$error;}
