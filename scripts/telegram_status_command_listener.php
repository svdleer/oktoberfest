<?php

declare(strict_types=1);

/**
 * Telegram command listener for live status checks.
 *
 * Commands:
 *   /status         -> last known state from storage (EN)
 *   /status live    -> fetch current live state from public sources (EN)
 *   /statusde       -> letzter gespeicherter Status (DE)
 *   /statusde live  -> Live-Abruf aus oeffentlichen Quellen (DE)
 *
 * Run periodically (e.g., every minute via cron):
 *   php scripts/telegram_status_command_listener.php
 */

const BASE_SHOP_URL = 'https://tischreservierung-oktoberfest.de/shop/?swoof=1&pa_festzelt=';
const EMPTY_MARKER = 'Es wurden keine Produkte gefunden';
const FISCHER_VRONI_OFFICIAL_DEFAULT_URL = 'https://reservierung.fischer-vroni.de/reservation';
const FISCHER_VRONI_OFFICIAL_NO_AVAIL_MARKER = 'Aktuell gibt es keine Verfügbarkeiten.';

const TENTS = [
    ['name' => 'Fischer-Vroni', 'slug' => 'fischer-vroni'],
    ['name' => 'Hofbraeu', 'slug' => 'hofbraeu-festzelt'],
    ['name' => 'Pschorr Braeurosl', 'slug' => 'festhalle-pschorr-braeurosl'],
    ['name' => 'Hacker', 'slug' => 'hacker-festzelt'],
    ['name' => 'Kufflers', 'slug' => 'kufflers-weinzelt'],
    ['name' => 'Kaefers', 'slug' => 'kaefers-wiesn-schaenke'],
    ['name' => 'Marstall', 'slug' => 'marstall-festzelt'],
    ['name' => 'Paulaner', 'slug' => 'paulaner-festzelt'],
    ['name' => 'Schuetzen', 'slug' => 'schuetzen-festzelt'],
];

$rootDir = dirname(__DIR__);
$envFile = $rootDir . '/.env.telegram';
$stateFile = $rootDir . '/storage/oktoberfest_tent_monitor_state.json';
$offsetFile = $rootDir . '/storage/telegram_status_listener_offset.txt';

$env = loadEnvFile($envFile);
$token = getenv('TELEGRAM_BOT_TOKEN') ?: ($env['TELEGRAM_BOT_TOKEN'] ?? '');
$fischerVroniOfficialUrl = getenv('FISCHER_VRONI_OFFICIAL_URL') ?: ($env['FISCHER_VRONI_OFFICIAL_URL'] ?? FISCHER_VRONI_OFFICIAL_DEFAULT_URL);

if ($token === '') {
    fwrite(STDERR, "Missing TELEGRAM_BOT_TOKEN in .env.telegram\n");
    exit(1);
}

if (!is_dir($rootDir . '/storage')) {
    mkdir($rootDir . '/storage', 0775, true);
}

$offset = 0;
if (is_file($offsetFile)) {
    $rawOffset = trim((string) file_get_contents($offsetFile));
    if (ctype_digit($rawOffset)) {
        $offset = (int) $rawOffset;
    }
}

$updates = telegramApi($token, 'getUpdates', [
    'offset' => (string) $offset,
    'timeout' => '0',
    'allowed_updates' => json_encode(['message']),
]);

if (!($updates['ok'] ?? false)) {
    fwrite(STDERR, "getUpdates failed: " . ($updates['description'] ?? 'unknown') . "\n");
    exit(2);
}

$items = $updates['result'] ?? [];
if (!is_array($items) || count($items) === 0) {
    echo "No command updates.\n";
    exit(0);
}

$latestUpdateId = $offset;
$processed = 0;

foreach ($items as $update) {
    $updateId = (int) ($update['update_id'] ?? 0);
    if ($updateId >= $latestUpdateId) {
        $latestUpdateId = $updateId + 1;
    }

    $msg = $update['message'] ?? null;
    if (!is_array($msg)) {
        continue;
    }

    $text = trim((string) ($msg['text'] ?? ''));
    if ($text === '') {
        continue;
    }

    $normalized = strtolower($text);
    $isStatusEn = str_starts_with($normalized, '/status');
    $isStatusDe = str_starts_with($normalized, '/statusde');
    if (!$isStatusEn && !$isStatusDe) {
        continue;
    }

    $lang = $isStatusDe ? 'de' : 'en';

    $chatId = (string) ($msg['chat']['id'] ?? '');
    if ($chatId === '') {
        continue;
    }

    $threadId = isset($msg['message_thread_id']) ? (int) $msg['message_thread_id'] : null;
    $live = str_contains($normalized, ' live') || str_contains($normalized, ' now');

    $statusRows = $live
        ? getLiveStatuses($fischerVroniOfficialUrl)
        : getStoredStatuses($stateFile);

    $header = $lang === 'de'
        ? ($live ? 'Oktoberfest Status (live)' : 'Oktoberfest Status (letzter Stand)')
        : ($live ? 'Oktoberfest Status (live)' : 'Oktoberfest Status (last known)');
    $timeLabel = $lang === 'de' ? 'Zeit' : 'Time';
    $availLabel = $lang === 'de' ? 'VERFUEGBAR' : 'AVAILABLE';
    $notAvailLabel = $lang === 'de' ? 'NICHT VERFUEGBAR' : 'NOT AVAILABLE';
    $tipLabel = $lang === 'de' ? 'Tipp' : 'Tip';
    $tipCommand = $lang === 'de' ? '/statusde live' : '/status live';

    $lines = [$header, $timeLabel . ': ' . date('Y-m-d H:i:s')];

    foreach ($statusRows as $row) {
        $lines[] = '- ' . $row['name'] . ': ' . ($row['available'] ? $availLabel : $notAvailLabel);
    }

    $lines[] = '';
    $lines[] = $tipLabel . ': ' . $tipCommand;

    $sent = telegramApi($token, 'sendMessage', [
        'chat_id' => $chatId,
        'text' => implode("\n", $lines),
        'disable_web_page_preview' => 'true',
        'message_thread_id' => $threadId !== null ? (string) $threadId : null,
    ]);

    if (!($sent['ok'] ?? false)) {
        fwrite(STDERR, "sendMessage failed: " . ($sent['description'] ?? 'unknown') . "\n");
        continue;
    }

    $processed++;
}

file_put_contents($offsetFile, (string) $latestUpdateId);
echo "Processed commands: {$processed}\n";
exit(0);

function getStoredStatuses(string $stateFile): array
{
    if (!is_file($stateFile)) {
        return [];
    }

    $raw = file_get_contents($stateFile);
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    $tents = $decoded['tents'] ?? [];
    if (!is_array($tents)) {
        return [];
    }

    $rows = [];
    foreach (TENTS as $tent) {
        $slug = $tent['slug'];
        $name = $tent['name'];
        $rows[] = [
            'name' => $name,
            'available' => (bool) ($tents[$slug]['isAvailable'] ?? false),
        ];
    }

    return $rows;
}

function getLiveStatuses(string $fischerVroniOfficialUrl): array
{
    $rows = [];
    foreach (TENTS as $tent) {
        $slug = $tent['slug'];
        $name = $tent['name'];

        $marketHtml = fetchUrl(BASE_SHOP_URL . rawurlencode($slug));
        $marketAvailable = false;
        if ($marketHtml !== null) {
            $marketAvailable = detectMarketplaceAvailability($marketHtml, $slug);
        }

        $available = $marketAvailable;
        if ($slug === 'fischer-vroni') {
            $officialHtml = fetchUrl($fischerVroniOfficialUrl);
            if ($officialHtml !== null) {
                $official = detectFischerVroniOfficialAvailability($officialHtml);
                if ($official !== null) {
                    $available = $official;
                }
            }
        }

        $rows[] = ['name' => $name, 'available' => $available];
    }

    return $rows;
}

function detectMarketplaceAvailability(string $html, string $slug): bool
{
    if (stripos($html, EMPTY_MARKER) !== false) {
        return false;
    }

    return stripos($html, '/shop/' . $slug . '-') !== false || stripos($html, 'Preisspanne:') !== false;
}

function detectFischerVroniOfficialAvailability(string $html): ?bool
{
    if (stripos($html, FISCHER_VRONI_OFFICIAL_NO_AVAIL_MARKER) !== false) {
        return false;
    }

    if (stripos($html, 'Reservierungen') !== false) {
        return true;
    }

    return null;
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

    return $content === false ? null : $content;
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

    $env = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $env[trim($parts[0])] = trim($parts[1]);
        }
    }

    return $env;
}

function telegramApi(string $token, string $method, array $params): array
{
    // Remove null values to avoid malformed Bot API requests.
    $clean = array_filter($params, static fn($v) => $v !== null);

    $url = 'https://api.telegram.org/bot' . $token . '/' . $method;
    $opts = [
        'http' => [
            'method' => 'POST',
            'timeout' => 20,
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($clean),
            'ignore_errors' => true,
        ],
    ];

    $ctx = stream_context_create($opts);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        return ['ok' => false, 'description' => 'request failed'];
    }

    $json = json_decode($raw, true);
    if (!is_array($json)) {
        return ['ok' => false, 'description' => 'non-json response'];
    }

    return $json;
}
