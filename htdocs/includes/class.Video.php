<?php

# Interaction with videos of the minutes.
class Video
{
    # Retrieve a single video.
    public function get_video()
    {
        if (!isset($this->id)) {
            return false;
        }

        $database = new Database();
        $database->connect_mysqli();

        $sql = 'SELECT id, committee_id, author_name, title, html, path, capture_directory,
				description, license, length, fps, capture_rate, sponsor, width, height, date,
				video_index_cache AS index_cache, transcript,
					(SELECT COUNT(*)
					FROM video_index
					WHERE file_id=files.id) AS index_data
				FROM files
				WHERE id=' . $this->id;
        $result = mysqli_query($GLOBALS['db'], $sql);
        if (mysqli_num_rows($result) > 0) {
            $tmp = mysqli_fetch_object($result);
            $tmp = array_map('stripslashes', (array)$tmp);
            foreach ($tmp as $key => $variable) {
                $this->$key = $variable;
            }
        }
    }

    # Add (or edit) a video.
    public function submit()
    {
        if (!isset($this->video)) {
            return false;
        }

        $database = new Database();
        $database->connect_mysqli();

        # Clean up the data.
        $this->video = array_map('stripslashes', $this->video);
        $this->video = array_map(function ($field) {
            return mysqli_real_escape_string($GLOBALS['db'], $field);
        }, $this->video);

        # When in doubt, the video is public domain.
        if (empty($this->video['license'])) {
            $this->video['license'] = 'public domain';
        }

        # Check whether we have this exact video saved already. If so, we'll just update it.
        $sql = 'SELECT
                    id
				FROM files
				WHERE
                    chamber="' . $this->video['chamber'] . '" AND
				    date="' . $this->video['date'] . '" AND
				    length="' . $this->video['length'] . ' "';
        $result = mysqli_query($GLOBALS['db'], $sql);
        if (mysqli_num_rows($result) > 0) {
            $file = mysqli_fetch_array($result);
            $this->video['id'] = $file['id'];
        }

        # Assemble the SQL string.
        if (isset($this->video['id'])) {
            $sql = 'UPDATE files';
        } else {
            $sql = 'INSERT INTO files';
        }
        $sql .= '
				SET
                    chamber="' . $this->video['chamber'] . '",
				    title="' . $this->video['title'] . '",
				    type="video",
				    date="' . $this->video['date'] . '",
				    length="' . $this->video['length'] . '"';
        if (!empty($this->video['committee_id'])) {
            $sql .= ', committee_id=' . $this->video['committee_id'];
        }
        if (!empty($this->video['author_name'])) {
            $sql .= ', author_name=' . $this->video['author_name'] . '"';
        }
        if (!empty($this->video['html'])) {
            $sql .= ', html="' . $this->video['html'] . '"';
        } else {
            $sql .= ', html = NULL';
        }
        if (!empty($this->video['path'])) {
            $sql .= ', path="' . $this->video['path'] . '"';
        }
        if (!empty($this->video['fps'])) {
            $sql .= ', fps="' . $this->video['fps'] . '"';
        }
        if (!empty($this->video['capture_rate'])) {
            $sql .= ', capture_rate="' . $this->video['capture_rate'] . '"';
        }
        if (!empty($this->video['capture_directory'])) {
            $sql .= ', capture_directory="' . $this->video['capture_directory'] . '"';
        }
        if (!empty($this->video['width'])) {
            $sql .= ', width="' . $this->video['width'] . '"';
        }
        if (!empty($this->video['height'])) {
            $sql .= ', height="' . $this->video['height'] . '"';
        }
        if (!empty($this->video['description'])) {
            $sql .= ', description="' . $this->video['description'] . '"';
        }
        if (!empty($this->video['license'])) {
            $sql .= ', license="' . $this->video['license'] . '"';
        }
        if (!empty($this->video['sponsor'])) {
            $sql .= ', sponsor="' . $this->video['sponsor'] . '"';
        }
        if (isset($this->video['id'])) {
            $sql .= ' WHERE id=' . $this->video['id'];
        } else {
            $sql .= ', date_created=now()';
        }

        # Perform the database query.
        $result = mysqli_query($GLOBALS['db'], $sql);

        # If the query fails, complain,
        if (!$result) {
            return false;
        }

        # Grab the DB ID to use in the HTTP redirect below.
        if (isset($this->video['id'])) {
            $this->id = $this->video['id'];
        } else {
            $this->id = mysqli_insert_id($GLOBALS['db']);
        }

        return true;
    }


    # Get vital stats about this video via MPlayer and the filesystem.
    public function extract_file_data()
    {
        exec('/usr/bin/mplayer -ao null -vo null -identify -frames 0 ' . CLI_ROOT
            . $this->path, $mplayer);

        foreach ($mplayer as $option) {
            if (mb_strpos($option, '=') !== false) {
                $tmp = explode('=', $option);
                $tmp[0] = mb_strtolower($tmp[0]);
                $newoptions[$tmp[0]] = $tmp[1];
            }
        }
        $mplayer = $newoptions;
        unset($tmp);
        $this->fps = $mplayer['id_video_fps'];
        $this->width = $mplayer['id_video_width'];
        $this->height = $mplayer['id_video_height'];
        $this->length = seconds_to_time($mplayer['id_length']);

        if (empty($this->capture_rate) && !empty($this->capture_directory)) {
            $dir = scandir(CLI_ROOT . '/video/' . $this->capture_directory, 1);
            if ($dir == false) {
                return false;
            }
            $largest = $dir[0];
            $largest = explode('.', $largest);
            $largest = round($largest[0]);
            $this->capture_rate = round(($mplayer['id_length'] * $mplayer['id_video_fps']) / $largest);
        }

        return true;
    }


    # Get a sampling of five videos, at random, for a given legislator. Each must be less than five
    # minutes in length.
    public function legislator_sample()
    {
        if (!isset($this->legislator_id)) {
            return false;
        }
        $start = microtime(true);

        $database = new Database();
        $database->connect_mysqli();

        /*$sql = 'SELECT DISTINCT

                    (SELECT TIME_TO_SEC(MIN(time))
                    FROM video_index AS vi2
                    WHERE vi2.file_id=video_index.file_id AND vi2.linked_id=video_index.linked_id
                    AND vi2.file_id=video_index.file_id) AS start,

                    (SELECT TIME_TO_SEC(MAX(time))
                    FROM video_index AS vi2
                    WHERE vi2.file_id=video_index.file_id AND vi2.linked_id=video_index.linked_id
                    AND vi2.file_id=video_index.file_id) AS end,

                files.path, files.chamber, files.date
                FROM video_index
                LEFT JOIN files
                    ON video_index.file_id=files.id
                WHERE video_index.type="legislator" AND video_index.linked_id=' . $this->legislator_id . '
                HAVING start != end AND ( (end - start) < (60 * 5) )
                ORDER BY RAND()
                LIMIT 5';*/
        $sql = 'SELECT video_clips.time_start AS start, video_clips.time_end AS end,
					files.path, files.chamber, files.date
				FROM video_clips
				LEFT JOIN files
					ON video_clips.file_id = files.id
				WHERE legislator_id = ' . $this->legislator_id . '
				AND ( (time_end - time_start) < (60 * 50) )
				ORDER BY RAND()
                LIMIT 5';
        $result = mysqli_query($GLOBALS['db'], $sql);
        if (mysqli_num_rows($result) == 0) {
            return false;
        }
        $clips = array();
        while ($clip = mysqli_fetch_array($result)) {
            # Pad our numbers a little.
            $clip['start'] = $clip['start'] - 9;
            $clip['end'] = $clip['end'] + 9;
            $clip['duration'] = $clip['end'] - $clip['start'];
            $clips[] = $clip;
        }
        return $clips;
    }

    public function by_legislator()
    {
        if (!isset($this->legislator_id)) {
            return false;
        }

        $database = new Database();
        $database->connect_mysqli();

        $sql = 'SELECT files.id, files.path, files.date, files.chamber, files.capture_directory,
				video_clips.legislator_id, video_clips.bill_id, video_clips.time_start,
				video_clips.time_end, video_clips.screenshot
				FROM video_clips
				LEFT JOIN files
					ON video_clips.file_id=files.id
				WHERE legislator_id = ' . $this->legislator_id . '
				ORDER BY files.date ASC, video_clips.time_start ASC';

        $result = mysqli_query($GLOBALS['db'], $sql);
        if (mysqli_num_rows($result) == 0) {
            return false;
        } else {
            if (!isset($this->clips)) {
                $this->clips = new stdClass();
            }
            $i = 0;
            while ($clip = mysqli_fetch_object($result)) {
                $this->clips->{$i} = new stdClass();
                $this->clips->{$i}->path = $clip->path;
                $this->clips->{$i}->date = $clip->date;
                $this->clips->{$i}->chamber = $clip->chamber;
                $this->clips->{$i}->screenshot = str_replace('/video/', 'https://s3.amazonaws.com/video.richmondsunlight.com/', $clip->screenshot);
                $this->clips->{$i}->start = time_to_seconds($clip->time_start);
                $this->clips->{$i}->end = time_to_seconds($clip->time_end);
                $this->clips->{$i}->duration = time_to_seconds($clip->time_end) - time_to_seconds($clip->time_start);
                $i++;
            }
        }
    }

    # Get all of the video clips, in order, that involve this bill.
    public function by_bill()
    {
        if (!isset($this->bill_id)) {
            return false;
        }

        $database = new Database();
        $database->connect_mysqli();

        $sql = 'SELECT files.id, files.path, files.capture_directory, files.date, files.chamber,
				files.capture_rate, video_index.time, video_index.screenshot
				FROM video_index
				LEFT JOIN files
					ON video_index.file_id = files.id
				WHERE video_index.linked_id=' . $this->bill_id . ' AND video_index.type="bill"
				ORDER BY files.date ASC, files.chamber ASC, video_index.time ASC';
        $result = mysqli_query($GLOBALS['db'], $sql);
        if (mysqli_num_rows($result) == 0) {
            return false;
        } else {
            while ($moment = mysqli_fetch_array($result, MYSQL_ASSOC)) {
                $index[] = $moment;
            }

            # Iteratively reduce the array to just the beginning and end of each segment. We start
            # at position 1, rather than 0, since this is a comparative operation.
            $index2 = array();
            $index2[] = $index[0];
            for ($i = 1; $i < count($index); $i++) {
                # If this isn't the same chamber and date as the prior position, or the timestamp
                # isn't within 30 seconds after the prior position, then save this and the prior
                # position.
                if (
                        ($index[$i]['chamber'] != $index[$i - 1]['chamber'])
                        ||
                        ($index[$i]['date'] != $index[$i - 1]['date'])
                        ||
                        ((time_to_seconds($index[$i]['time']) - time_to_seconds($index[$i - 1]['time'])) > 30)
                ) {
                    $index2[] = $index[$i - 1];
                    $index2[] = $index[$i];
                }

                # If this is the last item in the array, save it, since it's the end of our current
                # segment.
                elseif (($i + 1) == count($index)) {
                    $index2[] = $index[$i];
                }
            }

            # In the unlikely event that we have nothing left.
            if (count($index2) == 0) {
                return false;
            }

            # If we've saved an odd number of frames, then drop the last one. We really shouldn't
            # have done that, and presumably it's indictive of larger problems, but what the heck?
            if (((count($index2) + 1) % 2) == 0) {
                $index2 = array_slice($index2, 0, -1);
            }

            # At this point we have a listing of points in time that bracket each segment about
            # this bill, one for the beginning of each segment and one for the end. Now we need to
            # combine every pair into a single array element.
            $clips = array();
            for ($i = 0; $i < count($index2); $i++) {
                # If this is an odd number.
                if (($i != 0) && ((($i + 1) % 2) == 0)) {
                    $clips[] = array(
                        'file_id' => $index2[$i]['id'],
                        'path' => $index2[$i]['path'],
                        'date' => $index2[$i]['date'],
                        'chamber' => $index2[$i]['chamber'],
                        'screenshot' => str_replace(
                            '/video/',
                            'https://s3.amazonaws.com/video.richmondsunlight.com/',
                            $index2[$i]['capture_directory']
                        ) . $index2[$i]['screenshot'] . '.jpg',
                        'start' => time_to_seconds($index2[$i - 1]['time']) - 10,
                        'end' => time_to_seconds($index2[$i]['time']) + 10,
                        'duration' => time_to_seconds($index2[$i]['time']) - time_to_seconds($index2[$i - 1]['time']) + 20
                    );
                }
            }

            return $clips;
        }
    }

    # Get return an array of tag data for a given video file, scaled on the basis of the amount of
    # time that is spent discussing each topic.
    public function file_tags()
    {
        if (!isset($this->id)) {
            return false;
        }

        $database = new Database();
        $database->connect_mysqli();

        # Generate a list of all tags applied to this file, getting each tag for every screenshot
        # that we have. So if we have ten screenshots of a bill tagged with "business," that would
        # be ten appearances of that tag.
        $sql = 'SELECT tags.tag, COUNT(*) AS number
				FROM video_index
				LEFT JOIN bills ON video_index.linked_id = bills.id
				LEFT JOIN tags ON bills.id = tags.bill_id
				WHERE video_index.type = "bill"
				AND video_index.file_id = ' . $this->id . '
				AND video_index.linked_id IS NOT NULL
				AND tags.tag IS NOT NULL
				GROUP BY tag
				ORDER BY number';
        $result = mysqli_query($GLOBALS['db'], $sql);

        # Unless we have ten tags, we just don't have enough data to continue.
        if (mysqli_num_rows($result) < 10) {
            return false;
        }

        # Build up an array of tags, with the key being the tag and the value being the count.
        while ($tag = mysqli_fetch_array($result)) {
            $tag = array_map('stripslashes', $tag);
            $tags[$tag{'tag'}] = $tag['number'];
        }

        # Sort the tags in reverse order by key (their count), shave off the top 30, and then
        # resort alphabetically.
        arsort($tags);
        $tags = array_slice($tags, 0, 30, true);
        $tag_data['biggest'] = max(array_values($tags));
        $tag_data['smallest'] = min(array_values($tags));
        ksort($tags);

        return $tags;
    }

    # Get a list of screenshots, one for each X seconds of video. (Default is 60.)
    public function screenshots()
    {
        if (!isset($this->id)) {
            return false;
        }
        if (!isset($this->frequency)) {
            $this->frequency = 60;
        }

        $increment = $this->frequency / round($this->capture_rate / $this->fps);
        $tmp = array_reverse(explode(':', $this->length));
        $this->length_in_seconds = 0;
        for ($i = 0; $i < count($tmp); $i++) {
            if ($i === 0) {
                $this->length_in_seconds = $this->length_in_seconds + $tmp[$i];
            } elseif ($i === 1) {
                $this->length_in_seconds = $this->length_in_seconds + ($tmp[$i] * 60);
            } elseif ($i === 2) {
                $this->length_in_seconds = $this->length_in_seconds + ($tmp[$i] * 60 * 60);
            }
        }

        $this->total_screenshots = floor($this->length_in_seconds * $this->fps / $this->capture_rate);

        # Build up a list of screenshots.
        $j = 0;
        $i = 1;
        while ($i < $this->total_screenshots) {
            if (!isset($this->screenshots)) {
                $this->screenshots = new stdClass();
            }
            if (!isset($this->screenshots->{$j})) {
                $this->screenshots->{$j} = new stdClass();
            }
            $this->screenshots->{$j}->number = $j;
            $this->screenshots->{$j}->seconds = round($j * $this->frequency);
            $this->screenshots->{$j}->filename = str_replace('/video/', 'https://s3.amazonaws.com/video.richmondsunlight.com/', $this->capture_directory)
                . str_pad($i, 8, '0', STR_PAD_LEFT) . '.jpg';
            $j++;
            $i = $i + $increment;
        }
    } // end method file_tags()


    # Indexes video clips by legislator and bill. Meant to be called by the store_clips() method.
    public function index_clips()
    {

        # We must have a file ID.
        if (!isset($this->id)) {
            return false;
        }

        # Are we seeking clips based on bills or legislators?
        if (!isset($this->clip_type)) {
            $this->clip_type = 'bills';
        }

        # By how many seconds should exerpts be fuzzed? "10" would provide 10 seconds of padding
        # both before and after a clip -- a total of 20 extra seconds of video bookending the
        # identified clip.
        if (!isset($this->fuzz)) {
            $this->fuzz = 0;
            $this->fuzz_default = $this->fuzz;
        }

        $database = new Database();
        $database->connect_mysqli();

        # Generates a list of every moment.
        $sql = 'SELECT files.path, files.capture_directory, files.date, files.chamber,
				video_index.time,
				CONCAT(files.capture_directory, video_index.screenshot, ".jpg") AS screenshot,
				video_index.linked_id, ';
        if ($this->clip_type == 'bills') {
            $sql .= 'bills.number AS bill_number';
        } elseif ($this->clip_type == 'legislators') {
            $sql .= 'representatives.name_formatted AS legislator_name';
        }
        $sql .= '
				FROM video_index
				LEFT JOIN files
					ON video_index.file_id = files.id';
        if ($this->clip_type == 'bills') {
            $sql .= '
				LEFT JOIN bills
					ON video_index.linked_id = bills.id
				WHERE video_index.type="bill"';
        } elseif ($this->clip_type == 'legislators') {
            $sql .= '
				LEFT JOIN representatives
					ON video_index.linked_id = representatives.id
				WHERE video_index.type="legislator"';
        }

        $sql .= ' AND files.id=' . $this->id . '
				AND video_index.linked_id IS NOT NULL
				ORDER BY video_index.time ASC';

        $result = mysqli_query($GLOBALS['db'], $sql);
        if (mysqli_num_rows($result) == 0) {
            return false;
        }

        # Build up an array of "moments" -- each moment deriving from a single screenshot with
        # a chyron.
        while ($moment = mysqli_fetch_array($result, MYSQL_ASSOC)) {
            $moments[] = $moment;
        }

        # Iteratively reduce the array to just the beginning and end of each segment. We start
        # at position 1, rather than 0, since this is a comparative operation. (That is, we start by
        # comparing 1 to 0, since we don't have anything to compare 0 to.)
        $index = array();
        $index[] = $moments[0];
        for ($i = 1; $i < count($moments); $i++) {
            # Extract the prior match.
            $tmp = end($index);
            $last_match = $tmp['linked_id'];

            # If this is the last item in the array, save it, since it's the end of our current segment.
            if (($i + 1) == count($moments)) {
                $index[] = $moments[$i];
                break;
            }

            # If this linked ID is the same as the last one that we matched, then carry on.
            if ($moments[$i]['linked_id'] == $last_match) {
                continue;
            }

            # Else if this linked ID different than the last one that we matched, then we're at a
            # boundary between speakers.
            else {
                $index[] = $moments[$i - 1];
                $index[] = $moments[$i];
            }
        }

        # Eliminate $moments, since it's no longer needed.
        unset($moments);

        # In the unlikely event that we've iteratively reduced $moments to nothing at all.
        if (count($index) == 0) {
            return false;
        }

        # If we've saved an odd number of frames, then drop the last one. We really shouldn't
        # have done that, and presumably it's indictive of larger problems, but what the heck?
        if (((count($index) + 1) % 2) == 0) {
            $index = array_slice($index, 0, -1);
        }

        # Create a new, empty object to store these clips in.
        $this->clips = new stdClass();

        # At this point we have a list of points in time that bracket each segment about this bill,
        # one for the beginning of each segment and one for the end. Now we need to combine every
        # pair into a single array element.
        $j = 0;
        for ($i = 0; $i < count($index); $i++) {
            # If this is an odd number.
            if (($i != 0) && ((($i + 1) % 2) == 0)) {
                # If the beginning and end of this clip are the exact same time, then we obviously
                # need to arrange for a clip that's longer than a single moment. Stretch it out to
                # thirty seconds.
                if (
                    (time_to_seconds($index[$i - 1]['time']) === time_to_seconds($index[$i]['time']))
                    &&
                    ($this->fuzz === 0)
                ) {
                    $this->fuzz = 15;
                }

                # Otherwise make sure that we've set the fuzz level to the default.
                else {
                    $this->fuzz = $this->fuzz_default;
                }

                $clip = array(
                    'path' => $index[$i]['path'],
                    'date' => $index[$i]['date'],
                    'chamber' => $index[$i]['chamber'],
                    'screenshot' => str_replace('/video/', 'https://s3.amazonaws.com/video.richmondsunlight.com/', $index[$i]['screenshot']),
                    'start' => time_to_seconds($index[$i - 1]['time']) - $this->fuzz,
                    'end' => time_to_seconds($index[$i]['time']) + $this->fuzz,
                    'duration' => time_to_seconds($index[$i]['time']) - time_to_seconds($index[$i - 1]['time']) + ($this->fuzz * 2),
                    'linked_id' => $index[$i]['linked_id'],
                    'bill_number' => mb_strtoupper($index[$i]['bill_number']),
                    'legislator_name' => $index[$i]['legislator_name']
                );

                $this->clips->{$j} = (object)$clip;
            }

            $j++;
        }

        return true;
    }

    # Indexes and stores clips.
    public function store_clips()
    {

        # Create a counter, so that we can report the total number of clips indexed and stored.
        $this->clip_count = 0;

        # We must have a file ID.
        if (!isset($this->id)) {
            return false;
        }

        $database = new Database();
        $database->connect_mysqli();

        # First, remove every clip already stored for this file.
        $sql = 'DELETE FROM video_clips
				WHERE file_id = ' . $this->id;
        mysqli_query($GLOBALS['db'], $sql);

        # Get a list of all bill clips.
        $this->clip_type = 'bills';
        $this->index_clips();

        # If there no clips were identified by index_clips(), then we're done here.
        if (!isset($this->clips) || count($this->clips) == 0) {
            return false;
        }

        # Increment our counter.
        $this->clip_count = $this->clip_count + count($this->clips);

        foreach ($this->clips as $clip) {
            $sql = 'INSERT INTO video_clips
					SET bill_id = ' . $clip->linked_id . ',
					file_id = ' . $this->id . ',
					time_start = "' . seconds_to_time($clip->start, true) . '",
					time_end = "' . seconds_to_time($clip->end, true) . '",
					screenshot = "' . $clip->screenshot . '",
					date_created = now()';
            mysqli_query($GLOBALS['db'], $sql);
        }

        # Get a list of all legislators clips.
        $this->clip_type = 'legislators';
        $this->index_clips();

        # Increment our counter.
        $this->clip_count = $this->clip_count + count($this->clips);

        foreach ($this->clips as $clip) {
            # If the legislator was talking about a bill (as opposed to, for instance, introducing
            # a visitor in the gallery), gather that bill ID. We actually generate a list of all
            # bills that were discussed within the prescribed time range, but only retrieve the one
            # that was discussed the most.
            $sql = 'SELECT linked_id AS id, COUNT(*) AS number
					FROM video_index
					WHERE file_id =' . $this->id . ' AND type="bill"
					AND TIME >= "' . seconds_to_time($clip->start, true) . '"
					AND TIME <= "' . seconds_to_time($clip->end, true) . '"
					GROUP BY linked_id
					ORDER BY number DESC
					LIMIT 1';
            $result = mysqli_query($GLOBALS['db'], $sql);
            if (mysqli_num_rows($result) === 1) {
                $bill = mysqli_fetch_array($result);
                $clip->bill_id = $bill['id'];
            }

            $sql = 'INSERT INTO video_clips
					SET legislator_id = ' . $clip->linked_id . ',
					file_id = ' . $this->id . ',
					time_start = "' . seconds_to_time($clip->start, true) . '",
					time_end = "' . seconds_to_time($clip->end, true) . '",
					screenshot = "' . $clip->screenshot . '",
					date_created = now()';

            if (isset($clip->bill_id)) {
                $sql .= ', bill_id = ' . $clip->bill_id;
            }

            mysqli_query($GLOBALS['db'], $sql);
        }

        return true;
    }


    # Get a single clip.
    public function get_clip()
    {

        /*
         * We accept either an ID or an MD5 hash of the ID. Note that we don't use the entire MD5
         * hash (we don't even accept a complete MD5 hash), but instead just the first 6 characters.
         */
        if (!isset($this->id) && !isset($this->hash)) {
            return false;
        }

        $database = new Database();
        $database->connect_mysqli();

        $sql = 'SELECT files.path, files.date, DATE_FORMAT(files.date, "%b %e, %Y") AS date_formatted,
				representatives.name_formatted AS legislator_name, bills.number AS bill_number,
				video_clips.bill_id, video_clips.time_start, video_clips.time_end, video_clips.screenshot
				FROM video_clips
				LEFT JOIN files
					ON video_clips.file_id = files.id
				LEFT JOIN representatives
					ON video_clips.legislator_id = representatives.id
				LEFT JOIN bills
					ON video_clips.bill_id = bills.id
				WHERE ';
        if (isset($this->hash)) {
            $sql .= 'SUBSTRING(MD5(video_clips.id), 1, 6) = "' . $this->hash . '"';
        } elseif (isset($this->id)) {
            $sql .= 'video_clips.file_id = ' . $this->id;
        }

        $result = mysqli_query($GLOBALS['db'], $sql);

        if (($result == false) || (mysqli_num_rows($result) == 0)) {
            return false;
        }

        $this->clip = mysqli_fetch_object($result);
        $this->clip->time_start_seconds = time_to_seconds($this->clip->time_start);
        $this->clip->time_end_seconds = time_to_seconds($this->clip->time_end);
        $this->clip->duration_seconds = $this->clip->time_end_seconds - $this->clip->time_start_seconds;
        $this->clip->title = $this->clip->legislator_name . ' Speaking about '
            . mb_strtoupper($this->clip->bill_number) . ' on ' . $this->clip->date_formatted;
        if (mb_substr($this->clip->screenshot, 0, 2) == '//') {
            $this->clip->screenshot = 'https:' . $this->clip->screenshot;
        }

        return true;
    }


    # Get all clips for a given file ID.
    public function get_clips()
    {
        if (!isset($this->id) || !isset($this->clip_type)) {
            return false;
        }

        # If a fuzz time has not been established, set it at 5 seconds.
        if (!isset($this->fuzz)) {
            $this->fuzz = 5;
        }

        $database = new Database();
        $database->connect_mysqli();

        if ($this->clip_type == 'legislators') {
            $sql = 'SELECT representatives.name_formatted AS legislator_name, video_clips.time_start,
					video_clips.time_end, video_clips.screenshot
					FROM video_clips
					LEFT JOIN representatives
						ON video_clips.legislator_id = representatives.id
					WHERE legislator_id IS NOT NULL AND video_clips.file_id=' . $this->id;
        } elseif ($this->clip_type == 'bills') {
            $sql = 'SELECT bills.number AS bill_number, video_clips.time_start, video_clips.time_end,
					video_clips.screenshot
					FROM video_clips
					LEFT JOIN bills
						ON video_clips.bill_id = bills.id
					WHERE bill_id IS NOT NULL AND legislator_id IS NULL
					AND video_clips.file_id=' . $this->id;
        } else {
            $sql = 'SELECT representatives.name_formatted AS legislator_name,
					bills.number AS bill_number,
					video_clips.bill_id, video_clips.time_start, video_clips.time_end,
					video_clips.screenshot
					FROM video_clips
					LEFT JOIN representatives
						ON video_clips.legislator_id = representatives.id
					LEFT JOIN bills
						ON video_clips.bill_id = bills.id
					WHERE video_clips.file_id=' . $this->id;
        }

        $result = mysqli_query($GLOBALS['db'], $sql);

        if (mysqli_num_rows($result) < 1) {
            return false;
        }

        # Create a new, empty object to store these clips in.
        $this->clips = new stdClass();

        $i = 0;
        while ($clip = mysqli_fetch_object($result)) {
            $clip->start = time_to_seconds($clip->time_start) - $this->fuzz;
            $clip->end = time_to_seconds($clip->time_end) + $this->fuzz;
            $clip->duration = time_to_seconds($clip->time_end) - time_to_seconds($clip->time_start) + ($this->fuzz * 2);

            $this->clips->{$i} = $clip;
            $i++;
        }

        return true;
    }


    # Turn an SBV file into an object of times and text. Expects to receive raw SBV text, not a file
    # path.
    public function parse_sbv()
    {
        if (!isset($this->sbv) || empty($this->sbv)) {
            return false;
        }

        # Intialize a variable to store our complete transcript.
        $this->complete = '';

        # YouTube's SBVs quite frequently contain whitespace at the end.
        $this->sbv = trim($this->sbv);

        # Set aside the raw SBV data.
        $this->raw_sbv = $this->sbv;

        # Turn the raw data into an array.
        $this->sbv = explode('-----', $this->sbv);

        # Step through every moment in the array.
        $i = 0;
        foreach ($this->sbv as $moment) {
            # Each moment is bracketed in newlines. Strip those out.
            $moment = trim($moment);

            # Break the moment up into individual lines.
            $moment = explode(PHP_EOL, $moment);

            $this->moments->$i->time_start = implode(array_slice(explode(',', $moment[0]), 0, 1));
            $this->moments->$i->time_end = implode(array_slice(explode(',', $moment[0]), 1, 1));
            $this->moments->$i->text = implode(' ', array_slice($moment, 1));

            # Append the text to our master transcript of text.
            $this->transcript .= $this->moments->$i->text . ' ';

            $i++;
        }

        # Restore the transcript to its original variable.
        $this->sbv = $this->sbv_raw;
        unset($this->sbv_raw);

        return true;
    }


    /*
     * Turn a WebVTT file into an object of times and text. Expects to receive raw WebVTT text, not
     * a file path.
     */
    public function parse_webvtt()
    {
        if (empty($this->webvtt)) {
            return false;
        }

        /*
         * Turn the raw WebVTT data into an array.
         */
        $this->webvtt = trim($this->webvtt);
        $this->webvtt = explode("\n\n", $this->webvtt);

        /*
         * Store the resulting data here.
         */
        $this->captions = new stdClass();

        /*
         * Step through every caption, one by one.
         */
        $i = 0;
        foreach ($this->webvtt as $caption) {
            /*
             * If there's no time range, skip this one.
             */
            if (mb_strpos($caption, '-->') === false) {
                continue;
            }

            $caption = trim($caption);
            $caption = explode("\n", $caption);

            $this->captions->$i = new stdClass();
            $this->captions->$i->time_start = implode(array_slice(explode(' --> ', $caption[0]), 0, 1));
            $this->captions->$i->time_end = implode(array_slice(explode(' --> ', $caption[0]), 1, 1));
            $this->captions->$i->text = str_replace("\n", ' ', implode(' ', array_slice($caption, 1)));

            $i++;
        }

        return true;
    }


    /*
     * Store a WebVTT in the database.
     */
    public function store_webvtt()
    {
        if (!isset($this->file_id) || !isset($this->webvtt)) {
            return false;
        }

        $database = new Database();
        $database->connect_mysqli();

        $sql = 'UPDATE files
				SET webvtt = "' . mysqli_real_escape_string($GLOBALS['db'], $this->webvtt) . '"
				WHERE id=' . $this->file_id;
        $result = mysqli_query($GLOBALS['db'], $sql);
        if ($result === false) {
            return false;
        }

        return true;
    }

    # Generate a merged array of transcript text and clips.
    // THIS IS JUST A PROOF OF CONCEPT. There's much more to be done. This needs lots of debugging
    // before being put into production.
    public function transcript_indexed()
    {
        if (!isset($this->id)) {
            return false;
        }

        $database = new Database();
        $database->connect_mysqli();

        $this->transcript = new stdClass();

        # SELECT A LIST OF EVERY TRANSCRIPT ITEM, BY TIME.
        $sql = 'SELECT time_start, time_end, text
				FROM video_transcript
				WHERE file_id = ' . $this->id . '
				ORDER BY time_start ASC, time_end ASC';
        $result = mysqli_query($GLOBALS['db'], $sql);
        while ($caption = mysqli_fetch_object($result)) {
            $caption->time_start = time_to_seconds($caption->time_start);
            $caption->time_end = time_to_seconds($caption->time_end);
            $key = $caption->time_start;
            // THIS IS ACTUALLY A BAD IDEA. We'll miss some data by using the timestamp as the key.
            $this->transcript->$key = $caption;
        }

        # SELECT A LIST OF EVERY SPEAKER AND BILL, BY TIME
        $sql = 'SELECT representatives.name_formatted AS legislator, bills.number AS bill,
				video_clips.time_start, video_clips.time_end
				FROM video_clips
				LEFT JOIN representatives
					ON video_clips.legislator_id = representatives.id
				LEFT JOIN bills
					ON video_clips.bill_id = bills.id
				WHERE video_clips.file_id = ' . $this->id . '
				ORDER BY time_start ASC, time_end ASC';
        $result = mysqli_query($GLOBALS['db'], $sql);
        while ($clip = mysqli_fetch_object($result)) {
            $clip->time_start = time_to_seconds($clip->time_start) - 5;
            $clip->time_end = time_to_seconds($clip->time_end);
            $key = $clip->time_start;
            // THIS IS ACTUALLY A BAD IDEA. We'll miss some data by using the timestamp as the key.
            $this->transcript->$key = $clip;
        }
        $this->transcript = (array) $this->transcript;
        ksort($this->transcript);
        $this->transcript = (object) $this->transcript;

        return true;
    }


    ////////////////////////////////////////////////////////////////////////
    /*
     * All of the below was created separately, and has not been merged into
     * the rest of the class methods. They probably replace some of the above
     * methods, because the above methods were created for YouTube transcripts,
     * while the below were created for DVD captions.
     */
    ////////////////////////////////////////////////////////////////////////

    /**
     * Normalize a WebVTT file's carriage returns.
     *
     * @param string $this->webvtt, the WebVTT file
     *
     * @return true or false
     */
    public function normalize_line_endings()
    {

        /*
         * Require transcript text.
         */
        if (!isset($this->webvtt)) {
            return false;
        }

        $this->webvtt = preg_replace('~\R~u', "\n", $this->webvtt);

        return true;
    }

    /**
     * Adjust captions by X seconds
     *
     * The default is +10.
     *
     * @param int    $this->offset, in seconds, defaults to 10
     * @param string $this->captions, the captions
     *
     * @return true or false
     */
    public function time_shift_srt()
    {

        /*
         * Require transcript text.
         */
        if (!isset($this->captions)) {
            return false;
        }

        /*
         * Require an offset time, in seconds.
         */
        if (!isset($this->offset)) {
            $this->offset = 10;
        }

        /*
         * Step through each of the captions.
         */
        foreach ($this->captions as &$caption) {
            /*
             * Step through the two timestamps.
             */
            $times = array('time_start', 'time_end');
            foreach ($times as &$time) {
                /*
                 * Convert the time to seconds (dropping microseconds).
                 */
                $caption->{$time} = preg_replace("/^([\d]{2})\:([\d]{2})\:([\d]{2}),([\d]{3})$/", "$1:$2:$3.$4", $caption->{$time});
                sscanf($caption->time_start, "%d:%d:%d.%d", $hours, $minutes, $seconds, $microseconds);
                $caption->{$time} = $hours * 3600 + $minutes * 60 + $seconds;

                /*
                 * Adjust the timestamp by the prescribed number of seconds.
                 */
                $caption->{$time} = $caption->{$time} + $this->offset;

                /*
                 * Format the seconds as HH:MM:SS again.
                 */
                $caption->{$time} = gmdate("H:i:s", $caption->{$time}) . '.' . $microseconds;
            }
        }

        return true;
    }

    /**
     * Load captions into the database
     *
     * Given captions, load each one into the database.
     *
     * @param string $this->captions The captions.
     * @param int $this->file_id The ID of this video file.
     *
     * @access public
     *
     * @return true or false
     */
    public function captions_to_database()
    {

        /*
         * Require transcript text.
         */
        if (!isset($this->captions)) {
            return false;
        }

        /*
         * Require a file ID.
         */
        if (!isset($this->file_id)) {
            return false;
        }

        /*
         * Don't accept suspiciously small numbers of captions.
         */
        if (count((array)$this->captions) <= 1) {
            return false;
        }

        $database = new Database();
        $database->connect_mysqli();

        /*
         * Delete all existing text for this video (which there may or may not be already).
         */
        $sql = 'DELETE FROM video_transcript
				WHERE file_id=' . $this->file_id;
        mysqli_query($GLOBALS['db'], $sql);

        /*
         * Structure each stanza and load it into the database.
         */
        foreach ($this->captions as &$caption) {
            /*
             * Identify when a new speaker is speaking. New speakers are indicated by a ">>" or a
             * ">>>" prefix. Sometimes we have captions that consist entirely of these markers. We
             * ignore these.
             */
            if (mb_strlen($caption->text) > 3) {
                if (mb_substr($caption->text, 0, 3) == '>> ') {
                    $caption->new_speaker = true;
                    $caption->text = mb_substr($caption->text, 3);
                } elseif (mb_substr($caption->text, 0, 4) == '>>> ') {
                    $caption->new_speaker = true;
                    $caption->text = mb_substr($caption->text, 4);
                }
            }

            /*
             * If we don't have core fields, post-replacement, skip this caption.
             */
            if (
                empty($caption->text) || empty($caption->time_start)
                || empty($caption->time_end)
            ) {
                continue;
            }

            /*
             * Assemble the SQL.
             */
            $sql = 'INSERT INTO video_transcript
					SET file_id=' . $this->file_id . ',
					time_start="' . $caption->time_start . '",
					time_end="' . $caption->time_end . '",
					text="' . mysqli_real_escape_string($GLOBALS['db'], $caption->text) . '"';
            if (isset($caption->new_speaker)) {
                $sql .= ', new_speaker="y"';
            }

            $result = mysqli_query($GLOBALS['db'], $sql);
            if ($result === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Identify speakers for transcription portions
     *
     * Attempt to label every speaker in video_transcript for a given file ID,
     * by combining chyron data and caption data.
     *
     * @param string $this->id The file ID.
     *
     * @access public
     * @return true or false
     */
    public function identify_speakers()
    {

        /*
         * Require a file ID.
         */
        if (!isset($this->file_id)) {
            return false;
        }

        $database = new Database();
        $database->connect_mysqli();

        /*
         * Retrieve all captions for this file.
         */
        $sql = 'SELECT id, text, time_start, time_end, new_speaker, legislator_id
				FROM video_transcript
				WHERE file_id=' . $this->file_id;
        $result = mysqli_query($GLOBALS['db'], $sql);
        if (mysqli_num_rows($result) == 0) {
            return false;
        }

        /*
         * Build up an array of all captions for this video.
         */
        $captions = array();
        $i = 0;
        while ($caption = mysqli_fetch_assoc($result)) {
            if ($caption['new_speaker'] == 'y') {
                $i++;
                $caption[$i] = array();
            }

            $caption['timestamp_start'] = time_to_seconds($caption['time_start']);
            $caption['timestamp_end'] = time_to_seconds($caption['time_end']);
            $caption['timestamp_duration'] = round(($caption['timestamp_end'] - $caption['timestamp_start']), 2);

            $captions[$i][] = $caption;
        }

        /*
         * Build up an array of video clips for this video.
         */
        $clips = array();
        $sql = 'SELECT legislator_id, bill_id, time_start, time_end
				FROM video_clips
				WHERE file_id=' . $this->file_id;
        $result = mysqli_query($GLOBALS['db'], $sql);
        if (mysqli_num_rows($result) > 0) {
            $clips = array();
            while ($clip = mysqli_fetch_assoc($result)) {
                $clip['timestamp_start'] = time_to_seconds($clip['time_start']);
                $clip['timestamp_end'] = time_to_seconds($clip['time_end']);
                $clips[] = $clip;
            }
        }

        /*
         * Define some phrases that tip us off as to who is speaking.
         */
        $phrases = array();
        $phrases['legislator'] = array(
            'mr. speaker',
            'mr. president'
        );
        $phrases['clerk'] = array(
            'house is now in session',
            'senate is now in session',
            'a quorum is present',
            'continuing with the calendar',
            'turning to page',
            'a bill to amend the code',
            'a bill to amend and reenact',
            'will meet upon adjournment',
            'meeting has been cancelled',
            'meets one half hour',
            'meets half an hour'
        );
        $phrases['speaker'] = array(
            'clerk will call the roll',
            'clerk will close the roll',
            'house will come to order',
            'house is now in session',
            'members will rise',
            'members will answer roll call',
            'gentleman may proceed',
            'gentleman has the floor',
            'gentlewoman has the floor',
            'gentleman may state it',
            'gentlewoman may state it',
            'without objection',
            'journal will so reflect',
            'gentleman from',
            'gentlewoman from',
            'i have examined and approved the journal',
            'shall the bill be engrossed',
            'speaker: ',
            'the question is on'
        );
        $phrases['president'] = array(
            'clerk will call the roll',
            'clerk will close the roll',
            'senate will come to order',
            'gentleman has the floor',
            'gentlewoman has the floor',
            'gentleman may state it',
            'gentlewoman may state it',
            'senator has the floor',
            'without objection',
            'journal will so reflect',
            'all those in favor',
            'clerk: '
        );

        /*
         * Identify all captions that occurred within a given clip.
         */
        foreach ($captions as $id => &$caption) {
            /*
             * If we already know who the speaker is, skip this caption.
             */
            if (!empty($caption[0]['legislator_id'])) {
                continue;
            }

            /*
             * Create a transcript of this caption segment, for text matching.
             */
            $transcript = '';
            foreach ($caption as $segment) {
                $transcript .= $segment['text'] . ' ';
            }

            /*
             * Identify the time metrics of this caption segment.
             */
            $time['start'] = $caption[0]['timestamp_start'];
            $time['end'] = $caption[count($caption) - 1]['timestamp_end'];
            $time['duration'] = round(($time['end'] - $time['start']), 2);

            /*
             * If this contains phrases that allow us to identify the identify as the
             * Speaker of the House, ID it as such.
             */
            if (mb_strlen($transcript) < 400) {
                foreach ($phrases['speaker'] as $phrase) {
                    if (mb_stripos($transcript, $phrase) !== false) {
                        foreach ($caption as &$line) {
                            $line['legislator_id'] = HOUSE_SPEAKER_ID;
                        }
                        continue;
                    }
                }
            }

            // if this contains phrases that allow us to ID it as the lt. gov or the
            // clerk, assign accordingly.
            // Uh...how are we going to label these? These aren't in the representatives table.

            /*
             * If this text's timespan substantially overlaps with a chyron timespan,
             * then call it a match.
             */
            foreach ($clips as &$clip) {
                if (
                    abs($time['start'] - $clip['timestamp_start']) < 20
                    &&
                    abs($time['end'] - $clip['timestamp_end']) < 10
                ) {
                    foreach ($caption as &$line) {
                        $line['legislator_id'] = $clip['legislator_id'];
                    }
                    continue;
                }
            }

            /*
             * if we still can't ID it, use the text from the prior speaker to ID them
             * (that is, using their introductions).
             */
            $prior_text = '';

            if ($id - 1 > 0) {
                foreach ($captions[$id - 1] as $segment) {
                    $prior_text .= $segment['text'] . ' ';
                }
            }

            $regex = '/(?:gentleman|gentlewoman|senator) from (.{3,30}),? (senator|ms\.|miss|mr\.)\s([a-z-]+)/i';
            preg_match($regex, $prior_text, $matches);
            if (count($matches) == 0) {
                /*
                 * Make another attempt, looking back 3 lines. This is to deal with the
                 * exchange that goes like this:
                 *
                 * Speaker: The gentleman from Richmond, Mr. Smith.
                 * Smith: I rise for purposes of an introduction.
                 * Speaker: The gentleman has the floor.
                 * Smith: Mr. Speaker, [etc.]
                 *
                 * Without this second attempt, we'd only identify the first line as Mr. Smith,
                 * but not the second.
                 */
                $prior_text = '';
                if ($id - 3 > 0) {
                    foreach ($captions[$id - 3] as $segment) {
                        $prior_text .= $segment['text'] . ' ';
                    }
                }
                preg_match($regex, $prior_text, $matches);
            }

            if (count($matches) > 0) {
                /*
                 * Look up the identity of the introduced legislator.
                 */
                foreach ($matches as &$match) {
                    $match = mb_strtolower($match);
                }
                $place = str_replace('county', '', $matches[1]);
                $place = str_replace('city', '', $place);
                $place = str_replace(',', '', $place);
                if ($matches[2] == 'miss') {
                    $sex = 'female';
                } elseif ($matches[2] == 'mr.') {
                    $sex = 'male';
                }
                $name = $matches[3];

                /*
                 * First we check without verifying the location, since the named location
                 * isn't necessarily a county or city (leading to under-matches).
                 */
                $sql = 'SELECT id
						FROM representatives
						WHERE name LIKE "' . $name . '%" ';
                if (!empty($sex)) {
                    $sql .= 'AND sex = "' . $sex . '" ';
                }
                $sql .= 'AND chamber =
							(SELECT chamber
							FROM files
							WHERE id=' . $this->file_id . ')';
                $result = mysqli_query($GLOBALS['db'], $sql);

                /*
                 * If more than 1 legislator was found, then we need to re-query, this time
                 * with location.
                 */
                if (mysqli_num_rows($result) > 1) {
                    $sql = 'SELECT representatives.id
							FROM representatives
							LEFT JOIN districts
								ON representatives.district_id = districts.id
							WHERE representatives.name LIKE "' . $name . '%" ';
                    if (!empty($sex)) {
                        $sql .= 'AND representatives.sex = "' . $sex . '" ';
                    }
                    $sql .= '
							AND representatives.chamber =
								(SELECT chamber
								FROM files
								WHERE files.id=' . $this->file_id . ')
							AND
								(representatives.place LIKE "' . $place . '%"
								OR
								districts.description LIKE "%' . $place . '%")';
                    $result = mysqli_query($GLOBALS['db'], $sql);
                }

                /*
                 * If we've matched exactly one legislator, then we know who has
                 * spoken the line in question.
                 */
                if (mysqli_num_rows($result) == 1) {
                    $legislator = mysqli_fetch_array($result);

                    /*
                     * Mark each line as being uttered by the matched legislator.
                     */
                    foreach ($caption as &$line) {
                        $line['legislator_id'] = $legislator['id'];
                    }
                    continue;
                }
            }
        }

        /*
         * Now insert all of these changes.
         */
        foreach ($captions as $segment) {
            foreach ($segment as $caption) {
                /*
                 * If this caption is an array, and we've got a legislator ID. Sometimes,
                 * the contents of $caption is not an array. Generally, the last caption
                 * segment in a transcript. I have no idea of why.
                 */
                if (is_array($caption) && !empty($caption['legislator_id'])) {
                    $sql = 'UPDATE video_transcript
							SET legislator_id = ' . $caption['legislator_id'] . '
							WHERE id = ' . $caption['id'];
                    mysqli_query($GLOBALS['db'], $sql);
                }
            }
        }

        return true;
    }

    /**
     * Create a transcript based on the atomized transcript in the database
     */
    public function generate_transcript()
    {

        /*
         * Require a file ID.
         */
        if (isset($this->id)) {
            $this->file_id = $this->id;
        }
        if (!isset($this->file_id)) {
            return false;
        }

        $database = new Database();
        $database->connect_mysqli();

        /*
         * Retrieve all captions for this file.
         */
        $sql = 'SELECT video_transcript.id, video_transcript.text, video_transcript.time_start,
				video_transcript.time_end, video_transcript.new_speaker,
				video_transcript.legislator_id, representatives.name,
				representatives.shortname
				FROM video_transcript
				LEFT JOIN representatives
					ON video_transcript.legislator_id = representatives.id
				WHERE file_id=' . $this->file_id . '
				ORDER BY time_start ASC';
        $result = mysqli_query($GLOBALS['db'], $sql);
        if (mysqli_num_rows($result) == 0) {
            return false;
        }

        /*
         * Build up an array of the lines.
         */
        $this->transcript = array();
        $i = 0;
        while ($line = mysqli_fetch_assoc($result)) {
            if ($line['new_speaker'] == 'y') {
                if (count($this->transcript) > 0) {
                    $i++;
                }
                $this->transcript[$i]['text'] = $line['text'];
                $this->transcript[$i]['id'] = $line['legislator_id'];
                $this->transcript[$i]['shortname'] = $line['shortname'];
                $this->transcript[$i]['name'] = stripslashes(pivot($line['name']));
                $this->transcript[$i]['time_start'] = $line['time_start'];
                $this->transcript[$i]['time_end'] = $line['time_end'];
            } elseif ($line['new_speaker'] == 'n') {
                $this->transcript[$i]['text'] .= ' ' . $line['text'];
            }
        }

        /*
         * Sentence case the text.
         */
        foreach ($this->transcript as &$line) {
            $line['text'] = $this->sentence_case(mb_strtolower($line['text']));
        }

        return true;
    }

    /*
     * Capitalize the beginning of each sentence.
     */
    public function upper($matches)
    {
        return mb_strtoupper($matches[0]);
    }

    /*
     * Move from all-caps to mixed case.
     */
    public function sentence_case($str)
    {
        $cap = true;
        $return = '';

        for ($x = 0; $x < mb_strlen($str); $x++) {
            $letter = mb_substr($str, $x, 1);

            if ($letter == '.' || $letter == '!' || $letter == '?') {
                $cap = true;
            } elseif ($letter != ' ' && $cap == true) {
                $letter = mb_strtoupper($letter);
                $cap = false;
            }

            $return .= $letter;
        }

        /*
         * Capitalize the beginning of each sentence.
         */
        $return = preg_replace_callback('/>> ([a-z])/', 'Video::upper', $return);

        /*
            * Fix the case of these words.
            */
        $words = array('I', 'Virginia', 'God', 'Mr', 'Ms', 'Mrs', 'Dr', 'Senate', 'House',
                'Reverend', 'Senator', 'Delegate',

                'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday',

                'January', 'February', 'March', 'April', 'June', 'July', 'August',
                'September', 'October', 'November', 'December',

                'Abbitt', 'Abbott', 'Adams', 'Aird', 'Albo', 'Alexander', 'Amundson', 'Anderson',
                'Armstrong', 'Athey', 'Austin', 'BaCote', 'Bagby', 'Barker', 'Barlow', 'Bell', 'Berg',
                'Black', 'Blevins', 'Bloxom', 'Bouchard', 'Bowling', 'Boysko', 'Brink', 'Bryant',
                'Bulova', 'Byron', 'Callahan', 'Campbell', 'Caputo', 'Carr', 'Carrico', 'Chafin',
                'Chase', 'Chichester', 'Cleaveland', 'Cline', 'Cole', 'Colgan', 'Collins', 'Comstock',
                'Cosgrove', 'Cox', 'Cox', 'Crockett-Stark', 'Cuccinelli', 'Dance', 'Davis', 'Davis',
                'Deeds', 'DeSteph', 'Dudenhefer', 'Dudley', 'Dunnavant', 'Ebbin', 'Edmunds', 'Edwards',
                'Eisenberg', 'Englin', 'Fariss', 'Farrell', 'Favola', 'Filler-Corn', 'Fowler',
                'Fralin', 'Frederick', 'Freitas', 'Futrell', 'Garrett', 'Gear', 'Gilbert',
                'Sturtevant', 'Greason', 'Griffith', 'Habeeb', 'Hall', 'Hamilton', 'Hanger',
                'Hargrove', 'Parrish', 'Hawkins', 'Head', 'Helsel', 'Heretick', 'Herring',
                'Hester', 'Hodges', 'Hogan', 'Houck', 'Howell', 'Hugo', 'Hull',
                'Iaquinto', 'Ingram', 'James', 'Janis', 'Joannou', 'Johnson', 'Jones', 'Jones', 'Keam',
                'Kilgore', 'Knight', 'Kory', 'Krizek', 'Krupicka', 'Lambert', 'Landes', 'LaRock',
                'Leftwich', 'LeMunyon', 'Levine', 'Lewis', 'Lindsey', 'Lingamfelter', 'Locke', 'Lohr',
                'Lopez', 'Loupassi', 'Lucas', 'Marsden', 'Marsh', 'Marshall', 'Martin', 'Mason',
                'Massie', 'Mathieson', 'May', 'McClellan', 'McDougle', 'McEachin', 'McPike', 'McQuigg',
                'McQuinn', 'McWaters', 'Melvin', 'Merricks', 'Miller', 'Mims', 'Minchew', 'Miyares',
                'Moran', 'Morefield', 'Morgan', 'Morris', 'Morrissey', 'Murphy', 'Newman', 'Nichols',
                'Nixon', 'Norment', 'Northam', 'Nutter', "O'Brien", "O'Quinn", 'Obenshain', 'Oder',
                'Orrock', "O'Bannon", 'Peace', 'Petersen', 'Phillips', 'Pillion', 'Plum', 'Pogge',
                'Poindexter', 'Poisson', 'Pollard', 'Potts', 'Preston', 'Price', 'Puckett', 'Puller',
                'Purkey', 'Putney', 'Quayle', 'Ramadan', 'Ransone', 'Rapp', 'Rasoul', 'Reeves',
                'Reid', 'Rerras', 'Reynolds', 'Robinson', 'Ruff', 'Rush', 'Rust', 'Saslaw', 'Saxman',
                'Scott', 'Shannon', 'Sherwood', 'Shuler', 'Sickles', 'Simon', 'Smith', 'Spruill',
                'Stanley', 'Stolle', 'Stosch', 'Stuart', 'Suetterlein', 'Suit', 'Sullivan',
                'Surovell', 'Tata', 'Taylor', 'Ticer', 'Torian', 'Toscano', 'Tyler', 'Valentine',
                'Vanderhye', 'Villanueva', 'Vogel', 'Waddell', 'Wagner', 'Wampler', 'Ward', 'Wardrup',
                'Ware', 'Ware', 'Watkins', 'Watson', 'Watts', 'Webert', 'Welch', 'Wexton', 'Whipple',
                'Williams', 'Wilt', 'Wittman', 'Wright', 'Yancey', 'Yost',

                'Accomack County', 'Albemarle County', 'Alleghany County', 'Amelia County',
                'Amherst County', 'Appomattox County', 'Arlington County', 'Augusta County',
                'Bath County', 'Bedford County', 'Bland County', 'Botetourt County',
                'Brunswick County', 'Buchanan County', 'Buckingham County', 'Campbell County',
                'Caroline County', 'Carroll County', 'Charles City County', 'Charlotte County',
                'Chesterfield County', 'Clarke County', 'Craig County', 'Culpeper County',
                'Cumberland County', 'Dickenson County', 'Dinwiddie County', 'Essex County',
                'Fairfax County', 'Fauquier County', 'Floyd County', 'Fluvanna County',
                'Franklin County', 'Frederick County', 'Giles County', 'Gloucester County',
                'Goochland County', 'Grayson County', 'Greene County', 'Greensville County',
                'Halifax County', 'Hanover County', 'Henrico County', 'Henry County',
                'Highland County', 'Isle of Wight County', 'James City County',
                'King and Queen County', 'King George County', 'King William County',
                'Lancaster County', 'Lee County', 'Loudoun County', 'Louisa County',
                'Lunenburg County', 'Madison County', 'Mathews County', 'Mecklenburg County',
                'Middlesex County', 'Montgomery County', 'Nelson County', 'New Kent County',
                'Northampton County', 'Northumberland County', 'Nottoway County', 'Orange County',
                'Page County', 'Patrick County', 'Pittsylvania County', 'Powhatan County',
                'Prince Edward County', 'Prince George County', 'Prince William County',
                'Pulaski County', 'Rappahannock County', 'Richmond County', 'Roanoke County',
                'Rockbridge County', 'Rockingham County', 'Russell County', 'Scott County',
                'Shenandoah County', 'Smyth County', 'Southampton County', 'Spotsylvania County',
                'Stafford County', 'Surry County', 'Sussex County', 'Tazewell County', 'Warren County',
                'Washington County', 'Westmoreland County', 'Wise County', 'Wythe County',
                'York County',

                'Accomack', 'Albemarle', 'Alleghany', 'Amelia',
                'Amherst', 'Appomattox', 'Arlington', 'Augusta',
                'Bath', 'Bedford', 'Bland', 'Botetourt',
                'Brunswick', 'Buchanan', 'Buckingham', 'Campbell',
                'Caroline', 'Carroll', 'Charles City', 'Charlotte',
                'Chesterfield', 'Clarke', 'Craig', 'Culpeper',
                'Cumberland', 'Dickenson', 'Dinwiddie', 'Essex',
                'Fairfax', 'Fauquier', 'Floyd', 'Fluvanna',
                'Franklin', 'Frederick', 'Giles', 'Gloucester',
                'Goochland', 'Grayson', 'Greene', 'Greensville',
                'Halifax', 'Hanover', 'Henrico', 'Henry',
                'Highland', 'Isle of Wight', 'James City',
                'King and Queen', 'King George', 'King William',
                'Lancaster', 'Lee', 'Loudoun', 'Louisa',
                'Lunenburg', 'Madison', 'Mathews', 'Mecklenburg',
                'Middlesex', 'Montgomery', 'Nelson', 'New Kent',
                'Northampton', 'Northumberland', 'Nottoway', 'Orange',
                'Page', 'Patrick', 'Pittsylvania', 'Powhatan',
                'Prince Edward', 'Prince George', 'Prince William',
                'Pulaski', 'Rappahannock', 'Richmond', 'Roanoke',
                'Rockbridge', 'Rockingham', 'Russell', 'Scott',
                'Shenandoah', 'Smyth', 'Southampton', 'Spotsylvania',
                'Stafford', 'Surry', 'Sussex', 'Tazewell', 'Warren',
                'Washington', 'Westmoreland', 'Wise', 'Wythe',
                'York',

                'Virginia Beach', 'Norfolk', 'Chesapeake', 'Richmond City', 'Richmond',
                'Newport News', 'Alexandria', 'Hampton', 'Roanoke City', 'Roanoke', 'Portsmouth',
                'Suffolk', 'Lynchburg', 'Harrisonburg', 'Charlottesville', 'Danville', 'Manassas',
                'Petersburg', 'Fredericksburg', 'Winchester', 'Salem', 'Staunton', 'Fairfax City',
                'Fairfax', 'Hopewell', 'Waynesboro', 'Bristol', 'Colonial Heights', 'Radford',
                'Manassas Park', 'Williamsburg', 'Martinsville', 'Falls Church', 'Poquoson',
                'Franklin City', 'Franklin', 'Lexington', 'Galax', 'Buena Vista', 'Covington',
                'Emporia', 'Norton');

        foreach ($words as $word) {
            $word = str_replace('.', '\.', $word);
            $find = '/(\b)' . mb_strtolower($word) . '(\b)/';
            $replace = '\1' . $word . ' \1';
            $return = preg_replace($find, $replace, $return);
        }

        /*
         * Fix spaces before periods.
         */
        $return = str_replace(' . ', '. ', $return);

        /*
         * Fix spaces before commas.
         */
        $return = str_replace(' , ', ', ', $return);

        return $return;
    }

    /*
    ///////
    // MOVE ALL VIDEOS TO HAVE A NAME BASED ON THEIR ID
    ///////
    # Select a list of every video path & ID.
    $sql = 'SELECT id, CONCAT('/video/', chamber, path) AS path
            FROM files
            WHERE type="video" AND path IS NOT NULL
            ORDER BY path ASC';
    $result = mysqli_query($GLOBALS['db'], $sql);
    while ($video = mysqli_fetch_array($result))
    {
        $videos[$video{path}] = $video['id'];
    }

    // Iterate through the file listing
    $container_directory = '/video/floor/senate/';
    $new_container_directory = '/video/';
    $files = scandir($container_directory);

    # Iterate through this list of files and move each of them.
    foreach ($files as $file)
    {

        # If this isn't an MP4, we're not going to be doing anything with it.
        if (substr($file, -4, 4) != '.mp4')
        {
            continue;
        }

        # Figure out the name of the directory containing the screenshots.
        $screenshot_directory = str_replace($file, '.mp4', '');

        # Rename the video file, making it the ID
        rename($container_directory.$file, $new_container_directory.$video[$file]);

        # Rename the video directory (if it exists), making it the ID.
        if (file_exists($screenshot_directory) !== false)
        {
            rename($screenshot_directory, $new_container_directory.$video[$file]);
        }

        # Update every files record to use the new path
        $sql = 'UPDATE video_index
                SET path="'.$.'", cache
                WHERE path="'.$.'"';
    }
    */
}
