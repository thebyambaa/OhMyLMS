<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Read model of the Account Hub lists: schools, classes, rosters, staff, parents and roster intake.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Schools;

defined( 'ABSPATH' ) || exit;

/**
 * Paged reads behind `ohmylms/v1/account-hub` (docs/api/account-hub.md). Callers check permissions and
 * pass the scope they resolved: `null` for a platform administrator (every school) or the ids of the
 * schools the caller administers. Rows come back raw; shaping stays with the REST controllers. Writes go
 * through `Service`, `ClassManagement`, `Lifecycle`, `Versions` and `RosterImport`.
 */
final class Directory {

	/**
	 * LIKE pattern matching a search string anywhere.
	 *
	 * @param string $search Clean search text.
	 * @return string
	 */
	public static function like( $search ) {
		global $wpdb;

		return '%' . $wpdb->esc_like( (string) $search ) . '%';
	}

	/**
	 * Active schools a user administers.
	 *
	 * @param int $user_id User id.
	 * @return int[]
	 */
	public static function managed_school_ids( $user_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Current relationships decide scope; never persisted across requests.
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT m.school_id FROM %i m JOIN %i s ON s.id=m.school_id AND s.status='active' WHERE m.user_id=%d AND m.role='school_admin' AND m.status='active'", Schema::table( 'school_memberships' ), Schema::table( 'schools' ), $user_id ) );

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * SQL restricting a school id column to a scope.
	 *
	 * @param string     $column Trusted column expression.
	 * @param int[]|null $scope  School ids, or null for every school.
	 * @return string
	 */
	private static function scope_sql( $column, $scope ) {
		if ( null === $scope ) {
			return '';
		}

		return $scope ? " AND {$column} IN (" . implode( ',', array_map( 'intval', $scope ) ) . ')' : ' AND 1=0';
	}

	/*
	 * Schools.
	 */

	/**
	 * SQL restricting `s.status` to a list filter.
	 *
	 * @param string $status `all`, `active` or `archived`.
	 * @return string
	 */
	private static function school_status_sql( $status ) {
		return in_array( $status, array( 'active', 'archived' ), true ) ? " AND s.status='{$status}'" : '';
	}

	/**
	 * Number of schools matching a filter.
	 *
	 * @param string     $like   LIKE pattern on the name.
	 * @param string     $status `all`, `active` or `archived`.
	 * @param int[]|null $scope  Scope.
	 * @return int
	 */
	public static function count_schools( $like, $status, $scope ) {
		global $wpdb;
		$where = self::scope_sql( 's.id', $scope ) . self::school_status_sql( $status );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Integer scope and the status fragment are trusted.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i s WHERE s.name LIKE %s{$where}", Schema::table( 'schools' ), $like ) );
	}

	/**
	 * Page of schools with their active class, student and teacher counts.
	 *
	 * @param int        $id     Only this school, or 0.
	 * @param string     $like   LIKE pattern on the name.
	 * @param int        $limit  Rows.
	 * @param int        $offset Offset.
	 * @param string     $status `all`, `active` or `archived`.
	 * @param int[]|null $scope  Scope.
	 * @return array[]
	 */
	public static function schools( $id, $like, $limit, $offset, $status, $scope ) {
		global $wpdb;
		$s     = Schema::table( 'schools' );
		$c     = Schema::table( 'classes' );
		$m     = Schema::table( 'school_memberships' );
		$extra = self::scope_sql( 's.id', $scope ) . ( $id ? $wpdb->prepare( ' AND s.id=%d', $id ) : '' ) . self::school_status_sql( $status );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table names and the integer scope are trusted; values are prepared.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.id,s.name,s.slug,s.timezone,s.status,s.created_at,
				(SELECT COUNT(*) FROM $c c WHERE c.school_id=s.id AND c.status='active') AS class_count,
				(SELECT COUNT(*) FROM $m m WHERE m.school_id=s.id AND m.role='student' AND m.status='active') AS student_count,
				(SELECT COUNT(*) FROM $m m WHERE m.school_id=s.id AND m.role='teacher' AND m.status='active') AS teacher_count
				FROM $s s WHERE s.name LIKE %s{$extra} ORDER BY s.name, s.id LIMIT %d OFFSET %d",
				$like,
				$limit,
				$offset
			),
			ARRAY_A
		);
		// phpcs:enable

		return (array) $rows;
	}

	/**
	 * A school's academic years, newest first.
	 *
	 * @param int $school_id School id.
	 * @param int $limit     Most rows.
	 * @return array[]
	 */
	public static function years( $school_id, $limit ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bounded list of one school's years.
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id,label,starts_at,ends_at,status FROM %i WHERE school_id=%d ORDER BY starts_at DESC, id DESC LIMIT %d', Schema::table( 'academic_years' ), $school_id, $limit ), ARRAY_A );
	}

	/**
	 * FROM/WHERE of a school's active students.
	 *
	 * @param int    $school_id School id.
	 * @param string $like      LIKE pattern on the name.
	 * @return string Prepared SQL fragment.
	 */
	private static function school_students_from( $school_id, $like ) {
		global $wpdb;

		return $wpdb->prepare( "FROM %i u JOIN %i m ON m.user_id=u.ID AND m.school_id=%d AND m.role='student' AND m.status='active' WHERE u.display_name LIKE %s", $wpdb->users, Schema::table( 'school_memberships' ), $school_id, $like );
	}

	/**
	 * Number of active students of a school.
	 *
	 * @param int    $school_id School id.
	 * @param string $like      LIKE pattern on the name.
	 * @return int
	 */
	public static function count_school_students( $school_id, $like ) {
		global $wpdb;
		$from = self::school_students_from( $school_id, $like );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- $from is prepared.
		return (int) $wpdb->get_var( "SELECT COUNT(*) $from" );
	}

	/**
	 * Page of a school's active students (id, display_name), by name.
	 *
	 * @param int    $school_id School id.
	 * @param string $like      LIKE pattern on the name.
	 * @param int    $limit     Rows.
	 * @param int    $offset    Offset.
	 * @return array[]
	 */
	public static function school_students( $school_id, $like, $limit, $offset ) {
		global $wpdb;
		$from = self::school_students_from( $school_id, $like );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- $from is prepared.
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT u.ID AS id,u.display_name $from ORDER BY u.display_name, u.ID LIMIT %d OFFSET %d", $limit, $offset ), ARRAY_A );
	}

	/*
	 * Classes.
	 */

	/**
	 * Shared FROM/WHERE of class lists.
	 *
	 * @param array      $filter `class_id` (only this class, scope not applied), `like`, `school_id` (int or null) and `status`.
	 * @param int[]|null $scope  Scope.
	 * @return string Prepared SQL fragment.
	 */
	private static function classes_from( array $filter, $scope ) {
		global $wpdb;
		$sql = $wpdb->prepare( 'FROM %i c LEFT JOIN %i s ON s.id=c.school_id LEFT JOIN %i y ON y.id=c.academic_year_id WHERE 1=1', Schema::table( 'classes' ), Schema::table( 'schools' ), Schema::table( 'academic_years' ) );
		if ( ! empty( $filter['class_id'] ) ) {
			return $sql . $wpdb->prepare( ' AND c.id=%d', $filter['class_id'] );
		}
		$sql .= $wpdb->prepare( ' AND c.name LIKE %s', $filter['like'] ?? '%' );
		if ( isset( $filter['school_id'] ) ) {
			$sql .= $wpdb->prepare( ' AND c.school_id=%d', $filter['school_id'] );
		}
		if ( in_array( $filter['status'] ?? 'all', array( 'active', 'archived' ), true ) ) {
			$sql .= $wpdb->prepare( ' AND c.status=%s', $filter['status'] );
		}

		return $sql . self::scope_sql( 'c.school_id', $scope );
	}

	/**
	 * Number of classes matching a filter.
	 *
	 * @param array      $filter See classes_from().
	 * @param int[]|null $scope  Scope.
	 * @return int
	 */
	public static function count_classes( array $filter, $scope ) {
		global $wpdb;
		$from = self::classes_from( $filter, $scope );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- $from is prepared by classes_from().
		return (int) $wpdb->get_var( "SELECT COUNT(*) $from" );
	}

	/**
	 * Page of classes with school name, year label and active student and teacher counts.
	 *
	 * @param array      $filter See classes_from().
	 * @param int[]|null $scope  Scope.
	 * @param int        $limit  Rows.
	 * @param int        $offset Offset.
	 * @return array[]
	 */
	public static function classes( array $filter, $scope, $limit, $offset ) {
		global $wpdb;
		$from = self::classes_from( $filter, $scope );
		$m    = Schema::table( 'class_memberships' );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- $from is prepared by classes_from().
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.id,c.school_id,c.academic_year_id,c.name,c.subject,c.grade,c.status,s.name AS school_name,y.label AS year_label,
				(SELECT COUNT(*) FROM $m m WHERE m.class_id=c.id AND m.role='student' AND m.status='active') AS student_count,
				(SELECT COUNT(*) FROM $m m WHERE m.class_id=c.id AND m.role='teacher' AND m.status='active') AS teacher_count
				$from ORDER BY c.name, c.id LIMIT %d OFFSET %d",
				$limit,
				$offset
			),
			ARRAY_A
		);
		// phpcs:enable

		return (array) $rows;
	}

	/*
	 * Class membership.
	 */

	/**
	 * FROM/WHERE of a class's active members.
	 *
	 * @param int    $class_id Class id.
	 * @param string $like     LIKE pattern on the name.
	 * @param string $role     `student`, `teacher` or '' for both.
	 * @return string Prepared SQL fragment.
	 */
	private static function members_from( $class_id, $like, $role ) {
		global $wpdb;
		$from = $wpdb->prepare( "FROM %i cm JOIN %i u ON u.ID=cm.user_id WHERE cm.class_id=%d AND cm.status='active' AND u.display_name LIKE %s", Schema::table( 'class_memberships' ), $wpdb->users, $class_id, $like );
		if ( in_array( $role, array( 'student', 'teacher' ), true ) ) {
			$from .= $wpdb->prepare( ' AND cm.role=%s', $role );
		}

		return $from;
	}

	/**
	 * Number of a class's active members.
	 *
	 * @param int    $class_id Class id.
	 * @param string $like     LIKE pattern on the name.
	 * @param string $role     `student`, `teacher` or ''.
	 * @return int
	 */
	public static function count_members( $class_id, $like, $role ) {
		global $wpdb;
		$from = self::members_from( $class_id, $like, $role );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- $from is prepared.
		return (int) $wpdb->get_var( "SELECT COUNT(*) $from" );
	}

	/**
	 * Page of a class's active members, teachers first.
	 *
	 * @param int    $class_id Class id.
	 * @param string $like     LIKE pattern on the name.
	 * @param string $role     `student`, `teacher` or ''.
	 * @param int    $limit    Rows.
	 * @param int    $offset   Offset.
	 * @return array[] Rows with id, user_id, role, joined_at, display_name.
	 */
	public static function members( $class_id, $like, $role, $limit, $offset ) {
		global $wpdb;
		$from = self::members_from( $class_id, $like, $role );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- $from is prepared.
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT cm.id,cm.user_id,cm.role,cm.joined_at,u.display_name $from ORDER BY cm.role='teacher' DESC, u.display_name, cm.id LIMIT %d OFFSET %d", $limit, $offset ), ARRAY_A );
	}

	/**
	 * One active membership with the member's name, or an empty array.
	 *
	 * @param int    $class_id Class id.
	 * @param int    $user_id  User id.
	 * @param string $role     Role.
	 * @return array
	 */
	public static function member( $class_id, $user_id, $role ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Read back the saved membership.
		return (array) $wpdb->get_row( $wpdb->prepare( "SELECT cm.id,cm.user_id,cm.role,cm.joined_at,u.display_name FROM %i cm JOIN %i u ON u.ID=cm.user_id WHERE cm.class_id=%d AND cm.user_id=%d AND cm.role=%s AND cm.status='active'", Schema::table( 'class_memberships' ), $wpdb->users, $class_id, $user_id, $role ), ARRAY_A );
	}

	/**
	 * User ids of a class's active students.
	 *
	 * @param int $class_id Class id.
	 * @return int[]
	 */
	public static function class_student_ids( $class_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Current roster.
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM %i WHERE class_id=%d AND role='student' AND status='active'", Schema::table( 'class_memberships' ), $class_id ) ) );
	}

	/**
	 * Active membership row id, or 0.
	 *
	 * @param int    $class_id Class id.
	 * @param int    $user_id  User id.
	 * @param string $role     Role.
	 * @return int
	 */
	public static function active_member_id( $class_id, $user_id, $role ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Current roster state.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM %i WHERE class_id=%d AND user_id=%d AND role=%s AND status='active'", Schema::table( 'class_memberships' ), $class_id, $user_id, $role ) );
	}

	/**
	 * FROM/WHERE of school roster people who are not yet active class members in a role.
	 *
	 * @param int    $school_id School id.
	 * @param int    $class_id  Class id.
	 * @param string $role      Role.
	 * @param string $like      LIKE pattern on the name.
	 * @return string Prepared SQL fragment.
	 */
	private static function candidates_from( $school_id, $class_id, $role, $like ) {
		global $wpdb;

		return $wpdb->prepare(
			"FROM %i u JOIN %i sm ON sm.user_id=u.ID AND sm.school_id=%d AND sm.role=%s AND sm.status='active' WHERE u.display_name LIKE %s AND NOT EXISTS (SELECT 1 FROM %i cm WHERE cm.class_id=%d AND cm.user_id=u.ID AND cm.role=%s AND cm.status='active')",
			$wpdb->users,
			Schema::table( 'school_memberships' ),
			$school_id,
			$role,
			$like,
			Schema::table( 'class_memberships' ),
			$class_id,
			$role
		);
	}

	/**
	 * Number of people who can join a class in a role.
	 *
	 * @param int    $school_id School id.
	 * @param int    $class_id  Class id.
	 * @param string $role      Role.
	 * @param string $like      LIKE pattern on the name.
	 * @return int
	 */
	public static function count_candidates( $school_id, $class_id, $role, $like ) {
		global $wpdb;
		$from = self::candidates_from( $school_id, $class_id, $role, $like );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- $from is prepared.
		return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT u.ID) $from" );
	}

	/**
	 * Page of people who can join a class in a role (id, display_name), by name.
	 *
	 * @param int    $school_id School id.
	 * @param int    $class_id  Class id.
	 * @param string $role      Role.
	 * @param string $like      LIKE pattern on the name.
	 * @param int    $limit     Rows.
	 * @param int    $offset    Offset.
	 * @return array[]
	 */
	public static function candidates( $school_id, $class_id, $role, $like, $limit, $offset ) {
		global $wpdb;
		$from = self::candidates_from( $school_id, $class_id, $role, $like );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- $from is prepared.
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT u.ID AS id,u.display_name $from ORDER BY u.display_name, u.ID LIMIT %d OFFSET %d", $limit, $offset ), ARRAY_A );
	}

	/**
	 * Number of a class's active assignments.
	 *
	 * @param int    $class_id Class id.
	 * @param string $like     LIKE pattern on the title.
	 * @return int
	 */
	public static function count_class_work( $class_id, $like ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One count.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE class_id=%d AND status='active' AND title LIKE %s", Schema::table( 'learning_assignments' ), $class_id, $like ) );
	}

	/**
	 * Page of a class's active assignments with recipient and completion counts, newest first.
	 *
	 * @param int    $class_id Class id.
	 * @param string $like     LIKE pattern on the title.
	 * @param int    $limit    Rows.
	 * @param int    $offset   Offset.
	 * @return array[] Rows with id, title, course_id, content_id, due_at, created_at, recipients, completed.
	 */
	public static function class_work( $class_id, $like, $limit, $offset ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One page with grouped recipient counts.
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT a.id,a.title,a.course_id,a.content_id,a.due_at,a.created_at,COUNT(r.id) AS recipients,SUM(CASE WHEN r.completed_at IS NOT NULL THEN 1 ELSE 0 END) AS completed FROM %i a LEFT JOIN %i r ON r.assignment_id=a.id WHERE a.class_id=%d AND a.status='active' AND a.title LIKE %s GROUP BY a.id ORDER BY a.id DESC LIMIT %d OFFSET %d", Schema::table( 'learning_assignments' ), Schema::table( 'assignment_recipients' ), $class_id, $like, $limit, $offset ), ARRAY_A );
	}

	/*
	 * Teachers and parents.
	 */

	/**
	 * WHERE clause selecting the people of a kind visible in a scope.
	 *
	 * @param string     $kind      `teacher` or `parent`.
	 * @param int|null   $school_id Optional school filter.
	 * @param int[]|null $scope     Scope.
	 * @return string Prepared SQL fragment on `u`.
	 */
	private static function people_where( $kind, $school_id, $scope ) {
		global $wpdb;
		$school = $school_id ? $wpdb->prepare( ' AND x.school_id=%d', $school_id ) : '';
		if ( 'teacher' === $kind ) {
			$relation = $wpdb->prepare( "EXISTS (SELECT 1 FROM %i x JOIN %i xs ON xs.id=x.school_id AND xs.status='active' WHERE x.user_id=u.ID AND x.role='teacher' AND x.status='active'", Schema::table( 'school_memberships' ), Schema::table( 'schools' ) );
		} else {
			$relation = $wpdb->prepare( "EXISTS (SELECT 1 FROM %i x WHERE x.guardian_user_id=u.ID AND x.status='active'", Schema::table( 'guardian_links' ) );
		}
		$relation .= $school . self::scope_sql( 'x.school_id', $scope ) . ')';
		if ( null !== $scope || $school_id ) {
			return $relation;
		}
		$role = $wpdb->prepare( 'EXISTS (SELECT 1 FROM %i um WHERE um.user_id=u.ID AND um.meta_key=%s AND um.meta_value LIKE %s)', $wpdb->usermeta, $wpdb->get_blog_prefix() . 'capabilities', '%' . $wpdb->esc_like( '"ohmylms_' . $kind . '"' ) . '%' );

		return "($role OR $relation)";
	}

	/**
	 * FROM/WHERE of a people list.
	 *
	 * @param string     $kind         `teacher` or `parent`.
	 * @param int|null   $school_id    Optional school filter.
	 * @param string     $like         LIKE pattern.
	 * @param bool       $search_email Whether the pattern also matches email addresses.
	 * @param int[]|null $scope        Scope.
	 * @return string Prepared SQL fragment.
	 */
	private static function people_from( $kind, $school_id, $like, $search_email, $scope ) {
		global $wpdb;
		$search = $search_email ? $wpdb->prepare( '(u.display_name LIKE %s OR u.user_email LIKE %s)', $like, $like ) : $wpdb->prepare( 'u.display_name LIKE %s', $like );

		return $wpdb->prepare( 'FROM %i u WHERE ', $wpdb->users ) . self::people_where( $kind, $school_id, $scope ) . ' AND ' . $search;
	}

	/**
	 * Number of teachers or parents.
	 *
	 * @param string     $kind         `teacher` or `parent`.
	 * @param int|null   $school_id    Optional school filter.
	 * @param string     $like         LIKE pattern.
	 * @param bool       $search_email Whether the pattern also matches email addresses.
	 * @param int[]|null $scope        Scope.
	 * @return int
	 */
	public static function count_people( $kind, $school_id, $like, $search_email, $scope ) {
		global $wpdb;
		$from = self::people_from( $kind, $school_id, $like, $search_email, $scope );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- $from is assembled from prepared fragments.
		return (int) $wpdb->get_var( "SELECT COUNT(*) $from" );
	}

	/**
	 * Page of teachers or parents (ID, display_name, user_email), by name.
	 *
	 * @param string     $kind         `teacher` or `parent`.
	 * @param int|null   $school_id    Optional school filter.
	 * @param string     $like         LIKE pattern.
	 * @param bool       $search_email Whether the pattern also matches email addresses.
	 * @param int[]|null $scope        Scope.
	 * @param int        $limit        Rows.
	 * @param int        $offset       Offset.
	 * @return array[]
	 */
	public static function people( $kind, $school_id, $like, $search_email, $scope, $limit, $offset ) {
		global $wpdb;
		$from = self::people_from( $kind, $school_id, $like, $search_email, $scope );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- $from is assembled from prepared fragments.
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT u.ID,u.display_name,u.user_email $from ORDER BY u.display_name, u.ID LIMIT %d OFFSET %d", $limit, $offset ), ARRAY_A );
	}

	/**
	 * Active schools where these users teach (user_id, id, name), by school name.
	 *
	 * @param int[]      $user_ids User ids.
	 * @param int[]|null $scope    Scope.
	 * @return array[]
	 */
	public static function teacher_schools( array $user_ids, $scope ) {
		global $wpdb;
		$in    = $user_ids ? implode( ',', array_map( 'intval', $user_ids ) ) : '0';
		$where = self::scope_sql( 's.id', $scope );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Integer ids and scope are trusted.
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT m.user_id,s.id,s.name FROM %i m JOIN %i s ON s.id=m.school_id AND s.status='active' WHERE m.role='teacher' AND m.status='active' AND m.user_id IN ($in){$where} ORDER BY s.name, s.id", Schema::table( 'school_memberships' ), Schema::table( 'schools' ) ), ARRAY_A );
	}

	/**
	 * Active classes each of these users teaches.
	 *
	 * @param int[]      $user_ids User ids.
	 * @param int[]|null $scope    Scope.
	 * @return array<int,int> User id => classes.
	 */
	public static function teacher_class_counts( array $user_ids, $scope ) {
		global $wpdb;
		$in    = $user_ids ? implode( ',', array_map( 'intval', $user_ids ) ) : '0';
		$where = self::scope_sql( 'c.school_id', $scope );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Integer ids and scope are trusted.
		return self::counts( $wpdb->get_results( $wpdb->prepare( "SELECT cm.user_id,COUNT(*) AS total FROM %i cm JOIN %i c ON c.id=cm.class_id AND c.status='active' WHERE cm.role='teacher' AND cm.status='active' AND cm.user_id IN ($in){$where} GROUP BY cm.user_id", Schema::table( 'class_memberships' ), Schema::table( 'classes' ) ), ARRAY_A ) );
	}

	/**
	 * Active children linked to each of these parents.
	 *
	 * @param int[]      $user_ids Parent ids.
	 * @param int[]|null $scope    Scope.
	 * @return array<int,int> Parent id => linked children.
	 */
	public static function child_counts( array $user_ids, $scope ) {
		global $wpdb;
		$in    = $user_ids ? implode( ',', array_map( 'intval', $user_ids ) ) : '0';
		$where = self::scope_sql( 'g.school_id', $scope );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Integer ids and scope are trusted.
		return self::counts( $wpdb->get_results( $wpdb->prepare( "SELECT g.guardian_user_id AS user_id,COUNT(*) AS total FROM %i g WHERE g.status='active' AND g.guardian_user_id IN ($in){$where} GROUP BY g.guardian_user_id", Schema::table( 'guardian_links' ) ), ARRAY_A ) );
	}

	/**
	 * Map `user_id`/`total` rows.
	 *
	 * @param mixed $rows Rows.
	 * @return array<int,int>
	 */
	private static function counts( $rows ) {
		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row['user_id'] ] = (int) $row['total'];
		}

		return $counts;
	}

	/**
	 * Whether a user is a parent visible in a scope.
	 *
	 * @param int        $user_id User id.
	 * @param int[]|null $scope   Scope.
	 * @return bool
	 */
	public static function is_parent( $user_id, $scope ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Assembled from prepared fragments.
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT u.ID FROM %i u WHERE u.ID=%d AND ', $wpdb->users, $user_id ) . self::people_where( 'parent', null, $scope ) );
	}

	/**
	 * A parent's active links in scope, by child name.
	 *
	 * @param int        $guardian_id Parent id.
	 * @param int[]|null $scope       Scope.
	 * @param int        $limit       Most rows.
	 * @return array[] Rows with id, school_id, school_name, student_user_id, display_name, approved_at.
	 */
	public static function children( $guardian_id, $scope, $limit ) {
		global $wpdb;
		$where = self::scope_sql( 'g.school_id', $scope );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Integer scope is trusted; values are prepared.
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT g.id,g.school_id,s.name AS school_name,g.student_user_id,u.display_name,g.approved_at FROM %i g JOIN %i s ON s.id=g.school_id JOIN %i u ON u.ID=g.student_user_id WHERE g.guardian_user_id=%d AND g.status='active'{$where} ORDER BY u.display_name, g.id LIMIT %d", Schema::table( 'guardian_links' ), Schema::table( 'schools' ), $wpdb->users, $guardian_id, $limit ), ARRAY_A );
	}

	/**
	 * Open guardian invitations addressed to an email in scope, newest first.
	 *
	 * @param string     $email Email.
	 * @param int[]|null $scope Scope.
	 * @param int        $limit Most rows.
	 * @return array[] Rows with id, school_id, school_name, student_user_id, display_name, expires_at.
	 */
	public static function pending_children( $email, $scope, $limit ) {
		global $wpdb;
		$where = self::scope_sql( 'i.school_id', $scope );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Integer scope is trusted; values are prepared.
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT i.id,i.school_id,s.name AS school_name,i.student_user_id,u.display_name,i.expires_at FROM %i i JOIN %i s ON s.id=i.school_id LEFT JOIN %i u ON u.ID=i.student_user_id WHERE i.role='guardian' AND i.email=%s AND i.consumed_at IS NULL AND i.revoked_at IS NULL AND i.expires_at>%s{$where} ORDER BY i.id DESC LIMIT %d", Schema::table( 'school_invitations' ), Schema::table( 'schools' ), $wpdb->users, $email, Service::now(), $limit ), ARRAY_A );
	}

	/**
	 * Id of any open guardian invitation for a student and email, or 0 (first row found; the
	 * Guardians list uses `Guardians::pending()`, which takes the newest).
	 *
	 * @param int    $school_id  School id.
	 * @param int    $student_id Student id.
	 * @param string $email      Email.
	 * @return int
	 */
	public static function open_guardian_invitation( $school_id, $student_id, $email ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Current invitation state.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM %i WHERE school_id=%d AND student_user_id=%d AND role='guardian' AND email=%s AND consumed_at IS NULL AND revoked_at IS NULL AND expires_at>%s", Schema::table( 'school_invitations' ), $school_id, $student_id, $email, Service::now() ) );
	}

	/*
	 * Roster intake.
	 */

	/**
	 * Id of the newest open invitation for an email and role in a school, or 0.
	 *
	 * @param int    $school_id School id.
	 * @param string $email     Email.
	 * @param string $role      Role.
	 * @return int
	 */
	public static function pending_invitation( $school_id, $email, $role ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Current invitation state.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE school_id=%d AND role=%s AND email=%s AND consumed_at IS NULL AND revoked_at IS NULL AND expires_at>%s ORDER BY id DESC LIMIT 1', Schema::table( 'school_invitations' ), $school_id, $role, $email, Service::now() ) );
	}

	/**
	 * User id of a school student ID, or 0.
	 *
	 * @param int    $school_id School id.
	 * @param string $external  School student ID.
	 * @return int
	 */
	public static function student_by_external_id( $school_id, $external ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Current roster state; the column is unique per school.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM %i WHERE school_id=%d AND external_student_id=%s', Schema::table( 'school_student_profiles' ), $school_id, $external ) );
	}

	/**
	 * FROM/WHERE of a school's invitations.
	 *
	 * @param int    $school_id    School id.
	 * @param string $like         LIKE pattern on the email.
	 * @param bool   $pending_only Only open invitations.
	 * @return string Prepared SQL fragment.
	 */
	private static function invitations_from( $school_id, $like, $pending_only ) {
		global $wpdb;
		$from = $wpdb->prepare( 'FROM %i i LEFT JOIN %i c ON c.id=i.class_id LEFT JOIN %i u ON u.ID=i.student_user_id WHERE i.school_id=%d AND i.email LIKE %s', Schema::table( 'school_invitations' ), Schema::table( 'classes' ), $wpdb->users, $school_id, $like );
		if ( $pending_only ) {
			$from .= $wpdb->prepare( ' AND i.consumed_at IS NULL AND i.revoked_at IS NULL AND i.expires_at>%s', Service::now() );
		}

		return $from;
	}

	/**
	 * Number of a school's invitations.
	 *
	 * @param int    $school_id    School id.
	 * @param string $like         LIKE pattern on the email.
	 * @param bool   $pending_only Only open invitations.
	 * @return int
	 */
	public static function count_invitations( $school_id, $like, $pending_only ) {
		global $wpdb;
		$from = self::invitations_from( $school_id, $like, $pending_only );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- $from is prepared.
		return (int) $wpdb->get_var( "SELECT COUNT(*) $from" );
	}

	/**
	 * Page of a school's invitations, newest first (never the token).
	 *
	 * @param int    $school_id    School id.
	 * @param string $like         LIKE pattern on the email.
	 * @param bool   $pending_only Only open invitations.
	 * @param int    $limit        Rows.
	 * @param int    $offset       Offset.
	 * @return array[]
	 */
	public static function invitations( $school_id, $like, $pending_only, $limit, $offset ) {
		global $wpdb;
		$from = self::invitations_from( $school_id, $like, $pending_only );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- $from is prepared.
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT i.id,i.role,i.email,i.class_id,c.name AS class_name,i.student_user_id,u.display_name AS student_name,i.expires_at,i.consumed_at,i.revoked_at,i.created_at $from ORDER BY i.id DESC LIMIT %d OFFSET %d", $limit, $offset ), ARRAY_A );
	}

	/**
	 * FROM/WHERE of a school's managed (no email) student accounts.
	 *
	 * @param int    $school_id School id.
	 * @param string $like      LIKE pattern on name and school student ID.
	 * @return string Prepared SQL fragment.
	 */
	private static function managed_students_from( $school_id, $like ) {
		global $wpdb;

		return $wpdb->prepare(
			"FROM %i p JOIN %i u ON u.ID=p.user_id JOIN %i um ON um.user_id=u.ID AND um.meta_key='_ohmylms_managed_school' AND um.meta_value=%s JOIN %i m ON m.school_id=p.school_id AND m.user_id=p.user_id AND m.role='student' AND m.status='active' WHERE p.school_id=%d AND (u.display_name LIKE %s OR p.external_student_id LIKE %s)",
			Schema::table( 'school_student_profiles' ),
			$wpdb->users,
			$wpdb->usermeta,
			(string) (int) $school_id,
			Schema::table( 'school_memberships' ),
			$school_id,
			$like,
			$like
		);
	}

	/**
	 * Number of a school's managed student accounts.
	 *
	 * @param int    $school_id School id.
	 * @param string $like      LIKE pattern.
	 * @return int
	 */
	public static function count_managed_students( $school_id, $like ) {
		global $wpdb;
		$from = self::managed_students_from( $school_id, $like );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- $from is prepared.
		return (int) $wpdb->get_var( "SELECT COUNT(*) $from" );
	}

	/**
	 * Page of a school's managed student accounts, by name.
	 *
	 * @param int    $school_id School id.
	 * @param string $like      LIKE pattern.
	 * @param int    $limit     Rows.
	 * @param int    $offset    Offset.
	 * @return array[] Rows with id, display_name, user_login, external_student_id, joined_at.
	 */
	public static function managed_students( $school_id, $like, $limit, $offset ) {
		global $wpdb;
		$from = self::managed_students_from( $school_id, $like );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- $from is prepared.
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT u.ID AS id,u.display_name,u.user_login,p.external_student_id,m.joined_at $from ORDER BY u.display_name, u.ID LIMIT %d OFFSET %d", $limit, $offset ), ARRAY_A );
	}

	/**
	 * Latest expiry of each student's open password-setup link.
	 *
	 * @param int   $school_id   School id.
	 * @param int[] $student_ids Student ids.
	 * @return array<int,string> Student id => expiry.
	 */
	public static function setup_link_expiries( $school_id, array $student_ids ) {
		global $wpdb;
		$setup = array();
		if ( ! $student_ids ) {
			return $setup;
		}
		$in = implode( ',', array_fill( 0, count( $student_ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- One %d per id; the sniff cannot count a generated placeholder list.
		$links = $wpdb->get_results( $wpdb->prepare( "SELECT student_user_id,MAX(expires_at) AS expires_at FROM %i WHERE school_id=%d AND role='activation' AND consumed_at IS NULL AND revoked_at IS NULL AND expires_at>%s AND student_user_id IN ($in) GROUP BY student_user_id", array_merge( array( Schema::table( 'school_invitations' ), $school_id, Service::now() ), array_map( 'intval', $student_ids ) ) ), ARRAY_A );
		foreach ( (array) $links as $link ) {
			$setup[ (int) $link['student_user_id'] ] = (string) $link['expires_at'];
		}

		return $setup;
	}
}
