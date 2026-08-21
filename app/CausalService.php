<?php
final class CausalService
{
    public static function reconcile(int $sid): void
    {
        if ($sid <= 0) return;
        self::applyPendingNoActionEffects($sid);
        self::refreshResolution($sid);
    }

    private static function applyPendingNoActionEffects(int $sid): void
    {
        $pdo = db();
        $q = $pdo->prepare('SELECT d.decision_id,d.event_id,d.character_id,se.event_code,pr.role_code,c.name,se.event_second FROM decisions d JOIN simulation_events se ON se.event_id=d.event_id JOIN characters c ON c.character_id=d.character_id JOIN plot_roles pr ON pr.plot_role_id=c.plot_role_id WHERE d.simulation_id=? AND d.status="NO_ACTION"');
        $q->execute([$sid]);
        foreach ($q->fetchAll() as $d) {
            $marker='NO_ACTION_EFFECT_'.$d['decision_id'];
            $m=$pdo->prepare('SELECT 1 FROM simulation_state WHERE simulation_id=? AND state_key=?');
            $m->execute([$sid,$marker]);
            if($m->fetchColumn()) continue;

            $sim=$pdo->prepare('SELECT plot_id FROM simulations WHERE simulation_id=?');
            $sim->execute([$sid]);$plotId=(int)$sim->fetchColumn();
            $e=$pdo->prepare('SELECT * FROM plot_no_action_effects WHERE plot_id=? AND event_code=? AND role_code=? ORDER BY sort_order,no_action_effect_id');
            $e->execute([$plotId,$d['event_code'],$d['role_code']]);
            $changes=[];
            foreach($e->fetchAll() as $effect){
                $change=self::applyEffect($sid,(int)$d['character_id'],(int)$d['event_id'],(int)$d['event_second'],$effect);
                if($change!=='')$changes[]=$change;
            }
            $pdo->prepare('INSERT INTO simulation_state(simulation_id,state_key,state_value,updated_at_second) VALUES(?,?,"APPLIED",?) ON DUPLICATE KEY UPDATE state_value="APPLIED",updated_at_second=VALUES(updated_at_second)')->execute([$sid,$marker,(int)$d['event_second']]);
            if($changes){
                $phase=self::phaseAt($sid,(int)$d['event_second']);
                $pdo->prepare('INSERT INTO simulation_events(simulation_id,event_second,phase,event_type,actor_character_id,event_code,headline,event_text,visibility,caused_by_event_id) VALUES(?,?,?,?,?,?,?,?,"WORLD",?)')->execute([$sid,(int)$d['event_second'],$phase,'PLAYER',(int)$d['character_id'],'NO_ACTION_EFFECT_'.$d['decision_id'],$d['name'].': consequence of inaction',implode(' ',$changes),(int)$d['event_id']]);
                $pdo->prepare('INSERT INTO character_history(simulation_id,character_id,event_id,event_second,history_type,title,detail_text) VALUES(?,?,?,?,"CONSEQUENCE",?,?)')->execute([$sid,(int)$d['character_id'],(int)$d['event_id'],(int)$d['event_second'],'Consequence Of Inaction',implode(' ',$changes)]);
            }
        }
    }

    private static function applyEffect(int $sid,int $cid,int $eventId,int $second,array $e): string
    {
        $pdo=db();$scope=$e['effect_scope'];$detail=(string)($e['detail_text']??'');
        if($scope==='FACTOR'){
            $q=$pdo->prepare('SELECT current_value,min_value,max_value,label FROM simulation_factors WHERE simulation_id=? AND factor_code=?');$q->execute([$sid,$e['target_code']]);$r=$q->fetch();if(!$r)return'';
            $old=(int)$r['current_value'];$new=max((int)$r['min_value'],min((int)$r['max_value'],$old+(int)$e['delta_value']));
            $pdo->prepare('UPDATE simulation_factors SET current_value=? WHERE simulation_id=? AND factor_code=?')->execute([$new,$sid,$e['target_code']]);
            return $r['label'].' '.$old.'→'.$new.'. '.trim($detail);
        }
        if($scope==='STAT'){
            $q=$pdo->prepare('SELECT current_value,label FROM character_stats WHERE character_id=? AND stat_code=?');$q->execute([$cid,$e['target_code']]);$r=$q->fetch();if(!$r)return'';
            $old=(int)$r['current_value'];$new=max(0,min(100,$old+(int)$e['delta_value']));$pdo->prepare('UPDATE character_stats SET current_value=? WHERE character_id=? AND stat_code=?')->execute([$new,$cid,$e['target_code']]);
            $pdo->prepare('INSERT INTO character_history(simulation_id,character_id,event_id,event_second,history_type,title,detail_text) VALUES(?,?,?,?,"STAT_CHANGE",?,?)')->execute([$sid,$cid,$eventId,$second,$r['label'].' changed',$old.' → '.$new.($detail?' · '.$detail:'')]);
            return $r['label'].' '.$old.'→'.$new.'. '.trim($detail);
        }
        if($scope==='LOCATION'){
            $q=$pdo->prepare('SELECT current_location FROM characters WHERE character_id=?');$q->execute([$cid]);$old=(string)$q->fetchColumn();$new=(string)$e['set_value'];
            if($old!==$new){$pdo->prepare('UPDATE characters SET current_location=? WHERE character_id=?')->execute([$new,$cid]);$pdo->prepare('INSERT INTO character_history(simulation_id,character_id,event_id,event_second,history_type,title,detail_text) VALUES(?,?,?,?,"LOCATION","Location Changed",?)')->execute([$sid,$cid,$eventId,$second,$old.' → '.$new.($detail?' · '.$detail:'')]);}
            return 'Location '.$old.'→'.$new.'. '.trim($detail);
        }
        if($scope==='KNOWLEDGE'){
            $pdo->prepare('INSERT INTO character_knowledge(character_id,knowledge_key,knowledge_text,state,confidence,learned_at_second) VALUES(?,?,?,"KNOWN",100,?) ON DUPLICATE KEY UPDATE knowledge_text=VALUES(knowledge_text),state="KNOWN",confidence=100,learned_at_second=VALUES(learned_at_second)')->execute([$cid,$e['target_code'],$e['set_value']?:$detail,$second]);
            return $detail?:'Knowledge changed.';
        }
        if($scope==='WORLD_STATE'){
            $pdo->prepare('INSERT INTO simulation_state(simulation_id,state_key,state_value,updated_at_second) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE state_value=VALUES(state_value),updated_at_second=VALUES(updated_at_second)')->execute([$sid,$e['target_code'],$e['set_value']??'',$second]);
            return $detail?:($e['target_code'].'='.$e['set_value'].'.');
        }
        return'';
    }

    private static function refreshResolution(int $sid): void
    {
        $pdo=db();$q=$pdo->prepare('SELECT event_id FROM simulation_events WHERE simulation_id=? AND event_code="FINAL_STATE" LIMIT 1');$q->execute([$sid]);$eventId=(int)$q->fetchColumn();if(!$eventId)return;
        $f=$pdo->prepare('SELECT factor_code,current_value FROM simulation_factors WHERE simulation_id=?');$f->execute([$sid]);$factors=[];foreach($f->fetchAll() as$r)$factors[$r['factor_code']]=(int)$r['current_value'];
        $s=$pdo->prepare('SELECT state_key,state_value FROM simulation_state WHERE simulation_id=?');$s->execute([$sid]);$state=[];foreach($s->fetchAll()as$r)$state[$r['state_key']]=$r['state_value'];
        $guest=$state['GUEST_STATUS']??'UNRESOLVED';$exit=$state['FARAH_EXIT_STATUS']??($state['FARAH_POSITIONED_TO_LEAVE']??null ? 'POSITIONED TO LEAVE':'UNRESOLVED');$holder=$state['REPORT_HOLDER']??'UNCLEAR';
        $evidence=(int)($factors['EVIDENCE']??50);$safety=(int)($factors['SAFETY']??50);$control=(int)($factors['CONTROL']??50);$suspicion=(int)($factors['SUSPICION']??0);$trust=(int)($factors['TRUST']??50);
        $truth=$evidence>=70?'The evidence survives in a strong, difficult-to-dismiss form.':($evidence>=45?'Enough evidence survives to keep the concealment question alive.':'The evidence chain is badly weakened and the truth will be difficult to prove.');
        $people=$guest==='EVACUATED'?'The injured guest reaches the west evacuation point.':($guest==='AT_RISK'?'The injured guest remains exposed as the east-side danger peaks.':($guest==='EVACUATING'?'The injured guest is still being moved when the immediate story closes.':'The injured guest’s evacuation remains unresolved.'));
        $farah=$exit==='BLOCKED'?'Farah is stopped at the rear exit and cannot simply carry the night’s unresolved questions away with her.':($exit==='LEFT'?'Farah leaves Bellweather before the group can fully resolve the confrontation.':($exit==='ATTEMPTING'?'Farah is still trying to leave as the final confrontation closes.':'Farah’s final position remains inside the unresolved crisis.'));
        $text=$people.' '.$truth.' '.$farah.' Final factors: Safety '.$safety.', Evidence '.$evidence.', Farah Control '.$control.', Public Suspicion '.$suspicion.', Group Trust '.$trust.'.';
        $pdo->prepare('UPDATE simulation_events SET headline="What Actually Remains",event_text=? WHERE event_id=?')->execute([$text,$eventId]);
    }

    private static function phaseAt(int $sid,int $second): string
    {
        $q=db()->prepare('SELECT duration_seconds FROM simulations WHERE simulation_id=?');$q->execute([$sid]);$duration=max(1,(int)$q->fetchColumn());$p=$second/$duration;
        return $p<.18?'EXPOSITION':($p<.42?'INCITING_INCIDENT':($p<.72?'RISING_ACTION':($p<.92?'CLIMAX':'RESOLUTION')));
    }
}
