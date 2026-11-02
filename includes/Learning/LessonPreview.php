<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * "Preview as learner" for lessons.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Learning;

use OhMyLMS\Design\Ui;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Opens a lesson in the real learner template and Interactivity modules as a learner who has just
 * enrolled and completed nothing, without enrolling the author or writing progress (see
 * docs/api/lesson-authoring.md, "Learner preview").
 *
 * The link is signed for one user and session (a nonce over the lesson, the bypass flag and the expiry)
 * and expires after {@see self::lifetime()}. Only people who can edit the lesson can open it; anyone else
 * gets 403, so a learner can never use a preview link to skip a lock. While a preview is open the
 * completion and video progress endpoints refuse that user's writes for the lesson; opening the lesson
 * normally lifts the refusal.
 */
final class LessonPreview {

	const QUERY   = 'ohmylms_lesson_preview';
	const NONCE   = 'preview_nonce';
	const EXPIRES = 'preview_expires';
	const BYPASS  = 'preview_bypass';

	/** Transient prefix of "this user is previewing this lesson", read by the write endpoints. */
	const MARKER = 'ohmylms_lesson_preview_';

	/**
	 * The preview of this request: lesson, user, course, bypass and expiry.
	 *
	 * @var array{id:int,user:int,course:int,bypass:bool,expires:int}|null
	 */
	private static $context = null;

	/** Register the request handler. */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'start' ), 1 );
	}

	/**
	 * Seconds a link stays valid (default one hour, 1 minute to 1 day).
	 *
	 * @return int
	 */
	public static function lifetime() {
		/**
		 * Filters how long a lesson preview link stays valid.
		 *
		 * @param int $seconds Seconds.
		 */
		return (int) max( MINUTE_IN_SECONDS, min( DAY_IN_SECONDS, (int) apply_filters( 'ohmylms_lesson_preview_lifetime', HOUR_IN_SECONDS ) ) );
	}

	/**
	 * Nonce action of one link.
	 *
	 * @param int  $id      Lesson ID.
	 * @param bool $bypass  Locks bypassed.
	 * @param int  $expires Expiry (Unix time).
	 * @return string
	 */
	private static function action( $id, $bypass, $expires ) {
		return 'ohmylms_lesson_preview_' . (int) $id . '_' . ( $bypass ? 1 : 0 ) . '_' . (int) $expires;
	}

	/**
	 * Signed preview link for the current user.
	 *
	 * @param int  $id      Lesson ID.
	 * @param bool $bypass  Open the lesson past drip, prerequisite, sequential and video locks.
	 * @param int  $expires Expiry (Unix time); 0 for now plus the lifetime.
	 * @return string
	 */
	public static function url( $id, $bypass = false, $expires = 0 ) {
		$id      = absint( $id );
		$expires = $expires ? (int) $expires : time() + self::lifetime();
		return add_query_arg(
			array(
				self::QUERY   => $id,
				self::BYPASS  => $bypass ? 1 : 0,
				self::EXPIRES => $expires,
				self::NONCE   => wp_create_nonce( self::action( $id, $bypass, $expires ) ),
			),
			get_permalink( $id )
		);
	}

	/**
	 * Whether a user may preview a lesson: they can edit it.
	 *
	 * @param int $id      Lesson ID.
	 * @param int $user_id User; 0 for the current user.
	 * @return bool
	 */
	public static function can_preview( $id, $user_id = 0 ) {
		$id      = absint( $id );
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		$status  = $id ? get_post_status( $id ) : false;
		return $user_id > 0 && OHMYLMS_LESSON_CPT === get_post_type( $id ) && $status && ! in_array( $status, array( 'trash', 'auto-draft' ), true ) && user_can( $user_id, 'edit_post', $id );
	}

	/**
	 * Check a link for the current user.
	 *
	 * @param int    $id      Lesson ID.
	 * @param bool   $bypass  Bypass flag of the link.
	 * @param int    $expires Expiry of the link.
	 * @param string $nonce   Nonce of the link.
	 * @return true|WP_Error
	 */
	public static function verify( $id, $bypass, $expires, $nonce ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'ohmylms_lesson_preview_login', __( 'Log in to preview this lesson.', 'ohmylms' ), array( 'status' => 401 ) );
		}
		if ( ! self::can_preview( $id ) ) {
			return new WP_Error( 'ohmylms_lesson_preview_forbidden', __( 'You cannot preview this lesson.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		$expires = (int) $expires;
		if ( $expires < time() || $expires > time() + self::lifetime() + MINUTE_IN_SECONDS ) {
			return new WP_Error( 'ohmylms_lesson_preview_expired', __( 'This preview link has expired. Open the preview again from the lesson editor.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		if ( ! wp_verify_nonce( (string) $nonce, self::action( $id, $bypass, $expires ) ) ) {
			return new WP_Error( 'ohmylms_lesson_preview_invalid', __( 'This preview link is not valid. Open the preview again from the lesson editor.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Start a preview on `?ohmylms_lesson_preview=`, or lift the write refusal when the user opens the
	 * lesson normally.
	 */
	public static function start() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- The link carries its own nonce, checked in verify().
		if ( ! isset( $_GET[ self::QUERY ] ) ) {
			if ( is_user_logged_in() && is_singular( OHMYLMS_LESSON_CPT ) ) {
				delete_transient( self::marker( get_current_user_id(), get_queried_object_id() ) );
			}
			return;
		}
		$id      = absint( wp_unslash( $_GET[ self::QUERY ] ) );
		$bypass  = ! empty( $_GET[ self::BYPASS ] );
		$expires = absint( wp_unslash( $_GET[ self::EXPIRES ] ?? 0 ) );
		$nonce   = sanitize_text_field( wp_unslash( $_GET[ self::NONCE ] ?? '' ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		nocache_headers();
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		$check = is_singular( OHMYLMS_LESSON_CPT ) && get_queried_object_id() === $id ? self::verify( $id, $bypass, $expires, $nonce ) : new WP_Error( 'ohmylms_lesson_preview_not_found', __( 'Lesson not found.', 'ohmylms' ), array( 'status' => 404 ) );
		if ( is_wp_error( $check ) ) {
			wp_die( esc_html( $check->get_error_message() ), esc_html__( 'Lesson preview', 'ohmylms' ), array( 'response' => (int) $check->get_error_data()['status'] ) );
		}
		header( 'Referrer-Policy: same-origin' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		self::$context = array(
			'id'      => $id,
			'user'    => get_current_user_id(),
			'course'  => LessonGate::course( $id, get_current_user_id() ),
			'bypass'  => $bypass,
			'expires' => $expires,
		);
		set_transient( self::marker( self::$context['user'], $id ), $expires, max( MINUTE_IN_SECONDS, $expires - time() ) );
		self::hooks();
		$lock = $bypass ? null : self::gate( $id, self::$context['course'] );
		if ( $lock ) {
			self::locked_page( $lock );
		}
	}

	/**
	 * End the preview of this request (tests).
	 */
	public static function reset() {
		self::$context = null;
		remove_filter( 'ohmylms_drip_protection_message', array( __CLASS__, 'drip_message' ), 99 );
		remove_filter( 'ohmylms_is_lesson_locked', array( __CLASS__, 'locked' ), 99 );
		remove_filter( 'ohmylms_is_lesson_sequentially_locked', array( __CLASS__, 'sequentially_locked' ), 99 );
		remove_filter( 'ohmylms_video_context', array( __CLASS__, 'video_context' ), 99 );
		remove_action( 'ohmylms_before_lesson_main_content', array( __CLASS__, 'banner' ), 0 );
		remove_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		remove_filter( 'wp_robots', 'wp_robots_no_robots' );
		remove_action( 'wp_enqueue_scripts', array( Ui::class, 'enqueue' ) );
	}

	/** Learner rules and preview display for the rest of the request. */
	private static function hooks() {
		add_filter( 'ohmylms_drip_protection_message', array( __CLASS__, 'drip_message' ), 99, 4 );
		add_filter( 'ohmylms_is_lesson_locked', array( __CLASS__, 'locked' ), 99, 4 );
		add_filter( 'ohmylms_is_lesson_sequentially_locked', array( __CLASS__, 'sequentially_locked' ), 99, 4 );
		add_filter( 'ohmylms_video_context', array( __CLASS__, 'video_context' ), 99 );
		add_action( 'ohmylms_before_lesson_main_content', array( __CLASS__, 'banner' ), 0 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_filter( 'wp_robots', 'wp_robots_no_robots' );
		add_action( 'wp_enqueue_scripts', array( Ui::class, 'enqueue' ) );
	}

	/**
	 * Whether this request previews a lesson (any lesson when `$id` is 0).
	 *
	 * @param int $id Lesson ID.
	 * @return bool
	 */
	public static function active( $id = 0 ) {
		return null !== self::$context && ( ! $id || (int) $id === self::$context['id'] );
	}

	/**
	 * Whether this request's preview bypasses the locks.
	 *
	 * @return bool
	 */
	public static function bypassed() {
		return null !== self::$context && self::$context['bypass'];
	}

	/**
	 * Transient key of a user's open preview of a lesson.
	 *
	 * @param int $user_id User.
	 * @param int $id      Lesson ID.
	 * @return string
	 */
	private static function marker( $user_id, $id ) {
		return self::MARKER . (int) $user_id . '_' . (int) $id;
	}

	/**
	 * Whether the user's latest view of the lesson is an open preview, so its writes are refused.
	 *
	 * @param int $user_id User.
	 * @param int $id      Lesson ID.
	 * @return bool
	 */
	public static function previewing( $user_id, $id ) {
		if ( self::active( $id ) && self::$context['user'] === (int) $user_id ) {
			return true;
		}
		$expires = $user_id && $id ? (int) get_transient( self::marker( $user_id, $id ) ) : 0;
		return $expires >= time();
	}

	/**
	 * When content opens for a learner who enrols now.
	 *
	 * @param int $content_id Lesson, quiz or assignment.
	 * @param int $course_id  Course.
	 * @return \DateTimeImmutable|null
	 */
	public static function opens_at( $content_id, $course_id ) {
		$drip = get_post_meta( (int) $content_id, '_drip_settings', true );
		if ( ! is_array( $drip ) || empty( $drip['enable'] ) ) {
			return null;
		}
		if ( 'enrollment-from-x-days' === ( $drip['type'] ?? '' ) ) {
			$days = max( 0, (int) ( $drip['days'] ?? 0 ) );
			return $days ? ( new \DateTimeImmutable( 'now', DripSchedule::timezone() ) )->modify( "+{$days} days" ) : null;
		}
		return DripSchedule::opens_at( $drip, (int) $course_id, 0 );
	}

	/**
	 * Whether drip keeps content closed for a learner who enrols now.
	 *
	 * @param int $content_id Content.
	 * @param int $course_id  Course.
	 * @return bool
	 */
	private static function dripped( $content_id, $course_id ) {
		$at = self::opens_at( $content_id, $course_id );
		return $at && new \DateTimeImmutable( 'now', DripSchedule::timezone() ) < $at;
	}

	/**
	 * Whether the sequential mode locks content for a learner who completed nothing.
	 *
	 * @param int $content_id Content.
	 * @param int $course_id  Course.
	 * @return bool
	 */
	public static function sequential( $content_id, $course_id ) {
		$course = $course_id ? ohmylms_get_course( $course_id ) : null;
		if ( ! $course || 'yes' !== $course->get_sequential_mode() ) {
			return false;
		}
		$position = array_search( (int) $content_id, \OhMyLMS\SequentialMode::get_ordered_content_ids( $course_id ), true );
		return false !== $position && $position > 0;
	}

	/**
	 * What stops a new learner on the lesson page itself, in the order of `CommonHook::restrict_access()`:
	 * an unmet prerequisite, then the sequential lock. Drip is shown by the lesson template.
	 *
	 * @param int $id        Lesson ID.
	 * @param int $course_id Course.
	 * @return array{title:string,message:string,link:string,label:string}|null
	 */
	public static function gate( $id, $course_id ) {
		$missing = LessonGate::missing_prerequisites( $id, 0 );
		if ( $missing ) {
			$required = (array) ( ohmylms_get_lesson( $id )->get_prerequisites()['data'] ?? array() );
			$first    = absint( is_array( reset( $required ) ) ? ( reset( $required )['value'] ?? 0 ) : 0 );
			return array(
				'title'   => __( 'Access Denied', 'ohmylms' ),
				/* translators: %s: text value. */
				'message' => sprintf( __( 'You must complete the prerequisite "%s" before viewing this content.', 'ohmylms' ), $missing[0] ),
				'link'    => $first ? self::link( $first ) : '',
				'label'   => __( 'Go to Prerequisite Content', 'ohmylms' ),
			);
		}
		if ( self::sequential( $id, $course_id ) ) {
			return array(
				'title'   => __( 'Content Locked', 'ohmylms' ),
				'message' => __( 'You must complete the previous lesson before accessing this content.', 'ohmylms' ),
				'link'    => '',
				'label'   => '',
			);
		}
		return null;
	}

	/**
	 * Address of other content from the preview: its own preview for a lesson the user can edit, else its page.
	 *
	 * @param int $content_id Content.
	 * @return string
	 */
	public static function link( $content_id ) {
		$content_id = absint( $content_id );
		if ( self::can_preview( $content_id ) && null !== self::$context ) {
			return self::url( $content_id, self::$context['bypass'] );
		}
		return (string) get_permalink( $content_id );
	}

	/**
	 * Next content after a lesson, as {@see self::link()} gives it; '' on the last one.
	 *
	 * @param int $id Lesson ID.
	 * @return string
	 */
	public static function next_url( $id ) {
		$next = ohmylms_get_next_content( $id );
		return is_array( $next ) && ! empty( $next['id'] ) ? esc_url_raw( self::link( (int) $next['id'] ) ) : '';
	}

	/**
	 * Whether "Mark as complete" waits for the video, as for a learner who has not watched it.
	 *
	 * @param int $id Lesson ID.
	 * @return bool
	 */
	public static function video_pending( $id ) {
		return ! self::bypassed() && VideoCompletion::rule( $id )['required'];
	}

	/**
	 * `ohmylms_drip_protection_message` for the previewed lesson: the new learner's release, or none when bypassed.
	 *
	 * @param string $message    Message.
	 * @param int    $content_id Content.
	 * @param string $post_type  Post type.
	 * @param int    $student_id Learner.
	 * @return string
	 */
	public static function drip_message( $message, $content_id, $post_type, $student_id ) {
		if ( ! self::active( $content_id ) || (int) $student_id !== self::$context['user'] ) {
			return $message;
		}
		$at = self::bypassed() ? null : self::opens_at( $content_id, self::$context['course'] );
		if ( ! $at || new \DateTimeImmutable( 'now', DripSchedule::timezone() ) >= $at ) {
			return '';
		}
		return sprintf(
			/* translators: 1: content kind such as "assignment", 2: release date and time. */
			__( 'This %1$s will be available on %2$s.', 'ohmylms' ),
			__( 'lesson', 'ohmylms' ),
			DripSchedule::format( $at )
		);
	}

	/**
	 * `ohmylms_is_lesson_locked` for the previewing user: drip and the sequential lock of a new learner.
	 *
	 * @param bool $locked     Locked.
	 * @param int  $content_id Content.
	 * @param int  $course_id  Course.
	 * @param int  $student_id Learner.
	 * @return bool
	 */
	public static function locked( $locked, $content_id, $course_id, $student_id ) {
		if ( null === self::$context || (int) $student_id !== self::$context['user'] ) {
			return $locked;
		}
		return ! self::bypassed() && ( self::dripped( $content_id, $course_id ) || self::sequential( $content_id, $course_id ) );
	}

	/**
	 * `ohmylms_is_lesson_sequentially_locked` for the previewing user.
	 *
	 * @param bool $locked     Locked.
	 * @param int  $content_id Content.
	 * @param int  $course_id  Course.
	 * @param int  $student_id Learner.
	 * @return bool
	 */
	public static function sequentially_locked( $locked, $content_id, $course_id, $student_id ) {
		if ( null === self::$context || (int) $student_id !== self::$context['user'] ) {
			return $locked;
		}
		return ! self::bypassed() && self::sequential( $content_id, $course_id );
	}

	/**
	 * Video players of the preview start from zero and report nothing.
	 *
	 * @param array $context Player context.
	 * @return array
	 */
	public static function video_context( $context ) {
		return array_merge(
			(array) $context,
			array(
				'track'      => false,
				'resume'     => 0,
				'time'       => 0,
				'videoDone'  => false,
				'lessonDone' => false,
				'percent'    => 0,
			)
		);
	}

	/**
	 * The course outline of the player shell as a new learner's: enrolled, nothing complete, locks applied,
	 * lessons linked to their previews.
	 *
	 * @param array|\WP_Error $outline `Student\CourseOutline::get()`.
	 * @return array|\WP_Error
	 */
	public static function outline( $outline ) {
		if ( null === self::$context || ! is_array( $outline ) || ! empty( $outline['locked'] ) ) {
			return $outline;
		}
		$course              = (int) ( $outline['id'] ?? 0 );
		$outline['enrolled'] = true;
		foreach ( (array) ( $outline['units'] ?? array() ) as $index => $unit ) {
			foreach ( (array) ( $unit['items'] ?? array() ) as $position => $item ) {
				$outline['units'][ $index ]['items'][ $position ] = self::outline_item( (array) $item, $course );
			}
			$outline['units'][ $index ]['done'] = 0;
		}
		if ( ! empty( $outline['challenge'] ) && is_array( $outline['challenge'] ) ) {
			$outline['challenge'] = self::outline_item( $outline['challenge'], $course );
		}
		return $outline;
	}

	/**
	 * One outline row as a new learner's.
	 *
	 * @param array $item   Row.
	 * @param int   $course Course.
	 * @return array
	 */
	private static function outline_item( array $item, $course ) {
		$item['complete'] = false;
		$content          = (int) ( $item['content_id'] ?? 0 );
		if ( 'practice' === ( $item['kind'] ?? '' ) || ! $content ) {
			return $item;
		}
		$item['available'] = self::bypassed() || ! ( self::dripped( $content, $course ) || self::sequential( $content, $course ) );
		$item['url']       = self::link( $content );
		return $item;
	}

	/**
	 * Body class of preview pages.
	 *
	 * @param string[] $classes Classes.
	 * @return string[]
	 */
	public static function body_class( $classes ) {
		$classes[] = 'ohmylms-lesson-preview';
		return $classes;
	}

	/**
	 * Banner markup: "nothing is recorded", the lock mode and its switch.
	 *
	 * @return string
	 */
	public static function banner_html() {
		if ( null === self::$context ) {
			return '';
		}
		$id     = self::$context['id'];
		$bypass = self::$context['bypass'];
		$notes  = array(
			__( 'You see this lesson as a learner who has just enrolled and completed nothing. Progress, completion, video watching and course events are not saved.', 'ohmylms' ),
			$bypass ? __( 'Locks are bypassed: drip, prerequisites, the sequential order and the video rule do not apply.', 'ohmylms' ) : __( 'Drip, prerequisites, the sequential order and the video rule apply as they would for a learner.', 'ohmylms' ),
		);
		if ( 'publish' !== get_post_status( $id ) ) {
			$notes[] = __( 'This lesson is not published, so learners cannot open it yet.', 'ohmylms' );
		}
		$switch = Ui::button(
			$bypass ? __( 'Show locks as learners see them', 'ohmylms' ) : __( 'Bypass locks', 'ohmylms' ),
			array(
				'href' => self::url( $id, ! $bypass ),
				'size' => 'sm',
			)
		);
		return '<div class="' . esc_attr( Ui::alert_class( array( 'tone' => 'warning' ) ) . ' ohmylms-lesson-preview-banner' ) . '" role="status">'
			. '<p class="oml-alert-title">' . esc_html__( 'Preview — nothing is recorded', 'ohmylms' ) . '</p>'
			. '<div class="oml-alert-content"><p>' . esc_html( implode( ' ', $notes ) ) . '</p><p>' . $switch . '</p></div></div>';
	}

	/** Print the banner above the lesson. */
	public static function banner() {
		echo self::banner_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- banner_html() escapes every value.
	}

	/**
	 * Stop at a lock as the learner would, with the banner and the bypass switch.
	 *
	 * @param array{title:string,message:string,link:string,label:string} $lock Lock.
	 */
	private static function locked_page( array $lock ) {
		ob_start();
		Ui::enqueue();
		if ( class_exists( \OhMyLMS\Design\Tokens::class ) ) {
			wp_add_inline_style( Ui::STYLE_HANDLE, \OhMyLMS\Design\Tokens::frontend_css() );
		}
		wp_print_styles( Ui::STYLE_HANDLE );
		echo self::banner_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- banner_html() escapes every value.
		?>
		<div class="<?php echo esc_attr( Ui::alert_class( array( 'tone' => 'danger' ) ) . ' ohmylms-lesson-preview-lock' ); ?>" role="alert">
			<p class="oml-alert-title"><?php echo esc_html( $lock['title'] ); ?></p>
			<div class="oml-alert-content">
				<p><?php echo esc_html( $lock['message'] ); ?></p>
				<?php if ( $lock['link'] ) : ?>
					<p><a href="<?php echo esc_url( $lock['link'] ); ?>"><?php echo esc_html( $lock['label'] ); ?></a></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
		wp_die( ob_get_clean(), esc_html( $lock['title'] ), array( 'response' => 200 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
	}
}
