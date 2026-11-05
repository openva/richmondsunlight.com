<?php

use PHPUnit\Framework\TestCase;

/**
 * Bill2::progress(), which fills in the bill page's Progress box. The histories here are real
 * ones, abridged, except the overridden veto.
 */
class BillProgressTest extends TestCase
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
        $this->assertSame('passed', Bill2::progress([
            self::entry('2026-02-11', 'passed committee'),   // Reported from Finance and Appropriations
            self::entry('2026-02-11', 'failed committee'),   // Continued to 2027 in Finance and Appropriations
            self::entry('2026-02-09', 'passed committee'),   // Reported from Commerce and Labor
            self::entry('2026-01-13', 'in committee'),
        ])['committee']);
    }

    public function testAFailureAfterBeingReportedIsAFailure(): void
    {
        $this->assertSame('failed', Bill2::progress([
            self::entry('2026-02-17', 'failed committee'),   // Left in Appropriations
            self::entry('2026-02-06', 'passed committee'),   // Reported from Public Safety
        ])['committee']);
    }

    public function testAFailureInSubcommitteeIsAFailure(): void
    {
        $this->assertSame('failed', Bill2::progress([
            self::entry('2026-02-03', 'failed subcommittee'),
            self::entry('2026-01-20', 'in subcommittee'),
        ])['committee']);
    }

    /**
     * HJ1 (2025): continued from 2024, then reported in 2025.
     */
    public function testABillReportedAfterBeingContinuedPassed(): void
    {
        $this->assertSame('passed', Bill2::progress([
            self::entry('2025-01-14', 'passed committee'),
            self::entry('2024-02-20', 'failed committee'),
        ])['committee']);
    }

    public function testABillNoCommitteeHasActedOnHasNoOutcome(): void
    {
        $this->assertNull(Bill2::progress([
            self::entry('2026-01-13', 'in committee'),
            self::entry('2026-01-13', 'introduced'),
            self::entry('2026-01-13', null),
        ])['committee']);
    }

    public function testRowsMayBeObjects(): void
    {
        $this->assertSame('passed', Bill2::progress([(object) self::entry('2026-02-11', 'passed committee')])['committee']);
    }

    /**
     * SB382 (2026), signed by the governor. LIS never records "Enacted" for a bill like this.
     */
    public function testASignedBillBecameLaw(): void
    {
        $this->assertSame('passed', Bill2::progress([
            self::entry('2026-04-13', null),                  // Acts of Assembly Chapter text (CHAP0634)
            self::entry('2026-04-13', 'signed by governor'),  // Approved by Governor-Chapter 634
            self::entry('2026-03-30', 'passed'),              // Signed by President
        ])['law']);
    }

    /**
     * HB1642 (2025), whose amendments from the governor were adopted.
     */
    public function testAnEnactedBillBecameLaw(): void
    {
        $this->assertSame('passed', Bill2::progress([
            self::entry('2025-04-02', 'enacted'),             // Enacted, Chapter 637
            self::entry('2025-04-02', 'signed by governor'),  // Governor's recommendation adopted
        ])['law']);
    }

    public function testAVetoedBillDidNot(): void
    {
        $this->assertSame('failed', Bill2::progress([
            self::entry('2026-04-13', 'vetoed by governor'),
            self::entry('2026-03-30', 'passed'),
        ])['law']);
    }

    public function testAnOverriddenVetoBecameLaw(): void
    {
        $this->assertSame('passed', Bill2::progress([
            self::entry('2026-04-22', 'enacted'),             // House and Senate: veto overridden
            self::entry('2026-04-13', 'vetoed by governor'),
        ])['law']);
    }

    public function testABillAwaitingTheGovernorHasNoOutcome(): void
    {
        $this->assertNull(Bill2::progress([
            self::entry('2026-03-30', 'passed'),
            self::entry('2026-03-04', 'passed committee'),
        ])['law']);
    }

    public function testEveryStepOfASignedBill(): void
    {
        $this->assertSame(
            ['committee' => 'passed', 'house' => 'passed', 'senate' => 'passed', 'governor' => 'passed', 'law' => 'passed'],
            Bill2::progress([
                self::entry('2026-04-13', 'signed by governor'),
                self::entry('2026-03-30', 'passed'),
                self::entry('2026-03-04', 'passed house'),
                self::entry('2026-02-27', 'passed committee'),
                self::entry('2026-02-16', 'passed senate'),
                self::entry('2026-02-11', 'passed committee'),
                self::entry('2026-02-11', 'failed committee'),
                self::entry('2026-01-13', 'introduced'),
            ])
        );
    }

    public function testABillThatDiedInTheSecondChamber(): void
    {
        $this->assertSame(
            ['committee' => 'failed', 'house' => null, 'senate' => 'passed', 'governor' => null, 'law' => null],
            Bill2::progress([
                self::entry('2026-02-24', 'failed committee'),     // Left in Appropriations
                self::entry('2026-02-16', 'passed senate'),
                self::entry('2026-02-09', 'passed committee'),
            ])
        );
    }

    public function testAVetoedBillWasNotSigned(): void
    {
        $progress = Bill2::progress([
            self::entry('2026-04-22', 'enacted'),             // veto overridden
            self::entry('2026-04-13', 'vetoed by governor'),
        ]);

        $this->assertSame('failed', $progress['governor']);
        $this->assertSame('passed', $progress['law']);
    }

    public function testAnEmptyHistory(): void
    {
        $this->assertSame(
            ['committee' => null, 'house' => null, 'senate' => null, 'governor' => null, 'law' => null],
            Bill2::progress([])
        );
    }
}
