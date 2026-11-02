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
use OhMyLMS\Curriculum\SyllabusSettings;
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

	$snapshot = syllabus_request( 'GET', $syllabus_path )->get_data();
	syllabus_check( isset( $snapshot['settings']['categories'] ) && array_key_exists( 'version', $snapshot['syllabus'] ) && is_array( $snapshot['requirements'] ), 'The editor read carries the source, profile and course requirements.' );
	syllabus_check( count( $snapshot['requirements'] ) === Catalog::summary( $course_id )['skills'], 'Every mirrored outcome is listed as a requirement.' );
	$required_skill = $snapshot['requirements'][0]['term_id'];
	$before         = CourseProgram::editor( $course_id )['draft']['outcomes'];
	foreach ( array(
		array( 'skills' => array() ),
		array( 'skills' => array( array( 'required' => true ) ) ),
		array(
			'skills' => array(
				array(
					'term_id' => $required_skill,
					'target'  => 'expert',
				),
			),
		),
		array(
			'skills' => array(
				array(
					'term_id' => $required_skill,
					'extra'   => 1,
				),
			),
		),
		array(
			'skills' => array(
				array(
					'term_id'  => $shared_id,
					'required' => true,
				),
			),
		),
	) as $invalid ) {
		syllabus_check( 400 === syllabus_command( $syllabus_id, 'set_requirements', $invalid )->get_status(), 'Invalid or foreign requirement change rejected.' );
	}
	syllabus_check( CourseProgram::editor( $course_id )['draft']['outcomes'] === $before, 'Rejected requirement changes leave the draft outcomes.' );
	$required = syllabus_command(
		$syllabus_id,
		'set_requirements',
		array(
			'skills' => array(
				array(
					'term_id'  => $required_skill,
					'required' => true,
					'target'   => 'mastered',
				),
			),
		)
	);
	syllabus_check( 200 === $required->get_status(), 'Require a skill at Mastered.' );
	$requirement = wp_list_filter( $required->get_data()['requirements'], array( 'term_id' => $required_skill ) );
	$requirement = reset( $requirement );
	syllabus_check( $requirement && true === $requirement['required'] && 'mastered' === $requirement['target'], 'The response reports the new requirement and target.' );
	$outcome = wp_list_filter( CourseProgram::editor( $course_id )['draft']['outcomes'], array( 'term_id' => $required_skill ) );
	$outcome = reset( $outcome );
	syllabus_check( ! empty( $outcome['required'] ) && 'mastered' === $outcome['target'] && ! get_post_meta( $course_id, CourseProgram::CURRENT, true ), 'The requirement is saved to the course draft only.' );
	syllabus_check(
		200 === syllabus_command(
			$syllabus_id,
			'edit_group',
			array(
				'group_id' => $first_group,
				'name'     => $fixture_tag . ' Algebra again',
				'code'     => 'A',
			)
		)->get_status(),
		'Edit after choosing requirements.'
	);
	$outcome = wp_list_filter( CourseProgram::editor( $course_id )['draft']['outcomes'], array( 'term_id' => $required_skill ) );
	$outcome = reset( $outcome );
	syllabus_check( ! empty( $outcome['required'] ) && 'mastered' === $outcome['target'], 'Synchronization keeps chosen requirements.' );

	foreach ( array(
		array( 'categories' => 'Core' ),
		array( 'version' => str_repeat( 'v', 61 ) ),
		array( 'subject' => str_repeat( 's', 101 ) ),
		array( 'name' => '' ),
		array( 'publish' => true ),
	) as $invalid ) {
		syllabus_check( 400 === syllabus_command( $syllabus_id, 'edit_source', $invalid )->get_status(), 'Invalid source change rejected.' );
	}
	$source = syllabus_command(
		$syllabus_id,
		'edit_source',
		array(
			'name'        => $fixture_tag . ' Source',
			'code'        => '0580',
			'version'     => '2027 edition',
			'description' => 'Reviewed source notes',
			'subject'     => 'Mathematics',
			'grade'       => 'Grade 10',
			'language'    => 'English',
			'categories'  => array( 'Core', ' Extended ', 'Core', '' ),
		)
	);
	syllabus_check( 200 === $source->get_status(), 'Save source details and profile: ' . wp_json_encode( $source->get_data() ) );
	$item = Items::get( $syllabus_id );
	syllabus_check( '0580' === $item['code'] && '2027 edition' === $item['version'] && 'Reviewed source notes' === $item['description'], 'Source code, version and description persist.' );
	syllabus_check( array( 'Core', 'Extended' ) === $source->get_data()['settings']['categories'] && 'Grade 10' === $source->get_data()['settings']['grade'], 'Profile fields are cleaned and returned.' );
	syllabus_check( $fixture_tag . ' Source' === get_the_title( $course_id ), 'Renaming the syllabus renames its mirrored course.' );
	$category_group = Syllabus::add_group( $syllabus_id, array( 'name' => 'Extended' ) );
	$category_skill = Syllabus::create_skill( $syllabus_id, $category_group, array( 'name' => $fixture_tag . ' Extended skill' ) );
	syllabus_check( ! is_wp_error( $category_skill ), 'Create a skill whose group names a category.' );
	foreach ( Syllabus::outline( $syllabus_id )['contents'] as $content ) {
		foreach ( $content['groups'] as $group ) {
			foreach ( $group['skills'] as $skill ) {
				$fixture_terms[] = $skill['term_id']; }
		}
	}
	$used = syllabus_command(
		$syllabus_id,
		'edit_source',
		array(
			'version'    => 'Not saved',
			'categories' => array( 'Core' ),
		)
	);
	syllabus_check( 409 === $used->get_status(), 'A category used by skills cannot be removed.' );
	syllabus_check( '2027 edition' === Items::get( $syllabus_id )['version'], 'A refused profile change leaves the source fields.' );
	$without_course = Items::create(
		array(
			'name'        => $fixture_tag . ' Without course',
			'item_type'   => 'subject',
			'is_syllabus' => true,
		)
	);
	$fixture_items[] = $without_course['id'];
	syllabus_check( ! SyllabusCourse::course_id( $without_course['id'] ), 'Fixture syllabus has no course.' );
	syllabus_check( 404 === syllabus_command( $without_course['id'], 'edit_source', array( 'grade' => 'Grade 9' ) )->get_status(), 'A profile needs the syllabus course.' );
	syllabus_check( 200 === syllabus_command( $without_course['id'], 'edit_source', array( 'version' => '1' ) )->get_status() && '1' === Items::get( $without_course['id'] )['version'], 'Source fields save without a course.' );
	Syllabus::add_skill( $without_course['id'], Syllabus::add_group( $without_course['id'], array( 'name' => $fixture_tag . ' Placed' ) ), $required_skill );
	syllabus_check( 404 === syllabus_command( $without_course['id'], 'set_requirements', array( 'skills' => array( array( 'term_id' => $required_skill ) ) ) )->get_status(), 'Requirements need the syllabus course.' );

	$group_ids     = array_column( Syllabus::outline( $syllabus_id )['contents'][0]['groups'], 'id' );
	$group_skills  = array_column( Syllabus::outline( $syllabus_id )['contents'][0]['groups'][ array_search( $first_group, $group_ids, true ) ]['skills'], 'term_id' );
	$held          = syllabus_command( $syllabus_id, 'delete_group', array( 'group_id' => $first_group ) );
	syllabus_check( $group_skills && 409 === $held->get_status() && 'ohmylms_syllabus_needs_confirmation' === $held->get_data()['code'], 'Deleting a group with skills needs confirmation.' );
	syllabus_check( (bool) Syllabus::group( $first_group ), 'An unconfirmed deletion keeps the group.' );
	syllabus_check(
		404 === syllabus_command(
			$other_id,
			'delete_group',
			array(
				'group_id' => $first_group,
				'confirm'  => true,
			)
		)->get_status(),
		'Another syllabus cannot delete this group.'
	);
	$deleted = syllabus_command(
		$syllabus_id,
		'delete_group',
		array(
			'group_id' => $first_group,
			'confirm'  => true,
		)
	);
	syllabus_check( 200 === $deleted->get_status() && ! Syllabus::group( $first_group ), 'Confirmed deletion removes the group.' );
	foreach ( $group_skills as $group_skill ) {
		$term = get_term( $group_skill, Taxonomy::NAME );
		syllabus_check( $term && ! is_wp_error( $term ), 'Deleting a group keeps its skills in the library.' );
	}
	syllabus_check( ! array_intersect( $group_skills, array_map( 'intval', array_column( CourseProgram::editor( $course_id )['draft']['outcomes'], 'term_id' ) ) ), 'Skills of a deleted group leave the course outcomes.' );
	$empty = Syllabus::add_group( $syllabus_id, array( 'name' => $fixture_tag . ' Empty' ) );
	syllabus_check( 200 === syllabus_command( $syllabus_id, 'delete_group', array( 'group_id' => $empty ) )->get_status() && ! Syllabus::group( $empty ), 'An empty group deletes without confirmation.' );

	$settings_before = SyllabusSettings::get( $syllabus_id );
	foreach ( array(
		array( 'category_styles' => array( 'Core' => array( 'color' => 'red;display:none' ) ) ),
		array( 'category_styles' => array( 'Core' => array( 'shape' => 'round' ) ) ),
		array( 'category_styles' => 'Core' ),
		array( 'category_styles' => array( 'Core' => array( 'icon' => 'not-an-icon' ) ) ),
	) as $invalid ) {
		syllabus_check( 400 === syllabus_command( $syllabus_id, 'edit_source', $invalid )->get_status(), 'Invalid category style rejected.' );
	}
	$unknown = syllabus_command( $syllabus_id, 'edit_source', array( 'category_styles' => array( 'Unknown' => array( 'icon' => 'book' ) ) ) );
	syllabus_check( 400 === $unknown->get_status() && 'ohmylms_syllabus_category_unknown' === $unknown->get_data()['code'], 'A style needs an existing category.' );
	syllabus_check( SyllabusSettings::get( $syllabus_id ) === $settings_before, 'Rejected styles change nothing.' );
	$styled = syllabus_command(
		$syllabus_id,
		'edit_source',
		array(
			'category_styles' => array(
				'Core'     => array(
					'icon'  => 'calculator',
					'color' => '#AABBCC',
				),
				'Extended' => array( 'icon' => 'book' ),
			),
		)
	);
	syllabus_check( 200 === $styled->get_status(), 'Save category styles: ' . wp_json_encode( $styled->get_data() ) );
	syllabus_check(
		array(
			'Core'     => array(
				'icon'  => 'calculator',
				'color' => '#aabbcc',
			),
			'Extended' => array(
				'icon'  => 'book',
				'color' => '#6e42d3',
			),
		) === $styled->get_data()['settings']['category_styles'],
		'Styles are stored with lowercase and default colours.'
	);
	syllabus_check( 200 === syllabus_command( $syllabus_id, 'edit_source', array( 'category_styles' => array() ) )->get_status() && array() === SyllabusSettings::get( $syllabus_id )['category_styles'], 'Styles can be cleared.' );

	$optional = array();
	foreach ( SyllabusCourse::requirements( $syllabus_id ) as $requirement ) {
		$optional[] = array(
			'term_id'  => $requirement['term_id'],
			'required' => false,
		);
	}
	syllabus_check( $optional && 200 === syllabus_command( $syllabus_id, 'set_requirements', array( 'skills' => $optional ) )->get_status(), 'Make every syllabus skill optional.' );
	$publication = syllabus_request( 'GET', 'authoring/courses/' . $course_id . '/publication' );
	syllabus_check( 200 === $publication->get_status() && in_array( 'Skill-based and blended courses need a required skill outcome.', $publication->get_data()['readiness'], true ), 'Publication readiness reports the missing required skill of the mirrored course.' );
	syllabus_check(
		409 === syllabus_request(
			'POST',
			'authoring/courses/' . $course_id . '/publication',
			array(
				'revision'  => 'stale',
				'confirmed' => true,
			)
		)->get_status(),
		'Publishing a stale preview is refused.'
	);
	$refused = syllabus_request(
		'POST',
		'authoring/courses/' . $course_id . '/publication',
		array(
			'revision'  => $publication->get_data()['revision'],
			'confirmed' => true,
		)
	);
	syllabus_check( 400 <= $refused->get_status() && 'draft' === get_post_status( $course_id ) && ! get_post_meta( $course_id, CourseProgram::CURRENT, true ), 'A mirrored course with nothing required cannot publish.' );
	wp_set_current_user( $fixture_users['author'] );
	syllabus_check( 400 < syllabus_request( 'GET', 'authoring/courses/' . $course_id . '/publication' )->get_status(), 'Authors cannot preview the mirrored course publication.' );
	wp_set_current_user( $fixture_users['administrator'] );
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
