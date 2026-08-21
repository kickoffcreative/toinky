<?php
final class StateSyncService
{
    public static function reconcile(int $sid): void
    {
        if($sid<=0)return;
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
