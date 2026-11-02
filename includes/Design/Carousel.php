<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Server-rendered scroll-snap carousel driven by the `ohmylms/carousel` Interactivity module.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Design;

use OhMyLMS\Extensions\Interactivity;

defined( 'ABSPATH' ) || exit;

/**
 * Scroll-snap carousel markup.
 *
 * The track is a plain horizontally scrolling list, so it stays usable without JavaScript and in
 * fetched or editor-preview HTML that is never hydrated. Arrows, dots and the autoplay toggle start
 * `hidden` and are revealed by the module. Slides per view come from CSS custom properties that
 * mirror the old Slick breakpoints (below 1200px at most 3, below 768px at most 2, below 576px 1).
 */
final class Carousel {
	const MODULE = 'ohmylms/carousel';

	/** Attributes printed on the scrolling track; its children are the slides. */
	const TRACK_ATTRIBUTES = 'tabindex="0" data-wp-on--scroll="actions.sync" data-wp-on--keydown="actions.key"';

	/** Breakpoint caps on slides per view, matching the old Slick `responsive` settings. */
	const BREAKPOINTS = array(
		'lg' => 3,
		'md' => 2,
		'sm' => 1,
	);

	/**
	 * Contexts of carousels opened but not yet closed.
	 *
	 * @var array[]
	 */
	private static $open = array();

	/**
	 * Accessible name for the next opened carousel.
	 *
	 * @var string
	 */
	private static $label = '';

	/**
	 * Name the next carousel, e.g. with the row heading shown above it.
	 *
	 * @param string $label Accessible name.
	 */
	public static function label( $label ) {
		self::$label = (string) $label;
	}

	/**
	 * Settings for a course card carousel. Elements only expose a column count (Slick `slidesToShow`);
	 * the filter can turn on autoplay, looping or dots.
	 *
	 * @param int   $columns Columns per row.
	 * @param array $atts    Shortcode/element attributes, empty on the archive page.
	 * @return array
	 */
	public static function course_settings( $columns, array $atts = array() ) {
		/**
		 * Filters course carousel settings.
		 *
		 * @param array $settings `slidesToShow`, `autoplay`, `autoplaySpeed` (ms), `loop`, `arrows`, `dots`.
		 * @param array $atts     Shortcode/element attributes.
		 */
		$settings = apply_filters( 'ohmylms_course_carousel_settings', array( 'slidesToShow' => (int) $columns ), $atts );
		return is_array( $settings ) ? $settings : array( 'slidesToShow' => (int) $columns );
	}

	/**
	 * Slides per view at each breakpoint.
	 *
	 * @param int $slides Slides per view on wide screens.
	 * @return array{base:int,lg:int,md:int,sm:int}
	 */
	public static function responsive( $slides ) {
		$slides = self::slides( $slides );
		$sizes  = array( 'base' => $slides );
		foreach ( self::BREAKPOINTS as $name => $cap ) {
			$sizes[ $name ] = min( $slides, $cap );
		}
		return $sizes;
	}

	/**
	 * Initial Interactivity context. Slick option names are accepted as aliases.
	 *
	 * @param array $settings `slidesToShow`, `autoplay`, `autoplaySpeed`, `loop` (or `infinite`), `arrows`, `dots`.
	 * @return array
	 */
	public static function context( array $settings ) {
		$slides   = self::slides( isset( $settings['slidesToShow'] ) ? $settings['slidesToShow'] : 0 );
		$autoplay = ! empty( $settings['autoplay'] );
		return array(
			'slidesToShow'  => $slides,
			'autoplay'      => $autoplay,
			'autoplaySpeed' => max( 1000, isset( $settings['autoplaySpeed'] ) ? (int) $settings['autoplaySpeed'] : 5000 ),
			'loop'          => ! empty( $settings['loop'] ) || ! empty( $settings['infinite'] ),
			'arrows'        => ! isset( $settings['arrows'] ) || (bool) $settings['arrows'],
			'dots'          => ! empty( $settings['dots'] ),
			'index'         => 0,
			'count'         => 0,
			'perView'       => $slides,
			'playing'       => $autoplay,
			'hovered'       => false,
			'focused'       => false,
			'mounted'       => false,
			'labels'        => array(
				/* translators: 1: slide position, 2: number of positions. */
				'dot'   => __( 'Go to slide %1$d of %2$d', 'ohmylms' ),
				'play'  => __( 'Play carousel', 'ohmylms' ),
				'pause' => __( 'Pause carousel', 'ohmylms' ),
			),
		);
	}

	/**
	 * Open a carousel: the region wrapper and its arrows. Print the track next, then close().
	 *
	 * @param array $settings See context().
	 * @return string
	 */
	public static function open( array $settings ) {
		Interactivity::enqueue( self::MODULE );
		$context      = self::context( $settings );
		self::$open[] = $context;
		$label        = '' !== self::$label ? self::$label : __( 'Courses', 'ohmylms' );
		self::$label  = '';
		$sizes        = self::responsive( $context['slidesToShow'] );
		$style        = sprintf(
			'--per-row:%1$d;--ohmylms-carousel-slides:%1$d;--ohmylms-carousel-slides-lg:%2$d;--ohmylms-carousel-slides-md:%3$d;--ohmylms-carousel-slides-sm:%4$d',
			$sizes['base'],
			$sizes['lg'],
			$sizes['md'],
			$sizes['sm']
		);

		return '<div class="ohmylms-carousel-outer ohmylms-carousel" role="region" aria-roledescription="' . esc_attr__( 'carousel', 'ohmylms' ) . '" aria-label="' . esc_attr( $label ) . '" style="' . esc_attr( $style ) . '"'
			. ' data-wp-interactive="' . esc_attr( self::MODULE ) . '" ' . wp_interactivity_data_wp_context( $context )
			. ' data-wp-init="callbacks.mount" data-wp-class--is-enhanced="context.mounted"'
			. ' data-wp-on--mouseenter="actions.hoverIn" data-wp-on--mouseleave="actions.hoverOut"'
			. ' data-wp-on--focusin="actions.focusIn" data-wp-on--focusout="actions.focusOut">'
			. self::arrow( 'prev', __( 'Previous', 'ohmylms' ) )
			. self::arrow( 'next', __( 'Next', 'ohmylms' ) );
	}

	/** Whether a carousel was opened and not closed yet. */
	public static function is_open() {
		return ! empty( self::$open );
	}

	/**
	 * Close the most recently opened carousel: dots, the autoplay toggle and the wrapper's end tag.
	 *
	 * @return string Empty when no carousel is open.
	 */
	public static function close() {
		$context = array_pop( self::$open );
		if ( null === $context ) {
			return '';
		}
		$html = '';
		if ( $context['dots'] || $context['autoplay'] ) {
			$html .= '<div class="ohmylms-carousel-controls">';
			if ( $context['autoplay'] ) {
				$html .= '<button type="button" class="ohmylms-carousel-play" hidden data-wp-bind--hidden="!state.scrollable" data-wp-class--is-paused="!context.playing" data-wp-bind--aria-label="state.playLabel" data-wp-on--click="actions.togglePlay" aria-label="' . esc_attr( $context['labels']['pause'] ) . '"><span aria-hidden="true" class="ohmylms-carousel-play-icon"></span></button>';
			}
			if ( $context['dots'] ) {
				$html .= '<div class="ohmylms-carousel-dots" hidden data-wp-bind--hidden="state.dotsHidden">'
					. '<template data-wp-each--dot="state.dots" data-wp-each-key="context.dot.index">'
					. '<button type="button" class="ohmylms-carousel-dot" data-wp-bind--aria-label="context.dot.label" data-wp-bind--aria-current="context.dot.current" data-wp-on--click="actions.goTo"></button>'
					. '</template></div>';
			}
			$html .= '</div>';
		}
		return $html . '</div>';
	}

	/**
	 * Previous/next button. Icons are the chevrons the Slick arrows used.
	 *
	 * @param string $direction `prev` or `next`.
	 * @param string $label     Accessible name.
	 * @return string
	 */
	private static function arrow( $direction, $label ) {
		$path = 'prev' === $direction
			? 'M1.655 6.526L7.01 1.171a1.167 1.167 0 111.645 1.657L3.288 8.171a1.167 1.167 0 000 1.657l5.367 5.343a1.167 1.167 0 11-1.645 1.657l-5.355-5.355a3.5 3.5 0 010-4.947z'
			: 'M7.345 6.526L1.99 1.171A1.167 1.167 0 10.345 2.828l5.367 5.343a1.167 1.167 0 010 1.657L.345 15.171a1.167 1.167 0 101.645 1.657l5.355-5.355a3.5 3.5 0 000-4.947z';
		$edge = 'prev' === $direction ? 'state.atStart' : 'state.atEnd';
		return '<button type="button" class="ohmylms-carousel-arrow ohmylms-carousel-' . esc_attr( $direction ) . '" aria-label="' . esc_attr( $label ) . '" hidden'
			. ' data-wp-bind--hidden="state.arrowsHidden" data-wp-bind--aria-disabled="' . esc_attr( $edge ) . '" data-wp-on--click="actions.' . esc_attr( $direction ) . '">'
			. '<svg width="9" height="18" fill="none" viewBox="0 0 9 18" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path fill="#A1A1AA" d="' . esc_attr( $path ) . '"/></svg>'
			. '</button>';
	}

	/**
	 * Clamp slides per view; 0 or junk (the course list's `columns` defaults to "no") means 4.
	 *
	 * @param mixed $slides Slides per view.
	 * @return int
	 */
	private static function slides( $slides ) {
		$slides = (int) $slides;
		return $slides < 1 ? 4 : min( $slides, 6 );
	}
}
