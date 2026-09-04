<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function deploymentHtaccess(string $template, string $live): string
{
    // cPanel owns both its PHP handler and its separate PHP INI directive block.
    $pattern = '/^[\t ]*#[^\r\n]*\bBEGIN cPanel-generated[^\r\n]*\R.*?^[\t ]*#[^\r\n]*\bEND cPanel-generated[^\r\n]*(?:\R|$)/ms';
    preg_match_all($pattern, $live, $matches);
    $blocks = $matches[0];
    $begins = preg_match_all('/^[\t ]*#[^\r\n]*\bBEGIN cPanel-generated/m', $live);
    $ends = preg_match_all('/^[\t ]*#[^\r\n]*\bEND cPanel-generated/m', $live);
    if ($begins !== count($blocks) || $ends !== count($blocks)) {
        throw new RuntimeException('Incomplete cPanel configuration block; live .htaccess was not changed.');
    }
    $serverConfig = implode("\n", $blocks);
    if (!preg_match('/^\s*(?:AddHandler|SetHandler)\b/im', $serverConfig)) {
        // Recover the known ea-php84 setting if an earlier deployment removed it.
        $serverConfig .= <<<'HANDLER'

# php -- BEGIN cPanel-generated handler, do not edit
# Set the "ea-php84" package as the default "PHP" programming language.
<IfModule mime_module>
  AddHandler application/x-httpd-ea-php84 .php .php8 .phtml
</IfModule>
# php -- END cPanel-generated handler, do not edit
HANDLER;
    }
    return rtrim($template)."\n\n".trim($serverConfig)."\n";
}

function deployHtaccess(string $templatePath, string $documentRoot): bool
{
    $root = realpath($documentRoot);
    if ($root === false || !is_dir($root)) {
        throw new RuntimeException('Deployment document root does not exist.');
    }
    $target = $root.'/.htaccess';
    if (is_link($target)) {
        throw new RuntimeException('Refusing to replace a linked .htaccess.');
    }
    $template = file_get_contents($templatePath);
    $exists = is_file($target);
    $live = $exists ? file_get_contents($target) : '';
    if ($template === false || $live === false) {
        throw new RuntimeException('Could not read the deployment or live .htaccess.');
    }
    $next = deploymentHtaccess($template, $live);
    if ($next === $live) {
        return false;
    }
    $config = $root.'/config';
    if (is_link($config)) {
        throw new RuntimeException('Refusing to back up configuration through a linked directory.');
    }
    if (!is_dir($config) && !mkdir($config, 0700)) {
        throw new RuntimeException('Could not create the private configuration directory.');
    }
    if ($exists) {
        $backup = tempnam($config, 'htaccess-backup-');
        if ($backup === false || file_put_contents($backup, $live, LOCK_EX) !== strlen($live)) {
            throw new RuntimeException('Could not back up .htaccess; deployment stopped.');
        }
    }
    $temporary = tempnam($root, '.htaccess-deploy-');
    if ($temporary === false) {
        throw new RuntimeException('Could not prepare the new .htaccess.');
    }
    try {
        if (file_put_contents($temporary, $next, LOCK_EX) !== strlen($next) || !chmod($temporary, 0644)) {
            throw new RuntimeException('Could not write the new .htaccess.');
        }
        if (!rename($temporary, $target)) {
            throw new RuntimeException('Could not replace .htaccess; the previous configuration remains.');
        }
    } finally {
        if (is_file($temporary)) {
            unlink($temporary);
        }
    }
    return true;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        if ($argc !== 2) {
            throw new RuntimeException('Usage: php tools/deploy_htaccess.php DOCUMENT_ROOT');
        }
        $changed = deployHtaccess(dirname(__DIR__).'/.htaccess', $argv[1]);
        echo $changed ? "Updated application rules; cPanel PHP configuration preserved or restored.\n" : "Application rules and cPanel PHP configuration are current.\n";
    } catch (Throwable $error) {
        fwrite(STDERR, $error->getMessage()."\n");
        exit(1);
    }
}
