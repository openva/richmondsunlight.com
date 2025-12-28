const { test, expect } = require('@playwright/test');

const billPath = '/bill/2025/hb41/';

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
});
