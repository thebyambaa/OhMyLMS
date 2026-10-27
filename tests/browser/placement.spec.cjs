/**
 * Curriculum and Course Bundles in place of categories and tags, in a real browser: the admin menu and
 * old routes, the course editor, course list filter, membership plan editor, public filters and old URLs.
 * Needs the disposable site: OHMYLMS_TEST_CREDENTIALS (JSON), OHMYLMS_CHROMIUM_PATH (browser),
 * OHMYLMS_PHP and, if the PHP build needs one, OHMYLMS_PHP_INI. Run with --workers=1.
 */
const { test, expect } = require('@playwright/test');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');

const php = process.env.OHMYLMS_PHP || 'php';
const ini = process.env.OHMYLMS_PHP_INI ? ['-c', process.env.OHMYLMS_PHP_INI] : [];
const fixture = (...args) => execFileSync(php, [...ini, '-d', 'display_startup_errors=0', '-d', 'error_reporting=22527', '-d', 'memory_limit=1024M', 'tests/php/placement-browser-fixture.php', ...args], { env: process.env, encoding: 'utf8', timeout: 180000 });
const lastJson = (output) => JSON.parse(output.trim().split('\n').filter((line) => line.startsWith('{')).pop());
const admin = () => JSON.parse(fs.readFileSync(process.env.OHMYLMS_TEST_CREDENTIALS, 'utf8'));
let state;
const server = () => lastJson(fixture('state', state.tag, JSON.stringify(state)));
test.describe.configure({ mode: 'serial' });
test.beforeAll(() => { state = lastJson(fixture('setup')); });
test.afterAll(() => { if (state) fixture('cleanup', state.tag, JSON.stringify(state)); });

async function login(page, user, password) {
  await page.goto('/wp-login.php');
  await page.locator('#user_login').fill(user);
  await page.locator('#user_pass').fill(password);
  await page.locator('#wp-submit').click();
  await page.waitForURL((url) => !url.pathname.endsWith('wp-login.php'));
}
const watch = (page) => {
  const problems = [];
  page.on('pageerror', (error) => problems.push(`pageerror: ${error.message}`));
  // The shipped admin vendor bundle logs these store messages on first load of any OhMyLMS admin page,
  // and a missing optional asset shows up as a 404; they predate this feature.
  const known = /favicon|Failed to load resource|Store "core\/(preferences|keyboard-shortcuts)" is already registered/;
  page.on('console', (message) => { if (message.type() === 'error' && !known.test(message.text())) problems.push(`console: ${message.text()}`); });
  return problems;
};
const app = (hash) => `/wp-admin/admin.php?page=ohmylms#${hash}`;
// wp.apiFetch sends PUT as POST with an override header, so any write to the route counts.
const saved = (page, route) => page.waitForResponse((response) => decodeURIComponent(response.url()).includes(route) && ['POST', 'PUT'].includes(response.request().method()) && response.ok());

test('Curriculum and Course Bundles are Content Hub tabs, and the old category, tag and extension addresses open them', async ({ page }) => {
  const problems = watch(page);
  await login(page, admin().username, admin().password);
  await page.goto(app('/courses'));
  // The first submenu entry repeats the top-level link and is hidden, so only visible entries count.
  const submenu = page.locator('#adminmenu li.wp-has-current-submenu .wp-submenu a:visible');
  // The old #/courses address opens the hub's Courses tab, and the hub entry stays highlighted.
  await expect(submenu.filter({ hasText: /^Content Hub$/ })).toBeVisible({ timeout: 30000 });
  const labels = (await submenu.allTextContents()).map((text) => text.trim());
  // Content Hub holds Courses, Lessons, Assessments, Skills, Curriculum and Course Bundles, so none has its own entry.
  expect(labels.slice(0, 2)).toEqual(['Content Hub', 'Certificates']);
  for (const retired of ['Courses', 'Assessments', 'Skills', 'Curriculum', 'Course Bundles', 'Categories', 'Tags']) expect(labels).not.toContain(retired);
  const tabs = page.getByRole('navigation', { name: 'Content Hub sections' });
  await expect(tabs.getByRole('link')).toHaveText(['Courses', 'Lessons', 'Quizzes', 'Question Bank', 'Assignments', 'Skills', 'Curriculum', 'Course Bundles']);
  const tabAt = async (hash, tab, heading) => {
    await page.goto(app(hash));
    await expect(tabs.locator('a[aria-current="page"]')).toHaveText(tab, { timeout: 30000 });
    await expect(page.getByRole('heading', { name: heading, exact: true })).toBeVisible({ timeout: 30000 });
    // The hub owns the page title, so the tab body has no second level-one heading.
    await expect(page.getByRole('heading', { level: 1 })).toHaveCount(1);
    await expect(submenu.filter({ hasText: /^Content Hub$/ })).toBeVisible();
  };
  await tabAt('/content-hub/curriculum', 'Curriculum', 'Curriculum');
  await tabAt('/content-hub/bundles', 'Course Bundles', 'Course Bundles');
  // Every older address keeps working and lands on the same tab.
  await tabAt('/extensions/curriculum', 'Curriculum', 'Curriculum');
  await tabAt('/extensions/bundles', 'Course Bundles', 'Course Bundles');
  await tabAt('/categories', 'Curriculum', 'Curriculum');
  await tabAt('/tags', 'Course Bundles', 'Course Bundles');
  // The hub address itself shows the Courses tab; there is no Catalog tab, and its old addresses lead on.
  await page.goto(app('/content-hub'));
  await expect(tabs.locator('a[aria-current="page"]')).toHaveText('Courses', { timeout: 30000 });
  await expect(submenu.filter({ hasText: /^Content Hub$/ })).toBeVisible();
  await page.goto(app('/content-hub/catalog'));
  await expect(page).toHaveURL(/#\/content-hub\/courses$/, { timeout: 30000 });
  await expect(tabs.locator('a[aria-current="page"]')).toHaveText('Courses');
  await page.goto(app(`/content-hub/catalog/${state.plain}`));
  await expect(page).toHaveURL(new RegExp(`#/course-edit/${state.plain}$`), { timeout: 30000 });
  // The tabs link to each other from inside the hub.
  await page.goto(app('/content-hub'));
  await tabs.getByRole('link', { name: 'Curriculum', exact: true }).click();
  await expect(page).toHaveURL(/#\/content-hub\/curriculum$/);
  expect(problems).toEqual([]);
});

test('the course editor places a course in the curriculum and course bundles, saving as you go', async ({ page }) => {
  const problems = watch(page);
  const tag = state.tag;
  await login(page, admin().username, admin().password);
  await page.goto(app(`/course-edit/${state.plain}/settings/organize`));
  const items = page.getByRole('group', { name: 'Curriculum items' });
  const bundles = page.getByRole('group', { name: 'Course bundles' });
  await expect(items.getByLabel(`Arts ${tag}`)).toBeVisible({ timeout: 30000 });
  await expect(page.getByText('Organize your courses in categories')).toHaveCount(0);
  await expect(bundles.getByLabel(`Hidden path ${tag} (draft)`)).toBeVisible();
  await expect(items.getByLabel(`Arts ${tag}`)).not.toBeChecked();

  // Searching keeps the branch above a match, so a result is never shown out of context.
  await page.getByLabel('Find a curriculum item').fill('phys');
  await expect(items.getByLabel(`Physics ${tag}`)).toBeVisible();
  await expect(items.getByLabel(`Science ${tag}`)).toBeVisible();
  await expect(items.getByLabel(`Chemistry ${tag}`)).toHaveCount(0);
  await expect(items.getByLabel(`Arts ${tag}`)).toHaveCount(0);
  await page.getByLabel('Find a curriculum item').fill('zzzz');
  await expect(page.getByText('No curriculum items match your search.')).toBeVisible();
  await page.getByLabel('Find a curriculum item').fill('');

  const put = saved(page, 'organization');
  await items.getByLabel(`Arts ${tag}`).check();
  await put;
  expect(server().items.plain).toEqual([state.arts]);
  await page.reload();
  await expect(items.getByLabel(`Arts ${tag}`)).toBeChecked({ timeout: 30000 });

  const bundle = saved(page, 'organization');
  await bundles.getByLabel(`Data career ${tag}`).check();
  await bundle;
  expect(server().bundles.plain).toEqual([state.bundle]);

  const untrack = saved(page, 'organization');
  await bundles.getByLabel(`Data career ${tag}`).uncheck();
  await untrack;
  const unplace = saved(page, 'organization');
  await items.getByLabel(`Arts ${tag}`).uncheck();
  await unplace;
  expect(server().items.plain).toEqual([]);
  expect(server().bundles.plain).toEqual([]);
  expect(problems).toEqual([]);
});

test('the course list filters by curriculum item, including everything below it', async ({ page }) => {
  const problems = watch(page);
  const tag = state.tag;
  await login(page, admin().username, admin().password);
  await page.goto(app('/courses'));
  const control = page.locator('div[class*="-control"]', { hasText: 'All Curriculum' }).first();
  await expect(control).toBeVisible({ timeout: 30000 });
  await expect(page.getByText(`Painting ${tag}`)).toBeVisible();
  await control.click({ force: true });
  await expect(page.getByRole('option', { name: `Arts ${tag}` })).toBeVisible();
  await expect(page.getByRole('option', { name: `Unused ${tag}` })).toBeVisible();
  await page.getByRole('option', { name: `Science ${tag}` }).click({ force: true });
  await expect(page.getByText(`Physics 101 ${tag}`)).toBeVisible({ timeout: 15000 });
  await expect(page.getByText(`Chemistry 101 ${tag}`)).toBeVisible();
  await expect(page.getByText(`Painting ${tag}`)).toHaveCount(0);
  await expect(page.getByText('All Categories')).toHaveCount(0);
  expect(problems).toEqual([]);
});

test('the membership editor selects curriculum items and bundles, and keeps old rules removable', async ({ page }) => {
  const problems = watch(page);
  const tag = state.tag;
  await login(page, admin().username, admin().password);
  await page.goto(app('/memberships'));
  await page.getByText(`#${state.plan}`, { exact: true }).click({ timeout: 30000 });
  await page.getByRole('tab', { name: 'Courses', exact: true }).click();
  const modal = page.locator('.ohmylms-membership-modal');
  await expect(modal.getByText('Curriculum mindmap')).toBeVisible({ timeout: 30000 });
  await expect(modal.getByText('Included through parent')).toHaveCount(2);
  await expect(modal.getByText('1 course linked to this item').first()).toBeVisible();

  // The old category rule is listed, still counts toward the preview, and can only be removed.
  const legacy = modal.locator('.ohmylms-membership-legacy-rules');
  await expect(legacy).toContainText(`Legacy category ${tag} (category)`);
  const preview = modal.locator('table.widefat');
  await expect(preview.getByRole('row', { name: new RegExp(`Legacy course ${tag}.*Legacy category ${tag}`) })).toBeVisible();
  await expect(preview.getByRole('row', { name: new RegExp(`Physics 101 ${tag}.*Physics ${tag}`) })).toBeVisible();
  await expect(modal.getByText('Included courses preview (3)')).toBeVisible();
  await legacy.getByRole('button', { name: 'Remove' }).click();
  await expect(legacy).toHaveCount(0);
  await expect(modal.getByText('Included courses preview (2)')).toBeVisible({ timeout: 15000 });

  // Add a course bundle: its courses join the preview, with the bundle named as the reason.
  const field = modal.getByRole('heading', { name: 'Course bundles', exact: true }).locator('xpath=following-sibling::*[1]');
  await field.click({ force: true });
  await page.getByRole('option', { name: `Data career ${tag}` }).click({ force: true });
  await expect(preview.getByRole('row', { name: new RegExp(`Painting ${tag}.*Data career ${tag}`) })).toBeVisible({ timeout: 15000 });
  await expect(page.getByRole('option', { name: `Hidden path ${tag} (draft)` })).toBeVisible();

  const put = saved(page, `membership/${state.plan}`);
  await modal.getByRole('button', { name: 'Save', exact: true }).click();
  await put;
  const after = server();
  expect(after.plan).toEqual([state.science]);
  expect(after.plan_bundles).toEqual([state.bundle]);
  expect(after.legacy_rules).toEqual([]);
  expect(after.legacy_terms).toEqual([state.legacy_term]);
  expect(problems).toEqual([]);
});

test('public course filters list curriculum items and course bundles and filter without a reload', async ({ page }) => {
  const tag = state.tag;
  const problems = watch(page);
  await page.goto(state.page_url);
  const filters = page.locator('.ohmylms-filter-accordion');
  await expect(filters.getByText('Curriculum', { exact: true })).toBeVisible({ timeout: 30000 });
  await expect(filters.getByText('Course bundle', { exact: true })).toBeVisible();
  for (const hidden of [`Unused ${tag}`, `Hidden path ${tag}`, `Legacy category ${tag}`]) await expect(filters.getByText(hidden)).toHaveCount(0);
  const box = (name) => filters.locator('label.ohmylms-checkbox', { hasText: name });
  const grid = page.locator('body');
  for (const name of [`Physics 101 ${tag}`, `Painting ${tag}`, `Plain course ${tag}`]) await expect(grid.getByText(name).first()).toBeVisible();

  const ajax = () => page.waitForResponse((response) => response.url().includes('admin-ajax.php') && response.request().method() === 'POST');
  let pending = ajax();
  await box(`Science ${tag}`).click();
  await pending;
  await expect(grid.getByText(`Physics 101 ${tag}`).first()).toBeVisible();
  await expect(grid.getByText(`Chemistry 101 ${tag}`).first()).toBeVisible();
  await expect(grid.getByText(`Painting ${tag}`)).toHaveCount(0);
  await expect(grid.getByText(`Plain course ${tag}`)).toHaveCount(0);

  pending = ajax();
  await box(`Data career ${tag}`).click();
  await pending;
  await expect(grid.getByText(`Physics 101 ${tag}`).first()).toBeVisible();
  await expect(grid.getByText(`Chemistry 101 ${tag}`)).toHaveCount(0);
  await expect(grid.getByText(`Painting ${tag}`)).toHaveCount(0);
  expect(problems).toEqual([]);
});

test('old category addresses still work and the old terms are untouched', async ({ page }) => {
  const tag = state.tag;
  // The taxonomies stay registered, so the old archive addresses keep resolving.
  const plain = await page.goto(`/?course_category=legacy-category-${tag}`);
  expect(plain.status()).toBe(200);
  expect(await page.content()).not.toMatch(/Fatal error|Parse error|Uncaught/);
  const response = await page.goto(`/?post_type=ohmylms-course&course_category=legacy-category-${tag}`);
  expect(response.status()).toBe(200);
  expect(await page.content()).not.toMatch(/Fatal error|Parse error|Uncaught/);
  await expect(page.getByText(`Legacy course ${tag}`).first()).toBeVisible();
  await expect(page.getByText(`Physics 101 ${tag}`)).toHaveCount(0);
  expect(server().legacy_terms).toEqual([state.legacy_term]);
});