<?php
final class AiInteractionLogger
{
    public static function start(array $meta,array $request,string $endpoint,string $provider,string $model): int
    {
        $json=json_encode($request,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if($json===false)$json='{}';
        $pdo=db();
        $q=$pdo->prepare('INSERT INTO ai_reasoner_interactions(simulation_id,character_id,decision_id,purpose,provider,model_name,endpoint,request_started_at_utc,request_bytes,prompt_chars,request_json) VALUES(?,?,?,?,?,?,?,UTC_TIMESTAMP(6),?,?,?)');
        $q->execute([
            $meta['simulation_id']??null,$meta['character_id']??null,$meta['decision_id']??null,
            $meta['purpose']??'NPC_OPTION_SELECTION',$provider,$model,self::safeEndpoint($endpoint),
            strlen($json),(int)($meta['prompt_chars']??0),self::storeRaw()?$json:null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function complete(int $id,array $result): void
    {
        $raw=(string)($result['response_raw']??'');
        $parsed=$result['parsed']??null;
        $parsedJson=$parsed===null?null:json_encode($parsed,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $prompt=(int)($result['prompt_eval_count']??0);$eval=(int)($result['eval_count']??0);
        $q=db()->prepare('UPDATE ai_reasoner_interactions SET request_completed_at_utc=UTC_TIMESTAMP(6),success=?,fallback_used=?,http_status=?,latency_ms=?,response_bytes=?,response_chars=?,prompt_eval_count=?,eval_count=?,total_token_count=?,total_duration_ns=?,load_duration_ns=?,prompt_eval_duration_ns=?,eval_duration_ns=?,selected_action_code=?,confidence=?,reason_text=?,response_json=?,parsed_response_json=?,error_text=?,host_cpu_percent=?,host_ram_used_mb=?,host_gpu_percent=?,host_vram_used_mb=?,host_gpu_power_watts=? WHERE ai_interaction_id=?');
        $q->execute([
            !empty($result['success'])?1:0,!empty($result['fallback_used'])?1:0,$result['http_status']??null,$result['latency_ms']??null,
            strlen($raw),mb_strlen($raw),$result['prompt_eval_count']??null,$result['eval_count']??null,($prompt||$eval)?$prompt+$eval:null,
            $result['total_duration_ns']??null,$result['load_duration_ns']??null,$result['prompt_eval_duration_ns']??null,$result['eval_duration_ns']??null,
            $result['selected_action']??null,$result['confidence']??null,$result['reason']??null,self::storeRaw()?$raw:null,$parsedJson,
            $result['error']??null,$result['host_cpu_percent']??null,$result['host_ram_used_mb']??null,$result['host_gpu_percent']??null,$result['host_vram_used_mb']??null,$result['host_gpu_power_watts']??null,$id,
        ]);
    }

    public static function setting(string $key,?string $default=null): ?string
    {
        try{$q=db()->prepare('SELECT setting_value FROM ai_reasoner_settings WHERE setting_key=?');$q->execute([$key]);$v=$q->fetchColumn();return $v===false?$default:(string)$v;}catch(Throwable $e){return $default;}
    }

    private static function storeRaw(): bool{return self::setting('store_raw_payloads','1')!=='0';}
    private static function safeEndpoint(string $endpoint): string
    {
        $p=parse_url($endpoint);if(!$p)return $endpoint;
        $scheme=$p['scheme']??'http';$host=$p['host']??'';$port=isset($p['port'])?':'.$p['port']:'';$path=$p['path']??'';
        return $scheme.'://'.$host.$port.$path;
    }
}
