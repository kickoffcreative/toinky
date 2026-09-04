<?php
require __DIR__ . '/app/runtime_check.php';
$root = __DIR__;
if (!is_file($root . '/.installed') || !is_file($root . '/config/database.php')) {
    header('Location: install.php');
    exit;
}
require $root . '/public/index.php';
