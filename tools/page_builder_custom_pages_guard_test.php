<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/MigrationService.php';
MigrationService::run();
require_once $root.'/app/PageContentService.php';

$pdo=db();
$admin=$pdo->query('SELECT user_id,is_admin FROM users WHERE is_admin=1 ORDER BY user_id LIMIT 1')->fetch();
if(!$admin)throw new RuntimeException('Custom pages test needs an admin fixture.');
$adminUser=['user_id'=>(int)$admin['user_id'],'is_admin'=>1];

$failures=[];
$check=function(bool $ok,string $label)use(&$failures){echo ($ok?'PASS':'FAIL').' '.$label."\n";if(!$ok)$failures[]=$label;};
$throws=function(callable $fn): bool{try{$fn();return false;}catch(Throwable $e){return true;}};

try{
    $code=PageContentService::addPage($adminUser,'Guard Test Page','guard-test-page');
    $check($code==='GUARD_TEST_PAGE','addPage returns the page code');
    $codes=array_map(static fn($p)=>(string)$p['page_code'],PageContentService::pages());
    $check(in_array('GUARD_TEST_PAGE',$codes,true),'the new page appears in the pages list');
    $check($throws(static fn()=>PageContentService::addPage($adminUser,'Guard Test Page','guard-test-page')),'a duplicate page address is rejected');
    $check($throws(static fn()=>PageContentService::addPage($adminUser,'Buy','buy')),'a reserved page address is rejected');
    $check($throws(static fn()=>PageContentService::addPage($adminUser,'Bad','Bad Slug!')),'an invalid page address is rejected');
    $sectionId=PageContentService::addSection($adminUser,'GUARD_TEST_PAGE',['label_text'=>'Hero','layout_type'=>'CONTAINER']);
    $check($sectionId>0,'a section can be added to the new page');
    $keyed=PageContentService::keyed('GUARD_TEST_PAGE');
    $check(isset($keyed['HERO']),'the new page sections load by page code');
}finally{
    $q=$pdo->prepare('SELECT site_page_id FROM site_pages WHERE page_code=?');$q->execute(['GUARD_TEST_PAGE']);
    $pid=$q->fetchColumn();
    if($pid){
        $pdo->prepare('DELETE FROM site_page_sections WHERE site_page_id=?')->execute([$pid]);
        $pdo->prepare('DELETE FROM site_pages WHERE site_page_id=?')->execute([$pid]);
    }
}
if($failures)throw new RuntimeException('Custom pages guard test failed: '.implode('; ',$failures));
echo "PAGE BUILDER CUSTOM PAGES GUARD TEST PASSED\n";
