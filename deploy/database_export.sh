#!/bin/bash -e

HOST=""
USERNAME=""
PASSWORD=""

# All database tables that we want to export the structure of
STRUCTURE="bills bills_copatrons bills_full_text bills_places bills_section_numbers bills_status bills_views blacklist chamber_status comments comments_subscriptions committees committee_members dashboard_bills dashboard_portfolios dashboard_user_data dashboard_watch_lists districts dockets files gazetteer meetings minutes polls representatives representatives_districts representatives_fundraising representatives_terms representatives_votes sessions tags users vacode video_clips video_index video_index_faces video_transcript votes"

# All database tables that we want to export the contents of
ALL_CONTENTS="committees committee_members districts files representatives representatives_districts representatives_fundraising representatives_terms sessions"

# All database tables that we want to export some contents of
SOME_CONTENTS=(bills_copatrons bills_full_text bills_places bills_section_numbers bills_status bills_views comments dockets polls representatives_votes video_clips votes)

# Export all of the structural data that we want
mysqldump -d richmondsunlight --routines --triggers -u ricsun -p"$PASSWORD" --host "$HOST" "$STRUCTURE" > structure.sql
# The ID of the bill to use to generate test data
BILL_ID=46308

# Export all of the tables for which we want complete contents
mysqldump richmondsunlight --no-create-info -u ricsun -p"$PASSWORD" --host "$HOST" "$ALL_CONTENTS" > basic-contents.sql
# Export selected contents from the remaining tables
mysqldump richmondsunlight --no-create-info -u "$USERNAME" -p"$PASSWORD" --host "$HOST" bills --where "id=$BILL_ID" > test-records.sql 
for TABLE in ${SOME_CONTENTS[*]}
do
    mysqldump richmondsunlight --no-create-info -u "$USERNAME" -p"$PASSWORD" --host "$HOST" "$TABLE" --where "bill_id=$BILL_ID" >> test-records.sql
done
