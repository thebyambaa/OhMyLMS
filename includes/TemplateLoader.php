<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class TemplateLoader.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS;

/**
 * Class TemplateLoader
 *
 * @package OhMyLMS
 * @since 1.0.0
 */
class TemplateLoader {
	/**
	 * Relocated templates.
	 *
	 * @var mixed
	 */
	private static $relocated_templates = array(
		'recurring-totals.php'              => 'checkout/',
		'recurring-subscription-totals.php' => 'checkout/',
		'subscription-info.php'             => 'checkout/',
	);

	/**
	 * Course page id
	 *
	 * @var int $course_page_id
	 * @since 1.0.0
	 */
	private static $course_page_id = 0;


	/**
	 * TemplateLoader constructor.
	 *
	 * @since 1.0.0
	 */
	public static function init(): void {
		add_action( 'pre_get_posts', array( __CLASS__, 'pre_get_posts' ) );
		add_filter( 'template_include', array( __CLASS__, 'load_template' ), 999 );
		add_filter( 'ohmylms_get_template', array( __CLASS__, 'handle_relocated_templates' ), 10, 5 );
	}

	/**
	 * Load the template
	 *
	 * @param mixed $template Template.
	 * @return string
	 * @since 1.0.0
	 */
	public static function load_template( $template ): string {
		if ( is_embed() ) {
			return $template;
		}
		$default_file = self::get_template_loader_default_file();

		if ( $default_file ) {
			$search_files = self::get_template_loader_files( $default_file );
			$template     = locate_template( $search_files );

			if ( ! $template ) {
				$template = OHMYLMS_PATH . '/templates/' . $default_file;
			}
		}
		return $template;
	}


	/**
	 * Get the default file for the template loader.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	private static function get_template_loader_default_file(): string {
		if ( is_singular( OHMYLMS_COURSE_CPT ) ) {
			$default_file = 'single-course.php';

		} elseif ( is_singular( OHMYLMS_LESSON_CPT ) || ohmylms_is_content_page() ) {
			$default_file = 'single-lesson.php';

		} elseif ( ohmylms_is_courses_page() || ohmylms_is_course_category() || ohmylms_is_course_taxonomy() ) {
			$default_file = 'archive-course.php';

		} elseif ( ohmylms_is_membership_page() ) {
			$default_file = 'archive-membership.php';

		} else {
			$default_file = '';
		}
		return $default_file;
	}


	/**
	 * Get the files to search for the template loader.
	 *
	 * @param mixed $default_file Default file.
	 * @return mixed|null
	 * @since 1.0.0
	 */
	private static function get_template_loader_files( $default_file ) {
		$templates = apply_filters( 'ohmylms_template_loader_files', array(), $default_file );

		if ( is_page_template() ) {
			$page_template = get_page_template_slug();

			if ( $page_template ) {
				$validated_file = validate_file( $page_template );
				if ( 0 === $validated_file ) {
					$templates[] = $page_template;
				}
			}
		}

		if ( is_singular( OHMYLMS_COURSE_CPT ) ) {
			$object       = get_queried_object();
			$name_decoded = urldecode( $object->post_name );
			if ( $name_decoded !== $object->post_name ) {
				$templates[] = "single-product-{$name_decoded}.php";
			}
			$templates[] = "single-product-{$object->post_name}.php";
		}

		$templates[] = $default_file;
		if ( isset( $cs_default ) ) {
			$templates[] = ohmylms_template_path() . $cs_default;
		}
		$templates[] = ohmylms_template_path() . $default_file;

		return array_unique( $templates );
	}


	/**
	 * Pre get posts.
	 *
	 * @param \WP_Query $q Q.
	 * @return \WP_Query
	 */
	public static function pre_get_posts( \WP_Query $q ): \WP_Query {
		if ( ! $q->is_main_query() && ! is_admin() ) {
			return $q;
		}

		if ( is_page( ohmylms_get_page_id( 'course' ) ) ) {
			$q->set( 'post_type', OHMYLMS_COURSE_CPT );
			$q->set( 'posts_per_page', 6 );
			$q->set( 'page_id', '' );
			return $q;
		}
		if ( is_page( ohmylms_get_page_id( 'membership' ) ) ) {
			$q->set( 'post_type', OHMYLMS_MEMBERSHIP_CPT );
			$q->set( 'posts_per_page', 10 );
			$q->set( 'page_id', '' );
			return $q;
		}

		return $q;
	}

	/**
	 * Handle relocated templates for Template loader.
	 *
	 * @param mixed $template Template.
	 * @param mixed $template_name Template name.
	 * @param mixed $args Operation arguments.
	 * @param mixed $template_path Template path.
	 * @param mixed $default_path Default path.
	 */
	public static function handle_relocated_templates( $template, $template_name, $args, $template_path, $default_path ) {
		if ( file_exists( $template ) ) {
			return $template;
		}

		$ohmylms_pro_path = OHMYLMS_PRO_DIR;
		$template_file    = basename( $template_name );

		if ( ! isset( self::$relocated_templates[ $template_file ] ) ) {
			return $template;
		}
		return $ohmylms_pro_path . '/templates/' . self::$relocated_templates[ $template_file ] . $template_file;
	}
}

add_action( 'init', array( 'OhMyLMS\TemplateLoader', 'init' ) );
