const { test, expect } = require('@playwright/test');

// Fixtures for this day are in deploy/mysql/video-minutes-fixtures.sql.
const minutesPath = '/minutes/house/2026/01/26/';

test.describe('Minutes video', () => {
  test('lists the bills taken up, in order, with what each is about', async ({ page }) => {
    await page.goto(minutesPath);

    const bills = page.locator('#video-bills li');
    await expect(bills).toHaveCount(14);
    await expect(bills.first()).toContainText('HB 1384');
    await expect(bills.first().locator('a')).toHaveAttribute('href', '/bill/2026/hb1384/');
    await expect(page.locator('#video-bills li', { hasText: 'HB 176' })).toContainText(
      'State-facilitated IRA savings program'
    );
  });

  test('names legislators correctly, including those elected in 2026', async ({ page }) => {
    await page.goto(minutesPath);
    await page.locator('#tab-video-speakers').click();

    const speakers = page.locator('#video-speakers');
    await expect(speakers).toBeVisible();
    // Her people.id (567) is not her terms.id (560).
    await expect(speakers.locator('a[href="/legislator/jlanderson/"]')).toHaveText(
      'Del. Jessica Anderson (D-Williamsburg)'
    );
    await expect(speakers).not.toContainText('Kimberly Pope Adams');
  });

  test('shows one panel at a time, chosen by tab or by URL', async ({ page }) => {
    await page.goto(minutesPath);

    await expect(page.locator('#tab-video-bills')).toHaveAttribute('aria-selected', 'true');
    await expect(page.locator('#video-bills')).toBeVisible();
    await expect(page.locator('#transcript')).toBeHidden();

    await page.locator('#tab-transcript').click();
    await expect(page.locator('#transcript')).toBeVisible();
    await expect(page.locator('#video-bills')).toBeHidden();
    await expect(page.locator('#transcript')).toContainText('House Bill 176');

    await page.locator('#tab-transcript').press('ArrowLeft');
    await expect(page.locator('#tab-video-speakers')).toBeFocused();
    await expect(page.locator('#video-speakers')).toBeVisible();

    await page.goto(minutesPath + '#transcript');
    await expect(page.locator('#tab-transcript')).toHaveAttribute('aria-selected', 'true');
  });

  test('shows the minutes without hiding them behind a tab', async ({ page }) => {
    await page.goto(minutesPath);
    await expect(page.locator('#minutes')).toBeVisible();
    await expect(page.locator('#minutes')).toContainText('Called to order at 12 m. by Don Scott, Speaker');
    await expect(page.locator('#minutes a[href="/bill/2026/hb1384/"]').first()).toBeVisible();
  });
});
