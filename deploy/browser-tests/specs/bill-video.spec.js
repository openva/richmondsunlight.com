const { test, expect } = require('@playwright/test');

// Fixtures for these bills are in deploy/mysql/video-bill-fixtures.sql.
const multiClipBill = '/bill/2025/sb738/';
const singleClipBill = '/bill/2025/sb805/';

test.describe('Bill video', () => {
  test('lists each clip with where and when it was recorded', async ({ page }) => {
    await page.goto(multiClipBill);

    const section = page.locator('#video');
    await expect(section.locator('video')).toHaveCount(1);
    await expect(page.locator('script[src*="flowplayer"]')).toHaveCount(0);

    const clips = section.locator('.clip-player-clip');
    await expect(clips).toHaveCount(2);
    await expect(clips.nth(0).locator('.clip-player-meeting')).toHaveText('Senate floor session');
    await expect(clips.nth(0).locator('time')).toHaveText('April 2, 2025');
    await expect(clips.nth(1).locator('.clip-player-meeting')).toHaveText(
      'Senate School Health Services Subcommittee to Study SB1303'
    );
  });

  test('shows only the current clip’s transcript, with speakers', async ({ page }) => {
    await page.goto(multiClipBill);

    const clips = page.locator('#video .clip-player-clip');
    const first = clips.nth(0).locator('.clip-player-transcript');
    const second = clips.nth(1).locator('.clip-player-transcript');

    await expect(first).toBeVisible();
    await expect(second).toBeHidden();
    await expect(first).toContainText('cell phone usage policy');
    await expect(first.locator('a.video-speaker').first()).toHaveAttribute(
      'href',
      '/legislator/sgpekarsky/'
    );
    await expect(page.locator('#video .clip-player-now')).toHaveText(
      'Clip 1 of 2: Senate floor session, April 2, 2025'
    );
  });

  test('switches clips from the list and from the previous/next buttons', async ({ page }) => {
    await page.goto(multiClipBill);

    const section = page.locator('#video');
    const clips = section.locator('.clip-player-clip');
    const video = section.locator('video');
    const prev = section.locator('.clip-player-prev');
    const next = section.locator('.clip-player-next');

    await expect(prev).toBeDisabled();
    await expect(next).toBeEnabled();

    await clips.nth(1).locator('.clip-player-select').click();
    await expect(clips.nth(1)).toHaveClass(/is-active/);
    await expect(clips.nth(1).locator('.clip-player-select')).toHaveAttribute('aria-expanded', 'true');
    await expect(clips.nth(0).locator('.clip-player-select')).toHaveAttribute('aria-expanded', 'false');
    await expect(clips.nth(1).locator('.clip-player-transcript')).toBeVisible();
    await expect(clips.nth(0).locator('.clip-player-transcript')).toBeHidden();
    await expect(section.locator('.clip-player-now')).toHaveText(/^Clip 2 of 2: /);
    await expect(video).toHaveAttribute('src', /rs-senate-20250915/);
    await expect(next).toBeDisabled();

    await prev.click();
    await expect(clips.nth(0)).toHaveClass(/is-active/);
    await expect(video).toHaveAttribute('src', /rs-senate-20250402/);
  });

  test('opens the clip named in the URL fragment', async ({ page }) => {
    await page.goto(multiClipBill + '#video-clip-2');
    await expect(page.locator('#video-clip-2')).toHaveClass(/is-active/);
    await expect(page.locator('#video .clip-player-now')).toHaveText(/^Clip 2 of 2: /);
  });

  test('omits clip navigation when there is only one clip', async ({ page }) => {
    await page.goto(singleClipBill);

    const section = page.locator('#video');
    await expect(section.locator('.clip-player-clip')).toHaveCount(1);
    await expect(section.locator('.clip-player-prev')).toBeHidden();
    await expect(section.locator('.clip-player-next')).toBeHidden();
    await expect(section.locator('.clip-player-now')).toHaveText('Senate floor session, April 2, 2025');
    await expect(section.locator('.clip-player-transcript')).toContainText('child support');
  });
});
