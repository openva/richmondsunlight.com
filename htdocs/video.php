<?php

###
# Video
#
# PURPOSE
# Displays a single video of a floor session or a committee meeting, with a guide to it: the bills
# taken up, the legislators who spoke, and the transcript.
#
# The video's URL is its slug and a readable suffix (e.g., /video/k7qm/2026-01-20-house-appropriations/),
# but only the slug identifies it. Any other URL for the video is redirected to that one. ?t=SECONDS
# starts the video at that time.
#
# Everything about the video comes from the API (/1.1/video/{slug}.json).
#
###

# INCLUDES
include_once 'includes/settings.inc.php';
include_once 'vendor/autoload.php';

# The session and the page template use the database (the video itself comes from the API).
$database = new Database();
$database->connect_mysqli();

# INITIALIZE SESSION
resume_session();

# LOCALIZE AND CLEAN UP VARIABLES
$requested_slug = (string) ($_GET['slug'] ?? '');
$slug = strtolower($requested_slug);
$time = isset($_GET['t']) && preg_match('/^\d{1,5}$/', (string) $_GET['t']) ? (int) $_GET['t'] : null;

$video = Video::is_slug($slug) ? Video::from_api($slug) : null;
if ($video === null) {
    http_response_code(404);
    include '404.php';
    exit();
}

# Send any other URL for this video to its own.
$path = parse_url($video['url'], PHP_URL_PATH);
if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) !== $path) {
    header('Location: ' . $path . ($time !== null ? '?t=' . $time : ''), true, 301);
    exit();
}

$meeting = $video['meeting'];
$date = date('l, F j, Y', strtotime($video['date']));
$playable = $video['status'] === 'available';

# The meeting, as a phrase (e.g., "the House Appropriations Committee's meeting").
if ($meeting['type'] === 'floor') {
    $meeting_phrase = 'the ' . ucfirst($video['chamber']) . '’s floor session';
} else {
    $meeting_phrase = 'a meeting of the ' . $meeting['label'];
}
$description = 'Video of ' . $meeting_phrase . ' on ' . $date . '.';

# PAGE METADATA
$page_title = $meeting['label'] . ', ' . date('F j, Y', strtotime($video['date']));
$site_section = 'day';

$html_head = '
	<link rel="canonical" href="' . htmlspecialchars($video['url']) . '">
	<meta name="description" content="' . htmlspecialchars($description) . '">
	<meta property="og:title" content="' . htmlspecialchars($page_title) . '">
	<meta property="og:description" content="' . htmlspecialchars($description) . '">
	<meta property="og:url" content="' . htmlspecialchars($video['url']) . '">
	<meta property="og:type" content="video.other">
	<meta property="og:site_name" content="Richmond Sunlight">
	<meta property="og:locale" content="en_US">';
if (!empty($video['media']['poster'])) {
    $html_head .= '
	<meta property="og:image" content="' . htmlspecialchars($video['media']['poster']) . '">';
}
if (!empty($video['media']['mp4'])) {
    $html_head .= '
	<meta property="og:video" content="' . htmlspecialchars($video['media']['mp4']) . '">
	<meta property="og:video:type" content="video/mp4">';
    if (!empty($video['width']) && !empty($video['height'])) {
        $html_head .= '
	<meta property="og:video:width" content="' . (int) $video['width'] . '">
	<meta property="og:video:height" content="' . (int) $video['height'] . '">';
    }
}

# Describe the video for search engines (https://schema.org/VideoObject).
if ($playable) {
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'VideoObject',
        'name' => $page_title,
        'description' => $description,
        'uploadDate' => $video['date'],
        'url' => $video['url'],
    ];
    if (!empty($video['length'])) {
        $schema['duration'] = sprintf(
            'PT%dH%dM%dS',
            intdiv($video['length'], 3600),
            intdiv($video['length'], 60) % 60,
            $video['length'] % 60
        );
    }
    if (!empty($video['media']['poster'])) {
        $schema['thumbnailUrl'] = $video['media']['poster'];
    }
    if (!empty($video['media']['mp4'])) {
        $schema['contentUrl'] = $video['media']['mp4'];
    }
    if (!empty($video['media']['youtube_id'])) {
        $schema['embedUrl'] = 'https://www.youtube.com/embed/' . $video['media']['youtube_id'];
    }
    if (!empty($video['transcript'])) {
        # The whole transcript is on the page already, so only its start is repeated here.
        $text = '';
        foreach ($video['transcript'] as $turn) {
            foreach ($turn['lines'] as $line) {
                $text .= $line['text'] . ' ';
            }
            if (mb_strlen($text) > 5000) {
                break;
            }
        }
        $schema['transcript'] = trim(mb_substr(preg_replace('/\s+/', ' ', $text), 0, 5000));
    }
    $html_head .= '
	<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>
	<script src="/js/video-player.js" defer></script>';
}

# PAGE BODY
$page_body = '
	<p class="video-summary">Video of ' . htmlspecialchars($meeting_phrase) . ' on
	<time datetime="' . htmlspecialchars($video['date']) . '">' . $date . '</time>'
    . (!empty($video['length']) ? ', ' . Video::clock($video['length']) . ' long' : '') . '.</p>';

if ($meeting['type'] === 'subcommittee' && !empty($meeting['parent_committee']['url'])) {
    $page_body .= '
	<p>The ' . htmlspecialchars($meeting['committee']['name']) . ' Subcommittee is part of the
	<a href="' . htmlspecialchars(parse_url($meeting['parent_committee']['url'], PHP_URL_PATH)) . '">'
        . htmlspecialchars(ucfirst($video['chamber']) . ' ' . $meeting['parent_committee']['name']) . ' Committee</a>.</p>';
} elseif ($meeting['type'] === 'committee' && !empty($meeting['committee']['url'])) {
    $page_body .= '
	<p>More about the <a href="' . htmlspecialchars(parse_url($meeting['committee']['url'], PHP_URL_PATH)) . '">'
        . htmlspecialchars($meeting['label']) . '</a>.</p>';
}

if (!$playable) {
    $page_body .= '
	<p class="video-processing">This video is still being processed. It’ll be here as soon as it’s
	ready, usually within a day.</p>';
} else {
    $page_body .= Video::page_player_html($video, null);
}

# PAGE SIDEBAR
$page_sidebar = '
		<div class="box">
			<h3>About This Video</h3>';
if (in_array($video['license'], ['public domain', 'public-domain'], true)) {
    $page_sidebar .= '
			<p>This is the official video recording of the ' . ucfirst($video['chamber']) . '. It’s in
			the public domain, and may be freely copied, edited, or incorporated into other works.</p>';
}
if (!empty($video['sponsor'])) {
    $page_sidebar .= '
			<p>This video appears courtesy of ' . Video::sponsor_html($video['sponsor']) . ', who
			purchased it from the General Assembly for Richmond Sunlight, so that it may be freely
			available to everybody.</p>';
}
if (!empty($video['day'])) {
    $page_sidebar .= '
			<p>See <a href="' . htmlspecialchars(parse_url($video['day'], PHP_URL_PATH)) . '">everything that
			happened in the General Assembly this day</a>'
        . (!empty($video['minutes'])
            ? ', including <a href="' . htmlspecialchars(parse_url($video['minutes'], PHP_URL_PATH) . '#' . parse_url($video['minutes'], PHP_URL_FRAGMENT)) . '">the '
                . ucfirst($video['chamber']) . '’s minutes</a>, as recorded by the clerk'
            : '')
        . '.</p>';
}
$page_sidebar .= '
		</div>';

if (!empty($video['same_day'])) {
    $page_sidebar .= '
		<div class="box">
			<h3>Also Recorded This Day</h3>
			<ul class="video-same-day">';
    foreach ($video['same_day'] as $other) {
        $page_sidebar .= '
				<li><a href="' . htmlspecialchars(parse_url($other['url'], PHP_URL_PATH)) . '">'
            . htmlspecialchars($other['label']) . '</a>'
            . ($other['status'] !== 'available' ? ' <small>(processing)</small>' : '') . '</li>';
    }
    $page_sidebar .= '
			</ul>
		</div>';
}

# OUTPUT THE PAGE
$page = new Page();
foreach ([
    'page_title',
    'page_body',
    'page_sidebar',
    'site_section',
    'html_head',
] as $prop) {
    if (isset(${$prop})) {
        $page->{$prop} = ${$prop};
    }
}
$page->process();
