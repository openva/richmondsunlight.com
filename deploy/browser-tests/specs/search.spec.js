const { test, expect } = require('@playwright/test');

test.describe('Search', () => {
  test('searching a known term returns relevant results', async ({ page }) => {
    await page.goto('/search/?q=sales+tax');

    await expect(page.locator('text=results found')).toBeVisible({ timeout: 10000 });
    // At least one result link should appear in the results div
    await expect(page.locator('.results a').first()).toBeVisible({ timeout: 10000 });
  });

  test('searching a bill number redirects to the bill page', async ({ page }) => {
    await page.goto('/search/?q=HB2049');

    await expect(page).toHaveURL(/\/bill\/2025\/hb2049\//, { timeout: 10000 });
  });

  test('empty search shows the search form gracefully', async ({ page }) => {
    await page.goto('/search/');

    await expect(page.locator('input[name="q"].search')).toBeVisible({ timeout: 10000 });
    // No Sphinx error or PHP error should appear
    await expect(page.locator('text=error')).not.toBeVisible();
  });
});
