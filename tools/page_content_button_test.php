<?php
require_once __DIR__.'/../app/PageContentService.php';

$defaults=PageContentService::defaultButtons();
if(count($defaults)!==3)throw new RuntimeException('Home Hero defaults must contain three buttons.');
$custom=PageContentService::buttons(['buttons_json'=>json_encode([['label'=>'Explore','url'=>'/stories','member_url'=>'','target'=>'_blank','visible'=>true,'background_type'=>'GRADIENT','background_color'=>'#111111','background_end_color'=>'#6B2938']])]);
if(count($custom)!==1||$custom[0]['label']!=='Explore'||$custom[0]['target']!=='_blank'||$custom[0]['background_type']!=='GRADIENT')throw new RuntimeException('Hero button normalization failed.');
$style=PageContentService::buttonStyle($custom[0]);
if(!str_contains($style,'--hero-button-background:linear-gradient')||!str_contains($style,'--hero-button-radius:6px'))throw new RuntimeException('Hero button style output is incomplete.');
$css=file_get_contents(__DIR__.'/../public/assets/toinky-home.css');
if($css===false||!str_contains($css,'.public-home .hero-actions .hero-button')||!str_contains($css,'background: var(--hero-button-background')||!str_contains($css,'!important'))throw new RuntimeException('Home Hero button styles do not override global theme button rules.');
$admin=file_get_contents(__DIR__.'/../public/admin.php');
if($admin===false||!str_contains($admin,'admin_color_control($label,$name($field)')||!str_contains($admin,'function initColorControl')||!str_contains($admin,"list.querySelectorAll('[data-color-control]').forEach(initColorControl)"))throw new RuntimeException('Home Hero buttons do not expose full color controls for all button panels.');
$empty=PageContentService::buttons(['buttons_json'=>'[]']);
if($empty!==[])throw new RuntimeException('An explicitly empty Hero button list must remain empty.');
echo "PASS: Home Hero buttons normalize, style, and preserve empty configurations.\n";
