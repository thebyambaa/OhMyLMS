<?php
/**
 * Authored syllabus API: groups, ordering, CSV review/apply, mirroring and mappings on 800.local.
 * Set OHMYLMS_SYLLABUS_EDITOR_TEST=1. Only tagged fixtures are removed in finally.
 *
 * @package OhMyLMS
 */

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_SYLLABUS_EDITOR_TEST' ) ) {
	exit( "Set OHMYLMS_SYLLABUS_EDITOR_TEST=1 to run the Local syllabus tests.\n" );
}
define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

use OhMyLMS\Curriculum\Comparison;
use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\Syllabus;
use OhMyLMS\Curriculum\SyllabusCourse;
use OhMyLMS\Learning\Catalog;
use OhMyLMS\Learning\CourseProgram;
use OhMyLMS\Skills\Taxonomy;

$fixture_tag     = 'syleditor' . strtolower( wp_generate_password( 8, false, false ) );
$fixture_users   = array();
$fixture_items   = array();
$fixture_courses = array();
$fixture_terms   = array();
$checks          = 0;

/**
 * Assert without rendering HTML.
 *
 * @param bool   $condition Required condition.
 * @param string $message Failure explanation.
 * @throws RuntimeException When an assertion fails.
 */
function syllabus_check( $condition, $message ) {
	global $checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI assertion text.
	}
	++$checks;
}

/**
 * Dispatch REST under the selected fixture user.
 *
 * @param string $method HTTP method.
 * @param string $syllabus_path Namespace-relative path.
 * @param array  $data JSON or query values.
 * @return WP_REST_Response
 */
function syllabus_request( $method, $syllabus_path, $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/' . $syllabus_path );
	if ( 'GET' === $method ) {
		$request->set_query_params( $data );
	} else {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
}

/**
 * Run one editor command.
 *
 * @param int    $syllabus Syllabus ID.
 * @param string $operation Operation.
 * @param array  $fields Command fields.
 * @return WP_REST_Response
 */
function syllabus_command( $syllabus, $operation, $fields = array() ) {
	return syllabus_request( 'POST', 'authoring/syllabuses/' . $syllabus . '/commands', array( 'operation' => $operation ) + $fields );
}

try {
	foreach ( array( 'administrator', 'author', 'subscriber' ) as $fixture_role ) {
		$fixture_user = wp_insert_user(
			array(
				'user_login' => $fixture_tag . $fixture_role,
				'user_pass'  => wp_generate_password( 32 ),
				'role'       => $fixture_role,
			)
		);
		syllabus_check( ! is_wp_error( $fixture_user ), 'Fixture user created.' );
		$fixture_users[ $fixture_role ] = $fixture_user;
	}
	wp_set_current_user( $fixture_users['administrator'] );
	foreach ( array( 'First', 'Second' ) as $label ) {
		$item = Items::create(
			array(
				'name'        => $fixture_tag . $label,
				'item_type'   => 'subject',
				'is_syllabus' => true,
			)
		);
		syllabus_check( ! is_wp_error( $item ), 'Create syllabus fixture.' );
		$fixture_items[] = $item['id'];
	}
	$syllabus_id   = $fixture_items[0];
	$other_id      = $fixture_items[1];
	$syllabus_path = 'authoring/syllabuses/' . $syllabus_id;
	$before        = SyllabusCourse::course_id( $syllabus_id );
	$read          = syllabus_request( 'GET', $syllabus_path );
	syllabus_check( 200 === $read->get_status(), 'Read syllabus.' );
	syllabus_check( SyllabusCourse::course_id( $syllabus_id ) === $before, 'GET does not create a course.' );
	foreach ( array( 0, $fixture_users['author'], $fixture_users['subscriber'] ) as $fixture_user ) {
		wp_set_current_user( $fixture_user );
		syllabus_check( 400 < syllabus_request( 'GET', $syllabus_path )->get_status(), 'Unauthorized syllabus read denied.' );
		syllabus_check(
			400 < syllabus_command(
				$syllabus_id,
				'add_group',
				array(
					'item_id' => $syllabus_id,
					'name'    => 'denied',
				)
			)->get_status(),
			'Unauthorized write denied.'
		);
	}
	wp_set_current_user( $fixture_users['administrator'] );
	foreach ( array(
		array(
			'operation' => 'add_group',
			'item_id'   => $syllabus_id,
			'name'      => array( 'bad' ),
		),
		array(
			'operation'  => 'add_group',
			'item_id'    => $syllabus_id,
			'name'       => 'bad',
			'unexpected' => true,
		),
		array(
			'operation' => 'move_group',
			'group_id'  => 1,
			'item_id'   => $syllabus_id,
			'position'  => -1,
		),
		array(
			'operation' => 'import',
			'rows'      => array( array( 'skill' => array( 'bad' ) ) ),
			'dry_run'   => true,
		),
		array(
			'operation' => 'import',
			'rows'      => array(),
			'dry_run'   => true,
		),
	) as $invalid ) {
		syllabus_check( 400 === syllabus_request( 'POST', $syllabus_path . '/commands', $invalid )->get_status(), 'Invalid command rejected before writes.' );
	}
	syllabus_check( 0 === Syllabus::outline( $syllabus_id )['totals']['groups'], 'Rejected inputs created no groups.' );
	syllabus_check( 200 === syllabus_command( $syllabus_id, 'sync_course' )->get_status(), 'Explicitly create the syllabus course.' );
	$created = syllabus_command(
		$syllabus_id,
		'add_group',
		array(
			'item_id' => $syllabus_id,
			'name'    => $fixture_tag . ' Algebra',
			'code'    => 'A',
		)
	);
	syllabus_check( 200 === $created->get_status(), 'Create skill group: ' . wp_json_encode( $created->get_data() ) );
	$first_group       = $created->get_data()['contents'][0]['groups'][0]['id'];
	$created           = syllabus_command(
		$syllabus_id,
		'add_group',
		array(
			'item_id' => $syllabus_id,
			'name'    => $fixture_tag . ' Geometry',
			'code'    => 'G',
		)
	);
	$second_group      = $created->get_data()['contents'][0]['groups'][1]['id'];
	$course_id         = SyllabusCourse::course_id( $syllabus_id );
	$fixture_courses[] = $course_id;
	syllabus_check( 0 < $course_id && 'draft' === get_post_status( $course_id ), 'Mirrored course is a draft.' );
	$created = syllabus_command(
		$syllabus_id,
		'create_skill',
		array(
			'group_id'    => $first_group,
			'name'        => $fixture_tag . ' Count',
			'code'        => 'A.1',
			'description' => 'Notes',
		)
	);
	syllabus_check( 200 === $created->get_status(), 'Create syllabus skill.' );
	$skill_id        = $created->get_data()['contents'][0]['groups'][0]['skills'][0]['term_id'];
	$fixture_terms[] = $skill_id;
	$fixture_terms[] = Syllabus::outline( $syllabus_id )['root_skill'];
	$created         = syllabus_command(
		$syllabus_id,
		'create_skill',
		array(
			'group_id' => $first_group,
			'name'     => $fixture_tag . ' Add',
			'code'     => 'A.2',
		)
	);
	$second_skill    = $created->get_data()['contents'][0]['groups'][0]['skills'][1]['term_id'];
	$fixture_terms[] = $second_skill;
	syllabus_check(
		200 === syllabus_command(
			$syllabus_id,
			'move_skill',
			array(
				'group_id'    => $first_group,
				'term_id'     => $second_skill,
				'destination' => $first_group,
				'position'    => 0,
			)
		)->get_status(),
		'Reorder skill.'
	);
	$reopen = syllabus_request( 'GET', $syllabus_path )->get_data();
	syllabus_check( $second_skill === $reopen['contents'][0]['groups'][0]['skills'][0]['term_id'], 'Skill order persists after reopen.' );
	syllabus_check(
		200 === syllabus_command(
			$syllabus_id,
			'move_skill',
			array(
				'group_id'    => $first_group,
				'term_id'     => $skill_id,
				'destination' => $second_group,
				'position'    => 0,
			)
		)->get_status(),
		'Move skill between groups.'
	);
	$outside = Syllabus::add_group( $other_id, array( 'name' => $fixture_tag . ' Outside' ) );
	syllabus_check(
		200 === syllabus_command(
			$other_id,
			'attach_skill',
			array(
				'group_id' => $outside,
				'term_id'  => $second_skill,
			)
		)->get_status(),
		'Assign an existing library skill to another syllabus.'
	);
	syllabus_check(
		200 === syllabus_command(
			$syllabus_id,
			'edit_skill',
			array(
				'term_id'     => $skill_id,
				'name'        => $fixture_tag . ' Count renamed',
				'code'        => 'A.1',
				'description' => 'Updated notes',
			)
		)->get_status(),
		'Edit skill details.'
	);
	syllabus_check( 'Updated notes' === get_term( $skill_id, Taxonomy::NAME )->description, 'Skill notes persist in the library.' );
	$rejected = syllabus_command(
		$syllabus_id,
		'move_skill',
		array(
			'group_id'    => $second_group,
			'term_id'     => $skill_id,
			'destination' => $outside,
			'position'    => 0,
		)
	);
	syllabus_check( 400 <= $rejected->get_status(), 'Cross-syllabus placement rejected.' );
	syllabus_check(
		200 === syllabus_command(
			$syllabus_id,
			'move_group',
			array(
				'group_id' => $second_group,
				'item_id'  => $syllabus_id,
				'position' => 0,
			)
		)->get_status(),
		'Reorder group.'
	);
	syllabus_check( syllabus_request( 'GET', $syllabus_path )->get_data()['contents'][0]['groups'][0]['id'] === $second_group, 'Group order persists.' );
	$rows   = array(
		array(
			'line'       => 2,
			'content'    => 'Imported topic',
			'group'      => 'Imported group',
			'skill'      => $fixture_tag . ' Imported skill',
			'skill_code' => 'I.1',
		),
	);
	$before = Syllabus::outline( $syllabus_id );
	$review = syllabus_command(
		$syllabus_id,
		'import',
		array(
			'rows'    => $rows,
			'dry_run' => true,
		)
	)->get_data();
	syllabus_check( ! empty( $review['report']['valid'] ) && ! $review['report']['applied'], 'CSV review returns a valid plan without applying.' );
	syllabus_check( Syllabus::outline( $syllabus_id ) === $before, 'CSV preview changes no syllabus data.' );
	syllabus_check(
		409 === syllabus_command(
			$syllabus_id,
			'import',
			array(
				'rows'            => $rows,
				'dry_run'         => false,
				'review_revision' => str_repeat( '0', 64 ),
			)
		)->get_status(),
		'Stale import review is rejected without writes.'
	);
	syllabus_check( Syllabus::outline( $syllabus_id ) === $before, 'Rejected review leaves the outline unchanged.' );
	syllabus_check(
		200 === syllabus_command(
			$syllabus_id,
			'edit_group',
			array(
				'group_id' => $first_group,
				'name'     => $fixture_tag . ' Renamed algebra',
				'code'     => 'A',
			)
		)->get_status(),
		'Another saved edit can change the reviewed syllabus.'
	);
	$changed_outline = Syllabus::outline( $syllabus_id );
	syllabus_check(
		409 === syllabus_command(
			$syllabus_id,
			'import',
			array(
				'rows'            => $rows,
				'dry_run'         => false,
				'review_revision' => $review['report']['review_revision'],
			)
		)->get_status(),
		'A real syllabus edit invalidates the prior review.'
	);
	syllabus_check( Syllabus::outline( $syllabus_id ) === $changed_outline, 'Stale reviewed import leaves the new edit intact.' );
	$review  = syllabus_command(
		$syllabus_id,
		'import',
		array(
			'rows'    => $rows,
			'dry_run' => true,
		)
	)->get_data();
	$applied = syllabus_command(
		$syllabus_id,
		'import',
		array(
			'rows'            => $rows,
			'dry_run'         => false,
			'review_revision' => $review['report']['review_revision'],
		)
	);
	syllabus_check( 200 === $applied->get_status() && $applied->get_data()['report']['applied'], 'Reviewed CSV applies.' );
	foreach ( Syllabus::outline( $syllabus_id )['contents'] as $content ) {
		foreach ( $content['groups'] as $group ) {
			foreach ( $group['skills'] as $skill ) {
				$fixture_terms[] = $skill['term_id']; }
		}
	}
	$sync = syllabus_command( $syllabus_id, 'sync_course' );
	syllabus_check( 200 === $sync->get_status() && $course_id === $sync->get_data()['course']['id'], 'Course synchronization is repeatable.' );
	syllabus_check( 3 === Catalog::summary( $course_id )['skills'] && ! get_post_meta( $course_id, CourseProgram::CURRENT, true ), 'Mirrored outcomes saved in draft only.' );
	$shared_term     = wp_insert_term( $fixture_tag . ' Shared', Taxonomy::NAME );
	$shared_id       = $shared_term['term_id'];
	$fixture_terms[] = $shared_id;
	update_term_meta( $shared_id, Comparison::SUBJECT_META, 'Mathematics' );
	$mapping = array(
		'specific_id'       => $skill_id,
		'shared_id'         => $shared_id,
		'relation'          => 'equivalent',
		'note'              => 'Reviewed test mapping',
		'expected_revision' => '',
	);
	$mapped  = syllabus_request( 'PUT', 'syllabus-comparison/mapping', $mapping );
	syllabus_check( 200 === $mapped->get_status(), 'Create revision-checked explicit mapping.' );
	syllabus_check( 409 === syllabus_request( 'PUT', 'syllabus-comparison/mapping', $mapping )->get_status(), 'Stale mapping update rejected.' );
	$compared = syllabus_request(
		'GET',
		'syllabus-comparison/compare',
		array(
			'a' => $syllabus_id,
			'b' => $other_id,
		)
	);
	syllabus_check( 200 === $compared->get_status() && 1 === count( $compared->get_data()['only_a'] ), 'Comparison reflects explicit mapping.' );
	syllabus_check(
		200 === syllabus_command(
			$syllabus_id,
			'detach_skill',
			array(
				'group_id' => $second_group,
				'term_id'  => $skill_id,
			)
		)->get_status(),
		'Detach placement.'
	);
	syllabus_check( (bool) get_term( $skill_id, Taxonomy::NAME ) && ! is_wp_error( get_term( $skill_id, Taxonomy::NAME ) ), 'Detachment preserves the library skill.' );
	echo "Syllabus editor integration: $checks checks passed.\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test count.
} finally {
	wp_set_current_user( $fixture_users['administrator'] ?? 0 );
	foreach ( $fixture_items as $fixture_item ) {
		$fixture_course = SyllabusCourse::course_id( $fixture_item );
		if ( $fixture_course ) {
			$fixture_courses[] = $fixture_course; }
		$outline = Syllabus::outline( $fixture_item );
		if ( ! is_wp_error( $outline ) && $outline['root_skill'] ) {
			$fixture_terms[] = $outline['root_skill']; }
		Items::delete( $fixture_item, 'delete', true );
	}
	foreach ( array_unique( array_filter( $fixture_courses ) ) as $fixture_course ) {
		foreach ( Catalog::chapters( $fixture_course ) as $fixture_chapter ) {
			wp_delete_post( $fixture_chapter['id'], true ); }
		wp_delete_post( $fixture_course, true );
	}
	foreach ( array_unique( array_filter( $fixture_terms ) ) as $fixture_term ) {
		wp_delete_term( $fixture_term, Taxonomy::NAME ); }
	foreach ( $fixture_users as $fixture_user ) {
		wp_delete_user( $fixture_user ); }
}
