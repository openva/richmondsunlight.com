<?php

/**
 * Portfolio email: each morning, each user whose portfolios are set to send email gets one message
 * listing what happened to their bills since their last one. rs-machine's portfolio_email task
 * calls run(). See docs/portfolios.md (phase 5).
 *
 * What's been sent is tracked by dashboard_user_data.last_notified, not by a time window: each run
 * sends each user the actions that Richmond Sunlight recorded (bills_status.date_created, which is
 * set when an action is first stored, and never changed) since then, and moves it forward only if
 * the message was sent. So a missed run, or a second run in a day, sends each action once.
 *
 * actions() and recipients() query the database; since(), digest(), and render() are pure, and
 * tested without one; run() ties them together, and sends through a Mailer.
 */
class PortfolioDigest
{
    /**
     * How times are stored.
     */
    const DATETIME = 'Y-m-d H:i:s';

    /**
     * Where links go.
     */
    const BASE_URL = 'https://www.richmondsunlight.com';

    /**
     * How far back a message reaches, at most: a user whose email was off for a month, or whose
     * last message failed for a week, gets the past few days' news, not a month's.
     */
    const LOOKBACK = '-3 days';

    /**
     * How long before the run to stop: an action being stored as the run starts is in the next
     * run's message, not missed by both.
     */
    const SETTLE = '-1 minute';

    /**
     * The actions that were recorded after $since and by $until, by bill ID: each bill's number,
     * catch_line, and year, and its actions (status, translation, date, recorded), in order.
     */
    public static function actions(string $since, string $until): array
    {
        $sql = 'SELECT bills_status.bill_id, bills.number, bills.catch_line, sessions.year,
                bills_status.status, bills_status.translation, bills_status.date,
                bills_status.date_created AS recorded
                FROM bills_status
                JOIN bills ON bills.id = bills_status.bill_id
                JOIN sessions ON sessions.id = bills.session_id
                WHERE bills_status.date_created > ? AND bills_status.date_created <= ?
                ORDER BY bills_status.date ASC, bills_status.id ASC';
        $result = mysqli_execute_query($GLOBALS['db'], $sql, [$since, $until]);
        $actions = [];
        while ($result && ($row = mysqli_fetch_assoc($result))) {
            $row = array_map('stripslashes_nullsafe', $row);
            $id = (int) $row['bill_id'];
            $actions[$id] ??= [
                'number' => strtolower($row['number']),
                'catch_line' => $row['catch_line'],
                'year' => (int) $row['year'],
                'actions' => [],
            ];
            $actions[$id]['actions'][] = [
                'status' => $row['status'],
                'translation' => $row['translation'],
                'date' => $row['date'],
                'recorded' => $row['recorded'],
            ];
        }
        return $actions;
    }

    /**
     * The users who get email: those with email on (dashboard_user_data.email_active), a valid
     * address, and a portfolio set to send it (notify = "daily").
     *
     * @return array[] Each with user_id, name, email, unsub_hash, last_notified, and portfolios
     *     (those set to send email, by name: each with hash, name, and bill_ids, but those hidden
     *     from it).
     */
    public static function recipients(): array
    {
        $sql = 'SELECT users.id AS user_id, users.name, users.email, d.unsub_hash, d.last_notified,
                p.id AS portfolio_id, p.hash, p.name AS portfolio, b.bill_id
                FROM dashboard_user_data d
                JOIN users ON users.id = d.user_id
                JOIN dashboard_portfolios p ON p.user_id = d.user_id AND p.notify = "daily"
                LEFT JOIN dashboard_bills b ON b.portfolio_id = p.id AND b.excluded = "n"
                WHERE d.email_active = "y"
                ORDER BY users.id, p.name, p.id, b.bill_id';
        $result = mysqli_query($GLOBALS['db'], $sql);
        $recipients = [];
        while ($result && ($row = mysqli_fetch_assoc($result))) {
            if (filter_var($row['email'], FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }
            $user_id = (int) $row['user_id'];
            $recipients[$user_id] ??= [
                'user_id' => $user_id,
                'name' => stripslashes_nullsafe($row['name']),
                'email' => $row['email'],
                'unsub_hash' => $row['unsub_hash'],
                'last_notified' => $row['last_notified'],
                'portfolios' => [],
            ];
            $portfolio_id = (int) $row['portfolio_id'];
            $recipients[$user_id]['portfolios'][$portfolio_id] ??= [
                'hash' => $row['hash'],
                'name' => stripslashes_nullsafe($row['portfolio']),
                'bill_ids' => [],
            ];
            if ($row['bill_id'] !== null) {
                $recipients[$user_id]['portfolios'][$portfolio_id]['bill_ids'][] = (int) $row['bill_id'];
            }
        }
        foreach ($recipients as &$recipient) {
            $recipient['portfolios'] = array_values($recipient['portfolios']);
        }
        return array_values($recipients);
    }

    /**
     * When a user's message starts: their last message, but no further back than LOOKBACK. A user
     * who's never had one starts now (so their first message has only what happens next).
     */
    public static function since(?string $last_notified, DateTimeImmutable $now): string
    {
        $earliest = $now->modify(self::LOOKBACK);
        if ($last_notified === null || $last_notified === '') {
            return $now->format(self::DATETIME);
        }
        $last = new DateTimeImmutable($last_notified, $now->getTimezone());
        return max($last, $earliest)->format(self::DATETIME);
    }

    /**
     * A user's bills that had actions recorded after $since, each with those actions, by
     * portfolio; or null if none did. A bill in more than one of the user's portfolios is listed
     * once, in the first.
     *
     * @param array $actions As actions() gives them.
     * @return array|null portfolios (each with hash, name, and bills: number, catch_line, year,
     *     url, and actions), bills (how many), and actions (how many).
     */
    public static function digest(array $recipient, array $actions, string $since): ?array
    {
        $listed = [];
        $portfolios = [];
        $action_count = 0;
        foreach ($recipient['portfolios'] as $portfolio) {
            $bills = [];
            foreach ($portfolio['bill_ids'] as $bill_id) {
                if (isset($listed[$bill_id]) || !isset($actions[$bill_id])) {
                    continue;
                }
                $new = array_values(array_filter(
                    $actions[$bill_id]['actions'],
                    fn($action) => $action['recorded'] > $since
                ));
                if (count($new) == 0) {
                    continue;
                }
                $listed[$bill_id] = true;
                $bill = $actions[$bill_id];
                $bills[] = [
                    'number' => $bill['number'],
                    'catch_line' => $bill['catch_line'],
                    'year' => $bill['year'],
                    'url' => self::BASE_URL . '/bill/' . $bill['year'] . '/' . $bill['number'] . '/',
                    'actions' => $new,
                ];
                $action_count += count($new);
            }
            if (count($bills) > 0) {
                $portfolios[] = ['hash' => $portfolio['hash'], 'name' => $portfolio['name'], 'bills' => $bills];
            }
        }
        if (count($listed) == 0) {
            return null;
        }
        return ['portfolios' => $portfolios, 'bills' => count($listed), 'actions' => $action_count];
    }

    /**
     * A user's message: subject, text, html, and headers (List-Unsubscribe, for one-click
     * unsubscribing in mail programs).
     */
    public static function render(array $recipient, array $digest): array
    {
        $unsubscribe = self::BASE_URL . '/photosynthesis/unsubscribe/' . $recipient['unsub_hash'] . '/';
        $portfolios_url = self::BASE_URL . '/photosynthesis/';

        if ($digest['bills'] == 1) {
            $subject = 'News on ' . strtoupper($digest['portfolios'][0]['bills'][0]['number']);
        } else {
            $subject = 'News on ' . $digest['bills'] . ' of your bills';
        }
        $subject .= ', from Richmond Sunlight';

        $intro = 'Here’s what’s happened to the bills you’re following since we last wrote.';
        $why = 'You’re getting this because one of your portfolios is set to send email.';

        $text = $intro . "\n";
        $html = '<div style="font-family: Georgia, serif; font-size: 16px; line-height: 1.4; max-width: 40em;">'
            . '<p>' . self::html($intro) . '</p>';

        foreach ($digest['portfolios'] as $portfolio) {
            $text .= "\n" . mb_strtoupper($portfolio['name']) . "\n";
            $html .= '<h2 style="font-size: 18px; margin: 1.5em 0 .5em;">' . self::html($portfolio['name']) . '</h2>';
            foreach ($portfolio['bills'] as $bill) {
                [$bill_text, $bill_html] = self::render_bill($bill);
                $text .= $bill_text;
                $html .= $bill_html;
            }
        }

        $text .= "\nYour portfolios: " . $portfolios_url . "\n\n"
            . $why . ' To change that, edit the portfolio. To stop all of these emails, unsubscribe: ' . $unsubscribe . "\n";
        $html .= '<p style="margin-top: 2em;"><a href="' . self::html($portfolios_url) . '">Your portfolios</a></p>'
            . '<p style="font-size: 13px; color: #666;">' . self::html($why) . ' To change that, edit the portfolio. '
            . 'To stop all of these emails, <a href="' . self::html($unsubscribe) . '">unsubscribe</a>.</p></div>';

        return [
            'subject' => $subject,
            'text' => $text,
            'html' => $html,
            'headers' => [
                'List-Unsubscribe' => '<' . $unsubscribe . '>',
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ],
        ];
    }

    /**
     * One bill in a message, as text and as HTML: its number, catch line, link, and actions.
     */
    private static function render_bill(array $bill): array
    {
        $number = strtoupper($bill['number']);
        $text = "\n" . $number . ': ' . $bill['catch_line'] . "\n" . $bill['url'] . "\n";
        $html = '<p style="margin: 1em 0 .25em;"><a href="' . self::html($bill['url']) . '"><strong>'
            . self::html($number) . '</strong>: ' . self::html($bill['catch_line']) . '</a></p><ul style="margin-top: 0;">';
        foreach ($bill['actions'] as $action) {
            $date = self::date($action['date']);
            $prefix = $date === '' ? '' : $date . ': ';
            $text .= '  - ' . $prefix . $action['status'] . "\n";
            $html .= '<li>' . self::html($prefix . $action['status']) . '</li>';
        }
        return [$text, $html . '</ul>'];
    }

    /**
     * Send each user their message.
     *
     * @param array $options dry_run (send through $mailer, but change nothing in the database:
     *     pass a RecordingMailer, and read what it kept; a user who's never had a message gets the
     *     past day's), and only (the user IDs to send to, and
     *     no others; null for everybody).
     * @return array How many users get email (recipients), how many were sent a message (sent),
     *     had nothing to send (empty), or couldn't be sent theirs (failed), and how many actions
     *     were recorded in the span (actions).
     */
    public static function run(Mailer $mailer, DateTimeImmutable $now, array $options = []): array
    {
        $dry_run = !empty($options['dry_run']);
        $only = $options['only'] ?? null;

        $recipients = self::recipients();
        if ($only !== null) {
            $only = array_map('intval', $only);
            $recipients = array_values(array_filter($recipients, fn($r) => in_array($r['user_id'], $only, true)));
        }
        $counts = ['recipients' => count($recipients), 'sent' => 0, 'empty' => 0, 'failed' => 0, 'actions' => 0];
        if (count($recipients) == 0) {
            return $counts;
        }

        $until = $now->modify(self::SETTLE);
        $until_sql = $until->format(self::DATETIME);
        $sinces = self::starts($recipients, $until, $dry_run);
        $actions = self::actions(min($sinces), $until_sql);
        $counts['actions'] = array_sum(array_map(fn($bill) => count($bill['actions']), $actions));

        foreach ($recipients as $i => $recipient) {
            $digest = self::digest($recipient, $actions, $sinces[$i]);
            if ($digest === null) {
                $counts['empty']++;
                if (!$dry_run) {
                    self::notified($recipient['user_id'], $until_sql);
                }
                continue;
            }

            $sent = self::deliver($mailer, $recipient, $digest);
            $counts[$sent ? 'sent' : 'failed']++;
            if (!$dry_run) {
                if ($sent) {
                    self::notified($recipient['user_id'], $until_sql);
                }
                self::log($recipient['user_id'], $digest, $sent, $mailer, $now);
            }
        }
        return $counts;
    }

    /**
     * When each recipient's message starts (see since()).
     *
     * A dry run never moves last_notified, so a user who's never had a message would always start
     * at the run, and have nothing in theirs. Instead, a dry run shows what the past day's would be.
     */
    private static function starts(array $recipients, DateTimeImmutable $until, bool $dry_run): array
    {
        $sinces = [];
        foreach ($recipients as $i => $recipient) {
            $last = $recipient['last_notified'];
            if ($dry_run && ($last === null || $last === '')) {
                $last = $until->modify('-1 day')->format(self::DATETIME);
            }
            $sinces[$i] = self::since($last, $until);
        }
        return $sinces;
    }

    /**
     * Send a user their message. Returns whether it was sent.
     */
    private static function deliver(Mailer $mailer, array $recipient, array $digest): bool
    {
        $message = self::render($recipient, $digest);
        return $mailer->send(
            $recipient['email'],
            (string) $recipient['name'],
            $message['subject'],
            $message['text'],
            $message['html'],
            $message['headers']
        );
    }

    /**
     * Record a message in email_log, sent or not.
     */
    private static function log(int $user_id, array $digest, bool $sent, Mailer $mailer, DateTimeImmutable $now): void
    {
        $error = null;
        if (!$sent) {
            $error = $mailer instanceof PhpMailerMailer && $mailer->error !== '' ? $mailer->error : 'not sent';
        }
        mysqli_execute_query(
            $GLOBALS['db'],
            'INSERT INTO email_log SET user_id = ?, bills = ?, actions = ?, success = ?, error = ?, date_created = ?',
            [$user_id, $digest['bills'], $digest['actions'], $sent ? 'y' : 'n', $error === null ? null : mb_substr($error, 0, 255), $now->format(self::DATETIME)]
        );
    }

    /**
     * Whether a user, by the hash in their unsubscribe link, gets email: true, false, or null if
     * there's no such user.
     */
    public static function subscribed(string $unsub_hash): ?bool
    {
        if (preg_match('/^[a-z0-9]{8}$/', $unsub_hash) !== 1) {
            return null;
        }
        $result = mysqli_execute_query(
            $GLOBALS['db'],
            'SELECT email_active FROM dashboard_user_data WHERE unsub_hash = ?',
            [$unsub_hash]
        );
        $row = $result ? mysqli_fetch_assoc($result) : null;
        return $row ? $row['email_active'] == 'y' : null;
    }

    /**
     * Stop sending email to a user, by the hash in their unsubscribe link. (They can turn it back
     * on in their preferences.) Returns their user ID, or null if there's no such user.
     */
    public static function unsubscribe(string $unsub_hash): ?int
    {
        if (self::subscribed($unsub_hash) === null) {
            return null;
        }
        $result = mysqli_execute_query(
            $GLOBALS['db'],
            'SELECT user_id FROM dashboard_user_data WHERE unsub_hash = ?',
            [$unsub_hash]
        );
        $user_id = (int) mysqli_fetch_row($result)[0];
        mysqli_execute_query(
            $GLOBALS['db'],
            'UPDATE dashboard_user_data SET email_active = "n" WHERE user_id = ?',
            [$user_id]
        );
        return $user_id;
    }

    /**
     * Record that a user has been sent everything up to $until.
     */
    private static function notified(int $user_id, string $until): void
    {
        mysqli_execute_query(
            $GLOBALS['db'],
            'UPDATE dashboard_user_data SET last_notified = ? WHERE user_id = ?',
            [$until, $user_id]
        );
    }

    /**
     * An action's date, as "January 26," or '' if it has none.
     */
    private static function date(?string $date): string
    {
        if ($date === null || $date === '' || strtotime($date) === false) {
            return '';
        }
        return date('F j', strtotime($date));
    }

    private static function html($text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
