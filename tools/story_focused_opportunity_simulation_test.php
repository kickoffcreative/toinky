<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only.');}

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/MigrationService.php';
MigrationService::run();
require_once $root.'/app/StorySimulationBatchService.php';

$pdo=db();
$plotCode=strtoupper(trim((string)(getenv('TOINKY_TEST_PLOT_CODE')?:'EMPTY_THRONE')));
$opportunityCode=strtoupper(trim((string)(getenv('TOINKY_TEST_FOCUS_OPPORTUNITY')?:'ELIAN_VERIFIED_PATH')));
$q=$pdo->prepare('SELECT plot_id FROM plots WHERE code=?');
$q->execute([$plotCode]);
$plotId=(int)$q->fetchColumn();
if(!$plotId)throw new RuntimeException('Focused simulation test story '.$plotCode.' was not found.');

$options=FocusedOpportunitySimulationService::activeOptions($plotId,$opportunityCode);
if(!$options)throw new RuntimeException('Focused simulation test opportunity '.$opportunityCode.' has no active choices.');

$simulationIds=[];
$batchId=0;
try{
    foreach($options as$option){
        $result=FocusedOpportunitySimulationService::run($plotCode,$opportunityCode,(string)$option['option_code'],300,30);
        $simulationIds[]=(int)$result['simulation_id'];
        $focus=$result['focus_test']??[];
        if(empty($focus['seeded']))throw new RuntimeException($option['option_code'].' was not labelled as seeded.');
        if(empty($focus['opportunity_eligible']))throw new RuntimeException($option['option_code'].' did not receive an eligible target opportunity.');
        if(empty($focus['decision_delivered']))throw new RuntimeException($option['option_code'].' did not receive the focused decision.');
        if(empty($focus['choice_applied']))throw new RuntimeException($option['option_code'].' was not applied.');
        if((string)($focus['target_option_code']??'')!==(string)$option['option_code'])throw new RuntimeException('Focused result reported the wrong target choice.');
    }

    $admin=$pdo->query('SELECT user_id,is_admin FROM users WHERE is_admin=1 ORDER BY user_id LIMIT 1')->fetch();
    if(!$admin)throw new RuntimeException('Focused batch test needs an administrator.');
    $batchId=StorySimulationBatchService::create($admin,['plot_id'=>$plotId,'controller_mode'=>'FOCUSED','focus_opportunity_code'=>$opportunityCode,'requested_runs'=>1,'duration_seconds'=>300,'step_seconds'=>30]);
    $batch=StorySimulationBatchService::one($batchId);
    if(!$batch||$batch['controller_mode']!=='FOCUSED')throw new RuntimeException('Focused batch was not created in focused mode.');
    if((int)$batch['requested_runs']!==count($options))throw new RuntimeException('Focused batch did not schedule one run per active choice.');
    if((int)$batch['focus_runs_per_option']!==1)throw new RuntimeException('Focused batch did not preserve the runs-per-choice setting.');
}finally{
    if($batchId>0)$pdo->prepare('DELETE FROM simulation_review_batches WHERE simulation_review_batch_id=?')->execute([$batchId]);
    if($simulationIds){$marks=implode(',',array_fill(0,count($simulationIds),'?'));$pdo->prepare('DELETE FROM simulations WHERE simulation_id IN ('.$marks.')')->execute($simulationIds);}
}

echo 'Focused opportunity simulation passed for '.count($options)." active choices.\n";
