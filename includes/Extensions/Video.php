<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class file; see docs/CODING-STANDARDS.md.
/**
 * Lesson and session replay videos on the Interactivity API (batch L7 of docs/LEARNER-FRONTEND.md):
 * the `ohmylms/video` store, one provider adapter module per platform, watch tracking and the
 * markup of every player, including the decoration of theme overrides.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Extensions;

use OhMyLMS\Learning\VideoCompletion;
use OhMyLMS\Learning\VideoSource;
use OhMyLMS\VideoProgress\VideoProgressTracker;

defined( 'ABSPATH' ) || exit;

/** Renders video players with `ohmylms/video` directives and decorates players of theme overrides. */
final class Video {

	const STORE = 'ohmylms/video';

	/** Adapter modules (`ohmylms/video-<name>`, assets/interactivity/video/<name>.js), imported on demand. */
	const ADAPTERS = array( 'html5', 'youtube', 'vimeo', 'playerjs', 'wistia' );

	/** Playback speeds of the custom controls (assets/interactivity/video/progress.js RATES). */
	const RATES = array( 0.5, 0.75, 1, 1.25, 1.5, 2 );

	/** Milliseconds between progress saves while playing. */
	const SAVE_INTERVAL = 15000;

	const IFRAME_ALLOW = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';

	/**
	 * Lessons whose video is tracked in this request (one player per lesson reports progress).
	 *
	 * @var array<int, bool>
	 */
	private static $tracked = array();

	/**
	 * Players rendered in this request, for unique ids.
	 *
	 * @var int
	 */
	private static $players = 0;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'ohmylms_register_interactivity_modules', array( __CLASS__, 'register' ) );
		add_filter( 'ohmylms_interactivity_module_dependencies', array( __CLASS__, 'dependencies' ), 10, 2 );
		add_filter( 'ohmylms_template_html', array( __CLASS__, 'template' ), 15, 2 );
		add_action( 'ohmylms_lesson_completed', array( __CLASS__, 'lesson_completed' ), 10, 3 );
	}

	/**
	 * Register the adapter modules.
	 */
	public static function register() {
		foreach ( self::ADAPTERS as $adapter ) {
			$path = 'assets/interactivity/video/' . $adapter . '.js';
			wp_register_script_module( 'ohmylms/video-' . $adapter, plugins_url( $path, OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/' . $path ) );
		}
	}

	/**
	 * The adapters are dynamic dependencies of `ohmylms/video`: in the import map, fetched only when a
	 * player of that provider is on the page.
	 *
	 * @param array  $deps   Dependencies.
	 * @param string $module Module ID.
	 * @return array
	 */
	public static function dependencies( $deps, $module ) {
		if ( self::STORE === $module ) {
			foreach ( self::ADAPTERS as $adapter ) {
				$deps[] = array(
					'id'     => 'ohmylms/video-' . $adapter,
					'import' => 'dynamic',
				);
			}
		}
		return $deps;
	}

	/**
	 * `ohmylms_video_lesson_completed` used to fire when the tracker completed the lesson itself; the lesson is
	 * now completed through `ohmylms_lesson_completed`, so it fires there when the learner's video counts as watched.
	 *
	 * @param int $lesson_id  Lesson ID.
	 * @param int $course_id  Course ID.
	 * @param int $student_id Learner.
	 */
	public static function lesson_completed( $lesson_id, $course_id, $student_id ) {
		if ( OHMYLMS_LESSON_CPT !== get_post_type( (int) $lesson_id ) ) {
			return;
		}
		$progress = ( new VideoProgressTracker() )->get_progress( (int) $student_id, (int) $lesson_id );
		if ( $progress && $progress->is_completed ) {
			do_action( 'ohmylms_video_lesson_completed', (int) $lesson_id, (int) $course_id, (int) $student_id, $progress );
		}
	}

	/**
	 * Print a player.
	 *
	 * @param array $args See {@see self::player()}.
	 */
	public static function render( array $args ) {
		echo self::player( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- self::player() escapes every value.
	}

	/**
	 * Markup of a player.
	 *
	 * @param array $args {
	 *     Player arguments.
	 *
	 *     @type string    $url             Video URL (uploaded file, provider page or embed URL).
	 *     @type int       $lesson_id       Lesson whose progress the player reports (0: none).
	 *     @type int       $course_id       Course (derived from the lesson when 0).
	 *     @type bool      $track           Report watch progress (logged-in, enrolled learners only).
	 *     @type bool|null $custom          Custom controls; null follows `ohmylms_use_custom_video_player`.
	 *     @type bool      $autoplay        Start muted on load (not with reduced motion).
	 *     @type bool      $loop            Loop.
	 *     @type bool      $controls        Provider/native controls when the custom player is off.
	 *     @type string    $poster          Poster URL.
	 *     @type string    $poster_alt      Poster text alternative (cover of the plain video).
	 *     @type string    $aspect_ratio    `n/n`.
	 *     @type string    $title           Video title (accessible names).
	 *     @type bool      $remove_branding Vimeo title, byline and portrait off (provider controls).
	 *     @type string    $logo            Logo replacing the big play button (custom player); null reads the option.
	 * }
	 * @return string HTML, '' when the URL cannot be played.
	 */
	public static function player( array $args ) {
		$args   = wp_parse_args(
			$args,
			array(
				'url'             => '',
				'lesson_id'       => 0,
				'course_id'       => 0,
				'track'           => false,
				'custom'          => null,
				'autoplay'        => false,
				'loop'            => false,
				'controls'        => true,
				'poster'          => '',
				'poster_alt'      => '',
				'aspect_ratio'    => '16/9',
				'title'           => '',
				'remove_branding' => false,
				'logo'            => null,
			)
		);
		$custom = null === $args['custom'] ? 'yes' === get_option( 'ohmylms_use_custom_video_player', 'yes' ) : (bool) $args['custom'];
		$source = VideoSource::parse( (string) $args['url'], $custom );
		if ( '' === $source['provider'] ) {
			return '';
		}
		$args['aspect_ratio'] = preg_match( '#^\d{1,2}/\d{1,2}$#D', (string) $args['aspect_ratio'] ) ? (string) $args['aspect_ratio'] : '16/9';
		$args['logo']         = null === $args['logo'] ? (string) get_option( 'ohmylms_video_player_logo', '' ) : (string) $args['logo'];
		$custom               = $custom && in_array( $source['provider'], VideoSource::CUSTOM, true );
		$context              = self::context( $args, $source, $custom );
		self::enqueue( $context['track'] );
		if ( $custom ) {
			return self::custom_markup( $args, $source, $context );
		}
		return 'html5' === $source['provider'] ? self::native_markup( $args, $source, $context ) : self::iframe_markup( $args, $source, $context );
	}

	/**
	 * Context of a player: what it plays, what the server knows of the learner's watching, and the
	 * initial UI values.
	 *
	 * @param array $args   Normalized {@see self::player()} arguments.
	 * @param array $source {@see VideoSource::parse()}.
	 * @param bool  $custom Custom controls.
	 * @return array
	 */
	public static function context( array $args, array $source, $custom ) {
		$lesson  = absint( $args['lesson_id'] );
		$user    = get_current_user_id();
		$course  = absint( $args['course_id'] );
		$tracker = new VideoProgressTracker();
		$track   = $args['track'] && $lesson && $user && in_array( $source['provider'], VideoSource::TRACKABLE, true ) && empty( self::$tracked[ $lesson ] );
		if ( $track ) {
			$course = $course ? $course : (int) ohmylms_get_course_by_content_id( $lesson );
			$track  = $course > 0 && $tracker->is_user_enrolled( $user, $course );
		}
		$progress = null;
		if ( $track ) {
			self::$tracked[ $lesson ] = true;
			$progress                 = $tracker->get_progress( $user, $lesson );
		}
		$duration = $lesson ? (float) VideoCompletion::total( $lesson, 0 ) : 0.0;
		$done     = $progress && (int) $progress->is_completed;
		$resume   = $progress && ! $done ? (float) $progress->last_position : 0.0;
		if ( $resume <= 1 || ( $duration > 0 && $resume >= $duration - 3 ) ) {
			$resume = 0.0;
		}
		$site    = max( 1, min( 100, (int) get_option( 'ohmylms_video_completion_threshold', 90 ) ) );
		$context = array(
			'provider'      => $source['provider'],
			'videoId'       => $source['id'],
			'hash'          => $source['hash'],
			'custom'        => (bool) $custom,
			'lessonId'      => $lesson,
			'courseId'      => $course,
			'track'         => (bool) $track,
			'resume'        => round( $resume, 2 ),
			'videoDone'     => (bool) $done,
			'lessonDone'    => $track && \OhMyLMS\Assignment\Delivery::completed( $user, $lesson ),
			'percent'       => $progress ? round( (float) $progress->watch_percentage, 2 ) : 0,
			'threshold'     => $lesson ? (float) VideoCompletion::threshold( $lesson, apply_filters( 'ohmylms_video_completion_threshold', $site ) ) : 0,
			'autoplay'      => (bool) $args['autoplay'],
			'loop'          => (bool) $args['loop'],
			'muted'         => (bool) $args['autoplay'],
			'title'         => wp_strip_all_tags( (string) $args['title'] ),
			'mounted'       => false,
			'playing'       => false,
			'started'       => false,
			'time'          => round( $resume, 2 ),
			'duration'      => $duration,
			'volume'        => 1,
			'rate'          => 1,
			'rates'         => true,
			'captions'      => false,
			'captionsOn'    => false,
			'volumeControl' => true,
			'speedOpen'     => false,
			'fullscreen'    => false,
			'idle'          => false,
			'seeking'       => false,
			'volumeDrag'    => false,
			'error'         => '',
			'indicator'     => '',
		);
		/**
		 * Filters the context of a video player. Add-on values go under their own key.
		 *
		 * @param array $context Context.
		 * @param array $args    Player arguments.
		 */
		return (array) apply_filters( 'ohmylms_video_context', $context, $args );
	}

	/**
	 * Load `ohmylms/video` and its state; tracking players add the progress and completion nonces.
	 *
	 * @param bool $track A player reports progress.
	 */
	public static function enqueue( $track = false ) {
		Interactivity::enqueue( self::STORE );
		$state = array();
		if ( Interactivity::once( 'video-state' ) ) {
			$state['ajaxUrl']      = admin_url( 'admin-ajax.php' );
			$state['saveInterval'] = self::SAVE_INTERVAL;
			$state['messages']     = self::messages();
		}
		if ( $track && Interactivity::once( 'video-track-state' ) ) {
			$state['nonces'] = array(
				'progress' => wp_create_nonce( 'video_progress_nonce' ),
				'complete' => wp_create_nonce( 'lesson_completed_nonce' ),
			);
			Chrome::enqueue_ui();
		}
		if ( $state ) {
			wp_interactivity_state( self::STORE, $state );
		}
	}

	/**
	 * Translated messages of the store.
	 *
	 * @return array
	 */
	public static function messages() {
		return array(
			'play'            => __( 'Play', 'ohmylms' ),
			'pause'           => __( 'Pause', 'ohmylms' ),
			'mute'            => __( 'Mute', 'ohmylms' ),
			'unmute'          => __( 'Unmute', 'ohmylms' ),
			'fullscreen'      => __( 'Full screen', 'ohmylms' ),
			'exitFullscreen'  => __( 'Exit full screen', 'ohmylms' ),
			/* translators: 1: current time, 2: video length, e.g. "1:05 of 10:00". */
			'timeText'        => __( '%1$s of %2$s', 'ohmylms' ),
			/* translators: %d: seconds. */
			'forward'         => __( '%d seconds forward', 'ohmylms' ),
			/* translators: %d: seconds. */
			'back'            => __( '%d seconds back', 'ohmylms' ),
			'loadError'       => __( 'Unable to load video. Please try again later.', 'ohmylms' ),
			'videoInvalid'    => __( 'This video link is not valid.', 'ohmylms' ),
			'videoMissing'    => __( 'This video was removed or is private.', 'ohmylms' ),
			'embedBlocked'    => __( 'The owner of this video does not allow it to be played on other sites.', 'ohmylms' ),
			'lessonCompleted' => __( 'Lesson completed.', 'ohmylms' ),
		);
	}

	/**
	 * Iframe URL of a provider player (custom player off), with the lesson's settings.
	 *
	 * @param array $source {@see VideoSource::parse()}.
	 * @param array $args   Normalized player arguments.
	 * @return string
	 */
	public static function embed_url( array $source, array $args ) {
		switch ( $source['provider'] ) {
			case 'youtube':
				$params = array();
				if ( $args['loop'] ) {
					$params['loop']     = 1;
					$params['playlist'] = $source['id'];
				}
				// Autoplay is started by the module (muted, and not with reduced motion), not by the URL.
				$params += array(
					'modestbranding' => 1,
					'showinfo'       => 0,
					'rel'            => 0,
					'enablejsapi'    => 1,
					'origin'         => untrailingslashit( home_url() ),
					'controls'       => $args['controls'] ? 1 : 0,
				);
				return add_query_arg( $params, $source['embed'] );
			case 'vimeo':
				$params = array(
					'autoplay' => 0,
					'muted'    => $args['autoplay'] ? 1 : 0,
					'controls' => $args['controls'] ? 1 : 0,
					'dnt'      => 1,
				);
				if ( $args['remove_branding'] ) {
					$params += array(
						'title'    => 0,
						'byline'   => 0,
						'portrait' => 0,
					);
				}
				if ( $args['loop'] ) {
					$params['loop'] = 1;
				}
				if ( $source['hash'] ) {
					$params['h'] = $source['hash'];
				}
				return add_query_arg( $params, $source['embed'] );
			case 'bunny':
				return add_query_arg(
					array(
						'autoplay'   => 'false',
						'preload'    => 'true',
						'responsive' => 'true',
						'loop'       => $args['loop'] ? 'true' : 'false',
					),
					$source['embed']
				);
			default:
				return $source['embed'];
		}
	}

	/**
	 * Decorate players of templates that predate the module (theme overrides): an empty
	 * `.ohmylms-custom-video-player[data-video-url]` becomes the full player; a plain
	 * `.ohmylms-video-player` (`video.the-video` and its poster cover) and an
	 * `.ohmylms-lesson-external-video` iframe get the island directives. Players that already have
	 * them (the current templates) are left as they are.
	 *
	 * @param string $html     Template HTML.
	 * @param string $template Template name.
	 * @return string
	 */
	public static function template( $html, $template ) {
		if ( ! is_string( $html ) || '' === $html || 0 === strpos( (string) $template, 'emails/' ) ) {
			return $html;
		}
		if ( false === strpos( $html, 'ohmylms-custom-video-player' ) && false === strpos( $html, 'ohmylms-video-player' ) && false === strpos( $html, 'ohmylms-lesson-external-video' ) ) {
			return $html;
		}
		$lesson = 'single-lesson/content-lesson.php' === $template && OHMYLMS_LESSON_CPT === get_post_type() ? (int) get_the_ID() : 0;
		$html   = (string) preg_replace_callback(
			'#<div\b(?=[^>]*\bohmylms-custom-video-player\b)[^>]*>\s*(?:<!--.*?-->\s*)*</div>#s',
			static function ( $found ) use ( $lesson ) {
				return self::decorate_custom( $found[0], $lesson );
			},
			$html
		);
		return self::decorate_plain( $html, $lesson );
	}

	/**
	 * Full player for an empty custom-player placeholder of an override.
	 *
	 * @param string $placeholder Placeholder `<div>`.
	 * @param int    $lesson      Lesson to track (0: none).
	 * @return string
	 */
	private static function decorate_custom( $placeholder, $lesson ) {
		$tags = new \WP_HTML_Tag_Processor( $placeholder );
		if ( ! $tags->next_tag() || null !== $tags->get_attribute( 'data-wp-interactive' ) || ! $tags->get_attribute( 'data-video-url' ) ) {
			return $placeholder;
		}
		$flag  = static function ( $name ) use ( $tags ) {
			return 'true' === (string) $tags->get_attribute( $name );
		};
		$ratio = preg_match( '#aspect-ratio:\s*(\d{1,2}/\d{1,2})#', (string) $tags->get_attribute( 'style' ), $found ) ? $found[1] : '16/9';
		$html  = self::player(
			array(
				'url'          => html_entity_decode( (string) $tags->get_attribute( 'data-video-url' ), ENT_QUOTES, 'UTF-8' ),
				'lesson_id'    => $lesson,
				'track'        => $lesson > 0,
				'custom'       => true,
				'autoplay'     => $flag( 'data-autoplay' ),
				'loop'         => $flag( 'data-loop' ),
				'poster'       => (string) $tags->get_attribute( 'data-poster' ),
				'logo'         => (string) $tags->get_attribute( 'data-logo-url' ),
				'aspect_ratio' => $ratio,
				'title'        => $lesson ? get_the_title( $lesson ) : '',
			)
		);
		return '' === $html ? $placeholder : $html;
	}

	/**
	 * Island directives for the plain `<video>` and the iframe embed of an override.
	 *
	 * @param string $html   HTML.
	 * @param int    $lesson Lesson to track (0: none).
	 * @return string
	 */
	private static function decorate_plain( $html, $lesson ) {
		if ( false === strpos( $html, 'ohmylms-video-player' ) && false === strpos( $html, 'ohmylms-lesson-external-video' ) ) {
			return $html;
		}
		$tags  = new \WP_HTML_Tag_Processor( $html );
		$owned = false;
		while ( $tags->next_tag() ) {
			$tag = $tags->get_tag();
			if ( 'DIV' === $tag && ( $tags->has_class( 'ohmylms-video-player' ) || $tags->has_class( 'ohmylms-lesson-external-video' ) ) && ! $tags->has_class( 'ohmylms-custom-video-player' ) ) {
				$owned = null === $tags->get_attribute( 'data-wp-interactive' );
				if ( $owned ) {
					self::decorate_island( $tags, $lesson );
				}
				continue;
			}
			if ( ! $owned ) {
				continue;
			}
			if ( 'VIDEO' === $tag ) {
				if ( (int) $tags->get_attribute( 'tabindex' ) > 0 ) {
					$tags->remove_attribute( 'tabindex' );
				}
				// The module starts playback (muted, not with reduced motion).
				$tags->remove_attribute( 'autoplay' );
			} elseif ( $tags->has_class( 'ohmylms-video-player-cover' ) && null === $tags->get_attribute( 'data-wp-bind--hidden' ) ) {
				$tags->set_attribute( 'data-wp-bind--hidden', 'state.coverHidden' );
			} elseif ( $tags->has_class( 'ohmylms-video-player-play' ) && null === $tags->get_attribute( 'data-wp-on--click' ) ) {
				$tags->set_attribute( 'data-wp-on--click', 'actions.coverPlay' );
				$tags->set_attribute( 'type', 'button' );
			} elseif ( 'IFRAME' === $tag && 'wistia' === VideoSource::parse( (string) $tags->get_attribute( 'src' ), false )['provider'] ) {
				$tags->add_class( 'wistia_embed' );
				$tags->set_attribute( 'name', 'wistia_embed' );
			}
		}
		return $tags->get_updated_html();
	}

	/**
	 * Make a plain player wrapper an island: read its source ahead, then come back to the wrapper.
	 *
	 * @param \WP_HTML_Tag_Processor $tags   Processor on the wrapper.
	 * @param int                    $lesson Lesson to track (0: none).
	 */
	private static function decorate_island( $tags, $lesson ) {
		$tags->set_bookmark( 'ohmylms-video' );
		$url      = '';
		$autoplay = false;
		$depth    = 0;
		while ( '' === $url && $tags->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
			$name = $tags->get_tag();
			if ( 'DIV' === $name ) {
				$depth += $tags->is_tag_closer() ? -1 : 1;
				if ( $depth < 0 ) {
					break;
				}
				continue;
			}
			if ( $tags->is_tag_closer() ) {
				continue;
			}
			if ( 'VIDEO' === $name ) {
				$autoplay = null !== $tags->get_attribute( 'autoplay' );
				$url      = (string) $tags->get_attribute( 'src' );
			} elseif ( in_array( $name, array( 'SOURCE', 'IFRAME' ), true ) ) {
				$url = (string) $tags->get_attribute( 'src' );
			}
		}
		$tags->seek( 'ohmylms-video' );
		$tags->release_bookmark( 'ohmylms-video' );
		$source = VideoSource::parse( html_entity_decode( $url, ENT_QUOTES, 'UTF-8' ), false );
		if ( '' === $source['provider'] ) {
			return;
		}
		$args    = array(
			'lesson_id' => $lesson,
			'course_id' => 0,
			'track'     => $lesson > 0,
			'autoplay'  => $autoplay,
			'loop'      => false,
			'title'     => $lesson ? get_the_title( $lesson ) : '',
		);
		$context = self::context( $args, $source, false );
		self::enqueue( $context['track'] );
		foreach ( self::island_attributes( $context, false ) as $name => $value ) {
			$tags->set_attribute( $name, $value );
		}
		if ( (int) $tags->get_attribute( 'tabindex' ) > 0 ) {
			$tags->remove_attribute( 'tabindex' );
		}
	}

	/**
	 * Directives of an island root.
	 *
	 * @param array $context Context.
	 * @param bool  $custom  Custom controls.
	 * @return array<string, string>
	 */
	private static function island_attributes( array $context, $custom ) {
		$attributes = array(
			'data-wp-interactive'                   => self::STORE,
			'data-wp-context'                       => wp_json_encode( $context ),
			'data-wp-init'                          => 'callbacks.mount',
			'data-wp-on--keydown'                   => 'actions.keydown',
			'data-wp-on-window--pagehide'           => 'actions.pagehide',
			'data-wp-on-document--visibilitychange' => 'actions.visibility',
			'data-wp-class--is-playing'             => 'context.playing',
		);
		if ( $custom ) {
			$attributes += array(
				'data-wp-on--pointermove'               => 'actions.activity',
				'data-wp-on--focusin'                   => 'actions.activity',
				'data-wp-on-document--click'            => 'actions.outside',
				'data-wp-on-document--fullscreenchange' => 'actions.fullscreenChange',
				'data-wp-on-document--webkitfullscreenchange' => 'actions.fullscreenChange',
				'data-wp-class--is-fullscreen'          => 'context.fullscreen',
			);
		}
		return $attributes;
	}

	/**
	 * Attribute string, escaped; `true` prints a bare attribute, `false` and null none.
	 *
	 * @param array $attributes Attributes.
	 * @return string
	 */
	private static function attributes( array $attributes ) {
		$html = '';
		foreach ( $attributes as $name => $value ) {
			if ( null === $value || false === $value ) {
				continue;
			}
			$html .= true === $value ? ' ' . $name : sprintf( ' %s="%s"', $name, esc_attr( (string) $value ) );
		}
		return $html;
	}

	/**
	 * Accessible name of a player.
	 *
	 * @param array $args Player arguments.
	 * @return string
	 */
	private static function label( array $args ) {
		$title = wp_strip_all_tags( (string) $args['title'] );
		/* translators: %s: video or lesson title. */
		return '' !== $title ? sprintf( __( 'Video: %s', 'ohmylms' ), $title ) : __( 'Video player', 'ohmylms' );
	}

	/**
	 * Caption tracks of a video file. Lessons have no caption field; add-ons provide them here.
	 *
	 * @param array $args Player arguments.
	 * @return array<int, array{src:string,srclang:string,label:string,kind:string,default:bool}>
	 */
	private static function text_tracks( array $args ) {
		/**
		 * Filters the caption tracks (`<track>`) of an uploaded or self-hosted lesson video.
		 *
		 * @param array  $tracks    Each `src`, `srclang`, `label`, optional `kind` (`subtitles` or `captions`) and `default`.
		 * @param int    $lesson_id Lesson ID (0 for a session replay).
		 * @param string $url       Video URL.
		 */
		$tracks = apply_filters( 'ohmylms_video_text_tracks', array(), absint( $args['lesson_id'] ), (string) $args['url'] );
		$clean  = array();
		foreach ( is_array( $tracks ) ? $tracks : array() as $track ) {
			if ( ! is_array( $track ) || empty( $track['src'] ) ) {
				continue;
			}
			$clean[] = array(
				'src'     => (string) $track['src'],
				'srclang' => sanitize_key( (string) ( $track['srclang'] ?? '' ) ),
				'label'   => (string) ( $track['label'] ?? '' ),
				'kind'    => 'captions' === ( $track['kind'] ?? '' ) ? 'captions' : 'subtitles',
				'default' => ! empty( $track['default'] ),
			);
		}
		return $clean;
	}

	/**
	 * `<video>` with its sources and tracks.
	 *
	 * @param array $args       Player arguments.
	 * @param array $attributes `<video>` attributes.
	 * @return string
	 */
	private static function video_element( array $args, array $attributes ) {
		$url    = (string) $args['url'];
		$tracks = self::text_tracks( $args );
		$remote = 0 === strpos( $url, 'http' ) && wp_parse_url( $url, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST );
		if ( $tracks && $remote ) {
			// Cross-origin captions need CORS; without tracks the file must not need it.
			$attributes['crossorigin'] = 'anonymous';
		}
		$html = '<video' . self::attributes( $attributes ) . '>';
		// MOV plays best when the browser negotiates the codec itself.
		$html .= false !== stripos( $url, '.mov' ) ? '' : sprintf( '<source src="%s" type="%s">', esc_url( $url ), esc_attr( VideoSource::mime( $url ) ) );
		foreach ( $tracks as $track ) {
			$html .= '<track' . self::attributes(
				array(
					'kind'    => $track['kind'],
					'src'     => esc_url( $track['src'] ),
					'srclang' => $track['srclang'] ? $track['srclang'] : null,
					'label'   => $track['label'] ? $track['label'] : null,
					'default' => $track['default'],
				)
			) . '>';
		}
		$html .= esc_html__( 'Your browser does not support the video tag.', 'ohmylms' ) . '</video>';
		return $html;
	}

	/**
	 * Custom player (HTML5, YouTube, Vimeo) with accessible custom controls.
	 *
	 * @param array $args    Player arguments.
	 * @param array $source  Source.
	 * @param array $context Context.
	 * @return string
	 */
	private static function custom_markup( array $args, array $source, array $context ) {
		$id       = 'ohmylms-video-' . ( ++self::$players );
		$provider = $source['provider'];
		$root     = self::attributes(
			array(
				'class'          => 'ohmylms-custom-video-player ohmylms-responsive-video-wrapper is-provider-' . $provider,
				'id'             => $id,
				'style'          => 'aspect-ratio: ' . $args['aspect_ratio'] . ';',
				'data-video-url' => esc_url_raw( (string) $args['url'] ),
				'role'           => 'region',
				'aria-label'     => self::label( $args ),
			) + self::island_attributes( $context, true )
		);
		ob_start();
		if ( $args['logo'] && Interactivity::once( 'video-logo-style' ) ) {
			printf( '<style>.ohmylms-page img.ohmylms-player-logo { background-color: %s !important; }</style>', esc_html( (string) sanitize_hex_color( (string) get_option( 'ohmylms_video_player_logo_bg_color', '#6E42D3' ) ) ) );
		}
		?>
		<div<?php echo $root; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- self::attributes() escapes. ?>>
			<div class="ohmylms-video-player-container" data-wp-on--click="actions.toggle">
				<?php
				if ( 'html5' === $provider ) {
					// Native controls until the module mounts its own, so the video also plays without JavaScript.
					$video = self::video_element(
						$args,
						array(
							'class'       => 'ohmylms-html5-video',
							'controls'    => true,
							'playsinline' => true,
							'preload'     => 'metadata',
							'poster'      => $args['poster'] ? esc_url( (string) $args['poster'] ) : null,
							'loop'        => (bool) $args['loop'],
							'muted'       => (bool) $args['autoplay'],
							'aria-label'  => self::label( $args ),
						)
					);
					echo $video; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by video_element().
				} else {
					$page = 'youtube' === $provider ? 'https://www.youtube.com/watch?v=' . rawurlencode( $source['id'] ) : 'https://vimeo.com/' . rawurlencode( $source['id'] ) . ( $source['hash'] ? '/' . rawurlencode( $source['hash'] ) : '' );
					/* translators: %s: YouTube or Vimeo. */
					printf( '<noscript><a href="%s" target="_blank" rel="noopener">%s</a></noscript>', esc_url( $page ), esc_html( sprintf( __( 'Watch this video on %s', 'ohmylms' ), 'youtube' === $provider ? 'YouTube' : 'Vimeo' ) ) );
				}
				?>
			</div>
			<?php if ( 'html5' !== $provider ) : ?>
				<div class="ohmylms-video-interaction-blocker" aria-hidden="true" data-wp-on--click="actions.toggle"></div>
				<div class="ohmylms-branding-blocker-top" aria-hidden="true" data-wp-on--click="actions.toggle"></div>
				<div class="ohmylms-branding-blocker-bottom-right" aria-hidden="true" data-wp-on--click="actions.toggle"></div>
			<?php endif; ?>
			<?php if ( $args['logo'] ) : ?>
				<button type="button" class="ohmylms-player-logo-button" aria-label="<?php esc_attr_e( 'Play video', 'ohmylms' ); ?>" hidden data-wp-bind--hidden="state.overlayHidden" data-wp-on--click="actions.start">
					<img class="ohmylms-player-logo" src="<?php echo esc_url( $args['logo'] ); ?>" alt="" style="top: 50%; left: 50%; transform: translate(-50%, -50%);">
				</button>
			<?php else : ?>
				<button type="button" class="ohmylms-big-play-button" aria-label="<?php esc_attr_e( 'Play video', 'ohmylms' ); ?>" hidden data-wp-bind--hidden="state.overlayHidden" data-wp-on--click="actions.start">
					<svg width="48" height="48" viewBox="0 0 100 100" fill="none" aria-hidden="true" focusable="false"><circle class="play-button-circle" cx="50" cy="50" r="48" stroke-width="4"/><path class="play-button-icon" d="M40 30L70 50L40 70V30Z"/></svg>
				</button>
			<?php endif; ?>
			<div class="ohmylms-video-error" role="alert" hidden data-wp-bind--hidden="state.errorHidden"><p data-wp-text="context.error"></p></div>
			<div class="ohmylms-seek-indicator" aria-hidden="true" hidden data-wp-bind--hidden="state.indicatorHidden"><span data-wp-text="context.indicator"></span></div>
			<div class="ohmylms-custom-controls" hidden data-wp-bind--hidden="!context.mounted" data-wp-class--hidden="state.controlsHidden">
				<div class="ohmylms-controls-progress-container">
					<div class="ohmylms-progress-bar" role="slider" tabindex="0" aria-label="<?php esc_attr_e( 'Seek', 'ohmylms' ); ?>" aria-valuemin="0" aria-valuemax="<?php echo esc_attr( (string) round( $context['duration'] ) ); ?>" aria-valuenow="<?php echo esc_attr( (string) round( $context['time'] ) ); ?>" data-wp-bind--aria-valuemax="state.durationMax" data-wp-bind--aria-valuenow="state.timeNow" data-wp-bind--aria-valuetext="state.timeText" data-wp-class--is-seeking="context.seeking" data-wp-on--keydown="actions.seekKey" data-wp-on--pointerdown="actions.seekStart" data-wp-on--pointermove="actions.seekMove" data-wp-on--pointerup="actions.seekEnd" data-wp-on--pointercancel="actions.seekEnd">
						<div class="ohmylms-progress-filled" data-wp-style--width="state.progressWidth"></div>
						<div class="ohmylms-progress-handle" data-wp-style--left="state.progressWidth"></div>
					</div>
					<div class="ohmylms-time-display" aria-hidden="true">
						<span class="ohmylms-current-time" data-wp-text="state.currentLabel">0:00</span>
						<span class="ohmylms-separator">/</span>
						<span class="ohmylms-duration" data-wp-text="state.durationLabel">--:--</span>
					</div>
				</div>
				<div class="ohmylms-controls-bottom">
					<div class="ohmylms-controls-left">
						<button type="button" class="ohmylms-control-btn ohmylms-play-pause" aria-label="<?php esc_attr_e( 'Play', 'ohmylms' ); ?>" data-wp-bind--aria-label="state.playLabel" data-wp-on--click="actions.toggle">
							<span class="ohmylms-play-icon" data-wp-bind--hidden="context.playing"><svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M8 5v14l11-7z" fill="currentColor"/></svg></span>
							<span class="ohmylms-pause-icon" hidden data-wp-bind--hidden="!context.playing"><svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 4h4v16H6V4zm8 0h4v16h-4V4z" fill="currentColor"/></svg></span>
						</button>
						<div class="ohmylms-volume-control" data-wp-bind--hidden="state.volumeHidden">
							<button type="button" class="ohmylms-control-btn ohmylms-volume-btn" aria-label="<?php esc_attr_e( 'Mute', 'ohmylms' ); ?>" aria-pressed="false" data-wp-bind--aria-pressed="state.mutePressed" data-wp-on--click="actions.mute">
								<span class="ohmylms-volume-icon" data-wp-bind--hidden="state.mutePressed"><svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02z" fill="currentColor"/></svg></span>
								<span class="ohmylms-mute-icon" hidden data-wp-bind--hidden="!state.mutePressed"><svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z" fill="currentColor"/></svg></span>
							</button>
							<div class="ohmylms-volume-slider">
								<div class="ohmylms-volume-track" role="slider" tabindex="0" aria-label="<?php esc_attr_e( 'Volume', 'ohmylms' ); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="100" aria-valuetext="100%" data-wp-bind--aria-valuenow="state.volumePercent" data-wp-bind--aria-valuetext="state.volumeText" data-wp-class--is-dragging="context.volumeDrag" data-wp-on--keydown="actions.volumeKey" data-wp-on--pointerdown="actions.volumeStart" data-wp-on--pointermove="actions.volumeMove" data-wp-on--pointerup="actions.volumeEnd" data-wp-on--pointercancel="actions.volumeEnd">
									<div class="ohmylms-volume-fill" data-wp-style--width="state.volumeWidth"></div>
									<div class="ohmylms-volume-thumb" data-wp-style--left="state.volumeWidth"></div>
								</div>
							</div>
						</div>
					</div>
					<div class="ohmylms-controls-right">
						<button type="button" class="ohmylms-control-btn ohmylms-captions-btn" aria-label="<?php esc_attr_e( 'Captions', 'ohmylms' ); ?>" aria-pressed="false" hidden data-wp-bind--hidden="state.captionsHidden" data-wp-bind--aria-pressed="context.captionsOn" data-wp-on--click="actions.captions">
							<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M19 4H5a2 2 0 00-2 2v12a2 2 0 002 2h14a2 2 0 002-2V6a2 2 0 00-2-2zm-8 7H9.5v-.5h-2v3h2V13H11v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-4a1 1 0 011-1h3a1 1 0 011 1v1zm7 0h-1.5v-.5h-2v3h2V13H18v1a1 1 0 01-1 1h-3a1 1 0 01-1-1v-4a1 1 0 011-1h3a1 1 0 011 1v1z" fill="currentColor"/></svg>
						</button>
						<div class="ohmylms-speed-control" data-wp-bind--hidden="state.speedHidden">
							<button type="button" class="ohmylms-control-btn ohmylms-speed-btn" aria-label="<?php esc_attr_e( 'Playback speed', 'ohmylms' ); ?>" aria-haspopup="menu" aria-expanded="false" aria-controls="<?php echo esc_attr( $id ); ?>-speed" data-wp-bind--aria-expanded="context.speedOpen" data-wp-on--click="actions.speedToggle" data-wp-on--keydown="actions.speedButtonKey">
								<span class="ohmylms-speed-text" data-wp-text="state.rateLabel">1x</span>
							</button>
							<div class="ohmylms-speed-menu" id="<?php echo esc_attr( $id ); ?>-speed" role="menu" aria-label="<?php esc_attr_e( 'Playback speed', 'ohmylms' ); ?>" data-wp-class--active="context.speedOpen" data-wp-on--keydown="actions.speedMenuKey">
								<?php foreach ( self::RATES as $rate ) : ?>
									<button type="button" role="menuitemradio" tabindex="-1" aria-checked="<?php echo 1 === $rate ? 'true' : 'false'; ?>" <?php echo wp_interactivity_data_wp_context( array( 'speedRate' => $rate ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core escapes the attribute. ?> data-wp-bind--aria-checked="state.rateChecked" data-wp-class--active="state.rateChecked" data-wp-on--click="actions.setRate"><?php echo esc_html( $rate . 'x' ); ?></button>
								<?php endforeach; ?>
							</div>
						</div>
						<button type="button" class="ohmylms-control-btn ohmylms-fullscreen-btn" aria-label="<?php esc_attr_e( 'Full screen', 'ohmylms' ); ?>" data-wp-bind--aria-label="state.fullscreenLabel" data-wp-on--click="actions.fullscreen">
							<span class="ohmylms-fullscreen-icon" data-wp-bind--hidden="context.fullscreen"><svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z" fill="currentColor"/></svg></span>
							<span class="ohmylms-exit-fullscreen-icon" hidden data-wp-bind--hidden="!context.fullscreen"><svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 16h3v3h2v-5H5v2zm3-8H5v2h5V5H8v3zm6 11h2v-3h3v-2h-5v5zm2-11V5h-2v5h5V8h-3z" fill="currentColor"/></svg></span>
						</button>
					</div>
				</div>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Plain `<video>` (custom player off), with the poster cover.
	 *
	 * @param array $args    Player arguments.
	 * @param array $source  Source.
	 * @param array $context Context.
	 * @return string
	 */
	private static function native_markup( array $args, array $source, array $context ) {
		$ratio = $args['aspect_ratio'];
		$root  = self::attributes(
			array(
				'class'          => 'ohmylms-video-player ohmylms-responsive-video-wrapper',
				'style'          => 'max-width: 100%; margin: 0 auto; aspect-ratio: ' . $ratio . ';',
				'data-video-url' => esc_url_raw( $source['embed'] ),
			) + self::island_attributes( $context, false )
		);
		$bare  = ! $args['controls'];
		$video = self::video_element(
			$args,
			array(
				'class'        => 'the-video',
				'style'        => 'aspect-ratio: ' . $ratio . ';',
				'controls'     => ! $bare,
				'controlslist' => $bare ? null : 'nodownload nopictureinpicture',
				'playsinline'  => true,
				'preload'      => 'metadata',
				'loop'         => (bool) $args['loop'],
				'muted'        => (bool) $args['autoplay'],
				// Without controls the video itself takes the keyboard (Space, Enter, K).
				'tabindex'     => $bare ? '0' : null,
				'aria-label'   => self::label( $args ),
			)
		);
		ob_start();
		?>
		<div<?php echo $root; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- self::attributes() escapes. ?>>
			<?php echo $video; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by video_element(). ?>
			<?php if ( $args['poster'] && ! $args['autoplay'] ) : ?>
				<div class="ohmylms-video-player-cover" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" hidden data-wp-bind--hidden="state.coverHidden">
					<img src="<?php echo esc_url( (string) $args['poster'] ); ?>" alt="<?php echo esc_attr( (string) $args['poster_alt'] ); ?>" style="width: 100%; height: 100%; object-fit: cover;">
					<button type="button" class="ohmylms-video-player-play" aria-label="<?php esc_attr_e( 'Play video', 'ohmylms' ); ?>" data-wp-on--click="actions.coverPlay">
						<svg width="14" height="14" fill="none" viewBox="0 0 14 14" aria-hidden="true" focusable="false"><path fill="var(--ohmylms-primary-color)" d="M2.977.309C1.715-.415.69.178.69 1.632v10.735c0 1.456 1.024 2.048 2.286 1.325l9.382-5.381c1.263-.724 1.263-1.898 0-2.622L2.977.31z"/></svg>
					</button>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Provider iframe (custom player off, or a provider the custom controls do not drive).
	 *
	 * @param array $args    Player arguments.
	 * @param array $source  Source.
	 * @param array $context Context.
	 * @return string
	 */
	private static function iframe_markup( array $args, array $source, array $context ) {
		$ratio  = $args['aspect_ratio'];
		$wistia = 'wistia' === $source['provider'];
		$root   = self::attributes(
			array(
				'class'          => 'ohmylms-lesson-external-video ohmylms-responsive-video-wrapper',
				'style'          => 'aspect-ratio: ' . $ratio . ';',
				'data-video-url' => esc_url_raw( (string) $args['url'] ),
			) + self::island_attributes( $context, false )
		);
		$frame  = self::attributes(
			array(
				'src'             => esc_url( self::embed_url( $source, $args ) ),
				'class'           => $wistia ? 'wistia_embed' : null,
				'name'            => $wistia ? 'wistia_embed' : null,
				'title'           => self::label( $args ),
				'frameborder'     => '0',
				'allow'           => self::IFRAME_ALLOW,
				'referrerpolicy'  => 'strict-origin-when-cross-origin',
				'allowfullscreen' => true,
				'style'           => 'aspect-ratio: ' . $ratio . ';',
			)
		);
		return '<div' . $root . '><iframe' . $frame . '></iframe></div>';
	}
}
