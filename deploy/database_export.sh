#!/bin/bash -e

HOST="{PDO_SERVER}"
USERNAME="{PDO_USERNAME}"
PASSWORD="{PDO_PASSWORD}"

# All database tables that we want to export the structure of
STRUCTURE=(bills bills_copatrons bills_full_text bills_places bills_section_numbers bills_status bills_views blacklist chamber_status comments comments_subscriptions committees committee_members dashboard_bills dashboard_portfolios dashboard_user_data dashboard_watch_lists districts dockets files gazetteer meetings minutes polls representatives representatives_districts representatives_fundraising representatives_terms representatives_votes sessions tags users vacode video_clips video_index video_index_faces video_transcript votes)

# All database tables that we want to export the contents of
ALL_CONTENTS=(committees committee_members districts files representatives representatives_districts representatives_fundraising representatives_terms sessions)

# All database tables that we want to export some contents of, as test data
SOME_CONTENTS=(bills_copatrons bills_full_text bills_places bills_section_numbers bills_status bills_views comments dockets polls video_clips votes)

# The ID of the bills to use to generate test data
BILL_IDS=(45618 45663 46308 46058 45113 44355 44599 45136 45453 45811)

# Change to the directory this script is in
cd "$(dirname "$0")"
mkdir -p mysql

# Export all of the structural data
truncate --size 0 mysql/structure.sql
for TABLE in ${STRUCTURE[*]}; do
    mysqldump {MYSQL_DATABASE} -d --routines --triggers -u "$USERNAME" -p"$PASSWORD" \
        --host "$HOST" --tables "$TABLE" >> mysql/structure.sql
done

# Export all of the tables for which we want complete contents
truncate --size 0 mysql/basic-contents.sql
for TABLE in ${ALL_CONTENTS[*]}; do
    mysqldump {MYSQL_DATABASE} --no-create-info -u "$USERNAME" -p"$PASSWORD" --host "$HOST" \
        --tables "$TABLE" >> mysql/basic-contents.sql
done

# Export selected contents from the remaining tables
truncate --size 0 mysql/test-records.sql
for BILL_ID in "${BILL_IDS[@]}"; do
    mysqldump {MYSQL_DATABASE} --no-create-info -u "$USERNAME" -p"$PASSWORD" --host "$HOST" bills \
        --where "id=$BILL_ID" >> mysql/test-records.sql
done

for TABLE in ${SOME_CONTENTS[*]}; do
    for BILL_ID in "${BILL_IDS[@]}"; do
        # Genericize all IP addresses and email addresses, to maintain privacy.
        mysqldump {MYSQL_DATABASE} --no-create-info -u "$USERNAME" -p"$PASSWORD" --host "$HOST" "$TABLE" \
            --where "bill_id=$BILL_ID" |perl -pe 's{[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}}{ sprintf "127.%01d.%01d.%01d", int(255*rand()), int(255*rand()), int(255*rand()) }ge' \
            |sed -E "s/\b[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}\b/example@example.com/g" \
            >> mysql/test-records.sql
    done
done