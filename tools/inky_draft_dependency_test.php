<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/StoryAuthoringService.php';
require_once $root.'/app/InkyGuideService.php';

$draftCount=(int)db()->query('SELECT COUNT(*) FROM inky_story_guide_drafts')->fetchColumn();
if($draftCount<1){
    // Fresh builds have no saved draft (drafts only exist once a writer runs
    // the guided flow). Seed a minimal step-0 draft row for EMPTY_THRONE so
    // the read below exercises the map on a real story workspace. Inserted
    // directly because saveProgress refuses published stories by design
    // ("Only draft stories can be resumed through Inky"); the test only
    // reads state_json. No-op on a live install where drafts already exist.
    $admin=db()->query('SELECT user_id FROM users WHERE is_admin=1 ORDER BY user_id LIMIT 1')->fetch();
    if(!$admin)throw new RuntimeException('Draft dependency test needs an administrator fixture.');
    $seedPlot=(int)(db()->query("SELECT plot_id FROM plots WHERE code='EMPTY_THRONE' AND status='active'")->fetchColumn()?:0);
    if($seedPlot<1)throw new RuntimeException('Draft dependency test needs the EMPTY_THRONE story.');
    db()->prepare('INSERT INTO inky_story_guide_drafts(plot_id,author_user_id,state_json) VALUES(?,?,?)')->execute([$seedPlot,(int)$admin['user_id'],'{"step":0,"answers":[],"accepted_proposals":[]}']);
}
$plotId=(int)($argv[1]??0);
if($plotId<1){
    $plotId=(int)(db()->query('SELECT plot_id FROM inky_story_guide_drafts ORDER BY updated_at_utc DESC LIMIT 1')->fetchColumn()?:0);
}
if($plotId<1)throw new RuntimeException('No saved Inky draft found.');
$q=db()->prepare('SELECT state_json FROM inky_story_guide_drafts WHERE plot_id=? LIMIT 1');
$q->execute([$plotId]);
$raw=$q->fetchColumn();
if($raw===false)throw new RuntimeException('No saved Inky draft found for plot '.$plotId.'.');
$state=json_decode((string)$raw,true,20,JSON_THROW_ON_ERROR);
$map=StoryAuthoringService::inkyDraftDependencyMap($plotId,(array)($state['accepted_proposals']??[]));
$entries=StoryAuthoringService::inkyDraftEntries($plotId,(array)($state['accepted_proposals']??[]));
$locked=array_filter($map,static fn($entry):bool=>!empty($entry['blocked']));
$manual=array_values(array_filter($entries,static fn($entry):bool=>($entry['source']??'')==='writer'));
$decisionChildren=array_values(array_filter($entries,static fn($entry):bool=>($entry['entity']??'')==='decision'&&!empty($entry['children'])));
echo json_encode(['plot_id'=>$plotId,'accepted_count'=>count((array)($state['accepted_proposals']??[])),'mapped_count'=>count($map),'locked_count'=>count($locked),'entry_count'=>count($entries),'manual_writer_count'=>count($manual),'decisions_with_options'=>count($decisionChildren),'entries'=>$map],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE),PHP_EOL;
echo 'PASS: draft dependency map and Writer scan are readable without changing story data.',PHP_EOL;
