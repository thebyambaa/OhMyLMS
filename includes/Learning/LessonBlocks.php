<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Lesson editor allowlist. Lessons stay in Gutenberg; question bodies stay on question posts.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Learning;

defined( 'ABSPATH' ) || exit;

/**
 * Blocks a lesson may contain, plus the exercise reference block.
 */
final class LessonBlocks {

	const ALLOWED = array(
		'core/paragraph',
		'core/heading',
		'core/list',
		'core/image',
		'ohmylms/lesson-video',
		'ohmylms/lesson-callout',
		'ohmylms/lesson-math',
		'ohmylms/lesson-exercise',
	);

	/** Register the blocks, the allowlist and the starter template. */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 20 );
		add_filter( 'allowed_block_types_all', array( __CLASS__, 'allow' ), 10, 2 );
	}

	/** Block types and the lesson template. */
	public static function register() {
		$object = get_post_type_object( OHMYLMS_LESSON_CPT );
		if ( $object ) {
			$object->template = array(
				array(
					'core/paragraph',
					array(
						'placeholder' => __( 'Write the lesson. Add video, a callout, math or an exercise from the block list.', 'ohmylms' ),
					),
				),
			);
		}
		wp_register_script(
			'ohmylms-lesson-blocks',
			plugins_url( 'assets/js/lesson-blocks.js', OHMYLMS_FILE ),
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-i18n' ),
			OHMYLMS_VERSION,
			true
		);
		self::block(
			'ohmylms/lesson-video',
			array(
				'url'   => array(
					'type'    => 'string',
					'default' => '',
				),
				'title' => array(
					'type'    => 'string',
					'default' => '',
				),
			),
			array( __CLASS__, 'render_video' )
		);
		self::block(
			'ohmylms/lesson-callout',
			array(
				'tone' => array(
					'type'    => 'string',
					'default' => 'info',
				),
				'text' => array(
					'type'    => 'string',
					'default' => '',
				),
			),
			array( __CLASS__, 'render_callout' )
		);
		self::block(
			'ohmylms/lesson-math',
			array(
				'tex' => array(
					'type'    => 'string',
					'default' => '',
				),
			),
			array( __CLASS__, 'render_math' )
		);
		self::block(
			'ohmylms/lesson-exercise',
			array(
				'quizId'     => array(
					'type'    => 'integer',
					'default' => 0,
				),
				'questionId' => array(
					'type'    => 'integer',
					'default' => 0,
				),
			),
			array( __CLASS__, 'render_exercise' )
		);
		unset( $common );
	}

	/**
	 * Register one dynamic block.
	 *
	 * @param string   $name       Block name.
	 * @param array    $attributes Attributes.
	 * @param callable $render     Render callback.
	 */
	private static function block( $name, array $attributes, $render ) {
		register_block_type(
			$name,
			array(
				'api_version'     => 3,
				'attributes'      => $attributes,
				'render_callback' => $render,
				'editor_script'   => 'ohmylms-lesson-blocks',
			)
		);
	}

	/**
	 * Lessons may only use the lesson template blocks.
	 *
	 * @param bool|string[]            $allowed Current allowlist.
	 * @param \WP_Block_Editor_Context $context Editor context.
	 * @return bool|string[]
	 */
	public static function allow( $allowed, $context ) {
		$post = isset( $context->post ) ? $context->post : null;
		if ( ! $post || OHMYLMS_LESSON_CPT !== $post->post_type || false === $allowed ) {
			return $allowed;
		}
		return self::ALLOWED;
	}

	/**
	 * Video URL. Only http(s) URLs are printed.
	 *
	 * @param array $attributes Attributes.
	 * @return string
	 */
	public static function render_video( $attributes ) {
		$url   = isset( $attributes['url'] ) ? esc_url( $attributes['url'] ) : '';
		$title = isset( $attributes['title'] ) ? sanitize_text_field( $attributes['title'] ) : '';
		if ( '' === $url ) {
			return '';
		}
		return '<figure class="oml-lesson-video"><video controls src="' . $url . '"></video><figcaption>' . esc_html( $title ) . '</figcaption></figure>';
	}

	/**
	 * Callout. The tone is one of three tokens.
	 *
	 * @param array $attributes Attributes.
	 * @return string
	 */
	public static function render_callout( $attributes ) {
		$tone = isset( $attributes['tone'] ) ? sanitize_key( $attributes['tone'] ) : 'info';
		if ( ! in_array( $tone, array( 'info', 'warning', 'success' ), true ) ) {
			$tone = 'info';
		}
		$text = isset( $attributes['text'] ) ? wp_kses_post( $attributes['text'] ) : '';
		return '<aside class="oml-lesson-callout is-' . esc_attr( $tone ) . '">' . $text . '</aside>';
	}

	/**
	 * TeX source. MathLive on the learner page typesets elements with data-latex.
	 *
	 * @param array $attributes Attributes.
	 * @return string
	 */
	public static function render_math( $attributes ) {
		$tex = isset( $attributes['tex'] ) ? (string) $attributes['tex'] : '';
		if ( '' === trim( $tex ) ) {
			return '';
		}
		return '<span class="oml-math" data-latex="' . esc_attr( $tex ) . '">' . esc_html( $tex ) . '</span>';
	}

	/**
	 * Embed a quiz or question by id. The post content stores only those ids.
	 *
	 * @param array $attributes Attributes.
	 * @return string
	 */
	public static function render_exercise( $attributes ) {
		$quiz     = isset( $attributes['quizId'] ) ? absint( $attributes['quizId'] ) : 0;
		$question = isset( $attributes['questionId'] ) ? absint( $attributes['questionId'] ) : 0;
		if ( $quiz && OHMYLMS_QUIZ_CPT === get_post_type( $quiz ) ) {
			return '<div class="oml-lesson-exercise">' . ohmylms_get_quiz_player( $quiz ) . '</div>';
		}
		if ( $question && OHMYLMS_QUESTION_CPT === get_post_type( $question ) ) {
			return '<div class="oml-lesson-exercise">' . NativeLessonEditor::render_question_block(
				array(
					'questionId' => $question,
					'delivery'   => 'inline',
				)
			) . '</div>';
		}
		return '<p class="oml-lesson-exercise">' . esc_html__( 'This exercise does not point at a quiz or question yet.', 'ohmylms' ) . '</p>';
	}
}
