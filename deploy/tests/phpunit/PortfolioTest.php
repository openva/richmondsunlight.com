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
        $criteria = Portfolio::criteria(['tag' => 'education', 'status' => 'nonsense', 'patron_id' => '12x']);
        $this->assertSame('education', $criteria['tag']);
        $this->assertNull($criteria['status']);
        $this->assertNull($criteria['patron_id']);

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
        $smart = $this->make(self::USER, ['name' => 'Doomed'], Portfolio::criteria(['tag' => 'education']));
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
