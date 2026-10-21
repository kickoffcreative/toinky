<?php
final class GenericStateService
{
    public static function getByKey(int $sid,string $key): array
    {
        $pdo=db();
        $meta=self::metadataForKey($sid,$key);
        if($meta){
            $q=$pdo->prepare('SELECT state_value FROM simulation_entity_state WHERE simulation_id=? AND entity_code=? AND property_code=?');
            $q->execute([$sid,$meta['entity_code'],$meta['property_code']]);$v=$q->fetchColumn();
            if($v!==false)return ['exists'=>true,'value'=>(string)$v,'meta'=>$meta];
            if($meta['initial_value']!==null)return ['exists'=>true,'value'=>(string)$meta['initial_value'],'meta'=>$meta];
            return ['exists'=>false,'value'=>null,'meta'=>$meta];
        }
        $q=$pdo->prepare('SELECT state_value FROM simulation_state WHERE simulation_id=? AND state_key=?');$q->execute([$sid,$key]);$v=$q->fetchColumn();
        return $v===false?['exists'=>false,'value'=>null,'meta'=>null]:['exists'=>true,'value'=>(string)$v,'meta'=>null];
    }

    public static function setByKey(int $sid,string $key,string $value,int $second,string $detail=''): string
    {
        $pdo=db();$meta=self::metadataForKey($sid,$key);
        if(!$meta){
            $q=$pdo->prepare('SELECT state_value FROM simulation_state WHERE simulation_id=? AND state_key=?');$q->execute([$sid,$key]);$old=$q->fetchColumn();
            if($old!==false && (string)$old===$value)return '';
            $pdo->prepare('INSERT INTO simulation_state(simulation_id,state_key,state_value,updated_at_second) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE state_value=VALUES(state_value),updated_at_second=VALUES(updated_at_second)')->execute([$sid,$key,$value,$second]);
            return $detail?:($key.'='.$value.'.');
        }

        $current=self::getByKey($sid,$key);$old=$current['exists']?(string)$current['value']:null;
        if($old===$value)return '';
        if(!self::valueAllowed($meta,$value))return '';
        if(!self::transitionAllowed((int)$meta['property_id'],$old,$value))return '';

        $pdo->prepare('INSERT INTO simulation_entity_state(simulation_id,entity_code,property_code,state_value,updated_at_second) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE state_value=VALUES(state_value),updated_at_second=VALUES(updated_at_second)')->execute([$sid,$meta['entity_code'],$meta['property_code'],$value,$second]);
        // Compatibility mirror while older plot rules still use state_key.
        $pdo->prepare('INSERT INTO simulation_state(simulation_id,state_key,state_value,updated_at_second) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE state_value=VALUES(state_value),updated_at_second=VALUES(updated_at_second)')->execute([$sid,$key,$value,$second]);
        return $detail?:($meta['label'].' '.($old??'UNSET').'→'.$value.'.');
    }

    public static function conditionPasses(int $sid,string $key,string $operator,?string $compare): bool
    {
        if(str_starts_with($key,'FACTOR:')){
            $code=substr($key,7);$q=db()->prepare('SELECT current_value FROM simulation_factors WHERE simulation_id=? AND factor_code=?');$q->execute([$sid,$code]);$v=$q->fetchColumn();$exists=$v!==false;$value=$exists?(string)$v:null;
        }else{
            $r=self::getByKey($sid,$key);$exists=$r['exists'];$value=$r['value'];
        }
        $list=array_values(array_filter(array_map('trim',explode(',',(string)$compare)),fn($v)=>$v!==''));
        $num=is_numeric($value)&&is_numeric($compare);
        return match($operator){
            'EQ'=>$exists && (string)$value===(string)$compare,
            'NEQ'=>!$exists || (string)$value!==(string)$compare,
            'IN'=>$exists && in_array((string)$value,$list,true),
            'NOT_IN'=>!$exists || !in_array((string)$value,$list,true),
            'EXISTS'=>$exists,
            'NOT_EXISTS'=>!$exists,
            'LT'=>$exists && $num && (float)$value<(float)$compare,
            'LTE'=>$exists && $num && (float)$value<=(float)$compare,
            'GT'=>$exists && $num && (float)$value>(float)$compare,
            'GTE'=>$exists && $num && (float)$value>=(float)$compare,
            // Unknown or malformed operators must never make a restricted action legal.
            default=>false,
        };
    }

    public static function initializePlotState(int $sid): void
    {
        $pdo=db();$q=$pdo->prepare('SELECT pe.entity_code,p.property_code,p.initial_value FROM simulations s JOIN plot_entities pe ON pe.plot_id=s.plot_id JOIN plot_entity_properties p ON p.plot_entity_id=pe.plot_entity_id WHERE s.simulation_id=? AND p.initial_value IS NOT NULL');$q->execute([$sid]);
        foreach($q->fetchAll() as $r)$pdo->prepare('INSERT IGNORE INTO simulation_entity_state(simulation_id,entity_code,property_code,state_value,updated_at_second) VALUES(?,?,?,?,0)')->execute([$sid,$r['entity_code'],$r['property_code'],$r['initial_value']]);
        $q=$pdo->prepare('SELECT d.target_code,d.default_text_value FROM simulations s JOIN plot_state_definitions d ON d.plot_id=s.plot_id AND d.effect_scope="WORLD_STATE" AND d.active=1 WHERE s.simulation_id=? AND d.default_text_value IS NOT NULL AND d.default_text_value<>"" AND NOT EXISTS(SELECT 1 FROM plot_state_aliases a WHERE a.plot_id=s.plot_id AND a.state_key=d.target_code)');$q->execute([$sid]);
        foreach($q->fetchAll() as $r)$pdo->prepare('INSERT IGNORE INTO simulation_state(simulation_id,state_key,state_value,updated_at_second) VALUES(?,?,?,0)')->execute([$sid,$r['target_code'],$r['default_text_value']]);
    }

    private static function metadataForKey(int $sid,string $key): ?array
    {
        $pdo=db();$q=$pdo->prepare('SELECT a.entity_code,a.property_code,p.property_id,p.label,p.value_type,p.initial_value,p.allowed_values,p.min_value,p.max_value FROM simulations s JOIN plot_state_aliases a ON a.plot_id=s.plot_id AND a.state_key=? JOIN plot_entities e ON e.plot_id=s.plot_id AND e.entity_code=a.entity_code JOIN plot_entity_properties p ON p.plot_entity_id=e.plot_entity_id AND p.property_code=a.property_code WHERE s.simulation_id=? LIMIT 1');$q->execute([$key,$sid]);$r=$q->fetch();if($r)return$r;
        if(str_contains($key,'.')){
            [$entity,$property]=explode('.',$key,2);$q=$pdo->prepare('SELECT e.entity_code,p.property_code,p.property_id,p.label,p.value_type,p.initial_value,p.allowed_values,p.min_value,p.max_value FROM simulations s JOIN plot_entities e ON e.plot_id=s.plot_id AND e.entity_code=? JOIN plot_entity_properties p ON p.plot_entity_id=e.plot_entity_id AND p.property_code=? WHERE s.simulation_id=? LIMIT 1');$q->execute([$entity,$property,$sid]);$r=$q->fetch();if($r)return$r;
        }
        return null;
    }

    private static function valueAllowed(array $meta,string $value): bool
    {
        if($meta['value_type']==='NUMBER'){
            if(!is_numeric($value))return false;$n=(float)$value;
            if($meta['min_value']!==null && $n<(float)$meta['min_value'])return false;
            if($meta['max_value']!==null && $n>(float)$meta['max_value'])return false;
        }
        if($meta['value_type']==='BOOLEAN' && !in_array(strtoupper($value),['YES','NO','TRUE','FALSE','1','0'],true))return false;
        $allowed=array_values(array_filter(array_map('trim',explode(',',(string)$meta['allowed_values'])),fn($v)=>$v!==''));
        return !$allowed || in_array($value,$allowed,true);
    }

    private static function transitionAllowed(int $propertyId,?string $old,string $new): bool
    {
        $pdo=db();$q=$pdo->prepare('SELECT COUNT(*) FROM plot_property_transitions WHERE property_id=? AND active=1');$q->execute([$propertyId]);$count=(int)$q->fetchColumn();
        if($count===0)return true;
        $q=$pdo->prepare('SELECT 1 FROM plot_property_transitions WHERE property_id=? AND active=1 AND ((from_value IS NULL AND ? IS NULL) OR from_value=?) AND to_value=? LIMIT 1');$q->execute([$propertyId,$old,$old,$new]);return(bool)$q->fetchColumn();
    }
}
