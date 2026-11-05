<?php

use PHPUnit\Framework\TestCase;

/**
 * Import::legislator_in_lis(), which checks whether a legislator is still listed on their
 * chamber's website, by their ID.
 */
class ImportLegislatorInLisTest extends TestCase
{
    private const HOUSE_PAGE = '<a href="/members/H0108">Watts, Vivian E.</a>
        <a href="/members/H0342">Scott, Phillip A.</a>';

    private const SENATE_PAGE = '<a href="//apps.senate.virginia.gov/Senator/memberpage.php?id=S100 ">Todd E. Pillion</a>
        <a href="//apps.senate.virginia.gov/Senator/memberpage.php?id=S85  ">Adam P. Ebbin</a>';

    /**
     * @param string|false $page What the chamber's website returns.
     * @param array<string,mixed> $api What the LIS API returns.
     */
    private function import($page, array $api = []): Import
    {
        return new class (new class extends Log {
            public function put($message, $level = 3)
            {
                return true;
            }
        }, $page, $api) extends Import {
            public function __construct(Log $log, private $page, private array $api)
            {
                parent::__construct($log);
            }

            protected function http_get($url)
            {
                return $this->page;
            }

            protected function lis_api_request($path, array $query = [])
            {
                return $this->api;
            }
        };
    }

    public function testFindsMembersByTheirId(): void
    {
        $this->assertTrue($this->import(self::HOUSE_PAGE)->legislator_in_lis('H0108'));
        $this->assertTrue($this->import(self::SENATE_PAGE)->legislator_in_lis('S0085'));
    }

    /**
     * A departed member isn't found by a surname that a colleague shares.
     */
    public function testDoesntFindADepartedMemberByTheirName(): void
    {
        $this->assertFalse($this->import(self::HOUSE_PAGE)->legislator_in_lis('H0155', 'Scott, Donald'));
    }

    public function testDoesntMatchTheStartOfALongerId(): void
    {
        $this->assertFalse($this->import(self::SENATE_PAGE)->legislator_in_lis('S0010'));
    }

    /**
     * A page that links to nobody by ID has changed, and says nothing about who's left office.
     */
    public function testFallsBackToTheApiWhenThePageListsNobody(): void
    {
        $api = ['Members' => [['MemberNumber' => 'H0108', 'MemberStatus' => 'Active']]];

        $this->assertTrue($this->import('<p>Watts, Vivian E.</p>', $api)->legislator_in_lis('H0108'));
        $this->assertTrue($this->import(false, $api)->legislator_in_lis('H0108'));
        $this->assertFalse($this->import(false, [])->legislator_in_lis('H0108'));
    }

    public function testRejectsAnInvalidId(): void
    {
        $this->expectException(Exception::class);
        $this->import(self::HOUSE_PAGE)->legislator_in_lis('108');
    }
}
