<?php
/** Read-only REST checks against a supplied WordPress installation; no records are saved. */
if ( 'cli' !== PHP_SAPI || empty( $argv[1] ) ) {
	exit( 1 );
}
require rtrim( $argv[1], '/\\' ) . '/wp-load.php';
$cases = json_decode( stream_get_contents( STDIN ), true );
wp_set_current_user( 1 );
if ( ! current_user_can( 'edit_posts' ) ) {
	throw new RuntimeException( 'The local test author cannot edit posts.' );
}
$call = static function ( $data ) {
	$request = new WP_REST_Request( 'POST', '/ohmylms/v1/question-template/preview' );
	$request->set_body_params( $data );
	return rest_do_request( $request );
};
$checks = 0;
foreach ( $cases as $case ) {
	$first = $call( $case );
	$data  = $first->get_data();
	if ( false !== strpos( wp_json_encode( $data ), 'lbrace' ) ) {
		throw new RuntimeException( 'MathLive literal variable braces did not resolve.' );
	}
	if ( 200 !== $first->get_status() || empty( $data['valid'] ) || strpos( wp_json_encode( $data['question'] ), '{{' ) !== false ) {
		throw new RuntimeException( 'Unresolved randomized type: ' . $case['settings']['type'] . ' ' . wp_json_encode( $data ) );
	}
	if ( $data !== $call( $case )->get_data() || isset( $data['question']['settings']['template'] ) ) {
		throw new RuntimeException( 'Instance changed for the same seed or retained its template.' );
	}
	if ( ! is_string( $data['question']['questions'][0]['answer'] ) || 101 !== $data['question']['questions'][0]['id'] || true !== $data['question']['questions'][0]['is_correct'] ) {
		throw new RuntimeException( 'Option submission contracts changed.' );
	}
	if ( strpos( $data['question']['description'], '<script' ) !== false || strpos( $data['question']['description'], 'onerror' ) !== false ) {
		throw new RuntimeException( 'Unsafe preview HTML survived sanitization.' );
	}
	$snapshot = new \OhMyLMS\Assessment\QuestionSnapshot(
		array(
			'id' => 1, 'question_id' => 1, 'question_uuid' => 'test', 'version_no' => 1,
			'type' => $case['settings']['type'], 'title' => $case['name'], 'body' => $case['description'],
			'settings' => $case['settings'], 'options' => $case['questions'],
			'media' => array(), 'parts' => array(), 'extension' => array(),
		)
	);
	$learner = $snapshot->instantiate( $case['seed'] )->student_view();
	if ( false !== strpos( wp_json_encode( $learner ), 'lbrace' ) ) {
		throw new RuntimeException( 'Learner equation retains literal variable braces.' );
	}
	if ( strpos( wp_json_encode( $learner ), '{{' ) !== false || isset( $learner['settings']['template'] ) || isset( $learner['questions'][0]['is_correct'] ) ) {
		throw new RuntimeException( 'Learner delivery has unresolved variables or teacher keys: ' . $case['settings']['type'] );
	}
	if ( $learner !== $snapshot->instantiate( $case['seed'] )->student_view() || ! $snapshot->is_template() ) {
		throw new RuntimeException( 'Restoration changed numbers or mutated the frozen template.' );
	}
	++$checks;
}
$broken = $cases[0];
$broken['name'] = '{{z}}';
if ( false !== $call( $broken )->get_data()['valid'] ) {
	throw new RuntimeException( 'Unknown variable was accepted.' );
}
$broken['settings']['template']['constraints'] = array( 'a>100' );
if ( false !== $call( $broken )->get_data()['valid'] ) {
	throw new RuntimeException( 'Impossible constraints were accepted.' );
}
wp_set_current_user( 0 );
if ( ! in_array( $call( $cases[0] )->get_status(), array( 401, 403 ), true ) ) {
	throw new RuntimeException( 'Teacher-only preview became public.' );
}
echo $checks . " randomized question types passed read-only REST checks.\n";
