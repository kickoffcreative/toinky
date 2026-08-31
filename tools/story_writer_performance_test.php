<?php
$source=file_get_contents(__DIR__.'/../public/writer.php');
if($source===false)throw new RuntimeException('Story Writer source could not be read.');
foreach([
    'const initializeTargetSelect=select=>',
    'const initializeConditionField=field=>',
    'const pendingWriterControls=new Set()',
    'const flushWriterControls=deadline=>',
    'processed<6',
    'const queueVisibleWriterControls=root=>',
    'queueVisibleWriterControls(document)',
    "'requestIdleCallback'in window",
    "document.addEventListener('toggle'",
    "writerContent?.addEventListener('pointerdown'",
    'content-visibility:auto',
]as$required)if(!str_contains($source,$required))throw new RuntimeException('Story Writer lazy initialization is missing '.$required.'.');
if(str_contains($source,'initializeVisibleTargetSelects(document)'))throw new RuntimeException('Story Value selectors are being initialized synchronously again.');
if(str_contains($source,'initializeVisibleConditionFields(document)'))throw new RuntimeException('Condition controls are being initialized synchronously again.');
echo "PASS: heavy Story Value controls initialize in idle batches or immediately when used.\n";
