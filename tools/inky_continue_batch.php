<?php
declare(strict_types=1);

ini_set('display_errors','1');
error_reporting(E_ALL);
set_exception_handler(static function(Throwable$e): void {
    fwrite(STDERR,'Batch failed: '.get_class($e).': '.$e->getMessage().PHP_EOL);
    exit(1);
});

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/InkyGuideService.php';
require_once $root.'/app/InkyCowriterService.php';

// Usage: php tools/inky_continue_batch.php [plot_id] [--show-response]
$plotId=(int)($argv[1]??0);
$showResponse=in_array('--show-response',$argv,true);
if($plotId<1)$plotId=(int)db()->query('SELECT d.plot_id FROM inky_story_guide_drafts d JOIN plots p ON p.plot_id=d.plot_id WHERE p.workflow_status="DRAFT" ORDER BY d.updated_at_utc DESC LIMIT 1')->fetchColumn();
if($plotId<1)throw new RuntimeException('No saved Inky draft was found.');

$plotQuery=db()->prepare('SELECT p.plot_id,p.code,p.title,p.author_user_id,u.* FROM plots p JOIN users u ON u.user_id=p.author_user_id WHERE p.plot_id=? LIMIT 1');
$plotQuery->execute([$plotId]);$writer=$plotQuery->fetch();
if(!$writer)throw new RuntimeException('Story or story author was not found.');
$stateQuery=db()->prepare('SELECT state_json FROM inky_story_guide_drafts WHERE plot_id=? AND author_user_id=? LIMIT 1');
$stateQuery->execute([$plotId,(int)$writer['author_user_id']]);$rawState=$stateQuery->fetchColumn();
if($rawState===false)throw new RuntimeException('No Inky guide state exists for this story.');
$state=json_decode((string)$rawState,true,20,JSON_THROW_ON_ERROR);if(!is_array($state))throw new RuntimeException('The saved Inky guide state is invalid.');

$accepted=(array)($state['accepted_proposals']??[]);$brief=(array)(($state['metadata']??[])?:($state['brief']??[]));$workspace=StoryAuthoringService::workspace($plotId);$active=static fn(array$row):bool=>(int)($row['active']??1)===1;
$draftContext=['story_values'=>array_values(array_map(static fn($row)=>['target_code'=>(string)($row['target_code']??''),'label_text'=>(string)($row['label_text']??''),'effect_scope'=>(string)($row['effect_scope']??''),'value_type'=>(string)($row['value_type']??'')],array_filter($workspace['state_definitions']??[],$active))),'locations'=>array_values(array_map(static fn($row)=>['location_code'=>(string)($row['location_code']??''),'label_text'=>(string)($row['label_text']??''),'description'=>(string)($row['description']??'')],array_filter($workspace['locations']??[],$active))),'characters'=>array_values(array_map(static fn($row)=>['role_code'=>(string)($row['role_code']??''),'name'=>(string)($row['name']??''),'role_type'=>(string)($row['role_type']??'')],array_filter($workspace['characters']??[],$active))),'events'=>array_values(array_map(static fn($row)=>['event_code'=>(string)($row['event_code']??''),'headline'=>(string)($row['headline']??''),'phase'=>(string)($row['phase']??''),'event_text'=>(string)($row['event_text']??'')],array_filter($workspace['events']??[],$active))),'decisions'=>array_values(array_map(static fn($row)=>['event_code'=>(string)($row['event_code']??''),'role_code'=>(string)($row['role_code']??''),'prompt_text'=>(string)($row['prompt_text']??'')],array_filter($workspace['decisions']??[],$active)))];
$staticKeys=['title','archetype','setting_name','scenario_text','location_1_name','location_1_description','location_2_name','location_2_description','story_value_1','story_value_2','character_1','event_1','decision_1','opportunity_1','ending_section_1','ending_1','max_players','max_npcs'];$nextKey='';foreach($staticKeys as$key)if(!array_key_exists($key,$accepted)){$nextKey=$key;break;}
$dynamicNumber=static function(string$type)use($accepted,$workspace): int {$base=['location'=>2,'event'=>2,'decision'=>2][$type];$existing=count(array_filter($workspace[$type.'s']??[],static fn($row)=>(int)($row['active']??1)===1));$numbers=[];foreach(array_keys($accepted)as$key)if(preg_match('/^'.$type.'_([0-9]+)$/',(string)$key,$match)===1)$numbers[]=(int)$match[1]+1;$number=max($base,$existing+1,...$numbers);while(array_key_exists($type.'_'.$number,$accepted))$number++;return$number;};
$dynamicKeys=['location'=>'location_'.$dynamicNumber('location'),'event'=>'event_'.$dynamicNumber('event'),'decision'=>'decision_'.$dynamicNumber('decision')];$storyValueKey=!array_key_exists('story_value_1',$accepted)?'story_value_1':(!array_key_exists('story_value_2',$accepted)?'story_value_2':'story_value_1');
$tests=[['button'=>'Next Suggestion','kind'=>'proposal','key'=>$nextKey!==''?$nextKey:$dynamicKeys['decision']],['button'=>'Suggest a Story Value','kind'=>'proposal','key'=>$storyValueKey],['button'=>'Suggest a Location','kind'=>'proposal','key'=>$dynamicKeys['location']],['button'=>'Suggest an Event','kind'=>'proposal','key'=>$dynamicKeys['event']],['button'=>'Suggest a Decision','kind'=>'proposal','key'=>$dynamicKeys['decision']],['button'=>'Suggest a Character','kind'=>'cowriter','key'=>'character_'.(count($workspace['characters']??[])+1)]];
$proposalInput=static function(string$key)use($state,$brief,$accepted,$draftContext): array{return['phase'=>'proposal','proposal_key'=>$key,'answers'=>json_encode($state['answers']??[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'brief'=>json_encode($brief,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'draft_context'=>json_encode($draftContext,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'accepted_proposals'=>json_encode($accepted,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'rejected_value'=>'','current_proposal'=>'','live_edited_character'=>'','variation'=>'','editable_fields'=>'[]'];};
$latestInteraction=static function(string$purpose,int$afterId):?array{$q=db()->prepare('SELECT ai_interaction_id,purpose,success,http_status,latency_ms,error_text,response_chars,parsed_response_json,request_started_at_utc,request_completed_at_utc FROM ai_reasoner_interactions WHERE purpose=? AND ai_interaction_id>? ORDER BY ai_interaction_id DESC LIMIT 1');$q->execute([$purpose,$afterId]);return$q->fetch()?:null;};
$shape=static function(mixed$value):array{if(is_string($value))return['type'=>'text','chars'=>mb_strlen($value)];if(!is_array($value))return['type'=>gettype($value)];$out=['type'=>'object','keys'=>array_values(array_map('strval',array_keys($value)))];if(isset($value['options'])&&is_array($value['options']))$out['options']=count($value['options']);return$out;};

echo 'Story: '.(string)$writer['code'].' (plot '.$plotId.')'.PHP_EOL.'Running '.count($tests).' Continue with Inky button checks sequentially.'.PHP_EOL;$failures=0;
foreach($tests as$test){$started=microtime(true);$beforeId=(int)db()->query('SELECT COALESCE(MAX(ai_interaction_id),0) FROM ai_reasoner_interactions')->fetchColumn();$output=['button'=>$test['button'],'key'=>$test['key'],'kind'=>$test['kind']];try{if($test['kind']==='proposal'){$result=InkyGuideService::guide($writer,$proposalInput($test['key']));$output['success']=true;$output['returned_key']=(string)($result['proposal']['key']??'');$output['returned_shape']=$shape($result['proposal']['value']??null);}else{$result=InkyCowriterService::run($writer,$plotId,'suggest_character');$character=$result['character_suggestion']['character']??null;if(!is_array($character))throw new RuntimeException('Character response did not contain a usable character.');$output['success']=true;$output['returned_shape']=$shape($character);}}catch(Throwable$e){$failures++;$output['success']=false;$output['error_class']=get_class($e);$output['error']=$e->getMessage();}$output['elapsed_ms']=round((microtime(true)-$started)*1000,2);$row=$latestInteraction($test['kind']==='proposal'?'INKY_STORY_PROPOSAL':'INKY_SUGGEST_CHARACTER',$beforeId);$output['interaction_created']=(bool)$row;if($row){if(!$showResponse)unset($row['parsed_response_json']);$output['latest_interaction']=$row;}echo json_encode($output,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE).PHP_EOL;}
echo 'Completed: '.(count($tests)-$failures).' passed, '.$failures.' failed.'.PHP_EOL;exit($failures?1:0);
