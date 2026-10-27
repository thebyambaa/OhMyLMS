# Structured question parts

In a Gutenberg OhMyLMS Question, choose **Structured (multi-part)**. The main question statement and image form the shared stem. Each part has its own visible **Part statement**, answer type, marks, and private marking notes. Statement formatting supports images and equations.

The part answer selector keeps the existing Number, Short exact text, and Written (teacher marks) formats and adds the existing choice, true/false, blanks, matching, reorder, expression, visual learning, graphing, hot text, table grid, labeling, hotspot, and drawing editors. Drawing and written responses require teacher review. Recursive structured questions and passages, media recording, and ungraded activities are not offered as parts.

New leaf questions are stored inline as `kind: question` with `question.settings` and `question.questions`. Their configuration, option keys and media are captured in the parent's immutable version. Existing parts retain their original schemas and stable IDs. Parent marks are the sum of part marks; automatic fractions are weighted by those marks, and manual parts remain pending for teacher review.

Learner delivery uses the existing leaf public-view contract and renderer. Nested input prefixes and DOM IDs isolate each part. Choice tokens are scoped to the delivery, parent question/version, and stable part ID; matching definitions have separate tokens. Keys, tolerances and rubrics stay private. Resume restores the same tokens, while later author edits leave issued versions unchanged. Automatic practice and Question Player selection exclude parents containing teacher-marked parts.

`tests/php/structured-parts-integration.php` checks mixed grading, token isolation, learner redaction, input prefixes, frozen media, legacy compatibility, and real writer/version capture. It requires `OHMYLMS_TEST_SITE_PATH` and rolls back its writer fixtures. `tests/js/structured-parts.test.mjs` exercises the actual editor callbacks and leaf autosave validation.
