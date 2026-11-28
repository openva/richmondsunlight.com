<?php

###
# Photosynthesis Functions
#
# PURPOSE
# A function library for the Photosynthesis bill-tracking tools.
#
###

# Fills a smart portfolio with the bills that match its criteria. rs-machine's photosynthesis task
# calls this for every smart portfolio, hourly; the work is Portfolio::populate()'s.
function populate_smart_portfolio($portfolio_id)
{
    return Portfolio::populate((int) $portfolio_id) !== null;
}

/*
 * The form for creating or editing a smart portfolio: its name, description, visibility, and
 * email, and its criteria (a tag, a patron, and a committee, any of which may be left blank),
 * each showing what's saved.
 */
function smart_portfolio_form($form_data = array())
{
    $defaults = array(
        'name' => '',
        'notes' => '',
        'tag' => '',
        'patron_id' => '',
        'committee_id' => '',
        'public' => 'y',
        'notify' => 'none',
    );
    $form_data = array_merge($defaults, (array) $form_data);
    $editing = isset($form_data['id']);
    $action = $editing ? $_SERVER['REQUEST_URI'] : '/photosynthesis/process-actions.php';

    $options = function (array $groups, $selected) {
        $html = '<option value="">Any</option>';
        foreach (['former' => 'Saved', 'house' => 'House', 'senate' => 'Senate'] as $chamber => $label) {
            if (count($groups[$chamber] ?? []) == 0) {
                continue;
            }
            $html .= '<optgroup label="' . $label . '">';
            foreach ($groups[$chamber] as $option) {
                $html .= '<option value="' . (int) $option['id'] . '"'
                    . ((string) $option['id'] === (string) $selected ? ' selected="selected"' : '') . '>'
                    . Portfolio::text($option['label']) . '</option>';
            }
            $html .= '</optgroup>';
        }
        return $html;
    };

    # A saved patron or committee that's no longer current is still shown, and kept.
    $patrons = Portfolio::patrons();
    $committees = Portfolio::committees();
    if (!empty($form_data['patron_id']) && !in_array((int) $form_data['patron_id'], array_column(array_merge(...array_values($patrons)), 'id'), true)) {
        $result = mysqli_execute_query($GLOBALS['db'], 'SELECT name FROM people WHERE id = ?', [(int) $form_data['patron_id']]);
        $person = $result ? mysqli_fetch_assoc($result) : null;
        if ($person) {
            $patrons['former'] = [['id' => (int) $form_data['patron_id'], 'label' => $person['name'] . ' (no longer serving)']];
        }
    }
    if (!empty($form_data['committee_id']) && !in_array((int) $form_data['committee_id'], array_column(array_merge(...array_values($committees)), 'id'), true)) {
        $result = mysqli_execute_query($GLOBALS['db'], 'SELECT name, chamber FROM committees WHERE id = ?', [(int) $form_data['committee_id']]);
        $committee = $result ? mysqli_fetch_assoc($result) : null;
        if ($committee) {
            $committees['former'] = [['id' => (int) $form_data['committee_id'], 'label' => ucfirst($committee['chamber']) . ' ' . $committee['name'] . ' (no longer exists)']];
        }
    }

    $tags = '';
    foreach (Portfolio::tag_suggestions() as $tag) {
        $tags .= '<option value="' . Portfolio::text($tag) . '"></option>';
    }
    $list_id = 'smart-tags-' . ($editing ? (int) $form_data['id'] : 'new');

    $content = '
			<form method="post" action="' . Portfolio::text($action) . '">
				' . Csrf::field() . '

				<fieldset id="create-smart-portfolio">
					<table>
						<tr><td><label for="name">Name</label></td></tr>
						<tr><td><input type="text" size="20" maxlength="120" name="form_data[name]" id="name" value="' . Portfolio::text($form_data['name']) . '" /></td></tr>
						<tr><td><label for="notes">Description</label></td></tr>
						<tr><td><textarea name="form_data[notes]" id="notes">' . Portfolio::text($form_data['notes']) . '</textarea></td></tr>
						<tr><td><p class="criteria-help">Gather every bill of this session that matches
							all of these. Choose at least one.</p></td></tr>
						<tr><td><label for="tag">Tag</label></td></tr>
						<tr><td><input type="text" size="20" maxlength="30" name="form_data[tag]" id="tag" list="' . $list_id . '" autocomplete="off" value="' . Portfolio::text($form_data['tag']) . '" />
							<datalist id="' . $list_id . '">' . $tags . '</datalist>
							<br /><small>A tag that no bill has yet is fine: bills are tagged as the session goes.</small></td></tr>
						<tr><td><label for="patron">Patron</label></td></tr>
						<tr><td><select name="form_data[patron_id]" id="patron" size="1">' . $options($patrons, $form_data['patron_id']) . '</select></td></tr>
						<tr><td><label for="committee">Committee</label></td></tr>
						<tr><td><select name="form_data[committee_id]" id="committee" size="1">' . $options($committees, $form_data['committee_id']) . '</select>
							<br /><small>Bills referred to it this session, even after they’ve moved on.</small></td></tr>
						<tr><td>
							<fieldset id="public">
								<legend>Public Visibility</legend>
								<input type="radio" name="form_data[public]" id="public-y" value="y"' . (($form_data['public'] == 'y') ? ' checked="checked"' : '') . ' /><label for="public-y">Anybody can see this portfolio</label>
								<input type="radio" name="form_data[public]" id="public-n" value="n"' . (($form_data['public'] == 'n') ? ' checked="checked"' : '') . ' /><label for="public-n">Only let me see this portfolio</label>
							</fieldset>
						</td></tr>
						<tr><td>
							<fieldset id="notify">
								<legend>Email</legend>
								<input type="radio" name="form_data[notify]" id="none" value="none"' . (($form_data['notify'] == 'none') ? ' checked="checked"' : '') . ' /><label for="none" class="label-radio">No email</label>
								<input type="radio" name="form_data[notify]" id="daily" value="daily"' . (($form_data['notify'] == 'daily') ? ' checked="checked"' : '') . ' /><label for="daily" class="label-radio">Email me each morning when its bills have news</label>
							</fieldset>
						</td></tr>
					</table>';
    if ($editing) {
        $content .= '
				<p class="warning">If you change what it gathers, the bills that no longer match will be
				taken out of it, except those you’ve written notes on.</p>
				<input type="hidden" name="edit-smart-portfolio" value="y">
				<input type="hidden" name="form_data[id]" id="portfolio-id" value="' . (int) $form_data['id'] . '" />
				<input type="hidden" name="form_data[type]" value="smart">
				<input type="submit" name="submit" id="submit" value="Save Changes">';
    } else {
        $content .= '
				<input type="hidden" name="add-smart-portfolio" value="y">
				<input type="submit" name="submit" id="submit" value="Create">';
    }
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
function portfolio_form($form_data = array())
{
    # Provide defaults so the form can render even when no data is supplied.
    $defaults = array(
        'name' => '',
        'notes' => '',
        'public' => 'y',
        'notify' => 'none',
    );
    $form_data = array_merge($defaults, (array) $form_data);

    # If we're editing an existing portfolio.
    if (isset($form_data['id'])) {
        $submit_label = 'Save Changes';
        $action = $_SERVER['REQUEST_URI'];
    }

    # Else if we're creating a new portfolio.
    else {
        $submit_label = 'Create';
        $action = '/photosynthesis/process-actions.php';
    }
    $content = '
		<form method="post" action="' . Portfolio::text($action) . '">
			' . Csrf::field() . '

			<fieldset id="create-portfolio">
				<table class="form">
					<tr><td><label for="name">Name</label></td></tr>
					<tr><td><input type="text" size="40" maxlength="120" id="name" name="form_data[name]" value="' . Portfolio::text($form_data['name']) . '" /></td></tr>
					<tr><td><label for="notes">Description</label></td></tr>
					<tr><td><textarea name="form_data[notes]" id="notes">' . Portfolio::text($form_data['notes']) . '</textarea></td></tr>
					<tr><td>
						<fieldset id="public">
							<legend>Public Visibility</legend>
							<input type="radio" name="form_data[public]" id="public-y" value="y"' . (($form_data['public'] == 'y') ? ' checked="checked"' : '') . ' /><label for="public-y">Anybody can see this portfolio</label>
							<input type="radio" name="form_data[public]" id="public-n" value="n"' . (($form_data['public'] == 'n') ? ' checked="checked"' : '') . ' /><label for="public-n">Only let me see this portfolio</label>
						</fieldset>
					</td></tr>
					<tr><td>
						<fieldset id="notify">
							<legend>Email</legend>
							<input type="radio" name="form_data[notify]" id="none" value="none"' . (($form_data['notify'] == 'none') ? ' checked="checked"' : '') . ' /><label for="none" class="label-radio">No email</label>
							<input type="radio" name="form_data[notify]" id="daily" value="daily"' . (($form_data['notify'] == 'daily') ? ' checked="checked"' : '') . ' /><label for="daily" class="label-radio">Email me each morning when its bills have news</label>
						</fieldset>
					</td></tr>
					<tr><td><input type="submit" name="submit" value="' . $submit_label . '" /></td></tr>
				</table>
			</fieldset>';
    if (isset($form_data['id'])) {
        $content .= '
			<input type="hidden" name="form_data[id]" id="portfolio-id" value="' . (int) $form_data['id'] . '" />';
    } else {
        $content .= '
			<input type="hidden" name="add-portfolio" value="y" />';
    }
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
    $hash = Portfolio::text($portfolio['hash']);

    # Get a listing of all of the bills in this portfolio.
    $sql = 'SELECT dashboard_bills.id AS record_id, dashboard_bills.notes, dashboard_bills.excluded,
			bills.number, bills.catch_line,

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
			WHERE dashboard_bills.user_id = ?
			AND dashboard_bills.portfolio_id = ?
			AND bills.session_id = ?
			ORDER BY last_date DESC';
    $result = mysqli_execute_query($GLOBALS['db'], $sql, [(int) $user_id, (int) $portfolio['id'], SESSION_ID]);

    # A smart portfolio's hidden bills are listed apart, each with a button to show it again.
    $rows = '';
    $hidden = [];
    while ($result && ($bill = mysqli_fetch_array($result))) {
        $bill = array_map('stripslashes_nullsafe', $bill);
        $record = $hash . '-' . (int) $bill['record_id'];
        if ($bill['excluded'] == 'y') {
            $hidden[] = '<li><a href="/bill/' . SESSION_YEAR . '/' . $bill['number'] . '/">' . mb_strtoupper($bill['number']) . '</a>: '
                . $bill['catch_line'] . '
				<form method="post" action="/photosynthesis/process-actions.php" class="show-bill">
					' . Csrf::field() . '
					<input type="hidden" name="show-bill" value="' . $record . '" />
					<button type="submit">Show again</button>
				</form></li>';
            continue;
        }
        $bill['timestamp'] = strtotime((string) $bill['last_date']);

        $rows .= '
				<tr' . ((isset($_SESSION['last_access']) && $_SESSION['last_access'] < $bill['timestamp']) ? ' class="changed"' : '') . '>
					<td><a href="/bill/' . SESSION_YEAR . '/' . $bill['number'] . '/">'
                    . mb_strtoupper($bill['number']) . '</a></td>
					<td>' . $bill['catch_line'] . '</td>
					<td>' . $bill['last_action'] . '</td>
					<td sorttable_customkey="' . $bill['timestamp'] . '">' . $bill['last_date'] . '</td>
					<td class="options">';
        if ($portfolio['type'] == 'normal') {
            $rows .= '
						<form method="post" action="/photosynthesis/process-actions.php" class="delete-bill"
							onsubmit="return confirm(\'Are you sure you want to stop tracking ' . mb_strtoupper($bill['number']) . '?\')">
							' . Csrf::field() . '
							<input type="hidden" name="delete-bill" value="' . $record . '" />
							<button type="submit" title="Stop tracking this bill">&#x2715; Remove</button>
						</form>';
        } else {
            $rows .= '
						<form method="post" action="/photosynthesis/process-actions.php" class="hide-bill">
							' . Csrf::field() . '
							<input type="hidden" name="hide-bill" value="' . $record . '" />
							<button type="submit" title="Leave this bill out of this portfolio, though it matches">&#x2715; Hide</button>
						</form>';
        }
        $rows .= '
					</td>
				</tr>';

        if (empty($bill['notes'])) {
            $notes = 'Add your comments about this bill '
                . 'here—they’ll be listed on your public portfolio and on the bill’s page. '
                . 'Should this pass or fail? Why?';
        } else {
            $notes = Portfolio::notes_html($bill['notes']);
        }

        $rows .= '
			<tr id="' . (int) $bill['record_id'] . '-notes" class="notes">
				<td></td>
				<td colspan="4">
					<div class="edit" id="' . (int) $bill['record_id'] . '">' . $notes . '</div>
				</td>
			</tr>';
    }

    if ($rows !== '') {
        $content = '
		<table style="clear: both;" class="sortable" id="listing-' . $hash . '">
			<thead>
				<tr>
					<th id="bill">Bill</th>
					<th id="catch-line">Catch Line</th>
					<th id="last-action">Last Action</th>
					<th id="date">Date</th>
					<th id="options">&nbsp;</th>
				</tr>
			</thead>
			<tbody>' . $rows . '
			</tbody>
		</table>';
    } elseif ($portfolio['type'] == 'smart') {
        $content = '
			<div class="no-bills">No bills match this smart portfolio yet.</div>';
    } else {
        $content = '
			<div class="no-bills">
				<strong>Get started: Add some bills to your portfolio!</strong>
				<p>You can either enter a bill ID here, or you can add them directly from any
				<a href="/bills/">bill</a> page throughout the website.</p>
			</div>';
    }

    if (count($hidden) > 0) {
        $content .= '
		<div class="hidden-bills" id="hidden-' . $hash . '">
			<p>Hidden from this portfolio, though they match:</p>
			<ul>' . implode('', $hidden) . '</ul>
		</div>';
    }

    return $content;
}
