# Quiz and question modules

Default authoring now runs through `includes/Learning/NativeQuizEditor.php`, `NativeQuestionEditor.php`, their REST controllers, and `assets/src/features/learning/Native*` components. See [Gutenberg authoring](GUTENBERG-AUTHORING.md). The former quiz form editor is removed; its public factory redirects to Gutenberg. Shared answer and validation controls and exported utilities remain available for compatibility.

| Module | Implementation | Responsibility |
| --- | --- | --- |
| Quiz player | `assets/interactivity/quiz-player/` | Learner navigation, required answers, submission, timer, matching and reorder interactions |
| Quiz authoring | `assets/src/features/learning/NativeQuiz*` | Gutenberg settings, bank placement and legacy-route redirect; `quiz-editor/` retains public compatibility utilities |
| Question editor | `assets/src/features/question-editor/` | Question canvas/settings, answer editors, form workspace, preview and custom editor adapter |
| Question bank | `assets/src/features/question-bank/` | Search, creation dialogs, approval, versions, sharing and skill links |

Quiz and question editors have independent `index.js` interfaces and `components.json` runtime contracts. Each factory accepts `readRuntime` and returns a React component. These bridges use the existing WordPress store; saved payloads, REST routes and grading contracts stay the same. The question canvas retains a legacy quiz-placement removal action through the quiz API.

`window.ohmylms.extensions.quizEditorComponents` and `questionEditorComponents` expose the separate factory interfaces. The existing `quizComponents` interface combines both for compatibility. Old imports under `features/quizzes/`, the moved question-bank files and `extensions/QuestionEditor.jsx` forward to the canonical implementations. Add new code to the canonical modules.

The player exports `createQuizPlayer(runtime)` from `player.js`. It returns `{ state, actions, callbacks }`; its runtime supplies context/element access, event wrappers, answer validation, event emission and the scoped expiry action. `index.js` binds that interface to the WordPress Interactivity store. `state.js` owns reactive getters, `validation.js` owns required-answer checks, and `questionControls.js` exports `createQuestionControls(root, context)` for matching/reorder. The published `assets/interactivity/quiz.js` and `questions.js` URLs still initialize their original store namespaces.

## Extend a question type

Register its server render/validate/grade definition with `ohmylms_register_question_type`, then register its React authoring component before admin bootstrap:

```js
window.ohmylms.extensions.registerQuestionEditor('my-question', {
  label: 'My question',
  render: MyQuestionEditor,
});
```

`MyQuestionEditor` receives `{ question, value, onChange }`. `value` contains question settings; `onChange(settingsPatch)` merges settings through the existing authoring store and keeps the registered type ID. The existing server manifest controls which custom types are available. Register frontend validators and dynamic mount adapters through `ohmylms/interactivity`. See [EXTENSIONS.md](EXTENSIONS.md) and [INTERACTIVITY.md](INTERACTIVITY.md) for full registration contracts. Custom types retain the current selection and preview behavior; the static form dropdown/preview renderer is not an automatic plugin registry.

For a new built-in authoring component, add its factory to the appropriate module interface and its runtime contract to that module's manifest. For question form types, update `questionBlocks.mjs`, the appropriate answer controls and preview renderer. Keep bank management in the bank module and player interactions in the player module.

## Interactive question types

`dropdown-blanks`, `categorize`, `multi-blank` and `build-expression` are built-in "recognize and produce" types from the math.mn spec. They share one rules class, `includes/Assessment/Interactive.php` (grading, learner-safe view, authoring validation), and are registered by `QuestionTypes::register_interactive()`.

| Type | Learner does | Answer posted | Key stays server-side in |
| --- | --- | --- | --- |
| `dropdown-blanks` | Picks from a menu at each `{1}` marker | `slot id => chosen text` | `slots[].answer` |
| `categorize` | Taps/drops each item into a group (a menu per item works without JavaScript) | `item id => group id` | `key` |
| `multi-blank` | Fills `{a}` boxes in a sentence or table cells; numeric boxes use tolerance and fractions | `blank id => text` | `blanks` |
| `build-expression` | Taps tiles from a bank into the answer row | ordered list of tile texts | `correct`, `distractors`, `alternatives` |

Grading is whole-question by default, with per-item results returned for feedback; `partial_credit` (not on `build-expression`) awards the share of correct items. A type with answer keys inside public structures declares `public_view( $settings, $seed )` in its definition; `QuestionSnapshot::student_view()` merges it, and the templates call `Interactive::learner_settings()` so raw settings can never leak a key. List order (items, choices, tiles) is shuffled with a stable seed.

Player: `assets/js/interactive-controls.js` upgrades the native controls and also exposes `window.OhMyLMSInteractive.render/collect/enhance` for the practice runner. Editor: `question-editor/InteractiveEditors.jsx` with pure helpers in `interactiveModel.mjs`; add a type there, in `questionBlocks.mjs`, in `Interactive::TYPES` and in a `templates/single-lesson/quiz-loop/<type>.php`.

### Math expressions (CAS)

`expression` accepts a typed answer graded by algebraic equivalence, so `2x+6` matches `2(x+3)`. The engine is `includes/Assessment/Cas/Expression.php`: plain PHP, no external service, and the learner's text is parsed into a bounded tree and never evaluated as code.

- **Input:** ASCII (`2(x+3)`, `x^2`, `sqrt(x)`), Unicode (`×`, `÷`, `√`, `x²`, `π`) or the LaTeX a math keyboard produces (`\frac`, `\sqrt`, `^{}`, `\cdot`). The player shows a row of symbol keys under the field. A MathLive field can replace it later without server changes, because the server already accepts its LaTeX.
- **Equivalence:** two expressions are equal when they agree, within tolerance, at 14 deterministic sample points where both are defined. Equations are equal when left-minus-right agrees up to a non-zero constant, so `x=3` matches `2x+5=11`. Undefined points (divide by zero, root of a negative) are skipped, and an answer defined where the key is not is rejected.
- **Forms:** `expanded`, `factored`, `simplified` or `any` are checked on the learner's tree after equivalence, so the right value in the wrong shape is reported as a form problem. These are structural checks (no unexpanded brackets or uncombined like terms; a product of factors; no foldable arithmetic or unreduced fractions), not a full normal-form proof.
- **Where it applies:** the `expression` type; `build-expression` with "accept equivalent expressions"; and a `multi-blank` blank of kind `expression`.
- **External CAS:** the `ohmylms_cas_equivalent` filter receives `( null, $student, $key, $settings )`. Return `true` or `false` to decide equivalence (for SymPy, Giac or Maxima behind your own endpoint); return `null` to keep the built-in test. Form checks still run.
- **Limits:** 240 characters, 200 tokens, 400 nodes, depth 40. No calculus, no inequalities, no matrices. Functions: abs, sqrt, ln, log, exp, sin, cos, tan, asin, acos, atan; `e` is Euler's number.

Tests: `php tests/cas-unit.php` and the CAS section of `php tests/assessment-unit.php`.
### Visual and manipulative types

Eight types answer with a few numbers that the learner sets by dragging or tapping. Rules live in `includes/Assessment/Visual.php` (delegated from `Interactive`); the widgets are in `assets/js/interactive-visual.js`, registered with `window.OhMyLMSInteractive.register( type, { build( root, config ) } )`. The server renders an empty shell (`templates/single-lesson/quiz-loop/visual.php`) carrying only the public configuration in `data-config`; the widget writes hidden fields named `attempt[a][quiz_question][q][key]`.

| Type | Learner does | Answer posted | Graded |
| --- | --- | --- | --- |
| `number-line` | Drags a point (or uses arrow keys) | `value` | within `tolerance`, default half a tick |
| `shade-model` | Shades parts of a bar, grid or circle | `c0`, `c3`, … | number of shaded parts equals `answer` (any parts) |
| `count-blocks` | Adds thousands/hundreds/tens/ones blocks | one count per place | total equals `target`; `canonical` forbids more than 9 of a kind |
| `set-clock` | Drags the minute hand; the hour hand follows | `h`, `m` | within `tolerance` minutes, wrapping around the 12-hour face |
| `make-amount` | Taps bills and coins into a tray | `d1000`, `d500`, … | sum equals `target`, only listed denominations |
| `fill-level` | Sets a vertical slider; a jug fills | `value` | within `tolerance`, default one minor tick |
| `build-chart` | Drags or keys each bar to a height | one value per bar id | each bar equals its `values` entry; `show_table` shows the data above the chart |
| `grid-build` | Taps or paints squares | `r2c3`, … | `area`, `perimeter`, `rectangle`, `connected` conditions; `show_measures` shows the live area and perimeter |

Every widget works with pointer, touch and keyboard, and the required-answer check passes once a widget has been touched (`data-touched`). The editor previews each question with the same script the learners get; `AdminAssets` loads it on the plugin's admin screens. To add a type: a case in `Visual`, a `build` function, and an editor in `question-editor/VisualEditors.jsx`.

### Graphing, labelling and hotspots

Three more types are rules in `includes/Assessment/ExtendedQuestions.php`; the learner widgets are in `assets/interactivity/extended-questions.js`. All three are graded all-or-nothing (full marks or none) from the frozen settings.

| Type | Author sets | Learner does | Graded |
| --- | --- | --- | --- |
| `graphing` | `mode` (`points` or `line`), `min` and `max` (within -100 to 100), `points` (up to 50; `line` needs exactly two different points), `tolerance` (0 to 1, default 0.25 of a unit) | Clicks the board or types x and y to place points, drags them (they snap to quarter units); in line mode two points draw the line through them | `points`: every expected point has its own learner point within `tolerance`, and the counts match. `line`: both learner points are different and lie within `tolerance` of the expected line |
| `labeling` | `image_url` (http or https), `targets` (an id, the correct label and an x and y position as percentages, up to 50), optional `distractors` (up to 50 extra labels) | Drops a label on each target of the image | every target carries its correct label |
| `hotspot` | `image_url`, `zones` (x, y and radius as percentages, up to 50, at least one) | Clicks the image once | the click is inside any zone |

The graph is drawn with JSXGraph 1.14.1, only to draw the board: it never decides correctness, and the posted answer is a plain JSON list of `{ x, y }` points (`points`). The library is a bundled file, `assets/dist/jsxgraph/jsxgraphcore.js`, copied from `node_modules` by `tools/sync-admin-assets.mjs` and registered as the script `ohmylms-jsxgraph` (`Interactive::enqueue()`). The Function Graph block (`assets/src/blocks/interactive-activity`) reads the same copy, so the plugin carries one JSXGraph.

Deliberately or not yet built: **transformations** (reflect, translate or rotate a shape on a board) are not built; **measuring with a protractor or ruler** is not built; **AI-graded explanations** are not built on purpose, since AI here only hints and explains and never grades, scores or changes mastery (`includes/AI/Settings.php`). Written, drawn, audio and video responses (`draw`, `audio-response`, `video-response`, `discussion-board`) are teacher-marked.
### Randomized template questions

Any question can be turned into a template that draws new numbers every time it is issued ("Randomize numbers" panel in the question editor). The version stays one immutable row holding the template; each issue stores an integer **instance seed** and `QuestionSnapshot::instantiate( $seed )` produces the concrete question. Same seed, same numbers, so grading, review, reports and resume always see what the learner saw. Engine: `includes/Assessment/Template.php`.

```
settings.template = {
  variables:   [ { name: 'a', type: 'int',     min: 2, max: 9, step: 1, exclude: [5] },
                 { name: 'x', type: 'decimal', min: 1, max: 5, places: 1 },
                 { name: 'w', type: 'choice',  values: ['apples', 'pears'] },
                 { name: 'c', type: 'expr',    expr: 'a*b', places: 0 } ],
  constraints: [ 'a>b', 'gcd(a,b)=1' ],
  set:         [ { path: 'answer', expr: 'a*b' } ]
}
```

- **Placeholders:** `{{a}}`, `{{a*b}}`, `{{x:2}}` (two decimals), `{{b:+}}` (always signed, so `3x{{b:+}}` reads `3x-4`). They work in the title, body, option text, hint, worked solution and any text setting. A text that is exactly one placeholder becomes a number.
- **Computed settings (`set`):** number fields (an expected value, a target, the area of a grid) are filled by a formula at a dotted path such as `answer`, `target`, `blanks.a.answer`, `parts.0.answer`, `values.cat`. The value typed in the field is replaced.
- **Variables** are single letters a–z except `e`, drawn deterministically from the seed (a 48-bit hash stream), redrawn until every condition holds. Formulas and conditions use the expression engine, which gained `gcd`, `lcm`, `mod`, `min`, `max`, `round(x, n)`, `floor`, `ceil` and multi-argument calls.
- **Where the seed lives:** `attempt_items.display.instance_seed` (fixed from the attempt seed), `practice_items.display.instance_seed` (fresh per item and different from numbers the session already used), and, for inline checks and author previews, derived from the signed render token. Every load of a frozen version for an item goes through `AttemptItems::snapshot( $item )`.
- **Authoring check:** saving validates the template by building eight real examples and running the question type's own validation on each. It rejects unknown letters, unreadable formulas, unresolved `{{…}}`, conditions nobody can satisfy and templates whose numbers never change. `POST /question-template/preview` returns examples and the reason a draft fails; the panel's "Show examples" uses it.
- **Learners** never receive the template, the variables or the formulas; only the concrete question. Teachers see the numbers each learner was given in the report (`instance`).
- **Practice and mastery:** a template can be issued again and again in a session (it is never "exhausted"). Each issue counts as its own first try for evidence, since the numbers are new. Mastery still asks for several *families* (default two for Proficient), so a skill needs at least two different templates or questions, or the rule `ohmylms_mastery_rules.proficient_families` lowered to 1.
- **Not built:** mistake replay (a missed item returns later with new numbers), templates that change the number of options or parts, and parameterised images.

Tests: `php tests/template-unit.php`; the real-database phase `php tests/php/assessment-integration.php templates` covers authoring, per-attempt numbers, grading, reports, versions, previews, practice and evidence.

## Build and verify

Run `npm run lint`, `npm test`, and `npm run build`. The build adapts both editor manifests and produces separate lazy editor chunks. Frontend player files are served as native script modules and do not need webpack output.
