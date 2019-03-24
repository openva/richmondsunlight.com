#!/bin/bash -e

# All database tables that we want to export the structure of
STRUCTURE="bills bills_copatrons bills_full_text bills_places bills_section_numbers bills_status bills_views blacklist chamber_status comments comments_subscriptions committees committee_members dashboard_bills dashboard_portfolios dashboard_user_data dashboard_watch_lists districts dockets files gazetteer meetings minutes polls representatives representatives_districts representatives_fundraising representatives_terms representatives_votes sessions tags users vacode video_clips video_index video_index_faces video_transcript votes"

# All database tables that we want to export the contents of
ALL_CONTENTS="committees committee_members districts files representatives representatives_districts representatives_fundraising representatives_terms sessions"

# All database tables that we want to export some contents of
SOME_CONTENTS="bills bills_copatrons bills_full_text bills_places bills_section_numbers bills_status comments dockets meetings minutes polls representatives_votes tags video_clips video_index video_index_faces video_transcript votes"

# Export all of the structural data that we want
mysqldump -d richmondsun -p"$PASSWORD" --host "$HOST" "$STRUCTURE" > database.sql

# Append to the export all of the tables for which we want all contents
mysqldump richmondsun -u ricsun -p"$PASSWORD" --host "$HOST" "$ALL_CONTENTS" >> database.sql
