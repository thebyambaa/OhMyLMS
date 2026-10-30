const { test, expect } = require('@playwright/test');
const fs = require('node:fs');
test('automatic text, language tiles and matching study blocks play and resume', async ({page,baseURL})=>{
 test.skip(baseURL!=='http://127.0.0.1:8100','Requires disposable migration clone');
 const credentials=JSON.parse(fs.readFileSync(process.env.OHMYLMS_TEST_CREDENTIALS,'utf8'));
 await page.goto('/wp-login.php'); await page.locator('#user_login').fill(credentials.username); await page.locator('#user_pass').fill(credentials.password); await page.locator('#wp-submit').click(); await page.waitForURL(u=>!u.pathname.endsWith('wp-login.php'));
 await page.goto('/wp-admin/admin.php?page=ohmylms-app'); await page.waitForFunction(()=>Boolean(window.wp?.apiFetch));
 const fixture=await page.evaluate(async()=>{
  const api=async(path,data,method='POST')=>{try{return await wp.apiFetch({path,method,data});}catch(e){throw new Error(JSON.stringify(e));}};
  const question=async(settings,questions=[])=>{const q=await api('/ohmylms/v1/question',{name:'Wordwall browser fixture',quiz_id:0,settings:{...settings,required:true,score:{enabled:true,value:1}},questions});await api(`/ohmylms/v1/question-bank/${q.id}/approve`,{});return q.id;};
  const text=await question({type:'text-entry',accepted:['үүл'],case_sensitive:false,collapse_whitespace:true});
  const tiles=await question({type:'build-expression',tile_mode:'letters',correct:['ү','ү','л'],distractors:['а'],alternatives:[],form:'any',equivalence:false});
  const match=await question({type:'matching'},[{answer:'Cloud',matching_data:{label:'Hidden cloud back'}},{answer:'Rain',matching_data:{label:'Hidden rain back'}}]);
  const pages=[];
  for(const [mode,ids] of [['standard',[text,tiles]],['flashcards',[match]],['memory-pairs',[match]]]){
   const p=await api('/wp/v2/pages',{title:`Wordwall ${mode} browser fixture`,status:'publish',content:`<!-- wp:ohmylms/question-player ${JSON.stringify({source:'selected',questionIds:ids,activityMode:mode,graded:true,affectsSkills:false})} /-->`});pages.push({id:p.id,mode});
  }
  return {pages,questions:[text,tiles,match]};
 });
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 const open=async mode=>{await page.goto(`/?page_id=${fixture.pages.find(p=>p.mode===mode).id}`);await page.getByRole('button',{name:'Start questions',exact:true}).click();};
 await open('standard');
 await page.getByRole('textbox',{name:'Your answer',exact:true}).fill('ҮҮЛ');
 await page.getByRole('button',{name:'Next',exact:true}).click();
 const bank=page.locator('.ohmylms-build-bank');
 await bank.getByRole('button',{name:'ү',exact:true}).first().click();await bank.getByRole('button',{name:'ү',exact:true}).first().click();await bank.getByRole('button',{name:'л',exact:true}).click();
 await page.getByRole('button',{name:'Submit responses',exact:true}).click();await expect(page.getByText('Score: 2 / 2',{exact:true})).toBeVisible();
 await open('flashcards');
 await expect(page.getByText('Hidden cloud back',{exact:true})).toHaveCount(0);
 await page.getByRole('button',{name:'Cloud',exact:true}).click();await expect(page.getByText('Hidden cloud back',{exact:true})).toBeVisible();
 await page.reload();await expect(page.getByText('Hidden cloud back',{exact:true})).toBeVisible();
 await page.getByRole('button',{name:'Rain',exact:true}).click();await expect(page.getByText('Study activity complete. No academic score was recorded.',{exact:true})).toBeVisible();
 await page.locator('.ohmylms-question-player').screenshot({path:'test-results/wordwall-flashcards.png'});
 await open('memory-pairs');
 const cards=page.locator('.ohmylms-study-cards button');await expect(cards).toHaveCount(4);
 await cards.first().click();await expect(page.locator('.ohmylms-study-cards button[aria-pressed="true"]')).toHaveCount(1);
 await page.reload();await expect(page.locator('.ohmylms-study-cards button[aria-pressed="true"]')).toHaveCount(1);
 expect(errors).toEqual([]);
 await page.goto(`/wp-admin/post.php?post=${fixture.questions[0]}&action=edit`);
 const canvas=page.frameLocator('iframe[name="editor-canvas"]');
 await expect(canvas.getByLabel('Accepted answers (one per line)',{exact:true})).toHaveValue('үүл',{timeout:30000});
 await page.goto(`/wp-admin/post.php?post=${fixture.questions[1]}&action=edit`);
 await expect(canvas.getByLabel('Build from',{exact:true})).toHaveValue('letters',{timeout:30000});
 await page.goto('/wp-admin/admin.php?page=ohmylms-app');await page.waitForFunction(()=>Boolean(window.wp?.apiFetch));
 await page.evaluate(async fixture=>{for(const p of fixture.pages)await wp.apiFetch({path:`/wp/v2/pages/${p.id}?force=true`,method:'DELETE'});for(const id of fixture.questions)await wp.apiFetch({path:`/ohmylms/v1/question/${id}`,method:'DELETE'});},fixture);
});
