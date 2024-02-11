<?php

class Tags
{
    # Take a fragment of a tag, get suggested autocompletions.
    public function get_suggestions()
    {
        if (!isset($this->fragment)) {
            return false;
        }

        $database = new Database();
        $database->connect_mysqli();

        $sql = 'SELECT tag AS text, COUNT(*) AS number
        		FROM tags
        		WHERE tag LIKE "' . $this->fragment . '%"
        		GROUP BY tag
        		HAVING number > 5
        		ORDER BY number DESC
        		LIMIT 5';
        $result = mysqli_query($GLOBALS['db'], $sql);
        if (mysqli_num_rows($result) == 0) {
            return false;
        }
        $tags = array();
        while ($tag = mysqli_fetch_array($result)) {
            $tags[] = $tag['text'];
        }

        return $tags;
    } // end get_suggestions()
}
