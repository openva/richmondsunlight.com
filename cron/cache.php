<?php

/**
 * Preemptively cache data
 * 
 * Loads data into Memcached preemptively.
 *
 * @usage	Must be invoked from within update_db.php.
 */
 
/*
 * Connect to Memcached.
 */
$mc = new Memcached();
$mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);

/*
 * Cache every bill ID / number from the current session
 */
$sql = 'SELECT id, number
		FROM bills
		WHERE session_id = ' . SESSION_ID;
$result = mysql_query($sql);
if (mysql_num_rows($result) > 0)
{
	
	while ($bill = mysql_fetch_array($result))
	{

		/*
		 * Cache this bill ID and number in Memcached, setting a far-off expiry date.
		 */
		$mc->set('bill-' . $bill['number'], $bill['id'], (60 * 60 * 24 * 180) );
		
		/*
		 * Cache all data about this bill in Memcached. (We merely have to request the bill
		 * for the method to cache it).
		 */
		$bill2 = new Bill2;
		$bill2->id = $bill['id'];
		unset($bill2);
		
		
	}
	
}
