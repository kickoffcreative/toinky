<?php
if(!function_exists('render_inky')){
function render_inky(array $user,array $context=[]):void
{
    $preferences=UserProfileService::inkyPreferences((int)$user['user_id']);if(!(int)$preferences['enabled'])return;$displayName=UserProfileService::inkyDisplayName($preferences);$effectiveSide=strtoupper((string)($preferences['dock_position']??'AUTO'));if($effectiveSide==='AUTO')$effectiveSide=str_ends_with((string)$preferences['anchor_position'],'_RIGHT')?'RIGHT':'LEFT';
    $actions=['review_plot'=>['label'=>'Review Plot','implemented'=>true],'whats_next'=>['label'=>"What's Next?",'implemented'=>true],'quality_check'=>['label'=>'Quality Check My Work','implemented'=>true],'dependencies'=>['label'=>'Find Dependency Problems','implemented'=>true],'character_knowledge'=>['label'=>'Check Character Knowledge','implemented'=>true],'story_values'=>['label'=>'Suggest Story Values','implemented'=>true],'review_opportunity'=>['label'=>'Review This Opportunity','implemented'=>true],'focused_tests'=>['label'=>'Prepare Focused Tests','implemented'=>true]];
    $artwork='assets/inky/inky-octopus-approved.png';
    $cssVersion=is_file(__DIR__.'/assets/toinky-inky.css')?(string)filemtime(__DIR__.'/assets/toinky-inky.css'):'1';$jsVersion=is_file(__DIR__.'/assets/toinky-inky.js')?(string)filemtime(__DIR__.'/assets/toinky-inky.js'):'1';$artVersion=is_file(__DIR__.'/'.$artwork)?(string)filemtime(__DIR__.'/'.$artwork):'1';
    ?><link rel="stylesheet" href="assets/toinky-inky.css?v=<?=e($cssVersion)?>">
<aside class="inky-shell inky-side-<?=e(strtolower($effectiveSide))?> inky-size-<?=e(strtolower($preferences['size_code']))?> inky-animation-<?=e(strtolower($preferences['animation_level']))?> inky-anchor-<?=e(strtolower(str_replace('_','-',$preferences['anchor_position'])))?>" data-inky data-display-name="<?=e($displayName)?>" data-character-code="<?=e($preferences['character_code'])?>" data-context="<?=e($context['context']??'workspace')?>" data-plot-id="<?=(int)($context['plot_id']??0)?>" data-dock="<?=e($preferences['dock_position'])?>" data-sounds="<?=(int)$preferences['sounds_enabled']?>" data-proactive-help="<?=e($preferences['proactive_help'])?>" data-csrf="<?=e(AuthService::csrfToken())?>">
  <section class="inky-panel" id="inky-panel" aria-hidden="true" aria-label="Inky assistant">
    <button class="inky-close" type="button" aria-label="Close Inky">×</button>
    <p class="inky-kicker"><?=e($displayName)?> is here to save your day!</p>
    <h2>What can I do for you?</h2>
    <p class="inky-status" data-inky-status aria-live="polite">Checking assistant availability…</p>
    <div class="inky-actions"><?php foreach($actions as$code=>$action):?><button type="button" data-inky-action="<?=e($code)?>" data-implemented="<?=$action['implemented']?'1':'0'?>" disabled><?=e($action['label'])?><?=$action['implemented']?'':' · Soon'?></button><?php endforeach;?></div>
    <div class="inky-result" data-inky-result hidden aria-live="polite"></div>
    <a class="inky-settings-link" href="profile.php?section=inky">Inky settings</a>
  </section>
  <button class="inky-character" type="button" aria-controls="inky-panel" aria-expanded="false" aria-label="Open <?=e($displayName)?>. Drag to move <?=e($displayName)?>.">
    <span class="inky-artwork-wrap" aria-hidden="true"><img class="inky-artwork" src="<?=e($artwork)?>?v=<?=e($artVersion)?>" alt=""></span>
    <span class="inky-online-dot" aria-hidden="true"></span>
  </button>
</aside><script src="assets/toinky-inky.js?v=<?=e($jsVersion)?>" defer></script><?php
}}
