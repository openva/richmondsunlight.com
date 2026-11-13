<?php

###
# A Day in the Legislature
#
# PURPOSE
# Shows what happened in the General Assembly on one day: each chamber's floor session (its video,
# its votes, everything else it did, and its minutes), and every committee and subcommittee meeting
# (its video, what it did with each bill, and the rest of its docket). On the most recent day, it
# also shows what's scheduled next. See docs/minutes-replacement.md.
#
# The URL is /day/YYYY-MM-DD/, and /day/latest/ redirects to the most recent day. #house or #senate
# shows only that chamber.
#
# Everything about the day comes from the API (/1.1/day/{date}.json).
#
###

# INCLUDES
include_once 'includes/settings.inc.php';
include_once 'vendor/autoload.php';

# The session and the page template use the database (the day itself comes from the API).
$database = new Database();
$database->connect_mysqli();

# INITIALIZE SESSION
resume_session();

# LOCALIZE AND CLEAN UP VARIABLES
$date = (string) ($_GET['date'] ?? '');

if ($date === 'latest') {
    $day = Day::from_api('latest');
    if ($day !== null) {
        header('Location: ' . Day::page_path($day['date']), true, 302);
        exit();
    }
} else {
    $day = Day::is_date($date) ? Day::from_api($date) : null;
}
if ($day === null) {
    http_response_code(404);
    include '404.php';
    exit();
}

# Send any other URL for this day (one without a trailing slash, say) to its own.
if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) !== Day::page_path($day['date'])) {
    header('Location: ' . Day::page_path($day['date']), true, 301);
    exit();
}

$year = (int) substr($day['date'], 0, 4);
$session_year = $year;
$long_date = date('l, F j, Y', strtotime($day['date']));

/**
 * A bill's number as people write it (e.g., "HB 1384").
 */
$bill_number = fn(string $number) => mb_strtoupper(preg_replace('/^([a-z]+)/', '$1 ', $number));

/**
 * A bill, linked, with its title and patron.
 */
$bill_html = function (array $bill) use ($bill_number): string {
    $html = '<a class="day-bill-number" href="' . htmlspecialchars(parse_url($bill['url'], PHP_URL_PATH)) . '">'
        . htmlspecialchars($bill_number($bill['number'])) . '</a>'
        . ' <span class="day-bill-title">' . htmlspecialchars($bill['catch_line']) . '</span>';
    if (!empty($bill['patron'])) {
        $html .= ' <span class="day-bill-patron">' . htmlspecialchars($bill['patron']['name']) . '</span>';
    }

    return $html;
};

/**
 * A vote: its tally (linked to the roll call, if there is one), or that it was a voice vote, and
 * whether it was close.
 */
$vote_html = function (?array $vote): string {
    if ($vote === null) {
        return '';
    }
    if ($vote['voice']) {
        return ' <span class="day-vote">voice vote</span>';
    }
    $tally = $vote['yes'] . '–' . $vote['no'];
    $label = $vote['yes'] . ' to ' . $vote['no'];
    $html = !empty($vote['url'])
        ? ' <a class="day-vote" href="' . htmlspecialchars(parse_url($vote['url'], PHP_URL_PATH)) . '" aria-label="'
            . $label . ', the roll call">' . $tally . '</a>'
        : ' <span class="day-vote" aria-label="' . $label . '">' . $tally . '</span>';
    if ($vote['close']) {
        $html .= ' <span class="day-close">close vote</span>';
    }

    return $html;
};

/**
 * Actions, one item per bill, each with what was done to it.
 */
$actions_html = function (array $actions, bool $show_text = true, string $class = 'day-actions') use ($bill_html, $vote_html): string {
    $bills = [];
    foreach ($actions as $action) {
        $bills[$action['bill']['number']]['bill'] = $action['bill'];
        $bills[$action['bill']['number']]['actions'][] = $action;
        if ($action['outcome'] !== null) {
            $bills[$action['bill']['number']]['outcome'] = $action['outcome'];
        }
    }
    $html = '
			<ul class="' . $class . '">';
    foreach ($bills as $bill) {
        $html .= '
				<li' . (!empty($bill['outcome']) ? ' class="day-outcome-' . htmlspecialchars($bill['outcome']) . '"' : '') . '>'
            . $bill_html($bill['bill']);
        $parts = [];
        foreach ($bill['actions'] as $action) {
            $parts[] = ($show_text ? htmlspecialchars($action['text']) : '') . $vote_html($action['vote']);
        }
        $parts = array_filter(array_map('trim', $parts));
        if (count($parts) > 0) {
            $html .= '
					<span class="day-action-text">' . implode('; ', $parts) . '</span>';
        }
        $html .= '</li>';
    }

    return $html . '
			</ul>';
};

/**
 * Videos of a meeting, each linked to its page.
 */
$videos_html = function (array $videos): string {
    if (count($videos) === 0) {
        return '';
    }
    $html = '
			<ul class="day-videos">';
    foreach ($videos as $i => $video) {
        $label = count($videos) > 1 ? 'Video, part ' . ($i + 1) : 'Video';
        $html .= '
				<li><a href="' . htmlspecialchars(parse_url($video['url'], PHP_URL_PATH)) . '">'
            . (!empty($video['poster']) && $video['status'] === 'available'
                ? '<img src="' . htmlspecialchars($video['poster']) . '" alt="" loading="lazy" width="160" height="90">' : '')
            . '<span class="day-video-label">' . $label . '</span>'
            . ($video['status'] !== 'available'
                ? ' <small>(processing)</small>'
                : (!empty($video['length']) ? ' <small>' . Video::clock($video['length']) . '</small>' : ''))
            . '</a></li>';
    }

    return $html . '
			</ul>';
};

/**
 * When and where a meeting was (or will be).
 */
$when_html = function (array $meeting): string {
    $parts = [];
    if (!empty($meeting['time'])) {
        $parts[] = date('g:i a', strtotime($meeting['time']));
    } elseif (!empty($meeting['time_description'])) {
        $parts[] = htmlspecialchars($meeting['time_description']);
    }
    if (!empty($meeting['location'])) {
        $parts[] = htmlspecialchars($meeting['location']);
    }

    return count($parts) > 0 ? '
			<p class="day-meeting-when">' . implode(', ', $parts) . '</p>' : '';
};

/**
 * A meeting's heading, linked to its committee's page, if it has one.
 */
$meeting_heading = function (array $meeting, string $tag): string {
    $url = $meeting['committee']['url'] ?? $meeting['parent_committee']['url'] ?? null;
    $label = htmlspecialchars($meeting['label']);
    if ($meeting['type'] === 'committee' && $url !== null) {
        $label = '<a href="' . htmlspecialchars(parse_url($url, PHP_URL_PATH)) . '">' . $label . '</a>';
    }

    return '<' . $tag . '>' . $label . '</' . $tag . '>';
};

# How much happened, in a sentence or two.
$floor_chambers = array_map(fn($c) => ucfirst($c['chamber']), array_filter($day['chambers'], fn($c) => $c['floor']));
$committee_meetings = array_filter($day['meetings'], fn($m) => $m['type'] !== 'other');
$outcomes = ['advanced' => [], 'stopped' => []];
foreach (array_merge(...array_map(fn($c) => $c['actions'], $day['chambers']), ...array_map(fn($m) => $m['actions'], $day['meetings'])) as $action) {
    if (isset($outcomes[$action['outcome']])) {
        $outcomes[$action['outcome']][$action['bill']['number']] = true;
    }
}
$summary = [];
if (count($floor_chambers) === 2) {
    $summary[] = 'The House and the Senate met in floor sessions.';
} elseif (count($floor_chambers) === 1) {
    $summary[] = 'The ' . reset($floor_chambers) . ' met in a floor session.';
}
if (count($committee_meetings) > 0) {
    $summary[] = count($committee_meetings) === 1
        ? 'One committee or subcommittee met.'
        : count($committee_meetings) . ' committees and subcommittees met.';
} elseif (count($day['meetings']) > 0) {
    $summary[] = count($day['meetings']) === 1 ? 'One meeting was held.' : count($day['meetings']) . ' meetings were held.';
}
$advanced = count($outcomes['advanced']);
$stopped = count($outcomes['stopped']);
if ($advanced + $stopped > 0) {
    $summary[] = ($advanced === 1 ? 'One bill' : $advanced . ' bills') . ' moved forward, and '
        . ($stopped === 1 ? 'one was' : $stopped . ' were') . ' stopped.';
}
$description = 'What happened in the Virginia General Assembly on ' . $long_date . '.';

# PAGE METADATA
$page_title = $long_date;
$site_section = 'day';

$html_head = '
	<link rel="canonical" href="' . htmlspecialchars($day['url']) . '">
	<meta name="description" content="' . htmlspecialchars($description . ' ' . implode(' ', $summary)) . '">
	<meta property="og:title" content="' . htmlspecialchars('The General Assembly, ' . $long_date) . '">
	<meta property="og:description" content="' . htmlspecialchars($description) . '">
	<meta property="og:url" content="' . htmlspecialchars($day['url']) . '">
	<meta property="og:type" content="website">
	<meta property="og:site_name" content="Richmond Sunlight">
	<script src="/js/day.js" defer></script>';

# PAGE BODY
$page_body = '
	<nav class="day-nav" aria-label="Other days">'
    . (!empty($day['previous'])
        ? '<a class="day-nav-previous" href="' . Day::page_path($day['previous']) . '" rel="prev">← '
            . date('l, F j', strtotime($day['previous'])) . '</a>'
        : '<span></span>')
    . '<a class="day-nav-calendar" href="/day/' . $year . '/">' . $year . ' calendar</a>'
    . (!empty($day['next'])
        ? '<a class="day-nav-next" href="' . Day::page_path($day['next']) . '" rel="next">'
            . date('l, F j', strtotime($day['next'])) . ' →</a>'
        : '<span></span>')
    . '</nav>';

if (count($summary) > 0) {
    $page_body .= '
	<p class="day-summary">' . implode(' ', $summary) . '</p>';
}

# Choosing a chamber. (It does nothing without JavaScript, so it's hidden until day.js shows it.)
$chambers_present = array_unique(array_filter(array_merge(
    array_map(fn($c) => $c['chamber'], $day['chambers']),
    array_map(fn($m) => $m['chamber'], $day['meetings'])
)));
if (count($chambers_present) > 1) {
    $page_body .= '
	<div class="day-filter" role="group" aria-label="Show" hidden>
		<button type="button" data-show="" aria-pressed="true">House and Senate</button>
		<button type="button" data-show="house" aria-pressed="false">House</button>
		<button type="button" data-show="senate" aria-pressed="false">Senate</button>
	</div>';
}

# Each chamber's floor session, and any of its actions that weren't in a committee.
if (count($day['chambers']) > 0) {
    $page_body .= '
	<section id="floor" class="day-section">
		<h2>Floor Sessions</h2>';
    foreach ($day['chambers'] as $chamber) {
        $name = ucfirst($chamber['chamber']);
        $passed = array_filter($chamber['actions'], fn($a) => $a['type'] === 'passed');
        $failed = array_filter($chamber['actions'], fn($a) => $a['type'] === 'failed');
        $votes = array_filter($chamber['actions'], fn($a) => $a['vote'] !== null && !$a['vote']['voice']
            && !in_array($a['type'], ['passed', 'failed', 'procedural', 'reading'], true));
        $rest = array_filter($chamber['actions'], fn($a) => !in_array($a, $passed, true)
            && !in_array($a, $failed, true) && !in_array($a, $votes, true));

        $page_body .= '
		<section class="day-chamber" id="floor-' . $chamber['chamber'] . '" data-chamber="' . $chamber['chamber'] . '">
			<h3>' . ($chamber['floor'] ? 'The ' . $name : 'Other ' . $name . ' Actions') . '</h3>';
        if ($chamber['floor'] && count($chamber['videos']) === 0) {
            $page_body .= '
			<p class="day-pending">There’s no video of this floor session yet.</p>';
        }
        $page_body .= $videos_html($chamber['videos']);

        if (count($passed) > 0) {
            $page_body .= '
			<h4>Passed <small>(' . count($passed) . ')</small></h4>' . $actions_html($passed, false);
        }
        if (count($failed) > 0) {
            $page_body .= '
			<h4>Failed <small>(' . count($failed) . ')</small></h4>' . $actions_html($failed, false);
        }
        if (count($votes) > 0) {
            $page_body .= '
			<h4>Other Votes <small>(' . count($votes) . ')</small></h4>' . $actions_html($votes);
        }
        if (count($rest) > 0) {
            $page_body .= '
			<details class="day-everything-else">
				<summary>' . ($chamber['floor'] ? 'Everything else the ' . $name . ' did' : 'Show them') . ' <small>('
                . count($rest) . ')</small></summary>' . $actions_html($rest) . '
			</details>';
        }
        if ($chamber['floor'] && count($passed) + count($failed) + count($votes) + count($rest) === 0) {
            $page_body .= '
			<p class="day-pending">No actions on bills have been recorded yet.</p>';
        }

        if (!empty($chamber['minutes'])) {
            $page_body .= '
			<details class="day-minutes" id="minutes-' . $chamber['chamber'] . '">
				<summary>The ' . $name . '’s minutes</summary>
				<div class="day-minutes-text">' . Day::minutes_html($chamber['minutes'], $year) . '</div>
			</details>';
        } elseif ($chamber['floor']) {
            $page_body .= '
			<p class="day-pending">The clerk’s minutes aren’t available yet. They usually take a day or two.</p>';
        }
        $page_body .= '
		</section>';
    }
    $page_body .= '
	</section>';
}

# Committee and subcommittee meetings (or, outside of a session, any meetings).
if (count($day['meetings']) > 0) {
    $page_body .= '
	<section id="meetings" class="day-section">
		<h2>' . (count($committee_meetings) > 0 ? 'Committee and Subcommittee Meetings' : 'Meetings') . '</h2>';
    foreach ($day['meetings'] as $meeting) {
        $page_body .= '
		<article class="day-meeting day-meeting-' . htmlspecialchars($meeting['type']) . '"'
            . (!empty($meeting['chamber']) ? ' data-chamber="' . htmlspecialchars($meeting['chamber']) . '"' : '') . '>
			' . $meeting_heading($meeting, 'h3') . $when_html($meeting) . $videos_html($meeting['videos']);
        if (count($meeting['actions']) > 0) {
            $page_body .= $actions_html($meeting['actions']);
        }
        if (count($meeting['docket']) > 0) {
            if (count($meeting['actions']) > 0) {
                $page_body .= '
			<details class="day-docket">
				<summary>Also on the docket, with no action recorded <small>(' . count($meeting['docket']) . ')</small></summary>'
                    . $actions_html(array_map(fn($bill) => ['bill' => $bill, 'text' => '', 'outcome' => null, 'vote' => null], $meeting['docket']), false) . '
			</details>';
            } else {
                $page_body .= '
			<h4>On the Docket <small>(' . count($meeting['docket']) . ')</small></h4>
			<p class="day-pending">No actions have been recorded yet.</p>'
                    . $actions_html(array_map(fn($bill) => ['bill' => $bill, 'text' => '', 'outcome' => null, 'vote' => null], $meeting['docket']), false);
            }
        }
        if (count($meeting['videos']) + count($meeting['actions']) + count($meeting['docket']) === 0) {
            $page_body .= '
			<p class="day-pending">Nothing more is recorded about this meeting yet.</p>';
        }
        $page_body .= '
		</article>';
    }
    $page_body .= '
	</section>';
}

# On the most recent day, what's next.
if (!empty($day['coming_up'])) {
    $next = $day['coming_up'];
    $page_body .= '
	<section id="coming-up" class="day-section">
		<h2>Coming Up: ' . date('l, F j', strtotime($next['date'])) . '</h2>';
    foreach ($next['floor'] as $chamber => $time) {
        $page_body .= '
		<p data-chamber="' . htmlspecialchars($chamber) . '">The ' . ucfirst($chamber) . ' meets'
            . (!empty($time) ? ' at ' . date('g:i a', strtotime($time)) : '') . '.</p>';
    }
    if (count($next['meetings']) > 0) {
        $page_body .= '
		<ul class="day-coming-up">';
        foreach ($next['meetings'] as $meeting) {
            $when = trim(strip_tags($when_html($meeting)));
            $page_body .= '
			<li' . (!empty($meeting['chamber']) ? ' data-chamber="' . htmlspecialchars($meeting['chamber']) . '"' : '') . '>'
                . htmlspecialchars($meeting['label'])
                . ($when !== '' ? ' <span class="day-meeting-when">' . $when . '</span>' : '')
                . (count($meeting['docket']) > 0
                    ? ' <small>(' . count($meeting['docket']) . (count($meeting['docket']) === 1 ? ' bill' : ' bills') . ' on the docket)</small>'
                    : '')
                . '</li>';
        }
        $page_body .= '
		</ul>';
    }
    $page_body .= '
		<p><a href="' . htmlspecialchars(parse_url($next['schedule_url'], PHP_URL_PATH)) . '">The full schedule and dockets</a></p>
	</section>';
}

# PAGE SIDEBAR
$page_sidebar = '
	<div class="box">
		<h3>About This Page</h3>
		<p>Everything that the General Assembly did on this day, gathered from the legislature’s
		records: its votes and other actions on bills, its committees’ dockets and schedules, the
		clerks’ minutes of each floor session, and video of the floor sessions and meetings.</p>
		<p>A day’s records arrive over a day or two: votes during the day, video that evening, and
		the minutes a day or so later.</p>
	</div>
	<div class="box">
		<h3>More</h3>
		<ul>
			<li><a href="/day/' . $year . '/">Every day of ' . $year . '</a></li>
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
    'html_head',
    'session_year',
] as $prop) {
    if (isset(${$prop})) {
        $page->{$prop} = ${$prop};
    }
}
$page->process();
