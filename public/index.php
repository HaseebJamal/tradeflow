<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// XAMPP resolves /public/ through DirectoryIndex but leaves REQUEST_URI as the
// directory. Normalise it to the front controller so Symfony uses the same
// base URL as a direct /public/index.php request.
if (isset($_SERVER['REQUEST_URI'], $_SERVER['SCRIPT_NAME'])
    && str_ends_with(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '', '/public/')
    && str_ends_with($_SERVER['SCRIPT_NAME'], '/public/index.php')) {
    $_SERVER['REQUEST_URI'] = rtrim($_SERVER['REQUEST_URI'], '/').'/index.php';
}

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
