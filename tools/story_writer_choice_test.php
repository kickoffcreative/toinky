<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/MigrationService.php';
MigrationService::run();
require_once $root.'/app/StoryAuthoringService.php';

$pdo=db();
$writer=$pdo->query('SELECT user_id,is_admin FROM users WHERE is_admin=1 ORDER BY user_id LIMIT 1')->fetch();
$opportunity=$pdo->query('SELECT dynamic_opportunity_id,plot_id FROM plot_dynamic_opportunities ORDER BY dynamic_opportunity_id LIMIT 1')->fetch();
if(!$writer||!$opportunity)throw new RuntimeException('Choice test needs an administrator and a conditional opportunity fixture.');

$code='CHOICE_TEST_'.strtoupper(bin2hex(random_bytes(5)));
$pdo->beginTransaction();
try{
    $id=StoryAuthoringService::saveDynamicOption($writer,(int)$opportunity['plot_id'],[
        'dynamic_opportunity_id'=>(int)$opportunity['dynamic_opportunity_id'],
        'option_code'=>$code,
        'label'=>'Test Conditional Choice',
        'npc_weight'=>3,
        'rationale_text'=>'Used only to verify the Story Writer insertion path.',
        'result_text'=>'The temporary test choice was accepted.',
        'sort_order'=>999,
        'active'=>1,
    ]);
    $q=$pdo->prepare('SELECT option_code,label,result_text,npc_weight,rationale_text,sort_order,active FROM plot_dynamic_opportunity_options WHERE dynamic_option_id=?');
    $q->execute([$id]);$saved=$q->fetch();
    if(!$saved||$saved['option_code']!==$code||$saved['label']!=='Test Conditional Choice'||$saved['result_text']!=='The temporary test choice was accepted.'||(int)$saved['npc_weight']!==3||(int)$saved['sort_order']!==999||(int)$saved['active']!==1)throw new RuntimeException('Conditional Choice fields were not stored correctly.');
    $pdo->rollBack();
}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}

echo "Story Writer Conditional Choice insertion passed.\n";
