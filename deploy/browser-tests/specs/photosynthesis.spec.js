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

// Log in as any test user (see deploy/mysql/test-users.sql).
async function loginAs(page, email) {
  const resp = await page.request.post('/account/login/', {
    form: {
      'form_data[email]': email,
      'form_data[password]': 'password123',
      submit: 'Log In',
      'form_data[return_uri]': '/',
    },
    maxRedirects: 0,
  });
  const match = (resp.headers()['set-cookie'] || '').match(/PHPSESSID=([^;]+)/);
  if (!match) {
    throw new Error('Login did not return a PHPSESSID cookie');
  }
  await page.context().addCookies([
    { name: 'PHPSESSID', value: match[1], domain: 'rs_web', path: '/', secure: false },
  ]);
}

// Post to process-actions.php as a script does, with the session's CSRF token.
async function act(page, form) {
  await page.goto('/photosynthesis/');
  const token = await page.locator('input[name="csrf_token"]').first().inputValue();
  const resp = await page.request.post('/photosynthesis/process-actions.php', {
    form: { ...form, csrf_token: token },
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
  return resp.json();
}

// The hash of the newest of the logged-in user's portfolios with this name.
async function hashOf(page, name) {
  await page.goto('/photosynthesis/');
  const portfolio = page.locator('.portfolio', { has: page.locator('h1', { hasText: name }) }).last();
  return (await portfolio.getAttribute('id')).replace('portfolio-', '');
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

  test('a user who never paid can make a second portfolio, and a smart portfolio', async ({ page }) => {
    // tester@example.com is a "free" user.
    await loginAs(page, 'tester@example.com');
    await page.goto('/photosynthesis/');
    const stamp = Date.now();

    const normal = page.locator('form:has(input[name="add-portfolio"])');
    await normal.locator('input[name="form_data[name]"]').fill(`Second ${stamp}`);
    await Promise.all([page.waitForNavigation(), normal.locator('input[type="submit"]').click()]);
    await expect(page.locator('h1', { hasText: `Second ${stamp}` })).toBeVisible();
    // Bills can now be added to either.
    await expect(page.locator('#add-bill-form select[name="portfolio"] option[value="pwt04"]')).toHaveCount(1);

    await page.locator('a[href="#create-smart-portfolio"]').click();
    const smart = page.locator('form:has(input[name="add-smart-portfolio"])');
    await smart.locator('input[name="form_data[name]"]').fill(`Dogs ${stamp}`);
    await smart.locator('input[name="form_data[tag]"]').fill('police dogs');
    await Promise.all([page.waitForNavigation(), smart.locator('input[type="submit"]').click()]);

    const dogs = page.locator('.portfolio', { has: page.locator('h1', { hasText: `Dogs ${stamp}` }) });
    await expect(dogs.locator('.type')).toHaveText('Smart Portfolio');
    await expect(dogs.locator('.criteria')).toContainText('Bills tagged police dogs');
    await expect(dogs.getByRole('cell', { name: 'HB1591' })).toBeVisible();
    await expect(dogs.locator('.visibility')).toContainText('Public');

    for (const name of [`Second ${stamp}`, `Dogs ${stamp}`]) {
      expect((await act(page, { 'delete-portfolio': await hashOf(page, name) })).success).toBe(true);
    }
  });

  test('a bill page has a checkbox for each portfolio, each adding or removing the bill', async ({ page }) => {
    await loginAs(page, 'testuser@example.com');
    const bill = '/bill/2025/hb1561/';
    const box = (hash) => page.locator(`#track-bill input.track-portfolio[data-portfolio="${hash}"]`);
    const posted = () => page.waitForResponse(r => r.url().includes('/photosynthesis/process-actions.php'));

    await page.goto(bill);
    for (const hash of ['pwt01', 'pwtp1']) {
      if (await box(hash).isChecked()) {
        await Promise.all([posted(), box(hash).uncheck()]);
      }
    }

    // Checking two puts it in both.
    for (const hash of ['pwt01', 'pwtp1']) {
      const [resp] = await Promise.all([posted(), box(hash).check()]);
      expect(resp.status(), hash).toBe(200);
    }
    await page.reload();
    await expect(box('pwt01')).toBeChecked();
    await expect(box('pwtp1')).toBeChecked();
    await page.goto('/photosynthesis/');
    await expect(page.locator('#listing-pwt01').getByRole('cell', { name: 'HB1561' })).toBeVisible();
    await expect(page.locator('#listing-pwtp1').getByRole('cell', { name: 'HB1561' })).toBeVisible();

    // Unchecking one takes it out of that one only.
    await page.goto(bill);
    const [resp] = await Promise.all([posted(), box('pwt01').uncheck()]);
    expect(resp.status()).toBe(200);
    await page.reload();
    await expect(box('pwt01')).not.toBeChecked();
    await expect(box('pwtp1')).toBeChecked();

    await Promise.all([posted(), box('pwtp1').uncheck()]);
  });

  test('a bill in a smart portfolio is listed with it, with no checkbox for it', async ({ page }) => {
    await loginAs(page, 'testuser@example.com');
    const name = `Police dog bills ${Date.now()}`;
    expect((await act(page, { 'add-smart-portfolio': 'y', 'form_data[name]': name, 'form_data[tag]': 'police dogs' })).success).toBe(true);
    const hash = await hashOf(page, name);

    await page.goto('/bill/2025/hb1591/');
    const tracker = page.locator('#track-bill');
    await expect(tracker.locator('.track-smart', { hasText: name })).toContainText('tagged police dogs');
    await expect(tracker.locator(`input[data-portfolio="${hash}"]`)).toHaveCount(0);
    await expect(tracker.locator('input[data-portfolio="pwt01"]')).toHaveCount(1);

    expect((await act(page, { 'delete-portfolio': hash })).success).toBe(true);
  });

  test('the portfolio tab lists a private portfolio’s bills', async ({ page }) => {
    await loginAs(page, 'testuser@example.com');
    await page.goto('/bill/2025/hb41/');
    const list = page.locator('#portfolio-list');
    // pwt01, "Test Portfolio," is private, and has HB2049.
    const heading = list.locator('h6', { hasText: /^Test Portfolio/ });
    await expect(heading).toContainText('(private)');
    await expect(list.locator('a[href="/bill/2025/hb2049/"]').first()).toHaveText('HB2049');
    await expect(page.locator('#portfolio-sidebar')).toBeVisible();
  });

  test('a bill’s page lists each person tracking it once', async ({ page, request }) => {
    await loginAs(page, 'testuser@example.com');
    // A second public portfolio with HB2049, which pwtp1 has too.
    const name = `Also public ${Date.now()}`;
    expect((await act(page, { 'add-portfolio': 'y', 'form_data[name]': name, 'form_data[public]': 'y' })).success).toBe(true);
    const hash = await hashOf(page, name);
    expect((await act(page, { 'add-bill': 'HB2049', portfolio: hash })).success).toBe(true);

    // As somebody who isn't logged in.
    const html = await (await request.get('/bill/2025/hb2049/')).text();
    const tracked = html.match(/This bill is being tracked by (.*?)<\/p>/s);
    expect(tracked).not.toBeNull();
    expect(tracked[1].match(/Test Organization/g)).toHaveLength(1);

    expect((await act(page, { 'delete-portfolio': hash })).success).toBe(true);
  });

  test('a smart portfolio made by tag lists its bills, privately and publicly', async ({ page }) => {
    await loginAs(page, 'testuser@example.com');
    await page.goto('/photosynthesis/');
    const name = `By tag ${Date.now()}`;
    await page.locator('a[href="#create-smart-portfolio"]').click();
    const form = page.locator('form:has(input[name="add-smart-portfolio"])');
    await form.locator('input[name="form_data[name]"]').fill(name);
    // The tag box suggests tags.
    await expect(form.locator('datalist option[value="police dogs"]')).toHaveCount(1);
    await form.locator('input[name="form_data[tag]"]').fill('Police Dogs');
    await Promise.all([page.waitForNavigation(), form.locator('input[type="submit"]').click()]);

    const portfolio = page.locator('.portfolio', { has: page.locator('h1', { hasText: name }) });
    await expect(portfolio.locator('.criteria')).toHaveText('Bills tagged police dogs.');
    await expect(portfolio.getByRole('cell', { name: 'HB1591' })).toBeVisible();
    const hash = await hashOf(page, name);

    await page.goto(`/photosynthesis/${hash}/`);
    await expect(page.locator('#public-portfolio')).toContainText('HB1591');

    expect((await act(page, { 'delete-portfolio': hash })).success).toBe(true);
  });

  test('a smart portfolio’s form shows what’s saved, and needs a criterion', async ({ page }) => {
    await loginAs(page, 'testuser@example.com');
    const name = `Saved ${Date.now()}`;
    // Chad Green (506), and House Education (6).
    expect((await act(page, { 'add-smart-portfolio': 'y', 'form_data[name]': name, 'form_data[patron_id]': '506', 'form_data[committee_id]': '6' })).success).toBe(true);
    const hash = await hashOf(page, name);

    await page.goto(`/photosynthesis/portfolios/edit/${hash}/`);
    await expect(page.locator('#patron')).toHaveValue('506');
    await expect(page.locator('#committee')).toHaveValue('6');
    await expect(page.locator('#tag')).toHaveValue('');
    await expect(page.locator('.warning')).toContainText('except those you’ve written notes on');

    // Taking every criterion away is refused.
    await page.locator('#patron').selectOption('');
    await page.locator('#committee').selectOption('');
    await Promise.all([page.waitForNavigation(), page.locator('#submit').click()]);
    await expect(page.locator('#messages')).toContainText('needs a tag, a patron, or a committee');

    // Without a criterion, it can't be made, either.
    expect((await act(page, { 'add-smart-portfolio': 'y', 'form_data[name]': name })).success).toBe(false);
    await page.goto('/photosynthesis/');
    expect((await act(page, { 'delete-portfolio': hash })).success).toBe(true);
  });

  test('a bill can be hidden from a smart portfolio, and shown again', async ({ page }) => {
    await loginAs(page, 'testuser@example.com');
    const name = `Hiding ${Date.now()}`;
    expect((await act(page, { 'add-smart-portfolio': 'y', 'form_data[name]': name, 'form_data[tag]': 'police dogs' })).success).toBe(true);
    const hash = await hashOf(page, name);

    // From the bill's page.
    await page.goto('/bill/2025/hb1591/');
    const line = page.locator('#track-bill .track-smart', { hasText: name });
    await expect(line).toContainText('In');
    await Promise.all([page.waitForNavigation(), line.locator('button').click()]);
    await expect(page.locator('#track-bill .track-smart', { hasText: name })).toContainText('Hidden from');

    // It's listed as hidden, and isn't on the public page.
    await page.goto('/photosynthesis/');
    await expect(page.locator(`#listing-${hash}`)).toHaveCount(0);
    const hidden = page.locator(`#hidden-${hash}`);
    await expect(hidden).toContainText('HB1591');
    let html = await (await page.request.get(`/photosynthesis/${hash}/`)).text();
    expect(html).not.toContain('HB1591');

    // Shown again, from the private page.
    await Promise.all([page.waitForNavigation(), hidden.locator('button', { hasText: 'Show again' }).click()]);
    await expect(page.locator(`#listing-${hash}`).getByRole('cell', { name: 'HB1591' })).toBeVisible();
    html = await (await page.request.get(`/photosynthesis/${hash}/`)).text();
    expect(html).toContain('HB1591');

    // And hidden from the private page.
    await Promise.all([page.waitForNavigation(), page.locator(`#listing-${hash} form.hide-bill button`).first().click()]);
    await expect(page.locator(`#hidden-${hash}`)).toContainText('HB1591');

    expect((await act(page, { 'delete-portfolio': hash })).success).toBe(true);
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
