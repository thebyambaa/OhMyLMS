# Sample course walkthrough: flaws found

Run on 800.local, 2026-11-25. Course 23933 "Sample Course: All Features": one unit, one
lesson page (23936) with core and OhMyLMS blocks, one quiz (23937) with 13 questions.
Status: FIXED (verified in the browser) or NOT A DEFECT (explained). Nothing is deferred.

## Course builder

| # | Flaw | Status |
|---|------|--------|
| B1 | Stray "Example course editor panel" text under every builder step | NOT A DEFECT. It comes from the opt-in `modules/examples` module, which this test site enables (it also adds the "Example number" question type). |
| B2 | A new unit is named "Untitled" instead of asking for a title | FIXED: the outline shows an unnamed default unit as "New unit" with a "Name this unit" placeholder; the stored name is unchanged so `BlankDraft::discard()` still works |
| B3 | The "Draft saved" banner inserted above the content and shifted the layout under the pointer | FIXED: the message is a fixed toast (`.oml-outline-toast`), the outline no longer moves |
| B4 | Lesson rows have no "Block editor" link while quiz rows do | NOT A DEFECT. A lesson's title link opens its block editor; a quiz's title opens its settings, so only quizzes need the extra link. |
| B5 | Publish dialog does not warn about draft questions or a pass mark of 0 | NOT A DEFECT on review. Empty quizzes already block publishing, draft-status questions publish normally, and a pass mark of 0 now reads "no pass mark set" (P1). |

## Lesson page

| # | Flaw | Status |
|---|------|--------|
| L1 | Callout tone had no front-end styling | FIXED: `assets/css/lesson-blocks.css` |
| L2 | Math block printed raw TeX | FIXED: `render_math()` emits the marker the runtime typesets |
| L3 | A YouTube link became an unplayable `<video>` | FIXED: `LessonBlocks::embed_url()`, with `tests/php/lesson-video-embed-unit.php` |
| L4 | Core Button looked unreadable | NOT A DEFECT. Buttons are outside the lesson allowlist. |
| L5 | Callout, Math and Video were bare inputs in the editor | FIXED: `assets/js/lesson-blocks.js` |
| L6 | The "Choose a context" panel used oversized text | FIXED: `learning-context.css` |
| L7 | Unknown callout tone falls back to Info | NOT A DEFECT. Three tones by design. |
| L8 | Account and checkout blocks offered in the lesson inserter | NOT A DEFECT. `LessonBlocks::ALLOWED` restricts the inserter. |

## Quiz authoring

| # | Flaw | Status |
|---|------|--------|
| Q1 | The optional Name is copied into the question body | NOT A DEFECT. The dialog says the name starts the question and can be edited; the visible problem was Q2. |
| Q2 | Question text printed twice when the body equals the title | FIXED: `templates/single-lesson/quiz-form.php` |
| Q3 | Choice questions start with "Option 1" and "Option 2" | NOT A DEFECT. Option 1 starts marked correct, and the server needs at least two options. |
| Q4 | Sticky question toolbar lets the settings header show through | NOT A DEFECT. Normal sticky scrolling: content passes under the bar. |
| Q5 | Question list actions were cramped and misaligned | FIXED: one wrapping flex group (`.oml-question-actions`) |
| Q6 | "Statement" demands an expected answer yet was listed under not-scored content | FIXED: it is a scored typed-answer type, so the picker moves it to "More types" with the hint "A typed answer checked against the expected answers you list." |
| Q7 | About 19 types answered "Invalid question settings." with no reason | FIXED: `Authoring::check()` keeps `WP_Error` messages and `ExtendedQuestions::settings_hint()` says what each type needs, for example "Add 1 to 20 accepted words." |
| Q8 | Raw schema wording such as "expected is a required property of settings" | NOT A DEFECT for core: it comes from the example module's `example-number` type. |
| Q9 | Passage says "A structured question needs at least one part" | FIXED: "A passage needs at least one question part (50 at most)." |
| Q10 | Fill in the blank saved through the API ignores `{blank}` in the body unless options are sent | FIXED as wording: the server reads blanks from the question name, and the message now says so. The editor already copies the statement into the name. |

## Quiz learner view

| # | Flaw | Status |
|---|------|--------|
| P1 | A 0 of 15 attempt showed "Result: Pass" | FIXED: `templates/single-lesson/content-quiz.php` |
| P2 | Matching circles are not focusable controls | NOT A DEFECT. Options are `role="button"` with `tabindex="0"` and a keydown handler. |
| P3 | "Choose a context" showed one large inline link | FIXED: choices are button-style links in `learning-context.css` |
