<?php

use PHPUnit\Framework\TestCase;

/**
 * Bill2::committee_outcome(), which says whether a bill passed committee, for the bill page's
 * Progress box. The histories here are real ones, abridged.
 */
class CommitteeOutcomeTest extends TestCase
{
    private static function entry(string $date, ?string $translation): array
    {
        return ['date_raw' => $date, 'translation' => $translation];
    }

    /**
     * SB382 (2026): LIS recorded a motion to continue the bill that failed, then the vote to
     * report it. The history is newest first, as Bill2 retrieves it.
     */
    public function testAFailedMotionOnTheDayItWasReportedIsNotAFailure(): void
    {
        $this->assertSame('passed', Bill2::committee_outcome([
            self::entry('2026-02-11', 'passed committee'),   // Reported from Finance and Appropriations
            self::entry('2026-02-11', 'failed committee'),   // Continued to 2027 in Finance and Appropriations
            self::entry('2026-02-09', 'passed committee'),   // Reported from Commerce and Labor
            self::entry('2026-01-13', 'in committee'),
        ]));
    }

    public function testAFailureAfterBeingReportedIsAFailure(): void
    {
        $this->assertSame('failed', Bill2::committee_outcome([
            self::entry('2026-02-17', 'failed committee'),   // Left in Appropriations
            self::entry('2026-02-06', 'passed committee'),   // Reported from Public Safety
        ]));
    }

    public function testAFailureInSubcommitteeIsAFailure(): void
    {
        $this->assertSame('failed', Bill2::committee_outcome([
            self::entry('2026-02-03', 'failed subcommittee'),
            self::entry('2026-01-20', 'in subcommittee'),
        ]));
    }

    /**
     * HJ1 (2025): continued from 2024, then reported in 2025.
     */
    public function testABillReportedAfterBeingContinuedPassed(): void
    {
        $this->assertSame('passed', Bill2::committee_outcome([
            self::entry('2025-01-14', 'passed committee'),
            self::entry('2024-02-20', 'failed committee'),
        ]));
    }

    public function testABillNoCommitteeHasActedOnHasNoOutcome(): void
    {
        $this->assertNull(Bill2::committee_outcome([
            self::entry('2026-01-13', 'in committee'),
            self::entry('2026-01-13', 'introduced'),
            self::entry('2026-01-13', null),
        ]));
    }

    public function testRowsMayBeObjects(): void
    {
        $this->assertSame('passed', Bill2::committee_outcome([(object) self::entry('2026-02-11', 'passed committee')]));
    }
}
