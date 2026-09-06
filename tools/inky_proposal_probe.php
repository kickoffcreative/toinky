<?php
declare(strict_types=1);

ini_set('display_errors','1');
error_reporting(E_ALL);
set_exception_handler(static function(Throwable$e): void {
    fwrite(STDERR,'Probe failed: '.get_class($e).': '.$e->getMessage().PHP_EOL);
    exit(1);
});

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/InkyGuideService.php';

// Usage: php tools/inky_proposal_probe.php [plot_id] [proposal_key] [--show-response]
$plotId=(int)($argv[1]??0);
$requestedKey=trim((string)($argv[2]??''));
$showResponse=in_array('--show-response',$argv,true);
if($plotId<1){
    $latest=db()->query('SELECT d.plot_id FROM inky_story_guide_drafts d JOIN plots p ON p.plot_id=d.plot_id WHERE p.workflow_status="DRAFT" ORDER BY d.updated_at_utc DESC LIMIT 1')->fetchColumn();
    $plotId=(int)$latest;
}
if($plotId<1)throw new RuntimeException('No saved Inky draft was found.');

$plotQuery=db()->prepare('SELECT p.plot_id,p.code,p.title,p.author_user_id,u.* FROM plots p JOIN users u ON u.user_id=p.author_user_id WHERE p.plot_id=? LIMIT 1');
$plotQuery->execute([$plotId]);
$writer=$plotQuery->fetch();
if(!$writer)throw new RuntimeException('Story or story author was not found.');

$stateQuery=db()->prepare('SELECT state_json FROM inky_story_guide_drafts WHERE plot_id=? AND author_user_id=? LIMIT 1');
$stateQuery->execute([$plotId,(int)$writer['author_user_id']]);
$rawState=$stateQuery->fetchColumn();
if($rawState===false)throw new RuntimeException('No saved Inky guide state exists for this story.');
$state=json_decode((string)$rawState,true,20,JSON_THROW_ON_ERROR);
if(!is_array($state))throw new RuntimeException('The saved Inky guide state is invalid.');

$keys=['title','archetype','setting_name','scenario_text','location_1_name','location_1_description','location_2_name','location_2_description','story_value_1','story_value_2','character_1','event_1','decision_1','opportunity_1','ending_section_1','ending_1','max_players','max_npcs'];
$accepted=(array)($state['accepted_proposals']??[]);
$nextKey=$requestedKey;
if($nextKey==='')foreach($keys as $key){if(!array_key_exists($key,$accepted)){$nextKey=$key;break;}}
if($nextKey==='')throw new RuntimeException('This guide draft has no remaining proposal to request.');

$brief=(array)(($state['metadata']??[])?:($state['brief']??[]));
$input=[
    'phase'=>'proposal',
    'proposal_key'=>$nextKey,
    'answers'=>json_encode($state['answers']??[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
    'brief'=>json_encode($brief,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
    'accepted_proposals'=>json_encode($accepted,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
    'rejected_value'=>'',
    'current_proposal'=>'',
    'live_edited_character'=>'',
    'variation'=>'',
    'editable_fields'=>'[]',
];

echo 'Story: '.(string)$writer['code'].' (plot '.$plotId.')'.PHP_EOL;
echo 'Next proposal: '.$nextKey.PHP_EOL;
echo 'Saved answers: '.strlen((string)$input['answers']).' bytes'.PHP_EOL;
echo 'Saved accepted proposals: '.strlen((string)$input['accepted_proposals']).' bytes'.PHP_EOL;
$settings=AiSettingsService::settings();
echo 'AI timeout: '.(string)($settings['timeout_seconds']??'unknown').' seconds'.PHP_EOL;

$started=microtime(true);
try{
    $result=InkyGuideService::guide($writer,$input);
    echo 'PASS: proposal completed in '.round(microtime(true)-$started,2).' seconds.'.PHP_EOL;
    echo 'Returned key: '.(string)($result['proposal']['key']??'').PHP_EOL;
}catch(Throwable $e){
    echo 'FAIL: '.get_class($e).': '.$e->getMessage().PHP_EOL;
    echo 'Elapsed: '.round(microtime(true)-$started,2).' seconds.'.PHP_EOL;
    $exitCode=1;
}

$interactionQuery=db()->query("SELECT ai_interaction_id,purpose,success,http_status,latency_ms,prompt_chars,response_chars,error_text,parsed_response_json,request_started_at_utc,request_completed_at_utc FROM ai_reasoner_interactions WHERE purpose='INKY_STORY_PROPOSAL' ORDER BY ai_interaction_id DESC LIMIT 1");
$interaction=$interactionQuery->fetch();
if($interaction){
    if(!$showResponse)unset($interaction['parsed_response_json']);
    echo 'Latest proposal interaction: '.json_encode($interaction,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
}

if(isset($exitCode))exit($exitCode);
