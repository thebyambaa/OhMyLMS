<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Reviewed roster import: plan a parsed CSV against the roster, then apply the reviewed plan.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Schools;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Roster CSV import behind `POST ohmylms/v1/account-hub/schools/{id}/roster-import` (docs/api/account-hub.md).
 * The REST controller parses the file and checks scope. A plan has one action per row: `create_student`
 * (no email: a managed account), `invite`, `existing`, `pending` or `error`. Applying takes a per-school
 * advisory lock, re-plans, requires the review token of an unchanged plan and writes each row through
 * `Service::create_student()` or `Service::invite()`; applying the same file again creates nothing.
 */
final class RosterImport {

	/**
	 * Columns the import understands.
	 */
	const COLUMNS = array( 'role', 'name', 'email', 'external_student_id' );

	/**
	 * Whether the account with this email already has the role in the school.
	 *
	 * @param int    $school_id School id.
	 * @param string $email     Email.
	 * @param string $role      Role.
	 * @return bool
	 */
	public static function on_roster( $school_id, $email, $role ) {
		$user = get_user_by( 'email', $email );

		return $user && Access::school( $school_id, array( $role ), (int) $user->ID );
	}

	/**
	 * Plan every row against the current roster without writing.
	 *
	 * @param int   $school_id School id.
	 * @param array $parsed    Parsed CSV: `header` and `rows` of `{line, cells, extra}`.
	 * @return array{rows:array,counts:array,ignored:string[]}
	 */
	public static function plan( $school_id, array $parsed ) {
		$ignored = array_values( array_diff( $parsed['header'], self::COLUMNS ) );
		$seen    = array();
		$rows    = array();
		foreach ( $parsed['rows'] as $row ) {
			$cells    = $row['cells'];
			$role     = '' === ( $cells['role'] ?? '' ) ? 'student' : strtolower( $cells['role'] );
			$name     = Service::text( $cells['name'] ?? '', 190 );
			$email    = trim( $cells['email'] ?? '' );
			$external = Service::text( $cells['external_student_id'] ?? '', 100 );
			$error    = '';
			if ( $row['extra'] ) {
				$error = __( 'This row has more cells than the header.', 'ohmylms' );
			} elseif ( ! in_array( $role, array( 'student', 'teacher' ), true ) ) {
				$error = __( 'Role must be student or teacher.', 'ohmylms' );
			} elseif ( '' === $name ) {
				$error = __( 'Enter a name.', 'ohmylms' );
			} elseif ( '' !== $email && ! is_email( $email ) ) {
				$error = __( 'The email address is not valid.', 'ohmylms' );
			} elseif ( 'teacher' === $role && '' === $email ) {
				$error = __( 'Teachers need an email address for their invitation.', 'ohmylms' );
			} elseif ( 'student' === $role && '' === $email && '' === $external ) {
				$error = __( 'A student without email needs a school student ID.', 'ohmylms' );
			}
			// Rows with email become invitations (the school student ID is not used); rows without become managed accounts.
			$external = '' === $email ? $external : '';
			$key      = '' === $email ? 'id:' . mb_strtolower( $external ) : 'email:' . strtolower( $email ) . ':' . $role;
			if ( '' === $error && isset( $seen[ $key ] ) ) {
				/* translators: %d: earlier line number. */
				$error = sprintf( __( 'Repeats line %d of this file.', 'ohmylms' ), $seen[ $key ] );
			}
			if ( '' === $error ) {
				$seen[ $key ] = $row['line'];
			}
			if ( '' !== $error ) {
				$action = 'error';
			} elseif ( '' === $email ) {
				$action = Directory::student_by_external_id( $school_id, $external ) ? 'existing' : 'create_student';
			} elseif ( self::on_roster( $school_id, $email, $role ) ) {
				$action = 'existing';
			} else {
				$action = Directory::pending_invitation( $school_id, $email, $role ) ? 'pending' : 'invite';
			}
			$rows[] = array(
				'line'                => $row['line'],
				'role'                => $role,
				'name'                => $name,
				'email'               => $email,
				'external_student_id' => $external,
				'action'              => $action,
				'error'               => '' === $error ? null : $error,
			);
		}
		$counts = array_fill_keys( array( 'create_student', 'invite', 'existing', 'pending', 'error' ), 0 );
		foreach ( $rows as $row ) {
			++$counts[ $row['action'] ];
		}

		return array(
			'rows'    => $rows,
			'counts'  => $counts,
			'ignored' => $ignored,
		);
	}

	/**
	 * Review token binding the caller, school, file and every planned action.
	 *
	 * @param int   $school_id School id.
	 * @param array $plan      Plan.
	 * @return string
	 */
	public static function review_token( $school_id, array $plan ) {
		return hash_hmac( 'sha256', wp_json_encode( array( get_current_user_id(), (int) $school_id, $plan['rows'] ) ), wp_salt( 'nonce' ) );
	}

	/**
	 * Apply a reviewed plan under the school's import lock.
	 *
	 * @param int    $school_id  School id.
	 * @param array  $parsed     Parsed CSV.
	 * @param string $review     Review token of the dry run.
	 * @param bool   $send_email Whether invitations are emailed.
	 * @return array|WP_Error Applied plan (actions `created`, `invited`, `existing`, `pending`, `error`), or
	 *                        `ohmylms_account_hub_import_busy` / `_review_stale` (409).
	 */
	public static function apply( $school_id, array $parsed, $review, $send_email ) {
		global $wpdb;
		$lock = 'ohmylms_roster_import_' . md5( $wpdb->prefix . $school_id );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Serialize imports into one school.
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $lock ) ) ) {
			return new WP_Error( 'ohmylms_account_hub_import_busy', __( 'Another import into this school is running. Try again shortly.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		try {
			$plan = self::plan( $school_id, $parsed );
			if ( ! hash_equals( self::review_token( $school_id, $plan ), (string) $review ) ) {
				return new WP_Error( 'ohmylms_account_hub_review_stale', __( 'The roster or the file changed since the review. Review it again before importing.', 'ohmylms' ), array( 'status' => 409 ) );
			}
			foreach ( $plan['rows'] as &$row ) {
				try {
					if ( 'create_student' === $row['action'] ) {
						$created         = Service::create_student(
							$school_id,
							array(
								'name'                => $row['name'],
								'external_student_id' => $row['external_student_id'],
							)
						);
						$row['action']   = 'created';
						$row['user_id']  = (int) $created['id'];
						$row['username'] = (string) $created['username'];
					} elseif ( 'invite' === $row['action'] ) {
						$invite        = Service::invite(
							$school_id,
							array(
								'role'       => $row['role'],
								'email'      => $row['email'],
								'send_email' => (bool) $send_email,
							)
						);
						$row['action'] = 'invited';
						$row['url']    = (string) $invite['url'];
					}
				} catch ( \Throwable $failure ) {
					$row['action'] = 'error';
					$row['error']  = 409 === (int) $failure->getCode() ? __( 'Already on the roster; nothing was changed.', 'ohmylms' ) : __( 'This row could not be saved. Nothing was changed for it.', 'ohmylms' );
				}
			}
			unset( $row );
			try {
				Service::audit( $school_id, 'roster_imported' );
			} catch ( \Throwable $failure ) {
				unset( $failure );
			}
		} finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Release the import lock.
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
		$plan['counts'] = array_fill_keys( array( 'created', 'invited', 'existing', 'pending', 'error' ), 0 );
		foreach ( $plan['rows'] as $row ) {
			++$plan['counts'][ $row['action'] ];
		}

		return $plan;
	}
}
