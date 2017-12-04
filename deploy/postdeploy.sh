#!/bin/bash

# Set permissions properly, since appspec.yml gets this wrong.
chown -R ricsun:web /vol/www/richmondsunlight.com/
chmod -R g+w /vol/www/richmondsunlight.com/
