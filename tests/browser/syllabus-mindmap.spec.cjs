// Isolated UI fixture: real syllabus components, React, and layout; workspace writes are recorded.
const { test, expect } = require('@playwright/test');
const fs = require('node:fs');
const path = require('node:path');
const { transformSync } = require('@babel/core');
const root = path.resolve(__dirname, '../..');
const source = 'assets/src/features/curriculum/';

function moduleScript(file, bindings, names, only) {
  const result = transformSync(fs.readFileSync(path.join(root, source, file), 'utf8'), {
    configFile: false,
    babelrc: false,
    presets: [['@babel/preset-react', { pragma: 'createElement' }]],
    plugins: [() => ({ visitor: {
      ImportDeclaration(p) { p.remove(); },
      ExportNamedDeclaration(p) {
        if (only && !only.includes(p.node.declaration?.id?.name)) p.remove();
        else p.replaceWith(p.node.declaration);
      },
    } })],
  });
  return `(function(){${bindings}\n${result.code}\nreturn {${names}};})()`;
}

test.beforeEach(async ({ page }) => {
  await page.setContent('<div id="root"></div>');
  await page.addScriptTag({ path: path.resolve(root, '../../../wp-includes/js/dist/vendor/react.js') });
  await page.addScriptTag({ path: path.resolve(root, '../../../wp-includes/js/dist/vendor/react-dom.js') });
  await page.addStyleTag({ content: fs.readFileSync(path.join(root, 'assets/css/admin-ui.css'), 'utf8').replace(/^@import.*$/gm, '') });
  await page.addStyleTag({ path: path.join(root, 'assets/css/syllabus-mindmap.css') });
  await page.addStyleTag({ content: 'body{margin:0;font:14px Arial}.components-button{cursor:pointer;padding:6px;background:white;border:1px solid #ccc;border-radius:4px}.components-search-control input{box-sizing:border-box;width:100%;padding:10px}' });
  const element = 'const {createElement,useState,useEffect,useLayoutEffect,useCallback,useMemo,useRef,useId}=React;';
  await page.addScriptTag({ content: `
    window.fixture={calls:[],fail:false};
    const __=text=>text;
    const sprintf=(text,name)=>text.replace('%s',name);
    const Button=({children,label,variant,size,isBusy,...props})=>React.createElement('button',{type:'button',...props,'aria-label':label,className:'components-button'},children);
    const SearchControl=({label,value,onChange})=>React.createElement('label',{className:'components-search-control'},label,React.createElement('input',{type:'search',value,onChange:event=>onChange(event.target.value)}));
    const helpers=${moduleScript('workspace.mjs', '', 'buildOutline,ancestorKeys,containerOf,outlineDropPosition,topicLabel')};
    const labels=${moduleScript('syllabus.mjs', '', 'groupLabel,skillLabel')};
    const translations=${moduleScript('skillTranslations.mjs', '', 'skillText')};
    const mapLayout=${moduleScript('syllabusMap.mjs', '', 'layoutSyllabusMap,MAP_CARD')};
    const navigation=${moduleScript('useMapNavigation.js', element, 'useMapNavigation,MAP_GUTTER')};
    const quick=${moduleScript('WorkspaceParts.jsx', element, 'QuickAdd', ['QuickAdd'])};
    const WorkspaceContext=React.createContext(null);
    const useWorkspace=()=>React.useContext(WorkspaceContext);
    const KindIcon=()=>null;
    const map=${moduleScript('SyllabusMindmap.jsx', `${element} const {useMapNavigation,MAP_GUTTER}=navigation; const {ancestorKeys,containerOf,outlineDropPosition,topicLabel}=helpers; const {groupLabel,skillLabel}=labels; const {skillText}=translations; const {layoutSyllabusMap,MAP_CARD}=mapLayout; const {QuickAdd}=quick;`, 'SyllabusMindmap')};
    function App(){
      const [outline,setOutline]=React.useState({contents:[
        {id:1,depth:0,name:'Mathematics',groups:[]},
        {id:2,parent_id:1,depth:1,name:'Algebra',groups:[{id:10,name:'Equations',skills:[{term_id:100,name:'Solve equations'},{term_id:101,name:'Form equations'}]}]},
        {id:3,parent_id:1,depth:1,name:'Geometry',groups:[]}
      ]});
      const [selected,select]=React.useState('c:1');
      const tree=React.useMemo(()=>helpers.buildOutline(outline),[outline]);
      const record=async(kind,...args)=>{fixture.calls.push([kind,...args]);return !fixture.fail;};
      const actions={
        addTopic:async(id,data)=>{const ok=await record('folder',id,data);if(ok)setOutline(current=>({contents:[...current.contents,{id:9,parent_id:id,depth:1,...data,groups:[]}]}));return ok;},
        addSkill:(...args)=>record('skill',...args),
        addSkillInTopic:(...args)=>record('topicSkill',...args),
        reorderOutline:(...args)=>record('move',...args)
      };
      return React.createElement(WorkspaceContext.Provider,{value:{tree,selected,select,actions,pending:false,skillLanguage:'original'}},
        React.createElement('div',{className:'ohmylms-ws is-mindmap-view'},React.createElement('div',{className:'ohmylms-ws-body has-mindmap'},
          React.createElement(map.SyllabusMindmap),React.createElement('main',{className:'ohmylms-ws-main'},React.createElement('h2',null,'Selected: '+selected)))));
    }
    ReactDOM.createRoot(document.getElementById('root')).render(React.createElement(App));
  ` });
});

const card = (page, key) => page.locator(`[data-node-key="${key}"]`);

test('select, collapse, search hidden branches, and zoom the syllabus map', async ({ page }) => {
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await expect(page.locator('.ohmylms-syllabus-map-card')).toHaveCount(6);
  await card(page, 's:10:100').locator('.ohmylms-syllabus-map-select').click();
  await expect(page.getByRole('heading', { name: 'Selected: s:10:100' })).toBeVisible();
  await page.getByRole('button', { name: 'Collapse all', exact: true }).click();
  await expect(page.locator('.ohmylms-syllabus-map-card')).toHaveCount(3);
  await page.getByRole('searchbox').fill('FORM equations');
  await expect(card(page, 's:10:101')).toBeInViewport();
  await expect(card(page, 'c:3')).toHaveCount(0);
  await page.getByRole('searchbox').fill('missing');
  await expect(page.getByRole('status')).toHaveText('No matching folders or skills.');
  await page.getByRole('searchbox').fill('');
  await page.getByRole('button', { name: 'Expand all', exact: true }).click();
  await page.getByRole('button', { name: 'Zoom in', exact: true }).click();
  await expect(page.getByRole('button', { name: 'Reset zoom' })).toHaveText('110%');
  await page.getByRole('button', { name: 'Reset zoom' }).click();
  await page.getByRole('button', { name: 'Collapse all', exact: true }).click();
  await page.evaluate(() => document.querySelector('[data-node-key="c:2"] .ohmylms-syllabus-map-select').click());
  await expect(card(page, 'g:10')).toBeAttached();
  expect(errors).toEqual([]);
  await page.screenshot({ path: path.join(root, 'build/syllabus-mindmap-desktop.png'), fullPage: true });
});

test('add actions target the correct folder or group and preserve failed-save drafts', async ({ page }) => {
  await card(page, 'c:1').getByRole('button', { name: '+ Folder', exact: true }).click();
  await page.getByLabel('Folder name', { exact: true }).fill('Statistics');
  await page.evaluate(() => { fixture.fail = true; });
  await page.getByRole('button', { name: 'Add folder', exact: true }).click();
  await expect(page.getByLabel('Folder name', { exact: true })).toHaveValue('Statistics');
  await expect(card(page, 'c:9')).toHaveCount(0);
  await page.evaluate(() => { fixture.fail = false; });
  await page.getByRole('button', { name: 'Add folder', exact: true }).click();
  await expect(card(page, 'c:9')).toContainText('Statistics');
  await page.getByRole('button', { name: 'Done', exact: true }).click();
  await card(page, 'g:10').getByRole('button', { name: '+ Skill', exact: true }).click();
  await page.getByLabel('Skill name', { exact: true }).fill('Factor expressions');
  await page.getByRole('button', { name: 'Add skill', exact: true }).click();
  expect(await page.evaluate(() => fixture.calls.at(-1))).toEqual(['skill', 10, { name: 'Factor expressions', code: '' }]);
});

test('valid folder drops use existing reorder actions and cycles are rejected', async ({ page }) => {
  await page.getByRole('button', { name: 'Fit map', exact: true }).click();
  await card(page, 'c:2').locator('.ohmylms-syllabus-map-move-handle').dragTo(card(page, 'c:3'));
  expect(await page.evaluate(() => fixture.calls.at(-1))).toEqual(['move', 'c:2', 'c:3', 'inside']);
  await page.evaluate(() => { fixture.calls = []; });
  await card(page, 'c:2').locator('.ohmylms-syllabus-map-move-handle').dragTo(card(page, 'g:10'));
  expect(await page.evaluate(() => fixture.calls)).toEqual([]);
  await page.getByRole('button', { name: 'Fit map', exact: true }).click();
  const handle = card(page, 's:10:100').locator('.ohmylms-syllabus-map-move-handle');
  await handle.scrollIntoViewIfNeeded();
  const from = await handle.boundingBox();
  const to = await card(page, 's:10:101').boundingBox();
  await page.mouse.move(from.x + from.width / 2, from.y + from.height / 2);
  await page.mouse.down();
  await page.mouse.move(from.x + from.width / 2 + 10, from.y + from.height / 2, { steps: 3 });
  await page.mouse.move(to.x + to.width / 2, to.y + to.height * 0.75, { steps: 8 });
  await page.mouse.up();
  expect(await page.evaluate(() => fixture.calls.at(-1))).toEqual(['move', 's:10:100', 's:10:101', true]);
});

test('mobile canvas scrolls internally and details stack below it', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  const map = await page.locator('.ohmylms-syllabus-map').boundingBox();
  const details = await page.locator('.ohmylms-ws-main').boundingBox();
  expect(details.y).toBeGreaterThanOrEqual(map.y + map.height);
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
  await page.screenshot({ path: path.join(root, 'build/syllabus-mindmap-mobile.png'), fullPage: true });
});

test('background drag pans without moving nodes and pointer release stops panning', async ({ page }) => {
  const viewport = page.locator('.ohmylms-syllabus-map-viewport');
  const before = await viewport.evaluate(el => ({ left: el.scrollLeft, top: el.scrollTop }));
  const point = await viewport.evaluate(el => {
    const box = el.getBoundingClientRect();
    for (let y = box.top + 80; y < box.bottom - 80; y += 40) {
      const x = box.left + box.width * 0.7;
      if (!document.elementFromPoint(x, y).closest('.ohmylms-syllabus-map-card')) return { x, y };
    }
    throw new Error('No blank canvas point found');
  });
  await page.mouse.move(point.x, point.y);
  await page.mouse.down();
  await page.mouse.move(point.x - 120, point.y + 20, { steps: 8 });
  await expect(viewport).toHaveClass(/is-panning/);
  await page.mouse.up();
  await expect(viewport).not.toHaveClass(/is-panning/);
  const after = await viewport.evaluate(el => el.scrollLeft);
  expect(after - before.left).toBeCloseTo(120, 0);
  await page.mouse.move(point.x - 160, point.y + 30);
  expect(await viewport.evaluate(el => el.scrollLeft)).toBe(after);
  expect(await page.evaluate(() => fixture.calls)).toEqual([]);
});

test('zoom keeps the pointer anchor and toolbar zoom keeps the viewport center', async ({ page }) => {
  const viewport = page.locator('.ohmylms-syllabus-map-viewport');
  await viewport.evaluate(el => el.scrollTo({ left: 300, top: 80 }));
  const box = await viewport.boundingBox();
  const point = { x: box.width * 0.6, y: box.height * 0.4 };
  const world = (position) => viewport.evaluate((el, p) => {
    const zoom = Number(/scale\(([^)]+)\)/.exec(document.querySelector('.ohmylms-syllabus-map-stage').style.transform)[1]);
    return { x: (el.scrollLeft + p.x - 240) / zoom, y: (el.scrollTop + p.y - 240) / zoom };
  }, position);
  const before = await world(point);
  await page.mouse.move(box.x + point.x, box.y + point.y);
  await page.keyboard.down('Control');
  await page.mouse.wheel(0, -60);
  await page.keyboard.up('Control');
  await expect(page.getByRole('button', { name: 'Reset zoom' })).not.toHaveText('100%');
  const after = await world(point);
  expect(Math.abs(after.x - before.x)).toBeLessThan(1);
  expect(Math.abs(after.y - before.y)).toBeLessThan(1);
  const center = { x: box.width / 2, y: box.height / 2 };
  const centerBefore = await world(center);
  await page.getByRole('button', { name: 'Zoom in', exact: true }).click();
  const centerAfter = await world(center);
  expect(Math.abs(centerAfter.x - centerBefore.x)).toBeLessThan(1);
  expect(Math.abs(centerAfter.y - centerBefore.y)).toBeLessThan(1);
});

test('fit shows every node and Find selected restores a hidden selection', async ({ page }) => {
  await page.getByRole('button', { name: 'Fit map', exact: true }).click();
  for (const node of await page.locator('.ohmylms-syllabus-map-card').all()) await expect(node).toBeInViewport({ ratio: 0.99 });
  await card(page, 's:10:100').locator('.ohmylms-syllabus-map-select').click();
  await page.getByRole('button', { name: 'Collapse all', exact: true }).click();
  await expect(card(page, 's:10:100')).toHaveCount(0);
  await page.getByRole('searchbox').fill('Geometry');
  await page.getByRole('button', { name: 'Find selected', exact: true }).click();
  await expect(page.getByRole('searchbox')).toHaveValue('');
  await expect(card(page, 's:10:100')).toBeInViewport();
});

test('move picker supports keyboard moves and preserves a rejected move', async ({ page }) => {
  await page.getByRole('button', { name: 'Fit map', exact: true }).click();
  await card(page, 'c:2').locator('.ohmylms-syllabus-map-select').click();
  await page.getByRole('button', { name: 'Move selected', exact: true }).click();
  await page.getByLabel('Placement', { exact: true }).selectOption('inside');
  await page.getByLabel('Destination', { exact: true }).selectOption('c:3');
  await page.evaluate(() => { fixture.fail = true; });
  await page.getByRole('button', { name: 'Move node', exact: true }).click();
  await expect(page.getByLabel('Destination', { exact: true })).toHaveValue('c:3');
  await page.evaluate(() => { fixture.fail = false; });
  await page.getByRole('button', { name: 'Move node', exact: true }).press('Enter');
  await expect(page.getByLabel('Destination', { exact: true })).toHaveCount(0);
  expect(await page.evaluate(() => fixture.calls.at(-1))).toEqual(['move', 'c:2', 'c:3', 'inside']);
});

test('canvas keyboard zoom and move-handle activation remain accessible', async ({ page }) => {
  const viewport = page.locator('.ohmylms-syllabus-map-viewport');
  await viewport.focus();
  await viewport.press('+');
  await expect(page.getByRole('button', { name: 'Reset zoom' })).toHaveText('110%');
  await viewport.press('-');
  await expect(page.getByRole('button', { name: 'Reset zoom' })).toHaveText('100%');
  await page.getByRole('button', { name: 'Fit map', exact: true }).click();
  await card(page, 'c:2').locator('.ohmylms-syllabus-map-move-handle').press('Enter');
  await expect(page.getByLabel('Destination', { exact: true })).toBeVisible();
});

test.describe('touch input', () => {
  test.use({ hasTouch: true });
  test('a finger pans the background and the move handle moves a node', async ({ page }) => {
    const viewport = page.locator('.ohmylms-syllabus-map-viewport');
    const box = await viewport.boundingBox();
    const point = { x: box.x + box.width * 0.6, y: box.y + 40 };
    const before = await viewport.evaluate(el => el.scrollLeft);
    const client = await page.context().newCDPSession(page);
    await client.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [point] });
    await client.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: point.x - 100, y: point.y }] });
    await client.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
    expect(await viewport.evaluate(el => el.scrollLeft)).toBeGreaterThan(before + 50);
    await page.getByRole('button', { name: 'Fit map', exact: true }).click();
    const handle = await card(page, 'c:2').locator('.ohmylms-syllabus-map-move-handle').boundingBox();
    const destination = await card(page, 'c:3').boundingBox();
    await client.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: handle.x + handle.width / 2, y: handle.y + handle.height / 2 }] });
    await client.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: destination.x + destination.width / 2, y: destination.y + destination.height / 2 }] });
    await client.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
    expect(await page.evaluate(() => fixture.calls.at(-1))).toEqual(['move', 'c:2', 'c:3', 'inside']);
    await client.detach();
  });
});
