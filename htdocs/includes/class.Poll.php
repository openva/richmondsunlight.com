<?php

class Poll
{

    /*
     * Determine whether the current user has voted on this poll before.
     */
    public function has_voted()
    {
        if (empty($this->bill_id))
        {
            return FALSE;
        }

        if (logged_in() === FALSE)
        {
            return FALSE;
        }

        $database = new Database;
        $database->connect_old();

        $sql = 'SELECT *
				FROM polls
				WHERE user_id=
					(SELECT id
					FROM users
					WHERE cookie_hash = "' . $_SESSION['id'] . '")
				AND bill_id=' . $this->bill_id;
        $result = mysqli_query($db, $sql);
        if (mysqli_num_rows($result) === 0)
        {
            return FALSE;
        }

        return TRUE;
    } // end has_voted()

    /*
     * Retrieve the results for a given poll.
     */
    public function get_results()
    {
        if (empty($this->bill_id))
        {
            return FALSE;
        }

        /*
         * Connect to Memcached to retrieve these poll results.
         */
        $mc = new Memcached();
        $mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);
        $this->results = $mc->get('poll-' . $this->bill_id);

        /*
         * If we have poll results in the cache.
         */
        if ($this->results != FALSE)
        {
            $this->results = unserialize($poll);
            return TRUE;
        }

        /*
         * Else if there are no poll results in the cache.
         */
        else
        {
            $database = new Database;
            $database->connect_old();

            $sql = 'SELECT COUNT(*) AS total,
						(SELECT COUNT(*)
						FROM polls
						WHERE bill_id = ' . $this->bill_id . '
						AND vote = "y") AS yes
					FROM polls
					WHERE bill_id= ' . $this->bill_id;
            $result = mysqli_query($db, $sql);
            if (mysqli_num_rows($result) == 0)
            {
                return FALSE;
            }

            $this->results = mysqli_fetch_array($result);
            $mc->set('poll-' . $this->bill_id, serialize($this->results), (60 * 60 * 24));
            return TRUE;
        }
    } // end get_results()
}
