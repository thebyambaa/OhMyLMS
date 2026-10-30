// Assessment engine end-to-end checks on the isolated test site: exam page breaks, autosave and
// resume, keyboard and mobile use, lesson checks with guest claim, skill practice, the school
// portal skill views. The Question Bank and Skills screens are covered by the new app's tests.
const { test, expect } = require('@playwright/test');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');

const PHP =
  process.env.OHMYLMS_PHP ||
  'C:/Users/Byambaa/AppData/Roaming/Local/lightning-services/php-8.2.30+1/bin/win64/php.exe';
const fixture = (...args) =>
  execFileSync(
    PHP,
    [
      '-d',
      'memory_limit=512M',
      '-d',
      `extension_dir=${path.dirname(PHP)}/ext`,
      '-d',
      'extension=mysqli',
      '-d',
      'extension=mbstring',
      path.resolve('tests/php/assessment-browser-fixture.php'),
      ...args,
    ],
    { env: process.env, timeout: 120000, encoding: 'utf8' },
  );

test.describe.configure({ mode: 'serial' });
let state;
test.beforeAll(() => {
  state = JSON.parse(fixture('setup').trim().split('\n').pop());
});
test.afterAll(() => {
  if (state) fixture('cleanup', state.tag);
});

async function login(page, login, password, redirect = '') {
  await page.goto('/wp-login.php' + (redirect ? `?redirect_to=${encodeURIComponent(redirect)}` : ''));
  await page.locator('#user_login').fill(login);
  await page.locator('#user_pass').fill(password);
  await page.locator('#wp-submit').click();
  await page.waitForURL((url) => !url.pathname.endsWith('wp-login.php'));
}
const admin = () => JSON.parse(fs.readFileSync(process.env.OHMYLMS_TEST_CREDENTIALS, 'utf8'));
const user = (role) => state.users[role];
const noHorizontalScroll = (page) =>
  page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth);
function trackErrors(page) {
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  return errors;
}

test('author adds a page break and publishes the exam', async ({ page }) => {
  const errors = trackErrors(page);
  await login(page, admin().username, admin().password);
  await page.goto(`/wp-admin/post.php?post=${state.quiz}&action=edit`);
  await page.waitForFunction(() => window.wp?.plugins?.getPlugin('ohmylms-native-quiz-settings'));
  await page.evaluate(() => wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/document'));
  const assessmentPanel = page.getByRole('button', {name: 'Assessment and pools', exact: true});
  await expect(assessmentPanel).toBeVisible();
  if ((await assessmentPanel.getAttribute('aria-expanded')) !== 'true') await assessmentPanel.click();
  const breaks = page.getByRole('checkbox', { name: 'Start on a new page' });
  await expect(breaks).toHaveCount(2);
  await breaks.nth(1).check();
  await page.getByRole('button', { name: 'Save assessment settings' }).click();
  await expect(page.getByText('Assessment settings saved').first()).toBeVisible();
  await page.evaluate(async () => {
    wp.data.dispatch('core/editor').editPost({status: 'publish'});
    await wp.data.dispatch('core/editor').savePost();
  });
  await expect.poll(() => page.evaluate(() => wp.data.select('core/editor').getCurrentPost().status)).toBe('publish');
  expect(errors).toEqual([]);
});

test('learner takes the paged exam on a phone with keyboard, autosave and resume', async ({
  page,
}) => {
  const errors = trackErrors(page);
  await page.setViewportSize({ width: 390, height: 844 });
  await login(page, user('learner').login, user('learner').password, state.quiz_url);
  await page.goto(state.quiz_url);
  await page.getByRole('button', { name: 'Start Quiz' }).click();
  const pageOne = page.locator('.ohmylms-question-group.question-group-1');
  const pageTwo = page.locator('.ohmylms-question-group.question-group-2');
  await expect(pageOne).toBeVisible();
  await expect(pageTwo).toBeHidden();
  await expect(pageOne.getByRole('heading', { name: 'Part A' })).toBeVisible();
  expect(await noHorizontalScroll(page)).toBe(true);

  // Answer the first question with the keyboard only.
  const four = pageOne.getByRole('radio', { name: '4', exact: true });
  await four.focus();
  await page.keyboard.press('Space');
  await expect(four).toBeChecked();
  await expect(page.getByRole('status').filter({ hasText: 'All answers saved' })).toBeVisible();

  // Reload: the server copy of the answer comes back.
  await page.reload();
  const start = page.getByRole('button', { name: 'Start Quiz' });
  if (await start.isVisible()) await start.click();
  await expect(
    page.locator('.question-group-1').getByRole('radio', { name: '4', exact: true }),
  ).toBeChecked();

  await page.locator('.question-group-1 label.single-option', { hasText: '9' }).click();
  await page.getByRole('button', { name: 'Next' }).click();
  await expect(pageTwo).toBeVisible();
  await expect(pageTwo.getByRole('heading', { name: 'Part B' })).toBeVisible();
  await pageTwo.locator('label.single-option', { hasText: '3' }).click();
  await expect(page.getByRole('status').filter({ hasText: 'All answers saved' })).toBeVisible();
  expect(await noHorizontalScroll(page)).toBe(true);
  await Promise.all([
    page.waitForNavigation(),
    page.locator('.ohmylms-button.quiz-submit').click(),
  ]);
  const result = JSON.parse(fixture('attempt', state.tag));
  expect(result.attempt.status).not.toBe('pending');
  expect(Number(result.attempt.total)).toBe(3);
  expect(errors).toEqual([]);
});

test('guest answers a lesson check by keyboard and saves it to their account', async ({
  page,
}) => {
  const errors = trackErrors(page);
  await page.goto(state.page_url);
  const check = page.locator('form.ohmylms-inline-check');
  await expect(check).toBeVisible();
  const right = check.getByRole('radio', { name: '4', exact: true });
  await right.focus();
  await page.keyboard.press('Space');
  await check.getByRole('button', { name: 'Check answer' }).focus();
  await page.keyboard.press('Enter');
  await expect(check.getByText('Correct!')).toBeVisible();

  await login(page, user('learner').login, user('learner').password, state.page_url);
  await page.goto(state.page_url);
  await expect(page.getByText(/Save 1 practice answers from this device/)).toBeVisible();
  await page.getByRole('button', { name: 'Save to my account' }).click();
  await expect(page.getByText('Your practice answers were saved to your account.')).toBeVisible();
  expect(errors).toEqual([]);
});

test('learner practises the skill until the session ends', async ({ page }) => {
  const errors = trackErrors(page);
  await login(page, user('learner').login, user('learner').password);
  await page.goto(`/?ohmylms_practice=${state.skill}`);
  await page.getByRole('button', { name: 'Start practice' }).click();
  const finished = /Practice complete|every available practice question/;
  for (let step = 0; step < 6; step++) {
    // Wait for the runner to show either the next question or the end of the session.
    await page.waitForFunction((pattern) => {
      const stage = document.querySelector('.ohmylms-practice-stage');
      return (
        stage &&
        (stage.querySelector('.ohmylms-practice-question') || new RegExp(pattern).test(stage.textContent))
      );
    }, finished.source);
    const form = page.locator('.ohmylms-practice-question');
    if (!(await form.isVisible())) break;
    await form.getByRole('radio').first().check();
    await form.getByRole('button', { name: 'Check answer' }).click();
    const next = page.getByRole('button', { name: 'Next question' });
    await expect(next).toBeFocused();
    await next.click();
  }
  await expect(page.locator('.ohmylms-practice-stage')).toContainText(
    /Practice complete|every available practice question/,
  );
  expect(JSON.parse(fixture('attempt', state.tag)).evidence).toBeGreaterThan(0);
  expect(errors).toEqual([]);
});

test('guardian sees the child skills on a phone and the teacher sees the class matrix', async ({
  page,
}) => {
  const errors = trackErrors(page);
  await page.setViewportSize({ width: 390, height: 844 });
  await login(page, user('guardian').login, user('guardian').password);
  await page.goto('/?ohmylms_portal=1&view=parent-dashboard');
  const skills = page.locator('.ohmylms-child-skills');
  await expect(skills.getByRole('heading', { name: 'Skills' })).toBeVisible();
  await expect(skills.getByRole('cell', { name: /Бутархай/ })).toBeVisible();
  expect(await noHorizontalScroll(page)).toBe(true);

  await page.context().clearCookies();
  await page.setViewportSize({ width: 1280, height: 900 });
  await login(page, user('teacher').login, user('teacher').password);
  await page.goto('/?ohmylms_portal=1&view=teacher-dashboard');
  await page.getByRole('button', { name: 'Open class' }).click();
  const matrix = page.locator('.ohmylms-class-skills');
  await expect(matrix.getByRole('heading', { name: 'Class skills' })).toBeVisible();
  await expect(matrix.getByRole('rowheader', { name: 'Сүхээ Learner' })).toBeVisible();
  expect(errors).toEqual([]);
});
