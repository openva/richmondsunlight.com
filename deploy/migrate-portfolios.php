<?php

/*
 * Repairs Photosynthesis's tables, once, as phase 1 of docs/portfolios.md describes:
 *
 * 1. Gives every portfolio without a hash one (Portfolio::create() always gives one now).
 * 2. Deletes the portfolios, bills, watch lists, and settings of users who no longer exist (until
 *    now, deleting an account left them behind). Their notes on bills were shown among the bills'
 *    comments, so those go too.
 * 3. Deletes watch lists that no portfolio uses, and bills in portfolios that don't exist.
 * 4. Gives users who have portfolios, but no Photosynthesis settings (dashboard_user_data), the
 *    settings that email needs (phase 2).
 *
 * Run it from the site's root directory. By itself, it only says what it would do:
 *
 *     php deploy/migrate-portfolios.php
 *
 * Then, to do it:
 *
 *     php deploy/migrate-portfolios.php --apply
 *
 * It can be run again safely: a second run finds nothing to do.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/../htdocs/includes/settings.inc.php';
require __DIR__ . '/../htdocs/includes/class.Database.php';
require __DIR__ . '/../htdocs/includes/class.Portfolio.php';

$apply = in_array('--apply', $argv, true);

$database = new Database();
$db = $database->connect_mysqli();
if ($db === false) {
    fwrite(STDERR, "Could not connect to the database.\n");
    exit(1);
}

function count_of(mysqli $db, string $sql): int
{
    return (int) mysqli_fetch_row(mysqli_query($db, $sql))[0];
}

echo $apply ? "Repairing Photosynthesis's tables.\n" : "Not changing anything (run with --apply to make these changes).\n";

# 1. Portfolios without a hash.
$result = mysqli_query($db, 'SELECT id FROM dashboard_portfolios WHERE hash = "" OR hash IS NULL');
$missing = mysqli_fetch_all($result, MYSQLI_ASSOC);
echo count($missing) . " portfolio(s) without a hash\n";
foreach ($missing as $row) {
    do {
        $hash = Portfolio::new_hash();
        $taken = mysqli_execute_query($db, 'SELECT 1 FROM dashboard_portfolios WHERE hash = ?', [$hash]);
    } while (mysqli_num_rows($taken) > 0);
    echo '  portfolio ' . $row['id'] . ' gets ' . $hash . "\n";
    if ($apply) {
        mysqli_execute_query($db, 'UPDATE dashboard_portfolios SET hash = ? WHERE id = ?', [$hash, $row['id']]);
    }
}

# 2. What's left of deleted users.
$gone = 'NOT EXISTS (SELECT 1 FROM users WHERE users.id = %s.user_id)';
foreach (['dashboard_bills', 'dashboard_portfolios', 'dashboard_watch_lists', 'dashboard_user_data'] as $table) {
    $where = sprintf($gone, $table);
    $count = count_of($db, 'SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $where);
    echo $count . ' row(s) of ' . $table . " belonging to users who no longer exist\n";
    if ($apply && $count > 0) {
        mysqli_query($db, 'DELETE FROM ' . $table . ' WHERE ' . $where);
    }
}

# 3. Watch lists that no portfolio uses.
$where = 'NOT EXISTS (SELECT 1 FROM dashboard_portfolios p WHERE p.watch_list_id = dashboard_watch_lists.id)';
$result = mysqli_query($db, 'SELECT id FROM dashboard_watch_lists WHERE ' . $where);
$unused = array_column(mysqli_fetch_all($result, MYSQLI_ASSOC), 'id');
echo count($unused) . ' watch list(s) that no portfolio uses' . (count($unused) ? ' (' . implode(', ', $unused) . ')' : '') . "\n";
if ($apply && count($unused) > 0) {
    mysqli_query($db, 'DELETE FROM dashboard_watch_lists WHERE ' . $where);
}

# 4. Bills in portfolios that no longer exist.
$where = 'NOT EXISTS (SELECT 1 FROM dashboard_portfolios p WHERE p.id = dashboard_bills.portfolio_id)';
$count = count_of($db, 'SELECT COUNT(*) FROM dashboard_bills WHERE ' . $where);
echo $count . " bill(s) in portfolios that no longer exist\n";
if ($apply && $count > 0) {
    mysqli_query($db, 'DELETE FROM dashboard_bills WHERE ' . $where);
}

# 5. Users with portfolios, but no Photosynthesis settings (which email needs).
$result = mysqli_query(
    $db,
    'SELECT DISTINCT p.user_id
    FROM dashboard_portfolios p
    JOIN users ON users.id = p.user_id
    LEFT JOIN dashboard_user_data d ON d.user_id = p.user_id
    WHERE d.user_id IS NULL'
);
$without = array_column(mysqli_fetch_all($result, MYSQLI_ASSOC), 'user_id');
echo count($without) . " user(s) with portfolios but no Photosynthesis settings\n";
if ($apply) {
    foreach ($without as $user_id) {
        Portfolio::ensure_user_data((int) $user_id);
    }
}

echo $apply ? "Done.\n" : "Nothing was changed.\n";
