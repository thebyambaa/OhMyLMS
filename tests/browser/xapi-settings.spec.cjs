/** Settings-tab tests against an authenticated admin UI; xAPI API writes and LRS actions are mocked. */
const { test, expect } = require('@playwright/test');
test.use({ storageState: process.env.OHMYLMS_TEST_STORAGE_STATE || undefined });
test.skip(!process.env.OHMYLMS_TEST_STORAGE_STATE, 'Requires an authenticated test administrator storage state');

const snapshot = () => ({
  settings: { enabled: 1, endpoint: 'https://lrs.example.test/xapi', key: 'test-key', actor: 'pseudonym', responses: 1, retention_days: 14, groups: { enrolled: 1, lessons: 1, courses: 1, attempts: 1, answers: 1, skills: 1 } },
  has_secret: true, locked: { endpoint: false, key: false, secret: false },
  group_labels: { enrolled: 'Course enrolment', lessons: 'Lesson completion', courses: 'Course completion', attempts: 'Quiz attempts and results', answers: 'Individual question answers', skills: 'Skill mastery' },
  counts: { pending: 3, sent: 12, failed: 1, skipped: 0 }, health: {},
  failures: [{ id: 1, kind: 'answered', tries: 10, last_error: 'Example failure' }],
});

async function mockApi(page, data = snapshot()) {
  const calls = [];
  await page.route(/\/xapi\/settings(?:\/(?:test|send|retry))?(?:\?|$)/, async route => {
    const request = route.request();
    const action = new URL(request.url()).pathname.split('/').pop();
    calls.push({ action, method: request.method(), body: request.method() === 'POST' ? request.postDataJSON() : null });
    if (action !== 'settings') {
      await route.fulfill({ json: { ok: action !== 'test', message: action === 'test' ? 'Example connection problem' : 'Example action completed', snapshot: data } });
    } else {
      if (request.method() === 'POST') {
        const { secret, ...settings } = request.postDataJSON();
        data.settings = settings;
        data.has_secret = !!secret || data.has_secret;
      }
      await route.fulfill({ json: data });
    }
  });
  return calls;
}

test('xAPI settings save and delivery actions stay in the Settings tab', async ({ page }) => {
  const calls = await mockApi(page);
  await page.goto('/wp-admin/admin.php?page=ohmylms#/settings/xapi-settings');
  const panel = page.locator('.ohmylms-tab-panel-body');
  await expect(panel.getByRole('heading', { name: 'xAPI', exact: true })).toBeVisible();
  await expect(page.locator('#toplevel_page_ohmylms .wp-submenu a[href*="page=ohmylms-xapi"]')).toHaveCount(0);
  await expect(panel.getByLabel('Secret (password)', { exact: true })).toHaveValue('');
  await expect(panel.getByText('A secret is saved. Leave blank to keep it.')).toBeVisible();
  let navigations = 0;
  page.on('request', request => { if (request.isNavigationRequest() && request.frame() === page.mainFrame()) navigations++; });
  await panel.getByLabel('LRS endpoint', { exact: true }).fill('https://changed.example.test/xapi');
  await expect(panel.getByRole('button', { name: 'Test connection', exact: true })).toBeDisabled();
  await panel.getByRole('button', { name: 'Save settings', exact: true }).click();
  await expect(panel.getByText('xAPI settings saved.', { exact: true })).toBeVisible();
  const saved = calls.find(call => call.action === 'settings' && call.method === 'POST');
  expect(saved.body.secret).toBe('');
  expect(saved.body.endpoint).toBe('https://changed.example.test/xapi');
  for (const [button, action, message] of [
    ['Test connection', 'test', 'Example connection problem'],
    ['Send queued now', 'send', 'Example action completed'],
    ['Retry failed', 'retry', 'Example action completed'],
  ]) {
    await panel.getByRole('button', { name: button, exact: true }).click();
    await expect(panel.getByText(message, { exact: true })).toBeVisible();
    expect(calls.some(call => call.action === action && call.method === 'POST')).toBe(true);
  }
  expect(navigations).toBe(0);
  await expect(page).toHaveURL(/#\/settings\/xapi-settings$/);
});

test('configuration constants lock connection inputs', async ({ page }) => {
  const data = snapshot();
  data.locked = { endpoint: true, key: true, secret: true };
  await mockApi(page, data);
  await page.goto('/wp-admin/admin.php?page=ohmylms#/settings/xapi-settings');
  for (const label of ['LRS endpoint', 'Key (username)', 'Secret (password)']) {
    await expect(page.getByLabel(label, { exact: true })).toBeDisabled();
  }
});

test('the add-on Manage button opens the xAPI Settings tab without navigation', async ({ page }) => {
  await mockApi(page);
  await page.goto('/wp-admin/admin.php?page=ohmylms#/integrations');
  const heading = page.getByRole('heading', { name: 'xAPI', exact: true });
  await expect(heading).toBeVisible();
  const card = heading.locator('xpath=ancestor::div[.//button[@aria-label="Manage"]][1]');
  let navigations = 0;
  page.on('request', request => { if (request.isNavigationRequest() && request.frame() === page.mainFrame()) navigations++; });
  await card.getByRole('button', { name: 'Manage', exact: true }).click();
  await expect(page).toHaveURL(/#\/settings\/xapi-settings$/);
  await expect(page.getByLabel('LRS endpoint', { exact: true })).toBeVisible();
  expect(navigations).toBe(0);
});
