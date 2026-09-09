<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/AuthService.php';
require_once $root.'/app/PageContentService.php';
AuthService::boot();
$admin=AuthService::requireAdmin();

$pageCode=strtoupper(trim((string)($_GET['page']??'HOME')));
$pages=PageContentService::pages();
$pageCodes=array_map(static fn(array $page): string => (string)$page['page_code'],$pages);
if(!in_array($pageCode,$pageCodes,true))$pageCode=$pageCodes[0]??'HOME';
$sections=PageContentService::adminSections($pageCode);
$sectionId=(int)($_GET['section']??0);
$section=null;
foreach($sections as $candidate)if((int)$candidate['site_page_section_id']===$sectionId){$section=$candidate;break;}
if(!$section)$section=$sections[0]??null;

$error=null;$notice=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        AuthService::requireCsrf();
        if((string)($_POST['action']??'')!=='save_page_builder')throw new RuntimeException('Unknown page builder action.');
        $sectionId=(int)($_POST['section_id']??0);
        $sectionById=null;
        foreach($sections as $candidate)if((int)$candidate['site_page_section_id']===$sectionId){$sectionById=$candidate;break;}
        if(!$sectionById)throw new RuntimeException('Page section not found.');
        if(($sectionById['section_code']??'')==='HERO')throw new RuntimeException('Hero sections use their dedicated editor.');
        $rows=json_decode((string)($_POST['rows_payload']??'[]'),true,20,JSON_THROW_ON_ERROR);
        if(!is_array($rows))throw new RuntimeException('The builder tree is invalid.');
        $input=array_merge($sectionById,['rows_present'=>1,'rows'=>$rows,'layout_type'=>$sectionById['layout_type']??'IMAGE_RIGHT','background_mode'=>$sectionById['background_mode']??'DEFAULT','background_color'=>$sectionById['background_color']??'#F4EEE2','background_fit'=>$sectionById['background_fit']??'COVER','background_opacity'=>$sectionById['background_opacity']??100,'overlay_mode'=>$sectionById['overlay_mode']??'NONE','overlay_color_start'=>$sectionById['overlay_color_start']??'#1C242B','overlay_color_end'=>$sectionById['overlay_color_end']??'#6B2938','overlay_opacity'=>$sectionById['overlay_opacity']??0]);
        PageContentService::saveSection($admin,$sectionId,$input);
        header('Location: page-builder.php?page='.rawurlencode($pageCode).'&section='.$sectionId.'&saved=1');
        exit;
    }catch(Throwable $e){$error=$e->getMessage();}
}
if(isset($_GET['saved']))$notice='Changes saved.';
$rows=$section?PageContentService::editorRows($section):[];
$pageMedia=db()->query('SELECT media_id,title_text FROM writer_media_assets WHERE active=1 ORDER BY title_text,media_id')->fetchAll();
if($section&&($section['section_code']??'')!=='HERO'&&!$rows)$rows=[['layout_type'=>'CONTAINER','active'=>true,'children'=>[]]];
function builder_e(string $value): string{return htmlspecialchars($value,ENT_QUOTES,'UTF-8');}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Page Builder</title>
<link rel="stylesheet" href="assets/toinky-admin-shell.css">
<link rel="stylesheet" href="assets/toinky-admin-workspace.css">
<link rel="stylesheet" href="assets/toinky-page-builder.css?v=20260909-sidebar">
</head>
<body class="page-builder-app">
<header class="page-builder-topbar">
<a href="admin.php?view=pages" class="page-builder-back">Back to Pages</a>
<strong>Page Builder</strong>
<label>Page <select id="page-select"><?php foreach($pages as $page):?><option value="<?=builder_e((string)$page['page_code'])?>" <?=$pageCode===$page['page_code']?'selected':''?>><?=builder_e((string)$page['label_text'])?></option><?php endforeach;?></select></label>
<button type="button" id="preview-refresh">Refresh Preview</button>
</header>
<?php if($error):?><p class="page-builder-alert error"><?=builder_e($error)?></p><?php elseif($notice):?><p class="page-builder-alert notice"><?=builder_e($notice)?></p><?php endif;?>
<main class="page-builder-shell">
<aside class="page-builder-sidebar" id="page-builder-sidebar">
<nav class="page-builder-sections" aria-label="Page sections">
<?php foreach($sections as $item):$hero=($item['section_code']??'')==='HERO';?>
<a class="page-builder-section <?=$section&&$item['site_page_section_id']===$section['site_page_section_id']?'selected':''?>" href="?page=<?=rawurlencode($pageCode)?>&section=<?=(int)$item['site_page_section_id']?>">
<span><?=builder_e((string)($item['label_text']??'Section'))?></span><small><?=$hero?'Hero':'Section'?></small>
</a>
<?php endforeach;?>
</nav>
<?php if($section&&($section['section_code']??'')!=='HERO'):?>
<form method="post" id="builder-form">
<?=csrf_field()?><input type="hidden" name="action" value="save_page_builder"><input type="hidden" name="section_id" value="<?=(int)$section['site_page_section_id']?>"><input type="hidden" name="rows_payload" id="rows-payload">
<div class="inspector-heading"><span>Editing</span><strong><?=builder_e((string)$section['label_text'])?></strong></div>
<div class="inspector-tabs" role="tablist"><button type="button" class="active" data-inspector-tab="content">Content</button><button type="button" data-inspector-tab="style">Style</button><button type="button" data-inspector-tab="advanced">Advanced</button></div>
<section class="inspector-panel active" data-inspector-panel="content"><div id="content-properties"><p class="builder-muted">Select a section or row.</p></div></section>
<section class="inspector-panel" data-inspector-panel="style"><div id="style-properties"></div></section>
<section class="inspector-panel" data-inspector-panel="advanced"><div id="advanced-properties"></div></section>
<button class="builder-save" type="submit">Save Changes</button>
</form>
<?php else:?><p class="builder-muted">Hero sections keep their dedicated Hero editor. Select a content section to edit its Container tree.</p><?php endif;?>
</aside>
<button type="button" id="sidebar-toggle" aria-controls="page-builder-sidebar" aria-expanded="true" aria-label="Hide properties sidebar" title="Hide properties sidebar"><span aria-hidden="true">&lt;</span><span class="page-builder-toggle-label">Hide properties sidebar</span></button>
<section class="page-builder-preview"><div class="preview-toolbar"><span>Live preview</span><button type="button" id="open-preview">Open page</button></div><iframe id="page-preview" title="Page preview" src="index.php?view=<?=$pageCode==='HOME'?'public':strtolower($pageCode)?>"></iframe></section>
</main>
<script>
window.toinkyPageBuilder={page:<?=json_encode($pageCode)?>,rows:<?=json_encode($rows,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,media:<?=json_encode($pageMedia,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,hero:<?=json_encode(($section['section_code']??'')==='HERO')?>};
</script>
<script src="assets/toinky-page-builder.js?v=20260909-sidebar" defer></script>
</body>
</html>
