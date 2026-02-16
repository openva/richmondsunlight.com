const { test, expect } = require('@playwright/test');

test('can log in with test user and see account links', async ({ page }) => {
  // Submit login, then set session cookie in the browser.
  const resp = await page.request.post('/account/login/', {
    form: {
      'form_data[email]': 'tester@example.com',
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

  await page.goto('/');
  await expect(page.locator('a', { hasText: 'Log Out' })).toBeVisible({ timeout: 10000 });
});

test('login redirect stays on the same host and scheme', async ({ page }) => {
  // Submit the login form as a raw request so we can inspect the Location header directly.
  // If login.php uses a hardcoded https:// URL, the Location header would be https://... even
  // when the server is only reachable via http://, and the browser would fail to follow it.
  const resp = await page.request.post('/account/login/', {
    form: {
      'form_data[email]': 'tester@example.com',
      'form_data[password]': 'password123',
      submit: 'Log In',
      'form_data[return_uri]': '/',
    },
    maxRedirects: 0,
  });

  expect(resp.status()).toBe(302);
  const location = resp.headers()['location'] || '';
  // The redirect must use http://, not https://, in the local test environment.
  expect(location).toMatch(/^http:\/\//);
  expect(location).not.toMatch(/^https:\/\//);

  // Also verify the session is usable after the redirect.
  const setCookie = resp.headers()['set-cookie'] || '';
  const match = setCookie.match(/PHPSESSID=([^;]+)/);
  if (!match) throw new Error('Login did not return a PHPSESSID cookie');
  await page.context().addCookies([
    { name: 'PHPSESSID', value: match[1], domain: 'rs_web', path: '/' },
  ]);
  await page.goto('/');
  await expect(page.locator('a', { hasText: 'Log Out' })).toBeVisible({ timeout: 10000 });
});

test('can log out and see login link', async ({ page }) => {
  // Log in and seed the session cookie
  const resp = await page.request.post('/account/login/', {
    form: {
      'form_data[email]': 'tester@example.com',
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

  // Verify logged in
  await page.goto('/');
  await expect(page.locator('a', { hasText: 'Log Out' })).toBeVisible({ timeout: 10000 });

  // Hit logout endpoint
  await page.goto('/account/logout/');

  // After logout, header should show login link
  await page.goto('/');
  await expect(page.locator('a', { hasText: 'Log In' })).toBeVisible({ timeout: 10000 });
});
