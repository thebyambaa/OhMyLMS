const { test, expect } = require('@playwright/test');

// Learner pages rendered by tests/fixtures/interactivity.php from the real templates, with the
// Interactivity modules from assets/interactivity and no jQuery: any jQuery request is refused and
// reported. Each test drives the stores, so a store that failed to hydrate fails its test.
async function open(page, view) {
  const problems = [];
  const warnings = new Set();
  page.on('console', (message) => {
    if (message.type() === 'error') problems.push(`console: ${message.text()}`);
    if (message.type() === 'warning' && !warnings.has(message.text())) {
      warnings.add(message.text());
      test.info().annotations.push({ type: 'console warning', description: message.text() });
    }
  });
  page.on('pageerror', (error) => problems.push(`pageerror: ${error.message}`));
  page.on('response', (response) => {
    if (response.status() >= 400) problems.push(`HTTP ${response.status()}: ${response.url()}`);
  });
  page.on('requestfailed', (request) => {
    if (!/jquery/i.test(request.url())) problems.push(`requestfailed: ${request.url()}`);
  });
  await page.route(/jquery/i, (route) => {
    problems.push(`jQuery requested: ${route.request().url()}`);
    return route.abort();
  });
  await page.goto(`/fixture?view=${view}`);
  await page.waitForLoadState('networkidle');
  expect(await page.evaluate(() => typeof window.jQuery)).toBe('undefined');
  return problems;
}

test('course catalogue: load more, search and the filter drawer', async ({ page }) => {
  const problems = await open(page, 'catalog');
  const results = page.locator('.ohmylms-catalog-results');
  await expect(results.locator('.course-card')).toHaveCount(2);
  await expect(page.locator('.ohmylms-catalog-count')).toHaveText('Showing 2 of 3 courses.');

  await page.getByRole('button', { name: 'See More Courses' }).click();
  await expect(page).toHaveURL(/[?&]paged=2\b/);
  await expect(results.locator('.course-card')).toHaveCount(3);
  await expect(page.locator('.ohmylms-course-loadmore-area')).toBeHidden();

  await page.locator('input[name="course_search"]').fill('geo');
  await expect(page).toHaveURL(/[?&]course_search=geo\b/);
  await expect(results.locator('.course-card')).toHaveCount(1);
  await expect(results).toContainText('Geometry');
  await expect(page.locator('.ohmylms-catalog-count')).toHaveText('Showing 1 course.');

  const toggle = page.locator('.filter-hamburger');
  await toggle.click();
  await expect(toggle).toHaveAttribute('aria-expanded', 'true');
  await expect(page.locator('.ohmylms-course-outer')).toHaveClass(/open-sidebar/);
  expect(problems).toEqual([]);
});

async function buy(page, card) {
  const posted = [];
  await page.route('**/wp-json/ohmylms-commerce/v1/cart/items', async (route) => {
    posted.push(route.request().postDataJSON());
    await route.fulfill({ json: { status: 'error', message: 'Sold out' } });
  });
  await card.getByRole('link', { name: 'Buy now' }).click();
  await expect(card.locator('.ohmylms-cart-message')).toHaveText('Sold out');
  await expect(card.getByRole('link', { name: 'Buy now' })).toHaveClass(/added/);
  return posted;
}

test('course catalogue: the buy button posts its course', async ({ page }) => {
  const problems = await open(page, 'catalog');
  const card = page.locator('.course-card', { hasText: 'Geometry' });
  expect(await buy(page, card)).toEqual([{ course_id: 6, quantity: 1 }]);
  expect(problems).toEqual([]);
});

test('course catalogue: after a search the buy button posts the course it shows', async ({ page }) => {
  await open(page, 'catalog');
  await page.locator('input[name="course_search"]').fill('geo');
  await expect(page).toHaveURL(/[?&]course_search=geo\b/);
  const card = page.locator('.ohmylms-catalog-results .course-card');
  await expect(card).toHaveCount(1);
  await expect(card).toContainText('Geometry');
  expect(await buy(page, card)).toEqual([{ course_id: 6, quantity: 1 }]);
});

test('course carousel: mounts and the arrows scroll the track', async ({ page }) => {
  const problems = await open(page, 'carousel');
  const carousel = page.locator('.ohmylms-carousel');
  await expect(carousel).toHaveClass(/is-enhanced/);
  const next = carousel.getByRole('button', { name: 'Next' });
  await expect(next).toBeVisible();
  await next.click();
  await expect.poll(() => carousel.locator('.ohmylms-carousel-track').evaluate((track) => track.scrollLeft)).toBeGreaterThan(0);
  await expect(carousel.getByRole('button', { name: 'Previous' })).toHaveAttribute('aria-disabled', 'false');
  expect(problems).toEqual([]);
});

test('account: profile rows, my-courses tabs and the membership plan dialog', async ({ page }) => {
  const problems = await open(page, 'account');
  const skills = page.locator('#skills-container input');
  const before = await skills.count();
  await page.locator('[data-target="skills-container"]').click();
  await expect(skills).toHaveCount(before + 1);
  await expect(skills.last()).toBeFocused();

  const active = page.getByRole('tab', { name: 'Active' });
  await active.click();
  await expect(active).toHaveAttribute('aria-selected', 'true');
  await expect(page.locator('#course-active-courses')).toHaveClass(/active/);
  await active.press('ArrowRight');
  await expect(page.getByRole('tab', { name: 'Completed' })).toHaveAttribute('aria-selected', 'true');
  await expect(page.getByRole('tab', { name: 'Completed' })).toBeFocused();

  await page.getByRole('button', { name: 'View Plan' }).click();
  const dialog = page.getByRole('dialog', { name: 'Plan Details' });
  await expect(dialog).toBeVisible();
  await expect(dialog).toContainText('Geometry');
  await page.keyboard.press('Escape');
  await expect(dialog).toBeHidden();
  await expect(page.getByRole('button', { name: 'View Plan' })).toBeFocused();
  expect(problems).toEqual([]);
});

test('login and sign-up: switch, password toggle, terms gate and inline login error', async ({ page }) => {
  const problems = await open(page, 'auth');
  const login = page.locator('form.ohmylms-form-login');
  const signup = page.locator('form.ohmylms-form-signup');
  await expect(login).toBeVisible();
  await expect(signup).toBeHidden();

  await login.getByRole('button', { name: 'Sign Up' }).click();
  await expect(signup).toBeVisible();
  await expect(login).toBeHidden();
  await expect(signup.locator('#signup-first-name')).toBeFocused();
  const submit = signup.getByRole('button', { name: 'Sign Up' });
  await expect(submit).toBeDisabled();
  await signup.locator('label[for="tnc-accept"] .checkedbox').click();
  await expect(signup.locator('#tnc-accept')).toBeChecked();
  await expect(submit).toBeEnabled();
  const password = signup.locator('#signup-password');
  await signup.getByRole('button', { name: 'Show password' }).click();
  await expect(password).toHaveAttribute('type', 'text');

  await signup.getByRole('button', { name: 'Log In' }).click();
  await expect(login).toBeVisible();
  let posted = '';
  await page.route('**/wp-admin/admin-ajax.php', async (route) => {
    posted = route.request().postData() || '';
    await route.fulfill({ json: { status: 'error', message: 'Unknown username.' } });
  });
  await login.locator('#username').fill('nobody');
  await login.locator('#login-password').fill('wrong-password');
  await login.getByRole('button', { name: 'Log in' }).click();
  await expect(login.getByRole('alert')).toHaveText('Unknown username.');
  expect(posted).toContain('ohmylms_login');
  await expect(login.getByRole('button', { name: 'Log in' })).toBeEnabled();
  expect(problems).toEqual([]);
});

test('course reviews: required notice and star rating', async ({ page }) => {
  const problems = await open(page, 'reviews');
  const form = page.locator('.ohmylms-course-reviews-form');
  const notice = form.locator('.required-notice');
  await expect(notice).toBeHidden();
  await form.getByRole('button', { name: 'Submit' }).click();
  await expect(notice).toBeVisible();
  await form.locator('.ohmylms-star.star-4').click();
  await expect(form.locator('.ohmylms-star.star-4')).toHaveClass(/active/);
  await expect(form.locator('input[name="rating"][value="4"]')).toBeChecked();
  await expect(form.locator('.ohmylms-stars')).toHaveClass(/selected/);
  expect(problems).toEqual([]);
});

test('checkout: gateway selection, VAT field and floating labels', async ({ page }) => {
  const problems = await open(page, 'checkout');
  const qpay = page.locator('li.payment_method_qpay');
  await expect(qpay.locator('.payment_box')).toBeHidden();
  await qpay.locator('label.ohmylms-radiobtn').click();
  await expect(page.locator('#payment_method_qpay')).toBeChecked();
  await expect(qpay).toHaveClass(/selected/);
  await expect(qpay.locator('.payment_box')).toBeVisible();
  await expect(page.locator('li.payment_method_offline .payment_box')).toBeHidden();

  await page.route('**/wp-json/ohmylms-commerce/v1/countries/DE/states', (route) =>
    route.fulfill({ json: [{ code: 'BE', title: 'Berlin' }, { code: 'BY', title: 'Bavaria' }] }));
  const vat = page.locator('#vat_number_field');
  await expect(vat).toBeHidden();
  await page.locator('#country').selectOption('DE');
  await expect(vat).toBeVisible();
  await expect(page.locator('select#state option')).toHaveText(['Berlin', 'Bavaria']);

  const name = page.locator('#first_name');
  await name.focus();
  await expect(page.locator('#first_name_field')).toHaveClass(/ohmylms-folded/);
  expect(problems).toEqual([]);
});
