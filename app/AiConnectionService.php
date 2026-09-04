<?php
final class AiConnectionService
{
    public static function status(): array
    {
        $cfg=self::connectionConfig();$settings=class_exists('AiSettingsService')?AiSettingsService::settings():[];
        $provider=strtolower(trim((string)($settings['provider']??AiInteractionLogger::setting('provider','ollama'))));
        $model=trim((string)($settings['model']??AiInteractionLogger::setting('ollama_model','qwen3:8b')));
        if($provider!=='ollama')return['online'=>false,'provider'=>$provider,'model'=>$model,'message'=>'AI service is not enabled.'];
        $base=trim((string)($settings['endpoint']??''));if($base==='')$base=trim((string)($cfg['endpoint']??getenv('GAME2_AI_ENDPOINT')?:AiInteractionLogger::setting('ollama_endpoint','https://endurable-cleft-wildlife.ngrok-free.dev/')));
        if($base===''||!function_exists('curl_init'))return['online'=>false,'provider'=>'ollama','model'=>$model,'message'=>'AI service cannot be reached right now.'];
        $headers=['Accept: application/json'];self::appendHeaders($headers,$cfg);
        $ch=curl_init(rtrim($base,'/').'/api/tags');$options=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>2,CURLOPT_TIMEOUT=>6,CURLOPT_FOLLOWLOCATION=>true];
        $basicUser=self::value('GAME2_AI_BASIC_USER',$cfg,'basic_user');$basicPassword=self::value('GAME2_AI_BASIC_PASSWORD',$cfg,'basic_password');
        if($basicUser!==''&&$basicPassword!==''){$options[CURLOPT_HTTPAUTH]=CURLAUTH_BASIC;$options[CURLOPT_USERPWD]=$basicUser.':'.$basicPassword;}
        curl_setopt_array($ch,$options);$raw=(string)curl_exec($ch);$curlError=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        if($status<200||$status>=300||$raw==='')return['online'=>false,'provider'=>'ollama','model'=>$model,'message'=>$curlError!==''?'Connection failed: '.$curlError:'AI service is offline or returned HTTP '.$status.'.'];
        try{$decoded=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch(Throwable $e){return['online'=>false,'provider'=>'ollama','model'=>$model,'message'=>'AI service returned an unreadable status response.'];}
        $models=array_values(array_filter(array_map(static fn($row)=>trim((string)($row['name']??$row['model']??'')),(array)($decoded['models']??[]))));
        $available=$model===''||in_array($model,$models,true)||(bool)array_filter($models,static fn($name)=>str_starts_with($name,$model.':')||str_starts_with($model,$name.':'));
        return['online'=>$available,'provider'=>'ollama','model'=>$model,'message'=>$available?'AI service is ready.':'Configured model is not available at the AI service.'];
    }

    public static function completeJson(string $purpose,string $system,string $user): array
    {
        $status=self::status();if(empty($status['online']))throw new RuntimeException((string)$status['message']);
        $cfg=self::connectionConfig();$settings=class_exists('AiSettingsService')?AiSettingsService::settings():[];$base=trim((string)($settings['endpoint']??''));if($base==='')$base=trim((string)($cfg['endpoint']??getenv('GAME2_AI_ENDPOINT')?:AiInteractionLogger::setting('ollama_endpoint','https://endurable-cleft-wildlife.ngrok-free.dev/')));$endpoint=rtrim($base,'/').'/api/chat';$model=(string)$status['model'];$timeout=max(10,min(120,(int)($settings['timeout_seconds']??$cfg['timeout_seconds']??AiInteractionLogger::setting('timeout_seconds','45'))));
        $payload=['model'=>$model,'stream'=>false,'think'=>false,'format'=>'json','keep_alive'=>'10m','messages'=>[['role'=>'system','content'=>$system],['role'=>'user','content'=>$user]],'options'=>['temperature'=>0.2]];$json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if($json===false)throw new RuntimeException('AI request could not be prepared.');
        $interactionId=AiInteractionLogger::start(['purpose'=>$purpose,'prompt_chars'=>mb_strlen($system)+mb_strlen($user)],$payload,$endpoint,'ollama',$model);$started=hrtime(true);$raw='';$httpStatus=null;
        try{if(!function_exists('curl_init'))throw new RuntimeException('PHP cURL extension is unavailable.');$headers=['Accept: application/json','Content-Type: application/json'];self::appendHeaders($headers,$cfg);$ch=curl_init($endpoint);$options=[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_POSTFIELDS=>$json,CURLOPT_CONNECTTIMEOUT=>min(5,$timeout),CURLOPT_TIMEOUT=>$timeout,CURLOPT_FOLLOWLOCATION=>true];$basicUser=self::value('GAME2_AI_BASIC_USER',$cfg,'basic_user');$basicPassword=self::value('GAME2_AI_BASIC_PASSWORD',$cfg,'basic_password');if($basicUser!==''&&$basicPassword!==''){$options[CURLOPT_HTTPAUTH]=CURLAUTH_BASIC;$options[CURLOPT_USERPWD]=$basicUser.':'.$basicPassword;}curl_setopt_array($ch,$options);$raw=(string)curl_exec($ch);$httpStatus=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$curlError=curl_error($ch);curl_close($ch);if($raw===''&&$curlError!=='')throw new RuntimeException($curlError);if($httpStatus<200||$httpStatus>=300)throw new RuntimeException('AI HTTP '.$httpStatus.'.');$outer=json_decode($raw,true,512,JSON_THROW_ON_ERROR);$parsed=self::decodeStructuredResponse($outer);AiInteractionLogger::complete($interactionId,['success'=>true,'http_status'=>$httpStatus,'latency_ms'=>(hrtime(true)-$started)/1e6,'response_raw'=>$raw,'parsed'=>$parsed,'prompt_eval_count'=>$outer['prompt_eval_count']??null,'eval_count'=>$outer['eval_count']??null,'total_duration_ns'=>$outer['total_duration']??null,'load_duration_ns'=>$outer['load_duration']??null,'prompt_eval_duration_ns'=>$outer['prompt_eval_duration']??null,'eval_duration_ns'=>$outer['eval_duration']??null]);return['data'=>$parsed,'model'=>$model,'interaction_id'=>$interactionId];}catch(Throwable $e){AiInteractionLogger::complete($interactionId,['success'=>false,'http_status'=>$httpStatus,'latency_ms'=>(hrtime(true)-$started)/1e6,'response_raw'=>$raw,'error'=>$e->getMessage()]);throw$e;}
    }

    private static function decodeStructuredResponse(array $outer): array
    {
        $content=$outer['message']['content']??$outer['response']??$outer['choices'][0]['message']['content']??null;
        if(is_array($content)){if(array_key_exists('summary',$content))return$content;$parts=[];foreach($content as$part){if(is_string($part))$parts[]=$part;elseif(is_array($part)){foreach(['text','content']as$key){if(is_string($part[$key]??null)){$parts[]=$part[$key];break;}}}}$content=implode("\n",$parts);}
        if(!is_string($content)||trim($content)==='')throw new RuntimeException('AI service returned an invalid structured response.');
        $content=preg_replace('/<think\b[^>]*>.*?<\/think>/is','',$content)??$content;$content=trim($content);
        $content=preg_replace('/^```(?:json)?\s*/i','',$content)??$content;$content=preg_replace('/\s*```$/','',$content)??$content;$content=trim($content);
        try{$parsed=json_decode($content,true,512,JSON_THROW_ON_ERROR);}catch(Throwable $e){$candidate=self::extractJsonValue($content);try{$parsed=json_decode($candidate,true,512,JSON_THROW_ON_ERROR);}catch(Throwable $inner){throw new RuntimeException('AI service returned an invalid structured response.',$inner);}}
        if(!is_array($parsed))throw new RuntimeException('AI service returned an invalid structured response.');
        return$parsed;
    }

    private static function extractJsonValue(string $content): string
    {
        $start=null;$stack=[];$inString=false;$escaped=false;$length=strlen($content);
        for($i=0;$i<$length;$i++){$char=$content[$i];if($start===null){if($char==='{'||$char==='['){$start=$i;$stack=[$char==='{'?'}':']'];}continue;}if($inString){if($escaped)$escaped=false;elseif($char==='\\')$escaped=true;elseif($char==='"')$inString=false;continue;}if($char==='"'){$inString=true;continue;}if($char==='{'||$char==='['){$stack[]=$char==='{'?'}':']';continue;}if($char==='}'||$char===']'){if(!$stack||array_pop($stack)!==$char)throw new RuntimeException('AI service returned an invalid structured response.');if(!$stack)return substr($content,$start,$i-$start+1);}}
        throw new RuntimeException('AI service returned an invalid structured response.');
    }

    private static function connectionConfig(): array{$path=dirname(__DIR__).'/config/ai.php';if(!is_file($path))return[];$cfg=require$path;return is_array($cfg)?$cfg:[];}
    private static function value(string $env,array $cfg,string $key): string{$value=trim((string)getenv($env));return$value!==''?$value:trim((string)($cfg[$key]??''));}
    private static function appendHeaders(array &$headers,array $cfg): void{$clientId=self::value('GAME2_AI_ACCESS_CLIENT_ID',$cfg,'access_client_id');$clientSecret=self::value('GAME2_AI_ACCESS_CLIENT_SECRET',$cfg,'access_client_secret');if($clientId!==''&&$clientSecret!==''){$headers[]='CF-Access-Client-Id: '.$clientId;$headers[]='CF-Access-Client-Secret: '.$clientSecret;}$token=self::value('GAME2_AI_NGROK_TOKEN',$cfg,'ngrok_token');if($token!=='')$headers[]='Authorization: Bearer '.$token;}
}
