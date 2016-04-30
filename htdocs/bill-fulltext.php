<?php

###
# Bills' Full Text
# 
# PURPOSE
# List the full text of individual bills.
# 
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once('includes/functions.inc.php');

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
connect_to_db();

# LOCALIZE AND CLEAN UP VARIABLES
$year = mysql_escape_string($_REQUEST['year']);
$bill = mysql_escape_string($_REQUEST['bill']);

# RETRIEVE THE BILL INFO FROM THE DATABASE

# Get the bill's content from the API.
# We append a query string, containing the current time, to avoid getting a cached copy.
$json_url = 'http://api.richmondsunlight.com/1.1/bill/' . $year . '/' . $bill . '.json?' . time();
$json = get_content($json_url);
if ($json === FALSE)
{
	header("Status: 404 Not Found\n\r");
	include('404.php');
	exit();
}
$bill = json_decode($json);

# Cast this bill as an array, rather than an object, in which the array is wrapped as a result of
# being stored in JSON.
$bill = (array) $bill;

if (
	strpos($bill['number'], 'hr') !== FALSE
	|| 
	strpos($bill['number'], 'hjr') !== FALSE
	|| 
	strpos($bill['number'], 'sr') !== FALSE
	|| 
	strpos($bill['number'], 'sjr') !== FALSE
)
{
	$bill['type'] = 'resolution';
}
else
{
	$bill['type'] = 'bill';
}

/*
 * Retrieve from Virginia Decoded all defined terms that apply to the text that this bill
 * proposes to amend (if, indeed, it is amending the Code).
 */
$bill_text = new Bill2;
$bill_text->bill_id = $bill['id'];
if ($bill_text->get_terms() === TRUE)
{
	$html_head = $bill_text->javascript;
	$term_pcres = $bill_text->term_pcres;
}

# Retrieve every version of this bill's text.
$sql = 'SELECT number, date_introduced, text
		FROM bills_full_text
		WHERE bill_id = '.$bill['id'].' AND bills_full_text.text IS NOT NULL
		ORDER BY date_introduced DESC';
$result = mysql_query($sql);
if (mysql_num_rows($result) > 0)
{

	while ($version = mysql_fetch_array($result, MYSQL_ASSOC))
	{
	
		$version = array_map('stripslashes', $version);
	
		# The HTML for amended versions of bills is beastly. Clean it up.
		$version['text'] = str_replace('<center><b><br><center><b>', '<center><b>', $version['text']);
		
		# Replace every instance of a URL for a section of the state code with the URL
		# for Virginia Decoded.
		$version['text'] = preg_replace('/"http:\/\/leg1.state.va.us\/cgi-bin\/legp504\.exe\?000\+cod\+([0-9A-Z\.:-]+)"/',
			'"http://vacode.org/$1/" class="code"', $version['text']);
		
		# Convert the <i> tags to <em> tags in the head of the bill, so that we can pretty
		# up the bill text without affecting the header text.  Those tags should be found
		# within the first 20 lines of the bill's text.
		$version['text'] = explode("\r", $version['text']);
		for ($i=0; $i<19; $i++)
		{
			$version['text'][$i] = str_replace('<i>', '<em>', $version['text'][$i]);
			$version['text'][$i] = str_replace('</i>', '</em>', $version['text'][$i]);
			if ($i < count($version['text']))
			{
				break;
			}
		}
		
		# All subsequent <i> tags should become <ins> tags, and <s> tags should become
		# <del> tags.
		for ($i=20; $i<count($version['text']); $i++)
		{
			$version['text'][$i] = str_replace('<i>', '<ins>', $version['text'][$i]);
			$version['text'][$i] = str_replace('</i>', '</ins>', $version['text'][$i]);
			$version['text'][$i] = str_replace('<s>', '<del>', $version['text'][$i]);
			$version['text'][$i] = str_replace('</s>', '</del>', $version['text'][$i]);
		}
		$version['text'] = implode("\r", $version['text']);
		
		# If we have a list of terms (in regular expression form), then wrap every use of
		# that term with <span class="dictionary"></span>.
		if (is_array($term_pcres))
		{
			$version['text'] = preg_replace_callback($term_pcres, 'replace_terms', $version['text']);	
		}

		# Every set of centered hyphens should become an HR.
		$version['text'] = str_replace('<center>----------</center>', '<hr>', $version['text']);
		
		# Save all of this to an array.
		$versions[] = $version;
		
	}
	
}

# PAGE METADATA
$page_title = strtoupper($bill['number']).': '.$bill['catch_line'];
$site_section = 'bills';

# PAGE SIDEBAR
$page_sidebar = '
	<div class="box">
		<h3>Explanation</h3>
		<p>For a plain English description of this bill, comments, voting, tagging, etc.,
		<a href="/bill/'.$bill['year'].'/'.$bill['number'].'/">return to the main page for
		'.strtoupper($bill['number']).'</a>.</p>
		
		<p>This is the actual text of the bill—the legislation itself. Generally this is
		amending existing law, proposing the addition or removal of words from laws that are
		already on the books, but sometimes it’s proposing an entirely new law.</p>';

if ($bill['type'] != 'resolution')
{
	$page_sidebar .= '
		<p>Words that are <span style="background-color: #ff0;">highlighted in yellow</span> are
		proposed additions to the existing law, and words that are <s style="color:
		#c00;">crossed out in red</s> are proposed to be removed from the existing law.</p>';
}

$page_sidebar .= '
		<p>The numbers with the § symbol before them are references to existing laws, and
		if you click on them they’ll take you to that section of the Code of Virginia.</p>
	</div>';

# PAGE CONTENT
$page_body = '
<div class="full-text tabs">
	<ul class="tabs">';
	
# Iterate through to create the tabs.
foreach ($versions as $version)
{
	$page_body .= '<li><a href="#'.$version['number'].'">'.strtoupper($version['number']).'</a></li>';
}
$page_body .= '
	</ul>';

foreach ($versions as $version)
{
	$page_body .= '
		<div id="' . $version['number'] . '"" class="bill-text">
			<p style="clear: left;">' . $version['text'] . '</p>
		</div>';
}

$page_body .= '
</div>
<style>
	.bill-text {
		font-family: georgia, "times new roman", times, serif;
		font-size: 16px;
		line-height: 24px;
	}
	.bill-text hr {
		width: 33%;
		border: 0;
    	height: 1px;
    	background-image: linear-gradient(to right, rgba(0, 0, 0, 0), rgba(0, 0, 0, 0.75), rgba(0, 0, 0, 0));
	}
</style>';

# OUTPUT THE PAGE
$page = new Page;
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->html_head = $html_head;
$page->process();
