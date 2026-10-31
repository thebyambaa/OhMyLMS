<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class file; see docs/CODING-STANDARDS.md.
/**
 * Course catalogue on the Interactivity API (`ohmylms/catalog`): the course archive and the course
 * list shortcode with its block, Elementor, Bricks and WPBakery elements. Search, filters, sort,
 * curriculum buttons and "load more" are GET parameters, so the server renders every list for its
 * URL (with or without JavaScript); the module swaps the result regions with
 * `@wordpress/interactivity-router`.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Extensions;

use OhMyLMS\Curriculum\Placement;

defined( 'ABSPATH' ) || exit;

/** Catalogue URL contract, shared query builder, server state and template decoration. */
final class Catalog {

	const MODULE = 'ohmylms/catalog';

	/** Query parameters by filter key; lists are sent as `name[]`. Mirrors PARAMS in assets/interactivity/catalog/catalog.js. */
	const PARAMS = array(
		'search'     => 'course_search',
		'curriculum' => 'course_curriculum',
		'bundle'     => 'course_bundle',
		'level'      => 'course_level',
		'price'      => 'course_price',
		'sort'       => 'course_sort',
	);

	/** Multi-value filters, in URL order. */
	const LISTS = array( 'curriculum', 'bundle', 'level', 'price' );

	/** Sort key => array( meta key, order ); an empty meta key orders by date. */
	const SORTS = array(
		'date'      => array( '', 'DESC' ),
		'max_price' => array( '_price', 'DESC' ),
		'min_price' => array( '_price', 'ASC' ),
		'rating'    => array( '_average_rating', 'DESC' ),
		'review'    => array( '_review_count', 'DESC' ),
	);

	const DEFAULT_SORT = 'date';
	const PRICES       = array( 'paid', 'free' );
	const MAX_PAGES    = 50;
	const MAX_POSTS    = 500;
	const MAX_SEARCH   = 100;
	const MAX_VALUES   = 50;

	/** `data-type` of the filter checkboxes (filters/filters.php) => filter key. */
	const TYPES = array(
		'category'         => 'curriculum',
		'tag'              => 'bundle',
		'difficulty_level' => 'level',
		'price_type'       => 'price',
	);

	/** Templates decorated for theme overrides copied before the catalogue moved to the router. */
	const TEMPLATES = array(
		'filters/filters.php',
		'filters/filter-header.php',
		'filters/course-search.php',
		'filters/course-sort.php',
		'filters/search-sort.php',
		'filters/category-type-button.php',
		'loop/load-more.php',
	);

	/**
	 * Catalogue being rendered (set by begin()).
	 *
	 * @var array|null
	 */
	private static $current = null;

	/**
	 * Catalogues rendered in this request.
	 *
	 * @var int
	 */
	private static $instances = 0;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'pre_get_posts', array( __CLASS__, 'main_query' ), 20 );
		add_filter( 'redirect_canonical', array( __CLASS__, 'canonical' ) );
		add_filter( 'ohmylms_template_html', array( __CLASS__, 'template' ), 25, 2 );
	}

	/**
	 * Keep catalogue URLs as they are. The canonical redirect would rewrite `name[]` to `name[0]`
	 * (an extra request per navigation) and drop `paged` on the archive, whose main query lists
	 * pages 1..N as one page.
	 *
	 * @param string|false $redirect_url Canonical URL.
	 * @return string|false
	 */
	public static function canonical( $redirect_url ) {
		global $wp_query;
		$params = array_intersect( array_keys( (array) $_GET ), array_values( self::PARAMS ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Parameter names only.
		$pages  = $wp_query instanceof \WP_Query ? (int) $wp_query->get( 'ohmylms_catalog_pages' ) : 0;
		return $params || $pages > 1 ? false : $redirect_url;
	}

	// ---- URL contract -----------------------------------------------------------------------

	/**
	 * No filters.
	 *
	 * @return array{search:string,curriculum:string[],bundle:string[],level:string[],price:string[],sort:string,page:int}
	 */
	public static function empty_filters() {
		return array(
			'search'     => '',
			'curriculum' => array(),
			'bundle'     => array(),
			'level'      => array(),
			'price'      => array(),
			'sort'       => '',
			'page'       => 1,
		);
	}

	/**
	 * Filters from GET parameters (or any array using the same names). Unknown values are dropped.
	 *
	 * @param array|null $source Parameters, unslashed; null reads `$_GET`.
	 * @return array See empty_filters().
	 */
	public static function filters( $source = null ) {
		if ( null === $source ) {
			$source = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public, read-only listing parameters.
			// A page holding a course list may have been redirected from `?paged=N` to `/page/N/`.
			if ( empty( $source['paged'] ) && did_action( 'parse_query' ) && get_query_var( 'paged' ) > 1 ) {
				$source['paged'] = get_query_var( 'paged' );
			}
		}
		$source  = is_array( $source ) ? $source : array();
		$filters = self::empty_filters();

		$search = isset( $source[ self::PARAMS['search'] ] ) && is_scalar( $source[ self::PARAMS['search'] ] ) ? (string) $source[ self::PARAMS['search'] ] : '';
		$search = trim( sanitize_text_field( $search ) );
		if ( '' !== $search ) {
			$filters['search'] = function_exists( 'mb_substr' ) ? mb_substr( $search, 0, self::MAX_SEARCH ) : substr( $search, 0, self::MAX_SEARCH );
		}

		foreach ( self::LISTS as $key ) {
			$values = isset( $source[ self::PARAMS[ $key ] ] ) ? (array) $source[ self::PARAMS[ $key ] ] : array();
			foreach ( $values as $value ) {
				$value = is_scalar( $value ) ? sanitize_key( (string) $value ) : '';
				if ( '' === $value || in_array( $value, $filters[ $key ], true ) || count( $filters[ $key ] ) >= self::MAX_VALUES || ! self::valid( $key, $value ) ) {
					continue;
				}
				$filters[ $key ][] = $value;
			}
		}

		$sort = isset( $source[ self::PARAMS['sort'] ] ) && is_scalar( $source[ self::PARAMS['sort'] ] ) ? sanitize_key( (string) $source[ self::PARAMS['sort'] ] ) : '';
		if ( isset( self::SORTS[ $sort ] ) ) {
			$filters['sort'] = $sort;
		}

		$page            = isset( $source['paged'] ) && is_scalar( $source['paged'] ) ? absint( $source['paged'] ) : 1;
		$filters['page'] = self::clamp_page( $page );
		return $filters;
	}

	/**
	 * Map the legacy admin-ajax fields (`search_term`, `category_slug[]`, `tag_slug[]`,
	 * `level_slug[]`, `price_slug[]`, `sort_by`) to filters.
	 *
	 * @param array $post Unslashed POST data.
	 * @return array See empty_filters().
	 */
	public static function legacy_filters( array $post ) {
		$map    = array(
			'search_term'   => self::PARAMS['search'],
			'category_slug' => self::PARAMS['curriculum'],
			'tag_slug'      => self::PARAMS['bundle'],
			'level_slug'    => self::PARAMS['level'],
			'price_slug'    => self::PARAMS['price'],
			'sort_by'       => self::PARAMS['sort'],
		);
		$source = array();
		foreach ( $map as $legacy => $name ) {
			if ( isset( $post[ $legacy ] ) ) {
				$source[ $name ] = $post[ $legacy ];
			}
		}
		return self::filters( $source );
	}

	/**
	 * Whether a list value can match anything.
	 *
	 * @param string $key   Filter key.
	 * @param string $value Sanitized value.
	 * @return bool
	 */
	private static function valid( $key, $value ) {
		switch ( $key ) {
			case 'curriculum':
				return 1 === preg_match( '/^c\d+$/', $value );
			case 'bundle':
				return 1 === preg_match( '/^t\d+$/', $value );
			case 'price':
				return in_array( $value, self::PRICES, true );
			default:
				return true;
		}
	}

	/**
	 * Clamp a page number to 1..MAX_PAGES.
	 *
	 * @param mixed $page Page.
	 * @return int
	 */
	private static function clamp_page( $page ) {
		return max( 1, min( self::MAX_PAGES, (int) $page ) );
	}

	/**
	 * Whether the filters narrow the list (sort and page do not).
	 *
	 * @param array $filters Filters.
	 * @return bool
	 */
	public static function is_filtered( array $filters ) {
		return '' !== $filters['search'] || $filters['curriculum'] || $filters['bundle'] || $filters['price'] || array_diff( $filters['level'], array( 'all' ) );
	}

	/**
	 * Canonical query string: fixed order, sorted lists, no empty or default values. Mirrors
	 * toParams() in assets/interactivity/catalog/catalog.js so server links and client URLs match.
	 *
	 * @param array $filters Filters.
	 * @return string Encoded query, without `?`.
	 */
	public static function query_string( array $filters ) {
		// Form encoding, as URLSearchParams and a submitted GET form produce it (space "+", "*" kept, "~" escaped).
		$encode = static function ( $value ) {
			return str_replace( array( '%20', '%2A', '~' ), array( '+', '*', '%7E' ), rawurlencode( (string) $value ) );
		};
		$pairs  = array();
		if ( '' !== $filters['search'] ) {
			$pairs[] = self::PARAMS['search'] . '=' . $encode( $filters['search'] );
		}
		foreach ( self::LISTS as $key ) {
			$values = $filters[ $key ];
			sort( $values, SORT_STRING );
			foreach ( $values as $value ) {
				$pairs[] = self::PARAMS[ $key ] . '%5B%5D=' . $encode( $value );
			}
		}
		if ( '' !== $filters['sort'] && self::DEFAULT_SORT !== $filters['sort'] ) {
			$pairs[] = self::PARAMS['sort'] . '=' . $encode( $filters['sort'] );
		}
		if ( $filters['page'] > 1 ) {
			$pairs[] = 'paged=' . (int) $filters['page'];
		}
		return implode( '&', $pairs );
	}

	/**
	 * The page's own URL without catalogue parameters or a `/page/N/` suffix.
	 *
	 * @return string
	 */
	public static function base_url() {
		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$url  = ( is_ssl() ? 'https://' : 'http://' ) . $host . $uri;
		$url  = remove_query_arg( array_merge( array_values( self::PARAMS ), array( 'paged' ) ), $url );
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$base = preg_replace( '#/page/\d+/?$#', '/', $path );
		return $base === $path ? $url : str_replace( $path, $base, $url );
	}

	/**
	 * URL of a filtered list.
	 *
	 * @param array       $filters Filters (missing keys are empty).
	 * @param string|null $base    Base URL; defaults to base_url().
	 * @return string
	 */
	public static function url( array $filters, $base = null ) {
		$base  = null === $base ? self::base_url() : $base;
		$query = self::query_string( array_merge( self::empty_filters(), $filters ) );
		if ( '' === $query ) {
			return $base;
		}
		return $base . ( false === strpos( $base, '?' ) ? '?' : '&' ) . $query;
	}

	/**
	 * Hidden fields that keep the base URL's own query arguments (e.g. `page_id` with plain
	 * permalinks) when the filter form is submitted without JavaScript.
	 *
	 * @return array<string,string>
	 */
	public static function base_fields() {
		$query = (string) wp_parse_url( self::base_url(), PHP_URL_QUERY );
		$args  = array();
		wp_parse_str( $query, $args );
		$fields = array();
		foreach ( $args as $name => $value ) {
			if ( is_scalar( $value ) ) {
				$fields[ (string) $name ] = (string) $value;
			}
		}
		return $fields;
	}

	/**
	 * Field name of a filter checkbox for a `data-type` (`category` => `course_curriculum[]`).
	 *
	 * @param string $type Filter type from `ohmylms_archive_page_filters`, or a filter key.
	 * @return string Empty for unknown types.
	 */
	public static function field( $type ) {
		$key = isset( self::TYPES[ $type ] ) ? self::TYPES[ $type ] : $type;
		return in_array( $key, self::LISTS, true ) ? self::PARAMS[ $key ] . '[]' : '';
	}

	/**
	 * Whether a checkbox value is selected in the current request.
	 *
	 * @param string $type  Filter type or key.
	 * @param string $value Value.
	 * @return bool
	 */
	public static function is_selected( $type, $value ) {
		$key     = isset( self::TYPES[ $type ] ) ? self::TYPES[ $type ] : $type;
		$filters = self::current_filters();
		return in_array( $key, self::LISTS, true ) && in_array( (string) $value, $filters[ $key ], true );
	}

	// ---- Queries --------------------------------------------------------------------------------

	/**
	 * Apply search, level, price, curriculum, bundle and sort to WP_Query arguments. The archive,
	 * the course list and the legacy admin-ajax actions all use this, so they list the same courses.
	 *
	 * @param array $args    WP_Query arguments.
	 * @param array $filters Filters.
	 * @return array
	 */
	public static function query_args( array $args, array $filters ) {
		$filters = array_merge( self::empty_filters(), $filters );
		if ( '' !== $filters['search'] ) {
			$args['s'] = $filters['search'];
		}

		$meta   = array();
		$levels = array_values( array_diff( $filters['level'], array( 'all' ) ) );
		if ( $levels ) {
			$meta[] = array(
				'key'     => '_level',
				'value'   => $levels,
				'compare' => 'IN',
			);
		}
		if ( $filters['price'] ) {
			$meta[] = array(
				'key'     => '_price_type',
				'value'   => $filters['price'],
				'compare' => 'IN',
			);
		}
		if ( isset( self::SORTS[ $filters['sort'] ] ) ) {
			list( $key, $order ) = self::SORTS[ $filters['sort'] ];
			if ( '' === $key ) {
				$args['orderby'] = 'date';
				$args['order']   = $order;
			} else {
				// Courses without the meta (no rating yet) are listed last instead of being left out.
				$meta['ohmylms_sort'] = array(
					'relation'           => 'OR',
					'ohmylms_sort_value' => array(
						'key'     => $key,
						'type'    => 'NUMERIC',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => $key,
						'compare' => 'NOT EXISTS',
					),
				);
				$args['orderby']      = array(
					'ohmylms_sort_value' => $order,
					'date'               => 'DESC',
				);
				unset( $args['order'], $args['meta_key'] );
			}
		}
		if ( $meta ) {
			$existing           = ! empty( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? array( $args['meta_query'] ) : array();
			$args['meta_query'] = array_merge( array( 'relation' => 'AND' ), $existing, $meta ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Course filters are meta fields.
		}

		return Placement::narrow_query(
			$args,
			Placement::ids_from_slugs( $filters['curriculum'], 'item' ),
			Placement::ids_from_slugs( $filters['bundle'], 'bundle' )
		);
	}

	/**
	 * "Load more" paging: `paged=N` lists pages 1..N, so a reload, a shared link and Back show the
	 * cards that were on screen. The page size and page count are kept as query vars for templates.
	 *
	 * @param array $args     WP_Query arguments.
	 * @param int   $per_page Courses per page.
	 * @param int   $pages    Pages to show.
	 * @return array
	 */
	public static function paginate( array $args, $per_page, $pages ) {
		$per_page = (int) $per_page;
		if ( $per_page < 1 ) {
			return $args;
		}
		$pages                            = min( self::clamp_page( $pages ), max( 1, (int) floor( self::MAX_POSTS / $per_page ) ) );
		$args['posts_per_page']           = $per_page * $pages;
		$args['paged']                    = 1;
		$args['ohmylms_catalog_per_page'] = $per_page;
		$args['ohmylms_catalog_pages']    = $pages;
		return $args;
	}

	/**
	 * Archive (and course taxonomy) main query: apply the request's filters and load-more pages.
	 *
	 * @param \WP_Query $query Query.
	 */
	public static function main_query( $query ) {
		if ( is_admin() || ! $query instanceof \WP_Query || ! $query->is_main_query() || $query->is_singular() ) {
			return;
		}
		$types = (array) $query->get( 'post_type' );
		if ( ! in_array( OHMYLMS_COURSE_CPT, $types, true ) && ! $query->is_tax( array( 'course_category', 'course_tag' ) ) ) {
			return;
		}
		$filters         = self::filters();
		$filters['page'] = max( $filters['page'], self::clamp_page( $query->get( 'paged' ) ) );
		$current         = array();
		foreach ( array( 'meta_query', 'post__in', 'orderby', 'order', 'meta_key' ) as $name ) {
			$value = $query->get( $name );
			if ( '' !== $value && array() !== $value && null !== $value ) {
				$current[ $name ] = $value;
			}
		}
		$args = self::query_args( $current, $filters );
		if ( self::settings()['load_more'] ) {
			$args = self::paginate( $args, (int) $query->get( 'posts_per_page' ), $filters['page'] );
		}
		// WP_Query ignores post__not_in (hidden roadmap courses) once post__in is set.
		$excluded = array_map( 'intval', (array) $query->get( 'post__not_in' ) );
		if ( ! empty( $args['post__in'] ) && $excluded ) {
			$remaining        = array_values( array_diff( array_map( 'intval', $args['post__in'] ), $excluded ) );
			$args['post__in'] = $remaining ? $remaining : array( 0 );
		}
		foreach ( $args as $name => $value ) {
			$query->set( $name, $value );
		}
		if ( isset( $args['orderby'] ) && is_array( $args['orderby'] ) ) {
			$query->set( 'order', '' );
		}
	}

	/**
	 * Course list shortcode query: request filters when the list shows catalogue controls, and
	 * load-more paging in the layouts that have the button.
	 *
	 * @param array $args WP_Query arguments.
	 * @param array $atts Shortcode attributes.
	 * @return array
	 */
	public static function list_query_args( array $args, array $atts ) {
		$settings = self::settings( $atts );
		$filters  = self::filters();
		if ( $settings['controls'] ) {
			$args = self::query_args( $args, $filters );
		}
		if ( $settings['load_more'] && isset( $args['posts_per_page'] ) ) {
			$args = self::paginate( $args, (int) $args['posts_per_page'], $filters['page'] );
		}
		return $args;
	}

	/**
	 * Layout and controls of the archive (options) or of a course list (shortcode attributes).
	 *
	 * @param array|null $atts Shortcode attributes, or null/empty for the archive page.
	 * @return array{layout:string,style:string,filters:bool,search:bool,sort:bool,category:bool,carousel:bool,load_more:bool,controls:bool}
	 */
	public static function settings( $atts = null ) {
		$atts     = is_array( $atts ) ? $atts : array();
		$option   = static function ( $key, $name, $fallback ) use ( $atts ) {
			return isset( $atts[ $key ] ) ? (string) $atts[ $key ] : (string) get_option( $name, $fallback );
		};
		$layout   = $option( 'layout', 'ohmylms_archive_page_layout', 'grid' );
		$style    = $option( 'layout_style', 'ohmylms_archive_page_layout_style', 'grid-style1' );
		$grid     = 'grid' === $layout;
		$carousel = $grid && in_array( $style, array( 'grid-style3', 'grid-style4' ), true );
		$bar      = $grid && in_array( $style, array( 'grid-style1', 'grid-style2' ), true );
		$settings = array(
			'layout'    => $layout,
			'style'     => $style,
			'filters'   => $grid && 'yes' === $option( 'show_filter', 'ohmylms_archive_page_filter_is_enabled', 'no' ),
			'search'    => $bar && 'yes' === $option( 'show_search', 'ohmylms_archive_page_search_is_enabled', 'no' ),
			'sort'      => $bar && 'yes' === $option( 'show_sort', 'ohmylms_archive_page_sorting_is_enabled', 'no' ),
			'category'  => $carousel && 'yes' === $option( 'is_enable_category', 'ohmylms_archive_page_category_is_enabled', 'no' ),
			'carousel'  => $carousel,
			'load_more' => 'list' === $layout || $bar,
		);

		$settings['controls'] = $settings['filters'] || $settings['search'] || $settings['sort'] || $settings['category'];
		return $settings;
	}

	// ---- Rendering --------------------------------------------------------------------------------

	/**
	 * Start rendering a catalogue (templates/archive-course.php). Reads the query the template
	 * loops over, enqueues the module with its state and returns the ids and counts the
	 * templates print. The carousel templates read the selected curriculum item from `$category`.
	 *
	 * @param array|null $atts Shortcode attributes, or null on the archive page.
	 * @return array
	 */
	public static function begin( $atts = null ) {
		global $wp_query;
		$settings = self::settings( $atts );
		$filters  = self::filters();
		$query    = $wp_query instanceof \WP_Query ? $wp_query : null;
		$per_page = $query ? (int) $query->get( 'ohmylms_catalog_per_page' ) : 0;
		$per_page = $per_page > 0 ? $per_page : ( $query ? (int) $query->get( 'posts_per_page' ) : 0 );
		$found    = $query ? (int) $query->found_posts : 0;
		$total    = $per_page > 0 ? max( 1, (int) ceil( $found / $per_page ) ) : 1;
		$pages    = $query ? (int) $query->get( 'ohmylms_catalog_pages' ) : 0;
		$pages    = $pages > 0 ? $pages : max( 1, $query ? (int) $query->get( 'paged' ) : 1 );
		$suffix   = 1 === ++self::$instances ? '' : '-' . self::$instances;

		$filters['page'] = min( $pages, $total );

		$catalog = array(
			'settings' => $settings,
			'filters'  => $filters,
			'form'     => 'ohmylms-catalog-form' . $suffix,
			'heading'  => 'ohmylms-catalog-heading' . $suffix,
			'panel'    => 'ohmylms-course-filters' . $suffix,
			'results'  => 'ohmylms/catalog-results' . $suffix,
			'more'     => 'ohmylms/catalog-more' . $suffix,
			'found'    => $found,
			'shown'    => $query ? (int) $query->post_count : 0,
			'pages'    => min( $pages, $total ),
			'total'    => $total,
			'filtered' => (bool) self::is_filtered( $filters ),
		);

		$GLOBALS['category'] = isset( $filters['curriculum'][0] ) ? $filters['curriculum'][0] : null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Read by content-course-carousel.php and content-course-popup.php.
		self::$current       = $catalog;
		self::enqueue( $filters );
		if ( $settings['filters'] ) {
			Interactivity::enqueue( 'ohmylms/ui' );
		}
		return $catalog;
	}

	/**
	 * Finish a catalogue started with begin().
	 */
	public static function end() {
		self::$current = null;
	}

	/**
	 * Catalogue being rendered, or null outside templates/archive-course.php (and in overrides of it).
	 *
	 * @return array|null
	 */
	public static function current() {
		return self::$current;
	}

	/**
	 * Filters of the catalogue being rendered, or of the request.
	 *
	 * @return array
	 */
	public static function current_filters() {
		return self::$current ? self::$current['filters'] : self::filters();
	}

	/**
	 * Root context (ids of the form and the results heading).
	 *
	 * @param array $catalog Result of begin().
	 * @return array
	 */
	public static function context( array $catalog ) {
		return array(
			'form'    => $catalog['form'],
			'heading' => $catalog['heading'],
		);
	}

	/**
	 * Result count for the live region.
	 *
	 * @param array $catalog Result of begin().
	 * @return string
	 */
	public static function count_label( array $catalog ) {
		if ( $catalog['found'] < 1 ) {
			return __( 'No courses found.', 'ohmylms' );
		}
		$shown = min( $catalog['shown'], $catalog['found'] );
		if ( $shown >= $catalog['found'] ) {
			/* translators: %s: number of courses. */
			return sprintf( _n( 'Showing %s course.', 'Showing all %s courses.', $catalog['found'], 'ohmylms' ), number_format_i18n( $catalog['found'] ) );
		}
		/* translators: 1: courses shown, 2: courses found. */
		return sprintf( _n( 'Showing %1$s of %2$s course.', 'Showing %1$s of %2$s courses.', $catalog['found'], 'ohmylms' ), number_format_i18n( $shown ), number_format_i18n( $catalog['found'] ) );
	}

	/**
	 * Enqueue `ohmylms/catalog` with its state.
	 *
	 * @param array|null $filters Filters; defaults to the request's.
	 */
	public static function enqueue( $filters = null ) {
		Interactivity::enqueue( self::MODULE );
		if ( ! Interactivity::once( 'catalog-state' ) ) {
			return;
		}
		wp_interactivity_state(
			self::MODULE,
			array(
				'loading' => false,
				'filters' => null === $filters ? self::filters() : $filters,
			)
		);
	}

	// ---- Theme overrides --------------------------------------------------------------------------

	/**
	 * Decorate the catalogue templates. The plugin's own templates already carry everything; this
	 * converts overrides copied before the router migration: legacy field names (`ohmylms-filter-checkbox`,
	 * `course-search`, `course-sort`) become the URL contract, controls get their state and form,
	 * buttons and links get their actions, and the load-more area becomes a router region.
	 *
	 * @param string $html     Template HTML.
	 * @param string $template Template name.
	 * @return string
	 */
	public static function template( $html, $template ) {
		if ( ! is_string( $html ) || '' === trim( $html ) || ! in_array( $template, self::TEMPLATES, true ) ) {
			return $html;
		}
		$catalog = self::$current;
		$filters = self::current_filters();
		$form    = $catalog ? $catalog['form'] : '';
		$tags    = new \WP_HTML_Tag_Processor( $html );
		$first   = true;
		$found   = false;
		$sort    = false;
		while ( $tags->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
			$tag = $tags->get_tag();
			if ( $tags->is_tag_closer() ) {
				if ( 'SELECT' === $tag ) {
					$sort = false;
				}
				continue;
			}
			if ( $first ) {
				$first = false;
				// Without a catalogue root (an archive-course.php override) each template is its own island.
				if ( ! $catalog && null === $tags->get_attribute( 'data-wp-interactive' ) ) {
					$tags->set_attribute( 'data-wp-interactive', self::MODULE );
					$tags->set_attribute( 'data-wp-on--change', 'actions.change' );
					$tags->set_attribute( 'data-wp-on--input', 'actions.input' );
					$tags->set_attribute( 'data-wp-on--keydown', 'actions.keydown' );
					$found = true;
				}
			}
			$name = (string) $tags->get_attribute( 'name' );
			if ( 'INPUT' === $tag && 'ohmylms-filter-checkbox' === $name ) {
				$field = self::field( (string) $tags->get_attribute( 'data-type' ) );
				$value = (string) $tags->get_attribute( 'data-slug' );
				if ( '' !== $field ) {
					$tags->set_attribute( 'name', $field );
					$tags->set_attribute( 'value', $value );
					$tags->set_attribute( 'checked', self::is_selected( (string) $tags->get_attribute( 'data-type' ), $value ) );
					$tags->remove_attribute( 'aria-required' );
					self::associate( $tags, $form );
					$found = true;
				}
			} elseif ( 'INPUT' === $tag && 'course-search' === $name ) {
				$tags->set_attribute( 'name', self::PARAMS['search'] );
				$tags->set_attribute( 'value', $filters['search'] );
				if ( null === $tags->get_attribute( 'aria-label' ) ) {
					$tags->set_attribute( 'aria-label', __( 'Search courses', 'ohmylms' ) );
				}
				self::associate( $tags, $form );
				$found = true;
			} elseif ( 'SELECT' === $tag && ( 'course-sort' === $name || $tags->has_class( 'course-sort' ) ) && self::PARAMS['sort'] !== $name ) {
				$tags->set_attribute( 'name', self::PARAMS['sort'] );
				if ( null === $tags->get_attribute( 'aria-label' ) ) {
					$tags->set_attribute( 'aria-label', __( 'Sort courses', 'ohmylms' ) );
				}
				self::associate( $tags, $form );
				$sort  = true;
				$found = true;
			} elseif ( 'OPTION' === $tag && $sort ) {
				$tags->set_attribute( 'selected', ( '' !== $filters['sort'] ? $filters['sort'] : self::DEFAULT_SORT ) === $tags->get_attribute( 'value' ) );
			} elseif ( $tags->has_class( 'clear-filter' ) && null === $tags->get_attribute( 'data-wp-on--click' ) ) {
				$tags->set_attribute( 'data-wp-on--click', 'actions.clear' );
				$found = true;
			} elseif ( $tags->has_class( 'ohmylms-close-filter' ) && $catalog && null === $tags->get_attribute( 'data-wp-on--click' ) ) {
				$tags->set_attribute( 'data-wp-on--click', 'actions.closeFilters' );
				if ( null === $tags->get_attribute( 'aria-label' ) ) {
					$tags->set_attribute( 'aria-label', __( 'Close filters', 'ohmylms' ) );
				}
			} elseif ( $tags->has_class( 'filter-hamburger' ) && $catalog && $catalog['settings']['filters'] && null === $tags->get_attribute( 'data-wp-on--click' ) ) {
				self::set( $tags, self::drawer_toggle( $catalog ) );
			} elseif ( 'LI' === $tag && $tags->has_class( 'ohmylms-category-filter' ) && null === $tags->get_attribute( 'data-wp-context' ) ) {
				$slug = (string) $tags->get_attribute( 'data-slug' );
				$tags->set_attribute( 'data-wp-context', wp_json_encode( array( 'slug' => $slug ) ) );
				$tags->set_attribute( 'data-wp-class--active', 'state.categoryActive' );
				if ( 'all' === $slug ? ! $filters['curriculum'] : in_array( $slug, $filters['curriculum'], true ) ) {
					$tags->add_class( 'active' );
				} else {
					$tags->remove_class( 'active' );
				}
				$found = true;
			} elseif ( 'A' === $tag && null !== $tags->get_attribute( 'data-slug' ) && '#' === $tags->get_attribute( 'href' ) ) {
				$slug = (string) $tags->get_attribute( 'data-slug' );
				$tags->set_attribute( 'href', self::category_url( $slug, $filters ) );
				$tags->set_attribute( 'data-wp-on--click', 'actions.category' );
				$tags->set_attribute( 'data-wp-bind--aria-current', 'state.categoryCurrent' );
			} elseif ( $tags->has_class( 'ohmylms-course-loadmore-area' ) && null === $tags->get_attribute( 'data-wp-router-region' ) ) {
				$tags->set_attribute( 'data-wp-interactive', self::MODULE );
				$tags->set_attribute( 'data-wp-router-region', $catalog ? $catalog['more'] : 'ohmylms/catalog-more' );
				$found = true;
			} elseif ( $tags->has_class( 'ohmylms-course-loadmore-btn' ) && null === $tags->get_attribute( 'data-wp-on--click' ) ) {
				$paged = max( 1, (int) $tags->get_attribute( 'data-paged' ), $catalog ? $catalog['pages'] : 1 );
				$tags->set_attribute( 'data-paged', (string) $paged );
				$tags->set_attribute( 'type', 'submit' );
				$tags->set_attribute( 'name', 'paged' );
				$tags->set_attribute( 'value', (string) ( $paged + 1 ) );
				self::associate( $tags, $form );
				$tags->set_attribute( 'data-wp-on--click', 'actions.loadMore' );
				$tags->set_attribute( 'data-wp-bind--disabled', 'state.loading' );
				$found = true;
			}
		}
		if ( $found ) {
			self::enqueue( $filters );
		}
		return $tags->get_updated_html();
	}

	/**
	 * Attributes of the mobile filter drawer toggle (`ohmylms/ui` drawer on `.ohmylms-course-outer`).
	 *
	 * @param array $catalog Result of begin().
	 * @return array
	 */
	public static function drawer_toggle( array $catalog ) {
		return array(
			'aria-controls'               => $catalog['panel'],
			'aria-expanded'               => 'false',
			'data-wp-bind--aria-expanded' => 'ohmylms/ui::context.drawerOpen',
			'data-wp-on--click'           => 'ohmylms/ui::actions.drawerToggle',
		);
	}

	/**
	 * URL of a curriculum button: the current filters with only that item (or none for "All").
	 *
	 * @param string $slug    `c<id>` or `all`.
	 * @param array  $filters Filters.
	 * @return string
	 */
	public static function category_url( $slug, array $filters ) {
		$filters['curriculum'] = 'all' === $slug || '' === $slug ? array() : array( $slug );
		$filters['page']       = 1;
		return self::url( $filters );
	}

	/**
	 * Associate a control with the filter form.
	 *
	 * @param \WP_HTML_Tag_Processor $tags Processor.
	 * @param string                 $form Form id ('' without a catalogue root).
	 */
	private static function associate( $tags, $form ) {
		if ( '' !== $form && null === $tags->get_attribute( 'form' ) ) {
			$tags->set_attribute( 'form', $form );
		}
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
