<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(240);

# INCLUDES
# Include any files or libraries that are necessary for this specific page to function.
include_once('../includes/functions.inc.php');
include_once('../includes/settings.inc.php');
include_once('../includes/text-statistics.inc.php');

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific page.
connect_to_db();

$statistics = new TextStatistics;

$sql = 'SELECT id, number, catch_line, full_text
		FROM bills
		WHERE session_id=14 AND full_text IS NOT NULL';
$result = mysql_query($sql);
while ($bill = mysql_fetch_array($result))
{
	echo $statistics->flesch_kincaid_grade_level($bill['full_text']).',';
}

?>
