<?php

/**
 * Undocumented class
 */
class Places
{

    /**
     * List places that we have a record of.
     * 
     * This is not just a list of every entry in the gazetteer. That would not be useful. Instead,
     * it's a list of every place name that has been mentioned in legislation.
     *
     * @param int $session_id
     * @return array
     */
    public function list_all($session_id = SESSION_ID)
    {

        $sql = 'SELECT DISTINCT
                    placename AS name,
                    longitude,
                    latitude
                FROM bills_places
                    LEFT JOIN bills
                    ON bills_places.bill_id = bills.id
                WHERE
                    bills.session_id=' . $session_id;
        $stmt = $GLOBALS['dbh']->prepare($sql);
        $stmt->bindParam(":year", $year, PDO::PARAM_INT);
        $stmt->execute();
        $places = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $places;

    }

    /**
     * List bills for a given place
     *
     * @param string $place
     * @param int $session_id
     * @return array
     */
    public function bills($place, $session_id = SESSION_ID)
    {

        /*
         * If the place isn't specified, there's nothing for us to do
         */
        if (empty($place))
        {
            return false;
        }

        /*
         * If the session ID hasn't been specified, default to the current session ID.
         */
        if (empty($session_id))
        {
            $session_id = SESSION_ID;
        }

        $sql = 'SELECT
                    bills.number,
                    bills.chamber,
                    bills.catch_line,
                    bills.status AS status_raw,
                    representatives.name AS patron,
                    bills.date_introduced,
                    bills.status
                FROM bills
                LEFT JOIN representatives
                    ON bills.chief_patron_id = representatives.id
                LEFT JOIN sessions
                    ON bills.session_id = sessions.id
                WHERE
                    bills_places.placename = "' . $place . '"
                    bills.session_id = ' . $session_id . '
                ORDER BY
                    bills.chamber DESC,
                    SUBSTRING(bills.number FROM 1 FOR 2) ASC,
                    CAST(LPAD(SUBSTRING(bills.number FROM 3), 4, "0") AS unsigned) ASC';
        $stmt = $GLOBALS['dbh']->prepare($sql);
        $stmt->bindParam(":year", $year, PDO::PARAM_INT);
        $stmt->execute();
        $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $bills;

    }

}
