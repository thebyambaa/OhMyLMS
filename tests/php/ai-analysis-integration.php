<?php
/** AI syllabus analysis: skill placement, runs in slices, suggestions, review and permissions. Isolated site only. */
if ( PHP_SAPI !== 'cli' ) { exit; }
$config = json_decode( file_get_contents( getenv( 'OHMYLMS_TEST_CREDENTIALS' ) ), true );
$_SERVER['HTTP_HOST'] = '127.0.0.1:8100';
require $config['site'] . '/wp-load.php';
if ( ! defined( 'OHMYLMS_TEST_SITE' ) || ! in_array( DB_NAME, array( 'ohmylms_source_test', 'ohmylms_streak_test' ), true ) ) { throw new RuntimeException( 'Requires an isolated database.' ); }

use OhMyLMS\AI\Secrets;
use OhMyLMS\AI\Settings as AiSettings;
use OhMyLMS\Curriculum\AiAnalysis;
use OhMyLMS\Curriculum\Comparison;
use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\Schema;
use OhMyLMS\Curriculum\SkillMappings;
use OhMyLMS\Curriculum\Syllabus;
use OhMyLMS\Skills\Taxonomy;

$checks = 0; $items = array(); $terms = array(); $runs = array(); $users = array();
$before = get_option( Comparison::OPTION, array() );
$ai_before = get_option( AiSettings::OPTION, null );
$admin = get_user_by( 'login', $config['username'] )->ID;
$tag = wp_generate_password( 6, false, false );
function ok( $value, $message ) { global $checks; if ( ! $value ) { throw new RuntimeException( $message ); } ++$checks; }
function call_api( $method, $path, array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/syllabus-comparison/' . $path );
	if ( 'GET' === $method ) { $request->set_query_params( $data ); }
	else { $request->set_header( 'Content-Type', 'application/json' ); $request->set_body( wp_json_encode( $data ) ); }
	return rest_do_request( $request );
}

// A fake provider: places objectives and judges candidates from the prompt it is sent.
$calls = array( 'classify' => 0, 'match' => 0, 'links' => 0, 'fail' => false );
$answer = static function ( $body ) use ( &$calls ) {
	$system = (string) $body['system'];
	$user   = (string) $body['messages'][0]['content'];
	if ( false !== strpos( $system, 'curriculum analyst' ) ) {
		++$calls['classify'];
		preg_match_all( '/^id: (\d+) \| code: [^|]* \| topic: [^|]* \| name: (.*)$/m', $user, $found, PREG_SET_ORDER );
		$results = array();
		foreach ( $found as $row ) {
			$geometry  = (bool) preg_match( '/triangle|area/i', $row[2] );
			$results[] = array( 'id' => (int) $row[1], 'domain' => $geometry ? 'geometry' : 'algebra', 'grade_from' => 7, 'grade_to' => $geometry ? 7 : 8, 'gloss' => $geometry ? 'Find the area of a triangle' : 'Solve linear equations in one variable', 'confidence' => 0.9 );
		}
		return array( 'results' => $results );
	}
	if ( false !== strpos( $system, 'dependency graph' ) ) {
		++$calls['links'];
		$results = array();
		foreach ( preg_split( '/(?=^Skill \d+: )/m', $user ) as $block ) {
			if ( ! preg_match( '/^Skill (\d+): ([^|]*)/', $block, $head ) ) { continue; }
			preg_match_all( '/^  - (\d+): ([^|]*)/m', $block, $candidates, PREG_SET_ORDER );
			$ids = array(); foreach ( $candidates as $candidate ) { $ids[ trim( $candidate[2] ) ] = $candidate[1]; }
			$row = array( 'id' => (int) $head[1], 'prerequisites' => array(), 'encompasses' => array() );
			$linear = null; $expand = null;
			foreach ( $ids as $name => $id ) { if ( 0 === strpos( $name, 'Solve linear equations' ) ) { $linear = $id; } if ( 0 === strpos( $name, 'Expand brackets' ) ) { $expand = $id; } }
			if ( false !== strpos( $head[2], 'quadratic' ) && $linear ) {
				$row['prerequisites'][] = array( 'skill' => $linear, 'confidence' => 0.9, 'reason' => 'Needs linear equations first.' );
				$row['encompasses'][]   = array( 'skill' => $linear, 'weight' => 0.5, 'confidence' => 0.8, 'reason' => 'Uses them while solving.' );
				if ( $expand ) { $row['encompasses'][] = array( 'skill' => $expand, 'weight' => 0.6, 'confidence' => 0.85, 'reason' => 'Expanding is part of it.' ); }
			}
			if ( false !== strpos( $head[2], 'Expand brackets' ) && $linear ) {
				$row['prerequisites'][] = array( 'skill' => $linear, 'confidence' => 0.85, 'reason' => 'Needs linear equations first.' );
			}
			$results[] = $row;
		}
		return array( 'results' => $results );
	}
	++$calls['match'];
	$results = array();
	foreach ( preg_split( '/(?=^Objective \d+ )/m', $user ) as $block ) {
		if ( ! preg_match( '/^Objective (\d+) \[[^\]]*\] (.*?) \| topic/', $block, $head ) ) { continue; }
		preg_match_all( '/^  - (n?\d+): ([^|]*)/m', $block, $candidates, PREG_SET_ORDER );
		$row = array( 'id' => (int) $head[1], 'matches' => array(), 'new_skill' => null );
		foreach ( $candidates as $candidate ) {
			if ( false !== stripos( $candidate[2], 'Solve linear equations' ) && preg_match( '/тэгшитгэл|equation/iu', $head[2] ) ) {
				$row['matches'][] = array( 'skill' => $candidate[1], 'relation' => 'equivalent', 'confidence' => 0.95, 'reason' => 'Same ability.' );
			} elseif ( 'n' === $candidate[1][0] && preg_match( '/triangle/i', $head[2] ) ) {
				$row['matches'][] = array( 'skill' => $candidate[1], 'relation' => 'equivalent', 'confidence' => 0.85, 'reason' => 'Proposed earlier.' );
			}
		}
		if ( ! $row['matches'] && preg_match( '/triangle/i', $head[2] ) ) {
			$row['new_skill'] = array( 'name' => 'Area of a triangle ' . $GLOBALS['tag'], 'description' => 'Find the area from base and height.', 'topic' => 'Area', 'domain' => 'geometry', 'grade_from' => 6, 'grade_to' => 7, 'confidence' => 0.8, 'reason' => 'No skill covers it.' );
		}
		$results[] = $row;
	}
	return array( 'results' => $results );
};

try {
	wp_set_current_user( $admin );
	Schema::install();
	ok( Schema::ready() && $GLOBALS['wpdb']->get_var( "SHOW COLUMNS FROM " . SkillMappings::table() . " LIKE 'source'" ), 'schema 8 adds mapping provenance' );

	// ---- Domain and grade-band metadata on shared skills ----
	$placement = call_api( 'GET', 'skill-placement' )->get_data();
	ok( isset( $placement['domains']['algebra'], $placement['domains']['calculus'] ) && 13 === count( $placement['grades'] ) && 'Beyond grade 12' === $placement['grades'][12]['label'] && false === $placement['ai_available'], 'placement options: ' . wp_json_encode( $placement ) );
	$linear = Comparison::save_shared( 0, array( 'name' => 'Solve linear equations ' . $tag, 'subject' => 'Mathematics ' . $tag, 'topic' => 'Equations' ) );
	ok( ! is_wp_error( $linear ) && '' === $linear['domain'] && 0 === $linear['grade_from'], 'metadata is optional' );
	$terms[] = $linear['id'];
	$quad = call_api( 'POST', 'shared-skills/save', array( 'name' => 'Solve quadratic equations ' . $tag, 'subject' => 'Mathematics ' . $tag, 'domain' => 'algebra', 'grade_from' => 9 ) )->get_data();
	$terms[] = $quad['id'];
	ok( 'algebra' === $quad['domain'] && 9 === $quad['grade_from'] && 9 === $quad['grade_to'], 'a single grade is a band of one: ' . wp_json_encode( $quad ) );
	ok( 400 === call_api( 'POST', 'shared-skills/save', array( 'name' => 'Bad ' . $tag, 'domain' => 'astrology' ) )->get_status(), 'unknown domains are rejected' );
	ok( 400 === call_api( 'POST', 'shared-skills/save', array( 'name' => 'Bad ' . $tag, 'grade_from' => 9, 'grade_to' => 7 ) )->get_status() && 400 === call_api( 'POST', 'shared-skills/save', array( 'name' => 'Bad ' . $tag, 'grade_from' => 14 ) )->get_status(), 'bad grade bands are rejected' );
	$edited = Comparison::save_shared( $quad['id'], array( 'name' => $quad['name'], 'subject' => 'Mathematics ' . $tag, 'expected_revision' => $quad['revision'] ) );
	ok( 'algebra' === $edited['domain'] && 9 === $edited['grade_to'], 'an edit without metadata keeps it' );
	ok( 1 === count( Comparison::shared_skills( array( 'search' => $tag, 'domain' => 'algebra' ) )['items'] ) && 1 === count( Comparison::shared_skills( array( 'search' => $tag, 'grade' => 9 ) )['items'] ) && 0 === count( Comparison::shared_skills( array( 'search' => $tag, 'grade' => 5 ) )['items'] ), 'skills filter by domain and by a grade in their band' );

	// ---- A syllabus to analyse ----
	$item = Items::create( array( 'name' => 'Grade 7 ' . $tag, 'is_syllabus' => true ) ); $syllabus = (int) $item['id']; $items[] = $syllabus;
	update_option( Comparison::OPTION, array_merge( (array) $before, array( 'Mathematics ' . $tag => array( $syllabus ) ) ) );
	$group = Syllabus::add_group( $syllabus, array( 'name' => 'Algebra and geometry' ) );
	$names = array( 'Шугаман тэгшитгэл бодох', 'Find the area of a triangle', 'Triangle area problems', 'Collect like terms', 'Use a protractor', 'Order integers', 'Read bar charts', 'Convert units', 'Triangle area with coordinates' );
	$objectives = array();
	foreach ( $names as $index => $name ) {
		$term = Syllabus::create_skill( $syllabus, $group, array( 'name' => $name . ' ' . $tag, 'code' => '7.' . ( $index + 1 ) ) );
		ok( ! is_wp_error( $term ), 'objective created' );
		$terms[] = $term['term_id']; $objectives[] = (int) $term['term_id'];
	}

	// No provider yet: nothing starts.
	ok( 503 === call_api( 'POST', 'ai/runs', array( 'kind' => 'syllabus', 'syllabus_id' => $syllabus ) )->get_status(), 'runs need an AI connection' );
	update_option( AiSettings::OPTION, array( 'enabled' => false, 'provider' => 'anthropic', 'model' => 'test-model', 'key' => Secrets::seal( 'test-key' ), 'daily_limit' => 30, 'max_tokens' => 400, 'timeout' => 5 ) );
	ok( AiAnalysis::available(), 'the shared connection is enough; the tutor switch is not needed' );
	remove_all_filters( 'pre_http_request' );
	$sent = array();
	add_filter( 'pre_http_request', static function ( $pre, $args, $url ) use ( $answer, &$sent, &$calls ) {
		if ( false === strpos( $url, 'api.anthropic.com' ) ) { return new WP_Error( 'blocked', 'External HTTP is blocked' ); }
		$body   = json_decode( $args['body'], true );
		$sent[] = $body;
		if ( $calls['fail'] ) { return array( 'response' => array( 'code' => 500, 'message' => 'Error' ), 'headers' => array(), 'body' => '{"type":"error","error":{"message":"down"}}' ); }
		return array( 'response' => array( 'code' => 200, 'message' => 'OK' ), 'headers' => array(), 'body' => wp_json_encode( array( 'content' => array( array( 'type' => 'text', 'text' => "```json\n" . wp_json_encode( $answer( $body ), JSON_UNESCAPED_UNICODE ) . "\n```" ) ), 'usage' => array( 'input_tokens' => 10, 'output_tokens' => 10 ) ) ) );
	}, 1000, 3 );

	// ---- A run over the syllabus, in slices ----
	$started = call_api( 'POST', 'ai/runs', array( 'kind' => 'syllabus', 'syllabus_id' => $syllabus ) );
	ok( 200 === $started->get_status(), 'run started: ' . wp_json_encode( $started->get_data() ) );
	$run = $started->get_data(); $runs[] = $run['id'];
	ok( 'queued' === $run['status'] && 9 === $run['total'] && 'Mathematics ' . $tag === $run['subject'] && 'test-model' === $run['model'], 'run state: ' . wp_json_encode( $run ) );
	ok( 409 === call_api( 'POST', 'ai/runs', array( 'kind' => 'syllabus', 'syllabus_id' => $syllabus ) )->get_status(), 'one active run per syllabus' );
	ok( (bool) wp_next_scheduled( AiAnalysis::HOOK, array( $run['id'] ) ), 'WP-Cron is the fallback' );
	$run = call_api( 'POST', 'ai/runs/' . $run['id'] . '/step' )->get_data();
	ok( 'running' === $run['status'] && 8 === $run['processed'] && 1 === $calls['classify'] && 1 === $calls['match'], 'the first slice is eight objectives in two calls: ' . wp_json_encode( $run ) );
	ok( 6000 >= $sent[0]['max_tokens'] && $sent[0]['max_tokens'] > 400, 'analysis uses the longer administrator budget' );
	ok( false !== strpos( $sent[0]['messages'][0]['content'], 'Grade 7 ' . $tag ) && false !== strpos( $sent[1]['messages'][0]['content'], '- ' . $linear['id'] . ': Solve linear equations' ), 'the syllabus is context and the library supplies candidates' );
	ok( 'algebra' === get_term_meta( $objectives[0], AiAnalysis::HINT_DOMAIN, true ) && 'Solve linear equations in one variable' === get_term_meta( $objectives[0], AiAnalysis::HINT_GLOSS, true ) && 7 === (int) get_term_meta( $objectives[1], AiAnalysis::HINT_FROM, true ), 'objectives keep the estimated placement and English meaning' );
	$run = AiAnalysis::step( $run['id'] );
	ok( 'done' === $run['status'] && 9 === $run['processed'] && false !== strpos( $sent[3]['messages'][0]['content'], ': Area of a triangle ' . $tag ), 'the last slice sees the skill proposed earlier: ' . wp_json_encode( $run ) );

	$list = call_api( 'GET', 'ai/suggestions', array( 'run_id' => $run['id'] ) )->get_data();
	$by = array(); foreach ( $list['items'] as $row ) { $by[ $row['objective']['id'] ][] = $row; }
	ok( 4 === $list['total'], 'four suggestions: ' . wp_json_encode( $list ) );
	ok( 'new_skill' === $list['items'][0]['kind'] && 'Area of a triangle ' . $tag === $list['items'][0]['proposal']['name'] && 'geometry' === $list['items'][0]['proposal']['domain'], 'new skills come first' );
	ok( 'mapping' === $by[ $objectives[0] ][0]['kind'] && $linear['id'] === $by[ $objectives[0] ][0]['shared']['id'] && 0.95 === $by[ $objectives[0] ][0]['confidence'], 'the Mongolian objective maps to the English skill through its English meaning' );
	ok( 'mapping' === $by[ $objectives[2] ][0]['kind'] && $list['items'][0]['id'] === $by[ $objectives[2] ][0]['proposal']['id'], 'a second objective proposing the same skill in one slice points to the first proposal' );
	ok( 'mapping' === $by[ $objectives[8] ][0]['kind'] && 'pending' === $by[ $objectives[8] ][0]['proposal']['status'], 'a later slice maps to the proposal' );
	ok( ! isset( $by[ $objectives[3] ] ), 'no weak suggestions for unrelated objectives' );

	// ---- Review ----
	$waiting = call_api( 'POST', 'ai/suggestions/review', array( 'ids' => array( $by[ $objectives[8] ][0]['id'] ), 'action' => 'accept' ) )->get_data();
	ok( isset( $waiting['results'][ $by[ $objectives[8] ][0]['id'] ]['error'] ), 'a mapping to an unaccepted proposal waits' );
	$accepted = call_api( 'POST', 'ai/suggestions/review', array( 'ids' => array( $list['items'][0]['id'] ), 'action' => 'accept', 'overrides' => array( $list['items'][0]['id'] => array( 'grade_from' => 6, 'grade_to' => 8 ) ) ) )->get_data();
	$new_skill = $accepted['results'][ $list['items'][0]['id'] ];
	ok( 'accepted' === $new_skill['status'] && $new_skill['result_id'] > 0, 'accepting a proposal creates the skill: ' . wp_json_encode( $accepted ) );
	$terms[] = $new_skill['result_id'];
	$created = Comparison::shared_skill( $new_skill['result_id'] );
	ok( 'geometry' === $created['domain'] && 6 === $created['grade_from'] && 8 === $created['grade_to'] && 'Mathematics ' . $tag === $created['subject'], 'the new skill carries the reviewer\'s grade band: ' . wp_json_encode( $created ) );
	$row = $GLOBALS['wpdb']->get_row( $GLOBALS['wpdb']->prepare( 'SELECT * FROM %i WHERE specific_term_id=%d AND shared_term_id=%d', SkillMappings::table(), $objectives[1], $new_skill['result_id'] ), ARRAY_A );
	ok( $row && 'equivalent' === $row['relation'] && 'ai' === $row['source'] && 0.8 === (float) $row['confidence'] && false !== strpos( $row['note'], 'Suggested by AI (80%)' ), 'the objective is mapped and the mapping says AI suggested it: ' . wp_json_encode( $row ) );
	$confident = call_api( 'POST', 'ai/runs/' . $run['id'] . '/accept-confident', array( 'min_confidence' => 0.8 ) )->get_data();
	ok( 3 === $confident['accepted'] && 0 === $confident['failed'], 'accepting the confident ones: ' . wp_json_encode( $confident ) );
	ok( 3 === count( Comparison::shared_skill( $new_skill['result_id'] )['mappings']['mapped_from'] ) && 1 === count( Comparison::shared_skill( $linear['id'] )['mappings']['mapped_from'] ), 'the proposal mappings resolved to the created skill' );
	ok( 400 === call_api( 'POST', 'ai/runs/' . $run['id'] . '/accept-confident', array( 'min_confidence' => 0.2 ) )->get_status(), 'a low threshold is refused' );
	ok( 0 === call_api( 'GET', 'ai/suggestions', array( 'run_id' => $run['id'] ) )->get_data()['total'] && 4 === AiAnalysis::run( $run['id'] )['accepted'], 'nothing is left pending' );

	// ---- Already mapped, re-analysis, failures, cancel ----
	$empty = call_api( 'POST', 'ai/runs', array( 'kind' => 'syllabus', 'syllabus_id' => $syllabus ) );
	ok( 200 === $empty->get_status() && 5 === $empty->get_data()['total'], 'only unmapped objectives are analysed again: ' . wp_json_encode( $empty->get_data() ) );
	$runs[] = $empty->get_data()['id'];
	$calls['fail'] = true;
	foreach ( array( 1, 2 ) as $try ) {
		$failing = AiAnalysis::step( $empty->get_data()['id'] );
		ok( 'running' === $failing['status'] && 0 === $failing['processed'] && '' !== $failing['error'], 'a failed slice is retried' );
	}
	$failing = AiAnalysis::step( $empty->get_data()['id'] );
	ok( 'done' === $failing['status'] && 5 === $failing['failures'], 'after three tries the slice is given up: ' . wp_json_encode( $failing ) );
	$calls['fail'] = false;
	$again = call_api( 'POST', 'ai/runs', array( 'kind' => 'syllabus', 'syllabus_id' => $syllabus, 'include_mapped' => true ) )->get_data();
	$runs[] = $again['id'];
	ok( 9 === $again['total'], 'mapped objectives can be analysed again on request' );
	$cancelled = call_api( 'POST', 'ai/runs/' . $again['id'] . '/cancel' )->get_data();
	ok( 'cancelled' === $cancelled['status'] && ! wp_next_scheduled( AiAnalysis::HOOK, array( $again['id'] ) ) && 'cancelled' === AiAnalysis::step( $again['id'] )['status'], 'a cancelled run stops' );

	// ---- Placement of shared skills ----
	$placing = call_api( 'POST', 'ai/runs', array( 'kind' => 'placement', 'subject' => 'Mathematics ' . $tag ) )->get_data();
	$runs[] = $placing['id'];
	ok( 1 === $placing['total'], 'only skills without a domain or grades are placed: ' . wp_json_encode( $placing ) );
	AiAnalysis::step( $placing['id'] );
	$suggestion = call_api( 'GET', 'ai/suggestions', array( 'run_id' => $placing['id'], 'kind' => 'placement' ) )->get_data()['items'][0];
	ok( 'placement' === $suggestion['kind'] && $linear['id'] === $suggestion['shared']['id'] && 'algebra' === $suggestion['placement']['domain'], 'a placement suggestion: ' . wp_json_encode( $suggestion ) );
	$rejected = call_api( 'POST', 'ai/suggestions/review', array( 'ids' => array( $suggestion['id'] ), 'action' => 'reject' ) )->get_data();
	ok( 'rejected' === $rejected['results'][ $suggestion['id'] ]['status'] && '' === Comparison::shared_skill( $linear['id'] )['domain'], 'rejecting changes nothing' );
	ok( 'rejected' === call_api( 'POST', 'ai/suggestions/review', array( 'ids' => array( $suggestion['id'] ), 'action' => 'accept' ) )->get_data()['results'][ $suggestion['id'] ]['status'], 'a reviewed suggestion stays reviewed' );
	$placing = call_api( 'POST', 'ai/runs', array( 'kind' => 'placement', 'subject' => 'Mathematics ' . $tag ) )->get_data(); $runs[] = $placing['id'];
	AiAnalysis::step( $placing['id'] );
	$suggestion = AiAnalysis::suggestions( array( 'run_id' => $placing['id'] ) )['items'][0];
	AiAnalysis::review( array( $suggestion['id'] ), 'accept' );
	$placed = Comparison::shared_skill( $linear['id'] );
	ok( 'algebra' === $placed['domain'] && 7 === $placed['grade_from'] && 8 === $placed['grade_to'], 'accepting a placement stores it: ' . wp_json_encode( $placed ) );
	ok( 400 === call_api( 'POST', 'ai/suggestions/review', array( 'ids' => array(), 'action' => 'accept' ) )->get_status() && 400 === call_api( 'POST', 'ai/suggestions/review', array( 'ids' => array( 1 ), 'action' => 'maybe' ) )->get_status(), 'review input is checked' );
	ok( 400 === call_api( 'POST', 'ai/runs', array( 'kind' => 'nonsense' ) )->get_status() && 404 === call_api( 'POST', 'ai/runs', array( 'kind' => 'syllabus', 'syllabus_id' => 999999 ) )->get_status(), 'run input is checked' );
	ok( count( call_api( 'GET', 'ai/runs', array( 'syllabus_id' => $syllabus ) )->get_data() ) === 3, 'runs list by syllabus' );

	// ---- Links between shared skills ----
	$expand = Comparison::save_shared( 0, array( 'name' => 'Expand brackets ' . $tag, 'subject' => 'Mathematics ' . $tag, 'domain' => 'algebra', 'grade_from' => 8 ) ); $terms[] = $expand['id'];
	$unplaced = Comparison::save_shared( 0, array( 'name' => 'Unplaced skill ' . $tag, 'subject' => 'Mathematics ' . $tag ) ); $terms[] = $unplaced['id'];
	$edges_before = $calls['links'];
	$link_run = call_api( 'POST', 'ai/runs', array( 'kind' => 'links', 'subject' => 'Mathematics ' . $tag ) );
	ok( 200 === $link_run->get_status(), 'a links run starts: ' . wp_json_encode( $link_run->get_data() ) );
	$link_run = $link_run->get_data(); $runs[] = $link_run['id'];
	ok( 4 === $link_run['total'], 'only placed skills are linked (the unplaced one waits): ' . wp_json_encode( $link_run ) );
	$link_run = AiAnalysis::step( $link_run['id'] );
	ok( 'done' === $link_run['status'] && 1 === $calls['links'] - $edges_before, 'one call for the slice: ' . wp_json_encode( $link_run ) );
	$pending = call_api( 'GET', 'ai/suggestions', array( 'run_id' => $link_run['id'], 'kind' => 'link' ) )->get_data();
	ok( 4 === $pending['total'], 'four link suggestions: ' . wp_json_encode( $pending ) );
	$by_type = array(); foreach ( $pending['items'] as $row ) { $by_type[ $row['relation'] ][] = $row; }
	ok( 2 === count( $by_type['prerequisite'] ) && 2 === count( $by_type['encompasses'] ), 'two prerequisites and two encompass links' );
	$first = $by_type['encompasses'][0];
	ok( $quad['id'] === $first['skill']['id'] && in_array( $first['weight'], array( 0.5, 0.6 ), true ) && ! empty( $first['shared']['name'] ), 'a link suggestion names both skills and the weight: ' . wp_json_encode( $first ) );
	$rejected_link = $by_type['encompasses'][1];
	call_api( 'POST', 'ai/suggestions/review', array( 'ids' => array( $rejected_link['id'] ), 'action' => 'reject' ) );
	$accepted_link = $by_type['encompasses'][0];
	$result = call_api( 'POST', 'ai/suggestions/review', array( 'ids' => array( $accepted_link['id'] ), 'action' => 'accept', 'overrides' => array( $accepted_link['id'] => array( 'weight' => 0.7 ) ) ) )->get_data();
	ok( 'accepted' === $result['results'][ $accepted_link['id'] ]['status'], 'an encompass link is accepted: ' . wp_json_encode( $result ) );
	$accepted_all = call_api( 'POST', 'ai/runs/' . $link_run['id'] . '/accept-confident', array( 'min_confidence' => 0.8 ) )->get_data();
	ok( 2 === $accepted_all['accepted'] && 0 === $accepted_all['failed'], 'the confident prerequisites are accepted: ' . wp_json_encode( $accepted_all ) );
	$links = call_api( 'GET', 'skill-links/' . $quad['id'] )->get_data();
	ok( $linear['id'] === $links['prerequisites'][0]['id'] && 'ai' === $links['prerequisites'][0]['source'] && 0.9 === $links['prerequisites'][0]['confidence'], 'the prerequisite joins the skill and records AI: ' . wp_json_encode( $links ) );
	ok( \OhMyLMS\Skills\Taxonomy::prerequisites( $quad['id'] ) === array( $linear['id'] ), 'the skill\'s own prerequisite list is the source of truth recommendations read' );
	ok( 1 === count( $links['encompasses'] ) && 0.7 === $links['encompasses'][0]['weight'] && $expand['id'] === $links['encompasses'][0]['id'], 'the reviewer\'s weight is kept: ' . wp_json_encode( $links['encompasses'] ) );
	ok( 1 === count( call_api( 'GET', 'skill-links/' . $expand['id'] )->get_data()['encompassed_by'] ), 'the other side lists what encompasses it' );
	ok( array( $quad['id'] => array( $expand['id'] => 0.7 ) ) === \OhMyLMS\Curriculum\SkillEdges::encompasses_many( array( $quad['id'], $linear['id'] ) ), 'the guidance engine reads weights in one query' );
	ok( 409 === call_api( 'POST', 'skill-links', array( 'skill_id' => $expand['id'], 'other_id' => $quad['id'], 'type' => 'encompasses', 'weight' => 0.5 ) )->get_status(), 'an encompass loop is refused' );
	ok( 400 === call_api( 'POST', 'skill-links', array( 'skill_id' => $linear['id'], 'other_id' => $quad['id'], 'type' => 'prerequisite' ) )->get_status(), 'a prerequisite loop is refused' );
	ok( 400 === call_api( 'POST', 'skill-links', array( 'skill_id' => $quad['id'], 'other_id' => $quad['id'], 'type' => 'encompasses', 'weight' => 1 ) )->get_status(), 'a skill cannot encompass itself' );
	ok( 400 === call_api( 'POST', 'skill-links', array( 'skill_id' => $quad['id'], 'other_id' => $linear['id'], 'type' => 'encompasses' ) )->get_status(), 'an encompass link needs a weight' );
	ok( 404 === call_api( 'POST', 'skill-links', array( 'skill_id' => $quad['id'], 'other_id' => $objectives[0], 'type' => 'prerequisite' ) )->get_status(), 'only shared skills are linked' );
	$manual = call_api( 'POST', 'skill-links', array( 'skill_id' => $quad['id'], 'other_id' => $expand['id'], 'type' => 'prerequisite' ) )->get_data();
	ok( 2 === count( $manual['prerequisites'] ) && 'manual' === $manual['prerequisites'][1]['source'], 'a manual prerequisite is recorded as manual: ' . wp_json_encode( $manual ) );
	$again_links = call_api( 'POST', 'ai/runs', array( 'kind' => 'links', 'subject' => 'Mathematics ' . $tag ) )->get_data(); $runs[] = $again_links['id'];
	ok( 2 === $again_links['total'], 'skills that already have prerequisites are skipped: ' . wp_json_encode( $again_links ) );
	call_api( 'POST', 'ai/runs/' . $again_links['id'] . '/cancel' );
	ok( 0 === call_api( 'GET', 'ai/suggestions', array( 'run_id' => $link_run['id'], 'kind' => 'link' ) )->get_data()['total'], 'nothing is left pending' );
	$removed = call_api( 'POST', 'skill-links/remove', array( 'skill_id' => $quad['id'], 'other_id' => $expand['id'], 'type' => 'encompasses' ) )->get_data();
	ok( 0 === count( $removed['encompasses'] ) && 2 === count( $removed['prerequisites'] ), 'a link is removed on request' );
	$removed = call_api( 'POST', 'skill-links/remove', array( 'skill_id' => $quad['id'], 'other_id' => $expand['id'], 'type' => 'prerequisite' ) )->get_data();
	ok( 1 === count( $removed['prerequisites'] ) && \OhMyLMS\Skills\Taxonomy::prerequisites( $quad['id'] ) === array( $linear['id'] ), 'removing a prerequisite also clears the skill\'s list' );
	ok( 404 === call_api( 'GET', 'skill-links/' . $objectives[0] )->get_status(), 'links are read for shared skills only' );

	// ---- MCP ----
	$tools   = \OhMyLMS\MCP\CurriculumAiTools::definitions();
	$execute = new ReflectionMethod( \OhMyLMS\MCP\Abilities::class, 'execute' );
	$execute->setAccessible( true );
	$mcp_links = $execute->invoke( null, 'get_skill_links', \OhMyLMS\MCP\CurriculumAiTools::definitions()['get_skill_links'], array( 'id' => $quad['id'] ) );
	ok( ! is_wp_error( $mcp_links ) && 1 === count( $mcp_links['data']['prerequisites'] ), 'MCP reads a skill\'s links' );
	$options = $execute->invoke( null, 'get_skill_placement_options', $tools['get_skill_placement_options'], array() );
	$listed  = $execute->invoke( null, 'list_skill_suggestions', $tools['list_skill_suggestions'], array( 'run_id' => $placing['id'], 'status' => 'all' ) );
	ok( ! is_wp_error( $options ) && isset( $options['data']['domains']['algebra'] ) && ! is_wp_error( $listed ) && 1 === $listed['data']['total'], 'MCP tools read placement options and suggestions' );
	ok( isset( \OhMyLMS\MCP\Server::tools()['review_skill_suggestions'], \OhMyLMS\MCP\Server::tools()['start_syllabus_analysis'] ), 'the MCP server lists the review tool' );

	// ---- Only administrators ----
	$learner = wp_create_user( 'ai-analysis-' . $tag, wp_generate_password(), 'ai-analysis-' . $tag . '@example.org' ); $users[] = $learner;
	wp_set_current_user( $learner );
	foreach ( array( array( 'GET', 'ai/runs' ), array( 'POST', 'ai/runs' ), array( 'GET', 'ai/suggestions' ), array( 'POST', 'ai/suggestions/review' ), array( 'GET', 'skill-placement' ), array( 'GET', 'skill-links/1' ), array( 'POST', 'skill-links' ), array( 'POST', 'skill-links/remove' ) ) as $route ) {
		ok( in_array( call_api( $route[0], $route[1], array( 'ids' => array( 1 ), 'action' => 'accept' ) )->get_status(), array( 401, 403 ), true ), 'a learner reached ' . $route[1] );
	}
	wp_set_current_user( $admin );
	echo $checks . " AI analysis integration checks passed.\n";
} finally {
	global $wpdb;
	wp_set_current_user( $admin );
	update_option( Comparison::OPTION, $before );
	if ( null === $ai_before ) { delete_option( AiSettings::OPTION ); } else { update_option( AiSettings::OPTION, $ai_before ); }
	foreach ( $runs as $id ) {
		$wpdb->delete( Schema::table( 'ai_suggestions' ), array( 'run_id' => $id ) );
		$wpdb->delete( Schema::table( 'ai_analysis_runs' ), array( 'id' => $id ) );
		wp_clear_scheduled_hook( AiAnalysis::HOOK, array( $id ) );
	}
	foreach ( $terms as $id ) { $wpdb->delete( SkillMappings::table(), array( 'specific_term_id' => $id ) ); $wpdb->delete( \OhMyLMS\Curriculum\SkillEdges::table(), array( 'skill_id' => $id ) ); $wpdb->delete( \OhMyLMS\Curriculum\SkillEdges::table(), array( 'other_id' => $id ) ); }
	foreach ( $items as $id ) { $item = Items::get( $id ); if ( $item ) { $course = $item['course_id']; $root = $item['skill_root_id']; Items::delete( $id, 'delete', true ); if ( $course ) { wp_delete_post( $course, true ); } if ( $root ) { wp_delete_term( $root, Taxonomy::NAME ); } } }
	foreach ( $terms as $id ) { wp_delete_term( $id, Taxonomy::NAME ); }
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $users as $id ) { if ( $id && ! is_wp_error( $id ) ) { wp_delete_user( $id ); } }
}
