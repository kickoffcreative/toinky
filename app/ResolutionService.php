<?php
final class ResolutionService
{
    public static function refresh(int $sid): void
    {
        $pdo=db();$q=$pdo->prepare('SELECT s.plot_id,se.event_id,p.resolution_headline,p.include_factor_summary FROM simulations s JOIN plots p ON p.plot_id=s.plot_id JOIN simulation_events se ON se.simulation_id=s.simulation_id AND se.event_code="FINAL_STATE" WHERE s.simulation_id=? LIMIT 1');$q->execute([$sid]);$r=$q->fetch();if(!$r)return;
        // Task 5 Phase 2: a pinned simulation renders its ending headline
        // from the snapshot's plot row, not the live draft.
        // A present-but-empty snapshot value wins: the engine default applies,
        // not the live draft. Only a missing key (a snapshot predating the
        // column) falls back to live.
        if(class_exists('SimulationContent')){$plot=SimulationContent::plotRow($sid);if($plot!==null){if(array_key_exists('resolution_headline',$plot))$r['resolution_headline']=$plot['resolution_headline'];if(array_key_exists('include_factor_summary',$plot))$r['include_factor_summary']=$plot['include_factor_summary'];}}
        $sections=self::resolutionSections($sid,(int)$r['plot_id']);$parts=[];
        foreach($sections as $s){$fragment=self::pickFragment($sid,(int)$r['plot_id'],$s['section_code']);if($fragment!=='')$parts[]=$fragment;}
        $f=$pdo->prepare('SELECT label,current_value FROM simulation_factors WHERE simulation_id=? ORDER BY simulation_factor_id');$f->execute([$sid]);$summary=[];foreach($f->fetchAll() as $row)$summary[]=$row['label'].' '.$row['current_value'];
        if($summary&&(int)$r['include_factor_summary']===1)$parts[]='Final factors: '.implode(', ',$summary).'.';
        $headline=trim((string)($r['resolution_headline']??''))?:'What Actually Remains';
        $pdo->prepare('UPDATE simulation_events SET headline=?,event_text=? WHERE event_id=?')->execute([$headline,implode(' ',$parts),$r['event_id']]);
    }

    // Task 5 Phase 2: ending sections from the snapshot when pinned.
    private static function resolutionSections(int $sid,int $plotId): array {
        if(class_exists('SimulationContent')){$pinned=SimulationContent::resolutionSections($sid);if($pinned!==null)return$pinned;}
        $sections=db()->prepare('SELECT section_code,MIN(section_order) section_order FROM plot_resolution_fragments WHERE plot_id=? AND active=1 GROUP BY section_code ORDER BY section_order,section_code');$sections->execute([$plotId]);return$sections->fetchAll();
    }

    private static function pickFragmentRow(int $sid,int $plotId,string $section): ?array
    {
        $pdo=db();$fragments=null;if(class_exists('SimulationContent'))$fragments=SimulationContent::resolutionFragments($sid,$section);
        if($fragments===null){$q=$pdo->prepare('SELECT * FROM plot_resolution_fragments WHERE plot_id=? AND section_code=? AND active=1 ORDER BY priority,resolution_fragment_id');$q->execute([$plotId,$section]);$fragments=$q->fetchAll();}
        foreach($fragments as $f){
            $conds=null;if(class_exists('SimulationContent'))$conds=SimulationContent::resolutionConditions($sid,(int)$f['resolution_fragment_id']);
            if($conds===null){$c=$pdo->prepare('SELECT state_key,operator,compare_value FROM plot_resolution_conditions WHERE resolution_fragment_id=? AND active=1 ORDER BY sort_order,resolution_condition_id');$c->execute([$f['resolution_fragment_id']]);$conds=$c->fetchAll();}
            $ok=true;
            foreach($conds as $cond){if(!GenericStateService::conditionPasses($sid,$cond['state_key'],$cond['operator'],$cond['compare_value'])){$ok=false;break;}}
            if($ok)return $f;
        }
        return null;
    }

    private static function pickFragment(int $sid,int $plotId,string $section): string
    {
        $f=self::pickFragmentRow($sid,$plotId,$section);return$f?self::render($sid,(string)$f['fragment_text']):'';
    }

    public static function fragmentImages(int $sid): array
    {
        $pdo=db();$q=$pdo->prepare('SELECT plot_id FROM simulations WHERE simulation_id=?');$q->execute([$sid]);$plotId=(int)$q->fetchColumn();if($plotId<1)return[];
        $sections=self::resolutionSections($sid,$plotId);$ids=[];$order=[];
        foreach($sections as $i=>$s){$f=self::pickFragmentRow($sid,$plotId,$s['section_code']);if($f){$ids[]=(int)$f['resolution_fragment_id'];$order[(int)$f['resolution_fragment_id']]=$i;}}
        if(!$ids)return[];
        $rows=null;if(class_exists('SimulationContent'))$rows=SimulationContent::mediaForFragments($sid,$ids);
        if($rows===null){$in=implode(',',array_fill(0,count($ids),'?'));$q=$pdo->prepare('SELECT * FROM plot_media_assets WHERE resolution_fragment_id IN ('.$in.') AND active=1 ORDER BY sort_order,media_asset_id');$q->execute($ids);$rows=$q->fetchAll();}
        usort($rows,static function($a,$b)use($order){$oa=$order[(int)$a['resolution_fragment_id']]??99;$ob=$order[(int)$b['resolution_fragment_id']]??99;return($oa<=>$ob)?:((int)$a['sort_order']<=>(int)$b['sort_order'])?:((int)$a['media_asset_id']<=>(int)$b['media_asset_id']);});
        return$rows;
    }

    public static function fragments(int $sid): array
    {
        $pdo=db();$q=$pdo->prepare('SELECT plot_id FROM simulations WHERE simulation_id=?');$q->execute([$sid]);$plotId=(int)$q->fetchColumn();if($plotId<1)return[];
        $images=[];foreach(self::fragmentImages($sid) as$img)$images[(int)$img['resolution_fragment_id']][]=$img;
        $sections=self::resolutionSections($sid,$plotId);$out=[];
        foreach($sections as$s){$f=self::pickFragmentRow($sid,$plotId,(string)$s['section_code']);if(!$f)continue;$fid=(int)$f['resolution_fragment_id'];$out[]=['section_code'=>(string)$s['section_code'],'section_label'=>ucwords(strtolower(str_replace('_',' ',(string)$s['section_code']))),'text'=>self::render($sid,(string)$f['fragment_text']),'images'=>$images[$fid]??[]];}
        return$out;
    }

    private static function render(int $sid,string $text): string
    {
        return preg_replace_callback('/\{(state|factor):([^}]+)\}/',function($m)use($sid){
            if($m[1]==='state'){$r=GenericStateService::getByKey($sid,$m[2]);return $r['exists']?(string)$r['value']:'';}
            $q=db()->prepare('SELECT current_value FROM simulation_factors WHERE simulation_id=? AND factor_code=?');$q->execute([$sid,$m[2]]);$v=$q->fetchColumn();return $v===false?'':(string)$v;
        },$text)??$text;
    }
}
