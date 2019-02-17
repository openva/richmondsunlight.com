<?php

###
# Bills
#
# PURPOSE
# Individual bills introduced into the GA.
#
###

# Store debug information.
$debug_timing = array();
$debug_timing['start'] = microtime(TRUE);

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once 'settings.inc.php';
include_once 'simplepie.inc.php';
include_once 'vendor/autoload.php';

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database;
$database->connect_old();

# INITIALIZE SESSION
session_start();

# Grab the user data.
if (logged_in() === TRUE)
{
    $user = get_user();
}

$debug_timing['logged in'] = microtime(TRUE);

# LOCALIZE AND CLEAN UP VARIABLES
$year = mysql_escape_string($_REQUEST['year']);
$bill = mb_strtolower(mysql_escape_string($_REQUEST['bill']));

# Initialize variables.
$html_head = '';
$page_body = '';

# Get the bill's content from the API.
# We append a query string, containing the current time, to avoid getting a cached copy.
$json_url = 'https://api.richmondsunlight.com/1.1/bill/' . $year . '/' . $bill . '.json?' . time();
$json = get_content($json_url);

$debug_timing['JSON retrieved'] = microtime(TRUE);

if ($json === FALSE)
{
    header("Status: 404 Not Found\n\r");
    include '404.php';
    exit();
}

$bill = json_decode($json);

# Cast this bill as an array, rather than an object, in which the array is wrapped as a result of
# being stored in JSON.
$bill = (array) $bill;

# There's a bit of Unicode (a non-breaking space) included in summaries. Realistically, those
# all need to be stripped out at the time of import, and retroactively, and summary hashes need
# to be re-calculated. Between now and then, though, this needs to be done.
$bill['summary'] = str_replace('\u00a0', ' ', $bill['summary']);

/*
 * Retrieve from Virginia Decoded all defined terms that apply to the text that this bill proposes
 * to amend (if, indeed, it is amending the Code).
 */
$bill_text = new Bill2;
$bill_text->bill_id = $bill['id'];
if ($bill_text->get_terms() === TRUE)
{
    /* Send a bit of JavaScript to the browser, which we use in each API call. */
    $html_head .= $bill_text->javascript;
    $term_pcres = $bill_text->term_pcres;
    if (is_array($term_pcres))
    {
        $bill['summary'] = preg_replace_callback($term_pcres, 'replace_terms', $bill['summary']);
    }
}

$debug_timing['definitions retrieved'] = microtime(TRUE);

# We want to record a view count hit for this bill, but only if this is a real user, not a
# search engine. Start by defining a list of bots.
$bots = array('Googlebot', 'msnbot', 'Gigabot', 'Slurp', 'Teoma', 'ia_archiver', 'Yandex',
            'Heritrix', 'twiceler', 'bingbot', 'bot');
# Check to see if the current user agent is a known bot.
foreach ($bots as $bot)
{
    if (mb_stripos($_SERVER['HTTP_USER_AGENT'], $bot) !== FALSE)
    {
        $is_bot = TRUE;
        break;
    }
}
# Update bills_views to reflect this view, provided that this visitor hasn't been defined
# as a bot.
if (!isset($is_bot))
{
    # Increment the view counter for this bill.
    $sql = 'INSERT DELAYED INTO bills_views
			SET bill_id = ' . $bill['id'] . ', ip="' . $_SERVER['REMOTE_ADDR'] . '"';
    if (isset($user) && !empty($user['id']))
    {
        $sql .= ', user_id = ' . $user['id'];
    }
    mysql_query($sql);
}

# PAGE METADATA
$page_title = $bill['year'] . ' » ' . $bill['catch_line'] . ' (' . mb_strtoupper($bill['number']) . ')';
$site_section = 'bills';

/*
 * Facebook metadata.
 */
$html_head .= '<meta property="og:title" content="' . mb_strtoupper($bill['number']) . ': '
    . $bill['catch_line'] . '"/>
	<meta property="og:image" content="https://www.richmondsunlight.com/images/legislators/thumbnails/'
        . $bill['patron_shortname'] . '.jpg"/>
	<meta property="og:url" content="' . $bill['url'] . '"/>
	<meta property="og:type" content="website" />
	<meta property="og:site_name" content="Richmond Sunlight" />
	<meta property="og:locale" content="en_US" />';

/*
 * Twitter metadata.
 */
$html_head .= '<meta name="twitter:card" content="summary" />
	<meta property="twitter:title" content="' . mb_strtoupper($bill['number']) . ', introduced by ' . $bill['patron_name_formatted'] . '"/>
	<meta property="twitter:image" content="https://www.richmondsunlight.com/images/legislators/thumbnails/'
        . $bill['patron_shortname'] . '.jpg"/>
	<meta name="twitter:site" content="@richmond_sun" />
	<meta property="twitter:description" content="' . $bill['catch_line'] . '" />';

/*
 * Alternate representations of the data on this page.
 */
$html_head .= '
	<link rel="alternate" type="application/rss+xml" href="https://www.richmondsunlight.com/rss/bill/'
        . $bill['number'] . '/" title="RSS for ' . $bill['number'] . '" />
	<link rel="alternate" type="application/json" href="http://api.richmondsunlight.com/1.1/bill/'
        . $bill['year'] . '/' . $bill['number'] . '.json" title="JSON for ' . $bill['number'] . '" />
	<link rel="alternate" type="application/pdf" href="http://lis.virginia.gov/cgi-bin/legp604.exe?'
        . $bill['session_lis_id'] . '+ful+' . mb_strtoupper($bill['number']) . '+pdf" title="PDF of ' . $bill['number'] . '" />';

# Come up with a meta description.
if (!empty($bill['summary']))
{
    $tmp = str_replace("\n", ' ', $bill['summary']);
    $tmp = strip_tags($tmp);
    $tmp = str_replace($bill['catch_line'], '', $tmp);
    $tmp = htmlspecialchars(trim($tmp));
    $html_head .= '<meta property="og:description" content="' . $tmp . '" />
		<meta name="description" content="' . $tmp . '" />';
}

# PAGE SIDEBAR
$page_sidebar = '';

# If the user has a type (either "free" or "paid") then they're a Photosynthesis user.
# Determine whether they're tracking the bill, show the appropriate text, and save it to
# a variable to be displayed later.
if ($bill['session_id'] == SESSION_ID)
{
    if (isset($user['type']) && !empty($user['type']))
    {

        # Find out if this bill is being tracked by this user.
        $sql = 'SELECT DISTINCT dashboard_portfolios.name, dashboard_portfolios.hash,
				dashboard_bills.id AS dashboard_bills_id, dashboard_bills.notes
				FROM dashboard_bills
					LEFT JOIN dashboard_portfolios
					ON dashboard_bills.portfolio_id = dashboard_portfolios.id
				WHERE dashboard_bills.bill_id = ' . $bill['id'] . '
				AND dashboard_portfolios.user_id= ' . $user['id'];
        $result = mysql_query($sql);

        # If this bill is being tracked, notify this user.
        if (mysql_num_rows($result) > 0)
        {
            $portfolio = mysql_fetch_array($result);
            $portfolio = array_map('stripslashes', $portfolio);
            if (count($_SESSION['portfolios'] == 1))
            {
                $ps_status = '
				<p><a href="/photosynthesis/">You are tracking this bill</a>.</p>';
            }
            else
            {
                $ps_status = '<p>You are tracking this bill in in
				<a href="/photosynthesis/#' . $portfolio['hash'] . '">' . $portfolio['name'] . '</a>.</p>';
            }
            # Set a tracked flag so we don't double-count this later.
            $tracked = TRUE;
        }

        # If this bill isn't being tracked, but user has portfolios to which this bill
        # could be added.
        elseif (isset($_SESSION['portfolios']))
        {
            $ps_status = '<form method="post" action="/photosynthesis/process-actions.php">';
            # If there's just one portfolio.
            if (count($_SESSION['portfolios'] == 1))
            {
                $ps_status .= '<input type="hidden" name="portfolio" value="' . $_SESSION['portfolios'][0]['hash'] . '" />';
            }

            # Or, if there's multiple portfolios.
            else
            {
                $ps_status .= '<label for="portfolio-selector">Select a Portfolio</label>
                <select name="portfolio" id="portfolio-selector">';
                foreach ($_SESSION['portfolios'] as $portfolio)
                {
                    $ps_status .= '<option value="' . $portfolio['hash'] . '">' . $portfolio['name'] . '</option>';
                }
                $ps_status .= '</select>';
            }
            $ps_status .= '
				<input type="hidden" name="add-bill" value="' . $bill['number'] . '" />
				<input type="submit" value="Track this Bill" />
			</form>';
        }
    }

    # Find out if this bill is being tracked by anybody at all, excluding the current user. If
    # it is, save the tracking data to a variable to be displayed below.
    $sql = 'SELECT users.name AS user_name, dashboard_user_data.organization AS organization,
			dashboard_portfolios.hash
			FROM dashboard_bills
			LEFT JOIN dashboard_portfolios
				ON dashboard_bills.portfolio_id = dashboard_portfolios.id
			LEFT JOIN users
				ON dashboard_portfolios.user_id = users.id
			LEFT JOIN dashboard_user_data
				ON dashboard_user_data.user_id = users.id
			WHERE dashboard_bills.bill_id =' . $bill['id'] . '
			AND dashboard_portfolios.public = "y"';
    if (!empty($user['id']))
    {
        $sql .= ' AND users.id != ' . $user['id'];
    }
    $sql .= ' ORDER BY RAND()';
    $result = mysql_query($sql);
    $portfolio_count = mysql_num_rows($result);

    # If we've found anything, list them.
    if ($portfolio_count > 0)
    {
        $ps_portfolios = '<p>This bill is being tracked by ';
        if ($portfolio_count == 1)
        {
            $ps_portfolios .= 'one member, ';
        }
        $i=2;
        while ($portfolio = mysql_fetch_array($result))
        {
            $portfolio = array_map('stripslashes', $portfolio);

            # Quasi-anonymize the user.
            $tmp = explode(' ', $portfolio['user_name']);
            if (count($tmp) > 1)
            {
                $portfolio['user_name'] = $tmp[0] . ' ' . $tmp[1]{0} . '.';
            }
            else
            {
                $portfolio['user_name'] = $tmp[0];
            }

            $ps_portfolios .= '<a href="/photosynthesis/' . $portfolio['hash'] . '/">' .
                (!empty($portfolio['organization']) ? $portfolio['organization'] : $portfolio['user_name']) . '</a>';
            if ($i < $portfolio_count)
            {
                $ps_portfolios .= ', ';
            }
            elseif ($i == $portfolio_count)
            {
                $ps_portfolios .= ' and ';
            }
            $i++;
        }
        if (mb_substr($ps_portfolios, -5) != '.</a>')
        {
            $ps_portfolios .= '.';
        }
        $ps_portfolios .= '</p>';
    }


    # If we have Photosynthesis status or portfolio data, display it in the sidebar.
    if (isset($ps_status) || isset($ps_portfolios))
    {
        $page_sidebar .= '
		<div class="box">
			<h3>Photosynthesis</h3>';
        if (isset($ps_portfolios))
        {
            $page_sidebar .= $ps_portfolios;
        }
        if (isset($ps_status))
        {
            $page_sidebar .= $ps_status;
        }
        $page_sidebar .= '
		</div>';
    }
}

$debug_timing['portfolio data retrieved'] = microtime(TRUE);

# Instantiate our poll functionality.
$poll = new Poll;
$poll->bill_id = $bill['id'];

$page_sidebar .= '<div class="box">';

# Display the poll voting form, but only if this user hasn't voted on this bill and
# this bill is from the current session.
if (($bill['session_id'] == SESSION_ID) && ($poll->has_voted() === FALSE))
{
    $page_sidebar .= '
		<h3>Cast Your Vote</h3>
		<p>Do you think this bill should become law?</p>
		<form method="post" action="/process-polls.php">
			<input type="radio" name="poll[vote]" value="y" />Yes<br />
			<input type="radio" name="poll[vote]" value="n" />No<br />
			<div style="display: none;"><input type="radio" name="poll[vote]" value="x" />I’m a Spammer<br /></div>
			<input type="hidden" name="poll[bill_id]" value="' . $bill['id'] . '">
			<input type="hidden" name="poll[return_to]" value="' . $_SERVER['REQUEST_URI'] . '" />
			<input type="submit" name="submit" value="Vote"><br />
			<p><a id="show-poll-results" style="cursor: pointer;">View Results</a></p>
		</form>';
}
else
{
    $has_voted = 'yes';
    $page_sidebar .= '
	<h3>Poll Results</h3>';
}

# Get poll results.
if ($poll->get_results() !== FALSE)
{
    $debug_timing['poll results retrieved'] = microtime(TRUE);

    $page_sidebar .= '<div id="poll-results"';
    if (!isset($has_voted))
    {
        $page_sidebar .= ' style="display: none;">';
    }
    else
    {
        $page_sidebar .= '>';
    }

    if ($poll->results['total'] > 0)
    {

        # Do the math to determine the percentage for each.
        $poll->results['no'] = round((($poll->results['total'] - $poll->results['yes']) / $poll->results['total']) * 100);
        $poll->results['yes'] = round(($poll->results['yes'] / $poll->results['total']) * 100);

        # Establish the label text for the graph.
        $poll->results['no_text'] = urlencode('no ' . $poll->results['no'] . '%');
        $poll->results['yes_text'] = urlencode('yes ' . $poll->results['yes'] . '%');

        # Assemble the URL for, and display, the chart for the voting percentage.
        $page_sidebar .= '<img src="'
            . '//chart.googleapis.com/chart?chs=215x115&amp;cht=p&amp;chd=t:'
            . $poll->results['yes'] . ',' . $poll->results['no']
            . '&amp;chl=' . (($poll->results['yes']) ? $poll->results['yes_text'] : '')
            . ((isset($poll->results['yes'], $poll->results['no'])) ? '|' : '') .
            (($poll->results['no']) ? $poll->results['no_text'] : '')
            . '&amp;chf=bg,s,f4eee5&amp;chts=333333,9" />
			<p>' . $poll->results['total'] . ' vote' . ($poll->results['total'] > 1 ? 's' : '') . '</p>';
    }
    else
    {
        if ($bill['session_id'] == SESSION_ID)
        {
            $page_sidebar .= '<p>No Richmond Sunlight visitors have voted on this bill yet.</p>';
        }
        else
        {
            $page_sidebar .= '<p>No Richmond Sunlight visitors voted on this bill while voting was open.</p>';
        }
    }
}
$page_sidebar .= '</div></div>';

# Tags
$page_sidebar .= '
	<div class="box">
        <h3>Tags</h3>
        <ul class="tags" id="tags_list">';

if (isset($bill['tags']) && (count($bill['tags']) > 0))
{
    foreach ($bill['tags'] as $tag_id => $tag)
    {

        # We're saving this list for use below, in the list of related bills.
        $tags[] = $tag['tag'];
        $page_sidebar .= '<li><a href="/bills/tags/' . urlencode($tag) . '/">' . $tag . '</a>';
        if (isset($user) && ($user['trusted'] == 'y'))
        {
            $page_sidebar .= ' [<a href="/process-tags.php?delete=' . $tag_id . '&amp;bill_id='
                . $bill['id'] . '">x</a>]';
        }
        $page_sidebar .= '</li>';
    }

    $page_sidebar .= '</ul>';
}
else
{
    $page_sidebar .= '</ul><p id="tag_admonition"><em>Hey! This bill has no tags! Why not add some
    so that other people can find it?</em></p>';
}


# Provide a much longer maxlength for the tag input field for trusted users than for
# the general public.
if (isset($user) && ($user['trusted'] == 'y'))
{
    $maxlength = '200';
}
else
{
    $maxlength = '40';
}
# Allow people to add tags.
$html_head .= '
		<script src="/js/vendor/jquery-tags-input/dist/jquery.tagsinput.min.js"></script>
		<link rel="stylesheet" href="/js/vendor/jquery-tags-input/dist/jquery.tagsinput.min.css"/>';
$page_sidebar .= '
			<form method="post" action="/process-tags.php">
				<div class="ui-widget">
					<input type="text" id="tags" name="tags[tags]" size="25" maxlength="' . $maxlength . '" required />
				</div>
				<input type="hidden" name="tags[bill_id]" value="' . $bill['id'] . '" id="bill_id" />
				<input type="hidden" name="tags[return_to]" value="' . $_SERVER['REQUEST_URI'] . '" />
				<input type="submit" name="submit" value="Save" id="tags_submit" />';

$page_sidebar .=
<<<EOD
    <script>
        $(document).ready(function() {
            $("#tags_submit").click(function( event ) {

                // Stop the form from submitting normally.
                event.preventDefault();

                // Get the form values.
                var tags = $("#tags").val(),
                    bill_id = $("#bill_id").val();

                var posting = $.post( "/process-tags-ajax.php", { tags: tags, bill_id: bill_id } );

                // If the posting was successful.
                posting.done(function( data ) {

                    // Clear out the tags input field
                    $("#tags").importTags('');

                    // Append the tags to the list
                    var tagList = tags.split(',')
                    tagList.forEach(function(tag) {
                        $( "#tags_list" ).append('<li><a href="/bills/tags/' + encodeURIComponent(tag) + '">' + tag + '</a></li>');
                        $( "#tag_admonition" ).remove();
                    });

                    // Return the tagsinput field to normal size.
                    $("#tags_tagsinput").height("14px");
                    $("#tags_tagsinput").width("80%");

                });

                // If the posting failed.
                posting.fail(function( data ) {

                    var response = $.parseJSON( data );

                });

            });
        });
    </script>
EOD;

$page_sidebar .= '
				<p>Separate each tag with a comma: <em>crime, capital murder, jury</em>.</p>
			</form>
			<script>
				$( document ).ready(function() {

					$("#tags").tagsInput({
						autocomplete_url: "' . API_URL . '1.1/tag-suggest/",
						width: "80%",
						height: "10px",
						minChars: "3",
						defaultText: ""
					});

					$("#tags_tagsinput").focusin(function() {
						$("#tags_tagsinput").height(100);
						$("#tags_tagsinput").width("95%");
					});

				});
			</script>
			<style>
				.ui-autocomplete-loading {
				    background: white url("/images/wait.gif") right center no-repeat;
				}
				.ui-autocomplete {
					background-color: white;
					font-size: 11px;
					font-weight: normal;
				}
				.ui-autocomplete li {
					text-align: left;
				}
				.ui-autocomplete a {
					font-weight: normal;
				}
			</style>
		</div>';

# Provide options to view the full bill text, etc.
$page_sidebar .= '
	<div class="box">
		<h3>More Information</h3>
		<ul>';
$page_sidebar .= '
			<li><a href="http://lis.virginia.gov/cgi-bin/legp604.exe?' . $bill['session_lis_id'] . '+ful+' . mb_strtoupper($bill['number']) . '+pdf">View as PDF</a></li>';
$page_sidebar .= '
			<li><a href="http://lis.virginia.gov/cgi-bin/legp604.exe?' . $bill['session_lis_id'] . '+sum+' . mb_strtoupper($bill['number']) . '">View on the Legislature’s Site</a></li>
			<li><a href="' . API_URL . '1.1/bill/' . $bill['year'] . '/' . $bill['number'] . '.json">View as JSON</a></li>';

if (!empty($bill['impact_statement_id']))
{
    $page_sidebar .= '
			<li><a href="https://lis.virginia.gov/cgi-bin/legp604.exe?' . $bill['session_lis_id'] . '+oth+' . mb_strtoupper($bill['number']) . 'F' . $bill['impact_statement_id'] . '+PDF">Fiscal Impact Statement</a></li>';
}

$page_sidebar .= '</ul></div>';

# Only display this DIV if we actually have some data.
if (isset($bill['related']) && ($bill['related'] > 0))
{
    $page_sidebar .= '
		<div class="box">
			<h3>Related Bills</h3>
			<ul>';
    foreach ($bill['related'] as $related_bill)
    {
        $related_bill = (array) $related_bill;
        $page_sidebar .= '
			<li><a href="/bill/' . $related_bill['year'] . '/' . $related_bill['number']
            . '/" class="balloon">' . mb_strtoupper($related_bill['number']) . balloon($related_bill, 'bill')
            . '</a>: ' . $related_bill['catch_line'] . '</li>';
    }
    $page_sidebar .= '
			</ul>
		</div>';
}

# PAGE CONTENT
$page_body .= '

<div id="bill-metadata">
<h2>Introduced By</h2>
<p><a href="/legislator/' . $bill['patron_shortname'] . '/" class="legislator">' .
    $bill['patron_name_formatted'] . '</a>';

# If this bill has any copatrons, list them.
if (isset($bill['copatron']) && (count($bill['copatron']) > 0))
{

    # If there are a small number (5 or less) display them right on the screen.
    if (count($bill['copatron']) <= 5)
    {
        $page_body .= ' with support from co-patron';
        if (count($bill['copatron']) > 1)
        {
            $page_body .= 's';
        }
        $page_body .= ' ';
        $i=1;
        foreach ($bill['copatron'] as $copatron)
        {
            $copatron = (array) $copatron;
            $page_body .= '<a href="/legislator/' . $copatron['shortname'] . '/" class="legislator">' . $copatron['name_formatted'] . '</a>';

            if ($i < count($bill['copatron']))
            {
                if ($i == (count($bill['copatron']) - 1))
                {
                    $page_body .= ', and ';
                }
                else
                {
                    $page_body .= ', ';
                }
            }
            $i++;
        }
    }
    # If there are more than five copatrons, we want to provide a link to reveal them,
    # rather than displaying them all on-screen.
    else
    {

        # Calculate the average partisanship rating.
        $partisanship = array();
        foreach ($bill['copatron'] as $copatron)
        {
            $partisanship[] = $copatron->partisanship;
        }
        $partisanship = array_sum($partisanship) / count($partisanship);

        # Display the partisanship ratings of the copatrons.
        $page_body .= ' with support from ' . count($bill['copatron']) . ' copatrons, whose
			average partisan position is:</p>
			<div id="partisanship-graph">
				<div style="width: ' . $partisanship . '%;"></div>
			</div>
			<p style="clear: left;">Those copatrons are ';

        foreach ($bill['copatron'] as $copatron)
        {
            $page_body .= '<a href="/legislator/' . $copatron->shortname . '/" class="legislator">'
                . $copatron->name_formatted . '</a>, ';
        }
        $page_body = mb_substr($page_body, 0, -2);
    }
}
$page_body .= '</p>';



# The status table.
if (isset($bill['status_history']))
{
    $bill['history'] = '';

    foreach ($bill['status_history'] as $status)
    {

        # Cast this object as an array.
        $status = (array) $status;

        # Include a link to look at the vote, but only if there was a vote associated with this
        # action (obviously), and only if the LIS vote ID is 8 characters or less. That second
        # requirement is because longer IDs are for subcommittee votes, and subcommittee votes
        # aren't included in the vote data that's syndicated from the legislature in vote.csv.
        if (!empty($status['lis_vote_id']) && ($status['vote_count'] > 0)
            && mb_strlen($status['lis_vote_id'] <= 8))
        {
            $tmp = $status['status'] . ' (<a href="/bill/' . $bill['year'] . '/'
                . mb_strtolower($bill['number']) . '/' . mb_strtolower($status['lis_vote_id']) . '/">'
                . 'see vote tally</a>)';
            $status['status'] = $tmp;
        }
        $bill['history'] = '<tr><td>' . $status['date'] . '</td><td>' . $status['status'] . '</td></tr>' . $bill['history'];

        # Build up an array of status translations to use to create our checkbox list.
        if (!empty($status['translation']))
        {
            $statuses[] = $status['translation'];
        }

        # If the bill's status is blank, according to the bills table, take this
        # opportunity to give it a status, using the first non-blank status
        # translation available.
        if (empty($bill['status']) && !empty($status['translation']))
        {
            $bill['status'] = $status['translation'];
        }
    }

    $bill['history'] = '<table>
                            <thead><th>Date</th><th>Action</th></thead>
                            <tbody>' . $bill['history'] . '</tbody>
                        </table>';

    $passed = '<div class="checkbox passed">✓</div>';
    $failed = '<div class="checkbox failed">✗</div>';
    $neither = '<div class="checkbox">☐</div>';
    $page_body .= '
	<div id="bill-progress">
		<h2>Progress</h2>
		<table id="bill-progress">
			<tr class="alt">
				<td>' . $passed . '</td>
				<td class="text">Introduced</td>
			</tr>
			<tr>
				<td>';
    if ((in_array('failed committee', $statuses)) || (in_array('failed subcommittee', $statuses)))
    {
        $page_body .= $failed;
    }
    elseif (in_array('passed committee', $statuses))
    {
        $page_body .= $passed;
    }
    else
    {
        $page_body .= $neither;
    }
    $page_body .= '</td>
				<td class="text">Passed Committee</td>
			</tr>
			<tr class="alt">
				<td>';
    if (mb_substr($bill['number'], 0, 2) != 'SR')
    {
        if (in_array('passed house', $statuses))
        {
            $page_body .= $passed;
        }
        elseif (in_array('failed house', $statuses))
        {
            $page_body .= $failed;
        }
        else
        {
            $page_body .= $neither;
        }
        $page_body .= '</td>
					<td class="text">Passed House</td>
				</tr>
				<tr>
					<td>';
    }
    if (mb_substr($bill['number'], 0, 2) != 'HR')
    {
        if (in_array('passed senate', $statuses))
        {
            $page_body .= $passed;
        }
        elseif (in_array('failed senate', $statuses))
        {
            $page_body .= $failed;
        }
        else
        {
            $page_body .= $neither;
        }
        $page_body .= '</td>
					<td class="text">Passed Senate</td>
				</tr>
				<tr class="alt">
					<td>';
    }
    if (((mb_substr($bill['number'], 0, 2) != 'HR') && (mb_substr($bill['number'], 0, 2) != 'SR'))
        && (mb_substr($bill['number'], 0, 2) != 'SJ') && (mb_substr($bill['number'], 0, 2) != 'HJ'))
    {
        if (in_array('vetoed by governor', $statuses))
        {
            $page_body .= $failed;
        }
        elseif (in_array('signed by governor', $statuses))
        {
            $page_body .= $passed;
        }
        else
        {
            $page_body .= $neither;
        }
        $page_body .= '</td>
					<td class="text">Signed by Governor</td>
				</tr>
				<tr>
					<td>';
        if (in_array('enacted', $statuses))
        {
            $page_body .= $passed;
        }
        elseif (in_array('vetoed by governor', $statuses))
        {
            $page_body .= $failed;
        }
        else
        {
            $page_body .= $neither;
        }
        $page_body .= '</td>
					<td class="text">Became Law</td>
				</tr>';
    }
    $page_body .= '
		</table>
		</div>';
}


# BILL SUMMARY
$page_body .= '<h2>Description</h2>
<p>' . $bill['summary'];

# Display a list of the sections of the Code of Virginia affected by this bill.
$code_sections = bill_sections($bill['id']);

if (($code_sections !== FALSE) && (count($code_sections) > 0))
{
    $page_body .= ' <em>Amends ';
    foreach ($code_sections as $section)
    {
        $page_body .= '<a href="' . $section['url'] . '" class="code">§&nbsp;'
            . $section['section_number'] . '</a>';
        if (next($code_sections) != $section)
        {
            $page_body .= ', ';
        }
    }
    $page_body .= ' of the <a href="https://vacode.org/">Code of Virginia</a>.</em>';
}

# Show a link to the view full text, but only if we *have* the full text.
if ($bill['word_count'] > 0)
{
    $page_body .= ' <a href="/bill/' . $bill['year'] . '/' . mb_strtolower($bill['number']) . '/fulltext/">Read&nbsp;the&nbsp;Bill&nbsp;»</a></p>';
}

# If we have any notes about this bill.
if (!empty($bill['notes']))
{
    $page_body .= '
		<div id="notes">
		<h2>Notes</h2>
		' . $bill['notes'] . '
		</div>';
}

# If this bill is no longer alive.
if (!empty($bill['outcome']))
{
    $page_body .= '
		<h2>Outcome</h2>';
    if ($bill['outcome'] == 'failed')
    {
        $page_body .= '
		<div class="bill-outcome failed">Bill Has Failed</div>';
    }
    elseif ($bill['outcome'] == 'passed')
    {
        $page_body .= '
		<div class="bill-outcome passed">Bill Has Passed</div>';
    }
}

# If this bill remains alive.
else
{
    $page_body .= '<h2>Status</h2>
	<p>';

    # If we have any status data, use that as the date of the last action. If not, just use
    # today's date, since that's better than nothing.
    if (!empty($bill['status_detail_date']))
    {
        $page_body .= $bill['status_detail_date'] . ': ';
    }
    else
    {
        $page_body .= date('m/d/Y') . ': ';
    }

    # If this bill has become part of another bill, then that's its final status.
    if (!empty($bill['incorporated_into']))
    {
        $page_body .= 'Merged into <a href="/bill/' . $bill['year'] . '/'
            . $bill['incorporated_into'] . '/">' . mb_strtoupper($bill['incorporated_into']) . '</a>';
    }

    # If it's assigned to a committee, but the committee has not yet acted on it, then we can
    # say that it's going to be voted on by that committee soon.
    elseif (!empty($bill['committee']) && !in_array('passed senate', $statuses) && !in_array('passed house', $statuses)
        && !in_array('passed committee', $statuses) && !in_array('failed committee', $statuses)
        && !in_array('failed subcommittee', $statuses) && !in_array('incorporated', $statuses))
    {
        $page_body .=
            'Awaiting a Vote in the <a href="/committee/' . $bill['committee_chamber'] . '/' . $bill['committee_shortname'] . '/">'
            . $bill['committee'] . '</a> Committee';
    }
    else
    {
        if (count($statuses) > 0)
        {
            $page_body .= explain_status(reset($statuses));
        }
        else
        {
            $page_body .= 'Introduced';
        }
    }
}

/*
 * When a bill is brand-new, there's no history data. Only show the history section if we've got
 * history data.
 */
if (!empty($bill['history']))
{
    $page_body .= '
	<h2 id="history">History</h2>
	<div id="status-history">
		' . $bill['history'] . '
	</div>';
}

/*
 * Upcoming hearings.
 */
$sql = 'SELECT DATE_FORMAT(dockets.date, "%m/%d/%Y") AS date, committees.name AS committee,
		committees.chamber, committees.meeting_time, committees_parent.name AS parent_committee,
		committees_parent.shortname AS parent_shortname, committees.shortname
		FROM dockets
		LEFT JOIN committees
			ON dockets.committee_id = committees.id
		LEFT JOIN committees AS committees_parent
			ON committees.parent_id = committees_parent.id
		WHERE dockets.bill_id=' . $bill['id'] . ' AND dockets.date > now()
		LIMIT 1';

$result = mysql_query($sql);
if (mysql_num_rows($result) > 0)
{
    $docket = mysql_fetch_array($result);
    $docket = array_map('stripslashes', $docket);

    $page_body .= '
		<div class="docket">
			<h2>Hearing Scheduled</h2>
			This bill is scheduled to be heard in the ';
    if (!empty($docket['parent_committee']))
    {
        $page_body .= '<a href="/committee/' . $docket['chamber'] . '/' . $docket['parent_shortname'] . '/">' . ucfirst($docket['chamber'])
                . ' ' . $docket['parent_committee'] . '</a>&rsquo;s ' . $docket['committee'] . ' subcommittee';
    }
    else
    {
        $page_body .= '<a href="/committee/' . $docket['chamber'] . '/' . $docket['shortname'] . '/">' . ucfirst($docket['chamber']) .
            ' ' . $docket['committee'] . '</a> committee';
    }
    $page_body .= ' on ' . $docket['date'] . '. It meets on ' . $docket['meeting_time'] . '.
		</div>';
}

$debug_timing['hearings retrieved'] = microtime(TRUE);

/*
 * If places are mentioned in this bill, map them.
 */
if (isset($bill['places']) && (count($bill['places']) > 0))
{
    $page_body .= '
		<h2>Map</h2>
		<p>This bill mentions';

    foreach ($bill['places'] as $place)
    {
        $place = (array) $place;
        $page_body .= ' ' . $place['name'] . ',';
    }
    $page_body = rtrim($page_body, ',');
    $page_body .= '.</p>

	<div id="map" style="width: 100%; height: 190px;">
		<img src="//maps.googleapis.com/maps/api/staticmap?center=38.1%2C-79.8&amp;zoom=6&amp;size=420x190' .
        '&amp;maptype=terrain&amp;sensor=false';
    foreach ($bill['places'] as $place)
    {
        $place = (array) $place;
        $page_body .= '&amp;markers=' . $place['latitude'] . ',' . $place['longitude'];
    }
    $page_body .= '" /></div>';
}

if (($bill['video'] !== FALSE) && (count($bill['video']) > 0))
{

    /*
     * Generate a text transcript of these clips.
     */
    $transcript = array();
    foreach ($bill['video'] as $video)
    {
        $sql = 'SELECT representatives.name_formatted AS speaker, video_transcript.text
				FROM video_transcript
				LEFT JOIN representatives
					ON video_transcript.legislator_id = representatives.id
				WHERE video_transcript.file_id=' . $video->file_id . '
					AND time_start >= " ' . seconds_to_time($video->start) . ' "
					AND time_end <= " ' . seconds_to_time($video->end) . ' "
				ORDER BY video_transcript.time_start ASC';
        $result = mysql_query($sql);
        if (mysql_num_rows($result) > 0)
        {
            $transcript[$video->file_id] = array();

            while ($line = mysql_fetch_assoc($result))
            {
                $transcript[$video->file_id][] = $line;
            }
        }
    }

    # Determine the cumulative duration of these clips.
    $duration = 0;
    foreach ($bill['video'] as $clip)
    {
        $clip = (array) $clip;
        $duration = $duration + $clip['duration'];
    }
    $duration = str_replace(' ago', '', seconds_to_units($duration));

    /*
     * Add the Flowplayer code.
     */
    $html_head .= '
		<script src="/js/flowplayer-6.0.5/flowplayer.min.js"></script>
		<link rel="stylesheet" href="/js/flowplayer-6.0.5/skin/minimalist.css">';

    # Start a new DIV for this legislator's highlights reel.
    $page_body .= '
	<div id="video">
		<h2>Video</h2>

		<p>This bill was discussed on the floor of the General Assembly. Below is all of the
		video that we have of that discussion, ' . count($bill['video']) . ' clip'
        . ((count($bill['video']) > 1) ? 's' : '') . ' in all, totaling ' . $duration . '.</p>

		<div class="flowplayer" id="player">
			<a class="fp-prev">←</a>
			<a class="fp-next">→</a>
		</div>
		<div id="playlist">
		</div>

		<script>
			/* Create the playlist. */
			var allVideos = [';
    foreach ($bill['video'] as $num => $clip)
    {
        $clip = (array) $clip;
        $page_body .= '
			{
				sources: [{
					type: "video/mp4",
					src: "' . $clip['path'] . '",
					date: "' . $clip['date'] . '",
					start: ' . $clip['start'] . ',
					duration: ' . $clip['duration'] . ',
					cuepoints: [' . ($clip['start'] + $clip['duration']) . ' ]
				}]
			},';
    }
    $page_body .= "];

			flowplayer(function (api, root) {
					api.on('ready', function() {
						firstplayer.seek(api.video.start);
					});
				});

			/* Load the playlist into Flowplayer. */
			flowplayer('#player', {
				playlist: allVideos
			});

			/* When we hit the cuepoint, advance to the next video. */
			var firstplayer = flowplayer('#player');
			firstplayer.on('cuepoint', function(e, api, cuepoint) {
				if (firstplayer.video.is_last == false) {
					firstplayer.next();
				}
				else {
					firstplayer.pause();
				}
			});

			/* Create the playlist. */
			/*var playlistHTML = '';
			$.each(allVideos, function(i,data) {
				playlistHTML += '<li><a href=javascript:play(' + i + ')>' + data['sources'][0]['date'] + ' ' + data['sources'][0]['duration'] + ' seconds</a></li>';
			});
			$('#playlist').html(playlistHTML);*/

		</script>";

    if (count($transcript) > 0)
    {
        $page_body .= '<h3>Transcript</h3>
			<div style="height: 15em; overflow: scroll;">
			<p>This is a transcript of the video clips in which this bill is discussed.</p>';
        $prior_speaker = '';

        foreach ($transcript as $file)
        {
            foreach ($file as $line)
            {
                if ($prior_speaker != $line['speaker'])
                {
                    $page_body .=  '<br><br>';
                    if (!empty($line['speaker']))
                    {
                        $page_body .=  '<strong>' . $line['speaker'] . ':</strong> ';
                    }
                    else
                    {
                        $page_body .=  '<strong>[Unknown]:</strong> ';
                    }
                    $prior_speaker = $line['speaker'];
                }
                $page_body .=  $line['text'] . ' ';
            }
            $page_body .= '<hr>';
        }
        $page_body .= '</div>';
    }

    $page_body .= '</div>';
}


# DUPLICATES OF THIS BILL

if (isset($bill['duplicates']))
{
    $page_body .= '
	<div id="identical">
		<a name="identical"></a>
		<h2>Duplicate Bills</h2>
		<p>The following bills are identical to this one: ';

    # Iterate through the duplicates and display them as a list.
    $i=0;
    foreach ($bill['duplicates'] as $duplicate)
    {
        $duplicate = (array) $duplicate;

        $page_body .= '<a href="/bill/' . $duplicate['year'] . '/' . $duplicate['number'] . '/">'
            . mb_strtoupper($duplicate['number']) . '</a>';
        if ((count($bill['duplicates']) - 2) == $i)
        {
            $page_body .= ' and ';
        }
        elseif (count($bill['duplicates']) > ($i+1))
        {
            $page_body .= ', ';
        }
        $i++;
    }

    $page_body .= '.</p>
	</div>';
}

# Close the DIV that encloses bill metadata.
$page_body .= '
	</div>';

# Get our own blog entries about this bill.
/*
// COMMENTED OUT UNTIL VCU STARTS DOING THIS AGAIN -- IT'S TOO SLOW
$mc = new Memcached();
$mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);
$news = $mc->get('blog-entries-' . $bill['id']);
if ($mc->getResultCode() > 0)
{

    unset($news);

    $blog_api_url = 'https://www.richmondsunlight.com/blog/wp-json/posts?filter[tag]=' . urlencode($bill['number']);
    $blog_json = file_get_contents($blog_api_url);
    if ($blog_json !== FALSE)
    {

        $blog_entries = json_decode($blog_json);
        if ( ($blog_entries !== FALSE) && (count($blog_entries) > 0) )
        {

            # Set a counter to allow us to limit the output to the last five blog entries.
            $i=0;
            $news = '';

            foreach ($blog_entries as $blog_entry)
            {

                # If this blog entry is from the same year as this bill.
                if ( $bill['year'] != date('Y', strtotime($blog_entry->date)) )
                {
                    continue;
                }

                $news .= '
                    <h3><a href="' . $blog_entry->link . '">' . $blog_entry->title . '</a></h3>'
                    . '<p>' . date('F j, Y', strtotime($blog_entry->date) ) . '<br />'
                    . strip_tags($blog_entry->excerpt) . '</p>';
                $i++;
                if ($i==5)
                {
                    break;
                }

            }

        }

    }

    # Cache these blog entries for an hour (even if there are none).
    $mc->set('blog-entries-' . $bill['id'], $news, (60 * 60) );

}

if (!empty($news))
{
    $page_body .= '
    <div id="news">
        <h2>In the News</h2>
        ' . $news . '
    </div>';
}

$debug_timing['blog entries retrieved'] = microtime(TRUE);*/

# BILL COMMENTS
$page_body .= '
    <div id="comments">
    <div id="comment-list">';

/*
 * Get any comments on this bill.
 */
$mc = new Memcached();
$mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);
$comments = $mc->get('comments-' . $bill['id']);
if ($mc->getResultCode() != 0)
{
    $comm = new Comments;
    $comm->bill_id = $bill['id'];
    $comments = $comm->get();
    $mc->set('comments-' . $bill['id'], serialize($comments), (60 * 60 * 24 * 7));
}
else
{
    $comments = unserialize($comments);
}

$debug_timing['comments retrieved'] = microtime(TRUE);

/*
 * Make sure that we a) have comments, and b) that the variable doesn't equal FALSE. (We actually
 * store FALSE in Memcached, so that we can cache the fact that there are no comments.)
 */
if (isset($comments) && is_array($comments))
{
    $page_body .= '<h2>Comments</h2>';
    $i=1;

    # Our two comments array keys are timestamps. Resort them and then reindex them.
    ksort($comments);
    $comments = array_values($comments);

    foreach ($comments as $comment)
    {

        # Provide an anchor tag for this comment.
        $page_body .= '<a name="comment-' . $i . '"></a>';

        # Start off the DIV that contains every comment.
        $page_body .= '<div class="comment';

        # If this comment was posted by the legislator who introduced it, apply a special style and
        # reformat the name and URL.
        if ($comment['representative_id'] === $bill['chief_patron_id'])
        {
            $page_body .= ' legislator';

            # Replace the provided URL with the legislator's Richmond Sunlight page.
            $comment['url'] = 'https://www.richmondsunlight.com/legislator/'
                . $bill['patron_shortname'] . '/';

            # Replace the provided name with the legislator's proper name.
            $comment['name'] = $bill['patron_prefix'] . ' ' . pivot($bill['patron']) . ' '
                . $bill['patron_suffix'];

            # Display the legislator's photograph.
            $badge = '<img src="/images/legislators/thumbnails/'
                . $bill['patron_shortname'] . '.jpg" width="50" class="photo" />';
        }

        # If this comment is an editor's pick, apply a special style and add a note.
        elseif ($comment['editors_pick'] == 'y')
        {
            $page_body .= ' editors-pick';
            $badge = '<div class="notice">Editor’s Pick</div>';
        }

        $page_body .= '">';

        # If we've got a badge to apply to this DIV (a photo, a label, whatever), now's the time.
        if (isset($badge))
        {
            $page_body .= "\r\t\t\t" . $badge;
            # We don't want to retain this for subsequent comments.
            unset($badge);
        }

        # If this is a Photosynthesis comment, rather than a comment directly on the bill.
        if (isset($comment['type']) && ($comment['type'] == 'photosynthesis'))
        {
            $page_body .= '
			<a href="/photosynthesis/' . $comment['hash'] . '/"><cite>' . $comment['name'] . '</cite><strong>, tracking this bill in Photosynthesis</a>, notes</strong>:<br />';
        }

        # Otherwise, credit it as a comment.
        else
        {
            $page_body .= '
			<cite>' . (!empty($comment['url']) ? '<a href="' . $comment['url'] . '">' : '')
            . $comment['name'] . (!empty($comment['url']) ? '</a>' : '')
            . '</cite> <strong>writes</strong>:<br />';
        }

        # Include the comment itself, followed by the post time and the permalink.
        $page_body .= $comment['comment'] . '
			<div class="metadata">
				<span class="date">Posted ' . seconds_to_units(time() - $comment['timestamp']) . '.</span>
				<a href="#comment-' . $i . '" title="Permalink to this comment" class="permalink">#</a>
			</div>
		</div>';
        $i++;
    }
}

/*
 * End #comment-list
 */
$page_body .= '</div>';


# Only let the user add a new comment if this bill is from the current session and, if
# the session is over, if the bill has passed.
if (($bill['session_id'] == SESSION_ID))
{
    $page_body .= '
	<h2>Post a Public Comment About this Bill</h2>
	<form method="post" action="/process-comments.php" id="comment-form">
		<input type="text" size="30" maxlength="50" name="comment[expiration_date]" id="expiration_date" value="' . $user['name'] . '" required /> <label for="expiration_date"><strong>Name</strong> <small>required</small></label><br />
		<input type="email" size="30" maxlength="50" name="comment[zip]" id="zip" value="' . $user['email'] . '" required /> <label for="zip"><strong>Mail</strong> <small>won’t be published, required</small></label><br />
		<input type="url" size="30" maxlength="50" name="comment[age]" id="age" value="' . $user['url'] . '" /> <label for="age"><strong>Website</strong></label> <small>if you have one</small><br />
		<div style="display: none;"><input type="text" size="2" maxlength="2" name="comment[state]" id="state" /> <label for="state">Leave this field empty</label><br /></div>
		<textarea rows="16" cols="60" name="comment[comment]" id="comment" required></textarea><br />
		<small>(Limited HTML is OK: &lt;a&gt;, &lt;em&gt;, &lt;strong&gt;, &lt;s&gt)</small><br />';

    # Create a new instance of the comments-subscription class
    $subscription = new CommentSubscription;
    # Give it the user's ID and the bill's ID.
    $subscription->user_id = $user['id'];
    $subscription->bill_id = $bill['id'];

    # Get the user's subscription status. (Either false or, if true, we get a hash of the
    # subscription ID.
    $subscription_status = $subscription->is_subscribed();

    # If the person isn't already subscribed to this bill's comments.
    if ($subscription_status === false)
    {
        $page_body .= '<input type="checkbox" value="y" name="comment[subscribe]"'
        . ' id="subscribe" /> <label for="subscribe"><strong>Subscribe</strong> <small>get future'
        . ' comments by e-mail</small></label><br />';
    }

    # Otherwise, if the person is subscribed to this bill's comments.
    else
    {
        $page_body .= '<strong>You are subscribed</strong> to be e-mailed future comments
			to this bill. <a href="/unsubscribe/' . $subscription_status . '/">Unsubscribe?</a><br />';
    }

    $page_body .= '
            <input type="hidden" name="comment[bill_id]" id="bill_id" value="' . $bill['id'] . '" />
            <input type="submit" name="submit" id="comment-submit" value="Submit" />
            <div id="comment-error" style="background-color: pink; border: 3px solid black; padding: 1em; display: none; margin: 2em 0;"></div>
        </form>
        <script>
            $(document).ready(function() {
                $("#comment-submit").click(function( event ) {

                    // Stop the form from submitting normally.
                    event.preventDefault();

                    // Get the form values.
                    var expiration_date = $("#expiration_date").val(),
                        zip = $("#zip").val(),
                        age = $("#age").val(),
                        comment = $("#comment").val(),
                        bill_id = $("#bill_id").val(),
                        subscribe = $("#subscribe").val();

                    var posting = $.post( "/process-comments-ajax.php", { expiration_date: expiration_date, zip: zip, age: age, bill_id: bill_id, subscribe: subscribe, comment: comment } );

                    // If the posting was successful.
                    posting.done(function( data ) {

                        var response = $.parseJSON( data );

                        // Append the comment to the list
                        $( "#comment-list" ).append(
                            "<a name=comment-new></a><div class=comment id=newcomment><cite><a href=" + age + ">" + expiration_date + "</a></cite> <strong>writes</strong>:<br /><p>" + comment + "</p><div class=metadata><span class=date>Posted 1 second ago.</span></div></div>"
                        );

                        // Clear out the comment field
                        $("#comment").val("");

                        // Scroll to the new comment
                        var commentAnchor = $("a[name=\'comment-new\']");
                        $("html,body").animate({scrollTop: commentAnchor.offset().top},"slow");

                        // Flash and fade the comment
                        $("#newcomment").fadeIn(200).fadeOut(200).fadeIn(200).fadeOut(200).fadeIn(200);

                    });

                    // If the posting failed.
                    posting.fail(function( data ) {

                        var response = $.parseJSON( data );

                        // Display the error in the error field.
                        $( "#comment-error" ).empty().append( response.error );
                        $( "#comment-error" ).show();

                    });

                });
            });
        </script>';
}

$page_body .= '
	</div>';

# OUTPUT THE PAGE
$page = new Page;
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->html_head = $html_head;
$page->process();

$debug_timing['contents sent for output'] = microtime(TRUE);

/*
 * Show me debugging information.
 */
if (isset($user) && ($user['id'] == '5059'))
{
    echo '<div style="background-color: white; width: 200px; border: 1px solid #000; padding: 5px;
			font-size: .75em; text-align: left; opacity: .8; position: absolute; right: 0; top: 0;">
		<table>';
    $start_time = reset($debug_timing);
    $cumulative_time = 0;

    foreach ($debug_timing as $description => $time)
    {
        if ($description == 'start')
        {
            continue;
        }

        $time = $time - $start_time;
        echo '
			<tr>
				<td>' . $description . '</td>
				<td>' . round(($time - $cumulative_time), 3) . '</td>
			</tr>';
        $cumulative_time = $time;
    }

    echo '<tr><td>Total</td><td>' . round(microtime(TRUE) - $start_time, 3) . '</td></tr>';

    echo '</table></div>';
}
