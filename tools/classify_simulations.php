<?php
declare(strict_types=1);
// Classification tool (audit task 16): bucket every simulation by its
// linkage before any cleanup. READ-ONLY — it deletes nothing and changes
// nothing. Buckets: (1) linked to a hosted game — keep (deleting the
// simulation would cascade-delete the game); (2) linked to saved review
// runs only — keep while the batch is referenced (deleting the simulation
// nulls the run's simulation_id but keeps result_json); (3) linked to
// neither — the candidate pool, subdivided by status and age.
// RUNNING-with-neither is suspicious (a game that never linked, or an
// interrupted Lab run) and needs individual review before any removal.
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only.');}

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';

$pdo=db();
$rows=$pdo->query('SELECT s.simulation_id,s.plot_id,s.status,s.created_at_utc,EXISTS(SELECT 1 FROM games g WHERE g.simulation_id=s.simulation_id) AS has_game,EXISTS(SELECT 1 FROM simulation_review_batch_runs r WHERE r.simulation_id=s.simulation_id) AS has_review FROM simulations s ORDER BY s.simulation_id')->fetchAll();

$total=count($rows);
$hasGame=0;$hasReviewOnly=0;$neither=[];
$neitherByStatus=['COMPLETE'=>0,'READY'=>0,'RUNNING'=>0];
$neitherRunning=[];
$oldestNeither=null;
foreach($rows as $r){
    $hg=(int)$r['has_game']===1;$hr=(int)$r['has_review']===1;
    if($hg){$hasGame++;continue;}
    if($hr){$hasReviewOnly++;continue;}
    $neither[]=$r;
    $st=(string)$r['status'];
    if(isset($neitherByStatus[$st]))$neitherByStatus[$st]++;
    if($st==='RUNNING')$neitherRunning[]=$r;
    if($oldestNeither===null||(string)$r['created_at_utc']<(string)$oldestNeither)$oldestNeither=(string)$r['created_at_utc'];
}
$neitherCount=count($neither);

echo "Simulation classification (read-only, nothing deleted)\n";
echo "total: {$total}\n";
echo "has_game (keep — deleting the simulation cascades to the game): {$hasGame}\n";
echo "has_review_only (keep while the batch is referenced): {$hasReviewOnly}\n";
echo "neither (candidate pool): {$neitherCount}\n";
foreach($neitherByStatus as $st=>$n)echo "  neither {$st}: {$n}\n";
echo "oldest neither created_at_utc: ".($oldestNeither??'n/a')."\n";
if($neitherRunning){
    echo "RUNNING with neither link (needs individual review before any removal):\n";
    foreach($neitherRunning as $r)echo "  simulation_id=".(int)$r['simulation_id']." plot_id=".(int)$r['plot_id']." created_at_utc=".(string)$r['created_at_utc']."\n";
}
echo "classify_simulations: done.\n";
