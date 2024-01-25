<?php

###
# Index Page
#
# PURPOSE
# The front page of the site.
#
###

# INCLUDES
include_once 'settings.inc.php';
include_once 'vendor/autoload.php';

# INITIALIZE SESSION
session_start();

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database();
$database->connect_mysqli();

# PAGE METADATA
$page_title = 'Welcome to Richmond Sunlight';
$browser_title = 'Tracking the Virginia General Assembly';
$site_section = 'home';

# PAGE CONTENT
if (strtotime(SESSION_START) < time() && strtotime(SESSION_END) > time()) {
    $page_body = '<p>The ' . SESSION_YEAR . ' Virginia General Assembly session began on '
        . date('F j', strtotime(SESSION_START)) . ', and is scheduled to continue until '
        . date('F j', strtotime(SESSION_END)) . '. Here you can read <a href="/bills/">the '
        . 'bills are proposed</a>.</p>';
} elseif (strtotime(SESSION_START) > time()) {
    $page_body = '<p>The ' . SESSION_YEAR . ' Virginia General Assembly session will begin on '
        . date('F j', strtotime(SESSION_START)) . ', scheduled to continue until '
        . date('F j', strtotime(SESSION_END)) . '. Here you can read <a href="/bills/">the '
        . 'bills are proposed</a>.</p>';
} else {
    $page_body = '<p>The ' . SESSION_YEAR . ' Virginia General Assembly session began on '
        . date('F j', strtotime(SESSION_START)) . ' and continued through '
        . date('F j', strtotime(SESSION_END)) . '. Here you can read <a href="/bills/">the '
        . 'bills were proposed</a> and <a href="/bills/passed/">the bills that passed into '
        . 'law.</p>';
}

$sql = 'SELECT COUNT(*) AS count, tags.tag
		FROM tags
		LEFT JOIN bills
			ON tags.bill_id = bills.id
		WHERE bills.session_id=' . SESSION_ID . '
		GROUP BY tags.tag
		HAVING count > 10
		ORDER BY tag ASC';
$result = mysqli_query($GLOBALS['db'], $sql);
$tag_count = mysqli_num_rows($result);
if ($tag_count > 0) {
    $page_body .= '
	<h2>Bill Topics</h2>
	<div class="tags">';
    # Build up an array of tags, with the key being the tag and the value being the count.
    while ($tag = mysqli_fetch_array($result)) {
        $tag = array_map('stripslashes', $tag);
        $tags[$tag['tag']] = $tag['count'];
    }

    # Sort the tags in reverse order by key (their count), shave off the top 30, and then
    # resort alphabetically.
    arsort($tags);
    $tags = array_slice($tags, 0, 75);
    ksort($tags);

    # Establish a scale -- the average size in this list should be 1.25em, with the scale
    # moving up and down from there.
    $multiple = 1.25 / (array_sum($tags) / count($tags));

    foreach ($tags as $tag => $count) {
        $size = round(($count * $multiple), 1);
        if ($size > 4) {
            $size = 4;
        } elseif ($size < .75) {
            $size = .75;
        }

        $page_body .= '<span style="font-size: ' . $size . 'em;"><a href="/bills/tags/'
            . urlencode($tag) . '/">' . $tag . '</a></span> ';
    }
    $page_body .= '
	</div>';
}

# Show all bills, with a hotness greater than or equal to 10, that have recently hit progress
# milestones.
$sql = 'SELECT
			bills.number,
			bills.catch_line,
			bills.hotness,
			bills_status.status,
			bills_status.translation AS status_translation
		FROM bills_status
		LEFT JOIN bills
			ON bills_status.bill_id = bills.id
		WHERE bills.session_id =14
		AND (bills_status.translation = "passed house"
			OR bills_status.translation = "passed senate"
			OR bills_status.translation = "passed committee"
			OR bills_status.translation = "failed committee"
			OR bills_status.translation = "failed house"
			OR bills_status.translation = "failed senate")
		AND DATEDIFF( NOW( ) , bills_status.date ) <= 5
		AND interestingness >= 100
		ORDER BY DATE DESC';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0) {
    $page_body .= '<div id="updates">
					<h2>Interesting Bill Updates</h2>
					<table>';
    while ($bill = mysqli_fetch_array($result)) {
        $bill['url'] = '/bill/' . SESSION_YEAR . '/' . $bill['number'] . '/';
        $page_body .= '<tr>
						<td><a href="' . $bill['url'] . '" class="balloon">'
                            . mb_strtoupper($bill['number']) . '</td>
						<td>' . $bill['catch_line'] . '</td>
						<td>' . $bill['status_translation'] . '</td>
					</tr>';
    }
    $page_body .= '</table></div>';
}

# Legislative Map
$sql = 'SELECT
			bills.number,
			bills.catch_line,
			bills_places.longitude,
			bills_places.latitude,
			bills_places.placename
		FROM bills_places
		LEFT JOIN bills
			ON bills_places.bill_id=bills.id
		WHERE
			bills.session_id=' . SESSION_ID;
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0) {
    $places = [];
    while ($place = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
        $place_json = [];
        $place_json['latitude'] = $place['latitude'];
        $place_json['longitude'] = $place['longitude'];
        $place_json['place'] = $place['placename'];
        $place_json['description'] = strtoupper($place['number']) . ': ' . $place['catch_line'];
        $place_json['url'] = '/bill/' . SESSION_YEAR . '/' . $place['number'] . '/';
        $places[] = $place_json;
    }

    $geojson = [
        'type' => 'FeatureCollection',
        'features' => array_map(function ($item) {
            return [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float)$item['longitude'], (float)$item['latitude']]
                ],
                'properties' => [
                    'description' => $item['description'],
                    'url' => $item['url'],
                    'place' => $item['place']
                ]
            ];
        }, $places)
    ];
    $geojson = json_encode($geojson);

    $html_head .= '<script src="https://api.mapbox.com/mapbox-gl-js/v2.3.1/mapbox-gl.js"></script>
    <link href="https://api.mapbox.com/mapbox-gl-js/v2.3.1/mapbox-gl.css" rel="stylesheet" />';

    $page_body .= '<h2>Places Mentioned in Bills</h2>
	<script>
		
		$( document ).ready(function() {
			mapboxgl.accessToken = "' . MAPBOX_TOKEN . '";
			
			var markers = ' . $geojson . '

			var map = new mapboxgl.Map({
				container: "map",
				style: "mapbox://styles/mapbox/streets-v11",
				center: [-79.4,37.8],
				zoom: 5.4
			});

			function fanOutCoordinates(features, distance) {
				const groupedByCoordinates = {};
			
				// Group features by their coordinates
				features.forEach(feature => {
					const key = feature.geometry.coordinates.join(",");
					if (!groupedByCoordinates[key]) {
						groupedByCoordinates[key] = [];
					}
					groupedByCoordinates[key].push(feature);
				});
			
				// Adjust coordinates for each group
				Object.keys(groupedByCoordinates).forEach(key => {
					const group = groupedByCoordinates[key];
					if (group.length > 1) {
						group.forEach((feature, index) => {
							const angle = (2 * Math.PI / group.length) * index;
							const dx = distance * Math.cos(angle);
							const dy = distance * Math.sin(angle);
							feature.geometry.coordinates[0] += dx;
							feature.geometry.coordinates[1] += dy;
						});
					}
				});
			
				return Object.values(groupedByCoordinates).flat();
			}
			
			const adjustedFeatures = fanOutCoordinates(markers.features, 0.0001);
			markers.features = adjustedFeatures;

			map.on("load", function () {
                
				map.addSource("places", {
					"type": "geojson",
					"data": markers
				});

				map.addLayer({
					"id": "places",
					"type": "symbol",
					"source": "places",
					"layout": {
						"icon-image": "marker-15",
						"icon-allow-overlap": true
					}
				});

				var popup = new mapboxgl.Popup({
					closeButton: false,
					closeOnClick: false
				});
 
				map.on("mouseenter", "places", (e) => {

					// Change the cursor style as a UI indicator.
					map.getCanvas().style.cursor = "pointer";
					 
					// Copy coordinates array.
					const coordinates = e.features[0].geometry.coordinates.slice();
					const description = e.features[0].properties.description;
					 
					// Ensure that if the map is zoomed out such that multiple
					// copies of the feature are visible, the popup appears
					// over the copy being pointed to.
					while (Math.abs(e.lngLat.lng - coordinates[0]) > 180) {
						coordinates[0] += e.lngLat.lng > coordinates[0] ? 360 : -360;
					}
					 
					// Populate the popup and set its coordinates
					// based on the feature found.
					popup.setLngLat(coordinates).setHTML(description).addTo(map);
				});
					 
				map.on("mouseleave", "places", () => {
					map.getCanvas().style.cursor = "";
					popup.remove();
				});

				map.on("click", "places", function (e) {
					var url = e.features[0].properties.url;
					if (url) {
						window.location.href = url;
					}
				});

			});
		});
	</script>
	<div id="map" style="height:250px; width: 100%;"></div>';
}

# Newest Comments
$sql = 'SELECT
			comments.id,
			comments.bill_id,
			comments.date_created AS date,
			comments.name,
			comments.email,
			comments.url,
			comments.comment,
			comments.type,
			bills.number AS bill_number,
			bills.catch_line AS bill_catch_line,
			sessions.year,
			(
				SELECT COUNT(*)
				FROM comments
				WHERE bill_id=bills.id AND status="published"
				AND date_created <= date
			) AS number
		FROM comments
		LEFT JOIN bills
			ON bills.id=comments.bill_id
		LEFT JOIN sessions
			ON bills.session_id=sessions.id
		WHERE comments.status="published"
		ORDER BY comments.date_created DESC
		LIMIT 6';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0) {
    $page_body .= '
	<div id="newest-comments">
		<h2>Newest Comments</h2>';
    while ($comment = mysqli_fetch_array($result)) {
        $comment = array_map('stripslashes', $comment);
        if (mb_strlen($comment['comment']) > 200) {
            $comment['comment'] = preg_replace('#<blockquote>(.*)</blockquote>#D', '', $comment['comment']);
            $comment['comment'] = strip_tags($comment['comment']);
        }
        $page_body .= '<a href="/bill/' . $comment['year'] . '/' . $comment['bill_number']
            . '/#comment-' . $comment['number'] . '">
			<div><strong>' . $comment['bill_catch_line'] . '</strong><br />
			' . $comment['name'] . ' writes:
			' . $comment['comment'] . '</div></a>';
    }
    $page_body .= '
		</div>';
}

$page_sidebar = '';

# Session Stats
$sql = 'SELECT chamber, COUNT(*) AS count
		FROM bills
		WHERE session_id=' . SESSION_ID . '
		GROUP BY chamber';
$result = mysqli_query($GLOBALS['db'], $sql);
while ($stats = mysqli_fetch_array($result)) {
    if ($stats['chamber'] == 'house') {
        $session['house_count'] = $stats['count'];
    } elseif ($stats['chamber'] == 'senate') {
        $session['senate_count'] = $stats['count'];
    }
}

$page_sidebar .= '
	<h3>Total Bills Filed</h3>
	<div class="box stats" id="bills-left">
		<p id="house-bill-count">
			<a href="/bills/#house" style="text-decoration: none;">
			<span class="stat-number">' . number_format($session['house_count']) . '</span>
			</a>
		</p>

		<p id="senate-bill-count">
			<a href="/bills/#senate" style="text-decoration: none;">
			<span class="stat-number">' . number_format($session['senate_count']) . '</span>
			</a>
		</p>
	</div>';

# Most interesting bills
$sql = 'SELECT bills.number, bills.catch_line,
		DATE_FORMAT(bills.date_introduced, "%M %d, %Y") AS date_introduced,
		representatives.name AS patron, bills.status, bills.hotness
		FROM bills
		LEFT JOIN representatives
			ON bills.chief_patron_id = representatives.id
		WHERE bills.session_id = ' . SESSION_ID . '
		ORDER BY bills.hotness DESC
		LIMIT 5';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0) {
    $page_sidebar .= '
		<h3>Today’s Most Interesting Bills</h3>
		<div class="box" id="interesting">
			<ul>';
    while ($bill = mysqli_fetch_array($result)) {
        $bill = array_map('stripslashes', $bill);
        $page_sidebar .= '
			<li><a href="/bill/' . SESSION_YEAR . '/' . $bill['number'] . '/" class="balloon">'
                . mb_strtoupper($bill['number']) . balloon($bill, 'bill') . '</a>: '
                . $bill['catch_line'] . '</li>
		';
    }
    $page_sidebar .= '
			</ul>
		</div>';
}

# Newest Bills
if (LEGISLATIVE_SEASON == true) {
    $sql = 'SELECT
				bills.number,
				bills.catch_line,
				sessions.year,
				DATE_FORMAT(bills.date_introduced, "%M %d, %Y") AS date_introduced,
				representatives.name AS patron,
				(
					SELECT status
					FROM bills_status
					WHERE bill_id=bills.id
					ORDER BY date DESC, id DESC
					LIMIT 1
				) AS status
			FROM bills
			LEFT JOIN sessions
				ON bills.session_id=sessions.id
			LEFT JOIN representatives
				ON bills.chief_patron_id = representatives.id
			WHERE
				bills.date_introduced >= CURDATE() - INTERVAL 4 DAY
			ORDER BY
				bills.date_introduced DESC,
				bills.id DESC
			LIMIT 5';
    $result = mysqli_query($GLOBALS['db'], $sql);
    if (mysqli_num_rows($result) > 0) {
        $page_sidebar .= '
			<h3>Newest Bills</h3>
			<div class="box" id="newest">
				<ul>';
        while ($bill = mysqli_fetch_array($result)) {
            $bill = array_map('stripslashes', $bill);
            $bill['summary'] = mb_substr($bill['summary'], 0, 175) . '...';
            $page_sidebar .= '
				<li><a href="/bill/' . $bill['year'] . '/' . mb_strtolower($bill['number'])
                    . '/" class="balloon">' . mb_strtoupper($bill['number'])
                    . balloon($bill, 'bill') . '</a>: ' . $bill['catch_line'] . '</li>
			';
        }
        $page_sidebar .= '
				</ul>
			</div>';
    }
}

$html_head .= '
<script type="application/ld+json">
{
   "@context": "http://schema.org",
   "@type": "WebSite",
   "url": "https://www.richmondsunlight.com/",
   "potentialAction": {
     "@type": "SearchAction",
     "target": "https://www.richmondsunlight.com/search/?q={search_term_string}",
     "query-input": "required name=search_term_string"
   }
}
</script>';

$page = new Page();
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->html_head = $html_head;
$page->browser_title = $browser_title;
$page->assemble();
$page->display();
