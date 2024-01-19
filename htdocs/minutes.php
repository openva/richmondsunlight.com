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
session_start();

# LOCALIZE AND CLEAN UP VARIABLES
$chamber = mysqli_real_escape_string($GLOBALS['db'], $_REQUEST['chamber']);
$date = mysqli_real_escape_string($GLOBALS['db'], $_REQUEST['year']) . '-' . mysqli_real_escape_string($GLOBALS['db'], $_REQUEST['date']);

# PAGE METADATA
$page_title = date('m/d/Y', strtotime($date)) . ' ' . ucfirst($chamber) . ' Proceedings';
$site_section = 'minutes';

$html_head = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Get the link and video elements
    var link = document.getElementByClass("marker");
    var video = document.getElementById("player");

    // Listen for click on the link
    link.addEventListener("click", function(event) {
        // Prevent the default action of the anchor tag
        event.preventDefault();

        // Get the time from the data-seek attribute and seek the video to that time
        var time = link.getAttribute("data-time");
        video.currentTime = time;
    });
});
</script>';

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
    // Unfortunately, this doesn't actually work for every bill number. Lists are often
    // presented as "HRs 1 and 2," for instance, and this can't pick those up. Also, this
    // links using the existing case (upper), and should be linking in lower, but preg_replace
    // lacks a mechanism by which to make changes mid-replacement.
    // CORRECTION TO ABOVE: It can make changes -- use preg_replace_callback();
    $minutes = preg_replace('/(HR|HB|SJR|SB) ([0-9A-Z]+)/', '<a href="/bill/' . $_REQUEST['year'] . '/$1$2/">$1 $2</a>', $minutes);

    # Retrieve a single video, if it's available.
    $sql = 'SELECT id, author_name, title, html, path, description, license, length, sponsor,
			video_index_cache AS index_cache, transcript,
				(SELECT COUNT(*)
				FROM video_index
				WHERE file_id=files.id) AS index_data
			FROM files
			WHERE type="video" AND committee_id IS NULL AND date="' . $date . '"
			AND chamber="' . $chamber . '"
			LIMIT 1';
    $result = mysqli_query($GLOBALS['db'], $sql);
    if (mysqli_num_rows($result) > 0) {
        $video = mysqli_fetch_array($result);
        $video = array_map('stripslashes', $video);
    }

    # Create a new video object.
    $video2 = new Video();
    $video2->id = $video['id'];
    $video2->get_video();

    /*
     * Retrieve a transcript.
     */
    if ($video2->generate_transcript() === true) {
        $video['transcript'] = '
		<style>
			dl.transcript dt {
				font-weight: bold;
				float: left;
				clear: left;
				width: 10em;
				text-align: right;
			}
			dl.transcript dd {
				margin-left: 10.5em;
			}
			dl.transcript dt + dd {
				margin-bottom: 1em;
			}
		</style>
		<dl class="transcript">';
        $i = 1;
        foreach ($video2->transcript as $line) {
            if (empty($line['name'])) {
                $line['name'] = '[Unknown]';
            }
            $video['transcript'] .= '
				<dt id="line-' . $i . '">';
            if (!isset($line['shortname'])) {
                $video['transcript'] .= $line['name'];
            } else {
                $video['transcript'] .= '<a href="/legislator/' . $line['shortname']
                    . '/" class="legislator">' . $line['name'] . '</a>';
            }
            $video['transcript'] .= '</dt>
				<dd data-time="' . $line['time_start'] . '" data-time-end="' . $line['time_end'] . '">' . $line['text'] . '</dd>';
            $i++;
        }
        $video['transcript'] .= '</dl>';
    }

    # If we have a path, use that.
    if (mb_substr($video['path'], -3) == 'mp4') {
        $video['html'] = '
		<style>
			#player, video {
				width: 100%;
			}
		</style>
		<div class="player" id="player">
			<video src="' . $video['path'] . '" controls></video>
		</div>';
    }


    # PAGE SIDEBAR
    $page_sidebar = '

		<div class="box">
			<h3>Explanation</h3>
			<p>These are the official minutes of the ' . ucfirst($chamber) . ', as recorded by the
			clerk, for ' . date('m/d/Y', strtotime($date)) . ', presented verbatim. They’re
			pretty dry, but they are the best way to see what the ' . ucfirst($chamber) . ' did on
			a given day.</p>';
    if ($video['license'] == 'public domain') {
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
            while ($tag = mysqli_fetch_array($result)) {
                $tag = array_map('stripslashes', $tag);
                $tags[$tag{tag}] = $tag['count'];
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

    #  Create the tabs in the header.
    $page_body = '
		<div id="sources" class="tabs">
		<ul>';
    if (!empty($video['html'])) {
        $page_body .= '<li><a href="#video">Video</a><li>';
    }
    if (!empty($video['transcript'])) {
        $page_body .= '<li><a href="#transcript">Transcript</a><li>';
    }
    if (!empty($minutes)) {
        $page_body .= '<li><a href="#minutes">Minutes</a><li>';
    }
    $page_body .= '</ul>';

    if (!empty($video['html']) || !empty($video2->path)) {
        $page_body .= '
			<div id="video">
				<div class="video" style="width: 100%;">
					' . $video['html'] . '
				</div>';

        $video2->fuzz = 5;

        $video2->clip_type = 'bills';
        $video2->get_clips();
        if (isset($video2->clips)) {
            $bill_clips = $video2->clips;
        }

        $video2->clip_type = 'legislators';
        $video2->index_clips();
        if (isset($video2->clips)) {
            $legislator_clips = $video2->clips;
        }

        if (isset($video2->path)) {
            $page_body .= '<p><a href="' . $video2->path . '">Download this Video</a></p>';
        }

        if (
            isset($video['html']) &&
            (count($bill_clips) > 0 || count($legislator_clips) > 0 || count($video2->screenshots) > 0)
        ) {
            $page_body .= '<h3>Index</h3>
				<div id="video-index" class="tabs">
				<ul>';
            if (count($bill_clips) > 0) {
                $page_body .= '<li><a href="#bill">By Bill</a></li>';
            }
            if (count($legislator_clips) > 0) {
                $page_body .= '<li><a href="#legislator">By Legislator</a></li>';
            }

            $page_body .= '<li><a href="#time">By Time</a></li>
				</ul>

				<div id="bill">';

            foreach ($bill_clips as $clip) {
                $page_body .= '<div class="marker" data-time="' . $clip->start . '" style="background-image: url(' . $clip->screenshot . ')">
					<span>' . mb_strtoupper($clip->bill_number) . '—' . seconds_to_time($clip->duration) . '</span></div>';
            }
            $page_body .= '</div>

				<div id="legislator">';
            foreach ($legislator_clips as $clip) {
                $page_body .= '<div class="marker" data-time="' . $clip->start . '" style="background-image: url(' . $clip->screenshot . ')">
				<span>' . $clip->legislator_name . '—' . mb_substr(seconds_to_time($clip->duration), 3) . '</span></div>';
            }
            $page_body .= '</div>';

            $video2->screenshots();
            $page_body .= '<div id="time">';
            foreach ($video2->screenshots as $screenshot) {
                $page_body .= '<div class="marker" data-time="' . $screenshot->seconds . '" style="background-image: url(' . $screenshot->filename . ')">
				<span>' . mb_substr(seconds_to_time($screenshot->seconds), 0, 5) . '</span></div>';
            }
            $page_body .= '</div>
				</div>';
        }

        # Close the DIV for the video tab.
        $page_body .= '</div>';
    }

    # Show the minutes.
    $page_body .= '
		<div id="minutes">
			<h2>Minutes</h2>'
            . nl2p($minutes) . '
		</div>';

    # Show the transcript.
    if (!empty($video['transcript'])) {
        $page_body .= '

			<div id="transcript">

			<h2>Transcript</h2>
			<p><em>What follows is a transcript of this day’s session that was created as
			closed-captioning text, written in real time during the session. We have made
			an effort to automatically clean up the text, but it is far from perfect.</em></p>';


        $page_body .= $video['transcript'];

        # Close the transcript DIV.
        $page_body .= '</div>';
    }

    # Close the DIV for the tabbed interface.
    $page_body .= '</div>';
}


# OUTPUT THE PAGE
$page = new Page();
$page->page_title = $page_title;
$page->html_head = $html_head;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->process();
