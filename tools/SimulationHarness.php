<?php
final class SimulationHarness
{
    public static function resetDatabase(): void
    {
        $pdo=db();$pdo->exec('SET FOREIGN_KEY_CHECKS=0');$tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);foreach($tables as$table)$pdo->exec('DROP TABLE IF EXISTS `'.str_replace('`','``',(string)$table).'`');$pdo->exec('SET FOREIGN_KEY_CHECKS=1');self::runSqlFile($pdo,__DIR__.'/../database/schema.sql');self::runSqlFile($pdo,__DIR__.'/../database/seed.sql');MigrationService::run();
    }
    public static function run(string $plotCode,string $strategy='no-action',int $duration=300,int $step=30): array
    {
        $aiCharacterIds=self::prepareAiCharacters($sid=SimulationService::create($plotCode,$duration));
        try{
            SimulationService::start($sid);self::playAiDecisions($sid,$aiCharacterIds);
            while(true){$sim=self::simulationRow($sid);if($sim['status']==='COMPLETE')break;if($strategy!=='no-action')self::playOpenPlayerDecisions($sid,$strategy,$aiCharacterIds);self::playAiDecisions($sid,$aiCharacterIds);SimulationService::advance($sid,$step);self::playAiDecisions($sid,$aiCharacterIds);}
            if($strategy!=='no-action')self::playOpenPlayerDecisions($sid,$strategy,$aiCharacterIds);self::playAiDecisions($sid,$aiCharacterIds);
        }finally{self::restoreAiCharacters($aiCharacterIds);}
        StateSyncService::reconcile($sid);ResolutionService::refresh($sid);return self::result($sid);
    }
    private static function prepareAiCharacters(int $sid): array
    {
        $pdo=db();$ids=[];$npcId=null;
        if(strtolower(trim((string)getenv('GAME2_SIM_NPC_REASONER')))==='ollama'){
            $role=strtoupper(trim((string)getenv('GAME2_SIM_AI_NPC_ROLE')));
            $sql='SELECT c.character_id,pr.role_code FROM characters c JOIN plot_roles pr ON pr.plot_role_id=c.plot_role_id WHERE c.simulation_id=? AND c.actor_type="NPC"';$args=[$sid];if($role!==''){$sql.=' AND pr.role_code=?';$args[]=$role;}$sql.=' ORDER BY c.slot_no,c.character_id LIMIT 1';
            $q=$pdo->prepare($sql);$q->execute($args);$row=$q->fetch();if(!$row)throw new RuntimeException($role!==''?'Requested AI NPC role '.$role.' is not active in this simulation.':'No active NPC is available for AI lab testing.');
            $npcId=(int)$row['character_id'];$ids[]=$npcId;putenv('GAME2_SIM_AI_NPC_ID='.$npcId);
        }
        if(strtolower(trim((string)getenv('GAME2_SIM_PLAYER_REASONER')))==='ollama'){
            $q=$pdo->prepare('SELECT character_id FROM characters WHERE simulation_id=? AND actor_type="PLAYER" ORDER BY slot_no,character_id');$q->execute([$sid]);foreach($q->fetchAll()as$row)$ids[]=(int)$row['character_id'];
        }
        $ids=array_values(array_unique($ids));
        foreach($ids as$characterId)$pdo->prepare('INSERT INTO character_history(simulation_id,character_id,event_second,history_type,title,detail_text) VALUES(?,?,0,"INTERACTION","Simulation Lab AI Control",?)')->execute([$sid,$characterId,'This character keeps its database-defined actor type and is routed through the configured StoryReasoner for Simulation Lab testing.']);
        return$ids;
    }
    private static function restoreAiCharacters(array $characterIds): void
    {
        putenv('GAME2_SIM_AI_NPC_ID');
    }
    private static function playAiDecisions(int $sid,array $characterIds): void
    {
        if(!$characterIds)return;$pdo=db();
        foreach($characterIds as$characterId){
            $cq=$pdo->prepare('SELECT actor_type FROM characters WHERE simulation_id=? AND character_id=?');$cq->execute([$sid,$characterId]);$actorType=(string)$cq->fetchColumn();if($actorType==='')continue;
            $q=$pdo->prepare('SELECT decision_id,event_id FROM decisions WHERE simulation_id=? AND character_id=? AND status="OPEN" ORDER BY opens_at_second,decision_id');$q->execute([$sid,$characterId]);
            foreach($q->fetchAll()as$d){$decisionId=(int)$d['decision_id'];$options=self::aiDecisionOptions($sid,$decisionId,$characterId);if(!$options)continue;
                try{$context=AiReasoningContextService::forDecision($sid,$decisionId);$context['purpose']='SIMULATION_LAB_'.strtoupper($actorType).'_OPTION_SELECTION';$result=StoryReasonerFactory::withFallback()->selectOption($context,$options);$selected=(string)($result['selected_action']??'');$pick=null;foreach($options as$o)if((string)$o['option_code']===$selected){$pick=$o;break;}if(!$pick)throw new RuntimeException('Reasoner returned no currently valid option.');
                    $detail='Provider: '.($result['provider']??'unknown').' | Selected: '.$selected.' | Confidence: '.($result['confidence']??'n/a').' | Reason: '.($result['reason']??'');if(!empty($result['fallback_used']))$detail.=' | Fallback: '.($result['fallback_error']??'yes');
                    $pdo->prepare('INSERT INTO character_history(simulation_id,character_id,event_id,event_second,history_type,title,detail_text) SELECT ?,?,?,elapsed_seconds,"INTERACTION","AI Options Considered",? FROM simulations WHERE simulation_id=?')->execute([$sid,$characterId,$d['event_id'],$detail,$sid]);
                    if($actorType==='NPC')BehaviorService::chooseNpcForSimulationLab($sid,$decisionId,$selected);else SimulationService::choose($sid,$decisionId,$selected);
                }catch(Throwable$e){$fallback=self::weightedPick($options);$pdo->prepare('INSERT INTO character_history(simulation_id,character_id,event_id,event_second,history_type,title,detail_text) SELECT ?,?,?,elapsed_seconds,"INTERACTION","AI Reasoner Fallback",? FROM simulations WHERE simulation_id=?')->execute([$sid,$characterId,$d['event_id'],$e->getMessage(),$sid]);try{if($actorType==='NPC')BehaviorService::chooseNpcForSimulationLab($sid,$decisionId,(string)$fallback['option_code']);else SimulationService::choose($sid,$decisionId,(string)$fallback['option_code']);}catch(Throwable$ignored){}}
            }
        }
    }
    private static function aiDecisionOptions(int $sid,int $decisionId,int $characterId): array
    {
        $pdo=db();$sim=self::simulationRow($sid);$q=$pdo->prepare('SELECT pdto.* FROM decision_options do JOIN plot_decision_template_options pdto ON pdto.option_code=do.option_code JOIN plot_decision_templates pdt ON pdt.template_id=pdto.template_id JOIN characters c ON c.character_id=? JOIN plot_roles pr ON pr.plot_role_id=c.plot_role_id WHERE do.decision_id=? AND pdt.plot_id=? AND pdt.role_code=pr.role_code AND pdt.event_code=(SELECT se.event_code FROM decisions dx JOIN simulation_events se ON se.event_id=dx.event_id WHERE dx.decision_id=?) ORDER BY pdto.sort_order,pdto.template_option_id');$q->execute([$characterId,$decisionId,$sim['plot_id'],$decisionId]);$out=[];foreach($q->fetchAll()as$o){if(class_exists('CausalService')&&!CausalService::optionAllowed($sid,(int)$o['template_option_id']))continue;$out[]=$o;}return$out;
    }
    private static function weightedPick(array$options): array
    {
        $total=max(1,array_sum(array_map(fn($r)=>max(0,(int)($r['npc_weight']??0)),$options)));$roll=random_int(1,$total);$cursor=0;foreach($options as$r){$cursor+=max(0,(int)($r['npc_weight']??0));if($roll<=$cursor)return$r;}return$options[0];
    }
    private static function playOpenPlayerDecisions(int $sid,string $strategy,array $excludeCharacterIds=[]): void
    {
        $snapshot=SimulationService::snapshot($sid);
        foreach($snapshot['characters']as$character){if($character['actor_type']!=='PLAYER'||in_array((int)$character['character_id'],$excludeCharacterIds,true))continue;foreach($character['open_decisions']as$decision){$options=$decision['options']??[];if(!$options)continue;$pick=self::pickOption($character,$decision,$options,$strategy);try{SimulationService::choose($sid,(int)$decision['decision_id'],(string)$pick['option_code']);}catch(Throwable$e){}}}
    }
    private static function pickOption(array $character,array $decision,array $options,string $strategy): array
    {
        if($strategy==='first')return$options[0];if($strategy==='random')return$options[random_int(0,count($options)-1)];
        $role=self::roleCode($character);$best=null;$bestScore=-PHP_INT_MAX;
        foreach($options as$option){$text=strtolower(trim(($option['label']??'').' '.($option['result_text']??'').' '.($option['rationale_text']??'').' '.($option['option_code']??'')));$score=0;
            if($strategy==='anti-rowan'){
                $generic=['review'=>5,'verify'=>6,'evidence'=>4,'letter'=>3,'neutral'=>4,'council'=>3,'lawful'=>5,'legitimate'=>4,'protect'=>3,'secure'=>3,'challenge'=>4,'claim'=>2,'oppose'=>5,'refuse'=>4,'delay'=>2,'public'=>2];foreach($generic as$term=>$w)if(str_contains($text,$term))$score+=$w;
                foreach(['support rowan'=>-9,'back rowan'=>-9,'recognize rowan'=>-8,'proclaim rowan'=>-9,'destroy'=>-7,'suppress'=>-6,'remain silent'=>-5,'do nothing'=>-5]as$term=>$w)if(str_contains($text,$term))$score+=$w;
                if($role==='ELIAN'){foreach(['claim'=>7,'activate'=>5,'letter'=>4,'review'=>4]as$t=>$w)if(str_contains($text,$t))$score+=$w;}
                if($role==='MARA'){foreach(['pause'=>5,'council'=>5,'broker'=>4,'stability'=>4,'peace'=>4,'supplies'=>2]as$t=>$w)if(str_contains($text,$t))$score+=$w;}
                if($role==='TOMAS'){foreach(['secure'=>6,'protect'=>6,'neutral'=>5,'refuse'=>6,'evidence'=>5,'guard'=>4]as$t=>$w)if(str_contains($text,$t))$score+=$w;}
            }elseif($strategy==='pro-rowan'){
                foreach(['rowan'=>5,'proclaim'=>7,'recognize'=>7,'support'=>5,'back'=>5,'continuity'=>4,'coronation'=>5,'crown'=>4,'destroy'=>3,'suppress'=>3]as$t=>$w)if(str_contains($text,$t))$score+=$w;
                foreach(['challenge'=>-5,'oppose'=>-6,'claim'=>-2,'verify'=>-4,'neutral'=>-3,'delay'=>-4,'refuse'=>-5]as$t=>$w)if(str_contains($text,$t))$score+=$w;
            }
            $score+=self::stableTieBreak((string)($option['option_code']??$option['label']??''));if($score>$bestScore){$bestScore=$score;$best=$option;}
        }return$best??$options[0];
    }
    private static function roleCode(array $character): string
    {
        try{$q=db()->prepare('SELECT role_code FROM plot_roles WHERE plot_role_id=?');$q->execute([$character['plot_role_id']]);return strtoupper((string)$q->fetchColumn());}catch(Throwable$e){return'';}
    }
    private static function stableTieBreak(string $value): float{return(hexdec(substr(md5($value),0,4))%1000)/100000.0;}
    public static function result(int $sid): array
    {
        $pdo=db();$snapshot=SimulationService::snapshot($sid);$sim=$snapshot['simulation'];$state=[];try{$q=$pdo->prepare('SELECT state_key,state_value FROM simulation_state WHERE simulation_id=? ORDER BY state_key');$q->execute([$sid]);foreach($q->fetchAll()as$r)$state[$r['state_key']]=$r['state_value'];}catch(Throwable$e){}$meaningfulState=self::meaningfulState($state);$factors=[];foreach($snapshot['factors']as$f)$factors[$f['factor_code']]=(int)$f['current_value'];$characters=[];foreach($snapshot['characters']as$c)$characters[$c['name']]=['type'=>$c['actor_type'],'goal'=>$c['goal_text'],'fear'=>$c['fear_text'],'location'=>$c['current_location'],'stats'=>array_column($c['stats'],'current_value','stat_code')];$counts=['player_actions'=>0,'npc_actions'=>0,'player_no_action'=>0];$q=$pdo->prepare('SELECT c.actor_type,d.status,COUNT(*) n FROM decisions d JOIN characters c ON c.character_id=d.character_id WHERE d.simulation_id=? GROUP BY c.actor_type,d.status');$q->execute([$sid]);foreach($q->fetchAll()as$r){if($r['actor_type']==='PLAYER'&&$r['status']==='CHOSEN')$counts['player_actions']+=(int)$r['n'];if($r['actor_type']==='NPC'&&$r['status']==='CHOSEN')$counts['npc_actions']+=(int)$r['n'];if($r['actor_type']==='PLAYER'&&$r['status']==='NO_ACTION')$counts['player_no_action']+=(int)$r['n'];}$q=$pdo->prepare('SELECT event_text FROM simulation_events WHERE simulation_id=? AND event_code="FINAL_STATE" ORDER BY event_id DESC LIMIT 1');$q->execute([$sid]);$resolution=(string)($q->fetchColumn()?:'');$warnings=self::integrityWarnings($sid,$snapshot,$state,$resolution);$family=self::outcomeFamily($sid,$meaningfulState,$factors,$characters);$signature=hash('sha256',json_encode($family,JSON_UNESCAPED_UNICODE));return['simulation_id'=>$sid,'plot'=>$sim['title'],'status'=>$sim['status'],'factors'=>$factors,'state'=>$state,'meaningful_state'=>$meaningfulState,'characters'=>$characters,'counts'=>$counts,'ai_usage'=>self::aiUsage($sid),'resolution'=>$resolution,'warnings'=>$warnings,'outcome_family'=>$family,'outcome_family_label'=>self::familyLabel($family),'outcome_signature'=>substr($signature,0,12)];
    }
    private static function aiUsage(int$sid): array
    {
        try{$q=db()->prepare('SELECT COUNT(*) calls,SUM(success=1) successes,SUM(success=0) failures,COALESCE(SUM(prompt_eval_count),0) prompt_tokens,COALESCE(SUM(eval_count),0) generated_tokens,COALESCE(SUM(total_token_count),0) total_tokens,COALESCE(SUM(latency_ms),0) latency_ms,COALESCE(SUM(total_duration_ns),0) ollama_total_ns FROM ai_reasoner_interactions WHERE simulation_id=?');$q->execute([$sid]);$r=$q->fetch()?:[];return['calls'=>(int)($r['calls']??0),'successes'=>(int)($r['successes']??0),'failures'=>(int)($r['failures']??0),'prompt_tokens'=>(int)($r['prompt_tokens']??0),'generated_tokens'=>(int)($r['generated_tokens']??0),'total_tokens'=>(int)($r['total_tokens']??0),'latency_ms'=>round((float)($r['latency_ms']??0),2),'ollama_total_ms'=>round(((float)($r['ollama_total_ns']??0))/1e6,2)];}catch(Throwable$e){return['calls'=>0,'successes'=>0,'failures'=>0,'prompt_tokens'=>0,'generated_tokens'=>0,'total_tokens'=>0,'latency_ms'=>0,'ollama_total_ms'=>0];}
    }
    private static function outcomeFamily(int $sid,array $state,array $factors,array $characters): array
    {
        $band=fn(int$v)=>$v<35?'LOW':($v<65?'MID':'HIGH');$dimensions=[];
        try{$q=db()->prepare('SELECT dimension_code,label_text,source_type,source_key FROM plot_outcome_dimensions pod JOIN simulations s ON s.plot_id=pod.plot_id WHERE s.simulation_id=? AND pod.active=1 ORDER BY pod.sort_order,pod.outcome_dimension_id');$q->execute([$sid]);foreach($q->fetchAll()as$d){$type=strtoupper((string)$d['source_type']);$key=(string)$d['source_key'];$value=$type==='FACTOR'?$band((int)($factors[$key]??50)):strtoupper((string)($state[$key]??'UNKNOWN'));$dimensions[(string)$d['dimension_code']]=['label'=>(string)$d['label_text'],'value'=>$value];}}catch(Throwable$e){}
        if(!$dimensions){foreach($state as$key=>$value)$dimensions['STATE_'.$key]=['label'=>str_replace('_',' ',(string)$key),'value'=>strtoupper((string)$value)];foreach($factors as$key=>$value)$dimensions['FACTOR_'.$key]=['label'=>str_replace('_',' ',(string)$key),'value'=>$band((int)$value)];}
        $goals=[];foreach($characters as$name=>$c)$goals[$name]=self::goalClass((string)$c['goal']);ksort($goals);return['dimensions'=>$dimensions,'goals'=>$goals];
    }
    private static function goalClass(string $goal): string{$g=strtolower($goal);foreach(['crown','claim','letter','evidence','palace','secure','protect','legitim','family','supply','council','stability']as$t)if(str_contains($g,$t))return strtoupper($t);return substr(strtoupper(preg_replace('/\\s+/',' ',trim($goal))),0,40);}
    private static function familyLabel(array$f): string{$parts=[];foreach(($f['dimensions']??[])as$d)$parts[]=trim((string)$d['label'].' '.(string)$d['value']);return$parts?implode(' / ',$parts):'UNCONFIGURED OUTCOME';}
    private static function meaningfulState(array$state): array{$ignorePrefixes=['NO_ACTION_EFFECT_','TRANSITION_FIRED_','CONSEQUENCE_FIRED_','DYNAMIC_OPPORTUNITY_','OPPORTUNITY_FIRED_','GOAL_RULE_FIRED_','FEAR_RULE_FIRED_'];$out=[];foreach($state as$key=>$value){$internal=false;foreach($ignorePrefixes as$prefix)if(str_starts_with((string)$key,$prefix)){$internal=true;break;}if(!$internal)$out[$key]=$value;}ksort($out);return$out;}
    private static function integrityWarnings(int$sid,array$snapshot,array$state,string$resolution): array{$pdo=db();$warnings=[];if(($snapshot['simulation']['status']??'')!=='COMPLETE')$warnings[]='Simulation did not reach COMPLETE.';foreach($snapshot['factors']as$f){$v=(int)$f['current_value'];if($v<(int)$f['min_value']||$v>(int)$f['max_value'])$warnings[]='Factor '.$f['factor_code'].' is outside its configured range.';}$players=count(array_filter($snapshot['characters'],fn($c)=>$c['actor_type']==='PLAYER'));$npcs=count(array_filter($snapshot['characters'],fn($c)=>$c['actor_type']==='NPC'));$allowed=$players===1?2:1;if($npcs>$allowed)$warnings[]='Active NPC count exceeds cast budget.';$q=$pdo->prepare('SELECT actor_character_id,event_second,COUNT(*) n FROM simulation_events WHERE simulation_id=? AND event_type="NPC" AND actor_character_id IS NOT NULL GROUP BY actor_character_id,event_second HAVING COUNT(*)>1');$q->execute([$sid]);foreach($q->fetchAll()as$r)$warnings[]='NPC '.$r['actor_character_id'].' produced multiple world actions at second '.$r['event_second'].'.';if(($state['LETTER_STATUS']??null)==='DESTROYED'&&stripos($resolution,'survives')!==false)$warnings[]='Resolution says the letter survives although LETTER_STATUS=DESTROYED.';if($resolution==='')$warnings[]='No FINAL_STATE resolution text was generated.';return array_values(array_unique($warnings));}
    private static function simulationRow(int$sid): array{$q=db()->prepare('SELECT s.*,p.plot_id FROM simulations s JOIN plots p ON p.plot_id=s.plot_id WHERE s.simulation_id=?');$q->execute([$sid]);$r=$q->fetch();if(!$r)throw new RuntimeException('Simulation not found.');return$r;}
    private static function runSqlFile(PDO$pdo,string$path): void{$sql=file_get_contents($path);if($sql===false)throw new RuntimeException('Unable to read '.basename($path));foreach(self::splitSql($sql)as$stmt)$pdo->exec($stmt);}
    private static function splitSql(string$sql): array{$out=[];$buf='';$quote=null;$escaped=false;$line=false;$block=false;$len=strlen($sql);for($i=0;$i<$len;$i++){$c=$sql[$i];$next=$i+1<$len?$sql[$i+1]:'';if($line){if($c==="\n"){$line=false;$buf.=$c;}continue;}if($block){if($c==='*'&&$next==='/'){$block=false;$i++;}continue;}if($quote===null){if(($c==='-'&&$next==='-'&&($i+2>=$len||ctype_space($sql[$i+2])))||$c==='#'){$line=true;if($c==='-')$i++;continue;}if($c==='/'&&$next==='*'){$block=true;$i++;continue;}if($c==="'"||$c==='"'||$c==='`'){$quote=$c;$buf.=$c;continue;}if($c===';'){$s=trim($buf);if($s!=='')$out[]=$s;$buf='';continue;}$buf.=$c;continue;}$buf.=$c;if($escaped){$escaped=false;continue;}if($c==='\\'&&$quote!=='`'){$escaped=true;continue;}if($c===$quote){if($next===$quote&&$quote!=='`'){$buf.=$next;$i++;continue;}$quote=null;}}$s=trim($buf);if($s!=='')$out[]=$s;return$out;}
}
