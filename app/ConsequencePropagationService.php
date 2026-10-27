<?php
// Task 5 Phase 3: plot_consequence_rules (+ conditions/effects) stay live
// deliberately — not in the workspace snapshot, no studio editor (no PHP
// code writes them), no draft-edit vector. If they ever gain a studio
// editor, they must move into the snapshot first.
final class ConsequencePropagationService
{
    public static function evaluate(int $sid,?int $second=null): void
    {
        if($sid<=0)return;
        $pdo=db();GenericStateService::initializePlotState($sid);
        $q=$pdo->prepare('SELECT plot_id,elapsed_seconds,current_phase FROM simulations WHERE simulation_id=?');$q->execute([$sid]);$sim=$q->fetch();if(!$sim)return;
        $second=$second??(int)$sim['elapsed_seconds'];

        for($pass=0;$pass<20;$pass++){
            $changed=false;
            $r=$pdo->prepare('SELECT * FROM plot_consequence_rules WHERE plot_id=? AND active=1 ORDER BY priority,consequence_rule_id');$r->execute([(int)$sim['plot_id']]);
            foreach($r->fetchAll() as $rule){
                $marker='CONSEQUENCE_FIRED_'.$rule['consequence_rule_id'];
                if((int)$rule['once_only']===1 && GenericStateService::getByKey($sid,$marker)['exists'])continue;
                if(!self::conditionsPass($sid,(int)$rule['consequence_rule_id'],(string)$sim['current_phase']))continue;
                $changes=self::applyEffects($sid,$rule,$second);
                GenericStateService::setByKey($sid,$marker,'YES',$second);
                if($changes){self::logWorld($sid,$rule,$second,$changes);$changed=true;}
            }
            if(!$changed)break;
            $q->execute([$sid]);$sim=$q->fetch();if(!$sim)break;
        }
    }

    private static function conditionsPass(int $sid,int $ruleId,string $phase): bool
    {
        $q=db()->prepare('SELECT * FROM plot_consequence_conditions WHERE consequence_rule_id=? ORDER BY sort_order,consequence_condition_id');$q->execute([$ruleId]);
        foreach($q->fetchAll() as $c){
            $key=(string)$c['state_key'];
            if($key==='STORY_PHASE'){
                $value=$phase;$compare=(string)$c['compare_value'];$list=array_values(array_filter(array_map('trim',explode(',',$compare)),fn($v)=>$v!==''));
                $ok=match($c['operator']){'EQ'=>$value===$compare,'NEQ'=>$value!==$compare,'IN'=>in_array($value,$list,true),'NOT_IN'=>!in_array($value,$list,true),default=>false};
            } else $ok=GenericStateService::conditionPasses($sid,$key,(string)$c['operator'],$c['compare_value']);
            if(!$ok)return false;
        }
        return true;
    }

    private static function applyEffects(int $sid,array $rule,int $second): array
    {
        $pdo=db();$q=$pdo->prepare('SELECT * FROM plot_consequence_effects WHERE consequence_rule_id=? ORDER BY sort_order,consequence_effect_id');$q->execute([$rule['consequence_rule_id']]);$changes=[];
        foreach($q->fetchAll() as $e){
            $scope=(string)$e['effect_scope'];$detail=(string)($e['detail_text']??'');
            if($scope==='WORLD_STATE'){$c=GenericStateService::setByKey($sid,(string)$e['target_code'],(string)($e['set_value']??''),$second,$detail);if($c!=='')$changes[]=$c;continue;}
            if($scope==='FACTOR'){
                $f=$pdo->prepare('SELECT current_value,min_value,max_value,label FROM simulation_factors WHERE simulation_id=? AND factor_code=? FOR UPDATE');$f->execute([$sid,$e['target_code']]);$row=$f->fetch();if(!$row)continue;
                $old=(int)$row['current_value'];$new=max((int)$row['min_value'],min((int)$row['max_value'],$old+(int)$e['delta_value']));if($old===$new)continue;
                $pdo->prepare('UPDATE simulation_factors SET current_value=? WHERE simulation_id=? AND factor_code=?')->execute([$new,$sid,$e['target_code']]);
                $pdo->prepare('INSERT INTO simulation_factor_history(simulation_id,factor_code,old_value,new_value,event_second,source_type,source_id,detail_text) VALUES(?,?,?,?,?,"CONSEQUENCE",?,?)')->execute([$sid,$e['target_code'],$old,$new,$second,$e['consequence_effect_id'],$detail?:null]);
                $changes[]=$row['label'].' '.$old.'→'.$new.'.';continue;
            }
            $cid=self::characterForRole($sid,$e['target_role_code']??null);if(!$cid)continue;
            if($scope==='LOCATION'){
                $x=$pdo->prepare('SELECT current_location FROM characters WHERE character_id=?');$x->execute([$cid]);$old=(string)$x->fetchColumn();$new=(string)$e['set_value'];if($old===$new)continue;
                $pdo->prepare('UPDATE characters SET current_location=? WHERE character_id=?')->execute([$new,$cid]);$pdo->prepare('INSERT INTO character_history(simulation_id,character_id,event_second,history_type,title,detail_text) VALUES(?,?,?,"LOCATION","Location Changed",?)')->execute([$sid,$cid,$second,$old.' → '.$new.($detail?' · '.$detail:'')]);$changes[]='Location '.$old.'→'.$new.'.';continue;
            }
            if($scope==='STAT'){
                $x=$pdo->prepare('SELECT current_value,label FROM character_stats WHERE character_id=? AND stat_code=?');$x->execute([$cid,$e['target_code']]);$row=$x->fetch();if(!$row)continue;$old=(int)$row['current_value'];$new=max(0,min(100,$old+(int)$e['delta_value']));if($old===$new)continue;
                $pdo->prepare('UPDATE character_stats SET current_value=? WHERE character_id=? AND stat_code=?')->execute([$new,$cid,$e['target_code']]);$changes[]=$row['label'].' '.$old.'→'.$new.'.';continue;
            }
            if($scope==='GOAL'||$scope==='FEAR'){
                $col=$scope==='GOAL'?'goal_text':'fear_text';$x=$pdo->prepare('SELECT '.$col.' FROM characters WHERE character_id=?');$x->execute([$cid]);$old=(string)$x->fetchColumn();$new=(string)$e['set_value'];if($new===''||$new===$old)continue;
                $pdo->prepare('UPDATE characters SET '.$col.'=? WHERE character_id=?')->execute([$new,$cid]);$pdo->prepare('INSERT INTO character_state_history(simulation_id,character_id,event_second,state_type,old_value,new_value,reason_text) VALUES(?,?,?,?,?,?,?)')->execute([$sid,$cid,$second,$scope,$old,$new,$detail?:null]);$changes[]=ucfirst(strtolower($scope)).' changed.';
            }
        }
        return $changes;
    }

    private static function characterForRole(int $sid,?string $role): ?int
    {
        if(!$role)return null;$q=db()->prepare('SELECT c.character_id FROM characters c JOIN plot_roles pr ON pr.plot_role_id=c.plot_role_id WHERE c.simulation_id=? AND pr.role_code=? LIMIT 1');$q->execute([$sid,$role]);$v=$q->fetchColumn();return$v===false?null:(int)$v;
    }

    private static function logWorld(int $sid,array $rule,int $second,array $changes): void
    {
        $pdo=db();$q=$pdo->prepare('SELECT current_phase FROM simulations WHERE simulation_id=?');$q->execute([$sid]);$phase=(string)$q->fetchColumn();
        $pdo->prepare('INSERT INTO simulation_events(simulation_id,event_second,phase,event_type,event_code,headline,event_text,visibility) VALUES(?,?,?,?,?,?,?,"WORLD")')->execute([$sid,$second,$phase,'SYSTEM','CONSEQUENCE_'.$rule['rule_code'].'_'.$second,$rule['label'],implode(' ',$changes)]);
    }
}
