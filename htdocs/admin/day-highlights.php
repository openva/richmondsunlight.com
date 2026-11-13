<?php

###
# A Day's Highlights
#
# PURPOSE
# Every bill scored on a day (see includes/class.DayHighlights.php), with each signal's points, for
# tuning the weights, and buttons to hide a highlight that shouldn't be one (a clip of the wrong
# bill, say, or a news story that only mentions it), or to show it again. Hiding one chooses the
# day's highlights again, without it. rs-machine keeps it hidden when it scores the day again.
#
# /admin/day-highlights.php?date=2026-01-26
#
###

# INCLUDES
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/functions.inc.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/settings.inc.php';

$database = new Database();
$database->connect_mysqli();

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$date = (string) ($_GET['date'] ?? date('Y-m-d'));
if (!Day::is_date($date)) {
    $date = date('Y-m-d');
}

# Hide a highlight, or show it again. Only a form on this site may do this.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
    if (parse_url($origin, PHP_URL_HOST) !== parse_url('//' . $_SERVER['HTTP_HOST'], PHP_URL_HOST)) {
        http_response_code(403);
        die('This can only be done from this page.');
    }
    $bill_id = (int) ($_POST['bill_id'] ?? 0);
    if ($bill_id > 0) {
        DayHighlights::set_hidden($date, $bill_id, ($_POST['hidden'] ?? '') === 'y');
    }
    header('Location: /admin/day-highlights.php?date=' . $date, true, 303);
    exit();
}

$scores = DayHighlights::scores($date);
$previous = Day::adjacent($date, false);
$next = Day::adjacent($date, true);

?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Highlights of <?= h($date) ?></title>
	<style>
		body { font: 14px/1.4 sans-serif; margin: 1em; color: #222; }
		table { border-collapse: collapse; width: 100%; }
		th, td { text-align: left; vertical-align: top; padding: .4em .5em; border-bottom: 1px solid #ddd; }
		tr.highlight { background: #fbf3dc; }
		tr.hidden td { color: #999; }
		.signals { font-size: .9em; color: #555; }
		.labels { font-size: .9em; }
		nav { margin: .5em 0 1em; display: flex; gap: 1em; flex-wrap: wrap; }
		form { margin: 0; }
	</style>
</head>
<body>
	<h1>Highlights of <?= h(date('l, F j, Y', strtotime($date))) ?></h1>
	<nav>
		<a href="/admin/">Admin</a>
		<?php if ($previous) { ?><a href="?date=<?= h($previous) ?>">← <?= h($previous) ?></a><?php } ?>
		<?php if ($next) { ?><a href="?date=<?= h($next) ?>"><?= h($next) ?> →</a><?php } ?>
		<a href="<?= h(Day::page_path($date)) ?>">The day’s page</a>
		<form method="get"><label for="date">Another day</label> <input type="date" id="date" name="date" value="<?= h($date) ?>"> <button>Go</button></form>
	</nav>
<?php if (count($scores) === 0) { ?>
	<p>No bills have been scored for this day. (rs-machine’s <code>cron/day_highlights.php</code>
	scores the past two weeks, and <code>utilities/backfill_day_highlights.php</code> earlier days.)</p>
<?php } else { ?>
	<p>Scored at <?= h(max(array_column($scores, 'date_modified'))) ?>. The page’s API response is
	cached for up to 15 minutes, so a change may take that long to show.</p>
	<table>
		<thead><tr><th>#</th><th>Bill</th><th>Score</th><th>What happened, and why</th><th>Highlight</th></tr></thead>
		<tbody>
<?php foreach ($scores as $row) {
    $reasons = $row['reasons'];
    $classes = trim(($row['position'] !== null ? 'highlight ' : '') . ($row['hidden'] === 'y' ? 'hidden' : ''));
    ?>
			<tr class="<?= h($classes) ?>">
				<td><?= h($row['position'] ?? '') ?></td>
				<td><a href="<?= h(parse_url($reasons['bill']['url'], PHP_URL_PATH)) ?>"><?= h(strtoupper($reasons['bill']['number'])) ?></a>
					<?= h($row['chamber']) ?></td>
				<td><?= h(number_format((float) $row['score'], 2)) ?></td>
				<td>
					<?= h($reasons['action']['text']) ?>
					<?php if (!empty($reasons['action']['vote']) && !$reasons['action']['vote']['voice']) { ?>
						(<?= h($reasons['action']['vote']['yes'] . '–' . $reasons['action']['vote']['no']) ?>)
					<?php } ?>
					<div class="signals"><?= h(implode(' · ', array_map(
                        fn($signal) => $signal['signal'] . ' ' . $signal['points']
                            . (is_array($signal['value']) ? '' : ' (' . $signal['value'] . ')'),
                        $reasons['signals']
                    ))) ?></div>
					<?php if (count($reasons['labels']) > 0) { ?>
						<div class="labels">“<?= h(implode('”, “', $reasons['labels'])) ?>”</div>
					<?php } ?>
				</td>
				<td>
					<form method="post">
						<input type="hidden" name="bill_id" value="<?= h($row['bill_id']) ?>">
						<input type="hidden" name="hidden" value="<?= $row['hidden'] === 'y' ? 'n' : 'y' ?>">
						<button aria-label="<?= h(($row['hidden'] === 'y' ? 'Show ' : 'Hide ') . strtoupper($reasons['bill']['number'])) ?>"><?= $row['hidden'] === 'y' ? 'Show' : 'Hide' ?></button>
					</form>
				</td>
			</tr>
<?php } ?>
		</tbody>
	</table>
<?php } ?>
</body>
</html>
