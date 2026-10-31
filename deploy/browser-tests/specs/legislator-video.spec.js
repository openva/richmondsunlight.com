const { test, expect } = require('@playwright/test');

// Fixtures: Deeds's older clips are in deploy/mysql/video-legislator-fixtures.sql; Krizek's come
// from the video index in deploy/mysql/video-minutes-fixtures.sql. Both of them also speak in some
// of the videos in the local sample (deploy/mysql/local-test-records.sql), when it's loaded, and
// those change with every export, so these tests don't count on how many clips there are.

// Deeds's clips from the fixtures alone
const DEEDS_FIXTURE_CLIPS = 281;

// Open the year that a clip is listed under, unless it already is.
async function openYear(page, clip) {
  const year = page.locator('.clip-player-year', { has: clip });
  if ((await year.getAttribute('open')) === null) {
    await year.locator('summary').click();
  }
}

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

    // The summary's count is the number of clips listed.
    const summary = section.getByText(/We have \d+ video clips of Creigh Deeds speaking, from 2009 through \d{4}\./);
    await expect(summary).toBeVisible();
    const total = Number((await summary.textContent()).match(/We have (\d+) video clips/)[1]);
    expect(total).toBeGreaterThanOrEqual(DEEDS_FIXTURE_CLIPS);
    await expect(section.locator('.clip-player-clip')).toHaveCount(total);

    // Years run newest first, and only the first is open.
    const years = await section.locator('.clip-player-year summary').evaluateAll((summaries) =>
      summaries.map((element) => Number(element.textContent.match(/\d{4}/)[0]))
    );
    expect(years.length).toBeGreaterThanOrEqual(9);
    expect(years).toEqual([...years].sort((a, b) => b - a));
    expect(years).toContain(2009);
    await expect(section.locator('.clip-player-year[open]')).toHaveCount(1);
    await expect(section.locator('.clip-player-year').first()).toHaveAttribute('open', '');
    await expect(section.locator('.clip-player-now')).toHaveText(
      new RegExp(`^Clip 1 of ${total}: .+, \\w+ \\d{1,2}, ${years[0]}$`)
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
    await expect(page.locator('.clip-player-now')).toHaveText(/^Clip \d+ of \d+: Senate floor session, .+ 2012$/);
    await expect(clip.locator('[data-transcript-src]')).toHaveAttribute('data-loaded', 'yes');
  });

  test('builds clips from the speaker index, with transcripts', async ({ page }) => {
    await openVideoTab(page, 'pekrizek');

    // The fixture's clip, among however many others the sample adds
    const floor = page
      .locator('.clip-player-clip', { hasText: 'House floor session' })
      .filter({ hasText: 'January 26, 2026' })
      .first();
    await openYear(page, floor);
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
    // The button follows the current clip. The fixtures' clips of Deeds have no captions...
    await openVideoTab(page, 'rcdeeds');
    const year = page.locator('.clip-player-year', { hasText: '2012' });
    await year.locator('summary').click();
    await year.locator('.clip-player-clip').first().locator('.clip-player-select').click();
    await expect(page.locator('#video .clip-player-captions')).toBeDisabled();

    // ...and the fixture's clip of Krizek does.
    await openVideoTab(page, 'pekrizek');
    const clip = page
      .locator('.clip-player-clip', { hasText: 'House floor session' })
      .filter({ hasText: 'January 26, 2026' })
      .first();
    await openYear(page, clip);
    await clip.locator('.clip-player-select').click();
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
