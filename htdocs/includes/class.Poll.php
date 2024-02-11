<?php

class Poll
{
    /*
     * Determine whether the current user has voted on this poll before.
     */
    public function has_voted()
    {
        if (empty($this->bill_id)) {
            return false;
        }

        if (logged_in() === false) {
            return false;
        }

        $database = new Database();
        $database->connect_mysqli();

        $sql = 'SELECT *
				FROM polls
				WHERE user_id=
					(SELECT id
					FROM users
					WHERE cookie_hash = "' . $_SESSION['id'] . '")
				AND bill_id=' . $this->bill_id;
        $result = mysqli_query($GLOBALS['db'], $sql);
        if (mysqli_num_rows($result) === 0) {
            return false;
        }

        return true;
    } // end has_voted()

    /*
     * Retrieve the results for a given poll.
     */
    public function get_results()
    {

        if (empty($this->bill_id)) {
            return false;
        }

        /*
         * Connect to Memcached to retrieve these poll results.
         */
        if (MEMCACHED_SERVER != '') {
            $mc = new Memcached();
            $mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);
            $this->results = $mc->get('poll-' . $this->bill_id);

            /*
            * If we have poll results in the cache.
            */
            if ($this->results != false) {
                $this->results = unserialize($this->results);
                return true;
            }
        }

        /*
         * Else if there are no poll results in the cache.
         */
        $database = new Database();
        $database->connect_mysqli();

        $sql = 'SELECT COUNT(*) AS total,
                    (SELECT COUNT(*)
                    FROM polls
                    WHERE bill_id = ' . $this->bill_id . '
                    AND vote = "y") AS yes
                FROM polls
                WHERE bill_id= ' . $this->bill_id;
        $result = mysqli_query($GLOBALS['db'], $sql);
        if (mysqli_num_rows($result) == 0) {
            return false;
        }

        $this->results = mysqli_fetch_array($result);

        if (MEMCACHED_SERVER != '') {
            $mc->set('poll-' . $this->bill_id, serialize($this->results), (60 * 60 * 24));
        }

        return true;
    } // end get_results()
}
