<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Learner identity and registrations.
 *
 * @package OhMyLMS\XApi
 */

namespace OhMyLMS\XApi;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Registrations are plugin-owned rows that must be read back immediately after the insert, so they are deliberately uncached.

/**
 * Builds the actor for a learner and the registration UUID for a learner's course enrolment.
 *
 * The default actor is an account whose name is an HMAC of the WordPress user ID, so a statement
 * store never holds a name or email address. The account name is stable per learner, which is
 * what an LRS needs to group a learner's activity.
 */
final class Identity {

	/**
	 * Actor for a user, or null when the chosen identity mode cannot identify them.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array|null
	 */
	public static function actor( $user_id ) {
		$user = get_userdata( (int) $user_id );
		if ( ! $user ) {
			return null;
		}
		$settings = Settings::all();
		$home     = apply_filters( 'ohmylms_xapi_account_homepage', untrailingslashit( home_url() ) );

		return self::actor_for( $settings['actor'], (int) $user->ID, (string) $user->user_email, $home, Settings::actor_key() );
	}

	/**
	 * Actor from explicit values (no WordPress access, so it can be unit tested).
	 *
	 * @param string $mode    pseudonym|user_id|mbox.
	 * @param int    $user_id User ID.
	 * @param string $email   User email.
	 * @param string $home    Account home page IRI.
	 * @param string $key     Pseudonymisation key.
	 * @return array|null
	 */
	public static function actor_for( $mode, $user_id, $email, $home, $key ) {
		if ( $user_id < 1 ) {
			return null;
		}
		if ( 'mbox' === $mode ) {
			if ( '' === $email || false === strpos( $email, '@' ) ) {
				return null;
			}

			return array(
				'objectType' => 'Agent',
				'mbox'       => 'mailto:' . $email,
			);
		}
		$name = 'user_id' === $mode ? (string) $user_id : hash_hmac( 'sha256', 'user:' . $user_id, $key );

		return array(
			'objectType' => 'Agent',
			'account'    => array(
				'homePage' => $home,
				'name'     => $name,
			),
		);
	}

	/**
	 * Registration UUID for a learner's enrolment in a course, created on first use.
	 *
	 * @param int $student_id Learner.
	 * @param int $course_id  Course.
	 * @return string
	 */
	public static function registration( $student_id, $course_id ) {
		global $wpdb;
		$table = Schema::table( 'registrations' );
		$wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO %i (student_id, course_id, registration, created_at) VALUES (%d, %d, %s, %s)',
				$table,
				(int) $student_id,
				(int) $course_id,
				Uuid::v4(),
				current_time( 'mysql', true )
			)
		);

		return (string) $wpdb->get_var( $wpdb->prepare( 'SELECT registration FROM %i WHERE student_id=%d AND course_id=%d', $table, (int) $student_id, (int) $course_id ) );
	}
}
