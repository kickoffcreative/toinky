<?php
if(!function_exists('render_inky')){
function render_inky(array $user,array $context=[]):void
{
    $preferences=UserProfileService::inkyPreferences((int)$user['user_id']);if(!(int)$preferences['enabled'])return;$displayName=UserProfileService::inkyDisplayName($preferences);
    $actions=['review_plot'=>['label'=>'Review Plot','implemented'=>true],'whats_next'=>['label'=>"What's Next?",'implemented'=>true],'quality_check'=>['label'=>'Quality Check My Work','implemented'=>true],'dependencies'=>['label'=>'Find Dependency Problems','implemented'=>true],'character_knowledge'=>['label'=>'Check Character Knowledge','implemented'=>true],'story_values'=>['label'=>'Suggest Story Values','implemented'=>true],'review_opportunity'=>['label'=>'Review This Opportunity','implemented'=>true],'focused_tests'=>['label'=>'Prepare Focused Tests','implemented'=>true]];
    ?><link rel="stylesheet" href="assets/toinky-inky.css">
<aside class="inky-shell inky-size-<?=e(strtolower($preferences['size_code']))?> inky-animation-<?=e(strtolower($preferences['animation_level']))?> inky-anchor-<?=e(strtolower(str_replace('_','-',$preferences['anchor_position'])))?>" data-inky data-character-code="<?=e($preferences['character_code'])?>" data-context="<?=e($context['context']??'workspace')?>" data-plot-id="<?=(int)($context['plot_id']??0)?>" data-dock="<?=e($preferences['dock_position'])?>" data-sounds="<?=(int)$preferences['sounds_enabled']?>" data-proactive-help="<?=e($preferences['proactive_help'])?>" data-csrf="<?=e(AuthService::csrfToken())?>">
  <section class="inky-panel" id="inky-panel" aria-hidden="true" aria-label="Inky assistant">
    <button class="inky-close" type="button" aria-label="Close Inky">×</button>
    <p class="inky-kicker"><?=e($displayName)?> is here to save your day!</p>
    <h2>What can I do for you?</h2>
    <p class="inky-status" data-inky-status aria-live="polite">Checking whether Qwen is available…</p>
    <div class="inky-actions"><?php foreach($actions as$code=>$action):?><button type="button" data-inky-action="<?=e($code)?>" data-implemented="<?=$action['implemented']?'1':'0'?>" disabled><?=e($action['label'])?><?=$action['implemented']?'':' · Soon'?></button><?php endforeach;?></div>
    <div class="inky-result" data-inky-result hidden aria-live="polite"></div>
    <a class="inky-settings-link" href="profile.php?section=inky">Inky settings</a>
  </section>
  <button class="inky-character" type="button" aria-controls="inky-panel" aria-expanded="false" aria-label="Open <?=e($displayName)?>. Drag to move <?=e($displayName)?>.">
    <svg viewBox="0 0 120 120" role="img" aria-label="<?=e($displayName)?>">
      <path class="inky-body" d="M60 9c-23 0-39 17-39 39 0 15 7 25 18 31-7 8-13 17-13 27 0 4 3 7 7 7 9 0 13-12 16-24h5c-2 8-3 17 0 23 2 4 8 5 11 1 4-5 3-15 1-24h5c3 12 7 24 16 24 4 0 7-3 7-7 0-10-6-19-13-27 11-6 18-16 18-31 0-22-16-39-39-39Z"/>
      <path class="inky-tentacle" d="M38 80c-11 8-17 17-17 27M51 86c-5 12-6 22-2 29M69 86c5 12 6 22 2 29M82 80c11 8 17 17 17 27"/>
      <ellipse class="inky-eye" cx="47" cy="47" rx="5" ry="7"/><ellipse class="inky-eye" cx="73" cy="47" rx="5" ry="7"/>
      <path class="inky-smile" d="M50 64c7 7 13 7 20 0"/><circle class="inky-cheek" cx="38" cy="62" r="5"/><circle class="inky-cheek" cx="82" cy="62" r="5"/>
      <path class="inky-highlight" d="M42 25c-8 7-13 15-15 25"/>
    </svg>
    <span class="inky-online-dot" aria-hidden="true"></span>
  </button>
</aside><script src="assets/toinky-inky.js" defer></script><?php
}}
