<?php
$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';

$pdo=db();
$sid=(int)$pdo->query('SELECT COALESCE(MAX(simulation_id),0) FROM simulations')->fetchColumn();
if($sid<=0){fwrite(STDERR,"No simulations found.\n");exit(1);}

$q=$pdo->prepare('SELECT interaction_id,simulation_id,character_id,decision_id,provider,model,success,http_status,error_message,created_at_utc FROM ai_reasoner_interactions WHERE simulation_id=? ORDER BY interaction_id');
$q->execute([$sid]);
$rows=$q->fetchAll();

echo "Latest simulation: {$sid}\n";
if(!$rows){echo "No AI interaction rows found for this simulation.\n";exit(0);}
foreach($rows as$r){
    echo "Interaction {$r['interaction_id']} | success={$r['success']} | provider={$r['provider']} | model={$r['model']} | HTTP=".($r['http_status']??'null')."\n";
    echo "  character_id=".($r['character_id']??'null')." | decision_id=".($r['decision_id']??'null')."\n";
    echo "  error=".($r['error_message']??'null')."\n";
}
