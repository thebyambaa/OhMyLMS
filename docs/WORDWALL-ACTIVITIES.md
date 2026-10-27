# Wordwall-inspired activities: first implementation

Implemented 2026-10-21. This is the first delivery of the [34-template plan](WORDWALL-TEMPLATE-AUDIT-AND-PLAN.md), not complete Wordwall parity.

## Existing blocks extended

**Build from tiles** now offers Math tiles, Words (sentence ordering) and Letters (anagram). It keeps the existing `build-expression` ID, response sequence, immutable versions and grader. New language modes use exact ordering; mathematical equivalence remains a Math-only option. Repeated letters stay separate selectable tiles, and Unicode grapheme segmentation avoids breaking combining marks. Changing mode explicitly converts the current answer and clears distractors/alternative orders; Gutenberg Undo restores the previous definition. The chooser also finds this block when searching for anagram, spelling, letters or words. Single choice and Multiple select now have distinct labels; IDs are preserved.

**Question Player** now offers Standard, Flashcards, Matching pairs, Hangman and Spell the word. Flashcards and Matching Pairs are also inserter variations of the existing `ohmylms/question-player` block. They store references and an `activityMode` attribute, not another copy of the questions or answer keys.

Use approved Matching questions with text on both sides of each pair. Select at most 24 pairs per activity. Flashcards reveal the reverse of each front card; Matching pairs hides both sides and remembers successful matches and turns. These are study activities: the server disables academic grading and skill effects regardless of submitted client flags. No grade events or mastery evidence are written. A standard placement without a mode retains its original configuration key and behavior.

Study mode reuses authenticated placement access, frozen question versions and practice-session storage. Opaque card IDs are scoped to each session; hidden backs and the private pair map are not preloaded into the browser. Reveal state is saved under a row lock with a revision. A stale/repeated request returns the current state instead of applying the action twice. Resume restores open cards, revealed backs, matched pairs and turns. Card reveals and play-again are keyboard buttons, and there is no required animation or timer. Study-player JavaScript loads only when a study run is played.

The first study release uses text pairs. Media-rich cards, self-rating, spaced repetition, free token boards and arcade modes remain later work.

## Hangman and Spell the word

These are additional inserter variations and Play mode choices on the existing Question Player block. Reuse approved **Type the answer (automatic)** questions or **Build from tiles → Letters** questions. Put a clue in the question statement; plain text is used, falling back to the title. Avoid including the answer in that clue. Media/audio clue presentation is not included in this delivery.

Each accepted answer must contain a letter and at most 64 grapheme clusters. Hangman uses the first accepted answer, ignores letter case, reveals repeated occurrences together and displays spaces, punctuation and numbers automatically. Type a single letter using the learner's keyboard; a six-step drawing and textual mistake counter track progress. Duplicate guesses do not count twice. Canonically equivalent Unicode spellings are not folded; use the same written character form as the target.

Spelling accepts a typed whole word or phrase. Automatic text questions keep their existing case, whitespace and punctuation rules and accepted alternatives. Letter-tile questions use their primary constructed answer with case-insensitive comparison; tile alternatives are not imported into spelling in this release. After six mistakes, the round ends and reveals the answer. Correct guesses also finish a round. Next word advances through the frozen selection; Play again creates a new session.

The server retains the target and checks each guess. Public Hangman state contains only revealed characters; spelling state never contains accepted answers before the round ends. Mistakes, guessed letters, the current round and completion persist under a revision-checked session lock. These modes always disable academic grading and skill effects. Their JavaScript loads separately only when a word game is played. Keyboard forms, visible labels, status announcements and a static drawing provide play without a timer or required animation.

## Crossword and Wordsearch

Both are now question-type options in the existing Gutenberg block. They provide compact word/clue entry, generated frozen layouts, the same interactive preview and learner controls, optional partial credit, private-answer projections and standard player grading. See [puzzle interface research, behavior and limits](WORDWALL-PUZZLE-UI.md).

## Watch and memorize

Added as `activityMode: memory-recall` on the existing Question Player, with an **OhMyLMS Watch and memorize** inserter variation. It reuses approved Matching question fronts as a text-item pool, preserving the original matching question and grader. Matching backs are not used or exposed by this mode. This is a practice presentation, not a new graded question type.

The author selects 3–24 distinct items (up to 200 visible characters each), 1–10 items to remember, and 5–120 seconds of study. The pool must contain more items than the number to remember. Formatting and repeated whitespace collapse for display and duplicate detection. A random subset is frozen by the issued session; separate private HMAC-seeded orders prevent public session IDs or candidate positions from disclosing that subset.

The learner flow is ready → timed study → hidden recall → review. Begin study persists the server deadline once. Refresh restores the remaining time and never restarts observation; an expired study projects recall immediately. Ready to recall allows an early transition. The browser removes study cards at expiry, while the server decides which content is available. Recall choices disclose no target flags until completion. Selecting/unselecting choices persists under a revision-checked row lock; selections are capped at the target count. Stale requests return the current state, and a completed session cannot reopen.

Feedback reports remembered items and false selections; practice accuracy is `max(0, remembered − false selections) / target count`. It creates no academic grade events or skill evidence. Review identifies shown items after submission, and Play again issues a new session. This is memory practice: previously shown content cannot be made secret from a learner who recorded the observation screen.

The interaction follows the observation/recall pattern in [Wordwall's official guide](https://wordwall.zendesk.com/hc/en-gb/articles/47554178269585--How-to-create-a-Watch-and-Memorize-activity). This first version uses stationary, keyboard/touch-selectable text cards and untimed recall. Moving targets, lives, media cards, ordered recall and a dedicated response-format family remain future extensions. Timers/listeners are cleaned up on rerender; the mode runtime loads only when played.

Validation: `tests/php/recall-integration.php` exercises phase access, expiry, restart resistance, stale/forged actions, saved choices, selection caps, false-positive penalties and no grade events. `tests/browser/recall-activity.spec.cjs` covers timed playback, refresh, early recall, review, restart, mobile layout and Gutenberg mode settings on the disposable site. `tests/js/recall-player.test.mjs` checks the zero-remaining-time mounting race and timer cleanup.

## New response format

**Type the answer (automatic)** uses the new `text-entry` ID. Enter accepted answers, one per line, and choose case sensitivity, repeated-whitespace handling and punctuation handling. Leading/trailing whitespace is ignored. It performs literal normalized comparison, not semantic or AI grading; Short answer and Long answer retain teacher marking. Case handling supports Mongolian Cyrillic. Canonically equivalent Unicode spellings are not automatically folded by this grader; enter an alternative when necessary.

Accepted answers are private settings; learner input is limited to 2000 characters. The bounded server validator rejects empty/invalid answer lists and malformed comparison settings; the grader awards no credit to multiple submitted guesses. It participates in native authoring, live preview, immutable bank approval, quiz/practice/question-player rendering, server grading, and expected-answer reporting. Existing quiz autosave handles its conventional named input. Study progress is persisted by the study service; the standard standalone Question Player's existing draft behavior is unchanged.

## Compatibility and validation

Existing type IDs, mathematical tile grading, manual short-answer semantics, course outcomes and player themes keep their contracts. The new PascalCase PHP filenames follow the plugin's established PSR-4 class mapping; their narrow filename-sniff exception is documented in the source and this document.

Checks include language-token tests (Mongolian repeated letters and combining marks), bounded automatic-answer checks, frozen publish/player grading, private-answer projection, reveal/resume, stale and forged actions, completion without grade events, and browser playback. Use the dedicated disposable migration database for `tests/php/wordwall-integration.php`; that suite refuses other databases and rolls its fixtures back. The browser spec `tests/browser/wordwall-activities.spec.cjs` is restricted to the disposable server at port 8100 and checks the native authoring controls plus learner playback. `tests/browser/word-games.spec.cjs` additionally checks both word modes, repeated Cyrillic letters, multi-round advancement, mistake limits, refresh recovery, completion and restart.

The remaining core teacher tools, matching modes, timed games, arcade adaptations, Word magnets and Maths generator are now implemented. See [the complete activity catalogue](ALL-ACTIVITIES.md) for source requirements, controls, scoring and explicit limits.

## Labelled diagram: existing Labeling block extended

Use the existing **Labeling** question type (also searchable by “labelled diagram”, “labeled diagram” and “pins”). Choose a diagram image, enter the correct label for each target, then click or drag the numbered pins in the editor. The full-screen pin editor also supports arrow-key positioning. Optional **Distractor labels** add unused choices to the label bank.

The learner can drag a label onto a numbered pin, click a label then a pin, or use Enter/Space on these same buttons. Delete/Backspace on a pin clears its label; Escape cancels a selection. **Use label menus** provides native dropdown controls. Filled pins show their selected labels, and the status reports completion without exposing correctness. Labels remain reusable, preserving historical repeated-label definitions. Clearing, replacing and menu changes all write the original `target ID → label text` response.

The enlarged diagram includes its label bank, menus and clear action. Escape closes it and returns focus to the enlarge button; Tab stays inside the enlarged view. The drag enhancement loads only when a Labeling widget appears, with versioned URLs. If it cannot load, the original diagram menus still submit answers.

Native hidden answer fields exist before JavaScript loads, allowing quiz autosave to restore responses even while the enhancement loads. The controls synchronize with `ohmylms:answer-restored`. Standalone Question Player placements also keep a tab-local draft scoped to the issued session and question; starting a new run gives a fresh draft. Existing frozen image references, public target coordinates, shuffled/sorted label bank, all-or-nothing grading and reports remain in use. The target-to-correct-label map stays private until authorized review. This extends the response type rather than introducing another activity mode or grader.

Based on [Wordwall’s Labelled Diagram guide](https://wordwall.zendesk.com/hc/en-gb/articles/360015809077--How-to-create-a-Labelled-Diagram-activity). Wordwall timers, immediate correctness checks and leaderboards are outside this delivery; feedback follows the existing assessment/player policy.

Validation: `tests/php/diagram-labels-integration.php` covers safe public projection, bounded distractors, pre-rendered restore fields, frozen grading after draft edits, incomplete/incorrect placements and existing reports. `tests/browser/diagram-labels.spec.cjs` covers native Gutenberg save/reopen, pin adjustment, distractors, drag, keyboard and tap input, menu fallback, refresh, enlarged view, mobile and grading.

## Image quiz

Choose **Image quiz** in Question Player → Play mode, or insert **OhMyLMS Image quiz**. It reuses approved single-choice bank questions: add a main image, 2–6 answer choices (text and/or images), and exactly one correct answer in the existing question editor. Variable questions are excluded. The picker filters compatible questions; launches recheck compatibility and access. Random skill pools skip incompatible questions.

The image loads before Reveal image becomes available. A 6×6 cover opens progressively over 10–120 seconds (default 30). Buzz pauses the reveal and opens the choices. Each round accepts one answer, shows feedback, then proceeds to the next image. Skip image handles unavailable pictures for zero points. Desktop and mobile use keyboard-operable buttons.

The server saves the start time, paused tile count, round, revision and total practice points. Refresh preserves progress; repeated actions cannot award points twice. Correct answers earn `max(1, 36 − tiles revealed at buzz)` points; incorrect/skipped rounds earn zero. Active responses omit correctness and option database IDs. Keys appear only in that round’s review. Media uses the existing version snapshots. Concealment is a visual practice mechanic: the image URL remains visible to the browser.

This follows [Wordwall’s Image Quiz guide](https://wordwall.zendesk.com/hc/en-gb/articles/360015997958--How-to-create-an-Image-Quiz-activity) for image entry, timed reveal, buzzer and choice flow. Lives, leaderboards, automatic next-round transitions, alternate layouts and theme presets remain future work. Practice points never create grade events or skill evidence.

Validation: `tests/php/image-activity-integration.php` covers phase gates, timing, private answers, invalid/repeated actions, scoring and absence of academic events in a rolled-back disposable database transaction. `tests/browser/image-quiz.spec.cjs` covers saved buzzer state, multiple rounds, full reveal, feedback, desktop/mobile layouts and native block controls.
