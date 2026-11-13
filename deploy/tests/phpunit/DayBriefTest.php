<?php

use PHPUnit\Framework\TestCase;

/**
 * Days' briefs: what the model is told, how what it writes is checked, and how a brief is stored
 * and shown. No model is asked: write() is given a stand-in. The day is January 26, 2026, from
 * deploy/mysql/day-page-fixtures.sql, whose brief is there too.
 */
class DayBriefTest extends TestCase
{
    private const DATE = '2026-01-26';

    private static ?array $day = null;

    protected function setUp(): void
    {
        $database = new Database();
        $database->connect_mysqli();
        if (self::$day === null) {
            DayHighlights::save(self::DATE);
            self::$day = Day::record_for_api(self::DATE);
        }
    }

    private static function input(): array
    {
        return DayBrief::input(self::$day);
    }

    public function testTellsTheModelOnlyTheDaysRecord(): void
    {
        $input = self::input();
        $this->assertSame('Monday, January 26, 2026', $input['date']);
        $hb341 = $input['highlights'][0];
        $this->assertSame('HB 341', $hb341['bill']);
        $this->assertSame('45–52', $hb341['vote']);
        $this->assertContains('Close vote, 45–52', $hb341['reasons']);
        $this->assertContains(['HB 341'], array_column($input['news'], 'bills'));

        $prompt = DayBrief::prompt($input);
        $this->assertStringContainsString('HB 341: Plastic bag tax; distribution to towns.', $prompt);
        $this->assertStringContainsString('What happened, on the House floor: Read third time and defeated by House, 45–52', $prompt);
        $this->assertStringContainsString('Use only the facts below', $prompt);

        // A day with no highlights needs no brief.
        $this->assertNull(DayBrief::input(array_merge(self::$day, ['highlights' => []])));
    }

    public function testAcceptsABriefThatMatchesTheRecord(): void
    {
        $this->assertSame([], DayBrief::check(self::$day['brief']['text'], self::input()));
    }

    public function testRejectsWhatIsntInTheRecord(): void
    {
        $input = self::input();
        $check = fn(string $text) => DayBrief::check($text, $input);

        $this->assertContains('HB 9999 is not one of the bills given.',
            $check('The House defeated HB 341, 45–52. It also passed HB 9999.'));
        $this->assertContains('The tally 52–45 is not one given.',
            $check('The House defeated HB 341, 52–45. The vote was close.'));
        $this->assertContains('The number 14 is not in the facts given.',
            $check('The House defeated HB 341, 45–52. Some 14 bills passed.'));
        $this->assertContains('It must name at least one bill, by number.',
            $check('The House met. The Senate met too.'));
        $this->assertContains('It has 1 sentences; it must have three to five.',
            $check('The House defeated HB 341, 45–52.'));
        $this->assertContains('It must be one paragraph of plain text, with no formatting, lists, or links.',
            $check('The House defeated **HB 341**, 45–52. See https://example.com.'));
        // "HJR 12" and "HJ 12" are the same bill; a tally may be written with a hyphen.
        $this->assertSame([], $check('The House defeated HB 341, 45-52. The vote was close. It was in the news.'));
    }

    public function testAsksAgainOnceIfTheBriefHasProblems(): void
    {
        $good = self::$day['brief']['text'];
        $answers = ['The House defeated HB 341, 50–50. That is all.', $good];
        $asked = [];
        $written = DayBrief::write(self::input(), function (array $messages) use (&$answers, &$asked) {
            $asked[] = $messages;
            return array_shift($answers);
        });
        $this->assertSame($good, $written['text']);
        $this->assertSame([], $written['problems']);
        $this->assertCount(2, $asked);
        $this->assertStringContainsString('The tally 50–50 is not one given.', end($asked[1])['content']);

        // Twice wrong, and it's rejected.
        $written = DayBrief::write(self::input(), fn() => 'The House defeated HB 341, 50–50. That is all.');
        $this->assertNotEmpty($written['problems']);

        // No answer at all.
        $this->assertNull(DayBrief::write(self::input(), fn() => null)['text']);
    }

    public function testStoresBriefsAndShowsOnlyThoseThatPassed(): void
    {
        $date = '2026-01-25';
        DayBrief::save($date, ['text' => 'Rejected.', 'problems' => ['It has 1 sentences.']], 'abc');
        $this->assertSame('abc', DayBrief::stored_hash($date));
        $this->assertNull(DayBrief::for_api($date));

        DayBrief::save($date, ['text' => 'Fine. Really.', 'problems' => []], 'def');
        $this->assertSame('def', DayBrief::stored_hash($date));
        $this->assertSame('Fine. Really.', DayBrief::for_api($date)['text']);
        mysqli_query($GLOBALS['db'], 'DELETE FROM day_briefs WHERE date = "' . $date . '"');

        $this->assertSame(DayBrief::MODEL, self::$day['brief']['model']);
    }

    public function testLinksTheBillsThatABriefNames(): void
    {
        $html = DayBrief::html('The House defeated HB 341 & HB 9999.', self::$day);
        $this->assertSame('The House defeated <a href="/bill/2026/hb341/">HB 341</a> &amp; HB 9999.', $html);
    }
}
