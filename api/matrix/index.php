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

$all = [
    'Fischer-Vroni' => ['red', 'red', 'red', 'red', 'red'],
    'Hofbraeu Festzelt' => ['green', 'green', 'green', 'green', 'green'],
    'Festhalle Pschorr Braeurosl' => ['green', 'green', 'green', 'green', 'green'],
    'Hacker Festzelt' => ['green', 'green', 'green', 'green', 'green'],
    'Kufflers Weinzelt' => ['green', 'green', 'green', 'green', 'green'],
    'Kaefers Wiesn Schenke' => ['unavail', 'red', 'green', 'red', 'unavail'],
    'Marstall Festzelt' => ['green', 'green', 'green', 'green', 'green'],
    'Paulaner Festzelt' => ['green', 'green', 'green', 'green', 'green'],
    'Schuetzen Festzelt' => ['green', 'red', 'green', 'red', 'green'],
];

$mittag = [
    'Fischer-Vroni' => ['red', 'red', 'red', 'red', 'red'],
    'Hofbraeu Festzelt' => ['green', 'green', 'green', 'green', 'green'],
    'Festhalle Pschorr Braeurosl' => ['green', 'red', 'green', 'red', 'green'],
    'Hacker Festzelt' => ['green', 'green', 'green', 'green', 'green'],
    'Kufflers Weinzelt' => ['green', 'green', 'green', 'green', 'green'],
    'Kaefers Wiesn Schenke' => ['unavail', 'red', 'green', 'red', 'unavail'],
    'Marstall Festzelt' => ['green', 'green', 'green', 'green', 'green'],
    'Paulaner Festzelt' => ['green', 'green', 'green', 'green', 'green'],
    'Schuetzen Festzelt' => ['green', 'red', 'green', 'red', 'green'],
];

$abend = [
    'Fischer-Vroni' => ['red', 'red', 'red', 'red', 'red'],
    'Hofbraeu Festzelt' => ['green', 'green', 'green', 'green', 'green'],
    'Festhalle Pschorr Braeurosl' => ['green', 'green', 'green', 'green', 'green'],
    'Hacker Festzelt' => ['green', 'green', 'green', 'green', 'green'],
    'Kufflers Weinzelt' => ['green', 'green', 'green', 'green', 'green'],
    'Kaefers Wiesn Schenke' => ['unavail', 'red', 'red', 'red', 'unavail'],
    'Marstall Festzelt' => ['green', 'green', 'green', 'green', 'green'],
    'Paulaner Festzelt' => ['green', 'green', 'green', 'green', 'green'],
    'Schuetzen Festzelt' => ['green', 'red', 'green', 'red', 'green'],
];

$source = $timeslot === 'mittag' ? $mittag : ($timeslot === 'abend' ? $abend : $all);

$tents = [];
foreach ($source as $name => $statuses) {
    $mapped = [];
    foreach ($dates as $index => $date) {
        $mapped[$date] = $statuses[$index] ?? 'unavail';
    }

    $tents[] = [
        'name' => $name,
        'status' => $mapped,
    ];
}

echo json_encode([
    'timeslot' => $timeslot,
    'dates' => $dates,
    'tents' => $tents,
], JSON_UNESCAPED_SLASHES);
