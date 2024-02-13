<?php

/**
 * Database-connection methods.
 */
class Database
{
    /*
     * Create a PDO-based MySQL connection.
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
         * Connect
         */
        $this->db = new PDO(PDO_DSN, PDO_USERNAME, PDO_PASSWORD);

        if ($this->db !== false) {
            $GLOBALS['db_pdo'] = $this->db;
            return $this->db;
        }

        /*
         * If this is isn't a request to the API, send the browser to an error page.
         */
        if (mb_stristr($_GET['REQUEST_URI'], 'api.richmondsunlight.com') === false) {
            header('Location: https://' . $_SERVER['SERVER_NAME'] . '/site-down/');
            exit;
        }

        /*
         * If this is a request to the API, just return false.
         */
        else {
            return false;
        }
    }

    /*
     * Connect via MySQLi
     */
    public function connect_mysqli()
    {

        /*
         * If we already have a database connection, reuse it.
         */
        if (isset($GLOBALS['db']) && $GLOBALS['db'] instanceof mysqli) {
            return $GLOBALS['db'];
        }

        $this->db = mysqli_connect(PDO_SERVER, PDO_USERNAME, PDO_PASSWORD);

        /*
         * If the connection succeeded.
         */
        if ($this->db !== false) {
            mysqli_select_db($this->db, MYSQL_DATABASE);
            mysqli_query($this->db, 'SET NAMES "utf8"');
            $GLOBALS['db'] = $this->db;
            return $this->db;
        }

        /*
         * If this is isn't a request to the API, send the browser to an error page.
         */
        if (mb_stristr($_GET['REQUEST_URI'], 'api.richmondsunlight.com') === false) {
            header('Location: https://' . $_SERVER['SERVER_NAME'] . '/site-down/');
            exit;
        }

        /*
         * If this is a request to the API, just return false.
         */
        else {
            return false;
        }
    }

    /*
     * Connect via PHP's old-school MySQL connector
     */
    public function connect_old()
    {

        /*
         * If we already have a database connection, reuse it.
         */
        if (isset($GLOBALS['db_old'])) {
            return $GLOBALS['db_old'];
        } elseif (isset($GLOBALS['db']) && get_class($GLOBALS['db']) == 'mysql') {
            return $GLOBALS['db'];
        }

        $this->db = mysql_connect(PDO_SERVER, PDO_USERNAME, PDO_PASSWORD);

        /*
         * If the connection succeeded.
         */
        if ($this->db !== false) {
            mysql_select_db(MYSQL_DATABASE, $this->db);
            mysql_query('SET NAMES "utf8"');
            $GLOBALS['db'] = $this->db;
            return $this->db;
        }

        /*
         * If this is isn't a request to the API, send the browser to an error page.
         */
        if (mb_stristr($_GET['REQUEST_URI'], 'api.richmondsunlight.com') === false) {
            header('Location: https://' . $_SERVER['SERVER_NAME'] . '/site-down/');
            exit;
        }

        /*
         * If this is a request to the API, just return false.
         */
        else {
            return false;
        }
    }
}
