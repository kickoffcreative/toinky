<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
// Rebuild the existing Google Fonts catalogue as local, licensed WOFF2 assets.
$root=dirname(__DIR__);$families=['Cormorant Garamond','Instrument Sans','Cinzel','Alegreya Sans SC','Alegreya Sans','Fredoka','Baloo 2','DM Sans','Manrope','Oswald','Roboto Slab','Playfair Display','Uncial Antiqua','MedievalSharp','Libre Baskerville','EB Garamond','Creepster','Nosifer','Special Elite','IM Fell English','Spectral','Crimson Pro','Lora','Parisienne','Orbitron','Space Grotesk','Rye','Grenze Gotisch','Barlow Condensed','Archivo Narrow','Bebas Neue','Saira Condensed','Inter','Merriweather'];
$fetch=static function(string $url): string{
    $host=parse_url($url,PHP_URL_HOST);if(!str_starts_with($url,'https://')||!in_array($host,['fonts.googleapis.com','fonts.gstatic.com','raw.githubusercontent.com'],true))throw new RuntimeException('Unapproved font source.');
    $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_USERAGENT=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/131.0.0.0 Safari/537.36']);$body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);if($status!==200||!is_string($body))throw new RuntimeException('Font source returned HTTP '.$status);return $body;
};
$queries=[implode('&',array_map(static fn($family)=>'family='.rawurlencode($family),$families)), 'family=Cormorant+Garamond:wght@500;600;700&family=Instrument+Sans:wght@400;500;600;700'];
$css="/* Local Toinky font catalogue. Sources and licences: manifest.json and licenses/. */\n";$assets=[];$seen=[];$records=[];$covered=[];
foreach($queries as $query){$source='https://fonts.googleapis.com/css2?'.$query.'&display=swap';$remote=$fetch($source);preg_match_all('~/\* (latin(?:-ext)?) \*/\s*(@font-face\s*\{[^}]+\})~',$remote,$blocks,PREG_SET_ORDER);
    foreach($blocks as $block){$face=$block[2];preg_match("/font-family:\\s*'([^']+)'/",$face,$name);preg_match('~url\((https://fonts\.gstatic\.com/[^)]+\.woff2)\)~',$face,$url);if(!$name||!$url||!in_array($name[1],$families,true))throw new RuntimeException('Unexpected font CSS.');$family=$name[1];$covered[$family]=true;$file=substr(hash('sha256',$url[1]),0,24).'.woff2';
        if(!isset($assets[$file])){$bytes=$fetch($url[1]);if(substr($bytes,0,4)!=='wOF2')throw new RuntimeException('Invalid WOFF2 asset.');$assets[$file]=$bytes;$records[$file]=['source'=>$url[1],'sha256'=>hash('sha256',$bytes),'bytes'=>strlen($bytes)];}
        $face=str_replace($url[1],$file,$face);if(!isset($seen[$face])){$css.='/* '.$block[1]." */\n".$face."\n";$seen[$face]=true;}
    }
}
$licenses=[];foreach($families as $family){if(!isset($covered[$family]))throw new RuntimeException('Missing font family '.$family);$slug=strtolower(preg_replace('/[^a-zA-Z0-9]/','',$family));$license=null;$source=null;
    foreach(['ofl/'.$slug.'/OFL.txt','apache/'.$slug.'/LICENSE.txt','ofl/'.$slug.'/LICENSE.txt','apache/'.$slug.'/LICENSE'] as $candidate){try{$source='https://raw.githubusercontent.com/google/fonts/main/'.$candidate;$license=$fetch($source);break;}catch(RuntimeException $e){}}
    if(!$license||(!str_contains($license,'SIL OPEN FONT LICENSE')&&!str_contains($license,'Apache License')))throw new RuntimeException('Missing recognised licence for '.$family);
    $license=str_replace(["\r\n","\r"],"\n",$license);
    $licenses[$slug.'.txt']=$license;$records['licenses/'.$slug.'.txt']=['family'=>$family,'source'=>$source,'sha256'=>hash('sha256',$license)];
}
$directory=$root.'/public/assets/fonts';if(!is_dir($directory.'/licenses')&&!mkdir($directory.'/licenses',0755,true)&&!is_dir($directory.'/licenses'))throw new RuntimeException('Could not create font asset directory.');
foreach($assets as $file=>$bytes)file_put_contents($directory.'/'.$file,$bytes);foreach($licenses as $file=>$text)file_put_contents($directory.'/licenses/'.$file,$text);
file_put_contents($directory.'/fonts.css',$css);file_put_contents($directory.'/manifest.json',json_encode(['families'=>$families,'subsets'=>['latin','latin-ext'],'sources'=>$records],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
echo 'Vendored '.count($families).' families, '.count($assets)." WOFF2 assets and their licences.\n";
