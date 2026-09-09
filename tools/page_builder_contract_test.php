<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/PageContentService.php';
$required=['IMAGE_RIGHT','IMAGE_LEFT','THREE_COLUMN','FOUR_COLUMN','SOCIALS','TEXT_CENTER','TEXT_TOP','CARDS_ONLY','TEXT_BULLETS','CONTAINER'];
if(PageContentService::LAYOUTS!==$required)throw new RuntimeException('Page layout contract is incomplete.');
$row=PageContentService::rows(['layout_type'=>'TEXT_BULLETS','eyebrow_text'=>'Label','title_text'=>'Title','content_html'=>'<p>Body</p>','rows_json'=>'[{"layout_type":"TEXT_BULLETS","title_text":"Row","bullets":["One","Two"]}]']);
if(count($row)!==1||$row[0]['layout_type']!=='TEXT_BULLETS'||count($row[0]['bullets'])!==2)throw new RuntimeException('Page row normalization failed.');
$container=PageContentService::rows(['rows_json'=>json_encode([['layout_type'=>'CONTAINER','children'=>[['layout_type'=>'FOUR_COLUMN','columns'=>[['title'=>'One'],['title'=>'Two'],['title'=>'Three'],['title'=>'Four']]]]])]);
if($container[0]['layout_type']!=='CONTAINER'||count($container[0]['children'])!==1||$container[0]['children'][0]['layout_type']!=='FOUR_COLUMN')throw new RuntimeException('Container row normalization failed.');
echo "PASS: page builder layout and row contract\n";

