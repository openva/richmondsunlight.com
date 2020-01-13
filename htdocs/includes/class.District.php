<?php

class District
{
    public function info($chamber, $number)
    {

        $database = new Database;
        $database->connect_mysqli();

        $sql = 'SELECT id, chamber, number, description, notes
                FROM districts
                WHERE
                    date_ended = "0000-00-00"
                    AND chamber=' . mysqli_real_escape_string($GLOBALS['db'], $chamber) . '
                    AND number=' . mysqli_real_escape_string($GLOBALS['db'], $number);

        $result = mysqli_query($GLOBALS['db'], $sql);
        if (mysqli_num_rows($result) == 0)
        {
            return FALSE;
        }
        $district = mysqli_fetch_assoc($result);

        # Clean it up.
        $district = array_map('stripslashes', $district);

        return $district;

    }

}
