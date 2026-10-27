<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Controller for handling user REST API endpoints.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Controller for handling user REST API endpoints.
 *
 * This class extends the RESTController abstract class and defines REST API routes
 * for User-related CRUD operations and many more.
 *
 * @since 1.0.0
 */
class SetupWizardController extends RestController {

	/**
	 * The base route for user base endpoints.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $base = 'setup-wizard';

	/**
	 * Check setup permission for Setup wizard controller.
	 */
	public function check_setup_permission() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Verify the nonce for incoming REST API requests.
	 * This method checks the 'nonce' header in the request against the expected nonce value for the setup wizard.
	 *
	 * @param WP_REST_Request $request The REST API request object.
	 * @return bool|WP_Error True if the nonce is valid, WP_Error otherwise.
	 *
	 * @since 1.1.18
	 */
	public function verify_nonce( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'nonce' );
		if ( ! wp_verify_nonce( $nonce, 'ohmylms_setup_wizard' ) ) {
			return new WP_Error( 'invalid_nonce', __( 'Invalid nonce.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Registers REST API routes for user operations.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_data' ),
					'permission_callback' => array( $this, 'check_setup_permission' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/activate-addon',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'activate_addon' ),
					'permission_callback' => array( $this, 'check_setup_permission' ),
					'args'                => array(
						'slug' => array(
							'required' => true,
							'type'     => 'string',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/onboarding-started',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'onboarding_started' ),
					'permission_callback' => array( $this, 'check_setup_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/onboarding-completed',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'onboarding_completed' ),
					'permission_callback' => array( $this, 'check_setup_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/onboarding-skipped',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'onboarding_skipped' ),
					'permission_callback' => array( $this, 'check_setup_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/import-course',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'import_course' ),
					'permission_callback' => array( $this, 'check_setup_permission' ),
				),
			)
		);
	}

	/**
	 * Fires the onboarding started telemetry event.
	 *
	 * @since  1.1.16
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function onboarding_started( WP_REST_Request $request ) {
		$nonce_verification = $this->verify_nonce( $request );

		if ( is_wp_error( $nonce_verification ) ) {
			return rest_ensure_response( $nonce_verification );
		}

		/**
		 * Fires when the setup wizard onboarding starts.
		 *
		 * @since 1.1.16
		 */
		do_action( 'ohmylms_onboarding_started' );

		return rest_ensure_response(
			array(
				'status'  => 'success',
				'message' => __( 'Onboarding started event tracked.', 'ohmylms' ),
			)
		);
	}

	/**
	 * Fires the onboarding completed telemetry event.
	 *
	 * @since  1.1.16
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function onboarding_completed( WP_REST_Request $request ) {
		$nonce_verification = $this->verify_nonce( $request );

		if ( is_wp_error( $nonce_verification ) ) {
			return rest_ensure_response( $nonce_verification );
		}

		/**
		 * Fires when the setup wizard onboarding is completed.
		 *
		 * @since 1.1.16
		 */
		do_action( 'ohmylms_onboarding_completed' );

		return rest_ensure_response(
			array(
				'status'  => 'success',
				'message' => __( 'Onboarding completed event tracked.', 'ohmylms' ),
			)
		);
	}

	/**
	 * Fires the onboarding skipped telemetry event.
	 *
	 * @since  1.1.17
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function onboarding_skipped( WP_REST_Request $request ) {
		$nonce_verification = $this->verify_nonce( $request );

		if ( is_wp_error( $nonce_verification ) ) {
			return rest_ensure_response( $nonce_verification );
		}

		$step = sanitize_text_field( (string) ( $request->get_param( 'step' ) ?? '' ) );

		/**
		 * Fires when the user exits the setup wizard without completing it.
		 *
		 * @since 1.1.17
		 * @param string $step The wizard step ID the user was on when they exited.
		 */
		do_action( 'ohmylms_onboarding_skipped', $step );

		return rest_ensure_response(
			array(
				'status'  => 'success',
				'message' => __( 'Onboarding skipped event tracked.', 'ohmylms' ),
			)
		);
	}

	/**
	 * Import a sample course from JSON data.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function import_course( WP_REST_Request $request ) {
		$nonce_verification = $this->verify_nonce( $request );

		if ( is_wp_error( $nonce_verification ) ) {
			return rest_ensure_response( $nonce_verification );
		}

		$courses_data = $request->get_json_params();

		if ( empty( $courses_data ) || ! is_array( $courses_data ) ) {
			return new WP_Error( 'invalid_course_data', __( 'Invalid course data provided.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$course_ids = array();
		$is_first   = ! get_option( 'ohmylms_first_course_created', false );

		foreach ( $courses_data as $course_data ) {
			if ( ! isset( $course_data['title'] ) ) {
				continue;
			}

			// Create the course.
			$course_id = wp_insert_post(
				array(
					'post_title'   => sanitize_text_field( $course_data['title'] ),
					'post_content' => wp_kses_post( $course_data['content'] ),
					'post_status'  => 'draft',
					'post_type'    => 'ohmylms-course',
					'post_author'  => get_current_user_id(),
				)
			);

			if ( is_wp_error( $course_id ) ) {
				continue;
			}
			$this->update_default_meta_data( $course_id );

			$course_ids[] = $course_id;

			// Import chapters and lessons.
			if ( ! empty( $course_data['contents'] ) && is_array( $course_data['contents'] ) ) {
				$chapter_order = 0;
				foreach ( $course_data['contents'] as $chapter_data ) {
					$this->import_chapter( $course_id, $chapter_data, $chapter_order++ );
				}
			}
		}

		if ( empty( $course_ids ) ) {
			return new WP_Error( 'course_import_failed', __( 'Failed to import sample course.', 'ohmylms' ), array( 'status' => 500 ) );
		}

		if ( $is_first ) {
			do_action( 'ohmylms_after_creating_first_course', $course_ids[0], null );
		}

		return rest_ensure_response(
			array(
				'status'     => 'success',
				'message'    => __( 'Sample course imported successfully.', 'ohmylms' ),
				'course_ids' => $course_ids,
			)
		);
	}


	/**
	 * Update default meta data
	 *
	 * @param int $course_id Course identifier.
	 */
	private function update_default_meta_data( $course_id ) {
		update_post_meta( $course_id, '_access_type', 'public' );
		update_post_meta( $course_id, '_level', 'beginner' );
		update_post_meta( $course_id, '_type', 'self-paced' );
		update_post_meta( $course_id, '_price_type', 'free' );
		update_post_meta( $course_id, '_price', 0 );
		update_post_meta( $course_id, '_regular_price', 0 );
	}

	/**
	 * Import a chapter and its contents.
	 *
	 * @param int   $course_id Course identifier.
	 * @param array $chapter_data Chapter data.
	 * @param int   $order Order.
	 */
	private function import_chapter( $course_id, $chapter_data, $order ) {
		if ( empty( $chapter_data['title'] ) ) {
			return;
		}

		// Create the chapter.
		$chapter_id = wp_insert_post(
			array(
				'post_title'   => sanitize_text_field( $chapter_data['title'] ),
				'post_content' => '',
				'post_status'  => 'publish',
				'post_type'    => 'ohmylms-chapter',
				'post_author'  => get_current_user_id(),
			)
		);

		if ( is_wp_error( $chapter_id ) ) {
			return;
		}

		// Associate chapter with course.
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_chapter_relationship';
		$wpdb->insert(
			$table_name,
			array(
				'course_id'    => $course_id,
				'chapter_id'   => $chapter_id,
				'order_number' => $order,
			)
		);

		// Import lessons for the chapter.
		if ( ! empty( $chapter_data['contents'] ) && is_array( $chapter_data['contents'] ) ) {
			$content_order = 0;
			foreach ( $chapter_data['contents'] as $content_data ) {
				$this->import_content( $chapter_id, $content_data, $content_order++ );
			}
		}
	}

	/**
	 * Import a content item (lesson, quiz, etc.) and associate it with a chapter.
	 *
	 * @param int   $chapter_id Chapter identifier.
	 * @param array $content_data Content data.
	 * @param int   $order Order.
	 */
	private function import_content( $chapter_id, $content_data, $order ) {
		if ( empty( $content_data['title'] ) || empty( $content_data['type'] ) ) {
			return;
		}

		$post_type = '';
		switch ( $content_data['type'] ) {
			case 'ohmylms-lesson':
				$post_type = 'ohmylms-lesson';
				break;
			case 'ohmylms-quiz':
				$post_type = 'ohmylms-quiz';
				break;
			// Add other content types here if needed.
			default:
				return;
		}

		// Create the content post.
		$content_id = wp_insert_post(
			array(
				'post_title'   => sanitize_text_field( $content_data['title'] ),
				'post_content' => wp_kses_post( $content_data['content'] ),
				'post_status'  => 'publish',
				'post_type'    => $post_type,
				'post_author'  => get_current_user_id(),
			)
		);

		if ( is_wp_error( $content_id ) ) {
			return;
		}

		// Associate content with chapter.
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_content_relationship';

		$content_type_for_db = isset( $content_data['content_type'] ) ? $content_data['content_type'] : '';
		if ( 'ohmylms-quiz' === $post_type ) {
			$content_type_for_db = 'quiz';
		}

		$wpdb->insert(
			$table_name,
			array(
				'chapter_id'   => $chapter_id,
				'content_id'   => $content_id,
				'content_type' => $content_type_for_db,
				'order_number' => $order,
			)
		);
	}


	/**
	 * Save setup wizard data
	 *
	 * @param mixed $request REST request.
	 */
	public function update_data( $request ) {
		$nonce_verification = $this->verify_nonce( $request );

		if ( is_wp_error( $nonce_verification ) ) {
			return rest_ensure_response( $nonce_verification );
		}

		$this->save_currency_data( $request );
		$this->create_contact( $request );
		$this->save_layout_settings( $request );
		$this->save_design_data( $request );

		if ( isset( $request['optin'] ) && is_array( $request['optin'] ) ) {
			foreach ( $request['optin'] as $key => $payment ) {
				update_option( $key, $payment );
			}
		}

		// Save full wizard data.
		if ( isset( $request['wizard_data'] ) ) {
			update_option( 'ohmylms_setup_wizard_data', $request['wizard_data'] );
		}

		// Save certificate ID if provided.
		if ( isset( $request['certificate_id'] ) ) {
			update_option( 'ohmylms_setup_wizard_certificate_id', $request['certificate_id'] );
		}

		flush_rewrite_rules( true );

		if ( 'yes' === get_option( 'ohmylms_allow_tracking', 'no' ) ) {
			/**
			 * Fires after tracking consent is accepted.
			 *
			 * @since 1.1.10
			 */
			do_action( 'ohmylms_after_accept_consent' );
		}

		return rest_ensure_response(
			array(
				'status'  => 'success',
				'message' => __( 'Save data successfully.', 'ohmylms' ),
			)
		);
	}

	/**
	 * Activate addon
	 *
	 * @param mixed $request REST request.
	 */
	public function activate_addon( $request ) {
		$slug = sanitize_text_field( $request['slug'] );

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have permissions to activate plugins.', 'ohmylms' ), array( 'status' => 403 ) );
		}

		$result = activate_plugin( $slug );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'status'  => 'success',
				'message' => __( 'Plugin activated successfully.', 'ohmylms' ),
			)
		);
	}


	/**
	 * Save email settings for Setup wizard controller.
	 *
	 * @param mixed $request REST request.
	 */
	private function save_email_settings( $request ) {

		if ( isset( $request['logo'] ) ) {
			update_option( 'ohmylms_email_branding_image', sanitize_text_field( $request['logo'] ) );
		}

		if ( isset( $request['branding_title'] ) ) {
			update_option( 'ohmylms_branding_title', sanitize_text_field( $request['branding_title'] ) );
		}

		if ( isset( $request['background_color'] ) ) {
			update_option( 'ohmylms_email_background_color', sanitize_text_field( $request['background_color'] ) );
		}

		if ( isset( $request['base_color'] ) ) {
			update_option( 'ohmylms_email_base_color', sanitize_text_field( $request['base_color'] ) );
		}

		if ( isset( $request['body_background_color'] ) ) {
			update_option( 'ohmylms_email_body_background_color', sanitize_text_field( $request['body_background_color'] ) );
		}

		if ( isset( $request['body_text_color'] ) ) {
			update_option( 'ohmylms_email_body_text_color', sanitize_text_field( $request['body_text_color'] ) );
		}

		if ( isset( $request['sender_name'] ) ) {
			update_option( 'ohmylms_email_sender_name', sanitize_text_field( $request['sender_name'] ) );
		}

		if ( isset( $request['sender_email_address'] ) ) {
			update_option( 'ohmylms_email_sender_email_address', sanitize_text_field( $request['sender_email_address'] ) );
		}

		if ( isset( $request['email_footer_text'] ) ) {
			update_option( 'ohmylms_email_footer_text', sanitize_text_field( $request['email_footer_text'] ) );
		}
	}

	/**
	 * The wizard's "category" names become top-level curriculum items (course categories were replaced
	 * by the curriculum). Names that already exist are left alone.
	 *
	 * @param mixed $request REST request.
	 */
	private function save_terms( $request ) {
		if ( isset( $request['category'] ) && is_array( $request['category'] ) ) {
			\OhMyLMS\Curriculum\Placement::ensure_items( array_map( 'sanitize_text_field', array_map( 'strval', $request['category'] ) ) );
		}
	}

	/**
	 * Save payment data for Setup wizard controller.
	 *
	 * @param mixed $request REST request.
	 */
	private function save_payment_data( $request ) {
		if ( isset( $request['payment'] ) && is_array( $request['payment'] ) ) {
			foreach ( $request['payment'] as $key => $payment ) {
				update_option( "{$key}", $payment );
			}
		}
	}

	/**
	 * Save permalink data for Setup wizard controller.
	 *
	 * @param mixed $request REST request.
	 */
	private function save_permalink_data( $request ) {
		if ( isset( $request['permalink'] ) && is_array( $request['permalink'] ) ) {
			foreach ( $request['permalink'] as $key => $payment ) {
				update_option( $key, $payment );
			}
			flush_rewrite_rules( true );
		}
	}

	/**
	 * Save currency data for Setup wizard controller.
	 *
	 * @param mixed $request REST request.
	 */
	private function save_currency_data( $request ) {
		if ( isset( $request['currency'] ) && is_array( $request['currency'] ) ) {
			foreach ( $request['currency'] as $key => $payment ) {
				update_option( $key, $payment );
			}
		}
	}

	/**
	 * Save design data for Setup wizard controller.
	 *
	 * @param mixed $request REST request.
	 */
	private function save_design_data( $request ) {
		if ( isset( $request['design'] ) && is_array( $request['design'] ) ) {
			foreach ( $request['design'] as $key => $design ) {
				update_option( $key, $design );
			}
		}
	}


	/**
	 * Create contact for Setup wizard controller.
	 *
	 * @param mixed $request REST request.
	 */
	private function create_contact( $request ) {
		if ( isset( $request['contact'] ) ) {
			$email      = isset( $request['contact']['email'] ) ? sanitize_text_field( $request['contact']['email'] ) : '';
			$name       = isset( $request['contact']['name'] ) ? sanitize_text_field( $request['contact']['name'] ) : '';
			$setup_data = isset( $request['wizard_data'] ) ? $request['wizard_data'] : array();

			$createContactInstance = new \OhMyLMS\SetupWizard\CreateContact( $email, $name, $setup_data );
			$response              = $createContactInstance->create_contact_via_webhook();
		}
	}

	/**
	 * Save layout settings for Setup wizard controller.
	 *
	 * @param mixed $request REST request.
	 */
	private function save_layout_settings( $request ) {
		$design_obj = new \OhMyLMS\Admin\Settings\Design();
		$settings   = $design_obj->get_settings();
		if ( is_array( $settings ) ) {
			foreach ( $settings as $setting ) {
				if ( isset( $setting['id'], $setting['value'] ) ) {
					if ( 'ohmylms_archive_page_row' == $setting['id'] ) {
						$setting['value'] = array();
					}
					update_option( $setting['id'], $setting['value'] );
				}
			}
		}
	}
}
