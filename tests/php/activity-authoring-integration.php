<?php
/**
 * Draft activity creation, retry safety and per-placement skill links.
 *
 * @package OhMyLMS
 */

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_STRUCTURE_INTEGRATION' ) ) {
	exit( "Set OHMYLMS_STRUCTURE_INTEGRATION=1 for local activity checks.\n" );
}
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Learning\Catalog;
use OhMyLMS\Learning\CourseProgram;
use OhMyLMS\Skills\Taxonomy;

/**
 * Assert an integration result.
 *
 * @param bool   $condition Expected result.
 * @param string $message Failure reason.
 * @throws RuntimeException On failure.
 */
function activity_check( $condition, $message ) {
	global $activity_checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI diagnostic.
	}
	++$activity_checks;
}

/**
 * Call one course structure endpoint.
 *
 * @param string $method HTTP method.
 * @param string $suffix Optional resources suffix.
 * @param array  $data Request body.
 * @param array  $query Query parameters.
 * @return WP_REST_Response
 */
function activity_api( $method, $suffix = '', array $data = array(), array $query = array() ) {
	global $activity_course;
	$request = new WP_REST_Request( $method, '/ohmylms/v1/courses/' . $activity_course . '/structure' . $suffix );
	$request->set_header( 'Content-Type', 'application/json' );
	$request->set_query_params( $query );
	$request->set_body( wp_json_encode( $data ) );
	return rest_do_request( $request );
}

$activity_checks = 0;
$activity_posts  = array();
$activity_terms  = array();
$activity_admin  = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
	)
)[0]->ID;
try {
	wp_set_current_user( $activity_admin );
	$activity_course  = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_COURSE_CPT,
			'post_title'  => 'Activity authoring fixture',
			'post_status' => 'draft',
			'post_author' => $activity_admin,
		)
	);
	$activity_posts[] = $activity_course;
	$activity_unit    = Catalog::add_chapter( $activity_course, 'Activity unit' )['id'];
	foreach ( array( 'Teaching skill', 'Other skill' ) as $activity_label ) {
		$activity_term    = wp_insert_term( $activity_label . ' ' . wp_generate_uuid4(), Taxonomy::NAME );
		$activity_terms[] = (int) $activity_term['term_id'];
	}
	$activity_snapshot            = activity_api( 'GET' )->get_data();
	$activity_group               = wp_generate_uuid4();
	$activity_program             = $activity_snapshot['program'];
	$activity_program['groups']   = array(
		array(
			'id'         => $activity_group,
			'chapter_id' => $activity_unit,
			'title'      => 'Activity lesson',
		),
	);
	$activity_program['outcomes'] = array(
		array(
			'term_id'    => $activity_terms[0],
			'target'     => 'proficient',
			'required'   => false,
			'chapter_id' => $activity_unit,
			'group_id'   => $activity_group,
		),
	);
	$activity_saved               = activity_api(
		'PUT',
		'',
		array(
			'revision' => $activity_snapshot['revision'],
			'units'    => array_values(
				array_filter(
					$activity_snapshot['units'],
					static function ( $unit ) {
						return $unit['id'] > 0;
					}
				)
			),
			'program'  => $activity_program,
		)
	);
	activity_check( 200 === $activity_saved->get_status(), 'Lesson and linked skill fixture saved.' );
	$activity_snapshot     = $activity_saved->get_data();
	$activity_skill_search = activity_api(
		'GET',
		'/resources',
		array(),
		array(
			'type'   => 'skill',
			'search' => 'Teaching skill',
		)
	);
	activity_check( 200 === $activity_skill_search->get_status() && in_array( $activity_terms[0], array_column( $activity_skill_search->get_data()['items'], 'id' ), true ), 'Authors can find existing skills for new lessons.' );
	$activity_request = array(
		'kind'       => 'reading',
		'title'      => 'Draft reading fixture',
		'content'    => '<p>Explain the skill.</p>',
		'request_id' => wp_generate_uuid4(),
		'revision'   => $activity_snapshot['revision'],
	);
	$activity_created = activity_api( 'POST', '/resources', $activity_request );
	activity_check( 200 === $activity_created->get_status(), 'An editable reading draft is created through the native writer.' );
	$activity_reading = $activity_created->get_data();
	$activity_posts[] = $activity_reading['id'];
	activity_check( 'draft' === get_post_status( $activity_reading['id'] ) && 'text' === get_post_meta( $activity_reading['id'], '_type', true ), 'Reading remains a text draft.' );
	activity_check( '<p>Explain the skill.</p>' === get_post_field( 'post_content', $activity_reading['id'] ), 'Authored content is retained as HTML.' );
	activity_check( ! empty( $activity_reading['edit_url'] ), 'The native content editor link is returned.' );
	activity_check( array() === CourseProgram::editor( $activity_course )['draft']['items'], 'Creating a resource does not save its outline placement.' );
	$activity_retry = activity_api( 'POST', '/resources', $activity_request );
	activity_check( $activity_reading['id'] === $activity_retry->get_data()['id'], 'A retry reuses the same resource instead of duplicating it.' );
	activity_check( 409 === activity_api( 'POST', '/resources', array_replace( $activity_request, array( 'title' => 'Different request' ) ) )->get_status(), 'A reused request UUID with different content is rejected.' );
	foreach ( array(
		array( 'kind' => 'unknown' ),
		array( 'title' => '' ),
		array( 'title' => array() ),
		array( 'content' => array() ),
		array( 'request_id' => 'invalid' ),
		array(
			'kind' => 'video',
			'url'  => 'javascript:alert(1)',
		),
	) as $activity_invalid ) {
		activity_check( 400 === activity_api( 'POST', '/resources', array_replace( $activity_request, $activity_invalid, array( 'request_id' => $activity_invalid['request_id'] ?? wp_generate_uuid4() ) ) )->get_status(), 'Invalid creation fields are rejected.' );
	}
	activity_check(
		409 === activity_api(
			'POST',
			'/resources',
			array_replace(
				$activity_request,
				array(
					'revision'   => 'stale',
					'request_id' => wp_generate_uuid4(),
				)
			)
		)->get_status(),
		'Stale course revisions cannot create resource drafts.'
	);
	foreach ( array( 'video', 'quiz' ) as $activity_kind ) {
		$activity_response = activity_api(
			'POST',
			'/resources',
			array_replace(
				$activity_request,
				array(
					'request_id' => wp_generate_uuid4(),
					'kind'       => $activity_kind,
					'title'      => 'Draft ' . $activity_kind . ' fixture',
					'url'        => 'https://example.com/lesson.mp4',
				)
			)
		);
		activity_check( 200 === $activity_response->get_status(), 'A ' . $activity_kind . ' draft is created: ' . wp_json_encode( $activity_response->get_data() ) );
		$activity_resource = $activity_response->get_data();
		$activity_posts[]  = $activity_resource['id'];
		activity_check( 'draft' === get_post_status( $activity_resource['id'] ), 'Creation never publishes the resource.' );
		if ( 'video' === $activity_kind ) {
			activity_check( 'video' === get_post_meta( $activity_resource['id'], '_type', true ) && 'https://example.com/lesson.mp4' === get_post_meta( $activity_resource['id'], '_external_url', true ), 'Video settings use the native lesson contract.' );
		}
	}
	foreach ( array( 'unit-test', 'course-challenge' ) as $activity_style ) {
		$activity_response = activity_api(
			'POST',
			'/resources',
			array_replace(
				$activity_request,
				array(
					'request_id' => wp_generate_uuid4(),
					'kind'       => 'quiz',
					'style'      => $activity_style,
					'title'      => 'Draft ' . $activity_style . ' fixture',
				)
			)
		);
		activity_check( 200 === $activity_response->get_status() && $activity_style === $activity_response->get_data()['style'], 'A slot creates a quiz with the ' . $activity_style . ' style: ' . wp_json_encode( $activity_response->get_data() ) );
		$activity_posts[] = $activity_response->get_data()['id'];
		activity_check( OhMyLMS\Assessment\AssessmentSettings::get( $activity_response->get_data()['id'] )['style'] === $activity_style, 'The style is stored in the assessment settings.' );
	}
	foreach ( array(
		array(
			'kind'  => 'reading',
			'style' => 'unit-test',
		),
		array(
			'kind'  => 'quiz',
			'style' => 'exam',
		),
	) as $activity_invalid_style ) {
		activity_check( 400 === activity_api( 'POST', '/resources', array_replace( $activity_request, $activity_invalid_style, array( 'request_id' => wp_generate_uuid4() ) ) )->get_status(), 'Styles apply only to quizzes and must be known.' );
	}
	$activity_snapshot                     = activity_api( 'GET' )->get_data();
	$activity_item                         = array(
		'id'         => wp_generate_uuid4(),
		'type'       => 'lesson',
		'content_id' => $activity_reading['id'],
		'chapter_id' => $activity_unit,
		'group_id'   => $activity_group,
		'skill_ids'  => array( $activity_terms[0] ),
		'required'   => true,
	);
	$activity_snapshot['program']['items'] = array(
		$activity_item,
		array(
			'id'         => wp_generate_uuid4(),
			'type'       => 'practice',
			'content_id' => $activity_terms[0],
			'chapter_id' => $activity_unit,
			'group_id'   => $activity_group,
		),
	);
	$activity_save_body                    = array(
		'revision' => $activity_snapshot['revision'],
		'units'    => $activity_snapshot['units'],
		'program'  => $activity_snapshot['program'],
	);
	$activity_placed                       = activity_api( 'PUT', '', $activity_save_body );
	activity_check( 200 === $activity_placed->get_status(), 'Explicit save places reading and skill practice in the lesson.' );
	$activity_placed = $activity_placed->get_data();
	activity_check( array( $activity_terms[0] ) === $activity_placed['program']['items'][0]['skill_ids'] && 'draft' === $activity_placed['program']['items'][0]['status'], 'Placement skill links and resource publication status round-trip.' );
	activity_check( array() === wp_get_object_terms( $activity_reading['id'], Taxonomy::NAME, array( 'fields' => 'ids' ) ), 'Course-specific links do not overwrite the shared resource taxonomy.' );
	$activity_preview = activity_api( 'GET', '/preview' )->get_data();
	activity_check(
		(bool) array_filter(
			$activity_preview['readiness'],
			static function ( $message ) {
				return str_contains( $message, 'Publish the learning resource' );
			}
		),
		'Existing publication checks still reject draft resources.'
	);
	$activity_save_body['revision']                         = $activity_placed['revision'];
	$activity_save_body['program']['items'][0]['skill_ids'] = array( $activity_terms[1] );
	activity_check( 400 === activity_api( 'PUT', '', $activity_save_body )->get_status(), 'Unknown course skill scope is rejected.' );
	$activity_fail = static function ( $meta_id, $post_id, $meta_key ) {
		if ( '_ohmylms_outline_draft_request' === $meta_key ) {
			throw new RuntimeException( 'Injected failure after resource insertion.' );
		}
	};
	add_action( 'added_post_meta', $activity_fail, 10, 3 );
	$activity_failed = activity_api(
		'POST',
		'/resources',
		array_replace(
			$activity_request,
			array(
				'title'      => 'Activity rollback fixture',
				'revision'   => $activity_placed['revision'],
				'request_id' => wp_generate_uuid4(),
			)
		)
	);
	remove_action( 'added_post_meta', $activity_fail, 10 );
	activity_check( 500 === $activity_failed->get_status(), 'A post-insertion failure aborts creation.' );
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Verify the exact temporary resource was rolled back.
	activity_check( '0' === $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_title = %s", 'Activity rollback fixture' ) ), 'No partial resource draft is committed on failure.' );
	wp_set_current_user( 0 );
	activity_check( 401 === activity_api( 'POST', '/resources', $activity_request )->get_status(), 'Anonymous draft creation is denied.' );
	echo 'PASS: ' . (int) $activity_checks . " activity authoring checks.\n";
} finally {
	wp_set_current_user( $activity_admin );
	global $wpdb;
	foreach ( Catalog::chapters( $activity_course ?? 0 ) as $activity_unit_row ) {
		wp_delete_post( $activity_unit_row['id'], true );
	}
	$wpdb->delete( $wpdb->prefix . 'ohmylms_chapter_relationship', array( 'course_id' => $activity_course ?? 0 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture unit relationships only.
	foreach ( array_reverse( $activity_posts ) as $activity_id ) {
		wp_delete_post( $activity_id, true );
	}
	foreach ( $activity_terms as $activity_id ) {
		wp_delete_term( $activity_id, Taxonomy::NAME );
	}
}
