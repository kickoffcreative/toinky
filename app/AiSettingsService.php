<?php
require_once __DIR__.'/AiEndpointPolicy.php';
final class AiSettingsService
{
    public static function settings(): array
    {
        return [
            'provider'=>AiInteractionLogger::setting('provider','ollama')?:'ollama',
            'endpoint'=>AiEndpointPolicy::configuredEndpoint((string)AiInteractionLogger::setting('ollama_endpoint','')),
            'model'=>AiInteractionLogger::setting('ollama_model','qwen3:8b')?:'qwen3:8b',
            'timeout_seconds'=>AiInteractionLogger::setting('timeout_seconds','45')?:'45',
        ];
    }

    public static function save(int $actorId,array $input): void
    {
        $q=db()->prepare('SELECT 1 FROM users WHERE user_id=? AND status="ACTIVE" AND is_super_admin=1');$q->execute([$actorId]);if(!$q->fetchColumn())throw new RuntimeException('Super Admin access is required.');
        $target=AiEndpointPolicy::resolve(trim((string)($input['ai_endpoint']??'')));$endpoint=$target['url'];
        $serverEndpoint=AiEndpointPolicy::configuredEndpoint();if($serverEndpoint!==''&&$endpoint!==AiEndpointPolicy::validateUrl($serverEndpoint)['url'])throw new RuntimeException('The AI endpoint is managed by server configuration.');
        $model=trim((string)($input['ai_model']??'qwen3:8b'));if($model==='')$model='qwen3:8b';
        $timeout=max(10,min(120,(int)($input['ai_timeout_seconds']??45)));
        self::set('provider','ollama');self::set('ollama_endpoint',rtrim($endpoint,'/'));self::set('ollama_model',$model);self::set('timeout_seconds',(string)$timeout);
    }

    public static function diagnostics(): array
    {
        $settings=self::settings();$status=AiConnectionService::status();$endpoint=rtrim((string)$settings['endpoint'],'/');$checks=[];
        $checks[]=['label'=>'Configured URL','ok'=>$endpoint!=='','detail'=>$endpoint!==''?$endpoint:'No AI URL configured.'];
        $checks[]=['label'=>'PHP cURL','ok'=>function_exists('curl_init'),'detail'=>function_exists('curl_init')?'Available':'PHP cURL extension is unavailable.'];
        $checks[]=['label'=>'Provider enabled','ok'=>strtolower((string)$settings['provider'])==='ollama','detail'=>strtolower((string)$settings['provider'])==='ollama'?'Enabled':'AI provider is not enabled.'];
        $checks[]=['label'=>'Model','ok'=>trim((string)$settings['model'])!=='','detail'=>(string)$settings['model']];
        $checks[]=['label'=>'Connection','ok'=>!empty($status['online']),'detail'=>(string)($status['message']??(!empty($status['online'])?'Connected':'Connection failed'))];
        return ['settings'=>$settings,'status'=>$status,'checks'=>$checks];
    }

    private static function set(string $key,string $value): void
    {
        db()->prepare('INSERT INTO ai_reasoner_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute([$key,$value]);
    }
}
