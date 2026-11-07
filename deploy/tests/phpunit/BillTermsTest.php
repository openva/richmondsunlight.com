<?php

use PHPUnit\Framework\TestCase;

/**
 * Bill2's call to the Virginia Decoded API for the defined terms in a bill's section of the Code.
 * Bill pages wait on it, so it has to give up quickly, and a bill has to be identified by its `id`.
 */
class BillTermsTest extends TestCase
{
    private function fetch(string $url)
    {
        $method = new ReflectionMethod(Bill2::class, 'fetchTerms');

        return $method->invoke(new Bill2(), $url);
    }

    public function testAnUnreachableApiIsGivenUpOnQuickly(): void
    {
        $start = microtime(true);
        $result = $this->fetch('http://192.0.2.1/api/dictionary/');
        $elapsed = microtime(true) - $start;

        $this->assertFalse($result);
        $this->assertLessThan(6, $elapsed);
    }

    public function testAReplyIsReturned(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'terms');
        file_put_contents($file, '["person","state"]');
        $result = $this->fetch('file://' . $file);
        unlink($file);

        $this->assertSame('["person","state"]', $result);
    }

    public function testAnEmptyReplyIsAFailure(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'terms');
        $result = $this->fetch('file://' . $file);
        unlink($file);

        $this->assertFalse($result);
    }

    /**
     * get_terms() reads `id`. The bill pages once set `bill_id`, so the terms never loaded.
     */
    public function testATermsLookupWithoutAnIdFails(): void
    {
        $this->assertFalse((new Bill2())->get_terms());
    }
}
