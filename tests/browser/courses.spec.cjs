const {test,expect}=require('@playwright/test');
const fs=require('node:fs');
test.beforeEach(async({page})=>{
 const credentials=JSON.parse(fs.readFileSync(process.env.OHMYLMS_TEST_CREDENTIALS,'utf8'));
 await page.goto('/wp-login.php');await page.locator('#user_login').fill(credentials.username);await page.locator('#user_pass').fill(credentials.password);await page.locator('#wp-submit').click();await page.waitForURL(/wp-admin/);
 await page.goto('/wp-admin/admin.php?page=ohmylms&ohmylms_legacy=1');
});
test('course list creates a course through the source dialog',async({page})=>{
 const errors=[];page.on('pageerror',error=>errors.push(error.message));
 await page.goto('/wp-admin/admin.php?page=ohmylms&ohmylms_legacy=1#/courses');
 await page.getByRole('button',{name:'Add Course',exact:true}).first().click();
 const selfPaced=page.getByRole('button',{name:/Self.Paced/});
 if(await selfPaced.count())await selfPaced.first().click();
 const createRoute=/\/courses(?:\?|$)/;
 await page.route(createRoute,route=>route.request().method()==='POST'?route.fulfill({status:503,contentType:'application/json',body:JSON.stringify({code:'test_create_failure',message:'Simulated creation failure',data:{status:503}})}):route.continue());
 await page.getByRole('button',{name:/Start from Scratch/}).click();
 await expect(page.getByRole('alert').filter({hasText:'Simulated creation failure'})).toBeVisible();
 await page.unroute(createRoute);
 const created=page.waitForResponse(response=>/\/courses(?:\?|$)/.test(response.url())&&response.request().method()==='POST');
 await page.getByRole('button',{name:/Start from Scratch/}).click();
 const response=await created;expect(response.status()).toBe(201);const course=await response.json();
 try{
  await expect(page.getByPlaceholder('Enter Course Title')).toBeVisible();
  await page.getByPlaceholder('Enter Course Title').fill('Created from source dialog');
  await page.getByRole('button',{name:'Add Chapter',exact:true}).first().click();
  await expect.poll(()=>page.evaluate(()=>wp.data.select('ohmylms/store').getCourseChapters().allIds.length)).toBe(2);
  await page.locator('.ohmylms-more-options-dropdown button').click();
  const saved=page.waitForResponse(response=>response.url().includes(`/courses/${course.id}/chapters`)&&response.request().method()==='POST');
  await page.getByRole('menuitem',{name:'Save as Draft',exact:true}).click();expect((await saved).ok()).toBe(true);
  await page.reload();await expect(page.getByPlaceholder('Enter Course Title')).toHaveValue('Created from source dialog');
  await expect.poll(()=>page.evaluate(()=>wp.data.select('ohmylms/store').getCourseChapters().allIds.length)).toBe(2);
  expect(errors).toEqual([]);
 }finally{
  await page.evaluate(async id=>{
   const saved=await wp.apiFetch({path:`/ohmylms/v1/courses/${id}`});
   for(const chapter of saved.chapters)await wp.apiFetch({path:`/ohmylms/v1/chapters/${chapter.id}`,method:'DELETE'});
   await wp.apiFetch({path:`/ohmylms/v1/courses/${id}`,method:'DELETE'});
  },course.id);
 }
});
test('course source authoring preserves curriculum and settings',async({page},testInfo)=>{
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
 try{
  await page.goto(`/wp-admin/admin.php?page=ohmylms&ohmylms_legacy=1#/course-edit/${fixture.course.id}/content`);
  await expect(page.getByPlaceholder('Enter Course Title')).toHaveValue('Course source fixture',{timeout:20000});
  await expect(page.locator('.ohmylms-course-builder-wrapper')).toBeVisible();
  expect(await page.evaluate(()=>Object.keys(window.ohmylms.extensions.courseComponents).length)).toBe(25);
  await expect(page.getByRole('heading',{name:'Course source lesson Lesson',exact:true})).toBeVisible();
  await page.getByPlaceholder('Enter Course Title').fill('Saved React course');
  await page.getByPlaceholder('Enter chapter name').fill('Renamed source chapter');
  await page.locator('.ohmylms-course-content-info [contenteditable="true"]').fill('Saved course description');
  await page.locator('.ohmylms-more-options-dropdown button').click();
  const saved=page.waitForResponse(response=>response.url().includes(`/courses/${fixture.course.id}/chapters`)&&response.request().method()==='POST');
  await page.getByRole('menuitem',{name:'Save as Draft',exact:true}).click();
  expect((await saved).ok()).toBe(true);
  await expect(page.getByText('Course has been saved as draft successfully').first()).toBeVisible();
  await page.reload();
  await expect(page.getByPlaceholder('Enter Course Title')).toHaveValue('Saved React course');
  await expect(page.locator('.ohmylms-course-content-info [contenteditable="true"]')).toContainText('Saved course description');
  const persisted=await page.evaluate(id=>wp.apiFetch({path:`/ohmylms/v1/courses/${id}`}),fixture.course.id);
  expect(persisted.chapters.map(chapter=>Number(chapter.id))).toEqual([Number(fixture.chapter.id)]);
  expect(persisted.chapters[0].name).toBe('Renamed source chapter');
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
  // A chapter write failure must preserve editor data and not mark publication complete.
  const chapterRoute=new RegExp(`/courses/${fixture.course.id}/chapters`);
  await page.route(chapterRoute,route=>route.request().method()==='POST'?route.fulfill({status:500,contentType:'application/json',body:JSON.stringify({code:'test_chapter_failure',message:'Simulated chapter failure',data:{status:500}})}):route.continue());
  await page.locator('.ohmylms-more-options-dropdown button').click();
  await page.getByRole('menuitem',{name:'Save as Draft',exact:true}).click();
  await expect(page.getByRole('alert').filter({hasText:'chapters could not be saved'})).toBeVisible();
  expect(Number(await page.evaluate(()=>wp.data.select('ohmylms/store').getCourse().regular_price))).toBe(120);
  await page.unroute(chapterRoute);
  await page.getByRole('button',{name:/3 Preview/}).click();
  await expect(page.getByText('Review Course Summary',{exact:true})).toBeVisible();
  const published=page.waitForResponse(response=>response.url().includes(`/courses/${fixture.course.id}/chapters`)&&response.request().method()==='POST');
  await page.getByRole('button',{name:'Publish',exact:true}).click();expect((await published).ok()).toBe(true);
  await expect(page.getByText('Course has been published successfully').first()).toBeVisible();
  await page.reload();
  await expect(page.getByText('Review Course Summary',{exact:true})).toBeVisible();
  const finalCourse=await page.evaluate(id=>wp.apiFetch({path:`/ohmylms/v1/courses/${id}`}),fixture.course.id);
  expect(finalCourse.status).toBe('publish');expect(finalCourse.price_type).toBe('paid');
  expect(Number(finalCourse.regular_price)).toBe(120);expect(Number(finalCourse.sale_price)).toBe(90);
  expect(errors).toEqual([]);
 }finally{
  await testInfo.attach('course-errors',{body:JSON.stringify(errors),contentType:'application/json'});
  await page.evaluate(async({course,chapter,lesson})=>{
   for(const [route,id] of [['lessons',lesson.id],['chapters',chapter.id],['courses',course.id]])await wp.apiFetch({path:`/ohmylms/v1/${route}/${id}`,method:'DELETE'});
  },fixture);
 }
});
