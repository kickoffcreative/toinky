<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/MigrationService.php';
MigrationService::run();
require_once $root.'/app/PageContentService.php';

$pdo=db();
$admin=$pdo->query('SELECT user_id,is_admin FROM users WHERE is_admin=1 ORDER BY user_id LIMIT 1')->fetch();
if(!$admin)throw new RuntimeException('Hero editor test needs an admin fixture.');
$adminUser=['user_id'=>(int)$admin['user_id'],'is_admin'=>1];
$nonAdmin=['user_id'=>(int)$admin['user_id'],'is_admin'=>0];

$failures=[];
$check=function(bool $ok,string $label)use(&$failures){echo ($ok?'PASS':'FAIL').' '.$label."\n";if(!$ok)$failures[]=$label;};
$throws=function(callable $fn): bool{try{$fn();return false;}catch(Throwable $e){return true;}};

$mediaId=(int)$pdo->query('SELECT media_id FROM writer_media_assets WHERE active=1 ORDER BY media_id LIMIT 1')->fetchColumn();

try{
    $code=PageContentService::addPage($adminUser,'Guard Hero Page','guard-hero-page');
    $check($code==='GUARD_HERO_PAGE','addPage returns the page code');
    $heroId=PageContentService::addSection($adminUser,'GUARD_HERO_PAGE',['label_text'=>'Hero','layout_type'=>'CONTAINER']);
    $check($heroId>0,'a hero section can be added to the new page');
    $introId=PageContentService::addSection($adminUser,'GUARD_HERO_PAGE',['label_text'=>'Intro','layout_type'=>'TEXT_TOP']);
    $check($introId>0,'a content section can be added to the new page');
    PageContentService::saveHero($adminUser,$heroId,['eyebrow_text'=>'Guard Kicker','title_text'=>'Guard Title','content_html'=>'<p>Guard intro.</p>','image_media_id'=>$mediaId>0?(string)$mediaId:'']);
    $row=$pdo->query('SELECT eyebrow_text,title_text,content_html,image_media_id FROM site_page_sections WHERE site_page_section_id='.$heroId)->fetch();
    $check($row['eyebrow_text']==='Guard Kicker','hero eyebrow is saved');
    $check($row['title_text']==='Guard Title','hero title is saved');
    $check(str_contains((string)$row['content_html'],'Guard intro.'),'hero intro is saved');
    $check($mediaId>0?(int)$row['image_media_id']===$mediaId:(int)$row['image_media_id']===0,'hero image is saved');
    PageContentService::saveHero($adminUser,$introId,['eyebrow_text'=>'Must Not Save','title_text'=>'Must Not Save','content_html'=>'<p>Must not save.</p>','image_media_id'=>$mediaId>0?(string)$mediaId:'']);
    $row2=$pdo->query('SELECT eyebrow_text,title_text,content_html,image_media_id FROM site_page_sections WHERE site_page_section_id='.$introId)->fetch();
    $check($row2['eyebrow_text']===null&&$row2['title_text']===null,'a content first-section keeps its text untouched by saveHero');
    $check($mediaId>0?(int)$row2['image_media_id']===$mediaId:(int)$row2['image_media_id']===0,'a content first-section still takes the hero image');
    $check($throws(static fn()=>PageContentService::saveHero($nonAdmin,$heroId,[])),'a non-admin cannot save a hero');
    $check($throws(static fn()=>PageContentService::saveHero($adminUser,0,[])),'an unknown section id is rejected');
    $check($throws(static fn()=>PageContentService::saveHero($adminUser,$heroId,['image_media_id'=>'999999999'])),'an unknown media id is rejected');
    $check($throws(static fn()=>PageContentService::saveHero($adminUser,$heroId,['eyebrow_text'=>str_repeat('x',191)])),'an over-long eyebrow is rejected');
}finally{
    $q=$pdo->prepare('SELECT site_page_id FROM site_pages WHERE page_code=?');$q->execute(['GUARD_HERO_PAGE']);
    $pid=$q->fetchColumn();
    if($pid){
        $pdo->prepare('DELETE FROM site_page_sections WHERE site_page_id=?')->execute([$pid]);
        $pdo->prepare('DELETE FROM site_pages WHERE site_page_id=?')->execute([$pid]);
    }
}
if($failures)throw new RuntimeException('Hero editor guard test failed: '.implode('; ',$failures));
echo "PAGE BUILDER HERO EDITOR GUARD TEST PASSED\n";
