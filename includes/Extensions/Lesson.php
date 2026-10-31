<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class file; see docs/CODING-STANDARDS.md.
/**
 * Lesson and assignment player on the Interactivity API (batch L6 of docs/LEARNER-FRONTEND.md): "Mark as
 * complete" (`ohmylms/lesson`), assignment start, timer, file pick and submission (`ohmylms/assignment`),
 * the lesson sidebar tabs (`ohmylms/tabs`), content protection (`ohmylms/protection`) and the sized images
 * of lesson content.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Extensions;

use OhMyLMS\Assignment\Delivery;
use OhMyLMS\Learning\VideoCompletion;

defined( 'ABSPATH' ) || exit;

/** Decorates the lesson templates, including theme overrides, and prints the player state. */
final class Lesson {

	const LESSON     = 'ohmylms/lesson';
	const ASSIGNMENT = 'ohmylms/assignment';
	const PROTECTION = 'ohmylms/protection';

	const NAVIGATION_TEMPLATE = 'single-lesson/content-navigation.php';
	const ASSIGNMENT_TEMPLATE = 'single-lesson/content-assignment.php';
	const SIDEBAR_TEMPLATE    = 'single-lesson/sidebar/sidebar.php';

	const LESSON_ROOT = array(
		'data-wp-interactive' => self::LESSON,
		'data-wp-init'        => 'callbacks.listen',
	);

	const COMPLETE_INPUT = array(
		'data-wp-on--change'     => 'actions.complete',
		'data-wp-bind--checked'  => 'context.completed',
		'data-wp-bind--disabled' => 'state.completeDisabled',
	);

	const COMPLETE_LABEL = array( 'data-wp-on--keydown' => 'actions.checkboxKey' );

	const VIDEO_HINT = array( 'data-wp-bind--hidden' => '!context.videoPending' );

	const START_BUTTON = array(
		'data-wp-on--click'       => 'ohmylms/assignment::actions.start',
		'data-wp-bind--hidden'    => 'ohmylms/assignment::state.started',
		'data-wp-bind--aria-busy' => 'ohmylms/assignment::state.busy',
	);

	const SKIP_LINK = array( 'data-wp-bind--hidden' => 'ohmylms/assignment::state.started' );

	const ASSIGNMENT_ROOT = array(
		'data-wp-interactive' => self::ASSIGNMENT,
		'data-wp-init'        => 'callbacks.mount',
	);

	const SUBMIT_PANEL = array( 'data-wp-style--display' => 'state.panelDisplay' );

	const TIMER = array(
		'data-wp-style--display' => 'state.timerDisplay',
		'role'                   => 'timer',
	);

	const TIMER_TEXT = array( 'data-wp-text' => 'state.timeLabel' );

	const TIMER_BAR = array( 'data-wp-style--width' => 'state.timerWidth' );

	const FORM = array(
		'data-wp-on--submit'      => 'actions.submit',
		'data-wp-bind--aria-busy' => 'state.busy',
	);

	const FILE_INPUT = array(
		'data-wp-on--change'     => 'actions.pick',
		'data-wp-bind--disabled' => 'state.locked',
	);

	const FILE_LABEL = array( 'data-wp-on--keydown' => 'actions.pickKey' );

	const ATTACHED = array( 'data-wp-style--display' => 'state.attachedDisplay' );

	const SIZE_ALERT = array(
		'data-wp-style--display' => 'state.sizeAlertDisplay',
		'role'                   => 'alert',
	);

	const ERROR = array(
		'data-wp-style--display' => 'state.errorDisplay',
		'role'                   => 'alert',
	);

	const ERROR_TEXT = array( 'data-wp-text' => 'state.error' );

	const LOCKED_FIELD = array( 'data-wp-bind--disabled' => 'state.locked' );

	const SUBMIT_BUTTON = array( 'data-wp-bind--disabled' => 'state.submitDisabled' );

	const TAB = array(
		'data-wp-on--click'           => 'actions.select',
		'data-wp-on--keydown'         => 'actions.key',
		'data-wp-class--active'       => 'state.selected',
		'data-wp-bind--aria-selected' => 'state.selected',
		'data-wp-bind--tabindex'      => 'state.tabIndex',
		'role'                        => 'tab',
	);

	const TAB_PANEL = array(
		'data-wp-class--active' => 'state.selected',
		'role'                  => 'tabpanel',
	);

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'ohmylms_template_html', array( __CLASS__, 'template' ), 17, 2 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'wp_footer', array( __CLASS__, 'protection' ), 4 );
	}

	/**
	 * Decorate a rendered lesson template.
	 *
	 * @param string $html     Template HTML.
	 * @param string $template Template name.
	 * @return string
	 */
	public static function template( $html, $template ) {
		if ( ! is_string( $html ) || '' === $html || 0 !== strpos( (string) $template, 'single-lesson/' ) ) {
			return $html;
		}
		if ( self::NAVIGATION_TEMPLATE === $template ) {
			$html = self::navigation( $html );
		} elseif ( self::ASSIGNMENT_TEMPLATE === $template ) {
			$html = self::assignment( $html );
		} elseif ( self::SIDEBAR_TEMPLATE === $template ) {
			$html = self::sidebar( $html );
		}
		return 0 === strpos( (string) $template, 'single-lesson/content-' ) ? self::images( $html ) : $html;
	}

	/**
	 * Context of `.ohmylms-lesson-navigation` for the current learner.
	 *
	 * @param int $id Lesson, quiz, assignment or session.
	 * @return array{lessonId:int,completed:bool,busy:bool,videoPending:bool,nextUrl:string}
	 */
	public static function navigation_context( $id ) {
		$id        = (int) $id;
		$user      = get_current_user_id();
		$completed = $user > 0 && Delivery::completed( $user, $id ) && ! \OhMyLMS\Quiz\Submission::is_preview( $id, $user );
		return array(
			'lessonId'     => $id,
			'completed'    => $completed,
			'busy'         => false,
			'videoPending' => ! $completed && OHMYLMS_LESSON_CPT === get_post_type( $id ) && ! VideoCompletion::satisfied( $user, $id ),
			'nextUrl'      => esc_url_raw( (string) ohmylms_get_next_content_permalink( $id ) ),
		);
	}

	/**
	 * Load `ohmylms/lesson` with its nonce and messages.
	 */
	public static function enqueue_lesson() {
		Interactivity::enqueue( self::LESSON );
		Chrome::enqueue_ui();
		if ( Interactivity::once( 'lesson-state' ) ) {
			wp_interactivity_state(
				self::LESSON,
				array(
					'nonces'   => array( 'complete' => wp_create_nonce( 'lesson_completed_nonce' ) ),
					'messages' => array(
						'markComplete' => __( 'Mark as Complete', 'ohmylms' ),
						'completed'    => __( 'Completed', 'ohmylms' ),
						'failed'       => __( 'Lesson completion failed.', 'ohmylms' ),
					),
				)
			);
		}
	}

	/**
	 * Server values of `ohmylms/assignment` for the current learner. The deadline comes from the server
	 * only; `serverNow` lets the module correct the device clock.
	 *
	 * @param int $id Assignment ID.
	 * @return array
	 */
	public static function assignment_state( $id ) {
		$id         = (int) $id;
		$user       = get_current_user_id();
		$assignment = ohmylms_get_assignment( $id );
		$timer      = Delivery::timer( $id, $user );
		$limit_mb   = $assignment ? (float) $assignment->get_max_file_size_limit() : 0;
		$max_bytes  = (int) wp_max_upload_size();
		if ( $assignment && ! empty( $assignment->get_enable_file_size_limit() ) && $limit_mb > 0 ) {
			$max_bytes = (int) min( $max_bytes, $limit_mb * MB_IN_BYTES );
		}
		return array(
			'assignmentId'  => $id,
			'timeLimit'     => $timer['limit'],
			'deadline'      => $timer['deadline'] ? strtotime( $timer['deadline'] ) * 1000 : 0,
			'deadlineLabel' => $timer['deadline_label'],
			'remaining'     => $timer['remaining'],
			'serverNow'     => (int) round( microtime( true ) * 1000 ),
			'maxBytes'      => $max_bytes,
			'started'       => false,
			'expired'       => false,
			'locked'        => false,
			'busy'          => false,
			'error'         => '',
			'files'         => array(),
			'tooLarge'      => false,
			'nonces'        => array(
				'submit' => wp_create_nonce( 'assignment_submission_nonce' ),
				'start'  => wp_create_nonce( 'save_remaining_time' ),
			),
			'messages'      => array(
				'submitFailed' => __( 'An error occurred. Please try again.', 'ohmylms' ),
				/* translators: %s: file name. */
				'selected'     => __( 'Selected file: %s', 'ohmylms' ),
				'removed'      => __( 'The file was removed.', 'ohmylms' ),
				'remove'       => __( 'Remove', 'ohmylms' ),
				'timeUp'       => __( 'Time is up. This assignment can no longer be submitted.', 'ohmylms' ),
				'autoSubmit'   => __( 'Time is up. Your file is being submitted.', 'ohmylms' ),
				'startFailed'  => __( 'The assignment could not be started. Please try again.', 'ohmylms' ),
			),
		);
	}

	/**
	 * Load `ohmylms/assignment` and its state for an assignment page.
	 *
	 * @param int $id Assignment ID.
	 */
	public static function enqueue_assignment( $id ) {
		Interactivity::enqueue( self::ASSIGNMENT );
		Chrome::enqueue_ui();
		if ( Interactivity::once( 'assignment-state' ) ) {
			wp_interactivity_state( self::ASSIGNMENT, self::assignment_state( $id ) );
		}
	}

	/**
	 * Add `ohmylms/lesson` directives (and the assignment start controls) to `content-navigation.php`.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function navigation( $html ) {
		if ( false === strpos( $html, 'ohmylms-lesson-navigation' ) ) {
			return $html;
		}
		$tags       = new \WP_HTML_Tag_Processor( $html );
		$found      = false;
		$assignment = false;
		$id         = (int) get_the_ID();
		while ( $tags->next_tag() ) {
			$tag = $tags->get_tag();
			if ( $tags->has_class( 'ohmylms-lesson-navigation' ) ) {
				$found = true;
				if ( null === $tags->get_attribute( 'data-wp-interactive' ) ) {
					self::set( $tags, self::LESSON_ROOT + array( 'data-wp-context' => wp_json_encode( self::navigation_context( $id ) ) ) );
				}
			} elseif ( ! $found ) {
				continue;
			} elseif ( $tags->has_class( 'assignment-navigation' ) ) {
				$assignment = true;
			} elseif ( 'INPUT' === $tag && 'ohmylms-completed-lesson' === $tags->get_attribute( 'id' ) ) {
				self::add( $tags, self::COMPLETE_INPUT );
			} elseif ( 'LABEL' === $tag && $tags->has_class( 'ohmylms-checkbox' ) ) {
				self::add( $tags, self::COMPLETE_LABEL );
			} elseif ( 'ohmylms-video-completion-hint' === $tags->get_attribute( 'id' ) ) {
				self::add( $tags, self::VIDEO_HINT );
			} elseif ( $tags->has_class( 'start-submit-assignment' ) ) {
				$assignment = true;
				self::add( $tags, self::START_BUTTON );
			} elseif ( $assignment && 'A' === $tag && $tags->has_class( 'skip' ) ) {
				self::add( $tags, self::SKIP_LINK );
			}
		}
		if ( $found ) {
			self::enqueue_lesson();
		}
		if ( $assignment && OHMYLMS_ASSIGNMENT_CPT === get_post_type( $id ) ) {
			self::enqueue_assignment( $id );
		}
		return $tags->get_updated_html();
	}

	/**
	 * Add `ohmylms/assignment` directives to `content-assignment.php`. Theme overrides copied before L6 have
	 * no file list template: their picker stays visible, so the browser shows the chosen file.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function assignment( $html ) {
		if ( false === strpos( $html, 'content-type-assignment' ) ) {
			return $html;
		}
		$tags     = new \WP_HTML_Tag_Processor( $html );
		$found    = false;
		$in_error = false;
		while ( $tags->next_tag() ) {
			$tag = $tags->get_tag();
			if ( $tags->has_class( 'content-type-assignment' ) ) {
				$found = true;
				self::root( $tags, self::ASSIGNMENT_ROOT );
				continue;
			}
			if ( ! $found ) {
				continue;
			}
			if ( $in_error && 'SPAN' === $tag ) {
				self::add( $tags, self::ERROR_TEXT );
				$in_error = false;
			} elseif ( $tags->has_class( 'ohmylms-assignment-submit' ) ) {
				self::add( $tags, self::SUBMIT_PANEL );
			} elseif ( $tags->has_class( 'submission-time-limit' ) ) {
				self::add( $tags, self::TIMER );
			} elseif ( $tags->has_class( 'timer-display' ) ) {
				self::add( $tags, self::TIMER_TEXT );
			} elseif ( $tags->has_class( 'progress-inner' ) ) {
				self::add( $tags, self::TIMER_BAR );
			} elseif ( 'FORM' === $tag && $tags->has_class( 'ohmylms-assignment-form' ) ) {
				self::add( $tags, self::FORM );
			} elseif ( 'INPUT' === $tag && 'file' === $tags->get_attribute( 'type' ) ) {
				self::add( $tags, self::FILE_INPUT );
			} elseif ( 'LABEL' === $tag && $tags->has_class( 'ohmylms-file-upload' ) ) {
				self::add( $tags, self::FILE_LABEL );
			} elseif ( $tags->has_class( 'attached-resources' ) ) {
				self::add( $tags, self::ATTACHED );
			} elseif ( $tags->has_class( 'submission-max-file-limit-alert' ) ) {
				self::add( $tags, self::SIZE_ALERT );
			} elseif ( $tags->has_class( 'assignment-submit-alert' ) ) {
				$in_error = self::add( $tags, self::ERROR );
			} elseif ( 'TEXTAREA' === $tag && 'submission-body' === $tags->get_attribute( 'name' ) ) {
				self::add( $tags, self::LOCKED_FIELD );
			} elseif ( 'BUTTON' === $tag && 'submit' === $tags->get_attribute( 'type' ) ) {
				self::add( $tags, self::SUBMIT_BUTTON );
			}
		}
		if ( $found ) {
			self::enqueue_assignment( (int) get_the_ID() );
		}
		return $tags->get_updated_html();
	}

	/**
	 * Drive the lesson sidebar tabs (`li[data-target]` in `.ohmylms-lesson-tab-nav`) with `ohmylms/tabs`.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function sidebar( $html ) {
		if ( false === strpos( $html, 'ohmylms-lesson-tab-nav' ) ) {
			return $html;
		}
		$keys   = array();
		$active = '';
		$scan   = new \WP_HTML_Tag_Processor( $html );
		$in_nav = false;
		while ( $scan->next_tag() ) {
			if ( $scan->has_class( 'ohmylms-lesson-tab-nav' ) ) {
				$in_nav = true;
			} elseif ( $in_nav && 'LI' === $scan->get_tag() ) {
				$target = (string) $scan->get_attribute( 'data-target' );
				if ( 0 === strpos( $target, '#' ) && strlen( $target ) > 1 ) {
					$keys[] = substr( $target, 1 );
					if ( '' === $active && $scan->has_class( 'active' ) ) {
						$active = substr( $target, 1 );
					}
				}
			} elseif ( $in_nav && $scan->has_class( 'ohmylms-lesson-tab-content' ) ) {
				break;
			}
		}
		if ( ! $keys ) {
			return $html;
		}
		$active = $active ? $active : $keys[0];
		$tags   = new \WP_HTML_Tag_Processor( $html );
		$found  = false;
		while ( $tags->next_tag() ) {
			$id = (string) $tags->get_attribute( 'id' );
			if ( $tags->has_class( 'ohmylms-lesson-sidebar' ) ) {
				$found = true;
				self::root(
					$tags,
					array(
						'data-wp-interactive' => 'ohmylms/tabs',
						'data-wp-context'     => wp_json_encode( array( 'activeTab' => $active ) ),
					)
				);
			} elseif ( ! $found ) {
				continue;
			} elseif ( $tags->has_class( 'ohmylms-lesson-tab-nav' ) && null === $tags->get_attribute( 'role' ) ) {
				$tags->set_attribute( 'role', 'tablist' );
			} elseif ( 'LI' === $tags->get_tag() && in_array( substr( (string) $tags->get_attribute( 'data-target' ), 1 ), $keys, true ) ) {
				$key = substr( (string) $tags->get_attribute( 'data-target' ), 1 );
				if ( self::add( $tags, self::TAB + array( 'data-wp-context' => wp_json_encode( array( 'tabKey' => $key ) ) ) ) ) {
					$tags->set_attribute( 'aria-controls', $key );
					$tags->set_attribute( 'aria-selected', $key === $active ? 'true' : 'false' );
					$tags->set_attribute( 'tabindex', $key === $active ? '0' : '-1' );
				}
			} elseif ( $tags->has_class( 'ohmylms-lesson-single-tab-content' ) && in_array( $id, $keys, true ) ) {
				self::add( $tags, self::TAB_PANEL + array( 'data-wp-context' => wp_json_encode( array( 'tabKey' => $id ) ) ) );
			}
		}
		if ( $found ) {
			Interactivity::enqueue( 'ohmylms/tabs' );
		}
		return $tags->get_updated_html();
	}

	/**
	 * Size and align images of lesson content that carry `data-width` and `data-align` (written by the
	 * content editor), on the server instead of in the browser.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function images( $html ) {
		if ( false === strpos( $html, 'data-align' ) || false === strpos( $html, 'data-width' ) ) {
			return $html;
		}
		$tags = new \WP_HTML_Tag_Processor( $html );
		while ( $tags->next_tag( 'IMG' ) ) {
			$width = trim( (string) $tags->get_attribute( 'data-width' ) );
			$align = (string) $tags->get_attribute( 'data-align' );
			if ( '' === $width || ! in_array( $align, array( 'left', 'center', 'right' ), true ) || ! preg_match( '/^\d{1,4}(?:\.\d+)?(?:%|px)?$/D', $width ) ) {
				continue;
			}
			$width = is_numeric( $width ) ? $width . '%' : $width;
			$style = trim( (string) $tags->get_attribute( 'style' ) );
			$tags->set_attribute(
				'style',
				( '' === $style ? '' : rtrim( $style, '; ' ) . ';' ) . sprintf(
					'display:block;width:%1$s !important;margin-left:%2$s;margin-right:%3$s',
					$width,
					'left' === $align ? '0' : 'auto',
					'right' === $align ? '0' : 'auto'
				)
			);
		}
		return $tags->get_updated_html();
	}

	/**
	 * Whether content protection runs on this page: the option is on and this is a lesson, quiz,
	 * assignment or session page.
	 *
	 * @return bool
	 */
	public static function protected_page() {
		return 'yes' === get_option( 'ohmylms_content_protection', 'no' ) && function_exists( 'ohmylms_is_content_page' ) && ohmylms_is_content_page();
	}

	/**
	 * Mark protected pages, so `@media print` prints nothing even without JavaScript.
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public static function body_class( $classes ) {
		if ( self::protected_page() ) {
			$classes[] = 'ohmylms-content-protected';
		}
		return $classes;
	}

	/**
	 * Print the screenshot overlay, which loads `ohmylms/protection`, on protected pages only.
	 */
	public static function protection() {
		if ( ! self::protected_page() || ! Interactivity::once( 'protection' ) ) {
			return;
		}
		Interactivity::enqueue( self::PROTECTION );
		echo '<div class="ohmylms-screenshot-protection" aria-hidden="true" data-wp-interactive="' . esc_attr( self::PROTECTION ) . '" data-wp-init="callbacks.install"></div>';
	}

	/**
	 * Decorate a store root unless it already belongs to a store.
	 *
	 * @param \WP_HTML_Tag_Processor $tags       Processor.
	 * @param array                  $attributes Attributes.
	 */
	private static function root( $tags, array $attributes ) {
		if ( null === $tags->get_attribute( 'data-wp-interactive' ) ) {
			self::set( $tags, $attributes );
		}
	}

	/**
	 * Add attributes unless the element already has the first directive of the set.
	 *
	 * @param \WP_HTML_Tag_Processor $tags       Processor.
	 * @param array                  $attributes Attributes; the first `data-wp-*` key is the marker.
	 * @return bool Whether the attributes were added.
	 */
	private static function add( $tags, array $attributes ) {
		foreach ( array_keys( $attributes ) as $name ) {
			if ( 0 === strpos( $name, 'data-wp-' ) && 'data-wp-interactive' !== $name ) {
				if ( null !== $tags->get_attribute( $name ) ) {
					return false;
				}
				break;
			}
		}
		self::set( $tags, $attributes );
		return true;
	}

	/**
	 * Set attributes.
	 *
	 * @param \WP_HTML_Tag_Processor $tags       Processor.
	 * @param array                  $attributes Attributes.
	 */
	private static function set( $tags, array $attributes ) {
		foreach ( $attributes as $name => $value ) {
			$tags->set_attribute( $name, $value );
		}
	}
}
