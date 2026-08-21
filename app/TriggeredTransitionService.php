<?php
final class TriggeredTransitionService
{
    public static function evaluate(int $sid, ?int $second=null, ?int $eventId=null): void
    {
        if ($sid <= 0) return;
        $pdo=db();
        $simQ=$pdo->prepare('SELECT plot_id,elapsed_seconds,current_phase FROM simulations WHERE simulation_id=?');
        $simQ->execute([$sid]);$sim=$simQ->fetch();if(!$sim)return;
        $second=$second??(int)$sim['elapsed_seconds'];

        // Allow cascades (A causes B causes C) while preventing runaway loops.
        for($pass=0;$pass<20;$pass++){
            $firedAny=false;
            $q=$pdo->prepare('SELECT * FROM plot_triggered_transitions WHERE plot_id=? AND active=1 ORDER BY priority,transition_id');
            $q->execute([(int)$sim['plot_id']]);
            foreach($q->fetchAll() as $tr){
                $marker='TRANSITION_FIRED_'.$tr['transition_id'];
                if((int)$tr['once_only']===1){$m=$pdo->prepare('SELECT 1 FROM simulation_state WHERE simulation_id=? AND state_key=?');$m->execute([$sid,$marker]);if($m->fetchColumn())continue;}
                if(!self::conditionsPass($sid,(int)$tr['transition_id'],(string)$sim['current_phase']))continue;
                self::applyTransition($sid,$tr,$second,$eventId);
                $pdo->prepare('INSERT INTO simulation_state(simulation_id,state_key,state_value,updated_at_second) VALUES(?,?,"YES",?) ON DUPLICATE KEY UPDATE state_value="YES",updated_at_second=VALUES(updated_at_second)')->execute([$sid,$marker,$second]);
                $firedAny=true;
            }
            if(!$firedAny)break;
            $simQ->execute([$sid]);$sim=$simQ->fetch();if(!$sim)break;
        }
    }

    private static function conditionsPass(int $sid,int $transitionId,string $phase): bool
    {
        $q=db()->prepare('SELECT * FROM plot_triggered_transition_conditions WHERE transition_id=? ORDER BY sort_order,transition_condition_id');$q->execute([$transitionId]);
        foreach($q->fetchAll() as $c){
            if($c['state_key']==='STORY_PHASE'){$exists=true;$value=$phase;}else{$s=db()->prepare('SELECT state_value FROM simulation_state WHERE simulation_id=? AND state_key=?');$s->execute([$sid,$c['state_key']]);$value=$s->fetchColumn();$exists=$value!==false;$value=$exists?(string)$value:null;}
            $list=array_values(array_filter(array_map('trim',explode(',',(string)$c['compare_value'])),fn($v)=>$v!==''));
            $ok=match($c['operator']){
                'EQ'=>$exists && $value===(string)$c['compare_value'],
                'NEQ'=>!$exists || $value!==(string)$c['compare_value'],
                'IN'=>$exists && in_array($value,$list,true),
                'NOT_IN'=>!$exists || !in_array($value,$list,true),
                'EXISTS'=>$exists,
                'NOT_EXISTS'=>!$exists,
                default=>true,
            };
            if(!$ok)return false;
        }
        return true;
    }

    private static function applyTransition(int $sid,array $tr,int $second,?int $eventId): void
    {
        $pdo=db();$effects=$pdo->prepare('SELECT * FROM plot_triggered_transition_effects WHERE transition_id=? ORDER BY sort_order,transition_effect_id');$effects->execute([$tr['transition_id']]);$changes=[];
        foreach($effects->fetchAll() as $e){$c=self::applyEffect($sid,$e,$second,$eventId);if($c!=='')$changes[]=$c;}
        if(!$changes)return;
        $phaseQ=$pdo->prepare('SELECT current_phase FROM simulations WHERE simulation_id=?');$phaseQ->execute([$sid]);$phase=(string)$phaseQ->fetchColumn();
        $pdo->prepare('INSERT INTO simulation_events(simulation_id,event_second,phase,event_type,event_code,headline,event_text,visibility,caused_by_event_id) VALUES(?,?,?,?,?,?,?,"WORLD",?)')->execute([$sid,$second,$phase,'SYSTEM','TRANSITION_'.$tr['transition_code'].'_'.$second,$tr['label'],implode(' ',$changes),$eventId]);
    }

    private static function characterIdForRole(int $sid,?string $roleCode): ?int
    {
        if(!$roleCode)return null;$q=db()->prepare('SELECT c.character_id FROM characters c JOIN plot_roles pr ON pr.plot_role_id=c.plot_role_id WHERE c.simulation_id=? AND pr.role_code=? LIMIT 1');$q->execute([$sid,$roleCode]);$id=$q->fetchColumn();return$id===false?null:(int)$id;
    }

    private static function applyEffect(int $sid,array $e,int $second,?int $eventId): string
    {
        $pdo=db();$scope=$e['effect_scope'];$detail=(string)($e['detail_text']??'');$cid=self::characterIdForRole($sid,$e['target_role_code']??null);
        if($scope==='WORLD_STATE')return class_exists('CausalService')?CausalService::setWorldState($sid,(string)$e['target_code'],(string)($e['set_value']??''),$second,$detail):'';
        if($scope==='FACTOR'){$q=$pdo->prepare('SELECT current_value,min_value,max_value,label FROM simulation_factors WHERE simulation_id=? AND factor_code=? FOR UPDATE');$q->execute([$sid,$e['target_code']]);$r=$q->fetch();if(!$r)return'';$old=(int)$r['current_value'];$new=max((int)$r['min_value'],min((int)$r['max_value'],$old+(int)$e['delta_value']));if($old===$new)return'';$pdo->prepare('UPDATE simulation_factors SET current_value=? WHERE simulation_id=? AND factor_code=?')->execute([$new,$sid,$e['target_code']]);$pdo->prepare('INSERT INTO simulation_factor_history(simulation_id,factor_code,old_value,new_value,event_second,source_type,source_id,detail_text) VALUES(?,?,?,?,"TRANSITION",?,?,?)')->execute([$sid,$e['target_code'],$old,$new,$second,$e['transition_effect_id'],$detail?:null]);return$r['label'].' '.$old.'→'.$new.'.';}
        if(!$cid)return'';
        if($scope==='LOCATION'){$q=$pdo->prepare('SELECT current_location FROM characters WHERE character_id=?');$q->execute([$cid]);$old=(string)$q->fetchColumn();$new=(string)$e['set_value'];if($old===$new)return'';$pdo->prepare('UPDATE characters SET current_location=? WHERE character_id=?')->execute([$new,$cid]);$pdo->prepare('INSERT INTO character_history(simulation_id,character_id,event_id,event_second,history_type,title,detail_text) VALUES(?,?,?,?,"LOCATION","Location Changed",?)')->execute([$sid,$cid,$eventId,$second,$old.' → '.$new.($detail?' · '.$detail:'')]);return'Location '.$old.'→'.$new.'.';}
        if($scope==='GOAL'||$scope==='FEAR'){$column=$scope==='GOAL'?'goal_text':'fear_text';$q=$pdo->prepare('SELECT '.$column.' FROM characters WHERE character_id=? FOR UPDATE');$q->execute([$cid]);$old=(string)$q->fetchColumn();$new=(string)$e['set_value'];if($new===''||$old===$new)return'';$pdo->prepare('UPDATE characters SET '.$column.'=? WHERE character_id=?')->execute([$new,$cid]);$pdo->prepare('INSERT INTO character_state_history(simulation_id,character_id,event_id,event_second,state_type,old_value,new_value,reason_text) VALUES(?,?,?,?,?,?,?,?)')->execute([$sid,$cid,$eventId,$second,$scope,$old,$new,$detail?:null]);$pdo->prepare('INSERT INTO character_history(simulation_id,character_id,event_id,event_second,history_type,title,detail_text) VALUES(?,?,?,?,"CONSEQUENCE",?,?)')->execute([$sid,$cid,$eventId,$second,ucfirst(strtolower($scope)).' Changed',$old.' → '.$new.($detail?' · '.$detail:'')]);return ucfirst(strtolower($scope)).' changed.';}
        if($scope==='STAT'){$q=$pdo->prepare('SELECT current_value,label FROM character_stats WHERE character_id=? AND stat_code=? FOR UPDATE');$q->execute([$cid,$e['target_code']]);$r=$q->fetch();if(!$r)return'';$old=(int)$r['current_value'];$new=max(0,min(100,$old+(int)$e['delta_value']));if($old===$new)return'';$pdo->prepare('UPDATE character_stats SET current_value=? WHERE character_id=? AND stat_code=?')->execute([$new,$cid,$e['target_code']]);return$r['label'].' '.$old.'→'.$new.'.';}
        if($scope==='KNOWLEDGE'){$pdo->prepare('INSERT INTO character_knowledge(character_id,knowledge_key,knowledge_text,state,confidence,learned_at_second) VALUES(?,?,?,"KNOWN",100,?) ON DUPLICATE KEY UPDATE knowledge_text=VALUES(knowledge_text),state="KNOWN",confidence=100,learned_at_second=VALUES(learned_at_second)')->execute([$cid,$e['target_code'],$e['set_value']?:$detail,$second]);return$detail?:'Knowledge changed.';}
        return'';
    }
}
