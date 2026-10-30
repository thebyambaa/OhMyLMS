const {test,expect}=require('@playwright/test');
const fs=require('node:fs');const path=require('node:path');
const graph={'2026-09-01':{earning:120,refund:10,net:110},'2026-09-02':{earning:40,refund:0,net:40}};
const course={id:912,name:'React analytics course',title:'React analytics course',price:'120',total_sales_count:3,total_enrolled_students:12,sales_growth_rate:0,date:'2026-09-01'};
const student={id:914,student_id:914,name:'Analytics learner',email:'learner@example.test',skipped_quizzes:[],skipped_assignments:[],completion_rate:50,start_date:'2026-09-01'};
const courseReport={title:course.title,content_data:{total_enrollment:12,completed_students:3,in_progress_students:9,ratings:4.8,chapters:2,lessons:4,quizzes:1,assignments:1},earning:{currency:'$',currency_pos:'left',total_earning:160,total_refund:10,net_amount:150,graph_data:graph},students:[student]};
const earnings={earning_graph:{total_revenue:160,total_refund:10,net_amount:150,growth:{total_revenue:10,total_refund:0,net_amount:10},graph_data:graph},order_by_country:{Mongolia:150},transactions:[],count_unchecked_orders:2,currency:'$',currency_pos:'left'};
test.beforeEach(async({page})=>{
 const credentials=JSON.parse(fs.readFileSync(process.env.OHMYLMS_TEST_CREDENTIALS,'utf8'));
 await page.goto('/wp-login.php');await page.locator('#user_login').fill(credentials.username);await page.locator('#user_pass').fill(credentials.password);await page.locator('#wp-submit').click();await page.waitForURL(/wp-admin/);
 for(const file of ['sdk/extensions.js','assets/dist/admin/ohmylms.js'])await page.route('**/build/'+file+'*',route=>route.fulfill({path:path.resolve('build/'+file),contentType:'application/javascript'}));
});
test('dashboard source renders data, opens course creation and recovers after request failure',async({page})=>{
 const errors=[];page.on('pageerror',error=>errors.push(error.message));let fail=false;
 await page.route(/\/dashboard\?/,route=>route.fulfill(fail?{status:503,json:{message:'Simulated failure'}}:{json:{currency:'$',currency_pos:'left',earning:{total_earning:160,refund:10,net_income:150,growth:{earning:0,refund:0,net:0}},recent_courses:[course],top_course:null,total_course:1,course_sold:3,total_enrollments:12,sales_growth_rate:0,enrollment_growth_rate:0}}));
 await page.goto('/wp-admin/admin.php?page=ohmylms&ohmylms_legacy=1#/dashboard');
 await expect.poll(()=>page.evaluate(()=>Object.keys(window.ohmylms?.extensions?.analyticsComponents||{}).length)).toBe(18);
 await expect(page.getByRole('link',{name:course.name,exact:true})).toBeVisible();
 await page.getByRole('button',{name:'Add Course',exact:true}).first().click();await expect(page.getByRole('dialog')).toBeVisible();await page.keyboard.press('Escape');
 fail=true;await page.reload();await expect(page.getByRole('button',{name:'Go to all orders',exact:true})).toBeVisible();
 fail=false;await page.reload();await expect(page.getByRole('link',{name:course.name,exact:true})).toBeVisible();
 expect(errors).toEqual([]);
 await page.screenshot({path:'test-results/analytics-dashboard.png',fullPage:true});
});
test('course and earnings reports render charts, filter requests and keep navigation',async({page})=>{
 const errors=[];page.on('pageerror',error=>errors.push(error.message));const courseRequests=[],earningRequests=[];
 await page.route('**/analytics/course/912*',route=>{courseRequests.push(route.request().url());return route.fulfill({json:courseReport});});
 await page.route('**/analytics/earnings*',route=>{earningRequests.push(route.request().url());return route.fulfill({json:earnings});});
 await page.goto('/wp-admin/admin.php?page=ohmylms&ohmylms_legacy=1#/course/912/report');
 await expect(page.getByText('Journey Mapping',{exact:true})).toBeVisible();await expect(page.getByText(student.name,{exact:true})).toBeVisible();
 await expect(page.locator('canvas').first()).toBeVisible();
 await page.getByPlaceholder('Search',{exact:true}).fill('Analytics');
 await expect.poll(()=>courseRequests.some(url=>url.includes('search=Analytics')&&url.includes('data_type=student'))).toBe(true);
 await page.screenshot({path:'test-results/analytics-course-report.png',fullPage:true});
 await page.goto('/wp-admin/admin.php?page=ohmylms&ohmylms_legacy=1#/earnings-report');
 await expect(page.getByRole('heading',{name:'Earnings Report',exact:true})).toBeVisible();await expect(page.getByText('Mongolia',{exact:true})).toBeVisible();
 await expect(page.locator('canvas').first()).toBeVisible();
 await page.locator('.ohmylms-dashboard-filter select').selectOption('current_year');
 await expect.poll(()=>earningRequests.some(url=>url.includes('filter=current_year')&&url.includes('data_type=earning'))).toBe(true);
 await page.screenshot({path:'test-results/analytics-earnings-report.png',fullPage:true});
 await page.getByRole('button',{name:'Review Orders',exact:true}).click();await expect(page).toHaveURL(/#\/orders/);expect(errors).toEqual([]);
});

