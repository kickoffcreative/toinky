<?php
if(!function_exists('render_inky')){
function render_inky(array $user,array $context=[]):void
{
    $preferences=UserProfileService::inkyPreferences((int)$user['user_id']);if(!(int)$preferences['enabled'])return;$displayName=UserProfileService::inkyDisplayName($preferences);$effectiveSide=strtoupper((string)($preferences['dock_position']??'AUTO'));if($effectiveSide==='AUTO')$effectiveSide=str_ends_with((string)$preferences['anchor_position'],'_RIGHT')?'RIGHT':'LEFT';
    $actions=['review_plot'=>['label'=>'Review Plot','implemented'=>true],'whats_next'=>['label'=>"What's Next?",'implemented'=>true],'quality_check'=>['label'=>'Quality Check My Work','implemented'=>true],'dependencies'=>['label'=>'Find Dependency Problems','implemented'=>true],'character_knowledge'=>['label'=>'Check Character Knowledge','implemented'=>true],'story_values'=>['label'=>'Suggest Story Values','implemented'=>true],'review_opportunity'=>['label'=>'Review This Opportunity','implemented'=>true],'focused_tests'=>['label'=>'Prepare Focused Tests','implemented'=>true]];
    $cssVersion=is_file(__DIR__.'/assets/toinky-inky.css')?(string)filemtime(__DIR__.'/assets/toinky-inky.css'):'1';$jsVersion=is_file(__DIR__.'/assets/toinky-inky.js')?(string)filemtime(__DIR__.'/assets/toinky-inky.js'):'1';
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
    <svg viewBox="0 0 120 120" role="img" aria-label="<?=e($displayName)?>">
      <path class="inky-body" d="M60 8c-24 0-40 17-40 39 0 17 9 30 24 35h32c15-5 24-18 24-35C100 25 84 8 60 8Z"/>
      <path class="inky-head-shadow" d="M30 54c1-20 13-35 33-38 15-2 26 5 31 18-9-8-23-11-38-8-15 3-24 13-26 28Z"/>
      <path class="inky-tentacle inky-tentacle-fill" d="M43 78c-15 3-25 14-24 27 1 8 8 11 15 8 6-3 8-10 6-15-2-4-7-5-10-2"/>
      <path class="inky-tentacle inky-tentacle-fill" d="M53 82c-10 9-12 24-6 31 5 6 14 3 15-5 1-5-3-9-8-8"/>
      <path class="inky-tentacle inky-tentacle-fill" d="M67 82c10 9 12 24 6 31-5 6-14 3-15-5-1-5 3-9 8-8"/>
      <path class="inky-tentacle inky-tentacle-fill" d="M77 78c15 3 25 14 24 27-1 8-8 11-15 8-6-3-8-10-6-15 2-4 7-5 10-2"/>
      <path class="inky-arm" d="M82 70c15-5 22-20 15-30-3-4-9-2-7 3 5 11-1 20-11 24"/>
      <path class="inky-pen" d="M95 31l6-14 7 5-10 12Z"/><path class="inky-pen-line" d="M94 35l9-13"/>
      <circle class="inky-sucker" cx="32" cy="103" r="4"/><circle class="inky-sucker" cx="49" cy="105" r="3"/><circle class="inky-sucker" cx="71" cy="105" r="3"/><circle class="inky-sucker" cx="88" cy="103" r="4"/>
      <ellipse class="inky-eye" cx="47" cy="48" rx="8" ry="10"/><ellipse class="inky-eye" cx="73" cy="48" rx="8" ry="10"/>
      <circle class="inky-eye-light" cx="50" cy="44" r="3"/><circle class="inky-eye-light" cx="76" cy="44" r="3"/>
      <path class="inky-smile" d="M52 64c4 6 12 6 16 0"/><circle class="inky-cheek" cx="38" cy="61" r="5"/><circle class="inky-cheek" cx="82" cy="61" r="5"/>
      <path class="inky-highlight" d="M42 24c-8 6-13 14-15 25"/>
    </svg>
    <span class="inky-online-dot" aria-hidden="true"></span>
  </button>
</aside><script src="assets/toinky-inky.js?v=<?=e($jsVersion)?>" defer></script><?php
}}
