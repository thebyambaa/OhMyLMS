<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Stale-save versions of schools, classes and accounts, and the edits that check them.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Schools;

use OhMyLMS\Utility\Transaction;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * A version is a short hash of the fields an Account Hub editor saves (docs/api/account-hub.md). An edit
 * with a version locks the row (`FOR UPDATE`) inside one transaction, compares the stored version and
 * only then writes through `Service::update_school()` or `ClassManagement::edit()`. A stale version
 * answers `ohmylms_account_hub_conflict` (409) with the current version; nothing is written. Service
 * exceptions propagate to the caller.
 */
final class Versions {

	/**
	 * Version of a school row.
	 *
	 * @param array $row School row.
	 * @return string
	 */
	public static function school( array $row ) {
		return substr( hash( 'sha256', wp_json_encode( array( (string) $row['name'], (string) $row['timezone'], (string) $row['status'] ) ) ), 0, 16 );
	}

	/**
	 * Version of a class row.
	 *
	 * @param array $row Class row.
	 * @return string
	 */
	public static function classroom( array $row ) {
		return substr( hash( 'sha256', wp_json_encode( array( (string) $row['name'], (string) $row['subject'], (string) $row['grade'], (string) $row['status'], (string) $row['academic_year_id'] ) ) ), 0, 16 );
	}

	/**
	 * Version of an account's email, display name, first and last name.
	 *
	 * @param string $email   Email.
	 * @param string $display Display name.
	 * @param string $first   First name.
	 * @param string $last    Last name.
	 * @return string
	 */
	public static function account( $email, $display, $first, $last ) {
		return substr( hash( 'sha256', (string) wp_json_encode( array( (string) $email, (string) $display, (string) $first, (string) $last ) ) ), 0, 16 );
	}

	/**
	 * Lock a row of a Schools table for the rest of the transaction.
	 *
	 * @param string $table Table key.
	 * @param int    $id    Row id.
	 * @return array
	 */
	private static function lock( $table, $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Lock the row so the version check and the write are atomic.
		return (array) $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d FOR UPDATE', Schema::table( $table ), $id ), ARRAY_A );
	}

	/**
	 * Stale-save error.
	 *
	 * @param string $message Message.
	 * @param string $version Current version.
	 * @return WP_Error
	 */
	private static function conflict( $message, $version ) {
		return new WP_Error(
			'ohmylms_account_hub_conflict',
			$message,
			array(
				'status'  => 409,
				'version' => $version,
			)
		);
	}

	/**
	 * Rename a school or change its time zone, unless the stored version differs.
	 *
	 * @param int    $school_id School id.
	 * @param array  $data      `name` and `timezone`.
	 * @param string $version   Version the editor loaded, or '' to skip the check.
	 * @return true|WP_Error
	 */
	public static function update_school( $school_id, array $data, $version ) {
		return Transaction::run(
			static function () use ( $school_id, $data, $version ) {
				$current = self::lock( 'schools', $school_id );
				if ( '' !== $version && self::school( $current ) !== $version ) {
					return self::conflict( __( 'This school was changed by someone else. Reload it before saving.', 'ohmylms' ), self::school( $current ) );
				}
				Service::update_school( (int) $school_id, $data );

				return true;
			}
		);
	}

	/**
	 * Edit an active class's name, subject and grade, unless the stored version differs or it was archived.
	 *
	 * @param int    $class_id Class id.
	 * @param array  $data     `name`, `subject` and `grade`.
	 * @param string $version  Version the editor loaded, or '' to skip the check.
	 * @return true|WP_Error
	 */
	public static function update_class( $class_id, array $data, $version ) {
		return Transaction::run(
			static function () use ( $class_id, $data, $version ) {
				$current = self::lock( 'classes', $class_id );
				if ( '' !== $version && self::classroom( $current ) !== $version ) {
					return self::conflict( __( 'This class was changed by someone else. Reload it before saving.', 'ohmylms' ), self::classroom( $current ) );
				}
				if ( 'active' !== $current['status'] ) {
					return new WP_Error( 'ohmylms_account_hub_class_archived', __( 'This class is archived. Restore it first.', 'ohmylms' ), array( 'status' => 409 ) );
				}
				ClassManagement::edit( (int) $class_id, $data );

				return true;
			}
		);
	}

	/**
	 * Change an account's names and email, unless the stored version differs. The user row is locked and
	 * the names are read under the same lock, bypassing the object cache. A rejected update answers
	 * `ohmylms_account_hub_rejected` (422). The caller clears the user cache.
	 *
	 * @param int    $user_id User id.
	 * @param string $first   First name.
	 * @param string $last    Last name.
	 * @param string $email   Email.
	 * @param string $version Version the editor loaded, or '' to skip the check.
	 * @return true|WP_Error
	 */
	public static function update_account( $user_id, $first, $last, $email, $version ) {
		return Transaction::run(
			static function () use ( $user_id, $first, $last, $email, $version ) {
				global $wpdb;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Lock the account row so the version check and the write are atomic.
				$row = $wpdb->get_row( $wpdb->prepare( 'SELECT user_email,display_name FROM %i WHERE ID=%d FOR UPDATE', $wpdb->users, $user_id ), ARRAY_A );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Current names under the same lock, bypassing the object cache.
				$meta    = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key,meta_value FROM %i WHERE user_id=%d AND meta_key IN ('first_name','last_name') ORDER BY umeta_id DESC", $wpdb->usermeta, $user_id ), ARRAY_A );
				$names   = wp_list_pluck( (array) $meta, 'meta_value', 'meta_key' );
				$current = self::account( $row['user_email'] ?? '', $row['display_name'] ?? '', $names['first_name'] ?? '', $names['last_name'] ?? '' );
				if ( '' !== $version && $version !== $current ) {
					return self::conflict( __( 'This account was changed by someone else. Reload it before saving.', 'ohmylms' ), $current );
				}
				$updated = wp_update_user(
					array(
						'ID'           => $user_id,
						'user_email'   => $email,
						'first_name'   => $first,
						'last_name'    => $last,
						'display_name' => trim( $first . ' ' . $last ),
					)
				);
				if ( is_wp_error( $updated ) ) {
					return new WP_Error( 'ohmylms_account_hub_rejected', $updated->get_error_message(), array( 'status' => 422 ) );
				}

				return true;
			}
		);
	}
}
