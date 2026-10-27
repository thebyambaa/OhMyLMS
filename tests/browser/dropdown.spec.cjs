const {test,expect}=require('@playwright/test');
test('native multiple selections restore, autosave and submit to the server grader',async({page})=>{
 const saved=[];page.on('request',req=>{if(req.url().endsWith('/attempt/responses'))saved.push(JSON.parse(req.postData()));});
 await page.goto('/fixture');
 const field=page.locator('form select[multiple]');
 await expect.poll(()=>field.evaluate(node=>Array.from(node.selectedOptions,option=>option.value))).toEqual(['positive']);
 await expect(page.locator('form select:not([multiple])')).toHaveValue('up');
 await field.selectOption(['positive','rising']);
 await expect.poll(()=>saved.length).toBeGreaterThan(0);
 expect(saved.at(-1).response).toEqual({'1':['positive','rising'],'2':'up'});
 const fields=await page.locator('form').evaluate(form=>Array.from(new FormData(form).entries()));
 expect(fields.filter(([name])=>name==='attempt[1][quiz_question][1][1][]').map(([,value])=>value).sort()).toEqual(['positive','rising']);
 await page.getByRole('button',{name:'Submit',exact:true}).click();
 const result=JSON.parse(await page.locator('body').innerText());
 expect(result.correct).toBe(true);expect(result.fraction).toBe(1);
});
test('practice collects multiple selections and keeps another question independent',async({page})=>{
 await page.goto('/fixture');
 await page.locator('#practice select[multiple]').selectOption(['positive','rising']);
 await page.locator('#practice select:not([multiple])').selectOption('down');
 const response=await page.evaluate(()=>window.OhMyLMSInteractive.collect(document.querySelector('#practice')));
 response['1'].sort();
 expect(response).toEqual({'1':['positive','rising'],'2':'down'});
 await expect.poll(()=>page.locator('form select[multiple]').evaluate(node=>Array.from(node.selectedOptions,option=>option.value))).toEqual(['positive']);
});
test('mobile multiple select retains accessible labels and selected values',async({browser})=>{
 const context=await browser.newContext({viewport:{width:390,height:844},hasTouch:true,isMobile:true});
 const page=await context.newPage();await page.goto('http://127.0.0.1:8112/fixture');
 const field=page.locator('form select[multiple]');
 await expect(field).toHaveAttribute('aria-label','Choice 1');
 await field.selectOption(['rising']);await field.focus();await field.press('Tab');
 expect(await field.evaluate(node=>Array.from(node.selectedOptions,option=>option.value))).toEqual(['rising']);
 await context.close();
});
