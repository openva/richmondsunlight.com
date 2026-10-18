const { test, expect } = require('@playwright/test');

// Fixtures: Deeds's older clips are in deploy/mysql/video-legislator-fixtures.sql; Krizek's come
// from the video index in deploy/mysql/video-minutes-fixtures.sql.

async function openVideoTab(page, shortname) {
  await page.goto(`/legislator/${shortname}/`);
  await page.locator('a[href="#video"]').click();
  await expect(page.locator('#video')).toBeVisible();
}

test.describe('Legislator video', () => {
  test('groups clips by year, newest first, with only the latest year open', async ({ page }) => {
    await openVideoTab(page, 'rcdeeds');

    const section = page.locator('#video');
    await expect(page.locator('script[src*="flowplayer"]')).toHaveCount(0);
    await expect(section).toContainText('We have 281 video clips of Creigh Deeds speaking, from 2009 through 2017.');
    await expect(section.locator('.clip-player-clip')).toHaveCount(281);

    const years = section.locator('.clip-player-year');
    await expect(years).toHaveCount(9);
    await expect(years.first().locator('summary')).toContainText('2017');
    await expect(section.locator('.clip-player-year[open]')).toHaveCount(1);
    await expect(section.locator('.clip-player-now')).toHaveText(
      'Clip 1 of 281: Senate floor session, February 25, 2017'
    );

    // Older clips stored as http:// must be upgraded, or the CSP blocks them.
    await expect(section.locator('.clip-player-clip[data-src^="http://"]')).toHaveCount(0);
  });

  test('plays a clip from a collapsed year, and loads its transcript', async ({ page }) => {
    await openVideoTab(page, 'rcdeeds');

    const year = page.locator('.clip-player-year', { hasText: '2012' });
    await year.locator('summary').click();
    const clip = year.locator('.clip-player-clip').nth(2);
    await clip.locator('.clip-player-select').click();

    await expect(clip).toHaveClass(/is-active/);
    await expect(page.locator('.clip-player-now')).toHaveText(/^Clip \d+ of 281: Senate floor session, .+ 2012$/);
    await expect(clip.locator('[data-transcript-src]')).toHaveAttribute('data-loaded', 'yes');
  });

  test('builds clips from the speaker index, with transcripts', async ({ page }) => {
    await openVideoTab(page, 'pekrizek');

    const floor = page.locator('.clip-player-clip', { hasText: 'House floor session' }).first();
    await expect(floor).toContainText('January 26, 2026');
    await floor.locator('.clip-player-select').click();
    await expect(floor.locator('[data-transcript-src]')).toContainText('House Bill 368');
  });

  test('rejects bad transcript requests', async ({ request }) => {
    const bad = await request.get('/video-transcript.php?file=15141&start=10&end=5');
    expect(bad.status()).toBe(400);
    const good = await request.get('/video-transcript.php?file=15141&start=1380&end=1430');
    expect(good.status()).toBe(200);
    expect(await good.text()).toContain('data-seek');
  });

  test('hides the captions button for clips without captions', async ({ page }) => {
    await openVideoTab(page, 'rcdeeds');
    await expect(page.locator('#video .clip-player-captions')).toBeDisabled();

    await openVideoTab(page, 'pekrizek');
    await expect(page.locator('#video .clip-player-captions')).toBeEnabled();
  });

  test('serves captions as WebVTT, placed clear of the broadcast chyrons', async ({ request }) => {
    const response = await request.get('/video-transcript.php?format=vtt&file=15141&start=1380&end=1430');
    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('text/vtt');
    const vtt = await response.text();
    expect(vtt.startsWith('WEBVTT')).toBe(true);
    expect(vtt).toMatch(/\d\d:\d\d:\d\d\.\d{3} --> \d\d:\d\d:\d\d\.\d{3} line:5%/);
  });
});
