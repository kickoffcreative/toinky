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

$failed=false;
try{$method->invoke(null,['reply'=>'Try again.','question'=>'','complete'=>false,'brief'=>null],0);}
catch(RuntimeException $e){$failed=str_contains($e->getMessage(),'next question');}
if(!$failed)throw new RuntimeException('The guide accepted a missing next question.');

$failed=false;
try{$method->invoke(null,['reply'=>'Done.','question'=>'','complete'=>true,'brief'=>['title'=>'Too short','setting_name'=>'Place','scenario_text'=>'Short']],4);}
catch(RuntimeException $e){$failed=str_contains($e->getMessage(),'too short');}
if(!$failed)throw new RuntimeException('The guide accepted a too-short final concept.');

echo "PASS: Inky guided conversation contract validates turn-by-turn responses.\n";
