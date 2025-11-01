<?php

/**
 * Supplies tag-related helpers such as autocomplete suggestions.
 */
class Tags
{
    public $fragment;

    /**
     * Take a fragment of a tag string and return autocomplete suggestions.
     *
     * @return array|false Array of suggested tags, or false when no matches are found.
     */
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
