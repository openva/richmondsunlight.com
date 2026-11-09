<?php

use PHPUnit\Framework\TestCase;

/**
 * Places, which lists Virginia's counties and independent cities, their legislators, and the bills
 * that name them. The database tests need the gazetteer's counties and cities, which
 * deploy/database_export.sh exports to test-records.sql.
 */
class PlacesTest extends TestCase
{
    private static ?Places $places = null;

    public static function setUpBeforeClass(): void
    {
        try {
            $db = (new Database())->connect();
        } catch (PDOException $e) {
            return;
        }
        if ($db === false || (int) $db->query('SELECT COUNT(*) FROM gazetteer')->fetchColumn() === 0) {
            return;
        }
        self::$places = new Places($db, false);
    }

    private function places(): Places
    {
        if (self::$places === null) {
            $this->markTestSkipped('The database, or its gazetteer, is not available');
        }
        return self::$places;
    }

    /**
     * The House and Senate district numbers that represent a place.
     */
    private function districtNumbers(string $slug): array
    {
        $place = $this->places()->find($slug);
        $this->assertNotFalse($place, $slug . ' should be found');
        $numbers = ['house' => [], 'senate' => []];
        foreach ($this->places()->districts($place['id']) as $district) {
            $numbers[$district['chamber']][] = $district['number'];
        }
        return $numbers;
    }

    public function testSlugs(): void
    {
        $this->assertSame('fairfax-county', Places::slug('Fairfax County'));
        $this->assertSame('king-and-queen-county', Places::slug('King and Queen County'));
        $this->assertSame('fairfax-county', Places::slug('fairfax+county'));
        $this->assertSame('charlottesville', Places::slug('Charlottesville'));
    }

    public function testListsEveryCountyAndCityButNoTowns(): void
    {
        $types = array_count_values(array_column($this->places()->listAll(), 'type'));
        $this->assertSame(95, $types['county']);
        $this->assertSame(38, $types['city']);
    }

    public function testCitiesNamedLikeCountiesAreCalledCities(): void
    {
        $this->assertSame('City of Fairfax', $this->places()->find('fairfax')['display_name']);
        $this->assertSame('Fairfax County', $this->places()->find('fairfax-county')['display_name']);
        $this->assertSame('Charlottesville', $this->places()->find('charlottesville')['display_name']);
    }

    public function testUnknownPlacesAreNotFound(): void
    {
        $this->assertFalse($this->places()->find('nowhere'));
        $this->assertFalse($this->places()->findByName('Scottsville'));   // A town
    }

    /**
     * Searching district descriptions for "Richmond" used to find Richmond County's legislators, too.
     */
    public function testRichmondAndRichmondCountyHaveTheirOwnDistricts(): void
    {
        $this->assertSame(['house' => [77, 78, 79], 'senate' => [14, 15]], $this->districtNumbers('richmond'));
        $this->assertSame(['house' => [67], 'senate' => [25]], $this->districtNumbers('richmond-county'));
    }

    public function testTheCityOfFairfaxIsInOneDistrictOfEachChamber(): void
    {
        $this->assertSame(['house' => [11], 'senate' => [37]], $this->districtNumbers('fairfax'));
    }

    /**
     * House District 4 is less than 1% of Fairfax County, but 44% of the district.
     */
    public function testADistrictThatIsASliverOfABigCountyIsIncluded(): void
    {
        $this->assertContains(4, $this->districtNumbers('fairfax-county')['house']);
    }

    /**
     * Hampton's old boundary included Hampton Roads, and so overlapped Norfolk's districts.
     */
    public function testNeighborsAcrossTheWaterDontShareDistricts(): void
    {
        $this->assertNotContains(94, $this->districtNumbers('hampton')['house']);
    }

    public function testEveryPlaceHasADelegateAndASenator(): void
    {
        foreach ($this->places()->listAll() as $place) {
            $chambers = array_column($this->places()->districts($place['id']), 'chamber');
            $this->assertContains('house', $chambers, $place['name'] . ' should have a delegate');
            $this->assertContains('senate', $chambers, $place['name'] . ' should have a senator');
        }
    }

    public function testLegislatorsAreOnePerDistrictDelegatesFirst(): void
    {
        $place = $this->places()->find('richmond-county');
        $legislators = $this->places()->legislators($place['id']);
        $this->assertSame(['house', 'senate'], array_column($legislators, 'chamber'));
        $this->assertTrue($legislators[0]['whole'], 'House District 67 has all of Richmond County');
    }

    public function testTheMapHasAShapeAndALabelInsideThePlaceForEachDistrict(): void
    {
        $place = $this->places()->find('richmond');
        $map = $this->places()->mapData($place['id']);
        $this->assertNotEmpty($map['place']);

        $district_ids = array_column($this->places()->districts($place['id']), 'district_id');
        $this->assertEqualsCanonicalizing($district_ids, array_keys($map['districts']));
        foreach ($map['districts'] as $district) {
            foreach ($district['shape'] as $polygon) {
                $this->assertGreaterThanOrEqual(4, count($polygon[0]), 'Every polygon is a closed ring');
            }
            $inside = false;
            foreach ($map['place'] as $polygon) {
                $inside = $inside || PlaceMap::contains($polygon, $district['label']);
            }
            $this->assertTrue($inside, 'Every label is inside the place');
        }
    }

    /**
     * Charlottesville is smaller than both of its districts, which used to be drawn as if they
     * ended at the city line.
     */
    public function testDistrictsAreDrawnPastTheEdgeOfThePlace(): void
    {
        $place = $this->places()->find('charlottesville');
        $map = $this->places()->mapData($place['id']);
        $place_bounds = PlaceMap::bounds($map['place']);

        // The map can be panned past the place, and the districts are drawn that far.
        $this->assertLessThan($place_bounds[0], $map['view'][0]);
        $this->assertGreaterThan($place_bounds[2], $map['view'][2]);
        foreach ($map['districts'] as $district) {
            $district_bounds = PlaceMap::bounds($district['shape']);
            $this->assertLessThan($place_bounds[0], $district_bounds[0], 'The district reaches west of the city');
        }

        // Everything outside the city is masked, and the city isn't.
        $this->assertCount(1, $map['mask']);
        $this->assertCount(1 + count($map['place']), $map['mask'][0], 'The view, with the city cut out');
    }

    public function testLegislatorsSayWhetherTheirDistrictsExtendPastThePlace(): void
    {
        $place = $this->places()->find('charlottesville');
        foreach ($this->places()->legislators($place['id']) as $legislator) {
            $this->assertTrue($legislator['extends'], $legislator['name_formatted'] . "'s district extends past the city");
        }

        // Some of Fairfax County's districts are entirely inside it.
        $place = $this->places()->find('fairfax-county');
        $this->assertContains(false, array_column($this->places()->legislators($place['id']), 'extends'));
    }

    /**
     * Fairfax County is the biggest case: 22 districts, which have to stay small enough to cache.
     */
    public function testTheBiggestMapIsSmallEnoughToCache(): void
    {
        $place = $this->places()->find('fairfax-county');
        $map = $this->places()->mapData($place['id']);
        $this->assertGreaterThan(15, count($map['districts']));
        $this->assertLessThan(300000, strlen(serialize($map)));
    }

    /**
     * Bills are listed from every year that has them, not only from 2024 on. (This adds a bill from
     * 2010, and removes it afterward.)
     */
    public function testBillsFromOlderYearsAreListedAndCounted(): void
    {
        $this->places();   // Skips this test where there's no database
        $db = (new Database())->connect();
        $session = $db->query('SELECT id FROM sessions WHERE year = 2010 ORDER BY id LIMIT 1')->fetchColumn();
        if (!$session) {
            $this->markTestSkipped('There is no 2010 session in the database');
        }

        try {
            $db->prepare('INSERT INTO bills (id, number, session_id, chief_patron_id, catch_line, date_created)
                VALUES (990001, "hb9990", ?, 16, "A bill from 2010", NOW())')->execute([$session]);
            $db->exec('INSERT INTO bills_places (bill_id, placename, latitude, longitude)
                VALUES (990001, "Highland County", 38.3, -79.5)');

            $places = new Places($db, false);
            $bills = $places->bills('Highland County');
            $this->assertSame([2010], array_keys($bills));
            $this->assertSame('hb9990', $bills[2010][0]['number']);
            $this->assertSame(1, $places->find('highland-county')['bill_count']);
        } finally {
            $db->exec('DELETE FROM bills_places WHERE bill_id = 990001');
            $db->exec('DELETE FROM bills WHERE id = 990001');
        }
    }

    public function testADistrictListsThePlacesItRepresents(): void
    {
        $place = $this->places()->find('richmond-county');
        $district_id = $this->places()->districts($place['id'])[0]['district_id'];
        $this->assertContains('richmond-county', array_column($this->places()->forDistrict($district_id), 'slug'));
    }

    /**
     * HJ14 (2024) names Richmond, among other places.
     */
    public function testBillsAreGroupedByYear(): void
    {
        $bills = $this->places()->bills('Richmond');
        $this->assertArrayHasKey(2024, $bills);
        $this->assertContains('hj14', array_column($bills[2024], 'number'));
        $years = array_keys($bills);
        $sorted = $years;
        rsort($sorted);
        $this->assertSame($sorted, $years, 'The newest year should be first');
    }

    /**
     * Places opens a database connection only when a query needs one, so that a page whose places
     * are all in Memcached doesn't take up a connection.
     */
    public function testNoConnectionIsOpenedUntilOneIsNeeded(): void
    {
        $places = new Places(null, false);
        $db = new ReflectionProperty(Places::class, 'db');

        $this->assertNull($db->getValue($places));
    }
}
