<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/MigrationService.php';
MigrationService::run();
require_once $root.'/app/StoryAuthoringService.php';

$pdo=db();
$writer=$pdo->query('SELECT user_id,is_admin FROM users WHERE is_admin=1 ORDER BY user_id LIMIT 1')->fetch();
$opportunity=$pdo->query('SELECT o.*,t.template_id,t.actor_type template_actor_type FROM plot_dynamic_opportunities o JOIN plot_decision_templates t ON t.plot_id=o.plot_id AND t.event_code=o.opportunity_code AND t.role_code=o.role_code ORDER BY o.dynamic_opportunity_id LIMIT 1')->fetch();
if(!$writer||!$opportunity)throw new RuntimeException('Dynamic opportunity mirror test needs an administrator and an existing mirrored opportunity.');
$definitionQuery=$pdo->prepare('SELECT * FROM plot_state_definitions WHERE plot_id=? AND active=1 ORDER BY CASE value_type WHEN "NUMERIC" THEN 0 WHEN "PRESET" THEN 1 ELSE 2 END,plot_state_definition_id LIMIT 1');
$definitionQuery->execute([$opportunity['plot_id']]);$definition=$definitionQuery->fetch();
if(!$definition)throw new RuntimeException('Dynamic opportunity mirror test needs a managed Story Value.');

$code='MIRROR_SYNC_TEST_'.strtoupper(bin2hex(random_bytes(3)));
$delta=$definition['value_type']==='NUMERIC'?1:null;$setValue=null;
if($definition['value_type']==='PRESET'){$values=array_values(array_filter(array_map('trim',preg_split('/\r?\n/',(string)$definition['allowed_values_text'])?:[])));$setValue=$values[0]??null;}
elseif($definition['value_type']==='TEXT')$setValue='Mirror synchronization test value';

$pdo->beginTransaction();
try{
    $optionId=StoryAuthoringService::saveDynamicOption($writer,(int)$opportunity['plot_id'],[
        'dynamic_opportunity_id'=>$opportunity['dynamic_opportunity_id'],'option_code'=>$code,
        'label'=>'Test newly added mirrored choice','result_text'=>'The test choice is applied.',
        'npc_weight'=>27,'rationale_text'=>'Regression coverage for conditional choice mirroring.',
        'sort_order'=>987,'active'=>1,
    ]);
    $mirrorQuery=$pdo->prepare('SELECT x.*,t.actor_type FROM plot_decision_template_options x JOIN plot_decision_templates t ON t.template_id=x.template_id WHERE t.plot_id=? AND t.event_code=? AND t.role_code=? AND x.option_code=?');
    $mirrorQuery->execute([$opportunity['plot_id'],$opportunity['opportunity_code'],$opportunity['role_code'],$code]);$mirror=$mirrorQuery->fetch();
    if(!$mirror||(int)$mirror['active']!==1)throw new RuntimeException('Saving a new conditional Choice did not create its active behavior mirror.');
    if((string)$mirror['actor_type']!==(string)$opportunity['template_actor_type'])throw new RuntimeException('Authoring synchronization changed the existing template actor type.');

    StoryAuthoringService::saveDynamicEffect($writer,(int)$opportunity['plot_id'],[
        'dynamic_option_id'=>$optionId,'effect_scope'=>$definition['effect_scope'],'target_code'=>$definition['target_code'],
        'delta_value'=>$delta,'set_value'=>$setValue,'detail_text'=>'Mirrored test consequence.',
        'sort_order'=>1,'active'=>1,
    ]);
    $effectQuery=$pdo->prepare('SELECT e.* FROM plot_option_effects e JOIN plot_decision_template_options x ON x.template_option_id=e.template_option_id WHERE x.template_option_id=? AND e.effect_scope=? AND e.target_code=? ORDER BY e.effect_id DESC LIMIT 1');
    $effectQuery->execute([$mirror['template_option_id'],$definition['effect_scope'],$definition['target_code']]);$effect=$effectQuery->fetch();
    if(!$effect||(int)$effect['active']!==1||$effect['detail_text']!=='Mirrored test consequence.')throw new RuntimeException('Saving a conditional Effect did not synchronize its behavior mirror.');

    $pdo->prepare('DELETE FROM plot_decision_template_options WHERE template_option_id=?')->execute([$mirror['template_option_id']]);
    DynamicOpportunityService::syncBehaviorTemplateById((int)$opportunity['dynamic_opportunity_id']);
    $mirrorQuery->execute([$opportunity['plot_id'],$opportunity['opportunity_code'],$opportunity['role_code'],$code]);$repaired=$mirrorQuery->fetch();
    if(!$repaired||(int)$repaired['active']!==1)throw new RuntimeException('Opening-time synchronization did not repair a missing mirrored Choice.');
    $effectQuery->execute([$repaired['template_option_id'],$definition['effect_scope'],$definition['target_code']]);$repairedEffect=$effectQuery->fetch();
    if(!$repairedEffect||(int)$repairedEffect['active']!==1)throw new RuntimeException('Opening-time synchronization did not repair the missing mirrored Effect.');

    StoryAuthoringService::saveDynamicOption($writer,(int)$opportunity['plot_id'],[
        'dynamic_opportunity_id'=>$opportunity['dynamic_opportunity_id'],'dynamic_option_id'=>$optionId,
        'option_code'=>$code,'label'=>'Test newly added mirrored choice','result_text'=>'The test choice is applied.',
        'npc_weight'=>27,'rationale_text'=>'Regression coverage for conditional choice mirroring.',
        'sort_order'=>987,'active'=>0,
    ]);
    $mirrorQuery->execute([$opportunity['plot_id'],$opportunity['opportunity_code'],$opportunity['role_code'],$code]);
    if((int)$mirrorQuery->fetch()['active']!==0)throw new RuntimeException('Retiring a conditional Choice did not retire its behavior mirror.');
    $pdo->rollBack();
}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw$error;}

echo "Dynamic opportunity Choice and Effect mirror synchronization passed.\n";
