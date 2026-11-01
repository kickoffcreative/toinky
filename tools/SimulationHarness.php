<?php
require_once __DIR__.'/../app/GenericStateService.php';
require_once __DIR__.'/../app/RecapService.php';
require_once __DIR__.'/../app/RecapChecks.php';
final class SimulationHarness
{
    public static function resetDatabase(): void
    {
        $pdo=db();$pdo->exec('SET FOREIGN_KEY_CHECKS=0');$tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);foreach($tables as$table)$pdo->exec('DROP TABLE IF EXISTS `'.str_replace('`','``',(string)$table).'`');$pdo->exec('SET FOREIGN_KEY_CHECKS=1');self::runSqlFile($pdo,__DIR__.'/../database/schema.sql');self::runSqlFile($pdo,__DIR__.'/../database/seed.sql');MigrationService::run();
    }
    public static function run(string $plotCode,string $strategy='no-action',int $duration=300,int $step=30,string $controllerMode='legacy',string $focus='',?int $plotVersionId=null,?array $choiceScript=null,bool $withRecap=false): array
    {
        $controllerMode=strtolower(trim($controllerMode));$focus=strtoupper(trim($focus));putenv('GAME2_SIM_FOCUS='.$focus);
        $controllers=self::controllerAssignments($plotCode,$controllerMode);
        $scriptState=$choiceScript!==null?['entries'=>self::normalizeChoiceScript($choiceScript),'fired'=>[]]:null;if($scriptState!==null)putenv('GAME2_SIM_CHOICE_SCRIPT='.json_encode($scriptState['entries']));
        $aiCharacterIds=self::prepareAiCharacters($sid=SimulationService::create($plotCode,$duration,$controllers,$plotVersionId),$controllerMode);
        try{
            SimulationService::start($sid);self::playAiDecisions($sid,$aiCharacterIds,$scriptState);
            while(true){$sim=self::simulationRow($sid);if($sim['status']==='COMPLETE')break;if(in_array($controllerMode,['human-random','human-biased'],true))self::playOpenControlledDecisions($sid,$strategy,$aiCharacterIds,$scriptState);elseif($strategy!=='no-action'||$scriptState!==null)self::playOpenPlayerDecisions($sid,$strategy,$aiCharacterIds,$scriptState);self::playAiDecisions($sid,$aiCharacterIds,$scriptState);SimulationService::advance($sid,$step);self::playAiDecisions($sid,$aiCharacterIds);}
            if(in_array($controllerMode,['human-random','human-biased'],true))self::playOpenControlledDecisions($sid,$strategy,$aiCharacterIds,$scriptState);elseif($strategy!=='no-action'||$scriptState!==null)self::playOpenPlayerDecisions($sid,$strategy,$aiCharacterIds,$scriptState);self::playAiDecisions($sid,$aiCharacterIds,$scriptState);
        }finally{self::restoreAiCharacters($aiCharacterIds);putenv('GAME2_SIM_CHOICE_SCRIPT');}
        StateSyncService::reconcile($sid);ResolutionService::refresh($sid);$result=self::result($sid,$focus);if($scriptState!==null){foreach($scriptState['entries']as$i=>$entry){if(isset($scriptState['fired'][$i]))continue;$rq=db()->prepare('SELECT 1 FROM decisions d JOIN simulation_events se ON se.event_id=d.event_id JOIN characters c ON c.character_id=d.character_id JOIN plot_roles pr ON pr.plot_role_id=c.plot_role_id WHERE d.simulation_id=? AND d.status="CHOSEN" AND se.event_code=? AND d.chosen_option_code=? AND (pr.role_code=? OR c.name=?) LIMIT 1');$rq->execute([$sid,(string)$entry['event_code'],(string)$entry['option_code'],(string)($entry['role_code']??''),(string)($entry['character_name']??''))];if($rq->fetchColumn())$scriptState['fired'][$i]=true;}$unfired=[];foreach($scriptState['entries']as$i=>$entry)if(!isset($scriptState['fired'][$i]))$unfired[]=$entry;$result['choice_script']=['entries'=>$scriptState['entries'],'fired_count'=>count($scriptState['fired']),'unfired'=>$unfired];foreach($unfired as$entry)$result['warnings'][]='Scripted choice never fired: '.self::scriptLabel($entry);}if($withRecap){$recapSnapshot=SimulationService::snapshot($sid);$recapDuration=max(1,(int)($recapSnapshot['simulation']['duration_seconds']??$duration));$recaps=[];$allFindings=[];foreach($recapSnapshot['characters']as$ch){$tl=RecapService::timeline($recapSnapshot,$ch,$recapDuration);$text=RecapService::renderText($tl,$ch,$recapDuration);$recaps[(string)$ch['name']]=$text;$findings=RecapChecks::run($sid,$tl,$text,$ch);$allFindings[(string)$ch['name']]=$findings;foreach(RecapChecks::failures($findings)as$f)$result['warnings'][]='Recap check failed: '.$f['check'].' — '.$f['evidence'];foreach(RecapChecks::warnings($findings)as$f)$result['warnings'][]='Recap check warning: '.$f['check'].' — '.$f['evidence'];}$result['recaps']=$recaps;$result['recap_checks']=$allFindings;$result['warnings']=array_values(array_unique($result['warnings']));}return$result;
    }
    private static function controllerAssignments(string $plotCode,string $mode): array
    {
        if(!in_array($mode,['all-ai','human-random','human-biased'],true))return[];$q=db()->prepare('SELECT pr.role_code FROM plot_roles pr JOIN plots p ON p.plot_id=pr.plot_id WHERE p.code=? AND pr.active=1 AND pr.simulation_active=1');$q->execute([$plotCode]);$value=$mode==='all-ai'?'AI':'HUMAN';$out=[];foreach($q->fetchAll()as$r)$out[(string)$r['role_code']]=$value;return$out;
    }
    private static function prepareAiCharacters(int $sid,string $controllerMode='legacy'): array
    {
        $pdo=db();$ids=[];$npcId=null;
        if($controllerMode==='all-ai'){$q=$pdo->prepare('SELECT character_id FROM characters WHERE simulation_id=? ORDER BY character_id');$q->execute([$sid]);foreach($q->fetchAll()as$row)$ids[]=(int)$row['character_id'];}
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
    private static function playAiDecisions(int $sid,array $characterIds,?array &$scriptState=null): void
    {
        if(!$characterIds)return;$pdo=db();
        foreach($characterIds as$characterId){
            $cq=$pdo->prepare('SELECT actor_type,controller_type FROM characters WHERE simulation_id=? AND character_id=?');$cq->execute([$sid,$characterId]);$control=$cq->fetch();$actorType=(string)($control['actor_type']??'');$controllerType=(string)($control['controller_type']??'');if($actorType==='')continue;
            $q=$pdo->prepare('SELECT decision_id,event_id FROM decisions WHERE simulation_id=? AND character_id=? AND status="OPEN" ORDER BY opens_at_second,decision_id');$q->execute([$sid,$characterId]);
            foreach($q->fetchAll()as$d){$decisionId=(int)$d['decision_id'];$options=self::aiDecisionOptions($sid,$decisionId,$characterId);if(!$options)continue;if($scriptState!==null){$scripted=self::scriptedPick($sid,(int)$characterId,$d,$options,$scriptState);if($scripted!==null){try{if($controllerType==='AI')BehaviorService::chooseAutomated($sid,$decisionId,$scripted['option_code']);elseif($actorType==='NPC')BehaviorService::chooseNpcForSimulationLab($sid,$decisionId,$scripted['option_code']);else SimulationService::choose($sid,$decisionId,$scripted['option_code']);$scriptState['fired'][$scripted['entry']]=true;continue;}catch(Throwable $e){}}}
                try{$context=AiReasoningContextService::forDecision($sid,$decisionId);$profile=self::focusProfile($sid,strtoupper(trim((string)getenv('GAME2_SIM_FOCUS'))));if($profile)$context['simulation_focus']=$profile;$context['purpose']='SIMULATION_LAB_'.strtoupper($actorType).'_OPTION_SELECTION';$result=StoryReasonerFactory::withFallback()->selectOption($context,$options);$selected=(string)($result['selected_action']??'');$pick=null;foreach($options as$o)if((string)$o['option_code']===$selected){$pick=$o;break;}if(!$pick)throw new RuntimeException('Reasoner returned no currently valid option.');
                    $detail='Provider: '.($result['provider']??'unknown').' | Selected: '.$selected.' | Confidence: '.($result['confidence']??'n/a').' | Reason: '.($result['reason']??'');if(!empty($result['fallback_used']))$detail.=' | Fallback: '.($result['fallback_error']??'yes');
                    $pdo->prepare('INSERT INTO character_history(simulation_id,character_id,event_id,event_second,history_type,title,detail_text) SELECT ?,?,?,elapsed_seconds,"INTERACTION","AI Options Considered",? FROM simulations WHERE simulation_id=?')->execute([$sid,$characterId,$d['event_id'],$detail,$sid]);
                    if($controllerType==='AI')BehaviorService::chooseAutomated($sid,$decisionId,$selected);elseif($actorType==='NPC')BehaviorService::chooseNpcForSimulationLab($sid,$decisionId,$selected);else SimulationService::choose($sid,$decisionId,$selected);
                }catch(Throwable$e){$fallback=self::weightedPick($options);$pdo->prepare('INSERT INTO character_history(simulation_id,character_id,event_id,event_second,history_type,title,detail_text) SELECT ?,?,?,elapsed_seconds,"INTERACTION","AI Reasoner Fallback",? FROM simulations WHERE simulation_id=?')->execute([$sid,$characterId,$d['event_id'],$e->getMessage(),$sid]);try{if($controllerType==='AI')BehaviorService::chooseAutomated($sid,$decisionId,(string)$fallback['option_code']);elseif($actorType==='NPC')BehaviorService::chooseNpcForSimulationLab($sid,$decisionId,(string)$fallback['option_code']);else SimulationService::choose($sid,$decisionId,(string)$fallback['option_code']);}catch(Throwable$ignored){}}
            }
        }
    }
    private static function aiDecisionOptions(int $sid,int $decisionId,int $characterId): array
    {
        // Task 5 Phase 2: a pinned review run offers the snapshot's options.
        if(class_exists('SimulationContent')){$rows=SimulationContent::decisionOptionsForDecision($sid,$decisionId,$characterId);if($rows!==null){$out=[];foreach($rows as$o){if(empty($o['_pinned_dynamic_option_id'])&&class_exists('CausalService')&&!CausalService::optionAllowed($sid,(int)$o['template_option_id']))continue;$out[]=$o;}return self::applyFocusWeights($sid,$out,strtoupper(trim((string)getenv('GAME2_SIM_FOCUS'))));}}
        $pdo=db();$sim=self::simulationRow($sid);$q=$pdo->prepare('SELECT pdto.*,pdt.event_code,pr.role_code FROM decision_options do JOIN plot_decision_template_options pdto ON pdto.option_code=do.option_code AND pdto.active=1 JOIN plot_decision_templates pdt ON pdt.template_id=pdto.template_id JOIN characters c ON c.character_id=? JOIN plot_roles pr ON pr.plot_role_id=c.plot_role_id WHERE do.decision_id=? AND pdt.plot_id=? AND pdt.role_code=pr.role_code AND pdt.event_code=(SELECT se.event_code FROM decisions dx JOIN simulation_events se ON se.event_id=dx.event_id WHERE dx.decision_id=?) ORDER BY pdto.sort_order,pdto.template_option_id');$q->execute([$characterId,$decisionId,$sim['plot_id'],$decisionId]);$out=[];foreach($q->fetchAll()as$o){if(class_exists('CausalService')&&!CausalService::optionAllowed($sid,(int)$o['template_option_id']))continue;$out[]=$o;}return self::applyFocusWeights($sid,$out,strtoupper(trim((string)getenv('GAME2_SIM_FOCUS'))));
    }
    private static function weightedPick(array$options): array
    {
        $focusActive=array_key_exists('focus_score',$options[0]??[]);$total=max(1,array_sum(array_map(fn($r)=>max(0,(int)($focusActive?($r['focus_score']??0):($r['npc_weight']??0))),$options)));$roll=random_int(1,$total);$cursor=0;foreach($options as$r){$cursor+=max(0,(int)($focusActive?($r['focus_score']??0):($r['npc_weight']??0)));if($roll<=$cursor)return$r;}return$options[0];
    }
    private static function playOpenControlledDecisions(int $sid,string $strategy,array $excludeCharacterIds=[],?array &$scriptState=null): void
    {
        $snapshot=SimulationService::snapshot($sid);
        foreach($snapshot['characters']as$character){if(($character['controller_type']??'')!=='HUMAN'||in_array((int)$character['character_id'],$excludeCharacterIds,true))continue;foreach($character['open_decisions']as$decision){$options=self::applyFocusWeights($sid,$decision['options']??[],strtoupper(trim((string)getenv('GAME2_SIM_FOCUS'))));if(!$options)continue;if($scriptState!==null){$scripted=self::scriptedPick($sid,(int)$character['character_id'],$decision,$options,$scriptState);if($scripted!==null){try{BehaviorService::chooseControlled($sid,(int)$decision['decision_id'],$scripted['option_code']);$scriptState['fired'][$scripted['entry']]=true;continue;}catch(Throwable $e){}}if($strategy==='no-action')continue;}$pick=$strategy==='first'?$options[0]:($strategy==='random'?$options[random_int(0,count($options)-1)]:self::weightedPick($options));try{BehaviorService::chooseControlled($sid,(int)$decision['decision_id'],(string)$pick['option_code']);}catch(Throwable $e){}}}
    }
    private static function playOpenPlayerDecisions(int $sid,string $strategy,array $excludeCharacterIds=[],?array &$scriptState=null): void
    {
        $snapshot=SimulationService::snapshot($sid);
        foreach($snapshot['characters']as$character){if($character['actor_type']!=='PLAYER'||in_array((int)$character['character_id'],$excludeCharacterIds,true))continue;foreach($character['open_decisions']as$decision){$options=$decision['options']??[];if(!$options)continue;if($scriptState!==null){$scripted=self::scriptedPick($sid,(int)$character['character_id'],$decision,$options,$scriptState);if($scripted!==null){try{BehaviorService::chooseControlled($sid,(int)$decision['decision_id'],$scripted['option_code']);$scriptState['fired'][$scripted['entry']]=true;continue;}catch(Throwable $e){}}if($strategy==='no-action')continue;}$pick=self::pickOption($character,$decision,$options,$strategy);try{SimulationService::choose($sid,(int)$decision['decision_id'],(string)$pick['option_code']);}catch(Throwable$e){}}}
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
    private static function focusProfile(int $sid,string $focus): ?array
    {
        if($focus==='')return null;try{$q=db()->prepare('SELECT pfp.focus_profile_id,pfp.focus_code,pfp.label_text,pfp.instruction_text FROM plot_focus_profiles pfp JOIN simulations s ON s.plot_id=pfp.plot_id WHERE s.simulation_id=? AND pfp.focus_code=? AND pfp.active=1');$q->execute([$sid,$focus]);$r=$q->fetch();return$r?:null;}catch(Throwable $e){return null;}
    }
    private static function applyFocusWeights(int $sid,array $options,string $focus): array
    {
        $profile=self::focusProfile($sid,$focus);if(!$profile)return$options;$q=db()->prepare('SELECT focus_weight FROM plot_focus_option_weights WHERE focus_profile_id=? AND event_code=? AND role_code=? AND option_code=?');foreach($options as&$o){$q->execute([$profile['focus_profile_id'],$o['event_code']??'', $o['role_code']??'', $o['option_code']??'']);$v=$q->fetchColumn();if($v===false&&isset($o['template_option_id'])){$x=db()->prepare('SELECT w.focus_weight FROM plot_focus_option_weights w JOIN plot_decision_template_options po ON po.option_code=w.option_code JOIN plot_decision_templates pt ON pt.template_id=po.template_id AND pt.event_code=w.event_code AND pt.role_code=w.role_code WHERE w.focus_profile_id=? AND po.template_option_id=? LIMIT 1');$x->execute([$profile['focus_profile_id'],$o['template_option_id']]);$v=$x->fetchColumn();}$o['focus_score']=$v===false?50:(int)$v;}unset($o);return$options;
    }
    private static function focusAlignment(int $sid,string $focus): ?float
    {
        $profile=self::focusProfile($sid,$focus);if(!$profile)return null;$q=db()->prepare('SELECT d.chosen_option_code,se.event_code,pr.role_code FROM decisions d JOIN simulation_events se ON se.event_id=d.event_id JOIN characters c ON c.character_id=d.character_id JOIN plot_roles pr ON pr.plot_role_id=c.plot_role_id WHERE d.simulation_id=? AND d.status="CHOSEN"');$q->execute([$sid]);$ratios=[];$weight=db()->prepare('SELECT focus_weight FROM plot_focus_option_weights WHERE focus_profile_id=? AND event_code=? AND role_code=? AND option_code=?');$max=db()->prepare('SELECT MAX(focus_weight) FROM plot_focus_option_weights WHERE focus_profile_id=? AND event_code=? AND role_code=?');foreach($q->fetchAll()as$r){$weight->execute([$profile['focus_profile_id'],$r['event_code'],$r['role_code'],$r['chosen_option_code']]);$w=$weight->fetchColumn();$max->execute([$profile['focus_profile_id'],$r['event_code'],$r['role_code']]);$m=$max->fetchColumn();if($w!==false&&$m!==false&&(int)$m>0)$ratios[]=((int)$w/(int)$m)*100;}return$ratios?round(array_sum($ratios)/count($ratios),1):null;
    }
    public static function result(int $sid,string $focus=''): array
    {
        $pdo=db();$snapshot=SimulationService::snapshot($sid);$sim=$snapshot['simulation'];$state=[];try{$q=$pdo->prepare('SELECT state_key,state_value FROM simulation_state WHERE simulation_id=? ORDER BY state_key');$q->execute([$sid]);foreach($q->fetchAll()as$r)$state[$r['state_key']]=$r['state_value'];}catch(Throwable$e){}$meaningfulState=self::meaningfulState($state);$factors=[];foreach($snapshot['factors']as$f)$factors[$f['factor_code']]=(int)$f['current_value'];$characters=[];foreach($snapshot['characters']as$c)$characters[$c['name']]=['type'=>$c['actor_type'],'goal'=>$c['goal_text'],'fear'=>$c['fear_text'],'location'=>$c['current_location'],'stats'=>array_column($c['stats'],'current_value','stat_code')];$counts=['player_actions'=>0,'npc_actions'=>0,'player_no_action'=>0];$q=$pdo->prepare('SELECT c.actor_type,d.status,COUNT(*) n FROM decisions d JOIN characters c ON c.character_id=d.character_id WHERE d.simulation_id=? GROUP BY c.actor_type,d.status');$q->execute([$sid]);foreach($q->fetchAll()as$r){if($r['actor_type']==='PLAYER'&&$r['status']==='CHOSEN')$counts['player_actions']+=(int)$r['n'];if($r['actor_type']==='NPC'&&$r['status']==='CHOSEN')$counts['npc_actions']+=(int)$r['n'];if($r['actor_type']==='PLAYER'&&$r['status']==='NO_ACTION')$counts['player_no_action']+=(int)$r['n'];}$q=$pdo->prepare('SELECT event_text FROM simulation_events WHERE simulation_id=? AND event_code="FINAL_STATE" ORDER BY event_id DESC LIMIT 1');$q->execute([$sid]);$resolution=(string)($q->fetchColumn()?:'');$warnings=self::integrityWarnings($sid,$snapshot,$state,$resolution);$family=self::outcomeFamily($sid,$meaningfulState,$factors,$characters);$signature=hash('sha256',json_encode($family,JSON_UNESCAPED_UNICODE));return['simulation_id'=>$sid,'plot'=>$sim['title'],'status'=>$sim['status'],'plot_version_id'=>(int)($sim['plot_version_id']??0),'factors'=>$factors,'state'=>$state,'meaningful_state'=>$meaningfulState,'characters'=>$characters,'counts'=>$counts,'ai_usage'=>self::aiUsage($sid),'resolution'=>$resolution,'warnings'=>$warnings,'outcome_family'=>$family,'outcome_family_label'=>self::familyLabel($family),'outcome_signature'=>substr($signature,0,12),'focus_alignment_percent'=>self::focusAlignment($sid,$focus)];
    }
    private static function aiUsage(int$sid): array
    {
        try{$q=db()->prepare('SELECT COUNT(*) calls,SUM(success=1) successes,SUM(success=0) failures,COALESCE(SUM(prompt_eval_count),0) prompt_tokens,COALESCE(SUM(eval_count),0) generated_tokens,COALESCE(SUM(total_token_count),0) total_tokens,COALESCE(SUM(latency_ms),0) latency_ms,COALESCE(SUM(total_duration_ns),0) ollama_total_ns FROM ai_reasoner_interactions WHERE simulation_id=?');$q->execute([$sid]);$r=$q->fetch()?:[];return['calls'=>(int)($r['calls']??0),'successes'=>(int)($r['successes']??0),'failures'=>(int)($r['failures']??0),'prompt_tokens'=>(int)($r['prompt_tokens']??0),'generated_tokens'=>(int)($r['generated_tokens']??0),'total_tokens'=>(int)($r['total_tokens']??0),'latency_ms'=>round((float)($r['latency_ms']??0),2),'ollama_total_ms'=>round(((float)($r['ollama_total_ns']??0))/1e6,2)];}catch(Throwable$e){return['calls'=>0,'successes'=>0,'failures'=>0,'prompt_tokens'=>0,'generated_tokens'=>0,'total_tokens'=>0,'latency_ms'=>0,'ollama_total_ms'=>0];}
    }
    private static function outcomeFamily(int $sid,array $state,array $factors,array $characters): array
    {
        $band=fn(int$v)=>$v<35?'LOW':($v<65?'MID':'HIGH');$dimensions=[];
        try{$q=db()->prepare('SELECT dimension_code,label_text,source_type,source_key FROM plot_outcome_dimensions pod JOIN simulations s ON s.plot_id=pod.plot_id WHERE s.simulation_id=? AND pod.active=1 ORDER BY pod.sort_order,pod.outcome_dimension_id');$q->execute([$sid]);foreach($q->fetchAll()as$d){$type=strtoupper((string)$d['source_type']);$key=(string)$d['source_key'];$resolved=$type==='WORLD_STATE'?GenericStateService::getByKey($sid,$key):null;$value=$type==='FACTOR'?$band((int)($factors[$key]??50)):strtoupper((string)(($resolved&&$resolved['exists'])?$resolved['value']:'UNKNOWN'));$dimensions[(string)$d['dimension_code']]=['label'=>(string)$d['label_text'],'value'=>$value];}}catch(Throwable$e){}
        if(!$dimensions){foreach($state as$key=>$value)$dimensions['STATE_'.$key]=['label'=>str_replace('_',' ',(string)$key),'value'=>strtoupper((string)$value)];foreach($factors as$key=>$value)$dimensions['FACTOR_'.$key]=['label'=>str_replace('_',' ',(string)$key),'value'=>$band((int)$value)];}
        $goals=[];foreach($characters as$name=>$c)$goals[$name]=self::goalClass((string)$c['goal']);ksort($goals);return['dimensions'=>$dimensions,'goals'=>$goals];
    }
    private static function goalClass(string $goal): string{$g=strtolower($goal);foreach(['crown','claim','letter','evidence','palace','secure','protect','legitim','family','supply','council','stability']as$t)if(str_contains($g,$t))return strtoupper($t);return substr(strtoupper(preg_replace('/\\s+/',' ',trim($goal))),0,40);}
    private static function familyLabel(array$f): string{$parts=[];foreach(($f['dimensions']??[])as$d)$parts[]=trim((string)$d['label'].' '.(string)$d['value']);return$parts?implode(' / ',$parts):'UNCONFIGURED OUTCOME';}
    private static function meaningfulState(array$state): array{$ignorePrefixes=['NO_ACTION_EFFECT_','TRANSITION_FIRED_','CONSEQUENCE_FIRED_','DYNAMIC_OPPORTUNITY_','OPPORTUNITY_FIRED_','GOAL_RULE_FIRED_','FEAR_RULE_FIRED_'];$out=[];foreach($state as$key=>$value){$internal=false;foreach($ignorePrefixes as$prefix)if(str_starts_with((string)$key,$prefix)){$internal=true;break;}if(!$internal)$out[$key]=$value;}ksort($out);return$out;}
    private static function integrityWarnings(int$sid,array$snapshot,array$state,string$resolution): array{$pdo=db();$warnings=[];if(($snapshot['simulation']['status']??'')!=='COMPLETE')$warnings[]='Simulation did not reach COMPLETE.';foreach($snapshot['factors']as$f){$v=(int)$f['current_value'];if($v<(int)$f['min_value']||$v>(int)$f['max_value'])$warnings[]='Factor '.$f['factor_code'].' is outside its configured range.';}$players=count(array_filter($snapshot['characters'],fn($c)=>$c['actor_type']==='PLAYER'));$npcs=count(array_filter($snapshot['characters'],fn($c)=>$c['actor_type']==='NPC'));$allowed=$players===1?2:1;if($npcs>$allowed)$warnings[]='Active NPC count exceeds cast budget.';if($players===0&&$npcs===0)$warnings[]='Empty cast: no characters were present.';$q=$pdo->prepare('SELECT actor_character_id,event_second,COUNT(*) n FROM simulation_events WHERE simulation_id=? AND event_type="NPC" AND actor_character_id IS NOT NULL GROUP BY actor_character_id,event_second HAVING COUNT(*)>1');$q->execute([$sid]);foreach($q->fetchAll()as$r)$warnings[]='NPC '.$r['actor_character_id'].' produced multiple world actions at second '.$r['event_second'].'.';foreach(self::configuredIntegrityWarnings($sid)as$warning)$warnings[]=$warning;if($resolution==='')$warnings[]='No FINAL_STATE resolution text was generated.';return array_values(array_unique($warnings));}
    private static function configuredIntegrityWarnings(int $sid): array
    {
        $pdo=db();try{$q=$pdo->prepare('SELECT pir.integrity_rule_id,pir.message_text FROM plot_integrity_rules pir JOIN simulations s ON s.plot_id=pir.plot_id WHERE s.simulation_id=? AND pir.active=1 ORDER BY pir.priority,pir.integrity_rule_id');$q->execute([$sid]);}catch(Throwable$e){return[];}$warnings=[];
        foreach($q->fetchAll()as$rule){$cq=$pdo->prepare('SELECT source_type,source_key,operator,compare_value FROM plot_integrity_rule_conditions WHERE integrity_rule_id=? ORDER BY sort_order,integrity_rule_condition_id');$cq->execute([$rule['integrity_rule_id']]);$matches=true;foreach($cq->fetchAll()as$condition){$key=(string)$condition['source_key'];if($condition['source_type']==='FACTOR')$key='FACTOR:'.$key;if(!GenericStateService::conditionPasses($sid,$key,(string)$condition['operator'],$condition['compare_value'])){$matches=false;break;}}if($matches)$warnings[]=(string)$rule['message_text'];
        }return$warnings;
    }
    private static function simulationRow(int$sid): array{$q=db()->prepare('SELECT s.*,p.plot_id FROM simulations s JOIN plots p ON p.plot_id=s.plot_id WHERE s.simulation_id=?');$q->execute([$sid]);$r=$q->fetch();if(!$r)throw new RuntimeException('Simulation not found.');return$r;}
    private static function runSqlFile(PDO$pdo,string$path): void{$sql=file_get_contents($path);if($sql===false)throw new RuntimeException('Unable to read '.basename($path));foreach(self::splitSql($sql)as$stmt)$pdo->exec($stmt);}
    private static function splitSql(string$sql): array{$out=[];$buf='';$quote=null;$escaped=false;$line=false;$block=false;$len=strlen($sql);for($i=0;$i<$len;$i++){$c=$sql[$i];$next=$i+1<$len?$sql[$i+1]:'';if($line){if($c==="\n"){$line=false;$buf.=$c;}continue;}if($block){if($c==='*'&&$next==='/'){$block=false;$i++;}continue;}if($quote===null){if(($c==='-'&&$next==='-'&&($i+2>=$len||ctype_space($sql[$i+2])))||$c==='#'){$line=true;if($c==='-')$i++;continue;}if($c==='/'&&$next==='*'){$block=true;$i++;continue;}if($c==="'"||$c==='"'||$c==='`'){$quote=$c;$buf.=$c;continue;}if($c===';'){$s=trim($buf);if($s!=='')$out[]=$s;$buf='';continue;}$buf.=$c;continue;}$buf.=$c;if($escaped){$escaped=false;continue;}if($c==='\\'&&$quote!=='`'){$escaped=true;continue;}if($c===$quote){if($next===$quote&&$quote!=='`'){$buf.=$next;$i++;continue;}$quote=null;}}$s=trim($buf);if($s!=='')$out[]=$s;return$out;}

    // Lab version resolution: an explicit override wins; otherwise the
    // PUBLISHED version (the one live games run) wins; otherwise the
    // latest submitted version; otherwise null (unpinned draft). The
    // published-or-latest fallback keeps fresh/CI databases — which have
    // no published version — on today's path instead of failing closed.
    // The resolved id + source are always recorded by callers.
    public static function resolveLabVersion(string $plotCode,?int $override=null,bool $latestOnly=false): array
    {
        $pdo=db();$q=$pdo->prepare('SELECT plot_id,published_plot_version_id FROM plots WHERE code=?');$q->execute([$plotCode]);$plot=$q->fetch();
        if(!$plot)throw new RuntimeException('Plot not found: '.$plotCode);
        $plotId=(int)$plot['plot_id'];
        if($override!==null){$vq=$pdo->prepare('SELECT plot_version_id FROM plot_versions WHERE plot_version_id=? AND plot_id=?');$vq->execute([$override,$plotId]);if(!$vq->fetchColumn())throw new RuntimeException('Version '.$override.' does not belong to '.$plotCode.'.');return['plot_version_id'=>$override,'source'=>'override'];}
        if(!$latestOnly&&(int)($plot['published_plot_version_id']??0)>0)return['plot_version_id'=>(int)$plot['published_plot_version_id'],'source'=>'published'];
        $lq=$pdo->prepare('SELECT plot_version_id FROM plot_versions WHERE plot_id=? ORDER BY version_no DESC LIMIT 1');$lq->execute([$plotId]);$latest=$lq->fetchColumn();
        if($latest!==false)return['plot_version_id'=>(int)$latest,'source'=>'latest'];
        return['plot_version_id'=>null,'source'=>'unpinned'];
    }

    // Normalize a choice-script document into a list of entries. Accepts
    // {"choices":[{"role_code"|"character_name","event_code","option_code"}]}
    // or the shorthand {"ROLE_CODE":{"EVENT_CODE":"OPTION_CODE"}}.
    // Throws on malformed input so a bad script fails fast, before any
    // simulation is created.
    public static function normalizeChoiceScript(mixed $script): array
    {
        if(!is_array($script))throw new RuntimeException('Choice script must be a JSON object.');
        $raw=$script;
        if(isset($script['choices'])&&is_array($script['choices']))$raw=$script['choices'];
        $entries=[];
        if(array_is_list($raw)){
            foreach($raw as$item){
                if(!is_array($item))throw new RuntimeException('Each choice-script entry must be an object.');
                $role=isset($item['role_code'])?strtoupper(trim((string)$item['role_code'])):null;
                $name=isset($item['character_name'])?trim((string)$item['character_name']):null;
                $event=strtoupper(trim((string)($item['event_code']??'')));
                $option=strtoupper(trim((string)($item['option_code']??'')));
                if($event===''||$option===''||(($role===null||$role==='')&&($name===null||$name==='')))throw new RuntimeException('Each choice-script entry needs event_code, option_code, and role_code or character_name.');
                $entries[]=['role_code'=>($role!==''?$role:null),'character_name'=>($name!==''?$name:null),'event_code'=>$event,'option_code'=>$option];
            }
        }else{
            foreach($raw as$role=>$events){
                if(!is_array($events))throw new RuntimeException('Choice-script shorthand maps a role code to event/option pairs.');
                foreach($events as$event=>$option)$entries[]=['role_code'=>strtoupper(trim((string)$role)),'character_name'=>null,'event_code'=>strtoupper(trim((string)$event)),'option_code'=>strtoupper(trim((string)$option))];
            }
        }
        if(!$entries)throw new RuntimeException('Choice script contains no entries.');
        return$entries;
    }

    // First unfired script entry matching this decision (role or
    // character-name alias + event), whose option is actually offered.
    // Returns ['entry' => index, 'option_code' => code] or null. Callers
    // mark the entry fired only after their choose call succeeds, so a
    // failed force stays unfired and is reported at the end of the run.
    public static function scriptedPick(int $sid,int $characterId,array $decision,array $options,array $scriptState): ?array
    {
        $char=null;
        try{$q=db()->prepare('SELECT c.name,pr.role_code FROM characters c JOIN plot_roles pr ON pr.plot_role_id=c.plot_role_id WHERE c.character_id=?');$q->execute([$characterId]);$char=$q->fetch();}catch(Throwable $e){}
        if(!$char)return null;
        $roleCode=strtoupper((string)($char['role_code']??''));
        $charName=(string)($char['name']??'');
        $eventCode='';
        try{$q=db()->prepare('SELECT event_code FROM simulation_events WHERE event_id=?');$q->execute([(int)($decision['event_id']??0)]);$eventCode=strtoupper((string)($q->fetchColumn()?:''));}catch(Throwable $e){}
        if($roleCode===''||$eventCode==='')return null;
        $available=[];
        foreach($options as$o)$available[strtoupper((string)($o['option_code']??''))]=true;
        foreach($scriptState['entries']as$i=>$entry){
            if(isset($scriptState['fired'][$i]))continue;
            $roleMatch=($entry['role_code']!==null&&$entry['role_code']===$roleCode)||($entry['character_name']!==null&&strcasecmp((string)$entry['character_name'],$charName)===0);
            if(!$roleMatch||$entry['event_code']!==$eventCode)continue;
            if(!isset($available[$entry['option_code']]))continue;
            return['entry'=>$i,'option_code'=>$entry['option_code']];
        }
        return null;
    }

    private static function scriptLabel(array $entry): string
    {
        return (string)($entry['role_code']??$entry['character_name']??'') . '/' . (string)$entry['event_code'] . ' -> ' . (string)$entry['option_code'];
    }

}
