<?php

/**
 * Generate Legislators Menu
 *
 * Query the database to generate a list of all legislators and emit the HTML that
 * is inserted into the site-wide navigation.
 */

$root = realpath(__DIR__ . '/../htdocs');
require $root . '/includes/settings.inc.php';
require $root . '/includes/class.Database.php';
require $root . '/includes/class.Legislator.php';
require $root . '/includes/vendor/autoload.php';

$database = new Database();
$database->connect_mysqli();

/*
 * Get a list of all current legislators.
 */
$legislator = new Legislator();
$legislator_list = $legislator->get_list('current');

$alphabet = range('A', 'Z');
$legislators = [
    'house' => array_fill_keys($alphabet, []),
    'senate' => array_fill_keys($alphabet, []),
];

/*
 * Build up an HTML-formatted array of legislators by chamber and first letter.
 */
foreach ($legislator_list as $legislator) {
    $letter = strtoupper(substr($legislator['name'], 0, 1));
    if (!in_array($letter, $alphabet, true)) {
        continue;
    }
    $link = '<a href="/legislator/' . $legislator['shortname'] . '/">' . $legislator['name_formatted'] . '</a>';
    $legislators[$legislator['chamber']][$letter][] = $link;
}

foreach ($legislators as &$chamber) {
    foreach ($chamber as &$by_letter) {
        if (!empty($by_letter)) {
            sort($by_letter, SORT_NATURAL | SORT_FLAG_CASE);
        }
    }
    unset($by_letter);
}
unset($chamber);

/*
 * Establish our alphabetical groupings. These letters mark where each submenu starts.
 */
$house_categories = ['A', 'D', 'I', 'M', 'S'];
$senate_categories = ['A', 'J', 'S'];

/**
 * Render the menu markup for a single chamber.
 */
function render_chamber_menu($title, array $categories, array $alphabet, array $legislators_by_letter)
{
    $categories = array_values(array_unique(array_map('strtoupper', $categories)));
    sort($categories);

    if (empty($categories)) {
        return '';
    }

    $segments = [];
    $segments[] = '    <li>' . $title . ' »';
    $segments[] = '        <ul class="alphabetic">';

    $pending_categories = $categories;
    $current_category = array_shift($pending_categories);
    $open_section = false;

    foreach ($alphabet as $letter) {
        if ($current_category === null && $open_section === false) {
            break;
        }

        if ($current_category !== null && $letter === $current_category) {
            if ($open_section) {
                $segments[] = '                </ul></li>';
            }
            $segments[] = '            <li>' . $letter . ' »';
            $segments[] = '                <ul class="legislators">';
            $open_section = true;
            $current_category = array_shift($pending_categories);
        } elseif ($open_section === false) {
            continue;
        }

        foreach ($legislators_by_letter[$letter] ?? [] as $legislator_link) {
            $segments[] = '                    <li>' . $legislator_link . '</li>';
        }
    }

    if ($open_section) {
        $segments[] = '                </ul></li>';
    }

    $segments[] = '        </ul>';
    $segments[] = '    </li>';

    return implode("\n", $segments);
}

$menu = [
    '<ul>',
    render_chamber_menu('House', $house_categories, $alphabet, $legislators['house']),
    render_chamber_menu('Senate', $senate_categories, $alphabet, $legislators['senate']),
    '</ul>',
];

echo implode("\n", array_filter($menu));
