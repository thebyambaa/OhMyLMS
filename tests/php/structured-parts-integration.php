<?php
/**
 * Frozen structured leaf contracts against the local WordPress runtime.
 *
 * @package OhMyLMS
 */

$structured_site = getenv( 'OHMYLMS_TEST_SITE_PATH' );
if ( 'cli' !== PHP_SAPI || ! $structured_site || ! is_file( $structured_site . '/wp-load.php' ) ) {
	throw new RuntimeException( 'Set OHMYLMS_TEST_SITE_PATH to a local WordPress installation.' );
}
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
define( 'DISABLE_WP_CRON', true );
require $structured_site . '/wp-load.php';

use OhMyLMS\Assessment\Grader;
use OhMyLMS\Assessment\QuestionSnapshot;
use OhMyLMS\Assessment\Structured;
use OhMyLMS\Assessment\StructuredPart;

$structured_checks = 0;
/**
 * Assert a structured contract.
 *
 * @param bool   $condition Expected outcome.
 * @param string $message Failure description.
 * @throws RuntimeException When an assertion fails.
 */
function structured_check( $condition, $message ) {
	global $structured_checks;
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
	++$structured_checks;
}

$choice   = array(
	'id'       => 'choice',
	'label'    => '(b)',
	'prompt'   => '<b>Choose four.</b>',
	'kind'     => 'question',
	'marks'    => 2,
	'rubric'   => 'private teacher note',
	'question' => array(
		'settings'  => array( 'type' => 'single-choice' ),
		'questions' => array(
			array(
				'id'           => 41,
				'answer'       => 'Four',
				'is_correct'   => true,
				'order_number' => 1,
			),
			array(
				'id'           => 42,
				'answer'       => 'Five',
				'is_correct'   => false,
				'order_number' => 2,
			),
		),
	),
);
$chart    = array(
	'id'       => 'chart',
	'label'    => '(c)',
	'prompt'   => 'Set A to three.',
	'kind'     => 'question',
	'marks'    => 3,
	'question' => array(
		'settings'  => array(
			'type'       => 'build-chart',
			'categories' => array(
				array(
					'id'    => 'a',
					'label' => 'A',
				),
			),
			'values'     => array( 'a' => 3 ),
			'max'        => 10,
			'step'       => 1,
		),
		'questions' => array(),
	),
);
$settings = array(
	'type'  => 'structured',
	'parts' => array(
		array(
			'id'     => 'a',
			'label'  => '(a)',
			'prompt' => 'Find two.',
			'kind'   => 'numerical',
			'marks'  => 1,
			'answer' => 2,
		),
		$choice,
		$chart,
	),
);
$question = new QuestionSnapshot(
	array(
		'id'            => 99,
		'question_id'   => 71,
		'version_no'    => 1,
		'question_uuid' => 'parent',
		'type'          => 'structured',
		'title'         => 'Shared stem',
		'body'          => '',
		'settings'      => $settings,
		'options'       => array(),
		'parts'         => array(),
		'extension'     => array(),
		'media'         => array(),
	)
);
structured_check( true === Structured::validate_settings( $settings ), 'Mixed parts validate through leaf schemas.' );
$view   = $question->student_view( array(), array(), array(), array(), 'attempt1' );
$public = wp_json_encode( $view );
structured_check( ! str_contains( $public, 'is_correct' ) && ! str_contains( $public, 'private teacher note' ) && ! str_contains( $public, '"values"' ), 'No nested key or rubric leaks.' );
$token = $view['settings']['parts'][1]['question']['questions'][0]['id'];
structured_check( ! is_numeric( $token ), 'Nested options use opaque tokens.' );
$next = $question->student_view( array(), array(), array(), array(), 'attempt2' );
structured_check( $token !== $next['settings']['parts'][1]['question']['questions'][0]['id'], 'Nested option tokens change between attempts.' );
$answer  = array(
	'a'      => '2',
	'choice' => array( $token ),
	'chart'  => array( 'a' => '3' ),
);
$decoded = Structured::map_response( $question, $answer, 'attempt1', true );
structured_check( 41 === $decoded['choice'][0] && '2' === $decoded['a'], 'Only nested option responses are decoded.' );
structured_check( Structured::map_response( $question, $decoded, 'attempt1', false ) === $answer, 'Resume tokenization round trips.' );
$grade = Grader::grade( $question, $decoded );
structured_check( ! is_wp_error( $grade ) && 1.0 === $grade['fraction'], 'Correct mixed parts receive full marks.' );
structured_check(
	array(
		'a'      => 1.0,
		'choice' => 1.0,
		'chart'  => 1.0,
	) === $grade['parts'],
	'Stable part IDs preserve evidence attribution.'
);
$decoded['choice'] = array( 42 );
$grade             = Grader::grade( $question, $decoded );
structured_check( abs( $grade['fraction'] - 4 / 6 ) < 0.00001, 'Part marks weight the parent score.' );
$raw = Structured::map_response( $question, array( 'choice' => array( 41 ) ), 'attempt1', true );
structured_check( 0.0 === Grader::grade( $question, $raw )['fraction'], 'Raw option IDs cannot bypass delivery tokens.' );
$wrong_scope = Structured::map_response( $question, $answer, 'attempt2', true );
structured_check( 0.0 === Grader::grade( $question, $wrong_scope )['parts']['choice'], 'Cross-attempt tokens are rejected.' );
structured_check( is_wp_error( Grader::grade( $question, array( 'choice' => array( array( 'invalid' ) ) ) ) ), 'Malformed nested responses are rejected.' );
structured_check( is_wp_error( Grader::grade( $question, array( 'unknown' => 'value' ) ) ), 'Unknown part IDs are rejected.' );
$html = StructuredPart::render( $view['settings']['parts'][1]['question'], array( 'id' => 10 ), 'attempt[10][quiz_question][71][choice]', 'unique-choice', 71 );
structured_check( str_contains( $html, 'name="attempt[10][quiz_question][71][choice][]"' ), 'Existing templates submit under the part prefix.' );
structured_check( ! str_contains( $html, '[quiz_question][0]' ), 'No child input escapes into another question.' );
$chart_html = StructuredPart::render( $view['settings']['parts'][2]['question'], array( 'id' => 10 ), 'attempt[10][quiz_question][71][chart]', 'unique-chart', 71 );
structured_check( str_contains( $chart_html, 'data-name-prefix="attempt[10][quiz_question][71][chart]"' ), 'Dynamic widgets use the nested prefix.' );
$matching                                 = $choice;
$matching['question']['settings']['type'] = 'matching';
$matching['question']['questions'][0]['matching_data'] = array( 'label' => '2 + 2' );
$matching['question']['questions'][1]['matching_data'] = array( 'label' => '2 + 3' );
$matching_view     = StructuredPart::view( $matching, 'matches' );
$definition        = $matching_view['definitions'][0]['id'];
$matching_response = StructuredPart::map_response(
	$matching,
	array(
		41 => 41,
		42 => 42,
	),
	'matches',
	false
);
structured_check( $definition !== $matching_view['questions'][1]['id'], 'Matching definitions have independent tokens.' );
structured_check(
	array(
		41 => 41,
		42 => 42,
	) === StructuredPart::map_response( $matching, $matching_response, 'matches', true ),
	'Matching definition keys and answers round trip.'
);
$blank             = $choice;
$blank['prompt']   = 'A secret is {zebra}.';
$blank['question'] = array(
	'settings'  => array( 'type' => 'fill-in-the-blank' ),
	'questions' => array(),
);
$blank_settings    = array( 'parts' => array( $blank ) );
structured_check( ! str_contains( wp_json_encode( Structured::public_parts( $blank_settings, 'parent', 'scope' ) ), 'zebra' ), 'Typed inline blank keys are removed from part statements.' );
$manual             = $choice;
$manual['question'] = array(
	'settings'  => array( 'type' => 'draw' ),
	'questions' => array(),
);
structured_check( Structured::has_manual( array( 'parts' => array( $manual ) ) ), 'Teacher-marked children cannot enter automatic practice pools.' );
$manual_grade = Grader::grade( StructuredPart::snapshot( $manual ), array( 'drawing' => '[[1,2],[3,4]]' ) );
structured_check( ! is_wp_error( $manual_grade ) && $manual_grade['pending'], 'Drawing uses the existing manual grader.' );
$invalid                                 = $choice;
$invalid['question']['settings']['type'] = 'structured';
structured_check( true !== StructuredPart::validate( $invalid ), 'Recursive multi-part children are refused.' );
$legacy          = $settings;
$legacy['parts'] = array(
	$settings['parts'][0],
	array(
		'id'       => 'text',
		'kind'     => 'text',
		'accepted' => array( 'X = 2' ),
		'marks'    => 1,
	),
);
structured_check( true === Structured::validate_settings( $legacy ), 'Existing numerical and exact-text configurations still validate.' );
structured_check(
	1.0 === Structured::grade(
		array(
			'a'    => '2',
			'text' => 'x=2',
		),
		new QuestionSnapshot( array_merge( $question->to_array(), array( 'settings' => $legacy ) ) )
	)['fraction'],
	'Legacy parts retain case and whitespace handling.'
);
$media_data = $question->to_array();
$media_data['settings']['parts'][1]['question']['questions'][0]['image_url'] = 'http://800.local/uploads/live.png';
$media_data['media']['frozen'] = array( 'part:choice:option:41' => array( 'url' => 'http://800.local/uploads/frozen.png' ) );
$media_question                = new QuestionSnapshot( $media_data );
structured_check( 'http://800.local/uploads/frozen.png' === $media_question->get_settings()['parts'][1]['question']['questions'][0]['image_url'], 'Nested media resolves to the frozen copy.' );
structured_check( Grader::present( array( 'part' => array( 'slot' => array( 'selection' ) ) ) ), 'Nested multi-select answers count as present.' );

// Exercise the real authoring writer and immutable version/token boundary without keeping fixtures.
try {
	\OhMyLMS\Utility\Transaction::run(
		static function () use ( $settings ) {
			$admin = get_users(
				array(
					'role'   => 'administrator',
					'number' => 1,
				)
			)[0];
			wp_set_current_user( $admin->ID );
			$saved = \OhMyLMS\QuestionBank\DraftWriter::save(
				array(
					'name'      => 'Structured fixture',
					'settings'  => $settings,
					'questions' => array(),
				)
			);
			structured_check( ! is_wp_error( $saved ), 'Mixed question saves through the actual authoring writer.' );
			$identity = \OhMyLMS\QuestionBank\VersionPublisher::identity( $saved['id'], false );
			$item     = array(
				'version_id' => (int) $identity['current_version_id'],
				'display'    => array(),
			);
			$frozen   = \OhMyLMS\Assessment\AttemptItems::snapshot( $item );
			structured_check( 3 === count( $frozen->get_settings()['parts'] ), 'Version capture retains every private part.' );
			$view     = $frozen->student_view( array(), array(), array(), array(), 'fixture-delivery' );
			$response = array(
				'a'      => '2',
				'choice' => array( $view['settings']['parts'][1]['question']['questions'][0]['id'] ),
				'chart'  => array( 'a' => '3' ),
			);
			$decoded  = \OhMyLMS\Assessment\AttemptItems::untokenize( 'fixture-delivery', $item, $response );
			structured_check( 1.0 === Grader::grade( $frozen, $decoded )['fraction'], 'Real versioned attempt decoding produces the correct nested grade.' );
			structured_check( \OhMyLMS\Assessment\AttemptItems::tokenize( 'fixture-delivery', $item, $decoded ) === $response, 'Stored attempt responses resume with the original part tokens.' );
			$settings['parts'][1]['question']['questions'][0]['is_correct'] = false;
			$settings['parts'][1]['question']['questions'][1]['is_correct'] = true;
			$updated = \OhMyLMS\QuestionBank\DraftWriter::save(
				array(
					'id'       => $saved['id'],
					'settings' => $settings,
				)
			);
			structured_check( ! is_wp_error( $updated ) && 1.0 === Grader::grade( \OhMyLMS\Assessment\AttemptItems::snapshot( $item ), $decoded )['fraction'], 'Editing a part never changes an issued version.' );
			throw new RuntimeException( 'STRUCTURED_FIXTURES_ROLLBACK' );
		}
	);
} catch ( Throwable $failure ) {
	if ( 'STRUCTURED_FIXTURES_ROLLBACK' !== $failure->getMessage() ) {
		throw $failure;
	}
}
echo 'Structured parts: ' . (int) $structured_checks . " checks passed; writer fixtures rolled back.\n";
