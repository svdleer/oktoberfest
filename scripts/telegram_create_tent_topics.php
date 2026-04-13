<?php

declare(strict_types=1);

/**
 * Create one Telegram forum topic per tent and update .env.telegram mapping.
 *
 * Usage:
 *   php scripts/telegram_create_tent_topics.php
 */

const TENTS = [
    ['name' => 'Fischer-Vroni', 'slug' => 'fischer-vroni'],
    ['name' => 'Hofbraeu Festzelt', 'slug' => 'hofbraeu-festzelt'],
    ['name' => 'Festhalle Pschorr Braeurosl', 'slug' => 'festhalle-pschorr-braeurosl'],
    ['name' => 'Hacker Festzelt', 'slug' => 'hacker-festzelt'],
    ['name' => 'Kufflers Weinzelt', 'slug' => 'kufflers-weinzelt'],
    ['name' => 'Kaefers Wiesn Schenke', 'slug' => 'kaefers-wiesn-schaenke'],
    ['name' => 'Marstall Festzelt', 'slug' => 'marstall-festzelt'],
    ['name' => 'Paulaner Festzelt', 'slug' => 'paulaner-festzelt'],
    ['name' => 'Schuetzen Festzelt', 'slug' => 'schuetzen-festzelt'],
];

$rootDir = dirname(__DIR__);
$envFile = $rootDir . '/.env.telegram';
$env = loadEnvFile($envFile);

$botToken = getenv('TELEGRAM_BOT_TOKEN') ?: ($env['TELEGRAM_BOT_TOKEN'] ?? '');
$target = getenv('TELEGRAM_TARGET') ?: ($env['TELEGRAM_TARGET'] ?? ($env['TELEGRAM_CHAT_ID'] ?? ''));

if ($botToken === '' || $target === '') {
    fwrite(STDERR, "Missing TELEGRAM_BOT_TOKEN or TELEGRAM_TARGET in .env.telegram\n");
    exit(1);
}

echo "Creating topics in: {$target}\n";

$map = [];
$errors = [];

foreach (TENTS as $tent) {
    $name = (string) $tent['name'];
    $slug = (string) $tent['slug'];

    $result = telegramApi($botToken, 'createForumTopic', [
        'chat_id' => $target,
        'name' => $name,
    ]);

    if (($result['ok'] ?? false) !== true) {
        $errors[] = sprintf('%s (%s): %s', $name, $slug, $result['description'] ?? 'unknown error');
        echo "FAILED: {$name}\n";
        continue;
    }

    $threadId = $result['result']['message_thread_id'] ?? null;
    if (!is_int($threadId)) {
        $errors[] = sprintf('%s (%s): no message_thread_id returned', $name, $slug);
        echo "FAILED: {$name}\n";
        continue;
    }

    $map[$slug] = $threadId;
    echo sprintf("OK: %s -> %d\n", $name, $threadId);
}

if (count($map) > 0) {
    $mapping = [];
    foreach ($map as $slug => $threadId) {
        $mapping[] = $slug . ':' . $threadId;
    }

    updateEnvLine($envFile, 'TELEGRAM_TENT_TOPIC_MAP', implode(',', $mapping));
    echo "\nUpdated .env.telegram TELEGRAM_TENT_TOPIC_MAP\n";
}

if (count($errors) > 0) {
    echo "\nErrors:\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(2);
}

echo "\nAll topics created successfully.\n";
exit(0);

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

    if ($raw === false || $raw === null) {
        return [
            'ok' => false,
            'description' => 'request failed',
        ];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [
            'ok' => false,
            'description' => 'non-json response',
        ];
    }

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

        $data[trim($parts[0])] = trim(trim($parts[1]), "\"'");
    }

    return $data;
}

function updateEnvLine(string $path, string $key, string $value): void
{
    $lines = is_file($path) ? file($path, FILE_IGNORE_NEW_LINES) : [];
    if ($lines === false) {
        $lines = [];
    }

    $updated = false;
    foreach ($lines as $index => $line) {
        if (str_starts_with($line, $key . '=')) {
            $lines[$index] = $key . '=' . $value;
            $updated = true;
            break;
        }
    }

    if (!$updated) {
        $lines[] = $key . '=' . $value;
    }

    file_put_contents($path, implode("\n", $lines) . "\n");
}
