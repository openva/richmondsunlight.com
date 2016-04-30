<?php

###
# Search
# 
# PURPOSE
# Searches all bills.
#
# NOTES
# None.
#
# TODO
# None.
#
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once('includes/functions.inc.php');
include_once('includes/settings.inc.php');
include_once('includes/sphinxapi.php');

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
@connect_to_db();

# INITIALIZE SESSION
session_start();

# PAGE METADATA
$page_title = 'Search';
$site_section = 'search';

# LOCALIZE VARIABLES
$q = $_REQUEST['q'];
$year = $_REQUEST['year'];
$p = $_REQUEST['p'];

# Clean up the variables.
$per_page = 10;
if (empty($p) || !is_numeric($p))
{
	$p = 1;
}
	
# If a year isn't set, assume the current session.  Be sure to keep this as !isset,
# rather than empty, because an empty variable signifies all years, whereas *no*
# variable means a year has not been selected, and thus the current year is defaulted
# to.
if (!isset($year) || !is_numeric($p))
{
	$year = SESSION_YEAR;
}

	
# SIDEBAR
$page_sidebar = '
	
	<div class="box">
		<h3>Options</h3>
		<form method="get" action="/search/">
			<fieldset name="year">
				<legend name="year">Year</legend>
				<input type="radio" name="year" id="2006" value="2006"'.($year == '2006' ? ' checked="checked"' : '').' /><label for="2006">2006</label><br />
				<input type="radio" name="year" id="2007" value="2007"'.($year == '2007' ? ' checked="checked"' : '').' /><label for="2007">2007</label><br />
				<input type="radio" name="year" id="2008" value="2008"'.($year == '2008' ? ' checked="checked"' : '').' /><label for="2008">2008</label><br />
				<input type="radio" name="year" id="2009" value="2009"'.($year == '2009' ? ' checked="checked"' : '').' /><label for="2009">2009</label><br />
				<input type="radio" name="year" id="2010" value="2010"'.($year == '2010' ? ' checked="checked"' : '').' /><label for="2010">2010</label><br />
				<input type="radio" name="year" id="2011" value="2011"'.($year == '2011' ? ' checked="checked"' : '').' /><label for="2011">2011</label><br />
				<input type="radio" name="year" id="2012" value="2012"'.($year == '2012' ? ' checked="checked"' : '').' /><label for="2012">2012</label><br />
				<input type="radio" name="year" id="2013" value="2013"'.($year == '2013' ? ' checked="checked"' : '').' /><label for="2013">2013</label><br />
				<input type="radio" name="year" id="2014" value="2014"'.($year == '2014' ? ' checked="checked"' : '').' /><label for="2014">2014</label><br />
				<input type="radio" name="year" id="2015" value="2015"'.($year == '2015' ? ' checked="checked"' : '').' /><label for="2015">2015</label><br />
				<input type="radio" name="year" id="2016" value="2016"'.($year == '2016' ? ' checked="checked"' : '').' /><label for="2016">2016</label><br />
				<input type="radio" name="year" id="all" value=""'.($year == '' ? ' checked="checked"' : '').' /><label for="all">All</label>
			</fieldset>
			<input type="submit" name="submit" value="Go" />
		</form>
	</div>
';

# PAGE CONTENT

if (!empty($q))
{
	# Clean up the query.
	$q = trim($q);
	
	# If it's a bill, just redirect to the bill page.
	if (eregi('([hs]{1})([bjr]{1})([[:space:]]?)([0-9]+)', $q))
	{
		$q = str_replace(' ', '', $q);
		header('Location: http://www.richmondsunlight.com/bill/'.SESSION_YEAR.'/'.strtolower($q).'/');
		exit;
	}
		
	# Display the search form again.
	$page_body = @search_form(stripslashes($q));
	
	# Connect to Sphinx and issue a query.
	$sphinx = new SphinxClient();
	$sphinx->SetServer('localhost', 9312);
	$sphinx->SetLimits( (($p-1)*$per_page), $per_page);
	$sphinx->SetFieldWeights(
		array(
			'catch_line' => 50,
			'tags' => 50,
			'summary' => 30,
			'full_text' => 20,
		)
	);

	# If a year has been specified in which to search, filter against that.
	if (!empty($year))
	{
		$sphinx->SetFilter('year', array($year));
	}
	
	# Issue the query.
	$result = $sphinx->Query($q, 'bills');
	
	# If there's an error, return a warning and bail.
	if ($result === false)
	{
		$page_body .= '<p>An error occurred, so no results could be found.</p>';
	}
	# If everything is A-OK, then list the results.
	else
	{
		$page_body .= '<p>'.number_format($result['total_found']).' results found.</p>
		<div class="results">';
		
		# Iterate through the results and build up a list of IDs.
		foreach ($result['matches'] as $law_id => $details)
		{
			$ids[] = $law_id;
		}
		
		# Feed the resulting list of IDs to the function that will retrieve them.
		$bills = new Bill2;
		$bill_list = new stdClass();
		$i=0;
		$documents = array();
		
		foreach ($ids as $bills->id)
		{
			$tmp = $bills->info();
			$bill_list->$i = $tmp;
			$documents[] = strip_tags($tmp['summary']);
			$i++;
		};
		
		# Define the options that we'll use for our excerption query.
		$options = array
		(
			'before_match'		=> '<strong>',
			'after_match'		=> '</strong>',
			'chunk_separator'	=> ' .&thinsp;.&thinsp;. ',
			'limit'				=> 250,
			'around'			=> 25,
			'single_passage'	=> true,
		);
		
		# Ask Sphinx to provide us with excerpts for each of these results.
		$excerpts = $sphinx->BuildExcerpts($documents, 'bills', $q, $options);
		
		$i=0;
		foreach ($bill_list as $search_result)
		{
			$page_body .= '<h2><a href="'.$search_result['url'].'">'.$search_result['catch_line']
				.' ('.strtoupper($search_result['number']).')</a></h2>
				<p class="excerpt">'.$excerpts[$i].'</p>
				<p class="url"><a href="'
					.$search_result['url'].'">'.htmlspecialchars($search_result['url']).'</a></p>';
			$i++;
		}
		
		# List the page numbers.
		if ($result['total_found'] > $per_page)
		{
		
			$page_body .= '<ul class="paging">';
			
			for ($i=1; ($i * $per_page) <= (ceil($result['total_found']/10)*10); $i++)
			{
			
				# Assemble the URL for this page link.
				$url = '/search/?q='.urlencode($q).'&amp;p='.$i;
				if (isset($year))
				{
					$url .= '&amp;year='.urlencode($year);
				}
				if (isset($sort))
				{
					$url .= '&amp;sort='.urlencode($sort);
				}
				if (isset($per_page))
				{
					$url .= '&amp;per_page='.urlencode($per_page);
				}
				$page_body .= '<li><a href="'.$url.'"';
				if ($i == $p)
				{
					$page_body .= ' class="current"';
				}
				$page_body .= '>'.$i.'</a></li>';
			}
			
			$page_body .= '</ul>';
			
		}
		
		# Close the results DIV.
		$page_body .= '</div>';
		
	}
}

# If the page is being loaded straight.
else
{
	# Display a blank form.
	$page_body = @search_form();
}

# OUTPUT THE PAGE
$page = new Page;
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->process();

?>
