#!/bin/bash

aws s3 cp /vol/www/richmondsunlight.com/html/mirror/ s3://mirror.richmondsunlight.com --recursive --grants read=uri=http://acs.amazonaws.com/groups/global/AllUsers
cd /vol/www/richmondsunlight.com/html/mirror/ && rm -Rf ./*

