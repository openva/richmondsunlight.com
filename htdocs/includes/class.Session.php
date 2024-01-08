<?php

/**
 * Session Class
 *
 * This class is responsible for handling session-related operations, primarily
 * focused on determining the status of a session based on database records.
 */
class Session
{
    /**
     * Retrieves the current session's status.
     *
     * This method queries the database to retrieve information about the current
     * session based on a predefined SESSION_ID. It then evaluates whether the
     * current time falls within the session's start and end dates, and whether
     * the current month is within the legislative season (November to April).
     *
     * @return array An associative array containing the session status with keys
     *               'in_session' (boolean) indicating if the current time is within
     *               the session period, and 'in_season' (boolean) indicating if
     *               it is the legislative season.
     *
     * @throws Exception If there is a database connection or execution error.
     */
    public function status()
    {

        $sql = 'SELECT *
                FROM sessions
                WHERE
                    id= :id';

        $stmt = $GLOBALS['dbh']->prepare($sql);
        $stmt->bindParam(':id', SESSION_ID);

        $stmt->execute();
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($session == false)
        {
            unset($session);
            $session=array();
            $session['in_session'] = false;
            $session['in_season'] = false;
            return false;
        }

        $session = array_map('stripslashes', $session);

        /*
         * If we're in the midst of session
         */
        if (
                time() >= strtotime($session['date_started'])
                &&
                time() <= strtotime($session['date_ended'])
        )
        {
            $session['in_session'] = true;
            $session['in_season'] = true;
        }

        /*
         * If it's legislative season, but not session
         */
        elseif (date('n') >= 11 || date('n') <= 4)
        {
            $session['in_session'] = false;
            $session['in_season'] = true;
        }

        /*
         * It's the off season
         */
        else
        {
            $session['in_session'] = false;
            $session['in_season'] = false;
        }

        return $session;

    }

}
