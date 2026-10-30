<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Administrator endpoints for the xAPI Settings tab.
 *
 * @package OhMyLMS\XApi
 */

namespace OhMyLMS\XApi;

defined( 'ABSPATH' ) || exit;

/** Exposes the existing emitter controls without returning the saved secret. */
final class SettingsController {

	/** Register settings and explicit delivery actions. */
	public static function register_routes() {
		$groups = array();
		foreach ( array_keys( Settings::groups() ) as $group ) {
			$groups[ $group ] = array( 'type' => 'boolean' );
		}
		register_rest_route(
			'ohmylms/v1',
			'/xapi/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'read' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'save' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
					'args'                => array(
						'enabled'        => array(
							'type'     => 'boolean',
							'required' => true,
						),
						'endpoint'       => array(
							'type'     => 'string',
							'required' => true,
						),
						'key'            => array(
							'type'     => 'string',
							'required' => true,
						),
						'secret'         => array( 'type' => 'string' ),
						'clear_secret'   => array( 'type' => 'boolean' ),
						'actor'          => array(
							'type'     => 'string',
							'enum'     => array( 'pseudonym', 'user_id', 'mbox' ),
							'required' => true,
						),
						'responses'      => array(
							'type'     => 'boolean',
							'required' => true,
						),
						'retention_days' => array(
							'type'     => 'integer',
							'minimum'  => 1,
							'maximum'  => 365,
							'required' => true,
						),
						'groups'         => array(
							'type'                 => 'object',
							'properties'           => $groups,
							'additionalProperties' => false,
							'required'             => true,
						),
					),
				),
			)
		);
		register_rest_route(
			'ohmylms/v1',
			'/xapi/settings/(?P<action>test|send|retry)',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'run_action' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);
	}

	/**
	 * Restrict credentials and delivery controls to site administrators.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( AdminPage::CAPABILITY );
	}

	/**
	 * Read settings, delivery health and recent failures.
	 *
	 * @return array
	 */
	public static function read() {
		$settings   = Settings::all();
		$has_secret = '' !== $settings['secret'];
		$ready      = Schema::ready();
		unset( $settings['secret'] );
		return array(
			'settings'     => $settings,
			'has_secret'   => $has_secret,
			'locked'       => array(
				'endpoint' => Settings::locked( 'endpoint' ),
				'key'      => Settings::locked( 'key' ),
				'secret'   => Settings::locked( 'secret' ),
			),
			'group_labels' => Settings::groups(),
			'counts'       => $ready ? Outbox::counts() : array(
				'pending' => 0,
				'sent'    => 0,
				'failed'  => 0,
				'skipped' => 0,
			),
			'health'       => Processor::health(),
			'failures'     => $ready ? Outbox::recent_failed( 10 ) : array(),
		);
	}

	/**
	 * Save validated fields through the existing settings contract.
	 *
	 * @param \WP_REST_Request $request Settings body.
	 * @return array|\WP_Error
	 */
	public static function save( $request ) {
		$input = $request->get_json_params();
		if ( ! is_array( $input ) ) {
			return new \WP_Error( 'ohmylms_xapi_invalid_settings', __( 'Send settings as a JSON object.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$endpoint = $input['endpoint'] ?? '';
		if ( ! Settings::locked( 'endpoint' ) && '' !== $endpoint && '' === Settings::normalize_endpoint( $endpoint ) ) {
			return new \WP_Error( 'ohmylms_xapi_invalid_endpoint', __( 'Enter a valid HTTP or HTTPS LRS endpoint.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		Settings::save( $input );
		Processor::schedule();
		return self::read();
	}

	/**
	 * Run an administrator-requested connection or queue action.
	 *
	 * @param \WP_REST_Request $request Requested action.
	 * @return array
	 */
	public static function run_action( $request ) {
		$ok = true;
		switch ( $request['action'] ) {
			case 'test':
				$result  = LrsClient::test();
				$ok      = $result['ok'];
				$message = $result['message'];
				break;
			case 'send':
				Processor::process( Processor::BATCH, 20 );
				$message = __( 'Queued statements were processed.', 'ohmylms' );
				break;
			default:
				Outbox::requeue_failed();
				$message = __( 'Failed statements were queued again.', 'ohmylms' );
		}
		return array(
			'ok'       => $ok,
			'message'  => $message,
			'snapshot' => self::read(),
		);
	}
}
