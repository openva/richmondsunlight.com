<?php

use PHPUnit\Framework\TestCase;

/**
 * Bill2::latest_impact_statements(), which reduces a bill's impact statements to the most recent
 * one from each agency. The LIS IDs and PDF URLs here are real ones.
 */
class ImpactStatementTest extends TestCase
{
    private static function statement(int $id, string $lis_id, ?string $pdf_url = null, ?string $summary = null): array
    {
        return ['id' => $id, 'lis_id' => $lis_id, 'pdf_url' => $pdf_url, 'summary' => $summary];
    }

    public function testKeepsTheMostRecentStatementAcrossBillVersions(): void
    {
        // SB738 (2025): the Department of Planning and Budget, on four versions of the bill, and
        // a stray older-style row without a PDF.
        $latest = Bill2::latest_impact_statements([
            self::statement(1, 'F122'),
            self::statement(2, 'SB738F122', 'https://lis.blob.core.windows.net/files/1012851.PDF'),
            self::statement(3, 'SB738ERF122', 'https://lis.blob.core.windows.net/files/1069737.PDF'),
            self::statement(4, 'SB738H1F122', 'https://lis.blob.core.windows.net/files/1043153.PDF'),
            self::statement(5, 'SB738S1F122', 'https://lis.blob.core.windows.net/files/1029961.PDF'),
        ]);

        $this->assertCount(1, $latest);
        $this->assertSame('SB738ERF122', $latest[0]['lis_id']);
        $this->assertSame('ER', $latest[0]['version']);
        $this->assertSame('122', $latest[0]['agency_code']);
        $this->assertSame('Department of Planning and Budget', $latest[0]['agency']);
        $this->assertSame('Fiscal Impact Statement (Department of Planning and Budget)', $latest[0]['title']);
    }

    public function testKeepsTheMostRecentStatementFromEachAgency(): void
    {
        // HB217 (2026): the Department of Planning and Budget and the Criminal Sentencing
        // Commission, whose last statement (on substitute H2) came after its one on the enrolled
        // bill. Planning and Budget's statement on the introduced bill was also revised.
        $latest = Bill2::latest_impact_statements([
            self::statement(1, 'HB217F160', 'https://lis.blob.core.windows.net/files/1083210.PDF'),
            self::statement(2, 'HB217F122', 'https://lis.blob.core.windows.net/files/1096308.PDF'),
            self::statement(3, 'HB217ERF122', 'https://lis.blob.core.windows.net/files/1215058.PDF'),
            self::statement(4, 'HB217ERF160', 'https://lis.blob.core.windows.net/files/1215570.PDF'),
            self::statement(5, 'HB217H1F160', 'https://lis.blob.core.windows.net/files/1105654.PDF'),
            self::statement(6, 'HB217H1F122', 'https://lis.blob.core.windows.net/files/1138719.PDF'),
            self::statement(7, 'HB217H2F160', 'https://lis.blob.core.windows.net/files/1220077.PDF'),
            self::statement(8, 'HB217S2F160', 'https://lis.blob.core.windows.net/files/1195986.PDF'),
        ]);

        $this->assertSame(['HB217ERF122', 'HB217H2F160'], array_column($latest, 'lis_id'));
        $this->assertSame(
            ['Department of Planning and Budget', 'Virginia Criminal Sentencing Commission'],
            array_column($latest, 'agency')
        );
    }

    public function testReadsStatementsFromBefore2025ByWhenTheyWereRecorded(): void
    {
        $latest = Bill2::latest_impact_statements([
            self::statement(10, 'F122', null, 'Introduced'),
            self::statement(20, 'FS3122', null, 'Substitute'),
            self::statement(30, 'F160', null, 'Sentencing'),
        ]);

        $this->assertSame(['FS3122', 'F160'], array_column($latest, 'lis_id'));
        $this->assertSame(['S3', ''], array_column($latest, 'version'));
        $this->assertSame('Substitute', $latest[0]['summary']);
    }

    public function testReadsIdsPrefixedWithAPriorSession(): void
    {
        // SB280 (2025), carried over from 2024, whose LIS session ID is 241.
        $latest = Bill2::latest_impact_statements([
            self::statement(42561, '241FS1122'),
            self::statement(136753, '241SB280F122', 'https://lis.blob.core.windows.net/legacy/1001781.PDF'),
            self::statement(136754, '241SB280F160', 'https://lis.blob.core.windows.net/legacy/1001780.PDF'),
            self::statement(136755, '241SB280FS1122', 'https://lis.blob.core.windows.net/legacy/1001783.PDF'),
            self::statement(136756, '241SB280FS1160', 'https://lis.blob.core.windows.net/legacy/1001782.PDF'),
        ]);

        $this->assertSame(['241SB280FS1122', '241SB280FS1160'], array_column($latest, 'lis_id'));
        $this->assertSame(['S1', 'S1'], array_column($latest, 'version'));
        $this->assertSame(['122', '160'], array_column($latest, 'agency_code'));

        // HB9 (2025), with the Commission on Local Government's 2024 code.
        $latest = Bill2::latest_impact_statements([
            self::statement(42365, '241F122'),
            self::statement(136521, '241HB9F164', 'https://lis.blob.core.windows.net/legacy/1001264.PDF'),
        ]);
        $this->assertSame(['241F122', '241HB9F164'], array_column($latest, 'lis_id'));
        $this->assertSame('Local Fiscal Impact Statement (Commission on Local Government)', $latest[1]['title']);
    }

    public function testFindsTheNewestPdfOnAnyHost(): void
    {
        // LIS moved 2025's statements to another host, with the same file numbers.
        $latest = Bill2::latest_impact_statements([
            self::statement(1, 'SB738H1F122', 'https://lisdoc.blob.core.windows.net/filesdev/1043153.PDF'),
            self::statement(2, 'SB738S1F122', 'https://lis.blob.core.windows.net/files/1029961.PDF'),
        ]);

        $this->assertSame('SB738H1F122', $latest[0]['lis_id']);
    }

    public function testNamesEachKindOfStatement(): void
    {
        $latest = Bill2::latest_impact_statements([
            self::statement(1, 'HB127F110', 'https://lis.blob.core.windows.net/files/1.PDF'),
            self::statement(2, 'HB153F195', 'https://lis.blob.core.windows.net/files/2.PDF'),
            self::statement(3, 'SB55F142', 'https://lis.blob.core.windows.net/files/3.PDF'),
        ]);

        $titles = array_column($latest, 'title', 'agency_code');
        $this->assertSame('Racial and Ethnic Impact Statement', $titles['110']);
        $this->assertSame('Local Fiscal Impact Statement (Commission on Local Government)', $titles['195']);
        $this->assertSame('Fiscal Impact Statement', $titles['142']);
    }

    public function testKeepsStatementsWhoseIdsCantBeRead(): void
    {
        $latest = Bill2::latest_impact_statements([
            self::statement(1, 'something odd'),
            self::statement(2, 'another'),
            self::statement(3, 'HB1F122', 'https://lis.blob.core.windows.net/files/1091483.PDF'),
        ]);

        $this->assertCount(3, $latest);
        $this->assertSame('HB1F122', $latest[0]['lis_id']);
    }

    public function testReturnsNothingForNoStatements(): void
    {
        $this->assertSame([], Bill2::latest_impact_statements([]));
    }
}
