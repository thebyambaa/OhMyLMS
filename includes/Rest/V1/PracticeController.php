<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Learner practice: skill sessions, inline checks, guest credentials and claims, and the
 * learner's own skill progress. Logged-in learners use cookie auth; guests send the
 * X-OhMyLMS-Guest credential header. Nothing here exposes answers before grading.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Assessment\Engine;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Practice\Guests;
use OhMyLMS\Practice\Inline;
use OhMyLMS\Practice\Sessions;
use OhMyLMS\Skills\Evidence;
use OhMyLMS\Skills\Recommendations;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Learner practice: skill sessions, inline checks, guest credentials and claims, and the
 * learner's own skill progress. Logged-in learners use cookie auth; guests send the
 * X-OhMyLMS-Guest credential header. Nothing here exposes answers before grading.
 */
class PracticeController extends RestController {
	/**
	 * Register routes for Practice controller.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/practice/sessions',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'start' ),
					'permission_callback' => array( $this, 'owner_permission' ),
					'args'                => array(
						'term_id'    => array(
							'required' => true,
							'type'     => 'integer',
						),
						'item_limit' => array(
							'type'    => 'integer',
							'default' => 10,
						),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/practice/sessions/(?P<uuid>[0-9a-f-]{36})',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'show' ),
					'permission_callback' => array( $this, 'owner_permission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/practice/sessions/(?P<uuid>[0-9a-f-]{36})/answer',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'answer' ),
					'permission_callback' => array( $this, 'owner_permission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/practice/sessions/(?P<uuid>[0-9a-f-]{36})/hint',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'hint' ),
					'permission_callback' => array( $this, 'owner_permission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/practice/sessions/(?P<uuid>[0-9a-f-]{36})/skip',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'skip' ),
					'permission_callback' => array( $this, 'owner_permission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/practice/sessions/(?P<uuid>[0-9a-f-]{36})/ai',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'ai' ),
					'permission_callback' => array( $this, 'owner_permission' ),
					'args'                => array(
						'item_id' => array(
							'required' => true,
							'type'     => 'integer',
						),
						'kind'    => array(
							'required' => true,
							'type'     => 'string',
							'enum'     => array( 'hint', 'explain' ),
						),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/practice/inline',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'inline' ),
					'permission_callback' => array( $this, 'owner_permission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/practice/guest',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'guest' ),
					'permission_callback' => array( $this, 'guest_permission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/practice/claim',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'claim' ),
					'permission_callback' => 'is_user_logged_in',
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/me/skills',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'my_skills' ),
					'permission_callback' => 'is_user_logged_in',
				),
			)
		);
	}

	/** Practice must be enabled; the owner is the logged-in learner or a valid guest credential.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function owner_permission( WP_REST_Request $request ) {
		if ( ! Schema::ready() || ! Engine::practice() ) {
			return new WP_Error( 'ohmylms_practice_disabled', __( 'Practice is not available.', 'ohmylms' ), array( 'status' => 404 ) ); }
		if ( is_user_logged_in() ) {
			return true; }
		return Guests::from_request( $request ) ? true : new WP_Error( 'ohmylms_guest_required', __( 'Start a guest session or log in.', 'ohmylms' ), array( 'status' => 401 ) );
	}

	/**
	 * Guest permission for Practice controller.
	 */
	public function guest_permission() {
		if ( ! Schema::ready() || ! Engine::practice() ) {
			return new WP_Error( 'ohmylms_practice_disabled', __( 'Practice is not available.', 'ohmylms' ), array( 'status' => 404 ) ); }
		if ( is_user_logged_in() ) {
			return new WP_Error( 'ohmylms_guest_unneeded', __( 'You are logged in; no guest session is needed.', 'ohmylms' ), array( 'status' => 409 ) ); }
		// Light abuse protection: a handful of guest sessions per address per hour.
		$key   = 'ohmylms_guest_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		$count = (int) get_transient( $key );
		if ( $count >= 20 ) {
			return new WP_Error( 'ohmylms_guest_limit', __( 'Too many guest sessions. Please try again later.', 'ohmylms' ), array( 'status' => 429 ) ); }
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}

	/**
	 * Owner for Practice controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	private function owner( WP_REST_Request $request ) {
		if ( is_user_logged_in() ) {
			return array( 'student_id' => get_current_user_id() ); }
		$guest = Guests::from_request( $request );
		return $guest ? array( 'guest_id' => (int) $guest['id'] ) : array();
	}

	/**
	 * Session for Practice controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	private function session( WP_REST_Request $request ) {
		$session = Sessions::get( (string) $request['uuid'] );
		// Placement tests run through the same routes, for learners and guests alike.
		if ( ! $session || ! in_array( $session['mode'], array( 'skill', \OhMyLMS\Placement\Placement::MODE ), true ) || ! Sessions::owns( $session, $this->owner( $request ) ) ) {
			return new WP_Error( 'ohmylms_practice_missing', __( 'Practice session not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$access = \OhMyLMS\Learning\PracticeAccessPolicy::session( $session );
		if ( is_wp_error( $access ) ) {
			return $access; }
		return $session;
	}

	/**
	 * Start for Practice controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function start( WP_REST_Request $request ) {
		$session = Sessions::start(
			$this->owner( $request ),
			(int) $request['term_id'],
			array(
				'item_limit' => (int) $request['item_limit'],
				'style'      => (string) $request['style'],
				'course_id'  => (int) $request['course_id'],
			)
		);
		if ( is_wp_error( $session ) ) {
			return $session; }
		$response = rest_ensure_response( Sessions::state( $session ) );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * Show for Practice controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function show( WP_REST_Request $request ) {
		$session = $this->session( $request );
		return is_wp_error( $session ) ? $session : rest_ensure_response( Sessions::state( $session ) );
	}

	/**
	 * Answer for Practice controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function answer( WP_REST_Request $request ) {
		$session = $this->session( $request );
		if ( is_wp_error( $session ) ) {
			return $session; }
		$result = Sessions::answer( $session, (int) $request['item_id'], $request['response'] ?? array() );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * Hint for Practice controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function hint( WP_REST_Request $request ) {
		$session = $this->session( $request );
		if ( is_wp_error( $session ) ) {
			return $session; }
		$result = Sessions::hint( $session, (int) $request['item_id'] );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * Skip for Practice controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function skip( WP_REST_Request $request ) {
		$session = $this->session( $request );
		if ( is_wp_error( $session ) ) {
			return $session; }
		$result = Sessions::skip( $session, (int) $request['item_id'] );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * Ai for Practice controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function ai( WP_REST_Request $request ) {
		$session = $this->session( $request );
		if ( is_wp_error( $session ) ) {
			return $session; }
		$result = Sessions::ai_help( $session, (int) $request['item_id'], (string) $request['kind'] );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * Inline for Practice controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function inline( WP_REST_Request $request ) {
		$result = Inline::answer( (string) $request['token'], $request['response'] ?? array(), $this->owner( $request ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * Guest for Practice controller.
	 */
	public function guest() {
		$guest    = Guests::create();
		$response = rest_ensure_response( $guest );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * Claim for Practice controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function claim( WP_REST_Request $request ) {
		$result = Guests::claim( (string) $request['guest_token'], get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			return $result; }
		Evidence::process( 200 );
		return rest_ensure_response( $result );
	}

	/**
	 * My skills for Practice controller.
	 */
	public function my_skills() {
		Evidence::process( 50 );
		$student = get_current_user_id();
		return rest_ensure_response(
			array(
				'skills'          => Evidence::summary( $student ),
				'recommendations' => Recommendations::for_student( $student ),
			)
		);
	}
}
