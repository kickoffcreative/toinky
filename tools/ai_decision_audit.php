<?php
$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';

$pdo=db();
$sid=(int)($argv[1]??0);
if($sid<=0)$sid=(int)$pdo->query('SELECT COALESCE(MAX(simulation_id),0) FROM simulations')->fetchColumn();
if($sid<=0){fwrite(STDERR,"No simulations found.\n");exit(1);}

$q=$pdo->prepare('SELECT a.*,c.name AS character_name,d.prompt_text,d.opens_at_second,d.chosen_option_code,d.resolved_text FROM ai_reasoner_interactions a LEFT JOIN characters c ON c.character_id=a.character_id LEFT JOIN decisions d ON d.decision_id=a.decision_id WHERE a.simulation_id=? ORDER BY a.ai_interaction_id');
$q->execute([$sid]);
$rows=$q->fetchAll();

echo "Game2 AI decision audit\nSimulation: {$sid}\nAI interactions: ".count($rows)."\n\n";
if(!$rows){echo "No AI interactions found for this simulation.\n";exit(0);}

$totals=['success'=>0,'fail'=>0,'tokens'=>0,'latency'=>0.0];
foreach($rows as $i=>$r){
    $totals[$r['success']?'success':'fail']++;
    $totals['tokens']+=(int)($r['total_token_count']??0);
    $totals['latency']+=(float)($r['latency_ms']??0);

    $request=[];
    if(!empty($r['request_json'])){
        try{$request=json_decode($r['request_json'],true,512,JSON_THROW_ON_ERROR);}catch(Throwable $e){}
    }
    $story=[];
    foreach(($request['messages']??[]) as $m){
        if(($m['role']??'')!=='user')continue;
        $text=(string)($m['content']??'');
        $pos=strpos($text,"\n");
        $json=$pos===false?$text:substr($text,$pos+1);
        try{$story=json_decode($json,true,512,JSON_THROW_ON_ERROR);}catch(Throwable $e){}
    }

    echo 'Decision '.($i+1).' | interaction '.(int)$r['ai_interaction_id'].' | t='.(int)($r['opens_at_second']??0)."s\n";
    echo 'Character: '.($r['character_name']??'unknown')."\n";
    echo 'Prompt: '.trim((string)($r['prompt_text']??''))."\n";
    if(!empty($story['character'])){
        $ch=$story['character'];
        echo 'Goal: '.trim((string)($ch['goal']??''))."\n";
        echo 'Fear: '.trim((string)($ch['fear']??''))."\n";
        echo 'Location: '.trim((string)($ch['location']??''))."\n";
        if(!empty($ch['stats']))echo 'Stats: '.json_encode($ch['stats'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
    }
    echo "Legal options:\n";
    foreach(($story['available_actions']??[]) as $o){
        echo '  - '.($o['code']??'?').': '.($o['label']??'')."\n";
    }
    echo 'Qwen choice: '.($r['selected_action_code']??'null').' | confidence='.($r['confidence']===null?'null':$r['confidence'])."\n";
    echo 'Reason: '.trim((string)($r['reason_text']??''))."\n";
    echo 'Engine chosen: '.($r['chosen_option_code']??'null')."\n";
    echo 'Result: '.trim((string)($r['resolved_text']??''))."\n";
    echo 'Tokens: '.(int)($r['total_token_count']??0).' (prompt '.(int)($r['prompt_eval_count']??0).' + generated '.(int)($r['eval_count']??0).')'."\n";
    echo 'Latency: '.round((float)($r['latency_ms']??0),2).' ms | Ollama: '.round(((float)($r['total_duration_ns']??0))/1e6,2)." ms\n";
    if(!$r['success'])echo 'ERROR: '.($r['error_text']??'unknown')."\n";

    if(!empty($r['decision_id'])){
        try{
            $fh=$pdo->prepare('SELECT factor_code,old_value,new_value,detail_text,event_second FROM simulation_factor_history WHERE simulation_id=? AND source_id=? ORDER BY event_second,factor_code');
            $fh->execute([$sid,$r['decision_id']]);
            $effects=$fh->fetchAll();
        }catch(Throwable $e){
            $effects=[];
        }
        if($effects){echo "Factor effects:\n";foreach($effects as $e)echo '  - '.$e['factor_code'].': '.$e['old_value'].' -> '.$e['new_value'].($e['detail_text']?' | '.$e['detail_text']:'')."\n";}
    }
    echo str_repeat('-',72)."\n";
}

echo 'Summary: successes='.$totals['success'].' failures='.$totals['fail'].' total_tokens='.$totals['tokens'].' total_latency='.round($totals['latency']/1000,2)."s\n";
