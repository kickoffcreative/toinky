<?php
final class DynamicOpportunityService
{
    public static function evaluate(int $sid,?int $second=null): void { self::reconcile($sid); }

    public static function evaluateFocused(int $sid,string $opportunityCode): array
    {
        $pdo=db();$opportunityCode=strtoupper(trim($opportunityCode));
        $rule=self::ruleForFocused($sid,$opportunityCode);if(!$rule)throw new RuntimeException('Focused opportunity '.$opportunityCode.' is unavailable in this simulation.');
        $trace=self::focusedConditionTrace($sid,(int)$rule['dynamic_opportunity_id'],(string)$rule['role_code'],(string)$rule['current_phase']);$eligible=!array_filter($trace,static fn($condition)=>!$condition['passed']);
        if(!$eligible)return['eligible'=>false,'decision_id'=>null,'conditions'=>$trace];
        $existing=$pdo->prepare('SELECT decision_id FROM simulation_dynamic_opportunities WHERE simulation_id=? AND dynamic_opportunity_id=? AND character_id=? LIMIT 1');$existing->execute([$sid,$rule['dynamic_opportunity_id'],$rule['character_id']]);$decisionId=(int)$existing->fetchColumn();
        if(!$decisionId){$pdo->prepare('UPDATE decisions SET status="CLOSED",resolved_text="Closed by isolated focused-test setup." WHERE character_id=? AND status="OPEN"')->execute([$rule['character_id']]);self::openOpportunity($sid,$rule,$rule);$existing->execute([$sid,$rule['dynamic_opportunity_id'],$rule['character_id']]);$decisionId=(int)$existing->fetchColumn();}
        return['eligible'=>true,'decision_id'=>$decisionId?:null,'conditions'=>$trace];
    }

    public static function focusedConditionTrace(int $sid,int $ruleId,string $roleCode,string $phase): array
    {
        $q=db()->prepare('SELECT c.character_id FROM characters c JOIN plot_roles pr ON pr.plot_role_id=c.plot_role_id WHERE c.simulation_id=? AND pr.role_code=? LIMIT 1');$q->execute([$sid,$roleCode]);$cid=(int)$q->fetchColumn();if(!$cid)throw new RuntimeException('Focused opportunity character '.$roleCode.' is not active.');
        $conditions=null;if(class_exists('SimulationContent'))$conditions=SimulationContent::dynamicConditions($sid,$ruleId);
        if($conditions===null){$q=db()->prepare('SELECT * FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=? AND active=1 ORDER BY sort_order,dynamic_condition_id');$q->execute([$ruleId]);$conditions=$q->fetchAll();}
        $trace=[];
        foreach($conditions as$condition){[$exists,$value]=self::sourceValue($sid,$cid,(string)$condition['source_type'],$condition['source_key'],$phase);$trace[]=['source_type'=>(string)$condition['source_type'],'source_key'=>$condition['source_key'],'operator'=>(string)$condition['operator'],'compare_value'=>$condition['compare_value'],'exists'=>$exists,'actual'=>$exists?$value:null,'passed'=>self::compare($exists,$value,(string)$condition['operator'],$condition['compare_value'])];}
        return$trace;
    }

    public static function reconcile(int $sid): void
    {
        if($sid<=0)return;
        $pdo=db();
        $s=$pdo->prepare('SELECT plot_id,elapsed_seconds,duration_seconds,current_phase,status FROM simulations WHERE simulation_id=?');
        $s->execute([$sid]);$sim=$s->fetch();
        if(!$sim || $sim['status']!=='RUNNING')return;
        GenericStateService::initializePlotState($sid);
        self::syncStatuses($sid);

        $openedPairs=[];
        // Multiple passes let one actor's decision alter state and immediately make OTHER actors eligible.
        // The same NPC is subject to a short plot-configurable action cooldown so it cannot chain
        // independent decisions at the exact same simulation instant.
        for($pass=0;$pass<12;$pass++){
            $opened=false;
            foreach(self::opportunityRules($sid,$sim) as $rule){
                if((int)$sim['elapsed_seconds']>=(int)$sim['duration_seconds']-5)continue;
                $pairKey=(int)$rule['dynamic_opportunity_id'].':'.(int)$rule['character_id'];if(isset($openedPairs[$pairKey]))continue;
                if(!empty($rule['once_only'])){if(self::alreadyOpened($sid,(int)$rule['dynamic_opportunity_id'],(int)$rule['character_id']))continue;}
                elseif(self::hasUnresolvedInstance($sid,(int)$rule['dynamic_opportunity_id'],(int)$rule['character_id']))continue;
                if(self::hasOpenDecision((int)$rule['character_id']))continue;
                if((string)$rule['actor_type']==='NPC' && self::npcCooldownActive((int)$rule['character_id'],(int)$sim['elapsed_seconds'],(int)$rule['npc_action_cooldown_seconds']))continue;
                if(!self::conditionsPass($sid,(int)$rule['character_id'],(int)$rule['dynamic_opportunity_id'],(string)$sim['current_phase']))continue;
                self::openOpportunity($sid,$rule,$sim);$opened=true;$openedPairs[$pairKey]=true;
            }
            if(!$opened)break;
            self::syncStatuses($sid);
        }
    }

    // Task 5 Phase 2: one opportunity rule row in the live query's shape
    // (opportunity fields + the cast character + the plot cooldown), from
    // the snapshot when pinned. As in the live query, the character's
    // actor_type wins over the opportunity's when the keys collide.
    private static function ruleForFocused(int $sid,string $opportunityCode): ?array {
        $pdo=db();
        if(class_exists('SimulationContent')&&SimulationContent::forSimulation($sid)!==null){
            $opp=SimulationContent::opportunity($sid,$opportunityCode);if($opp===null)return null;
            $sim=$pdo->prepare('SELECT elapsed_seconds,current_phase,duration_seconds,status FROM simulations WHERE simulation_id=?');$sim->execute([$sid]);$s=$sim->fetch();if(!$s)return null;
            $ch=$pdo->prepare('SELECT c.character_id,c.actor_type FROM characters c JOIN plot_roles pr ON pr.plot_role_id=c.plot_role_id WHERE c.simulation_id=? AND pr.role_code=? AND pr.active=1 LIMIT 1');$ch->execute([$sid,$opp['role_code']]);$c=$ch->fetch();if(!$c)return null;
            return array_merge($opp,$s,$c,['npc_action_cooldown_seconds'=>SimulationContent::plotValue($sid,'npc_action_cooldown_seconds')]);
        }
        $q=$pdo->prepare('SELECT o.*,c.character_id,c.actor_type,p.npc_action_cooldown_seconds,s.elapsed_seconds,s.current_phase,s.duration_seconds,s.status FROM plot_dynamic_opportunities o JOIN simulations s ON s.plot_id=o.plot_id JOIN plot_roles pr ON pr.plot_id=o.plot_id AND pr.role_code=o.role_code AND pr.active=1 JOIN characters c ON c.simulation_id=s.simulation_id AND c.plot_role_id=pr.plot_role_id JOIN plots p ON p.plot_id=o.plot_id WHERE s.simulation_id=? AND o.opportunity_code=? AND o.active=1 LIMIT 1');
        $q->execute([$sid,$opportunityCode]);$rule=$q->fetch();return$rule?:null;
    }

    private static function opportunityRules(int $sid,array $sim): array {
        $pdo=db();
        if(class_exists('SimulationContent')&&SimulationContent::forSimulation($sid)!==null){
            $opps=SimulationContent::opportunities($sid)??[];
            $chars=$pdo->prepare('SELECT pr.role_code,c.character_id,c.actor_type FROM characters c JOIN plot_roles pr ON pr.plot_role_id=c.plot_role_id WHERE c.simulation_id=? AND pr.active=1');$chars->execute([$sid]);$byRole=[];foreach($chars->fetchAll()as$c)$byRole[(string)$c['role_code']]=$c;
            $cooldown=SimulationContent::plotValue($sid,'npc_action_cooldown_seconds');
            $rows=[];foreach($opps as$o){$role=(string)$o['role_code'];if(!isset($byRole[$role]))continue;$rows[]=array_merge($o,$byRole[$role],['npc_action_cooldown_seconds'=>$cooldown]);}return$rows;
        }
        $q=$pdo->prepare('SELECT d.*,c.character_id,c.actor_type,p.npc_action_cooldown_seconds FROM plot_dynamic_opportunities d JOIN plot_roles pr ON pr.plot_id=d.plot_id AND pr.role_code=d.role_code AND pr.active=1 JOIN characters c ON c.simulation_id=? AND c.plot_role_id=pr.plot_role_id JOIN plots p ON p.plot_id=d.plot_id WHERE d.plot_id=? AND d.active=1 ORDER BY d.priority,d.dynamic_opportunity_id');
        $q->execute([$sid,$sim['plot_id']]);return$q->fetchAll();
    }

    private static function syncStatuses(int $sid): void
    {
        $pdo=db();
        $pdo->prepare('UPDATE simulation_dynamic_opportunities sdo JOIN decisions d ON d.decision_id=sdo.decision_id SET sdo.status=CASE d.status WHEN "CHOSEN" THEN "CHOSEN" WHEN "NO_ACTION" THEN "EXPIRED" WHEN "CLOSED" THEN "CLOSED" ELSE sdo.status END WHERE sdo.simulation_id=?')->execute([$sid]);
    }

    private static function alreadyOpened(int $sid,int $ruleId,int $cid): bool
    {
        $q=db()->prepare('SELECT 1 FROM simulation_dynamic_opportunities WHERE simulation_id=? AND dynamic_opportunity_id=? AND character_id=? LIMIT 1');
        $q->execute([$sid,$ruleId,$cid]);return(bool)$q->fetchColumn();
    }

    private static function hasUnresolvedInstance(int $sid,int $ruleId,int $cid): bool
    {
        $q=db()->prepare('SELECT 1 FROM simulation_dynamic_opportunities WHERE simulation_id=? AND dynamic_opportunity_id=? AND character_id=? AND status="OPENED" LIMIT 1');
        $q->execute([$sid,$ruleId,$cid]);return(bool)$q->fetchColumn();
    }

    private static function hasOpenDecision(int $cid): bool
    {
        $q=db()->prepare('SELECT 1 FROM decisions WHERE character_id=? AND status="OPEN" LIMIT 1');$q->execute([$cid]);return(bool)$q->fetchColumn();
    }

    private static function npcCooldownActive(int $cid,int $second,int $cooldown): bool
    {
        if($cooldown<=0)return false;
        $q=db()->prepare('SELECT MAX(opens_at_second) FROM decisions WHERE character_id=? AND status="CHOSEN"');
        $q->execute([$cid]);$last=$q->fetchColumn();
        if($last===false || $last===null)return false;
        return ($second-(int)$last)<$cooldown;
    }

    private static function conditionsPass(int $sid,int $cid,int $ruleId,string $phase): bool
    {
        $pdo=db();$trace=getenv('GAME2_TRACE_OPPORTUNITIES')==='1';$traceLabel='';
        if($trace){
            $q=$pdo->prepare('SELECT d.opportunity_code,d.role_code,s.elapsed_seconds FROM plot_dynamic_opportunities d JOIN simulations s ON s.simulation_id=? WHERE d.dynamic_opportunity_id=?');
            $q->execute([$sid,$ruleId]);$meta=$q->fetch();
            $filter=strtoupper(trim((string)getenv('GAME2_TRACE_ROLE')));
            if(!$meta || ($filter!=='' && strtoupper((string)$meta['role_code'])!==$filter))$trace=false;
            else $traceLabel='t='.(int)$meta['elapsed_seconds'].' '.$meta['role_code'].'/'.$meta['opportunity_code'];
        }
        $conditions=null;if(class_exists('SimulationContent'))$conditions=SimulationContent::dynamicConditions($sid,$ruleId);
        if($conditions===null){$q=$pdo->prepare('SELECT * FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=? AND active=1 ORDER BY sort_order,dynamic_condition_id');$q->execute([$ruleId]);$conditions=$q->fetchAll();}
        foreach($conditions as $condition){
            [$exists,$value]=self::sourceValue($sid,$cid,(string)$condition['source_type'],$condition['source_key'],$phase);
            $pass=self::compare($exists,$value,(string)$condition['operator'],$condition['compare_value']);
            if($trace){
                $source=(string)$condition['source_type'].($condition['source_key']!==null?':'.$condition['source_key']:'');
                fwrite(STDERR,'[opportunity] '.$traceLabel.' | '.$source.' actual='.($exists?$value:'<missing>').' '.$condition['operator'].' '.$condition['compare_value'].' => '.($pass?'PASS':'FAIL').PHP_EOL);
            }
            if(!$pass)return false;
        }
        return true;
    }

    private static function sourceValue(int $sid,int $cid,string $type,?string $key,string $phase): array
    {
        $pdo=db();
        if($type==='WORLD_STATE'){$r=GenericStateService::getByKey($sid,(string)$key);return[$r['exists'],$r['exists']?(string)$r['value']:''];}
        if($type==='FACTOR'){$q=$pdo->prepare('SELECT current_value FROM simulation_factors WHERE simulation_id=? AND factor_code=?');$q->execute([$sid,$key]);$v=$q->fetchColumn();return[$v!==false,$v!==false?(string)$v:''];}
        if($type==='LOCATION'){$q=$pdo->prepare('SELECT current_location FROM characters WHERE character_id=?');$q->execute([$cid]);$v=$q->fetchColumn();return[$v!==false,(string)$v];}
        if($type==='GOAL'){$q=$pdo->prepare('SELECT goal_text FROM characters WHERE character_id=?');$q->execute([$cid]);$v=$q->fetchColumn();return[$v!==false,(string)$v];}
        if($type==='FEAR'){$q=$pdo->prepare('SELECT fear_text FROM characters WHERE character_id=?');$q->execute([$cid]);$v=$q->fetchColumn();return[$v!==false,(string)$v];}
        if($type==='STAT'){$q=$pdo->prepare('SELECT current_value FROM character_stats WHERE character_id=? AND stat_code=?');$q->execute([$cid,$key]);$v=$q->fetchColumn();return[$v!==false,$v!==false?(string)$v:''];}
        if($type==='KNOWLEDGE'){$q=$pdo->prepare('SELECT knowledge_text FROM character_knowledge WHERE character_id=? AND knowledge_key=? AND state="KNOWN" LIMIT 1');$q->execute([$cid,$key]);$v=$q->fetchColumn();return[$v!==false,$v!==false?(string)$v:''];}
        if($type==='RELATIONSHIP'){
            [$role,$metric]=array_pad(explode(':',(string)$key,2),2,'');
            if(!in_array($metric,['trust','suspicion','loyalty'],true))return[false,''];
            $q=$pdo->prepare('SELECT r.'.$metric.' FROM relationships r JOIN characters target ON target.character_id=r.to_character_id JOIN plot_roles pr ON pr.plot_role_id=target.plot_role_id WHERE r.simulation_id=? AND r.from_character_id=? AND pr.role_code=? LIMIT 1');$q->execute([$sid,$cid,$role]);$v=$q->fetchColumn();return[$v!==false,$v!==false?(string)$v:''];
        }
        if($type==='PHASE')return[true,$phase];
        if($type==='ACTION_COUNT'){$q=$pdo->prepare('SELECT COUNT(*) FROM decisions WHERE character_id=? AND status="CHOSEN"');$q->execute([$cid]);return[true,(string)(int)$q->fetchColumn()];}
        if($type==='PLAYER_ACTION_COUNT'){$q=$pdo->prepare('SELECT COUNT(*) FROM decisions d JOIN characters c ON c.character_id=d.character_id WHERE d.simulation_id=? AND c.actor_type="PLAYER" AND d.status="CHOSEN"');$q->execute([$sid]);return[true,(string)(int)$q->fetchColumn()];}
        if($type==='OPTION_CHOICE_COUNT'){
            $codes=array_values(array_filter(array_map('trim',explode(',',(string)$key)),fn($v)=>$v!==''));
            if(!$codes)return[false,''];
            $marks=implode(',',array_fill(0,count($codes),'?'));
            $q=$pdo->prepare('SELECT COUNT(*) FROM decisions WHERE simulation_id=? AND status="CHOSEN" AND chosen_option_code IN ('.$marks.')');
            $q->execute(array_merge([$sid],$codes));return[true,(string)(int)$q->fetchColumn()];
        }
        return[false,''];
    }

    private static function compare(bool $exists,string $value,string $operator,?string $compare): bool
    {
        $list=array_values(array_filter(array_map('trim',explode(',',(string)$compare)),fn($v)=>$v!==''));
        return match($operator){
            'EQ'=>$exists && $value===(string)$compare,
            'NEQ'=>!$exists || $value!==(string)$compare,
            'IN'=>$exists && in_array($value,$list,true),
            'NOT_IN'=>!$exists || !in_array($value,$list,true),
            'EXISTS'=>$exists,
            'NOT_EXISTS'=>!$exists,
            'LT'=>$exists && (float)$value<(float)$compare,
            'LTE'=>$exists && (float)$value<=(float)$compare,
            'GT'=>$exists && (float)$value>(float)$compare,
            'GTE'=>$exists && (float)$value>=(float)$compare,
            default=>false,
        };
    }

    private static function openOpportunity(int $sid,array $rule,array $sim): void
    {
        $pdo=db();
        // Task 5 Phase 2: a pinned simulation must not mirror its snapshot
        // into the shared live template tables — that would overwrite the
        // draft's mirror for every other simulation of the story.
        // BehaviorService::onWorldEvent opens the decision from the pinned
        // opportunity instead.
        if(!class_exists('SimulationContent')||SimulationContent::forSimulation($sid)===null)self::ensureBehaviorTemplate($rule);
        $second=(int)$sim['elapsed_seconds'];$code=(string)$rule['opportunity_code'];
        $eventId=0;if(!empty($rule['once_only'])||!self::alreadyOpened($sid,(int)$rule['dynamic_opportunity_id'],(int)$rule['character_id'])){$q=$pdo->prepare('SELECT event_id FROM simulation_events WHERE simulation_id=? AND event_code=? LIMIT 1');$q->execute([$sid,$code]);$eventId=(int)$q->fetchColumn();}
        if(!$eventId){
            $pdo->prepare('INSERT INTO simulation_events(simulation_id,event_second,phase,event_type,actor_character_id,event_code,headline,event_text,visibility) VALUES(?,?,?,?,?,?,?,?,"PRIVATE")')->execute([$sid,$second,$sim['current_phase'],'SYSTEM',(int)$rule['character_id'],$code,'A New Opportunity Opens',(string)$rule['prompt_text']]);
            $eventId=(int)$pdo->lastInsertId();
            // Log the opportunity BEFORE BehaviorService resolves an NPC decision, so history reads
            // Opportunity -> Options Considered -> Decision -> Consequence.
            $pdo->prepare('INSERT INTO character_history(simulation_id,character_id,event_id,event_second,history_type,title,detail_text) VALUES(?,?,?,?,"INTERACTION","Dynamic Opportunity",?)')->execute([$sid,$rule['character_id'],$eventId,$second,$rule['prompt_text']]);
            if(class_exists('BehaviorService'))BehaviorService::onWorldEvent($sid,$eventId,$code,$second);
        }
        $d=$pdo->prepare('SELECT decision_id,status FROM decisions WHERE character_id=? AND event_id=? LIMIT 1');$d->execute([$rule['character_id'],$eventId]);$decision=$d->fetch();$decisionId=(int)($decision['decision_id']??0);
        if(!$decisionId)return;
        $status=($decision['status']??'OPEN')==='CHOSEN'?'CHOSEN':'OPENED';
        $pdo->prepare('INSERT IGNORE INTO simulation_dynamic_opportunities(simulation_id,dynamic_opportunity_id,character_id,decision_id,created_at_second,status) VALUES(?,?,?,?,?,?)')->execute([$sid,$rule['dynamic_opportunity_id'],$rule['character_id'],$decisionId,$second,$status]);
    }

    public static function syncBehaviorTemplateById(int $opportunityId,?string $actorType=null): int
    {
        $q=db()->prepare('SELECT * FROM plot_dynamic_opportunities WHERE dynamic_opportunity_id=? LIMIT 1');$q->execute([$opportunityId]);$rule=$q->fetch();if(!$rule)return 0;if($actorType!==null)$rule['actor_type']=$actorType;return self::ensureBehaviorTemplate($rule);
    }

    private static function ensureBehaviorTemplate(array $rule): int
    {
        $pdo=db();$requestedActorType=in_array(($rule['actor_type']??''),['PLAYER','NPC'],true)?(string)$rule['actor_type']:null;
        $q=$pdo->prepare('SELECT template_id,actor_type FROM plot_decision_templates WHERE plot_id=? AND event_code=? AND role_code=? LIMIT 1');$q->execute([$rule['plot_id'],$rule['opportunity_code'],$rule['role_code']]);$template=$q->fetch();$templateId=(int)($template['template_id']??0);
        $actorType=$requestedActorType??(in_array(($template['actor_type']??''),['PLAYER','NPC'],true)?(string)$template['actor_type']:'PLAYER');
        if(!$templateId){$pdo->prepare('INSERT INTO plot_decision_templates(plot_id,event_code,role_code,actor_type,prompt_text,decision_window_seconds,active) VALUES(?,?,?,?,?,?,?)')->execute([$rule['plot_id'],$rule['opportunity_code'],$rule['role_code'],$actorType,$rule['prompt_text'],$rule['window_seconds'],(int)($rule['active']??1)]);$templateId=(int)$pdo->lastInsertId();}
        else{$pdo->prepare('UPDATE plot_decision_templates SET actor_type=?,prompt_text=?,decision_window_seconds=?,active=? WHERE template_id=?')->execute([$actorType,$rule['prompt_text'],$rule['window_seconds'],(int)($rule['active']??1),$templateId]);}

        $q=$pdo->prepare('SELECT * FROM plot_decision_template_options WHERE template_id=? ORDER BY template_option_id');$q->execute([$templateId]);$mirrors=[];foreach($q->fetchAll()as$mirror)$mirrors[(string)$mirror['option_code']]=$mirror;
        $q=$pdo->prepare('SELECT * FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=? ORDER BY sort_order,dynamic_option_id');$q->execute([$rule['dynamic_opportunity_id']]);$dynamicOptions=$q->fetchAll();$dynamicCodes=[];
        foreach($dynamicOptions as$option){
            $code=(string)$option['option_code'];$dynamicCodes[]=$code;$mirror=$mirrors[$code]??null;$weight=max(0,(int)($option['npc_weight']??1));
            if($mirror){$templateOptionId=(int)$mirror['template_option_id'];$pdo->prepare('UPDATE plot_decision_template_options SET label=?,npc_weight=?,rationale_text=?,result_text=?,sort_order=?,active=? WHERE template_option_id=?')->execute([$option['label'],$weight,$option['rationale_text'],$option['result_text'],$option['sort_order'],(int)($option['active']??1),$templateOptionId]);}
            else{$pdo->prepare('INSERT INTO plot_decision_template_options(template_id,option_code,label,npc_weight,rationale_text,result_text,sort_order,active) VALUES(?,?,?,?,?,?,?,?)')->execute([$templateId,$code,$option['label'],$weight,$option['rationale_text'],$option['result_text'],$option['sort_order'],(int)($option['active']??1)]);$templateOptionId=(int)$pdo->lastInsertId();}
            self::syncBehaviorEffects($templateOptionId,(int)$option['dynamic_option_id']);
        }
        foreach($mirrors as$code=>$mirror)if(!in_array($code,$dynamicCodes,true))$pdo->prepare('UPDATE plot_decision_template_options SET active=0 WHERE template_option_id=?')->execute([$mirror['template_option_id']]);
        return$templateId;
    }

    private static function syncBehaviorEffects(int $templateOptionId,int $dynamicOptionId): void
    {
        $pdo=db();$q=$pdo->prepare('SELECT * FROM plot_option_effects WHERE template_option_id=? ORDER BY effect_id');$q->execute([$templateOptionId]);$available=[];foreach($q->fetchAll()as$effect)$available[$effect['effect_scope'].'|'.$effect['target_code'].'|'.($effect['target_role_code']??'')][]=$effect;
        $q=$pdo->prepare('SELECT * FROM plot_dynamic_option_effects WHERE dynamic_option_id=? ORDER BY sort_order,dynamic_effect_id');$q->execute([$dynamicOptionId]);
        foreach($q->fetchAll()as$effect){$key=$effect['effect_scope'].'|'.$effect['target_code'].'|'.($effect['target_role_code']??'');$mirror=!empty($available[$key])?array_shift($available[$key]):null;if($mirror){$pdo->prepare('UPDATE plot_option_effects SET delta_value=?,set_value=?,detail_text=?,sort_order=?,active=?,target_role_code=? WHERE effect_id=?')->execute([$effect['delta_value'],$effect['set_value'],$effect['detail_text'],$effect['sort_order'],(int)($effect['active']??1),$effect['target_role_code']??null,$mirror['effect_id']]);}else{$pdo->prepare('INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order,active,target_role_code) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$templateOptionId,$effect['effect_scope'],$effect['target_code'],$effect['delta_value'],$effect['set_value'],$effect['detail_text'],$effect['sort_order'],(int)($effect['active']??1),$effect['target_role_code']??null]);}}
        foreach($available as$effects)foreach($effects as$effect)$pdo->prepare('UPDATE plot_option_effects SET active=0 WHERE effect_id=?')->execute([$effect['effect_id']]);
    }
}
