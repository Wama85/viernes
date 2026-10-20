<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// El sistema vive en /home/soft326/viernes
$base = __DIR__ . '/../../viernes';

if (file_exists($maintenance = $base . '/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $base . '/vendor/autoload.php';

$app = require_once $base . '/bootstrap/app.php';

// La carpeta pública está aparte del sistema
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
