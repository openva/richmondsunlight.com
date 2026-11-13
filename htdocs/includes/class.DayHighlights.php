<?php

/**
 * A day's highlights: the bills that something was done to on a day, scored by how likely they
 * are to interest a reader, and the five to ten best of them. Also the news about a day's bills,
 * and what people did with them on this site. See docs/minutes-replacement.md.
 *
 * Scores are computed ahead of time, by rs-machine's cron/day_highlights.php (hourly for the past
 * two days, and nightly for the past two weeks), and stored in day_highlights, because they read
 * bills_views, which is too big to query on every page load. Day::record_for_api() reads them.
 *
 * A score adds up points for each of these, and keeps each one's value and points, so that the
 * page can say why a bill ranked high, and so that the weights (WEIGHTS) can be tuned:
 *
 * - What was done to it: a final vote counts for more than a referral.
 * - How close its votes were, and how much they divided the parties.
 * - How long it was discussed (on video, by its chyrons), and by how many legislators.
 * - News stories about it, from three days before to three days after.
 * - Interest in it on this site that day and the next (hotness's formula, for those days), and
 *   its interestingness, a little, as a tiebreaker.
 *
 * rs-machine and rs-api copy this file from this repo's deploy branch, as they do class.Day.php.
 */
class DayHighlights
{
    /**
     * Each signal's weight. Points for counts are the weight times log2(1 + the count), so that
     * the tenth news story counts for less than the first.
     */
    public const WEIGHTS = [
        // What was done to the bill. Only its most important action that day counts.
        'floor_vote' => 10,          // Passed, or failed, on the floor
        'committee_stopped' => 8,    // Continued, passed by indefinitely, tabled, or defeated
        'committee_advanced' => 6,   // Reported
        'subcommittee' => 4,         // A subcommittee's recommendation
        'veto' => 10,
        'governor' => 4,             // The governor's amendments, and acting on them
        'conference' => 3,           // A vote on a conference report
        'incorporated' => 1,

        'close' => 12,               // Times (1 - margin / votes cast)³, for the closest vote
        'party_line' => 10,          // Times how far apart the parties voted (0 to 1)
        'discussion' => 4,           // Times log2(1 + minutes of discussion)
        'speakers' => 2,             // For each legislator who spoke, after the first
        'news' => 8,                 // Times log2(1 + stories)
        'site' => 2.5,               // Times log2(1 + the day's hotness)
        'interestingness' => 0.5,    // Times log2(1 + interestingness)
    ];

    /** At most this many legislators who spoke count. */
    private const MAX_SPEAKERS = 8;

    /** How many highlights a day has, at most and (if it has that many candidates) at least. */
    public const MAX_HIGHLIGHTS = 10;
    public const MIN_HIGHLIGHTS = 5;

    /** Past the minimum, a bill needs this fraction of the top bill's score to be a highlight. */
    private const RELATIVE_CUTOFF = 0.5;

    /** At most this many highlights are any one legislator's bills. */
    public const MAX_PER_PATRON = 2;

    /** Each chamber that did something gets at least this many highlights, if it has them. */
    public const MIN_PER_CHAMBER = 2;

    /** News stories count from this many days before a day to this many after. */
    private const NEWS_DAYS = 3;

    /** Chyrons of a bill more than this many seconds apart are separate discussions of it. */
    private const CLIP_GAP = 30;

    /** How much earlier than a discussion's first chyron its clip starts, in seconds. */
    private const CLIP_LEAD = 10;

    /**
     * Run a prepared query, and return all of its rows (none, if it fails, as it does before
     * day_highlights exists).
     *
     * @param string $sql
     * @param string $types
     * @param array $params
     * @return array
     */
    private static function rows(string $sql, string $types = '', array $params = []): array
    {
        try {
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
        } catch (mysqli_sql_exception $e) {
            return [];
        }
    }

    /**
     * A list of integers, for an IN () clause.
     *
     * @param array $ids
     * @return string
     */
    private static function id_list(array $ids): string
    {
        return implode(',', array_map('intval', $ids)) ?: '0';
    }

    /**
     * Points for a count: the weight times log2(1 + the count).
     *
     * @param string $signal
     * @param float $count
     * @return float
     */
    private static function log_points(string $signal, float $count): float
    {
        return self::WEIGHTS[$signal] * log(1 + max(0, $count), 2);
    }

    /**
     * What an action is worth, by what it did to the bill.
     *
     * @param array $action From Day::bills_acted_on()
     * @return array The signal's name (or null, if it's worth nothing) and its points
     */
    public static function action_points(array $action): array
    {
        $signal = null;
        switch ($action['type']) {
            case 'passed':
            case 'failed':
                $signal = 'floor_vote';
                break;
            case 'committee':
                $signal = ['stopped' => 'committee_stopped', 'advanced' => 'committee_advanced', 'incorporated' => 'incorporated'][$action['outcome']] ?? null;
                break;
            case 'subcommittee':
                $signal = $action['outcome'] === 'incorporated' ? 'incorporated' : 'subcommittee';
                break;
            case 'governor':
                $signal = preg_match('/\bveto/i', $action['text']) ? 'veto' : 'governor';
                break;
            case 'conference':
                // Only a vote on a conference report decides anything. (Not appointing
                // conferees, or insisting on an amendment, or acceding to a request for a
                // conference.)
                $signal = preg_match('/conference report (?:agreed|adopted|rejected|defeated|failed)/i', $action['text']) ? 'conference' : null;
                break;
        }

        return [$signal, $signal !== null ? (float) self::WEIGHTS[$signal] : 0.0];
    }

    /**
     * The reason for a close vote, as readers see it: "Close vote, 45–52". If the vote was in the
     * other chamber from the action that the highlight shows, it says so ("Close Senate vote,
     * 21–18"), so that it isn't mistaken for that action's vote.
     *
     * @param array $vote From Day::classify()
     * @param string|null $vote_chamber Where the vote was
     * @param string|null $shown_chamber Where the action that the highlight shows was
     * @return string
     */
    public static function close_vote_label(array $vote, ?string $vote_chamber, ?string $shown_chamber): string
    {
        $which = $vote_chamber !== null && $shown_chamber !== null && $vote_chamber !== $shown_chamber
            ? ucfirst($vote_chamber) . ' '
            : '';

        return 'Close ' . $which . 'vote, ' . $vote['yes'] . '–' . $vote['no'];
    }

    /**
     * Whether a bill is a ceremonial resolution (commending someone, or celebrating a life), which
     * is never a highlight.
     *
     * @param array $bill With number and catch_line
     * @return bool
     */
    public static function is_ceremonial(array $bill): bool
    {
        return preg_match('/^[hs][jr]/', $bill['number'])
            && preg_match('/^(?:Commending|Celebrating|Memorializing|Honoring|Congratulating|Recognizing)\b/i', $bill['catch_line']);
    }

    /**
     * How close a vote was, from 0 (unanimous) to 1 (a tie). Voice votes, and votes of fewer than
     * three, aren't close.
     *
     * @param array|null $vote From Day::classify()
     * @return float
     */
    public static function closeness(?array $vote): float
    {
        if ($vote === null || $vote['voice'] || $vote['yes'] === 0 || $vote['no'] === 0
            || $vote['yes'] + $vote['no'] < 3) {
            return 0.0;
        }

        return 1 - abs($vote['yes'] - $vote['no']) / ($vote['yes'] + $vote['no']);
    }

    /**
     * How far apart the parties voted, from 0 (each party voted the same way, in the same
     * proportion) to 1 (every Democrat voted one way, and every Republican the other). Null if
     * either party had fewer than two votes.
     *
     * @param array $counts Votes, by party ("D" or "R"), then vote ("Y" or "N")
     * @return float|null
     */
    public static function party_line(array $counts): ?float
    {
        $share = [];
        foreach (['D', 'R'] as $party) {
            $yes = (int) ($counts[$party]['Y'] ?? 0);
            $no = (int) ($counts[$party]['N'] ?? 0);
            if ($yes + $no < 2) {
                return null;
            }
            $share[$party] = $yes / ($yes + $no);
        }

        return abs($share['D'] - $share['R']);
    }

    /**
     * How each party voted on each of some roll calls. A legislator's party is that of the term
     * they were serving on the vote's date.
     *
     * @param array $vote_ids votes.id values
     * @return array By vote ID, then party, then vote ("Y" or "N"): the number of votes
     */
    private static function party_votes(array $vote_ids): array
    {
        if (count($vote_ids) === 0) {
            return [];
        }
        $counts = [];
        foreach (self::rows('SELECT representatives_votes.vote_id, representatives_votes.vote,
                    COALESCE(terms.party, representatives.party) AS party, COUNT(*) AS number
                FROM representatives_votes
                JOIN votes
                    ON votes.id = representatives_votes.vote_id
                LEFT JOIN terms
                    ON terms.id = (
                        SELECT t.id
                        FROM terms AS t
                        WHERE t.person_id = representatives_votes.representative_id
                            AND t.date_started <= votes.date
                        ORDER BY t.date_started DESC
                        LIMIT 1)
                LEFT JOIN representatives
                    ON representatives.id = representatives_votes.representative_id
                WHERE representatives_votes.vote_id IN (' . self::id_list($vote_ids) . ')
                    AND representatives_votes.vote IN ("Y", "N")
                GROUP BY representatives_votes.vote_id, party, representatives_votes.vote') as $row) {
            $counts[(int) $row['vote_id']][$row['party']][$row['vote']] = (int) $row['number'];
        }

        return $counts;
    }

    /**
     * When each bill was discussed on a day's videos, by its chyrons, and which legislators
     * appeared on screen during each discussion.
     *
     * @param string $date
     * @param array $bill_ids
     * @return array By bill ID: seconds (in all), speakers (people.id values), and clip (the
     *               longest discussion: slug, start, duration, and label), or nothing
     */
    private static function discussions(string $date, array $bill_ids): array
    {
        if (count($bill_ids) === 0) {
            return [];
        }
        $files = [];
        foreach (self::rows('SELECT id, slug, title, chamber
                FROM files
                WHERE date = ? AND type = "video" AND slug IS NOT NULL', 's', [$date]) as $row) {
            $files[(int) $row['id']] = $row;
        }
        if (count($files) === 0) {
            return [];
        }

        // Each bill's chyrons, and each legislator's, by video.
        $bill_times = [];
        $legislator_times = [];
        foreach (self::rows('SELECT file_id, TIME_TO_SEC(time) AS seconds, type, linked_id
                FROM video_index
                WHERE file_id IN (' . self::id_list(array_keys($files)) . ') AND ignored = "n"
                    AND linked_id IS NOT NULL
                    AND (type = "legislator" OR linked_id IN (' . self::id_list($bill_ids) . '))
                ORDER BY file_id, time') as $row) {
            if ($row['type'] === 'bill') {
                $bill_times[(int) $row['linked_id']][(int) $row['file_id']][] = (int) $row['seconds'];
            } else {
                $legislator_times[(int) $row['file_id']][] = [(int) $row['seconds'], (int) $row['linked_id']];
            }
        }

        $discussions = [];
        foreach ($bill_times as $bill_id => $by_file) {
            $seconds = 0;
            $speakers = [];
            $clip = null;
            foreach ($by_file as $file_id => $times) {
                // Split the chyrons into runs, at each gap of more than CLIP_GAP seconds.
                $runs = [];
                foreach ($times as $time) {
                    $last = count($runs) - 1;
                    if ($last >= 0 && $time - $runs[$last][1] <= self::CLIP_GAP) {
                        $runs[$last][1] = $time;
                    } else {
                        $runs[] = [$time, $time];
                    }
                }
                foreach ($runs as [$start, $end]) {
                    $seconds += $end - $start;
                    foreach ($legislator_times[$file_id] ?? [] as [$time, $person_id]) {
                        if ($time >= $start - self::CLIP_LEAD && $time <= $end + self::CLIP_LEAD) {
                            $speakers[$person_id] = true;
                        }
                    }
                    if ($clip === null || $end - $start > $clip['duration']) {
                        $clip = [
                            'slug' => $files[$file_id]['slug'],
                            'start' => max(0, $start - self::CLIP_LEAD),
                            'duration' => $end - $start + self::CLIP_LEAD,
                            'label' => Video::meeting_label($files[$file_id]['title'], $files[$file_id]['chamber']),
                        ];
                    }
                }
            }
            $discussions[$bill_id] = ['seconds' => $seconds, 'speakers' => array_keys($speakers), 'clip' => $clip];
        }

        return $discussions;
    }

    /**
     * How many news stories mention each bill, from NEWS_DAYS before a day to NEWS_DAYS after.
     *
     * @param string $date
     * @param array $bill_ids
     * @return array By bill ID
     */
    private static function news_counts(string $date, array $bill_ids): array
    {
        if (count($bill_ids) === 0) {
            return [];
        }
        $counts = [];
        foreach (self::rows('SELECT bill_id, COUNT(DISTINCT url) AS stories
                FROM bills_news
                WHERE bill_id IN (' . self::id_list($bill_ids) . ')
                    AND date BETWEEN DATE(?) - INTERVAL ' . self::NEWS_DAYS . ' DAY AND DATE(?) + INTERVAL ' . self::NEWS_DAYS . ' DAY
                GROUP BY bill_id', 'ss', [$date, $date]) as $row) {
            $counts[(int) $row['bill_id']] = (int) $row['stories'];
        }

        return $counts;
    }

    /**
     * Interest in each bill on this site on a day and the next: hotness's parts (see rs-machine's
     * cron/cleanup.php), counted for those two days.
     *
     * @param string $date
     * @param array $bill_ids
     * @return array By bill ID: comments, viewers, portfolios, poll_votes, subscriptions, hotness,
     *               and interestingness
     */
    private static function interest(string $date, array $bill_ids): array
    {
        if (count($bill_ids) === 0) {
            return [];
        }
        $ids = self::id_list($bill_ids);
        $interest = [];
        foreach ($bill_ids as $id) {
            $interest[$id] = ['comments' => 0, 'viewers' => 0, 'portfolios' => 0, 'poll_votes' => 0, 'subscriptions' => 0, 'interestingness' => 0];
        }
        // Each part: its table, what's counted, its date column, and any other condition.
        foreach ([
            'comments' => ['comments', 'COUNT(*)', 'date_created', 'status = "published"'],
            'viewers' => ['bills_views', 'COUNT(DISTINCT ip)', 'date', '1'],
            'portfolios' => ['dashboard_bills', 'COUNT(*)', 'date_created', '1'],
            'poll_votes' => ['polls', 'COUNT(*)', 'date_created', '1'],
            'subscriptions' => ['comments_subscriptions', 'COUNT(*)', 'date_created', '1'],
        ] as $part => [$table, $count, $column, $condition]) {
            foreach (self::rows('SELECT bill_id, ' . $count . ' AS n
                    FROM ' . $table . '
                    WHERE ' . $column . ' >= ? AND ' . $column . ' < DATE(?) + INTERVAL 2 DAY
                        AND bill_id IN (' . $ids . ') AND ' . $condition . '
                    GROUP BY bill_id', 'ss', [$date, $date]) as $row) {
                if (isset($interest[(int) $row['bill_id']])) {
                    $interest[(int) $row['bill_id']][$part] = (int) $row['n'];
                }
            }
        }
        foreach (self::rows('SELECT id, interestingness FROM bills WHERE id IN (' . $ids . ')') as $row) {
            $interest[(int) $row['id']]['interestingness'] = (int) $row['interestingness'];
        }
        foreach ($interest as &$parts) {
            $parts['hotness'] = $parts['comments'] * 5 + $parts['viewers'] / 3 + $parts['portfolios'] * 10
                + $parts['poll_votes'] * 2 + $parts['subscriptions'] * 2;
        }
        unset($parts);

        return $interest;
    }

    /**
     * A number of things, in words: "1 news story," "3 news stories."
     */
    private static function count_of(int $n, string $one, string $many): string
    {
        return $n . ' ' . ($n === 1 ? $one : $many);
    }

    /**
     * Score every bill that something was done to on a day.
     *
     * @param string $date YYYY-MM-DD
     * @param array $hidden IDs of bills that an admin has hidden from the day's highlights
     * @return array|null By bill ID: bill_id, chamber, patron_id, score, eligible (whether it can
     *                    be a highlight), position (its place among the highlights, or null), and
     *                    reasons (see below); or null if the day has no page. Reasons are signals
     *                    (each signal's value and points), labels (the ones readers see), action
     *                    (what was done to the bill), bill, and clip.
     */
    public static function compute(string $date, array $hidden = []): ?array
    {
        $day = Day::bills_acted_on($date);
        if ($day === null) {
            return null;
        }
        $bills = $day['bills'];
        $bill_ids = array_keys($bills);

        $vote_ids = [];
        foreach ($bills as $bill) {
            foreach ($bill['actions'] as $action) {
                if ($action['vote_id'] !== null) {
                    $vote_ids[$action['vote_id']] = true;
                }
            }
        }
        $party_votes = self::party_votes(array_keys($vote_ids));
        $discussions = self::discussions($date, $bill_ids);
        $news = self::news_counts($date, $bill_ids);
        $interest = self::interest($date, $bill_ids);

        // How the bills rank by how many people looked at them.
        $viewers = array_filter(array_map(fn($parts) => $parts['viewers'], $interest));
        arsort($viewers);
        $view_rank = array_flip(array_keys($viewers));

        $scored = [];
        foreach ($bills as $bill_id => $bill) {
            $signals = [];
            $labels = [];
            $add = function (string $signal, $value, float $points) use (&$signals) {
                $signals[] = ['signal' => $signal, 'value' => $value, 'points' => round($points, 2)];
            };

            // The most important thing done to it. (Of equals, one with a vote.)
            $best = null;
            $best_points = -1.0;
            foreach ($bill['actions'] as $action) {
                [$signal, $points] = self::action_points($action);
                $points += $action['vote'] !== null ? 0.01 : 0;
                if ($points > $best_points) {
                    [$best, $best_signal, $best_points] = [$action, $signal, $points];
                }
            }
            $best_points = floor($best_points);
            $add('action', $best_signal ?? $best['type'], $best_points);

            // Its closest vote, and its most partisan one.
            $closest = null;
            $closest_chamber = null;
            $party_line = null;
            foreach ($bill['actions'] as $action) {
                if (self::closeness($action['vote']) > self::closeness($closest)) {
                    $closest = $action['vote'];
                    $closest_chamber = $action['chamber'];
                }
                if ($action['vote_id'] !== null && isset($party_votes[$action['vote_id']])) {
                    $line = self::party_line($party_votes[$action['vote_id']]);
                    if ($line !== null && ($party_line === null || $line > $party_line)) {
                        $party_line = $line;
                    }
                }
            }
            if ($closest !== null) {
                $closeness = self::closeness($closest);
                $add('close', round($closeness, 3), self::WEIGHTS['close'] * $closeness ** 3);
                if (Day::is_close($closest)) {
                    $labels[] = self::close_vote_label($closest, $closest_chamber, $best['chamber']);
                }
            }
            if ($party_line !== null && $party_line > 0) {
                $add('party_line', round($party_line, 3), self::WEIGHTS['party_line'] * $party_line);
                if ($party_line >= 0.9) {
                    $labels[] = 'Party-line vote';
                } elseif ($party_line >= 0.6) {
                    $labels[] = 'Mostly along party lines';
                }
            }

            // How much it was discussed.
            $discussion = $discussions[$bill_id] ?? null;
            if ($discussion !== null) {
                $minutes = $discussion['seconds'] / 60;
                $add('discussion', round($minutes, 1), self::log_points('discussion', $minutes));
                if ($minutes >= 3) {
                    $labels[] = self::count_of((int) round($minutes), 'minute', 'minutes') . ' of discussion';
                }
                $speakers = count($discussion['speakers']);
                if ($speakers > 1) {
                    $add('speakers', $speakers, self::WEIGHTS['speakers'] * (min($speakers, self::MAX_SPEAKERS) - 1));
                }
                if ($speakers >= 3) {
                    $labels[] = $speakers . ' legislators spoke';
                }
            }

            // Outside attention.
            if (!empty($news[$bill_id])) {
                $add('news', $news[$bill_id], self::log_points('news', $news[$bill_id]));
                $labels[] = self::count_of($news[$bill_id], 'news story', 'news stories');
            }

            // Attention on this site.
            $parts = $interest[$bill_id];
            if ($parts['hotness'] > 0) {
                $add('site', array_diff_key($parts, ['interestingness' => 0]), self::log_points('site', $parts['hotness']));
            }
            if (isset($view_rank[$bill_id]) && $parts['viewers'] >= 3) {
                if ($view_rank[$bill_id] === 0) {
                    $labels[] = 'The most-viewed of the day’s bills';
                } elseif ($view_rank[$bill_id] < 3) {
                    $labels[] = 'One of the most-viewed of the day’s bills';
                }
            }
            if ($parts['comments'] > 0) {
                $labels[] = self::count_of($parts['comments'], 'comment', 'comments');
            }
            if ($parts['portfolios'] >= 2) {
                $labels[] = 'Added to ' . $parts['portfolios'] . ' Photosynthesis portfolios';
            }
            if ($parts['interestingness'] > 0) {
                $add('interestingness', $parts['interestingness'], self::log_points('interestingness', $parts['interestingness']));
            }

            $score = round(array_sum(array_column($signals, 'points')), 2);
            $scored[$bill_id] = [
                'bill_id' => $bill_id,
                'chamber' => $best['chamber'],
                'patron_id' => $bill['patron_id'],
                'score' => $score,
                'eligible' => $day['in_session'] && $best_points > 0 && !in_array($bill_id, $hidden, true)
                    && !self::is_ceremonial($bill['bill']),
                'position' => null,
                'reasons' => [
                    'signals' => $signals,
                    'labels' => $labels,
                    'action' => [
                        'text' => $best['text'],
                        'type' => $best['type'],
                        'outcome' => $best['outcome'],
                        'chamber' => $best['chamber'],
                        'where' => $best['where'],
                        'place' => $best['place'],
                        'vote' => $best['vote'],
                    ],
                    'bill' => $bill['bill'],
                    'clip' => $discussion['clip'] ?? null,
                ],
            ];
        }

        foreach (self::select($scored) as $position => $bill_id) {
            $scored[$bill_id]['position'] = $position + 1;
        }

        return $scored;
    }

    /**
     * Choose a day's highlights from its scored bills: the best, up to MAX_HIGHLIGHTS (and down
     * to MIN_HIGHLIGHTS, or as far as RELATIVE_CUTOFF allows), no more than MAX_PER_PATRON of any
     * one legislator's, and at least MIN_PER_CHAMBER of each chamber's, if it has that many.
     *
     * @param array $scored By bill ID: score, chamber, patron_id, and eligible
     * @return array The highlights' bill IDs, best first
     */
    public static function select(array $scored): array
    {
        $candidates = array_filter($scored, fn($bill) => $bill['eligible']);
        uasort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);
        if (count($candidates) === 0) {
            return [];
        }
        $top = reset($candidates)['score'];

        $picked = [];
        $patron_count = function (?int $patron_id) use (&$picked, $candidates): int {
            if ($patron_id === null) {
                return 0;
            }
            return count(array_filter($picked, fn($id) => $candidates[$id]['patron_id'] === $patron_id));
        };
        foreach ($candidates as $id => $bill) {
            if (count($picked) >= self::MAX_HIGHLIGHTS) {
                break;
            }
            if (count($picked) >= self::MIN_HIGHLIGHTS && $bill['score'] < $top * self::RELATIVE_CUTOFF) {
                break;
            }
            if ($patron_count($bill['patron_id']) < self::MAX_PER_PATRON) {
                $picked[] = $id;
            }
        }

        // Make sure that each chamber has its share, replacing the weakest highlights of a
        // chamber that has more than its share, if there's no more room.
        $in_chamber = fn(array $picked, string $chamber) => count(array_filter(
            $picked,
            fn($id) => $candidates[$id]['chamber'] === $chamber
        ));
        $chambers = array_unique(array_filter(array_column($candidates, 'chamber')));
        foreach ($chambers as $chamber) {
            foreach ($candidates as $id => $bill) {
                if ($in_chamber($picked, $chamber) >= self::MIN_PER_CHAMBER) {
                    break;
                }
                if ($bill['chamber'] !== $chamber || in_array($id, $picked, true)
                    || $patron_count($bill['patron_id']) >= self::MAX_PER_PATRON) {
                    continue;
                }
                if (count($picked) >= self::MAX_HIGHLIGHTS) {
                    $replaceable = array_filter($picked, fn($other) => $candidates[$other]['chamber'] !== $chamber
                        && $in_chamber($picked, (string) $candidates[$other]['chamber']) > self::MIN_PER_CHAMBER);
                    if (count($replaceable) === 0) {
                        break;
                    }
                    $picked = array_values(array_diff($picked, [end($replaceable)]));
                }
                $picked[] = $id;
            }
        }

        usort($picked, fn($a, $b) => $candidates[$b]['score'] <=> $candidates[$a]['score']);

        return $picked;
    }

    /**
     * Score a day's bills, and store the scores in day_highlights, keeping any that an admin has
     * hidden hidden.
     *
     * @param string $date YYYY-MM-DD
     * @return int|null How many highlights the day has, or null if it has no page
     */
    public static function save(string $date): ?int
    {
        $hidden = array_map(fn($row) => (int) $row['bill_id'], self::rows(
            'SELECT bill_id FROM day_highlights WHERE date = ? AND hidden = "y"',
            's',
            [$date]
        ));
        $scored = self::compute($date, $hidden);
        if ($scored === null) {
            mysqli_query($GLOBALS['db'], 'DELETE FROM day_highlights WHERE date = "' . mysqli_real_escape_string($GLOBALS['db'], $date) . '"');
            return null;
        }

        mysqli_begin_transaction($GLOBALS['db']);
        $stmt = mysqli_prepare($GLOBALS['db'], 'INSERT INTO day_highlights
                (date, bill_id, chamber, score, position, reasons, date_created, date_modified)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE chamber = VALUES(chamber), score = VALUES(score),
                position = VALUES(position), reasons = VALUES(reasons), date_modified = NOW()');
        foreach ($scored as $bill) {
            $reasons = json_encode($bill['reasons'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $stmt->bind_param('sisdis', $date, $bill['bill_id'], $bill['chamber'], $bill['score'], $bill['position'], $reasons);
            $stmt->execute();
        }
        mysqli_query($GLOBALS['db'], 'DELETE FROM day_highlights
            WHERE date = "' . mysqli_real_escape_string($GLOBALS['db'], $date) . '"
                AND bill_id NOT IN (' . self::id_list(array_keys($scored)) . ')');
        mysqli_commit($GLOBALS['db']);

        return count(array_filter($scored, fn($bill) => $bill['position'] !== null));
    }

    /**
     * Hide a bill from a day's highlights, or show it again, and choose the day's highlights
     * again.
     *
     * @param string $date
     * @param int $bill_id
     * @param bool $hidden
     * @return void
     */
    public static function set_hidden(string $date, int $bill_id, bool $hidden): void
    {
        $stmt = mysqli_prepare($GLOBALS['db'], 'UPDATE day_highlights SET hidden = ? WHERE date = ? AND bill_id = ?');
        $value = $hidden ? 'y' : 'n';
        $stmt->bind_param('ssi', $value, $date, $bill_id);
        $stmt->execute();
        self::save($date);
    }

    /**
     * Every bill scored on a day, best first, for the admin page.
     *
     * @param string $date
     * @return array Each one's bill_id, chamber, score, position, hidden, reasons, and date_modified
     */
    public static function scores(string $date): array
    {
        $rows = self::rows('SELECT bill_id, chamber, score, position, hidden, reasons, date_modified
            FROM day_highlights
            WHERE date = ?
            ORDER BY position IS NULL, position, score DESC', 's', [$date]);
        foreach ($rows as &$row) {
            $row['reasons'] = json_decode((string) $row['reasons'], true) ?: [];
        }
        unset($row);

        return $rows;
    }

    /**
     * A day's highlights, for the API.
     *
     * @param string $date
     * @return array highlights (each one's position, bill, chamber, score, action, reasons, and
     *               clip), and updated (when the scores were last computed, or null)
     */
    public static function for_api(string $date): array
    {
        $highlights = [];
        $updated = null;
        foreach (self::rows('SELECT position, chamber, score, hidden, reasons, date_modified
                FROM day_highlights
                WHERE date = ?
                ORDER BY position', 's', [$date]) as $row) {
            $updated = max($updated, $row['date_modified']);
            if ($row['position'] === null || $row['hidden'] === 'y') {
                continue;
            }
            $reasons = json_decode((string) $row['reasons'], true);
            if (!is_array($reasons)) {
                continue;
            }
            $clip = $reasons['clip'] ?? null;
            $highlights[] = [
                'position' => (int) $row['position'],
                'bill' => $reasons['bill'],
                'chamber' => $row['chamber'],
                'score' => (float) $row['score'],
                'action' => $reasons['action'],
                'reasons' => $reasons['labels'],
                'clip' => $clip !== null ? [
                    'url' => 'https://www.richmondsunlight.com' . Video::page_path($clip['slug'], $clip['start']),
                    'start' => $clip['start'],
                    'duration' => $clip['duration'],
                    'label' => $clip['label'],
                ] : null,
            ];
        }

        return [
            'highlights' => $highlights,
            'updated' => $updated !== null ? date('c', strtotime($updated)) : null,
        ];
    }

    /**
     * A bill's number, year, title, and URL, from a row with number, year, and catch_line.
     *
     * @param array $row
     * @return array
     */
    private static function bill(array $row): array
    {
        return [
            'number' => $row['number'],
            'year' => (int) $row['year'],
            'catch_line' => html_entity_decode((string) $row['catch_line'], ENT_QUOTES),
            'url' => 'https://www.richmondsunlight.com/bill/' . $row['year'] . '/' . $row['number'] . '/',
        ];
    }

    /**
     * News stories about a day's bills, from two days before it to three days after, newest
     * first.
     *
     * @param string $date
     * @param array $bill_ids The bills that something was done to on that day
     * @return array Each story's title, url, publication, date, and bills
     */
    public static function news_for_api(string $date, array $bill_ids): array
    {
        if (count($bill_ids) === 0) {
            return [];
        }
        $stories = [];
        foreach (self::rows('SELECT bills_news.url, bills_news.title, bills_news.publication,
                    bills_news.date, bills.number, bills.catch_line, sessions.year
                FROM bills_news
                JOIN bills
                    ON bills.id = bills_news.bill_id
                JOIN sessions
                    ON sessions.id = bills.session_id
                WHERE bills_news.bill_id IN (' . self::id_list($bill_ids) . ')
                    AND bills_news.date BETWEEN DATE(?) - INTERVAL 2 DAY AND DATE(?) + INTERVAL ' . self::NEWS_DAYS . ' DAY
                ORDER BY bills_news.date DESC, bills_news.id ASC', 'ss', [$date, $date]) as $row) {
            $stories[$row['url']] ??= [
                'title' => html_entity_decode((string) $row['title'], ENT_QUOTES),
                'url' => $row['url'],
                'publication' => $row['publication'],
                'date' => $row['date'],
                'bills' => [],
            ];
            $stories[$row['url']]['bills'][] = self::bill($row);
        }

        return array_slice(array_values($stories), 0, 20);
    }

    /**
     * What people did on this site about a day: comments posted that day on its bills, how its
     * most-voted-on bills are doing in our polls, and the bills of that year that the most people
     * looked at that day (whether or not anything was done to them).
     *
     * @param string $date
     * @param array $bill_ids The bills that something was done to on that day
     * @return array comments (each one's bill, name, excerpt, url, and date), polls (each bill,
     *               and its yes and no votes), and most_viewed (each bill, and its viewers)
     */
    public static function site_activity_for_api(string $date, array $bill_ids): array
    {
        $activity = ['comments' => [], 'polls' => [], 'most_viewed' => []];
        $ids = self::id_list($bill_ids);
        $columns = 'bills.number, bills.catch_line, sessions.year';
        $join = ' JOIN bills ON bills.id = %s.bill_id JOIN sessions ON sessions.id = bills.session_id ';

        if (count($bill_ids) > 0) {
            foreach (self::rows('SELECT comments.name, comments.comment, comments.date_created, ' . $columns . '
                    FROM comments' . sprintf($join, 'comments') . '
                    WHERE comments.bill_id IN (' . $ids . ') AND comments.status = "published"
                        AND comments.date_created >= ? AND comments.date_created < DATE(?) + INTERVAL 1 DAY
                    ORDER BY comments.date_created DESC
                    LIMIT 10', 'ss', [$date, $date]) as $row) {
                $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(stripslashes((string) $row['comment'])), ENT_QUOTES)));
                if (mb_strlen($text) > 280) {
                    $text = preg_replace('/\s+\S*$/', '', mb_substr($text, 0, 280)) . '…';
                }
                $bill = self::bill($row);
                $activity['comments'][] = [
                    'bill' => $bill,
                    'name' => trim(stripslashes((string) $row['name'])),
                    'excerpt' => $text,
                    'url' => $bill['url'] . '#comments',
                    'date' => date('c', strtotime($row['date_created'])),
                ];
            }

            foreach (self::rows('SELECT SUM(polls.vote = "y") AS yes, SUM(polls.vote = "n") AS no, ' . $columns . '
                    FROM polls' . sprintf($join, 'polls') . '
                    WHERE polls.bill_id IN (' . $ids . ') AND polls.date_created < DATE(?) + INTERVAL 1 DAY
                    GROUP BY polls.bill_id
                    HAVING COUNT(*) >= 10
                    ORDER BY COUNT(*) DESC
                    LIMIT 5', 's', [$date]) as $row) {
                $activity['polls'][] = ['bill' => self::bill($row), 'yes' => (int) $row['yes'], 'no' => (int) $row['no']];
            }
        }

        foreach (self::rows('SELECT COUNT(DISTINCT bills_views.ip) AS viewers, ' . $columns . '
                FROM bills_views' . sprintf($join, 'bills_views') . '
                WHERE bills_views.date >= ? AND bills_views.date < DATE(?) + INTERVAL 1 DAY
                    AND sessions.year = ?
                GROUP BY bills_views.bill_id
                HAVING viewers >= 3
                ORDER BY viewers DESC, bills.number
                LIMIT 5', 'ssi', [$date, $date, (int) substr($date, 0, 4)]) as $row) {
            $activity['most_viewed'][] = ['bill' => self::bill($row), 'viewers' => (int) $row['viewers']];
        }

        return $activity;
    }
}
