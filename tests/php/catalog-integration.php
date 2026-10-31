<?php
/**
 * Course catalogue (batch L3): search, filters, sort and load more are GET parameters rendered on
 * the server, with `ohmylms/catalog` directives, router regions and state.
 *
 * Boots the local site (800.local), creates three published test courses (deleted again at the
 * end), renders the course list shortcode (which the block, Elementor, Bricks and WPBakery
 * elements delegate to) with query arguments, and checks: the URL contract and its canonical
 * query string (the same string tests/js/catalog.test.mjs expects from the module), server-side
 * filtering, sorting and cumulative load-more pages, the directives, regions and state, theme
 * overrides with the pre-L3 markup, the module registration, that the legacy admin-ajax actions
 * list the same courses, and that the live archive renders the filtered list for the same URL.
 *
 * Usage (set OHMYLMS_CATALOG_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/catalog-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.Security.NonceVerification, WordPress.WP.AlternativeFunctions

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_CATALOG_TEST' ) ) {
	exit( "Set OHMYLMS_CATALOG_TEST=1 to run the course catalogue tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$host                   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['HTTP_HOST']   = $host;
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Extensions\Catalog;
use OhMyLMS\Extensions\Interactivity;

$checks   = 0;
$warnings = array();
set_error_handler(
	static function ( $severity, $message, $file, $line ) use ( &$warnings ) {
		$file = wp_normalize_path( $file );
		foreach ( array( 'includes/Extensions/Catalog.php', 'templates/archive-course.php', 'templates/filters/', 'templates/loop/load-more.php' ) as $own ) {
			if ( false !== strpos( $file, $own ) ) {
				$warnings[] = "$message in $file:$line";
			}
		}
		return false;
	}
);

/**
 * Record one check.
 *
 * @param bool   $condition Passes.
 * @param string $message   Failure message.
 * @throws RuntimeException When the check fails.
 */
function ok( $condition, $message ) {
	global $checks;
	if ( ! $condition ) {
		throw new RuntimeException( 'FAILED: ' . $message );
	}
	++$checks;
}

/**
 * Processor on the first tag carrying a class (or `#id`), or null.
 *
 * @param string $html     HTML.
 * @param string $selector Class name or `#id`.
 * @param string $tag_name Optional tag name.
 * @return WP_HTML_Tag_Processor|null
 */
function find_tag( $html, $selector, $tag_name = '' ) {
	$tags = new WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag( $tag_name ? array( 'tag_name' => $tag_name ) : array() ) ) {
		$match = 0 === strpos( $selector, '#' ) ? substr( $selector, 1 ) === $tags->get_attribute( 'id' ) : $tags->has_class( $selector );
		if ( $match ) {
			return $tags;
		}
	}
	return null;
}

/**
 * Processor on the first tag with an attribute value, or null.
 *
 * @param string $html  HTML.
 * @param string $name  Attribute.
 * @param string $value Value.
 * @return WP_HTML_Tag_Processor|null
 */
function find_attribute( $html, $name, $value ) {
	$tags = new WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag() ) {
		if ( $value === $tags->get_attribute( $name ) ) {
			return $tags;
		}
	}
	return null;
}

/**
 * Course card ids (`course-<id>`) in document order.
 *
 * @param string $html HTML.
 * @return int[]
 */
function card_ids( $html ) {
	preg_match_all( '/<div id="course-(\d+)" class="course-card/', $html, $matches );
	return array_map( 'intval', $matches[1] );
}

/**
 * Render the course list shortcode for a request URL.
 *
 * @param string $query Query string of the request.
 * @param string $atts  Shortcode attributes.
 * @return string
 */
function render_list( $query, $atts ) {
	$_GET                   = array();
	$_SERVER['REQUEST_URI'] = '/courses-test/' . ( '' !== $query ? '?' . $query : '' );
	wp_parse_str( $query, $_GET );
	$instances = new ReflectionProperty( Catalog::class, 'instances' );
	$instances->setAccessible( true );
	$instances->setValue( null, 0 );
	$html = do_shortcode( '[ohmylms_course_list ' . $atts . ']' );
	$_GET = array();
	return $html;
}

$token   = 'Zqcatalog' . wp_generate_password( 6, false );
$courses = array();
$exit    = 0;
try {
	wp_set_current_user( 0 );

	// Three courses sharing a search token: A paid/beginner/30, B free/beginner/10, C paid/expert without a price.
	$specs = array(
		'a' => array( 'paid', 'beginner', '30', '4.5', '-3 days' ),
		'b' => array( 'free', 'beginner', '10', '', '-2 days' ),
		'c' => array( 'paid', 'expert', '', '3', '-1 days' ),
	);
	foreach ( $specs as $key => $spec ) {
		$id = wp_insert_post(
			array(
				'post_type'    => OHMYLMS_COURSE_CPT,
				'post_status'  => 'publish',
				'post_title'   => "$token course " . strtoupper( $key ),
				'post_content' => 'Catalogue test course.',
				'post_date'    => gmdate( 'Y-m-d H:i:s', strtotime( $spec[4] ) ),
			),
			true
		);
		ok( ! is_wp_error( $id ), 'test course is created' );
		$courses[ $key ] = (int) $id;
		update_post_meta( $id, '_price_type', $spec[0] );
		update_post_meta( $id, '_level', $spec[1] );
		if ( '' !== $spec[2] ) {
			update_post_meta( $id, '_price', $spec[2] );
		}
		if ( '' !== $spec[3] ) {
			update_post_meta( $id, '_average_rating', $spec[3] );
		}
	}
	list( $a, $b, $c ) = array_values( $courses );

	// ---- URL contract ----------------------------------------------------------------------
	$filters = Catalog::filters(
		array(
			'course_search'     => ' linear equations ',
			'course_curriculum' => array( 'c27', 'c150', 'c27', 'bad', 't3' ),
			'course_level'      => array( 'expert', 'beginner' ),
			'course_price'      => array( 'paid', 'gratis' ),
			'course_sort'       => 'rating',
			'paged'             => '2',
		)
	);
	ok( 'linear equations' === $filters['search'], 'search is trimmed' );
	ok( array( 'c27', 'c150' ) === $filters['curriculum'], 'curriculum keeps valid c<id> slugs once: ' . wp_json_encode( $filters['curriculum'] ) );
	ok( array( 'paid' ) === $filters['price'], 'unknown price types are dropped' );
	ok( 'rating' === $filters['sort'] && 2 === $filters['page'], 'sort and page are read' );
	ok( 'course_search=linear+equations&course_curriculum%5B%5D=c150&course_curriculum%5B%5D=c27&course_level%5B%5D=beginner&course_level%5B%5D=expert&course_price%5B%5D=paid&course_sort=rating&paged=2' === Catalog::query_string( $filters ), 'canonical query string matches the module: ' . Catalog::query_string( $filters ) );
	ok( 'course_search=a+b*c%7Ed%26e' === Catalog::query_string( Catalog::filters( array( 'course_search' => 'a b*c~d&e' ) ) ), 'search terms use form encoding, as URLSearchParams does' );
	ok( 50 === Catalog::filters( array( 'paged' => '9999' ) )['page'], 'pages are capped' );
	ok( '' === Catalog::filters( array( 'course_sort' => 'menu_order' ) )['sort'], 'unknown sorts are dropped' );
	ok( 'http://x.test/?page_id=4&course_price%5B%5D=free' === Catalog::url( array( 'price' => array( 'free' ) ), 'http://x.test/?page_id=4' ), 'URLs keep the base query' );
	$_SERVER['REQUEST_URI'] = '/course/page/3/?page_id=4&course_level%5B%5D=expert&paged=3';
	ok( Catalog::base_url() === 'http://' . $host . '/course/?page_id=4', 'base URL drops catalogue parameters and /page/N/: ' . Catalog::base_url() );
	ok( array( 'page_id' => '4' ) === Catalog::base_fields(), 'other query arguments become hidden form fields' );
	ok( 'course_curriculum[]' === Catalog::field( 'category' ) && 'course_level[]' === Catalog::field( 'difficulty_level' ) && 'course_price[]' === Catalog::field( 'price_type' ) && 'course_bundle[]' === Catalog::field( 'tag' ), 'filter types map to field names' );
	ok( ! array_intersect( array( 'course_category', 'course_tag', 's', 'paged' ), Catalog::PARAMS ), 'parameters avoid the course_category/course_tag taxonomy query vars and core search' );

	// ---- Module registration ---------------------------------------------------------------
	Interactivity::register();
	$registered = new ReflectionProperty( WP_Script_Modules::class, 'registered' );
	$registered->setAccessible( true );
	$modules = (array) $registered->getValue( wp_script_modules() );
	ok( isset( $modules['ohmylms/catalog'] ) && false !== strpos( $modules['ohmylms/catalog']['src'], 'assets/interactivity/catalog.js' ), 'ohmylms/catalog is registered' );
	$dependencies = array();
	foreach ( (array) $modules['ohmylms/catalog']['dependencies'] as $dependency ) {
		$dependencies[ $dependency['id'] ] = $dependency['import'];
	}
	ok( isset( $dependencies['@wordpress/interactivity'], $dependencies['ohmylms/interactivity'] ), 'depends on the runtime and the SDK' );
	ok( 'dynamic' === ( $dependencies['@wordpress/interactivity-router'] ?? '' ), 'the router is a dynamic dependency' );

	// ---- Server-side filtering through the course list -------------------------------------
	$atts = 'layout="grid" layout_style="grid-style1" show_filter="yes" show_search="yes" show_sort="yes" posts_per_page="2"';
	$html = render_list( 'course_search=' . $token, $atts );
	ok( array( $c, $b ) === card_ids( $html ), 'search lists page 1 (2 per page, newest first): ' . wp_json_encode( card_ids( $html ) ) );
	$state = wp_interactivity_state( 'ohmylms/catalog' );
	ok( $token === $state['filters']['search'] && false === $state['loading'], 'state carries the request filters' );

	$html = render_list( 'course_search=' . $token . '&course_level%5B%5D=beginner', $atts );
	ok( array( $b, $a ) === card_ids( $html ), 'level filter: ' . wp_json_encode( card_ids( $html ) ) );
	$html = render_list( 'course_search=' . $token . '&course_level%5B%5D=all', $atts );
	ok( 2 === count( card_ids( $html ) ), '"All levels" does not filter' );
	$html = render_list( 'course_search=' . $token . '&course_price%5B%5D=free', $atts );
	ok( array( $b ) === card_ids( $html ), 'price filter' );
	$html = render_list( 'course_search=' . $token . '&course_sort=max_price&paged=2', $atts );
	ok( array( $a, $b, $c ) === card_ids( $html ), 'price high to low lists the course without a price last: ' . wp_json_encode( card_ids( $html ) ) );
	$html = render_list( 'course_search=' . $token . '&course_sort=rating&paged=2', $atts );
	ok( array( $a, $c, $b ) === card_ids( $html ), 'top rated keeps unrated courses: ' . wp_json_encode( card_ids( $html ) ) );
	$html = render_list( 'course_search=' . $token . '&course_curriculum%5B%5D=c999999', $atts );
	ok( array() === card_ids( $html ), 'an unknown curriculum item lists nothing' );
	ok( false !== strpos( $html, 'no-courses-found' ) && null !== find_tag( $html, 'ohmylms-course-sidebar' ), 'an empty filtered list keeps the filters and shows an empty state' );
	ok( preg_match( '/class="ohmylms-catalog-count[^"]*"[^>]*>No courses found\.<\/p>/', $html ), 'the live count says nothing was found' );

	// ---- Load more: paged=N lists pages 1..N --------------------------------------------------
	$html   = render_list( 'course_search=' . $token, $atts );
	$area   = find_tag( $html, 'ohmylms-course-loadmore-area' );
	$button = find_tag( $html, 'ohmylms-course-loadmore-btn' );
	ok( null !== $area && 'ohmylms/catalog-more' === $area->get_attribute( 'data-wp-router-region' ) && 'ohmylms/catalog' === $area->get_attribute( 'data-wp-interactive' ) && null === $area->get_attribute( 'hidden' ), 'load-more area is a visible router region' );
	ok( null !== $button && 'submit' === $button->get_attribute( 'type' ) && 'paged' === $button->get_attribute( 'name' ) && '2' === $button->get_attribute( 'value' ) && 'ohmylms-catalog-form' === $button->get_attribute( 'form' ), 'load more submits paged=2 with the filter form (no JavaScript)' );
	ok( 'actions.loadMore' === $button->get_attribute( 'data-wp-on--click' ) && '1' === $button->get_attribute( 'data-paged' ) && '2' === $button->get_attribute( 'data-total' ) && 'state.loading' === $button->get_attribute( 'data-wp-bind--disabled' ), 'load more directives and page data' );
	ok( false !== strpos( $html, 'Showing 2 of 3 courses.' ), 'the count reports shown and found courses' );
	$html = render_list( 'course_search=' . $token . '&paged=2', $atts );
	ok( array( $c, $b, $a ) === card_ids( $html ), 'paged=2 keeps page 1 and adds page 2' );
	$area = find_tag( $html, 'ohmylms-course-loadmore-area' );
	ok( true === $area->get_attribute( 'hidden' ) && null === find_tag( $html, 'ohmylms-course-loadmore-btn' ), 'the last page hides the area but keeps the region' );
	ok( false !== strpos( $html, 'Showing all 3 courses.' ), 'the count on the last page' );

	// ---- Directives, form controls and regions ---------------------------------------------
	$query = 'course_search=' . $token . '&course_level%5B%5D=expert&course_price%5B%5D=paid&course_sort=review';
	$html  = render_list( $query, $atts );
	$root  = find_tag( $html, 'ohmylms-catalog' );
	ok( null !== $root && $root->has_class( 'ohmylms-container' ) && 'ohmylms/catalog' === $root->get_attribute( 'data-wp-interactive' ), 'the container is the catalogue root and keeps .ohmylms-container' );
	$context  = json_decode( (string) $root->get_attribute( 'data-wp-context' ), true );
	$expected = array(
		'form'    => 'ohmylms-catalog-form',
		'heading' => 'ohmylms-catalog-heading',
	);
	ok( $expected === $context, 'root context names the form and heading' );
	foreach ( array(
		'data-wp-on--change'              => 'actions.change',
		'data-wp-on--input'               => 'actions.input',
		'data-wp-on--submit'              => 'actions.submit',
		'data-wp-on--keydown'             => 'actions.keydown',
		'data-wp-on--mouseover'           => 'actions.cardOver',
		'data-wp-on-window--popstate'     => 'actions.popstate',
		'data-wp-on-window--resize' => 'callbacks.reposition',
	) as $name => $value ) {
		ok( $value === $root->get_attribute( $name ), "root $name" );
	}
	$form = find_tag( $html, '#ohmylms-catalog-form', 'form' );
	ok( null !== $form && 'get' === $form->get_attribute( 'method' ) && 0 === strpos( (string) $form->get_attribute( 'action' ), 'http://' ), 'filters submit as a GET form without JavaScript' );
	ok( false !== strpos( $html, '<noscript>' ) && null !== find_tag( $html, 'ohmylms-catalog-apply' ), 'a no-JavaScript Apply button' );
	$results = find_tag( $html, 'ohmylms-catalog-results' );
	ok( 'ohmylms/catalog-results' === $results->get_attribute( 'data-wp-router-region' ) && 'ohmylms/catalog' === $results->get_attribute( 'data-wp-interactive' ) && 'state.loading' === $results->get_attribute( 'data-wp-bind--aria-busy' ), 'results are a router region with a busy state' );
	$heading = find_tag( $html, '#ohmylms-catalog-heading' );
	ok( 'H2' === $heading->get_tag() && '-1' === $heading->get_attribute( 'tabindex' ), 'the results heading is a focus target' );
	$count = find_tag( $html, 'ohmylms-catalog-count' );
	ok( 'status' === $count->get_attribute( 'role' ) && 'polite' === $count->get_attribute( 'aria-live' ), 'the result count is a live region' );
	ok( strpos( $html, 'ohmylms-catalog-count' ) > strpos( $html, 'data-wp-router-region="ohmylms/catalog-results"' ), 'the count is inside the results region' );
	$box = find_attribute( $html, 'value', 'expert' );
	ok( null !== $box && 'course_level[]' === $box->get_attribute( 'name' ) && null !== $box->get_attribute( 'checked' ) && 'ohmylms-catalog-form' === $box->get_attribute( 'form' ), 'level checkbox is a real, checked form control' );
	$box = find_attribute( $html, 'value', 'beginner' );
	ok( null === $box->get_attribute( 'checked' ), 'other boxes are unchecked' );
	ok( null === $box->get_attribute( 'aria-required' ), 'filters are not marked required' );
	$search = find_attribute( $html, 'name', 'course_search' );
	ok( $token === $search->get_attribute( 'value' ) && 'search' === $search->get_attribute( 'type' ) && 'ohmylms-catalog-form' === $search->get_attribute( 'form' ), 'search box keeps the term' );
	ok( false !== strpos( $html, 'for="ohmylms-catalog-form-search"' ), 'search box is labelled' );
	ok( preg_match( '/<option value="review"\s+selected/', $html ), 'sort keeps the selection' );
	$aside = find_tag( $html, 'ohmylms-course-sidebar' );
	ok( 'ohmylms-course-filters' === $aside->get_attribute( 'id' ) && 'ohmylms/catalog' === $aside->get_attribute( 'data-wp-interactive' ), 'filter sidebar is the drawer panel' );
	$outer = find_tag( $html, 'ohmylms-course-outer' );
	ok( 'ohmylms/ui' === $outer->get_attribute( 'data-wp-interactive' ) && 'context.drawerOpen' === $outer->get_attribute( 'data-wp-class--open-sidebar' ) && 'actions.drawerOutside' === $outer->get_attribute( 'data-wp-on-document--click' ), 'mobile filter drawer uses the ohmylms/ui drawer' );
	$toggle = find_tag( $html, 'filter-hamburger' );
	ok( 'ohmylms/ui::actions.drawerToggle' === $toggle->get_attribute( 'data-wp-on--click' ) && 'ohmylms-course-filters' === $toggle->get_attribute( 'aria-controls' ), 'filter toggle opens the drawer' );
	ok( 'actions.closeFilters' === find_tag( $html, 'ohmylms-close-filter' )->get_attribute( 'data-wp-on--click' ), 'close button closes the drawer' );
	ok( 'actions.clear' === find_tag( $html, 'clear-filter' )->get_attribute( 'data-wp-on--click' ), 'clear all' );
	ok( substr_count( $html, '<div' ) === substr_count( $html, '</div>' ), 'markup is balanced' );

	// ---- Carousel layout: curriculum buttons are links, the region re-renders the carousel ----
	$items = \OhMyLMS\Curriculum\Placement::public_items();
	if ( $items ) {
		add_filter(
			'pre_option_ohmylms_archive_page_row',
			static function () {
				return array(
					array(
						'row_heading'          => 'All courses',
						'row_display_criteria' => 'all',
					),
				);
			}
		);
		$slug = $items[0]['slug'];
		$html = render_list( 'course_curriculum%5B%5D=' . $slug, 'layout="grid" layout_style="grid-style3" is_enable_category="yes" columns="3" posts_per_page="5"' );
		$li   = find_attribute( $html, 'data-slug', $slug );
		ok( null !== $li && 'LI' === $li->get_tag() && $li->has_class( 'active' ) && 'state.categoryActive' === $li->get_attribute( 'data-wp-class--active' ), 'the selected curriculum button is active' );
		ok( false !== strpos( $html, 'href="http://' . $host . '/courses-test/?course_curriculum%5B%5D=' . $slug . '"' ), 'curriculum buttons are real links' );
		ok( false !== strpos( $html, 'data-wp-on--click="actions.category"' ), 'curriculum links are followed by the router' );
		$all = find_attribute( $html, 'data-slug', 'all' );
		ok( ! $all->has_class( 'active' ), '"All" is not active while an item is selected' );
		$region = strpos( $html, 'data-wp-router-region="ohmylms/catalog-results"' );
		$track  = strpos( $html, 'data-wp-interactive="ohmylms/carousel"' );
		ok( false !== $region && false !== $track && $track > $region, 'the carousel renders inside the results region, so the router swap hydrates it' );
		ok( null === find_tag( $html, 'ohmylms-course-loadmore-area' ), 'carousel layouts have no load more' );
		foreach ( card_ids( $html ) as $id ) {
			ok( in_array( $id, \OhMyLMS\Curriculum\Placement::narrow_query( array(), \OhMyLMS\Curriculum\Placement::ids_from_slugs( array( $slug ), 'item' ) )['post__in'], true ), "card $id belongs to $slug" );
		}
	}

	// ---- Theme overrides with the pre-L3 markup ---------------------------------------------
	$_GET   = array(
		'course_level' => array( 'expert' ),
		'course_sort'  => 'rating',
	);
	$legacy = '<div class="ohmylms-filter-accordion"><label class="ohmylms-checkbox"><input type="checkbox" name="ohmylms-filter-checkbox" value="" data-type="difficulty_level" data-slug="expert" aria-required="true"><span class="checkedbox" tabindex="0" data-target="x"></span></label></div>';
	$decor  = apply_filters( 'ohmylms_template_html', $legacy, 'filters/filters.php', array() );
	$island = find_tag( $decor, 'ohmylms-filter-accordion' );
	$box    = find_attribute( $decor, 'data-slug', 'expert' );
	ok( 'ohmylms/catalog' === $island->get_attribute( 'data-wp-interactive' ) && 'actions.change' === $island->get_attribute( 'data-wp-on--change' ), 'an override outside a catalogue root becomes its own island' );
	ok( 'course_level[]' === $box->get_attribute( 'name' ) && 'expert' === $box->get_attribute( 'value' ) && true === $box->get_attribute( 'checked' ) && null === $box->get_attribute( 'aria-required' ), 'legacy checkboxes get the URL field name and state' );
	$decor = apply_filters( 'ohmylms_template_html', '<div class="course-sortbox"><select name="course-sort" class="course-sort"><option value="date">Date</option><option value="rating">Top</option></select></div>', 'filters/course-sort.php', array() );
	ok( false !== strpos( $decor, 'name="course_sort"' ) && false !== strpos( $decor, '<option selected value="rating">' ) && false !== strpos( $decor, '<option value="date">' ), 'legacy sort select is renamed and selected: ' . $decor );
	$decor = apply_filters( 'ohmylms_template_html', '<div class="course-searchbox"><input type="search" name="course-search" placeholder="Search"></div>', 'filters/course-search.php', array() );
	ok( false !== strpos( $decor, 'name="course_search"' ) && false !== strpos( $decor, 'aria-label="Search courses"' ), 'legacy search box is renamed and labelled' );
	$decor = apply_filters( 'ohmylms_template_html', '<div class="ohmylms-category-type-button"><ul><li class="active ohmylms-category-filter" data-slug="all"><a href="#" data-slug="all">All</a></li></ul></div>', 'filters/category-type-button.php', array() );
	ok( false !== strpos( $decor, 'data-wp-on--click="actions.category"' ) && false === strpos( $decor, 'href="#"' ), 'legacy curriculum buttons get links and the action' );
	$decor  = apply_filters( 'ohmylms_template_html', '<div class="ohmylms-course-loadmore-area"><button class="ohmylms-course-loadmore-btn ohmylms-button" type="button" data-paged="1" data-total="3">More</button></div>', 'loop/load-more.php', array() );
	$area   = find_tag( $decor, 'ohmylms-course-loadmore-area' );
	$button = find_tag( $decor, 'ohmylms-course-loadmore-btn' );
	ok( 'ohmylms/catalog-more' === $area->get_attribute( 'data-wp-router-region' ) && 'actions.loadMore' === $button->get_attribute( 'data-wp-on--click' ) && '2' === $button->get_attribute( 'value' ), 'legacy load more becomes a region with the action' );
	$_GET = array();

	// ---- The legacy admin-ajax actions list the same courses --------------------------------
	$ajax = static function ( $body ) {
		$response = wp_remote_post(
			admin_url( 'admin-ajax.php' ),
			array(
				'body'    => $body,
				'timeout' => 30,
			)
		);
		ok( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ), 'admin-ajax answers: ' . ( is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_response_code( $response ) ) );
		return wp_remote_retrieve_body( $response );
	};
	$body = $ajax(
		array(
			'action'      => 'ohmylms_search_filter',
			'nonce'       => wp_create_nonce( 'search_filter_nonce' ),
			'page'        => 1,
			'search_term' => $token,
			'level_slug'  => array( 'beginner' ),
			'sort_by'     => 'min_price',
			'filter_type' => 'filter',
		)
	);
	$html = render_list( 'course_search=' . $token . '&course_level%5B%5D=beginner&course_sort=min_price', $atts );
	ok( array( $b, $a ) === card_ids( $body ) && card_ids( $body ) === card_ids( $html ), 'ohmylms_search_filter matches the server-rendered list: ' . wp_json_encode( card_ids( $body ) ) );
	$body = $ajax(
		array(
			'action'         => 'ohmylms_loadmore',
			'nonce'          => wp_create_nonce( 'load_more_nonce' ),
			'page'           => 2,
			'posts_per_page' => 2,
			'search_term'    => $token,
			'filter_type'    => 'filter',
		)
	);
	ok( array( $a ) === card_ids( $body ), 'ohmylms_loadmore still returns page 2 as a fragment: ' . wp_json_encode( card_ids( $body ) ) );

	// ---- The live archive renders the filtered list for the same URL --------------------------
	$url      = home_url( '/course/?course_search=' . rawurlencode( $token ) . '&course_level%5B%5D=expert' );
	$response = wp_remote_get( $url, array( 'timeout' => 30 ) );
	$page     = wp_remote_retrieve_body( $response );
	ok( 200 === wp_remote_retrieve_response_code( $response ), "archive answers 200 for $url" );
	ok( array( $c ) === card_ids( $page ), 'the archive filters on the server: ' . wp_json_encode( card_ids( $page ) ) );
	ok( false !== strpos( $page, 'data-wp-router-region="ohmylms/catalog-results"' ) && false !== strpos( $page, '"ohmylms/catalog"' ), 'the archive renders the region and the module' );
	ok( false !== strpos( $page, '"@wordpress/interactivity-router"' ), 'the import map can resolve the router' );
	ok( ! preg_match( '/<b>(Warning|Notice|Deprecated|Fatal error)<\/b>/', $page ), 'no PHP notices in the page' );
	foreach ( array( 'course_level%5B%5D=expert', 'paged=2' ) as $query ) {
		$response = wp_remote_get(
			home_url( '/course/?' . $query ),
			array(
				'timeout'     => 30,
				'redirection' => 0,
			)
		);
		ok( 200 === wp_remote_retrieve_response_code( $response ), "/course/?$query is not redirected (canonical redirects would rewrite [] and drop paged)" );
	}

	// ---- jQuery is gone from the catalogue ----------------------------------------------------
	$script = (string) file_get_contents( OHMYLMS_DIR . '/assets/src/frontend/js/ohmylms.js' );
	foreach ( array( 'ohmylms_loadmore', 'ohmylms_search_filter', 'filter-hamburger', 'ohmylms-category-type-button', 'ohmylms-course-card-popup', 'course-search', 'checkedbox' ) as $needle ) {
		ok( false === strpos( $script, $needle ), "ohmylms.js no longer handles $needle" );
	}

	ok( array() === $warnings, 'no PHP warnings from the catalogue: ' . implode( '; ', $warnings ) );
	echo "Catalogue tests passed ($checks checks).\n";
} catch ( Throwable $e ) {
	echo $e->getMessage(), ' (line ', $e->getLine(), ")\n", $e->getTraceAsString(), "\n";
	$exit = 1;
} finally {
	foreach ( $courses as $id ) {
		wp_delete_post( $id, true );
	}
}
exit( $exit );
