<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the Composer PSR-4 class/file mapping.
/**
 * Immutable activity launch context; never infer home learning from enrollment.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Family;

use OhMyLMS\Schools\Schema as SchoolSchema;

defined( 'ABSPATH' ) || exit;

/** Signed, session-bound context is captured only when a new source row is created. */
final class Provenance {

	/** Connect verified source lifecycle events. */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'private_headers' ), 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'ohmylms_attempt_started', array( __CLASS__, 'quiz_started' ) );
		add_action( 'ohmylms_attempt_submitted', array( __CLASS__, 'quiz_completed' ) );
		add_action( 'ohmylms_verified_lesson_completed', array( __CLASS__, 'lesson' ), 10, 4 );
		add_action( 'ohmylms_practice_started', array( __CLASS__, 'practice_started' ) );
		add_action( 'ohmylms_practice_completed', array( __CLASS__, 'practice_completed' ) );
		add_action( 'ohmylms_assignment_submitted', array( __CLASS__, 'assignment' ), 10, 4 );
	}

	/** Shared, small context chooser styles for course players and practice shortcodes. */
	public static function assets() {
		if ( Schema::enabled() && is_user_logged_in() && self::learning_page() ) {
			wp_enqueue_style( 'ohmylms-learning-context', plugins_url( 'assets/css/learning-context.css', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/css/learning-context.css' ) );
		}
	}

	/**
	 * Scope context styles and private headers to supported learner pages.
	 *
	 * @return bool
	 */
	private static function learning_page() {
		$post = get_post();
		return is_singular( \OhMyLMS\Student\PlayerContext::TYPES ) || (bool) get_query_var( 'ohmylms_practice' ) || (bool) get_query_var( 'ohmylms_learning' ) || ( $post && has_shortcode( $post->post_content, 'ohmylms_practice' ) );
	}

	/** Keep signed context choices out of shared caches and cross-origin referrers. */
	public static function private_headers() {
		$post = get_post();
		$hub  = $post && has_shortcode( $post->post_content, 'ohmylms_student_hub' );
		if ( ( is_user_logged_in() && ( self::learning_page() || $hub ) ) || isset( $_GET['oml_origin'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Private learner pages and signed context links must not be shared through caches.
			nocache_headers();
			header( 'Referrer-Policy: same-origin' );
		}
	}

	/**
	 * Sign one explicit choice for this learner, source and session.
	 *
	 * @param string $kind Source kind.
	 * @param int    $course Course ID.
	 * @param int    $content Content or skill ID.
	 * @param int    $assignment Distribution ID; zero means an explicit home launch.
	 * @return string
	 */
	public static function token( $kind, $course, $content, $assignment = 0 ) {
		$data = implode( ':', array( $kind, absint( $course ), absint( $content ), absint( $assignment ) ) );
		return $data . ':' . wp_create_nonce( 'ohmylms_origin:' . $data );
	}

	/**
	 * Resolve a signed choice; untrusted IDs alone never establish origin.
	 *
	 * @param string $token Signed choice.
	 * @param string $kind Expected source kind.
	 * @param int    $course Expected course.
	 * @param int    $content Expected content.
	 * @return array Verified scope or unknown.
	 */
	public static function resolve( $token, $kind, $course, $content ) {
		$unknown = array(
			'origin'        => 'unknown',
			'assignment_id' => 0,
			'school_id'     => 0,
			'class_id'      => 0,
		);
		$parts   = explode( ':', (string) $token );
		if ( ! is_user_logged_in() || \OhMyLMS\Schools\ViewAs::session() || 5 !== count( $parts ) || $kind !== $parts[0] || (int) $parts[1] !== (int) $course || (int) $parts[2] !== (int) $content ) {
			return $unknown;
		}
		$data = implode( ':', array_slice( $parts, 0, 4 ) );
		if ( ! wp_verify_nonce( $parts[4], 'ohmylms_origin:' . $data ) ) {
			return $unknown;
		}
		if ( ! absint( $parts[3] ) ) {
			return array_merge( $unknown, array( 'origin' => Schema::SCOPE ) );
		}
		foreach ( self::distributions( $course, 'practice' === $kind ? 0 : $content ) as $row ) {
			if ( absint( $parts[3] ) === (int) $row['id'] ) {
				return array(
					'origin'        => 'school',
					'assignment_id' => (int) $row['id'],
					'school_id'     => (int) $row['school_id'],
					'class_id'      => (int) $row['class_id'],
				);
			}
		}
		return $unknown;
	}

	/**
	 * Current distributions actually received by this learner, never merely course memberships.
	 *
	 * @param int $course Course ID.
	 * @param int $content Content ID.
	 * @return array
	 */
	private static function distributions( $course, $content ) {
		if ( ! is_user_logged_in() || ( defined( 'OHMYLMS_SCHOOLS_ENABLED' ) && ! OHMYLMS_SCHOOLS_ENABLED ) || SchoolSchema::VERSION !== get_option( 'ohmylms_school_schema' ) ) {
			return array();
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh recipient and relationship authorization.
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT a.id,a.title,a.school_id,a.class_id FROM %i a JOIN %i r ON r.assignment_id=a.id JOIN %i c ON c.id=a.class_id AND c.school_id=a.school_id AND c.status='active' JOIN %i s ON s.id=a.school_id AND s.status='active' JOIN %i cm ON cm.class_id=c.id AND cm.user_id=r.student_user_id AND cm.role='student' AND cm.status='active' JOIN %i sm ON sm.school_id=s.id AND sm.user_id=r.student_user_id AND sm.role='student' AND sm.status='active' WHERE a.status='active' AND r.student_user_id=%d AND a.course_id=%d AND (a.content_id=0 OR a.content_id=%d) ORDER BY a.id DESC LIMIT 100", SchoolSchema::table( 'learning_assignments' ), SchoolSchema::table( 'assignment_recipients' ), SchoolSchema::table( 'classes' ), SchoolSchema::table( 'schools' ), SchoolSchema::table( 'class_memberships' ), SchoolSchema::table( 'school_memberships' ), get_current_user_id(), $course, $content ), ARRAY_A );
	}

	/**
	 * Read launch context from this page or its same-origin request referrer.
	 * The referrer is only transport: the nonce authenticates user/session/content.
	 * Missing transport always yields unknown, never an inferred home origin.
	 *
	 * @return string
	 */
	private static function incoming() {
		$value = isset( $_GET['oml_origin'] ) && is_string( $_GET['oml_origin'] ) ? sanitize_text_field( wp_unslash( $_GET['oml_origin'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The contained nonce is verified by resolve().
		if ( $value ) {
			return $value;
		}
		$ref = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		if ( wp_parse_url( home_url( '/' ), PHP_URL_HOST ) !== wp_parse_url( $ref, PHP_URL_HOST ) ) {
			return '';
		}
		parse_str( (string) wp_parse_url( $ref, PHP_URL_QUERY ), $query );
		return isset( $query['oml_origin'] ) && is_string( $query['oml_origin'] ) ? $query['oml_origin'] : '';
	}

	/**
	 * Save a newly-created source once. Resumes, replays and reviews cannot reclassify it.
	 *
	 * @param string $kind Source kind.
	 * @param int    $id Source row ID.
	 * @param int    $learner Source owner.
	 * @param int    $course Course ID.
	 * @param int    $content Content or skill ID.
	 * @param bool   $complete Whether already complete.
	 */
	private static function capture( $kind, $id, $learner, $course, $content, $complete = false ) {
		if ( ! Schema::enabled() || ! $id || ! $learner || get_current_user_id() !== (int) $learner ) {
			return;
		}
		global $wpdb;
		if ( 'lesson' === $kind ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Verify source ownership before recording its context.
			$source = $wpdb->get_row( $wpdb->prepare( 'SELECT e.user_id AS learner,e.course_id AS course,p.content_id AS content FROM %i p JOIN %i e ON e.id=p.enrollment_id WHERE p.id=%d AND p.status=%s', $wpdb->prefix . 'ohmylms_user_progress', $wpdb->prefix . 'ohmylms_user_enrollment', $id, 'completed' ), ARRAY_A );
		} else {
			$map = array(
				'quiz'       => array( $wpdb->prefix . 'ohmylms_quiz_attempts', 'student_id', 'quiz_id' ),
				'assignment' => array( $wpdb->prefix . 'ohmylms_assignment_attempts', 'user_id', 'assignment_id' ),
				'practice'   => array( \OhMyLMS\Assessment\Schema::table( 'practice_sessions' ), 'student_id', 'term_id' ),
			);
			if ( ! isset( $map[ $kind ] ) ) {
				return;
			}
			list( $table, $owner_column, $content_column ) = $map[ $kind ];
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table and column identifiers come from the fixed map above; source ownership is rechecked.
			$source = $wpdb->get_row( $wpdb->prepare( 'SELECT %i AS learner,course_id AS course,%i AS content FROM %i WHERE id=%d', $owner_column, $content_column, $table, $id ), ARRAY_A );
		}
		if ( ! $source || (int) $source['learner'] !== (int) $learner || (int) $source['course'] !== (int) $course || (int) $source['content'] !== (int) $content ) {
			return;
		}
		$scope = self::resolve( self::incoming(), $kind, $course, $content );
		$now   = gmdate( 'Y-m-d H:i:s' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Append-only origin; the unique source key prevents replacement.
		$wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (learner_user_id,source_kind,source_id,course_id,content_id,origin,assignment_id,school_id,class_id,created_at,completed_at) VALUES (%d,%s,%d,%d,%d,%s,%d,%d,%d,%s,NULL)', Schema::table( 'activity' ), $learner, $kind, $id, $course, $content, $scope['origin'], $scope['assignment_id'], $scope['school_id'], $scope['class_id'], $now ) );
		if ( $complete ) {
			self::finish( $kind, $id, $learner );
		}
	}

	/**
	 * Complete an existing origin without attributing unclassified historical attempts.
	 *
	 * @param string $kind Source kind.
	 * @param int    $id Source row ID.
	 * @param int    $learner Source owner.
	 */
	private static function finish( $kind, $id, $learner ) {
		if ( ! Schema::enabled() ) {
			return;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Source lifecycle update; origin is never changed.
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET completed_at=COALESCE(completed_at,%s) WHERE source_kind=%s AND source_id=%d AND learner_user_id=%d', Schema::table( 'activity' ), gmdate( 'Y-m-d H:i:s' ), $kind, $id, $learner ) );
	}

	/**
	 * Capture a new quiz attempt.
	 *
	 * @param array $event Verified start event.
	 */
	public static function quiz_started( array $event ) {
		self::capture( 'quiz', $event['attempt_id'], $event['student_id'], $event['course_id'], $event['quiz_id'] );
	}

	/**
	 * Mark a submitted attempt; pending review is still a submission, never a grade.
	 *
	 * @param array $event Verified submission event.
	 */
	public static function quiz_completed( array $event ) {
		self::finish( 'quiz', $event['attempt_id'], $event['student_id'] );
	}

	/**
	 * Capture a new lesson completion; quiz/assignment completion hooks are excluded.
	 *
	 * @param int $id Progress row.
	 * @param int $content Lesson ID.
	 * @param int $course Course ID.
	 * @param int $learner Learner ID.
	 */
	public static function lesson( $id, $content, $course, $learner ) {
		if ( 'ohmylms-lesson' === get_post_type( $content ) ) {
			self::capture( 'lesson', $id, $learner, $course, $content, true );
		}
	}

	/**
	 * Capture a newly created signed-in practice session; guests stay unclassified.
	 *
	 * @param array $session Source row.
	 */
	public static function practice_started( array $session ) {
		self::capture( 'practice', $session['id'], $session['student_id'], $session['course_id'], $session['term_id'] );
	}

	/**
	 * Complete a recorded practice session.
	 *
	 * @param string $uuid Session UUID.
	 */
	public static function practice_completed( $uuid ) {
		$session = \OhMyLMS\Practice\Sessions::get( $uuid );
		if ( $session && 'complete' === $session['status'] ) {
			self::finish( 'practice', $session['id'], $session['student_id'] );
		}
	}

	/**
	 * Capture a new assignment submission (not its later staff review).
	 *
	 * @param int $id Source ID.
	 * @param int $content Assignment ID.
	 * @param int $course Course ID.
	 * @param int $learner Learner ID.
	 */
	public static function assignment( $id, $content, $course, $learner ) {
		self::capture( 'assignment', $id, $learner, $course, $content, true );
	}

	/**
	 * Show an explicit choice on the existing activity page before starting work.
	 *
	 * @param int    $course Course ID.
	 * @param int    $content Activity or skill ID.
	 * @param string $kind Optional practice kind.
	 */
	public static function render( $course, $content, $kind = '' ) {
		if ( ! Schema::enabled() || ! is_user_logged_in() || \OhMyLMS\Schools\ViewAs::session() ) {
			return;
		}
		$kind = $kind ? $kind : str_replace( 'ohmylms-', '', (string) get_post_type( $content ) );
		if ( ! in_array( $kind, array( 'lesson', 'quiz', 'assignment', 'practice' ), true ) ) {
			return;
		}
		$scope = self::resolve( self::incoming(), $kind, $course, $content );
		$base  = remove_query_arg( 'oml_origin' );
		nocache_headers();
		?>
		<aside class="oml-player-origin" aria-label="<?php esc_attr_e( 'Learning context', 'ohmylms' ); ?>">
			<p><?php esc_html_e( 'Choose a context before starting. An attempt keeps the context it started with.', 'ohmylms' ); ?></p>
			<p><?php esc_html_e( 'Learning context:', 'ohmylms' ); ?> <strong><?php echo esc_html( 'unknown' === $scope['origin'] ? __( 'Not selected', 'ohmylms' ) : ( 'school' === $scope['origin'] ? __( 'School assignment', 'ohmylms' ) : __( 'Home learning', 'ohmylms' ) ) ); ?></strong></p>
			<a href="<?php echo esc_url( add_query_arg( 'oml_origin', self::token( $kind, $course, $content ), $base ) ); ?>"><?php esc_html_e( 'Home learning', 'ohmylms' ); ?></a>
			<?php foreach ( self::distributions( $course, 'practice' === $kind ? 0 : $content ) as $distribution ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'oml_origin', self::token( $kind, $course, $content, $distribution['id'] ), $base ) ); ?>"><?php echo esc_html( $distribution['title'] ); ?></a>
			<?php endforeach; ?>
		</aside>
		<?php
	}
}
