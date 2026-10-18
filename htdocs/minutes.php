<?php

###
# Minutes
#
# PURPOSE
# Displays the minutes of a given chamber's meeting.
#
###

# CONSTANTS
define('VIDEO_CLIP_FUZZ_SECONDS', 5);

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
session_start();

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
    $minutes = preg_replace_callback(
        '/(HR|HB|SJR|SB|HJ|HJR) ([0-9]+)/',
        fn($m) => '<a href="/bill/' . $year . '/' . mb_strtolower($m[1] . $m[2]) . '/">' . $m[0] . '</a>',
        $minutes
    );

    # Retrieve the video of the floor session, if it's available. Committee meetings are recorded
    # on the same day, so prefer the file that's titled as a session.
    $sql = 'SELECT id, path, license, length, sponsor
			FROM files
			WHERE type="video" AND committee_id IS NULL AND date="' . $date . '"
			AND chamber="' . $chamber . '"
			ORDER BY (title LIKE "%Session%") DESC, id ASC
			LIMIT 1';
    $result = mysqli_query($GLOBALS['db'], $sql);
    if (mysqli_num_rows($result) > 0) {
        $video = mysqli_fetch_array($result);
        $video = array_map('stripslashes', $video);
    }

    # Create a new video object.
    $video2 = new Video();
    if (isset($video['id'])) {
        $video2->id = $video['id'];
        $video2->get_video();
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
			' . $video['sponsor'] . '
			<p>They purchased this video from the General Assembly for Richmond Sunlight, so that
			it may be freely available to everybody.</p>';
        } else {
            $page_sidebar .= '
			<p>This video appears courtesy of ' . $video['sponsor'] . ', who purchased this video
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
                $tag = array_map('stripslashes', $tag);
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

    if (!empty($video2->path)) {
        $html_head .= '
		<script src="/js/minutes-video.js" defer></script>';

        # Find the bills and the legislators in this video, using the chyrons that identify them.
        $video2->fuzz = VIDEO_CLIP_FUZZ_SECONDS;
        $video2->clip_type = 'bills';
        $bill_clips = $video2->index_clips() ? array_values((array) $video2->clips) : [];
        unset($video2->clips);
        $video2->clip_type = 'legislators';
        $legislator_clips = $video2->index_clips() ? array_values((array) $video2->clips) : [];

        $bills = [];
        $bill_ids = array_unique(array_map(fn($clip) => (int) $clip->linked_id, $bill_clips));
        if (count($bill_ids) > 0) {
            $result = mysqli_query($GLOBALS['db'], 'SELECT bills.id, bills.number, bills.catch_line,
                    sessions.year
                FROM bills
                LEFT JOIN sessions
                    ON bills.session_id = sessions.id
                WHERE bills.id IN (' . implode(',', $bill_ids) . ')');
            while ($bill = mysqli_fetch_assoc($result)) {
                $bills[$bill['id']] = $bill;
            }
        }

        # Group each legislator's appearances, and list legislators by last name.
        $speakers = [];
        $person_ids = array_unique(array_map(fn($clip) => (int) $clip->linked_id, $legislator_clips));
        if (count($person_ids) > 0) {
            $result = mysqli_query($GLOBALS['db'], 'SELECT id, name, shortname
                FROM people
                WHERE id IN (' . implode(',', $person_ids) . ')');
            while ($person = mysqli_fetch_assoc($result)) {
                $speakers[$person['id']] = $person + ['label' => null, 'starts' => []];
            }
        }
        foreach ($legislator_clips as $clip) {
            if (!isset($speakers[$clip->linked_id])) {
                continue;
            }
            $speakers[$clip->linked_id]['label'] = $clip->legislator_name;
            $speakers[$clip->linked_id]['starts'][] = max(0, $clip->start);
        }
        uasort($speakers, fn($a, $b) => strcmp($a['name'], $b['name']));

        $transcript = Video::transcript_span((int) $video2->id, 0, 86399);

        $page_body .= '
		<section id="video" class="minutes-video" aria-labelledby="video-heading">
			<h2 id="video-heading">Video</h2>
			<div class="minutes-video-player">
				<video controls preload="metadata" playsinline src="' . htmlspecialchars($video2->path) . '">
					<p>Your browser can’t play this video.
					<a href="' . htmlspecialchars($video2->path) . '">Download it instead.</a></p>
				</video>
				<p class="minutes-video-now" aria-live="polite" hidden></p>
			</div>
			<p class="minutes-video-download"><a href="' . htmlspecialchars($video2->path) . '">Download this video</a>'
            . (!empty($video['length']) ? ' (' . Video::clock(time_to_seconds($video['length'])) . ')' : '') . '</p>

			<div class="minutes-video-guide">
				<div class="minutes-video-tabs" role="tablist" aria-label="Find your way around this video" hidden>';
        $panels = [];
        if (count($bill_clips) > 0) {
            $panels['video-bills'] = 'Bills <span>' . count($bill_clips) . '</span>';
        }
        if (count($speakers) > 0) {
            $panels['video-speakers'] = 'Speakers <span>' . count($speakers) . '</span>';
        }
        if (count($transcript) > 0) {
            $panels['transcript'] = 'Transcript';
        }
        foreach ($panels as $id => $label) {
            $page_body .= '
					<button type="button" role="tab" id="tab-' . $id . '" aria-controls="' . $id . '">' . $label . '</button>';
        }
        $page_body .= '
				</div>';

        if (count($bill_clips) > 0) {
            $page_body .= '
				<section id="video-bills" class="minutes-video-panel" role="tabpanel" aria-labelledby="tab-video-bills">
					<h3>Bills</h3>
					<ol class="minutes-video-scroll minutes-video-list">';
            foreach ($bill_clips as $clip) {
                $bill = $bills[$clip->linked_id] ?? null;
                $start = max(0, $clip->start);
                $page_body .= '
						<li data-start="' . $start . '" data-end="' . $clip->end . '">
							<button type="button" class="video-timestamp" data-seek="' . $start . '"
								aria-label="Play from ' . Video::clock($start) . '">' . Video::clock($start) . '</button>
							<span class="minutes-video-item">';
                if ($bill !== null) {
                    $page_body .= '<a class="minutes-video-bill" href="/bill/' . $bill['year'] . '/' . $bill['number'] . '/">'
                        . mb_strtoupper(preg_replace('/^([a-z]+)/', '$1 ', $bill['number'])) . '</a> '
                        . htmlspecialchars(html_entity_decode((string) $bill['catch_line'], ENT_QUOTES));
                } else {
                    $page_body .= htmlspecialchars($clip->bill_number ?? '');
                }
                $page_body .= '</span>
							<span class="minutes-video-length" title="Length">' . Video::clock($clip->duration) . '</span>
						</li>';
            }
            $page_body .= '
					</ol>
				</section>';
        }

        if (count($speakers) > 0) {
            $page_body .= '
				<section id="video-speakers" class="minutes-video-panel" role="tabpanel" aria-labelledby="tab-video-speakers">
					<h3>Speakers</h3>
					<ul class="minutes-video-scroll minutes-video-list">';
            foreach ($speakers as $speaker) {
                $page_body .= '
						<li data-starts="' . implode(' ', $speaker['starts']) . '">
							<a class="minutes-video-item" href="/legislator/' . htmlspecialchars($speaker['shortname']) . '/">'
                    . htmlspecialchars($speaker['label']) . '</a>
							<span class="minutes-video-times">';
                foreach ($speaker['starts'] as $start) {
                    $page_body .= '<button type="button" class="video-timestamp" data-seek="' . $start . '"
								aria-label="Play from ' . Video::clock($start) . '">' . Video::clock($start) . '</button> ';
                }
                $page_body .= '</span>
						</li>';
            }
            $page_body .= '
					</ul>
				</section>';
        }

        if (count($transcript) > 0) {
            $page_body .= '
				<section id="transcript" class="minutes-video-panel" role="tabpanel" aria-labelledby="tab-transcript">
					<h3>Transcript</h3>
					<div class="minutes-video-scroll video-transcript">'
                . Video::transcript_html($transcript) . '
					</div>
					<p class="minutes-video-note">This transcript comes from the closed captions, which are
					written in real time during the session, so it’s far from perfect.</p>
				</section>';
        }

        $page_body .= '
			</div>
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
] as $prop) {
    if (isset(${$prop})) {
        $page->{$prop} = ${$prop};
    }
}
$page->process();
