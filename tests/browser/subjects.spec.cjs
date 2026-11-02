const { test, expect } = require('@playwright/test');
const fs = require('node:fs');
const fixture = require('node:path').resolve('test-results/subjects-browser-session.json');
// Create with `OHMYLMS_SUBJECTS_INTEGRATION=1 php tests/php/subjects-integration.php browser`; remove with `... cleanup`.
test.skip(!fs.existsSync(fixture), 'Requires the explicitly enabled local subject fixture.');
let state;
test.beforeEach(() => { state = JSON.parse(fs.readFileSync(fixture, 'utf8')); });

test('visitors browse subjects and published outlines without seeing drafts', async ({ page }, testInfo) => {
  // The site's existing SDK requests /wp/v2/users/me even on the anonymous home page.
  // Tolerate only that independently verified guest-preferences rejection; other errors still fail.
  await page.addInitScript(() => window.addEventListener('unhandledrejection', event => {
    if (event.reason?.code === 'rest_not_logged_in' && event.reason?.data?.status === 401) event.preventDefault();
  }));
  const problems = [];
  page.on('pageerror', error => problems.push(error.message));
  await page.goto(`${state.base}/?ohmylms_subject=all`);
  await expect(page.getByRole('heading', { name: 'Browse subjects', exact: true })).toBeVisible();
  await page.getByRole('link', { name: 'Subject browser fixture', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Subject browser fixture', exact: true })).toBeVisible();
  // The fixture saves an authored order through the subject course order API; drafts, private and roadmap courses drop out.
  await expect(page.locator('.oml-subject-course h2')).toHaveText(['Zulu course fixture', 'Alpha course fixture']);
  await expect(page.getByRole('link', { name: 'Sign up to enroll', exact: true })).toHaveCount(2);
  await expect(page.getByRole('link', { name: 'View course', exact: true })).toHaveCount(2);
  await expect(page.getByText('Hidden draft fixture', { exact: true })).toHaveCount(0);
  await expect(page.getByText('Hidden private fixture', { exact: true })).toHaveCount(0);
  await expect(page.getByText('Hidden roadmap fixture', { exact: true })).toHaveCount(0);
  await page.getByText('View published outline', { exact: true }).click();
  await expect(page.getByText('Published unit fixture', { exact: true })).toBeVisible();
  await expect(page.getByText('Hidden draft unit fixture', { exact: true })).toHaveCount(0);
  await page.screenshot({ path: testInfo.outputPath('subject-courses.png'), fullPage: true });
  await page.getByRole('link', { name: 'Zulu course fixture', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Zulu course fixture', exact: true })).toBeVisible();
  await page.getByRole('link', { name: /Subject browser fixture/ }).first().click();
  await expect(page.getByRole('heading', { name: 'Subject browser fixture', exact: true })).toBeVisible();
  await page.setViewportSize({ width: 390, height: 844 });
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
  await page.screenshot({ path: testInfo.outputPath('subject-mobile.png'), fullPage: true });
  const unavailable = await page.goto(`${state.base}/?ohmylms_subject=${state.items[2]}`);
  expect(unavailable.status()).toBe(404);
  await expect(page.getByRole('heading', { name: 'Subject not available', exact: true })).toBeVisible();
  expect(problems).toEqual([]);
});

test('signed-in learners get the authored order and continue where they left off', async ({ page, context }) => {
  await context.addCookies(state.cookies);
  const problems = [];
  page.on('pageerror', error => problems.push(error.message));
  await page.goto(`${state.base}/?ohmylms_subject=${state.subject}`);
  await expect(page.locator('.oml-subject-course h2')).toHaveText(['Zulu course fixture', 'Alpha course fixture']);
  const cards = page.locator('.oml-subject-course');
  await expect(cards.nth(0).getByRole('link', { name: 'Continue learning', exact: true })).toBeVisible();
  await expect(cards.nth(1).getByRole('link', { name: 'Start course', exact: true })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Sign up to enroll', exact: true })).toHaveCount(0);
  await cards.nth(1).getByRole('link', { name: 'Alpha course fixture', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Alpha course fixture', exact: true })).toBeVisible();
  await page.getByRole('link', { name: /Subject browser fixture/ }).first().click();
  await expect(page.getByRole('heading', { name: 'Subject browser fixture', exact: true })).toBeVisible();
  expect(problems).toEqual([]);
});
