<?php

###
# Representatives Listing Page
#
# PURPOSE
# Lists all current representatives.
#
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once 'settings.inc.php';
include_once 'vendor/autoload.php';

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database();
$database->connect_mysqli();

# INITIALIZE SESSION
session_start();

# PAGE METADATA
$page_title = 'Legislators';
$site_section = 'legislators';
$html_head = '';

# PAGE SIDEBAR
$page_sidebar = '

	<div class="box">
		<h3>Explanation</h3>
		<p>There are 100 members of the House of Delegates and 40 members of the Senate. Each
		represents the people within a single district, and those districts are numbered
		sequentially. Every Virginian is in one House district and in one Senate district, and so is
		represented by one delegate and one senator.</p>

		<p><a href="/your-legislators/">Find out who represents you in the General Assembly!</a></p>

		<p>House members serve just two-year terms, and are reelected every November in odd-numbered
		years—2013, 2015, etc. Senate members serve four-year terms, reelected in 2011, 2015,
		etc.</p>
	</div>
';

# PAGE CONTENT

# Present the tab options.
$page_body = '
<div class="tabs" id="tab_group_one">
    <ul class="tabs">
        <li><a href="#names">Names</a></li>
        <li><a href="#districts">Districts</a></li>
    </ul>';

$page_body .= '<div id="names">';

# Select all delegates from the database.
$sql = 'SELECT shortname, name, party, place
		FROM representatives
		WHERE chamber="house"
		AND (date_ended IS NULL OR date_ended > now())
		ORDER BY name ASC';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0) {
    $page_body .= '
	<div class="left_side">
		<h2>House of Delegates</h2>
		<ul>';
    while ($legislator = mysqli_fetch_array($result)) {
        $legislator = array_map('stripslashes', $legislator);
        $page_body .= '<li><a href="/legislator/' . $legislator['shortname'] . '/">' . $legislator['name'] .
            ' (' . $legislator['party'] . '-' . $legislator['place'] . ')</a></li>';
    }
    $page_body .= '</ul>
		</div>';
}

# Select all senators from the database.
$sql = 'SELECT shortname, name, party, place
		FROM representatives
		WHERE chamber="senate"
		AND (date_ended IS NULL OR date_ended > now())
		ORDER BY name ASC';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0) {
    $page_body .= '<div class="right_side">
		<h2>Senate</h2>
		<ul>';
    while ($legislator = mysqli_fetch_array($result)) {
        $page_body .= '<li><a href="/legislator/' . $legislator['shortname'] . '/">' . $legislator['name'] .
            ' (' . $legislator['party'] . '-' . $legislator['place'] . ')</a></li>';
    }
    $page_body .= '</ul>
	</div>';
}

$page_body .= '</div>';

$page_body .= '<div id="districts">';

// List all districts and their boundaries
$sql = 'SELECT
            terms.name_formatted,
            people.shortname,
            terms.party,
            districts.boundaries,
            districts.chamber,
            districts.number
        FROM terms
        LEFT JOIN people
            ON terms.person_id=people.id
        LEFT JOIN districts
            ON terms.district_id=districts.id
        WHERE
            terms.date_ended IS NULL OR
            terms.date_ended > NOW()
        ORDER BY
            districts.chamber,
            districts.number';
$result = mysqli_query($GLOBALS['db'], $sql);
$districts = array(
    'house' => array(
        'type' => 'FeatureCollection',
        'features' => array()
    ),
    'senate' => array(
        'type' => 'FeatureCollection',
        'features' => array()
    )
);
if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        if (empty($row['boundaries']) || !isset($districts[$row['chamber']])) {
            continue;
        }

        $boundary = json_decode($row['boundaries'], true);
        if (!isset($boundary['features'][0])) {
            continue;
        }

        $feature = $boundary['features'][0];
        // Replace any existing properties with simpler identifying data.
        $feature['properties'] = array(
            'name' => $row['name_formatted'],
            'chamber' => $row['chamber'],
            'number' => $row['number'],
            'shortname' => $row['shortname'],
            'party' => $row['party']
        );

        $districts[$row['chamber']]['features'][] = $feature;
    }

    $html_head .= '<script src="/js/vendor/mapbox-gl/dist/mapbox-gl.js"></script>
    <link href="/js/vendor/mapbox-gl/dist/mapbox-gl.css" rel="stylesheet" />
    <script src="/js/vendor/@turf/turf/turf.min.js"></script>
    <style>
        #district_map { height: 300px; margin-top: 1em; }
        .map-toggle { margin-top: .5em; }
        .map-toggle button { margin-right: .25em; }
        .map-toggle button.active { background-color: #dccbaf; }
    </style>';

    $page_body .= '
    <div class="map-toggle">
        <button type="button" class="active" data-chamber="house">House</button>
        <button type="button" data-chamber="senate">Senate</button>
    </div>
    <div id="district_map"></div>
    <script type="text/javascript">
        $(function() {
            var districtData = ' . json_encode($districts) . ';
            var current = "house";
            var map;

            mapboxgl.accessToken = "' . MAPBOX_TOKEN . '";
            map = new mapboxgl.Map({
                container: "district_map",
                style: "mapbox://styles/mapbox/streets-v11",
                center: [-78.57,37.48],
                zoom: 7
            });
            map.addControl(new mapboxgl.NavigationControl());

            function fitToData() {
                var features = districtData[current].features;
                if (!features.length) {
                    return;
                }
                var bounds = new mapboxgl.LngLatBounds();
                features.forEach(function(feature) {
                    var bbox = turf.bbox(feature);
                    bounds.extend([bbox[0], bbox[1]]);
                    bounds.extend([bbox[2], bbox[3]]);
                });
                map.fitBounds(bounds, { padding: 20 });
            }

            map.on("load", function() {
                map.addSource("boundaries", {
                    "type": "geojson",
                    "data": districtData[current]
                });
                map.addLayer({
                    "id": "boundaries-fill",
                    "type": "fill",
                    "source": "boundaries",
                    "paint": {
                        "fill-color": [
                            "case",
                            ["==", ["get", "party"], "D"], "#0000ff",
                            ["==", ["get", "party"], "R"], "#ff0000",
                            "#00ff00"
                        ],
                        "fill-opacity": 0.2
                    }
                });
                map.addLayer({
                    "id": "boundaries",
                    "type": "line",
                    "source": "boundaries",
                    "layout": {
                        "line-join": "round",
                        "line-cap": "round"
                    },
                    "paint": {
                        "line-color": "#888",
                        "line-width": 3
                    }
                });

                fitToData();
            });

            $(".map-toggle button").on("click", function() {
                var chamber = $(this).data("chamber");
                if (chamber === current) {
                    return;
                }

                current = chamber;
                $(".map-toggle button").removeClass("active");
                $(this).addClass("active");

                var source = map.getSource("boundaries");
                if (source) {
                    source.setData(districtData[current]);
                    fitToData();
                }
            });

            $("#tab_group_one").tabs({
                activate: function(event, ui) {
                    if (ui.newPanel.attr("id") === "districts" && map) {
                        map.resize();
                        fitToData();
                    }
                }
                });
                var clickHandler = function(e) {
                    if (!e.features || !e.features.length) {
                        return;
                    }
                    var props = e.features[0].properties || {};
                    if (props.shortname) {
                        window.location = "/legislator/" + props.shortname + "/";
                    }
                };
                map.on("click", "boundaries", clickHandler);
                map.on("click", "boundaries-fill", clickHandler);
                var enterHandler = function() { map.getCanvas().style.cursor = "pointer"; };
                var leaveHandler = function() { map.getCanvas().style.cursor = ""; };
                map.on("mouseenter", "boundaries", enterHandler);
                map.on("mouseleave", "boundaries", leaveHandler);
                map.on("mouseenter", "boundaries-fill", enterHandler);
                map.on("mouseleave", "boundaries-fill", leaveHandler);

                map.on("mousemove", function(e) {
                    var features = map.queryRenderedFeatures(e.point, { layers: ["boundaries", "boundaries-fill"] });
                    map.getCanvas().style.cursor = features.length ? "pointer" : "";
                });
            });
    </script>';
}
$page_body .= '</div>'; // end #districts
$page_body .= '</div>'; // end #tab_group_one

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
