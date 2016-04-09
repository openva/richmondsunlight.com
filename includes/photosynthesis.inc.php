<?php

###
# Photosynthesis Functions
# 
# PURPOSE
# A function library for the Photosynthesis bill-tracking tools.
# 
# NOTES
# None
# 
# TODO
# None.
# 
###

# Populates a portfolio with the contents of a watch list, whether for the first time
# or to update an existing portfolio.
function populate_smart_portfolio($portfolio_id)
{
	
	# Get the watch list ID.
	$sql = 'SELECT watch_list_id AS id
			FROM dashboard_portfolios
			WHERE id = '.$portfolio_id;
	$result = mysql_query($sql);
	if (mysql_num_rows($result) == 0)
	{
		return false;
	}
	$watch_list = mysql_fetch_array($result);
	
	# Get the ID of the user who owns this portfolio.
	$sql = 'SELECT user_id AS id
			FROM dashboard_portfolios
			WHERE id = '.$portfolio_id;
	$result = mysql_query($sql);
	if (mysql_num_rows($result) == 0)
	{
		return false;
	}
	$user = mysql_fetch_array($result);
	$user_id = $user['id'];
	
	# Get the smart portfolio criteria.
	$sql = 'SELECT tag, patron_id, committee_id, keyword, status, current_chamber
			FROM dashboard_watch_lists
			WHERE id ='.$watch_list['id'];
	$result = mysql_query($sql);
	if (mysql_num_rows($result) == 0)
	{
		return false;
	}
	$portfolio = mysql_fetch_array($result);
	
	# Remove any criterion that's not being used in this smart portfolio.
	foreach ($portfolio as $key => $criterion)
	{
		if (empty($criterion)) unset($portfolio[$key]);
	}
	
	# Get all bills that meet these criteria (up to 200). Start by assembling the query.
	$sql = 'SELECT id
			FROM bills
			WHERE session_id='.SESSION_ID;
	if (isset($portfolio['patron_id']))
	{
		$sql .= ' AND chief_patron_id='.$portfolio['patron_id'];
	}
	if (isset($portfolio['committee_id']))
	{
		$sql .= ' AND last_committee_id='.$portfolio['committee_id'];
	}
	if (isset($portfolio['keyword']))
	{
		$sql .= ' AND MATCH (bills.catch_line, bills.summary, bills.full_text, bills.number, bills.notes) AGAINST ("'.$portfolio['keyword'].'")';
	}
	if (isset($portfolio['status']))
	{
		$sql .= ' AND status="'.$portfolio['status'].'"';
	}
	if (isset($portfolio['current_chamber']))
	{
		$sql .= ' AND current_chamber="'.$portfolio['current_chamber'].'"';
	}
	if (isset($portfolio['tag']))
	{
		$sql .= ' AND id IN
			(SELECT bill_id
			FROM tags
			WHERE tag="'.$portfolio['tag'].'")';
	}

	# Run the actual query;
	$result = mysql_query($sql);
	
	# We don't want to fail when no bills are found. It's totally reasonable for somebody to
	# have a smart portfoilo that starts out blank. But we do want to erase any bills in the
	# portfolio in question before we wrap up. Specifying the user ID isn't strictly necessary,
	# but it's better to be cautious.
	if (mysql_num_rows($result) == 0)
	{
		$sql = 'DELETE FROM dashboard_bills
				WHERE portfolio_id = '.$portfolio_id.' AND user_id='.$user_id;
		mysql_query($sql);
		return true;
	}
	
	# When bills *are* found, build them up into an ID listing.
	while ($bill = mysql_fetch_array($result))
	{
		$new_bills[] = $bill['id'];
	}
	
	# Generate a listing of all bills already in this portfolio.
	$sql = 'SELECT bill_id AS id
			FROM dashboard_bills
			WHERE portfolio_id = '.$portfolio_id;
	$result = mysql_query($sql);
	if (mysql_num_rows($result) > 0)
	{
		while ($bill = mysql_fetch_array($result)) $old_bills[] = $bill['id'];
	}
	
	# Sort the array to figure out which to delete (because they're no longer a match),
	# which to ignore, and which to add.
	if (isset($old_bills))
	{
		# Bills already in the database that can be left alone.
		$kept_bills = array_intersect($old_bills, $new_bills);
		
		# Bills that are in the database, but not in the latest query, which need to
		# be deleted from the database.
		foreach ($old_bills as $value) if (!in_array($value, $new_bills)) $delete_bills[] = $value;
		
		# Bills that are not in the database, but are in the latest query, which need to
		# be inserted.
		foreach ($new_bills as $value) if (!in_array($value, $old_bills)) $insert_bills[] = $value;
	}
	
	# If there are no old bills to be filtered out, just go ahead and make the new bills the
	# list of bills to insert.
	else $insert_bills = $new_bills;
	
	# If any old bills are found (bills in this smart portfolio that no longer fit our
	# criteria) then delete them.
	if (isset($delete_bills))
	{
		foreach ($delete_bills AS $bill_id)
		{
			# Delete from dashboard_bills. Though it's not necessary to specify the user ID
			# here, we do so anyhow, because it's better to be overly cautious.
			$sql = 'DELETE FROM dashboard_bills
					WHERE portfolio_id = '.$portfolio_id.' AND bill_id = '.$bill_id.'
					AND user_id='.$user_id;
			mysql_query($sql);
		}
	}
	
	# If any new bills have been found, insert them.
	if (isset($insert_bills))
	{
		foreach ($insert_bills AS $bill_id)
		{
			# Insert into dashboard_bills.
			$sql = 'INSERT INTO dashboard_bills
					SET user_id = '.$user_id.', bill_id = '.$bill_id.',
					portfolio_id = '.$portfolio_id.', date_created = now()
					ON DUPLICATE KEY UPDATE bill_id=bill_id';
			$result = mysql_query($sql);
		}
	}	
}

###
# SMART PORTFOLIO FORM
# Waldo Jaquith <waldo@jaquith.org>
# October 22, 2007
#
# PURPOSE
# Displays the form for creating or editing a smart portfolio.
#
# NOTES
# None
###
function smart_portfolio_form($form_data)
{
	# Determine where the form is to be posted.
	if (isset($form_data['id'])) $action = $_SERVER['REQUEST_URI'];
	else $action = '/photosynthesis/process-actions.php';
	
	$content = '
			<form method="post" action="'.$action.'">
				
				<fieldset id="create-smart-portfolio">
					<table>
						<tr><td><label for="name">Name</label></td></tr>
						<tr><td><input type="text" size="20" maxlength="120" name="form_data[name]" id="name" value="'.$form_data['name'].'" /></td></tr>
						<tr><td><label for="notes">Description</label></td></tr>
						<tr><td><textarea name="form_data[notes]" id="notes">'.(!empty($form_data['notes']) ? $form_data['notes'] : '').'</textarea></td></tr>
						<tr><td><label for="tag">Tag</label></td></tr>
						<tr><td><input type="text" size="10" maxlength="30" name="form_data[tag]" id="tag" value="'.$form_data['tag'].'" /><br /></td></tr>
						<tr><td><label for="patron">Patron</label></td></tr>
						<tr><td>
							<select name="form_data[patron_id]" id="patron" size="1">
								<option></option>
								<optgroup label="Delegates">
									<option value="2"'.(($form_data['patron_id'] == 2) ? ' selected="selected"' : '').'>Abbitt, Watkins (I-59)</option>
									<option value="7"'.(($form_data['patron_id'] == 7) ? ' selected="selected"' : '').'>Albo, Dave (R-42)</option>
									<option value="8"'.(($form_data['patron_id'] == 8) ? ' selected="selected"' : '').'>Alexander, Kenny (D-89)</option>
									<option value="9"'.(($form_data['patron_id'] == 9) ? ' selected="selected"' : '').'>Amundson, Kris (D-44)</option>
									<option value="10"'.(($form_data['patron_id'] == 10) ? ' selected="selected"' : '').'>Armstrong, Ward (D-10)</option>
									<option value="11"'.(($form_data['patron_id'] == 11) ? ' selected="selected"' : '').'>Athey, Clay (R-18)</option>
									<option value="12"'.(($form_data['patron_id'] == 12) ? ' selected="selected"' : '').'>BaCote, Mamye (D-95)</option>
									<option value="13"'.(($form_data['patron_id'] == 13) ? ' selected="selected"' : '').'>Barlow, William (D-64)</option>
									<option value="4"'.(($form_data['patron_id'] == 4) ? ' selected="selected"' : '').'>Bell, Rob (R-58)</option>
									<option value="314"'.(($form_data['patron_id'] == 314) ? ' selected="selected"' : '').'>Bouchard, Joe (D-83)</option>
									<option value="14"'.(($form_data['patron_id'] == 14) ? ' selected="selected"' : '').'>Bowling, Danny (D-3)</option>
									<option value="15"'.(($form_data['patron_id'] == 15) ? ' selected="selected"' : '').'>Brink, Bob (D-48)</option>
									<option value="16"'.(($form_data['patron_id'] == 16) ? ' selected="selected"' : '').'>Bulova, David (D-37)</option>
									<option value="17"'.(($form_data['patron_id'] == 17) ? ' selected="selected"' : '').'>Byron, Kathy (R-22)</option>
									<option value="19"'.(($form_data['patron_id'] == 19) ? ' selected="selected"' : '').'>Caputo, Chuck (D-67)</option>
									<option value="20"'.(($form_data['patron_id'] == 20) ? ' selected="selected"' : '').'>Carrico, Bill (R-5)</option>
									<option value="21"'.(($form_data['patron_id'] == 21) ? ' selected="selected"' : '').'>Cline, Ben (R-24)</option>
									<option value="22"'.(($form_data['patron_id'] == 22) ? ' selected="selected"' : '').'>Cole, Mark (R-88)</option>
									<option value="23"'.(($form_data['patron_id'] == 23) ? ' selected="selected"' : '').'>Cosgrove, John (R-78)</option>
									<option value="24"'.(($form_data['patron_id'] == 24) ? ' selected="selected"' : '').'>Cox, Kirk (R-66)</option>
									<option value="25"'.(($form_data['patron_id'] == 25) ? ' selected="selected"' : '').'>Crockett-Stark, Anne (R-6)</option>
									<option value="26"'.(($form_data['patron_id'] == 26) ? ' selected="selected"' : '').'>Dance, Rosalyn (D-63)</option>
									<option value="28"'.(($form_data['patron_id'] == 28) ? ' selected="selected"' : '').'>Ebbin, Adam (D-49)</option>
									<option value="29"'.(($form_data['patron_id'] == 29) ? ' selected="selected"' : '').'>Eisenberg, Al (D-47)</option>
									<option value="30"'.(($form_data['patron_id'] == 30) ? ' selected="selected"' : '').'>Englin, David (D-45)</option>
									<option value="31"'.(($form_data['patron_id'] == 31) ? ' selected="selected"' : '').'>Fralin, Bill (R-17)</option>
									<option value="32"'.(($form_data['patron_id'] == 32) ? ' selected="selected"' : '').'>Frederick, Jeff (R-52)</option>
									<option value="33"'.(($form_data['patron_id'] == 33) ? ' selected="selected"' : '').'>Gear, Tom (R-91)</option>
									<option value="34"'.(($form_data['patron_id'] == 34) ? ' selected="selected"' : '').'>Gilbert, Todd (R-15)</option>
									<option value="35"'.(($form_data['patron_id'] == 35) ? ' selected="selected"' : '').'>Griffith, Morgan (R-8)</option>
									<option value="36"'.(($form_data['patron_id'] == 36) ? ' selected="selected"' : '').'>Hall, Franklin P. (D-69)</option>
									<option value="37"'.(($form_data['patron_id'] == 37) ? ' selected="selected"' : '').'>Hamilton, Phil (R-93)</option>
									<option value="38"'.(($form_data['patron_id'] == 38) ? ' selected="selected"' : '').'>Hargrove, Frank (R-55)</option>
									<option value="39"'.(($form_data['patron_id'] == 39) ? ' selected="selected"' : '').'>Hogan, Clarke (R-60)</option>
									<option value="40"'.(($form_data['patron_id'] == 40) ? ' selected="selected"' : '').'>Howell, Algie (D-90)</option>
									<option value="41"'.(($form_data['patron_id'] == 41) ? ' selected="selected"' : '').'>Howell, Bill (R-28)</option>
									<option value="42"'.(($form_data['patron_id'] == 42) ? ' selected="selected"' : '').'>Hugo, Tim (R-40)</option>
									<option value="43"'.(($form_data['patron_id'] == 43) ? ' selected="selected"' : '').'>Hull, Bob (D-38)</option>
									<option value="45"'.(($form_data['patron_id'] == 45) ? ' selected="selected"' : '').'>Iaquinto, Sal (R-84)</option>
									<option value="46"'.(($form_data['patron_id'] == 46) ? ' selected="selected"' : '').'>Ingram, Riley (R-62)</option>
									<option value="47"'.(($form_data['patron_id'] == 47) ? ' selected="selected"' : '').'>Janis, Bill (R-56)</option>
									<option value="48"'.(($form_data['patron_id'] == 48) ? ' selected="selected"' : '').'>Joannou, Johnny (D-79)</option>
									<option value="49"'.(($form_data['patron_id'] == 49) ? ' selected="selected"' : '').'>Johnson, Joseph (D-4)</option>
									<option value="51"'.(($form_data['patron_id'] == 51) ? ' selected="selected"' : '').'>Jones, Chris (R-76)</option>
									<option value="50"'.(($form_data['patron_id'] == 50) ? ' selected="selected"' : '').'>Jones, Dwight (D-70)</option>
									<option value="52"'.(($form_data['patron_id'] == 52) ? ' selected="selected"' : '').'>Kilgore, Terry (R-1)</option>
									<option value="5"'.(($form_data['patron_id'] == 5) ? ' selected="selected"' : '').'>Landes, Steve (R-25)</option>
									<option value="53"'.(($form_data['patron_id'] == 53) ? ' selected="selected"' : '').'>Lewis, Lynwood (D-100)</option>
									<option value="54"'.(($form_data['patron_id'] == 54) ? ' selected="selected"' : '').'>Lingamfelter, Scott (R-31)</option>
									<option value="55"'.(($form_data['patron_id'] == 55) ? ' selected="selected"' : '').'>Lohr, Matt (R-26)</option>
									<option value="315"'.(($form_data['patron_id'] == 315) ? ' selected="selected"' : '').'>Loupassi, Manoil (R-68)</option>
									<option value="56"'.(($form_data['patron_id'] == 56) ? ' selected="selected"' : '').'>Marsden, Dave (D-41)</option>
									<option value="58"'.(($form_data['patron_id'] == 58) ? ' selected="selected"' : '').'>Marshall, Bob (R-13)</option>
									<option value="57"'.(($form_data['patron_id'] == 57) ? ' selected="selected"' : '').'>Marshall, Danny (R-14)</option>
									<option value="316"'.(($form_data['patron_id'] == 316) ? ' selected="selected"' : '').'>Massie, Jimmie (R-72)</option>
									<option value="317"'.(($form_data['patron_id'] == 317) ? ' selected="selected"' : '').'>Mathieson, Bobby (D-21)</option>
									<option value="59"'.(($form_data['patron_id'] == 59) ? ' selected="selected"' : '').'>May, Joe (R-33)</option>
									<option value="60"'.(($form_data['patron_id'] == 60) ? ' selected="selected"' : '').'>McClellan, Jennifer (D-71)</option>
									<option value="63"'.(($form_data['patron_id'] == 63) ? ' selected="selected"' : '').'>Melvin, Ken (D-80)</option>
									<option value="318"'.(($form_data['patron_id'] == 318) ? ' selected="selected"' : '').'>Merricks, Don (R-16)</option>
									<option value="306"'.(($form_data['patron_id'] == 306) ? ' selected="selected"' : '').'>Miller, Jackson (R-50)</option>
									<option value="64"'.(($form_data['patron_id'] == 64) ? ' selected="selected"' : '').'>Miller, Paula (D-87)</option>
									<option value="65"'.(($form_data['patron_id'] == 65) ? ' selected="selected"' : '').'>Moran, Brian (D-46)</option>
									<option value="66"'.(($form_data['patron_id'] == 66) ? ' selected="selected"' : '').'>Morgan, Harvey (R-98)</option>
									<option value="319"'.(($form_data['patron_id'] == 319) ? ' selected="selected"' : '').'>Morrissey, Joe (D-74)</option>
									<option value="312"'.(($form_data['patron_id'] == 312) ? ' selected="selected"' : '').'>Nichols, Paul (D-51)</option>
									<option value="67"'.(($form_data['patron_id'] == 67) ? ' selected="selected"' : '').'>Nixon, Sam (R-27)</option>
									<option value="68"'.(($form_data['patron_id'] == 68) ? ' selected="selected"' : '').'>Nutter, Dave (R-7)</option>
									<option value="70"'.(($form_data['patron_id'] == 70) ? ' selected="selected"' : '').'>Oder, Glenn (R-94)</option>
									<option value="71"'.(($form_data['patron_id'] == 71) ? ' selected="selected"' : '').'>Orrock, Bobby (R-54)</option>
									<option value="69"'.(($form_data['patron_id'] == 69) ? ' selected="selected"' : '').'>O\'Bannon, John (R-73)</option>
									<option value="72"'.(($form_data['patron_id'] == 72) ? ' selected="selected"' : '').'>Peace, Chris (R-97)</option>
									<option value="73"'.(($form_data['patron_id'] == 73) ? ' selected="selected"' : '').'>Phillips, Bud (D-2)</option>
									<option value="74"'.(($form_data['patron_id'] == 74) ? ' selected="selected"' : '').'>Plum, Ken (D-36)</option>
									<option value="320"'.(($form_data['patron_id'] == 320) ? ' selected="selected"' : '').'>Pogge, Brenda (R-96)</option>
									<option value="321"'.(($form_data['patron_id'] == 321) ? ' selected="selected"' : '').'>Poindexter, Charles (R-9)</option>
									<option value="75"'.(($form_data['patron_id'] == 75) ? ' selected="selected"' : '').'>Poisson, David (D-32)</option>
									<option value="76"'.(($form_data['patron_id'] == 76) ? ' selected="selected"' : '').'>Purkey, Harry (R-82)</option>
									<option value="77"'.(($form_data['patron_id'] == 77) ? ' selected="selected"' : '').'>Putney, Lacey (I-19)</option>
									<option value="80"'.(($form_data['patron_id'] == 80) ? ' selected="selected"' : '').'>Rust, Tom (R-86)</option>
									<option value="81"'.(($form_data['patron_id'] == 81) ? ' selected="selected"' : '').'>Saxman, Chris (R-20)</option>
									<option value="82"'.(($form_data['patron_id'] == 82) ? ' selected="selected"' : '').'>Scott, Ed (R-30)</option>
									<option value="83"'.(($form_data['patron_id'] == 83) ? ' selected="selected"' : '').'>Scott, Jim (D-53)</option>
									<option value="84"'.(($form_data['patron_id'] == 84) ? ' selected="selected"' : '').'>Shannon, Steve (D-35)</option>
									<option value="85"'.(($form_data['patron_id'] == 85) ? ' selected="selected"' : '').'>Sherwood, Beverly (R-29)</option>
									<option value="86"'.(($form_data['patron_id'] == 86) ? ' selected="selected"' : '').'>Shuler, Jim (D-12)</option>
									<option value="87"'.(($form_data['patron_id'] == 87) ? ' selected="selected"' : '').'>Sickles, Mark (D-43)</option>
									<option value="88"'.(($form_data['patron_id'] == 88) ? ' selected="selected"' : '').'>Spruill, Lionell (D-77)</option>
									<option value="89"'.(($form_data['patron_id'] == 89) ? ' selected="selected"' : '').'>Suit, Terrie (R-81)</option>
									<option value="90"'.(($form_data['patron_id'] == 90) ? ' selected="selected"' : '').'>Tata, Robert (R-85)</option>
									<option value="3"'.(($form_data['patron_id'] == 3) ? ' selected="selected"' : '').'>Toscano, David (D-57)</option>
									<option value="91"'.(($form_data['patron_id'] == 91) ? ' selected="selected"' : '').'>Tyler, Roslyn (D-75)</option>
									<option value="92"'.(($form_data['patron_id'] == 92) ? ' selected="selected"' : '').'>Valentine, Shannon (D-23)</option>
									<option value="313"'.(($form_data['patron_id'] == 313) ? ' selected="selected"' : '').'>Vanderhye, Margi (D-34)</option>
									<option value="94"'.(($form_data['patron_id'] == 94) ? ' selected="selected"' : '').'>Ward, Jeion (D-92)</option>
									<option value="97"'.(($form_data['patron_id'] == 97) ? ' selected="selected"' : '').'>Ware, Lee (R-65)</option>
									<option value="96"'.(($form_data['patron_id'] == 96) ? ' selected="selected"' : '').'>Ware, Onzlee (D-11)</option>
									<option value="98"'.(($form_data['patron_id'] == 98) ? ' selected="selected"' : '').'>Watts, Vivian (D-39)</option>
									<option value="100"'.(($form_data['patron_id'] == 100) ? ' selected="selected"' : '').'>Wittman, Rob (R-99)</option>
									<option value="101"'.(($form_data['patron_id'] == 101) ? ' selected="selected"' : '').'>Wright, Tom (R-61)</option>
								</optgroup>
								<optgroup label="Senators">
									<option value="311"'.(($form_data['patron_id'] == 311) ? ' selected="selected"' : '').'>Barker, George (D-39)</option>
									<option value="265"'.(($form_data['patron_id'] == 265) ? ' selected="selected"' : '').'>Blevins, Harry (R-14)</option>
									<option value="267"'.(($form_data['patron_id'] == 267) ? ' selected="selected"' : '').'>Colgan, Chuck (D-29)</option>
									<option value="268"'.(($form_data['patron_id'] == 268) ? ' selected="selected"' : '').'>Cuccinelli, Ken (R-37)</option>
									<option value="269"'.(($form_data['patron_id'] == 269) ? ' selected="selected"' : '').'>Deeds, Creigh (D-25)</option>
									<option value="271"'.(($form_data['patron_id'] == 271) ? ' selected="selected"' : '').'>Edwards, John (D-21)</option>
									<option value="272"'.(($form_data['patron_id'] == 272) ? ' selected="selected"' : '').'>Hanger, Emmett (R-24)</option>
									<option value="273"'.(($form_data['patron_id'] == 273) ? ' selected="selected"' : '').'>Hawkins, Charles (R-19)</option>
									<option value="274"'.(($form_data['patron_id'] == 274) ? ' selected="selected"' : '').'>Herring, Mark (D-33)</option>
									<option value="275"'.(($form_data['patron_id'] == 275) ? ' selected="selected"' : '').'>Houck, Edd (D-17)</option>
									<option value="276"'.(($form_data['patron_id'] == 276) ? ' selected="selected"' : '').'>Howell, Janet (D-32)</option>
									<option value="278"'.(($form_data['patron_id'] == 278) ? ' selected="selected"' : '').'>Locke, Mamie (D-2)</option>
									<option value="279"'.(($form_data['patron_id'] == 279) ? ' selected="selected"' : '').'>Lucas, Louise (D-18)</option>
									<option value="280"'.(($form_data['patron_id'] == 280) ? ' selected="selected"' : '').'>Marsh, Henry (D-16)</option>
									<option value="281"'.(($form_data['patron_id'] == 281) ? ' selected="selected"' : '').'>Martin, Stephen (R-11)</option>
									<option value="282"'.(($form_data['patron_id'] == 282) ? ' selected="selected"' : '').'>McDougle, Ryan (R-4)</option>
									<option value="61"'.(($form_data['patron_id'] == 61) ? ' selected="selected"' : '').'>McEachin, Don (D-9)</option>
									<option value="322"'.(($form_data['patron_id'] == 322) ? ' selected="selected"' : '').'>Miller, John (D-1)</option>
									<option value="283"'.(($form_data['patron_id'] == 283) ? ' selected="selected"' : '').'>Miller, Yvonne (D-5)</option>
									<option value="284"'.(($form_data['patron_id'] == 284) ? ' selected="selected"' : '').'>Newman, Steve (R-23)</option>
									<option value="285"'.(($form_data['patron_id'] == 285) ? ' selected="selected"' : '').'>Norment, Tommy (R-3)</option>
									<option value="307"'.(($form_data['patron_id'] == 307) ? ' selected="selected"' : '').'>Northam, Ralph (D-6)</option>
									<option value="286"'.(($form_data['patron_id'] == 286) ? ' selected="selected"' : '').'>Obenshain, Mark (R-26)</option>
									<option value="310"'.(($form_data['patron_id'] == 310) ? ' selected="selected"' : '').'>Petersen, Chap (D-34)</option>
									<option value="289"'.(($form_data['patron_id'] == 289) ? ' selected="selected"' : '').'>Puckett, Phil (D-38)</option>
									<option value="290"'.(($form_data['patron_id'] == 290) ? ' selected="selected"' : '').'>Puller, Toddy (D-36)</option>
									<option value="291"'.(($form_data['patron_id'] == 291) ? ' selected="selected"' : '').'>Quayle, Fred (R-13)</option>
									<option value="293"'.(($form_data['patron_id'] == 293) ? ' selected="selected"' : '').'>Reynolds, Roscoe (D-20)</option>
									<option value="294"'.(($form_data['patron_id'] == 294) ? ' selected="selected"' : '').'>Ruff, Frank (R-15)</option>
									<option value="295"'.(($form_data['patron_id'] == 295) ? ' selected="selected"' : '').'>Saslaw, Dick (D-35)</option>
									<option value="323"'.(($form_data['patron_id'] == 323) ? ' selected="selected"' : '').'>Smith, Ralph (R-22)</option>
									<option value="296"'.(($form_data['patron_id'] == 296) ? ' selected="selected"' : '').'>Stolle, Ken (R-8)</option>
									<option value="297"'.(($form_data['patron_id'] == 297) ? ' selected="selected"' : '').'>Stosch, Walter (R-12)</option>
									<option value="309"'.(($form_data['patron_id'] == 309) ? ' selected="selected"' : '').'>Stuart, Richard (R-28)</option>
									<option value="298"'.(($form_data['patron_id'] == 298) ? ' selected="selected"' : '').'>Ticer, Patsy (D-30)</option>
									<option value="308"'.(($form_data['patron_id'] == 308) ? ' selected="selected"' : '').'>Vogel, Jill Holtzman (R-27)</option>
									<option value="299"'.(($form_data['patron_id'] == 299) ? ' selected="selected"' : '').'>Wagner, Frank (R-7)</option>
									<option value="300"'.(($form_data['patron_id'] == 300) ? ' selected="selected"' : '').'>Wampler, William (R-40)</option>
									<option value="301"'.(($form_data['patron_id'] == 301) ? ' selected="selected"' : '').'>Watkins, John (R-10)</option>
									<option value="302"'.(($form_data['patron_id'] == 302) ? ' selected="selected"' : '').'>Whipple, Mary Margaret (D-31)</option>
								</optgroup>
							</select>
						</td></tr>
						<tr><td><label for="committee">Committee</label></td></tr>
						<tr><td>
							<select name="form_data[committee_id]" id="committee" size="1">
								<option></option>
								<optgroup label="House">
									<option value="1">Ag., Chesapeake and Nat. Resources</option>
									<option value="2">Appropriations</option>
									<option value="3">Commerce and Labor</option>
									<option value="4">Counties, Cities and Towns</option>
									<option value="5">Courts of Justice</option>
									<option value="6">Education</option>
									<option value="7">Finance</option>
									<option value="8">General Laws</option>
									<option value="9">Health, Welfare and Institutions</option>
									<option value="10">Militia, Police and Public Safety</option>
									<option value="11">Privileges and Elections</option>
									<option value="12">Rules</option>
									<option value="13">Science and Technology</option>
									<option value="14">Transportation</option>
								</optgroup>
								<optgroup label="Senate">
									<option value="15">Ag., Conservation and Nat. Resources</option>
									<option value="16">Commerce and Labor</option>
									<option value="17">Courts of Justice</option>
									<option value="18">Education and Health</option>
									<option value="19">Finance</option>
									<option value="20">General Laws and Technology</option>
									<option value="21">Local Government</option>
									<option value="22">Privileges and Elections</option>
									<option value="23">Rehabilitation and Soc. Services</option>
									<option value="24">Rules</option>
									<option value="25">Transportation</option>
								</optgroup>
							</select>
						</td></tr>
						<tr><td><label for="keyword">Keyword</label></td></tr>
						<tr><td><input type="text" size="20" maxlength="120" name="form_data[keyword]" id="keyword" value="'.$form_data['keyword'].'" /><br /></td></tr>
						<tr><td><label for="status">Status</label></td></tr>
						<tr><td>
							<select name="form_data[status]" id="status" />
								<option></option>
								<option value="introduced"'.($form_data['status'] == 'introduced' ? ' selected="selected"' : '').'>Introduced</option>
								<option value="passed house"'.($form_data['status'] == 'passed house' ? ' selected="selected"' : '').'>Passed House</option>
								<option value="passed senate"'.($form_data['status'] == 'passed senate' ? ' selected="selected"' : '').'>Passed Senate</option>
								<option value="passed"'.($form_data['status'] == 'passed' ? ' selected="selected"' : '').'>Passed</option>
								<option value="failed"'.($form_data['status'] == 'failed' ? ' selected="selected"' : '').'>Failed</option>
								<option value="continued"'.($form_data['status'] == 'continued' ? ' selected="selected"' : '').'>Continued</option>
								<option value="approved"'.($form_data['status'] == 'approved' ? ' selected="selected"' : '').'>Approved</option>
								<option value="vetoed"'.($form_data['status'] == 'vetoed' ? ' selected="selected"' : '').'>Vetoed</option>
							</select>
						</td></tr>
						<tr><td><label for="chamber">Current Chamber</label></td></tr>
						<tr><td>
							<select name="form_data[current_chamber]" id="chamber" />
								<option></option>
								<option value="house"'.($form_data['current_chamber'] == 'house' ? ' selected="selected"' : '').'>House</option>
								<option value="senate"'.($form_data['current_chamber'] == 'senate' ? ' selected="selected"' : '').'>Senate</option>
							</select>
						</td></tr>
						<tr><td>
							<fieldset id="public">
								<legend>Public Visibility</legend>
								<input type="radio" name="form_data[public]" id="public-y" value="y"'.(($form_data['public'] == 'y') ? ' checked="checked"' : '').' /><label for="public-y">Anybody can see this portfolio</label>
								<input type="radio" name="form_data[public]" id="public-n" value="n"'.(($form_data['public'] == 'n') ? ' checked="checked"' : '').' /><label for="public-n">Only let me see this portfolio</label>
							</fieldset>
						</td></tr>
						<tr><td>
							<fieldset id="notify">
								<legend>E-Mail Notification</legend>
								<input type="radio" name="form_data[notify]" id="none" value="none"'.(($form_data['notify'] == 'none') ? ' checked="checked"' : '').' /><label for="none" class="label-radio">None</label>
								<input type="radio" name="form_data[notify]" id="hourly" value="hourly"'.(($form_data['notify'] == 'hourly') ? ' checked="checked"' : '').' /><label for="hourly" class="label-radio">Hourly</label>
								<input type="radio" name="form_data[notify]" id="daily" value="daily"'.(($form_data['notify'] == 'daily') ? ' checked="checked"' : '').' /><label for="daily" class="label-radio">Daily</label>
							</fieldset>
						</td></tr>
					</table>';
	if (isset($form_data['id'])) $content .= '
				<input type="hidden" name="edit-smart-portfolio" value="y">
				<input type="hidden" name="form_data[id]" id="portfolio-id" value="'.$form_data['id'].'" />
				<input type="hidden" name="form_data[type]" value="smart">
				<input type="submit" name="submit" id="submit" value="Save Changes">';
	else $content .= '
				<input type="hidden" name="add-smart-portfolio" value="y">
				<input type="submit" name="submit" id="submit" value="Create">';
	$content .= '
				</fieldset>
			</form>';
	
	return $content;
}

###
# PORTFOLIO FORM
# Waldo Jaquith <waldo@jaquith.org>
# October 22, 2007
#
# PURPOSE
# Displays the form for creating or editing a portfolio.
#
# NOTES
# None
###
function portfolio_form($form_data)
{

	# If we're editing an existing portfolio.
	if (isset($form_data['id']))
	{
		$submit_label = 'Save Changes';
		$action = $_SERVER['REQUEST_URI'];
	}
	
	# Else if we're creating a new portfolio.
	else
	{
		$submit_label = 'Create';
		$action = '/photosynthesis/process-actions.php';
	}
	$content = '
		<form method="post" action="'.$action.'">
			
			<fieldset id="create-portfolio">
				<table class="form">
					<tr><td><label for="name">Name</label></td></tr>
					<tr><td><input type="text" size="40" maxlength="120" id="name" name="form_data[name]" value="'.(!empty($form_data['name']) ? $form_data['name'] : '').'" /></td></tr>
					<tr><td><label for="notes">Description</label></td></tr>
					<tr><td><textarea name="form_data[notes]" id="notes">'.(!empty($form_data['notes']) ? $form_data['notes'] : '').'</textarea></td></tr>
					<tr><td>
						<fieldset id="public">
							<legend>Public Visibility</legend>
							<input type="radio" name="form_data[public]" id="public-y" value="y"'.(($form_data['public'] == 'y') ? ' checked="checked"' : '').' /><label for="public-y">Anybody can see this portfolio</label>
							<input type="radio" name="form_data[public]" id="public-n" value="n"'.(($form_data['public'] == 'n') ? ' checked="checked"' : '').' /><label for="public-n">Only let me see this portfolio</label>
						</fieldset>
					</td></tr>
					<tr><td>
						<fieldset id="notify">
							<legend>E-Mail Notification</legend>
							<input type="radio" name="form_data[notify]" id="none" value="none"'.(($form_data['notify'] == 'none') ? ' checked="checked"' : '').' /><label for="none" class="label-radio">None</label>
							<input type="radio" name="form_data[notify]" id="hourly" value="hourly"'.(($form_data['notify'] == 'hourly') ? ' checked="checked"' : '').' /><label for="hourly" class="label-radio">Hourly</label>
							<input type="radio" name="form_data[notify]" id="daily" value="daily"'.(($form_data['notify'] == 'daily') ? ' checked="checked"' : '').' /><label for="daily" class="label-radio">Daily</label>
						</fieldset>
					</td></tr>
					<tr><td><input type="submit" name="submit" value="'.$submit_label.'" /></td></tr>
				</table>
			</fieldset>';
	if (isset($form_data['id'])) $content .= '
			<input type="hidden" name="form_data[id]" id="portfolio-id" value="'.$form_data['id'].'" />';
	else $content .= '
			<input type="hidden" name="add-portfolio" value="y" />';
	$content .= '
		</form>';
	
	return $content;
}


###
# DISPLAY PORTFOLIO
# Waldo Jaquith <waldo@jaquith.org>
# November 30, 2007
#
# PURPOSE
# Returned the contents of a portfolio.
#
# NOTES
# Accepts an ID as input, outputs HTML.
###
function show_portfolio($portfolio, $user_id)
{			
	# Get a listing of all of the bills in this portfolio.
	$sql = 'SELECT dashboard_bills.id AS record_id, dashboard_bills.notes, bills.number,
			bills.catch_line,
	
				(SELECT translation
				FROM bills_status
				WHERE bill_id = bills.id AND translation IS NOT NULL
				ORDER BY date DESC, date_created DESC
				LIMIT 1) AS last_action,
				
				(SELECT DATE_FORMAT(date, "%m/%d/%y") AS date_formatted
				FROM bills_status
				WHERE bill_id = bills.id AND translation IS NOT NULL
				ORDER BY date DESC, date_created DESC
				LIMIT 1) AS last_date
			
			FROM bills LEFT JOIN dashboard_bills
			ON bills.id = dashboard_bills.bill_id
			WHERE dashboard_bills.user_id='.$user_id.'
			AND dashboard_bills.portfolio_id = '.$portfolio['id'].'
			AND bills.session_id = '.SESSION_ID.'
			ORDER BY last_date DESC';
	$result = mysql_query($sql);
	if (mysql_num_rows($result) > 0)
	{

		$content = '
		<table style="clear: both;" class="sortable" id="listing-'.$portfolio['hash'].'">
			<thead>
				<tr>
					<th id="bill">Bill</th>
					<th id="catch-line">Catch Line</th>
					<th id="last-action">Last Action</th>
					<th id="date">Date</th>';
		if ($portfolio['type'] == 'normal')
		{
			$content .= '
					<th id="options">&nbsp;</th>';
		}
		$content .= '
				</tr>
			</thead>
			<tbody>';
		while ($bill = mysql_fetch_array($result))
		{
			$bill = array_map('stripslashes', $bill);
			$bill['timestamp'] = strtotime($bill['last_date']);
			
			$content .= '
				<tr'.(($_SESSION['last_access'] < $bill['timestamp']) ? ' class="changed"' : '').'>
					<td><a href="/bill/'.SESSION_YEAR.'/'.$bill['number'].'/">'
					.strtoupper($bill['number']).'</a></td>
					<td>'.$bill['catch_line'].'</td>
					<td>'.$bill['last_action'].'</td>
					<td sorttable_customkey="'.$bill['timestamp'].'">'.$bill['last_date'].'</td>';
			if ($portfolio['type'] == 'normal')
			{
				$content .= '
					<td class="options">
						<a href="/photosynthesis/delete/'.$portfolio['hash'].'-'.$bill['record_id'].'/" title="Stop tracking this bill"
							onclick="return confirm(\'Are you sure you want to stop tracking '.strtoupper($bill['number']).'?\')">x</a>
					</td>';
			}
			$content .= '</tr>';
			
			if (empty($bill['notes']))
			{
				$bill['notes'] = 'Add your comments about this bill '
					.'here—they’ll be listed on your public portfolio and on the bill’s page. '
					.'Should this pass or fail? Why?';
			}
			
			$content .= '
			<tr id="'.$bill['record_id'].'-notes" class="notes">
				<td></td>
				<td colspan="'.(($portfolio['type'] == 'normal') ? '4' : '3').'">
					<div class="edit" id="'.$bill['record_id'].'">'.$bill['notes'].'</div>
				</td>
			</tr>';
		}
		$content .= '
			</tbody>
		</table>';
	}
	else
	{
		if ($portfolio['type'] == 'smart')
		{
			$content .= '
			<div class="no-bills">No bills currently match your criteria for this smart portfolio.</div>';
		}
		else
		{
			$content .= '
			<div class="no-bills">
				<strong>Get started: Add some bills to your portfolio!</strong>
				<p>You can either enter a bill ID here, or you can add them directly from any
				<a href="/bills/">bill</a> page throughout the website.</p>
			</div>';
		}
	}
	
	return $content;
}

?>