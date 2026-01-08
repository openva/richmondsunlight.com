<?php

/**
 * Bill Preview Image Generator
 *
 * Generates and serves Open Graph preview images for bill pages.
 * URL: /bill/{year}/{bill}/preview.png
 */

include_once 'includes/settings.inc.php';
include_once 'includes/functions.inc.php';

// Validate inputs
$year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1900, 'max_range' => 2100]
]);

$bill_number = isset($_GET['bill']) ? $_GET['bill'] : '';
if (!preg_match('/^[hsrbj]{1,3}\d{1,4}$/i', $bill_number)) {
    http_response_code(404);
    exit();
}

if ($year === false || $year === null) {
    http_response_code(404);
    exit();
}

$bill_number = mb_strtolower($bill_number);

// Get bill data from API
$json_url = API_URL . '1.1/bill/' . $year . '/' . $bill_number . '.json';
$json = get_content($json_url);

if ($json === false) {
    http_response_code(404);
    exit();
}

$bill = json_decode($json, true);

if ($bill === null || isset($bill['error'])) {
    http_response_code(404);
    exit();
}

// Ensure year is set in the bill data
if (!isset($bill['year'])) {
    $bill['year'] = $year;
}

// Generate or retrieve cached image
$preview = new BillPreviewImage($bill);

if ($preview->isCached()) {
    $image_path = $preview->getCachePath();
} else {
    $image_path = $preview->generate();
}

if ($image_path === null || !file_exists($image_path)) {
    // Return a default fallback image
    $fallback = $_SERVER['DOCUMENT_ROOT'] . '/images/templates/new/richmond-sunlight-logo.png';
    if (file_exists($fallback)) {
        header('Content-Type: image/png');
        header('Cache-Control: max-age=3600, public');
        readfile($fallback);
    } else {
        http_response_code(404);
    }
    exit();
}

// Serve the image
header('Content-Type: image/png');
header('Content-Length: ' . filesize($image_path));
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($image_path)) . ' GMT');
header('ETag: "' . md5_file($image_path) . '"');

// Cache for 7 days for current session, 30 days for older
$is_current_session = isset($bill['session_id'])
    && defined('SESSION_ID')
    && $bill['session_id'] == SESSION_ID;
$max_age = $is_current_session ? (60 * 60 * 24 * 7) : (60 * 60 * 24 * 30);
header('Cache-Control: max-age=' . $max_age . ', public');

readfile($image_path);
