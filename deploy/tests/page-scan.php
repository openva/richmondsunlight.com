<?php

$url_prefix = 'http://localhost';

$failures = [];

$pages =
[
    [
        'url' => '/',
        'http_status' => '200',
        'string' => 'Welcome to Richmond Sunlight',
    ],
    [
        'url' => '/bills/',
        'http_status' => '200',
        'string' => 'bills found',
    ],
    [
        'url' => '/bills/2024/',
        'http_status' => '200',
        'string' => 'SB278',
    ],
    [
        'url' => '/bills/2024/1/',
        'http_status' => '200',
        'string' => 'No bills',
    ],
    [
        'url' => '/bills/tags/abortion/',
        'http_status' => '200',
        'string' => 'bill found',
    ],
    [
        'url' => '/bill/2024/hb0/',
        'http_status' => '404',
    ],
    [
        'url' => '/bill/2024/hb10223/',
        'http_status' => '404',
    ],
    [
        'url' => '/bill/2024/hb221/',
        'http_status' => '200',
        'string' => 'Cat Management',
    ],
    [
        'url' => '/bills/introduced/1000/',
        'http_status' => '200',
        'string' => 'Home instruction',
    ],
    [
        'url' => '/bills/activity/1000/',
        'http_status' => '200',
        'string' => 'Rereferred',
    ],
    [
        'url' => '/legislators/',
        'http_status' => '200',
        'string' => 'Charlottesville',
    ],
    [
        'url' => '/legislator/rcdeeds/',
        'http_status' => '200',
        'string' => 'Sen. Creigh Deeds',
    ],
    [
        'url' => '/legislator/jondoe/',
        'http_status' => '404',
    ],
    [
        'url' => '/photosynthesis/portfolios/',
        'http_status' => '200',
        'string' => '',
    ],
    [
        'url' => '/downloads/',
        'http_status' => '200',
        'string' => 'Metadata',
    ],
    [
        'url' => '/schedule/2024/01/31/',
        'http_status' => '200',
        'string' => 'Health Care',
    ],
    [
        'url' => '/schedule/2024/01/32/',
        'http_status' => '404',
    ],
    [
        'url' => '/schedule/',
        'http_status' => '200',
        'string' => 'Schedule for',
    ],
    [
        'url' => '/account/register/',
        'http_status' => '200',
        'string' => 'Create Your Account',
    ],
    [
        'url' => '/search/',
        'http_status' => '200',
        'string' => '',
    ],
    [
        'url' => '/search/?q=abortion&year=2024',
        'http_status' => '200',
        'string' => 'SB278',
    ],
    [
        'url' => '/search/?q=cat&year=',
        'http_status' => '200',
        'string' => 'HB221',
    ],
    [
        'url' => '/search/?q=nosuchresult',
        'http_status' => '200',
        'string' => '0 results found',
    ],
    [
        'url' => '/committees/',
        'http_status' => '200',
        'string' => 'Appropriations',
    ],
    [
        'url' => '/committee/house/appropriations/',
        'http_status' => '200',
        'string' => 'Transportation',
    ],
    [
        'url' => '/committee/house/nosuchcommittee/',
        'http_status' => '404',
    ],
    [
        'url' => '/statistics/',
        'http_status' => '200',
        'string' => 'Bills Introduced Daily',
    ],
];

/**
 * Iterate through the list of pages, testing each
 */
foreach ($pages as $page) {
    $ch = curl_init($url_prefix . $page['url']);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $content = curl_exec($ch);

    if (curl_errno($ch)) {
        echo 'cURL error: ' . curl_error($ch);
        continue;
    }

    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (!empty($page['http_status']) && $page['http_status'] != $http_status) {
        $failures[] = ['page' => $page, 'error' => ['http_status' => $http_status]];
        continue;
    }

    if (!empty($page['string']) && stristr($content, $page['string']) === false) {
        $failures[] = ['page' => $page, 'error' => ['string' => false]];
        continue;
    }

    curl_close($ch);
}

if (count($failures) > 0) {
    echo 'Page scan failed with ' . count($failures) . ' errors' . ":\n\n";

    foreach ($failures as $failure) {
        echo '* ' . $failure['page']['url'] . ' returned ';
        foreach ($failure['error'] as $key => $value) {
            if ($key == 'string') {
                $value = 'nothing that matched';
            }
            echo $value . ' for ' . $key . ' instead of ' . $failure['page'][$key];
        }
        echo "\n";
    }
    exit(1);
}

echo 'Tested ' . count($pages) . ' URLs, no errors found.' . "\n";
exit(0);
