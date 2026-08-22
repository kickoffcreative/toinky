<?php
final class DynamicOpportunityService
{
    public static function evaluate(int $sid,?int $second=null): void { self::reconcile($sid); }

    public static function reconcile(int $sid): void
    {
        if($sid<=0)return;
        $pdo=db();
        $s=$pdo->prepare('SELECT plot_id,elapsed_seconds,duration_seconds,current_phase,status FROM simulations WHERE simulation_id=?');
        $s->execute([$sid]);$sim=$s->fetch();
        if(!$sim || $sim['status']!=='RUNNING')return;
        GenericStateService::initializePlotState($sid);
        self::syncStatuses($sid);

        // Multiple passes let one actor's decision alter state and immediately make OTHER actors eligible.
        // The same NPC is subject to a short plot-configurable action cooldown so it cannot chain
        // independent decisions at the exact same simulation instant.
        for($pass=0;$pass<12;$pass++){
            $opened=false;
            $q=$pdo->prepare('SELECT d.*,c.character_id,c.actor_type,p.npc_action_cooldown_seconds FROM plot_dynamic_opportunities d JOIN plot_roles pr ON pr.plot_id=d.plot_id AND pr.role_code=d.role_code JOIN characters c ON c.simulation_id=? AND c.plot_role_id=pr.plot_role_id JOIN plots p ON p.plot_id=d.plot_id WHERE d.plot_id=? AND d.active=1 ORDER BY d.priority,d.dynamic_opportunity_id');
            $q->execute([$sid,$sim['plot_id']]);
            foreach($q->fetchAll() as $rule){
                if((int)$sim['elapsed_seconds']>=(int)$sim['duration_seconds']-5)continue;
                if(self::alreadyOpened($sid,(int)$rule['dynamic_opportunity_id'],(int)$rule['character_id']))continue;
                if(self::hasOpenDecision((int)$rule['character_id']))continue;
                if((string)$rule['actor_type']==='NPC' && self::npcCooldownActive((int)$rule['character_id'],(int)$sim['elapsed_seconds'],(int)$rule['npc_action_cooldown_seconds']))continue;
                if(!self::conditionsPass($sid,(int)$rule['character_id'],(int)$rule['dynamic_opportunity_id'],(string)$sim['current_phase']))continue;
                self::openOpportunity($sid,$rule,$sim);$opened=true;
            }
            if(!$opened)break;
            self::syncStatuses($sid);
        }
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
        $q=db()->prepare('SELECT * FROM plot_dynamic_opportunity_conditions WHERE dynamic_opportunity_id=? ORDER BY sort_order,dynamic_condition_id');$q->execute([$ruleId]);
        foreach($q->fetchAll() as $c){
            [$exists,$value]=self::sourceValue($sid,$cid,(string)$c['source_type'],$c['source_key'],$phase);
            if(!self::compare($exists,$value,(string)$c['operator'],$c['compare_value']))return false;
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
        $pdo=db();self::ensureBehaviorTemplate($rule);
        $second=(int)$sim['elapsed_seconds'];$code=(string)$rule['opportunity_code'];
        $q=$pdo->prepare('SELECT event_id FROM simulation_events WHERE simulation_id=? AND event_code=? LIMIT 1');$q->execute([$sid,$code]);$eventId=(int)$q->fetchColumn();
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

    private static function ensureBehaviorTemplate(array $rule): void
    {
        $pdo=db();$q=$pdo->prepare('SELECT template_id FROM plot_decision_templates WHERE plot_id=? AND event_code=? AND role_code=? LIMIT 1');$q->execute([$rule['plot_id'],$rule['opportunity_code'],$rule['role_code']]);$templateId=(int)$q->fetchColumn();if($templateId)return;
        $actorType=(string)($rule['actor_type']??'PLAYER');
        $pdo->prepare('INSERT INTO plot_decision_templates(plot_id,event_code,role_code,actor_type,prompt_text,decision_window_seconds) VALUES(?,?,?,?,?,?)')->execute([$rule['plot_id'],$rule['opportunity_code'],$rule['role_code'],$actorType,$rule['prompt_text'],$rule['window_seconds']]);$templateId=(int)$pdo->lastInsertId();
        $o=$pdo->prepare('SELECT * FROM plot_dynamic_opportunity_options WHERE dynamic_opportunity_id=? ORDER BY sort_order,dynamic_option_id');$o->execute([$rule['dynamic_opportunity_id']]);
        foreach($o->fetchAll() as $opt){
            $weight=$actorType==='NPC'?(int)($opt['npc_weight']??1):0;$rationale=$opt['rationale_text']??null;
            $pdo->prepare('INSERT INTO plot_decision_template_options(template_id,option_code,label,npc_weight,rationale_text,result_text,sort_order) VALUES(?,?,?,?,?,?,?)')->execute([$templateId,$opt['option_code'],$opt['label'],$weight,$rationale,$opt['result_text'],$opt['sort_order']]);$templateOptionId=(int)$pdo->lastInsertId();
            $e=$pdo->prepare('SELECT * FROM plot_dynamic_option_effects WHERE dynamic_option_id=? ORDER BY sort_order,dynamic_effect_id');$e->execute([$opt['dynamic_option_id']]);
            foreach($e->fetchAll() as $effect)$pdo->prepare('INSERT INTO plot_option_effects(template_option_id,effect_scope,target_code,delta_value,set_value,detail_text,sort_order) VALUES(?,?,?,?,?,?,?)')->execute([$templateOptionId,$effect['effect_scope'],$effect['target_code'],$effect['delta_value'],$effect['set_value'],$effect['detail_text'],$effect['sort_order']]);
        }
    }
}
