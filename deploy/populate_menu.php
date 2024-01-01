<?php

/*
 * Define file names
 */
$template_file = 'htdocs/includes/templates/new.inc.php';
$legislator_menu_file = 'htdocs/includes/templates/legislators.html';

/*
 * Make sure the files exist
 */
if (!file_exists($template_file))
{
    echo 'Error: ' . $FILE_TO_EDIT . ' template is missing, cannot insert legislators menu.' . "\n";
    exit(1);
}
elseif (!file_exists($legislator_menu_file))
{
    echo 'Error: ' . $REPLACEMENT_FILE . ' is missing, cannot insert into template.' . "\n";
    exit(1);
}

/*
 * Get the contents of both files
 */
$legislator_menu = file_get_contents($legislator_menu_file);
$template = file_get_contents($template_file);

/*
 * Swap out the placeholder with the menu contents
 */
$template = str_replace('<!--legislator_menu-->', $legislator_menu, $template);

if (file_put_contents($template_file, $template) == false)
{
    echo 'Error: Failed in adding legislators listing to the menu.' . "\n";
    exit(1);
}

echo 'Successfully inserted list of legislators into the site template.' . "\n";
