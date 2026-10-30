const {test,expect}=require('@playwright/test');
const fs=require('node:fs');
async function login(page){
 const credentials=JSON.parse(fs.readFileSync(process.env.OHMYLMS_TEST_CREDENTIALS,'utf8'));
 await page.goto('/wp-login.php');await page.locator('#user_login').fill(credentials.username);await page.locator('#user_pass').fill(credentials.password);await page.locator('#wp-submit').click();await page.waitForURL(/wp-admin/);
}
test.beforeEach(async({page})=>{await login(page);});
test('custom admin page and isolated rendering failure',async({page})=>{
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto('/wp-admin/admin.php?page=ohmylms&ohmylms_legacy=1#/extensions/example-page');
 await expect(page.getByRole('heading',{name:'OhMyLMS example module'})).toBeVisible();
 await page.evaluate(()=>{
  const sdk=window.ohmylms.extensions;
  sdk.registerSlot('test-throwing',{label:'Throwing example',slot:'admin.screen.after',render:()=>{throw new Error('Expected isolated extension failure');}});
 });
 await page.goto('/wp-admin/admin.php?page=ohmylms&ohmylms_legacy=1');
 await page.evaluate(()=>{
  window.ohmylms.extensions.registerSlot('test-throwing',{label:'Throwing example',slot:'admin.screen.after',render:()=>{throw new Error('Expected isolated extension failure');}});
 });
 await expect(page.getByRole('alert').filter({hasText:'This extension could not be displayed.'})).toBeVisible();
 await expect(page.getByText('Overview',{exact:true})).toBeVisible();
 expect(errors.filter(e=>!e.includes('Expected isolated extension failure'))).toEqual([]);
});
test('membership UI creates and reopens namespaced extension settings',async({page},testInfo)=>{
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto('/wp-admin/admin.php?page=ohmylms&ohmylms_legacy=1#/memberships');
 await page.getByRole('button',{name:'Add Membership',exact:true}).first().click();
 await expect(page.getByLabel('Member benefit')).toBeVisible();
 await page.locator('.ohmylms-membership-plan-name-input input, input.ohmylms-membership-plan-name-input').fill('Browser extension plan');
 await page.getByPlaceholder('e.g. 5.90').first().fill('120');
 await page.getByLabel('Member benefit').fill('Browser-tested benefit');
 await page.getByRole('button',{name:'Next',exact:true}).click();
 const saved=page.waitForResponse(r=>r.url().includes('/membership')&&r.request().method()==='POST');
 await page.getByRole('button',{name:'Save',exact:true}).click();
 const response=await saved;expect(response.status()).toBe(201);const plan=await response.json();
 try{
  expect(plan.extension_settings['example-benefit'].benefit).toBe('Browser-tested benefit');
  await expect(page.getByText('Browser extension plan',{exact:true})).toBeVisible();
  await page.getByText(`#${plan.id}`,{exact:true}).click();
  await expect(page.getByLabel('Member benefit')).toHaveValue('Browser-tested benefit');
  await page.screenshot({path:testInfo.outputPath('membership-extension.png'),fullPage:true});
  expect(errors).toEqual([]);
 } finally {await page.evaluate(id=>wp.apiFetch({path:`/ohmylms/v1/membership/${id}`,method:'DELETE'}),plan.id);}
});
test('registered question editor updates the existing authoring store',async({page})=>{
 await page.goto('/wp-admin/admin.php?page=ohmylms&ohmylms_legacy=1');
 await page.waitForFunction(()=>window.ohmylms?.extensions&&wp.data.select('ohmylms/store'));
 await page.evaluate(()=>{
  const sdk=window.ohmylms.extensions;
  wp.data.dispatch('ohmylms/store').setQuestion({name:'Example number',settings:{type:'example-number',expected:1,score:{value:1}}});
  const type=sdk.questionTypes('ohmylms/store').find(t=>t.type==='example-number');
  const element=document.createElement('div');element.id='question-test';document.body.appendChild(element);
  wp.element.createRoot(element).render(wp.element.createElement(type.edit));
 });
 await page.getByLabel('Expected number').fill('42');
 expect(await page.evaluate(()=>wp.data.select('ohmylms/store').selectQuestion().settings.expected)).toBe(42);
});
test('admin feature routes render on desktop and mobile',async({page},testInfo)=>{
 test.setTimeout(180000);
 const failures=[];let route='';
 page.on('pageerror',error=>failures.push({route,error:error.message}));
 page.on('response',response=>{if(response.status()>=500)failures.push({route,url:response.url(),status:response.status()});});
 for(route of ['/courses','/quizzes','/memberships','/orders','/subscriptions','/students','/integrations','/settings/general-settings','/webhooks','/certificates','/assignments']){
  await page.goto('/wp-admin/admin.php?page=ohmylms&ohmylms_legacy=1#'+route);
  await page.waitForLoadState('networkidle');
  await expect(page.locator('body')).not.toContainText('There has been a critical error');
 }
 await page.setViewportSize({width:390,height:844});
 await page.goto('/wp-admin/admin.php?page=ohmylms&ohmylms_legacy=1#/memberships');await page.waitForLoadState('networkidle');
 await expect(page.getByText('All Memberships',{exact:true})).toBeVisible();
 await page.screenshot({path:testInfo.outputPath('memberships-mobile.png'),fullPage:true});
 await page.evaluate(()=>document.documentElement.dir='rtl');
 await page.screenshot({path:testInfo.outputPath('memberships-rtl.png'),fullPage:true});
 await testInfo.attach('route-observations',{body:JSON.stringify(failures,null,2),contentType:'application/json'});
 expect(failures).toEqual([]);
});

test('custom lesson uses the real save and reopen flow',async({page})=>{
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto('/wp-admin/admin.php?page=ohmylms&ohmylms_legacy=1');
 const lesson=await page.evaluate(()=>wp.apiFetch({path:'/ohmylms/v1/lessons',method:'POST',data:{name:'Browser reading',type:'example-reading',description:'Initial reading',status:'draft'}}));
 try{
  await page.goto(`/wp-admin/admin.php?page=ohmylms&ohmylms_legacy=1#/lesson-edit/${lesson.id}`);
  await expect(page.getByLabel('Reading title')).toHaveValue('Browser reading');
  await page.getByLabel('Reading title').fill('Updated browser reading');
  await page.getByLabel('Reading content').fill('Updated reading body');
  const saved=page.waitForResponse(r=>r.url().includes(`/lessons/${lesson.id}`)&&['POST','PUT','PATCH'].includes(r.request().method()));
  await page.getByRole('button',{name:'Save',exact:true}).click();
  expect((await saved).ok()).toBe(true);
  await page.reload();
  await expect(page.getByLabel('Reading title')).toHaveValue('Updated browser reading');
  await expect(page.getByLabel('Reading content')).toHaveValue('Updated reading body');
  expect(errors).toEqual([]);
 }finally{await page.evaluate(id=>wp.apiFetch({path:`/ohmylms/v1/lessons/${id}`,method:'DELETE'}),lesson.id);}
});

test('built-in rich text lesson edits without duplicate editor plugins',async({page})=>{
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto('/wp-admin/admin.php?page=ohmylms&ohmylms_legacy=1');
 const lesson=await page.evaluate(()=>wp.apiFetch({path:'/ohmylms/v1/lessons',method:'POST',data:{name:'Rich text fixture',type:'text',description:'Initial text',status:'draft'}}));
 try{
  await page.goto(`/wp-admin/admin.php?page=ohmylms&ohmylms_legacy=1#/lesson-edit/${lesson.id}`);
  const editor=page.locator('[contenteditable="true"]').first();
  await page.waitForFunction(id=>wp.data.select('ohmylms/store').getLesson()?.id==id,lesson.id);
  await expect(editor).toContainText('Initial text');await editor.fill('Saved rich text lesson');
  const saved=page.waitForResponse(r=>r.url().includes(`/lessons/${lesson.id}`)&&['POST','PUT','PATCH'].includes(r.request().method()),{timeout:15000});
  await page.getByRole('button',{name:'Save',exact:true}).click();expect((await saved).ok()).toBe(true);
  await page.reload();await expect(page.locator('[contenteditable="true"]').first()).toContainText('Saved rich text lesson');
  expect(errors).toEqual([]);
 }finally{await page.evaluate(id=>wp.apiFetch({path:`/ohmylms/v1/lessons/${id}`,method:'DELETE'}),lesson.id);}
});

test('student course page mounts its extension slot',async({page},testInfo)=>{
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto('/wp-admin/admin.php?page=ohmylms&ohmylms_legacy=1');
 const course=await page.evaluate(()=>wp.apiFetch({path:'/ohmylms/v1/courses',method:'POST',data:{name:'Student slot fixture',description:'Student course body',status:'publish'}}));
 try{
  await page.goto(`/?post_type=ohmylms-course&p=${course.id}`);
  await expect(page.getByText('Example course footer',{exact:true})).toBeVisible();
  await page.screenshot({path:testInfo.outputPath('student-course.png'),fullPage:true});
  expect(errors).toEqual([]);
 }finally{
  await page.goto('/wp-admin/admin.php?page=ohmylms&ohmylms_legacy=1');
  await page.evaluate(id=>wp.apiFetch({path:`/ohmylms/v1/courses/${id}`,method:'DELETE'}),course.id);
 }
});
