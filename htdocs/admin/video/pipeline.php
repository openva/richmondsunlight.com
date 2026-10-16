<?php

###
# Video Pipeline
#
# PURPOSE
# Shows what rs-video-processor has done to each video, what it is doing now,
# what it is waiting to do, and what has failed. Read-only.
#
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
# (The Composer autoloader also loads functions.inc.php; including that too is fatal.)
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/settings.inc.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/vendor/autoload.php';

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database();
$db = $database->connect_mysqli();
if (!$db instanceof mysqli) {
    die('Unable to connect to the database.');
}

# PAGE METADATA
$page_title = 'Video Pipeline';
$site_section = 'admin';
$page_sidebar = '';
$html_head = '';

# CUSTOM FUNCTIONS
function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** A link to this page with the given filters. */
function filter_link(array $filters, $label)
{
    $query = http_build_query(array_filter($filters, fn($v) => $v !== null && $v !== ''));
    return '<a href="/admin/video/pipeline.php' . ($query !== '' ? '?' . $query : '') . '">' . $label . '</a>';
}

function minutes_ago($timestamp, $now)
{
    $minutes = (int) floor(($now - $timestamp) / 60);
    if ($minutes < 60) {
        return $minutes . ' min ago';
    }
    if ($minutes < 48 * 60) {
        return floor($minutes / 60) . ' h ' . ($minutes % 60) . ' min ago';
    }
    return floor($minutes / 1440) . ' days ago';
}

# PAGE CONTENT
$now = time();
$stages = VideoPipelineStatus::STAGES;
$states = VideoPipelineStatus::STATES;

// Filters for the video list.
$filterStage = isset($_GET['stage'], $stages[$_GET['stage']]) ? $_GET['stage'] : null;
$filterState = isset($_GET['state'], $states[$_GET['state']]) ? $_GET['state'] : null;
$filterChamber = in_array($_GET['chamber'] ?? '', ['house', 'senate'], true) ? $_GET['chamber'] : null;
$filterId = isset($_GET['id']) && ctype_digit((string) $_GET['id']) ? (int) $_GET['id'] : null;
$showAll = ($_GET['show'] ?? '') === 'all';

$videos = VideoPipelineStatus::loadVideos($db, $filterId);
$openJobs = VideoPipelineStatus::loadOpenJobs($db);

// Every video's state at every stage, and the totals.
$totals = [];
foreach ($stages as $stage => $label) {
    $totals[$stage] = array_fill_keys(array_keys($states), 0);
}
$rows = [];
foreach ($videos as $id => $video) {
    $cells = [];
    foreach ($stages as $stage => $label) {
        $cells[$stage] = VideoPipelineStatus::stageState($stage, $video, $openJobs[$id][$stage] ?? null, $now);
        $totals[$stage][$cells[$stage]['state']]++;
    }
    $rows[$id] = $cells;
}

$page_body = '
<style>
	.pipeline table { border-collapse: collapse; margin-bottom: 1.5em; }
	.pipeline th, .pipeline td { padding: 3px 8px; border-bottom: 1px solid #ddd; text-align: left; vertical-align: top; }
	.pipeline td.num { text-align: right; }
	.pipeline .state { display: inline-block; min-width: 5.5em; padding: 1px 4px; border-radius: 3px; font-size: 90%; }
	.pipeline .done { background: #d7f0d7; }
	.pipeline .running { background: #cfe3ff; }
	.pipeline .waiting { background: #fff2c2; }
	.pipeline .retry { background: #ffdcae; }
	.pipeline .failed { background: #f7c1c1; }
	.pipeline .blocked { background: #eee; }
	.pipeline .manual { background: #e6dcf5; }
	.pipeline .n\/a { color: #999; }
	.pipeline .error { font-family: monospace; font-size: 85%; max-width: 50em; word-break: break-word; }
	.pipeline .muted { color: #777; }
</style>
<div class="pipeline">
<p>
	<a href="/admin/">Admin</a> | <a href="/admin/video/">Videos</a> |
	<a href="/admin/video/pipeline.php">Pipeline</a>
</p>';

// What the pipeline is doing now.
$lastActivity = null;
$result = mysqli_query($db, 'SELECT MAX(date_modified) AS last FROM video_jobs');
if ($result && $row = mysqli_fetch_assoc($result)) {
    $lastActivity = VideoPipelineStatus::utc($row['last']);
}
$running = [];
foreach ($rows as $id => $cells) {
    foreach ($cells as $stage => $cell) {
        if ($cell['state'] === 'running') {
            $running[] = [$id, $stage, $cell['detail']];
        }
    }
}

$page_body .= '<h2>Now</h2><p>';
if ($lastActivity === null) {
    $page_body .= 'The pipeline has not recorded any jobs yet.';
} else {
    $page_body .= 'Last job activity ' . h(minutes_ago($lastActivity, $now))
        . ' (' . h(VideoPipelineStatus::local($lastActivity)) . '). ';
    $page_body .= empty($running)
        ? 'Nothing is running.'
        : count($running) . ' job(s) running:';
}
$page_body .= '</p>';
if (!empty($running)) {
    $page_body .= '<table><thead><tr><th>Video</th><th>Stage</th><th></th></tr></thead><tbody>';
    foreach ($running as [$id, $stage, $detail]) {
        $video = $videos[$id];
        $page_body .= '<tr><td>' . filter_link(['id' => $id], '#' . $id) . ' ' . h($video['date']) . ' '
            . h($video['title']) . '</td><td>' . h($stages[$stage]) . '</td><td class="muted">' . h($detail) . '</td></tr>';
    }
    $page_body .= '</tbody></table>';
}

// Totals by stage and state. Each count links to the matching videos.
if ($filterId === null) {
    $page_body .= '<h2>By stage</h2>
		<p class="muted">Every video, by what is left to do at each stage. "Waiting" is ready to run;
		"blocked" needs an earlier stage first; "N/A" does not apply (e.g. detection before 2020).</p>
		<table><thead><tr><th>Stage</th>';
    foreach ($states as $label) {
        $page_body .= '<th>' . h($label) . '</th>';
    }
    $page_body .= '</tr></thead><tbody>';
    foreach ($stages as $stage => $label) {
        $page_body .= '<tr><th>' . h($label) . '</th>';
        foreach ($states as $state => $stateLabel) {
            $count = $totals[$stage][$state];
            $page_body .= '<td class="num">' . ($count > 0
                ? filter_link(['stage' => $stage, 'state' => $state, 'show' => 'all'], number_format($count))
                : '<span class="muted">0</span>') . '</td>';
        }
        $page_body .= '</tr>';
    }
    $page_body .= '</tbody></table>';

    // Throughput: jobs finished per day, and how long they took.
    $days = 14;
    $done = VideoPipelineStatus::loadRecentlyDone($db, $days);
    $perDay = [];
    $durations = [];
    foreach ($done as $job) {
        $finished = VideoPipelineStatus::utc($job['date_modified']);
        if ($finished === null) {
            continue;
        }
        $day = (new DateTime('@' . $finished))->setTimezone(new DateTimeZone('America/New_York'))->format('Y-m-d');
        $perDay[$day][$job['stage']] = ($perDay[$day][$job['stage']] ?? 0) + 1;
        $started = VideoPipelineStatus::utc($job['claimed_at']);
        if ($started !== null && $started <= $finished) {
            $durations[$job['stage']][] = $finished - $started;
        }
    }
    krsort($perDay);

    $page_body .= '<h2>Finished, last ' . $days . ' days</h2>';
    if (empty($perDay)) {
        $page_body .= '<p>No jobs finished in the last ' . $days . ' days.</p>';
    } else {
        $page_body .= '<p class="muted">Each job counts once, on the day it last finished. Times are
			from when the job started to when it finished; jobs finished before the pipeline began
			recording start times have none.</p><table><thead><tr><th>Day</th>';
        foreach ($stages as $label) {
            $page_body .= '<th>' . h($label) . '</th>';
        }
        $page_body .= '</tr></thead><tbody>';
        foreach ($perDay as $day => $counts) {
            $page_body .= '<tr><td>' . h(date('D M j', strtotime($day))) . '</td>';
            foreach ($stages as $stage => $label) {
                $page_body .= '<td class="num">' . (isset($counts[$stage]) ? (int) $counts[$stage] : '<span class="muted">0</span>') . '</td>';
            }
            $page_body .= '</tr>';
        }
        $page_body .= '<tr><th>Average time</th>';
        foreach ($stages as $stage => $label) {
            $page_body .= '<td class="num">' . (empty($durations[$stage])
                ? '<span class="muted">—</span>'
                : h(round(array_sum($durations[$stage]) / count($durations[$stage]) / 60, 1) . ' min')) . '</td>';
        }
        $page_body .= '</tr></tbody></table>';
    }

    // The pipeline's own reports of starting and stopping.
    $result = mysqli_query(
        $db,
        "SELECT date, message FROM logs
         WHERE message LIKE 'Video processor started%'
            OR message LIKE 'Video pipeline stopped%'
            OR message LIKE 'Auto-shutdown%'
         ORDER BY date DESC LIMIT 15"
    );
    $page_body .= '<h2>Recent runs</h2>';
    if (!$result || mysqli_num_rows($result) === 0) {
        $page_body .= '<p>No start or stop messages in the log.</p>';
    } else {
        $page_body .= '<table><tbody>';
        while ($row = mysqli_fetch_assoc($result)) {
            $page_body .= '<tr><td>' . h(date('M j, g:i a', strtotime($row['date']))) . '</td><td>' . h($row['message']) . '</td></tr>';
        }
        $page_body .= '</tbody></table>';
    }
}

// The videos themselves.
$listed = [];
foreach ($rows as $id => $cells) {
    $video = $videos[$id];
    if ($filterChamber !== null && $video['chamber'] !== $filterChamber) {
        continue;
    }
    if ($filterStage !== null && $filterState !== null && $cells[$filterStage]['state'] !== $filterState) {
        continue;
    }
    if ($filterStage === null && $filterState !== null) {
        $matches = false;
        foreach ($cells as $cell) {
            $matches = $matches || $cell['state'] === $filterState;
        }
        if (!$matches) {
            continue;
        }
    }
    // By default, only videos with something left to do or something wrong.
    if (!$showAll && $filterId === null && $filterState === null) {
        $open = false;
        foreach ($cells as $cell) {
            $open = $open || in_array($cell['state'], ['running', 'waiting', 'retry', 'failed'], true);
        }
        if (!$open) {
            continue;
        }
    }
    $listed[] = $id;
}

$limit = 300;
$current = ['stage' => $filterStage, 'state' => $filterState, 'chamber' => $filterChamber, 'show' => $showAll ? 'all' : null];
$page_body .= '<h2>Videos</h2><p>';
if ($filterId !== null) {
    $page_body .= 'Video #' . $filterId . '. ' . filter_link([], 'Back to all videos') . '.';
} else {
    $page_body .= number_format(count($listed)) . ' video(s)';
    if ($filterStage !== null || $filterState !== null) {
        $page_body .= ' where ' . h($filterStage !== null ? $stages[$filterStage] : 'any stage') . ' is '
            . h($filterState !== null ? strtolower($states[$filterState]) : 'anything');
    } elseif (!$showAll) {
        $page_body .= ' with work waiting, running, retrying or failed';
    }
    $page_body .= count($listed) > $limit ? ', newest ' . $limit . ' shown' : '';
    $page_body .= '. Show: '
        . filter_link(array_merge($current, ['chamber' => null]), 'both chambers') . ', '
        . filter_link(array_merge($current, ['chamber' => 'house']), 'House') . ', '
        . filter_link(array_merge($current, ['chamber' => 'senate']), 'Senate') . ' | '
        . filter_link(['chamber' => $filterChamber], 'open work only') . ', '
        . filter_link(['chamber' => $filterChamber, 'show' => 'all'], 'every video') . '.';
}
$page_body .= '</p>';

if (!empty($listed)) {
    $page_body .= '<table><thead><tr><th>ID</th><th>Date</th><th>Chamber</th><th>Title</th>';
    foreach ($stages as $label) {
        $page_body .= '<th>' . h($label) . '</th>';
    }
    $page_body .= '</tr></thead><tbody>';
    foreach (array_slice($listed, 0, $limit) as $id) {
        $video = $videos[$id];
        $page_body .= '<tr><td>' . filter_link(['id' => $id], $id) . '</td><td>' . h($video['date']) . '</td><td>'
            . h(ucfirst((string) $video['chamber'])) . '</td><td>' . h($video['title']) . '</td>';
        foreach ($stages as $stage => $label) {
            $cell = $rows[$id][$stage];
            $page_body .= '<td><span class="state ' . h($cell['state']) . '" title="' . h($cell['detail']) . '">'
                . h($states[$cell['state']]) . '</span></td>';
        }
        $page_body .= '</tr>';
    }
    $page_body .= '</tbody></table>';
}

// For a single video, spell out every stage, including errors in full.
if ($filterId !== null && isset($rows[$filterId])) {
    $page_body .= '<table><thead><tr><th>Stage</th><th>State</th><th>Detail</th></tr></thead><tbody>';
    foreach ($stages as $stage => $label) {
        $cell = $rows[$filterId][$stage];
        $page_body .= '<tr><td>' . h($label) . '</td><td><span class="state ' . h($cell['state']) . '">'
            . h($states[$cell['state']]) . '</span></td><td class="error">' . h($cell['detail']) . '</td></tr>';
    }
    $page_body .= '</tbody></table>';
}

// Failed and retrying jobs, with their errors.
if ($filterId === null) {
    $problems = [];
    foreach ($rows as $id => $cells) {
        if ($filterChamber !== null && $videos[$id]['chamber'] !== $filterChamber) {
            continue;
        }
        foreach ($cells as $stage => $cell) {
            if (in_array($cell['state'], ['failed', 'retry'], true)) {
                $problems[] = [$id, $stage, $cell];
            }
        }
    }
    $page_body .= '<h2>Failed and retrying</h2>';
    if (empty($problems)) {
        $page_body .= '<p>None.</p>';
    } else {
        $page_body .= '<p class="muted">On the processor, <code>php bin/job_status.php --reset=N</code> clears one
			video\'s job records so its stages run again, <code>--reset-stage=STAGE</code> or <code>--reset-failed</code> retries many, and
			<code>php bin/requeue_video.php --id=N --apply</code> starts a video over.</p>
			<table><thead><tr><th>Video</th><th>Stage</th><th>State</th><th>Error</th></tr></thead><tbody>';
        foreach (array_slice($problems, 0, 200) as [$id, $stage, $cell]) {
            $video = $videos[$id];
            $page_body .= '<tr><td>' . filter_link(['id' => $id], '#' . $id) . ' ' . h($video['date']) . '<br><span class="muted">'
                . h($video['title']) . '</span></td><td>' . h($stages[$stage]) . '</td><td><span class="state '
                . h($cell['state']) . '">' . h($states[$cell['state']]) . '</span></td><td class="error">'
                . h($cell['detail']) . '</td></tr>';
        }
        $page_body .= '</tbody></table>';
    }
}

$page_body .= '</div>';

# OUTPUT THE PAGE
$page = new Page();
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->html_head = $html_head;
$page->process();
