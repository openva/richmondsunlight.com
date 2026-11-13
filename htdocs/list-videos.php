<?php

###
# List Videos
#
# PURPOSE
# Lists a year's videos of floor sessions and committee meetings, by chamber, then by committee
# (with each committee's subcommittees). The videos come from the API (/1.1/videos.json).
#
###

# INCLUDES
include_once 'includes/settings.inc.php';
include_once 'vendor/autoload.php';

# The session and the page template use the database (the videos come from the API).
$database = new Database();
$database->connect_mysqli();

# INITIALIZE SESSION
resume_session();

# LOCALIZE VARIABLES
$this_year = (int) date('Y');
$year = isset($_GET['year']) && preg_match('/^\d{4}$/', (string) $_GET['year']) ? (int) $_GET['year'] : null;

if ($year !== null) {
    if ($year < 2009 || $year > $this_year) {
        http_response_code(404);
        include '404.php';
        exit();
    }
    $videos = Video::list_from_api(['year' => $year]);
} else {
    # Until there's video of this year, show last year's.
    $year = $this_year;
    $videos = Video::list_from_api(['year' => $year]);
    if (count($videos) === 0) {
        $year--;
        $videos = Video::list_from_api(['year' => $year]);
    }
}

# PAGE METADATA
$page_title = 'Video » ' . $year;
$site_section = 'day';

# Group the videos by chamber, then by floor session or committee (subcommittees with their
# committees), newest first.
$groups = [];
foreach (array_reverse($videos) as $video) {
    $meeting = $video['meeting'];
    if ($meeting['type'] === 'floor') {
        $group = 'Floor Sessions';
    } elseif ($meeting['type'] === 'committee') {
        $group = $meeting['committee']['name'];
    } elseif ($meeting['type'] === 'subcommittee') {
        $group = $meeting['parent_committee']['name'];
    } else {
        $group = 'Other Meetings';
    }
    $groups[$video['chamber']][$group][] = $video;
}

# Floor sessions first, then committees in alphabetical order, then everything else.
foreach ($groups as &$chamber_groups) {
    uksort($chamber_groups, function ($a, $b) {
        $rank = fn($name) => $name === 'Floor Sessions' ? 0 : ($name === 'Other Meetings' ? 2 : 1);
        return [$rank($a), $a] <=> [$rank($b), $b];
    });
}
unset($chamber_groups);

# PAGE BODY
$page_body = '
	<p>Video of the General Assembly’s floor sessions and committee meetings. Each video has a guide
	to the bills taken up and the legislators who spoke, and a transcript, once those are ready.</p>';

if (count($videos) === 0) {
    $page_body .= '
	<p>There’s no video of ' . $year . ' yet.</p>';
}

foreach (['house', 'senate'] as $chamber) {
    if (empty($groups[$chamber])) {
        continue;
    }
    $page_body .= '
	<h2>' . ucfirst($chamber) . '</h2>';
    foreach ($groups[$chamber] as $group => $group_videos) {
        $page_body .= '
	<details class="video-list-group">
		<summary>' . htmlspecialchars($group) . ' <small>(' . count($group_videos) . ')</small></summary>
		<ul class="video-list">';
        foreach ($group_videos as $video) {
            $label = date('F j', strtotime($video['date']));
            if ($video['meeting']['type'] === 'subcommittee') {
                $label .= ': ' . $video['meeting']['committee']['name'] . ' Subcommittee';
            } elseif ($video['meeting']['type'] === 'unknown') {
                $label .= ': ' . $video['meeting']['label'];
            }
            $page_body .= '
			<li><a href="' . htmlspecialchars(parse_url($video['url'], PHP_URL_PATH)) . '">'
                . htmlspecialchars($label) . '</a>'
                . (!empty($video['length']) ? ' <small>' . Video::clock($video['length']) . '</small>' : '')
                . ($video['status'] !== 'available' ? ' <small>(processing)</small>' : '') . '</li>';
        }
        $page_body .= '
		</ul>
	</details>';
    }
}

# PAGE SIDEBAR
$page_sidebar = '
	<div class="box">
		<h3>Years</h3>
		<ul>';
for ($i = $this_year; $i >= 2009; $i--) {
    $page_sidebar .= '
			<li>' . ($i === $year ? '<strong>' . $i . '</strong>' : '<a href="/video/' . $i . '/">' . $i . '</a>') . '</li>';
}
$page_sidebar .= '
		</ul>
	</div>';

# OUTPUT THE PAGE
$page = new Page();
foreach ([
    'page_title',
    'page_body',
    'page_sidebar',
    'site_section',
] as $prop) {
    if (isset(${$prop})) {
        $page->{$prop} = ${$prop};
    }
}
$page->process();
