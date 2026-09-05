<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/InkyCowriterService.php';

$actions=[
    'whats_next',
    'review_consistency',
    'scenario_map',
    'review_plot',
    'review_characters',
    'review_opportunities',
    'dependencies',
    'quality_check',
    'focused_tests',
];
$purposes=[
    'whats_next'=>'INKY_WHATS_NEXT',
    'review_consistency'=>'INKY_STORY_CONSISTENCY',
    'scenario_map'=>null,
    'review_plot'=>'INKY_REVIEW_PLOT',
    'review_characters'=>'INKY_CHARACTER_KNOWLEDGE',
    'review_opportunities'=>'INKY_OPPORTUNITY_REVIEW',
    'dependencies'=>'INKY_DEPENDENCY_CHECK',
    'quality_check'=>'INKY_QUALITY_CHECK',
    'focused_tests'=>'INKY_FOCUSED_TESTS',
];
$target=trim((string)($argv[1]??''));
if($target===''||in_array(strtolower($target),['-h','--help','help'],true)){
    fwrite(STDOUT,"Usage: php tools/inky_review_batch.php <plot_id|story_code|all> [action ...]\n");
    fwrite(STDOUT,"Actions: ".implode(', ',$actions)."\n");
    fwrite(STDOUT,"Runs actions sequentially and records each AI interaction. Results are not saved to the browser review panel.\n");
    exit($target===''?2:0);
}
$requested=array_slice($argv,2);
if($requested!==[]){
    $unknown=array_values(array_diff($requested,$actions));
    if($unknown!==[])throw new RuntimeException('Unknown action(s): '.implode(', ',$unknown));
    $actions=$requested;
}
$pdo=db();
$admin=$pdo->query('SELECT user_id FROM users WHERE is_admin=1 ORDER BY user_id LIMIT 1')->fetch();
if(!$admin)throw new RuntimeException('An administrator account is required to run the batch.');
$writer=['user_id'=>(int)$admin['user_id'],'is_admin'=>1];
if(strtolower($target)==='all'){
    $stories=$pdo->query('SELECT plot_id,code,title,workflow_status,status FROM plots ORDER BY plot_id')->fetchAll();
}elseif(ctype_digit($target)){
    $query=$pdo->prepare('SELECT plot_id,code,title,workflow_status,status FROM plots WHERE plot_id=? LIMIT 1');
    $query->execute([(int)$target]);$stories=$query->fetchAll();
}else{
    $query=$pdo->prepare('SELECT plot_id,code,title,workflow_status,status FROM plots WHERE code=? LIMIT 1');
    $query->execute([$target]);$stories=$query->fetchAll();
}
if($stories===[])throw new RuntimeException('No matching story was found.');

$countItems=static function(array$result): int{
    foreach(['next_steps','review','character_knowledge','opportunity_review','dependency_check','quality_check','focused_tests']as$key){
        if(!isset($result[$key])||!is_array($result[$key]))continue;
        foreach(['steps','recommendations','issues','opportunities','problems','checks','tests']as$list)if(isset($result[$key][$list])&&is_array($result[$key][$list]))return count($result[$key][$list]);
    }
    if(isset($result['scenario_map']['combination_count'])&&$result['scenario_map']['combination_count']!==null)return(int)$result['scenario_map']['combination_count'];
    return 0;
};
$summaryText=static function(array$result): string{
    foreach(['next_steps','review','character_knowledge','opportunity_review','dependency_check','quality_check','focused_tests','scenario_map']as$key)if(isset($result[$key]['summary']))return trim((string)$result[$key]['summary']);
    return '';
};
$latestInteraction=static function(PDO$pdo,string$purpose,int$afterId): ?int{
    $query=$pdo->prepare('SELECT ai_interaction_id FROM ai_reasoner_interactions WHERE purpose=? AND ai_interaction_id>? ORDER BY ai_interaction_id DESC LIMIT 1');
    $query->execute([$purpose,$afterId]);$id=$query->fetchColumn();return$id===false?null:(int)$id;
};
$passed=0;$failed=0;
foreach($stories as$story){
    $plotId=(int)$story['plot_id'];
    foreach($actions as$action){
        $started=hrtime(true);
        $beforeId=(int)$pdo->query('SELECT COALESCE(MAX(ai_interaction_id),0) FROM ai_reasoner_interactions')->fetchColumn();
        try{
            $result=InkyCowriterService::run($writer,$plotId,$action);
            $passed++;
            echo json_encode(['plot_id'=>$plotId,'code'=>$story['code'],'title'=>$story['title'],'action'=>$action,'success'=>true,'interaction_id'=>$purposes[$action]!==null?$latestInteraction($pdo,$purposes[$action],$beforeId):null,'items'=>$countItems($result),'summary'=>$summaryText($result),'elapsed_ms'=>round((hrtime(true)-$started)/1e6,2)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
        }catch(Throwable$e){
            $failed++;
            echo json_encode(['plot_id'=>$plotId,'code'=>$story['code'],'title'=>$story['title'],'action'=>$action,'success'=>false,'interaction_id'=>$purposes[$action]!==null?$latestInteraction($pdo,$purposes[$action],$beforeId):null,'error'=>$e->getMessage(),'elapsed_ms'=>round((hrtime(true)-$started)/1e6,2)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
        }
    }
}
echo "Completed: {$passed} passed, {$failed} failed.\n";
exit($failed>0?1:0);
