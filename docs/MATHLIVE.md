# Math input and equations

Enable the opt-in rollout with `define( 'OHMYLMS_MATHLIVE_ENABLED', true );` in `wp-config.php`, or the `ohmylms_mathlive_enabled` filter. The local `800.local` site is enabled for acceptance testing. Disabling presentation retains native expression inputs and existing grading contracts.

MathLive 0.111.1 and Compute Engine 0.151.0 are pinned in npm. Run `npm ci` and `npm run build` with the repository's Node/npm versions. The build packages fonts and licenses locally. No separate KaTeX dependency or computation service is added. MathLive's distributed fonts retain their upstream names.

Expression answers, alternatives and expression blanks use a shared MathLive field while the existing named input continues to carry the answer. Numerical values and tolerances retain their original controls. Learner menus and the mobile keyboard expose basic supported notation. Typing or pasting unsupported notation still reaches bounded PHP validation and is rejected. Native inputs remain usable if the runtime fails.

Use **Insert equation** for prompts, choices, hints and explanations. Choose inline or display layout. Prompts show editable equation fields directly; **Edit original source** in the equation dialog provides a text fallback. Storage uses the existing content string with explicit markers:

The prompt formatting toolbar now inserts a MathLive equation at the text caret, with fraction, power, square-root and sine templates. Ctrl/Cmd+M inserts an empty equation; it remains editable without storing an invalid empty marker. Each equation has accessible inline/display and remove controls. Tab, Escape and MathLive's `move-out` event return the caret to surrounding prose. The existing **Preview question** action renders the current content. These controls use MathLive for both editing and display.

```
[[ohmylms-math:latex:display]]\frac{1}{2}x={{a}}[[/ohmylms-math]]
```

Dollar signs remain ordinary text. WordPress HTML sanitization is unchanged. The browser renderer uses text nodes and rejects presentation commands that inject HTML, URLs or macros. Editing preserves source tokens; save/reopen and snapshot publishing store markers rather than custom-element HTML.

Enable **Randomize numbers**, define variables and use `{{a}}` or computed tokens such as `{{a*b}}` inside equations. MathLive displays these as locked placeholders and restores the original token on edits. Variable buttons insert tokens into answer and equation controls. Range, step, exclusions, calculated variables, conditions and sample-count controls use the existing server randomization model. **Show examples** checks concrete server-generated instances. Compute Engine loads only when a teacher requests **Check syntax with Compute Engine**; this is advisory and does not replace server validation, randomization or grading. It never rewrites the formula or runs learner solving.

The CAS accepts bounded fractions, roots, powers, single-letter variables, supported functions and equations. It retains equivalence and required expanded/factored/simplified checks. A scalar solution (`3`) and an equation (`x=3`) remain different answer categories: author an alternative if both should be accepted. The acceptance quiz includes a randomized scalar alternative. Learner payloads retain public answer-form instructions but omit expected answers, alternatives, tolerances and randomization settings.

The new `includes/Assessment/MathLive.php` follows the existing PSR-4 namespace/file mapping. Its PascalCase filename intentionally retains that mapping; WordPressCS filename findings are reported rather than suppressed or addressed by a breaking rename.

## Verification

- Production source build and asset synchronization passed. Webpack warns about the 806 KiB MathLive entry and the optional approximately 3.9 MiB Compute Engine chunk.
- CAS: 94 checks passed. Randomization/templates: 1,666 checks passed.
- JavaScript: 339 of 340 tests passed. The unchanged `assets/js/elementor-widgets.js` fails the pre-existing recovery AST hash expectation.
- Eight MathLive browser tests passed: real PHP template/native POST/server grading; fractions, roots, powers and equations; equivalent and required forms; explicit markers and local fonts; restoration event, randomized tokens and dynamic mounts/cleanup; plain fallback and unsupported input; mobile keyboard and physical keys; direct author editing/serialization/reopen; template/lazy-loader coexistence without duplicate script execution; cursor insertion/layout/removal; and empty-field creation/navigation to prose. The toolbar and Ctrl+M were also verified in an isolated draft on `800.local`.
- Local quiz 150 (**MathLive acceptance test**) was created through the admin UI. Its two randomized questions were edited, saved, duplicated and reopened. Published snapshots preserved original markers. Fixed seeds reproduced the same instances; learner payload checks and authoritative grading passed. Actual preview submissions returned **2 / 2** for equation solutions and for number-only alternatives.
- Changed PHP syntax, source-contract checks, new math-module ESLint and scoped math CSS checks passed.
- Repository-wide checks remain blocked by existing formatting violations (10 unrelated files), PHP standards findings across 730 files, JavaScript standards findings and CSS standards findings. No baseline or rule suppression was added.
- The existing general PHP unit harness fails because its anonymous question fixture lacks `get_settings()`. The general interactivity browser fixture fails before quiz rendering because it does not provide `OhMyLMS\Assessment\Schema`; its run was stopped after confirming this blocker. The guarded disposable-site assessment integration suite was unavailable; it was not pointed at the user's working database.

Persistent enrolled-learner attempts and theme-specific override combinations have not been exercised end to end on `800.local`. Browser restoration-event and deterministic snapshot checks cover the integration boundary, but do not substitute for that acceptance pass. Compute Engine syntax recognition can exceed the server's supported notation. These differences are explained in the teacher controls.

## Manual acceptance

1. Open quiz 150, edit an equation directly, insert an inline fraction, save and reopen. Confirm tokens remain variables and ordinary dollar amounts remain text.
2. Draw sample variants; check range/exclusions/conditions and calculated variables. Verify syntax checking leaves original formulas untouched.
3. Preview and submit a correct solution, an equivalent solution, an incorrect solution and an unsupported expression. Check each required answer form and expression blank.
4. On an enrolled learner account, answer multiple questions, navigate pages, autosave, leave/resume and submit. Check the saved attempt review and ensure no expected answers appear before feedback is permitted.
5. On a touch device, open/dismiss the math keyboard; use Tab and physical keys. Block the runtime request and confirm the plain input can still submit. Repeat with active theme overrides and custom question extensions.

No deployment or merge was performed.

## Randomized previews across question types

Switching a bank question's response type retains its variable definitions and conditions. Computed-answer rules are cleared with the old type's answer fields; configure the new type's expected answers or targets before saving.

The in-editor learner preview resolves the entire draft through the author-only template REST endpoint before rendering any question type. Prompts, explicit MathLive equation markers, matching labels, tiles, nested settings, hints and explanations share one server-generated instance. Each mounted preview keeps its seed; changing the draft replaces the displayed instance without accepting a stale response. Generation failures show an error instead of unresolved variables. Authoring and overview quick editing intentionally keep `{{a}}` source intact. Questions without randomization remain unchanged.

Nested option content also resolves variables, preserving string labels, numeric IDs and boolean correctness flags. Existing learner deliveries, server grading, restoration and review continue to instantiate frozen snapshots using their stored seeds; the new preview response is restricted by the existing author permission and sanitized with WordPress KSES.

Verification: read-only local REST checks cover every built-in type from the source catalog, stable seeds, nested content, MathLive markers, sanitization, unknown variables, impossible conditions and unauthorized access. Targeted preview/model tests cover stale responses, generation errors and unchanged old questions. Full enrolled-attempt regression testing still requires the disposable assessment database.

Acceptance: enable Randomize numbers; define `a` with a range; insert `{{a}}` in the prompt and a type-specific label or tile (including inside a Ctrl+M equation). Open Preview question and confirm numbers replace the tokens. Return to editing and confirm the tokens remain. Repeat for matching, Build from tiles, expression blanks and a passage. Submit a learner attempt and resume it to confirm its original values persist.

MathLive literal double-brace delimiters (for example, `\left\lbrace\left\lbrace a\right\rbrace\right\rbrace`) are recognized as `{{a}}`. Newly typed tokens serialize canonically and reopen as locked variable prompts. Server substitution also recognizes the older delimiter form inside explicit equation markers, so previously saved equations resolve without resaving or modifying their frozen source. Unmarked literal LaTeX and ordinary single braces are unchanged. Unknown variables still produce template errors; surrounding fractions, roots and powers are preserved.

Verification: real pinned-MathLive keyboard entry and reopen, four JavaScript math utility tests, 1,670 PHP template checks, and read-only local REST/snapshot checks for all 38 built-in types cover canonical and previously saved literal-brace tokens. Manual acceptance: define `a`, type `{{a}}` inside a Ctrl+M equation, save/reopen, and preview a generated number. Check an older saved equation also resolves without changing its source.

## Visual content editing across question types

### Dropdown choices and points

Dropdown answer setup uses one editable row per choice with a correctness tick before it. Renaming a ticked choice preserves the tick. Tick multiple choices to enable learner multiple selection, then choose **Equal partial credit for each correct selection** or **Any correct selection earns full points**. Any incorrect selection earns zero for that dropdown; duplicate selections never earn extra credit. Each dropdown has its own points; the question total is derived and cannot be edited after upgrading the answer setup. Removing a dropdown marker updates the total.

Edited dropdowns use `dropdown_grading_version: 2`, private slot `answers`, `points` and `grading` fields, and the existing `answer` field as a legacy primary answer. The learner view exposes only slot IDs, shuffled choices and the multiple-selection flag. Existing frozen versions without the new version marker retain their original single-answer and whole-question/partial-credit behavior. The server checks the total against the dropdown points and grades against the frozen snapshot. Native template fields retain their names, with `[]` appended for a multiple selection; practice collection, autosave, restoration, submission and review support the resulting bounded selection arrays. Custom template overrides can keep submitting a scalar, but need to honor the learner `multiple` flag and array field names to allow several selections.

Acceptance: create two dropdowns worth 3 and 2 points, tick two correct choices in the first and one in the second, and save/reopen. In equal mode, selecting one correct choice in the first plus the correct second choice earns 3.5/5; selecting both first choices earns 5/5. In any mode, either first correct choice plus the second earns 5/5. Add a wrong choice and confirm the first dropdown earns zero. Resume an attempt and confirm every selection remains selected. Check the question total updates when a dropdown is removed.

All built-in question statements use the main Question editor. Dropdown sentences, inline multi-blank sentences and reading passages no longer have a second statement editor in answer setup. Their instructions appear below the main editor, followed by the answer controls. The main editor adapts to the existing `settings.text` or `settings.passage` contracts; it preserves legacy introductory content, synchronizes blank IDs, and carries statement content across question-type and blank-layout changes. Choice labels, table cells and structured subquestion prompts remain separate answer/content fields. Public editor components retain their standalone defaults for extensions.

Acceptance: open a dropdown question and confirm its sentence appears in the main Question editor; edit `{1}` and `{2}` there and confirm the corresponding answer controls follow. Save/reopen and preview it. Repeat for inline multi-blanks and a reading passage, and check switching a multi-blank between inline and table layouts preserves the statement. Ordinary question types retain their existing main editor and instructions.

Formatting toolbars are disabled by default in all shared rich content controls, including overview quick editing, answer labels, hints and explanations. Ctrl+M (Cmd+M on macOS) inserts an editable MathLive equation at the prose caret. `QuestionForm.jsx` keeps an explicit `promptToolbarTypes` allowlist, currently empty: future toolbar requests should opt in only the requested type. Rich content and existing inline MathLive equations remain editable, with no change to saved content or grading contracts.

The shared prompt editor is used by every built-in question type. Choice, matching and reorder labels, hints, explanations, structured-part prompts, reading passages, interactive sentence/blank text, category labels and video checkpoints use the same inline MathLive authoring controls. Plain-text fields continue to store plain text and explicit equation markers; pasted HTML is converted to text. Existing HTML formatting is preserved; formatting and image toolbar actions require an explicit opt-in. Numbers, tolerances, exact-match keys, variable definitions, URLs and diagram coordinates retain their specialised controls. Custom extension editors are unchanged.

Acceptance: open single/multiple choice, matching, structured, dropdown blanks and a reading passage; insert a fraction in each relevant content field; edit it directly; Tab/Escape back to prose; save/reopen and preview. Confirm correct-option selections and matching pairs remain intact, and literal `<`, `&` and ordinary dollar amounts survive in plain labels.

Verification for the shared visual controls: 10 targeted JavaScript tests and 9 MathLive browser tests pass; source contracts and the new shared control/runtime ESLint checks pass. The full check still stops at 10 existing formatting failures. Existing editor modules retain standards violations (camel-case callbacks, translator comments and nested expressions); none are suppressed. Live local checks covered choice labels, structured part prompts and reading passages with incomplete disposable drafts.
### Fill-in-the-blank interaction

Answer chips have explicit dark text on a light background in the authoring stage. In the learner preview and drag-mode quiz, double-click an available choice to place it in the first empty blank from left to right. Enter provides the same keyboard action. Dragging and selecting a choice followed by a blank remain available. Placed choices retain a dashed placeholder at their original bank position; clearing a filled blank restores that choice. Submission still uses the original answer fields, and repeated answer words remain separate tokens.

Verification: three browser tests cover placement, duplicate tokens, drag/click/keyboard input, restored answers, submission fields, question isolation and theme contrast. Four JavaScript blank-model tests and the PHP inline-blank parsing/privacy/grading checks pass. The source build and changed JavaScript lint checks pass. The existing full-check formatting failures and deprecated `clip` property in the unchanged accessibility-input rule remain.
### Optional partial grading for blanks

Fill-in-the-blank authoring has an **Enable partial grading** checkbox in its footer. Enabled: each correct blank earns an equal share of the question points. Disabled: the question earns points only when every blank is correct. The flag is saved as `settings.partial_credit` and read by the server from the frozen question version. Older inline-brace versions without the flag retain partial credit; older separate-answer versions without the flag retain all-or-nothing grading. Neither mode accepts extra answer positions for credit. Required-answer validation remains unchanged.

Only Multi Select retains the layout switch in editing and preview, including its compatible `single-choice` and `multiple-choice` contracts. Every other question type hides it. Verification includes checkbox settings round trips, legacy defaults, readonly controls, and server grading against distinct frozen versions with partial grading enabled and disabled. The remaining PHP standards findings in `includes/Extensions/QuestionTypes.php` predate these changes, including its established PSR-4 file name.
### Quick prompt editing in quiz cards

Click a question prompt in the overview to edit it in place. Ctrl+M inserts a MathLive equation without a formatting toolbar. Changes use the same canonical prompt patch and quiz autosave validation as the full editor. Double-click the prompt, or use the **Edit** button, to open the full question editor. Pinned versions remain readonly. Quick edits preserve rich content, explicit equation markers and existing grading settings.
