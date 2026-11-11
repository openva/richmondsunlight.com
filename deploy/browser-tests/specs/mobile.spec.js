const { test, expect, devices } = require('@playwright/test');

// Pages at iPhone size: nothing may be wider than the screen, and the phone menu has to work.
// (See mobile-fixes.md.)

// The iPhone's size and touch screen, but in Chromium (the only browser that the tests install),
// rather than WebKit.
const { defaultBrowserType, ...iPhone } = devices['iPhone 15'];
test.use(iPhone);

const pages = [
  '/',
  '/bills/',
  '/bill/2025/hb41/',
  '/bills/topic/',
  '/bills/introduced/',
  '/legislators/',
  '/legislator/sgpekarsky/',
  '/your-legislators/',
  '/committees/',
  '/committee/senate/local-government/',
  '/minutes/',
  '/minutes/house/2026/01/26/',
  '/schedule/',
  '/statistics/',
  '/places/',
  '/places/richmond/',
  '/photosynthesis/',
  '/account/register/',
  '/account/login/',
  '/about/',
  '/about/site/',
];

for (const path of pages) {
  test(`${path} fits an iPhone's screen`, async ({ page }) => {
    const response = await page.goto(path);
    expect(response.status()).toBe(200);

    // On phones, #content hides whatever overflows it, which would hide the problem from this
    // test, so let it overflow, and then list anything that's wider than the screen.
    await page.addStyleTag({ content: '#content { overflow: visible !important; }' });
    const tooWide = await page.evaluate(() => {
      const width = document.documentElement.clientWidth;
      return [...document.querySelectorAll('#page *')]
        .filter((el) => {
          const box = el.getBoundingClientRect();
          // SVG drawings clip their own contents.
          return box.width > 0 && box.right > width + 1 && !el.closest('svg');
        })
        .map((el) => el.tagName.toLowerCase() + (el.id ? '#' + el.id : '')
          + (typeof el.className === 'string' && el.className ? '.' + el.className.trim().split(/\s+/).join('.') : ''));
    });
    expect(tooWide, 'elements wider than the screen').toEqual([]);
  });
}

test('the phone menu opens, and its sections open on a tap', async ({ page }) => {
  await page.goto('/');

  const nav = page.locator('#nav');
  await expect(nav).toBeHidden();
  await page.locator('#nav-toggle').tap();
  await expect(nav).toBeVisible();
  await expect(page.locator('#nav-toggle')).toHaveAttribute('aria-expanded', 'true');

  // Every section is listed.
  for (const name of ['Home', 'Bills', 'Legislators', 'Places', 'Tools', 'Video', 'Schedule', 'Stats', 'About']) {
    await expect(nav.locator('> li').filter({ hasText: name }).first()).toBeVisible();
  }

  // Tapping Bills opens its submenu, rather than going to /bills/.
  await page.locator('#t-bills > a').tap();
  await expect(page).toHaveURL(/\/$/);
  await expect(page.locator('#t-bills .nav-section-link a')).toBeVisible();

  // A section without a submenu goes to its page.
  await page.locator('#t-schedule > a').tap();
  await expect(page).toHaveURL(/\/schedule\/$/);
});
