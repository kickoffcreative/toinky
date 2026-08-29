<?php
require_once __DIR__.'/StoryAuthoringService.php';
require_once __DIR__.'/BehaviorService.php';
require_once __DIR__.'/CausalService.php';
require_once __DIR__.'/ConsequencePropagationService.php';
require_once __DIR__.'/DynamicOpportunityService.php';
require_once __DIR__.'/StateSyncService.php';
require_once __DIR__.'/SimulationService.php';
require_once __DIR__.'/../tools/SimulationHarness.php';

final class StorySimulationBatchService
{
    public static function create(array $admin,array $input): int
    {
        if((int)($admin['is_admin']??0)!==1)throw new RuntimeException('Administrator access is required.');
        $plotId=(int)($input['plot_id']??0);$mode=strtoupper(trim((string)($input['controller_mode']??'')));if(!in_array($mode,['AUTONOMOUS','HUMAN_RANDOM','HUMAN_BIASED'],true))throw new RuntimeException('Choose a supported simulation control mode.');$runs=max(1,min(250,(int)($input['requested_runs']??10)));$duration=max(60,min(86400,(int)($input['duration_seconds']??300)));$step=max(1,min(3600,(int)($input['step_seconds']??30)));
        $q=db()->prepare('SELECT plot_id FROM plots WHERE plot_id=?');$q->execute([$plotId]);if(!$q->fetchColumn())throw new RuntimeException('Story not found.');$q=db()->prepare('SELECT plot_version_id FROM plot_versions WHERE plot_id=? ORDER BY version_no DESC LIMIT 1');$q->execute([$plotId]);$versionId=$q->fetchColumn()?:null;
        $q=db()->prepare('INSERT INTO simulation_review_batches(plot_id,plot_version_id,requested_by_user_id,controller_mode,requested_runs,duration_seconds,step_seconds) VALUES(?,?,?,?,?,?,?)');$q->execute([$plotId,$versionId,$admin['user_id'],$mode,$runs,$duration,$step]);return(int)db()->lastInsertId();
    }

    public static function batches(int $limit=50): array
    {
        $q=db()->prepare('SELECT b.*,p.code,p.title,u.display_name requested_by FROM simulation_review_batches b JOIN plots p ON p.plot_id=b.plot_id JOIN users u ON u.user_id=b.requested_by_user_id ORDER BY b.simulation_review_batch_id DESC LIMIT '.max(1,min(250,$limit)));$q->execute();return$q->fetchAll();
    }

    public static function one(int $batchId): ?array
    {
        $q=db()->prepare('SELECT b.*,p.code,p.title,u.display_name requested_by FROM simulation_review_batches b JOIN plots p ON p.plot_id=b.plot_id JOIN users u ON u.user_id=b.requested_by_user_id WHERE b.simulation_review_batch_id=?');$q->execute([$batchId]);$row=$q->fetch();return$row?:null;
    }

    public static function processNext(): ?array
    {
        $pdo=db();$lockName='toinky_simulation_review_worker';$lock=$pdo->prepare('SELECT GET_LOCK(?,0)');$lock->execute([$lockName]);if((int)$lock->fetchColumn()!==1)return null;$batchId=0;
        try{
            $pdo->exec('UPDATE simulation_review_batches b SET b.completed_runs=(SELECT COALESCE(MAX(r.run_no),0) FROM simulation_review_batch_runs r WHERE r.simulation_review_batch_id=b.simulation_review_batch_id),b.status="QUEUED",b.last_error="Recovered after an interrupted worker run." WHERE b.status="RUNNING" AND b.updated_at_utc<UTC_TIMESTAMP()-INTERVAL 60 MINUTE');
            $pdo->beginTransaction();
            try{
                $q=$pdo->query('SELECT * FROM simulation_review_batches WHERE status="QUEUED" ORDER BY created_at_utc,simulation_review_batch_id LIMIT 1 FOR UPDATE');$batch=$q->fetch();
                if(!$batch){$pdo->commit();return null;}
                $batchId=(int)$batch['simulation_review_batch_id'];$count=$pdo->prepare('SELECT COALESCE(MAX(run_no),0) FROM simulation_review_batch_runs WHERE simulation_review_batch_id=?');$count->execute([$batchId]);$savedRuns=(int)$count->fetchColumn();
                $pdo->prepare('UPDATE simulation_review_batches SET completed_runs=?,status="RUNNING",started_at_utc=COALESCE(started_at_utc,UTC_TIMESTAMP()),completed_at_utc=NULL,last_error=NULL WHERE simulation_review_batch_id=?')->execute([$savedRuns,$batchId]);$batch['completed_runs']=$savedRuns;$pdo->commit();
            }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
            if((int)$batch['completed_runs']>=(int)$batch['requested_runs']){$summary=self::summarize($batchId);$package=self::reviewPackage($batchId,$summary);$pdo->prepare('UPDATE simulation_review_batches SET completed_runs=?,status="COMPLETE",summary_json=?,review_package=?,last_error=NULL,completed_at_utc=UTC_TIMESTAMP() WHERE simulation_review_batch_id=?')->execute([$summary['completed_runs'],json_encode($summary,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$package,$batchId]);return self::one($batchId);}
            $plot=StoryAuthoringService::story((int)$batch['plot_id']);[$controllerMode,$strategy]=self::modeSettings((string)$batch['controller_mode']);$result=SimulationHarness::run((string)$plot['code'],$strategy,(int)$batch['duration_seconds'],(int)$batch['step_seconds'],$controllerMode,'');
            $pdo->beginTransaction();
            try{
                $current=$pdo->prepare('SELECT requested_runs FROM simulation_review_batches WHERE simulation_review_batch_id=? FOR UPDATE');$current->execute([$batchId]);$requestedRuns=(int)$current->fetchColumn();if($requestedRuns<1)throw new RuntimeException('Simulation review batch is unavailable.');
                $count=$pdo->prepare('SELECT COALESCE(MAX(run_no),0) FROM simulation_review_batch_runs WHERE simulation_review_batch_id=?');$count->execute([$batchId]);$runNo=(int)$count->fetchColumn()+1;
                if($runNo<=$requestedRuns)$pdo->prepare('INSERT INTO simulation_review_batch_runs(simulation_review_batch_id,run_no,simulation_id,result_json) VALUES(?,?,?,?)')->execute([$batchId,$runNo,$result['simulation_id']??null,json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)]);
                else$runNo=$requestedRuns;
                $complete=$runNo>=$requestedRuns;$pdo->prepare('UPDATE simulation_review_batches SET completed_runs=?,status=?,completed_at_utc=NULL WHERE simulation_review_batch_id=?')->execute([$runNo,$complete?'RUNNING':'QUEUED',$batchId]);$pdo->commit();
            }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
            if($complete){$summary=self::summarize($batchId);$package=self::reviewPackage($batchId,$summary);$pdo->prepare('UPDATE simulation_review_batches SET completed_runs=?,status="COMPLETE",summary_json=?,review_package=?,last_error=NULL,completed_at_utc=UTC_TIMESTAMP() WHERE simulation_review_batch_id=?')->execute([$summary['completed_runs'],json_encode($summary,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$package,$batchId]);}
            return self::one($batchId);
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if($batchId>0)$pdo->prepare('UPDATE simulation_review_batches SET status="FAILED",last_error=?,completed_at_utc=UTC_TIMESTAMP() WHERE simulation_review_batch_id=?')->execute([mb_substr($e->getMessage(),0,4000),$batchId]);throw$e;}
        finally{$release=$pdo->prepare('SELECT RELEASE_LOCK(?)');$release->execute([$lockName]);}
    }

    public static function resume(array $admin,int $batchId): array
    {
        if((int)($admin['is_admin']??0)!==1)throw new RuntimeException('Administrator access is required.');$pdo=db();$pdo->beginTransaction();
        try{$q=$pdo->prepare('SELECT requested_runs,status FROM simulation_review_batches WHERE simulation_review_batch_id=? FOR UPDATE');$q->execute([$batchId]);$batch=$q->fetch();if(!$batch)throw new RuntimeException('Simulation review batch not found.');if($batch['status']!=='FAILED')throw new RuntimeException('Only a failed simulation review batch can be resumed.');$count=$pdo->prepare('SELECT COALESCE(MAX(run_no),0) FROM simulation_review_batch_runs WHERE simulation_review_batch_id=?');$count->execute([$batchId]);$savedRuns=(int)$count->fetchColumn();$complete=$savedRuns>=(int)$batch['requested_runs'];$pdo->prepare('UPDATE simulation_review_batches SET completed_runs=?,status=?,last_error=NULL,completed_at_utc=NULL WHERE simulation_review_batch_id=?')->execute([$savedRuns,$complete?'RUNNING':'QUEUED',$batchId]);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
        if($complete){$summary=self::summarize($batchId);$package=self::reviewPackage($batchId,$summary);$pdo->prepare('UPDATE simulation_review_batches SET completed_runs=?,status="COMPLETE",summary_json=?,review_package=?,completed_at_utc=UTC_TIMESTAMP() WHERE simulation_review_batch_id=?')->execute([$summary['completed_runs'],json_encode($summary,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$package,$batchId]);}
        return self::one($batchId)??throw new RuntimeException('Simulation review batch not found after resume.');
    }

    private static function modeSettings(string $mode): array
    {
        if($mode==='AUTONOMOUS'){putenv('GAME2_SIM_NPC_REASONER=manual');putenv('GAME2_SIM_PLAYER_REASONER=manual');return['all-ai','random'];}
        putenv('GAME2_SIM_NPC_REASONER=manual');putenv('GAME2_SIM_PLAYER_REASONER=manual');return[$mode==='HUMAN_BIASED'?'human-biased':'human-random',$mode==='HUMAN_BIASED'?'weighted':'random'];
    }

    private static function summarize(int $batchId): array
    {
        $q=db()->prepare('SELECT result_json,simulation_id FROM simulation_review_batch_runs WHERE simulation_review_batch_id=? ORDER BY run_no');$q->execute([$batchId]);$runs=$q->fetchAll();$families=[];$warnings=[];$counts=['player_actions'=>0,'npc_actions'=>0,'player_no_action'=>0];$ids=[];
        foreach($runs as$row){$r=json_decode((string)$row['result_json'],true)?:[];if($row['simulation_id'])$ids[]=(int)$row['simulation_id'];$family=(string)($r['outcome_family_label']??'Unknown');$families[$family]=($families[$family]??0)+1;foreach(($r['warnings']??[])as$warning)$warnings[$warning]=($warnings[$warning]??0)+1;foreach($counts as$key=>$unused)$counts[$key]+=(int)($r['counts'][$key]??0);}
        arsort($families);arsort($warnings);$choices=[];
        if($ids){$marks=implode(',',array_fill(0,count($ids),'?'));$cq=db()->prepare('SELECT pt.event_code,pr.role_code,d.chosen_option_code,COUNT(*) choice_count FROM decisions d JOIN simulations s ON s.simulation_id=d.simulation_id JOIN simulation_events se ON se.event_id=d.event_id JOIN characters c ON c.character_id=d.character_id JOIN plot_roles pr ON pr.plot_role_id=c.plot_role_id JOIN plot_decision_templates pt ON pt.plot_id=s.plot_id AND pt.event_code=se.event_code AND pt.role_code=pr.role_code WHERE d.simulation_id IN ('.$marks.') AND d.status="CHOSEN" GROUP BY pt.event_code,pr.role_code,d.chosen_option_code ORDER BY pt.event_code,pr.role_code,d.chosen_option_code');$cq->execute($ids);$choices=$cq->fetchAll();}
        $seen=[];foreach($choices as$choice)$seen[$choice['event_code'].'|'.$choice['role_code'].'|'.$choice['chosen_option_code']]=true;$batch=self::one($batchId);$all=db()->prepare('SELECT t.event_code,t.role_code,o.option_code FROM plot_decision_template_options o JOIN plot_decision_templates t ON t.template_id=o.template_id WHERE t.plot_id=? AND t.active=1 AND o.active=1 ORDER BY t.event_code,t.role_code,o.sort_order,o.template_option_id');$all->execute([$batch['plot_id']]);$unobserved=[];foreach($all->fetchAll()as$option){$key=$option['event_code'].'|'.$option['role_code'].'|'.$option['option_code'];if(!isset($seen[$key]))$unobserved[]=$key;}
        $n=max(1,count($runs));$improvements=[];if(count($families)<2)$improvements[]='Outcome diversity is low: all completed runs reached the same semantic outcome family. Review branch dependencies and ending conditions.';foreach($warnings as$message=>$count)$improvements[]=$count.' of '.$n.' runs: '.$message;if(($counts['player_no_action']/$n)>2)$improvements[]='Players averaged '.round($counts['player_no_action']/$n,1).' timed-out choices per run. Review prompt clarity and decision windows.';if($unobserved)$improvements[]='These options were never selected in this batch; inspect weights, conditions, and reachability: '.implode(', ',array_slice($unobserved,0,20)).(count($unobserved)>20?' and '.(count($unobserved)-20).' more':'').'.';if(!$improvements)$improvements[]='No deterministic integrity issue was found. Use the reviewer package to assess prose, viewpoint knowledge, and qualitative agency.';
        return['completed_runs'=>count($runs),'outcome_families'=>$families,'warning_frequency'=>$warnings,'average_counts'=>array_map(static fn($v)=>round($v/$n,2),$counts),'choice_frequency'=>$choices,'unobserved_options'=>$unobserved,'specific_improvement_points'=>$improvements];
    }

    private static function reviewPackage(int $batchId,array $summary): string
    {
        $batch=self::one($batchId);$audit=StoryAuthoringService::audit((int)$batch['plot_id']);$lines=['INSTRUCTIONS FOR THE REVIEWER','Use the authored package and simulation evidence below. Identify specific improvements for immersion, viewpoint continuity, agency, causality, dependency coverage, reachability, pacing, replayability, and mobile readability. Separate observed evidence from inference. Refer to stable keys. Never recommend plot-specific PHP. Do not propose chronology or structural-order changes unless the evidence demonstrates a defect; label any such proposal MANUAL STRUCTURAL REVIEW and do not include it in an importable correction file.','','TOINKY SIMULATION REVIEW EVIDENCE','Story: '.$batch['title'].' ('.$batch['code'].')','Control Mode: '.str_replace('_',' ',(string)$batch['controller_mode']),'Completed Runs: '.$summary['completed_runs'],'','OBSERVED OUTCOME FAMILIES'];foreach($summary['outcome_families']as$label=>$count)$lines[]=$count.' run(s): '.$label;$lines[]='';$lines[]='OBSERVED CHOICE FREQUENCY';foreach($summary['choice_frequency']as$c)$lines[]=$c['event_code'].' | '.$c['role_code'].' | '.$c['chosen_option_code'].': '.$c['choice_count'];$lines[]='';$lines[]='UNOBSERVED OPTIONS (NOT PROOF OF UNREACHABILITY)';foreach($summary['unobserved_options']as$key)$lines[]=$key;$lines[]='';$lines[]='DETERMINISTIC IMPROVEMENT POINTS';foreach($summary['specific_improvement_points']as$i=>$point)$lines[]=($i+1).'. '.$point;$lines[]='';$lines[]=$audit['text'];return implode("\n",$lines);
    }
}
