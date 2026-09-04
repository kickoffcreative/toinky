<?php
require_once __DIR__.'/AiConnectionService.php';

final class InkyGuideService
{
    private const STEP_COUNT=5;
    private const MAX_ANSWER_CHARS=12000;
    private const MAX_TOTAL_CHARS=24000;
    private const MAX_PROPOSAL_ANSWERS_CHARS=8000;
    private const MAX_PROPOSAL_ACCEPTED_CHARS=14000;
    private const PROPOSALS=[
        'title'=>['label'=>'Title','type'=>'text','max'=>190],
        'archetype'=>['label'=>'Archetype','type'=>'text','max'=>120],
        'setting_name'=>['label'=>'Setting','type'=>'text','max'=>190],
        'scenario_text'=>['label'=>'Scenario Briefing','type'=>'long','max'=>4000],
        'location_1_name'=>['label'=>'Location 1 · Location Name','type'=>'text','max'=>190],
        'location_1_description'=>['label'=>'Location 1 · Description','type'=>'long','max'=>2000],
        'location_2_name'=>['label'=>'Location 2 · Location Name','type'=>'text','max'=>190],
        'location_2_description'=>['label'=>'Location 2 · Description','type'=>'long','max'=>2000],
        'story_value_1'=>['label'=>'Story Value 1','type'=>'value'],
        'story_value_2'=>['label'=>'Story Value 2','type'=>'value'],
        'character_1'=>['label'=>'Character 1','type'=>'character'],
        'character_2'=>['label'=>'Character 2','type'=>'character'],
        'character_3'=>['label'=>'Character 3','type'=>'character'],
        'character_4'=>['label'=>'Character 4','type'=>'character'],
        'character_5'=>['label'=>'Character 5','type'=>'character'],
        'character_6'=>['label'=>'Character 6','type'=>'character'],
        'character_7'=>['label'=>'Character 7','type'=>'character'],
        'character_8'=>['label'=>'Character 8','type'=>'character'],
        'character_9'=>['label'=>'Character 9','type'=>'character'],
        'character_10'=>['label'=>'Character 10','type'=>'character'],
        'character_11'=>['label'=>'Character 11','type'=>'character'],
        'character_12'=>['label'=>'Character 12','type'=>'character'],
        'character_13'=>['label'=>'Character 13','type'=>'character'],
        'character_14'=>['label'=>'Character 14','type'=>'character'],
        'character_15'=>['label'=>'Character 15','type'=>'character'],
        'character_16'=>['label'=>'Character 16','type'=>'character'],
        'event_1'=>['label'=>'Opening Event','type'=>'event'],
        'decision_1'=>['label'=>'Decision 1','type'=>'decision'],
        'opportunity_1'=>['label'=>'Conditional Opportunity 1','type'=>'opportunity'],
        'ending_section_1'=>['label'=>'Ending Section 1','type'=>'ending_section'],
        'ending_1'=>['label'=>'Ending 1','type'=>'ending'],
        'max_players'=>['label'=>'Human Players','type'=>'number'],
        'max_npcs'=>['label'=>'AI Cast','type'=>'number'],
    ];

    public static function guidedDrafts(array $user): array
    {
        $q=db()->prepare('SELECT d.guide_draft_id,d.plot_id,d.updated_at_utc,p.title FROM inky_story_guide_drafts d JOIN plots p ON p.plot_id=d.plot_id WHERE d.author_user_id=? AND p.workflow_status="DRAFT" ORDER BY d.updated_at_utc DESC');
        $q->execute([(int)$user['user_id']]);return$q->fetchAll();
    }

    public static function loadProgress(array $user,int $plotId): ?array
    {
        self::assertDraftAccess($user,$plotId);$q=db()->prepare('SELECT state_json FROM inky_story_guide_drafts WHERE plot_id=? AND author_user_id=? LIMIT 1');$q->execute([$plotId,(int)$user['user_id']]);$raw=$q->fetchColumn();if($raw===false)return null;return self::jsonObject($raw,'saved draft state');
    }

    public static function saveProgress(array $user,int $plotId,mixed$raw): void
    {
        self::assertDraftAccess($user,$plotId);$state=self::jsonObject($raw,'draft state');$state['step']=max(0,min(self::STEP_COUNT,(int)($state['step']??0)));$state['answers']=self::answers($state['answers']??[]);$state['accepted_proposals']=self::jsonObject($state['accepted_proposals']??[],'accepted proposals');$json=json_encode($state,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if($json===false||mb_strlen($json)>500000)throw new RuntimeException('The Inky draft is too large to save.');$q=db()->prepare('INSERT INTO inky_story_guide_drafts(plot_id,author_user_id,state_json) VALUES(?,?,?) ON DUPLICATE KEY UPDATE author_user_id=VALUES(author_user_id),state_json=VALUES(state_json),updated_at_utc=CURRENT_TIMESTAMP');$q->execute([$plotId,(int)$user['user_id'],$json]);
    }

    private static function assertDraftAccess(array $user,int$plotId): void
    {
        $q=db()->prepare('SELECT author_user_id,workflow_status FROM plots WHERE plot_id=? LIMIT 1');$q->execute([$plotId]);$plot=$q->fetch();if(!$plot||((int)($user['is_admin']??0)!==1&&(int)$plot['author_user_id']!==(int)$user['user_id']))throw new RuntimeException('You may only edit your own Inky drafts.');if((string)$plot['workflow_status']!=='DRAFT')throw new RuntimeException('Only draft stories can be resumed through Inky.');
    }

    public static function guide(array $user,array $input): array
    {
        if((string)($input['phase']??'')==='proposal')return self::proposal($input);
        $genreId=(int)($input['genre_id']??0);$q=db()->prepare('SELECT label_text FROM story_genres WHERE genre_id=? AND active=1');$q->execute([$genreId]);$genre=trim((string)$q->fetchColumn());if($genre==='')throw new RuntimeException('Choose a genre for the conversation.');
        $step=(int)($input['step']??0);if($step<0||$step>=self::STEP_COUNT)throw new RuntimeException('That Inky conversation step is not available.');$answers=self::answers($input['answers']??[]);for($index=0;$index<$step;$index++)if(trim((string)($answers[$index]??''))==='')throw new RuntimeException('Complete the earlier Inky question first.');$answer=self::text($input['answer']??'','answer',self::MAX_ANSWER_CHARS);if(mb_strlen($answer)<3)throw new RuntimeException('Give Inky a little more to work with.');$answers[$step]=$answer;ksort($answers);$total=0;foreach($answers as$value)$total+=mb_strlen((string)$value);if($total>self::MAX_TOTAL_CHARS)throw new RuntimeException('The Inky conversation has become too long. Start a new conversation or shorten an earlier answer.');
        $system='You are Inky, a patient story coach for a new interactive-fiction writer. Hold a five-step conversation in the supplied genre. Respond with JSON only and exactly this shape: {"reply":"brief warm acknowledgement and useful guidance","question":"one clear next question","complete":false,"brief":null}. On the fifth answer, use {"reply":"brief encouraging wrap-up","question":"","complete":true,"brief":{"title":"player-facing story title","setting_name":"short setting","scenario_text":"80 to 250 words describing the story concept","tone":"short tone","author_notes":"short notes for the writer"}}. Ask only one question at a time. Do not create characters, locations, events, decisions, options, endings, stable codes, database records, PHP, schema, or engine instructions. Do not force the writer to know technical story design terms. Build from the writer\'s answers and do not invent major facts without signaling them as a suggestion. The five questions are: story spark; who the story follows and what they want; where and when it happens; the central problem or meaningful choice; tone and the feeling the player should leave with. Keep reply and question concise. Return no markdown and no additional keys.';
        $prompt=json_encode(['genre'=>$genre,'step'=>$step+1,'current_answer'=>$answer,'previous_answers'=>$answers,'instruction'=>'Acknowledge the latest answer, then ask the next single question. At step five, summarize only the writer concept into the brief.'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if($prompt===false)throw new RuntimeException('The Inky conversation request could not be prepared.');$response=AiConnectionService::completeJson('INKY_STORY_GUIDE',$system,$prompt);$guided=self::validatedResponse((array)$response['data'],$step);return['step'=>$step,'next_step'=>$guided['complete']?null:$step+1,'reply'=>$guided['reply'],'question'=>$guided['question'],'complete'=>$guided['complete'],'brief'=>$guided['brief'],'answers'=>$answers,'model'=>$response['model']];
    }

    public static function suggestContent(array $user,array $input): array
    {
        [$genre,$step,$answer,$answers,$brief]=self::context($input);
        $system='You are Inky, a patient story coach for an amateur interactive-fiction writer. Return JSON only and exactly this shape: {"content":"writer-ready answer"}. Suggest content for the current question only. Build from the supplied answers and brief, do not invent major facts without signaling them as a suggestion, and do not use technical engine terms. Keep the answer useful but concise.';
        $prompt=json_encode(['genre'=>$genre,'step'=>$step+1,'question'=>self::questions()[$step],'current_answer'=>$answer,'previous_answers'=>$answers,'current_brief'=>$brief,'instruction'=>'Suggest a possible answer the writer can edit before continuing.'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if($prompt===false)throw new RuntimeException('The Inky content suggestion request could not be prepared.');
        $response=AiConnectionService::completeJson('INKY_STORY_GUIDE_CONTENT',$system,$prompt);$contentData=(array)$response['data'];$content=self::text($contentData['content']??$contentData['suggestion']??$contentData['answer']??'','suggested content',self::MAX_ANSWER_CHARS);if(mb_strlen($content)<3)throw new RuntimeException('Inky returned an empty content suggestion.');return['content'=>$content,'model'=>$response['model']];
    }

    public static function suggestEdits(array $user,array $input): array
    {
        [$genre,$step,$answer,$answers,$brief]=self::context($input);$variation=self::text($input['variation']??'','edit variation',80,false);$variationInstruction=$variation!==''?'Shape every suggestion toward making the answer '.$variation.'.':'Identify the most useful improvements.';
        $system='You are Inky, a precise story editor for an amateur interactive-fiction writer. Return JSON only and exactly this shape: {"edits":[{"target":"exact contiguous text copied from the current answer","replacement":"text that should replace only that target","reason":"why this improves the answer"}]}. Return 3 to 8 genuinely different itemized edits, never more than 20. Every target must be copied exactly from the supplied current answer, including capitalization and punctuation. Keep each target short and specific; never use the entire answer as a target. Every replacement must be materially different from its target and must improve a distinct part of the answer. The replacement may be empty only when the targeted text should be removed. Do not echo the current answer as a replacement, repeat the same target, or return general writing advice without a target and replacement. Follow the requested editing direction: '.$variationInstruction.' Each edit must be independently understandable and focused on the current answer. Do not rewrite the whole story, invent hidden rules, or use PHP, schema, or engine instructions.';
        $prompt=json_encode(['genre'=>$genre,'step'=>$step+1,'question'=>self::questions()[$step],'current_answer'=>$answer,'previous_answers'=>$answers,'current_brief'=>$brief,'variation'=>$variation,'instruction'=>$variationInstruction],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if($prompt===false)throw new RuntimeException('The Inky edit suggestion request could not be prepared.');
        $response=AiConnectionService::completeJson('INKY_STORY_GUIDE_EDITS',$system,$prompt);$data=(array)$response['data'];$rawEdits=$data['edits']??$data['edit_suggestions']??$data['suggestions']??$data['recommendations']??$data['items']??$data['changes']??$data;if(isset($rawEdits['edit'])||isset($rawEdits['suggestion'])||isset($rawEdits['revision'])||isset($rawEdits['replacement'])||isset($rawEdits['proposed_edit']))$rawEdits=[$rawEdits];$edits=[];$seen=[];foreach(array_slice((array)$rawEdits,0,20)as$item){if(!is_array($item))continue;$target=self::text($item['target']??$item['original']??$item['find']??'','edit target',self::MAX_ANSWER_CHARS,false);$replacement=self::text($item['replacement']??$item['proposed_edit']??$item['edit']??$item['suggestion']??$item['revision']??$item['suggested_edit']??'','edit replacement',self::MAX_ANSWER_CHARS,false);$reason=self::text($item['reason']??$item['explanation']??$item['why']??$item['details']??'','edit explanation',600,false);$fingerprint=mb_strtolower($target);if($target!==''&&$target!==$answer&&str_contains($answer,$target)&&$replacement!==$target&&!isset($seen[$fingerprint])){$seen[$fingerprint]=true;$edits[]=['target'=>$target,'replacement'=>$replacement,'edit'=>$replacement,'reason'=>$reason];}}if(!$edits)throw new RuntimeException('Inky returned no usable targeted edit suggestions.');return['edits'=>$edits,'model'=>$response['model']];
    }

    private static function context(array $input): array
    {
        $genreId=(int)($input['genre_id']??0);$q=db()->prepare('SELECT label_text FROM story_genres WHERE genre_id=? AND active=1');$q->execute([$genreId]);$genre=trim((string)$q->fetchColumn());if($genre==='')throw new RuntimeException('Choose a genre for the conversation.');$step=(int)($input['step']??0);if($step<0||$step>=self::STEP_COUNT)throw new RuntimeException('That Inky conversation step is not available.');$answer=self::text($input['answer']??'','current answer',self::MAX_ANSWER_CHARS,false);return[$genre,$step,$answer,self::answers($input['answers']??[]),self::jsonObject($input['brief']??[],'story brief')];
    }

    private static function questions(): array
    {
        return['What is the spark for the story?','Who does the story follow, and what do they want?','Where and when does it happen?','What problem, mystery, or meaningful choice drives the story?','What tone should it have, and what feeling should the player leave with?'];
    }

    private static function proposal(array $input): array
    {
        $key=trim((string)($input['proposal_key']??''));$definition=self::PROPOSALS[$key]??null;if(!$definition&&preg_match('/^story_value_([3-9]|1[0-6])$/',$key,$match))$definition=['label'=>'Story Value '.$match[1],'type'=>'value'];if(!$definition)throw new RuntimeException('That Inky proposal is not available.');
        $answers=self::boundedPromptContext(self::jsonObject($input['answers']??[],'conversation answers'),self::MAX_PROPOSAL_ANSWERS_CHARS);$brief=self::jsonObject($input['brief']??[],'story brief');$accepted=self::boundedPromptContext(self::jsonObject($input['accepted_proposals']??[],'accepted proposals'),self::MAX_PROPOSAL_ACCEPTED_CHARS);$rejected=self::jsonValue($input['rejected_value']??null);$live=self::jsonValue($input['current_proposal']??null);if($live!==null&&$live!==''&&$live!==[])$rejected=$live;$variation=self::text($input['variation']??'','suggestion variation',80,false);$editableFields=self::proposalFields($input['editable_fields']??[]);
        $system='You are Inky, a patient story coach helping an amateur interactive-fiction writer. Return JSON only and exactly this shape: {"key":"'.$key.'","value":PROPOSAL_VALUE,"reason":"one short reason","label":"'.$definition['label'].'"}. Propose only the requested item. Use the writer answers and current brief. Do not repeat a rejected proposal. Do not create database records, PHP, schema, or hidden story rules. For locations, propose a name or description only. For characters, return an object with exactly name, role_code, role_type, mystery_character, public_description, private_knowledge, starting_location, goal_text, fear_text, pronoun_set. Set mystery_character to true only when the character should be selectable in the lobby with its identity and details hidden until the story begins. For events, return an object with exactly event_code, phase, headline, text. For decisions, return an object with exactly event_code, character, prompt, options, where options is an array of exactly two objects with label and result. For conditional opportunities, return an object with exactly opportunity_code, character, trigger, prompt, condition, options, where condition has source_type, source_key, operator, compare_value and options is an array of exactly two objects with label and result. For ending sections, return an object with exactly section_code, label, description. For endings, return an object with exactly section, headline, text. For story values, return an object with exactly scope, target_code, label, value_type, allowed_values, description. Story value scope must be FACTOR, STAT, LOCATION, KNOWLEDGE, RELATIONSHIP, WORLD_STATE, GOAL, or FEAR. Story value type must be NUMERIC, PRESET, or TEXT. Keep all prose concise and usable in Story Writer. When a live edited proposal is supplied, it is the authoritative source for the current item. Use it as the basis for the new suggestion and never copy current-item fields from accepted_proposals. For character suggestions, ignore all accepted character entries and use only live_edited_character as the character being revised.';
        if($editableFields&&$rejected!==null)$system.=' A current proposal is supplied. Only revise these selected field paths: '.json_encode($editableFields,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).'. Preserve every other field exactly, including nested fields and option details, and return the complete item with those preserved values included.';
        $prompt=json_encode(['requested_proposal'=>$key,'requested_label'=>$definition['label'],'writer_answers'=>$answers,'current_brief'=>$brief,'accepted_proposals'=>$accepted,'rejected_proposal'=>$rejected,'live_edited_proposal'=>$live,'live_edited_character'=>$input['live_edited_character']??null,'variation'=>$variation,'editable_fields'=>$editableFields,'instruction'=>$variation!==''?'Make this alternative '.$variation.'.':'Make this a fresh alternative.'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if($prompt===false)throw new RuntimeException('The Inky proposal request could not be prepared.');
        $response=AiConnectionService::completeJson('INKY_STORY_PROPOSAL',$system,$prompt);$proposalInput=(array)$response['data'];if($editableFields&&$rejected!==null&&array_key_exists('value',$proposalInput))$proposalInput['value']=self::mergeProposalFields($rejected,$proposalInput['value'],$editableFields);$proposal=self::validatedProposal($proposalInput,$key,$definition);return['proposal'=>$proposal,'model'=>$response['model']];
    }

    private static function validatedResponse(array $input,int $step): array
    {
        $reply=self::text($input['reply']??'','Inky reply',1200);$complete=$step===self::STEP_COUNT-1;if($complete){$briefInput=is_array($input['brief']??null)?$input['brief']:[];$scenario=self::text($briefInput['scenario_text']??'','story concept',4000);if(mb_strlen($scenario)<80)throw new RuntimeException('Inky returned a story concept that is too short.');return['reply'=>$reply,'question'=>'','complete'=>true,'brief'=>['title'=>self::text($briefInput['title']??'','story title',190),'setting_name'=>self::text($briefInput['setting_name']??'','setting',190),'scenario_text'=>$scenario,'tone'=>self::text($briefInput['tone']??'','tone',160,false),'author_notes'=>self::text($briefInput['author_notes']??'','writer notes',2000,false)]];}
        return['reply'=>$reply,'question'=>self::text($input['question']??'','next question',600),'complete'=>false,'brief'=>null];
    }

    private static function answers(mixed $raw): array
    {
        if(is_string($raw)){try{$raw=json_decode($raw,true,20,JSON_THROW_ON_ERROR);}catch(Throwable$e){throw new RuntimeException('The Inky conversation state is invalid.');}}
        if(!is_array($raw))throw new RuntimeException('The Inky conversation state is invalid.');$answers=[];foreach($raw as$key=>$value){if(!preg_match('/^[0-4]$/',(string)$key))continue;if(!is_scalar($value))continue;$answers[(int)$key]=self::text($value,'earlier answer',self::MAX_ANSWER_CHARS);}return$answers;
    }

    private static function text(mixed$value,string$label,int$max,bool$required=true): string
    {
        $value=is_scalar($value)?trim((string)$value):'';if($required&&$value==='')throw new RuntimeException('Inky is missing a usable '.$label.'.');if(mb_strlen($value)>$max)throw new RuntimeException('Inky’s '.$label.' is too long.');return$value;
    }

    private static function jsonObject(mixed$raw,string$label): array
    {
        if(is_string($raw)){try{$raw=json_decode($raw,true,20,JSON_THROW_ON_ERROR);}catch(Throwable$e){throw new RuntimeException('The Inky '.$label.' is invalid.');}}
        if(!is_array($raw))throw new RuntimeException('The Inky '.$label.' is invalid.');return$raw;
    }

    private static function jsonValue(mixed$raw): mixed
    {
        if(!is_string($raw)||trim($raw)==='')return$raw;try{return json_decode($raw,true,20,JSON_THROW_ON_ERROR);}catch(Throwable$e){return$raw;}
    }

    private static function boundedPromptContext(mixed$value,int$limit): mixed
    {
        $remaining=$limit;return self::boundPromptValue($value,$remaining);
    }

    private static function boundPromptValue(mixed$value,int &$remaining): mixed
    {
        if($remaining<=0)return null;if(is_string($value)){$text=mb_substr($value,0,$remaining);$remaining-=mb_strlen($text);return$text;}if(is_int($value)||is_float($value)||is_bool($value)||$value===null)return$value;if(!is_array($value))return null;$bounded=[];foreach($value as$key=>$item){if($remaining<=0)break;$bounded[$key]=self::boundPromptValue($item,$remaining);}return$bounded;
    }

    private static function proposalFields(mixed$raw): array
    {
        if(is_string($raw))$raw=self::jsonValue($raw);if(!is_array($raw))return[];$paths=[];foreach(array_slice($raw,0,100)as$path){if(!is_array($path))continue;$clean=[];$valid=true;foreach($path as$segment){if(is_int($segment)||(is_string($segment)&&preg_match('/^\d+$/',$segment)))$clean[]=(int)$segment;elseif(is_string($segment)&&preg_match('/^[A-Za-z][A-Za-z0-9_]*$/',$segment))$clean[]=$segment;else{$valid=false;break;}}if($valid)$paths[]=$clean;}return$paths;
    }

    private static function mergeProposalFields(mixed$current,mixed$suggested,array$paths): mixed
    {
        if(!is_array($current)||!is_array($suggested)||!$paths)return$suggested;if(in_array([],$paths,true))return$suggested;$merged=$current;foreach($paths as$path){$source=$suggested;$valid=true;foreach($path as$segment){if(!is_array($source)||!array_key_exists($segment,$source)){$valid=false;break;}$source=$source[$segment];}if(!$valid||!$path)continue;$target=&$merged;foreach(array_slice($path,0,-1)as$segment){if(!is_array($target)||!array_key_exists($segment,$target)){$valid=false;break;}$target=&$target[$segment];}if($valid)$target[$path[array_key_last($path)]]=$source;unset($target);}return$merged;
    }

    private static function validatedProposal(array$input,string$key,array$definition): array
    {
        $value=$input['value']??null;$type=$definition['type'];$reason=self::text($input['reason']??'','proposal reason',500,false);
        if($type==='value'){$value=self::objectFields($value,['scope','target_code','label','value_type','allowed_values','description'],'story value');$scopes=['FACTOR','STAT','LOCATION','KNOWLEDGE','RELATIONSHIP','WORLD_STATE','GOAL','FEAR'];$types=['NUMERIC','PRESET','TEXT'];$scope=in_array(($value['scope']??''),$scopes,true)?(string)$value['scope']:'WORLD_STATE';$valueType=in_array(($value['value_type']??''),$types,true)?(string)$value['value_type']:'PRESET';$allowed=[];foreach((array)($value['allowed_values']??[])as$item){$item=self::text($item,'allowed story value',80,false);if($item!=='')$allowed[]=$item;}if($valueType!=='PRESET')$allowed=[];$normalized=['scope'=>$scope,'target_code'=>self::code((string)($value['target_code']??$value['label']??'')),'label'=>self::text($value['label']??'','story value label',190),'value_type'=>$valueType,'allowed_values'=>array_values(array_unique(array_slice($allowed,0,8))),'description'=>self::text($value['description']??'','story value description',1000,false)];return['key'=>$key,'label'=>$definition['label'],'value'=>$normalized,'reason'=>$reason];}
        if($type==='number'){$number=(int)$value;$number=$key==='max_npcs'?max(0,min(8,$number)):max(1,min(8,$number));return['key'=>$key,'label'=>$definition['label'],'value'=>$number,'reason'=>$reason];}
        if(in_array($type,['character','event','decision','opportunity','ending_section','ending'],true))return['key'=>$key,'label'=>$definition['label'],'value'=>self::validateProposalObject($value,$type),'reason'=>$reason];
        $text=self::text($value,strtolower($definition['label']),(int)$definition['max']);if($key==='scenario_text'&&mb_strlen($text)<80)throw new RuntimeException('Inky returned a scenario briefing that is too short.');return['key'=>$key,'label'=>$definition['label'],'value'=>$text,'reason'=>$reason];
    }

    private static function validateProposalObject(mixed$value,string$type): array
    {
        if(!is_array($value))throw new RuntimeException('Inky returned an invalid '.$type.' proposal.');
        if($type==='character')return['name'=>self::text($value['name']??'','character name',120),'role_code'=>self::code((string)($value['role_code']??$value['name']??'')),'role_type'=>in_array(($value['role_type']??''),['PLAYER','NPC'],true)?$value['role_type']:'NPC','mystery_character'=>(int)!empty($value['mystery_character']),'public_description'=>self::text($value['public_description']??'','character description',1000),'private_knowledge'=>self::text($value['private_knowledge']??'','private knowledge',1000,false),'starting_location'=>self::text($value['starting_location']??'','starting location',190,false),'goal_text'=>self::text($value['goal_text']??'','character goal',500,false),'fear_text'=>self::text($value['fear_text']??'','character fear',500,false),'pronoun_set'=>in_array(($value['pronoun_set']??''),['HE','SHE','IT'],true)?$value['pronoun_set']:'IT'];
        if($type==='event')return['event_code'=>self::code((string)($value['event_code']??$value['headline']??'')),'phase'=>in_array(($value['phase']??''),['EXPOSITION','INCITING_INCIDENT','RISING_ACTION','CLIMAX','RESOLUTION'],true)?$value['phase']:'EXPOSITION','headline'=>self::text($value['headline']??'','event headline',190),'text'=>self::text($value['text']??'','event text',1600)];
        if($type==='decision')return['event_code'=>self::code((string)($value['event_code']??'')),'character'=>self::text($value['character']??'','decision character',120),'prompt'=>self::text($value['prompt']??'','decision prompt',1200),'options'=>self::proposalOptions($value['options']??[])];
        if($type==='opportunity'){$condition=self::objectFields($value['condition']??[] ,['source_type','source_key','operator','compare_value'],'opportunity condition');return['opportunity_code'=>self::code((string)($value['opportunity_code']??$value['prompt']??'')),'character'=>self::text($value['character']??'','opportunity character',120),'trigger'=>self::text($value['trigger']??'','opportunity trigger',500),'prompt'=>self::text($value['prompt']??'','opportunity prompt',1200),'condition'=>['source_type'=>self::text($condition['source_type']??'PHASE','condition source type',40,false),'source_key'=>self::text($condition['source_key']??'','condition source key',190,false),'operator'=>self::text($condition['operator']??'EQ','condition operator',20,false),'compare_value'=>self::text($condition['compare_value']??'EXPOSITION','condition value',500,false)],'options'=>self::proposalOptions($value['options']??[])];}
        if($type==='ending_section')return['section_code'=>self::code((string)($value['section_code']??$value['label']??'')),'label'=>self::text($value['label']??'','ending section label',190),'description'=>self::text($value['description']??'','ending section description',1000,false)];
        return['section'=>self::text($value['section']??'','ending section',190),'headline'=>self::text($value['headline']??'','ending headline',190),'text'=>self::text($value['text']??'','ending text',1600)];
    }

    private static function proposalOptions(mixed$value): array
    {
        if(!is_array($value))throw new RuntimeException('Inky returned invalid decision options.');$options=[];foreach(array_slice($value,0,2)as$option)$options[]=['label'=>self::text($option['label']??'','option label',190),'result'=>self::text($option['result']??'','option result',800)];if(count($options)!==2)throw new RuntimeException('Inky must return two contrasting options.');return$options;
    }

    private static function objectFields(mixed$value,array$fields,string$label): array
    {
        if(!is_array($value))throw new RuntimeException('Inky returned an invalid '.$label.' proposal.');foreach($fields as$field)if(!array_key_exists($field,$value))throw new RuntimeException('Inky returned an incomplete '.$label.' proposal.');return$value;
    }

    private static function code(string$value): string
    {
        $value=strtoupper(trim((string)preg_replace('/[^A-Z0-9]+/i','_',trim($value))));return trim($value,'_');
    }
}
