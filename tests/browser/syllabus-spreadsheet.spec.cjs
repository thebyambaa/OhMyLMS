// Real workspace, spreadsheet and row-action components; persistence and WP controls use local fixtures.
const { test, expect } = require('@playwright/test');
const { transformSync } = require('@babel/core');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');

function compile(file, bindings, exports, only) {
  const result = transformSync(fs.readFileSync(path.join(root, 'assets/src/features/curriculum', file), 'utf8'), {
    configFile: false, babelrc: false,
    presets: [['@babel/preset-react', { pragma: 'createElement' }]],
    plugins: [() => ({ visitor: {
      ImportDeclaration(p) { p.remove(); },
      ExportNamedDeclaration(p) {
        if (only && !only.includes(p.node.declaration?.id?.name)) p.remove();
        else p.replaceWith(p.node.declaration);
      },
    } })],
  });
  return `(function(){${bindings}\n${result.code}\nreturn {${exports}};})()`;
}

test.beforeEach(async ({ page }) => {
  await page.setContent('<div id="root"></div>');
  for (const file of ['react.js', 'react-dom.js']) await page.addScriptTag({ path: path.resolve(root, '../../../wp-includes/js/dist/vendor', file) });
  await page.addStyleTag({ content: fs.readFileSync(path.join(root, 'assets/css/admin-ui.css'), 'utf8').replace(/^@import.*$/gm, '') });
  await page.addStyleTag({ path: path.join(root, 'assets/css/syllabus-spreadsheet.css') });
  await page.addStyleTag({ content: 'body{margin:0;font:14px Arial}.components-button{padding:8px;border:1px solid #ccc;background:#fff;cursor:pointer}.components-button:disabled{cursor:default}.fixture-menu{position:absolute;z-index:3;padding:8px;background:white;border:1px solid #ccc}.fixture-menu button{display:block}.ohmylms-ws-header h1{font-size:22px}' });
  const element = 'const {createElement,useCallback,useState,useEffect,useMemo,useRef,useId}=React;';
  await page.addScriptTag({ content: `
    const __=text=>text, _x=text=>text, _n=(one,many,n)=>n===1?one:many;
    const sprintf=(text,...args)=>{let i=0;return text.replace(/%(?:(\\d+)\\$)?[sd]/g,(_,n)=>args[n?Number(n)-1:i++]);};
    const Button=({children,label,variant,size,isBusy,isDestructive,icon,href,...props})=>React.createElement('button',{type:'button',...props,'aria-label':label,className:'components-button'},children||label);
    const Notice=({children,status,isDismissible,onRemove})=>React.createElement('div',{className:'components-notice',role:'status'},children);
    const Dashicon=()=>null, Spinner=()=>null;
    const TextareaControl=({label,value,onChange,disabled,rows})=>React.createElement('label',null,label,React.createElement('textarea',{value,disabled,rows,onChange:e=>onChange(e.target.value)}));
    function DropdownMenu({label,controls,disabled}){const [open,setOpen]=React.useState(false);return React.createElement('div',null,
      React.createElement(Button,{label,disabled,onClick:()=>setOpen(!open)},'⋯'),open&&React.createElement('div',{className:'fixture-menu',role:'menu'},controls.map(c=>React.createElement('button',{key:c.title,role:'menuitem',disabled:c.isDisabled,onClick:()=>{setOpen(false);c.onClick();}},c.title))));}
    const helpers=${compile('workspace.mjs', '', 'buildOutline,contentKey,groupKey,skillKey,stepAmongSiblings,CURRICULUM_PATH,ancestorKeys,defaultKey,displayCounts,resolveKey,syllabusIdFromHash')};
    const labels=${compile('syllabus.mjs', '', 'fileName,totalsParts')};
    const csv=${compile('csv.mjs', '', 'parseRecords,outlineCsv,templateCsv')};
    const model=${compile('spreadsheet.mjs', 'const {parseRecords}=csv;', 'SHEET_COLUMNS,SHEET_IDENTITY,sheetFieldColumn,editSheetCell,pastedSheetRows,saveSheetChanges,sheetChanges,syllabusSheetRows')};
    const parts=${compile('WorkspaceParts.jsx', element, 'QuickAdd,RowMenu', ['QuickAdd','RowMenu'])};
    const rowActions=${compile('SpreadsheetActions.jsx', `${element} const {RowMenu}=parts;const {contentKey,groupKey,skillKey,stepAmongSiblings}=helpers;`, 'SpreadsheetActions')};
    window.fixture={calls:[],fail:false,data:{
      syllabus:{id:1,name:'Mathematics'},settings:{},totals:{groups:2,skills:3},course:{id:50,status:'draft'},
      contents:[{id:1,depth:0,parent_id:0,name:'Mathematics',groups:[]},
        {id:2,parent_id:1,depth:1,name:'Algebra',code:'',groups:[{id:10,item_id:2,name:'Equations',skills:[{term_id:100,name:'Solve equations'},{term_id:101,name:'Form equations'}]}]},
        {id:3,parent_id:1,depth:1,name:'Geometry',code:'',groups:[{id:11,item_id:3,name:'Applications',skills:[{term_id:100,name:'Solve equations'}]}]}]
    }};
    const record=(kind,...args)=>fixture.calls.push([kind,...args]);
    const api={
      loadSyllabus:async()=>structuredClone(fixture.data),loadTree:async()=>({items:fixture.data.contents}),
      updateSkill:async(id,term,patch)=>{record('updateSkill',id,term,patch);if(fixture.fail)throw new Error('Save rejected');for(const c of fixture.data.contents)for(const g of c.groups)for(const s of g.skills)if(s.term_id===term)Object.assign(s,patch);},
      updateItem:async(id,patch)=>{record('updateItem',id,patch);Object.assign(fixture.data.contents.find(c=>c.id===id),patch);},
      updateGroup:async(id,group,patch)=>{record('updateGroup',id,group,patch);for(const c of fixture.data.contents)for(const g of c.groups)if(g.id===group)Object.assign(g,patch);}
    };
    fixture.actions={
      addSkill:async(group,data)=>{record('addSkill',group,data);for(const c of fixture.data.contents)for(const g of c.groups)if(g.id===group)g.skills.push({term_id:200,...data});fixture.refresh();return true;},
      addSkillInTopic:async(...args)=>{record('addSkillInTopic',...args);return true;},
      addTopic:async(...args)=>{record('addTopic',...args);return true;},
      stepSkill:async(...args)=>record('stepSkill',...args),stepChapter:async(...args)=>record('stepChapter',...args),stepTopic:async(...args)=>record('stepTopic',...args),
      removeSkill:async(group,term)=>{record('removeSkill',group,term);for(const c of fixture.data.contents)for(const g of c.groups)if(g.id===group)g.skills=g.skills.filter(s=>s.term_id!==term);fixture.refresh();return true;}
    };
    const sheet=${compile('SyllabusSpreadsheet.jsx', `${element}const {QuickAdd}=parts;const {SpreadsheetActions}=rowActions;const {SHEET_COLUMNS,SHEET_IDENTITY,sheetFieldColumn,editSheetCell,pastedSheetRows,saveSheetChanges,sheetChanges,syllabusSheetRows}=model;`, 'SyllabusSpreadsheet')};
    const WorkspaceContext=React.createContext(null);
    function useSyllabusWorkspace(){const [outline,setOutline]=React.useState(()=>structuredClone(fixture.data));fixture.refresh=()=>setOutline(structuredClone(fixture.data));const tree=React.useMemo(()=>helpers.buildOutline(outline),[outline]);return {outline,tree,items:outline.contents,adopt:setOutline,revision:0,pending:false,run:async()=>true};}
    const useCourseCatalog=()=>({catalog:null});
    const createActions=()=>fixture.actions;
    const hashQuery=()=>new URLSearchParams();
    const download=()=>{};
    const TopicPane=({node})=>React.createElement('h2',null,'Details: '+node.key);
    const ChapterPane=TopicPane,SkillPane=TopicPane;
    const WorkspaceSidebar=()=>React.createElement('aside',null,'Outline navigation');
    const SyllabusMindmap=()=>React.createElement('section',{'aria-label':'Mind map canvas'},'Mind map');
    const ImportDialog=()=>null,SkillStudio=()=>null,StudioGroupEditor=()=>null;
    const workspace=${compile('SyllabusWorkspace.jsx', `${element}const {SyllabusSpreadsheet}=sheet;const {RowMenu}=parts;const {fileName,totalsParts}=labels;const {outlineCsv,templateCsv}=csv;const {CURRICULUM_PATH,ancestorKeys,defaultKey,displayCounts,resolveKey,syllabusIdFromHash}=helpers;`, 'SyllabusWorkspace')};
    ReactDOM.createRoot(document.getElementById('root')).render(React.createElement(workspace.SyllabusWorkspace,{syllabusId:1}));
  ` });
});

const views = page => page.getByRole('group', { name: 'Syllabus view' });
const cell = (page, row, column) => page.locator(`[data-row="${row}"][data-column="${column}"]`);
const rowMenu = (page, row) => page.locator('tbody tr').nth(row).getByRole('button', { name: 'Row actions' });

test('Spreadsheet is a full workspace view with the existing syllabus rows', async ({ page }) => {
  await views(page).getByRole('button', { name: 'Spreadsheet', exact: true }).click();
  await expect(page.getByRole('dialog')).toHaveCount(0);
  await expect(page.locator('tbody tr')).toHaveCount(3);
  await expect(cell(page, 0, 1)).toHaveValue('Algebra');
  await expect(cell(page, 0, 2)).toHaveValue('Equations');
  await expect(cell(page, 0, 3)).toHaveValue('Solve equations');
  await expect(page.locator('.ohmylms-ws-main')).toHaveCount(0);
  await views(page).getByRole('button', { name: 'Mind map', exact: true }).click();
  await expect(page.getByRole('region', { name: 'Mind map canvas' })).toBeVisible();
  await views(page).getByRole('button', { name: 'Outline', exact: true }).click();
  await expect(page.getByText('Outline navigation', { exact: true })).toBeVisible();
});

test('edits keep shared identities, guard view switches, and save or discard in place', async ({ page }) => {
  await views(page).getByRole('button', { name: 'Spreadsheet', exact: true }).click();
  await cell(page, 0, 3).fill('Revised objective');
  await expect(cell(page, 2, 3)).toHaveValue('Revised objective');
  await expect(views(page).getByRole('button', { name: 'Outline', exact: true })).toBeDisabled();
  await expect(rowMenu(page, 0)).toBeDisabled();
  await page.evaluate(() => { fixture.fail = true; });
  await page.getByRole('button', { name: 'Save changes', exact: true }).click();
  await expect(page.getByText(/Your edits are still here/)).toBeVisible();
  await expect(cell(page, 0, 3)).toHaveValue('Revised objective');
  await page.evaluate(() => { fixture.fail = false; });
  await page.getByRole('button', { name: 'Save changes', exact: true }).click();
  await expect(page.getByText('Syllabus spreadsheet saved.', { exact: true })).toBeVisible();
  expect(await page.evaluate(() => fixture.calls.at(-1))).toEqual(['updateSkill',1,100,{name:'Revised objective'}]);
  await cell(page, 0, 3).fill('Discard me');
  await page.getByRole('button', { name: 'Discard changes', exact: true }).click();
  await expect(cell(page, 0, 3)).toHaveValue('Revised objective');
  await expect(views(page).getByRole('button', { name: 'Outline', exact: true })).toBeEnabled();
});

test('row actions open details, add a skill in its group, move, and remove only its placement', async ({ page }) => {
  await views(page).getByRole('button', { name: 'Spreadsheet', exact: true }).click();
  await rowMenu(page, 0).click();
  await page.getByRole('menuitem', { name: 'Open details and resources', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Details: s:10:100', exact: true })).toBeVisible();
  await expect(page.getByRole('table', { name: 'Syllabus spreadsheet' })).toBeVisible();
  await page.getByRole('button', { name: 'Close details', exact: true }).click();
  await rowMenu(page, 0).click();
  await page.getByRole('menuitem', { name: 'Add skill in this chapter', exact: true }).click();
  await page.getByLabel('Skill name', { exact: true }).fill('New objective');
  await page.locator('.ohmylms-ws-quickadd').getByRole('button', { name: 'Add skill', exact: true }).click();
  await expect(page.locator('tbody tr')).toHaveCount(4);
  expect(await page.evaluate(() => fixture.calls.at(-1))).toEqual(['addSkill',10,{name:'New objective',code:''}]);
  await page.locator('.ohmylms-ws-quickadd').getByRole('button', { name: 'Done', exact: true }).click();
  await rowMenu(page, 0).click();
  await page.getByRole('menuitem', { name: 'Move down', exact: true }).click();
  expect(await page.evaluate(() => fixture.calls.at(-1))).toEqual(['stepSkill',10,100,1]);
  await rowMenu(page, 0).click();
  await page.getByRole('menuitem', { name: 'Remove skill from this chapter', exact: true }).click();
  await expect(page.locator('tbody tr')).toHaveCount(3);
  expect(await page.evaluate(() => fixture.calls.at(-1))).toEqual(['removeSkill',10,100]);
  await expect(cell(page, 2, 3)).toHaveValue('Solve equations');
});

test('mobile spreadsheet scrolls internally and retains its row actions', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await views(page).getByRole('button', { name: 'Spreadsheet', exact: true }).click();
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
  const scroll = page.locator('.ohmylms-sheet-scroll');
  await scroll.evaluate(el => { el.scrollLeft = el.scrollWidth; });
  await expect(rowMenu(page, 0)).toBeInViewport();
  await page.screenshot({ path: path.join(root, 'build/syllabus-spreadsheet-mobile.png'), fullPage: true });
});
