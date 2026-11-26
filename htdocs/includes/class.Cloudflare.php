<?php

/**
 * Expire pages from Cloudflare's cache, so that a change (a comment or a tag, added or removed) is
 * seen by visitors without a session, who are otherwise served a copy for up to 10 minutes, or a
 * week for an old bill (see Page::display()), plus a while longer as it's refetched.
 *
 * This happens only if CLOUDFLARE_API_TOKEN and CLOUDFLARE_ZONE_ID are set. The token needs the
 * Zone > Cache Purge permission, for the site's zone. A failed purge is logged, but never stops
 * the change itself: the page will expire on its own, in time.
 */
class Cloudflare
{
    /**
     * The most URLs that Cloudflare will purge in one request.
     */
    const MAX_URLS = 30;

    /**
     * Cloudflare's API.
     */
    const ENDPOINT = 'https://api.cloudflare.com/client/v4/zones/';

    /**
     * The token, zone, and API to use instead of CLOUDFLARE_API_TOKEN, CLOUDFLARE_ZONE_ID, and
     * ENDPOINT, if they've been set by use() (only tests do that).
     */
    private static ?array $settings = null;

    /**
     * Use this token, zone, and API, instead of the site's settings, until use() is called again
     * without them. For tests.
     */
    public static function use(?string $token = null, ?string $zone = null, ?string $endpoint = null): void
    {
        self::$settings = $token === null ? null : [
            'token' => $token,
            'zone' => (string) $zone,
            'endpoint' => $endpoint ?? self::ENDPOINT,
        ];
    }

    /**
     * The token, zone, and API to use.
     */
    private static function settings(): array
    {
        return self::$settings ?? [
            'token' => defined('CLOUDFLARE_API_TOKEN') ? (string) CLOUDFLARE_API_TOKEN : '',
            'zone' => defined('CLOUDFLARE_ZONE_ID') ? (string) CLOUDFLARE_ZONE_ID : '',
            'endpoint' => self::ENDPOINT,
        ];
    }

    /**
     * Whether Cloudflare's cache can be purged here.
     */
    public static function enabled(): bool
    {
        $settings = self::settings();
        return $settings['token'] != '' && $settings['zone'] != '';
    }

    /**
     * The URLs that Cloudflare may have cached for each path. It caches http and https copies
     * separately, so both are purged.
     *
     * @param string[] $paths Paths on this site, like "/bill/2026/hb1/".
     * @return string[]
     */
    public static function urls(array $paths): array
    {
        $host = parse_url(SITE_BASE_URL, PHP_URL_HOST);
        $urls = [];
        foreach (array_unique($paths) as $path) {
            foreach (['https', 'http'] as $scheme) {
                $urls[] = $scheme . '://' . $host . $path;
            }
        }
        return $urls;
    }

    /**
     * The path of each bill's page, by its ID.
     *
     * @param int[] $bill_ids
     * @return string[]
     */
    public static function bill_paths(array $bill_ids): array
    {
        $bill_ids = array_values(array_unique(array_map('intval', $bill_ids)));
        if (count($bill_ids) == 0) {
            return [];
        }

        $sql = 'SELECT bills.number, sessions.year
                FROM bills
                LEFT JOIN sessions ON bills.session_id = sessions.id
                WHERE bills.id IN (' . implode(', ', array_fill(0, count($bill_ids), '?')) . ')';
        $result = mysqli_execute_query($GLOBALS['db'], $sql, $bill_ids);
        if ($result === false) {
            return [];
        }

        $paths = [];
        while ($bill = mysqli_fetch_assoc($result)) {
            $paths[] = '/bill/' . $bill['year'] . '/' . strtolower($bill['number']) . '/';
        }
        return $paths;
    }

    /**
     * Expire the pages of these bills, and the home page, which lists recent comments and bills.
     *
     * @param int[] $bill_ids
     * @return bool Whether they were all purged (false, too, if purging isn't enabled).
     */
    public static function purge_bills(array $bill_ids): bool
    {
        if (!self::enabled()) {
            return false;
        }
        return self::purge(array_merge(['/'], self::bill_paths($bill_ids)));
    }

    /**
     * Expire these paths from Cloudflare's cache.
     *
     * @param string[] $paths Paths on this site, like "/bill/2026/hb1/".
     * @return bool Whether they were all purged (false, too, if purging isn't enabled).
     */
    public static function purge(array $paths): bool
    {
        if (!self::enabled()) {
            return false;
        }

        $purged = true;
        foreach (array_chunk(self::urls($paths), self::MAX_URLS) as $urls) {
            $error = self::request($urls);
            if ($error !== null) {
                $log = new Log();
                $log->put('Cloudflare’s cache of ' . implode(', ', $urls) . ' couldn’t be purged: '
                    . $error, 5);
                $purged = false;
            }
        }
        return $purged;
    }

    /**
     * Ask Cloudflare to purge these URLs. This is done while the visitor waits, so it gives up
     * quickly.
     *
     * @param string[] $urls
     * @return string|null Why it failed, or null if it didn't.
     */
    private static function request(array $urls): ?string
    {
        $settings = self::settings();
        $ch = curl_init($settings['endpoint'] . rawurlencode($settings['zone']) . '/purge_cache');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $settings['token'],
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['files' => $urls]));

        $response = curl_exec($ch);
        if ($response === false) {
            return curl_error($ch);
        }

        $decoded = json_decode($response, true);
        if (($decoded['success'] ?? false) !== true) {
            return $decoded['errors'][0]['message'] ?? ('HTTP ' . curl_getinfo($ch, CURLINFO_RESPONSE_CODE));
        }
        return null;
    }
}
