<?php
final class CharacterAutomationService
{
    public static function process(int $sid): array
    {
        $pdo=db();$summary=['chosen'=>0,'failed'=>0];
        for($pass=0;$pass<12;$pass++){
            $q=$pdo->prepare('SELECT d.decision_id,d.event_id,d.character_id FROM decisions d JOIN characters c ON c.character_id=d.character_id JOIN simulations s ON s.simulation_id=d.simulation_id WHERE d.simulation_id=? AND d.status="OPEN" AND d.opens_at_second<=s.elapsed_seconds AND c.controller_type="AI" ORDER BY d.opens_at_second,d.decision_id');
            $q->execute([$sid]);$decisions=$q->fetchAll();if(!$decisions)break;$progress=false;
            foreach($decisions as$d){
                $status=$pdo->prepare('SELECT status FROM decisions WHERE decision_id=? AND simulation_id=?');$status->execute([$d['decision_id'],$sid]);if($status->fetchColumn()!=='OPEN')continue;
                $options=self::options($sid,(int)$d['decision_id'],(int)$d['character_id']);if(!$options)continue;
                try{
                    $context=AiReasoningContextService::forDecision($sid,(int)$d['decision_id']);$context['purpose']='LIVE_CHARACTER_OPTION_SELECTION';
                    $result=StoryReasonerFactory::withFallback()->selectOption($context,$options);$selected=(string)($result['selected_action']??'');$pick=null;foreach($options as$o)if((string)$o['option_code']===$selected){$pick=$o;break;}if(!$pick)throw new RuntimeException('Reasoner returned no currently valid option.');
                    BehaviorService::chooseAutomated($sid,(int)$d['decision_id'],$selected);
                    // Record the reasoning only when this call won the claim;
                    // a losing racer leaves no history behind.
                    self::log($sid,$d,$result,$selected);$summary['chosen']++;$progress=true;
                }catch(Throwable$e){
                    // A decision resolved by someone else while the reasoner
                    // was running is benign: skip it, do not fall back, do
                    // not count it as a failure.
                    $st=$pdo->prepare('SELECT status FROM decisions WHERE decision_id=? AND simulation_id=?');$st->execute([$d['decision_id'],$sid]);
                    if($st->fetchColumn()!=='OPEN')continue;
                    try{
                        $pick=self::weightedPick($options);
                        BehaviorService::chooseAutomated($sid,(int)$d['decision_id'],(string)$pick['option_code']);
                        // Record the fallback only once it actually resolved
                        // the decision.
                        self::logFailure($sid,$d,$e->getMessage(),(string)$pick['option_code']);$summary['chosen']++;$progress=true;
                    }catch(Throwable $e2){
                        // The weighted fallback also failed and the decision
                        // is still OPEN: a real failure, not a race. Log it
                        // visibly instead of swallowing it.
                        error_log('Toinky AI automation failed for simulation '.$sid.' decision '.$d['decision_id'].': '.$e2->getMessage());
                        $summary['failed']++;
                    }
                }
            }
            if(class_exists('DynamicOpportunityService'))DynamicOpportunityService::reconcile($sid);
            if(!$progress)break;
        }
        return$summary;
    }

    private static function options(int $sid,int $decisionId,int $characterId): array
    {
        // Task 5 Phase 2: a pinned simulation offers the AI the snapshot's
        // options (template or dynamic); the live query stays for legacy sims.
        if(class_exists('SimulationContent')){$rows=SimulationContent::decisionOptionsForDecision($sid,$decisionId,$characterId);if($rows!==null){$out=[];foreach($rows as$o){if(empty($o['_pinned_dynamic_option_id'])&&class_exists('CausalService')&&!CausalService::optionAllowed($sid,(int)$o['template_option_id']))continue;$out[]=$o;}return$out;}}
        $pdo=db();$q=$pdo->prepare('SELECT pdto.* FROM decision_options do JOIN plot_decision_template_options pdto ON pdto.option_code=do.option_code AND pdto.active=1 JOIN plot_decision_templates pdt ON pdt.template_id=pdto.template_id JOIN characters c ON c.character_id=? JOIN plot_roles pr ON pr.plot_role_id=c.plot_role_id JOIN simulations s ON s.simulation_id=? WHERE do.decision_id=? AND pdt.plot_id=s.plot_id AND pdt.role_code=pr.role_code AND pdt.event_code=(SELECT se.event_code FROM decisions dx JOIN simulation_events se ON se.event_id=dx.event_id WHERE dx.decision_id=?) ORDER BY pdto.sort_order,pdto.template_option_id');
        $q->execute([$characterId,$sid,$decisionId,$decisionId]);$out=[];foreach($q->fetchAll()as$o){if(class_exists('CausalService')&&!CausalService::optionAllowed($sid,(int)$o['template_option_id']))continue;$out[]=$o;}return$out;
    }

    private static function weightedPick(array$options): array
    {
        $weights=array_map(fn($r)=>max(0,(int)($r['npc_weight']??0)),$options);$total=array_sum($weights);if($total<=0)return$options[0];$roll=random_int(1,$total);$cursor=0;foreach($options as$i=>$r){$cursor+=$weights[$i];if($roll<=$cursor)return$r;}return$options[0];
    }

    private static function log(int $sid,array$d,array$result,string$selected): void
    {
        $detail='Provider: '.($result['provider']??'unknown').' | Selected: '.$selected.' | Confidence: '.($result['confidence']??'n/a').' | Reason: '.($result['reason']??'');if(!empty($result['fallback_used']))$detail.=' | Fallback: '.($result['fallback_error']??'yes');
        db()->prepare('INSERT INTO character_history(simulation_id,character_id,event_id,event_second,history_type,title,detail_text) SELECT ?,?,?,elapsed_seconds,"INTERACTION","AI Options Considered",? FROM simulations WHERE simulation_id=?')->execute([$sid,$d['character_id'],$d['event_id'],$detail,$sid]);
    }

    private static function logFailure(int$sid,array$d,string$error,string$fallback): void
    {
        db()->prepare('INSERT INTO character_history(simulation_id,character_id,event_id,event_second,history_type,title,detail_text) SELECT ?,?,?,elapsed_seconds,"INTERACTION","AI Reasoner Fallback",? FROM simulations WHERE simulation_id=?')->execute([$sid,$d['character_id'],$d['event_id'],$error.' | Weighted fallback: '.$fallback,$sid]);
    }
}
