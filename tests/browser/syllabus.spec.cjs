/**
 * Syllabuses in a real browser. In the Curriculum tab any item can be turned into one and its name opens the
 * syllabus workspace: a full-page editor (outline on the left, the selected topic, chapter or skill on the
 * right). A syllabus is also a course, so its chapters (skill groups) and skills are the course's, and lessons
 * are attached to a chapter or owned by a skill. A CSV file is mapped, checked and imported. Names are made up
 * to illustrate the shapes of real syllabuses; they are not syllabus definitions.
 * Needs the disposable site: OHMYLMS_TEST_CREDENTIALS (JSON), OHMYLMS_CHROMIUM_PATH (browser). Run with --workers=1.
 */
const { test, expect } = require('@playwright/test');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

const admin = () => JSON.parse(fs.readFileSync(process.env.OHMYLMS_TEST_CREDENTIALS, 'utf8'));
async function login(page) {
  const { username, password } = admin();
  await page.goto('/wp-login.php');
  await page.locator('#user_login').fill(username);
  await page.locator('#user_pass').fill(password);
  await page.locator('#wp-submit').click();
  await page.waitForURL((url) => !url.pathname.endsWith('wp-login.php'));
}
const watch = (page) => {
  const problems = [];
  page.on('pageerror', (error) => problems.push(`pageerror: ${error.message}`));
  // The shipped admin vendor bundle logs these store messages on first load of any OhMyLMS admin page.
  const known =
    /favicon|Failed to load resource|Store "core\/(preferences|keyboard-shortcuts)" is already registered/;
  page.on('console', (message) => {
    if (message.type() === 'error' && !known.test(message.text()))
      problems.push(`console: ${message.text()}`);
  });
  return problems;
};
const api = (page, options) => page.evaluate((o) => wp.apiFetch(o), options);
const created = { items: [], lessons: [] };
const tag = Math.random().toString(36).slice(2, 7);
const base = '/ohmylms/v1';

// The workspace's own message area (WordPress also repeats each message in a hidden screen-reader region).
const notice = (page, text) =>
  page.locator('.ohmylms-ws-status .components-notice', { hasText: text }).first();
const side = (page) => page.locator('.ohmylms-ws-side');
const main = (page) => page.locator('.ohmylms-ws-main');
const title = (page) => page.locator('input.ohmylms-ws-title');
const row = (page, text) => main(page).locator('.ohmylms-ws-row-main', { hasText: text });
const treeNode = (page, text) => side(page).locator('.ohmylms-ws-node-main', { hasText: text });

async function makeItem(page, data) {
  const item = (await api(page, { path: `${base}/curriculum/items`, method: 'POST', data })).item;
  created.items.push(item.id);
  return item;
}
async function makeLesson(page, name) {
  const lesson = await api(page, {
    path: `${base}/lessons`,
    method: 'POST',
    data: { name, status: 'publish', type: 'text' },
  });
  created.lessons.push(lesson.id);
  return lesson;
}
/**
 * Remove what a test made: the courses (and their chapters) the syllabuses became, the lessons, the library
 * skills (found through each syllabus's root skill, named after it and carrying this run's tag) and the items.
 */
async function cleanup(page) {
  const courses = [];
  for (const id of created.items) {
    const item = await api(page, { path: `${base}/curriculum/items/${id}` }).catch(() => null);
    if (item?.item?.course_id) courses.push(item.item.course_id);
  }
  const { skills } = await api(page, { path: `${base}/skills` }).catch(() => ({ skills: [] }));
  const roots = skills.filter((skill) => skill.parent === 0 && skill.name.includes(tag));
  const doomed = [
    ...skills.filter((skill) => roots.some((root) => root.id === skill.parent)),
    ...roots,
  ];
  for (const skill of doomed)
    await api(page, {
      path: `${base}/skills/${skill.id}`,
      method: 'DELETE',
      data: { force: true },
    }).catch(() => null);
  for (const id of [...created.items].reverse())
    await api(page, {
      path: `${base}/curriculum/items/${id}`,
      method: 'DELETE',
      data: { children: 'delete', confirm: true },
    }).catch(() => null);
  for (const id of courses) {
    const catalog = await api(page, { path: `${base}/content-hub/catalog/${id}` }).catch(
      () => null,
    );
    for (const chapter of catalog?.chapters || [])
      await api(page, { path: `${base}/chapters/${chapter.id}`, method: 'DELETE' }).catch(
        () => null,
      );
    await api(page, { path: `${base}/courses/${id}`, method: 'DELETE' }).catch(() => null);
  }
  for (const id of created.lessons)
    await api(page, { path: `${base}/lessons/${id}`, method: 'DELETE' }).catch(() => null);
  created.items.length = 0;
  created.lessons.length = 0;
}
async function openCurriculum(page) {
  await page.goto('/wp-admin/admin.php?page=ohmylms#/content-hub/curriculum');
  await page.reload();
  await expect(page.getByRole('heading', { name: 'Curriculum', exact: true })).toBeVisible({
    timeout: 30000,
  });
}
async function openEditor(page, framework, subject) {
  await openCurriculum(page);
  await page.getByRole('button', { name: `Expand ${framework}` }).click();
  await page.getByRole('button', { name: `Edit ${subject}`, exact: true }).click();
}
async function openWorkspace(page, id) {
  await page.goto(`/wp-admin/admin.php?page=ohmylms#/content-hub/curriculum/syllabus/${id}`);
  await page.reload();
  await expect(page.locator('.ohmylms-ws-header h1')).toBeVisible({ timeout: 60000 });
}

test.describe.configure({ mode: 'serial' });

test('any item becomes a syllabus, opens in its own editor, and is built there chapter by chapter', async ({
  page,
}) => {
  const problems = watch(page);
  await login(page);
  try {
    const framework = await makeItem(page, {
      name: `Syl framework ${tag}`,
      item_type: 'framework',
    });
    const subject = await makeItem(page, {
      name: `Syl subject ${tag}`,
      item_type: 'subject',
      parent_id: framework.id,
    });
    await openEditor(page, framework.name, subject.name);

    // Any item (here a "subject") becomes a syllabus with one click and keeps its own type. Its name then
    // opens the syllabus editor, and so does the button in its panel.
    const toggle = page.getByLabel('This item is a syllabus');
    const addChild = (name) => page.getByRole('button', { name: `Add child to ${name}` });
    await expect(toggle).not.toBeChecked();
    await expect(page.getByRole('link', { name: 'Open the syllabus editor' })).toHaveCount(0);
    await expect(addChild(subject.name)).toBeVisible();
    await toggle.check();
    await expect(page.getByRole('link', { name: 'Open the syllabus editor' })).toBeVisible({
      timeout: 15000,
    });
    const treeRow = page.locator('.ohmylms-cur-row', { hasText: subject.name }).first();
    await expect(treeRow.locator('.ohmylms-cur-syllabus-badge')).toHaveText('Syllabus');
    await expect(treeRow.locator('.ohmylms-cur-type')).toHaveText('Subject');
    // Being a syllabus also made it a course, placed under it.
    await expect(treeRow).toContainText('1 course');
    await expect(addChild(subject.name)).toHaveCount(0);
    await treeRow.getByRole('link', { name: subject.name }).click();
    await expect(page).toHaveURL(/#\/content-hub\/curriculum\/syllabus\/\d+$/);
    await expect(page.locator('.ohmylms-ws-header h1')).toHaveText(subject.name, {
      timeout: 60000,
    });
    // An empty syllabus opens on itself, with the course it is.
    await expect(title(page)).toHaveValue(subject.name);
    const course = page.locator('.ohmylms-ws-course');
    await expect(course).toContainText('Course draft');
    await expect(course).toContainText('Skill-based');
    await expect(main(page).getByText('Nothing is in this syllabus yet.')).toBeVisible();

    // A chapter (a skill group) from the left panel, like Add Chapter in the course builder.
    await page.getByRole('button', { name: 'Add Chapter' }).click();
    const sideForm = side(page).locator('.ohmylms-ws-quickadd');
    await sideForm.getByRole('button', { name: 'Add chapter' }).click();
    await expect(sideForm.getByText('Enter a name or a code.')).toBeVisible();
    await sideForm.getByLabel('Code').fill('C1.1');
    await sideForm.getByLabel('Chapter name').fill('Types of number');
    await sideForm.getByLabel('Chapter name').press('Enter');
    await expect(title(page)).toHaveValue('Types of number', { timeout: 30000 });
    await expect(treeNode(page, 'C1.1 · Types of number')).toHaveAttribute('aria-current', 'true');
    await expect(page.locator('.ohmylms-ws-side-count')).toContainText('1 Chapter');
    await sideForm.getByRole('button', { name: 'Done' }).click();

    // Skills go in from the keyboard: code, Tab, name, Enter, and the form stays for the next one.
    await main(page).getByRole('button', { name: 'Add Content' }).click();
    await page.getByRole('menuitem', { name: /^Skill A learning objective/ }).click();
    const skillForm = main(page).locator('.ohmylms-ws-quickadd');
    const addSkill = async (code, name) => {
      await skillForm.getByLabel('Code').fill(code);
      await skillForm.getByLabel('Skill', { exact: true }).fill(name);
      await skillForm.getByLabel('Skill', { exact: true }).press('Enter');
      await expect(row(page, name)).toBeVisible({ timeout: 30000 });
    };
    await addSkill('C1.1.1', 'Identify natural numbers');
    await expect(skillForm.getByLabel('Code')).toBeFocused();
    await addSkill('C1.1.2', 'Identify prime numbers');
    await expect(main(page).locator('.ohmylms-ws-rows .ohmylms-ws-row')).toHaveCount(2);
    await expect(page.locator('.ohmylms-ws-header-title p')).toContainText('1 chapter · 2 skills');

    // A code is unique in the syllabus: the server's refusal is shown and nothing is added.
    await skillForm.getByLabel('Code').fill('c1.1.1');
    await skillForm.getByLabel('Skill', { exact: true }).fill('Another');
    await skillForm.getByLabel('Skill', { exact: true }).press('Enter');
    await expect(notice(page, /already uses the code/)).toBeVisible();
    await expect(main(page).locator('.ohmylms-ws-rows .ohmylms-ws-row')).toHaveCount(2);
    await skillForm.getByRole('button', { name: 'Done' }).click();

    // The course follows: one chapter for the group and one skill for each skill.
    const outline = () => api(page, { path: `${base}/curriculum/items/${subject.id}/syllabus` });
    const catalog = async () =>
      api(page, { path: `${base}/content-hub/catalog/${(await outline()).course.id}` });
    let snapshot = await catalog();
    expect(snapshot.chapters.map((chapter) => chapter.name)).toEqual(['C1.1 · Types of number']);
    expect(snapshot.skills.map((skill) => skill.code)).toEqual(['C1.1.1', 'C1.1.2']);

    // A skill: rename it in place, reorder it from its menu, and see the outline follow.
    await row(page, 'Identify prime numbers').click();
    await expect(title(page)).toHaveValue('Identify prime numbers');
    await title(page).fill('Identify primes');
    await title(page).press('Tab');
    await expect(treeNode(page, 'C1.1.2 · Identify primes')).toBeVisible({ timeout: 30000 });
    await page.getByRole('button', { name: 'Skill actions' }).click();
    await page.getByRole('menuitem', { name: 'Move up' }).click();
    await main(page).getByRole('button', { name: 'C1.1 · Types of number' }).click();
    await expect
      .poll(() => main(page).locator('.ohmylms-ws-row-title').allInnerTexts())
      .toEqual(['Identify primes', 'Identify natural numbers']);
    snapshot = await catalog();
    expect(snapshot.skills.map((skill) => skill.code)).toEqual(['C1.1.2', 'C1.1.1']);

    // Taking a skill out of its chapter keeps it in the skill library.
    await row(page, 'Identify natural numbers').click();
    await page.getByRole('button', { name: 'Skill actions' }).click();
    await page.getByRole('menuitem', { name: 'Take out of this chapter' }).click();
    await expect(notice(page, 'stays in the skill library')).toBeVisible();
    await expect(title(page)).toHaveValue('Types of number');
    const library = await api(page, { path: `${base}/skills` });
    expect(library.skills.some((skill) => skill.name === 'Identify natural numbers')).toBe(true);
    expect((await outline()).totals).toEqual({ contents: 0, groups: 1, skills: 1 });

    // Deleting a chapter asks first, and the course loses the chapter too.
    await page.getByRole('button', { name: 'Chapter actions' }).click();
    await page.getByRole('menuitem', { name: 'Delete chapter…' }).click();
    const dialog = page.getByRole('dialog');
    await expect(dialog).toContainText('stays in the skill library');
    await dialog.getByRole('button', { name: 'Delete chapter' }).click();
    await expect(treeNode(page, 'Types of number')).toHaveCount(0);
    await expect(title(page)).toHaveValue(subject.name);
    snapshot = await catalog();
    expect(snapshot.chapters).toEqual([]);

    // Back to the curriculum, where an empty syllabus can be switched off again.
    await page.locator('.ohmylms-ws-back').click();
    await expect(page.getByRole('heading', { name: 'Curriculum', exact: true })).toBeVisible();
    await page.getByRole('button', { name: `Edit ${subject.name}`, exact: true }).click();
    await page.getByLabel('This item is a syllabus').uncheck();
    await expect(page.getByRole('link', { name: 'Open the syllabus editor' })).toHaveCount(0);
    expect(problems).toEqual([]);
  } finally {
    await cleanup(page);
  }
});

test('topics hold chapters; the course card shows where the course stands', async ({ page }) => {
  const problems = watch(page);
  await login(page);
  try {
    const framework = await makeItem(page, {
      name: `Syl topics framework ${tag}`,
      item_type: 'framework',
    });
    const subject = await makeItem(page, {
      name: `Syl topics subject ${tag}`,
      item_type: 'subject',
      parent_id: framework.id,
      is_syllabus: true,
    });
    await openWorkspace(page, subject.id);

    // A topic is added from the syllabus's Add Content menu; it holds chapters and sub-topics.
    await main(page).getByRole('button', { name: 'Add Content' }).click();
    await page.getByRole('menuitem', { name: /^Topic A section/ }).click();
    const topicForm = main(page).locator('.ohmylms-ws-quickadd');
    await topicForm.getByLabel('Code').fill('1');
    await topicForm.getByLabel('Topic name').fill('Number');
    await topicForm.getByLabel('Topic name').press('Enter');
    await expect(row(page, 'Number')).toBeVisible({ timeout: 30000 });
    await topicForm.getByRole('button', { name: 'Done' }).click();
    await row(page, 'Number').click();
    await expect(title(page)).toHaveValue('Number');
    await expect(treeNode(page, '1 · Number')).toHaveAttribute('aria-current', 'true');

    // The topic's own notes save as you leave the field.
    const notes = page.getByRole('textbox', { name: 'Description' });
    await notes.fill('Whole numbers, fractions and sets.');
    await notes.press('Tab');
    await expect(main(page).locator('.ohmylms-ws-note', { hasText: 'Saved' }).first()).toBeVisible({
      timeout: 30000,
    });
    const topicId = (await api(page, { path: `${base}/curriculum/tree` })).items.find(
      (entry) => entry.name === 'Number' && entry.parent_id === subject.id,
    ).id;
    const topic = (await api(page, { path: `${base}/curriculum/items/${topicId}` })).item;
    expect(topic.description).toBe('Whole numbers, fractions and sets.');

    // A chapter inside the topic: Add Chapter puts it into the topic the selection is in.
    await page.getByRole('button', { name: 'Add Chapter' }).click();
    await expect(side(page).getByText('Goes into 1 · Number.')).toBeVisible();
    const sideForm = side(page).locator('.ohmylms-ws-quickadd');
    await sideForm.getByLabel('Code').fill('C1.1');
    await sideForm.getByLabel('Chapter name').fill('Types of number');
    await sideForm.getByLabel('Chapter name').press('Enter');
    await expect(title(page)).toHaveValue('Types of number', { timeout: 30000 });
    await expect(page.locator('.ohmylms-ws-crumbs')).toContainText('1 · Number');
    await sideForm.getByRole('button', { name: 'Done' }).click();

    // The syllabus itself shows its course: draft, not published, with what it holds.
    await treeNode(page, subject.name).click();
    const course = page.locator('.ohmylms-ws-course');
    await expect(course).toContainText('Course draft');
    await expect(course).toContainText('Not published yet');
    await expect(course.locator('.ohmylms-ws-facts div', { hasText: 'Chapters' })).toContainText(
      '1',
    );
    // The course is opened where every course is: its own editor, listed in the Courses tab.
    await expect(course.getByRole('link', { name: 'Course settings' })).toHaveAttribute(
      'href',
      /#\/course-edit\/\d+\/settings$/,
    );
    await expect(course.getByRole('link', { name: /catalog/i })).toHaveCount(0);
    await expect(course.getByRole('button', { name: 'Publish course' })).toBeEnabled();

    // Deleting the topic asks what happens to what is under it.
    await row(page, 'Number').click();
    await page.getByRole('button', { name: 'Topic actions' }).click();
    await page.getByRole('menuitem', { name: 'Delete topic…' }).click();
    const dialog = page.getByRole('dialog');
    await expect(dialog).toContainText('1 chapter will be deleted');
    await dialog.getByRole('button', { name: 'Delete topic' }).click();
    await expect(treeNode(page, '1 · Number')).toHaveCount(0, { timeout: 30000 });
    await expect(title(page)).toHaveValue(subject.name);
    const catalog = await api(page, {
      path: `${base}/content-hub/catalog/${
        (await api(page, { path: `${base}/curriculum/items/${subject.id}/syllabus` })).course.id
      }`,
    });
    expect(catalog.chapters).toEqual([]);
    expect(problems).toEqual([]);
  } finally {
    await cleanup(page);
  }
});

test('lessons are attached to a chapter, and a skill owns its lessons', async ({ page }) => {
  const problems = watch(page);
  await login(page);
  try {
    const framework = await makeItem(page, {
      name: `Syl lessons framework ${tag}`,
      item_type: 'framework',
    });
    const subject = await makeItem(page, {
      name: `Syl lessons subject ${tag}`,
      item_type: 'subject',
      parent_id: framework.id,
      is_syllabus: true,
    });
    const group = (
      await api(page, {
        path: `${base}/curriculum/items/${subject.id}/syllabus/groups`,
        method: 'POST',
        data: { name: 'Fractions', code: 'F1' },
      })
    ).group_id;
    await api(page, {
      path: `${base}/curriculum/items/${subject.id}/syllabus/groups/${group}/skills`,
      method: 'POST',
      data: { name: 'Add fractions', code: 'F1.1' },
    });
    const lesson = await makeLesson(page, `Adding fractions ${tag}`);
    await openWorkspace(page, subject.id);

    // Chapter level: Lesson, quiz or assignment from Add Content, as in the course.
    await treeNode(page, 'F1 · Fractions').click();
    await expect(main(page).getByText('Nothing is attached to the whole chapter.')).toBeVisible({
      timeout: 30000,
    });
    await main(page).getByRole('button', { name: 'Add Content' }).click();
    await page.getByRole('menuitem', { name: /^Lesson, quiz or assignment/ }).click();
    const attach = page.getByRole('dialog', { name: 'Attach content' });
    await attach.getByLabel(new RegExp(`Adding fractions ${tag}`)).check();
    await attach.getByRole('button', { name: 'Attach 1' }).click();
    const attached = main(page).locator('.ohmylms-catalog-item');
    await expect(attached).toContainText(`Adding fractions ${tag}`, { timeout: 30000 });
    const outline = await api(page, { path: `${base}/curriculum/items/${subject.id}/syllabus` });
    const chapterId = outline.contents[0].groups[0].chapter_id;
    const catalogOf = () => api(page, { path: `${base}/content-hub/catalog/${outline.course.id}` });
    let catalog = await catalogOf();
    expect(catalog.attachments).toHaveLength(1);
    expect(catalog.attachments[0]).toMatchObject({
      type: 'lesson',
      content_id: lesson.id,
      chapter_id: chapterId,
      required: false,
    });
    await attached.getByLabel('Required').check();
    await expect.poll(async () => (await catalogOf()).attachments[0].required).toBe(true);
    await attached.getByRole('button', { name: /^Remove/ }).click();
    await expect(attached).toHaveCount(0);
    expect((await catalogOf()).attachments).toEqual([]);

    // Skill level: lessons are tagged to the skill, so they follow it everywhere.
    await treeNode(page, 'F1.1 · Add fractions').click();
    await expect(title(page)).toHaveValue('Add fractions');
    const owns = main(page).getByRole('region', { name: 'What this skill owns' });
    await expect(owns.getByText('No lessons teach this skill yet.')).toBeVisible({
      timeout: 30000,
    });
    await expect(owns.getByText(/No questions are mapped to this skill yet/)).toBeVisible();
    await owns.getByRole('button', { name: 'Add lesson' }).click();
    const picker = page.getByRole('dialog', { name: /^Add a lesson to/ });
    await picker.getByLabel('Search lessons').fill(`Adding fractions ${tag}`);
    await picker.getByRole('button', { name: `Add Adding fractions ${tag} to this skill` }).click();
    // While a dialog is open WordPress hides the page behind it from assistive technology, so the lesson that
    // was added is checked once the dialog is closed.
    await expect(picker.getByRole('button', { name: /Add Adding fractions/ })).toHaveCount(0, {
      timeout: 30000,
    });
    await picker.getByRole('button', { name: 'Done' }).click();
    await expect(owns.getByRole('link', { name: `Adding fractions ${tag}` })).toBeVisible({
      timeout: 30000,
    });
    // The picker also writes a new lesson (a draft) and tags it at once.
    await owns.getByRole('button', { name: 'Add lesson' }).click();
    await expect(
      picker.getByRole('button', { name: 'Create and add', exact: true }),
    ).toBeDisabled();
    await picker.getByLabel('New lesson title').fill(`Fraction games ${tag}`);
    await picker.getByRole('button', { name: 'Create and add' }).click();
    await expect(page.getByRole('dialog')).toHaveCount(0, { timeout: 30000 });
    await expect(page).toHaveURL(/#\/lesson-edit\/\d+\?returnTo=/);
    const newLessonId = Number(new URL(page.url()).hash.match(/lesson-edit\/(\d+)/)[1]);
    created.lessons.push(newLessonId);
    const draft = await api(page, { path: `${base}/lessons/${newLessonId}` });
    expect(draft.status).toBe('draft');
    expect(draft.type).toBe('text');
    const returnTo = new URLSearchParams(new URL(page.url()).hash.split('?')[1]).get('returnTo');
    await page.goto(`/wp-admin/admin.php?page=ohmylms#${returnTo}`);
    await expect(title(page)).toHaveValue('Add fractions');
    await expect(owns.getByRole('link', { name: `Fraction games ${tag}` })).toBeVisible({
      timeout: 30000,
    });
    const skill = outline.contents[0].groups[0].skills[0];
    const owned = (await api(page, { path: `${base}/skills/${skill.term_id}` })).lessons;
    expect(owned).toHaveLength(2);
    expect(owned).toContain(lesson.id);
    expect(owned).toContain(newLessonId);
    await owns.getByRole('button', { name: `Take Adding fractions ${tag} off this skill` }).click();
    await expect(owns.getByRole('link', { name: `Adding fractions ${tag}` })).toHaveCount(0, {
      timeout: 30000,
    });
    expect((await api(page, { path: `${base}/skills/${skill.term_id}` })).lessons).toHaveLength(1);

    // Which skills the course requires: a course can only be published once one is required, and a required
    // skill needs approved questions. Set from the skill, then for a whole chapter.
    const firstSkill = async () => (await catalogOf()).skills[0];
    const inCourse = main(page).getByRole('region', { name: 'This skill in the course' });
    expect((await firstSkill()).required).toBe(false);
    await inCourse.getByLabel('Learners must reach this skill to finish the course').check();
    await expect.poll(async () => (await firstSkill()).required).toBe(true);
    await inCourse.getByLabel('Target').selectOption('mastered');
    await expect.poll(async () => (await firstSkill()).target).toBe('mastered');
    await treeNode(page, 'F1 · Fractions').click();
    await expect(main(page).getByText('1 of 1 required for the course')).toBeVisible({
      timeout: 30000,
    });
    await expect(main(page).locator('.ohmylms-ws-row', { hasText: 'Add fractions' })).toContainText(
      'Required',
    );
    await main(page)
      .getByRole('button', { name: 'Skills the course requires in this chapter' })
      .click();
    await page.getByRole('menuitem', { name: 'Make every skill in this chapter optional' }).click();
    await expect.poll(async () => (await firstSkill()).required).toBe(false);
    await expect(main(page).getByText('0 of 1 required for the course')).toBeVisible({
      timeout: 30000,
    });
    await main(page)
      .getByRole('button', { name: 'Skills the course requires in this chapter' })
      .click();
    await page.getByRole('menuitem', { name: 'Require every skill in this chapter' }).click();
    await expect.poll(async () => (await firstSkill()).required).toBe(true);
    // Publishing says what the course still needs: here, approved questions for the required skill.
    await treeNode(page, subject.name).click();
    await expect(
      page.locator('.ohmylms-ws-facts div', { hasText: 'Required skills' }),
    ).toContainText('1', { timeout: 30000 });
    await main(page).getByRole('button', { name: 'Publish course' }).click();
    const publish = page.getByRole('dialog');
    await publish.getByRole('button', { name: 'Publish', exact: true }).click();
    await expect(publish).toContainText('approved questions', { timeout: 30000 });
    await publish.getByRole('button', { name: 'Cancel' }).click();
    expect(problems).toEqual([]);
  } finally {
    await cleanup(page);
  }
});

test('a CSV file is mapped, checked and imported, and importing it again changes nothing', async ({
  page,
}, testInfo) => {
  const problems = watch(page);
  await login(page);
  // Semicolons, a quoted cell with a comma, Cyrillic text, maths symbols and merged-cell blanks.
  const csv = [
    'Chapter code;Chapter;Skill ID;Ref;Objective',
    '11.1;Квадрат тэгшитгэл;М11.1А1;М11.1А;"Графикийг зөв зурах, дараа нь шалгах"',
    ';;М11.1А2;М11.1А;Үргэлж эерэг байх нөхцөлийг (a > 0, D < 0) хэрэглэх.',
    ';;М11.1Б1;М11.1Б;Тэнцэтгэл бишийг шилжүүлэх',
    '11.2;Тэгшитгэлийн систем;М11.2А1;М11.2А;Шулууныг зурах',
  ].join('\r\n');
  const file = path.join(os.tmpdir(), `ohmylms-syllabus-${tag}.csv`);
  fs.writeFileSync(file, '﻿' + csv, 'utf8');
  const bad = path.join(os.tmpdir(), `ohmylms-syllabus-bad-${tag}.csv`);
  fs.writeFileSync(bad, 'Skill ID,Objective\r\nX9,\r\nМ11.1А1,Fine\r\n', 'utf8');
  try {
    const framework = await makeItem(page, {
      name: `Syl import framework ${tag}`,
      item_type: 'framework',
    });
    const subject = await makeItem(page, {
      name: `Syl import subject ${tag}`,
      item_type: 'subject',
      parent_id: framework.id,
      is_syllabus: true,
    });
    await openWorkspace(page, subject.id);
    const header = page.locator('.ohmylms-ws-header');
    const more = header.getByRole('button', { name: 'More syllabus actions' });

    // The template downloads; the export waits for something to export.
    await more.click();
    await expect(page.getByRole('menuitem', { name: 'Export CSV' })).toBeDisabled();
    const [template] = await Promise.all([
      page.waitForEvent('download'),
      page.getByRole('menuitem', { name: 'Download CSV template' }).click(),
    ]);
    expect(template.suggestedFilename()).toMatch(/-template\.csv$/);
    expect(fs.readFileSync(await template.path(), 'utf8')).toContain(
      'content_code,content,group_code,group,skill_code,skill,description',
    );

    // Choose the file: the delimiter and the columns are worked out, and the first rows are shown.
    await header.getByRole('button', { name: 'Import CSV' }).click();
    const dialog = page.getByRole('dialog');
    await dialog.locator('input[type=file]').setInputFiles(file);
    await expect(dialog.getByText('Tell us what each column holds')).toBeVisible();
    const roles = await dialog
      .locator('.ohmylms-syl-map select')
      .evaluateAll((selects) => selects.map((select) => select.options[select.selectedIndex].text));
    expect(roles).toEqual([
      'Content code',
      'Content (topic or chapter)',
      'Skill code',
      'Skill group code',
      'Skill',
    ]);
    await expect(dialog.getByLabel('The first row holds column titles')).toBeChecked();
    await expect(dialog.locator('.ohmylms-syl-map tbody tr')).toHaveCount(4);
    await expect(dialog.getByText('Графикийг зөв зурах, дараа нь шалгах')).toBeVisible();

    // Without a skill column nothing can be checked.
    const skillSelect = dialog.locator('.ohmylms-syl-map select').nth(4);
    await skillSelect.selectOption('');
    await expect(dialog.getByRole('button', { name: 'Check the file' })).toBeDisabled();
    await expect(dialog.getByText('Choose which column holds the skill')).toBeVisible();
    await skillSelect.selectOption('skill');

    // The review shows what would change before anything is saved.
    await dialog.getByRole('button', { name: 'Check the file' }).click();
    await expect(dialog.getByText('Ready to import 4 rows')).toBeVisible({ timeout: 30000 });
    const report = (label) =>
      dialog
        .locator('.ohmylms-syl-report tbody tr', {
          has: page.getByRole('rowheader', { name: label, exact: true }),
        })
        .locator('td')
        .allInnerTexts();
    expect(await report('Contents')).toEqual(['2', '0', '–', '0']);
    expect(await report('Skill groups')).toEqual(['3', '0', '–', '0']);
    expect(await report('Skills')).toEqual(['4', '0', '0', '0']);
    expect(
      (await api(page, { path: `${base}/curriculum/items/${subject.id}/syllabus` })).totals.skills,
    ).toBe(0);
    await testInfo.attach('import-review', {
      body: await page.screenshot(),
      contentType: 'image/png',
    });

    await dialog.getByRole('button', { name: 'Import', exact: true }).click();
    await expect(dialog.getByText('Imported.')).toBeVisible({ timeout: 60000 });
    await dialog.getByRole('button', { name: 'Done' }).click();
    await expect(page.locator('.ohmylms-ws-header-title p')).toContainText(
      '2 topics · 3 chapters · 4 skills',
    );
    // The outline shows what was imported, and the course has a chapter for each chapter and a skill for each skill.
    await treeNode(page, '11.1 · Квадрат тэгшитгэл').click();
    await expect(main(page).locator('.ohmylms-ws-rows .ohmylms-ws-row-title')).toHaveText([
      'М11.1А',
      'М11.1Б',
    ]);
    await treeNode(page, subject.name).click();
    const facts = page.locator('.ohmylms-ws-facts');
    await expect(facts.locator('div', { hasText: 'Chapters' })).toContainText('3', {
      timeout: 30000,
    });
    await expect(facts.locator('div', { hasText: /^Skills/ })).toContainText('4');

    // Everything landed as the file said, including the quoted comma, the maths text and the filled-down cells.
    const outline = await api(page, {
      path: `${base}/curriculum/items/${subject.id}/syllabus`,
    });
    const first = outline.contents.find((content) => content.code === '11.1');
    expect(first.name).toBe('Квадрат тэгшитгэл');
    expect(first.groups.map((group) => group.name)).toEqual(['М11.1А', 'М11.1Б']);
    expect(first.groups[0].skills.map((skill) => skill.name)).toEqual([
      'Графикийг зөв зурах, дараа нь шалгах',
      'Үргэлж эерэг байх нөхцөлийг (a > 0, D < 0) хэрэглэх.',
    ]);
    expect(
      outline.contents.find((content) => content.code === '11.2').groups[0].skills[0].code,
    ).toBe('М11.2А1');
    const catalog = await api(page, { path: `${base}/content-hub/catalog/${outline.course.id}` });
    expect(catalog.chapters.map((chapter) => chapter.name)).toEqual(['М11.1А', 'М11.1Б', 'М11.2А']);
    expect(catalog.skills).toHaveLength(4);

    // The same file again has nothing to do.
    await header.getByRole('button', { name: 'Import CSV' }).click();
    await page.getByRole('dialog').locator('input[type=file]').setInputFiles(file);
    await page.getByRole('dialog').getByRole('button', { name: 'Check the file' }).click();
    await expect(page.getByRole('dialog').getByText(/there is nothing to import/)).toBeVisible({
      timeout: 30000,
    });
    await expect(
      page.getByRole('dialog').getByRole('button', { name: 'Import', exact: true }),
    ).toBeDisabled();
    await page.getByRole('dialog').getByRole('button', { name: 'Back' }).click();

    // A file with a problem is refused with the row number, and nothing is imported.
    await page.getByRole('dialog').getByRole('button', { name: 'Choose another file' }).click();
    await page.getByRole('dialog').locator('input[type=file]').setInputFiles(bad);
    await page.getByRole('dialog').getByRole('button', { name: 'Check the file' }).click();
    await expect(
      page.getByRole('dialog').getByText(/Row 2: Skill code “X9” has no skill name/),
    ).toBeVisible({ timeout: 30000 });
    await expect(page.getByRole('dialog').getByText(/Nothing was imported/)).toBeVisible();
    await expect(
      page.getByRole('dialog').getByRole('button', { name: 'Import', exact: true }),
    ).toBeDisabled();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog')).toHaveCount(0);

    // The export reads back as the same syllabus: file name, byte order mark for Excel, codes and text.
    await more.click();
    const [exported] = await Promise.all([
      page.waitForEvent('download'),
      page.getByRole('menuitem', { name: 'Export CSV' }).click(),
    ]);
    expect(exported.suggestedFilename()).toMatch(/-export\.csv$/);
    const text = fs.readFileSync(await exported.path(), 'utf8');
    expect(text.charCodeAt(0)).toBe(0xfeff);
    expect(text).toContain('М11.1А2');
    expect(text).toContain('"Графикийг зөв зурах, дараа нь шалгах"');
    expect(problems).toEqual([]);
  } finally {
    fs.rmSync(file, { force: true });
    fs.rmSync(bad, { force: true });
    await cleanup(page);
  }
});
