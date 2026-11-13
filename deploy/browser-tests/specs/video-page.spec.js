const fs = require('fs');
const path = require('path');
const { test, expect } = require('@playwright/test');

// The videos are in deploy/mysql/video-minutes-fixtures.sql and video-page-fixtures.sql, and their
// slugs are set by zz-video-slugs.sql. All were recorded on January 26, 2026.
const floor = '/video/b334/2026-01-26-house-floor/';
const committee = '/video/fcmk/2026-01-26-house-appropriations/';
const subcommittee = '/video/fcmm/2026-01-26-house-appropriations-capital-outlay/';
const processing = '/video/fcmn/2026-01-26-house-rules/';

// The fixtures' videos are elsewhere (or don't exist), and Playwright's Chromium can't play MP4,
// so serve a blank, hour-long WebM in their place. It's served in ranges, as video hosts do, because
// a browser can't seek in a video that isn't.
const blank = fs.readFileSync(path.join(__dirname, '..', 'fixtures', 'blank.webm'));
async function serveBlankVideo(page) {
  await page.route(/\.mp4(?:[?#]|$)/, (route) => {
    const range = /bytes=(\d+)-(\d*)/.exec(route.request().headers().range || '');
    const start = range ? parseInt(range[1], 10) : 0;
    const end = range && range[2] ? Math.min(parseInt(range[2], 10), blank.length - 1) : blank.length - 1;
    return route.fulfill({
      status: range ? 206 : 200,
      contentType: 'video/webm',
      headers: {
        'Accept-Ranges': 'bytes',
        ...(range ? { 'Content-Range': `bytes ${start}-${end}/${blank.length}` } : {}),
      },
      body: blank.subarray(start, end + 1),
    });
  });
}

async function currentTime(page) {
  return page.locator('#video video').evaluate((video) => video.currentTime);
}

test.describe('Video page', () => {
  test('plays a floor session, with its bills, speakers, and transcript', async ({ page }) => {
    await page.goto(floor);

    await expect(page).toHaveTitle(/House floor session, January 26, 2026/);
    await expect(page.locator('#video video')).toHaveCount(1);
    await expect(page.locator('#video-bills li')).toHaveCount(14);
    await expect(page.locator('#video-bills li').first()).toContainText('HB 1384');

    await page.locator('#tab-video-speakers').click();
    // Her people.id (567) is not her terms.id (560).
    await expect(page.locator('#video-speakers a[href="/legislator/jlanderson/"]')).toHaveText(
      'Del. Jessica Anderson (D-Williamsburg)'
    );

    await page.locator('#tab-transcript').click();
    await expect(page.locator('#transcript')).toContainText('House Bill 176');
  });

  test('describes itself for search engines and link previews', async ({ page }) => {
    await page.goto(floor);

    await expect(page.locator('link[rel="canonical"]')).toHaveAttribute(
      'href',
      'https://www.richmondsunlight.com' + floor
    );
    await expect(page.locator('meta[property="og:video"]')).toHaveAttribute('content', /^https:\/\//);
    const schema = JSON.parse(await page.locator('script[type="application/ld+json"]').textContent());
    expect(schema['@type']).toBe('VideoObject');
    expect(schema.uploadDate).toBe('2026-01-26');
    expect(schema.duration).toBe('PT0H59M35S');
    expect(schema.thumbnailUrl).toMatch(/^https:\/\/video\.richmondsunlight\.com\/.+\.jpg$/);
    expect(schema.transcript.length).toBeGreaterThan(0);
  });

  test('offers captions from this site', async ({ page, request }) => {
    await page.goto(floor);
    await expect(page.locator('#video video track[kind="captions"]')).toHaveAttribute(
      'src',
      '/video/b334/captions.vtt'
    );

    const captions = await request.get('/video/b334/captions.vtt');
    expect(captions.status()).toBe(200);
    expect(captions.headers()['content-type']).toContain('text/vtt');
    expect(await captions.text()).toMatch(/^WEBVTT/);

    expect((await request.get('/video/fcmn/captions.vtt')).status()).toBe(404);
  });

  test('starts at the time in ?t=', async ({ page }) => {
    await serveBlankVideo(page);
    await page.goto(floor + '?t=90');
    await expect.poll(() => currentTime(page)).toBeCloseTo(90, 0);
  });

  test('plays from a bill when its timestamp is chosen', async ({ page }) => {
    await serveBlankVideo(page);
    await page.goto(floor);

    const first = page.locator('#video-bills li').first();
    const start = parseFloat(await first.getAttribute('data-start'));
    await first.locator('.video-timestamp').click();
    await expect.poll(() => currentTime(page)).toBeGreaterThanOrEqual(start);
    await expect.poll(() => currentTime(page)).toBeLessThan(start + 30);
  });

  test('keeps the time being watched in the address bar', async ({ page }) => {
    await serveBlankVideo(page);
    await page.goto(floor);

    const first = page.locator('#video-bills li').first();
    const start = parseFloat(await first.getAttribute('data-start'));
    await first.locator('.video-timestamp').click();
    await expect.poll(() => parseInt(new URL(page.url()).searchParams.get('t') || '0', 10))
      .toBeGreaterThanOrEqual(Math.floor(start));
    expect(new URL(page.url()).pathname).toBe(floor);
  });

  test('sends any other URL for a video to its own', async ({ page, request }) => {
    for (const [from, to] of [
      ['/video/b334/', floor],
      ['/video/b334/wrong-suffix/', floor],
      ['/video/b334/2026-01-26-house-floor', floor],
      ['/video/b334/?t=30', floor + '?t=30'],
    ]) {
      const response = await request.get(from, { maxRedirects: 0 });
      expect(response.status(), from).toBe(301);
      expect(new URL(response.headers().location, 'http://x').pathname
        + new URL(response.headers().location, 'http://x').search, from).toBe(to);
    }

    // Slugs are lowercase.
    await page.goto('/video/B334/');
    expect(new URL(page.url()).pathname).toBe(floor);
  });

  test('is a 404 for a slug that doesn’t exist', async ({ request }) => {
    expect((await request.get('/video/bbbb/')).status()).toBe(404);
    expect((await request.get('/video/zzzz/2026-01-26-house-floor/')).status()).toBe(404);
  });

  test('shows a video that’s still being processed, without a player', async ({ page }) => {
    await page.goto(processing);

    await expect(page.locator('.video-processing')).toContainText('still being processed');
    await expect(page.locator('video')).toHaveCount(0);
    await expect(page.locator('.video-summary')).toContainText('House Rules Committee');
    await expect(page.locator('.video-summary')).toContainText('Monday, January 26, 2026');
  });

  test('links a committee meeting to its committee, and says what’s still being prepared', async ({ page }) => {
    await page.goto(committee);

    await expect(page.locator('#content a[href="/committee/house/appropriations/"]').first()).toBeVisible();
    await expect(page.locator('.video-player-pending')).toHaveText('The transcript is still being prepared.');
  });

  test('links a subcommittee meeting to its parent committee', async ({ page }) => {
    await page.goto(subcommittee);

    await expect(page).toHaveTitle(/House Appropriations: Capital Outlay Subcommittee/);
    await expect(page.locator('#content a[href="/committee/house/appropriations/"]').first()).toBeVisible();
  });

  test('lists the day’s other videos, and links to its day and its minutes', async ({ page }) => {
    await page.goto(committee);

    const others = page.locator('.video-same-day li');
    await expect(others).toHaveCount(3);
    await expect(others.locator(`a[href="${floor}"]`)).toHaveText('House floor session');
    await expect(others.filter({ hasText: 'House Rules Committee' })).toContainText('processing');
    await expect(page.locator('a[href="/day/2026-01-26/"]')).toBeVisible();
    await expect(page.locator('a[href="/day/2026-01-26/#minutes-house"]')).toBeVisible();
  });
});

test.describe('Lists of videos', () => {
  test('lists a year’s videos by chamber and committee', async ({ page }) => {
    await page.goto('/video/2026/');

    await expect(page.locator('summary', { hasText: 'Floor Sessions' }).first()).toBeVisible();
    const appropriations = page.locator('details', { has: page.locator('summary', { hasText: 'Appropriations' }) });
    await appropriations.locator('summary').click();
    await expect(appropriations.locator(`a[href="${subcommittee}"]`)).toHaveText(
      'January 26: Capital Outlay Subcommittee'
    );
  });

  test('lists a committee’s meetings on its page, with its subcommittees’', async ({ page }) => {
    await page.goto('/committee/house/appropriations/');

    const videos = page.locator('.video-list');
    await expect(videos.locator(`a[href="${committee}"]`)).toBeVisible();
    await expect(videos.locator(`a[href="${subcommittee}"]`)).toBeVisible();
  });

  test('links clips on bill and legislator pages to their videos', async ({ page }) => {
    await page.goto('/bill/2025/sb738/');
    await expect(page.locator('.video-transcript-source a').first()).toHaveAttribute(
      'href',
      /^\/video\/[bcdfghjkmnpqrstvwxz2-9]{4}\/\?t=\d+$/
    );
  });
});
