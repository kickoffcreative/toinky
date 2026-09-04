<?php
require_once __DIR__.'/StoryAuthoringService.php';
require_once __DIR__.'/AiConnectionService.php';
require_once __DIR__.'/UserProfileService.php';

final class InkyCowriterService
{
    public static function run(array $writer,int $plotId,string $action,array $context=[]): array
    {
        if(!StoryAuthoringService::canEdit($writer,$plotId))throw new RuntimeException('You may only ask Inky to review stories you can edit.');$displayName=UserProfileService::inkyDisplayName(UserProfileService::inkyPreferences((int)$writer['user_id']));
        return match($action){'review_plot'=>self::reviewPlot($plotId,$displayName),'whats_next'=>self::whatsNext($plotId,$displayName),'quality_check'=>self::qualityCheck($plotId,$displayName),'dependencies'=>self::dependencyCheck($plotId,$displayName),'suggest_character'=>self::suggestCharacter($plotId,$displayName),'character_knowledge'=>self::characterKnowledgeCheck($plotId,$displayName),'story_values'=>self::storyValueSuggestions($plotId,$displayName),'review_opportunity'=>self::opportunityReview($plotId,$displayName,$context),'focused_tests'=>self::focusedTestPlan($plotId,$displayName),default=>throw new RuntimeException('That Inky action is not available yet.')};
    }

    private static function suggestCharacter(int $plotId,string $displayName): array
    {
        $plot=StoryAuthoringService::story($plotId);$workspace=StoryAuthoringService::workspace($plotId);$roles=$workspace['characters']??[];$locations=array_values(array_filter($workspace['locations']??[],static fn($row)=>(int)($row['active']??1)===1));$playerCount=count(array_filter($roles,static fn($row)=>(string)($row['role_type']??'')==='PLAYER'));$npcCount=count(array_filter($roles,static fn($row)=>(string)($row['role_type']??'')==='NPC'));$available=[];if($playerCount<(int)$plot['max_players'])$available[]='PLAYER';if($npcCount<(int)$plot['max_npcs'])$available[]='NPC';if(!$available)throw new RuntimeException('The Story Framework character limits are already full.');if(!$locations)throw new RuntimeException('Add an active Story Framework location before suggesting a character.');
        $audit=StoryAuthoringService::audit($plotId);$locationNames=array_values(array_map(static fn($row)=>(string)$row['label_text'],$locations));$existing=array_values(array_map(static fn($row)=>['role_code'=>(string)$row['role_code'],'name'=>(string)$row['name'],'role_type'=>(string)$row['role_type'],'active'=>(int)($row['active']??1)],$roles));$system='You are '.$displayName.', a patient story coach suggesting exactly one additional character for an amateur interactive-fiction writer. Use only the supplied story context. Respect the available Story Framework slots and existing entity identities. Choose one role_type from the supplied available_role_types, never duplicate an existing role code or character name, and choose starting_location exactly from the supplied active_locations. The new character must be original. Do not copy, paraphrase, or reuse any existing character profile, description, private knowledge, goal, fear, or other character-specific prose. Existing character identities are constraints only, not source material. The suggestion must be a useful, playable addition that fits the story brief without revealing or inventing engine rules. Return JSON only with this exact shape: {"summary":"one concise reason","character":{"role_code":"STABLE_CODE","role_type":"PLAYER|NPC","mystery_character":false,"name":"Name","public_description":"player-visible description","private_knowledge":"optional private knowledge","starting_location":"EXACT ACTIVE LOCATION","goal_text":"character goal","fear_text":"optional fear","pronoun_set":"HE|SHE|IT"}}. Keep prose concise, return no markdown, and return no additional keys.';$prompt='AVAILABLE ROLE TYPES: '.implode(', ',$available)."\nACTIVE LOCATIONS: ".implode(' | ',$locationNames)."\nEXISTING CHARACTER IDENTITIES (DO NOT COPY THEIR PROSE): ".json_encode($existing,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\nSTORY CONTEXT (EXCLUDES EXISTING CHARACTER PROFILES):\n".self::characterSuggestionContext($plot,$workspace,$locations);$response=AiConnectionService::completeJson('INKY_SUGGEST_CHARACTER',$system,$prompt);try{$suggestion=self::validatedCharacterSuggestion((array)$response['data'],$available,$locationNames,$roles);}catch(RuntimeException $error){$retryPrompt=$prompt."\n\nRETRY REQUIREMENT: The previous candidate failed validation. Generate a completely different original character. Recheck every role code, name, active location, and authored field before returning JSON. Never reuse any prose from an existing character identity.";$response=AiConnectionService::completeJson('INKY_SUGGEST_CHARACTER',$system,$retryPrompt);$suggestion=self::validatedCharacterSuggestion((array)$response['data'],$available,$locationNames,$roles);}return['action'=>'suggest_character','display_name'=>$displayName,'model'=>$response['model'],'character_suggestion'=>$suggestion,'automated_findings'=>['errors'=>count($audit['errors']),'warnings'=>count($audit['warnings'])]];
    }

    private static function characterSuggestionContext(array $plot,array $workspace,array $locations): string
    {
        $clip=static function(mixed $value,int $limit): mixed{if(!is_scalar($value))return$value;$text=(string)$value;return function_exists('mb_substr')?mb_substr($text,0,$limit):substr($text,0,$limit);};$map=static function(array $rows,array $fields,int $limit,int $count=15)use($clip): array{$out=[];foreach(array_slice($rows,0,$count)as$row){$item=[];foreach($fields as$field)if(array_key_exists($field,$row))$item[$field]=$clip($row[$field],$limit);$out[]=$item;}return$out;};$context=['story'=>['title'=>$clip($plot['title']??'',190),'stable_code'=>$clip($plot['code']??'',120),'genre'=>$clip($plot['genre_label']??$plot['genre']??'',120),'setting'=>$clip($plot['setting_name']??$plot['setting']??'',190),'scenario_briefing'=>$clip($plot['scenario_text']??'',1800),'tone'=>$clip($plot['tone']??'',300)],'active_locations'=>$map($locations,['location_code','label_text','description'],500,12),'story_values'=>$map($workspace['state_definitions']??[],['effect_scope','target_code','label_text','value_type','allowed_values_text','default_numeric_value'],220,15),'world_events'=>$map($workspace['events']??[],['event_code','phase','headline','event_second','sort_order'],300,15),'decisions'=>$map($workspace['decisions']??[],['event_code','role_code','prompt_text','decision_window_seconds','option_count'],350,15),'decision_options'=>$map($workspace['options']??[],['event_code','role_code','option_code','label'],220,20),'conditional_opportunities'=>$map($workspace['opportunities']??[],['opportunity_code','role_code','prompt_text'],350,15),'ending_sections'=>$map($workspace['story_sections']??[],['section_code','label_text'],180,10),'endings'=>$map($workspace['resolutions']??[],['section_code','headline','priority'],220,12)];$json=json_encode($context,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);return$json===false?'{}':$json;
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
        $response=AiConnectionService::completeJson('INKY_CHARACTER_KNOWLEDGE',$system,$audit['text']);$knowledge=self::validatedCharacterKnowledge((array)$response['data']);return['action'=>'character_knowledge','display_name'=>$displayName,'model'=>$response['model'],'character_knowledge'=>$knowledge,'automated_findings'=>['errors'=>count($audit['errors']),'warnings'=>count($audit['warnings'])]];
    }

    private static function storyValueSuggestions(int $plotId,string $displayName): array
    {
        $audit=StoryAuthoringService::audit($plotId);
        $system='You are '.$displayName.', a concise co-writer suggesting managed Story Framework values for authored interactive-story data. Use only the supplied story package. Return advice only; do not rewrite the story, claim changes were made, or propose PHP/schema changes. Suggest only database-authored story values that belong in Story Framework: FACTOR, STAT, LOCATION, KNOWLEDGE, RELATIONSHIP, WORLD_STATE, GOAL, or FEAR. Focus on values that would clarify choice effects, dynamic conditions, ending conditions, character knowledge, and replayable branch outcomes. Every suggestion must cite a named stable key from the package and include observed evidence or clearly labeled inference. Use short stable value codes. Return JSON only with this exact shape: {"summary":"one concise paragraph","suggestions":[{"stable_key":"KEY","scope":"WORLD_STATE","target_code":"SHORT_STABLE_CODE","label":"human-readable label","value_type":"NUMERIC|PRESET|TEXT","allowed_values":["VALUE"],"reason":"observed evidence or labeled inference","example_use":"where the author could use it"}]}. Return no markdown and no additional keys.';
        $response=AiConnectionService::completeJson('INKY_STORY_VALUES',$system,$audit['text']);$values=self::validatedStoryValues((array)$response['data']);return['action'=>'story_values','display_name'=>$displayName,'model'=>$response['model'],'story_values'=>$values,'automated_findings'=>['errors'=>count($audit['errors']),'warnings'=>count($audit['warnings'])]];
    }

    private static function opportunityReview(int $plotId,string $displayName,array $context=[]): array
    {
        $audit=StoryAuthoringService::audit($plotId);$target=self::targetFocus($context);
        $system='You are '.$displayName.', a concise co-writer reviewing authored decision opportunities in an interactive story. Use only the supplied story package. Return advice only; do not rewrite the story, claim changes were made, or propose PHP/schema changes. Review world-event decisions and conditional opportunities for prompt clarity, player agency, option contrast, visible consequences, AI rationale guidance, condition/effect fit, character knowledge, and mobile readability. Every item must cite a named stable key from the package, identify the opportunity code being reviewed, and include observed evidence or clearly labeled inference. A seeded focused opportunity test proves local branch behavior only, not organic reachability. Return JSON only with this exact shape: {"summary":"one concise paragraph","opportunities":[{"opportunity_code":"EVENT_OR_OPPORTUNITY","stable_key":"KEY","severity":"BLOCKER|HIGH|MEDIUM|LOW","finding":"specific opportunity issue or strength","recommendation":"specific authored-data or prose improvement","focused_test":"optional focused validation idea"}]}. Return 3 to 8 items, keep each item short enough to read on a phone, and return no markdown or additional keys.';
        $prompt=$target['type']!==''&&$target['code']!==''?'REQUESTED FOCUS: '.$target['type'].' '.$target['code']."\nIf that exact record appears in the story package, prioritize it before broader opportunity advice. If it is not found, say so in the summary and continue with the closest stable-key evidence.\n\n".$audit['text']:$audit['text'];
        $response=AiConnectionService::completeJson('INKY_OPPORTUNITY_REVIEW',$system,$prompt);$review=self::validatedOpportunityReview((array)$response['data']);return['action'=>'review_opportunity','display_name'=>$displayName,'model'=>$response['model'],'target_focus'=>$target,'opportunity_review'=>$review,'automated_findings'=>['errors'=>count($audit['errors']),'warnings'=>count($audit['warnings'])]];
    }

    private static function focusedTestPlan(int $plotId,string $displayName): array
    {
        $audit=StoryAuthoringService::audit($plotId);
        $system='You are '.$displayName.', a concise co-writer preparing a focused validation plan for authored interactive-story data. Use only the supplied story package. Return advice only; do not claim tests were run, queue jobs, rewrite the story, or propose PHP/schema changes. Suggest tests that an author or administrator can run with existing Toinky validation and Game2 Simulation Lab workflows. Prefer focused-opportunity tests for dynamic opportunities and branch-risk areas, but include broad simulations or manual smoke checks when the evidence warrants them. A seeded focused opportunity test proves local branch behavior from a manufactured checkpoint and does not prove organic reachability from story start. Every test must cite a named stable key from the package and include observed evidence or clearly labeled inference. Return JSON only with this exact shape: {"summary":"one concise paragraph","tests":[{"stable_key":"KEY","test_type":"VALIDATION|FOCUSED_OPPORTUNITY|BROAD_SIMULATION|MANUAL_SMOKE","focus_code":"OPPORTUNITY_OR_AREA","runs":10,"reason":"observed evidence or labeled inference","expected_evidence":"what passing evidence should show"}]}. Return 3 to 6 tests, keep each item short enough to read on a phone, and return no markdown or additional keys.';
        $response=AiConnectionService::completeJson('INKY_FOCUSED_TESTS',$system,$audit['text']);$tests=self::validatedFocusedTests((array)$response['data']);return['action'=>'focused_tests','display_name'=>$displayName,'model'=>$response['model'],'focused_tests'=>$tests,'automated_findings'=>['errors'=>count($audit['errors']),'warnings'=>count($audit['warnings'])]];
    }

    private static function validatedCharacterSuggestion(array $input,array $available,array $locationNames,array $existing): array
    {
        $character=is_array($input['character']??null)?$input['character']:$input;$text=static function(mixed $value,int $max,bool $required=true): string{$value=trim((string)$value);$value=function_exists('mb_substr')?mb_substr($value,0,$max):substr($value,0,$max);if($required&&$value==='')throw new RuntimeException('Inky’s character suggestion was incomplete.');return$value;};$name=$text($character['name']??'',120);$roleCode=self::stableCode((string)($character['role_code']??$name));if($roleCode==='')throw new RuntimeException('Inky’s character suggestion did not include a stable role code.');$roleType=strtoupper(trim((string)($character['role_type']??'')));if(!in_array($roleType,$available,true))throw new RuntimeException('Inky suggested a character type that has no available Story Framework slot.');$startingLocation=$text($character['starting_location']??'',190);if(!in_array($startingLocation,$locationNames,true))throw new RuntimeException('Inky suggested a starting location that is not active in Story Framework.');foreach($existing as$row)if(strcasecmp($roleCode,(string)$row['role_code'])===0||strcasecmp($name,(string)$row['name'])===0)throw new RuntimeException('Inky suggested an existing character instead of a new one.');$freshFields=['public_description','private_knowledge','goal_text','fear_text'];foreach($existing as$row)foreach($freshFields as$field){$candidate=mb_strtolower(trim((string)($character[$field]??'')));$previous=mb_strtolower(trim((string)($row[$field]??'')));if($candidate!==''&&$previous!==''&&$candidate===$previous)throw new RuntimeException('Inky reused an existing character profile. Please request the character again.');}$pronoun=strtoupper(trim((string)($character['pronoun_set']??'IT')));if(!in_array($pronoun,['HE','SHE','IT'],true))$pronoun='IT';$summary=$text($input['summary']??'A new character that fits the current story package.',300);return['summary'=>$summary,'character'=>['role_code'=>$roleCode,'role_type'=>$roleType,'mystery_character'=>!empty($character['mystery_character']),'name'=>$name,'public_description'=>$text($character['public_description']??'',1000),'private_knowledge'=>$text($character['private_knowledge']??'',1000,false),'starting_location'=>$startingLocation,'goal_text'=>$text($character['goal_text']??'',500,false),'fear_text'=>$text($character['fear_text']??'',500,false),'pronoun_set'=>$pronoun]];
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
