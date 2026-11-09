<?php

/**
 * Provides helper methods for establishing database connections.
 */
class Database
{
    /**
     * @var PDO|mysqli|null Active database connection.
     */
    public $db;

    /**
     * Create a PDO-based MySQL connection (or reuse an existing one).
     *
     * @return PDO|false Active PDO connection, or false if the connection fails.
     */
    public function connect()
    {

        /*
         * If we already have a database connection, reuse it.
         */
        if (isset($GLOBALS['db_pdo'])) {
            return $GLOBALS['db_pdo'];
        }

        /*
         * If we already have a database connection, reuse it.
         */
        if (isset($GLOBALS['db']) && $GLOBALS['db'] instanceof pdo) {
            return $GLOBALS['db'];
        }

        /*
         * Connect with persistent connection and optimized settings
         */
        $options = [
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];
        try {
            $this->db = new PDO(PDO_DSN, PDO_USERNAME, PDO_PASSWORD, $options);
        } catch (PDOException $e) {
            $this->db = false;
        }

        if ($this->db !== false) {
            $GLOBALS['db_pdo'] = $this->db;
            return $this->db;
        }

        return self::connection_failed();
    }

    /**
     * Create a MySQLi connection (or reuse an existing one).
     *
     * @return mysqli|false Active MySQLi connection, or false if the connection fails.
     */
    public function connect_mysqli()
    {

        /*
         * If we already have a database connection, reuse it.
         */
        if (isset($GLOBALS['db']) && $GLOBALS['db'] instanceof mysqli) {
            return $GLOBALS['db'];
        }

        $previous_reporting = mysqli_report(MYSQLI_REPORT_OFF);
        try {
            $this->db = mysqli_connect(PDO_SERVER, PDO_USERNAME, PDO_PASSWORD);
        } catch (mysqli_sql_exception $e) {
            $this->db = false;
        } finally {
            mysqli_report($previous_reporting);
        }

        /*
         * If the connection succeeded.
         */
        if ($this->db !== false) {
            mysqli_select_db($this->db, MYSQL_DATABASE);
            mysqli_query($this->db, 'SET NAMES "utf8"');
            $GLOBALS['db'] = $this->db;
            return $this->db;
        }

        return self::connection_failed();
    }

    /**
     * Handle a failure to connect. The site sends the browser to an error page, but the API (whose
     * clients want JSON, not a page) and command-line scripts are left to handle it themselves.
     *
     * @return false For the API or the command line. Otherwise, it doesn't return.
     */
    private static function connection_failed()
    {
        if (PHP_SAPI === 'cli' || self::is_api_request()) {
            return false;
        }

        header('Location: ' . SITE_BASE_URL . '/site-down/', true, 302);
        exit;
    }

    /**
     * Whether this is a request to the API, judging by the host it was made to.
     */
    private static function is_api_request(): bool
    {
        $host = mb_strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            return false;
        }

        $api_host = defined('API_URL') ? mb_strtolower((string) parse_url((string) API_URL, PHP_URL_HOST)) : '';

        return $host === 'api.richmondsunlight.com' || ($api_host !== '' && $host === $api_host);
    }
}
