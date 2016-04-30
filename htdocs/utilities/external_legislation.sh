#!/bin/bash

cd /vol/www/richmondsunlight.com/html/downloads/bills/2016/pdf/

# Iterate through the files in this directory.
for f in *.pdf
do
	OUTPUT="$(pdftohtml -q -stdout $f |recode html..utf8 |egrep LEGISLATION.NOT.PREPARED)"
	if [[ $OUTPUT == *"PREPARED"* ]]; then
		# Echo the name of the file if it has this string.
		echo "$f"
	fi
done
