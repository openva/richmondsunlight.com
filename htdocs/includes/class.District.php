<?php

class District
{
    public function info($chamber, $number)
    {

        $sql = 'SELECT id, chamber, number, description, notes, boundaries
                FROM districts
                WHERE
                    date_ended = "0000-00-00"
                    AND chamber= :chamber
                    AND number= :number';

        $stmt = $GLOBALS['dbh']->prepare($sql);
        $stmt->bindParam(':chamber', $chamber);
        $stmt->bindParam(':number', $number);

        $stmt->execute();
        $district = $stmt->fetch(PDO::FETCH_ASSOC);

        # Clean it up.
        $district = array_map('stripslashes', $district);

        return $district;

    }

}
