# CLAUDE.md

Supplements AGENTS.md with coding-level gotchas and conventions.

## Legislator Data Model

There are three overlapping tables for legislators. Use the right one:

| Table | Status | Use for |
|-------|--------|---------|
| `people` | Current | Canonical identity — `shortname`, `name`, `sex`, `race`, `bio` |
| `terms` | Current | Per-term data — `chamber`, `party`, `district_id`, `name_formatted`, `lis_id`. FK to `people.id` via `terms.person_id` |
| `representatives` | **Legacy** | Older queries only. Do not use for new code. |

New code should JOIN `people` + `terms`. `video_index.linked_id` references `terms.id` when `type = 'legislator'`.

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
