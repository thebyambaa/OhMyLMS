<?php
// Disabled unless OHMYLMS_ENABLED_MODULES contains 'examples'.
add_action(
	'ohmylms_register_extensions',
	static function () {
		ohmylms_register_extension_settings(
			'example-benefit',
			array(
				'contexts' => array( 'membership' ),
				'schema'   => array(
					'type'       => 'object',
					'properties' => array(
						'benefit' => array(
							'type'      => 'string',
							'maxLength' => 200,
						),
					),
				),
			)
		);
		ohmylms_register_question_type(
			'example-number',
			array(
				'label'    => 'Example number',
				// The grader reads only the question object it is given, so it can grade frozen versions.
				'snapshot' => true,
				'editor'   => array(
					'schema' => array(
						'type'       => 'object',
						'properties' => array( 'expected' => array( 'type' => 'number' ) ),
						'required'   => array( 'expected' ),
					),
				),
				'render'   => static function ( $question, $attempt ) {
					printf( '<label>Number <input type="number" step="any" name="attempt[%d][quiz_question][%d][]"></label>', (int) $attempt['id'], (int) $question['id'] );
				},
				'validate' => static function ( $answer ) {
					return is_array( $answer ) && ( count( $answer ) === 0 || ( count( $answer ) === 1 && ( reset( $answer ) === '' || is_numeric( reset( $answer ) ) ) ) );},
				'grade'    => static function ( $answer, $question ) {
					$settings = $question->get_settings();
					$correct  = count( $answer ) === 1 && is_numeric( reset( $answer ) ) && isset( $settings['expected'] ) && (float) reset( $answer ) === (float) $settings['expected'];
					return array(
						'correct'  => $correct,
						'fraction' => $correct ? 1 : 0,
						'manual'   => false,
					);},
			)
		);
		ohmylms_register_lesson_type(
			'example-reading',
			array(
				'label'  => 'Example reading',
				'editor' => array( 'format' => 'wordpress-content' ),
				'render' => static function ( $lesson ) {
					echo '<article class="ohmylms-reading">' . wpautop( wp_kses_post( $lesson->get_description() ) ) . '</article>';},
			)
		);
		ohmylms_register_activity(
			'example-reveal',
			array(
				'label'  => 'Example reveal',
				'render' => static function ( $data ) {
					echo '<details><summary>' . esc_html( $data['prompt'] ?? 'Reveal' ) . '</summary><p>' . esc_html( $data['answer'] ?? '' ) . '</p></details>';},
			)
		);
		ohmylms_register_layout(
			'example-frame',
			array(
				'label'    => 'Example frame',
				'contexts' => array( 'course', 'lesson', 'quiz' ),
				'render'   => static function ( $args ) {
					$templates = array(
						'course' => 'content-single-course.php',
						'lesson' => 'single-lesson/content-lesson.php',
						'quiz'   => 'single-lesson/quiz-form.php',
					);
					echo '<section class="ohmylms-example-frame">';
					ohmylms_get_template( $templates[ $args['context'] ], $args );
					echo '</section>';},
			)
		);
		ohmylms_register_checkout_field(
			'example-reference',
			array(
				'label'    => 'Order reference',
				'type'     => 'text',
				'required' => false,
				'schema'   => array(
					'type'      => 'string',
					'maxLength' => 100,
				),
			)
		);
	}
);
add_action(
	'ohmylms_enqueue_extension_scripts',
	static function ( $sdk ) {
		wp_enqueue_script( 'ohmylms-example-module', plugins_url( 'modules/examples/editor.js', OHMYLMS_FILE ), array( $sdk ), OHMYLMS_VERSION, true );
	}
);
