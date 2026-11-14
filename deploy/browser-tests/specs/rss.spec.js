const { test, expect } = require('@playwright/test');

// The site's RSS feeds, all RSS 2.0. (day-page.spec.js covers the feed of days in more detail.)
const feeds = [
  '/rss/bill/hb41/',
  '/rss/tag/police/',
  '/rss/legislator/nbennis/',
  '/rss/comments/',
  '/photosynthesis/rss/portfolio/pwt01/',
  '/rss/days/',
];

for (const path of feeds) {
  test(`${path} is an RSS 2.0 feed`, async ({ request }) => {
    const response = await request.get(path);
    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('application/rss+xml');
    const body = await response.text();
    expect(body).toMatch(/^<\?xml version="1\.0" encoding="utf-8"\?>/);
    expect(body).toContain('<rss version="2.0"');
    expect(body).not.toContain('0.91');
    expect(body).toMatch(/<atom:link href="https:\/\/www\.richmondsunlight\.com\/[^"]+" rel="self"/);
    // Every item has a guid.
    const items = (body.match(/<item>/g) || []).length;
    expect(items).toBeGreaterThan(0);
    expect((body.match(/<guid /g) || []).length).toBe(items);
  });
}
