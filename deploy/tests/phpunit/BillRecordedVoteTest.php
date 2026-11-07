<?php

use PHPUnit\Framework\TestCase;

/**
 * Bill2's call to recordedvote.org, for the bills that it lists as similar. When that service can't
 * be reached, the call has to give up, since the request for a bill can't be answered until it does.
 */
class BillRecordedVoteTest extends TestCase
{
    /**
     * A Bill2 that asks an address that never answers (192.0.2.1 is reserved for documentation, and
     * isn't routed anywhere), instead of recordedvote.org.
     */
    private function billThatCantReachRecordedVote(): Bill2
    {
        return new class extends Bill2 {
            protected function recordedvoteUrl(string $number): string
            {
                return 'http://192.0.2.1/v1/bill/similarity/' . $number;
            }
        };
    }

    private function relatedFromRecordedVote(Bill2 $bill, array $data): bool
    {
        $method = new ReflectionMethod(Bill2::class, 'related_recordedvote');

        return $method->invoke($bill, $data);
    }

    /**
     * Without a timeout, this waits for minutes (curl's own limit is 300 seconds).
     */
    public function testAnUnreachableRecordedVoteIsGivenUpOnQuickly(): void
    {
        $start = microtime(true);
        $result = $this->relatedFromRecordedVote($this->billThatCantReachRecordedVote(), ['number' => 'hb1']);
        $elapsed = microtime(true) - $start;

        $this->assertFalse($result, 'A failed call is reported as one, so the internal related bills are used');
        $this->assertLessThan(6, $elapsed, 'It should give up after 2 seconds, not wait for curl\'s default');
    }

    /**
     * A Bill2 that reads recordedvote's "reply" from a file.
     */
    private function billWithReply(string $reply): array
    {
        $file = tempnam(sys_get_temp_dir(), 'rv');
        file_put_contents($file, $reply);
        $bill = new class ($file) extends Bill2 {
            public function __construct(private string $file)
            {
            }

            protected function recordedvoteUrl(string $number): string
            {
                return 'file://' . $this->file;
            }
        };
        $result = $this->relatedFromRecordedVote($bill, ['number' => 'hb1']);
        unlink($file);

        return [$result, $bill];
    }

    public function testAReplyThatIsNotJsonIsAFailure(): void
    {
        [$result] = $this->billWithReply('<html>502 Bad Gateway</html>');
        $this->assertFalse($result, 'The internal related bills are used instead');
    }

    public function testAReplyThatIsNotAListIsAFailure(): void
    {
        [$result] = $this->billWithReply('"rate limited"');
        $this->assertFalse($result);
    }

    public function testAListIsUsed(): void
    {
        [$result, $bill] = $this->billWithReply(
            '[{"bill_id":"HB2","bill_description":"A bill"}]'
        );
        $this->assertTrue($result);
        $this->assertSame('hb2', (new ReflectionProperty(Bill2::class, 'related_bills'))->getValue($bill)[0]['number']);
    }

    public function testTheTimeoutIsTwoSeconds(): void
    {
        $constants = (new ReflectionClass(Bill2::class))->getConstants(ReflectionClassConstant::IS_PRIVATE);
        $this->assertSame(2, $constants['RECORDEDVOTE_CONNECT_TIMEOUT']);
    }

    public function testTheWholeCallIsLimitedToFiveSeconds(): void
    {
        $constants = (new ReflectionClass(Bill2::class))->getConstants(ReflectionClassConstant::IS_PRIVATE);
        $this->assertSame(5, $constants['RECORDEDVOTE_TIMEOUT']);
    }

    public function testTheUrlIsRecordedVotesSimilarityEndpoint(): void
    {
        $method = new ReflectionMethod(Bill2::class, 'recordedvoteUrl');
        $this->assertSame(
            'https://api.recordedvote.org/v1/bill/similarity/hb41?remove_duplicates=1',
            $method->invoke(new Bill2(), 'hb41')
        );
    }
}
