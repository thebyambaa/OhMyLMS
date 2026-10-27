<?php
/**
 * Quiz import / export integration suite (isolated test site only).
 *
 * Builds a quiz with every question type (including randomized templates, skills, bank attributes,
 * media and extension settings), exports it, imports it as a new draft, and proves the copy is the
 * same quiz: identical stored content, identical grading, identical randomized numbers.
 *
 *   OHMYLMS_TEST_CREDENTIALS=/path/test-credentials.json php tests/php/quiz-exchange-integration.php
 */
if ( PHP_SAPI !== 'cli' ) {
	exit;
}
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
$config = json_decode( file_get_contents( getenv( 'OHMYLMS_TEST_CREDENTIALS' ) ), true );
require $config['site'] . '/wp-load.php';
if ( ! defined( 'OHMYLMS_TEST_SITE' ) || DB_NAME !== 'ohmylms_source_test' ) {
	throw new RuntimeException( 'Requires disposable test site' );
}
require_once ABSPATH . 'wp-admin/includes/user.php';

use OhMyLMS\Assessment\Grader;
use OhMyLMS\Extensions\Settings;
use OhMyLMS\QuestionBank\VersionPublisher;
use OhMyLMS\Quiz\Exchange\Document;
use OhMyLMS\Quiz\Exchange\Exporter;
use OhMyLMS\Quiz\Exchange\Importer;
use OhMyLMS\Quiz\QuizSkills;
use OhMyLMS\Skills\Taxonomy;

$checks  = 0;
$fixture = array(
	'users' => array(),
	'terms' => array(),
);
function ok( $condition, $message ) {
	global $checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	++$checks;
}
function call( $method, $path, $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/' . ltrim( $path, '/' ) );
	if ( $data ) {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
}
function as_user( $id ) {
	wp_set_current_user( $id );
}
function make_user( $role ) {
	global $fixture;
	$id = wp_create_user( 'ohmylms-exch-' . $role . '-' . wp_generate_password( 8, false, false ), wp_generate_password( 24 ), 'exch-' . wp_generate_password( 8, false, false ) . '@example.invalid' );
	if ( is_wp_error( $id ) ) {
		throw new RuntimeException( $id->get_error_message() );
	}
	( new WP_User( $id ) )->set_role( $role );
	$fixture['users'][] = $id;
	return $id;
}
/** Posts of the types this suite creates, for "nothing was created" checks. */
function content_count() {
	global $wpdb;
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('ohmylms-quiz','ohmylms-question')" );
}
function problems_text( $error ) {
	$text = $error->get_error_message();
	foreach ( (array) ( $error->get_error_data()['errors'] ?? array() ) as $problem ) {
		$text .= ' | ' . Document::describe( $problem );
	}
	return $text;
}
/** An import must be refused with the needle in its report, and must create nothing. */
function refuse( $content, $needle, $label ) {
	$before = content_count();
	$result = Importer::run( $content, array( 'name' => 'Should not exist' ) );
	ok( is_wp_error( $result ), $label . ': expected a refusal' );
	ok( false !== strpos( problems_text( $result ), $needle ), $label . ': report should mention "' . $needle . '" but was: ' . problems_text( $result ) );
	ok( 400 === $result->get_error_data()['status'], $label . ': refusal is a 400' );
	ok( content_count() === $before, $label . ': a refused import created content' );
}
function encode_file( array $document ) {
	return json_encode( $document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION );
}
function question_rows( $quiz_id ) {
	return ohmylms_get_quiz( $quiz_id )->get_questions();
}
/** Stored question content without ids that differ between copies. */
function stored( $question_id ) {
	$content = VersionPublisher::content( $question_id );
	foreach ( $content['options'] as $index => $option ) {
		unset( $content['options'][ $index ]['id'] );
	}
	return $content;
}
function snapshot_of( $question_id ) {
	$identity = VersionPublisher::identity( $question_id, false );
	ok( $identity && (int) $identity['current_version_id'] > 0, 'Question ' . $question_id . ' has no captured version' );
	return VersionPublisher::snapshot( (int) $identity['current_version_id'] );
}
function grade_with( $snapshot, $answer ) {
	$grade = Grader::grade( $snapshot, $answer );
	ok( ! is_wp_error( $grade ), 'Grading failed: ' . ( is_wp_error( $grade ) ? $grade->get_error_message() : '' ) );
	return $grade;
}
function correct_option_ids( $snapshot ) {
	$ids = array();
	foreach ( $snapshot->get_questions() as $option ) {
		if ( ! empty( $option['is_correct'] ) ) {
			$ids[] = (string) $option['id'];
		}
	}
	return $ids;
}
function wrong_option_ids( $snapshot ) {
	$ids = array();
	foreach ( $snapshot->get_questions() as $option ) {
		if ( empty( $option['is_correct'] ) ) {
			$ids[] = (string) $option['id'];
		}
	}
	return $ids;
}

// ---- Sample questions: one of everything -------------------------------------------------------
function q( $type, $description, $options = array(), $settings = array(), $score = 1, $extra = array() ) {
	static $n = 0;
	++$n;
	return array_merge(
		array(
			'name'        => 'Question ' . $n,
			'description' => $description,
			'status'      => 'publish',
			'settings'    => array_merge(
				array(
					'type'          => $type,
					'score'         => array(
						'enabled' => true,
						'value'   => $score,
					),
					'question_code' => 'Q-IT' . str_pad( $n, 4, '0', STR_PAD_LEFT ),
				),
				$settings
			),
			'questions'   => $options,
		),
		$extra
	);
}
function opt( $answer, $correct = false, $extra = array() ) {
	return array_merge(
		array(
			'answer'     => $answer,
			'is_correct' => $correct ? 1 : 0,
		),
		$extra
	);
}
function pair( $answer, $label, $image = '', $image_id = '' ) {
	return opt(
		$answer,
		false,
		array(
			'matching_data' => array(
				'label'     => $label,
				'image_id'  => $image_id,
				'image_url' => $image,
			),
		)
	);
}

$admin       = get_user_by( 'login', $config['username'] )->ID;
$baseline    = (int) $GLOBALS['wpdb']->get_var( "SELECT MAX(ID) FROM {$GLOBALS['wpdb']->posts}" );
$http_log    = array();
$http_filter = null;

try {
	as_user( $admin );

	// Fixtures: two skills, a media file, an extension that stores settings on questions and quizzes.
	$skill_a = wp_insert_term( 'IT Fractions', Taxonomy::NAME, array( 'slug' => 'it-fractions' ) );
	$skill_b = wp_insert_term( 'IT Ratios', Taxonomy::NAME, array( 'slug' => 'it-ratios' ) );
	ok( ! is_wp_error( $skill_a ) && ! is_wp_error( $skill_b ), 'Could not create skills' );
	$term_a             = (int) $skill_a['term_id'];
	$term_b             = (int) $skill_b['term_id'];
	$fixture['terms'][] = $term_a;
	$fixture['terms'][] = $term_b;
	$uuid_a             = Taxonomy::uuid( $term_a );

	$png      = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' );
	$uploads  = wp_get_upload_dir();
	wp_mkdir_p( $uploads['path'] );
	$png_path = $uploads['path'] . '/it-exchange-' . wp_generate_password( 6, false, false ) . '.png';
	file_put_contents( $png_path, $png );
	$attachment = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/png',
			'post_title'     => 'IT exchange image',
			'post_status'    => 'inherit',
		),
		$png_path
	);
	ok( $attachment > 0, 'Could not create the test attachment' );
	$image_url = wp_get_attachment_url( $attachment );

	Settings::register(
		'it-exchange',
		array(
			'contexts' => array( 'quiz', 'question' ),
			'schema'   => array(
				'type'       => 'object',
				'properties' => array( 'note' => array( 'type' => 'string' ) ),
			),
		)
	);

	$template_numeric = array(
		'variables'   => array(
			array(
				'name' => 'a',
				'type' => 'int',
				'min'  => 2,
				'max'  => 9,
				'step' => 1,
			),
			array(
				'name'   => 'x',
				'type'   => 'decimal',
				'min'    => 1,
				'max'    => 5,
				'places' => 2,
			),
		),
		'constraints' => array( 'a>2' ),
		'set'         => array(
			array(
				'path' => 'answer',
				'expr' => 'a*x',
			),
		),
	);
	$template_choice  = array(
		'variables' => array(
			array(
				'name' => 'a',
				'type' => 'int',
				'min'  => 1,
				'max'  => 9,
				'step' => 1,
			),
			array(
				'name' => 'b',
				'type' => 'int',
				'min'  => 1,
				'max'  => 9,
				'step' => 1,
			),
		),
	);

	$samples = array(
		'single'      => q(
			'single-choice',
			'<p>2 + 2 = ?</p>',
			array( opt( '3', false, array( 'thumbnail_id' => $attachment ) ), opt( '4', true ), opt( '5' ) ),
			array(
				'hint'        => 'Add them.',
				'explanation' => '<p>Two plus two is four.</p>',
				'required'    => true,
				'randomize'   => true,
			),
			2,
			array(
				'thumbnail_id' => $attachment,
				'skills'       => array(
					'p1' => array(
						'primary'    => $term_a,
						'supporting' => array( $term_b ),
					),
				),
			)
		),
		'multiple'    => q( 'multiple-choice', '<p>Pick the even numbers.</p>', array( opt( '2', true ), opt( '3' ), opt( '4', true ) ), array( 'partial_credit' => true ), 3, array(
			'bank' => array(
				'difficulty' => 'challenge',
				'source'     => 'IT suite',
				'secure'     => true,
			),
		) ),
		'truefalse'   => q( 'true-false', '<p>The sky is green.</p>', array( opt( 'True' ), opt( 'False', true ) ) ),
		'short'       => q( 'short-text', '<p>Name a prime number.</p>' ),
		'long'        => q( 'long-text', '<p>Explain photosynthesis.</p>', array(), array(), 5 ),
		'fill'        => q( 'fill-in-the-blank', '<p>{Paris} is the capital of {France}.</p>', array( opt( '', true ) ), array(
			'blank_mode'     => 'drag',
			'case_sensitive' => false,
		), 2, array( 'name' => '{Paris} is the capital of {France}.' ) ),
		'statement'   => q( 'statement', '<p>Where does photosynthesis happen?</p>', array( opt( 'chloroplast', true ) ) ),
		'matching'    => q( 'matching', '<p>Match each animal to its sound.</p>', array( pair( 'Cat', 'Meows', $image_url, $attachment ), pair( 'Dog', 'Barks' ), pair( 'Cow', 'Moos' ) ) ),
		'reorder'     => q( 'reorder', '<p>Put the numbers in order.</p>', array( pair( '1', '' ), pair( '2', '' ), pair( '3', '' ) ) ),
		'numerical'   => q( 'numerical', '<p>How long is the rod?</p>', array(), array(
			'answer'          => 12,
			'tolerance'       => 0.5,
			'tolerance_type'  => 'absolute',
			'unit'            => 'cm',
			'allow_fractions' => true,
		) ),
		'rand_number' => q( 'numerical', '<p>{{a}} pens cost {{x:2}} each. What is the total?</p>', array(), array(
			'answer'    => 0,
			'tolerance' => 0.01,
			'template'  => $template_numeric,
		), 2 ),
		'rand_choice' => q( 'single-choice', '<p>What is {{a}} + {{b}}?</p>', array( opt( '{{a+b}}', true ), opt( '{{a+b+1}}' ), opt( '{{a+b-1}}' ) ), array( 'template' => $template_choice ) ),
		'structured'  => q( 'structured', '<p>A rectangle is 4 cm by 3 cm.</p>', array(), array(
			'parts' => array(
				array(
					'id'     => 'a',
					'label'  => '(a)',
					'kind'   => 'numerical',
					'marks'  => 1,
					'prompt' => 'Find the area.',
					'answer' => 12,
				),
				array(
					'id'       => 'b',
					'label'    => '(b)',
					'kind'     => 'text',
					'marks'    => 1,
					'prompt'   => 'Name the shape.',
					'accepted' => array( 'rectangle' ),
				),
				array(
					'id'     => 'c',
					'label'  => '(c)',
					'kind'   => 'written',
					'marks'  => 2,
					'prompt' => 'Explain how you know.',
				),
			),
		), 4 ),
		'dropdown'    => q( 'dropdown-blanks', '', array(), array(
			'text'  => 'The slope is {1}, so the line {2}.',
			'slots' => array(
				array(
					'id'      => '1',
					'choices' => array( 'positive', 'negative' ),
					'answer'  => 'positive',
				),
				array(
					'id'      => '2',
					'choices' => array( 'rises', 'falls' ),
					'answer'  => 'rises',
				),
			),
		) ),
		'categorize'  => q( 'categorize', '<p>Sort the numbers.</p>', array(), array(
			'buckets' => array(
				array(
					'id'    => 'b1',
					'label' => 'Even',
				),
				array(
					'id'    => 'b2',
					'label' => 'Odd',
				),
			),
			'items'   => array(
				array(
					'id'   => 'i1',
					'text' => '2',
				),
				array(
					'id'   => 'i2',
					'text' => '3',
				),
			),
			'key'     => array(
				'i1' => 'b1',
				'i2' => 'b2',
			),
		) ),
		'multiblank'  => q( 'multi-blank', '', array(), array(
			'layout' => 'inline',
			'text'   => '3 + 4 = {a}',
			'blanks' => array(
				'a' => array(
					'kind'   => 'numerical',
					'answer' => 7,
				),
			),
		) ),
		'tiles'       => q( 'build-expression', '<p>Build 2x + 6.</p>', array(), array(
			'correct'      => array( '2', 'x', '+', '6' ),
			'distractors'  => array( '3' ),
			'alternatives' => array(),
			'equivalence'  => false,
			'form'         => 'any',
		) ),
		'expression'  => q( 'expression', '<p>Write 2x + 6 in factored form.</p>', array(), array(
			'answer'       => '2x+6',
			'alternatives' => array(),
			'form'         => 'any',
		) ),
		'numberline'  => q( 'number-line', '<p>Place 5.</p>', array(), array(
			'min'    => 0,
			'max'    => 10,
			'step'   => 1,
			'target' => 5,
		) ),
		'fill_level'  => q( 'fill-level', '<p>Fill to 500 ml.</p>', array(), array(
			'min'    => 0,
			'max'    => 1000,
			'step'   => 50,
			'unit'   => 'ml',
			'target' => 500,
		) ),
		'shade'       => q( 'shade-model', '<p>Shade three eighths.</p>', array(), array(
			'shape'  => 'bar',
			'parts'  => 8,
			'cols'   => 8,
			'answer' => 3,
		) ),
		'blocks'      => q( 'count-blocks', '<p>Show 243.</p>', array(), array(
			'places'        => array( 'hundreds', 'tens', 'ones' ),
			'target'        => 243,
			'max_per_place' => 20,
			'canonical'     => false,
		) ),
		'clock'       => q( 'set-clock', '<p>Set 3:45.</p>', array(), array(
			'hour'         => 3,
			'minute'       => 45,
			'tolerance'    => 1,
			'snap'         => 1,
			'show_digital' => false,
		) ),
		'money'       => q( 'make-amount', '<p>Make 3500.</p>', array(), array(
			'denominations' => array( 5000, 1000, 500, 100 ),
			'symbol'        => '₮',
			'target'        => 3500,
		) ),
		'chart'       => q( 'build-chart', '<p>Build the chart.</p>', array(), array(
			'categories' => array(
				array(
					'id'    => 'a',
					'label' => 'A',
				),
				array(
					'id'    => 'b',
					'label' => 'B',
				),
			),
			'max'        => 10,
			'step'       => 1,
			'unit'       => '',
			'values'     => array(
				'a' => 4,
				'b' => 7,
			),
			'show_table' => true,
		) ),
		'grid'        => q( 'grid-build', '<p>Build a rectangle of area 12.</p>', array(), array(
			'rows'          => 6,
			'cols'          => 6,
			'constraints'   => array(
				'area'      => 12,
				'perimeter' => 14,
				'rectangle' => true,
			),
			'show_measures' => false,
		) ),
		'passage'     => q( 'passage', '', array(), array(
			'passage' => 'A rectangle has a length of 4 cm and a width of 3 cm.',
			'parts'   => array(
				array(
					'id'     => 'a',
					'label'  => '(a)',
					'kind'   => 'numerical',
					'marks'  => 1,
					'prompt' => 'Find the area.',
					'answer' => 12,
				),
			),
		) ),
		'graphing'    => q( 'graphing', '<p>Plot (2, 3).</p>', array(), array(
			'mode'      => 'points',
			'min'       => -5,
			'max'       => 5,
			'points'    => array(
				array(
					'x' => 2,
					'y' => 3,
				),
			),
			'tolerance' => 0.25,
		) ),
		'hottext'     => q( 'hot-text', '<p>Select the even numbers.</p>', array(), array(
			'tokens'  => array(
				array(
					'id'   => 't1',
					'text' => '2',
				),
				array(
					'id'   => 't2',
					'text' => '3',
				),
				array(
					'id'   => 't3',
					'text' => '4',
				),
			),
			'correct' => array( 't1', 't3' ),
		) ),
		'matchgrid'   => q( 'match-table-grid', '<p>Match each sum.</p>', array(), array(
			'rows'    => array(
				array(
					'id'    => 'r1',
					'label' => '2 + 2',
				),
				array(
					'id'    => 'r2',
					'label' => '3 + 3',
				),
			),
			'columns' => array(
				array(
					'id'    => 'c1',
					'label' => '4',
				),
				array(
					'id'    => 'c2',
					'label' => '6',
				),
			),
			'key'     => array(
				'r1' => 'c1',
				'r2' => 'c2',
			),
		) ),
		'labeling'    => q( 'labeling', '<p>Label the diagram.</p>', array(), array(
			'image_url'   => 'https://example.com/diagram.png',
			'targets'     => array(
				array(
					'id'     => 'a',
					'x'      => 25,
					'y'      => 50,
					'answer' => 'A',
				),
				array(
					'id'     => 'b',
					'x'      => 75,
					'y'      => 50,
					'answer' => 'B',
				),
			),
			'distractors' => array(),
		) ),
		'hotspot'     => q( 'hotspot', '<p>Click the centre.</p>', array(), array(
			'image_url' => 'https://example.com/diagram.png',
			'zones'     => array(
				array(
					'x'      => 50,
					'y'      => 50,
					'radius' => 12,
				),
			),
		) ),
		'draw'        => q( 'draw', '<p>Draw a triangle.</p>' ),
		'audio'       => q( 'audio-response', '<p>Say hello.</p>', array(), array( 'max_seconds' => 60 ) ),
		'video'       => q( 'video-response', '<p>Show your working.</p>', array(), array( 'max_seconds' => 90 ) ),
		'poll'        => q( 'poll', '<p>Do you like maths?</p>', array(), array(
			'choices' => array(
				array(
					'id'   => 'c1',
					'text' => 'Yes',
				),
				array(
					'id'   => 'c2',
					'text' => 'No',
				),
			),
		), 0 ),
		'cloud'       => q( 'word-cloud', '<p>One word for maths.</p>', array(), array(), 0 ),
		'discussion'  => q( 'discussion-board', '<p>Discuss.</p>' ),
		'ivideo'      => q( 'interactive-video', '<p>Watch and answer.</p>', array(), array(
			'video_url'   => 'https://example.com/v.mp4',
			'checkpoints' => array(
				array(
					'id'     => 'a',
					'at'     => 5,
					'prompt' => 'What is 2 + 2?',
					'answer' => '4',
				),
			),
		) ),
	);
	$content = array();
	foreach ( array_values( $samples ) as $index => $payload ) {
		$payload['order_number'] = $index + 1;
		$content[]               = $payload;
	}

	// ---- Build the source quiz the way the quiz editor saves it ----
	$created = call(
		'POST',
		'quiz',
		array(
			'name'   => 'IT source quiz',
			'status' => 'draft',
		)
	);
	ok( 201 === $created->get_status(), 'Source quiz was not created' );
	$source_id = (int) $created->get_data()['id'];
	$saved     = call(
		'PUT',
		'quiz/' . $source_id,
		array(
			'description'   => '<p>Covers <strong>everything</strong>.</p>',
			'settings'      => array(
				'time_limit'           => array(
					'value' => 30,
					'type'  => 'minutes',
				),
				'passing_grade'        => array(
					'enabled' => true,
					'value'   => 60,
				),
				'hide_answers'         => true,
				'randomize_questions'  => true,
				'allow_attempts'       => 3,
				'layout'               => 'one_question_per_page',
				'player_template'      => 'paper',
				'short_text_limit'     => 200,
			),
			'drip_settings' => array(
				'enable' => true,
				'type'   => 'enrollment-from-x-days',
				'days'   => 3,
			),
			'content'       => $content,
		)
	);
	ok( 200 === $saved->get_status(), 'Source questions were not saved: ' . wp_json_encode( $saved->get_data() ) );
	QuizSkills::set( $source_id, array( $term_a, $term_b ) );
	$source_rows = question_rows( $source_id );
	ok( count( $source_rows ) === count( $samples ), 'Source quiz has every sample question' );
	// Extension settings are stored beside the question, then frozen into a new version.
	update_post_meta( $source_id, '_ohmylms_extension_settings', wp_slash( array( 'it-exchange' => array( 'note' => 'quiz-level' ) ) ) );
	update_post_meta( $source_rows[0]['id'], '_ohmylms_extension_settings', wp_slash( array( 'it-exchange' => array( 'note' => 'question-level' ) ) ) );
	foreach ( $source_rows as $row ) {
		VersionPublisher::capture( $row['id'] );
	}

	// ---- Export ----
	$export = Exporter::export( $source_id );
	ok( ! is_wp_error( $export ), 'Export failed' );
	ok( 'IT-source-quiz.ohmylms.json' === $export['filename'] && 'application/json' === $export['mime'], 'Export filename and type: ' . $export['filename'] );
	ok( array() === $export['warnings'], 'A complete quiz exports without warnings: ' . wp_json_encode( $export['warnings'] ) );
	$parsed = Document::parse( $export['content'] );
	ok( ! is_wp_error( $parsed ), 'The exported file parses: ' . ( is_wp_error( $parsed ) ? problems_text( $parsed ) : '' ) );
	$exported = $parsed['document'];
	ok( array() === $parsed['warnings'], 'The exported file has no warnings' );
	ok( count( $exported['questions'] ) === count( $samples ) && $export['summary']['questions'] === count( $samples ), 'Every question is in the file' );
	ok( 2 === $export['summary']['randomized'], 'The summary counts the two randomized questions' );
	$file_types = array_keys( $export['summary']['types'] );
	foreach ( $samples as $label => $sample ) {
		ok( in_array( $sample['settings']['type'], $file_types, true ), 'Type ' . $sample['settings']['type'] . ' (' . $label . ') is in the file' );
	}
	ok( 'paper' === $exported['quiz']['settings']['player_template'] && 3 === $exported['quiz']['settings']['allow_attempts'], 'Quiz settings are exported' );
	ok( 3 === $exported['quiz']['drip_settings']['days'], 'Drip settings are exported' );
	ok( 2 === count( $exported['quiz']['skills'] ) && $uuid_a === $exported['quiz']['skills'][0]['uuid'], 'Quiz skills are exported with their uuid' );
	ok( 'quiz-level' === $exported['quiz']['extension_settings']['it-exchange']['note'], 'Quiz extension settings are exported' );
	$first = $exported['questions'][0];
	ok( 'question-level' === $first['extension_settings']['it-exchange']['note'], 'Question extension settings are exported' );
	ok( $image_url === $first['image_url'] && $image_url === $first['options'][0]['image_url'], 'Question and option images are exported as addresses' );
	ok( 'it-fractions' === $first['skills']['p1']['primary']['slug'] && 'it-ratios' === $first['skills']['p1']['supporting'][0]['slug'], 'Question skills are exported' );
	ok( 'challenge' === $exported['questions'][1]['bank']['difficulty'] && true === $exported['questions'][1]['bank']['secure'], 'Bank attributes are exported' );
	ok( isset( $exported['questions'][10]['settings']['template']['variables'] ) && '{{a}} pens cost {{x:2}} each. What is the total?' === strip_tags( $exported['questions'][10]['description'] ), 'A randomized question exports its template and placeholders' );
	ok( '{{a+b}}' === $exported['questions'][11]['options'][0]['answer'], 'Placeholders in option text are exported' );

	// ---- Dry run changes nothing and reports what the file holds ----
	$before = content_count();
	$dry    = Importer::run( $export['content'], array( 'dry_run' => true ) );
	ok( ! is_wp_error( $dry ) && true === $dry['dry_run'] && empty( $dry['id'] ), 'Dry run reports without importing' );
	ok( content_count() === $before, 'A dry run created content' );
	ok( $dry['summary']['questions'] === count( $samples ) && 'IT source quiz (imported ' === substr( $dry['name'], 0, 25 ) && true === $dry['renamed'], 'Dry run names the copy so it is easy to spot: ' . $dry['name'] );

	// ---- Import: a new draft that is the same quiz ----
	$result = Importer::run( $export['content'], array() );
	ok( ! is_wp_error( $result ) && ! empty( $result['id'] ), 'Import failed: ' . ( is_wp_error( $result ) ? problems_text( $result ) : '' ) );
	$new_id = (int) $result['id'];
	ok( $new_id !== $source_id && 'draft' === get_post_status( $new_id ), 'The import is a new draft quiz' );
	ok( array() === $result['warnings'], 'A same-site import has no warnings: ' . wp_json_encode( $result['warnings'] ) );
	$new_rows = question_rows( $new_id );
	ok( count( $new_rows ) === count( $source_rows ), 'Every question was imported' );
	$source_post = get_post( $source_id );
	$new_post    = get_post( $new_id );
	ok( $new_post->post_content === $source_post->post_content, 'The quiz description is kept' );
	$source_quiz = ohmylms_get_quiz( $source_id );
	$new_quiz    = ohmylms_get_quiz( $new_id );
	ok( $new_quiz->get_settings() == $source_quiz->get_settings(), 'Quiz settings are identical' );
	ok( $new_quiz->get_drip_settings() == $source_quiz->get_drip_settings(), 'Drip settings are identical' );
	ok( QuizSkills::get( $new_id ) === QuizSkills::get( $source_id ), 'Quiz skills are linked' );
	ok( get_post_meta( $new_id, '_ohmylms_extension_settings', true ) == get_post_meta( $source_id, '_ohmylms_extension_settings', true ), 'Quiz extension settings are kept' );

	foreach ( $source_rows as $index => $row ) {
		$copy  = $new_rows[ $index ];
		$label = 'Question ' . ( $index + 1 ) . ' (' . $row['settings']['type'] . ')';
		ok( (int) $copy['id'] !== (int) $row['id'], $label . ' is a new question, not a link to the original' );
		ok( stored( $copy['id'] ) == stored( $row['id'] ), $label . ' is stored identically' );
		$copy_snapshot = snapshot_of( $copy['id'] );
		ok( null !== $copy_snapshot && $copy_snapshot->get_type() === $row['settings']['type'], $label . ' has a frozen version of the right type' );
		ok( \OhMyLMS\QuestionBank\SkillMap::current( $copy['id'] ) == \OhMyLMS\QuestionBank\SkillMap::current( $row['id'] ), $label . ' has the same skill mapping' );
		ok( \OhMyLMS\QuestionBank\BankAttributes::get( $copy['id'] )['difficulty'] === \OhMyLMS\QuestionBank\BankAttributes::get( $row['id'] )['difficulty'], $label . ' has the same difficulty' );
	}
	ok( 'challenge' === \OhMyLMS\QuestionBank\BankAttributes::get( $new_rows[1]['id'] )['difficulty'] && 1 === (int) \OhMyLMS\QuestionBank\BankAttributes::get( $new_rows[1]['id'] )['secure'], 'Bank attributes were written' );
	ok( (int) $new_rows[0]['thumbnail_id'] === $attachment, 'A media file this site already has is reused, not duplicated' );
	ok( 1 === count( array_filter( array_keys( \OhMyLMS\QuestionBank\SkillMap::current( $new_rows[0]['id'] ) ) ) ), 'The skill map keeps its part' );
	ok( 'question-level' === get_post_meta( $new_rows[0]['id'], '_ohmylms_extension_settings', true )['it-exchange']['note'], 'Question extension settings were written' );
	ok( snapshot_of( $new_rows[0]['id'] )->to_array()['extension']['it-exchange']['note'] === 'question-level', 'Extension settings are frozen into the imported version' );

	// A second export of the copy is the same file (apart from the quiz name and date).
	$again = Exporter::export( $new_id );
	ok( ! is_wp_error( $again ) && array() === $again['warnings'], 'The copy exports cleanly' );
	$left  = Document::parse( $export['content'] )['document'];
	$right = Document::parse( $again['content'] )['document'];
	$left['source']        = $right['source'] = array();
	$left['quiz']['name']  = $right['quiz']['name'] = '';
	ok( $left == $right, 'export(import(export(quiz))) equals export(quiz)' );

	// ---- The imported questions work, and grade like the originals ----
	$by_label = array();
	foreach ( array_keys( $samples ) as $index => $label ) {
		$by_label[ $label ] = array(
			'source' => (int) $source_rows[ $index ]['id'],
			'copy'   => (int) $new_rows[ $index ]['id'],
		);
	}
	foreach ( array( 'source', 'copy' ) as $side ) {
		$tag    = ' (' . $side . ')';
		$single = snapshot_of( $by_label['single'][ $side ] );
		ok( grade_with( $single, correct_option_ids( $single ) )['correct'], 'Single choice: the right option is correct' . $tag );
		ok( ! grade_with( $single, array( wrong_option_ids( $single )[0] ) )['correct'], 'Single choice: a wrong option is wrong' . $tag );
		$multiple = snapshot_of( $by_label['multiple'][ $side ] );
		ok( grade_with( $multiple, correct_option_ids( $multiple ) )['fraction'] === 1.0, 'Multiple choice: all correct earns full marks' . $tag );
		$half = grade_with( $multiple, array( correct_option_ids( $multiple )[0] ) );
		ok( 0.5 === $half['fraction'], 'Multiple choice: partial credit is kept' . $tag );
		$truefalse = snapshot_of( $by_label['truefalse'][ $side ] );
		ok( grade_with( $truefalse, correct_option_ids( $truefalse ) )['correct'], 'True / false grades' . $tag );
		$fill = snapshot_of( $by_label['fill'][ $side ] );
		ok( grade_with( $fill, array( 'paris', 'FRANCE' ) )['correct'], 'Fill in the blank: case-insensitive setting is kept' . $tag );
		ok( 0.5 === grade_with( $fill, array( 'paris', 'Spain' ) )['fraction'], 'Fill in the blank: each blank earns its share' . $tag );
		ok( grade_with( snapshot_of( $by_label['statement'][ $side ] ), array( 'chloroplast' ) )['correct'], 'Statement grades' . $tag );
		ok( grade_with( snapshot_of( $by_label['numerical'][ $side ] ), array( '12.3' ) )['correct'], 'Numerical: tolerance is kept' . $tag );
		ok( ! grade_with( snapshot_of( $by_label['numerical'][ $side ] ), array( '14' ) )['correct'], 'Numerical: outside tolerance is wrong' . $tag );
		ok( grade_with( snapshot_of( $by_label['expression'][ $side ] ), array( '2(x+3)' ) )['correct'], 'Math expression: equivalent form is correct' . $tag );
		ok( grade_with( snapshot_of( $by_label['multiblank'][ $side ] ), array( 'a' => '7' ) )['correct'], 'Multi-blank grades' . $tag );
		ok( grade_with( snapshot_of( $by_label['hottext'][ $side ] ), array( 't1' => '1', 't3' => '1' ) )['correct'], 'Hot text grades' . $tag );
		ok( grade_with( snapshot_of( $by_label['categorize'][ $side ] ), array( 'i1' => 'b1', 'i2' => 'b2' ) )['correct'], 'Categorize grades' . $tag );
		ok( grade_with( snapshot_of( $by_label['numberline'][ $side ] ), array( 'value' => '5' ) )['correct'], 'Number line grades' . $tag );
		ok( grade_with( snapshot_of( $by_label['structured'][ $side ] ), array( 'a' => '12', 'b' => 'Rectangle' ) )['fraction'] > 0.4, 'Structured parts grade' . $tag );
	}

	// ---- Randomized variables: same template, same numbers for the same seed ----
	foreach ( array( 'rand_number', 'rand_choice' ) as $label ) {
		$source_snapshot = snapshot_of( $by_label[ $label ]['source'] );
		$copy_snapshot   = snapshot_of( $by_label[ $label ]['copy'] );
		ok( $copy_snapshot->is_template() && $source_snapshot->is_template(), $label . ' stays a randomized question' );
		$seen = array();
		foreach ( array( 1, 2, 3, 17, 4242 ) as $seed ) {
			$a = $source_snapshot->instantiate( $seed );
			$b = $copy_snapshot->instantiate( $seed );
			ok( $a->get_name() === $b->get_name() && $a->get_description() === $b->get_description(), $label . ' seed ' . $seed . ': same wording' );
			ok( $a->get_settings() == $b->get_settings(), $label . ' seed ' . $seed . ': same settings' );
			ok( array_column( $a->get_questions(), 'answer' ) === array_column( $b->get_questions(), 'answer' ), $label . ' seed ' . $seed . ': same option text' );
			ok( false === strpos( $b->get_description(), '{{' ), $label . ' seed ' . $seed . ': every placeholder is filled in' );
			$seen[ $b->get_description() ] = true;
			if ( 'rand_number' === $label ) {
				$answer = (string) $b->get_settings()['answer'];
				ok( '' !== $answer && $answer === (string) $a->get_settings()['answer'], 'Seed ' . $seed . ': the computed answer matches the original' );
				ok( grade_with( $b, array( $answer ) )['correct'], 'Seed ' . $seed . ': the computed answer is graded correct' );
				ok( ! grade_with( $b, array( (string) ( (float) $answer + 5 ) ) )['correct'], 'Seed ' . $seed . ': a wrong total is graded wrong' );
			} else {
				ok( grade_with( $b, correct_option_ids( $b ) )['correct'], 'Seed ' . $seed . ': the computed option is graded correct' );
				$texts = array_column( $b->get_questions(), 'answer' );
				ok( count( array_unique( $texts ) ) === 3 && false === strpos( implode( '', $texts ), '{{' ), 'Seed ' . $seed . ': option text is computed' );
			}
		}
		ok( count( $seen ) > 1, $label . ': different seeds give different numbers' );
	}

	// ---- Through the REST API: dry run, import, export, and the old CSV route ----
	$before = content_count();
	$dry    = call( 'POST', 'quiz/import', array( 'content' => $export['content'], 'dry_run' => true ) );
	ok( 200 === $dry->get_status() && true === $dry->get_data()['dry_run'] && 'ohmylms' === $dry->get_data()['format'], 'REST dry run: ' . wp_json_encode( $dry->get_data() ) );
	ok( content_count() === $before, 'The REST dry run created content' );
	$rest = call( 'POST', 'quiz/import', array( 'content' => $export['content'], 'name' => 'IT REST copy' ) );
	ok( 201 === $rest->get_status() && 'IT REST copy' === get_the_title( $rest->get_data()['id'] ) && 'draft' === get_post_status( $rest->get_data()['id'] ), 'REST import creates the named draft' );
	$file = call( 'GET', 'quiz/' . $source_id . '/export' );
	ok( 200 === $file->get_status() && 'application/json' === $file->get_data()['mime'] && 0 === strpos( $file->get_data()['content'], "{\n    \"format\": \"ohmylms.quiz\"" ), 'REST export returns the file' );
	$csv = call( 'POST', 'quiz/import', array( 'name' => 'IT CSV quiz', 'csv' => "type,question,points,correct,option_1,option_2\nsingle-choice,What is 2+2?,1,2,3,4\n" ) );
	ok( 201 === $csv->get_status() && 'draft' === get_post_status( $csv->get_data()['id'] ) && 'csv' === $csv->get_data()['format'], 'The older CSV import still works: ' . wp_json_encode( $csv->get_data() ) );
	$csv_dry = call( 'POST', 'quiz/import', array( 'csv' => "type,question,points,correct,option_1,option_2\nsingle-choice,What is 2+2?,1,2,3,4\n", 'dry_run' => true ) );
	ok( 200 === $csv_dry->get_status() && 1 === $csv_dry->get_data()['summary']['questions'], 'A CSV dry run reports its questions' );
	ok( 400 === call( 'POST', 'quiz/import', array( 'content' => '   ' ) )->get_status(), 'An empty upload is a 400' );

	// ---- Refused files: everything is checked first, nothing is created ----
	$base = json_decode( $export['content'], true );
	$mutate = static function ( callable $change ) use ( $base ) {
		$copy = $base;
		$change( $copy );
		return encode_file( $copy );
	};
	refuse( '{"questions": [', 'not valid JSON', 'Broken JSON' );
	refuse(
		$mutate(
			static function ( &$d ) {
				$d['questions'][2]['options'][0]['is_correct'] = false;
				$d['questions'][2]['options'][1]['is_correct'] = false;
			}
		),
		'Question 3: Mark exactly one option as correct',
		'No correct answer'
	);
	// ...and every problem is listed, not just the first.
	$before = content_count();
	$many   = Importer::run(
		$mutate(
			static function ( &$d ) {
				$d['questions'][0]['options'] = array( $d['questions'][0]['options'][0] );
				$d['questions'][3]['settings']['type'] = 'not-a-registered-type';
				$d['questions'][4]['description'] = '';
				$d['questions'][4]['name'] = '';
				$d['questions'][10]['description'] = '<p>{{a}} pens cost {{z}} each.</p>';
			}
		),
		array()
	);
	$text   = problems_text( $many );
	ok( is_wp_error( $many ) && count( $many->get_error_data()['errors'] ) >= 4, 'All problems are reported together: ' . $text );
	ok( false !== strpos( $text, 'Question 1: It needs at least two options' ), 'Too few options is reported' );
	ok( false !== strpos( $text, 'Question 4' ) && false !== strpos( $text, 'not-a-registered-type' ), 'An unavailable question type is reported by number' );
	ok( false !== strpos( $text, 'Question 5: The question text is empty' ), 'An empty question is reported' );
	ok( false !== strpos( $text, 'Question 11: Randomized numbers' ) && false !== strpos( $text, '{{z}}' ), 'A template formula that cannot be worked out is reported with the formula' );
	ok( content_count() === $before, 'Nothing was created by a file with several bad questions' );
	refuse(
		$mutate(
			static function ( &$d ) {
				$d['questions'][10]['settings']['template']['variables'][0]['min'] = 5;
				$d['questions'][10]['settings']['template']['variables'][0]['max'] = 3;
			}
		),
		'Question 11: Randomized numbers',
		'Impossible template range'
	);
	refuse(
		$mutate(
			static function ( &$d ) {
				$d['questions'][10]['settings']['template']['variables'][0]['min'] = 5;
				$d['questions'][10]['settings']['template']['variables'][0]['max'] = 5;
				$d['questions'][10]['settings']['template']['variables'][1]['min'] = 2;
				$d['questions'][10]['settings']['template']['variables'][1]['max'] = 2;
			}
		),
		'numbers never change',
		'A template that never varies'
	);
	refuse(
		$mutate(
			static function ( &$d ) {
				$d['questions'][0]['extension_settings'] = array( 'no-such-extension' => array( 'note' => 'x' ) );
			}
		),
		'Extension settings cannot be imported',
		'Unregistered extension settings'
	);
	refuse(
		$mutate(
			static function ( &$d ) {
				$d['questions'][9]['settings']['answer'] = 'twelve';
			}
		),
		'Question 10',
		'Numerical answer that is not a number'
	);
	refuse(
		$mutate(
			static function ( &$d ) {
				$d['questions'][7]['options'][1]['matching_data']['label'] = '';
			}
		),
		'Question 8: Pair 2 needs an item and a matching definition',
		'A matching pair without a definition'
	);
	refuse(
		$mutate(
			static function ( &$d ) {
				$d['questions'][5]['name'] = 'No braces here';
			}
		),
		'Question 6: Write the answers in braces',
		'Fill in the blank without blanks'
	);
	refuse(
		$mutate(
			static function ( &$d ) {
				$d['questions'][1]['bank']['difficulty'] = 'impossible';
			}
		),
		'Question 2: Difficulty must be easy, standard or challenge',
		'Bad bank difficulty'
	);

	// ---- Cross-site skills: uuid first, then slug; a missing skill is a warning ----
	$count_before = content_count();
	$result       = Importer::run(
		$mutate(
			static function ( &$d ) {
				$d['quiz']['skills'][0]['uuid']                          = 'uuid-from-another-site';
				$d['questions'][0]['skills']['p1']['primary']['uuid']    = 'uuid-from-another-site';
				$d['questions'][0]['skills']['p1']['supporting'][]       = array(
					'uuid' => 'gone',
					'slug' => 'it-no-such-skill',
					'name' => 'Skill that does not exist here',
				);
				$d['quiz']['settings']['player_template']                = 'a-design-this-site-lacks';
				$d['quiz']['name']                                       = 'IT cross-site quiz';
				$d['quiz']['status']                                     = 'publish';
			}
		),
		array()
	);
	ok( ! is_wp_error( $result ), 'A file with an unknown skill still imports: ' . ( is_wp_error( $result ) ? problems_text( $result ) : '' ) );
	$warnings = implode( ' | ', $result['warnings'] );
	ok( false !== strpos( $warnings, 'Skill that does not exist here' ) && false !== strpos( $warnings, 'not linked' ), 'The missing skill is named in a warning: ' . $warnings );
	ok( false !== strpos( $warnings, 'a-design-this-site-lacks' ), 'An unknown player design is a warning' );
	ok( 'draft' === get_post_status( $result['id'] ) && 'IT cross-site quiz' === get_the_title( $result['id'] ), 'A quiz marked published still imports as a draft' );
	$cross_rows = question_rows( $result['id'] );
	ok( \OhMyLMS\QuestionBank\SkillMap::current( $cross_rows[0]['id'] ) == array(
		'p1' => array(
			'primary'    => $term_a,
			'supporting' => array( $term_b ),
		),
	), 'Skills match by slug when the uuid is unknown, and the missing one is left out' );
	ok( QuizSkills::get( $result['id'] ) === array( $term_a, $term_b ), 'Quiz skills match by slug too' );
	ok( ! isset( ohmylms_get_quiz( $result['id'] )->get_settings()['player_template'] ), 'The unknown player design is dropped' );

	// ---- Media from another site is copied in; failures are warnings, never silent ----
	$http_filter = static function ( $pre, $args, $url ) use ( &$http_log, $png ) {
		if ( 0 !== strpos( $url, 'https://source.example/' ) ) {
			return $pre;
		}
		$http_log[] = $url;
		if ( false !== strpos( $url, 'broken' ) ) {
			return new WP_Error( 'http_request_failed', 'Simulated network failure' );
		}
		if ( ! empty( $args['filename'] ) ) {
			file_put_contents( $args['filename'], $png );
		}
		return array(
			'headers'  => array( 'content-type' => 'image/png' ),
			'body'     => $png,
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => $args['filename'] ?? null,
		);
	};
	add_filter( 'pre_http_request', $http_filter, PHP_INT_MAX, 3 );
	$remote  = 'https://source.example/wp-content/uploads/2026/10';
	$foreign = $mutate(
		static function ( &$d ) use ( $remote ) {
			$d['source']['uploads_url']                                         = 'https://source.example/wp-content/uploads';
			$d['quiz']['name']                                                  = 'IT media quiz';
			$d['questions'][0]['image_url']                                     = $remote . '/question.png';
			$d['questions'][0]['options'][0]['image_url']                       = $remote . '/option.png';
			$d['questions'][7]['options'][0]['matching_data']['image_url']      = $remote . '/pair.png';
			$d['questions'][3]['description']                                   = '<p>Name a prime <img src="' . $remote . '/inline.png" alt=""></p>';
			$d['questions'][4]['image_url']                                     = $remote . '/broken.png';
			$d['questions'][5]['settings']['hint']                              = 'See ' . $remote . '/inline.png';
		}
	);
	$attachments_before = (int) $GLOBALS['wpdb']->get_var( "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->posts} WHERE post_type='attachment'" );
	$dry_media          = Importer::run( $foreign, array( 'dry_run' => true ) );
	ok( ! is_wp_error( $dry_media ) && array() === $http_log, 'A dry run downloads nothing' );
	$media_result = Importer::run( $foreign, array() );
	ok( ! is_wp_error( $media_result ), 'The media import succeeds: ' . ( is_wp_error( $media_result ) ? problems_text( $media_result ) : '' ) );
	$media_rows = question_rows( $media_result['id'] );
	$new_thumb  = (int) $media_rows[0]['thumbnail_id'];
	ok( $new_thumb > 0 && $new_thumb !== $attachment && wp_get_attachment_url( $new_thumb ), 'The question image was copied into the media library' );
	ok( (int) $media_rows[0]['questions'][0]['thumbnail_id'] > 0 && (int) $media_rows[0]['questions'][0]['thumbnail_id'] !== $new_thumb, 'The option image was copied' );
	$pair_image = $media_rows[7]['questions'][0]['matching_data'];
	ok( 0 === strpos( $pair_image['image_url'], $uploads['baseurl'] ) && (int) $pair_image['image_id'] > 0, 'The matching image was copied and points at this site' );
	ok( false === strpos( $media_rows[3]['description'], 'source.example' ) && false !== strpos( $media_rows[3]['description'], $uploads['baseurl'] ), 'An address inside question text is rewritten to the copy' );
	ok( false === strpos( $media_rows[5]['settings']['hint'], 'source.example' ), 'Addresses inside settings are rewritten too' );
	ok( 0 === (int) $media_rows[4]['thumbnail_id'], 'An image that cannot be fetched is left out' );
	$media_warnings = implode( ' | ', $media_result['warnings'] );
	ok( false !== strpos( $media_warnings, 'Question 5' ) && false !== strpos( $media_warnings, 'broken.png' ) && false !== strpos( $media_warnings, 'Simulated network failure' ), 'The failed image is reported with its question and reason: ' . $media_warnings );
	ok( 1 === count( $media_result['warnings'] ), 'Only the failed image is reported' );
	ok( 4 === $media_result['media_copied'], 'Four files were copied: question image, option image, matching image, and the file used in text and in a hint' );
	ok( 1 === count( array_keys( $http_log, $remote . '/inline.png', true ) ), 'A file used twice is downloaded once' );
	$attachments_after = (int) $GLOBALS['wpdb']->get_var( "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->posts} WHERE post_type='attachment'" );
	ok( $attachments_after - $attachments_before === $media_result['media_copied'], 'Exactly the copied files were added to the media library' );
	$no_copy = Importer::run( $foreign, array( 'copy_media' => false ) );
	ok( ! is_wp_error( $no_copy ) && empty( $no_copy['media_copied'] ), 'Copying can be switched off' );
	ok( false !== strpos( implode( ' | ', $no_copy['warnings'] ), 'copying media was switched off' ), 'Skipped media is reported' );
	remove_filter( 'pre_http_request', $http_filter, PHP_INT_MAX );
	$http_filter = null;

	// ---- Permissions ----
	$author_a   = make_user( 'author' );
	$author_b   = make_user( 'author' );
	$subscriber = make_user( 'subscriber' );
	as_user( $subscriber );
	ok( 403 === call( 'POST', 'quiz/import', array( 'content' => $export['content'] ) )->get_status(), 'A subscriber cannot import' );
	ok( 403 === call( 'GET', 'quiz/' . $source_id . '/export' )->get_status(), 'A subscriber cannot export' );
	as_user( $author_a );
	$own = call( 'POST', 'quiz/import', array( 'content' => $export['content'], 'name' => 'IT author quiz' ) );
	ok( 201 === $own->get_status(), 'An author can import' );
	$own_id = (int) $own->get_data()['id'];
	ok( 200 === call( 'GET', 'quiz/' . $own_id . '/export' )->get_status(), 'An author can export their own quiz' );
	ok( 403 === call( 'GET', 'quiz/' . $source_id . '/export' )->get_status(), 'An author cannot export an admin quiz (the file holds every answer key)' );
	as_user( $author_b );
	ok( 403 === call( 'GET', 'quiz/' . $own_id . '/export' )->get_status(), 'Another author cannot export it either' );
	ok( is_wp_error( Exporter::export( $own_id ) ), 'The exporter checks access itself' );
	as_user( 0 );
	ok( 401 === call( 'GET', 'quiz/' . $source_id . '/export' )->get_status(), 'A guest cannot export' );
	ok( 401 === call( 'POST', 'quiz/import', array( 'content' => $export['content'] ) )->get_status(), 'A guest cannot import' );
	as_user( $admin );

	// ---- An unfinished question still exports, with a warning, and the file is then refused ----
	$draft = call( 'POST', 'quiz', array( 'name' => 'IT unfinished', 'status' => 'draft' ) )->get_data()['id'];
	call( 'PUT', 'quiz/' . $draft, array( 'content' => array( q( 'single-choice', '<p>Half done</p>', array( opt( 'only one' ) ) ) ) ) );
	$unfinished = Exporter::export( $draft );
	ok( ! is_wp_error( $unfinished ) && count( $unfinished['warnings'] ) >= 1 && 1 === $unfinished['warnings'][0]['question'] && false !== strpos( $unfinished['warnings'][0]['message'], 'unfinished' ), 'An unfinished question is flagged on export: ' . wp_json_encode( $unfinished['warnings'] ) );
	refuse( $unfinished['content'], 'Question 1', 'An unfinished question' );

	echo "quiz exchange: passed\n";
	echo $checks . " quiz exchange integration checks passed.\n";
} finally {
	if ( $http_filter ) {
		remove_filter( 'pre_http_request', $http_filter, PHP_INT_MAX );
	}
	as_user( $admin );
	global $wpdb;
	// Everything this run created: questions (with their options and versions), quizzes, copied media.
	foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID > %d AND post_type='ohmylms-question' ORDER BY ID", $baseline ) ) as $question_id ) {
		\OhMyLMS\QuestionBank\Usage::delete_or_archive( (int) $question_id );
	}
	foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID > %d AND post_type IN ('ohmylms-quiz','ohmylms-question','attachment') ORDER BY ID", $baseline ) ) as $post_id ) {
		$wpdb->delete( \OhMyLMS\Assessment\Schema::table( 'quiz_skills' ), array( 'quiz_id' => (int) $post_id ), array( '%d' ) );
		wp_delete_post( (int) $post_id, true );
	}
	if ( ! empty( $attachment ) ) {
		wp_delete_attachment( $attachment, true );
	}
	foreach ( $fixture['terms'] as $term_id ) {
		wp_delete_term( $term_id, Taxonomy::NAME );
	}
	foreach ( $fixture['users'] as $user_id ) {
		wp_delete_user( $user_id );
	}
}
