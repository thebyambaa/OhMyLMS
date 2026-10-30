const {test,expect}=require('@playwright/test');
const fs=require('node:fs');
const path=require('node:path');
test('Communities React source loads, edits, validates and retries saving',async({page})=>{
 const credentials=JSON.parse(fs.readFileSync(process.env.OHMYLMS_TEST_CREDENTIALS,'utf8'));
 await page.goto('/wp-login.php');await page.locator('#user_login').fill(credentials.username);await page.locator('#user_pass').fill(credentials.password);await page.locator('#wp-submit').click();await page.waitForURL(/wp-admin/);
 for(const file of ['sdk/extensions.js','assets/dist/admin/ohmylms.js'])await page.route('**/build/'+file+'*',route=>route.fulfill({path:path.resolve('build/'+file),contentType:'application/javascript'}));
 let community={id:912,title:'React community fixture',name:'React community fixture',slug:'react-community-fixture',description:'Study together',parent_id:null,visibility:'public',members:4,posts:2};
 let attempts=0;
 await page.route(/\/communities(?:\?|$)/,route=>route.fulfill({json:[community]}));
 await page.route('**/communities/analytics*',route=>route.fulfill({json:{members:{total:4},posts:{total:2},engagement:{total:50}}}));
 await page.route('**/community/spaces/912*',async route=>{
  if(route.request().method()==='GET')return route.fulfill({json:community});
  attempts++;
  if(attempts===1)return route.fulfill({status:503,json:{message:'Simulated save failure'}});
  community={...community,...route.request().postDataJSON()};
  return route.fulfill({json:community});
 });
 await page.route(/\/courses\?search=/,route=>route.fulfill({json:[{id:913,name:'React course fixture',has_community:'no'}]}));
 const errors=[];page.on('pageerror',error=>errors.push(error.message));
 await page.goto('/wp-admin/admin.php?page=ohmylms&ohmylms_legacy=1#/communities');
 await expect.poll(()=>page.evaluate(()=>Object.keys(window.ohmylms?.extensions?.communityComponents||{}).length)).toBe(5);
 await expect(page.getByText('Community Spaces',{exact:true})).toBeVisible();
 await page.getByText('React community fixture',{exact:true}).click();
 const dialog=page.getByRole('dialog');await expect(dialog).toBeVisible();
 const fields=dialog.locator('input[type="text"]');
 await expect(fields.first()).toHaveValue('React community fixture');
 await fields.first().fill('Updated React community');
 const slug=dialog.getByPlaceholder('community-slug');
 await expect(slug).toHaveValue('updated-react-community');
 await slug.fill('bad slug');await expect(dialog.getByRole('button',{name:'Next',exact:true})).toBeDisabled();
 await slug.fill('custom-community');
 await dialog.getByRole('button',{name:'Next',exact:true}).click();
 await expect(dialog.getByRole('button',{name:'Save',exact:true})).toBeVisible();
 await expect(dialog.getByText('Select a course...', {exact:true})).toBeVisible();
 await dialog.getByRole('button',{name:'Save',exact:true}).click();
 await expect.poll(()=>attempts).toBe(1);await expect(dialog).toBeVisible();
 await expect(dialog.getByRole('button',{name:'Save',exact:true})).toBeEnabled();
 await dialog.getByRole('button',{name:'Save',exact:true}).click();
 await expect.poll(()=>attempts).toBe(2);await expect(dialog).not.toBeVisible();
 expect(community.title).toBe('Updated React community');expect(community.slug).toBe('custom-community');
 await page.reload();await page.getByText('React community fixture',{exact:true}).click();
 await expect(page.getByRole('dialog').locator('input[type="text"]').first()).toHaveValue('Updated React community');
 expect(errors).toEqual([]);
});


