<?php
require_once __DIR__.'/DynamicOpportunityService.php';
require_once __DIR__.'/SimulationService.php';
require_once __DIR__.'/StateSyncService.php';
require_once __DIR__.'/../tools/SimulationHarness.php';

final class FocusedOpportunitySimulationService
{
    private const PHASES=['EXPOSITION','INCITING_INCIDENT','RISING_ACTION','CLIMAX','RESOLUTION'];

    public static function opportunities(int $plotId): array
    {
        $q=db()->prepare('SELECT o.dynamic_opportunity_id,o.opportunity_code,o.role_code,o.prompt_text,COUNT(x.dynamic_option_id) active_option_count FROM plot_dynamic_opportunities o LEFT JOIN plot_dynamic_opportunity_options x ON x.dynamic_opportunity_id=o.dynamic_opportunity_id AND x.active=1 WHERE o.plot_id=? AND o.active=1 GROUP BY o.dynamic_opportunity_id,o.opportunity_code,o.role_code,o.prompt_text HAVING active_option_count>0 ORDER BY o.role_code,o.opportunity_code');
        $q->execute([$plotId]);return$q->fetchAll();
    }

    public static function activeOptions(int $plotId,string $opportunityCode,?int $plotVersionId=null): array
    {
        // Task 5 Phase 2: a pinned batch resolves its target choices from the
        // version snapshot; a missing opportunity in the snapshot is an empty
        // list (fail fast) rather than a live read.
        if($plotVersionId!==null&&class_exists('SimulationContent')){
            $snap=SimulationContent::forVersion($plotVersionId);
            if($snap!==null){$opp=SimulationContent::opportunityByVersion($plotVersionId,$opportunityCode);if($opp===null)return[];$opts=SimulationContent::dynamicOptionsByVersion($plotVersionId,(int)$opp['dynamic_opportunity_id'])??[];return array_map(static fn($x)=>['option_code'=>$x['option_code'],'label'=>$x['label'],'sort_order'=>$x['sort_order']],$opts);}
        }
        $q=db()->prepare('SELECT x.option_code,x.label,x.sort_order FROM plot_dynamic_opportunity_options x JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=x.dynamic_opportunity_id WHERE o.plot_id=? AND o.opportunity_code=? AND o.active=1 AND x.active=1 ORDER BY x.sort_order,x.dynamic_option_id');
        $q->execute([$plotId,strtoupper(trim($opportunityCode))]);return$q->fetchAll();
    }

    public static function run(string $plotCode,string $opportunityCode,string $optionCode,int $duration=300,int $step=30,?int $plotVersionId=null): array
    {
        $pdo=db();$opportunityCode=strtoupper(trim($opportunityCode));$optionCode=strtoupper(trim($optionCode));
        // Task 5 Phase 2: a pinned focused run resolves its opportunity from
        // the version snapshot. When the snapshot exists there is no live
        // fallback: an opportunity added after the version was submitted is
        // not part of that version's content.
        $opportunity=null;$versionSnap=null;
        if($plotVersionId!==null&&class_exists('SimulationContent'))$versionSnap=SimulationContent::forVersion($plotVersionId);
        if($versionSnap!==null)$opportunity=SimulationContent::opportunityByVersion($plotVersionId,$opportunityCode);
        else{$q=$pdo->prepare('SELECT o.*,p.code plot_code FROM plot_dynamic_opportunities o JOIN plots p ON p.plot_id=o.plot_id WHERE p.code=? AND o.opportunity_code=? AND o.active=1');$q->execute([$plotCode,$opportunityCode]);$opportunity=$q->fetch();}
        if(!$opportunity)throw new RuntimeException('Focused opportunity '.$opportunityCode.' is not active in '.$plotCode.'.');
        $options=self::activeOptions((int)$opportunity['plot_id'],$opportunityCode,$plotVersionId);if(!$options)throw new RuntimeException('Focused opportunity '.$opportunityCode.' has no active choices.');
        if(!in_array($optionCode,array_column($options,'option_code'),true))throw new RuntimeException('Focused choice '.$optionCode.' is not active under '.$opportunityCode.'.');

        putenv('GAME2_SIM_NPC_REASONER=manual');putenv('GAME2_SIM_PLAYER_REASONER=manual');
        $controllers=[];$roles=$pdo->prepare('SELECT role_code FROM plot_roles WHERE plot_id=? AND active=1 AND simulation_active=1');$roles->execute([$opportunity['plot_id']]);foreach($roles->fetchAll()as$role)$controllers[(string)$role['role_code']]='HUMAN';
        $sid=SimulationService::create($plotCode,$duration,$controllers,$plotVersionId);$setup=[];$focus=[];$focusError=null;$choiceApplied=false;$decisionDelivered=false;$available=[];$trace=[];
        try{
            $phase=self::seedConditions($sid,$opportunity,$setup);
            $second=self::phaseSecond($phase,$duration);
            $pdo->prepare('UPDATE simulations SET status="RUNNING",elapsed_seconds=?,current_phase=?,started_at_utc=UTC_TIMESTAMP() WHERE simulation_id=?')->execute([$second,$phase,$sid]);
            self::seedPastWorldEvents($sid,(int)$opportunity['plot_id'],$second,$duration,$setup);
            self::seedDeferredCounts($sid,$opportunity,$setup,$phase,$second);
            $focus=DynamicOpportunityService::evaluateFocused($sid,$opportunityCode);$trace=$focus['conditions'];
            if(!$focus['eligible'])$focusError='The seeded checkpoint did not satisfy every active condition.';
            $decisionId=(int)($focus['decision_id']??0);$decisionDelivered=$decisionId>0;
            if($decisionId){
                $q=$pdo->prepare('SELECT option_code FROM decision_options WHERE decision_id=? ORDER BY sort_order,decision_option_id');$q->execute([$decisionId]);$available=array_column($q->fetchAll(),'option_code');
                if(in_array($optionCode,$available,true)){
                    $q=$pdo->prepare('SELECT opens_at_second FROM decisions WHERE decision_id=?');$q->execute([$decisionId]);$opens=(int)$q->fetchColumn();if($opens>=$duration){$focusError='The target decision opens after the configured story duration.';$pdo->prepare('UPDATE decisions SET status="CLOSED",resolved_text=? WHERE decision_id=? AND status="OPEN"')->execute([$focusError,$decisionId]);}else{if($opens>$second){$second=$opens;$pdo->prepare('UPDATE simulations SET elapsed_seconds=? WHERE simulation_id=?')->execute([$second,$sid]);}BehaviorService::chooseControlled($sid,$decisionId,$optionCode);$choiceApplied=true;}
                }else{$focusError='The target choice was removed by its own option prerequisites.';$pdo->prepare('UPDATE decisions SET status="CLOSED",resolved_text=? WHERE decision_id=? AND status="OPEN"')->execute([$focusError,$decisionId]);}
            }elseif($focusError===null)$focusError='The opportunity was eligible but no decision was delivered.';
            self::completeDownstream($sid,$duration,$step);
        }catch(Throwable $e){$focusError=$e->getMessage();self::completeDownstream($sid,$duration,$step);}
        StateSyncService::reconcile($sid);ResolutionService::refresh($sid);$result=SimulationHarness::result($sid);
        if($focusError!==null)$result['warnings'][]='Focused test: '.$focusError;
        $result['warnings']=array_values(array_unique($result['warnings']));
        $result['focus_test']=['seeded'=>true,'opportunity_code'=>$opportunityCode,'role_code'=>(string)$opportunity['role_code'],'target_option_code'=>$optionCode,'available_option_codes'=>$available,'opportunity_eligible'=>!empty($focus['eligible']),'decision_delivered'=>$decisionDelivered,'choice_applied'=>$choiceApplied,'condition_trace'=>$trace,'setup'=>$setup,'error'=>$focusError,'downstream_opportunities'=>self::openedOpportunities($sid,$opportunityCode)];
        return$result;
    }

    private static function seedConditions(int $sid,array $opportunity,array &$setup): string
    {
        $conditions=null;if(class_exists('SimulationContent'))$conditions=SimulationContent::dynamicConditions($sid,(int)$opportunity['dynamic_opportunity_id']);
        if($conditions===null){$q=db()->prepare('SELECT * FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=? AND active=1 ORDER BY sort_order,dynamic_condition_id');$q->execute([$opportunity['dynamic_opportunity_id']]);$conditions=$q->fetchAll();}
        $groups=[];
        foreach($conditions as$c){$type=(string)$c['source_type'];$key=(string)($c['source_key']??'');$groups[$type.'|'.$key][]=$c;}
        $phase='RISING_ACTION';
        foreach($groups as$group){$type=(string)$group[0]['source_type'];$key=(string)($group[0]['source_key']??'');if($type==='PHASE'){$phase=(string)self::chooseCandidate($sid,$opportunity,$group,self::PHASES);$setup[]=['source_type'=>'PHASE','source_key'=>null,'value'=>$phase,'method'=>'seeded checkpoint'];continue;}if(in_array($type,['ACTION_COUNT','PLAYER_ACTION_COUNT','OPTION_CHOICE_COUNT'],true))continue;
            $candidates=self::candidates($sid,$opportunity,$type,$key,$group);$value=self::chooseCandidate($sid,$opportunity,$group,$candidates);self::applySeed($sid,$opportunity,$type,$key,$value);$setup[]=['source_type'=>$type,'source_key'=>$key?:null,'value'=>$value,'method'=>'seeded value'];
        }
        return$phase;
    }

    private static function seedDeferredCounts(int $sid,array $opportunity,array &$setup,string $phase,int $second): void
    {
        $conditions=null;if(class_exists('SimulationContent'))$conditions=SimulationContent::dynamicConditions($sid,(int)$opportunity['dynamic_opportunity_id']);
        if($conditions!==null){$order=['OPTION_CHOICE_COUNT'=>0,'ACTION_COUNT'=>1,'PLAYER_ACTION_COUNT'=>2];$conditions=array_values(array_filter($conditions,static fn($c)=>isset($order[(string)$c['source_type']])));usort($conditions,static function($a,$b)use($order){return($order[(string)$a['source_type']]<=>$order[(string)$b['source_type']])?:((int)$a['sort_order']<=>(int)$b['sort_order'])?:((int)$a['dynamic_condition_id']<=>(int)$b['dynamic_condition_id']);});}
        else{$q=db()->prepare('SELECT * FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=? AND active=1 AND source_type IN ("OPTION_CHOICE_COUNT","ACTION_COUNT","PLAYER_ACTION_COUNT") ORDER BY FIELD(source_type,"OPTION_CHOICE_COUNT","ACTION_COUNT","PLAYER_ACTION_COUNT"),sort_order,dynamic_condition_id');$q->execute([$opportunity['dynamic_opportunity_id']]);$conditions=$q->fetchAll();}
        $groups=[];foreach($conditions as$c)$groups[$c['source_type'].'|'.(string)($c['source_key']??'')][]=$c;
        foreach($groups as$group){$type=(string)$group[0]['source_type'];$key=(string)($group[0]['source_key']??'');$value=(int)self::chooseCandidate($sid,$opportunity,$group,self::numericCandidates($group,0,100));self::applyCountSeed($sid,$opportunity,$type,$key,$value,$phase,$second);$setup[]=['source_type'=>$type,'source_key'=>$key?:null,'value'=>(string)$value,'method'=>'seeded history'];}
        $trace=DynamicOpportunityService::focusedConditionTrace($sid,(int)$opportunity['dynamic_opportunity_id'],(string)$opportunity['role_code'],$phase);$failed=array_values(array_filter($trace,static fn($row)=>!$row['passed']));if($failed){$labels=array_map(static fn($row)=>$row['source_type'].($row['source_key']!==null?':'.$row['source_key']:'').' '.$row['operator'].' '.$row['compare_value'].' (actual '.($row['exists']?$row['actual']:'missing').')',$failed);throw new RuntimeException('Focused setup cannot satisfy: '.implode('; ',$labels));}
    }

    private static function candidates(int $sid,array $opportunity,string $type,string $key,array $conditions): array
    {
        $values=[];foreach($conditions as$c){$compare=(string)($c['compare_value']??'');if(in_array($c['operator'],['EQ','NEQ'],true)&&$compare!=='')$values[]=$compare;foreach(array_filter(array_map('trim',explode(',',$compare)),static fn($v)=>$v!=='')as$v)$values[]=$v;if(is_numeric($compare)){for($n=(int)$compare-1;$n<=(int)$compare+1;$n++)$values[]=(string)$n;}}
        // Task 5 Phase 2: the state definition comes from the snapshot
        // when pinned. plot_factors min/max/initial (the FACTOR branch below)
        // stay live: factors are not in the workspace snapshot.
        $pinned=class_exists('SimulationContent')?SimulationContent::forSimulation($sid):null;
        $definition=$pinned!==null?SimulationContent::stateDefinition($sid,$type,$key):null;
        if($definition===null&&$pinned===null){$q=db()->prepare('SELECT value_type,allowed_values_text,default_numeric_value FROM plot_state_definitions WHERE plot_id=? AND effect_scope=? AND target_code=? LIMIT 1');$q->execute([$opportunity['plot_id'],$type,$key]);$definition=$q->fetch();}
        if($definition){foreach(preg_split('/\r?\n/',trim((string)$definition['allowed_values_text']))?:[]as$v)if(trim($v)!=='')$values[]=trim($v);if($definition['default_numeric_value']!==null)$values[]=(string)$definition['default_numeric_value'];}
        $min=null;$max=null;if($type==='FACTOR'){$q=db()->prepare('SELECT initial_value,min_value,max_value FROM plot_factors WHERE plot_id=? AND factor_code=?');$q->execute([$opportunity['plot_id'],$key]);if($r=$q->fetch()){$min=(int)$r['min_value'];$max=(int)$r['max_value'];$values=array_merge($values,[(string)$r['initial_value'],(string)$min,(string)$max]);}}elseif(in_array($type,['STAT','RELATIONSHIP'],true)){$min=0;$max=100;$values=array_merge($values,['0','50','100']);}
        $current=self::currentValue($sid,$opportunity,$type,$key);if($current!==null)$values[]=$current;if(($definition['value_type']??null)==='TEXT')$values[]='FOCUS_TEST_VALUE';if(in_array($type,['WORLD_STATE','FACTOR','STAT','KNOWLEDGE','RELATIONSHIP'],true))$values[]=null;
        $values=array_values(array_unique($values,SORT_REGULAR));if($min!==null)$values=array_values(array_filter($values,static fn($value)=>$value===null||(is_numeric($value)&&(float)$value>=$min&&(float)$value<=$max)));return$values;
    }

    private static function numericCandidates(array $conditions,int $min,int $max): array
    {
        $values=[$min,$max,0,1];foreach($conditions as$c)if(is_numeric($c['compare_value'])){for($n=(int)$c['compare_value']-1;$n<=(int)$c['compare_value']+1;$n++)if($n>=$min&&$n<=$max)$values[]=$n;}sort($values);return array_values(array_unique(array_map('strval',$values)));
    }

    private static function chooseCandidate(int $sid,array $opportunity,array $conditions,array $candidates): ?string
    {
        foreach($candidates as$value){$exists=$value!==null;$passes=true;foreach($conditions as$c)if(!self::compare($exists,(string)($value??''),(string)$c['operator'],$c['compare_value'])){$passes=false;break;}if($passes)return$value;}
        $source=$conditions[0]['source_type'].(!empty($conditions[0]['source_key'])?':'.$conditions[0]['source_key']:'');throw new RuntimeException('Focused setup found contradictory or unsupported conditions for '.$source.'.');
    }

    private static function compare(bool $exists,string $value,string $operator,?string $compare): bool
    {
        $list=array_values(array_filter(array_map('trim',explode(',',(string)$compare)),static fn($v)=>$v!==''));return match($operator){'EQ'=>$exists&&$value===(string)$compare,'NEQ'=>!$exists||$value!==(string)$compare,'IN'=>$exists&&in_array($value,$list,true),'NOT_IN'=>!$exists||!in_array($value,$list,true),'EXISTS'=>$exists,'NOT_EXISTS'=>!$exists,'LT'=>$exists&&(float)$value<(float)$compare,'LTE'=>$exists&&(float)$value<=(float)$compare,'GT'=>$exists&&(float)$value>(float)$compare,'GTE'=>$exists&&(float)$value>=(float)$compare,default=>false};
    }

    private static function applySeed(int $sid,array $opportunity,string $type,string $key,?string $value): void
    {
        $pdo=db();$cid=self::characterId($sid,(string)$opportunity['role_code']);
        if($type==='WORLD_STATE'){if($value===null){$pdo->prepare('DELETE FROM simulation_state WHERE simulation_id=? AND state_key=?')->execute([$sid,$key]);$a=$pdo->prepare('SELECT entity_code,property_code FROM plot_state_aliases WHERE plot_id=? AND state_key=?');$a->execute([$opportunity['plot_id'],$key]);if($alias=$a->fetch())$pdo->prepare('DELETE FROM simulation_entity_state WHERE simulation_id=? AND entity_code=? AND property_code=?')->execute([$sid,$alias['entity_code'],$alias['property_code']]);return;}$pdo->prepare('INSERT INTO simulation_state(simulation_id,state_key,state_value,updated_at_second) VALUES(?,?,?,0) ON DUPLICATE KEY UPDATE state_value=VALUES(state_value),updated_at_second=0')->execute([$sid,$key,$value]);$a=$pdo->prepare('SELECT entity_code,property_code FROM plot_state_aliases WHERE plot_id=? AND state_key=?');$a->execute([$opportunity['plot_id'],$key]);if($alias=$a->fetch())$pdo->prepare('INSERT INTO simulation_entity_state(simulation_id,entity_code,property_code,state_value,updated_at_second) VALUES(?,?,?,?,0) ON DUPLICATE KEY UPDATE state_value=VALUES(state_value),updated_at_second=0')->execute([$sid,$alias['entity_code'],$alias['property_code'],$value]);return;}
        if($type==='FACTOR'){if($value===null)$pdo->prepare('DELETE FROM simulation_factors WHERE simulation_id=? AND factor_code=?')->execute([$sid,$key]);else$pdo->prepare('UPDATE simulation_factors SET current_value=? WHERE simulation_id=? AND factor_code=?')->execute([(int)$value,$sid,$key]);return;}
        if(in_array($type,['LOCATION','GOAL','FEAR'],true)){$column=['LOCATION'=>'current_location','GOAL'=>'goal_text','FEAR'=>'fear_text'][$type];$pdo->prepare('UPDATE characters SET '.$column.'=? WHERE character_id=?')->execute([$value??'',$cid]);return;}
        if($type==='STAT'){if($value===null)$pdo->prepare('DELETE FROM character_stats WHERE character_id=? AND stat_code=?')->execute([$cid,$key]);else$pdo->prepare('UPDATE character_stats SET current_value=? WHERE character_id=? AND stat_code=?')->execute([(int)$value,$cid,$key]);return;}
        if($type==='KNOWLEDGE'){if($value===null){$pdo->prepare('DELETE FROM character_knowledge WHERE character_id=? AND knowledge_key=?')->execute([$cid,$key]);return;}$pdo->prepare('INSERT INTO character_knowledge(character_id,knowledge_key,knowledge_text,state,confidence,learned_at_second) VALUES(?,?,?,"KNOWN",100,0) ON DUPLICATE KEY UPDATE knowledge_text=VALUES(knowledge_text),state="KNOWN",confidence=100')->execute([$cid,$key,$value]);return;}
        if($type==='RELATIONSHIP'){[$role,$metric]=array_pad(explode(':',$key,2),2,'');if(!in_array($metric,['trust','suspicion','loyalty'],true))throw new RuntimeException('Focused relationship key '.$key.' is invalid.');$target=self::characterId($sid,$role);if($value===null)$pdo->prepare('DELETE FROM relationships WHERE simulation_id=? AND from_character_id=? AND to_character_id=?')->execute([$sid,$cid,$target]);else$pdo->prepare('UPDATE relationships SET '.$metric.'=? WHERE simulation_id=? AND from_character_id=? AND to_character_id=?')->execute([(int)$value,$sid,$cid,$target]);}
    }

    private static function currentValue(int $sid,array $opportunity,string $type,string $key): ?string
    {
        $pdo=db();$cid=self::characterId($sid,(string)$opportunity['role_code']);if($type==='WORLD_STATE'){$r=GenericStateService::getByKey($sid,$key);return$r['exists']?(string)$r['value']:null;}if($type==='FACTOR'){$q=$pdo->prepare('SELECT current_value FROM simulation_factors WHERE simulation_id=? AND factor_code=?');$q->execute([$sid,$key]);}elseif($type==='STAT'){$q=$pdo->prepare('SELECT current_value FROM character_stats WHERE character_id=? AND stat_code=?');$q->execute([$cid,$key]);}elseif(in_array($type,['LOCATION','GOAL','FEAR'],true)){$column=['LOCATION'=>'current_location','GOAL'=>'goal_text','FEAR'=>'fear_text'][$type];$q=$pdo->prepare('SELECT '.$column.' FROM characters WHERE character_id=?');$q->execute([$cid]);}else return null;$v=$q->fetchColumn();return$v===false?null:(string)$v;
    }

    private static function applyCountSeed(int $sid,array $opportunity,string $type,string $key,int $target,string $phase,int $second): void
    {
        $pdo=db();$targetCid=self::characterId($sid,(string)$opportunity['role_code']);$cid=$targetCid;$optionCode='FOCUS_ACTION';
        if($type==='OPTION_CHOICE_COUNT'){$codes=array_values(array_filter(array_map('trim',explode(',',$key)),static fn($v)=>$v!==''));if(!$codes)throw new RuntimeException('Focused option-choice setup needs at least one stable option code.');$optionCode=(string)$codes[0];$q=$pdo->prepare('SELECT c.character_id FROM characters c WHERE c.simulation_id=? AND c.character_id<>? ORDER BY CASE WHEN c.actor_type="NPC" THEN 0 ELSE 1 END,c.character_id LIMIT 1');$q->execute([$sid,$targetCid]);$cid=(int)($q->fetchColumn()?:$targetCid);$marks=implode(',',array_fill(0,count($codes),'?'));$countQ=$pdo->prepare('SELECT COUNT(*) FROM decisions WHERE simulation_id=? AND status="CHOSEN" AND chosen_option_code IN ('.$marks.')');$countQ->execute(array_merge([$sid],$codes));$current=(int)$countQ->fetchColumn();}
        elseif($type==='ACTION_COUNT'){$countQ=$pdo->prepare('SELECT COUNT(*) FROM decisions WHERE character_id=? AND status="CHOSEN"');$countQ->execute([$cid]);$current=(int)$countQ->fetchColumn();}
        else{$q=$pdo->prepare('SELECT character_id FROM characters WHERE simulation_id=? AND actor_type="PLAYER" ORDER BY character_id LIMIT 1');$q->execute([$sid]);$cid=(int)$q->fetchColumn();if(!$cid)throw new RuntimeException('Focused player-action setup needs an active player character.');$countQ=$pdo->prepare('SELECT COUNT(*) FROM decisions d JOIN characters c ON c.character_id=d.character_id WHERE d.simulation_id=? AND c.actor_type="PLAYER" AND d.status="CHOSEN"');$countQ->execute([$sid]);$current=(int)$countQ->fetchColumn();}
        if($current>$target)throw new RuntimeException('Focused history already exceeds the required '.$type.' value.');for($i=$current;$i<$target;$i++)self::insertHistoryDecision($sid,$cid,$phase,$second,$optionCode.'_'.$i,$type==='OPTION_CHOICE_COUNT'?$optionCode:'FOCUS_ACTION_'.$i);
    }

    private static function insertHistoryDecision(int $sid,int $cid,string $phase,int $second,string $eventSuffix,string $choiceCode): void
    {
        $pdo=db();$eventCode='FOCUS_SETUP_'.preg_replace('/[^A-Z0-9_]/','_',strtoupper($eventSuffix)).'_'.bin2hex(random_bytes(3));$pdo->prepare('INSERT INTO simulation_events(simulation_id,event_second,phase,event_type,actor_character_id,event_code,headline,event_text,visibility) VALUES(?,?,?,"SYSTEM",?,?,"Focused Test Setup","Synthetic history used only to satisfy a focused-test condition.","DIRECTOR")')->execute([$sid,$second,$phase,$cid,$eventCode]);$eventId=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO decisions(simulation_id,character_id,event_id,prompt_text,status,opens_at_second,closes_at_second,chosen_option_code,resolved_text) VALUES(?,?,?,"Focused test setup","CHOSEN",?,?,?,"Seeded history")')->execute([$sid,$cid,$eventId,$second,$second,$choiceCode]);
    }

    private static function seedPastWorldEvents(int $sid,int $plotId,int $checkpointSecond,int $duration,array &$setup): void
    {
        $rows=null;if(class_exists('SimulationContent'))$rows=SimulationContent::worldEvents($sid);
        if($rows===null){$q=db()->prepare('SELECT trigger_percent,phase,event_code FROM plot_world_events WHERE plot_id=? AND active=1 ORDER BY trigger_percent,sort_order,plot_world_event_id');$q->execute([$plotId]);$rows=$q->fetchAll();}
        $events=[];foreach($rows as $row){$row['event_second']=(int)round((float)($row['trigger_percent']??0)*max(1,$duration)/100);if($row['event_second']<=$checkpointSecond)$events[]=$row;}
        $insert=db()->prepare('INSERT IGNORE INTO simulation_events(simulation_id,event_second,phase,event_type,event_code,headline,event_text,visibility) VALUES(?,?,?,"SYSTEM",?,"Focused Test Prehistory","This authored event is treated as already elapsed at the seeded checkpoint.","DIRECTOR")');
        foreach($events as$event)$insert->execute([$sid,$event['event_second'],$event['phase'],$event['event_code']]);
        if($events)$setup[]=['source_type'=>'PREHISTORY','source_key'=>null,'value'=>(string)count($events).' authored world event(s) already elapsed','method'=>'seeded checkpoint'];
    }

    private static function completeDownstream(int $sid,int $duration,int $step): void
    {
        $guard=0;while($guard++<max(5,(int)ceil($duration/max(1,$step))+5)){$q=db()->prepare('SELECT status,elapsed_seconds FROM simulations WHERE simulation_id=?');$q->execute([$sid]);$sim=$q->fetch();if(!$sim||$sim['status']==='COMPLETE')break;self::playOpenDecisions($sid);SimulationService::advance($sid,$step);self::playOpenDecisions($sid);}self::playOpenDecisions($sid);
    }

    private static function playOpenDecisions(int $sid): void
    {
        $snapshot=SimulationService::snapshot($sid);foreach($snapshot['characters']as$character)foreach($character['open_decisions']as$decision){$options=$decision['options']??[];if(!$options)continue;$pick=$options[random_int(0,count($options)-1)];try{BehaviorService::chooseControlled($sid,(int)$decision['decision_id'],(string)$pick['option_code']);}catch(Throwable $ignored){}}
    }

    private static function openedOpportunities(int $sid,string $exclude): array
    {
        $q=db()->prepare('SELECT o.opportunity_code,sdo.status,COUNT(*) occurrence_count FROM simulation_dynamic_opportunities sdo JOIN plot_dynamic_opportunities o ON o.dynamic_opportunity_id=sdo.dynamic_opportunity_id WHERE sdo.simulation_id=? AND o.opportunity_code<>? GROUP BY o.opportunity_code,sdo.status ORDER BY o.opportunity_code,sdo.status');$q->execute([$sid,$exclude]);return$q->fetchAll();
    }

    private static function characterId(int $sid,string $role): int
    {
        $q=db()->prepare('SELECT c.character_id FROM characters c JOIN plot_roles pr ON pr.plot_role_id=c.plot_role_id WHERE c.simulation_id=? AND pr.role_code=? LIMIT 1');$q->execute([$sid,$role]);$id=(int)$q->fetchColumn();if(!$id)throw new RuntimeException('Focused opportunity character '.$role.' is not active in the simulation.');return$id;
    }

    private static function phaseSecond(string $phase,int $duration): int
    {
        $ratio=['EXPOSITION'=>0,'INCITING_INCIDENT'=>.18,'RISING_ACTION'=>.42,'CLIMAX'=>.72,'RESOLUTION'=>.92][$phase]??.42;return min(max(0,(int)ceil($duration*$ratio)),$duration-6);
    }
}
