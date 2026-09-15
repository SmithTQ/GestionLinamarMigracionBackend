<?php

// Tests must never boot with the development config cache, especially its MySQL database.
putenv('APP_ENV=testing');
putenv('APP_CONFIG_CACHE=/tmp/linamar-testing-config.php');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');
putenv('DB_URL=');
$_ENV['APP_ENV'] = 'testing';
$_ENV['APP_CONFIG_CACHE'] = '/tmp/linamar-testing-config.php';
$_ENV['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_DATABASE'] = ':memory:';
$_ENV['DB_URL'] = '';
$_SERVER['APP_ENV'] = 'testing';
$_SERVER['APP_CONFIG_CACHE'] = '/tmp/linamar-testing-config.php';
$_SERVER['DB_CONNECTION'] = 'sqlite';
$_SERVER['DB_DATABASE'] = ':memory:';
$_SERVER['DB_URL'] = '';

$cachedConfig = dirname(__DIR__).'/bootstrap/cache/config.php';

if (is_file($cachedConfig)) {
    unlink($cachedConfig);
}

$testingCachedConfig = getenv('APP_CONFIG_CACHE') ?: '/tmp/linamar-testing-config.php';
if (is_file($testingCachedConfig)) {
    unlink($testingCachedConfig);
}

require dirname(__DIR__).'/vendor/autoload.php';
