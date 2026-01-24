# AGENTS.md

This file provides guidance to LLMs when working with code in this repository.

## Overview

Richmond Sunlight is a PHP-based website that tracks Virginia General Assembly legislation. The codebase has evolved over ~20 years and shows a mix of modern practices (Docker, CI/CD, SOA) alongside legacy patterns. The front-end (this repository) works with several companion systems: rs-api (API), rs-machine (scrapers/parsers), and rs-video-processor.

## Development Environment

### Local Development with Docker

Start the local environment:
```bash
./docker-run.sh
```

The site will be available at `http://localhost:8000`. The script will:
- Download the rs-api repository if not present
- Concatenate database dumps into a single file
- Build and start Docker containers (web, api, db, memcached)
- Wait for MariaDB to be available
- Run site setup scripts

Stop the environment:
```bash
./docker-stop.sh
```

### Running Tests

Run all tests (unit, integration, browser, security):
```bash
./docker-tests.sh
```

Run tests with full OWASP ZAP scan:
```bash
./docker-tests.sh --zap-full-scan
```

Skip browser tests:
```bash
./docker-tests.sh --no-browser-tests
```

Individual test scripts (run inside container):
```bash
# Inside the web container
/var/www/deploy/tests/run-all.sh       # All PHP tests
/var/www/deploy/tests/page-scan.php    # Page scanning
/var/www/deploy/tests/bill-test.php    # Bill unit tests
/var/www/deploy/tests/legislator-test.php  # Legislator tests
/var/www/deploy/tests/api.sh           # API tests
```

### Dependency Management

Install PHP dependencies:
```bash
composer install
```

Install JavaScript dependencies:
```bash
cd htdocs/js/vendor
yarn install && yarn build
```

### Code Quality

PHP linting (runs in CI):
```bash
find htdocs -name '*.php' -print0 | xargs -0 -n1 -P8 php -l
```

Pre-commit hooks are configured in `.pre-commit-config.yaml` for shell linting, PHP linting, and PHP-CS-Fixer.

## Architecture

### Directory Structure

- **htdocs/** - Public web root containing the front-end application
  - **includes/** - PHP classes, functions, templates, and vendor dependencies
    - **class.*.php** - Class files (autoloaded)
    - **functions.inc.php** - Non-class utility functions
    - **settings.inc.php** - Configuration constants
    - **templates/** - HTML templates
    - **vendor/** - Composer dependencies
  - **css/** - Stylesheets
  - **js/** - JavaScript and client-side dependencies
  - **images/** - Static assets (legislator photos, district maps)
  - Page files (bill.php, legislator.php, etc.)
- **api/** - Separate API application (rs-api repository, downloaded during docker-run.sh)
- **deploy/** - Deployment scripts, database schemas, tests
  - **mysql/** - Database schema and seed data
  - **browser-tests/** - Playwright browser tests
  - **tests/** - PHP unit and integration tests
- **.github/workflows/** - CI/CD pipeline configuration

### Core PHP Classes

Classes live in `htdocs/includes/class.*.php` and are autoloaded via SPL:

- **Bill2** - Bill lookup, enrichment, related data (named "Bill2" to avoid naming conflicts)
- **Legislator** - Member lookup and details
- **User** - User queries, personalization, bill recommendations, tagging
- **Database** - Database connection management (PDO and MySQLi)
- **Page** - Template rendering and page assembly
- **Committee** - Committee details and membership
- **Comments** - Aggregates comments from direct posts and Photosynthesis notes
- **Video** - Legislative video interaction and metadata
- **Vote** - Vote tallies and detailed voting records
- **Import** - Imports/normalizes data from external sources (LIS API)
- **Log** - Multi-backend logging (filesystem, Slack, stdout)
- **CommentSubscription** - Comment notification management

### Page Structure Pattern

Each public-facing PHP file follows this pattern:
```php
1. Include settings.inc.php and autoload
2. Initialize session
3. Connect to database
4. Localize/clean request variables
5. Fetch data (from API or database)
6. Build page content ($page_body, $page_sidebar)
7. Set metadata ($page_title, $html_head)
8. Use Page class to assemble and display
```

### Routing

Apache mod_rewrite (`.htaccess`) handles all routing, transforming clean URLs to PHP scripts:
- `/bill/2024/hb123/` → `bill.php?year=2024&bill=hb123`
- `/legislator/john-doe/` → `legislator.php?shortname=john-doe`

### Database Interaction

Database class provides both PDO and MySQLi connections. The pattern is:
```php
$database = new Database();
$database->connect_mysqli();
$sql = 'SELECT ...';
$result = mysqli_query($GLOBALS['db'], $sql);
```

All user input must be escaped with `mysqli_real_escape_string()`.

Database schemas are in `deploy/mysql/`:
- **structure.sql** - Table definitions
- **basic-contents.sql** - Core data (sessions, VA Code sections)
- **test-records.sql** - Test data for development
- **test-users.sql** - Test user accounts

### API Integration

The front-end communicates with rs-api for most data. API endpoints (v1.1):
```
/1.1/bill/{year}/{number}.json
/1.1/bills/{year}.json
/1.1/legislator/{shortname}.json
/1.1/legislators.json
/1.1/vote/{lis_id}.json
/1.1/code-section/{section}.json
```

Pattern for calling the API:
```php
$json_url = API_URL . '1.1/bill/' . $year . '/' . $bill . '.json';
$json = get_content($json_url);  // CURL wrapper in functions.inc.php
$bill = json_decode($json);
```

### Caching Strategy

Memcached is used extensively:
- Bill IDs by number (24 hours)
- Legislator data (24 hours)
- User sessions (session duration)
- Templates (24 hours)
- User recommendations (30 minutes)

### Template System

Templates use placeholder replacement:
- Main template: `htdocs/includes/templates/new.inc.php`
- Placeholders: `%browser_title%`, `%page_body%`, `%page_sidebar%`, etc.
- Templates cached in Memcached for 24 hours

### Configuration

Settings are in `htdocs/includes/settings.inc.php` (git-ignored). Copy from `settings-default.inc.php` to create. Key constants:
- `SESSION_ID`, `SESSION_YEAR` - Current legislative session
- `PDO_DSN` - Database connection string
- `API_URL` - rs-api endpoint
- `MEMCACHED_SERVER` - Cache server host/port
- `AWS_*` - AWS credentials for S3/SQS

For Docker, use `deploy/settings-docker.inc.php`.

## Deployment

### Branches

- **master** → staging.richmondsunlight.com (auto-deployed)
- **deploy** → www.richmondsunlight.com (auto-deployed)

### CI/CD Pipeline

GitHub Actions workflow (`.github/workflows/deploy.yml`):

1. **Lint and Build** - PHP syntax check, Composer install, Yarn build
2. **Integration Tests** - Docker environment, unit tests, browser tests (Playwright), OWASP ZAP security scan
3. **Deploy** - AWS CodeDeploy to staging (master) or production (deploy)

The pipeline runs on:
- Push to master or deploy
- Pull requests
- Daily at 4 AM UTC (scheduled redeploy)

### Test Levels

- **Unit tests** - Bill, Legislator, and API response validation (`deploy/tests/*.php`)
- **Integration tests** - Page scanning for errors/warnings (`deploy/tests/page-scan.php`)
- **Browser tests** - Playwright tests for user flows (`deploy/browser-tests/*.spec.ts`)
- **Security tests** - OWASP ZAP baseline scan (full scan on schedule and deploy PRs)

## Important Patterns

### Naming Conventions

- Classes: PascalCase (e.g., `class.Bill2.php`)
- Methods: snake_case (e.g., `get_list()`)
- Database tables: snake_case
- URLs: kebab-case with trailing slashes

### Security Practices

- `mysqli_real_escape_string()` for all user input
- HTMLPurifier for comment sanitization
- Session management via Memcached
- CSRF protection on forms
- Bot detection for analytics

### Data Flow

1. **External Sources** (LIS API) → **Import class** → **Database**
2. **Database** → **API endpoints** → **Front-end pages**
3. **User Input** → **Process scripts** → **Database** → **Page refresh**

### Photosynthesis (Bill Tracking)

Photosynthesis is the premium bill-tracking feature:
- User portfolios (manual and "smart"/automated)
- Portfolio notes become public comments
- Email notifications on bill changes
- URLs use 5-character hashes for portfolios

### Video System

Legislative videos from Archive.org:
- Video indexing links timestamps to bills/legislators
- Transcript storage and search
- Clip generation with unique hashes
- SQS queue for video processing jobs (rs-video-processor)

## Common Tasks

### Adding a New Page

1. Create PHP file in `htdocs/`
2. Follow the standard page structure pattern
3. Add routing rules to `htdocs/.htaccess`
4. Add to `deploy/tests/page-scan.php` for automated testing

### Modifying a Class

1. Edit `htdocs/includes/class.*.php`
2. Classes are autoloaded - no need to modify includes
3. Add tests to `deploy/tests/` if applicable
4. Test locally with `./docker-run.sh` and `./docker-tests.sh`

### Database Changes

1. Modify `deploy/mysql/structure.sql` for schema changes
2. Update seed data in `deploy/mysql/basic-contents.sql` if needed
3. Test with fresh Docker build: `docker compose down -v && ./docker-run.sh`
4. Document migration path for production database

### Updating Dependencies

PHP:
```bash
composer update
composer install
```

JavaScript:
```bash
cd htdocs/js/vendor
yarn upgrade
yarn build
```

### Working with the API

The API is a separate repository (openva/rs-api) but shares class files:
- API lives in `api/` directory (auto-downloaded by docker-run.sh)
- API uses same database and classes as front-end
- Changes to `htdocs/includes/class.*.php` affect both front-end and API
- Test API changes with: `./deploy/tests/api.sh`
