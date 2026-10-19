<?php

use PHPUnit\Framework\Attributes\Group;

/**
 * Bill2::latest_impact_statements(), on every bill's impact statements in the local database.
 */
#[Group('local')]
class ImpactStatementSweepTest extends LocalTestCase
{
    public function testEveryStatementIsFromAKnownAgency(): void
    {
        $bills = [];
        foreach (self::rows('SELECT id, bill_id, lis_id, pdf_url, summary FROM fiscal_impact_statements') as $row) {
            $bills[$row['bill_id']][] = $row;
        }

        $problems = [];
        foreach ($bills as $bill_id => $statements) {
            $latest = Bill2::latest_impact_statements($statements);
            $agencies = [];
            foreach ($latest as $statement) {
                $label = 'Bill ' . $bill_id . '’s ' . $statement['lis_id'];
                if (empty($statement['agency_code'])) {
                    $problems[] = $label . ' isn’t a recognizable statement ID';
                    continue;
                }
                if (!isset(Bill2::IMPACT_STATEMENT_AGENCIES[$statement['agency_code']])) {
                    $problems[] = $label . ' is from an unknown agency, ' . $statement['agency_code'];
                }
                if (isset($agencies[$statement['agency_code']])) {
                    $problems[] = $label . ' is a second statement from agency ' . $statement['agency_code'];
                }
                $agencies[$statement['agency_code']] = true;
            }
        }

        $this->assertNoProblems($problems, count($bills), 'bills’ impact statements');
    }
}
