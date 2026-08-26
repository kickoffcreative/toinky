<?php
require_once __DIR__.'/MediaLibraryService.php';

final class PageContentService
{
    public const LAYOUTS=['IMAGE_RIGHT','IMAGE_LEFT','THREE_COLUMN','SOCIALS','TEXT_CENTER'];
    public const BACKGROUND_FITS=['COVER','CONTAIN','TILE','STRETCH','CENTER'];
    public const SOCIAL_PLATFORMS=['facebook'=>'Facebook','instagram'=>'Instagram','x'=>'X / Twitter','tiktok'=>'TikTok','youtube'=>'YouTube','linkedin'=>'LinkedIn','pinterest'=>'Pinterest','threads'=>'Threads','bluesky'=>'Bluesky','discord'=>'Discord'];

    public static function pages(): array
    {
        return db()->query('SELECT * FROM site_pages WHERE active=1 ORDER BY sort_order,site_page_id')->fetchAll();
    }

    public static function sections(string $pageCode): array
    {
        $q=db()->prepare('SELECT s.*,im.file_path image_path,im.thumbnail_path image_thumbnail,im.alt_text image_alt,bg.file_path background_path FROM site_page_sections s JOIN site_pages p ON p.site_page_id=s.site_page_id LEFT JOIN writer_media_assets im ON im.media_id=s.image_media_id AND im.active=1 LEFT JOIN writer_media_assets bg ON bg.media_id=s.background_media_id AND bg.active=1 WHERE p.page_code=? AND p.active=1 AND s.active=1 ORDER BY s.sort_order,s.site_page_section_id');$q->execute([strtoupper($pageCode)]);return$q->fetchAll();
    }

    public static function keyed(string $pageCode): array
    {
        $out=[];foreach(self::sections($pageCode)as$section)$out[$section['section_code']]=$section;return$out;
    }

    public static function saveSection(array $admin,int $sectionId,array $input): void
    {
        if((int)($admin['is_admin']??0)!==1)throw new RuntimeException('Administrator access is required.');$q=db()->prepare('SELECT 1 FROM site_page_sections WHERE site_page_section_id=?');$q->execute([$sectionId]);if(!$q->fetchColumn())throw new RuntimeException('Page section not found.');
        $imageId=self::mediaChoice($admin,$input['image_media_id']??'');$layout=in_array(($input['layout_type']??''),self::LAYOUTS,true)?$input['layout_type']:'IMAGE_RIGHT';$mode=in_array(($input['background_mode']??''),['DEFAULT','COLOR','MEDIA'],true)?$input['background_mode']:'DEFAULT';$backgroundId=$mode==='MEDIA'?self::mediaChoice($admin,$input['background_media_id']??''):null;$color=self::color($input['background_color']??'#F4EEE2','background color');$fit=in_array(($input['background_fit']??''),self::BACKGROUND_FITS,true)?$input['background_fit']:'COVER';$backgroundOpacity=self::percent($input['background_opacity']??100);$overlayMode=in_array(($input['overlay_mode']??''),['NONE','COLOR','GRADIENT'],true)?$input['overlay_mode']:'NONE';$overlayStart=self::color($input['overlay_color_start']??'#1C242B','overlay color');$overlayEnd=self::color($input['overlay_color_end']??'#6B2938','gradient color');$overlayOpacity=self::percent($input['overlay_opacity']??0);
        $eyebrow=trim((string)($input['eyebrow_text']??''));$title=trim((string)($input['title_text']??''));if(mb_strlen($eyebrow)>190||mb_strlen($title)>500)throw new RuntimeException('A page title or label is too long.');$html=self::sanitizeHtml((string)($input['content_html']??''));
        $columns=[];for($i=1;$i<=3;$i++)$columns[]=['title'=>mb_substr(trim((string)($input['column_'.$i.'_title']??'')),0,190),'content'=>self::sanitizeHtml((string)($input['column_'.$i.'_content']??'')),'image_media_id'=>self::mediaChoice($admin,$input['column_'.$i.'_image_media_id']??'')];
        db()->prepare('UPDATE site_page_sections SET eyebrow_text=?,title_text=?,content_html=?,image_media_id=?,layout_type=?,columns_json=?,background_mode=?,background_color=?,background_media_id=?,background_fit=?,background_opacity=?,overlay_mode=?,overlay_color_start=?,overlay_color_end=?,overlay_opacity=?,updated_by_user_id=? WHERE site_page_section_id=?')->execute([$eyebrow?:null,$title?:null,$html?:null,$imageId,$layout,json_encode($columns,JSON_UNESCAPED_SLASHES),$mode,$mode==='COLOR'?$color:null,$backgroundId,$fit,$backgroundOpacity,$overlayMode,$overlayStart,$overlayEnd,$overlayOpacity,$admin['user_id'],$sectionId]);
    }

    public static function addSection(array $admin,string $pageCode,array $input): int
    {
        if((int)($admin['is_admin']??0)!==1)throw new RuntimeException('Administrator access is required.');$label=trim((string)($input['label_text']??''));if($label===''||mb_strlen($label)>120)throw new RuntimeException('Enter a section name up to 120 characters.');$baseCode=strtoupper(trim((string)preg_replace('/[^A-Za-z0-9]+/','_',$label),'_'));if($baseCode==='')throw new RuntimeException('Enter a valid section name.');$q=db()->prepare('SELECT site_page_id FROM site_pages WHERE page_code=? AND active=1');$q->execute([strtoupper($pageCode)]);$pageId=(int)$q->fetchColumn();if(!$pageId)throw new RuntimeException('Page not found.');$code=$baseCode;$suffix=2;$exists=db()->prepare('SELECT 1 FROM site_page_sections WHERE site_page_id=? AND section_code=?');while(true){$exists->execute([$pageId,$code]);if(!$exists->fetchColumn())break;$code=$baseCode.'_'.$suffix++;}$sort=(int)db()->query('SELECT COALESCE(MAX(sort_order),0)+10 FROM site_page_sections WHERE site_page_id='.$pageId)->fetchColumn();$layout=in_array(($input['layout_type']??''),self::LAYOUTS,true)?$input['layout_type']:'IMAGE_RIGHT';$q=db()->prepare('INSERT INTO site_page_sections(site_page_id,section_code,label_text,layout_type,sort_order,updated_by_user_id) VALUES(?,?,?,?,?,?)');$q->execute([$pageId,$code,$label,$layout,$sort,$admin['user_id']]);return(int)db()->lastInsertId();
    }

    public static function columns(array $section): array
    {
        $columns=json_decode((string)($section['columns_json']??''),true);if(!is_array($columns))return[];$q=db()->prepare('SELECT file_path,thumbnail_path,alt_text FROM writer_media_assets WHERE media_id=? AND active=1');foreach($columns as&$column){$id=(int)($column['image_media_id']??0);$column['image_path']=null;$column['alt_text']='';if($id>0){$q->execute([$id]);if($media=$q->fetch()){$column['image_path']=$media['file_path'];$column['alt_text']=$media['alt_text'];}}}unset($column);return array_slice($columns,0,3);
    }

    public static function socials(): array
    {
        $q=db()->prepare('SELECT setting_value FROM platform_settings WHERE setting_key="BRAND_SOCIAL_URLS"');$q->execute();$saved=json_decode((string)$q->fetchColumn(),true);$out=[];foreach(self::SOCIAL_PLATFORMS as$key=>$label)$out[$key]=['label'=>$label,'url'=>is_array($saved)?(string)($saved[$key]??''):''];return$out;
    }

    public static function saveSocials(array $admin,array $input): void
    {
        if((int)($admin['is_admin']??0)!==1)throw new RuntimeException('Administrator access is required.');$urls=[];foreach(self::SOCIAL_PLATFORMS as$key=>$label){$url=trim((string)($input['social_'.$key]??''));if($url!==''&&(!filter_var($url,FILTER_VALIDATE_URL)||!str_starts_with(strtolower($url),'https://')))throw new RuntimeException($label.' needs a complete URL beginning with https://.');$urls[$key]=$url;}$q=db()->prepare('INSERT INTO platform_settings(setting_key,setting_value,updated_by_user_id) VALUES("BRAND_SOCIAL_URLS",?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by_user_id=VALUES(updated_by_user_id)');$q->execute([json_encode($urls,JSON_UNESCAPED_SLASHES),$admin['user_id']]);
    }

    public static function style(array $section): string
    {
        $parts=[];$mode=$section['background_mode']??'DEFAULT';if($mode==='COLOR'&&!empty($section['background_color']))$parts[]='--section-bg-color:'.$section['background_color'];if($mode==='MEDIA'&&!empty($section['background_path'])){$path=str_replace(['"',"'",')'],['','',''],(string)$section['background_path']);$parts[]='--section-bg-image:url('.$path.')';[$size,$repeat,$position]=match($section['background_fit']??'COVER'){'CONTAIN'=>['contain','no-repeat','center'],'TILE'=>['auto','repeat','top left'],'STRETCH'=>['100% 100%','no-repeat','center'],'CENTER'=>['auto','no-repeat','center'],default=>['cover','no-repeat','center']};$parts[]='--section-bg-size:'.$size;$parts[]='--section-bg-repeat:'.$repeat;$parts[]='--section-bg-position:'.$position;$parts[]='--section-bg-opacity:'.(max(0,min(100,(int)($section['background_opacity']??100)))/100);}$overlay=$section['overlay_mode']??'NONE';$opacity=max(0,min(100,(int)($section['overlay_opacity']??0)))/100;if($overlay!=='NONE'&&$opacity>0){$start=self::rgba((string)($section['overlay_color_start']??'#1C242B'),$opacity);$end=self::rgba((string)($section['overlay_color_end']??'#6B2938'),$opacity);$parts[]='--section-overlay:'.($overlay==='GRADIENT'?'linear-gradient(135deg,'.$start.','.$end.')':$start);}return$parts?implode(';',$parts).';':'';
    }

    private static function mediaChoice(array $admin,$value): ?int
    {
        $id=(int)$value;if($id<1)return null;MediaLibraryService::requireVisible($admin,$id);return$id;
    }

    private static function color($value,string $label): string{$color=strtoupper(trim((string)$value));if(!preg_match('/^#[0-9A-F]{6}([0-9A-F]{2})?$/',$color))throw new RuntimeException('Choose a valid '.$label.'.');return$color;}
    private static function percent($value): int{return max(0,min(100,(int)$value));}
    private static function rgba(string $hex,float $opacity): string{$hex=ltrim(substr($hex,0,7),'#');return'rgba('.hexdec(substr($hex,0,2)).','.hexdec(substr($hex,2,2)).','.hexdec(substr($hex,4,2)).','.$opacity.')';}

    private static function sanitizeHtml(string $html): string
    {
        $html=strip_tags($html,'<p><br><strong><b><em><i><u><h2><h3><h4><ul><ol><li><blockquote><a>');$html=preg_replace_callback('/<\/?([a-z0-9]+)(?:\s+[^>]*)?>/i',static function(array $match):string{$tag=strtolower($match[1]);$closing=str_starts_with($match[0],'</');if($closing)return'</'.$tag.'>';if($tag==='br')return'<br>';if($tag!=='a')return'<'.$tag.'>';$href='';if(preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/i',$match[0],$found))$href=trim($found[2]);if($href!==''&&!preg_match('~^(?:https?://|mailto:|/|\?|#)~i',$href))$href='';return$href===''?'<a>':'<a href="'.htmlspecialchars($href,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'">';},$html);return trim((string)$html);
    }
}
