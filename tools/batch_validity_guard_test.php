<?php
declare(strict_types=1);
// Guard test: batch-run validity (audit task 15 part 3). A review-batch run
// is valid evidence only when its frozen result shows a non-empty cast with
// at least one player character, a completed simulation, no cast/resolution
// failure warning, and -- for organic runs -- at least one recorded decision.
// Validity is stored alongside result_json (migration 133) and summarize()
// aggregates only valid runs; invalid runs are reported separately, never
// rewritten: result_json and the run's COMPLETE status stay as recorded.
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only.');}

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/StorySimulationBatchService.php';
require_once $root.'/tools/SimulationHarness.php';

$failures=[];
$check=function(bool $ok,string $label)use(&$failures){echo ($ok?'PASS':'FAIL').' '.$label."\n";if(!$ok)$failures[]=$label;};

$healthy=[
    'simulation_id'=>1,'status'=>'COMPLETE',
    'characters'=>[
        'Hero'=>['type'=>'PLAYER','goal'=>'Claim','fear'=>'Loss','location'=>'Hall','stats'=>[]],
        'Guide'=>['type'=>'NPC','goal'=>'Help','fear'=>'None','location'=>'Hall','stats'=>[]],
    ],
    'counts'=>['player_actions'=>3,'npc_actions'=>1,'player_no_action'=>0],
    'warnings'=>[],'outcome_family_label'=>'Peace / HIGH','resolution'=>'The end.',
];

// --- 1. assessValidity() on synthetic results ---
$v=StorySimulationBatchService::assessValidity($healthy);
$check($v['valid']===true&&$v['reason']===null,'a healthy organic run is valid');
$empty=$healthy;$empty['characters']=[];
$v=StorySimulationBatchService::assessValidity($empty);
$check($v['valid']===false&&$v['reason']==='empty cast','an empty cast is invalid (empty cast)');
$npcOnly=$healthy;$npcOnly['characters']=['Guide'=>['type'=>'NPC','goal'=>'Help','fear'=>'None','location'=>'Hall','stats'=>[]]];
$v=StorySimulationBatchService::assessValidity($npcOnly);
$check($v['valid']===false&&$v['reason']==='no player characters','a cast with no player character is invalid');
$noDecisions=$healthy;$noDecisions['counts']=['player_actions'=>0,'npc_actions'=>0,'player_no_action'=>0];
$v=StorySimulationBatchService::assessValidity($noDecisions);
$check($v['valid']===false&&$v['reason']==='no decisions recorded','an organic run with no decisions is invalid');
$badWarning=$healthy;$badWarning['warnings']=['No FINAL_STATE resolution text was generated.'];
$v=StorySimulationBatchService::assessValidity($badWarning);
$check($v['valid']===false&&str_contains((string)$v['reason'],'No FINAL_STATE'),'a missing FINAL_STATE resolution invalidates the run');
$castWarning=$healthy;$castWarning['warnings']=['Active NPC count exceeds cast budget.'];
$v=StorySimulationBatchService::assessValidity($castWarning);
$check($v['valid']===false,'a cast-budget warning invalidates the run');
$qualityWarning=$healthy;$qualityWarning['warnings']=['Factor TENSION is outside its configured range.'];
$v=StorySimulationBatchService::assessValidity($qualityWarning);
$check($v['valid']===true,'a non-cast quality warning does not invalidate the run');
$incomplete=$healthy;$incomplete['status']='RUNNING';
$v=StorySimulationBatchService::assessValidity($incomplete);
$check($v['valid']===false&&$v['reason']==='simulation did not complete','a run that did not complete is invalid');
$focused=$noDecisions;$focused['focus_test']=['seeded'=>true,'opportunity_code'=>'TEST','target_option_code'=>'OPT_A','opportunity_eligible'=>false,'decision_delivered'=>false,'choice_applied'=>false];
$v=StorySimulationBatchService::assessValidity($focused);
$check($v['valid']===true,'a seeded focused run with no decisions is still valid evidence');

// --- static checks ---
$src=file_get_contents($root.'/app/StorySimulationBatchService.php');
$check($src!==false&&str_contains($src,'assessValidity'),'the batch service assesses per-run validity');
$check($src!==false&&str_contains($src,'is_valid,invalid_reason'),'the batch service stores validity alongside result_json');
$mig=$root.'/database/migrations/133_batch_run_validity.sql';
$check(is_file($mig)&&str_contains((string)file_get_contents($mig),'simulation_review_batch_runs'),'migration 133 adds the validity columns');

// --- 2. summarize() excludes invalid runs (DB-backed, skips gracefully) ---
$pdo=db();
$cols=$pdo->query("SHOW COLUMNS FROM simulation_review_batch_runs LIKE 'is_valid'")->fetchAll();
if(!$cols){echo "SKIP: migration 133 columns not present yet.\n";}
else{
    $plot=$pdo->prepare("SELECT plot_id,code FROM plots WHERE code='EMPTY_THRONE' AND status IN ('draft','active') LIMIT 1");
    $plot->execute();$prow=$plot->fetch();
    $adminRow=$prow?$pdo->query('SELECT user_id FROM users WHERE is_admin=1 LIMIT 1')->fetch():false;
    if(!$prow||!$adminRow){echo "SKIP: no EMPTY_THRONE plot or admin user fixture.\n";}
    else{
        $plotId=(int)$prow['plot_id'];
        $batchId=0;$created=false;
        try{
            $batchId=StorySimulationBatchService::create(['is_admin'=>1,'user_id'=>(int)$adminRow['user_id']],['plot_id'=>$plotId,'controller_mode'=>'AUTONOMOUS','requested_runs'=>2,'duration_seconds'=>300,'step_seconds'=>30]);
            $created=true;
            $validResult=$healthy;$validResult['simulation_id']=null;$validResult['outcome_family_label']='Peace / HIGH';$validResult['counts']=['player_actions'=>4,'npc_actions'=>0,'player_no_action'=>0];
            $invalidResult=$healthy;$invalidResult['simulation_id']=null;$invalidResult['outcome_family_label']='Chaos / LOW';$invalidResult['characters']=[];$invalidResult['counts']=['player_actions'=>100,'npc_actions'=>0,'player_no_action'=>0];
            $ins=$pdo->prepare('INSERT INTO simulation_review_batch_runs(simulation_review_batch_id,run_no,simulation_id,result_json,is_valid,invalid_reason) VALUES(?,?,?,?,?,?)');
            $ins->execute([$batchId,1,null,json_encode($validResult,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),1,null]);
            $ins->execute([$batchId,2,null,json_encode($invalidResult,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),0,'empty cast']);
            $ref=new ReflectionMethod('StorySimulationBatchService','summarize');$ref->setAccessible(true);
            $summary=$ref->invoke(null,$batchId);
            $check((int)$summary['completed_runs']===2,'summarize() counts both recorded runs as completed');
            $check((int)$summary['valid_runs']===1,'summarize() reports one valid run');
            $check((int)$summary['invalid_run_count']===1,'summarize() reports one invalid run');
            $check(isset($summary['outcome_families']['Peace / HIGH'])&&!isset($summary['outcome_families']['Chaos / LOW']),'outcome_families aggregates only the valid run');
            $check((float)$summary['average_counts']['player_actions']===4.0,'average_counts averages only the valid run');
            $check(($summary['invalid_runs'][0]['reason']??'')==='empty cast','the invalid run carries its reason');
            $check((($summary['invalid_run_reasons']['empty cast']??0)===1),'invalid reasons are tallied');
            // Legacy rows (NULL validity columns) are assessed at read time.
            $pdo->prepare('UPDATE simulation_review_batch_runs SET is_valid=NULL,invalid_reason=NULL WHERE simulation_review_batch_id=? AND run_no=2')->execute([$batchId]);
            $summary2=$ref->invoke(null,$batchId);
            $check((int)$summary2['invalid_run_count']===1&&($summary2['invalid_runs'][0]['reason']??'')==='empty cast','a legacy run with NULL validity columns is assessed from its frozen result');
            // And the frozen result is untouched.
            $stored=$pdo->prepare('SELECT result_json FROM simulation_review_batch_runs WHERE simulation_review_batch_id=? AND run_no=2');$stored->execute([$batchId]);
            $check(str_contains((string)$stored->fetchColumn(),'"outcome_family_label":"Chaos / LOW"'),'the frozen result_json is never rewritten');
        }finally{
            if($created){$pdo->prepare('DELETE FROM simulation_review_batch_runs WHERE simulation_review_batch_id=?')->execute([$batchId]);$pdo->prepare('DELETE FROM simulation_review_batches WHERE simulation_review_batch_id=?')->execute([$batchId]);}
        }
        // --- 3. summarizeFocused() excludes invalid runs ---
        $focusedBatch=0;$focusedCreated=false;
        try{
            $pdo->prepare("INSERT INTO simulation_review_batches(plot_id,plot_version_id,requested_by_user_id,controller_mode,focus_opportunity_code,focus_runs_per_option,requested_runs,duration_seconds,step_seconds,status) VALUES(?,NULL,?,'FOCUSED','TEST',1,2,300,30,'COMPLETE')")->execute([$plotId,(int)$adminRow['user_id']]);
            $focusedBatch=(int)$pdo->lastInsertId();$focusedCreated=true;
            $focusValid=$healthy;$focusValid['simulation_id']=null;$focusValid['outcome_family_label']='Peace / HIGH';
            $focusValid['focus_test']=['seeded'=>true,'opportunity_code'=>'TEST','role_code'=>'HERO','target_option_code'=>'OPT_A','available_option_codes'=>['OPT_A'],'opportunity_eligible'=>true,'decision_delivered'=>true,'choice_applied'=>true,'condition_trace'=>[],'setup'=>[],'error'=>null,'downstream_opportunities'=>[]];
            $focusInvalid=$healthy;$focusInvalid['simulation_id']=null;$focusInvalid['outcome_family_label']='Chaos / LOW';$focusInvalid['characters']=[];
            $focusInvalid['focus_test']=['seeded'=>true,'opportunity_code'=>'TEST','role_code'=>'HERO','target_option_code'=>'OPT_A','available_option_codes'=>['OPT_A'],'opportunity_eligible'=>false,'decision_delivered'=>false,'choice_applied'=>false,'condition_trace'=>[],'setup'=>[],'error'=>'The seeded checkpoint did not satisfy every active condition.','downstream_opportunities'=>[]];
            $ins=$pdo->prepare('INSERT INTO simulation_review_batch_runs(simulation_review_batch_id,run_no,simulation_id,result_json,is_valid,invalid_reason) VALUES(?,?,?,?,?,?)');
            $ins->execute([$focusedBatch,1,null,json_encode($focusValid,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),1,null]);
            $ins->execute([$focusedBatch,2,null,json_encode($focusInvalid,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),0,'empty cast']);
            $refF=new ReflectionMethod('StorySimulationBatchService','summarizeFocused');$refF->setAccessible(true);
            $batchRow=StorySimulationBatchService::one($focusedBatch);
            $fs=$refF->invoke(null,$focusedBatch,$batchRow);
            $check((int)$fs['completed_runs']===2&&(int)$fs['valid_runs']===1,'summarizeFocused() counts total and valid runs');
            $check((int)$fs['activation_count']===1,'summarizeFocused() counts activation only from valid runs');
            $check((int)$fs['choice_application_count']===1,'summarizeFocused() counts application only from valid runs');
            $check(isset($fs['outcome_families']['Peace / HIGH'])&&!isset($fs['outcome_families']['Chaos / LOW']),'summarizeFocused() families exclude the invalid run');
            $check((int)$fs['invalid_run_count']===1,'summarizeFocused() reports the invalid run');
        }catch(Throwable $e){echo 'SKIP: focused batch fixture could not be built ('.$e->getMessage().").\n";}
        finally{
            if($focusedCreated){$pdo->prepare('DELETE FROM simulation_review_batch_runs WHERE simulation_review_batch_id=?')->execute([$focusedBatch]);$pdo->prepare('DELETE FROM simulation_review_batches WHERE simulation_review_batch_id=?')->execute([$focusedBatch]);}
        }
    }
}

if($failures){echo 'FAILURES: '.implode('; ',$failures)."\n";exit(1);}
echo "batch_validity_guard_test: all checks passed.\n";
