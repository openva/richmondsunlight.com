<?php

###
# Video Captions
#
# PURPOSE
# Emits a video's captions, as WebVTT, for its player's <track>, by passing along the API's
# (/1.1/video/{slug}.vtt). They're served from this site because a <track> from another site needs
# the <video> to be marked crossorigin, which would also require CORS of the video's host, and
# not every host (e.g., the Internet Archive) allows that.
#
###

# INCLUDES
include_once 'includes/settings.inc.php';
include_once 'vendor/autoload.php';

$slug = strtolower((string) ($_GET['slug'] ?? ''));
$captions = Video::is_slug($slug) ? get_content(API_URL . '1.1/video/' . $slug . '.vtt') : false;

if ($captions === false || strpos($captions, 'WEBVTT') !== 0) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'There are no captions for this video.';
    exit();
}

header('Content-Type: text/vtt; charset=utf-8');
header('Cache-Control: max-age=3600, public');
echo $captions;
