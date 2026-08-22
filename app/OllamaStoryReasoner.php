<?php
final class OllamaStoryReasoner implements StoryReasoner
{
    public function selectOption(array $context,array $options): array
    {
        if(!$options)throw new RuntimeException('No valid options supplied to reasoner.');
        $cfg=$this->connectionConfig();
        $endpointOverride=trim((string)getenv('GAME2_AI_ENDPOINT'));
        $base=$endpointOverride!==''?$endpointOverride:(string)($cfg['endpoint']??AiInteractionLogger::setting('ollama_endpoint','http://127.0.0.1:11434'));
        $endpoint=rtrim($base,'/').'/api/chat';
        $model=(string)($cfg['model']??AiInteractionLogger::setting('ollama_model','qwen3:8b'));
        $timeout=max(3,(int)($cfg['timeout_seconds']??AiInteractionLogger::setting('timeout_seconds','20')));
        $payload=$this->payload($model,$context,$options);
        $promptChars=0;foreach($payload['messages']as$m)$promptChars+=mb_strlen((string)$m['content']);
        $meta=['simulation_id'=>$context['simulation_id']??null,'character_id'=>$context['character_id']??null,'decision_id'=>$context['decision_id']??null,'purpose'=>$context['purpose']??'NPC_OPTION_SELECTION','prompt_chars'=>$promptChars];
        $interactionId=AiInteractionLogger::start($meta,$payload,$endpoint,'ollama',$model);
        $started=hrtime(true);$raw='';$status=null;
        try{
            if(!function_exists('curl_init'))throw new RuntimeException('PHP cURL extension is unavailable.');
            $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if($json===false)throw new RuntimeException('Unable to encode Ollama request.');
            $headers=['Content-Type: application/json'];
            $clientId=$this->value('GAME2_AI_ACCESS_CLIENT_ID',$cfg,'access_client_id');$clientSecret=$this->value('GAME2_AI_ACCESS_CLIENT_SECRET',$cfg,'access_client_secret');
            if($clientId!==''&&$clientSecret!==''){$headers[]='CF-Access-Client-Id: '.$clientId;$headers[]='CF-Access-Client-Secret: '.$clientSecret;}
            $ngrokToken=$this->value('GAME2_AI_NGROK_TOKEN',$cfg,'ngrok_token');if($ngrokToken!=='')$headers[]='Authorization: Bearer '.$ngrokToken;
            $basicUser=$this->value('GAME2_AI_BASIC_USER',$cfg,'basic_user');$basicPassword=$this->value('GAME2_AI_BASIC_PASSWORD',$cfg,'basic_password');
            $ch=curl_init($endpoint);$curlOptions=[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_POSTFIELDS=>$json,CURLOPT_CONNECTTIMEOUT=>min(5,$timeout),CURLOPT_TIMEOUT=>$timeout];
            if($basicUser!==''&&$basicPassword!==''){$curlOptions[CURLOPT_HTTPAUTH]=CURLAUTH_BASIC;$curlOptions[CURLOPT_USERPWD]=$basicUser.':'.$basicPassword;}
            curl_setopt_array($ch,$curlOptions);
            $raw=(string)curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$curlError=curl_error($ch);curl_close($ch);
            if($raw===''&&$curlError!=='')throw new RuntimeException($curlError);if($status<200||$status>=300)throw new RuntimeException('Ollama HTTP '.$status.'.');
            $outer=json_decode($raw,true,512,JSON_THROW_ON_ERROR);$content=(string)($outer['message']['content']??'');$parsed=json_decode($content,true,512,JSON_THROW_ON_ERROR);
            $selected=(string)($parsed['selected_action']??'');$validCodes=array_map(fn($o)=>(string)$o['option_code'],$options);
            if(!in_array($selected,$validCodes,true))throw new RuntimeException('AI selected an action code that is not currently valid.');
            $confidence=max(0,min(100,(float)($parsed['confidence']??0)));$reason=trim((string)($parsed['reason']??''));
            AiInteractionLogger::complete($interactionId,['success'=>true,'http_status'=>$status,'latency_ms'=>(hrtime(true)-$started)/1e6,'response_raw'=>$raw,'parsed'=>$parsed,'prompt_eval_count'=>$outer['prompt_eval_count']??null,'eval_count'=>$outer['eval_count']??null,'total_duration_ns'=>$outer['total_duration']??null,'load_duration_ns'=>$outer['load_duration']??null,'prompt_eval_duration_ns'=>$outer['prompt_eval_duration']??null,'eval_duration_ns'=>$outer['eval_duration']??null,'selected_action'=>$selected,'confidence'=>$confidence,'reason'=>$reason]);
            return['selected_action'=>$selected,'confidence'=>$confidence,'reason'=>$reason,'provider'=>'ollama','model'=>$model,'interaction_id'=>$interactionId];
        }catch(Throwable$e){AiInteractionLogger::complete($interactionId,['success'=>false,'http_status'=>$status,'latency_ms'=>(hrtime(true)-$started)/1e6,'response_raw'=>$raw,'error'=>$e->getMessage()]);throw$e;}
    }
    private function connectionConfig(): array
    {
        $path=dirname(__DIR__).'/config/ai.php';if(!is_file($path))return[];$cfg=require$path;return is_array($cfg)?$cfg:[];
    }
    private function value(string$env,array$cfg,string$key): string
    {
        $v=trim((string)getenv($env));return$v!==''?$v:trim((string)($cfg[$key]??''));
    }
    private function payload(string$model,array$context,array$options): array
    {
        $safeOptions=[];foreach($options as$o)$safeOptions[]=['code'=>(string)$o['option_code'],'label'=>(string)($o['label']??''),'result'=>(string)($o['result_text']??''),'rationale'=>(string)($o['rationale_text']??'')];
        $story=['character'=>['name'=>$context['name']??'','goal'=>$context['goal']??'','fear'=>$context['fear']??'','stats'=>$context['stats']??[],'knowledge'=>$context['knowledge']??[],'location'=>$context['location']??''],'world'=>$context['world']??[],'factors'=>$context['factors']??[],'available_actions'=>$safeOptions];
        $system='You are the decision reasoner for a deterministic story simulation. Choose exactly one action from AVAILABLE_ACTIONS. Never invent an action, fact, state change, person, object, power, rule, or consequence. The game engine is authoritative. Base the choice on the character Goal, Fear, Stats, Knowledge, Location and current world state. Return JSON only with keys: selected_action, confidence, reason. confidence is 0-100. reason must be one short sentence.';
        $user="CURRENT GAME STATE\n".json_encode($story,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        return['model'=>$model,'stream'=>false,'think'=>false,'format'=>'json','messages'=>[['role'=>'system','content'=>$system],['role'=>'user','content'=>$user]],'options'=>['temperature'=>0.2]];
    }
}
