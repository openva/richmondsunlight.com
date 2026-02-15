<?php

// Figure out if this is running in Docker
if (file_exists('/.dockerenv')) {
    $url_prefix = 'http://rs_web';
} elseif (is_readable('/proc/1/cgroup') && strpos(file_get_contents('/proc/1/cgroup'), '/docker/') !== false) {
    $url_prefix = 'http://rs_web';
} else {
    $url_prefix = 'http://localhost:8000';
}

$failures = [];

echo 'Running front end tests...' . "\n";

$pages =
[

    [
        'url' => '/search/?q=learning&year=2025',
        'http_status' => '200',
        'string' => 'HB41',
    ],
    [
        'url' => '/search/?q=tax&year=',
        'http_status' => '200',
        'string' => 'HB1561',
    ],
    [
        'url' => '/search/?q=nosuchresult',
        'http_status' => '200',
        'string' => '0 results found',
    ],
];

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
        'strings' => ['bills found'],
    ],
    [
        'url' => '/bills/2025/',
        'http_status' => '200',
        'string' => 'SB305',
    ],
    [
        'url' => '/bills/2025/1/',
        'http_status' => '200',
        'string' => 'No bills',
    ],
    [
        'url' => '/bills/tags/civics/',
        'http_status' => '200',
        'string' => 'bills found',
    ],
    [
        'url' => '/bill/2025/hb0/',
        'http_status' => '404',
    ],
    [
        'url' => '/bill/2025/hb10223/',
        'http_status' => '404',
    ],
    [
        'url' => '/bill/2025/hb2049/',
        'http_status' => '200',
        'string' => 'Retail Sales and Use Tax',
    ],
    [
        'url' => '/bills/introduced/1000/',
        'http_status' => '200',
        'string' => 'food allergy',
    ],
    [
        'url' => '/bills/activity/1000/',
        'http_status' => '200',
        'string' => 'Senate substitute rejected',
    ],
    [
        'url' => '/legislators/',
        'http_status' => '200',
        'strings' =>
            ['Charlottesville', 'Richmond', 'Virginia Beach', 'Norfolk'],
    ],
    [
        'url' => '/legislator/rcdeeds/',
        'http_status' => '200',
        'strings' =>
            ['Sen. Creigh Deeds', 'Democrat', 'District 11', 'Charlottesville'],
    ],
    [
        'url' => '/legislator/rlware/',
        'http_status' => '200',
        'strings' => ['Powhatan', 'January 1998', 'DelLWare&#064;house.virginia.gov']
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
        'url' => '/schedule/2025/01/13/',
        'http_status' => '200',
        'strings' => ['Neurological Injury', 'Zoning'],
    ],
    [
        'url' => '/schedule/2025/01/32/',
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
        'strings' => ['Create Your Account', 'Password', 'E-Mail'],
    ],
    [
        'url' => '/search/',
        'http_status' => '200',
        'string' => '',
    ],
    [
        'url' => '/committees/',
        'http_status' => '200',
        'strings' => ['Appropriations', 'Education', 'Welfare', 'Courts of Justice'],
    ],
    [
        'url' => '/committee/house/appropriations/',
        'http_status' => '200',
        'strings' => ['House Appropriations Committee', 'Transportation', 'Higher Education'],
    ],
    [
        'url' => '/committee/house/nosuchcommittee/',
        'http_status' => '404',
    ],
    [
        'url' => '/statistics/',
        'http_status' => '200',
        'strings' => ['Bills Introduced Daily', 'Top 10 Bill Filers', 'Top 10 Most-Viewed Bills'],
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
        echo '❌ '  . $page['url'] . "\n";
        continue;
    }

    if (!empty($page['string'])) {
        if (stristr($content, $page['string']) === false) {
            $failures[] = ['page' => $page, 'error' => ['string' => false]];
            echo '❌ '  . $page['url'] . "\n";
            continue;
        }
    }

    if (!empty($page['strings']) && is_array($page['strings'])) {
        $missing = [];
        foreach ($page['strings'] as $needle) {
            if (stristr($content, $needle) === false) {
                $missing[] = $needle;
            }
        }
        if (!empty($missing)) {
            $failures[] = ['page' => $page, 'error' => ['strings' => $missing]];
            echo '❌ '  . $page['url'] . "\n";
            continue;
        }
    }

    echo '✅ '  . $page['url'] . "\n";
}

if (count($failures) > 0) {
    echo 'Page scan failed with ' . count($failures) . ' errors' . ":\n\n";

    foreach ($failures as $failure) {
        echo '* ' . $failure['page']['url'] . ' returned ';
        foreach ($failure['error'] as $key => $value) {
            if ($key == 'string') {
                $value = 'nothing that matched';
            }
            if ($key == 'strings' && is_array($value)) {
                $value = 'missing: ' . implode(', ', $value);
                $expected = 'expected all: ' . implode(', ', $failure['page']['strings']);
                echo $value . ' (' . $expected . ')';
                continue;
            }
            echo $value . ' for ' . $key . ' instead of ' . $failure['page'][$key];
        }
        echo "\n";
    }
    exit(1);
}

echo 'Tested ' . count($pages) . ' URLs, no errors found.' . "\n";
exit(0);
