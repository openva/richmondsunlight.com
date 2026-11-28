<?php

use PHPUnit\Framework\TestCase;

/**
 * PortfolioDigest's pure parts: which bills and actions go in a user's message, and the message.
 * No database. (PortfolioEmailTest runs the whole thing against the database.)
 */
class PortfolioDigestTest extends TestCase
{
    private const SINCE = '2026-01-26 07:00:00';

    /**
     * A user with two portfolios: "Education" (bills 1 and 2) and "Water" (bills 2 and 3).
     */
    private function recipient(?array $portfolios = null): array
    {
        return [
            'user_id' => 1,
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'unsub_hash' => 'abcd1234',
            'last_notified' => self::SINCE,
            'portfolios' => $portfolios ?? [
                ['hash' => 'edu01', 'name' => 'Education', 'bill_ids' => [1, 2]],
                ['hash' => 'wat01', 'name' => 'Water', 'bill_ids' => [2, 3]],
            ],
        ];
    }

    private function action(string $status, string $recorded = '2026-01-27 06:00:00', string $date = '2026-01-26 00:00:00'): array
    {
        return ['status' => $status, 'translation' => null, 'date' => $date, 'recorded' => $recorded];
    }

    private function bill(string $number, string $catch_line, array $actions): array
    {
        return ['number' => $number, 'catch_line' => $catch_line, 'year' => 2026, 'actions' => $actions];
    }

    public function testAUserWhoseBillsHadNoActionsGetsNothing(): void
    {
        $this->assertNull(PortfolioDigest::digest($this->recipient(), [], self::SINCE));

        // Nor one whose bills' actions were all sent already.
        $actions = [1 => $this->bill('hb1', 'Schools', [$this->action('Passed House', '2026-01-26 06:00:00')])];
        $this->assertNull(PortfolioDigest::digest($this->recipient(), $actions, self::SINCE));

        // Nor one with no bills.
        $empty = $this->recipient([['hash' => 'emp01', 'name' => 'Empty', 'bill_ids' => []]]);
        $this->assertNull(PortfolioDigest::digest($empty, $actions, self::SINCE));
    }

    public function testActionsOnBillsTheUserIsntTrackingAreLeftOut(): void
    {
        $actions = [
            1 => $this->bill('hb1', 'Schools', [$this->action('Passed House')]),
            9 => $this->bill('hb9', 'Roads', [$this->action('Passed Senate')]),
        ];
        $digest = PortfolioDigest::digest($this->recipient(), $actions, self::SINCE);
        $this->assertSame(1, $digest['bills']);
        $this->assertSame(1, $digest['actions']);
        $this->assertSame(['hb1'], array_column($digest['portfolios'][0]['bills'], 'number'));
        $this->assertCount(1, $digest['portfolios']);
    }

    public function testOnlyActionsRecordedSinceTheLastMessageAreSent(): void
    {
        $actions = [1 => $this->bill('hb1', 'Schools', [
            $this->action('Referred to Education', '2026-01-25 10:00:00'),
            $this->action('Reported from Education', '2026-01-27 06:00:00'),
        ])];
        $digest = PortfolioDigest::digest($this->recipient(), $actions, self::SINCE);
        $this->assertSame(['Reported from Education'], array_column($digest['portfolios'][0]['bills'][0]['actions'], 'status'));
    }

    public function testABillInTwoPortfoliosAppearsOnce(): void
    {
        $actions = [
            2 => $this->bill('hb2', 'Wells', [$this->action('Passed House')]),
            3 => $this->bill('hb3', 'Rivers', [$this->action('Passed House')]),
        ];
        $digest = PortfolioDigest::digest($this->recipient(), $actions, self::SINCE);
        $this->assertSame(2, $digest['bills']);
        // HB2 is in the first portfolio it's in; HB3 is in the second.
        $this->assertSame('Education', $digest['portfolios'][0]['name']);
        $this->assertSame(['hb2'], array_column($digest['portfolios'][0]['bills'], 'number'));
        $this->assertSame('Water', $digest['portfolios'][1]['name']);
        $this->assertSame(['hb3'], array_column($digest['portfolios'][1]['bills'], 'number'));
    }

    public function testAnExcludedBillIsLeftOut(): void
    {
        // recipients() leaves a bill hidden from a portfolio out of its bill_ids.
        $recipient = $this->recipient([['hash' => 'edu01', 'name' => 'Education', 'bill_ids' => [1]]]);
        $actions = [
            1 => $this->bill('hb1', 'Schools', [$this->action('Passed House')]),
            2 => $this->bill('hb2', 'Wells', [$this->action('Passed House')]),
        ];
        $digest = PortfolioDigest::digest($recipient, $actions, self::SINCE);
        $this->assertSame(['hb1'], array_column($digest['portfolios'][0]['bills'], 'number'));
    }

    public function testWhenAMessageStarts(): void
    {
        $now = new DateTimeImmutable('2026-01-27 07:00:00');
        // From the last message.
        $this->assertSame('2026-01-26 07:00:00', PortfolioDigest::since('2026-01-26 07:00:00', $now));
        // But no more than three days back.
        $this->assertSame('2026-01-24 07:00:00', PortfolioDigest::since('2025-12-01 07:00:00', $now));
        // And a user who's never had one starts now.
        $this->assertSame('2026-01-27 07:00:00', PortfolioDigest::since(null, $now));
    }

    public function testTheMessage(): void
    {
        $actions = [
            1 => $this->bill('hb1', 'Schools & <libraries>', [
                $this->action('Reported from Education <with amendments>'),
                $this->action('Passed House', '2026-01-27 06:30:00', '2026-01-27 00:00:00'),
            ]),
            3 => $this->bill('sb3', 'Rivers', [$this->action('Passed Senate')]),
        ];
        $recipient = $this->recipient();
        $recipient['portfolios'][1]['name'] = 'Water <b>& wells</b>';
        $message = PortfolioDigest::render($recipient, PortfolioDigest::digest($recipient, $actions, self::SINCE));

        $this->assertSame('News on 2 of your bills, from Richmond Sunlight', $message['subject']);

        foreach (['text', 'html'] as $part) {
            $body = $message[$part];
            $this->assertStringContainsString('https://www.richmondsunlight.com/bill/2026/hb1/', $body);
            $this->assertStringContainsString('https://www.richmondsunlight.com/bill/2026/sb3/', $body);
            $this->assertStringContainsString('https://www.richmondsunlight.com/photosynthesis/', $body);
            $this->assertStringContainsString('https://www.richmondsunlight.com/photosynthesis/unsubscribe/abcd1234/', $body);
            $this->assertStringContainsString('HB1', $body);
            $this->assertStringContainsString('SB3', $body);
            $this->assertStringContainsString('January 26', $body);
            $this->assertStringContainsString('January 27', $body);
        }
        $this->assertStringContainsString('Passed House', $message['text']);
        $this->assertStringContainsString('Schools & <libraries>', $message['text']);

        // The HTML escapes names, catch lines, and actions.
        $this->assertStringContainsString('Schools &amp; &lt;libraries&gt;', $message['html']);
        $this->assertStringContainsString('Reported from Education &lt;with amendments&gt;', $message['html']);
        $this->assertStringContainsString('Water &lt;b&gt;&amp; wells&lt;/b&gt;', $message['html']);
        $this->assertStringNotContainsString('<libraries>', $message['html']);
        $this->assertStringNotContainsString('<b>', $message['html']);

        $this->assertSame('<https://www.richmondsunlight.com/photosynthesis/unsubscribe/abcd1234/>', $message['headers']['List-Unsubscribe']);
        $this->assertSame('List-Unsubscribe=One-Click', $message['headers']['List-Unsubscribe-Post']);

        // One bill is named in the subject.
        $one = PortfolioDigest::render($recipient, PortfolioDigest::digest($recipient, [3 => $actions[3]], self::SINCE));
        $this->assertSame('News on SB3, from Richmond Sunlight', $one['subject']);
    }
}
