const { test, expect } = require('@playwright/test');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const fixture = path.resolve('test-results/learner-journey-session.json');
test.skip(!fs.existsSync(fixture), 'Requires the explicitly enabled learner fixture.');

// Lesson groups were flattened into their unit (b0e21ca5): the journey runs on the course overview's unit sections.
test('learner enrolls, completes an activity and resumes within the unit, with separate skill progress', async ({ page, context }, testInfo) => {
  const state = JSON.parse(fs.readFileSync(fixture, 'utf8'));
  await context.addCookies(state.cookies);
  const errors = [];
  await page.addInitScript(() => {
    window.journeyRejections = [];
    window.addEventListener('unhandledrejection', event => {
      window.journeyRejections.push({ code: event.reason?.code, message: event.reason?.message, status: event.reason?.data?.status });
    });
  });
  page.on('pageerror', async error => errors.push({ message: error.message, page: page.url(), rejection: await page.evaluate(() => window.journeyRejections).catch(() => []) }));
  const oldLesson = new URL(state.overview);
  oldLesson.searchParams.set('hub_lesson', state.lesson);
  const unit = page.locator('#hub-unit-1');

  await page.goto(state.overview);
  await expect(page.getByRole('heading', { name: 'Learner journey course', exact: true })).toBeVisible();
  await expect(page.locator('a[href*="hub_lesson"]')).toHaveCount(0);
  // An old lesson-group page moves permanently to its unit; visitors still see titles only.
  const moved = await page.goto(oldLesson.href);
  expect(moved.request().redirectedFrom()).not.toBeNull();
  expect(page.url()).toBe(state.unit_url);
  await expect(unit.getByRole('heading', { name: 'Foundations', exact: true })).toBeVisible();
  await expect(page.getByText('Published activity body for Read numbers.')).toHaveCount(0);

  // Use the existing administrative enrollment controller; checkout is a separate workflow.
  execFileSync(state.php_binary, [...state.php_args, 'tests/php/learner-journey-integration.php', 'enroll'], { env: { ...process.env, OHMYLMS_STRUCTURE_INTEGRATION: '1' }, windowsHide: true });
  await page.goto(oldLesson.href);
  expect(page.url()).toBe(state.unit_url);
  await expect(page.getByRole('progressbar', { name: 'Activity completion', exact: true })).toHaveAttribute('value', '0');
  await expect(page.getByRole('progressbar', { name: 'Skill proficiency', exact: true })).toHaveAttribute('value', '0');
  await expect(unit.locator('.oml-hub-row-name')).toHaveText(['Read numbers', 'Watch numbers', /Compare numbers/]);
  await expect(page.getByRole('heading', { name: 'Introduction', exact: true })).toHaveCount(0);
  await page.screenshot({ path: testInfo.outputPath('learner-unit.png'), fullPage: true });
  await page.getByRole('link', { name: 'Continue learning', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Read numbers', exact: true })).toBeVisible();
  await expect(page.locator('.oml-player-lesson')).toHaveCount(0);
  await expect(page.locator('.oml-player-activity[aria-current="page"]')).toContainText('Read numbers');
  await expect(page.getByRole('link', { name: 'Next activity: Watch numbers', exact: true })).toBeVisible();
  await expect(page.getByRole('link', { name: /^Lesson overview/ })).toHaveCount(0);
  await page.getByRole('button', { name: 'Mark activity complete', exact: true }).click();
  await expect(page.getByText('Activity completed in this course', { exact: true })).toBeVisible();
  await page.getByRole('link', { name: 'Foundations', exact: true }).click();
  expect(page.url()).toBe(state.unit_url);
  await expect(page.getByRole('progressbar', { name: 'Activity completion', exact: true })).toHaveAttribute('value', '33');
  await expect(page.getByRole('progressbar', { name: 'Skill proficiency', exact: true })).toHaveAttribute('value', '0');
  await page.reload();
  await page.getByRole('link', { name: 'Continue learning', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Watch numbers', exact: true })).toBeVisible();
  await page.getByRole('link', { name: /Course overview$/ }).click();
  await expect(unit.locator('.oml-course-unit-meta')).toContainText('1/2');
  await page.setViewportSize({ width: 390, height: 844 });
  await page.screenshot({ path: testInfo.outputPath('learner-unit-mobile.png'), fullPage: true, animations: 'disabled' });
  expect(errors).toEqual([]);
});
