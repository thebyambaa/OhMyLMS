<?php
/**
 * Real WordPress revision, membership and permission checks for the skill studio.
 *
 * @package OhMyLMS\Tests
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}
$studio_config        = json_decode( file_get_contents( getenv( 'OHMYLMS_TEST_CREDENTIALS' ) ), true );
$_SERVER['HTTP_HOST'] = '127.0.0.1:8780';
require $studio_config['site'] . '/wp-load.php';
if ( ! defined( 'OHMYLMS_TEST_SITE' ) || ! OHMYLMS_TEST_SITE || 'ohmylms_source_test' !== DB_NAME ) {
	throw new RuntimeException( 'Requires the isolated WordPress test database.' );
}

use OhMyLMS\Curriculum\Comparison;
use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\SkillMappings;
use OhMyLMS\Curriculum\Syllabus;
use OhMyLMS\Skills\Taxonomy;

$studio_checks  = 0;
$studio_items   = array();
$studio_terms   = array();
$studio_student = 0;
$studio_admin   = get_user_by( 'login', $studio_config['username'] )->ID;
$studio_tag     = wp_generate_password( 8, false, false );

/**
 * Assert a meaningful integration outcome.
 *
 * @param bool   $condition Expected outcome.
 * @param string $message Failure explanation.
 */
function studio_check( $condition, $message ) {
	global $studio_checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	++$studio_checks;
}

/**
 * Dispatch through real REST permission callbacks.
 *
 * @param string $method HTTP method.
 * @param string $path Comparison path.
 * @param array  $data JSON fields.
 * @return WP_REST_Response Response.
 */
function studio_request( $method, $path, array $data ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/syllabus-comparison/' . $path );
	$request->set_header( 'Content-Type', 'application/json' );
	$request->set_body( wp_json_encode( $data ) );
	return rest_do_request( $request );
}

try {
	wp_set_current_user( $studio_admin );
	OhMyLMS\Curriculum\Schema::install();
	$source         = Items::create(
		array(
			'name'        => 'Studio source ' . $studio_tag,
			'is_syllabus' => true,
		)
	);
	$source['id']   = (int) $source['id'];
	$studio_items[] = $source['id'];
	$group          = Syllabus::add_group( $source['id'], array( 'name' => 'Algebra' ) );
	$objectives     = array();
	foreach ( array( 'Original', 'Replacement', 'Already connected' ) as $name ) {
		$row            = Syllabus::create_skill( $source['id'], $group, array( 'name' => $name . ' ' . $studio_tag ) );
		$objectives[]   = $row['term_id'];
		$studio_terms[] = $row['term_id'];
	}
	$created = studio_request(
		'POST',
		'shared-skills/save',
		array(
			'name'        => 'Studio shared ' . $studio_tag,
			'subject'     => 'Mathematics',
			'topic'       => 'Algebra',
			'description' => 'Solve a reviewed linear equation.',
		)
	);
	studio_check( 200 === $created->get_status(), 'Shared skill creation works through the existing REST endpoint.' );
	$shared         = $created->get_data();
	$studio_terms[] = $shared['id'];
	$initial        = studio_request(
		'PUT',
		'mapping',
		array(
			'specific_id'       => $objectives[0],
			'shared_id'         => $shared['id'],
			'relation'          => 'related',
			'note'              => 'Original scope',
			'expected_revision' => '',
		)
	);
	studio_check( 200 === $initial->get_status(), 'Initial descriptive connection saves.' );
	$original = SkillMappings::for_skill( $objectives[0] )['maps_to'][0];
	$payload  = array(
		'old_specific_id'   => $objectives[0],
		'specific_id'       => $objectives[1],
		'shared_id'         => $shared['id'],
		'source_id'         => $source['id'],
		'relation'          => 'narrower',
		'note'              => 'Reviewed replacement',
		'expected_revision' => SkillMappings::revision( $original ),
	);
	$result   = studio_request( 'POST', 'mapping/reconnect', $payload );
	studio_check( 200 === $result->get_status(), 'Current reconnection succeeds: ' . wp_json_encode( $result->get_data() ) );
	studio_check( $original['id'] === $result->get_data()['id'], 'Reconnection preserves mapping identity.' );
	studio_check( ! SkillMappings::for_skill( $objectives[0] )['maps_to'], 'Original endpoint is no longer connected.' );
	$replacement = SkillMappings::for_skill( $objectives[1] )['maps_to'][0];
	studio_check( 'narrower' === $replacement['relation'] && 'Reviewed replacement' === $replacement['note'], 'Replacement retains reviewed relationship and scope.' );
	studio_check( 409 === studio_request( 'POST', 'mapping/reconnect', $payload )->get_status(), 'A stale retry cannot change the replacement.' );
	studio_request(
		'PUT',
		'mapping',
		array(
			'specific_id'       => $objectives[2],
			'shared_id'         => $shared['id'],
			'relation'          => 'related',
			'note'              => '',
			'expected_revision' => '',
		)
	);
	$payload['old_specific_id']   = $objectives[1];
	$payload['specific_id']       = $objectives[2];
	$payload['expected_revision'] = SkillMappings::revision( $replacement );
	studio_check( 409 === studio_request( 'POST', 'mapping/reconnect', $payload )->get_status(), 'Duplicate replacement is rejected without overwriting it.' );
	studio_check( $replacement === SkillMappings::for_skill( $objectives[1] )['maps_to'][0], 'Rejected reconnection keeps every original mapping field.' );
	$payload['specific_id'] = $shared['id'];
	studio_check( 400 === studio_request( 'POST', 'mapping/reconnect', $payload )->get_status(), 'An objective outside the selected source is rejected.' );
	$payload['specific_id'] = $objectives[0];
	$missing                = $payload;
	unset( $missing['expected_revision'] );
	studio_check( 400 === studio_request( 'POST', 'mapping/reconnect', $missing )->get_status(), 'Revision is required.' );
	$second         = Comparison::save_shared(
		0,
		array(
			'name'    => 'Joint skill ' . $studio_tag,
			'subject' => 'Mathematics',
		)
	);
	$studio_terms[] = $second['id'];
	$joint          = SkillMappings::save_groups(
		array(
			array(
				'source_id' => $source['id'],
				'pairs'     => array(
					array(
						'specific_id'       => $objectives[0],
						'expected_revision' => '',
					),
					array(
						'specific_id'       => $objectives[1],
						'expected_revision' => '',
					),
				),
			),
		),
		$second['id'],
		'Required together'
	);
	studio_check( ! is_wp_error( $joint ), 'Combined coverage fixture saves.' );
	$payload['old_specific_id']   = $objectives[0];
	$payload['specific_id']       = $objectives[2];
	$payload['shared_id']         = $second['id'];
	$payload['expected_revision'] = SkillMappings::revision( $joint[0] );
	studio_check( 409 === studio_request( 'POST', 'mapping/reconnect', $payload )->get_status(), 'Individual reconnection cannot split a combined group.' );
	studio_check( 2 === count( SkillMappings::group( $joint[0]['group_uuid'] )['items'] ), 'The whole group remains intact.' );
	$studio_student = wp_insert_user(
		array(
			'user_login' => 'studio-student-' . $studio_tag,
			'user_pass'  => wp_generate_password( 40, false ),
			'role'       => 'subscriber',
		)
	);
	wp_set_current_user( $studio_student );
	studio_check( 403 === studio_request( 'POST', 'mapping/reconnect', $payload )->get_status(), 'Learners cannot reconnect administrator mappings.' );
	studio_check( 403 === studio_request( 'POST', 'shared-skills/save', array( 'name' => 'Forbidden' ) )->get_status(), 'Learners cannot create shared skills.' );
	wp_set_current_user( 0 );
	studio_check( 401 === studio_request( 'POST', 'mapping/reconnect', $payload )->get_status(), 'Anonymous reconnection is denied.' );
	echo "Skill studio integration: {$studio_checks} checks passed.\n";
} finally {
	wp_set_current_user( $studio_admin );
	foreach ( $studio_items as $id ) {
		$item = Items::get( $id );
		if ( $item ) {
			Items::delete( $id, 'delete', true );
			if ( $item['course_id'] ) {
				wp_delete_post( $item['course_id'], true ); }
			if ( $item['skill_root_id'] ) {
				wp_delete_term( $item['skill_root_id'], Taxonomy::NAME ); }
		}
	}
	foreach ( $studio_terms as $id ) {
		wp_delete_term( $id, Taxonomy::NAME ); }
	if ( $studio_student && ! is_wp_error( $studio_student ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $studio_student );
	}
}
