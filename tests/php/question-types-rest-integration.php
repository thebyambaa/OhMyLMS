<?php
/**
 * Question types through `ohmylms/v1/questions`: one question of every built-in type is created, read,
 * edited (with a new version) and refused when stale; the shared-settings and per-type answer rules,
 * media, bank fields and skills are checked; the learner preview of a draft carries no answer key.
 *
 * Runs against the Local site (`800.local`) and removes everything it creates (tagged questions,
 * a temporary attachment, a temporary skill and their question-bank rows).
 *
 * Usage (set OHMYLMS_QUESTIONS_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/question-types-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, Generic.Formatting.MultipleStatementAlignment, WordPress.DB.DirectDatabaseQuery, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- CLI test script writing to STDOUT/STDERR.

use OhMyLMS\Assessment\Schema;
use OhMyLMS\Extensions\Registry;
use OhMyLMS\Skills\Taxonomy;

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_QUESTIONS_TEST' ) ) {
	exit( "Set OHMYLMS_QUESTIONS_TEST=1 to run the question type REST tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

global $wpdb;

$checks       = 0;
$question_ids = array();
$post_ids     = array();
$term_ids     = array();
$tag          = 'qtypes' . strtolower( wp_generate_password( 6, false, false ) );

/**
 * Assert a condition.
 *
 * @param mixed  $value   Value that must be truthy.
 * @param string $message Failure message.
 * @throws RuntimeException When the condition is falsy.
 */
function ok( $value, $message ) {
	global $checks;
	if ( ! $value ) {
		throw new RuntimeException( 'FAILED: ' . $message );
	}
	++$checks;
}

/**
 * Dispatch a REST request.
 *
 * @param string $method HTTP method.
 * @param string $path   Path below /ohmylms/v1.
 * @param array  $data   JSON body.
 * @return WP_REST_Response
 */
function call_api( $method, $path, $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1' . $path );
	if ( 'GET' === $method ) {
		$request->set_query_params( $data );
	} else {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
}

/**
 * Assert an error response.
 *
 * @param WP_REST_Response $response Response.
 * @param int              $status   Expected status.
 * @param string           $code     Expected code.
 * @param string           $label    Description.
 * @param string|null      $field    Expected `data.field`, if any.
 */
function ok_error( $response, $status, $code, $label, $field = null ) {
	$data = $response->get_data();
	$got  = isset( $data['code'] ) ? $data['code'] : '';
	ok( $status === $response->get_status() && $code === $got, "$label: expected $status $code, got " . $response->get_status() . ' ' . $got . ' ' . wp_json_encode( $data ) );
	if ( null !== $field ) {
		ok( ( $data['data']['field'] ?? null ) === $field, "$label: field $field, got " . wp_json_encode( $data['data'] ?? null ) );
	}
}

/**
 * Create a question and remember it for cleanup.
 *
 * @param array  $data  Payload.
 * @param string $label Description.
 * @return array Editor view.
 */
function make_question( array $data, $label ) {
	global $question_ids;
	$response = call_api( 'POST', '/questions', $data );
	ok( 201 === $response->get_status(), "$label created: " . wp_json_encode( $response->get_data() ) );
	$question_ids[] = (int) $response->get_data()['id'];
	return $response->get_data();
}

/**
 * Number of questions this run created.
 *
 * @return int
 */
function tagged_count() {
	global $wpdb, $tag;
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_title LIKE %s", OHMYLMS_QUESTION_CPT, '%' . $wpdb->esc_like( $tag ) . '%' ) );
}

try {
	ok( Schema::ready(), 'question bank schema is installed' );
	$admins = get_users(
		array(
			'role'    => 'administrator',
			'number'  => 1,
			'orderby' => 'ID',
			'fields'  => 'ID',
		)
	);
	ok( ! empty( $admins ), 'an administrator exists' );
	wp_set_current_user( (int) $admins[0] );

	$fixtures = json_decode( file_get_contents( __DIR__ . '/fixtures/question-types.json' ), true );
	ok( is_array( $fixtures ) && count( $fixtures ) > 40, 'type fixtures load' );

	// Every built-in type (and only built-in types) has a fixture.
	$builtin = array_keys(
		array_filter(
			Registry::all( 'question' ),
			static function ( $definition, $id ) {
				return Registry::is_core( 'question', $id );
			},
			ARRAY_FILTER_USE_BOTH
		)
	);
	sort( $builtin );
	$fixture_types = array_keys( $fixtures );
	sort( $fixture_types );
	ok( $builtin === $fixture_types, 'a fixture for every built-in type: missing ' . wp_json_encode( array_values( array_diff( $builtin, $fixture_types ) ) ) . ', extra ' . wp_json_encode( array_values( array_diff( $fixture_types, $builtin ) ) ) );

	// Create, read, edit and stale-save one question of every type.
	foreach ( $fixtures as $type => $fixture ) {
		$body = array(
			'name'     => "$tag " . $fixture['name'],
			'settings' => $fixture['settings'],
			'bank'     => array( 'difficulty' => 'easy' ),
		);
		if ( isset( $fixture['options'] ) ) {
			$body['options'] = $fixture['options'];
		}
		$created = make_question( $body, $type );
		ok( $type === $created['type'] && 1 === $created['version'], "$type: type and first version" );
		$read = call_api( 'GET', '/questions/' . $created['id'] );
		ok( 200 === $read->get_status() && $type === $read->get_data()['settings']['type'], "$type: read back" );
		ok( '' === $read->get_data()['thumbnail_url'] && '' === $read->get_data()['video_url'], "$type: media URLs in the editor view" );
		foreach ( $fixture['settings'] as $key => $value ) {
			if ( 'type' !== $key ) {
				ok( $read->get_data()['settings'][ $key ] == $value, "$type: setting $key stored" ); // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- JSON numbers may come back as int or float.
			}
		}
		$settings         = $read->get_data()['settings'];
		$settings['hint'] = 'Think about ' . $type;
		$edited           = call_api(
			'PATCH',
			'/questions/' . $created['id'],
			array(
				'base_modified' => $read->get_data()['modified'],
				'settings'      => $settings,
			)
		);
		ok( 200 === $edited->get_status() && 'Think about ' . $type === $edited->get_data()['settings']['hint'], "$type: edited " . wp_json_encode( $edited->get_data() ) );
		ok( 2 === $edited->get_data()['version'], "$type: the edit is a new version" );
		$wpdb->update( $wpdb->posts, array( 'post_modified_gmt' => gmdate( 'Y-m-d H:i:s', time() + 120 ) ), array( 'ID' => $created['id'] ) );
		clean_post_cache( $created['id'] );
		ok_error(
			call_api(
				'PATCH',
				'/questions/' . $created['id'],
				array(
					'base_modified' => $edited->get_data()['modified'],
					'name'          => "$tag stale",
				)
			),
			409,
			'ohmylms_question_conflict',
			"$type: stale save"
		);
	}

	// Shared settings rules; nothing is written.
	$before = tagged_count();
	$single = $fixtures['single-choice'];
	$base   = array(
		'name'    => "$tag invalid",
		'options' => $single['options'],
	);
	foreach ( array(
		array( array( 'required' => 'yes' ), 'settings.required' ),
		array( array( 'randomize' => array( 1 ) ), 'settings.randomize' ),
		array( array( 'partial_credit' => 2 ), 'settings.partial_credit' ),
		array( array( 'hint' => array( 'x' ) ), 'settings.hint' ),
		array( array( 'explanation' => str_repeat( 'x', 65536 ) ), 'settings.explanation' ),
		array( array( 'score' => array( 'value' => -1 ) ), 'settings.score' ),
		array( array( 'score' => array( 'value' => 10001 ) ), 'settings.score' ),
		array( array( 'score' => 'one' ), 'settings.score' ),
		array( array( 'blank_mode' => 'guess' ), 'settings.blank_mode' ),
		array( array( 'match_layout' => 'list' ), 'settings.match_layout' ),
	) as $case ) {
		ok_error( call_api( 'POST', '/questions', array_merge( $base, array( 'settings' => array_merge( array( 'type' => 'single-choice' ), $case[0] ) ) ) ), 422, 'ohmylms_question_invalid_settings', 'shared setting ' . $case[1], $case[1] );
	}
	ok( tagged_count() === $before, 'rejected shared settings wrote nothing' );
	$legacy = make_question(
		array_merge(
			$base,
			array(
				'settings' => array(
					'type'      => 'single-choice',
					'required'  => '1',
					'randomize' => 0,
					'score'     => array(
						'enabled' => '1',
						'value'   => '2.5',
					),
				),
			)
		),
		'legacy flag values'
	);
	ok( '1' === $legacy['settings']['required'] && 2.5 === (float) $legacy['settings']['score']['value'], 'legacy flag and number strings are accepted as stored' );

	// Statement: expected typed answers.
	ok_error(
		call_api(
			'POST',
			'/questions',
			array(
				'name'     => "$tag s",
				'settings' => array( 'type' => 'statement' ),
			)
		),
		422,
		'ohmylms_question_invalid_options',
		'statement without answers',
		'options'
	);
	ok_error(
		call_api(
			'POST',
			'/questions',
			array(
				'name'     => "$tag s",
				'settings' => array( 'type' => 'statement' ),
				'options'  => array(
					array(
						'answer'     => ' <b></b> ',
						'is_correct' => true,
					),
				),
			)
		),
		422,
		'ohmylms_question_invalid_options',
		'statement answer without text',
		'options[0].answer'
	);
	ok_error(
		call_api(
			'POST',
			'/questions',
			array(
				'name'     => "$tag s",
				'settings' => array( 'type' => 'statement' ),
				'options'  => array(
					array(
						'answer'     => '4',
						'is_correct' => false,
					),
				),
			)
		),
		422,
		'ohmylms_question_invalid_options',
		'statement answer not correct',
		'options[0].answer'
	);

	// Fill in the blank: {braces} in the name, or accepted answers.
	ok_error(
		call_api(
			'POST',
			'/questions',
			array(
				'name'     => "$tag no blanks",
				'settings' => array( 'type' => 'fill-in-the-blank' ),
			)
		),
		422,
		'ohmylms_question_invalid_options',
		'fill-in-the-blank without answers',
		'options'
	);
	ok_error(
		call_api(
			'POST',
			'/questions',
			array(
				'name'     => "$tag empty {  }",
				'settings' => array( 'type' => 'fill-in-the-blank' ),
			)
		),
		422,
		'ohmylms_question_invalid_options',
		'fill-in-the-blank with an empty blank',
		'options'
	);
	$accepted = make_question(
		array(
			'name'     => "$tag capital of France",
			'settings' => array( 'type' => 'fill-in-the-blank' ),
			'options'  => array(
				array(
					'answer'     => 'Paris',
					'is_correct' => true,
				),
			),
		),
		'fill-in-the-blank with accepted answers'
	);
	ok( 'Paris' === $accepted['options'][0]['answer'], 'accepted answer stored' );
	$braces = make_question(
		array(
			'name'     => "$tag {2} + {2} = 4",
			'settings' => array( 'type' => 'fill-in-the-blank' ),
		),
		'fill-in-the-blank with braces'
	);
	ok_error(
		call_api(
			'PATCH',
			'/questions/' . $braces['id'],
			array(
				'base_modified' => $braces['modified'],
				'name'          => "$tag 2 + 2 = 4",
			)
		),
		422,
		'ohmylms_question_invalid_options',
		'renaming away every blank',
		'options'
	);
	$renamed = call_api(
		'PATCH',
		'/questions/' . $braces['id'],
		array(
			'base_modified' => $braces['modified'],
			'name'          => "$tag {3} + {1} = 4",
		)
	);
	ok( 200 === $renamed->get_status(), 'renaming with blanks is accepted' );

	// Type rules of settings types (DraftWriter and the type's validate_settings).
	ok_error(
		call_api(
			'POST',
			'/questions',
			array(
				'name'     => "$tag poll",
				'settings' => array_merge(
					$fixtures['poll']['settings'],
					array(
						'score' => array(
							'enabled' => true,
							'value'   => 1,
						),
					)
				),
			)
		),
		400,
		'ohmylms_invalid_question_settings',
		'scored poll'
	);
	ok_error(
		call_api(
			'POST',
			'/questions',
			array(
				'name'     => "$tag slide",
				'settings' => array(
					'type'  => 'slide',
					'score' => array(
						'enabled' => true,
						'value'   => 1,
					),
				),
			)
		),
		400,
		'ohmylms_invalid_question_settings',
		'scored slide'
	);
	ok_error(
		call_api(
			'POST',
			'/questions',
			array(
				'name'     => "$tag hotspot",
				'settings' => array_merge( $fixtures['hotspot']['settings'], array( 'image_url' => '' ) ),
			)
		),
		400,
		'ohmylms_invalid_question_settings',
		'hotspot without an image'
	);
	ok_error(
		call_api(
			'POST',
			'/questions',
			array(
				'name'     => "$tag categorize",
				'settings' => array_merge( $fixtures['categorize']['settings'], array( 'key' => array( 'i1' => 'b9' ) ) ),
			)
		),
		400,
		'ohmylms_invalid_question_settings',
		'categorize item outside the groups'
	);
	ok_error(
		call_api(
			'POST',
			'/questions',
			array(
				'name'     => "$tag hot",
				'settings' => array_merge( $fixtures['hot-text']['settings'], array( 'correct' => array( 't9' ) ) ),
			)
		),
		400,
		'ohmylms_invalid_question_settings',
		'hot text key outside the tokens'
	);
	ok_error(
		call_api(
			'POST',
			'/questions',
			array(
				'name'     => "$tag num",
				'settings' => array( 'type' => 'numerical' ),
			)
		),
		400,
		'ohmylms_invalid_question_settings',
		'numerical without an answer'
	);

	// Media, option images, bank fields and skills.
	$attachment = wp_insert_attachment(
		array(
			'post_title'     => "$tag image",
			'post_mime_type' => 'image/png',
			'post_status'    => 'inherit',
			'guid'           => 'http://800.local/wp-includes/images/w-logo-blue.png',
		),
		false
	);
	ok( $attachment > 0, 'temporary attachment' );
	$post_ids[] = $attachment;
	update_post_meta( $attachment, '_wp_attached_file', "$tag.png" );
	ok_error(
		call_api(
			'POST',
			'/questions',
			array_merge(
				$base,
				array(
					'settings'     => array( 'type' => 'single-choice' ),
					'thumbnail_id' => (int) $admins[0] + 999999,
				)
			)
		),
		422,
		'ohmylms_question_invalid_media',
		'question image that is not an attachment',
		'thumbnail_id'
	);
	$term = wp_insert_term( "$tag skill", Taxonomy::NAME );
	ok( ! is_wp_error( $term ), 'temporary skill' );
	$term_ids[] = (int) $term['term_id'];
	$rich       = make_question(
		array(
			'name'         => "$tag rich",
			'settings'     => array( 'type' => 'single-choice' ),
			'options'      => array(
				array(
					'answer'     => 'A',
					'is_correct' => true,
					'image_id'   => $attachment,
				),
				array(
					'answer'     => 'B',
					'is_correct' => false,
				),
			),
			'thumbnail_id' => $attachment,
			'bank'         => array(
				'difficulty' => 'challenge',
				'source'     => 'Workbook p. 12',
				'secure'     => true,
			),
			'skills'       => array(
				'p1' => array(
					'primary'    => (int) $term['term_id'],
					'supporting' => array(),
				),
			),
		),
		'question with media, bank fields and a skill'
	);
	ok( $attachment === $rich['thumbnail_id'] && '' !== $rich['thumbnail_url'], 'question image with its URL' );
	ok( $attachment === $rich['options'][0]['image_id'] && '' !== $rich['options'][0]['image_url'], 'option image with its URL' );
	ok( 'Workbook p. 12' === $rich['bank']['source'] && true === $rich['bank']['secure'] && 'challenge' === $rich['bank']['difficulty'], 'bank fields stored' );
	ok( (int) $term['term_id'] === $rich['skills']['p1']['primary'], 'skill map stored' );
	$cleared = call_api(
		'PATCH',
		'/questions/' . $rich['id'],
		array(
			'base_modified' => $rich['modified'],
			'thumbnail_id'  => 0,
			'bank'          => array( 'secure' => false ),
			'skills'        => new stdClass(),
		)
	);
	ok( 200 === $cleared->get_status() && 0 === $cleared->get_data()['thumbnail_id'] && '' === $cleared->get_data()['thumbnail_url'], 'question image removed' );
	ok( false === $cleared->get_data()['bank']['secure'] && 'Workbook p. 12' === $cleared->get_data()['bank']['source'], 'only the sent bank keys change' );
	ok( array() === (array) $cleared->get_data()['skills'], 'skill map cleared' );

	// The learner preview of an unsaved hot-text question has no answer key.
	$preview = call_api(
		'POST',
		'/question-preview',
		array(
			'post_id'  => $rich['id'],
			'question' => array(
				'id'          => $rich['id'],
				'name'        => "$tag preview",
				'description' => '',
				'settings'    => $fixtures['hot-text']['settings'],
				'questions'   => array(),
			),
			'seed'     => 1,
		)
	);
	ok( 200 === $preview->get_status() && is_string( $preview->get_data()['url'] ?? null ), 'preview created: ' . wp_json_encode( $preview->get_data() ) );
	parse_str( (string) wp_parse_url( $preview->get_data()['url'], PHP_URL_QUERY ), $query );
	$stored = get_transient( 'ohmylms_question_preview_' . $query['ohmylms_question_preview'] );
	ok( is_array( $stored ) && ! isset( $stored['question']['settings']['correct'] ) && isset( $stored['question']['settings']['tokens'] ), 'preview keeps the tokens and drops the answer key' );
	delete_transient( 'ohmylms_question_preview_' . $query['ohmylms_question_preview'] );

	echo "OK $checks checks\n";
	$exit_code = 0;
} catch ( Throwable $error ) {
	fwrite( STDERR, $error->getMessage() . "\n" . $error->getTraceAsString() . "\n" );
	echo "Passed $checks checks before the failure.\n";
	$exit_code = 1;
} finally {
	$question_ids = array_unique( array_merge( array_map( 'intval', $question_ids ), array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title LIKE %s", OHMYLMS_QUESTION_CPT, '%' . $wpdb->esc_like( $tag ) . '%' ) ) ) ) );
	foreach ( $question_ids as $leftover ) {
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE question_id = %d', $wpdb->prefix . 'ohmylms_question_answers', $leftover ) ) as $answer_id ) {
			$wpdb->delete( $wpdb->prefix . 'ohmylms_question_answermeta', array( 'answer_id' => (int) $answer_id ), array( '%d' ) );
		}
		$wpdb->delete( $wpdb->prefix . 'ohmylms_question_answers', array( 'question_id' => $leftover ), array( '%d' ) );
		wp_delete_post( $leftover, true );
		if ( Schema::ready() ) {
			$wpdb->query( $wpdb->prepare( 'DELETE s FROM %i s JOIN %i v ON v.id = s.version_id WHERE v.question_id = %d', Schema::table( 'qb_version_skills' ), Schema::table( 'qb_question_versions' ), $leftover ) );
			$wpdb->delete( Schema::table( 'qb_question_versions' ), array( 'question_id' => $leftover ), array( '%d' ) );
			$wpdb->delete( Schema::table( 'qb_questions' ), array( 'question_id' => $leftover ), array( '%d' ) );
			$wpdb->delete( Schema::table( 'question_skills' ), array( 'question_id' => $leftover ), array( '%d' ) );
		}
	}
	foreach ( $post_ids as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	foreach ( $term_ids as $term_id ) {
		wp_delete_term( $term_id, Taxonomy::NAME );
	}
}

exit( isset( $exit_code ) ? $exit_code : 0 );
