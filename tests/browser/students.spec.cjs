const {test,expect}=require('@playwright/test');
const fs=require('node:fs');
async function login(page){
 const credentials=JSON.parse(fs.readFileSync(process.env.OHMYLMS_TEST_CREDENTIALS,'utf8'));
 await page.goto('/wp-login.php');await page.locator('#user_login').fill(credentials.username);await page.locator('#user_pass').fill(credentials.password);await page.locator('#wp-submit').click();await page.waitForURL(/wp-admin/);
 await page.goto('/wp-admin/admin.php?page=ohmylms&ohmylms_legacy=1');
}
async function registration(page){
 await page.goto('/?page_id=210');
 await page.locator('.ohmylms-show-signup-form').first().click();
 const form=page.locator('form[data-ohmylms-registration="react"]').first();
 await expect(form).toBeVisible();
 await expect(form.getByRole('button',{name:'Sign Up',exact:true})).toBeDisabled();
 await form.getByLabel('First Name').fill('Source');await form.getByLabel('Last Name').fill('Student');
 await form.locator('[name="email"]').fill(`source-${Date.now()}@example.invalid`);
 await form.locator('[name="password"]').fill('Source-test-password-42!');
 await form.locator('[name="tnc-accept"]').check();
 return form;
}

test('registration keeps PHP fields, rejects errors and submits verification once',async({page})=>{
 const errors=[];page.on('pageerror',error=>errors.push(error.message));
 const form=await registration(page);
 await form.getByRole('button',{name:'Show password',exact:true}).click();
 await expect(form.locator('[name="password"]')).toHaveAttribute('type','text');
 await form.evaluate(element=>{const input=document.createElement('input');input.name='extension_fixture';input.value='preserved';element.append(input);});
 let count=0;
 await page.route('**/admin-ajax.php',async route=>{
  if(!route.request().postData()?.includes('ohmylms_signup'))return route.continue();
  count++;const body=route.request().postData();expect(body).toContain('ohmylms-signup-nonce');expect(body).toContain('extension_fixture');
  await route.fulfill({json:count===1?{status:'error',message:'<strong>Email already exists</strong>'}:{status:'pending_verification',message:'Please verify your email.'}});
 });
 await form.getByRole('button',{name:'Sign Up',exact:true}).click();
 await expect(form.getByRole('alert')).toHaveText('Email already exists');
 await form.getByRole('button',{name:'Sign Up',exact:true}).click();
 await expect(page.getByRole('status').filter({hasText:'Please verify your email.'})).toBeVisible();
 await expect(form).toBeHidden();expect(count).toBe(2);expect(errors).toEqual([]);
});

test('registration creates a real account and redirects',async({page,browser})=>{
 const adminContext=await browser.newContext();const admin=await adminContext.newPage();await login(admin);
 const form=await registration(page);const email=await form.locator('[name="email"]').inputValue();
 try{
  let result;
  await page.route('**/admin-ajax.php',async route=>{
   if(!route.request().postData()?.includes('ohmylms_signup'))return route.continue();
   const response=await route.fetch();result=await response.json();await route.fulfill({response});
  });
  await form.getByRole('button',{name:'Sign Up',exact:true}).click();
  await expect.poll(()=>result?.status).toBe('success');
  await page.waitForURL(result.redirect_url);
  const users=await admin.evaluate(email=>wp.apiFetch({path:'/wp/v2/users?context=edit&search='+encodeURIComponent(email)}),email);
  expect(users).toHaveLength(1);expect(users[0].roles).not.toContain('administrator');
 }finally{
  await admin.evaluate(async email=>{const self=await wp.apiFetch({path:'/wp/v2/users/me'});const users=await wp.apiFetch({path:'/wp/v2/users?context=edit&search='+encodeURIComponent(email)});for(const user of users)if(user.email===email)await wp.apiFetch({path:`/wp/v2/users/${user.id}?force=true&reassign=${self.id}`,method:'DELETE'});},email);
  await adminContext.close();
 }
});

test('student list sorts, searches, and blocks the current bulk selection',async({page})=>{
 await login(page);const errors=[];page.on('pageerror',error=>errors.push(error.message));
 let lastQuery;let ids;let blocked=false;
 await page.route(/\/ohmylms\/v1\/students(?:\?|$)/,async route=>{
  if(route.request().method()==='POST'){ids=route.request().postDataJSON().ids;blocked=true;return route.fulfill({json:{status:'success'}});}
  lastQuery=new URL(route.request().url()).searchParams;
  return route.fulfill({headers:{'X-WP-Total':'2'},json:[1,2].map(id=>({user_id:id,student_name:`Fixture ${id}`,student_email:`fixture${id}@example.invalid`,is_banned:blocked,courses_enrolled:1}))});
 });
 await page.goto('/wp-admin/admin.php?page=ohmylms&ohmylms_legacy=1#/students');
 await expect(page.getByText('Fixture 1',{exact:true})).toBeVisible();
 await page.getByRole('button',{name:'Email',exact:true}).click();
 await expect.poll(()=>lastQuery?.get('order_by')).toBe('email');
 await page.getByPlaceholder('Search Students').fill('Fixture');
 await expect.poll(()=>lastQuery?.get('search')).toBe('Fixture');
 const rows=page.locator('tbody tr');await rows.nth(0).getByRole('checkbox').check();await rows.nth(1).getByRole('checkbox').check();
 await page.getByRole('button',{name:'Apply',exact:true}).click();
 await page.getByRole('button',{name:'Block',exact:true}).click();
 await expect.poll(()=>ids?.length).toBe(2);expect(ids.map(Number)).toEqual([1,2]);
 await expect(page.getByText('Blocked',{exact:true})).toHaveCount(2);expect(errors).toEqual([]);
});
