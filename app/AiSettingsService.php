<?php
require_once __DIR__.'/UserFacingException.php';
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
        $q=db()->prepare('SELECT 1 FROM users WHERE user_id=? AND status="ACTIVE" AND is_super_admin=1');$q->execute([$actorId]);if(!$q->fetchColumn())throw new UserFacingException('Super Admin access is required.');
        $target=AiEndpointPolicy::resolve(trim((string)($input['ai_endpoint']??'')));$endpoint=$target['url'];
        $serverEndpoint=AiEndpointPolicy::configuredEndpoint();if($serverEndpoint!==''&&$endpoint!==AiEndpointPolicy::validateUrl($serverEndpoint)['url'])throw new UserFacingException('The AI endpoint is managed by server configuration.');
        $model=trim((string)($input['ai_model']??'qwen3:8b'));if($model==='')$model='qwen3:8b';
        $timeout=max(10,min(120,(int)($input['ai_timeout_seconds']??45)));
        self::set('provider','ollama');self::set('ollama_endpoint',rtrim($endpoint,'/'));self::set('ollama_model',$model);self::set('timeout_seconds',(string)$timeout);
    }

    public static function diagnostics(int $actorId,bool $fresh=false): array
    {
        $q=db()->prepare('SELECT 1 FROM users WHERE user_id=? AND status="ACTIVE" AND is_super_admin=1');$q->execute([$actorId]);if(!$q->fetchColumn())throw new UserFacingException('Super Admin access is required.');
        $settings=self::settings();$status=AiConnectionService::status($fresh);$endpoint=rtrim((string)$settings['endpoint'],'/');$checks=[];
        $checks[]=['label'=>'Configured URL','ok'=>$endpoint!=='','detail'=>$endpoint!==''?$endpoint:'No AI URL configured.'];
        $checks[]=['label'=>'PHP cURL','ok'=>function_exists('curl_init'),'detail'=>function_exists('curl_init')?'Available':'PHP cURL extension is unavailable.'];
        $checks[]=['label'=>'Provider enabled','ok'=>strtolower((string)$settings['provider'])==='ollama','detail'=>strtolower((string)$settings['provider'])==='ollama'?'Enabled':'AI provider is not enabled.'];
        $checks[]=['label'=>'Model','ok'=>trim((string)$settings['model'])!=='','detail'=>(string)$settings['model']];
        $checks[]=['label'=>'Connection','ok'=>!empty($status['online']),'detail'=>(string)($status['message']??(!empty($status['online'])?'Connected':'Connection failed'))];
        $checks[]=['label'=>'Diagnostic','ok'=>!empty($status['online']),'detail'=>self::diagnosticDetail($status)];
        if(array_key_exists('basic_auth_configured',$status))$checks[]=['label'=>'Server Basic Authentication','ok'=>(int)($status['http_status']??0)!==401||!empty($status['basic_auth_configured']),'detail'=>!empty($status['basic_auth_configured'])?'Username and password are configured on the server; values are never displayed.':'No complete Basic Authentication credential pair is configured on the server.'];
        return ['settings'=>$settings,'status'=>$status,'checks'=>$checks];
    }

    /** Only fixed text and bounded numeric status codes may reach the admin UI. */
    private static function diagnosticDetail(array $status): string
    {
        $code=(string)($status['diagnostic_code']??'');$http=(int)($status['http_status']??0);
        if($code==='HTTP_ERROR'){
            if($http===401)return !empty($status['basic_auth_configured'])?'HTTP 401: the endpoint rejected the configured credentials. Check the private server configuration against the gateway authentication policy.':'HTTP 401: the endpoint requires authentication, but no complete server Basic Authentication credential pair is configured.';
            if($http===403)return 'HTTP 403: the endpoint denied access. Check gateway permissions and upstream origin restrictions.';
            if($http===404)return 'HTTP 404: the requested AI API path is unavailable. Check the tunnel and upstream service.';
            if($http>=300&&$http<400)return 'HTTP redirect refused. Configure the final approved HTTPS API URL; redirects are not followed.';
            return $http>=100&&$http<=599?'HTTP '.$http.': the AI endpoint did not return a successful status response.':'No valid HTTP response was received.';
        }
        if($code==='TRANSPORT_ERROR')return match((int)($status['curl_errno']??0)){
            6=>'DNS lookup failed on the hosting server.',
            7=>'The hosting server could not connect to the approved AI endpoint.',
            28=>'The connection test timed out. Check the tunnel, upstream service and hosting network access.',
            35,51,58,60,77,83,90,91=>'TLS verification or negotiation failed. Check the hosting certificate trust store and endpoint certificate; do not disable verification.',
            default=>'The HTTPS transport failed. Check the private server log for the matching AI status request.',
        };
        return match($code){
            'READY'=>'The configured model is listed by the AI API. This checks model availability, not inference.',
            'NOT_CONFIGURED'=>'No AI endpoint is configured.',
            'PROVIDER_DISABLED'=>'The AI provider is disabled.',
            'DESTINATION_REFUSED'=>'The endpoint failed destination approval or public DNS validation. Check the approved host policy and private server log.',
            'CURL_UNAVAILABLE'=>'The hosting PHP runtime does not provide cURL.',
            'TRANSPORT_SETUP'=>'The hosting cURL runtime refused required secure transport options.',
            'EMPTY_RESPONSE'=>'The endpoint returned an empty response.',
            'INVALID_RESPONSE'=>'The endpoint did not return the expected JSON model catalogue.',
            'MODEL_UNAVAILABLE'=>'The configured model was not found in the API model catalogue.',
            default=>'No detailed result is available. Run Test Connection for a fresh check.',
        };
    }

    private static function set(string $key,string $value): void
    {
        db()->prepare('INSERT INTO ai_reasoner_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute([$key,$value]);
    }
}
