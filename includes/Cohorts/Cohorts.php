<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Cohorts: learners who bought or enrolled together.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Cohorts;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Cohort storage, validation, membership and assignment.
 *
 * A cohort belongs to one course (`scope = course`, the course becomes cohort-based) or to one
 * membership plan (`scope = membership`). It has a start date, an optional end date, an optional
 * enrolment window (`enrollment_start`, `enrollment_end`) and an optional capacity. Learners join
 * automatically when they enrol or buy inside the window (see {@see CohortHooks}), by hand, by the
 * window sync, or by an automation. Dates are site time. See docs/api/cohorts.md.
 *
 * @since 1.0.0
 */
final class Cohorts {

	const MAX_TITLE    = 190;
	const MAX_CAPACITY = 100000;
	const STATUSES     = array( 'active', 'archived' );
	const SOURCES      = array( 'purchase', 'enrolment', 'membership', 'manual', 'automation', 'sync' );
	const FIELDS       = array( 'title', 'scope', 'course_id', 'membership_id', 'start_date', 'end_date', 'enrollment_start', 'enrollment_end', 'capacity', 'status', 'close_access_at_end' );
	const DATE_FIELDS  = array( 'start_date', 'end_date', 'enrollment_start', 'enrollment_end' );

	/**
	 * Error helper.
	 *
	 * @param string $code    Code.
	 * @param string $message Message.
	 * @param int    $status  HTTP status.
	 * @param array  $data    Extra data.
	 * @return WP_Error
	 */
	private static function error( $code, $message, $status, array $data = array() ) {
		return new WP_Error( $code, $message, array_merge( array( 'status' => $status ), $data ) );
	}

	/**
	 * Current site time as a MySQL datetime.
	 *
	 * @return string
	 */
	public static function now() {
		return current_time( 'mysql' );
	}

	/**
	 * Stored datetime to ISO 8601 without offset, or null.
	 *
	 * @param mixed $value Stored value.
	 * @return string|null
	 */
	private static function iso( $value ) {
		return $value && '0000-00-00 00:00:00' !== $value ? str_replace( ' ', 'T', (string) $value ) : null;
	}

	/**
	 * One cohort row by id.
	 *
	 * @param int $id Cohort.
	 * @return array|null
	 */
	public static function row( $id ) {
		global $wpdb;
		CohortSchema::ensure();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Cohort owner reads its table.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', CohortSchema::cohorts(), (int) $id ), ARRAY_A );
		return $row ? $row : null;
	}

	/**
	 * Member count of a cohort.
	 *
	 * @param int $id Cohort.
	 * @return int
	 */
	public static function member_count( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE cohort_id = %d', CohortSchema::members(), (int) $id ) );
	}

	/**
	 * Decoded meta of a row.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	private static function meta( array $row ) {
		$meta = json_decode( (string) ( $row['meta'] ?? '' ), true );
		return is_array( $meta ) ? $meta : array();
	}

	/**
	 * Whether the enrolment window of a cohort is open at a time (legacy rule: before the enrolment
	 * deadline and before the end date; plus the optional window start).
	 *
	 * @param array  $row Row.
	 * @param string $at  MySQL datetime.
	 * @return bool
	 */
	public static function window_open( array $row, $at ) {
		if ( 'archived' === ( $row['status'] ?? 'active' ) ) {
			return false;
		}
		if ( ! empty( $row['enrollment_start'] ) && $at < $row['enrollment_start'] ) {
			return false;
		}
		if ( ! empty( $row['enrollment_end'] ) && $at > $row['enrollment_end'] ) {
			return false;
		}
		return empty( $row['end_date'] ) || $at <= $row['end_date'];
	}

	/**
	 * Whether a cohort has a free seat.
	 *
	 * @param array $row Row.
	 * @return bool
	 */
	public static function has_seat( array $row ) {
		return empty( $row['has_capacity'] ) || null === $row['capacity'] || self::member_count( (int) $row['id'] ) < (int) $row['capacity'];
	}

	/**
	 * Response shape of a cohort.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	public static function prepare( array $row ) {
		$count    = self::member_count( (int) $row['id'] );
		$capacity = ! empty( $row['has_capacity'] ) && is_numeric( $row['capacity'] ) ? (int) $row['capacity'] : null;
		$scope    = (int) $row['membership_id'] > 0 ? 'membership' : 'course';
		$target   = 'membership' === $scope ? (int) $row['membership_id'] : (int) $row['course_id'];
		$now      = self::now();
		if ( ! empty( $row['end_date'] ) && $now > $row['end_date'] ) {
			$state = 'ended';
		} elseif ( ! empty( $row['start_date'] ) && $now >= $row['start_date'] ) {
			$state = 'running';
		} else {
			$state = 'upcoming';
		}
		return array(
			'id'                  => (int) $row['id'],
			'title'               => (string) $row['title'],
			'scope'               => $scope,
			'course_id'           => 'course' === $scope ? $target : 0,
			'membership_id'       => 'membership' === $scope ? $target : 0,
			'target_title'        => html_entity_decode( get_the_title( $target ), ENT_QUOTES, 'UTF-8' ),
			'start_date'          => self::iso( $row['start_date'] ),
			'end_date'            => self::iso( $row['end_date'] ),
			'enrollment_start'    => self::iso( $row['enrollment_start'] ?? null ),
			'enrollment_end'      => self::iso( $row['enrollment_end'] ),
			'capacity'            => $capacity,
			'member_count'        => $count,
			'seats_left'          => null === $capacity ? null : max( 0, $capacity - $count ),
			'status'              => in_array( $row['status'], self::STATUSES, true ) ? $row['status'] : 'active',
			'state'               => $state,
			'enrollment_open'     => self::window_open( $row, $now ),
			'close_access_at_end' => ! empty( self::meta( $row )['close_access_at_end'] ),
			'created_at'          => self::iso( $row['created_at'] ),
		);
	}

	/**
	 * Parse an ISO 8601 date (site time when no offset) to MySQL format.
	 *
	 * @param mixed $value Value.
	 * @return string|false|null Null for an empty value, false when invalid.
	 */
	private static function parse_date( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2})?)?$/', $value ) ) {
			return false;
		}
		$value = str_replace( 'T', ' ', $value );
		if ( 10 === strlen( $value ) ) {
			$value .= ' 00:00:00';
		} elseif ( 16 === strlen( $value ) ) {
			$value .= ':00';
		}
		$date = \DateTime::createFromFormat( 'Y-m-d H:i:s', $value );
		return $date && $date->format( 'Y-m-d H:i:s' ) === $value ? $value : false;
	}

	/**
	 * Validate a create (no `$current`) or partial update. Everything is checked before writing.
	 *
	 * @param mixed      $body    JSON body.
	 * @param array|null $current Stored row for updates.
	 * @return array|WP_Error Column => value to write (meta merged).
	 */
	public static function validate( $body, $current = null ) {
		if ( ! is_array( $body ) ) {
			return self::error( 'rest_invalid_param', __( 'Send the cohort as an object.', 'ohmylms' ), 400 );
		}
		$unknown = array_diff( array_keys( $body ), self::FIELDS );
		if ( $unknown ) {
			return self::error( 'ohmylms_cohort_unknown_field', __( 'The request contains unknown fields.', 'ohmylms' ), 400, array( 'params' => array_fill_keys( $unknown, __( 'Unknown field.', 'ohmylms' ) ) ) );
		}
		$errors = array();
		$row    = array();

		if ( null === $current ) {
			$scope = $body['scope'] ?? '';
			if ( ! in_array( $scope, array( 'course', 'membership' ), true ) ) {
				$errors['scope'] = __( 'Choose a course or a membership plan.', 'ohmylms' );
			} elseif ( 'course' === $scope ) {
				$id = $body['course_id'] ?? 0;
				if ( ! is_int( $id ) || OHMYLMS_COURSE_CPT !== get_post_type( $id ) ) {
					$errors['course_id'] = __( 'Choose an existing course.', 'ohmylms' );
				} else {
					$row['course_id']     = $id;
					$row['membership_id'] = 0;
				}
			} else {
				$id = $body['membership_id'] ?? 0;
				if ( ! is_int( $id ) || OHMYLMS_MEMBERSHIP_CPT !== get_post_type( $id ) ) {
					$errors['membership_id'] = __( 'Choose an existing membership plan.', 'ohmylms' );
				} else {
					$row['course_id']     = 0;
					$row['membership_id'] = $id;
				}
			}
			foreach ( array( 'title', 'start_date' ) as $required ) {
				if ( ! array_key_exists( $required, $body ) ) {
					$errors[ $required ] = __( 'This field is required.', 'ohmylms' );
				}
			}
		} else {
			$scope = (int) $current['membership_id'] > 0 ? 'membership' : 'course';
			foreach ( array( 'scope', 'course_id', 'membership_id' ) as $fixed ) {
				if ( array_key_exists( $fixed, $body ) ) {
					$stored = 'scope' === $fixed ? $scope : (int) $current[ $fixed ];
					if ( $body[ $fixed ] !== $stored ) {
						$errors[ $fixed ] = __( 'The course or plan of a cohort cannot change.', 'ohmylms' );
					}
				}
			}
		}

		if ( array_key_exists( 'title', $body ) ) {
			$title = is_string( $body['title'] ) ? trim( sanitize_text_field( $body['title'] ) ) : '';
			if ( '' === $title || mb_strlen( $title ) > self::MAX_TITLE ) {
				/* translators: %d: maximum length. */
				$errors['title'] = sprintf( __( 'Enter a title of 1 to %d characters.', 'ohmylms' ), self::MAX_TITLE );
			} else {
				$row['title'] = $title;
			}
		}
		foreach ( self::DATE_FIELDS as $field ) {
			if ( ! array_key_exists( $field, $body ) ) {
				continue;
			}
			$date = self::parse_date( $body[ $field ] );
			if ( false === $date || ( 'start_date' === $field && null === $date ) ) {
				$errors[ $field ] = __( 'Enter a valid date (YYYY-MM-DD or YYYY-MM-DDTHH:MM).', 'ohmylms' );
			} else {
				$row[ $field ] = $date;
			}
		}
		if ( array_key_exists( 'capacity', $body ) ) {
			$capacity = $body['capacity'];
			if ( null === $capacity ) {
				$row['has_capacity'] = 0;
				$row['capacity']     = null;
			} elseif ( ! is_int( $capacity ) || $capacity < 1 || $capacity > self::MAX_CAPACITY ) {
				/* translators: %d: maximum capacity. */
				$errors['capacity'] = sprintf( __( 'Enter a capacity from 1 to %d, or leave it empty for unlimited seats.', 'ohmylms' ), self::MAX_CAPACITY );
			} else {
				$row['has_capacity'] = 1;
				$row['capacity']     = $capacity;
			}
		}
		if ( array_key_exists( 'status', $body ) ) {
			if ( ! in_array( $body['status'], self::STATUSES, true ) ) {
				$errors['status'] = __( 'Use active or archived.', 'ohmylms' );
			} else {
				$row['status'] = $body['status'];
			}
		}
		$meta = $current ? self::meta( $current ) : array();
		if ( array_key_exists( 'close_access_at_end', $body ) ) {
			if ( ! is_bool( $body['close_access_at_end'] ) ) {
				$errors['close_access_at_end'] = __( 'Use true or false.', 'ohmylms' );
			} else {
				$meta['close_access_at_end'] = $body['close_access_at_end'];
				$row['meta']                 = wp_json_encode( $meta );
			}
		}

		if ( ! $errors ) {
			$merged = array_merge( $current ? $current : array(), $row );
			$start  = $merged['start_date'] ?? null;
			$end    = $merged['end_date'] ?? null;
			$open   = $merged['enrollment_start'] ?? null;
			$close  = $merged['enrollment_end'] ?? null;
			if ( $end && $start && $end <= $start ) {
				$errors['end_date'] = __( 'The end must be after the start.', 'ohmylms' );
			}
			if ( $close && $end && $close > $end ) {
				$errors['enrollment_end'] = __( 'Enrolment must close before the cohort ends.', 'ohmylms' );
			}
			if ( $open && $close && $open >= $close ) {
				$errors['enrollment_start'] = __( 'Enrolment must open before it closes.', 'ohmylms' );
			}
			if ( $current && isset( $row['capacity'] ) && null !== $row['capacity'] && $row['capacity'] < self::member_count( (int) $current['id'] ) ) {
				$errors['capacity'] = __( 'The cohort already has more members than this capacity.', 'ohmylms' );
			}
		}
		if ( $errors ) {
			return self::error( 'ohmylms_cohort_invalid', __( 'Some cohort fields are not valid.', 'ohmylms' ), 422, array( 'params' => $errors ) );
		}
		if ( null === $current ) {
			$row = array_merge(
				array(
					'status'       => 'active',
					'has_capacity' => 0,
					'capacity'     => null,
					'meta'         => wp_json_encode( $meta ),
				),
				$row
			);
		}
		return $row;
	}

	/**
	 * Create a cohort. A course cohort makes its course cohort-based (`_type`), which the course
	 * page, checkout and drip schedules read.
	 *
	 * @param array $row Validated row.
	 * @return array|WP_Error Prepared cohort.
	 */
	public static function create( array $row ) {
		global $wpdb;
		CohortSchema::ensure();
		$row['created_at'] = self::now();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! $wpdb->insert( CohortSchema::cohorts(), $row ) ) {
			return self::error( 'ohmylms_cohort_not_saved', __( 'The cohort could not be saved.', 'ohmylms' ), 500 );
		}
		$id = (int) $wpdb->insert_id;
		if ( ! empty( $row['course_id'] ) && 'cohort-based' !== get_post_meta( (int) $row['course_id'], '_type', true ) ) {
			update_post_meta( (int) $row['course_id'], '_type', 'cohort-based' );
		}
		/**
		 * Fires after a cohort was created.
		 *
		 * @param int $id Cohort ID.
		 */
		do_action( 'ohmylms_cohort_created', $id );
		return self::prepare( self::row( $id ) );
	}

	/**
	 * Update a cohort.
	 *
	 * @param int   $id  Cohort.
	 * @param array $row Validated changes.
	 * @return array|WP_Error
	 */
	public static function update( $id, array $row ) {
		global $wpdb;
		if ( $row ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( false === $wpdb->update( CohortSchema::cohorts(), $row, array( 'id' => (int) $id ) ) ) {
				return self::error( 'ohmylms_cohort_not_saved', __( 'The cohort could not be saved.', 'ohmylms' ), 500 );
			}
			do_action( 'ohmylms_cohort_updated', (int) $id, array_keys( $row ) );
		}
		return self::prepare( self::row( $id ) );
	}

	/**
	 * Delete an empty cohort.
	 *
	 * @param int $id Cohort.
	 * @return true|WP_Error
	 */
	public static function delete( $id ) {
		global $wpdb;
		if ( self::member_count( $id ) > 0 ) {
			return self::error( 'ohmylms_cohort_has_members', __( 'Remove the members or archive the cohort instead.', 'ohmylms' ), 409 );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! $wpdb->delete( CohortSchema::cohorts(), array( 'id' => (int) $id ), array( '%d' ) ) ) {
			return self::error( 'ohmylms_cohort_not_deleted', __( 'The cohort could not be deleted.', 'ohmylms' ), 500 );
		}
		do_action( 'ohmylms_cohort_deleted', (int) $id );
		return true;
	}

	/**
	 * Paged list.
	 *
	 * @param array $args `page`, `per_page`, `search`, `course_id`, `membership_id`, `status`.
	 * @return array{items: array[], total: int}
	 */
	public static function page( array $args ) {
		global $wpdb;
		CohortSchema::ensure();
		$where  = array( '1=1' );
		$params = array( CohortSchema::cohorts() );
		if ( ! empty( $args['course_id'] ) ) {
			$where[]  = 'course_id = %d';
			$params[] = (int) $args['course_id'];
		}
		if ( ! empty( $args['membership_id'] ) ) {
			$where[]  = 'membership_id = %d';
			$params[] = (int) $args['membership_id'];
		}
		if ( 'archived' === ( $args['status'] ?? '' ) ) {
			$where[] = "status = 'archived'";
		} elseif ( 'active' === ( $args['status'] ?? '' ) ) {
			$where[] = "( status IS NULL OR status <> 'archived' )";
		}
		if ( ! empty( $args['search'] ) ) {
			$where[]  = 'title LIKE %s';
			$params[] = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
		}
		$sql_where = implode( ' AND ', $where );
		$per_page  = (int) $args['per_page'];
		$offset    = ( (int) $args['page'] - 1 ) * $per_page;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $sql_where holds only placeholders.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE {$sql_where}", $params ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE {$sql_where} ORDER BY start_date DESC, id DESC LIMIT %d OFFSET %d", array_merge( $params, array( $per_page, $offset ) ) ), ARRAY_A );
		// phpcs:enable
		return array(
			'items' => array_map( array( __CLASS__, 'prepare' ), $rows ? $rows : array() ),
			'total' => $total,
		);
	}

	/**
	 * Whether a learner may be a member: enrolled in the course, or holding the plan.
	 *
	 * @param array $row     Cohort row.
	 * @param int   $user_id Learner.
	 * @return bool
	 */
	public static function eligible( array $row, $user_id ) {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		if ( (int) $row['membership_id'] > 0 ) {
			return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE user_id = %d AND membership_id = %d AND status IN ('enrolled','active','completed')", $wpdb->prefix . 'ohmylms_user_membership', (int) $user_id, (int) $row['membership_id'] ) );
		}
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE user_id = %d AND course_id = %d AND status = 'enrolled'", $wpdb->prefix . 'ohmylms_user_enrollment', (int) $user_id, (int) $row['course_id'] ) );
		// phpcs:enable
	}

	/**
	 * Add a learner to a cohort under the cohort lock (capacity is checked inside it).
	 *
	 * @param int    $id         Cohort.
	 * @param int    $user_id    Learner.
	 * @param string $source     One of {@see self::SOURCES}.
	 * @param int    $source_ref Reference (order, automation, ...).
	 * @param int    $added_by   Acting user, 0 for the system.
	 * @return true|WP_Error
	 */
	public static function add_member( $id, $user_id, $source = 'manual', $source_ref = 0, $added_by = 0 ) {
		global $wpdb;
		$row = self::row( $id );
		if ( ! $row ) {
			return self::error( 'ohmylms_cohort_not_found', __( 'Cohort not found.', 'ohmylms' ), 404 );
		}
		if ( ! get_userdata( (int) $user_id ) ) {
			return self::error( 'ohmylms_cohort_user_not_found', __( 'User not found.', 'ohmylms' ), 404 );
		}
		if ( 'archived' === $row['status'] ) {
			return self::error( 'ohmylms_cohort_archived', __( 'An archived cohort takes no new members.', 'ohmylms' ), 409 );
		}
		if ( ! self::eligible( $row, $user_id ) ) {
			return self::error( 'ohmylms_cohort_not_enrolled', __( 'Only learners enrolled in the course or holding the plan can join this cohort.', 'ohmylms' ), 409 );
		}
		$lock = 'ohmylms-cohort-' . md5( $wpdb->prefix . ':' . (int) $id );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		if ( 1 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) {
			return self::error( 'ohmylms_cohort_busy', __( 'The cohort is busy. Try again.', 'ohmylms' ), 409 );
		}
		try {
			if ( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE cohort_id = %d AND user_id = %d', CohortSchema::members(), (int) $id, (int) $user_id ) ) ) {
				return self::error( 'ohmylms_cohort_member_exists', __( 'The learner is already in this cohort.', 'ohmylms' ), 409 );
			}
			if ( ! self::has_seat( $row ) ) {
				return self::error( 'ohmylms_cohort_full', __( 'The cohort is full.', 'ohmylms' ), 409 );
			}
			$inserted = $wpdb->insert(
				CohortSchema::members(),
				array(
					'cohort_id'  => (int) $id,
					'user_id'    => (int) $user_id,
					'source'     => in_array( $source, self::SOURCES, true ) ? $source : 'manual',
					'source_ref' => (int) $source_ref,
					'joined_at'  => self::now(),
					'added_by'   => (int) $added_by,
				),
				array( '%d', '%d', '%s', '%d', '%s', '%d' )
			);
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
		// phpcs:enable
		if ( ! $inserted ) {
			return self::error( 'ohmylms_cohort_member_not_saved', __( 'The learner could not be added.', 'ohmylms' ), 500 );
		}
		/**
		 * Fires after a learner joined a cohort.
		 *
		 * @param int    $id      Cohort ID.
		 * @param int    $user_id Learner.
		 * @param string $source  How they joined.
		 */
		do_action( 'ohmylms_cohort_member_added', (int) $id, (int) $user_id, $source );
		return true;
	}

	/**
	 * Remove a learner from a cohort.
	 *
	 * @param int $id      Cohort.
	 * @param int $user_id Learner.
	 * @return true|WP_Error
	 */
	public static function remove_member( $id, $user_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$deleted = $wpdb->delete(
			CohortSchema::members(),
			array(
				'cohort_id' => (int) $id,
				'user_id'   => (int) $user_id,
			),
			array( '%d', '%d' )
		);
		if ( ! $deleted ) {
			return self::error( 'ohmylms_cohort_member_not_found', __( 'The learner is not in this cohort.', 'ohmylms' ), 404 );
		}
		do_action( 'ohmylms_cohort_member_removed', (int) $id, (int) $user_id );
		return true;
	}

	/**
	 * Paged members with display names (no email).
	 *
	 * @param int   $id   Cohort.
	 * @param array $args `page`, `per_page`, `search`.
	 * @return array{items: array[], total: int}
	 */
	public static function members_page( $id, array $args ) {
		global $wpdb;
		$params = array( CohortSchema::members(), $wpdb->users, (int) $id );
		$search = '';
		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$search   = ' AND ( u.display_name LIKE %s OR u.user_login LIKE %s OR u.user_email LIKE %s )';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		$per_page = (int) $args['per_page'];
		$offset   = ( (int) $args['page'] - 1 ) * $per_page;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $search holds only placeholders.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i m JOIN %i u ON u.ID = m.user_id WHERE m.cohort_id = %d{$search}", $params ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT m.user_id, m.source, m.joined_at, u.display_name, u.user_login FROM %i m JOIN %i u ON u.ID = m.user_id WHERE m.cohort_id = %d{$search} ORDER BY m.joined_at DESC, m.id DESC LIMIT %d OFFSET %d", array_merge( $params, array( $per_page, $offset ) ) ), ARRAY_A );
		// phpcs:enable
		$items = array();
		foreach ( $rows ? $rows : array() as $row ) {
			$items[] = array(
				'user_id'   => (int) $row['user_id'],
				'name'      => (string) $row['display_name'],
				'login'     => (string) $row['user_login'],
				'source'    => (string) $row['source'],
				'joined_at' => self::iso( $row['joined_at'] ),
			);
		}
		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Cohorts of a learner for a course or plan.
	 *
	 * @param int    $user_id Learner.
	 * @param string $column  `course_id` or `membership_id`.
	 * @param int    $ref     Course or plan.
	 * @return array[] Rows, earliest start first.
	 */
	public static function of_learner( $user_id, $column, $ref ) {
		global $wpdb;
		if ( ! in_array( $column, array( 'course_id', 'membership_id' ), true ) || ! $ref ) {
			return array();
		}
		CohortSchema::ensure();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $column is allow-listed.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT c.* FROM %i c JOIN %i m ON m.cohort_id = c.id WHERE m.user_id = %d AND c.{$column} = %d ORDER BY c.start_date ASC", CohortSchema::cohorts(), CohortSchema::members(), (int) $user_id, (int) $ref ), ARRAY_A );
		// phpcs:enable
		return $rows ? $rows : array();
	}

	/**
	 * Open cohort that would take a new learner now (earliest start first), or null.
	 *
	 * @param string $column `course_id` or `membership_id`.
	 * @param int    $ref    Course or plan.
	 * @return array|null
	 */
	public static function open_cohort( $column, $ref ) {
		global $wpdb;
		if ( ! in_array( $column, array( 'course_id', 'membership_id' ), true ) || ! $ref ) {
			return null;
		}
		CohortSchema::ensure();
		$now = self::now();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $column is allow-listed.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE {$column} = %d AND ( status IS NULL OR status <> 'archived' ) ORDER BY start_date ASC, id ASC LIMIT 50", CohortSchema::cohorts(), (int) $ref ), ARRAY_A );
		// phpcs:enable
		foreach ( $rows ? $rows : array() as $row ) {
			if ( self::window_open( $row, $now ) && self::has_seat( $row ) ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * Whether a course or plan has any cohort that is not archived.
	 *
	 * @param string $column `course_id` or `membership_id`.
	 * @param int    $ref    Course or plan.
	 * @return bool
	 */
	public static function has_cohorts( $column, $ref ) {
		global $wpdb;
		if ( ! in_array( $column, array( 'course_id', 'membership_id' ), true ) || ! $ref ) {
			return false;
		}
		CohortSchema::ensure();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $column is allow-listed.
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE {$column} = %d AND ( status IS NULL OR status <> 'archived' )", CohortSchema::cohorts(), (int) $ref ) );
		// phpcs:enable
	}

	/**
	 * Put a learner who just enrolled or bought into the open cohort of the course or plan, unless
	 * they already belong to one that has not ended.
	 *
	 * @param int    $user_id    Learner.
	 * @param string $column     `course_id` or `membership_id`.
	 * @param int    $ref        Course or plan.
	 * @param string $source     Source.
	 * @param int    $source_ref Reference.
	 * @return int Cohort joined, 0 when none.
	 */
	public static function assign( $user_id, $column, $ref, $source, $source_ref = 0 ) {
		$now = self::now();
		foreach ( self::of_learner( $user_id, $column, $ref ) as $existing ) {
			if ( 'archived' !== $existing['status'] && ( empty( $existing['end_date'] ) || $existing['end_date'] >= $now ) ) {
				return 0;
			}
		}
		$cohort = self::open_cohort( $column, $ref );
		if ( ! $cohort ) {
			return 0;
		}
		$added = self::add_member( (int) $cohort['id'], $user_id, $source, $source_ref );
		return true === $added ? (int) $cohort['id'] : 0;
	}

	/**
	 * Add every eligible learner whose enrolment started inside the cohort's enrolment window and
	 * who is in no other cohort of the same course or plan. Stops when the cohort is full.
	 *
	 * @param int $id       Cohort.
	 * @param int $added_by Acting user.
	 * @return array{added: int, full: bool}|WP_Error
	 */
	public static function sync( $id, $added_by = 0 ) {
		global $wpdb;
		$row = self::row( $id );
		if ( ! $row ) {
			return self::error( 'ohmylms_cohort_not_found', __( 'Cohort not found.', 'ohmylms' ), 404 );
		}
		if ( 'archived' === $row['status'] ) {
			return self::error( 'ohmylms_cohort_archived', __( 'An archived cohort takes no new members.', 'ohmylms' ), 409 );
		}
		$from = ! empty( $row['enrollment_start'] ) ? $row['enrollment_start'] : '1000-01-01 00:00:00';
		$to   = ! empty( $row['enrollment_end'] ) ? $row['enrollment_end'] : ( ! empty( $row['end_date'] ) ? $row['end_date'] : '9999-12-31 23:59:59' );
		$scope_column = (int) $row['membership_id'] > 0 ? 'membership_id' : 'course_id';
		$ref          = (int) $row[ $scope_column ];
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		if ( 'membership_id' === $scope_column ) {
			$users = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT user_id FROM %i WHERE membership_id = %d AND status IN ('enrolled','active','completed') AND start_date BETWEEN %s AND %s ORDER BY start_date ASC LIMIT 5000", $wpdb->prefix . 'ohmylms_user_membership', $ref, $from, $to ) );
		} else {
			$users = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT user_id FROM %i WHERE course_id = %d AND status = 'enrolled' AND start_date BETWEEN %s AND %s ORDER BY start_date ASC LIMIT 5000", $wpdb->prefix . 'ohmylms_user_enrollment', $ref, $from, $to ) );
		}
		$taken = array_map(
			'intval',
			$wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT m.user_id FROM %i m JOIN %i c ON c.id = m.cohort_id WHERE c.{$scope_column} = %d", CohortSchema::members(), CohortSchema::cohorts(), $ref ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- allow-listed column.
		);
		// phpcs:enable
		$added = 0;
		$full  = false;
		foreach ( array_map( 'intval', $users ) as $user_id ) {
			if ( in_array( $user_id, $taken, true ) ) {
				continue;
			}
			$result = self::add_member( (int) $id, $user_id, 'sync', 0, $added_by );
			if ( true === $result ) {
				++$added;
			} elseif ( 'ohmylms_cohort_full' === $result->get_error_code() ) {
				$full = true;
				break;
			}
		}
		return array(
			'added' => $added,
			'full'  => $full || ! self::has_seat( self::row( $id ) ),
		);
	}
}
