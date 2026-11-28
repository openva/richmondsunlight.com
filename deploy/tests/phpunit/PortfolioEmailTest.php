<?php

use PHPUnit\Framework\TestCase;

/**
 * Portfolio email, against the database: PortfolioDigest::run(), sending through a RecordingMailer,
 * and (if Mailpit is running, as it is in Docker) through PHPMailer, to Mailpit.
 *
 * The test user (90001, testuser@example.com) gets a portfolio set to send email, with HB41. Each
 * test adds actions to HB41, recorded (date_created) in 2030, so that they're the only ones in the
 * span it sends, and runs as if it's then. They're deleted afterward, and the user's settings put
 * back.
 */
class PortfolioEmailTest extends TestCase
{
    private const USER = 90001;
    private const EMAIL = 'testuser@example.com';
    private const UNSUB_HASH = 'pwtest01';
    private const LAST_NOTIFIED = '2030-01-01 07:00:00';
    private const NOW = '2030-01-02 07:00:00';
    private const MAILPIT = 'http://mailpit:8025';

    private int $hb41;
    private array $portfolio;
    private array $saved;

    protected function setUp(): void
    {
        $database = new Database();
        if ($database->connect_mysqli() === false) {
            $this->markTestSkipped('No database.');
        }
        $result = mysqli_query($GLOBALS['db'], 'SHOW COLUMNS FROM dashboard_user_data LIKE "last_notified"');
        if (mysqli_num_rows($result) == 0) {
            $this->markTestSkipped('deploy/migrate-portfolio-email.sql hasn’t been run.');
        }
        $result = mysqli_execute_query($GLOBALS['db'], 'SELECT email_active, last_notified FROM dashboard_user_data WHERE user_id = ?', [self::USER]);
        $this->saved = mysqli_fetch_assoc($result) ?: [];
        if (empty($this->saved)) {
            $this->markTestSkipped('The test users aren’t loaded (deploy/mysql/test-users.sql).');
        }
        $this->hb41 = (int) mysqli_fetch_row(mysqli_execute_query(
            $GLOBALS['db'],
            'SELECT id FROM bills WHERE number = "hb41" AND session_id = ?',
            [SESSION_ID]
        ))[0];

        $this->portfolio = Portfolio::create(self::USER, ['name' => 'Emailed', 'notify' => 'daily']);
        Portfolio::add_bill($this->portfolio['hash'], self::USER, 'HB41');
        $this->set_user_data('y', self::LAST_NOTIFIED);

        // One action recorded before the last message, and two after.
        $this->add_action('Email test: referred', '2030-01-01 06:00:00');
        $this->add_action('Email test: reported', '2030-01-02 05:00:00');
        $this->add_action('Email test: passed', '2030-01-02 06:00:00');
    }

    protected function tearDown(): void
    {
        if (!isset($this->portfolio)) {
            return;
        }
        Portfolio::delete($this->portfolio['hash'], self::USER);
        mysqli_execute_query($GLOBALS['db'], 'DELETE FROM bills_status WHERE bill_id = ? AND status LIKE "Email test:%"', [$this->hb41]);
        mysqli_execute_query($GLOBALS['db'], 'DELETE FROM email_log WHERE user_id = ? AND date_created >= "2030-01-01"', [self::USER]);
        $this->set_user_data($this->saved['email_active'], $this->saved['last_notified']);
    }

    private function add_action(string $status, string $recorded): void
    {
        mysqli_execute_query(
            $GLOBALS['db'],
            'INSERT INTO bills_status SET bill_id = ?, session_id = ?, status = ?, date = "2025-01-15", date_created = ?',
            [$this->hb41, SESSION_ID, $status, $recorded]
        );
    }

    private function set_user_data(string $email_active, ?string $last_notified): void
    {
        mysqli_execute_query(
            $GLOBALS['db'],
            'UPDATE dashboard_user_data SET email_active = ?, last_notified = ? WHERE user_id = ?',
            [$email_active, $last_notified, self::USER]
        );
    }

    private function last_notified(): ?string
    {
        $result = mysqli_execute_query($GLOBALS['db'], 'SELECT last_notified FROM dashboard_user_data WHERE user_id = ?', [self::USER]);
        return mysqli_fetch_row($result)[0];
    }

    private function log(): array
    {
        $result = mysqli_execute_query(
            $GLOBALS['db'],
            'SELECT bills, actions, success FROM email_log WHERE user_id = ? AND date_created >= "2030-01-01" ORDER BY id',
            [self::USER]
        );
        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }

    private function run_email(Mailer $mailer, array $options = []): array
    {
        return PortfolioDigest::run($mailer, new DateTimeImmutable(self::NOW), $options + ['only' => [self::USER]]);
    }

    public function testTheUserGetsOneEmailListingTheNewActions(): void
    {
        $mailer = new RecordingMailer();
        $counts = $this->run_email($mailer);
        $this->assertSame(1, $counts['recipients']);
        $this->assertSame(1, $counts['sent']);
        $this->assertSame(0, $counts['failed']);

        $this->assertCount(1, $mailer->messages);
        $message = $mailer->messages[0];
        $this->assertSame(self::EMAIL, $message['to']);
        $this->assertSame('News on HB41, from Richmond Sunlight', $message['subject']);
        $this->assertStringContainsString('Email test: reported', $message['text']);
        $this->assertStringContainsString('Email test: passed', $message['text']);
        $this->assertStringContainsString('EMAILED', $message['text']);
        $this->assertStringContainsString('/photosynthesis/unsubscribe/' . self::UNSUB_HASH . '/', $message['html']);

        // Actions from before the last message aren't sent again.
        $this->assertStringNotContainsString('Email test: referred', $message['text']);

        // It's recorded, and the next message starts where this one stopped.
        $this->assertSame([['bills' => 1, 'actions' => 2, 'success' => 'y']], $this->log());
        $this->assertSame('2030-01-02 06:59:00', $this->last_notified());
    }

    public function testASecondRunSendsNothing(): void
    {
        $this->run_email(new RecordingMailer());
        $mailer = new RecordingMailer();
        $counts = $this->run_email($mailer);
        $this->assertSame(0, $counts['sent']);
        $this->assertSame(1, $counts['empty']);
        $this->assertCount(0, $mailer->messages);

        // But an action recorded after that is sent the next time.
        $this->add_action('Email test: signed', '2030-01-02 08:00:00');
        $later = PortfolioDigest::run($mailer, new DateTimeImmutable('2030-01-03 07:00:00'), ['only' => [self::USER]]);
        $this->assertSame(1, $later['sent']);
        $this->assertStringContainsString('Email test: signed', $mailer->messages[0]['text']);
        $this->assertStringNotContainsString('Email test: passed', $mailer->messages[0]['text']);
    }

    public function testAUserWithEmailOffGetsNothing(): void
    {
        $this->set_user_data('n', self::LAST_NOTIFIED);
        $mailer = new RecordingMailer();
        $this->assertSame(0, $this->run_email($mailer)['recipients']);
        $this->assertCount(0, $mailer->messages);
        $this->assertSame(self::LAST_NOTIFIED, $this->last_notified());
    }

    public function testAUserWhosePortfoliosDontSendEmailGetsNothing(): void
    {
        Portfolio::update($this->portfolio['hash'], self::USER, ['notify' => 'none']);
        $mailer = new RecordingMailer();
        $this->assertSame(0, $this->run_email($mailer)['recipients']);
        $this->assertCount(0, $mailer->messages);
    }

    public function testAHiddenBillIsntSent(): void
    {
        $smart = Portfolio::create(self::USER, ['name' => 'Smart emailed', 'notify' => 'daily'], Portfolio::criteria(['tag' => 'education']));
        try {
            // Only the smart portfolio sends email; HB41 (tagged education) is in it.
            Portfolio::update($this->portfolio['hash'], self::USER, ['notify' => 'none']);
            $mailer = new RecordingMailer();
            $this->run_email($mailer, ['dry_run' => true]);
            $this->assertCount(1, $mailer->messages);
            $this->assertStringContainsString('HB41', $mailer->messages[0]['text']);

            // Hidden from it, it isn't sent, and there's nothing else to send.
            $record = Portfolio::containing(self::USER, $this->hb41)[$smart['id']]['record_id'];
            Portfolio::exclude($smart['hash'], self::USER, $record);
            $mailer = new RecordingMailer();
            $this->assertSame(1, $this->run_email($mailer)['empty']);
            $this->assertCount(0, $mailer->messages);
        } finally {
            Portfolio::delete($smart['hash'], self::USER);
        }
    }

    public function testWhenSendingFailsTheNextRunSendsTheSameActions(): void
    {
        $counts = $this->run_email(new RecordingMailer(true));
        $this->assertSame(1, $counts['failed']);
        $this->assertSame(0, $counts['sent']);
        $this->assertSame(self::LAST_NOTIFIED, $this->last_notified());
        $this->assertSame([['bills' => 1, 'actions' => 2, 'success' => 'n']], $this->log());

        $mailer = new RecordingMailer();
        $this->assertSame(1, $this->run_email($mailer)['sent']);
        $this->assertStringContainsString('Email test: reported', $mailer->messages[0]['text']);
        $this->assertStringContainsString('Email test: passed', $mailer->messages[0]['text']);
    }

    public function testADryRunChangesNothing(): void
    {
        $mailer = new RecordingMailer();
        $counts = $this->run_email($mailer, ['dry_run' => true]);
        $this->assertSame(1, $counts['sent']);
        $this->assertCount(1, $mailer->messages);
        $this->assertSame(self::LAST_NOTIFIED, $this->last_notified());
        $this->assertSame([], $this->log());
    }

    public function testAUserWhoNeverHadAMessageStartsNow(): void
    {
        $this->set_user_data('y', null);
        $mailer = new RecordingMailer();
        $counts = $this->run_email($mailer);
        $this->assertSame(1, $counts['empty']);
        $this->assertCount(0, $mailer->messages);
        $this->assertSame('2030-01-02 06:59:00', $this->last_notified());
    }

    public function testADryRunShowsTheDayForAUserWhoNeverHadAMessage(): void
    {
        // Rather than always nothing, since a dry run never sets last_notified.
        $this->set_user_data('y', null);
        $mailer = new RecordingMailer();
        $this->assertSame(1, $this->run_email($mailer, ['dry_run' => true])['sent']);
        $this->assertStringContainsString('Email test: reported', $mailer->messages[0]['text']);
        $this->assertStringNotContainsString('Email test: referred', $mailer->messages[0]['text']);
        $this->assertNull($this->last_notified());
    }

    public function testUnsubscribing(): void
    {
        $this->assertTrue(PortfolioDigest::subscribed(self::UNSUB_HASH));
        $this->assertSame(self::USER, PortfolioDigest::unsubscribe(self::UNSUB_HASH));
        $this->assertFalse(PortfolioDigest::subscribed(self::UNSUB_HASH));
        $this->assertNull(PortfolioDigest::subscribed('zzzzzzzz'));
        $this->assertNull(PortfolioDigest::subscribed('PWTEST01'));
        $this->assertNull(PortfolioDigest::unsubscribe('nope'));
    }

    /**
     * End to end: PHPMailer sends to Mailpit, which has one message, to the test user, whose
     * unsubscribe link works.
     */
    public function testSendingThroughMailpit(): void
    {
        if (@file_get_contents(self::MAILPIT . '/api/v1/messages') === false) {
            $this->markTestSkipped('Mailpit isn’t running.');
        }
        $this->mailpit('DELETE', '/api/v1/messages');

        $counts = $this->run_email(new PhpMailerMailer());
        $this->assertSame(1, $counts['sent']);

        $list = $this->mailpit('GET', '/api/v1/messages');
        $this->assertSame(1, $list['total']);
        $summary = $list['messages'][0];
        $this->assertSame(self::EMAIL, $summary['To'][0]['Address']);
        $this->assertSame('do_not_reply@richmondsunlight.com', $summary['From']['Address']);
        $this->assertSame('News on HB41, from Richmond Sunlight', $summary['Subject']);

        $message = $this->mailpit('GET', '/api/v1/message/' . $summary['ID']);
        $unsubscribe = 'https://www.richmondsunlight.com/photosynthesis/unsubscribe/' . self::UNSUB_HASH . '/';
        $this->assertStringContainsString('Email test: passed', $message['Text']);
        $this->assertStringContainsString('Email test: passed', $message['HTML']);
        $this->assertStringContainsString($unsubscribe, $message['Text']);
        $this->assertStringContainsString($unsubscribe, $message['HTML']);

        $headers = $this->mailpit('GET', '/api/v1/message/' . $summary['ID'] . '/headers');
        $this->assertSame(['<' . $unsubscribe . '>'], $headers['List-Unsubscribe']);
        $this->assertSame(['List-Unsubscribe=One-Click'], $headers['List-Unsubscribe-Post']);

        // The link, on this server: it asks, and then (as a mail program's one-click does) a POST
        // stops the email.
        $local = str_replace('https://www.richmondsunlight.com', 'http://localhost', $unsubscribe);
        $page = file_get_contents($local);
        $this->assertStringContainsString('Stop sending me email', $page);
        $this->assertTrue(PortfolioDigest::subscribed(self::UNSUB_HASH));
        file_get_contents($local, false, stream_context_create(['http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => 'List-Unsubscribe=One-Click',
        ]]));
        $this->assertFalse(PortfolioDigest::subscribed(self::UNSUB_HASH));

        $this->mailpit('DELETE', '/api/v1/messages');
    }

    private function mailpit(string $method, string $path): ?array
    {
        $response = file_get_contents(self::MAILPIT . $path, false, stream_context_create(['http' => ['method' => $method]]));
        return json_decode((string) $response, true);
    }
}
