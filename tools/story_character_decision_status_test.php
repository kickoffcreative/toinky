<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/MigrationService.php';
MigrationService::run();
require_once $root.'/app/StoryAuthoringService.php';

$pdo=db();
$writer=$pdo->query('SELECT user_id,is_admin FROM users WHERE is_admin=1 ORDER BY user_id LIMIT 1')->fetch();
$decision=$pdo->query('SELECT * FROM plot_decision_templates ORDER BY template_id LIMIT 1')->fetch();
$character=$pdo->query('SELECT * FROM plot_roles ORDER BY plot_role_id LIMIT 1')->fetch();
if(!$writer||!$decision||!$character)throw new RuntimeException('Character/decision status test needs seeded records.');

$pdo->beginTransaction();
try{
    StoryAuthoringService::saveDecision($writer,(int)$decision['plot_id'],[
        'template_id'=>$decision['template_id'],'event_code'=>$decision['event_code'],
        'role_code'=>$decision['role_code'],'actor_type'=>$decision['actor_type'],
        'prompt_text'=>$decision['prompt_text'],'decision_window_seconds'=>$decision['decision_window_seconds'],
        'active'=>0,
    ]);
    StoryAuthoringService::saveCharacter($writer,(int)$character['plot_id'],[
        'plot_role_id'=>$character['plot_role_id'],'role_code'=>$character['role_code'],
        'role_type'=>$character['role_type'],'slot_no'=>$character['slot_no'],'name'=>$character['name'],
        'public_description'=>$character['public_description'],'private_knowledge'=>$character['private_knowledge'],
        'starting_location'=>$character['starting_location'],'goal_text'=>$character['goal_text'],
        'fear_text'=>$character['fear_text'],'pronoun_set'=>$character['pronoun_set'],
        'simulation_active'=>$character['simulation_active'],'active'=>0,
    ]);
    $q=$pdo->prepare('SELECT active FROM plot_decision_templates WHERE template_id=?');$q->execute([$decision['template_id']]);
    if((int)$q->fetchColumn()!==0)throw new RuntimeException('Decision did not become inactive.');
    $q=$pdo->prepare('SELECT active FROM plot_roles WHERE plot_role_id=?');$q->execute([$character['plot_role_id']]);
    if((int)$q->fetchColumn()!==0)throw new RuntimeException('Character did not become inactive.');
    $details=StoryAuthoringService::dependencyDetails((int)$decision['plot_id']);
    if(!isset($details['decision'][(int)$decision['template_id']]))throw new RuntimeException('Decision dependency details were not enumerated.');
    $pdo->rollBack();
}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}

echo "Story character/decision active state and dependency details passed.\n";
