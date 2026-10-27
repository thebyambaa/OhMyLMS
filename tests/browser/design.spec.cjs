// Settings → Design → Colors & fonts, and the shared layout of SDK admin pages.
const { test, expect } = require('@playwright/test');
const fs = require('node:fs');

const KEYS = ['ohmylms_admin_primary_color', 'ohmylms_admin_font_family', 'ohmylms_font_family'];
async function login(page) {
  const credentials = JSON.parse(fs.readFileSync(process.env.OHMYLMS_TEST_CREDENTIALS, 'utf8'));
  await page.goto('/wp-login.php');
  await page.locator('#user_login').fill(credentials.username);
  await page.locator('#user_pass').fill(credentials.password);
  await page.locator('#wp-submit').click();
  await page.waitForURL(/wp-admin/);
}
const saveDesign = (page, values) =>
  page.evaluate(
    (data) => wp.apiFetch({ path: '/ohmylms/v1/settings/design', method: 'POST', data }),
    values,
  );

test('admin colors and font are chosen under Design and applied everywhere', async ({ page }) => {
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await login(page);
  await page.goto('/wp-admin/admin.php?page=ohmylms#/settings/design-settings/course-list');
  const before = await page.evaluate(() => wp.apiFetch({ path: '/ohmylms/v1/settings/design' }));
  try {
    await page.getByRole('tab', { name: 'Colors & fonts' }).click();
    const section = page.locator('.ohmylms-theme-settings');
    await expect(section.getByRole('heading', { name: 'Learner site' })).toBeVisible();
    await expect(section.getByRole('heading', { name: 'Admin dashboard' })).toBeVisible();
    await section.getByLabel('Font').nth(1).selectOption('Noto Sans');
    await page.evaluate(() =>
      wp.data
        .dispatch('ohmylms/store')
        .updateDesignSettings({ ohmylms_admin_primary_color: { value: '#0f766e' } }),
    );
    // Colors preview before saving.
    expect(
      await page.evaluate(() =>
        getComputedStyle(document.documentElement).getPropertyValue('--wp-admin-theme-color').trim(),
      ),
    ).toBe('#0f766e');
    const saved = page.waitForResponse(
      (r) => r.url().includes('settings/design') && r.request().method() !== 'GET',
    );
    await page.getByRole('button', { name: 'Save Changes' }).first().click();
    expect((await saved).ok()).toBe(true);

    // Native screens and SDK pages both follow the saved tokens.
    await page.goto('/wp-admin/admin.php?page=ohmylms#/courses');
    await page.reload();
    const add = page.getByRole('button', { name: /Add Course/ });
    await expect(add).toHaveCSS('background-color', 'rgb(15, 118, 110)');
    await expect(page.getByRole('heading', { name: 'All Courses' })).toHaveCSS(
      'font-family',
      /Noto Sans/,
    );
    // The old Skills address opens the Content Hub's Skills tab.
    await page.goto('/wp-admin/admin.php?page=ohmylms#/extensions/skills');
    const title = page.locator('.ohmylms-content-hub-header h1');
    await expect(title).toHaveText('Content Hub');
    await expect(title).toHaveCSS('font-size', '20px');
    await expect(page.locator('.ohmylms-ext-header h2')).toHaveText('Skill library');
    await expect(page.locator('.ohmylms-dashboard-layout.extensions .ohmylms-content')).toHaveCSS(
      'padding-left',
      '40px',
    );
    await expect(page.getByRole('button', { name: 'Add skill' })).toHaveCSS(
      'background-color',
      'rgb(15, 118, 110)',
    );
    expect(errors).toEqual([]);
  } finally {
    const restore = {};
    for (const key of KEYS) {
      const field = (Array.isArray(before) ? before : Object.values(before || {})).find?.(
        (item) => item && item.id === key,
      );
      restore[key] = field ? field.value : '';
    }
    await saveDesign(page, restore);
  }
});

test('learner pages read the design tokens', async ({ page, request }) => {
  const html = await (await request.get('/')).text();
  expect(html).toContain('--ohmylms-primary-color:');
  expect(html).toContain('--ohmylms-heading-color:');
});
