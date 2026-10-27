const {test,expect}=require('@playwright/test');
const fs=require('node:fs');
const path=require('node:path');
test.beforeEach(async({page})=>{
 const credentials=JSON.parse(fs.readFileSync(process.env.OHMYLMS_TEST_CREDENTIALS,'utf8'));
 await page.goto('/wp-login.php');await page.locator('#user_login').fill(credentials.username);await page.locator('#user_pass').fill(credentials.password);await page.locator('#wp-submit').click();await page.waitForURL(/wp-admin/);
 for(const file of ['sdk/extensions.js','assets/dist/admin/ohmylms.js'])await page.route('**/build/'+file+'*',route=>route.fulfill({path:path.resolve('build/'+file),contentType:'application/javascript'}));
 await page.goto('/wp-admin/admin.php?page=ohmylms#/memberships');
 await expect.poll(()=>page.evaluate(()=>Object.keys(window.ohmylms?.extensions?.membershipComponents||{}).length)).toBe(7);
});

test('membership React editor validates, retries failed saves, and persists extension fields',async({page})=>{
 const errors=[];page.on('pageerror',error=>errors.push(error.message));
 await page.getByRole('button',{name:'Add Membership',exact:true}).first().click();
 await expect(page.getByRole('button',{name:'Next',exact:true})).toBeDisabled();
 const name=page.locator('.ohmylms-membership-plan-name-input input, input.ohmylms-membership-plan-name-input');
 await name.fill('React membership fixture');
 await page.getByPlaceholder('e.g. 5.90').first().fill('120');
 await page.getByLabel('Member benefit').fill('React benefit retained');
 await page.getByRole('button',{name:'Next',exact:true}).click();
 await expect(page.getByRole('tab',{name:'Courses',exact:true})).toHaveAttribute('aria-selected','true');
 const endpoint=/\/membership(?:\?|$)/;
 await page.route(endpoint,route=>route.request().method()==='POST'?route.fulfill({status:503,json:{message:'Simulated save failure'}}):route.continue());
 const failed=page.waitForResponse(r=>r.status()===503&&r.url().includes('/membership'));
 await page.getByRole('button',{name:'Save',exact:true}).click();await failed;
 await expect(page.getByRole('button',{name:'Save',exact:true})).toBeEnabled();
 await expect(page.getByRole('dialog')).toBeVisible();
 await page.unroute(endpoint);
 // The course preview is also a POST under /membership and can finish after Save is clicked; only the save counts.
 const saved=page.waitForResponse(r=>r.url().includes('/membership')&&!r.url().includes('course-preview')&&r.request().method()==='POST');
 await page.getByRole('button',{name:'Save',exact:true}).click();
 const response=await saved;expect(response.status()).toBe(201);const plan=await response.json();
 try{
  expect(plan.extension_settings['example-benefit'].benefit).toBe('React benefit retained');
  await expect(page.getByText('React membership fixture',{exact:true})).toBeVisible();
  await page.getByText(`#${plan.id}`,{exact:true}).click();
  await expect(page.getByLabel('Member benefit')).toHaveValue('React benefit retained');
  await name.fill('Updated React membership');
  await page.getByRole('button',{name:'Next',exact:true}).click();
  const updated=page.waitForResponse(r=>r.url().includes(`/membership/${plan.id}`)&&r.request().method()==='POST');
  await page.getByRole('button',{name:'Save',exact:true}).click();expect((await updated).ok()).toBe(true);
  await page.reload();await expect(page.getByText('Updated React membership',{exact:true})).toBeVisible();
  expect(errors).toEqual([]);
 }finally{await page.evaluate(id=>wp.apiFetch({path:`/ohmylms/v1/membership/${id}`,method:'DELETE'}),plan.id);}
});
