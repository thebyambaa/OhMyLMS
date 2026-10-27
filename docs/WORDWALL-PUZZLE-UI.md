# Puzzle authoring and player interface

Reviewed and implemented 2026-10-21. This extends the existing Gutenberg question block and shared assessment engine; it does not create a separate authoring application.

## Wordwall research

Inspected the signed-in [template picker](https://wordwall.net/create/picktemplate?folderId=0), both puzzle content-entry screens, and public [Crossword](https://wordwall.net/resource/104477440/animals-and-sea-animals-crossword) and [Wordsearch](https://wordwall.net/resource/104581076/english/shapes) players. Cross-checked the [official Crossword guide](https://content.wordwall.net/hc/en-us/articles/360015997738-How-to-create-a-Crossword-activity).

Observed patterns:

- The creation flow moves from template selection to content entry to play. Content entry is a compact numbered list with Add an item and a clear completion action.
- Crossword uses answer/clue columns. Its player lets learners select a word, displays its clue, highlights the word and accepts letters from a keyboard. The public example also has fullscreen, timer and sound controls.
- Wordsearch offers Without clues and With clues. Its player places the grid beside its word/clue list and draws a line over selected letters.
- Template switching and visual styles are separate from the content definition.

No activity was published, no subscription was purchased, and no resource was copied into the user's Wordwall library. The account's saved-resource count remained unchanged. Wordwall retained its normal unfinished-editor continuation prompt.

## OhMyLMS implementation

Crossword and Wordsearch appear in the existing question type selector and chooser. Their author controls use numbered answer/clue rows, Add an item, item movement/removal and Edit content / Preview puzzle actions. The existing question statement supplies the title/prompt; Gutenberg Save remains the save action. Preview generates a layout when needed, while layout and grading settings stay in a collapsed disclosure.

The live preview and learner player share `assets/interactivity/puzzles.mjs`. Crossword has numbered starts, Across/Down clue buttons, active-word highlighting, paste/type support, arrow keys, Backspace navigation, native mobile input and fullscreen. Wordsearch supports words or hidden-answer clues, pointer drag, two endpoint clicks, keyboard endpoint selection, selected-path highlighting and removable selections. Standard quiz/player navigation and feedback surround both controls.

Assessment feedback is given by the existing submit/review flow. A highlighted wordsearch selection is a proposed response, not a browser-issued claim of correctness. Academic grading remains server-authoritative. This release does not add Wordwall's timer/lives system, illustrated themes, sound effects, media clues or a separate onscreen alphabet keyboard.

## Layout and grading contract v1

- Both formats accept 1–12 distinct words, each 2–12 uppercase graphemes long, in a square grid of 4–12 cells per side. Spaces and punctuation are excluded. Wordwall's observed entry limits are larger (Crossword 2–30 and Wordsearch 3–25); the initial OhMyLMS limits are explicit bounds for this generator and response contract.
- The editor normalizes generated words to NFC and uppercase, supports Mongolian Cyrillic including Ө and Ү, and keeps repeated letters in separate cells. Imported definitions should use the same Unicode character form; the PHP grader does not claim general Unicode canonical-equivalence folding.
- A bounded seeded generator either returns a layout or asks for fewer words, more shared letters or a larger grid. Crosswords must be connected, with compatible perpendicular intersections and empty space separating neighboring words. Authors can adjust crossword positions and review the private answer layout.
- Wordsearch has optional diagonal/backward placement and an editable filler alphabet, including a Mongolian example. Reversed duplicate target words are rejected. An accidental additional occurrence is allowed: any straight path spelling a target in the frozen grid is accepted, including the reverse endpoint order. A target can earn credit once, regardless of how many occurrences are selected.
- Publishing freezes entries, positions and the wordsearch grid in the existing immutable version. Delivery and resume never generate another layout.
- Crossword public data includes occupied cells and clues, without letters. Wordsearch public data includes the letter grid and visible words or clues, without private word coordinates. A wordsearch necessarily exposes the letters that contain its answers.
- Responses are scalar values keyed by cell ID (Crossword) or selection slot (Wordsearch). The server validates bounds, unknown cells, duplicates, malformed values and response counts. Default grading awards equal partial credit per complete word; authors may require the whole puzzle. Incorrect/redundant wordsearch selections subtract from found-word credit, floored at zero, preventing selection spam.

## Persistence and asset loading

Native named fields exist before lazy loading, so versioned quiz autosave can restore server-received answers even when the puzzle module is still loading. Wordsearch highlights refresh on the existing answer-restored event. In the standalone Question Player, the puzzle additionally retains its draft in sessionStorage scoped to the issued session/question and page. This restores work in the same browser tab, not across devices; storage restrictions can disable that convenience without preventing play. New player sessions have different keys.

The small loader runs with the shared interactive controls. It imports the puzzle runtime only when a puzzle appears, including fetched questions; loader and runtime share a file-based cache version. Editor preview code is part of the question editor bundle, not the math runtime.

## Validation

`tests/js/word-puzzles.test.mjs` checks deterministic generation, invalid/impossible inputs, manual conflicts, directions, Unicode and learner projections. `tests/php/puzzles-integration.php` validates private/public contracts, grading policies and real frozen question-player submissions inside a rolled-back transaction against the dedicated migration database. `tests/browser/word-puzzles.spec.cjs` covers Gutenberg edit/save/reopen, both live previews, keyboard construction, wordsearch selection, same-tab refresh and final grading on the disposable site.
