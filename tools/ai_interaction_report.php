<?php
$root=dirname(__DIR__);require_once $root.'/app/bootstrap.php';
$id=(int)($argv[1]??0);if($id<=0){fwrite(STDERR,"Usage: php tools/ai_interaction_report.php <interaction_id>\n");exit(2);} 
$q=db()->prepare('SELECT * FROM ai_reasoner_interactions WHERE ai_interaction_id=?');$q->execute([$id]);$r=$q->fetch();if(!$r){fwrite(STDERR,"AI interaction not found.\n");exit(1);} 
$ms=static fn($ns)=>$ns===null?null:round(((float)$ns)/1e6,2);
$out=[
 'interaction_id'=>(int)$r['ai_interaction_id'],
 'success'=>(bool)$r['success'],
 'fallback_used'=>(bool)$r['fallback_used'],
 'provider'=>$r['provider'],
 'model'=>$r['model_name'],
 'purpose'=>$r['purpose'],
 'simulation_id'=>$r['simulation_id']===null?null:(int)$r['simulation_id'],
 'character_id'=>$r['character_id']===null?null:(int)$r['character_id'],
 'decision_id'=>$r['decision_id']===null?null:(int)$r['decision_id'],
 'http_status'=>$r['http_status']===null?null:(int)$r['http_status'],
 'latency_ms'=>$r['latency_ms']===null?null:round((float)$r['latency_ms'],2),
 'request_bytes'=>(int)$r['request_bytes'],
 'response_bytes'=>(int)$r['response_bytes'],
 'prompt_chars'=>(int)$r['prompt_chars'],
 'response_chars'=>(int)$r['response_chars'],
 'prompt_tokens'=>$r['prompt_eval_count']===null?null:(int)$r['prompt_eval_count'],
 'generated_tokens'=>$r['eval_count']===null?null:(int)$r['eval_count'],
 'total_tokens'=>$r['total_token_count']===null?null:(int)$r['total_token_count'],
 'ollama_total_ms'=>$ms($r['total_duration_ns']),
 'model_load_ms'=>$ms($r['load_duration_ns']),
 'prompt_eval_ms'=>$ms($r['prompt_eval_duration_ns']),
 'generation_ms'=>$ms($r['eval_duration_ns']),
 'selected_action'=>$r['selected_action_code'],
 'confidence'=>$r['confidence']===null?null:(float)$r['confidence'],
 'reason'=>$r['reason_text'],
 'error'=>$r['error_text'],
 'host_telemetry'=>[
   'cpu_percent'=>$r['host_cpu_percent']===null?null:(float)$r['host_cpu_percent'],
   'ram_used_mb'=>$r['host_ram_used_mb']===null?null:(float)$r['host_ram_used_mb'],
   'gpu_percent'=>$r['host_gpu_percent']===null?null:(float)$r['host_gpu_percent'],
   'vram_used_mb'=>$r['host_vram_used_mb']===null?null:(float)$r['host_vram_used_mb'],
   'gpu_power_watts'=>$r['host_gpu_power_watts']===null?null:(float)$r['host_gpu_power_watts'],
 ],
];
echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
