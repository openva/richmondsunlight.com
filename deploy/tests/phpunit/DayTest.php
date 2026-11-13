<?php

use PHPUnit\Framework\TestCase;

/**
 * Day's reading of bill actions (LIS's text, which says what was done and by whom, but not always
 * which committee), and its matching of LIS's committee names to ours. The actions here are from
 * production.
 */
class DayTest extends TestCase
{
    private static function names_match(string $a, string $b): bool
    {
        $method = new ReflectionMethod('Day', 'names_match');
        return $method->invoke(null, $a, $b);
    }

    public function testRecognizesDates(): void
    {
        $this->assertTrue(Day::is_date('2026-01-26'));
        $this->assertFalse(Day::is_date('2026-02-30'));
        $this->assertFalse(Day::is_date('2026-1-26'));
        $this->assertFalse(Day::is_date('latest'));
    }

    public function testFindsTheCommitteeThatReportedABill(): void
    {
        $action = Day::classify('Reported from Courts of Justice with substitute and rereferred to Finance and Appropriations (9-Y 5-N)');
        $this->assertSame('committee', $action['type']);
        $this->assertSame('advanced', $action['outcome']);
        $this->assertSame('Courts of Justice', $action['committee']);
        $this->assertSame('Reported from Courts of Justice with substitute and rereferred to Finance and Appropriations', $action['text']);
        $this->assertSame(['yes' => 9, 'no' => 5, 'abstaining' => 0, 'voice' => false], $action['vote']);

        // "and" can be part of a committee's name.
        $this->assertSame('Commerce and Labor', Day::classify('Reported from Commerce and Labor (10-Y 4-N)')['committee']);
    }

    public function testFindsTheCommitteeThatStoppedABill(): void
    {
        foreach ([
            'Passed by indefinitely in Courts of Justice (9-Y 5-N)' => 'Courts of Justice',
            'Continued to 2027 in Rules by voice vote' => 'Rules',
            'Left in Appropriations' => 'Appropriations',
            'Failed to report (defeated) in Privileges and Elections (7-Y 8-N)' => 'Privileges and Elections',
            'Stricken at request of Patron in General Laws (22-Y 0-N)' => 'General Laws',
        ] as $status => $committee) {
            $action = Day::classify($status);
            $this->assertSame('stopped', $action['outcome'], $status);
            $this->assertSame($committee, $action['committee'], $status);
        }
        $this->assertTrue(Day::classify('Continued to 2027 in Rules by voice vote')['vote']['voice']);
        $this->assertSame('Continued to 2027 in Rules', Day::classify('Continued to 2027 in Rules by voice vote')['text']);
    }

    public function testReadsSubcommitteeRecommendations(): void
    {
        $this->assertSame('advanced', Day::classify('Subcommittee recommends reporting with substitute (6-Y 2-N)')['outcome']);
        $this->assertSame('stopped', Day::classify('Subcommittee recommends laying on the table (5-Y 3-N)')['outcome']);
        $this->assertSame('stopped', Day::classify('Subcommittee recommends continuing to 2027')['outcome']);
        $this->assertSame('incorporated', Day::classify('Subcommittee recommends incorporating into HB1234')['outcome']);
        $this->assertSame('subcommittee', Day::classify('Subcommittee recommends reporting (6-Y 2-N)')['type']);
        $this->assertNull(Day::classify('Subcommittee recommends reporting (6-Y 2-N)')['committee']);
    }

    public function testReadsFloorActions(): void
    {
        $this->assertSame('passed', Day::classify('Read third time and passed House (97-Y 0-N 0-A)')['type']);
        $this->assertSame(1, Day::classify('Agreed to by House (62-Y 33-N 1-A)')['vote']['abstaining']);
        $this->assertSame('passed', Day::classify('Passed Senate with substitute (21-Y 19-N)')['type']);
        $this->assertSame('failed', Day::classify('Read third time and defeated by House (45-Y 52-N 0-A)')['type']);
        $this->assertSame('reading', Day::classify('Read second time and engrossed')['type']);
        $this->assertSame('procedural', Day::classify('Passed by for the day')['type']);
        $this->assertSame('procedural', Day::classify('Rules suspended (40-Y 0-N 0-A)')['type']);
        $this->assertSame('governor', Day::classify('House concurred in Governor\'s recommendation (96-Y 0-N)')['type']);
        $this->assertSame('conference', Day::classify('Conferees appointed by Senate')['type']);
        $this->assertSame('conference', Day::classify('Senators: Lucas, Deeds, Locke, McDougle, Pillion')['type']);
        $this->assertSame('referred', Day::classify('Assigned HGL sub: ABC/Gaming')['type']);
        $this->assertSame('amendments', Day::classify('Courts of Justice Substitute agreed to')['type']);
    }

    public function testLeavesOutPaperwork(): void
    {
        foreach ([
            'Fiscal Impact Statement from Department of Planning and Budget (HB95)',
            'Senate committee offered',
            'House subcommittee offered',
            'Placed on Courts of Justice Agenda',
            'Bill text as passed House and Senate (HB1)',
            'Signed by Speaker',
            'Enrolled',
            'Governor\'s Action Deadline 11:59 p.m., April 13, 2026',
            'Presented and laid on Speaker\'s table 25105978D',
        ] as $status) {
            $this->assertSame('paperwork', Day::classify($status)['type'], $status);
        }
    }

    public function testKnowsACloseVote(): void
    {
        $vote = fn(int $yes, int $no) => ['yes' => $yes, 'no' => $no, 'abstaining' => 0, 'voice' => false];
        $this->assertTrue(Day::is_close($vote(8, 6)));
        $this->assertTrue(Day::is_close($vote(51, 47)));
        $this->assertTrue(Day::is_close($vote(45, 52)));
        $this->assertFalse(Day::is_close($vote(9, 5)));
        $this->assertFalse(Day::is_close($vote(97, 0)));
        $this->assertFalse(Day::is_close(['yes' => null, 'no' => null, 'abstaining' => null, 'voice' => true]));
        $this->assertFalse(Day::is_close(null));
    }

    public function testMatchesLisCommitteeNamesToOurs(): void
    {
        $this->assertTrue(self::names_match('Finance', 'Finance and Appropriations'));
        $this->assertTrue(self::names_match('Militia, Police and Public Safety', 'Public Safety'));
        $this->assertTrue(self::names_match('#1 Criminal', 'Criminal'));
        $this->assertTrue(self::names_match('Health & Human Resources', 'Health and Human Resources Subcommittee'));
        $this->assertFalse(self::names_match('Finance', 'Transportation'));
        $this->assertFalse(self::names_match('Education', 'Higher Education and Arts'));
    }

    public function testLinksBillNumbersInMinutes(): void
    {
        $html = Day::minutes_html("HB 1384 - Passed\nHJR 14 and SB 12 & more", 2026);
        $this->assertStringContainsString('<a href="/bill/2026/hb1384/">HB 1384</a>', $html);
        $this->assertStringContainsString('<a href="/bill/2026/hj14/">HJR 14</a>', $html);
        $this->assertStringContainsString('<a href="/bill/2026/sb12/">SB 12</a> &amp; more', $html);
    }
}
