<?php
declare(strict_types=1);

require_once __DIR__.'/../app/PageContentService.php';
require_once __DIR__.'/../app/PageBuilderService.php';

$rows=[[
    'layout_type'=>'CONTAINER',
    'background_color'=>'#123456',
    'background_fit'=>'TILE',
    'content_width'=>'MAX',
    'max_width_px'=>1980,
    'title_text'=>'must be removed',
    'content_html'=>'must be removed',
    'children'=>[[
        'layout_type'=>'CONTAINER',
        'children'=>[[
            'layout_type'=>'TEXT_TOP',
            'active'=>false,
            'title_text'=>'Nested heading',
            'content_html'=>'<p>Nested copy</p><script>alert(1)</script>',
            'background_color'=>'#ABCDEF',
            'background_fit'=>'FIT',
            'padding_top_px'=>24,
            'padding_bottom_px'=>18,
        ],[
            'layout_type'=>'FOUR_COLUMN',
            'label_text'=>'Four labels',
            'column_background_color'=>'#FEDCBA',
            'column_background_fit'=>'TILE',
            'column_padding_left_px'=>12,
            'columns'=>[
                ['icon_text'=>'✦','title'=>'One','content'=>'<p>First</p>'],
                ['icon_text'=>'◆','title'=>'Two','content'=>'<p>Second</p>'],
                ['icon_text'=>'☉','title'=>'Three','content'=>'<p>Third</p>'],
                ['icon_text'=>'⚑','title'=>'Four','content'=>'<p>Fourth</p>'],
            ],
        ]],
    ]],
]];

$normalized=PageBuilderService::normalizeRows($rows);
if(count($normalized)!==1||($normalized[0]['layout_type']??'')!=='CONTAINER')throw new RuntimeException('Root Container was not preserved.');
if(array_key_exists('title_text',$normalized[0])||array_key_exists('content_html',$normalized[0]))throw new RuntimeException('Container received content fields.');
if(($normalized[0]['background_color']??'')!=='#123456')throw new RuntimeException('Container background color was not preserved.');
if(($normalized[0]['background_fit']??'')!=='TILE')throw new RuntimeException('Container background fit was not preserved.');
if(($normalized[0]['content_width']??'')!=='MAX'||($normalized[0]['max_width_px']??0)!==1980)throw new RuntimeException('Container content width was not preserved.');
$nested=$normalized[0]['children'][0]['children'][0]??[];
if(($nested['title_text']??'')!=='Nested heading'||($nested['active']??true)!==false)throw new RuntimeException('Nested content row was not preserved.');
if(str_contains((string)($nested['content_html']??''),'<script'))throw new RuntimeException('Unsafe HTML was not removed.');
if(($nested['text_align']??'')!=='left'||($nested['blend_mode']??'')!=='normal')throw new RuntimeException('Optional style defaults were not applied.');
if(($nested['background_color']??null)!=='#ABCDEF'||($nested['background_fit']??'')!=='FIT')throw new RuntimeException('Content row background settings were not preserved.');
if(($nested['padding_top_px']??0)!==24||($nested['padding_bottom_px']??0)!==18)throw new RuntimeException('Content row padding was not preserved.');
$columns=$normalized[0]['children'][0]['children'][1]??[];
if(($columns['layout_type']??'')!=='FOUR_COLUMN'||($columns['label_text']??'')!=='Four labels')throw new RuntimeException('Four Columns row was not preserved.');
if(($columns['column_background_color']??'')!=='#FEDCBA'||($columns['column_background_fit']??'')!=='TILE')throw new RuntimeException('Column style settings were not preserved.');
if(($columns['column_padding_left_px']??0)!==12)throw new RuntimeException('Column padding was not preserved.');
if(count($columns['columns']??[])!==4||(($columns['columns'][2]['icon_text']??'')!=='☉'))throw new RuntimeException('Four column icon presets were not preserved.');

echo "PASS: recursive Page Builder row contract",PHP_EOL;
