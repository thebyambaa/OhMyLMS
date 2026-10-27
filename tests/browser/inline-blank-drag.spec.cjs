const { test, expect } = require('@playwright/test');
const path = require('node:path');
const base = path.resolve(__dirname, '../..');
const question = id => `<span class="ohmylms-drag-blanks" id="q${id}"><input readonly name="answer[${id}][]" aria-label="First blank"><input readonly name="answer[${id}][]" aria-label="Second blank"><span class="ohmylms-blank-answer-bank"><button type="button" class="ohmylms-blank-token" draggable="true" data-blank-token="0" data-blank-answer="same">same</button><button type="button" class="ohmylms-blank-token" draggable="true" data-blank-token="1" data-blank-answer="same">same</button></span></span>`;
test.beforeEach(async ({ page }) => {
 await page.setContent(`<form>${question(1)}${question(2)}</form>`);
 await page.addStyleTag({path: path.join(base, 'assets/css/quiz-a11y.css')});
 await page.addScriptTag({path: path.join(base, 'assets/js/inline-blank-drag.js')});
});
test('double-click places left to right, preserves bank positions and submission, and stays scoped', async ({ page }) => {
 const bank = page.locator('#q1 .ohmylms-blank-token');
 await bank.nth(1).dblclick();
 await expect(page.locator('#q1 input').first()).toHaveValue('same');
 await expect(bank.nth(1)).toBeDisabled();
 await expect(bank.nth(1)).toBeVisible();
 await expect(bank.nth(1)).toHaveCSS('border-top-style', 'dashed');
 await expect(bank.nth(1)).toHaveCSS('color', 'rgba(0, 0, 0, 0)');
 await expect(page.locator('#q2 input').first()).toHaveValue('');
 await bank.first().dblclick();
 await expect(page.locator('#q1 input').nth(1)).toHaveValue('same');
 expect(await page.evaluate(() => new FormData(document.querySelector('form')).getAll('answer[1][]'))).toEqual(['same', 'same']);
 await page.locator('#q1 input').first().click();
 await expect(bank.nth(1)).toBeEnabled();
 await expect(page.locator('#q1 input').first()).toHaveValue('');
});
test('keyboard, click placement, drag placement and restoration keep repeated tokens independent', async ({ page }) => {
 const bank = page.locator('#q1 .ohmylms-blank-token');
 await bank.first().press('Enter');
 await expect(page.locator('#q1 input').first()).toHaveValue('same');
 await bank.nth(1).click();
 await page.locator('#q1 input').nth(1).click();
 await expect(bank.nth(1)).toBeDisabled();
 await page.locator('#q1 input').first().click();
 await bank.first().dragTo(page.locator('#q1 input').first());
 await expect(page.locator('#q1 input').first()).toHaveValue('same');
 await page.evaluate(() => {
  const fields = document.querySelectorAll('#q2 input');
  fields[0].value = 'same'; fields[1].value = 'same';
  document.dispatchEvent(new CustomEvent('ohmylms:answer-restored'));
 });
 await expect(page.locator('#q2 .is-placed')).toHaveCount(2);
 await page.locator('#q2 input').nth(1).click();
 await expect(page.locator('#q2 .is-placed')).toHaveCount(1);
});
test('author chips and learner tokens have readable dark text on light backgrounds', async ({ page }) => {
 await page.addStyleTag({path: path.join(base, 'assets/css/question-stage.css')});
 await page.evaluate(() => {
  const editor = document.createElement('div'); editor.className = 'ohmylms-question-authoring toplevel_page_ohmylms'; editor.style.color = '#fff';
  editor.innerHTML = '<span class="ohmylms-blank-answer-chip">Readable answer</span><div class="ohmylms-inline-blank-preview"><button disabled class="components-button is-secondary ohmylms-blank-token is-placed">Placed answer</button></div>'; document.body.append(editor);
 });
 await page.addStyleTag({content: '.toplevel_page_ohmylms button.components-button.is-secondary:disabled { color: #949494; border: 1px solid #949494; }'});
 await expect(page.locator('.ohmylms-blank-answer-chip')).toHaveCSS('color', 'rgb(29, 35, 39)');
 await expect(page.locator('.ohmylms-inline-blank-preview .is-placed')).toHaveCSS('border-top-style', 'dashed');
 await expect(page.locator('.ohmylms-inline-blank-preview .is-placed')).toHaveCSS('color', 'rgba(0, 0, 0, 0)');
 await expect(page.locator('#q1 .ohmylms-blank-token').first()).toHaveCSS('color', 'rgb(29, 35, 39)');
});
