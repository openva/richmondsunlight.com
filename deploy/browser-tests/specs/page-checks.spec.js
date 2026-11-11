const { test, expect } = require('@playwright/test');

// HB41 is the primary bill used across tests; 76483 is its DB id.
const billPath = '/bill/2025/hb41/';
const billId = '76483';

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
    { name: 'PHPSESSID', value: match[1], domain: 'rs_web', path: '/' },
  ]);
}

// ---------------------------------------------------------------------------
// Account dashboard
// ---------------------------------------------------------------------------

test('logged-in user sees profile form at /account/', async ({ page }) => {
  await loginTestUser(page);
  await page.goto('/account/');
  await expect(page.locator('#registration')).toBeVisible({ timeout: 10000 });
  await expect(page.locator('[name="form_data[email]"]')).toHaveValue(
    'testuser@example.com'
  );
});

// ---------------------------------------------------------------------------
// Committee detail page
// ---------------------------------------------------------------------------

// senate/local-government has SB839 (session 31, status="introduced") so the
// "Bills in this Committee" sidebar renders with bill links.
test('/committee/senate/local-government/ renders bills list with bill links', async ({ page }) => {
  await page.goto('/committee/senate/local-government/');
  // The sidebar "Bills in this Committee" box lists bills as .bill links.
  await expect(
    page.locator('.box li a.bill').first()
  ).toBeVisible({ timeout: 10000 });
});

// ---------------------------------------------------------------------------
// Schedule page with docketed bills
// ---------------------------------------------------------------------------

// 2025-01-13 has a docket entry for SB839 in committee 21 (senate/local-government).
test('/schedule/2025/01/13/ shows docket listing with bill links', async ({ page }) => {
  await page.goto('/schedule/2025/01/13/');
  await expect(
    page.locator('table.bill-listing a[href*="/bill/"]').first()
  ).toBeVisible({ timeout: 10000 });
});

// ---------------------------------------------------------------------------
// AJAX / data endpoints
// ---------------------------------------------------------------------------

test('code-section-json.php returns application/json', async ({ request }) => {
  const resp = await request.get('/code-section-json.php?section=15.2-2286.2');
  expect(resp.ok()).toBeTruthy();
  expect(resp.headers()['content-type']).toMatch(/application\/json/);
  // Body is valid JSON (may be null if only one row due to the fetch-ahead idiom).
  const text = await resp.text();
  expect(() => JSON.parse(text)).not.toThrow();
});

test('process-comments.php silently discards requests with honeypot field filled', async ({ request }) => {
  const resp = await request.post('/process-comments.php', {
    form: {
      expiration_date: 'Playwright Tester',
      zip: 'pw@example.com',
      age: 'https://example.com',
      bill_id: billId,
      comment: 'Honeypot test',
      state: 'this-should-trigger-spam-filter',  // honeypot
    },
  });
  // Script exits silently — 200 with empty body.
  expect(resp.status()).toBe(200);
  const text = await resp.text();
  expect(text.trim()).toBe('');
});

test('process-comments.php returns JSON error for empty comment', async ({ request }) => {
  const resp = await request.post('/process-comments.php', {
    form: {
      expiration_date: 'Playwright Tester',
      zip: 'pw@example.com',
      age: 'https://example.com',
      bill_id: billId,
      comment: '',
    },
  });
  expect(resp.status()).toBe(500);
  const json = await resp.json();
  expect(json.error).toBeTruthy();
});

// Clear this bill's poll votes, so that the database's one-vote-per-person rule doesn't block
// a test's vote.
async function clearPollVotes() {
  const mysql = require('mysql2/promise');
  const db = await mysql.createConnection({
    host: process.env.DB_HOST || 'db',
    user: 'ricsun',
    password: 'password',
    database: 'richmondsunlight',
  });
  await db.execute('DELETE FROM polls WHERE bill_id = ?', [billId]);
  await db.end();
}

test('process-polls.php redirects after a poll vote posted without JavaScript', async ({ page }) => {
  await clearPollVotes();

  // page.request shares the browser session so PHP can track the new anonymous user.
  const resp = await page.request.post('/process-polls.php', {
    form: {
      'poll[bill_id]': billId,
      'poll[vote]': 'y',
      'poll[return_to]': billPath,
    },
    maxRedirects: 0,
  });
  expect(resp.status()).toBe(302);
});

test('process-polls.php answers a JSON vote with the results', async ({ page }) => {
  await clearPollVotes();

  const vote = () => page.request.post('/process-polls.php', {
    headers: { 'Content-Type': 'application/json' },
    data: JSON.stringify({ bill_id: Number(billId), vote: 'n' }),
  });
  let resp = await vote();
  expect(resp.status()).toBe(200);
  expect(await resp.json()).toEqual({ results: { total: 1, yes: 0 } });

  // A second vote isn't counted, but still gets the results.
  resp = await vote();
  expect(resp.status()).toBe(409);
  expect((await resp.json()).results).toEqual({ total: 1, yes: 0 });

  // A vote that isn't yes or no is refused.
  resp = await page.request.post('/process-polls.php', {
    headers: { 'Content-Type': 'application/json' },
    data: JSON.stringify({ bill_id: Number(billId), vote: 'maybe' }),
  });
  expect(resp.status()).toBe(400);
});

test('voting in a bill page\'s poll shows the results without leaving the page', async ({ page }) => {
  await clearPollVotes();
  await page.goto(billPath);

  const poll = page.locator('#poll');
  await expect(poll.locator('h3')).toHaveText('Cast Your Vote');

  // Voting without choosing an answer asks for one.
  await poll.locator('input[type="submit"]').click();
  await expect(page.locator('#poll-error')).toHaveText('Choose yes or no.');

  await poll.locator('input[value="y"]').check();
  const [response] = await Promise.all([
    page.waitForResponse('**/process-polls.php'),
    poll.locator('input[type="submit"]').click(),
  ]);
  expect(response.status()).toBe(200);

  await expect(page).toHaveURL(new RegExp(billPath.replace(/\//g, '\\/') + '$'));
  await expect(poll.locator('h3')).toHaveText('Poll Results');
  await expect(page.locator('#poll-form')).toHaveCount(0);
  await expect(page.locator('#poll-results canvas')).toBeVisible();
  await expect(page.locator('#poll-results')).toContainText('1 vote');
});

// ---------------------------------------------------------------------------
// Legislators listing
// ---------------------------------------------------------------------------

test('/legislators/ lists current House and Senate members', async ({ page }) => {
  await page.goto('/legislators/');
  // The Names tab is inside jQuery UI tabs, so elements may not be "visible"
  // until the tab is activated. Check the page HTML content instead.
  const html = await page.content();
  expect(html).toContain('House of Delegates');
  expect(html).toContain('Senate');
  // Verify a reasonable number of legislator links are present (100 House + 40 Senate)
  const links = page.locator('#names a[href*="/legislator/"]');
  expect(await links.count()).toBeGreaterThanOrEqual(100);
});

// ---------------------------------------------------------------------------
// Your Legislators — address geocoding
// ---------------------------------------------------------------------------

test('your-legislators address lookup successfully resolves coordinates', async ({ page }) => {
  // Pass coordinates directly to avoid dependency on external geocoding service.
  // 37.5407, -77.4360 = 100 E Main St, Richmond, VA 23219
  await page.goto('/your-legislators/?latitude=37.5407&longitude=-77.4360');
  // If coordinates are accepted, the page moves on to district lookup (which may or may
  // not work depending on whether OPENSTATES_KEY is configured). Either way, the
  // "could not be identified" message must NOT appear.
  const body = await page.locator('#content').textContent({ timeout: 15000 });
  expect(body).not.toContain('Your location could not be identified');
});

// ---------------------------------------------------------------------------
// CSV / data export
// ---------------------------------------------------------------------------

test('vote-csv.php returns 200 for a known legislator', async ({ request }) => {
  // The test DB has no vote records, but the endpoint should still return 200.
  const resp = await request.get('/vote-csv.php?shortname=FER122&year=2025');
  expect(resp.status()).toBe(200);
});

// ---------------------------------------------------------------------------
// Admin pages
// ---------------------------------------------------------------------------

test('/admin/ returns 401 for unauthenticated users', async ({ request }) => {
  const resp = await request.get('/admin/', { maxRedirects: 0 });
  expect(resp.status()).toBe(401);
});

test('/admin/ returns 200 for authenticated admin users', async ({ request }) => {
  const credentials = Buffer.from('admin:password123').toString('base64');
  const resp = await request.get('/admin/', {
    headers: { Authorization: `Basic ${credentials}` },
  });
  expect(resp.status()).toBe(200);
});
