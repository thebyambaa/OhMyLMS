<?php
/** Skill maps: coherence map, syllabus overlay, learner overlay, syllabus comparison, REST, MCP and My skills. Isolated site only. */
if ( PHP_SAPI !== 'cli' ) { exit; }
$config = json_decode( file_get_contents( getenv( 'OHMYLMS_TEST_CREDENTIALS' ) ), true );
$_SERVER['HTTP_HOST'] = '127.0.0.1:8100';
require $config['site'] . '/wp-load.php';
if ( ! defined( 'OHMYLMS_TEST_SITE' ) || ! in_array( DB_NAME, array( 'ohmylms_source_test', 'ohmylms_streak_test' ), true ) ) { throw new RuntimeException( 'Requires an isolated database.' ); }

use OhMyLMS\Assessment\Schema as AssessmentSchema;
use OhMyLMS\Curriculum\Comparison;
use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\Schema;
use OhMyLMS\Curriculum\SkillEdges;
use OhMyLMS\Curriculum\SkillMappings;
use OhMyLMS\Curriculum\Syllabus;
use OhMyLMS\Performance\Guidance;
use OhMyLMS\Skills\Taxonomy;

global $wpdb;
$checks = 0; $items = array(); $terms = array(); $users = array();
$before = get_option( Comparison::OPTION, array() );
$admin  = get_user_by( 'login', $config['username'] )->ID;
$tag    = wp_generate_password( 6, false, false );
function ok( $value, $message ) { global $checks; if ( ! $value ) { throw new RuntimeException( $message ); } ++$checks; }
function rest( $method, $path, array $query = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/' . $path );
	$request->set_query_params( $query );
	return rest_do_request( $request );
}
/** Response data as a client receives it (objects become arrays). */
function data( $response ) { return json_decode( wp_json_encode( $response->get_data() ), true ); }

try {
	wp_set_current_user( $admin );
	Schema::install();

	// ---- A subject library ----
	$subject = 'Mathematics ' . $tag;
	$shared  = static function ( $name, $domain, $from, $to ) use ( $subject, $tag, &$terms ) {
		$skill = Comparison::save_shared( 0, array( 'name' => $name . ' ' . $tag, 'subject' => $subject, 'topic' => 'Map', 'domain' => $domain, 'grade_from' => $from, 'grade_to' => $to ) );
		ok( ! is_wp_error( $skill ), 'shared skill created: ' . $name );
		$terms[] = $skill['id'];
		return (int) $skill['id'];
	};
	$linear    = $shared( 'Solve linear equations', 'algebra', 7, 7 );
	$quadratic = $shared( 'Solve quadratic equations', 'algebra', 9, 10 );
	$expand    = $shared( 'Expand brackets', 'algebra', 10, 10 );
	$triangle  = $shared( 'Area of a triangle', 'geometry', 6, 7 );
	$pythag    = $shared( 'Pythagoras theorem', 'geometry', 8, 8 );
	$integers  = $shared( 'Order integers', 'number', 5, 5 );
	$powers    = $shared( 'Laws of indices', 'number', 9, 9 );
	$loose     = $shared( 'Not placed yet', '', 0, 0 );
	SkillEdges::add_prerequisite( $quadratic, $linear );
	SkillEdges::add_prerequisite( $quadratic, $expand );   // Taught later than the skill that needs it.
	SkillEdges::add_prerequisite( $pythag, $triangle );
	SkillEdges::add_prerequisite( $powers, $integers );    // Four grades back.
	SkillEdges::add_encompass( $quadratic, $linear, 0.5 );

	// ---- Two syllabuses mapped onto it ----
	$syllabus = static function ( $name, array $objectives ) use ( $tag, &$items, &$terms ) {
		$item = Items::create( array( 'name' => $name . ' ' . $tag, 'is_syllabus' => true ) ); $id = (int) $item['id']; $items[] = $id;
		$group = Syllabus::add_group( $id, array( 'name' => 'All' ) );
		$ids   = array();
		foreach ( $objectives as $index => $row ) {
			$term = Syllabus::create_skill( $id, $group, array( 'name' => $row[0] . ' ' . $tag, 'code' => (string) ( $index + 1 ) ) );
			ok( ! is_wp_error( $term ), 'objective created' );
			$terms[] = $term['term_id']; $ids[] = (int) $term['term_id'];
			if ( $row[1] ) { ok( ! is_wp_error( SkillMappings::save( (int) $term['term_id'], $row[1], $row[2] ?? 'equivalent' ) ), 'objective mapped' ); }
		}
		return array( $id, $ids );
	};
	list( $grade9, $objectives9 ) = $syllabus( 'Grade 9', array( array( 'Linear', $linear ), array( 'Quadratics', $quadratic ), array( 'Indices', $powers, 'narrower' ), array( 'Triangles', $triangle, 'related' ), array( 'Bar charts', 0 ) ) );
	list( $igcse, $objectives_i ) = $syllabus( 'IGCSE', array( array( 'Equations', $linear ), array( 'Pythagoras', $pythag ), array( 'Quadratic equations', $quadratic, 'broader' ) ) );
	update_option( Comparison::OPTION, array_merge( (array) $before, array( $subject => array( $grade9, $igcse ) ) ) );

	// ---- Subjects ----
	$subjects = array_column( data( rest( 'GET', 'syllabus-comparison/skill-map/subjects' ) ), null, 'subject' );
	ok( 8 === $subjects[ $subject ]['skills'] && array( $grade9, $igcse ) === array_column( $subjects[ $subject ]['syllabuses'], 'id' ), 'subjects list their skills and syllabuses: ' . wp_json_encode( $subjects[ $subject ] ?? null ) );

	// ---- Coherence map ----
	$response = rest( 'GET', 'syllabus-comparison/skill-map', array( 'subject' => $subject ) );
	ok( 200 === $response->get_status(), 'map read: ' . wp_json_encode( $response->get_data() ) );
	$map = data( $response );
	ok( array( 'number', 'algebra', 'geometry' ) === array_column( $map['domains'], 'id' ) && 'Algebra' === $map['domains'][1]['label'], 'domains in library order: ' . wp_json_encode( $map['domains'] ) );
	ok( range( 5, 10 ) === array_column( $map['grades'], 'value' ) && 'Grade 5' === $map['grades'][0]['label'], 'grades from the first to the last used' );
	ok( array( $loose ) === $map['unplaced'] && 8 === count( $map['skills'] ), 'unplaced skills are listed apart' );
	$skills = array_column( $map['skills'], null, 'id' );
	ok( array( $linear, $expand ) === $skills[ $quadratic ]['prerequisites'] && array( array( 'id' => $linear, 'weight' => 0.5 ) ) === $skills[ $quadratic ]['includes'] && 2 === $skills[ $quadratic ]['mapped'], 'skills carry links and mapping counts: ' . wp_json_encode( $skills[ $quadratic ] ) );
	$issues = array();
	foreach ( $map['issues'] as $issue ) { $issues[] = $issue['type'] . ':' . $issue['skill'] . ':' . $issue['other']; }
	ok( in_array( "later_prerequisite:$quadratic:$expand", $issues, true ) && in_array( "long_gap:$powers:$integers", $issues, true ), 'coherence issues: ' . wp_json_encode( $issues ) );
	ok( in_array( "no_prerequisites:$expand:0", $issues, true ) && ! in_array( "no_prerequisites:$linear:0", $issues, true ), 'missing prerequisites are flagged above the first grade only' );
	ok( null === $map['syllabus'] && null === $map['student'], 'no overlays unless asked' );
	ok( 400 === rest( 'GET', 'syllabus-comparison/skill-map' )->get_status(), 'a subject is required' );

	// ---- Syllabus overlay ----
	$overlay = data( rest( 'GET', 'syllabus-comparison/skill-map', array( 'subject' => $subject, 'syllabus_id' => $grade9 ) ) )['syllabus'];
	ok( $grade9 === $overlay['id'] && 5 === $overlay['objectives'] && 2 === $overlay['unmapped'], 'related and missing mappings count as unmapped: ' . wp_json_encode( $overlay ) );
	ok( 3 === count( $overlay['covered'] ) && isset( $overlay['covered'][ $linear ], $overlay['covered'][ $quadratic ], $overlay['covered'][ $powers ] ), 'three skills taught: ' . wp_json_encode( $overlay['covered'] ) );
	ok( 'narrower' === $overlay['covered'][ $powers ][0]['relation'] && ! isset( $overlay['covered'][ $triangle ] ), 'covering relations are kept; related ones do not cover' );

	// ---- Learner overlay and the student map ----
	$learner = wp_create_user( 'skill-map-' . $tag, wp_generate_password(), 'skill-map-' . $tag . '@example.org' ); $users[] = $learner;
	$other   = wp_create_user( 'skill-map-other-' . $tag, wp_generate_password(), 'skill-map-other-' . $tag . '@example.org' ); $users[] = $other;
	$state   = static function ( $term, $level ) use ( $wpdb, $learner ) {
		$wpdb->insert( AssessmentSchema::table( 'student_skill_state' ), array( 'student_id' => $learner, 'term_id' => $term, 'level' => $level, 'policy' => 'evidence', 'last_evidence_at' => gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ), 'updated_at' => current_time( 'mysql', true ) ) );
	};
	$state( $objectives9[0], 'mastered' );   // Linear, through the Grade 9 objective.
	$state( $quadratic, 'familiar' );
	$state( $pythag, 'attempted' );
	Guidance::flush();
	$student = data( rest( 'GET', 'syllabus-comparison/skill-map', array( 'subject' => $subject, 'student_id' => $learner ) ) )['student'];
	ok( 'mastered' === $student['levels'][ $linear ] && 'familiar' === $student['levels'][ $quadratic ] && ! isset( $student['levels'][ $objectives9[0] ] ), 'objective work shows on its shared skill: ' . wp_json_encode( $student ) );
	ok( 5 === $student['counts']['not-started'] && 1 === $student['counts']['mastered'] && 'algebra' === $student['areas'][0]['domain'] && 75 === $student['areas'][0]['strength'], 'counts and strength per domain: ' . wp_json_encode( $student['areas'] ) );
	ok( 404 === rest( 'GET', 'syllabus-comparison/skill-map', array( 'subject' => $subject, 'student_id' => 999999999 ) )->get_status(), 'an unknown learner is refused' );

	wp_set_current_user( $learner );
	$own = rest( 'GET', 'reports/skills/students/' . $learner . '/map' );
	$mine = data( $own );
	ok( 200 === $own->get_status() && array( array( 'subject' => $subject, 'started' => 3 ) ) === $mine['subjects'] && $subject === $mine['map']['subject'] && 'mastered' === $mine['map']['student']['levels'][ $linear ], 'a learner reads their own map: ' . wp_json_encode( $mine['subjects'] ) );
	ok( 403 === rest( 'GET', 'syllabus-comparison/skill-map', array( 'subject' => $subject ) )->get_status() || 401 === rest( 'GET', 'syllabus-comparison/skill-map', array( 'subject' => $subject ) )->get_status(), 'learners cannot read the coherence map' );
	ok( in_array( rest( 'GET', 'syllabus-comparison/compare', array( 'a' => $grade9, 'b' => $igcse ) )->get_status(), array( 401, 403 ), true ), 'learners cannot compare syllabuses' );
	if ( \OhMyLMS\Practice\Frontend::enabled() ) {
		Guidance::flush();
		$html = \OhMyLMS\Practice\Frontend::skills();
		ok( false !== strpos( $html, 'My skill map' ) && false !== strpos( $html, 'ohmylms-map-mastered' ) && false !== strpos( $html, 'Grade 10' ), 'My skills draws the map' );
	}
	wp_set_current_user( $other );
	ok( 403 === rest( 'GET', 'reports/skills/students/' . $learner . '/map' )->get_status(), 'another learner cannot read it' );
	$empty = data( rest( 'GET', 'reports/skills/students/' . $other . '/map' ) );
	ok( array() === $empty['subjects'] && null === $empty['map'], 'a learner with no skills has no map' );
	wp_set_current_user( $admin );

	// ---- Syllabus comparison ----
	$compare = rest( 'GET', 'syllabus-comparison/compare', array( 'a' => $grade9, 'b' => $igcse ) );
	ok( 200 === $compare->get_status(), 'compare read: ' . wp_json_encode( $compare->get_data() ) );
	$result = data( $compare );
	ok( array( $linear, $quadratic ) === $result['both'] && array( $powers ) === $result['only_a'] && array( $pythag ) === $result['only_b'], 'both, only A and only B: ' . wp_json_encode( array( $result['both'], $result['only_a'], $result['only_b'] ) ) );
	ok( 50 === $result['overlap'] && array( $objectives9[3], $objectives9[4] ) === $result['unmapped_a'] && array() === $result['unmapped_b'], 'overlap and unmapped objectives' );
	ok( 'Grade 9 ' . $tag === $result['a']['name'] && 5 === count( $result['a']['objectives'] ) && isset( $result['skills'][ $pythag ] ), 'the sides and skills are named' );
	$domains = array_column( $result['domains'], null, 'domain' );
	ok( 2 === $domains['algebra']['both'] && 1 === $domains['geometry']['only_b'] && 'Number' === $domains['number']['label'], 'counts per domain: ' . wp_json_encode( $result['domains'] ) );
	ok( 400 === rest( 'GET', 'syllabus-comparison/compare', array( 'a' => $grade9, 'b' => $grade9 ) )->get_status(), 'a syllabus is not compared with itself' );

	// ---- MCP ----
	$tools = \OhMyLMS\MCP\Server::tools();
	ok( '/syllabus-comparison/skill-map' === $tools['get_skill_map']['route'] && '/syllabus-comparison/compare' === $tools['compare_syllabuses']['route'] && '/reports/skills/students/{student_id}/map' === $tools['get_student_skill_map']['route'], 'MCP lists the map tools' );
	echo $checks . " skill map integration checks passed.\n";
} finally {
	wp_set_current_user( $admin );
	update_option( Comparison::OPTION, $before );
	foreach ( $users as $id ) { $wpdb->delete( AssessmentSchema::table( 'student_skill_state' ), array( 'student_id' => $id ) ); }
	foreach ( $terms as $id ) { $wpdb->delete( SkillMappings::table(), array( 'specific_term_id' => $id ) ); $wpdb->delete( SkillEdges::table(), array( 'skill_id' => $id ) ); $wpdb->delete( SkillEdges::table(), array( 'other_id' => $id ) ); }
	foreach ( $items as $id ) { $item = Items::get( $id ); if ( $item ) { $course = $item['course_id']; $root = $item['skill_root_id']; Items::delete( $id, 'delete', true ); if ( $course ) { wp_delete_post( $course, true ); } if ( $root ) { wp_delete_term( $root, Taxonomy::NAME ); } } }
	foreach ( $terms as $id ) { wp_delete_term( $id, Taxonomy::NAME ); }
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $users as $id ) { if ( $id && ! is_wp_error( $id ) ) { wp_delete_user( $id ); } }
}
