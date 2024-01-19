<?php

###
# List District IDs
#
# PURPOSE
# Lists all district numbers and IDs, used as a lookup when entering new legislators.
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/functions.inc.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/settings.inc.php';

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database();
$database->connect_mysqli();

# PAGE METADATA
$page_title = 'District IDs';
$site_section = 'admin';

# PAGE CONTENT

$sql = 'SELECT id, number, chamber
        FROM districts
        WHERE date_ended IS NULL
        ORDER BY chamber ASC, number ASC';
$result = mysqli_query($GLOBALS['db'], $sql);

$page_body .= '
    <table>
        <thead>
            <tr><th>Chamber</th><th>Number</th><th>ID</th></tr>
        </thead>
        <tbody>';

while ($district = mysqli_fetch_array($result)) {
    $page_body .= '
        <tr>
            <td>' . $district['chamber'] . '</td>
            <td>' . $district['number'] . '</td>
            <td>' . $district['id'] . '</td>
        </tr>';
}

$page_body .= '</tbody></table>';

# OUTPUT THE PAGE
$page = new Page();
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->process();
