# Assessment profiles

A quiz, a practice set and an exam are **one stored thing**: one `ohmylms-quiz` post with the same question
links, revisions, attempts, grading and skill evidence. They differ only in a **profile**, chosen by the
assessment kind saved with the quiz (`AssessmentSettings::get( $quiz_id )['kind']`). Shared code never tests
for a particular kind; it asks `Assessment\Profiles::of( $quiz_id )`.

| What a profile decides | Key | Used by |
| --- | --- | --- |
| Player design (template) the kind always uses | `player_template` | `templates/single-lesson/quiz-form.php` |
| Layout (`one_question_per_page`, `all_questions_in_one_page`, `number_of_questions_per_page`) | `layout` | quiz form |
| Deliveries the kind's questions may use (`quiz`, `inline`, `practice`; the editor offers only matching blocks) | `deliveries` | `NativeQuizEditor::quiz_blocks`, `Profiles::allows` |
| List the author returns to from the editor | `return_route` | `NativeLessonEditor::return_url` |
| Editor sidebar panel and the data it receives | `panel`, `editor_data` | `Profiles::editor`, `ProfilePanel.jsx` |
| Starting context of the player script | `context` | `Interactivity::quiz` filter |
| Player designs the kind brings (hidden from the quiz design picker) | `templates` | `PlayerTemplates` |

Built in: `quiz` and `practice` (no overrides) and `exam` (`includes/Exams/Profile.php`: the exam dashboard
design, one question per page, formal attempts only, the Exams tab, the Exam panel). The exam also has a record
(`ohmylms-exam`), a private wrapper course for enrollment and a public URL, which is delivery, not authoring.

## Combined assessment editor

Quizzes and exams open in the same Gutenberg editor. **Assessment type** chooses the profile without
resetting duration, attempts, grading or feedback settings. A quiz linked to an exam record retains its
Exam type so that its publication and question-security rules remain intact.

The **Quiz settings** or **Exam settings** panel combines timing, grading, scheduling, results release,
sections and learner extra time. Native saves accept the additive `ohmylms_quiz.assessment` field and write
it to the existing assessment metadata after question placements have been synchronized, inside the same
transaction as the document. Invalid rules roll back document and settings changes together. Existing
clients that omit the field retain the current assessment rules. Question pools and exam publication keep
their explicit controls and existing permission checks.

**Student preview** saves pending document changes, then embeds the existing authenticated preview player
in an editor dialog. It uses the saved profile, player design, questions, math and grading. Restart opens
a new temporary session; preview submissions create no learner attempts or skill evidence. The preview
result continues to show the author the answer review, including answers withheld from learners.

## Add a kind

Register it before the first assessment is read (plugin load or `plugins_loaded`):

```php
add_filter( 'ohmylms_assessment_profiles', function ( $profiles ) {
	$profiles['placement'] = array(
		'label'           => 'Placement test',
		'player_template' => 'placement',                 // a design registered below
		'layout'          => 'all_questions_in_one_page',
		'deliveries'      => array( 'quiz' ),                // no practice sets or inline checks
		'return_route'    => '/content-hub/quizzes',
		'panel'           => 'placement',
		'editor_data'     => fn( $quiz_id ) => array( 'quiz' => $quiz_id ),
		'context'         => function ( $context, $quiz_id ) {
			$context['placement'] = true;
			return $context;
		},
		'templates'       => array(
			'placement' => array(
				'label'       => 'Placement',
				'description' => 'Placement test design.',
				'stylesheet'  => 'my-placement-style',      // a registered style handle
			),
		),
	);
	return $profiles;
} );
```

- **Deliveries** are the ways a question reaches a learner. See [Deliveries](#deliveries).
- **Kind ids** are lowercase letters, digits and hyphens. A quiz saved with an unregistered kind is refused.
- **Set the kind** with `AssessmentSettings::save( $quiz_id, array( 'kind' => 'placement' ) )`.
- **Design parts**: a design is `templates/quiz-player/<id>/{header,progress,question-meta,footer}.php`, found
  in the plugin or in the theme at `ohmylms/quiz-player/<id>/`. A missing part falls back to the shared one.
  The player script is the shared `ohmylms/quiz` store; add actions to it from your own module if needed.
- **Editor panel**: from your own editor script, `window.ohmylmsProfilePanels[ 'placement' ] = Component`. The
  component receives the profile's `editor_data` as `data` and renders a `PluginDocumentSettingPanel`.
- **Extra rules** (publish checks, delivery pages, REST routes) belong to the kind's own code, as for exams
  (`includes/Exams/`); the registry only carries what shared code needs to ask.

`tests/php/assessment-profiles-integration.php` checks the built-in kinds and registers a new kind through the
filter alone.

## Deliveries

One bank question can reach a learner three ways, and `Assessment\DeliveryPolicy` is the single place that decides
which questions each accepts:

| Delivery | Served as | Accepts |
| --- | --- | --- |
| `quiz` | A slot in a formal quiz or exam attempt | Any question that exists and is not trashed or archived, including exam-only and teacher-marked ones |
| `inline` | A one-question check in lesson content | The approved version (an editor sees an unapproved one as a preview); not exam-only, not teacher-marked |
| `practice` | A run of the question player | The approved version; not exam-only, not teacher-marked |

`DeliveryPolicy::snapshot()`, `DeliveryPolicy::allows()` and `Profiles::allows()` are used by the question player
(`QuestionPlayer::eligible`), the inline check, the exam builder and the editor's block list. They also drive the editor's single Question block (below).
`tests/php/delivery-policy-integration.php` covers the rules.

### One Question block, three sources

The editor has one inserter entry, **OhMyLMS Question**. A switch on the block chooses where the question comes
from; the Question Player block is the same block in another state and no longer has its own inserter entry:

| Source | Quiz or exam editor | Lesson or page editor |
| --- | --- | --- |
| **Write here** | Author a question as a quiz slot | Author a question that plays as an inline check |
| **Pick from bank** | Opens the bank picker; the picked questions replace the blank card as quiz slots | Becomes a practice set of chosen bank questions |
| **From skills** | A random practice set drawn from skills (only if the kind allows the `practice` delivery) | A random practice set drawn from skills |

The switch is `QuestionSourceSwitch.jsx` and its rules are `questionSource.mjs`. Both blocks use it, so the two
blocks read as one. The editor receives the deliveries its kind allows in `window.ohmylmsNativeLesson.profile`; an
exam offers no skills source, and the standalone bank-question editor offers only **Write here**. A practice set of
exactly one chosen question can also be changed back to a Question block from the block toolbar.

### Two learner runtimes, kept on purpose

The `inline` and `practice` deliveries have their own learner scripts, and they are not merged:

| | Inline check (`Practice/Inline.php`, `inline-check.js`) | Question player (`QuestionPlayer.php`, `question-player.js`) |
| --- | --- | --- |
| Learners | Logged-in learners and guests (a guest's answers stay in the browser and can be saved to an account later) | Logged-in learners |
| Shape | One question with immediate feedback and retries; every answer is kept, only the first counts as first-try evidence | A frozen run of chosen or skill-drawn questions with Previous/Next and a final score; also the activity and game modes |
| Authority | A signed token for that question version and lesson, made on each page load | The saved block settings |

They share the practice session tables, `Sessions` grading, the approved snapshots and `DeliveryPolicy`. Merging the
scripts would mean teaching the player guests, retries and lesson scope, which puts learner evidence and the guest
trust model at risk for little saved code.

### Exams and practice

Publishing an exam makes its questions exam-only (secure), so a practice set or inline check that uses one stops
working. `Composer::warnings()` finds those uses (`DeliveryPolicy::other_uses()`, which scans page, post and lesson
content for question player blocks, inline question blocks and `[ohmylms_question]` shortcodes). The exam summary
lists them as `warnings`, and publishing answers with HTTP 409 `exam_overlap` until the request repeats with
`confirm_overlap`; the editor's Exam panel shows a **Publish anyway** button. Use a separate copy of a question for
the exam to keep the original playable in practice.


### Inline student question previews

Unselected native question blocks open an author-only frame through `POST /ohmylms/v1/question-preview`. The frame uses `single-lesson/quiz-form.php`, the registered question renderer, the learner-safe `QuestionSnapshot::student_view()` contract, and normal frontend asset hooks. It consumes the current unsaved draft. It creates no learner attempt and disables assessment timing and submission controls.

The edited quiz's `settings.presentation` selects Paper View or the standard student player. The current assessment profile and player design apply to standard previews. Paper previews run the same server-rendered paper (`PaperPresentation::sheet()`) with one public question item. Switching presentation refreshes the frames; the editor's Paper view toggle remains an authoring layout control.

Extensions do not register a second inline preview component. Register their normal question renderer and public settings/view, and enqueue their student assets as usual. Their markup and assets are used by the isolated preview document, including theme template overrides. Preview sessions contain only the public question view, are bound to the author and parent document, and expire after 15 minutes.
