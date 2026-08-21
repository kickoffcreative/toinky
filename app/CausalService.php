<?php
final class CausalService
{
    public static function reconcile(int $sid): void
    {
        if ($sid <= 0) return;
        self::applyPendingNoActionEffects($sid);
        self::refreshResolution($sid);
    }

    public static function applyNoActionDecision(int $sid,int $decisionId): void
    {
        $pdo=db();
        $q=$pdo->prepare('SELECT d.decision_id,d.event_id,d.character_id,d.closes_at_second,se.event_code,pr.role_code,c.name FROM decisions d JOIN simulation_events se ON se.event_id=d.event_id JOIN characters c ON c.character_id=d.character_id JOIN plot_roles pr ON pr.plot_role_id=c.plot_role_id WHERE d.simulation_id=? AND d.decision_id=? AND d.status="NO_ACTION" LIMIT 1');
        $q->execute([$sid,$decisionId]);$d=$q->fetch();if(!$d)return;
        $marker='NO_ACTION_EFFECT_'.$d['decision_id'];
        $m=$pdo->prepare('SELECT 1 FROM simulation_state WHERE simulation_id=? AND state_key=?');$m->execute([$sid,$marker]);if($m->fetchColumn())return;

        $sim=$pdo->prepare('SELECT plot_id FROM simulations WHERE simulation_id=?');$sim->execute([$sid]);$plotId=(int)$sim->fetchColumn();
        $e=$pdo->prepare('SELECT * FROM plot_no_action_effects WHERE plot_id=? AND event_code=? AND role_code=? ORDER BY sort_order,no_action_effect_id');$e->execute([$plotId,$d['event_code'],$d['role_code']]);
        $changes=[];$second=(int)$d['closes_at_second'];
        foreach($e->fetchAll() as $effect){
            if(!self::noActionEffectAllowed($sid,(int)$effect['no_action_effect_id']))continue;
            $change=self::applyEffect($sid,(int)$d['character_id'],(int)$d['event_id'],$second,$effect,'NO_ACTION',(int)$d['decision_id']);
            if($change!=='')$changes[]=$change;
        }
        $pdo->prepare('INSERT INTO simulation_state(simulation_id,state_key,state_value,updated_at_second) VALUES(?,?,"APPLIED",?) ON DUPLICATE KEY UPDATE state_value="APPLIED",updated_at_second=VALUES(updated_at_second)')->execute([$sid,$marker,$second]);
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
        foreach($q->fetchAll() as $c)if(!self::conditionPasses($sid,$c['state_key'],$c['operator'],$c['compare_value']))return false;
        return true;
    }

    public static function optionAllowed(int $sid,int $templateOptionId): bool
    {
        $q=db()->prepare('SELECT state_key,operator,compare_value FROM plot_option_conditions WHERE template_option_id=? ORDER BY sort_order,option_condition_id');$q->execute([$templateOptionId]);
        foreach($q->fetchAll() as $c)if(!self::conditionPasses($sid,$c['state_key'],$c['operator'],$c['compare_value']))return false;
        return true;
    }

    private static function conditionPasses(int $sid,string $key,string $operator,?string $compare): bool
    {
        $q=db()->prepare('SELECT state_value FROM simulation_state WHERE simulation_id=? AND state_key=?');$q->execute([$sid,$key]);$exists=false;$value=$q->fetchColumn();if($value!==false)$exists=true;$value=$exists?(string)$value:null;
        $list=array_values(array_filter(array_map('trim',explode(',',(string)$compare)),fn($v)=>$v!==''));
        return match($operator){
            'EQ'=>$exists && $value===(string)$compare,
            'NEQ'=>!$exists || $value!==(string)$compare,
            'IN'=>$exists && in_array($value,$list,true),
            'NOT_IN'=>!$exists || !in_array($value,$list,true),
            'EXISTS'=>$exists,
            'NOT_EXISTS'=>!$exists,
            default=>true,
        };
    }

    private static function applyEffect(int $sid,int $cid,int $eventId,int $second,array $e,string $sourceType='CAUSAL',?int $sourceId=null): string
    {
        $pdo=db();$scope=$e['effect_scope'];$detail=(string)($e['detail_text']??'');
        if($scope==='FACTOR'){
            $q=$pdo->prepare('SELECT current_value,min_value,max_value,label FROM simulation_factors WHERE simulation_id=? AND factor_code=? FOR UPDATE');$q->execute([$sid,$e['target_code']]);$r=$q->fetch();if(!$r)return'';
            $old=(int)$r['current_value'];$new=max((int)$r['min_value'],min((int)$r['max_value'],$old+(int)$e['delta_value']));
            if($new===$old)return trim($detail);
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
            return $detail?:'Knowledge changed.';
        }
        if($scope==='WORLD_STATE')return self::setWorldState($sid,(string)$e['target_code'],(string)($e['set_value']??''),$second,$detail);
        return'';
    }

    public static function setWorldState(int $sid,string $key,string $value,int $second,string $detail=''): string
    {
        $pdo=db();$q=$pdo->prepare('SELECT state_value FROM simulation_state WHERE simulation_id=? AND state_key=?');$q->execute([$sid,$key]);$old=$q->fetchColumn();$exists=$old!==false;$old=$exists?(string)$old:null;
        if($exists && $old===$value)return '';

        if($key==='GUEST_STATUS'){
            $allowed=[
                null=>['INJURED'],
                'INJURED'=>['STABILIZED','AT_RISK'],
                'STABILIZED'=>['EVACUATING','AT_RISK'],
                'EVACUATING'=>['EVACUATED','AT_RISK'],
                'AT_RISK'=>['STABILIZED','EVACUATING','EVACUATED'],
                'EVACUATED'=>[],
            ];
            if(!in_array($value,$allowed[$old]??[],true))return '';
        }

        if($key==='FARAH_EXIT_STATUS'){
            $allowed=[
                null=>['INSIDE'],
                'INSIDE'=>['POSITIONED'],
                'POSITIONED'=>['INSIDE','ATTEMPTING'],
                'ATTEMPTING'=>['LEFT','BLOCKED'],
                'LEFT'=>[],
                'BLOCKED'=>[],
            ];
            if(!in_array($value,$allowed[$old]??[],true))return '';
        }

        if($key==='GUEST_HELPERS'){
            $n=(int)$value;if($n<0||$n>6)return '';$value=(string)$n;
        }
        if($key==='WEST_ROUTE' && !in_array($value,['UNKNOWN','USABLE','BLOCKED'],true))return '';
        if($key==='REPORT_HOLDER' && !in_array($value,['NONE','ALDEN','FARAH'],true))return '';
        if($key==='ALDEN_EVIDENCE_KNOWLEDGE' && !in_array($value,['UNKNOWN','KNOWN'],true))return '';
        if($key==='ALDEN_SHAREABLE_EVIDENCE' && !in_array($value,['NO','YES'],true))return '';
        if($key==='REPORT_COPY_STATE' && !in_array($value,['NONE','ALDEN_COPY_EXISTS'],true))return '';
        if($key==='EVIDENCE_WITNESSES' && !in_array($value,['ALDEN_ONLY','NIKO_INFORMED','CORROBORATED'],true))return '';
        if($key==='EVIDENCE_EXTERNALIZED' && !in_array($value,['NO','YES'],true))return '';
        if($key==='EVIDENCE_DISTRIBUTION' && !in_array($value,['PRIVATE','SHARED'],true))return '';

        $pdo->prepare('INSERT INTO simulation_state(simulation_id,state_key,state_value,updated_at_second) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE state_value=VALUES(state_value),updated_at_second=VALUES(updated_at_second)')->execute([$sid,$key,$value,$second]);
        return $detail?:($key.'='.($value!==''?$value:'EMPTY').'.');
    }

    private static function refreshResolution(int $sid): void
    {
        $pdo=db();$q=$pdo->prepare('SELECT event_id FROM simulation_events WHERE simulation_id=? AND event_code="FINAL_STATE" LIMIT 1');$q->execute([$sid]);$eventId=(int)$q->fetchColumn();if(!$eventId)return;
        $f=$pdo->prepare('SELECT factor_code,current_value FROM simulation_factors WHERE simulation_id=?');$f->execute([$sid]);$factors=[];foreach($f->fetchAll() as$r)$factors[$r['factor_code']]=(int)$r['current_value'];
        $s=$pdo->prepare('SELECT state_key,state_value FROM simulation_state WHERE simulation_id=?');$s->execute([$sid]);$state=[];foreach($s->fetchAll()as$r)$state[$r['state_key']]=$r['state_value'];
        $guest=$state['GUEST_STATUS']??'UNRESOLVED';$exit=$state['FARAH_EXIT_STATUS']??'INSIDE';
        $holder=$state['REPORT_HOLDER']??'NONE';$externalized=$state['EVIDENCE_EXTERNALIZED']??'NO';$distribution=$state['EVIDENCE_DISTRIBUTION']??'PRIVATE';$witnesses=$state['EVIDENCE_WITNESSES']??'ALDEN_ONLY';$copy=$state['REPORT_COPY_STATE']??'NONE';
        $evidence=(int)($factors['EVIDENCE']??50);$safety=(int)($factors['SAFETY']??50);$control=(int)($factors['CONTROL']??50);$suspicion=(int)($factors['SUSPICION']??0);$trust=(int)($factors['TRUST']??50);

        if($externalized==='YES')$truth='An evidence summary now exists outside Bellweather, so local control of the original report can no longer erase the entire evidence trail.';
        elseif($distribution==='SHARED' || $copy==='ALDEN_COPY_EXISTS')$truth='Evidence survives in more than one recoverable path, so no single character completely controls the proof.';
        elseif($witnesses==='CORROBORATED' || $witnesses==='NIKO_INFORMED')$truth='The evidence is no longer only Alden’s private claim; another person can corroborate at least part of what he knows.';
        elseif($holder==='ALDEN')$truth='Alden still physically controls the failed report, but the evidence has not yet been distributed beyond him.';
        elseif($holder==='FARAH')$truth='Farah physically controls the failed report, leaving the documentary evidence vulnerable to her decisions.';
        else $truth=$evidence>=70?'The evidence score is strong, but the physical report remains unsecured and the surviving proof depends on what characters know.':($evidence>=45?'Enough knowledge and circumstantial evidence survives to keep the concealment question alive, but the physical report is not securely distributed.':'The evidence chain is badly weakened and the truth will be difficult to prove.');

        $people=$guest==='EVACUATED'?'The injured guest reaches the west evacuation point.':($guest==='AT_RISK'?'The injured guest remains exposed as the east-side danger peaks.':($guest==='EVACUATING'?'The injured guest is still being moved when the immediate story closes.':($guest==='STABILIZED'?'The injured guest is stabilized but remains inside the danger zone.':'The injured guest’s evacuation remains unresolved.')));
        $farah=$exit==='BLOCKED'?'Farah is stopped at the rear exit and cannot simply carry the night’s unresolved questions away with her.':($exit==='LEFT'?'Farah leaves Bellweather before the group can fully resolve the confrontation.':($exit==='ATTEMPTING'?'Farah is still trying to leave as the final confrontation closes.':($exit==='POSITIONED'?'Farah remains near the rear exit but never completes an escape attempt.':'Farah remains inside Bellweather as the confrontation closes.')));
        $text=$people.' '.$truth.' '.$farah.' Final factors: Safety '.$safety.', Evidence '.$evidence.', Farah Control '.$control.', Public Suspicion '.$suspicion.', Group Trust '.$trust.'.';
        $pdo->prepare('UPDATE simulation_events SET headline="What Actually Remains",event_text=? WHERE event_id=?')->execute([$text,$eventId]);
    }

    private static function phaseAt(int $sid,int $second): string
    {
        $q=db()->prepare('SELECT duration_seconds FROM simulations WHERE simulation_id=?');$q->execute([$sid]);$duration=max(1,(int)$q->fetchColumn());$p=$second/$duration;
        return $p<.18?'EXPOSITION':($p<.42?'INCITING_INCIDENT':($p<.72?'RISING_ACTION':($p<.92?'CLIMAX':'RESOLUTION')));
    }
}
