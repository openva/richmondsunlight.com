<?php

// Unit tests for Log::database() — verifies messages are stored in the logs table.

require_once __DIR__ . '/../../htdocs/includes/settings.inc.php';
require_once __DIR__ . '/../../htdocs/includes/class.Database.php';
require_once __DIR__ . '/../../htdocs/includes/class.Log.php';

$database = new Database();
$db = $database->connect();

if ($db === false) {
    echo "Could not connect to database.\n";
    exit(1);
}

$failures = [];

function check($condition, $message) {
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

$log = new Log();

// Clean up any previous test rows.
$db->exec("DELETE FROM logs WHERE message LIKE 'TEST_LOG_%'");

// Test 1: database() returns true on success.
$result = $log->database('TEST_LOG_basic', 3);
check($result === true, 'database() should return true on success');

// Test 2: The row was actually inserted.
$stmt = $db->prepare("SELECT message, level FROM logs WHERE message = :msg");
$stmt->execute([':msg' => 'TEST_LOG_basic']);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
check($row !== false, 'Row should exist in logs table after database()');
check($row['message'] === 'TEST_LOG_basic', 'Stored message should match input');
check((int) $row['level'] === 3, 'Stored level should match input');

// Test 3: Different severity levels are stored correctly.
foreach ([1, 5, 8] as $level) {
    $msg = 'TEST_LOG_level_' . $level;
    $log->database($msg, $level);
    $stmt = $db->prepare("SELECT level FROM logs WHERE message = :msg");
    $stmt->execute([':msg' => $msg]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    check($row !== false, "Row should exist for level {$level}");
    check((int) $row['level'] === $level, "Stored level should be {$level}");
}

// Test 4: put() stores messages in the database regardless of verbosity.
$log->verbosity = 8; // Only emergencies go to Slack.
$log->output = 'none'; // Avoid side effects.
$log->put('TEST_LOG_via_put', 1); // Level 1 = debug, well below verbosity.
$stmt = $db->prepare("SELECT message FROM logs WHERE message = :msg");
$stmt->execute([':msg' => 'TEST_LOG_via_put']);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
check($row !== false, 'put() should store messages in the database even when below verbosity threshold');

// Test 5: database() returns false when no DB connection is available.
$saved = $GLOBALS['db_pdo'];
unset($GLOBALS['db_pdo']);
$result = $log->database('TEST_LOG_nodb', 3);
check($result === false, 'database() should return false when no DB connection exists');
$GLOBALS['db_pdo'] = $saved;

// Clean up.
$db->exec("DELETE FROM logs WHERE message LIKE 'TEST_LOG_%'");

if (!empty($failures)) {
    foreach ($failures as $failure) {
        echo "FAIL {$failure}\n";
    }
    exit(1);
}

echo "Log database tests passed.\n";
exit(0);
