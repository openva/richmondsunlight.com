<?php

use PHPUnit\Framework\TestCase;

/**
 * Portfolio, which keeps Photosynthesis users' portfolios: each change is made only to the user's
 * own. These run against the test users in deploy/mysql/test-users.sql: 90001 (whose portfolios
 * are pwt01 and pwtp1, each with HB2049) and 90004 (whose portfolio is pwt04). Portfolios that the
 * tests make are deleted afterward.
 */
class PortfolioTest extends TestCase
{
    private const USER = 90001;
    private const OTHER_USER = 90004;

    /** Users made by a test, deleted afterward. */
    private array $users = [];

    /** The hashes of portfolios made by a test. */
    private array $made = [];

    protected function setUp(): void
    {
        $database = new Database();
        if ($database->connect_mysqli() === false) {
            $this->markTestSkipped('No database.');
        }
        $result = mysqli_execute_query($GLOBALS['db'], 'SELECT COUNT(*) FROM dashboard_portfolios WHERE hash IN ("pwt01", "pwt04")');
        if ((int) mysqli_fetch_row($result)[0] != 2) {
            $this->markTestSkipped('The test users’ portfolios aren’t loaded (deploy/mysql/test-users.sql).');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->made as [$hash, $user_id]) {
            Portfolio::delete($hash, $user_id);
        }
        foreach ($this->users as $user_id) {
            Portfolio::delete_user($user_id);
            mysqli_execute_query($GLOBALS['db'], 'DELETE FROM users WHERE id = ?', [$user_id]);
        }
    }

    private function make(int $user_id, array $fields, ?array $criteria = null): array
    {
        $portfolio = Portfolio::create($user_id, $fields, $criteria);
        $this->assertNotNull($portfolio);
        $this->made[] = [$portfolio['hash'], $user_id];
        return $portfolio;
    }

    private function rows(string $sql, array $params): int
    {
        return (int) mysqli_fetch_row(mysqli_execute_query($GLOBALS['db'], $sql, $params))[0];
    }

    public function testCreatingGivesAUniqueFiveCharacterHash(): void
    {
        $hashes = [];
        for ($i = 0; $i < 5; $i++) {
            $portfolio = $this->make(self::USER, ['name' => 'Hash test ' . $i]);
            $this->assertMatchesRegularExpression('/^[bcdfghjklmnpqrstvxyz0-9]{5}$/', $portfolio['hash']);
            $hashes[] = $portfolio['hash'];
        }
        $this->assertCount(5, array_unique($hashes));
        $this->assertSame(0, $this->rows('SELECT COUNT(*) FROM dashboard_portfolios WHERE hash = ""', []));
    }

    public function testCreatingAPortfolio(): void
    {
        $portfolio = $this->make(self::USER, ['name' => '  Water bills  ', 'notes' => 'Wells, mostly.', 'public' => 'n', 'notify' => 'sometimes']);
        $this->assertSame('Water bills', $portfolio['name']);
        $this->assertSame('Wells, mostly.', $portfolio['notes']);
        $this->assertSame('n', $portfolio['public']);
        // A value it can't have is left out.
        $this->assertSame('none', $portfolio['notify']);
        $this->assertSame('normal', $portfolio['type']);

        // Not without a name.
        $this->assertNull(Portfolio::create(self::USER, ['name' => '   ']));
    }

    public function testCreatingASmartPortfolio(): void
    {
        $criteria = Portfolio::criteria(['tag' => ' Education ', 'status' => 'introduced', 'patron_id' => '12x']);
        // Tags are lowercase; only a tag, a patron, and a committee are criteria.
        $this->assertSame(['tag' => 'education', 'patron_id' => null, 'committee_id' => null], $criteria);

        $portfolio = $this->make(self::USER, ['name' => 'Education'], $criteria);
        $this->assertSame('smart', $portfolio['type']);
        $this->assertSame('education', $portfolio['criteria']['tag']);

        // Not without a criterion.
        $this->assertNull(Portfolio::create(self::USER, ['name' => 'Nothing'], Portfolio::criteria([])));
    }

    public function testListsAUsersPortfolios(): void
    {
        $smart = $this->make(self::USER, ['name' => 'Smart one'], Portfolio::criteria(['tag' => 'education']));
        $hashes = array_column(Portfolio::for_user(self::USER), 'hash');
        $this->assertContains('pwt01', $hashes);
        $this->assertContains('pwtp1', $hashes);
        $this->assertContains($smart['hash'], $hashes);
        $this->assertNotContains('pwt04', $hashes);

        // Bills can't be added to smart portfolios.
        $this->assertNotContains($smart['hash'], array_column(Portfolio::for_user(self::USER, true), 'hash'));
    }

    public function testAnotherUsersPortfolioCantBeFound(): void
    {
        $this->assertNotNull(Portfolio::find('pwt04', self::OTHER_USER));
        $this->assertNull(Portfolio::find('pwt04', self::USER));
        $this->assertNull(Portfolio::find('nope!', self::USER));
    }

    public function testAnotherUsersPortfolioCantBeAddedTo(): void
    {
        $this->assertNull(Portfolio::add_bill('pwt04', self::USER, 'HB2049'));
        $this->assertSame(0, $this->rows(
            'SELECT COUNT(*) FROM dashboard_bills b JOIN dashboard_portfolios p ON p.id = b.portfolio_id WHERE p.hash = "pwt04"',
            []
        ));
    }

    public function testAddingAndRemovingABill(): void
    {
        $portfolio = $this->make(self::USER, ['name' => 'Bills to add']);
        $record = Portfolio::add_bill($portfolio['hash'], self::USER, 'HB 2049');
        $this->assertNotNull($record);
        $this->assertSame(77600, $record['bill_id']);

        // Not twice, nor a bill that doesn't exist, nor to a smart portfolio.
        $this->assertNull(Portfolio::add_bill($portfolio['hash'], self::USER, 'hb2049'));
        $this->assertNull(Portfolio::add_bill($portfolio['hash'], self::USER, 'HB99999'));
        $this->assertNull(Portfolio::add_bill($portfolio['hash'], self::USER, '1 OR 1=1'));
        $smart = $this->make(self::USER, ['name' => 'Smart'], Portfolio::criteria(['tag' => 'education']));
        $this->assertNull(Portfolio::add_bill($smart['hash'], self::USER, 'HB2049'));

        // Another user can't remove it, nor can it be removed through another portfolio.
        $this->assertNull(Portfolio::remove_bill($portfolio['hash'], self::OTHER_USER, $record['record_id']));
        $this->assertNull(Portfolio::remove_bill('pwt04', self::OTHER_USER, $record['record_id']));
        $this->assertNull(Portfolio::remove_bill('pwt01', self::USER, $record['record_id']));
        $this->assertSame(1, $this->rows('SELECT COUNT(*) FROM dashboard_bills WHERE id = ?', [$record['record_id']]));

        $this->assertSame(77600, Portfolio::remove_bill($portfolio['hash'], self::USER, $record['record_id']));
        $this->assertSame(0, $this->rows('SELECT COUNT(*) FROM dashboard_bills WHERE id = ?', [$record['record_id']]));
    }

    public function testAnotherUsersNotesCantBeChanged(): void
    {
        $portfolio = $this->make(self::USER, ['name' => 'Notes']);
        $record = Portfolio::add_bill($portfolio['hash'], self::USER, 'HB2049');
        $this->assertNull(Portfolio::set_notes(self::OTHER_USER, $record['record_id'], 'Mine now'));
        $this->assertSame(77600, Portfolio::set_notes(self::USER, $record['record_id'], 'A good bill.'));
        $result = mysqli_execute_query($GLOBALS['db'], 'SELECT notes FROM dashboard_bills WHERE id = ?', [$record['record_id']]);
        $this->assertSame('A good bill.', mysqli_fetch_row($result)[0]);
    }

    public function testAnotherUsersPortfolioCantBeEdited(): void
    {
        $this->assertNull(Portfolio::update('pwt04', self::USER, ['name' => 'Taken over']));
        $this->assertSame('Tester Portfolio', Portfolio::find('pwt04', self::OTHER_USER)['name']);

        $portfolio = $this->make(self::USER, ['name' => 'Before']);
        $updated = Portfolio::update($portfolio['hash'], self::USER, ['name' => 'After', 'notes' => '', 'public' => 'n']);
        $this->assertSame('After', $updated['name']);
        $this->assertNull($updated['notes']);
        $this->assertSame('n', $updated['public']);
    }

    public function testAnotherUsersSmartPortfolioCriteriaCantBeEdited(): void
    {
        $smart = $this->make(self::USER, ['name' => 'Smart'], Portfolio::criteria(['tag' => 'education']));
        $this->assertNull(Portfolio::update($smart['hash'], self::OTHER_USER, ['name' => 'Smart'], Portfolio::criteria(['tag' => 'taxes'])));

        $updated = Portfolio::update($smart['hash'], self::USER, ['name' => 'Smart'], Portfolio::criteria(['tag' => 'taxes']));
        $this->assertSame('taxes', $updated['criteria']['tag']);

        // A smart portfolio needs a criterion.
        $this->assertNull(Portfolio::update($smart['hash'], self::USER, ['name' => 'Smart'], Portfolio::criteria([])));
    }

    public function testAnotherUsersPortfolioCantBeDeleted(): void
    {
        $this->assertNull(Portfolio::delete('pwt04', self::USER));
        $this->assertNotNull(Portfolio::find('pwt04', self::OTHER_USER));
    }

    public function testDeletingAPortfolioDeletesItsBillsAndWatchListAndNobodyElses(): void
    {
        // (A tag that no bill has, so that it starts empty.)
        $smart = $this->make(self::USER, ['name' => 'Doomed'], Portfolio::criteria(['tag' => 'no bill has this']));
        mysqli_execute_query(
            $GLOBALS['db'],
            'INSERT INTO dashboard_bills SET user_id = ?, bill_id = 77600, portfolio_id = ?, date_created = NOW()',
            [self::USER, $smart['id']]
        );
        $other = $this->make(self::OTHER_USER, ['name' => 'Survivor'], Portfolio::criteria(['tag' => 'education']));
        Portfolio::add_bill('pwt04', self::OTHER_USER, 'HB2049');

        $this->assertSame([77600], Portfolio::delete($smart['hash'], self::USER));
        $this->assertNull(Portfolio::find($smart['hash'], self::USER));
        $this->assertSame(0, $this->rows('SELECT COUNT(*) FROM dashboard_bills WHERE portfolio_id = ?', [$smart['id']]));
        $this->assertSame(0, $this->rows('SELECT COUNT(*) FROM dashboard_watch_lists WHERE id = ?', [$smart['watch_list_id']]));

        // Everybody else's are as they were.
        $this->assertNotNull(Portfolio::find($other['hash'], self::OTHER_USER));
        $this->assertSame(1, $this->rows('SELECT COUNT(*) FROM dashboard_watch_lists WHERE id = ?', [$other['watch_list_id']]));
        $this->assertSame(1, $this->rows(
            'SELECT COUNT(*) FROM dashboard_bills b JOIN dashboard_portfolios p ON p.id = b.portfolio_id WHERE p.hash = "pwt04"',
            []
        ));
        $this->assertSame(2, $this->rows(
            'SELECT COUNT(*) FROM dashboard_bills b JOIN dashboard_portfolios p ON p.id = b.portfolio_id WHERE p.hash IN ("pwt01", "pwtp1") AND b.bill_id = 77600',
            []
        ));
        $pwt04 = Portfolio::find('pwt04', self::OTHER_USER);
        mysqli_execute_query($GLOBALS['db'], 'DELETE FROM dashboard_bills WHERE portfolio_id = ?', [$pwt04['id']]);
    }

    public function testDeletingAUserDeletesTheirPortfolios(): void
    {
        $user_id = 90099;
        mysqli_execute_query($GLOBALS['db'], 'DELETE FROM users WHERE id = ?', [$user_id]);
        mysqli_execute_query(
            $GLOBALS['db'],
            'INSERT INTO users SET id = ?, cookie_hash = ?, name = "Departing", email = "departing@example.com", private_hash = "depart01", date_created = NOW()',
            [$user_id, 'portfoliotestportfoliotest000099']
        );
        $this->users[] = $user_id;
        mysqli_execute_query(
            $GLOBALS['db'],
            'INSERT INTO dashboard_user_data SET user_id = ?, type = "free", email_active = "y", unsub_hash = "depart01", last_access = NOW(), date_created = NOW()',
            [$user_id]
        );
        $portfolio = Portfolio::create($user_id, ['name' => 'Bills']);
        Portfolio::add_bill($portfolio['hash'], $user_id, 'HB2049');
        Portfolio::create($user_id, ['name' => 'Smart'], Portfolio::criteria(['tag' => 'education']));

        $user = new User();
        $user->id = $user_id;
        $this->assertTrue($user->delete());
        foreach (['dashboard_bills', 'dashboard_portfolios', 'dashboard_watch_lists', 'dashboard_user_data'] as $table) {
            $this->assertSame(0, $this->rows('SELECT COUNT(*) FROM ' . $table . ' WHERE user_id = ?', [$user_id]), $table);
        }
        $this->assertNotNull(Portfolio::find('pwt01', self::USER));
    }

    public function testOnlyPeopleWithAccountsCanKeepPortfolios(): void
    {
        $this->assertTrue(Portfolio::can_use(['id' => 1, 'password' => 'x']));
        // Commenting without an account makes a user with no password.
        $this->assertFalse(Portfolio::can_use(['id' => 1, 'password' => null]));
        $this->assertFalse(Portfolio::can_use(false));
    }

    public function testAUsersSettingsAreMadeWithTheirFirstPortfolio(): void
    {
        $user_id = 90098;
        mysqli_execute_query($GLOBALS['db'], 'DELETE FROM users WHERE id = ?', [$user_id]);
        mysqli_execute_query(
            $GLOBALS['db'],
            'INSERT INTO users SET id = ?, cookie_hash = ?, name = "New Person", email = "new@example.com", private_hash = "newpers1", date_created = NOW()',
            [$user_id, 'portfoliotestportfoliotest000098']
        );
        $this->users[] = $user_id;
        $this->assertSame(0, $this->rows('SELECT COUNT(*) FROM dashboard_user_data WHERE user_id = ?', [$user_id]));

        $this->assertNotNull(Portfolio::create($user_id, ['name' => 'Bills']));
        $result = mysqli_execute_query($GLOBALS['db'], 'SELECT email_active, type, unsub_hash FROM dashboard_user_data WHERE user_id = ?', [$user_id]);
        $settings = mysqli_fetch_assoc($result);
        $this->assertSame('y', $settings['email_active']);
        $this->assertSame('free', $settings['type']);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{8}$/', $settings['unsub_hash']);

        // Only once.
        Portfolio::ensure_user_data($user_id);
        $this->assertSame(1, $this->rows('SELECT COUNT(*) FROM dashboard_user_data WHERE user_id = ?', [$user_id]));
    }

    public function testDescribesASmartPortfoliosCriteria(): void
    {
        $this->assertSame('Bills tagged <em>education</em>', Portfolio::describe(['tag' => 'education']));
        $this->assertSame(
            'Bills tagged <em>&lt;b&gt;</em>, patroned by Green, Chad',
            Portfolio::describe(['tag' => '<b>', 'patron_id' => 506])
        );
        $committee = mysqli_fetch_assoc(mysqli_query($GLOBALS['db'], 'SELECT id, name, chamber FROM committees WHERE parent_id IS NULL LIMIT 1'));
        if ($committee) {
            $this->assertStringContainsString(
                'in the ' . ucfirst($committee['chamber']) . ' ' . $committee['name'] . ' Committee',
                html_entity_decode(Portfolio::describe(['committee_id' => $committee['id']]), ENT_QUOTES)
            );
        }
    }

    public function testOnlyAPublicPortfolioIsFoundPublicly(): void
    {
        $portfolio = Portfolio::find_public('pwtp1');
        $this->assertSame('Public Test Portfolio', $portfolio['name']);
        $this->assertSame('normal', $portfolio['type']);
        // An organization's portfolio is theirs, but made by a person.
        $this->assertSame('Test Organization', $portfolio['owner']);
        $this->assertSame('Test Organization', $portfolio['organization']);
        $this->assertNotSame('Test Organization', $portfolio['person']);

        $this->assertNull(Portfolio::find_public('pwt01'));
        $this->assertNull(Portfolio::find_public('nope!'));
        $this->assertNull(Portfolio::record_for_api('pwt01'));
    }

    public function testAPortfolioForTheApi(): void
    {
        $record = Portfolio::record_for_api('pwtp1');
        $this->assertSame('pwtp1', $record['hash']);
        $this->assertSame('Public Test Portfolio', $record['name']);
        $this->assertSame('Public portfolio for browser testing', $record['notes']);
        $this->assertSame('normal', $record['type']);
        $this->assertNull($record['criteria']);
        $this->assertSame('Test Organization', $record['owner']);
        $this->assertSame('https://www.richmondsunlight.com/photosynthesis/pwtp1/', $record['page']);
        $bills = array_column($record['bills'], null, 'number');
        $this->assertArrayHasKey('hb2049', $bills);
        $this->assertSame('https://www.richmondsunlight.com/bill/' . SESSION_YEAR . '/hb2049/', $bills['hb2049']['url']);
        $this->assertSame((string) SESSION_YEAR, $bills['hb2049']['year']);

        // An empty portfolio has no bills, rather than not being found.
        $empty = $this->make(self::USER, ['name' => 'Empty', 'public' => 'y']);
        $this->assertSame([], Portfolio::record_for_api($empty['hash'])['bills']);
    }

    public function testASmartPortfolioForTheApiHasItsCriteriaAndNotItsHiddenBills(): void
    {
        $portfolio = $this->make(
            self::USER,
            ['name' => 'Green’s education bills', 'notes' => '<p>Mostly &amp; <a href="https://example.com/">civics</a></p>', 'public' => 'y'],
            Portfolio::criteria(['tag' => 'education', 'patron_id' => '506', 'committee_id' => '6'])
        );
        $committee = mysqli_fetch_assoc(mysqli_query($GLOBALS['db'], 'SELECT shortname, chamber, name FROM committees WHERE id = 6'));

        $record = Portfolio::record_for_api($portfolio['hash']);
        $this->assertSame('smart', $record['type']);
        $this->assertSame('Mostly & civics', $record['notes']);
        $this->assertSame('education', $record['criteria']['tag']);
        $this->assertSame(['shortname' => 'wcgreen', 'name' => 'Green, Chad'], $record['criteria']['patron']);
        $this->assertSame($committee, $record['criteria']['committee']);
        $this->assertSame(
            'Bills tagged education, patroned by Green, Chad, in the ' . ucfirst($committee['chamber']) . ' ' . $committee['name'] . ' Committee',
            $record['criteria']['description']
        );
        $this->assertContains('hb41', array_column($record['bills'], 'number'));

        // A bill hidden from it isn't listed.
        $hb41 = Portfolio::containing(self::USER, $this->numbers_ids('hb41'))[$portfolio['id']]['record_id'];
        Portfolio::exclude($portfolio['hash'], self::USER, $hb41);
        $this->assertNotContains('hb41', array_column(Portfolio::record_for_api($portfolio['hash'])['bills'], 'number'));

        // Nor is a private one found.
        Portfolio::update($portfolio['hash'], self::USER, ['public' => 'n']);
        $this->assertNull(Portfolio::record_for_api($portfolio['hash']));
    }

    public function testNotesAsPlainText(): void
    {
        $this->assertSame("One & two\n\nThree\nfour", Portfolio::plain_text('<p>One &amp; two</p><p>Three<br />four</p>'));
        $this->assertSame('Bills tagged <b>', Portfolio::plain_text('Bills tagged <em>&lt;b&gt;</em>'));
        $this->assertNull(Portfolio::plain_text('  '));
        $this->assertNull(Portfolio::plain_text(null));
        $this->assertNull(Portfolio::plain_text('<p></p>'));
    }

    public function testListsWhichPortfoliosHaveABill(): void
    {
        $pwt01 = Portfolio::find('pwt01', self::USER);
        $pwtp1 = Portfolio::find('pwtp1', self::USER);
        $containing = Portfolio::containing(self::USER, 77600);
        $this->assertArrayHasKey($pwt01['id'], $containing);
        $this->assertArrayHasKey($pwtp1['id'], $containing);
        $this->assertSame([], Portfolio::containing(self::OTHER_USER, 77600));
    }

    public function testListsEachPersonTrackingABillOnce(): void
    {
        // pwtp1 is public and has HB2049; so will this.
        $second = $this->make(self::USER, ['name' => 'Second public', 'public' => 'y']);
        Portfolio::add_bill($second['hash'], self::USER, 'HB2049');

        $trackers = array_filter(Portfolio::trackers(77600), fn($tracker) => $tracker['name'] == 'Test Organization');
        $this->assertCount(1, $trackers);
        $this->assertSame('pwtp1', array_values($trackers)[0]['hash']);

        // Not the user who's looking.
        $this->assertCount(0, array_filter(Portfolio::trackers(77600, self::USER), fn($tracker) => $tracker['name'] == 'Test Organization'));

        $this->assertSame('Jane D.', Portfolio::public_name('Jane  Doe Smith'));
        $this->assertSame('Cher', Portfolio::public_name('Cher'));
        $this->assertSame('An Organization', Portfolio::public_name('Jane Doe', 'An Organization'));
    }

    public function testTheSidebarListsEveryPortfolioIncludingPrivateOnes(): void
    {
        $empty = $this->make(self::USER, ['name' => 'Nothing yet', 'public' => 'n']);
        $sidebar = Portfolio::sidebar(self::USER);
        $by_hash = array_column($sidebar, null, 'hash');
        // pwt01 is private.
        $this->assertSame('n', $by_hash['pwt01']['public']);
        $this->assertContains('hb2049', array_column($by_hash['pwt01']['bills'], 'number'));
        $this->assertSame(0, $by_hash[$empty['hash']]['count']);

        $html = Portfolio::sidebar_html(self::USER);
        $this->assertStringContainsString('Test Portfolio</a> <span class="private">(private)</span>', $html);
        $this->assertStringContainsString('href="/bill/' . SESSION_YEAR . '/hb2049/"', $html);
        $this->assertStringContainsString('No bills yet.', $html);
        $this->assertSame('', Portfolio::sidebar_html(90097));
    }

    /**
     * The IDs of the current session's bills in a portfolio, by number, and whether each is
     * hidden.
     */
    private function bills_in(int $portfolio_id): array
    {
        $result = mysqli_execute_query(
            $GLOBALS['db'],
            'SELECT bills.number, b.excluded FROM dashboard_bills b JOIN bills ON bills.id = b.bill_id
            WHERE b.portfolio_id = ? AND bills.session_id = ? ORDER BY bills.number',
            [$portfolio_id, SESSION_ID]
        );
        $bills = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $bills[$row['number']] = $row['excluded'];
        }
        return $bills;
    }

    /** The current session's bill numbers that this SQL (on `bills`) finds. */
    private function numbers(string $where, array $params): array
    {
        $result = mysqli_execute_query(
            $GLOBALS['db'],
            'SELECT number FROM bills WHERE session_id = ? AND ' . $where . ' ORDER BY number',
            [SESSION_ID, ...$params]
        );
        return array_column(mysqli_fetch_all($result, MYSQLI_ASSOC), 'number');
    }

    public function testReferralsAreMatchedToCommittees(): void
    {
        // HB41's tag is education, its patron wcgreen (506), and it's in House Education (6).
        $this->assertSame(6, Day::referral_committee('house', 'Referred to Committee on Education', '2025-01-10'));
        $this->assertSame(5, Day::referral_committee('house', 'Referred to Committee for Courts of Justice', '2025-01-10'));
        // LIS's "Finance and Appropriations" is our Senate Finance.
        $this->assertSame(19, Day::referral_committee('senate', 'Rereferred to Finance and Appropriations', '2025-02-10'));
        $this->assertNull(Day::referral_committee('house', 'Read first time', '2025-01-10'));
        $this->assertNull(Day::referral_committee('house', 'Referred to Committee on Nonexistent Things', '2025-01-10'));
    }

    public function testEachCriterionAloneAndTogetherGathersTheRightBills(): void
    {
        $tag = $this->make(self::USER, ['name' => 'Tag'], Portfolio::criteria(['tag' => 'education']));
        $this->assertSame(
            $this->numbers('id IN (SELECT bill_id FROM tags WHERE tag = ?)', ['education']),
            array_keys($this->bills_in($tag['id']))
        );
        $this->assertArrayHasKey('hb41', $this->bills_in($tag['id']));

        $patron = $this->make(self::USER, ['name' => 'Patron'], Portfolio::criteria(['patron_id' => '506']));
        $this->assertSame($this->numbers('chief_patron_id = ?', [506]), array_keys($this->bills_in($patron['id'])));

        // Committee: every bill there now, at least.
        $committee = $this->make(self::USER, ['name' => 'Committee'], Portfolio::criteria(['committee_id' => '6']));
        $in = array_keys($this->bills_in($committee['id']));
        foreach ($this->numbers('last_committee_id = ?', [6]) as $number) {
            $this->assertContains($number, $in);
        }

        $all = $this->make(self::USER, ['name' => 'All'], Portfolio::criteria(['tag' => 'education', 'patron_id' => '506', 'committee_id' => '6']));
        $expected = $this->numbers(
            'id IN (SELECT bill_id FROM tags WHERE tag = ?) AND chief_patron_id = ? AND last_committee_id = ?',
            ['education', 506, 6]
        );
        $this->assertContains('hb41', $expected);
        $this->assertSame($expected, array_keys($this->bills_in($all['id'])));
    }

    public function testACommitteeGathersBillsThatWereReferredToItAndMovedOn(): void
    {
        // HB1591 isn't in House Education; put it on a docket of one of its subcommittees (47), and
        // HB2723 on a referral to it.
        $hb1591 = $this->numbers_ids('hb1591');
        $hb2723 = $this->numbers_ids('hb2723');
        mysqli_execute_query($GLOBALS['db'], 'INSERT INTO dockets SET date = "2025-01-15", committee_id = 47, bill_id = ?, date_created = NOW()', [$hb1591]);
        mysqli_execute_query(
            $GLOBALS['db'],
            'INSERT INTO bills_status SET bill_id = ?, session_id = ?, status = "Referred to Committee on Education", date = "2025-01-08", chamber = "house", date_created = NOW()',
            [$hb2723, SESSION_ID]
        );
        try {
            Portfolio::forget_referrals();
            $committee = $this->make(self::USER, ['name' => 'Education committee'], Portfolio::criteria(['committee_id' => '6']));
            $in = $this->bills_in($committee['id']);
            $this->assertArrayHasKey('hb1591', $in);
            $this->assertArrayHasKey('hb2723', $in);
        } finally {
            mysqli_execute_query($GLOBALS['db'], 'DELETE FROM dockets WHERE bill_id = ? AND committee_id = 47 AND date = "2025-01-15"', [$hb1591]);
            mysqli_execute_query($GLOBALS['db'], 'DELETE FROM bills_status WHERE bill_id = ? AND status = "Referred to Committee on Education" AND date = "2025-01-08"', [$hb2723]);
            Portfolio::forget_referrals();
        }
    }

    private function numbers_ids(string $number): int
    {
        $result = mysqli_execute_query($GLOBALS['db'], 'SELECT id FROM bills WHERE number = ? AND session_id = ?', [$number, SESSION_ID]);
        return (int) mysqli_fetch_row($result)[0];
    }

    public function testPopulatingOnlyAddsAndASecondRunAddsNothing(): void
    {
        // A tag of the test's own, on HB1591.
        $hb1591 = $this->numbers_ids('hb1591');
        mysqli_execute_query($GLOBALS['db'], 'INSERT IGNORE INTO tags SET bill_id = ?, tag = "portfolio test", date_created = NOW()', [$hb1591]);
        try {
            $portfolio = $this->make(self::USER, ['name' => 'Test tag'], Portfolio::criteria(['tag' => 'portfolio test']));
            $this->assertSame(['hb1591' => 'n'], $this->bills_in($portfolio['id']));
            $this->assertSame(['added' => 0, 'removed' => 0], Portfolio::populate($portfolio['id']));

            // It stops matching, and stays.
            mysqli_execute_query($GLOBALS['db'], 'DELETE FROM tags WHERE bill_id = ? AND tag = "portfolio test"', [$hb1591]);
            Portfolio::populate($portfolio['id']);
            $this->assertSame(['hb1591' => 'n'], $this->bills_in($portfolio['id']));
        } finally {
            mysqli_execute_query($GLOBALS['db'], 'DELETE FROM tags WHERE bill_id = ? AND tag = "portfolio test"', [$hb1591]);
        }

        // A normal portfolio isn't populated.
        $this->assertNull(Portfolio::populate(Portfolio::find('pwt01', self::USER)['id']));
    }

    public function testAHiddenBillIsntAddedBack(): void
    {
        $portfolio = $this->make(self::USER, ['name' => 'Education'], Portfolio::criteria(['tag' => 'education']));
        $record = Portfolio::containing(self::USER, $this->numbers_ids('hb41'))[$portfolio['id']]['record_id'];

        // Only its owner, and only in a smart portfolio.
        $this->assertNull(Portfolio::exclude($portfolio['hash'], self::OTHER_USER, $record));
        $this->assertNull(Portfolio::exclude('pwt01', self::USER, $record));
        $this->assertSame($this->numbers_ids('hb41'), Portfolio::exclude($portfolio['hash'], self::USER, $record));

        $this->assertSame(['added' => 0, 'removed' => 0], Portfolio::populate($portfolio['id']));
        $this->assertSame('y', $this->bills_in($portfolio['id'])['hb41']);

        // Nor shown in the tab, nor as tracked.
        $sidebar = array_column(Portfolio::sidebar(self::USER, 1000), null, 'hash');
        $this->assertNotContains('hb41', array_column($sidebar[$portfolio['hash']]['bills'], 'number'));

        // Shown again.
        Portfolio::exclude($portfolio['hash'], self::USER, $record, false);
        $this->assertSame('n', $this->bills_in($portfolio['id'])['hb41']);
    }

    public function testChangingTheCriteriaRemovesBillsThatNoLongerMatchUnlessTheyHaveNotes(): void
    {
        $portfolio = $this->make(self::USER, ['name' => 'Changing'], Portfolio::criteria(['tag' => 'education']));
        $before = $this->bills_in($portfolio['id']);
        $this->assertArrayHasKey('hb41', $before);
        $this->assertArrayHasKey('sb738', $before);
        // A note on SB738, which isn't Chad Green's.
        $record = Portfolio::containing(self::USER, $this->numbers_ids('sb738'))[$portfolio['id']]['record_id'];
        Portfolio::set_notes(self::USER, $record, 'Keep this one.');

        $updated = Portfolio::update($portfolio['hash'], self::USER, ['name' => 'Changing'], Portfolio::criteria(['tag' => 'education', 'patron_id' => '506']));
        $this->assertSame(506, (int) $updated['criteria']['patron_id']);
        $after = array_keys($this->bills_in($portfolio['id']));
        $expected = $this->numbers('id IN (SELECT bill_id FROM tags WHERE tag = ?) AND chief_patron_id = ?', ['education', 506]);
        $expected[] = 'sb738';
        sort($expected);
        $this->assertSame($expected, $after);

        // Saving the same criteria again changes nothing.
        Portfolio::set_notes(self::USER, $record, '');
        Portfolio::update($portfolio['hash'], self::USER, ['name' => 'Changing again'], Portfolio::criteria(['tag' => 'education', 'patron_id' => '506']));
        $this->assertContains('sb738', array_keys($this->bills_in($portfolio['id'])));
    }

    public function testTheFormsChoicesComeFromTheDatabase(): void
    {
        $patrons = Portfolio::patrons();
        $this->assertGreaterThan(0, count($patrons['house']));
        $this->assertMatchesRegularExpression('/^.+, .+ \((D|R|I)-\d+\)$/', $patrons['house'][0]['label']);
        $committees = Portfolio::committees();
        $this->assertContains(6, array_column($committees['house'], 'id'));
        $this->assertNotContains(47, array_column($committees['house'], 'id'));
        $tags = Portfolio::tag_suggestions();
        $this->assertContains('education', $tags);
        $this->assertTrue(Portfolio::tag_used('education'));
        $this->assertFalse(Portfolio::tag_used('no bill has this tag'));
    }

    public function testNotesAreCleanedForDisplay(): void
    {
        $html = Portfolio::notes_html('<a href="https://example.com/" onclick="steal()">Read <b onmouseover="x()">this</b></a>'
            . "\n\n<script>alert(1)</script><a href=\"javascript:alert(1)\">no</a><img src=x onerror=alert(1)>");
        $this->assertStringContainsString('<a href="https://example.com/">Read <b>this</b></a>', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('onmouseover', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('<p>', $html);
        $this->assertSame('', Portfolio::notes_html(null));

        $this->assertSame('&lt;script&gt;alert(&quot;hi&quot;)&lt;/script&gt;', Portfolio::text('<script>alert("hi")</script>'));
    }

    public function testCsrfTokens(): void
    {
        $_SESSION = [];
        $token = Csrf::token();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertSame($token, Csrf::token());
        $this->assertStringContainsString('value="' . $token . '"', Csrf::field());

        $_POST = [];
        $this->assertFalse(Csrf::valid());
        $_POST = ['csrf_token' => 'nope'];
        $this->assertFalse(Csrf::valid());
        $_POST = ['csrf_token' => $token];
        $this->assertTrue(Csrf::valid());
        $_POST = [];
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;
        $this->assertTrue(Csrf::valid());
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);

        // A session without a token accepts none.
        $_SESSION = [];
        $_POST = ['csrf_token' => ''];
        $this->assertFalse(Csrf::valid());
    }
}
