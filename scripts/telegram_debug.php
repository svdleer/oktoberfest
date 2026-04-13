<?php

declare(strict_types=1);

/**
 * Telegram debug helper for channel configuration.
 *
 * Usage:
 *   php scripts/telegram_debug.php
 */

$rootDir = dirname(__DIR__);
$envFile = $rootDir . '/.env.telegram';
$env = loadEnvFile($envFile);

$token = getenv('TELEGRAM_BOT_TOKEN') ?: ($env['TELEGRAM_BOT_TOKEN'] ?? '');
$target = getenv('TELEGRAM_TARGET') ?: ($env['TELEGRAM_TARGET'] ?? ($env['TELEGRAM_CHAT_ID'] ?? ''));

if ($token === '' || $target === '') {
    fwrite(STDERR, "Missing TELEGRAM_BOT_TOKEN or TELEGRAM_TARGET in .env.telegram\n");
    exit(1);
}

echo "Telegram debug\n";
echo "Target: {$target}\n\n";

runStep('getMe', telegramApi($token, 'getMe', []));
runStep('getChat', telegramApi($token, 'getChat', ['chat_id' => $target]));
runStep('sendMessage', telegramApi($token, 'sendMessage', [
    'chat_id' => $target,
    'text' => 'Telegram debug test message',
    'disable_web_page_preview' => 'true',
]));

exit(0);

function runStep(string $name, array $result): void
{
    echo "=== {$name} ===\n";
    echo 'HTTP: ' . ($result['httpCode'] ?? 'n/a') . "\n";
    echo 'OK: ' . (($result['ok'] ?? false) ? 'true' : 'false') . "\n";

    if (($result['ok'] ?? false) === true) {
        echo "Result: success\n";
    } else {
        $desc = $result['description'] ?? 'Unknown error';
        echo "Error: {$desc}\n";
    }

    if (!empty($result['raw'])) {
        echo 'Raw: ' . $result['raw'] . "\n";
    }

    echo "\n";
}

function telegramApi(string $token, string $method, array $params): array
{
    $url = sprintf('https://api.telegram.org/bot%s/%s', rawurlencode($token), $method);
    $payload = http_build_query($params);

    $opts = [
        'http' => [
            'method' => 'POST',
            'timeout' => 20,
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
            'ignore_errors' => true,
        ],
    ];

    $context = stream_context_create($opts);
    $raw = @file_get_contents($url, false, $context);

    $httpCode = null;
    $headers = function_exists('http_get_last_response_headers') ? http_get_last_response_headers() : [];
    if (is_array($headers)) {
        foreach ($headers as $header) {
            if (preg_match('/HTTP\/\S+\s+(\d+)/', $header, $matches)) {
                $httpCode = (int) $matches[1];
                break;
            }
        }
    }

    if ($raw === false || $raw === null) {
        return [
            'ok' => false,
            'description' => 'Request failed (no response body)',
            'raw' => '',
            'httpCode' => $httpCode,
        ];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [
            'ok' => false,
            'description' => 'Non-JSON response',
            'raw' => $raw,
            'httpCode' => $httpCode,
        ];
    }

    $decoded['raw'] = $raw;
    $decoded['httpCode'] = $httpCode;
    return $decoded;
}

function loadEnvFile(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return [];
    }

    $data = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);

        if ($key === '') {
            continue;
        }

        $data[$key] = trim($value, "\"'");
    }

    return $data;
}
