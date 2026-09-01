<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/app/InkyCowriterService.php';

$method=new ReflectionMethod(InkyCowriterService::class,'validatedNextSteps');
$method->setAccessible(true);
$qualityMethod=new ReflectionMethod(InkyCowriterService::class,'validatedQualityCheck');
$qualityMethod->setAccessible(true);
$dependencyMethod=new ReflectionMethod(InkyCowriterService::class,'validatedDependencyCheck');
$dependencyMethod->setAccessible(true);
$knowledgeMethod=new ReflectionMethod(InkyCowriterService::class,'validatedCharacterKnowledge');
$knowledgeMethod->setAccessible(true);

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

$failed=false;
try{$knowledgeMethod->invoke(null,['summary'=>'','issues'=>[]]);}
catch(RuntimeException $e){$failed=str_contains($e->getMessage(),'summary');}
if(!$failed)throw new RuntimeException('Character-knowledge validation accepted a missing summary.');

echo "PASS: Inky action contract validation normalizes and rejects malformed payloads.\n";
