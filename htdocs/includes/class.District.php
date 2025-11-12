<?php

declare(strict_types=1);

/**
 * Provides helpers for looking up district metadata.
 */
class District
{
    /**
     * Retrieve information about a district by chamber and number.
     *
     * @param string     $chamber Chamber identifier (`house` or `senate`).
     * @param string|int $number  District number.
     *
     * @return array|null District data or null when not found.
     */
    public function info($chamber, $number)
    {
        $sql = 'SELECT id, chamber, number, description, notes, boundaries
                FROM districts
                WHERE
                    date_ended IS NULL AND
                    chamber= :chamber AND
                    number= :number';

        $stmt = $GLOBALS['dbh']->prepare($sql);
        $stmt->bindParam(':chamber', $chamber);
        $stmt->bindParam(':number', $number);

        $stmt->execute();
        $district = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($district === false) {
            return null;
        }

        return array_map('stripslashes', $district);
    }
}
