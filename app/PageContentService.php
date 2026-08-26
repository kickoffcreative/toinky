<?php
require_once __DIR__.'/MediaLibraryService.php';

final class PageContentService
{
    public static function pages(): array
    {
        return db()->query('SELECT * FROM site_pages WHERE active=1 ORDER BY sort_order,site_page_id')->fetchAll();
    }

    public static function sections(string $pageCode): array
    {
        $q=db()->prepare('SELECT s.*,im.file_path image_path,im.thumbnail_path image_thumbnail,bg.file_path background_path FROM site_page_sections s JOIN site_pages p ON p.site_page_id=s.site_page_id LEFT JOIN writer_media_assets im ON im.media_id=s.image_media_id AND im.active=1 LEFT JOIN writer_media_assets bg ON bg.media_id=s.background_media_id AND bg.active=1 WHERE p.page_code=? AND p.active=1 AND s.active=1 ORDER BY s.sort_order,s.site_page_section_id');$q->execute([strtoupper($pageCode)]);return$q->fetchAll();
    }

    public static function keyed(string $pageCode): array
    {
        $out=[];foreach(self::sections($pageCode)as$section)$out[$section['section_code']]=$section;return$out;
    }

    public static function saveSection(array $admin,int $sectionId,array $input): void
    {
        if((int)($admin['is_admin']??0)!==1)throw new RuntimeException('Administrator access is required.');$q=db()->prepare('SELECT 1 FROM site_page_sections WHERE site_page_section_id=?');$q->execute([$sectionId]);if(!$q->fetchColumn())throw new RuntimeException('Page section not found.');
        $imageId=self::mediaChoice($admin,$input['image_media_id']??'');$mode=in_array(($input['background_mode']??''),['DEFAULT','COLOR','MEDIA'],true)?$input['background_mode']:'DEFAULT';$backgroundId=$mode==='MEDIA'?self::mediaChoice($admin,$input['background_media_id']??''):null;$color=strtoupper(trim((string)($input['background_color']??'')));if($mode==='COLOR'&&!preg_match('/^#[0-9A-F]{6}([0-9A-F]{2})?$/',$color))throw new RuntimeException('Choose a valid section background color.');
        $eyebrow=trim((string)($input['eyebrow_text']??''));$title=trim((string)($input['title_text']??''));if(mb_strlen($eyebrow)>190||mb_strlen($title)>500)throw new RuntimeException('A page title or label is too long.');$html=self::sanitizeHtml((string)($input['content_html']??''));
        db()->prepare('UPDATE site_page_sections SET eyebrow_text=?,title_text=?,content_html=?,image_media_id=?,background_mode=?,background_color=?,background_media_id=?,updated_by_user_id=? WHERE site_page_section_id=?')->execute([$eyebrow?:null,$title?:null,$html?:null,$imageId,$mode,$mode==='COLOR'?$color:null,$backgroundId,$admin['user_id'],$sectionId]);
    }

    public static function style(array $section): string
    {
        if(($section['background_mode']??'DEFAULT')==='COLOR'&&!empty($section['background_color']))return'background-color:'.$section['background_color'].';';if(($section['background_mode']??'DEFAULT')==='MEDIA'&&!empty($section['background_path']))return'background-image:linear-gradient(#1C242B66,#1C242B66),url('.str_replace(['"',"'",')'],['','',''],(string)$section['background_path']).');background-size:cover;background-position:center;';return'';
    }

    private static function mediaChoice(array $admin,$value): ?int
    {
        $id=(int)$value;if($id<1)return null;MediaLibraryService::requireVisible($admin,$id);return$id;
    }

    private static function sanitizeHtml(string $html): string
    {
        $html=strip_tags($html,'<p><br><strong><b><em><i><u><h2><h3><h4><ul><ol><li><blockquote><a>');$html=preg_replace_callback('/<\/?([a-z0-9]+)(?:\s+[^>]*)?>/i',static function(array $match):string{$tag=strtolower($match[1]);$closing=str_starts_with($match[0],'</');if($closing)return'</'.$tag.'>';if($tag==='br')return'<br>';if($tag!=='a')return'<'.$tag.'>';$href='';if(preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/i',$match[0],$found))$href=trim($found[2]);if($href!==''&&!preg_match('~^(?:https?://|mailto:|/|\?|#)~i',$href))$href='';return$href===''?'<a>':'<a href="'.htmlspecialchars($href,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'">';},$html);return trim((string)$html);
    }
}
