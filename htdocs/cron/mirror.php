<?php

###
# Update Legislator Website Mirrors
# 
# PURPOSE
# Periodically mirrors the websites of every member of the legislature, storing archival snapshots
# of how their sites looked in years past.
#
###

error_reporting(E_ALL);
ini_set('display_errors', 1);
	
# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once('../includes/settings.inc.php');
include_once('../includes/functions.inc.php');

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
connect_to_db();

# Define the root directory for the mirrors.
$dir = $_SERVER['DOCUMENT_ROOT'] . '/mirror/';

# Create a list of every directory name contained within the master mirrors directory. This will
# include other files (ones that aren't directories, that is), but that's fine, given our
# application of this array.
$directories = scandir($dir);

# Eliminate the current and parent directories.
unset($directories[0]);
unset($directories[1]);
$directories = array_values($directories);

# Step through every directory and retrieve the name of the subdirectory with the most recent date
# (which is to say the largest value, since they're named in form "YYYYMMDD").
foreach ($directories as $directory)
{
	# If this file is a directory.
	if (is_dir($dir.$directory.'/') === true)
	{
		$subdirs = scandir($dir.$directory.'/');
		rsort($subdirs);
		$tmp[$directory] = current($subdirs);
	}
}
asort($tmp);
$directories = $tmp;
unset($tmp);

# Create a list of every currently-serving legislator from the database and store it as an array,
# using the legislator shortname as the key. We sort randomly to faciliate the below missing-site
# array comparison.
$sql = 'SELECT shortname, url
		FROM representatives
		WHERE (date_ended IS NULL OR date_ended >= now()) AND url IS NOT NULL
		ORDER BY RAND() ASC';
$result = mysql_query($sql);
while ($tmp = mysql_fetch_array($result))
{
	$legislators[$tmp{shortname}] = $tmp['url'];
}

# Compare the array of directory names to the array of known legislators, generating a list of all
# legislators of whom we have no record.
$missing = array_diff_key($legislators, $directories);

# Then compare in the opposite direction, generating a list of all directories that are for
# legislators not found in the our list -- that is, retired legislators.
$retired = array_keys(array_diff_key($directories, $legislators));

# Iterate through the list of retired legislators and remove each of their directories.
foreach ($retired as $remove)
{
	unset($directories[$remove]);
}


# If we do have missing legislators (that is, a legislator about whom we know, and who has a
# website, but we don't have a copy of their website), then we want to grab a copy of one of their
# sites. We don't want to do this every time, because it will block us from updating existing sites
# if we can't retrieve this site, so we just do it 5% of the time.
if ( (count($missing) > 0) && (rand(1,20) == 1) )
{
	$legislator = key($missing);
}

# Since there is no legislator in the database whose site we don't have a copy of, we can just use
# a random directory name at the top of the stack (that is, the oldest ones) as our legislator to be
# updated, though we iterate through to make sure it's a legislator that's still in office. Note
# that we just don't take the one on the top of the stack because if that site couldn't be retrieved
# for some reason, we'd be blocking all future updates of all sites.
else
{

	# Slice off the top five directories from the stack.
	$directories = array_slice($directories, 0, 10);
	
	# Shuffle these five directories.
	$keys = array_keys($directories);
	shuffle($keys);
	$directories = array_merge(array_flip($keys), $directories);

	# Step through the randomly ordered five directories.
	foreach ($directories as $directory => $blah)
	{
		if (isset($legislators[$directory]))
		{
			$legislator = $directory;
			break;
		}
	}
}

# Retrieve this legislator's URL from the database.
$url = $legislators[$legislator];

# Execute the actual mirroring. Allowing up to 300 seconds for this to run.
chdir($dir);
$cmd = '/vol/www/richmondsunlight.com/alarmlimit 300 wget -a log.txt --mirror --html-extension --directory-prefix='.$legislator.'/'.date('Ymd').'/ --no-host-directories --convert-links '.$url;
echo $cmd;
exec($cmd, $output);
echo '<a href="/mirror/' . $legislator . '/' . date('Ymd') . '/">Link</a>';
