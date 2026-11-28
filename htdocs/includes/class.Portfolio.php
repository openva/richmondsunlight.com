<?php

/**
 * Photosynthesis portfolios: users' lists of bills, with their notes on each bill. A portfolio is
 * a row in dashboard_portfolios, identified publicly by its five-character hash; its bills are in
 * dashboard_bills; a smart portfolio (one that fills itself) has a watch list of criteria, in
 * dashboard_watch_lists. See docs/portfolios.md.
 *
 * Everything here that changes a portfolio is done only for the portfolio's owner: each method
 * takes the user's ID, and does nothing to anybody else's portfolio. Every query is prepared.
 */
class Portfolio
{
    /**
     * The characters of a portfolio's hash. (The URL rewrite rules allow [a-z0-9].)
     */
    const HASH_CHARACTERS = 'bcdfghjklmnpqrstvxyz0123456789';

    /**
     * How long a hash is.
     */
    const HASH_LENGTH = 5;

    /**
     * How many times to try a new hash, if one is already taken, before giving up.
     */
    const HASH_ATTEMPTS = 10;

    /**
     * MySQL's error for a duplicate key.
     */
    const DUPLICATE_KEY = 1062;

    /**
     * The fields of a portfolio that its owner sets, and the values each can have (null for any
     * text).
     */
    const FIELDS = [
        'name' => null,
        'notes' => null,
        'public' => ['y', 'n'],
        'notify' => ['none', 'daily'],
    ];

    /**
     * A smart portfolio's criteria, as dashboard_watch_lists stores them: a tag, a patron (a
     * people.id, as bills.chief_patron_id is, so it follows them from term to term), and a
     * committee (a full committee's ID). A bill has to match all that are set.
     */
    const CRITERIA = ['tag', 'patron_id', 'committee_id'];

    /**
     * The referrals of the current session's bills to each committee (see referrals()).
     */
    private static ?array $referrals = null;

    /**
     * The HTML Purifier that cleans notes (see notes_html()).
     */
    private static ?HTMLPurifier $purifier = null;

    /**
     * A new, random hash. It may already be taken (see create()).
     */
    public static function new_hash(): string
    {
        $hash = '';
        $last = strlen(self::HASH_CHARACTERS) - 1;
        for ($i = 0; $i < self::HASH_LENGTH; $i++) {
            $hash .= self::HASH_CHARACTERS[random_int(0, $last)];
        }
        return $hash;
    }

    /**
     * Whether this looks like a portfolio's hash.
     */
    public static function is_hash($hash): bool
    {
        return is_string($hash) && preg_match('/^[a-z0-9]{5}$/', $hash) === 1;
    }

    /**
     * A user's portfolios, by name, each with its type ("normal" or "smart").
     *
     * @param bool $normal_only Leave out smart portfolios (bills can't be added to those).
     * @return array[] Each with id, hash, name, notes, notify, public, watch_list_id, and type.
     */
    public static function for_user(int $user_id, bool $normal_only = false): array
    {
        $sql = 'SELECT p.id, p.hash, p.name, p.notes, p.notify, p.public, p.watch_list_id,
                w.' . implode(', w.', self::CRITERIA) . '
                FROM dashboard_portfolios p
                LEFT JOIN dashboard_watch_lists w ON w.id = p.watch_list_id AND w.user_id = p.user_id
                WHERE p.user_id = ?' . ($normal_only ? ' AND p.watch_list_id IS NULL' : '') . '
                ORDER BY p.name ASC, p.id ASC';
        $result = mysqli_execute_query($GLOBALS['db'], $sql, [$user_id]);
        if ($result === false) {
            return [];
        }
        $portfolios = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $criteria = array_intersect_key($row, array_flip(self::CRITERIA));
            $portfolio = self::typed(array_diff_key($row, $criteria));
            if ($portfolio['type'] == 'smart') {
                $portfolio['criteria'] = $criteria;
            }
            $portfolios[] = $portfolio;
        }
        return $portfolios;
    }

    /**
     * Whether this user (as get_user() gives them) can keep portfolios: anybody with an account.
     * (Commenting without an account makes a user without a password, who can't log in again.)
     */
    public static function can_use($user): bool
    {
        return is_array($user) && !empty($user['id']) && !empty($user['password']);
    }

    /**
     * Make sure that a user has Photosynthesis settings (a dashboard_user_data row), which email
     * needs: whether it's on, and the hash in its unsubscribe link. They're made when a user first
     * makes a portfolio or adds a bill.
     */
    public static function ensure_user_data(int $user_id): void
    {
        $result = mysqli_execute_query($GLOBALS['db'], 'SELECT 1 FROM dashboard_user_data WHERE user_id = ?', [$user_id]);
        if ($result === false || mysqli_num_rows($result) > 0) {
            return;
        }
        $hash = '';
        for ($i = 0; $i < 8; $i++) {
            $hash .= self::HASH_CHARACTERS[random_int(0, strlen(self::HASH_CHARACTERS) - 1)];
        }
        try {
            mysqli_execute_query(
                $GLOBALS['db'],
                'INSERT IGNORE INTO dashboard_user_data
                SET user_id = ?, email_active = "y", type = "free", last_access = NOW(), unsub_hash = ?,
                date_created = NOW()',
                [$user_id, $hash]
            );
        } catch (mysqli_sql_exception $e) {
            // The user doesn't exist (a test, or a deleted account).
        }
    }

    /**
     * A smart portfolio's criteria, in words, as HTML: "Bills tagged <em>education</em>, in the
     * House Education Committee".
     */
    public static function describe(array $criteria): string
    {
        $parts = [];
        if (!empty($criteria['tag'])) {
            $parts[] = 'tagged <em>' . self::text($criteria['tag']) . '</em>';
        }
        if (!empty($criteria['patron_id'])) {
            $result = mysqli_execute_query(
                $GLOBALS['db'],
                'SELECT name FROM people WHERE id = ?',
                [(int) $criteria['patron_id']]
            );
            $person = $result ? mysqli_fetch_assoc($result) : null;
            $parts[] = 'patroned by ' . self::text($person ? $person['name'] : 'a legislator who’s no longer listed');
        }
        if (!empty($criteria['committee_id'])) {
            $result = mysqli_execute_query(
                $GLOBALS['db'],
                'SELECT name, chamber FROM committees WHERE id = ?',
                [(int) $criteria['committee_id']]
            );
            $committee = $result ? mysqli_fetch_assoc($result) : null;
            $parts[] = $committee
                ? 'in the ' . self::text(ucfirst($committee['chamber']) . ' ' . $committee['name']) . ' Committee'
                : 'in a committee that no longer exists';
        }
        return count($parts) > 0 ? 'Bills ' . implode(', ', $parts) : 'Bills matching nothing yet';
    }

    /**
     * Which of a user's portfolios have this bill.
     *
     * @return array[] By portfolio ID: its record's ID (record_id), and whether it's hidden from
     *     that (smart) portfolio (excluded).
     */
    public static function containing(int $user_id, int $bill_id): array
    {
        $result = mysqli_execute_query(
            $GLOBALS['db'],
            'SELECT id, portfolio_id, excluded FROM dashboard_bills WHERE user_id = ? AND bill_id = ?',
            [$user_id, $bill_id]
        );
        $records = [];
        while ($result && ($row = mysqli_fetch_assoc($result))) {
            $records[(int) $row['portfolio_id']] = ['record_id' => (int) $row['id'], 'excluded' => $row['excluded'] == 'y'];
        }
        return $records;
    }

    /**
     * The people (and organizations) tracking a bill in a public portfolio, each once, with the
     * oldest of their public portfolios that has it.
     *
     * @return array[] Each with name (an organization's, or the person's first name and initial)
     *     and hash.
     */
    public static function trackers(int $bill_id, ?int $except_user_id = null): array
    {
        $sql = 'SELECT p.user_id, SUBSTRING_INDEX(GROUP_CONCAT(p.hash ORDER BY p.id), ",", 1) AS hash,
                users.name AS user_name, d.organization
                FROM dashboard_bills b
                JOIN dashboard_portfolios p ON p.id = b.portfolio_id
                JOIN users ON users.id = p.user_id
                LEFT JOIN dashboard_user_data d ON d.user_id = p.user_id
                WHERE b.bill_id = ? AND b.excluded = "n" AND p.public = "y"' . ($except_user_id !== null ? ' AND p.user_id != ?' : '') . '
                GROUP BY p.user_id, users.name, d.organization
                ORDER BY MIN(p.id)';
        $params = $except_user_id !== null ? [$bill_id, $except_user_id] : [$bill_id];
        $result = mysqli_execute_query($GLOBALS['db'], $sql, $params);
        $trackers = [];
        while ($result && ($row = mysqli_fetch_assoc($result))) {
            $row = array_map('stripslashes_nullsafe', $row);
            $trackers[] = ['name' => self::public_name($row['user_name'], $row['organization']), 'hash' => $row['hash']];
        }
        return $trackers;
    }

    /**
     * How a portfolio's owner is named publicly: their organization, or their first name and the
     * initial of their last.
     */
    public static function public_name($user_name, $organization = null): string
    {
        if (!empty($organization)) {
            return (string) $organization;
        }
        $names = preg_split('/\s+/', trim((string) $user_name));
        if (count($names) > 1) {
            return $names[0] . ' ' . mb_substr($names[1], 0, 1) . '.';
        }
        return $names[0] ?? '';
    }

    /**
     * What the "Your Bill Portfolio" tab lists: each of a user's portfolios, with its bills of
     * the current session, most recently acted on first.
     *
     * @param int $limit The most bills to list from each portfolio.
     * @return array[] Each portfolio's hash, name, type, public, bills (number, catch_line, date),
     *     and how many bills it has (count).
     */
    public static function sidebar(int $user_id, int $limit = 15): array
    {
        $portfolios = [];
        foreach (self::for_user($user_id) as $portfolio) {
            $portfolio['bills'] = [];
            $portfolio['count'] = 0;
            $portfolios[$portfolio['id']] = $portfolio;
        }
        if (count($portfolios) == 0) {
            return [];
        }
        $sql = 'SELECT b.portfolio_id, bills.number, bills.catch_line,
                (SELECT MAX(date) FROM bills_status WHERE bill_id = bills.id) AS date
                FROM dashboard_bills b
                JOIN bills ON bills.id = b.bill_id
                WHERE b.user_id = ? AND bills.session_id = ? AND b.excluded = "n"
                ORDER BY date DESC, bills.number ASC';
        $result = mysqli_execute_query($GLOBALS['db'], $sql, [$user_id, SESSION_ID]);
        while ($result && ($row = mysqli_fetch_assoc($result))) {
            $id = (int) $row['portfolio_id'];
            if (!isset($portfolios[$id])) {
                continue;
            }
            $portfolios[$id]['count']++;
            if (count($portfolios[$id]['bills']) < $limit) {
                $portfolios[$id]['bills'][] = $row;
            }
        }
        return array_values($portfolios);
    }

    /**
     * The "Your Bill Portfolio" tab's contents, as HTML, or '' if the user has no portfolios.
     */
    public static function sidebar_html(int $user_id): string
    {
        $portfolios = self::sidebar($user_id);
        if (count($portfolios) == 0) {
            return '';
        }
        $html = '';
        foreach ($portfolios as $portfolio) {
            $html .= '<h6><a href="/photosynthesis/#' . self::text($portfolio['hash']) . '">'
                . self::text(stripslashes_nullsafe($portfolio['name'])) . '</a>'
                . ($portfolio['public'] == 'y' ? '' : ' <span class="private">(private)</span>') . '</h6>';
            if ($portfolio['count'] == 0) {
                $html .= '<p class="empty">No bills yet.</p>';
                continue;
            }
            foreach ($portfolio['bills'] as $bill) {
                $html .= '<div class="bill"><a href="/bill/' . SESSION_YEAR . '/' . self::text($bill['number']) . '/">'
                    . self::text(strtoupper($bill['number'])) . '</a>: ' . $bill['catch_line']
                    . ($bill['date'] ? ' <span class="last-updated">Updated&nbsp;' . date('n/j/Y', strtotime($bill['date'])) . '</span>' : '')
                    . '</div>';
            }
            if ($portfolio['count'] > count($portfolio['bills'])) {
                $more = $portfolio['count'] - count($portfolio['bills']);
                $html .= '<p class="more"><a href="/photosynthesis/#' . self::text($portfolio['hash']) . '">and '
                    . $more . ' more</a></p>';
            }
        }
        return $html . '<p><a href="/photosynthesis/">View your portfolios »</a></p>';
    }

    /**
     * A user's portfolio, by its hash, or null if it isn't theirs (or doesn't exist). A smart
     * portfolio has its criteria, too.
     */
    public static function find(string $hash, int $user_id): ?array
    {
        if (!self::is_hash($hash)) {
            return null;
        }
        $sql = 'SELECT id, hash, name, notes, notify, public, watch_list_id
                FROM dashboard_portfolios
                WHERE hash = ? AND user_id = ?';
        $result = mysqli_execute_query($GLOBALS['db'], $sql, [$hash, $user_id]);
        $portfolio = $result ? mysqli_fetch_assoc($result) : null;
        if (!$portfolio) {
            return null;
        }
        $portfolio = self::typed($portfolio);

        if ($portfolio['type'] == 'smart') {
            $sql = 'SELECT ' . implode(', ', self::CRITERIA) . '
                    FROM dashboard_watch_lists
                    WHERE id = ? AND user_id = ?';
            $result = mysqli_execute_query($GLOBALS['db'], $sql, [$portfolio['watch_list_id'], $user_id]);
            $criteria = $result ? mysqli_fetch_assoc($result) : null;
            $portfolio['criteria'] = $criteria ?: array_fill_keys(self::CRITERIA, null);
        }
        return $portfolio;
    }

    /**
     * A public portfolio, by its hash, or null if there's none (or it's private): its fields, its
     * owner as they're shown publicly (owner: their organization or, if none, their first name and
     * initial; person: the latter; organization; and url, their link), and, for a smart portfolio,
     * its criteria.
     */
    public static function find_public(string $hash): ?array
    {
        if (!self::is_hash($hash)) {
            return null;
        }
        $sql = 'SELECT p.id, p.user_id, p.hash, p.name, p.notes, p.public, p.watch_list_id,
                users.name AS user_name, users.url, d.organization,
                w.' . implode(', w.', self::CRITERIA) . '
                FROM dashboard_portfolios p
                JOIN users ON users.id = p.user_id
                LEFT JOIN dashboard_user_data d ON d.user_id = p.user_id
                LEFT JOIN dashboard_watch_lists w ON w.id = p.watch_list_id AND w.user_id = p.user_id
                WHERE p.hash = ? AND p.public = "y"';
        $result = mysqli_execute_query($GLOBALS['db'], $sql, [$hash]);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        if (!$row) {
            return null;
        }
        $row = array_map('stripslashes_nullsafe', $row);
        $criteria = array_intersect_key($row, array_flip(self::CRITERIA));
        $portfolio = self::typed(array_diff_key($row, $criteria, array_flip(['user_name', 'organization', 'url'])));
        $portfolio['user_id'] = (int) $portfolio['user_id'];
        $portfolio['owner'] = self::public_name($row['user_name'], $row['organization']);
        $portfolio['person'] = self::public_name($row['user_name']);
        $portfolio['organization'] = empty($row['organization']) ? null : $row['organization'];
        // The owner's link, only if it's to a web page.
        $portfolio['url'] = preg_match('#^https?://#i', (string) $row['url']) ? $row['url'] : null;
        if ($portfolio['type'] == 'smart') {
            $portfolio['criteria'] = $criteria;
        }
        return $portfolio;
    }

    /**
     * A public portfolio as the API gives it (rs-api's /1.1/photosynthesis/{hash}.json), or null
     * if there's none (or it's private). An empty portfolio has no bills, rather than being null.
     *
     * Notes are plain text: the API's users show them as they like, and rs-api has no HTML
     * Purifier to make their HTML safe.
     *
     * @return array|null hash, name, notes, type ("normal" or "smart"), criteria (a smart
     *     portfolio's: tag, patron, committee, and description, in words; null for a normal one),
     *     owner, organization, url (the owner's), page (the portfolio's), and bills (the current
     *     session's, but those hidden from it: number, catch_line, year, notes, status, date, url).
     */
    public static function record_for_api(string $hash): ?array
    {
        $portfolio = self::find_public($hash);
        if ($portfolio === null) {
            return null;
        }

        return [
            'hash' => $portfolio['hash'],
            'name' => (string) $portfolio['name'],
            'notes' => self::plain_text($portfolio['notes']),
            'type' => $portfolio['type'],
            'criteria' => $portfolio['type'] == 'smart' ? self::criteria_for_api($portfolio['criteria']) : null,
            'owner' => $portfolio['owner'],
            'organization' => $portfolio['organization'],
            'url' => $portfolio['url'],
            'page' => 'https://www.richmondsunlight.com/photosynthesis/' . $portfolio['hash'] . '/',
            'bills' => self::bills_for_api($portfolio['id']),
        ];
    }

    /**
     * A smart portfolio's criteria, as the API gives them: the tag; the patron's shortname and
     * name; the committee's shortname, chamber, and name; and all of them in words. Those not set
     * (or a patron or committee that's gone) are null.
     */
    private static function criteria_for_api(array $criteria): array
    {
        $api = [
            'tag' => empty($criteria['tag']) ? null : $criteria['tag'],
            'patron' => null,
            'committee' => null,
            'description' => self::plain_text(self::describe($criteria)),
        ];
        $lookups = [
            'patron' => ['patron_id', 'SELECT shortname, name FROM people WHERE id = ?'],
            'committee' => ['committee_id', 'SELECT shortname, chamber, name FROM committees WHERE id = ?'],
        ];
        foreach ($lookups as $key => [$field, $sql]) {
            if (empty($criteria[$field])) {
                continue;
            }
            $result = mysqli_execute_query($GLOBALS['db'], $sql, [(int) $criteria[$field]]);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            $api[$key] = $row ?: null;
        }
        return $api;
    }

    /**
     * A portfolio's bills of the current session, but those hidden from it, as the API gives them.
     */
    private static function bills_for_api(int $portfolio_id): array
    {
        $sql = 'SELECT bills.number, bills.catch_line, sessions.year, b.notes,
                (SELECT status FROM bills_status WHERE bill_id = bills.id
                    ORDER BY date DESC, id DESC LIMIT 1) AS status,
                (SELECT date FROM bills_status WHERE bill_id = bills.id
                    ORDER BY date DESC, id DESC LIMIT 1) AS date
                FROM dashboard_bills b
                JOIN bills ON bills.id = b.bill_id
                JOIN sessions ON sessions.id = bills.session_id
                WHERE b.portfolio_id = ? AND b.excluded = "n" AND bills.session_id = ?
                ORDER BY bills.chamber DESC,
                SUBSTRING(bills.number FROM 1 FOR 2) ASC,
                CAST(LPAD(SUBSTRING(bills.number FROM 3), 4, "0") AS unsigned) ASC';
        $result = mysqli_execute_query($GLOBALS['db'], $sql, [$portfolio_id, SESSION_ID]);
        $bills = [];
        while ($result && ($bill = mysqli_fetch_assoc($result))) {
            $bill = array_map('stripslashes_nullsafe', $bill);
            $number = strtolower($bill['number']);
            $bills[] = [
                'number' => $number,
                'catch_line' => $bill['catch_line'],
                'year' => (string) $bill['year'],
                'notes' => self::plain_text($bill['notes']),
                'status' => $bill['status'],
                'date' => $bill['date'],
                'url' => 'https://www.richmondsunlight.com/bill/' . $bill['year'] . '/' . $number . '/',
            ];
        }
        return $bills;
    }

    /**
     * Notes (or describe()'s HTML) as plain text, or null if there are none: paragraphs and line
     * breaks kept as line breaks, tags removed, and entities decoded.
     */
    public static function plain_text($html): ?string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return null;
        }
        $text = preg_replace(['#<br\s*/?>#i', '#</p>\s*#i'], ["\n", "\n\n"], $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace("/\n{3,}/", "\n\n", $text));
        return $text === '' ? null : $text;
    }

    /**
     * Mark a portfolio row as normal or smart.
     */
    private static function typed(array $portfolio): array
    {
        $portfolio['id'] = (int) $portfolio['id'];
        $portfolio['watch_list_id'] = $portfolio['watch_list_id'] === null ? null : (int) $portfolio['watch_list_id'];
        $portfolio['type'] = $portfolio['watch_list_id'] === null ? 'normal' : 'smart';
        return $portfolio;
    }

    /**
     * A portfolio's fields from a form: trimmed, with each limited to the values it can have.
     * Fields that weren't given, or have values they can't have, are left out.
     */
    public static function fields(array $form): array
    {
        $fields = [];
        foreach (self::FIELDS as $field => $values) {
            if (!isset($form[$field]) || !is_string($form[$field])) {
                continue;
            }
            $value = trim($form[$field]);
            if ($values !== null && !in_array($value, $values, true)) {
                continue;
            }
            if ($field == 'name') {
                $value = mb_substr($value, 0, 120);
            }
            $fields[$field] = $value;
        }
        return $fields;
    }

    /**
     * A smart portfolio's criteria from a form. Patrons and committees are named by their IDs.
     * Criteria that weren't given are null.
     */
    public static function criteria(array $form): array
    {
        $criteria = [];
        foreach (self::CRITERIA as $field) {
            $value = isset($form[$field]) && is_string($form[$field]) ? trim($form[$field]) : '';
            if (in_array($field, ['patron_id', 'committee_id'])) {
                $criteria[$field] = ctype_digit($value) && $value !== '0' ? (int) $value : null;
            } else {
                // Tags are lowercase, and at most 30 characters.
                $value = mb_substr(mb_strtolower(preg_replace('/\s+/', ' ', $value)), 0, 30);
                $criteria[$field] = $value === '' ? null : $value;
            }
        }
        return $criteria;
    }

    /**
     * Create a portfolio for this user, with a hash that no other portfolio has. If criteria are
     * given, it's a smart portfolio, with a watch list of them.
     *
     * @param array $fields name (required), and optionally notes, public, and notify.
     * @param array|null $criteria As criteria() gives them; at least one must be set.
     * @return array|null The new portfolio (as find() gives it), or null if it couldn't be made.
     */
    public static function create(int $user_id, array $fields, ?array $criteria = null): ?array
    {
        $fields = self::fields($fields);
        if (($fields['name'] ?? '') === '') {
            return null;
        }
        if ($criteria !== null && count(array_filter($criteria, fn($value) => $value !== null)) == 0) {
            return null;
        }

        $watch_list_id = null;
        if ($criteria !== null) {
            $criteria = array_intersect_key($criteria, array_flip(self::CRITERIA));
            $sql = 'INSERT INTO dashboard_watch_lists
                    SET ' . implode(' = ?, ', array_keys($criteria)) . ' = ?, user_id = ?, date_created = NOW()';
            if (!mysqli_execute_query($GLOBALS['db'], $sql, [...array_values($criteria), $user_id])) {
                return null;
            }
            $watch_list_id = mysqli_insert_id($GLOBALS['db']);
        }

        $sql = 'INSERT INTO dashboard_portfolios
                SET hash = ?, user_id = ?, watch_list_id = ?, name = ?, notes = ?, public = ?,
                notify = ?, date_created = NOW()';
        $params = [
            $user_id,
            $watch_list_id,
            $fields['name'],
            ($fields['notes'] ?? '') === '' ? null : $fields['notes'],
            $fields['public'] ?? 'y',
            $fields['notify'] ?? 'none',
        ];
        for ($attempt = 0; $attempt < self::HASH_ATTEMPTS; $attempt++) {
            $hash = self::new_hash();
            if (self::hash_taken($hash)) {
                continue;
            }
            try {
                $created = mysqli_execute_query($GLOBALS['db'], $sql, [$hash, ...$params]);
                $errno = mysqli_errno($GLOBALS['db']);
            } catch (mysqli_sql_exception $e) {
                $created = false;
                $errno = $e->getCode();
            }
            if ($created) {
                $portfolio_id = (int) mysqli_insert_id($GLOBALS['db']);
                self::ensure_user_data($user_id);
                if ($watch_list_id !== null) {
                    self::populate($portfolio_id);
                }
                return self::find($hash, $user_id);
            }
            if ($errno != self::DUPLICATE_KEY) {
                break;
            }
        }

        if ($watch_list_id !== null) {
            mysqli_execute_query($GLOBALS['db'], 'DELETE FROM dashboard_watch_lists WHERE id = ? AND user_id = ?', [$watch_list_id, $user_id]);
        }
        return null;
    }

    /**
     * Whether a portfolio already has this hash. (Another could take it before it's used, so
     * create() still checks for a duplicate key.)
     */
    private static function hash_taken(string $hash): bool
    {
        $result = mysqli_execute_query($GLOBALS['db'], 'SELECT 1 FROM dashboard_portfolios WHERE hash = ?', [$hash]);
        return $result === false || mysqli_num_rows($result) > 0;
    }

    /**
     * Change a user's portfolio: its fields, and, for a smart portfolio, its criteria (if given).
     *
     * @return array|null The changed portfolio, or null if it isn't theirs, or couldn't be changed.
     */
    public static function update(string $hash, int $user_id, array $fields, ?array $criteria = null): ?array
    {
        $portfolio = self::find($hash, $user_id);
        if ($portfolio === null) {
            return null;
        }
        $fields = self::fields($fields);
        if (array_key_exists('name', $fields) && $fields['name'] === '') {
            return null;
        }
        if (array_key_exists('notes', $fields) && $fields['notes'] === '') {
            $fields['notes'] = null;
        }
        if ($criteria !== null && $portfolio['type'] == 'smart') {
            $criteria = array_intersect_key($criteria, array_flip(self::CRITERIA));
            if (count(array_filter($criteria, fn($value) => $value !== null)) == 0) {
                return null;
            }
        }

        if (count($fields) > 0) {
            $sql = 'UPDATE dashboard_portfolios
                    SET ' . implode(' = ?, ', array_keys($fields)) . ' = ?
                    WHERE id = ? AND user_id = ?';
            if (!mysqli_execute_query($GLOBALS['db'], $sql, [...array_values($fields), $portfolio['id'], $user_id])) {
                return null;
            }
        }

        // Changing a smart portfolio's criteria fills it again, and takes out the bills that no
        // longer match (except those with notes).
        if ($criteria !== null && $portfolio['type'] == 'smart') {
            $changed = false;
            foreach ($criteria as $field => $value) {
                if ((string) ($portfolio['criteria'][$field] ?? '') !== (string) $value) {
                    $changed = true;
                }
            }
            if ($changed) {
                $sql = 'UPDATE dashboard_watch_lists
                        SET ' . implode(' = ?, ', array_keys($criteria)) . ' = ?
                        WHERE id = ? AND user_id = ?';
                $params = [...array_values($criteria), $portfolio['watch_list_id'], $user_id];
                if (!mysqli_execute_query($GLOBALS['db'], $sql, $params)) {
                    return null;
                }
                self::populate($portfolio['id'], true);
            }
        }

        return self::find($hash, $user_id);
    }

    /**
     * The SQL condition (on `bills`) for the bills of the current session that match a smart
     * portfolio's criteria, and its parameters, or null if it has none.
     *
     * A bill matches a committee if it was referred to it this session, not only if it's there
     * now: if it's there now (bills.last_committee_id), if it was on a docket of the committee or
     * of one of its subcommittees, or if an action referred it there (see referrals()).
     *
     * @return array|null [string $sql, array $params]
     */
    public static function matching(array $criteria): ?array
    {
        $sql = ['bills.session_id = ?'];
        $params = [SESSION_ID];
        $any = false;
        if (!empty($criteria['tag'])) {
            $sql[] = 'EXISTS (SELECT 1 FROM tags WHERE tags.bill_id = bills.id AND tags.tag = ?)';
            $params[] = (string) $criteria['tag'];
            $any = true;
        }
        if (!empty($criteria['patron_id'])) {
            $sql[] = 'bills.chief_patron_id = ?';
            $params[] = (int) $criteria['patron_id'];
            $any = true;
        }
        if (!empty($criteria['committee_id'])) {
            $committee_id = (int) $criteria['committee_id'];
            $referred = self::referrals()[$committee_id] ?? [];
            $sql[] = '(bills.last_committee_id = ?
                OR EXISTS (SELECT 1 FROM dockets JOIN committees ON committees.id = dockets.committee_id
                    WHERE dockets.bill_id = bills.id AND (committees.id = ? OR committees.parent_id = ?))'
                . (count($referred) > 0 ? ' OR bills.id IN (' . implode(', ', array_map('intval', $referred)) . ')' : '')
                . ')';
            array_push($params, $committee_id, $committee_id, $committee_id);
            $any = true;
        }
        return $any ? [implode(' AND ', $sql), $params] : null;
    }

    /**
     * The current session's bills that actions referred to each committee, by the committee's
     * ID, from LIS's text ("Referred to Committee on Finance"; see Day::referral_committee()).
     *
     * @return array<int, int[]>
     */
    public static function referrals(): array
    {
        if (self::$referrals !== null) {
            return self::$referrals;
        }
        self::$referrals = [];
        $result = mysqli_execute_query(
            $GLOBALS['db'],
            'SELECT bills_status.bill_id, bills_status.status, bills_status.date,
            COALESCE(bills_status.chamber, bills.chamber) AS chamber
            FROM bills_status
            JOIN bills ON bills.id = bills_status.bill_id
            WHERE bills.session_id = ? AND bills_status.status REGEXP "^(re)?referred to"',
            [SESSION_ID]
        );
        while ($result && ($row = mysqli_fetch_assoc($result))) {
            $date = $row['date'] ? substr($row['date'], 0, 10) : date('Y-m-d');
            $committee_id = Day::referral_committee((string) $row['chamber'], $row['status'], $date);
            if ($committee_id !== null) {
                self::$referrals[$committee_id][] = (int) $row['bill_id'];
            }
        }
        foreach (self::$referrals as $committee_id => $bill_ids) {
            self::$referrals[$committee_id] = array_values(array_unique($bill_ids));
        }
        return self::$referrals;
    }

    /**
     * Forget the referrals that referrals() found, so that it looks again. (For a long-running
     * process, and tests.)
     */
    public static function forget_referrals(): void
    {
        self::$referrals = null;
    }

    /**
     * Fill a smart portfolio with the current session's bills that match its criteria, in one
     * query. It only adds bills: a bill that stops matching stays, with its notes, and a bill
     * hidden from the portfolio (excluded) keeps its row, so it isn't added again.
     *
     * @param bool $prune Also take out the current session's bills that no longer match, except
     *     those with notes. For when the criteria have changed.
     * @return array|null How many bills were added and removed, or null if it isn't a smart
     *     portfolio.
     */
    public static function populate(int $portfolio_id, bool $prune = false): ?array
    {
        $sql = 'SELECT p.id, p.user_id, ' . implode(', ', array_map(fn($field) => 'w.' . $field, self::CRITERIA)) . '
                FROM dashboard_portfolios p
                JOIN dashboard_watch_lists w ON w.id = p.watch_list_id
                WHERE p.id = ?';
        $result = mysqli_execute_query($GLOBALS['db'], $sql, [$portfolio_id]);
        $portfolio = $result ? mysqli_fetch_assoc($result) : null;
        if (!$portfolio) {
            return null;
        }
        $matching = self::matching($portfolio);
        if ($matching === null) {
            return ['added' => 0, 'removed' => 0];
        }
        [$condition, $params] = $matching;
        $user_id = (int) $portfolio['user_id'];

        $removed = 0;
        if ($prune) {
            $sql = 'DELETE dashboard_bills FROM dashboard_bills
                    JOIN bills ON bills.id = dashboard_bills.bill_id
                    WHERE dashboard_bills.portfolio_id = ? AND bills.session_id = ?
                    AND (dashboard_bills.notes IS NULL OR dashboard_bills.notes = "")
                    AND NOT (' . $condition . ')';
            mysqli_execute_query($GLOBALS['db'], $sql, [$portfolio_id, SESSION_ID, ...$params]);
            $removed = max(0, mysqli_affected_rows($GLOBALS['db']));
        }

        $sql = 'INSERT INTO dashboard_bills (user_id, bill_id, portfolio_id, date_created)
                SELECT ?, bills.id, ?, NOW()
                FROM bills
                WHERE ' . $condition . '
                ON DUPLICATE KEY UPDATE dashboard_bills.bill_id = dashboard_bills.bill_id';
        mysqli_execute_query($GLOBALS['db'], $sql, [$user_id, $portfolio_id, ...$params]);
        $added = max(0, mysqli_affected_rows($GLOBALS['db']));

        return ['added' => $added, 'removed' => $removed];
    }

    /**
     * Populate every smart portfolio (as rs-machine's photosynthesis task does, hourly).
     *
     * @return array How many portfolios were populated and failed, and bills added.
     */
    public static function populate_all(): array
    {
        self::forget_referrals();
        $counts = ['portfolios' => 0, 'failed' => 0, 'added' => 0];
        $result = mysqli_query($GLOBALS['db'], 'SELECT id FROM dashboard_portfolios WHERE watch_list_id IS NOT NULL');
        while ($result && ($row = mysqli_fetch_assoc($result))) {
            $populated = self::populate((int) $row['id']);
            if ($populated === null) {
                $counts['failed']++;
                continue;
            }
            $counts['portfolios']++;
            $counts['added'] += $populated['added'];
        }
        return $counts;
    }

    /**
     * Hide a bill from a user's smart portfolio, or show it again. A hidden bill stays out, even
     * though it still matches.
     *
     * @return int|null The bill's ID, or null if the record isn't in that smart portfolio of theirs.
     */
    public static function exclude(string $hash, int $user_id, int $record_id, bool $excluded = true): ?int
    {
        $portfolio = self::find($hash, $user_id);
        if ($portfolio === null || $portfolio['type'] != 'smart') {
            return null;
        }
        $result = mysqli_execute_query(
            $GLOBALS['db'],
            'SELECT bill_id FROM dashboard_bills WHERE id = ? AND portfolio_id = ? AND user_id = ?',
            [$record_id, $portfolio['id'], $user_id]
        );
        $record = $result ? mysqli_fetch_assoc($result) : null;
        if (!$record) {
            return null;
        }
        mysqli_execute_query(
            $GLOBALS['db'],
            'UPDATE dashboard_bills SET excluded = ? WHERE id = ? AND portfolio_id = ? AND user_id = ?',
            [$excluded ? 'y' : 'n', $record_id, $portfolio['id'], $user_id]
        );
        return (int) $record['bill_id'];
    }

    /**
     * Whether any of the current session's bills has this tag (a smart portfolio's tag may not be
     * used yet: bills are tagged as the session goes).
     */
    public static function tag_used(string $tag): bool
    {
        $result = mysqli_execute_query(
            $GLOBALS['db'],
            'SELECT 1 FROM tags JOIN bills ON bills.id = tags.bill_id WHERE tags.tag = ? AND bills.session_id = ? LIMIT 1',
            [$tag, SESSION_ID]
        );
        return $result !== false && mysqli_num_rows($result) > 0;
    }

    /**
     * The tags that a smart portfolio's form suggests: the current session's, most used first,
     * then other sessions'.
     *
     * @return string[]
     */
    public static function tag_suggestions(int $limit = 1500): array
    {
        $result = mysqli_execute_query(
            $GLOBALS['db'],
            'SELECT tags.tag, MAX(bills.session_id = ?) AS current, COUNT(*) AS n
            FROM tags JOIN bills ON bills.id = tags.bill_id
            GROUP BY tags.tag
            ORDER BY current DESC, n DESC, tags.tag ASC
            LIMIT ' . (int) $limit,
            [SESSION_ID]
        );
        $tags = [];
        while ($result && ($row = mysqli_fetch_assoc($result))) {
            $tags[] = mb_strtolower($row['tag']);
        }
        return array_values(array_unique($tags));
    }

    /**
     * The legislators serving now, for a smart portfolio's form: by chamber, each with their
     * person's ID (id) and a label, "McClure, Adele (D-2)".
     *
     * @return array<string, array[]> "house" and "senate"
     */
    public static function patrons(): array
    {
        $result = mysqli_query(
            $GLOBALS['db'],
            'SELECT people.id, people.name, terms.party, terms.chamber, districts.number
            FROM terms
            JOIN people ON people.id = terms.person_id
            LEFT JOIN districts ON districts.id = terms.district_id
            WHERE terms.date_started <= CURDATE() AND (terms.date_ended IS NULL OR terms.date_ended >= CURDATE())
            ORDER BY terms.chamber ASC, people.name ASC'
        );
        $patrons = ['house' => [], 'senate' => []];
        while ($result && ($row = mysqli_fetch_assoc($result))) {
            $patrons[$row['chamber']][] = [
                'id' => (int) $row['id'],
                'label' => $row['name'] . ' (' . $row['party'] . ($row['number'] ? '-' . $row['number'] : '') . ')',
            ];
        }
        return $patrons;
    }

    /**
     * The full committees that exist now, for a smart portfolio's form, by chamber, each with its
     * ID and name.
     *
     * @return array<string, array[]> "house" and "senate"
     */
    public static function committees(): array
    {
        $result = mysqli_query(
            $GLOBALS['db'],
            'SELECT id, name, chamber FROM committees
            WHERE parent_id IS NULL AND date_ended IS NULL
            ORDER BY chamber ASC, name ASC'
        );
        $committees = ['house' => [], 'senate' => []];
        while ($result && ($row = mysqli_fetch_assoc($result))) {
            $committees[$row['chamber']][] = ['id' => (int) $row['id'], 'label' => $row['name']];
        }
        return $committees;
    }

    /**
     * Delete a user's portfolio, with its bills and its watch list.
     *
     * @return array|null The bill IDs that were in it (whose pages show its notes), or null if it
     *     isn't theirs.
     */
    public static function delete(string $hash, int $user_id): ?array
    {
        $portfolio = self::find($hash, $user_id);
        if ($portfolio === null) {
            return null;
        }
        $bill_ids = self::bill_ids($portfolio['id']);

        mysqli_execute_query(
            $GLOBALS['db'],
            'DELETE FROM dashboard_bills WHERE portfolio_id = ? AND user_id = ?',
            [$portfolio['id'], $user_id]
        );
        mysqli_execute_query(
            $GLOBALS['db'],
            'DELETE FROM dashboard_portfolios WHERE id = ? AND user_id = ?',
            [$portfolio['id'], $user_id]
        );
        if ($portfolio['watch_list_id'] !== null) {
            mysqli_execute_query(
                $GLOBALS['db'],
                'DELETE FROM dashboard_watch_lists WHERE id = ? AND user_id = ?',
                [$portfolio['watch_list_id'], $user_id]
            );
        }
        return $bill_ids;
    }

    /**
     * Delete all of a user's portfolios, their bills, their watch lists, and their Photosynthesis
     * settings, as when their account is deleted.
     *
     * @return int[] The bill IDs that were in them.
     */
    public static function delete_user(int $user_id): array
    {
        $result = mysqli_execute_query(
            $GLOBALS['db'],
            'SELECT DISTINCT bill_id FROM dashboard_bills WHERE user_id = ?',
            [$user_id]
        );
        $bill_ids = [];
        while ($result && ($row = mysqli_fetch_assoc($result))) {
            $bill_ids[] = (int) $row['bill_id'];
        }
        foreach (['dashboard_bills', 'dashboard_portfolios', 'dashboard_watch_lists', 'dashboard_user_data'] as $table) {
            mysqli_execute_query($GLOBALS['db'], 'DELETE FROM ' . $table . ' WHERE user_id = ?', [$user_id]);
        }
        return $bill_ids;
    }

    /**
     * The IDs of the bills in a portfolio.
     *
     * @return int[]
     */
    private static function bill_ids(int $portfolio_id): array
    {
        $result = mysqli_execute_query(
            $GLOBALS['db'],
            'SELECT bill_id FROM dashboard_bills WHERE portfolio_id = ?',
            [$portfolio_id]
        );
        $bill_ids = [];
        while ($result && ($row = mysqli_fetch_assoc($result))) {
            $bill_ids[] = (int) $row['bill_id'];
        }
        return $bill_ids;
    }

    /**
     * Add a bill of the current session to a user's (normal) portfolio.
     *
     * @param string $number A bill number, like "HB 1" or "hb1".
     * @return array|null The new record (record_id, bill_id), or null if the portfolio isn't
     *     theirs, is a smart portfolio, there's no such bill, or the bill is already in it.
     */
    public static function add_bill(string $hash, int $user_id, string $number): ?array
    {
        $portfolio = self::find($hash, $user_id);
        if ($portfolio === null || $portfolio['type'] != 'normal') {
            return null;
        }
        $number = strtolower(str_replace(' ', '', trim($number)));
        if (!preg_match('/^[a-z]{2,3}[0-9]{1,5}$/', $number)) {
            return null;
        }

        $result = mysqli_execute_query(
            $GLOBALS['db'],
            'SELECT id FROM bills WHERE number = ? AND session_id = ?',
            [$number, SESSION_ID]
        );
        $bill = $result ? mysqli_fetch_assoc($result) : null;
        if (!$bill) {
            return null;
        }

        // Once in each portfolio.
        $result = mysqli_execute_query(
            $GLOBALS['db'],
            'SELECT id FROM dashboard_bills WHERE bill_id = ? AND portfolio_id = ?',
            [$bill['id'], $portfolio['id']]
        );
        if ($result === false || mysqli_num_rows($result) > 0) {
            return null;
        }

        try {
            $added = mysqli_execute_query(
                $GLOBALS['db'],
                'INSERT INTO dashboard_bills SET user_id = ?, bill_id = ?, portfolio_id = ?, date_created = NOW()',
                [$user_id, $bill['id'], $portfolio['id']]
            );
        } catch (mysqli_sql_exception $e) {
            $added = false;
        }
        if (!$added) {
            return null;
        }
        $record = ['record_id' => mysqli_insert_id($GLOBALS['db']), 'bill_id' => (int) $bill['id']];
        self::ensure_user_data($user_id);
        return $record;
    }

    /**
     * Remove a bill from a user's portfolio, by its record's ID (dashboard_bills.id).
     *
     * @return int|null The bill's ID, or null if the record isn't in that portfolio of theirs.
     */
    public static function remove_bill(string $hash, int $user_id, int $record_id): ?int
    {
        $portfolio = self::find($hash, $user_id);
        if ($portfolio === null) {
            return null;
        }
        $result = mysqli_execute_query(
            $GLOBALS['db'],
            'SELECT bill_id FROM dashboard_bills WHERE id = ? AND portfolio_id = ? AND user_id = ?',
            [$record_id, $portfolio['id'], $user_id]
        );
        $record = $result ? mysqli_fetch_assoc($result) : null;
        if (!$record) {
            return null;
        }
        mysqli_execute_query(
            $GLOBALS['db'],
            'DELETE FROM dashboard_bills WHERE id = ? AND portfolio_id = ? AND user_id = ?',
            [$record_id, $portfolio['id'], $user_id]
        );
        return (int) $record['bill_id'];
    }

    /**
     * Set a user's notes on a bill in one of their portfolios, by its record's ID.
     *
     * @return int|null The bill's ID, or null if the record isn't theirs.
     */
    public static function set_notes(int $user_id, int $record_id, string $notes): ?int
    {
        $result = mysqli_execute_query(
            $GLOBALS['db'],
            'SELECT bill_id FROM dashboard_bills WHERE id = ? AND user_id = ?',
            [$record_id, $user_id]
        );
        $record = $result ? mysqli_fetch_assoc($result) : null;
        if (!$record) {
            return null;
        }
        $notes = trim($notes);
        mysqli_execute_query(
            $GLOBALS['db'],
            'UPDATE dashboard_bills SET notes = ? WHERE id = ? AND user_id = ?',
            [$notes === '' ? null : $notes, $record_id, $user_id]
        );
        return (int) $record['bill_id'];
    }

    /**
     * Forget the cached copies of these bills' comments (a portfolio's notes on a bill are among
     * them), and Cloudflare's copies of their pages.
     *
     * @param int[] $bill_ids
     */
    public static function expire_bills(array $bill_ids): void
    {
        $bill_ids = array_values(array_unique(array_map('intval', $bill_ids)));
        if (count($bill_ids) == 0) {
            return;
        }
        if (MEMCACHED_SERVER != '' && class_exists('Memcached')) {
            $mc = new Memcached();
            $mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);
            foreach ($bill_ids as $bill_id) {
                $mc->delete('comments-' . $bill_id);
            }
        }
        Cloudflare::purge_bills($bill_ids);
    }

    /**
     * Text (a portfolio's name, a person's name), safe to put in HTML.
     */
    public static function text($text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Notes (on a portfolio, or on a bill), as HTML that's safe to show: paragraphs, and only the
     * few tags that the forms allow, links only to http, https, and mailto addresses, and no
     * attributes but a link's href. Notes were stored with whatever HTML they were typed with.
     */
    public static function notes_html($notes): string
    {
        $notes = trim((string) $notes);
        if ($notes === '') {
            return '';
        }
        if (self::$purifier === null) {
            // HTML Purifier's own autoloader, not Composer's, which would include
            // functions.inc.php a second time.
            if (!class_exists('HTMLPurifier')) {
                require_once __DIR__ . '/vendor/ezyang/htmlpurifier/library/HTMLPurifier.auto.php';
            }
            $config = HTMLPurifier_Config::createDefault();
            $config->set('HTML.Allowed', 'p,br,a[href],em,strong,i,b,s,blockquote,ol,ul,li');
            $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
            $config->set('Cache.DefinitionImpl', null);
            self::$purifier = new HTMLPurifier($config);
        }
        return self::$purifier->purify(nl2p($notes));
    }
}
