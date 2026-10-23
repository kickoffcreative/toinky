<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/MigrationService.php';
MigrationService::run();
require_once $root.'/app/StoryAuthoringService.php';
require_once $root.'/app/StoryCorrectionService.php';

$pdo=db();
$admin=$pdo->query('SELECT user_id,is_admin FROM users WHERE is_admin=1 ORDER BY user_id LIMIT 1')->fetch();
if(!$admin)throw new RuntimeException('Corrections v2 test needs an administrator fixture.');
$plot=$pdo->query("SELECT plot_id,code FROM plots WHERE code='EMPTY_THRONE' LIMIT 1")->fetch();
if(!$plot)throw new RuntimeException('Corrections v2 test needs the Empty Throne fixture.');
$version=$pdo->prepare('SELECT plot_version_id,version_no FROM plot_versions WHERE plot_id=? ORDER BY version_no DESC LIMIT 1');
$version->execute([$plot['plot_id']]);
$version=$version->fetch();
if(!$version){
    $audit=StoryAuthoringService::audit((int)$plot['plot_id']);
    $snapshot=json_encode(['plot'=>StoryAuthoringService::story((int)$plot['plot_id']),'content'=>$audit['workspace']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $pdo->prepare('INSERT INTO plot_versions(plot_id,version_no,snapshot_json,submitted_by_user_id,workflow_status) VALUES(?,1,?,? ,"SUBMITTED")')->execute([$plot['plot_id'],$snapshot,$admin['user_id']]);
    $version=['plot_version_id'=>(int)$pdo->lastInsertId(),'version_no'=>1];
}
$events=$pdo->prepare('SELECT event_code,trigger_percent,sort_order FROM plot_world_events WHERE plot_id=? AND active=1 ORDER BY trigger_percent,sort_order,plot_world_event_id');
$events->execute([$plot['plot_id']]);
$events=$events->fetchAll();
if(count($events)<2)throw new RuntimeException('Corrections v2 chronology test needs at least two events.');

function correction_file(array $package): array
{
    $path=tempnam(sys_get_temp_dir(),'toinky-correction-v2-');
    file_put_contents($path,json_encode($package,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    return ['error'=>UPLOAD_ERR_OK,'size'=>filesize($path),'name'=>'corrections-v2.json','tmp_name'=>$path];
}

$base=['format'=>'toinky-story-corrections-v2','plot_code'=>$plot['code'],'base_version'=>(int)$version['version_no']];
$headlineQuery=$pdo->prepare('SELECT headline FROM plot_world_events WHERE plot_id=? AND event_code=?');
$headlineQuery->execute([$plot['plot_id'],$events[0]['event_code']]);
$originalHeadline=(string)$headlineQuery->fetchColumn();
$valid=$base+['changes'=>[[
    'operation'=>'update','entity'=>'event','key'=>$events[0]['event_code'],
    'expected'=>['headline'=>$originalHeadline],
    'values'=>['headline'=>$originalHeadline.' Preview'],
    'reason'=>'Verify preview rollback without changing chronology.',
]]];
$file=correction_file($valid);
try{$preview=StoryCorrectionService::inspect($admin,(int)$version['plot_version_id'],$file);}finally{@unlink($file['tmp_name']);}
if(($preview['change_count']??0)!==1)throw new RuntimeException('A valid Corrections v2 preview did not produce one report item.');
$headlineQuery->execute([$plot['plot_id'],$events[0]['event_code']]);
$headlineAfter=$headlineQuery->fetchColumn();
if($headlineAfter!==$originalHeadline)throw new RuntimeException('Corrections v2 preview changed live story data.');

$stale=$base+['changes'=>[[
    'operation'=>'update','entity'=>'event','key'=>$events[0]['event_code'],
    'expected'=>['headline'=>'A hallucinated current headline'],
    'values'=>['headline'=>'Unsafe replacement'],
    'reason'=>'Verify stale-value protection.',
]]];
$file=correction_file($stale);$blocked=false;
try{StoryCorrectionService::inspect($admin,(int)$version['plot_version_id'],$file);}catch(RuntimeException $error){$blocked=str_contains($error->getMessage(),'Stale or hallucinated change');}finally{@unlink($file['tmp_name']);}
if(!$blocked)throw new RuntimeException('Corrections v2 accepted a stale expected value.');

$reordered=$base+['changes'=>[[
    'operation'=>'update','entity'=>'event','key'=>$events[0]['event_code'],
    'expected'=>['trigger_percent'=>(float)$events[0]['trigger_percent'],'sort_order'=>(int)$events[0]['sort_order']],
    'values'=>['trigger_percent'=>(float)$events[1]['trigger_percent']+1,'sort_order'=>(int)$events[0]['sort_order']],
    'reason'=>'Verify chronology protection.',
]]];
$file=correction_file($reordered);$blocked=false;
try{StoryCorrectionService::inspect($admin,(int)$version['plot_version_id'],$file);}catch(RuntimeException $error){$blocked=str_contains($error->getMessage(),'Chronology protection');}finally{@unlink($file['tmp_name']);}
if(!$blocked)throw new RuntimeException('Corrections v2 accepted an event-sequence change.');

echo "Corrections v2 current-value, preview-rollback, and chronology safeguards passed.\n";
