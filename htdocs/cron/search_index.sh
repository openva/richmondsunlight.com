#!/bin/bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
FILES="./search_index/*.json"
cd "$DIR" || exit
for f in $FILES
do
	echo "$f"
	curl -XPOST http://localhost:9200/_bulk --data-binary @"$f" -vn
	rm -f "$f"
done
