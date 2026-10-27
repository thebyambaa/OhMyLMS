const {test,expect}=require('@playwright/test');
const fs=require('node:fs');
const path=require('node:path');
test('gamification React panels mount and track direct routes',async({page})=>{
 const errors=[];page.on('pageerror',error=>errors.push(error.message));
 const credentials=JSON.parse(fs.readFileSync(process.env.OHMYLMS_TEST_CREDENTIALS,'utf8'));
 await page.goto('/wp-login.php');
 await page.locator('#user_login').fill(credentials.username);
 await page.locator('#user_pass').fill(credentials.password);
 await page.locator('#wp-submit').click();await page.waitForURL(/wp-admin/);
 // Exercise the current workspace builds even when the isolated PHP fixture uses a copied plugin.
 await page.route('**/build/sdk/extensions.js*',route=>route.fulfill({path:path.resolve('build/sdk/extensions.js'),contentType:'application/javascript'}));
 await page.route('**/build/assets/dist/admin/ohmylms.js*',route=>route.fulfill({path:path.resolve('build/assets/dist/admin/ohmylms.js'),contentType:'application/javascript'}));
 await page.goto('/wp-admin/admin.php?page=ohmylms#/gamification/point-settings');
 await expect.poll(()=>page.evaluate(()=>Object.keys(window.ohmylms?.extensions?.gamificationComponents||{}).length)).toBe(13);
 for(const [key,label] of [['point-settings','Bonus Point'],['badge-settings','Achievement Badges'],['level-settings','Learner Levels'],['reward-settings','Reward'],['leaderboard-settings','Leaderboard'],['streak-settings','Streaks']]){
   await page.goto('/wp-admin/admin.php?page=ohmylms#/gamification/'+key);
   await expect(page.getByRole('tab',{name:label,exact:true})).toHaveAttribute('aria-selected','true');
 }
 await expect(page.getByRole('heading',{name:'Daily learning streaks',exact:true})).toBeVisible();
 await expect(page.getByLabel('Minimum practice answers')).toHaveValue('3');
 await page.getByRole('button',{name:'Save streak settings',exact:true}).click();
 await expect(page.getByRole('tabpanel',{name:'Streaks',exact:true}).getByText('Streak settings saved.',{exact:true})).toBeVisible();
 for (const [tab,button] of [['Learner Levels','Add Level'],['Achievement Badges','Add Badge']]) {
   await page.getByRole('tab',{name:tab,exact:true}).click();
   await page.getByRole('button',{name:button,exact:true}).click();
   await expect(page.getByRole('heading',{name:'Achievement earning rules',exact:true})).toBeVisible();
   const condition=page.getByRole('combobox').first();
   await condition.selectOption('completed_lesson');
   await expect(condition).toHaveValue('completed_lesson');
   await condition.selectOption('completed_courses');
   await expect(condition).toHaveValue('completed_courses');
   await page.getByRole('button',{name:'Cancel',exact:true}).click();
 }
 await page.getByRole('tab',{name:'Bonus Point',exact:true}).click();
 await expect(page).toHaveURL(/gamification\/point-settings/);
 expect(errors).toEqual([]);
});

/**
 * Certificates lives in the Gamification screen while the add-on is on. The fixture switches the add-on
 * on the disposable site; set OHMYLMS_PHP (and OHMYLMS_PHP_INI if the PHP build needs one) for it.
 */
const {execFileSync}=require('node:child_process');
const php=process.env.OHMYLMS_PHP||'php';
const ini=process.env.OHMYLMS_PHP_INI?['-c',process.env.OHMYLMS_PHP_INI]:[];
const fixture=(...args)=>{
  const output=execFileSync(php,[...ini,'-d','display_startup_errors=0','-d','error_reporting=22527','tests/php/gamification-browser-fixture.php',...args],{env:process.env,encoding:'utf8',timeout:120000});
  return JSON.parse(output.trim().split('\n').filter((line)=>line.startsWith('{')).pop());
};
const hash=(route)=>`/wp-admin/admin.php?page=ohmylms#${route}`;
const settingsTabs=['Bonus Point','Achievement Badges','Learner Levels','Reward','Leaderboard','Streaks'];

test('Certificates is a Gamification tab while Gamification is on, and keeps its own menu entry while it is off',async({page})=>{
 const errors=[];page.on('pageerror',error=>errors.push(error.message));
 const credentials=JSON.parse(fs.readFileSync(process.env.OHMYLMS_TEST_CREDENTIALS,'utf8'));
 const before=fixture('on');
 let certificate=0;
 const submenu=page.locator('#toplevel_page_ohmylms .wp-submenu li');
 // A flag set on the server needs a page load, and a hash change alone is not one.
 const open=async(route)=>{await page.goto('/wp-admin/index.php');await page.goto(hash(route));};
 try {
  await page.goto('/wp-login.php');
  await page.locator('#user_login').fill(credentials.username);
  await page.locator('#user_pass').fill(credentials.password);
  await page.locator('#wp-submit').click();await page.waitForURL(/wp-admin/);

  // The old address opens the tab, which is the seventh tab of Gamification.
  await open('/certificates');
  await expect(page).toHaveURL(/#\/gamification\/certificates$/,{timeout:30000});
  await expect(page.getByRole('tab')).toHaveText([...settingsTabs,'Certificates']);
  await expect(page.getByRole('tab',{name:'Certificates',exact:true})).toHaveAttribute('aria-selected','true');
  await expect(page.getByRole('heading',{name:'All Certificates',exact:true})).toBeVisible();
  // There is no Certificates entry in the admin menu, and Gamification stays highlighted.
  await expect(submenu.filter({hasText:/^Gamification$/})).toHaveClass(/current/);
  await expect(submenu.filter({hasText:/^Certificates$/})).toHaveCount(0);

  // The other tabs are unchanged and do not show the certificates.
  await page.getByRole('tab',{name:'Bonus Point',exact:true}).click();
  await expect(page).toHaveURL(/#\/gamification\/point-settings$/);
  await expect(page.getByRole('heading',{name:'All Certificates',exact:true})).toHaveCount(0);
  await expect(submenu.filter({hasText:/^Gamification$/})).toHaveClass(/current/);
  await page.getByRole('tab',{name:'Certificates',exact:true}).click();
  await expect(page).toHaveURL(/#\/gamification\/certificates$/);
  await expect(page.getByRole('heading',{name:'All Certificates',exact:true})).toBeVisible();

  // Settings → Gamification keeps its six tabs.
  await page.goto(hash('/settings/gamification-settings/point-settings'));
  await expect(page.getByRole('tab',{name:'Bonus Point',exact:true})).toHaveAttribute('aria-selected','true',{timeout:30000});
  await expect(page.getByRole('tab',{name:'Certificates',exact:true})).toHaveCount(0);

  // The template editor, opened from the tab, keeps Gamification highlighted.
  await page.goto(hash('/gamification/certificates'));
  certificate=await page.evaluate(async()=>(await window.wp.apiFetch({path:'/ohmylms/v1/certificates',method:'POST',data:{name:'Gamification tab check',status:'publish',contents:{},html_contents:''}})).id);
  expect(certificate).toBeGreaterThan(0);
  await page.goto(hash(`/certificate-edit/${certificate}`));
  await expect(submenu.filter({hasText:/^Gamification$/})).toHaveClass(/current/,{timeout:30000});
  await expect(submenu.filter({hasText:/^Certificates$/})).toHaveCount(0);

  // With Gamification off there is no such tab: Certificates keeps its own screen and menu entry.
  fixture('off');
  await open('/certificates');
  await expect(page.getByRole('heading',{name:'All Certificates',exact:true})).toBeVisible({timeout:30000});
  await expect(page).toHaveURL(/#\/certificates$/);
  await expect(page.getByRole('tab',{name:'Certificates',exact:true})).toHaveCount(0);
  await expect(submenu.filter({hasText:/^Certificates$/})).toHaveClass(/current/);
  await expect(submenu.filter({hasText:/^Gamification$/})).toHaveCount(0);
  expect(errors).toEqual([]);
 } finally {
  if (certificate) await page.evaluate(async(id)=>{try{await window.wp.apiFetch({path:`/ohmylms/v1/certificates/${id}`,method:'DELETE'});}catch(e){}},certificate).catch(()=>{});
  fixture('restore',JSON.stringify(before));
 }
});
