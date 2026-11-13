<?php

/*
 * Score days' bills and store their highlights (see htdocs/includes/class.DayHighlights.php), and
 * show what was chosen, and why. In production, rs-machine's cron/day_highlights.php does this;
 * this is for Docker (docker-setup-site.sh runs it for the test fixtures' day), and for tuning the
 * weights against real days.
 *
 *     php deploy/day-highlights.php 2026-01-26 [2026-02-16 ...]
 *     php deploy/day-highlights.php --all-bills 2026-02-16    (every bill's score, not just the highlights)
 */

set_include_path(__DIR__ . '/../htdocs/includes' . PATH_SEPARATOR . get_include_path());
require_once __DIR__ . '/../htdocs/includes/settings.inc.php';
require_once __DIR__ . '/../htdocs/includes/vendor/autoload.php';

$database = new Database();
$database->connect_mysqli();

$args = array_slice($argv, 1);
$all_bills = in_array('--all-bills', $args, true);
$dates = array_values(array_filter($args, fn($arg) => $arg !== '--all-bills'));
if (count($dates) === 0) {
    fwrite(STDERR, "Usage: php deploy/day-highlights.php [--all-bills] YYYY-MM-DD [...]\n");
    exit(1);
}

foreach ($dates as $date) {
    if (!Day::is_date($date)) {
        fwrite(STDERR, $date . " isn't a date.\n");
        exit(1);
    }
    $count = DayHighlights::save($date);
    if ($count === null) {
        echo $date . ": no page for this day.\n";
        continue;
    }
    echo $date . ': ' . $count . " highlights\n";
    foreach (DayHighlights::scores($date) as $row) {
        if (!$all_bills && $row['position'] === null) {
            continue;
        }
        $reasons = $row['reasons'];
        printf(
            "  %2s %-7s %6.2f  %-6s %s%s\n",
            $row['position'] ?? '-',
            strtoupper($reasons['bill']['number']),
            $row['score'],
            (string) $row['chamber'],
            $reasons['action']['text'],
            $row['hidden'] === 'y' ? ' (hidden)' : ''
        );
        echo '             ' . implode(', ', array_map(
            fn($signal) => $signal['signal'] . ' ' . $signal['points'],
            $reasons['signals']
        )) . "\n";
        if (count($reasons['labels']) > 0) {
            echo '             “' . implode('”, “', $reasons['labels']) . "”\n";
        }
    }
}
