<?php
/**
 * Question rows export / import integration suite (isolated test site only).
 *
 * Creates one question of every registered type, exports them as import rows (JSON and CSV), imports
 * the file, and proves each copy stores the same type, title, settings, options, skills and bank
 * attributes. Sample settings for the activity types come from tests/php/fixtures/question-type-defaults.json,
 * made from the editor's own default settings.
 *
 *   OHMYLMS_TEST_CREDENTIALS=/path/test-credentials.json php tests/php/question-rows-integration.php
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
wp_set_current_user( get_user_by( 'login', $config['username'] )->ID );

use OhMyLMS\Assessment\ExtendedQuestions;
use OhMyLMS\Extensions\Registry;
use OhMyLMS\QuestionBank\BankAttributes;
use OhMyLMS\QuestionBank\BulkImport;
use OhMyLMS\QuestionBank\RowExporter;
use OhMyLMS\QuestionBank\SkillMap;
use OhMyLMS\QuestionBank\VersionPublisher;
use OhMyLMS\Skills\Taxonomy;

$checks = 0;
/**
 * Assert one contract.
 *
 * @param bool   $ok Condition.
 * @param string $message Failure description.
 * @throws RuntimeException On failure.
 */
function rows_check( $ok, $message ) {
	global $checks;
	if ( ! $ok ) {
		throw new RuntimeException( $message );
	}
	++$checks;
}
/**
 * Call a REST route inside the process.
 *
 * @param string     $method HTTP method.
 * @param string     $path Route below ohmylms/v1.
 * @param array|null $body JSON body.
 * @param array      $query Query parameters.
 * @return array Status and data.
 */
function rows_call( $method, $path, $body = null, $query = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/' . $path );
	$request->set_query_params( $query );
	if ( null !== $body ) {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );
	}
	$response = rest_do_request( $request );
	return array( $response->get_status(), $response->get_data() );
}
/**
 * Stored content of a question, reduced to what a copy must reproduce.
 *
 * @param int $id Question ID.
 * @return array
 */
function rows_normal( $id ) {
	$content  = VersionPublisher::content( $id );
	$settings = $content['settings'];
	foreach ( array( 'answer', 'tolerance' ) as $key ) {
		if ( isset( $settings[ $key ] ) ) {
			$settings[ $key ] = (float) $settings[ $key ];
		}
	}
	if ( isset( $settings['score'] ) && ! is_array( $settings['score'] ) ) {
		$settings['score'] = array(
			'enabled' => true,
			'value'   => (float) $settings['score'],
		);
	}
	if ( isset( $settings['score']['value'] ) ) {
		$settings['score']['value'] = (float) $settings['score']['value'];
	}
	ksort( $settings );
	$options = array();
	foreach ( $content['options'] as $option ) {
		$options[] = array( $option['answer'], (int) $option['is_correct'], $option['matching_data'], $option['image_url'] );
	}
	$bank = BankAttributes::get( $id );
	return array(
		'type'       => $content['type'],
		'title'      => $content['title'],
		'settings'   => $settings,
		'options'    => $options,
		'skills'     => SkillMap::current( $id ),
		'difficulty' => $bank['difficulty'] ?? '',
		'family'     => $bank['family_id'] ?? '',
	);
}

$taxonomy = Taxonomy::NAME;
$term     = wp_insert_term( 'Rows skill ' . wp_generate_password( 6, false ), $taxonomy );
$skill    = $term['term_id'];
update_term_meta( $skill, '_ohmylms_skill_code', 'ROWS.' . $skill );
// A second skill that shares a code with a third must be written by UUID, which the importer accepts.
$twin_a = wp_insert_term( 'Twin A ' . wp_generate_password( 6, false ), $taxonomy )['term_id'];
$twin_b = wp_insert_term( 'Twin B ' . wp_generate_password( 6, false ), $taxonomy )['term_id'];
foreach ( array( $twin_a, $twin_b ) as $twin ) {
	update_term_meta( $twin, '_ohmylms_skill_code', 'ROWS.TWIN' );
}
update_term_meta( $twin_a, '_ohmylms_skill_uuid', wp_generate_uuid4() );

$defaults = json_decode( file_get_contents( __DIR__ . '/fixtures/question-type-defaults.json' ), true );
$option   = static function ( $answer, $correct = false ) {
	return array(
		'answer'     => $answer,
		'is_correct' => $correct,
	);
};
$samples  = array(
	'single-choice'     => array(
		'name'     => 'Single: pick the prime',
		'q'        => array( $option( '4' ), $option( '7', true ), $option( '9' ) ),
		'settings' => array(
			'partial_credit' => false,
			'explanation'    => 'Seven has no divisors.',
		),
	),
	'multiple-choice'   => array(
		'name'     => 'Multiple: pick the evens | with a pipe',
		'q'        => array( $option( '2', true ), $option( '3' ), $option( '4 | four', true ) ),
		'settings' => array( 'partial_credit' => true ),
	),
	'true-false'        => array(
		'name' => 'True or false: 2+2=4',
		'q'    => array( $option( 'True', true ), $option( 'False' ) ),
	),
	'statement'         => array(
		'name' => 'Statement: water boils at 100C',
		'q'    => array( $option( 'Agree', true ), $option( 'Disagree' ) ),
	),
	'fill-in-the-blank' => array( 'name' => 'The capital of France is {Paris}.' ),
	'reorder'           => array(
		'name' => 'Reorder: smallest first',
		'q'    => array( $option( '1', true ), $option( '2', true ), $option( '3', true ) ),
	),
	'matching'          => array(
		'name' => 'Match numbers',
		'q'    => array(
			array(
				'answer'        => 'One',
				'matching_data' => array( 'label' => '1' ),
			),
			array(
				'answer'        => 'Two',
				'matching_data' => array( 'label' => '2' ),
			),
		),
	),
	'numerical'         => array(
		'name'     => 'Numerical: 2+2',
		'settings' => array(
			'answer'         => 4,
			'tolerance'      => 0.5,
			'tolerance_type' => 'absolute',
			'unit'           => 'cm',
		),
	),
	'structured'        => array(
		'name'        => 'Structured: rectangle',
		'description' => 'A rectangle has sides 3 cm and 5 cm.',
		'settings'    => array(
			'score' => array(
				'enabled' => true,
				'value'   => 6,
			),
			'parts' => array(
				array(
					'id'     => 'a',
					'label'  => '(a)',
					'prompt' => 'Find the area.',
					'kind'   => 'numerical',
					'marks'  => 2,
					'answer' => 15,
					'unit'   => 'cm²',
				),
				array(
					'id'       => 'b',
					'label'    => '(b)',
					'prompt'   => 'Solve 2x = 4.',
					'kind'     => 'text',
					'marks'    => 1,
					'accepted' => array( 'x=2' ),
				),
				array(
					'id'     => 'c',
					'label'  => '(c)',
					'prompt' => 'Explain.',
					'kind'   => 'written',
					'marks'  => 3,
					'rubric' => 'one mark per step',
				),
			),
		),
	),
	'categorize'        => array(
		'settings' => array(
			'buckets' => array(
				array(
					'id'    => 'a',
					'label' => 'Odd',
				),
				array(
					'id'    => 'b',
					'label' => 'Even',
				),
			),
			'items'   => array(
				array(
					'id'   => 'one',
					'text' => '1',
				),
				array(
					'id'   => 'two',
					'text' => '2',
				),
			),
			'key'     => array(
				'one' => 'a',
				'two' => 'b',
			),
		),
	),
	'build-expression'  => array(
		'settings' => array(
			'tile_mode'   => 'words',
			'tiles'       => array( 'We', 'learn', 'together' ),
			'correct'     => array( 'We', 'learn', 'together' ),
			'equivalence' => false,
		),
	),
	'labeling'          => array( 'settings' => array( 'image_url' => 'https://example.test/image.png' ) ),
	'hotspot'           => array( 'settings' => array( 'image_url' => 'https://example.test/image.png' ) ),
	'interactive-video' => array( 'settings' => array( 'video_url' => 'https://example.test/video.mp4' ) ),
);
$skip     = array( 'custom-type', 'example-number' );
$types    = array_values( array_diff( array_keys( Registry::all( 'question' ) ), $skip ) );
rows_check( count( $types ) >= 50, 'Every built-in question type is registered.' );

try {
	foreach ( array( 'json', 'csv' ) as $format ) {
		$label = 'Rows ' . $format . ' ' . time();
		$ids   = array();
		foreach ( $types as $type ) {
			$sample   = $samples[ $type ] ?? array();
			$unscored = in_array( $type, ExtendedQuestions::UNSCORED_TYPES, true );
			$settings = array_merge(
				$defaults[ $type ] ?? array(),
				$sample['settings'] ?? array(),
				array( 'type' => $type ),
				isset( $sample['settings']['score'] ) ? array() : array(
					'score' => array(
						'enabled' => ! $unscored,
						'value'   => $unscored ? 0 : 2,
					),
				)
			);
			$payload  = array(
				'name'     => $sample['name'] ?? ( 'Type ' . $type ),
				'settings' => $settings,
				'skills'   => 'structured' === $type ? array(
					'a' => array( 'primary' => $skill ),
					'b' => array(
						'primary'    => $skill,
						'supporting' => array( $twin_a ),
					),
					'c' => array( 'primary' => $skill ),
				) : array( 'p1' => array( 'primary' => $skill ) ),
				'bank'     => array(
					'difficulty' => 'challenge',
					'family_id'  => 'rows-' . $type,
				),
			);
			if ( isset( $sample['description'] ) ) {
				$payload['description'] = $sample['description'];
			}
			if ( isset( $sample['q'] ) ) {
				$payload['options'] = $sample['q'];
			}
			list( $status, $data ) = rows_call( 'POST', 'questions', $payload );
			rows_check( $status < 300, "Create $type: " . wp_json_encode( $data ) );
			$ids[ $type ] = $data['id'];
		}
		list( $status, $file ) = rows_call(
			'POST',
			'questions/export',
			array(
				'ids'    => array_values( array_map( 'intval', $ids ) ),
				'format' => $format,
				'label'  => $label,
			)
		);
		rows_check( $status < 300 && count( $ids ) === $file['count'], "Export $format wrote every question." );
		rows_check( ! $file['warnings'], 'Questions with skills export without warnings.' );
		if ( 'csv' === $format ) {
			rows_check( 0 === strpos( $file['content'], "\xEF\xBB\xBF" ) && str_ends_with( $file['filename'], '.csv' ), 'CSV carries a byte-order mark for spreadsheets.' );
		}
		// A skill whose code is shared is written by its UUID so the importer can resolve it.
		rows_check( false === strpos( $file['content'], 'ROWS.TWIN' ), 'A shared skill code is never written.' );
		$rows = BulkImport::parse( $file['content'], $format );
		rows_check( ! is_wp_error( $rows ), 'The exported file parses.' );
		$dry = BulkImport::run( $rows, array( 'dry_run' => true ) );
		rows_check( ! is_wp_error( $dry ), 'The exported file passes a dry run.' );
		foreach ( $dry['rows'] as $row ) {
			rows_check( ! $row['errors'], 'Row ' . $row['row'] . ' has no errors: ' . implode( ' / ', $row['errors'] ) );
		}
		$real = BulkImport::run(
			$rows,
			array(
				'dry_run'      => false,
				'skip_invalid' => false,
			)
		);
		rows_check( ! is_wp_error( $real ), 'Import succeeds.' );
		$position = 0;
		foreach ( $ids as $type => $original ) {
			$copy = (int) ( array_values( $real['rows'] )[ $position++ ]['id'] ?? 0 );
			rows_check( $copy > 0 && $copy !== $original, "$format: $type was imported as a new question." );
			$before = rows_normal( $original );
			$after  = rows_normal( $copy );
			rows_check( $before === $after, "$format: $type stored identically. " . wp_json_encode( array_diff_assoc( array_map( 'wp_json_encode', $before ), array_map( 'wp_json_encode', $after ) ) ) );
		}
	}
	// A question without a skill still exports, with a warning that names it.
	list( , $bare ) = rows_call(
		'POST',
		'questions',
		array(
			'name'     => 'No skill',
			'settings' => array(
				'type'   => 'numerical',
				'answer' => 1,
			),
		)
	);
	list( , $file ) = rows_call(
		'POST',
		'questions/export',
		array(
			'ids'    => array( (int) $bare['id'] ),
			'format' => 'json',
		)
	);
	rows_check( 1 === count( $file['warnings'] ), 'A question without a skill is reported.' );
	// The quiz route exports a quiz in order and refuses someone who cannot edit it.
	list( , $quiz ) = rows_call(
		'POST',
		'quiz',
		array(
			'name'   => 'Rows quiz',
			'status' => 'draft',
		)
	);
	foreach ( array_slice( array_values( $ids ), 0, 3 ) as $position => $question_id ) {
		BulkImportTestHelper::place( $quiz['id'], $question_id, $position );
	}
	list( $status, $file ) = rows_call(
		'POST',
		'questions/export',
		array(
			'quiz_id' => (int) $quiz['id'],
			'format'  => 'json',
		)
	);
	rows_check( $status < 300 && 3 === $file['count'] && 'Rows quiz' === json_decode( $file['content'], true )[0]['exam'], 'A quiz exports its questions under its title.' );
	wp_set_current_user( 0 );
	list( $status ) = rows_call(
		'POST',
		'questions/export',
		array(
			'ids'    => array( (int) reset( $ids ) ),
			'format' => 'json',
		)
	);
	rows_check( $status >= 400, 'Export needs a signed-in author.' );
} finally {
	wp_set_current_user( get_user_by( 'login', $config['username'] )->ID );
}
echo "Question rows export/import: {$checks} checks passed.\n";

/** Place an existing question in a quiz without going through the editor. */
final class BulkImportTestHelper {
	/**
	 * Link a question to a quiz.
	 *
	 * @param int $quiz_id Quiz ID.
	 * @param int $question_id Question ID.
	 * @param int $position Zero-based position.
	 */
	public static function place( $quiz_id, $question_id, $position ) {
		\OhMyLMS\QuestionBank\DraftWriter::link( $quiz_id, $question_id, $position + 1 );
	}
}
