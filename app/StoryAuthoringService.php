<?php
require_once __DIR__.'/StoryMediaService.php';
require_once __DIR__.'/DynamicOpportunityService.php';

final class StoryAuthoringService
{
    public const ARCHETYPES=[
        'Hero\'s Journey','Rags to Riches','The Quest','Voyage and Return','Overcoming the Monster',
        'Comedy','Tragedy','Rebirth','Romance','Mystery','Coming of Age','Rivalry','Survival',
        'Transformation','Redemption','Found Family','Forbidden Love','Political Intrigue','Heist','Slice of Life'
    ];
    public static function canEdit(array $user,int $plotId): bool
    {
        return StoryMediaService::canEdit($user,$plotId);
    }

    public static function stories(array $user): array
    {
        if((int)($user['is_admin']??0)===1)$q=db()->query('SELECT p.*,u.display_name author_name,g.label_text genre_label FROM plots p LEFT JOIN users u ON u.user_id=p.author_user_id LEFT JOIN story_genres g ON g.genre_id=p.genre_id ORDER BY p.updated_at_utc DESC,p.title');
        else{$q=db()->prepare('SELECT p.*,u.display_name author_name,g.label_text genre_label FROM plots p LEFT JOIN users u ON u.user_id=p.author_user_id LEFT JOIN story_genres g ON g.genre_id=p.genre_id WHERE p.author_user_id=? ORDER BY p.updated_at_utc DESC,p.title');$q->execute([$user['user_id']]);}
        return$q->fetchAll();
    }

    public static function createStory(array $user,array $input): int
    {
        $title=trim((string)($input['title']??''));$code=self::code(trim((string)($input['code']??''))?:$title);
        if($title===''||$code==='')throw new RuntimeException('Enter a story title and code.');
        $genreId=(int)($input['genre_id']??0);$q=db()->prepare('SELECT label_text FROM story_genres WHERE genre_id=? AND active=1');$q->execute([$genreId]);$genre=(string)$q->fetchColumn();if($genre==='')throw new RuntimeException('Choose a genre.');
        $pdo=db();$pdo->beginTransaction();
        try{
            $q=$pdo->prepare('INSERT INTO plots(code,title,archetype,genre,genre_id,setting_name,max_players,max_npcs,status,workflow_status,author_user_id,scenario_text) VALUES(?,?,?,?,?,?,?,?,"draft","DRAFT",?,?)');
            $q->execute([$code,$title,trim((string)($input['archetype']??'Interactive Drama')),$genre,$genreId,trim((string)($input['setting_name']??'Unspecified setting')),max(1,(int)($input['max_players']??3)),max(0,(int)($input['max_npcs']??3)),(int)$user['user_id'],trim((string)($input['scenario_text']??''))]);
            $plotId=(int)$pdo->lastInsertId();self::applyInkyProposals($user,$plotId,$input['inky_proposals']??null);$pdo->commit();return$plotId;
        }catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }

    private static function applyInkyProposals(array $user,int $plotId,mixed$raw): void
    {
        if($raw===null||$raw==='')return;if(is_string($raw)){try{$raw=json_decode($raw,true,20,JSON_THROW_ON_ERROR);}catch(Throwable$e){throw new RuntimeException('The accepted Inky suggestions could not be read.');}}
        if(!is_array($raw)||count($raw)>20)throw new RuntimeException('The accepted Inky suggestions are invalid.');
        $value=static fn(string$key)=>$raw[$key]??null;$locations=[];$locationCodes=[];$roles=[];$roleCodes=[];$sections=[];$sectionCodes=[];$eventCode='';
        $title=trim((string)($value('title')??''));$archetype=trim((string)($value('archetype')??''));$setting=trim((string)($value('setting_name')??''));$scenario=trim((string)($value('scenario_text')??''));$players=$value('max_players');$npcs=$value('max_npcs');
        if($title!==''||$archetype!==''||$setting!==''||$scenario!==''||$players!==null||$npcs!==null){$q=db()->prepare('UPDATE plots SET title=COALESCE(NULLIF(?,""),title),archetype=COALESCE(NULLIF(?,""),archetype),setting_name=COALESCE(NULLIF(?,""),setting_name),scenario_text=COALESCE(NULLIF(?,""),scenario_text),max_players=COALESCE(?,max_players),max_npcs=COALESCE(?,max_npcs) WHERE plot_id=?');$q->execute([$title,$archetype,$setting,$scenario,$players===null?null:max(1,min(8,(int)$players)),$npcs===null?null:max(0,min(8,(int)$npcs)),$plotId]);}
        foreach([1,2]as$index){$item=$value('location_'.$index.'_name');$description=$value('location_'.$index.'_description');if(!is_string($item)||trim($item)==='')continue;$label=trim($item);$code=self::inkyUniqueCode($label,$locationCodes);$locations[$label]=$code;$locations[strtolower($label)]=$code;$locationCodes[]=$code;self::saveLocation($user,$plotId,['location_code'=>$code,'label_text'=>$label,'description'=>is_string($description)?trim($description):'','sort_order'=>$index*10,'active'=>1]);}
        foreach(range(1,16)as$index){$item=$value('story_value_'.$index);if(!is_array($item))continue;$code=self::inkyUniqueCode((string)($item['target_code']??$item['label']??'VALUE_'.$index),[]);$allowed=array_values(array_filter(array_map(static fn($entry)=>trim((string)$entry),(array)($item['allowed_values']??[])),static fn($entry)=>$entry!==''));$type=in_array(($item['value_type']??''),['NUMERIC','PRESET','TEXT'],true)?$item['value_type']:'TEXT';self::saveStateDefinition($user,$plotId,['effect_scope'=>in_array(($item['scope']??''),['FACTOR','STAT','LOCATION','KNOWLEDGE','RELATIONSHIP','WORLD_STATE','GOAL','FEAR'],true)?$item['scope']:'WORLD_STATE','target_code'=>$code,'label_text'=>trim((string)($item['label']??$code)),'value_type'=>$type,'allowed_values_text'=>implode("\n",array_slice($allowed,0,8)),'description'=>trim((string)($item['description']??'')),'sort_order'=>$index*10,'active'=>1]);}
        $section=$value('ending_section_1');if(is_array($section)){ $sectionCode=self::inkyUniqueCode((string)($section['section_code']??$section['label']??'AFTERMATH'),$sectionCodes);$sectionCodes[]=$sectionCode;$sections[strtolower(trim((string)($section['label']??$sectionCode)))]=$sectionCode;$sections[strtolower($sectionCode)]=$sectionCode;self::saveStorySection($user,$plotId,['section_code'=>$sectionCode,'label_text'=>trim((string)($section['label']??$sectionCode)),'description'=>trim((string)($section['description']??'')),'sort_order'=>10,'active'=>1]);}
        $character=$value('character_1');if(is_array($character))self::applyInkyCharacterProposal($user,$plotId,$character,1);
        $event=$value('event_1');if(is_array($event)){ $eventCode=self::inkyUniqueCode((string)($event['event_code']??$event['headline']??'OPENING_EVENT'),[]);self::saveWorldEvent($user,$plotId,['event_second'=>0,'phase'=>in_array(($event['phase']??''),['EXPOSITION','INCITING_INCIDENT','RISING_ACTION','CLIMAX','RESOLUTION'],true)?$event['phase']:'EXPOSITION','event_code'=>$eventCode,'headline'=>trim((string)($event['headline']??$eventCode)),'event_text'=>trim((string)($event['text']??'')),'sort_order'=>0,'active'=>1]);}
        $decision=$value('decision_1');if(is_array($decision)){ $role=self::inkyRoleCode($plotId,(string)($decision['character']??''),$roles);$event=$eventCode?:self::inkyEventCode($plotId,(string)($decision['event_code']??''));if($role===''||$event==='')throw new RuntimeException('Inky needs a character and event before it can add the accepted decision.');$template= self::saveDecision($user,$plotId,['event_code'=>$event,'role_code'=>$role,'actor_type'=>'PLAYER','prompt_text'=>trim((string)($decision['prompt']??'')),'decision_window_seconds'=>30,'active'=>1]);foreach((array)($decision['options']??[])as$index=>$option){self::saveOption($user,$plotId,['template_id'=>$template,'option_code'=>self::inkyUniqueCode((string)($option['label']??'OPTION_'.($index+1)),[]),'label'=>trim((string)($option['label']??'Choice '.($index+1))),'npc_weight'=>0,'rationale_text'=>null,'result_text'=>trim((string)($option['result']??'')),'sort_order'=>$index,'active'=>1]);}}
        $opportunity=$value('opportunity_1');if(is_array($opportunity)){ $role=self::inkyRoleCode($plotId,(string)($opportunity['character']??''),$roles);$opportunityCode=self::inkyUniqueCode((string)($opportunity['opportunity_code']??$opportunity['prompt']??'OPPORTUNITY_1'),[]);if($role==='')throw new RuntimeException('Inky needs a character before it can add the accepted opportunity.');$id=self::saveDynamicOpportunity($user,$plotId,['opportunity_code'=>$opportunityCode,'role_code'=>$role,'prompt_text'=>trim((string)($opportunity['prompt']??'')),'window_seconds'=>35,'once_only'=>1,'priority'=>100,'active'=>1]);$condition=is_array($opportunity['condition']??null)?$opportunity['condition']:[];$source=in_array(($condition['source_type']??''),['WORLD_STATE','FACTOR','LOCATION','GOAL','FEAR','KNOWLEDGE','PHASE','ACTION_COUNT','PLAYER_ACTION_COUNT','OPTION_CHOICE_COUNT'],true)?$condition['source_type']:'PHASE';$sourceKey=in_array($source,['PHASE','ACTION_COUNT','PLAYER_ACTION_COUNT','OPTION_CHOICE_COUNT'],true)?null:self::nullable($condition['source_key']??null);$conditionValid=$source==='PHASE'||$sourceKey===null; if(!$conditionValid){$q=db()->prepare('SELECT 1 FROM plot_state_definitions WHERE plot_id=? AND target_code=? AND active=1 LIMIT 1');$q->execute([$plotId,self::code($sourceKey)]);$conditionValid=(bool)$q->fetchColumn();}if(!$conditionValid){$source='PHASE';$sourceKey=null;}$compare=self::nullable($condition['compare_value']??'EXPOSITION')??'EXPOSITION';self::saveDynamicCondition($user,$plotId,['dynamic_opportunity_id'=>$id,'source_type'=>$source,'source_key'=>$sourceKey,'operator'=>in_array(($condition['operator']??''),['EQ','NEQ','IN','NOT_IN','EXISTS','NOT_EXISTS','LT','LTE','GT','GTE'],true)?$condition['operator']:'EQ','compare_value'=>$compare,'sort_order'=>0,'active'=>1]);foreach((array)($opportunity['options']??[])as$index=>$option)self::saveDynamicOption($user,$plotId,['dynamic_opportunity_id'=>$id,'option_code'=>self::inkyUniqueCode((string)($option['label']??'OPTION_'.($index+1)),[]),'label'=>trim((string)($option['label']??'Choice '.($index+1))),'result_text'=>trim((string)($option['result']??'')),'npc_weight'=>1,'sort_order'=>$index,'active'=>1]);}
        $ending=$value('ending_1');if(is_array($ending)){ $sectionName=strtolower(trim((string)($ending['section']??'')));$endingSection=$sections[$sectionName]??reset($sectionCodes);if(!$endingSection){$endingSection='AFTERMATH';self::saveStorySection($user,$plotId,['section_code'=>$endingSection,'label_text'=>'Aftermath','description'=>'The consequences of the story.','sort_order'=>80,'active'=>1]);}$fragment=trim((string)($ending['headline']??''));$text=trim((string)($ending['text']??''));self::saveResolution($user,$plotId,['section_code'=>$endingSection,'section_order'=>80,'priority'=>100,'fragment_text'=>trim($fragment.($text!==''?"\n\n".$text:'')),'active'=>1]);}
    }

    private static function applyInkyCharacterProposal(array $user,int $plotId,mixed$value,?int$slotNo=null): void
    {
        if(!is_array($value))throw new RuntimeException('The accepted character suggestion is invalid.');self::guard($user,$plotId);$roleCode=self::code((string)($value['role_code']??$value['name']??''));$name=trim((string)($value['name']??''));if($roleCode===''||$name==='')throw new RuntimeException('The accepted character needs a name and stable role code.');
        $existingQ=db()->prepare('SELECT plot_role_id,role_code,slot_no FROM plot_roles WHERE plot_id=? AND active=1 AND UPPER(role_code)=? ORDER BY plot_role_id LIMIT 1');$existingQ->execute([$plotId,$roleCode]);$existing=$existingQ->fetch();$matchedRoleCode=(bool)$existing;if(!$existing&&$slotNo!==null){$existingQ=db()->prepare('SELECT plot_role_id,role_code,slot_no FROM plot_roles WHERE plot_id=? AND active=1 AND slot_no=? ORDER BY plot_role_id LIMIT 1');$existingQ->execute([$plotId,$slotNo]);$existing=$existingQ->fetch();}
        $q=db()->prepare('SELECT role_code FROM plot_roles WHERE plot_id=? ORDER BY plot_role_id');$q->execute([$plotId]);$used=array_map(static fn($code)=>strtoupper((string)$code),$q->fetchAll(PDO::FETCH_COLUMN));$id=$existing?(int)$existing['plot_role_id']:0;$roleCode=$matchedRoleCode?(string)$existing['role_code']:($existing?$roleCode:self::inkyUniqueCode($roleCode,$used));$start=trim((string)($value['starting_location']??''));$locationQuery=db()->prepare('SELECT label_text FROM plot_locations WHERE plot_id=? AND active=1 AND (label_text=? OR location_code=?) LIMIT 1');$locationQuery->execute([$plotId,$start,self::code($start)]);$storedStart=(string)($locationQuery->fetchColumn()?:'');if($storedStart!=='')$start=$storedStart;if($start==='')$start=(string)(db()->query('SELECT label_text FROM plot_locations WHERE plot_id='.(int)$plotId.' AND active=1 ORDER BY sort_order,plot_location_id LIMIT 1')->fetchColumn()?:'');if($start==='')throw new RuntimeException('Inky needs an active location before it can add the accepted character.');
        if($existing)$slot=(int)$existing['slot_no'];else{$countQ=db()->prepare('SELECT COUNT(*) FROM plot_roles WHERE plot_id=?');$countQ->execute([$plotId]);$slot=(int)$countQ->fetchColumn()+1;}$input=['role_code'=>$roleCode,'role_type'=>in_array(($value['role_type']??''),['PLAYER','NPC'],true)?$value['role_type']:'NPC','mystery_character'=>(int)!empty($value['mystery_character']),'slot_no'=>$slot,'name'=>$name,'public_description'=>trim((string)($value['public_description']??'')),'private_knowledge'=>trim((string)($value['private_knowledge']??'')),'starting_location'=>$start,'goal_text'=>trim((string)($value['goal_text']??'')),'fear_text'=>trim((string)($value['fear_text']??'')),'pronoun_set'=>in_array(($value['pronoun_set']??''),['HE','SHE','IT'],true)?$value['pronoun_set']:'IT','simulation_active'=>0,'active'=>1];if($id)$input['plot_role_id']=$id;self::saveCharacter($user,$plotId,$input);
    }

    public static function applyInkyProposal(array $user,int $plotId,string$key,mixed$value): void
    {
        if(preg_match('/^location_([2-9]|1[0-6])$/',$key)===1){if(!is_array($value))throw new RuntimeException('The accepted location suggestion is invalid.');$label=trim((string)($value['label_text']??''));if($label==='')throw new RuntimeException('The accepted location needs a name.');$q=db()->prepare('SELECT location_code FROM plot_locations WHERE plot_id=?');$q->execute([$plotId]);$used=array_map('strtoupper',$q->fetchAll(PDO::FETCH_COLUMN));$q=db()->prepare('SELECT COALESCE(MAX(sort_order),0)+10 FROM plot_locations WHERE plot_id=?');$q->execute([$plotId]);self::saveLocation($user,$plotId,['location_code'=>self::inkyUniqueCode((string)($value['location_code']??$label),$used),'label_text'=>$label,'description'=>trim((string)($value['description']??'')),'sort_order'=>(int)$q->fetchColumn(),'active'=>1]);return;}
        if(preg_match('/^event_([2-9]|1[0-6])$/',$key)===1){if(!is_array($value))throw new RuntimeException('The accepted event suggestion is invalid.');$q=db()->prepare('SELECT event_code FROM plot_world_events WHERE plot_id=?');$q->execute([$plotId]);$used=array_map('strtoupper',$q->fetchAll(PDO::FETCH_COLUMN));$q=db()->prepare('SELECT COALESCE(MAX(event_second),0)+30 FROM plot_world_events WHERE plot_id=?');$q->execute([$plotId]);self::saveWorldEvent($user,$plotId,['event_second'=>(int)$q->fetchColumn(),'phase'=>$value['phase']??'EXPOSITION','event_code'=>self::inkyUniqueCode((string)($value['event_code']??$value['headline']??'EVENT'),$used),'headline'=>trim((string)($value['headline']??'')),'event_text'=>trim((string)($value['text']??'')),'sort_order'=>0,'active'=>1]);return;}
        if(preg_match('/^decision_([2-9]|1[0-6])$/',$key)===1){if(!is_array($value))throw new RuntimeException('The accepted decision suggestion is invalid.');$role=self::inkyRoleCode($plotId,(string)($value['character']??''),[]);$event=self::inkyEventCode($plotId,(string)($value['event_code']??''));if($role===''||$event==='')throw new RuntimeException('Inky needs an existing character and event before it can add this decision.');$template=self::saveDecision($user,$plotId,['event_code'=>$event,'role_code'=>$role,'actor_type'=>'PLAYER','prompt_text'=>trim((string)($value['prompt']??'')),'decision_window_seconds'=>30,'active'=>1]);foreach((array)($value['options']??[])as$index=>$option)self::saveOption($user,$plotId,['template_id'=>$template,'option_code'=>self::inkyUniqueCode((string)($option['label']??'OPTION_'.($index+1)),[]),'label'=>trim((string)($option['label']??'Choice '.($index+1))),'npc_weight'=>0,'rationale_text'=>null,'result_text'=>trim((string)($option['result']??'')),'sort_order'=>$index,'active'=>1]);return;}
        if(preg_match('/^story_value_(?:[1-9]|1[0-6])$/',$key)===1){self::applyInkyProposals($user,$plotId,[$key=>$value]);return;}
        if(preg_match('/^character_(?:[1-9]|1[0-6])$/',$key)===1){self::applyInkyCharacterProposal($user,$plotId,$value,(int)substr($key,10));return;}
        $allowed=['title','archetype','setting_name','scenario_text','location_1_name','location_1_description','location_2_name','location_2_description','story_value_1','story_value_2','character_1','event_1','decision_1','opportunity_1','ending_section_1','ending_1','max_players','max_npcs'];if($plotId<1||!in_array($key,$allowed,true))throw new RuntimeException('That Inky proposal cannot be saved.');$pdo=db();$ownsTransaction=!$pdo->inTransaction();if($ownsTransaction)$pdo->beginTransaction();try{self::guard($user,$plotId);
            if(preg_match('/^location_([12])_description$/',$key,$match)){$slot=(int)$match[1]*10;$q=$pdo->prepare('SELECT plot_location_id,location_code,label_text FROM plot_locations WHERE plot_id=? AND sort_order=? ORDER BY plot_location_id LIMIT 1');$q->execute([$plotId,$slot]);$location=$q->fetch();if(!$location)throw new RuntimeException('Save the location name before accepting its description.');self::saveLocation($user,$plotId,['plot_location_id'=>(int)$location['plot_location_id'],'location_code'=>$location['location_code'],'label_text'=>$location['label_text'],'description'=>trim((string)$value),'sort_order'=>$slot,'active'=>1]);}
            elseif(preg_match('/^location_([12])_name$/',$key,$match)){$slot=(int)$match[1]*10;$q=$pdo->prepare('SELECT plot_location_id,location_code,description FROM plot_locations WHERE plot_id=? AND sort_order=? ORDER BY plot_location_id LIMIT 1');$q->execute([$plotId,$slot]);$location=$q->fetch();if($location)self::saveLocation($user,$plotId,['plot_location_id'=>(int)$location['plot_location_id'],'location_code'=>self::code((string)$value),'label_text'=>trim((string)$value),'description'=>$location['description'],'sort_order'=>$slot,'active'=>1]);else self::applyInkyProposals($user,$plotId,[$key=>$value]);}
            elseif($key==='event_1'&&is_array($value)){$q=$pdo->prepare('SELECT * FROM plot_world_events WHERE plot_id=? AND active=1 ORDER BY sort_order,event_second,plot_world_event_id LIMIT 1');$q->execute([$plotId]);$event=$q->fetch();if($event)self::saveWorldEvent($user,$plotId,['plot_world_event_id'=>(int)$event['plot_world_event_id'],'event_second'=>(int)$event['event_second'],'phase'=>$value['phase']??$event['phase'],'event_code'=>$value['event_code']??$event['event_code'],'headline'=>$value['headline']??$event['headline'],'event_text'=>$value['text']??$event['event_text'],'sort_order'=>(int)$event['sort_order'],'active'=>1]);else self::applyInkyProposals($user,$plotId,[$key=>$value]);}
            elseif($key==='ending_1'&&is_array($value)){ $sectionName=strtolower(trim((string)($value['section']??'')));$q=$pdo->prepare('SELECT section_code FROM plot_story_sections WHERE plot_id=? AND active=1 AND (LOWER(section_code)=? OR LOWER(label_text)=?) ORDER BY sort_order,plot_story_section_id LIMIT 1');$q->execute([$plotId,$sectionName,$sectionName]);$endingSection=(string)($q->fetchColumn()?:'');if($endingSection===''){$endingSection=(string)($pdo->query('SELECT section_code FROM plot_story_sections WHERE plot_id='.(int)$plotId.' AND active=1 ORDER BY sort_order,plot_story_section_id LIMIT 1')->fetchColumn()?:'');}if($endingSection===''){$endingSection='AFTERMATH';self::saveStorySection($user,$plotId,['section_code'=>$endingSection,'label_text'=>'Aftermath','description'=>'The consequences of the story.','sort_order'=>80,'active'=>1]);}$q=$pdo->prepare('SELECT resolution_fragment_id FROM plot_resolution_fragments WHERE plot_id=? AND section_code=? AND active=1 ORDER BY priority,resolution_fragment_id LIMIT 1');$q->execute([$plotId,$endingSection]);$fragmentId=(int)($q->fetchColumn()?:0);self::saveResolution($user,$plotId,['resolution_fragment_id'=>$fragmentId,'section_code'=>$endingSection,'section_order'=>80,'priority'=>100,'fragment_text'=>trim((string)($value['headline']??'').((string)($value['text']??'')!==''?"\n\n".$value['text']:'')),'active'=>1]);}
            else self::applyInkyProposals($user,$plotId,[$key=>$value]);if($ownsTransaction)$pdo->commit();}catch(Throwable$e){if($ownsTransaction&&$pdo->inTransaction())$pdo->rollBack();throw$e;}
    }

    private static function inkyRoleCode(int$plotId,string$name,array$roles): string
    {
        $key=strtolower(trim($name));if(isset($roles[$key]))return$roles[$key];$q=db()->prepare('SELECT role_code FROM plot_roles WHERE plot_id=? AND (role_code=? OR name=?) AND active=1 LIMIT 1');$q->execute([$plotId,self::code($name),$name]);return(string)$q->fetchColumn();
    }

    private static function inkyEventCode(int$plotId,string$code): string
    {
        $normalized=self::code($code);$q=db()->prepare('SELECT event_code FROM plot_world_events WHERE plot_id=? AND active=1 AND event_code=? LIMIT 1');$q->execute([$plotId,$normalized]);$stored=(string)($q->fetchColumn()?:'');if($stored!=='')return$stored;$q=db()->prepare('SELECT event_code FROM plot_world_events WHERE plot_id=? AND active=1 ORDER BY sort_order,plot_world_event_id LIMIT 1');$q->execute([$plotId]);return(string)($q->fetchColumn()?:'');
    }

    private static function inkyUniqueCode(string$value,array$used): string
    {
        $base=self::code($value)?:'ITEM';$candidate=$base;$index=2;while(in_array($candidate,$used,true))$candidate=$base.'_'.$index++;return$candidate;
    }

    public static function updateOverview(array $user,int $plotId,array $input): void
    {
        self::guard($user,$plotId);$genreId=(int)($input['genre_id']??0);$q=db()->prepare('SELECT label_text FROM story_genres WHERE genre_id=? AND active=1');$q->execute([$genreId]);$genre=(string)$q->fetchColumn();if($genre==='')throw new RuntimeException('Choose a genre.');
        $currentQ=db()->prepare('SELECT code FROM plots WHERE plot_id=?');$currentQ->execute([$plotId]);$currentCode=(string)$currentQ->fetchColumn();if($currentCode==='')throw new RuntimeException('Story not found.');$postedCode=trim((string)($input['story_code']??$currentCode));$nextCode=self::code($postedCode===''?$currentCode:$postedCode);if($nextCode!==$currentCode){self::assertStoryCodeChange($plotId,$currentCode,$nextCode);$duplicate=db()->prepare('SELECT 1 FROM plots WHERE code=? AND plot_id<>? LIMIT 1');$duplicate->execute([$nextCode,$plotId]);if($duplicate->fetchColumn())throw new RuntimeException('That Stable Story Code is already assigned to another story.');}
        $title=trim((string)($input['title']??''));if($title==='')throw new RuntimeException('The story needs a title.');
        $cur=db()->prepare('SELECT timeline_duration_seconds FROM plots WHERE plot_id=?');$cur->execute([$plotId]);$currentDuration=max(1,(int)$cur->fetchColumn());
        $duration=self::overviewDuration($user,$input,$currentDuration);
        $standingsCode=self::code((string)($input['standings_stat_code']??''));if($standingsCode!==''){$sq=db()->prepare('SELECT 1 FROM plot_state_definitions WHERE plot_id=? AND effect_scope="STAT" AND target_code=? AND active=1');$sq->execute([$plotId,$standingsCode]);if(!$sq->fetchColumn())throw new RuntimeException('Choose a valid standings stat.');}
        $standingsTitle=trim((string)($input['standings_title']??''));
        db()->prepare('UPDATE plots SET code=?,title=?,archetype=?,genre=?,genre_id=?,setting_name=?,max_players=?,max_npcs=?,scenario_text=?,timeline_duration_seconds=?,standings_stat_code=?,standings_title=? WHERE plot_id=?')->execute([$nextCode,$title,trim((string)$input['archetype']),$genre,$genreId,trim((string)$input['setting_name']),max(1,(int)$input['max_players']),max(0,(int)$input['max_npcs']),trim((string)$input['scenario_text']),$duration,$standingsCode!==''?$standingsCode:null,$standingsTitle!==''?mb_substr($standingsTitle,0,120):null,$plotId]);
        if($duration!==$currentDuration)db()->prepare('UPDATE plot_world_events SET event_second=ROUND(trigger_percent*?/100) WHERE plot_id=? AND trigger_percent IS NOT NULL')->execute([$duration,$plotId]);
        self::saveAllowedHostDurations($plotId,$input,$duration);
    }

    public static function saveLocation(array $user,int $plotId,array $input): int
    {
        self::guard($user,$plotId);$id=(int)($input['plot_location_id']??0);$label=trim((string)($input['label_text']??''));$code=self::code((string)($input['location_code']??$label));if($label===''||$code==='')throw new RuntimeException('A location needs a name and stable code.');$values=[$code,$label,self::nullable($input['description']??null),(int)($input['sort_order']??100),(int)!empty($input['active'])];
        if($id){$existing=db()->prepare('SELECT location_code FROM plot_locations WHERE plot_location_id=? AND plot_id=?');$existing->execute([$id,$plotId]);$current=$existing->fetchColumn();if($current===false)throw new RuntimeException('Location not found.');self::assertStableCodeChange($plotId,'location',$id,(string)$current,$code);$q=db()->prepare('UPDATE plot_locations SET location_code=?,label_text=?,description=?,sort_order=?,active=? WHERE plot_location_id=? AND plot_id=?');$q->execute(array_merge($values,[$id,$plotId]));return$id;}
        $q=db()->prepare('INSERT INTO plot_locations(plot_id,location_code,label_text,description,sort_order,active) VALUES(?,?,?,?,?,?)');$q->execute(array_merge([$plotId],$values));return(int)db()->lastInsertId();
    }

    public static function saveStateDefinition(array $user,int $plotId,array $input): int
    {
        self::guard($user,$plotId);$id=(int)($input['plot_state_definition_id']??0);$scopes=['FACTOR','STAT','LOCATION','KNOWLEDGE','RELATIONSHIP','WORLD_STATE','GOAL','FEAR'];$types=['NUMERIC','PRESET','TEXT'];$scope=in_array(($input['effect_scope']??''),$scopes,true)?$input['effect_scope']:'WORLD_STATE';$type=in_array(($input['value_type']??''),$types,true)?$input['value_type']:'TEXT';$code=self::code((string)($input['target_code']??''));$label=trim((string)($input['label_text']??''));if($code===''||$label==='')throw new RuntimeException('A story value needs a label and stable code.');$allowed=self::normalizeAllowedValues((string)($input['allowed_values_text']??''));if($type==='PRESET'&&$allowed==='')throw new RuntimeException('Preset story values need at least one allowed value.');$defaultText=$type==='NUMERIC'?null:self::nullable($input['default_text_value']??null);if($type==='PRESET'&&$defaultText!==null&&!in_array($defaultText,explode("\n",$allowed),true))throw new RuntimeException('The initial value must be one of the allowed preset values.');$values=[$scope,$code,$label,$type,$type==='NUMERIC'?self::integerOrNull($input['default_numeric_value']??null):null,$type==='PRESET'?$allowed:null,self::nullable($input['description']??null),(int)($input['sort_order']??100),(int)!empty($input['active']),$defaultText];
        if($id){$existing=db()->prepare('SELECT effect_scope,target_code,value_type,allowed_values_text FROM plot_state_definitions WHERE plot_state_definition_id=? AND plot_id=?');$existing->execute([$id,$plotId]);$current=$existing->fetch();if(!$current)throw new RuntimeException('Story value not found.');self::assertStableCodeChange($plotId,'state_definition',$id,$current['effect_scope'].'|'.$current['target_code'],$scope.'|'.$code);if($current['value_type']!==$type||(string)$current['allowed_values_text']!==(string)$values[5])self::assertStateDefinitionCompatibility($plotId,$scope,$code,$type,$values[5],$label);$q=db()->prepare('UPDATE plot_state_definitions SET effect_scope=?,target_code=?,label_text=?,value_type=?,default_numeric_value=?,allowed_values_text=?,description=?,sort_order=?,active=?,default_text_value=? WHERE plot_state_definition_id=? AND plot_id=?');$q->execute(array_merge($values,[$id,$plotId]));self::syncFactorRow($plotId,$values);return$id;}
        $q=db()->prepare('INSERT INTO plot_state_definitions(plot_id,effect_scope,target_code,label_text,value_type,default_numeric_value,allowed_values_text,description,sort_order,active,default_text_value) VALUES(?,?,?,?,?,?,?,?,?,?,?)');$q->execute(array_merge([$plotId],$values));$definitionId=(int)db()->lastInsertId();self::syncFactorRow($plotId,$values);return$definitionId;
    }

    private static function syncFactorRow(int $plotId,array $values): void
    {
        if($values[0]!=='FACTOR'||(int)$values[8]!==1)return;$initial=$values[4]===null?50:max(0,min(100,(int)$values[4]));db()->prepare('INSERT INTO plot_factors(plot_id,factor_code,label,initial_value,min_value,max_value,description) VALUES(?,?,?,?,0,100,?) ON DUPLICATE KEY UPDATE label=VALUES(label)')->execute([$plotId,$values[1],$values[2],$initial,$values[6]]);
    }

    public static function saveStorySection(array $user,int $plotId,array $input): int
    {
        self::guard($user,$plotId);$id=(int)($input['plot_story_section_id']??0);$label=trim((string)($input['label_text']??''));$code=self::code((string)($input['section_code']??$label));if($label===''||$code==='')throw new RuntimeException('An ending section needs a label and stable code.');$values=[$code,$label,self::nullable($input['description']??null),(int)($input['sort_order']??100),(int)!empty($input['active'])];
        if($id){$existing=db()->prepare('SELECT section_code FROM plot_story_sections WHERE plot_story_section_id=? AND plot_id=?');$existing->execute([$id,$plotId]);$current=$existing->fetchColumn();if($current===false)throw new RuntimeException('Ending section not found.');self::assertStableCodeChange($plotId,'story_section',$id,(string)$current,$code);$q=db()->prepare('UPDATE plot_story_sections SET section_code=?,label_text=?,description=?,sort_order=?,active=? WHERE plot_story_section_id=? AND plot_id=?');$q->execute(array_merge($values,[$id,$plotId]));return$id;}
        $q=db()->prepare('INSERT INTO plot_story_sections(plot_id,section_code,label_text,description,sort_order,active) VALUES(?,?,?,?,?,?)');$q->execute(array_merge([$plotId],$values));return(int)db()->lastInsertId();
    }

    public static function saveCharacter(array $user,int $plotId,array $input): int
    {
        self::guard($user,$plotId);$id=(int)($input['plot_role_id']??0);$roleCode=self::code((string)($input['role_code']??''));$name=trim((string)($input['name']??''));
        if($roleCode===''||$name==='')throw new RuntimeException('Every character needs a name and role code.');$roleType=in_array(($input['role_type']??''),['PLAYER','NPC'],true)?$input['role_type']:'PLAYER';if($id<1){$plotQ=db()->prepare('SELECT max_players,max_npcs FROM plots WHERE plot_id=?');$plotQ->execute([$plotId]);$plot=$plotQ->fetch();if(!$plot)throw new RuntimeException('Story not found.');$countQ=db()->prepare('SELECT COUNT(*) FROM plot_roles WHERE plot_id=? AND role_type=?');$countQ->execute([$plotId,$roleType]);$count=(int)$countQ->fetchColumn();$limit=$roleType==='PLAYER'?(int)$plot['max_players']:(int)$plot['max_npcs'];if($count>=$limit)throw new RuntimeException('This story has reached its Story Framework limit of '.$limit.' '.($roleType==='PLAYER'?'human player':'AI character').($limit===1?'':'s').'.');}
        $startingLocation=trim((string)($input['starting_location']??''));$locationCheck=db()->prepare('SELECT 1 FROM plot_locations WHERE plot_id=? AND label_text=?');$locationCheck->execute([$plotId,$startingLocation]);if(!$locationCheck->fetchColumn())throw new RuntimeException('Choose a starting location managed in Story Framework.');$values=[$roleCode,in_array(($input['role_type']??''),['PLAYER','NPC'],true)?$input['role_type']:'PLAYER',(int)!empty($input['mystery_character']),max(1,(int)($input['slot_no']??1)),$name,trim((string)($input['public_description']??'')),self::nullable($input['private_knowledge']??null),$startingLocation,trim((string)($input['goal_text']??'')),self::nullable($input['fear_text']??null),in_array(($input['pronoun_set']??''),['HE','SHE','IT'],true)?$input['pronoun_set']:'IT',(int)!empty($input['simulation_active']),(int)!empty($input['active'])];
        if($id){$existing=db()->prepare('SELECT role_code FROM plot_roles WHERE plot_role_id=? AND plot_id=?');$existing->execute([$id,$plotId]);$current=$existing->fetchColumn();if($current===false)throw new RuntimeException('Character not found.');self::assertStableCodeChange($plotId,'character',$id,(string)$current,$roleCode);$q=db()->prepare('UPDATE plot_roles SET role_code=?,role_type=?,mystery_character=?,slot_no=?,name=?,public_description=?,private_knowledge=?,starting_location=?,goal_text=?,fear_text=?,pronoun_set=?,simulation_active=?,active=? WHERE plot_role_id=? AND plot_id=?');$q->execute(array_merge($values,[$id,$plotId]));if(isset($input['stats'])&&is_array($input['stats']))self::saveRoleStats($user,$plotId,$id,$input['stats']);return$id;}
        $q=db()->prepare('INSERT INTO plot_roles(plot_id,role_code,role_type,mystery_character,slot_no,name,public_description,private_knowledge,starting_location,goal_text,fear_text,pronoun_set,simulation_active,active) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)');$q->execute(array_merge([$plotId],$values));$newId=(int)db()->lastInsertId();if(isset($input['stats'])&&is_array($input['stats']))self::saveRoleStats($user,$plotId,$newId,$input['stats']);return$newId;
    }

    public static function saveRoleStats(array $user,int $plotId,int $roleId,array $stats): void
    {
        self::guard($user,$plotId);self::assertParent('plot_roles','plot_role_id',$roleId,$plotId);$defs=[];$q=db()->prepare('SELECT target_code,label_text FROM plot_state_definitions WHERE plot_id=? AND effect_scope="STAT" AND active=1');$q->execute([$plotId]);foreach($q->fetchAll()as$d)$defs[(string)$d['target_code']]=(string)$d['label_text'];$pdo=db();
        foreach($stats as$statCode=>$raw){$statCode=self::code((string)$statCode);if(!isset($defs[$statCode]))continue;$raw=trim((string)$raw);if($raw===''){$pdo->prepare('DELETE FROM plot_role_stats WHERE plot_role_id=? AND stat_code=?')->execute([$roleId,$statCode]);continue;}if(!is_numeric($raw))throw new RuntimeException($defs[$statCode].' starting value must be a number.');$statValue=max(0,min(100,(int)round((float)$raw)));$pdo->prepare('INSERT INTO plot_role_stats(plot_role_id,stat_code,label,initial_value) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE label=VALUES(label),initial_value=VALUES(initial_value)')->execute([$roleId,$statCode,$defs[$statCode],$statValue]);}
    }

    public static function saveWorldEvent(array $user,int $plotId,array $input): int
    {
        self::guard($user,$plotId);$id=(int)($input['plot_world_event_id']??0);$code=self::code((string)($input['event_code']??''));if($code==='')throw new RuntimeException('Every event needs an event code.');
        $dq=db()->prepare('SELECT timeline_duration_seconds FROM plots WHERE plot_id=?');$dq->execute([$plotId]);$timelineDuration=max(1,(int)$dq->fetchColumn());
        if(isset($input['trigger_percent'])&&$input['trigger_percent']!==''&&$input['trigger_percent']!==null){$triggerPercent=max(0.0,min(100.0,(float)$input['trigger_percent']));$eventSecond=(int)round($triggerPercent*$timelineDuration/100);}
        else{$eventSecond=self::durationSeconds($input,'event_time_value','event_time_unit','event_second',0);$triggerPercent=round($eventSecond*100/$timelineDuration,3);}
        $values=[$eventSecond,$triggerPercent,in_array(($input['phase']??''),['EXPOSITION','INCITING_INCIDENT','RISING_ACTION','CLIMAX','RESOLUTION'],true)?$input['phase']:'EXPOSITION',$code,trim((string)($input['headline']??'')),trim((string)($input['event_text']??'')),(int)($input['sort_order']??0),(int)!empty($input['active'])];
        if($id){$existing=db()->prepare('SELECT event_code FROM plot_world_events WHERE plot_world_event_id=? AND plot_id=?');$existing->execute([$id,$plotId]);$current=$existing->fetchColumn();if($current===false)throw new RuntimeException('Event not found.');self::assertStableCodeChange($plotId,'event',$id,(string)$current,$code);$q=db()->prepare('UPDATE plot_world_events SET event_second=?,trigger_percent=?,phase=?,event_code=?,headline=?,event_text=?,sort_order=?,active=? WHERE plot_world_event_id=? AND plot_id=?');$q->execute(array_merge($values,[$id,$plotId]));return$id;}
        $q=db()->prepare('INSERT INTO plot_world_events(plot_id,event_second,trigger_percent,phase,event_code,headline,event_text,sort_order,active) VALUES(?,?,?,?,?,?,?,?,?,?)');$q->execute(array_merge([$plotId],$values));return(int)db()->lastInsertId();
    }

    public static function saveDecision(array $user,int $plotId,array $input): int
    {
        self::guard($user,$plotId);$id=(int)($input['template_id']??0);$values=[self::code((string)$input['event_code']),self::code((string)$input['role_code']),in_array(($input['actor_type']??''),['PLAYER','NPC'],true)?$input['actor_type']:'PLAYER',trim((string)$input['prompt_text']),max(5,self::durationSeconds($input,'decision_window_value','decision_window_unit','decision_window_seconds',30)),(int)!empty($input['active'])];
        if(!$values[0]||!$values[1]||!$values[3])throw new RuntimeException('A decision needs an event, character, and prompt.');
        if($id){$q=db()->prepare('UPDATE plot_decision_templates SET event_code=?,role_code=?,actor_type=?,prompt_text=?,decision_window_seconds=?,active=? WHERE template_id=? AND plot_id=?');$q->execute(array_merge($values,[$id,$plotId]));return$id;}
        $q=db()->prepare('INSERT INTO plot_decision_templates(plot_id,event_code,role_code,actor_type,prompt_text,decision_window_seconds,active) VALUES(?,?,?,?,?,?,?)');$q->execute(array_merge([$plotId],$values));return(int)db()->lastInsertId();
    }

    public static function saveOption(array $user,int $plotId,array $input): int
    {
        self::guard($user,$plotId);$templateId=(int)$input['template_id'];self::assertParent('plot_decision_templates','template_id',$templateId,$plotId);
        $id=(int)($input['template_option_id']??0);$values=[self::code((string)$input['option_code']),trim((string)$input['label']),(int)($input['npc_weight']??0),self::nullable($input['rationale_text']??null),trim((string)$input['result_text']),(int)($input['sort_order']??0),(int)!empty($input['active'])];
        if(!$values[0]||!$values[1]||!$values[4])throw new RuntimeException('An option needs a code, label, and result.');
        if($id){$existing=db()->prepare('SELECT o.option_code FROM plot_decision_template_options o JOIN plot_decision_templates t ON t.template_id=o.template_id WHERE o.template_option_id=? AND t.plot_id=?');$existing->execute([$id,$plotId]);$current=$existing->fetchColumn();if($current===false)throw new RuntimeException('Option not found.');self::assertStableCodeChange($plotId,'option',$id,(string)$current,$values[0]);$q=db()->prepare('UPDATE plot_decision_template_options o JOIN plot_decision_templates t ON t.template_id=o.template_id SET o.option_code=?,o.label=?,o.npc_weight=?,o.rationale_text=?,o.result_text=?,o.sort_order=?,o.active=? WHERE o.template_option_id=? AND t.plot_id=?');$q->execute(array_merge($values,[$id,$plotId]));return$id;}
        $q=db()->prepare('INSERT INTO plot_decision_template_options(template_id,option_code,label,npc_weight,rationale_text,result_text,sort_order,active) VALUES(?,?,?,?,?,?,?,?)');$q->execute(array_merge([$templateId],$values));return(int)db()->lastInsertId();
    }

    public static function saveOptionEffect(array $user,int $plotId,array $input): int
    {
        self::guard($user,$plotId);$optionId=(int)$input['template_option_id'];$q=db()->prepare('SELECT 1 FROM plot_decision_template_options o JOIN plot_decision_templates t ON t.template_id=o.template_id WHERE o.template_option_id=? AND t.plot_id=?');$q->execute([$optionId,$plotId]);if(!$q->fetchColumn())throw new RuntimeException('The option does not belong to this story.');
        $id=(int)($input['effect_id']??0);$scope=in_array(($input['effect_scope']??''),['FACTOR','STAT','LOCATION','KNOWLEDGE','RELATIONSHIP','WORLD_STATE','GOAL','FEAR'],true)?$input['effect_scope']:'WORLD_STATE';$values=[$scope,self::code((string)$input['target_code']),self::integerOrNull($input['delta_value']??null),self::nullable($input['set_value']??null),self::nullable($input['detail_text']??null),(int)($input['sort_order']??0),(int)!empty($input['active'])];if($values[1]==='')throw new RuntimeException('An effect needs a target code.');self::validateManagedValue($plotId,$scope,$values[1],$values[2],$values[3]);
        if($id){$q=db()->prepare('UPDATE plot_option_effects e JOIN plot_decision_template_options o ON o.template_option_id=e.template_option_id JOIN plot_decision_templates t ON t.template_id=o.template_id SET e.effect_scope=?,e.target_code=?,e.delta_value=?,e.set_value=?,e.detail_text=?,e.sort_order=?,e.active=? WHERE e.effect_id=? AND t.plot_id=?');$q->execute(array_merge($values,[$id,$plotId]));return$id;}
        $q=db()->prepare('INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order,active) VALUES(?,?,?,?,?,?,?,?)');$q->execute(array_merge([$optionId],$values));return(int)db()->lastInsertId();
    }


    public static function saveOptionCondition(array $user,int $plotId,array $input): int
    {
        self::guard($user,$plotId);$optionId=(int)($input['template_option_id']??0);$q=db()->prepare('SELECT 1 FROM plot_decision_template_options o JOIN plot_decision_templates t ON t.template_id=o.template_id WHERE o.template_option_id=? AND t.plot_id=?');$q->execute([$optionId,$plotId]);if(!$q->fetchColumn())throw new RuntimeException('The option does not belong to this story.');
        $id=(int)($input['option_condition_id']??0);$scope=in_array(($input['condition_scope']??''),['WORLD_STATE','FACTOR'],true)?$input['condition_scope']:'WORLD_STATE';$code=self::code((string)($input['source_key']??''));$operator=in_array(($input['operator']??''),['EQ','NEQ','IN','NOT_IN','EXISTS','NOT_EXISTS','LT','LTE','GT','GTE'],true)?$input['operator']:'EQ';$compare=self::nullable($input['compare_value']??null);if($code==='')throw new RuntimeException('A condition needs a story value.');self::validateManagedCondition($plotId,$scope,$code,$operator,$compare);$values=[$scope==='FACTOR'?'FACTOR:'.$code:$code,$operator,$compare,self::nullable($input['reason_text']??null),(int)($input['sort_order']??0)];
        if($id){$q=db()->prepare('UPDATE plot_option_conditions c JOIN plot_decision_template_options o ON o.template_option_id=c.template_option_id JOIN plot_decision_templates t ON t.template_id=o.template_id SET c.state_key=?,c.operator=?,c.compare_value=?,c.reason_text=?,c.sort_order=? WHERE c.option_condition_id=? AND t.plot_id=?');$q->execute(array_merge($values,[$id,$plotId]));return$id;}
        $q=db()->prepare('INSERT INTO plot_option_conditions(template_option_id,state_key,operator,compare_value,reason_text,sort_order) VALUES(?,?,?,?,?,?)');$q->execute(array_merge([$optionId],$values));return(int)db()->lastInsertId();
    }

    public static function saveWorldEventEffect(array $user,int $plotId,array $input): int
    {
        self::guard($user,$plotId);$eventCode=self::code((string)($input['event_code']??''));$eventQ=db()->prepare('SELECT 1 FROM plot_world_events WHERE plot_id=? AND event_code=?');$eventQ->execute([$plotId,$eventCode]);if(!$eventQ->fetchColumn())throw new RuntimeException('World event effects attach to an existing event.');
        $id=(int)($input['world_event_effect_id']??0);$scope=in_array(($input['effect_scope']??''),['WORLD_STATE','FACTOR'],true)?$input['effect_scope']:'WORLD_STATE';$values=[$scope,self::code((string)$input['target_code']),self::integerOrNull($input['delta_value']??null),self::nullable($input['set_value']??null),self::nullable($input['detail_text']??null),(int)($input['sort_order']??0),(int)!empty($input['active'])];if($values[1]==='')throw new RuntimeException('An effect needs a target code.');self::validateManagedValue($plotId,$scope,$values[1],$values[2],$values[3]);
        if($id){$q=db()->prepare('UPDATE plot_world_event_effects SET effect_scope=?,target_code=?,delta_value=?,set_value=?,detail_text=?,sort_order=?,active=? WHERE world_event_effect_id=? AND plot_id=?');$q->execute(array_merge($values,[$id,$plotId]));return$id;}
        $q=db()->prepare('INSERT INTO plot_world_event_effects(plot_id,event_code,effect_scope,target_code,delta_value,set_value,detail_text,sort_order,active) VALUES(?,?,?,?,?,?,?,?,?)');$q->execute(array_merge([$plotId,$eventCode],$values));return(int)db()->lastInsertId();
    }

    public static function saveWorldEventEffectCondition(array $user,int $plotId,array $input): int
    {
        self::guard($user,$plotId);$effectId=(int)($input['world_event_effect_id']??0);self::assertParent('plot_world_event_effects','world_event_effect_id',$effectId,$plotId);
        $id=(int)($input['world_event_effect_condition_id']??0);$scope=in_array(($input['condition_scope']??''),['WORLD_STATE','FACTOR'],true)?$input['condition_scope']:'WORLD_STATE';$code=self::code((string)($input['source_key']??''));$operator=in_array(($input['operator']??''),['EQ','NEQ','IN','NOT_IN','EXISTS','NOT_EXISTS','LT','LTE','GT','GTE'],true)?$input['operator']:'EQ';$compare=self::nullable($input['compare_value']??null);if($code==='')throw new RuntimeException('A condition needs a story value.');self::validateManagedCondition($plotId,$scope,$code,$operator,$compare);$values=[$scope==='FACTOR'?'FACTOR:'.$code:$code,$operator,$compare,(int)($input['sort_order']??0)];
        if($id){$q=db()->prepare('UPDATE plot_world_event_effect_conditions c JOIN plot_world_event_effects e ON e.world_event_effect_id=c.world_event_effect_id SET c.state_key=?,c.operator=?,c.compare_value=?,c.sort_order=? WHERE c.world_event_effect_condition_id=? AND e.plot_id=?');$q->execute(array_merge($values,[$id,$plotId]));return$id;}
        $q=db()->prepare('INSERT INTO plot_world_event_effect_conditions(world_event_effect_id,state_key,operator,compare_value,sort_order) VALUES(?,?,?,?,?)');$q->execute(array_merge([$effectId],$values));return(int)db()->lastInsertId();
    }

    public static function saveNoActionEffect(array $user,int $plotId,array $input): int
    {
        self::guard($user,$plotId);$eventCode=self::code((string)($input['event_code']??''));$roleCode=self::code((string)($input['role_code']??''));$decisionQ=db()->prepare('SELECT 1 FROM plot_decision_templates WHERE plot_id=? AND event_code=? AND role_code=?');$decisionQ->execute([$plotId,$eventCode,$roleCode]);if(!$decisionQ->fetchColumn())throw new RuntimeException('No-action effects attach to an existing decision.');
        $id=(int)($input['no_action_effect_id']??0);$scope=in_array(($input['effect_scope']??''),['FACTOR','STAT','LOCATION','KNOWLEDGE','WORLD_STATE','GOAL','FEAR'],true)?$input['effect_scope']:'WORLD_STATE';$values=[$scope,self::code((string)$input['target_code']),self::integerOrNull($input['delta_value']??null),self::nullable($input['set_value']??null),self::nullable($input['detail_text']??null),(int)($input['sort_order']??0)];if($values[1]==='')throw new RuntimeException('An effect needs a target code.');self::validateManagedValue($plotId,$scope,$values[1],$values[2],$values[3]);
        if($id){$q=db()->prepare('UPDATE plot_no_action_effects SET effect_scope=?,target_code=?,delta_value=?,set_value=?,detail_text=?,sort_order=? WHERE no_action_effect_id=? AND plot_id=?');$q->execute(array_merge($values,[$id,$plotId]));return$id;}
        $q=db()->prepare('INSERT INTO plot_no_action_effects(plot_id,event_code,role_code,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES(?,?,?,?,?,?,?,?,?)');$q->execute(array_merge([$plotId,$eventCode,$roleCode],$values));return(int)db()->lastInsertId();
    }

    public static function saveNoActionCondition(array $user,int $plotId,array $input): int
    {
        self::guard($user,$plotId);$effectId=(int)($input['no_action_effect_id']??0);self::assertParent('plot_no_action_effects','no_action_effect_id',$effectId,$plotId);
        $id=(int)($input['no_action_condition_id']??0);$scope=in_array(($input['condition_scope']??''),['WORLD_STATE','FACTOR'],true)?$input['condition_scope']:'WORLD_STATE';$code=self::code((string)($input['source_key']??''));$operator=in_array(($input['operator']??''),['EQ','NEQ','IN','NOT_IN','EXISTS','NOT_EXISTS'],true)?$input['operator']:'EQ';$compare=self::nullable($input['compare_value']??null);if($code==='')throw new RuntimeException('A condition needs a story value.');self::validateManagedCondition($plotId,$scope,$code,$operator,$compare);$values=[$scope==='FACTOR'?'FACTOR:'.$code:$code,$operator,$compare,self::nullable($input['reason_text']??null),(int)($input['sort_order']??0)];
        if($id){$q=db()->prepare('UPDATE plot_no_action_conditions c JOIN plot_no_action_effects e ON e.no_action_effect_id=c.no_action_effect_id SET c.state_key=?,c.operator=?,c.compare_value=?,c.reason_text=?,c.sort_order=? WHERE c.no_action_condition_id=? AND e.plot_id=?');$q->execute(array_merge($values,[$id,$plotId]));return$id;}
        $q=db()->prepare('INSERT INTO plot_no_action_conditions(no_action_effect_id,state_key,operator,compare_value,reason_text,sort_order) VALUES(?,?,?,?,?,?)');$q->execute(array_merge([$effectId],$values));return(int)db()->lastInsertId();
    }

    public static function saveDynamicOpportunity(array $user,int $plotId,array $input): int
    {
        self::guard($user,$plotId);$id=(int)($input['dynamic_opportunity_id']??0);$values=[self::code((string)$input['opportunity_code']),self::code((string)$input['role_code']),trim((string)$input['prompt_text']),max(5,self::durationSeconds($input,'window_value','window_unit','window_seconds',35)),(int)!empty($input['once_only']),(int)($input['priority']??100),(int)!empty($input['active'])];if(!$values[0]||!$values[1]||!$values[2])throw new RuntimeException('A conditional opportunity needs a code, character, and prompt.');
        if($id){$existing=db()->prepare('SELECT opportunity_code,role_code FROM plot_dynamic_opportunities WHERE dynamic_opportunity_id=? AND plot_id=?');$existing->execute([$id,$plotId]);$current=$existing->fetch();if(!$current)throw new RuntimeException('Conditional opportunity not found.');self::assertStableCodeChange($plotId,'opportunity',$id,(string)$current['opportunity_code'],$values[0]);$pdo=db();$ownsTransaction=!$pdo->inTransaction();if($ownsTransaction)$pdo->beginTransaction();try{$q=$pdo->prepare('UPDATE plot_dynamic_opportunities SET opportunity_code=?,role_code=?,prompt_text=?,window_seconds=?,once_only=?,priority=?,active=? WHERE dynamic_opportunity_id=? AND plot_id=?');$q->execute(array_merge($values,[$id,$plotId]));$pdo->prepare('UPDATE plot_decision_templates SET event_code=?,role_code=? WHERE plot_id=? AND event_code=? AND role_code=?')->execute([$values[0],$values[1],$plotId,$current['opportunity_code'],$current['role_code']]);DynamicOpportunityService::syncBehaviorTemplateById($id);if($ownsTransaction)$pdo->commit();return$id;}catch(Throwable$e){if($ownsTransaction&&$pdo->inTransaction())$pdo->rollBack();throw$e;}}
        $q=db()->prepare('INSERT INTO plot_dynamic_opportunities(plot_id,opportunity_code,role_code,prompt_text,window_seconds,once_only,priority,active) VALUES(?,?,?,?,?,?,?,?)');$q->execute(array_merge([$plotId],$values));return(int)db()->lastInsertId();
    }

    public static function saveDynamicCondition(array $user,int $plotId,array $input): int
    {
        self::guard($user,$plotId);$parent=(int)$input['dynamic_opportunity_id'];self::assertParent('plot_dynamic_opportunities','dynamic_opportunity_id',$parent,$plotId);$id=(int)($input['dynamic_condition_id']??0);$types=['WORLD_STATE','FACTOR','LOCATION','GOAL','FEAR','STAT','KNOWLEDGE','RELATIONSHIP','PHASE','ACTION_COUNT','PLAYER_ACTION_COUNT','OPTION_CHOICE_COUNT'];$operators=['EQ','NEQ','IN','NOT_IN','EXISTS','NOT_EXISTS','LT','LTE','GT','GTE'];$compare=is_array($input['compare_value']??null)?implode(',',array_values(array_filter(array_map(static fn($value)=>trim((string)$value),(array)$input['compare_value']),static fn($value)=>$value!==''))):($input['compare_value']??null);$values=[in_array(($input['source_type']??''),$types,true)?$input['source_type']:'WORLD_STATE',self::nullable($input['source_key']??null),in_array(($input['operator']??''),$operators,true)?$input['operator']:'EQ',self::nullable($compare),(int)($input['sort_order']??0),(int)!empty($input['active'])];if(!in_array($values[0],['PHASE','ACTION_COUNT','PLAYER_ACTION_COUNT','OPTION_CHOICE_COUNT'],true)&&$values[1]!==null)self::validateManagedCondition($plotId,$values[0],$values[1],$values[2],$values[3]);
        if($id){$q=db()->prepare('UPDATE plot_dynamic_opportunity_conditions c JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=c.dynamic_opportunity_id SET c.source_type=?,c.source_key=?,c.operator=?,c.compare_value=?,c.sort_order=?,c.active=? WHERE c.dynamic_condition_id=? AND o.plot_id=?');$q->execute(array_merge($values,[$id,$plotId]));return$id;}
        $q=db()->prepare('INSERT INTO plot_dynamic_opportunity_conditions(dynamic_opportunity_id,source_type,source_key,operator,compare_value,sort_order,active) VALUES(?,?,?,?,?,?,?)');$q->execute(array_merge([$parent],$values));return(int)db()->lastInsertId();
    }

    public static function saveDynamicOption(array $user,int $plotId,array $input): int
    {
        self::guard($user,$plotId);$parent=(int)$input['dynamic_opportunity_id'];self::assertParent('plot_dynamic_opportunities','dynamic_opportunity_id',$parent,$plotId);$id=(int)($input['dynamic_option_id']??0);$values=[self::code((string)$input['option_code']),trim((string)$input['label']),trim((string)$input['result_text']),max(0,(int)($input['npc_weight']??1)),self::nullable($input['rationale_text']??null),(int)($input['sort_order']??0),(int)!empty($input['active'])];if(!$values[0]||!$values[1]||!$values[2])throw new RuntimeException('A conditional option needs a code, label, and result.');
        if($id){$existing=db()->prepare('SELECT x.option_code,o.opportunity_code,o.role_code FROM plot_dynamic_opportunity_options x JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=x.dynamic_opportunity_id WHERE x.dynamic_option_id=? AND o.plot_id=?');$existing->execute([$id,$plotId]);$current=$existing->fetch();if(!$current)throw new RuntimeException('Conditional option not found.');self::assertStableCodeChange($plotId,'dynamic_option',$id,(string)$current['option_code'],$values[0]);$pdo=db();$ownsTransaction=!$pdo->inTransaction();if($ownsTransaction)$pdo->beginTransaction();try{$q=$pdo->prepare('UPDATE plot_dynamic_opportunity_options x JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=x.dynamic_opportunity_id SET x.option_code=?,x.label=?,x.result_text=?,x.npc_weight=?,x.rationale_text=?,x.sort_order=?,x.active=? WHERE x.dynamic_option_id=? AND o.plot_id=?');$q->execute(array_merge($values,[$id,$plotId]));if((string)$current['option_code']!==$values[0])$pdo->prepare('UPDATE plot_decision_template_options x JOIN plot_decision_templates t ON t.template_id=x.template_id SET x.option_code=? WHERE t.plot_id=? AND t.event_code=? AND t.role_code=? AND x.option_code=?')->execute([$values[0],$plotId,$current['opportunity_code'],$current['role_code'],$current['option_code']]);DynamicOpportunityService::syncBehaviorTemplateById($parent);if($ownsTransaction)$pdo->commit();return$id;}catch(Throwable$e){if($ownsTransaction&&$pdo->inTransaction())$pdo->rollBack();throw$e;}}
        $q=db()->prepare('INSERT INTO plot_dynamic_opportunity_options(dynamic_opportunity_id,option_code,label,result_text,npc_weight,rationale_text,sort_order,active) VALUES(?,?,?,?,?,?,?,?)');$q->execute(array_merge([$parent],$values));$id=(int)db()->lastInsertId();DynamicOpportunityService::syncBehaviorTemplateById($parent);return$id;
    }

    public static function saveDynamicEffect(array $user,int $plotId,array $input): int
    {
        self::guard($user,$plotId);$option=(int)$input['dynamic_option_id'];$q=db()->prepare('SELECT o.dynamic_opportunity_id FROM plot_dynamic_opportunity_options x JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=x.dynamic_opportunity_id WHERE x.dynamic_option_id=? AND o.plot_id=?');$q->execute([$option,$plotId]);$opportunityId=(int)$q->fetchColumn();if(!$opportunityId)throw new RuntimeException('The conditional option does not belong to this story.');$id=(int)($input['dynamic_effect_id']??0);$scopes=['FACTOR','STAT','LOCATION','KNOWLEDGE','RELATIONSHIP','WORLD_STATE','GOAL','FEAR'];$values=[in_array(($input['effect_scope']??''),$scopes,true)?$input['effect_scope']:'WORLD_STATE',self::code((string)$input['target_code']),self::integerOrNull($input['delta_value']??null),self::nullable($input['set_value']??null),self::nullable($input['detail_text']??null),(int)($input['sort_order']??0),(int)!empty($input['active'])];if(!$values[1])throw new RuntimeException('A conditional effect needs a target code.');self::validateManagedValue($plotId,$values[0],$values[1],$values[2],$values[3]);
        if($id){$q=db()->prepare('UPDATE plot_dynamic_option_effects e JOIN plot_dynamic_opportunity_options x ON x.dynamic_option_id=e.dynamic_option_id JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=x.dynamic_opportunity_id SET e.effect_scope=?,e.target_code=?,e.delta_value=?,e.set_value=?,e.detail_text=?,e.sort_order=?,e.active=? WHERE e.dynamic_effect_id=? AND o.plot_id=?');$q->execute(array_merge($values,[$id,$plotId]));DynamicOpportunityService::syncBehaviorTemplateById($opportunityId);return$id;}
        $q=db()->prepare('INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order,active) VALUES(?,?,?,?,?,?,?,?)');$q->execute(array_merge([$option],$values));$id=(int)db()->lastInsertId();DynamicOpportunityService::syncBehaviorTemplateById($opportunityId);return$id;
    }

    public static function saveResolution(array $user,int $plotId,array $input): int
    {
        self::guard($user,$plotId);$id=(int)($input['resolution_fragment_id']??0);$values=[self::code((string)($input['section_code']??'AFTERMATH')),(int)($input['section_order']??80),(int)($input['priority']??100),trim((string)$input['fragment_text']),(int)!empty($input['active'])];if($values[3]==='')throw new RuntimeException('Resolution prose cannot be empty.');$sectionCheck=db()->prepare('SELECT 1 FROM plot_story_sections WHERE plot_id=? AND section_code=?');$sectionCheck->execute([$plotId,$values[0]]);if(!$sectionCheck->fetchColumn())throw new RuntimeException('Choose an ending section managed in Story Framework.');
        if($id){$q=db()->prepare('UPDATE plot_resolution_fragments SET section_code=?,section_order=?,priority=?,fragment_text=?,active=? WHERE resolution_fragment_id=? AND plot_id=?');$q->execute(array_merge($values,[$id,$plotId]));return$id;}
        $q=db()->prepare('INSERT INTO plot_resolution_fragments(plot_id,section_code,section_order,priority,fragment_text,active) VALUES(?,?,?,?,?,?)');$q->execute(array_merge([$plotId],$values));return(int)db()->lastInsertId();
    }

    public static function saveResolutionCondition(array $user,int $plotId,array $input): int
    {
        self::guard($user,$plotId);$fragment=(int)$input['resolution_fragment_id'];self::assertParent('plot_resolution_fragments','resolution_fragment_id',$fragment,$plotId);$id=(int)($input['resolution_condition_id']??0);$operators=['EQ','NEQ','IN','NOT_IN','EXISTS','NOT_EXISTS','LT','LTE','GT','GTE'];$compare=is_array($input['compare_value']??null)?implode(',',array_values(array_filter(array_map(static fn($value)=>trim((string)$value),(array)$input['compare_value']),static fn($value)=>$value!==''))):($input['compare_value']??null);$values=[self::code((string)$input['state_key']),in_array(($input['operator']??''),$operators,true)?$input['operator']:'EQ',self::nullable($compare),(int)($input['sort_order']??0),(int)!empty($input['active'])];if(!$values[0])throw new RuntimeException('An ending condition needs a state key.');self::validateManagedCondition($plotId,null,$values[0],$values[1],$values[2]);
        if($id){$q=db()->prepare('UPDATE plot_resolution_conditions c JOIN plot_resolution_fragments f ON f.resolution_fragment_id=c.resolution_fragment_id SET c.state_key=?,c.operator=?,c.compare_value=?,c.sort_order=?,c.active=? WHERE c.resolution_condition_id=? AND f.plot_id=?');$q->execute(array_merge($values,[$id,$plotId]));return$id;}
        $q=db()->prepare('INSERT INTO plot_resolution_conditions(resolution_fragment_id,state_key,operator,compare_value,sort_order,active) VALUES(?,?,?,?,?,?)');$q->execute(array_merge([$fragment],$values));return(int)db()->lastInsertId();
    }

    public static function story(int $plotId): array
    {
        $q=db()->prepare('SELECT p.*,u.display_name author_name,u.email author_email,g.label_text genre_label FROM plots p LEFT JOIN users u ON u.user_id=p.author_user_id LEFT JOIN story_genres g ON g.genre_id=p.genre_id WHERE p.plot_id=?');$q->execute([$plotId]);$plot=$q->fetch();if(!$plot)throw new RuntimeException('Story not found.');return$plot;
    }

    public static function workspace(int $plotId): array
    {
        $tables=[
            'characters'=>['SELECT * FROM plot_roles WHERE plot_id=? ORDER BY name,role_code,plot_role_id',[$plotId]],
            'role_stats'=>['SELECT s.* FROM plot_role_stats s JOIN plot_roles r ON r.plot_role_id=s.plot_role_id WHERE r.plot_id=? ORDER BY s.plot_role_stat_id',[$plotId]],
            'locations'=>['SELECT * FROM plot_locations WHERE plot_id=? ORDER BY label_text,plot_location_id',[$plotId]],
            'state_definitions'=>['SELECT * FROM plot_state_definitions WHERE plot_id=? ORDER BY label_text,plot_state_definition_id',[$plotId]],
            'story_sections'=>['SELECT * FROM plot_story_sections WHERE plot_id=? ORDER BY label_text,plot_story_section_id',[$plotId]],
            'events'=>['SELECT * FROM plot_world_events WHERE plot_id=? ORDER BY event_second,sort_order,plot_world_event_id',[$plotId]],
            'decisions'=>['SELECT t.*,COUNT(CASE WHEN o.active=1 THEN 1 END) option_count FROM plot_decision_templates t LEFT JOIN plot_decision_template_options o ON o.template_id=t.template_id WHERE t.plot_id=? GROUP BY t.template_id ORDER BY t.event_code,t.role_code',[$plotId]],
            'options'=>['SELECT o.*,t.event_code,t.role_code FROM plot_decision_template_options o JOIN plot_decision_templates t ON t.template_id=o.template_id WHERE t.plot_id=? ORDER BY t.event_code,t.role_code,o.sort_order',[$plotId]],
            'effects'=>['SELECT e.*,o.option_code,t.event_code,t.role_code FROM plot_option_effects e JOIN plot_decision_template_options o ON o.template_option_id=e.template_option_id JOIN plot_decision_templates t ON t.template_id=o.template_id WHERE t.plot_id=? ORDER BY t.event_code,t.role_code,o.sort_order,e.sort_order',[$plotId]],
            'event_effects'=>['SELECT * FROM plot_world_event_effects WHERE plot_id=? ORDER BY event_code,sort_order,world_event_effect_id',[$plotId]],
            'event_effect_conditions'=>['SELECT c.*,e.event_code FROM plot_world_event_effect_conditions c JOIN plot_world_event_effects e ON e.world_event_effect_id=c.world_event_effect_id WHERE e.plot_id=? ORDER BY c.sort_order,c.world_event_effect_condition_id',[$plotId]],
            'no_action_effects'=>['SELECT * FROM plot_no_action_effects WHERE plot_id=? ORDER BY event_code,role_code,sort_order,no_action_effect_id',[$plotId]],
            'no_action_conditions'=>['SELECT c.*,e.event_code,e.role_code FROM plot_no_action_conditions c JOIN plot_no_action_effects e ON e.no_action_effect_id=c.no_action_effect_id WHERE e.plot_id=? ORDER BY c.sort_order,c.no_action_condition_id',[$plotId]],
            'option_conditions'=>['SELECT c.*,o.template_id,o.option_code FROM plot_option_conditions c JOIN plot_decision_template_options o ON o.template_option_id=c.template_option_id JOIN plot_decision_templates t ON t.template_id=o.template_id WHERE t.plot_id=? ORDER BY c.sort_order,c.option_condition_id',[$plotId]],
            'opportunities'=>['SELECT * FROM plot_dynamic_opportunities WHERE plot_id=? ORDER BY opportunity_code,dynamic_opportunity_id',[$plotId]],
            'dynamic_conditions'=>['SELECT c.*,o.opportunity_code,o.role_code FROM plot_dynamic_opportunity_conditions c JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=c.dynamic_opportunity_id WHERE o.plot_id=? ORDER BY o.opportunity_code,c.sort_order,c.dynamic_condition_id',[$plotId]],
            'dynamic_options'=>['SELECT x.*,o.opportunity_code,o.role_code FROM plot_dynamic_opportunity_options x JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=x.dynamic_opportunity_id WHERE o.plot_id=? ORDER BY o.opportunity_code,x.sort_order,x.dynamic_option_id',[$plotId]],
            'dynamic_effects'=>['SELECT e.*,x.option_code,o.opportunity_code,o.role_code FROM plot_dynamic_option_effects e JOIN plot_dynamic_opportunity_options x ON x.dynamic_option_id=e.dynamic_option_id JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=x.dynamic_opportunity_id WHERE o.plot_id=? ORDER BY o.opportunity_code,x.sort_order,e.sort_order,e.dynamic_effect_id',[$plotId]],
            'resolutions'=>['SELECT * FROM plot_resolution_fragments WHERE plot_id=? ORDER BY section_code,priority,resolution_fragment_id',[$plotId]],
            'resolution_conditions'=>['SELECT c.*,f.section_code FROM plot_resolution_conditions c JOIN plot_resolution_fragments f ON f.resolution_fragment_id=c.resolution_fragment_id WHERE f.plot_id=? ORDER BY f.section_code,f.priority,c.sort_order,c.resolution_condition_id',[$plotId]],
            'media'=>['SELECT * FROM plot_media_assets WHERE plot_id=? ORDER BY placement,sort_order',[$plotId]]
        ];$out=[];foreach($tables as$key=>$spec){$q=db()->prepare($spec[0]);$q->execute($spec[1]);$out[$key]=$q->fetchAll();}return$out;
    }

    public static function stableCodeUsageMap(int $plotId,?array $workspace=null): array
    {
        $w=$workspace??self::workspace($plotId);$map=[];
        $active=static fn(array$row):bool=>(int)($row['active']??1)===1;
        $add=static function(string$entity,int$id,string$keyword,int$count)use(&$map):void{if($count>0)$map[$entity][$id][$keyword]=($map[$entity][$id][$keyword]??0)+$count;};
        $activeDecisions=array_values(array_filter($w['decisions'],$active));$activeOpportunities=array_values(array_filter($w['opportunities'],$active));$activeEffects=array_values(array_filter($w['effects'],$active));$activeDynamicConditions=array_values(array_filter($w['dynamic_conditions'],$active));$activeDynamicEffects=array_values(array_filter($w['dynamic_effects'],$active));$activeResolutions=array_values(array_filter($w['resolutions'],$active));$activeResolutionConditions=array_values(array_filter($w['resolution_conditions'],$active));
        $activeDecisionIds=array_fill_keys(array_map(static fn($x)=>(int)$x['template_id'],$activeDecisions),true);$activeOpportunityIds=array_fill_keys(array_map(static fn($x)=>(int)$x['dynamic_opportunity_id'],$activeOpportunities),true);
        $activeOptions=array_values(array_filter($w['options'],static fn($x)=>$active($x)&&isset($activeDecisionIds[(int)$x['template_id']])));$activeDynamicOptions=array_values(array_filter($w['dynamic_options'],static fn($x)=>$active($x)&&isset($activeOpportunityIds[(int)$x['dynamic_opportunity_id']])));$activeOptionIds=array_fill_keys(array_map(static fn($x)=>(int)$x['template_option_id'],$activeOptions),true);$activeDynamicOptionIds=array_fill_keys(array_map(static fn($x)=>(int)$x['dynamic_option_id'],$activeDynamicOptions),true);$activeResolutionIds=array_fill_keys(array_map(static fn($x)=>(int)$x['resolution_fragment_id'],$activeResolutions),true);
        foreach($activeDecisions as $row){
            $id=(int)$row['template_id'];
            $optionCount=count(array_filter($activeOptions,static fn($x)=>(int)$x['template_id']===$id));
            $opportunityCount=count(array_filter($activeOpportunities,static fn($x)=>(string)$x['opportunity_code']===(string)$row['event_code']&&(string)$x['role_code']===(string)$row['role_code']&&$active($x)));
            $add('decision',$id,'OPTIONS',$optionCount);
            $add('decision',$id,'CONDITIONAL OPPORTUNITIES',$opportunityCount);
        }
        foreach($w['locations']as$row)if($active($row)){$count=count(array_filter($w['characters'],static fn($x)=>$active($x)&&in_array((string)$x['starting_location'],[(string)$row['label_text'],(string)$row['location_code']],true)));$add('location',(int)$row['plot_location_id'],'CHARACTERS',$count);}
        foreach($w['characters']as$row)if($active($row)){$code=(string)$row['role_code'];$add('character',(int)$row['plot_role_id'],'DECISIONS',count(array_filter($activeDecisions,static fn($x)=>(string)$x['role_code']===$code)));$add('character',(int)$row['plot_role_id'],'OPPORTUNITIES',count(array_filter($activeOpportunities,static fn($x)=>(string)$x['role_code']===$code)));$add('character',(int)$row['plot_role_id'],'ARTWORK',count(array_filter($w['media'],static fn($x)=>(int)($x['active']??1)===1&&(string)($x['role_code']??'')===$code)));}
        foreach($w['events']as$row)if($active($row)){$code=(string)$row['event_code'];$add('event',(int)$row['plot_world_event_id'],'DECISIONS',count(array_filter($activeDecisions,static fn($x)=>(string)$x['event_code']===$code)));$add('event',(int)$row['plot_world_event_id'],'ARTWORK',count(array_filter($w['media'],static fn($x)=>(int)($x['active']??1)===1&&(string)($x['event_code']??'')===$code)));}
        foreach($activeOptions as$row){ $id=(int)$row['template_option_id'];$code=(string)$row['option_code'];$add('option',$id,'EFFECTS',count(array_filter($activeEffects,static fn($x)=>isset($activeOptionIds[(int)$x['template_option_id']])&&(int)$x['template_option_id']===$id)));$add('option',$id,'CONDITIONS',count(array_filter($activeDynamicConditions,static fn($x)=>isset($activeOpportunityIds[(int)($x['dynamic_opportunity_id']??0)])&&(string)$x['source_type']==='OPTION_CHOICE_COUNT'&&(string)$x['source_key']===$code)));}
        foreach($w['opportunities']as$row)if($active($row)){ $id=(int)$row['dynamic_opportunity_id'];$add('opportunity',$id,'CONDITIONS',count(array_filter($activeDynamicConditions,static fn($x)=>(int)$x['dynamic_opportunity_id']===$id)));$add('opportunity',$id,'CHOICES',count(array_filter($activeDynamicOptions,static fn($x)=>(int)$x['dynamic_opportunity_id']===$id)));}
        foreach($activeDynamicOptions as$row){ $id=(int)$row['dynamic_option_id'];$code=(string)$row['option_code'];$add('dynamic_option',$id,'EFFECTS',count(array_filter($activeDynamicEffects,static fn($x)=>(int)$x['dynamic_option_id']===$id)));$add('dynamic_option',$id,'CONDITIONS',count(array_filter($activeDynamicConditions,static fn($x)=>isset($activeOpportunityIds[(int)($x['dynamic_opportunity_id']??0)])&&(string)$x['source_type']==='OPTION_CHOICE_COUNT'&&(string)$x['source_key']===$code)));}
        foreach($w['state_definitions']as$row)if($active($row)){ $id=(int)$row['plot_state_definition_id'];$scope=(string)$row['effect_scope'];$code=(string)$row['target_code'];$optionEffects=count(array_filter($activeEffects,static fn($x)=>isset($activeOptionIds[(int)$x['template_option_id']])&&(string)$x['effect_scope']===$scope&&(string)$x['target_code']===$code));$dynamicEffects=count(array_filter($activeDynamicEffects,static fn($x)=>isset($activeDynamicOptionIds[(int)$x['dynamic_option_id']])&&(string)$x['effect_scope']===$scope&&(string)$x['target_code']===$code));$dynamicConditions=count(array_filter($activeDynamicConditions,static fn($x)=>isset($activeOpportunityIds[(int)$x['dynamic_opportunity_id']])&&(string)$x['source_type']===$scope&&(string)$x['source_key']===$code));$endingConditions=count(array_filter($activeResolutionConditions,static fn($x)=>isset($activeResolutionIds[(int)$x['resolution_fragment_id']])&&(string)$x['state_key']===$code));$add('state_definition',$id,'EFFECTS',$optionEffects+$dynamicEffects);$add('state_definition',$id,'CONDITIONS',$dynamicConditions+$endingConditions);}
        foreach($w['story_sections']as$row)if($active($row)){ $code=(string)$row['section_code'];$add('story_section',(int)$row['plot_story_section_id'],'ENDINGS',count(array_filter($activeResolutions,static fn($x)=>(string)$x['section_code']===$code)));}
        return$map;
    }

    public static function storyCodeUsage(int $plotId): array
    {
        $usage=[];$q=db()->prepare('SELECT table_name FROM information_schema.columns WHERE table_schema=DATABASE() AND column_name="plot_id" ORDER BY table_name');$q->execute();
        foreach($q->fetchAll(PDO::FETCH_COLUMN)as$table){$table=(string)$table;if(in_array($table,['plots','simulations','inky_story_guide_drafts'],true)||!preg_match('/^[a-z0-9_]+$/',$table))continue;$countQ=db()->prepare('SELECT COUNT(*) FROM `'.$table.'` WHERE plot_id=?');$countQ->execute([$plotId]);$count=(int)$countQ->fetchColumn();if($count>0){$label=strtoupper(str_replace('_',' ',preg_replace('/^plot_/','',$table)));$usage[$label]=$count;}}
        $historyQ=db()->prepare('SELECT COUNT(*) FROM simulations WHERE plot_id=?');$historyQ->execute([$plotId]);$history=(int)$historyQ->fetchColumn();if($history>0)$usage['PLAY HISTORY']=$history;return$usage;
    }

    public static function deleteStory(array $user,int $plotId): void
    {
        self::guard($user,$plotId);$usage=self::storyCodeUsage($plotId);if($usage){$parts=[];foreach($usage as$keyword=>$count)$parts[]=$keyword.' '.$count;throw new RuntimeException('Cannot delete this story while it has linked records: '.implode(' · ',$parts));}
        $pdo=db();$pdo->beginTransaction();try{$q=$pdo->prepare('DELETE FROM plots WHERE plot_id=?');$q->execute([$plotId]);if($q->rowCount()<1)throw new RuntimeException('Story not found.');$pdo->commit();}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }

    public static function deleteDraft(array $user,int $plotId): void
    {
        self::guard($user,$plotId);$q=db()->prepare('SELECT workflow_status FROM plots WHERE plot_id=? LIMIT 1');$q->execute([$plotId]);if((string)$q->fetchColumn()!=='DRAFT')throw new RuntimeException('Only draft stories can be deleted here.');$pdo=db();$pdo->beginTransaction();try{$q=$pdo->prepare('DELETE FROM plots WHERE plot_id=?');$q->execute([$plotId]);if($q->rowCount()<1)throw new RuntimeException('Story not found.');$pdo->commit();}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }

    public static function dependencyDetails(int $plotId,?array $workspace=null): array
    {
        $w=$workspace??self::workspace($plotId);$out=[];$add=static function(string$entity,int$id,string$text)use(&$out):void{$out[$entity][$id][]=$text;};$status=static fn(array$row):string=>(int)($row['active']??1)===1?'':' · inactive';
        foreach($w['characters']as$row){$id=(int)$row['plot_role_id'];$code=(string)$row['role_code'];foreach($w['decisions']as$x)if((string)$x['role_code']===$code)$add('character',$id,'Decision · '.$x['event_code'].$status($x));foreach($w['opportunities']as$x)if((string)$x['role_code']===$code)$add('character',$id,'Opportunity · '.$x['opportunity_code'].$status($x));foreach($w['media']as$x)if((int)($x['active']??1)===1&&(string)($x['role_code']??'')===$code)$add('character',$id,'Artwork · '.$x['asset_code'].$status($x));}
        foreach($w['locations']as$row){$id=(int)$row['plot_location_id'];foreach($w['characters']as$x)if(in_array((string)$x['starting_location'],[(string)$row['label_text'],(string)$row['location_code']],true))$add('location',$id,'Character · '.$x['name'].$status($x));}
        foreach($w['events']as$row){$id=(int)$row['plot_world_event_id'];$code=(string)$row['event_code'];foreach($w['decisions']as$x)if((string)$x['event_code']===$code)$add('event',$id,'Decision · '.$x['role_code'].$status($x));foreach($w['media']as$x)if((int)($x['active']??1)===1&&(string)($x['event_code']??'')===$code)$add('event',$id,'Artwork · '.$x['asset_code'].$status($x));}
        foreach($w['decisions']as$row){$id=(int)$row['template_id'];foreach($w['options']as$x)if((int)$x['template_id']===$id)$add('decision',$id,'Option · '.$x['option_code'].$status($x));foreach($w['opportunities']as$x)if((string)$x['opportunity_code']===(string)$row['event_code']&&(string)$x['role_code']===(string)$row['role_code'])$add('decision',$id,'Conditional Opportunity · '.$x['opportunity_code'].$status($x));}
        foreach($w['options']as$row){$id=(int)$row['template_option_id'];$code=(string)$row['option_code'];foreach($w['effects']as$x)if((int)$x['template_option_id']===$id)$add('option',$id,'Effect · '.$x['effect_scope'].' '.$x['target_code'].$status($x));foreach($w['option_conditions']as$x)if((int)$x['template_option_id']===$id)$add('option',$id,'Condition · '.$x['state_key'].' '.$x['operator'].' '.($x['compare_value']??''));foreach($w['dynamic_conditions']as$x)if((string)$x['source_type']==='OPTION_CHOICE_COUNT'&&(string)$x['source_key']===$code)$add('option',$id,'Condition · '.$x['opportunity_code'].$status($x));foreach($w['dynamic_options']as$x)if((string)$x['opportunity_code']===(string)$row['event_code']&&(string)$x['role_code']===(string)$row['role_code']&&(string)$x['option_code']===$code)$add('option',$id,'Conditional Choice Mirror · '.$code.$status($x));}
        foreach($w['effects']as$row)$add('effect',(int)$row['effect_id'],'Story Value · '.$row['effect_scope'].' '.$row['target_code']);
        foreach($w['opportunities']as$row){$id=(int)$row['dynamic_opportunity_id'];foreach($w['dynamic_conditions']as$x)if((int)$x['dynamic_opportunity_id']===$id)$add('opportunity',$id,'Condition · '.$x['source_type'].' '.($x['source_key']?:'current').$status($x));foreach($w['dynamic_options']as$x)if((int)$x['dynamic_opportunity_id']===$id)$add('opportunity',$id,'Choice · '.$x['option_code'].$status($x));}
        foreach($w['dynamic_options']as$row){$id=(int)$row['dynamic_option_id'];$code=(string)$row['option_code'];foreach($w['dynamic_effects']as$x)if((int)$x['dynamic_option_id']===$id)$add('dynamic_option',$id,'Effect · '.$x['effect_scope'].' '.$x['target_code'].$status($x));foreach($w['dynamic_conditions']as$x)if((string)$x['source_type']==='OPTION_CHOICE_COUNT'&&(string)$x['source_key']===$code)$add('dynamic_option',$id,'Condition · '.$x['opportunity_code'].$status($x));}
        foreach($w['dynamic_effects']as$row)$add('dynamic_effect',(int)$row['dynamic_effect_id'],'Story Value · '.$row['effect_scope'].' '.$row['target_code']);
        foreach($w['state_definitions']as$row){$id=(int)$row['plot_state_definition_id'];$scope=(string)$row['effect_scope'];$code=(string)$row['target_code'];foreach($w['effects']as$x)if((string)$x['effect_scope']===$scope&&(string)$x['target_code']===$code)$add('state_definition',$id,'Option Effect · '.$x['event_code'].' · '.$x['option_code'].$status($x));foreach($w['dynamic_effects']as$x)if((string)$x['effect_scope']===$scope&&(string)$x['target_code']===$code)$add('state_definition',$id,'Conditional Effect · '.$x['opportunity_code'].' · '.$x['option_code'].$status($x));foreach($w['dynamic_conditions']as$x)if((string)$x['source_type']===$scope&&(string)$x['source_key']===$code)$add('state_definition',$id,'Opportunity Condition · '.$x['opportunity_code'].$status($x));foreach($w['resolution_conditions']as$x)if((string)$x['state_key']===$code)$add('state_definition',$id,'Ending Condition · '.$x['section_code'].$status($x));foreach($w['event_effects']as$x)if((string)$x['effect_scope']===$scope&&(string)$x['target_code']===$code)$add('state_definition',$id,'Event Effect · '.$x['event_code'].$status($x));foreach($w['no_action_effects']as$x)if((string)$x['effect_scope']===$scope&&(string)$x['target_code']===$code)$add('state_definition',$id,'No Action Effect · '.$x['event_code'].' · '.$x['role_code']);foreach($w['event_effect_conditions']as$x)if((string)$x['state_key']===$code||(string)$x['state_key']==='FACTOR:'.$code)$add('state_definition',$id,'Event Effect Condition · '.$x['event_code']);foreach($w['no_action_conditions']as$x)if((string)$x['state_key']===$code||(string)$x['state_key']==='FACTOR:'.$code)$add('state_definition',$id,'No Action Condition · '.$x['event_code'].' · '.$x['role_code']);foreach($w['option_conditions']as$x)if((string)$x['state_key']===$code||(string)$x['state_key']==='FACTOR:'.$code)$add('state_definition',$id,'Option Condition · '.$x['option_code']);}
        foreach($w['story_sections']as$row){$id=(int)$row['plot_story_section_id'];$code=(string)$row['section_code'];foreach($w['resolutions']as$x)if((string)$x['section_code']===$code)$add('story_section',$id,'Ending Fragment · '.$x['priority'].$status($x));}
        foreach($w['resolutions']as$row){$id=(int)$row['resolution_fragment_id'];foreach($w['resolution_conditions']as$x)if((int)$x['resolution_fragment_id']===$id)$add('resolution',$id,'Condition · '.$x['state_key'].$status($x));}
        return$out;
    }

    public static function inkyDraftDependencyMap(int $plotId,array $accepted,?array $workspace=null,?array $details=null,?array $usage=null): array
    {
        if($plotId<1||!$accepted)return[];
        $w=$workspace??self::workspace($plotId);
        $details=$details??self::dependencyDetails($plotId,$w);
        $usage=$usage??self::stableCodeUsageMap($plotId,$w);
        $out=[];
        foreach($accepted as$key=>$value){$base=self::inkyDraftEntryLabel((string)$key);$preview=self::inkyDraftEntryPreview((string)$key,$value);$reference=self::inkyProposalReference($key,$value,$w,$accepted);if(!$reference){$out[(string)$key]=['match'=>$base,'display'=>$base.($preview!==''?' · '.$preview:''),'preview'=>$preview,'blocked'=>false,'dependencies'=>[],'usage'=>[]];continue;}[$entity,$id]=$reference;$items=array_values(array_unique((array)($details[$entity][$id]??[])));$active=(array)($usage[$entity][$id]??[]);$entry=['match'=>$base,'display'=>$base.($preview!==''?' · '.$preview:''),'preview'=>$preview,'blocked'=>(bool)$active,'dependencies'=>$items,'usage'=>$active,'entity'=>$entity,'entity_id'=>$id,'writer_url'=>self::inkyDraftWriterUrl($plotId,$entity,$id)];if($entity==='decision')$entry['children']=self::inkyDecisionChildren($plotId,$id,$w);$out[(string)$key]=$entry;}
        return$out;
    }

    public static function inkyDraftEntries(int $plotId,array $accepted): array
    {
        if($plotId<1)return[];
        $w=self::workspace($plotId);
        $details=self::dependencyDetails($plotId,$w);
        $usage=self::stableCodeUsageMap($plotId,$w);
        $mapped=self::inkyDraftDependencyMap($plotId,$accepted,$w,$details,$usage);
        $entries=[];
        $represented=[];
        foreach($accepted as$key=>$value){
            $entry=$mapped[(string)$key]??[
                'match'=>self::inkyDraftEntryLabel((string)$key),
                'display'=>self::inkyDraftEntryLabel((string)$key).(($preview=self::inkyDraftEntryPreview((string)$key,$value))!==''?' · '.$preview:''),
                'blocked'=>false,
                'dependencies'=>[],
                'usage'=>[]
            ];
            if(isset($entry['entity'],$entry['entity_id']))$represented[$entry['entity'].':'.$entry['entity_id']]=true;
            $entry['key']=(string)$key;
            $entry['stage']=self::inkyDraftStage((string)$key);
            $entry['source']='cowriter';
            $entries[]=$entry;
        }
        $addManual=function(string$key,string$stage,string$match,string$display,string$entity,int$id)use(&$entries,$represented,$details,$usage,$plotId,$w):void{
            if(isset($represented[$entity.':'.$id]))return;
            $dependencies=array_values(array_unique((array)($details[$entity][$id]??[])));
            $entry=[
                'key'=>$key,
                'stage'=>$stage,
                'source'=>'writer',
                'manual'=>true,
                'match'=>$match,
                'display'=>$display,
                'preview'=>'',
                'blocked'=>(bool)($usage[$entity][$id]??[]),
                'dependencies'=>$dependencies,
                'usage'=>(array)($usage[$entity][$id]??[]),
                'entity'=>$entity,
                'entity_id'=>$id,
                'writer_url'=>self::inkyDraftWriterUrl($plotId,$entity,$id)
            ];
            if($entity==='decision')$entry['children']=self::inkyDecisionChildren($plotId,$id,$w);
            $entries[]=$entry;
        };
        foreach($w['locations']as$row){
            if((int)($row['active']??1)!==1)continue;
            $id=(int)$row['plot_location_id'];
            $label=trim((string)($row['label_text']??$row['location_code']??'Location'));
            $addManual('writer_location_'.$id,'Locations','Location · '.$label,'Location · '.$label,'location',$id);
        }
        foreach($w['state_definitions']as$row){
            if((int)($row['active']??1)!==1)continue;
            $id=(int)$row['plot_state_definition_id'];
            $label=trim((string)($row['label_text']??$row['target_code']??'Story Value'));
            $addManual('writer_state_definition_'.$id,'Story Values','Story Value · '.$label,'Story Value · '.$label,'state_definition',$id);
        }
        foreach($w['characters']as$row){
            if((int)($row['active']??1)!==1)continue;
            $id=(int)$row['plot_role_id'];
            $slot=(int)($row['slot_no']??0);
            $prefix=$slot>0?'Character '.$slot:'Character';
            $name=trim((string)($row['name']??$row['role_code']??'Unnamed'));
            $addManual('writer_character_'.$id,'Characters',$prefix,$prefix.' · '.$name,'character',$id);
        }
        foreach($w['events']as$row){
            if((int)($row['active']??1)!==1)continue;
            $id=(int)$row['plot_world_event_id'];
            $headline=trim((string)($row['headline']??$row['event_code']??'Event'));
            $prefix=(int)($row['event_second']??0)===0?'Opening Event':'Event';
            $addManual('writer_event_'.$id,'World Events',$prefix,$prefix.' · '.$headline,'event',$id);
        }
        foreach($w['decisions']as$row){
            if((int)($row['active']??1)!==1)continue;
            $id=(int)$row['template_id'];
            $role=trim((string)($row['role_code']??'Character'));
            $prompt=self::inkyDraftText((string)($row['prompt_text']??''));
            $display='Decision · '.$role.($prompt!==''?' · '.$prompt:'');
            $addManual('writer_decision_'.$id,'Decisions','Decision · '.$role,$display,'decision',$id);
        }
        foreach($w['opportunities']as$row){
            if((int)($row['active']??1)!==1)continue;
            $id=(int)$row['dynamic_opportunity_id'];
            $code=trim((string)($row['opportunity_code']??'Opportunity'));
            $addManual('writer_opportunity_'.$id,'Opportunities','Opportunity · '.$code,'Opportunity · '.$code,'opportunity',$id);
        }
        foreach($w['story_sections']as$row){
            if((int)($row['active']??1)!==1)continue;
            $id=(int)$row['plot_story_section_id'];
            $label=trim((string)($row['label_text']??$row['section_code']??'Ending Section'));
            $addManual('writer_story_section_'.$id,'Ending Sections','Ending Section · '.$label,'Ending Section · '.$label,'story_section',$id);
        }
        foreach($w['resolutions']as$row){
            if((int)($row['active']??1)!==1)continue;
            $id=(int)$row['resolution_fragment_id'];
            $preview=self::inkyDraftText((string)($row['fragment_text']??''));
            $addManual('writer_resolution_'.$id,'Ending Fragments','Ending Fragment · '.$id,'Ending Fragment · '.$id.($preview!==''?' · '.$preview:''),'resolution',$id);
        }
        return$entries;
    }

    public static function inkyDraftSnapshot(int $plotId,array $accepted): array
    {
        $entries=self::inkyDraftEntries($plotId,$accepted);
        $dependencies=[];
        foreach($entries as$entry){
            if(($entry['source']??'')!=='cowriter'||!isset($entry['key']))continue;
            $key=(string)$entry['key'];
            unset($entry['key'],$entry['stage'],$entry['source']);
            $dependencies[$key]=$entry;
        }
        return['dependencies'=>$dependencies,'entries'=>$entries];
    }

    private static function inkyDraftStage(string$key): string
    {
        if(preg_match('/^location_/', $key)===1)return'Locations';
        if(preg_match('/^story_value_/', $key)===1)return'Story Values';
        if(preg_match('/^character_/', $key)===1)return'Characters';
        if(preg_match('/^event_/', $key)===1)return'World Events';
        if(preg_match('/^decision_/', $key)===1)return'Decisions';
        if(preg_match('/^opportunity_/', $key)===1)return'Opportunities';
        if(preg_match('/^ending_section_/', $key)===1)return'Ending Sections';
        if(preg_match('/^ending_/', $key)===1)return'Ending Fragments';
        return'Story Overview';
    }

    private static function inkyDraftWriterUrl(int$plotId,string$entity,int$id): string
    {
        $tab=match($entity){
            'character'=>'characters',
            'event'=>'timeline',
            'decision'=>'decisions',
            'opportunity'=>'map',
            'story_section','resolution'=>'endings',
            'location','state_definition'=>'overview',
            default=>'overview'
        };
        $subtab=match($entity){
            'location'=>'locations',
            'state_definition'=>'values',
            'opportunity'=>'opportunities',
            'story_section'=>'sections',
            'resolution'=>'fragments',
            default=>''
        };
        $anchor=$entity==='decision'?'#decision-'.$id.'-options':'';
        return'writer.php?plot='.$plotId.'&tab='.$tab.($subtab!==''?'&subtab='.$subtab:'').$anchor;
    }

    private static function inkyDecisionChildren(int$plotId,int$decisionId,array$workspace): array
    {
        $children=[];
        $index=0;
        foreach($workspace['options']as$option){
            if((int)($option['template_id']??0)!==$decisionId)continue;
            $index++;
            $label=self::inkyDraftText((string)($option['label']??$option['option_code']??'Option '.$index));
            $result=self::inkyDraftText((string)($option['result_text']??''));
            $effects=[];
            foreach($workspace['effects']as$effect){
                if((int)($effect['template_option_id']??0)!==(int)$option['template_option_id'])continue;
                $effectText=(string)($effect['effect_scope']??'').' · '.(string)($effect['target_code']??'');
                $effects[]='Effect · '.trim($effectText);
            }
            $children[]=[
                'label'=>'Option '.$index,
                'display'=>'Option '.$index.' · '.$label.($result!==''?' · '.$result:''),
                'preview'=>$result,
                'effects'=>$effects,
                'writer_url'=>self::inkyDraftWriterUrl($plotId,'decision',$decisionId)
            ];
        }
        return$children;
    }

    private static function inkyDraftText(string$text): string
    {
        $text=trim(preg_replace('/\s+/',' ',strip_tags($text)));
        return mb_strlen($text)>96?mb_substr($text,0,96).'...':$text;
    }

    public static function assertInkyDraftEditAllowed(int $plotId,string$key,mixed$previous,mixed$next): void
    {
        if($plotId<1||$previous===$next)return;$entry=self::inkyDraftDependencyMap($plotId,[$key=>$previous])[$key]??null;if(!$entry||empty($entry['blocked']))return;$items=[];foreach((array)($entry['dependencies']??[])as$item)$items[]=$item;foreach((array)($entry['usage']??[])as$label=>$count)$items[]=$label.' '.(int)$count;$items=array_values(array_unique($items));throw new RuntimeException('Cannot edit this accepted entry while it has active linked records: '.implode(' · ',$items).'. Deactivate or remove those records first.');
    }

    private static function inkyProposalReference(string$key,mixed$value,array$w,array$accepted=[]): ?array
    {
        $active=static fn(array$row):bool=>(int)($row['active']??1)===1;$number=static function(string$key):?int{if(preg_match('/_(\d+)(?:_|$)/',$key,$m)!==1)return null;return(int)$m[1];};$code=static fn($value):string=>self::code((string)$value);$find=static function(array$rows,callable$test)use($active):?array{foreach($rows as$row)if($active($row)&&$test($row))return$row;return null;};$n=$number($key);
        if(preg_match('/^location_([12])_(name|description)$/',$key,$m)===1){$wanted=$code(is_string($value)?$value:(is_array($value)?($value['label_text']??''):''));if($m[2]==='description')$wanted=$code($accepted['location_'.$m[1].'_name']??'');$row=$wanted!==''?$find($w['locations'],static fn($x)=>$code($x['location_code']??'')===$wanted||$code($x['label_text']??'')===$wanted):null;if(!$row)$row=$find($w['locations'],static fn($x)=>(int)($x['sort_order']??0)===(int)$m[1]*10);return$row?['location',(int)$row['plot_location_id']]:null;}
        if(preg_match('/^location_\d+$/',$key)===1&&is_array($value)){$wanted=$code($value['location_code']??$value['label_text']??'');$row=$find($w['locations'],static fn($x)=>$wanted!==''&&($code($x['location_code']??'')===$wanted||$code($x['label_text']??'')===$wanted));return$row?['location',(int)$row['plot_location_id']]:null;}
        if($key==='event_1'){$row=$find($w['events'],static fn($x)=>true);return$row?['event',(int)$row['plot_world_event_id']]:null;}
        if(preg_match('/^event_\d+$/',$key)===1&&is_array($value)){$wanted=$code($value['event_code']??$value['headline']??'');$row=$find($w['events'],static fn($x)=>$wanted!==''&&($code($x['event_code']??'')===$wanted||$code($x['headline']??'')===$wanted));return$row?['event',(int)$row['plot_world_event_id']]:null;}
        if(preg_match('/^character_\d+$/',$key)===1&&is_array($value)){$wanted=$code($value['role_code']??$value['name']??'');$row=$wanted!==''?$find($w['characters'],static fn($x)=>$code($x['role_code']??'')===$wanted||$code($x['name']??'')===$wanted):null;if(!$row&&$n!==null)$row=$find($w['characters'],static fn($x)=>(int)($x['slot_no']??0)===$n);return$row?['character',(int)$row['plot_role_id']]:null;}
        if(preg_match('/^story_value_\d+$/',$key)===1&&is_array($value)){$wanted=$code($value['target_code']??$value['label']??'');$scope=strtoupper(trim((string)($value['scope']??'')));$row=$find($w['state_definitions'],static fn($x)=>$wanted!==''&&$code($x['target_code']??'')===$wanted&&($scope===''||strtoupper((string)($x['effect_scope']??''))===$scope));return$row?['state_definition',(int)$row['plot_state_definition_id']]:null;}
        if(preg_match('/^decision_\d+$/',$key)===1&&is_array($value)){$event=$code($value['event_code']??'');$character=$code($value['character']??'');$row=$find($w['decisions'],static fn($x)=>($event===''||$code($x['event_code']??'')===$event)&&($character===''||$code($x['role_code']??'')===$character||$code($x['role_name']??'')===$character));if(!$row&&$key==='decision_1')$row=$find($w['decisions'],static fn($x)=>true);return$row?['decision',(int)$row['template_id']]:null;}
        if($key==='opportunity_1'&&is_array($value)){$event=$code($value['opportunity_code']??$value['prompt']??'');$character=$code($value['character']??'');$row=$find($w['opportunities'],static fn($x)=>($event===''||$code($x['opportunity_code']??'')===$event)&&($character===''||$code($x['role_code']??'')===$character));return$row?['opportunity',(int)$row['dynamic_opportunity_id']]:null;}
        if($key==='ending_section_1'&&is_array($value)){$wanted=$code($value['section_code']??$value['label']??'');$row=$find($w['story_sections'],static fn($x)=>$wanted!==''&&($code($x['section_code']??'')===$wanted||$code($x['label_text']??'')===$wanted));return$row?['story_section',(int)$row['plot_story_section_id']]:null;}
        if($key==='ending_1'&&is_array($value)){$wanted=$code($value['section']??'');$row=$find($w['resolutions'],static fn($x)=>$wanted===''||$code($x['section_code']??'')===$wanted);return$row?['resolution',(int)$row['resolution_fragment_id']]:null;}
        return null;
    }

    private static function inkyDraftEntryLabel(string$key): string
    {
        if(preg_match('/^location_(\d+)_name$/',$key,$m)===1)return'Location '.$m[1].' · Name';if(preg_match('/^location_(\d+)_description$/',$key,$m)===1)return'Location '.$m[1].' · Description';if(preg_match('/^location_(\d+)$/',$key,$m)===1)return'Location '.$m[1];if(preg_match('/^character_(\d+)$/',$key,$m)===1)return'Character '.$m[1];if($key==='event_1')return'Opening Event';if(preg_match('/^event_(\d+)$/',$key,$m)===1)return'Event '.$m[1];if(preg_match('/^decision_(\d+)$/',$key,$m)===1)return'Decision '.$m[1];if($key==='opportunity_1')return'Conditional Opportunity 1';if(preg_match('/^story_value_(\d+)$/',$key,$m)===1)return'Story Value '.$m[1];if($key==='ending_section_1')return'Ending Section 1';if($key==='ending_1')return'Ending 1';return ucwords(str_replace('_',' ',$key));
    }

    private static function inkyDraftEntryPreview(string$key,mixed$value): string
    {
        if(is_array($value)){$field=preg_match('/^character_/',$key)===1?'name':(preg_match('/^location_/',$key)===1?'label_text':(preg_match('/^event_/',$key)===1?'headline':(preg_match('/^decision_/',$key)===1?'prompt':(preg_match('/^story_value_/',$key)===1?'label':(preg_match('/^ending/',$key)===1?'headline':'')))));$value=$field!==''?($value[$field]??''):(reset($value)?:'');} $text=trim(preg_replace('/\s+/',' ',strip_tags((string)$value)));if($text==='')return'';return mb_strlen($text)>72?mb_substr($text,0,72).'...':$text;
    }

    public static function deleteEntity(array $user,int $plotId,string $entity,int $id): void
    {
        self::guard($user,$plotId);$entity=trim($entity);$id=(int)$id;
        $deleteSql=[
            'location'=>['DELETE FROM plot_locations WHERE plot_location_id=? AND plot_id=?'],
            'state_definition'=>['DELETE FROM plot_state_definitions WHERE plot_state_definition_id=? AND plot_id=?'],
            'story_section'=>['DELETE FROM plot_story_sections WHERE plot_story_section_id=? AND plot_id=?'],
            'character'=>['DELETE FROM plot_roles WHERE plot_role_id=? AND plot_id=?'],
            'event'=>['DELETE FROM plot_world_events WHERE plot_world_event_id=? AND plot_id=?'],
            'decision'=>['DELETE FROM plot_decision_templates WHERE template_id=? AND plot_id=?'],
            'option'=>['DELETE x FROM plot_decision_template_options x JOIN plot_decision_templates t ON t.template_id=x.template_id WHERE x.template_option_id=? AND t.plot_id=?'],
            'effect'=>['DELETE e FROM plot_option_effects e JOIN plot_decision_template_options x ON x.template_option_id=e.template_option_id JOIN plot_decision_templates t ON t.template_id=x.template_id WHERE e.effect_id=? AND t.plot_id=?'],
            'event_effect'=>['DELETE FROM plot_world_event_effects WHERE world_event_effect_id=? AND plot_id=?'],
            'event_effect_condition'=>['DELETE c FROM plot_world_event_effect_conditions c JOIN plot_world_event_effects e ON e.world_event_effect_id=c.world_event_effect_id WHERE c.world_event_effect_condition_id=? AND e.plot_id=?'],
            'no_action_effect'=>['DELETE FROM plot_no_action_effects WHERE no_action_effect_id=? AND plot_id=?'],
            'no_action_condition'=>['DELETE c FROM plot_no_action_conditions c JOIN plot_no_action_effects e ON e.no_action_effect_id=c.no_action_effect_id WHERE c.no_action_condition_id=? AND e.plot_id=?'],
            'option_condition'=>['DELETE c FROM plot_option_conditions c JOIN plot_decision_template_options o ON o.template_option_id=c.template_option_id JOIN plot_decision_templates t ON t.template_id=o.template_id WHERE c.option_condition_id=? AND t.plot_id=?'],
            'opportunity'=>['DELETE FROM plot_dynamic_opportunities WHERE dynamic_opportunity_id=? AND plot_id=?'],
            'dynamic_condition'=>['DELETE c FROM plot_dynamic_opportunity_conditions c JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=c.dynamic_opportunity_id WHERE c.dynamic_condition_id=? AND o.plot_id=?'],
            'dynamic_option'=>['DELETE x FROM plot_dynamic_opportunity_options x JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=x.dynamic_opportunity_id WHERE x.dynamic_option_id=? AND o.plot_id=?'],
            'dynamic_effect'=>['DELETE e FROM plot_dynamic_option_effects e JOIN plot_dynamic_opportunity_options x ON x.dynamic_option_id=e.dynamic_option_id JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=x.dynamic_opportunity_id WHERE e.dynamic_effect_id=? AND o.plot_id=?'],
            'resolution'=>['DELETE FROM plot_resolution_fragments WHERE resolution_fragment_id=? AND plot_id=?'],
            'resolution_condition'=>['DELETE c FROM plot_resolution_conditions c JOIN plot_resolution_fragments f ON f.resolution_fragment_id=c.resolution_fragment_id WHERE c.resolution_condition_id=? AND f.plot_id=?'],
        ];
        if($id<1||!isset($deleteSql[$entity]))throw new RuntimeException('That story item cannot be deleted.');
        $w=self::workspace($plotId);$dependencies=self::dependencyDetails($plotId,$w)[$entity][$id]??[];if(in_array($entity,['effect','dynamic_effect','dynamic_condition','resolution_condition','event_effect','event_effect_condition','no_action_effect','no_action_condition'],true))$dependencies=[];
        $rowById=['character'=>['plot_role_id','role_code'],'event'=>['plot_world_event_id','event_code']];
        if(isset($rowById[$entity])){foreach($w[$entity==='character'?'characters':'events']as$row)if((int)$row[$rowById[$entity][0]]===$id){$code=(string)$row[$rowById[$entity][1]];$column=$entity==='character'?'role_code':'event_code';$q=db()->prepare('SELECT COUNT(*) FROM plot_event_perceptions WHERE plot_id=? AND '.$column.'=?');$q->execute([$plotId,$code]);if((int)$q->fetchColumn()>0)$dependencies[]='Perception records · '.$code;break;}}
        if($entity==='decision'){foreach($w['decisions']as$row)if((int)$row['template_id']===$id){$q=db()->prepare('SELECT 1 FROM plot_dynamic_opportunities WHERE plot_id=? AND opportunity_code=? AND role_code=? LIMIT 1');$q->execute([$plotId,$row['event_code'],$row['role_code']]);if($q->fetchColumn())$dependencies[]='Conditional opportunity · '.$row['event_code'];break;}}
        if($entity==='option'){foreach($w['options']as$row)if((int)$row['template_option_id']===$id){$q=db()->prepare('SELECT t.event_code,t.role_code FROM plot_decision_template_options x JOIN plot_decision_templates t ON t.template_id=x.template_id WHERE x.template_option_id=? AND t.plot_id=?');$q->execute([$id,$plotId]);$parent=$q->fetch();if($parent){$mirror=db()->prepare('SELECT 1 FROM plot_dynamic_opportunity_options x JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=x.dynamic_opportunity_id WHERE o.plot_id=? AND o.opportunity_code=? AND o.role_code=? AND x.option_code=? LIMIT 1');$mirror->execute([$plotId,$parent['event_code'],$parent['role_code'],$row['option_code']]);if($mirror->fetchColumn())$dependencies[]='Conditional choice mirror · '.$row['option_code'];}break;}}
        if($dependencies)throw new RuntimeException('Cannot delete this item while it has linked records: '.implode(' · ',$dependencies));
        $pdo=db();$ownsTransaction=!$pdo->inTransaction();if($ownsTransaction)$pdo->beginTransaction();try{
            if($entity==='opportunity'){$q=$pdo->prepare('SELECT opportunity_code,role_code FROM plot_dynamic_opportunities WHERE dynamic_opportunity_id=? AND plot_id=?');$q->execute([$id,$plotId]);$row=$q->fetch();if($row)$pdo->prepare('DELETE FROM plot_decision_templates WHERE plot_id=? AND event_code=? AND role_code=?')->execute([$plotId,$row['opportunity_code'],$row['role_code']]);}
            if($entity==='dynamic_option'){$q=$pdo->prepare('SELECT x.option_code,o.opportunity_code,o.role_code FROM plot_dynamic_opportunity_options x JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=x.dynamic_opportunity_id WHERE x.dynamic_option_id=? AND o.plot_id=?');$q->execute([$id,$plotId]);$row=$q->fetch();if($row)$pdo->prepare('DELETE x FROM plot_decision_template_options x JOIN plot_decision_templates t ON t.template_id=x.template_id WHERE t.plot_id=? AND t.event_code=? AND t.role_code=? AND x.option_code=?')->execute([$plotId,$row['opportunity_code'],$row['role_code'],$row['option_code']]);}
            if($entity==='character'){$pdo->prepare('DELETE FROM characters WHERE plot_role_id=?')->execute([$id]);}
            $q=$pdo->prepare($deleteSql[$entity][0]);$q->execute([$id,$plotId]);if($q->rowCount()<1)throw new RuntimeException('Story item not found.');
            if($ownsTransaction)$pdo->commit();
        }catch(Throwable$e){if($ownsTransaction&&$pdo->inTransaction())$pdo->rollBack();throw$e;}
    }

    public static function audit(int $plotId): array
    {
        return self::auditWorkspace(self::story($plotId),self::workspace($plotId));
    }

    public static function auditVersion(int $versionId): array
    {
        $q=db()->prepare('SELECT v.snapshot_json,v.workflow_status,v.version_no,p.plot_id,p.code,p.title,p.genre,p.workflow_status current_workflow,u.display_name author_name,g.label_text genre_label FROM plot_versions v JOIN plots p ON p.plot_id=v.plot_id LEFT JOIN users u ON u.user_id=p.author_user_id LEFT JOIN story_genres g ON g.genre_id=p.genre_id WHERE v.plot_version_id=?');$q->execute([$versionId]);$version=$q->fetch();if(!$version)throw new RuntimeException('Submitted revision not found.');$snapshot=json_decode((string)$version['snapshot_json'],true);if(!is_array($snapshot)||!is_array($snapshot['content']??null))throw new RuntimeException('The submitted revision snapshot is invalid.');$plot=is_array($snapshot['plot']??null)?$snapshot['plot']:[];$plot=array_merge($version,$plot,['workflow_status'=>$version['workflow_status']]);$workspace=$snapshot['content'];$current=self::workspace((int)$version['plot_id']);foreach($current as$key=>$rows)if(!array_key_exists($key,$workspace))$workspace[$key]=in_array($key,['locations','state_definitions','story_sections'],true)?$rows:[];return self::auditWorkspace($plot,$workspace);
    }

    private static function auditWorkspace(array $plot,array $workspace): array
    {
        $errors=[];$warnings=[];
        if(mb_strlen(trim((string)($plot['scenario_text']??'')))<80)$errors[]='The scenario briefing must contain at least 80 characters.';
        $activeCharacters=array_values(array_filter($workspace['characters'],static fn($x)=>(int)($x['active']??1)===1));$activeDecisions=array_values(array_filter($workspace['decisions'],static fn($x)=>(int)($x['active']??1)===1));
        if(!$activeCharacters)$errors[]='Add at least one active playable character.';
        foreach($activeCharacters as$c){$missing=[];foreach(['name','public_description','starting_location','goal_text','pronoun_set']as$f)if(trim((string)($c[$f]??''))==='')$missing[]=$f;if($missing)$errors[]=$c['role_code'].' is missing '.implode(', ',$missing).'.';}
        $endsWithComma=static fn(mixed$value): bool=>preg_match('/,\s*$/u',trim((string)$value))===1;
        foreach($workspace['locations']as$location)if((int)($location['active']??1)===1&&$endsWithComma($location['description']??''))$warnings[]=$location['location_code'].' description appears truncated at a comma.';
        foreach($workspace['events']as$event)if((int)($event['active']??1)===1&&$endsWithComma($event['event_text']??''))$warnings[]=$event['event_code'].' event_text appears truncated at a comma.';
        foreach($workspace['options']as$option)if((int)($option['active']??1)===1&&$endsWithComma($option['result_text']??''))$warnings[]=$option['option_code'].' result_text appears truncated at a comma.';
        foreach($workspace['resolutions']as$resolution)if((int)($resolution['active']??1)===1&&$endsWithComma($resolution['fragment_text']??''))$warnings[]=$resolution['section_code'].' fragment_text appears truncated at a comma.';
        [$integrityErrors,$integrityWarnings]=self::auditManagedReferences($workspace);$errors=array_merge($errors,$integrityErrors);$warnings=array_merge($warnings,$integrityWarnings);
        if(!$workspace['events'])$errors[]='Add a chronological world-event schedule.';
        if(!$activeDecisions)$errors[]='Add at least one active decision opportunity.';
        foreach($activeDecisions as$d)if((int)$d['option_count']<2)$errors[]=$d['event_code'].' / '.$d['role_code'].' needs at least two active options.';
        $eventCodes=array_fill_keys(array_column(array_filter($workspace['events'],static fn($x)=>(int)($x['active']??1)===1),'event_code'),true);$opportunityCodes=array_fill_keys(array_column(array_filter($workspace['opportunities'],static fn($x)=>(int)($x['active']??1)===1),'opportunity_code'),true);$roleCodes=array_fill_keys(array_column($activeCharacters,'role_code'),true);
        foreach($activeDecisions as$d){if(!isset($eventCodes[$d['event_code']])&&!isset($opportunityCodes[$d['event_code']]))$errors[]='Decision '.$d['event_code'].' references neither a world event nor a conditional opportunity.';if(!isset($roleCodes[$d['role_code']]))$errors[]='Active decision '.$d['event_code'].' references inactive or missing character '.$d['role_code'].'.';}
        foreach($workspace['options']as$o)if((int)($o['active']??1)===1&&trim((string)$o['result_text'])==='')$errors[]='Option '.$o['option_code'].' has no player-visible consequence.';
        foreach($workspace['opportunities']as$o){if((int)($o['active']??1)!==1)continue;if(!isset($roleCodes[$o['role_code']]))$errors[]='Active conditional opportunity '.$o['opportunity_code'].' references inactive or missing character '.$o['role_code'].'.';$choices=array_filter($workspace['dynamic_options'],static fn($x)=>(int)($x['active']??1)===1&&(int)$x['dynamic_opportunity_id']===(int)$o['dynamic_opportunity_id']);if(count($choices)<2)$errors[]='Conditional opportunity '.$o['opportunity_code'].' needs at least two active options.';}
        foreach($workspace['dynamic_conditions']as$c){if((int)($c['active']??1)!==1)continue;if(in_array($c['operator'],['EQ','NEQ','IN','NOT_IN','LT','LTE','GT','GTE'],true)&&trim((string)$c['compare_value'])==='')$errors[]='A condition on '.$c['opportunity_code'].' uses '.$c['operator'].' without a comparison value.';if(!in_array($c['source_type'],['LOCATION','GOAL','FEAR','PHASE','ACTION_COUNT','PLAYER_ACTION_COUNT'],true)&&trim((string)$c['source_key'])==='')$errors[]='A condition on '.$c['opportunity_code'].' needs a source key.';}
        foreach($workspace['resolution_conditions']as$c)if((int)($c['active']??1)===1&&trim((string)$c['state_key'])==='')$errors[]='An ending condition in '.$c['section_code'].' has no state key.';
        $playerProse=[];foreach($workspace['events']as$x)$playerProse[]=['event '.$x['event_code'],$x['event_text']];foreach($activeDecisions as$x)$playerProse[]=['decision '.$x['event_code'].'/'.$x['role_code'],$x['prompt_text']];foreach($workspace['options']as$x)if((int)($x['active']??1)===1)$playerProse[]=['option '.$x['option_code'],$x['result_text']];foreach($workspace['resolutions']as$x)$playerProse[]=['resolution '.$x['section_code'],$x['fragment_text']];foreach($playerProse as[$where,$text])if(preg_match('/(?:Final factors:|\b(?:NPC|PLAYER|NO_ACTION|WORLD_STATE|COURT_TRUST|LEGITIMACY|STABILITY|UNREST)\b|\d+\s*→\s*\d+)/u',(string)$text))$warnings[]=$where.' may expose engine language to players.';
        $words=0;$sections=[];foreach($workspace['resolutions']as$r)if((int)$r['active']===1){$words+=str_word_count(strip_tags((string)$r['fragment_text']));$sections[$r['section_code']]=true;}if($words<300||!isset($sections['AFTERMATH'])||!isset($sections['INVITATION']))$warnings[]='The authored ending needs at least 300 words plus AFTERMATH and INVITATION sections.';
        foreach($workspace['media']as$m)if((int)$m['active']===1&&trim((string)$m['alt_text'])==='')$errors[]='Active image '.$m['asset_code'].' needs alternative text.';
        if(!$workspace['media'])$warnings[]='No story artwork has been uploaded.';elseif(!array_filter($workspace['media'],static fn($m)=>(int)$m['active']===1&&$m['placement']==='PRIMARY'))$warnings[]='Add a Primary story image for public story cards and listings.';
        $lines=['TOINKY STORY REVIEW PACKAGE','Story: '.$plot['title'].' ('.$plot['code'].')','Author: '.($plot['author_name']??'Unassigned'),'Workflow: '.$plot['workflow_status'],'Genre: '.($plot['genre_label']??$plot['genre']),'','SCENARIO',trim((string)$plot['scenario_text']),'','AUTOMATED FINDINGS'];
        foreach($errors as$e)$lines[]='ERROR: '.$e;foreach($warnings as$w)$lines[]='WARNING: '.$w;if(!$errors&&!$warnings)$lines[]='PASS: Automated structural checks found no issue.';
        $lines[]='';$lines[]='CAST';foreach($activeCharacters as$c)$lines[]=$c['role_code'].' — '.$c['name'].'; '.$c['pronoun_set'].'; starts at '.$c['starting_location'].'; goal: '.$c['goal_text'].'; fear: '.($c['fear_text']?:'not authored').'; private knowledge: '.($c['private_knowledge']?:'none authored').'.';
        $lines[]='';$lines[]='MANAGED STORY VALUES';foreach($workspace['state_definitions']as$d)$lines[]=$d['effect_scope'].' '.$d['target_code'].' — '.$d['label_text'].'; type '.$d['value_type'].($d['allowed_values_text']?' ; allowed values '.$d['allowed_values_text']:'').($d['default_numeric_value']!==null?' ; default '.$d['default_numeric_value']:'').'.';
        $lines[]='';$lines[]='CHRONOLOGY AND DECISIONS';foreach($workspace['events']as$ev){$lines[]=$ev['event_second'].'s / same-time order '.$ev['sort_order'].' / '.$ev['phase'].' / '.$ev['event_code'].': '.$ev['headline'].' — '.$ev['event_text'];foreach($activeDecisions as$d)if($d['event_code']===$ev['event_code']){$lines[]='  '.$d['role_code'].' PROMPT ('.$d['decision_window_seconds'].'s window): '.$d['prompt_text'];foreach($workspace['options']as$o)if((int)($o['active']??1)===1&&(int)$o['template_id']===(int)$d['template_id']){$lines[]='    '.$o['option_code'].' [display order '.$o['sort_order'].', AI weight '.$o['npc_weight'].']: '.$o['label'].' => '.$o['result_text'];foreach($workspace['effects']as$x)if((int)($x['active']??1)===1&&(int)$x['template_option_id']===(int)$o['template_option_id'])$lines[]='      EFFECT '.$x['sort_order'].': '.$x['effect_scope'].' '.$x['target_code'].($x['delta_value']!==null?' changes by '.$x['delta_value']:' becomes '.$x['set_value']).($x['detail_text']?' — '.$x['detail_text']:'');foreach($workspace['option_conditions']as$xc)if((int)$xc['template_option_id']===(int)$o['template_option_id'])$lines[]='      CONDITION: '.$xc['state_key'].' '.$xc['operator'].($xc['compare_value']!==null?' '.$xc['compare_value']:'').($xc['reason_text']?' — '.$xc['reason_text']:'');}}}
        $lines[]='';$lines[]='DYNAMIC OPPORTUNITIES';foreach($workspace['opportunities']as$o){if((int)$o['active']!==1)continue;$lines[]=$o['role_code'].' / '.$o['opportunity_code'].' / ACTIVE / priority '.$o['priority'].' / '.$o['window_seconds'].'s: '.$o['prompt_text'];foreach($workspace['dynamic_conditions']as$c)if((int)($c['active']??1)===1&&(int)$c['dynamic_opportunity_id']===(int)$o['dynamic_opportunity_id'])$lines[]='  CONDITION '.$c['sort_order'].': '.$c['source_type'].' '.$c['source_key'].' '.$c['operator'].' '.$c['compare_value'];foreach($workspace['dynamic_options']as$x)if((int)($x['active']??1)===1&&(int)$x['dynamic_opportunity_id']===(int)$o['dynamic_opportunity_id']){$lines[]='  OPTION '.$x['sort_order'].' '.$x['option_code'].' [AI weight '.$x['npc_weight'].']: '.$x['label'].' => '.$x['result_text'].($x['rationale_text']?' | rationale: '.$x['rationale_text']:'');foreach($workspace['dynamic_effects']as$de)if((int)($de['active']??1)===1&&(int)$de['dynamic_option_id']===(int)$x['dynamic_option_id'])$lines[]='    EFFECT '.$de['sort_order'].': '.$de['effect_scope'].' '.$de['target_code'].($de['delta_value']!==null?' changes by '.$de['delta_value']:' becomes '.$de['set_value']);}}
        $lines[]='';$lines[]='ENDING FRAGMENTS';foreach($workspace['resolutions']as$r){$lines[]=$r['section_code'].' priority '.$r['priority'].': '.$r['fragment_text'];foreach($workspace['resolution_conditions']as$c)if((int)$c['resolution_fragment_id']===(int)$r['resolution_fragment_id'])$lines[]='  CONDITION '.$c['sort_order'].' ['.((int)($c['active']??1)===1?'ACTIVE':'INACTIVE').']: '.$c['state_key'].' '.$c['operator'].' '.$c['compare_value'];}
        $lines[]='';$lines[]='REVIEWER CRITIQUE REQUEST';$lines[]='Evaluate player immersion, viewpoint continuity, creative prose, character agency, branch causality, dependency gaps, unreachable outcomes, plot holes, pacing, replayability, mobile readability, and whether each character receives enough non-privileged information for meaningful choices. Base every recommendation on a named stable key and observed evidence. Prefer prose corrections. Do not recommend plot-specific PHP.';
        return['errors'=>$errors,'warnings'=>$warnings,'text'=>implode("\n",$lines),'workspace'=>$workspace];
    }

    private static function auditManagedReferences(array $workspace): array
    {
        $errors=[];$warnings=[];$definitions=[];$definitionsByCode=[];$locationValues=[];$phaseValues=[];
        foreach($workspace['state_definitions']??[]as$definition){if((int)($definition['active']??1)!==1)continue;$scope=strtoupper(trim((string)($definition['effect_scope']??'')));$code=self::code((string)($definition['target_code']??''));if($scope===''||$code==='')continue;$definitions[$scope.'|'.$code]=$definition;$definitionsByCode[$code][]=$definition;}
        foreach($workspace['locations']??[]as$location){if((int)($location['active']??1)!==1)continue;foreach(['label_text','location_code']as$field){$value=trim((string)($location[$field]??''));if($value!=='')$locationValues[$value]=true;}}
        foreach($workspace['events']??[]as$event){if((int)($event['active']??1)!==1)continue;$phase=trim((string)($event['phase']??''));if($phase!=='')$phaseValues[$phase]=true;}
        $checkCondition=static function(array$row,string$label,?array$definition=null)use(&$errors,$locationValues,$phaseValues):void{
            $source=strtoupper(trim((string)($row['source_type']??'')));$sourceKey=trim((string)($row['source_key']??''));$operator=strtoupper(trim((string)($row['operator']??'')));$compare=self::nullable($row['compare_value']??null);$opportunity=trim((string)($row['opportunity_code']??$row['section_code']??$label));
            if($source==='LOCATION'){if($sourceKey!=='')$errors[]=$label.' '.$opportunity.' uses LOCATION with an unexpected source key.';if($operator!=='EXISTS'&&$operator!=='NOT_EXISTS'){if($compare===null)$errors[]=$label.' '.$opportunity.' uses '.$operator.' without a comparison value.';else{foreach(in_array($operator,['IN','NOT_IN'],true)?array_map('trim',explode(',',$compare)):[$compare]as$value)if($value!==''&&!isset($locationValues[$value]))$errors[]=$label.' '.$opportunity.' compares LOCATION to unavailable value “'.$value.'”.';}}return;}
            if($source==='PHASE'){if($operator!=='EXISTS'&&$operator!=='NOT_EXISTS'){if($compare===null)$errors[]=$label.' '.$opportunity.' uses '.$operator.' without a comparison value.';else foreach(in_array($operator,['IN','NOT_IN'],true)?array_map('trim',explode(',',$compare)):[$compare]as$value)if($value!==''&&!isset($phaseValues[$value]))$errors[]=$label.' '.$opportunity.' compares PHASE to unavailable value “'.$value.'”.';}return;}
            if(in_array($source,['ACTION_COUNT','PLAYER_ACTION_COUNT','OPTION_CHOICE_COUNT'],true)){if(($problem=self::conditionValueProblem('NUMERIC','',$operator,$compare))!==null)$errors[]=$label.' '.$opportunity.' has an invalid '.$source.' condition: '.$problem;return;}
            if(!$definition){$errors[]=$label.' '.$opportunity.' references undefined Story Value '.$source.'|'.self::code($sourceKey).'.';return;}
            if(($problem=self::conditionValueProblem((string)$definition['value_type'],(string)($definition['allowed_values_text']??''),$operator,$compare))!==null)$errors[]=$label.' '.$opportunity.' has an invalid '.$source.'|'.self::code($sourceKey).' condition: '.$problem;
        };
        foreach($workspace['dynamic_conditions']??[]as$row){if((int)($row['active']??1)!==1)continue;$source=strtoupper(trim((string)($row['source_type']??'')));$key=self::code((string)($row['source_key']??''));$definition=$definitions[$source.'|'.$key]??null;$checkCondition($row,'Condition',$definition);}
        foreach($workspace['resolution_conditions']??[]as$row){if((int)($row['active']??1)!==1)continue;$key=self::code((string)($row['state_key']??''));$definition=$definitionsByCode[$key][0]??null;$checkCondition(['source_type'=>$definition['effect_scope']??'WORLD_STATE','source_key'=>$key,'operator'=>$row['operator']??'','compare_value'=>$row['compare_value']??null,'section_code'=>$row['section_code']??''],'Ending condition',$definition);if(!$definition)$errors[]='Ending condition '.($row['section_code']??'').' references undefined Story Value '.$key.'.';}
        $checkEffect=static function(array$row,string$label)use(&$errors,$definitions):void{$scope=strtoupper(trim((string)($row['effect_scope']??'')));$code=self::code((string)($row['target_code']??''));$key=$scope.'|'.$code;$definition=$definitions[$key]??null;$owner=trim((string)($row['event_code']??$row['opportunity_code']??$label)).'|'.trim((string)($row['option_code']??''));if(!$definition){$errors[]=$label.' '.$owner.' references undefined Story Value '.$key.'.';return;}$type=(string)$definition['value_type'];$delta=$row['delta_value']??null;$set=self::nullable($row['set_value']??null);if($type==='NUMERIC'){if($delta===null||$delta===''||!is_numeric((string)$delta))$errors[]=$label.' '.$owner.' must change Numerical Story Value '.$key.' with a numeric delta.';return;}if($set===null){$errors[]=$label.' '.$owner.' must set '.$type.' Story Value '.$key.' to a value.';return;}if($type==='PRESET'){$allowed=preg_split('/\r?\n/',trim((string)($definition['allowed_values_text']??'')))?:[];if(!in_array($set,$allowed,true))$errors[]=$label.' '.$owner.' sets '.$key.' to unavailable Preset Value “'.$set.'”.';}};
        foreach($workspace['effects']??[]as$row)if((int)($row['active']??1)===1)$checkEffect($row,'Option effect');foreach($workspace['dynamic_effects']??[]as$row)if((int)($row['active']??1)===1)$checkEffect($row,'Opportunity effect');
        $errors=array_values(array_unique($errors));$warnings=array_values(array_unique($warnings));return[$errors,$warnings];
    }

    public static function submit(array $user,int $plotId): int
    {
        self::guard($user,$plotId);$audit=self::audit($plotId);if($audit['errors'])throw new RuntimeException('Resolve the blocking validation errors before submission.');$pdo=db();$snapshot=json_encode(['plot'=>self::story($plotId),'content'=>$audit['workspace']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$q=$pdo->prepare('SELECT COALESCE(MAX(version_no),0)+1 FROM plot_versions WHERE plot_id=?');$q->execute([$plotId]);$version=(int)$q->fetchColumn();$pdo->beginTransaction();try{$pdo->prepare('INSERT INTO plot_versions(plot_id,version_no,snapshot_json,submitted_by_user_id) VALUES(?,?,?,?)')->execute([$plotId,$version,$snapshot,$user['user_id']]);$id=(int)$pdo->lastInsertId();$pdo->prepare('UPDATE plots SET workflow_status="SUBMITTED" WHERE plot_id=?')->execute([$plotId]);$pdo->commit();return$id;}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }

    public static function saveAudit(int $plotId,int $userId,?int $versionId=null): array
    {
        $a=$versionId?self::auditVersion($versionId):self::audit($plotId);db()->prepare('INSERT INTO story_audit_runs(plot_id,plot_version_id,requested_by_user_id,audit_text,blocking_error_count,warning_count) VALUES(?,?,?,?,?,?)')->execute([$plotId,$versionId,$userId,$a['text'],count($a['errors']),count($a['warnings'])]);return$a;
    }

    private static function guard(array $user,int $plotId): void{if(!self::canEdit($user,$plotId))throw new RuntimeException('You may only edit stories you own.');}
    private static function code(string $value):string{return trim(strtoupper((string)preg_replace('/[^A-Za-z0-9]+/','_',trim($value))),'_');}
    private static function nullable($value):?string{$value=trim((string)$value);return$value===''?null:$value;}
    private static function integerOrNull($value):?int{$value=trim((string)$value);return$value===''?null:(int)$value;}
    public static function durationChoices(bool $isAdmin,int $current): array
    {
        $choices=[1800=>'30 Minutes',3600=>'1 Hour',5400=>'1.5 Hours',7200=>'2 Hours',9000=>'2.5 Hours',10800=>'3 Hours'];
        if($isAdmin)$choices=[300=>'5 Minutes',600=>'10 Minutes',900=>'15 Minutes']+$choices;
        if($current>0&&!isset($choices[$current]))$choices[$current]=self::durationLabel($current);
        ksort($choices);return $choices;
    }

    public static function durationLabel(int $seconds): string
    {
        if($seconds>=3600&&$seconds%1800===0)return rtrim(rtrim(number_format($seconds/3600,1),'0'),'.').($seconds===3600?' Hour':' Hours');
        if($seconds%60===0)return intdiv($seconds,60).' Minutes';
        return $seconds.' Seconds';
    }

    public static function hostDurationOptions(bool $includeAdminOnly=true): array
    {
        $options=[];$q=db()->query('SELECT duration_seconds,label_text FROM platform_game_durations WHERE active=1 ORDER BY sort_order,duration_seconds');foreach($q->fetchAll()as$row)$options[(int)$row['duration_seconds']]=(string)$row['label_text'];if(!$includeAdminOnly)foreach(array_keys($options)as$seconds)if($seconds<1800)unset($options[$seconds]);return $options;
    }

    public static function allowedHostDurations(int $plotId): array
    {
        $options=self::hostDurationOptions();$q=db()->prepare('SELECT duration_seconds FROM plot_allowed_durations WHERE plot_id=?');$q->execute([$plotId]);$saved=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));if(!$saved)return array_keys($options);$allowed=[];foreach(array_keys($options)as$seconds)if(in_array($seconds,$saved,true))$allowed[]=$seconds;return $allowed!==[]?$allowed:array_keys($options);
    }

    private static function saveAllowedHostDurations(int $plotId,array $input,int $timelineDuration): void
    {
        if(empty($input['allowed_host_durations_present']))return;
        $options=self::hostDurationOptions();$posted=$input['allowed_host_durations']??[];if(!is_array($posted))$posted=[$posted];
        $set=[];foreach($posted as$value){$seconds=(int)$value;if(isset($options[$seconds]))$set[$seconds]=true;}
        if(isset($options[$timelineDuration]))$set[$timelineDuration]=true;
        if($set===[])throw new RuntimeException('Choose at least one host duration.');
        db()->prepare('DELETE FROM plot_allowed_durations WHERE plot_id=?')->execute([$plotId]);
        $ins=db()->prepare('INSERT INTO plot_allowed_durations(plot_id,duration_seconds) VALUES(?,?)');foreach(array_keys($set)as$seconds)$ins->execute([$plotId,$seconds]);
    }

    private static function overviewDuration(array $user,array $input,int $current): int
    {
        if(!isset($input['timeline_duration_seconds'])||$input['timeline_duration_seconds']==='')return $current;
        $posted=(int)$input['timeline_duration_seconds'];$allowed=array_keys(self::durationChoices((int)($user['is_admin']??0)===1,$current));
        if(!in_array($posted,$allowed,true))throw new RuntimeException('That game duration is not available.');
        return $posted;
    }

    private static function durationSeconds(array $input,string $valueKey,string $unitKey,string $legacyKey,int $default):int{$raw=trim((string)($input[$valueKey]??''));if($raw==='')return max(0,(int)($input[$legacyKey]??$default));$units=['seconds'=>1,'minutes'=>60,'hours'=>3600,'days'=>86400];$multiplier=$units[strtolower((string)($input[$unitKey]??'seconds'))]??1;return max(0,(int)round((float)$raw*$multiplier));}
    private static function normalizeAllowedValues(string $value):string{$parts=preg_split('/[\r\n,]+/',$value)?:[];$parts=array_values(array_unique(array_filter(array_map(static fn($x)=>trim((string)$x),$parts),static fn($x)=>$x!=='')));return implode("\n",$parts);}
    private static function assertStableCodeChange(int$plotId,string$entity,int$id,string$current,string$next):void{if($current===$next)return;$usage=self::stableCodeUsageMap($plotId)[$entity][$id]??[];if(!$usage)return;$keywords=[];foreach($usage as$keyword=>$count)$keywords[]=$keyword.' '.$count;throw new RuntimeException('Stable code is locked · '.implode(' · ',$keywords));}
    private static function assertStoryCodeChange(int$plotId,string$current,string$next):void{if($current===$next)return;$usage=self::storyCodeUsage($plotId);if(!$usage)return;$keywords=[];foreach($usage as$keyword=>$count)$keywords[]=$keyword.' '.$count;throw new RuntimeException('Stable Story Code is locked · '.implode(' · ',$keywords));}
    private static function validateManagedValue(int $plotId,?string $scope,string $code,?int $delta,?string $set,bool $requireEffect=true):void{$sql='SELECT * FROM plot_state_definitions WHERE plot_id=? AND target_code=?'.($scope!==null?' AND effect_scope=?':'').' LIMIT 1';$q=db()->prepare($sql);$args=[$plotId,$code];if($scope!==null)$args[]=$scope;$q->execute($args);$definition=$q->fetch();if(!$definition)throw new RuntimeException('Choose a story value managed in Story Framework; '.$code.' is not defined for '.($scope?:'this story').'.');if(!$requireEffect)return;$usesDelta=$definition['value_type']==='NUMERIC';if($usesDelta&&$delta===null)throw new RuntimeException($definition['label_text'].' needs a numerical Change By value.');if(!$usesDelta&&$set===null)throw new RuntimeException($definition['label_text'].' needs a '.($definition['value_type']==='TEXT'?'Free Text Value':'Set To value').'.');if($definition['value_type']==='PRESET'){$allowed=preg_split('/\r?\n/',trim((string)$definition['allowed_values_text']))?:[];if(!in_array((string)$set,$allowed,true))throw new RuntimeException('Choose an allowed value for '.$definition['label_text'].'.');}}
    private static function validateManagedCondition(int$plotId,?string$scope,string$code,string$operator,?string$compare):void{$sql='SELECT * FROM plot_state_definitions WHERE plot_id=? AND target_code=?'.($scope!==null?' AND effect_scope=?':'').' LIMIT 1';$q=db()->prepare($sql);$args=[$plotId,$code];if($scope!==null)$args[]=$scope;$q->execute($args);$definition=$q->fetch();if(!$definition)throw new RuntimeException('Choose a story value managed in Story Framework; '.$code.' is not defined.');$problem=self::conditionValueProblem((string)$definition['value_type'],(string)($definition['allowed_values_text']??''),$operator,$compare);if($problem!==null)throw new RuntimeException($definition['label_text'].' condition is invalid: '.$problem);}
    private static function conditionValueProblem(string$type,string$allowedText,string$operator,?string$compare):?string{if(in_array($operator,['EXISTS','NOT_EXISTS'],true))return null;if($compare===null||trim($compare)==='')return $operator.' needs a comparison value.';$parts=in_array($operator,['IN','NOT_IN'],true)?array_values(array_filter(array_map('trim',explode(',',$compare)),static fn($value)=>$value!=='')):[trim($compare)];if($type==='NUMERIC'){foreach($parts as$value)if(!is_numeric($value))return 'use only numerical values for '.$operator.'.';return null;}if(in_array($operator,['LT','LTE','GT','GTE'],true))return $operator.' is only available for Numerical Story Values.';if($type==='PRESET'){$allowed=preg_split('/\r?\n/',trim($allowedText))?:[];foreach($parts as$value)if(!in_array($value,$allowed,true))return '“'.$value.'” is not an allowed Preset Value.';}return null;}
    private static function assertStateDefinitionCompatibility(int$plotId,string$scope,string$code,string$type,?string$allowed,string$label):void{$w=self::workspace($plotId);$problems=[];$effectProblem=static function(array$row,string$key)use($type,$allowed,&$problems):void{if($type==='NUMERIC'&&$row['delta_value']===null)$problems[]=$key.' needs Change By';elseif($type!=='NUMERIC'&&$row['set_value']===null)$problems[]=$key.' needs Set To';elseif($type==='PRESET'&&!in_array((string)$row['set_value'],preg_split('/\r?\n/',trim((string)$allowed))?:[],true))$problems[]=$key.' has “'.$row['set_value'].'”';};foreach($w['effects']as$row)if((int)($row['active']??1)===1&&$row['effect_scope']===$scope&&$row['target_code']===$code)$effectProblem($row,'OPTION '.$row['event_code'].'|'.$row['role_code'].'|'.$row['option_code']);foreach($w['dynamic_effects']as$row)if((int)($row['active']??1)===1&&$row['effect_scope']===$scope&&$row['target_code']===$code)$effectProblem($row,'OPTION '.$row['opportunity_code'].'|'.$row['option_code']);foreach($w['dynamic_conditions']as$row)if((int)($row['active']??1)===1&&$row['source_type']===$scope&&$row['source_key']===$code){$problem=self::conditionValueProblem($type,(string)$allowed,(string)$row['operator'],$row['compare_value']);if($problem!==null)$problems[]='CONDITION '.$row['opportunity_code'].' ('.$problem.')';}foreach($w['resolution_conditions']as$row)if((int)($row['active']??1)===1&&$row['state_key']===$code){$problem=self::conditionValueProblem($type,(string)$allowed,(string)$row['operator'],$row['compare_value']);if($problem!==null)$problems[]='ENDING '.$row['section_code'].' ('.$problem.')';}if($problems)throw new RuntimeException('Cannot change '.$label.' to '.$type.'. Update these uses first: '.implode(' · ',$problems));}
    private static function assertParent(string $table,string $key,int $id,int $plotId):void{$q=db()->prepare('SELECT 1 FROM '.$table.' WHERE '.$key.'=? AND plot_id=?');$q->execute([$id,$plotId]);if(!$q->fetchColumn())throw new RuntimeException('The selected parent record does not belong to this story.');}
}
