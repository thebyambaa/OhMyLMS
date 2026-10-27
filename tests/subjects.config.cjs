const { defineConfig } = require('@playwright/test');
const fs = require('node:fs');
const path = require('node:path');
const fixture = path.resolve(__dirname, '../test-results/subjects-browser-session.json');
const host = fs.existsSync(fixture) ? new URL(JSON.parse(fs.readFileSync(fixture, 'utf8')).base).hostname : 'localhost';
module.exports = defineConfig({
  testDir: './browser', testMatch: 'subjects.spec.cjs', timeout: 60000, workers: 1,
  use: { headless: true, viewport: { width: 1440, height: 1000 }, launchOptions: { args: [`--host-resolver-rules=MAP ${host} 127.0.0.1`], ...(process.env.OHMYLMS_CHROMIUM_PATH ? { executablePath: process.env.OHMYLMS_CHROMIUM_PATH } : {}) }, screenshot: 'only-on-failure', trace: 'retain-on-failure' },
  outputDir: '../test-results/subjects-browser', reporter: [['list']],
});
