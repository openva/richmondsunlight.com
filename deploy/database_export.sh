#!/bin/bash

set -e

HOST="{PDO_SERVER}"
USERNAME="{PDO_USERNAME}"
PASSWORD="{PDO_PASSWORD}"

export MYSQL_PWD="$PASSWORD"

# All database tables that we want to export the structure of
STRUCTURE=(bills bills_copatrons bills_full_text bills_places bills_section_numbers bills_status bills_status_narratives bills_views blacklist chamber_status comments comments_subscriptions committees committee_members dashboard_bills dashboard_portfolios dashboard_user_data dashboard_watch_lists districts dockets files fiscal_impact_statements gazetteer meetings minutes polls people representatives representatives_votes sessions tags terms users vacode video_clips video_index video_index_faces video_transcript votes)

# All database tables that we want to export all contents of
ALL_CONTENTS=(committees committee_members districts files people representatives sessions terms)

# All database tables that we want to export some contents of, as test data
SOME_CONTENTS=(bills_copatrons bills_full_text bills_places bills_section_numbers bills_status bills_status_narratives bills_views comments dockets fiscal_impact_statements polls tags video_clips votes)

# The file IDs of videos and minutes that we want to export all the data for.
VIDEO_CONTENTS=(14798)

# The ID of the bills to use to generate test data
# Generate a new list with this query:
#   SELECT id
#   FROM bills
#   WHERE session_id = (SELECT id FROM sessions ORDER BY date_started DESC LIMIT 1,1)
#    OR session_id = (SELECT id FROM sessions ORDER BY date_started DESC LIMIT 2,1)
#   ORDER BY interestingness DESC
#   LIMIT 80;

BILL_IDS=(
    77039 74613 72911 76873 74093 73894 73446 77034 72883 74728 73202 77430 72884 74117 73805 77318
    72954 73556 77399 72980 76972 76995 73362 74500 74148 76483 78711 78827 72932 73272 72955 78195
    73118 79224 77600 78269 74670 73984 73286 76777 73960 77557 75107 74616 74490 73030 76924 78905
    76912 73053 74513 77733 73112 78556 78552 76706 74071 74585 77462 74875 76741 79465 77771 79881
    78258 77302 77668 74452 78892 79024 78235 73480 79928 77053 73896 73230 72902 78115 79529 74045
)

# Change to the directory this script is in
cd "$(dirname "$0")"
mkdir -p mysql

# Remove any existing SQL files
rm -f mysql/*.sql

# Export the structural data
truncate --size 0 mysql/structure.sql
STRUCTURE_LIST=$(printf "%s " "${STRUCTURE[@]}")
mysqldump -d --routines --triggers -u "$USERNAME" \
    --host "$HOST" {MYSQL_DATABASE} $STRUCTURE_LIST > mysql/structure.sql

# Export the tables for which we want complete contents
truncate --size 0 mysql/basic-contents.sql
ALL_CONTENTS_LIST=$(printf "%s " "${ALL_CONTENTS[@]}")
mysqldump --no-create-info --skip-lock-tables -u "$USERNAME" \
    --host "$HOST" {MYSQL_DATABASE} $ALL_CONTENTS_LIST > mysql/basic-contents.sql

# Export selected contents from the remaining tables
truncate --size 0 mysql/test-records.sql
for BILL_ID in "${BILL_IDS[@]}"; do
    mysqldump {MYSQL_DATABASE} --no-create-info --skip-lock-tables -u "$USERNAME" \
        --host "$HOST" bills --where "id=$BILL_ID" >> mysql/test-records.sql
done

for TABLE in "${SOME_CONTENTS[@]}"; do
    for BILL_ID in "${BILL_IDS[@]}"; do
        # Genericize all IP addresses and email addresses, to maintain privacy.
        mysqldump {MYSQL_DATABASE} --no-create-info --skip-lock-tables \
            -u "$USERNAME" --host "$HOST" "$TABLE" \
            --where "bill_id=$BILL_ID" |perl -pe 's{[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}}{ sprintf "127.%01d.%01d.%01d", int(255*rand()), int(255*rand()), int(255*rand()) }ge' \
            |sed -E "s/\b[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}\b/example@example.com/g" \
            >> mysql/test-records.sql
    done
done

# Export video records
truncate --size 0 mysql/video-records.sql
for TABLE in video_index video_clips video_transcript; do
    for FILE_ID in "${VIDEO_CONTENTS[@]}"; do
        mysqldump {MYSQL_DATABASE} --no-create-info --skip-lock-tables \
            -u "$USERNAME" --host "$HOST" "$TABLE" \
            --where "file_id=$FILE_ID" >> mysql/video-records.sql
    done
done

# Remove the environment variable, now that we're done with it
unset MYSQL_PWD

# Combine some exports into a single file, which we want for some repos
cat mysql/structure.sql mysql/basic-contents.sql mysql/test-records.sql \
    > mysql/database.sql
