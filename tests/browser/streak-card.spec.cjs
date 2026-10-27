const { test, expect } = require('@playwright/test');
const path = require('node:path');
const fs = require('node:fs');
const fixture = path.resolve('build/test-fixtures/streak-card.html');

for (const width of [1440, 390]) {
  test(`streak card states and timezone feedback at ${width}px`, async ({ page }) => {
    test.skip(!fs.existsSync(fixture), 'Generate with tests/php/streak-card-fixture.php first.');
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.setViewportSize({ width, height: 1000 });
    await page.route('http://127.0.0.1:8100/**', (route) => {
      const url = new URL(route.request().url());
      const pathname = url.pathname;
      if (url.searchParams.get('rest_route') === '/ohmylms/v1/engagement/streak' || pathname === '/wp-json/ohmylms/v1/engagement/streak') return route.fulfill({ json: true });
      const file = pathname === '/streak.css' ? 'assets/css/streak.css' : pathname === '/streak.js' ? 'assets/js/streak.js' : 'build/test-fixtures/streak-card.html';
      return route.fulfill({ path: path.resolve(file), contentType: pathname.endsWith('.css') ? 'text/css' : pathname.endsWith('.js') ? 'application/javascript' : 'text/html' });
    });
    await page.goto('http://127.0.0.1:8100/');
    await expect(page.getByRole('heading', { name: 'Your learning streak' })).toBeVisible();
    await expect(page.locator('.ohmylms-streak-day')).toHaveCount(7);
    for (const status of ['practiced', 'protected', 'missed', 'pending', 'future']) {
      expect(await page.locator(`.is-${status}`).count()).toBeGreaterThan(0);
    }
    await expect(page.locator('[aria-current="date"]')).toHaveText(/Learning due today/);
    await expect(page.getByRole('link', { name: 'Continue learning' })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await page.screenshot({ path: `test-results/streak-card-${width}.png`, fullPage: true });
    await page.getByText(/Streak timezone:/).click();
    await expect(page.getByLabel('Learning timezone', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Save timezone' }).click();
    await expect(page.getByText('Timezone saved. Refresh this page to update the calendar.')).toBeVisible();
    expect(errors).toEqual([]);
  });
}
