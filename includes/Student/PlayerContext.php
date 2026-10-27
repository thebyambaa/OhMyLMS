<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Context bar for the activity player: where the learner is and a clear way back to the course.
 *
 * Groups the assigned outline by lesson and links available adjacent activities for signed-in
 * learners. It changes no progress, access rules or content.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

defined( 'ABSPATH' ) || exit;

/** Exit bar on lesson, quiz and assignment pages. */
final class PlayerContext {

	/**
	 * Whether the focused shell is rendering.
	 *
	 * @var bool
	 */
	private static $active = false;

	/**
	 * Current navigation; omitted during an active quiz.
	 *
	 * @var array
	 */
	private static $navigation = array();

	/** Content types that play inside a course. */
	const TYPES = array( 'ohmylms-lesson', 'ohmylms-quiz', 'ohmylms-assignment', 'ohmylms-session' );

	/** Register the bar and its stylesheet. */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'ohmylms_before_lesson_main_content', array( __CLASS__, 'render' ), 1 );
	}

	/** Load the small stylesheet only where the bar shows. */
	public static function enqueue() {
		if ( is_singular( self::TYPES ) && is_user_logged_in() ) {
			wp_enqueue_style( 'ohmylms-player-context', plugins_url( 'assets/css/player-context.css', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/css/player-context.css' ) );
			wp_enqueue_style( 'ohmylms-learner-journey', plugins_url( 'assets/css/learner-journey.css', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/css/learner-journey.css' ) );
			wp_enqueue_script( 'ohmylms-player-context', plugins_url( 'assets/js/player-context.js', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/js/player-context.js' ), true );
		}
	}

	/**
	 * Open course navigation for authorized standalone skill practice.
	 *
	 * @param int $course_id Course ID.
	 * @param int $skill_id Skill term ID, never a content post ID.
	 * @return bool Whether the shell opened.
	 */
	public static function open_practice( $course_id, $skill_id ) {
		if ( ! is_user_logged_in() || ! $course_id || is_wp_error( \OhMyLMS\Learning\PracticeAccessPolicy::check( array( 'student_id' => get_current_user_id() ), $skill_id, $course_id ) ) ) {
			return false;
		}
		return self::open_outline( $course_id, $skill_id, 'practice' );
	}

	/**
	 * Open the same shell for a lesson in the learner's frozen course program.
	 *
	 * @param int    $course_id Course ID.
	 * @param string $placement Program placement UUID.
	 * @return bool Whether the shell opened.
	 */
	public static function open_learning( $course_id, $placement ) {
		if ( ! is_user_logged_in() || ! $placement || 'publish' !== get_post_status( $course_id ) ) {
			return false;
		}
		$state = \OhMyLMS\Learning\CompletionPolicy::status( get_current_user_id(), $course_id );
		if ( is_wp_error( $state ) ) {
			return false;
		}
		foreach ( $state['program']['items'] as $item ) {
			if ( $item['id'] === $placement && 'lesson' === $item['type'] && \OhMyLMS\Learning\LearningPath::available( $item, get_current_user_id(), $course_id ) ) {
				return self::open_outline( $course_id, $item['content_id'], 'content' );
			}
		}
		return false;
	}

	/**
	 * Render navigation after the caller's activity access check.
	 *
	 * @param int    $course_id Course ID.
	 * @param int    $current Content post or skill term ID.
	 * @param string $kind Identifier namespace.
	 * @return bool
	 */
	private static function open_outline( $course_id, $current, $kind ) {
		$outline = CourseOutline::get( $course_id, get_current_user_id() );
		if ( is_wp_error( $outline ) || $outline['locked'] ) {
			return false;
		}
		wp_enqueue_style( 'ohmylms-player-context', plugins_url( 'assets/css/player-context.css', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/css/player-context.css' ) );
		wp_enqueue_style( 'ohmylms-learner-journey', plugins_url( 'assets/css/learner-journey.css', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/css/learner-journey.css' ) );
		wp_enqueue_script( 'ohmylms-player-context', plugins_url( 'assets/js/player-context.js', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/js/player-context.js' ), true );
		self::$active     = true;
		self::$navigation = LessonJourney::activity( $outline, $current, $kind );
		ohmylms_get_template(
			'student-hub/player.php',
			array(
				'player_outline'    => $outline,
				'player_quiz'       => false,
				'player_current'    => $current,
				'player_kind'       => $kind,
				'player_navigation' => self::$navigation,
			)
		);
		return true;
	}

	/**
	 * Open the focused shell after the single-activity access gate has passed.
	 *
	 * Active quizzes retain their own exit confirmation and avoid extra navigation
	 * that could discard responses. Theme overrides can keep using the old hook.
	 *
	 * @param bool $taking_quiz Whether an existing quiz attempt is being rendered.
	 * @return bool Whether a shell was opened and must be closed.
	 */
	public static function open( $taking_quiz = false ) {
		if ( ! is_singular( self::TYPES ) || ! is_user_logged_in() ) {
			return false;
		}
		$course_id = (int) ohmylms_get_course_by_content_id( get_the_ID(), get_current_user_id() );
		$outline   = CourseOutline::get( $course_id, get_current_user_id() );
		if ( is_wp_error( $outline ) || $outline['locked'] ) {
			return false;
		}
		self::$active     = true;
		self::$navigation = $taking_quiz ? array() : LessonJourney::activity( $outline, get_the_ID(), 'content' );
		ohmylms_get_template(
			'student-hub/player.php',
			array(
				'player_outline'    => $outline,
				'player_quiz'       => (bool) $taking_quiz,
				'player_current'    => get_the_ID(),
				'player_navigation' => self::$navigation,
			)
		);
		return true;
	}

	/**
	 * Open the same shell without a course outline.
	 *
	 * Used after the access gate has passed for content that no course program places for this
	 * user, such as an author or administrator previewing an unplaced lesson.
	 *
	 * @return bool Always true; close() must follow.
	 */
	public static function open_standalone() {
		wp_enqueue_style( 'ohmylms-player-context', plugins_url( 'assets/css/player-context.css', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/css/player-context.css' ) );
		self::$active     = true;
		self::$navigation = array();
		?>
		<div class="oml-player" data-theme="light">
			<header class="oml-player-context">
				<a class="oml-player-exit" href="<?php echo esc_url( home_url( '/' ) ); ?>">&larr; <?php esc_html_e( 'Back to site', 'ohmylms' ); ?></a>
				<span class="oml-player-course"><?php echo esc_html( get_the_title() ); ?></span>
				<a class="oml-player-skip" href="#oml-player-main"><?php esc_html_e( 'Skip to activity', 'ohmylms' ); ?></a>
			</header>
			<div class="oml-player-layout">
				<main class="oml-player-main" id="oml-player-main" tabindex="-1">
		<?php
		return true;
	}

	/** Close a shell opened by open(). */
	public static function close() {
		if ( self::$active ) {
			if ( self::$navigation ) {
				ohmylms_get_template( 'student-hub/player-footer.php', array( 'player_navigation' => self::$navigation ) );
			}
			echo '</main></div></div>';
			self::$active     = false;
			self::$navigation = array();
		}
	}

	/**
	 * Whether the focused shell supplies navigation instead of the classic sidebar.
	 *
	 * @return bool
	 */
	public static function active() {
		return self::$active;
	}

	/**
	 * Use the calm shared stage in the focused shell, retaining extension designs.
	 *
	 * This is presentation only: saved quiz settings and frozen attempts are unchanged.
	 *
	 * @param string $selected Resolved presentation identifier.
	 * @return string
	 */
	public static function quiz_template( $selected ) {
		return self::$active && in_array( $selected, array( 'playful', 'classic', 'paper', 'focus' ), true ) ? 'paper' : $selected;
	}

	/**
	 * Where "back to the course" goes: the Khan-style overview when Browse subjects is on, else the course page.
	 *
	 * @param int $course_id Course ID.
	 * @return string
	 */
	public static function overview_url( $course_id ) {
		return in_array( 'library', Hub::settings()['tabs'], true ) ? Hub::course_url( $course_id ) : get_permalink( $course_id );
	}

	/** Print the bar. */
	public static function render() {
		if ( self::$active || ! is_singular( self::TYPES ) || ! is_user_logged_in() ) {
			return;
		}
		$course_id = (int) ohmylms_get_course_by_content_id( get_the_ID(), get_current_user_id() );
		if ( ! $course_id || OHMYLMS_COURSE_CPT !== get_post_type( $course_id ) ) {
			return;
		}
		\OhMyLMS\Family\Provenance::render( $course_id, get_the_ID() );
		?>
		<nav class="oml-player-context" aria-label="<?php esc_attr_e( 'Course navigation', 'ohmylms' ); ?>">
			<a class="oml-player-exit" href="<?php echo esc_url( self::overview_url( $course_id ) ); ?>">
				<span aria-hidden="true">&larr;</span> <?php esc_html_e( 'Course overview', 'ohmylms' ); ?>
			</a>
			<span class="oml-player-course"><?php echo esc_html( get_the_title( $course_id ) ); ?></span>
		</nav>
		<?php
	}
}
