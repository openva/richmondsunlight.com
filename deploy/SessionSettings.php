<?php

declare(strict_types=1);

/**
 * What the session settings in htdocs/includes/settings-default.inc.php should be, for
 * deploy/propose_session.php, which the session-update workflow runs to propose changing them.
 *
 * The settings move to a new regular session once bills may be prefiled for it: when LIS's
 * prefiling period has begun, it's November or later, LIS publishes bills for it, and rs-machine
 * has added it to the sessions table (which it does from LIS's list of sessions, and publishes as
 * downloads.richmondsunlight.com/sessions.json, the only place the session's ID can be read from
 * outside the database). Until then, the current session's settings are kept up to date, such as
 * when it adjourns. Special sessions are never switched to, since one runs alongside a regular
 * session; rs-machine announces them in Slack instead.
 *
 * LIS's sessions are from its API's GetSessionListAsync. A session's code is "20271": the year,
 * then 1 for the regular session, or 2, 3, … for special sessions. SESSION_LIS_ID is the code
 * without its leading "20".
 */
final class SessionSettings
{
    /** The settings, in the order they're in the file, and whether each is a number. */
    public const SETTINGS = [
        'SESSION_ID'         => true,
        'SESSION_SUFFIX'     => false,
        'SESSION_LIS_ID'     => false,
        'SESSION_LIS_API_ID' => false,
        'SESSION_YEAR'       => true,
        'SESSION_START'      => false,
        'SESSION_END'        => false,
    ];

    /**
     * The session settings in a settings file.
     *
     * @return array<string,string>
     */
    public static function read(string $file): array
    {
        $values = [];
        foreach (array_keys(self::SETTINGS) as $name) {
            $matches = self::matches($file, $name);
            if (count($matches) !== 1) {
                throw new UnexpectedValueException('Expected one ' . $name . ' in the settings, but found '
                    . count($matches));
            }
            $values[$name] = trim($matches[0][1], '\'"');
        }

        return $values;
    }

    /**
     * The settings file, with the session settings replaced by these.
     *
     * @param array<string,string> $values
     */
    public static function write(string $file, array $values): string
    {
        foreach (self::SETTINGS as $name => $numeric) {
            if (!array_key_exists($name, $values)) {
                throw new InvalidArgumentException('No value was given for ' . $name);
            }
            $value = (string) $values[$name];
            if ($numeric ? preg_match('/^\d+$/', $value) !== 1 : preg_match('/^[\w-]*$/', $value) !== 1) {
                throw new InvalidArgumentException('“' . $value . '” is not a valid ' . $name);
            }
            $literal = $numeric ? $value : "'" . $value . "'";
            $file = preg_replace(
                self::pattern($name),
                "rs_define('" . $name . "', " . $literal . ');',
                $file,
                -1,
                $count
            );
            if ($count !== 1) {
                throw new UnexpectedValueException('Expected one ' . $name . ' in the settings, but found ' . $count);
            }
        }

        return $file;
    }

    /**
     * What the settings should be.
     *
     * @param array<string,string>             $current   The settings as they are (read()).
     * @param list<array<string,mixed>>        $lis       Sessions from GetSessionListAsync.
     * @param list<array<string,mixed>>        $published Rows of sessions, from sessions.json.
     * @param callable(string):bool            $hasBills  Whether LIS publishes bills for a code.
     * @return array{values:array<string,string>,session:string,switch:bool,notes:list<string>}
     */
    public static function propose(
        array $current,
        array $lis,
        array $published,
        callable $hasBills,
        DateTimeImmutable $today
    ): array {
        $rows = [];
        foreach ($published as $row) {
            if (isset($row['lis_id'])) {
                $rows[(string) $row['lis_id']] = $row;
            }
        }
        $sessions = [];
        foreach ($lis as $session) {
            $code = (string) ($session['SessionCode'] ?? '');
            if (preg_match('/^\d{5}$/', $code) === 1) {
                $sessions[substr($code, 2)] = $session;
            }
        }

        $notes = [];
        $currentYear = (int) $current['SESSION_YEAR'];
        $today = $today->setTime(0, 0);

        // The newest regular session that's ready, if any is newer than the current session.
        $next = null;
        foreach ($sessions as $lisId => $session) {
            $year = (int) substr((string) $session['SessionCode'], 0, 4);
            if (
                ($session['SessionType'] ?? '') !== 'Regular' || $year <= $currentYear
                || ($next !== null && $year <= (int) $next['SessionYear'])
            ) {
                continue;
            }
            $events = self::events($session);
            $name = $year . ' Regular Session';
            if (!isset($events['Session Start'])) {
                continue;
            }
            $prefile = $events['Prefile Date'] ?? (new DateTimeImmutable($events['Session Start']))
                ->modify('-60 days')->format('Y-m-d');
            $opens = max($prefile, ($year - 1) . '-11-01');
            if ($today->format('Y-m-d') < $opens) {
                $notes[] = 'The ' . $name . ' will be ready to switch to on ' . $opens . '.';
                continue;
            }
            if (!isset($rows[$lisId])) {
                $notes[] = 'The ' . $name . ' isn’t in sessions.json yet, so its ID isn’t known.';
                continue;
            }
            if (!$hasBills((string) $session['SessionCode'])) {
                $notes[] = 'LIS hasn’t published bills for the ' . $name . ' yet.';
                continue;
            }
            $next = $session;
        }

        if ($next !== null) {
            $lisId = substr((string) $next['SessionCode'], 2);
            [$values, $provisional] = self::values($rows[$lisId], $next, null);
            $session = $next['SessionYear'] . ' Regular Session';
            if ($provisional) {
                $notes[] = 'LIS doesn’t have the date the session will adjourn, so SESSION_END is '
                    . 'projected, as ' . (((int) $next['SessionYear'] % 2) === 0 ? '60' : '46')
                    . ' days long. It will be corrected once LIS records the adjournment.';
            }

            return ['values' => $values, 'session' => $session, 'switch' => true, 'notes' => $notes];
        }

        // Otherwise, keep the current session's settings up to date.
        $lisId = $current['SESSION_LIS_ID'];
        $session = self::name($current);
        if (!isset($rows[$lisId])) {
            $notes[] = 'The current session (LIS ' . $lisId . ') isn’t in sessions.json, so its settings '
                . 'are left as they are.';
            return ['values' => $current, 'session' => $session, 'switch' => false, 'notes' => $notes];
        }
        [$values] = self::values($rows[$lisId], $sessions[$lisId] ?? null, $current['SESSION_END']);

        return ['values' => $values, 'session' => $session, 'switch' => false, 'notes' => $notes];
    }

    /**
     * The last day of a session that LIS hasn't recorded the adjournment of: 60 days in
     * even-numbered years and 46 days in odd ones, as the Constitution of Virginia (Art. IV, § 6)
     * and the customary extension of odd-year sessions make them.
     */
    public static function projectedEnd(string $start): string
    {
        $date = new DateTimeImmutable($start);

        return $date->modify(((int) $date->format('Y') % 2 === 0 ? '+59' : '+45') . ' days')->format('Y-m-d');
    }

    /**
     * "2027 Regular Session", "2026 Special Session 1".
     *
     * @param array<string,string> $settings
     */
    public static function name(array $settings): string
    {
        return $settings['SESSION_YEAR'] . ($settings['SESSION_SUFFIX'] === ''
            ? ' Regular Session'
            : ' Special Session ' . $settings['SESSION_SUFFIX']);
    }

    /**
     * A session's settings, from its row of sessions, and from LIS where the row lacks something.
     *
     * @param array<string,mixed>      $row
     * @param array<string,mixed>|null $lis
     * @param string|null              $end The end date to keep if neither knows it, or null to
     *                                      project one.
     * @return array{0:array<string,string>,1:bool} The settings, and whether the end is projected.
     */
    private static function values(array $row, ?array $lis, ?string $end): array
    {
        $events = $lis === null ? [] : self::events($lis);
        $start = (string) ($row['date_started'] ?? $events['Session Start'] ?? '');
        $known = $row['date_ended'] ?? $events['Adjournment'] ?? null;
        $provisional = $known === null && $end === null;
        $apiId = $row['lis_api_id'] ?? $lis['SessionID'] ?? '';

        return [[
            'SESSION_ID'         => (string) $row['id'],
            'SESSION_SUFFIX'     => (string) ($row['suffix'] ?? ''),
            'SESSION_LIS_ID'     => (string) $row['lis_id'],
            'SESSION_LIS_API_ID' => (string) $apiId,
            'SESSION_YEAR'       => (string) $row['year'],
            'SESSION_START'      => $start,
            'SESSION_END'        => (string) ($known ?? $end ?? self::projectedEnd($start)),
        ], $provisional];
    }

    /**
     * A session's events' dates, by their names ("Session Start"), since their types are
     * inconsistent (2026's Adjournment has the type of a Closing).
     *
     * @param array<string,mixed> $session
     * @return array<string,string>
     */
    private static function events(array $session): array
    {
        $events = [];
        foreach ($session['SessionEvents'] ?? [] as $event) {
            if (
                isset($event['DisplayName'])
                && preg_match('/^\d{4}-\d{2}-\d{2}/', (string) ($event['ActualDate'] ?? ''), $match) === 1
            ) {
                $events[(string) $event['DisplayName']] = $match[0];
            }
        }

        return $events;
    }

    /** @return list<array<int,string>> */
    private static function matches(string $file, string $name): array
    {
        preg_match_all(self::pattern($name), $file, $matches, PREG_SET_ORDER);

        return array_map(static fn($m) => [$m[0], $m[1]], $matches);
    }

    private static function pattern(string $name): string
    {
        return '/^rs_define\(\s*\'' . $name . '\'\s*,\s*([^)]*?)\s*\);/m';
    }
}
