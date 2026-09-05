<?php
require_once __DIR__.'/StoryAuthoringService.php';
require_once __DIR__.'/AiConnectionService.php';
require_once __DIR__.'/UserProfileService.php';

final class InkyCowriterService
{
    public static function run(array $writer,int $plotId,string $action,array $context=[]): array
    {
        if(!StoryAuthoringService::canEdit($writer,$plotId))throw new RuntimeException('You may only ask Inky to review stories you can edit.');$displayName=UserProfileService::inkyDisplayName(UserProfileService::inkyPreferences((int)$writer['user_id']));
        return match($action){'review_plot'=>self::reviewPlot($plotId,$displayName),'whats_next'=>self::whatsNext($plotId,$displayName),'quality_check'=>self::qualityCheck($plotId,$displayName),'dependencies'=>self::dependencyCheck($plotId,$displayName),'suggest_character'=>self::suggestCharacter($plotId,$displayName,$context),'review_characters'=>self::characterKnowledgeCheck($plotId,$displayName),'character_knowledge'=>self::characterKnowledgeCheck($plotId,$displayName),'story_values'=>self::storyValueSuggestions($plotId,$displayName),'review_opportunities'=>self::opportunityReview($plotId,$displayName),'review_opportunity'=>self::opportunityReview($plotId,$displayName),'focused_tests'=>self::focusedTestPlan($plotId,$displayName),default=>throw new RuntimeException('That Inky action is not available yet.')};
    }

    private static function suggestCharacter(int $plotId,string $displayName,array $context=[]): array
    {
        $plot=StoryAuthoringService::story($plotId);$workspace=StoryAuthoringService::workspace($plotId);$roles=$workspace['characters']??[];$locations=array_values(array_filter($workspace['locations']??[],static fn($row)=>(int)($row['active']??1)===1));$playerCount=count(array_filter($roles,static fn($row)=>(string)($row['role_type']??'')==='PLAYER'));$npcCount=count(array_filter($roles,static fn($row)=>(string)($row['role_type']??'')==='NPC'));$available=[];if($playerCount<(int)$plot['max_players'])$available[]='PLAYER';if($npcCount<(int)$plot['max_npcs'])$available[]='NPC';if(!$available)throw new RuntimeException('The Story Framework character limits are already full.');if(!$locations)throw new RuntimeException('Add an active Story Framework location before suggesting a character.');
        $guideState=self::guideDraftState($plotId);$roles=array_merge($roles,self::previousCharacterSuggestions($context));$audit=['text'=>'','errors'=>[],'warnings'=>[]];$locationNames=array_values(array_map(static fn($row)=>(string)$row['label_text'],$locations));$existing=array_values(array_map(static fn($row)=>['role_code'=>(string)$row['role_code'],'name'=>(string)$row['name'],'role_type'=>(string)$row['role_type'],'active'=>(int)($row['active']??1),'public_description'=>(string)($row['public_description']??''),'private_knowledge'=>(string)($row['private_knowledge']??''),'goal_text'=>(string)($row['goal_text']??''),'fear_text'=>(string)($row['fear_text']??'')],$roles));$explicit=self::explicitCharacterCandidates($plot,$workspace,$guideState,$context,$audit['text'],$existing);$explicitNames=array_values(array_map(static fn($item)=>(string)$item['name'],$explicit));$preferredExplicit=array_slice($explicit,0,1);$system='You are '.$displayName.', a patient story coach suggesting exactly one additional character for an amateur interactive-fiction writer. Scan the entire supplied draft context, including guided answers, accepted guide proposals, the scenario briefing, locations, story values, characters, world events, decisions, opportunities, and endings. Respect the available Story Framework slots and existing entity identities. Choose one role_type from the supplied available_role_types, never duplicate an existing role code or character name, and choose starting_location exactly from the supplied active_locations. Return the location label, not its database code. Character selection priority is strict: if EXPLICITLY IDENTIFIED CHARACTER CANDIDATES contains an eligible name, use that exact name first; only invent a new character name when no eligible explicit candidate remains. An explicit candidate is a person or character named in the authored draft, not a location, story title, role code, or generic noun. The new character must be original in profile and purpose. Do not copy, paraphrase, or reuse any existing character profile, description, private knowledge, goal, fear, or other character-specific prose. Existing character identities and previously shown unaccepted suggestions are constraints only, not source material. The suggestion must be a useful, playable addition that fits the full draft without revealing or inventing engine rules. Return JSON only with this exact shape: {"summary":"one concise reason","character":{"role_code":"STABLE_CODE","role_type":"PLAYER|NPC","mystery_character":false,"name":"Name","public_description":"player-visible description","private_knowledge":"optional private knowledge","starting_location":"EXACT ACTIVE LOCATION LABEL","goal_text":"character goal","fear_text":"optional fear","pronoun_set":"HE|SHE|IT"}}. Keep prose concise, return no markdown, and return no additional keys.';$prompt='AVAILABLE ROLE TYPES: '.implode(', ',$available)."\nACTIVE LOCATIONS (USE LABELS EXACTLY): ".implode(' | ',$locationNames)."\nEXPLICITLY IDENTIFIED CHARACTER CANDIDATES (USE THESE EXACT NAMES FIRST): ".json_encode($preferredExplicit,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE)."\nEXISTING CHARACTER IDENTITIES AND PREVIOUSLY SHOWN SUGGESTIONS (DO NOT COPY THEIR PROSE): ".json_encode(self::characterIdentities($roles),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE)."\nFULL DRAFT CONTEXT:\n".self::characterSuggestionContext($plot,$workspace,$locations,$guideState,$context,$audit['text']);$response=AiConnectionService::completeJson('INKY_SUGGEST_CHARACTER',$system,$prompt);try{$suggestion=self::validatedCharacterSuggestion((array)$response['data'],$available,$locations,$roles,$explicitNames);}catch(RuntimeException $error){$retryPrompt='CORRECTION ONLY. The previous character JSON failed validation: '.$error->getMessage().'\\nReturn one corrected JSON object with the exact schema already specified. Use the exact preferred explicit candidate name when one is supplied; otherwise keep the candidate name if it is new. Recheck role_type against AVAILABLE ROLE TYPES, starting_location against ACTIVE LOCATIONS, and keep every profile field original. Do not copy existing character prose. PREVIOUS JSON: '.json_encode((array)$response['data'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE).'\\nAVAILABLE ROLE TYPES: '.implode(', ',$available).'\\nACTIVE LOCATIONS: '.implode(' | ',$locationNames).'\\nPREFERRED EXPLICIT CANDIDATE: '.json_encode($preferredExplicit,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);$response=AiConnectionService::completeJson('INKY_SUGGEST_CHARACTER',$system,$retryPrompt);$suggestion=self::validatedCharacterSuggestion((array)$response['data'],$available,$locations,$roles,$explicitNames);}return['action'=>'suggest_character','display_name'=>$displayName,'model'=>$response['model'],'character_suggestion'=>$suggestion,'automated_findings'=>['errors'=>count($audit['errors']),'warnings'=>count($audit['warnings'])]];
    }

    private static function previousCharacterSuggestions(array $context): array
    {
        $items=$context['previous_characters']??[];if(!is_array($items))return[];$out=[];foreach(array_slice($items,0,8)as$item){if(!is_array($item))continue;$out[]=['role_code'=>(string)($item['role_code']??''),'name'=>(string)($item['name']??''),'role_type'=>(string)($item['role_type']??''),'active'=>1,'public_description'=>(string)($item['public_description']??''),'private_knowledge'=>(string)($item['private_knowledge']??''),'goal_text'=>(string)($item['goal_text']??''),'fear_text'=>(string)($item['fear_text']??'')];}return$out;
    }

    private static function characterIdentities(array $rows): array
    {
        return array_values(array_map(static fn($row)=>['role_code'=>(string)($row['role_code']??''),'name'=>(string)($row['name']??''),'role_type'=>(string)($row['role_type']??''),'active'=>(int)($row['active']??1)],$rows));
    }

    private static function guideDraftState(int $plotId): array
    {
        $q=db()->prepare('SELECT state_json FROM inky_story_guide_drafts WHERE plot_id=? LIMIT 1');$q->execute([$plotId]);$raw=$q->fetchColumn();if(!is_string($raw)||trim($raw)==='')return[];try{$state=json_decode($raw,true,20,JSON_THROW_ON_ERROR);}catch(Throwable$e){return[];}return is_array($state)?$state:[];
    }

    private static function explicitCharacterCandidates(array $plot,array $workspace,array $guideState,array $context,string $auditText,array $existing): array
    {
        $texts=[];$collect=function(mixed$value)use(&$collect,&$texts):void{if(is_string($value)){if(trim($value)!=='')$texts[]=trim($value);return;}if(!is_array($value))return;foreach($value as$key=>$item){if(is_string($key)&&preg_match('/(?:^|_)(?:id|code|active|count|order|priority|seconds?)$/i',$key))continue;$collect($item);}};$collect($plot);$collect($workspace);$collect($guideState['answers']??[]);$collect($guideState['brief']??[]);$collect($guideState['accepted_proposals']??[]);$collect($context['suggestion_context']??[]);$texts[]=$auditText;
        $normalize=static fn(string$value): string=>function_exists('mb_strtolower')?mb_strtolower(trim((string)preg_replace('/\s+/u',' ',$value))):strtolower(trim((string)preg_replace('/\s+/',' ',$value)));$existingNames=[];foreach($existing as$row){$name=$normalize((string)($row['name']??''));if($name!=='')$existingNames[$name]=true;}$excluded=['a','an','and','at','as','after','are','be','beneath','but','by','can','character','characters','choice','dark','day','event','five','for','from','has','her','his','in','into','is','it','its','journey','location','mystery','of','on','one','or','players','problem','setting','she','six','story','the','their','them','they','this','three','to','tone','two','was','were','what','when','where','who','with','world','writer','you'];foreach($workspace['locations']??[]as$row){foreach(['label_text','location_code']as$field){$name=$normalize((string)($row[$field]??''));if($name!=='')$excluded[$name]=true;}}foreach([$plot['title']??'',$plot['setting_name']??'',$plot['genre']??'',$plot['archetype']??'']as$value){foreach(preg_split('/\s+/u',trim((string)$value))?:[]as$word){$word=$normalize((string)preg_replace('/[^\p{L}\p{N}\']+/u','',$word));if($word!=='')$excluded[$word]=true;}}
        $candidates=[];$add=function(string$name,string$evidence,int$score=1)use(&$add,&$candidates,$normalize,$existingNames,$excluded):void{$name=trim((string)preg_replace('/\s+/u',' ',$name));$name=trim($name," \t\r\n\"'.,:;!?()[]{}");if($name===''||mb_strlen($name)<3||mb_strlen($name)>100)return;$key=$normalize($name);if(isset($existingNames[$key])||isset($excluded[$key]))return;$words=preg_split('/\s+/u',$name)?:[];if(count($words)>3)return;foreach($words as$word){$word=$normalize((string)preg_replace('/[^\p{L}\p{N}\']+/u','',$word));if($word===''||isset($excluded[$word]))return;}if(!isset($candidates[$key]))$candidates[$key]=['name'=>$name,'evidence'=>mb_substr($evidence,0,220),'score'=>0];$candidates[$key]['score']+=$score;};
        foreach($guideState['accepted_proposals']??[]as$key=>$value)if(preg_match('/^character_\d+$/',(string)$key)&&is_array($value))$add((string)($value['name']??''),'accepted guided character proposal',100);
        foreach($texts as$text){if(preg_match_all('/(?<![\p{L}])([A-Z][\p{L}\']{2,}(?:\s+[A-Z][\p{L}\']{2,}){0,2})/u',$text,$matches,PREG_OFFSET_CAPTURE)){foreach($matches[1]as$match){$name=(string)$match[0];$offset=(int)$match[1];$evidence=trim(substr($text,max(0,$offset-70),220));$add($name,$evidence,1);foreach(preg_split('/\s+/u',$name)?:[]as$word)$add($word,$evidence,1);}}}
        $result=array_values($candidates);usort($result,static fn($a,$b)=>$b['score']<=>$a['score']?:strcasecmp($a['name'],$b['name']));return array_slice(array_map(static fn($item)=>['name'=>$item['name'],'evidence'=>$item['evidence']],$result),0,20);
    }

    private static function fullCharacterSuggestionContext(array $plot,array $workspace,array $locations,array $guideState=[],array $requestContext=[],string $auditText=''): string
    {
        $clip=static function(mixed $value,int $limit): mixed{if(!is_scalar($value))return$value;$text=(string)$value;return function_exists('mb_substr')?mb_substr($text,0,$limit):substr($text,0,$limit);};$clipTree=static function(mixed $value,int $limit)use(&$clipTree,$clip): mixed{if(is_array($value)){foreach($value as$key=>$item)$value[$key]=$clipTree($item,$limit);return$value;}return is_string($value)?$clip($value,$limit):$value;};$map=static function(array $rows,int $limit,int $count=30)use($clip): array{$out=[];foreach(array_slice($rows,0,$count)as$row){$item=[];foreach($row as$field=>$value){if(is_int($field))continue;$item[$field]=$clip($value,$limit);} $out[]=$item;}return$out;};$context=['story'=>['title'=>$clip($plot['title']??'',190),'stable_code'=>$clip($plot['code']??'',120),'genre'=>$clip($plot['genre_label']??$plot['genre']??'',120),'archetype'=>$clip($plot['archetype']??'',120),'setting'=>$clip($plot['setting_name']??'',190),'max_players'=>(int)($plot['max_players']??0),'max_npcs'=>(int)($plot['max_npcs']??0),'scenario_briefing'=>$clip($plot['scenario_text']??'',4000)],'guided_answers'=>$clipTree($guideState['answers']??[],4000),'guided_brief'=>$clipTree($guideState['brief']??[],2000),'accepted_guided_proposals'=>$clipTree($guideState['accepted_proposals']??[],1800),'request_context'=>$clipTree($requestContext,1800),'active_locations'=>$map($locations,1800),'characters'=>self::characterIdentities($workspace['characters']??[]),'story_values'=>$map($workspace['state_definitions']??[],1000),'world_events'=>$map($workspace['events']??[],1800),'decisions'=>$map($workspace['decisions']??[],1800),'decision_options'=>$map($workspace['options']??[],1400),'effects'=>$map($workspace['effects']??[],1200),'conditional_opportunities'=>$map($workspace['opportunities']??[],1800),'opportunity_conditions'=>$map($workspace['dynamic_conditions']??[],1200),'opportunity_options'=>$map($workspace['dynamic_options']??[],1400),'opportunity_effects'=>$map($workspace['dynamic_effects']??[],1200),'ending_sections'=>$map($workspace['story_sections']??[],1000),'endings'=>$map($workspace['resolutions']??[],1800),'ending_conditions'=>$map($workspace['resolution_conditions']??[],1200),'full_review_package'=>$clip($auditText,6000)];$json=json_encode($context,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);return$json===false?'{}':$json;
    }

    private static function characterSuggestionContext(array $plot,array $workspace,array $locations,array $guideState=[],array $requestContext=[],string $auditText=''): string
    {
        $context=json_decode(self::fullCharacterSuggestionContext($plot,$workspace,$locations,$guideState,$requestContext,$auditText),true);if(!is_array($context))return'{}';unset($context['full_review_package'],$context['active_locations'],$context['characters']);$compact=function(mixed$value)use(&$compact):mixed{if(is_array($value)){if(function_exists('array_is_list')&&array_is_list($value))$value=array_slice($value,0,24);$out=[];foreach($value as$key=>$item){if(is_string($key)&&preg_match('/(?:^|_)(?:id|active|sort_order|created_at|updated_at|author_user_id)$/i',$key))continue;$out[$key]=$compact($item);}return$out;}if(!is_string($value))return$value;return function_exists('mb_substr')?mb_substr($value,0,260):substr($value,0,260);};$context=$compact($context);$json=json_encode($context,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);return$json===false?'{}':$json;
    }

    private static function reviewPlot(int $plotId,string $displayName): array
    {
        $audit=StoryAuthoringService::audit($plotId);
        $system='You are '.$displayName.', a concise co-writer reviewing an authored interactive story. Use only the supplied story package. Separate observed evidence from inference. Every finding and recommendation must cite a named stable key from the package. Review immersion, viewpoint continuity, creative prose, character agency, branch causality, dependency coverage, reachability, pacing, replayability, mobile readability, and whether each character receives enough non-privileged information for meaningful choices. Never recommend plot-specific PHP. Do not propose chronology or structural-order changes unless the supplied evidence demonstrates a defect; label any such recommendation MANUAL STRUCTURAL REVIEW. Prefer prose and authored-data corrections. Unobserved simulation options are not proof of unreachability. Return JSON only with this exact shape: {"summary":"one concise paragraph","observed_evidence":[{"stable_key":"KEY","finding":"fact directly supported by the package"}],"inferences":[{"stable_key":"KEY","inference":"clearly labeled interpretation"}],"recommendations":[{"stable_key":"KEY","category":"one review category","recommendation":"specific copy-ready improvement","manual_structural_review":false}]}. Keep each item short enough to read on a phone. Return no markdown and no additional keys.';
        $response=AiConnectionService::completeJson('INKY_REVIEW_PLOT',$system,$audit['text']);$review=self::validatedReview((array)$response['data']);return['action'=>'review_plot','display_name'=>$displayName,'model'=>$response['model'],'review'=>$review,'automated_findings'=>['errors'=>count($audit['errors']),'warnings'=>count($audit['warnings'])]];
    }

    private static function whatsNext(int $plotId,string $displayName): array
    {
        $audit=StoryAuthoringService::audit($plotId);
        $system='You are '.$displayName.', a concise co-writer helping an author decide the next best Story Writer tasks. Use only the supplied story package. Return advice only; do not rewrite the story or claim changes were made. Every step must cite one named stable key from the package. Prioritize blocking validation errors, then missing authored data, then high-value prose or dependency improvements. Separate direct evidence from inference inside the reason text. Never recommend plot-specific PHP. Do not propose chronology or structural-order changes unless the supplied evidence demonstrates a defect; label any such step MANUAL STRUCTURAL REVIEW. Prefer prose and authored-data corrections. Return JSON only with this exact shape: {"summary":"one concise paragraph","steps":[{"stable_key":"KEY","priority":"BLOCKER|HIGH|MEDIUM|LOW","action":"specific next authoring task","reason":"brief observed evidence or clearly labeled inference","manual_structural_review":false}]}. Return 3 to 6 steps, keep each item short enough to read on a phone, and return no markdown or additional keys.';
        $response=AiConnectionService::completeJson('INKY_WHATS_NEXT',$system,$audit['text']);$next=self::validatedNextSteps((array)$response['data']);return['action'=>'whats_next','display_name'=>$displayName,'model'=>$response['model'],'next_steps'=>$next,'automated_findings'=>['errors'=>count($audit['errors']),'warnings'=>count($audit['warnings'])]];
    }

    private static function qualityCheck(int $plotId,string $displayName): array
    {
        $audit=StoryAuthoringService::audit($plotId);
        $system='You are '.$displayName.', a concise co-writer performing a quality check on authored interactive-story data. Use only the supplied story package. Return advice only; do not rewrite the story or claim changes were made. Score only these categories: immersion, viewpoint_continuity, character_agency, branch_causality, dependency_coverage, pacing, replayability, mobile_readability. Each category must cite a named stable key from the package, distinguish observed evidence from inference in the note, and include one specific improvement. Never recommend plot-specific PHP. Do not propose chronology or structural-order changes unless the supplied evidence demonstrates a defect; label that item MANUAL STRUCTURAL REVIEW. Return JSON only with this exact shape: {"summary":"one concise paragraph","checks":[{"category":"immersion","score":1,"stable_key":"KEY","note":"observed evidence or labeled inference","improvement":"specific authoring improvement","manual_structural_review":false}]}. Scores are integers from 1 to 5. Return no markdown and no additional keys.';
        $response=AiConnectionService::completeJson('INKY_QUALITY_CHECK',$system,$audit['text']);$quality=self::validatedQualityCheck((array)$response['data']);return['action'=>'quality_check','display_name'=>$displayName,'model'=>$response['model'],'quality_check'=>$quality,'automated_findings'=>['errors'=>count($audit['errors']),'warnings'=>count($audit['warnings'])]];
    }

    private static function dependencyCheck(int $plotId,string $displayName): array
    {
        $audit=StoryAuthoringService::audit($plotId);
        $system='You are '.$displayName.', a concise co-writer finding dependency problems in authored interactive-story data. Use only the supplied story package. Return advice only; do not rewrite the story or claim changes were made. Focus on broken or weak authored dependencies: missing active choices, missing effects, missing conditions, inactive referenced entities, undefined managed story values, unreachable endings, dependency gaps between choices and outcomes, and player-visible engine leakage. Every problem must cite a named stable key from the package and include observed evidence or clearly labeled inference. Never recommend plot-specific PHP. Do not propose chronology or structural-order changes unless the supplied evidence demonstrates a defect; label that item MANUAL STRUCTURAL REVIEW. Return JSON only with this exact shape: {"summary":"one concise paragraph","problems":[{"stable_key":"KEY","severity":"BLOCKER|HIGH|MEDIUM|LOW","problem":"specific dependency issue","evidence":"observed evidence or labeled inference","fix":"specific authored-data fix","manual_structural_review":false}]}. Return no markdown and no additional keys.';
        $response=AiConnectionService::completeJson('INKY_DEPENDENCY_CHECK',$system,$audit['text']);$dependencies=self::validatedDependencyCheck((array)$response['data']);return['action'=>'dependencies','display_name'=>$displayName,'model'=>$response['model'],'dependency_check'=>$dependencies,'automated_findings'=>['errors'=>count($audit['errors']),'warnings'=>count($audit['warnings'])]];
    }

    private static function characterKnowledgeCheck(int $plotId,string $displayName): array
    {
        $audit=StoryAuthoringService::audit($plotId);
        $system='You are '.$displayName.', a concise co-writer checking character knowledge and viewpoint continuity in authored interactive-story data. Use only the supplied story package. Return advice only; do not rewrite the story or claim changes were made. Focus on whether each character has enough non-privileged information for meaningful choices, whether prompts or results expose private knowledge they should not have, whether private knowledge is authored clearly, and whether choices align with stated goals, fears, location, and known facts. Every issue must cite a named stable key from the package and include observed evidence or clearly labeled inference. Never recommend plot-specific PHP. Prefer authored-data and prose fixes. Return JSON only with this exact shape: {"summary":"one concise paragraph","issues":[{"role_code":"ROLE","stable_key":"KEY","severity":"BLOCKER|HIGH|MEDIUM|LOW","issue":"specific knowledge or viewpoint issue","evidence":"observed evidence or labeled inference","fix":"specific authored-data or prose fix"}]}. Return no markdown and no additional keys.';
        $response=AiConnectionService::completeJson('INKY_CHARACTER_KNOWLEDGE',$system,$audit['text']);$knowledge=self::validatedCharacterKnowledge((array)$response['data']);return['action'=>'review_characters','display_name'=>$displayName,'model'=>$response['model'],'character_knowledge'=>$knowledge,'automated_findings'=>['errors'=>count($audit['errors']),'warnings'=>count($audit['warnings'])]];
    }

    private static function storyValueSuggestions(int $plotId,string $displayName): array
    {
        $audit=StoryAuthoringService::audit($plotId);
        $system='You are '.$displayName.', a concise co-writer suggesting managed Story Framework values for authored interactive-story data. Use only the supplied story package. Return advice only; do not rewrite the story, claim changes were made, or propose PHP/schema changes. Suggest only database-authored story values that belong in Story Framework: FACTOR, STAT, LOCATION, KNOWLEDGE, RELATIONSHIP, WORLD_STATE, GOAL, or FEAR. Focus on values that would clarify choice effects, dynamic conditions, ending conditions, character knowledge, and replayable branch outcomes. Every suggestion must cite a named stable key from the package and include observed evidence or clearly labeled inference. Use short stable value codes. Return JSON only with this exact shape: {"summary":"one concise paragraph","suggestions":[{"stable_key":"KEY","scope":"WORLD_STATE","target_code":"SHORT_STABLE_CODE","label":"human-readable label","value_type":"NUMERIC|PRESET|TEXT","allowed_values":["VALUE"],"reason":"observed evidence or labeled inference","example_use":"where the author could use it"}]}. Return no markdown and no additional keys.';
        $response=AiConnectionService::completeJson('INKY_STORY_VALUES',$system,$audit['text']);$values=self::validatedStoryValues((array)$response['data']);return['action'=>'story_values','display_name'=>$displayName,'model'=>$response['model'],'story_values'=>$values,'automated_findings'=>['errors'=>count($audit['errors']),'warnings'=>count($audit['warnings'])]];
    }

    private static function opportunityReview(int $plotId,string $displayName,array $context=[]): array
    {
        $audit=StoryAuthoringService::audit($plotId);
        $system='You are '.$displayName.', a concise co-writer reviewing authored decision opportunities in an interactive story. Use only the supplied story package. Return advice only; do not rewrite the story, claim changes were made, or propose PHP/schema changes. Review world-event decisions and conditional opportunities for prompt clarity, player agency, option contrast, visible consequences, AI rationale guidance, condition/effect fit, character knowledge, and mobile readability. Every item must cite a named stable key from the package, identify the opportunity code being reviewed, and include observed evidence or clearly labeled inference. A seeded focused opportunity test proves local branch behavior only, not organic reachability. Return JSON only with this exact shape: {"summary":"one concise paragraph","opportunities":[{"opportunity_code":"EVENT_OR_OPPORTUNITY","stable_key":"KEY","severity":"BLOCKER|HIGH|MEDIUM|LOW","finding":"specific opportunity issue or strength","recommendation":"specific authored-data or prose improvement","focused_test":"optional focused validation idea"}]}. Return 3 to 8 items, keep each item short enough to read on a phone, and return no markdown or additional keys.';
        $response=AiConnectionService::completeJson('INKY_OPPORTUNITY_REVIEW',$system,$audit['text']);$review=self::validatedOpportunityReview((array)$response['data']);return['action'=>'review_opportunities','display_name'=>$displayName,'model'=>$response['model'],'opportunity_review'=>$review,'automated_findings'=>['errors'=>count($audit['errors']),'warnings'=>count($audit['warnings'])]];
    }

    private static function focusedTestPlan(int $plotId,string $displayName): array
    {
        $audit=StoryAuthoringService::audit($plotId);
        $system='You are '.$displayName.', a concise co-writer preparing a focused validation plan for authored interactive-story data. Use only the supplied story package. Return advice only; do not claim tests were run, queue jobs, rewrite the story, or propose PHP/schema changes. Suggest tests that an author or administrator can run with existing Toinky validation and Game2 Simulation Lab workflows. Prefer focused-opportunity tests for dynamic opportunities and branch-risk areas, but include broad simulations or manual smoke checks when the evidence warrants them. A seeded focused opportunity test proves local branch behavior from a manufactured checkpoint and does not prove organic reachability from story start. Every test must cite a named stable key from the package and include observed evidence or clearly labeled inference. Return JSON only with this exact shape: {"summary":"one concise paragraph","tests":[{"stable_key":"KEY","test_type":"VALIDATION|FOCUSED_OPPORTUNITY|BROAD_SIMULATION|MANUAL_SMOKE","focus_code":"OPPORTUNITY_OR_AREA","runs":10,"reason":"observed evidence or labeled inference","expected_evidence":"what passing evidence should show"}]}. Return 3 to 6 tests, keep each item short enough to read on a phone, and return no markdown or additional keys.';
        $response=AiConnectionService::completeJson('INKY_FOCUSED_TESTS',$system,$audit['text']);$tests=self::validatedFocusedTests((array)$response['data']);return['action'=>'focused_tests','display_name'=>$displayName,'model'=>$response['model'],'focused_tests'=>$tests,'automated_findings'=>['errors'=>count($audit['errors']),'warnings'=>count($audit['warnings'])]];
    }

    private static function normalizeCharacterSuggestionLocation(array $input,array $locations): array
    {
        $character=is_array($input['character']??null)?$input['character']:$input;$raw=trim((string)($character['starting_location']??$character['location']??''));$labels=[];$codes=[];
        foreach($locations as$location){if(is_array($location)){$label=trim((string)($location['label_text']??''));if($label!==''){$labels[]=$label;$code=strtoupper(trim((string)($location['location_code']??'')));if($code!=='')$codes[$code]=$label;}}else{$label=trim((string)$location);if($label!=='')$labels[]=$label;}}
        if(!$labels)return$input;$match=$codes[strtoupper($raw)]??null;if($match===null)foreach($labels as$label)if(strcasecmp($label,$raw)===0){$match=$label;break;}
        $key=static fn(string$value): string=>(string)(preg_replace('/[^a-z0-9]+/i','',strtolower(trim($value)))??'');if($match===null&&$key($raw)!=='')foreach($labels as$label){$labelKey=$key($label);if($labelKey===$key($raw)||str_contains($labelKey,$key($raw))||str_contains($key($raw),$labelKey)){$match=$label;break;}}
        $match??=$labels[0];if(is_array($input['character']??null))$input['character']['starting_location']=$match;else{$input['starting_location']=$match;if(array_key_exists('location',$input))$input['location']=$match;}return$input;
    }

    private static function validatedCharacterSuggestion(array $input,array $available,array $locationNames,array $existing,array $preferredNames=[]): array
    {
        $input=self::normalizeCharacterSuggestionLocation($input,$locationNames);
        $character=is_array($input['character']??null)?$input['character']:$input;$text=static function(mixed $value,int $max,bool $required=true): string{$value=trim((string)$value);$value=function_exists('mb_substr')?mb_substr($value,0,$max):substr($value,0,$max);if($required&&$value==='')throw new RuntimeException('Inky’s character suggestion was incomplete.');return$value;};$name=$text($character['name']??'',120);if($preferredNames){$normalizedName=mb_strtolower(trim($name));$matches=array_filter($preferredNames,static fn($candidate)=>mb_strtolower(trim((string)$candidate))===$normalizedName);if(!$matches)throw new RuntimeException('Inky did not prioritize an explicitly identified character from the draft.');}$roleCode=self::stableCode((string)($character['role_code']??$name));if($roleCode==='')throw new RuntimeException('Inky’s character suggestion did not include a stable role code.');$roleType=strtoupper(trim((string)($character['role_type']??'')));$roleType=['AI CHARACTER'=>'NPC','AI'=>'NPC','HUMAN'=>'PLAYER'][$roleType]??$roleType;if(!in_array($roleType,$available,true))throw new RuntimeException('Inky suggested a character type that has no available Story Framework slot.');$locationLabels=[];$locationCodes=[];foreach($locationNames as$location){if(is_array($location)){$label=$text($location['label_text']??'',190);$locationLabels[]=$label;$locationCodes[strtoupper((string)($location['location_code']??''))]=$label;}else{$locationLabels[]=$text($location,190);}}$startingLocation=$text($character['starting_location']??$character['location']??'',190);$startingLocation=$locationCodes[strtoupper($startingLocation)]??$startingLocation;$matches=array_values(array_filter($locationLabels,static fn($label)=>strcasecmp($label,$startingLocation)===0));if($matches)$startingLocation=$matches[0];if(!in_array($startingLocation,$locationLabels,true))throw new RuntimeException('Inky suggested a starting location that is not active in Story Framework.');foreach($existing as$row)if(strcasecmp($roleCode,(string)$row['role_code'])===0||strcasecmp($name,(string)$row['name'])===0)throw new RuntimeException('Inky suggested an existing character instead of a new one.');$fieldValues=['public_description'=>$text($character['public_description']??$character['description']??'',1000),'private_knowledge'=>$text($character['private_knowledge']??$character['private']??'',1000,false),'goal_text'=>$text($character['goal_text']??$character['goal']??'',500,false),'fear_text'=>$text($character['fear_text']??$character['fear']??'',500,false)];foreach($existing as$row)foreach($fieldValues as$field=>$candidateValue){$candidate=mb_strtolower(trim($candidateValue));$previous=mb_strtolower(trim((string)($row[$field]??'')));if($candidate!==''&&$previous!==''&&$candidate===$previous)throw new RuntimeException('Inky reused an existing character profile. Please request the character again.');}$pronoun=strtoupper(trim((string)($character['pronoun_set']??$character['pronouns']??'IT')));if(!in_array($pronoun,['HE','SHE','IT'],true))$pronoun='IT';$summary=$text($input['summary']??'A new character that fits the current story package.',300);return['summary'=>$summary,'character'=>['role_code'=>$roleCode,'role_type'=>$roleType,'mystery_character'=>!empty($character['mystery_character']),'name'=>$name,'public_description'=>$fieldValues['public_description'],'private_knowledge'=>$fieldValues['private_knowledge'],'starting_location'=>$startingLocation,'goal_text'=>$fieldValues['goal_text'],'fear_text'=>$fieldValues['fear_text'],'pronoun_set'=>$pronoun]];
    }

    private static function validatedReview(array $input): array
    {
        $summary=trim((string)($input['summary']??''));if($summary==='')throw new RuntimeException('Inky’s review did not include a summary.');
        $observed=self::items($input['observed_evidence']??null,'finding',false);$inferences=self::items($input['inferences']??null,'inference',false);$recommendations=self::items($input['recommendations']??null,'recommendation',true);if(!$observed||!$recommendations)throw new RuntimeException('Inky’s review did not include the required evidence and recommendations.');
        return['summary'=>$summary,'observed_evidence'=>$observed,'inferences'=>$inferences,'recommendations'=>$recommendations];
    }

    private static function validatedNextSteps(array $input): array
    {
        $summary=trim((string)($input['summary']??''));if($summary==='')throw new RuntimeException('Inky’s next-step guidance did not include a summary.');
        $steps=self::nextStepItems($input['steps']??null);if(!$steps)throw new RuntimeException('Inky’s next-step guidance did not include usable steps.');
        return['summary'=>$summary,'steps'=>$steps];
    }

    private static function validatedQualityCheck(array $input): array
    {
        $summary=trim((string)($input['summary']??''));if($summary==='')throw new RuntimeException('Inky’s quality check did not include a summary.');
        $checks=self::qualityCheckItems($input['checks']??null);if(!$checks)throw new RuntimeException('Inky’s quality check did not include usable checks.');
        return['summary'=>$summary,'checks'=>$checks];
    }

    private static function validatedDependencyCheck(array $input): array
    {
        $summary=trim((string)($input['summary']??''));if($summary==='')throw new RuntimeException('Inky’s dependency check did not include a summary.');
        $problems=self::dependencyItems($input['problems']??null);if(!$problems)throw new RuntimeException('Inky’s dependency check did not include usable problems.');
        return['summary'=>$summary,'problems'=>$problems];
    }

    private static function validatedCharacterKnowledge(array $input): array
    {
        $summary=trim((string)($input['summary']??''));if($summary==='')throw new RuntimeException('Inky’s character-knowledge check did not include a summary.');
        $issues=self::characterKnowledgeItems($input['issues']??null);if(!$issues)throw new RuntimeException('Inky’s character-knowledge check did not include usable issues.');
        return['summary'=>$summary,'issues'=>$issues];
    }

    private static function validatedStoryValues(array $input): array
    {
        $summary=trim((string)($input['summary']??''));if($summary==='')throw new RuntimeException('Inky’s story-value suggestions did not include a summary.');
        $suggestions=self::storyValueItems($input['suggestions']??null);if(!$suggestions)throw new RuntimeException('Inky’s story-value suggestions did not include usable suggestions.');
        return['summary'=>$summary,'suggestions'=>$suggestions];
    }

    private static function validatedOpportunityReview(array $input): array
    {
        $summary=trim((string)($input['summary']??''));if($summary==='')throw new RuntimeException('Inky’s opportunity review did not include a summary.');
        $opportunities=self::opportunityItems($input['opportunities']??null);if(!$opportunities)throw new RuntimeException('Inky’s opportunity review did not include usable items.');
        return['summary'=>$summary,'opportunities'=>$opportunities];
    }

    private static function validatedFocusedTests(array $input): array
    {
        $summary=trim((string)($input['summary']??''));if($summary==='')throw new RuntimeException('Inky’s focused-test plan did not include a summary.');
        $tests=self::focusedTestItems($input['tests']??null);if(!$tests)throw new RuntimeException('Inky’s focused-test plan did not include usable tests.');
        return['summary'=>$summary,'tests'=>$tests];
    }

    private static function items(mixed $rows,string $textKey,bool $recommendation): array
    {
        if(!is_array($rows))return[];$out=[];foreach(array_slice($rows,0,10)as$row){if(!is_array($row))continue;$key=trim((string)($row['stable_key']??''));$text=trim((string)($row[$textKey]??''));if($key===''||$text==='')continue;$item=['stable_key'=>$key,$textKey=>$text];if($recommendation){$item['category']=trim((string)($row['category']??'Story quality'));$item['manual_structural_review']=!empty($row['manual_structural_review']);}$out[]=$item;}return$out;
    }

    private static function nextStepItems(mixed $rows): array
    {
        if(!is_array($rows))return[];$priorities=['BLOCKER','HIGH','MEDIUM','LOW'];$out=[];foreach($rows as$row){if(!is_array($row))continue;$key=trim((string)($row['stable_key']??''));$action=trim((string)($row['action']??''));$reason=trim((string)($row['reason']??''));if($key===''||$action===''||$reason==='')continue;$priority=strtoupper(trim((string)($row['priority']??'MEDIUM')));if(!in_array($priority,$priorities,true))$priority='MEDIUM';$out[]=['stable_key'=>$key,'priority'=>$priority,'action'=>$action,'reason'=>$reason,'manual_structural_review'=>!empty($row['manual_structural_review'])];if(count($out)>=6)break;}return$out;
    }

    private static function qualityCheckItems(mixed $rows): array
    {
        if(!is_array($rows))return[];$allowed=['immersion','viewpoint_continuity','character_agency','branch_causality','dependency_coverage','pacing','replayability','mobile_readability'];$out=[];foreach($rows as$row){if(!is_array($row))continue;$category=strtolower(trim((string)($row['category']??'')));$key=trim((string)($row['stable_key']??''));$note=trim((string)($row['note']??''));$improvement=trim((string)($row['improvement']??''));if(!in_array($category,$allowed,true)||$key===''||$note===''||$improvement==='')continue;$score=max(1,min(5,(int)($row['score']??3)));$out[]=['category'=>$category,'score'=>$score,'stable_key'=>$key,'note'=>$note,'improvement'=>$improvement,'manual_structural_review'=>!empty($row['manual_structural_review'])];if(count($out)>=8)break;}return$out;
    }

    private static function dependencyItems(mixed $rows): array
    {
        if(!is_array($rows))return[];$severities=['BLOCKER','HIGH','MEDIUM','LOW'];$out=[];foreach($rows as$row){if(!is_array($row))continue;$key=trim((string)($row['stable_key']??''));$problem=trim((string)($row['problem']??''));$evidence=trim((string)($row['evidence']??''));$fix=trim((string)($row['fix']??''));if($key===''||$problem===''||$evidence===''||$fix==='')continue;$severity=strtoupper(trim((string)($row['severity']??'MEDIUM')));if(!in_array($severity,$severities,true))$severity='MEDIUM';$out[]=['stable_key'=>$key,'severity'=>$severity,'problem'=>$problem,'evidence'=>$evidence,'fix'=>$fix,'manual_structural_review'=>!empty($row['manual_structural_review'])];if(count($out)>=8)break;}return$out;
    }

    private static function characterKnowledgeItems(mixed $rows): array
    {
        if(!is_array($rows))return[];$severities=['BLOCKER','HIGH','MEDIUM','LOW'];$out=[];foreach($rows as$row){if(!is_array($row))continue;$role=self::stableCode((string)($row['role_code']??''));$key=trim((string)($row['stable_key']??''));$issue=trim((string)($row['issue']??''));$evidence=trim((string)($row['evidence']??''));$fix=trim((string)($row['fix']??''));if($role===''||$key===''||$issue===''||$evidence===''||$fix==='')continue;$severity=strtoupper(trim((string)($row['severity']??'MEDIUM')));if(!in_array($severity,$severities,true))$severity='MEDIUM';$out[]=['role_code'=>$role,'stable_key'=>$key,'severity'=>$severity,'issue'=>$issue,'evidence'=>$evidence,'fix'=>$fix];if(count($out)>=8)break;}return$out;
    }

    private static function storyValueItems(mixed $rows): array
    {
        if(!is_array($rows))return[];$scopes=['FACTOR','STAT','LOCATION','KNOWLEDGE','RELATIONSHIP','WORLD_STATE','GOAL','FEAR'];$types=['NUMERIC','PRESET','TEXT'];$out=[];foreach($rows as$row){if(!is_array($row))continue;$key=trim((string)($row['stable_key']??''));$scope=strtoupper(trim((string)($row['scope']??'WORLD_STATE')));$code=self::stableCode((string)($row['target_code']??''));$label=trim((string)($row['label']??''));$type=strtoupper(trim((string)($row['value_type']??'TEXT')));$reason=trim((string)($row['reason']??''));$example=trim((string)($row['example_use']??''));if(!in_array($scope,$scopes,true))$scope='WORLD_STATE';if(!in_array($type,$types,true))$type='TEXT';if($key===''||$code===''||$label===''||$reason===''||$example==='')continue;$allowed=[];foreach((array)($row['allowed_values']??[])as$value){$value=trim((string)$value);if($value!==''&&!in_array($value,$allowed,true))$allowed[]=$value;if(count($allowed)>=8)break;}if($type==='PRESET'&&!$allowed)continue;$out[]=['stable_key'=>$key,'scope'=>$scope,'target_code'=>$code,'label'=>$label,'value_type'=>$type,'allowed_values'=>$type==='PRESET'?$allowed:[],'reason'=>$reason,'example_use'=>$example];if(count($out)>=8)break;}return$out;
    }

    private static function opportunityItems(mixed $rows): array
    {
        if(!is_array($rows))return[];$severities=['BLOCKER','HIGH','MEDIUM','LOW'];$out=[];foreach($rows as$row){if(!is_array($row))continue;$opportunity=self::stableCode((string)($row['opportunity_code']??''));$key=trim((string)($row['stable_key']??''));$finding=trim((string)($row['finding']??''));$recommendation=trim((string)($row['recommendation']??''));if($opportunity===''||$key===''||$finding===''||$recommendation==='')continue;$severity=strtoupper(trim((string)($row['severity']??'MEDIUM')));if(!in_array($severity,$severities,true))$severity='MEDIUM';$out[]=['opportunity_code'=>$opportunity,'stable_key'=>$key,'severity'=>$severity,'finding'=>$finding,'recommendation'=>$recommendation,'focused_test'=>trim((string)($row['focused_test']??''))];if(count($out)>=8)break;}return$out;
    }

    private static function focusedTestItems(mixed $rows): array
    {
        if(!is_array($rows))return[];$types=['VALIDATION','FOCUSED_OPPORTUNITY','BROAD_SIMULATION','MANUAL_SMOKE'];$out=[];foreach($rows as$row){if(!is_array($row))continue;$key=trim((string)($row['stable_key']??''));$type=strtoupper(trim((string)($row['test_type']??'VALIDATION')));$focus=self::stableCode((string)($row['focus_code']??''));$reason=trim((string)($row['reason']??''));$expected=trim((string)($row['expected_evidence']??''));if(!in_array($type,$types,true))$type='VALIDATION';if($key===''||$focus===''||$reason===''||$expected==='')continue;$runs=max(1,min(50,(int)($row['runs']??10)));if($type==='VALIDATION'||$type==='MANUAL_SMOKE')$runs=1;elseif($type==='BROAD_SIMULATION')$runs=max(10,min(250,(int)($row['runs']??50)));$out[]=['stable_key'=>$key,'test_type'=>$type,'focus_code'=>$focus,'runs'=>$runs,'reason'=>$reason,'expected_evidence'=>$expected];if(count($out)>=6)break;}return$out;
    }

    private static function targetFocus(array $context): array
    {
        $type=strtolower(trim((string)($context['target_type']??'')));if(!in_array($type,['decision','opportunity'],true))$type='';
        $code=self::stableCode((string)($context['target_code']??''));return['type'=>$type,'code'=>$code];
    }

    private static function stableCode(string $value): string
    {
        return trim(strtoupper((string)preg_replace('/[^A-Za-z0-9]+/','_',trim($value))),'_');
    }
}
