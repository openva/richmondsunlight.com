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
        'notify' => ['none', 'hourly', 'daily'],
    ];

    /**
     * A smart portfolio's criteria, as dashboard_watch_lists stores them. Phase 3 of
     * docs/portfolios.md narrows these to tag, patron, and committee.
     */
    const CRITERIA = ['tag', 'keyword', 'status', 'current_chamber', 'patron_id', 'committee_id'];

    /**
     * The values that a watch list's status can have.
     */
    const STATUSES = ['introduced', 'passed house', 'passed senate', 'passed', 'failed', 'continued', 'approved', 'vetoed'];

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
        $sql = 'SELECT id, hash, name, notes, notify, public, watch_list_id
                FROM dashboard_portfolios
                WHERE user_id = ?' . ($normal_only ? ' AND watch_list_id IS NULL' : '') . '
                ORDER BY name ASC, id ASC';
        $result = mysqli_execute_query($GLOBALS['db'], $sql, [$user_id]);
        if ($result === false) {
            return [];
        }
        $portfolios = [];
        while ($portfolio = mysqli_fetch_assoc($result)) {
            $portfolios[] = self::typed($portfolio);
        }
        return $portfolios;
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
                $criteria[$field] = ctype_digit($value) ? (int) $value : null;
            } elseif ($field == 'status') {
                $criteria[$field] = in_array($value, self::STATUSES, true) ? $value : null;
            } elseif ($field == 'current_chamber') {
                $criteria[$field] = in_array($value, ['house', 'senate'], true) ? $value : null;
            } else {
                $value = mb_substr($value, 0, $field == 'tag' ? 30 : 120);
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

        if (count($fields) > 0) {
            $sql = 'UPDATE dashboard_portfolios
                    SET ' . implode(' = ?, ', array_keys($fields)) . ' = ?
                    WHERE id = ? AND user_id = ?';
            if (!mysqli_execute_query($GLOBALS['db'], $sql, [...array_values($fields), $portfolio['id'], $user_id])) {
                return null;
            }
        }

        if ($criteria !== null && $portfolio['type'] == 'smart') {
            $criteria = array_intersect_key($criteria, array_flip(self::CRITERIA));
            if (count(array_filter($criteria, fn($value) => $value !== null)) == 0) {
                return null;
            }
            $sql = 'UPDATE dashboard_watch_lists
                    SET ' . implode(' = ?, ', array_keys($criteria)) . ' = ?
                    WHERE id = ? AND user_id = ?';
            $params = [...array_values($criteria), $portfolio['watch_list_id'], $user_id];
            if (!mysqli_execute_query($GLOBALS['db'], $sql, $params)) {
                return null;
            }
        }

        return self::find($hash, $user_id);
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
        return ['record_id' => mysqli_insert_id($GLOBALS['db']), 'bill_id' => (int) $bill['id']];
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
