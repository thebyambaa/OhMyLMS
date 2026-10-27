/**
 * Curriculum editor, Course Bundles manager and the learner dashboard in a real browser.
 * Needs the disposable site: OHMYLMS_TEST_CREDENTIALS (JSON), OHMYLMS_CHROMIUM_PATH (browser),
 * OHMYLMS_PHP and, if the PHP build needs one, OHMYLMS_PHP_INI. Run with --workers=1.
 */
const { test, expect } = require('@playwright/test');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');

const php = process.env.OHMYLMS_PHP || 'php';
const ini = process.env.OHMYLMS_PHP_INI ? ['-c', process.env.OHMYLMS_PHP_INI] : [];
const fixture = (...args) => execFileSync(php, [...ini, '-d', 'display_startup_errors=0', '-d', 'error_reporting=22527', '-d', 'memory_limit=1024M', 'tests/php/curriculum-browser-fixture.php', ...args], { env: process.env, encoding: 'utf8', timeout: 180000 });
const admin = () => JSON.parse(fs.readFileSync(process.env.OHMYLMS_TEST_CREDENTIALS, 'utf8'));
let state;
test.describe.configure({ mode: 'serial' });
test.beforeAll(() => { state = JSON.parse(fixture('setup').trim().split('\n').filter((line) => line.startsWith('{')).pop()); });
test.afterAll(() => { if (state) fixture('cleanup', state.tag); });

async function login(page, user, password) {
  await page.goto('/wp-login.php');
  await page.locator('#user_login').fill(user);
  await page.locator('#user_pass').fill(password);
  await page.locator('#wp-submit').click();
  await page.waitForURL((url) => !url.pathname.endsWith('wp-login.php'));
}
const rowName = (page, text) => page.locator('.ohmylms-cur-name', { hasText: new RegExp('^' + text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '$') });
const names = (page, scope) => page.locator(`${scope} > li > .ohmylms-cur-row .ohmylms-cur-name`).allTextContents();
const watch = (page) => {
  const problems = [];
  page.on('pageerror', (error) => problems.push(`pageerror: ${error.message}`));
  page.on('dialog', (dialog) => { problems.push(`dialog: ${dialog.message()}`); dialog.dismiss(); });
  // The shipped admin vendor bundle logs these two store messages on first load of any OhMyLMS admin
  // page (also on the Dashboard); they predate this feature, so only these exact messages are ignored.
  const known = /favicon|Failed to load resource|Store "core\/(preferences|keyboard-shortcuts)" is already registered/;
  page.on('console', (message) => { if (message.type() === 'error' && !known.test(message.text())) problems.push(`console: ${message.text()}`); });
  return problems;
};
// WordPress core keeps a hidden link-dialog template in the admin footer, so only visible dialogs count.
const noModal = async (page) => expect(await page.locator('[role="dialog"]:visible, .components-modal__frame:visible, .components-modal__screen-overlay:visible').count()).toBe(0);
const notice = (page, text) => expect(page.locator('.ohmylms-cur-status .components-notice').filter({ hasText: text })).toBeVisible();

test('administrator builds, edits, searches, reorders, moves and deletes curriculum items inline', async ({ page }, testInfo) => {
  const problems = watch(page);
  const tag = state.tag;
  await login(page, admin().username, admin().password);
  await page.goto('/wp-admin/admin.php?page=ohmylms#/extensions/curriculum');
  await expect(page.getByRole('heading', { name: 'Curriculum', exact: true })).toBeVisible({ timeout: 30000 });
  const tree = page.locator('ul.ohmylms-cur-tree').first();
  await expect(rowName(page, `Exam board ${tag}`)).toBeVisible();

  // Branches open and close one at a time, to any depth.
  const board = `Exam board ${tag}`;
  await expect(page.getByRole('button', { name: `Expand ${board}` })).toHaveAttribute('aria-expanded', 'false');
  await page.getByRole('button', { name: `Expand ${board}` }).click();
  await expect(page.getByRole('button', { name: `Collapse ${board}` })).toHaveAttribute('aria-expanded', 'true');
  await page.getByRole('button', { name: 'Expand Secondary level' }).click();
  await page.getByRole('button', { name: 'Expand English language', exact: true }).click();
  await expect(rowName(page, 'English language syllabus')).toBeVisible();
  await expect(page.getByRole('button', { name: `Expand Entrance test ${tag}` })).toHaveAttribute('aria-expanded', 'false');

  // Keyboard: a toggle is a real button.
  await page.getByRole('button', { name: `Expand Entrance test ${tag}` }).focus();
  await page.keyboard.press('Enter');
  await expect(rowName(page, 'Reading and writing')).toBeVisible();

  // Add a root item and a child on this same screen.
  const created = `Browser board ${tag}`;
  await page.getByRole('button', { name: 'Add top-level item' }).first().click();
  const form = page.locator('form.ohmylms-cur-add');
  await form.getByLabel('Name of the new top-level item').fill(created);
  await form.getByLabel('Type', { exact: true }).fill('IB programme');
  await form.getByLabel('Name of the new top-level item').press('Enter');
  await notice(page, `Added “${created}”.`);
  await expect(rowName(page, created)).toBeVisible();
  await page.getByRole('button', { name: `Add child to ${created}` }).click();
  await page.getByLabel(`Name of the new item under ${created}`).fill(`Browser child ${tag}`);
  await page.getByLabel(`Name of the new item under ${created}`).press('Enter');
  await notice(page, `Added “Browser child ${tag}”.`);
  await expect(rowName(page, `Browser child ${tag}`)).toBeVisible();
  await page.getByRole('button', { name: 'Done' }).click();
  await noModal(page);

  // Edit inside an expanded panel; the open branches stay open after saving.
  const syllabus = 'English language syllabus';
  await page.getByRole('button', { name: `Edit ${syllabus}`, exact: true }).click();
  const panel = page.getByRole('region', { name: `Editing ${syllabus}` });
  await expect(panel).toBeVisible();
  await panel.getByLabel('Name', { exact: true }).fill('English language syllabus (revised)');
  await panel.getByLabel('Syllabus code (optional)').fill('E-101');
  await panel.getByLabel('Version (optional)').fill('2026');
  await expect(panel.getByText('Unsaved changes')).toBeVisible();
  await panel.getByRole('button', { name: 'Save changes' }).click();
  await notice(page, 'Saved “English language syllabus (revised)”.');
  await expect(panel.getByRole('status').filter({ hasText: 'Saved' })).toBeVisible();
  await expect(page.getByRole('button', { name: `Collapse ${board}` })).toHaveAttribute('aria-expanded', 'true');
  await expect(page.getByRole('button', { name: 'Collapse Secondary level' })).toHaveAttribute('aria-expanded', 'true');
  await expect(page.getByRole('button', { name: 'Collapse English language', exact: true })).toHaveAttribute('aria-expanded', 'true');
  await expect(page.getByText('E-101 · 2026', { exact: true })).toBeVisible();
  // Validation shows next to the field and blocks the save.
  await panel.getByLabel('Name', { exact: true }).fill('');
  await panel.getByRole('button', { name: 'Save changes' }).click();
  await expect(panel.getByText('Enter a name.')).toBeVisible();
  await panel.getByLabel('Name', { exact: true }).fill('English language syllabus (revised)');
  await page.getByRole('button', { name: `Close editor for English language syllabus (revised)` }).click();

  // Reorder siblings; keyboard focus stays on the control that was used.
  const order = async () => (await names(page, 'ul.ohmylms-cur-tree')).filter((name) => name.includes(tag));
  expect(await order()).toEqual([board, `Entrance test ${tag}`, created]);
  await page.getByRole('button', { name: `Move Entrance test ${tag} up` }).click();
  await notice(page, `Moved “Entrance test ${tag}” up.`);
  expect(await order()).toEqual([`Entrance test ${tag}`, board, created]);
  expect(await page.evaluate(() => document.activeElement && document.activeElement.id)).toMatch(/^ohmylms-cur-(up|down)-\d+$/);
  await page.getByRole('button', { name: `Move Entrance test ${tag} down` }).click();
  await notice(page, `Moved “Entrance test ${tag}” down.`);
  expect(await order()).toEqual([board, `Entrance test ${tag}`, created]);

  // Move between parents. Invalid parents are not offered, and the server refuses a circular move too.
  await page.getByRole('button', { name: `Edit ${board}`, exact: true }).click();
  const boardPanel = page.getByRole('region', { name: `Editing ${board}` });
  const targets = await boardPanel.getByLabel('Move to').locator('option').allTextContents();
  expect(targets.join('|')).not.toMatch(/Secondary level|English language|Exam board/);
  const descendant = await page.evaluate(async (id) => (await wp.apiFetch({ path: `/ohmylms/v1/curriculum/tree` })).items.find((item) => item.name === 'English language').id, state.subject);
  const refused = await page.evaluate(async ({ root, under }) => { try { await wp.apiFetch({ path: `/ohmylms/v1/curriculum/items/${root}/move`, method: 'POST', data: { parent_id: under } }); return 'accepted'; } catch (error) { return error.code; } }, { root: state.root_a, under: descendant });
  expect(refused).toBe('ohmylms_curriculum_cycle');
  await page.getByRole('button', { name: `Close editor for ${board}` }).click();
  await page.getByRole('button', { name: 'Edit Reading and writing', exact: true }).click();
  const sectionPanel = page.getByRole('region', { name: 'Editing Reading and writing' });
  expect(await sectionPanel.getByLabel('Move to').locator('option').allTextContents()).not.toContain('Reading and writing');
  await sectionPanel.getByLabel('Move to').selectOption({ label: '— Secondary level' });
  await sectionPanel.getByRole('button', { name: 'Move', exact: true }).click();
  await notice(page, 'Moved “Reading and writing”.');
  await expect(page.locator('li', { has: page.getByRole('button', { name: 'Edit Secondary level', exact: true }) }).first().getByText('Reading and writing', { exact: true }).first()).toBeVisible();

  // Link content and map a skill, still in the same panel.
  await expect(sectionPanel.getByRole('heading', { name: 'Linked content' })).toBeVisible();
  await sectionPanel.locator('summary', { hasText: /^Skills/ }).click();
  await expect(sectionPanel.locator('.ohmylms-cur-link-list li > span:first-child', { hasText: `Reading inference ${tag} (RD-1)` })).toBeVisible();
  await expect(sectionPanel.getByRole('heading', { name: 'Shared skills' })).toBeVisible();
  const mapping = sectionPanel.locator('details.ohmylms-cur-mapping', { has: page.locator('summary', { hasText: `Reading inference ${tag} (RD-1)` }) });
  await mapping.locator('summary').first().click();
  await mapping.getByLabel(`Find a shared skill for Reading inference ${tag}`).fill('Vocabulary');
  await mapping.getByRole('button', { name: `Choose Vocabulary in context ${tag} as the shared skill` }).click();
  await mapping.getByLabel('How do the requirements, difficulty or syllabus version differ? (optional)').fill('Longer passages');
  await mapping.getByRole('button', { name: 'Save mapping' }).click();
  await notice(page, `Mapped Reading inference ${tag} to Vocabulary in context ${tag}.`);
  await expect(mapping.getByText(/Maps to shared skill “Vocabulary in context .*” — Equivalent\./)).toBeVisible();
  await expect(mapping.getByText('Differences: Longer passages')).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('curriculum-panel.png'), fullPage: true });
  await page.getByRole('button', { name: 'Close editor for Reading and writing' }).click();

  // Search reveals matches inside their ancestors without losing the saved open/closed choices.
  await page.getByRole('button', { name: 'Collapse all' }).click();
  await expect(rowName(page, 'English language syllabus (revised)')).toHaveCount(0);
  await page.getByLabel('Search curriculum').fill('E-101');
  await expect(page.getByText('1 matching item, shown inside its branch.')).toBeVisible();
  await expect(rowName(page, 'English language syllabus (revised)')).toBeVisible();
  for (const ancestor of [board, 'Secondary level', 'English language']) await expect(page.locator('.ohmylms-cur-name', { hasText: new RegExp(`^${ancestor}$`) })).toBeVisible();
  await expect(rowName(page, `Entrance test ${tag}`)).toHaveCount(0);
  await expect(page.getByRole('button', { name: /Move .* up/ }).first()).toBeDisabled();
  await page.getByLabel('Search curriculum').fill('no such thing');
  await expect(page.getByText('No matching items.')).toBeVisible();
  await page.getByRole('button', { name: 'Clear search' }).click();
  await expect(rowName(page, 'English language syllabus (revised)')).toHaveCount(0);
  await page.getByLabel('Search curriculum').fill('мөнгөн');
  await expect(page.getByText('No matching items.')).toBeVisible();
  await page.getByRole('button', { name: 'Clear search' }).click();

  // Safe deletion: children need a choice, links are described, linked content survives.
  await page.getByRole('button', { name: 'Expand all' }).click();
  await page.getByRole('button', { name: `Edit ${created}`, exact: true }).click();
  const createdPanel = page.getByRole('region', { name: `Editing ${created}` });
  await createdPanel.getByRole('button', { name: `Delete ${created}…` }).click();
  const confirm = createdPanel.getByRole('group', { name: `Confirm deleting ${created}` });
  await expect(confirm.getByText('This item has 1 child.')).toBeVisible();
  await expect(confirm.getByLabel('Move its child up one level and delete only this item')).toBeChecked();
  await confirm.getByRole('button', { name: 'Cancel' }).click();
  await expect(rowName(page, created)).toBeVisible();
  await createdPanel.getByRole('button', { name: `Delete ${created}…` }).click();
  await confirm.getByRole('button', { name: 'Delete item' }).click();
  await notice(page, `Deleted “${created}” and 0 items under it. 1 child moved up.`);
  await expect(rowName(page, created)).toHaveCount(0);
  await expect(rowName(page, `Browser child ${tag}`)).toBeVisible();
  await page.getByRole('button', { name: `Edit English language syllabus (revised)`, exact: true }).click();
  const linked = page.getByRole('region', { name: 'Editing English language syllabus (revised)' });
  await linked.getByRole('button', { name: 'Delete English language syllabus (revised)…' }).click();
  // A syllabus is also a course, so it is linked to its own course as well as to the one the test placed.
  await expect(linked.getByText(/2 links to courses, skills, question banks or exams will be removed\. The content itself is not deleted\./)).toBeVisible();
  await linked.getByRole('button', { name: 'Delete item' }).click();
  await notice(page, 'Deleted “English language syllabus (revised)”');
  const course = await page.evaluate(async (search) => (await wp.apiFetch({ path: `/ohmylms/v1/curriculum/link-targets?type=course&search=${encodeURIComponent(search)}` })).length, `English warm-up ${tag}`);
  expect(course).toBe(1);

  await noModal(page);
  await page.screenshot({ path: testInfo.outputPath('curriculum-desktop.png'), fullPage: true });
  // Narrow screens: the tree reflows instead of scrolling sideways.
  await page.setViewportSize({ width: 390, height: 844 });
  const overflow = await page.evaluate(() => {
    const limit = document.documentElement.clientWidth;
    const wide = [...document.querySelectorAll('.ohmylms-curriculum *')].filter((el) => { const box = el.getBoundingClientRect(); return box.width > 0 && box.right > limit + 1; }).map((el) => `${el.tagName}.${String(el.className).slice(0, 40)}`);
    return { page: document.documentElement.scrollWidth <= window.innerWidth, wide };
  });
  expect(overflow).toEqual({ page: true, wide: [] });
  await page.screenshot({ path: testInfo.outputPath('curriculum-390.png'), fullPage: true });
  expect(problems).toEqual([]);
});

test('administrator creates, fills and publishes a Course Bundle inline', async ({ page }, testInfo) => {
  const problems = watch(page);
  const tag = state.tag;
  await login(page, admin().username, admin().password);
  await page.goto('/wp-admin/admin.php?page=ohmylms#/extensions/bundles');
  await expect(page.getByRole('heading', { name: 'Course Bundles', exact: true })).toBeVisible({ timeout: 30000 });
  await expect(page.getByText(`English exam preparation ${tag}`, { exact: true })).toBeVisible();
  await expect(page.locator('li', { hasText: `English exam preparation ${tag}` }).getByText('3 members')).toBeVisible();

  const title = `Browser bundle ${tag}`;
  await page.getByRole('button', { name: 'New bundle' }).click();
  await page.getByLabel('Name of the new bundle').fill(title);
  await page.getByLabel('Description (optional)').fill('Chosen by hand');
  await page.getByRole('button', { name: 'Create bundle' }).click();
  await expect(page.locator('.ohmylms-cur-status .components-notice').filter({ hasText: `Created “${title}”` })).toBeVisible();
  const panel = page.getByRole('region', { name: `Editing ${title}` });
  await expect(panel).toBeVisible();
  // A bundle cannot be published until it has members, and says why.
  await expect(panel.getByRole('button', { name: 'Publish', exact: true })).toBeDisabled();
  await expect(panel.getByText('Add at least one course or curriculum item before publishing.')).toBeVisible();

  await panel.getByLabel('Find a course').fill('English warm-up');
  await panel.getByRole('button', { name: `Add English warm-up ${tag} to the bundle` }).click();
  await panel.getByLabel('Add from').selectOption('curriculum');
  await panel.getByLabel('Find a curriculum item').fill('Entrance test');
  await panel.getByRole('button', { name: `Add Entrance test ${tag} to the bundle` }).click();
  await panel.getByLabel('Find a curriculum item').fill('Entrance test');
  await expect(panel.getByRole('button', { name: `Add Entrance test ${tag} to the bundle` })).toHaveCount(0);
  await expect(panel.getByText('Unsaved changes')).toBeVisible();
  await expect(panel.getByRole('button', { name: 'Publish', exact: true })).toBeDisabled();
  await expect(panel.getByText('Save your changes before publishing.')).toBeVisible();
  await panel.getByRole('button', { name: `Move Entrance test ${tag} up` }).click();
  expect(await panel.locator('.ohmylms-bundle-member-name strong').allTextContents()).toEqual([`Entrance test ${tag}`, `English warm-up ${tag}`]);
  await panel.getByRole('button', { name: 'Save bundle' }).click();
  await expect(page.locator('.ohmylms-cur-status .components-notice').filter({ hasText: `Saved “${title}”.` })).toBeVisible();
  await panel.getByRole('button', { name: 'Publish', exact: true }).click();
  await expect(panel.getByText(/Learners can find this bundle/)).toBeVisible();
  // Order survives a reload; a member is never added automatically.
  await page.reload();
  await page.getByRole('button', { name: `Edit ${title}` }).click();
  const reopened = page.getByRole('region', { name: `Editing ${title}` });
  await expect(reopened.locator('.ohmylms-bundle-member-name strong').first()).toHaveText(`Entrance test ${tag}`);
  expect(await reopened.locator('.ohmylms-bundle-member-name strong').count()).toBe(2);
  await reopened.getByRole('button', { name: `Delete ${title}…` }).click();
  await expect(reopened.getByText('No learners follow it.')).toBeVisible();
  await reopened.getByRole('button', { name: 'Cancel' }).click();
  await page.screenshot({ path: testInfo.outputPath('bundles-desktop.png'), fullPage: true });
  await noModal(page);
  expect(problems).toEqual([]);
});

test('learner finds, adds and removes a bundle; following does not enrol or grant access', async ({ page }, testInfo) => {
  const problems = watch(page);
  const tag = state.tag;
  const bundle = `English exam preparation ${tag}`;
  await login(page, state.login, state.password);
  // On the real student dashboard the section sits beneath the standard statistics, exactly once.
  await page.goto(state.dashboard_url);
  await expect(page.getByText('Enrolled courses', { exact: true })).toBeVisible();
  await expect(page.locator('#ohmylms-bundles')).toHaveCount(1);
  await expect(page.locator('#ohmylms-bundles').getByRole('heading', { name: 'My course bundles' })).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('learner-dashboard-page.png'), fullPage: true });
  await page.goto(state.page_url);
  const section = page.locator('#ohmylms-bundles');
  await expect(section.getByRole('heading', { name: 'My course bundles' })).toBeVisible();
  await expect(section.getByText('does not enrol you', { exact: false })).toBeVisible();
  const suggestion = section.locator('.ohmylms-bundles-suggested li', { hasText: bundle });
  await expect(suggestion.getByText('Includes 1 course you are enrolled in.')).toBeVisible();
  // The learner cannot reach administration.
  const denied = await page.evaluate(async () => { const c = JSON.parse(document.getElementById('wp-script-module-data-@wordpress/interactivity').textContent).state['ohmylms/bundles']; const get = (path) => fetch(c.root + path, { credentials: 'same-origin', headers: { 'X-WP-Nonce': c.nonce } }).then((r) => r.status); return [await get('curriculum/tree'), await get('bundles'), await get('skill-mappings')]; });
  expect(denied).toEqual([403, 403, 403]);

  // Add it: keyboard operable, announced, focus returns to the section.
  const add = section.getByRole('button', { name: `Add ${bundle} to my dashboard` });
  await add.focus();
  await page.keyboard.press('Enter');
  const followed = section.getByRole('article', { name: bundle });
  await expect(followed).toBeVisible();
  expect(await page.evaluate(() => document.activeElement && document.activeElement.id)).toBe('ohmylms-bundles');
  await expect(followed.getByText(`English warm-up ${tag}`)).toBeVisible();
  const warmup = followed.locator('.ohmylms-bundle-course', { hasText: `English warm-up ${tag}` });
  await expect(warmup.getByText('Enrolled', { exact: true })).toBeVisible();
  await expect(warmup.getByText('Traditional', { exact: true })).toBeVisible();
  await expect(warmup.getByLabel('Activities completed: 1 of 3')).toBeVisible();
  const other = followed.locator('.ohmylms-bundle-course', { hasText: `Test prep ${tag}` });
  await expect(other.getByText('Not enrolled', { exact: true })).toBeVisible();
  await expect(other.getByText(/You are not enrolled in this course/)).toBeVisible();
  await expect(other.locator('progress')).toHaveCount(0);
  // Syllabus-level view with skills: unassessed skills say so, and no percentage is invented.
  const syllabus = followed.locator('.ohmylms-bundle-syllabus', { hasText: 'Reading and writing' });
  await expect(syllabus.getByText('2 skills: 0 strengths · 0 need practice · 1 in progress · 1 not assessed')).toBeVisible();
  const row = (name) => followed.locator('.ohmylms-bundle-skill-table tbody tr', { hasText: name });
  await expect(row(`Vocabulary in context ${tag}`).getByText('Not assessed').first()).toBeVisible();
  await expect(row(`Reading inference ${tag}`).getByText('Developing').first()).toBeVisible();
  await expect(row(`Vocabulary in context ${tag}`).getByText('None available yet')).toBeVisible();
  expect(await followed.innerText()).not.toMatch(/\d\s?%/);
  await expect(followed.getByText('Course completed')).toHaveCount(0);
  // Following changed neither enrollment nor access.
  const access = await page.evaluate(async ({ other, enrolled }) => { const c = JSON.parse(document.getElementById('wp-script-module-data-@wordpress/interactivity').textContent).state['ohmylms/bundles']; const get = (id) => fetch(`${c.root}courses/${id}/learning/progress`, { credentials: 'same-origin', headers: { 'X-WP-Nonce': c.nonce } }).then((r) => r.status); return { other: await get(other), enrolled: await get(enrolled) }; }, { other: state.other_course, enrolled: state.enrolled_course });
  expect(access.other).toBe(403);
  expect(access.enrolled).not.toBe(403);
  await page.screenshot({ path: testInfo.outputPath('learner-desktop.png'), fullPage: true });
  for (const width of [390, 320]) {
    await page.setViewportSize({ width, height: 844 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({ path: testInfo.outputPath(`learner-${width}.png`), fullPage: true });
  }
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.reload();
  await expect(page.locator('#ohmylms-bundles').getByRole('article', { name: bundle })).toBeVisible();
  // Remove it again: back to suggestions, enrollment untouched.
  await page.locator('#ohmylms-bundles').getByRole('button', { name: `Remove ${bundle} from my dashboard` }).click();
  await expect(page.locator('#ohmylms-bundles').getByRole('article', { name: bundle })).toHaveCount(0);
  await expect(page.locator('#ohmylms-bundles .ohmylms-bundles-suggested li', { hasText: bundle })).toBeVisible();
  const still = await page.evaluate(async (enrolled) => { const c = JSON.parse(document.getElementById('wp-script-module-data-@wordpress/interactivity').textContent).state['ohmylms/bundles']; return fetch(`${c.root}courses/${enrolled}/learning/progress`, { credentials: 'same-origin', headers: { 'X-WP-Nonce': c.nonce } }).then((r) => r.status); }, state.enrolled_course);
  expect(still).not.toBe(403);
  expect(problems).toEqual([]);
});
