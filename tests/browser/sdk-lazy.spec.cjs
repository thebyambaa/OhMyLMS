const { test, expect } = require('@playwright/test');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const wordpress = path.resolve(root, '../../../wp-includes/js/dist/vendor');

// Exercise the production SDK with WordPress's React, without a database or login.
async function harness(page, directory, failCourses = false) {
  const requests = [];
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await page.route('https://ohmylms.test/**', async (route) => {
    const pathname = new URL(route.request().url()).pathname;
    if (pathname === '/') {
      return route.fulfill({
        contentType: 'text/html',
        body: `<!doctype html><html><body>
        <div id="app"></div><script src="/react.js"></script><script src="/react-dom.js"></script>
        <script>
          window.wp = {
            element: { ...React, ...ReactDOM },
            i18n: { __: text => text, sprintf: (text, value) => text.replace('%d', value) },
            data: {}, components: {}, apiFetch: async () => ({})
          };
          window.ohmylmsAssessment = ${JSON.stringify(directory === 'assets/dist/admin' ? { bankUi: '1', skills: '1' } : { bankUi: true, skills: true })};
          window.coreChunkTraffic = 0;
          window.webpackChunkohmylms = [];
          window.webpackChunkohmylms.push = () => window.coreChunkTraffic++;
        </script>
        <script src="/plugin/${directory}/extensions.js?ver=test"></script>
      </body></html>`,
      });
    }
    if (pathname === '/react.js' || pathname === '/react-dom.js') {
      return route.fulfill({
        path: path.join(wordpress, pathname.slice(1)),
        contentType: 'application/javascript',
      });
    }
    const prefix = `/plugin/${directory}/`;
    if (!pathname.startsWith(prefix)) return route.abort();
    const relative = pathname.slice(prefix.length);
    requests.push(relative);
    if (failCourses && relative.startsWith('chunks/courses.')) return route.abort();
    if (relative.includes('..')) return route.abort();
    return route.fulfill({
      path: path.join(root, directory, relative),
      contentType: 'application/javascript',
    });
  });
  await page.goto('https://ohmylms.test/');
  await page.waitForFunction(() => window.ohmylms?.extensions);
  return { requests, errors };
}

async function mountCourseLevel(page) {
  await page.evaluate(() => {
    const controls = {
      FlexWP: ({ children }) => React.createElement('div', null, children),
      FlexItemWP: ({ children }) => React.createElement('div', null, children),
      HeadingWP: ({ children }) => React.createElement('h2', null, children),
      TextWP: ({ children }) => React.createElement('p', null, children),
      SpacerWP: () => null,
      RadioGroupWP: ({ value, onChange, options }) =>
        React.createElement(
          'select',
          {
            value,
            onChange: (event) => onChange(event.target.value),
            'aria-label': 'Level',
          },
          options.map((option) =>
            React.createElement('option', { key: option.value, value: option.value }, option.label),
          ),
        ),
    };
    window.runtimeReads = 0;
    const Level = window.ohmylms.extensions.courseComponents.CourseLevel(() => {
      window.runtimeReads++;
      return { React, I: controls, b: wp.i18n };
    });
    window.factoryReads = window.runtimeReads;
    function Screen() {
      const [value, setValue] = React.useState('all');
      return React.createElement(Level, {
        experienceLevel: value,
        handleExperienceLevelChange: setValue,
      });
    }
    window.testRoot = ReactDOM.createRoot(document.getElementById('app'));
    window.testRoot.render(React.createElement(Screen));
  });
}

for (const directory of ['build/sdk', 'assets/dist/admin']) {
  test(`SDK loads features on demand from ${directory}`, async ({ page }) => {
    const { requests, errors } = await harness(page, directory);
    expect(requests).toEqual(['extensions.js']);
    const adminPages = await page.evaluate(() =>
      window.ohmylms.extensions.list('admin-page').map((item) => item.id),
    );
    expect(adminPages).toEqual(expect.arrayContaining(['question-bank', 'skills']));
    expect(adminPages).not.toContain('performance');
    await mountCourseLevel(page);
    await expect(page.getByRole('heading', { name: 'Level', exact: true })).toBeVisible();
    expect(await page.evaluate(() => window.factoryReads)).toBe(0);
    await page.getByLabel('Level', { exact: true }).selectOption('expert');
    await expect(page.getByLabel('Level', { exact: true })).toHaveValue('expert');
    expect(requests.filter((file) => file.startsWith('chunks/courses.'))).toHaveLength(1);
    expect(
      requests.some((file) => /chunks\/(settings|commerce|learning|question-bank)\./.test(file)),
    ).toBe(false);
    await page.evaluate(() => {
      const Bar = window.ohmylms.extensions.questionBankComponents.QuestionVersionBar;
      window.testRoot.render(React.createElement(Bar, { question: { temp: true } }));
    });
    await expect(page.getByText('New question', { exact: false })).toBeVisible();
    expect(requests.filter((file) => file.startsWith('chunks/question-bank.'))).toHaveLength(1);
    expect(errors).toEqual([]);
    expect(await page.evaluate(() => window.coreChunkTraffic)).toBe(0);
  });
}

test('a failed feature chunk displays a recovery action', async ({ page }) => {
  await harness(page, 'build/sdk', true);
  await mountCourseLevel(page);
  await expect(page.getByRole('alert')).toContainText('This screen could not be loaded.');
  await expect(page.getByRole('button', { name: 'Reload', exact: true })).toBeVisible();
});
