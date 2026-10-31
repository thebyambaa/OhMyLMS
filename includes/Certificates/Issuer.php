<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Issuing, revoking and reading the certificates learners received.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Certificates;

use OhMyLMS\Learning\CourseCertificate;
use OhMyLMS\Utility\Transaction;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Issued certificates (docs/api/certificates.md, "Issued certificates").
 *
 * A certificate is issued automatically when a learner completes a course whose assigned template is
 * published, or by an administrator. The row keeps what the certificate shows (learner name, course title,
 * completion date and the template as it was) so a later rename or template edit never changes an issued
 * certificate; re-issuing creates a new certificate from the current data and revokes the old one.
 */
final class Issuer {
	/**
	 * Capability for managing issued certificates (the template editor's).
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Characters of a verification code (no 0/O, 1/I/L).
	 */
	const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

	/**
	 * Completed courses checked per learner when the dashboard backfills certificates.
	 */
	const SYNC_LIMIT = 50;

	/**
	 * Issue the course's certificate after `ohmylms_course_completed`.
	 *
	 * @param mixed $student_id Learner ID.
	 * @param mixed $course_id  Course ID.
	 */
	public static function on_course_completed( $student_id, $course_id ) {
		try {
			self::issue_for_completion( (int) $student_id, (int) $course_id );
		} catch ( \Throwable $error ) {
			// Issuing must never break the completion that triggered it; the dashboard retries later.
			unset( $error );
		}
	}

	/**
	 * Issue the certificate a completed course awards, unless the learner already has one for the course
	 * (issued or revoked: an administrator's revocation is not undone by completing again).
	 *
	 * @param int $user_id   Learner ID.
	 * @param int $course_id Course ID.
	 * @return array|null|WP_Error The new record, null when nothing is due.
	 */
	public static function issue_for_completion( $user_id, $course_id ) {
		if ( ! IssuedSchema::install() || ! get_userdata( $user_id ) || OHMYLMS_COURSE_CPT !== get_post_type( $course_id ) ) {
			return null;
		}
		$template_id = CourseCertificate::current( $course_id );
		if ( ! $template_id || OHMYLMS_CERTIFICATE_CPT !== get_post_type( $template_id ) || 'publish' !== get_post_status( $template_id ) ) {
			return null;
		}
		$completed = self::completion( $user_id, $course_id );
		if ( null === $completed || self::has_any( $user_id, $course_id ) ) {
			return null;
		}

		return self::create( $user_id, $course_id, $template_id, 'completion', 0, $completed );
	}

	/**
	 * Issue a certificate by hand. Completion is not required.
	 *
	 * @param int $user_id     Learner ID.
	 * @param int $course_id   Course ID.
	 * @param int $template_id Template ID; 0 for the course's template.
	 * @return array|WP_Error
	 */
	public static function issue( $user_id, $course_id, $template_id = 0 ) {
		$ready = self::require_schema();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		$check = self::check_subjects( $user_id, $course_id );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$template_id = $template_id ? (int) $template_id : CourseCertificate::current( $course_id );
		$template    = self::check_template( $template_id );
		if ( is_wp_error( $template ) ) {
			return $template;
		}
		$active = self::active_for( $user_id, $course_id );
		if ( $active ) {
			return self::already_issued( $active );
		}
		$completed = self::completion( $user_id, $course_id );

		return self::create( $user_id, $course_id, $template_id, 'manual', 0, $completed ? $completed : null );
	}

	/**
	 * Revoke a certificate: it stays on record and verification reports it as revoked.
	 *
	 * @param int $id Issued certificate ID.
	 * @return array|WP_Error The revoked record.
	 */
	public static function revoke( $id ) {
		$ready = self::require_schema();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		try {
			$record = Transaction::run(
				static function () use ( $id ) {
					$record = self::lock( $id );
					if ( is_wp_error( $record ) ) {
						return $record;
					}
					if ( 'revoked' === $record['status'] ) {
						return new WP_Error( 'ohmylms_certificate_already_revoked', __( 'This certificate is already revoked.', 'ohmylms' ), array( 'status' => 409 ) );
					}
					self::mark_revoked( $record['id'] );
					return self::get( $record['id'] );
				}
			);
		} catch ( \Throwable $error ) {
			return self::storage_error();
		}
		if ( ! is_wp_error( $record ) ) {
			/**
			 * Fires after an issued certificate was revoked.
			 *
			 * @param array $record The revoked certificate.
			 */
			do_action( 'ohmylms_certificate_revoked', $record );
		}

		return $record;
	}

	/**
	 * Re-issue: a new certificate (new code) from the learner's current name, the course's current title
	 * and template; the given certificate is revoked if it is still valid.
	 *
	 * @param int $id Issued certificate ID.
	 * @return array|WP_Error The new record.
	 */
	public static function reissue( $id ) {
		$ready = self::require_schema();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		$record = self::get( $id );
		if ( ! $record ) {
			return self::not_found();
		}
		$check = self::check_subjects( $record['user_id'], $record['course_id'] );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$template_id = CourseCertificate::current( $record['course_id'] );
		$template_id = $template_id ? $template_id : $record['certificate_id'];
		if ( is_wp_error( self::check_template( $template_id ) ) ) {
			return new WP_Error( 'ohmylms_certificate_no_template', __( 'The course has no certificate template to re-issue from. Assign one to the course first.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		$revoked = null;
		try {
			$created = Transaction::run(
				static function () use ( $id, $template_id, &$revoked ) {
					$old = self::lock( $id );
					if ( is_wp_error( $old ) ) {
						return $old;
					}
					$active = self::active_for( $old['user_id'], $old['course_id'] );
					if ( $active && $active['id'] !== $old['id'] ) {
						return self::already_issued( $active );
					}
					if ( 'issued' === $old['status'] ) {
						self::mark_revoked( $old['id'] );
						$revoked = self::get( $old['id'] );
					}
					$completed = $old['completed_at'] ? $old['completed_at'] : self::completion( $old['user_id'], $old['course_id'] );
					$created   = self::create( $old['user_id'], $old['course_id'], $template_id, 'reissue', $old['id'], $completed ? $completed : null, false );
					if ( is_wp_error( $created ) ) {
						throw new \RuntimeException( 'Re-issue failed' );
					}
					return $created;
				}
			);
		} catch ( \Throwable $error ) {
			return self::storage_error();
		}
		if ( is_wp_error( $created ) ) {
			return $created;
		}
		if ( $revoked ) {
			/** This action is documented in includes/Certificates/Issuer.php (revoke()). */
			do_action( 'ohmylms_certificate_revoked', $revoked );
		}
		/** This action is documented in includes/Certificates/Issuer.php (create()). */
		do_action( 'ohmylms_certificate_issued', $created );

		return $created;
	}

	/**
	 * Issue what is due for a learner's completed courses (completions from before issuing existed, or a
	 * failed hook). Idempotent; at most {@see SYNC_LIMIT} courses.
	 *
	 * @param int $user_id Learner ID.
	 * @return int Number of certificates issued.
	 */
	public static function sync_for_user( $user_id ) {
		global $wpdb;
		if ( ! $user_id || ! IssuedSchema::install() ) {
			return 0;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bounded read of completed courses that award a published template and have no certificate yet.
		$courses = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT e.course_id FROM %i e INNER JOIN %i r ON r.course_id = e.course_id INNER JOIN %i t ON t.ID = r.certificate_id AND t.post_type = %s AND t.post_status = 'publish' LEFT JOIN %i i ON i.user_id = e.user_id AND i.course_id = e.course_id WHERE e.user_id = %d AND e.progress = 'completed' AND i.id IS NULL LIMIT %d",
				$wpdb->prefix . 'ohmylms_user_enrollment',
				$wpdb->prefix . 'ohmylms_certificate_relationship',
				$wpdb->posts,
				OHMYLMS_CERTIFICATE_CPT,
				IssuedSchema::table(),
				$user_id,
				self::SYNC_LIMIT
			)
		);
		$issued  = 0;
		foreach ( array_map( 'intval', (array) $courses ) as $course_id ) {
			$record = self::issue_for_completion( $user_id, $course_id );
			if ( is_array( $record ) ) {
				++$issued;
			}
		}

		return $issued;
	}

	// Reads.

	/**
	 * One issued certificate.
	 *
	 * @param int $id ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		if ( ! IssuedSchema::ready() ) {
			return null;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table; fresh read.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', IssuedSchema::table(), (int) $id ), ARRAY_A );

		return $row ? self::record( $row ) : null;
	}

	/**
	 * The certificate with a verification code, in any status.
	 *
	 * @param string $code Code as typed (case, spaces and dashes are ignored).
	 * @return array|null
	 */
	public static function find_by_code( $code ) {
		global $wpdb;
		$code = self::normalize_code( $code );
		if ( '' === $code || ! IssuedSchema::ready() ) {
			return null;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table; fresh read.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE code = %s', IssuedSchema::table(), $code ), ARRAY_A );

		return $row ? self::record( $row ) : null;
	}

	/**
	 * The valid certificate of a learner for a course.
	 *
	 * @param int $user_id   Learner ID.
	 * @param int $course_id Course ID.
	 * @return array|null
	 */
	public static function active_for( $user_id, $course_id ) {
		global $wpdb;
		if ( ! IssuedSchema::ready() ) {
			return null;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table; fresh read.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE active_key = %s', IssuedSchema::table(), self::active_key( $user_id, $course_id ) ), ARRAY_A );

		return $row ? self::record( $row ) : null;
	}

	/**
	 * A learner's valid certificates, newest first.
	 *
	 * @param int $user_id Learner ID.
	 * @return array[]
	 */
	public static function for_user( $user_id ) {
		global $wpdb;
		if ( ! $user_id || ! IssuedSchema::ready() ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table; bounded by the learner's courses.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE user_id = %d AND status = 'issued' ORDER BY issued_at DESC, id DESC LIMIT 200", IssuedSchema::table(), (int) $user_id ), ARRAY_A );

		return array_map( array( self::class, 'record' ), (array) $rows );
	}

	/**
	 * Page of issued certificates for the admin list.
	 *
	 * @param array $args `search`, `course`, `certificate`, `user`, `status` (`all|issued|revoked`), `source`,
	 *                    `orderby` (`issued_at|id|student_name|course_title`), `order`, `page`, `per_page`.
	 * @return array{items:array[],total:int}
	 */
	public static function query( array $args ) {
		global $wpdb;
		if ( ! IssuedSchema::ready() ) {
			return array(
				'items' => array(),
				'total' => 0,
			);
		}
		$where  = array( '1=1' );
		$values = array( IssuedSchema::table(), $wpdb->users );
		foreach ( array(
			'course'      => 'i.course_id',
			'certificate' => 'i.certificate_id',
			'user'        => 'i.user_id',
		) as $key => $column ) {
			if ( ! empty( $args[ $key ] ) ) {
				$where[]  = $column . ' = %d';
				$values[] = (int) $args[ $key ];
			}
		}
		if ( in_array( $args['status'] ?? 'all', array( 'issued', 'revoked' ), true ) ) {
			$where[]  = 'i.status = %s';
			$values[] = $args['status'];
		}
		if ( in_array( $args['source'] ?? '', array( 'completion', 'manual', 'reissue' ), true ) ) {
			$where[]  = 'i.source = %s';
			$values[] = $args['source'];
		}
		$search = trim( (string) ( $args['search'] ?? '' ) );
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '( i.code = %s OR i.student_name LIKE %s OR i.course_title LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s )';
			$values[] = self::normalize_code( $search );
			array_push( $values, $like, $like, $like, $like );
		}
		$columns  = array(
			'issued_at'    => 'i.issued_at',
			'id'           => 'i.id',
			'student_name' => 'i.student_name',
			'course_title' => 'i.course_title',
		);
		$orderby  = $columns[ $args['orderby'] ?? 'issued_at' ] ?? 'i.issued_at';
		$order    = 'asc' === strtolower( (string) ( $args['order'] ?? 'desc' ) ) ? 'ASC' : 'DESC';
		$per_page = max( 1, min( 100, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( max( 1, (int) ( $args['page'] ?? 1 ) ) - 1 ) * $per_page;
		$from     = 'FROM %i i LEFT JOIN %i u ON u.ID = i.user_id WHERE ' . implode( ' AND ', $where );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery -- $from holds only placeholders and fixed SQL (values are passed as an array); ORDER BY comes from allow-lists.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) $from", $values ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT i.*, u.user_email AS user_email $from ORDER BY $orderby $order, i.id $order LIMIT %d OFFSET %d", array_merge( $values, array( $per_page, $offset ) ) ), ARRAY_A );
		// phpcs:enable

		return array(
			'items' => array_map( array( self::class, 'record' ), (array) $rows ),
			'total' => $total,
		);
	}

	/**
	 * The template a certificate renders with (its snapshot).
	 *
	 * @param array $record Issued certificate.
	 * @return array Canonical template.
	 */
	public static function template( array $record ) {
		return Template::from_stored( $record['template'] );
	}

	/**
	 * Placeholder values of an issued certificate.
	 *
	 * @param array $record Issued certificate.
	 * @return array<string,string>
	 */
	public static function values( array $record ) {
		$date   = $record['completed_at'] ? $record['completed_at'] : $record['issued_at'];
		$values = array(
			'{student_name}'     => $record['student_name'],
			'{course_name}'      => $record['course_title'],
			'{date}'             => self::local_date( $date ),
			'{site_name}'        => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'{certificate_code}' => $record['code'],
		);

		/**
		 * Filters the text that replaces each placeholder on an issued certificate. Add values for
		 * placeholders registered with `ohmylms_certificate_placeholders`.
		 *
		 * @param array<string,string> $values Token => text.
		 * @param array                $record The issued certificate.
		 */
		$filtered = apply_filters( 'ohmylms_certificate_values', $values, $record );

		return is_array( $filtered ) ? array_map( 'strval', $filtered ) : $values;
	}

	/**
	 * Date in the site's date format and time zone.
	 *
	 * @param string $gmt MySQL GMT date.
	 * @return string
	 */
	public static function local_date( $gmt ) {
		$time = $gmt ? strtotime( $gmt . ' UTC' ) : false;

		return $time ? wp_date( (string) get_option( 'date_format' ), $time ) : '';
	}

	/**
	 * Canonical form of a code: upper case, groups of four separated by dashes.
	 *
	 * @param mixed $code Code as typed.
	 * @return string Empty when it cannot be a code.
	 */
	public static function normalize_code( $code ) {
		$plain = strtoupper( (string) preg_replace( '/[^A-Za-z0-9]/', '', is_scalar( $code ) ? (string) $code : '' ) );
		if ( 16 !== strlen( $plain ) || strspn( $plain, self::CODE_ALPHABET ) !== 16 ) {
			return '';
		}

		return implode( '-', str_split( $plain, 4 ) );
	}

	/**
	 * Completion date (GMT) of a learner's course, or null when it is not completed.
	 *
	 * @param int $user_id   Learner ID.
	 * @param int $course_id Course ID.
	 * @return string|null
	 */
	public static function completion( $user_id, $course_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh read; StudentStore caches completion per request.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT end_date FROM %i WHERE user_id = %d AND course_id = %d AND progress = 'completed' ORDER BY id DESC LIMIT 1", $wpdb->prefix . 'ohmylms_user_enrollment', (int) $user_id, (int) $course_id ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		$end = (string) ( $row['end_date'] ?? '' );

		return ( '' !== $end && '0000-00-00 00:00:00' !== $end ) ? get_gmt_from_date( $end ) : current_time( 'mysql', true );
	}

	// Writes.

	/**
	 * Insert a certificate row.
	 *
	 * @param int         $user_id     Learner ID.
	 * @param int         $course_id   Course ID.
	 * @param int         $template_id Template ID.
	 * @param string      $source      `completion`, `manual` or `reissue`.
	 * @param int         $replaces    Certificate this one replaces.
	 * @param string|null $completed   Completion date (GMT).
	 * @param bool        $announce    Fire `ohmylms_certificate_issued`.
	 * @return array|WP_Error
	 */
	private static function create( $user_id, $course_id, $template_id, $source, $replaces, $completed, $announce = true ) {
		global $wpdb;
		$template = Template::from_stored( get_post_meta( $template_id, '_contents', true ) );
		$row      = array(
			'user_id'        => (int) $user_id,
			'course_id'      => (int) $course_id,
			'certificate_id' => (int) $template_id,
			'status'         => 'issued',
			'active_key'     => self::active_key( $user_id, $course_id ),
			'source'         => $source,
			'student_name'   => self::student_name( $user_id ),
			'course_title'   => html_entity_decode( get_the_title( $course_id ), ENT_QUOTES, 'UTF-8' ),
			'template'       => (string) wp_json_encode( $template ),
			'completed_at'   => $completed,
			'issued_at'      => current_time( 'mysql', true ),
			'issued_by'      => 'completion' === $source ? 0 : get_current_user_id(),
			'replaces'       => (int) $replaces,
		);
		$inserted = false;
		for ( $try = 0; $try < 5 && ! $inserted; $try++ ) {
			$row['code'] = self::new_code();
			$suppress    = $wpdb->suppress_errors( true );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table write.
			$inserted = (bool) $wpdb->insert( IssuedSchema::table(), $row );
			$wpdb->suppress_errors( $suppress );
			if ( ! $inserted && self::active_for( $user_id, $course_id ) ) {
				return self::already_issued( self::active_for( $user_id, $course_id ) );
			}
		}
		if ( ! $inserted ) {
			return self::storage_error();
		}
		$record = self::get( (int) $wpdb->insert_id );
		if ( $announce ) {
			/**
			 * Fires after a certificate was issued (automatically, by hand or as a re-issue).
			 *
			 * @param array $record The issued certificate.
			 */
			do_action( 'ohmylms_certificate_issued', $record );
		}

		return $record;
	}

	/**
	 * Mark a row revoked by the current user.
	 *
	 * @param int $id Row ID.
	 * @throws \RuntimeException When the write fails.
	 */
	private static function mark_revoked( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table write inside a transaction.
		$updated = $wpdb->query( $wpdb->prepare( "UPDATE %i SET status = 'revoked', active_key = NULL, revoked_at = %s, revoked_by = %d WHERE id = %d AND status = 'issued'", IssuedSchema::table(), current_time( 'mysql', true ), get_current_user_id(), (int) $id ) );
		if ( 1 !== $updated ) {
			throw new \RuntimeException( 'Revoke failed' );
		}
	}

	/**
	 * Lock a row for the rest of the transaction.
	 *
	 * @param int $id Row ID.
	 * @return array|WP_Error
	 */
	private static function lock( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Row lock; must not be cached.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d FOR UPDATE', IssuedSchema::table(), (int) $id ), ARRAY_A );

		return $row ? self::record( $row ) : self::not_found();
	}

	// Helpers.

	/**
	 * Whether the learner has any certificate row (issued or revoked) for the course.
	 *
	 * @param int $user_id   Learner ID.
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	private static function has_any( $user_id, $course_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table; fresh read.
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE user_id = %d AND course_id = %d LIMIT 1', IssuedSchema::table(), (int) $user_id, (int) $course_id ) );
	}

	/**
	 * Typed record from a row.
	 *
	 * @param array $row Database row.
	 * @return array
	 */
	private static function record( array $row ) {
		$template = json_decode( (string) $row['template'], true );
		$record   = array(
			'id'             => (int) $row['id'],
			'code'           => (string) $row['code'],
			'user_id'        => (int) $row['user_id'],
			'course_id'      => (int) $row['course_id'],
			'certificate_id' => (int) $row['certificate_id'],
			'status'         => 'revoked' === $row['status'] ? 'revoked' : 'issued',
			'source'         => (string) $row['source'],
			'student_name'   => (string) $row['student_name'],
			'course_title'   => (string) $row['course_title'],
			'template'       => is_array( $template ) ? $template : null,
			'completed_at'   => $row['completed_at'] ? (string) $row['completed_at'] : null,
			'issued_at'      => (string) $row['issued_at'],
			'issued_by'      => (int) $row['issued_by'],
			'revoked_at'     => $row['revoked_at'] ? (string) $row['revoked_at'] : null,
			'revoked_by'     => (int) $row['revoked_by'],
			'replaces'       => (int) $row['replaces'],
		);
		if ( array_key_exists( 'user_email', $row ) ) {
			$record['user_email'] = (string) $row['user_email'];
		}

		return $record;
	}

	/**
	 * Learner name as certificates print it: first and last name, else the display name.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public static function student_name( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return '';
		}
		$name = trim( $user->first_name . ' ' . $user->last_name );

		return '' !== $name ? $name : (string) $user->display_name;
	}

	/**
	 * Unique key of the valid certificate of a learner and course.
	 *
	 * @param int $user_id   Learner ID.
	 * @param int $course_id Course ID.
	 * @return string
	 */
	private static function active_key( $user_id, $course_id ) {
		return (int) $user_id . ':' . (int) $course_id;
	}

	/**
	 * A random code of 16 characters (80 bits) in four groups.
	 *
	 * @return string
	 */
	private static function new_code() {
		$code = '';
		$max  = strlen( self::CODE_ALPHABET ) - 1;
		for ( $i = 0; $i < 16; $i++ ) {
			$code .= self::CODE_ALPHABET[ random_int( 0, $max ) ];
		}

		return implode( '-', str_split( $code, 4 ) );
	}

	/**
	 * The learner and course exist.
	 *
	 * @param int $user_id   Learner ID.
	 * @param int $course_id Course ID.
	 * @return true|WP_Error
	 */
	private static function check_subjects( $user_id, $course_id ) {
		if ( ! get_userdata( $user_id ) ) {
			return new WP_Error( 'ohmylms_certificate_invalid_user', __( 'Choose an existing learner.', 'ohmylms' ), array( 'status' => 422 ) );
		}
		if ( OHMYLMS_COURSE_CPT !== get_post_type( $course_id ) || 'trash' === get_post_status( $course_id ) ) {
			return new WP_Error( 'ohmylms_certificate_invalid_course', __( 'Choose an existing course.', 'ohmylms' ), array( 'status' => 422 ) );
		}

		return true;
	}

	/**
	 * A template that can be issued (published or draft, not trashed).
	 *
	 * @param int $template_id Template ID.
	 * @return true|WP_Error
	 */
	private static function check_template( $template_id ) {
		if ( ! $template_id ) {
			return new WP_Error( 'ohmylms_certificate_no_template', __( 'The course has no certificate template. Assign one to the course or choose a template.', 'ohmylms' ), array( 'status' => 422 ) );
		}
		if ( OHMYLMS_CERTIFICATE_CPT !== get_post_type( $template_id ) || ! in_array( get_post_status( $template_id ), array( 'publish', 'draft' ), true ) ) {
			return new WP_Error( 'ohmylms_certificate_invalid_template', __( 'Choose an existing certificate template.', 'ohmylms' ), array( 'status' => 422 ) );
		}

		return true;
	}

	/**
	 * The table is installed.
	 *
	 * @return true|WP_Error
	 */
	private static function require_schema() {
		return IssuedSchema::install() ? true : new WP_Error( 'ohmylms_certificate_storage_unavailable', __( 'Certificate storage could not be installed.', 'ohmylms' ), array( 'status' => 503 ) );
	}

	/**
	 * Conflict: the learner already holds a valid certificate for the course.
	 *
	 * @param array $active The valid certificate.
	 * @return WP_Error
	 */
	private static function already_issued( array $active ) {
		return new WP_Error(
			'ohmylms_certificate_already_issued',
			__( 'This learner already has a valid certificate for the course. Re-issue it instead.', 'ohmylms' ),
			array(
				'status' => 409,
				'id'     => $active['id'],
			)
		);
	}

	/**
	 * Not found error.
	 *
	 * @return WP_Error
	 */
	private static function not_found() {
		return new WP_Error( 'ohmylms_issued_certificate_not_found', __( 'Issued certificate not found.', 'ohmylms' ), array( 'status' => 404 ) );
	}

	/**
	 * Storage error.
	 *
	 * @return WP_Error
	 */
	private static function storage_error() {
		return new WP_Error( 'ohmylms_certificate_storage', __( 'The certificate could not be saved. Nothing was changed.', 'ohmylms' ), array( 'status' => 500 ) );
	}
}
