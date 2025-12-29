const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: __dirname + '/specs',
  timeout: 60_000,
  retries: 0,
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://rs_web',
    headless: true,
    viewport: { width: 1280, height: 720 },
  },
});
