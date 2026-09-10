<?php
final class PageBuilderService
{
    public const MAX_DEPTH=12;

    public static function saveRows(array $admin,int $sectionId,array $rows): void
    {
        if((int)($admin['is_admin']??0)!==1)throw new RuntimeException('Administrator access is required.');
        $q=db()->prepare('SELECT section_code FROM site_page_sections WHERE site_page_section_id=?');
        $q->execute([$sectionId]);
        $code=$q->fetchColumn();
        if($code===false)throw new RuntimeException('Page section not found.');
        if((string)$code==='HERO')throw new RuntimeException('Hero sections use their dedicated editor.');
        $normalized=self::normalizeRows($rows);
        db()->prepare('UPDATE site_page_sections SET rows_json=?,updated_by_user_id=? WHERE site_page_section_id=?')->execute([
            json_encode($normalized,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
            $admin['user_id'],
            $sectionId,
        ]);
    }

    public static function normalizeRows(array $rows,int $depth=0): array
    {
        if($depth>self::MAX_DEPTH)return[];
        $out=[];
        foreach(array_values($rows)as$row){
            if(!is_array($row))continue;
            $layout=strtoupper(trim((string)($row['layout_type']??'TEXT_TOP')));
            if(!in_array($layout,PageContentService::LAYOUTS,true)||$layout==='HERO')$layout='TEXT_TOP';
            $item=[
                'layout_type'=>$layout,
                'active'=>array_key_exists('active',$row)?(bool)$row['active']:true,
            ];
            if($layout==='CONTAINER'){
                $item['background_color']=self::optionalColor($row['background_color']??'');
                $item['background_media_id']=self::mediaId($row['background_media_id']??null);
                $item['background_fit']=self::backgroundFit($row['background_fit']??'FILL');
                $item['content_width']=self::contentWidth($row['content_width']??'FULL');
                $item['max_width_px']=self::maxWidth($row['max_width_px']??1980);
                self::applySpacing($item,$row,'margin');
                self::applySpacing($item,$row,'padding');
                $item['children']=self::normalizeRows(is_array($row['children']??null)?$row['children']:[],$depth+1);
                $out[]=$item;
                continue;
            }
            foreach(['label_text','eyebrow_text','title_text']as$key)$item[$key]=self::text($row[$key]??'',500);
            $item['content_html']=self::html($row['content_html']??'');
            $item['bullets']=self::listValues($row['bullets']??[]);
            $item['image_media_id']=self::mediaId($row['image_media_id']??null);
            $item['background_media_id']=self::mediaId($row['background_media_id']??null);
            $item['background_color']=self::optionalColor($row['background_color']??'');
            $item['background_fit']=self::backgroundFit($row['background_fit']??'FILL');
            $item['text_color']=self::optionalColor($row['text_color']??'');
            $textAlign=(string)($row['text_align']??'left');
            $item['text_align']=in_array($textAlign,['left','center','right'],true)?$textAlign:'left';
            $blendMode=(string)($row['blend_mode']??'normal');
            $item['blend_mode']=in_array($blendMode,['normal','multiply','screen','overlay','darken','lighten','difference'],true)?$blendMode:'normal';
            self::applySpacing($item,$row,'margin');
            self::applySpacing($item,$row,'padding');
            $item['column_background_color']=self::optionalColor($row['column_background_color']??'');
            $item['column_background_media_id']=self::mediaId($row['column_background_media_id']??null);
            $item['column_background_fit']=self::backgroundFit($row['column_background_fit']??'FILL');
            self::applySpacing($item,$row,'column_padding');
            if(is_array($row['columns']??null)){
                $item['columns']=[];
                foreach(array_slice($row['columns'],0,6)as$column){
                    if(!is_array($column))$column=[];
                    $item['columns'][]=[
                        'icon_text'=>self::text($column['icon_text']??'',120),
                        'title'=>self::text($column['title']??'',190),
                        'content'=>self::html($column['content']??''),
                    ];
                }
            }
            $out[]=$item;
        }
        return$out;
    }

    private static function text(mixed$value,int$limit): string
    {
        $value=trim((string)$value);
        return function_exists('mb_substr')?mb_substr($value,0,$limit):substr($value,0,$limit);
    }

    private static function html(mixed$value): string
    {
        $html=strip_tags((string)$value,'<p><br><strong><em><u><h2><h3><ul><ol><li><a>');
        $html=(string)preg_replace('/\s+on[a-z]+\s*=\s*(["\']).*?\1/isu','',$html);
        $html=(string)preg_replace('/\s+style\s*=\s*(["\']).*?\1/isu','',$html);
        return function_exists('mb_substr')?mb_substr($html,0,20000):substr($html,0,20000);
    }

    private static function listValues(mixed$value): array
    {
        if(is_string($value))$value=preg_split('/\r?\n/',$value);
        if(!is_array($value))return[];
        $out=[];
        foreach($value as$item){$item=self::text($item,500);if($item!=='')$out[]=$item;}
        return array_slice($out,0,30);
    }

    private static function pixels(mixed$value): int
    {
        return max(0,min(2000,(int)$value));
    }

    private static function applySpacing(array&$item,array$row,string$prefix): void
    {
        $mode=strtoupper(trim((string)($row[$prefix.'_mode']??'SIDES')));
        $item[$prefix.'_mode']=$mode==='ALL'?'ALL':'SIDES';
        $item[$prefix.'_all_px']=self::pixels($row[$prefix.'_all_px']??0);
        foreach(['top','right','bottom','left']as$edge)$item[$prefix.'_'.$edge.'_px']=self::pixels($row[$prefix.'_'.$edge.'_px']??0);
    }

    private static function color(mixed$value): string
    {
        $value=strtoupper(trim((string)$value));
        return preg_match('/^#[0-9A-F]{6}$/',$value)?$value:'#F4EEE2';
    }

    private static function optionalColor(mixed$value): string
    {
        $value=strtoupper(trim((string)$value));
        return preg_match('/^#[0-9A-F]{6}([0-9A-F]{2})?$/',$value)?$value:'';
    }

    private static function backgroundFit(mixed$value): string
    {
        $value=strtoupper(trim((string)$value));
        return in_array($value,['FILL','FIT','TILE'],true)?$value:'FILL';
    }

    private static function contentWidth(mixed$value): string
    {
        $value=strtoupper(trim((string)$value));
        return in_array($value,['FULL','MAX'],true)?$value:'FULL';
    }

    private static function maxWidth(mixed$value): int
    {
        return max(320,min(3840,(int)$value?:1980));
    }

    private static function mediaId(mixed$value): ?int
    {
        $id=(int)$value;
        if($id<1)return null;
        static $allowed=null;
        if($allowed===null)$allowed=array_fill_keys(array_map('intval',db()->query('SELECT media_id FROM writer_media_assets WHERE active=1')->fetchAll(PDO::FETCH_COLUMN)),true);
        return isset($allowed[$id])?$id:null;
    }
}
