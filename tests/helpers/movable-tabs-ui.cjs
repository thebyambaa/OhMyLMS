const {expect} = require('@playwright/test');

async function verifyMovableTabs(page) {
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  const scopes = ['content-hub', 'memberships'];
  await page.goto('/wp-admin/admin.php?page=ohmylms#/content-hub');
  const original = await page.evaluate(() => window.ohmylmsTabPreferences || {});
  const nav = page.locator('.ohmylms-content-hub > nav');
  await expect(nav.getByRole('link', {name:'Courses', exact:true})).toBeVisible();
  await expect(nav.getByRole('link', {name:'Catalog', exact:true})).toHaveCount(0);
  const waitSaved = async () => expect(page.locator('.ohmylms-tab-save-status')).toHaveCount(0);
  try {
    await nav.getByRole('button', {name:'Organize tabs', exact:true}).click();
    let dialog = page.getByRole('dialog');
    await dialog.getByRole('button', {name:'Reset layout', exact:true}).click();
    await waitSaved();
    await dialog.getByRole('button', {name:'New group', exact:true}).click();
    await dialog.getByLabel('Group name', {exact:true}).fill('Study tools');
    await dialog.getByLabel('Background color', {exact:true}).fill('#123456');
    await dialog.getByLabel('Text color', {exact:true}).fill('#ffffff');
    for (const name of ['Courses', 'Quizzes']) {
      await dialog.locator('.ohmylms-tab-assignments label').filter({hasText:new RegExp(`^${name}`)}).locator('select').selectOption({label:'Study tools'});
    }
    await waitSaved();
    await page.screenshot({path:'build/movable-tabs-dialog-qa.png',fullPage:false});
    await dialog.getByRole('button', {name:'Done', exact:true}).click();
    const group = nav.locator('.ohmylms-tab-group');
    await expect(group).toHaveCSS('background-color', 'rgb(18, 52, 86)');
    await expect(group).toHaveCSS('color', 'rgb(255, 255, 255)');
    await expect(group.getByRole('link')).toHaveText(['Courses', 'Quizzes']);
    // Drag into a group, then move that entire block to the front.
    await nav.getByRole('link', {name:'Skills', exact:true}).dragTo(group.getByRole('button', {name:/Study tools/}));
    await expect(group.getByRole('link')).toHaveText(['Courses', 'Quizzes', 'Skills']);
    await expect(nav.locator('a').first()).toHaveText('Lessons');
    await group.getByRole('button', {name:/Study tools/}).dragTo(nav.getByRole('link', {name:'Lessons', exact:true}), {targetPosition:{x:2,y:12}});
    await expect(nav.locator('a').first()).toHaveText('Courses');
    await waitSaved();
    await nav.screenshot({path:'build/movable-tabs-groups-qa.png'});
    await page.reload();
    await expect(group.getByRole('link')).toHaveText(['Courses', 'Quizzes', 'Skills']);
    await expect(nav.locator('a').first()).toHaveText('Courses');
    await group.getByRole('button', {name:/Study tools/}).click();
    await expect(group.getByRole('link')).toHaveCount(0);
    await waitSaved();
    await page.reload();
    await expect(group.getByRole('button', {name:/Study tools/})).toHaveAttribute('aria-expanded','false');
    await group.getByRole('button', {name:/Study tools/}).click();
    await group.getByRole('link', {name:'Courses', exact:true}).click();
    await expect(page).toHaveURL(/content-hub\/courses/);
    await expect(nav.getByRole('link', {name:'Courses', exact:true})).toHaveAttribute('aria-current','page');
    // Failed saves retain the layout and expose a working retry.
    await page.route('**/tab-preferences/content-hub*', (route) => route.fulfill({status:503,json:{message:'Simulated failure'}}));
    await nav.getByRole('link', {name:'Lessons', exact:true}).focus();
    await page.keyboard.press('Alt+ArrowRight');
    await expect(page.getByRole('button', {name:'Retry',exact:true})).toBeVisible();
    await page.unroute('**/tab-preferences/content-hub*');
    await page.getByRole('button', {name:'Retry',exact:true}).click();
    await waitSaved();
    for (const [hash, name] of [
      ['memberships', 'Plans'], ['accounthub','Students'],
      ['gamification/point-settings','Bonus Point'], ['settings/general-settings','General'],
      ['emails','Admin Email'],
    ]) {
      await page.goto(`/wp-admin/admin.php?page=ohmylms#/${hash}`);
      const tabs = page.locator('.ohmylms-movable-tabs').first();
      await expect(tabs.getByRole('button',{name:'Organize tabs',exact:true})).toBeVisible();
      await tabs.getByRole('button',{name:'Organize tabs',exact:true}).click();
      dialog = page.getByRole('dialog');
      await expect(dialog.locator('.ohmylms-tab-assignments label').filter({hasText:new RegExp(`^${name}`)})).toBeVisible();
      await expect(dialog.getByRole('button',{name:'New group',exact:true})).toBeVisible();
      await dialog.getByRole('button',{name:'Done',exact:true}).click();
    }
    await page.goto('/wp-admin/admin.php?page=ohmylms#/memberships');
    const membershipNav = page.locator('.ohmylms-membership > nav');
    await membershipNav.getByRole('link', {name:'Orders',exact:true}).dragTo(membershipNav.getByRole('link', {name:'Plans',exact:true}), {targetPosition:{x:2,y:12}});
    await expect(membershipNav.locator('a').first()).toHaveText('Orders');
    await waitSaved();
    await page.reload();
    await expect(membershipNav.locator('a').first()).toHaveText('Orders');
    await membershipNav.getByRole('link',{name:'Orders',exact:true}).click();
    await expect(page).toHaveURL(/memberships\/orders/);
    await expect(membershipNav.getByRole('link',{name:'Orders',exact:true})).toHaveAttribute('aria-current','page');
    await page.screenshot({path:'build/movable-tabs-qa.png',fullPage:false});
    expect(errors).toEqual([]);
  } finally {
    // Restore each tested section even when an assertion fails.
    await page.unroute('**/tab-preferences/content-hub*');
    await page.evaluate(async ({scopes, original}) => {
      for (const scope of scopes) await wp.apiFetch({path:`/ohmylms/v1/tab-preferences/${scope}`,method:'PUT',data:original[scope] || {order:[],groups:[],assignments:{}}});
    }, {scopes, original});
  }
}
module.exports = {verifyMovableTabs};
