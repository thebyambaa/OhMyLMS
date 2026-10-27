# Wordwall template audit and OhMyLMS implementation plan

Prepared 2026-10-21. All 34 catalogue entries now have a core question format, extended block or practice mode. See [implemented activities and explicit limits](ALL-ACTIVITIES.md) and [earlier delivery behavior](WORDWALL-ACTIVITIES.md). Advanced Wordwall themes, settings, physics and services are outside this core delivery. The implementation extends existing blocks wherever compatible.

## Evidence and scope

Reviewed all 34 template cards in the signed-in Wordwall picker supplied by the user: 12 Standard and 22 Pro templates. Cross-checked the official catalogue and help pages. This is a catalogue and code audit, not a claim that every template was created or play-tested. No Wordwall resources were created or changed. Printables and classroom tools absent from this picker (for example Seating plan) are outside this inventory.

References:

- [User's template picker](https://wordwall.net/create/picktemplate?folderId=0)
- [Official plans and template inventory](https://www.wordwall.net/price-plans)
- [Official template help catalogue](https://wordwall.zendesk.com/hc/en-gb/sections/360005042778-Templates)
- [Image quiz mechanics](https://wordwall.zendesk.com/hc/en-gb/articles/360015997958--How-to-create-an-Image-Quiz-activity)
- [Wordwall features and template switching](https://www.wordwall.net/features)

Local code reviewed: `questionBlocks.mjs`, `QuestionTypeChooser.jsx`, `interactiveModel.mjs`, `extendedModel.mjs`, `Extensions/QuestionTypes.php`, `Assessment/QuestionSnapshot.php`, `Practice/QuestionPlayer.php`, `Blocks/QuestionPlayerBlock.php`, `Quiz/PlayerTemplates.php`, the current player scripts, and the existing question-type and player documentation. Existing documentation's historical format counts are not used as proof of support; the current registry and controls are the implementation references.

## Recommendation

Extend the existing native question authoring and player engine. Add five focused response/content families and a separate activity-mode registry. Do not create 34 unrelated graders or replace the current player with another isolated player.

Keep three concepts separate:

1. **Question type:** what the author defines, what response the learner submits, and how it is graded.
2. **Activity mode:** how compatible question content is presented, sequenced, revealed or turned into a game.
3. **Visual theme:** appearance only. Existing Playful, Classic, Paper and Focus designs remain themes; their current IDs and meaning must not change.

Default new game activities to practice. Accuracy, game points, time, hints and participation are separate measurements. A player mode must not silently change exam grading or mastery evidence.

## Complete template comparison

“Existing” means a native interaction exists, not identical Wordwall gameplay. Every proposed ID below is a design choice, not a shipped identifier.

| # | Wordwall template | OhMyLMS coverage and required work | Delivery |
| --- | --- | --- | --- |
| 1 | Anagram | Implemented Unicode letter construction in Build from tiles | Letters mode of `build-expression` |
| 2 | Spin the wheel | Implemented core practice activity; see [current catalogue](ALL-ACTIVITIES.md) for source requirements and limits | `spin-wheel` Question Player mode |
| 3 | Open the box | Implemented core practice activity; see [current catalogue](ALL-ACTIVITIES.md) for source requirements and limits | `open-box` Question Player mode |
| 4 | Unjumble | Implemented sentence ordering in Build from tiles; reorder remains available | Words mode of `build-expression` |
| 5 | Matching pairs | Implemented concealed matching board with saved reveals and matches | `memory-pairs` Question Player mode |
| 6 | Quiz | Choice interactions and quiz player exist | Reuse; verify compatible choice configuration |
| 7 | Group sort | `categorize` exists | Reuse; add drag/tap/keyboard presentation where absent |
| 8 | Match up | `matching` exists | Reuse matching, including keyboard alternative |
| 9 | Flash cards | Implemented saved front/back study cards | `flashcards` Question Player mode |
| 10 | Speaking cards | Implemented core practice activity; see [current catalogue](ALL-ACTIVITIES.md) for source requirements and limits | `speaking-cards` Question Player mode |
| 11 | Complete the sentence | `fill-in-the-blank` supports drag blanks; dropdown and multi-blank also exist | Reuse blank engine; verify distractors and repeated words |
| 12 | Find the match | Implemented core practice activity; see [current catalogue](ALL-ACTIVITIES.md) for source requirements and limits | `find-match` Question Player mode |
| 13 | Gameshow quiz | Implemented core practice activity; see [current catalogue](ALL-ACTIVITIES.md) for source requirements and limits | `gameshow` Question Player mode |
| 14 | Type the answer | Implemented automatic accepted-answer text entry | `text-entry` question type |
| 15 | Spell the word | Dedicated spelling player now reuses existing word answers | Implemented `spell-word` Question Player mode over text-entry and letter tiles; typed input |
| 16 | Flip tiles | Implemented core practice activity; see [current catalogue](ALL-ACTIVITIES.md) for source requirements and limits | `flip-tiles` Question Player mode |
| 17 | Wordsearch | Generated frozen letter grid, words/clues and endpoint selection implemented | `wordsearch` question type |
| 18 | Labelled diagram | Existing `labeling` extended with draggable label bank, tap/keyboard placement, menus and restore | Existing image/pin builder and grader retained; distractor authoring added. Timers/leaderboards remain proposed |
| 19 | Hangman | Server-checked letter guesses, six mistakes and saved progress | Implemented `hangman` Question Player mode over text-entry and letter tiles |
| 20 | Crossword | Connected generator, word/clue builder and keyboard grid implemented | `crossword` question type |
| 21 | True or false | Implemented core practice activity; see [current catalogue](ALL-ACTIVITIES.md) for source requirements and limits | `timed-true-false` Question Player mode |
| 22 | Maze chase | Implemented core practice activity; see [current catalogue](ALL-ACTIVITIES.md) for source requirements and limits | `maze-chase` Question Player mode |
| 23 | Flying fruit | Implemented core practice activity; see [current catalogue](ALL-ACTIVITIES.md) for source requirements and limits | `flying-fruit` Question Player mode |
| 24 | Balloon pop | Implemented core practice activity; see [current catalogue](ALL-ACTIVITIES.md) for source requirements and limits | `balloon-pop` Question Player mode |
| 25 | Pair or No Pair | Implemented core practice activity; see [current catalogue](ALL-ACTIVITIES.md) for source requirements and limits | `pair-or-no-pair` Question Player mode |
| 26 | Rank order | `reorder` exists | Reuse; preserve current grading unless an author explicitly selects a newer policy |
| 27 | Whack-a-mole | Implemented core practice activity; see [current catalogue](ALL-ACTIVITIES.md) for source requirements and limits | `whack-a-mole` Question Player mode |
| 28 | Airplane | Implemented core practice activity; see [current catalogue](ALL-ACTIVITIES.md) for source requirements and limits | `airplane` Question Player mode |
| 29 | Image quiz | Implemented timed tile reveal, saved buzzer, answer review and practice points | `image-quiz` Question Player mode over image single-choice questions; lives/leaderboards remain proposed |
| 30 | Speed sorting | Implemented core practice activity; see [current catalogue](ALL-ACTIVITIES.md) for source requirements and limits | `speed-sorting` Question Player mode |
| 31 | Watch and memorize | Implemented timed observation, hidden recall and saved practice feedback over matching fronts | `memory-recall` Question Player mode; dedicated question family remains proposed |
| 32 | Win or lose quiz | Implemented core practice activity; see [current catalogue](ALL-ACTIVITIES.md) for source requirements and limits | `win-or-lose` Question Player mode |
| 33 | Maths generator | Implemented seven topic presets with bounded ranges over the existing numerical variable engine | Numerical editor → Maths generator |
| 34 | Word magnets | Implemented core practice activity; see [current catalogue](ALL-ACTIVITIES.md) for source requirements and limits | `word-magnets` Question Player mode |

## Five new families: editor, player, response and grading

### 1. Text entry (`text-entry`) — first priority

- Gutenberg editor: prompt, accepted answers, case/whitespace/punctuation policies, optional hints and maximum response length. Preview the exact normalization rule. Use accepted answers rather than an unrestricted regular-expression evaluator.
- Learner: text input, optional audio prompt, accessible label and feedback; normal autosave/resume.
- Private definition: accepted strings and normalized keys. Public projection: input configuration only.
- Response: one bounded string; server performs the same versioned normalization used by editor examples.
- Grade: exact accepted-answer matching. No automatic semantic or AI equivalence claim. Keep `short-text` and `long-text` manual behavior intact. Numerical and mathematical equivalence remain their existing types.

### 2. Word builder (`word-builder`) — first priority

- Shared content supports Anagram, Spell the word and Hangman modes.
- Editor: target word/phrase, clue or image/audio, accepted alternatives, language/alphabet, space/punctuation handling, hints, distractor letters and default practice mode.
- Learner: draggable letters plus tap-to-place and keyboard actions; spelling can accept typing. Repeated letters have distinct tile IDs. Show clear cursor/slot state.
- Grade: normalized constructed text against accepted answers; don't grade repeated-letter IDs as if identical letters have different meanings.
- Segment by grapheme clusters, normalize consistently on PHP and JS, support Mongolian Cyrillic including Ө/Ү, preserve author-selected case/punctuation semantics. Specify a fallback when platform segmentation differs.
- Hangman needs server-checked guess/reveal state, persisted guessed letters and mistake count. Do not ship a hidden target in the DOM and pretend it is secure assessment.

### 3. Crossword (`crossword`) — second priority

- Editor: clue/answer pairs with stable IDs, language, optional media clues, generate layout, review conflicts, manual adjustments and regenerate preview.
- Validate answer alphabet, intersecting letters, word bounds, connectivity policy and impossible layouts. Bounded seeded generation must either produce a valid layout or return an actionable error.
- Freeze the published layout; reopening/resuming an attempt must not regenerate a different puzzle.
- Learner: accessible numbered grid, across/down clue lists, active-word highlighting, touch keyboard and keyboard navigation.
- Response: bounded cell text keyed by cell ID. Grade per word, with intersections validated once; report missing/incorrect words and preserve the configured partial-credit policy. Keep answer placement private until permitted review.

### 4. Wordsearch (`wordsearch`) — second priority

- Editor: word/clue list, grid size, allowed directions, backward/diagonal policy, alphabet and generated-layout preview.
- Generate a deterministic grid under caps; detect unsupported long words, duplicates, overlaps and accidental extra occurrences according to a documented policy. Authors review before publishing.
- Learner: select start/end cells by pointer or keyboard; selected paths remain visible and can be corrected.
- Response: stable start/end coordinates or cell paths, validated against the frozen grid on the server. Grade distinct found targets; duplicate/reversed paths cannot earn repeated credit.
- The puzzle grid necessarily exposes letters from which answers can be inferred. Describe it as word-search practice, not as an answer-secret exam format.

### 5. Memory recall (`memory-recall`) — second priority

- Editor: study items, distractors, display order and study duration; first version assesses the set of items remembered. Ordered sequence recall is a separate explicit response policy if added later.
- Player phases: ready → study → hidden recall → feedback. Freeze the chosen study set and distractors per attempt.
- Resume uses server-recorded phase/deadline; refreshing cannot restart study time or award extra study exposure unnoticed. Keyboard/reduced-motion alternatives remain available.
- Response: selected opaque item IDs. Define exact-set or bounded net-correct scoring so selecting everything does not earn full credit.
- Measure memory practice separately from mastery of the item's underlying mathematics; default to no skill evidence.

## Activity player architecture

Add an activity-mode registry beside the existing question-type registry. Each mode declares supported interaction families, minimum content, media requirements, input alternatives, whether it reveals answers, scoring policy, resume state schema and bundle entry. Its compatibility function explains unavailable modes (for example no image, not enough pairs, manual-only questions or unsupported media).

Extend the existing Question Player placement and server run lifecycle with optional `activityMode` and bounded mode settings; legacy placements default to standard. Teacher-led tools use a presentation/study path rather than weakening the current QuestionPlayer eligibility guard, which excludes manually marked questions. Keep Quiz Player's attempt, enrollment and assessment policies authoritative.

Do not repurpose `player_template` to mean game mode: it currently changes appearance only. Let the author choose **Response format**, then **Play mode**, then **Visual theme**. Compatible mode switching preserves source question IDs and immutable versions. Any content conversion that changes answer structure creates a reviewed new draft, not an in-place rewrite of published questions.

Shared mode runtime owns stable item IDs, seeded shuffles, pause/reduced-motion behavior, score events, feedback, retries, focus, touch/keyboard controls and resume. Reuse it across:

- Study/reveal: Flash cards, Speaking cards, Flip tiles, Open the box, Spin the wheel.
- Pair games: Matching pairs, Find the match, Pair or No Pair, Balloon pop.
- Timed quiz/sort: Gameshow, timed True or false, Speed sorting, Win or lose.
- Visual reveal: Image quiz.
- Arcade movement: Maze chase, Flying fruit, Whack-a-mole, Airplane.
- Construction: Anagram, spelling, Hangman, Unjumble and Word magnets.

For flashcards and memory pairs, return content/reveal results through the appropriate study session contract. Do not preload a private matching key merely for game convenience. Visible study answers and assisted rounds must be recorded as such. Reuse existing server scoring; the browser's reported collision count, timer or game score is not authoritative academic evidence.

Split mode assets into lazy-loaded bundles and enqueue only the selected mode. Avoid loading all word/puzzle/arcade code into every quiz, especially given the existing bundle-size warnings. Timers and animation stop when the player unmounts; multiple player blocks on one page remain isolated.

## Mathematics generator

Build a topic/preset wizard over existing variables, constraints and seeded concrete question instances. Start with arithmetic, fractions/percentages and simple algebra using existing numerical/expression types. Provide ranges, exclusions, difficulty and answer-form controls. Show generated examples and reject impossible constraints. Preserve instance seeds, private formulas, historical versions and evidence-family rules. This is generation tooling, not another grader. No parameterized-image promise: that remains a separate capability.

## Delivery order and acceptance gates

1. **Foundation and quick reuse:** mode registry/compatibility manifest, versioned response contracts, restore state, accessible mode picker, drag-labeling enhancement, study deck and image reveal. Preserve old placements and all existing player themes.
2. **Core new types:** text entry and word builder, followed by sentence construction and pair games. Deliver complete authoring → save/reopen → publish → learner → resume → grade → report flows.
3. **Puzzles and memory:** crossword, wordsearch and memory recall. Freeze generated layouts and test phase transitions, Unicode, impossible inputs and large-but-bounded definitions.
4. **Teacher and practice modes:** wheel, boxes, magnets, Gameshow, timed sorting/binary choices, wager mode and maths-generator wizard.
5. **Arcade:** maze, moving targets, balloon physics, mole targets and flight; reuse one interaction/event runtime where practical. Ship mobile, keyboard, pause and reduced-motion equivalents alongside each game.

Within each phase, release individual modes only after their gates pass; do not show nonfunctional tiles in the production chooser.

Every new type requires:

- Server registry, settings validator, learner-safe projection, immutable publishing/media collection, versioned response normalization and grader.
- Gutenberg editor and live preview sharing schemas with bank authoring; consistent type labels. The existing chooser currently labels both choice IDs “Multiple select”; clarify this before adding more choices while preserving IDs.
- Learner renderer across quiz, practice and question-player surfaces, plus inline placements where supported. Unsupported contexts must be explicitly gated.
- Autosave/resume, required-answer checks, duplicate-submit handling, answer review, reports, partial credit and relevant skill-evidence policy.
- Tests for permission/access, private-key leakage, unknown/duplicate IDs, failed saves, shuffled content, concurrent/stale submissions, mobile and keyboard operation, and historical attempts after upgrades.
- No silent reinterpretation of old short-text, reorder, matching, quiz settings or stored answers. Snapshot and grader schema versions need an explicit compatibility strategy.

Completion means an end-to-end playable, resumable and reportable activity. A new dropdown option, theme or editor card is not sufficient.

## First implementation slice

Start with the mode foundation, `text-entry`, `word-builder`, Flash cards and Matching pairs. This adds useful recall/vocabulary mechanics with broad content reuse before undertaking the larger puzzle generators and arcade work. Follow with drag-labeling and Image quiz. Keep the full 34-template matrix above as the coverage checklist for later phases.

Puzzle delivery details and observed Wordwall interface patterns: [puzzle UI](WORDWALL-PUZZLE-UI.md).
