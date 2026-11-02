<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * The audience a dashboard figure covers: every learner, one school or one class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard;

use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Scope value: `all`, `school:<id>` or `class:<id>` (docs/api/dashboard.md, "Scope").
 *
 * A scope says *what* is counted, never *who* may see it: build one with {@see Scope::from_request()} or
 * {@see ScopeResolver::resolve()}, which check that the user may use it. Learners and courses are read
 * once per instance; `all` is unrestricted (both return null), so SQL never carries a site-wide id list.
 */
final class Scope {

	const ALL       = 'all';
	const SCHOOL    = 'school';
	const CLASSROOM = 'class';

	/**
	 * REST argument pattern of a scope id.
	 */
	const PATTERN = '^(all|school:[1-9][0-9]{0,18}|class:[1-9][0-9]{0,18})$';

	/**
	 * Type: all, school or class.
	 *
	 * @var string
	 */
	private $type;

	/**
	 * School or class id; 0 for all.
	 *
	 * @var int
	 */
	private $id;

	/**
	 * Display name.
	 *
	 * @var string
	 */
	private $label;

	/**
	 * School of a class (0 for an independent class) or the school itself.
	 *
	 * @var int
	 */
	private $school_id;

	/**
	 * Name of that school ('' when none).
	 *
	 * @var string
	 */
	private $school_name;

	/**
	 * Learner ids, once read.
	 *
	 * @var int[]|null
	 */
	private $learners = null;

	/**
	 * Course ids, once read.
	 *
	 * @var int[]|null
	 */
	private $courses = null;

	/**
	 * Constructor; use the named constructors.
	 *
	 * @param string $type        Type.
	 * @param int    $id          Id.
	 * @param string $label       Display name.
	 * @param int    $school_id   School id.
	 * @param string $school_name School name.
	 */
	private function __construct( $type, $id, $label, $school_id = 0, $school_name = '' ) {
		$this->type        = $type;
		$this->id          = (int) $id;
		$this->label       = (string) $label;
		$this->school_id   = (int) $school_id;
		$this->school_name = (string) $school_name;
	}

	/**
	 * Every learner of the site (site administrators only).
	 *
	 * @return Scope
	 */
	public static function all() {
		return new self( self::ALL, 0, __( 'All schools', 'ohmylms' ) );
	}

	/**
	 * One school.
	 *
	 * @param int    $id   School id.
	 * @param string $name School name.
	 * @return Scope
	 */
	public static function school( $id, $name ) {
		return new self( self::SCHOOL, $id, $name, $id, $name );
	}

	/**
	 * One class.
	 *
	 * @param int    $id          Class id.
	 * @param string $name        Class name.
	 * @param int    $school_id   School id; 0 for an independent class.
	 * @param string $school_name School name.
	 * @return Scope
	 */
	public static function classroom( $id, $name, $school_id, $school_name ) {
		return new self( self::CLASSROOM, $id, $name, $school_id, $school_name );
	}

	/**
	 * Split a scope id into type and numeric id.
	 *
	 * @param mixed $value Candidate such as `school:3`.
	 * @return array{0:string,1:int}|null
	 */
	public static function parse( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/' . self::PATTERN . '/', $value ) ) {
			return null;
		}
		if ( self::ALL === $value ) {
			return array( self::ALL, 0 );
		}
		list( $type, $id ) = explode( ':', $value, 2 );
		return array( $type, (int) $id );
	}

	/**
	 * The scope a REST request asks for (`scope`, default: the user's first scope), checked against what
	 * the user may use. Errors: 401/403 `ohmylms_dashboard_cannot_view` (no dashboard access), 403
	 * `ohmylms_dashboard_forbidden_scope` (not one of the user's scopes) and, for site administrators
	 * only, 404 `ohmylms_dashboard_scope_not_found` (no such active school or class).
	 *
	 * @param WP_REST_Request $request Request.
	 * @param int             $user_id User; the current user by default.
	 * @return Scope|WP_Error
	 */
	public static function from_request( $request, $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		$value   = $request['scope'];
		return ScopeResolver::resolve( null === $value || '' === $value ? null : (string) $value, $user_id );
	}

	/**
	 * Type.
	 *
	 * @return string `all`, `school` or `class`.
	 */
	public function type() {
		return $this->type;
	}

	/**
	 * School or class id (0 for all).
	 *
	 * @return int
	 */
	public function id() {
		return $this->id;
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function label() {
		return $this->label;
	}

	/**
	 * School of the scope (the school itself, a class's school, 0 otherwise).
	 *
	 * @return int
	 */
	public function school_id() {
		return $this->school_id;
	}

	/**
	 * Whether this is the site-wide scope.
	 *
	 * @return bool
	 */
	public function is_all() {
		return self::ALL === $this->type;
	}

	/**
	 * Whether commerce and other site-wide figures belong in this scope. Only `all` (which only site
	 * administrators can use): orders, payments and subscriptions are not attributed to schools.
	 *
	 * @return bool
	 */
	public function includes_commerce() {
		return $this->is_all();
	}

	/**
	 * Stable id: `all`, `school:<id>` or `class:<id>`. Also the scope's part of cache keys.
	 *
	 * @return string
	 */
	public function key() {
		return $this->is_all() ? self::ALL : $this->type . ':' . $this->id;
	}

	/**
	 * Cache key fragment; equal for equal scopes, whoever asks.
	 *
	 * @return string
	 */
	public function cache_key() {
		return $this->key();
	}

	/**
	 * Learners in the scope: active student members of the school, or the class roster (students who are
	 * still on their school's roster). Null for `all` (no restriction).
	 *
	 * @return int[]|null Sorted user ids.
	 */
	public function learner_ids() {
		if ( $this->is_all() ) {
			return null;
		}
		if ( null === $this->learners ) {
			$this->learners = self::SCHOOL === $this->type ? Directory::school_learners( $this->id ) : Directory::class_learners( $this->id );
		}
		return $this->learners;
	}

	/**
	 * Courses in the scope: courses its learners are enrolled in, plus courses linked to the class (or to
	 * the school's classes). Null for `all` (no restriction).
	 *
	 * @return int[]|null Sorted course ids.
	 */
	public function course_ids() {
		if ( $this->is_all() ) {
			return null;
		}
		if ( null === $this->courses ) {
			$linked        = self::SCHOOL === $this->type ? Directory::school_linked_courses( $this->id ) : Directory::class_linked_courses( $this->id );
			$enrolled      = Enrolments::course_ids_for_learners( (array) $this->learner_ids() );
			$ids           = array_values( array_unique( array_merge( $linked, $enrolled ) ) );
			$this->courses = Directory::existing_courses( $ids );
		}
		return $this->courses;
	}

	/**
	 * SQL condition limiting a column to the scope's learners, for `WHERE … AND <condition>`.
	 *
	 * Returns `1 = 1` for `all` and `1 = 0` for a scope without learners, so callers can always append it.
	 *
	 * @param string $column Trusted column expression such as `a.user_id`.
	 * @param array  $args   Placeholder values, appended to.
	 * @return string
	 */
	public function learner_condition( $column, array &$args ) {
		return self::in_condition( $column, $this->learner_ids(), $args );
	}

	/**
	 * SQL condition limiting a column to the scope's courses (see {@see Scope::learner_condition()}).
	 *
	 * @param string $column Trusted column expression such as `e.course_id`.
	 * @param array  $args   Placeholder values, appended to.
	 * @return string
	 */
	public function course_condition( $column, array &$args ) {
		return self::in_condition( $column, $this->course_ids(), $args );
	}

	/**
	 * `column IN (%d, …)` for a list, `1 = 1` for null (unrestricted), `1 = 0` for an empty list.
	 *
	 * @param string     $column Trusted column expression.
	 * @param int[]|null $ids    Ids.
	 * @param array      $args   Placeholder values, appended to.
	 * @return string
	 */
	public static function in_condition( $column, $ids, array &$args ) {
		if ( null === $ids ) {
			return '1 = 1';
		}
		if ( ! $ids ) {
			return '1 = 0';
		}
		foreach ( $ids as $id ) {
			$args[] = (int) $id;
		}
		return $column . ' IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')';
	}

	/**
	 * Response representation.
	 *
	 * @return array{id:string,type:string,label:string,school_id:int,school_name:string,commerce:bool}
	 */
	public function to_array() {
		return array(
			'id'          => $this->key(),
			'type'        => $this->type,
			'label'       => $this->label,
			'school_id'   => $this->school_id,
			'school_name' => $this->school_name,
			'commerce'    => $this->includes_commerce(),
		);
	}

	/**
	 * The `scope` REST argument.
	 *
	 * @return array
	 */
	public static function arg() {
		return array(
			'description' => __( 'Audience: "all", "school:<id>" or "class:<id>". Defaults to the first scope the user may use.', 'ohmylms' ),
			'type'        => 'string',
			'pattern'     => self::PATTERN,
		);
	}

	/**
	 * Error for a scope outside the user's.
	 *
	 * @return WP_Error
	 */
	public static function forbidden() {
		return new WP_Error( 'ohmylms_dashboard_forbidden_scope', __( 'Sorry, you cannot view this school or class.', 'ohmylms' ), array( 'status' => 403 ) );
	}

	/**
	 * Error for an unknown or inactive school or class (site administrators only).
	 *
	 * @return WP_Error
	 */
	public static function not_found() {
		return new WP_Error( 'ohmylms_dashboard_scope_not_found', __( 'That school or class does not exist or is not active.', 'ohmylms' ), array( 'status' => 404 ) );
	}
}
