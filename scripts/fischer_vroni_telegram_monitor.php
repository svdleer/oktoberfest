<?php

declare(strict_types=1);

/**
 * Oktoberfest tent availability monitor with Telegram alerts.
 *
 * Usage:
 *   php scripts/fischer_vroni_telegram_monitor.php
 *   php scripts/fischer_vroni_telegram_monitor.php --force
 */

const BASE_SHOP_URL = 'https://tischreservierung-oktoberfest.de/shop/?swoof=1&pa_festzelt=';
const EMPTY_MARKER = 'Es wurden keine Produkte gefunden';
const FISCHER_VRONI_OFFICIAL_DEFAULT_URL = 'https://reservierung.fischer-vroni.de/reservation';
const FISCHER_VRONI_OFFICIAL_NO_AVAIL_MARKER = 'Aktuell gibt es keine Verfügbarkeiten.';

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
$storageDir = $rootDir . '/storage';
$stateFile = $storageDir . '/oktoberfest_tent_monitor_state.json';
$envFile = $rootDir . '/.env.telegram';

if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
    fwrite(STDERR, "Failed to create storage directory: {$storageDir}\n");
    exit(1);
}

$env = loadEnvFile($envFile);
$botToken = getenv('TELEGRAM_BOT_TOKEN') ?: ($env['TELEGRAM_BOT_TOKEN'] ?? '');
$targets = resolveTelegramTargets($env);
$tentTopicMap = resolveTentTopicMap($env);
$fischerVroniOfficialUrl = getenv('FISCHER_VRONI_OFFICIAL_URL') ?: ($env['FISCHER_VRONI_OFFICIAL_URL'] ?? FISCHER_VRONI_OFFICIAL_DEFAULT_URL);
$force = in_array('--force', $argv, true);

if ($botToken === '' || count($targets) === 0) {
    fwrite(STDERR, "Missing TELEGRAM_BOT_TOKEN or Telegram target config. Configure TELEGRAM_TARGET/TELEGRAM_TARGETS in .env.telegram.\n");
    exit(1);
}

$state = loadState($stateFile);
$state['tents'] = is_array($state['tents'] ?? null) ? $state['tents'] : [];

$failedTargets = [];
$changeCount = 0;

foreach (TENTS as $tent) {
    $tentName = (string) $tent['name'];
    $slug = (string) $tent['slug'];
    $checkUrl = BASE_SHOP_URL . rawurlencode($slug);

    $html = fetchUrl($checkUrl);
    if ($html === null) {
        fwrite(STDERR, "Failed to fetch target URL: {$checkUrl}\n");
        continue;
    }

    $marketIsAvailable = detectAvailability($html, $slug);
    $officialSignal = null;
    if ($slug === 'fischer-vroni') {
        $officialHtml = fetchUrl($fischerVroniOfficialUrl);
        if ($officialHtml !== null) {
            $officialSignal = detectFischerVroniOfficialAvailability($officialHtml);
        }
    }

    $isAvailable = $officialSignal !== null ? $officialSignal : $marketIsAvailable;
    $previous = $state['tents'][$slug]['isAvailable'] ?? null;
    $changed = ($previous === null) || ((bool) $previous !== $isAvailable);

    $state['tents'][$slug] = [
        'name' => $tentName,
        'isAvailable' => $isAvailable,
        'checkedAt' => date(DATE_ATOM),
        'lastCheckUrl' => $checkUrl,
    ];

    $statusText = $isAvailable ? 'AVAILABLE' : 'NOT AVAILABLE';
    echo sprintf("[%s] %s: %s\n", date('Y-m-d H:i:s'), $tentName, $statusText);

    if (!$force && !$changed) {
        continue;
    }

    $changeCount++;
    $changeText = $previous === null
        ? 'Initial status check'
        : (($isAvailable ? 'Status changed: AVAILABLE' : 'Status changed: NOT AVAILABLE'));

    $signalSource = $officialSignal !== null ? 'official_fischer_vroni_portal' : 'marketplace_fallback';

    $message = implode("\n", [
        'Oktoberfest Monitor',
        'Tent: ' . $tentName,
        $changeText,
        'Current: ' . $statusText,
        'Signal source: ' . $signalSource,
        'Time: ' . date('Y-m-d H:i:s'),
        'URL: ' . $checkUrl,
    ]);

    if ($slug === 'fischer-vroni' && $officialSignal !== null) {
        $message .= "\nOfficial URL: " . $fischerVroniOfficialUrl;
    }

    foreach ($targets as $targetConfig) {
        $threadId = $tentTopicMap[$slug] ?? $targetConfig['thread_id'];
        $targetChatId = (string) $targetConfig['chat_id'];

        $sent = sendTelegramMessage(
            $botToken,
            $targetChatId,
            $message,
            $threadId
        );

        if (!$sent) {
            $failedTargets[] = $targetChatId . ' [' . $slug . ']';
            continue;
        }

        echo sprintf("Telegram notification sent to %s (%s).\n", $targetChatId, $slug);
    }
}

$state['checkedAt'] = date(DATE_ATOM);
saveState($stateFile, $state);

if ($changeCount === 0) {
    echo "No status changes, no Telegram messages sent.\n";
}

if (count($failedTargets) > 0) {
    fwrite(STDERR, 'Telegram send failed for: ' . implode(', ', $failedTargets) . "\n");
    exit(3);
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

function detectAvailability(string $html, string $slug): bool
{
    if (stripos($html, EMPTY_MARKER) !== false) {
        return false;
    }

    // Basic positive signal for product listings.
    return stripos($html, '/shop/' . $slug . '-') !== false || stripos($html, 'Preisspanne:') !== false;
}

function detectFischerVroniOfficialAvailability(string $html): ?bool
{
    if (stripos($html, FISCHER_VRONI_OFFICIAL_NO_AVAIL_MARKER) !== false) {
        return false;
    }

    if (
        stripos($html, 'Reservierungen') !== false &&
        stripos($html, FISCHER_VRONI_OFFICIAL_NO_AVAIL_MARKER) === false
    ) {
        // No explicit "no availability" marker present on official page.
        return true;
    }

    // Unknown/changed page format -> let caller fallback to marketplace source.
    return null;
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

function resolveTelegramTargets(array $env): array
{
    $rawTargets = getenv('TELEGRAM_TARGETS') ?: ($env['TELEGRAM_TARGETS'] ?? '');
    if ($rawTargets === '') {
        $single = getenv('TELEGRAM_TARGET') ?: ($env['TELEGRAM_TARGET'] ?? '');
        if ($single === '') {
            $single = getenv('TELEGRAM_CHAT_ID') ?: ($env['TELEGRAM_CHAT_ID'] ?? '');
        }
        $rawTargets = $single;
    }

    $targetList = array_values(array_filter(array_map('trim', explode(',', $rawTargets)), static function ($value) {
        return $value !== '';
    }));

    $defaultThreadRaw = getenv('TELEGRAM_MESSAGE_THREAD_ID') ?: ($env['TELEGRAM_MESSAGE_THREAD_ID'] ?? '');
    $defaultThread = ctype_digit((string) $defaultThreadRaw) ? (int) $defaultThreadRaw : null;

    $topicMapRaw = getenv('TELEGRAM_TARGET_TOPIC_MAP') ?: ($env['TELEGRAM_TARGET_TOPIC_MAP'] ?? '');
    $topicMapEntries = array_values(array_filter(array_map('trim', explode(',', $topicMapRaw)), static function ($value) {
        return $value !== '';
    }));

    $topicMap = [];
    foreach ($topicMapEntries as $entry) {
        $parts = explode(':', $entry, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $target = trim($parts[0]);
        $threadRaw = trim($parts[1]);
        if ($target === '' || !ctype_digit($threadRaw)) {
            continue;
        }

        $topicMap[$target] = (int) $threadRaw;
    }

    $resolved = [];
    foreach ($targetList as $target) {
        $resolved[] = [
            'chat_id' => $target,
            'thread_id' => $topicMap[$target] ?? $defaultThread,
        ];
    }

    return $resolved;
}

function resolveTentTopicMap(array $env): array
{
    $raw = getenv('TELEGRAM_TENT_TOPIC_MAP') ?: ($env['TELEGRAM_TENT_TOPIC_MAP'] ?? '');
    $entries = array_values(array_filter(array_map('trim', explode(',', $raw)), static function ($value) {
        return $value !== '';
    }));

    $map = [];
    foreach ($entries as $entry) {
        $parts = explode(':', $entry, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $slug = trim($parts[0]);
        $threadRaw = trim($parts[1]);
        if ($slug === '' || !ctype_digit($threadRaw)) {
            continue;
        }

        $map[$slug] = (int) $threadRaw;
    }

    return $map;
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
