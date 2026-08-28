<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/app/bootstrap.php';
require_once dirname(__DIR__).'/app/StoryAuthoringService.php';

$pdo=db();
$row=$pdo->query("SELECT d.*,p.plot_id FROM plot_state_definitions d JOIN plots p ON p.plot_id=d.plot_id WHERE EXISTS(SELECT 1 FROM plot_option_effects e JOIN plot_decision_template_options o ON o.template_option_id=e.template_option_id JOIN plot_decision_templates t ON t.template_id=o.template_id WHERE t.plot_id=d.plot_id AND e.effect_scope=d.effect_scope AND e.target_code=d.target_code) OR EXISTS(SELECT 1 FROM plot_dynamic_option_effects e JOIN plot_dynamic_opportunity_options o ON o.dynamic_option_id=e.dynamic_option_id JOIN plot_dynamic_opportunities x ON x.dynamic_opportunity_id=o.dynamic_opportunity_id WHERE x.plot_id=d.plot_id AND e.effect_scope=d.effect_scope AND e.target_code=d.target_code) LIMIT 1")->fetch();
$user=$pdo->query('SELECT user_id FROM users ORDER BY is_admin DESC,user_id LIMIT 1')->fetch();
if(!$row||!$user)throw new RuntimeException('Story-value type guard test needs a seeded, used Story Value.');

$writer=['user_id'=>(int)$user['user_id'],'is_admin'=>1];
$nextType=$row['value_type']==='NUMERIC'?'PRESET':'NUMERIC';
$pdo->beginTransaction();
try{
    $blocked=false;
    try{
        StoryAuthoringService::saveStateDefinition($writer,(int)$row['plot_id'],[
            'plot_state_definition_id'=>(int)$row['plot_state_definition_id'],
            'effect_scope'=>$row['effect_scope'],
            'target_code'=>$row['target_code'],
            'label_text'=>$row['label_text'],
            'value_type'=>$nextType,
            'default_numeric_value'=>0,
            'allowed_values_text'=>"VALID\nOTHER",
            'description'=>$row['description'],
            'sort_order'=>(int)$row['sort_order'],
            'active'=>(int)$row['active'],
        ]);
    }catch(RuntimeException $error){$blocked=str_contains($error->getMessage(),'Update these uses first:')&&str_contains($error->getMessage(),'OPTION ');}
    if(!$blocked)throw new RuntimeException('An incompatible Story Value type change was not blocked with its affected option key.');
    $pdo->rollBack();
    echo "Story Value type-change safeguards passed.\n";
}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw$error;}
