<?php

declare(strict_types=1);

use App\Application;
use App\Http\Request;

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (!is_file($autoload)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Composer dependencies are not installed (vendor/autoload.php missing).\n";

    return;
}

require $autoload;

$application = Application::boot();
$application->handle(Request::fromGlobals())->send();
