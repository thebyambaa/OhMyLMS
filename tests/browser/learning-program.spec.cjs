const { test, expect } = require('@playwright/test');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const php = 'C:/Users/Byambaa/AppData/Roaming/Local/lightning-services/php-8.2.29+0/bin/win64/php.exe';
const fixture = (...args) => execFileSync(php, ['-d', `extension_dir=${path.dirname(php)}/ext`, '-d', 'extension=mysqli', '-d', 'extension=mbstring', '-d', 'memory_limit=1024M', 'tests/php/learning-browser-fixture.php', ...args], { env: process.env, encoding: 'utf8', timeout: 120000 });
const admin = () => JSON.parse(fs.readFileSync(process.env.OHMYLMS_TEST_CREDENTIALS, 'utf8'));
let state;
test.describe.configure({ mode: 'serial' });
test.beforeAll(() => { state = JSON.parse(fixture('setup').trim().split('\n').pop()); });
test.afterAll(() => { if (state) fixture('cleanup', state.tag); });
async function login(page, user, password) {
  await page.goto('/wp-login.php');
  await page.locator('#user_login').fill(user); await page.locator('#user_pass').fill(password);
  await page.locator('#wp-submit').click(); await page.waitForURL((url) => !url.pathname.endsWith('wp-login.php'));
}
test('teacher publishes a blended path with a reused lesson and explicit skill target', async ({ page }, testInfo) => {
  await page.addInitScript(() => Object.defineProperty(window.crypto, 'randomUUID', { value: undefined }));
  const errors = []; page.on('pageerror', (error) => errors.push(error.message));
  await login(page, admin().username, admin().password);
  await page.goto(`/wp-admin/admin.php?page=ohmylms&ohmylms_legacy=1#/course-edit/${state.course}/settings/learning`);
  await expect(page.getByRole('heading', { name: 'Learning program', exact: true })).toBeVisible({ timeout: 25000 });
  await page.getByRole('radio', { name: 'Blended', exact: true }).check();
  await page.getByLabel('Select skill', { exact: true }).selectOption(String(state.skill));
  await page.getByRole('button', { name: 'Add outcome', exact: true }).click();
  await page.getByRole('button', { name: 'Add practice step', exact: true }).click();
  await page.getByLabel('Search resources', { exact: true }).fill(state.tag);
  await page.getByRole('button', { name: 'Search', exact: true }).click();
  await page.getByRole('button', { name: 'Add to path', exact: true }).click();
  await page.getByRole('button', { name: 'Move up', exact: true }).last().click();
  await page.getByRole('button', { name: 'Preview completion rules', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Completion preview', exact: true })).toBeVisible();
  await expect(page.getByText('All requirements satisfied: Eligible', { exact: true })).toBeVisible();
  await page.getByRole('checkbox', { name: /Apply this publication/ }).check();
  await page.getByRole('button', { name: 'Publish learning program', exact: true }).click();
  await expect(page.locator('.components-notice__content').getByText('Learning program published.', { exact: true })).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('teacher-learning-desktop.png'), fullPage: true });
  await page.reload();
  await expect(page.getByRole('radio', { name: 'Blended', exact: true })).toBeChecked();
  const program = await page.evaluate((id) => wp.apiFetch({ path: `/ohmylms/v1/courses/${id}/learning` }), state.course);
  expect(program.published.items.map((item) => item.type)).toEqual(['lesson', 'practice']);
  expect(program.published.items[0].content_id).toBe(state.lesson);
  await page.getByRole('button', { name: 'Learner progress', exact: true }).click();
  await expect(page.getByText('0/1', { exact: true }).first()).toBeVisible();
  expect(errors).toEqual([]);
});
test('learner completes shared lesson then reaches skill target without a duplicate award', async ({ page }, testInfo) => {
  const errors = []; page.on('pageerror', (error) => errors.push(error.message));
  await login(page, state.login, state.password);
  await page.goto(`/?ohmylms_learning=${state.course}`);
  await expect(page.getByRole('heading', { name: `Blended fractions ${state.tag}`, exact: true })).toBeVisible();
  await expect(page.locator('.ohmylms-learning-dimensions')).toContainText('0 of 1 required');
  await page.getByRole('link', { name: new RegExp(`Continue with Equivalent fractions shared lesson`) }).click();
  await expect(page.getByText('Two fractions are equivalent when they describe the same amount.', { exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'Mark lesson complete', exact: true }).click();
  await expect(page.getByText('Lesson completed in this course', { exact: true })).toBeVisible();
  await page.getByRole('link', { name: 'Back to learning path', exact: true }).click();
  await expect(page.getByText('Course completed', { exact: true })).toHaveCount(0);
  await page.screenshot({ path: testInfo.outputPath('learner-learning-desktop.png'), fullPage: true });
  for (const width of [390, 320]) {
    await page.setViewportSize({ width, height: 844 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({ path: testInfo.outputPath(`learner-learning-${width}.png`), fullPage: true });
  }
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.getByRole('link', { name: 'Practice', exact: true }).click();
  await page.getByRole('button', { name: 'Start practice', exact: true }).click();
  for (let i = 0; i < 3; i++) {
    await page.getByRole('radio', { name: 'Same value', exact: true }).check();
    await page.getByRole('button', { name: 'Check answer', exact: true }).click();
    await page.getByRole('button', { name: 'Next question', exact: true }).click();
    if (i === 0) {
      await page.reload();
      await page.getByRole('button', { name: 'Resume practice', exact: true }).click();
      await expect(page.locator('.ohmylms-practice-progress')).toContainText('Question 2');
    }
  }
  await page.goto(`/?ohmylms_learning=${state.course}`);
  await expect(page.getByText('Course completed', { exact: true })).toBeVisible();
  await expect(page.getByText('Target met', { exact: true })).toBeVisible();
  await page.reload();
  await expect(page.getByText('Course completed', { exact: true })).toBeVisible();
  expect(errors).toEqual([]);
});
