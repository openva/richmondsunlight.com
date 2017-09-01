<?php

/**
 * Generate bulk JSON for indexing by Elasticsearch.
 */

/*
 * Create our indexing directory, if it doesn't exist.
 */
$dir_name = 'search_index';
if (file_exists($dir_name) === FALSE)
{
	if (!mkdir($dir_name, 0755))
	{
		$log->put('Could not create directory to index bills.', 7);
		die();
	}
}
chdir($dir_name);

/*
 * Get a list of what's already been exported.
 */
$files = scandir('.');

/*
 * Assemble the SQL query.
 */
$sql = 'SELECT bills.id, sessions.year, bills.number, bills.catch_line, bills.summary,
		bills.full_text, bills.interestingness,
		(SELECT GROUP_CONCAT(tag) FROM tags WHERE bill_id=bills.id) AS tags
		FROM bills
		LEFT JOIN sessions
			ON bills.session_id = sessions.id
		WHERE bills.session_id = ' . SESSION_ID;

/*
 * Iterate through the results.
 */
$sth = $dbh->prepare($sql);
$sth->execute();
while ($bill = $sth->fetchObject())
{

	/*
	 * Clean everything up for export.
	 */
	$bill->summary = str_replace("\r", ' ', $bill->summary);
	$bill->summary = str_replace("\n", ' ', $bill->summary);
	$bill->full_text = str_replace("\r", ' ', $bill->full_text);
	$bill->full_text = str_replace("\n", ' ', $bill->full_text);
	$bill->summary = stripslashes($bill->summary);
	$bill->full_text = stripslashes($bill->full_text);
	$bill->catch_line = stripslashes($bill->catch_line);
	$bill->summary = strip_tags($bill->summary);
	$bill->full_text = strip_tags($bill->full_text);
	$bill->catch_line = strip_tags($bill->catch_line);
	$tmp = explode(',', $bill->tags);
	$bill->tags = $tmp;

	/*
	 * Set up the JSON preamble header, as instructinos for ElasticSearch.
	 */
	$header = array();
	$header['index'] = array();
	$header['index']['_index'] = 'rs';
	$header['index']['_type'] = 'bills';
	$header['index']['_id'] = $bill->id;

	/*
	 * Convert to JSON.
	 */
	$json = json_encode($bill);

	/*
	 * Create a single file for each session.
	 */
	$filename = $bill->year . '.json';

	$file_contents = json_encode($header) . "\n" . $json . "\n";
	if (!file_put_contents($filename, $file_contents, FILE_APPEND))
	{
		$log->put($bill->number . ' (' .$bill->year . ') could not be exported as JSON for indexing.', 3);
	}

}
