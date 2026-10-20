#!/bin/bash

set -e

# The database to export from: from the environment (as when rs-machine runs this), or else from
# the placeholders, which are filled in when this is deployed (see config_variables.sh).
HOST="${PDO_SERVER:-"{PDO_SERVER}"}"
USERNAME="${PDO_USERNAME:-"{PDO_USERNAME}"}"
PASSWORD="${PDO_PASSWORD:-"{PDO_PASSWORD}"}"
DATABASE="${MYSQL_DATABASE:-"{MYSQL_DATABASE}"}"

export MYSQL_PWD="$PASSWORD"

# All database tables that we want to export the structure of
STRUCTURE=(bills bills_copatrons bills_full_text bills_places bills_news bills_section_numbers bills_status bills_status_narratives bills_views blacklist chamber_status comments comments_subscriptions committees committee_members dashboard_bills dashboard_portfolios dashboard_user_data dashboard_watch_lists districts dockets files fiscal_impact_statements gazetteer logs meetings minutes polls people representatives representatives_votes sessions tags terms users vacode video_clips video_index video_index_faces video_transcript votes)

# All database tables that we want to export all contents of
ALL_CONTENTS=(committees committee_members districts people representatives sessions terms)

# All database tables that we want to export some contents of, as test data
SOME_CONTENTS=(bills_copatrons bills_full_text bills_places bills_news bills_section_numbers bills_status bills_status_narratives bills_views comments dockets fiscal_impact_statements polls tags votes)

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

# Lots of tests require all legislators (people, terms, and the legacy representatives), all
# committees, all sessions, and all terms. So those are included by default.
#
# API tests require 2025's HB41, SB839, SB738, SB305, HB1910, HB1894, and HB1591 (so those are IDs
# 77034, 77318, 77430, 76483, 76873, and 77039); all legislators; and file #14569.
#
# Front-end tests additionally require 2025's HB1561, HB2049, SB1350, HB2723, HB2052, and SB793
# (IDs 76924, 77600, 78711, 78905, 77635, and 76972).

BILL_IDS=(
    77039 74613 72911 76873 74093 73894 73446 77034 72883 74728 73202 77430 72884 74117 73805 77318
    72954 73556 77399 72980 76972 76995 73362 74500 74148 76483 78711 78827 72932 73272 72955 78195
    77600 76924 78905 76777 77635
)

# The local-only sample (local-test-records.sql, which isn't stored in GitHub, and which the tests
# in tests/local/ need) is a few hundred bills from the two most recent regular sessions, chosen
# to include as many kinds of bills as possible, plus a few videos from each chamber. See "Export
# the local-only sample," below.
#
# The committed fixtures (deploy/mysql/*-fixtures.sql) provide their own versions of these bills'
# and videos' records, so they're left out of the sample, lest both versions be loaded.
FIXTURE_BILL_IDS=(83944)
FIXTURE_FILE_IDS=(5159 5175 15141)

# How many videos to include in the sample from each chamber
SAMPLE_VIDEOS_PER_CHAMBER=6

# The file IDs that we want to export for video testing.
FILE_IDS=(
    14569
)

# Change to the directory this script is in
cd "$(dirname "$0")"
mkdir -p mysql

# Remove the files that this generates (but not the committed fixtures, which are also in mysql/)
rm -f mysql/structure.sql mysql/basic-contents.sql mysql/test-records.sql \
    mysql/local-test-records.sql mysql/video-records.sql mysql/database.sql

# Export the structural data
truncate --size 0 mysql/structure.sql
mysqldump -d --routines --triggers -u "$USERNAME" \
    --host "$HOST" "$DATABASE" "${STRUCTURE[@]}" > mysql/structure.sql

# Export the tables for which we want complete contents
truncate --size 0 mysql/basic-contents.sql
mysqldump --no-create-info --complete-insert --insert-ignore --skip-lock-tables -u "$USERNAME" \
    --host "$HOST" "$DATABASE" "${ALL_CONTENTS[@]}" > mysql/basic-contents.sql

# Export selected contents from the other tables
truncate --size 0 mysql/test-records.sql
for BILL_ID in "${BILL_IDS[@]}"; do
    mysqldump "$DATABASE" --no-create-info --complete-insert --insert-ignore --skip-lock-tables -u "$USERNAME" \
        --host "$HOST" bills --where "id=$BILL_ID" >> mysql/test-records.sql
done

# Files table
for FILE_ID in "${FILE_IDS[@]}"; do
    mysqldump "$DATABASE" --no-create-info --complete-insert --insert-ignore --skip-lock-tables -u "$USERNAME" \
        --host "$HOST" files --where "id=$FILE_ID" >> mysql/test-records.sql
done

# Video index
for FILE_ID in "${FILE_IDS[@]}"; do
    mysqldump "$DATABASE" --no-create-info --complete-insert --insert-ignore --skip-lock-tables -u "$USERNAME" \
        --host "$HOST" video_index --where "file_id=$FILE_ID" >> mysql/test-records.sql
done

# Video clips
for FILE_ID in "${FILE_IDS[@]}"; do
    mysqldump "$DATABASE" --no-create-info --complete-insert --insert-ignore --skip-lock-tables -u "$USERNAME" \
        --host "$HOST" video_clips --where "file_id=$FILE_ID" >> mysql/test-records.sql
done

# Video transcripts
for FILE_ID in "${FILE_IDS[@]}"; do
    mysqldump "$DATABASE" --no-create-info --complete-insert --insert-ignore --skip-lock-tables -u "$USERNAME" \
        --host "$HOST" video_transcript --where "file_id=$FILE_ID" >> mysql/test-records.sql
done

# Meetings and dockets from the 2025 session
mysqldump "$DATABASE" --no-create-info --complete-insert --insert-ignore --skip-lock-tables -u "$USERNAME" \
    --host "$HOST" meetings --where "session_id=31" >> mysql/test-records.sql
mysqldump "$DATABASE" --no-create-info --complete-insert --insert-ignore --skip-lock-tables -u "$USERNAME" \
    --host "$HOST" dockets --where "date_created > '2025-01-01' AND date_created < '2025-06-01'" >> mysql/test-records.sql


for TABLE in "${SOME_CONTENTS[@]}"; do
    for BILL_ID in "${BILL_IDS[@]}"; do
        # Genericize all IP addresses and email addresses, to maintain privacy.
        mysqldump "$DATABASE" --no-create-info --complete-insert --insert-ignore --skip-lock-tables \
            -u "$USERNAME" --host "$HOST" "$TABLE" \
            --where "bill_id=$BILL_ID" |perl -pe 's{[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}}{ sprintf "127.%01d.%01d.%01d", int(255*rand()), int(255*rand()), int(255*rand()) }ge' \
            |sed -E "s/\b[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}\b/example@example.com/g" \
            >> mysql/test-records.sql
    done
done

# Individual legislator vote records (representatives_votes links via votes.id, not bill_id)
for BILL_ID in "${BILL_IDS[@]}"; do
    mysqldump "$DATABASE" --no-create-info --complete-insert --insert-ignore --skip-lock-tables -u "$USERNAME" \
        --host "$HOST" representatives_votes \
        --where "vote_id IN (SELECT id FROM votes WHERE bill_id=$BILL_ID)" >> mysql/test-records.sql
done

# Export the local-only sample
query() {
    mysql "$DATABASE" --batch --skip-column-names -u "$USERNAME" --host "$HOST" -e "$1"
}
dump() {
    mysqldump "$DATABASE" --no-create-info --complete-insert --insert-ignore --skip-lock-tables \
        -u "$USERNAME" --host "$HOST" "$1" --where "$2"
}
# Genericize all IP addresses and email addresses, to maintain privacy.
scrub() {
    perl -pe 's{[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}}{ sprintf "127.%01d.%01d.%01d", int(255*rand()), int(255*rand()), int(255*rand()) }ge' \
        |sed -E "s/\b[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}\b/example@example.com/g"
}

FIXTURE_BILL_LIST=$(IFS=,; echo "${FIXTURE_BILL_IDS[*]}")
FIXTURE_FILE_LIST=$(IFS=,; echo "${FIXTURE_FILE_IDS[*]}")
RECENT_SESSIONS="SELECT id, date_started FROM sessions WHERE suffix IS NULL ORDER BY date_started DESC LIMIT 2"

# The videos from the recent sessions, with transcripts, in which the most bills were taken up
SAMPLE_FILE_LIST=$(query "
    SELECT id
    FROM (
        SELECT files.id,
            ROW_NUMBER() OVER (
                PARTITION BY files.chamber
                ORDER BY COUNT(DISTINCT video_index.linked_id) DESC, files.id DESC
            ) AS n
        FROM files
        JOIN video_index
            ON video_index.file_id = files.id AND video_index.type = 'bill'
            AND video_index.linked_id IS NOT NULL
        WHERE files.date >= (SELECT MIN(date_started) FROM ($RECENT_SESSIONS) AS recent)
            AND files.id IN (SELECT file_id FROM video_transcript)
            AND files.id NOT IN ($FIXTURE_FILE_LIST)
        GROUP BY files.id, files.chamber
    ) AS ranked
    WHERE n <= $SAMPLE_VIDEOS_PER_CHAMBER" | paste -sd, -)
SAMPLE_FILE_LIST=${SAMPLE_FILE_LIST:-0}

# Bills from the recent sessions: the most interesting two of each type (HB, SJ, etc.) and status,
# in each session, plus the most interesting (or most extreme) few without tags, with the most
# versions, with the most copatrons, incorporated into other bills, with the most votes, and with
# the most comments; the bills with the most impact statements from each agency; and the most
# interesting 30 of the bills taken up in the sampled videos (a floor session can take up
# hundreds).
SAMPLE_BILL_LIST=$(query "
    WITH recent AS ($RECENT_SESSIONS),
    candidates AS (
        SELECT bills.id, bills.session_id, LEFT(bills.number, 2) AS type, bills.status,
            bills.interestingness, bills.incorporated_into,
            (SELECT COUNT(*) FROM tags WHERE tags.bill_id = bills.id) AS tags,
            (SELECT COUNT(*) FROM bills_full_text WHERE bills_full_text.bill_id = bills.id) AS versions,
            (SELECT COUNT(*) FROM bills_copatrons WHERE bills_copatrons.bill_id = bills.id) AS copatrons,
            (SELECT COUNT(*) FROM votes WHERE votes.bill_id = bills.id) AS votes,
            (SELECT COUNT(*) FROM comments WHERE comments.bill_id = bills.id) AS comments
        FROM bills
        JOIN recent
            ON recent.id = bills.session_id
        WHERE bills.id NOT IN ($FIXTURE_BILL_LIST)
    ),
    ranked AS (
        SELECT id, tags, status,
            ROW_NUMBER() OVER (PARTITION BY session_id, type, status ORDER BY interestingness DESC, id) AS by_status,
            ROW_NUMBER() OVER (PARTITION BY session_id, tags = 0 ORDER BY interestingness DESC, id) AS by_untagged,
            ROW_NUMBER() OVER (PARTITION BY session_id ORDER BY versions DESC, id) AS by_versions,
            ROW_NUMBER() OVER (PARTITION BY session_id ORDER BY copatrons DESC, id) AS by_copatrons,
            ROW_NUMBER() OVER (PARTITION BY session_id, status = 'incorporated' ORDER BY interestingness DESC, id) AS by_incorporated,
            ROW_NUMBER() OVER (PARTITION BY session_id ORDER BY votes DESC, id) AS by_votes,
            ROW_NUMBER() OVER (PARTITION BY session_id ORDER BY comments DESC, id) AS by_comments
        FROM candidates
    )
    SELECT id
    FROM ranked
    WHERE by_status <= 2
        OR (tags = 0 AND by_untagged <= 10)
        OR by_versions <= 10
        OR by_copatrons <= 5
        OR (status = 'incorporated' AND by_incorporated <= 5)
        OR by_votes <= 5
        OR by_comments <= 5
    UNION
    SELECT bill_id
    FROM (
        SELECT fiscal_impact_statements.bill_id,
            ROW_NUMBER() OVER (
                PARTITION BY RIGHT(fiscal_impact_statements.lis_id, 3)
                ORDER BY COUNT(*) DESC, fiscal_impact_statements.bill_id DESC
            ) AS n
        FROM fiscal_impact_statements
        JOIN bills
            ON bills.id = fiscal_impact_statements.bill_id
        JOIN recent
            ON recent.id = bills.session_id
        WHERE fiscal_impact_statements.bill_id NOT IN ($FIXTURE_BILL_LIST)
        GROUP BY fiscal_impact_statements.bill_id, RIGHT(fiscal_impact_statements.lis_id, 3)
    ) AS impact
    WHERE n <= 3
    UNION
    SELECT id
    FROM (
        SELECT DISTINCT bills.id, bills.interestingness
        FROM video_index
        JOIN bills
            ON bills.id = video_index.linked_id
        WHERE video_index.type = 'bill' AND video_index.file_id IN ($SAMPLE_FILE_LIST)
            AND bills.id NOT IN ($FIXTURE_BILL_LIST)
        ORDER BY bills.interestingness DESC, bills.id
        LIMIT 30
    ) AS in_videos" | paste -sd, -)

# Add the bills that any of those were incorporated into, so that links to them work
INCORPORATED_LIST=$(query "SELECT DISTINCT incorporated_into FROM bills
    WHERE id IN ($SAMPLE_BILL_LIST) AND incorporated_into IS NOT NULL
        AND incorporated_into NOT IN ($FIXTURE_BILL_LIST)" | paste -sd, -)
if [[ -n "$INCORPORATED_LIST" ]]; then
    SAMPLE_BILL_LIST="$SAMPLE_BILL_LIST,$INCORPORATED_LIST"
fi

# The bills with the most versions, for which every version's text is exported (for the rest,
# only the latest version is, because bills' text is most of the size of the export)
VERSIONED_BILL_LIST=$(query "SELECT bill_id FROM bills_full_text WHERE bill_id IN ($SAMPLE_BILL_LIST)
    GROUP BY bill_id ORDER BY COUNT(*) DESC, bill_id LIMIT 20" | paste -sd, -)
VERSIONED_BILL_LIST=${VERSIONED_BILL_LIST:-0}

truncate --size 0 mysql/local-test-records.sql
dump bills "id IN ($SAMPLE_BILL_LIST)" >> mysql/local-test-records.sql

for TABLE in "${SOME_CONTENTS[@]}"; do
    case "$TABLE" in
        bills_full_text)
            WHERE="bill_id IN ($VERSIONED_BILL_LIST) OR id IN (
                SELECT id FROM (
                    SELECT id, ROW_NUMBER() OVER (PARTITION BY bill_id ORDER BY date_introduced DESC, id DESC) AS n
                    FROM bills_full_text
                    WHERE bill_id IN ($SAMPLE_BILL_LIST) AND text IS NOT NULL
                ) AS latest
                WHERE n = 1)"
            ;;
        bills_views)
            # Only a few of each bill's views, of which there can be thousands
            WHERE="id IN (
                SELECT id FROM (
                    SELECT id, ROW_NUMBER() OVER (PARTITION BY bill_id ORDER BY date DESC, id DESC) AS n
                    FROM bills_views
                    WHERE bill_id IN ($SAMPLE_BILL_LIST)
                ) AS recent_views
                WHERE n <= 20)"
            ;;
        *)
            WHERE="bill_id IN ($SAMPLE_BILL_LIST)"
            ;;
    esac
    dump "$TABLE" "$WHERE" |scrub >> mysql/local-test-records.sql
done

# Individual legislator vote records
dump representatives_votes "vote_id IN (SELECT id FROM votes WHERE bill_id IN ($SAMPLE_BILL_LIST))" \
    >> mysql/local-test-records.sql

# The sampled videos, everything indexed in them, and the minutes of the days they're from
dump files "id IN ($SAMPLE_FILE_LIST)" >> mysql/local-test-records.sql
for TABLE in video_index video_clips video_transcript; do
    dump "$TABLE" "file_id IN ($SAMPLE_FILE_LIST)" >> mysql/local-test-records.sql
done
dump minutes "(date, chamber) IN (SELECT date, chamber FROM files WHERE id IN ($SAMPLE_FILE_LIST))" \
    >> mysql/local-test-records.sql

# Export video records
truncate --size 0 mysql/video-records.sql
for TABLE in video_index video_clips video_transcript; do
    for FILE_ID in "${VIDEO_CONTENTS[@]}"; do
        mysqldump "$DATABASE" --no-create-info --complete-insert --insert-ignore --skip-lock-tables \
            -u "$USERNAME" --host "$HOST" "$TABLE" \
            --where "file_id=$FILE_ID" >> mysql/video-records.sql
    done
done

# Remove the environment variable, now that we're done with it
unset MYSQL_PWD

# Combine some exports into a single file, which we want for some repos
cat mysql/structure.sql mysql/basic-contents.sql mysql/test-records.sql \
    > mysql/database.sql
