<?php
final class StateSyncService
{
    public static function reconcile(int $sid): void
    {
        if($sid<=0)return;
        self::syncCurrentMotives($sid);
        self::evaluateMotiveLifecycle($sid);
        self::syncEvacuationLocations($sid);
    }

    private static function syncCurrentMotives(int $sid): void
    {
        $pdo=db();
        $q=$pdo->prepare('SELECT character_id,goal_text,fear_text FROM characters WHERE simulation_id=?');
        $q->execute([$sid]);
        foreach($q->fetchAll() as $c){
            self::syncMotive($sid,(int)$c['character_id'],'GOAL',(string)$c['goal_text']);
            self::syncMotive($sid,(int)$c['character_id'],'FEAR',(string)$c['fear_text']);
        }
    }

    private static function syncMotive(int $sid,int $cid,string $type,string $text): void
    {
        if($text==='')return;
        $pdo=db();
        $q=$pdo->prepare('SELECT character_motive_state_id,motive_text,lifecycle_status FROM character_motive_state WHERE character_id=? AND motive_type=? LIMIT 1');
        $q->execute([$cid,$type]);$r=$q->fetch();
        $s=$pdo->prepare('SELECT elapsed_seconds FROM simulations WHERE simulation_id=?');$s->execute([$sid]);$second=(int)$s->fetchColumn();
        if(!$r){
            $pdo->prepare('INSERT INTO character_motive_state(simulation_id,character_id,motive_type,motive_text,lifecycle_status,started_at_second) VALUES(?,?,?,?,"ACTIVE",?)')->execute([$sid,$cid,$type,$text,$second]);
            return;
        }
        if((string)$r['motive_text']===$text)return;
        $pdo->prepare('INSERT INTO character_motive_history(simulation_id,character_id,motive_type,motive_text,lifecycle_status,event_second,reason_text) VALUES(?,?,?,?,?,?,?)')->execute([$sid,$cid,$type,$r['motive_text'],'REPLACED',$second,'Character state changed to a new active motive.']);
        $pdo->prepare('UPDATE character_motive_state SET motive_text=?,lifecycle_status="ACTIVE",started_at_second=?,ended_at_second=NULL WHERE character_motive_state_id=?')->execute([$text,$second,$r['character_motive_state_id']]);
    }

    private static function evaluateMotiveLifecycle(int $sid): void
    {
        $pdo=db();
        $s=$pdo->prepare('SELECT plot_id,elapsed_seconds FROM simulations WHERE simulation_id=?');$s->execute([$sid]);$sim=$s->fetch();if(!$sim)return;
        $rules=$pdo->prepare('SELECT r.*,c.character_id,c.goal_text,c.fear_text FROM plot_motive_lifecycle_rules r JOIN plot_roles pr ON pr.plot_id=r.plot_id AND pr.role_code=r.role_code JOIN characters c ON c.simulation_id=? AND c.plot_role_id=pr.plot_role_id WHERE r.plot_id=? AND r.active=1 ORDER BY r.priority,r.motive_rule_id');
        $rules->execute([$sid,$sim['plot_id']]);
        foreach($rules->fetchAll() as $r){
            $current=$r['motive_type']==='GOAL'?(string)$r['goal_text']:(string)$r['fear_text'];
            if($r['match_text']!==null && (string)$r['match_text']!==$current)continue;
            if(!self::ruleConditionsPass($sid,(int)$r['motive_rule_id']))continue;
            self::applyLifecycleRule($sid,(int)$r['character_id'],$r,(int)$sim['elapsed_seconds']);
        }
    }

    private static function ruleConditionsPass(int $sid,int $ruleId): bool
    {
        $q=db()->prepare('SELECT state_key,operator,compare_value FROM plot_motive_lifecycle_conditions WHERE motive_rule_id=? ORDER BY sort_order,motive_condition_id');$q->execute([$ruleId]);
        foreach($q->fetchAll() as $c)if(!self::conditionPasses($sid,$c['state_key'],$c['operator'],$c['compare_value']))return false;
        return true;
    }

    private static function conditionPasses(int $sid,string $key,string $operator,?string $compare): bool
    {
        if($key==='STORY_PHASE'){
            $q=db()->prepare('SELECT current_phase FROM simulations WHERE simulation_id=?');$q->execute([$sid]);$value=(string)$q->fetchColumn();$exists=$value!=='';
        }else{
            $q=db()->prepare('SELECT state_value FROM simulation_state WHERE simulation_id=? AND state_key=?');$q->execute([$sid,$key]);$v=$q->fetchColumn();$exists=$v!==false;$value=$exists?(string)$v:'';
        }
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

    private static function applyLifecycleRule(int $sid,int $cid,array $r,int $second): void
    {
        $pdo=db();$type=(string)$r['motive_type'];$column=$type==='GOAL'?'goal_text':'fear_text';$current=(string)($type==='GOAL'?$r['goal_text']:$r['fear_text']);
        $m=$pdo->prepare('SELECT character_motive_state_id,motive_text,lifecycle_status FROM character_motive_state WHERE character_id=? AND motive_type=? LIMIT 1');$m->execute([$cid,$type]);$state=$m->fetch();
        if($state && (string)$state['motive_text']===$current && (string)$state['lifecycle_status']!=='ACTIVE')return;

        $status=(string)$r['terminal_status'];$reason=(string)($r['reason_text']??'');
        if($state){$pdo->prepare('UPDATE character_motive_state SET lifecycle_status=?,ended_at_second=? WHERE character_motive_state_id=?')->execute([$status,$second,$state['character_motive_state_id']]);}
        $pdo->prepare('INSERT INTO character_motive_history(simulation_id,character_id,motive_type,motive_text,lifecycle_status,event_second,reason_text) VALUES(?,?,?,?,?,?,?)')->execute([$sid,$cid,$type,$current,$status,$second,$reason?:null]);
        $pdo->prepare('INSERT INTO character_history(simulation_id,character_id,event_second,history_type,title,detail_text) VALUES(?,?,?,"CONSEQUENCE",?,?)')->execute([$sid,$cid,$second,ucfirst(strtolower($type)).' '.$status,$current.' → '.$status.($reason?' · '.$reason:'')]);

        $replacement=trim((string)($r['replacement_text']??''));
        if($replacement==='')return;
        $pdo->prepare('UPDATE characters SET '.$column.'=? WHERE character_id=? AND simulation_id=?')->execute([$replacement,$cid,$sid]);
        if($state){$pdo->prepare('UPDATE character_motive_state SET motive_text=?,lifecycle_status="ACTIVE",started_at_second=?,ended_at_second=NULL WHERE character_motive_state_id=?')->execute([$replacement,$second,$state['character_motive_state_id']]);}
        else{$pdo->prepare('INSERT INTO character_motive_state(simulation_id,character_id,motive_type,motive_text,lifecycle_status,started_at_second) VALUES(?,?,?,?,"ACTIVE",?)')->execute([$sid,$cid,$type,$replacement,$second]);}
        $pdo->prepare('INSERT INTO character_motive_history(simulation_id,character_id,motive_type,motive_text,lifecycle_status,event_second,reason_text) VALUES(?,?,?,?,"ACTIVE",?,?)')->execute([$sid,$cid,$type,$replacement,$second,'Replacement motive activated after '.$status.'.']);
        $pdo->prepare('INSERT INTO character_history(simulation_id,character_id,event_second,history_type,title,detail_text) VALUES(?,?,?,"CONSEQUENCE",?,?)')->execute([$sid,$cid,$second,ucfirst(strtolower($type)).' Replaced',$current.' → '.$replacement]);
    }

    private static function syncEvacuationLocations(int $sid): void
    {
        $pdo=db();
        $q=$pdo->prepare('SELECT state_key,state_value FROM simulation_state WHERE simulation_id=? AND state_key IN ("GUEST_STATUS","GUEST_SECOND_HELPER")');
        $q->execute([$sid]);$state=[];foreach($q->fetchAll() as $r)$state[$r['state_key']]=$r['state_value'];
        if(($state['GUEST_STATUS']??'')!=='EVACUATED')return;

        self::moveRole($sid,'LEILA','West Evacuation Point','Completed the injured guest evacuation.');
        $helper=(string)($state['GUEST_SECOND_HELPER']??'');
        if(in_array($helper,['KELLAN','WREN'],true))self::moveRole($sid,$helper,'West Evacuation Point','Completed the injured guest evacuation as the second helper.');
    }

    private static function moveRole(int $sid,string $roleCode,string $location,string $reason): void
    {
        $pdo=db();
        $q=$pdo->prepare('SELECT c.character_id,c.current_location,s.elapsed_seconds FROM characters c JOIN plot_roles pr ON pr.plot_role_id=c.plot_role_id JOIN simulations s ON s.simulation_id=c.simulation_id WHERE c.simulation_id=? AND pr.role_code=? LIMIT 1');
        $q->execute([$sid,$roleCode]);$r=$q->fetch();if(!$r||$r['current_location']===$location)return;
        $pdo->prepare('UPDATE characters SET current_location=? WHERE character_id=?')->execute([$location,$r['character_id']]);
        $pdo->prepare('INSERT INTO character_history(simulation_id,character_id,event_second,history_type,title,detail_text) VALUES(?,?,?,"LOCATION","Location Changed",?)')->execute([$sid,$r['character_id'],(int)$r['elapsed_seconds'],$r['current_location'].' → '.$location.' · '.$reason]);
    }
}
