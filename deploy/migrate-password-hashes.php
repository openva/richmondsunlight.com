<?php

/*
 * Replaces every remaining unsalted MD5 password hash with a salted hash of that MD5 hash (see
 * htdocs/includes/class.Password.php), so that people who haven't logged in since the switch to
 * salted hashes are protected too. They can still log in as usual, and their hash is replaced with
 * an ordinary one when they do.
 *
 * Run it once, from the site's root directory:
 *
 *     php deploy/migrate-password-hashes.php
 *
 * BEFORE RUNNING:
 * 1. Run deploy/migrate-password-hashes.sql, which widens users.password to fit these hashes.
 * 2. Deploy the code that checks salted hashes, and make sure that logging in works. Once this has
 *    run, older code can't check these people's passwords, so the code can't be rolled back.
 *
 * It can be stopped and run again safely: it only changes hashes that are still bare MD5 hashes.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/../htdocs/includes/settings.inc.php';
require __DIR__ . '/../htdocs/includes/class.Database.php';
require __DIR__ . '/../htdocs/includes/class.Password.php';

$database = new Database();
$db = $database->connect_mysqli();
if ($db === false) {
    fwrite(STDERR, "Could not connect to the database.\n");
    exit(1);
}

# Make sure that the new hashes will fit, rather than having them cut off, which would lock people
# out of their accounts.
$sql = 'SELECT CHARACTER_MAXIMUM_LENGTH
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "users" AND COLUMN_NAME = "password"';
$width = (int) mysqli_fetch_row(mysqli_query($db, $sql))[0];
if ($width < 255) {
    fwrite(STDERR, "users.password is only $width characters wide. Run deploy/migrate-password-hashes.sql first.\n");
    exit(1);
}

$updated = 0;
$batch = 500;
do {
    $result = mysqli_query(
        $db,
        'SELECT id, password
        FROM users
        WHERE password REGEXP "^[0-9a-fA-F]{32}$"
        LIMIT ' . $batch
    );
    $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);

    $updated_before = $updated;
    foreach ($rows as $row) {
        # Only change the hash if it hasn't changed since we read it (e.g., by the person logging in).
        mysqli_execute_query(
            $db,
            'UPDATE users SET password = ? WHERE id = ? AND password = ?',
            [Password::wrap($row['password']), $row['id'], $row['password']]
        );
        $updated += mysqli_affected_rows($db);
    }

    echo "Updated $updated so far.\n";

    # If nothing in a whole batch could be updated, trying again would only loop forever.
    if ($updated === $updated_before && count($rows) > 0) {
        fwrite(STDERR, "Could not update any of a batch of hashes: " . mysqli_error($db) . "\n");
        break;
    }
} while (count($rows) === $batch);

$result = mysqli_query($db, 'SELECT COUNT(*) FROM users WHERE password REGEXP "^[0-9a-fA-F]{32}$"');
$remaining = (int) mysqli_fetch_row($result)[0];
echo "Done. Updated $updated hashes; $remaining MD5 hashes remain.\n";
exit($remaining === 0 ? 0 : 1);
