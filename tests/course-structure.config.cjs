const { defineConfig } = require('@playwright/test');
const fs = require('node:fs');
const path = require('node:path');
const session = path.resolve(__dirname, '../test-results/structure-browser-session.json');
const host = fs.existsSync(session) ? new URL(JSON.parse(fs.readFileSync(session, 'utf8')).base).hostname : 'localhost';
module.exports = defineConfig({
  testDir: './browser', testMatch: 'course-structure.spec.cjs', timeout: 90000, expect: { timeout: 15000 }, workers: 1,
  use: { headless: true, viewport: { width: 1440, height: 1000 }, launchOptions: { args: [`--host-resolver-rules=MAP ${host} 127.0.0.1`], ...(process.env.OHMYLMS_CHROMIUM_PATH ? { executablePath: process.env.OHMYLMS_CHROMIUM_PATH } : {}) }, screenshot: 'only-on-failure', trace: 'retain-on-failure' },
  outputDir: '../test-results/course-structure-browser', reporter: [['list']],
});
