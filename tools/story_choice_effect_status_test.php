<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$writerSource=file_get_contents($root.'/public/writer.php');
if($writerSource===false)throw new RuntimeException('Story Writer source could not be read.');
foreach([
    "const surface=formSurface(form)||active.closest('[data-entry-type]')",
    "if(surface.matches('details'))surface.querySelector(':scope > summary')?.append(badge)",
]as$required)if(!str_contains($writerSource,$required))throw new RuntimeException('Choice Effect inactive card status is missing '.$required.'.');
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/MigrationService.php';
MigrationService::run();
require_once $root.'/app/StoryAuthoringService.php';

$pdo=db();
$writer=$pdo->query('SELECT user_id,is_admin FROM users WHERE is_admin=1 ORDER BY user_id LIMIT 1')->fetch();
$regular=$pdo->query('SELECT o.*,t.plot_id FROM plot_decision_template_options o JOIN plot_decision_templates t ON t.template_id=o.template_id ORDER BY o.template_option_id LIMIT 1')->fetch();
$dynamic=$pdo->query('SELECT x.*,o.plot_id FROM plot_dynamic_opportunity_options x JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=x.dynamic_opportunity_id ORDER BY x.dynamic_option_id LIMIT 1')->fetch();
$regularEffect=$pdo->query('SELECT e.*,t.plot_id FROM plot_option_effects e JOIN plot_decision_template_options o ON o.template_option_id=e.template_option_id JOIN plot_decision_templates t ON t.template_id=o.template_id JOIN plot_state_definitions d ON d.plot_id=t.plot_id AND d.effect_scope=e.effect_scope AND d.target_code=e.target_code ORDER BY e.effect_id LIMIT 1')->fetch();
$dynamicEffect=$pdo->query('SELECT e.*,o.plot_id FROM plot_dynamic_option_effects e JOIN plot_dynamic_opportunity_options x ON x.dynamic_option_id=e.dynamic_option_id JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=x.dynamic_opportunity_id JOIN plot_state_definitions d ON d.plot_id=o.plot_id AND d.effect_scope=e.effect_scope AND d.target_code=e.target_code ORDER BY e.dynamic_effect_id LIMIT 1')->fetch();
if(!$writer||!$regular||!$dynamic||!$regularEffect||!$dynamicEffect)throw new RuntimeException('Choice/effect status test needs seeded choices, effects, and managed Story Values.');

$pdo->beginTransaction();
try{
    StoryAuthoringService::saveOption($writer,(int)$regular['plot_id'],[
        'template_id'=>$regular['template_id'],'template_option_id'=>$regular['template_option_id'],
        'option_code'=>$regular['option_code'],'label'=>$regular['label'],'npc_weight'=>$regular['npc_weight'],
        'rationale_text'=>$regular['rationale_text'],'result_text'=>$regular['result_text'],
        'sort_order'=>$regular['sort_order'],'active'=>0,
    ]);
    StoryAuthoringService::saveDynamicOption($writer,(int)$dynamic['plot_id'],[
        'dynamic_opportunity_id'=>$dynamic['dynamic_opportunity_id'],'dynamic_option_id'=>$dynamic['dynamic_option_id'],
        'option_code'=>$dynamic['option_code'],'label'=>$dynamic['label'],'npc_weight'=>$dynamic['npc_weight'],
        'rationale_text'=>$dynamic['rationale_text'],'result_text'=>$dynamic['result_text'],
        'sort_order'=>$dynamic['sort_order'],'active'=>0,
    ]);
    StoryAuthoringService::saveOptionEffect($writer,(int)$regularEffect['plot_id'],[
        'template_option_id'=>$regularEffect['template_option_id'],'effect_id'=>$regularEffect['effect_id'],
        'effect_scope'=>$regularEffect['effect_scope'],'target_code'=>$regularEffect['target_code'],
        'delta_value'=>$regularEffect['delta_value'],'set_value'=>$regularEffect['set_value'],
        'detail_text'=>$regularEffect['detail_text'],'sort_order'=>$regularEffect['sort_order'],'active'=>0,
    ]);
    StoryAuthoringService::saveDynamicEffect($writer,(int)$dynamicEffect['plot_id'],[
        'dynamic_option_id'=>$dynamicEffect['dynamic_option_id'],'dynamic_effect_id'=>$dynamicEffect['dynamic_effect_id'],
        'effect_scope'=>$dynamicEffect['effect_scope'],'target_code'=>$dynamicEffect['target_code'],
        'delta_value'=>$dynamicEffect['delta_value'],'set_value'=>$dynamicEffect['set_value'],
        'detail_text'=>$dynamicEffect['detail_text'],'sort_order'=>$dynamicEffect['sort_order'],'active'=>0,
    ]);
    $pdo->prepare('UPDATE plot_option_effects SET active=0 WHERE template_option_id=?')->execute([$regularEffect['template_option_id']]);
    $pdo->prepare('UPDATE plot_dynamic_option_effects SET active=0 WHERE dynamic_option_id=?')->execute([$dynamicEffect['dynamic_option_id']]);
    foreach([
        ['plot_decision_template_options','template_option_id',$regular['template_option_id']],
        ['plot_dynamic_opportunity_options','dynamic_option_id',$dynamic['dynamic_option_id']],
        ['plot_option_effects','effect_id',$regularEffect['effect_id']],
        ['plot_dynamic_option_effects','dynamic_effect_id',$dynamicEffect['dynamic_effect_id']],
    ] as[$table,$key,$id]){$q=$pdo->prepare('SELECT active FROM '.$table.' WHERE '.$key.'=?');$q->execute([$id]);if((int)$q->fetchColumn()!==0)throw new RuntimeException($table.' did not become inactive.');}
    $usage=StoryAuthoringService::stableCodeUsageMap((int)$regularEffect['plot_id']);
    if(isset($usage['option'][(int)$regularEffect['template_option_id']]['EFFECTS']))throw new RuntimeException('An inactive effect still locked its Option Code.');
    $usage=StoryAuthoringService::stableCodeUsageMap((int)$dynamicEffect['plot_id']);
    if(isset($usage['dynamic_option'][(int)$dynamicEffect['dynamic_option_id']]['EFFECTS']))throw new RuntimeException('An inactive conditional effect still locked its Choice Code.');
    $pdo->rollBack();
}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}

echo "Story choice/effect active state and dependency locking passed.\n";
