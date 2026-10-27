const {test} = require('@playwright/test');
const fs = require('node:fs');
const {verifyMovableTabs} = require('../helpers/movable-tabs-ui.cjs');

test('submenu tabs drag, group, retain colors and order after reload, and retry failed saves', async ({page}) => {
  const credentials = JSON.parse(fs.readFileSync(process.env.OHMYLMS_TEST_CREDENTIALS, 'utf8'));
  await page.goto('/wp-login.php');
  await page.locator('#user_login').fill(credentials.username);
  await page.locator('#user_pass').fill(credentials.password);
  await page.locator('#wp-submit').click();
  await page.waitForURL(/wp-admin/);
  await verifyMovableTabs(page);
});
