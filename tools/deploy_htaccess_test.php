<?php
declare(strict_types=1);
require_once __DIR__.'/deploy_htaccess.php';

function checkDeployment(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$template = file_get_contents(dirname(__DIR__).'/.htaccess');
$handler = <<<'HANDLER'
# php -- BEGIN cPanel-generated handler, do not edit
# Keep the hosting provider's selected version and extensions.
<IfModule mime_module>
  AddHandler application/x-httpd-ea-php84 .php .php8 .phtml .custom
</IfModule>
# php -- END cPanel-generated handler, do not edit
HANDLER;
$ini = <<<'INI'
# BEGIN cPanel-generated php ini directives, do not edit
<IfModule php_module>
  php_value date.timezone "UTC"
  php_value memory_limit 256M
</IfModule>
# END cPanel-generated php ini directives, do not edit
INI;
$live = "Options -Indexes\n".$handler."\n\n".$ini."\n";
$merged = deploymentHtaccess($template, $live);
checkDeployment(str_contains($merged, $handler), 'cPanel handler was changed or lost.');
checkDeployment(str_contains($merged, $ini), 'cPanel INI settings were changed or lost.');
checkDeployment(str_starts_with($merged, rtrim($template)), 'New application routing rules were not installed.');
checkDeployment(deploymentHtaccess($template, $merged) === $merged, 'Repeated deployment duplicates or changes settings.');
$restored = deploymentHtaccess($template, $template);
checkDeployment(str_contains($restored, 'AddHandler application/x-httpd-ea-php84 .php .php8 .phtml'), 'Missing handler was not restored.');
checkDeployment(deploymentHtaccess($template, $restored) === $restored, 'Restored handler was duplicated.');
checkDeployment(str_contains(deploymentHtaccess($template, $ini), $ini), 'INI-only recovery lost settings.');
$future = str_replace('ea-php84', 'ea-php85', $live);
checkDeployment(str_contains(deploymentHtaccess($template, $future), 'ea-php85'), 'Deployment overwrote a newer cPanel selection.');
$crlf = str_replace("\n", "\r\n", $live);
checkDeployment(str_contains(deploymentHtaccess($template, $crlf), str_replace("\n", "\r\n", $handler)), 'CRLF handler was not preserved.');

$directory = sys_get_temp_dir().'/toinky-deployment-'.bin2hex(random_bytes(8));
mkdir($directory, 0700);
try {
    file_put_contents($directory.'/.htaccess', $live);
    checkDeployment(deployHtaccess(dirname(__DIR__).'/.htaccess', $directory), 'Deployment did not update the file.');
    checkDeployment(file_get_contents($directory.'/.htaccess') === $merged, 'Installed configuration differs from the prepared result.');
    $backups = glob($directory.'/config/htaccess-backup-*');
    checkDeployment(count($backups) === 1 && file_get_contents($backups[0]) === $live, 'Exact rollback backup was not saved.');
    checkDeployment(!deployHtaccess(dirname(__DIR__).'/.htaccess', $directory), 'Unchanged deployment was not a no-op.');
    checkDeployment(count(glob($directory.'/config/htaccess-backup-*')) === 1, 'Unchanged deployment made another backup.');
    $broken = "# php -- BEGIN cPanel-generated handler, do not edit\nAddHandler application/x-httpd-ea-php84 .php\n";
    file_put_contents($directory.'/.htaccess', $broken);
    $rejected = false;
    try {
        deployHtaccess(dirname(__DIR__).'/.htaccess', $directory);
    } catch (RuntimeException $error) {
        $rejected = str_contains($error->getMessage(), 'Incomplete cPanel');
    }
    checkDeployment($rejected, 'Malformed hosting configuration should stop deployment.');
    checkDeployment(file_get_contents($directory.'/.htaccess') === $broken, 'Failed deployment changed the live configuration.');
} finally {
    foreach (glob($directory.'/config/htaccess-backup-*') as $file) {
        unlink($file);
    }
    if (is_dir($directory.'/config')) {
        rmdir($directory.'/config');
    }
    unlink($directory.'/.htaccess');
    rmdir($directory);
}
echo "PASS: cPanel configuration survives deployment; missing handlers recover and rollback backups are preserved.\n";
