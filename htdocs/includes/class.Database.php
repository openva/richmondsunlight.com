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
        if (isset($GLOBALS['db']))
        {
            return $GLOBALS['db'];
        }

        /*
         * Connect
         */
        $this->db = new PDO(PDO_DSN, PDO_USERNAME, PDO_PASSWORD);

        if ($this->db !== FALSE)
        {
            $GLOBALS['db'] = $this->db;
            return $this->db;
        }

        /*
         * If this is isn't a request to the API, send the browser to an error page.
         */
        if (mb_stristr($_GET['REQUEST_URI'], 'api.richmondsunlight.com') === FALSE)
        {
            header('Location: https://www.richmondsunlight.com/site-down/');
            exit;
        }

        /*
         * If this is a request to the API, just return false.
         */
        else
        {
            return FALSE;
        }
    }

    /*
     * Connect via MySQLi
     */
    public function connect_old()
    {

        /*
         * If we already have a database connection, reuse it.
         */
        if (isset($GLOBALS['db_old']))
        {
            return $GLOBALS['db_old'];
        }

        $this->db = mysqli_connect(PDO_SERVER, PDO_USERNAME, PDO_PASSWORD);

        /*
         * If the connection succeeded.
         */
        if ($this->db !== FALSE)
        {
            mysqli_select_db($this->db, MYSQL_DATABASE);
            mysqli_query($this->db, 'SET NAMES "utf8"');
            $GLOBALS['db'] = $this->db;
            return TRUE;
        }

        /*
         * If this is isn't a request to the API, send the browser to an error page.
         */
        if (mb_stristr($_GET['REQUEST_URI'], 'api.richmondsunlight.com') === FALSE)
        {
            header('Location: https://www.richmondsunlight.com/site-down/');
            exit;
        }

        /*
         * If this is a request to the API, just return false.
         */
        else
        {
            return FALSE;
        }
    }
}
