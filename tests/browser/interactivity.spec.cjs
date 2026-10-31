const { test, expect } = require('@playwright/test');
const fs = require('node:fs');
const path = require('node:path');

// SmartScore is the separate ohmylms-smartscore add-on; the fixture renders its practice template when it is installed.
const smartScore = path.resolve(process.env.OHMYLMS_SMARTSCORE_DIR || path.join(__dirname, '../../../ohmylms-smartscore'));
const smartScoreMissing = !fs.existsSync(path.join(smartScore, 'templates/practice.php'));
const smartScoreReason = `Needs the ohmylms-smartscore add-on (templates/practice.php) at ${smartScore}; set OHMYLMS_SMARTSCORE_DIR.`;

test('public question registry supports validators, duplicate guards, unregister and mount cleanup', async ({ page }) => {
  await page.goto('/fixture?types=custom-type');
  await page.locator('.question-1 input[type=text]').fill('blocked');
  await page.evaluate(async () => {
    const sdk = await import('ohmylms/interactivity');
    window.unregisterValidator = sdk.registerAnswerValidator('custom-type', root => root.querySelector('input[type=text]').value === 'approved');
    let duplicateRejected = false;
    try { sdk.registerAnswerValidator('custom-type', () => true); } catch { duplicateRejected = true; }
    if (!duplicateRejected) throw new Error('Duplicate validator accepted');
    let disposed = false;
    const unregister = sdk.registerQuestionMount('addon-question', () => () => { disposed = true; });
    sdk.mountQuestion(document.body, 'addon-question', { questionId: 1 })();
    if (!disposed) throw new Error('Mount cleanup not returned');
    unregister();
    if (sdk.mountQuestion(document.body, 'addon-question', {}) !== null) throw new Error('Mount unregister failed');
  });
  await page.getByRole('button', { name: 'Submit', exact: true }).click();
  await expect(page.locator('.required-question')).toBeVisible();
  await page.evaluate(() => window.unregisterValidator());
  await page.getByRole('button', { name: 'Submit', exact: true }).click();
  await expect(page.locator('body')).toContainText('Fixture submission received');
});

test('real quiz template: required answers, navigation, custom type, native POST and extension event', async ({ page }) => {
  const errors = []; page.on('pageerror', error => errors.push(error.message));
  await page.goto('/fixture?types=single-choice,custom-type');
  // The step button reads Skip until the question has an answer; a required question still blocks it.
  await page.getByRole('button', { name: 'Skip', exact: true }).click();
  await expect(page.locator('.question-1 .required-question')).toBeVisible();
  await expect(page.locator('.question-2')).toBeHidden();
  await page.locator('.question-1 input[type=radio]').first().check();
  await page.getByRole('button', { name: 'Next', exact: true }).click();
  await expect(page.locator('.question-2')).toBeVisible();
  await page.getByRole('button', { name: 'Submit', exact: true }).click();
  await expect(page.locator('.question-2 .required-question')).toBeVisible();
  await page.locator('.question-2 input[type=text]').fill('custom');
  await page.evaluate(() => document.addEventListener('ohmylms:quiz-before-submit', event => event.preventDefault(), { once: true }));
  await page.getByRole('button', { name: 'Submit', exact: true }).click();
  await expect(page.locator('.question-2')).toBeVisible();
  await page.getByRole('button', { name: 'Previous', exact: true }).click();
  await expect(page.locator('.question-1 input[type=radio]').first()).toBeChecked();
  await page.getByRole('button', { name: 'Next', exact: true }).click();
  await page.getByRole('button', { name: 'Submit', exact: true }).click();
  await expect(page.locator('body')).toContainText('Fixture submission received');
  expect(errors).toEqual([]);
});

test('grouped and all-question layouts require every text field and preserve character limits', async ({ page }) => {
  for (const layout of ['number_of_questions_per_page', 'all_questions_in_one_page']) {
    await page.goto('/fixture?layout=' + layout);
    await page.locator('.question-1 input[type=radio]').first().check();
    const text = page.locator('.question-2 input[type=text]');
    await text.first().fill('abcdefgh'); await expect(text.first()).toHaveValue('abcde');
    const button = page.getByRole('button', { name: layout === 'all_questions_in_one_page' ? 'Submit' : 'Next', exact: true });
    await button.click(); await expect(page.locator('.question-2 .required-question')).toBeVisible();
    await text.last().fill('hello');
    if (layout !== 'all_questions_in_one_page') { await button.click(); await expect(page.locator('.question-3')).toBeVisible(); }
    await page.getByRole('button', { name: 'Submit', exact: true }).click();
    await expect(page.locator('.question-3 .required-question')).toBeVisible();
  }
});

test('timeout sends answer payload once and permits retry on failure', async ({ page }) => {
  let calls = 0;
  await page.route('**/expiry', async route => { calls++; expect(route.request().postData()).toContain('action=ohmylms_quiz_exit_submission'); await route.fulfill({ status: 500, json: { success: false } }); });
  await page.goto('/fixture?timer=0.02');
  await expect(page.locator('.ohmylms-quiz-timeup-text')).toContainText('Submission failed');
  await expect(page.getByRole('button', { name: 'Submit', exact: true })).toBeEnabled();
  expect(calls).toBe(1);
});

test('matching and reorder support keyboard input without crossing question instances', async ({ page }) => {
  const errors = []; page.on('pageerror', error => errors.push(error.message));
  await page.goto('/fixture?layout=all_questions_in_one_page&types=reorder,matching,matching');
  const reorder = page.locator('.quiz-reorder-options');
  const first = await reorder.locator('input').first().inputValue();
  await reorder.locator('.reorder-option').first().press('ArrowDown');
  await expect(reorder.locator('input').last()).toHaveValue(first);
  const matching = page.locator('.quiz-matching-options').first();
  const option = matching.locator('.matching-option').first();
  const id = await option.getAttribute('data-option-id');
  await option.press('Enter'); await matching.locator('.option-drop-box').first().press('Enter');
  await expect(matching.locator('.matching-answer-input').first()).toHaveValue(id);
  await expect(page.locator('.quiz-matching-options').last().locator('.matching-answer-input').first()).toHaveValue('');
  await matching.locator('.option-drop-box').first().press('Delete');
  await expect(matching.locator('.matching-answer-input').first()).toHaveValue('');
  expect(errors).toEqual([]);
});

test('SmartScore mounts the shared matching renderer for fetched question HTML', async ({ page, request }) => {
  test.skip(smartScoreMissing, smartScoreReason);
  const response = await request.get('/fixture?layout=all_questions_in_one_page&types=matching');
  const html = await response.text();
  const questionHtml = await page.evaluate(html => new DOMParser().parseFromString(html, 'text/html').querySelector('.quiz-matching-options').outerHTML, html);
  // Extract the actual renderer with DOM parsing rather than duplicating its markup.
  await page.route('**/api/practice/*/next', async route => {
    await route.fulfill({ json: { token: 'matching-token', questionType: 'matching', questionId: 101, state: { score: 0 }, html: questionHtml || html } });
  });
  await page.goto('/fixture?view=practice');
  const root = page.locator('.ohmylms-ss-question');
  const option = root.locator('.matching-option').first();
  const id = await option.getAttribute('data-option-id');
  await option.click(); await root.locator('.option-drop-box').first().click();
  await expect(root.locator('.matching-answer-input').first()).toHaveValue(id);
});

test('disclosure keyboard controls and queued reward presentation use the stores', async ({ page }) => {
  await page.goto('/fixture');
  const toggle = page.getByRole('button', { name: 'Disclosure' });
  await toggle.press('Enter'); await expect(toggle).toHaveAttribute('aria-expanded', 'true');
  await toggle.press('Space'); await expect(toggle).toHaveAttribute('aria-expanded', 'false');
  await page.evaluate(() => window.OhMyLMSCelebrate.show('Earned 10 points'));
  await expect(page.getByRole('status')).toContainText('Earned 10 points');
  await page.getByRole('button', { name: 'Dismiss notification' }).click();
  await expect(page.getByRole('status')).toBeHidden();
});

test('SmartScore isolates two instances, shows feedback and recovers expired tokens', async ({ page }) => {
  test.skip(smartScoreMissing, smartScoreReason);
  const errors = []; page.on('pageerror', error => errors.push(error.message));
  const requests = {}; let answers = 0;
  await page.route('**/api/practice/*/next', async route => {
    const quiz = Number(route.request().url().match(/practice\/(\d+)/)[1]);
    requests[quiz] = (requests[quiz] || 0) + 1;
    await route.fulfill({ json: { token: 'ticket-' + quiz, questionId: 101, questionType: 'custom-type', state: { score: 5, zone: 'learning', code: 'A' }, html: '<input name="attempt[0][quiz_question][101]" value="" aria-label="Answer">' } });
  });
  await page.route('**/api/practice/*/answer', async route => {
    answers++;
    if (answers === 1) { await route.fulfill({ status: 409, json: { code: 'smartscore_expired', message: 'Expired' } }); return; }
    await route.fulfill({ json: { correct: false, delta: -1, state: { score: 4, zone: 'learning' }, correctAnswer: ['custom'], explanation: '<p>Explanation text</p>' } });
  });
  await page.goto('/fixture?view=practice&multiple=1');
  const first = page.locator('.ohmylms-ss').first(), second = page.locator('.ohmylms-ss').last();
  await expect(first.getByRole('button', { name: 'Submit', exact: true })).toBeEnabled();
  await first.getByLabel('Answer').fill('wrong'); await first.getByRole('button', { name: 'Submit', exact: true }).click();
  await expect.poll(() => requests[10]).toBe(2);
  await first.getByLabel('Answer').fill('wrong'); await first.getByRole('button', { name: 'Submit', exact: true }).click();
  await expect(first).toContainText('Incorrect'); await expect(first).toContainText('Explanation text');
  await expect(first.getByLabel('Answer')).toBeDisabled();
  await expect(second.getByLabel('Answer')).toBeEnabled();
  await first.getByRole('button', { name: 'Got it' }).click();
  await expect(first.getByLabel('Answer')).toBeEnabled();
  expect(errors).toEqual([]);
});

test('SmartScore blocks duplicate requests and presents a mastery milestone', async ({ page }) => {
  test.skip(smartScoreMissing, smartScoreReason);
  let answers = 0;
  await page.route('**/api/practice/*/next', route => route.fulfill({ json: {
    token: 'mastery-token', questionId: 101, questionType: 'custom-type', state: { score: 95 },
    html: '<input name="attempt[0][quiz_question][101]" aria-label="Answer">',
  } }));
  await page.route('**/api/practice/*/answer', async route => {
    answers++;
    await route.fulfill({ json: { questionId: 101, correct: true, delta: 5, completed: true,
      crossed: [100], state: { score: 100, zone: 'mastered' } } });
  });
  await page.goto('/fixture?view=practice');
  await page.getByLabel('Answer').fill('custom');
  await page.evaluate(() => {
    const form = document.querySelector('.ohmylms-ss form');
    form.requestSubmit(); form.requestSubmit();
  });
  await expect(page.locator('.ohmylms-ss-feedback')).toContainText('Mastered');
  await expect(page.getByRole('button', { name: 'Keep practising' })).toBeVisible();
  expect(answers).toBe(1);
});
