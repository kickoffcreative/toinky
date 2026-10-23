<?php
final class BehaviorService {
    public static function onWorldEvent(int $sid,int $eventId,string $eventCode,int $second): void {
        $pdo=db();$sim=self::simulation($sid);
        $p=$pdo->prepare('SELECT pep.*,c.character_id,c.name,c.actor_type,c.slot_no FROM plot_event_perceptions pep JOIN plot_roles pr ON pr.plot_id=pep.plot_id AND pr.role_code=pep.role_code JOIN characters c ON c.simulation_id=? AND c.plot_role_id=pr.plot_role_id WHERE pep.plot_id=? AND pep.event_code=?');
        $p->execute([$sid,$sim['plot_id'],$eventCode]);
        foreach($p->fetchAll() as $r){
            $deliverySecond=$second+self::deliveryOffset($sid,$r,'perception');
            $pdo->prepare('INSERT INTO character_history(simulation_id,character_id,event_id,event_second,history_type,title,detail_text) VALUES(?,?,?,?,?,?,?)')->execute([$sid,$r['character_id'],$eventId,$deliverySecond,$r['history_type'],$r['title'],$r['perception_text']]);
            if(!empty($r['location_after'])) self::setLocation($sid,(int)$r['character_id'],$r['location_after'],$second,$eventId,'Event movement');
        }
        $t=$pdo->prepare('SELECT pdt.*,pr.plot_role_id,c.character_id,c.name,c.actor_type,c.controller_type,c.slot_no FROM plot_decision_templates pdt JOIN plot_roles pr ON pr.plot_id=pdt.plot_id AND pr.role_code=pdt.role_code AND pr.active=1 JOIN characters c ON c.simulation_id=? AND c.plot_role_id=pr.plot_role_id WHERE pdt.plot_id=? AND pdt.event_code=? AND pdt.active=1');
        $t->execute([$sid,$sim['plot_id'],$eventCode]);
        foreach($t->fetchAll() as $row){
            $opens=$second+self::deliveryOffset($sid,$row,'decision');$closes=$opens+max(5,(int)round((int)$row['decision_window_seconds']*self::durationScale($sim)));
            $pdo->prepare('INSERT IGNORE INTO decisions(simulation_id,character_id,event_id,prompt_text,status,opens_at_second,closes_at_second) VALUES(?,?,?,? ,"OPEN",?,?)')->execute([$sid,$row['character_id'],$eventId,$row['prompt_text'],$opens,$closes]);
            $q=$pdo->prepare('SELECT decision_id,status FROM decisions WHERE character_id=? AND event_id=?');$q->execute([$row['character_id'],$eventId]);$decision=$q->fetch();$did=(int)($decision['decision_id']??0);if(!$did)continue;
            $opts=$pdo->prepare('SELECT * FROM plot_decision_template_options WHERE template_id=? AND active=1 ORDER BY sort_order,template_option_id');$opts->execute([$row['template_id']]);$valid=0;
            foreach($opts->fetchAll() as $o){
                if(class_exists('CausalService') && !CausalService::optionAllowed($sid,(int)$o['template_option_id']))continue;
                $pdo->prepare('INSERT IGNORE INTO decision_options(decision_id,option_code,label,sort_order) VALUES(?,?,?,?)')->execute([$did,$o['option_code'],$o['label'],$o['sort_order']]);$valid++;
            }
            if($valid===0){$pdo->prepare('UPDATE decisions SET status="CLOSED",resolved_text="No valid action remained in the current world state." WHERE decision_id=? AND status="OPEN"')->execute([$did]);continue;}
            if(self::usesLegacyWeightedNpcController($row)&&!self::deferNpcToSimulationLab($sid,(int)$row['character_id']))self::resolveNpc($sid,$did,(int)$row['template_id'],$second);
        }
    }

    public static function choose(int $sid,int $decisionId,string $optionCode): void {
        $pdo=db();$sim=self::simulation($sid);
        $q=$pdo->prepare('SELECT d.*,c.actor_type FROM decisions d JOIN characters c ON c.character_id=d.character_id WHERE d.decision_id=? AND d.simulation_id=?');$q->execute([$decisionId,$sid]);$d=$q->fetch();
        if(!$d || $d['actor_type']!=='PLAYER') throw new RuntimeException('Player decision not found.');
        if($d['status']!=='OPEN') throw new RuntimeException('That decision is already closed.');
        if((int)$sim['elapsed_seconds']<(int)$d['opens_at_second']) throw new RuntimeException('That opportunity has not reached you yet.');
        if((int)$sim['elapsed_seconds']>(int)$d['closes_at_second']) throw new RuntimeException('That opportunity has already passed.');
        $o=self::decisionOption($sid,$d,$decisionId,$optionCode,$sim);
        self::applyOption($sid,$d,$o,(int)$sim['elapsed_seconds'],'PLAYER');
    }

    public static function chooseNpcForSimulationLab(int $sid,int $decisionId,string $optionCode): void {
        if(strtolower(trim((string)getenv('GAME2_SIM_NPC_REASONER')))!=='ollama')throw new RuntimeException('Simulation Lab NPC AI control is not enabled.');
        $pdo=db();$sim=self::simulation($sid);
        $q=$pdo->prepare('SELECT d.*,c.actor_type,c.character_id FROM decisions d JOIN characters c ON c.character_id=d.character_id WHERE d.decision_id=? AND d.simulation_id=?');$q->execute([$decisionId,$sid]);$d=$q->fetch();
        if(!$d || $d['actor_type']!=='NPC')throw new RuntimeException('Simulation Lab NPC decision not found.');
        if(!self::deferNpcToSimulationLab($sid,(int)$d['character_id']))throw new RuntimeException('This NPC is not assigned to Simulation Lab AI control.');
        if($d['status']!=='OPEN')throw new RuntimeException('That NPC decision is already closed.');
        if((int)$sim['elapsed_seconds']<(int)$d['opens_at_second'])throw new RuntimeException('That NPC opportunity has not opened yet.');
        if((int)$sim['elapsed_seconds']>(int)$d['closes_at_second'])throw new RuntimeException('That NPC opportunity has already passed.');
        $o=self::decisionOption($sid,$d,$decisionId,$optionCode,$sim);
        self::applyOption($sid,$d,$o,(int)$sim['elapsed_seconds'],'NPC');
    }

    public static function chooseControlled(int $sid,int $decisionId,string $optionCode): void
    {
        $pdo=db();$sim=self::simulation($sid);$q=$pdo->prepare('SELECT d.*,c.actor_type,c.controller_type FROM decisions d JOIN characters c ON c.character_id=d.character_id WHERE d.decision_id=? AND d.simulation_id=?');$q->execute([$decisionId,$sid]);$d=$q->fetch();
        if(!$d||$d['controller_type']!=='HUMAN')throw new RuntimeException('This decision is not assigned to a human controller.');
        if($d['status']!=='OPEN')throw new RuntimeException('That decision is already closed.');
        if((int)$sim['elapsed_seconds']<(int)$d['opens_at_second'])throw new RuntimeException('That opportunity has not reached you yet.');
        if((int)$sim['elapsed_seconds']>(int)$d['closes_at_second'])throw new RuntimeException('That opportunity has already passed.');
        $o=self::decisionOption($sid,$d,$decisionId,$optionCode,$sim);self::applyOption($sid,$d,$o,(int)$sim['elapsed_seconds'],(string)$d['actor_type']);
    }

    public static function chooseAutomated(int $sid,int $decisionId,string $optionCode): void
    {
        $pdo=db();$sim=self::simulation($sid);$q=$pdo->prepare('SELECT d.*,c.actor_type,c.controller_type FROM decisions d JOIN characters c ON c.character_id=d.character_id WHERE d.decision_id=? AND d.simulation_id=?');$q->execute([$decisionId,$sid]);$d=$q->fetch();
        if(!$d||$d['controller_type']!=='AI')throw new RuntimeException('This decision is not assigned to AI.');
        if($d['status']!=='OPEN')throw new RuntimeException('That decision is already closed.');
        if((int)$sim['elapsed_seconds']<(int)$d['opens_at_second'])throw new RuntimeException('That opportunity has not reached you yet.');
        if((int)$sim['elapsed_seconds']>(int)$d['closes_at_second'])throw new RuntimeException('That opportunity has already passed.');
        $o=self::decisionOption($sid,$d,$decisionId,$optionCode,$sim);self::applyOption($sid,$d,$o,(int)$sim['elapsed_seconds'],(string)$d['actor_type']);
    }

    public static function expire(int $sid,int $second): void {
        $pdo=db();$q=$pdo->prepare('SELECT d.*,c.name FROM decisions d JOIN characters c ON c.character_id=d.character_id WHERE d.simulation_id=? AND d.status="OPEN" AND d.closes_at_second<? ORDER BY d.closes_at_second,d.decision_id');$q->execute([$sid,$second]);
        foreach($q->fetchAll() as $d){
            $oq=$pdo->prepare('SELECT label FROM decision_options WHERE decision_id=? ORDER BY sort_order,decision_option_id');$oq->execute([$d['decision_id']]);$labels=array_map(static fn($label)=>lcfirst(trim((string)$label)),array_column($oq->fetchAll(),'label'));$last=array_pop($labels);$choiceText=$last??'act';if($labels)$choiceText=implode(', ',$labels).', or '.$choiceText;
            $personalText='You were given the chance to '.$choiceText.', but the moment passed before you could act — the situation changed.';
            $pdo->prepare('UPDATE decisions SET status="NO_ACTION",resolved_text=? WHERE decision_id=?')->execute([$personalText,$d['decision_id']]);
            $pdo->prepare('INSERT INTO character_history(simulation_id,character_id,event_id,event_second,history_type,title,detail_text) VALUES(?,?,?,?,"NO_ACTION",?,?)')->execute([$sid,$d['character_id'],$d['event_id'],$d['closes_at_second'],'The Opportunity Passed',$personalText]);
            self::worldLog($sid,(int)$d['closes_at_second'],'NO_ACTION',$d['character_id'],$d['name'].' let an opportunity pass','The situation changed without their intervention.',(int)$d['event_id']);
            if(class_exists('CausalService'))CausalService::applyNoActionDecision($sid,(int)$d['decision_id']);
        }
    }

    private static function decisionOption(int $sid,array $d,int $decisionId,string $optionCode,array $sim): array {
        $o=db()->prepare('SELECT pdto.* FROM decision_options do JOIN plot_decision_template_options pdto ON pdto.option_code=do.option_code AND pdto.active=1 JOIN plot_decision_templates pdt ON pdt.template_id=pdto.template_id JOIN characters c ON c.character_id=? JOIN plot_roles pr ON pr.plot_role_id=c.plot_role_id WHERE do.decision_id=? AND do.option_code=? AND pdt.plot_id=? AND pdt.role_code=pr.role_code AND pdt.event_code=(SELECT se.event_code FROM decisions dx JOIN simulation_events se ON se.event_id=dx.event_id WHERE dx.decision_id=?) LIMIT 1');
        $o->execute([$d['character_id'],$decisionId,$optionCode,$sim['plot_id'],$decisionId]);$opt=$o->fetch();if(!$opt)throw new RuntimeException('Decision option not found.');
        if(class_exists('CausalService')&&!CausalService::optionAllowed($sid,(int)$opt['template_option_id']))throw new RuntimeException('That action is no longer possible because the world has changed.');
        return$opt;
    }

    private static function durationScale(array $sim): float
    {
        $d=(int)($sim['duration_seconds']??0);
        return $d>0?max(1.0,$d/300):1.0;
    }

    private static function sentenceText(string $text): string
    {
        $text=trim($text);if($text==='')return'';return preg_match('/[.!?\x{2026}]$/u',$text)?$text:$text.'.';
    }

    private static function deliveryOffset(int $sid,array $row,string $kind): int
    {
        if(array_key_exists('delivery_offset_seconds',$row)&&$row['delivery_offset_seconds']!==null)return max(0,(int)$row['delivery_offset_seconds']);
        try{
            $q=db()->prepare('SELECT g.play_duration_seconds,pds.minimum_game_duration_seconds,pds.perception_step_seconds,pds.decision_step_seconds,pds.npc_additional_seconds FROM games g JOIN simulations s ON s.simulation_id=g.simulation_id JOIN plots p ON p.plot_id=s.plot_id JOIN platform_story_delivery_settings pds ON pds.setting_code="LONG_GAME_STAGGER" AND pds.active=1 WHERE g.simulation_id=? LIMIT 1');
            $q->execute([$sid]);$settings=$q->fetch();if(!$settings)return 0;
            $duration=(int)($settings['play_duration_seconds']??0);
            if($duration<(int)$settings['minimum_game_duration_seconds'])return 0;
            $step=$kind==='decision'?(int)$settings['decision_step_seconds']:(int)$settings['perception_step_seconds'];
            $offset=max(0,((int)($row['slot_no']??1)-1)*$step);
            if(($row['actor_type']??'')==='NPC')$offset+=(int)$settings['npc_additional_seconds'];
            return $offset;
        }catch(Throwable $e){return 0;}
    }

    private static function usesLegacyWeightedNpcController(array $row): bool
    {
        return ($row['actor_type']??'')==='NPC'
            && ($row['controller_type']??'HUMAN')==='HUMAN'
            && strtolower(trim((string)getenv('GAME2_SIM_NPC_REASONER')))==='weighted';
    }

    private static function deferNpcToSimulationLab(int $sid,int $characterId): bool {
        if(strtolower(trim((string)getenv('GAME2_SIM_NPC_REASONER')))!=='ollama')return false;
        $assigned=(int)getenv('GAME2_SIM_AI_NPC_ID');return$assigned>0&&$assigned===$characterId;
    }

    private static function resolveNpc(int $sid,int $decisionId,int $templateId,int $second): void {
        $pdo=db();$q=$pdo->prepare('SELECT d.*,c.name,c.character_id FROM decisions d JOIN characters c ON c.character_id=d.character_id WHERE d.decision_id=?');$q->execute([$decisionId]);$d=$q->fetch();if(!$d||$d['status']!=='OPEN')return;
        $o=$pdo->prepare('SELECT * FROM plot_decision_template_options WHERE template_id=? AND active=1 ORDER BY sort_order');$o->execute([$templateId]);$options=[];
        foreach($o->fetchAll() as$r){if(class_exists('CausalService')&&!CausalService::optionAllowed($sid,(int)$r['template_option_id']))continue;$options[]=$r;}
        if(!$options){$pdo->prepare('UPDATE decisions SET status="CLOSED",resolved_text="No valid NPC action remained in the current world state." WHERE decision_id=?')->execute([$decisionId]);return;}
        $total=max(1,array_sum(array_map(fn($r)=>max(0,(int)$r['npc_weight']),$options)));$roll=random_int(1,$total);$pick=$options[0];$cursor=0;
        foreach($options as $r){$cursor+=max(0,(int)$r['npc_weight']);if($roll<=$cursor){$pick=$r;break;}}
        $considered=[];$remaining=100;$lastIndex=count($options)-1;
        foreach($options as $i=>$r){$chance=$i===$lastIndex?$remaining:(int)round((max(0,(int)$r['npc_weight'])/$total)*100);$chance=max(0,min($remaining,$chance));$remaining-=$chance;$considered[]=$r['label'].' ['.$chance.'%]'.($r['rationale_text']?' — '.$r['rationale_text']:'');}
        $pdo->prepare('INSERT INTO character_history(simulation_id,character_id,event_id,event_second,history_type,title,detail_text) VALUES(?,?,?,?,"INTERACTION",?,?)')->execute([$sid,$d['character_id'],$d['event_id'],$second,'Options Considered',implode("\n",$considered)]);
        self::applyOption($sid,$d,$pick,$second,'NPC');
    }

    private static function applyOption(int $sid,array $d,array $opt,int $second,string $actorType): void {
        $pdo=db();$pdo->beginTransaction();
        try{
            if(class_exists('CausalService')&&!CausalService::optionAllowed($sid,(int)$opt['template_option_id']))throw new RuntimeException('Action invalidated by current world state.');
            $pdo->prepare('UPDATE decisions SET status="CHOSEN",chosen_option_code=?,resolved_text=? WHERE decision_id=?')->execute([$opt['option_code'],$opt['result_text'],$d['decision_id']]);
            $pdo->prepare('INSERT INTO character_history(simulation_id,character_id,event_id,event_second,history_type,title,detail_text) VALUES(?,?,?,?,"DECISION",?,?)')->execute([$sid,$d['character_id'],$d['event_id'],$second,'Decision: '.$opt['label'],$opt['result_text']]);
            $effects=$pdo->prepare('SELECT * FROM plot_option_effects WHERE template_option_id=? AND active=1 ORDER BY sort_order,effect_id');$effects->execute([$opt['template_option_id']]);
            $changes=[];foreach($effects->fetchAll() as $e){$changes[]=self::applyEffect($sid,(int)$d['character_id'],$d['event_id'],$second,$e,$actorType,(int)$d['decision_id']);}
            $cq=$pdo->prepare('SELECT name FROM characters WHERE character_id=?');$cq->execute([$d['character_id']]);$name=(string)$cq->fetchColumn();
            $changes=array_values(array_filter($changes,static fn($c)=>!preg_match('/^\s*[A-Z][A-Z0-9_]*\s*=\s*[^.!?]+\.?\s*$/u',(string)$c)));$resultText=trim((string)$opt['result_text']);$labelText=trim((string)$opt['label']);if($resultText===''||$resultText===$labelText||preg_match('/\byou\b|\byour\b|\byours\b|\byourself\b/i',$resultText))$worldText='chose to '.lcfirst(rtrim($labelText,'.')).'.';else $worldText=self::sentenceText($resultText);foreach($changes as$change){$change=self::sentenceText((string)$change);if($change!=='')$worldText.=' '.$change;}
            self::worldLog($sid,$second,$actorType.'_ACTION',(int)$d['character_id'],$name.': '.$opt['label'],$worldText,(int)$d['event_id']);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }

    private static function applyEffect(int $sid,int $cid,int $eventId,int $second,array $e,string $sourceType,?int $sourceId): string {
        $pdo=db();$scope=$e['effect_scope'];$detail=$e['detail_text']?:'';
        if($scope==='FACTOR'){
            $q=$pdo->prepare('SELECT current_value,min_value,max_value,label FROM simulation_factors WHERE simulation_id=? AND factor_code=? FOR UPDATE');$q->execute([$sid,$e['target_code']]);$r=$q->fetch();if(!$r)return'';$old=(int)$r['current_value'];$new=max((int)$r['min_value'],min((int)$r['max_value'],$old+(int)$e['delta_value']));
            if($new===$old)return trim($detail);$pdo->prepare('UPDATE simulation_factors SET current_value=? WHERE simulation_id=? AND factor_code=?')->execute([$new,$sid,$e['target_code']]);$pdo->prepare('INSERT INTO simulation_factor_history(simulation_id,factor_code,old_value,new_value,event_second,source_type,source_id,detail_text) VALUES(?,?,?,?,?,?,?,?)')->execute([$sid,$e['target_code'],$old,$new,$second,$sourceType,$sourceId,$detail?:null]);return $r['label'].' '.$old.'→'.$new.'.';
        }
        if($scope==='STAT'){
            $q=$pdo->prepare('SELECT current_value,label FROM character_stats WHERE character_id=? AND stat_code=? FOR UPDATE');$q->execute([$cid,$e['target_code']]);$r=$q->fetch();if(!$r)return'';$old=(int)$r['current_value'];$new=max(0,min(100,$old+(int)$e['delta_value']));$pdo->prepare('UPDATE character_stats SET current_value=? WHERE character_id=? AND stat_code=?')->execute([$new,$cid,$e['target_code']]);$pdo->prepare('INSERT INTO character_history(simulation_id,character_id,event_id,event_second,history_type,title,detail_text) VALUES(?,?,?,?,"STAT_CHANGE",?,?)')->execute([$sid,$cid,$eventId,$second,$r['label'].' changed',$old.' → '.$new.($detail?' · '.$detail:'')]);return $r['label'].' '.$old.'→'.$new.'.';
        }
        if($scope==='LOCATION'){self::setLocation($sid,$cid,(string)$e['set_value'],$second,$eventId,$detail?:'Location changed');return 'Location → '.$e['set_value'].'.';}
        if($scope==='GOAL' || $scope==='FEAR'){$state=self::setCharacterState($sid,$cid,$eventId,$second,$scope,(string)$e['set_value'],$detail);$tc=(string)($e['target_code']??'');$sv=(string)($e['set_value']??'');if($tc!==''&&$sv!==''&&GenericStateService::resolvesManaged($sid,$tc))GenericStateService::setByKey($sid,$tc,$sv,$second,$detail);return $state;}
        if($scope==='KNOWLEDGE'){$pdo->prepare('INSERT INTO character_knowledge(character_id,knowledge_key,knowledge_text,state,confidence,learned_at_second) VALUES(?,?,?,"KNOWN",100,?) ON DUPLICATE KEY UPDATE knowledge_text=VALUES(knowledge_text),state="KNOWN",confidence=100,learned_at_second=VALUES(learned_at_second)')->execute([$cid,$e['target_code'],$e['set_value']?:$detail,$second]);$pdo->prepare('INSERT INTO character_history(simulation_id,character_id,event_id,event_second,history_type,title,detail_text) VALUES(?,?,?,?,"KNOWLEDGE",?,?)')->execute([$sid,$cid,$eventId,$second,'Knowledge Gained',$detail?:($e['set_value']?:$e['target_code'])]);$tc=(string)($e['target_code']??'');$sv=(string)($e['set_value']??'');if($tc!==''&&$sv!==''&&GenericStateService::resolvesManaged($sid,$tc))GenericStateService::setByKey($sid,$tc,$sv,$second,$detail);return'';}
        if($scope==='WORLD_STATE'){if(class_exists('CausalService'))return CausalService::setWorldState($sid,(string)$e['target_code'],(string)($e['set_value']??''),$second,$detail);$pdo->prepare('INSERT INTO simulation_state(simulation_id,state_key,state_value,updated_at_second) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE state_value=VALUES(state_value),updated_at_second=VALUES(updated_at_second)')->execute([$sid,$e['target_code'],$e['set_value']??'', $second]);return $detail?:($e['target_code'].'='.$e['set_value'].'.');}
        return'';
    }

    private static function setCharacterState(int $sid,int $cid,int $eventId,int $second,string $type,string $value,string $reason): string {
        if($value==='')return'';$pdo=db();$column=$type==='GOAL'?'goal_text':'fear_text';$q=$pdo->prepare('SELECT '.$column.' FROM characters WHERE character_id=? AND simulation_id=? FOR UPDATE');$q->execute([$cid,$sid]);$old=(string)$q->fetchColumn();if($old===$value)return'';
        $pdo->prepare('UPDATE characters SET '.$column.'=? WHERE character_id=? AND simulation_id=?')->execute([$value,$cid,$sid]);
        $pdo->prepare('INSERT INTO character_state_history(simulation_id,character_id,event_id,event_second,state_type,old_value,new_value,reason_text) VALUES(?,?,?,?,?,?,?,?)')->execute([$sid,$cid,$eventId,$second,$type,$old,$value,$reason?:null]);
        $pdo->prepare('INSERT INTO character_history(simulation_id,character_id,event_id,event_second,history_type,title,detail_text) VALUES(?,?,?,?,"CONSEQUENCE",?,?)')->execute([$sid,$cid,$eventId,$second,ucfirst(strtolower($type)).' Changed',$old.' → '.$value.($reason?' · '.$reason:'')]);
        return ucfirst(strtolower($type)).' → '.rtrim($value).(preg_match('/[.!?]$/u',rtrim($value))?'':'.');
    }

    private static function setLocation(int $sid,int $cid,string $location,int $second,?int $eventId,string $reason): void {$pdo=db();$q=$pdo->prepare('SELECT current_location FROM characters WHERE character_id=? AND simulation_id=?');$q->execute([$cid,$sid]);$old=(string)$q->fetchColumn();if($old===$location)return;$pdo->prepare('UPDATE characters SET current_location=? WHERE character_id=?')->execute([$location,$cid]);$pdo->prepare('INSERT INTO character_history(simulation_id,character_id,event_id,event_second,history_type,title,detail_text) VALUES(?,?,?,?,"LOCATION",?,?)')->execute([$sid,$cid,$eventId,$second,'Location Changed',$old.' → '.$location.' · '.$reason]);}
    private static function worldLog(int $sid,int $second,string $code,?int $actorId,string $headline,string $text,?int $cause): int {$sim=self::simulation($sid);$pdo=db();$pdo->prepare('INSERT INTO simulation_events(simulation_id,event_second,phase,event_type,actor_character_id,event_code,headline,event_text,visibility,caused_by_event_id) VALUES(?,?,?,?,?,?,?,?,"WORLD",?)')->execute([$sid,$second,$sim['current_phase'],$actorId?($code==='NO_ACTION'?'PLAYER':(str_starts_with($code,'NPC')?'NPC':'PLAYER')):'SYSTEM',$actorId,$code.'_'.$second.'_'.bin2hex(random_bytes(2)),$headline,$text,$cause]);return(int)$pdo->lastInsertId();}
    private static function simulation(int $sid): array {$q=db()->prepare('SELECT * FROM simulations WHERE simulation_id=?');$q->execute([$sid]);$r=$q->fetch();if(!$r)throw new RuntimeException('Simulation not found.');return$r;}
}
