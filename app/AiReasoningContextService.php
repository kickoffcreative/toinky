<?php
final class AiReasoningContextService
{
    public static function forDecision(int $sid,int $decisionId): array
    {
        $pdo=db();
        $q=$pdo->prepare('SELECT d.decision_id,d.character_id,d.prompt_text,c.name,c.goal_text,c.fear_text,c.current_location,c.plot_role_id,pr.role_code FROM decisions d JOIN characters c ON c.character_id=d.character_id JOIN plot_roles pr ON pr.plot_role_id=c.plot_role_id WHERE d.simulation_id=? AND d.decision_id=? LIMIT 1');
        $q->execute([$sid,$decisionId]);$d=$q->fetch();if(!$d)throw new RuntimeException('Decision not found for AI context.');
        $stats=[];$q=$pdo->prepare('SELECT stat_code,current_value FROM character_stats WHERE character_id=? ORDER BY character_stat_id');$q->execute([$d['character_id']]);foreach($q->fetchAll()as$r)$stats[$r['stat_code']]=(int)$r['current_value'];
        $knowledge=[];$q=$pdo->prepare('SELECT knowledge_key,knowledge_text,state,confidence FROM character_knowledge WHERE character_id=? AND state="KNOWN" ORDER BY learned_at_second,character_knowledge_id');$q->execute([$d['character_id']]);foreach($q->fetchAll()as$r)$knowledge[]=['key'=>$r['knowledge_key'],'text'=>$r['knowledge_text'],'confidence'=>(int)$r['confidence']];
        $world=[];try{$q=$pdo->prepare('SELECT state_key,state_value FROM simulation_state WHERE simulation_id=? ORDER BY state_key');$q->execute([$sid]);foreach($q->fetchAll()as$r){$k=(string)$r['state_key'];if(self::internalState($k))continue;$world[$k]=$r['state_value'];}}catch(Throwable$e){}
        $factors=[];$q=$pdo->prepare('SELECT factor_code,current_value FROM simulation_factors WHERE simulation_id=? ORDER BY simulation_factor_id');$q->execute([$sid]);foreach($q->fetchAll()as$r)$factors[$r['factor_code']]=(int)$r['current_value'];
        return['simulation_id'=>$sid,'decision_id'=>$decisionId,'character_id'=>(int)$d['character_id'],'role_code'=>$d['role_code'],'name'=>$d['name'],'goal'=>$d['goal_text'],'fear'=>$d['fear_text'],'location'=>$d['current_location'],'stats'=>$stats,'knowledge'=>$knowledge,'world'=>$world,'factors'=>$factors,'prompt'=>$d['prompt_text'],'purpose'=>'NPC_OPTION_SELECTION'];
    }
    private static function internalState(string$key): bool
    {
        foreach(['NO_ACTION_EFFECT_','TRANSITION_FIRED_','CONSEQUENCE_FIRED_','DYNAMIC_OPPORTUNITY_','OPPORTUNITY_FIRED_','GOAL_RULE_FIRED_','FEAR_RULE_FIRED_']as$p)if(str_starts_with($key,$p))return true;
        return false;
    }
}
