<?php
declare(strict_types=1);

require_once __DIR__.'/../app/PageContentService.php';
require_once __DIR__.'/../app/PageBuilderService.php';

$rows=[[
    'layout_type'=>'CONTAINER',
    'title_text'=>'must be removed',
    'content_html'=>'must be removed',
    'children'=>[[
        'layout_type'=>'CONTAINER',
        'children'=>[[
            'layout_type'=>'TEXT_TOP',
            'active'=>false,
            'title_text'=>'Nested heading',
            'content_html'=>'<p>Nested copy</p><script>alert(1)</script>',
        ]],
    ]],
]];

$normalized=PageBuilderService::normalizeRows($rows);
if(count($normalized)!==1||($normalized[0]['layout_type']??'')!=='CONTAINER')throw new RuntimeException('Root Container was not preserved.');
if(array_key_exists('title_text',$normalized[0])||array_key_exists('content_html',$normalized[0]))throw new RuntimeException('Container received content fields.');
$nested=$normalized[0]['children'][0]['children'][0]??[];
if(($nested['title_text']??'')!=='Nested heading'||($nested['active']??true)!==false)throw new RuntimeException('Nested content row was not preserved.');
if(str_contains((string)($nested['content_html']??''),'<script'))throw new RuntimeException('Unsafe HTML was not removed.');
if(($nested['text_align']??'')!=='left'||($nested['blend_mode']??'')!=='normal')throw new RuntimeException('Optional style defaults were not applied.');

echo "PASS: recursive Page Builder row contract",PHP_EOL;
