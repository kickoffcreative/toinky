<?php
require_once __DIR__.'/StoryMediaService.php';

final class StoryAuthoringService
{
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
        $title=trim((string)($input['title']??''));$code=self::code((string)($input['code']??$title));
        if($title===''||$code==='')throw new RuntimeException('Enter a story title and code.');
        $genreId=(int)($input['genre_id']??0);$q=db()->prepare('SELECT label_text FROM story_genres WHERE genre_id=? AND active=1');$q->execute([$genreId]);$genre=(string)$q->fetchColumn();if($genre==='')throw new RuntimeException('Choose a genre.');
        $q=db()->prepare('INSERT INTO plots(code,title,archetype,genre,genre_id,setting_name,max_players,max_npcs,status,workflow_status,author_user_id,scenario_text) VALUES(?,?,?,?,?,?,?,?,"draft","DRAFT",?,?)');
        $q->execute([$code,$title,trim((string)($input['archetype']??'Interactive Drama')),$genre,$genreId,trim((string)($input['setting_name']??'Unspecified setting')),max(1,(int)($input['max_players']??3)),max(0,(int)($input['max_npcs']??3)),(int)$user['user_id'],trim((string)($input['scenario_text']??''))]);
        return(int)db()->lastInsertId();
    }

    public static function updateOverview(array $user,int $plotId,array $input): void
    {
        self::guard($user,$plotId);$genreId=(int)($input['genre_id']??0);$q=db()->prepare('SELECT label_text FROM story_genres WHERE genre_id=? AND active=1');$q->execute([$genreId]);$genre=(string)$q->fetchColumn();if($genre==='')throw new RuntimeException('Choose a genre.');
        $title=trim((string)($input['title']??''));if($title==='')throw new RuntimeException('The story needs a title.');
        db()->prepare('UPDATE plots SET title=?,archetype=?,genre=?,genre_id=?,setting_name=?,max_players=?,max_npcs=?,scenario_text=? WHERE plot_id=?')->execute([$title,trim((string)$input['archetype']),$genre,$genreId,trim((string)$input['setting_name']),max(1,(int)$input['max_players']),max(0,(int)$input['max_npcs']),trim((string)$input['scenario_text']),$plotId]);
    }

    public static function saveCharacter(array $user,int $plotId,array $input): int
    {
        self::guard($user,$plotId);$id=(int)($input['plot_role_id']??0);$roleCode=self::code((string)($input['role_code']??''));$name=trim((string)($input['name']??''));
        if($roleCode===''||$name==='')throw new RuntimeException('Every character needs a name and role code.');
        $values=[$roleCode,in_array(($input['role_type']??''),['PLAYER','NPC'],true)?$input['role_type']:'PLAYER',max(1,(int)($input['slot_no']??1)),$name,trim((string)($input['public_description']??'')),self::nullable($input['private_knowledge']??null),trim((string)($input['starting_location']??'')),trim((string)($input['goal_text']??'')),self::nullable($input['fear_text']??null),in_array(($input['pronoun_set']??''),['HE','SHE','IT'],true)?$input['pronoun_set']:'IT',(int)!empty($input['simulation_active'])];
        if($id){$q=db()->prepare('UPDATE plot_roles SET role_code=?,role_type=?,slot_no=?,name=?,public_description=?,private_knowledge=?,starting_location=?,goal_text=?,fear_text=?,pronoun_set=?,simulation_active=? WHERE plot_role_id=? AND plot_id=?');$q->execute(array_merge($values,[$id,$plotId]));return$id;}
        $q=db()->prepare('INSERT INTO plot_roles(plot_id,role_code,role_type,slot_no,name,public_description,private_knowledge,starting_location,goal_text,fear_text,pronoun_set,simulation_active) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');$q->execute(array_merge([$plotId],$values));return(int)db()->lastInsertId();
    }

    public static function saveWorldEvent(array $user,int $plotId,array $input): int
    {
        self::guard($user,$plotId);$id=(int)($input['plot_world_event_id']??0);$code=self::code((string)($input['event_code']??''));if($code==='')throw new RuntimeException('Every event needs an event code.');
        $values=[max(0,(int)($input['event_second']??0)),in_array(($input['phase']??''),['EXPOSITION','INCITING_INCIDENT','RISING_ACTION','CLIMAX','RESOLUTION'],true)?$input['phase']:'EXPOSITION',$code,trim((string)($input['headline']??'')),trim((string)($input['event_text']??'')),(int)($input['sort_order']??0),(int)!empty($input['active'])];
        if($id){$q=db()->prepare('UPDATE plot_world_events SET event_second=?,phase=?,event_code=?,headline=?,event_text=?,sort_order=?,active=? WHERE plot_world_event_id=? AND plot_id=?');$q->execute(array_merge($values,[$id,$plotId]));return$id;}
        $q=db()->prepare('INSERT INTO plot_world_events(plot_id,event_second,phase,event_code,headline,event_text,sort_order,active) VALUES(?,?,?,?,?,?,?,?)');$q->execute(array_merge([$plotId],$values));return(int)db()->lastInsertId();
    }

    public static function saveDecision(array $user,int $plotId,array $input): int
    {
        self::guard($user,$plotId);$id=(int)($input['template_id']??0);$values=[self::code((string)$input['event_code']),self::code((string)$input['role_code']),in_array(($input['actor_type']??''),['PLAYER','NPC'],true)?$input['actor_type']:'PLAYER',trim((string)$input['prompt_text']),max(5,(int)($input['decision_window_seconds']??30))];
        if(!$values[0]||!$values[1]||!$values[3])throw new RuntimeException('A decision needs an event, character, and prompt.');
        if($id){$q=db()->prepare('UPDATE plot_decision_templates SET event_code=?,role_code=?,actor_type=?,prompt_text=?,decision_window_seconds=? WHERE template_id=? AND plot_id=?');$q->execute(array_merge($values,[$id,$plotId]));return$id;}
        $q=db()->prepare('INSERT INTO plot_decision_templates(plot_id,event_code,role_code,actor_type,prompt_text,decision_window_seconds) VALUES(?,?,?,?,?,?)');$q->execute(array_merge([$plotId],$values));return(int)db()->lastInsertId();
    }

    public static function saveOption(array $user,int $plotId,array $input): int
    {
        self::guard($user,$plotId);$templateId=(int)$input['template_id'];self::assertParent('plot_decision_templates','template_id',$templateId,$plotId);
        $id=(int)($input['template_option_id']??0);$values=[self::code((string)$input['option_code']),trim((string)$input['label']),(int)($input['npc_weight']??0),self::nullable($input['rationale_text']??null),trim((string)$input['result_text']),(int)($input['sort_order']??0)];
        if(!$values[0]||!$values[1]||!$values[4])throw new RuntimeException('An option needs a code, label, and result.');
        if($id){$q=db()->prepare('UPDATE plot_decision_template_options o JOIN plot_decision_templates t ON t.template_id=o.template_id SET o.option_code=?,o.label=?,o.npc_weight=?,o.rationale_text=?,o.result_text=?,o.sort_order=? WHERE o.template_option_id=? AND t.plot_id=?');$q->execute(array_merge($values,[$id,$plotId]));return$id;}
        $q=db()->prepare('INSERT INTO plot_decision_template_options(template_id,option_code,label,npc_weight,rationale_text,result_text,sort_order) VALUES(?,?,?,?,?,?,?)');$q->execute(array_merge([$templateId],$values));return(int)db()->lastInsertId();
    }

    public static function saveOptionEffect(array $user,int $plotId,array $input): int
    {
        self::guard($user,$plotId);$optionId=(int)$input['template_option_id'];$q=db()->prepare('SELECT 1 FROM plot_decision_template_options o JOIN plot_decision_templates t ON t.template_id=o.template_id WHERE o.template_option_id=? AND t.plot_id=?');$q->execute([$optionId,$plotId]);if(!$q->fetchColumn())throw new RuntimeException('The option does not belong to this story.');
        $id=(int)($input['effect_id']??0);$scope=in_array(($input['effect_scope']??''),['FACTOR','STAT','LOCATION','KNOWLEDGE','RELATIONSHIP','WORLD_STATE','GOAL','FEAR'],true)?$input['effect_scope']:'WORLD_STATE';$values=[$scope,self::code((string)$input['target_code']),self::integerOrNull($input['delta_value']??null),self::nullable($input['set_value']??null),self::nullable($input['detail_text']??null),(int)($input['sort_order']??0)];if($values[1]==='')throw new RuntimeException('An effect needs a target code.');
        if($id){$q=db()->prepare('UPDATE plot_option_effects e JOIN plot_decision_template_options o ON o.template_option_id=e.template_option_id JOIN plot_decision_templates t ON t.template_id=o.template_id SET e.effect_scope=?,e.target_code=?,e.delta_value=?,e.set_value=?,e.detail_text=?,e.sort_order=? WHERE e.effect_id=? AND t.plot_id=?');$q->execute(array_merge($values,[$id,$plotId]));return$id;}
        $q=db()->prepare('INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES(?,?,?,?,?,?,?)');$q->execute(array_merge([$optionId],$values));return(int)db()->lastInsertId();
    }

    public static function saveDynamicOpportunity(array $user,int $plotId,array $input): int
    {
        self::guard($user,$plotId);$id=(int)($input['dynamic_opportunity_id']??0);$values=[self::code((string)$input['opportunity_code']),self::code((string)$input['role_code']),trim((string)$input['prompt_text']),max(5,(int)($input['window_seconds']??35)),(int)!empty($input['once_only']),(int)($input['priority']??100),(int)!empty($input['active'])];if(!$values[0]||!$values[1]||!$values[2])throw new RuntimeException('A conditional opportunity needs a code, character, and prompt.');
        if($id){$q=db()->prepare('UPDATE plot_dynamic_opportunities SET opportunity_code=?,role_code=?,prompt_text=?,window_seconds=?,once_only=?,priority=?,active=? WHERE dynamic_opportunity_id=? AND plot_id=?');$q->execute(array_merge($values,[$id,$plotId]));return$id;}
        $q=db()->prepare('INSERT INTO plot_dynamic_opportunities(plot_id,opportunity_code,role_code,prompt_text,window_seconds,once_only,priority,active) VALUES(?,?,?,?,?,?,?,?)');$q->execute(array_merge([$plotId],$values));return(int)db()->lastInsertId();
    }

    public static function saveDynamicCondition(array $user,int $plotId,array $input): int
    {
        self::guard($user,$plotId);$parent=(int)$input['dynamic_opportunity_id'];self::assertParent('plot_dynamic_opportunities','dynamic_opportunity_id',$parent,$plotId);$id=(int)($input['dynamic_condition_id']??0);$types=['WORLD_STATE','FACTOR','LOCATION','GOAL','FEAR','STAT','KNOWLEDGE','RELATIONSHIP','PHASE','ACTION_COUNT','PLAYER_ACTION_COUNT','OPTION_CHOICE_COUNT'];$operators=['EQ','NEQ','IN','NOT_IN','EXISTS','NOT_EXISTS','LT','LTE','GT','GTE'];$values=[in_array(($input['source_type']??''),$types,true)?$input['source_type']:'WORLD_STATE',self::nullable($input['source_key']??null),in_array(($input['operator']??''),$operators,true)?$input['operator']:'EQ',self::nullable($input['compare_value']??null),(int)($input['sort_order']??0)];
        if($id){$q=db()->prepare('UPDATE plot_dynamic_opportunity_conditions c JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=c.dynamic_opportunity_id SET c.source_type=?,c.source_key=?,c.operator=?,c.compare_value=?,c.sort_order=? WHERE c.dynamic_condition_id=? AND o.plot_id=?');$q->execute(array_merge($values,[$id,$plotId]));return$id;}
        $q=db()->prepare('INSERT INTO plot_dynamic_opportunity_conditions(dynamic_opportunity_id,source_type,source_key,operator,compare_value,sort_order) VALUES(?,?,?,?,?,?)');$q->execute(array_merge([$parent],$values));return(int)db()->lastInsertId();
    }

    public static function saveDynamicOption(array $user,int $plotId,array $input): int
    {
        self::guard($user,$plotId);$parent=(int)$input['dynamic_opportunity_id'];self::assertParent('plot_dynamic_opportunities','dynamic_opportunity_id',$parent,$plotId);$id=(int)($input['dynamic_option_id']??0);$values=[self::code((string)$input['option_code']),trim((string)$input['label']),trim((string)$input['result_text']),(int)($input['sort_order']??0)];if(!$values[0]||!$values[1]||!$values[2])throw new RuntimeException('A conditional option needs a code, label, and result.');
        if($id){$q=db()->prepare('UPDATE plot_dynamic_opportunity_options x JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=x.dynamic_opportunity_id SET x.option_code=?,x.label=?,x.result_text=?,x.sort_order=? WHERE x.dynamic_option_id=? AND o.plot_id=?');$q->execute(array_merge($values,[$id,$plotId]));return$id;}
        $q=db()->prepare('INSERT INTO plot_dynamic_opportunity_options(dynamic_opportunity_id,option_code,label,result_text,sort_order) VALUES(?,?,?,?,?)');$q->execute(array_merge([$parent],$values));return(int)db()->lastInsertId();
    }

    public static function saveDynamicEffect(array $user,int $plotId,array $input): int
    {
        self::guard($user,$plotId);$option=(int)$input['dynamic_option_id'];$q=db()->prepare('SELECT 1 FROM plot_dynamic_opportunity_options x JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=x.dynamic_opportunity_id WHERE x.dynamic_option_id=? AND o.plot_id=?');$q->execute([$option,$plotId]);if(!$q->fetchColumn())throw new RuntimeException('The conditional option does not belong to this story.');$id=(int)($input['dynamic_effect_id']??0);$scopes=['FACTOR','STAT','LOCATION','KNOWLEDGE','RELATIONSHIP','WORLD_STATE','GOAL','FEAR'];$values=[in_array(($input['effect_scope']??''),$scopes,true)?$input['effect_scope']:'WORLD_STATE',self::code((string)$input['target_code']),self::integerOrNull($input['delta_value']??null),self::nullable($input['set_value']??null),self::nullable($input['detail_text']??null),(int)($input['sort_order']??0)];if(!$values[1])throw new RuntimeException('A conditional effect needs a target code.');
        if($id){$q=db()->prepare('UPDATE plot_dynamic_option_effects e JOIN plot_dynamic_opportunity_options x ON x.dynamic_option_id=e.dynamic_option_id JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=x.dynamic_opportunity_id SET e.effect_scope=?,e.target_code=?,e.delta_value=?,e.set_value=?,e.detail_text=?,e.sort_order=? WHERE e.dynamic_effect_id=? AND o.plot_id=?');$q->execute(array_merge($values,[$id,$plotId]));return$id;}
        $q=db()->prepare('INSERT INTO plot_dynamic_option_effects(dynamic_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES(?,?,?,?,?,?,?)');$q->execute(array_merge([$option],$values));return(int)db()->lastInsertId();
    }

    public static function saveResolution(array $user,int $plotId,array $input): int
    {
        self::guard($user,$plotId);$id=(int)($input['resolution_fragment_id']??0);$values=[self::code((string)($input['section_code']??'AFTERMATH')),(int)($input['section_order']??80),(int)($input['priority']??100),trim((string)$input['fragment_text']),(int)!empty($input['active'])];if($values[3]==='')throw new RuntimeException('Resolution prose cannot be empty.');
        if($id){$q=db()->prepare('UPDATE plot_resolution_fragments SET section_code=?,section_order=?,priority=?,fragment_text=?,active=? WHERE resolution_fragment_id=? AND plot_id=?');$q->execute(array_merge($values,[$id,$plotId]));return$id;}
        $q=db()->prepare('INSERT INTO plot_resolution_fragments(plot_id,section_code,section_order,priority,fragment_text,active) VALUES(?,?,?,?,?,?)');$q->execute(array_merge([$plotId],$values));return(int)db()->lastInsertId();
    }

    public static function saveResolutionCondition(array $user,int $plotId,array $input): int
    {
        self::guard($user,$plotId);$fragment=(int)$input['resolution_fragment_id'];self::assertParent('plot_resolution_fragments','resolution_fragment_id',$fragment,$plotId);$id=(int)($input['resolution_condition_id']??0);$operators=['EQ','NEQ','IN','NOT_IN','EXISTS','NOT_EXISTS','LT','LTE','GT','GTE'];$values=[self::code((string)$input['state_key']),in_array(($input['operator']??''),$operators,true)?$input['operator']:'EQ',self::nullable($input['compare_value']??null),(int)($input['sort_order']??0)];if(!$values[0])throw new RuntimeException('An ending condition needs a state key.');
        if($id){$q=db()->prepare('UPDATE plot_resolution_conditions c JOIN plot_resolution_fragments f ON f.resolution_fragment_id=c.resolution_fragment_id SET c.state_key=?,c.operator=?,c.compare_value=?,c.sort_order=? WHERE c.resolution_condition_id=? AND f.plot_id=?');$q->execute(array_merge($values,[$id,$plotId]));return$id;}
        $q=db()->prepare('INSERT INTO plot_resolution_conditions(resolution_fragment_id,state_key,operator,compare_value,sort_order) VALUES(?,?,?,?,?)');$q->execute(array_merge([$fragment],$values));return(int)db()->lastInsertId();
    }

    public static function story(int $plotId): array
    {
        $q=db()->prepare('SELECT p.*,u.display_name author_name,u.email author_email,g.label_text genre_label FROM plots p LEFT JOIN users u ON u.user_id=p.author_user_id LEFT JOIN story_genres g ON g.genre_id=p.genre_id WHERE p.plot_id=?');$q->execute([$plotId]);$plot=$q->fetch();if(!$plot)throw new RuntimeException('Story not found.');return$plot;
    }

    public static function workspace(int $plotId): array
    {
        $tables=[
            'characters'=>['SELECT * FROM plot_roles WHERE plot_id=? ORDER BY slot_no,plot_role_id',[$plotId]],
            'events'=>['SELECT * FROM plot_world_events WHERE plot_id=? ORDER BY event_second,sort_order,plot_world_event_id',[$plotId]],
            'decisions'=>['SELECT t.*,COUNT(o.template_option_id) option_count FROM plot_decision_templates t LEFT JOIN plot_decision_template_options o ON o.template_id=t.template_id WHERE t.plot_id=? GROUP BY t.template_id ORDER BY t.event_code,t.role_code',[$plotId]],
            'options'=>['SELECT o.*,t.event_code,t.role_code FROM plot_decision_template_options o JOIN plot_decision_templates t ON t.template_id=o.template_id WHERE t.plot_id=? ORDER BY t.event_code,t.role_code,o.sort_order',[$plotId]],
            'effects'=>['SELECT e.*,o.option_code,t.event_code,t.role_code FROM plot_option_effects e JOIN plot_decision_template_options o ON o.template_option_id=e.template_option_id JOIN plot_decision_templates t ON t.template_id=o.template_id WHERE t.plot_id=? ORDER BY t.event_code,t.role_code,o.sort_order,e.sort_order',[$plotId]],
            'opportunities'=>['SELECT * FROM plot_dynamic_opportunities WHERE plot_id=? ORDER BY priority,opportunity_code',[$plotId]],
            'dynamic_conditions'=>['SELECT c.*,o.opportunity_code,o.role_code FROM plot_dynamic_opportunity_conditions c JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=c.dynamic_opportunity_id WHERE o.plot_id=? ORDER BY o.priority,c.sort_order',[$plotId]],
            'dynamic_options'=>['SELECT x.*,o.opportunity_code,o.role_code FROM plot_dynamic_opportunity_options x JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=x.dynamic_opportunity_id WHERE o.plot_id=? ORDER BY o.priority,x.sort_order',[$plotId]],
            'dynamic_effects'=>['SELECT e.*,x.option_code,o.opportunity_code,o.role_code FROM plot_dynamic_option_effects e JOIN plot_dynamic_opportunity_options x ON x.dynamic_option_id=e.dynamic_option_id JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=x.dynamic_opportunity_id WHERE o.plot_id=? ORDER BY o.priority,x.sort_order,e.sort_order',[$plotId]],
            'resolutions'=>['SELECT * FROM plot_resolution_fragments WHERE plot_id=? ORDER BY section_order,priority,resolution_fragment_id',[$plotId]],
            'resolution_conditions'=>['SELECT c.*,f.section_code FROM plot_resolution_conditions c JOIN plot_resolution_fragments f ON f.resolution_fragment_id=c.resolution_fragment_id WHERE f.plot_id=? ORDER BY f.section_order,f.priority,c.sort_order',[$plotId]],
            'media'=>['SELECT * FROM plot_media_assets WHERE plot_id=? ORDER BY placement,sort_order',[$plotId]]
        ];$out=[];foreach($tables as$key=>$spec){$q=db()->prepare($spec[0]);$q->execute($spec[1]);$out[$key]=$q->fetchAll();}return$out;
    }

    public static function audit(int $plotId): array
    {
        $plot=self::story($plotId);$workspace=self::workspace($plotId);$errors=[];$warnings=[];
        if(mb_strlen(trim((string)($plot['scenario_text']??'')))<80)$errors[]='The scenario briefing must contain at least 80 characters.';
        if(!$workspace['characters'])$errors[]='Add at least one playable character.';
        foreach($workspace['characters']as$c){$missing=[];foreach(['name','public_description','starting_location','goal_text','pronoun_set']as$f)if(trim((string)($c[$f]??''))==='')$missing[]=$f;if($missing)$errors[]=$c['role_code'].' is missing '.implode(', ',$missing).'.';}
        if(!$workspace['events'])$errors[]='Add a chronological world-event schedule.';
        if(!$workspace['decisions'])$errors[]='Add at least one decision opportunity.';
        foreach($workspace['decisions']as$d)if((int)$d['option_count']<2)$errors[]=$d['event_code'].' / '.$d['role_code'].' needs at least two options.';
        $eventCodes=array_fill_keys(array_column($workspace['events'],'event_code'),true);$roleCodes=array_fill_keys(array_column($workspace['characters'],'role_code'),true);
        foreach($workspace['decisions']as$d){if(!isset($eventCodes[$d['event_code']]))$errors[]='Decision '.$d['event_code'].' references a missing world event.';if(!isset($roleCodes[$d['role_code']]))$errors[]='Decision '.$d['event_code'].' references missing character '.$d['role_code'].'.';}
        foreach($workspace['options']as$o)if(trim((string)$o['result_text'])==='')$errors[]='Option '.$o['option_code'].' has no player-visible consequence.';
        foreach($workspace['opportunities']as$o){if(!isset($roleCodes[$o['role_code']]))$errors[]='Conditional opportunity '.$o['opportunity_code'].' references missing character '.$o['role_code'].'.';$choices=array_filter($workspace['dynamic_options'],static fn($x)=>(int)$x['dynamic_opportunity_id']===(int)$o['dynamic_opportunity_id']);if(count($choices)<2)$errors[]='Conditional opportunity '.$o['opportunity_code'].' needs at least two options.';}
        foreach($workspace['dynamic_conditions']as$c){if(in_array($c['operator'],['EQ','NEQ','IN','NOT_IN','LT','LTE','GT','GTE'],true)&&trim((string)$c['compare_value'])==='')$errors[]='A condition on '.$c['opportunity_code'].' uses '.$c['operator'].' without a comparison value.';if(!in_array($c['source_type'],['PHASE','ACTION_COUNT','PLAYER_ACTION_COUNT'],true)&&trim((string)$c['source_key'])==='')$errors[]='A condition on '.$c['opportunity_code'].' needs a source key.';}
        foreach($workspace['resolution_conditions']as$c)if(trim((string)$c['state_key'])==='')$errors[]='An ending condition in '.$c['section_code'].' has no state key.';
        $playerProse=[];foreach($workspace['events']as$x)$playerProse[]=['event '.$x['event_code'],$x['event_text']];foreach($workspace['decisions']as$x)$playerProse[]=['decision '.$x['event_code'].'/'.$x['role_code'],$x['prompt_text']];foreach($workspace['options']as$x)$playerProse[]=['option '.$x['option_code'],$x['result_text']];foreach($workspace['resolutions']as$x)$playerProse[]=['resolution '.$x['section_code'],$x['fragment_text']];foreach($playerProse as[$where,$text])if(preg_match('/(?:Final factors:|\b(?:NPC|PLAYER|NO_ACTION|WORLD_STATE|COURT_TRUST|LEGITIMACY|STABILITY|UNREST)\b|\d+\s*→\s*\d+)/u',(string)$text))$warnings[]=$where.' may expose engine language to players.';
        $words=0;$sections=[];foreach($workspace['resolutions']as$r)if((int)$r['active']===1){$words+=str_word_count(strip_tags((string)$r['fragment_text']));$sections[$r['section_code']]=true;}if($words<300||!isset($sections['AFTERMATH'])||!isset($sections['INVITATION']))$warnings[]='The authored ending needs at least 300 words plus AFTERMATH and INVITATION sections.';
        foreach($workspace['media']as$m)if((int)$m['active']===1&&trim((string)$m['alt_text'])==='')$errors[]='Active image '.$m['asset_code'].' needs alternative text.';
        if(!$workspace['media'])$warnings[]='No optional story artwork has been uploaded.';
        $lines=['TOINKY STORY REVIEW PACKAGE','Story: '.$plot['title'].' ('.$plot['code'].')','Author: '.($plot['author_name']??'Unassigned'),'Workflow: '.$plot['workflow_status'],'Genre: '.($plot['genre_label']??$plot['genre']),'','SCENARIO',trim((string)$plot['scenario_text']),'','AUTOMATED FINDINGS'];
        foreach($errors as$e)$lines[]='ERROR: '.$e;foreach($warnings as$w)$lines[]='WARNING: '.$w;if(!$errors&&!$warnings)$lines[]='PASS: Automated structural checks found no issue.';
        $lines[]='';$lines[]='CAST';foreach($workspace['characters']as$c)$lines[]=$c['role_code'].' — '.$c['name'].'; '.$c['pronoun_set'].'; starts at '.$c['starting_location'].'; goal: '.$c['goal_text'].'; fear: '.($c['fear_text']?:'not authored').'.';
        $lines[]='';$lines[]='CHRONOLOGY AND DECISIONS';foreach($workspace['events']as$ev){$lines[]=$ev['event_second'].'s '.$ev['phase'].' '.$ev['event_code'].': '.$ev['headline'].' — '.$ev['event_text'];foreach($workspace['decisions']as$d)if($d['event_code']===$ev['event_code']){$lines[]='  '.$d['role_code'].' PROMPT: '.$d['prompt_text'];foreach($workspace['options']as$o)if((int)$o['template_id']===(int)$d['template_id'])$lines[]='    '.$o['option_code'].': '.$o['label'].' => '.$o['result_text'];}}
        $lines[]='';$lines[]='DYNAMIC OPPORTUNITIES';foreach($workspace['opportunities']as$o)$lines[]=$o['role_code'].' / '.$o['opportunity_code'].': '.$o['prompt_text'];
        $lines[]='';$lines[]='ENDING FRAGMENTS';foreach($workspace['resolutions']as$r)$lines[]=$r['section_code'].' priority '.$r['priority'].': '.$r['fragment_text'];
        $lines[]='';$lines[]='AI CRITIQUE REQUEST';$lines[]='Critique this story for player immersion, viewpoint continuity, creative prose, character agency, branch causality, dependency gaps, unreachable outcomes, plot holes, pacing, replayability, mobile readability, and whether every character can understand enough to make meaningful choices without receiving privileged knowledge. Propose concrete database-authored improvements and do not recommend plot-specific PHP.';
        return['errors'=>$errors,'warnings'=>$warnings,'text'=>implode("\n",$lines),'workspace'=>$workspace];
    }

    public static function submit(array $user,int $plotId): int
    {
        self::guard($user,$plotId);$audit=self::audit($plotId);if($audit['errors'])throw new RuntimeException('Resolve the blocking validation errors before submission.');$pdo=db();$snapshot=json_encode(['plot'=>self::story($plotId),'content'=>$audit['workspace']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$q=$pdo->prepare('SELECT COALESCE(MAX(version_no),0)+1 FROM plot_versions WHERE plot_id=?');$q->execute([$plotId]);$version=(int)$q->fetchColumn();$pdo->beginTransaction();try{$pdo->prepare('INSERT INTO plot_versions(plot_id,version_no,snapshot_json,submitted_by_user_id) VALUES(?,?,?,?)')->execute([$plotId,$version,$snapshot,$user['user_id']]);$id=(int)$pdo->lastInsertId();$pdo->prepare('UPDATE plots SET workflow_status="SUBMITTED" WHERE plot_id=?')->execute([$plotId]);$pdo->commit();return$id;}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }

    public static function saveAudit(int $plotId,int $userId,?int $versionId=null): array
    {
        $a=self::audit($plotId);db()->prepare('INSERT INTO story_audit_runs(plot_id,plot_version_id,requested_by_user_id,audit_text,blocking_error_count,warning_count) VALUES(?,?,?,?,?,?)')->execute([$plotId,$versionId,$userId,$a['text'],count($a['errors']),count($a['warnings'])]);return$a;
    }

    private static function guard(array $user,int $plotId): void{if(!self::canEdit($user,$plotId))throw new RuntimeException('You may only edit stories you own.');}
    private static function code(string $value):string{return trim(strtoupper((string)preg_replace('/[^A-Za-z0-9]+/','_',trim($value))),'_');}
    private static function nullable($value):?string{$value=trim((string)$value);return$value===''?null:$value;}
    private static function integerOrNull($value):?int{$value=trim((string)$value);return$value===''?null:(int)$value;}
    private static function assertParent(string $table,string $key,int $id,int $plotId):void{$q=db()->prepare('SELECT 1 FROM '.$table.' WHERE '.$key.'=? AND plot_id=?');$q->execute([$id,$plotId]);if(!$q->fetchColumn())throw new RuntimeException('The selected parent record does not belong to this story.');}
}
