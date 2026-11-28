<?php

/**
 * Updates the session settings in htdocs/includes/settings-default.inc.php to what they should be
 * (see deploy/SessionSettings.php), for .github/workflows/session-update.yml, which proposes the
 * change as a pull request.
 *
 * Needs LIS_KEY in the environment. In GitHub Actions, it writes `changed`, `branch` and `title`
 * to GITHUB_OUTPUT, and the pull request's description to the file named by `body`.
 *
 * Usage: LIS_KEY=… php deploy/propose_session.php [--dry-run] [--sessions=<a copy of sessions.json>]
 */

declare(strict_types=1);

require_once __DIR__ . '/SessionSettings.php';

const SETTINGS_FILE = __DIR__ . '/../htdocs/includes/settings-default.inc.php';
const SESSIONS_URL = 'https://downloads.richmondsunlight.com/sessions.json';
const LIS_SESSIONS_URL = 'https://lis.virginia.gov/Session/api/GetSessionListAsync';

$dryRun = in_array('--dry-run', $argv, true);

function fail(string $message): never
{
    fwrite(STDERR, '::error::' . $message . "\n");
    exit(1);
}

/** @return array{0:int,1:string} The HTTP status and the body. */
function fetch(string $url, array $headers = [], bool $head = false): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_NOBODY         => $head,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => 60,
    ]);
    $body = curl_exec($ch);
    if ($body === false) {
        fail('Could not reach ' . $url . ': ' . curl_error($ch));
    }

    return [(int) curl_getinfo($ch, CURLINFO_HTTP_CODE), (string) $body];
}

function output(string $name, string $value): void
{
    $file = getenv('GITHUB_OUTPUT');
    if ($file !== false && $file !== '') {
        file_put_contents($file, $name . '=' . $value . "\n", FILE_APPEND);
    }
}

$key = getenv('LIS_KEY');
if ($key === false || $key === '') {
    fail('LIS_KEY must be set');
}

[$status, $body] = fetch(LIS_SESSIONS_URL, ['Accept: application/json', 'WebAPIKey: ' . $key]);
$lis = json_decode($body, true);
if ($status !== 200 || !is_array($lis['Sessions'] ?? null) || $lis['Sessions'] === []) {
    fail('LIS’s GetSessionListAsync returned an HTTP ' . $status . ' and no sessions');
}

$copy = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--sessions=')) {
        $copy = substr($arg, strlen('--sessions='));
    }
}
[$status, $body] = $copy === null ? fetch(SESSIONS_URL) : [200, (string) file_get_contents($copy)];
$published = json_decode($body, true);
if ($status !== 200 || !is_array($published) || $published === []) {
    fail(SESSIONS_URL . ' returned an HTTP ' . $status . ' and no sessions');
}

$hasBills = static function (string $code): bool {
    [$status] = fetch('https://lis.blob.core.windows.net/lisfiles/' . $code . '/BILLS.CSV', [], true);
    return $status === 200;
};

$file = file_get_contents(SETTINGS_FILE);
if ($file === false) {
    fail('Could not read ' . SETTINGS_FILE);
}

try {
    $current = SessionSettings::read($file);
    $proposal = SessionSettings::propose(
        $current,
        $lis['Sessions'],
        $published,
        $hasBills,
        new DateTimeImmutable('today', new DateTimeZone('America/New_York'))
    );
    $updated = SessionSettings::write($file, $proposal['values']);
} catch (Throwable $e) {
    fail($e->getMessage());
}

foreach ($proposal['notes'] as $note) {
    echo '::notice::' . $note . "\n";
}

$changed = array_diff_assoc($proposal['values'], $current);
if ($changed === []) {
    echo 'The settings are up to date, for the ' . $proposal['session'] . ".\n";
    output('changed', 'false');
    exit(0);
}

$title = $proposal['switch']
    ? 'Switch to the ' . $proposal['session']
    : 'Update the ' . $proposal['session'] . '’s settings';

$rows = [];
foreach ($proposal['values'] as $name => $value) {
    $rows[] = '| `' . $name . '` | ' . ($current[$name] === '' ? '(empty)' : '`' . $current[$name] . '`') . ' | '
        . ($value === '' ? '(empty)' : '`' . $value . '`') . (isset($changed[$name]) ? ' ✏️' : '') . ' |';
}
$description = implode("\n", array_merge([
    'Proposed by the session-update workflow (`.github/workflows/session-update.yml`), from LIS’s '
        . 'list of sessions and [sessions.json](' . SESSIONS_URL . '), which rs-machine writes from the '
        . 'sessions table.',
    '',
    '| Setting | Now | Proposed |',
    '|---|---|---|',
], $rows, $proposal['notes'] === [] ? [] : array_merge([''], array_map(
    static fn($note) => '- ' . $note,
    $proposal['notes']
)), [
    '',
    'Once this reaches `deploy`, rs-machine picks it up at its next build (daily, at 4 AM UTC).',
]));

echo $title . "\n\n" . $description . "\n";

if ($dryRun) {
    exit(0);
}

if (file_put_contents(SETTINGS_FILE, $updated) === false) {
    fail('Could not write ' . SETTINGS_FILE);
}
$bodyFile = tempnam(sys_get_temp_dir(), 'session-pr-');
file_put_contents($bodyFile, $description . "\n");

output('changed', 'true');
output('branch', 'session-settings/' . $proposal['values']['SESSION_LIS_ID']);
output('title', $title);
output('body', $bodyFile);
