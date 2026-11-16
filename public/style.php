<?php
#serves assets/css/style.css, which sits outside the public web root
declare(strict_types=1);

$file = dirname(__DIR__) . '/assets/css/style.css';
if (!is_file($file)) {
    http_response_code(404);
    exit;
}
header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: no-cache');
readfile($file);
