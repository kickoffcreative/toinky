<?php
require_once __DIR__.'/AiConnectionService.php';

final class InkyGuideService
{
    private const STEP_COUNT=5;
    private const MAX_ANSWER_CHARS=12000;
    private const MAX_TOTAL_CHARS=24000;
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
        'event_1'=>['label'=>'Opening Event','type'=>'event'],
        'decision_1'=>['label'=>'Decision 1','type'=>'decision'],
        'opportunity_1'=>['label'=>'Conditional Opportunity 1','type'=>'opportunity'],
        'ending_section_1'=>['label'=>'Ending Section 1','type'=>'ending_section'],
        'ending_1'=>['label'=>'Ending 1','type'=>'ending'],
        'max_players'=>['label'=>'Human Players','type'=>'number'],
        'max_npcs'=>['label'=>'AI Cast','type'=>'number'],
    ];

    public static function guide(array $user,array $input): array
    {
        if((string)($input['phase']??'')==='proposal')return self::proposal($input);
        $genreId=(int)($input['genre_id']??0);$q=db()->prepare('SELECT label_text FROM story_genres WHERE genre_id=? AND active=1');$q->execute([$genreId]);$genre=trim((string)$q->fetchColumn());if($genre==='')throw new RuntimeException('Choose a genre for the conversation.');
        $step=(int)($input['step']??0);if($step<0||$step>=self::STEP_COUNT)throw new RuntimeException('That Inky conversation step is not available.');$answers=self::answers($input['answers']??[]);for($index=0;$index<$step;$index++)if(trim((string)($answers[$index]??''))==='')throw new RuntimeException('Complete the earlier Inky question first.');$answer=self::text($input['answer']??'','answer',self::MAX_ANSWER_CHARS);if(mb_strlen($answer)<3)throw new RuntimeException('Give Inky a little more to work with.');$answers[$step]=$answer;ksort($answers);$total=0;foreach($answers as$value)$total+=mb_strlen((string)$value);if($total>self::MAX_TOTAL_CHARS)throw new RuntimeException('The Inky conversation has become too long. Start a new conversation or shorten an earlier answer.');
        $system='You are Inky, a patient story coach for a new interactive-fiction writer. Hold a five-step conversation in the supplied genre. Respond with JSON only and exactly this shape: {"reply":"brief warm acknowledgement and useful guidance","question":"one clear next question","complete":false,"brief":null}. On the fifth answer, use {"reply":"brief encouraging wrap-up","question":"","complete":true,"brief":{"title":"player-facing story title","setting_name":"short setting","scenario_text":"80 to 250 words describing the story concept","tone":"short tone","author_notes":"short notes for the writer"}}. Ask only one question at a time. Do not create characters, locations, events, decisions, options, endings, stable codes, database records, PHP, schema, or engine instructions. Do not force the writer to know technical story design terms. Build from the writer\'s answers and do not invent major facts without signaling them as a suggestion. The five questions are: story spark; who the story follows and what they want; where and when it happens; the central problem or meaningful choice; tone and the feeling the player should leave with. Keep reply and question concise. Return no markdown and no additional keys.';
        $prompt=json_encode(['genre'=>$genre,'step'=>$step+1,'current_answer'=>$answer,'previous_answers'=>$answers,'instruction'=>'Acknowledge the latest answer, then ask the next single question. At step five, summarize only the writer concept into the brief.'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if($prompt===false)throw new RuntimeException('The Inky conversation request could not be prepared.');$response=AiConnectionService::completeJson('INKY_STORY_GUIDE',$system,$prompt);$guided=self::validatedResponse((array)$response['data'],$step);return['step'=>$step,'next_step'=>$guided['complete']?null:$step+1,'reply'=>$guided['reply'],'question'=>$guided['question'],'complete'=>$guided['complete'],'brief'=>$guided['brief'],'answers'=>$answers,'model'=>$response['model']];
    }

    private static function proposal(array $input): array
    {
        $key=trim((string)($input['proposal_key']??''));$definition=self::PROPOSALS[$key]??null;if(!$definition)throw new RuntimeException('That Inky proposal is not available.');
        $answers=self::jsonObject($input['answers']??[],'conversation answers');$brief=self::jsonObject($input['brief']??[],'story brief');$accepted=self::jsonObject($input['accepted_proposals']??[],'accepted proposals');$rejected=self::jsonValue($input['rejected_value']??null);
        $system='You are Inky, a patient story coach helping an amateur interactive-fiction writer. Return JSON only and exactly this shape: {"key":"'.$key.'","value":PROPOSAL_VALUE,"reason":"one short reason","label":"'.$definition['label'].'"}. Propose only the requested item. Use the writer answers and current brief. Do not repeat a rejected proposal. Do not create database records, PHP, schema, or hidden story rules. For locations, propose a name or description only. For characters, return an object with exactly name, role_code, role_type, public_description, private_knowledge, starting_location, goal_text, fear_text, pronoun_set. For events, return an object with exactly event_code, phase, headline, text. For decisions, return an object with exactly event_code, character, prompt, options, where options is an array of exactly two objects with label and result. For conditional opportunities, return an object with exactly opportunity_code, character, trigger, prompt, condition, options, where condition has source_type, source_key, operator, compare_value and options is an array of exactly two objects with label and result. For ending sections, return an object with exactly section_code, label, description. For endings, return an object with exactly section, headline, text. For story values, return an object with exactly scope, target_code, label, value_type, allowed_values, description. Story value scope must be FACTOR, STAT, LOCATION, KNOWLEDGE, RELATIONSHIP, WORLD_STATE, GOAL, or FEAR. Story value type must be NUMERIC, PRESET, or TEXT. Keep all prose concise and usable in Story Writer.';
        $prompt=json_encode(['requested_proposal'=>$key,'requested_label'=>$definition['label'],'writer_answers'=>$answers,'current_brief'=>$brief,'accepted_proposals'=>$accepted,'rejected_proposal'=>$rejected],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if($prompt===false)throw new RuntimeException('The Inky proposal request could not be prepared.');
        $response=AiConnectionService::completeJson('INKY_STORY_PROPOSAL',$system,$prompt);$proposal=self::validatedProposal((array)$response['data'],$key,$definition);return['proposal'=>$proposal,'model'=>$response['model']];
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
        if($type==='character')return['name'=>self::text($value['name']??'','character name',120),'role_code'=>self::code((string)($value['role_code']??$value['name']??'')),'role_type'=>in_array(($value['role_type']??''),['PLAYER','NPC'],true)?$value['role_type']:'NPC','public_description'=>self::text($value['public_description']??'','character description',1000),'private_knowledge'=>self::text($value['private_knowledge']??'','private knowledge',1000,false),'starting_location'=>self::text($value['starting_location']??'','starting location',190,false),'goal_text'=>self::text($value['goal_text']??'','character goal',500,false),'fear_text'=>self::text($value['fear_text']??'','character fear',500,false),'pronoun_set'=>in_array(($value['pronoun_set']??''),['HE','SHE','IT'],true)?$value['pronoun_set']:'IT'];
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
