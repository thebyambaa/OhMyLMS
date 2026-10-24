const {test,expect}=require('@playwright/test');
const fs=require('node:fs');
const path=require('node:path');
// Signs in with OHMYLMS_TEST_CREDENTIALS, or reuses the local outline fixture session (tests/php/course-structure-browser-fixture.php).
const sessionPath=path.resolve(__dirname,'../../test-results/structure-browser-session.json');
const session=!process.env.OHMYLMS_TEST_CREDENTIALS&&fs.existsSync(sessionPath)?JSON.parse(fs.readFileSync(sessionPath,'utf8')):null;
test.skip(!process.env.OHMYLMS_TEST_CREDENTIALS&&!session,'Requires OHMYLMS_TEST_CREDENTIALS or the local outline fixture session.');
const admin=(route)=>`${session?session.base:''}/wp-admin/admin.php?page=ohmylms${route}`;
test.beforeEach(async({page,context})=>{
 if(session){await context.addCookies(session.cookies);}else{
  const credentials=JSON.parse(fs.readFileSync(process.env.OHMYLMS_TEST_CREDENTIALS,'utf8'));
  await page.goto('/wp-login.php');await page.locator('#user_login').fill(credentials.username);await page.locator('#user_pass').fill(credentials.password);await page.locator('#wp-submit').click();await page.waitForURL(/wp-admin/);
 }
 await page.goto(admin(''));
});
// The course editor saves units through the outline's structure endpoint, never the bulk chapter route.
const watchBulkChapterWrites=(page,courseId)=>{const writes=[];page.on('request',request=>{if(request.url().includes(`/courses/${courseId}/chapters`)&&request.method()==='POST')writes.push(request.url());});return writes;};
// Reading the course first gives its unit DELETEs the current structure revision.
const removeCourse=(page,id)=>page.evaluate(async id=>{
 const saved=await wp.apiFetch({path:`/ohmylms/v1/courses/${id}`});
 for(const chapter of saved.chapters||[])await wp.apiFetch({path:`/ohmylms/v1/chapters/${chapter.id}`,method:'DELETE'});
 await wp.apiFetch({path:`/ohmylms/v1/courses/${id}`,method:'DELETE'});
},id);
test('course list creates a course through the source dialog',async({page})=>{
 const errors=[];page.on('pageerror',error=>errors.push(error.message));
 await page.goto(admin('#/courses'));
 await page.getByRole('button',{name:'Add Course',exact:true}).first().click();
 const selfPaced=page.getByRole('button',{name:/Self.Paced/});
 if(await selfPaced.count())await selfPaced.first().click();
 const createRoute=/\/courses(?:\?|$)/;
 await page.route(createRoute,route=>route.request().method()==='POST'?route.fulfill({status:503,contentType:'application/json',body:JSON.stringify({code:'test_create_failure',message:'Simulated creation failure',data:{status:503}})}):route.continue());
 await page.getByRole('button',{name:'Start an empty course',exact:true}).click();
 await expect(page.getByRole('alert').filter({hasText:'Simulated creation failure'})).toBeVisible();
 await page.unroute(createRoute);
 const created=page.waitForResponse(response=>/\/courses(?:\?|$)/.test(response.url())&&response.request().method()==='POST');
 await page.getByRole('button',{name:'Start an empty course',exact:true}).click();
 const response=await created;expect(response.status()).toBe(201);const course=await response.json();
 const bulkWrites=watchBulkChapterWrites(page,course.id);
 try{
  await expect(page.getByRole('heading',{name:'Course outline',exact:true})).toBeVisible();
  await page.getByRole('tab',{name:'Course info',exact:true}).click();
  await page.getByPlaceholder('Enter Course Title').fill('Created from source dialog');
  await page.getByRole('tab',{name:'Outline',exact:true}).click();
  await page.getByRole('button',{name:'Add unit',exact:true}).click();
  await page.getByLabel('Unit title',{exact:true}).fill('Second unit');
  await page.getByRole('button',{name:'Save outline draft',exact:true}).click();
  await expect(page.getByRole('region',{name:'Course outline editor'}).getByText('Outline draft saved.',{exact:true})).toBeVisible();
  await page.reload();
  await page.getByRole('tab',{name:'Course info',exact:true}).click();
  await expect(page.getByPlaceholder('Enter Course Title')).toHaveValue('Created from source dialog');
  const structure=await page.evaluate(id=>wp.apiFetch({path:`/ohmylms/v1/courses/${id}/structure`}),course.id);
  expect(structure.units.map(unit=>unit.title)).toContain('Second unit');
  expect(bulkWrites).toEqual([]);
  expect(errors).toEqual([]);
 }finally{
  await removeCourse(page,course.id);
 }
});
test('course source authoring preserves the outline and settings',async({page},testInfo)=>{
 test.setTimeout(180000);
 const errors=[];page.on('pageerror',error=>errors.push(error.message));
 const fixture=await page.evaluate(async()=>{
  const course=await wp.apiFetch({path:'/ohmylms/v1/courses',method:'POST',data:{name:'Course source fixture',description:'Initial course description',status:'draft'}});
  const loaded=await wp.apiFetch({path:`/ohmylms/v1/courses/${course.id}`});
  const chapter=await wp.apiFetch({path:`/ohmylms/v1/chapters/${loaded.chapters[0].id}`,method:'POST',data:{name:'First chapter',description:'Chapter description'}});
  const lesson=await wp.apiFetch({path:'/ohmylms/v1/lessons',method:'POST',data:{name:'Course source lesson',type:'text',description:'Lesson body',status:'publish'}});
  await wp.apiFetch({path:`/ohmylms/v1/chapters/${chapter.id}/content`,method:'POST',data:[{id:lesson.id,name:lesson.name,type:'text',order_number:0}]});
  return {course,chapter,lesson};
 });
 const bulkWrites=watchBulkChapterWrites(page,fixture.course.id);
 try{
  await page.goto(admin(`#/course-edit/${fixture.course.id}/content`));
  const tree=page.getByRole('navigation',{name:'Units, lessons and activities'});
  await expect(tree.getByRole('button',{name:'First chapter',exact:true})).toBeVisible({timeout:20000});
  await expect(tree.getByRole('button',{name:'Course source lesson',exact:true})).toBeVisible();
  await page.getByRole('tab',{name:'Course info',exact:true}).click();
  await expect(page.getByPlaceholder('Enter Course Title')).toHaveValue('Course source fixture');
  await page.getByPlaceholder('Enter Course Title').fill('Saved React course');
  await page.locator('.ohmylms-course-content-info [contenteditable="true"]').fill('Saved course description');
  await page.getByRole('tab',{name:'Outline',exact:true}).click();
  await tree.getByRole('button',{name:'First chapter',exact:true}).click();
  await page.getByLabel('Unit title',{exact:true}).fill('Renamed source unit');
  await page.getByRole('button',{name:'Save outline draft',exact:true}).click();
  await expect(page.getByRole('region',{name:'Course outline editor'}).getByText('Outline draft saved.',{exact:true})).toBeVisible();
  await page.reload();
  await page.getByRole('tab',{name:'Course info',exact:true}).click();
  await expect(page.getByPlaceholder('Enter Course Title')).toHaveValue('Saved React course');
  await expect(page.locator('.ohmylms-course-content-info [contenteditable="true"]')).toContainText('Saved course description');
  const persisted=await page.evaluate(id=>wp.apiFetch({path:`/ohmylms/v1/courses/${id}`}),fixture.course.id);
  expect(persisted.chapters.map(chapter=>Number(chapter.id))).toEqual([Number(fixture.chapter.id)]);
  expect(persisted.chapters[0].name).toBe('Renamed source unit');
  expect(persisted.chapters[0].contents.map(content=>Number(content.id))).toContain(Number(fixture.lesson.id));
  await page.screenshot({path:testInfo.outputPath('course-content.png'),fullPage:true});
  await page.getByRole('button',{name:'2 Settings',exact:true}).click();
  await page.getByRole('radio',{name:'Paid',exact:true}).check();
  await page.locator('input.ohmylms-course-settings-pricing-input-regular, .ohmylms-course-settings-pricing-input-regular input').fill('120');
  await page.locator('input.ohmylms-course-settings-pricing-input-discount, .ohmylms-course-settings-pricing-input-discount input').first().fill('90');
  await page.getByRole('heading',{name:'Pricing',exact:true}).click();
  for(const tab of ['Resources','Organize','Engagement','Basics']){
   await page.getByRole('tab',{name:tab,exact:true}).click();
   expect(errors).toEqual([]);
  }
  await page.screenshot({path:testInfo.outputPath('course-settings.png'),fullPage:true});
  // A failed course write must keep the editor's data.
  const courseRoute=new RegExp(`/courses/${fixture.course.id}(?:\\?|$)`);
  await page.route(courseRoute,route=>route.request().method()==='POST'?route.fulfill({status:500,contentType:'application/json',body:JSON.stringify({code:'test_course_failure',message:'Simulated course failure',data:{status:500}})}):route.continue());
  await page.locator('.ohmylms-more-options-dropdown button').click();
  await page.getByRole('menuitem',{name:'Save as Draft',exact:true}).click();
  await expect(page.getByRole('alert').filter({hasText:'Simulated course failure'})).toBeVisible();
  expect(Number(await page.evaluate(()=>wp.data.select('ohmylms/store').getCourse().regular_price))).toBe(120);
  await page.unroute(courseRoute);
  await page.getByRole('button',{name:/3 Preview/}).click();
  await expect(page.getByText('Review Course Summary',{exact:true})).toBeVisible();
  const published=page.waitForResponse(response=>courseRoute.test(response.url())&&response.request().method()==='POST');
  await page.getByRole('button',{name:'Publish',exact:true}).click();expect((await published).ok()).toBe(true);
  await expect(page.getByText('Course has been published successfully').first()).toBeVisible();
  await page.reload();
  await expect(page.getByText('Review Course Summary',{exact:true})).toBeVisible();
  const finalCourse=await page.evaluate(id=>wp.apiFetch({path:`/ohmylms/v1/courses/${id}`}),fixture.course.id);
  expect(finalCourse.status).toBe('publish');expect(finalCourse.price_type).toBe('paid');
  expect(Number(finalCourse.regular_price)).toBe(120);expect(Number(finalCourse.sale_price)).toBe(90);
  expect(bulkWrites).toEqual([]);
  expect(errors).toEqual([]);
 }finally{
  await testInfo.attach('course-errors',{body:JSON.stringify(errors),contentType:'application/json'});
  // A unit that still holds the lesson cannot be deleted.
  await page.evaluate(id=>wp.apiFetch({path:`/ohmylms/v1/lessons/${id}`,method:'DELETE'}),fixture.lesson.id);
  await removeCourse(page,fixture.course.id);
 }
});
