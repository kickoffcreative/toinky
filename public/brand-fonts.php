<?php
declare(strict_types=1);
$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/MigrationService.php';
MigrationService::run();
header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: public, max-age=60, must-revalidate');
$roles=[
 'body'=>'body,input,select,textarea',
 'headings'=>'h1,h2,h3,.brand,.title',
 'navigation'=>'.side a,.nav a,.signed-nav a,.sidebar-section a',
 'labels'=>'label,.eyebrow,.group,.badge,.pill,.code,.slot,.deadline,.muted,small',
 'buttons'=>'.btn,button,input[type="submit"]',
 'brand_context'=>'.brand-context',
 'table_headings'=>'th'
];
$q=db()->prepare('SELECT setting_value FROM platform_settings WHERE setting_key="BRAND_FONT_STYLES"');
$q->execute();
$styles=json_decode((string)$q->fetchColumn(),true);
if(!is_array($styles))$styles=[];
function css_value(string $value,string $pattern,string $fallback):string{return preg_match($pattern,$value)?$value:$fallback;}
foreach($roles as$key=>$selector){
 $style=is_array($styles[$key]??null)?$styles[$key]:[];
 if(!$style)continue;
 $family=preg_replace('/[^A-Za-z0-9\s,_\-.]/','',(string)($style['family']??''));
 $color=css_value(strtoupper((string)($style['color']??'')),'/^#[0-9A-F]{6}([0-9A-F]{2})?$/','#1C242B');
 $size=css_value((string)($style['size']??''),'/^(?:0\.[1-9][0-9]?|[1-9][0-9]{0,2})(?:px|rem|em|%)$/','16px');
 $weight=css_value((string)($style['weight']??''),'/^(300|400|500|600|700|800|900)$/','400');
 $decoration=css_value((string)($style['decoration']??''),'/^(none|underline|overline|line-through)$/','none');
 echo $selector,'{font-family:',$family,';color:',$color,';font-size:',$size,';font-weight:',$weight,';text-decoration:',$decoration,'}',"\n";
}
