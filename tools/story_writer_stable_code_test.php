<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/app/bootstrap.php';
require_once dirname(__DIR__).'/app/StoryAuthoringService.php';

$pdo=db();
$plot=$pdo->query("SELECT p.plot_id FROM plots p WHERE EXISTS(SELECT 1 FROM plot_world_events e JOIN plot_decision_templates d ON d.plot_id=e.plot_id AND d.event_code=e.event_code AND d.active=1 WHERE e.plot_id=p.plot_id AND e.active=1) ORDER BY p.plot_id LIMIT 1")->fetch();
$user=$pdo->query('SELECT user_id FROM users ORDER BY is_admin DESC,user_id LIMIT 1')->fetch();
if(!$plot||!$user)throw new RuntimeException('Stable-code test needs a seeded story and user.');

$plotId=(int)$plot['plot_id'];
$writer=['user_id'=>(int)$user['user_id'],'is_admin'=>1];
$referenced=$pdo->prepare('SELECT e.* FROM plot_world_events e WHERE e.plot_id=? AND e.active=1 AND EXISTS(SELECT 1 FROM plot_decision_templates d WHERE d.plot_id=e.plot_id AND d.event_code=e.event_code AND d.active=1) LIMIT 1');
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

    $emptyCode='EMPTY_STORY_CODE_TEST_'.strtoupper(bin2hex(random_bytes(4)));$renamedCode=$emptyCode.'_RENAMED';$genreId=(int)$pdo->query('SELECT genre_id FROM story_genres WHERE active=1 ORDER BY sort_order,genre_id LIMIT 1')->fetchColumn();
    $pdo->beginTransaction();
    try{
        $pdo->prepare('INSERT INTO plots(code,title,archetype,genre,genre_id,setting_name,max_players,max_npcs,status,workflow_status,author_user_id,scenario_text) VALUES(?,?,?,?,?,?,?,?,"draft","DRAFT",?,?)')->execute([$emptyCode,'Empty story code test','Interactive Drama','Test',$genreId,'Unspecified setting',1,0,(int)$user['user_id'],str_repeat('A',80)]);
        $emptyPlotId=(int)$pdo->lastInsertId();StoryAuthoringService::updateOverview($writer,$emptyPlotId,['story_code'=>$renamedCode,'title'=>'Empty story code test','genre_id'=>$genreId,'archetype'=>'Interactive Drama','setting_name'=>'Unspecified setting','max_players'=>1,'max_npcs'=>0,'scenario_text'=>str_repeat('A',80)]);
        $check=$pdo->prepare('SELECT code FROM plots WHERE plot_id=?');$check->execute([$emptyPlotId]);if((string)$check->fetchColumn()!==$renamedCode)throw new RuntimeException('An empty story code could not be renamed.');
        $deleteCode='DELETE_STORY_CODE_TEST_'.strtoupper(bin2hex(random_bytes(4)));$pdo->prepare('INSERT INTO plots(code,title,archetype,genre,genre_id,setting_name,max_players,max_npcs,status,workflow_status,author_user_id,scenario_text) VALUES(?,?,?,?,?,?,?,?,"draft","DRAFT",?,?)')->execute([$deleteCode,'Delete story test','Interactive Drama','Test',$genreId,'Unspecified setting',1,0,(int)$user['user_id'],str_repeat('A',80)]);$deletePlotId=(int)$pdo->lastInsertId();StoryAuthoringService::deleteStory($writer,$deletePlotId);$check->execute([$deletePlotId]);if($check->fetchColumn()!==false)throw new RuntimeException('An empty story could not be deleted.');
        $historyCode='PLAY_HISTORY_CODE_TEST_'.strtoupper(bin2hex(random_bytes(4)));$pdo->prepare('INSERT INTO plots(code,title,archetype,genre,genre_id,setting_name,max_players,max_npcs,status,workflow_status,author_user_id,scenario_text) VALUES(?,?,?,?,?,?,?,?,"draft","DRAFT",?,?)')->execute([$historyCode,'Play history code test','Interactive Drama','Test',$genreId,'Unspecified setting',1,0,(int)$user['user_id'],str_repeat('A',80)]);$historyPlotId=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO simulations(plot_id) VALUES(?)')->execute([$historyPlotId]);$historyLocked=false;
        try{StoryAuthoringService::updateOverview($writer,$historyPlotId,['story_code'=>$historyCode.'_RENAMED','title'=>'Play history code test','genre_id'=>$genreId,'archetype'=>'Interactive Drama','setting_name'=>'Unspecified setting','max_players'=>1,'max_npcs'=>0,'scenario_text'=>str_repeat('A',80)]);}catch(RuntimeException $error){$historyLocked=str_contains($error->getMessage(),'Stable Story Code is locked')&&str_contains($error->getMessage(),'PLAY HISTORY');}
        if(!$historyLocked)throw new RuntimeException('A story with play history was not code-locked.');
        $deleteHistoryLocked=false;try{StoryAuthoringService::deleteStory($writer,$historyPlotId);}catch(RuntimeException $error){$deleteHistoryLocked=str_contains($error->getMessage(),'Cannot delete this story')&&str_contains($error->getMessage(),'PLAY HISTORY');}if(!$deleteHistoryLocked)throw new RuntimeException('A story with play history was not deletion-locked.');
        StoryAuthoringService::saveLocation($writer,$emptyPlotId,['location_code'=>'TEST_PLACE','label_text'=>'Test Place','description'=>'','sort_order'=>1,'active'=>1]);$locked=false;
        try{StoryAuthoringService::updateOverview($writer,$emptyPlotId,['story_code'=>$emptyCode,'title'=>'Empty story code test','genre_id'=>$genreId,'archetype'=>'Interactive Drama','setting_name'=>'Unspecified setting','max_players'=>1,'max_npcs'=>0,'scenario_text'=>str_repeat('A',80)]);}catch(RuntimeException $error){$locked=str_contains($error->getMessage(),'Stable Story Code is locked')&&str_contains($error->getMessage(),'LOCATIONS');}
        if(!$locked)throw new RuntimeException('A story with a location was not code-locked.');$deleteLocationLocked=false;try{StoryAuthoringService::deleteStory($writer,$emptyPlotId);}catch(RuntimeException $error){$deleteLocationLocked=str_contains($error->getMessage(),'Cannot delete this story')&&str_contains($error->getMessage(),'LOCATIONS');}if(!$deleteLocationLocked)throw new RuntimeException('A story with a location was not deletion-locked.');$pdo->rollBack();
    }catch(Throwable$emptyError){if($pdo->inTransaction())$pdo->rollBack();throw$emptyError;}
    echo "Story Writer stable-code locking passed.\n";
}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw$error;}
