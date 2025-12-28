const { test, expect } = require('@playwright/test');

test('can log in with test user and see account links', async ({ page }) => {
  // Submit login via API to avoid HTTPS redirect issues, then set session cookie in the browser.
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

  await page.goto('/');
  await expect(page.locator('a', { hasText: 'Log Out' })).toBeVisible({ timeout: 10000 });
});
