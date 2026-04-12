<?php

declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($path === '/api/health') {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'ok',
        'service' => 'oktoberfest-php-api',
        'timestamp' => date(DATE_ATOM),
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

http_response_code(404);
header('Content-Type: application/json');
echo json_encode([
    'error' => 'Not Found',
    'path' => $path,
], JSON_UNESCAPED_SLASHES);
