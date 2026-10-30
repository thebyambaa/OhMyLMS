<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Settings for the xAPI emitter.
 *
 * @package OhMyLMS\XApi
 */

namespace OhMyLMS\XApi;

defined( 'ABSPATH' ) || exit;

/**
 * Connection and privacy settings for the xAPI emitter.
 *
 * The LRS credentials can be fixed in wp-config.php with OHMYLMS_XAPI_ENDPOINT, OHMYLMS_XAPI_KEY
 * and OHMYLMS_XAPI_SECRET. A constant always wins over the stored option and keeps the secret
 * out of the database.
 */
final class Settings {

	const OPTION           = 'ohmylms_xapi_settings';
	const SITE_ID_OPTION   = 'ohmylms_xapi_site_id';
	const ACTOR_KEY_OPTION = 'ohmylms_xapi_actor_key';

	/**
	 * Event group => label. A group maps one or more statement kinds to a single switch.
	 *
	 * @return array<string,string>
	 */
	public static function groups() {
		return array(
			'enrolled' => __( 'Course enrolment', 'ohmylms' ),
			'lessons'  => __( 'Lesson completion', 'ohmylms' ),
			'courses'  => __( 'Course completion', 'ohmylms' ),
			'attempts' => __( 'Quiz attempts and results', 'ohmylms' ),
			'answers'  => __( 'Individual question answers', 'ohmylms' ),
			'skills'   => __( 'Skill mastery', 'ohmylms' ),
		);
	}

	/**
	 * Statement kind => event group.
	 *
	 * @return array<string,string>
	 */
	public static function kind_groups() {
		return array(
			'enrolled'          => 'enrolled',
			'lesson_completed'  => 'lessons',
			'course_completed'  => 'courses',
			'attempt_completed' => 'attempts',
			'attempt_graded'    => 'attempts',
			'answered'          => 'answers',
			'skill_mastered'    => 'skills',
		);
	}

	/**
	 * Defaults. The emitter is off until an administrator turns it on.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled'        => 0,
			'endpoint'       => '',
			'key'            => '',
			'secret'         => '',
			'actor'          => 'pseudonym',
			'groups'         => array_fill_keys( array_keys( self::groups() ), 1 ),
			'responses'      => 1,
			'retention_days' => 14,
		);
	}

	/**
	 * Effective settings: stored values over defaults, constants over both.
	 *
	 * @return array
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$values = array_merge( self::defaults(), $stored );

		$values['groups'] = array_merge( self::defaults()['groups'], is_array( $values['groups'] ) ? $values['groups'] : array() );
		foreach ( array(
			'OHMYLMS_XAPI_ENDPOINT' => 'endpoint',
			'OHMYLMS_XAPI_KEY'      => 'key',
			'OHMYLMS_XAPI_SECRET'   => 'secret',
		) as $constant => $field ) {
			if ( defined( $constant ) && '' !== (string) constant( $constant ) ) {
				$values[ $field ] = (string) constant( $constant );
			}
		}

		return $values;
	}

	/**
	 * Whether a field is fixed by a constant and cannot be edited in the admin.
	 *
	 * @param string $field endpoint|key|secret.
	 * @return bool
	 */
	public static function locked( $field ) {
		$constants = array(
			'endpoint' => 'OHMYLMS_XAPI_ENDPOINT',
			'key'      => 'OHMYLMS_XAPI_KEY',
			'secret'   => 'OHMYLMS_XAPI_SECRET',
		);

		return isset( $constants[ $field ] ) && defined( $constants[ $field ] ) && '' !== (string) constant( $constants[ $field ] );
	}

	/**
	 * Whether statements should be queued at all.
	 *
	 * @return bool
	 */
	public static function enabled() {
		$values = self::all();

		return ! empty( $values['enabled'] ) && '' !== self::normalize_endpoint( $values['endpoint'] ) && Schema::ready();
	}

	/**
	 * Whether a statement kind is switched on.
	 *
	 * @param string $kind Statement kind.
	 * @return bool
	 */
	public static function kind_enabled( $kind ) {
		$map = self::kind_groups();
		if ( ! isset( $map[ $kind ] ) ) {
			return false;
		}
		$values = self::all();

		return ! empty( $values['groups'][ $map[ $kind ] ] );
	}

	/**
	 * Validate and store settings from the admin form.
	 *
	 * A blank secret keeps the stored one, so the form never has to show it; a truthy
	 * `clear_secret` removes it.
	 *
	 * @param array $input Raw (already unslashed) form values.
	 * @return array The stored settings.
	 */
	public static function save( array $input ) {
		$current = get_option( self::OPTION, array() );
		$current = is_array( $current ) ? $current : array();
		$values  = array_merge( self::defaults(), $current );

		if ( ! self::locked( 'endpoint' ) ) {
			$values['endpoint'] = self::normalize_endpoint( isset( $input['endpoint'] ) ? $input['endpoint'] : '' );
		}
		if ( ! self::locked( 'key' ) ) {
			$values['key'] = sanitize_text_field( isset( $input['key'] ) ? $input['key'] : '' );
		}
		if ( ! self::locked( 'secret' ) && ! empty( $input['clear_secret'] ) ) {
			$values['secret'] = '';
		} elseif ( ! self::locked( 'secret' ) && isset( $input['secret'] ) && '' !== $input['secret'] ) {
			$values['secret'] = trim( (string) $input['secret'] );
		}
		$values['enabled']        = empty( $input['enabled'] ) ? 0 : 1;
		$values['actor']          = isset( $input['actor'] ) && in_array( $input['actor'], array( 'pseudonym', 'user_id', 'mbox' ), true ) ? $input['actor'] : 'pseudonym';
		$values['responses']      = empty( $input['responses'] ) ? 0 : 1;
		$values['retention_days'] = max( 1, min( 365, isset( $input['retention_days'] ) ? absint( $input['retention_days'] ) : 14 ) );
		$groups                   = isset( $input['groups'] ) && is_array( $input['groups'] ) ? $input['groups'] : array();
		$values['groups']         = array();
		foreach ( array_keys( self::groups() ) as $group ) {
			$values['groups'][ $group ] = empty( $groups[ $group ] ) ? 0 : 1;
		}

		update_option( self::OPTION, $values, false );
		if ( ! $values['enabled'] ) {
			wp_clear_scheduled_hook( Processor::HOOK );
		}

		return $values;
	}

	/**
	 * Accept only an http(s) endpoint, without a trailing slash or query string.
	 *
	 * @param mixed $url Candidate URL.
	 * @return string Empty when the value is not usable.
	 */
	public static function normalize_endpoint( $url ) {
		$url   = trim( (string) $url );
		$parts = wp_parse_url( $url );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return '';
		}
		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return '';
		}

		return untrailingslashit( strtok( $url, '?#' ) );
	}

	/**
	 * Random identifier of this site, created once. Statement IDs are derived from it so two
	 * sites that share an LRS can never produce the same statement ID.
	 *
	 * @return string
	 */
	public static function site_id() {
		$id = get_option( self::SITE_ID_OPTION );
		if ( ! Uuid::is_valid( $id ) ) {
			$id = Uuid::v4();
			update_option( self::SITE_ID_OPTION, $id, false );
		}

		return $id;
	}

	/**
	 * Secret that pseudonymises learners, created once. It is deliberately not a WordPress salt,
	 * so rotating salts after a security incident does not change every learner's identity.
	 *
	 * @return string
	 */
	public static function actor_key() {
		$key = get_option( self::ACTOR_KEY_OPTION );
		if ( ! is_string( $key ) || strlen( $key ) < 32 ) {
			$key = bin2hex( random_bytes( 32 ) );
			update_option( self::ACTOR_KEY_OPTION, $key, false );
		}

		return $key;
	}
}
