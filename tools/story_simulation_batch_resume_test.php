<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only.');}

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/MigrationService.php';
MigrationService::run();
require_once $root.'/app/StorySimulationBatchService.php';

$pdo=db();
$admin=$pdo->query('SELECT user_id,is_admin FROM users WHERE is_admin=1 ORDER BY user_id LIMIT 1')->fetch();
$plot=$pdo->query('SELECT plot_id FROM plots ORDER BY plot_id LIMIT 1')->fetch();
if(!$admin||!$plot)throw new RuntimeException('Simulation batch resume test needs an administrator and a story.');

$batchId=0;
try{
    $pdo->prepare('INSERT INTO simulation_review_batches(plot_id,requested_by_user_id,controller_mode,requested_runs,completed_runs,duration_seconds,step_seconds,status,last_error,completed_at_utc) VALUES(?,?,"AUTONOMOUS",3,1,300,30,"FAILED","Duplicate run test",UTC_TIMESTAMP())')->execute([$plot['plot_id'],$admin['user_id']]);
    $batchId=(int)$pdo->lastInsertId();
    $run=$pdo->prepare('INSERT INTO simulation_review_batch_runs(simulation_review_batch_id,run_no,result_json) VALUES(?,?,?)');
    $run->execute([$batchId,1,'{"outcome_family_label":"Test path","warnings":[],"counts":{}}']);
    $run->execute([$batchId,2,'{"outcome_family_label":"Test path","warnings":[],"counts":{}}']);
    $resumed=StorySimulationBatchService::resume($admin,$batchId);
    if((int)$resumed['completed_runs']!==2||$resumed['status']!=='QUEUED'||$resumed['last_error']!==null)throw new RuntimeException('Failed batch did not resume from the highest durable run number.');
    $pdo->prepare('DELETE FROM simulation_review_batches WHERE simulation_review_batch_id=?')->execute([$batchId]);
}catch(Throwable $error){if($batchId>0)$pdo->prepare('DELETE FROM simulation_review_batches WHERE simulation_review_batch_id=?')->execute([$batchId]);throw $error;}

echo "Simulation review batch resume recovery passed.\n";
