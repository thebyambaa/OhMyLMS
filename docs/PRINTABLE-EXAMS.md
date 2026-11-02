# Printable exams: design

Status: **phases 1 to 5 built** (A4 canvas, renderers for most types, paper settings, print page, paper blocks,
versions and answer sheets) and figures for the visual types. Still printed as a statement with lines: graphing,
hotspot, labeling, drawing, match table, hot text and the game and poll activities. Written October 24, 2026.

## Visual question figures

- `assets/src/features/paper/paperVisual.mjs` models number line, shade a model, count with blocks, set the clock,
  make an amount, fill to a level, build a chart and build on a grid from the fields of the learner view
  (`Visual::public_view()`, same defaults), so the editor and the student print page draw the same figure. The
  server already sends those learner views without targets, so no server change was needed.
- `PaperVisualViews.jsx` draws static SVGs: a ticked number line, bar, grid or circle parts, a clock face (with a
  digital box when set), a measuring jug with its scale, chart axes (with the data table when the author shows it)
  and a squared grid; count with blocks prints a place value table and make an amount a box per note or coin and a
  total line. Answer keys mark the target point, shade the parts, draw the clock hands, fill the jug, draw the
  bars, fill the place values and the fewest notes and coins, and state the grid shape's area and perimeter.

## Phase 5 as built

- **Versions** (Printable paper setting, 1 to 4). Version A is the paper as laid out in the editor. Versions B to D
  (`versionItems()`) shuffle each run of consecutive questions in place: headers, sections, statements, page
  breaks, wide items in columns and other blocks stay, and questions never leave their section or column.
  Choice options are reordered too (true or false keeps its order), and jumbled lists use a per-version seed
  (`questionSeed()` / `PrintPaper::seed()`), so each version has its own reorder, matching and sorting order.
- Print links take `version=B` and so on; `PrintPaper` accepts only versions the paper offers. Numbering, section
  totals and keys follow the printed order of the version.
- **Answer sheet** (`variant=sheet`): name and candidate number, then a row of lettered circles per choice question
  in the version's numbering (squares for select-all questions), and a note naming the questions answered on the
  paper. **Marking sheet** (`variant=sheet-key`) fills the correct circles, for marking by overlay.
- The editor's Printable paper panel has a version picker and Print paper, answer key, answer sheet and marking
  sheet buttons. The editor shows version A; the header shows the version when there is more than one.

## Phase 4 as built

- `assets/src/features/paper/paperTypes.mjs` prints matching, reorder, fill-in-the-blank, dropdown blanks,
  multi-blank (inline and table), sort into groups, build from tiles, math expression, crossword and wordsearch.
  Each form is built from the learner view: the editor and the answer key derive it from the full question
  (`learnerSettings()`, mirroring `Interactive::public_view()` and `WordPuzzle::public_view()`), while the student
  print page receives the server's learner view (`public: true`), so the layout is the same either way.
- Lists are jumbled by a fixed FNV-1a hash of the question ID and item text (`jumble()`), so the editor and the
  print page agree. Reorder steps and matching answers must never print in answer order, which only the full
  question shows, so for the student paper the server jumbles them with the identical `PrintPaper::jumble()`
  (rotating an order that would match) and strips matching pairs from the options. Reference values in
  `tests/js/paper-types.test.mjs` and `tests/php/paper-print-unit.php` keep both implementations equal.
- `PaperTypedViews.jsx` renders the bodies: matching columns with answer boxes, numbered ordering boxes, choice
  lists under numbered blanks, blank tables, group tables, tile rows, crossword grids with clue lists and
  wordsearch grids. Answer keys fill bubbles, crossword squares and wordsearch cells, and list answers.

## Phase 3 as built

- `OhMyLMS\Exams\PaperBlocks` registers the seven blocks server-side (so the exam inserter offers them) with render
  callbacks that print nothing online, except a columns section, whose contents stay in place. The editor side
  (`assets/src/features/paper/paperBlocks.jsx`) registers only on exam screens, where the print links are localized.
- `PaperBlockViews.jsx` holds one view per block, used by the editor (with RichText fields) and the print page (with
  saved values), so both lay out the same markup and CSS. Text defaults live in `paperTextDefaults()`, because
  documents do not store unchanged defaults.
- Header: school and subtitle are editable; the title is the exam title, time comes from Quiz settings and total
  marks add up live. Candidate fields: name, class, candidate number (boxes) and date.
- Instructions and formula sheet hold core blocks (a list by default); the formula sheet starts a new page unless
  switched off. Sections total the marks of the questions after them, up to the next section.
- Columns section: 1 to 3 columns, **Flow** (CSS multi-column) or **Side by side** (grid), an optional line between
  flowing columns, and **Span all columns** on any block directly inside a flowing section (`ohmylmsPaperSpan`;
  kept on question blocks by `NativeQuizEditor::normalize()`).
- The canvas treats page breaks and new-page formula sheets as sheet breaks. Numbering and totals walk blocks in
  document order with nested blocks in place (core's `getClientIdsWithDescendants()` lists nested blocks last).
- The print page receives paper blocks as nested items; their attributes are filtered on the server
  (`PrintPaper::paper_attributes()`: bold, italic and line breaks only), since block comment attributes are not
  filtered when a document is saved.
- Known limit: a columns section taller than one page is balanced over its whole height in the editor, while the
  printed columns fill each page in turn.

## Phase 2 as built

- `OhMyLMS\Exams\PrintPaper` serves `?ohmylms_print=ID&variant=paper|key&_wpnonce=…` (add `&print=1` to open the
  print dialog). It needs edit access to the exam and an exam-specific nonce, sends no-cache and noindex headers,
  and prints only the plugin's paper and math styles, never the theme's.
- Questions are what the exam delivers, as in Preview quiz: the pinned version, else the current content. The
  student paper is built from `QuestionSnapshot::student_view()` and keeps only name, description, public settings
  and options, so answers never reach the page. The answer key uses the full snapshot, and only for questions the
  person may use in the bank. Both variants use the same template seed, so randomized numbers match.
- Core blocks print as rendered, filtered HTML in document order. Blocks nested in layout containers print in order
  until phase 3 adds multi-column sections.
- The print page is server-rendered HTML (`PrintPaper::document()`, `OhMyLMS\Exams\PaperRenderer`, with the model
  in `OhMyLMS\Exams\PaperModel`). It prints the same markup as the editor's `PaperQuestion` renderer;
  `tests/fixtures/paper-models.json` keeps the PHP and JS models equal. The `ohmylms/paper-print` module only waits
  for math, fonts and images, then enables **Print**. The math loader is printed only when the sheet has math,
  and typesets the whole sheet: questions, paper blocks such as the formula sheet, and other content.
- The learner's Paper View is rendered by the same `PaperRenderer` (`PaperPresentation::sheet()`): the quiz
  template places each player question box in its printed place, choice options answer by their printed letters,
  and other types keep the player's controls under the printed statement. The `ohmylms/paper` module adds the
  question navigator, flags for review and timer announcements; the quiz player keeps autosave and submission.
- `@page` sets size and margins; page numbers use `@page` margin boxes (`counter(page)`/`counter(pages)`). The
  translated footer is escaped for the CSS string and for the style element.
- Print buttons: **Print paper** and **Print answer key** in the editor's Printable paper panel (they save first,
  like Preview quiz) and on Content Hub → Exams (`print_urls` in the exam summary).
- Checked by printing to PDF from the browser (Save as PDF): pagination, page footer and layout as printed.

## Phase 1 as built

- `assets/src/features/paper/paperModel.mjs`: settings normalization, the printed model of each question type
  and `paginate()`. Covered by `tests/js/paper.test.mjs`, including a check that student models carry no answers.
- `assets/src/features/paper/PaperQuestion.jsx`: the shared renderer. `PaperCanvas.jsx`: the Paper view toggle,
  sheet layout inside the editor iframe, the **Printable paper** sidebar panel and the per-block hooks.
  `store.js`: editor-only view state (`ohmylms/paper`); nothing in it is saved with the exam.
- `assets/css/paper.css`: canvas rules under `.is-ohmylms-paper`, printed question classes `ohmylms-print-*`
  (the `ohmylms-paper-*` names were already used by the Question paper dialog).
- Settings save in `_quiz_settings['paper']`, validated by `OhMyLMS\Exams\PaperSettings::sanitize()`.
- The Paper view choice is remembered per exam and person in `localStorage`; **Open this exam in paper view** is
  the saved default for people who have not chosen.
- Known limits: math (`$…$`) is not typeset inside the editor iframe. This is true of the normal editor canvas too,
  because the math runtime cannot construct its elements in the iframe; the print page (phase 2) is a normal
  document and will typeset. The editor's root container is `.alignfull`, so the canvas overrides the frontend
  full-width rules in `blocks-frontend.css` (`left`, body `overflow-x`).

Teachers lay out a paper version of an exam in the Gutenberg exam editor and see it as it will print. The
printed paper comes from the same questions and pinned versions as the online exam. No second copy is stored.

## Decisions

| Question | Decision |
|---|---|
| Where papers are authored | A **Paper view** of the existing native exam editor (`ohmylms-quiz`, `NativeQuizEditor`). There is no separate paper post type. |
| How the PDF is produced | **Browser print.** An editor-only print page uses `@page` CSS, and the user prints or saves it as PDF. No server PDF library. |
| First release | **Student paper** and **answer key / mark scheme**. Shuffled versions and bubble answer sheets come later. |

## How it fits the current editor

- Question blocks in a quiz document hold only references (`questionId`, `save: () => null`). Statements and answers
  are bank versions pinned per exam (`exam.version_ids`). The paper reads those pinned versions, the same way
  `ExamPaperDialog.jsx` does with `loadVersion()` and `loadMath()`.
- Question rendering already lives in JS. Paper renderers are therefore JS too, and **one renderer per question type is
  shared by the editor canvas and the print page**. This sharing is what makes the editor WYSIWYG.
- The online player builds from placements. Print-only blocks are ignored online: they render nothing in the player and
  never become placements.

## Pieces

### 1. Paper renderers (`assets/src/features/paper/renderers/`)

These are pure components, `( { snapshot, options, showAnswers } ) => element`, registered by question type. Third-party
types can register one through the existing extension registry (`registerPaperRenderer( type, Component )`). Types
without a renderer are shown in the editor as **Not printable**, with the reason. Printing still works; that question
prints its statement and a blank answer space.

| Type(s) | On paper | Answer key adds |
|---|---|---|
| single-choice, multiple-choice, true-false | Lettered options with bubbles (circles, or squares for multiple select) | Filled bubbles |
| short-text, text-entry, numerical | Answer line or box, with the unit after it | Accepted answers and tolerance |
| long-text | Ruled lines: `options.linesPerMark × marks`, at least 3 | Rubric |
| fill-in-the-blank, dropdown-blanks, multi-blank | Statement with numbered blanks (dropdowns list their choices) | Blank answers |
| matching | Two lettered columns and an answer row | Pairs |
| reorder | Items with ordering boxes | Correct order |
| categorize | Group table to write in | Grouping |
| structured | Parts `(a)`, `(b)` with `[n]` marks right-aligned; each part uses its kind's renderer | Per-part answer and rubric (mark scheme row) |
| crossword, wordsearch | Grid and clues | Solved grid |
| statement | Text only | — |
| expression, build-expression | Answer box | Accepted forms |
| number-line, shade-model, set-clock, fill-level, build-chart, grid-build, count-blocks, make-amount | A static SVG blank of the figure, where the existing figure code can produce one; otherwise **Not printable** in v1 | The answer drawn on the figure, where possible |

The student-paper output never contains answers, from the editor or the print page. See security below.

### 2. Print-only blocks (`assets/src/blocks/paper-*`)

These blocks are allowed in quiz documents through `NativeQuizEditor::quiz_blocks`.

- `ohmylms/paper-header`: school or organisation, exam title, subject or code, session, duration, total marks
  (computed live), and name, class and candidate-number boxes.
- `ohmylms/paper-instructions`: an editable rich-text list with a default instruction set.
- `ohmylms/paper-section`: a section heading with an optional marks subtotal.
- `ohmylms/paper-columns`: a multi-column section. Questions and other blocks go inside it (`InnerBlocks`).
  Settings:
  - `columns`: 1 to 3.
  - `gap`.
  - `rule`: a vertical line between columns.
  - `flow`:
    - **Flow** (default): CSS multi-column. Questions fill the first column top to bottom, then continue in the next.
      Each question uses `break-inside: avoid`, so it is never split across columns.
    - **Side by side**: a fixed grid row, for example two short questions next to each other.

  The section can stop mid-page and is followed by full-width content. Question numbering and marks totals continue
  across sections, because placements follow document order, including nested slots, as the native quiz editor
  already reconciles them. Wide items (tables, figures, long-answer lines) can be marked **Span all columns**
  (`column-span: all`). The online player ignores the column layout and shows its questions in order.
- `ohmylms/paper-page-break`: a forced page break.
- `ohmylms/paper-answer-space`: ruled lines, a squared grid or a blank box of a set height, for extra working space.
- `ohmylms/paper-formula-sheet`: free content that prints on its own page.

Core text, image, table and math blocks already in the document print as they are.

### 3. A4 paper canvas in the editor

- The editing surface is the sheet itself: the editor canvas is an A4 (or Letter) page with the paper's margins.
  Teachers insert questions and paper blocks straight onto it, and each block shows its printed form as they type.
- Exams created as **Printable paper** open on the paper canvas by default. Any other exam can switch to it with the
  **Paper view** toolbar toggle, which adds `is-ohmylms-paper` to the editor iframe body and loads `paper-editor.css`.
- When a question on the sheet is selected, its answers, marks and print options are edited in the block sidebar, so the
  page stays exactly as it will print.
- **Pagination preview:** a `ResizeObserver` measures block heights and draws page-boundary markers and page
  numbers. Questions use `break-inside: avoid`. A question taller than one page gets a warning. The markers are a
  close preview; the browser's print engine has the final say.
- **Paper settings** document panel, stored with the quiz settings (new `paper` key in `_quiz_settings`, written
  through the existing `ohmylms_quiz` REST field):
  `size` (A4 or Letter), `margins`, `font_size`, `numbering` (1, 2, 3 or Q1), `show_marks`, `lines_per_mark`,
  `footer` (title, page X of Y) and `answer_key_style` (inline answers or mark-scheme table).

### 4. Print page

- Route: `?ohmylms_print=ID&variant=paper|key&_wpnonce=…`. It needs `edit_post` on the quiz and a valid nonce, and it
  is never cached.
- It renders a minimal document with the theme's styles removed and mounts the same renderers. It waits for math and
  figures to load before enabling **Print** and offering auto-print.
- `@page { size; margin }` comes from the paper settings. Page numbers use `@page` margin boxes
  (`counter(page)` / `counter(pages)`) where the browser supports them.
- Buttons: **Print paper** and **Print answer key** in the editor's Paper settings, plus a **Print** action on
  Content Hub → Exams.

## Security

- Answer data reaches the client only for users who can edit the quiz. This is already true of `loadVersion`.
- The student-paper variant uses only public fields: statement, options, and part `id`/`label`/`prompt`/`kind`/`marks`/`unit`
  (the same field list as `Structured` public output). Renderers receive `showAnswers: false` and an answer-free snapshot.
  Hiding answers with CSS alone is not enough.
- The print route checks the capability and nonce on the server, sanitizes settings, and escapes all labels.

## Build phases

1. **A4 canvas and core renderers.** The paper canvas in Gutenberg (sheet sizing, margins, page markers), renderers
   for choice, true/false, short, long, numerical and structured types, and Paper settings. Teachers can add questions
   onto the sheet and see them as printed.
2. **Print page.** The print route with student paper and answer key variants, and the Print buttons.
3. **Print-only blocks.** Header, instructions, section, multi-column section, page break, answer space and formula
   sheet. Page markers on the canvas account for column flow.
4. **Remaining types.** Matching, reorder, blanks, categorize, crossword, wordsearch and expression, then static figures
   for the visual types.
5. **Later.** Shuffled versions A/B/C with per-version keys, and a bubble answer sheet.

## Verification

- JS unit tests for each renderer: the student variant contains no answer fields, and the key variant shows them.
- A PHP integration test for the print route: denied for anonymous users and non-editors, and the nonce is required.
- A Playwright test: toggle Paper view, add a page break and header, open the print page, and check page count and
  layout with `page.pdf()`.
