<?php
$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/GenericStateService.php';

$pdo=db();
$sid=(int)($argv[1]??0);
if($sid<=0)$sid=(int)$pdo->query('SELECT COALESCE(MAX(simulation_id),0) FROM simulations')->fetchColumn();
if($sid<=0){fwrite(STDERR,"No simulations found.\n");exit(1);}

$migration='029_distinguish_player_silence_and_activity.sql';
$q=$pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE migration=?');
$q->execute([$migration]);
$applied=(int)$q->fetchColumn()===1;

$q=$pdo->prepare('SELECT s.*,p.code AS plot_code FROM simulations s JOIN plots p ON p.plot_id=s.plot_id WHERE s.simulation_id=?');
$q->execute([$sid]);$sim=$q->fetch();
if(!$sim){fwrite(STDERR,"Simulation {$sid} not found.\n");exit(1);}

echo "Game2 dynamic opportunity audit\n";
echo "Simulation: {$sid} | Plot: {$sim['plot_code']} | Status: {$sim['status']} | Phase: {$sim['current_phase']} | t={$sim['elapsed_seconds']}s\n";
echo "Migration 029: ".($applied?'applied':'NOT APPLIED')."\n\n";

echo "Chosen decisions by time:\n";
$q=$pdo->prepare('SELECT d.opens_at_second,c.actor_type,pr.role_code,d.chosen_option_code,d.prompt_text FROM decisions d JOIN characters c ON c.character_id=d.character_id JOIN plot_roles pr ON pr.plot_role_id=c.plot_role_id WHERE d.simulation_id=? AND d.status="CHOSEN" ORDER BY d.opens_at_second,d.decision_id');
$q->execute([$sid]);$chosen=$q->fetchAll();
if(!$chosen)echo "  (none)\n";
foreach($chosen as $r)echo '  t='.(int)$r['opens_at_second'].'s | '.$r['actor_type'].' '.$r['role_code'].' | '.($r['chosen_option_code']??'null')."\n";

$q=$pdo->prepare('SELECT COUNT(*) FROM decisions d JOIN characters c ON c.character_id=d.character_id WHERE d.simulation_id=? AND c.actor_type="PLAYER" AND d.status="CHOSEN"');
$q->execute([$sid]);$playerCount=(int)$q->fetchColumn();
echo "Player chosen-action count: {$playerCount}\n\n";

$codes=['ROWAN_EXPLOIT_SILENCE','ROWAN_RESPOND_TO_ACTIVITY'];
foreach($codes as $code){
    $q=$pdo->prepare('SELECT d.dynamic_opportunity_id,d.prompt_text,d.active,d.priority FROM plot_dynamic_opportunities d WHERE d.plot_id=? AND d.opportunity_code=? LIMIT 1');
    $q->execute([$sim['plot_id'],$code]);$opp=$q->fetch();
    echo "{$code}:\n";
    if(!$opp){echo "  Definition: MISSING\n\n";continue;}
    echo '  Definition: present | active='.(int)$opp['active'].' | priority='.(int)$opp['priority']."\n";
    $q=$pdo->prepare('SELECT source_type,source_key,operator,compare_value FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=? ORDER BY sort_order,dynamic_condition_id');
    $q->execute([$opp['dynamic_opportunity_id']]);
    foreach($q->fetchAll() as $c)echo '  Condition: '.$c['source_type'].($c['source_key']!==null?':'.$c['source_key']:'').' '.$c['operator'].' '.$c['compare_value']."\n";
    $q=$pdo->prepare('SELECT sdo.status,sdo.created_at_second,d.chosen_option_code FROM simulation_dynamic_opportunities sdo LEFT JOIN decisions d ON d.decision_id=sdo.decision_id WHERE sdo.simulation_id=? AND sdo.dynamic_opportunity_id=?');
    $q->execute([$sid,$opp['dynamic_opportunity_id']]);$opened=$q->fetch();
    echo $opened
        ? '  Opened: yes | t='.(int)$opened['created_at_second'].'s | status='.$opened['status'].' | choice='.($opened['chosen_option_code']??'null')."\n\n"
        : "  Opened: no\n\n";
}

echo "Relevant final world state:\n";
foreach(['CROWN_HOLDER','ELIAN_CLAIM','ROWAN_CLAIM','LETTER_HOLDER','LETTER_STATUS'] as $key){
    $state=GenericStateService::getByKey($sid,$key);
    echo '  '.$key.': '.($state['exists']?(string)$state['value']:'(missing)')."\n";
}
