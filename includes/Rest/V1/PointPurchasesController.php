<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * PointPurchasesController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Engagement\GamificationRegistry;
use OhMyLMS\Engagement\PointPurchase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * `ohmylms/v1/point-purchases`: learners buy courses with bonus points; administrators refund such
 * orders and set a course's point price. See docs/api/gamification.md#point-purchases.
 *
 * @since 1.0.0
 */
class PointPurchasesController extends RestController {

	/**
	 * Capability for refunds and course point prices.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'point-purchases';

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$courses = array(
			'type'     => 'array',
			'required' => true,
			'items'    => array(
				'type'    => 'integer',
				'minimum' => 1,
			),
			'minItems' => 1,
			'maxItems' => PointPurchase::MAX_COURSES,
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'learner_permissions_check' ),
					'args'                => array( 'course_ids' => $courses ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/quote',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'quote' ),
					'permission_callback' => array( $this, 'learner_permissions_check' ),
					'args'                => array( 'course_ids' => $courses ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<order_id>[\d]+)/refund',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'refund' ),
					'permission_callback' => array( $this, 'admin_permissions_check' ),
					'args'                => array(
						'order_id' => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'reason'   => array(
							'type'      => 'string',
							'required'  => true,
							'minLength' => 1,
							'maxLength' => 500,
						),
						'confirm'  => array(
							'type'     => 'boolean',
							'required' => true,
						),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/courses/(?P<id>[\d]+)',
			array(
				'args' => array(
					'id' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_course' ),
					'permission_callback' => array( $this, 'course_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_course' ),
					'permission_callback' => array( $this, 'course_permissions_check' ),
					'args'                => array(
						'point_price'    => array(
							'type'    => 'integer',
							'minimum' => 0,
							'maximum' => GamificationRegistry::MAX_POINTS,
						),
						'points_enabled' => array( 'type' => 'boolean' ),
					),
				),
			)
		);
	}

	/**
	 * Learners must be logged in (cookie requests also need the REST nonce, checked by core).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function learner_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'ohmylms_points_not_logged_in', __( 'Log in to buy courses with points.', 'ohmylms' ), array( 'status' => 401 ) );
		}
		return true;
	}

	/**
	 * Refunds need the administrator capability.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function admin_permissions_check( $request ) {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return new WP_Error( 'ohmylms_refund_cannot_create', __( 'Sorry, you are not allowed to refund orders.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

	/**
	 * Course point prices need the administrator capability and `edit_post` on the course.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function course_permissions_check( $request ) {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return new WP_Error( 'ohmylms_points_cannot_edit', __( 'Sorry, you are not allowed to change point prices.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return $this->check_object_permission( $request, 'edit', array( OHMYLMS_COURSE_CPT ) );
	}

	/**
	 * Price a purchase.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function quote( $request ) {
		$quote = PointPurchase::quote( get_current_user_id(), (array) $request['course_ids'] );
		if ( is_wp_error( $quote ) ) {
			return $quote;
		}
		$quote['prices']     = (object) $quote['prices'];
		$quote['affordable'] = $quote['balance'] >= $quote['points'];
		$response            = rest_ensure_response( $quote );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/**
	 * Buy courses with points.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$result = PointPurchase::purchase( get_current_user_id(), (array) $request['course_ids'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$order              = ohmylms_ecommerce_get_order( $result['order_id'] );
		$result['redirect'] = $order ? $order->get_checkout_redirect_url() : '';
		$response           = rest_ensure_response( $result );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * Refund a points order.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function refund( $request ) {
		if ( true !== $request['confirm'] ) {
			return new WP_Error( 'ohmylms_points_confirm_required', __( 'Confirm the refund.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$result = PointPurchase::refund( (int) $request['order_id'], (string) $request['reason'], get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array_merge( array( 'order_id' => (int) $request['order_id'] ), $result ) );
	}

	/**
	 * Point price of a course.
	 *
	 * @param int $course_id Course.
	 * @return array
	 */
	private function course_data( $course_id ) {
		$points = get_post_meta( $course_id, '_purchase_point', true );
		return array(
			'course_id'        => (int) $course_id,
			'point_price'      => is_numeric( $points ) ? max( 0, (int) $points ) : 0,
			'points_enabled'   => 'yes' !== get_post_meta( $course_id, '_reward_disabled', true ),
			'purchase_enabled' => PointPurchase::enabled(),
		);
	}

	/**
	 * Read a course's point price.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_course( $request ) {
		return rest_ensure_response( $this->course_data( (int) $request['id'] ) );
	}

	/**
	 * Change a course's point price or opt-out.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_course( $request ) {
		$body    = (array) $request->get_json_params();
		$unknown = array_diff( array_keys( $body ), array( 'point_price', 'points_enabled' ) );
		if ( $unknown ) {
			return new WP_Error( 'ohmylms_settings_unknown_field', __( 'The request contains fields that cannot be changed.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$id = (int) $request['id'];
		if ( null !== $request['point_price'] ) {
			update_post_meta( $id, '_purchase_point', (int) $request['point_price'] );
		}
		if ( null !== $request['points_enabled'] ) {
			update_post_meta( $id, '_reward_disabled', $request['points_enabled'] ? 'no' : 'yes' );
		}
		return rest_ensure_response( $this->course_data( $id ) );
	}
}
