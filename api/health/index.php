<?php

declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

echo json_encode([
    'status' => 'ok',
    'service' => 'oktoberfest-php-api',
    'timestamp' => date(DATE_ATOM),
], JSON_UNESCAPED_SLASHES);
