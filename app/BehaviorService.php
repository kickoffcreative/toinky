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
        foreach(self::decisionTemplateRows($sid,$eventCode) as $row){
            $opens=$second+self::deliveryOffset($sid,$row,'decision');$closes=$opens+max(5,(int)round((int)$row['decision_window_seconds']*self::durationScale($sim)));
            $pdo->prepare('INSERT IGNORE INTO decisions(simulation_id,character_id,event_id,prompt_text,status,opens_at_second,closes_at_second) VALUES(?,?,?,? ,"OPEN",?,?)')->execute([$sid,$row['character_id'],$eventId,$row['prompt_text'],$opens,$closes]);
            $q=$pdo->prepare('SELECT decision_id,status FROM decisions WHERE character_id=? AND event_id=?');$q->execute([$row['character_id'],$eventId]);$decision=$q->fetch();$did=(int)($decision['decision_id']??0);if(!$did)continue;
            $options=self::templateOptions($sid,$row);$valid=0;
            foreach($options as $o){
                // Pinned opportunity options carry no per-option conditions
                // in the snapshot, so there is nothing to check for them.
                if(empty($row['_pinned_opportunity_id'])&&class_exists('CausalService') && !CausalService::optionAllowed($sid,(int)$o['template_option_id']))continue;
                $pdo->prepare('INSERT IGNORE INTO decision_options(decision_id,option_code,label,sort_order) VALUES(?,?,?,?)')->execute([$did,$o['option_code'],$o['label'],$o['sort_order']]);$valid++;
            }
            if($valid===0){$pdo->prepare('UPDATE decisions SET status="CLOSED",resolved_text="No valid action remained in the current world state." WHERE decision_id=? AND status="OPEN"')->execute([$did]);continue;}
            if(self::usesLegacyWeightedNpcController($row)&&!self::deferNpcToSimulationLab($sid,(int)$row['character_id'])){
                if(!empty($row['_pinned_opportunity_id']))self::resolveNpcFromOptions($sid,$did,$options,$second);
                else self::resolveNpc($sid,$did,(int)$row['template_id'],$second);
            }
        }
    }

    // Task 5 Phase 2: decision templates for one event, in the live query's
    // row shape (template fields + the cast character's fields). Pinned
    // simulations read the snapshot; an opportunity wins its template slot
    // (the live mirror sync overwrites the template for the opportunity's
    // role), so a template for that role is replaced by the synthesized
    // opportunity row. Perceptions stay live: plot_event_perceptions is not
    // part of the snapshot.
    private static function decisionTemplateRows(int $sid,string $eventCode): array {
        $pdo=db();$sim=self::simulation($sid);
        if(!class_exists('SimulationContent')||SimulationContent::forSimulation($sid)===null){
            $t=$pdo->prepare('SELECT pdt.*,pr.plot_role_id,c.character_id,c.name,c.actor_type,c.controller_type,c.slot_no FROM plot_decision_templates pdt JOIN plot_roles pr ON pr.plot_id=pdt.plot_id AND pr.role_code=pdt.role_code AND pr.active=1 JOIN characters c ON c.simulation_id=? AND c.plot_role_id=pr.plot_role_id WHERE pdt.plot_id=? AND pdt.event_code=? AND pdt.active=1');
            $t->execute([$sid,$sim['plot_id'],$eventCode]);return$t->fetchAll();
        }
        $templates=SimulationContent::decisionTemplates($sid,$eventCode)??[];
        $opp=SimulationContent::opportunity($sid,$eventCode);
        if($opp!==null){
            $templates=array_values(array_filter($templates,static fn($t)=>(string)$t['role_code']!==(string)$opp['role_code']));
            $templates[]=['template_id'=>0,'prompt_text'=>$opp['prompt_text'],'decision_window_seconds'=>$opp['window_seconds'],'actor_type'=>$opp['actor_type'],'role_code'=>$opp['role_code'],'_pinned_opportunity_id'=>(int)$opp['dynamic_opportunity_id']];
        }
        $chars=$pdo->prepare('SELECT pr.role_code,pr.plot_role_id,c.character_id,c.name,c.actor_type,c.controller_type,c.slot_no FROM characters c JOIN plot_roles pr ON pr.plot_role_id=c.plot_role_id WHERE c.simulation_id=? AND pr.active=1');$chars->execute([$sid]);$byRole=[];foreach($chars->fetchAll()as$c)$byRole[(string)$c['role_code']]=$c;
        $rows=[];foreach($templates as$t){$role=(string)$t['role_code'];if(!isset($byRole[$role]))continue;$rows[]=array_merge($t,$byRole[$role]);}return$rows;
    }

    // Options for one template row: snapshot template options, or the
    // pinned dynamic options for a synthesized opportunity row.
    private static function templateOptions(int $sid,array $row): array {
        if(!empty($row['_pinned_opportunity_id'])&&class_exists('SimulationContent')){
            return SimulationContent::dynamicOptions($sid,(int)$row['_pinned_opportunity_id'])??[];
        }
        if(class_exists('SimulationContent')){$pinned=SimulationContent::decisionOptions($sid,(int)$row['template_id']);if($pinned!==null)return$pinned;}
        $opts=db()->prepare('SELECT * FROM plot_decision_template_options WHERE template_id=? AND active=1 ORDER BY sort_order,template_option_id');$opts->execute([$row['template_id']]);return$opts->fetchAll();
    }

    public static function choose(int $sid,int $decisionId,string $optionCode): void {
        $pdo=db();$sim=self::simulation($sid);
        $q=$pdo->prepare('SELECT d.*,c.actor_type FROM decisions d JOIN characters c ON c.character_id=d.character_id WHERE d.decision_id=? AND d.simulation_id=?');$q->execute([$decisionId,$sid]);$d=$q->fetch();
        if(!$d || $d['actor_type']!=='PLAYER') throw new RuntimeException('Player decision not found.');
        if($d['status']!=='OPEN') throw new RuntimeException('That decision is already closed.');
        if((int)$sim['elapsed_seconds']<(int)$d['opens_at_second']) throw new RuntimeException('That opportunity has not reached you yet.');
        if((int)$sim['elapsed_seconds']>(int)$d['closes_at_second']) throw new RuntimeException('That opportunity has already passed.');
        $o=self::decisionOption($sid,$d,$decisionId,$optionCode,$sim);
        self::applyAndConfirm($sid,$d,$o,(int)$sim['elapsed_seconds'],'PLAYER');
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
        self::applyAndConfirm($sid,$d,$o,(int)$sim['elapsed_seconds'],'NPC');
    }

    public static function chooseControlled(int $sid,int $decisionId,string $optionCode): void
    {
        $pdo=db();$sim=self::simulation($sid);$q=$pdo->prepare('SELECT d.*,c.actor_type,c.controller_type FROM decisions d JOIN characters c ON c.character_id=d.character_id WHERE d.decision_id=? AND d.simulation_id=?');$q->execute([$decisionId,$sid]);$d=$q->fetch();
        if(!$d||$d['controller_type']!=='HUMAN')throw new RuntimeException('This decision is not assigned to a human controller.');
        if($d['status']!=='OPEN')throw new RuntimeException('That decision is already closed.');
        if((int)$sim['elapsed_seconds']<(int)$d['opens_at_second'])throw new RuntimeException('That opportunity has not reached you yet.');
        if((int)$sim['elapsed_seconds']>(int)$d['closes_at_second'])throw new RuntimeException('That opportunity has already passed.');
        $o=self::decisionOption($sid,$d,$decisionId,$optionCode,$sim);self::applyAndConfirm($sid,$d,$o,(int)$sim['elapsed_seconds'],(string)$d['actor_type']);
    }

    public static function chooseAutomated(int $sid,int $decisionId,string $optionCode): void
    {
        $pdo=db();$sim=self::simulation($sid);$q=$pdo->prepare('SELECT d.*,c.actor_type,c.controller_type FROM decisions d JOIN characters c ON c.character_id=d.character_id WHERE d.decision_id=? AND d.simulation_id=?');$q->execute([$decisionId,$sid]);$d=$q->fetch();
        if(!$d||$d['controller_type']!=='AI')throw new RuntimeException('This decision is not assigned to AI.');
        if($d['status']!=='OPEN')throw new RuntimeException('That decision is already closed.');
        if((int)$sim['elapsed_seconds']<(int)$d['opens_at_second'])throw new RuntimeException('That opportunity has not reached you yet.');
        if((int)$sim['elapsed_seconds']>(int)$d['closes_at_second'])throw new RuntimeException('That opportunity has already passed.');
        $o=self::decisionOption($sid,$d,$decisionId,$optionCode,$sim);self::applyAndConfirm($sid,$d,$o,(int)$sim['elapsed_seconds'],(string)$d['actor_type']);
    }

    public static function expire(int $sid,int $second): void {
        $pdo=db();$ownsTransaction=!$pdo->inTransaction();if($ownsTransaction)$pdo->beginTransaction();
        try{
            $q=$pdo->prepare('SELECT d.*,c.name FROM decisions d JOIN characters c ON c.character_id=d.character_id WHERE d.simulation_id=? AND d.status="OPEN" AND d.closes_at_second<? ORDER BY d.closes_at_second,d.decision_id');$q->execute([$sid,$second]);
            foreach($q->fetchAll() as $d){
                $oq=$pdo->prepare('SELECT label FROM decision_options WHERE decision_id=? ORDER BY sort_order,decision_option_id');$oq->execute([$d['decision_id']]);$labels=array_map(static fn($label)=>lcfirst(trim((string)$label)),array_column($oq->fetchAll(),'label'));$last=array_pop($labels);$choiceText=$last??'act';if($labels)$choiceText=implode(', ',$labels).', or '.$choiceText;
                $personalText='You were given the chance to '.$choiceText.', but the moment passed before you could act — the situation changed.';
                // Task 2: atomic claim — only the first resolver (a choice or
                // this expiry) wins. A concurrent choice that already claimed
                // the decision makes this UPDATE a no-op and we skip the
                // history, the world log and the no-action effects.
                $upd=$pdo->prepare('UPDATE decisions SET status="NO_ACTION",resolved_text=? WHERE decision_id=? AND status="OPEN"');$upd->execute([$personalText,$d['decision_id']]);
                if($upd->rowCount()!==1)continue;
                $pdo->prepare('INSERT INTO character_history(simulation_id,character_id,event_id,event_second,history_type,title,detail_text) VALUES(?,?,?,?,"NO_ACTION",?,?)')->execute([$sid,$d['character_id'],$d['event_id'],$d['closes_at_second'],'The Opportunity Passed',$personalText]);
                self::worldLog($sid,(int)$d['closes_at_second'],'NO_ACTION',$d['character_id'],$d['name'].' let an opportunity pass','The situation changed without their intervention.',(int)$d['event_id']);
                if(class_exists('CausalService'))CausalService::applyNoActionDecision($sid,(int)$d['decision_id']);
            }
            if($ownsTransaction)$pdo->commit();
        }catch(Throwable $e){if($ownsTransaction&&$pdo->inTransaction())$pdo->rollBack();throw$e;}
    }

    private static function decisionOption(int $sid,array $d,int $decisionId,string $optionCode,array $sim): array {
        // Task 5 Phase 2: resolve from the snapshot when pinned. The live
        // query below stays as a fallback when the pinned lookup misses
        // (defensive — the option list a player sees is built from the same
        // snapshot, so a miss means stale data, not a wrong option).
        if(class_exists('SimulationContent')){
            $rows=SimulationContent::decisionOptionsForDecision($sid,$decisionId,(int)$d['character_id']);
            if($rows!==null)foreach($rows as$candidate)if((string)$candidate['option_code']===$optionCode){
                $opt=$candidate;
                if(empty($opt['_pinned_dynamic_option_id'])&&class_exists('CausalService')&&!CausalService::optionAllowed($sid,(int)$opt['template_option_id']))throw new RuntimeException('That action is no longer possible because the world has changed.');
                return$opt;
            }
        }
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
        $options=null;if(class_exists('SimulationContent'))$options=SimulationContent::decisionOptions($sid,$templateId);
        if($options===null){$o=db()->prepare('SELECT * FROM plot_decision_template_options WHERE template_id=? AND active=1 ORDER BY sort_order');$o->execute([$templateId]);$options=$o->fetchAll();}
        $filtered=[];foreach($options as$r){if(class_exists('CausalService')&&!CausalService::optionAllowed($sid,(int)$r['template_option_id']))continue;$filtered[]=$r;}
        self::resolveNpcFromOptions($sid,$decisionId,$filtered,$second);
    }

    private static function resolveNpcFromOptions(int $sid,int $decisionId,array $options,int $second): void {
        $pdo=db();$q=$pdo->prepare('SELECT d.*,c.name,c.character_id FROM decisions d JOIN characters c ON c.character_id=d.character_id WHERE d.decision_id=?');$q->execute([$decisionId]);$d=$q->fetch();if(!$d||$d['status']!=='OPEN')return;
        if(!$options){$pdo->prepare('UPDATE decisions SET status="CLOSED",resolved_text="No valid NPC action remained in the current world state." WHERE decision_id=? AND status="OPEN"')->execute([$decisionId]);return;}
        $total=max(1,array_sum(array_map(fn($r)=>max(0,(int)$r['npc_weight']),$options)));$roll=random_int(1,$total);$pick=$options[0];$cursor=0;
        foreach($options as $r){$cursor+=max(0,(int)$r['npc_weight']);if($roll<=$cursor){$pick=$r;break;}}
        $considered=[];$remaining=100;$lastIndex=count($options)-1;
        foreach($options as $i=>$r){$chance=$i===$lastIndex?$remaining:(int)round((max(0,(int)$r['npc_weight'])/$total)*100);$chance=max(0,min($remaining,$chance));$remaining-=$chance;$considered[]=$r['label'].' ['.$chance.'%]'.($r['rationale_text']?' — '.$r['rationale_text']:'');}
$outcome=self::applyOption($sid,$d,$pick,$second,'NPC');
        // Record the reasoning only when this call won the claim; a losing
        // racer leaves no history behind.
        if($outcome!==null&&$outcome['status']==='CHOSEN'&&(string)$outcome['chosen_option_code']===(string)$pick['option_code'])$pdo->prepare('INSERT INTO character_history(simulation_id,character_id,event_id,event_second,history_type,title,detail_text) VALUES(?,?,?,?,"INTERACTION",?,?)')->execute([$sid,$d['character_id'],$d['event_id'],$second,'Options Considered',implode("\n",$considered)]);
    }

    // Atomic claim (Task 2): lock the decision row and claim it only while OPEN.
    // Returns the final decision row (status + chosen_option_code +
    // resolved_text) so callers can tell an idempotent retry from a conflict.
    // Participates in an outer transaction instead of nesting one (Task 3):
    // a nested beginTransaction() is a warning-only no-op, but the nested
    // commit() would prematurely commit the caller's transaction.
    private static function applyOption(int $sid,array $d,array $opt,int $second,string $actorType): ?array {
        $pdo=db();$ownsTransaction=!$pdo->inTransaction();if($ownsTransaction)$pdo->beginTransaction();
        try{
            $claim=$pdo->prepare('SELECT status,chosen_option_code,resolved_text FROM decisions WHERE decision_id=? AND simulation_id=? FOR UPDATE');$claim->execute([$d['decision_id'],$sid]);$current=$claim->fetch();
            if(!$current||$current['status']!=='OPEN'){if($ownsTransaction&&$pdo->inTransaction())$pdo->rollBack();return $current?:null;}
            if(empty($opt['_pinned_dynamic_option_id'])&&class_exists('CausalService')&&!CausalService::optionAllowed($sid,(int)$opt['template_option_id']))throw new RuntimeException('Action invalidated by current world state.');
            $upd=$pdo->prepare('UPDATE decisions SET status="CHOSEN",chosen_option_code=?,resolved_text=? WHERE decision_id=? AND status="OPEN"');$upd->execute([$opt['option_code'],$opt['result_text'],$d['decision_id']]);
            if($upd->rowCount()!==1){if($ownsTransaction&&$pdo->inTransaction())$pdo->rollBack();return self::readDecision($sid,(int)$d['decision_id']);}
            $pdo->prepare('INSERT INTO character_history(simulation_id,character_id,event_id,event_second,history_type,title,detail_text) VALUES(?,?,?,?,"DECISION",?,?)')->execute([$sid,$d['character_id'],$d['event_id'],$second,'Decision: '.$opt['label'],$opt['result_text']]);
            $effects=null;
            if(!empty($opt['_pinned_dynamic_option_id'])&&class_exists('SimulationContent'))$effects=SimulationContent::dynamicEffects($sid,(int)$opt['_pinned_dynamic_option_id']);
            elseif(class_exists('SimulationContent'))$effects=SimulationContent::optionEffects($sid,(int)($opt['template_option_id']??0));
            if($effects===null){$q=$pdo->prepare('SELECT * FROM plot_option_effects WHERE template_option_id=? AND active=1 ORDER BY sort_order,effect_id');$q->execute([$opt['template_option_id']]);$effects=$q->fetchAll();}
            $changes=[];foreach($effects as $e){$changes[]=self::applyEffect($sid,(int)$d['character_id'],$d['event_id'],$second,$e,$actorType,(int)$d['decision_id']);}
            $cq=$pdo->prepare('SELECT name FROM characters WHERE character_id=?');$cq->execute([$d['character_id']]);$name=(string)$cq->fetchColumn();
            $changes=array_values(array_filter($changes,static fn($c)=>!preg_match('/^\s*[A-Z][A-Z0-9_]*\s*=\s*[^.!?]+\.?\s*$/u',(string)$c)));$resultText=trim((string)$opt['result_text']);$labelText=trim((string)$opt['label']);if($resultText===''||$resultText===$labelText||preg_match('/\byou\b|\byour\b|\byours\b|\byourself\b/i',$resultText))$worldText='chose to '.lcfirst(rtrim($labelText,'.')).'.';else $worldText=self::sentenceText($resultText);foreach($changes as$change){$change=self::sentenceText((string)$change);if($change!=='')$worldText.=' '.$change;}
            self::worldLog($sid,$second,$actorType.'_ACTION',(int)$d['character_id'],$name.': '.$opt['label'],$worldText,(int)$d['event_id']);
            if($ownsTransaction)$pdo->commit();
            return ['status'=>'CHOSEN','chosen_option_code'=>$opt['option_code'],'resolved_text'=>$opt['result_text']];
        }catch(Throwable $e){if($ownsTransaction&&$pdo->inTransaction())$pdo->rollBack();throw$e;}
    }

    private static function readDecision(int $sid,int $decisionId): ?array {
        $q=db()->prepare('SELECT status,chosen_option_code,resolved_text FROM decisions WHERE decision_id=? AND simulation_id=?');$q->execute([$decisionId,$sid]);$r=$q->fetch();return $r?:null;
    }

    // Apply a choice and confirm the outcome. An idempotent retry (the same
    // option already chosen, e.g. a double-click or retried POST) succeeds
    // silently; any other already-resolved state is a conflict.
    private static function applyAndConfirm(int $sid,array $d,array $opt,int $second,string $actorType): void {
        $outcome=self::applyOption($sid,$d,$opt,$second,$actorType);
        if($outcome!==null&&$outcome['status']==='CHOSEN'&&(string)$outcome['chosen_option_code']===(string)$opt['option_code'])return;
        throw new RuntimeException('That decision is already closed.');
    }

    private static function applyEffect(int $sid,int $cid,int $eventId,int $second,array $e,string $sourceType,?int $sourceId): string {
        // Universal effect targets: an effect can apply to the deciding
        // character (default), one or more specific characters
        // (comma-separated role codes in target_role_code), or the whole
        // group ('GROUP'). Global scopes (FACTOR, WORLD_STATE) have no
        // character target and apply once.
        $scope=(string)($e['effect_scope']??'');
        if($scope==='FACTOR'||$scope==='WORLD_STATE'){
            return self::applyEffectToCharacter($sid,$cid,$eventId,$second,$e,$sourceType,$sourceId);
        }
        $targets=self::resolveEffectTargets($sid,$cid,$e);
        $out=[];
        foreach($targets as $tcid){
            $r=self::applyEffectToCharacter($sid,$tcid,$eventId,$second,$e,$sourceType,$sourceId);
            if($r!=='')$out[]=$r;
        }
        return implode(' ',array_values(array_unique($out)));
    }

    // Resolve who an effect applies to. target_role_code: ''/SELF = the
    // deciding character; 'GROUP' = every character in the simulation;
    // otherwise a comma-separated list of role codes (a single role code
    // targets just that character). Falls back to the decider.
    // Resolve who an effect applies to. target_role_code: ''/SELF = the
    // deciding character; 'GROUP' = every character in the simulation;
    // otherwise a comma-separated list of role codes (a single role code
    // targets just that character). 'SELF' inside a list adds the decider
    // alongside the listed roles. Falls back to the decider.
    private static function resolveEffectTargets(int $sid,int $cid,array $e): array {
        $raw=trim((string)($e['target_role_code']??''));
        if($raw===''||strcasecmp($raw,'SELF')===0)return [$cid];
        $parts=array_map(static fn($p)=>strtoupper(trim($p)),explode(',',$raw));
        if(in_array('GROUP',$parts,true)){
            $q=db()->prepare('SELECT character_id FROM characters WHERE simulation_id=? ORDER BY character_id');
            $q->execute([$sid]);
            $ids=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
            return $ids?:[$cid];
        }
        $ids=[];
        if(in_array('SELF',$parts,true))$ids[]=$cid;
        $codes=array_values(array_unique(array_filter($parts,static fn($c)=>$c!==''&&$c!=='SELF')));
        foreach($codes as $code){
            $q=db()->prepare('SELECT c.character_id FROM characters c JOIN plot_roles pr ON pr.plot_role_id=c.plot_role_id WHERE c.simulation_id=? AND pr.role_code=?');
            $q->execute([$sid,$code]);
            $id=$q->fetchColumn();
            if($id!==false)$ids[]=(int)$id;
        }
        return array_values(array_unique($ids))?:[$cid];
    }

    private static function applyEffectToCharacter(int $sid,int $cid,int $eventId,int $second,array $e,string $sourceType,?int $sourceId): string {
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

    public static function moveRoleToLocation(int $sid,string $roleCode,string $location,int $second,?int $eventId,string $reason): void {$pdo=db();$q=$pdo->prepare('SELECT c.character_id FROM characters c JOIN plot_roles pr ON pr.plot_role_id=c.plot_role_id WHERE c.simulation_id=? AND pr.role_code=?');$q->execute([$sid,$roleCode]);$cid=$q->fetchColumn();if($cid===false)return;self::setLocation($sid,(int)$cid,$location,$second,$eventId,$reason);}
    private static function setLocation(int $sid,int $cid,string $location,int $second,?int $eventId,string $reason): void {$pdo=db();$q=$pdo->prepare('SELECT current_location FROM characters WHERE character_id=? AND simulation_id=?');$q->execute([$cid,$sid]);$old=(string)$q->fetchColumn();if($old===$location)return;$pdo->prepare('UPDATE characters SET current_location=? WHERE character_id=?')->execute([$location,$cid]);$pdo->prepare('INSERT INTO character_history(simulation_id,character_id,event_id,event_second,history_type,title,detail_text) VALUES(?,?,?,?,"LOCATION",?,?)')->execute([$sid,$cid,$eventId,$second,'Location Changed',$old.' → '.$location.' · '.$reason]);}
    private static function worldLog(int $sid,int $second,string $code,?int $actorId,string $headline,string $text,?int $cause): int {$sim=self::simulation($sid);$pdo=db();$pdo->prepare('INSERT INTO simulation_events(simulation_id,event_second,phase,event_type,actor_character_id,event_code,headline,event_text,visibility,caused_by_event_id) VALUES(?,?,?,?,?,?,?,?,"WORLD",?)')->execute([$sid,$second,$sim['current_phase'],$actorId?($code==='NO_ACTION'?'PLAYER':(str_starts_with($code,'NPC')?'NPC':'PLAYER')):'SYSTEM',$actorId,$code.'_'.$second.'_'.bin2hex(random_bytes(2)),$headline,$text,$cause]);return(int)$pdo->lastInsertId();}
    private static function simulation(int $sid): array {$q=db()->prepare('SELECT * FROM simulations WHERE simulation_id=?');$q->execute([$sid]);$r=$q->fetch();if(!$r)throw new RuntimeException('Simulation not found.');return$r;}
}
