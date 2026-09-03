<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/app/InkyStarterService.php';

$method=new ReflectionMethod(InkyStarterService::class,'validatedDraft');
$method->setAccessible(true);
$draft=$method->invoke(null,[
    'title'=>'The Signal at Low Tide',
    'code'=>'signal_at_low_tide',
    'setting_name'=>'A storm-battered lighthouse',
    'archetype'=>'Interactive Drama',
    'scenario_text'=>'A lighthouse keeper discovers that the ships are following a signal no one remembers sending, and the next vessel will arrive before dawn.',
    'location'=>['location_code'=>'LIGHTHOUSE','label_text'=>'The Lighthouse','description'=>'A lonely tower above a dangerous inlet.'],
    'character'=>['role_code'=>'KEEPER','name'=>'Mara Vale','public_description'=>'A careful keeper who knows every sound the tower makes.','private_knowledge'=>'The signal began after the old beacon was repaired.','starting_location'=>'The Lighthouse','goal_text'=>'Learn who is sending the signal before the next ship reaches the rocks.','fear_text'=>'Being responsible for a disaster no one else can see.','pronoun_set'=>'SHE'],
    'event'=>['event_code'=>'SIGNAL_BEGINS','phase'=>'INCITING_INCIDENT','headline'=>'The Signal Begins','event_text'=>'A hidden lamp flashes across the water in a pattern that answers the lighthouse beacon.'],
    'decision'=>['prompt_text'=>'Do you answer the signal or extinguish the beacon before the ship arrives?'],
    'options'=>[
        ['option_code'=>'ANSWER_SIGNAL','label'=>'Answer the signal','result_text'=>'You send the pattern back and reveal that the lighthouse is occupied.'],
        ['option_code'=>'EXTINGUISH_BEACON','label'=>'Extinguish the beacon','result_text'=>'You darken the tower, protecting the ship from the false signal but losing your only lead.'],
    ],
    'endings'=>['aftermath'=>'The first choice changes what the keeper knows and what the arriving ship can see.','invitation'=>'The sea remains dark, and another signal waits beyond the horizon.'],
]);

if($draft['code']!=='SIGNAL_AT_LOW_TIDE')throw new RuntimeException('Starter codes were not normalized.');
if($draft['character']['starting_location']!==$draft['location']['label_text'])throw new RuntimeException('Starter character location does not match its authored location.');
if(count($draft['options'])!==2)throw new RuntimeException('Starter did not preserve exactly two choices.');
if($draft['character']['pronoun_set']!=='SHE')throw new RuntimeException('Starter pronoun set was not preserved.');

$failed=false;
try{$method->invoke(null,['title'=>'Incomplete','scenario_text'=>str_repeat('x',80),'location'=>['label_text'=>'Place'],'character'=>['name'=>'Person'],'event'=>['headline'=>'Event','event_text'=>'Text'],'decision'=>['prompt_text'=>'Choose'],'options'=>[['label'=>'Only choice','result_text'=>'One result']],'endings'=>['aftermath'=>'Aftermath','invitation'=>'Replay']]);}
catch(RuntimeException $e){$failed=str_contains($e->getMessage(),'two distinct usable choices');}
if(!$failed)throw new RuntimeException('Starter validation accepted an incomplete choice set.');

echo "PASS: Inky guided starter contract produces a bounded playable package.\n";
