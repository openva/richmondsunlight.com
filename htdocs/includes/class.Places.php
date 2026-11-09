<?php

/**
 * Virginia's counties and independent cities, their legislators, and the bills that name them.
 *
 * Localities come from the gazetteer, which also lists towns. A county's municipality is NULL, and
 * an independent city's is its name followed by " (city)". A locality's legislators are found by
 * comparing its boundary with each current district's boundary.
 */
class Places
{
    /**
     * A district is counted as representing a locality when their overlap is at least this share
     * of the locality or of the district. Checking both keeps districts that are a sliver of a big
     * county (House District 4 is 0.65% of Fairfax County, but 44% of the district), while leaving
     * out the slivers where boundaries from different sources don't quite line up.
     */
    public const MIN_SHARE = 0.02;

    /**
     * A district with at least this share of a locality represents all of it.
     */
    public const WHOLE_SHARE = 0.98;

    /**
     * How long to cache results, in seconds. Districts change only after redistricting, and the
     * gazetteer hardly ever, but bills are tagged with places daily during the session.
     */
    private const CACHE_DISTRICTS = 60 * 60 * 24 * 7;
    private const CACHE_BILLS = 60 * 60;

    /**
     * The SQL condition that selects counties and independent cities (but not towns).
     */
    private const LOCALITY_SQL = '(gazetteer.municipality IS NULL
        OR gazetteer.municipality = CONCAT(gazetteer.name, " (city)"))';

    protected ?PDO $db;
    protected ?Memcached $cache;

    /**
     * @param PDO|null             $db    A database connection, or null to open one when it's first
     *                                    needed (which, if everything is cached, it isn't).
     * @param Memcached|false|null $cache A cache connection, null to open one, or false not to cache.
     */
    public function __construct(?PDO $db = null, Memcached|false|null $cache = null)
    {
        $this->db = $db;
        if ($cache === null && defined('MEMCACHED_SERVER') && MEMCACHED_SERVER != ''
            && class_exists('Memcached')) {
            $cache = new Memcached();
            $cache->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);
        }
        $this->cache = $cache ?: null;
    }

    /**
     * The database connection, opened the first time a query needs it.
     */
    private function db(): PDO
    {
        if ($this->db === null) {
            $this->db = (new Database())->connect();
        }
        return $this->db;
    }

    /**
     * The URL slug for a locality's name: "Fairfax County" becomes "fairfax-county," and the city
     * of Fairfax becomes "fairfax."
     */
    public static function slug(string $name): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($name)), '-');
    }

    /**
     * Get a value from the cache, or else compute it and cache it.
     */
    private function cached(string $key, int $expiration, callable $compute): mixed
    {
        if ($this->cache !== null) {
            $value = $this->cache->get($key);
            if ($this->cache->getResultCode() === Memcached::RES_SUCCESS) {
                return $value;
            }
        }
        $value = $compute();
        if ($this->cache !== null) {
            $this->cache->set($key, $value, $expiration);
        }
        return $value;
    }

    /**
     * List every county and independent city, alphabetically, with how many bills (from any year)
     * have named it.
     *
     * @return array<int, array{id: int, name: string, display_name: string, type: string, slug: string, bill_count: int}>
     */
    public function listAll(): array
    {
        return $this->cached('places-list-v2', self::CACHE_BILLS, function () {
            $sql = 'SELECT
                        gazetteer.id,
                        gazetteer.name,
                        gazetteer.municipality IS NULL AS is_county,
                        (SELECT COUNT(DISTINCT bills_places.bill_id)
                        FROM bills_places
                        JOIN bills
                            ON bills.id = bills_places.bill_id
                        JOIN sessions
                            ON sessions.id = bills.session_id
                        WHERE
                            bills_places.placename = gazetteer.name COLLATE utf8mb3_bin) AS bill_count
                    FROM gazetteer
                    WHERE ' . self::LOCALITY_SQL . '
                    ORDER BY gazetteer.name ASC';
            $stmt = $this->db()->prepare($sql);
            $stmt->execute();

            $places = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $places[] = [
                    'id' => (int) $row['id'],
                    'name' => $row['name'],
                    'type' => $row['is_county'] ? 'county' : 'city',
                    'slug' => self::slug($row['name']),
                    'bill_count' => (int) $row['bill_count'],
                ];
            }

            /*
             * Four cities share a name with a county (Fairfax, Franklin, Richmond, and Roanoke), so
             * they're displayed as "City of Fairfax," etc.
             */
            $names = array_column($places, 'name');
            foreach ($places as &$place) {
                $place['display_name'] = $place['name'];
                if ($place['type'] === 'city' && in_array($place['name'] . ' County', $names, true)) {
                    $place['display_name'] = 'City of ' . $place['name'];
                }
            }
            unset($place);

            return $places;
        });
    }

    /**
     * Find a county or independent city by its slug.
     *
     * @return array{id: int, name: string, display_name: string, type: string, slug: string, bill_count: int}|false
     */
    public function find(string $slug): array|false
    {
        foreach ($this->listAll() as $place) {
            if ($place['slug'] === $slug) {
                return $place;
            }
        }
        return false;
    }

    /**
     * Find a county or independent city by its name in the gazetteer (which is how bills_places
     * names it). Towns and other places aren't found.
     *
     * @return array{id: int, name: string, display_name: string, type: string, slug: string, bill_count: int}|false
     */
    public function findByName(string $name): array|false
    {
        foreach ($this->listAll() as $place) {
            if ($place['name'] === $name) {
                return $place;
            }
        }
        return false;
    }

    /**
     * List the districts that represent a locality, by comparing their boundaries. "share" is how
     * much of the locality each one covers, and "district_share" is how much of the district is in
     * the locality (less than all of it, when the district includes parts of neighboring places).
     *
     * @return array<int, array{district_id: int, chamber: string, number: int, share: float, district_share: float}>
     */
    public function districts(int $gazetteer_id): array
    {
        return $this->cached('places-districts-v2-' . $gazetteer_id, self::CACHE_DISTRICTS,
            function () use ($gazetteer_id) {
                $sql = 'SELECT district_id, chamber, number, overlap / place_area AS share,
                            overlap / district_area AS district_share
                        FROM (
                            SELECT
                                districts.id AS district_id,
                                districts.chamber,
                                districts.number,
                                ST_Area(ST_Intersection(gazetteer.boundaries, districts.geom)) AS overlap,
                                ST_Area(gazetteer.boundaries) AS place_area,
                                ST_Area(districts.geom) AS district_area
                            FROM gazetteer
                            JOIN (
                                SELECT id, chamber, number,
                                    ST_GeomFromGeoJSON(JSON_EXTRACT(boundaries, "$.features[0].geometry")) AS geom
                                FROM districts
                                WHERE date_ended IS NULL AND boundaries IS NOT NULL
                            ) AS districts
                                ON MBRIntersects(gazetteer.boundaries, districts.geom)
                                AND ST_Intersects(gazetteer.boundaries, districts.geom)
                            WHERE gazetteer.id = :id AND ' . self::LOCALITY_SQL . '
                        ) AS overlaps
                        WHERE overlap / place_area >= :min_share OR overlap / district_area >= :min_share2
                        ORDER BY chamber ASC, number ASC';
                $stmt = $this->db()->prepare($sql);
                $stmt->bindValue(':id', $gazetteer_id, PDO::PARAM_INT);
                $stmt->bindValue(':min_share', self::MIN_SHARE);
                $stmt->bindValue(':min_share2', self::MIN_SHARE);
                $stmt->execute();

                $districts = [];
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $districts[] = [
                        'district_id' => (int) $row['district_id'],
                        'chamber' => $row['chamber'],
                        'number' => (int) $row['number'],
                        'share' => (float) $row['share'],
                        'district_share' => (float) $row['district_share'],
                    ];
                }
                return $districts;
            });
    }

    /**
     * The shapes to draw on a locality's map. Districts are drawn in full (as far as the map can be
     * panned), so that it's clear when they continue past the locality, with everything outside the
     * locality faded by a mask. All of the shapes are lists of polygons (see PlaceMap), simplified
     * so that the largest counties stay small enough to cache and to send to the browser.
     *
     * @return array{
     *     place: array,
     *     view: array{0: float, 1: float, 2: float, 3: float}|null,
     *     mask: array,
     *     districts: array<int, array{shape: array, label: array{0: float, 1: float}|null}>
     * }
     */
    public function mapData(int $gazetteer_id): array
    {
        return $this->cached('places-map-v3-' . $gazetteer_id, self::CACHE_DISTRICTS,
            function () use ($gazetteer_id) {
                $stmt = $this->db()->prepare('SELECT ST_AsGeoJSON(boundaries, 5) FROM gazetteer WHERE id = ?');
                $stmt->execute([$gazetteer_id]);
                $place = PlaceMap::polygons(json_decode((string) $stmt->fetchColumn(), true));
                $bounds = PlaceMap::bounds($place);
                if ($bounds === null) {
                    return ['place' => [], 'view' => null, 'mask' => [], 'districts' => []];
                }

                // Keep detail down to about a thousandth of the place's width, which is plenty when
                // the place fills a map, and ignore specks smaller than 0.2% of the place.
                $size = max($bounds[2] - $bounds[0], $bounds[3] - $bounds[1]);
                $tolerance = $size / 1000;
                $place_area = array_sum(array_map(fn ($polygon) => PlaceMap::area($polygon[0]), $place));
                $min_area = $place_area * 0.002;

                // The map can be panned and zoomed out only this far past the place. Districts are
                // cropped to a slightly bigger box, so that their cropped edges are never in view.
                $view = PlaceMap::expand($bounds, $size * 0.75);
                $crop = PlaceMap::expand($view, $size * 0.1);
                $crop_wkt = sprintf(
                    'POLYGON((%1$F %2$F, %3$F %2$F, %3$F %4$F, %1$F %4$F, %1$F %2$F))',
                    $crop[0], $crop[1], $crop[2], $crop[3]
                );

                $ids = array_column($this->districts($gazetteer_id), 'district_id');
                $districts = [];
                if (count($ids) > 0) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $stmt = $this->db()->prepare(
                        'SELECT districts.id,
                            ST_AsGeoJSON(ST_Intersection(districts.geom, ST_GeomFromText(?)), 5) AS shape,
                            ST_AsGeoJSON(ST_Intersection(gazetteer.boundaries, districts.geom), 5) AS inside
                        FROM gazetteer
                        JOIN (
                            SELECT id, ST_GeomFromGeoJSON(JSON_EXTRACT(boundaries, "$.features[0].geometry")) AS geom
                            FROM districts
                            WHERE id IN (' . $placeholders . ')
                        ) AS districts
                        WHERE gazetteer.id = ?'
                    );
                    $stmt->execute([$crop_wkt, ...$ids, $gazetteer_id]);
                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $shape = PlaceMap::clean(
                            PlaceMap::polygons(json_decode((string) $row['shape'], true)),
                            $tolerance,
                            $min_area
                        );
                        if (count($shape) === 0) {
                            continue;
                        }

                        // The district's label goes in the part of it that's in the place.
                        $inside = PlaceMap::clean(
                            PlaceMap::polygons(json_decode((string) $row['inside'], true)),
                            $tolerance,
                            $min_area
                        );
                        $districts[(int) $row['id']] = [
                            'shape' => $shape,
                            'label' => count($inside) > 0 ? PlaceMap::labelPoint($inside) : null,
                        ];
                    }
                }

                $place = PlaceMap::clean($place, $tolerance / 4, 0.0);
                return [
                    'place' => $place,
                    'view' => $view,
                    'mask' => PlaceMap::mask($place, $crop),
                    'districts' => $districts,
                ];
            });
    }

    /**
     * List a locality's current legislators: delegates, then senators, each by district number.
     * Each one is marked as representing all of the locality or part of it.
     *
     * @return array<int, array<string, mixed>>
     */
    public function legislators(int $gazetteer_id): array
    {
        $districts = $this->districts($gazetteer_id);
        if (count($districts) === 0) {
            return [];
        }

        $shares = [];
        $district_shares = [];
        foreach ($districts as $district) {
            $shares[$district['district_id']] = $district['share'];
            $district_shares[$district['district_id']] = $district['district_share'];
        }

        /*
         * Some districts have more than one term without an end date (when a legislator's
         * replacement has been added before the old term was closed), so take the term that started
         * most recently in each district.
         */
        $placeholders = implode(',', array_fill(0, count($shares), '?'));
        $sql = 'SELECT
                    people.shortname,
                    people.name,
                    terms.name_formatted,
                    terms.chamber,
                    terms.party,
                    terms.place,
                    terms.district_id,
                    districts.number AS district
                FROM terms
                JOIN people
                    ON people.id = terms.person_id
                JOIN districts
                    ON districts.id = terms.district_id
                WHERE
                    terms.district_id IN (' . $placeholders . ')
                    AND terms.date_started <= CURDATE()
                    AND (terms.date_ended IS NULL OR terms.date_ended > CURDATE())
                ORDER BY terms.date_started DESC';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(array_keys($shares));

        $legislators = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $district_id = (int) $row['district_id'];
            if (isset($legislators[$district_id])) {
                continue;
            }
            $row['district'] = (int) $row['district'];
            $row['whole'] = $shares[$district_id] >= self::WHOLE_SHARE;
            // Whether the district also includes parts of neighboring places.
            $row['extends'] = $district_shares[$district_id] < self::WHOLE_SHARE;
            $legislators[$district_id] = $row;
        }

        usort($legislators, function ($a, $b) {
            return [$a['chamber'], $a['district']] <=> [$b['chamber'], $b['district']];
        });
        return $legislators;
    }

    /**
     * List the bills that name a locality, from every year that we have, grouped by session year,
     * newest year first, and by bill number within each year.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function bills(string $name): array
    {
        return $this->cached('places-bills-v2-' . self::slug($name), self::CACHE_BILLS,
            function () use ($name) {
                $sql = 'SELECT DISTINCT
                            bills.number,
                            bills.catch_line,
                            bills.status,
                            sessions.year,
                            sessions.suffix,
                            people.name_formal AS patron,
                            people.shortname AS patron_shortname
                        FROM bills_places
                        JOIN bills
                            ON bills.id = bills_places.bill_id
                        JOIN sessions
                            ON sessions.id = bills.session_id
                        LEFT JOIN people
                            ON people.id = bills.chief_patron_id
                        WHERE
                            bills_places.placename = :name
                        ORDER BY
                            sessions.year DESC,
                            SUBSTRING(bills.number FROM 1 FOR 2) ASC,
                            CAST(SUBSTRING(bills.number FROM 3) AS UNSIGNED) ASC';
                $stmt = $this->db()->prepare($sql);
                $stmt->bindValue(':name', $name, PDO::PARAM_STR);
                $stmt->execute();

                $bills = [];
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $bill) {
                    $bills[(int) $bill['year']][] = $bill;
                }
                return $bills;
            });
    }

    /**
     * List the counties and cities that a district represents, alphabetically.
     *
     * @return array<int, array{name: string, slug: string, whole: bool}>
     */
    public function forDistrict(int $district_id): array
    {
        return $this->cached('places-for-district-' . $district_id, self::CACHE_DISTRICTS,
            function () use ($district_id) {
                $sql = 'SELECT name, overlap / place_area AS share
                        FROM (
                            SELECT
                                gazetteer.name,
                                ST_Area(ST_Intersection(gazetteer.boundaries, districts.geom)) AS overlap,
                                ST_Area(gazetteer.boundaries) AS place_area,
                                ST_Area(districts.geom) AS district_area
                            FROM (
                                SELECT ST_GeomFromGeoJSON(JSON_EXTRACT(boundaries, "$.features[0].geometry")) AS geom
                                FROM districts
                                WHERE id = :id AND date_ended IS NULL AND boundaries IS NOT NULL
                            ) AS districts
                            JOIN gazetteer
                                ON MBRIntersects(gazetteer.boundaries, districts.geom)
                                AND ST_Intersects(gazetteer.boundaries, districts.geom)
                            WHERE ' . self::LOCALITY_SQL . '
                        ) AS overlaps
                        WHERE overlap / place_area >= :min_share OR overlap / district_area >= :min_share2
                        ORDER BY name ASC';
                $stmt = $this->db()->prepare($sql);
                $stmt->bindValue(':id', $district_id, PDO::PARAM_INT);
                $stmt->bindValue(':min_share', self::MIN_SHARE);
                $stmt->bindValue(':min_share2', self::MIN_SHARE);
                $stmt->execute();

                $display_names = array_column($this->listAll(), 'display_name', 'name');
                $places = [];
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $places[] = [
                        'name' => $display_names[$row['name']] ?? $row['name'],
                        'slug' => self::slug($row['name']),
                        'whole' => (float) $row['share'] >= self::WHOLE_SHARE,
                    ];
                }
                return $places;
            });
    }
}
