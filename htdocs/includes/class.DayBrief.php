<?php

/**
 * A day's brief: a few sentences about what the General Assembly did on a day, written by a
 * language model from the day's records alone (its summary, its highlights, and the news about
 * them), and checked against them before it's shown. See docs/minutes-replacement.md.
 *
 * rs-machine's cron/day_briefs.php writes them, the morning after each day, with write(), and
 * stores them in day_briefs. A brief that fails its checks is stored, but never shown; the day's
 * page shows its summary instead. The API serves the brief with the day (Day::record_for_api()).
 *
 * rs-machine and rs-api copy this file from this repo's deploy branch.
 */
class DayBrief
{
    /** The model, as for bills' narratives (rs-machine's cron/bills_status_narratives.php). */
    public const MODEL = 'gpt-6-luna';

    /** How many news stories the model is told about. */
    private const NEWS = 5;

    /** The longest brief that's acceptable, in characters. */
    private const MAX_LENGTH = 1200;

    /** How many sentences a brief may have. */
    private const MIN_SENTENCES = 2;
    private const MAX_SENTENCES = 5;

    /** Bill numbers, as people write them ("HB 341," "SJR 12"). */
    private const BILL = '/\b(HB|SB|HJR?|SJR?|HR|SR)\s*(\d+)\b/i';

    /** Tallies ("45–52," "45-52," "45 to 52"). */
    private const TALLY = '/\b(\d{1,3})\s*(?:–|—|-|to)\s*(\d{1,3})\b/u';

    /**
     * Run a prepared query, and return all of its rows (none, if it fails, as it does before
     * day_briefs exists).
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
     * A bill's number, as people write it: "hb341" is "HB 341," and "hj12" is "HJ 12."
     */
    private static function number(string $number): string
    {
        return mb_strtoupper(preg_replace('/^([a-z]+)/', '$1 ', $number));
    }

    /**
     * A bill's number, reduced for comparison: "HB 341," "hb341," and "HJR 12" (as "hj12").
     */
    private static function key(string $prefix, string $digits): string
    {
        return substr(strtolower($prefix), 0, 2) . (int) $digits;
    }

    /**
     * What the model is told about a day: only what's in its record.
     *
     * @param array $day From Day::record_for_api()
     * @return array|null date, summary, highlights, and news; or null if the day has no
     *                    highlights (and so needs no brief)
     */
    public static function input(array $day): ?array
    {
        if (empty($day['highlights'])) {
            return null;
        }

        $highlights = [];
        foreach ($day['highlights'] as $highlight) {
            $vote = $highlight['action']['vote'] ?? null;
            $highlights[] = [
                'bill' => self::number($highlight['bill']['number']),
                'title' => $highlight['bill']['catch_line'],
                'patron' => $highlight['bill']['patron']['name'] ?? null,
                'chamber' => $highlight['chamber'] !== null ? ucfirst($highlight['chamber']) : null,
                'action' => $highlight['action']['text'],
                'where' => $highlight['action']['where'] ?? null,
                'vote' => $vote === null ? null : ($vote['voice'] ? 'voice vote' : $vote['yes'] . '–' . $vote['no']),
                'reasons' => $highlight['reasons'],
            ];
        }

        // News about the highlights only: the model knows what happened to them that day, but not
        // to the day's other bills.
        $highlighted = array_column($highlights, 'bill');
        $news = [];
        foreach ($day['news'] ?? [] as $story) {
            $bills = array_values(array_intersect(
                array_map(fn($bill) => self::number($bill['number']), $story['bills']),
                $highlighted
            ));
            if (count($bills) > 0 && count($news) < self::NEWS) {
                $news[] = ['title' => $story['title'], 'publication' => $story['publication'], 'bills' => $bills];
            }
        }

        return [
            'date' => date('l, F j, Y', strtotime($day['date'])),
            'summary' => Day::summary($day),
            'highlights' => $highlights,
            'news' => $news,
        ];
    }

    /**
     * A fingerprint of what the model is told, so that a brief is rewritten only if that changes.
     */
    public static function hash(array $input): string
    {
        return hash('sha256', json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * The prompt.
     *
     * @param array $input From input()
     * @return string
     */
    public static function prompt(array $input): string
    {
        $lines = [];
        foreach ($input['highlights'] as $i => $h) {
            $lines[] = ($i + 1) . '. ' . $h['bill'] . ': ' . $h['title']
                . ($h['patron'] !== null ? ' (patron: ' . $h['patron'] . ')' : '') . "\n"
                . '   What happened, ' . ($h['where'] !== null ? 'in the ' . $h['where'] : 'on the ' . ($h['chamber'] ?? 'chamber') . ' floor')
                . ': ' . $h['action'] . ($h['vote'] !== null ? ', ' . $h['vote'] : '') . "\n"
                . (count($h['reasons']) > 0 ? '   Why it stands out: ' . implode('; ', $h['reasons']) . "\n" : '');
        }
        $news = [];
        foreach ($input['news'] as $story) {
            $news[] = '- "' . $story['title'] . '" (' . $story['publication'] . '), about ' . implode(', ', $story['bills']);
        }

        return 'Write a brief account, of three to five sentences, of what the Virginia General Assembly did on '
            . $input['date'] . ', for a general reader. Use only the facts below. Do not add anything from'
            . " outside knowledge: no background, no context, and no predictions.\n\n"
            . "Rules:\n"
            . "- Lead with the most important events. Mention the bills that matter most, by number, written as they are below (e.g. \"HB 341\").\n"
            . "- If you give a vote's tally, write it exactly as it's given below (e.g. \"45–52\"). Don't give any other numbers, except ones that appear below.\n"
            . "- Describe a vote as close or as along party lines only if the facts below say so.\n"
            . "- Write plainly and neutrally. Don't characterize bills or legislators, or speculate about motives.\n"
            . "- Write one paragraph of plain text: no headings, lists, links, or formatting, and no dates.\n\n"
            . 'The day in numbers: ' . implode(' ', $input['summary']) . "\n\n"
            . "The day's most notable bills, most notable first:\n" . implode('', $lines)
            . (count($news) > 0 ? "\nNews coverage:\n" . implode("\n", $news) . "\n" : '');
    }

    /**
     * What's wrong with a brief, if anything: anything that isn't in the day's record, and
     * anything that isn't a short paragraph of plain text.
     *
     * @param string $text The brief
     * @param array $input From input()
     * @return string[] The problems, to show the model (none, if it's fine)
     */
    public static function check(string $text, array $input): array
    {
        $problems = [];
        $text = trim($text);
        if ($text === '') {
            return ['It is empty.'];
        }
        if (mb_strlen($text) > self::MAX_LENGTH) {
            $problems[] = 'It is too long: it must be under ' . self::MAX_LENGTH . ' characters.';
        }
        $sentences = preg_match_all('/[.!?](?=\s|$)/u', $text);
        if ($sentences < self::MIN_SENTENCES || $sentences > self::MAX_SENTENCES) {
            $problems[] = 'It has ' . $sentences . ' sentences; it must have three to five.';
        }
        if (preg_match('/https?:|www\.|[*#_`\[\]]|^\s*[-•]|\n\s*\n/mu', $text)) {
            $problems[] = 'It must be one paragraph of plain text, with no formatting, lists, or links.';
        }

        // Every bill it names must be one of the day's.
        $bills = [];
        foreach ($input['highlights'] as $h) {
            preg_match(self::BILL, $h['bill'], $m);
            $bills[self::key($m[1], $m[2])] = true;
        }
        foreach ($input['news'] as $story) {
            foreach ($story['bills'] as $bill) {
                preg_match(self::BILL, $bill, $m);
                $bills[self::key($m[1], $m[2])] = true;
            }
        }
        preg_match_all(self::BILL, $text, $named, PREG_SET_ORDER);
        if (count($named) === 0) {
            $problems[] = 'It must name at least one bill, by number.';
        }
        foreach ($named as $m) {
            if (!isset($bills[self::key($m[1], $m[2])])) {
                $problems[] = $m[0] . ' is not one of the bills given.';
            }
        }
        $rest = preg_replace(self::BILL, ' ', $text);

        // Every tally must be one of the day's, as it was given.
        $source = json_encode($input, JSON_UNESCAPED_UNICODE);
        preg_match_all(self::TALLY, $source, $given, PREG_SET_ORDER);
        $tallies = array_flip(array_map(fn($m) => $m[1] . '–' . $m[2], $given));
        preg_match_all(self::TALLY, $rest, $used, PREG_SET_ORDER);
        foreach ($used as $m) {
            if (!isset($tallies[$m[1] . '–' . $m[2]])) {
                $problems[] = 'The tally ' . $m[0] . ' is not one given.';
            }
        }
        $rest = preg_replace(self::TALLY, ' ', $rest);

        // And every other number must be in the day's record.
        preg_match_all('/\d+/', $source, $numbers);
        $known = array_flip($numbers[0]);
        preg_match_all('/\d+/', $rest, $others);
        foreach (array_unique($others[0]) as $number) {
            if (!isset($known[$number])) {
                $problems[] = 'The number ' . $number . ' is not in the facts given.';
            }
        }

        return array_values(array_unique($problems));
    }

    /**
     * Write a day's brief: ask the model, check what it writes, and if that has problems, ask it
     * once more, telling it what they are.
     *
     * @param array $input From input()
     * @param callable $complete Asks the model: takes the messages, returns its text (or null)
     * @return array text (the last brief written, or null), and problems (none, if it passed)
     */
    public static function write(array $input, callable $complete): array
    {
        $messages = [
            ['role' => 'system', 'content' => 'You write brief, accurate, neutral accounts of what the Virginia General Assembly did each day, from the facts you are given.'],
            ['role' => 'user', 'content' => self::prompt($input)],
        ];
        $text = null;
        $problems = ['Nothing was written.'];
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $text = $complete($messages);
            if ($text === null) {
                break;
            }
            $text = trim(preg_replace('/\s+/u', ' ', $text));
            $problems = self::check($text, $input);
            if (count($problems) === 0) {
                break;
            }
            $messages[] = ['role' => 'assistant', 'content' => $text];
            $messages[] = ['role' => 'user', 'content' => "That doesn't follow the rules:\n- "
                . implode("\n- ", $problems) . "\nPlease write it again, following the rules, and using only the facts given."];
        }

        return ['text' => $text, 'problems' => $text === null ? ['Nothing was written.'] : $problems];
    }

    /**
     * The fingerprint of the input that a day's stored brief was written from, if it has one.
     */
    public static function stored_hash(string $date): ?string
    {
        return self::rows('SELECT input_hash FROM day_briefs WHERE date = ?', 's', [$date])[0]['input_hash'] ?? null;
    }

    /**
     * Store a day's brief, replacing any that it had. One that has problems is stored, so that
     * it isn't asked for again until the day changes, but it's never shown.
     *
     * @param string $date
     * @param array $written From write()
     * @param string $hash From hash()
     * @return void
     */
    public static function save(string $date, array $written, string $hash): void
    {
        $stmt = mysqli_prepare($GLOBALS['db'], 'INSERT INTO day_briefs
                (date, text, model, input_hash, status, problems, date_created, date_modified)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE text = VALUES(text), model = VALUES(model),
                input_hash = VALUES(input_hash), status = VALUES(status),
                problems = VALUES(problems), date_modified = NOW()');
        $model = self::MODEL;
        $status = count($written['problems']) === 0 ? 'published' : 'rejected';
        $problems = count($written['problems']) > 0 ? implode("\n", $written['problems']) : null;
        $text = (string) $written['text'];
        $stmt->bind_param('ssssss', $date, $text, $model, $hash, $status, $problems);
        $stmt->execute();
    }

    /**
     * A day's brief, for the API: its text, the model that wrote it, and when; or null if it has
     * none that passed its checks.
     *
     * @param string $date
     * @return array|null
     */
    public static function for_api(string $date): ?array
    {
        $row = self::rows('SELECT text, model, date_modified
            FROM day_briefs
            WHERE date = ? AND status = "published"', 's', [$date])[0] ?? null;
        if ($row === null) {
            return null;
        }

        return [
            'text' => $row['text'],
            'model' => $row['model'],
            'written' => date('c', strtotime($row['date_modified'])),
        ];
    }

    /**
     * A brief, as HTML, with each bill that it names linked to its page.
     *
     * @param string $text
     * @param array $day From the API, whose highlights and news say where each bill is
     * @return string
     */
    public static function html(string $text, array $day): string
    {
        $urls = [];
        foreach ($day['highlights'] ?? [] as $highlight) {
            $urls[strtolower($highlight['bill']['number'])] = $highlight['bill']['url'];
        }
        foreach ($day['news'] ?? [] as $story) {
            foreach ($story['bills'] as $bill) {
                $urls[strtolower($bill['number'])] ??= $bill['url'];
            }
        }

        return preg_replace_callback(self::BILL, function ($m) use ($urls) {
            $url = $urls[self::key($m[1], $m[2])] ?? null;
            return $url !== null
                ? '<a href="' . htmlspecialchars(parse_url($url, PHP_URL_PATH)) . '">' . $m[0] . '</a>'
                : $m[0];
        }, htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
    }
}
