<?php
if(!function_exists('render_inky')){
function render_inky(array $user,array $context=[]):void
{
    $preferences=UserProfileService::inkyPreferences((int)$user['user_id']);if(!(int)$preferences['enabled'])return;$displayName=UserProfileService::inkyDisplayName($preferences);$effectiveSide=strtoupper((string)($preferences['dock_position']??'AUTO'));if($effectiveSide==='AUTO')$effectiveSide=str_ends_with((string)$preferences['anchor_position'],'_RIGHT')?'RIGHT':'LEFT';
    $actions=['review_plot'=>['label'=>'Review Plot','implemented'=>true],'whats_next'=>['label'=>"What's Next?",'implemented'=>true],'quality_check'=>['label'=>'Quality Check My Work','implemented'=>true],'dependencies'=>['label'=>'Find Dependency Problems','implemented'=>true],'character_knowledge'=>['label'=>'Check Character Knowledge','implemented'=>true],'story_values'=>['label'=>'Suggest Story Values','implemented'=>true],'review_opportunity'=>['label'=>'Review This Opportunity','implemented'=>true],'focused_tests'=>['label'=>'Prepare Focused Tests','implemented'=>true]];
    $isLantern=(string)$preferences['character_code']==='INKY_DIRECTION_D';$rig=$isLantern?'assets/inky/lantern/rig/':'assets/inky/rig/';$versionAsset=$isLantern?'body_blank.png':'body_base_blank.png';$rigVersion=is_file(__DIR__.'/'.$rig.$versionAsset)?(string)filemtime(__DIR__.'/'.$rig.$versionAsset):'1';
    $cssVersion=is_file(__DIR__.'/assets/toinky-inky.css')?(string)filemtime(__DIR__.'/assets/toinky-inky.css'):'1';$jsVersion=is_file(__DIR__.'/assets/toinky-inky.js')?(string)filemtime(__DIR__.'/assets/toinky-inky.js'):'1';$pixiVersion=is_file(__DIR__.'/assets/toinky-inky-pixi.js')?(string)filemtime(__DIR__.'/assets/toinky-inky-pixi.js'):'1';
    ?><link rel="stylesheet" href="assets/toinky-inky.css?v=<?=e($cssVersion)?>">
<aside class="inky-shell inky-side-<?=e(strtolower($effectiveSide))?> inky-size-<?=e(strtolower($preferences['size_code']))?> inky-animation-<?=e(strtolower($preferences['animation_level']))?> inky-anchor-<?=e(strtolower(str_replace('_','-',$preferences['anchor_position'])))?> <?=$isLantern?'inky-lantern-shell':''?>" data-inky data-inky-mood="idle" data-inky-asset-root="<?=e($rig)?>" data-display-name="<?=e($displayName)?>" data-character-code="<?=e($preferences['character_code'])?>" data-context="<?=e($context['context']??'workspace')?>" data-plot-id="<?=(int)($context['plot_id']??0)?>" data-dock="<?=e($preferences['dock_position'])?>" data-sounds="<?=(int)$preferences['sounds_enabled']?>" data-proactive-help="<?=e($preferences['proactive_help'])?>" data-csrf="<?=e(AuthService::csrfToken())?>">
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
    <span class="inky-artwork-wrap inky-rig-wrap <?=$isLantern?'inky-lantern-wrap':''?>" aria-hidden="true">
      <span class="inky-rig-effects" data-inky-rig-effects></span>
      <span class="inky-rig-character <?=$isLantern?'inky-lantern-rig':''?>">
<?php if($isLantern): ?>
        <img class="inky-rig-part inky-rig-body" src="<?=e($rig)?>body_blank.png?v=<?=e($rigVersion)?>" alt="">
        <img class="inky-rig-part inky-lantern-halo" src="<?=e($rig)?>halo.png?v=<?=e($rigVersion)?>" alt="">
        <img class="inky-rig-part inky-lantern-glow" src="<?=e($rig)?>inner_glow.png?v=<?=e($rigVersion)?>" alt="">
        <img class="inky-rig-part inky-lantern-cape inky-lantern-cape-left" src="<?=e($rig)?>cape_left.png?v=<?=e($rigVersion)?>" alt="">
        <img class="inky-rig-part inky-lantern-cape inky-lantern-cape-right" src="<?=e($rig)?>cape_right.png?v=<?=e($rigVersion)?>" alt="">
        <img class="inky-rig-part inky-rig-scarf" src="<?=e($rig)?>scarf.png?v=<?=e($rigVersion)?>" alt="">
        <img class="inky-rig-part inky-rig-eye inky-rig-eye-left" src="<?=e($rig)?>eye_left.png?v=<?=e($rigVersion)?>" alt="">
        <img class="inky-rig-part inky-rig-eye inky-rig-eye-right" src="<?=e($rig)?>eye_right.png?v=<?=e($rigVersion)?>" alt="">
        <img class="inky-rig-part inky-rig-lid inky-rig-lid-left" src="<?=e($rig)?>lid_left.png?v=<?=e($rigVersion)?>" alt="">
        <img class="inky-rig-part inky-rig-lid inky-rig-lid-right" src="<?=e($rig)?>lid_right.png?v=<?=e($rigVersion)?>" alt="">
        <img class="inky-rig-part inky-rig-mouth inky-rig-mouth-happy is-active" data-inky-mouth="happy" src="<?=e($rig)?>mouth_smile.png?v=<?=e($rigVersion)?>" alt="">
        <img class="inky-rig-part inky-rig-mouth inky-rig-mouth-open" data-inky-mouth="open" src="<?=e($rig)?>mouth_open.png?v=<?=e($rigVersion)?>" alt="">
        <img class="inky-rig-part inky-rig-mouth inky-rig-mouth-o" data-inky-mouth="small_o" src="<?=e($rig)?>mouth_o.png?v=<?=e($rigVersion)?>" alt="">
<?php else: ?>
        <img class="inky-rig-part inky-rig-body inky-tentacle inky-sucker" src="<?=e($rig)?>body_base_blank.png?v=<?=e($rigVersion)?>" alt="">
        <img class="inky-rig-part inky-rig-pen-arm inky-pen" src="<?=e($rig)?>pen_arm.png?v=<?=e($rigVersion)?>" alt="">
        <img class="inky-rig-part inky-rig-scarf" src="<?=e($rig)?>scarf_full.png?v=<?=e($rigVersion)?>" alt="">
        <img class="inky-rig-part inky-rig-eye inky-rig-eye-left" src="<?=e($rig)?>eye_left_open.png?v=<?=e($rigVersion)?>" alt="">
        <img class="inky-rig-part inky-rig-eye inky-rig-eye-right" src="<?=e($rig)?>eye_right_open.png?v=<?=e($rigVersion)?>" alt="">
        <img class="inky-rig-part inky-rig-lid inky-rig-lid-left" src="<?=e($rig)?>lid_left.png?v=<?=e($rigVersion)?>" alt="">
        <img class="inky-rig-part inky-rig-lid inky-rig-lid-right" src="<?=e($rig)?>lid_right.png?v=<?=e($rigVersion)?>" alt="">
        <img class="inky-rig-part inky-rig-mouth inky-rig-mouth-happy is-active" data-inky-mouth="happy" src="<?=e($rig)?>mouth_happy.png?v=<?=e($rigVersion)?>" alt="">
        <img class="inky-rig-part inky-rig-mouth inky-rig-mouth-open" data-inky-mouth="open" src="<?=e($rig)?>mouth_open.png?v=<?=e($rigVersion)?>" alt="">
        <img class="inky-rig-part inky-rig-mouth inky-rig-mouth-o" data-inky-mouth="small_o" src="<?=e($rig)?>mouth_small_o.png?v=<?=e($rigVersion)?>" alt="">
<?php endif; ?>
      </span>
    </span>
    <span class="inky-online-dot" aria-hidden="true"></span>
  </button>
</aside><script src="assets/toinky-inky.js?v=<?=e($jsVersion)?>" defer></script><?php if($isLantern): ?><script src="assets/vendor/pixi.min.js" defer></script><script src="assets/toinky-inky-pixi.js?v=<?=e($pixiVersion)?>" defer></script><?php endif; ?><?php
}}
