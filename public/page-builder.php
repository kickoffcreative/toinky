<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/AuthService.php';
require_once $root.'/app/PageContentService.php';
require_once $root.'/app/PageBuilderService.php';
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
        $action=(string)($_POST['action']??'');
        if($action==='add_section'){
            $label=trim((string)($_POST['section_label']??''));
            $layout=strtoupper(trim((string)($_POST['section_layout']??'CONTAINER')));
            if(!in_array($layout,PageContentService::LAYOUTS,true)||$layout==='HERO')$layout='CONTAINER';
            $newSectionId=PageContentService::addSection($admin,$pageCode,['label_text'=>$label,'layout_type'=>'CONTAINER']);
            $children=$layout==='CONTAINER'?[]:[['layout_type'=>$layout,'active'=>false,'label_text'=>'','title_text'=>'','content_html'=>'','bullets'=>[]]];
            PageBuilderService::saveRows($admin,$newSectionId,[['layout_type'=>'CONTAINER','active'=>true,'children'=>$children]]);
            header('Location: page-builder.php?page='.rawurlencode($pageCode).'&section='.$newSectionId.'&saved=1');
            exit;
        }
        if($action==='reorder_sections'){
            $order=json_decode((string)($_POST['section_order']??'[]'),true,10,JSON_THROW_ON_ERROR);
            if(!is_array($order))throw new RuntimeException('The section order is invalid.');
            PageContentService::reorderSections($admin,$pageCode,array_map('intval',$order));
            header('Location: page-builder.php?page='.rawurlencode($pageCode).'&section='.$sectionId.'&saved=1');
            exit;
        }
        if($action==='rename_section'){
            $sectionId=(int)($_POST['section_id']??0);
            PageContentService::renameSection($admin,$sectionId,(string)($_POST['section_label']??''));
            header('Location: page-builder.php?page='.rawurlencode($pageCode).'&section='.$sectionId.'&saved=1');
            exit;
        }
        if($action==='delete_section'){
            $sectionId=(int)($_POST['section_id']??0);
            PageContentService::deleteSection($admin,$sectionId);
            header('Location: page-builder.php?page='.rawurlencode($pageCode).'&saved=1');
            exit;
        }
        if($action!=='save_page_builder')throw new RuntimeException('Unknown page builder action.');
        $sectionId=(int)($_POST['section_id']??0);
        $sectionById=null;
        foreach($sections as $candidate)if((int)$candidate['site_page_section_id']===$sectionId){$sectionById=$candidate;break;}
        if(!$sectionById)throw new RuntimeException('Page section not found.');
        if(($sectionById['section_code']??'')==='HERO')throw new RuntimeException('Hero sections use their dedicated editor.');
        $rows=json_decode((string)($_POST['rows_payload']??'[]'),true,20,JSON_THROW_ON_ERROR);
        if(!is_array($rows))throw new RuntimeException('The builder tree is invalid.');
        PageBuilderService::saveRows($admin,$sectionId,$rows);
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
<link rel="stylesheet" href="assets/toinky-page-builder.css?v=20260909-section-tools">
</head>
<body class="page-builder-app">
<header class="page-builder-topbar">
<a href="admin.php?view=overview" class="page-builder-back">Back to Dashboard</a>
<strong>Page Builder</strong>
<label>Page <select id="page-select"><?php foreach($pages as $page):?><option value="<?=builder_e((string)$page['page_code'])?>" <?=$pageCode===$page['page_code']?'selected':''?>><?=builder_e((string)$page['label_text'])?></option><?php endforeach;?></select></label>
<button type="button" id="preview-refresh">Refresh Preview</button>
</header>
<?php if($error):?><p class="page-builder-alert error"><?=builder_e($error)?></p><?php elseif($notice):?><p class="page-builder-alert notice"><?=builder_e($notice)?></p><?php endif;?>
<main class="page-builder-shell">
<aside class="page-builder-sidebar" id="page-builder-sidebar">
<form method="post" id="section-add-form" class="section-add-form">
<input type="hidden" name="csrf_token" value="<?=htmlspecialchars(AuthService::csrfToken(),ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="action" value="add_section">
<label>New section<input type="text" name="section_label" maxlength="120" placeholder="Section name" required></label>
<label>First content<select name="section_layout"><option value="CONTAINER">Empty Container</option><option value="TEXT_TOP">Top Text Only</option><option value="TEXT_CENTER">Text Center</option><option value="IMAGE_RIGHT">Image On Right</option><option value="IMAGE_LEFT">Image On Left</option><option value="THREE_COLUMN">Three Columns</option><option value="FOUR_COLUMN">Four Columns</option><option value="CARDS_ONLY">Cards Only</option><option value="TEXT_BULLETS">Text With Bulleted List</option><option value="STORY_CARDS">Story Cards</option><option value="SOCIALS">Social Buttons</option></select></label>
<button type="submit">Add Section</button>
</form>
<form method="post" id="section-order-form" class="section-order-form">
<input type="hidden" name="csrf_token" value="<?=htmlspecialchars(AuthService::csrfToken(),ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="action" value="reorder_sections"><input type="hidden" name="section_order" id="section-order">
<p class="builder-muted">Drag sections to reorder them. Hero sections stay fixed.</p><button type="submit" id="section-order-save" disabled>Save Section Order</button>
</form>
<nav class="page-builder-sections" id="page-builder-sections" aria-label="Page sections">
<?php foreach($sections as $item):$hero=($item['section_code']??'')==='HERO';?>
<a class="page-builder-section <?=$section&&$item['site_page_section_id']===$section['site_page_section_id']?'selected':''?>" href="?page=<?=rawurlencode($pageCode)?>&section=<?=(int)$item['site_page_section_id']?>" data-section-id="<?=(int)$item['site_page_section_id']?>" data-fixed="<?=$hero?'1':'0'?>" draggable="<?=$hero?'false':'true'?>">
<span><?=builder_e((string)($item['label_text']??'Section'))?></span><small><?=$hero?'Hero':'Section'?></small>
</a>
<?php endforeach;?>
</nav>
<?php if($section&&($section['section_code']??'')!=='HERO'):?>
<div class="section-tools">
<form method="post" class="section-rename-form"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(AuthService::csrfToken(),ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="action" value="rename_section"><input type="hidden" name="section_id" value="<?=(int)$section['site_page_section_id']?>"><label>Section name<input name="section_label" maxlength="120" value="<?=builder_e((string)($section['label_text']??''))?>" required></label><button type="submit">Rename</button></form>
<form method="post" class="section-delete-form" onsubmit="return confirm('Delete this section? This cannot be undone.');"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(AuthService::csrfToken(),ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="action" value="delete_section"><input type="hidden" name="section_id" value="<?=(int)$section['site_page_section_id']?>"><button type="submit" <?=!empty($section['is_default'])?'disabled title="Default sections can be hidden but not deleted."':'title="Delete section"'?>>Delete</button></form>
</div>
<form method="post" id="builder-form">
<input type="hidden" name="csrf_token" value="<?=htmlspecialchars(AuthService::csrfToken(),ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="action" value="save_page_builder"><input type="hidden" name="section_id" value="<?=(int)$section['site_page_section_id']?>"><input type="hidden" name="rows_payload" id="rows-payload">
<div class="inspector-heading"><span>Editing</span><strong><?=builder_e((string)$section['label_text'])?></strong></div>
<div class="builder-inspector-tools"><button type="button" id="structure-open" data-structure-open>Structure</button></div>
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
<script src="assets/toinky-page-builder.js?v=20260909-section-tools" defer></script>
</body>
</html>
