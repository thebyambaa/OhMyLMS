# Quiz design and player templates

Open **Settings → Design → Quiz & Question Editor** to change the 20 palette colors. The editor and Classic player have separate stage, prompt, toolbar and answer colors; text, accent and correctness colors are shared. Switch the local preview between editor and player, use individual Reset controls, or restore the Wayground-style palette. **Save quiz design** saves only these colors through the authenticated Design endpoint. Invalid hex values disable saving, and server sanitizers reject invalid values. Site branding is independent.

The question editor opens in a fullscreen workspace with a white formatting toolbar, purple stage, dark prompt and colored answer cards. Correctness controls, answer images, type-specific configuration and versioned autosave remain native OhMyLMS controls. Save question returns to the quiz after autosave completes. Horizontal/vertical layout is a local presentation preference.

Choose **Playful**, **Classic**, **Paper** or **Focus** in the quiz introduction's settings and save the quiz. **Playful is the default** for quizzes that have not chosen a design: a bright white stage, a green progress pill, white answer rows with a thick bottom edge that turn blue when picked, chunky green buttons (a green, blue, red, orange and yellow palette) and the same colors on the results review. Its colors are the `--playful-*` variables on `.ohmylms-player-template-playful` in `assets/css/quiz-player-templates.css`; they do not follow the stage palette below, because they are a fixed design. Quizzes that chose Classic, Paper or Focus keep them, and an unknown or missing design falls back to Playful (`PlayerTemplates::DEFAULT_TEMPLATE`). Classic uses the colorful stage, Paper uses white exam-paper surfaces, and Focus uses a calm dark surface. Paper and Focus share the accent color. Saved quiz preview uses the selected design. Published attempts freeze the template identifier in their assessment revision; global palette changes remain site presentation settings. Existing quizzes default to Classic.

## Extend the designs

Register a design in PHP, with an optional stylesheet handle registered through WordPress:

```php
add_filter(
	'ohmylms_quiz_player_templates',
	static function ( $templates ) {
		$templates['school'] = array(
			'label'       => __( 'School', 'your-theme' ),
			'description' => __( 'School presentation style.', 'your-theme' ),
			'stylesheet'  => 'school-quiz-player',
		);
		return $templates;
	}
);
```

Scope CSS to `.ohmylms-quiz[data-player-template="school"]`. Shared controls, question renderers, grading and navigation remain active. Palette properties include `--ohmylms-quiz-accent`, `--ohmylms-quiz-player-stage`, `--ohmylms-quiz-player-prompt` and `--ohmylms-quiz-player-answer-1` through `-4`.

Themes can override `ohmylms/quiz-player/school/header.php`, `progress.php` and `question-meta.php`. Theme parts receive data in `$args`; escape each value for its output context. Preserve the shared close-control class and interactivity progress bindings when replacing those parts. Only these three fixed part names are accepted, and registered template identifiers cannot contain paths. Arbitrary stored PHP, HTML or stylesheet URLs are not accepted.

Compatibility exception: `includes/Quiz/PlayerTemplates.php` follows the existing PSR-4 class/file mapping. Renaming it to the WordPress `class-*.php` convention would prevent autoloading without a migration. The two filename findings remain visible in PHPCS; they are not suppressed.

## Verification on 2026-10-09

- Production admin SDK rebuilt and synced; 1,561 source files parse and 236 contracts across 19 manifests validate.
- 97 JavaScript tests, 186 assessment unit checks, 84 extended-question checks and 79 WordPress player-template integration checks pass.
- Modified PHP files pass syntax checks. New player parts and integration test pass PHPCS. Scoped editor/picker/design JavaScript and all four authored editor/player stylesheets pass their standards checks.
- The broader touched PHP check reports 174 errors and 35 warnings, including the documented filename exception and existing issues in legacy code. No blanket baseline or rule suppression was added.
- The broader three-file legacy JavaScript check reports 105 errors in the existing reconstructed settings/quiz modules; the focused new workspace, picker and palette components pass.
- Browser checks confirmed template persistence for Paper and Focus, native Previous/Next answer retention, fullscreen question editing, and the dedicated design save response `Colors saved.` The default accent was restored after the persistence test. Concurrent user edits to the comparison quiz were preserved.

The styling follows the inspected Wayground editor and Classic preview. Existing native type-specific capabilities and the parity limits described in `question-editor-verification.md` still apply.
