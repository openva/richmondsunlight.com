const { test, expect } = require('@playwright/test');

// Pages that once put request parameters straight into SQL. Each malformed request should be
// turned away (rather than run, or fail with a database error), and quotes in what people type
// should be stored as typed.

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
  const match = (resp.headers()['set-cookie'] || '').match(/PHPSESSID=([^;]+)/);
  if (!match) {
    throw new Error('Login did not return a PHPSESSID cookie');
  }
  await page.context().addCookies([
    // secure: false so the cookie is sent over HTTP in the test environment
    { name: 'PHPSESSID', value: match[1], domain: 'rs_web', path: '/', secure: false },
  ]);
}

// The session's CSRF token, which Photosynthesis's forms include (see class.Csrf.php).
async function csrfToken(page) {
  await page.goto('/photosynthesis/');
  return page.locator('input[name="csrf_token"]').first().inputValue();
}

test.describe('vote page', () => {
  // SB839's vote S07V270, in test-records.sql
  test('shows a vote', async ({ request }) => {
    const resp = await request.get('/vote.php?year=2025&bill=sb839&lis_id=S07V270');
    expect(resp.status()).toBe(200);
  });

  for (const query of [
    'year=2025&bill=sb839"||1%23&lis_id=S07V270',
    'year=2025&bill=sb839&lis_id="||sleep(3)%23',
    'year=2025 OR 1&bill=sb839&lis_id=S07V270',
    'bill=sb839&lis_id=S07V270',
  ]) {
    test(`is a 404 for ${query}`, async ({ request }) => {
      const resp = await request.get(`/vote.php?${query}`);
      expect(resp.status()).toBe(404);
    });
  }
});

test.describe('public portfolio page', () => {
  test('is a 404 for a hash that is not one', async ({ request }) => {
    // The URL rewrite rule's [QSA] passes a query string's hash through, after its own.
    const resp = await request.get('/photosynthesis/pwtp1/?hash=x" OR "1"="1');
    expect(resp.status()).toBe(404);
  });
});

test.describe('code-section-json.php', () => {
  test('wraps the JSON in a JavaScript function name', async ({ request }) => {
    const resp = await request.get('/code-section-json.php?section=15.2-2286.2&callback=jQuery123.cb');
    expect(resp.status()).toBe(200);
    // SB839 cites this section, in test-records.sql.
    expect(await resp.text()).toMatch(/^jQuery123\.cb \(\[.*"number":"SB839"/);
  });

  test('refuses a callback that is not a function name', async ({ request }) => {
    const resp = await request.get('/code-section-json.php?section=15.2-2286.2&callback=alert(1)//');
    expect(resp.status()).toBe(400);
    expect(await resp.text()).not.toContain('alert');
  });
});

test.describe('Photosynthesis', () => {
  test('ajax-bill-notes.php refuses an ID that is not a number', async ({ page }) => {
    await loginTestUser(page);
    const token = await csrfToken(page);
    const started = Date.now();
    const resp = await page.request.post('/photosynthesis/ajax-bill-notes.php', {
      form: { csrf_token: token, id: '0 OR SLEEP(3)', value: 'Notes' },
    });
    expect(await resp.text()).toBe(' ');
    expect(Date.now() - started).toBeLessThan(2500);
  });

  test('a portfolio can be named with quotes', async ({ page }) => {
    await loginTestUser(page);
    const name = `O"Brien's Portfolio ${Date.now()}`;
    const token = await csrfToken(page);
    const resp = await page.request.post('/photosynthesis/process-actions.php', {
      form: { 'add-portfolio': 'y', 'form_data[name]': name, csrf_token: token },
      headers: { Referer: '/photosynthesis/' },
    });
    expect(resp.status()).toBe(200);
    await page.goto('/photosynthesis/');
    await expect(page.locator('h1').filter({ hasText: name })).toBeVisible({ timeout: 15000 });
  });

  test('a portfolio can be edited, but only by its owner', async ({ page }) => {
    await loginTestUser(page);
    await page.goto('/photosynthesis/portfolios/edit/pwt01/');
    const token = await page.locator('input[name="csrf_token"]').first().inputValue();
    const edit = (hash, notes) => page.request.post(`/photosynthesis/portfolios/edit/${hash}/`, {
      form: {
        submit: 'y',
        csrf_token: token,
        'form_data[name]': 'Test Portfolio',
        'form_data[notes]': notes,
        'form_data[public]': 'n',
        'form_data[notify]': 'none',
      },
      maxRedirects: 0,
    });

    // A successful edit redirects to the portfolio; another user's portfolio, to the list.
    const notes = `Notes with "quotes" ${Date.now()}`;
    expect((await edit('pwt01', notes)).headers().location).toBe('/photosynthesis/#pwt01');
    expect((await edit('pwt04', 'Not saved')).headers().location).toBe('/photosynthesis/');

    await page.goto('/photosynthesis/portfolios/edit/pwt01/');
    await expect(page.locator('#notes')).toHaveValue(notes);
  });

  test('a smart portfolio can have quotes in its keyword', async ({ page }) => {
    await loginTestUser(page);
    const name = `Smart Portfolio ${Date.now()}`;
    const token = await csrfToken(page);
    const started = Date.now();
    const resp = await page.request.post('/photosynthesis/process-actions.php', {
      form: {
        csrf_token: token,
        'add-smart-portfolio': 'y',
        'form_data[name]': name,
        'form_data[keyword]': '"school") OR SLEEP(3) #',
      },
      headers: { Referer: '/photosynthesis/' },
    });
    expect(resp.status()).toBe(200);
    expect(Date.now() - started).toBeLessThan(2500);
    await page.goto('/photosynthesis/');
    await expect(page.locator('h1').filter({ hasText: name })).toBeVisible({ timeout: 15000 });
  });
});
