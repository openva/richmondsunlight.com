#!/bin/bash

# When deploying to the staging site, display all PHP errors.
sed -i -e 's/php_value display_errors Off/php_value display_errors On/g' htdocs/php.ini
sed -i -e 's/error_reporting = 7/error_reporting = E_ALL/g' htdocs/php.ini
