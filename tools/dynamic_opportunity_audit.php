<?php
$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/GenericStateService.php';

$pdo=db();
$sid=(int)($argv[1]??0);
$role=strtoupper(trim((string)($argv[2]??'')));
if($sid<=0)$sid=(int)$pdo->query('SELECT COALESCE(MAX(simulation_id),0) FROM simulations')->fetchColumn();
if($sid<=0){fwrite(STDERR,"No simulations found.\n");exit(1);}

$q=$pdo->prepare('SELECT s.*,p.code AS plot_code FROM simulations s JOIN plots p ON p.plot_id=s.plot_id WHERE s.simulation_id=?');
$q->execute([$sid]);$sim=$q->fetch();
if(!$sim){fwrite(STDERR,"Simulation {$sid} not found.\n");exit(1);}

echo "Game2 dynamic opportunity audit\n";
echo "Simulation: {$sid} | Plot: {$sim['plot_code']} | Status: {$sim['status']} | Phase: {$sim['current_phase']} | t={$sim['elapsed_seconds']}s";
if($role!=='')echo " | Role: {$role}";
echo "\n\n";

echo "All decisions by time:\n";
$sql='SELECT d.opens_at_second,d.closes_at_second,d.status,c.actor_type,pr.role_code,se.event_code,d.chosen_option_code
      FROM decisions d
      JOIN characters c ON c.character_id=d.character_id
      JOIN plot_roles pr ON pr.plot_role_id=c.plot_role_id
      JOIN simulation_events se ON se.event_id=d.event_id
      WHERE d.simulation_id=?';
$params=[$sid];
if($role!==''){$sql.=' AND pr.role_code=?';$params[]=$role;}
$sql.=' ORDER BY d.opens_at_second,d.decision_id';
$q=$pdo->prepare($sql);$q->execute($params);$decisions=$q->fetchAll();
if(!$decisions)echo "  (none)\n";
foreach($decisions as $r)echo '  t='.(int)$r['opens_at_second'].'-'.(int)$r['closes_at_second'].'s | '.$r['actor_type'].' '.$r['role_code'].' | '.$r['event_code'].' | '.$r['status'].' | '.($r['chosen_option_code']??'null')."\n";

$q=$pdo->prepare('SELECT COUNT(*) FROM decisions d JOIN characters c ON c.character_id=d.character_id WHERE d.simulation_id=? AND c.actor_type="PLAYER" AND d.status="CHOSEN"');
$q->execute([$sid]);echo 'Player chosen-action count: '.(int)$q->fetchColumn()."\n\n";

$sql='SELECT d.dynamic_opportunity_id,d.opportunity_code,d.role_code,d.active,d.priority
      FROM plot_dynamic_opportunities d
      WHERE d.plot_id=?';
$params=[$sim['plot_id']];
if($role!==''){$sql.=' AND d.role_code=?';$params[]=$role;}
$sql.=' ORDER BY d.role_code,d.priority,d.dynamic_opportunity_id';
$q=$pdo->prepare($sql);$q->execute($params);$opportunities=$q->fetchAll();
if(!$opportunities){echo "No matching dynamic opportunities found.\n";exit(0);}

$stateKeys=[];$factorKeys=[];
foreach($opportunities as $opp){
    echo $opp['role_code'].' / '.$opp['opportunity_code'].":\n";
    echo '  Definition: present | active='.(int)$opp['active'].' | priority='.(int)$opp['priority']."\n";
    $q=$pdo->prepare('SELECT source_type,source_key,operator,compare_value FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=? ORDER BY sort_order,dynamic_condition_id');
    $q->execute([$opp['dynamic_opportunity_id']]);$conditions=$q->fetchAll();
    foreach($conditions as $c){
        $key=$c['source_key'];
        echo '  Condition: '.$c['source_type'].($key!==null?':'.$key:'').' '.$c['operator'].' '.$c['compare_value']."\n";
        if($c['source_type']==='WORLD_STATE' && $key!==null)$stateKeys[(string)$key]=true;
        if($c['source_type']==='FACTOR' && $key!==null)$factorKeys[(string)$key]=true;
    }
    $q=$pdo->prepare('SELECT sdo.status,sdo.created_at_second,d.chosen_option_code FROM simulation_dynamic_opportunities sdo LEFT JOIN decisions d ON d.decision_id=sdo.decision_id WHERE sdo.simulation_id=? AND sdo.dynamic_opportunity_id=?');
    $q->execute([$sid,$opp['dynamic_opportunity_id']]);$opened=$q->fetch();
    echo $opened
        ? '  Opened: yes | t='.(int)$opened['created_at_second'].'s | status='.$opened['status'].' | choice='.($opened['chosen_option_code']??'null')."\n\n"
        : "  Opened: no\n\n";
}

echo "Referenced final world state:\n";
if(!$stateKeys)echo "  (none)\n";
foreach(array_keys($stateKeys) as $key){
    $state=GenericStateService::getByKey($sid,$key);
    echo '  '.$key.': '.($state['exists']?(string)$state['value']:'(missing)')."\n";
}

echo "Referenced final factors:\n";
if(!$factorKeys)echo "  (none)\n";
$q=$pdo->prepare('SELECT current_value FROM simulation_factors WHERE simulation_id=? AND factor_code=?');
foreach(array_keys($factorKeys) as $key){
    $q->execute([$sid,$key]);$value=$q->fetchColumn();
    echo '  '.$key.': '.($value===false?'(missing)':$value)."\n";
}
