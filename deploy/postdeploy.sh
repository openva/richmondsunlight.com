#!/bin/bash

# Set permissions properly, since appspec.yml gets this wrong.
chown -R ricsun:web /vol/www/richmondsunlight.com/
chmod -R g+w /vol/www/richmondsunlight.com/

# Expire the cached template (in case we've made changes to it).
echo "delete template-new" | nc localhost 11211
