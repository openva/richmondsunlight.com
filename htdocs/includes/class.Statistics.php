<?php

class Statistics
{
    /*
     * Return the number of bill actions occuring daily over time
     *
     * Query the bill activity table to generate a daily count of the number of actions taken
     * on all legislation.
     *
     * @param none
     * @access public
     * @return array
     */
    public function daily_activity()
    {

        $database = new Database();
        $db = $database->connect();

        $sql = 'SELECT date, COUNT(*) as number
				FROM bill_status
				WHERE session_id = ' . SESSION_ID . '
				GROUP BY date
				ORDER BY date ASC';
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll();
        if (count($result) == 0) {
            return false;
        }

        return $result;
    }

    /*
     * Return the number of bills filed per day
     *
     * Query the bills table to generate a daily count of the number of bills filed.
     *
     * @param none
     * @access public
     * @return array
     */
    public function bills_filed_daily()
    {
        $database = new Database();
        $db = $database->connect();

        $sql = 'SELECT date_introduced AS date, COUNT(*) AS number
				FROM bills
				WHERE session_id = ' . SESSION_ID . '
				GROUP BY date_introduced
				ORDER BY date_introduced ASC';
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll();
        if (count($result) == 0) {
            return false;
        }

        return $result;
    }

    /*
     * Return the number of views that a bill has had over time
     *
     * Query the bill views table to generate a daily count of the number of times that a bill
     * has been viewed, since the first view.
     *
     * @param none
     * @access public
     * @return array
     */
    public function bill_views()
    {
        if (!isset($bill) && empty($bill->id)) {
            return false;
        }

        $database = new Database();
        $db = $database->connect();

        $sql = 'SELECT DATE_FORMAT(date, "%Y-%m-%d") AS day, COUNT(*) AS number
				FROM bills_views
				WHERE bill_id= ' . $this->bill_id . '
				GROUP BY day
				ORDER BY day ASC';
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll();
        if (count($result) == 0) {
            return false;
        }

        return $result;
    }

    /**
     * Return the number of votes cast by a legislator daily
     *
     * Query the votes table to generate a daily count of the number of votes made by a
     * given legislator
     *
     * @param legislator
     * @access public
     * @return array
     */
    public function legislator_activity($legislator_id)
    {

        $database = new Database();
        $db = $database->connect();

        $sql = 'SELECT votes.date, COUNT(*) AS number
                FROM votes
                LEFT JOIN representatives_votes
                    ON votes.id=representatives_votes.vote_id
                WHERE
                    representatives_votes.representative_id=' . $legislator_id . ' AND
                    votes.session_id = ' . SESSION_ID . '
                GROUP BY votes.date
                ORDER BY date ASC';

        $stmt = $db->prepare($sql);
        $stmt->execute();

        $activity = [];
        while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $activity[$result['date']] = $result['number'];
        }

        return $activity;
    }
}
