const { defineConfig } = require('@playwright/test');
module.exports = defineConfig({
 testDir: 'browser', testMatch: 'inline-blank-drag.spec.cjs', workers: 1,
 use: { channel: 'msedge', headless: true },
});
