<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/MigrationService.php';
MigrationService::run();
require_once $root.'/app/BehaviorService.php';
require_once $root.'/app/CausalService.php';
require_once $root.'/app/ConsequencePropagationService.php';
require_once $root.'/app/DynamicOpportunityService.php';
require_once $root.'/app/StateSyncService.php';
require_once $root.'/app/SimulationService.php';

$pdo=db();
$previousNpcReasoner=getenv('GAME2_SIM_NPC_REASONER');
putenv('GAME2_SIM_NPC_REASONER=manual');
$plotCode=strtoupper(trim((string)(getenv('TOINKY_TEST_PLOT_CODE')?:'EMPTY_THRONE')));
$roles=$pdo->prepare('SELECT pr.role_code FROM plot_roles pr JOIN plots p ON p.plot_id=pr.plot_id WHERE p.code=? AND pr.active=1 AND pr.simulation_active=1');
$roles->execute([$plotCode]);
$controllers=[];
foreach($roles->fetchAll() as $role)$controllers[(string)$role['role_code']]='HUMAN';
if(!$controllers)throw new RuntimeException('Human-biased sampling test story has no active roles.');

$simulationId=0;
try{
    $simulationId=SimulationService::create($plotCode,300,$controllers);
    SimulationService::start($simulationId);
    $optionCount=0;
    $positiveWeightCount=0;
    for($attempt=0;$attempt<24&&$optionCount===0;$attempt++){
        if($attempt>0)SimulationService::advance($simulationId,5);
        $snapshot=SimulationService::snapshot($simulationId);
        $positiveWeightCount=0;
        foreach($snapshot['characters'] as $character){
            foreach($character['open_decisions'] as $decision){
                foreach($decision['options'] as $option){
                    $optionCount++;
                    if(!array_key_exists('npc_weight',$option))throw new RuntimeException('Simulation snapshot omitted an AI Choice Weight.');
                    if((int)$option['npc_weight']>0)$positiveWeightCount++;
                }
            }
        }
    }
    if($optionCount===0)throw new RuntimeException('Human-biased sampling test found no open choices.');
    if($positiveWeightCount===0)throw new RuntimeException('Human-biased sampling test exposed only zero-weight choices.');

    $mismatch=$pdo->query('SELECT COUNT(*) FROM plot_decision_template_options template_option JOIN plot_decision_templates template ON template.template_id=template_option.template_id JOIN plot_dynamic_opportunities opportunity ON opportunity.plot_id=template.plot_id AND opportunity.opportunity_code=template.event_code AND opportunity.role_code=template.role_code JOIN plot_dynamic_opportunity_options opportunity_option ON opportunity_option.dynamic_opportunity_id=opportunity.dynamic_opportunity_id AND opportunity_option.option_code=template_option.option_code WHERE template_option.npc_weight<>GREATEST(0,opportunity_option.npc_weight)')->fetchColumn();
    if((int)$mismatch!==0)throw new RuntimeException('Dynamic Choice mirrors do not preserve their authored AI Choice Weights.');
}finally{
    if($simulationId>0)$pdo->prepare('DELETE FROM simulations WHERE simulation_id=?')->execute([$simulationId]);
    if($previousNpcReasoner===false)putenv('GAME2_SIM_NPC_REASONER');
    else putenv('GAME2_SIM_NPC_REASONER='.$previousNpcReasoner);
}

echo "Human-biased simulations receive authored weighted choices.\n";
