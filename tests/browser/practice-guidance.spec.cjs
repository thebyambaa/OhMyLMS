const { test, expect } = require('@playwright/test');
const fs = require('node:fs');
const { execFileSync } = require('node:child_process');
const fixture = require('node:path').resolve('test-results/learner-journey-session.json');
test.skip(!fs.existsSync(fixture), 'Requires the explicitly enabled practice fixture.');

// Lesson groups were flattened into their unit (b0e21ca5): guidance continues along the unit, not a lesson page.
test('approved practice refreshes skill guidance and continues within the unit after evaluated answers', async ({ page, context }, testInfo) => {
  const state = JSON.parse(fs.readFileSync(fixture, 'utf8'));
  await context.addCookies(state.cookies);
  execFileSync(state.php_binary, [...state.php_args, 'tests/php/learner-journey-integration.php', 'enroll'], { env: { ...process.env, OHMYLMS_STRUCTURE_INTEGRATION: '1' }, windowsHide: true });
  const problems = [];
  page.on('pageerror', error => problems.push(error.message));
  const oldLesson = new URL(state.overview);
  oldLesson.searchParams.set('hub_lesson', state.lesson);
  await page.goto(oldLesson.href);
  expect(page.url()).toBe(state.unit_url);
  await page.locator('#hub-unit-1').getByRole('link', { name: /Compare numbers/ }).first().click();
  let guidance = page.getByRole('region', { name: 'Lesson practice guidance', exact: true });
  await expect(guidance.getByText('Needs practice', { exact: true })).toBeVisible();
  await expect(guidance.getByText('Up to 3 approved questions per session.', { exact: true })).toBeVisible();
  await expect(guidance.getByRole('link', { name: /^Practise Compare numbers/ })).toBeVisible();
  const runner = page.locator('.ohmylms-practice');
  await runner.getByRole('button', { name: 'Start practice', exact: true }).click();
  for (let index = 0; index < 3; index++) {
    await runner.getByRole('radio', { name: 'Right', exact: true }).check();
    await runner.getByRole('button', { name: 'Check answer', exact: true }).click();
    await runner.getByRole('button', { name: 'Next question', exact: true }).click();
  }
  await expect(runner.locator('.ohmylms-practice-summary')).toContainText('3');
  guidance = page.getByRole('region', { name: 'Lesson practice guidance', exact: true });
  await expect(guidance.getByText('Not started', { exact: true })).toHaveCount(0);
  await expect(guidance.getByText('Target reached', { exact: true })).toBeVisible();
  await expect(page.locator('.oml-player-activity[aria-current="page"] .oml-player-activity-kind')).toContainText('Proficient');
  // Nothing in the unit is complete yet, so the met skill continues at the unit's first open activity.
  const proceed = guidance.getByRole('link', { name: 'Continue unit: Read numbers', exact: true });
  await expect(proceed).toBeVisible();
  await expect(page.locator('a[href*="hub_lesson"]')).toHaveCount(0);
  await page.screenshot({ path: testInfo.outputPath('practice-updated-guidance.png'), fullPage: true });

  const guidanceRoute = '**/learning/practice-guidance?*';
  await page.route(guidanceRoute, route => route.fulfill({ status: 500, contentType: 'application/json', body: JSON.stringify({ message: 'Temporary refresh failure.' }) }));
  await guidance.getByRole('button', { name: 'Refresh skill progress', exact: true }).click();
  await expect(guidance.getByRole('status')).toHaveText('Temporary refresh failure.');
  await expect(guidance.getByText('Target reached', { exact: true })).toBeVisible();
  await page.unroute(guidanceRoute);
  await guidance.getByRole('button', { name: 'Refresh skill progress', exact: true }).click();
  await expect(page.getByRole('region', { name: 'Lesson practice guidance' }).getByRole('status')).toBeEmpty();
  await page.getByRole('link', { name: 'Back to unit progress', exact: true }).click();
  expect(page.url()).toBe(state.unit_url);
  await expect(page.getByRole('progressbar', { name: 'Skill proficiency', exact: true })).toHaveAttribute('value', '100');
  await page.goBack();
  await page.getByRole('region', { name: 'Lesson practice guidance' }).getByRole('link', { name: 'Continue unit: Read numbers', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Read numbers', exact: true })).toBeVisible();
  expect(problems).toEqual([]);
});
