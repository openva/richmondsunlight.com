const { test, expect } = require('@playwright/test');

// The day's actions, dockets, and meetings are in deploy/mysql/day-page-fixtures.sql, its floor
// session's video and minutes in video-minutes-fixtures.sql, and its committee meetings' videos in
// video-page-fixtures.sql.
const day = '/day/2026-01-26/';

test.describe('A day’s page', () => {
  test('shows what each chamber did on the floor', async ({ page }) => {
    await page.goto(day);

    await expect(page).toHaveTitle(/Monday, January 26, 2026/);
    const house = page.locator('#floor-house');
    await expect(house.locator('a[href="/video/b334/2026-01-26-house-floor/"]')).toBeVisible();

    const passed = house.locator('h4', { hasText: 'Passed' }).locator('xpath=following-sibling::ul[1]');
    await expect(passed).toContainText('HB 176');
    await expect(passed.locator('a.day-vote')).toHaveAttribute('href', '/bill/2026/hb176/hv0126/');

    const failed = house.locator('h4', { hasText: 'Failed' }).locator('xpath=following-sibling::ul[1]');
    await expect(failed).toContainText('HB 341');
    await expect(failed.locator('.day-close')).toHaveText('close vote');

    await expect(page.locator('#floor-senate')).toContainText('SJ 999');
  });

  test('tucks away the minor actions, and leaves out paperwork', async ({ page }) => {
    await page.goto(day);
    const rest = page.locator('#floor-house .day-everything-else');
    await expect(rest.locator('.day-actions')).toBeHidden();
    await rest.locator('summary').click();
    await expect(rest).toContainText('Read second time');

    // HB 414 had only a fiscal impact statement. (Its number is still in the minutes.)
    await expect(page.locator('#floor-house .day-actions', { hasText: 'HB 414' })).toHaveCount(0);
  });

  test('shows each committee and subcommittee meeting, with what it did', async ({ page }) => {
    await page.goto(day);

    const appropriations = page.locator('.day-meeting', { has: page.locator('h3', { hasText: /^House Appropriations Committee$/ }) });
    await expect(appropriations.locator('h3 a')).toHaveAttribute('href', '/committee/house/appropriations/');
    await expect(appropriations).toContainText('9:00 am');
    await expect(appropriations).toContainText('HB 199');
    await expect(appropriations.locator('a[href="/video/fcmk/2026-01-26-house-appropriations/"]')).toBeVisible();

    // The action doesn't name the subcommittee, but its docket does.
    const capitalOutlay = page.locator('.day-meeting', { hasText: 'Capital Outlay Subcommittee' });
    await expect(capitalOutlay).toContainText('HB 206');
    await expect(capitalOutlay).toContainText('Subcommittee recommends reporting');
    await expect(capitalOutlay.locator('.day-docket')).toContainText('HB 210');

    const rules = page.locator('.day-meeting', { hasText: 'House Rules Committee' });
    await expect(rules).toContainText('Continued to 2027 in Rules');
    await expect(rules.locator('.day-vote')).toHaveText('voice vote');

    // Party caucuses aren't meetings of the legislature.
    await expect(page.locator('#meetings')).not.toContainText('Caucus');
  });

  test('opens the minutes when they’re linked to', async ({ page }) => {
    await page.goto(day + '#minutes-house');
    const minutes = page.locator('#minutes-house');
    await expect(minutes).toHaveAttribute('open', '');
    await expect(minutes).toContainText('Called to order at 12 m. by Don Scott, Speaker');
    await expect(minutes.locator('a[href="/bill/2026/hb1384/"]').first()).toBeVisible();
  });

  test('shows one chamber at a time, chosen by button or by URL', async ({ page }) => {
    await page.goto(day);
    await page.locator('.day-filter button', { hasText: /^Senate$/ }).click();
    await expect(page.locator('#floor-senate')).toBeVisible();
    await expect(page.locator('#floor-house')).toBeHidden();
    await expect(page.locator('.day-meeting', { hasText: 'House Rules Committee' })).toBeHidden();
    expect(new URL(page.url()).hash).toBe('#senate');

    await page.goto(day + '#house');
    await expect(page.locator('#floor-house')).toBeVisible();
    await expect(page.locator('#floor-senate')).toBeHidden();

    await page.locator('.day-filter button', { hasText: 'House and Senate' }).click();
    await expect(page.locator('#floor-senate')).toBeVisible();
    await expect(page.locator('#floor-house')).toBeVisible();
  });

  // docker-setup-site.sh chooses the day's highlights (deploy/day-highlights.php).
  test('starts with the highlights, and says why each was chosen', async ({ page }) => {
    await page.goto(day);
    const first = page.locator('#highlights .day-highlights > li').first();
    await expect(first).toContainText('HB 341');
    await expect(first).toContainText('Read third time and defeated by House');
    await expect(first.locator('.day-reasons')).toContainText('Close vote, 45–52');
    await expect(first.locator('.day-reasons')).toContainText('1 news story');
    await expect(first.locator('.day-highlight-clip a')).toHaveAttribute('href', /^\/video\/b334\/\?t=\d+$/);

    // They follow the chamber filter.
    await page.locator('.day-filter button', { hasText: /^Senate$/ }).click();
    await expect(first).toBeHidden();
  });

  test('shows what people said here, and the news', async ({ page }) => {
    await page.goto(day);
    const said = page.locator('#what-people-said');
    await expect(said.locator('.day-comments')).toContainText('Towns need this money');
    await expect(said.locator('.day-comments a[href="/bill/2026/hb341/#comments"]')).toBeVisible();
    await expect(said.locator('.day-polls')).toContainText('70% of 10 voters support it');
    await expect(said.locator('.day-most-viewed')).toContainText('HB 341');

    const news = page.locator('#in-the-news');
    await expect(news.locator('a[href="https://example.com/news/plastic-bag-tax-fails"]')).toHaveText('House narrowly defeats plastic bag tax bill');
    await expect(news.locator('.day-news-bills a[href="/bill/2026/hb341/"]')).toBeVisible();
  });

  test('shows what’s coming up on the most recent day', async ({ page }) => {
    await page.goto('/day/latest/');
    expect(new URL(page.url()).pathname).toMatch(/^\/day\/\d{4}-\d{2}-\d{2}\/$/);
    await expect(page.locator('#coming-up')).toContainText('Joint Subcommittee on Day Page Fixtures');

    // Other days don't.
    await page.goto(day);
    await expect(page.locator('#coming-up')).toHaveCount(0);
  });

  test('is a 404 for a day with no page', async ({ request }) => {
    expect((await request.get('/day/2098-01-05/')).status()).toBe(404);
    expect((await request.get('/day/2026-02-30/')).status()).toBe(404);
  });

  test('is where the old minutes pages go', async ({ request }) => {
    for (const [from, to] of [
      ['/minutes/house/2026/01/26/', '/day/2026-01-26/#house'],
      ['/minutes/2025/', '/day/2025/'],
      ['/minutes/', '/day/'],
    ]) {
      const response = await request.get(from, { maxRedirects: 0 });
      expect(response.status(), from).toBe(301);
      const location = new URL(response.headers().location, 'http://x');
      expect(location.pathname + location.hash, from).toBe(to);
    }
  });
});

test.describe('The RSS feed of days', () => {
  test('is linked from days’ pages, and the calendar', async ({ page }) => {
    for (const path of [day, '/day/2026/']) {
      await page.goto(path);
      await expect(page.locator('link[rel="alternate"][type="application/rss+xml"]')).toHaveAttribute('href', '/rss/days/');
      await expect(page.locator('#sidebar a[href="/rss/days/"]')).toBeVisible();
    }
  });

  test('lists past days, each linked to its page', async ({ request }) => {
    const response = await request.get('/rss/days/');
    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('application/rss+xml');
    const body = await response.text();
    expect(body).toContain('<rss version="2.0"');
    const links = [...body.matchAll(/<link>https:\/\/www\.richmondsunlight\.com(\/day\/\d{4}-\d{2}-\d{2}\/)<\/link>/g)].map((m) => m[1]);
    expect(links.length).toBeGreaterThan(0);
    expect((await request.get(links[0])).status()).toBe(200);
  });
});

test.describe('The calendar of days', () => {
  test('links each day that has a page', async ({ page }) => {
    await page.goto('/day/2026/');
    const link = page.locator(`.day-calendar a[href="${day}"]`);
    await expect(link).toHaveText('26');
    await expect(link).toHaveAttribute('aria-label', /^Monday, January 26: House and Senate floor sessions/);
    await expect(page.locator('.day-calendar a[href="/day/2026-01-25/"]')).toHaveCount(0);
  });

  test('is in the menu', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('#t-daily > a')).toHaveAttribute('href', '/day/');
  });
});
