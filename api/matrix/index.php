<?php

declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$timeslot = strtolower((string) ($_GET['timeslot'] ?? 'all'));
if (!in_array($timeslot, ['all', 'mittag', 'abend'], true)) {
    $timeslot = 'all';
}

$dates = [
    '19.09.2026 (Sa)',
    '21.09.2026 (Mo)',
    '26.09.2026 (Sa)',
    '28.09.2026 (Mo)',
    '03.10.2026 (Sa)',
];

$officialReservationUrlMap = [
    'fischer-vroni' => 'https://reservierung.fischer-vroni.de/reservation',
    'hofbraeu-festzelt' => 'https://reservierung.hb-festzelt.de/reservierung',
    'festhalle-pschorr-braeurosl' => 'https://reservierung.braeurosl.de/',
    'hacker-festzelt' => 'https://reservierung.derhimmelderbayern.de/',
    'kaefers-wiesn-schaenke' => 'https://wiesnresmittag.kaefer-wiesn.de/',
    'marstall-festzelt' => 'https://reservierung.marstall-oktoberfest.de/',
    'paulaner-festzelt' => 'https://reservierung.paulanerfestzelt.de/',
    'schuetzen-festzelt' => 'https://schuetzen-festzelt.de/de/reservierung.html',
];

$defaultTentImageUrlMap = [
    'fischer-vroni' => 'https://fzos-core-production-public.fsn1.your-objectstorage.com/portal-header-images/01KH1RKHYC3KQXWHVT91HBDCB2.jpg',
];
$envTentImageUrlMap = resolveTentImageUrlMapFromEnv();
$tentImageUrlMap = array_merge($defaultTentImageUrlMap, $envTentImageUrlMap);

// Data is derived from the currently observed shop availability checks.
$venues = [
    [
        'name' => 'Fischer-Vroni',
        'slug' => 'fischer-vroni',
        'ticketTypes' => [
            'guestGroups' => ['No active listings detected'],
            'tableSizes' => ['No active listings detected'],
            'timeslots' => ['No active listings detected'],
        ],
        'sales' => [
            'open' => true,
            'note' => 'Shop is live, but this venue currently shows no matching products.',
        ],
        'slots' => [
            '19.09.2026 (Sa)' => ['mittag' => 'red', 'abend' => 'red'],
            '21.09.2026 (Mo)' => ['mittag' => 'red', 'abend' => 'red'],
            '26.09.2026 (Sa)' => ['mittag' => 'red', 'abend' => 'red'],
            '28.09.2026 (Mo)' => ['mittag' => 'red', 'abend' => 'red'],
            '03.10.2026 (Sa)' => ['mittag' => 'red', 'abend' => 'red'],
        ],
    ],
    [
        'name' => 'Hofbraeu Festzelt',
        'slug' => 'hofbraeu-festzelt',
        'ticketTypes' => [
            'guestGroups' => ['2-7', '8', '8-10', '11-20'],
            'tableSizes' => ['Kleingruppe', '8er', '10er', '20er'],
            'timeslots' => ['Mittag', 'Abend'],
        ],
        'sales' => ['open' => true, 'note' => 'Listings currently available.'],
        'slots' => [
            '19.09.2026 (Sa)' => ['mittag' => 'green', 'abend' => 'green'],
            '21.09.2026 (Mo)' => ['mittag' => 'green', 'abend' => 'green'],
            '26.09.2026 (Sa)' => ['mittag' => 'green', 'abend' => 'green'],
            '28.09.2026 (Mo)' => ['mittag' => 'green', 'abend' => 'green'],
            '03.10.2026 (Sa)' => ['mittag' => 'green', 'abend' => 'green'],
        ],
    ],
    [
        'name' => 'Festhalle Pschorr Braeurosl',
        'slug' => 'festhalle-pschorr-braeurosl',
        'ticketTypes' => [
            'guestGroups' => ['2-7', '8-10', '11-20'],
            'tableSizes' => ['Kleingruppe', '10er', '20er'],
            'timeslots' => ['Mittag', 'Abend'],
        ],
        'sales' => ['open' => true, 'note' => 'Listings currently available.'],
        'slots' => [
            '19.09.2026 (Sa)' => ['mittag' => 'green', 'abend' => 'green'],
            '21.09.2026 (Mo)' => ['mittag' => 'red', 'abend' => 'green'],
            '26.09.2026 (Sa)' => ['mittag' => 'green', 'abend' => 'green'],
            '28.09.2026 (Mo)' => ['mittag' => 'red', 'abend' => 'green'],
            '03.10.2026 (Sa)' => ['mittag' => 'green', 'abend' => 'green'],
        ],
    ],
    [
        'name' => 'Hacker Festzelt',
        'slug' => 'hacker-festzelt',
        'ticketTypes' => [
            'guestGroups' => ['2-7', '8-10', '11-20'],
            'tableSizes' => ['Kleingruppe', '10er', '20er'],
            'timeslots' => ['Mittag', 'Abend'],
        ],
        'sales' => ['open' => true, 'note' => 'Listings currently available.'],
        'slots' => [
            '19.09.2026 (Sa)' => ['mittag' => 'green', 'abend' => 'green'],
            '21.09.2026 (Mo)' => ['mittag' => 'green', 'abend' => 'green'],
            '26.09.2026 (Sa)' => ['mittag' => 'green', 'abend' => 'green'],
            '28.09.2026 (Mo)' => ['mittag' => 'green', 'abend' => 'green'],
            '03.10.2026 (Sa)' => ['mittag' => 'green', 'abend' => 'green'],
        ],
    ],
    [
        'name' => 'Kufflers Weinzelt',
        'slug' => 'kufflers-weinzelt',
        'ticketTypes' => [
            'guestGroups' => ['8-10', '11-20'],
            'tableSizes' => ['10er', '20er'],
            'timeslots' => ['Mittag', 'Nachmittag', 'Abend'],
        ],
        'sales' => ['open' => true, 'note' => 'Listings currently available.'],
        'slots' => [
            '19.09.2026 (Sa)' => ['mittag' => 'green', 'abend' => 'green'],
            '21.09.2026 (Mo)' => ['mittag' => 'green', 'abend' => 'green'],
            '26.09.2026 (Sa)' => ['mittag' => 'green', 'abend' => 'green'],
            '28.09.2026 (Mo)' => ['mittag' => 'green', 'abend' => 'green'],
            '03.10.2026 (Sa)' => ['mittag' => 'green', 'abend' => 'green'],
        ],
    ],
    [
        'name' => 'Kaefers Wiesn Schenke',
        'slug' => 'kaefers-wiesn-schaenke',
        'ticketTypes' => [
            'guestGroups' => ['8', '8-10', '16'],
            'tableSizes' => ['8er', '10er', '16er'],
            'timeslots' => ['Mittag', 'Nachmittag'],
        ],
        'sales' => ['open' => true, 'note' => 'Partial availability by day/time.'],
        'slots' => [
            '19.09.2026 (Sa)' => ['mittag' => 'unavail', 'abend' => 'unavail'],
            '21.09.2026 (Mo)' => ['mittag' => 'red', 'abend' => 'red'],
            '26.09.2026 (Sa)' => ['mittag' => 'green', 'abend' => 'red'],
            '28.09.2026 (Mo)' => ['mittag' => 'red', 'abend' => 'red'],
            '03.10.2026 (Sa)' => ['mittag' => 'unavail', 'abend' => 'unavail'],
        ],
    ],
    [
        'name' => 'Marstall Festzelt',
        'slug' => 'marstall-festzelt',
        'ticketTypes' => [
            'guestGroups' => ['8', '8-10', '11-20', '24'],
            'tableSizes' => ['8er', '10er', '16er', '20er', '24er'],
            'timeslots' => ['Mittag', 'Nachmittag', 'Abend'],
        ],
        'sales' => ['open' => true, 'note' => 'Listings currently available.'],
        'slots' => [
            '19.09.2026 (Sa)' => ['mittag' => 'green', 'abend' => 'green'],
            '21.09.2026 (Mo)' => ['mittag' => 'green', 'abend' => 'green'],
            '26.09.2026 (Sa)' => ['mittag' => 'green', 'abend' => 'green'],
            '28.09.2026 (Mo)' => ['mittag' => 'green', 'abend' => 'green'],
            '03.10.2026 (Sa)' => ['mittag' => 'green', 'abend' => 'green'],
        ],
    ],
    [
        'name' => 'Paulaner Festzelt',
        'slug' => 'paulaner-festzelt',
        'ticketTypes' => [
            'guestGroups' => ['2-7', '8-10', '11-20'],
            'tableSizes' => ['Kleingruppe', '10er', '20er'],
            'timeslots' => ['Mittag', 'Abend'],
        ],
        'sales' => ['open' => true, 'note' => 'Listings currently available.'],
        'slots' => [
            '19.09.2026 (Sa)' => ['mittag' => 'green', 'abend' => 'green'],
            '21.09.2026 (Mo)' => ['mittag' => 'green', 'abend' => 'green'],
            '26.09.2026 (Sa)' => ['mittag' => 'green', 'abend' => 'green'],
            '28.09.2026 (Mo)' => ['mittag' => 'green', 'abend' => 'green'],
            '03.10.2026 (Sa)' => ['mittag' => 'green', 'abend' => 'green'],
        ],
    ],
    [
        'name' => 'Schuetzen Festzelt',
        'slug' => 'schuetzen-festzelt',
        'ticketTypes' => [
            'guestGroups' => ['8', '8-10'],
            'tableSizes' => ['8er', '10er'],
            'timeslots' => ['Mittag', 'Abend'],
        ],
        'sales' => ['open' => true, 'note' => 'Limited by weekday.'],
        'slots' => [
            '19.09.2026 (Sa)' => ['mittag' => 'green', 'abend' => 'green'],
            '21.09.2026 (Mo)' => ['mittag' => 'red', 'abend' => 'red'],
            '26.09.2026 (Sa)' => ['mittag' => 'green', 'abend' => 'green'],
            '28.09.2026 (Mo)' => ['mittag' => 'red', 'abend' => 'red'],
            '03.10.2026 (Sa)' => ['mittag' => 'green', 'abend' => 'green'],
        ],
    ],
];

$weekdayByDate = [
    '19.09.2026 (Sa)' => 'samstag',
    '21.09.2026 (Mo)' => 'montag',
    '26.09.2026 (Sa)' => 'samstag',
    '28.09.2026 (Mo)' => 'montag',
    '03.10.2026 (Sa)' => 'samstag',
];

$imageCachePath = dirname(__DIR__, 2) . '/storage/tent_image_cache.json';
$imageCache = loadImageCache($imageCachePath);

$tents = [];
foreach ($venues as $venue) {
    $matrix = [];

    foreach ($dates as $date) {
        $slotStatuses = $venue['slots'][$date] ?? ['mittag' => 'unavail', 'abend' => 'unavail'];
        $mittagStatus = $slotStatuses['mittag'] ?? 'unavail';
        $abendStatus = $slotStatuses['abend'] ?? 'unavail';

        if ($timeslot === 'mittag') {
            $status = $mittagStatus;
        } elseif ($timeslot === 'abend') {
            $status = $abendStatus;
        } else {
            if ($mittagStatus === 'green' || $abendStatus === 'green') {
                $status = 'green';
            } elseif ($mittagStatus === 'red' || $abendStatus === 'red') {
                $status = 'red';
            } else {
                $status = 'unavail';
            }
        }

        $slotLinks = [];
        $officialReservationUrl = $officialReservationUrlMap[(string) $venue['slug']] ?? '#';
        foreach (['mittag', 'abend'] as $slotName) {
            $slotLinks[$slotName] = $officialReservationUrl;
        }

        $matrix[$date] = [
            'status' => $status,
            'slotStatus' => [
                'mittag' => $mittagStatus,
                'abend' => $abendStatus,
            ],
            'slotLinks' => $slotLinks,
        ];
    }

    $tents[] = [
        'name' => $venue['name'],
        'slug' => $venue['slug'],
        'reservationUrl' => $officialReservationUrlMap[(string) $venue['slug']] ?? '#',
        'imageUrl' => $tentImageUrlMap[(string) $venue['slug']] ?? resolveTentImageUrl((string) $venue['slug'], $imageCache),
        'ticketTypes' => $venue['ticketTypes'],
        'sales' => $venue['sales'],
        'matrix' => $matrix,
    ];
}

saveImageCache($imageCachePath, $imageCache);

echo json_encode([
    'timeslot' => $timeslot,
    'dates' => $dates,
    'tents' => $tents,
], JSON_UNESCAPED_SLASHES);

function resolveTentImageUrl(string $slug, array &$cache): string
{
    $now = time();
    $ttlSeconds = 24 * 60 * 60;

    $cached = $cache[$slug] ?? null;
    if (
        is_array($cached)
        && isset($cached['url'], $cached['fetchedAt'])
        && is_string($cached['url'])
        && is_int($cached['fetchedAt'])
        && !isGenericImageUrl($cached['url'])
        && ($now - $cached['fetchedAt']) < $ttlSeconds
    ) {
        return $cached['url'];
    }

    $imageUrl = '';
    foreach (resolveTentImageSourceUrls($slug) as $sourceUrl) {
        $html = @file_get_contents($sourceUrl);
        if (!is_string($html) || $html === '') {
            continue;
        }

        $imageUrl = extractBestTentImageFromHtml($html);

        if ($imageUrl === '' && preg_match('/<meta\\s+property="og:image"\\s+content="([^"]+)"/i', $html, $m) === 1) {
            $candidate = trim((string) $m[1]);
            if (!isGenericImageUrl($candidate)) {
                $imageUrl = $candidate;
            }
        }

        if ($imageUrl !== '') {
            break;
        }
    }

    if ($imageUrl === '') {
        $imageUrl = 'https://picsum.photos/seed/' . rawurlencode($slug) . '/320/180';
    }

    $cache[$slug] = [
        'url' => $imageUrl,
        'fetchedAt' => $now,
    ];

    return $imageUrl;
}

function resolveTentImageSourceUrls(string $slug): array
{
    $mainDomains = [
        'fischer-vroni' => 'https://www.fischer-vroni.de/',
        'hofbraeu-festzelt' => 'https://hb-festzelt.de/',
        'festhalle-pschorr-braeurosl' => 'https://www.braeurosl.de/',
        'hacker-festzelt' => 'https://www.derhimmelderbayern.de/',
        'kaefers-wiesn-schaenke' => 'https://www.kaefer-wiesn.de/',
        'marstall-festzelt' => 'https://www.marstall-oktoberfest.de/',
        'paulaner-festzelt' => 'https://www.stiftl-oktoberfest.de/',
        'schuetzen-festzelt' => 'https://schuetzen-festzelt.de/',
    ];

    $sources = [];
    if (isset($mainDomains[$slug])) {
        $sources[] = $mainDomains[$slug];
    }

    return $sources;
}

function resolveTentImageUrlMapFromEnv(): array
{
    $raw = getenv('TENT_IMAGE_URL_MAP');
    if (!is_string($raw) || trim($raw) === '') {
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
        $url = trim($parts[1]);
        if ($slug === '' || $url === '') {
            continue;
        }

        $map[$slug] = $url;
    }

    return $map;
}

function extractBestTentImageFromHtml(string $html): string
{
    if (preg_match_all('/https?:\\/\\/[^"\'\\s>]+\\.(?:jpg|jpeg|png|webp)/i', $html, $matches) < 1) {
        return '';
    }

    foreach ($matches[0] as $url) {
        $candidate = trim((string) $url);
        if ($candidate === '' || isGenericImageUrl($candidate)) {
            continue;
        }
        if (stripos($candidate, '/wp-content/uploads/') !== false) {
            return $candidate;
        }
    }

    foreach ($matches[0] as $url) {
        $candidate = trim((string) $url);
        if ($candidate !== '' && !isGenericImageUrl($candidate)) {
            return $candidate;
        }
    }

    return '';
}

function isGenericImageUrl(string $url): bool
{
    $u = strtolower($url);
    return str_contains($u, 'logo')
        || str_contains($u, '/flags/')
        || str_contains($u, '/plugins/')
        || str_contains($u, 'sitepress-multilingual-cms')
        || str_contains($u, 'woocommerce-products-filter');
}

function loadImageCache(string $path): array
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

function saveImageCache(string $path, array $cache): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    @file_put_contents($path, json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}
