<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping.
/**
 * Author-only inline previews through the complete student rendering pipeline.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Assessment;

use OhMyLMS\Extensions\Registry;
use OhMyLMS\QuestionBank\AccessPolicy;

defined( 'ABSPATH' ) || exit;

/** Render unsaved questions without publishing content or starting learner attempts. */
final class QuestionPreview {
	/** Register the author API and isolated frontend document. */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'template_redirect', array( __CLASS__, 'document' ), 0 );
	}

	/** Register an authenticated, non-publishing preview operation. */
	public static function routes() {
		register_rest_route(
			'ohmylms/v1',
			'/question-preview',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( __CLASS__, 'permission' ),
				'callback'            => array( __CLASS__, 'create' ),
				'args'                => array(
					'post_id'        => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					),
					'question'       => array(
						'type'       => 'object',
						'required'   => true,
						'properties' => array(
							'id'          => array(
								'type'    => 'integer',
								'minimum' => 0,
							),
							'name'        => array( 'type' => 'string' ),
							'description' => array( 'type' => 'string' ),
							'image_src'   => array( 'type' => 'string' ),
							'video_src'   => array( 'type' => 'string' ),
							'settings'    => array( 'type' => 'object' ),
							'questions'   => array(
								'type'     => 'array',
								'maxItems' => 1000,
								'items'    => array( 'type' => 'object' ),
							),
						),
					),
					'settings'       => array(
						'type'    => 'object',
						'default' => array(),
					),
					'kind'           => array(
						'type'    => 'string',
						'default' => 'quiz',
					),
					'ordinal'        => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 10000,
						'default' => 1,
					),
					'question_count' => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 10000,
						'default' => 1,
					),
					'seed'           => array(
						'type'    => 'integer',
						'default' => 1,
					),
				),
			)
		);
	}

	/**
	 * Require access to the authoring document and any referenced question.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function permission( $request ) {
		$post_id     = absint( $request['post_id'] );
		$question    = $request['question'];
		$question_id = absint( $question['id'] ?? 0 );
		return in_array( get_post_type( $post_id ), array( 'ohmylms-quiz', 'ohmylms-lesson', 'ohmylms-question' ), true )
			&& current_user_can( 'edit_post', $post_id )
			&& ( ! $question_id || AccessPolicy::can_use_question( $question_id ) );
	}

	/**
	 * Make a learner-safe view of the unsaved draft, including extension public views.
	 *
	 * @param array $draft Draft question.
	 * @param int   $seed Randomization seed.
	 * @return array|\WP_Error
	 */
	public static function view( array $draft, $seed ) {
		$draft    = map_deep(
			$draft,
			static function ( $value ) {
				return is_string( $value ) ? wp_kses_post( $value ) : $value;
			}
		);
		$settings = is_array( $draft['settings'] ?? null ) ? $draft['settings'] : array();
		$type     = $settings['type'] ?? '';
		if ( ! is_string( $type ) || ! Registry::get( 'question', $type ) ) {
			return new \WP_Error( 'ohmylms_preview_type', __( 'Enable this question type to preview it.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$options = array();
		foreach ( (array) ( $draft['questions'] ?? $draft['options'] ?? array() ) as $index => $option ) {
			if ( is_array( $option ) ) {
				$options[] = array_merge( $option, array( 'id' => max( 1, absint( $option['id'] ?? $index + 1 ) ) ) );
			}
		}
		if ( Template::has( $settings ) ) {
			$valid = Template::validate( $settings, $draft['name'] ?? '', $draft['description'] ?? '', $options );
			if ( true !== $valid ) {
				return new \WP_Error( 'ohmylms_preview_template', $valid, array( 'status' => 400 ) );
			}
		}
		$media = ! empty( $draft['id'] ) ? ( \OhMyLMS\QuestionBank\VersionPublisher::content( $draft['id'] )['media'] ?? array() ) : array();
		foreach ( array( 'image', 'video' ) as $medium ) {
			if ( array_key_exists( $medium . '_src', $draft ) ) {
				$media[ $medium . '_url' ] = esc_url_raw( $draft[ $medium . '_src' ] );
			}
		}
		$snapshot    = new QuestionSnapshot(
			array(
				'id'            => 0,
				'version_no'    => 0,
				'question_uuid' => '',
				'question_id'   => max( 1, absint( $draft['id'] ?? 0 ) ),
				'type'          => $type,
				'title'         => $draft['name'] ?? '',
				'body'          => $draft['description'] ?? '',
				'settings'      => $settings,
				'options'       => $options,
				'extension'     => array(),
				'parts'         => array(),
				'media'         => $media,
			)
		);
		$snapshot    = $snapshot->instantiate( $seed );
		$order       = array_column( $snapshot->get_questions(), 'id' );
		$definitions = $order;
		if ( ! empty( $settings['randomize'] ) ) {
			shuffle( $order );
		}
		shuffle( $definitions );
		return $snapshot->student_view( $order, array(), array( 'definitions' => $definitions ), array(), 'inline-preview:' . $seed );
	}

	/**
	 * Store only the public view in a short-lived, user-bound preview session.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public static function create( $request ) {
		if ( strlen( wp_json_encode( $request['question'] ) ) > 1048576 ) {
			return new \WP_Error( 'ohmylms_preview_size', __( 'This question is too large to preview.', 'ohmylms' ), array( 'status' => 413 ) );
		}
		$view = self::view( (array) $request['question'], (int) $request['seed'] );
		if ( is_wp_error( $view ) ) {
			return $view;
		}
		$token = strtolower( wp_generate_password( 32, false, false ) );
		set_transient(
			'ohmylms_question_preview_' . $token,
			array(
				'user'               => get_current_user_id(),
				'post_id'            => (int) $request['post_id'],
				'question'           => $view,
				'source_question_id' => absint( $request['question']['id'] ?? 0 ),
				'kind'               => sanitize_key( $request['kind'] ),
				'ordinal'            => (int) $request['ordinal'],
				'question_count'     => (int) $request['question_count'],
				'settings'           => \OhMyLMS\Learning\NativeLessonEditor::sanitize_setting( $request['settings'] ),
			),
			15 * MINUTE_IN_SECONDS
		);
		return array(
			'url' => add_query_arg(
				array(
					'ohmylms_question_preview' => $token,
					'_wpnonce'                 => wp_create_nonce( 'ohmylms_question_preview_' . $token ),
				),
				home_url( '/' )
			),
		);
	}

	/** Serve a private frame through the same template and asset hooks as students. */
	public static function document() {
		if ( ! isset( $_GET['ohmylms_question_preview'] ) ) {
			return;
		}
		$token = is_string( $_GET['ohmylms_question_preview'] ) ? sanitize_key( wp_unslash( $_GET['ohmylms_question_preview'] ) ) : '';
		$nonce = isset( $_GET['_wpnonce'] ) && is_string( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		$saved = get_transient( 'ohmylms_question_preview_' . $token );
		if ( ! wp_verify_nonce( $nonce, 'ohmylms_question_preview_' . $token ) || ! $saved || get_current_user_id() !== $saved['user'] || ! current_user_can( 'edit_post', $saved['post_id'] ) || ( ! empty( $saved['source_question_id'] ) && ! AccessPolicy::can_use_question( $saved['source_question_id'] ) ) ) {
			wp_die( esc_html__( 'This question preview expired. Reopen the question to try again.', 'ohmylms' ), '', array( 'response' => 403 ) );
		}
		nocache_headers();
		show_admin_bar( false );
		remove_action( 'wp_head', '_admin_bar_bump_cb' );
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Student template extensions resolve the preview's authorized parent document through get_the_ID().
		$GLOBALS['post'] = get_post( $saved['post_id'] );
		setup_postdata( $GLOBALS['post'] );
		wp_enqueue_style( 'ohmylms-frontend' );
		wp_enqueue_script( 'ohmylms-frontend' );
		MathLive::enqueue();
		wp_enqueue_style( 'ohmylms-question-frame', plugins_url( 'assets/css/question-preview.css', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/css/question-preview.css' ) );
		$question                           = $saved['question'];
		$saved['settings']['paper_content'] = array();
		$preview                            = array(
			'questions'       => array( $question ),
			'settings'        => $saved['settings'],
			'session'         => '',
			'single_question' => true,
			'profile_kind'    => $saved['kind'],
			'ordinal'         => $saved['ordinal'],
			'question_count'  => $saved['question_count'],
			'paper_items'     => array(
				array(
					'kind'          => 'question',
					'preview_label' => ( 'q' === ( $saved['settings']['paper']['numbering'] ?? '' ) ? 'Q' : '' ) . $saved['ordinal'],
					'question'      => array_merge( $question, array( 'public' => true ) ),
				),
			),
		);
		// Render before the head so assets enqueued by any registered question renderer are printed.
		ob_start();
		ohmylms_get_template( 'single-lesson/quiz-form.php', array( 'ohmylms_player_preview' => $preview ) );
		$content = ob_get_clean();
		?>
		<!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><?php wp_head(); ?></head>
		<body <?php body_class( 'ohmylms-page ohmylms-question-frame' ); ?>>
		<div class="ohmylms-question-preview-content"><?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- The student template and registered renderers escape their fields. ?></div>
		<?php wp_footer(); ?>
		</body></html>
		<?php
		exit;
	}
}
