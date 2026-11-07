<?php

use PHPUnit\Framework\TestCase;

/**
 * PlaceMap, which prepares the shapes for the maps on the places pages. None of these need a database.
 */
class PlaceMapTest extends TestCase
{
    private const SQUARE = [[0.0, 0.0], [0.0, 10.0], [10.0, 10.0], [10.0, 0.0], [0.0, 0.0]];

    public function testPolygonsAreKeptAndEverythingElseIsDropped(): void
    {
        // What intersecting a district with a place yields when their borders don't quite line up.
        $collection = [
            'type' => 'GeometryCollection',
            'geometries' => [
                ['type' => 'Point', 'coordinates' => [1, 1]],
                ['type' => 'LineString', 'coordinates' => [[0, 0], [1, 1]]],
                ['type' => 'Polygon', 'coordinates' => [self::SQUARE]],
                ['type' => 'MultiPolygon', 'coordinates' => [[self::SQUARE], [self::SQUARE]]],
                ['type' => 'MultiPoint', 'coordinates' => [[1, 1], [2, 2]]],
            ],
        ];
        $this->assertCount(3, PlaceMap::polygons($collection));
        $this->assertSame([], PlaceMap::polygons(['type' => 'MultiPoint', 'coordinates' => [[1, 1]]]));
        $this->assertSame([], PlaceMap::polygons(null));
    }

    public function testAreaAndBounds(): void
    {
        $this->assertEqualsWithDelta(100.0, PlaceMap::area(self::SQUARE), 0.0001);
        $this->assertSame([0.0, 0.0, 10.0, 10.0], PlaceMap::bounds([[self::SQUARE]]));
        $this->assertNull(PlaceMap::bounds([]));
    }

    public function testSimplifyingDropsPointsOnAStraightLineButKeepsCorners(): void
    {
        $ring = [[0.0, 0.0], [0.0, 5.0], [0.0, 10.0], [5.0, 10.0], [10.0, 10.0], [10.0, 0.0], [5.0, 0.0], [0.0, 0.0]];
        $simplified = PlaceMap::simplifyRing($ring, 0.1);
        $this->assertCount(5, $simplified);
        $this->assertSame($simplified[0], $simplified[count($simplified) - 1], 'The ring should still be closed');
        $this->assertEqualsWithDelta(100.0, PlaceMap::area($simplified), 0.0001);
    }

    public function testSimplifyingKeepsBumpsBiggerThanTheTolerance(): void
    {
        $ring = [[0.0, 0.0], [0.0, 10.0], [5.0, 10.0], [5.0, 12.0], [6.0, 10.0], [10.0, 10.0], [10.0, 0.0], [0.0, 0.0]];
        $this->assertContains([5.0, 12.0], PlaceMap::simplifyRing($ring, 0.5));
        $this->assertNotContains([5.0, 12.0], PlaceMap::simplifyRing($ring, 5.0));
    }

    public function testCleaningDropsSpecksButNotBigShapes(): void
    {
        $speck = [[20.0, 20.0], [20.0, 20.1], [20.1, 20.1], [20.1, 20.0], [20.0, 20.0]];
        $cleaned = PlaceMap::clean([[self::SQUARE], [$speck]], 0.01, 1.0);
        $this->assertCount(1, $cleaned);
        $this->assertSame(self::SQUARE, $cleaned[0][0]);
    }

    public function testCleaningDropsTinyHolesButKeepsBigOnes(): void
    {
        $tiny_hole = [[1.0, 1.0], [1.0, 1.1], [1.1, 1.1], [1.1, 1.0], [1.0, 1.0]];
        $big_hole = [[4.0, 4.0], [4.0, 6.0], [6.0, 6.0], [6.0, 4.0], [4.0, 4.0]];
        $cleaned = PlaceMap::clean([[self::SQUARE, $tiny_hole, $big_hole]], 0.01, 1.0);
        $this->assertCount(2, $cleaned[0], 'The outline and the big hole');
    }

    public function testExpandingABoxGrowsEverySide(): void
    {
        $this->assertSame([-1.0, -2.0, 11.0, 12.0], PlaceMap::expand([1.0, 0.0, 9.0, 10.0], 2.0));
    }

    public function testContainsRespectsHoles(): void
    {
        $hole = [[4.0, 4.0], [4.0, 6.0], [6.0, 6.0], [6.0, 4.0], [4.0, 4.0]];
        $this->assertTrue(PlaceMap::contains([self::SQUARE, $hole], [2.0, 2.0]));
        $this->assertFalse(PlaceMap::contains([self::SQUARE, $hole], [5.0, 5.0]), 'In the hole');
        $this->assertFalse(PlaceMap::contains([self::SQUARE, $hole], [12.0, 5.0]), 'Outside');
    }

    public function testALabelGoesAtTheCenterOfASimpleShape(): void
    {
        [$x, $y] = PlaceMap::labelPoint([[self::SQUARE]]);
        $this->assertEqualsWithDelta(5.0, $x, 0.0001);
        $this->assertEqualsWithDelta(5.0, $y, 0.0001);
    }

    /**
     * A U-shaped district's center is in the gap between its arms, outside the district.
     */
    public function testALabelStaysInsideAShapeWhoseCenterIsOutsideIt(): void
    {
        $u = [[0.0, 0.0], [10.0, 0.0], [10.0, 10.0], [8.0, 10.0], [8.0, 2.0], [2.0, 2.0], [2.0, 10.0], [0.0, 10.0], [0.0, 0.0]];
        $label = PlaceMap::labelPoint([[$u]]);
        $this->assertTrue(PlaceMap::contains([$u], $label));
    }

    public function testALabelGoesInTheBiggestPart(): void
    {
        $small = [[20.0, 20.0], [20.0, 21.0], [21.0, 21.0], [21.0, 20.0], [20.0, 20.0]];
        $this->assertTrue(PlaceMap::contains([self::SQUARE], PlaceMap::labelPoint([[$small], [self::SQUARE]])));
    }

    public function testTheMaskCoversTheBoxExceptThePlaceAndCoversThePlacesHoles(): void
    {
        $hole = [[4.0, 4.0], [4.0, 6.0], [6.0, 6.0], [6.0, 4.0], [4.0, 4.0]];
        $mask = PlaceMap::mask([[self::SQUARE, $hole]], [-10.0, -10.0, 20.0, 20.0]);

        $this->assertCount(2, $mask, 'The box, and the place\'s hole');
        $this->assertTrue(PlaceMap::contains($mask[0], [-5.0, -5.0]), 'Outside the place is masked');
        $this->assertFalse(PlaceMap::contains($mask[0], [2.0, 2.0]), 'The place isn\'t masked');
        $this->assertTrue(PlaceMap::contains($mask[1], [5.0, 5.0]), 'The hole in the place is masked');
    }

    public function testOutlinesAreWoundCounterclockwiseAndHolesClockwise(): void
    {
        $hole = [[4.0, 4.0], [6.0, 4.0], [6.0, 6.0], [4.0, 6.0], [4.0, 4.0]];   // Counterclockwise
        [[$outline, $wound_hole]] = PlaceMap::orient([[self::SQUARE, $hole]]);   // SQUARE is clockwise

        $signed = function (array $ring): float {
            $sum = 0.0;
            for ($i = 0; $i < count($ring) - 1; $i++) {
                $sum += $ring[$i][0] * $ring[$i + 1][1] - $ring[$i + 1][0] * $ring[$i][1];
            }
            return $sum;
        };
        $this->assertGreaterThan(0, $signed($outline));
        $this->assertLessThan(0, $signed($wound_hole));
    }

    public function testAFeatureIsGeoJsonWithTheShapeAsAMultiPolygon(): void
    {
        $feature = PlaceMap::feature([[self::SQUARE]], ['kind' => 'district', 'number' => 77]);
        $this->assertSame('Feature', $feature['type']);
        $this->assertSame(['kind' => 'district', 'number' => 77], $feature['properties']);
        $this->assertSame('MultiPolygon', $feature['geometry']['type']);
        $this->assertSame([[array_reverse(self::SQUARE)]], $feature['geometry']['coordinates'], 'Wound counterclockwise');
        $this->assertJson(json_encode($feature));
    }

    public function testAPointIsAGeoJsonPointFeature(): void
    {
        $this->assertSame(
            ['type' => 'Feature', 'properties' => ['label' => 'House 54'], 'geometry' => ['type' => 'Point', 'coordinates' => [1.5, 2.5]]],
            PlaceMap::point([1.5, 2.5], ['label' => 'House 54'])
        );
    }
}
