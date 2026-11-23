<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../SessionSettings.php';

/**
 * Which session the settings should be for, and rewriting them. data/lis_sessions.json is
 * sessions from LIS's GetSessionListAsync, as LIS returned them in November 2026.
 */
class SessionSettingsTest extends TestCase
{
    private const SETTINGS_2026 = [
        'SESSION_ID'         => '32',
        'SESSION_SUFFIX'     => '',
        'SESSION_LIS_ID'     => '261',
        'SESSION_LIS_API_ID' => '59',
        'SESSION_YEAR'       => '2026',
        'SESSION_START'      => '2026-01-14',
        'SESSION_END'        => '2026-03-14',
    ];

    /** sessions.json, as rs-machine published it in November 2026. */
    private const PUBLISHED = [
        ['id' => 32, 'lis_id' => '261', 'lis_api_id' => 59, 'year' => 2026, 'suffix' => null,
            'date_started' => '2026-01-14', 'date_ended' => '2026-03-14', 'crossover' => '2026-02-19'],
        ['id' => 33, 'lis_id' => '262', 'lis_api_id' => 60, 'year' => 2026, 'suffix' => '1',
            'date_started' => '2026-04-23', 'date_ended' => null, 'crossover' => null],
        ['id' => 34, 'lis_id' => '271', 'lis_api_id' => 61, 'year' => 2027, 'suffix' => null,
            'date_started' => '2027-01-13', 'date_ended' => null, 'crossover' => null],
    ];

    private array $lis;

    protected function setUp(): void
    {
        $this->lis = json_decode(
            file_get_contents(__DIR__ . '/data/lis_sessions.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        )['Sessions'];
    }

    public function testTheNewSessionIsSwitchedTo(): void
    {
        $proposal = $this->propose('2026-11-22');

        $this->assertTrue($proposal['switch']);
        $this->assertSame('2027 Regular Session', $proposal['session']);
        // 34, not 33, which is the special session's.
        $this->assertSame([
            'SESSION_ID'         => '34',
            'SESSION_SUFFIX'     => '',
            'SESSION_LIS_ID'     => '271',
            'SESSION_LIS_API_ID' => '61',
            'SESSION_YEAR'       => '2027',
            'SESSION_START'      => '2027-01-13',
            'SESSION_END'        => '2027-02-27',
        ], $proposal['values']);
        $this->assertStringContainsString('projected', implode(' ', $proposal['notes']));
    }

    public function testTheNewSessionIsNotSwitchedToBeforeNovember(): void
    {
        // 2027's prefiling period began July 20, 2026, but it isn't switched to before November.
        $proposal = $this->propose('2026-08-01');

        $this->assertFalse($proposal['switch']);
        $this->assertSame(self::SETTINGS_2026, $proposal['values']);
        $this->assertSame(['The 2027 Regular Session will be ready to switch to on 2026-11-01.'], $proposal['notes']);
    }

    public function testASessionNotYetInTheDatabaseIsWaitedFor(): void
    {
        $proposal = $this->propose('2026-11-22', published: array_slice(self::PUBLISHED, 0, 2));

        $this->assertFalse($proposal['switch']);
        $this->assertSame(self::SETTINGS_2026, $proposal['values']);
        $this->assertStringContainsString('isn’t in sessions.json', $proposal['notes'][0]);
    }

    public function testASessionWithoutBillsIsWaitedFor(): void
    {
        $proposal = $this->propose('2026-11-22', hasBills: false);

        $this->assertFalse($proposal['switch']);
        $this->assertStringContainsString('hasn’t published bills', $proposal['notes'][0]);
    }

    public function testAnAdjournmentInTheDatabaseIsUsed(): void
    {
        $published = self::PUBLISHED;
        $published[2]['date_ended'] = '2027-03-01';

        $proposal = $this->propose('2026-11-22', published: $published);

        $this->assertSame('2027-03-01', $proposal['values']['SESSION_END']);
        $this->assertSame([], $proposal['notes']);
    }

    /**
     * In May, with the special session underway, which isn't switched to.
     */
    public function testTheCurrentSessionIsKeptUpToDate(): void
    {
        $settings = array_merge(self::SETTINGS_2026, ['SESSION_END' => '2026-03-07', 'SESSION_LIS_API_ID' => '']);

        $proposal = $this->propose('2026-05-01', $settings);

        $this->assertFalse($proposal['switch']);
        $this->assertSame(self::SETTINGS_2026, $proposal['values']);
    }

    public function testAnUnknownEndIsKeptRatherThanProjected(): void
    {
        $settings = [
            'SESSION_ID'         => '34',
            'SESSION_SUFFIX'     => '',
            'SESSION_LIS_ID'     => '271',
            'SESSION_LIS_API_ID' => '61',
            'SESSION_YEAR'       => '2027',
            'SESSION_START'      => '2027-01-13',
            'SESSION_END'        => '2027-02-26',
        ];

        $this->assertSame($settings, $this->propose('2026-12-01', $settings)['values']);
    }

    public function testProjectedEnds(): void
    {
        $this->assertSame('2027-02-27', SessionSettings::projectedEnd('2027-01-13'));
        $this->assertSame('2026-03-14', SessionSettings::projectedEnd('2026-01-14'));
        $this->assertSame('2025-02-22', SessionSettings::projectedEnd('2025-01-08'));
    }

    public function testTheSettingsFileIsRewritten(): void
    {
        $file = file_get_contents(__DIR__ . '/../../../htdocs/includes/settings-default.inc.php');
        $current = SessionSettings::read($file);
        $this->assertSame(array_keys(SessionSettings::SETTINGS), array_keys($current));

        $rewritten = SessionSettings::write($file, self::SETTINGS_2026);

        $this->assertSame(self::SETTINGS_2026, SessionSettings::read($rewritten));
        $this->assertStringContainsString("rs_define('SESSION_ID', 32);", $rewritten);
        $this->assertStringContainsString("rs_define('SESSION_SUFFIX', '');", $rewritten);
        $this->assertStringContainsString("rs_define('SESSION_LIS_ID', '261');", $rewritten);
        // Nothing else changes.
        $this->assertSame($file, SessionSettings::write($rewritten, $current));
    }

    public function testASettingThatIsMissingIsAnError(): void
    {
        $this->expectException(UnexpectedValueException::class);
        SessionSettings::read("<?php\nrs_define('SESSION_ID', 32);\n");
    }

    public function testAValueThatIsntOneIsAnError(): void
    {
        $file = file_get_contents(__DIR__ . '/../../../htdocs/includes/settings-default.inc.php');
        $this->expectException(InvalidArgumentException::class);
        SessionSettings::write($file, array_merge(self::SETTINGS_2026, ['SESSION_ID' => "32); evil('"]));
    }

    private function propose(
        string $today,
        array $settings = self::SETTINGS_2026,
        array $published = self::PUBLISHED,
        bool $hasBills = true
    ): array {
        return SessionSettings::propose(
            $settings,
            $this->lis,
            $published,
            static fn(string $code): bool => $hasBills,
            new DateTimeImmutable($today)
        );
    }
}
