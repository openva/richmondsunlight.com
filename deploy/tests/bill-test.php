<?php

// Simple Bill unit checks against the seeded database.

require_once __DIR__ . '/../../htdocs/includes/settings.inc.php';
require_once __DIR__ . '/../../htdocs/includes/class.Database.php';
require_once __DIR__ . '/../../htdocs/includes/class.Bill2.php';

$db = new Database();
$db->connect_mysqli();

$failures = [];

function check($condition, $message) {
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

$bill = new Bill2();
$id = $bill->getid(2025, 'hb41');

check($id !== false, 'getid should return an ID for HB41 (2025)');
check(is_numeric($id), 'getid should return a numeric ID');

if ($id !== false) {
    $bill->id = $id;
    $info = $bill->info();

    check($info['number'] === 'hb41', 'info should return bill number hb41');
    check((int) $info['year'] === 2025, 'info should return session year 2025');
    check($info['chamber'] === 'house', 'info should return chamber house');
    check($info['status'] === 'failed committee', 'info should return status "failed committee"');
    check(strpos($info['catch_line'], 'Standards of Learning') !== false, 'info should include expected catch line fragment');
}

if (!empty($failures)) {
    foreach ($failures as $failure) {
        echo "❌ {$failure}\n";
    }
    exit(1);
}

echo "Bill2 getid/info tests passed.\n";
exit(0);
