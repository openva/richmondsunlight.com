<?php

###
# Video Transcript
#
# PURPOSE
# Emits the transcript of a span of a video file: as an HTML fragment, for players that load
# transcripts only when they're needed (e.g., on legislators' pages, which can list hundreds of
# clips), or as WebVTT captions, for a <track>.
#
# PARAMETERS
# file    The files.id of the video
# start   The start of the span, in seconds
# end     The end of the span, in seconds
# format  "html" (the default) or "vtt"
#
###

# INCLUDES
include_once 'settings.inc.php';
include_once 'vendor/autoload.php';

$file_id = filter_input(INPUT_GET, 'file', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$start = filter_input(INPUT_GET, 'start', FILTER_VALIDATE_FLOAT);
$end = filter_input(INPUT_GET, 'end', FILTER_VALIDATE_FLOAT);
$format = filter_input(INPUT_GET, 'format') === 'vtt' ? 'vtt' : 'html';

header('Content-Type: ' . ($format === 'vtt' ? 'text/vtt' : 'text/html') . '; charset=utf-8');

// An HTML transcript is for a clip, but captions may cover a whole recording.
$longest = $format === 'vtt' ? 86399 : 3600;
if (!$file_id || $start === false || $start === null || $end === false || $end === null
    || $start < 0 || $end <= $start || ($end - $start) > $longest) {
    http_response_code(400);
    exit();
}

$database = new Database();
$database->connect_mysqli();

$turns = Video::transcript_span($file_id, $start, min($end, 86399));

header('Cache-Control: public, max-age=86400');

if ($format === 'vtt') {
    echo Video::transcript_vtt($turns);
    exit();
}

if (count($turns) === 0) {
    echo '<p class="video-transcript-empty">There’s no transcript of this clip.</p>';
    exit();
}

echo Video::transcript_html($turns, $start);
