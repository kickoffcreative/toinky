<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only.');}

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/MigrationService.php';
MigrationService::run();
require_once $root.'/app/StoryAuthoringService.php';

$pdo=db();
$writer=$pdo->query('SELECT user_id,is_admin FROM users WHERE is_admin=1 ORDER BY user_id LIMIT 1')->fetch();
if(!$writer)throw new RuntimeException('Condition status test needs an administrator fixture.');
$dynamic=$pdo->query('SELECT c.*,o.plot_id FROM plot_dynamic_opportunity_conditions c JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=c.dynamic_opportunity_id ORDER BY c.dynamic_condition_id LIMIT 1')->fetch();
$ending=$pdo->query('SELECT c.*,f.plot_id FROM plot_resolution_conditions c JOIN plot_resolution_fragments f ON f.resolution_fragment_id=c.resolution_fragment_id ORDER BY c.resolution_condition_id LIMIT 1')->fetch();
if(!$dynamic||!$ending)throw new RuntimeException('Condition status test needs authored opportunity and ending conditions.');

$pdo->beginTransaction();
try{
    StoryAuthoringService::saveDynamicCondition($writer,(int)$dynamic['plot_id'],[
        'dynamic_opportunity_id'=>(int)$dynamic['dynamic_opportunity_id'],
        'dynamic_condition_id'=>(int)$dynamic['dynamic_condition_id'],
        'source_type'=>'PHASE','source_key'=>null,'operator'=>'IN',
        'compare_value'=>['INCITING_INCIDENT','RISING_ACTION'],
        'sort_order'=>$dynamic['sort_order'],'active'=>0,
    ]);
    $q=$pdo->prepare('SELECT source_type,source_key,compare_value,active FROM plot_dynamic_opportunity_conditions WHERE dynamic_condition_id=?');$q->execute([$dynamic['dynamic_condition_id']]);$phase=$q->fetch();
    if(!$phase||$phase['source_type']!=='PHASE'||$phase['source_key']!==null||$phase['compare_value']!=='INCITING_INCIDENT,RISING_ACTION'||(int)$phase['active']!==0)throw new RuntimeException('Opportunity condition status or phase values were not normalized correctly.');

    StoryAuthoringService::saveDynamicCondition($writer,(int)$dynamic['plot_id'],[
        'dynamic_opportunity_id'=>(int)$dynamic['dynamic_opportunity_id'],
        'dynamic_condition_id'=>(int)$dynamic['dynamic_condition_id'],
        'source_type'=>'PHASE','source_key'=>null,'operator'=>'IN',
        'compare_value'=>['INCITING_INCIDENT','RISING_ACTION'],
        'sort_order'=>$dynamic['sort_order'],'active'=>1,
    ]);
    $q=$pdo->prepare('SELECT source_type,source_key,compare_value,active FROM plot_dynamic_opportunity_conditions WHERE dynamic_condition_id=?');$q->execute([$dynamic['dynamic_condition_id']]);$phase=$q->fetch();
    if(!$phase||$phase['source_type']!=='PHASE'||$phase['source_key']!==null||$phase['compare_value']!=='INCITING_INCIDENT,RISING_ACTION'||(int)$phase['active']!==1)throw new RuntimeException('Phase dropdown values were not normalized correctly.');

    $q=$pdo->prepare('UPDATE plot_resolution_conditions SET active=0 WHERE resolution_condition_id=?');$q->execute([$ending['resolution_condition_id']]);
    $q=$pdo->prepare('SELECT active FROM plot_resolution_conditions WHERE resolution_condition_id=?');$q->execute([$ending['resolution_condition_id']]);
    if((int)$q->fetchColumn()!==0)throw new RuntimeException('Ending condition did not become inactive.');
    $pdo->rollBack();
}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}

echo "Story condition active state and phase selection passed.\n";
