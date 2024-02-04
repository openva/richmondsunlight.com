#!/bin/bash -e

HOST="{PDO_SERVER}"
USERNAME="{PDO_USERNAME}"
PASSWORD="{PDO_PASSWORD}"

export MYSQL_PWD="$PASSWORD"

# All database tables that we want to export the structure of
STRUCTURE=(bills bills_copatrons bills_full_text bills_places bills_places_test bills_section_numbers bills_status bills_views blacklist chamber_status comments comments_subscriptions committees committee_members dashboard_bills dashboard_portfolios dashboard_user_data dashboard_watch_lists districts dockets files gazetteer lobbyists meetings minutes polls representatives representatives_districts representatives_terms representatives_votes sessions tags users vacode video_clips video_index video_index_faces video_transcript votes)

# All database tables that we want to export the contents of
ALL_CONTENTS=(committees committee_members districts files representatives representatives_districts sessions)

# All database tables that we want to export some contents of, as test data
SOME_CONTENTS=(bills_copatrons bills_full_text bills_places bills_section_numbers bills_status bills_views comments dockets polls tags video_clips votes)

# The ID of the bills to use to generate test data
BILL_IDS=(73202 73894 74728 74513 73272)

# Change to the directory this script is in
cd "$(dirname "$0")"
mkdir -p mysql

# Export the structural data
truncate --size 0 mysql/structure.sql
STRUCTURE_LIST=$(printf "%s " "${STRUCTURE[@]}")
mysqldump -d --routines --triggers --set-gtid-purged=OFF -u "$USERNAME" \
    --host "$HOST" {MYSQL_DATABASE} $STRUCTURE_LIST > mysql/structure.sql

# Export the tables for which we want complete contents
truncate --size 0 mysql/basic-contents.sql
ALL_CONTENTS_LIST=$(printf "%s " "${ALL_CONTENTS[@]}")
mysqldump --no-create-info --skip-lock-tables --set-gtid-purged=OFF -u "$USERNAME" \
    --host "$HOST" {MYSQL_DATABASE} $ALL_CONTENTS_LIST > mysql/basic-contents.sql

# Export selected contents from the remaining tables
truncate --size 0 mysql/test-records.sql
for BILL_ID in "${BILL_IDS[@]}"; do
    mysqldump {MYSQL_DATABASE} --no-create-info --skip-lock-tables -u "$USERNAME" \
        --set-gtid-purged=OFF --host "$HOST" bills --where "id=$BILL_ID" >> mysql/test-records.sql
done

for TABLE in ${SOME_CONTENTS[*]}; do
    for BILL_ID in "${BILL_IDS[@]}"; do
        # Genericize all IP addresses and email addresses, to maintain privacy.
        mysqldump {MYSQL_DATABASE} --no-create-info --skip-lock-tables --set-gtid-purged=OFF \
            -u "$USERNAME" --host "$HOST" "$TABLE" \
            --where "bill_id=$BILL_ID" |perl -pe 's{[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}}{ sprintf "127.%01d.%01d.%01d", int(255*rand()), int(255*rand()), int(255*rand()) }ge' \
            |sed -E "s/\b[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}\b/example@example.com/g" \
            >> mysql/test-records.sql
    done
done

# Remove the environment variable, now that we're done with it
unset MYSQL_PWD
