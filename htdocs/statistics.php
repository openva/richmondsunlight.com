<?php

###
# Statistics
#
# PURPOSE
# Lists misc. statistics about bills.
#
###

# INCLUDES
# Include any files or libraries that are necessary for this specific page to function.
include_once 'includes/settings.inc.php';
include_once 'vendor/autoload.php';

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific page.
$database = new Database();
$database->connect_mysqli();

# PAGE METADATA
$page_title = 'Statistics';
$site_section = 'statistics';

$html_head = '<style>
    ol {
        list-style: decimal;
        margin-left: 2em;
    }
    .copatron-network {
        position: relative;
        border: 1px solid #dccbaf;
        border-radius: 4px;
        margin-bottom: 1.5em;
        overflow: hidden;
    }
    .copatron-network svg {
        display: block;
        width: 100%;
    }
    .copatron-tooltip {
        position: absolute;
        background: rgba(0,0,0,.8);
        color: #fff;
        padding: 4px 8px;
        border-radius: 3px;
        font-size: 12px;
        pointer-events: none;
        white-space: nowrap;
        display: none;
    }
    .copatron-network .node { cursor: pointer; }
    .copatron-network .node:hover { stroke-width: 3px; }
    .copatron-legend {
        font-size: 13px;
        color: #555;
        margin-bottom: .5em;
    }
    .copatron-legend span {
        display: inline-block;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        margin-right: 3px;
        vertical-align: middle;
    }
    .copatron-legend em {
        margin-left: 12px;
        font-style: italic;
        color: #888;
    }
</style>
<script src="/js/vendor/chart.js/dist/chart.umd.js"></script>
<script src="/js/vendor/d3/dist/d3.min.js"></script>';

# PAGE CONTENT
$page_body = '';
$page_sidebar = '';

$sql = 'SELECT
            DATE_FORMAT(date, "%M %d") AS d,
            COUNT(*) actions
        FROM bills_status
        WHERE
            date >= "' . SESSION_START . '" AND
            date <= "' . SESSION_END . '"
        GROUP BY d
        ORDER BY date ASC';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0) {
    $page_body = '<h2>Daily Bill Actions</h2>
        <p><a href="/bills/activity/">Actions are taken on bills each day</a>—they’re voted on,
        sent to committees, assessed, etc. Here is how many such actions were taken each day.</p>';
    $days = mysqli_fetch_all($result, MYSQLI_ASSOC);

    $labels = json_encode(array_column($days, 'd'));
    $data = json_encode(array_column($days, 'actions'));
    $page_body .=
    <<<EOD
<div>
  <canvas id="daily-bill-actions-chart"></canvas>
</div>

<script>
  const ctx1 = document.getElementById('daily-bill-actions-chart');

  new Chart(ctx1, {
    type: 'bar',
    data: {
      labels: $labels,
      datasets: [{
        label: '# of Actions',
        data: $data,
        backgroundColor: '#dccbaf',
        borderWidth: 1
      }]
    },
    options: {
      scales: {
        y: {
          beginAtZero: true
        }
      },
      plugins: {
        legend: {
            display: false
        }
      }
    }
  });
</script>
EOD;
}

$sql = 'SELECT
            DATE_FORMAT(date_introduced, "%M %d") AS date,
            COUNT(*) as number
        FROM bills
        WHERE
            session_id=' . SESSION_ID . '
        GROUP BY date_introduced
        ORDER BY date_introduced ASC';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0) {
    $page_body .= '<h2>Number of Bills Introduced Daily for ' . SESSION_YEAR . '</h2>';
    $days = mysqli_fetch_all($result, MYSQLI_ASSOC);

    $labels = json_encode(array_column($days, 'date'));
    $data = json_encode(array_column($days, 'number'));

    $page_body .=
    <<<EOD
<div>
  <canvas id="daily-bills-introduced-chart"></canvas>
</div>

<script>
  const ctx2 = document.getElementById('daily-bills-introduced-chart');

  new Chart(ctx2, {
    type: 'bar',
    data: {
      labels: $labels,
      datasets: [{
        label: '# of Bills',
        data: $data,
        backgroundColor: '#dccbaf',
        borderWidth: 1
      }]
    },
    options: {
      scales: {
        y: {
          beginAtZero: true
        }
      },
      plugins: {
        legend: {
            display: false
        }
      }
    }
  });
</script>
EOD;
}


$sql = 'SELECT
            representatives.name_formatted AS name,
            representatives.shortname,
            COUNT(*) AS number
        FROM representatives
        LEFT JOIN bills
            ON representatives.id=bills.chief_patron_id
        WHERE bills.session_id=' . SESSION_ID . '
        GROUP BY representatives.id
        ORDER BY number DESC, name ASC
        LIMIT 10';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0) {
    $page_body .= '<h2>Top 10 Bill Filers in ' . SESSION_YEAR . '</h2><ol>';
    $total = 0;
    while ($legislator = mysqli_fetch_assoc($result)) {
        $page_body .= '<li><a href="/legislator/' . $legislator['shortname'] . '/">'
            . $legislator['name'] . '</a>: ' . $legislator['number'] . ' bills</li>';
    }
    $page_body .= '</ol>';
}

$sql = 'SELECT
            bills.number,
            bills.catch_line,
            COUNT(*) AS views
        FROM bills_views
        LEFT JOIN bills
            ON bills_views.bill_id=bills.id
        WHERE bills.session_id= ' . SESSION_ID . '
        GROUP BY bills_views.bill_id
        ORDER BY views DESC
        LIMIT 10';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0) {
    $page_body .= '<h2>Top 10 Most-Viewed Bills for ' . SESSION_YEAR . '</h2><ol>';
    $total = 0;
    while ($bill = mysqli_fetch_assoc($result)) {
        $page_body .= '<li><a href="/bill/' . SESSION_YEAR . '/' . $bill['number'] . '/">'
            . strtoupper($bill['number']) . '</a>: ' . $bill['catch_line'] . '</li>';
    }
    $page_body .= '</ol>';
}

# COPATRONING NETWORK GRAPHS
$chambers = ['house' => 'House', 'senate' => 'Senate'];
foreach ($chambers as $chamber_key => $chamber_label) {
    $chamber_sql = mysqli_real_escape_string($GLOBALS['db'], $chamber_key);

    // Nodes: current legislators in this chamber
    $sql = 'SELECT people.id, people.name, people.shortname,
                   terms.name_formatted, terms.party
            FROM people
            JOIN terms ON people.id = terms.person_id
            WHERE terms.chamber = "' . $chamber_sql . '"
              AND terms.date_ended IS NULL';
    $result = mysqli_query($GLOBALS['db'], $sql);
    $nodes = [];
    $node_index = [];
    if ($result && mysqli_num_rows($result) > 0) {
        $i = 0;
        while ($row = mysqli_fetch_assoc($result)) {
            $nodes[] = $row;
            $node_index[$row['id']] = $i;
            $i++;
        }
    }

    // Edges: copatroning pairs within this chamber for the current session
    // bills_copatrons.legislator_id = terms.id (not person_id),
    // so join on terms.id and return person_id to match the node index.
    $sql = 'SELECT ta.person_id AS source, tb.person_id AS target, COUNT(*) AS weight
            FROM bills_copatrons a
            JOIN bills_copatrons b ON a.bill_id = b.bill_id AND a.legislator_id < b.legislator_id
            JOIN bills ON a.bill_id = bills.id
            JOIN terms ta ON a.legislator_id = ta.id
            JOIN terms tb ON b.legislator_id = tb.id
            WHERE bills.session_id = ' . SESSION_ID . '
              AND ta.chamber = "' . $chamber_sql . '"
              AND tb.chamber = "' . $chamber_sql . '"
            GROUP BY ta.person_id, tb.person_id
            HAVING weight >= 2
            ORDER BY weight DESC';
    $result = mysqli_query($GLOBALS['db'], $sql);
    $edges = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            // Map legislator IDs to node indices
            if (isset($node_index[$row['source']]) && isset($node_index[$row['target']])) {
                $edges[] = [
                    'source' => $node_index[$row['source']],
                    'target' => $node_index[$row['target']],
                    'weight' => (int)$row['weight'],
                ];
            }
        }
    }

    // Cap edges to keep graph readable
    $edge_cap = ($chamber_key === 'house') ? 400 : 200;
    if (count($edges) > $edge_cap) {
        $edges = array_slice($edges, 0, $edge_cap);
    }

    // Only render if we have nodes
    if (count($nodes) > 0) {
        $nodes_json = json_encode(array_map(function ($n) {
            $last_name = strstr($n['name'], ',', true) ?: $n['name'];
            return [
                'name' => $n['name_formatted'],
                'last_name' => $last_name,
                'shortname' => $n['shortname'],
                'party' => $n['party'],
            ];
        }, $nodes));
        $edges_json = json_encode($edges);
        $svg_height = ($chamber_key === 'house') ? 600 : 450;
        $container_id = 'copatron-' . $chamber_key;

        $page_body .= <<<EOD

<h2>{$chamber_label} Copatroning Network</h2>
<p>Legislators who copatron bills together are linked below. Thicker lines mean
more bills copatroned together. Drag to rearrange, scroll to zoom, hover for
names, click to visit a legislator's page.</p>
<div class="copatron-legend">
    <span style="background:#c0392b;"></span> Republican
    <span style="background:#2980b9; margin-left:8px;"></span> Democrat
    <span style="background:#7f8c8d; margin-left:8px;"></span> Independent
    <em>Drag nodes &middot; Scroll to zoom</em>
</div>
<div class="copatron-network" id="{$container_id}">
    <div class="copatron-tooltip" id="{$container_id}-tip"></div>
</div>

<script>
(function() {
    var nodes = {$nodes_json};
    var links = {$edges_json};
    if (nodes.length === 0) return;

    var container = document.getElementById('{$container_id}');
    var width = container.clientWidth || 800;
    var height = {$svg_height};

    var partyColor = { R: '#c0392b', D: '#2980b9', I: '#7f8c8d' };

    var svg = d3.select('#' + '{$container_id}')
        .append('svg')
        .attr('width', width)
        .attr('height', height)
        .attr('viewBox', '0 0 ' + width + ' ' + height);

    var g = svg.append('g');

    // Zoom
    svg.call(d3.zoom()
        .scaleExtent([0.3, 5])
        .on('zoom', function(event) { g.attr('transform', event.transform); })
    );

    // Weight scales for edge thickness and opacity
    var weights = links.map(function(l) { return l.weight; });
    var wMin = d3.min(weights) || 1, wMax = d3.max(weights) || 1;
    var strokeScale = d3.scaleLinear().domain([wMin, wMax]).range([0.5, 6]);
    var opacityScale = d3.scaleLinear().domain([wMin, wMax]).range([0.15, 0.85]);

    var simulation = d3.forceSimulation(nodes)
        .force('link', d3.forceLink(links)
            .distance(60)
            .strength(function(l) { return Math.min(l.weight / 10, 1); })
        )
        .force('charge', d3.forceManyBody().strength(-40))
        .force('x', d3.forceX(width / 2).strength(0.1))
        .force('y', d3.forceY(height / 2).strength(0.1))
        .force('collide', d3.forceCollide(10));

    var link = g.append('g')
        .selectAll('line')
        .data(links)
        .enter().append('line')
        .attr('stroke', '#555')
        .attr('stroke-opacity', function(d) { return opacityScale(d.weight); })
        .attr('stroke-width', function(d) { return strokeScale(d.weight); });

    var nodeGroup = g.append('g')
        .selectAll('g')
        .data(nodes)
        .enter().append('g')
        .attr('class', 'node')
        .call(d3.drag()
            .on('start', function(event, d) {
                if (!event.active) simulation.alphaTarget(0.3).restart();
                d.fx = d.x; d.fy = d.y;
            })
            .on('drag', function(event, d) {
                d.fx = event.x; d.fy = event.y;
            })
            .on('end', function(event, d) {
                if (!event.active) simulation.alphaTarget(0);
                d.fx = null; d.fy = null;
            })
        );

    nodeGroup.append('circle')
        .attr('r', 6)
        .attr('fill', function(d) { return partyColor[d.party] || '#7f8c8d'; })
        .attr('stroke', '#fff')
        .attr('stroke-width', 1.5);

    nodeGroup.append('text')
        .text(function(d) { return d.last_name; })
        .attr('dx', 8)
        .attr('dy', '.35em')
        .attr('font-size', '9px')
        .attr('fill', '#333')
        .attr('pointer-events', 'none');

    // Tooltip (shows full name on hover)
    var tip = document.getElementById('{$container_id}-tip');
    nodeGroup.on('mouseover', function(event, d) {
            tip.textContent = d.name;
            tip.style.display = 'block';
            tip.style.left = (event.offsetX + 10) + 'px';
            tip.style.top = (event.offsetY - 10) + 'px';
            d3.select(this).select('circle').attr('r', 9);
        })
        .on('mousemove', function(event) {
            tip.style.left = (event.offsetX + 10) + 'px';
            tip.style.top = (event.offsetY - 10) + 'px';
        })
        .on('mouseout', function() {
            tip.style.display = 'none';
            d3.select(this).select('circle').attr('r', 6);
        })
        .on('click', function(event, d) {
            window.location.href = '/legislator/' + d.shortname + '/';
        });

    simulation.on('tick', function() {
        link.attr('x1', function(d) { return d.source.x; })
            .attr('y1', function(d) { return d.source.y; })
            .attr('x2', function(d) { return d.target.x; })
            .attr('y2', function(d) { return d.target.y; });
        nodeGroup.attr('transform', function(d) { return 'translate(' + d.x + ',' + d.y + ')'; });
    });

    // After simulation settles, fit viewBox to actual node positions
    simulation.on('end', function() {
        var xs = nodes.map(function(n) { return n.x; });
        var ys = nodes.map(function(n) { return n.y; });
        var pad = 30;
        var minX = d3.min(xs) - pad, maxX = d3.max(xs) + pad;
        var minY = d3.min(ys) - pad, maxY = d3.max(ys) + pad;
        svg.attr('viewBox', minX + ' ' + minY + ' ' + (maxX - minX) + ' ' + (maxY - minY));
    });
})();
</script>
EOD;
    }
}

# SIDEBAR

# Select the total number of bills introduced in each chamber.
$sql = 'SELECT
            chamber,
            COUNT(*) AS count
        FROM bills
        WHERE
            session_id=' . SESSION_ID . '
        GROUP BY chamber';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0) {
    $page_sidebar .= '
                <div class="box">
                    <h3>By Chamber</h3>';
    while ($chamber = mysqli_fetch_array($result)) {
        if ($chamber['chamber'] == 'house') {
            $house['count'] = number_format($chamber['count']);
            $house['avg'] = round(($chamber['count'] / 100), 1);
        } elseif ($chamber['chamber'] == 'senate') {
            $senate['count'] = number_format($chamber['count']);
            $senate['avg'] = round(($chamber['count'] / 40), 1);
        }
    }

    $page_sidebar .= '
                    <strong>Senate</strong>
                    <ul>
                        <li>' . $senate['count'] . ' total bills</li>
                        <li>' . $senate['avg'] . ' bills per legislator</li>
                    </ul>
                    <strong>House</strong>
                    <ul>
                        <li>' . $house['count'] . ' total bills</li>
                        <li>' . $house['avg'] . ' bills per legislator</li>
                    </ul>';
    $page_sidebar .= '
                </div>';
}

# Select the total number of bills introduced in each chamber.
$sql = 'SELECT
            representatives.party,
            COUNT(*) AS count,
            (
                SELECT COUNT(*)
                FROM representatives
                WHERE party="D"
                AND date_ended IS NULL
            ) AS democrats_count,
            (
                SELECT COUNT(*)
                FROM representatives
                WHERE party="R"
                AND date_ended IS NULL
            ) AS republicans_count
        FROM bills
        LEFT JOIN representatives
            ON bills.chief_patron_id=representatives.id
        WHERE
            bills.session_id=' . SESSION_ID . '
        GROUP BY party';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0) {
    $page_sidebar .= '
			<div class="box">
				<h3>By Party</h3>';
    while ($party = mysqli_fetch_array($result)) {
        if ($party['party'] == 'R') {
            $republican['count'] = number_format($party['count']);
            $republican['avg'] = round(($party['count'] / $party['republicans_count']), 1);
        } elseif ($party['party'] == 'D') {
            $democratic['count'] = number_format($party['count']);
            $democratic['avg'] = round(($party['count'] / $party['democrats_count']), 1);
        }
    }

    $page_sidebar .= '
				<strong>Republican</strong>
				<ul>
					<li>' . $republican['count'] . ' total bills</li>
					<li>' . $republican['avg'] . ' bills per legislator</li>
				</ul>
				<strong>Democratic</strong>
				<ul>
					<li>' . $democratic['count'] . ' total bills</li>
					<li>' . $democratic['avg'] . ' bills per legislator</li>
				</ul>';
    $page_sidebar .= '
			</div>';
}

# Republican Tag Cloud
$sql = 'SELECT
            COUNT(*) AS count,
            tags.tag
        FROM tags
        LEFT JOIN bills
            ON tags.bill_id = bills.id
        LEFT JOIN representatives
            ON bills.chief_patron_id = representatives.id
        WHERE
            representatives.party = "R" AND
            bills.session_id = ' . SESSION_ID . '
        GROUP BY tags.tag
        HAVING count > 20
        ORDER BY tags.tag ASC';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0) {
    $page_sidebar .= '
		<a href="/help/tag-clouds/" class="help-icon-link" data-help-url="/help/tag-clouds/" title="Help"><img
            src="/images/help-beige.gif" class="help-icon" alt="?" /></a>

		<div class="box">
			<h3>Republican Tag Cloud</h3>
			<div class="tags">';
    while ($tag = mysqli_fetch_array($result)) {
        $tags[] = array_map('stripslashes', $tag);
    }
    for ($i = 0; $i < count($tags); $i++) {
        $font_size = round((log($tags[$i]['count']) / 3), 2);
        if ($font_size < '.75') {
            $font_size = '.75';
        }
        $page_sidebar .= '<span style="font-size: ' . $font_size . 'em;">
					<a href="/bills/tags/' . urlencode($tags[$i]['tag']) . '/">' . $tags[$i]['tag']
                    . '</a>
				</span>';
    }
    $page_sidebar .= '
			</div>
		</div>';
    unset($tags);
}

# Democratic Tag Cloud
$sql = 'SELECT
            COUNT(*) AS count,
            tags.tag
        FROM tags
        LEFT JOIN bills
            ON tags.bill_id = bills.id
        LEFT JOIN representatives
            ON bills.chief_patron_id = representatives.id
        WHERE
            representatives.party = "D" AND
            bills.session_id = ' . SESSION_ID . '
        GROUP BY tags.tag
        HAVING count > 20
        ORDER BY tags.tag ASC';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0) {
    $page_sidebar .= '
		<a href="/help/tag-clouds/" class="help-icon-link" data-help-url="/help/tag-clouds/" title="Help"><img
        src="/images/help-beige.gif" class="help-icon" alt="?" /></a>

		<div class="box">
			<h3>Democratic Tag Cloud</h3>
			<div class="tags">';
    while ($tag = mysqli_fetch_array($result)) {
        $tags[] = array_map('stripslashes', $tag);
    }
    for ($i = 0; $i < count($tags); $i++) {
        $font_size = round((log($tags[$i]['count']) / 3), 2);
        if ($font_size < '.75') {
            $font_size = '.75';
        }
        $page_sidebar .= '<span style="font-size: ' . $font_size . 'em;">
					<a href="/bills/tags/' . urlencode($tags[$i]['tag']) . '/">' . $tags[$i]['tag']
                    . '</a>
				</span>';
    }
    $page_sidebar .= '
			</div>
		</div>';
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
