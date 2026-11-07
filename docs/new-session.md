# Starting a New Legislative Session

Richmond Sunlight has one "current" session at a time. It is set in this repo's settings file and recorded in the `sessions` table. Every part of the system takes it from there: this site, rs-api, rs-machine, and rs-video-processor. This document covers how to switch to a new session. It applies to both regular and special sessions, though most of it is about the regular session that convenes each January.

In the examples below, `YYYY` is the new session's year and `YY` is its last two digits.

## When to do it

**Do it as soon as LIS has created the new session, before bill prefiling opens.** Bill prefiling usually opens in mid-November, and LIS may create the session months before that. Until the switch is made, rs-machine keeps fetching the old session's data, so new bills don't appear on the site.

In odd-numbered years, LIS also carries bills that were continued from the previous year's session into the new session's files. Those appear as soon as the session exists, before prefiling opens.

## 1. Get the new session's identifiers from LIS

You need four values.

| Value | What it is | Where to find it |
|---|---|---|
| LIS session ID | `YY1` for a regular session, `YY2` for a first special session, and so on | The last three digits of LIS's `SessionCode` |
| LIS API session ID | LIS's internal integer ID for the session | `SessionID` in the LIS Session API |
| Start date | When the session convenes | The "Session Start" event in the LIS Session API |
| End and crossover dates | Scheduled adjournment and crossover | "Adjournment" and "Crossover" events, once LIS publishes them |

The LIS Session API returns all of these. It needs the `LIS_KEY` from the settings:

```bash
curl -s -H "WebAPIKey: $LIS_KEY" -H "Accept: application/json" \
  https://lis.virginia.gov/Session/api/GetSessionListAsync \
  | python3 -c 'import json,sys; [print(s["SessionID"], s["SessionCode"], s["DisplayName"], [(e["DisplayName"], e["ActualDate"]) for e in s["SessionEvents"]]) for s in json.load(sys.stdin)["Sessions"][-4:]]'
```

The new session usually has `IsDefault: true`.

LIS often publishes the session before it sets the adjournment and crossover dates. If the adjournment date isn't available yet, estimate it: an even-year (long) session runs 60 days, and an odd-year (short) session runs 46 days. Correct the estimate later (step 7). Leave the crossover date empty until it's known.

To confirm that LIS is publishing data for the session, check that its bill file exists:

```bash
curl -s https://lis.blob.core.windows.net/lisfiles/20YY1/BILLS.CSV | wc -l
```

## 2. Add the session to the database

In production, add a row to `sessions`:

```sql
INSERT INTO sessions (lis_id, lis_api_id, year, suffix, date_started, date_ended, crossover, notes, date_created)
VALUES ('YY1', <LIS API session ID>, YYYY, NULL, 'YYYY-01-DD', 'YYYY-MM-DD', NULL, '', NOW());

SELECT * FROM sessions ORDER BY id DESC LIMIT 3;
```

Do this before deploying the new settings (step 5), so the site never points at a session that doesn't exist. Take note of the new row's `id`. Don't assume it is one more than the last regular session's: special sessions also get rows, so the number can skip.

For a special session, set `suffix` to the special session's number (`'1'` for Special Session I, `'2'` for Special Session II), and use `YY2`, `YY3`, and so on for `lis_id`.

Before adding the new session, compare LIS's session list with the `sessions` table. Add a row for any special session that has been held since the last regular session, then import its bills (see below).

### Importing a session that isn't the current one

rs-machine only collects data for the current session. To import a special session after the fact, or to fill in any other past session, add its `sessions` row and then run rs-machine's per-session tasks against it on the rs-machine server:

```bash
php cron/run.php --session=<sessions.id> bills copatrons summaries history full_text vote tags code_sections cleanup
```

Before running it, make sure every legislator who served in that session is in the database (step 8). A bill whose patron is missing isn't stored, and a missing member's votes aren't recorded. Run `bills` first, because the other tasks work on bills that are already in the database. If a task fails because LIS has no copy of one of its files for that session (often `fiscal_impact`), that's expected. `cleanup` comes last: it fills in each bill's full text and outcome. See rs-machine's README for details.

Pages on the site cache bills' IDs by number. Flush Memcached after the import, so that bill numbers the session shares with another session in the same year lead to the right bill.

Some bills show up in LIS under more than one session. LIS sometimes carries a bill from a regular session into a special session (often the budget bill) under the same LIS legislation ID. `bills.lis_id` must be unique, so rs-machine logs an error for such a bill ("Storing … failed: Duplicate entry") and leaves it under the session where it was first stored.

Bill URLs (`/bill/YYYY/hb1/`) include only the year. When a special session reuses a bill number from that year's regular session, the URL shows the one whose session started later.

## 3. Update the settings in this repo

`htdocs/includes/settings.inc.php` is not in git. It is generated during deploys from **`htdocs/includes/settings-default.inc.php`**, so that's the file to change. Update the "THE CURRENT SESSION" block:

| Constant | New value |
|---|---|
| `SESSION_ID` | The new `sessions.id` from step 2 |
| `SESSION_SUFFIX` | `''` for a regular session, or the special session's suffix |
| `SESSION_LIS_ID` | `'YY1'` |
| `SESSION_LIS_API_ID` | The LIS API `SessionID` |
| `SESSION_YEAR` | `YYYY` |
| `SESSION_START`, `SESSION_END` | Start and scheduled end. These determine `IN_SESSION`. |

Also confirm that `HOUSE_SPEAKER_LIS_ID` and `HOUSE_SPEAKER_ID`, in the same file, still identify the current Speaker of the House. They need to change only when the House elects a new Speaker.

`deploy/settings-docker.inc.php` is for local Docker development, and the test fixtures depend on its session. Leave it alone unless the fixtures are being regenerated too (see `deploy/database_export.sh`).

## 4. Update the year lists in this repo

These lists of years are hardcoded. Add the new year to each:

- `htdocs/list-bills.php`: the list of years on the Bills page.
- `htdocs/includes/templates/new.inc.php`: the Bills dropdown in the site navigation.
- `htdocs/downloads.php`: links to bulk bill downloads. Add a year only when `https://downloads.richmondsunlight.com/bills/YYYY.zip` exists, which usually isn't until the session is over.

To check for anything else that has a year hardcoded, search for the previous year:

```bash
grep -rnE '\bYYYY-1\b' htdocs deploy --include='*.php' | grep -v /vendor/
```

## 5. Deploy

1. Commit to `master` and let CI pass.
2. Merge `master` into the `deploy` branch. That deploys the site, and rs-api, rs-machine, and rs-video-processor all take their includes, including `settings-default.inc.php`, from the `deploy` branch.
3. Check that the site shows the new session: the home page and `/bills/YYYY/`.
4. Flush Memcached, so that no cached pages still show the old session. Deploys don't do this. On the web server, run `echo flush_all | nc -q1 localhost 11211`. PHP sessions are also stored in Memcached, so this logs everybody out.

## 6. Rebuild the other services

rs-api, rs-machine, and rs-video-processor each copy `htdocs/includes/*.php` from this repo's `deploy` branch when they build. They redeploy automatically every day at 4 AM. To make the change take effect immediately, run each one's deploy workflow by hand (GitHub Actions → `deploy.yml` → "Run workflow"):

- `openva/rs-api`
- `openva/rs-machine`
- `openva/rs-video-processor`

Afterwards, confirm that each server's `settings.inc.php` has the new `SESSION_YEAR`.

## 7. Check rs-machine

- **Bill status narratives**: `cron/bills_status_narratives.php` has a table of which party controls the House, the Senate, the governorship, and the lieutenant governorship, by session year. If the table doesn't cover the new year, narratives stop being generated, and the log says "Session year is outside of configuration." Extend it after each election that changes party control.
- **Run the main tasks by hand** and read their output: `php cron/run.php bills`, then `history`, `copatrons`, and `committee_members`. Bills should come from `lisfiles/20YY1/`, and committee members from the new LIS API session ID.
- **Session dates**: when LIS publishes the adjournment and crossover dates, update `sessions.date_ended` and `sessions.crossover`, and correct `SESSION_END` in `settings-default.inc.php` if it was an estimate. The crossover date goes into the bill status narratives.

## 8. Check the legislators

New members can arrive between sessions because of elections, special elections, and appointments. rs-machine's daily `legislators` task adds new members and marks departed ones as out of office. It can fail to add someone, though, for example when LIS hasn't yet assigned a district. Compare LIS's list with ours:

```bash
curl -s -H "WebAPIKey: $LIS_KEY" -H "Accept: application/json" \
  "https://lis.virginia.gov/Member/api/getmemberlistasync?sessionCode=20YY1" > lis-members.json
curl -s https://api.richmondsunlight.com/1.1/legislators.json > rs-legislators.json
```

LIS should list 140 members (100 House and 40 Senate). Ours should have the same number with no `date_ended`. Match them up by name and chamber. Check rs-machine's logs for "Found a new delegate" or "Found a new senator" messages about anyone who is missing.

To add a member by hand, create a `people` row if the person is new, then a `terms` row (see CLAUDE.md for the data model). Add their photo (`deploy/photo-check.php`). The legislators menu is rebuilt from the database during deploys (`deploy/generate_menu.php`).

## 9. Smoke test

Once bills are arriving, check that:

- [ ] A new-session bill appears at `/bill/YYYY/hb1/`, with its patron, summary, and full text.
- [ ] The API returns it: `https://api.richmondsunlight.com/1.1/bills/YYYY.json`.
- [ ] Bill status narratives and fiscal impact statements are being generated.
- [ ] Photosynthesis portfolios and email alerts include new-session bills.

Once the session convenes, check that votes, dockets and meetings, and video (both chambers' pollers, then the video processor) are all being attributed to the new session.
