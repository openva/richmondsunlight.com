<?php

// Smoke test for bill preview image generation.
// Verifies the endpoint returns a valid PNG image.

$failures = [];

function check($condition, $message) {
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

// Test the preview image endpoint
$url = 'http://localhost/bill/2025/hb41/preview.png';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);

$body = substr($response, $header_size);

check($http_code === 200, "Expected HTTP 200, got {$http_code}");
check(strpos($content_type, 'image/png') !== false, "Expected Content-Type image/png, got {$content_type}");
check(strlen($body) > 10000, "Expected image larger than 10KB, got " . strlen($body) . " bytes");

// Verify PNG magic bytes
$png_header = substr($body, 0, 8);
$expected_header = "\x89PNG\r\n\x1a\n";
check($png_header === $expected_header, "Response does not have valid PNG header");

// Test 404 for invalid bill
$url_404 = 'http://localhost/bill/2025/hb99999/preview.png';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url_404);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_exec($ch);
$http_code_404 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

check($http_code_404 === 404, "Expected HTTP 404 for invalid bill, got {$http_code_404}");

if (!empty($failures)) {
    foreach ($failures as $failure) {
        echo "❌ {$failure}\n";
    }
    exit(1);
}

echo "Bill preview image tests passed.\n";
exit(0);
