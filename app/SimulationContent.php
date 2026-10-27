<?php
// Task 5 Phase 2: pinned content reader. A simulation created from a
// published version reads its story content from that version's snapshot
// (plot_versions.snapshot_json) instead of the live authoring tables, so a
// later draft edit cannot change what a running game does next. Legacy
// simulations (plot_version_id NULL) get null from every accessor and keep
// using the live queries untouched. This class never writes to the DB.
//
// Snapshot shape (written by StoryAuthoringService::submit()):
//   ['plot' => <full plots row>, 'content' => <workspace() arrays>]
// Workspace rows are PDO rows as encoded at submit time, so they carry the
// same keys and value shapes as the live queries (strings, 'active' flags,
// sort orders). Accessors filter them in place and preserve the workspace
// ordering, which matches the live ORDER BY clauses.
//
// Task 5 Phase 3 fail-closed rule: when a simulation IS pinned, a snapshot
// that loads but lacks the requested content key throws (see content() /
// versionContent()) instead of silently reading the live draft. Legacy
// simulations (plot_version_id NULL) still get null from every accessor
// and keep using the live queries untouched. A snapshot that cannot load
// at all (missing version row, bad decode, wrong plot) still returns null
// — a damaged version row is an ops fault, and the live fallback keeps
// in-flight games alive while drift logging flags it.
final class SimulationContent
{
    private static array $cache=[];
    private static array $versionCache=[];

    public static function forSimulation(int $sid): ?array
    {
        if(array_key_exists($sid,self::$cache))return self::$cache[$sid];
        $snap=null;
        try{
            $q=db()->prepare('SELECT plot_id,plot_version_id FROM simulations WHERE simulation_id=?');$q->execute([$sid]);$sim=$q->fetch();
            if($sim&&!empty($sim['plot_version_id']))$snap=self::loadVersion((int)$sim['plot_version_id'],(int)$sim['plot_id']);
        }catch(Throwable $e){error_log('Toinky SimulationContent: could not load pinned content for simulation '.$sid.': '.$e->getMessage());}
        self::$cache[$sid]=$snap;return$snap;
    }

    public static function forVersion(int $versionId): ?array
    {
        if(array_key_exists($versionId,self::$versionCache))return self::$versionCache[$versionId];
        $snap=self::loadVersion($versionId,null);self::$versionCache[$versionId]=$snap;return$snap;
    }

    private static function loadVersion(int $versionId,?int $plotId): ?array
    {
        try{
            $q=db()->prepare('SELECT plot_id,snapshot_json FROM plot_versions WHERE plot_version_id=?');$q->execute([$versionId]);$row=$q->fetch();
            if(!$row)return null;
            if($plotId!==null&&(int)$row['plot_id']!==$plotId){error_log('Toinky SimulationContent: version '.$versionId.' does not belong to plot '.$plotId.'.');return null;}
            $snap=json_decode((string)$row['snapshot_json'],true);
            if(!is_array($snap)||!is_array($snap['content']??null))return null;
            return $snap;
        }catch(Throwable $e){error_log('Toinky SimulationContent: could not load version '.$versionId.': '.$e->getMessage());return null;}
    }

    public static function plotRow(int $sid): ?array
    {
        // Task 5 Phase 3: a pinned simulation whose snapshot has no plot
        // row fails closed, like a missing content key.
        $snap=self::forSimulation($sid);if($snap===null)return null;
        if(!is_array($snap['plot']??null))throw new RuntimeException('Pinned simulation '.$sid.' is missing snapshot content "plot". The pinned version predates this content section — re-submit the story to re-pin it.');
        return $snap['plot'];
    }

    public static function plotValue(int $sid,string $field): mixed
    {
        $plot=self::plotRow($sid);return $plot[$field]??null;
    }

    // Raw workspace rows for one key, or null when the simulation is
    // unpinned (caller falls back live). Task 5 Phase 3: when the
    // simulation is pinned and the snapshot lacks the key, throw instead
    // of reading the live draft — the pinned version predates this
    // content section, so the fix is to re-submit the story, not to mix
    // in current draft content.
    private static function content(int $sid,string $key): ?array
    {
        $snap=self::forSimulation($sid);if($snap===null)return null;
        if(!is_array($snap['content'][$key]??null))throw new RuntimeException('Pinned simulation '.$sid.' is missing snapshot content "'.$key.'". The pinned version predates this content section — re-submit the story to re-pin it.');
        return $snap['content'][$key];
    }

    private static function versionContent(int $versionId,string $key): ?array
    {
        $snap=self::forVersion($versionId);if($snap===null)return null;
        if(!is_array($snap['content'][$key]??null))throw new RuntimeException('Pinned version '.$versionId.' is missing snapshot content "'.$key.'". The version predates this content section — re-submit the story to re-pin it.');
        return $snap['content'][$key];
    }

    // (a) World events. Workspace order is trigger_percent, sort_order,
    // plot_world_event_id — the same order generateDueEvents uses.
    public static function worldEvents(int $sid): ?array
    {
        $rows=self::content($sid,'events');if($rows===null)return null;
        return array_values(array_filter($rows,static fn($r)=>(int)($r['active']??1)===1));
    }

    public static function worldEventEffects(int $sid,string $eventCode): ?array
    {
        $rows=self::content($sid,'event_effects');if($rows===null)return null;
        return array_values(array_filter($rows,static fn($r)=>(string)$r['event_code']===$eventCode&&(int)($r['active']??1)===1));
    }

    // Perceptions for one event. The table has no active flag, so no
    // filtering beyond the event code. A pinned simulation reads its
    // snapshot rows; the engine resolves the cast by role code.
    public static function perceptions(int $sid,string $eventCode): ?array
    {
        $rows=self::content($sid,'perceptions');if($rows===null)return null;
        return array_values(array_filter($rows,static fn($r)=>(string)$r['event_code']===$eventCode));
    }

    public static function worldEventEffectConditions(int $sid,int $effectId): ?array
    {
        $rows=self::content($sid,'event_effect_conditions');if($rows===null)return null;
        return array_values(array_filter($rows,static fn($r)=>(int)$r['world_event_effect_id']===$effectId));
    }

    // (b) Decision templates, options, effects, conditions.
    public static function decisionTemplates(int $sid,string $eventCode,bool $activeOnly=true): ?array
    {
        $rows=self::content($sid,'decisions');if($rows===null)return null;
        return array_values(array_filter($rows,static fn($r)=>(string)$r['event_code']===$eventCode&&(!$activeOnly||(int)($r['active']??1)===1)));
    }

    public static function decisionOptions(int $sid,int $templateId): ?array
    {
        $rows=self::content($sid,'options');if($rows===null)return null;
        return array_values(array_filter($rows,static fn($r)=>(int)$r['template_id']===$templateId&&(int)($r['active']??1)===1));
    }

    public static function optionEffects(int $sid,int $templateOptionId): ?array
    {
        $rows=self::content($sid,'effects');if($rows===null)return null;
        return array_values(array_filter($rows,static fn($r)=>(int)$r['template_option_id']===$templateOptionId&&(int)($r['active']??1)===1));
    }

    public static function optionConditions(int $sid,int $templateOptionId): ?array
    {
        $rows=self::content($sid,'option_conditions');if($rows===null)return null;
        return array_values(array_filter($rows,static fn($r)=>(int)$r['template_option_id']===$templateOptionId));
    }

    // (c) Dynamic opportunities. Opportunities are returned in reconcile
    // order (priority, dynamic_opportunity_id); the workspace stores them
    // by code, so sort here.
    public static function opportunities(int $sid): ?array
    {
        $rows=self::content($sid,'opportunities');if($rows===null)return null;
        $out=array_values(array_filter($rows,static fn($r)=>(int)($r['active']??1)===1));
        usort($out,static fn($a,$b)=>((int)$a['priority']<=>(int)$b['priority'])?:((int)$a['dynamic_opportunity_id']<=>(int)$b['dynamic_opportunity_id']));
        return$out;
    }

    public static function opportunity(int $sid,string $code): ?array
    {
        $rows=self::opportunities($sid);if($rows===null)return null;
        foreach($rows as$r)if(strcasecmp((string)$r['opportunity_code'],$code)===0)return$r;
        return null;
    }

    public static function opportunityByVersion(int $versionId,string $code): ?array
    {
        $rows=self::versionContent($versionId,'opportunities');if($rows===null)return null;
        foreach($rows as$r)if((int)($r['active']??1)===1&&strcasecmp((string)$r['opportunity_code'],$code)===0)return$r;
        return null;
    }

    public static function dynamicConditions(int $sid,int $opportunityId): ?array
    {
        $rows=self::content($sid,'dynamic_conditions');if($rows===null)return null;
        return array_values(array_filter($rows,static fn($r)=>(int)$r['dynamic_opportunity_id']===$opportunityId&&(int)($r['active']??1)===1));
    }

    public static function dynamicOptions(int $sid,int $opportunityId): ?array
    {
        $rows=self::content($sid,'dynamic_options');if($rows===null)return null;
        return array_values(array_filter($rows,static fn($r)=>(int)$r['dynamic_opportunity_id']===$opportunityId&&(int)($r['active']??1)===1));
    }

    public static function dynamicOptionsByVersion(int $versionId,int $opportunityId): ?array
    {
        $rows=self::versionContent($versionId,'dynamic_options');if($rows===null)return null;
        return array_values(array_filter($rows,static fn($r)=>(int)$r['dynamic_opportunity_id']===$opportunityId&&(int)($r['active']??1)===1));
    }

    public static function dynamicEffects(int $sid,int $dynamicOptionId): ?array
    {
        $rows=self::content($sid,'dynamic_effects');if($rows===null)return null;
        return array_values(array_filter($rows,static fn($r)=>(int)$r['dynamic_option_id']===$dynamicOptionId&&(int)($r['active']??1)===1));
    }

    // (d) No-action effects and conditions. No active filter, matching the
    // live queries (which do not filter on active either).
    public static function noActionEffects(int $sid,string $eventCode,string $roleCode): ?array
    {
        $rows=self::content($sid,'no_action_effects');if($rows===null)return null;
        return array_values(array_filter($rows,static fn($r)=>(string)$r['event_code']===$eventCode&&(string)$r['role_code']===$roleCode));
    }

    public static function noActionConditions(int $sid,int $effectId): ?array
    {
        $rows=self::content($sid,'no_action_conditions');if($rows===null)return null;
        return array_values(array_filter($rows,static fn($r)=>(int)$r['no_action_effect_id']===$effectId));
    }

    // (e) Endings and ending media.
    public static function resolutionSections(int $sid): ?array
    {
        $rows=self::content($sid,'resolutions');if($rows===null)return null;
        $sections=[];foreach($rows as$r){if((int)($r['active']??1)!==1)continue;$code=(string)$r['section_code'];$order=(int)($r['section_order']??0);if(!isset($sections[$code])||$order<$sections[$code]['section_order'])$sections[$code]=['section_code'=>$code,'section_order'=>$order];}
        $out=array_values($sections);usort($out,static fn($a,$b)=>($a['section_order']<=>$b['section_order'])?:strcmp($a['section_code'],$b['section_code']));return$out;
    }

    public static function resolutionFragments(int $sid,string $section): ?array
    {
        $rows=self::content($sid,'resolutions');if($rows===null)return null;
        $out=array_values(array_filter($rows,static fn($r)=>(string)$r['section_code']===$section&&(int)($r['active']??1)===1));
        usort($out,static fn($a,$b)=>((int)$a['priority']<=>(int)$b['priority'])?:((int)$a['resolution_fragment_id']<=>(int)$b['resolution_fragment_id']));return$out;
    }

    public static function resolutionConditions(int $sid,int $fragmentId): ?array
    {
        $rows=self::content($sid,'resolution_conditions');if($rows===null)return null;
        return array_values(array_filter($rows,static fn($r)=>(int)$r['resolution_fragment_id']===$fragmentId&&(int)($r['active']??1)===1));
    }

    public static function mediaForFragments(int $sid,array $fragmentIds): ?array
    {
        $rows=self::content($sid,'media');if($rows===null)return null;
        $want=array_fill_keys(array_map('intval',$fragmentIds),true);
        return array_values(array_filter($rows,static fn($r)=>(int)($r['active']??1)===1&&isset($want[(int)($r['resolution_fragment_id']??0)])));
    }

    // No active filter, matching the live query in
    // FocusedOpportunitySimulationService::candidates().
    public static function stateDefinition(int $sid,string $scope,string $code): ?array
    {
        $rows=self::content($sid,'state_definitions');if($rows===null)return null;
        foreach($rows as$r)if((string)$r['effect_scope']===$scope&&(string)$r['target_code']===$code)return$r;
        return null;
    }

    // Raw state-definition rows, or null when the simulation is unpinned
    // (callers keep their live query). A pinned simulation whose snapshot
    // lacks the key throws via content() (Task 5 Phase 3 fail-closed).
    public static function stateDefinitions(int $sid): ?array
    {
        return self::content($sid,'state_definitions');
    }

    // Resolve the playable options for one open decision from the pinned
    // snapshot. Returns null when the simulation is unpinned (callers keep
    // their live query). An opportunity wins its template slot — exactly as
    // the live mirror sync does — so a decision whose event is a conditional
    // opportunity resolves to the pinned dynamic options. Dynamic rows
    // carry '_pinned_dynamic_option_id' so effect/condition lookups know
    // where the option came from; template rows are returned as stored,
    // with event_code/role_code attached for the focus-weight lookup.
    public static function decisionOptionsForDecision(int $sid,int $decisionId,int $characterId): ?array
    {
        if(self::forSimulation($sid)===null)return null;
        $pdo=db();
        $eq=$pdo->prepare('SELECT se.event_code FROM decisions dx JOIN simulation_events se ON se.event_id=dx.event_id WHERE dx.decision_id=?');$eq->execute([$decisionId]);$eventCode=(string)$eq->fetchColumn();
        $rq=$pdo->prepare('SELECT pr.role_code FROM characters c JOIN plot_roles pr ON pr.plot_role_id=c.plot_role_id WHERE c.character_id=? AND c.simulation_id=?');$rq->execute([$characterId,$sid]);$roleCode=(string)$rq->fetchColumn();
        if($eventCode===''||$roleCode==='')return[];
        $opp=self::opportunity($sid,$eventCode);
        if($opp!==null&&(string)$opp['role_code']===$roleCode){
            $opts=self::dynamicOptions($sid,(int)$opp['dynamic_opportunity_id'])??[];
            foreach($opts as&$o){$o['event_code']=$eventCode;$o['_pinned_dynamic_option_id']=(int)$o['dynamic_option_id'];}
            unset($o);return$opts;
        }
        $templates=self::decisionTemplates($sid,$eventCode,false)??[];
        $template=null;foreach($templates as$t)if((string)$t['role_code']===$roleCode){$template=$t;break;}
        if($template===null)return[];
        $opts=self::decisionOptions($sid,(int)$template['template_id'])??[];
        foreach($opts as&$o){$o['event_code']=$eventCode;$o['role_code']=$roleCode;}
        unset($o);return$opts;
    }
}
