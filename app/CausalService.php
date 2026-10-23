<?php
final class CausalService
{
    public static function reconcile(int $sid): void
    {
        if($sid<=0)return;
        GenericStateService::initializePlotState($sid);
        self::applyPendingNoActionEffects($sid);
        ResolutionService::refresh($sid);
    }

    public static function applyNoActionDecision(int $sid,int $decisionId): void
    {
        $pdo=db();
        $q=$pdo->prepare('SELECT d.decision_id,d.event_id,d.character_id,d.closes_at_second,se.event_code,pr.role_code,c.name FROM decisions d JOIN simulation_events se ON se.event_id=d.event_id JOIN characters c ON c.character_id=d.character_id JOIN plot_roles pr ON pr.plot_role_id=c.plot_role_id WHERE d.simulation_id=? AND d.decision_id=? AND d.status="NO_ACTION" LIMIT 1');
        $q->execute([$sid,$decisionId]);$d=$q->fetch();if(!$d)return;
        $marker='NO_ACTION_EFFECT_'.$d['decision_id'];
        $m=GenericStateService::getByKey($sid,$marker);if($m['exists'])return;

        $sim=$pdo->prepare('SELECT plot_id FROM simulations WHERE simulation_id=?');$sim->execute([$sid]);$plotId=(int)$sim->fetchColumn();
        $e=$pdo->prepare('SELECT * FROM plot_no_action_effects WHERE plot_id=? AND event_code=? AND role_code=? ORDER BY sort_order,no_action_effect_id');$e->execute([$plotId,$d['event_code'],$d['role_code']]);
        $changes=[];$second=(int)$d['closes_at_second'];
        foreach($e->fetchAll() as $effect){
            if(!self::noActionEffectAllowed($sid,(int)$effect['no_action_effect_id']))continue;
            $change=self::applyEffect($sid,(int)$d['character_id'],(int)$d['event_id'],$second,$effect,'NO_ACTION',(int)$d['decision_id']);
            if($change!=='')$changes[]=$change;
        }
        GenericStateService::setByKey($sid,$marker,'APPLIED',$second);
        $changes=array_values(array_filter($changes,static fn($c)=>!preg_match('/^\s*[A-Z][A-Z0-9_]*\s*=\s*[^.!?]+\.?\s*$/u',(string)$c)));
        if($changes){
            $phase=self::phaseAt($sid,$second);
            $pdo->prepare('INSERT INTO simulation_events(simulation_id,event_second,phase,event_type,actor_character_id,event_code,headline,event_text,visibility,caused_by_event_id) VALUES(?,?,?,?,?,?,?,?,"WORLD",?)')->execute([$sid,$second,$phase,'PLAYER',(int)$d['character_id'],'NO_ACTION_EFFECT_'.$d['decision_id'],$d['name'].': consequence of inaction',implode(' ',$changes),(int)$d['event_id']]);
            $pdo->prepare('INSERT INTO character_history(simulation_id,character_id,event_id,event_second,history_type,title,detail_text) VALUES(?,?,?,?,"CONSEQUENCE",?,?)')->execute([$sid,(int)$d['character_id'],(int)$d['event_id'],$second,'Consequence Of Inaction',implode(' ',$changes)]);
        }
    }

    private static function applyPendingNoActionEffects(int $sid): void
    {
        $q=db()->prepare('SELECT decision_id FROM decisions WHERE simulation_id=? AND status="NO_ACTION" ORDER BY closes_at_second,decision_id');$q->execute([$sid]);
        foreach($q->fetchAll() as $r)self::applyNoActionDecision($sid,(int)$r['decision_id']);
    }

    private static function noActionEffectAllowed(int $sid,int $effectId): bool
    {
        $q=db()->prepare('SELECT state_key,operator,compare_value FROM plot_no_action_conditions WHERE no_action_effect_id=? ORDER BY sort_order,no_action_condition_id');$q->execute([$effectId]);
        foreach($q->fetchAll() as $c)if(!GenericStateService::conditionPasses($sid,$c['state_key'],$c['operator'],$c['compare_value']))return false;
        return true;
    }

    public static function optionAllowed(int $sid,int $templateOptionId): bool
    {
        $q=db()->prepare('SELECT state_key,operator,compare_value FROM plot_option_conditions WHERE template_option_id=? ORDER BY sort_order,option_condition_id');$q->execute([$templateOptionId]);
        foreach($q->fetchAll() as $c)if(!GenericStateService::conditionPasses($sid,$c['state_key'],$c['operator'],$c['compare_value']))return false;
        return true;
    }

    private static function applyEffect(int $sid,int $cid,int $eventId,int $second,array $e,string $sourceType='CAUSAL',?int $sourceId=null): string
    {
        $pdo=db();$scope=(string)$e['effect_scope'];$detail=(string)($e['detail_text']??'');
        if($scope==='FACTOR'){
            $q=$pdo->prepare('SELECT current_value,min_value,max_value,label FROM simulation_factors WHERE simulation_id=? AND factor_code=? FOR UPDATE');$q->execute([$sid,$e['target_code']]);$r=$q->fetch();if(!$r)return'';
            $old=(int)$r['current_value'];$new=max((int)$r['min_value'],min((int)$r['max_value'],$old+(int)$e['delta_value']));if($new===$old)return trim($detail);
            $pdo->prepare('UPDATE simulation_factors SET current_value=? WHERE simulation_id=? AND factor_code=?')->execute([$new,$sid,$e['target_code']]);
            $pdo->prepare('INSERT INTO simulation_factor_history(simulation_id,factor_code,old_value,new_value,event_second,source_type,source_id,detail_text) VALUES(?,?,?,?,?,?,?,?)')->execute([$sid,$e['target_code'],$old,$new,$second,$sourceType,$sourceId,$detail?:null]);
            return $r['label'].' '.$old.'→'.$new.'. '.trim($detail);
        }
        if($scope==='STAT'){
            $q=$pdo->prepare('SELECT current_value,label FROM character_stats WHERE character_id=? AND stat_code=? FOR UPDATE');$q->execute([$cid,$e['target_code']]);$r=$q->fetch();if(!$r)return'';
            $old=(int)$r['current_value'];$new=max(0,min(100,$old+(int)$e['delta_value']));$pdo->prepare('UPDATE character_stats SET current_value=? WHERE character_id=? AND stat_code=?')->execute([$new,$cid,$e['target_code']]);
            $pdo->prepare('INSERT INTO character_history(simulation_id,character_id,event_id,event_second,history_type,title,detail_text) VALUES(?,?,?,?,"STAT_CHANGE",?,?)')->execute([$sid,$cid,$eventId,$second,$r['label'].' changed',$old.' → '.$new.($detail?' · '.$detail:'')]);
            return $r['label'].' '.$old.'→'.$new.'. '.trim($detail);
        }
        if($scope==='LOCATION'){
            $q=$pdo->prepare('SELECT current_location FROM characters WHERE character_id=?');$q->execute([$cid]);$old=(string)$q->fetchColumn();$new=(string)$e['set_value'];
            if($old!==$new){$pdo->prepare('UPDATE characters SET current_location=? WHERE character_id=?')->execute([$new,$cid]);$pdo->prepare('INSERT INTO character_history(simulation_id,character_id,event_id,event_second,history_type,title,detail_text) VALUES(?,?,?,?,"LOCATION","Location Changed",?)')->execute([$sid,$cid,$eventId,$second,$old.' → '.$new.($detail?' · '.$detail:'')]);}
            return $old===$new?'':'Location '.$old.'→'.$new.'. '.trim($detail);
        }
        if($scope==='KNOWLEDGE'){
            $pdo->prepare('INSERT INTO character_knowledge(character_id,knowledge_key,knowledge_text,state,confidence,learned_at_second) VALUES(?,?,?,"KNOWN",100,?) ON DUPLICATE KEY UPDATE knowledge_text=VALUES(knowledge_text),state="KNOWN",confidence=100,learned_at_second=VALUES(learned_at_second)')->execute([$cid,$e['target_code'],$e['set_value']?:$detail,$second]);
            $tc=(string)($e['target_code']??'');$sv=(string)($e['set_value']??'');if($tc!==''&&$sv!==''&&GenericStateService::resolvesManaged($sid,$tc))GenericStateService::setByKey($sid,$tc,$sv,$second,$detail);
            return $detail?:'Knowledge changed.';
        }
        if($scope==='GOAL' || $scope==='FEAR'){$motive=self::setMotive($sid,$cid,$eventId,$second,$scope,(string)($e['set_value']??''),$detail);$tc=(string)($e['target_code']??'');$sv=(string)($e['set_value']??'');if($tc!==''&&$sv!==''&&GenericStateService::resolvesManaged($sid,$tc))GenericStateService::setByKey($sid,$tc,$sv,$second,$detail);return $motive;}
        if($scope==='WORLD_STATE')return GenericStateService::setByKey($sid,(string)$e['target_code'],(string)($e['set_value']??''),$second,$detail);
        return'';
    }

    private static function setMotive(int $sid,int $cid,int $eventId,int $second,string $type,string $value,string $reason): string
    {
        if($value==='')return'';$pdo=db();$column=$type==='GOAL'?'goal_text':'fear_text';$q=$pdo->prepare('SELECT '.$column.' FROM characters WHERE character_id=? AND simulation_id=?');$q->execute([$cid,$sid]);$old=(string)$q->fetchColumn();if($old===$value)return'';
        $pdo->prepare('UPDATE characters SET '.$column.'=? WHERE character_id=? AND simulation_id=?')->execute([$value,$cid,$sid]);
        if(self::tableExists('character_state_history'))$pdo->prepare('INSERT INTO character_state_history(simulation_id,character_id,event_id,event_second,state_type,old_value,new_value,reason_text) VALUES(?,?,?,?,?,?,?,?)')->execute([$sid,$cid,$eventId,$second,$type,$old,$value,$reason?:null]);
        $pdo->prepare('INSERT INTO character_history(simulation_id,character_id,event_id,event_second,history_type,title,detail_text) VALUES(?,?,?,?,"CONSEQUENCE",?,?)')->execute([$sid,$cid,$eventId,$second,ucfirst(strtolower($type)).' Changed',$old.' → '.$value.($reason?' · '.$reason:'')]);
        return ucfirst(strtolower($type)).' → '.$value.'.';
    }

    public static function setWorldState(int $sid,string $key,string $value,int $second,string $detail=''): string
    {
        return GenericStateService::setByKey($sid,$key,$value,$second,$detail);
    }

    private static function tableExists(string $table): bool
    {
        $q=db()->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');$q->execute([$table]);return(int)$q->fetchColumn()>0;
    }

    private static function phaseAt(int $sid,int $second): string
    {
        $q=db()->prepare('SELECT duration_seconds FROM simulations WHERE simulation_id=?');$q->execute([$sid]);$duration=max(1,(int)$q->fetchColumn());$p=$second/$duration;
        return $p<.18?'EXPOSITION':($p<.42?'INCITING_INCIDENT':($p<.72?'RISING_ACTION':($p<.92?'CLIMAX':'RESOLUTION')));
    }
}
