# A Day in the Legislature (replacing Minutes)

## Status

Phase 1 (the day's page, with no ranking) was built in November 2026, in this repo and rs-api. See
"Phase 1, as built" for where it differs from this proposal, and how to deploy it.

Phase 2 (highlights, "What People Said," and "In the News") was built next, in this repo, rs-api,
and rs-machine, and tested locally. It isn't committed or deployed, and its weights haven't been
tuned against real days yet. See "Phase 2, as built" (at the end).

Phases 3 through 5 aren't started.

## Goal

Replace the Minutes section (labeled "Video" in the menu) with one page for each day the General
Assembly meets. Each page says what happened that day and puts the most interesting parts first.

Today, `/minutes/` lists dates. Each date links to the clerk's minutes for one chamber, with that
chamber's floor video. The minutes are a complete record, but they're hard to read. A floor
session's minutes list dozens of bills in the order they were taken up, and none of them stands out
from the rest. Committee meetings, where most of the work happens, don't appear at all, except as a
list of videos.

Richmond Sunlight already has most of what a reader would want to know about a day, but it's
spread across bill pages, legislator pages, video pages, and committee pages. This page gathers it
in one place, for one date, and ranks it.

## What a day's page shows

In order, from the top:

1. **The day in brief.** A few sentences: how many bills passed and failed in each chamber, the
   closest or most partisan votes, and the bills that drew the most attention. At first this is
   assembled from the data with templates. Later it could be written by a language model (see
   "Phases").
2. **Highlights.** The five to ten bills that are most likely to interest a reader, ranked by the
   score described below. Each one shows:
   - its number, title, and patron
   - what happened to it that day (for example, "Reported by House Courts of Justice, 12–10")
   - the reasons it ranked high (for example, "3 news stories," "party-line vote," "most-viewed bill
     today")
   - a clip of the discussion, if there is one
3. **The highlight reel.** The highlights' video clips, in one player, played back to back, with a
   list of chapters. This is the "what happened today" video for someone who has ten minutes.
4. **Floor sessions.** One section per chamber:
   - the floor video
   - every recorded vote, with the contested ones marked
   - bills passed, failed, and otherwise disposed of
   - the official minutes, collapsed by default, with bill numbers linked as they are today
5. **Committee and subcommittee meetings.** One block per meeting, in the order they met. Each one
   shows:
   - the committee and the time
   - the video
   - the docket (agenda)
   - each bill's outcome
   - the vote counts
6. **What people said.** Comments posted that day on bills that were acted on, poll results, and the
   bills that were viewed most.
7. **In the news.** Articles that mention bills that were acted on that day, from `bills_news`.
8. **Coming up.** The next meeting day's schedule and dockets, from `meetings` and `dockets`. This
   section appears only on the most recent day.

Each section is left out when it has nothing to show. A day with only committee meetings has no
floor section, for example.

**Filters:** "House," "Senate," and "Both" (the default), kept in the URL's fragment, like today's
`/minutes/#house`.

**For logged-in users:** a "Your bills" box at the top of the page. It lists anything that happened
that day to bills in the user's Photosynthesis portfolios. This is optional, but it's cheap to
build, and it's the most useful part of the page for a regular user.

## The data, and where it comes from

| Source | Table | What it tells us about a day | Caveats |
|---|---|---|---|
| Bill history | `bills_status` | Every action on every bill, with its date and chamber. This is the page's backbone. | The status text is LIS's. `translation` normalizes it, but not completely. |
| Votes | `votes`, `representatives_votes` | Tallies, outcomes, and how contested (`contested`) and partisan (`partisanship`) each vote was | Committee votes have `committee_id`. How completely subcommittee votes are recorded needs checking. |
| Minutes | `minutes` | The clerk's official record of each floor session | They arrive a day or more late, and floor sessions only |
| Agendas | `dockets`, `meetings` | Which bills each committee meant to hear, and when and where it met | Dockets change during the day |
| Video | `files` (through the API) | Every floor and committee video, with its page | Videos are processed hours after a meeting |
| Clips | `video_clips` | Where in each video a bill was discussed, and by whom | The boundaries come from on-screen captions, so a clip can run long |
| Transcripts | `video_transcript` | What was said, by whom, and when | Some videos have no transcript yet, and speakers aren't always identified |
| News | `bills_news` | Articles that mention a bill (Virginia Mercury and Cardinal News, from `rs-machine/cron/articles.php`) | Two sources only, matched by bill number |
| Comments | `comments` | What site users said about a bill, and when | Low volume |
| Polls | `polls` | Site users' votes on a bill | Self-selected |
| Views | `bills_views` | Bill page views, by IP, with timestamps | Bots inflate it, so count distinct IPs, as `hotness` does |
| Portfolios | `dashboard_bills` | Who is tracking which bills | A strong signal of professional interest |
| Narratives | `bills_status_narratives` | Model-written plain-language summaries of a bill's progress | Only for bills with `interestingness` of at least 20 |

`hotness` and `interestingness` matter here, but neither can be used as-is:

- **`hotness`** is recalculated by `rs-machine/cron/cleanup.php` on every run, from the past one to
  three days. It describes now, not a past date, so a page for January 26 would show today's
  hotness. The day's score has to recompute the same formula for that date.
- **`interestingness`** covers the whole session. It's a fair sign that a bill matters, but it
  changes after the day in question, so it shouldn't be the main part of the score.

## Ranking: the day score

Each bill that was acted on that day gets a score. The score adds weighted signals in four groups.
Every weight is a setting, so it can be tuned against real days.

**What happened**

- The kind of action. A final vote, passage, failure, or a vote to report a bill counts for more
  than a referral or a reading. Continuing a bill to the next session, or passing it by
  indefinitely, also counts high: that is often how a bill dies.
- How contested the vote was (`votes.contested`).
- How partisan the vote was (`votes.partisanship`).
- Whether the outcome was a surprise. A bill that failed after passing the other chamber is one
  example. A bill that failed in a committee that its patron sits on is another.

**How much it was discussed**

- The length of the bill's clips that day, in total.
- The number of different speakers in those clips. Ten speakers means a debate. One speaker means a
  bill was presented and voted on.
- Whether there was public testimony. (Transcript text like "speaking in opposition" could
  suggest it. This needs testing.)

**How much outside attention it drew**

- News stories that mention the bill, from a few days before the day to a few days after.

**How much interest it drew on this site**

- `hotness`'s formula, computed for the day: distinct-IP views, comments, poll votes, portfolio
  adds, and comment subscriptions.
- The bill's `interestingness`, at a low weight, as a tiebreaker.

**Limits:**

- One bill from one patron doesn't get every highlight. No more than two highlights per patron.
- Each chamber gets at least two highlights, if it met.
- The score's reasons are kept with it, so that the page can say why a bill ranked high. Readers
  should be able to see why something was chosen, and so should whoever tunes the weights.

**Precomputing:** scores are computed in `rs-machine`, by a new cron task:

- hourly during session, on days that are still changing
- nightly for the past two weeks, because minutes, videos, news, and comments arrive late
- once, as a backfill, for every past day

Results go in a new `day_highlights` table:

| Column | Holds |
|---|---|
| `date` | the day |
| `bill_id` | the bill |
| `chamber` | where it was acted on |
| `score` | the total |
| `reasons` | JSON with each signal's value and weight |
| `date_modified` | when the row was last computed |

Queries against `bills_views`, with its 22 million rows, would be too slow to run on every page
load. Precomputing also means past days don't change unless their data changes.

## The highlight reel

Version 1 needs no new video processing:

- **Clips:** for each highlighted bill, take its `video_clips` rows for that day.
- **Long clips:** cut any clip longer than about three minutes. Keep its first 90 seconds (the
  presentation), then jump to the 60 seconds before the vote. The vote's time comes from the
  transcript: the chair says "the clerk will close the roll," or "all those in favor."
- **Playback:** play the clips in the existing video player, one after another. It moves between
  MP4s, and seeks with the `?t=` handling that video pages already have.
- **Chapters:** list one chapter per bill. A chapter links to the full meeting video at that
  moment.

Version 2 would choose excerpts from the transcript, either with heuristics or a language model. It
would find the exchange that best represents the debate: a sharp question, a close explanation of a
vote, or a witness's testimony. The excerpt would be shown as a quote, next to its clip.

## The day in brief, written by a model

`rs-machine` already writes bill narratives with an LLM (`cron/bills_status_narratives.php`). A
daily summary would work the same way:

- **Input:** the day's structured data only. That means the highlights with their reasons, the vote
  tallies, and the news headlines. No outside knowledge.
- **Output:** three to five sentences, with each claim linked to a bill.
- **Checks:** a model-written summary is shown only after every bill number and vote count in it is
  checked against the data. If a check fails, the template summary is shown instead.
- **Label:** it's marked as automatically written, as narratives should be.
- **Cost:** about 60 meeting days a year, so it's small.

This is the last phase, and it's optional. The page is useful without it.

## URLs and navigation

Two possible URL forms:

- **`/day/2026-01-26/` (recommended).** One page per day, for both chambers.
- **`/2026/01/26/`.** Shorter, but it could collide with other routes later.

The index is **`/day/`**. It's a calendar of the current session, with each meeting day linked and
shaded by how busy it was. Past sessions are at `/day/2025/`.

**Old minutes URLs** redirect permanently:

| Old | New |
|---|---|
| `/minutes/house/2026/01/26/` | `/day/2026-01-26/#house` |
| `/minutes/2026/` | `/day/2026/` |
| `/minutes/` | `/day/` |

The minutes stay on the new page. Search engines and old links keep working.

**Menu:** "Video" becomes **"Daily"** (or "Each Day," or "Day by Day"), linked to `/day/`, with the
same House and Senate entries. `/video/` stays as the full list of videos, linked from the new
section, and from each meeting block.

**The video API's `minutes` field** (in `Video::record_for_api()`) changes to point to the day's
page.

## Architecture

The architecture follows the pattern the video pages set:

- **The API serves the data.** A new endpoint, **`/1.1/day/{YYYY-MM-DD}.json`**, returns everything
  a day's page needs:
  - the highlights and their reasons
  - floor sessions
  - meetings
  - votes
  - minutes
  - news
  - comments
  - the next day's schedule

  A second endpoint, **`/1.1/days.json?year=2026`**, returns the calendar.
- **The logic lives in a new `class.Day.php`** in this repo's `htdocs/includes/`, which rs-api copies
  when it deploys, like `class.Video.php`. It reads `day_highlights` and the source tables.
- **`day.php` renders the page** from the API, as `video.php` does. Only the "Your bills" box needs
  the session and the database, and it can be loaded separately, so that the rest of the page can
  be cached for everyone.
- **Caching:** a past day's page rarely changes, so Cloudflare can keep it for a day. The current day
  should be cached for minutes, not hours. (The `/video/` pages already split caching this way.)

## When a day's data arrives

A day's page fills in over about two days, so it should never look broken while it's incomplete.

| When | What's there |
|---|---|
| Morning | The schedule and dockets |
| During the day | Votes and bill actions, as LIS publishes them and rs-machine imports them |
| That evening | Videos, then clips and transcripts, as the video processor finishes them |
| One to three days later | The minutes |
| Over the following days | News articles and comments |

Each missing piece says that it's coming, as video pages do with "The transcript is still being
prepared." The highlights are recomputed as data arrives, so the ranking of a recent day can
change. A small "Updated 4:12 p.m." line says so.

## Phases

1. **The day's page, with no ranking.**
   - Includes: the floor and committee sections, votes, the minutes, the "Coming up" section, the
     calendar, the redirects, and the new menu.
   - Ranking: none. Bills are listed by chamber and committee.
   - Value: this alone replaces Minutes, with much more on each page.
2. **Highlights.**
   - Includes: the `day_highlights` table, the cron task, the backfill, the Highlights section, the
     "In the news" section, and the "What people said" section.
   - Before it ships, tune the weights against a dozen past days that you remember.
3. **The highlight reel, version 1.** The clips, played back to back, with the vote-moment trimming.
4. **"Your bills," and a daily feed.** An RSS feed, and perhaps an email, that sends each day's brief
   and its highlights.
5. **A model-written brief, and transcript excerpts (reel version 2).**

## Open questions

- **The name.** "Daily," "Each Day," "The Day," or "Day by Day," and `/day/` or something else?
  - Answer: "Daily"
- **Both chambers on one page, or one per chamber?** One page is recommended. Most days, the
  interesting things happen in committees, and those don't divide neatly by chamber in a reader's
  mind.
    - Answer: Both chambers on one page; users don't have chambers as a mental model.
- **Is `bills_views` kept forever?** The backfill depends on it. If old rows are pruned, past days
  are ranked without page views.
    - Yes, `bills_views` is kept forever.
- **Interim meetings** (outside session). Should they get day pages? They have videos but few bill
  actions. Recommendation: yes, with only the meetings section, so that the calendar has no gaps.
    - Yes, but with only the meetings section.
- **News sources.** Two publications is thin. Adding more feeds to `articles.php`, such as the
  Richmond Times-Dispatch, VPM, or WHRO, would make the "outside attention" signal more reliable.
  The licenses and feeds would need checking.
    - Explore more sources for publications, but note that they need to publish full RSS feeds (the entire text of articles), which is uncommon. Do not incorporate any sources with approval, because human review is required.
- **Bots in view counts.** Before view counts carry much weight, check how much of `bills_views`
  comes from crawlers.
    - We have done a lot of work to reduce bills_views from tallying crawlers.
- **Is the "surprise" signal worth building?** It needs the bill's history and its committee's
  membership, so it's the most complex signal. It could wait until after the first round of tuning.
    - Surprise is not worth building now, because it's too hard.

## Risks

- **A bad highlight is worse than none.**
  - What could go wrong: the clip is misattributed (a clip's bill comes from on-screen text), or a
    news match is wrong (a story mentioned "HB 1" in passing).
  - The defense: show the reasons with every highlight, and give admins a way to hide one.
- **Rankings change as data arrives.**
  - What could go wrong: a day's highlights can change for two days, which may surprise anyone who
    linked to the page.
  - The defense: the page says when it was updated. After about two weeks, a day's scores stop
    being recomputed.
- **Model-written text.**
  - What could go wrong: the brief states something that isn't so.
  - The defense: the checks described above, and the template summary as a fallback.

## Phase 1, as built

### Pages

- **`/day/2026-01-26/`** (`htdocs/day.php`): the day's page. A URL without the trailing slash
  redirects to it. `#house` or `#senate` shows only that chamber (`htdocs/js/day.js`), and
  `#minutes-house` opens that chamber's minutes.
- **`/day/` and `/day/2026/`** (`htdocs/list-days.php`): a calendar of the year's days, a month at a
  time, shaded in four steps by how much happened (actions, videos, meetings, and floor sessions).
  `/day/` shows this year, or last year until the legislature has met.
- **`/day/latest/`** redirects to the most recent day.
- **Old minutes URLs** redirect permanently: `/minutes/house/2026/01/26/` to
  `/day/2026-01-26/#house`, `/minutes/2026/` to `/day/2026/`, and `/minutes/` to `/day/`.
  `minutes.php` and `list-minutes.php` are deleted.
- **The menu's "Video" is now "Daily,"** with "All Days," "Most Recent Day," and "Video" (`/video/`).
  It's highlighted on day and video pages.
- **Video pages** link to their day, and to their chamber's minutes on it. The video API's
  `minutes` field is now that link (`https://www.richmondsunlight.com/day/2026-01-26/#minutes-house`),
  and a new `day` field links to the day.
- **Sitemaps:** `deploy/generate_sitemaps.php` writes `days-YYYY.xml` for each year.

### What a day shows

- **A summary:** which chambers met on the floor, how many committees and subcommittees met, and
  how many bills moved forward or were stopped. (This is the "day in brief," without ranking.)
- **Each chamber's floor session:** its video, the bills it passed and defeated, its other votes,
  everything else it did (collapsed), and the clerk's minutes (collapsed). A chamber's actions
  outside of any committee (referrals, say) appear here even on days when it didn't meet on the
  floor, as "Other House Actions."
- **Each committee, subcommittee, or other meeting:** its time and place (from the schedule), its
  video, what it did with each bill (with the tally), and the rest of its docket.
- **Coming up,** on the most recent day only: the next day with anything scheduled, after today.
- **Outside a session,** only meetings. A day is in session if it falls within a session's dates, or
  if either chamber met on the floor (as in a reconvened session).

### How it works

- **The API** serves days: `/1.1/day/{YYYY-MM-DD}.json`, `/1.1/day/latest.json`, and
  `/1.1/days.json?year=2026` (rs-api's `day.php` and `days.php`). Both are cached for 15 minutes.
  They're documented in rs-api's `openapi.yaml`, and on `/about/api/`.
- **The logic** is in `htdocs/includes/class.Day.php`, which rs-api copies, as it does
  `class.Video.php`. The pages get everything from the API.
- **A day has a page** if it has a video, minutes, a docket, a meeting (other than a party caucus),
  or a decision on a bill (a vote, or an action like "Reported from" or "Continued to"), and it
  isn't in the future. The calendar and the day use the same test, so the calendar never links to a
  404.

### Reading LIS's bill actions

- **Committees are found by name, in the action's text** ("Reported from Courts of Justice"), not by
  `votes.committee_id`, which isn't reliable: floor votes can carry a committee's ID. LIS's names
  and ours differ ("Finance and Appropriations" is our "Finance"), so a name matches if it's the same
  as ours, or starts or ends with it.
- **Subcommittees aren't named** ("Subcommittee recommends reporting"), so they're found by the
  day's dockets: the subcommittee in that chamber with the bill on its docket. Failing that, by the
  vote's committee, if it's a subcommittee. Failing that, the action goes under "House
  subcommittees."
- **Schedules name subcommittees only in their descriptions** ("House Appropriations-Capital Outlay
  Subcommittee," filed under Appropriations' ID), so those are matched by name to the committee's
  subcommittees.
- **Meetings are merged by chamber and committee name,** not by ID, because committees are sometimes
  recreated with new IDs (dockets still use the Senate's old Courts of Justice).
- **Paperwork is left out:** fiscal impact statements, substitutes offered, agenda placements,
  enrolling and signing, the governor's action deadline, and the like.
- **LIS often records an action twice,** with and without its tally. Days keep one, with the tally.
- **A close vote** is one where the margin is no more than a fifth of the votes cast (8–6, or
  51–47).
- **Meetings are listed by chamber, then committee,** with subcommittees after their committee,
  rather than in the order they met: the schedule's times aren't complete enough to sort by.

### Tests

- `deploy/mysql/day-page-fixtures.sql`: actions, a roll call, dockets, and meetings for January 26,
  2026, and one Senate bill (SJ 999). It also schedules a meeting on January 14, 2099, so that the
  most recent day always has something coming up.
- `deploy/browser-tests/specs/day-page.spec.js` (it replaces `minutes-video.spec.js`),
  `deploy/tests/phpunit/DayTest.php`, rs-api's `deploy/tests/api.sh`, the page scan, and the local
  page sweep (`tests/local/PageSweepTest.php`), which loads every day page.

### Deploying

As with the video pages, rs-api's endpoints need this repo's includes on `deploy` first, and this
repo's tests need rs-api's endpoints.

1. **This repo, part 1 (includes only), to `deploy`:** `htdocs/includes/class.Day.php`,
   `htdocs/includes/class.Video.php` (the `day` and `minutes` fields), `deploy/mysql/day-page-fixtures.sql`,
   `docker-run.sh`, and `deploy/tests/phpunit/DayTest.php`.
2. **rs-api, to `master`:** `day.php`, `days.php`, `.htaccess`, `openapi.yaml`, and `api.sh`. Then
   purge Cloudflare's cache for `api.richmondsunlight.com/1.1/video/*`, since videos' `minutes`
   field changed.
3. **This repo, part 2 (everything else), to `deploy`:** the pages, routes, menu, CSS and
   JavaScript, the video page's links, the sitemap, `/about/api/`, the tests, and this document.
   Then purge Cloudflare's cache for `www.richmondsunlight.com/minutes/*`, so that the redirects
   take effect, and for the pages with the menu, or wait for them to expire.

## Phase 2, as built

### What a day shows now

- **Highlights,** after the summary: up to ten of the day's bills, best first. Each shows the bill,
  the most important thing done to it that day (with its tally), the reasons it was chosen ("Close
  vote, 45–52," "Party-line vote," "12 minutes of discussion," "5 legislators spoke," "2 news
  stories," "The most-viewed of the day's bills," "3 comments," "Added to 4 Photosynthesis
  portfolios"), and a link to the longest discussion of it on video. Highlights follow the House
  and Senate filter. For two weeks, a line says when they were chosen, and that they can change.
- **What People Said,** after the meetings: comments posted that day on the day's bills, how those
  bills are doing in our polls (if they have at least ten votes), and the five bills of that year
  that the most people looked at that day (whether or not anything was done to them).
- **In the News,** after that: stories about the day's bills, from two days before to three days
  after, with the bills each one is about.
- **No highlights outside of a session,** as for floor sessions.

### How highlights are chosen

The scoring is in `htdocs/includes/class.DayHighlights.php`. Its `WEIGHTS` are the settings to
tune. It scores every bill that the day's page shows an action on, using the same reading of LIS's
actions as the page (`Day::bills_acted_on()`), so the two can't disagree.

| Signal | Points |
|---|---|
| What was done to it (its most important action that day) | Passed or failed on the floor, or vetoed: 10. Stopped in committee (continued, passed by indefinitely, tabled, defeated): 8. Reported: 6. A subcommittee's recommendation: 4. The governor's amendments, and acting on them: 4. A vote on a conference report: 3. Incorporated: 1. Anything else (readings, referrals, appointing conferees): 0, and the bill can't be a highlight. |
| Its closest vote | 12 × (1 − margin ÷ votes cast)³: 51–47 is worth 10.6, and 9–5 is worth 4.4. |
| Its most partisan roll call | 10 × how far apart the parties voted, from 0 (each party split the same way) to 1 (every Democrat one way, every Republican the other). Each legislator's party is the one in the term they were serving that day. |
| How long it was discussed | 4 × log₂(1 + minutes), from its chyrons on the day's videos. Chyrons more than 30 seconds apart are separate discussions. |
| Who discussed it | 2 for each legislator whose chyron appeared during the discussions, after the first, up to 8 legislators. |
| News stories | 8 × log₂(1 + stories), from three days before the day to three days after. |
| Interest here | 2.5 × log₂(1 + hotness), with hotness's formula (from rs-machine's `cleanup.php`) counted for the day and the next. |
| Interestingness | 0.5 × log₂(1 + interestingness), as a tiebreaker. |

Then the highlights are chosen from the bills that can be highlights:

- The best ten, but past the fifth, only those with at least half the top bill's score.
- No more than two of any legislator's bills.
- At least two from each chamber that did anything, if it has them, replacing the weakest of a
  chamber that has more than two.
- Never a ceremonial resolution ("Commending…," "Celebrating the life of…").

Differences from the proposal:

- **`votes.partisanship` isn't used.** Its own comments say that it mismeasures party-line votes
  (both parties voting the same way can count as partisan). Party-line voting is measured from
  `representatives_votes` instead. Until rs-machine's `refetch_votes.php` has been run for a
  session, some of its roll calls have voters stored under term IDs, so a few votes may count the
  wrong party.
- **`video_clips` isn't used.** It hasn't been written since 2020. Discussions are found by bills'
  chyrons in `video_index`, as bill pages find them.
- **Public testimony** isn't detected. It needs testing against transcripts first.
- **The "surprise" signal** isn't built (as decided).

### Where it's stored and computed

- **`day_highlights`** (in `deploy/mysql/structure.sql`; `deploy/migrate-day-highlights.sql`
  creates it in production) has a row for every bill scored on every day: its chamber, score,
  position among the highlights (or NULL), whether an admin hid it, and its reasons (JSON: each
  signal's value and points, the labels readers see, the action, the bill, and the clip).
- **rs-machine's `cron/day_highlights.php`** (task `day_highlights`) scores today and yesterday
  hourly (6 a.m. to 11 p.m.), and the past two weeks at 3 a.m. Earlier days don't change.
- **rs-machine's `utilities/backfill_day_highlights.php [first year] [last year]`** scores every day
  since 2008. Run it once after deploying, and again whenever the weights change. Locally, all of
  2026's days took half a second.
- **The API** adds `highlights`, `highlights_updated`, `news`, and `site_activity` to
  `/1.1/day/{date}.json` (documented in rs-api's `openapi.yaml` and on `/about/api/`). Before
  `day_highlights` exists, `highlights` is empty, and nothing breaks.

### Hiding a highlight, and tuning

- **`/admin/day-highlights.php?date=2026-01-26`** lists every bill scored that day, with each
  signal's points, and a button to hide a highlight (a clip of the wrong bill, say, or a news story
  that only mentions it), or to show it again. Hiding one chooses the day's highlights again, and
  it stays hidden when rs-machine scores the day again.
- **To tune the weights,** look at a dozen days you remember on that page. Then change `WEIGHTS`
  (and the selection constants near it), deploy, and run the backfill again. Locally,
  `php deploy/day-highlights.php [--all-bills] 2026-02-16` does the same from the command line.

### News sources

`articles.php` reads Virginia Mercury and Cardinal News. In November 2026, other Virginia
publications' feeds were checked for full text (the whole article in each item, which matching
bill numbers needs). None has been added: each needs a person's review first.

| Publication | Feed | Full text? | Notes |
|---|---|---|---|
| VPM News | `https://www.vpm.org/index.rss` | Yes | Public radio; covers the legislature. The strongest candidate. |
| Virginia Scope | `https://www.virginiascope.com/feed/` | Yes | State politics. The feed is slow (over 20 seconds). |
| Virginia Dogwood | `https://vadogwood.com/feed/` | Yes | State politics, from a progressive network (Courier). Slow feed. |
| Virginia Business | `https://virginiabusiness.com/feed/` | Yes | Business, and policy affecting it. |
| Richmond BizSense | `https://richmondbizsense.com/feed/` | Yes | Richmond business; little legislative news. |
| The Richmonder | `https://www.richmonder.org/rss/` | Yes | Richmond city news. |
| Charlottesville Tomorrow | `https://www.cvilletomorrow.org/feed/` | Yes | Charlottesville news. |
| Roanoke Rambler | `https://roanokerambler.com/feed/` | Yes | Roanoke news. |
| Bacon's Rebellion | `https://www.baconsrebellion.com/feed/` | Yes | Opinion blog (conservative-leaning). |
| Blue Virginia | `https://bluevirginia.us/feed` | Yes | Opinion blog (Democratic). |
| WAMU | `https://wamu.org/feed/` | Partly | Some items are full, most aren't. |
| Augusta Free Press, Royal Examiner, WRIC, ARLnow, FFXnow, The Daily Progress, Chesapeake Bay Journal | | No | Excerpts only. |
| Richmond Times-Dispatch, Virginian-Pilot, Fauquier Times, Rappahannock News, VCU Capital News Service, VPAP | | Couldn't tell | Their feeds refused automated requests (403 or 429). |
| WHRO, Radio IQ | | Couldn't tell | No feed found at the usual address. |

Outside of session, few stories mention bills (Mercury had 3 of 100), so how often each source
names bills is better judged in January. Adding a source is one line in `articles.php`'s `$feeds`.

### Tests

- `deploy/mysql/day-page-fixtures.sql` gives HB 341 a news story, a comment, page views, and poll
  votes. `deploy/docker-setup-site.sh` chooses that day's highlights, with
  `deploy/day-highlights.php`.
- `deploy/tests/phpunit/DayHighlightsTest.php`: the signals, the choosing, the scoring of the
  fixtures' day, storing and hiding, and the news and site activity.
- `deploy/browser-tests/specs/day-page.spec.js` and rs-api's `deploy/tests/api.sh` check the new
  sections and fields.

### Deploying

Phase 1 goes first. Then:

1. **Production database:** run `deploy/migrate-day-highlights.sql`. It only creates a table.
2. **This repo, part 1, to `deploy`:** `htdocs/includes/class.Day.php`,
   `htdocs/includes/class.DayHighlights.php`, `deploy/mysql/structure.sql`,
   `deploy/mysql/day-page-fixtures.sql`, `deploy/migrate-day-highlights.sql`,
   `deploy/day-highlights.php`, `deploy/docker-setup-site.sh`, and
   `deploy/tests/phpunit/DayHighlightsTest.php`. The API starts returning the new fields when rs-api
   next deploys (it copies the includes), and rs-machine gets the class.
3. **rs-api, to `master`:** `openapi.yaml` and `deploy/tests/api.sh`. (This repo's CI runs rs-api's
   `api.sh`, and its new checks need part 1's fixtures and setup.)
4. **rs-machine, to `master`:** `cron/day_highlights.php`, `cron/tasks.php`, and
   `utilities/backfill_day_highlights.php`. Then run the backfill once:
   `php utilities/backfill_day_highlights.php`.
5. **This repo, part 2, to `deploy`:** `htdocs/day.php`, `htdocs/css/page-elements.css`,
   `htdocs/admin/day-highlights.php`, `htdocs/about-api.php`, the browser tests, `CLAUDE.md`, and
   this document.
