<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/StoryAuthoringService.php';
require_once $root.'/app/InkyGuideService.php';

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
$locked=array_filter($map,static fn($entry):bool=>!empty($entry['blocked']));
echo json_encode(['plot_id'=>$plotId,'accepted_count'=>count((array)($state['accepted_proposals']??[])),'mapped_count'=>count($map),'locked_count'=>count($locked),'entries'=>$map],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE),PHP_EOL;
echo 'PASS: draft dependency map is readable without changing story data.',PHP_EOL;
