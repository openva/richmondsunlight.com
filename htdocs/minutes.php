<?php

###
# Minutes
#
# PURPOSE
# Displays the minutes of a given chamber's meeting.
#
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once 'includes/settings.inc.php';
include_once 'vendor/autoload.php';

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database();
$database->connect_mysqli();

# INITIALIZE SESSION
resume_session();

# LOCALIZE AND CLEAN UP VARIABLES
$chamber = mysqli_real_escape_string($GLOBALS['db'], $_REQUEST['chamber']);
$date = mysqli_real_escape_string($GLOBALS['db'], $_REQUEST['year']) . '-' . mysqli_real_escape_string($GLOBALS['db'], $_REQUEST['date']);

# PAGE METADATA
$page_title = date('m/d/Y', strtotime($date)) . ' ' . ucfirst($chamber) . ' Proceedings';
$site_section = 'minutes';

$html_head = '';

# RETRIEVE THE MINUTES FROM THE DATABASE
$sql = 'SELECT text
		FROM minutes
		WHERE date="' . $date . '" AND chamber="' . $chamber . '"';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) == 0) {
    $page_body = '<p>No minutes are available for that date.</p>';
} else {
    $minutes = mysqli_fetch_array($result);
    $minutes = stripslashes($minutes['text']);

    # Turn every bill number into a link.
    // This doesn't catch every bill number. Lists are often presented as "HRs 1 and 2," for
    // instance, and this can't pick those up.
    $year = (int) $_REQUEST['year'];
    $session_year = $year;
    $minutes = preg_replace_callback(
        '/(HR|HB|SJR|SB|HJ|HJR) ([0-9]+)/',
        fn($m) => '<a href="/bill/' . $year . '/' . mb_strtolower($m[1] . $m[2]) . '/">' . $m[0] . '</a>',
        $minutes
    );

    # Get the videos recorded in this chamber that day, from the API, and the floor session's video
    # in full. (Committees meet the same day, and the floor sometimes sits twice.)
    $day_videos = Video::list_from_api(['chamber' => $chamber, 'date' => $date]);
    $video = null;
    foreach ($day_videos as $day_video) {
        if ($day_video['meeting']['type'] === 'floor') {
            $video = Video::from_api($day_video['slug']);
            break;
        }
    }

    # Its ID, for the tags of the bills it covers.
    $video2 = new Video();
    if ($video !== null) {
        $video2->id = (int) $day_video['id'];
    }


    # PAGE SIDEBAR
    $page_sidebar = '

		<div class="box">
			<h3>Explanation</h3>
			<p>These are the official minutes of the ' . ucfirst($chamber) . ', as recorded by the
			clerk, for ' . date('m/d/Y', strtotime($date)) . ', presented verbatim. They’re
			pretty dry, but they are the best way to see what the ' . ucfirst($chamber) . ' did on
			a given day.</p>';
    if (isset($video['license']) && $video['license'] == 'public domain') {
        $page_sidebar .= '
			<p>Thankfully, there’s video. The video is the official video recording of the
			chamber for the same date. It’s is in the public domain, and may be freely
			copied, edited, or incorporated into other works.</p>';
    }

    if (!empty($video['sponsor'])) {
        if (mb_strpos($video['sponsor'], 'img src') !== false) {
            $page_sidebar .= '
			<p>This video appears courtesy of:</p>
			' . Video::sponsor_html($video['sponsor']) . '
			<p>They purchased this video from the General Assembly for Richmond Sunlight, so that
			it may be freely available to everybody.</p>';
        } else {
            $page_sidebar .= '
			<p>This video appears courtesy of ' . Video::sponsor_html($video['sponsor']) . ', who purchased this video
			from the General Assembly for Richmond Sunlight, so that it may be freely available
			to everybody.</p>';
        }
    }

    $page_sidebar .= '
		</div>';

    # Get a list of tags for this video.
    $tags = $video2->file_tags();
    if ($tags !== false) {
        $page_sidebar .= '
			<div class="box">
				<h3>What The ' . ucfirst($chamber) . ' Dealt with Today</h3>
				<div class="tags">';
        $page_sidebar .= tag_cloud($tags);
        $page_sidebar .= '
				</div>
			</div>';
    }

    # If we can't gather time-based tags, then display topic-based tags.
    else {
        # Determine the most popular tags for today's actions.
        $sql = 'SELECT tags.tag, COUNT(*) AS count
				FROM bills_status
				JOIN bills ON bills_status.bill_id=bills.id
				JOIN tags ON bills_status.bill_id=tags.bill_id
				WHERE bills_status.date="' . $date . '" AND bills.current_chamber="' . $chamber . '"
				GROUP BY tag
				ORDER BY count DESC';
        $result = mysqli_query($GLOBALS['db'], $sql);
        if (mysqli_num_rows($result) > 0) {
            # Build up an array of tags, with the key being the tag and the value being the count.
            $tags = array();
            while ($tag = mysqli_fetch_array($result)) {
                $tag = array_map('stripslashes_nullsafe', $tag);
                $tags[$tag['tag']] = $tag['count'];
            }

            # Sort the tags in reverse order by key (their count), shave off the top 30, and then
            # resort alphabetically.
            arsort($tags);
            $tags = array_slice($tags, 0, 30, true);
            ksort($tags);

            $page_sidebar .= '
				<div class="box">
					<h3>What the ' . ucfirst($chamber) . ' Dealt with Today</h3>
					<div class="tags">';
            $page_sidebar .= tag_cloud($tags);
            $page_sidebar .= '
					</div>
				</div>';
        }
    }

    $page_body = '';

    if ($video !== null && $video['status'] === 'available') {
        $html_head .= '
		<script src="/js/video-player.js" defer></script>';
        $page_body .= Video::page_player_html($video);
    }

    # List every video recorded that day, each with its own page.
    if (count($day_videos) > 0) {
        $page_body .= '
		<section id="day-videos">
			<h2>' . (count($day_videos) === 1 ? 'Video of This Day' : 'Videos of This Day') . '</h2>
			<ul class="video-list">';
        foreach ($day_videos as $day_video) {
            $page_body .= '
				<li><a href="' . htmlspecialchars(parse_url($day_video['url'], PHP_URL_PATH)) . '">'
                . htmlspecialchars($day_video['meeting']['label']) . '</a>'
                . (!empty($day_video['length']) ? ' <small>' . Video::clock($day_video['length']) . '</small>' : '')
                . ($day_video['status'] !== 'available' ? ' <small>(processing)</small>' : '') . '</li>';
        }
        $page_body .= '
			</ul>
		</section>';
    }

    # Show the minutes.
    $page_body .= '
		<section id="minutes">
			<h2>Minutes</h2>'
            . nl2p($minutes) . '
		</section>';
}

// OUTPUT THE PAGE
$page = new Page();
foreach ([
    'page_title',
    'page_body',
    'page_sidebar',
    'site_section',
    'browser_title',
    'html_head',
    'body_tag',
    'session_year',
] as $prop) {
    if (isset(${$prop})) {
        $page->{$prop} = ${$prop};
    }
}
$page->process();
