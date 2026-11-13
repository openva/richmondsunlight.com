<?php

###
# Each Day in the General Assembly RSS
#
# PURPOSE
# Lists the most recent days that the legislature did anything, each published the morning after,
# with how much happened and the day's highlights. See includes/class.DayFeed.php.
#
# Everything comes from the API.
#
###

# INCLUDES
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/settings.inc.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/functions.inc.php';

$now = time();
$year = (int) date('Y', $now);

# This year's days, and last year's, if this year doesn't have enough yet.
$days = Day::list_from_api($year);
if (count(DayFeed::dates($days, $now)) < DayFeed::ITEMS) {
    $days = array_merge($days, Day::list_from_api($year - 1));
}

$records = [];
foreach (DayFeed::dates($days, $now) as $date) {
    $day = Day::from_api($date);
    if ($day !== null) {
        $records[] = $day;
    }
}

# It's cached for 30 minutes (by .htaccess, for application/rss+xml).
header('Content-Type: application/rss+xml; charset=utf-8');
echo DayFeed::xml($records);
