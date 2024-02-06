<?php

###
# Statistics
#
# PURPOSE
# Lists misc. statistics about bills.
#
###

# INCLUDES
# Include any files or libraries that are necessary for this specific page to function.
include_once 'includes/settings.inc.php';
include_once 'vendor/autoload.php';

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific page.
$database = new Database();
$database->connect_mysqli();

# PAGE METADATA
$page_title = 'Statistics';
$site_section = 'statistics';

$html_head = '<style>
    ol {
        list-style: decimal;
        margin-left: 2em;
    }
</style>
<script src="/js/vendor/chart.js/dist/chart.umd.js"></script>';

# PAGE CONTENT
$sql = 'SELECT
            date,
            COUNT(*) actions
        FROM bills_status
        WHERE
            date >= "' . SESSION_START . '" AND
            DATE <= "' . SESSION_END . '"
        GROUP BY date
        ORDER BY date ASC';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0) {
    $page_body = '<h2>Daily Bill Actions</h2>
        <p><a href="/bills/activity/">Actions are taken on bills each day</a>—they’re voted on,
        sent to committees, assessed, etc. Here is how many such actions were taken each day.</p>';
    $days = mysqli_fetch_all($result, MYSQLI_ASSOC);

    $labels = json_encode(array_column($days, 'date'));
    $data = json_encode(array_column($days, 'actions'));
    $page_body .=
    <<<EOD
<div>
  <canvas id="daily-bill-actions-chart"></canvas>
</div>

<script>
  const ctx1 = document.getElementById('daily-bill-actions-chart');

  new Chart(ctx1, {
    type: 'bar',
    data: {
      labels: $labels,
      datasets: [{
        label: '# of Actions',
        data: $data,
        backgroundColor: '#dccbaf',
        borderWidth: 1
      }]
    },
    options: {
      scales: {
        y: {
          beginAtZero: true
        }
      },
      plugins: {
        legend: {
            display: false
        }
      }
    }
  });
</script>
EOD;
}

$sql = 'SELECT
            DATE_FORMAT(date_introduced, "%M %d") AS date,
            COUNT(*) as number
        FROM bills
        WHERE
            session_id=30
        GROUP BY date_introduced
        ORDER BY date_introduced ASC';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0) {
    $page_body .= '<h2>Number of Bills Introduced Daily for ' . SESSION_YEAR . '</h2>';
    $days = mysqli_fetch_all($result, MYSQLI_ASSOC);

    $labels = json_encode(array_column($days, 'date'));
    $data = json_encode(array_column($days, 'number'));

    $page_body .=
    <<<EOD
<div>
  <canvas id="daily-bills-introduced-chart"></canvas>
</div>

<script>
  const ctx2 = document.getElementById('daily-bills-introduced-chart');

  new Chart(ctx2, {
    type: 'bar',
    data: {
      labels: $labels,
      datasets: [{
        label: '# of Bills',
        data: $data,
        backgroundColor: '#dccbaf',
        borderWidth: 1
      }]
    },
    options: {
      scales: {
        y: {
          beginAtZero: true
        }
      },
      plugins: {
        legend: {
            display: false
        }
      }
    }
  });
</script>
EOD;
}


$sql = 'SELECT
            representatives.name_formatted AS name,
            representatives.shortname,
            COUNT(*) AS number
        FROM representatives
        LEFT JOIN bills
            ON representatives.id=bills.chief_patron_id
        WHERE bills.session_id=30
        GROUP BY representatives.id
        ORDER BY number DESC, name ASC
        LIMIT 10';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0) {
    $page_body .= '<h2>Top 10 Bill Filers in ' . SESSION_YEAR . '</h2><ol>';
    $total = 0;
    while ($legislator = mysqli_fetch_assoc($result)) {
        $page_body .= '<li><a href="/legislator/' . $legislator['shortname'] . '/">'
            . $legislator['name'] . '</a>: ' . $legislator['number'] . ' bills</li>';
    }
    $page_body .= '</ol>';
}

$sql = 'SELECT
            number,
            catch_line
        FROM bills
        WHERE session_id = ' .SESSION_ID . '
        ORDER BY view_count DESC
        LIMIT 10';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0) {
    $page_body .= '<h2>Top 10 Most-Viewed Bills for ' . SESSION_YEAR . '</h2><ol>';
    $total = 0;
    while ($bill = mysqli_fetch_assoc($result)) {
        $page_body .= '<li><a href="/bill/' . SESSION_YEAR . '/' . $bill['number'] . '/">'
            . strtoupper($bill['number']) . '</a>: ' . $bill['catch_line'] . '</li>';
    }
    $page_body .= '</ol>';
}

# SIDEBAR

# Select the total number of bills introduced in each chamber.
$sql = 'SELECT
            chamber,
            COUNT(*) AS count
        FROM bills
        WHERE
            session_id=' . SESSION_ID . '
        GROUP BY chamber';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0) {
    $page_sidebar .= '
                <div class="box">
                    <h3>By Chamber</h3>';
    while ($chamber = mysqli_fetch_array($result)) {
        if ($chamber['chamber'] == 'house') {
            $house['count'] = number_format($chamber['count']);
            $house['avg'] = round(($chamber['count'] / 100), 1);
        } elseif ($chamber['chamber'] == 'senate') {
            $senate['count'] = number_format($chamber['count']);
            $senate['avg'] = round(($chamber['count'] / 40), 1);
        }
    }

    $page_sidebar .= '
                    <strong>Senate</strong>
                    <ul>
                        <li>' . $senate['count'] . ' total bills</li>
                        <li>' . $senate['avg'] . ' bills per legislator</li>
                    </ul>
                    <strong>House</strong>
                    <ul>
                        <li>' . $house['count'] . ' total bills</li>
                        <li>' . $house['avg'] . ' bills per legislator</li>
                    </ul>';
    $page_sidebar .= '
                </div>';
}

# Select the total number of bills introduced in each chamber.
$sql = 'SELECT
            representatives.party,
            COUNT(*) AS count,
            (
                SELECT COUNT(*)
                FROM representatives
                WHERE party="D"
                AND date_ended IS NULL
            ) AS democrats_count,
            (
                SELECT COUNT(*)
                FROM representatives
                WHERE party="R"
                AND date_ended IS NULL
            ) AS republicans_count
        FROM bills
        LEFT JOIN representatives
            ON bills.chief_patron_id=representatives.id
        WHERE
            bills.session_id=' . SESSION_ID . '
        GROUP BY party';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0) {
    $page_sidebar .= '
			<div class="box">
				<h3>By Party</h3>';
    while ($party = mysqli_fetch_array($result)) {
        if ($party['party'] == 'R') {
            $republican['count'] = number_format($party['count']);
            $republican['avg'] = round(($party['count'] / $party['republicans_count']), 1);
        } elseif ($party['party'] == 'D') {
            $democratic['count'] = number_format($party['count']);
            $democratic['avg'] = round(($party['count'] / $party['democrats_count']), 1);
        }
    }

    $page_sidebar .= '
				<strong>Republican</strong>
				<ul>
					<li>' . $republican['count'] . ' total bills</li>
					<li>' . $republican['avg'] . ' bills per legislator</li>
				</ul>
				<strong>Democratic</strong>
				<ul>
					<li>' . $democratic['count'] . ' total bills</li>
					<li>' . $democratic['avg'] . ' bills per legislator</li>
				</ul>';
    $page_sidebar .= '
			</div>';
}

# Republican Tag Cloud
$sql = 'SELECT
            COUNT(*) AS count,
            tags.tag
        FROM tags
        LEFT JOIN bills
            ON tags.bill_id = bills.id
        LEFT JOIN representatives
            ON bills.chief_patron_id = representatives.id
        WHERE
            representatives.party = "R" AND
            bills.session_id = ' . SESSION_ID . '
        GROUP BY tags.tag
        HAVING count > 20
        ORDER BY tags.tag ASC';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0) {
    $page_sidebar .= '
		<a href="javascript:openpopup(\'/help/tag-clouds/\')" title="Help"><img
            src="/images/help-beige.gif" class="help-icon" alt="?" /></a>

		<div class="box">
			<h3>Republican Tag Cloud</h3>
			<div class="tags">';
    while ($tag = mysqli_fetch_array($result)) {
        $tags[] = array_map('stripslashes', $tag);
    }
    for ($i = 0; $i < count($tags); $i++) {
        $font_size = round((log($tags[$i]['count']) / 3), 2);
        if ($font_size < '.75') {
            $font_size = '.75';
        }
        $page_sidebar .= '<span style="font-size: ' . $font_size . 'em;">
					<a href="/bills/tags/' . urlencode($tags[$i]['tag']) . '/">' . $tags[$i]['tag']
                    . '</a>
				</span>';
    }
    $page_sidebar .= '
			</div>
		</div>';
    unset($tags);
}

# Democratic Tag Cloud
$sql = 'SELECT
            COUNT(*) AS count,
            tags.tag
        FROM tags
        LEFT JOIN bills
            ON tags.bill_id = bills.id
        LEFT JOIN representatives
            ON bills.chief_patron_id = representatives.id
        WHERE
            representatives.party = "D" AND
            bills.session_id = ' . SESSION_ID . '
        GROUP BY tags.tag
        HAVING count > 20
        ORDER BY tags.tag ASC';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0) {
    $page_sidebar .= '
		<a href="javascript:openpopup(\'/help/tag-clouds/\')" title="Help"><img
        src="/images/help-beige.gif" class="help-icon" alt="?" /></a>

		<div class="box">
			<h3>Democratic Tag Cloud</h3>
			<div class="tags">';
    while ($tag = mysqli_fetch_array($result)) {
        $tags[] = array_map('stripslashes', $tag);
    }
    for ($i = 0; $i < count($tags); $i++) {
        $font_size = round((log($tags[$i]['count']) / 3), 2);
        if ($font_size < '.75') {
            $font_size = '.75';
        }
        $page_sidebar .= '<span style="font-size: ' . $font_size . 'em;">
					<a href="/bills/tags/' . urlencode($tags[$i]['tag']) . '/">' . $tags[$i]['tag']
                    . '</a>
				</span>';
    }
    $page_sidebar .= '
			</div>
		</div>';
}

# OUTPUT THE PAGE
$page = new Page();
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->html_head = $html_head;
$page->process();
