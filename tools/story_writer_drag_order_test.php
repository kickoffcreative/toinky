<?php
$source=file_get_contents(__DIR__.'/../public/writer.php');
if($source===false)throw new RuntimeException('Story Writer source could not be read.');
if(str_contains($source,'handle.draggable=true'))throw new RuntimeException('Native button dragging was restored; it produces a prohibited cursor in supported browsers.');
if(str_contains($source,"event.pointerType==='mouse'"))throw new RuntimeException('Mouse pointer reordering is disabled.');
foreach(["setPointerCapture(event.pointerId)","targetAt(event.clientX,event.clientY)","await saveOrder()"]as$required)if(!str_contains($source,$required))throw new RuntimeException('Pointer reordering is missing '.$required.'.');
if(!str_contains($source,"\$o['option_code'].' · '.\$o['label']")||!str_contains($source,"\$x['option_code'].' · '.\$x['label']"))throw new RuntimeException('Stable Option Codes are missing from choice headings.');
echo "PASS: options, choices, and effects use pointer-based reordering and show stable option codes.\n";
