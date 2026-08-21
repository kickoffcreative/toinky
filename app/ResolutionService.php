<?php
final class ResolutionService
{
    public static function refresh(int $sid): void
    {
        $pdo=db();$q=$pdo->prepare('SELECT s.plot_id,se.event_id FROM simulations s JOIN simulation_events se ON se.simulation_id=s.simulation_id AND se.event_code="FINAL_STATE" WHERE s.simulation_id=? LIMIT 1');$q->execute([$sid]);$r=$q->fetch();if(!$r)return;
        $sections=$pdo->prepare('SELECT DISTINCT section_code FROM plot_resolution_fragments WHERE plot_id=? AND active=1 ORDER BY section_code');$sections->execute([$r['plot_id']]);$parts=[];
        foreach($sections->fetchAll() as $s){$fragment=self::pickFragment($sid,(int)$r['plot_id'],$s['section_code']);if($fragment!=='')$parts[]=$fragment;}
        $f=$pdo->prepare('SELECT label,current_value FROM simulation_factors WHERE simulation_id=? ORDER BY simulation_factor_id');$f->execute([$sid]);$summary=[];foreach($f->fetchAll() as $row)$summary[]=$row['label'].' '.$row['current_value'];
        if($summary)$parts[]='Final factors: '.implode(', ',$summary).'.';
        $pdo->prepare('UPDATE simulation_events SET headline="What Actually Remains",event_text=? WHERE event_id=?')->execute([implode(' ',$parts),$r['event_id']]);
    }

    private static function pickFragment(int $sid,int $plotId,string $section): string
    {
        $pdo=db();$q=$pdo->prepare('SELECT * FROM plot_resolution_fragments WHERE plot_id=? AND section_code=? AND active=1 ORDER BY priority,resolution_fragment_id');$q->execute([$plotId,$section]);
        foreach($q->fetchAll() as $f){
            $c=$pdo->prepare('SELECT state_key,operator,compare_value FROM plot_resolution_conditions WHERE resolution_fragment_id=? ORDER BY sort_order,resolution_condition_id');$c->execute([$f['resolution_fragment_id']]);$ok=true;
            foreach($c->fetchAll() as $cond){if(!GenericStateService::conditionPasses($sid,$cond['state_key'],$cond['operator'],$cond['compare_value'])){$ok=false;break;}}
            if($ok)return self::render($sid,(string)$f['fragment_text']);
        }
        return '';
    }

    private static function render(int $sid,string $text): string
    {
        return preg_replace_callback('/\{(state|factor):([^}]+)\}/',function($m)use($sid){
            if($m[1]==='state'){$r=GenericStateService::getByKey($sid,$m[2]);return $r['exists']?(string)$r['value']:'';}
            $q=db()->prepare('SELECT current_value FROM simulation_factors WHERE simulation_id=? AND factor_code=?');$q->execute([$sid,$m[2]]);$v=$q->fetchColumn();return $v===false?'':(string)$v;
        },$text)??$text;
    }
}
