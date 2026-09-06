<?php
$admin=file_get_contents(__DIR__.'/../public/admin.php');
$css=file_get_contents(__DIR__.'/../public/assets/toinky-admin-workspace.css');
foreach(['ai-settings.php','lab.php']as$extra){${$extra==='ai-settings.php'?'ai':'lab'}=file_get_contents(__DIR__.'/../public/'.$extra);if(${$extra==='ai-settings.php'?'ai':'lab'}===false)throw new RuntimeException('Admin UI source could not be read: '.$extra);}
if($admin===false||$css===false)throw new RuntimeException('Admin UI sources could not be read.');
foreach(['class="layout admin-app"','assets/toinky-admin-workspace.css']as$needle){if(!str_contains($admin,$needle))throw new RuntimeException('Admin shell wiring is incomplete: '.$needle);}
if(!str_contains($ai,'class="layout admin-app"')||!str_contains($ai,'assets/toinky-admin-workspace.css'))throw new RuntimeException('AI Settings is not using the compact admin shell.');
if(!str_contains($lab,'class="workspace-layout admin-app lab-app"')||!str_contains($lab,'assets/toinky-admin-workspace.css'))throw new RuntimeException('Story Engine Lab is not using the compact admin shell.');
foreach(['.admin-app .main','.admin-app .field input','.admin-app .table','.admin-app .admin-shell-side','.admin-app .theme-tabs','.admin-app .page-section-summary','@media (max-width: 760px)']as$needle){if(!str_contains($css,$needle))throw new RuntimeException('Admin compact styling is incomplete: '.$needle);}
echo "PASS: Admin tabs use the compact Story Writer workspace shell.\n";
