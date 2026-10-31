<?php
/**
 * Subject profiles against local WordPress: defaults, REST saves, subject-aware placement and MCP tools.
 *
 * Run with OHMYLMS_STRUCTURE_INTEGRATION=1. The stored profiles are restored afterwards.
 *
 * @package OhMyLMS
 */

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_STRUCTURE_INTEGRATION' ) ) {
	exit( "Set OHMYLMS_STRUCTURE_INTEGRATION=1 for local subject profile checks.\n" );
}
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Curriculum\Comparison;
use OhMyLMS\Skills\SubjectProfiles;

/**
 * Assert an integration result.
 *
 * @param bool   $condition Expected result.
 * @param string $message   Failure reason.
 * @throws RuntimeException On failure.
 */
function profile_check( $condition, $message ) {
	global $profile_checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI diagnostic.
	}
	++$profile_checks;
}

/**
 * Call a subject profile route.
 *
 * @param string $method HTTP method.
 * @param string $route  Route below /syllabus-comparison.
 * @param array  $data   Body or query.
 * @return WP_REST_Response
 */
function profile_api( $method, $route, array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/syllabus-comparison/' . $route );
	if ( 'GET' === $method ) {
		$request->set_query_params( $data );
	} else {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
}

$profile_checks   = 0;
$profile_original = get_option( SubjectProfiles::OPTION, null );
$profile_admin    = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
	)
)[0]->ID;
try {
	delete_option( SubjectProfiles::OPTION );
	wp_set_current_user( $profile_admin );
	$profile_payload = profile_api( 'GET', 'subject-profiles' )->get_data();
	profile_check( array( 'generic', 'mathematics' ) === array_column( $profile_payload['profiles'], 'key' ), 'Two built-in profiles: ' . wp_json_encode( array_column( $profile_payload['profiles'], 'key' ) ) );
	$profile_maths_in_use = isset( SubjectProfiles::subjects_in_use()['Mathematics'] );
	profile_check( ( $profile_maths_in_use ? 'mathematics' : 'generic' ) === $profile_payload['default'], 'The default follows the existing library: ' . $profile_payload['default'] );

	// Mathematics reads exactly as before profiles.
	profile_check( 12 === count( Comparison::domains( 'Mathematics' ) ) && isset( Comparison::domains( 'Mathematics' )['calculus'] ), 'Mathematics keeps its twelve domains' );
	profile_check( 'Grade 7' === Comparison::grade_label( 7, 'Mathematics' ) && 'Beyond grade 12' === Comparison::grade_label( 13, 'Mathematics' ) && 13 === Comparison::max_level( 'Mathematics' ), 'Mathematics keeps grades 1–12 and 13' );
	profile_check( ! is_wp_error( Comparison::placement( 'algebra', 7, 13, 'Mathematics' ) ) && is_wp_error( Comparison::placement( 'algebra', 7, 14, 'Mathematics' ) ), 'Mathematics bands are checked against its scale' );
	$profile_options = profile_api( 'GET', 'skill-placement', array( 'subject' => 'Mathematics' ) )->get_data();
	profile_check( 'mathematics' === $profile_options['profile'] && 13 === count( $profile_options['grades'] ) && $profile_options['grades'] === $profile_options['levels'], 'skill-placement keeps grades and adds levels' );

	// A language profile with CEFR levels.
	$profile_english = profile_api(
		'POST',
		'subject-profiles/save',
		array(
			'label'             => 'English language',
			'language'          => 'en',
			'domains'           => array(
				array(
					'id'    => 'reading',
					'label' => 'Reading',
				),
				array(
					'id'    => 'grammar',
					'label' => 'Grammar',
				),
			),
			'levels'            => array( 'A1', 'A2', 'B1', 'B2', 'C1', 'C2' ),
			'ai'                => array( 'role' => 'English language curriculum analyst' ),
			'expected_revision' => $profile_payload['revision'],
		)
	);
	profile_check( 200 === $profile_english->get_status(), 'A profile saves: ' . wp_json_encode( $profile_english->get_data() ) );
	$profile_before  = $profile_payload['revision'];
	$profile_payload = $profile_english->get_data();
	profile_check( in_array( 'english-language', array_column( $profile_payload['profiles'], 'key' ), true ), 'The key comes from the label' );
	profile_check( array(
		'reading' => 'Reading',
		'grammar' => 'Grammar',
	) === Comparison::domains( 'English language' ), 'Skills of that subject use its domains' );
	profile_check( 'B2' === Comparison::grade_label( 4, 'English language' ) && 6 === Comparison::max_level( 'English language' ), 'and its levels' );
	profile_check( ! is_wp_error( Comparison::placement( 'reading', 1, 6, 'English language' ) ) && is_wp_error( Comparison::placement( 'algebra', 1, 2, 'English language' ) ) && is_wp_error( Comparison::placement( 'reading', 1, 7, 'English language' ) ), 'Placement is checked per subject' );
	$profile_english_options = profile_api( 'GET', 'skill-placement', array( 'subject' => 'English language' ) )->get_data();
	profile_check( 'english-language' === $profile_english_options['profile'] && 'C2' === end( $profile_english_options['levels'] )['label'], 'skill-placement answers per subject' );

	// Guarded saves. The route takes a 16-character revision (ComparisonController); the one read before the last save is stale.
	$profile_stale = profile_api(
		'POST',
		'subject-profiles/save',
		array(
			'label'             => 'Chemistry',
			'levels'            => array( 'Grade 9' ),
			'expected_revision' => $profile_before,
		)
	);
	profile_check( 409 === $profile_stale->get_status() && 'ohmylms_subject_profile_conflict' === $profile_stale->get_data()['code'], 'A stale revision is refused: ' . wp_json_encode( $profile_stale->get_data() ) );
	$profile_malformed = profile_api(
		'POST',
		'subject-profiles/save',
		array(
			'label'             => 'Chemistry',
			'levels'            => array( 'Grade 9' ),
			'expected_revision' => 'stale',
		)
	);
	profile_check( 400 === $profile_malformed->get_status() && 'rest_invalid_param' === $profile_malformed->get_data()['code'], 'A malformed revision is refused' );
	$profile_invalid = profile_api(
		'POST',
		'subject-profiles/save',
		array(
			'label'             => 'Chemistry',
			'levels'            => array(),
			'expected_revision' => $profile_payload['revision'],
		)
	);
	profile_check( 400 === $profile_invalid->get_status(), 'An invalid profile is refused' );

	// Subjects without a profile use the default; the default can change.
	profile_check( Comparison::domains( 'Unprofiled subject' ) === Comparison::domains(), 'Unprofiled subjects use the default profile' );
	$profile_payload = profile_api(
		'POST',
		'subject-profiles/default',
		array(
			'key'               => 'generic',
			'expected_revision' => $profile_payload['revision'],
		)
	)->get_data();
	profile_check( 'generic' === $profile_payload['default'] && array() === Comparison::domains( 'Unprofiled subject' ) && 'Level 3' === Comparison::grade_label( 3, 'Unprofiled subject' ), 'The generic default has no domains and plain levels' );
	profile_check( 12 === count( Comparison::domains( 'Mathematics' ) ), 'A subject with its own profile is unaffected by the default' );

	// Customising a built-in profile stores a copy; removing it restores the built-in.
	$profile_custom = profile_api(
		'POST',
		'subject-profiles/save',
		array(
			'key'               => 'mathematics',
			'label'             => 'Mathematics',
			'domains'           => array(
				array(
					'id'    => 'number',
					'label' => 'Number sense',
				),
			),
			'levels'            => array( 'Grade 1', 'Grade 2' ),
			'expected_revision' => $profile_payload['revision'],
		)
	)->get_data();
	profile_check(
		array( 'number' => 'Number sense' ) === Comparison::domains( 'Mathematics' ),
		'A built-in profile can be customised'
	);
	$profile_payload = profile_api(
		'POST',
		'subject-profiles/remove',
		array(
			'key'               => 'mathematics',
			'expected_revision' => $profile_custom['revision'],
		)
	)->get_data();
	profile_check( 12 === count( Comparison::domains( 'Mathematics' ) ), 'Removing the copy restores the built-in profile' );
	profile_check(
		404 === profile_api(
			'POST',
			'subject-profiles/remove',
			array(
				'key'               => 'mathematics',
				'expected_revision' => $profile_payload['revision'],
			)
		)->get_status(),
		'A built-in profile without changes cannot be removed'
	);

	// MCP tools exist and dispatch through the same routes.
	$profile_tools = \OhMyLMS\MCP\CurriculumAiTools::definitions();
	profile_check( isset( $profile_tools['list_subject_profiles'], $profile_tools['save_subject_profile'], $profile_tools['remove_subject_profile'], $profile_tools['set_default_subject_profile'] ), 'Subject profile MCP tools are defined' );

	wp_set_current_user( 0 );
	profile_check( 401 === profile_api( 'GET', 'subject-profiles' )->get_status(), 'Anonymous requests are denied' );
	echo 'PASS: ' . (int) $profile_checks . " subject profile integration checks.\n";
} finally {
	if ( null === $profile_original ) {
		delete_option( SubjectProfiles::OPTION );
	} else {
		update_option( SubjectProfiles::OPTION, $profile_original, false );
	}
}
