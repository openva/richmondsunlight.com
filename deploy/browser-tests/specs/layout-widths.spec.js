const { test, expect } = require('@playwright/test');

// The page has fixed widths between its breakpoints (in master.css and menu.css), so a breakpoint
// set too low makes the page wider than the screen. These are the widths around each breakpoint,
// and the common tablet and laptop widths between them. (mobile.spec.js covers phones.)
const widths = [768, 820, 834, 900, 901, 1024, 1180, 1199, 1200, 1280];

const pages = ['/', '/bills/', '/bill/2025/hb41/', '/legislators/', '/day/2026-01-26/', '/about/'];

for (const width of widths) {
  test(`pages don't scroll sideways at ${width}px`, async ({ page }) => {
    await page.setViewportSize({ width, height: 900 });
    for (const path of pages) {
      await page.goto(path);
      const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
      expect(scrollWidth, `${path} at ${width}px`).toBeLessThanOrEqual(width);
    }
  });
}

test('the phone menu is used up to 900px, and the full menu above that', async ({ page }) => {
  await page.setViewportSize({ width: 900, height: 900 });
  await page.goto('/');
  await expect(page.locator('#nav-toggle')).toBeVisible();

  await page.setViewportSize({ width: 901, height: 900 });
  await expect(page.locator('#nav-toggle')).toBeHidden();
  await expect(page.locator('#t-bills > a')).toBeVisible();
});
