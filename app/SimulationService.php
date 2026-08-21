<?php
final class SimulationService {
    public static function create(string $plotCode='CONCEALED_FAILURE', int $duration=300): int {
        $pdo=db();$q=$pdo->prepare('SELECT * FROM plots WHERE code=? AND status="active"');$q->execute([$plotCode]);$plot=$q->fetch();if(!$plot)throw new RuntimeException('Plot not found.');
        $pdo->beginTransaction();
        try{
            $pdo->prepare('INSERT INTO simulations(plot_id,duration_seconds,mode,status,current_phase) VALUES(?,?,"MANUAL","READY","SETUP")')->execute([$plot['plot_id'],$duration]);$sid=(int)$pdo->lastInsertId();
            $f=$pdo->prepare('SELECT * FROM plot_factors WHERE plot_id=?');$f->execute([$plot['plot_id']]);foreach($f->fetchAll()as$r)$pdo->prepare('INSERT INTO simulation_factors(simulation_id,factor_code,label,current_value,min_value,max_value) VALUES(?,?,?,?,?,?)')->execute([$sid,$r['factor_code'],$r['label'],$r['initial_value'],$r['min_value'],$r['max_value']]);
            $initialState=['GUEST_STATUS'=>'INJURED','GUEST_HELPERS'=>'1','WEST_ROUTE'=>'UNKNOWN','FARAH_EXIT_STATUS'=>'INSIDE'];
            $is=$pdo->prepare('INSERT INTO simulation_state(simulation_id,state_key,state_value,updated_at_second) VALUES(?,?,?,0)');foreach($initialState as$k=>$v)$is->execute([$sid,$k,$v]);
            $roles=$pdo->prepare('SELECT * FROM plot_roles WHERE plot_id=? ORDER BY CASE WHEN role_type="PLAYER" THEN 0 ELSE 1 END,slot_no');$roles->execute([$plot['plot_id']]);
            foreach($roles->fetchAll()as$r){
                $pdo->prepare('INSERT INTO characters(simulation_id,plot_role_id,actor_type,slot_no,name,description,private_knowledge,current_location,goal_text,fear_text) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([$sid,$r['plot_role_id'],$r['role_type'],$r['slot_no'],$r['name'],$r['public_description'],$r['private_knowledge'],$r['starting_location'],$r['goal_text'],$r['fear_text']]);$cid=(int)$pdo->lastInsertId();
                $s=$pdo->prepare('SELECT * FROM plot_role_stats WHERE plot_role_id=?');$s->execute([$r['plot_role_id']]);foreach($s->fetchAll()as$st)$pdo->prepare('INSERT INTO character_stats(character_id,stat_code,label,current_value) VALUES(?,?,?,?)')->execute([$cid,$st['stat_code'],$st['label'],$st['initial_value']]);
                if($r['private_knowledge'])$pdo->prepare('INSERT INTO character_knowledge(character_id,knowledge_key,knowledge_text,state,confidence,learned_at_second) VALUES(?,?,?,?,100,0)')->execute([$cid,'START_'.$r['role_code'],$r['private_knowledge'],'KNOWN']);
                $pdo->prepare('INSERT INTO character_history(simulation_id,character_id,event_second,history_type,title,detail_text) VALUES(?,?,0,"EXPERIENCED","Initial State",?)')->execute([$sid,$cid,'Location: '.$r['starting_location'].' | Goal: '.$r['goal_text'].' | Fear: '.($r['fear_text']?:'None specified')]);
            }
            $chars=$pdo->prepare('SELECT character_id FROM characters WHERE simulation_id=?');$chars->execute([$sid]);$ids=array_column($chars->fetchAll(),'character_id');foreach($ids as$a)foreach($ids as$b)if($a!==$b)$pdo->prepare('INSERT INTO relationships(simulation_id,from_character_id,to_character_id,trust,suspicion,loyalty) VALUES(?,?,?,?,?,?)')->execute([$sid,$a,$b,50,10,50]);
            $pdo->commit();return$sid;
        }catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }

    public static function start(int $sid): void {
        db()->prepare('UPDATE simulations SET status="RUNNING",current_phase="EXPOSITION",started_at_utc=UTC_TIMESTAMP() WHERE simulation_id=? AND status="READY"')->execute([$sid]);
        self::worldEvent($sid,0,'EXPOSITION','STORY_START','Story Begins','Bellweather Resort settles into a rain-heavy evening. The world is now active.');
    }

    public static function advance(int $sid,int $seconds=30): void {
        $pdo=db();$sim=self::simulation($sid);if($sim['status']==='READY'){self::start($sid);$sim=self::simulation($sid);}if($sim['status']!=='RUNNING')return;
        $next=min((int)$sim['duration_seconds'],(int)$sim['elapsed_seconds']+$seconds);$phase=self::phaseFor($next,(int)$sim['duration_seconds']);
        if(class_exists('BehaviorService'))BehaviorService::expire($sid,$next);
        $pdo->prepare('UPDATE simulations SET elapsed_seconds=?,current_phase=? WHERE simulation_id=?')->execute([$next,$phase,$sid]);self::generateDueEvents($sid,$next,$phase);
        if($next>=(int)$sim['duration_seconds']){$pdo->prepare('UPDATE simulations SET status="COMPLETE",current_phase="COMPLETE",ended_at_utc=UTC_TIMESTAMP() WHERE simulation_id=?')->execute([$sid]);if(class_exists('BehaviorService'))BehaviorService::expire($sid,$next+1);}
    }

    public static function choose(int $sid,int $decisionId,string $optionCode): void {if(!class_exists('BehaviorService'))throw new RuntimeException('Behavior engine is unavailable.');BehaviorService::choose($sid,$decisionId,$optionCode);}

    private static function phaseFor(int $elapsed,int $duration): string {$p=$duration?($elapsed/$duration):1;return$p<.18?'EXPOSITION':($p<.42?'INCITING_INCIDENT':($p<.72?'RISING_ACTION':($p<.92?'CLIMAX':'RESOLUTION')));}

    private static function generateDueEvents(int $sid,int $elapsed,string $phase): void {
        $existing=db()->prepare('SELECT event_code FROM simulation_events WHERE simulation_id=?');$existing->execute([$sid]);$codes=array_flip(array_column($existing->fetchAll(),'event_code'));
        if($elapsed>=45&&!isset($codes['WALL_SHIFT']))self::worldEvent($sid,45,'INCITING_INCIDENT','WALL_SHIFT','The East Wall Moves','A section of the east retaining wall gives way. The service road is damaged and a guest near the east stairwell is injured.');
        if($elapsed>=90&&!isset($codes['REPORT_MISSING']))self::reportEvent($sid);
        if($elapsed>=150&&!isset($codes['PRESSURE_RISE']))self::worldEvent($sid,150,'RISING_ACTION','PRESSURE_RISE','Pressure Builds','The storm worsens. What happened during the first minutes now shapes who has evidence, who is helping the injured guest, and who controls the group’s attention.');
        if($elapsed>=230&&!isset($codes['REAR_EXIT']))self::worldEvent($sid,230,'CLIMAX','REAR_EXIT','The Rear Exit','The east side becomes increasingly unsafe. The group must act with the evidence, relationships and positions created by their earlier choices.');
        if($elapsed>=285&&!isset($codes['FINAL_STATE']))self::worldEvent($sid,285,'RESOLUTION','FINAL_STATE','What Remains','The simulation reaches its final minutes. The ending is determined by the world factors and unresolved consequences created during play.');
    }

    private static function reportEvent(int $sid): void {
        $pdo=db();$q=$pdo->prepare('SELECT state_value FROM simulation_state WHERE simulation_id=? AND state_key="REPORT_HOLDER"');$q->execute([$sid]);$holder=(string)$q->fetchColumn();
        if($holder==='FARAH')$text='Alden reaches his office and finds the copied failed inspection report gone. Farah reached the evidence first.';
        elseif($holder==='ALDEN')$text='Alden secures the copied failed inspection report before it can disappear. Farah now has to challenge the evidence rather than simply remove it.';
        else $text='Alden checks the office and realizes the copied failed inspection report is exposed during the confusion. Nobody has clearly secured it yet.';
        self::worldEvent($sid,90,'INCITING_INCIDENT','REPORT_MISSING','The Report Becomes The Conflict',$text);
    }

    private static function worldEvent(int $sid,int $second,string $phase,string $code,string $headline,string $text): int {
        $pdo=db();$q=$pdo->prepare('SELECT event_id FROM simulation_events WHERE simulation_id=? AND event_code=?');$q->execute([$sid,$code]);$id=(int)$q->fetchColumn();if($id)return$id;
        $pdo->prepare('INSERT INTO simulation_events(simulation_id,event_second,phase,event_type,event_code,headline,event_text,visibility) VALUES(?,?,?,?,?,?,?,"WORLD")')->execute([$sid,$second,$phase,'WORLD',$code,$headline,$text]);$id=(int)$pdo->lastInsertId();
        $sim=self::simulation($sid);$pq=$pdo->prepare('SELECT COUNT(*) FROM plot_event_perceptions WHERE plot_id=? AND event_code=?');$pq->execute([$sim['plot_id'],$code]);$hasSpecific=(int)$pq->fetchColumn()>0;
        if($hasSpecific && class_exists('BehaviorService'))BehaviorService::onWorldEvent($sid,$id,$code,$second);
        else{$chars=$pdo->prepare('SELECT character_id FROM characters WHERE simulation_id=?');$chars->execute([$sid]);foreach($chars->fetchAll()as$c)$pdo->prepare('INSERT INTO character_history(simulation_id,character_id,event_id,event_second,history_type,title,detail_text) VALUES(?,?,?,?,"EXPERIENCED",?,?)')->execute([$sid,$c['character_id'],$id,$second,$headline,$text]);}
        return$id;
    }

    public static function snapshot(int $sid): array {
        $pdo=db();$s=$pdo->prepare('SELECT s.*,p.title,p.archetype,p.genre,p.setting_name FROM simulations s JOIN plots p ON p.plot_id=s.plot_id WHERE s.simulation_id=?');$s->execute([$sid]);$sim=$s->fetch();if(!$sim)throw new RuntimeException('Simulation not found.');
        $f=$pdo->prepare('SELECT * FROM simulation_factors WHERE simulation_id=? ORDER BY simulation_factor_id');$f->execute([$sid]);
        $c=$pdo->prepare('SELECT * FROM characters WHERE simulation_id=? ORDER BY CASE WHEN actor_type="PLAYER" THEN 0 ELSE 1 END,slot_no');$c->execute([$sid]);$chars=$c->fetchAll();
        foreach($chars as&$ch){
            $st=$pdo->prepare('SELECT * FROM character_stats WHERE character_id=? ORDER BY character_stat_id');$st->execute([$ch['character_id']]);$ch['stats']=$st->fetchAll();
            $h=$pdo->prepare('SELECT * FROM character_history WHERE character_id=? ORDER BY event_second,history_id');$h->execute([$ch['character_id']]);$ch['history']=$h->fetchAll();
            $d=$pdo->prepare('SELECT d.* FROM decisions d WHERE d.character_id=? AND d.status="OPEN" ORDER BY d.opens_at_second,d.decision_id');$d->execute([$ch['character_id']]);$ch['open_decisions']=$d->fetchAll();
            foreach($ch['open_decisions']as&$dec){
                $o=$pdo->prepare('SELECT do.*,pdto.template_option_id FROM decision_options do JOIN decisions d ON d.decision_id=do.decision_id JOIN simulation_events se ON se.event_id=d.event_id JOIN characters c ON c.character_id=d.character_id JOIN plot_roles pr ON pr.plot_role_id=c.plot_role_id JOIN plot_decision_templates pdt ON pdt.plot_id=pr.plot_id AND pdt.role_code=pr.role_code AND pdt.event_code=se.event_code JOIN plot_decision_template_options pdto ON pdto.template_id=pdt.template_id AND pdto.option_code=do.option_code WHERE do.decision_id=? ORDER BY do.sort_order,do.decision_option_id');
                $o->execute([$dec['decision_id']]);$options=[];foreach($o->fetchAll()as$opt){if(class_exists('CausalService')&&!CausalService::optionAllowed($sid,(int)$opt['template_option_id']))continue;$options[]=$opt;}$dec['options']=$options;
            }unset($dec);
            $ch['open_decisions']=array_values(array_filter($ch['open_decisions'],fn($d)=>!empty($d['options'])));
        }
        unset($ch);$e=$pdo->prepare('SELECT se.*,c.name actor_name FROM simulation_events se LEFT JOIN characters c ON c.character_id=se.actor_character_id WHERE se.simulation_id=? AND se.visibility="WORLD" ORDER BY se.event_second,se.event_id');$e->execute([$sid]);
        return['simulation'=>$sim,'factors'=>$f->fetchAll(),'characters'=>$chars,'world'=>$e->fetchAll()];
    }

    private static function simulation(int $sid): array {$q=db()->prepare('SELECT * FROM simulations WHERE simulation_id=?');$q->execute([$sid]);$r=$q->fetch();if(!$r)throw new RuntimeException('Simulation not found.');return$r;}
}
