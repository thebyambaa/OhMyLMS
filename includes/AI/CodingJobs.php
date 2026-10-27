<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping.
/**
 * Pull-only development worker queue. WordPress never executes commands or files.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\AI;

use OhMyLMS\MCP\Server;

defined( 'ABSPATH' ) || exit;

/** A worker token cannot be used as an administrator REST session. */
final class CodingJobs {
	const OPTION = 'ohmylms_coding_worker';
	const JOBS   = 'ohmylms_coding_jobs';

	/** Register routes. */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/** Register admin and independently authenticated worker routes. */
	public static function routes() {
		foreach ( array(
			''          => 'GET',
			'/pair'     => 'POST',
			'/jobs'     => 'POST',
			'/decision' => 'POST',
		) as $suffix => $method ) {
			register_rest_route(
				'ohmylms/v1',
				'/admin-assistant/coding' . $suffix,
				array(
					'methods'             => $method,
					'permission_callback' => array( AssistantController::class, 'permission' ),
					'callback'            => array( __CLASS__, 'admin' ),
				)
			);
		}
		register_rest_route(
			'ohmylms/v1',
			'/admin-assistant/worker',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( __CLASS__, 'worker_permission' ),
				'callback'            => array( __CLASS__, 'worker' ),
			)
		);
	}

	/**
	 * A pinned local-development site and live token owner are required.
	 *
	 * @param \WP_REST_Request $request Worker request.
	 * @return bool|\WP_Error
	 */
	public static function worker_permission( $request ) {
		$config = get_option( self::OPTION, array() );
		$token  = $request->get_header( 'Authorization' );
		if ( ! self::local() || ! is_string( $token ) || ! preg_match( '/^Bearer (oml_worker_[a-f0-9]{64})$/', $token, $match ) || empty( $config['hash'] ) || ! hash_equals( $config['hash'], hash( 'sha256', $match[1] ) ) || ! user_can( $config['owner'], 'manage_options' ) || ! user_can( $config['owner'], 'edit_posts' ) ) {
			return new \WP_Error( 'worker_forbidden', __( 'Worker authentication failed.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		wp_set_current_user( $config['owner'] );
		return true;
	}

	/**
	 * Require an explicit local/development environment.
	 *
	 * @return bool Availability.
	 */
	private static function local() {
		return in_array( wp_get_environment_type(), array( 'local', 'development' ), true );
	}

	/**
	 * Identify the actual plugin checkout.
	 *
	 * @return string Checkout hash.
	 */
	private static function checkout() {
		return hash( 'sha256', str_replace( '\\', '/', realpath( dirname( OHMYLMS_FILE ) ) ) );
	}

	/**
	 * Describe worker status and this administrator's recent jobs.
	 *
	 * @return array Safe status.
	 */
	public static function summary() {
		$config = get_option( self::OPTION, array() );
		$jobs   = array_filter(
			(array) get_option( self::JOBS, array() ),
			static function ( $job ) {
				return get_current_user_id() === $job['owner'];
			}
		);
		return array(
			'available' => self::local(),
			'paired'    => ! empty( $config['hash'] ),
			'connected' => ! empty( $config['seen'] ) && time() - $config['seen'] < 90,
			'providers' => $config['providers'] ?? array(),
			'jobs'      => array_values( array_slice( $jobs, -10, null, true ) ),
		);
	}

	/**
	 * Serialize claims, approvals and reports across PHP processes.
	 *
	 * @param callable $callback Operation.
	 * @return mixed
	 */
	private static function locked( $callback ) {
		global $wpdb;
		$name = 'oml_jobs_' . md5( $wpdb->prefix );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $name ) ) ) {
			return new \WP_Error( 'worker_busy', __( 'The worker queue is busy. Retry shortly.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		try {
			return $callback();
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
		}
	}

	/**
	 * Admin decisions create jobs; no model or worker can approve application.
	 *
	 * @param \WP_REST_Request $request Admin request.
	 * @return array|\WP_Error
	 */
	public static function admin( $request ) {
		if ( 'GET' === $request->get_method() ) {
			return self::summary();
		}
		return self::locked(
			static function () use ( $request ) {
				if ( ! self::local() ) {
					return new \WP_Error( 'worker_environment', __( 'Coding jobs require a local or development WordPress environment.', 'ohmylms' ), array( 'status' => 403 ) );
				}
				$data = $request->get_json_params();
				if ( ! is_array( $data ) || strlen( $request->get_body() ) > 12000 ) {
					return new \WP_Error( 'worker_body', __( 'Invalid coding request.', 'ohmylms' ), array( 'status' => 400 ) );
				}
				if ( str_ends_with( $request->get_route(), '/pair' ) ) {
					foreach ( (array) get_option( self::JOBS, array() ) as $active_job ) {
						if ( in_array( $active_job['status'], array( 'queued', 'running', 'apply_queued', 'applying', 'rollback_queued', 'rolling_back' ), true ) ) {
							return new \WP_Error( 'worker_active', __( 'Finish the active job before replacing the worker connection. A stopped worker may need operator recovery.', 'ohmylms' ), array( 'status' => 409 ) );
						}
					}
					$token = 'oml_worker_' . bin2hex( random_bytes( 32 ) );
					update_option(
						self::OPTION,
						array(
							'hash'      => hash( 'sha256', $token ),
							'owner'     => get_current_user_id(),
							'checkout'  => self::checkout(),
							'seen'      => 0,
							'providers' => array(),
						),
						false
					);
					return array(
						'token'    => $token,
						'checkout' => self::checkout(),
						'endpoint' => rest_url( 'ohmylms/v1/admin-assistant/worker' ),
					);
				}
				$jobs = (array) get_option( self::JOBS, array() );
				if ( str_ends_with( $request->get_route(), '/jobs' ) ) {
					$config   = get_option( self::OPTION, array() );
					$provider = $data['provider'] ?? '';
					$prompt   = $data['prompt'] ?? '';
					if ( ! is_string( $provider ) || ! in_array( $provider, array( 'codex', 'claude', 'gemini' ), true ) || ! is_string( $prompt ) || ! trim( $prompt ) || mb_strlen( $prompt ) > 4000 || empty( $config['providers'][ $provider ]['ready'] ) || ! self::summary()['connected'] || (int) ( $config['owner'] ?? 0 ) !== get_current_user_id() ) {
						return new \WP_Error( 'worker_not_ready', __( 'Connect a worker with this provider configured, then enter a request of up to 4,000 characters.', 'ohmylms' ), array( 'status' => 409 ) );
					}
					foreach ( $jobs as $job ) {
						if ( in_array( $job['status'], array( 'queued', 'running', 'applying', 'apply_queued', 'rollback_queued', 'rolling_back' ), true ) ) {
							return new \WP_Error( 'worker_active', __( 'Finish the active coding job first.', 'ohmylms' ), array( 'status' => 409 ) );
						}
					}
					$id          = wp_generate_uuid4();
					$jobs[ $id ] = array(
						'id'       => $id,
						'owner'    => get_current_user_id(),
						'provider' => $provider,
						'prompt'   => sanitize_textarea_field( $prompt ),
						'status'   => 'queued',
						'created'  => gmdate( 'c' ),
						'checkout' => self::checkout(),
						'pair'     => $config['hash'],
					);
					// Retain recoverable reviews; require operator cleanup rather than deleting them.
					if ( count( $jobs ) > 50 ) {
						return new \WP_Error( 'worker_full', __( 'The development queue needs operator archival before adding more jobs.', 'ohmylms' ), array( 'status' => 409 ) );
					}
				} else {
					$id       = $data['id'] ?? '';
					$decision = $data['decision'] ?? '';
					if ( ! is_string( $id ) || ! isset( $jobs[ $id ] ) || get_current_user_id() !== $jobs[ $id ]['owner'] || ! is_string( $data['digest'] ?? null ) || ! hash_equals( $jobs[ $id ]['digest'] ?? '', $data['digest'] ) || ! $data['digest'] ) {
						return new \WP_Error( 'worker_review', __( 'Refresh and review this job before deciding.', 'ohmylms' ), array( 'status' => 409 ) );
					}
					$job    = &$jobs[ $id ];
					$config = get_option( self::OPTION, array() );
					if ( ( $job['pair'] ?? '' ) !== ( $config['hash'] ?? '' ) ) {
						return new \WP_Error( 'worker_repaired', __( 'This review belongs to an earlier worker connection.', 'ohmylms' ), array( 'status' => 409 ) );
					}
					if ( 'review' === $job['status'] && 'approve' === $decision && ! empty( $job['checks_passed'] ) ) {
						$job['status'] = 'apply_queued';
					} elseif ( 'review' === $job['status'] && 'reject' === $decision ) {
						$job['status'] = 'rejected';
					} elseif ( 'applied' === $job['status'] && 'rollback' === $decision ) {
						$job['status'] = 'rollback_queued';
					} else {
						return new \WP_Error( 'worker_decision', __( 'This decision is unavailable for the current job state.', 'ohmylms' ), array( 'status' => 409 ) );
					}
				}
				update_option( self::JOBS, $jobs, false );
				return self::summary();
			}
		);
	}

	/**
	 * Worker heartbeat/claim/report. Reports cannot approve or apply an update.
	 *
	 * @param \WP_REST_Request $request Worker request.
	 * @return array|\WP_Error
	 */
	public static function worker( $request ) {
		return self::locked(
			static function () use ( $request ) {
				$data   = $request->get_json_params();
				$config = get_option( self::OPTION, array() );
				if ( ! is_array( $data ) || strlen( $request->get_body() ) > 300000 || ! is_string( $data['checkout'] ?? null ) || ! hash_equals( self::checkout(), $data['checkout'] ) ) {
					return new \WP_Error( 'worker_checkout', __( 'Worker checkout does not match this plugin.', 'ohmylms' ), array( 'status' => 400 ) );
				}
				$config['seen'] = time();
				foreach ( array( 'codex', 'claude', 'gemini' ) as $provider ) {
					$state                            = $data['providers'][ $provider ] ?? array();
					$detail                           = is_string( $state['detail'] ?? null ) ? $state['detail'] : '';
					$config['providers'][ $provider ] = array(
						'ready'  => true === ( $state['ready'] ?? false ),
						'detail' => sanitize_text_field( substr( $detail, 0, 300 ) ),
					);
				}
				update_option( self::OPTION, $config, false );
				$jobs = (array) get_option( self::JOBS, array() );
				if ( ! empty( $data['report'] ) ) {
					$report = $data['report'];
					$id     = $report['id'] ?? '';
					if ( ! is_array( $report ) || ! is_string( $id ) || ! isset( $jobs[ $id ] ) || ! is_string( $report['status'] ?? null ) || ! is_string( $report['lease'] ?? null ) || ! hash_equals( $jobs[ $id ]['lease'] ?? '', $report['lease'] ) || ( $jobs[ $id ]['pair'] ?? '' ) !== $config['hash'] ) {
						return new \WP_Error( 'worker_lease', 'Invalid job lease.', array( 'status' => 409 ) );
					}
					$job = &$jobs[ $id ];
					if ( ( $report['status'] ?? '' ) === $job['status'] ) {
						return array( 'received' => true );
					}
					$allowed = array(
						'running'      => array( 'review', 'failed' ),
						'applying'     => array( 'applied', 'failed' ),
						'rolling_back' => array( 'rolled_back', 'failed' ),
					);
					$status  = $report['status'] ?? '';
					if ( ! in_array( $status, $allowed[ $job['status'] ] ?? array(), true ) || ( 'review' === $status && ( ! is_string( $report['digest'] ?? null ) || ! preg_match( '/^[a-f0-9]{64}$/', $report['digest'] ) ) ) ) {
						return new \WP_Error( 'worker_state', 'Invalid job transition.', array( 'status' => 409 ) );
					}
					$job['status'] = $status;
					foreach ( array(
						'summary' => 12000,
						'diff'    => 200000,
						'checks'  => 20000,
						'digest'  => 64,
					) as $field => $limit ) {
						if ( isset( $report[ $field ] ) && is_string( $report[ $field ] ) ) {
							// JSON text rendered by React: preserve code in the review rather than stripping HTML tags.
							$job[ $field ] = wp_check_invalid_utf8( substr( $report[ $field ], 0, $limit ) );
						}
					}
					if ( 'review' === $status ) {
						$job['checks_passed'] = true === ( $report['checks_passed'] ?? false );
					}
					$job['updated'] = gmdate( 'c' );
					update_option( self::JOBS, $jobs, false );
					return array( 'received' => true );
				}
				if ( ! empty( $data['heartbeat'] ) ) {
					return array( 'job' => null );
				}
				foreach ( $jobs as &$job ) {
					if ( ( $job['pair'] ?? '' ) !== $config['hash'] || get_current_user_id() !== $job['owner'] ) {
						continue;
					}
					$claims = array(
						'queued'          => 'running',
						'apply_queued'    => 'applying',
						'rollback_queued' => 'rolling_back',
					);
					if ( isset( $claims[ $job['status'] ] ) ) {
						$job['operation'] = $job['status'];
						$job['status']    = $claims[ $job['status'] ];
						$job['lease']     = wp_generate_uuid4();
						update_option( self::JOBS, $jobs, false );
						return array( 'job' => $job );
					}
				}
				return array( 'job' => null );
			}
		);
	}
}
