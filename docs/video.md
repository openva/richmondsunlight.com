# Video Pages

## Status

Built in November 2026, in all three repos, and tested locally. Nothing is committed or deployed. See
"How it was built" (below the open questions) for where the build differs from the plan, and
"Deploying" for the order to ship it in.

## Goal

Give every video its own page, at a short, permanent URL. Today there's no page for a video: clip
links on bill and legislator pages go straight to the MP4 file, and only floor sessions can be
watched on the site (on `/minutes/`). Committee and subcommittee meetings, which are in the
database, have no page at all.

## Decisions

- **Each video gets a random slug**, stored in a new `files.slug` column. Database IDs aren't relied
  on as permanent.
- **URLs are the slug plus a readable suffix:** `/video/k7qm/2026-01-20-house-appropriations/`.
  Only the slug identifies the video. The suffix is generated, never stored, and a wrong or
  missing one redirects to the right one.
- **The page gets everything from rs-api**, through a new v1.1 endpoint,
  `/1.1/video/{slug}.json`. It doesn't query the database.
- **A video that isn't ready yet** still has a page. It shows a "processing" note and whatever
  metadata exists.
- **`/video/clip/` is removed,** with no redirects. Those URLs were never advertised.

## Slugs

**Format: four characters, from the 27-character set `bcdfghjkmnpqrstvwxz23456789`.**

- **No vowels,** so a slug can't spell a word.
- **No look-alikes:** no `0`/`o`, `1`/`l`/`i`.
- **Lowercase only.** A request with uppercase letters redirects to lowercase.
- **Why four characters:** there are about 19,000 videos now. Three characters from this set give
  19,683 slugs, which is barely enough. Even three characters from all 36 would be 40% used on day
  one, and random slugs collide more often as the space fills. Four characters give 531,441.
- **No all-digit slugs.** Every slug must contain at least one letter, so it can never be mistaken
  for a numeric ID in the existing `/1.1/video/{id}.json` route. (Fewer than 1% of the space are
  all digits; generate again if one comes up.)

**Column:** `slug char(4) CHARACTER SET ascii COLLATE ascii_bin`, with a unique index. It's nullable
until the backfill is done, then `NOT NULL`, so any code path that inserts a video without a slug
fails loudly instead of creating a video with no page.

**Generating one:** pick four characters at random, insert, and if the unique index rejects it, try
again. Both places that insert into `files` must do this:

- `rs-video-processor`: `src/Sync/VideoImporter.php`, for nearly every video.
- This repo: `Video::submit()` in `htdocs/includes/class.Video.php`, used by `htdocs/admin/video/`.

**Backfill:** a one-time script in `rs-video-processor/bin/` gives a slug to every existing row.

## Readable suffix

The suffix is built from the date, the chamber and the meeting:

| Meeting | Suffix |
|---|---|
| Floor session | `2026-03-11-house-floor` |
| Committee | `2026-01-20-house-appropriations` |
| Subcommittee | `2026-01-20-house-courts-of-justice-criminal-law` |
| Committee the processor couldn't identify | `2026-01-20-house-committee` |

rs-api builds the suffix and returns the page's full URL (`url`), so the website never has to
build it. If a committee is renamed, the old suffix redirects to the new one.

Links within the site (bill pages, legislator pages, minutes) can use just `/video/k7qm/` and let
the page redirect to the full URL, so they don't need the suffix logic either. A `?t=` time is kept
across the redirect.

## Deep links to a moment

`/video/k7qm/2026-01-20-house-appropriations/?t=1234` starts playback at 20:34. This replaces the
clip links on bill and legislator pages, which now point at the MP4 file with `#t=`.

## The API (rs-api)

### New: `/1.1/video/{slug}.json`

```json
{
  "slug": "k7qm",
  "url": "https://www.richmondsunlight.com/video/k7qm/2026-01-20-house-appropriations/",
  "status": "available",
  "pending": ["transcript"],
  "date": "2026-01-20",
  "chamber": "house",
  "meeting": {
    "type": "committee",
    "label": "House Appropriations",
    "committee": { "name": "Appropriations", "shortname": "appropriations", "url": "…" },
    "parent_committee": null
  },
  "title": "…",
  "description": "…",
  "length": 7384,
  "width": 1280,
  "height": 720,
  "license": "public domain",
  "sponsor": "…",
  "media": { "mp4": "https://…", "youtube_id": null, "poster": "https://…" },
  "captions": "https://api.richmondsunlight.com/1.1/video/k7qm.vtt",
  "bills": [
    { "start": 312, "end": 655, "number": "hb41", "year": 2026, "catch_line": "…", "url": "…" }
  ],
  "speakers": [
    { "name": "…", "shortname": "…", "label": "…", "starts": [318, 1290] }
  ],
  "transcript": [
    { "start": 312.4, "speaker": { "name": "…", "shortname": "…" }, "lines": [{ "start": 312.4, "end": 318.9, "text": "…" }] }
  ],
  "minutes": "https://www.richmondsunlight.com/minutes/house/2026/01/20/",
  "same_day": [
    { "slug": "p3xd", "url": "…", "label": "House floor session" }
  ]
}
```

Notes on the fields:

- **`status`:**
  - `available`: there's something to play: an MP4 on a host that the site's Content-Security-Policy
    lets pages play (`video.richmondsunlight.com`, `archive.org`, `sliq.net`,
    `archive-video.granicus.com`), or a YouTube ID (in `files.youtube_id`, or a YouTube URL in
    `files.path`).
  - `processing`: nothing to play yet.
  - This is broader than `UnwantedVideoCleaner`'s "not downloaded" test, which the plan first named.
    Until a video is downloaded, its `path` is the legislature's own copy (a sliq.net MP4 for the
    House, an MP4 or a YouTube page for the Senate), which plays, so it's shown.
- **`pending`:** the processing stages (from `video_jobs`) that haven't finished. A video can be
  playable while its transcript or chapters are still being made. Only the stages visitors see
  (`transcript`, `bills`, `speakers`) are listed, and only while their job is `running`, `retry`,
  `waiting` or `pending`: a stage has a `video_jobs` row only once it has started, so a missing row
  doesn't mean the work is waiting.
- **`meeting.type`:** `floor`, `committee`, `subcommittee` or `unknown`. See open question 2.
- **`bills` and `speakers`:** the chapters the minutes page builds now. rs-api's `includes/` are
  copied from this repo's `deploy` branch when rs-api is deployed, so nothing needed porting: the
  work is done by this repo's `Video::record_for_api()` and `Video::list_for_api()`, and the API's
  endpoints only call them. The ID rules in `CLAUDE.md` apply:
  - `video_index.linked_id` is a `people.id`. Get the name from the term the person was serving on
    the video's date.
  - `video_transcript.legislator_id` is a `terms.id`.
- **`sponsor`:** this is HTML (some sponsors have a logo). The API returns it as stored; the site
  renders it with `Video::sponsor_html()`, which keeps only text, and links and images with http(s)
  addresses.
- **`poster`:** the video's first indexed frame (one with a chyron), so it's known to exist. Older
  videos, which have no index, have none. It's in `videos.json` too, for the sitemap.
- **`transcript`:** a full floor session's transcript can run to hundreds of KB. It's gzipped. If
  that turns out to be slow, add `?include=` to make it optional; don't start with that.
- **Caching:** not in Memcached, as first planned: production rs-api has no Memcached server
  configured (`MEMCACHED_SERVER` isn't among its deploy settings), so the cache would never be used.
  Cloudflare's one-hour cache (below) does the job.

### New: `/1.1/video/{slug}.vtt`

The captions, as WebVTT, from what `video-transcript.php?format=vtt` does now.

### Changed: `/1.1/videos.json`

- **Add** `slug`, `url`, `status` and `meeting` to each video.
- **Add filters:** `chamber`, `committee` (a shortname, including its subcommittees) and `date`.
  The minutes page, committee pages and `/video/` need them.
- **Default:** with no filter it returns all 19,000 videos. Leave that alone, but the website should
  always filter.
- **No matches:** filters that match nothing return `[]`. (A `year` that matched nothing used to be a
  404.) No videos at all is still a 404.
- **Old fields keep their types:** `id`, `width` and `height` are still strings, as they always were.

### Deprecated: `/1.1/video/{id}.json`

It stays, for anyone already using it, with `slug` and `url` added. It's marked deprecated in
`openapi.yaml` and on the API's documentation page, and sends a `Deprecation: true` header.

### Routing and caching

In `htdocs/1.1/.htaccess`:

```
RewriteRule ^video/([0-9]+).json$ video.php?id=$1 [QSA]
RewriteRule ^video/([b-df-hjkmnp-tv-xz2-9]{4}).json$ video-slug.php?slug=$1 [QSA]
RewriteRule ^video/([b-df-hjkmnp-tv-xz2-9]{4}).vtt$ video-captions.php?slug=$1 [QSA]
```

The numeric rule comes first, so a four-digit ID still reaches the old endpoint.

Cache-Control is set only in `.htaccess`, which can't see whether a video is still processing. Give
`/1.1/video/` one hour, like current-session bills. A video that's still processing is then at most
an hour behind.

### Documentation

- `openapi.yaml`
- This repo's `htdocs/about-api.php` and `htdocs/llms.txt`

### Tests

Add to `deploy/tests/` for each case below:

- A slug that exists
- A slug that doesn't exist (404)
- A malformed slug (404)
- A processing video
- A floor video
- A subcommittee video
- The VTT output
- Each new filter on `videos.json`

## The website (this repo)

### Routes

In `htdocs/.htaccess`:

```
RewriteRule ^video/?$ /list-videos.php [QSA,L]
RewriteRule ^video/([0-9]{4})/?$ /list-videos.php?year=$1 [QSA,L]
RewriteRule ^video/([A-Za-z0-9]{4})/captions\.vtt$ /video-captions.php?slug=$1 [QSA,L]
RewriteRule ^video/([A-Za-z0-9]{4})/(?:([a-z0-9-]+)/?)?$ /video.php?slug=$1&suffix=$2 [QSA,L]
```

- **Years:** `/video/2025/` lists that year's videos. A year can't be mistaken for a slug, because
  slugs are never all digits.

- **Uppercase:** the slug pattern accepts it so that `video.php` can redirect to lowercase rather
  than 404.
- **The `htdocs/video/` directory** exists. Check that `/video/` reaches `list-videos.php` rather than
  a directory listing or `mod_dir`'s trailing-slash redirect.
- **Remove:** the `/video/clip/` rule and `htdocs/video/clip.php`.

### `htdocs/video.php` (new)

1. **Fetch:** get `API_URL . '1.1/video/{slug}.json'` with `get_content()`, as `bill.php` does.
   - An API error, or no such slug: 404.
   - A slug that isn't lowercase, or a suffix that doesn't match the `url` the API returned: 301 to
     `url`, keeping `?t=`.
2. **`status` is `processing`:** show the metadata and a note that the video is being processed.
   No player, no download link.
3. **`status` is `available`:** show the player, chapters and transcript.
   - If `pending` isn't empty, say which parts are still being prepared ("The transcript is still
     being prepared.").
4. **Every video shows:**
   - The meeting name, chamber, date and length.
   - A link to the committee, and its parent committee for a subcommittee.
   - A link to the day's minutes, if there are any.
   - The day's other videos.
   - The license and sponsor note.
5. **In `<head>`:**
   - `<link rel="canonical">` with `url`.
   - Open Graph tags, including `og:video`.
   - A schema.org `VideoObject` in JSON-LD: name, description, upload date, duration, thumbnail,
     MP4 address and transcript.
6. **YouTube:** a video with only a `youtube_id` embeds YouTube. The site's CSP already allows it.

### Captions

`htdocs/video-captions.php` (new) fetches `/1.1/video/{slug}.vtt` from the API and passes it
through.

Pointing the `<track>` straight at the API would make it a cross-origin request. That needs
`crossorigin` on the `<video>` element, and that applies to the MP4 too: video from a host that
doesn't send CORS headers (`archive.org`, for one) would stop playing. Passing the captions through
the site avoids that.

### One player for every page

- **Move the markup:** the player, chapter tabs and transcript now built inline in `minutes.php`
  become one function, e.g. `Video::page_player_html(array $video)`.
  - It takes the API's JSON, not database rows.
  - `video.php` uses it.
- **Generalize the script:** `htdocs/js/minutes-video.js` becomes `video-player.js`.
  - Add `?t=` support.
  - Update the time in the address bar as the video plays, so a copied URL starts where the viewer
    is.

### Pages that change

- **`minutes.php`:**
  - List every video recorded that day in that chamber (`videos.json?chamber=…&date=…`), each
    linking to its page.
  - This also fixes days with more than one floor recording, which now show only the first.
  - Whether it keeps its own player is open question 1.
- **`committee.php`:** list the committee's meetings, newest first, including its subcommittees'
  (`videos.json?committee=…`).
- **`bill.php`, `legislator.php` and `class.Video.php`:**
  - Clip "source" links (`bill.php:1291`, `legislator.php:790`, `class.Video.php:251`, `:321`)
    become `/video/{slug}/?t={start}`.
  - Add `files.slug` to the queries that build those clips (`Video::person_clips()`, the bill
    clips).
- **`list-videos.php` (new):** `/video/`, listing videos by year, then chamber, then committee.
  `/video/` shows this year (or last year, until there's video of this year).

### Sitemaps

Add a video sitemap to `deploy/generate_sitemaps.php`, from `videos.json`, with Google's video
extensions (thumbnail, title, description, duration, content location). This is the main way
committee video gets found in search.

### Tests

- **Fixtures:** add test videos with slugs, including one still processing, to the local database
  (`deploy/mysql/`).
- **Playwright** `video-page.spec.js` covers:
  - The player loads.
  - `?t=` seeks.
  - A wrong suffix redirects.
  - An uppercase slug redirects.
  - An unknown slug is a 404.
  - A processing video shows its note and no player.
  - A committee video links to its committee.
- **Update:** `minutes-video.spec.js`, `bill-video.spec.js` and `legislator-video.spec.js`, for the
  moved player and the new links.

## Schema changes

The schema has to change in every repo with a copy of the `files` table:

| File | Change |
|---|---|
| `deploy/mysql/structure.sql` (this repo) | Add `files.slug` |
| `rs-api/deploy/database.sql` | Add `files.slug` |
| `rs-video-processor` test schema | Add `files.slug` |

Locally, the column stays nullable. The fixtures use `INSERT IGNORE`, which, with a `NOT NULL`
column they don't fill in, would insert an empty slug and then silently drop every later row as a
duplicate. Instead, `deploy/mysql/zz-video-slugs.sql`, loaded after every other fixture, gives each
local video a slug made from its ID (15141 is `b334`), so tests can name them. Production slugs are
random.

Production, in two steps:

```sql
-- Before the backfill
ALTER TABLE files ADD slug char(4) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER id,
    ADD UNIQUE KEY slug (slug);

-- After the backfill, once SELECT COUNT(*) FROM files WHERE slug IS NULL returns 0
ALTER TABLE files MODIFY slug char(4) CHARACTER SET ascii COLLATE ascii_bin NOT NULL;
```

## Deploying

The three repos depend on each other in a loop, so the site ships in two parts:

- rs-api's endpoints call `Video` methods that are in this repo's includes, which rs-api copies
  from this repo's `deploy` branch when it deploys (on every push to `master`, and nightly at 4
  a.m.). Deploying rs-api first would break `/1.1/videos.json` in production.
- This repo's tests run against rs-api's `master`, and its deploy waits for them. Deploying the
  site's pages first would fail their tests, because the endpoints wouldn't exist yet.

1. **Production database:** add the nullable column:

   ```sql
   ALTER TABLE files ADD slug char(4) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER id,
       ADD UNIQUE KEY slug (slug);
   ```

2. **rs-video-processor:** deploy it, so that new videos get slugs. Then give every existing video
   one with `php bin/backfill_slugs.php`.
3. **This repo, part 1 (includes only), to `deploy`:**
   - `htdocs/includes/class.Video.php`
   - `deploy/mysql/structure.sql`, `video-page-fixtures.sql`, `zz-video-slugs.sql`
   - `docker-run.sh`
   - `deploy/tests/phpunit/VideoTest.php`

   No page uses the new methods yet. `Video::person_clips()` now reads `files.slug`, so this needs
   step 1.
4. **Make the column `NOT NULL`:** once `SELECT COUNT(*) FROM files WHERE slug IS NULL` returns 0,
   and step 3 has shipped (the admin upload writes slugs from then on):

   ```sql
   ALTER TABLE files MODIFY slug char(4) CHARACTER SET ascii COLLATE ascii_bin NOT NULL;
   ```

5. **rs-api, to `master`:** its deploy picks up step 3's includes. Then purge Cloudflare's cache for
   `api.richmondsunlight.com/1.1/videos.json` and `/1.1/video/*`.
6. **This repo, part 2 (everything else), to `deploy`:** the video and list pages, captions, routes,
   the shared player and its CSS, the minutes, committee, bill and legislator pages, the sitemap,
   the docs, and the browser tests.

## Open questions and their answers

1. **Does the minutes page keep its own player,** or only link to the video page?
   - Answer: The minutes page keeps its own player, and we use a standard video player across
    the site.
2. **What does a deleted video's page return?**
   - A 404.
3. **Should the numeric `/1.1/video/{id}.json` be marked deprecated** in the API docs, now that
   video IDs aren't meant to be relied on?
   - Yes.

## Risk to note

`files.id` is `smallint unsigned`, which tops out at 65,535, and so is every `file_id` column that
points to it (`video_clips`, `video_index`, `video_jobs`, `video_sources`, `video_transcript`).
There are about 19,000 videos now. That's years away, but when it's reached, every insert fails.
Moving all of them to `mediumint unsigned` is a separate change; slugs don't depend on it.

## How it was built

Where the build differs from the plan above, the plan has been updated. In short:

- **"Available"** is any video with an address that pages may play, not only our own copies (see
  `status`).
- **No Memcached:** production rs-api doesn't have it (see Caching).
- **Nothing was ported to rs-api:** its includes come from this repo, so the video logic is in this
  repo's `class.Video.php`, and the API's endpoints (`video-slug.php`, `video-captions.php`,
  `videos.php`) are thin.
- **Transcripts are in turns**, each with its speaker and timed lines, as the site already grouped
  them.
- **A local slug is made from the video's ID,** so that tests can name videos (see Schema changes).
- **The deploy happens in a different order** (see Deploying).

What's where:

| Repo | Files |
|---|---|
| richmondsunlight.com | `htdocs/includes/class.Video.php` (slugs, the API's records, the shared player), `htdocs/video.php`, `htdocs/video-captions.php`, `htdocs/list-videos.php`, `htdocs/js/video-player.js` (was `minutes-video.js`), `htdocs/minutes.php`, `htdocs/committee.php`, `htdocs/bill.php`, `htdocs/legislator.php`, `htdocs/.htaccess`, `htdocs/css/page-elements.css`, `deploy/generate_sitemaps.php`, `htdocs/about-api.php`, `htdocs/llms.txt`; removed `htdocs/video/clip.php` |
| rs-api | `htdocs/1.1/video-slug.php`, `video-captions.php`, `videos.php`, `video.php`, `.htaccess`, `htdocs/openapi.yaml`, `deploy/tests/api.sh`, `AGENTS.md` |
| rs-video-processor | `src/Sync/VideoSlug.php`, `src/Sync/VideoSlugBackfill.php`, `bin/backfill_slugs.php`, `src/Sync/VideoImporter.php`, `deploy/mysql/structure.sql`, and tests |

Tests:

- **rs-api's** `deploy/tests/api.sh` passes in full.
- **rs-video-processor's** PHPUnit suite passes (676 tests).
- **This repo's:**
  - Both PHPUnit suites pass.
  - The full Playwright suite passes (114 tests), with the new `video-page.spec.js`.
  - It plays a blank WebM, `deploy/browser-tests/fixtures/blank.webm`, in place of the fixtures'
    remote MP4s, which Playwright's Chromium can't play, so it can check that `?t=` and timestamps
    seek.

