<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Lesson settings and navigation for the unmodified WordPress post editor.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Learning;

defined( 'ABSPATH' ) || exit;

/** Integrate LMS data with native Gutenberg post saving. */
final class NativeLessonEditor {

	/** Existing storage keys remain compatible with the LMS REST API and player. */
	private const META_KEYS = array(
		'type'              => '_type',
		'preview_enable'    => '_preview_enable',
		'enable_comments'   => '_enable_comments',
		'cover_image_id'    => '_cover_image_id',
		'video_id'          => '_video_id',
		'audio_id'          => '_audio_id',
		'external_url'      => '_external_url',
		'drip_settings'     => '_drip_settings',
		'prerequisites'     => '_prerequisites',
		'download_resource' => '_download_resource',
		'video_settings'    => '_video_settings',
	);

	/** Register editor integration hooks. */
	public static function init() {
		NativeQuizEditor::init();
		NativeQuestionEditor::init();
		\OhMyLMS\Assessment\QuestionPreview::init();
		add_action( 'init', array( __CLASS__, 'register_question_block' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
		add_action( 'enqueue_block_assets', array( __CLASS__, 'question_block_styles' ) );
		add_action( 'rest_after_insert_ohmylms-lesson', array( __CLASS__, 'after_save' ), 10, 3 );
		add_filter( 'ohmylms_rest_prepare_lesson', array( __CLASS__, 'legacy_response' ), 10, 2 );
		add_filter( 'rest_prepare_ohmylms-lesson', array( __CLASS__, 'blocks_for_editor' ), 10, 3 );
	}

	/** Register a reference-only placement using the established inline assessment flow. */
	public static function register_question_block() {
		register_block_type(
			'ohmylms/shared-multiple-choice',
			array(
				'api_version'     => 3,
				'attributes'      => array(
					'questionId'        => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'uuid'              => array(
						'type'    => 'string',
						'default' => '',
					),
					'authoringRevision' => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'delivery'          => array(
						'type'    => 'string',
						'default' => 'inline',
					),
				),
				'render_callback' => array( __CLASS__, 'render_question_block' ),
			)
		);
	}

	/**
	 * Resolve approved content through the existing access, grading and evidence boundary.
	 *
	 * @param array $attributes Saved question references, without answer keys.
	 * @return string Learner-safe interactive question markup.
	 */
	public static function render_question_block( $attributes ) {
		if ( 'quiz' === ( $attributes['delivery'] ?? '' ) || 'ohmylms-quiz' === get_post_type() ) {
			return '';
		}
		$html = \OhMyLMS\Practice\Inline::render( (string) ( $attributes['uuid'] ?? '' ) );
		return \OhMyLMS\Assessment\MathLive::content( $html );
	}

	/** Load shared authoring styles inside the native editor canvas, including its iframe. */
	public static function question_block_styles() {
		$screen = is_admin() ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->post_type, array( 'ohmylms-lesson', 'ohmylms-quiz', 'ohmylms-question' ), true ) || ! $screen->is_block_editor() ) {
			return;
		}
		wp_enqueue_style( 'ohmylms-question-editor', plugins_url( 'assets/css/question-editor.css', OHMYLMS_FILE ), array( 'wp-components', 'dashicons' ), OHMYLMS_VERSION );
		// The canvas is an iframe: enqueue the learner widget styles as block assets too.
		\OhMyLMS\Assessment\Interactive::enqueue();
		if ( 'ohmylms-quiz' === $screen->post_type ) {
			// The printable paper canvas; inert until Paper view is switched on.
			wp_enqueue_style( 'ohmylms-paper', plugins_url( 'assets/css/paper.css', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/css/paper.css' ) );
		}
		if ( \OhMyLMS\Assessment\MathLive::enabled() ) {
			wp_enqueue_style( 'ohmylms-mathlive', plugins_url( 'assets/css/mathlive.css', OHMYLMS_FILE ), array(), '0.111.1' );
		}
	}

	/**
	 * Schema for the existing lesson settings; block content uses core post fields.
	 *
	 * @return array REST schema.
	 */
	public static function settings_schema() {
		$object = array(
			'type'                 => 'object',
			'additionalProperties' => true,
		);
		$id     = array(
			'type'    => 'integer',
			'minimum' => 0,
		);
		return array(
			'type'                 => 'object',
			'context'              => array( 'edit' ),
			'additionalProperties' => false,
			'properties'           => array(
				'type'              => array(
					'type' => 'string',
					'enum' => array_keys( \OhMyLMS\Extensions\Registry::all( 'lesson' ) ),
				),
				'preview_enable'    => array( 'type' => 'boolean' ),
				'enable_comments'   => array( 'type' => 'boolean' ),
				'cover_image_id'    => $id,
				'video_id'          => $id,
				'audio_id'          => $id,
				'external_url'      => array( 'type' => 'string' ),
				'drip_settings'     => array_merge(
					$object,
					array(
						'properties' => array(
							'enable' => array( 'type' => 'boolean' ),
							'days'   => $id,
							'type'   => array(
								'type' => 'string',
								'enum' => array( 'specific-date', 'enrollment-from-x-days', 'cohort-from-x-days', 'cohort-start' ),
							),
							'date'   => array( 'type' => array( 'string', 'null' ) ),
							'time'   => array( 'type' => array( 'string', 'null' ) ),
						),
					)
				),
				'prerequisites'     => array_merge(
					$object,
					array(
						'properties' => array(
							'enable' => array( 'type' => 'boolean' ),
							'data'   => array(
								'type'  => 'array',
								'items' => $object,
							),
						),
					)
				),
				'download_resource' => array_merge(
					$object,
					array(
						'properties' => array(
							'file' => array(
								'type'  => 'array',
								'items' => $object,
							),
						),
					)
				),
				'video_settings'    => $object,
			),
		);
	}

	/** Register additional fields on the native post endpoint. */
	public static function register_settings() {
		register_rest_field(
			'ohmylms-lesson',
			'ohmylms_lesson_settings',
			array(
				'get_callback'    => array( __CLASS__, 'read_settings' ),
				'update_callback' => array( __CLASS__, 'save_settings' ),
				'schema'          => self::settings_schema(),
			)
		);
	}

	/**
	 * Normalize legacy empty values for the core REST schema without migrating data.
	 *
	 * @param array $post_data Prepared post.
	 * @return array Lesson settings.
	 */
	public static function read_settings( $post_data ) {
		$settings = array();
		$schema   = self::settings_schema();
		foreach ( self::META_KEYS as $property => $key ) {
			$value = get_post_meta( (int) $post_data['id'], $key, true );
			$type  = $schema['properties'][ $property ]['type'];
			if ( 'object' === $type ) {
				$value = is_array( $value ) && $value ? $value : (object) array();
			} elseif ( 'boolean' === $type ) {
				$value = rest_sanitize_boolean( $value );
			} elseif ( 'integer' === $type ) {
				$value = absint( $value );
			} else {
				$value = (string) $value;
			}
			$settings[ $property ] = 'type' === $property && '' === $value ? 'text' : $value;
		}
		return $settings;
	}

	/**
	 * Sanitize settings leaves while preserving established extension object shapes.
	 *
	 * @param mixed $value Settings value.
	 * @return mixed Sanitized value.
	 */
	public static function sanitize_setting( $value ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			$result = array();
			foreach ( (array) $value as $key => $item ) {
				$result[ $key ] = in_array( $key, array( 'url', 'external_url' ), true ) ? esc_url_raw( (string) $item ) : self::sanitize_setting( $item );
			}
			return $result;
		}
		return is_string( $value ) ? sanitize_text_field( $value ) : $value;
	}

	/**
	 * Save only submitted settings; core owns content, title, status and revisions.
	 *
	 * @param array    $value Submitted settings.
	 * @param \WP_Post $post Lesson post.
	 * @return true|\WP_Error Save result.
	 */
	public static function save_settings( $value, $post ) {
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new \WP_Error( 'rest_cannot_update', __( 'You cannot edit this lesson.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}
		$valid = rest_validate_value_from_schema( $value, self::settings_schema(), 'ohmylms_lesson_settings' );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$value = self::sanitize_setting( $value );
		$value = rest_sanitize_value_from_schema( $value, self::settings_schema() );
		if ( is_wp_error( $value ) ) {
			return $value;
		}
		foreach ( $value as $property => $setting ) {
			if ( 'external_url' === $property ) {
				$setting = esc_url_raw( $setting );
			}
			update_post_meta( $post->ID, self::META_KEYS[ $property ], $setting );
		}
		return true;
	}

	/**
	 * Preserve lesson lifecycle hooks after a native REST save.
	 *
	 * @param \WP_Post         $post Post.
	 * @param \WP_REST_Request $request Request.
	 * @param bool             $creating Whether this is a new lesson.
	 */
	public static function after_save( $post, $request, $creating ) {
		if ( ! get_post_meta( $post->ID, '_type', true ) ) {
			update_post_meta( $post->ID, '_type', 'text' );
		}
		do_action( 'ohmylms_lesson_meta_updated', ohmylms_get_lesson( $post->ID ) );
		// Placement remains managed by the LMS; an unrelated native post field cannot move a lesson.
		$lesson_request = clone $request;
		$lesson_request->set_param( 'chapter_id', 0 );
		do_action( 'ohmylms_rest_insert_lesson', $post, $lesson_request, $creating );
	}

	/**
	 * Read a safe LMS hash route for returning from the editor.
	 *
	 * @return string Same-site return URL.
	 */
	public static function return_url() {
		// Navigation only: this parameter never changes lesson data.
		$screen   = get_current_screen();
		$fallback = $screen && 'ohmylms-quiz' === $screen->post_type ? \OhMyLMS\Assessment\Profiles::of( get_the_ID() )['return_route'] : ( $screen && 'ohmylms-question' === $screen->post_type ? '/question-bank' : '/content-hub/lessons' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only editor navigation.
		$route = isset( $_GET['ohmylms_return'] ) ? wp_sanitize_redirect( wp_unslash( $_GET['ohmylms_return'] ) ) : $fallback;
		if ( ! preg_match( '#^/(?:content-hub|course-edit|question-bank)(?:/|$)#', $route ) || preg_match( '/[\r\n]/', $route ) ) {
			$route = $fallback;
		}
		return admin_url( 'admin.php?page=ohmylms' ) . '#' . $route;
	}

	/**
	 * Let existing LMS entry points discover the native editing URL.
	 *
	 * @param \WP_REST_Response $response Existing lesson response.
	 * @param \WP_Post          $post Post.
	 * @return \WP_REST_Response Response with additive editing URL.
	 */
	public static function legacy_response( $response, $post ) {
		$data = $response->get_data();
		$url  = self::edit_url( $post->ID );
		if ( $url ) {
			$data['native_editor_url'] = $url;
			$response->set_data( $data );
		}
		return $response;
	}

	/**
	 * Show standalone inline-check shortcodes as question blocks when a lesson opens in the editor.
	 *
	 * @param \WP_REST_Response $response Lesson response.
	 * @param \WP_Post          $post     Lesson.
	 * @param \WP_REST_Request  $request  Request.
	 * @return \WP_REST_Response
	 */
	public static function blocks_for_editor( $response, $post, $request ) {
		if ( 'edit' !== $request->get_param( 'context' ) || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $response;
		}
		$data = $response->get_data();
		if ( isset( $data['content']['raw'] ) && is_string( $data['content']['raw'] ) ) {
			$data['content']['raw'] = \OhMyLMS\Practice\Inline::blocks_from_shortcodes( $data['content']['raw'] );
			$response->set_data( $data );
		}
		return $response;
	}

	/**
	 * Expose direct editor links only for editable built-in lessons.
	 *
	 * @param int $lesson_id Lesson ID.
	 * @return string Native editor URL, or an empty string for custom lessons.
	 */
	public static function edit_url( $lesson_id ) {
		$type = get_post_meta( $lesson_id, '_type', true );
		$type = $type ? $type : 'text';
		if ( 'ohmylms-lesson' !== get_post_type( $lesson_id ) || ! in_array( $type, array( 'text', 'audio', 'video' ), true ) || ! current_user_can( 'edit_post', $lesson_id ) ) {
			return '';
		}
		return (string) get_edit_post_link( $lesson_id, 'raw' );
	}

	/** Enqueue only the settings extension on native lesson screens. */
	public static function enqueue() {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, array( 'ohmylms-lesson', 'ohmylms-quiz', 'ohmylms-question' ), true ) || ! $screen->is_block_editor() ) {
			return;
		}
		$asset_path = OHMYLMS_DIR . '/assets/dist/admin/native-lesson-editor.asset.php';
		if ( ! is_file( $asset_path ) ) {
			return;
		}
		$asset = require $asset_path;
		\OhMyLMS\Assessment\Interactive::enqueue();
		$dependencies = array_merge( $asset['dependencies'], array( 'ohmylms-interactive-visual', 'ohmylms-extended-questions', 'ohmylms-puzzle-loader' ) );
		wp_enqueue_script( 'ohmylms-native-lesson-settings', plugins_url( 'assets/dist/admin/native-lesson-editor.js', OHMYLMS_FILE ), $dependencies, $asset['version'], true );
		wp_localize_script( 'ohmylms-native-lesson-settings', 'ohmylmsPlayerTemplates', \OhMyLMS\Quiz\PlayerTemplates::choices() );
		do_action( 'ohmylms_enqueue_extension_scripts', 'ohmylms-native-lesson-settings' );
		// Reuse the lazy MathLive configuration on the native lesson screen as well as the LMS app.
		wp_enqueue_script( 'ohmylms-mathlive-loader' );
		wp_enqueue_style( 'ohmylms-question-editor', plugins_url( 'assets/css/question-editor.css', OHMYLMS_FILE ), array( 'wp-components', 'dashicons' ), OHMYLMS_VERSION );
		wp_localize_script(
			'ohmylms-native-lesson-settings',
			'ohmylmsNativeLesson',
			array(
				'returnUrl'       => self::return_url(),
				'previewUrl'      => 'ohmylms-quiz' === $screen->post_type ? \OhMyLMS\Assessment\PreviewPlayer::url( get_the_ID(), 'gutenberg' ) : '',
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presence flag for read-only navigation, not a submitted value.
				'hasReturn'       => isset( $_GET['ohmylms_return'] ),
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only initial skill selection; the question writer validates it on save.
				'initialSkillId'  => isset( $_GET['ohmylms_skill'] ) ? absint( $_GET['ohmylms_skill'] ) : 0,
				'questionTypes'   => \OhMyLMS\Extensions\Registry::manifest()['question'] ?? array(),
				'newQuestionUrl'  => admin_url( 'post-new.php?post_type=ohmylms-question' ),
				'bankUrl'         => admin_url( 'admin.php?page=ohmylms' ) . '#/question-bank',
				'chapterId'       => ohmylms_get_chapter_id_by_content_id( get_the_ID() ),
				'profile'         => 'ohmylms-quiz' === $screen->post_type ? \OhMyLMS\Assessment\Profiles::editor( get_the_ID() ) : null,
				'assessmentTypes' => array_values(
					array_map(
						static function ( $profile ) {
							return array(
								'value' => $profile['kind'],
								'label' => $profile['label'],
							);
						},
						\OhMyLMS\Assessment\Profiles::all()
					)
				),
				'printUrls'       => 'ohmylms-quiz' === $screen->post_type ? array(
					'paper' => \OhMyLMS\Exams\PrintPaper::url( get_the_ID(), 'paper' ),
					'key'   => \OhMyLMS\Exams\PrintPaper::url( get_the_ID(), 'key' ),
				) : null,
			)
		);
	}
}
