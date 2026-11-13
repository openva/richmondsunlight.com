<?php

/**
 * A day in the General Assembly: what each chamber did on the floor, every committee and
 * subcommittee meeting (its video, its outcomes, and the rest of its docket), and, on the most
 * recent day, what's scheduled next. See docs/minutes-replacement.md.
 *
 * The API serves days (/1.1/day/{date}.json and /1.1/days.json, through record_for_api() and
 * list_for_api()), and the site's /day/ pages show them (through from_api() and list_from_api()).
 * rs-api copies this file from this repo's deploy branch, as it does class.Video.php.
 *
 * Bill actions (bills_status) are LIS's text, which names the committee that acted, but not the
 * subcommittee. votes.committee_id isn't reliable enough to use instead (floor votes can carry a
 * committee's ID), so committees are found by name, and subcommittees by the day's dockets.
 */
class Day
{
    /**
     * Bill actions that are paperwork, not something that a chamber or a committee did. They're
     * left out of days entirely.
     */
    private const PAPERWORK = [
        '/^Fiscal Impact/i',
        '/^Impact statement/i',
        '/^(?:(?:House|Senate)\s+)?(?:sub)?committee offered\b/i',
        '/^Floor offered\b/i',
        '/^Placed on (?:Calendar|.+ Agenda)\b/i',
        '/^Committee Referral Pending/i',
        '/^Committee$/i',
        '/^Bill text as passed/i',
        '/^Acts of Assembly Chapter text/i',
        '/Action Deadline/i',
        '/^Enrolled Bill communicated/i',
        '/^Communicated to Governor/i',
        '/^(?:Re)?enrolled\b/i',
        '/^Laid on Clerk\'s Desk/i',
        '/^Received\b/i',
        '/^Signed by (?:Speaker|President)/i',
        '/^(?:Prefiled|Presented) and ordered printed/i',
        '/^Printed as engrossed/i',
        '/^Engrossed bill text/i',
        '/substitute printed\b/i',
        '/^Effective\b/i',
        '/^Blank Action/i',
        '/^Presented\b/i',
        '/^Requires \d+ affirmative votes/i',
    ];

    /**
     * Bill actions that a committee takes, and that name it. Each pattern's last group is the
     * committee's name, and its outcome is what the action does to the bill.
     */
    private const COMMITTEE_ACTIONS = [
        ['/^Reported from (.+?)(?=\s+with\s|\s+and\s+(?:re)?referred|\s*\(|$)/i', 'advanced'],
        ['/^Incorporated (?:by|in(?:to)?) (.+?)(?=\s*\(|\s+by voice vote|$)/i', 'incorporated'],
        ['/^(?:Passed by indefinitely|Continued to \d{4}|Tabled|Left|Failed to report(?: \(defeated\))?|Stricken at (?:the )?request of (?:the )?Patron|Stricken from docket|Laid on the table) (?:in|by) (.+?)(?=\s*\(|\s+with\s|\s+by voice vote|$)/i', 'stopped'],
    ];

    /** Meetings that are a chamber's floor session, rather than a committee's. */
    private const FLOOR_MEETING = '/^(?:House|Senate)\b.*\b(?:Session|Convenes|Floor)\b/i';

    /** The SQL condition that leaves out party caucuses, which aren't meetings of the legislature. */
    private const MEETING_FILTER = '(meetings.committee_id IS NOT NULL OR meetings.description NOT LIKE "%Caucus%")';

    /**
     * Bill actions that show that something happened that day (a vote, or a decision), as a
     * MariaDB regular expression. bills_status.status is case-insensitive.
     */
    private const DECISIVE_ACTION = '[0-9]+-Y [0-9]+-N|voice vote|^(Reported|Passed|Read third|Agreed|Defeated|Continued to|Left in|Tabled|Stricken|Incorporated by|Subcommittee)';

    /**
     * Bill actions that only happen on the floor, as a MariaDB regular expression: readings, and
     * floor procedure, and any recorded vote that isn't a committee's.
     */
    private const FLOOR_ACTION = '^(Read (first|second|third) time|Passed by (for the day|temporarily)|Constitutional reading|Rules suspended|Engrossed)|(passed|agreed to by|defeated by) (House|Senate)';
    private const COMMITTEE_ACTION = '^(Reported|Passed by indefinitely|Continued to|Left in|Tabled|Stricken|Incorporated by|Subcommittee|Failed to report|Referred|Rereferred|Assigned)';

    /** All committees, by ID, loaded once. */
    private static ?array $committees = null;

    /**
     * Whether a string is a date that could have a page (YYYY-MM-DD).
     *
     * @param string $date
     * @return bool
     */
    public static function is_date(string $date): bool
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches)) {
            return false;
        }

        return (int) $matches[1] >= 2000 && checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
    }

    /**
     * The address of a day's page on this site.
     *
     * @param string $date YYYY-MM-DD
     * @return string
     */
    public static function page_path(string $date): string
    {
        return '/day/' . $date . '/';
    }

    /**
     * The full URL of a day's page.
     *
     * @param string $date YYYY-MM-DD
     * @return string
     */
    public static function url(string $date): string
    {
        return 'https://www.richmondsunlight.com' . self::page_path($date);
    }

    /**
     * What a bill action is: its type, what it did to the bill, the committee that took it (if a
     * committee did), its text without its tally, and its vote.
     *
     * Types are "paperwork" (left out of days), "committee" and "subcommittee" (decisions by
     * either), "passed" and "failed" (on the floor), "reading", "procedural", and "amendments"
     * (also on the floor), "referred", "governor", "conference", "incorporates", and "other".
     *
     * @param string $status The bills_status.status text
     * @return array type, outcome ("advanced", "stopped", "incorporated", or null), committee (a
     *               name, or null), text, and vote (yes, no, abstaining, and voice, or null)
     */
    public static function classify(string $status): array
    {
        $status = trim(preg_replace('/\s+/', ' ', $status));

        $vote = null;
        if (preg_match('/\((\d+)-Y (\d+)-N(?: (\d+)-A)?\)/', $status, $matches)) {
            $vote = [
                'yes' => (int) $matches[1],
                'no' => (int) $matches[2],
                'abstaining' => isset($matches[3]) ? (int) $matches[3] : 0,
                'voice' => false,
            ];
        } elseif (preg_match('/\(voice vote\)|\bby voice vote\b/i', $status)) {
            $vote = ['yes' => null, 'no' => null, 'abstaining' => null, 'voice' => true];
        }

        // The text without its tally (or "(Voice Vote)"), which is shown separately.
        $text = trim(preg_replace('/\s*\((?:\d+-Y \d+-N(?: \d+-A)?|voice vote)\)\s*/i', ' ', $status));
        $text = trim(preg_replace('/\s+by voice vote\b/i', '', $text));

        $type = 'other';
        $outcome = null;

        foreach (self::PAPERWORK as $pattern) {
            if (preg_match($pattern, $status)) {
                return ['type' => 'paperwork', 'outcome' => null, 'committee' => null, 'text' => $text, 'vote' => $vote];
            }
        }

        foreach (self::COMMITTEE_ACTIONS as [$pattern, $what]) {
            if (preg_match($pattern, $status, $matches)) {
                $name = trim($matches[1]);
                // "Reported from House Appropriations" names the chamber, which the committee's
                // name in the database doesn't.
                $name = preg_replace('/^(?:House|Senate)\s+(?:Committee (?:on|for)\s+)?/i', '', $name);
                $name = preg_replace('/^Committee (?:on|for)\s+/i', '', $name);
                return ['type' => 'committee', 'outcome' => $what, 'committee' => $name, 'text' => $text, 'vote' => $vote];
            }
        }

        if (preg_match('/^Subcommittee (?:recommends|failed to recommend) (.*)$/i', $status, $matches)) {
            if (stripos($status, 'failed to recommend') !== false) {
                $outcome = 'stopped';
            } elseif (preg_match('/^incorporat/i', $matches[1])) {
                $outcome = 'incorporated';
            } elseif (preg_match('/^report/i', $matches[1])) {
                $outcome = 'advanced';
            } elseif (preg_match('/^(?:laying on the table|continuing|passing by indefinitely|striking|no action)/i', $matches[1])) {
                $outcome = 'stopped';
            }
            return ['type' => 'subcommittee', 'outcome' => $outcome, 'committee' => null, 'text' => $text, 'vote' => $vote];
        }

        if (preg_match('/^(?:Read third time and )?(?:passed|agreed to by) (?:House|Senate)\b|^Passed (?:House|Senate)\b|^Agreed to by (?:House|Senate)\b/i', $status)) {
            $type = 'passed';
            $outcome = 'advanced';
        } elseif (preg_match('/^(?:Read third time and )?defeated\b|\bdefeated by (?:House|Senate)\b|^(?:Read third time and )?failed to pass\b/i', $status)) {
            $type = 'failed';
            $outcome = 'stopped';
        } elseif (preg_match('/Governor|^Enacted\b|^Vetoed\b/i', $status)) {
            $type = 'governor';
        } elseif (preg_match('/conference|conferees|acceded to request|insisted on|^(?:Senators|Delegates):/i', $status)) {
            $type = 'conference';
        } elseif (preg_match('/^(?:Referred|Rereferred|Assigned)\b/i', $status)) {
            $type = 'referred';
        } elseif (preg_match('/^Incorporates\b/i', $status)) {
            $type = 'incorporates';
        } elseif (preg_match('/^Read (?:first|second|third) time\b|engrossed/i', $status)) {
            $type = 'reading';
        } elseif (preg_match('/^(?:Rules suspended|Constitutional reading dispensed|Passed by (?:for the day|temporarily)|Taken up|Reading of (?:substitute|amendments?) waived|Motion\b|Reconsideration\b|Moved from\b|VOTE:|Item \d+ Passed by)/i', $status)) {
            $type = 'procedural';
        } elseif (preg_match('/substitute|amendment/i', $status)) {
            $type = 'amendments';
        }

        return ['type' => $type, 'outcome' => $outcome, 'committee' => null, 'text' => $text, 'vote' => $vote];
    }

    /**
     * Whether a vote was close: both sides had votes, and the margin was no more than a fifth of
     * the votes cast (e.g., 8-6, or 51-47).
     *
     * @param array|null $vote From classify()
     * @return bool
     */
    public static function is_close(?array $vote): bool
    {
        if ($vote === null || $vote['voice'] || $vote['yes'] === 0 || $vote['no'] === 0) {
            return false;
        }

        return abs($vote['yes'] - $vote['no']) <= max(1, 0.2 * ($vote['yes'] + $vote['no']));
    }

    /**
     * A committee's name, reduced to its words, for comparison: "#1 Criminal" is "criminal", and
     * "Health & Human Resources Subcommittee" is "health and human resources".
     *
     * @param string $name
     * @return string
     */
    private static function name_words(string $name): string
    {
        $name = mb_strtolower(html_entity_decode($name, ENT_QUOTES));
        $name = str_replace('&', ' and ', $name);
        $name = preg_replace('/#\s*\d+/', ' ', $name);
        $name = preg_replace('/\b(?:sub)?committee\b|\bdocket\b/', ' ', $name);
        $name = preg_replace('/[^a-z0-9]+/', ' ', $name);

        return trim($name);
    }

    /**
     * Whether two committee names name the same committee. LIS's names and ours differ: LIS's
     * "Finance and Appropriations" is our "Finance," and our "Militia, Police and Public Safety"
     * is LIS's "Public Safety." So one name may start or end with the other.
     *
     * @param string $a
     * @param string $b
     * @return bool
     */
    private static function names_match(string $a, string $b): bool
    {
        $a = self::name_words($a);
        $b = self::name_words($b);
        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b) {
            return true;
        }
        [$short, $long] = mb_strlen($a) < mb_strlen($b) ? [$a, $b] : [$b, $a];

        return str_starts_with($long, $short . ' ') || str_ends_with($long, ' ' . $short);
    }

    /**
     * All committees, by ID.
     *
     * @return array
     */
    private static function committees(): array
    {
        if (self::$committees === null) {
            self::$committees = [];
            $result = mysqli_query($GLOBALS['db'], 'SELECT id, chamber, name, shortname, parent_id,
                    date_started, date_ended
                FROM committees');
            while ($result && $row = mysqli_fetch_assoc($result)) {
                $row['name'] = html_entity_decode((string) $row['name'], ENT_QUOTES);
                self::$committees[(int) $row['id']] = $row;
            }
        }

        return self::$committees;
    }

    /**
     * The committee (not subcommittee) with a name, in a chamber, preferring one that existed on
     * a date, and then one that still exists.
     *
     * @param string $chamber
     * @param string $name
     * @param string $date YYYY-MM-DD
     * @return array|null The committee's row
     */
    private static function committee_named(string $chamber, string $name, string $date): ?array
    {
        $best = null;
        $best_rank = -1;
        foreach (self::committees() as $committee) {
            if ($committee['chamber'] !== $chamber || !empty($committee['parent_id'])
                || !self::names_match($committee['name'], $name)) {
                continue;
            }
            $existed = ($committee['date_started'] === null || $committee['date_started'] <= $date)
                && ($committee['date_ended'] === null || $committee['date_ended'] >= $date);
            $rank = ($existed ? 4 : 0) + ($committee['date_ended'] === null ? 2 : 0)
                + (self::name_words($committee['name']) === self::name_words($name) ? 1 : 0);
            if ($rank > $best_rank) {
                $best = $committee;
                $best_rank = $rank;
            }
        }

        return $best;
    }

    /**
     * A committee's name, its shortname, and the URL of its page here, if it has one (only
     * committees that still exist do).
     *
     * @param array $committee A committee's row
     * @return array
     */
    private static function committee_summary(array $committee): array
    {
        $url = null;
        $shortname = $committee['shortname'];
        if (empty($committee['parent_id'])) {
            $current = self::committee_named($committee['chamber'], $committee['name'], date('Y-m-d'));
            if ($current !== null && $current['date_ended'] === null && !empty($current['shortname'])) {
                $shortname = $current['shortname'];
                $url = 'https://www.richmondsunlight.com/committee/' . $committee['chamber'] . '/' . $shortname . '/';
            }
        }

        return [
            'name' => $committee['name'],
            'shortname' => empty($committee['parent_id']) ? $shortname : null,
            'url' => $url,
        ];
    }

    /**
     * A new meeting, for a committee or a subcommittee (by its row), with nothing in it yet.
     *
     * @param array $committee A committee's row
     * @return array
     */
    private static function committee_meeting(array $committee): array
    {
        $committees = self::committees();
        $chamber = ucfirst($committee['chamber']);
        if (!empty($committee['parent_id']) && isset($committees[(int) $committee['parent_id']])) {
            $parent = $committees[(int) $committee['parent_id']];
            return self::new_meeting(
                self::meeting_key($committee['chamber'], $parent['name'], $committee['name']),
                $committee['chamber'],
                'subcommittee',
                $chamber . ' ' . $parent['name'] . ': ' . $committee['name'] . ' Subcommittee',
                self::committee_summary($committee),
                self::committee_summary($parent)
            );
        }

        return self::new_meeting(
            self::meeting_key($committee['chamber'], null, $committee['name']),
            $committee['chamber'],
            'committee',
            $chamber . ' ' . $committee['name'] . ' Committee',
            self::committee_summary($committee),
            null
        );
    }

    /**
     * What identifies a committee's or subcommittee's meeting, so that its video, its docket, its
     * actions, and its place on the schedule all end up in one place, even when they name it
     * differently, or by different IDs (committees are sometimes recreated).
     *
     * @param string $chamber
     * @param string|null $parent The parent committee's name, for a subcommittee
     * @param string $name
     * @return string
     */
    private static function meeting_key(string $chamber, ?string $parent, string $name): string
    {
        if ($parent !== null) {
            return $chamber . '|' . self::name_words($parent) . '|' . self::name_words($name);
        }

        return $chamber . '||' . self::name_words($name);
    }

    /**
     * An empty meeting.
     *
     * @return array
     */
    private static function new_meeting(string $key, ?string $chamber, string $type, string $label, ?array $committee, ?array $parent): array
    {
        return [
            'key' => $key,
            'chamber' => $chamber,
            'type' => $type,
            'label' => $label,
            'committee' => $committee,
            'parent_committee' => $parent,
            'time' => null,
            'time_description' => null,
            'location' => null,
            'videos' => [],
            'actions' => [],
            'docket' => [],
        ];
    }

    /**
     * Run a prepared query, and return all of its rows.
     *
     * @param string $sql
     * @param string $types
     * @param array $params
     * @return array
     */
    private static function rows(string $sql, string $types = '', array $params = []): array
    {
        $stmt = mysqli_prepare($GLOBALS['db'], $sql);
        if ($stmt === false) {
            return [];
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        if (!$stmt->execute()) {
            return [];
        }

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * The SQL that selects every date (as "d") that has a page, between two dates (each given
     * five times): a date with a video, minutes, a docket, a meeting, or a decision on a bill.
     *
     * @return string
     */
    private static function dates_sql(): string
    {
        return '(SELECT date AS d FROM files WHERE type = "video" AND slug IS NOT NULL AND date BETWEEN ? AND ?)
            UNION (SELECT date FROM minutes WHERE date BETWEEN ? AND ?)
            UNION (SELECT dockets.date FROM dockets
                JOIN bills ON bills.id = dockets.bill_id
                JOIN committees ON committees.id = dockets.committee_id
                WHERE dockets.date BETWEEN ? AND ?)
            UNION (SELECT date FROM meetings WHERE date BETWEEN ? AND ? AND ' . self::MEETING_FILTER . ')
            UNION (SELECT DATE(bills_status.date) FROM bills_status
                JOIN bills ON bills.id = bills_status.bill_id
                WHERE bills_status.date >= ? AND bills_status.date < DATE(?) + INTERVAL 1 DAY
                    AND bills_status.status REGEXP "' . self::DECISIVE_ACTION . '")';
    }

    /**
     * The nearest date with a page before or after a date, no later than today.
     *
     * @param string $date YYYY-MM-DD
     * @param bool $after Whether to look after the date, rather than before it
     * @return string|null
     */
    public static function adjacent(string $date, bool $after): ?string
    {
        $today = date('Y-m-d');
        if ($after) {
            $from = date('Y-m-d', strtotime($date . ' +1 day'));
            $to = $today;
        } else {
            // Sessions are never more than a year apart.
            $from = date('Y-m-d', strtotime($date . ' -400 days'));
            $to = min($today, date('Y-m-d', strtotime($date . ' -1 day')));
        }
        if ($from > $to) {
            return null;
        }
        $rows = self::rows(
            'SELECT d FROM (' . self::dates_sql() . ') AS dates ORDER BY d ' . ($after ? 'ASC' : 'DESC') . ' LIMIT 1',
            str_repeat('s', 10),
            array_merge(...array_fill(0, 5, [$from, $to]))
        );

        return $rows[0]['d'] ?? null;
    }

    /**
     * The most recent date with a page.
     *
     * @return string|null
     */
    public static function latest(): ?string
    {
        return self::adjacent(date('Y-m-d', strtotime('+1 day')), false);
    }

    /**
     * Whether a date is during a session (including a day when either chamber met on the floor
     * after it, like a reconvened session).
     *
     * @param string $date
     * @return bool
     */
    private static function in_session(string $date): bool
    {
        $rows = self::rows('SELECT 1
            FROM sessions
            WHERE date_started <= ? AND COALESCE(date_ended, date_started + INTERVAL 100 DAY) >= ?
            LIMIT 1', 'ss', [$date, $date]);

        return count($rows) > 0;
    }

    /**
     * A bill, as days list it.
     *
     * @param array $row With number, year, catch_line, patron_name, and patron_shortname
     * @return array
     */
    private static function bill(array $row): array
    {
        return [
            'number' => $row['number'],
            'year' => (int) $row['year'],
            'catch_line' => html_entity_decode((string) $row['catch_line'], ENT_QUOTES),
            'url' => 'https://www.richmondsunlight.com/bill/' . $row['year'] . '/' . $row['number'] . '/',
            'patron' => !empty($row['patron_name'])
                ? ['name' => $row['patron_name'], 'shortname' => $row['patron_shortname']]
                : null,
        ];
    }

    /**
     * The SQL that joins a bill's chief patron, and the term they were serving on a date (the
     * last parameter). The query must already join `bills`.
     *
     * @return string
     */
    private static function patron_sql(): string
    {
        return '
                LEFT JOIN people
                    ON people.id = bills.chief_patron_id
                LEFT JOIN terms
                    ON terms.id = (
                        SELECT t.id
                        FROM terms AS t
                        WHERE t.person_id = people.id AND t.date_started <= ?
                        ORDER BY t.date_started DESC
                        LIMIT 1)';
    }

    /**
     * Meetings on a date from the schedule (`meetings`) and from dockets, merged into $meetings
     * (by key), and any floor session's time put in $floor_times (by chamber).
     *
     * @param string $date
     * @param array $meetings Meetings, by key
     * @param array $floor_times
     * @param array $dockets Bill IDs on each subcommittee's docket, by bill ID, then chamber
     * @return void
     */
    private static function scheduled_meetings(string $date, array &$meetings, array &$floor_times, array &$dockets): void
    {
        $committees = self::committees();

        $rows = self::rows('SELECT DISTINCT committee_id, time, timedesc, description, location
                FROM meetings
                WHERE date = ? AND ' . self::MEETING_FILTER . '
                ORDER BY time ASC, id ASC', 's', [$date]);

        // The schedule sometimes lists a committee's meeting twice, once without the committee
        // ("Finance and Appropriations - Annual Meeting," and "Senate Finance and Appropriations -
        // Annual Meeting"). The one without is left out.
        $without_chamber = fn(string $description) => self::name_words(preg_replace('/^(?:House|Senate)\s+/i', '', $description));
        $committee_descriptions = [];
        foreach ($rows as $row) {
            if (!empty($row['committee_id'])) {
                $committee_descriptions[] = $without_chamber(html_entity_decode((string) $row['description'], ENT_QUOTES));
            }
        }

        foreach ($rows as $row) {
            $description = trim(html_entity_decode((string) $row['description'], ENT_QUOTES));
            $time = $row['time'] !== null ? substr($row['time'], 0, 5) : null;

            $committee = !empty($row['committee_id']) ? ($committees[(int) $row['committee_id']] ?? null) : null;
            if ($committee === null && empty($row['committee_id'])
                && in_array($without_chamber($description), $committee_descriptions, true)) {
                continue;
            }
            if ($committee === null) {
                if (preg_match(self::FLOOR_MEETING, $description, $matches)) {
                    $chamber = mb_strtolower(strtok($description, ' '));
                    $floor_times[$chamber] ??= $time;
                    continue;
                }
                $chamber = preg_match('/^(House|Senate)\b/i', $description, $matches) ? mb_strtolower($matches[1]) : null;
                $label = trim(preg_replace('/\s+Other$/', '', $description));
                $key = 'meeting|' . self::name_words($label);
                $meetings[$key] ??= self::new_meeting($key, $chamber, 'other', $label, null, null);
            } else {
                // The schedule names a subcommittee only in its description: "House
                // Appropriations-Health & Human Resources Committee."
                if (empty($committee['parent_id'])
                    && preg_match('/^(?:House|Senate)\s+.+?\s*-\s*(.+?)(?:\s+(?:Committee|Subcommittee|Docket|Other))?$/', $description, $matches)) {
                    foreach ($committees as $sub) {
                        if (!empty($sub['parent_id']) && isset($committees[(int) $sub['parent_id']])
                            && $sub['chamber'] === $committee['chamber']
                            && self::names_match($committees[(int) $sub['parent_id']]['name'], $committee['name'])
                            && self::names_match($sub['name'], $matches[1])) {
                            $committee = $sub;
                            break;
                        }
                    }
                }
                $meeting = self::committee_meeting($committee);
                $key = $meeting['key'];
                $meetings[$key] ??= $meeting;
            }
            $meetings[$key]['time'] ??= $time;
            $meetings[$key]['time_description'] ??= !empty($row['timedesc']) ? $row['timedesc'] : null;
            $meetings[$key]['location'] ??= !empty($row['location']) ? html_entity_decode($row['location'], ENT_QUOTES) : null;
        }

        foreach (self::rows('SELECT DISTINCT dockets.committee_id, bills.id, bills.number, bills.catch_line,
                    sessions.year, COALESCE(terms.name_formatted, people.name_formal) AS patron_name,
                    people.shortname AS patron_shortname
                FROM dockets
                LEFT JOIN bills
                    ON dockets.bill_id = bills.id
                LEFT JOIN sessions
                    ON bills.session_id = sessions.id' . self::patron_sql() . '
                WHERE dockets.date = ? AND bills.id IS NOT NULL
                ORDER BY bills.number', 'ss', [$date, $date]) as $row) {
            $committee = $committees[(int) $row['committee_id']] ?? null;
            if ($committee === null) {
                continue;
            }
            $meeting = self::committee_meeting($committee);
            $key = $meeting['key'];
            $meetings[$key] ??= $meeting;
            $meetings[$key]['docket'][(int) $row['id']] = self::bill($row);
            if (!empty($committee['parent_id'])) {
                $dockets[(int) $row['id']][$committee['chamber']] = $key;
            }
        }
    }

    /**
     * Sort meetings by chamber (House first, then the Senate, then joint meetings), and then by
     * committee, with each committee's subcommittees after it.
     *
     * @param array $meetings
     * @return array
     */
    private static function sorted_meetings(array $meetings): array
    {
        $rank = ['house' => 0, 'senate' => 1];
        usort($meetings, function ($a, $b) use ($rank) {
            $sort = fn($m) => [
                $rank[$m['chamber']] ?? 2,
                $m['parent_committee']['name'] ?? $m['committee']['name'] ?? $m['label'],
                $m['type'] === 'subcommittee' ? 1 : 0,
                $m['label'],
            ];
            return $sort($a) <=> $sort($b);
        });

        return $meetings;
    }

    /**
     * A meeting, as the API lists it: its docket is only the bills that it didn't act on, and it
     * loses its key.
     *
     * @param array $meeting
     * @return array
     */
    private static function finished_meeting(array $meeting): array
    {
        $acted_on = array_map(fn($action) => $action['bill']['number'], $meeting['actions']);
        $meeting['docket'] = array_values(array_filter(
            $meeting['docket'],
            fn($bill) => !in_array($bill['number'], $acted_on, true)
        ));
        unset($meeting['key']);

        return $meeting;
    }

    /**
     * A video, as days list it.
     *
     * @param array $video From Video::list_for_api()
     * @return array
     */
    private static function video(array $video): array
    {
        return [
            'slug' => $video['slug'],
            'url' => $video['url'],
            'label' => $video['meeting']['label'],
            'status' => $video['status'],
            'length' => $video['length'],
            'poster' => $video['poster'],
        ];
    }

    /**
     * Everything that happened on a day, for the API: what build() finds, plus the day's
     * highlights, the news about its bills, what people did with them on this site, and its
     * brief (see DayBrief).
     *
     * @param string $date YYYY-MM-DD
     * @return array|null The day, or null if it hasn't happened yet, or nothing happened
     */
    public static function record_for_api(string $date): ?array
    {
        $day = self::build($date);
        if ($day === null) {
            return null;
        }

        $bill_ids = array_keys(self::actions_by_bill($day));
        $highlights = $day['in_session'] ? DayHighlights::for_api($date) : ['highlights' => [], 'updated' => null];
        $day['highlights'] = $highlights['highlights'];
        $day['highlights_updated'] = $highlights['updated'];
        $day['news'] = DayHighlights::news_for_api($date, $bill_ids);
        $day['site_activity'] = DayHighlights::site_activity_for_api($date, $bill_ids);
        $day['brief'] = $day['in_session'] ? DayBrief::for_api($date) : null;

        return self::without_ids($day);
    }

    /**
     * Each bill that something was done to on a day, and what was done to it, and where, for
     * scoring (see DayHighlights). These are the actions that the day's page shows.
     *
     * @param string $date YYYY-MM-DD
     * @return array|null in_session, and bills (by bill ID: the bill, its chief patron's ID, and
     *                    its actions, each with chamber, where (the meeting's label, or null for
     *                    the floor), place ("floor", "committee", "subcommittee", or "other"), and
     *                    vote_id), or null if there's no page for the day
     */
    public static function bills_acted_on(string $date): ?array
    {
        $day = self::build($date);
        if ($day === null) {
            return null;
        }

        return ['in_session' => $day['in_session'], 'bills' => self::actions_by_bill($day)];
    }

    /**
     * A built day's actions, by bill ID.
     *
     * @param array $day From build()
     * @return array
     */
    private static function actions_by_bill(array $day): array
    {
        $bills = [];
        $add = function (array $action, ?string $chamber, ?string $where, string $place) use (&$bills) {
            $id = $action['_bill_id'];
            $bills[$id] ??= ['bill' => $action['bill'], 'patron_id' => $action['_patron_id'], 'actions' => []];
            $bills[$id]['actions'][] = array_merge($action, [
                'chamber' => $chamber,
                'where' => $where,
                'place' => $place,
                'vote_id' => $action['_vote_id'],
            ]);
        };
        foreach ($day['chambers'] as $chamber) {
            foreach ($chamber['actions'] as $action) {
                $add($action, $chamber['chamber'], null, 'floor');
            }
        }
        foreach ($day['meetings'] as $meeting) {
            foreach ($meeting['actions'] as $action) {
                $add($action, $meeting['chamber'], $meeting['label'], $meeting['type']);
            }
        }

        return $bills;
    }

    /**
     * A day without the IDs that build() keeps on each action, which the API doesn't show.
     *
     * @param array $day
     * @return array
     */
    private static function without_ids(array $day): array
    {
        $strip = fn(array $actions) => array_map(
            fn($action) => array_diff_key($action, ['_bill_id' => 0, '_vote_id' => 0, '_patron_id' => 0]),
            $actions
        );
        foreach ($day['chambers'] as &$chamber) {
            $chamber['actions'] = $strip($chamber['actions']);
        }
        unset($chamber);
        foreach ($day['meetings'] as &$meeting) {
            $meeting['actions'] = $strip($meeting['actions']);
        }
        unset($meeting);

        return $day;
    }

    /**
     * Everything that happened on a day, from the legislature's records. Each action keeps its
     * bill's ID, its chief patron's ID, and its vote's ID (_bill_id, _patron_id, and _vote_id).
     *
     * @param string $date YYYY-MM-DD
     * @return array|null The day, or null if it hasn't happened yet, or nothing happened
     */
    private static function build(string $date): ?array
    {
        if (!self::is_date($date) || $date > date('Y-m-d')) {
            return null;
        }
        self::$committees = null;
        $committees = self::committees();

        $chambers = [];
        $chamber = fn(string $name) => [
            'chamber' => $name,
            'floor' => false,
            'time' => null,
            'videos' => [],
            'minutes' => null,
            'actions' => [],
        ];
        $meetings = [];
        $floor_times = [];
        $dockets = [];

        // The day's videos.
        foreach (Video::list_for_api(['date' => $date]) as $video) {
            $meeting = $video['meeting'];
            if ($meeting['type'] === 'floor') {
                $chambers[$video['chamber']] ??= $chamber($video['chamber']);
                $chambers[$video['chamber']]['videos'][] = self::video($video);
                continue;
            }
            if ($meeting['type'] === 'subcommittee') {
                $key = self::meeting_key($video['chamber'], $meeting['parent_committee']['name'], $meeting['committee']['name']);
            } elseif ($meeting['type'] === 'committee') {
                $key = self::meeting_key($video['chamber'], null, $meeting['committee']['name']);
            } else {
                $key = 'video|' . $video['slug'];
            }
            $meetings[$key] ??= self::new_meeting(
                $key,
                $video['chamber'],
                $meeting['type'] === 'unknown' ? 'other' : $meeting['type'],
                $meeting['label'],
                $meeting['committee'],
                $meeting['parent_committee']
            );
            $meetings[$key]['videos'][] = self::video($video);
        }

        // The minutes of each chamber's floor session.
        foreach (self::rows('SELECT chamber, text FROM minutes WHERE date = ?', 's', [$date]) as $row) {
            $chambers[$row['chamber']] ??= $chamber($row['chamber']);
            $chambers[$row['chamber']]['minutes'] = stripslashes((string) $row['text']);
        }

        self::scheduled_meetings($date, $meetings, $floor_times, $dockets);

        // What happened to each bill.
        $rows = self::rows('SELECT bills_status.id, bills_status.status, bills_status.date,
                    bills_status.chamber, bills_status.lis_vote_id, votes.id AS vote_id,
                    votes.committee_id AS vote_committee_id, bills.id AS bill_id, bills.number,
                    bills.chamber AS bill_chamber, bills.catch_line, bills.chief_patron_id, sessions.year,
                    COALESCE(terms.name_formatted, people.name_formal) AS patron_name,
                    people.shortname AS patron_shortname
                FROM bills_status
                LEFT JOIN bills
                    ON bills_status.bill_id = bills.id
                LEFT JOIN sessions
                    ON bills.session_id = sessions.id
                LEFT JOIN votes
                    ON votes.lis_id = bills_status.lis_vote_id AND votes.session_id = bills_status.session_id' . self::patron_sql() . '
                WHERE bills_status.date >= ? AND bills_status.date < DATE(?) + INTERVAL 1 DAY
                    AND bills.id IS NOT NULL
                ORDER BY bills_status.date ASC, bills_status.id ASC', 'sss', [$date, $date, $date]);

        // Bills' committee actions, by bill ID, so that what a committee did with a bill can be
        // found by the bill's other actions ("Incorporates HB 12").
        $committee_actions = [];
        $actions = [];
        foreach ($rows as $row) {
            $what = self::classify((string) $row['status']);
            if ($what['type'] === 'paperwork') {
                continue;
            }
            $action_chamber = $row['chamber'];
            if (empty($action_chamber)) {
                $action_chamber = preg_match('/\b(House|Senate)\b/', $row['status'], $matches)
                    ? mb_strtolower($matches[1])
                    : $row['bill_chamber'];
            }
            $time = substr((string) $row['date'], 11, 5);
            $vote = $what['vote'];
            if ($vote !== null) {
                $vote['close'] = self::is_close($vote);
                $vote['url'] = !empty($row['vote_id']) && preg_match('/^[A-Za-z0-9]{6,8}$/', (string) $row['lis_vote_id'])
                    ? 'https://www.richmondsunlight.com/bill/' . $row['year'] . '/' . $row['number'] . '/'
                        . mb_strtolower($row['lis_vote_id']) . '/'
                    : null;
            }
            $action = [
                'bill' => self::bill($row),
                'text' => $what['text'],
                'type' => $what['type'],
                'outcome' => $what['outcome'],
                'time' => $time !== '00:00' && $time !== '' ? $time : null,
                'vote' => $vote,
                '_bill_id' => (int) $row['bill_id'],
                '_patron_id' => !empty($row['chief_patron_id']) ? (int) $row['chief_patron_id'] : null,
                '_vote_id' => !empty($row['vote_id']) ? (int) $row['vote_id'] : null,
            ];

            // Where it happened: in a committee, a subcommittee, or the chamber.
            $key = null;
            if ($what['type'] === 'committee') {
                $committee = self::committee_named($action_chamber, $what['committee'], $date);
                if ($committee !== null) {
                    $meeting = self::committee_meeting($committee);
                } else {
                    $meeting = self::new_meeting(
                        self::meeting_key($action_chamber, null, $what['committee']),
                        $action_chamber,
                        'committee',
                        ucfirst($action_chamber) . ' ' . $what['committee'] . ' Committee',
                        ['name' => $what['committee'], 'shortname' => null, 'url' => null],
                        null
                    );
                }
                $key = $meeting['key'];
                $meetings[$key] ??= $meeting;
            } elseif ($what['type'] === 'subcommittee') {
                $key = $dockets[(int) $row['bill_id']][$action_chamber] ?? null;
                $vote_committee = $committees[(int) $row['vote_committee_id']] ?? null;
                if ($key === null && $vote_committee !== null && !empty($vote_committee['parent_id'])
                    && $vote_committee['chamber'] === $action_chamber) {
                    $meeting = self::committee_meeting($vote_committee);
                    $key = $meeting['key'];
                    $meetings[$key] ??= $meeting;
                }
                if ($key === null) {
                    // A subcommittee that can't be identified.
                    $key = $action_chamber . '|?|';
                    $meetings[$key] ??= self::new_meeting(
                        $key,
                        $action_chamber,
                        'subcommittee',
                        ucfirst($action_chamber) . ' subcommittees',
                        null,
                        null
                    );
                }
            }

            $actions[] = ['action' => $action, 'chamber' => $action_chamber, 'key' => $key, 'bill_id' => (int) $row['bill_id']];
            if ($key !== null) {
                $committee_actions[(int) $row['bill_id']] ??= $key;
            }
        }

        foreach ($actions as $item) {
            $key = $item['key'];
            if ($key === null && $item['action']['type'] === 'incorporates') {
                $key = $committee_actions[$item['bill_id']] ?? null;
            }
            if ($key !== null) {
                $list = &$meetings[$key]['actions'];
            } else {
                $chambers[$item['chamber']] ??= $chamber($item['chamber']);
                $list = &$chambers[$item['chamber']]['actions'];
            }

            // LIS often records an action twice, once with its tally and once without. Keep one,
            // with the tally.
            $duplicate = false;
            foreach ($list as $i => $other) {
                if ($other['bill']['number'] === $item['action']['bill']['number'] && $other['text'] === $item['action']['text']) {
                    $duplicate = true;
                    if ($other['vote'] === null && $item['action']['vote'] !== null) {
                        $list[$i]['vote'] = $item['action']['vote'];
                    }
                    $list[$i]['_vote_id'] ??= $item['action']['_vote_id'];
                    if ($other['time'] === null) {
                        $list[$i]['time'] = $item['action']['time'];
                    }
                    break;
                }
            }
            if (!$duplicate) {
                $list[] = $item['action'];
            }
            unset($list);
        }

        // Whether each chamber met on the floor.
        foreach ($chambers as $name => &$floor) {
            $floor['floor'] = count($floor['videos']) > 0 || $floor['minutes'] !== null
                || count(array_filter($floor['actions'], fn($action) => in_array($action['type'], ['passed', 'failed', 'reading', 'procedural'], true)
                    || ($action['vote'] !== null && $action['type'] !== 'referred'))) > 0;
            $floor['time'] = $floor_times[$name] ?? null;
        }
        unset($floor);

        // Outside of a session, there are only meetings.
        $in_session = self::in_session($date) || count(array_filter($chambers, fn($c) => $c['floor'])) > 0;
        if (!$in_session) {
            $chambers = [];
        }

        $meetings = array_map([self::class, 'finished_meeting'], self::sorted_meetings($meetings));

        $chambers = array_values(array_filter(
            [$chambers['house'] ?? null, $chambers['senate'] ?? null],
            fn($c) => $c !== null && ($c['floor'] || count($c['actions']) > 0)
        ));

        if (count($chambers) === 0 && count($meetings) === 0) {
            return null;
        }

        $next = self::adjacent($date, true);
        $day = [
            'date' => $date,
            'url' => self::url($date),
            'in_session' => $in_session,
            'previous' => self::adjacent($date, false),
            'next' => $next,
            'chambers' => $chambers,
            'meetings' => $meetings,
            'coming_up' => null,
        ];

        // On the most recent day, what's scheduled next.
        if ($next === null) {
            $day['coming_up'] = self::coming_up();
        }

        return $day;
    }

    /**
     * The next day that has anything scheduled, after today, and its meetings and dockets.
     *
     * @return array|null date, schedule_url, and meetings
     */
    private static function coming_up(): ?array
    {
        $today = date('Y-m-d');
        $rows = self::rows('SELECT MIN(d) AS d FROM (
                (SELECT MIN(date) AS d FROM dockets WHERE date > ?)
                UNION ALL (SELECT MIN(date) FROM meetings WHERE date > ? AND ' . self::MEETING_FILTER . ')
            ) AS dates', 'ss', [$today, $today]);
        $date = $rows[0]['d'] ?? null;
        if ($date === null) {
            return null;
        }

        $meetings = [];
        $floor_times = [];
        $dockets = [];
        self::scheduled_meetings($date, $meetings, $floor_times, $dockets);

        return [
            'date' => $date,
            'schedule_url' => 'https://www.richmondsunlight.com/schedule/' . str_replace('-', '/', $date) . '/',
            'floor' => $floor_times,
            'meetings' => array_map([self::class, 'finished_meeting'], self::sorted_meetings($meetings)),
        ];
    }

    /**
     * Every day in a year that has a page, for the API, with how much happened on it.
     *
     * @param int $year
     * @return array Each day's date, url, the chambers that met on the floor, and how many videos,
     *               meetings, and decisions on bills there were
     */
    public static function list_for_api(int $year): array
    {
        $from = $year . '-01-01';
        $to = min($year . '-12-31', date('Y-m-d'));
        if ($from > $to) {
            return [];
        }

        $days = [];
        $day = function (string $date) use (&$days) {
            $days[$date] ??= ['date' => $date, 'url' => self::url($date), 'floor' => [], 'videos' => 0, 'meetings' => 0, 'actions' => 0];
            return $date;
        };

        foreach (self::rows('SELECT date, chamber, committee_id, title
                FROM files
                WHERE type = "video" AND slug IS NOT NULL AND date BETWEEN ? AND ?', 'ss', [$from, $to]) as $row) {
            $date = $day($row['date']);
            $days[$date]['videos']++;
            if (empty($row['committee_id']) && Video::meeting_label($row['title'], $row['chamber']) === ucfirst((string) $row['chamber']) . ' floor session') {
                $days[$date]['floor'][] = $row['chamber'];
            }
        }
        foreach (self::rows('SELECT date, chamber FROM minutes WHERE date BETWEEN ? AND ?', 'ss', [$from, $to]) as $row) {
            $days[$day($row['date'])]['floor'][] = $row['chamber'];
        }
        foreach (self::rows('SELECT dockets.date, COUNT(DISTINCT dockets.committee_id) AS meetings
                FROM dockets
                JOIN bills ON bills.id = dockets.bill_id
                JOIN committees ON committees.id = dockets.committee_id
                WHERE dockets.date BETWEEN ? AND ?
                GROUP BY dockets.date', 'ss', [$from, $to]) as $row) {
            $days[$day($row['date'])]['meetings'] = (int) $row['meetings'];
        }
        foreach (self::rows('SELECT date, COUNT(DISTINCT COALESCE(committee_id, description)) AS meetings
                FROM meetings
                WHERE date BETWEEN ? AND ? AND ' . self::MEETING_FILTER . '
                GROUP BY date', 'ss', [$from, $to]) as $row) {
            $date = $day($row['date']);
            $days[$date]['meetings'] = max($days[$date]['meetings'], (int) $row['meetings']);
        }
        foreach (self::rows('SELECT DATE(bills_status.date) AS date, bills_status.chamber, COUNT(*) AS actions,
                    SUM(bills_status.status REGEXP "' . self::FLOOR_ACTION . '"
                        OR (bills_status.status REGEXP "[0-9]+-Y [0-9]+-N|voice vote"
                            AND bills_status.status NOT REGEXP "' . self::COMMITTEE_ACTION . '")) AS floor
                FROM bills_status
                JOIN bills ON bills.id = bills_status.bill_id
                WHERE bills_status.date >= ? AND bills_status.date < DATE(?) + INTERVAL 1 DAY
                    AND bills_status.status REGEXP "' . self::DECISIVE_ACTION . '"
                GROUP BY DATE(bills_status.date), bills_status.chamber', 'ss', [$from, $to]) as $row) {
            $date = $day($row['date']);
            $days[$date]['actions'] += (int) $row['actions'];
            if ((int) $row['floor'] > 0 && in_array($row['chamber'], ['house', 'senate'], true)) {
                $days[$date]['floor'][] = $row['chamber'];
            }
        }

        ksort($days);
        foreach ($days as &$entry) {
            $entry['floor'] = array_values(array_intersect(['house', 'senate'], $entry['floor']));
        }
        unset($entry);

        return array_values($days);
    }

    /**
     * How much happened on a day, in a sentence or two each: which chambers met on the floor, how
     * many committees met, and how many bills moved forward or were stopped.
     *
     * @param array $day From the API
     * @return string[] Sentences
     */
    public static function summary(array $day): array
    {
        $floor_chambers = array_map(fn($c) => ucfirst($c['chamber']), array_filter($day['chambers'], fn($c) => $c['floor']));
        $committee_meetings = array_filter($day['meetings'], fn($m) => $m['type'] !== 'other');
        $outcomes = ['advanced' => [], 'stopped' => []];
        foreach (array_merge(...array_map(fn($c) => $c['actions'], $day['chambers']), ...array_map(fn($m) => $m['actions'], $day['meetings'])) as $action) {
            if (isset($outcomes[$action['outcome']])) {
                $outcomes[$action['outcome']][$action['bill']['number']] = true;
            }
        }

        $summary = [];
        if (count($floor_chambers) === 2) {
            $summary[] = 'The House and the Senate met in floor sessions.';
        } elseif (count($floor_chambers) === 1) {
            $summary[] = 'The ' . reset($floor_chambers) . ' met in a floor session.';
        }
        if (count($committee_meetings) > 0) {
            $summary[] = count($committee_meetings) === 1
                ? 'One committee or subcommittee met.'
                : count($committee_meetings) . ' committees and subcommittees met.';
        } elseif (count($day['meetings']) > 0) {
            $summary[] = count($day['meetings']) === 1 ? 'One meeting was held.' : count($day['meetings']) . ' meetings were held.';
        }
        $advanced = count($outcomes['advanced']);
        $stopped = count($outcomes['stopped']);
        $bills = fn(int $n) => [0 => 'No bills', 1 => 'One bill'][$n] ?? $n . ' bills';
        if ($advanced > 0 && $stopped > 0) {
            $summary[] = $bills($advanced) . ' moved forward, and ' . ($stopped === 1 ? 'one was' : $stopped . ' were') . ' stopped.';
        } elseif ($advanced > 0) {
            $summary[] = $bills($advanced) . ' moved forward.';
        } elseif ($stopped > 0) {
            $summary[] = $bills($stopped) . ($stopped === 1 ? ' was stopped.' : ' were stopped.');
        }

        return $summary;
    }

    /**
     * Get a day from the API.
     *
     * @param string $date YYYY-MM-DD, or "latest" for the most recent day
     * @return array|null The day, or null if there's no page for it (or the API can't be reached)
     */
    public static function from_api(string $date): ?array
    {
        if ($date !== 'latest' && !self::is_date($date)) {
            return null;
        }
        $json = get_content(API_URL . '1.1/day/' . $date . '.json');
        $day = $json !== false ? json_decode($json, true) : null;
        if (!is_array($day) || isset($day['error']) || empty($day['date'])) {
            return null;
        }

        return $day;
    }

    /**
     * Get a year's days from the API.
     *
     * @param int $year
     * @return array The days (none, if the API can't be reached)
     */
    public static function list_from_api(int $year): array
    {
        $json = get_content(API_URL . '1.1/days.json?year=' . $year);
        $days = $json !== false ? json_decode($json, true) : null;
        if (!is_array($days) || isset($days['error'])) {
            return [];
        }

        return $days;
    }

    /**
     * The minutes, as HTML, with each bill number linked to its bill.
     *
     * @param string $minutes The minutes' text
     * @param int $year The session's year
     * @return string
     */
    public static function minutes_html(string $minutes, int $year): string
    {
        $html = nl2p(htmlspecialchars($minutes, ENT_NOQUOTES, 'UTF-8', false));

        // This doesn't catch every bill number. Lists are often presented as "HRs 1 and 2," for
        // instance, and this can't pick those up.
        return preg_replace_callback(
            '/\b(HR|HB|SJR|SB|SR|HJ|HJR|SJ) ([0-9]+)\b/',
            fn($m) => '<a href="/bill/' . $year . '/' . mb_strtolower(str_replace(['HJR', 'SJR'], ['HJ', 'SJ'], $m[1]) . $m[2]) . '/">' . $m[0] . '</a>',
            $html
        );
    }
}
