<?php
/**
 * Native MCP transport acceptance for bounded authoring and reviews.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Curriculum\LessonObjectives;
use OhMyLMS\Skills\Taxonomy;

/**
 * Exercise the real adapter and existing authoring services on disposable data.
 *
 * @param callable $transport Authenticated MCP transport.
 * @param array    $state Fixture IDs, extended for cleanup.
 * @param int      $admin Administrator ID.
 */
function authoring_checks( callable $transport, array &$state, $admin ) {
	$call      = static function ( $name, array $arguments ) use ( $transport ) {
		$reply = $transport(
			'tools/call',
			array(
				'name'      => $name,
				'arguments' => $arguments,
			)
		);
		objectives_check( isset( $reply['result'] ) && empty( $reply['result']['isError'] ), $name . ' succeeds: ' . wp_json_encode( $reply ) );
		return $reply['result']['structuredContent']['data'];
	};
	$reject    = static function ( $name, array $arguments ) use ( $transport ) {
		$reply = $transport(
			'tools/call',
			array(
				'name'      => $name,
				'arguments' => $arguments,
			)
		);
		objectives_check( ! empty( $reply['error'] ) || ! empty( $reply['result']['isError'] ), $name . ' rejects invalid or stale input' );
	};
	$catalog   = $transport( 'tools/list', array() );
	$available = array_column( $catalog['result']['tools'], 'name' );
	objectives_check( 8 === count( array_intersect( array_keys( \OhMyLMS\MCP\AuthoringTools::definitions() ), $available ) ), 'All authoring tools are discoverable' );
	$lesson           = $call(
		'save_lesson',
		array(
			'name'     => 'MCP equations ' . $state['course'],
			'content'  => '<!-- wp:paragraph --><p>Solve x + 2 = 5.</p><!-- /wp:paragraph -->',
			'settings' => array(
				'type'         => 'text',
				'external_url' => 'https://example.invalid/lesson.mp4',
			),
		)
	);
	$state['posts'][] = $lesson['id'];
	objectives_check( 'draft' === $lesson['status'], 'MCP lesson creation is draft only' );
	$read = $call( 'get_lesson_for_edit', array( 'id' => $lesson['id'] ) );
	objectives_check( $lesson['revision'] === $read['revision'] && false !== strpos( $read['content'], 'x + 2' ), 'Raw block content and content revision round-trip' );
	$changed = $call(
		'save_lesson',
		array(
			'id'       => $lesson['id'],
			'revision' => $read['revision'],
			'name'     => 'Updated MCP equations',
		)
	);
	objectives_check( $changed['content'] === $read['content'] && $changed['settings']['external_url'] === $read['settings']['external_url'], 'Partial lesson edits preserve content and settings' );
	$reject(
		'save_lesson',
		array(
			'id'       => $lesson['id'],
			'revision' => $read['revision'],
			'name'     => 'Stale overwrite',
		)
	);
	$reject(
		'save_lesson',
		array(
			'name'   => 'Invalid lesson',
			'status' => 'publish',
		)
	);
	$links = $call( 'get_lesson_objectives', array( 'id' => $lesson['id'] ) );
	wp_set_current_user( $admin );
	wp_set_object_terms( $lesson['id'], array( $state['skills'][1] ), Taxonomy::NAME );
	$reference = array(
		'syllabus_id' => $state['syllabus'],
		'term_id'     => $state['skills'][0],
		'relation'    => 'teaches',
	);
	$mapped    = $call(
		'set_lesson_objectives',
		array(
			'id'       => $lesson['id'],
			'revision' => $links['revision'],
			'mappings' => array( $reference ),
		)
	);
	objectives_check( has_term( $state['skills'][0], Taxonomy::NAME, $lesson['id'] ) && has_term( $state['skills'][1], Taxonomy::NAME, $lesson['id'] ), 'Explicit mapping synchronizes tags without losing existing tags' );
	$reject(
		'set_lesson_objectives',
		array(
			'id'       => $lesson['id'],
			'revision' => $links['revision'],
			'mappings' => array(),
		)
	);
	$review_input = array(
		'id'                 => $lesson['id'],
		'syllabus_id'        => $reference['syllabus_id'],
		'term_id'            => $reference['term_id'],
		'content_revision'   => $mapped['content_revision'],
		'objective_revision' => $mapped['mappings'][0]['objective_revision'],
		'review_revision'    => $mapped['review_revision'],
		'status'             => 'partial',
		'evidence'           => 'The lesson gives one equation example but no guided practice of \\frac{1}{2}.',
		'recommendation'     => 'Add worked examples and independent practice.',
	);
	$reject( 'save_lesson_alignment_review', array_replace( $review_input, array( 'evidence' => '<script></script>' ) ) );
	$reviewed = $call( 'save_lesson_alignment_review', $review_input );
	objectives_check( ! $reviewed['mappings'][0]['review']['stale'] && $admin === $reviewed['mappings'][0]['review']['reviewed_by'], 'Review tracks the inspected versions and reviewer' );
	$reject( 'save_lesson_alignment_review', $review_input );
	$review_state = $call( 'get_lesson_alignment_reviews', array( 'id' => $lesson['id'] ) );
	objectives_check( 'partial' === $review_state['mappings'][0]['review']['status'], 'Review status and evidence can be read back' );
	objectives_check( $review_input['evidence'] === $review_state['mappings'][0]['review']['evidence'], 'Review evidence preserves mathematical backslashes' );
	$original_objective = \OhMyLMS\Skills\Objectives::get( get_term( $reference['term_id'], Taxonomy::NAME ) );
	\OhMyLMS\Skills\Objectives::save( $reference['term_id'], 'Solve equations with two variables.' );
	$objective_changed = $call( 'get_lesson_alignment_reviews', array( 'id' => $lesson['id'] ) );
	objectives_check( $objective_changed['mappings'][0]['review']['stale'], 'Official objective edits mark saved reviews stale independently of lesson edits' );
	\OhMyLMS\Skills\Objectives::save( $reference['term_id'], $original_objective );
	$edit = $call( 'get_lesson_for_edit', array( 'id' => $lesson['id'] ) );
	$call(
		'save_lesson',
		array(
			'id'       => $lesson['id'],
			'revision' => $edit['revision'],
			'content'  => '<!-- wp:paragraph --><p>Additional equation examples.</p><!-- /wp:paragraph -->',
		)
	);
	$stale = $call( 'get_lesson_alignment_reviews', array( 'id' => $lesson['id'] ) );
	objectives_check( $stale['mappings'][0]['review']['stale'], 'Content changes mark saved review stale' );
	$clear = $call(
		'set_lesson_objectives',
		array(
			'id'       => $lesson['id'],
			'revision' => $stale['revision'],
			'mappings' => array(),
		)
	);
	objectives_check( array() === $clear['mappings'] && ! has_term( $state['skills'][0], Taxonomy::NAME, $lesson['id'] ) && has_term( $state['skills'][1], Taxonomy::NAME, $lesson['id'] ), 'Clearing targets removes only managed tags and reviews' );
	wp_set_current_user( $admin );
	wp_update_post(
		array(
			'ID'          => $lesson['id'],
			'post_status' => 'publish',
		)
	);
	$live = $call( 'get_lesson_for_edit', array( 'id' => $lesson['id'] ) );
	$reject(
		'save_lesson',
		array(
			'id'       => $lesson['id'],
			'revision' => $live['revision'],
			'name'     => 'Unconfirmed live edit',
		)
	);
	$call(
		'save_lesson',
		array(
			'id'              => $lesson['id'],
			'revision'        => $live['revision'],
			'name'            => 'Explicit live edit',
			'allow_published' => true,
		)
	);
	$question         = $call(
		'save_question',
		array(
			'name'        => 'Solve x + 2 = 5',
			'description' => '<p>Solve x + 2 = 5</p>',
			'settings'    => array(
				'type'     => 'single-choice',
				'required' => false,
			),
			'questions'   => array(
				array(
					'answer'     => '3',
					'is_correct' => true,
				),
				array(
					'answer'     => '7',
					'is_correct' => false,
				),
			),
			'skills'      => array(
				'p1' => array(
					'primary'    => $state['skills'][0],
					'supporting' => array(),
				),
			),
		)
	);
	$state['posts'][] = $question['id'];
	objectives_check( 'draft' === $question['status'] && 2 === count( $question['questions'] ) && 0 === $question['approved_version_id'], 'Question draft and native answer options created without approval' );
	$question_read = $call( 'get_question_for_edit', array( 'id' => $question['id'] ) );
	objectives_check( $question['revision'] === $question_read['revision'], 'Question authoring revision round-trips' );
	$edited = $call(
		'save_question',
		array(
			'id'          => $question['id'],
			'revision'    => $question['revision'],
			'description' => '<p>Find the value of x in x + 2 = 5.</p>',
		)
	);
	objectives_check( $edited['questions'] === $question['questions'] && $edited['skills'] === $question['skills'], 'Question partial edits retain options and versioned skill attribution' );
	objectives_check( $edited['version_id'] !== $question['version_id'] && 0 === $edited['approved_version_id'], 'Question edits capture a new version without approving it' );
	$reject(
		'save_question',
		array(
			'id'       => $question['id'],
			'revision' => $question['revision'],
			'name'     => 'Stale question',
		)
	);
	$reject(
		'save_question',
		array(
			'name'     => 'Invalid type',
			'settings' => array( 'type' => 'nonexistent-type' ),
		)
	);
	wp_set_current_user( 0 );
	objectives_check( is_wp_error( wp_get_ability( 'ohmylms/save-lesson' )->execute( array( 'name' => 'Unauthorized' ) ) ), 'Anonymous authoring ability rejected' );
	$request = new WP_REST_Request( 'GET', '/ohmylms/v1/mcp-authoring/lessons/' . $lesson['id'] );
	objectives_check( 401 === rest_do_request( $request )->get_status(), 'Anonymous authoring REST read rejected' );
	wp_set_current_user( $admin );
}
