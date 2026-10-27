<?php
/** Guidance engine: strength by area, next steps, covered reviews, readiness, REST, MCP and My skills. Isolated site only. */
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
use OhMyLMS\Performance\Snapshot;
use OhMyLMS\Skills\Mastery;
use OhMyLMS\Skills\Taxonomy;

global $wpdb;
$checks = 0; $items = array(); $terms = array(); $users = array();
$before       = get_option( Comparison::OPTION, array() );
$rules_before = get_option( Mastery::OPTION, null );
$admin        = get_user_by( 'login', $config['username'] )->ID;
$tag          = wp_generate_password( 6, false, false );
function ok( $value, $message ) { global $checks; if ( ! $value ) { throw new RuntimeException( $message ); } ++$checks; }
function guidance_get( $student, array $query = array() ) {
	$request = new WP_REST_Request( 'GET', '/ohmylms/v1/reports/skills/students/' . $student . '/guidance' );
	$request->set_query_params( $query );
	return rest_do_request( $request );
}

try {
	wp_set_current_user( $admin );
	Schema::install();
	ok( Schema::ready() && AssessmentSchema::ready(), 'schemas are installed' );
	// Thirty days between reviews keeps the dates below easy to follow.
	update_option( Mastery::OPTION, array( 'review_after_days' => 30 ) );

	// ---- A small library: algebra, geometry, number ----
	$subject = 'Mathematics ' . $tag;
	$shared  = static function ( $name, $domain, $from, $to ) use ( $subject, $tag, &$terms ) {
		$skill = Comparison::save_shared( 0, array( 'name' => $name . ' ' . $tag, 'subject' => $subject, 'topic' => 'Guidance', 'domain' => $domain, 'grade_from' => $from, 'grade_to' => $to ) );
		ok( ! is_wp_error( $skill ), 'shared skill created: ' . $name );
		$terms[] = $skill['id'];
		return (int) $skill['id'];
	};
	$linear    = $shared( 'Solve linear equations', 'algebra', 7, 7 );
	$expand    = $shared( 'Expand brackets', 'algebra', 8, 8 );
	$quadratic = $shared( 'Solve quadratic equations', 'algebra', 9, 9 );
	$systems   = $shared( 'Solve simultaneous equations', 'algebra', 9, 9 );
	$triangle  = $shared( 'Area of a triangle', 'geometry', 6, 7 );
	$pythag    = $shared( 'Pythagoras theorem', 'geometry', 8, 8 );
	$integers  = $shared( 'Order integers', 'number', 6, 6 );
	ok( true === SkillEdges::add_prerequisite( $quadratic, $linear ) && true === SkillEdges::add_prerequisite( $quadratic, $expand ) && true === SkillEdges::add_prerequisite( $systems, $linear ) && true === SkillEdges::add_prerequisite( $pythag, $triangle ), 'prerequisites saved' );
	ok( true === SkillEdges::add_encompass( $quadratic, $linear, 0.5 ) && true === SkillEdges::add_encompass( $quadratic, $expand, 0.6 ), 'includes links saved' );

	// ---- A syllabus whose objectives map onto the library ----
	$item = Items::create( array( 'name' => 'Grade 9 ' . $tag, 'is_syllabus' => true ) ); $syllabus = (int) $item['id']; $items[] = $syllabus;
	update_option( Comparison::OPTION, array_merge( (array) $before, array( $subject => array( $syllabus ) ) ) );
	$group      = Syllabus::add_group( $syllabus, array( 'name' => 'Equations' ) );
	$objectives = array();
	foreach ( array( 'Linear equations', 'Quadratic equations', 'Read bar charts' ) as $index => $name ) {
		$term = Syllabus::create_skill( $syllabus, $group, array( 'name' => $name . ' ' . $tag, 'code' => '9.' . ( $index + 1 ) ) );
		ok( ! is_wp_error( $term ), 'objective created' );
		$terms[] = $term['term_id']; $objectives[] = (int) $term['term_id'];
	}
	ok( ! is_wp_error( SkillMappings::save( $objectives[0], $linear, 'equivalent' ) ) && ! is_wp_error( SkillMappings::save( $objectives[1], $quadratic, 'equivalent' ) ), 'objectives mapped' );

	// ---- A learner: states are derived rows, so the fixture writes them directly ----
	$learner = wp_create_user( 'guidance-' . $tag, wp_generate_password(), 'guidance-' . $tag . '@example.org' ); $users[] = $learner;
	$other   = wp_create_user( 'guidance-other-' . $tag, wp_generate_password(), 'guidance-other-' . $tag . '@example.org' ); $users[] = $other;
	$state   = static function ( $term, $level, $days_ago, $struggling = 0 ) use ( $wpdb, $learner ) {
		$wpdb->insert( AssessmentSchema::table( 'student_skill_state' ), array( 'student_id' => $learner, 'term_id' => $term, 'level' => $level, 'policy' => 'evidence', 'evidence_count' => 4, 'struggling' => $struggling, 'last_evidence_at' => gmdate( 'Y-m-d H:i:s', time() - $days_ago * DAY_IN_SECONDS ), 'updated_at' => current_time( 'mysql', true ) ) );
	};
	$state( $objectives[0], 'proficient', 40 );  // Linear, through the objective: review was due 10 days ago.
	$state( $expand, 'proficient', 20 );         // Fresh.
	$state( $quadratic, 'familiar', 1 );         // Recent work that includes linear (0.5) and expanding (0.6).
	$state( $triangle, 'attempted', 2, 1 );      // Struggling.
	$state( $integers, 'proficient', 50 );       // Due, nothing covers it.
	Guidance::flush();
	$guidance = Snapshot::guidance( $learner, 10 );

	// Strength by area.
	$areas = array_column( $guidance['areas'], null, 'domain' );
	ok( array( 'number', 'algebra', 'geometry' ) === array_column( $guidance['areas'], 'domain' ), 'areas, strongest first: ' . wp_json_encode( $guidance['areas'] ) );
	ok( 70 === $areas['algebra']['strength'] && 'steady' === $areas['algebra']['band'] && 3 === $areas['algebra']['started'] && 8 === $areas['algebra']['grade'] && 'Grade 8' === $areas['algebra']['grade_label'] && 'Algebra' === $areas['algebra']['label'], 'algebra pools the objective onto its shared skill' );
	ok( 20 === $areas['geometry']['strength'] && 'building' === $areas['geometry']['band'] && 80 === $areas['number']['strength'], 'geometry and number strengths' );

	// Next steps, one sitting of about fifteen minutes: help, the review nothing covers, then the
	// started skill to keep going with. New skills wait for the next sitting but are listed as ready.
	$steps = $guidance['next_steps'];
	ok( array( 'help', 'review', 'continue' ) === array_column( $steps, 'type' ), 'next step order: ' . wp_json_encode( array_column( $steps, 'type' ) ) );
	ok( $triangle === $steps[0]['skill']['id'] && $integers === $steps[1]['skill']['id'] && $quadratic === $steps[2]['skill']['id'], 'the struggling skill, the overdue skill and the started skill' );
	ok( 13 === $guidance['session_minutes'] && 5 === $steps[0]['minutes'] && 3 === $steps[1]['minutes'] && '' !== $steps[2]['message'], 'the session adds up its minutes: ' . $guidance['session_minutes'] );
	$ready = $guidance['ready'];
	ok( $systems === $ready[0]['skill']['id'] && array( $linear ) === array_column( $ready[0]['prerequisites'], 'id' ) && '' !== $ready[0]['message'], 'a ready skill names what it builds on: ' . wp_json_encode( $ready ) );
	ok( ! in_array( $pythag, array_column( array_column( $ready, 'skill' ), 'id' ), true ), 'a skill whose prerequisite is still developing is not ready' );

	// Reviews covered by later work.
	ok( 1 === $guidance['reviews']['due'] && 1 === $guidance['reviews']['fresh'] && 1 === count( $guidance['reviews']['covered'] ), 'review counts: ' . wp_json_encode( $guidance['reviews'] ) );
	$covered = $guidance['reviews']['covered'][0];
	ok( $linear === $covered['skill']['id'] && 0.5 === $covered['credit'] && $quadratic === $covered['covered_by'][0]['id'] && strtotime( $covered['next_due_at'] . ' UTC' ) > time(), 'linear equations are kept fresh by quadratics: ' . wp_json_encode( $covered ) );
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT level, review_due FROM %i WHERE student_id=%d AND term_id=%d', AssessmentSchema::table( 'student_skill_state' ), $learner, $objectives[0] ), ARRAY_A );
	ok( 'proficient' === $row['level'] && 0 === (int) $row['review_due'], 'guidance never writes states' );

	// Readiness for the syllabus.
	$mine = null;
	foreach ( $guidance['readiness'] as $entry ) { if ( $syllabus === $entry['syllabus']['id'] ) { $mine = $entry; } }
	ok( $mine && 1 === count( $guidance['readiness'] ), 'only syllabuses the learner has started are listed: ' . wp_json_encode( $guidance['readiness'] ) );
	ok( 50 === $mine['percent'] && 3 === $mine['total'] && 1 === $mine['ready'] && 1 === $mine['learning'] && 1 === $mine['not_started'], 'readiness through the mapped objectives: ' . wp_json_encode( $mine ) );
	ok( array( $objectives[1], $objectives[2] ) === array_column( $mine['gaps'], 'id' ), 'gaps list the started objective first' );

	// A link removed changes the guidance on the next read.
	SkillEdges::remove( $quadratic, $linear, 'encompasses' );
	Guidance::flush();
	ok( 2 === Snapshot::guidance( $learner, 10 )['reviews']['due'], 'without the link the review is due again' );

	// ---- One subject at a time ----
	$other_subject = 'Physics ' . $tag;
	$force         = Comparison::save_shared( 0, array( 'name' => 'Forces ' . $tag, 'subject' => $other_subject, 'domain' => 'mechanics', 'grade_from' => 9, 'grade_to' => 9 ) );
	$terms[]       = (int) $force['id'];
	$state( (int) $force['id'], 'familiar', 1 );
	Guidance::flush();
	$all  = Snapshot::guidance( $learner, 10 );
	$only = Snapshot::guidance( $learner, 10, $subject );
	$phys = Snapshot::guidance( $learner, 10, $other_subject );
	ok( array( 'Mathematics ' . $tag, $other_subject ) === $all['subjects'] && '' === $all['subject'] && in_array( 'mechanics', array_column( $all['areas'], 'domain' ), true ), 'all subjects together: ' . wp_json_encode( $all['subjects'] ) );
	ok( $subject === $only['subject'] && ! in_array( 'mechanics', array_column( $only['areas'], 'domain' ), true ) && 1 === count( $only['readiness'] ), 'one subject leaves the others out' );
	ok( array( 'mechanics' ) === array_column( $phys['areas'], 'domain' ) && array() === $phys['readiness'] && array( 'continue' ) === array_column( $phys['next_steps'], 'type' ) && (int) $force['id'] === $phys['next_steps'][0]['skill']['id'], 'the other subject has only its own skill, to keep going with, and no syllabus' );

	// ---- A placement test feeds ready skills ----
	$wpdb->insert( AssessmentSchema::table( 'practice_sessions' ), array( 'uuid' => wp_generate_uuid4(), 'student_id' => $learner, 'mode' => 'placement', 'term_id' => 0, 'policy' => wp_json_encode( array( 'placement' => array( 'subject' => $subject, 'grade' => 8, 'graph' => array(), 'askable' => array(), 'result' => array( 'known' => array( $triangle ), 'unknown' => array(), 'unsure' => array(), 'domains' => array(), 'start' => array( $integers ) ) ) ) ), 'status' => 'complete', 'item_limit' => 5, 'started_at' => current_time( 'mysql', true ), 'completed_at' => current_time( 'mysql', true ) ) );
	$placement_session = (int) $wpdb->insert_id;
	Guidance::flush();
	$placed = Snapshot::guidance( $learner, 10, $subject );
	$ready  = $placed['ready'];
	ok( in_array( $pythag, array_column( array_column( $ready, 'skill' ), 'id' ), true ), 'a prerequisite the placement found known makes its skill ready: ' . wp_json_encode( array_column( array_column( $ready, 'skill' ), 'id' ) ) );
	$wpdb->delete( AssessmentSchema::table( 'practice_sessions' ), array( 'id' => $placement_session ) );

	// ---- Cold start: a new grade 7 learner with no placement test starts at grade 7 ----
	$newcomer = wp_create_user( 'guidance-new-' . $tag, wp_generate_password(), 'guidance-new-' . $tag . '@example.org' ); $users[] = $newcomer;
	Guidance::flush();
	ok( array() === Snapshot::guidance( $newcomer, 10, $subject )['next_steps'], 'without a grade or placement there is nowhere to start (as before)' );
	update_user_meta( $newcomer, \OhMyLMS\Student\LearnerProfile::META_GRADE, '7' );
	Guidance::flush();
	$start = Snapshot::guidance( $newcomer, 10, $subject );
	$ids   = array_column( array_column( $start['next_steps'], 'skill' ), 'id' );
	ok( array( 'ready', 'ready' ) === array_column( $start['next_steps'], 'type' ) && in_array( $linear, $ids, true ) && in_array( $triangle, $ids, true ) && ! in_array( $integers, $ids, true ) && ! in_array( $quadratic, $ids, true ), 'grade 7 skills that need nothing first, not grade 6 or 9: ' . wp_json_encode( $ids ) );

	// ---- Answers spread over two objectives of one shared skill are judged together ----
	$second = Syllabus::create_skill( $syllabus, $group, array( 'name' => 'Linear equations again ' . $tag, 'code' => '9.9' ) );
	$terms[] = (int) $second['term_id'];
	SkillMappings::save( (int) $second['term_id'], $linear, 'equivalent' );
	$pooler = wp_create_user( 'guidance-pool-' . $tag, wp_generate_password(), 'guidance-pool-' . $tag . '@example.org' ); $users[] = $pooler;
	$event  = 9300000 + wp_rand( 1, 99999 ) * 10;
	foreach ( array( array( $objectives[0], 'a' ), array( $objectives[0], 'b' ), array( (int) $second['term_id'], 'c' ) ) as $i => $answer ) {
		++$event;
		$wpdb->insert( AssessmentSchema::table( 'skill_evidence' ), array( 'grade_event_id' => $event, 'student_id' => $pooler, 'term_id' => $answer[0], 'part_id' => 'p1', 'role' => 'primary', 'awarded' => 1, 'available' => 1, 'independent' => 1, 'first_try' => 1, 'difficulty' => 'standard', 'family_id' => $answer[1], 'source_type' => 'practice', 'source_id' => $event, 'question_id' => $event, 'version_id' => 1, 'evidence_at' => gmdate( 'Y-m-d H:i:s', time() - 3600 + $i * 60 ), 'superseded' => 0 ) );
	}
	\OhMyLMS\Performance\Engine::recompute( $pooler, $objectives[0] );
	\OhMyLMS\Performance\Engine::recompute( $pooler, (int) $second['term_id'] );
	$alone = $wpdb->get_col( $wpdb->prepare( 'SELECT level FROM %i WHERE student_id=%d ORDER BY term_id', AssessmentSchema::table( 'student_skill_state' ), $pooler ) );
	ok( ! in_array( 'proficient', $alone, true ), 'neither objective alone is proficient: ' . wp_json_encode( $alone ) );
	Guidance::flush();
	ok( 'proficient' === Guidance::levels( $pooler )[ $linear ], 'three correct answers from three families across the two objectives make the shared skill proficient' );

	// ---- At most three skills in progress: no new skills beyond that ----
	$busy = wp_create_user( 'guidance-busy-' . $tag, wp_generate_password(), 'guidance-busy-' . $tag . '@example.org' ); $users[] = $busy;
	update_user_meta( $busy, \OhMyLMS\Student\LearnerProfile::META_GRADE, '7' );
	foreach ( array( $expand, $pythag, $integers ) as $i => $term ) {
		$wpdb->insert( AssessmentSchema::table( 'student_skill_state' ), array( 'student_id' => $busy, 'term_id' => $term, 'level' => 'familiar', 'policy' => 'evidence', 'evidence_count' => 2, 'last_evidence_at' => gmdate( 'Y-m-d H:i:s', time() - ( $i + 1 ) * 3600 ), 'updated_at' => current_time( 'mysql', true ) ) );
	}
	Guidance::flush();
	$full = Snapshot::guidance( $busy, 10, $subject );
	ok( array( 'continue', 'continue', 'continue' ) === array_column( $full['next_steps'], 'type' ) && array( $expand, $pythag, $integers ) === array_column( array_column( $full['next_steps'], 'skill' ), 'id' ), 'three skills in progress: keep going with them, most recent first, and nothing new: ' . wp_json_encode( array_column( $full['next_steps'], 'type' ) ) );
	ok( 15 === $full['session_minutes'], 'three keep-going steps fill one fifteen-minute sitting' );
	foreach ( array( $newcomer, $pooler, $busy ) as $id ) { $wpdb->delete( AssessmentSchema::table( 'skill_evidence' ), array( 'student_id' => $id ) ); }

	// ---- REST and permissions ----
	wp_set_current_user( $learner );
	$own = guidance_get( $learner, array( 'limit' => 2 ) );
	ok( 200 === $own->get_status() && 2 === count( $own->get_data()['next_steps'] ) && isset( $own->get_data()['ready'] ) && isset( $own->get_data()['areas'], $own->get_data()['readiness'], $own->get_data()['reviews'] ), 'a learner reads their own guidance: ' . wp_json_encode( $own->get_data() ) );
	wp_set_current_user( $other );
	ok( 403 === guidance_get( $learner )->get_status(), 'another learner cannot read it' );
	wp_set_current_user( 0 );
	ok( in_array( guidance_get( $learner )->get_status(), array( 401, 403 ), true ), 'guests cannot read it' );
	wp_set_current_user( $admin );
	ok( 200 === guidance_get( $learner )->get_status(), 'administrators read any learner' );
	ok( array( 'mechanics' ) === array_column( guidance_get( $learner, array( 'subject' => $other_subject ) )->get_data()['areas'], 'domain' ), 'the route narrows to a subject' );
	ok( isset( \OhMyLMS\MCP\Server::tools()['get_student_guidance'] ) && '/reports/skills/students/{student_id}/guidance' === \OhMyLMS\MCP\Server::tools()['get_student_guidance']['route'], 'the MCP server lists the guidance tool' );

	// ---- Exam mode: Preparing for a syllabus by a date ----
	$exam_day = gmdate( 'Y-m-d', time() + 40 * DAY_IN_SECONDS );
	ok( null === Guidance::target( $learner ) && null === Snapshot::guidance( $learner, 6, '' )['exam'], 'no exam until one is chosen' );
	ok( is_wp_error( Guidance::set_target( $learner, $linear, $exam_day ) ), 'a skill is not an exam' );
	ok( is_wp_error( Guidance::set_target( $learner, $syllabus, gmdate( 'Y-m-d', time() - 3 * DAY_IN_SECONDS ) ) ) && is_wp_error( Guidance::set_target( $learner, $syllabus, 'soon' ) ) && is_wp_error( Guidance::set_target( $learner, $syllabus, gmdate( 'Y-m-d', time() + 1200 * DAY_IN_SECONDS ) ) ), 'past, unreadable and far-off dates are refused' );
	ok( in_array( $syllabus, array_column( Guidance::targets(), 'id' ), true ), 'the syllabus is offered as an exam' );
	$saved = Guidance::set_target( $learner, $syllabus, $exam_day );
	ok( array( 'syllabus_id' => $syllabus, 'date' => $exam_day ) === $saved, 'the exam is stored' );
	$plan = Snapshot::guidance( $learner, 6, '' );
	ok( $plan['exam'] && 40 === $plan['exam']['days_left'] && 'learn' === $plan['exam']['phase'] && $syllabus === $plan['exam']['syllabus']['id'] && 2 === $plan['exam']['remaining'] && $plan['exam']['on_track'], 'the plan counts the two skills still to learn: ' . wp_json_encode( $plan['exam'] ) );
	ok( 7 === count( $plan['exam']['schedule'] ) && 1 === count( $plan['exam']['schedule'][0]['skills'] ), 'a week of one skill a day' );
	$today = $plan['exam']['schedule'][0]['skills'][0]['id'];
	$exam_steps = array_filter( $plan['next_steps'], static function ( $step ) { return 'exam' === ( $step['reason'] ?? '' ); } );
	ok( 1 === count( $exam_steps ) && $today === array_values( $exam_steps )[0]['skill']['id'] && 'help' === $plan['next_steps'][0]['type'], 'help comes first, then today\'s exam skill: ' . wp_json_encode( array_column( $plan['next_steps'], 'type' ) ) );
	ok( $plan['exam']['readiness']['percent'] === (int) floor( 100 * ( 1 + 1 + 0.5 + 0 ) / 4 ), 'readiness counts the pooled levels (both linear objectives count: ' . wp_json_encode( $plan['exam']['readiness'] ) . ')' );

	$route = static function ( $user, $method, array $params = array() ) use ( $learner ) {
		wp_set_current_user( $user );
		$request = new WP_REST_Request( $method, '/ohmylms/v1/reports/skills/students/' . $learner . '/exam-target' );
		$request->set_body_params( $params );
		return rest_do_request( $request );
	};
	ok( 200 === $route( $learner, 'GET' )->get_status() && $syllabus === $route( $learner, 'GET' )->get_data()['target']['syllabus_id'], 'the learner reads the exam' );
	ok( 403 === $route( $other, 'GET' )->get_status() && 403 === $route( $other, 'POST', array( 'syllabus_id' => $syllabus, 'date' => $exam_day ) )->get_status(), 'another learner can neither read nor set it' );
	ok( 400 === $route( $learner, 'POST', array( 'syllabus_id' => $syllabus, 'date' => 'soon' ) )->get_status() && 200 === $route( $learner, 'POST', array( 'syllabus_id' => $syllabus, 'date' => gmdate( 'Y-m-d', time() + 5 * DAY_IN_SECONDS ) ) )->get_status(), 'the learner sets a new date, bad dates are refused' );
	Guidance::flush();
	$week = Snapshot::guidance( $learner, 6, '' )['exam'];
	ok( 'review' === $week['phase'] && 5 === $week['days_left'] && 'review' === $week['schedule'][0]['phase'], 'the last week is for review: ' . wp_json_encode( $week['phase'] ) );
	if ( \OhMyLMS\Practice\Frontend::enabled() ) {
		wp_set_current_user( $learner );
		$html = \OhMyLMS\Practice\Frontend::skills();
		ok( false !== strpos( $html, 'Preparing for' ) && false !== strpos( $html, 'Grade 9 ' . $tag ) && false !== strpos( $html, 'name="exam_date"' ), 'My skills shows the Preparing for card with the plan' );
	}
	ok( 200 === $route( $learner, 'DELETE' )->get_status() && null === Guidance::target( $learner ) && null === Snapshot::guidance( $learner, 6, '' )['exam'], 'the exam can be removed' );
	wp_set_current_user( $admin );

	// ---- Who needs help now: stuck learners of a course, grouped by the prerequisite to reteach ----
	$class_course = wp_insert_post( array( 'post_type' => OHMYLMS_COURSE_CPT, 'post_status' => 'publish', 'post_title' => 'Help now ' . $tag, 'post_author' => $admin ) );
	foreach ( array( $learner, $other ) as $enrolled ) {
		$wpdb->insert( $wpdb->prefix . 'ohmylms_user_enrollment', array( 'user_id' => $enrolled, 'course_id' => $class_course, 'status' => 'enrolled', 'progress' => 'running', 'start_date' => current_time( 'mysql' ) ) );
	}
	Guidance::flush();
	$help = \OhMyLMS\Performance\HelpNow::for_students( array( $learner, $other, $learner ) );
	ok( 2 === $help['students'] && 1 === $help['stuck'] && 1 === count( $help['groups'] ) && array( $learner ) === array_column( $help['groups'][0]['students'], 'id' ), 'only the learner stuck on the triangle is listed, once: ' . wp_json_encode( $help ) );
	ok( in_array( $triangle, array_column( $help['groups'][0]['skills'], 'id' ), true ), 'the group names the skill they are stuck on' );
	// A learner stuck on more skills than fit one sitting still shows every one to the teacher.
	$many = wp_create_user( 'guidance-many-' . $tag, wp_generate_password(), 'guidance-many-' . $tag . '@example.org' ); $users[] = $many;
	foreach ( array( $expand, $pythag, $integers, $triangle ) as $i => $term ) {
		$wpdb->insert( AssessmentSchema::table( 'student_skill_state' ), array( 'student_id' => $many, 'term_id' => $term, 'level' => 'attempted', 'policy' => 'evidence', 'evidence_count' => 4, 'struggling' => 1, 'last_evidence_at' => gmdate( 'Y-m-d H:i:s', time() - ( $i + 1 ) * 3600 ), 'updated_at' => current_time( 'mysql', true ) ) );
	}
	Guidance::flush();
	$all = Snapshot::guidance( $many, 20, '' );
	ok( 4 === count( $all['stuck'] ) && count( $all['next_steps'] ) < 4, 'stuck lists all four skills while one sitting holds fewer: ' . count( $all['next_steps'] ) );
	$seen = \OhMyLMS\Performance\HelpNow::for_students( array( $many ) );
	ok( 1 === $seen['stuck'] && 4 === array_sum( array_map( static function ( $group ) { return count( $group['skills'] ); }, $seen['groups'] ) ), 'the teacher view groups all four stuck skills: ' . wp_json_encode( $seen['groups'] ) );
	$helpnow = static function ( $user, $course ) {
		wp_set_current_user( $user );
		$request = new WP_REST_Request( 'GET', '/ohmylms/v1/reports/skills/help-now' );
		$request->set_query_params( array( 'course_id' => $course ) );
		return rest_do_request( $request );
	};
	$response = $helpnow( $admin, $class_course );
	ok( 200 === $response->get_status() && 1 === $response->get_data()['stuck'], 'a teacher with access reads the view' );
	ok( 403 === $helpnow( $other, $class_course )->get_status() && in_array( $helpnow( 0, $class_course )->get_status(), array( 401, 403 ), true ) && 400 === $helpnow( $admin, 0 )->get_status(), 'learners and guests cannot, and a course is required' );
	wp_set_current_user( $admin );
	wp_delete_post( $class_course, true );
	$wpdb->delete( $wpdb->prefix . 'ohmylms_user_enrollment', array( 'course_id' => $class_course ) );

	// ---- My skills ----
	if ( \OhMyLMS\Practice\Frontend::enabled() ) {
		wp_set_current_user( $learner );
		Guidance::flush();
		$html = \OhMyLMS\Practice\Frontend::skills();
		ok( false !== strpos( $html, 'Strength by area' ) && false !== strpos( $html, 'Readiness' ) && false !== strpos( $html, 'Suggested next steps' ) && false !== strpos( $html, 'Grade 9 ' . $tag ), 'My skills shows the guidance' );
		ok( false === strpos( $html, '<script' ), 'My skills guidance adds no inline script' );
		wp_set_current_user( $admin );
	}
	echo $checks . " guidance integration checks passed.\n";
} finally {
	wp_set_current_user( $admin );
	update_option( Comparison::OPTION, $before );
	if ( null === $rules_before ) { delete_option( Mastery::OPTION ); } else { update_option( Mastery::OPTION, $rules_before ); }
	foreach ( $users as $id ) { $wpdb->delete( AssessmentSchema::table( 'student_skill_state' ), array( 'student_id' => $id ) ); }
	foreach ( $terms as $id ) { $wpdb->delete( SkillMappings::table(), array( 'specific_term_id' => $id ) ); $wpdb->delete( SkillEdges::table(), array( 'skill_id' => $id ) ); $wpdb->delete( SkillEdges::table(), array( 'other_id' => $id ) ); }
	foreach ( $items as $id ) { $item = Items::get( $id ); if ( $item ) { $course = $item['course_id']; $root = $item['skill_root_id']; Items::delete( $id, 'delete', true ); if ( $course ) { wp_delete_post( $course, true ); } if ( $root ) { wp_delete_term( $root, Taxonomy::NAME ); } } }
	foreach ( $terms as $id ) { wp_delete_term( $id, Taxonomy::NAME ); }
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $users as $id ) { if ( $id && ! is_wp_error( $id ) ) { wp_delete_user( $id ); } }
}
