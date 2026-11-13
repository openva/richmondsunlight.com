<?php

###
# Days
#
# PURPOSE
# A calendar of a year's days in the General Assembly, each linked to its page (/day/YYYY-MM-DD/),
# and shaded by how much happened. The days come from the API (/1.1/days.json).
#
###

# INCLUDES
include_once 'includes/settings.inc.php';
include_once 'vendor/autoload.php';

# The session and the page template use the database (the days come from the API).
$database = new Database();
$database->connect_mysqli();

# INITIALIZE SESSION
resume_session();

# LOCALIZE VARIABLES
$this_year = (int) date('Y');
$first_year = 2008;
$year = isset($_GET['year']) && preg_match('/^\d{4}$/', (string) $_GET['year']) ? (int) $_GET['year'] : null;

if ($year !== null) {
    if ($year < $first_year || $year > $this_year) {
        http_response_code(404);
        include '404.php';
        exit();
    }
    $days = Day::list_from_api($year);
} else {
    # Until the legislature has met this year, show last year.
    $year = $this_year;
    $days = Day::list_from_api($year);
    if (count($days) === 0) {
        $year--;
        $days = Day::list_from_api($year);
    }
}
$session_year = $year;

# PAGE METADATA
$page_title = 'Daily » ' . $year;
$site_section = 'day';

# How busy each day was, from 1 to 4, by where it falls among the year's days.
$activity = fn($day) => $day['actions'] + 10 * $day['videos'] + 5 * $day['meetings'] + 20 * count($day['floor']);
$levels = [];
if (count($days) > 0) {
    $sorted = array_map($activity, $days);
    sort($sorted);
    $quartile = fn($q) => $sorted[(int) floor($q * (count($sorted) - 1))];
    foreach ($days as $day) {
        $score = $activity($day);
        $levels[$day['date']] = $score >= $quartile(.75) ? 4 : ($score >= $quartile(.5) ? 3 : ($score >= $quartile(.25) ? 2 : 1));
    }
}

/**
 * What happened on a day, briefly, for its link's label.
 */
$describe = function (array $day): string {
    $parts = [];
    if (count($day['floor']) === 2) {
        $parts[] = 'House and Senate floor sessions';
    } elseif (count($day['floor']) === 1) {
        $parts[] = ucfirst($day['floor'][0]) . ' floor session';
    }
    if ($day['meetings'] > 0) {
        $parts[] = $day['meetings'] . ($day['meetings'] === 1 ? ' meeting' : ' meetings');
    }
    if ($day['videos'] > 0) {
        $parts[] = $day['videos'] . ($day['videos'] === 1 ? ' video' : ' videos');
    }

    return implode(', ', $parts);
};

# PAGE BODY
$page_body = '
	<p>Every day that the General Assembly meets, everything it did: each chamber’s floor session,
	every committee and subcommittee meeting, the votes and other actions on bills, the minutes, and
	the video. Choose a day. Darker days were busier.</p>';

if (count($days) === 0) {
    $page_body .= $year === $this_year
        ? '
	<p>The General Assembly hasn’t met in ' . $year . ' yet.</p>'
        : '
	<p>There’s no record of the General Assembly’s days in ' . $year . '.</p>';
}

$by_date = [];
foreach ($days as $day) {
    $by_date[$day['date']] = $day;
}

# One calendar for each month with a day in it, Sunday through Saturday.
$months = array_unique(array_map(fn($day) => substr($day['date'], 0, 7), $days));
foreach ($months as $month) {
    $first = strtotime($month . '-01');
    $page_body .= '
	<section class="day-month">
		<h2>' . date('F', $first) . '</h2>
		<table class="day-calendar">
			<caption>' . date('F Y', $first) . '</caption>
			<thead>
				<tr><th scope="col" abbr="Sunday">S</th><th scope="col" abbr="Monday">M</th><th scope="col" abbr="Tuesday">T</th><th scope="col" abbr="Wednesday">W</th><th scope="col" abbr="Thursday">T</th><th scope="col" abbr="Friday">F</th><th scope="col" abbr="Saturday">S</th></tr>
			</thead>
			<tbody>
				<tr>';
    $offset = (int) date('w', $first);
    $page_body .= str_repeat('<td></td>', $offset);
    $length = (int) date('t', $first);
    for ($i = 1; $i <= $length; $i++) {
        $date = $month . '-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        if (isset($by_date[$date])) {
            $label = date('l, F j', strtotime($date)) . ': ' . $describe($by_date[$date]);
            $page_body .= '<td class="day-level-' . $levels[$date] . '"><a href="' . Day::page_path($date) . '" aria-label="'
                . htmlspecialchars($label) . '" title="' . htmlspecialchars($label) . '">' . $i . '</a></td>';
        } else {
            $page_body .= '<td>' . $i . '</td>';
        }
        if (($offset + $i) % 7 === 0 && $i < $length) {
            $page_body .= '</tr>
				<tr>';
        }
    }
    $page_body .= str_repeat('<td></td>', (7 - ($offset + $length) % 7) % 7) . '</tr>
			</tbody>
		</table>
	</section>';
}

# PAGE SIDEBAR
$page_sidebar = '
	<div class="box">
		<h3>Years</h3>
		<ul class="day-years">';
for ($i = $this_year; $i >= $first_year; $i--) {
    $page_sidebar .= '
			<li>' . ($i === $year ? '<strong>' . $i . '</strong>' : '<a href="/day/' . $i . '/">' . $i . '</a>') . '</li>';
}
$page_sidebar .= '
		</ul>
	</div>
	<div class="box">
		<h3>More</h3>
		<ul>
			<li><a href="/day/latest/">The most recent day</a></li>
			<li><a href="/video/' . $year . '/">Every video of ' . $year . '</a></li>
			<li><a href="/schedule/">The legislature’s schedule</a></li>
		</ul>
	</div>';

# OUTPUT THE PAGE
$page = new Page();
foreach ([
    'page_title',
    'page_body',
    'page_sidebar',
    'site_section',
    'session_year',
] as $prop) {
    if (isset(${$prop})) {
        $page->{$prop} = ${$prop};
    }
}
$page->process();
