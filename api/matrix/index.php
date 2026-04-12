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
        foreach (['mittag', 'abend'] as $slotName) {
            $slotLinks[$slotName] = sprintf(
                'https://tischreservierung-oktoberfest.de/shop/?swoof=1&pa_festzelt=%s&pa_wochentag=%s&pa_tageszeit=%s',
                rawurlencode((string) $venue['slug']),
                rawurlencode((string) ($weekdayByDate[$date] ?? 'samstag')),
                rawurlencode($slotName)
            );
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
        'reservationUrl' => 'https://tischreservierung-oktoberfest.de/shop/?swoof=1&pa_festzelt=' . rawurlencode((string) $venue['slug']),
        'ticketTypes' => $venue['ticketTypes'],
        'sales' => $venue['sales'],
        'matrix' => $matrix,
    ];
}

echo json_encode([
    'timeslot' => $timeslot,
    'dates' => $dates,
    'tents' => $tents,
], JSON_UNESCAPED_SLASHES);
