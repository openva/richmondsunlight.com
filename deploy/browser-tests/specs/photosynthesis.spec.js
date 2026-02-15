const { test, expect } = require('@playwright/test');

async function loginTestUser(page) {
  const resp = await page.request.post('/account/login/', {
    form: {
      'form_data[email]': 'testuser@example.com',
      'form_data[password]': 'password123',
      submit: 'Log In',
      'form_data[return_uri]': '/',
    },
    maxRedirects: 0,
  });

  const setCookie = resp.headers()['set-cookie'] || '';
  const match = setCookie.match(/PHPSESSID=([^;]+)/);
  if (!match) {
    throw new Error('Login did not return a PHPSESSID cookie');
  }

  await page.context().addCookies([
    // secure: false so the cookie is sent over HTTP in the test environment
    { name: 'PHPSESSID', value: match[1], domain: 'rs_web', path: '/', secure: false },
  ]);
}

test.describe('Photosynthesis', () => {
  test('/photosynthesis/ loads for logged-in user and shows portfolio', async ({ page }) => {
    await loginTestUser(page);
    await page.goto('/photosynthesis/');

    await expect(page.locator('input[name="add-bill"]')).toBeVisible({ timeout: 10000 });
    // Use exact heading match to avoid matching "Public Test Portfolio" as a substring
    await expect(page.getByRole('heading', { name: 'Test Portfolio', exact: true })).toBeVisible({ timeout: 10000 });
    await expect(page.getByRole('cell', { name: 'HB2049' }).first()).toBeVisible({ timeout: 10000 });
  });

  test('logged-in user can add a bill to a portfolio', async ({ page }) => {
    await loginTestUser(page);
    await page.goto('/photosynthesis/');

    // Fill the bill number and select the portfolio, then submit the form
    await page.fill('input[name="add-bill"]', 'HB41');
    // Test user has 2 portfolios, so a <select> is rendered
    await page.selectOption('select[name="portfolio"]', 'pwt01');
    await Promise.all([
      page.waitForNavigation({ timeout: 10000 }),
      page.click('input[type="submit"][value="Add"]'),
    ]);

    // HB41 should now appear in the Test Portfolio table
    await expect(page.locator('#listing-pwt01').getByRole('cell', { name: 'HB41' })).toBeVisible({ timeout: 15000 });
  });

  test('logged-in paid user can create a new portfolio', async ({ page }) => {
    await loginTestUser(page);
    await page.goto('/photosynthesis/');

    const portfolioName = `Playwright Portfolio ${Date.now()}`;
    const createForm = page.locator('form:has(input[name="add-portfolio"])');
    await createForm.locator('input[name="form_data[name]"]').fill(portfolioName);
    await createForm.locator('input[type="submit"][value="Create"]').click();

    await expect(page).toHaveURL(/\/photosynthesis\/$/, { timeout: 10000 });
    // New portfolio appears as an <h1> heading (paid user view)
    await expect(page.locator('h1').filter({ hasText: portfolioName })).toBeVisible({ timeout: 15000 });
  });

  test('public portfolio page shows pre-loaded bill', async ({ page }) => {
    await page.goto('/photosynthesis/pwtp1/');
    await expect(page.locator('text=HB2049').first()).toBeVisible({ timeout: 10000 });
  });

  test('/photosynthesis/portfolios/ lists organizations with public portfolios', async ({ page }) => {
    await page.goto('/photosynthesis/portfolios/');
    await expect(page.locator('text=Test Organization')).toBeVisible({ timeout: 10000 });
  });
});
