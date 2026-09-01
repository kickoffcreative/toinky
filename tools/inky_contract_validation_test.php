<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/app/InkyCowriterService.php';

$method=new ReflectionMethod(InkyCowriterService::class,'validatedNextSteps');
$method->setAccessible(true);
$qualityMethod=new ReflectionMethod(InkyCowriterService::class,'validatedQualityCheck');
$qualityMethod->setAccessible(true);

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

echo "PASS: Inky action contract validation normalizes and rejects malformed payloads.\n";
