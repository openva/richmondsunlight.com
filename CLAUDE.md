# CLAUDE.md

Supplements AGENTS.md with coding-level gotchas and conventions.

## Legislator Data Model

There are three overlapping tables for legislators. Use the right one:

| Table | Status | Use for |
|-------|--------|---------|
| `people` | Current | Canonical identity — `shortname`, `name`, `sex`, `race`, `bio` |
| `terms` | Current | Per-term data — `chamber`, `party`, `district_id`, `name_formatted`, `lis_id`. FK to `people.id` via `terms.person_id` |
| `representatives` | **Legacy** | Older queries only. Do not use for new code. |

New code should JOIN `people` + `terms`.

Legislator IDs in video data are mixed:

- `video_index.linked_id` (where `type = 'legislator'`) and `video_clips.legislator_id` are **`people.id`** values, as rs-video-processor writes them. Get the name from the term the person was serving on the video's date (see `Video::legislator_join()`). Most `people.id` values equal the person's `terms.id`, but not for anybody elected since 2024, so joining these straight to `terms.id` shows the wrong person.
- `video_transcript.legislator_id` is a `terms.id` (written by `Video::identify_speakers()`).

## Bill Number Case Sensitivity

`bills.number` uses `utf8mb3_bin` collation — it is case-sensitive. Bills are stored lowercase (e.g., `hb41`, not `HB41`). Always call `strtolower()` on bill number input before using it in a SQL query.

## Admin Pages

Admin pages live in `htdocs/admin/` and follow a different pattern from public pages:

- **Do not use the `Page` class** — output raw HTML directly
- **No PHP session check needed** — access is controlled by HTTP Basic Auth via `.htaccess`
- Include `../../includes/settings.inc.php` and `../../includes/functions.inc.php`
- Connect to DB with `$database = new Database(); $database->connect_mysqli();`

## Class Naming

The bill class is named `Bill2` (not `Bill`) to avoid conflicts with legacy code. This is intentional — do not rename it.

## Playwright Login Pattern

Logging in during browser tests requires:
1. POST to `/account/login/` with credentials
2. Extract `PHPSESSID` from the `Set-Cookie` response header
3. Add it to the browser context with `secure: false` (the test environment is HTTP, not HTTPS)

Test user: `testuser@example.com` / see `deploy/mysql/test-users.sql` for credentials. User ID is 90001.

## SESSION_ID and SESSION_YEAR

The current legislative session is hardcoded in `deploy/settings-docker.inc.php`:
- `SESSION_ID = 31`
- `SESSION_YEAR = 2025`

## Video Pages and Slugs

Every video (`files` row) has a `slug`, four random characters from `bcdfghjkmnpqrstvwxz23456789`, never all digits, used in its URL: `/video/{slug}/{readable-suffix}/`. Only the slug identifies a video; `video.php` redirects any other suffix to the right one. Don't link to videos by `files.id`, or to their MP4s; use `Video::page_path($slug, $seconds)`. See `docs/video.md`.

- Video pages, the minutes page's player, `/video/`, and committee pages' video lists get their data from the API (`/1.1/video/{slug}.json`, `/1.1/videos.json`), not the database.
- That API's video logic is in this repo's `htdocs/includes/class.Video.php` (`record_for_api()`, `list_for_api()`): rs-api copies `htdocs/includes/` from this repo's `deploy` branch when it deploys. So a change there changes the API, and rs-api's endpoints can't use a method until it's on `deploy`.
- Locally, `deploy/mysql/zz-video-slugs.sql` gives each video a slug made from its ID (15141 is `b334`), so tests can name them; production slugs are random. New video fixtures need no slug of their own.

## Day Pages

`/day/YYYY-MM-DD/` shows everything the General Assembly did on a day; it replaced the minutes
pages (`/minutes/...` redirects to it). Like video pages, day pages get their data from the API
(`/1.1/day/{date}.json`, `/1.1/days.json`), whose logic is in this repo's
`htdocs/includes/class.Day.php`. See `docs/minutes-replacement.md`.

- Bill actions are matched to committees by the name in LIS's text, not by `votes.committee_id`
  (unreliable), and to subcommittees by the day's dockets.
- `Day::classify()` decides what an action is. Add new LIS wording there (and to `DayTest.php`).
- A day's highlights are scored by `htdocs/includes/class.DayHighlights.php` (its `WEIGHTS`), ahead
  of time, by rs-machine's `cron/day_highlights.php`, into `day_highlights`. Locally,
  `php deploy/day-highlights.php 2026-01-26` scores a day; `docker-setup-site.sh` does the
  fixtures' day, which the tests expect. `/admin/day-highlights.php` shows the scores, and hides a
  highlight.
- A day's brief is written by a language model (rs-machine's `cron/day_briefs.php`), from what
  `DayBrief::input()` tells it, and shown only if `DayBrief::check()` finds every bill and number in
  it in the day's record.
