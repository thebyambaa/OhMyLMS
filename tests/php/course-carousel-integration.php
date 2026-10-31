<?php
/**
 * Course carousels render the `ohmylms/carousel` Interactivity markup and Slick is gone (includes/Design/Carousel.php).
 *
 * Boots the local site (800.local) and renders the course list shortcode, which the Elementor, Bricks and
 * WPBakery course list elements all delegate to, in carousel layouts 3 and 4. Checks the region, context
 * (mapped from the element's column count), breakpoint custom properties, arrows, track directives,
 * the optional dots/autoplay controls, balanced markup on the archive path, that `ohmylms/carousel` is
 * registered and enqueued, and that no `ohmylms-slick` handle is registered or enqueued anywhere.
 * Options are overridden with `pre_option_*` filters; nothing is written to the database. Needs at least
 * one published course.
 *
 * Usage (set OHMYLMS_COURSE_CAROUSEL_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/course-carousel-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.WP.AlternativeFunctions

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_COURSE_CAROUSEL_TEST' ) ) {
	exit( "Set OHMYLMS_COURSE_CAROUSEL_TEST=1 to run the course carousel tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Design\Carousel;

$checks = 0;

/**
 * Assert a condition.
 *
 * @param mixed  $value   Value that must be truthy.
 * @param string $message Failure message.
 * @throws RuntimeException When the condition is falsy.
 */
function ok( $value, $message ) {
	global $checks;
	if ( ! $value ) {
		throw new RuntimeException( 'FAILED: ' . $message );
	}
	++$checks;
}

/**
 * Read a private WP_Script_Modules property (registered modules or the enqueue queue).
 *
 * @param string $name Property name.
 * @return array
 */
function script_modules_property( $name ) {
	$property = new ReflectionProperty( WP_Script_Modules::class, $name );
	$property->setAccessible( true );
	return (array) $property->getValue( wp_script_modules() );
}

/**
 * Attributes of the first tag carrying a class.
 *
 * @param string $html       Markup.
 * @param string $class_name Class name.
 * @return WP_HTML_Tag_Processor|null Processor positioned on the tag.
 */
function tag_with_class( $html, $class_name ) {
	$tags = new WP_HTML_Tag_Processor( $html );
	return $tags->next_tag( array( 'class_name' => $class_name ) ) ? $tags : null;
}

/**
 * Render the course list shortcode with the given attributes.
 *
 * @param string $atts Shortcode attribute string.
 * @return string
 */
function render_course_list( $atts ) {
	return do_shortcode( '[ohmylms_course_list ' . $atts . ']' );
}

$rows = static function () {
	return array(
		array(
			'row_heading'          => 'Carousel test row',
			'row_display_criteria' => 'all',
		),
	);
};
add_filter( 'pre_option_ohmylms_archive_page_row', $rows );

$courses = get_posts(
	array(
		'post_type'      => OHMYLMS_COURSE_CPT,
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
	)
);
ok( ! empty( $courses ), 'the site has at least one published course to render' );

// Script registration: Slick is gone, the carousel module is registered.
$GLOBALS['wp_scripts'] = new WP_Scripts();
do_action( 'wp_enqueue_scripts' );
foreach ( array( 'ohmylms-slick', 'ohmylms-slick-editor' ) as $handle ) {
	ok( ! wp_script_is( $handle, 'registered' ), "$handle is not registered" );
	ok( ! wp_script_is( $handle, 'enqueued' ), "$handle is not enqueued" );
}
$registered = script_modules_property( 'registered' );
ok( isset( $registered['ohmylms/carousel'] ), 'ohmylms/carousel is a registered script module' );
ok( false !== strpos( (string) $registered['ohmylms/carousel']['src'], 'assets/interactivity/carousel.js' ), 'ohmylms/carousel points at assets/interactivity/carousel.js' );
$dependencies = array_map(
	static function ( $dependency ) {
		return is_array( $dependency ) ? $dependency['id'] : $dependency;
	},
	(array) $registered['ohmylms/carousel']['dependencies']
);
ok( in_array( '@wordpress/interactivity', $dependencies, true ), 'ohmylms/carousel depends on @wordpress/interactivity' );

// Layout 3, three columns: the Slick slidesToShow/responsive settings map to context and CSS properties.
$html = render_course_list( 'layout="grid" layout_style="grid-style3" columns="3" posts_per_page="5"' );
ok( false === stripos( $html, 'slick' ), 'carousel markup has no Slick classes or initializers' );
ok( false === strpos( $html, 'ohmylms-carousel-skeleton' ), 'carousel renders real cards, not a skeleton waiting for JavaScript' );
$root = tag_with_class( $html, 'ohmylms-carousel' );
ok( null !== $root, 'carousel wrapper renders' );
ok( 'ohmylms/carousel' === $root->get_attribute( 'data-wp-interactive' ), 'wrapper is an ohmylms/carousel island' );
ok( 'region' === $root->get_attribute( 'role' ) && 'carousel' === $root->get_attribute( 'aria-roledescription' ), 'wrapper is a labelled carousel region' );
ok( 'Carousel test row' === $root->get_attribute( 'aria-label' ), 'region is named by its row heading' );
ok( 'callbacks.mount' === $root->get_attribute( 'data-wp-init' ), 'wrapper mounts the store' );
ok( 'actions.hoverIn' === $root->get_attribute( 'data-wp-on--mouseenter' ) && 'actions.focusIn' === $root->get_attribute( 'data-wp-on--focusin' ), 'hover and focus pause autoplay' );
ok( $root->has_class( 'ohmylms-carousel-outer' ), 'legacy .ohmylms-carousel-outer class is kept' );
$context = json_decode( (string) $root->get_attribute( 'data-wp-context' ), true );
ok( is_array( $context ), 'context is JSON' );
ok( 3 === $context['slidesToShow'] && 3 === $context['perView'], 'columns map to slidesToShow' );
ok( false === $context['autoplay'] && false === $context['loop'] && false === $context['dots'] && true === $context['arrows'], 'defaults match the old Slick options (no autoplay, infinite false, arrows, no dots)' );
ok( 0 === $context['index'] && 0 === $context['count'], 'runtime state starts empty' );
ok( false !== strpos( $context['labels']['dot'], '%1$d' ), 'dot label template is passed to the store' );
$style = (string) $root->get_attribute( 'style' );
ok( false !== strpos( $style, '--per-row:3;--ohmylms-carousel-slides:3;--ohmylms-carousel-slides-lg:3;--ohmylms-carousel-slides-md:2;--ohmylms-carousel-slides-sm:1' ), 'breakpoints become CSS custom properties: ' . $style );

$track = tag_with_class( $html, 'ohmylms-carousel-track' );
ok( null !== $track, 'scroll-snap track renders' );
ok( $track->has_class( 'ohmylms-course-cards' ) && $track->has_class( 'ohmylms-course-cards-carousel' ), 'track keeps .ohmylms-course-cards and .ohmylms-course-cards-carousel' );
ok( '3' === $track->get_attribute( 'data-col' ), 'track keeps data-col' );
ok( '0' === $track->get_attribute( 'tabindex' ), 'track is keyboard focusable' );
ok( 'actions.key' === $track->get_attribute( 'data-wp-on--keydown' ), 'arrow keys on the track navigate' );
ok( 'actions.sync' === $track->get_attribute( 'data-wp-on--scroll' ), 'scrolling syncs the index' );
ok( substr_count( $html, 'class="course-card' ) >= 1, 'course cards are slides inside the track' );

foreach ( array(
	'prev' => array( 'Previous', 'state.atStart' ),
	'next' => array( 'Next', 'state.atEnd' ),
) as $direction => $expected ) {
	$tags  = new WP_HTML_Tag_Processor( $html );
	$found = $tags->next_tag(
		array(
			'tag_name'   => 'button',
			'class_name' => 'ohmylms-carousel-' . $direction,
		)
	);
	ok( $found, "$direction arrow is a <button>" );
	ok( 'button' === $tags->get_attribute( 'type' ), "$direction arrow has type=button" );
	ok( $expected[0] === $tags->get_attribute( 'aria-label' ), "$direction arrow is labelled" );
	ok( true === $tags->get_attribute( 'hidden' ), "$direction arrow is hidden until the store reveals it" );
	ok( 'state.arrowsHidden' === $tags->get_attribute( 'data-wp-bind--hidden' ), "$direction arrow visibility is bound" );
	ok( $expected[1] === $tags->get_attribute( 'data-wp-bind--aria-disabled' ), "$direction arrow disables at the edge" );
	ok( 'actions.' . $direction === $tags->get_attribute( 'data-wp-on--click' ), "$direction arrow click action" );
}
ok( false === strpos( $html, 'ohmylms-carousel-dots' ) && false === strpos( $html, 'ohmylms-carousel-play' ), 'no dots or autoplay toggle unless enabled' );
ok( substr_count( $html, '<div' ) === substr_count( $html, '</div>' ), 'layout 3 markup is balanced' );
ok( in_array( 'ohmylms/carousel', array_keys( script_modules_property( 'queue' ) ), true ) || in_array( 'ohmylms/carousel', script_modules_property( 'queue' ), true ), 'rendering enqueues ohmylms/carousel' );
ok( ! wp_script_is( 'ohmylms-slick', 'enqueued' ), 'rendering does not enqueue Slick' );

// Optional Slick-era options through the settings filter.
$enable = static function ( $settings ) {
	return array_merge(
		$settings,
		array(
			'autoplay'      => true,
			'autoplaySpeed' => 4000,
			'infinite'      => true,
			'dots'          => true,
		)
	);
};
add_filter( 'ohmylms_course_carousel_settings', $enable );
$html = render_course_list( 'layout="grid" layout_style="grid-style4" columns="5" posts_per_page="5"' );
remove_filter( 'ohmylms_course_carousel_settings', $enable );
$root    = tag_with_class( $html, 'ohmylms-carousel' );
$context = json_decode( (string) $root->get_attribute( 'data-wp-context' ), true );
ok( 5 === $context['slidesToShow'] && true === $context['autoplay'] && 4000 === $context['autoplaySpeed'] && true === $context['loop'] && true === $context['dots'] && true === $context['playing'], 'filter enables autoplay, loop (Slick infinite) and dots' );
ok( false !== strpos( (string) $root->get_attribute( 'style' ), '--ohmylms-carousel-slides:5;--ohmylms-carousel-slides-lg:3;--ohmylms-carousel-slides-md:2;--ohmylms-carousel-slides-sm:1' ), 'five columns cap at 3/2/1 below the breakpoints' );
ok( false !== strpos( $html, '<template data-wp-each--dot="state.dots"' ) && false !== strpos( $html, 'class="ohmylms-carousel-dot"' ), 'dots render from state' );
$play = tag_with_class( $html, 'ohmylms-carousel-play' );
ok( null !== $play && 'actions.togglePlay' === $play->get_attribute( 'data-wp-on--click' ) && 'Pause carousel' === $play->get_attribute( 'aria-label' ), 'autoplay has a labelled pause/play button' );
ok( substr_count( $html, '<div' ) === substr_count( $html, '</div>' ), 'layout 4 markup with controls is balanced' );

// Archive page path: settings come from options and loop-end closes what loop-start opened.
$options = array(
	'ohmylms_archive_page_layout'       => 'grid',
	'ohmylms_archive_page_layout_style' => 'grid-style4',
	'ohmylms_columns_per_row'           => 2,
);
foreach ( $options as $name => $value ) {
	add_filter(
		'pre_option_' . $name,
		static function () use ( $value ) {
			return $value;
		}
	);
}
$html = ohmylms_course_loop_start( false ) . '<div class="course-card"></div>' . ohmylms_course_loop_end( false );
ok( ! Carousel::is_open(), 'loop-end closes the carousel opened by loop-start' );
ok( 'Courses' === tag_with_class( $html, 'ohmylms-carousel' )->get_attribute( 'aria-label' ), 'an unnamed carousel falls back to "Courses"' );
ok( false !== strpos( $html, '--ohmylms-carousel-slides:2;--ohmylms-carousel-slides-lg:2;--ohmylms-carousel-slides-md:2;--ohmylms-carousel-slides-sm:1' ), 'archive columns map to custom properties' );
ok( substr_count( $html, '<div' ) === substr_count( $html, '</div>' ), 'archive carousel markup is balanced' );

// Grid layouts 1/2 are not carousels.
$html = render_course_list( 'layout="grid" layout_style="grid-style1" columns="3" posts_per_page="3"' );
ok( false === strpos( $html, 'ohmylms/carousel' ) && false === strpos( $html, 'ohmylms-carousel-track' ), 'layout 1 renders a plain grid' );

// Context clamping.
ok( 4 === Carousel::context( array( 'slidesToShow' => 'no' ) )['slidesToShow'], 'a junk column count falls back to 4' );
ok( 6 === Carousel::context( array( 'slidesToShow' => 12 ) )['slidesToShow'], 'slides per view are capped at 6' );
ok( 1000 === Carousel::context( array( 'autoplaySpeed' => 10 ) )['autoplaySpeed'], 'autoplay speed has a 1s floor' );
ok( false === Carousel::context( array( 'arrows' => false ) )['arrows'], 'arrows can be turned off' );

// No consumer or shipped file still references Slick.
foreach ( array(
	'includes/Assets/FrontendAssets.php',
	'includes/Blocks/BlocksManager.php',
	'includes/Bricks/Elements/CourseListElement.php',
	'includes/Elementor/Widgets/CheckoutWidget.php',
	'includes/Elementor/Widgets/CourseListWidget.php',
	'includes/Student/Hub.php',
	'templates/loop/loop-start.php',
	'templates/loop/loop-end.php',
) as $file ) {
	ok( false === stripos( (string) file_get_contents( OHMYLMS_DIR . '/' . $file ), 'slick' ), "$file does not mention Slick" );
}
foreach ( array( 'assets/dist/frontend/slick.js', 'assets/src/runtimes/dist/frontend/slick.js', 'assets/src/frontend/scss/archive/_slick.min.scss', 'assets/js/wpbakery-frontend.js' ) as $file ) {
	ok( ! file_exists( OHMYLMS_DIR . '/' . $file ), "$file is deleted" );
}
$manifest = json_decode( (string) file_get_contents( OHMYLMS_DIR . '/assets/src/manifest.json' ), true );
ok( array() === array_filter( array_column( $manifest['assets'], 'output' ), static fn( $output ) => false !== stripos( $output, 'slick' ) ), 'the asset manifest builds no Slick output' );
ok( in_array( 'assets/dist/frontend/slick.js', $manifest['retiredAssets'], true ), 'slick.js is a retired asset' );
foreach ( array( 'catalog', 'course', 'rtl' ) as $module ) {
	ok( false === stripos( (string) file_get_contents( OHMYLMS_DIR . "/assets/css/frontend/$module.css" ), 'slick' ), "$module.css has no Slick rules" );
}
ok( false !== strpos( (string) file_get_contents( OHMYLMS_DIR . '/assets/css/frontend/catalog.css' ), 'scroll-snap-type:x mandatory' ), 'catalog.css styles the scroll-snap track' );

echo "Course carousel tests passed ($checks checks).\n";
