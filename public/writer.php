<?php
$root=dirname(__DIR__);ini_set('display_errors','0');error_reporting(E_ALL);try{require_once $root.'/app/bootstrap.php';require_once $root.'/app/MigrationService.php';MigrationService::run();require_once $root.'/app/AuthService.php';require_once $root.'/app/MediaLibraryService.php';require_once $root.'/app/StoryMediaService.php';require_once $root.'/app/StoryAuthoringService.php';AuthService::boot();$writer=AuthService::requireWriter();}catch(Throwable $startupError){error_log('Toinky writer startup failed: '.$startupError->getMessage());http_response_code(500);exit('Story Writer is temporarily unavailable. Please contact the administrator.');}$isAdmin=(int)$writer['is_admin']===1;$error=$notice=null;$audit=null;$plotId=(int)($_GET['plot']??$_POST['plot_id']??0);$tab=(string)($_GET['tab']??$_POST['tab']??'overview');$tabs=['how_it_works','overview','characters','timeline','decisions','map','endings','artwork','media'];if($isAdmin)$tabs[]='validation';if(!in_array($tab,$tabs,true))$tab='overview';
if($_SERVER['REQUEST_METHOD']==='POST'){try{AuthService::requireCsrf();$a=(string)($_POST['action']??'');if($a==='submit'&&!$isAdmin)throw new RuntimeException('Administrator access is required to validate or submit a story.');if($a==='create'){$plotId=StoryAuthoringService::createStory($writer,$_POST);$notice='Story draft created.';}elseif($a==='overview'){StoryAuthoringService::updateOverview($writer,$plotId,$_POST);$notice='Overview saved.';}elseif($a==='character'){StoryAuthoringService::saveCharacter($writer,$plotId,$_POST);StoryMediaService::setCharacterImage($writer,$plotId,(string)$_POST['role_code'],$_POST['character_media_id']??'__KEEP__');$notice='Character saved.';}elseif($a==='characters_bulk'){$pdo=db();$pdo->beginTransaction();try{foreach((array)($_POST['characters']??[])as$character){StoryAuthoringService::saveCharacter($writer,$plotId,$character);StoryMediaService::setCharacterImage($writer,$plotId,(string)($character['role_code']??''),$character['character_media_id']??'__KEEP__');}$pdo->commit();$notice='All character changes saved.';}catch(Throwable$bulkError){if($pdo->inTransaction())$pdo->rollBack();throw$bulkError;}}elseif($a==='event'){StoryAuthoringService::saveWorldEvent($writer,$plotId,$_POST);$notice='Event saved.';}elseif($a==='decision'){StoryAuthoringService::saveDecision($writer,$plotId,$_POST);$notice='Decision saved.';}elseif($a==='option'){StoryAuthoringService::saveOption($writer,$plotId,$_POST);$notice='Option saved.';}elseif($a==='effect'){StoryAuthoringService::saveOptionEffect($writer,$plotId,$_POST);$notice='Option effect saved.';}elseif($a==='opportunity'){StoryAuthoringService::saveDynamicOpportunity($writer,$plotId,$_POST);$notice='Conditional opportunity saved.';}elseif($a==='dynamic_condition'){StoryAuthoringService::saveDynamicCondition($writer,$plotId,$_POST);$notice='Opportunity condition saved.';}elseif($a==='dynamic_option'){StoryAuthoringService::saveDynamicOption($writer,$plotId,$_POST);$notice='Conditional option saved.';}elseif($a==='dynamic_effect'){StoryAuthoringService::saveDynamicEffect($writer,$plotId,$_POST);$notice='Conditional effect saved.';}elseif($a==='resolution'){StoryAuthoringService::saveResolution($writer,$plotId,$_POST);$notice='Ending fragment saved.';}elseif($a==='resolution_condition'){StoryAuthoringService::saveResolutionCondition($writer,$plotId,$_POST);$notice='Ending condition saved.';}elseif($a==='attach_media'){StoryMediaService::attachFromLibrary($writer,$plotId,$_POST);$notice='Library image added to this story.';}elseif($a==='library_upload'){MediaLibraryService::upload($writer,$_POST,$_FILES['image']??[]);$notice='Image added to your media library.';$tab='media';}elseif($a==='library_update'){MediaLibraryService::update($writer,(int)$_POST['media_id'],$_POST);$notice='Media details updated.';$tab='media';}elseif($a==='library_process'){MediaLibraryService::cropResize($writer,(int)$_POST['media_id'],$_POST);$notice='Media cropped and resized.';$tab='media';}elseif($a==='library_thumbnail'){MediaLibraryService::regenerateThumbnail($writer,(int)$_POST['media_id'],$_POST);$notice='Thumbnail recreated.';$tab='media';}elseif($a==='library_delete'){MediaLibraryService::delete($writer,(int)$_POST['media_id']);$notice='Media deleted.';$tab='media';}elseif($a==='deactivate'){StoryMediaService::deactivate($writer,(int)$_POST['media_asset_id']);$notice='Image hidden.';}elseif($a==='submit'){StoryAuthoringService::submit($writer,$plotId);$notice='Fixed revision submitted for review.';}}catch(Throwable$e){$error=$e->getMessage();}}
$plots=StoryAuthoringService::stories($writer);if($plotId<1&&$plots)$plotId=(int)$plots[0]['plot_id'];$plot=null;foreach($plots as$p)if((int)$p['plot_id']===$plotId)$plot=$p;if($plotId&&(!$plot||!StoryAuthoringService::canEdit($writer,$plotId))){http_response_code(403);exit('You may not edit that story.');}$genres=db()->query('SELECT * FROM story_genres WHERE active=1 ORDER BY sort_order')->fetchAll();$libraryMedia=MediaLibraryService::visibleTo($writer);$mediaView=in_array((string)($_GET['media_view']??$_POST['media_view']??'thumbnail'),['thumbnail','list'],true)?(string)($_GET['media_view']??$_POST['media_view']??'thumbnail'):'thumbnail';$mediaPerPage=in_array((int)($_GET['per_page']??$_POST['per_page']??25),[25,50,100],true)?(int)($_GET['per_page']??$_POST['per_page']??25):25;$mediaPage=max(1,(int)($_GET['media_page']??$_POST['media_page']??1));$mediaPagination=MediaLibraryService::page($writer,$mediaPage,$mediaPerPage);$characterImages=[];$w=['characters'=>[],'events'=>[],'decisions'=>[],'options'=>[],'effects'=>[],'opportunities'=>[],'dynamic_conditions'=>[],'dynamic_options'=>[],'dynamic_effects'=>[],'resolutions'=>[],'resolution_conditions'=>[],'media'=>[]];if($plot){$plot=StoryAuthoringService::story($plotId);$w=StoryAuthoringService::workspace($plotId);$characterImages=StoryMediaService::characterImagesForPlot($plotId);if($tab==='validation'&&!$audit)$audit=StoryAuthoringService::audit($plotId);}
function writer_csrf_field():string{return'<input type="hidden" name="csrf_token" value="'.e(AuthService::csrfToken()).'">';}function writer_form_context(int$p,string$t,string$a):string{return writer_csrf_field().'<input type="hidden" name="plot_id" value="'.$p.'"><input type="hidden" name="tab" value="'.e($t).'"><input type="hidden" name="action" value="'.e($a).'">';}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Story Writer · Toinky</title><style>
:root{font-family:Inter,system-ui,sans-serif;color:#1C242B;background:#F4EEE2}*{box-sizing:border-box}body{margin:0}.workspace-layout{display:grid;grid-template-columns:250px minmax(0,1fr);min-height:100vh}.workspace-side{background:#1C242B;color:#fff;padding:24px 16px}.workspace-side a{display:block;color:#F4EEE2;text-decoration:none;padding:9px 10px;border-radius:8px}.workspace-side a.active,.workspace-side a:hover{background:#66715C;color:#fff}.workspace-group{font-size:11px;text-transform:uppercase;color:#F4EEE2;font-weight:800;letter-spacing:.08em;margin:22px 10px 7px}.workspace-main{min-width:0}.workspace-side .toinky-brand-group{display:block}.workspace-side .toinky-brand{display:block;max-width:180px}.workspace-side .brand-context{display:block!important;color:#F4EEE2!important;margin:14px 10px 0;line-height:1.3}.bar{display:none}.barin,.shell{max-width:1280px;margin:auto}.barin{display:flex;justify-content:space-between}.bar a{color:#fff}.shell{padding:22px 16px}.layout{display:grid;grid-template-columns:240px minmax(0,1fr);gap:18px}.card{background:#fff;border:1px solid #d9d4ca;border-radius:14px;padding:19px;margin-bottom:16px}.side{position:sticky;top:18px;align-self:start}.link,.tab{display:block;padding:9px 10px;border-radius:8px;color:#1C242B;text-decoration:none}.link.active,.tab.active{background:#B58E46;color:#1C242B;font-weight:800}.side.card{background:#F4EEE2;color:#1C242B;border-color:#d5cec1}.side.card h2{color:#1C242B}.side.card .link{color:#1C242B}.side.card .link.active{background:#B58E46;color:#1C242B}.side.card .muted{color:#52606A}.tabs{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px}.tab{border:1px solid #d6d0e6;background:#fff}.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.field{font-weight:750}.field input,.field select,.field textarea{display:block;width:100%;margin-top:5px;padding:10px;border:1px solid #c9c3b9;border-radius:8px;font:inherit}.wide{grid-column:1/-1}.grid>.btn{margin-top:16px}.btn{border:0;border-radius:8px;background:#B58E46;color:#1C242B;padding:10px 14px;font-weight:800;cursor:pointer}.alt{background:#fff;color:#6B2938;border:1px solid #6B2938}.good{background:#147a4b}.muted,.help{color:#6b6c78}.help{font-size:13px}.notice,.error,.warning{padding:12px;border-radius:9px}.notice{background:#dcfce7}.error{background:#fee2e2;color:#991b1b}.warning{background:#fff7d6;color:#6f5200}.record{border-top:1px solid #e4dfd7;padding:13px 0}.record summary{cursor:pointer;font-weight:850}.badge{display:inline-block;padding:3px 7px;border-radius:999px;background:#F4EEE2;color:#6B2938;font-size:11px;font-weight:800}.mapnode{border-left:4px solid #6B2938;padding:12px;background:#F4EEE2;margin:10px 0}.child{margin:8px 0 0 22px;padding:9px;background:#fff;border:1px solid #ddd;border-radius:8px}.audit{white-space:pre-wrap;background:#151827;color:#edf0ff;padding:16px;border-radius:10px;max-height:620px;overflow:auto;font:13px/1.5 ui-monospace,monospace}.asset{display:grid;grid-template-columns:120px 1fr auto;gap:12px;padding:12px 0;border-top:1px solid #ddd}.asset img{width:120px;height:85px;object-fit:cover;border-radius:8px}@media(max-width:800px){.workspace-layout,.layout,.grid{grid-template-columns:1fr}.workspace-side{position:static}.side{position:static}.wide{grid-column:auto}.tabs{flex-wrap:nowrap;overflow:auto}.tab{white-space:nowrap}}
.character-editor{display:grid;grid-template-columns:150px minmax(0,1fr);gap:16px;padding-top:14px}.character-editor-image,.character-image-empty{width:150px;height:180px;object-fit:cover;border-radius:10px;border:1px solid #d5cec1}.character-image-empty{display:grid;place-items:center;background:#F4EEE2;color:#64748b}.save-all{margin:18px 0}.media-toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px}.media-library{display:grid;gap:16px}.media-thumbnails{grid-template-columns:repeat(auto-fill,minmax(270px,1fr))}.media-item{border:1px solid #ddd6ca;border-radius:12px;overflow:hidden;background:#fbf8f1}.media-item>img{width:100%;height:190px;object-fit:cover}.media-item-body{padding:12px}.media-item details{margin-top:10px}.media-item summary{cursor:pointer;font-weight:800}.media-list .media-item{display:grid;grid-template-columns:180px 1fr}.media-list .media-item>img{height:100%;min-height:180px}.compact{margin-top:10px}.danger{border-color:#9f1239!important;color:#9f1239!important;margin-top:10px}.pagination{display:flex;flex-wrap:wrap;gap:6px;margin-top:18px}.pagination a{padding:7px 10px;border:1px solid #d5cec1;border-radius:7px;color:#1C242B;text-decoration:none}.pagination a.active{background:#B58E46;font-weight:800}.thumb-crop-picker{position:relative;margin:10px 0;cursor:crosshair;overflow:hidden;border:1px solid #d5cec1;border-radius:8px;background:#1C242B}.thumb-crop-picker img{display:block;width:100%;height:auto}.thumb-crop-box{position:absolute;border:2px solid #fff;box-shadow:0 0 0 999px rgba(28,36,43,.48),0 0 0 1px #6B2938 inset;pointer-events:none}@media(max-width:800px){.character-editor,.media-list .media-item{grid-template-columns:1fr}.media-toolbar{align-items:flex-start;flex-direction:column}}
</style>
<link rel="stylesheet" href="assets/toinky-theme.css">
<link rel="stylesheet" href="assets/toinky-admin-shell.css">
<link rel="stylesheet" href="assets/toinky-admin-interior.css">
<style>
.writer-media-panel{padding:0!important;overflow:hidden}.writer-media-panel>summary{list-style:none;display:flex;align-items:center;gap:10px;padding:13px 15px;cursor:pointer;font-weight:850}.writer-media-panel>summary::-webkit-details-marker{display:none}.writer-media-panel>summary::after{content:'+';margin-left:auto;font-size:18px}.writer-media-panel[open]>summary::after{content:'−'}.writer-media-panel-body{padding:0 14px 14px;border-top:1px solid #e2ded6}.media-toolbar{margin:10px 0}.media-library{gap:10px}.media-thumbnails{grid-template-columns:repeat(auto-fill,minmax(180px,1fr))}.media-item{border-radius:9px;font-size:12px}.media-item>img{height:112px}.media-item-body{padding:9px}.media-item details{margin-top:7px}.media-item summary{font-size:12px}.media-item .field{font-size:12px}.media-item .field input,.media-item .field select,.media-item .field textarea{padding:7px}.media-list .media-item{grid-template-columns:120px 1fr}.media-list .media-item>img{min-height:120px}.writer-media-count{color:#6b6c78;font-size:12px;font-weight:650}.media-page-size{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.media-page-size a{padding:5px 7px;border:1px solid #d5cec1;border-radius:5px;text-decoration:none}.media-page-size a.active{background:#1C242B;color:#F4EEE2}@media(max-width:800px){.media-list .media-item{grid-template-columns:90px 1fr}.media-list .media-item>img{min-height:100px}.media-thumbnails{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:520px){.media-thumbnails{grid-template-columns:1fr}}
</style>
</head>
<body><div class="workspace-layout"><?php require __DIR__.'/_workspace_sidebar.php';render_workspace_sidebar($writer,'writer',true);?><main class="workspace-main"><header class="workspace-page-header"><div><h1>Story Writer</h1><p>Signed in as <?=e($writer['display_name'])?></p></div></header>
<main class="shell">
<?php if($error):?>
<p class="error"><?=e($error)?>
</p>
<?php endif;?>

<?php if($notice):?>
<p class="notice"><?=e($notice)?>
</p>
<?php endif;?>
<div class="layout"><aside class="card side story-list"><h2>Your Stories</h2><div class="story-list-links">
<?php foreach($plots as$p):?>
<a class="link <?=((int)$p['plot_id']===$plotId)?'active':''?>
" href="?plot=<?=(int)$p['plot_id']?>
"><?=e($p['title'])?>
<br><small><?=e($p['workflow_status']??'DRAFT')?>
 · <?=e($p['genre_label']??$p['genre'])?>
</small></a>
<?php endforeach;?></div>
<details><summary><b>New Story</b></summary><form method="post"><?=writer_form_context(0,'overview','create')?>
<label class="field">Title<input name="title" required></label><label class="field">Code<input name="code"></label><label class="field">Genre<select name="genre_id">
<?php foreach($genres as$g):?>
<option value="<?=(int)$g['genre_id']?>
"><?=e($g['label_text'])?>
</option>
<?php endforeach;?>
</select></label><label class="field">Setting<input name="setting_name"></label><label class="field">Archetype<input name="archetype" value="Interactive Drama"></label><label class="field">Players<input type="number" name="max_players" min="1" value="3"></label><label class="field">AI cast<input type="number" name="max_npcs" min="0" value="3"></label><label class="field">Scenario<textarea name="scenario_text" rows="5"></textarea></label><button class="btn">Create Draft</button></form></details></aside><div>
<?php if($plot):?>
<section class="card"><span class="badge" style="float:right"><?=e($plot['workflow_status'])?>
</span><small>Guided Story Workspace</small><h1><?=e($plot['title'])?>
</h1><p class="muted">Required story data, dependencies, outcomes, and optional artwork remain in the database. PHP contains no plot-specific rules.</p></section><nav class="tabs">
<?php $writerTabs=['how_it_works'=>'How It Works','overview'=>'Overview','characters'=>'Characters','timeline'=>'Timeline','decisions'=>'Decisions','map'=>'Story Map','endings'=>'Endings','artwork'=>'Artwork','media'=>'Media'];if($isAdmin)$writerTabs['validation']='Submit for Review';foreach($writerTabs as$k=>$v):?>
<a class="tab <?=$tab===$k?'active':''?>
" href="?plot=<?=$plotId?>
&tab=<?=$k?>
"><?=e($v)?>
</a>
<?php endforeach;?>
</nav>


<?php if($tab==='how_it_works'):?>
<section class="card how-it-works"><h2>How Story Writer Works</h2><p>Build a shared world in layers. Keep public facts separate from private knowledge, and make every important choice change what happens next.</p>
<div class="grid"><div><h3>Workspace tabs</h3><dl><dt>Overview</dt><dd>Define the premise, genre, setting, cast size, and shared briefing without revealing private information.</dd><dt>Characters</dt><dd>Create each role’s public identity, private knowledge, goal, fear, starting location, and portrait.</dd><dt>Timeline</dt><dd>Place shared world events in chronological order. Events should advance the situation whether or not a player acts.</dd><dt>Decisions</dt><dd>Give a character a focused prompt, meaningful options, visible results, and mechanical effects. Include what happens when time runs out.</dd><dt>Story Map</dt><dd>Review dependencies, conditional opportunities, and causal paths so branches are intentional and reachable.</dd></dl></div><div><h3>Finishing the story</h3><dl><dt>Endings</dt><dd>Write condition-driven fragments covering the immediate result, character fate, wider aftermath, and a reason to replay.</dd><dt>Artwork</dt><dd>Place optional contextual images that support scenes without replacing required story information.</dd><dt>Media</dt><dd>Upload or reuse accessible image assets, including useful alternative text.</dd><dt>Submit for Review</dt><dd>Run validation and freeze the current story as a numbered revision for administrator and AI-assisted review.</dd></dl></div></div>
<h3>Make decisions interesting</h3><ul><li>Build dilemmas with real tradeoffs, not obvious good and bad answers.</li><li>Give each role only the information that character should know.</li><li>Let choices create visible narrative and mechanical consequences.</li><li>Use early details as later clues, leverage, risks, or reversals.</li><li>Allow branches to reconnect without erasing player agency.</li><li>Vary pacing between pressure, discovery, negotiation, and consequence.</li><li>Test every role, option, timeout, dependency, and ending condition.</li><li>Make the resolution reflect what players actually chose and protected.</li></ul></section>

<?php elseif($tab==='overview'):?>
<section class="card"><h2>Story Overview</h2><p class="help">Explain the shared situation without revealing private character knowledge.</p><form method="post" class="grid"><?=writer_form_context($plotId,$tab,'overview')?>
<label class="field">Title <span class="badge">Required</span><input name="title" value="<?=e($plot['title'])?>
" required></label><label class="field">Stable Code<input value="<?=e($plot['code'])?>
" disabled></label><label class="field">Genre<select name="genre_id">
<?php foreach($genres as$g):?>
<option value="<?=(int)$g['genre_id']?>
" <?=((int)$plot['genre_id']===(int)$g['genre_id'])?'selected':''?>
><?=e($g['label_text'])?>
</option>
<?php endforeach;?>
</select></label><label class="field">Archetype<input name="archetype" value="<?=e($plot['archetype'])?>
"></label><label class="field">Setting<input name="setting_name" value="<?=e($plot['setting_name'])?>
" required></label><label class="field">Human Players<input type="number" min="1" name="max_players" value="<?=(int)$plot['max_players']?>
"></label><label class="field">AI Characters<input type="number" min="0" name="max_npcs" value="<?=(int)$plot['max_npcs']?>
"></label><label class="field wide">Scenario Briefing <span class="badge">Required</span><textarea name="scenario_text" rows="8" required><?=e($plot['scenario_text'])?>
</textarea></label><button class="btn">Save Overview</button></form></section>


<?php elseif($tab==='characters'):?>
<section class="card"><h2>Characters</h2><p class="help">Public description is shared. Goal, fear, private knowledge, location, and portrait shape each character’s private experience.</p>
<?php if($w['characters']):?><form method="post"><?=writer_form_context($plotId,$tab,'characters_bulk')?>
<?php foreach($w['characters'] as$c):$prefix='characters['.(int)$c['plot_role_id'].']';$currentCharacterMedia=0;foreach($w['media'] as$cm)if($cm['placement']==='CHARACTER'&&$cm['role_code']===$c['role_code']&&(int)$cm['active']===1){$currentCharacterMedia=(int)($cm['library_media_id']??0);break;}?>
<details class="record"><summary><?=e($c['name'].' · '.$c['role_code'])?></summary>
<div class="character-editor"><?php if(isset($characterImages[$c['role_code']])):?><img class="character-editor-image" src="<?=e($characterImages[$c['role_code']]['file_path'])?>" alt="<?=e($characterImages[$c['role_code']]['alt_text'])?>"><?php else:?><div class="character-image-empty">No portrait</div><?php endif;?>
<div class="grid"><input type="hidden" name="<?=e($prefix)?>[plot_role_id]" value="<?=(int)$c['plot_role_id']?>">
<label class="field">Name<input name="<?=e($prefix)?>[name]" value="<?=e($c['name'])?>" required></label>
<label class="field">Role Code<input name="<?=e($prefix)?>[role_code]" value="<?=e($c['role_code'])?>" required></label>
<label class="field">Type<select name="<?=e($prefix)?>[role_type]"><option <?=$c['role_type']==='PLAYER'?'selected':''?>>PLAYER</option><option <?=$c['role_type']==='NPC'?'selected':''?>>NPC</option></select></label>
<label class="field">Order<input type="number" name="<?=e($prefix)?>[slot_no]" value="<?=(int)$c['slot_no']?>"></label>
<label class="field">Pronoun<select name="<?=e($prefix)?>[pronoun_set]"><?php foreach(['HE'=>'He','SHE'=>'She','IT'=>'It']as$k=>$v):?><option value="<?=$k?>" <?=$c['pronoun_set']===$k?'selected':''?>><?=$v?></option><?php endforeach;?></select></label>
<label class="field">Starting Location<input name="<?=e($prefix)?>[starting_location]" value="<?=e($c['starting_location'])?>" required></label>
<label class="field wide">Public Description<textarea name="<?=e($prefix)?>[public_description]" rows="3" required><?=e($c['public_description'])?></textarea></label>
<label class="field wide">Current Goal at Story Start<textarea name="<?=e($prefix)?>[goal_text]" rows="3" required><?=e($c['goal_text'])?></textarea></label>
<label class="field">Fear<textarea name="<?=e($prefix)?>[fear_text]" rows="3"><?=e($c['fear_text'])?></textarea></label>
<label class="field">Private Knowledge<textarea name="<?=e($prefix)?>[private_knowledge]" rows="3"><?=e($c['private_knowledge'])?></textarea></label>
<label class="field wide"><input style="width:auto;display:inline" type="checkbox" name="<?=e($prefix)?>[simulation_active]" value="1" <?=((int)$c['simulation_active']===1)?'checked':''?>> Include in new games</label>
<label class="field wide">Character Image<select name="<?=e($prefix)?>[character_media_id]"><option value="__KEEP__">Keep current image</option><option value="__REMOVE__">Remove image</option><?php foreach($libraryMedia as$m):if($m['media_type']!=='CHARACTER')continue;?><option value="<?=(int)$m['media_id']?>" <?=($currentCharacterMedia===(int)$m['media_id'])?'selected':''?>><?=e($m['title_text'])?></option><?php endforeach;?></select><span class="help">Only your own uploads are shown unless you are an administrator.</span></label>
</div></div></details><?php endforeach;?><button class="btn save-all">Save All Character Changes</button></form><?php endif;?>
<details class="record add-character" open><summary>Add Character</summary><form method="post" class="grid"><?=writer_form_context($plotId,$tab,'character')?><input type="hidden" name="plot_role_id" value="0">
<label class="field">Name<input name="name" required></label><label class="field">Role Code<input name="role_code" required></label>
<label class="field">Type<select name="role_type"><option>PLAYER</option><option>NPC</option></select></label><label class="field">Order<input type="number" name="slot_no" value="<?=count($w['characters'])+1?>"></label>
<label class="field">Pronoun<select name="pronoun_set"><option value="HE">He</option><option value="SHE">She</option><option value="IT">It</option></select></label>
<label class="field">Starting Location<input name="starting_location" required></label><label class="field wide">Public Description<textarea name="public_description" rows="3" required></textarea></label>
<label class="field wide">Current Goal at Story Start<textarea name="goal_text" rows="3" required></textarea></label><label class="field">Fear<textarea name="fear_text" rows="3"></textarea></label><label class="field">Private Knowledge<textarea name="private_knowledge" rows="3"></textarea></label>
<label class="field wide"><input style="width:auto;display:inline" type="checkbox" name="simulation_active" value="1" checked> Include in new games</label>
<label class="field wide">Character Image<select name="character_media_id"><option value="__KEEP__">Add image later</option><?php foreach($libraryMedia as$m):if($m['media_type']!=='CHARACTER')continue;?><option value="<?=(int)$m['media_id']?>"><?=e($m['title_text'])?></option><?php endforeach;?></select></label><button class="btn">Add Character</button></form></details>
</section>


<?php elseif($tab==='timeline'):?>
<section class="card"><h2>Chronological World Events</h2><p class="help">Decision prompts and images connect to these stable event codes.</p>
<?php foreach(array_merge($w['events'],[['plot_world_event_id'=>0,'event_second'=>0,'phase'=>'EXPOSITION','event_code'=>'','headline'=>'','event_text'=>'','sort_order'=>0,'active'=>1]])as$ev):?>
<details class="record" <?=empty($ev['plot_world_event_id'])?'open':''?>
><summary><?=empty($ev['plot_world_event_id'])?'Add Event':(int)$ev['event_second'].'s · '.e($ev['headline'])?>
</summary><form method="post" class="grid"><?=writer_form_context($plotId,$tab,'event')?>
<input type="hidden" name="plot_world_event_id" value="<?=(int)$ev['plot_world_event_id']?>
"><label class="field">Story Second<input type="number" min="0" name="event_second" value="<?=(int)$ev['event_second']?>
"></label><label class="field">Phase<select name="phase">
<?php foreach(['EXPOSITION','INCITING_INCIDENT','RISING_ACTION','CLIMAX','RESOLUTION']as$p):?>
<option <?=$ev['phase']===$p?'selected':''?>
><?=$p?>
</option>
<?php endforeach;?>
</select></label><label class="field">Event Code<input name="event_code" value="<?=e($ev['event_code'])?>
" required></label><label class="field">Headline<input name="headline" value="<?=e($ev['headline'])?>
" required></label><label class="field wide">Player-Facing Prose<textarea name="event_text" rows="5" required><?=e($ev['event_text'])?>
</textarea></label><label class="field">Order<input type="number" name="sort_order" value="<?=(int)$ev['sort_order']?>
"></label><label class="field"><input style="width:auto;display:inline" type="checkbox" name="active" value="1" <?=((int)$ev['active']===1)?'checked':''?>
> Active</label><button class="btn"><?=empty($ev['plot_world_event_id'])?'Add':'Save'?>
 Event</button></form></details>
<?php endforeach;?>
</section>


<?php elseif($tab==='decisions'):?>
<section class="card"><h2>Decisions, Options, and Effects</h2><p class="help">The chain is event → character decision → option → ordered effects. A set value changes state; a delta changes a numeric factor or stat.</p>
<?php foreach(array_merge($w['decisions'],[['template_id'=>0,'event_code'=>'','role_code'=>'','actor_type'=>'PLAYER','prompt_text'=>'','decision_window_seconds'=>30,'option_count'=>0]])as$d):?>
<details class="record" <?=empty($d['template_id'])?'open':''?>
><summary><?=empty($d['template_id'])?'Add Decision':e($d['event_code'].' · '.$d['role_code']).' · '.(int)$d['option_count'].' options'?>
</summary><form method="post" class="grid"><?=writer_form_context($plotId,$tab,'decision')?>
<input type="hidden" name="template_id" value="<?=(int)$d['template_id']?>
"><label class="field">Event<select name="event_code">
<?php foreach($w['events']as$ev):?>
<option value="<?=e($ev['event_code'])?>
" <?=$d['event_code']===$ev['event_code']?'selected':''?>
><?=e($ev['headline'])?>
</option>
<?php endforeach;?>
</select></label><label class="field">Character<select name="role_code">
<?php foreach($w['characters']as$c):?>
<option value="<?=e($c['role_code'])?>
" <?=$d['role_code']===$c['role_code']?'selected':''?>
><?=e($c['name'])?>
</option>
<?php endforeach;?>
</select></label><label class="field">Controller<select name="actor_type"><option <?=$d['actor_type']==='PLAYER'?'selected':''?>
>PLAYER</option><option <?=$d['actor_type']==='NPC'?'selected':''?>
>NPC</option></select></label><label class="field">Window Seconds<input type="number" min="5" name="decision_window_seconds" value="<?=(int)$d['decision_window_seconds']?>
"></label><label class="field wide">Prompt<textarea name="prompt_text" rows="4" required><?=e($d['prompt_text'])?>
</textarea></label><button class="btn"><?=empty($d['template_id'])?'Add':'Save'?>
 Decision</button></form>
<?php if(!empty($d['template_id'])):?>

<?php foreach($w['options']as$o): if((int)$o['template_id']===(int)$d['template_id']):?>
<details class="record"><summary>Option: <?=e($o['label'])?>
</summary><form method="post" class="grid"><?=writer_form_context($plotId,$tab,'option')?>
<input type="hidden" name="template_id" value="<?=(int)$d['template_id']?>
"><input type="hidden" name="template_option_id" value="<?=(int)$o['template_option_id']?>
"><label class="field">Code<input name="option_code" value="<?=e($o['option_code'])?>
" required></label><label class="field">Label<input name="label" value="<?=e($o['label'])?>
" required></label><label class="field">AI Weight<input type="number" name="npc_weight" value="<?=(int)$o['npc_weight']?>
"></label><label class="field">Order<input type="number" name="sort_order" value="<?=(int)$o['sort_order']?>
"></label><label class="field wide">AI Rationale Guidance<textarea name="rationale_text"><?=e($o['rationale_text'])?>
</textarea></label><label class="field wide">Player-Facing Result<textarea name="result_text" rows="4" required><?=e($o['result_text'])?>
</textarea></label><button class="btn">Save Option</button></form>
<?php foreach($w['effects']as$x): if((int)$x['template_option_id']===(int)$o['template_option_id']):?>
<form method="post" class="grid child"><?=writer_form_context($plotId,$tab,'effect')?>
<input type="hidden" name="template_option_id" value="<?=(int)$o['template_option_id']?>
"><input type="hidden" name="effect_id" value="<?=(int)$x['effect_id']?>
"><label class="field">Effect Scope<select name="effect_scope">
<?php foreach(['WORLD_STATE','FACTOR','STAT','LOCATION','KNOWLEDGE','RELATIONSHIP','GOAL','FEAR']as$s):?>
<option <?=$x['effect_scope']===$s?'selected':''?>
><?=$s?>
</option>
<?php endforeach;?>
</select></label><label class="field">Target Code<input name="target_code" value="<?=e($x['target_code'])?>
" required></label><label class="field">Delta<input type="number" name="delta_value" value="<?=e($x['delta_value'])?>
"></label><label class="field">Set Value<input name="set_value" value="<?=e($x['set_value'])?>
"></label><label class="field wide">Narrative Detail<input name="detail_text" value="<?=e($x['detail_text'])?>
"></label><label class="field">Order<input type="number" name="sort_order" value="<?=(int)$x['sort_order']?>
"></label><button class="btn alt">Save Effect</button></form>
<?php endif; endforeach;?>
<details class="child"><summary>Add Effect</summary><form method="post" class="grid"><?=writer_form_context($plotId,$tab,'effect')?>
<input type="hidden" name="template_option_id" value="<?=(int)$o['template_option_id']?>
"><label class="field">Scope<select name="effect_scope">
<?php foreach(['WORLD_STATE','FACTOR','STAT','LOCATION','KNOWLEDGE','RELATIONSHIP','GOAL','FEAR']as$s):?>
<option><?=$s?>
</option>
<?php endforeach;?>
</select></label><label class="field">Target Code<input name="target_code" required></label><label class="field">Delta<input type="number" name="delta_value"></label><label class="field">Set Value<input name="set_value"></label><label class="field wide">Narrative Detail<input name="detail_text"></label><label class="field">Order<input type="number" name="sort_order" value="0"></label><button class="btn">Add Effect</button></form></details></details>
<?php endif; endforeach;?>
<details class="record"><summary>Add Option</summary><form method="post" class="grid"><?=writer_form_context($plotId,$tab,'option')?>
<input type="hidden" name="template_id" value="<?=(int)$d['template_id']?>
"><label class="field">Code<input name="option_code" required></label><label class="field">Label<input name="label" required></label><label class="field">AI Weight<input type="number" name="npc_weight" value="0"></label><label class="field">Order<input type="number" name="sort_order" value="0"></label><label class="field wide">AI Rationale Guidance<textarea name="rationale_text"></textarea></label><label class="field wide">Player-Facing Result<textarea name="result_text" rows="4" required></textarea></label><button class="btn">Add Option</button></form></details>
<?php endif;?>
</details>
<?php endforeach;?>
</section>


<?php elseif($tab==='map'):?>
<section class="card"><h2>Story Map and Dependencies</h2><p class="help">Read each branch from top to bottom: world event → character decision → option → ordered effects. Conditional opportunities open only when every authored condition passes.</p>
<?php foreach($w['events']as$ev):?>
<div class="mapnode"><b><?=(int)$ev['event_second']?>
s · <?=e($ev['headline'])?>
</b><br><small><?=e($ev['phase'].' · '.$ev['event_code'])?>
</small>
<?php foreach($w['decisions']as$d): if($d['event_code']===$ev['event_code']):?>
<div class="child"><b><?=e($d['role_code'])?>
 decides:</b> <?=e($d['prompt_text'])?>

<?php foreach($w['options']as$o): if((int)$o['template_id']===(int)$d['template_id']):?>
<div class="child">↳ <b><?=e($o['label'])?>
</b> → <?=e($o['result_text'])?>

<?php foreach($w['effects']as$x): if((int)$x['template_option_id']===(int)$o['template_option_id']):?>
<br><small>then <?=e($x['effect_scope'].' '.$x['target_code'].' '.($x['set_value']!==null?'becomes '.$x['set_value']:'changes by '.$x['delta_value']))?>
</small>
<?php endif; endforeach;?>
</div>
<?php endif; endforeach;?>
</div>
<?php endif; endforeach;?>
</div>
<?php endforeach;?>
</section><section class="card"><h2>Conditional Opportunities</h2>
<?php foreach(array_merge($w['opportunities'],[['dynamic_opportunity_id'=>0,'opportunity_code'=>'','role_code'=>'','prompt_text'=>'','window_seconds'=>35,'once_only'=>1,'priority'=>100,'active'=>1]])as$o):?>
<details class="record" <?=empty($o['dynamic_opportunity_id'])?'open':''?>
><summary><?=empty($o['dynamic_opportunity_id'])?'Add Conditional Opportunity':e($o['role_code'].' · '.$o['opportunity_code'])?>
</summary><form method="post" class="grid"><?=writer_form_context($plotId,$tab,'opportunity')?>
<input type="hidden" name="dynamic_opportunity_id" value="<?=(int)$o['dynamic_opportunity_id']?>
"><label class="field">Code<input name="opportunity_code" value="<?=e($o['opportunity_code'])?>
" required></label><label class="field">Character<select name="role_code">
<?php foreach($w['characters']as$c):?>
<option value="<?=e($c['role_code'])?>
" <?=$o['role_code']===$c['role_code']?'selected':''?>
><?=e($c['name'])?>
</option>
<?php endforeach;?>
</select></label><label class="field">Window Seconds<input type="number" min="5" name="window_seconds" value="<?=(int)$o['window_seconds']?>
"></label><label class="field">Priority<input type="number" name="priority" value="<?=(int)$o['priority']?>
"></label><label class="field wide">Prompt<textarea name="prompt_text" rows="4" required><?=e($o['prompt_text'])?>
</textarea></label><label class="field"><input style="width:auto;display:inline" type="checkbox" name="once_only" value="1" <?=((int)$o['once_only']===1)?'checked':''?>
> Open only once</label><label class="field"><input style="width:auto;display:inline" type="checkbox" name="active" value="1" <?=((int)$o['active']===1)?'checked':''?>
> Active</label><button class="btn"><?=empty($o['dynamic_opportunity_id'])?'Add':'Save'?>
 Opportunity</button></form>
<?php if(!empty($o['dynamic_opportunity_id'])):?>
<h4>Every condition below must pass</h4>
<?php foreach($w['dynamic_conditions']as$c): if((int)$c['dynamic_opportunity_id']===(int)$o['dynamic_opportunity_id']):?>
<form method="post" class="grid child"><?=writer_form_context($plotId,$tab,'dynamic_condition')?>
<input type="hidden" name="dynamic_opportunity_id" value="<?=(int)$o['dynamic_opportunity_id']?>
"><input type="hidden" name="dynamic_condition_id" value="<?=(int)$c['dynamic_condition_id']?>
"><label class="field">Source<select name="source_type">
<?php foreach(['WORLD_STATE','FACTOR','LOCATION','GOAL','FEAR','STAT','KNOWLEDGE','RELATIONSHIP','PHASE','ACTION_COUNT','PLAYER_ACTION_COUNT','OPTION_CHOICE_COUNT']as$s):?>
<option <?=$c['source_type']===$s?'selected':''?>
><?=$s?>
</option>
<?php endforeach;?>
</select></label><label class="field">Key<input name="source_key" value="<?=e($c['source_key'])?>
"></label><label class="field">Operator<select name="operator">
<?php foreach(['EQ','NEQ','IN','NOT_IN','EXISTS','NOT_EXISTS','LT','LTE','GT','GTE']as$op):?>
<option <?=$c['operator']===$op?'selected':''?>
><?=$op?>
</option>
<?php endforeach;?>
</select></label><label class="field">Value<input name="compare_value" value="<?=e($c['compare_value'])?>
"></label><label class="field">Order<input type="number" name="sort_order" value="<?=(int)$c['sort_order']?>
"></label><button class="btn alt">Save Condition</button></form>
<?php endif; endforeach;?>
<details class="child"><summary>Add Condition</summary><form method="post" class="grid"><?=writer_form_context($plotId,$tab,'dynamic_condition')?>
<input type="hidden" name="dynamic_opportunity_id" value="<?=(int)$o['dynamic_opportunity_id']?>
"><label class="field">Source<select name="source_type">
<?php foreach(['WORLD_STATE','FACTOR','LOCATION','GOAL','FEAR','STAT','KNOWLEDGE','RELATIONSHIP','PHASE','ACTION_COUNT','PLAYER_ACTION_COUNT','OPTION_CHOICE_COUNT']as$s):?>
<option><?=$s?>
</option>
<?php endforeach;?>
</select></label><label class="field">Key<input name="source_key"></label><label class="field">Operator<select name="operator">
<?php foreach(['EQ','NEQ','IN','NOT_IN','EXISTS','NOT_EXISTS','LT','LTE','GT','GTE']as$op):?>
<option><?=$op?>
</option>
<?php endforeach;?>
</select></label><label class="field">Value<input name="compare_value"></label><label class="field">Order<input type="number" name="sort_order" value="0"></label><button class="btn">Add Condition</button></form></details><h4>Choices when this opportunity opens</h4>
<?php foreach($w['dynamic_options']as$x): if((int)$x['dynamic_opportunity_id']===(int)$o['dynamic_opportunity_id']):?>
<details class="child"><summary><?=e($x['label'])?>
</summary><form method="post" class="grid"><?=writer_form_context($plotId,$tab,'dynamic_option')?>
<input type="hidden" name="dynamic_opportunity_id" value="<?=(int)$o['dynamic_opportunity_id']?>
"><input type="hidden" name="dynamic_option_id" value="<?=(int)$x['dynamic_option_id']?>
"><label class="field">Code<input name="option_code" value="<?=e($x['option_code'])?>
" required></label><label class="field">Label<input name="label" value="<?=e($x['label'])?>
" required></label><label class="field wide">Result<textarea name="result_text" rows="3" required><?=e($x['result_text'])?>
</textarea></label><label class="field">Order<input type="number" name="sort_order" value="<?=(int)$x['sort_order']?>
"></label><button class="btn alt">Save Choice</button></form>
<?php foreach($w['dynamic_effects']as$de): if((int)$de['dynamic_option_id']===(int)$x['dynamic_option_id']):?>
<form method="post" class="grid child"><?=writer_form_context($plotId,$tab,'dynamic_effect')?>
<input type="hidden" name="dynamic_option_id" value="<?=(int)$x['dynamic_option_id']?>
"><input type="hidden" name="dynamic_effect_id" value="<?=(int)$de['dynamic_effect_id']?>
"><label class="field">Scope<select name="effect_scope">
<?php foreach(['WORLD_STATE','FACTOR','STAT','LOCATION','KNOWLEDGE','RELATIONSHIP','GOAL','FEAR']as$s):?>
<option <?=$de['effect_scope']===$s?'selected':''?>
><?=$s?>
</option>
<?php endforeach;?>
</select></label><label class="field">Target<input name="target_code" value="<?=e($de['target_code'])?>
" required></label><label class="field">Delta<input type="number" name="delta_value" value="<?=e($de['delta_value'])?>
"></label><label class="field">Set Value<input name="set_value" value="<?=e($de['set_value'])?>
"></label><label class="field wide">Narrative Detail<input name="detail_text" value="<?=e($de['detail_text'])?>
"></label><label class="field">Order<input type="number" name="sort_order" value="<?=(int)$de['sort_order']?>
"></label><button class="btn alt">Save Effect</button></form>
<?php endif; endforeach;?>
<details><summary>Add Effect</summary><form method="post" class="grid"><?=writer_form_context($plotId,$tab,'dynamic_effect')?>
<input type="hidden" name="dynamic_option_id" value="<?=(int)$x['dynamic_option_id']?>
"><label class="field">Scope<select name="effect_scope">
<?php foreach(['WORLD_STATE','FACTOR','STAT','LOCATION','KNOWLEDGE','RELATIONSHIP','GOAL','FEAR']as$s):?>
<option><?=$s?>
</option>
<?php endforeach;?>
</select></label><label class="field">Target<input name="target_code" required></label><label class="field">Delta<input type="number" name="delta_value"></label><label class="field">Set Value<input name="set_value"></label><label class="field wide">Narrative Detail<input name="detail_text"></label><label class="field">Order<input type="number" name="sort_order" value="0"></label><button class="btn">Add Effect</button></form></details></details>
<?php endif; endforeach;?>
<details class="child"><summary>Add Choice</summary><form method="post" class="grid"><?=writer_form_context($plotId,$tab,'dynamic_option')?>
<input type="hidden" name="dynamic_opportunity_id" value="<?=(int)$o['dynamic_opportunity_id']?>
"><label class="field">Code<input name="option_code" required></label><label class="field">Label<input name="label" required></label><label class="field wide">Result<textarea name="result_text" required></textarea></label><label class="field">Order<input type="number" name="sort_order" value="0"></label><button class="btn">Add Choice</button></form></details>
<?php endif;?>
</details>
<?php endforeach;?>
</section>


<?php elseif($tab==='endings'):?>
<section class="card"><h2>Outcomes and Epilogue</h2><p class="help">Write immediate consequences, named-character fates, wider aftermath, and a lingering invitation to replay. Every condition beneath a fragment must pass before it appears.</p>
<?php foreach(array_merge($w['resolutions'],[['resolution_fragment_id'=>0,'section_code'=>'AFTERMATH','section_order'=>80,'priority'=>100,'fragment_text'=>'','active'=>1]])as$r):?>
<details class="record" <?=empty($r['resolution_fragment_id'])?'open':''?>
><summary><?=empty($r['resolution_fragment_id'])?'Add Ending Fragment':e($r['section_code']).' · priority '.(int)$r['priority']?>
</summary><form method="post" class="grid"><?=writer_form_context($plotId,$tab,'resolution')?>
<input type="hidden" name="resolution_fragment_id" value="<?=(int)$r['resolution_fragment_id']?>
"><label class="field">Section Code<input name="section_code" value="<?=e($r['section_code'])?>
" required></label><label class="field">Section Order<input type="number" name="section_order" value="<?=(int)$r['section_order']?>
"></label><label class="field">Priority<input type="number" name="priority" value="<?=(int)$r['priority']?>
"></label><label class="field"><input style="width:auto;display:inline" type="checkbox" name="active" value="1" <?=((int)$r['active']===1)?'checked':''?>
> Active</label><label class="field wide">Narrative Fragment<textarea name="fragment_text" rows="7" required><?=e($r['fragment_text'])?>
</textarea></label><button class="btn"><?=empty($r['resolution_fragment_id'])?'Add':'Save'?>
 Fragment</button></form>
<?php if(!empty($r['resolution_fragment_id'])):?>
<h4>Outcome Conditions</h4>
<?php foreach($w['resolution_conditions']as$c): if((int)$c['resolution_fragment_id']===(int)$r['resolution_fragment_id']):?>
<form method="post" class="grid child"><?=writer_form_context($plotId,$tab,'resolution_condition')?>
<input type="hidden" name="resolution_fragment_id" value="<?=(int)$r['resolution_fragment_id']?>
"><input type="hidden" name="resolution_condition_id" value="<?=(int)$c['resolution_condition_id']?>
"><label class="field">State Key<input name="state_key" value="<?=e($c['state_key'])?>
" required></label><label class="field">Operator<select name="operator">
<?php foreach(['EQ','NEQ','IN','NOT_IN','EXISTS','NOT_EXISTS','LT','LTE','GT','GTE']as$op):?>
<option <?=$c['operator']===$op?'selected':''?>
><?=$op?>
</option>
<?php endforeach;?>
</select></label><label class="field">Value<input name="compare_value" value="<?=e($c['compare_value'])?>
"></label><label class="field">Order<input type="number" name="sort_order" value="<?=(int)$c['sort_order']?>
"></label><button class="btn alt">Save Condition</button></form>
<?php endif; endforeach;?>
<details class="child"><summary>Add Outcome Condition</summary><form method="post" class="grid"><?=writer_form_context($plotId,$tab,'resolution_condition')?>
<input type="hidden" name="resolution_fragment_id" value="<?=(int)$r['resolution_fragment_id']?>
"><label class="field">State Key<input name="state_key" required></label><label class="field">Operator<select name="operator">
<?php foreach(['EQ','NEQ','IN','NOT_IN','EXISTS','NOT_EXISTS','LT','LTE','GT','GTE']as$op):?>
<option><?=$op?>
</option>
<?php endforeach;?>
</select></label><label class="field">Value<input name="compare_value"></label><label class="field">Order<input type="number" name="sort_order" value="0"></label><button class="btn">Add Condition</button></form></details>
<?php endif;?>
</details>
<?php endforeach;?>
</section>


<?php elseif($tab==='artwork'):?>
<section class="card"><h2>Story Artwork</h2><p class="help">Choose one Primary image as the story's visual identifier in public story cards and listings. You may also connect images to characters, openings, events, turning points, outcomes, and endings. Older stories without a Primary image continue to use their first available scene image.</p><form method="post" class="grid"><?=writer_form_context($plotId,$tab,'attach_media')?>
<label class="field">Placement<select name="placement" id="story-placement">
<?php foreach(['PRIMARY'=>'Primary Story Image','CHARACTER'=>'Character','OPENING'=>'Opening','SCENE'=>'Scene','TURNING_POINT'=>'Turning Point','OUTCOME'=>'Outcome','ENDING'=>'Ending','OTHER'=>'Other']as$p=>$placementLabel):?>
<option value="<?=e($p)?>"><?=e($placementLabel)?>
</option>
<?php endforeach;?>
</select></label><label class="field">Library Image<select name="library_media_id" id="story-library-media" required><option value="">Choose an image</option><?php foreach($libraryMedia as$m):?><option value="<?=(int)$m['media_id']?>" data-media-type="<?=e($m['media_type'])?>"><?=e($m['title_text'].' · '.$m['media_type'])?></option><?php endforeach;?></select><span class="help">Only media matching the placement is suggested. The list is restricted to images you uploaded; administrators can use any active image.</span></label><label class="field">Asset Code<input name="asset_code"></label><label class="field">Character<select name="role_code"><option value="">Any</option>
<?php foreach($w['characters']as$c):?>
<option value="<?=e($c['role_code'])?>
"><?=e($c['name'])?>
</option>
<?php endforeach;?>
</select></label><label class="field">Event<select name="event_code"><option value="">None</option>
<?php foreach($w['events']as$ev):?>
<option value="<?=e($ev['event_code'])?>
"><?=e($ev['headline'])?>
</option>
<?php endforeach;?>
</select></label><label class="field">Story Second<input type="number" min="0" name="scene_second"></label><label class="field">Outcome State Key<input name="condition_state_key"></label><label class="field">Condition<select name="condition_operator"><option value="">None</option>
<?php foreach(['EQ','NEQ','IN','NOT_IN','EXISTS','NOT_EXISTS']as$o):?>
<option><?=$o?>
</option>
<?php endforeach;?>
</select></label><label class="field">Condition Value<input name="condition_value"></label><label class="field">Title Override<input name="title_text"></label><label class="field">Order<input type="number" name="sort_order" value="100"></label><label class="field wide">Caption<textarea name="caption_text"></textarea></label><button class="btn">Add Library Image</button></form></section><section class="card">
<?php if(!$w['media']):?>
<p>No artwork yet. That is okay.</p>
<?php endif;?>

<?php foreach($w['media']as$m):?>
<div class="asset"><img src="<?=e($m['file_path'])?>
" alt="<?=e($m['alt_text'])?>
"><div><b><?=e($m['title_text']?:$m['asset_code'])?>
</b><br><small><?=e($m['placement'].' · '.$m['alt_text'])?>
</small></div>
<?php if((int)$m['active']===1):?>
<form method="post"><?=writer_form_context($plotId,$tab,'deactivate')?>
<input type="hidden" name="media_asset_id" value="<?=(int)$m['media_asset_id']?>
"><button class="btn alt">Hide</button></form>
<?php endif;?>
</div>
<?php endforeach;?>
</section>

<?php elseif($tab==='media'):?>
<details class="card writer-media-panel"><summary>Upload New Media</summary><div class="writer-media-panel-body"><p class="help">Upload JPEG, PNG, or WebP files up to 10 MB. Only your uploads appear here; administrators can manage all active media.</p>
<form method="post" enctype="multipart/form-data" class="grid"><?=writer_form_context($plotId,$tab,'library_upload')?>
<label class="field">Media Type<select name="media_type"><?php foreach(MediaLibraryService::TYPES as$type):?><option><?=e($type)?></option><?php endforeach;?></select></label>
<label class="field">Title<input name="title_text" maxlength="190" required></label><label class="field wide">Alternative Text<input name="alt_text" maxlength="500" required></label>
<label class="field wide">Image<input type="file" name="image" accept="image/jpeg,image/png,image/webp" required></label><button class="btn">Upload to Media Library</button></form></div></details>
<details class="card writer-media-panel" open><summary>Media Library <span class="writer-media-count"><?=(int)$mediaPagination['total']?> item<?=(int)$mediaPagination['total']===1?'':'s'?></span></summary><div class="writer-media-panel-body"><div class="media-toolbar"><div><a class="btn <?=$mediaView==='thumbnail'?'':'alt'?>" href="?plot=<?=$plotId?>&tab=media&media_view=thumbnail&per_page=<?=$mediaPerPage?>">Thumbnail</a> <a class="btn <?=$mediaView==='list'?'':'alt'?>" href="?plot=<?=$plotId?>&tab=media&media_view=list&per_page=<?=$mediaPerPage?>">List</a></div><div class="media-page-size"><span>Show</span><?php foreach([25,50,100]as$pageSize):?><a class="<?=$mediaPerPage===$pageSize?'active':''?>" href="?plot=<?=$plotId?>&tab=media&media_view=<?=e($mediaView)?>&per_page=<?=$pageSize?>"><?=$pageSize?></a><?php endforeach;?></div></div>
<?php if(!$mediaPagination['items']):?><p>No media yet. Upload an image above, then connect it from Artwork or Characters.</p><?php endif;?>
<div class="media-library <?=$mediaView==='list'?'media-list':'media-thumbnails'?>"><?php foreach($mediaPagination['items'] as$m):?>
<article class="media-item"><img src="<?=e($mediaView==='thumbnail'&&!empty($m['thumbnail_path'])?$m['thumbnail_path']:$m['file_path'])?>" alt="<?=e($m['alt_text'])?>"><div class="media-item-body"><b><?=e($m['title_text'])?></b><br><small><?=e($m['media_type'].' · '.$m['width_px'].'×'.$m['height_px'])?></small><?php if($isAdmin):?><br><small>Uploaded by <?=e($m['uploader_name']??'System')?></small><?php endif;?>
<details><summary>Edit details</summary><form method="post" class="grid compact"><?=writer_form_context($plotId,$tab,'library_update')?><input type="hidden" name="media_id" value="<?=(int)$m['media_id']?>"><input type="hidden" name="media_view" value="<?=e($mediaView)?>"><input type="hidden" name="per_page" value="<?=$mediaPerPage?>"><input type="hidden" name="media_page" value="<?=(int)$mediaPagination['page']?>">
<label class="field">Media Type<select name="media_type"><?php foreach(MediaLibraryService::TYPES as$type):?><option <?=$m['media_type']===$type?'selected':''?>><?=e($type)?></option><?php endforeach;?></select></label><label class="field">Title<input name="title_text" maxlength="190" value="<?=e($m['title_text'])?>" required></label><label class="field wide">Alternative Text<input name="alt_text" maxlength="500" value="<?=e($m['alt_text'])?>" required></label><button class="btn">Save Details</button></form></details>
<details><summary>Crop or resize</summary><form method="post" class="grid compact"><?=writer_form_context($plotId,$tab,'library_process')?><input type="hidden" name="media_id" value="<?=(int)$m['media_id']?>"><input type="hidden" name="media_view" value="<?=e($mediaView)?>"><input type="hidden" name="per_page" value="<?=$mediaPerPage?>"><input type="hidden" name="media_page" value="<?=(int)$mediaPagination['page']?>">
<label class="field">Crop X<input type="number" min="0" name="crop_x" value="0"></label><label class="field">Crop Y<input type="number" min="0" name="crop_y" value="0"></label><label class="field">Crop Width<input type="number" min="1" max="<?=(int)$m['width_px']?>" name="crop_width" value="<?=(int)$m['width_px']?>"></label><label class="field">Crop Height<input type="number" min="1" max="<?=(int)$m['height_px']?>" name="crop_height" value="<?=(int)$m['height_px']?>"></label><label class="field">Resize Width<input type="number" min="1" max="1920" name="resize_width" placeholder="Keep crop width"></label><label class="field">Resize Height<input type="number" min="1" max="1920" name="resize_height" placeholder="Keep crop height"></label><button class="btn">Apply Crop / Resize</button></form></details>
<details><summary>Recreate 150×150 thumbnail</summary><p class="help">Choose a square size, then click the face or subject to center the crop. You can fine-tune the coordinates before saving.</p><form method="post" class="grid compact" data-thumb-form data-image-width="<?=(int)$m['width_px']?>" data-image-height="<?=(int)$m['height_px']?>"><?=writer_form_context($plotId,$tab,'library_thumbnail')?><input type="hidden" name="media_id" value="<?=(int)$m['media_id']?>"><input type="hidden" name="media_view" value="<?=e($mediaView)?>"><input type="hidden" name="per_page" value="<?=$mediaPerPage?>"><input type="hidden" name="media_page" value="<?=(int)$mediaPagination['page']?>"><div class="thumb-crop-picker wide" data-thumb-picker><img src="<?=e($m['file_path'])?>" alt=""><span class="thumb-crop-box" data-thumb-box></span></div><label class="field">Crop X<input type="number" min="0" max="<?=(int)$m['width_px']-1?>" name="thumbnail_crop_x" value="<?=(int)($m['thumbnail_crop_x']??0)?>"></label><label class="field">Crop Y<input type="number" min="0" max="<?=(int)$m['height_px']-1?>" name="thumbnail_crop_y" value="<?=(int)($m['thumbnail_crop_y']??0)?>"></label><label class="field wide">Square Crop Size<input type="number" min="1" max="<?=min((int)$m['width_px'],(int)$m['height_px'])?>" name="thumbnail_crop_size" value="<?=(int)($m['thumbnail_crop_size']??min((int)$m['width_px'],(int)$m['height_px']))?>"></label><button class="btn">Recreate Thumbnail</button></form></details>
<form method="post" onsubmit="return confirm('Delete this media file? It will be removed from stories and themes that use it.')"><?=writer_form_context($plotId,$tab,'library_delete')?><input type="hidden" name="media_id" value="<?=(int)$m['media_id']?>"><input type="hidden" name="media_view" value="<?=e($mediaView)?>"><input type="hidden" name="per_page" value="<?=$mediaPerPage?>"><input type="hidden" name="media_page" value="<?=(int)$mediaPagination['page']?>"><button class="btn alt danger">Delete</button></form>
</div></article><?php endforeach;?></div>
<?php if((int)$mediaPagination['pages']>1):?><nav class="pagination" aria-label="Media pages"><?php for($pageNo=1;$pageNo<=(int)$mediaPagination['pages'];$pageNo++):?><a class="<?=$pageNo===(int)$mediaPagination['page']?'active':''?>" href="?plot=<?=$plotId?>&tab=media&media_view=<?=e($mediaView)?>&per_page=<?=$mediaPerPage?>&media_page=<?=$pageNo?>"><?=$pageNo?></a><?php endfor;?></nav><?php endif;?>
</div></details>


<?php else:?>
<section class="card"><h2>Submit for Review</h2><p>Submission freezes the current content into a numbered revision. Administrators review that exact revision.</p>
<?php if($audit['errors']):?>
<div class="error"><b><?=count($audit['errors'])?>
 blocking issue(s)</b><ul>
<?php foreach($audit['errors']as$x):?>
<li><?=e($x)?>
</li>
<?php endforeach;?>
</ul></div>
<?php else:?>
<div class="notice">Automated structural validation passed.</div>
<?php endif;?>

<?php if($audit['warnings']):?>
<div class="warning"><b><?=count($audit['warnings'])?>
 warning(s)</b><ul>
<?php foreach($audit['warnings']as$x):?>
<li><?=e($x)?>
</li>
<?php endforeach;?>
</ul></div>
<?php endif;?>
<form method="post" style="margin-top:18px"><?=writer_form_context($plotId,$tab,'submit')?>
<button class="btn good" <?=$audit['errors']?'disabled':''?>
>Submit Fixed Revision</button></form>
</section>
<?php endif;?>


<?php else:?>
<section class="card"><h1>Create Your First Story</h1><p>Use “New Story” to begin. The studio will guide you through every required dependency.</p></section>
<?php endif;?>
</div></div></main><script>
const placement=document.querySelector('#story-placement'),mediaSelect=document.querySelector('#story-library-media');
if(placement&&mediaSelect){const options=[...mediaSelect.options].map(option=>option.cloneNode(true));const filter=()=>{const selected=mediaSelect.value;mediaSelect.replaceChildren(...options.filter((option,index)=>index===0||placement.value==='OTHER'||option.dataset.mediaType===placement.value).map(option=>option.cloneNode(true)));if([...mediaSelect.options].some(option=>option.value===selected))mediaSelect.value=selected;};placement.addEventListener('change',filter);filter();}
document.querySelectorAll('[data-thumb-form]').forEach(form=>{const picker=form.querySelector('[data-thumb-picker]'),image=picker?.querySelector('img'),box=picker?.querySelector('[data-thumb-box]'),xInput=form.elements.thumbnail_crop_x,yInput=form.elements.thumbnail_crop_y,sizeInput=form.elements.thumbnail_crop_size,width=Number(form.dataset.imageWidth),height=Number(form.dataset.imageHeight);if(!picker||!image||!box)return;const render=()=>{const scale=image.clientWidth/width,size=Math.min(Number(sizeInput.value)||1,width,height),x=Math.max(0,Math.min(Number(xInput.value)||0,width-size)),y=Math.max(0,Math.min(Number(yInput.value)||0,height-size));xInput.value=Math.round(x);yInput.value=Math.round(y);box.style.left=(x*scale)+'px';box.style.top=(y*scale)+'px';box.style.width=(size*scale)+'px';box.style.height=(size*scale)+'px';};picker.addEventListener('click',event=>{const rect=image.getBoundingClientRect(),scale=width/rect.width,size=Math.min(Number(sizeInput.value)||1,width,height),centerX=(event.clientX-rect.left)*scale,centerY=(event.clientY-rect.top)*scale;xInput.value=Math.round(Math.max(0,Math.min(centerX-size/2,width-size)));yInput.value=Math.round(Math.max(0,Math.min(centerY-size/2,height-size)));render();});[xInput,yInput,sizeInput].forEach(input=>input.addEventListener('input',render));image.addEventListener('load',render);window.addEventListener('resize',render);render();});
</script></main></div></body></html>
