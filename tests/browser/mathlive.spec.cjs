const { test, expect } = require('@playwright/test');

test('standard LaTeX renders only in question regions and preserves source fields', async ({page}) => {
 await page.goto('/fixture');
 const text = String.raw`Costs $5 and $10. Use $x^2$, $$\frac{1}{2}$$, \(a+b\) and \[\sqrt{y}\].`;
 await page.evaluate(text => {
  const region = document.createElement('section'); region.id = 'latex-paper'; region.setAttribute('data-ohmylms-latex', '');
  const paragraph = document.createElement('p'); paragraph.textContent = text; region.append(paragraph);
  const source = document.createElement('textarea'); source.value = text; region.append(source);
  const literal = document.createElement('pre'); literal.id = 'outside-latex'; literal.textContent = text;
  document.body.append(region, literal);
 }, text);
 await expect(page.locator('#latex-paper math-field')).toHaveCount(4);
 await expect(page.locator('#latex-paper p')).toContainText('Costs $5 and $10.');
 await expect(page.locator('#latex-paper textarea')).toHaveValue(text);
 await expect(page.locator('#outside-latex math-field')).toHaveCount(0);
 const fields = await page.locator('#latex-paper math-field').evaluateAll(nodes => nodes.map(node => ({value: node.value, readonly: node.readOnly})));
 expect(fields.every(field => field.readonly)).toBe(true);
 expect(fields[1].value).toBe(String.raw`\frac{1}{2}`);
 await page.evaluate(() => {
  const unsafe = document.createElement('p'); unsafe.id = 'unsafe-latex'; unsafe.textContent = String.raw`$\href{https://example.invalid}{x}$ and $z^2$`;
  document.querySelector('#latex-paper').append(unsafe);
 });
 await expect(page.locator('#unsafe-latex math-field')).toHaveCount(1);
 await expect(page.locator('#unsafe-latex')).toContainText(String.raw`$\href{https://example.invalid}{x}$`);
 await expect(page.locator('#unsafe-latex a')).toHaveCount(0);
});

test('visual labels preserve plain contracts, newlines, literal HTML and equations on reopen', async ({ page }) => {
 await page.goto('/fixture');
 const source = 'Price < 5 & tax\n[[ohmylms-math:latex:inline]]\\frac{1}{2}[[/ohmylms-math]]';
 await page.evaluate(value => {
  const editor = document.createElement('div'); editor.id = 'plain-label'; editor.contentEditable = 'true'; editor.textContent = value; document.body.append(editor);
  window.OhMyLMSMath.hydrateAuthor(editor);
 }, source);
 expect(await page.evaluate(() => window.OhMyLMSMath.authorContent(document.querySelector('#plain-label'), false))).toBe(source);
 await page.evaluate(() => {
  const editor = document.querySelector('#plain-label');
  const saved = window.OhMyLMSMath.authorContent(editor, false); editor.textContent = saved; window.OhMyLMSMath.hydrateAuthor(editor);
 });
 await expect(page.locator('#plain-label math-field')).toHaveCount(1);
 expect(await page.evaluate(() => window.OhMyLMSMath.authorContent(document.querySelector('#plain-label'), false))).toBe(source);
 const lines = await page.evaluate(() => {
  const editor = document.createElement('div'); editor.innerHTML = 'First<div>Second<br>Third</div>';
  return window.OhMyLMSMath.authorContent(editor, false);
 });
 expect(lines).toBe('First\nSecond\nThird');
});
test.beforeEach(async ({page})=>{page.on('pageerror',error=>console.log('PAGE ERROR:',error.stack));page.on('console',message=>{if(message.type()==='error')console.log('CONSOLE ERROR:',message.text());});});

test('typing randomized variables into MathLive preserves canonical tokens on reopen', async ({page}) => {
 await page.goto('/fixture');
 await page.evaluate(() => {
  const editor = document.createElement('div'); editor.id = 'typed-variable'; editor.contentEditable = 'true'; document.body.append(editor);
  window.OhMyLMSMath.insertAuthor(editor, '');
 });
 const field = page.locator('#typed-variable math-field');
 await field.click();
 for (const key of ['{', '{', 'a', '}', '}']) await field.press(key);
 const saved = await page.evaluate(() => window.OhMyLMSMath.authorContent(document.querySelector('#typed-variable'), false));
 expect(saved).toContain('{{a}}');
 expect(saved).not.toContain('lbrace');
 await page.evaluate(source => {
  const editor = document.querySelector('#typed-variable'); editor.textContent = source; window.OhMyLMSMath.hydrateAuthor(editor);
 }, saved);
 expect(await page.evaluate(() => window.OhMyLMSMath.authorContent(document.querySelector('#typed-variable'), false))).toBe(saved);
 expect(await field.evaluate(node => node.getPrompts())).toEqual(['omlvar0']);
});

test('real expression template synchronizes native POST, renders markers and grades on PHP', async ({page}) => {
 const requests=[]; page.on('request',request=>requests.push(request.url()));
 await page.goto('/fixture');
 await expect(page.locator('math-field.ohmylms-math-input')).toHaveCount(5);
 await expect(page.locator('#prose')).toContainText('Costs $5.');
 await expect(page.locator('#prose math-field')).toHaveCount(1);
 const answers=[String.raw`\frac{\sqrt{x^{2}+1}}{\frac{1}{2}}`,'2x+6','(x+1)(x-1)',String.raw`\frac{1}{2}`,'x=3'];
 // Use the pinned public value API, then emit the same input event as typing/paste.
 await page.evaluate(answers=>document.querySelectorAll('math-field.ohmylms-math-input').forEach((field,index)=>{field.value=answers[index];field.dispatchEvent(new Event('input',{bubbles:true}));}),answers);
 await expect(page.locator('input[name]').nth(0)).toHaveValue(answers[0]);
 await page.getByRole('button',{name:'Submit',exact:true}).click();
 const result=JSON.parse(await page.locator('body').innerText());
 expect(Object.values(result).every(grade=>grade.correct)).toBe(true);
 expect(requests.some(url=>url.includes('fonts/')&&url.endsWith('.woff2'))).toBe(true);
 expect(requests.every(url=>new URL(url).hostname==='127.0.0.1')).toBe(true);
});

test('restoration, locked random tokens, dynamic questions and cleanup', async ({page}) => {
 await page.goto('/fixture');
 await page.evaluate(()=>{
  const input=document.querySelector('input[name]');input.value='x^{12}';input.form.dispatchEvent(new CustomEvent('ohmylms:answer-restored',{detail:{questionId:1}}));
  const dynamic=document.querySelector('#dynamic');
  dynamic.innerHTML='<input class="ohmylms-expression-input" aria-label="Randomized equation" value="{{a}}x+{{b:+}}={{c}}">';
 });
 await expect(page.locator('math-field.ohmylms-math-input').first()).toHaveJSProperty('value','x^{12}');
 await expect(page.locator('#dynamic math-field')).toHaveCount(1);
 const source=await page.evaluate(()=>{
  const field=document.querySelector('#dynamic math-field');
  field.insert('+1');field.dispatchEvent(new Event('input',{bubbles:true}));
  return document.querySelector('#dynamic input').value;
 });
 expect(source).toContain('{{a}}');expect(source).toContain('{{b:+}}');expect(source).toContain('{{c}}');expect(source).not.toContain('placeholder');
 await page.evaluate(()=>{
  const input=document.querySelector('#dynamic input');
  const cleanup=window.OhMyLMSMath.mountInput(input);cleanup();
  window.OhMyLMSMath.mountInput(input);
 });
 await expect(page.locator('#dynamic math-field')).toHaveCount(1);
 await page.evaluate(()=>document.querySelector('#dynamic').replaceChildren());
 await expect(page.locator('#dynamic math-field')).toHaveCount(0);
});

test('unsupported expressions and wrong answer forms are rejected, plain fallback submits', async ({page})=>{
 await page.goto('/fixture?fallback=1');
 await expect(page.locator('math-field')).toHaveCount(0);
 const answers=[String.raw`\unknown{x}`,'2(x+3)','x^2-1','2/4','x=4'];
 for(let index=0;index<answers.length;index++) await page.locator('input[name]').nth(index).fill(answers[index]);
 await page.getByRole('button',{name:'Submit',exact:true}).click();
 const result=JSON.parse(await page.locator('body').innerText());
 expect(result['1'].reason).toBe('parse');
 expect(result['2'].reason).toBe('form');expect(result['3'].reason).toBe('form');expect(result['4'].reason).toBe('form');
 expect(Object.values(result).every(grade=>!grade.correct)).toBe(true);
});

test('keyboard input and mobile virtual keyboard remain accessible',async({browser})=>{
 const context=await browser.newContext({viewport:{width:390,height:844},hasTouch:true,isMobile:true});
 const page=await context.newPage();await page.goto('http://127.0.0.1:8110/fixture');
 const field=page.locator('math-field.ohmylms-math-input').first();await field.click();await field.press('x');await field.press('+');await field.press('2');
 await expect(page.locator('input[name]').first()).toHaveValue('x+2');
 await expect.poll(()=>page.evaluate(()=>window.mathVirtualKeyboard.visible)).toBe(true);
 await expect(field).toHaveAttribute('aria-label','Your answer');
 await field.press('Tab');
 await context.close();
});

test('author content shows editable equations and serializes original markers on edits and reopen', async ({ page }) => {
 await page.goto('/fixture');
 const original = 'Solve [[ohmylms-math:latex:display]]{{b}}x={{a*b}}[[/ohmylms-math]] costs $5.';
 await page.evaluate(source => {
  const editor = document.createElement('div'); editor.id = 'author'; editor.contentEditable = 'true'; editor.setAttribute('aria-label', 'Question prompt'); editor.textContent = source;
  document.body.append(editor); window.OhMyLMSMath.hydrateAuthor(editor);
 }, original);
 await expect(page.locator('#author math-field')).toHaveCount(1);
 await expect(page.locator('#author')).not.toContainText('[[ohmylms-math:');
 expect(await page.evaluate(() => window.OhMyLMSMath.authorContent(document.querySelector('#author')))).toBe(original);
 const saved = await page.evaluate(() => {
  const editor = document.querySelector('#author'); const field = editor.querySelector('math-field');
  field.insert('+1'); field.dispatchEvent(new Event('input', { bubbles: true }));
  return window.OhMyLMSMath.authorContent(editor);
 });
 expect(saved).toContain('{{b}}'); expect(saved).toContain('{{a*b}}'); expect(saved).not.toContain('math-field'); expect(saved).not.toContain('placeholder');
 await page.evaluate(source => { const editor = document.querySelector('#author'); editor.innerHTML = source; window.OhMyLMSMath.hydrateAuthor(editor); }, saved);
 await expect(page.locator('#author math-field')).toHaveCount(1);
 expect(await page.evaluate(() => window.OhMyLMSMath.authorContent(document.querySelector('#author')))).toBe(saved);
});

test('template runtime and dynamic loader load MathLive only once', async ({ page }) => {
 const errors = []; const requests = [];
 page.on('pageerror', error => errors.push(error.message));
 page.on('request', request => { if (request.resourceType() === 'script' && request.url().endsWith('/math-runtime.js')) requests.push(request.url()); });
 await page.goto('/fixture?loader=1');
 await expect(page.locator('math-field.ohmylms-math-input')).toHaveCount(5);
 await page.locator('math-field.ohmylms-math-input').first().click();
 await page.locator('math-field.ohmylms-math-input').first().press('3');
 await expect(page.locator('input[name]').first()).toHaveValue('3');
 expect(requests).toHaveLength(1); expect(errors).toEqual([]);
});




test('rich text inserts at the caret, toggles display and removes without serializing controls', async ({ page }) => {
 await page.goto('/fixture');
 await page.evaluate(() => {
  const editor = document.createElement('div'); editor.id = 'rich-author'; editor.contentEditable = 'true'; editor.setAttribute('role', 'textbox'); editor.setAttribute('aria-label', 'Prompt editor'); editor.textContent = 'Before after.'; document.body.append(editor);
  const range = document.createRange(); range.setStart(editor.firstChild, 7); range.collapse(true);
  window.OhMyLMSMath.insertAuthor(editor, String.raw`\frac{a}{b}`, range);
 });
 await expect(page.locator('#rich-author math-field')).toHaveCount(1);
 let source = await page.evaluate(() => window.OhMyLMSMath.authorContent(document.querySelector('#rich-author')));
 expect(source).toBe(String.raw`Before [[ohmylms-math:latex:inline]]\frac{a}{b}[[/ohmylms-math]]after.`);
 expect(source).not.toContain('button');
 await page.locator('#rich-author').getByRole('button', { name: 'Display equation', exact: true }).click();
 source = await page.evaluate(() => window.OhMyLMSMath.authorContent(document.querySelector('#rich-author')));
 expect(source).toContain('latex:display');
 await page.locator('#rich-author math-field').click(); await page.locator('#rich-author math-field').press('Tab');
 expect(await page.evaluate(() => document.activeElement.id)).toBe('rich-author');
 await page.locator('#rich-author').getByRole('button', { name: 'Remove equation', exact: true }).click();
 await expect(page.locator('#rich-author math-field')).toHaveCount(0);
 expect(await page.evaluate(() => window.OhMyLMSMath.authorContent(document.querySelector('#rich-author')))).toBe('Before after.');
});

test('empty equation remains editable until source exists, then exits to surrounding prose', async ({ page }) => {
 await page.goto('/fixture');
 await page.evaluate(() => {
  const editor = document.createElement('div'); editor.id = 'empty-author'; editor.contentEditable = 'true'; editor.setAttribute('role', 'textbox'); editor.textContent = 'Solve '; document.body.append(editor);
  window.OhMyLMSMath.insertAuthor(editor);
 });
 expect(await page.evaluate(() => window.OhMyLMSMath.authorContent(document.querySelector('#empty-author')))).toBe('Solve ');
 await page.locator('#empty-author math-field').press('x'); await page.locator('#empty-author math-field').press('2');
 await expect.poll(() => page.evaluate(() => window.OhMyLMSMath.authorContent(document.querySelector('#empty-author')))).toContain('latex:inline]]x2[[/ohmylms-math]]');
 await page.locator('#empty-author math-field').press('Escape');
 expect(await page.evaluate(() => document.activeElement.id)).toBe('empty-author');
});
