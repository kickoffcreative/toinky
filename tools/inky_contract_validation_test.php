<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/app/InkyCowriterService.php';

$decodeMethod=new ReflectionMethod(AiConnectionService::class,'decodeStructuredResponse');
$decodeMethod->setAccessible(true);
$decoded=$decodeMethod->invoke(null,['message'=>['content'=>"<think>Reason privately before answering.</think>\n```json\n{\"summary\":\"Recovered\",\"steps\":[]}\n```\n"]]);
if($decoded['summary']!=='Recovered')throw new RuntimeException('AI structured-response normalization did not remove reasoning and fences.');
$decoded=$decodeMethod->invoke(null,['response'=>'Prefix text {"summary":"Extracted"} trailing text']);
if($decoded['summary']!=='Extracted')throw new RuntimeException('AI structured-response normalization did not extract embedded JSON.');
$decoded=$decodeMethod->invoke(null,['choices'=>[['message'=>['content'=>['summary'=>'Array content']]]]]);
if($decoded['summary']!=='Array content')throw new RuntimeException('AI structured-response normalization did not accept provider array content.');
$decoded=$decodeMethod->invoke(null,['message'=>['content'=>[['type'=>'text','text'=>'{"summary":"Block content"}']]]]);
if($decoded['summary']!=='Block content')throw new RuntimeException('AI structured-response normalization did not join provider text blocks.');

$method=new ReflectionMethod(InkyCowriterService::class,'validatedNextSteps');
$method->setAccessible(true);
$reviewMethod=new ReflectionMethod(InkyCowriterService::class,'validatedReview');
$reviewMethod->setAccessible(true);
$qualityMethod=new ReflectionMethod(InkyCowriterService::class,'validatedQualityCheck');
$qualityMethod->setAccessible(true);
$dependencyMethod=new ReflectionMethod(InkyCowriterService::class,'validatedDependencyCheck');
$dependencyMethod->setAccessible(true);
$knowledgeMethod=new ReflectionMethod(InkyCowriterService::class,'validatedCharacterKnowledge');
$knowledgeMethod->setAccessible(true);
$valueMethod=new ReflectionMethod(InkyCowriterService::class,'validatedStoryValues');
$valueMethod->setAccessible(true);
$opportunityMethod=new ReflectionMethod(InkyCowriterService::class,'validatedOpportunityReview');
$opportunityMethod->setAccessible(true);
$focusedMethod=new ReflectionMethod(InkyCowriterService::class,'validatedFocusedTests');
$focusedMethod->setAccessible(true);
$characterSuggestionMethod=new ReflectionMethod(InkyCowriterService::class,'validatedCharacterSuggestion');
$characterSuggestionMethod->setAccessible(true);

$emptyReview=$reviewMethod->invoke(null,['summary'=>'The completed story has no specific review findings.','observed_evidence'=>[],'inferences'=>[],'recommendations'=>[]]);
if($emptyReview['summary']===''||$emptyReview['observed_evidence']!==[]||$emptyReview['recommendations']!==[])throw new RuntimeException('Completed-story review did not accept a valid no-findings response.');
$groundedReview=$reviewMethod->invoke(null,[
    'summary'=>'Only grounded recommendations should survive.',
    'observed_evidence'=>[],
    'inferences'=>[],
    'recommendations'=>[
        ['stable_key'=>'opening scene','severity'=>'high','category'=>'immersion','recommendation'=>'Add one sensory detail to the opening scene.'],
        ['stable_key'=>'scenario_text','severity'=>'blocker','category'=>'immersion','recommendation'=>'Ignored generic field citation.'],
    ],
],['OPENING_SCENE']);
if(count($groundedReview['recommendations'])!==1||$groundedReview['recommendations'][0]['stable_key']!=='OPENING_SCENE')throw new RuntimeException('Review validator did not filter recommendations outside the stable-key catalog.');
if($groundedReview['recommendations'][0]['severity']!=='HIGH')throw new RuntimeException('Review recommendation severity was not normalized.');
$failed=false;
try{$reviewMethod->invoke(null,['summary'=>'Retry this response.','recommendations'=>[['stable_key'=>'scenario_text','recommendation'=>'Generic citation.']]],['OPENING_SCENE']);}
catch(RuntimeException $e){$failed=str_contains($e->getMessage(),'usable stable keys');}
if(!$failed)throw new RuntimeException('Review validator accepted a response with no grounded recommendations.');
$emptyContracts=[
    [$method,['summary'=>'No next-step gaps were identified.','steps'=>[]]],
    [$qualityMethod,['summary'=>'No quality concerns were identified.','checks'=>[]]],
    [$dependencyMethod,['summary'=>'No dependency problems were identified.','problems'=>[]]],
    [$knowledgeMethod,['summary'=>'No character knowledge issues were identified.','issues'=>[]]],
    [$valueMethod,['summary'=>'No additional Story Framework values were identified.','suggestions'=>[]]],
    [$opportunityMethod,['summary'=>'No opportunity issues were identified.','opportunities'=>[]]],
    [$focusedMethod,['summary'=>'No focused tests were identified.','tests'=>[]]],
];
foreach($emptyContracts as[$validator,$payload]){$empty=$validator->invoke(null,$payload);if(trim((string)$empty['summary'])==='')throw new RuntimeException('A completed-story empty review lost its summary.');}

$characterSuggestion=$characterSuggestionMethod->invoke(null,[
    'summary'=>'A cautious cartographer can join the expedition.',
    'character'=>[
        'role_code'=>'CARTOGRAPHER',
        'role_type'=>'NPC',
        'mystery_character'=>true,
        'name'=>'Mara Vale',
        'public_description'=>'A careful cartographer who reads coastlines like stories.',
        'private_knowledge'=>'She recognizes the island from a forbidden map.',
        'starting_location'=>'Harbor',
        'goal_text'=>'To map a safe route home.',
        'fear_text'=>'That the map will lead the crew into danger.',
        'pronoun_set'=>'SHE',
    ],
],['NPC'],['Harbor'],[['role_code'=>'KEEPER','name'=>'Elias']]);
if($characterSuggestion['character']['role_code']!=='CARTOGRAPHER'||$characterSuggestion['character']['starting_location']!=='Harbor')throw new RuntimeException('Character suggestion validation did not preserve the usable proposal.');
if($characterSuggestion['character']['mystery_character']!==true)throw new RuntimeException('Character suggestion validation did not preserve mystery status.');

$characterLocationFallback=$characterSuggestionMethod->invoke(null,[
    'summary'=>'A new character with a distant connection to the shore.',
    'character'=>[
        'role_code'=>'SIGNAL_KEEPER',
        'role_type'=>'NPC',
        'name'=>'Ivo March',
        'public_description'=>'A quiet keeper who notices patterns in the tide.',
        'starting_location'=>'The old signal tower',
    ],
],['NPC'],[['location_code'=>'FORGOTTEN_SHORE','label_text'=>'Forgotten Shore']],[]);
if($characterLocationFallback['character']['starting_location']!=='Forgotten Shore')throw new RuntimeException('Character suggestion validation did not normalize an invalid starting location to an active location.');

$failed=false;
try{$characterSuggestionMethod->invoke(null,['summary'=>'New character','character'=>['name'=>'Elias','role_type'=>'NPC','starting_location'=>'Harbor','public_description'=>'Duplicate.']],['NPC'],['Harbor'],[['role_code'=>'KEEPER','name'=>'Elias']]);}
catch(RuntimeException $e){$failed=str_contains($e->getMessage(),'existing character');}
if(!$failed)throw new RuntimeException('Character suggestion validation accepted a duplicate character.');

$result=$method->invoke(null,[
    'summary'=>'Start with the highest-risk authoring gaps.',
    'steps'=>[
        ['stable_key'=>'ERROR_SCENARIO','priority'=>'BLOCKER','action'=>'Expand the scenario briefing.','reason'=>'Observed evidence: the scenario briefing is under the required length.','manual_structural_review'=>false],
        ['stable_key'=>'EVENT_OPENING','priority'=>'unexpected','action'=>'Tighten the opening event copy.','reason'=>'Inference: shorter prose will read better on phones.','manual_structural_review'=>true],
        ['stable_key'=>'MISSING_ACTION','priority'=>'HIGH','action'=>'','reason'=>'Observed evidence: this row should be ignored.'],
        ['stable_key'=>'STEP_3','priority'=>'LOW','action'=>'Check one more authored dependency.','reason'=>'Observed evidence: coverage is unclear.'],
        ['stable_key'=>'STEP_4','priority'=>'LOW','action'=>'Check one more authored dependency.','reason'=>'Observed evidence: coverage is unclear.'],
        ['stable_key'=>'STEP_5','priority'=>'LOW','action'=>'Check one more authored dependency.','reason'=>'Observed evidence: coverage is unclear.'],
        ['stable_key'=>'STEP_6','priority'=>'LOW','action'=>'Check one more authored dependency.','reason'=>'Observed evidence: coverage is unclear.'],
        ['stable_key'=>'STEP_7','priority'=>'LOW','action'=>'This seventh valid item should be capped.','reason'=>'Observed evidence: too many items.'],
    ],
]);

if($result['summary']!=='Start with the highest-risk authoring gaps.')throw new RuntimeException('Next-step summary was not preserved.');
if(count($result['steps'])!==6)throw new RuntimeException('Next-step guidance was not capped at six valid items.');
if($result['steps'][0]['priority']!=='BLOCKER')throw new RuntimeException('Valid next-step priority was not preserved.');
if($result['steps'][1]['priority']!=='MEDIUM')throw new RuntimeException('Unknown next-step priority was not normalized.');
if($result['steps'][1]['manual_structural_review']!==true)throw new RuntimeException('Manual structural review flag was not preserved.');

$failed=false;
try{$method->invoke(null,['summary'=>'','steps'=>[]]);}
catch(RuntimeException $e){$failed=str_contains($e->getMessage(),'summary');}
if(!$failed)throw new RuntimeException('Next-step validation accepted a missing summary.');

$quality=$qualityMethod->invoke(null,[
    'summary'=>'The draft is playable but needs stronger evidence-backed polish.',
    'checks'=>[
        ['category'=>'immersion','score'=>5,'stable_key'=>'OPENING_SCENE','note'=>'Observed evidence: the opening uses concrete sensory detail.','improvement'=>'Keep the same specificity in later events.','manual_structural_review'=>false],
        ['category'=>'branch_causality','score'=>9,'stable_key'=>'CHOICE_REPORT','note'=>'Inference: the cause-and-effect chain may feel abrupt.','improvement'=>'Add result prose that names the immediate consequence.','manual_structural_review'=>true],
        ['category'=>'unsupported_category','score'=>1,'stable_key'=>'SKIP_ME','note'=>'Observed evidence: ignored.','improvement'=>'Ignored.'],
    ],
]);
if($quality['summary']!=='The draft is playable but needs stronger evidence-backed polish.')throw new RuntimeException('Quality-check summary was not preserved.');
if(count($quality['checks'])!==2)throw new RuntimeException('Quality-check validation did not filter unsupported categories.');
if($quality['checks'][1]['score']!==5)throw new RuntimeException('Quality-check score was not clamped.');
if($quality['checks'][1]['manual_structural_review']!==true)throw new RuntimeException('Quality-check manual structural review flag was not preserved.');

$failed=false;
try{$qualityMethod->invoke(null,['summary'=>'','checks'=>[]]);}
catch(RuntimeException $e){$failed=str_contains($e->getMessage(),'summary');}
if(!$failed)throw new RuntimeException('Quality-check validation accepted a missing summary.');

$dependencies=$dependencyMethod->invoke(null,[
    'summary'=>'Several authored links need tighter dependency coverage.',
    'problems'=>[
        ['stable_key'=>'DECISION_OPENING','severity'=>'BLOCKER','problem'=>'The decision has too few active choices.','evidence'=>'Observed evidence: the package reports fewer than two active options.','fix'=>'Add or reactivate a second authored option.','manual_structural_review'=>false],
        ['stable_key'=>'ENDING_SECRET','severity'=>'unexpected','problem'=>'The ending may not have a clear trigger.','evidence'=>'Inference: no nearby condition appears to set the required value.','fix'=>'Connect a choice effect to the ending condition value.','manual_structural_review'=>true],
        ['stable_key'=>'SKIP_ME','severity'=>'HIGH','problem'=>'','evidence'=>'Observed evidence: ignored.','fix'=>'Ignored.'],
    ],
]);
if($dependencies['summary']!=='Several authored links need tighter dependency coverage.')throw new RuntimeException('Dependency-check summary was not preserved.');
if(count($dependencies['problems'])!==2)throw new RuntimeException('Dependency-check validation did not filter malformed problems.');
if($dependencies['problems'][0]['severity']!=='BLOCKER')throw new RuntimeException('Dependency-check severity was not preserved.');
if($dependencies['problems'][1]['severity']!=='MEDIUM')throw new RuntimeException('Dependency-check unknown severity was not normalized.');
if($dependencies['problems'][1]['manual_structural_review']!==true)throw new RuntimeException('Dependency-check manual structural review flag was not preserved.');

$failed=false;
try{$dependencyMethod->invoke(null,['summary'=>'','problems'=>[]]);}
catch(RuntimeException $e){$failed=str_contains($e->getMessage(),'summary');}
if(!$failed)throw new RuntimeException('Dependency-check validation accepted a missing summary.');

$knowledge=$knowledgeMethod->invoke(null,[
    'summary'=>'Two characters need clearer viewpoint boundaries.',
    'issues'=>[
        ['role_code'=>'Farah Vale','stable_key'=>'FARAH_SECRET_PROMPT','severity'=>'HIGH','issue'=>'The prompt may expose another character’s private knowledge.','evidence'=>'Inference: the character has no authored known fact for that detail.','fix'=>'Move the detail into private knowledge or reword the prompt around observable behavior.'],
        ['role_code'=>'ELIAN','stable_key'=>'ELIAN_CLUE','severity'=>'unexpected','issue'=>'The character lacks enough context for a meaningful choice.','evidence'=>'Observed evidence: no private knowledge explains the clue.','fix'=>'Add non-privileged setup in the event or character knowledge.'],
        ['role_code'=>'','stable_key'=>'SKIP_ME','severity'=>'LOW','issue'=>'Ignored.','evidence'=>'Ignored.','fix'=>'Ignored.'],
    ],
]);
if($knowledge['summary']!=='Two characters need clearer viewpoint boundaries.')throw new RuntimeException('Character-knowledge summary was not preserved.');
if(count($knowledge['issues'])!==2)throw new RuntimeException('Character-knowledge validation did not filter malformed issues.');
if($knowledge['issues'][0]['role_code']!=='FARAH_VALE')throw new RuntimeException('Character-knowledge role code was not normalized.');
if($knowledge['issues'][1]['severity']!=='MEDIUM')throw new RuntimeException('Character-knowledge unknown severity was not normalized.');

$structuralKnowledge=$knowledgeMethod->invoke(null,[
    'summary'=>'The response contains invalid condition references and private knowledge issues.',
    'issues'=>[
        ['role_code'=>'WREN','stable_key'=>'WREN_RESCUE_GAP','severity'=>'BLOCKER','issue'=>'Invalid WORLD_STATE condition','evidence'=>'GUEST_HELPERS uses an unsupported operator.','fix'=>'Correct the authored condition.'],
        ['role_code'=>'GENERAL','stable_key'=>'WALL_SHIFT','severity'=>'LOW','issue'=>'Missing story artwork','evidence'=>'No story artwork has been uploaded.','fix'=>'Upload the story artwork.'],
    ],
],['WREN_RESCUE_GAP','WALL_SHIFT'],['WREN','GENERAL']);
if($structuralKnowledge['issues']!==[]||!str_starts_with($structuralKnowledge['summary'],'No separate character-knowledge'))throw new RuntimeException('Character review did not separate structural findings from qualitative issues.');
$structuralOpportunity=$opportunityMethod->invoke(null,[
    'summary'=>'The review identifies several structural issues with conditions and effects in the opportunities.',
    'opportunities'=>[
        ['opportunity_code'=>'WREN_RESCUE_GAP','stable_key'=>'WREN_RESCUE_GAP','severity'=>'BLOCKER','finding'=>'Invalid WORLD_STATE reference.','recommendation'=>'Correct the condition reference.'],
    ],
],['WREN_RESCUE_GAP'],['WREN_RESCUE_GAP']);
if($structuralOpportunity['opportunities']!==[]||!str_starts_with($structuralOpportunity['summary'],'No separate opportunity-quality'))throw new RuntimeException('Opportunity review did not separate structural findings from qualitative issues.');
$structuralQuality=$qualityMethod->invoke(null,[
    'summary'=>'The quality review found structural validation issues with the story package.',
    'checks'=>[
        ['category'=>'dependency_coverage','score'=>2,'stable_key'=>'WREN_RESCUE_GAP','note'=>'Invalid WORLD_STATE condition.','improvement'=>'Fix the authored condition.'],
    ],
],['WREN_RESCUE_GAP']);
if($structuralQuality['checks']!==[]||!str_starts_with($structuralQuality['summary'],'No separate qualitative quality'))throw new RuntimeException('Quality review did not separate structural findings from qualitative issues.');
$failed=false;
try{$knowledgeMethod->invoke(null,[
    'summary'=>'A qualitative issue was reported.',
    'issues'=>[
        ['role_code'=>'UNKNOWN_ROLE','stable_key'=>'WREN_RESCUE_GAP','severity'=>'MEDIUM','issue'=>'The character lacks enough context for a meaningful choice.','evidence'=>'Observed evidence: the prompt does not explain the immediate goal.','fix'=>'Add player-facing context to the prompt.'],
    ],
],['WREN_RESCUE_GAP'],['WREN']);}catch(RuntimeException $e){$failed=str_contains($e->getMessage(),'usable role code');}
if(!$failed)throw new RuntimeException('Character review silently discarded a malformed qualitative issue.');

$failed=false;
try{$knowledgeMethod->invoke(null,['summary'=>'','issues'=>[]]);}
catch(RuntimeException $e){$failed=str_contains($e->getMessage(),'summary');}
if(!$failed)throw new RuntimeException('Character-knowledge validation accepted a missing summary.');

$values=$valueMethod->invoke(null,[
    'summary'=>'Add clearer managed values for branch outcomes.',
    'suggestions'=>[
        ['stable_key'=>'CHOICE_REVEAL','scope'=>'world_state','target_code'=>'report custody','label'=>'Report custody','value_type'=>'preset','allowed_values'=>['HELD','LOST','HELD'],'reason'=>'Observed evidence: outcomes depend on who has the report.','example_use'=>'Use as a choice effect and ending condition.'],
        ['stable_key'=>'GOAL_ALIGN','scope'=>'unsupported','target_code'=>'trust','label'=>'Trust','value_type'=>'unexpected','allowed_values'=>[],'reason'=>'Inference: trust could clarify a relationship shift.','example_use'=>'Track branch consequences.'],
        ['stable_key'=>'SKIP','scope'=>'WORLD_STATE','target_code'=>'','label'=>'Skip','value_type'=>'TEXT','reason'=>'Ignored.','example_use'=>'Ignored.'],
    ],
]);
if($values['summary']!=='Add clearer managed values for branch outcomes.')throw new RuntimeException('Story-value summary was not preserved.');
if(count($values['suggestions'])!==2)throw new RuntimeException('Story-value validation did not filter malformed suggestions.');
if($values['suggestions'][0]['scope']!=='WORLD_STATE')throw new RuntimeException('Story-value scope was not normalized.');
if($values['suggestions'][0]['target_code']!=='REPORT_CUSTODY')throw new RuntimeException('Story-value target code was not normalized.');
if(count($values['suggestions'][0]['allowed_values'])!==2)throw new RuntimeException('Story-value preset values were not deduplicated.');
if($values['suggestions'][1]['scope']!=='WORLD_STATE')throw new RuntimeException('Story-value unknown scope was not defaulted.');
if($values['suggestions'][1]['value_type']!=='TEXT')throw new RuntimeException('Story-value unknown type was not defaulted.');

$failed=false;
try{$valueMethod->invoke(null,['summary'=>'','suggestions'=>[]]);}
catch(RuntimeException $e){$failed=str_contains($e->getMessage(),'summary');}
if(!$failed)throw new RuntimeException('Story-value validation accepted a missing summary.');

$opportunity=$opportunityMethod->invoke(null,[
    'summary'=>'Several opportunities need tighter authored consequences.',
    'opportunities'=>[
        ['opportunity_code'=>'reveal report','stable_key'=>'CHOICE_REVEAL','severity'=>'HIGH','finding'=>'Observed evidence: the choice result names a major reveal.','recommendation'=>'Make the immediate consequence explicit in the result text.','focused_test'=>'Run a seeded focused opportunity test for each active choice.'],
        ['opportunity_code'=>'COURT_HEARING','stable_key'=>'RATIONALE_GAP','severity'=>'unexpected','finding'=>'Inference: AI rationale guidance may be too thin.','recommendation'=>'Add private rationale guidance for why this option fits the character.','focused_test'=>''],
        ['opportunity_code'=>'','stable_key'=>'SKIP','severity'=>'LOW','finding'=>'Ignored.','recommendation'=>'Ignored.'],
    ],
]);
if($opportunity['summary']!=='Several opportunities need tighter authored consequences.')throw new RuntimeException('Opportunity-review summary was not preserved.');
if(count($opportunity['opportunities'])!==2)throw new RuntimeException('Opportunity-review validation did not filter malformed items.');
if($opportunity['opportunities'][0]['opportunity_code']!=='REVEAL_REPORT')throw new RuntimeException('Opportunity-review code was not normalized.');
if($opportunity['opportunities'][1]['severity']!=='MEDIUM')throw new RuntimeException('Opportunity-review unknown severity was not normalized.');
if($opportunity['opportunities'][0]['focused_test']==='')throw new RuntimeException('Opportunity-review focused test text was not preserved.');

$failed=false;
try{$opportunityMethod->invoke(null,['summary'=>'','opportunities'=>[]]);}
catch(RuntimeException $e){$failed=str_contains($e->getMessage(),'summary');}
if(!$failed)throw new RuntimeException('Opportunity-review validation accepted a missing summary.');

$focused=$focusedMethod->invoke(null,[
    'summary'=>'Prioritize local branch coverage before broad replay.',
    'tests'=>[
        ['stable_key'=>'OPPORTUNITY_REVEAL','test_type'=>'focused_opportunity','focus_code'=>'reveal report','runs'=>8,'reason'=>'Observed evidence: the opportunity controls a high-impact branch.','expected_evidence'=>'Every active choice is delivered and applied from the seeded checkpoint.'],
        ['stable_key'=>'ENDING_REVIEW','test_type'=>'broad_simulation','focus_code'=>'ending families','runs'=>999,'reason'=>'Inference: broad replay may expose missing aftermath outcomes.','expected_evidence'=>'Outcome families and warning frequency are reviewed.'],
        ['stable_key'=>'COPY_CHECK','test_type'=>'manual_smoke','focus_code'=>'mobile prose','runs'=>30,'reason'=>'Observed evidence: player-facing prose is long.','expected_evidence'=>'The phone view remains readable without covering choices.'],
        ['stable_key'=>'DEFAULT_ME','test_type'=>'unexpected','focus_code'=>'validation','runs'=>0,'reason'=>'Inference: validation should catch structural gaps.','expected_evidence'=>'Blocking errors are resolved or confirmed.'],
        ['stable_key'=>'SKIP','test_type'=>'VALIDATION','focus_code'=>'','runs'=>5,'reason'=>'Ignored.','expected_evidence'=>'Ignored.'],
    ],
]);
if($focused['summary']!=='Prioritize local branch coverage before broad replay.')throw new RuntimeException('Focused-test summary was not preserved.');
if(count($focused['tests'])!==4)throw new RuntimeException('Focused-test validation did not filter malformed tests.');
if($focused['tests'][0]['test_type']!=='FOCUSED_OPPORTUNITY')throw new RuntimeException('Focused-test type was not normalized.');
if($focused['tests'][0]['focus_code']!=='REVEAL_REPORT')throw new RuntimeException('Focused-test focus code was not normalized.');
if($focused['tests'][1]['runs']!==250)throw new RuntimeException('Broad simulation run count was not capped at 250.');
if($focused['tests'][2]['runs']!==1)throw new RuntimeException('Manual smoke run count was not forced to one.');
if($focused['tests'][3]['test_type']!=='VALIDATION')throw new RuntimeException('Unknown focused-test type was not defaulted.');
if($focused['tests'][3]['runs']!==1)throw new RuntimeException('Validation run count was not forced to one.');
$groundedFocused=$focusedMethod->invoke(null,[
    'summary'=>'Only executable authored targets should survive.',
    'tests'=>[
        ['stable_key'=>'OPENING_EVENT','test_type'=>'validation','focus_code'=>'opening event','runs'=>1,'reason'=>'Observed evidence: the opening event is a branch entry point.','expected_evidence'=>'Validation reports the authored event and its references.'],
        ['stable_key'=>'OPPORTUNITY_REVEAL','test_type'=>'focused_opportunity','focus_code'=>'OPPORTUNITY_OR_AREA','runs'=>10,'reason'=>'Generic placeholder.','expected_evidence'=>'Ignored.'],
    ],
],['OPENING_EVENT','OPPORTUNITY_REVEAL']);
if(count($groundedFocused['tests'])!==1||$groundedFocused['tests'][0]['focus_code']!=='OPENING_EVENT')throw new RuntimeException('Focused-test validation did not reject placeholder or unlisted focus codes.');

$failed=false;
try{$focusedMethod->invoke(null,['summary'=>'','tests'=>[]]);}
catch(RuntimeException $e){$failed=str_contains($e->getMessage(),'summary');}
if(!$failed)throw new RuntimeException('Focused-test validation accepted a missing summary.');

echo "PASS: Inky action contract validation normalizes and rejects malformed payloads.\n";
