<?php

use PHPUnit\Framework\TestCase;

/**
 * Delegates' preferred first names, which LIS gives only sometimes (in quotes, in
 * MemberDisplayName), and the House's member pages give otherwise.
 */
class ImportPreferredNameTest extends TestCase
{
    /** A delegate whose preferred name LIS doesn't give. */
    private const MEMBER = [
        'MemberNumber' => 'H0402',
        'ChamberCode' => 'H',
        'MemberDisplayName' => 'Charles H. Schmidt, Jr.',
        'ListDisplayName' => 'Schmidt, Charles H.',
        'PartyCode' => 'R',
        'DistrictName' => '77th',
    ];

    private const MEMBER_PAGE = <<<HTML
        <img alt="Delegate Charles H. Schmidt, Jr." src="/delegate_photos/H0402.jpg" />
        <p class="mb-2 libre-baskerville-bold text-hodBlue-200">Preferred Name: Charlie</p>
        HTML;

    /**
     * @param string|false $page What the House's website returns.
     * @param array<string,mixed> $member
     */
    private function import($page, array $member = self::MEMBER): Import
    {
        return new class (new class extends Log {
            public function put($message, $level = 3)
            {
                return true;
            }
        }, $page, $member) extends Import {
            public array $requested = [];

            public function __construct(Log $log, private $page, private array $member)
            {
                parent::__construct($log);
            }

            protected function http_get($url)
            {
                $this->requested[] = $url;
                return $this->page;
            }

            protected function lis_api_request($path, array $query = [])
            {
                if ($path === '/Member/api/getmembersasync' || $path === '/Member/api/getactivemembersasync') {
                    return ['Members' => [$this->member]];
                }
                return [];
            }
        };
    }

    public function testUsesThePreferredNameFromTheMembersPage(): void
    {
        $import = $this->import(self::MEMBER_PAGE);
        $data = $import->fetch_legislator_data_api('house', 'H0402');

        $this->assertSame(['https://house.vga.virginia.gov/members/H0402'], $import->requested);
        $this->assertSame('Schmidt, Charlie', $data['name']);
        $this->assertStringStartsWith('Del. Charlie Schmidt (R', $data['name_formatted']);
    }

    public function testReadsPreferredNamesThatArentOneWord(): void
    {
        $page = str_replace('Charlie', 'Mary-Ellen', self::MEMBER_PAGE);
        $data = $this->import($page)->fetch_legislator_data('house', 'H0402');

        $this->assertSame('Mary-Ellen', $data['nickname']);
    }

    /**
     * A redirect to the list of all members has no preferred name of theirs.
     */
    public function testIgnoresAPageThatIsntTheMembers(): void
    {
        $page = str_replace('H0402', 'H0056', self::MEMBER_PAGE);
        $data = $this->import($page)->fetch_legislator_data_api('house', 'H0402');

        $this->assertSame('Schmidt, Charles', $data['name']);
    }

    public function testFallsBackToTheListedName(): void
    {
        $data = $this->import(false)->fetch_legislator_data_api('house', 'H0402');

        $this->assertSame('Schmidt, Charles', $data['name']);
    }

    /**
     * A name LIS gives in quotes needs no request.
     */
    public function testPrefersTheNameLisGives(): void
    {
        $member = ['MemberDisplayName' => 'Charles H. "Chuck" Schmidt, Jr.'] + self::MEMBER;
        $import = $this->import(self::MEMBER_PAGE, $member);
        $data = $import->fetch_legislator_data_api('house', 'H0402');

        $this->assertSame([], $import->requested);
        $this->assertSame('Schmidt, Chuck', $data['name']);
    }

    /**
     * The roster is fetched daily, and needs only names and IDs: not a request per delegate.
     */
    public function testTheRosterMakesNoRequestsForPages(): void
    {
        $import = $this->import(self::MEMBER_PAGE);
        $roster = $import->fetch_active_members('house');

        $this->assertCount(1, $roster);
        $this->assertSame([], $import->requested);
    }
}
