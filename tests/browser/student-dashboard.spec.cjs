const { test, expect } = require('@playwright/test');
const path = require('node:path');
for (const width of [1440, 390]) {
  test(`student dashboard tabs, theme feedback and layout at ${width}px`, async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.setViewportSize({ width, height: 1000 });
    let failSave = false;
    await page.route('http://127.0.0.1:8100/**', route => {
      if (route.request().method() === 'PUT') return route.fulfill({ status: failSave ? 500 : 200, json: { theme: 'sunset' } });
      const name = new URL(route.request().url()).pathname;
      if (name.endsWith('.svg')) return route.fulfill({ path: path.resolve('assets/images/dashboard-course.svg'), contentType: 'image/svg+xml' });
      const file = { '/dashboard.css': 'assets/css/student-dashboard.css', '/dashboard.js': 'assets/js/student-dashboard.js', '/streak.css': 'assets/css/streak.css' }[name] || 'build/test-fixtures/student-dashboard.html';
      return route.fulfill({ path: path.resolve(file), contentType: name.endsWith('.css') ? 'text/css' : name.endsWith('.js') ? 'application/javascript' : 'text/html' });
    });
    await page.goto('http://127.0.0.1:8100/student-dashboard');
    await expect(page.getByRole('heading', { name: 'Exploring fractions', exact: true })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.getByRole('tab', { name: 'Continue learning', exact: true }).press('ArrowRight');
    await expect(page.getByRole('tab', { name: 'Recent skills', exact: true })).toHaveAttribute('aria-selected', 'true');
    await expect(page.locator('#oml-panel-recent').getByRole('heading', { name: 'Understanding fractions', exact: true })).toBeVisible();
    await page.getByRole('tab', { name: 'Recommendations', exact: true }).click();
    await expect(page.locator('#oml-panel-recommendations')).toBeVisible();
    await page.getByRole('tab', { name: 'Continue learning', exact: true }).click();
    await page.getByText('Customize dashboard', { exact: true }).click();
    await page.getByRole('button', { name: 'Sunset', exact: true }).click();
    await expect(page.locator('.oml-student-dashboard')).toHaveAttribute('data-dashboard-theme', 'sunset');
    await expect(page.getByText('Dashboard theme saved.', { exact: true })).toBeVisible();
    failSave = true;
    await page.getByRole('button', { name: 'Meadow', exact: true }).click();
    await expect(page.getByText('Could not save your theme. Please try again.', { exact: true })).toBeVisible();
    await expect(page.locator('.oml-student-dashboard')).toHaveAttribute('data-dashboard-theme', 'sunset');
    await page.getByText('Customize dashboard', { exact: true }).click();
    await page.screenshot({ path: `test-results/student-dashboard-${width}.png`, fullPage: true });
    expect(errors).toEqual([]);
  });
}
