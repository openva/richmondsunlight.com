<?php

###
# Edit and Add New Videos
#
# PURPOSE
# Provides administrative video-editing functions.
#
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once 'settings.inc.php';
include_once 'functions.inc.php';

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database;
$database->connect_old();

# PAGE METADATA
$page_title = 'Videos';
$site_section = 'videos';

# CUSTOM FUNCTIONS
function show_form($form_data)
{
    $returned_data = '
		<form method="post" action="/admin/video/">

			<fieldset>
			<legend>Committee ID</legend>
			<input type="text" name="form_data[committee_id]" id="committee_id" size="3" value="'.$form_data['committee_id'].'" />
			</fieldset>

			<fieldset>
			<legend>Author Credit</legend>
			<input type="text" name="form_data[author_name]" id="author_name" value="'.$form_data['author_name'].'" />
			</fieldset>

			<fieldset>
			<legend class="required">Chamber</legend>
			<select name="form_data[chamber]" id="chamber">
				<option value="house"' . (($form_data['chamber'] == 'house') ? ' selected="selected"' : '') . '>House</option>
				<option value="senate"' . (($form_data['chamber'] == 'senate') ? ' selected="selected"' : '') . '>Senate</option>
			</select>
			</fieldset>

			<fieldset>
			<legend class="required">Title</legend>
			<input type="text" name="form_data[title]" id="title" value="'.$form_data['title'].'" />
			</fieldset>

			<fieldset>
			<legend class="required">Path (prepend with a slash, e.g. <code>/video/house/floor/20190204.mp4</code>))</legend>
			<input type="text" name="form_data[path]" id="path" size="60" value="'.$form_data['path'].'" />
			</fieldset>

			<fieldset>
			<legend>License</legend>
			<input type="text" name="form_data[license]" id="license" value="'.$form_data['license'].'" />
			</fieldset>

			<fieldset>
			<legend>Length (HH:MM:SS)</legend>
			<input type="text" name="form_data[length]" id="length" size="8" value="'.$form_data['length'].'" />
			</fieldset>

			<fieldset>
			<legend>FPS</legend>
			<input type="text" name="form_data[fps]" id="length" size="5" value="'.$form_data['fps'].'" />
			</fieldset>

			<fieldset>
			<legend>Capture Rate in Frame Frequency (i.e. 60, 150)</legend>
			<input type="text" name="form_data[capture_rate]" id="length" size="3" value="'.$form_data['capture_rate'].'" />
			</fieldset>

			<fieldset>
			<legend>Width</legend>
			<input type="text" name="form_data[width]" id="length" size="4" value="'.$form_data['width'].'" />
			</fieldset>

			<fieldset>
			<legend>Height</legend>
			<input type="text" name="form_data[height]" id="length" size="4" value="'.$form_data['height'].'" />
			</fieldset>

			<fieldset>
			<legend>Description</legend>
			<textarea name="form_data[description]" id="description" rows="5" cols="60">'.$form_data['description'].'</textarea>
			</fieldset>

			<fieldset>
			<legend>Embed HTML</legend>
			<textarea name="form_data[html]" id="html" rows="10" cols="60">'.$form_data['html'].'</textarea>
			</fieldset>

			<fieldset>
			<legend>Media Type</legend>
			<select name="form_data[type]" id="type">
				<option value="video"'.(($form_data['type'] == 'video') ? ' selected="selected"' : '').'>Video</option>
				<option value="audio"'.(($form_data['type'] == 'audio') ? ' selected="selected"' : '').'>Audio</option>
			</select>
			</fieldset>

			<fieldset>
			<legend class="required">Date Recorded (YYYY-MM-DD)</legend>
			<input type="text" name="form_data[date]" id="date" size="10" value="'.$form_data['date'].'" />
			</fieldset>

			<fieldset>
			<legend>Sponsor</legend>
			<textarea rows="4" cols="50" name="form_data[sponsor]" id="sponsor">'.$form_data['sponsor'].'</textarea>
			</fieldset>';
    if (isset($form_data['id']))
    {
        $returned_data .= '
			<input type="hidden" name="form_data[id]" id="id" value="'.$form_data['id'].'" />';
    }
    $returned_data .= '
			<input type="submit" name="submit" value="Save" />
		</form>';
    return $returned_data;
}

# PAGE CONTENT

$html_head = '
<style type="text/css">
	legend.required:after {
		content: " *";
		color: red;
	}
</style>';

# If a form is being submitted, we're saving new data or updating existing data, so go for it.
if (isset($_POST['form_data']))
{

    $video = new Video;
    $video->video = $_POST['form_data'];
    if ($video->submit() === FALSE)
    {
        die('Submitting video failed.');
    }

    # Redirect the browser to edit this video.
    header('Location: https://www.richmondsunlight.com/admin/video/?id=' . $video->id);
    exit();

}

/*
 * Edit a specific video.
 */
if (isset($_GET['id']) && !isset($_GET['op']))
{

    $id = $_GET['id'];
    $sql = 'SELECT id, chamber, committee_id, author_name, title, html, path, description,
			license, type, length, date, fps, capture_rate, width, height, sponsor
			FROM files
			WHERE id='.$id;
    $result = mysql_query($sql);
    if (mysql_num_rows($result) == 0)
    {
        die('No such file available.');
    }
    $video = mysql_fetch_array($result);

    $sql = 'SELECT *
			FROM video_index
			WHERE file_id='.$_GET['id'];
    $result = mysql_query($sql);
    if (mysql_num_rows($result) == 0)
    {
        $page_body .= '<p style="font-size: 1.5em; text-align: center;">
			<a href="/utilities/parse_video.php?id=' . $id . '">Parse Video</a></p>';
    }

    $page_body .= '
		<div style="float: right;" id="reimport">
		<form method="get" action="/admin/video/">
			<input type="hidden" name="op" value="metadata">
			<input type="hidden" name="path" value="' . $video['path'] . '">
			<input type="hidden" name="id" value="' . $video['id'] . '">
			<input type="submit" value="Reimport File Metadata">
		</form>
		</div>
	';
    $page_body .= show_form($video);

}

/*
 * Import an SRT into the database, cleaning it up some.
 */
elseif (isset($_GET['id']) && ($_GET['op'] == 'srt'))
{

    $sql = 'SELECT chamber, date
			FROM files
			WHERE id = ' . $_GET['id'];
    $result = mysql_query($sql);
    if (!$result)
    {
        die('No file of that ID is found.');
    }
    $file = mysql_fetch_array($result);
    $filename = $_SERVER['DOCUMENT_ROOT'] . 'video/' . $file['chamber'] . '/floor/' . str_replace('-', '', $file['date']) . '.srt';
    if (file_exists($filename) == FALSE)
    {
        die('No file found by the name ' . $filename);
    }
    if (is_readable($filename) == FALSE)
    {
        die($filename . ' is not readable by the web user.');
    }
    $captions = new Video;
    $captions->srt = file_get_contents($filename);
    $captions->normalize_line_endings();
    $captions->eliminate_duplicates();
    $captions->offset = -18;
    $captions->time_shift_srt();

    if (strlen($captions->srt) < 200)
    {
        die('Captions file is implausibly short.');
    }

    $sql = 'UPDATE files
			SET srt = "' . mysql_real_escape_string($captions->srt) . '"
			WHERE id = ' . $_GET['id'];
    $result = mysql_query($sql);
    if ($result === FALSE)
    {
        die('Captions could not be inserted');
    }
    else
    {
        header('Location: https://www.richmondsunlight.com/admin/video/');
        exit();
    }

}

/*
 * Turn SRT into a transcript.
 */
elseif (isset($_GET['id']) && ($_GET['op'] == 'transcript'))
{

    $sql = 'SELECT srt
			FROM files
			WHERE id = ' . $_GET['id'];
    $result = mysql_query($sql);
    if (!$result)
    {
        die('No file of that ID is found.');
    }
    $file = mysql_fetch_array($result);
    $file['srt'] = stripslashes($file['srt']);

    $captions = new Video;
    $captions->srt = $file['srt'];
    if ($captions->srt_to_transcript() === FALSE)
    {
        die('Could not generate transcript.');
    }
    if (empty($captions->transcript))
    {
        die('No captions generated');
    }

    $sql = 'UPDATE files
			SET transcript = "' . mysql_real_escape_string($captions->transcript) . '"
			WHERE id = ' . $_GET['id'];
    $result = mysql_query($sql);
    if ($result === FALSE)
    {
        die('Transcript could not be inserted.');
    }

    header('Location: https://www.richmondsunlight.com/admin/video/');
    exit();

}

/*
 * Atomize SRT contents into the video_transcript table.
 */
elseif (isset($_GET['id']) && ($_GET['op'] == 'atomize'))
{

    $sql = 'SELECT srt
			FROM files
			WHERE id = ' . $_GET['id'];
    $result = mysql_query($sql);
    if (!$result)
    {
        die('No file of that ID is found.');
    }
    $file = mysql_fetch_array($result);
    $file['srt'] = stripslashes($file['srt']);

    $captions = new Video;
    $captions->file_id = $_GET['id'];
    $captions->srt = $file['srt'];
    if ($captions->srt_to_database() === FALSE)
    {
        die('Could not atomize captions.');
    }

    header('Location: https://www.richmondsunlight.com/admin/video/');
    exit();

}

/*
 * Identify speakers for this video's captions.
 */
elseif (isset($_GET['id']) && ($_GET['op'] == 'identify'))
{

    $captions = new Video;
    $captions->file_id = $_GET['id'];
    if ($captions->identify_speakers() === FALSE)
    {
        die('Could not identify speakers.');
    }
    header('Location: https://www.richmondsunlight.com/admin/video/');
    exit();

}

/*
 * Generate clips for this video.
 */
elseif (isset($_GET['id']) && ($_GET['op'] == 'clips'))
{

    $video = new Video;
    $video->id = $_GET['id'];
    $video->store_clips();
    header('Location: https://www.richmondsunlight.com/admin/video/');
    exit();

}

/*
 * Reextract metadata about this file (e.g., after a file has been replaced with
 * a new one, such as a captured webstream being replaced with a DVD rip).
 */
elseif (isset($_GET['id']) && ($_GET['op'] == 'metadata') && isset($_GET['path']))
{

    /*
     * Get data about the file from MPlayer.
     */
    $video = new Video;
    $video->path = $_GET['path'];
    $video->extract_file_data();

    /*
     * Get our own metadata from the database.
     */
    $sql = 'SELECT id, chamber, committee_id, author_name, title, html, path, description,
			license, type, length, date, fps, capture_rate, width, height, sponsor
			FROM files
			WHERE id=' . $_GET['id'];
    $result = mysql_query($sql);
    $video_data = mysql_fetch_array($result);

    /*
     * Switch the fields in question to our updated values.
     */
    $fields = array('fps', 'width', 'height', 'length');
    foreach ($fields as $name)
    {
        $video_data[$name] = $video->$name;
    }

    $page_body .= show_form($video_data);

}

# Allow the entry of a new video.
elseif (isset($_GET['new']))
{
    $page_body .= show_form($_GET['form_data']);
}

# Display the opening page, which lists past videos.
else
{

    # Select all of the videos.
    $sql = 'SELECT id, chamber, capture_directory, committee_id, title, type, length, date, path,
			srt, transcript,
			(SELECT COUNT(*) FROM video_transcript WHERE file_id=files.id) AS atomized_count,
			(SELECT COUNT(*) FROM video_transcript WHERE file_id=files.id AND legislator_id IS NOT NULL) AS identified_count
			FROM files
			ORDER BY date DESC';
    $result = mysql_query($sql);
    if (mysql_num_rows($result) == 0)
    {
        die('No videos found.');
    }
    $videos = array();
    $video_paths = array();
    while ($video = mysql_fetch_array($result))
    {

        $videos[] = array_map('stripslashes', $video);
        # We save this to a separate array, which we use to detect newly uploaded files.
        if ($video['capture_directory'] != '')
        {
            $video_paths[] = $video['capture_directory'];
        }

    }

    # Get a list of all video files.
    foreach (array('house','senate') as $chamber)
    {

        $directory = '/video/' . $chamber . '/floor/';
        if ($fp = opendir($_SERVER['DOCUMENT_ROOT'] . $directory))
        {

            while (($filename = readdir($fp)) !== FALSE)
            {
                if (($filename == '.') || ($filename == '..'))
                {
                    continue;
                }
                if (is_dir($_SERVER['DOCUMENT_ROOT'] . $directory . $filename) === TRUE)
                {
                    $files[$chamber][] = $directory . $filename . '/';
                }
            }
            closedir($fp);

        }

        sort($files[$chamber]);

    }

    # Iterate through our file list to look for capture directories that have not yet been added
    # to the database.
    foreach ($files as $chamber)
    {

        foreach ($chamber as $file)
        {

            if (!in_array($file, $video_paths))
            {

                $tmp = explode('/', $file);
                $chamber = $tmp[2];
                $date = $tmp[4];
                $date = $date{0}.$date{1}.$date{2}.$date{3}.'-'.$date{4}.$date{5}.'-'.$date{6}.$date{7};
                $path = substr($file, 0, -1);
                $page_body .= '<a href="/admin/video/?new=&amp;';
                $page_body .= 'form_data[path]='.$path.'.mp4'
                .'&amp;form_data[chamber]='.$chamber.'&amp;form_data[date]='.$date;
                if (strstr($file, 'house') !== false)
                {
                    $page_body .= '&amp;form_data[title]=House+Session';
                }
                elseif (strstr($file, 'senate') !== false)
                {
                    $page_body .= '&amp;form_data[title]=Senate+Session';
                }
                $page_body .= '">'.$file
                .'</a> ';

            }

        }

    }

    # List all of the videos that we have in the database.
    $page_body .= '
	<p><a href="/admin/video/?new">Add New Video</a> |
	<a href="/utilities/resolve_chyrons.php">Resolve Chyrons</a> |
	<a href="/admin/video/orphaned-chyrons.php">Orphaned Chyrons</a> |
	<a href="/utilities/internet_archive_video.php">IA Export</a></p>

	<table>
		<thead>
		<tr>
			<th>Date</th>
			<th>Chamber</th>
			<th>Type</th>
			<th>Title</th>
			<th>Length</th>
			<th>Parse</th>
			<th>Captions</th>
			<th>Edit</th>
		</tr>
		</thead>
		<tbody>';

    foreach ($videos as $video)
    {

        $page_body .= '
			<tr>
				<td>' . $video['date'] . '</td>
				<td>' . $video['chamber'] . '</td>
				<td>' . $video['type'] . '</td>
				<td>' . $video['title'] . '</a></td>
				<td>' . $video['length'] . '</td>
				<td><a href="/utilities/resolve_chyrons.php?id=' . $video['id'] . '">redo</a></td>
				<td>';
        if (empty($video['srt']))
        {
            $page_body .= '<a href="?op=srt&id=' . $video['id'] . '" title="Store SRT in database">import</a>';
        }
        else
        {

            if ($video['atomized_count'] == 0)
            {
                $page_body .= '<a href="?op=atomize&id=' . $video['id'] . '" title="Break transcript into lines">atomize</a> ';
            }
            if ($video['identified_count'] == 0)
            {
                $page_body .= '<a href="?op=identify&id=' . $video['id'] . '" title="Identify speakers">ID</a>';
            }
            else
            {
                $page_body .= '<a href="?op=identify&id=' . $video['id'] . '" title="Identify speakers that were not previously IDed">Re-ID</a>';
            }

            $page_body .= '
				<a href="?op=clips&id=' . $video['id'] . '" title="Rebuild clips">re-clip</a>';

        }

        $page_body .= '</td>
				<td>[<a href="/admin/video/?id=' . $video['id'] . '">edit</a>]</td>
			</tr>';

    }

    $page_body .= '</tbody></table>';

}

# OUTPUT THE PAGE
$page = new Page;
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->html_head = $html_head;
$page->process();
