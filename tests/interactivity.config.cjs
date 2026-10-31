const { defineConfig } = require('@playwright/test');
const path = require('node:path');
const php = process.env.OHMYLMS_PHP_BIN || 'php';
// Installed Microsoft Edge by default; OHMYLMS_BROWSER_CHANNEL='' uses Playwright's Chromium (npx playwright install chromium).
const channel = process.env.OHMYLMS_BROWSER_CHANNEL ?? 'msedge';
module.exports = defineConfig({
  testDir: 'browser', testMatch: ['interactivity.spec.cjs', 'learner-no-jquery.spec.cjs'], timeout: 30000, workers: 1,
  outputDir: '../test-results/interactivity-browser',
  use: { baseURL: 'http://127.0.0.1:8108', ...(channel ? { channel } : {}), headless: true },
  webServer: { command: `"${php}" -S 127.0.0.1:8108 "${path.join(__dirname, 'fixtures/interactivity.php')}"`, url: 'http://127.0.0.1:8108/fixture', reuseExistingServer: false, timeout: 15000 },
});
