<?php

// Load Composer autoloader (also loads functions.inc.php via the "files" autoload entry)
require_once __DIR__ . '/../htdocs/includes/vendor/autoload.php';

// The Database class checks $_GET['REQUEST_URI'] on connection failure and calls exit() if it
// doesn't look like an API request. Setting a fake API URL here causes it to return false
// instead, letting test classes handle missing DB gracefully via markTestSkipped().
$_GET['REQUEST_URI'] = 'api.richmondsunlight.com/test';

// Register an autoloader for project classes (class.ClassName.php convention)
spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/../htdocs/includes/class.' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Load application settings (constants, timezone, locale, etc.).
// Suppress E_WARNING during this include: settings.inc.php calls ini_set() for
// the Memcached session handler, which isn't available outside the web/Docker context.
$_bootstrap_prev_reporting = error_reporting(E_ERROR | E_PARSE);
require_once __DIR__ . '/../htdocs/includes/settings.inc.php';
error_reporting($_bootstrap_prev_reporting);
unset($_bootstrap_prev_reporting);
