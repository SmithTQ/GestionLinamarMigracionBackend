<?php

// Tests must never boot with the development config cache, especially its MySQL database.
$cachedConfig = dirname(__DIR__).'/bootstrap/cache/config.php';

if (is_file($cachedConfig)) {
    unlink($cachedConfig);
}

require dirname(__DIR__).'/vendor/autoload.php';
