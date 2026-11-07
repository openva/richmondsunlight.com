<?php

/**
 * Places
 *
 * With no place specified, lists every Virginia county and independent city. With one, lists that
 * locality's legislators and the bills that have named it.
 */

/*
 * INCLUDES
 * Include any files or libraries that are necessary for this specific page to function.
 */
include_once 'settings.inc.php';
include_once 'vendor/autoload.php';

/*
 * DECLARATIVE FUNCTIONS
 * Run those functions that are necessary prior to loading this specific page.
 */
$database = new Database();
$db = $database->connect();

/*
 * Initialize the session.
 */
resume_session();

$places = new Places($db);

$page_body = '';
$html_head = '';
$site_section = 'bills';

/*
 * Old URLs used the place's name, with "+" for spaces (e.g., /places/fairfax+county/). Send those
 * to the place's slug.
 */
$requested = $_GET['place'] ?? '';
$requested = is_string($requested) ? $requested : '';
if ($requested !== '' && $requested !== Places::slug($requested)) {
    header('Location: /places/' . Places::slug($requested) . '/', true, 301);
    exit;
}

/*
 * A specific place.
 */
if ($requested !== '') {
    $place = $places->find($requested);
    if ($place === false) {
        http_response_code(404);
        include '404.php';
        exit;
    }

    $name = $place['display_name'];
    // "The City of Richmond is represented by...," but "Henrico County is represented by..."
    $name_in_sentence = str_starts_with($name, 'City of ') ? 'the ' . $name : $name;
    $legislators = $places->legislators($place['id']);
    $bills = $places->bills($place['name']);
    $bill_count = array_sum(array_map('count', $bills));

    $delegates = array_values(array_filter($legislators, fn ($l) => $l['chamber'] === 'house'));
    $senators = array_values(array_filter($legislators, fn ($l) => $l['chamber'] === 'senate'));

    $page_title = $name . ' Legislation and Legislators';
    $canonical = 'https://www.richmondsunlight.com/places/' . $place['slug'] . '/';

    /*
     * A sentence or two about the place, for people and for search engines.
     */
    $count = function (int $n, string $one, string $many): string {
        $words = ['no', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine'];
        return ($words[$n] ?? number_format($n)) . ' ' . ($n === 1 ? $one : $many);
    };
    $intro = $name_in_sentence . ' is represented in the General Assembly by '
        . $count(count($delegates), 'delegate', 'delegates') . ' and '
        . $count(count($senators), 'senator', 'senators') . '. ';
    if ($bill_count > 0) {
        // Our records go back as far as the oldest bill that names the place.
        $intro .= 'Since ' . min(array_keys($bills)) . ', ' . $count($bill_count, 'bill has', 'bills have')
            . ' named ' . $name_in_sentence . '.';
    } else {
        $intro .= 'No bills have named ' . $name_in_sentence . '.';
    }
    $intro = ucfirst($intro);

    $html_head .= '
        <meta name="description" content="' . htmlspecialchars($intro) . '">
        <link rel="canonical" href="' . $canonical . '">
        <script type="application/ld+json">' . json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'AdministrativeArea',
            'name' => $name . ', Virginia',
            'url' => $canonical,
            'containedInPlace' => ['@type' => 'State', 'name' => 'Virginia'],
        ], JSON_UNESCAPED_SLASHES) . '</script>';

    $page_body .= '<p class="place-intro">' . htmlspecialchars($intro) . '</p>';

    /*
     * Legislators, in one list: delegates, then senators. Each one's title ("Del.," "Sen.") and
     * color (blue for the House, red for the Senate, as on the map) say which chamber they're in.
     */
    if (count($legislators) > 0) {
        $page_body .= '<h2>Legislators</h2><ul class="place-legislators">';
        foreach (array_merge($delegates, $senators) as $legislator) {
            $page_body .= '
                <li class="' . $legislator['chamber'] . '">
                    <a href="/legislator/' . $legislator['shortname'] . '/">
                        <img src="/images/legislators/thumbnails/' . $legislator['shortname'] . '.jpg"
                            alt="" width="60" loading="lazy">
                        <span class="place-legislator-name">'
                            . htmlspecialchars($legislator['name_formatted']) . '</span>
                    </a>
                    <span class="place-legislator-district">District ' . $legislator['district']
                        . ' · ' . ($legislator['whole'] ? 'All' : 'Part') . ' of the '
                        . $place['type'] . '</span>
                </li>';
        }
        $page_body .= '</ul>';
    }

    /*
     * The districts, in words: which districts the place is in, and whether they reach beyond it.
     * A small city's districts can be much bigger than the city, and that's easy to miss on a map
     * that's centered on the city.
     */
    $district_name = fn (array $legislator): string =>
        ($legislator['chamber'] === 'house' ? 'House' : 'Senate') . ' District ' . $legislator['district'];
    $chamber_clause = function (array $legislators, string $chamber) use ($count, $district_name): string {
        return count($legislators) === 1 ? $district_name($legislators[0])
            : $count(count($legislators), $chamber . ' district', $chamber . ' districts');
    };
    $districts_summary = '';
    if (count($delegates) > 0 && count($senators) > 0) {
        $house = $chamber_clause($delegates, 'House');
        $senate = $chamber_clause($senators, 'Senate');
        if (count($delegates) === 1 && count($senators) === 1) {
            $districts_summary = 'All of ' . $name_in_sentence . ' is in ' . $house . ' and ' . $senate . '.';
        } elseif (count($delegates) > 1 && count($senators) > 1) {
            $districts_summary = $name_in_sentence . ' is divided among ' . $house . ' and ' . $senate . '.';
        } elseif (count($delegates) === 1) {
            $districts_summary = 'All of ' . $name_in_sentence . ' is in ' . $house
                . ', and it’s divided among ' . $senate . '.';
        } else {
            $districts_summary = 'All of ' . $name_in_sentence . ' is in ' . $senate
                . ', and it’s divided among ' . $house . '.';
        }

        $extending = array_values(array_filter($legislators, fn ($l) => $l['extends']));
        if (count($extending) === 1) {
            $districts_summary .= ' ' . $district_name($extending[0])
                . ' also includes parts of neighboring localities.';
        } elseif (count($extending) === 2 && count($legislators) === 2) {
            $districts_summary .= ' Both districts also include parts of neighboring localities.';
        } elseif (count($extending) > 0 && count($extending) === count($legislators)) {
            $districts_summary .= ' All of these districts also include parts of neighboring localities.';
        } elseif (count($extending) > 0) {
            $districts_summary .= ' ' . ucfirst($count(count($extending), 'of', 'of'))
                . ' these districts also include parts of neighboring localities.';
        }
        $districts_summary = ucfirst($districts_summary);

        $page_body .= '
            <h2>Districts</h2>
            <p class="place-districts-summary">' . htmlspecialchars($districts_summary) . '</p>';
    }

    /*
     * A map of the place and of every district that represents it. House and Senate districts
     * overlap, so they're translucent: blue for the House, red for the Senate. Districts are drawn in
     * full, and everything outside the place is faded, so that it's clear when a district continues
     * past the place. Without a Mapbox token (as in development), there's no map.
     */
    $map_data = $places->mapData($place['id']);
    $bounds = PlaceMap::bounds($map_data['place']);
    if (MAPBOX_TOKEN !== '' && $bounds !== null && $districts_summary !== '') {
        $features = [
            PlaceMap::feature($map_data['place'], ['kind' => 'place']),
            PlaceMap::feature($map_data['mask'], ['kind' => 'mask']),
        ];
        $labels = [];
        foreach ($legislators as $legislator) {
            $district = $map_data['districts'][$legislator['district_id']] ?? null;
            if ($district === null) {
                continue;
            }
            $properties = [
                'kind' => 'district',
                'chamber' => $legislator['chamber'],
                'label' => ($legislator['chamber'] === 'house' ? 'House ' : 'Senate ') . $legislator['district'],
                'legislator' => $legislator['name_formatted'],
                'url' => '/legislator/' . $legislator['shortname'] . '/',
            ];
            $features[] = PlaceMap::feature($district['shape'], $properties);
            if ($district['label'] !== null) {
                $labels[] = PlaceMap::point($district['label'], $properties);
            }
        }

        $html_head .= '
        <script src="/js/vendor/mapbox-gl/dist/mapbox-gl.js"></script>
        <link href="/js/vendor/mapbox-gl/dist/mapbox-gl.css" rel="stylesheet">';

        $page_body .= '
            <ul class="place-map-legend">
                <li><span class="swatch house"></span> House districts</li>
                <li><span class="swatch senate"></span> Senate districts</li>
            </ul>
            <div id="place-map" style="height: 32em;" role="img" aria-label="' . htmlspecialchars('Map of ' . $name
                . ' and the House and Senate districts that represent it') . '"></div>
            <script>
                $(document).ready(function () {
                    mapboxgl.accessToken = ' . json_encode(MAPBOX_TOKEN) . ';
                    if (mapboxgl.config && typeof mapboxgl.config === "object") {
                        mapboxgl.config.EVENTS_URL = null;
                    }
                    if (typeof mapboxgl.setTelemetryEnabled === "function") {
                        mapboxgl.setTelemetryEnabled(false);
                    }

                    var colors = { house: "#1f5fbf", senate: "#c0392b" };
                    var shapes = ' . json_encode(['type' => 'FeatureCollection', 'features' => $features],
                        JSON_UNESCAPED_SLASHES) . ';
                    var labels = ' . json_encode(['type' => 'FeatureCollection', 'features' => $labels],
                        JSON_UNESCAPED_SLASHES) . ';

                    var map = new mapboxgl.Map({
                        container: "place-map",
                        style: "mapbox://styles/mapbox/light-v11",
                        bounds: ' . json_encode([[$bounds[0], $bounds[1]], [$bounds[2], $bounds[3]]]) . ',
                        fitBoundsOptions: { padding: 30 },
                        // Districts are drawn only this far past the place, so the map stops here.
                        maxBounds: ' . json_encode([
                            [$map_data['view'][0], $map_data['view'][1]],
                            [$map_data['view'][2], $map_data['view'][3]],
                        ]) . '
                    });
                    map.addControl(new mapboxgl.NavigationControl({ showCompass: false }));
                    map.scrollZoom.disable();
                    window.placeMap = map;

                    map.on("load", function () {
                        map.addSource("shapes", { type: "geojson", data: shapes });
                        map.addSource("labels", { type: "geojson", data: labels });

                        ["house", "senate"].forEach(function (chamber) {
                            var isDistrict = ["all", ["==", ["get", "kind"], "district"], ["==", ["get", "chamber"], chamber]];
                            map.addLayer({
                                id: "districts-" + chamber + "-fill",
                                type: "fill",
                                source: "shapes",
                                filter: isDistrict,
                                paint: { "fill-color": colors[chamber], "fill-opacity": 0.16 }
                            });
                            map.addLayer({
                                id: "districts-" + chamber + "-outline",
                                type: "line",
                                source: "shapes",
                                filter: isDistrict,
                                paint: {
                                    "line-color": colors[chamber],
                                    "line-width": 2,
                                    "line-opacity": 0.85,
                                    // Dashed, so that a Senate border can be told from a House border it overlaps.
                                    "line-dasharray": chamber === "senate" ? [3, 2] : [1, 0]
                                }
                            });
                        });

                        // Fade everything outside the place, so that it stands out, while showing that
                        // its districts continue past it.
                        map.addLayer({
                            id: "outside-mask",
                            type: "fill",
                            source: "shapes",
                            filter: ["==", ["get", "kind"], "mask"],
                            paint: { "fill-color": "#ffffff", "fill-opacity": 0.4 }
                        });

                        // An invisible fill, to tell whether a click is inside the place.
                        map.addLayer({
                            id: "place-fill",
                            type: "fill",
                            source: "shapes",
                            filter: ["==", ["get", "kind"], "place"],
                            paint: { "fill-color": "#000000", "fill-opacity": 0 }
                        });

                        map.addLayer({
                            id: "place-outline",
                            type: "line",
                            source: "shapes",
                            filter: ["==", ["get", "kind"], "place"],
                            paint: { "line-color": "#243a51", "line-width": 3.5 }
                        });

                        // Each district is labeled once, inside the place. When a House district and a
                        // Senate district share a label point, as when both cover all of a small city,
                        // the House label goes above the point and the Senate label below it. Labels
                        // try other sides of their points before giving way, so that where districts
                        // are crowded together, they move rather than pile up (and the rest appear
                        // when the map is zoomed in).
                        var anchors = {
                            house: ["bottom", "top", "left", "right", "bottom-left", "bottom-right", "top-left", "top-right"],
                            senate: ["top", "bottom", "right", "left", "top-right", "top-left", "bottom-right", "bottom-left"]
                        };
                        ["house", "senate"].forEach(function (chamber) {
                            map.addLayer({
                                id: "districts-" + chamber + "-label",
                                type: "symbol",
                                source: "labels",
                                filter: ["==", ["get", "chamber"], chamber],
                                layout: {
                                    "text-field": ["get", "label"],
                                    "text-size": 13,
                                    "text-variable-anchor": anchors[chamber],
                                    "text-radial-offset": 0.4,
                                    "text-justify": "auto",
                                    "text-padding": 1
                                },
                                paint: {
                                    "text-color": chamber === "house" ? "#103d82" : "#8a2418",
                                    "text-halo-color": "rgba(255, 255, 255, 0.95)",
                                    "text-halo-width": 2
                                }
                            });
                        });
                    });

                    // Clicking inside the place lists the districts at that point, each linking to
                    // its legislator. (Outside the place, only some of the districts there are shown.)
                    var fills = ["districts-house-fill", "districts-senate-fill"];
                    function districtsAt(point) {
                        if (map.queryRenderedFeatures(point, { layers: ["place-fill"] }).length === 0) {
                            return [];
                        }
                        return map.queryRenderedFeatures(point, { layers: fills });
                    }
                    map.on("click", function (e) {
                        var found = districtsAt(e.point);
                        if (found.length === 0) {
                            return;
                        }
                        var list = document.createElement("ul");
                        list.className = "place-map-popup";
                        var listed = {};
                        found.forEach(function (feature) {
                            // A shape that crosses map tiles is returned once for each of them.
                            if (listed[feature.properties.label]) {
                                return;
                            }
                            listed[feature.properties.label] = true;
                            var item = document.createElement("li");
                            var link = document.createElement("a");
                            link.href = feature.properties.url;
                            link.textContent = feature.properties.legislator;
                            item.className = feature.properties.chamber;
                            item.appendChild(document.createTextNode(feature.properties.label + ": "));
                            item.appendChild(link);
                            list.appendChild(item);
                        });
                        new mapboxgl.Popup({ closeButton: false }).setLngLat(e.lngLat).setDOMContent(list).addTo(map);
                    });
                    map.on("mousemove", function (e) {
                        map.getCanvas().style.cursor = districtsAt(e.point).length > 0 ? "pointer" : "";
                    });
                });
            </script>';
    }

    /*
     * Bills, with one tab for each session year.
     */
    $page_body .= '<h2>Bills</h2>';
    if ($bill_count === 0) {
        $page_body .= '<p>No bills have named ' . htmlspecialchars($name_in_sentence) . '.</p>';
    } else {
        $page_body .= '<div class="tabs" id="place-bills"><ul>';
        foreach ($bills as $year => $year_bills) {
            $page_body .= '<li><a href="#bills-' . $year . '">' . $year . ' ('
                . count($year_bills) . ')</a></li>';
        }
        $page_body .= '</ul>';

        foreach ($bills as $year => $year_bills) {
            $page_body .= '
                <div id="bills-' . $year . '">
                    <table class="bill-listing sortable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Title</th>
                                <th>Patron</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>';
            foreach ($year_bills as $bill) {
                $status = explain_status($bill['status']);
                $page_body .= '
                            <tr>
                                <td><a href="/bill/' . $bill['year'] . '/' . $bill['number'] . '/">'
                                    . mb_strtoupper($bill['number']) . '</a></td>
                                <td>' . htmlspecialchars(html_entity_decode((string) $bill['catch_line'], ENT_QUOTES)) . '</td>
                                <td>' . ($bill['patron_shortname'] ? '<a href="/legislator/'
                                    . $bill['patron_shortname'] . '/">' . htmlspecialchars($bill['patron']) . '</a>' : '') . '</td>
                                <td>' . htmlspecialchars(ucfirst((string) $status)) . '</td>
                            </tr>';
            }
            $page_body .= '
                        </tbody>
                    </table>
                </div>';
        }
        $page_body .= '</div>';
    }
}

/*
 * The list of all places.
 */
else {
    $page_title = 'Virginia Counties and Cities';
    $intro = 'The legislators who represent each of Virginia’s 95 counties and 38 independent '
        . 'cities, and the bills that have named them.';
    $html_head .= '
        <meta name="description" content="' . htmlspecialchars($intro) . '">
        <link rel="canonical" href="https://www.richmondsunlight.com/places/">';
    $page_body .= '<p class="place-intro">' . htmlspecialchars($intro) . '</p>';

    $all = $places->listAll();
    foreach (['county' => 'Counties', 'city' => 'Cities'] as $type => $heading) {
        $page_body .= '<h2>' . $heading . '</h2><ul class="place-list">';
        foreach ($all as $place) {
            if ($place['type'] !== $type) {
                continue;
            }
            $page_body .= '<li><a href="/places/' . $place['slug'] . '/">'
                . htmlspecialchars($place['display_name']) . '</a>'
                . ($place['bill_count'] > 0 ? ' <span class="place-bill-count">('
                    . number_format($place['bill_count']) . ' bill'
                    . ($place['bill_count'] === 1 ? '' : 's') . ')</span>' : '')
                . '</li>';
        }
        $page_body .= '</ul>';
    }
}

// OUTPUT THE PAGE
$page = new Page();
foreach ([
    'page_title',
    'page_body',
    'page_sidebar',
    'site_section',
    'browser_title',
    'html_head',
    'body_tag',
] as $prop) {
    if (isset(${$prop})) {
        $page->{$prop} = ${$prop};
    }
}
$page->process();
