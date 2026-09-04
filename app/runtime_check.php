<?php
if (defined('TOINKY_RUNTIME_CHECKED')) {
    return;
}
define('TOINKY_RUNTIME_CHECKED', true);

if (PHP_VERSION_ID < 80400) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Toinky requires PHP 8.4 or newer. Set the domain PHP version in cPanel, then reload this page.');
}
