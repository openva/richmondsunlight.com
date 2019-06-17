#!/bin/bash -e

HOST="{PDO_SERVER}"
USERNAME="{PDO_USERNAME}"
PASSWORD="{PDO_PASSWORD}"

# All database tables that we want to export the structure of
STRUCTURE="bills bills_copatrons bills_full_text bills_places bills_section_numbers bills_status bills_views blacklist chamber_status comments comments_subscriptions committees committee_members dashboard_bills dashboard_portfolios dashboard_user_data dashboard_watch_lists districts dockets files gazetteer meetings minutes polls representatives representatives_districts representatives_fundraising representatives_terms representatives_votes sessions tags users vacode video_clips video_index video_index_faces video_transcript votes"

# All database tables that we want to export the contents of
ALL_CONTENTS="committees committee_members districts files representatives representatives_districts representatives_fundraising representatives_terms sessions"

# All database tables that we want to export some contents of, as test data
SOME_CONTENTS=(bills_copatrons bills_full_text bills_places bills_section_numbers bills_status bills_views comments dockets polls video_clips votes)

# The ID of the bill to use to generate test data
BILL_ID=46308

# Change to the directory this script is in
cd `dirname $0`
mkdir -p mysql

# Export all of the structural data
mysqldump {MYSQL_DATABASE} -d --routines --triggers -u "$USERNAME" -p"$PASSWORD" \
    --host "$HOST" $STRUCTURE > mysql/structure.sql

# Export all of the tables for which we want complete contents
mysqldump {MYSQL_DATABASE} --no-create-info -u "$USERNAME" -p"$PASSWORD" --host "$HOST" \
    $ALL_CONTENTS > mysql/basic-contents.sql

# Export selected contents from the remaining tables
mysqldump {MYSQL_DATABASE} --no-create-info -u "$USERNAME" -p"$PASSWORD" --host "$HOST" bills \
    --where "id=$BILL_ID" > mysql/test-records.sql 
for TABLE in ${SOME_CONTENTS[*]}
do
    # Genericize all IP addresses and email addresses, to maintain privacy.
    mysqldump {MYSQL_DATABASE} --no-create-info -u "$USERNAME" -p"$PASSWORD" --host "$HOST" "$TABLE" \
        --where "bill_id=$BILL_ID" |perl -pe 's{[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}}{ sprintf "%01d.%01d.%01d.%01d", int(255*rand()), int(255*rand()), int(255*rand()), int(255*rand()) }ge'
        |sed -E "s/\b[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}\b/example@example.com/g" \
        >> mysql/test-records.sql
done
