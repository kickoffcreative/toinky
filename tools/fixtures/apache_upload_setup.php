<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
// Disposable image only. The TLS redirect is covered separately; this fixture uses HTTP.
$root='/var/www/html';$rules=str_replace("\r\n","\n",(string)file_get_contents($root.'/.htaccess'));
$rules=str_replace(["RewriteCond %{HTTPS} !=on\n","RewriteRule ^ https://toinky.com%{REQUEST_URI} [R=308,L,NE]\n"],'',$rules);file_put_contents($root.'/.htaccess',$rules);
file_put_contents('/etc/apache2/conf-enabled/toinky-fixture.conf', <<<'CONF'
ServerName localhost
<Directory /var/www/html>
    AllowOverride All
    Require all granted
</Directory>
Alias /upload-policy/ /var/www/upload-policy/
<Directory /var/www/upload-policy>
    AllowOverride All
    Require all granted
</Directory>
AddHandler application/x-httpd-php .php .phtml .php8 .phar
CONF);
file_put_contents($root.'/engine.php','<?php echo "PHP_HANDLER_ACTIVE";');
file_put_contents($root.'/control.php.jpg','<?php echo "DOUBLE_HANDLER_ACTIVE";');
file_put_contents($root.'/theme-media.php','<?php echo "IMAGE_ROUTE";');
foreach(['app','config','database','tools','public'] as $part)file_put_contents($root.'/'.$part.'/probe.php','<?php echo "PRIVATE_EXECUTED";');
$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
mkdir($root.'/public/uploads/profiles',0750,true);file_put_contents($root.'/public/uploads/profiles/pixel.png',$png);
$payload='<?php file_put_contents("/tmp/toinky-upload-executed","EXECUTED"); echo "UPLOAD_EXECUTED"; ?>';
foreach(['payload.php','payload.phtml','payload.php8','payload.phar','payload.cgi'] as $name)file_put_contents('/var/www/upload-policy/'.$name,$payload);
file_put_contents('/var/www/upload-policy/pixel.png',$png);file_put_contents('/var/www/upload-policy/polyglot.php.jpg',$png.$payload);
foreach([$root,'/var/www/upload-policy'] as $directory){
 $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
 foreach($it as $item){chown($item->getPathname(),'www-data');chgrp($item->getPathname(),'www-data');chmod($item->getPathname(),$item->isDir()?0750:0640);}
 chown($directory,'www-data');chgrp($directory,'www-data');chmod($directory,0750);
}
