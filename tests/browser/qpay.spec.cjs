const {test, expect} = require('@playwright/test');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
// The `ohmylms-checkout` registry shim, then the gateway contract of assets/interactivity/checkout.js
// (resume( form ) before posting, open( response, form ) for a pending answer), without jQuery.
const registry = 'window.PaymentGatewayHandlers=window.PaymentGatewayHandlers||{};window.registerPaymentGatewayHandler=window.registerPaymentGatewayHandler||function(id,handler){window.PaymentGatewayHandlers[id]=handler;};';
const checkout = `document.getElementById('ohmylms-checkout-form').addEventListener('submit', async (event) => {
  event.preventDefault();
  const form = event.currentTarget;
  const handler = window.PaymentGatewayHandlers[form.querySelector('[name="payment_method"]:checked').value];
  if (handler?.resume?.(form)) return;
  const response = await (await fetch(window.ohmylms_checkout_params.ajax_url, {method: 'POST', body: new URLSearchParams(new FormData(form))})).json();
  if ('pending' === response.payment_status) handler?.open?.(response, form);
  else if (response.redirect) window.location.assign(response.redirect);
});`;
const i18n = {title:'Pay with QPay',waiting:'Waiting for payment confirmation',paused:'Checking paused; order pending',close:'Close',resume:'Resume QPay payment',check:'Resume checking',banks:'Or open your bank app:',error:'Please resume checking',paid:'Payment confirmed'};
const png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aB3sAAAAASUVORK5CYII=';

async function setup(page) {
  const calls = {checkout:0,resume:0,poll:0}; let paid = false;
  const pending = {result:'success',payment_status:'pending',payment_method:'qpay',order_id:123,payment_token:'test-capability',qr_image:png,urls:[{description:'Khan Bank',link:'khanbank://payment/test'},{description:'Unsafe',link:'javascript:alert(1)'},{description:'<img onerror=alert(1)>',link:'bankapp://payment'}]};
  await page.route('http://qpay.test/**', async route => {
    if (route.request().url().endsWith('/admin-ajax.php')) {
      const data = new URLSearchParams(route.request().postData());
      let json;
      if (data.get('action') === 'ohmylms_checkout') { calls.checkout++; json = pending; }
      else {
        expect(data.get('payment_token')).toBe('test-capability');
        if (data.get('action') === 'ohmylms_qpay_resume') { calls.resume++; json={success:true,data:pending}; }
        else { calls.poll++; json={success:true,data:paid?{status:'paid',redirect_url:'http://qpay.test/thanks'}:{status:'pending'}}; }
      }
      await route.fulfill({json}); return;
    }
    await route.fulfill({contentType:'text/html',body:`<!doctype html><html><body><div class="ohmylms-notices-wrapper"></div><form class="checkout ohmylms-checkout-form" id="ohmylms-checkout-form"><label><input type="radio" name="payment_method" value="qpay" checked>QPay</label><input name="action" value="ohmylms_checkout" type="hidden"><input name="email" value="student@example.invalid"><input name="account_password" type="password" value="must-not-be-stored"><button class="ohmylms-place-order-button" type="submit">Place order<span class="ohmylms-loader"></span></button></form></body></html>`});
  });
  await page.goto('http://qpay.test/checkout');
  await page.evaluate(i18n => { window.ohmylms_checkout_params={ajax_url:'http://qpay.test/admin-ajax.php'}; window.ohmylms_qpay_params={ajax_url:'http://qpay.test/admin-ajax.php',nonce:'nonce',i18n}; }, i18n);
  await page.addStyleTag({path:path.join(root,'packages/e-commerce/assets/css/qpay-checkout.css')});
  await page.addScriptTag({content:registry});
  await page.addScriptTag({content:checkout});
  await page.addScriptTag({path:path.join(root,'packages/e-commerce/assets/js/qpay-checkout.js')});
  expect(await page.evaluate(() => typeof window.jQuery)).toBe('undefined');
  return {calls,setPaid:()=>{paid=true;}};
}

for (const width of [1280,390]) test(`QPay native checkout, safe resume and QR layout at ${width}px`, async ({page}) => {
  await page.setViewportSize({width,height:850});
  const errors=[]; page.on('pageerror',e=>errors.push(e.message));
  const {calls,setPaid}=await setup(page);
  await page.getByRole('button',{name:'Place order'}).click();
  const modal=page.getByRole('dialog'); await expect(modal).toBeVisible();
  await expect(page.getByRole('link',{name:'Khan Bank'})).toHaveAttribute('href','khanbank://payment/test');
  await expect(page.getByRole('link',{name:'Unsafe',exact:true})).toHaveCount(0);
  expect(await page.locator('.ohmylms-qpay-banks img').count()).toBe(0);
  const box=await modal.boundingBox(); expect(box.x).toBeGreaterThanOrEqual(0); expect(box.x+box.width).toBeLessThanOrEqual(width);
  expect(calls.checkout).toBe(1); expect(page.url()).toContain('/checkout');
  const stored=await page.evaluate(()=>sessionStorage.getItem('ohmylms.qpay./checkout'));
  expect(stored).not.toContain('student@example.invalid'); expect(stored).not.toContain('must-not-be-stored');
  await page.screenshot({path:path.join(root,`test-results/qpay-${width}.png`),fullPage:true});
  await page.getByRole('button',{name:'Close',exact:true}).click(); await expect(modal).not.toBeVisible();
  await page.getByRole('button',{name:'Place order'}).click(); await expect(modal).toBeVisible();
  await expect.poll(()=>calls.resume).toBe(1); expect(calls.checkout).toBe(1);
  setPaid();
  await expect(page).toHaveURL('http://qpay.test/thanks',{timeout:15000}); expect(errors).toEqual([]);
});

test('QR close stops polling and leaves a resumable order',async({page})=>{
  const {calls}=await setup(page);
  await page.getByRole('button',{name:'Place order'}).click(); await expect(page.getByRole('dialog')).toBeVisible();
  await page.getByRole('button',{name:'Close',exact:true}).click(); const count=calls.poll;
  await page.waitForTimeout(3300); expect(calls.poll).toBe(count);
  await page.getByRole('button',{name:'Resume QPay payment',exact:true}).click();
  await expect(page.getByRole('dialog')).toBeVisible(); expect(calls.checkout).toBe(1);
});

test('unpaid polling timeout pauses checking without reporting an expired invoice',async({page})=>{
  const {calls}=await setup(page);
  await page.clock.install();
  await page.getByRole('button',{name:'Place order'}).click();
  await expect(page.getByRole('dialog')).toBeVisible();
  await expect.poll(()=>calls.poll).toBeGreaterThan(0);
  await page.clock.runFor(100);
  await page.clock.setSystemTime(Date.now()+301000);
  await page.clock.runFor(3100);
  await expect(page.getByRole('status')).toHaveText(i18n.paused);
  expect(page.url()).toContain('/checkout');
  await expect(page.getByRole('button',{name:'Resume checking',exact:true})).toBeEnabled();
});

test('explicit payment check recovers a paid invoice without a callback',async({page})=>{
  const {calls}=await setup(page);
  await page.getByRole('button',{name:'Place order'}).click();
  await expect(page.getByRole('dialog')).toBeVisible();
  await page.route('http://qpay.test/admin-ajax.php',async route=>{
    const data=new URLSearchParams(route.request().postData());
    if(data.get('action')!=='ohmylms_qpay_resume') { await route.fallback(); return; }
    expect(data.get('payment_token')).toBe('test-capability');
    await route.fulfill({json:{success:true,data:{status:'paid',redirect_url:'http://qpay.test/thanks'}}});
  });
  await page.getByRole('button',{name:'Resume checking',exact:true}).click();
  await expect(page).toHaveURL('http://qpay.test/thanks');
  expect(calls.checkout).toBe(1);
});

test('other gateways still follow their redirect response',async({page})=>{
  await setup(page);
  await page.evaluate(()=>{document.querySelector('[name="payment_method"]').value='offline';});
  await page.route('http://qpay.test/admin-ajax.php',route=>route.fulfill({json:{result:'success',redirect:'http://qpay.test/thanks'}}));
  await page.getByRole('button',{name:'Place order'}).click();
  await expect(page).toHaveURL('http://qpay.test/thanks');
});
