<?php
/**
 * Real REST checks for the typed curriculum workspace: branch deletion, icons, skill map,
 * subject profiles and course bundles. Run on the Local site with OHMYLMS_CURRICULUM_WORKSPACE_TEST=1;
 * tagged fixtures are removed in finally and no database reset occurs.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Bundles\Bundles;
use OhMyLMS\Curriculum\Comparison;
use OhMyLMS\Curriculum\Items;
use OhMyLMS\Skills\SubjectProfileModel;
use OhMyLMS\Skills\SubjectProfiles;
use OhMyLMS\Skills\Taxonomy;

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_CURRICULUM_WORKSPACE_TEST' ) ) {
	exit( "Set OHMYLMS_CURRICULUM_WORKSPACE_TEST=1 to run the curriculum workspace REST tests.\n" );
}
define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

/**
 * Dispatch a real REST request.
 *
 * @param string $method HTTP method.
 * @param string $path Route below the namespace.
 * @param array  $data Request fields.
 * @return WP_REST_Response
 */
function ohmylms_workspace_request( $method, $path, array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1' . $path );
	if ( in_array( $method, array( 'GET', 'DELETE' ), true ) ) {
		$request->set_query_params( $data );
	} else {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
}

/**
 * Check an invariant.
 *
 * @param bool   $condition Expected condition.
 * @param string $message Failure description.
 * @throws RuntimeException On failure.
 */
function ohmylms_workspace_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
}

/**
 * Create a tagged curriculum item.
 *
 * @param string $name Name.
 * @param int    $parent_id Parent ID.
 * @return int
 */
function ohmylms_workspace_item( $name, $parent_id = 0 ) {
	global $workspace_items;
	$response = ohmylms_workspace_request(
		'POST',
		'/curriculum/items',
		array(
			'name'      => $name,
			'item_type' => 'topic',
			'parent_id' => $parent_id,
		)
	);
	ohmylms_workspace_check( 201 === $response->get_status(), 'Curriculum fixture creation failed.' );
	$id                = (int) $response->get_data()['item']['id'];
	$workspace_items[] = $id;
	return $id;
}

$workspace_tag     = 'workspace-' . wp_generate_uuid4();
$workspace_items   = array();
$workspace_bundles = array();
$workspace_terms   = array();
$workspace_option  = null;
$workspace_posts   = array();
$workspace_users   = array();
$workspace_admin   = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
ohmylms_workspace_check( ! empty( $workspace_admin ), 'An administrator is required.' );
$workspace_admin = (int) $workspace_admin[0];
try {
	foreach ( array( 'editor', 'subscriber' ) as $workspace_role ) {
		$workspace_user = wp_insert_user(
			array(
				'user_login' => 'cw' . substr( md5( $workspace_tag ), 0, 12 ) . $workspace_role,
				'user_pass'  => wp_generate_password(),
				'role'       => $workspace_role,
			)
		);
		ohmylms_workspace_check( ! is_wp_error( $workspace_user ), 'User fixture creation failed.' );
		$workspace_users[ $workspace_role ] = $workspace_user;
	}
	wp_set_current_user( $workspace_admin );

	// Icons and branch deletion.
	$workspace_tree = ohmylms_workspace_request( 'GET', '/curriculum/tree' )->get_data();
	ohmylms_workspace_check( in_array( 'calculator', array_column( $workspace_tree['icons'], 'id' ), true ), 'Tree does not list the icon catalogue.' );
	$workspace_root  = ohmylms_workspace_item( $workspace_tag . ' root' );
	$workspace_child = ohmylms_workspace_item( $workspace_tag . ' child', $workspace_root );
	$workspace_leaf  = ohmylms_workspace_item( $workspace_tag . ' leaf', $workspace_child );
	$workspace_shown = ohmylms_workspace_request( 'GET', '/curriculum/items/' . $workspace_root )->get_data();
	ohmylms_workspace_check( 64 === strlen( $workspace_shown['delete_revision'] ) && 2 === $workspace_shown['dependents']['descendants'], 'Delete preview is missing.' );
	$workspace_icon = ohmylms_workspace_request(
		'PUT',
		'/curriculum/items/' . $workspace_root,
		array(
			'icon'                => 'calculator',
			'expected_updated_at' => $workspace_shown['item']['updated_at'],
		)
	);
	ohmylms_workspace_check( 200 === $workspace_icon->get_status() && 'calculator' === $workspace_icon->get_data()['item']['icon'], 'Icon was not saved.' );
	ohmylms_workspace_check( 400 === ohmylms_workspace_request( 'PUT', '/curriculum/items/' . $workspace_root, array( 'icon' => 'not-an-icon' ) )->get_status(), 'Unknown icon accepted.' );
	ohmylms_workspace_check( 400 === ohmylms_workspace_request( 'DELETE', '/curriculum/items/' . $workspace_root, array( 'children' => 'orphan' ) )->get_status(), 'Unknown child strategy accepted.' );
	ohmylms_workspace_check( 400 === ohmylms_workspace_request( 'DELETE', '/curriculum/items/' . $workspace_root, array( 'expected_revision' => 'short' ) )->get_status(), 'Malformed revision accepted.' );
	$workspace_children = ohmylms_workspace_request( 'DELETE', '/curriculum/items/' . $workspace_root );
	ohmylms_workspace_check( 409 === $workspace_children->get_status() && 'ohmylms_curriculum_has_children' === $workspace_children->get_data()['code'], 'Branch delete without a strategy was not refused.' );
	$workspace_unconfirmed = ohmylms_workspace_request( 'DELETE', '/curriculum/items/' . $workspace_root, array( 'children' => 'delete' ) );
	ohmylms_workspace_check( 409 === $workspace_unconfirmed->get_status() && 'ohmylms_curriculum_needs_confirmation' === $workspace_unconfirmed->get_data()['code'], 'Unconfirmed branch delete was not refused.' );
	$workspace_revision = ohmylms_workspace_request( 'GET', '/curriculum/items/' . $workspace_root )->get_data()['delete_revision'];
	$workspace_late     = ohmylms_workspace_item( $workspace_tag . ' late', $workspace_child );
	$workspace_stale    = ohmylms_workspace_request(
		'DELETE',
		'/curriculum/items/' . $workspace_root,
		array(
			'children'          => 'delete',
			'confirm'           => true,
			'expected_revision' => $workspace_revision,
		)
	);
	ohmylms_workspace_check( 409 === $workspace_stale->get_status() && 'ohmylms_curriculum_stale' === $workspace_stale->get_data()['code'] && Items::get( $workspace_late ), 'Stale branch delete removed items.' );
	wp_set_current_user( $workspace_users['editor'] );
	ohmylms_workspace_check(
		403 === ohmylms_workspace_request(
			'DELETE',
			'/curriculum/items/' . $workspace_root,
			array(
				'children' => 'delete',
				'confirm'  => true,
			)
		)->get_status() && Items::get( $workspace_root ),
		'Editor deleted a curriculum branch.'
	);
	wp_set_current_user( $workspace_admin );
	$workspace_promote = ohmylms_workspace_request(
		'DELETE',
		'/curriculum/items/' . $workspace_child,
		array(
			'children'          => 'promote',
			'confirm'           => true,
			'expected_revision' => ohmylms_workspace_request( 'GET', '/curriculum/items/' . $workspace_child )->get_data()['delete_revision'],
		)
	);
	ohmylms_workspace_check( 200 === $workspace_promote->get_status() && ! Items::get( $workspace_child ) && (int) Items::get( $workspace_leaf )['parent_id'] === $workspace_root, 'Promote delete failed.' );
	$workspace_branch = ohmylms_workspace_request(
		'DELETE',
		'/curriculum/items/' . $workspace_root,
		array(
			'children'          => 'delete',
			'confirm'           => true,
			'expected_revision' => ohmylms_workspace_request( 'GET', '/curriculum/items/' . $workspace_root )->get_data()['delete_revision'],
		)
	);
	ohmylms_workspace_check( 200 === $workspace_branch->get_status() && ! Items::get( $workspace_root ) && ! Items::get( $workspace_leaf ) && ! Items::get( $workspace_late ), 'Confirmed branch delete failed.' );

	// Skill map.
	$workspace_subject = 'Workspace ' . substr( md5( $workspace_tag ), 0, 8 );
	foreach ( array( 'Count', 'Add' ) as $workspace_index => $workspace_name ) {
		$workspace_term = wp_insert_term( $workspace_tag . $workspace_name, Taxonomy::NAME );
		ohmylms_workspace_check( ! is_wp_error( $workspace_term ), 'Shared skill fixture creation failed.' );
		$workspace_terms[] = (int) $workspace_term['term_id'];
		update_term_meta( (int) $workspace_term['term_id'], Comparison::SUBJECT_META, $workspace_subject );
		update_term_meta( (int) $workspace_term['term_id'], Comparison::DOMAIN_META, 'number' );
		update_term_meta( (int) $workspace_term['term_id'], Comparison::GRADE_FROM_META, 1 + 5 * $workspace_index );
	}
	Taxonomy::set_prerequisites( $workspace_terms[1], array( $workspace_terms[0] ) );
	$workspace_subjects = ohmylms_workspace_request( 'GET', '/syllabus-comparison/skill-map/subjects' );
	ohmylms_workspace_check( 200 === $workspace_subjects->get_status() && in_array( $workspace_subject, array_column( $workspace_subjects->get_data(), 'subject' ), true ), 'Skill map subjects are missing the fixture subject.' );
	$workspace_map = ohmylms_workspace_request(
		'GET',
		'/syllabus-comparison/skill-map',
		array(
			'subject'     => $workspace_subject,
			'syllabus_id' => '',
			'student_id'  => '',
		)
	);
	ohmylms_workspace_check( 200 === $workspace_map->get_status() && 2 === count( $workspace_map->get_data()['skills'] ) && 'long_gap' === $workspace_map->get_data()['issues'][0]['type'], 'Skill map did not describe the subject.' );
	ohmylms_workspace_check( 400 === ohmylms_workspace_request( 'GET', '/syllabus-comparison/skill-map' )->get_status(), 'Skill map accepted a missing subject.' );
	ohmylms_workspace_check(
		400 === ohmylms_workspace_request(
			'GET',
			'/syllabus-comparison/skill-map',
			array(
				'subject'    => $workspace_subject,
				'student_id' => '-1',
			)
		)->get_status(),
		'Skill map accepted an invalid learner.'
	);

	// Subject profiles.
	$workspace_option   = get_option( SubjectProfiles::OPTION, 'missing' );
	$workspace_profiles = ohmylms_workspace_request( 'GET', '/syllabus-comparison/subject-profiles' )->get_data();
	$workspace_profile  = array(
		'label'             => $workspace_subject,
		'language'          => 'en',
		'domains'           => array(
			array(
				'id'    => 'number',
				'label' => 'Number',
			),
		),
		'levels'            => array( 'Stage 1', 'Stage 2' ),
		'ai'                => array( 'context' => 'Test' ),
		'expected_revision' => $workspace_profiles['revision'],
	);
	foreach ( array(
		'missing revision' => array( 'expected_revision' => null ),
		'no levels'        => array( 'levels' => array() ),
		'level type'       => array( 'levels' => array( array( 'x' ) ) ),
		'ai field'         => array( 'ai' => array( 'prompt' => 'x' ) ),
		'domain shape'     => array( 'domains' => array( array( 'label' => 'No id' ) ) ),
		'domain id'        => array(
			'domains' => array(
				array(
					'id'    => '9bad',
					'label' => 'Bad',
				),
			),
		),
	) as $workspace_case => $workspace_change ) {
		$workspace_body = array_filter(
			array_merge( $workspace_profile, $workspace_change ),
			static function ( $value ) {
				return null !== $value;
			}
		);
		ohmylms_workspace_check( 400 === ohmylms_workspace_request( 'POST', '/syllabus-comparison/subject-profiles/save', $workspace_body )->get_status(), 'Profile save accepted ' . $workspace_case . '.' );
	}
	ohmylms_workspace_check( get_option( SubjectProfiles::OPTION, 'missing' ) === $workspace_option, 'Rejected profile saves changed the option.' );
	wp_set_current_user( $workspace_users['editor'] );
	ohmylms_workspace_check( 403 === ohmylms_workspace_request( 'POST', '/syllabus-comparison/subject-profiles/save', $workspace_profile )->get_status(), 'Editor saved a profile.' );
	ohmylms_workspace_check( 403 === ohmylms_workspace_request( 'GET', '/syllabus-comparison/skill-map', array( 'subject' => $workspace_subject ) )->get_status(), 'Editor read the skill map.' );
	wp_set_current_user( $workspace_admin );
	$workspace_saved = ohmylms_workspace_request( 'POST', '/syllabus-comparison/subject-profiles/save', $workspace_profile );
	ohmylms_workspace_check( 200 === $workspace_saved->get_status(), 'Valid profile save failed.' );
	$workspace_key = SubjectProfileModel::key( $workspace_subject );
	foreach ( $workspace_saved->get_data()['profiles'] as $workspace_row ) {
		if ( $workspace_key === $workspace_row['key'] && $workspace_row['curriculum_id'] ) {
			$workspace_items[] = (int) $workspace_row['curriculum_id'];
		}
	}
	ohmylms_workspace_check( 409 === ohmylms_workspace_request( 'POST', '/syllabus-comparison/subject-profiles/save', $workspace_profile )->get_status(), 'Stale profile save was applied.' );
	ohmylms_workspace_check(
		409 === ohmylms_workspace_request(
			'POST',
			'/syllabus-comparison/subject-profiles/default',
			array(
				'key'               => $workspace_key,
				'expected_revision' => $workspace_profiles['revision'],
			)
		)->get_status(),
		'Stale default change was applied.'
	);
	ohmylms_workspace_check( 400 === ohmylms_workspace_request( 'POST', '/syllabus-comparison/subject-profiles/remove', array( 'expected_revision' => $workspace_saved->get_data()['revision'] ) )->get_status(), 'Profile removal accepted a missing key.' );
	$workspace_removed = ohmylms_workspace_request(
		'POST',
		'/syllabus-comparison/subject-profiles/remove',
		array(
			'key'               => $workspace_key,
			'expected_revision' => $workspace_saved->get_data()['revision'],
		)
	);
	ohmylms_workspace_check( 200 === $workspace_removed->get_status() && ! in_array( $workspace_key, array_column( $workspace_removed->get_data()['profiles'], 'key' ), true ), 'Profile removal failed.' );

	// Course bundles.
	$workspace_course = wp_insert_post(
		array(
			'post_title'  => $workspace_tag,
			'post_type'   => OHMYLMS_COURSE_CPT,
			'post_status' => 'draft',
		),
		true
	);
	ohmylms_workspace_check( ! is_wp_error( $workspace_course ), 'Course fixture creation failed.' );
	$workspace_posts[] = $workspace_course;
	ohmylms_workspace_check( 400 === ohmylms_workspace_request( 'POST', '/bundles', array( 'description' => 'No title' ) )->get_status(), 'Bundle without a title was created.' );
	ohmylms_workspace_check( 400 === ohmylms_workspace_request( 'POST', '/bundles', array( 'title' => str_repeat( 'x', 191 ) ) )->get_status(), 'Oversized bundle title accepted.' );
	$workspace_created = ohmylms_workspace_request( 'POST', '/bundles', array( 'title' => $workspace_tag ) );
	ohmylms_workspace_check( 201 === $workspace_created->get_status(), 'Bundle creation failed.' );
	$workspace_bundle    = $workspace_created->get_data()['bundle'];
	$workspace_bundles[] = (int) $workspace_bundle['id'];
	$workspace_path      = '/bundles/' . $workspace_bundle['id'];
	ohmylms_workspace_check( 400 === ohmylms_workspace_request( 'GET', '/bundles/targets', array( 'type' => 'lesson' ) )->get_status(), 'Unknown target type accepted.' );
	ohmylms_workspace_check(
		in_array(
			$workspace_course,
			array_map(
				'intval',
				array_column(
					ohmylms_workspace_request(
						'GET',
						'/bundles/targets',
						array(
							'type'   => 'course',
							'search' => $workspace_tag,
						)
					)->get_data(),
					'id'
				)
			),
			true
		),
		'Course target search failed.'
	);
	foreach ( array(
		'missing items'  => array(),
		'member type'    => array(
			'items' => array(
				array(
					'type' => 'lesson',
					'id'   => 1,
				),
			),
		),
		'member id'      => array(
			'items' => array(
				array(
					'type' => 'course',
					'id'   => 0,
				),
			),
		),
		'missing member' => array(
			'items' => array(
				array(
					'type' => 'course',
					'id'   => PHP_INT_MAX,
				),
			),
		),
	) as $workspace_case => $workspace_body ) {
		ohmylms_workspace_check( 400 === ohmylms_workspace_request( 'PUT', $workspace_path . '/items', $workspace_body )->get_status(), 'Bundle members accepted ' . $workspace_case . '.' );
	}
	$workspace_members = ohmylms_workspace_request(
		'PUT',
		$workspace_path . '/items',
		array(
			'items'               => array(
				array(
					'type' => 'course',
					'id'   => $workspace_course,
				),
			),
			'expected_updated_at' => $workspace_bundle['updated_at'],
		)
	);
	ohmylms_workspace_check( 200 === $workspace_members->get_status() && 1 === count( $workspace_members->get_data()['members'] ), 'Bundle members were not saved.' );
	ohmylms_workspace_check(
		409 === ohmylms_workspace_request(
			'PUT',
			$workspace_path,
			array(
				'title'               => 'Stale',
				'expected_updated_at' => '2000-01-01 00:00:00',
			)
		)->get_status() && 0 === strcmp( $workspace_tag, Bundles::get( $workspace_bundle['id'] )['title'] ),
		'Stale bundle update was applied.'
	);
	ohmylms_workspace_check(
		409 === ohmylms_workspace_request(
			'POST',
			$workspace_path . '/publish',
			array(
				'published'           => true,
				'expected_updated_at' => '2000-01-01 00:00:00',
			)
		)->get_status() && 'draft' === Bundles::get( $workspace_bundle['id'] )['status'],
		'Stale publish was applied.'
	);
	$workspace_current = $workspace_members->get_data()['bundle']['updated_at'];
	$workspace_publish = ohmylms_workspace_request(
		'POST',
		$workspace_path . '/publish',
		array(
			'published'           => true,
			'expected_updated_at' => $workspace_current,
		)
	);
	ohmylms_workspace_check( 200 === $workspace_publish->get_status() && 'published' === $workspace_publish->get_data()['bundle']['status'], 'Publishing failed.' );
	ohmylms_workspace_check(
		409 === ohmylms_workspace_request(
			'PUT',
			$workspace_path . '/items',
			array(
				'items'               => array(),
				'expected_updated_at' => $workspace_publish->get_data()['bundle']['updated_at'],
			)
		)->get_status(),
		'A published bundle was emptied.'
	);
	wp_set_current_user( $workspace_users['editor'] );
	ohmylms_workspace_check( 403 === ohmylms_workspace_request( 'GET', '/bundles' )->get_status() && 403 === ohmylms_workspace_request( 'DELETE', $workspace_path )->get_status(), 'Editor reached bundle administration.' );
	wp_set_current_user( $workspace_admin );
	ohmylms_workspace_check( 409 === ohmylms_workspace_request( 'DELETE', $workspace_path, array( 'expected_updated_at' => '2000-01-01 00:00:00' ) )->get_status() && Bundles::get( $workspace_bundle['id'] ), 'Stale bundle delete was applied.' );
	ohmylms_workspace_check( 200 === ohmylms_workspace_request( 'DELETE', $workspace_path, array( 'expected_updated_at' => $workspace_publish->get_data()['bundle']['updated_at'] ) )->get_status() && ! Bundles::get( $workspace_bundle['id'] ), 'Bundle delete failed.' );

	echo "Curriculum workspace REST integration checks passed.\n";
} finally {
	wp_set_current_user( $workspace_admin );
	foreach ( $workspace_bundles as $workspace_bundle ) {
		Bundles::delete( $workspace_bundle, true );
	}
	foreach ( array_reverse( $workspace_items ) as $workspace_item ) {
		if ( Items::get( $workspace_item ) ) {
			Items::delete( $workspace_item, 'delete', true );
		}
	}
	foreach ( $workspace_posts as $workspace_post ) {
		wp_delete_post( $workspace_post, true );
	}
	foreach ( $workspace_terms as $workspace_term ) {
		wp_delete_term( $workspace_term, Taxonomy::NAME );
	}
	if ( 'missing' === $workspace_option ) {
		delete_option( SubjectProfiles::OPTION );
	} elseif ( null !== $workspace_option ) {
		update_option( SubjectProfiles::OPTION, $workspace_option, false );
	}
	foreach ( $workspace_users as $workspace_user ) {
		wp_delete_user( $workspace_user );
	}
}
