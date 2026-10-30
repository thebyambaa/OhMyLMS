<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Stored progress of the setup wizard.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\SetupWizard;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the one small, versioned option that records the wizard's progress.
 *
 * Shape (version 1): `status`, `steps` (step id => `done` | `skipped`), `answers` (`experience`,
 * `niche`, `topics`), `started_at`, `completed_at`. Settings the wizard changes (currency, page ids)
 * live in their own options through the settings registry and are never copied here.
 *
 * @since 1.0.0
 */
final class SetupProgress {

	/**
	 * Option name.
	 */
	const OPTION = 'ohmylms_setup_progress';

	/**
	 * Storage format version.
	 */
	const VERSION = 1;

	/**
	 * Statuses.
	 *
	 * @var string[]
	 */
	const STATUSES = array( 'not_started', 'in_progress', 'completed', 'dismissed' );

	/**
	 * Empty progress.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'version'      => self::VERSION,
			'status'       => 'not_started',
			'steps'        => array(),
			'answers'      => array(
				'experience' => null,
				'niche'      => null,
				'topics'     => array(),
			),
			'started_at'   => null,
			'completed_at' => null,
		);
	}

	/**
	 * Read the progress; unreadable or foreign data reads as not started.
	 *
	 * @return array
	 */
	public static function read() {
		$stored   = get_option( self::OPTION, null );
		$progress = self::defaults();
		if ( ! is_array( $stored ) || ! isset( $stored['version'] ) || self::VERSION !== $stored['version'] ) {
			return $progress;
		}

		if ( isset( $stored['status'] ) && in_array( $stored['status'], self::STATUSES, true ) ) {
			$progress['status'] = $stored['status'];
		}
		if ( isset( $stored['steps'] ) && is_array( $stored['steps'] ) ) {
			foreach ( $stored['steps'] as $step => $state ) {
				if ( SetupSteps::has( $step ) && in_array( $state, array( 'done', 'skipped' ), true ) ) {
					$progress['steps'][ $step ] = $state;
				}
			}
		}
		$answers = isset( $stored['answers'] ) && is_array( $stored['answers'] ) ? $stored['answers'] : array();
		if ( isset( $answers['experience'] ) && in_array( $answers['experience'], SetupSteps::EXPERIENCES, true ) ) {
			$progress['answers']['experience'] = $answers['experience'];
		}
		if ( isset( $answers['niche'] ) && in_array( $answers['niche'], SetupSteps::NICHES, true ) ) {
			$progress['answers']['niche'] = $answers['niche'];
		}
		if ( isset( $answers['topics'] ) && is_array( $answers['topics'] ) ) {
			$topics = array_values( array_filter( array_filter( $answers['topics'], 'is_string' ), 'strlen' ) );

			$progress['answers']['topics'] = array_slice( $topics, 0, SetupSteps::MAX_TOPICS );
		}
		foreach ( array( 'started_at', 'completed_at' ) as $key ) {
			if ( isset( $stored[ $key ] ) && is_string( $stored[ $key ] ) ) {
				$progress[ $key ] = $stored[ $key ];
			}
		}

		return $progress;
	}

	/**
	 * Store the progress (not autoloaded).
	 *
	 * @param array $progress Progress as returned by {@see self::read()}.
	 * @return bool Whether the stored value now equals the given progress.
	 */
	public static function write( array $progress ) {
		$progress['version'] = self::VERSION;
		update_option( self::OPTION, $progress, false );

		return get_option( self::OPTION ) === $progress;
	}

	/**
	 * Current UTC time in ISO 8601.
	 *
	 * @return string
	 */
	public static function now() {
		return gmdate( 'Y-m-d\TH:i:s\Z' );
	}

	/**
	 * The first step that is neither done nor skipped.
	 *
	 * @param array $progress Progress.
	 * @return string|null Null when every step is resolved.
	 */
	public static function first_unresolved( array $progress ) {
		foreach ( SetupSteps::ids() as $step ) {
			if ( ! isset( $progress['steps'][ $step ] ) ) {
				return $step;
			}
		}

		return null;
	}
}
