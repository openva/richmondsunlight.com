#!/bin/bash
FILES="./search_index/*.json"
for f in $FILES
do
	echo "$f"
	curl -XPOST http://localhost:9200/_bulk --data-binary @"$f" -vn
  rm -f "$f"
done
