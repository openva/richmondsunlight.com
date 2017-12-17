<?php

class Poll
{
	
	/*
	 * Retrieve the results for a given poll.
	 */	
	function get_results()
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
		$this->results = $mc->get('poll-' . $bill['id']);

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
						WHERE bill_id = ' . $bill['id'] . '
						AND vote = "y") AS yes
					FROM polls
					WHERE bill_id= ' . $bill['id'];
			$result = mysql_query($sql);
			if (mysql_num_rows($result) == 0)
			{
				return FALSE;
			}
			
			$this->results = mysql_fetch_array($result);
			$mc->set( 'poll-' . $bill['id'], serialize($this->results), (60 * 60 * 24) );
			return TRUE;
			
		}

	} // end get_results()

}

