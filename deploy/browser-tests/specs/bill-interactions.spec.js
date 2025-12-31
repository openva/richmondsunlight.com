const { test, expect } = require('@playwright/test');

const billPath = '/bill/2025/hb41/';
const apiBaseUrl = process.env.PLAYWRIGHT_API_BASE_URL;

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

async function loginTrustedUser(page) {
  const resp = await page.request.post('/account/login/', {
    form: {
      'form_data[email]': 'trusted@example.com',
      'form_data[password]': 'password123',
      submit: 'Log In',
      'form_data[return_uri]': '/',
    },
    maxRedirects: 0,
  });

  const setCookie = resp.headers()['set-cookie'] || '';
  const match = setCookie.match(/PHPSESSID=([^;]+)/);
  if (!match) {
    throw new Error('Trusted login did not return a PHPSESSID cookie');
  }

  await page.context().addCookies([
    { name: 'PHPSESSID', value: match[1], domain: 'rs_web', path: '/' },
  ]);
}

test.describe('Bill interactions', () => {
  test('can add tags via AJAX and see them in the DOM', async ({ page, request }) => {
    const tagText = `pw-tag-${Date.now()}`;
    const cleanTag = tagText.replace(/[^a-zA-Z0-9 ]/g, '').toLowerCase();

    await page.goto(billPath);
    // Set the hidden input directly (plugin hides it) and submit via the page script.
    await page.locator('#tags').evaluate((el, value) => {
      el.value = value;
    }, tagText);

    const [response] = await Promise.all([
      page.waitForResponse(
        (resp) =>
          resp.url().includes('/process-tags.php') && resp.status() >= 200 && resp.status() < 300
      ),
      page.click('#tags_submit', { force: true }),
    ]);

    expect(response.ok()).toBeTruthy();
    await page.reload();
    await expect(page.locator('#tags_list li', { hasText: cleanTag })).toBeVisible({ timeout: 15000 });
  });

  test('can add a comment via AJAX and see it appended', async ({ page, request }) => {
    const commentText = `Playwright comment ${Date.now()}`;

    await page.goto(billPath);
    const billId = await page.locator('#bill_id').first().inputValue();
    const commentResponse = await request.post('/process-comments.php', {
      // The bizarre field names are an anti-spam measure.
      form: {
        expiration_date: 'Playwright Tester',
        zip: 'tester@example.com',
        age: 'https://example.com',
        bill_id: billId,
        comment: commentText,
      },
    });

    if (!commentResponse.ok()) {
        const body = await commentResponse.text();
        throw new Error(`Comment POST failed: ${commentResponse.status()} ${body}`);
    }

    await page.reload();
    await expect(page.locator('#comment-list')).toContainText(commentText, { timeout: 15000 });
  });

  test('logged-in user can add a comment via AJAX and see it appended', async ({ page }) => {
    await loginTestUser(page);

    const commentText = `Playwright logged-in comment ${Date.now()}`;

    await page.goto(billPath);
    // sleep for 5 seconds to avoid rate limiting
    await page.waitForTimeout(5000);
    const billId = await page.locator('#bill_id').first().inputValue();
    const commentResponse = await page.request.post('/process-comments.php', {
      form: {
        expiration_date: 'Playwright Tester',
        zip: 'tester@example.com',
        age: 'https://example.com',
        bill_id: billId,
        comment: commentText,
      },
    });

    if (!commentResponse.ok()) {
        const body = await commentResponse.text();
        throw new Error(`Comment POST failed: ${commentResponse.status()} ${body}`);
    }

    await page.reload();
    await expect(page.locator('#comment-list')).toContainText(commentText, { timeout: 15000 });
  });

  test('logged-in user can add tags via AJAX and see them in the DOM', async ({ page }) => {
    await loginTestUser(page);

    const tagText = `pw-tag-${Date.now()}`;
    const cleanTag = tagText.replace(/[^a-zA-Z0-9 ]/g, '').toLowerCase();

    await page.goto(billPath);
    await page.locator('#tags').evaluate((el, value) => {
      el.value = value;
    }, tagText);

    const [response] = await Promise.all([
      page.waitForResponse(
        (resp) =>
          resp.url().includes('/process-tags.php') && resp.status() >= 200 && resp.status() < 300
      ),
      page.click('#tags_submit', { force: true }),
    ]);

    expect(response.ok()).toBeTruthy();
    await page.reload();
    await expect(page.locator('#tags_list li', { hasText: cleanTag })).toBeVisible({ timeout: 15000 });
  });

  test('trusted user can delete a tag via UI', async ({ page }) => {
    await loginTrustedUser(page);

    const tagText = `pw-tag-delete-${Date.now()}`;
    const cleanTag = tagText.replace(/[^a-zA-Z0-9 ]/g, '').toLowerCase();

    await page.goto(billPath);
    await page.locator('#tags').evaluate((el, value) => {
      el.value = value;
    }, tagText);

    const [addResponse] = await Promise.all([
      page.waitForResponse(
        (resp) =>
          resp.url().includes('/process-tags.php') && resp.status() >= 200 && resp.status() < 300
      ),
      page.click('#tags_submit', { force: true }),
    ]);
    expect(addResponse.ok()).toBeTruthy();

    await page.reload();
    const tagItem = page.locator('#tags_list li', { hasText: cleanTag });
    const deleteLink = tagItem.locator('.delete-tag');
    await expect(deleteLink).toBeVisible({ timeout: 10000 });

    const [deleteResponse] = await Promise.all([
      page.waitForResponse(
        (resp) =>
          resp.url().includes('/delete-tags.php') && resp.status() >= 200 && resp.status() < 300
      ),
      deleteLink.click(),
    ]);
    expect(deleteResponse.ok()).toBeTruthy();
    await page.reload();
    await expect(page.locator('#tags_list li', { hasText: cleanTag })).toHaveCount(0, { timeout: 15000 });
  });

  test('untrusted user cannot delete tags', async ({ page }) => {
    await loginTestUser(page);

    // Fetch an existing tag via API to get a tag ID without relying on cache invalidation timing.
    const apiResponse = await page.request.get(`${apiBaseUrl}/1.1/bill/2025/hb41.json`);
    const billJson = await apiResponse.json();
    const tags = billJson.tags || {};
    const firstTagId = Object.keys(tags)[0];
    const firstTagName = tags[firstTagId];
    if (!firstTagId) {
      throw new Error('No existing tags found to test deletion');
    }

    const deleteAttempt = await page.request.post('/delete-tags.php', {
      form: {
        tag_id: firstTagId,
        bill_id: billJson.id,
        tag: firstTagName,
      },
    });

    expect(deleteAttempt.status()).toBe(403);
  });
});
