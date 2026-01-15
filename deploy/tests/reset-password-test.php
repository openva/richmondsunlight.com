<?php

// Validate that reset-password rotates the private hash and updates the password.

$htdocs_root = realpath(__DIR__ . '/../../htdocs');
chdir($htdocs_root);

require_once $htdocs_root . '/includes/settings.inc.php';
require_once $htdocs_root . '/includes/class.Database.php';
require_once $htdocs_root . '/includes/class.Page.php';
require_once $htdocs_root . '/includes/class.User.php';
require_once $htdocs_root . '/includes/functions.inc.php';

$autoload_path = $htdocs_root . '/vendor/autoload.php';
$created_autoload = false;
if (!file_exists($autoload_path)) {
    $vendor_dir = dirname($autoload_path);
    if (!is_dir($vendor_dir)) {
        mkdir($vendor_dir, 0775, true);
    }
    file_put_contents($autoload_path, "<?php\n");
    $created_autoload = true;
}
if ($created_autoload) {
    register_shutdown_function(function () use ($autoload_path) {
        if (file_exists($autoload_path)) {
            unlink($autoload_path);
        }
    });
}

$db = new Database();
$db->connect_mysqli();

$failures = [];

function rp_check($condition, $message)
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

$user_id = 90001;
$seed_hash = 'testuser';
$new_password = 'newpass123';

$result = mysqli_query($GLOBALS['db'], 'SELECT password, private_hash FROM users WHERE id=' . $user_id);
if ($result === false || mysqli_num_rows($result) === 0) {
    rp_check(false, 'Test user 90001 missing from users table');
} else {
    $original = mysqli_fetch_assoc($result);
    mysqli_query(
        $GLOBALS['db'],
        'UPDATE users SET password=MD5("password123"), private_hash="' . $seed_hash . '" WHERE id=' . $user_id
    );

    $_GET = [];
    $_POST = [
        'reset_password' => '1',
        'hash' => $seed_hash,
        'password' => $new_password,
        'password_2' => $new_password,
    ];
    $_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';
    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';

    ob_start();
    ob_start();
    include $htdocs_root . '/reset-password.php';
    ob_get_clean();

    $result = mysqli_query($GLOBALS['db'], 'SELECT password, private_hash FROM users WHERE id=' . $user_id);
    $updated = mysqli_fetch_assoc($result);

    rp_check($updated['password'] === md5($new_password), 'Password should be updated by reset flow');
    rp_check($updated['private_hash'] !== $seed_hash, 'private_hash should rotate after reset');
    rp_check(
        preg_match('/^[bcdfghjklmnpqrstvxyz0123456789]{8}$/', $updated['private_hash']) === 1,
        'private_hash should be regenerated with expected format'
    );

    mysqli_query(
        $GLOBALS['db'],
        'UPDATE users SET password="' . $original['password'] . '", private_hash="'
        . $original['private_hash'] . '" WHERE id=' . $user_id
    );
}

if (!empty($failures)) {
    foreach ($failures as $failure) {
        echo "❌ {$failure}\n";
    }
    exit(1);
}

echo "Reset password tests passed.\n";
exit(0);
