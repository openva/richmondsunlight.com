<?php

/**
 * Prepares the shapes for the maps on the places pages: pulls polygons out of GeoJSON, simplifies
 * them, and writes them back as GeoJSON features.
 *
 * Shapes are lists of polygons. A polygon is a list of rings: the outline first, then any holes.
 * A ring is a list of [longitude, latitude] points.
 */
class PlaceMap
{

    /**
     * Pull the polygons out of a GeoJSON geometry. Intersecting a district with a place leaves
     * stray points and lines where their borders don't quite line up, so anything that isn't a
     * polygon is dropped.
     *
     * @param array<string, mixed>|null $geometry A decoded GeoJSON geometry.
     * @return array<int, array<int, array<int, array{0: float, 1: float}>>>
     */
    public static function polygons(?array $geometry): array
    {
        if ($geometry === null || !isset($geometry['type'])) {
            return [];
        }
        switch ($geometry['type']) {
            case 'Polygon':
                return [$geometry['coordinates']];
            case 'MultiPolygon':
                return $geometry['coordinates'];
            case 'GeometryCollection':
                $polygons = [];
                foreach ($geometry['geometries'] as $member) {
                    $polygons = array_merge($polygons, self::polygons($member));
                }
                return $polygons;
            default:
                return [];
        }
    }

    /**
     * The area of a ring, in square degrees.
     */
    public static function area(array $ring): float
    {
        $sum = 0.0;
        $n = count($ring);
        for ($i = 0; $i < $n - 1; $i++) {
            $sum += $ring[$i][0] * $ring[$i + 1][1] - $ring[$i + 1][0] * $ring[$i][1];
        }
        return abs($sum) / 2;
    }

    /**
     * The corners of a shape: [west, south, east, north].
     *
     * @return array{0: float, 1: float, 2: float, 3: float}|null
     */
    public static function bounds(array $polygons): ?array
    {
        $bounds = null;
        foreach ($polygons as $polygon) {
            foreach ($polygon[0] ?? [] as [$x, $y]) {
                $bounds ??= [$x, $y, $x, $y];
                $bounds = [min($bounds[0], $x), min($bounds[1], $y), max($bounds[2], $x), max($bounds[3], $y)];
            }
        }
        return $bounds;
    }

    /**
     * Thin out a ring with the Douglas-Peucker algorithm, keeping the shape to within a tolerance.
     */
    public static function simplifyRing(array $ring, float $tolerance): array
    {
        $points = array_slice($ring, 0, -1);   // Drop the point that closes the ring.
        $n = count($points);
        if ($n < 4) {
            return $ring;
        }

        // A ring starts and ends at the same point, so split it at the point farthest from there.
        $far = 0;
        $far_distance = -1.0;
        for ($i = 1; $i < $n; $i++) {
            $distance = ($points[$i][0] - $points[0][0]) ** 2 + ($points[$i][1] - $points[0][1]) ** 2;
            if ($distance > $far_distance) {
                $far_distance = $distance;
                $far = $i;
            }
        }
        $first = self::douglasPeucker(array_slice($points, 0, $far + 1), $tolerance);
        $second = self::douglasPeucker(array_merge(array_slice($points, $far), [$points[0]]), $tolerance);
        $simplified = array_merge($first, array_slice($second, 1, -1));

        if (count($simplified) < 3) {
            return $ring;
        }
        $simplified[] = $simplified[0];
        return $simplified;
    }

    /**
     * @param array<int, array{0: float, 1: float}> $points A line, not a ring.
     */
    private static function douglasPeucker(array $points, float $tolerance): array
    {
        $n = count($points);
        if ($n < 3) {
            return $points;
        }
        [$x1, $y1] = $points[0];
        [$x2, $y2] = $points[$n - 1];
        $dx = $x2 - $x1;
        $dy = $y2 - $y1;
        $length = sqrt($dx * $dx + $dy * $dy);

        $max = 0.0;
        $index = 0;
        for ($i = 1; $i < $n - 1; $i++) {
            if ($length == 0.0) {
                $distance = sqrt(($points[$i][0] - $x1) ** 2 + ($points[$i][1] - $y1) ** 2);
            } else {
                $distance = abs($dy * $points[$i][0] - $dx * $points[$i][1] + $x2 * $y1 - $y2 * $x1) / $length;
            }
            if ($distance > $max) {
                $max = $distance;
                $index = $i;
            }
        }

        if ($max <= $tolerance) {
            return [$points[0], $points[$n - 1]];
        }
        $left = self::douglasPeucker(array_slice($points, 0, $index + 1), $tolerance);
        $right = self::douglasPeucker(array_slice($points, $index), $tolerance);
        return array_merge(array_slice($left, 0, -1), $right);
    }

    /**
     * Simplify a shape, and drop its specks: polygons and holes smaller than a given area.
     *
     * @return array<int, array<int, array<int, array{0: float, 1: float}>>>
     */
    public static function clean(array $polygons, float $tolerance, float $min_area): array
    {
        $cleaned = [];
        foreach ($polygons as $polygon) {
            if (self::area($polygon[0]) < $min_area) {
                continue;
            }
            $rings = [];
            foreach ($polygon as $i => $ring) {
                if ($i > 0 && self::area($ring) < $min_area) {
                    continue;
                }
                $ring = self::simplifyRing($ring, $tolerance);
                if (count($ring) >= 4) {
                    $rings[] = $ring;
                } elseif ($i === 0) {
                    // Without its outline, the polygon is gone, holes and all.
                    continue 2;
                }
            }
            $cleaned[] = $rings;
        }
        return $cleaned;
    }

    /**
     * Grow a box ([west, south, east, north]) by a margin on every side.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    public static function expand(array $box, float $margin): array
    {
        return [$box[0] - $margin, $box[1] - $margin, $box[2] + $margin, $box[3] + $margin];
    }

    /**
     * Whether a point is inside a polygon (inside its outline, and not in any of its holes).
     */
    public static function contains(array $polygon, array $point): bool
    {
        $inside = false;
        foreach ($polygon as $ring) {
            $n = count($ring);
            for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
                [$xi, $yi] = $ring[$i];
                [$xj, $yj] = $ring[$j];
                if (($yi > $point[1]) !== ($yj > $point[1])
                    && $point[0] < ($xj - $xi) * ($point[1] - $yi) / ($yj - $yi) + $xi) {
                    $inside = !$inside;
                }
            }
        }
        return $inside;
    }

    /**
     * A point well inside a shape, for its label: the center of its biggest polygon, if that's
     * inside it, or else the middle of the widest stretch of the polygon across its center. (A
     * crescent's center, for instance, is outside of it.)
     *
     * @return array{0: float, 1: float}
     */
    public static function labelPoint(array $polygons): array
    {
        $biggest = $polygons[0];
        foreach ($polygons as $polygon) {
            if (self::area($polygon[0]) > self::area($biggest[0])) {
                $biggest = $polygon;
            }
        }

        $center = self::centroid($biggest[0]);
        if (self::contains($biggest, $center)) {
            return $center;
        }
        $span = self::widestSpan($biggest, $center[1]);
        return $span === null ? $biggest[0][0] : [($span[0] + $span[1]) / 2, $center[1]];
    }

    /**
     * The center of a ring's area (or of its bounding box, if it has no area).
     *
     * @return array{0: float, 1: float}
     */
    private static function centroid(array $ring): array
    {
        $sum = $cx = $cy = 0.0;
        $n = count($ring);
        for ($i = 0; $i < $n - 1; $i++) {
            $cross = $ring[$i][0] * $ring[$i + 1][1] - $ring[$i + 1][0] * $ring[$i][1];
            $sum += $cross;
            $cx += ($ring[$i][0] + $ring[$i + 1][0]) * $cross;
            $cy += ($ring[$i][1] + $ring[$i + 1][1]) * $cross;
        }
        if (abs($sum) > 1e-12) {
            return [$cx / (3 * $sum), $cy / (3 * $sum)];
        }
        $box = self::bounds([[$ring]]);
        return [($box[0] + $box[2]) / 2, ($box[1] + $box[3]) / 2];
    }

    /**
     * The widest stretch of a polygon along a horizontal line, as [west, east], or null if the
     * line misses it. Between each pair of places where the line crosses the polygon's edges, the
     * line is inside the polygon.
     *
     * @return array{0: float, 1: float}|null
     */
    private static function widestSpan(array $polygon, float $y): ?array
    {
        $crossings = [];
        foreach ($polygon as $ring) {
            $n = count($ring);
            for ($i = 0; $i < $n - 1; $i++) {
                [$x1, $y1] = $ring[$i];
                [$x2, $y2] = $ring[$i + 1];
                if (($y1 > $y) !== ($y2 > $y)) {
                    $crossings[] = $x1 + ($y - $y1) * ($x2 - $x1) / ($y2 - $y1);
                }
            }
        }
        sort($crossings);

        $widest = null;
        for ($i = 0; $i + 1 < count($crossings); $i += 2) {
            if ($widest === null || $crossings[$i + 1] - $crossings[$i] > $widest[1] - $widest[0]) {
                $widest = [$crossings[$i], $crossings[$i + 1]];
            }
        }
        return $widest;
    }

    /**
     * A shape that covers a box, except for a place: the box with the place's outlines cut out of
     * it. Holes in the place (such as an independent city inside a county) aren't part of the
     * place, so they're covered, too.
     */
    public static function mask(array $place_polygons, array $box): array
    {
        $outline = [[$box[0], $box[1]], [$box[2], $box[1]], [$box[2], $box[3]], [$box[0], $box[3]], [$box[0], $box[1]]];
        $mask = [array_merge([$outline], array_map(fn ($polygon) => $polygon[0], $place_polygons))];
        foreach ($place_polygons as $polygon) {
            foreach (array_slice($polygon, 1) as $hole) {
                $mask[] = [$hole];
            }
        }
        return $mask;
    }

    /**
     * Wind each polygon's outline counterclockwise and its holes clockwise, as GeoJSON specifies.
     * Mapbox tells holes from outlines by their winding, so a hole wound the wrong way gets filled.
     */
    public static function orient(array $polygons): array
    {
        foreach ($polygons as &$polygon) {
            foreach ($polygon as $i => &$ring) {
                $sum = 0.0;
                $n = count($ring);
                for ($k = 0; $k < $n - 1; $k++) {
                    $sum += $ring[$k][0] * $ring[$k + 1][1] - $ring[$k + 1][0] * $ring[$k][1];
                }
                $counterclockwise = $sum > 0;
                if ($counterclockwise !== ($i === 0)) {
                    $ring = array_reverse($ring);
                }
            }
            unset($ring);
        }
        unset($polygon);
        return $polygons;
    }

    /**
     * A GeoJSON feature for a shape.
     *
     * @param array<int, array> $polygons
     * @param array<string, mixed> $properties
     * @return array<string, mixed>
     */
    public static function feature(array $polygons, array $properties): array
    {
        return [
            'type' => 'Feature',
            'properties' => $properties,
            'geometry' => ['type' => 'MultiPolygon', 'coordinates' => self::orient($polygons)],
        ];
    }

    /**
     * A GeoJSON feature for a point.
     *
     * @param array{0: float, 1: float} $point
     * @param array<string, mixed> $properties
     * @return array<string, mixed>
     */
    public static function point(array $point, array $properties): array
    {
        return [
            'type' => 'Feature',
            'properties' => $properties,
            'geometry' => ['type' => 'Point', 'coordinates' => $point],
        ];
    }
}
