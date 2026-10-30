<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Setup wizard logic: step validation, side effects and progress.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\SetupWizard;

use OhMyLMS\Curriculum\Placement;
use OhMyLMS\Settings\SettingsService;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Applies one wizard step at a time. Every method is safe to repeat: saving a step again with the
 * same input changes nothing, and nothing here deletes or overwrites existing content.
 *
 * No method makes an outbound HTTP request or touches credentials. Permissions are checked by the
 * REST controller (`manage_options`); the pages step additionally needs `publish_pages`.
 *
 * @since 1.0.0
 */
final class SetupService {

	/**
	 * The wizard state returned to the admin app.
	 *
	 * @return array
	 */
	public static function state() {
		$progress    = SetupProgress::read();
		$steps       = array();
		$currency    = SettingsService::read( 'currency' );
		$definitions = SetupSteps::all();
		foreach ( $definitions as $id => $definition ) {
			$steps[] = array(
				'id'        => $id,
				'status'    => isset( $progress['steps'][ $id ] ) ? $progress['steps'][ $id ] : 'pending',
				'skippable' => $definition['skippable'],
				'available' => $definition['available'],
			);
		}

		return array(
			'version'       => SetupProgress::VERSION,
			'status'        => $progress['status'],
			'current_step'  => SetupProgress::first_unresolved( $progress ),
			'steps'         => $steps,
			'answers'       => $progress['answers'],
			'preferences'   => array(
				'currency'           => $currency['currency'],
				'currency_pos'       => $currency['currency_pos'],
				'price_num_decimals' => $currency['price_num_decimals'],
			),
			'starter_pages' => StarterPages::describe(),
			'started_at'    => $progress['started_at'],
			'completed_at'  => $progress['completed_at'],
		);
	}

	/**
	 * Validate the body of a step request against the step schema.
	 *
	 * @param string $step Step id.
	 * @param mixed  $body Decoded JSON body (null for none).
	 * @return array|WP_Error Sanitised input.
	 */
	public static function validate_input( $step, $body ) {
		$definition = SetupSteps::get( $step );
		if ( ! $definition ) {
			return new WP_Error( 'ohmylms_setup_unknown_step', __( 'Unknown setup step.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		if ( null === $body ) {
			$body = array();
		}
		if ( ! is_array( $body ) || ( $body && array_values( $body ) === $body ) ) {
			return new WP_Error( 'ohmylms_setup_invalid_body', __( 'Send a JSON object.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$properties = $definition['schema']['properties'];
		$unknown    = array();
		foreach ( array_keys( $body ) as $key ) {
			if ( ! isset( $properties[ $key ] ) ) {
				$unknown[ (string) $key ] = __( 'Unknown field.', 'ohmylms' );
			}
		}
		if ( $unknown ) {
			return new WP_Error(
				'ohmylms_setup_unknown_field',
				__( 'Unknown field(s) in the request.', 'ohmylms' ),
				array(
					'status' => 400,
					'params' => $unknown,
				)
			);
		}

		$problems = array();
		$clean    = array();
		foreach ( isset( $definition['schema']['required'] ) ? $definition['schema']['required'] : array() as $name ) {
			if ( ! array_key_exists( $name, $body ) ) {
				$problems[ $name ] = __( 'This field is required.', 'ohmylms' );
			}
		}
		foreach ( $body as $name => $value ) {
			// Core accepts a comma-separated string for an array; the wizard requires a real JSON array.
			if ( 'array' === $properties[ $name ]['type'] && ! is_array( $value ) ) {
				$problems[ $name ] = __( 'Must be a list.', 'ohmylms' );
				continue;
			}
			$result = rest_validate_value_from_schema( $value, $properties[ $name ], $name );
			if ( is_wp_error( $result ) ) {
				$problems[ $name ] = $result->get_error_message();
				continue;
			}
			$clean[ $name ] = rest_sanitize_value_from_schema( $value, $properties[ $name ], $name );
		}
		if ( $problems ) {
			return new WP_Error(
				'rest_invalid_param',
				__( 'Invalid parameter(s) in the request.', 'ohmylms' ),
				array(
					'status' => 400,
					'params' => $problems,
				)
			);
		}

		if ( isset( $clean['topics'] ) ) {
			$clean['topics'] = self::clean_topics( $clean['topics'] );
		}

		return $clean;
	}

	/**
	 * Sanitise topic names: plain text, no empty entries, no duplicates (case-insensitive).
	 *
	 * @param string[] $topics Topic names.
	 * @return string[]
	 */
	private static function clean_topics( array $topics ) {
		$clean = array();
		$seen  = array();
		foreach ( $topics as $topic ) {
			$name = trim( sanitize_text_field( (string) $topic ) );
			$key  = function_exists( 'mb_strtolower' ) ? mb_strtolower( $name ) : strtolower( $name );
			if ( '' === $name || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$clean[]      = $name;
		}

		return $clean;
	}

	/**
	 * Save a step and mark it done.
	 *
	 * @param string $step  Step id.
	 * @param mixed  $input Decoded request body.
	 * @return array|WP_Error `{changed: string[], pages: array}` of the step.
	 */
	public static function save_step( $step, $input ) {
		$definition = SetupSteps::get( $step );
		if ( ! $definition ) {
			return new WP_Error( 'ohmylms_setup_unknown_step', __( 'Unknown setup step.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		if ( ! $definition['available'] ) {
			return new WP_Error( 'ohmylms_setup_step_unavailable', __( 'This step is not available yet. You can skip it.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		$clean = self::validate_input( $step, $input );
		if ( is_wp_error( $clean ) ) {
			return $clean;
		}

		$progress = SetupProgress::read();
		$result   = array(
			'changed' => array(),
			'pages'   => array(),
		);

		switch ( $step ) {
			case 'experience':
				$progress['answers']['experience'] = $clean['experience'];
				break;

			case 'preferences':
				$values = SettingsService::validate( 'currency', $clean );
				if ( is_wp_error( $values ) ) {
					return self::rewrap_settings_error( $values );
				}
				$changed = SettingsService::write( 'currency', $values );
				if ( is_wp_error( $changed ) ) {
					return new WP_Error( 'ohmylms_setup_save_failed', __( 'The preferences could not be saved.', 'ohmylms' ), array( 'status' => 500 ) );
				}
				$result['changed'] = $changed;
				break;

			case 'niche':
				$progress['answers']['niche']  = $clean['niche'];
				$progress['answers']['topics'] = isset( $clean['topics'] ) ? $clean['topics'] : array();
				if ( $progress['answers']['topics'] ) {
					// Existing names are reused, so repeating the step never duplicates a topic.
					Placement::ensure_items( $progress['answers']['topics'] );
				}
				break;

			case 'pages':
				if ( ! current_user_can( 'publish_pages' ) ) {
					return new WP_Error( 'ohmylms_setup_cannot_publish_pages', __( 'Sorry, you are not allowed to publish pages.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
				}
				$pages = StarterPages::ensure();
				if ( is_wp_error( $pages ) ) {
					return $pages;
				}
				$result['pages'] = $pages;
				break;
		}

		$progress['steps'][ $step ] = 'done';

		return self::store( $progress, $result );
	}

	/**
	 * Skip a step. A step that is already done stays done.
	 *
	 * @param string $step Step id.
	 * @return array|WP_Error Empty step result.
	 */
	public static function skip_step( $step ) {
		$definition = SetupSteps::get( $step );
		if ( ! $definition ) {
			return new WP_Error( 'ohmylms_setup_unknown_step', __( 'Unknown setup step.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		if ( ! $definition['skippable'] ) {
			return new WP_Error( 'ohmylms_setup_step_required', __( 'This step cannot be skipped.', 'ohmylms' ), array( 'status' => 409 ) );
		}

		$progress = SetupProgress::read();
		if ( empty( $progress['steps'][ $step ] ) ) {
			$progress['steps'][ $step ] = 'skipped';
		}

		return self::store(
			$progress,
			array(
				'changed' => array(),
				'pages'   => array(),
			)
		);
	}

	/**
	 * Finish the wizard. Every step must be done or skipped.
	 *
	 * @return true|WP_Error
	 */
	public static function complete() {
		$progress = SetupProgress::read();
		if ( 'completed' === $progress['status'] ) {
			return true;
		}

		$missing = array();
		foreach ( SetupSteps::ids() as $step ) {
			if ( ! isset( $progress['steps'][ $step ] ) ) {
				$missing[] = $step;
			}
		}
		if ( $missing ) {
			return new WP_Error(
				'ohmylms_setup_incomplete',
				__( 'Finish or skip every step before completing the setup.', 'ohmylms' ),
				array(
					'status'  => 409,
					'missing' => $missing,
				)
			);
		}

		$progress['status']       = 'completed';
		$progress['completed_at'] = SetupProgress::now();
		if ( ! SetupProgress::write( $progress ) ) {
			return new WP_Error( 'ohmylms_setup_save_failed', __( 'The setup progress could not be saved.', 'ohmylms' ), array( 'status' => 500 ) );
		}

		/**
		 * Fires when the setup wizard is completed.
		 *
		 * @since 1.1.16
		 */
		do_action( 'ohmylms_onboarding_completed' );

		return true;
	}

	/**
	 * Leave the wizard without completing it. Progress is kept, so the wizard can be resumed.
	 *
	 * @param string $step The step the administrator was on (may be empty).
	 * @return true|WP_Error
	 */
	public static function dismiss( $step ) {
		$progress = SetupProgress::read();
		if ( 'completed' === $progress['status'] ) {
			return new WP_Error( 'ohmylms_setup_already_completed', __( 'The setup is already completed.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		if ( 'dismissed' !== $progress['status'] ) {
			$progress['status'] = 'dismissed';
			if ( ! SetupProgress::write( $progress ) ) {
				return new WP_Error( 'ohmylms_setup_save_failed', __( 'The setup progress could not be saved.', 'ohmylms' ), array( 'status' => 500 ) );
			}

			/**
			 * Fires when the administrator exits the setup wizard without completing it.
			 *
			 * @since 1.1.17
			 * @param string $step The wizard step id the administrator was on (empty when unknown).
			 */
			do_action( 'ohmylms_onboarding_skipped', $step );
		}

		return true;
	}

	/**
	 * Start the wizard over. Only the progress is reset: settings, pages and topics stay as they are,
	 * and the remembered answers prefill the forms.
	 *
	 * @return true|WP_Error
	 */
	public static function reset() {
		$progress                 = SetupProgress::read();
		$progress['status']       = 'not_started';
		$progress['steps']        = array();
		$progress['started_at']   = null;
		$progress['completed_at'] = null;
		if ( ! SetupProgress::write( $progress ) ) {
			return new WP_Error( 'ohmylms_setup_save_failed', __( 'The setup progress could not be saved.', 'ohmylms' ), array( 'status' => 500 ) );
		}

		return true;
	}

	/**
	 * Store progress after a step, moving a wizard that has not started (or was dismissed) to in progress.
	 *
	 * @param array $progress Progress with the step marked.
	 * @param array $result   Step result to return.
	 * @return array|WP_Error
	 */
	private static function store( array $progress, array $result ) {
		$started = false;
		if ( 'not_started' === $progress['status'] ) {
			$progress['status']     = 'in_progress';
			$progress['started_at'] = SetupProgress::now();
			$started                = true;
		} elseif ( 'dismissed' === $progress['status'] ) {
			$progress['status'] = 'in_progress';
		}
		if ( ! SetupProgress::write( $progress ) ) {
			return new WP_Error( 'ohmylms_setup_save_failed', __( 'The setup progress could not be saved.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		if ( $started ) {
			/**
			 * Fires when the setup wizard starts.
			 *
			 * @since 1.1.16
			 */
			do_action( 'ohmylms_onboarding_started' );
		}

		return $result;
	}

	/**
	 * Report a settings validation failure with the wizard's error code.
	 *
	 * @param WP_Error $error Error from the settings service.
	 * @return WP_Error
	 */
	private static function rewrap_settings_error( WP_Error $error ) {
		$data = $error->get_error_data();

		return new WP_Error(
			'ohmylms_setup_invalid_value',
			__( 'Some values are not valid.', 'ohmylms' ),
			array(
				'status' => 422,
				'params' => is_array( $data ) && isset( $data['params'] ) ? $data['params'] : array(),
			)
		);
	}
}
