<?php
if(!function_exists('render_inky')){
function render_inky(array $user,array $context=[]):void
{
    $preferences=UserProfileService::inkyPreferences((int)$user['user_id']);if(!(int)$preferences['enabled'])return;$displayName=UserProfileService::inkyDisplayName($preferences);
    $actions=['review_plot'=>['label'=>'Review Plot','implemented'=>true],'whats_next'=>['label'=>"What's Next?",'implemented'=>true],'quality_check'=>['label'=>'Quality Check My Work','implemented'=>true],'dependencies'=>['label'=>'Find Dependency Problems','implemented'=>true],'character_knowledge'=>['label'=>'Check Character Knowledge','implemented'=>true],'story_values'=>['label'=>'Suggest Story Values','implemented'=>true],'review_opportunity'=>['label'=>'Review This Opportunity','implemented'=>false],'focused_tests'=>['label'=>'Prepare Focused Tests','implemented'=>true]];
    ?><link rel="stylesheet" href="assets/toinky-inky.css">
<aside class="inky-shell inky-size-<?=e(strtolower($preferences['size_code']))?> inky-animation-<?=e(strtolower($preferences['animation_level']))?> inky-anchor-<?=e(strtolower(str_replace('_','-',$preferences['anchor_position'])))?>" data-inky data-character-code="<?=e($preferences['character_code'])?>" data-context="<?=e($context['context']??'workspace')?>" data-plot-id="<?=(int)($context['plot_id']??0)?>" data-dock="<?=e($preferences['dock_position'])?>" data-sounds="<?=(int)$preferences['sounds_enabled']?>" data-csrf="<?=e(AuthService::csrfToken())?>">
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
      <path class="inky-body" d="M60 7C45 27 23 42 23 70c0 23 16 41 37 41s37-18 37-41C97 42 75 27 60 7Z"/>
      <ellipse class="inky-eye" cx="47" cy="65" rx="5" ry="7"/><ellipse class="inky-eye" cx="73" cy="65" rx="5" ry="7"/>
      <path class="inky-smile" d="M49 81c7 7 15 7 22 0"/><circle class="inky-cheek" cx="39" cy="79" r="5"/><circle class="inky-cheek" cx="81" cy="79" r="5"/>
      <path class="inky-highlight" d="M44 30c-8 9-13 18-15 28"/>
    </svg>
    <span class="inky-online-dot" aria-hidden="true"></span>
  </button>
</aside><script src="assets/toinky-inky.js" defer></script><?php
}}
