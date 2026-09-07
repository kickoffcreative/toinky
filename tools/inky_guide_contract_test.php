<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/app/InkyGuideService.php';

$method=new ReflectionMethod(InkyGuideService::class,'validatedResponse');
$method->setAccessible(true);
$next=$method->invoke(null,[
    'reply'=>'That is a strong beginning.',
    'question'=>'Who does the story follow, and what do they want?',
    'complete'=>false,
    'brief'=>null,
],0);
if($next['complete']!==false)throw new RuntimeException('The guide completed before the fifth answer.');
if($next['question']!=='Who does the story follow, and what do they want?')throw new RuntimeException('The guide did not preserve its next question.');

$final=$method->invoke(null,[
    'reply'=>'Your story concept is ready.',
    'question'=>'',
    'complete'=>true,
    'brief'=>[
        'title'=>'The Signal',
        'setting_name'=>'A lighthouse',
        'scenario_text'=>str_repeat('A lighthouse keeper follows a forgotten signal into the storm. ',2),
        'tone'=>'Warm',
        'author_notes'=>'Keep the first choice personal.',
    ],
],4);
if($final['complete']!==true)throw new RuntimeException('The guide did not complete on the fifth answer.');
if($final['brief']['title']!=='The Signal')throw new RuntimeException('The final brief title was not preserved.');

$normalizeMethod=new ReflectionMethod(InkyGuideService::class,'normalizeProposalInput');
$normalizeMethod->setAccessible(true);
$validateMethod=new ReflectionMethod(InkyGuideService::class,'validateProposalObject');
$validateMethod->setAccessible(true);
$eventInput=$normalizeMethod->invoke(null,[
    'key'=>'event_1',
    'value'=>['code'=>'OPENING_SCENE','title'=>'The Storm Arrives','description'=>'Rain reaches the harbor.'],
    'reason'=>'Sets the story in motion.',
],'event');
$event=$validateMethod->invoke(null,$eventInput['value'],'event');
if($event['headline']!=='The Storm Arrives'||$event['text']!=='Rain reaches the harbor.')throw new RuntimeException('Event proposal aliases were not normalized.');
$packagedEvent=$normalizeMethod->invoke(null,[
    'story_value_1'=>['scope'=>'GOAL','target_code'=>'HERO_GOAL','label'=>'Hero Goal','value_type'=>'TEXT','allowed_values'=>[],'description'=>'A package item.'],
    'event_1'=>['event_code'=>'OPENING_SCENE','headline'=>'The Bell Rings','text'=>'A warning sounds across the harbor.'],
], 'event', [], 'event_1');
$eventFromPackage=$validateMethod->invoke(null,$packagedEvent['value'],'event');
if($eventFromPackage['headline']!=='The Bell Rings')throw new RuntimeException('Package-shaped event responses did not select the requested event.');
$unrelatedPackage=$normalizeMethod->invoke(null,['story_value_1'=>['label'=>'Sibling value'],'event_1'=>['headline'=>'Sibling event','text'=>'Sibling text.']], 'decision', [], 'decision_2');
if(array_key_exists('value',$unrelatedPackage))throw new RuntimeException('Unrelated package data was treated as the requested proposal.');
$locationInput=$normalizeMethod->invoke(null,[
    'key'=>'location_2',
    'value'=>['name'=>'The Old Jetty','text'=>'A broken pier above the tide.'],
    'reason'=>'Adds a contrasting location.',
],'location');
$location=$validateMethod->invoke(null,$locationInput['value'],'location');
if($location['label_text']!=='The Old Jetty'||$location['description']!=='A broken pier above the tide.')throw new RuntimeException('Location proposal aliases were not normalized.');
$packagedLocation=$normalizeMethod->invoke(null,['location_2_name'=>'The Old Jetty'],'text',[],'location_2_name');
if($packagedLocation['value']!=='The Old Jetty')throw new RuntimeException('Package-shaped location responses did not select the requested location.');
$decisionInput=$normalizeMethod->invoke(null,[
    'key'=>'decision_1',
    'value'=>[
        'event_key'=>'OPENING_SCENE',
        'character'=>'BRIANNA',
        'question'=>'Which risk will Brianna take?',
        'choices'=>[
            ['text'=>'Enter the cave','consequence'=>'She finds a clue.'],
            ['action'=>'Return to the group','outcome'=>'Her friends stay safe.'],
        ],
    ],
    'reason'=>'Creates a meaningful choice.',
],'decision');
$decision=$validateMethod->invoke(null,$decisionInput['value'],'decision');
if(count($decision['options'])!==2||$decision['options'][0]['label']!=='Enter the cave'||$decision['options'][1]['result']!=='Her friends stay safe.')throw new RuntimeException('Decision proposal aliases were not normalized.');

$failed=false;
try{$method->invoke(null,['reply'=>'Try again.','question'=>'','complete'=>false,'brief'=>null],0);}
catch(RuntimeException $e){$failed=str_contains($e->getMessage(),'next question');}
if(!$failed)throw new RuntimeException('The guide accepted a missing next question.');

$failed=false;
try{$method->invoke(null,['reply'=>'Done.','question'=>'','complete'=>true,'brief'=>['title'=>'Too short','setting_name'=>'Place','scenario_text'=>'Short']],4);}
catch(RuntimeException $e){$failed=str_contains($e->getMessage(),'too short');}
if(!$failed)throw new RuntimeException('The guide accepted a too-short final concept.');

echo "PASS: Inky guided conversation contract validates turn-by-turn responses.\n";
