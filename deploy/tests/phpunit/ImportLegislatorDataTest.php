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
