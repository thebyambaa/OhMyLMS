<?php
/** Adaptive placement test: starting, adaptive questions, no help, "not learned yet", the placement, evidence, REST, MCP and My skills. Isolated site only. */
if ( PHP_SAPI !== 'cli' ) { exit; }
$config = json_decode( file_get_contents( getenv( 'OHMYLMS_TEST_CREDENTIALS' ) ), true );
$_SERVER['HTTP_HOST'] = '127.0.0.1:8100';
require $config['site'] . '/wp-load.php';
if ( ! defined( 'OHMYLMS_TEST_SITE' ) || ! in_array( DB_NAME, array( 'ohmylms_source_test', 'ohmylms_streak_test' ), true ) ) { throw new RuntimeException( 'Requires an isolated database.' ); }

use OhMyLMS\Assessment\Schema as AssessmentSchema;
use OhMyLMS\Curriculum\Comparison;
use OhMyLMS\Curriculum\Schema;
use OhMyLMS\Curriculum\SkillEdges;
use OhMyLMS\Placement\Placement;
use OhMyLMS\Skills\Evidence;
use OhMyLMS\Skills\Taxonomy;

global $wpdb;
$checks = 0; $terms = array(); $users = array(); $posts = array();
$admin = get_user_by( 'login', $config['username'] )->ID;
$tag   = wp_generate_password( 6, false, false );
function ok( $value, $message ) { global $checks; if ( ! $value ) { throw new RuntimeException( $message ); } ++$checks; }
function rest( $method, $path, array $data = array(), $guest = '' ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/' . $path );
	if ( $guest ) { $request->set_header( \OhMyLMS\Practice\Guests::HEADER, $guest ); }
	if ( 'GET' === $method ) { $request->set_query_params( $data ); }
	elseif ( $data ) { $request->set_header( 'Content-Type', 'application/json' ); $request->set_body( wp_json_encode( $data ) ); }
	return rest_do_request( $request );
}
function data( $response ) { return json_decode( wp_json_encode( $response->get_data() ), true ); }

try {
	wp_set_current_user( $admin );
	Schema::install();
	ok( \OhMyLMS\Assessment\Engine::practice(), 'practice is on' );

	// ---- A subject: an algebra chain (grades 6 to 9) and one geometry skill, two questions each ----
	$subject = 'Mathematics ' . $tag;
	$skill   = static function ( $name, $domain, $grade ) use ( $subject, $tag, &$terms, &$posts ) {
		$saved = Comparison::save_shared( 0, array( 'name' => $name . ' ' . $tag, 'subject' => $subject, 'topic' => 'Placement', 'domain' => $domain, 'grade_from' => $grade, 'grade_to' => $grade ) );
		ok( ! is_wp_error( $saved ), 'skill created' );
		$id      = (int) $saved['id'];
		$terms[] = $id;
		update_term_meta( $id, '_ohmylms_public_practice', 1 );
		for ( $n = 1; $n <= 2; $n++ ) {
			$created = rest( 'POST', 'question', array( 'name' => "$name q$n $tag", 'description' => "$name $n?", 'settings' => array( 'type' => 'single-choice', 'hint' => '<p>A hint</p>', 'score' => array( 'enabled' => true, 'value' => 1 ) ), 'questions' => array( array( 'answer' => 'Right', 'is_correct' => 1, 'order_number' => 1 ), array( 'answer' => 'Wrong', 'is_correct' => 0, 'order_number' => 2 ) ), 'skills' => array( 'p1' => array( 'primary' => $id ) ), 'bank' => array( 'family_id' => "pl-$id-$n" ) ) );
			ok( 201 === $created->get_status(), 'question created: ' . wp_json_encode( $created->get_data() ) );
			$posts[] = (int) $created->get_data()['id'];
			ok( 200 === rest( 'POST', 'questions/' . $created->get_data()['id'] . '/approve' )->get_status(), 'question approved' );
		}
		return $id;
	};
	$a6 = $skill( 'Number bonds', 'algebra', 6 );
	$a7 = $skill( 'Linear equations', 'algebra', 7 );
	$a8 = $skill( 'Expanding brackets', 'algebra', 8 );
	$a9 = $skill( 'Quadratic equations', 'algebra', 9 );
	$g7 = $skill( 'Angles', 'geometry', 7 );
	SkillEdges::add_prerequisite( $a7, $a6 );
	SkillEdges::add_prerequisite( $a8, $a7 );
	SkillEdges::add_prerequisite( $a9, $a8 );
	$empty_subject = 'Empty ' . $tag;
	$empty         = Comparison::save_shared( 0, array( 'name' => 'Nothing to ask ' . $tag, 'subject' => $empty_subject, 'domain' => 'number', 'grade_from' => 3 ) );
	$terms[]       = (int) $empty['id'];
	ok( in_array( $subject, Placement::subjects(), true ), 'the subject offers a test' );

	// ---- Access ----
	wp_set_current_user( 0 );
	ok( 401 === rest( 'POST', 'placement/sessions', array( 'subject' => $subject ) )->get_status(), 'without a guest credential nobody can start a test' );
	// A guest takes the test, then signs in: the test becomes theirs.
	$guest       = \OhMyLMS\Practice\Guests::create();
	$guest_start = rest( 'POST', 'placement/sessions', array( 'subject' => $subject, 'item_limit' => 5 ), $guest['token'] );
	ok( 201 === $guest_start->get_status(), 'a guest starts a test: ' . wp_json_encode( $guest_start->get_data() ) );
	$guest_state = data( $guest_start );
	$guest_right = array_column( $guest_state['current']['questions'], 'id', 'answer' )['Right'];
	$guest_after = rest( 'POST', 'practice/sessions/' . $guest_state['uuid'] . '/answer', array( 'item_id' => $guest_state['current']['item_id'], 'response' => array( $guest_right ) ), $guest['token'] );
	ok( 200 === $guest_after->get_status() && 1 === data( $guest_after )['session']['answered'], 'the guest answers through the practice routes' );
	ok( 401 === rest( 'GET', 'practice/sessions/' . $guest_state['uuid'] )->get_status() && 404 === rest( 'GET', 'practice/sessions/' . $guest_state['uuid'], array(), \OhMyLMS\Practice\Guests::create()['token'] )->get_status(), 'another visitor or guest cannot open the guest test' );
	$claimer = wp_create_user( 'placement-claim-' . $tag, wp_generate_password(), 'placement-claim-' . $tag . '@example.org' ); $users[] = $claimer;
	ok( ! is_wp_error( \OhMyLMS\Practice\Guests::claim( $guest['token'], $claimer ) ), 'the guest signs in' );
	$claimed = $wpdb->get_row( $wpdb->prepare( 'SELECT student_id, mode FROM %i WHERE uuid=%s', AssessmentSchema::table( 'practice_sessions' ), $guest_state['uuid'] ), ARRAY_A );
	$moved   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE source_type='placement' AND student_id=%d", AssessmentSchema::table( 'grade_events' ), $claimer ) );
	ok( $claimer === (int) $claimed['student_id'] && 'placement' === $claimed['mode'] && 1 === $moved, 'the test and its graded answer move to the learner' );
	$learner = wp_create_user( 'placement-' . $tag, wp_generate_password(), 'placement-' . $tag . '@example.org' ); $users[] = $learner;
	$other   = wp_create_user( 'placement-other-' . $tag, wp_generate_password(), 'placement-other-' . $tag . '@example.org' ); $users[] = $other;
	wp_set_current_user( $learner );
	ok( 409 === rest( 'POST', 'placement/sessions', array( 'subject' => $empty_subject ) )->get_status(), 'a subject without questions has no test' );
	ok( 400 === rest( 'POST', 'placement/sessions', array( 'subject' => $subject, 'grade' => 14 ) )->get_status(), 'grades are bounded' );
	$listed = data( rest( 'GET', 'placement/subjects' ) );
	ok( in_array( $subject, array_column( $listed, 'subject' ), true ) && false !== strpos( $listed[0]['url'], 'ohmylms_placement=' ), 'learners see where to take tests' );

	// ---- The test: right on algebra, "not learned yet" on geometry ----
	$started = rest( 'POST', 'placement/sessions', array( 'subject' => $subject, 'grade' => 8, 'item_limit' => 6 ) );
	ok( 201 === $started->get_status(), 'test started: ' . wp_json_encode( $started->get_data() ) );
	$state = data( $started );
	ok( 'placement' === $state['mode'] && 6 === $state['item_limit'] && $state['current'] && $subject === $state['placement']['subject'] && 8 === $state['placement']['grade'], 'the first question is ready' );
	ok( false === $state['current']['has_hint'] && false === $state['ai']['hint'] && false === $state['ai']['explain'] && true === $state['can_skip'] && false === $state['retry'], 'no hints, tutor or retries; skipping is "not learned yet"' );
	$uuid = $state['uuid'];
	ok( 409 === rest( 'POST', 'practice/sessions/' . $uuid . '/hint', array( 'item_id' => $state['current']['item_id'] ) )->get_status(), 'hints are refused' );
	ok( 409 === rest( 'POST', 'practice/sessions/' . $uuid . '/ai', array( 'item_id' => $state['current']['item_id'], 'kind' => 'hint' ) )->get_status() || 400 === rest( 'POST', 'practice/sessions/' . $uuid . '/ai', array( 'item_id' => $state['current']['item_id'], 'kind' => 'hint' ) )->get_status(), 'tutor help is refused' );
	$skill_of = static function ( $item_id ) use ( $wpdb ) {
		$display = json_decode( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT display FROM %i WHERE id=%d', AssessmentSchema::table( 'practice_items' ), $item_id ) ), true );
		return (int) ( $display['placement_skill'] ?? 0 );
	};
	$asked = array();
	for ( $turn = 0; $turn < 10 && 'active' === $state['status'] && $state['current']; $turn++ ) {
		$item    = $state['current']['item_id'];
		$asked[] = $skill_of( $item );
		if ( $g7 === end( $asked ) ) {
			$state = data( rest( 'POST', 'practice/sessions/' . $uuid . '/skip', array( 'item_id' => $item ) ) )['session'];
			continue;
		}
		$right    = array_column( $state['current']['questions'], 'id', 'answer' )['Right'];
		$response = data( rest( 'POST', 'practice/sessions/' . $uuid . '/answer', array( 'item_id' => $item, 'response' => array( $right ) ) ) );
		ok( ! isset( $response['feedback'] ) && ! isset( $response['correct'] ) && isset( $response['session'] ) && 64 === strlen( (string) $response['receipt'] ), 'no feedback during the test, only a receipt: ' . wp_json_encode( array_keys( $response ) ) );
		$state = $response['session'];
	}
	ok( 'complete' === $state['status'] && count( $asked ) <= 6 && count( $asked ) === count( array_unique( $asked ) ), 'one question per skill, within the limit: ' . wp_json_encode( $asked ) );
	ok( in_array( $g7, $asked, true ), 'every domain is reached' );
	ok( count( $asked ) === $state['answered'] && array( 'done' ) === array_values( array_unique( $state['results'] ) ) && null === $state['correct'] && 0 === $state['skipped'], 'progress counts "not learned yet" and never shows right or wrong: ' . wp_json_encode( array( $state['answered'], $state['results'], $state['correct'] ) ) );
	ok( ! in_array( $a6, $asked, true ), 'right answers higher up settle the earlier skills without asking: ' . wp_json_encode( $asked ) );
	$result = $state['placement']['result'];
	$areas  = array_column( $result['domains'], null, 'domain' );
	ok( 9 === $areas['algebra']['grade'] && 'Grade 9' === $areas['algebra']['grade_label'] && 4 === $areas['algebra']['known'] && 0 === $areas['geometry']['grade'] && '' === $areas['geometry']['grade_label'], 'working grades: ' . wp_json_encode( $result['domains'] ) );
	ok( 4 === $result['known'] && 5 === $result['total'] && array( $g7 ) === array_column( $result['start'], 'id' ) && false !== strpos( $result['start'][0]['practice_url'], 'ohmylms_practice=' . $g7 ), 'start with what is not known yet: ' . wp_json_encode( $result['start'] ) );
	ok( array() === $state['recommendations'] && empty( $state['reward'] ), 'no practice suggestions or rewards on a placement' );

	// ---- Evidence: only what was answered ----
	Evidence::process( 200 );
	$sources = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT source_type FROM %i WHERE student_id=%d', AssessmentSchema::table( 'grade_events' ), $learner ) );
	ok( array( 'placement' ) === $sources, 'answers are graded as placement: ' . wp_json_encode( $sources ) );
	$states = $wpdb->get_col( $wpdb->prepare( 'SELECT term_id FROM %i WHERE student_id=%d', AssessmentSchema::table( 'student_skill_state' ), $learner ) );
	ok( ! in_array( (string) $a6, $states, true ) && ! in_array( (string) $g7, $states, true ) && $states, 'estimated and skipped skills get no level: ' . wp_json_encode( $states ) );
	ok( in_array( 'placement', \OhMyLMS\Performance\Settings::surfaces(), true ), 'placement can be switched off as skill evidence' );

	// ---- Results ----
	$results = data( rest( 'GET', 'reports/skills/students/' . $learner . '/placement' ) );
	ok( 1 === count( $results ) && $subject === $results[0]['subject'] && $uuid === $results[0]['uuid'] && 9 === array_column( $results[0]['domains'], 'grade', 'domain' )['algebra'], 'the learner reads their placement: ' . wp_json_encode( $results ) );
	if ( \OhMyLMS\Practice\Frontend::enabled() ) {
		$html = \OhMyLMS\Practice\Frontend::skills();
		ok( false !== strpos( $html, 'Placement tests' ) && false !== strpos( $html, 'Take it again' ) && false !== strpos( $html, 'Grade 9' ), 'My skills shows the placement' );
		$page = \OhMyLMS\Placement\PlacementPage::render( array( 'subject' => $subject ) );
		ok( false !== strpos( $page, 'data-placement="' . esc_attr( $subject ) . '"' ) && false !== strpos( $page, 'ohmylms-placement-grade' ), 'the test page carries the subject and the grade question' );
		ok( false !== strpos( \OhMyLMS\Placement\PlacementPage::render( array( 'subject' => 'Nope ' . $tag ) ), 'no placement test' ), 'an unknown subject has no page' );
	}
	wp_set_current_user( $other );
	ok( 403 === rest( 'GET', 'reports/skills/students/' . $learner . '/placement' )->get_status(), 'another learner cannot read it' );
	ok( 404 === rest( 'GET', 'practice/sessions/' . $uuid )->get_status(), 'nor open the test' );
	wp_set_current_user( $admin );
	ok( 200 === rest( 'GET', 'reports/skills/students/' . $learner . '/placement' )->get_status() && isset( \OhMyLMS\MCP\Server::tools()['get_student_placement'] ), 'staff read it; MCP lists it' );
	echo $checks . " placement test integration checks passed.\n";
} finally {
	wp_set_current_user( $admin );
	foreach ( $users as $id ) {
		$sessions = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE student_id=%d', AssessmentSchema::table( 'practice_sessions' ), $id ) );
		foreach ( $sessions as $session ) { $wpdb->delete( AssessmentSchema::table( 'practice_items' ), array( 'session_id' => $session ) ); }
		$wpdb->delete( AssessmentSchema::table( 'practice_sessions' ), array( 'student_id' => $id ) );
		foreach ( array( 'student_skill_state', 'skill_evidence', 'grade_events', 'skill_level_events' ) as $table ) { $wpdb->delete( AssessmentSchema::table( $table ), array( 'student_id' => $id ) ); }
	}
	foreach ( $terms as $id ) { $wpdb->delete( SkillEdges::table(), array( 'skill_id' => $id ) ); $wpdb->delete( SkillEdges::table(), array( 'other_id' => $id ) ); }
	foreach ( $posts as $id ) { wp_delete_post( $id, true ); }
	foreach ( $terms as $id ) { wp_delete_term( $id, Taxonomy::NAME ); }
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $users as $id ) { if ( $id && ! is_wp_error( $id ) ) { wp_delete_user( $id ); } }
}
