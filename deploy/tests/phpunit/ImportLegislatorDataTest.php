<?php

use PHPUnit\Framework\TestCase;

/**
 * Import::fetch_legislator_data() for delegates, from the House's member pages, and
 * Import::update_legislator()'s handling of data that a scrape failed to find.
 */
class ImportLegislatorDataTest extends TestCase
{
    /**
     * The parts of https://house.vga.virginia.gov/members/H0402 that are parsed.
     */
    private const MEMBER_PAGE = <<<HTML
        <img alt="Delegate Charles H. Schmidt, Jr." src="/delegate_photos/H0402.jpg" />
        <p class="mb-2 libre-baskerville-bold">
            77th District
          </p>
        <p class="mb-2 libre-baskerville-bold text-hodBlue-200 text-hodBlue-70 tracking-wide">Preferred Name: Charlie</p>
        <p class="mb-2 libre-baskerville-regular text-hodBlue-200 tracking-normal text-hodBlue-70 ">Member Since: January 17, 2026 </p>
        <p class="text-base leading-relaxed text-hodBlue-700">
          <span class="font-semibold text-hodBlue-400">Gender:</span>
          Male
        </p>
        <p class="text-base leading-relaxed text-hodBlue-700">
          <span class="font-semibold text-hodBlue-400">Race(s)/Ethnicity:</span>
          Caucasian
        </p>
        HTML;

    private function importReturning($html): Import
    {
        return new class (new Log(), $html) extends Import {
            public array $requested = [];

            public function __construct(Log $log, private $html)
            {
                parent::__construct($log);
            }

            protected function http_get($url)
            {
                $this->requested[] = $url;
                return $this->html;
            }
        };
    }

    public function testParsesAMemberPage(): void
    {
        $import = $this->importReturning(self::MEMBER_PAGE);
        $data = @$import->fetch_legislator_data('house', '402');

        $this->assertSame(['https://house.vga.virginia.gov/members/H0402'], $import->requested);
        $this->assertSame('house', $data['chamber']);
        $this->assertSame('402', $data['lis_id']);
        $this->assertSame('Charlie', $data['nickname']);
        $this->assertSame('2026-01-17', $data['date_started']);
        $this->assertSame('male', $data['sex']);
        $this->assertSame('white', $data['race']);
        $this->assertSame('https://house.vga.virginia.gov/delegate_photos/H0402.jpg', $data['photo_url']);
    }

    /**
     * The House's old member pages redirect to the list of all members, which was parsed as
     * though it were the member's page.
     */
    public function testRejectsAPageThatIsntTheMembers(): void
    {
        $page = str_replace('H0402', 'H0056', self::MEMBER_PAGE);
        $this->assertFalse($this->importReturning($page)->fetch_legislator_data('house', 'H0402'));
    }

    public function testRejectsAFailedRequest(): void
    {
        $this->assertFalse($this->importReturning(false)->fetch_legislator_data('house', 'H0402'));
    }

    /**
     * A year alone isn't a date: strtotime() reads "2024" as 20:24 today.
     */
    public function testIgnoresAYearWithoutADate(): void
    {
        $page = str_replace('January 17, 2026', '2024', self::MEMBER_PAGE);
        $data = @$this->importReturning($page)->fetch_legislator_data('house', 'H0402');

        $this->assertArrayNotHasKey('date_started', $data);
    }

    /**
     * The parts of https://apps.senate.virginia.gov/Senator/memberpage.php?id=S99 that are parsed.
     */
    private const SENATE_MEMBER_PAGE = <<<HTML
        <h1 class="display-4 fw-bold mb-4">Virginia Senate Members</h1>
        <img src="https://apps.senate.virginia.gov/Senator/images/member_photos/Sturtevant12" class="profile_pic img-fluid" alt="Photo of Senator Glen H. Sturtevant, Jr.">
        <h2 class="section-title">Glen H. &quot;Tex&quot; Sturtevant, Jr.</h2>
        <h4>Biography</h4>
        <div class="lrgblacktext">
            Born in Wilmington, Delaware on September 13, 1982; Education: Catholic University, B.A.;
            Occupation: Attorney; Member of the Senate: 2016-2020, 2024-
        </div>
        HTML;

    /**
     * What a malformed ID gets: the list of all senators.
     */
    private const SENATE_LIST_PAGE = <<<HTML
        <img src="https://apps.senate.virginia.gov/Senator/images/member_photos/Aird13" alt="">
        <a href="//apps.senate.virginia.gov/Senator/memberpage.php?id=S99 ">Sturtevant</a>
        <img src="https://apps.senate.virginia.gov/Senator/images/member_photos/Sturtevant12" alt="">
        HTML;

    public function testParsesASenateMemberPage(): void
    {
        $import = $this->importReturning(self::SENATE_MEMBER_PAGE);
        $data = $import->fetch_legislator_data('senate', 'S0099');

        // The Senate's site needs the ID in the form S99.
        $this->assertSame(['https://apps.senate.virginia.gov/Senator/memberpage.php?id=S99'], $import->requested);
        $this->assertSame('senate', $data['chamber']);
        $this->assertSame('99', $data['lis_id']);
        $this->assertSame('Tex', $data['nickname']);
        $this->assertSame(
            'https://apps.senate.virginia.gov/Senator/images/member_photos/Sturtevant12',
            $data['photo_url']
        );
        $this->assertStringStartsWith('Born in Wilmington, Delaware', $data['bio']);
        $this->assertStringEndsWith('Member of the Senate: 2016-2020, 2024-', $data['bio']);
        $this->assertArrayNotHasKey('name', $data, 'The page’s name isn’t in "Last, First" form.');
    }

    /**
     * A malformed ID redirects to the list of all senators, whose first photo was taken to be
     * every senator's.
     */
    public function testRejectsTheListOfSenators(): void
    {
        $this->assertFalse($this->importReturning(self::SENATE_LIST_PAGE)->fetch_legislator_data('senate', '99'));
    }

    public function testRejectsAFailedSenateRequest(): void
    {
        $this->assertFalse($this->importReturning(false)->fetch_legislator_data('senate', '99'));
    }

    public function testOmitsWhatIsntOnThePage(): void
    {
        $page = preg_replace('/<p[^>]*>\s*<span[^>]*>(Gender|Race)[\s\S]+?<\/p>/', '', self::MEMBER_PAGE);
        $data = @$this->importReturning($page)->fetch_legislator_data('house', 'H0402');

        $this->assertArrayNotHasKey('sex', $data);
        $this->assertArrayNotHasKey('race', $data);
        $this->assertArrayNotHasKey('url', $data);
    }

    public function testOmitsRacesThatCantBeStored(): void
    {
        $page = str_replace('Caucasian', 'Multiracial', self::MEMBER_PAGE);
        $data = @$this->importReturning($page)->fetch_legislator_data('house', 'H0402');

        $this->assertArrayNotHasKey('race', $data);
    }

    /**
     * A blank value means that a scrape didn't find it, not that it should be erased.
     */
    public function testUpdateLegislatorKeepsWhatIsntProvided(): void
    {
        $database = new Database();
        $dbh = @$database->connect();
        if (!($dbh instanceof PDO)) {
            $this->markTestSkipped('Database not available');
        }
        $GLOBALS['dbh'] = $dbh;

        $term = $dbh->query('SELECT t.id, t.url, t.email, p.race, p.sex
            FROM terms t JOIN people p ON p.id = t.person_id
            WHERE t.url IS NOT NULL AND t.url <> "" AND p.race IS NOT NULL AND p.sex IS NOT NULL
            LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        if ($term === false) {
            $this->markTestSkipped('No legislator has a URL, race, and sex');
        }

        $dbh->beginTransaction();
        try {
            $import = new Import(new Log());
            $import->update_legislator([
                'id'             => $term['id'],
                'name_formatted' => 'Test',
                'url'            => '',
                'race'           => '',
                'sex'            => null,
                'email'          => 'changed@example.com',
            ]);

            $after = $dbh->query('SELECT t.url, t.email, p.race, p.sex
                FROM terms t JOIN people p ON p.id = t.person_id
                WHERE t.id = ' . (int)$term['id'])->fetch(PDO::FETCH_ASSOC);
        } finally {
            $dbh->rollBack();
        }

        $this->assertSame($term['url'], $after['url']);
        $this->assertSame($term['race'], $after['race']);
        $this->assertSame($term['sex'], $after['sex']);
        $this->assertSame('changed@example.com', $after['email']);
    }
}
