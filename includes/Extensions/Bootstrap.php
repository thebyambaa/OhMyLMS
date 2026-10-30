<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
namespace OhMyLMS\Extensions;

/**
 * Plugin bootstrap implementation.
 */
final class Bootstrap {
	/**
	 * Init for Bootstrap.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( TabPreferences::class, 'register_routes' ) );
		QuestionTypes::register_defaults();
		QuestionTypes::register_math();
		QuestionTypes::register_interactive();
		\OhMyLMS\Assessment\ExtendedQuestions::register();
		Activities::register_defaults();
		foreach ( array( 'text', 'audio', 'video' ) as $type ) {
			Registry::register(
				'lesson',
				$type,
				array(
					'label'  => 'text' === $type ? __( 'Block lesson', 'ohmylms' ) : ucfirst( $type ),
					'render' => static function () {
											the_content(); },
					'editor' => array( 'format' => 'wordpress-content' ),
				)
			);
		}
		Registry::mark_core();
		do_action( 'ohmylms_register_extensions' );
		add_filter(
			'ohmylms_supported_question_types',
			static function () {
				return array_keys( Registry::all( 'question' ) );
			},
			100
		);
		add_filter(
			'ohmylms_lesson_types',
			static function () {
				return array_keys( Registry::all( 'lesson' ) );
			},
			100
		);
		add_shortcode( 'ohmylms_activity', array( __CLASS__, 'activity' ) );
		add_action(
			'rest_api_init',
			static function () {
				register_rest_route(
					'ohmylms/v1',
					'/extensions',
					array(
						'methods'             => 'GET',
						'permission_callback' => static function () {
							return current_user_can( 'edit_posts' );},
						'callback'            => static function () {
							return rest_ensure_response(
								array_merge(
									Registry::manifest(),
									array(
										'apiVersion' => 1,
										'settings'   => Settings::manifest(),
									)
								)
							);},
					)
				);
			}
		);
		Editor::init();
		\OhMyLMS\AI\AdminPage::init();
		add_action(
			'ohmylms_lesson_completed',
			static function ( $lesson, $course, $student ) {
				do_action(
					'ohmylms_lesson_completed_event',
					array(
						'lesson_id'  => (int) $lesson,
						'course_id'  => (int) $course,
						'student_id' => (int) $student,
					)
				);
			},
			10,
			3
		);
		add_action(
			'ohmylms_course_completed',
			static function ( $student, $course, $order ) {
				do_action(
					'ohmylms_course_completed_event',
					array(
						'student_id' => (int) $student,
						'course_id'  => (int) $course,
						'order_id'   => (int) $order,
					)
				);
			},
			10,
			3
		);
	}
	/**
	 * Activity for Bootstrap.
	 *
	 * @param mixed  $attributes Attributes.
	 * @param string $content Content.
	 */
	public static function activity( $attributes, $content = '' ) {
		$definition = Registry::get( 'activity', $attributes['type'] ?? '' );
		if ( ! $definition ) {
			return '';
		}
		// Shortcodes may carry plain attributes (prompt="..." answer="...") instead of a JSON data blob.
		$data = isset( $attributes['data'] ) ? json_decode( $attributes['data'], true ) : array_diff_key( (array) $attributes, array( 'type' => 1 ) );
		if ( ! is_array( $data ) ) {
			return '';
		}
		ob_start();
		call_user_func( $definition['render'], $data );
		return ob_get_clean();
	}
	/**
	 * Lesson for Bootstrap.
	 *
	 * @param mixed $lesson Lesson.
	 */
	public static function lesson( $lesson ) {
		$definition = Registry::get( 'lesson', $lesson->get_type() );
		if ( $definition ) {
			call_user_func( $definition['render'], $lesson );
		} else {
			the_content();
		}
	}
}
