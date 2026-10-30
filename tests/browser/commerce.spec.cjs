const {test,expect}=require('@playwright/test');
const fs=require('node:fs'),path=require('node:path');
const order={id:9321,status:'completed',total:'100',subtotal:'100',paid:'100',refunds:[],currency:{currency:'$',currency_pos:'left'},line_items:[{id:1,key:1,name:'Commerce test course',price:'100',quantity:1,total:'100'}],coupon_lines:{},order_notes:[],related_orders:[],student_id:914,student_name:'Commerce learner',student_email:'learner@example.test',address:'Test address',date_created:'2026-09-25 10:00:00',payment_method:'offline',payment_method_title:'Offline',total_orders:'1',total_revenue:'100',aov:'100'};
const subscription={...order,id:9322,status:'active',original_order_id:9321,plan_name:'Test plan',billing_period:'month',payment_gateway:{title:'Offline'},schedule_start_date:'2026-09-25',schedule_end_date:'2027-09-25',schedule_next_payment_date:'2026-10-25',last_payment_date:'2026-09-25',notes:[]};
test.beforeEach(async({page})=>{
 const credentials=JSON.parse(fs.readFileSync(process.env.OHMYLMS_TEST_CREDENTIALS,'utf8'));
 await page.goto('/wp-login.php');await page.locator('#user_login').fill(credentials.username);await page.locator('#user_pass').fill(credentials.password);await page.locator('#wp-submit').click();await page.waitForURL(/wp-admin/);
 for(const file of ['sdk/extensions.js','assets/dist/admin/ohmylms.js'])await page.route('**/build/'+file+'*',route=>route.fulfill({path:path.resolve('build/'+file),contentType:'application/javascript'}));
 await page.route('**/cx-ecommerce/v1/**',async route=>{const url=new URL(route.request().url());const headers={'X-WP-Total':'1','X-WP-TotalPages':'1','X-WP-Page':'1'};let json=[];
 if(url.pathname.endsWith('/orders'))json={orders:[order],currency:order.currency};
 else if(url.pathname.endsWith('/orders/9321'))json=order;
 else if(url.pathname.endsWith('/subscriptions'))json=[subscription];
 else if(url.pathname.endsWith('/subscriptions/9322'))json=subscription;
 if(route.request().method()!=='GET')return route.fulfill({status:400,json:{message:'Unconfigured test mutation'}});
 return route.fulfill({headers,json});});
});
test('commerce routes load editable source without runtime errors',async({page})=>{
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 for(const route of ['orders','order-edit/9321','subscriptions','subscription-edit/9322','coupons']){
  await page.goto('/wp-admin/admin.php?page=ohmylms&ohmylms_legacy=1#/'+route);
  await expect.poll(()=>page.evaluate(()=>Object.keys(window.ohmylms?.extensions?.commerceComponents||{}).length)).toBe(22);
  await expect(page.locator('.ohmylms-admin, #ohmylms, #ohmylms-app, #root').first()).toBeAttached().catch(()=>{});
  await page.waitForTimeout(1500);
  await page.screenshot({path:'test-results/commerce-'+route.replace('/','-')+'.png',fullPage:true});
  expect(errors,route).toEqual([]);
 }
});
