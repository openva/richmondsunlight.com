<?php

// Simple Legislator unit checks against the seeded database.

require_once __DIR__ . '/../../htdocs/includes/settings.inc.php';
require_once __DIR__ . '/../../htdocs/includes/functions.inc.php';
require_once __DIR__ . '/../../htdocs/includes/class.Database.php';
require_once __DIR__ . '/../../htdocs/includes/class.Legislator.php';

$db = new Database();
$db->connect_mysqli();

$failures = [];

function lcheck($condition, $message)
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

$legislator = new Legislator();
$id = $legislator->getid('rcdeeds');

lcheck($id !== false, 'getid should return an ID for rcdeeds');
lcheck($id == '269', 'getid should return ID 269');

if ($id !== false) {
    $info = $legislator->info($id);

    lcheck($info !== false, 'info should return data for rcdeeds');
    lcheck($info['shortname'] === 'rcdeeds', 'info should return shortname rcdeeds');
    lcheck($info['name'] === 'Creigh Deeds', 'info should pivot name to "Creigh Deeds"');
    lcheck($info['chamber'] === 'senate', 'info should return chamber senate');
    lcheck((int) $info['district'] === 11, 'info should return district 11');
    lcheck($info['party'] === 'D', 'info should return party D');
    lcheck($info['party_name'] === 'Democratic', 'info should return party_name Democratic');
    lcheck($info['prefix'] === 'Sen.', 'info should set prefix Sen.');
    lcheck($info['suffix'] === '(D-Charlottesville)', 'info should set suffix with party/place');
    lcheck($info['email'] === 'senatordeeds@senate.virginia.gov', 'info should return expected email');
    lcheck($info['lis_id'] === 'S62', 'info should prefix senate LIS ID');
    lcheck($info['website_name'] === 'senatordeeds.com', 'info should derive website_name');
}

if (!empty($failures)) {
    foreach ($failures as $failure) {
        echo "❌ {$failure}\n";
    }
    exit(1);
}

echo "Legislator getid/info tests passed.\n";
exit(0);
