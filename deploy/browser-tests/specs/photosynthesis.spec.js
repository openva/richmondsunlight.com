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

    await expect(page.getByLabel('Bill #')).toBeVisible({ timeout: 10000 });
    // Use exact heading match to avoid matching "Public Test Portfolio" as a substring
    await expect(page.getByRole('heading', { name: 'Test Portfolio', exact: true })).toBeVisible({ timeout: 10000 });
    await expect(page.getByRole('cell', { name: 'HB2049' }).first()).toBeVisible({ timeout: 10000 });
  });

  test('logged-in user can add a bill to a portfolio', async ({ page }) => {
    await loginTestUser(page);
    await page.goto('/photosynthesis/');

    // Fill the bill number and select the portfolio, then submit the form
    await page.getByLabel('Bill #').fill('HB41');
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

  test('a bill can’t be added to another user’s portfolio', async ({ page }) => {
    await loginTestUser(page);
    await page.goto('/photosynthesis/');
    const token = await page.locator('input[name="csrf_token"]').first().inputValue();
    // pwt04 is tester@example.com's.
    const resp = await page.request.post('/photosynthesis/process-actions.php', {
      form: { 'add-bill': 'HB41', portfolio: 'pwt04', csrf_token: token },
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    expect(resp.status()).toBe(422);
    expect((await resp.json()).success).toBe(false);
  });

  test('a bill or a portfolio can’t be deleted without the page’s token, or by a link', async ({ page }) => {
    await loginTestUser(page);
    await page.goto('/photosynthesis/');
    const record = await page.locator('#listing-pwt01 form.delete-bill input[name="delete-bill"]').first().inputValue();

    const ajax = { 'X-Requested-With': 'XMLHttpRequest' };
    for (const form of [{ 'delete-bill': record }, { 'delete-bill': record, csrf_token: 'wrong' }, { 'delete-portfolio': 'pwt01' }]) {
      const resp = await page.request.post('/photosynthesis/process-actions.php', { form, headers: ajax });
      expect(resp.status(), JSON.stringify(form)).toBe(403);
    }
    // The old links, which a page elsewhere could have sent people to.
    for (const path of [`/photosynthesis/delete/${record}/`, '/photosynthesis/portfolios/delete/pwt01/']) {
      expect((await page.request.get(path, { maxRedirects: 0 })).status(), path).toBe(405);
    }

    await page.goto('/photosynthesis/');
    await expect(page.locator(`#listing-pwt01 input[value="${record}"]`)).toHaveCount(1);
  });

  test('a portfolio’s name is shown as text, and the portfolio can be deleted', async ({ page }) => {
    await loginTestUser(page);
    await page.goto('/photosynthesis/');
    const name = `<script>window.portfolioXss = 1</script><b>Bold</b> ${Date.now()}`;
    const createForm = page.locator('form:has(input[name="add-portfolio"])');
    await createForm.locator('input[name="form_data[name]"]').fill(name);
    await createForm.locator('input[name="form_data[notes]"], textarea[name="form_data[notes]"]').fill('<img src=x onerror="window.notesXss = 1">Notes');
    await Promise.all([page.waitForNavigation(), createForm.locator('input[type="submit"][value="Create"]').click()]);

    const heading = page.locator('h1').filter({ hasText: name });
    await expect(heading).toBeVisible({ timeout: 15000 });
    expect(await page.evaluate(() => window.portfolioXss)).toBeUndefined();
    expect(await page.evaluate(() => window.notesXss)).toBeUndefined();
    const portfolio = page.locator('.portfolio', { has: heading });
    await expect(portfolio.locator('h1 b')).toHaveCount(0);

    page.once('dialog', dialog => dialog.accept());
    await Promise.all([page.waitForNavigation(), portfolio.locator('form.delete-portfolio button').click()]);
    await expect(page.locator('h1').filter({ hasText: name })).toHaveCount(0);
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
