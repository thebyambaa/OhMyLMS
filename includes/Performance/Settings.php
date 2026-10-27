<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Skill tracking settings (option "ohmylms_performance"): which policy decides levels, which
 * activities count, and what the AI may do.
 *
 * @package OhMyLMS\Performance
 */

namespace OhMyLMS\Performance;

use OhMyLMS\Assessment\Profiles;
use OhMyLMS\Performance\Policies\EvidencePolicy;
use OhMyLMS\Performance\Policies\ScorePolicy;
use OhMyLMS\Performance\Policies\StepsPolicy;

defined( 'ABSPATH' ) || exit;

/** The single place skill tracking is configured. */
final class Settings {

	const OPTION = 'ohmylms_performance';

	/**
	 * Registered policies, keyed by id.
	 *
	 * @var Policy[]|null
	 */
	private static $policies = null;

	/**
	 * Defaults: evidence rules, every activity counts, AI notes off.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'policy'      => 'evidence',
			// Surface => whether its answers count as skill evidence; a missing surface uses its profile.
			'surfaces'    => array(),
			// Show the 0-100 mastery score beside the level.
			'show_score'  => true,
			'ai_insights' => false,
		);
	}

	/**
	 * Stored settings over the defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$stored   = get_option( self::OPTION, array() );
		$settings = array_merge( self::defaults(), is_array( $stored ) ? array_intersect_key( $stored, self::defaults() ) : array() );
		if ( ! isset( self::policies()[ $settings['policy'] ] ) ) {
			$settings['policy'] = 'evidence';
		}
		$settings['surfaces']    = array_map( 'boolval', (array) $settings['surfaces'] );
		$settings['show_score']  = (bool) $settings['show_score'];
		$settings['ai_insights'] = (bool) $settings['ai_insights'];
		return $settings;
	}

	/**
	 * Validate submitted settings against the current ones. Unknown keys are dropped.
	 *
	 * @param mixed $input Submitted values.
	 * @return array|\WP_Error The settings to store.
	 */
	public static function validate( $input ) {
		if ( ! is_array( $input ) ) {
			return new \WP_Error( 'ohmylms_performance_settings', __( 'Invalid skill tracking settings.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$next = array_merge( self::get(), array_intersect_key( $input, self::defaults() ) );
		if ( ! isset( self::policies()[ (string) $next['policy'] ] ) ) {
			return new \WP_Error( 'ohmylms_performance_policy', __( 'Unknown skill level policy.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$surfaces = array();
		foreach ( (array) $next['surfaces'] as $surface => $counts ) {
			if ( in_array( $surface, self::surfaces(), true ) ) {
				$surfaces[ $surface ] = (bool) $counts;
			}
		}
		$next['surfaces']    = $surfaces;
		$next['show_score']  = (bool) $next['show_score'];
		$next['ai_insights'] = (bool) $next['ai_insights'];
		return $next;
	}

	/**
	 * Store settings. A change to the policy or to which activities count rebuilds every state in the
	 * background, because states are derived from the evidence.
	 *
	 * @param mixed $input Submitted values.
	 * @return array|\WP_Error The stored settings.
	 */
	public static function save( $input ) {
		$next = self::validate( $input );
		if ( is_wp_error( $next ) ) {
			return $next;
		}
		$before = self::get();
		update_option( self::OPTION, $next, false );
		$after = self::get();
		if ( $before['policy'] !== $after['policy'] || array_diff_assoc( $before['surfaces'], $after['surfaces'] ) || array_diff_assoc( $after['surfaces'], $before['surfaces'] ) ) {
			Engine::schedule_rebuild( $before['surfaces'] !== $after['surfaces'] );
		}
		return $after;
	}

	/**
	 * What the settings screen needs: the settings, the policies to choose from, and the activities.
	 *
	 * @return array
	 */
	public static function payload() {
		$policies = array();
		foreach ( self::policies() as $id => $policy ) {
			$policies[] = array(
				'id'          => $id,
				'label'       => $policy->label(),
				'description' => $policy->description(),
			);
		}
		$surfaces = array();
		foreach ( self::surfaces() as $surface ) {
			$surfaces[] = array(
				'id'     => $surface,
				'label'  => Profiles::all()[ $surface ]['label'] ?? ucwords( str_replace( '-', ' ', $surface ) ),
				'counts' => self::counts( 'practice' === $surface || in_array( $surface, Profiles::kinds(), true ) ? 'quiz' : $surface, 0, $surface ),
			);
		}
		return self::get() + array(
			'policies'        => $policies,
			'surface_options' => $surfaces,
			'ai_ready'        => \OhMyLMS\AI\Settings::connection_configured(),
		);
	}

	/**
	 * Registered policies.
	 *
	 * @return Policy[] Keyed by id.
	 */
	public static function policies() {
		if ( null === self::$policies ) {
			$policies = array();
			foreach ( (array) apply_filters( 'ohmylms_performance_policies', array( new EvidencePolicy(), new StepsPolicy(), new ScorePolicy() ) ) as $policy ) {
				if ( $policy instanceof Policy ) {
					$policies[ $policy->id() ] = $policy;
				}
			}
			self::$policies = $policies + array( 'evidence' => new EvidencePolicy() );
		}
		return self::$policies;
	}

	/**
	 * The policy deciding levels now.
	 *
	 * @return Policy
	 */
	public static function policy() {
		return self::policies()[ self::get()['policy'] ];
	}

	/**
	 * Whether a learner may try a wrong practice answer again, and a quiz tells them their answers
	 * can move a skill up or down: the step policy's way of working.
	 *
	 * @return bool
	 */
	public static function retries() {
		return 'steps' === self::get()['policy'];
	}

	/**
	 * The activities whose answers can feed skills: the registered assessment kinds, practice, inline
	 * checks, the question player and placement tests.
	 *
	 * @return string[]
	 */
	public static function surfaces() {
		return array_values( array_unique( array_merge( Profiles::kinds(), array( 'practice', 'inline', 'question-player', 'placement' ) ) ) );
	}

	/**
	 * Whether answers from an activity count as skill evidence.
	 *
	 * @param string $source_type quiz, practice, inline or question-player (the evidence source types).
	 * @param int    $source_id   Attempt, session or item the grade came from.
	 * @param string $surface     The surface when already known, e.g. from the settings screen.
	 * @return bool
	 */
	public static function counts( $source_type, $source_id = 0, $surface = '' ) {
		$surface = '' !== $surface ? $surface : self::surface( $source_type, (int) $source_id );
		$saved   = self::get()['surfaces'];
		if ( isset( $saved[ $surface ] ) ) {
			return $saved[ $surface ];
		}
		return 'quiz' === $source_type || 'practice' === $source_type ? (bool) Profiles::get( $surface )['skills'] : true;
	}

	/**
	 * The surface an evidence source belongs to: an attempt's assessment kind, or the source type.
	 *
	 * @param string $source_type Evidence source type.
	 * @param int    $source_id   Source ID.
	 * @return string
	 */
	public static function surface( $source_type, $source_id ) {
		global $wpdb;
		if ( 'quiz' !== $source_type || ! $source_id ) {
			return (string) $source_type;
		}
		$quiz = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT quiz_id FROM ' . $wpdb->prefix . 'ohmylms_quiz_attempts WHERE id=%d', (int) $source_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh read of one attempt's quiz.
		return $quiz ? (string) \OhMyLMS\Assessment\AssessmentSettings::get( $quiz )['kind'] : 'quiz';
	}
}
