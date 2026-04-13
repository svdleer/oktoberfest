<?php

declare(strict_types=1);

/**
 * Oktoberfest tent availability monitor with Telegram alerts.
 *
 * Usage:
 *   php scripts/fischer_vroni_telegram_monitor.php
 *   php scripts/fischer_vroni_telegram_monitor.php --force
 */

const FISCHER_VRONI_OFFICIAL_DEFAULT_URL = 'https://reservierung.fischer-vroni.de/reservation';
const FISCHER_VRONI_OFFICIAL_NO_AVAIL_MARKER = 'Aktuell gibt es keine Verfügbarkeiten.';
const DEFAULT_OFFICIAL_TENT_URL_MAP = [
    'fischer-vroni' => 'https://reservierung.fischer-vroni.de/reservation',
    'hofbraeu-festzelt' => 'https://reservierung.hb-festzelt.de/reservierung',
    'festhalle-pschorr-braeurosl' => 'https://reservierung.braeurosl.de/',
    'hacker-festzelt' => 'https://reservierung.derhimmelderbayern.de/',
    'kaefers-wiesn-schaenke' => 'https://wiesnresmittag.kaefer-wiesn.de/',
    'marstall-festzelt' => 'https://reservierung.marstall-oktoberfest.de/',
    'schuetzen-festzelt' => 'https://schuetzen-festzelt.de/de/reservierung.html',
    'paulaner-festzelt' => 'https://reservierung.paulanerfestzelt.de/',
];

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
$officialTentUrlMap = resolveOfficialTentUrlMap($env);
$officialTentUrlMap['fischer-vroni'] = $fischerVroniOfficialUrl;
$officialTentCookieMap = resolveOfficialTentCookieMap($env);
$fischerVroniFormatAlertEnabled = envBool(
    getenv('FISCHER_VRONI_FORMAT_ALERT') ?: ($env['FISCHER_VRONI_FORMAT_ALERT'] ?? 'true'),
    true
);
$fischerVroniFormatTopicRaw = getenv('FISCHER_VRONI_FORMAT_TOPIC_ID') ?: ($env['FISCHER_VRONI_FORMAT_TOPIC_ID'] ?? '');
$fischerVroniFormatTopicId = ctype_digit((string) $fischerVroniFormatTopicRaw) ? (int) $fischerVroniFormatTopicRaw : null;
$fischerVroniActivationAlertEnabled = envBool(
    getenv('FISCHER_VRONI_ACTIVATION_ALERT') ?: ($env['FISCHER_VRONI_ACTIVATION_ALERT'] ?? 'true'),
    true
);
$fischerVroniActivationTopicRaw = getenv('FISCHER_VRONI_ACTIVATION_TOPIC_ID') ?: ($env['FISCHER_VRONI_ACTIVATION_TOPIC_ID'] ?? '');
$fischerVroniActivationTopicId = ctype_digit((string) $fischerVroniActivationTopicRaw) ? (int) $fischerVroniActivationTopicRaw : null;
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

    $officialUrl = $officialTentUrlMap[$slug] ?? null;
    if (!is_string($officialUrl) || $officialUrl === '') {
        fwrite(STDERR, "No official URL configured for tent: {$slug}\n");
        continue;
    }

    $officialCookie = $officialTentCookieMap[$slug] ?? null;
    $officialHtml = fetchUrl($officialUrl, $officialCookie);
    if ($officialHtml === null) {
        fwrite(STDERR, "Failed to fetch official URL: {$officialUrl}\n");
        continue;
    }

    $officialSignal = null;
    $officialFingerprintHash = null;
    $officialFingerprintSignal = null;
    $formatChanged = false;
    $previousFingerprint = null;
    $activationDetected = false;
    $activationChanged = false;
    $previousActivationDetected = false;

    if ($slug === 'fischer-vroni') {
        $officialSignal = detectFischerVroniOfficialAvailability($officialHtml);
        $activationDetected = detectFischerVroniActivationSignal($officialHtml);
        $previousActivationDetected = (bool) ($state['tents'][$slug]['activationDetected'] ?? false);
        $activationChanged = $fischerVroniActivationAlertEnabled && ($activationDetected !== $previousActivationDetected);
        $fingerprint = buildFischerVroniFingerprint($officialHtml);
        $officialFingerprintHash = $fingerprint['hash'];
        $officialFingerprintSignal = $fingerprint['signal'];
        $previousFingerprint = $state['tents'][$slug]['officialFingerprintHash'] ?? null;
        $formatChanged = $fischerVroniFormatAlertEnabled
            && $previousFingerprint !== null
            && $officialFingerprintHash !== null
            && $previousFingerprint !== $officialFingerprintHash;
    } else {
        $officialSignal = detectGenericOfficialAvailability($officialHtml);
    }

    $previous = $state['tents'][$slug]['isAvailable'] ?? null;
    $isAvailable = $officialSignal !== null
        ? $officialSignal
        : (($previous !== null) ? (bool) $previous : false);
    $changed = ($previous === null) || ((bool) $previous !== $isAvailable);

    $state['tents'][$slug] = [
        'name' => $tentName,
        'isAvailable' => $isAvailable,
        'checkedAt' => date(DATE_ATOM),
        'lastCheckUrl' => $officialUrl,
        'officialFingerprintHash' => $officialFingerprintHash,
        'officialFingerprintSignal' => $officialFingerprintSignal,
        'activationDetected' => $activationDetected,
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

    $signalSource = 'official_portal:' . $officialUrl;

    $message = implode("\n", [
        'Oktoberfest Monitor',
        'Tent: ' . $tentName,
        $changeText,
        'Current: ' . $statusText,
        'Signal source: ' . $signalSource,
        'Time: ' . date('Y-m-d H:i:s'),
        'URL: ' . $officialUrl,
    ]);

    if ($officialSignal !== null && is_string($officialUrl) && $officialUrl !== '') {
        $message .= "\nOfficial URL: " . $officialUrl;
    }

    $shouldSendStandardSignal = $changed || $force;
    if ($shouldSendStandardSignal) {
        foreach ($targets as $targetConfig) {
            $threadId = $tentTopicMap[$slug] ?? $targetConfig['thread_id'];
            $targetChatId = (string) $targetConfig['chat_id'];

            $sent = sendTelegramMessage($botToken, $targetChatId, $message, $threadId);
            if (!$sent) {
                $failedTargets[] = $targetChatId . ' [' . $slug . ']';
                continue;
            }

            echo sprintf("Telegram notification sent to %s (%s).\n", $targetChatId, $slug);
        }
    }

    if ($slug === 'fischer-vroni' && $formatChanged && ($changed || $force)) {
        $formatMessage = implode("\n", [
            'Oktoberfest Monitor',
            'Tent: Fischer-Vroni',
            'Official page signal format changed',
            'Current: ' . $statusText,
            'Signal source: official_fischer_vroni_portal',
            'Time: ' . date('Y-m-d H:i:s'),
            'Official URL: ' . $fischerVroniOfficialUrl,
            'Fingerprint: ' . substr((string) ($previousFingerprint ?? ''), 0, 12) . ' -> ' . substr((string) ($officialFingerprintHash ?? ''), 0, 12),
        ]);

        foreach ($targets as $targetConfig) {
            $targetChatId = (string) $targetConfig['chat_id'];
            $threadId = $fischerVroniFormatTopicId ?? ($tentTopicMap[$slug] ?? $targetConfig['thread_id']);

            $sent = sendTelegramMessage($botToken, $targetChatId, $formatMessage, $threadId);
            if (!$sent) {
                $failedTargets[] = $targetChatId . ' [' . $slug . ':format-change]';
                continue;
            }

            echo sprintf("Format-change alert sent to %s (%s).\n", $targetChatId, $slug);
        }
    }

    if ($slug === 'fischer-vroni') {
        $activationJustOpened = $activationChanged && $activationDetected && !$previousActivationDetected;
        $shouldSendActivationSignal = ($changed || $force) && ($activationJustOpened || ($force && $activationDetected));

        if ($shouldSendActivationSignal) {
            $activationMessage = implode("\n", [
                'Oktoberfest Monitor',
                'Tent: Fischer-Vroni',
                'Booking form activation detected',
                'Signal source: official_fischer_vroni_portal',
                'Time: ' . date('Y-m-d H:i:s'),
                'Official URL: ' . $fischerVroniOfficialUrl,
                'Hint: booking fields on public page are no longer empty.',
            ]);

            foreach ($targets as $targetConfig) {
                $targetChatId = (string) $targetConfig['chat_id'];
                $threadId = $fischerVroniActivationTopicId
                    ?? $fischerVroniFormatTopicId
                    ?? ($tentTopicMap[$slug] ?? $targetConfig['thread_id']);

                $sent = sendTelegramMessage($botToken, $targetChatId, $activationMessage, $threadId);
                if (!$sent) {
                    $failedTargets[] = $targetChatId . ' [' . $slug . ':activation]';
                    continue;
                }

                echo sprintf("Activation alert sent to %s (%s).\n", $targetChatId, $slug);
            }
        }
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

function fetchUrl(string $url, ?string $cookieHeader = null): ?string
{
    $headers = "User-Agent: OktoberfestMonitor/1.0\r\nAccept: text/html,*/*\r\n";
    if (is_string($cookieHeader) && trim($cookieHeader) !== '') {
        $headers .= 'Cookie: ' . trim($cookieHeader) . "\r\n";
    }

    $opts = [
        'http' => [
            'method' => 'GET',
            'timeout' => 20,
            'header' => $headers,
        ],
    ];

    $context = stream_context_create($opts);

    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $content = @file_get_contents($url, false, $context);
        if ($content !== false && $content !== '') {
            return $content;
        }

        // brief backoff for transient network/host issues
        usleep($attempt * 200000);
    }

    // Fallback for hosts that are sensitive to PHP stream wrappers.
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'OktoberfestMonitor/1.0',
            CURLOPT_HTTPHEADER => ['Accept: text/html,*/*'],
        ]);
        if (is_string($cookieHeader) && trim($cookieHeader) !== '') {
            curl_setopt($ch, CURLOPT_COOKIE, trim($cookieHeader));
        }

        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        if (is_string($body) && $body !== '' && $httpCode >= 200 && $httpCode < 400) {
            return $body;
        }
    }

    return null;
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
        return true;
    }

    return null;
}

function detectFischerVroniActivationSignal(string $html): bool
{
    // If explicit "no availability" marker exists, booking is not active.
    if (stripos($html, FISCHER_VRONI_OFFICIAL_NO_AVAIL_MARKER) !== false) {
        return false;
    }

    $hasBookingListId = preg_match('/booking_list_id&quot;:(?!null)\d+/i', $html) === 1;
    $hasSeatplanArea = preg_match('/seatplan_area_id&quot;:(?!null)\d+/i', $html) === 1;
    $hasDateValue = preg_match('/&quot;date&quot;:&quot;[^&]+&quot;/i', $html) === 1;

    return $hasBookingListId || $hasSeatplanArea || $hasDateValue;
}

function detectGenericOfficialAvailability(string $html): ?bool
{
    $negativeMarkers = [
        'keine verfügbarkeiten',
        'keine verfuegbarkeiten',
        'aktuell gibt es keine',
        'derzeit keine',
        'momentan keine',
        'ausgebucht',
    ];

    foreach ($negativeMarkers as $marker) {
        if (stripos($html, $marker) !== false) {
            return false;
        }
    }

    $positiveMarkers = [
        'reservierung',
        'reservierungen',
        'buchung',
        'verfügbarkeit',
        'verfuegbarkeit',
    ];

    foreach ($positiveMarkers as $marker) {
        if (stripos($html, $marker) !== false) {
            return true;
        }
    }

    return null;
}

function buildFischerVroniFingerprint(string $html): array
{
    $text = strip_tags($html);
    $text = preg_replace('/\s+/u', ' ', $text) ?? '';
    $text = trim($text);

    $signal = '';
    if (preg_match('/Reservierungen.{0,260}/u', $text, $matches) === 1) {
        $signal = trim((string) $matches[0]);
    }

    if ($signal === '') {
        $signal = substr($text, 0, 260);
    }

    return [
        'hash' => hash('sha256', $signal),
        'signal' => $signal,
    ];
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

function envBool(string $value, bool $default): bool
{
    $normalized = strtolower(trim($value));
    if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }
    if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }
    return $default;
}

function resolveOfficialTentUrlMap(array $env): array
{
    $map = DEFAULT_OFFICIAL_TENT_URL_MAP;

    $raw = getenv('OFFICIAL_TENT_URL_MAP') ?: ($env['OFFICIAL_TENT_URL_MAP'] ?? '');
    if ($raw === '') {
        return $map;
    }

    $entries = array_values(array_filter(array_map('trim', explode(',', $raw)), static function ($value) {
        return $value !== '';
    }));

    foreach ($entries as $entry) {
        $parts = explode(':', $entry, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $slug = trim($parts[0]);
        $url = trim($parts[1]);
        if ($slug === '' || $url === '') {
            continue;
        }

        $map[$slug] = $url;
    }

    return $map;
}

function resolveOfficialTentCookieMap(array $env): array
{
    $raw = getenv('OFFICIAL_TENT_COOKIE_MAP') ?: ($env['OFFICIAL_TENT_COOKIE_MAP'] ?? '');
    if ($raw === '') {
        return [];
    }

    $entries = array_values(array_filter(array_map('trim', explode('|', $raw)), static function ($value) {
        return $value !== '';
    }));

    $map = [];
    foreach ($entries as $entry) {
        $parts = explode('=', $entry, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $slug = trim($parts[0]);
        $cookie = trim($parts[1]);
        if ($slug === '' || $cookie === '') {
            continue;
        }

        $map[$slug] = $cookie;
    }

    return $map;
}
