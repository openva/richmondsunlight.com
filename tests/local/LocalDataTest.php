<?php

use PHPUnit\Framework\Attributes\Group;

/**
 * Check that the local sample (from deploy/database_export.sh) is loaded, and that it has the
 * variety of records that the other local tests need in order to be useful. Unlike the other
 * local tests, these fail, rather than being skipped, when the local data isn't loaded.
 */
#[Group('local')]
class LocalDataTest extends LocalTestCase
{
    /**
     * The two most recent regular sessions, which the sample is drawn from.
     */
    private static function recentSessions(): array
    {
        return self::column('SELECT id FROM sessions WHERE suffix IS NULL ORDER BY date_started DESC LIMIT 2');
    }

    protected function requiresLocalData(): bool
    {
        return false;
    }

    public function testLocalDataIsLoaded(): void
    {
        $this->assertGreaterThanOrEqual(
            self::LOCAL_BILL_COUNT,
            (int) self::value('SELECT COUNT(*) FROM bills'),
            'Load the local sample, deploy/mysql/local-test-records.sql; see “Local tests” in README.md'
        );
    }

    public function testRecentSessionsHaveEveryTypeOfBill(): void
    {
        $types = self::column('SELECT DISTINCT LEFT(number, 2) FROM bills
            WHERE session_id IN (' . implode(',', self::recentSessions()) . ')');
        foreach (['hb', 'sb', 'hj', 'sj', 'hr', 'sr'] as $type) {
            $this->assertContains($type, $types, 'No ' . strtoupper($type) . ' bills from the recent sessions');
        }
    }

    public function testRecentSessionsHaveBillsWithManyOutcomes(): void
    {
        $statuses = self::column('SELECT DISTINCT status FROM bills
            WHERE session_id IN (' . implode(',', self::recentSessions()) . ')');
        $this->assertGreaterThanOrEqual(8, count($statuses), 'Bills have only these statuses: ' . implode(', ', $statuses));
    }

    public function testHasUncommonKindsOfBills(): void
    {
        $counts = self::rows('SELECT
                (SELECT COUNT(*) FROM bills WHERE id NOT IN (SELECT bill_id FROM tags)) AS untagged,
                (SELECT COUNT(*) FROM bills WHERE status = "incorporated") AS incorporated,
                (SELECT COUNT(*) FROM (SELECT bill_id FROM bills_full_text GROUP BY bill_id HAVING COUNT(*) >= 3) AS t)
                    AS many_versions,
                (SELECT COUNT(DISTINCT bill_id) FROM votes) AS voted_on,
                (SELECT COUNT(*) FROM representatives_votes) AS legislator_votes,
                (SELECT COUNT(DISTINCT RIGHT(lis_id, 3)) FROM fiscal_impact_statements) AS impact_agencies')[0];

        foreach ($counts as $kind => $count) {
            $this->assertGreaterThan(0, (int) $count, 'The local data has no bills of this kind: ' . $kind);
        }
        $this->assertGreaterThanOrEqual(3, (int) $counts['impact_agencies'], 'Too few agencies’ impact statements');
    }

    public function testHasVideoOfBills(): void
    {
        // Bills' clips are found from where the video index shows each bill on screen.
        $bills = (int) self::value('SELECT COUNT(DISTINCT linked_id) FROM video_index
            WHERE type = "bill" AND linked_id IN (SELECT id FROM bills)
                AND file_id IN (SELECT id FROM files)
                AND file_id IN (SELECT file_id FROM video_transcript)');
        $this->assertGreaterThan(0, $bills, 'No bills in videos that have transcripts');
    }
}
