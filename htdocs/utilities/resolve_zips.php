<html>
<head>
</head>
<body>

<?php
	
	# INCLUDES
	# Include any files or libraries that are necessary for this specific
	# page to function.
	include_once('../includes/functions.inc.php');
	include_once('../includes/settings.inc.php');
	
	# DECLARATIVE FUNCTIONS
	# Run those functions that are necessary prior to loading this specific
	# page.
	connect_to_db();
	
	$sql = 'SELECT id, zip
			FROM users
			WHERE zip IS NOT NULL AND latitude IS NULL AND longitude IS NULL
			ORDER BY date_created DESC
			LIMIT 50';
	$result = mysql_query($sql);
	while ($user = mysql_fetch_array($result))
	{
		$location = new Location;
		$location->zip = $user['zip'];
		$coordinates = $location->get_coordinates();
		if ($coordinates !== false)
		{
			$sql = 'UPDATE users
					SET latitude='.$coordinates['latitude'].', longitude='.$coordinates['longitude'].', city="'.$coordinates['city'].'",
					state="'.$coordinates['statecode'].'"
					WHERE id='.$user['id'];
			mysql_query($sql);
		}
	}

?>
</body>
</html>