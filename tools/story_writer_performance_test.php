<?php
$source=file_get_contents(__DIR__.'/../public/writer.php');
if($source===false)throw new RuntimeException('Story Writer source could not be read.');
foreach([
    'const initializeTargetSelect=select=>',
    'const initializeConditionField=field=>',
    'initializeVisibleTargetSelects(document)',
    'initializeVisibleConditionFields(document)',
    "document.addEventListener('toggle'",
    "writerContent?.addEventListener('pointerdown'",
    'content-visibility:auto',
]as$required)if(!str_contains($source,$required))throw new RuntimeException('Story Writer lazy initialization is missing '.$required.'.');
if(str_contains($source,"document.querySelectorAll('[data-target-select]').forEach"))throw new RuntimeException('All Story Value selectors are being initialized eagerly again.');
if(str_contains($source,"document.querySelectorAll('.condition-value-field').forEach"))throw new RuntimeException('All condition controls are being initialized eagerly again.');
echo "PASS: heavy Story Value controls initialize only when visible or used.\n";
