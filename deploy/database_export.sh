#!/bin/bash

set -e

HOST="{PDO_SERVER}"
USERNAME="{PDO_USERNAME}"
PASSWORD="{PDO_PASSWORD}"

export MYSQL_PWD="$PASSWORD"

# All database tables that we want to export the structure of
STRUCTURE=(bills bills_copatrons bills_full_text bills_places bills_section_numbers bills_status bills_views blacklist chamber_status comments comments_subscriptions committees committee_members dashboard_bills dashboard_portfolios dashboard_user_data dashboard_watch_lists districts dockets files fiscal_impact_statements gazetteer meetings minutes polls people representatives representatives_votes sessions tags terms users vacode video_clips video_index video_index_faces video_transcript votes)

# All database tables that we want to export all contents of
ALL_CONTENTS=(committees committee_members districts files people representatives sessions terms)

# All database tables that we want to export some contents of, as test data
SOME_CONTENTS=(bills_copatrons bills_full_text bills_places bills_section_numbers bills_status bills_views comments dockets fiscal_impact_statements polls tags video_clips votes)

# The ID of the bills to use to generate test data
# Generate a new list with this query:
#   SELECT id
#   FROM bills
#   WHERE session_id = (SELECT id FROM sessions ORDER BY date_started DESC LIMIT 1,1)
#   ORDER BY interestingness DESC
#   LIMIT 20;
BILL_IDS=(77039 76873 77034 77430 77318 77399 76972 76995 76483 78711 78827 78195 78269 79224 77600 76777 77557 76924 76912 78905
)

# Change to the directory this script is in
cd "$(dirname "$0")"
mkdir -p mysql

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

# Remove the environment variable, now that we're done with it
unset MYSQL_PWD
