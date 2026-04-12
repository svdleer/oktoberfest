<?php

declare(strict_types=1);

/**
 * Fischer-Vroni availability monitor with Telegram alerts.
 *
 * Usage:
 *   php scripts/fischer_vroni_telegram_monitor.php
 *   php scripts/fischer_vroni_telegram_monitor.php --force
 */

const DEFAULT_CHECK_URL = 'https://tischreservierung-oktoberfest.de/shop/?swoof=1&pa_festzelt=fischer-vroni';
const EMPTY_MARKER = 'Es wurden keine Produkte gefunden';

$rootDir = dirname(__DIR__);
$storageDir = $rootDir . '/storage';
$stateFile = $storageDir . '/fischer_vroni_monitor_state.json';
$envFile = $rootDir . '/.env.telegram';

if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
    fwrite(STDERR, "Failed to create storage directory: {$storageDir}\n");
    exit(1);
}

$env = loadEnvFile($envFile);
$botToken = getenv('TELEGRAM_BOT_TOKEN') ?: ($env['TELEGRAM_BOT_TOKEN'] ?? '');
$target = getenv('TELEGRAM_TARGET') ?: ($env['TELEGRAM_TARGET'] ?? '');
if ($target === '') {
    $target = getenv('TELEGRAM_CHAT_ID') ?: ($env['TELEGRAM_CHAT_ID'] ?? '');
}
$threadIdRaw = getenv('TELEGRAM_MESSAGE_THREAD_ID') ?: ($env['TELEGRAM_MESSAGE_THREAD_ID'] ?? '');
$threadId = ctype_digit((string) $threadIdRaw) ? (int) $threadIdRaw : null;
$checkUrl = getenv('CHECK_URL') ?: ($env['CHECK_URL'] ?? DEFAULT_CHECK_URL);
$force = in_array('--force', $argv, true);

if ($botToken === '' || $target === '') {
    fwrite(STDERR, "Missing TELEGRAM_BOT_TOKEN or TELEGRAM_TARGET (or TELEGRAM_CHAT_ID fallback). Configure .env.telegram or environment variables.\n");
    exit(1);
}

$html = fetchUrl($checkUrl);
if ($html === null) {
    fwrite(STDERR, "Failed to fetch target URL: {$checkUrl}\n");
    exit(2);
}

$isAvailable = detectAvailability($html);
$state = loadState($stateFile);
$previous = $state['isAvailable'] ?? null;
$changed = ($previous === null) || ((bool) $previous !== $isAvailable);

$state['isAvailable'] = $isAvailable;
$state['checkedAt'] = date(DATE_ATOM);
$state['lastCheckUrl'] = $checkUrl;

saveState($stateFile, $state);

$statusText = $isAvailable ? 'AVAILABLE' : 'NOT AVAILABLE';
echo sprintf("[%s] Fischer-Vroni: %s\n", date('Y-m-d H:i:s'), $statusText);

if ($force || $changed) {
    $changeText = $previous === null
        ? 'Initial status check'
        : (($isAvailable ? 'Status changed: AVAILABLE' : 'Status changed: NOT AVAILABLE'));

    $message = implode("\n", [
        'Oktoberfest Monitor',
        'Tent: Fischer-Vroni',
        $changeText,
        'Current: ' . $statusText,
        'Time: ' . date('Y-m-d H:i:s'),
        'URL: ' . $checkUrl,
    ]);

    $sent = sendTelegramMessage($botToken, $target, $message, $threadId);
    if (!$sent) {
        fwrite(STDERR, "Telegram message failed to send.\n");
        exit(3);
    }

    echo sprintf("Telegram notification sent to %s.\n", $target);
} else {
    echo "No status change, no Telegram message sent.\n";
}

exit(0);

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

        $value = trim($value, "\"'");
        $data[$key] = $value;
    }

    return $data;
}

function fetchUrl(string $url): ?string
{
    $opts = [
        'http' => [
            'method' => 'GET',
            'timeout' => 20,
            'header' => "User-Agent: OktoberfestMonitor/1.0\r\nAccept: text/html,*/*\r\n",
        ],
    ];

    $context = stream_context_create($opts);
    $content = @file_get_contents($url, false, $context);

    if ($content === false) {
        return null;
    }

    return $content;
}

function detectAvailability(string $html): bool
{
    if (stripos($html, EMPTY_MARKER) !== false) {
        return false;
    }

    // Basic positive signal for product listings.
    return stripos($html, '/shop/fischer-vroni-') !== false || stripos($html, 'Preisspanne:') !== false;
}

function loadState(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function saveState(string $path, array $state): void
{
    file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function sendTelegramMessage(string $botToken, string $target, string $message, ?int $threadId): bool
{
    $url = sprintf('https://api.telegram.org/bot%s/sendMessage', rawurlencode($botToken));
    $payloadData = [
        'chat_id' => $target,
        'text' => $message,
        'disable_web_page_preview' => 'true',
    ];
    if ($threadId !== null) {
        $payloadData['message_thread_id'] = (string) $threadId;
    }

    $payload = http_build_query($payloadData);

    $opts = [
        'http' => [
            'method' => 'POST',
            'timeout' => 20,
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
        ],
    ];

    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);

    return $response !== false;
}
