
<?php

error_reporting(E_ALL);
ini_set('display_errors', 'On');

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once '../../includes/settings.inc.php';
include_once '../../includes/functions.inc.php';

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database;
$database->connect_old();

if (count($_POST) == 0)
{

    # Generate pulldown
    $sql = 'SELECT id, name
			FROM representatives
			ORDER BY name ASC';
    $result = mysqli_query($GLOBALS['db'], $sql);
    $legislator_select = '<option value=""></option><option value="ignore">Ignore</option>';
    while ($legislator = mysqli_fetch_array($result))
    {
        $legislator_select .= '<option value="' . $legislator['id'] . '">' .  stripslashes($legislator['name'])
            . '</option>';
    }

    $sql = 'SELECT vi.raw_text, COUNT(*) AS number, CONCAT(files.capture_directory, vi2.screenshot, ".jpg") AS url
			FROM video_index AS vi
			LEFT JOIN video_index as vi2
				ON vi.id = vi2.id
			LEFT JOIN files
				ON vi.file_id = files.id
			WHERE vi.linked_id IS NULL
			AND vi.type = "legislator"
			AND vi.ignored = "n"
			AND vi.raw_text NOT LIKE "%Virginia Senate%"
			AND vi.raw_text NOT LIKE "%Senate of Virginia%"
			AND vi.raw_text NOT LIKE "%The Senate of Vi%"
			AND vi.raw_text NOT LIKE "%Schaar%"
			AND vi.raw_text NOT LIKE "%Delegates%"
			AND vi.raw_text NOT LIKE "%At Ease%"
			AND vi.raw_text NOT LIKE "%Reverend%"
			AND vi.raw_text NOT LIKE "%Rabbi%"
			AND vi.raw_text NOT LIKE "%in Recess%"
			AND vi.raw_text NOT LIKE "%at Ease%"
			GROUP BY raw_text
			HAVING number > 2
			ORDER BY number DESC
			LIMIT 50';
    $result = mysqli_query($GLOBALS['db'], $sql);
    if (mysqli_num_rows($result) < 1)
    {
        die('No orphaned chyrons found.');
    }

    echo '
	<style>
		tbody tr {
			padding: 2px;
		}
			tbody tr:nth-child(odd) {
				background-color: #eee;
			}
	</style>

	<form method="post" action="/admin/video/orphaned-chyrons.php">
		<table>
			<thead>
				<tr>
					<th>Chyron</th>
					<th>#</th>
					<th>Legislator</th>
				</tr>
			<tbody>';
    while ($chyron = mysqli_fetch_array($result))
    {

        $chyron['url'] = str_replace(
            '/video/',
            'http://s3.amazonaws.com/video.richmondsunlight.com/',
            $chyron['url']
        );

        echo '<tr>
			<td><a href="' . $chyron['url'] . '" target="_new">' . stripslashes($chyron['raw_text']) . '</a></td>
			<td>' . $chyron['number'] . '</td>
			<td><select name="chyron[' . md5($chyron['raw_text']) . ']">' . $legislator_select
                . '</select></td>
			</tr>';

    }

    echo '</tbody></table>
		<input type="submit" name="submit" value="Submit" />
	</form>';
}

# If $_POST is set.
else
{

    foreach ($_POST['chyron'] as $chyron_md5 => $legislator_id)
    {

        if (empty($legislator_id))
        {
            continue;
        }

        # Ignore this chyron.
        if ($legislator_id == 'ignore')
        {
            $sql = 'UPDATE video_index
					SET ignored = "y"
					WHERE linked_id IS NULL
					AND type = "legislator"
					AND md5(raw_text) = "' . $chyron_md5 . '"';
        }

        # Associate this chyron with a given legislator.
        else
        {
            $sql = 'UPDATE video_index
					SET linked_id = ' . $legislator_id . '
					WHERE linked_id IS NULL
					AND type = "legislator"
					AND md5(raw_text) = "' . $chyron_md5 . '"';
        }

        $result = mysqli_query($GLOBALS['db'], $sql);
        if ($result === FALSE)
        {
            echo '<p>Error: Query failed. ' . $sql . '</p>';
        }

        echo '.';

    }

    echo '<p>Done.</p>';

}
