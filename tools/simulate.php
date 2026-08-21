<?php
$root=dirname(__DIR__);
if(getenv('GAME2_DATABASE_CONFIG')===false||getenv('GAME2_DATABASE_CONFIG')==='')putenv('GAME2_DATABASE_CONFIG='.$root.'/config/database.test.php.example');
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/MigrationService.php';
require_once $root.'/app/BehaviorService.php';
require_once $root.'/app/CausalService.php';
require_once $root.'/app/ConsequencePropagationService.php';
require_once $root.'/app/DynamicOpportunityService.php';
require_once $root.'/app/MotiveLifecycleService.php';
require_once $root.'/app/StateSyncService.php';
require_once $root.'/app/SimulationService.php';
require_once __DIR__.'/SimulationHarness.php';

$args=$argv;array_shift($args);$opt=['plot'=>'EMPTY_THRONE','runs'=>10,'strategy'=>'no-action','duration'=>300,'step'=>30,'reset'=>false,'json'=>false];
foreach($args as$a){if($a==='--reset'){$opt['reset']=true;continue;}if($a==='--json'){$opt['json']=true;continue;}if(preg_match('/^--([^=]+)=(.*)$/',$a,$m)){$k=$m[1];$v=$m[2];if(array_key_exists($k,$opt))$opt[$k]=in_array($k,['runs','duration','step'],true)?(int)$v:$v;}}
if(!in_array($opt['strategy'],['no-action','random','first'],true)){fwrite(STDERR,"Strategy must be no-action, random, or first.\n");exit(2);}if((int)$opt['runs']<1){fwrite(STDERR,"--runs must be at least 1.\n");exit(2);}
try{
    if($opt['reset'])SimulationHarness::resetDatabase();else MigrationService::run();
    $results=[];$outcomes=[];$warningCount=0;$factorRanges=[];$totals=['player_actions'=>0,'npc_actions'=>0,'player_no_action'=>0];
    for($i=1;$i<=(int)$opt['runs'];$i++){$r=SimulationHarness::run((string)$opt['plot'],(string)$opt['strategy'],(int)$opt['duration'],(int)$opt['step']);$results[]=$r;$outcomes[$r['outcome_signature']]=($outcomes[$r['outcome_signature']]??0)+1;$warningCount+=count($r['warnings']);foreach($r['counts']as$k=>$v)$totals[$k]+=$v;foreach($r['factors']as$k=>$v){if(!isset($factorRanges[$k]))$factorRanges[$k]=['min'=>$v,'max'=>$v];else{$factorRanges[$k]['min']=min($factorRanges[$k]['min'],$v);$factorRanges[$k]['max']=max($factorRanges[$k]['max'],$v);}}}
    arsort($outcomes);$summary=['plot'=>$opt['plot'],'runs'=>(int)$opt['runs'],'strategy'=>$opt['strategy'],'unique_outcomes'=>count($outcomes),'outcome_frequencies'=>$outcomes,'average_counts'=>array_map(fn($v)=>round($v/(int)$opt['runs'],2),$totals),'factor_ranges'=>$factorRanges,'warning_count'=>$warningCount,'runs_detail'=>$results];
    if($opt['json']){echo json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;exit($warningCount?1:0);}echo "Game2 simulation batch\n";echo "Plot: {$opt['plot']} | Runs: {$opt['runs']} | Strategy: {$opt['strategy']}\n";echo "Unique final outcome signatures: ".count($outcomes)."\n";foreach($outcomes as$sig=>$n)echo "  {$sig}: {$n}\n";echo "Average player actions: {$summary['average_counts']['player_actions']}\n";echo "Average NPC actions: {$summary['average_counts']['npc_actions']}\n";echo "Average player no-actions: {$summary['average_counts']['player_no_action']}\n";echo "Factor ranges:\n";foreach($factorRanges as$k=>$range)echo "  {$k}: {$range['min']}..{$range['max']}\n";echo "Integrity warnings: {$warningCount}\n";if($warningCount){foreach($results as$r)foreach($r['warnings']as$w)echo "  Simulation {$r['simulation_id']}: {$w}\n";}exit($warningCount?1:0);
}catch(Throwable$e){fwrite(STDERR,'Simulation harness failed: '.$e->getMessage().PHP_EOL);exit(1);}
