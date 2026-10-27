const {test,expect}=require('@playwright/test');
const fs=require('node:fs');
const path=require('node:path');
test.beforeEach(async({page})=>{
 const credentials=JSON.parse(fs.readFileSync(process.env.OHMYLMS_TEST_CREDENTIALS,'utf8'));
 await page.goto('/wp-login.php');await page.locator('#user_login').fill(credentials.username);await page.locator('#user_pass').fill(credentials.password);await page.locator('#wp-submit').click();await page.waitForURL(/wp-admin/);
 for(const file of ['sdk/extensions.js','assets/dist/admin/ohmylms.js'])await page.route('**/build/'+file+'*',route=>route.fulfill({path:path.resolve('build/'+file),contentType:'application/javascript'}));
 await page.goto('/wp-admin/admin.php?page=ohmylms');
 await expect.poll(()=>page.evaluate(()=>Object.keys(window.ohmylms?.extensions?.learningComponents||{}).length)).toBe(25);
});

test('learning lists mount from editable React source',async({page})=>{
 const errors=[];page.on('pageerror',error=>errors.push(error.message));
 for(const [route,label] of [['quizzes','All Quizzes'],['assignments','All Assignments'],['sessions','Sessions']]){
  await page.goto('/wp-admin/admin.php?page=ohmylms#/'+route);
  await expect(page.getByText(label,{exact:true}).last()).toBeVisible();
 }
 expect(errors).toEqual([]);
});

test('assignment editor persists title changes',async({page})=>{
 const errors=[];page.on('pageerror',error=>errors.push(error.message));
 for(const kind of ['assignment']){
  const item=await page.evaluate(async kind=>wp.apiFetch({path:`/ohmylms/v1/${kind === 'lesson' ? 'lessons' : 'assignment'}`,method:'POST',data:{name:`React ${kind} fixture`,type:'text',description:'Learning fixture body',status:'draft',total_points:100,maximum_pass_points:50,time_limit:0,number_of_files:1,max_file_size_limit:10}}),kind);
  try{
   await page.goto(`/wp-admin/admin.php?page=ohmylms#/${kind}-edit/${item.id}`);
   const title=page.getByPlaceholder(`Enter ${kind} title`);
   await expect(title).toHaveValue(`React ${kind} fixture`);
   await title.fill(`Saved React ${kind}`);
   const saved=page.waitForResponse(response=>response.url().includes(`/${kind === 'lesson' ? 'lessons' : 'assignment'}/${item.id}`)&&response.request().method()==='POST');
   await page.getByRole('button',{name:'Save',exact:true}).click();expect((await saved).ok()).toBe(true);
   await page.reload();await expect(title).toHaveValue(`Saved React ${kind}`);
  }finally{await page.evaluate(({kind,id})=>wp.apiFetch({path:`/ohmylms/v1/${kind === 'lesson' ? 'lessons' : 'assignment'}/${id}`,method:'DELETE'}),{kind,id:item.id});}
 }
 expect(errors).toEqual([]);
});

test('assignment grading loads prior marks and submits edited score',async({page})=>{
 const errors=[];page.on('pageerror',error=>errors.push(error.message));
 let posted;
 const report={report:[{display_name:'Learning Test Student',submissions:[{id:17,score:20,note:'Previous note',status:'submitted',submitted_date:'2026-09-24 10:00:00',content:'Submitted work'}]}],additional_data:{assignment_name:'Learning Assignment',course_name:'Learning Course',total_marks:100,pass_marks:50}};
 await page.route('**/assignment/987/report/123*',async route=>{
  if(route.request().method()==='POST'){posted=route.request().postDataJSON();Object.assign(report.report[0].submissions[0],posted[0]);}
  await route.fulfill({json:report});
 });
 await page.goto('/wp-admin/admin.php?page=ohmylms#/assignment-report/987/grade-assignment/123');
 await expect(page.getByRole('spinbutton')).toHaveValue('20');
 await expect(page.getByText('Submitted work',{exact:true})).toBeVisible();
 await page.getByRole('spinbutton').fill('75');
 await page.getByRole('button',{name:'Upgrade Grade',exact:true}).click();
 await expect.poll(()=>posted?.[0]?.score).toBe(75);
 expect(posted[0]).toMatchObject({id:17,score:75,note:'Previous note',status:'passed'});
 expect(errors).toEqual([]);
});

