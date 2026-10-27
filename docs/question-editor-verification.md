# Question editor comparison and verification

Verified 9 October 2026. The shared quiz and question-bank editor provides a searchable type chooser, rich prompt, type-specific answer controls, collapsed settings and learner preview. All 38 native formats can be inserted. Fourteen formats were added while retaining immutable question versions, private grading keys and native automatic/manual assessment behavior.

## Saved browser examples

- [OhMyLMS comparison quiz 79](http://800.local/wp-admin/admin.php?page=ohmylms#/quiz-edit/79): 39 saved questions covering 38 types.
- [Wayground comparison quiz](https://wayground.com/activity/admin/quiz/6ac86b7fb55f13275caa8e4c/edit): 26 questions, including passage and interactive-video groups with child questions.
- [Wayground slide lesson](https://wayground.com/activity/presentation/6ac87a493e2eb76a4aaa06bc/edit): title slide and fourteen copied questions. Slide requires a lesson rather than a quiz in Wayground.

Both quizzes remain drafts. The additional OhMyLMS question was originally inserted as a poll through the browser, saved and reloaded. On resumption it was already version 2, Build a chart, with an empty prompt; that existing edit was preserved. The first 38 examples still cover every type. Do not regenerate the comparison quiz over these edits.

## Format mapping

| Wayground format | OhMyLMS format / behavior |
| --- | --- |
| Multiple choice | Single choice |
| Multiple select | Multiple choice |
| True / false | True / false |
| Fill in the blank | Fill in the blank; Multi-blank / table |
| Open ended | Short answer / Long answer |
| Match | Matching |
| Reorder | Reorder |
| Categorize | Sort into groups |
| Dropdown | Dropdown in a sentence |
| Drag and drop | Build from tiles |
| Math response | Math expression / Numerical |
| Multi-part | Structured (multi-part) |
| Passage | Passage with native structured parts |
| Match table grid | Match table grid |
| Hot text | Hot text selection |
| Hotspot | Image coordinate selection with graded regions |
| Labeling | Positioned image labels with selection controls |
| Graphing | Points and straight lines |
| Draw | Canvas response, teacher review |
| Audio / Video response | Recording, bounded upload or media link, teacher review |
| Poll | Unscored choices |
| Word cloud | Unscored individual word visualization |
| Discussion board | Individual written response, teacher review |
| Slide | Unscored content item |
| Interactive video | Video with timed text checkpoints |

OhMyLMS additionally retains Statement, Number line, Shade a model, Count with blocks, Set the clock, Make an amount, Fill to a level, Build a chart and Build on a grid.

## Browser checks

All original 24 and new 14 formats mounted in the editor and learner preview. The saved OhMyLMS quiz player navigated every type. New automatic controls were answered, a triangle drawn, a generated WAV uploaded and played, and media-link, poll, word, discussion and checkpoint responses submitted. Preview showed 8/35 and manual grading pending; most legacy questions were intentionally unanswered, so this was a submission smoke check rather than an all-correct scoring assertion. Preview creates no learner attempt or grade event.

After rebuilding and reloading, graph coordinates (2,3) rendered with numbered axes, the word cloud condensed repeated words, and an empty audio response hid its player. OhMyLMS retains the saved 39-question quiz. Read-only integration against its actual frozen versions confirmed 38 distinct types, full credit for seven extended automatic formats, pending review for four manual formats, zero marks for three unscored formats, and absence of private grading keys in learner settings.

Wayground whole-quiz preview navigated through 26/26, showing match, media response, drawing, table, passage, image, graph, hot-text, multi-part and video checkpoint layouts. The last checkpoint and Show answers controls worked. The companion lesson preview showed its title slide. Individual saved premium cards display Unlock, but whole-quiz preview is available; no upgrade was purchased.

## Automated checks

- 90 relevant JavaScript tests pass (editor, bank, insertion, media, preview and reports).
- 84 extended PHP checks pass; 186 existing assessment unit checks pass with mbstring.
- Source checks pass: 1,559 JS/JSX files parse and 236 contracts across 19 manifests are valid.
- Production webpack build and shipped admin asset synchronization pass.
- New editor components, extended model, report response component and learner widget pass scoped ESLint. Both new CSS files pass Stylelint.
- The extended PHP template and test harness pass WordPress standards. `ExtendedQuestions.php` reports two filename rules only.
- The final scoped check of twelve existing integration JS files reports **55 errors and 2 warnings**. Remaining examples include classic JSX createElement import diagnostics, old nested ternaries, translation comments, accessibility labels, preserved snake_case contract fields and hook dependencies. Repository-wide legacy/recovered standards failures remain; no blanket baseline or rule suppression was introduced.

Compatibility exception: `includes/Assessment/ExtendedQuestions.php` retains the established PascalCase PSR-4 class-to-file mapping. Renaming it to a WordPress `class-*` filename without changing autoloading would break class resolution. The two filename findings remain visible; they are not suppressed.

## Remaining differences and verification limits

This is a native OhMyLMS adaptation, not complete Wayground feature parity. Graphing does not implement quadratic/exponential curves or inequalities. Word clouds do not aggregate a live class. Discussion does not provide a shared peer wall, likes or comments. Labeling uses selection controls instead of Wayground's draggable labels. Video checkpoints accept text rather than arbitrary nested question formats; passage parts retain native numerical/expression/written kinds. Slide is a content item rather than a presentation canvas with shape, theme and animation tools.

Microphone/camera capture was not exercised against user permission prompts; generated upload and media-link paths were tested. Report rendering and passage marking were covered by automated contract checks, not by modifying real learner attempts. Client validation provides basic authoring checks; server validation enforces the stronger bounded schema. These are explicit limits of the completed verification.
