const { test, expect } = require('@playwright/test');

/**
 * Helper function to generate a unique email address for testing
 */
function generateUniqueEmail() {
  const timestamp = Date.now();
  const randomString = Math.random().toString(36).substring(7);
  return `test-${timestamp}-${randomString}@example.com`;
}

/**
 * Helper function to fill in the registration form with valid data
 * @param {object} page - Playwright page object
 * @param {object} overrides - Optional overrides for specific fields
 */
async function fillRegistrationForm(page, overrides = {}) {
  const defaults = {
    name: 'John Doe',
    email: generateUniqueEmail(),
    password: 'SecurePass123!',
    password2: 'SecurePass123!',
    organization: '',
    url: '',
    zip: '',
  };

  const data = { ...defaults, ...overrides };

  await page.fill('input[name="form_data[name]"]', data.name);
  await page.fill('input[name="form_data[email]"]', data.email);
  await page.fill('input[name="form_data[password]"]', data.password);
  await page.fill('input[name="form_data[password_2]"]', data.password2);

  if (data.organization) {
    await page.fill('input[name="form_data[organization]"]', data.organization);
  }

  if (data.url) {
    await page.fill('input[name="form_data[url]"]', data.url);
  }

  if (data.zip) {
    await page.fill('input[name="form_data[zip]"]', data.zip);
  }
}

test.describe('Account Registration Form', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/account/register/');
    await expect(page.locator('h1, legend').filter({ hasText: 'Create Your Account' })).toBeVisible();
  });

  test('1. Happy path - successful registration with all required fields', async ({ page }) => {
    await fillRegistrationForm(page);

    // Wait a bit to avoid the 5-second spam detection
    await page.waitForTimeout(6000);

    await page.click('input[type="submit"][value="Create My Account"]');

    // Should see success message
    await expect(page.locator('text=Thanks for Registering!')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('a[href="/photosynthesis/"]').filter({ hasText: 'Get Started' })).toBeVisible();
  });

  test('2. Password too short - minimum length validation (< 8 characters)', async ({ page }) => {
    // Fill the form manually to bypass client-side validation by removing the required attribute
    await page.fill('input[name="form_data[name]"]', 'John Doe');
    await page.fill('input[name="form_data[email]"]', generateUniqueEmail());
    await page.fill('input[name="form_data[password]"]', 'short1');
    await page.fill('input[name="form_data[password_2]"]', 'short1');

    await page.waitForTimeout(6000);
    await page.click('input[type="submit"][value="Create My Account"]');

    // Should see error message about password length - look for the error in the messages div
    await expect(page.locator('div#messages.errors')).toBeVisible();
    await expect(page.locator('div#messages.errors')).toContainText('a password that');
    await expect(page.locator('div#messages.errors')).toContainText('at least 8 characters long');
  });

  test('3. Password mismatch - confirmation field validation', async ({ page }) => {
    await fillRegistrationForm(page, {
      password: 'SecurePass123!',
      password2: 'DifferentPass456!',
    });

    await page.waitForTimeout(6000);
    await page.click('input[type="submit"][value="Create My Account"]');

    // Should see error message about password mismatch
    await expect(page.locator('text=the same password twice')).toBeVisible();
  });

  test('4. Invalid email format - email validation', async ({ page }) => {
    // Remove the email input type to bypass HTML5 validation
    await page.evaluate(() => {
      const emailInput = document.querySelector('input[name="form_data[email]"]');
      emailInput.setAttribute('type', 'text');
    });

    await page.fill('input[name="form_data[name]"]', 'John Doe');
    await page.fill('input[name="form_data[email]"]', 'notanemail');
    await page.fill('input[name="form_data[password]"]', 'SecurePass123!');
    await page.fill('input[name="form_data[password_2]"]', 'SecurePass123!');

    await page.waitForTimeout(6000);
    await page.click('input[type="submit"][value="Create My Account"]');

    // Should see error message about invalid email
    await expect(page.locator('text=a valid e-mail address')).toBeVisible();
  });

  test('5. Missing required fields - field presence validation', async ({ page }) => {
    // Remove required attributes to bypass HTML5 validation
    await page.evaluate(() => {
      document.querySelectorAll('input[required]').forEach(input => {
        input.removeAttribute('required');
      });
    });

    // Submit empty form
    await page.waitForTimeout(6000);
    await page.click('input[type="submit"][value="Create My Account"]');

    // Should see error messages for all required fields
    await expect(page.locator('text=your name')).toBeVisible();
    await expect(page.locator('text=your e-mail address')).toBeVisible();
    await expect(page.locator('text=your choice of password')).toBeVisible();
  });

  test('6. Duplicate email - uniqueness validation', async ({ page }) => {
    // Use the known test user email
    await fillRegistrationForm(page, {
      email: 'testuser@example.com',
    });

    await page.waitForTimeout(6000);

    // Submit and wait for response
    await Promise.all([
      page.waitForLoadState('domcontentloaded'),
      page.click('input[type="submit"][value="Create My Account"]')
    ]);

    // Wait for page to finish loading
    await page.waitForLoadState('networkidle', { timeout: 15000 });

    // Should see error message about email already in use
    await expect(page.locator('div#messages.errors')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('div#messages.errors')).toContainText('an e-mail address');
    await expect(page.locator('div#messages.errors')).toContainText('not already in use');
    await expect(page.locator('a[href="/account/reset-password/"]').filter({ hasText: 'reset your password' })).toBeVisible();
  });

  test('7. Invalid ZIP code format - ZIP validation (not 5 digits)', async ({ page }) => {
    await fillRegistrationForm(page, {
      zip: '123', // Too short
    });

    await page.waitForTimeout(6000);
    await page.click('input[type="submit"][value="Create My Account"]');

    // HTML5 pattern validation should prevent submission or show browser error
    // The pattern [0-9]{5} should trigger validation
    const zipInput = page.locator('input[name="form_data[zip]"]');
    const validationMessage = await zipInput.evaluate(el => el.validationMessage);
    expect(validationMessage).toBeTruthy();
  });

  test('8. Invalid ZIP code format - non-numeric characters', async ({ page }) => {
    await fillRegistrationForm(page, {
      zip: 'abcde',
    });

    await page.waitForTimeout(6000);
    await page.click('input[type="submit"][value="Create My Account"]');

    // HTML5 pattern validation should prevent submission
    const zipInput = page.locator('input[name="form_data[zip]"]');
    const validationMessage = await zipInput.evaluate(el => el.validationMessage);
    expect(validationMessage).toBeTruthy();
  });

  test('9. Invalid URL format - URL validation', async ({ page }) => {
    await fillRegistrationForm(page, {
      url: 'not a valid url',
    });

    await page.waitForTimeout(6000);
    await page.click('input[type="submit"][value="Create My Account"]');

    // HTML5 URL validation should prevent submission or show browser error
    const urlInput = page.locator('input[name="form_data[url]"]');
    const validationMessage = await urlInput.evaluate(el => el.validationMessage);
    expect(validationMessage).toBeTruthy();
  });

  test('10. Optional fields work correctly - organization, website, mailing list', async ({ page }) => {
    await fillRegistrationForm(page, {
      organization: 'Test Organization Inc.',
      url: 'https://example.com',
      zip: '23219', // Valid Virginia ZIP
    });

    // Uncheck the mailing list checkbox (it's checked by default)
    await page.uncheck('input[name="form_data[mailing_list]"]');

    await page.waitForTimeout(6000);
    await page.click('input[type="submit"][value="Create My Account"]');

    // Should successfully register with optional fields
    await expect(page.locator('text=Thanks for Registering!')).toBeVisible({ timeout: 10000 });
  });

  test('11. Spam honeypot field exists and is hidden', async ({ page }) => {
    // This test verifies that the honeypot field exists in the form and is hidden from view
    // The actual spam detection happens server-side and is difficult to test reliably
    // in an automated browser environment

    const honeypotField = page.locator('input[name="age"]');
    await expect(honeypotField).toBeAttached();

    // Verify the honeypot is hidden (in a div with display: none)
    const isHidden = await page.evaluate(() => {
      const field = document.querySelector('input[name="age"]');
      const parent = field.closest('div');
      const style = window.getComputedStyle(parent);
      return style.display === 'none';
    });

    expect(isHidden).toBe(true);
  });
});
