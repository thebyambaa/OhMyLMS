<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Domain-owned page-template integration extracted from the shared hook adapter.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\ThemeSupport;

defined( 'ABSPATH' ) || exit;

/** Owns page-template integration; hook registration remains in CommonHook for compatibility. */
final class PageTemplates {

	/**
	 * Load custom template
	 *
	 * @param string $template Template.
	 * @return string
	 */
	public function load_custom_template( $template ) {

		if ( is_ohmylms_profile() && get_page_template_slug() === 'ohmylms-profile' ) {
			$plugin_template = OHMYLMS_PATH . '/templates/page-template/ohmylms-profile.php';
			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}

		// Dashboard page template.
		if ( get_page_template_slug() === 'ohmylms-dashboard' ) {
			$plugin_template = OHMYLMS_PATH . '/templates/page-template/ohmylms-dashboard.php';
			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}

		// My Courses page template.
		if ( get_page_template_slug() === 'ohmylms-my-courses' ) {
			$plugin_template = OHMYLMS_PATH . '/templates/page-template/ohmylms-my-courses.php';
			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}

		if ( $this->maybe_bricks_theme() ) {
			return $template;
		}

		// Thank you page without header.
		if ( is_ohmylms_order_received_page() ) {
			$plugin_template = OHMYLMS_PATH . '/templates/page-template/ohmylms-thankyou.php';
			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}

		// Checkout page layout type.
		$checkout_page_layout_type = get_option( 'ohmylms_checkout_page_layout_type' );

		// Check if current post has a checkout block with layoutType attribute.
		if ( is_ohmylms_checkout() ) {
			$post = get_post();
			if ( $post ) {
				// First check for Elementor widgets.
				$elementor_data = get_post_meta( $post->ID, '_elementor_data', true );
				if ( ! empty( $elementor_data ) ) {
					$layout_type_from_elementor = $this->find_layout_type_in_elementor_data( $elementor_data, $checkout_page_layout_type );
					if ( $layout_type_from_elementor !== $checkout_page_layout_type ) {
						$checkout_page_layout_type = $layout_type_from_elementor;
					}
				}

				// Then check for Bricks elements.
				$bricks_data = get_post_meta( $post->ID, '_bricks_page_content_2', true );
				if ( ! empty( $bricks_data ) ) {
					$layout_type_from_bricks = $this->find_layout_type_in_bricks_data( $bricks_data, $checkout_page_layout_type );
					if ( $layout_type_from_bricks !== $checkout_page_layout_type ) {
						$checkout_page_layout_type = $layout_type_from_bricks;
					}
				}

				// Then check for Gutenberg blocks.
				if ( $post->post_content && has_blocks( $post->post_content ) ) {
					$blocks                    = parse_blocks( $post->post_content );
					$checkout_page_layout_type = $this->find_layout_type_in_blocks( $blocks, $checkout_page_layout_type );
				}

				// Finally check for shortcode (for other page builders).
				if ( $post->post_content && has_shortcode( $post->post_content, 'ohmylms_checkout' ) ) {
					preg_match( '/\[ohmylms_checkout[^\]]*layout_type=["\']?([^"\'\s\]]+)["\']?[^\]]*\]/', $post->post_content, $matches );
					if ( ! empty( $matches[1] ) ) {
						$checkout_page_layout_type = $matches[1];
					}
				}
			}
		}

		if ( is_ohmylms_checkout() && 'canvas' === $checkout_page_layout_type ) {
			$plugin_template = OHMYLMS_PATH . '/templates/page-template/ohmylms-checkout.php';

			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}

		return $template;
	}

	/**
	 * Recursively search for layoutType in blocks (handles nested blocks)
	 *
	 * @param array  $blocks Array of blocks to search.
	 * @param string $default_value Default value to return if not found.
	 * @return string The found layoutType or default value
	 */
	private function find_layout_type_in_blocks( $blocks, $default_value ) {
		foreach ( $blocks as $block ) {
			// Check if this is the checkout block.
			if ( 'ohmylms/checkout' === $block['blockName'] ) {
				if ( ! empty( $block['attrs']['layoutType'] ) ) {
					return $block['attrs']['layoutType'];
				}
			}

			// Recursively check inner blocks.
			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = $this->find_layout_type_in_blocks( $block['innerBlocks'], $default_value );
				if ( $found !== $default_value ) {
					return $found;
				}
			}
		}

		return $default_value;
	}

	/**
	 * Find layoutType in Elementor widget data
	 *
	 * @param string|array $elementor_data JSON string or already decoded array of Elementor data.
	 * @param string       $default_value Default value to return if not found.
	 * @return string The found layoutType or default value
	 */
	private function find_layout_type_in_elementor_data( $elementor_data, $default_value ) {
		// Decode JSON data — the meta comes back already decoded when it was saved serialized.
		$data = is_string( $elementor_data ) ? json_decode( $elementor_data, true ) : $elementor_data;

		if ( ! is_array( $data ) ) {
			return $default_value;
		}

		return $this->find_layout_type_in_elementor_elements( $data, $default_value );
	}

	/**
	 * Recursively search for layoutType in Elementor elements
	 *
	 * @param array  $elements Array of Elementor elements.
	 * @param string $default_value Default value to return if not found.
	 * @return string The found layoutType or default value
	 */
	private function find_layout_type_in_elementor_elements( $elements, $default_value ) {
		foreach ( $elements as $element ) {
			// Check if this is the checkout widget.
			if ( isset( $element['widgetType'] ) && 'ohmylms-checkout' === $element['widgetType'] ) {
				if ( ! empty( $element['settings']['layout_type'] ) ) {
					return $element['settings']['layout_type'];
				}
			}

			// Recursively check child elements.
			if ( ! empty( $element['elements'] ) ) {
				$found = $this->find_layout_type_in_elementor_elements( $element['elements'], $default_value );
				if ( $found !== $default_value ) {
					return $found;
				}
			}
		}

		return $default_value;
	}

	/**
	 * Find layoutType in Bricks element data
	 *
	 * @param array  $bricks_data Array of Bricks elements.
	 * @param string $default_value Default value to return if not found.
	 * @return string The found layoutType or default value
	 */
	private function find_layout_type_in_bricks_data( $bricks_data, $default_value ) {
		if ( ! is_array( $bricks_data ) ) {
			return $default_value;
		}

		return $this->find_layout_type_in_bricks_elements( $bricks_data, $default_value );
	}

	/**
	 * Recursively search for layoutType in Bricks elements
	 *
	 * @param array  $elements Array of Bricks elements.
	 * @param string $default_value Default value to return if not found.
	 * @return string The found layoutType or default value
	 */
	private function find_layout_type_in_bricks_elements( $elements, $default_value ) {
		foreach ( $elements as $element ) {
			// Check if this is the checkout element.
			if ( isset( $element['name'] ) && 'ohmylms-checkout-2' === $element['name'] ) {
				if ( ! empty( $element['settings']['layout_type'] ) ) {
					return $element['settings']['layout_type'];
				}
			}

			// Recursively check child elements.
			if ( ! empty( $element['elements'] ) ) {
				$found = $this->find_layout_type_in_bricks_elements( $element['elements'], $default_value );
				if ( $found !== $default_value ) {
					return $found;
				}
			}
		}

		return $default_value;
	}

	/**
	 * Maybe bricks theme for Common hook.
	 */
	public function maybe_bricks_theme() {
		$current_theme = wp_get_theme();
		$parent_theme  = $current_theme->parent();

		if ( 'Bricks' === $current_theme->get( 'Name' ) ) {
			return true;
		}

		if ( $parent_theme && 'Bricks' === $parent_theme->get( 'Name' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Register custom template
	 *
	 * @param array $templates Templates.
	 * @return array
	 */
	public function register_custom_template( $templates ) {

		$templates['ohmylms-profile'] = 'OhMyLMS My Profile'; // Template name as it appears in the editor.

		$templates['ohmylms-checkout'] = 'OhMyLMS Checkout'; // Template name as it appears in the editor.

		$templates['ohmylms-thankyou'] = 'OhMyLMS Thank You'; // Template name as it appears in the editor.

		$templates['ohmylms-dashboard'] = 'OhMyLMS Dashboard'; // Template name as it appears in the editor.

		$templates['ohmylms-my-courses'] = 'OhMyLMS My Courses'; // Template name as it appears in the editor.

		return $templates;
	}

	/**
	 * Add display post states for Common hook.
	 *
	 * @param mixed $post_states Post states.
	 * @param mixed $post Post.
	 */
	public function add_display_post_states( $post_states, $post ) {
		$state = __( 'OhMyLMS Page', 'ohmylms' );
		if ( ohmylms_get_page_id( 'checkout' ) === get_post_field( 'ID', $post->ID ) ) {
			$post_states['ohmylms_checkout'] = $state . ' - Checkout';
		}
		if ( ohmylms_get_page_id( 'course' ) === get_post_field( 'ID', $post->ID ) ) {
			$post_states['ohmylms_course'] = $state . ' - All Course';
		}
		if ( ohmylms_get_page_id( 'membership' ) === get_post_field( 'ID', $post->ID ) ) {
			$post_states['ohmylms_membership'] = $state . ' - All Membership';
		}
		if ( ohmylms_get_page_id( 'student_dashboard' ) === get_post_field( 'ID', $post->ID ) ) {
			$post_states['ohmylms_student_dashboard'] = $state . ' - Student Dashboard';
		}
		if ( ohmylms_get_page_id( 'student_profile' ) === get_post_field( 'ID', $post->ID ) ) {
			$post_states['ohmylms_student_profile'] = $state . ' - Student My Profile';
		}
		if ( ohmylms_get_page_id( 'student_courses' ) === get_post_field( 'ID', $post->ID ) ) {
			$post_states['ohmylms_student_courses'] = $state . ' - Student My Courses';
		}
		return $post_states;
	}

	/**
	 * Remove page title form OhMyLMS pages
	 *
	 * Only strips the title where the theme prints the page's own heading inside the
	 * main loop. Every other consumer of `the_title` — nav menus, breadcrumbs, widgets,
	 * search results — must keep the real title, otherwise WordPress substitutes its
	 * "#123 (no title)" placeholder for these pages.
	 *
	 * @param string  $title Page title.
	 * @param int int $id Post id
	 * .
	 * @return string $title
	 * @since 1.0.0
	 */
	public function ohmylms_remove_title( $title, $id = 0 ) {

		if ( is_admin() ) {
			return $title;
		}

		// Not the page heading being rendered — leave the title untouched.
		if ( ! in_the_loop() || ! is_main_query() || ! is_singular() ) {
			return $title;
		}

		// Only the post actually being viewed, never another post referenced on the page.
		if ( ! $id || get_queried_object_id() !== (int) $id ) {
			return $title;
		}

		$post = get_post( $id );
		if ( ! $post ) {
			return $title;
		}
		$slugs = array( 'ohmylms-all-courses', 'ohmylms-all-membership', 'ohmylms-checkout', 'ohmylms-checkout-2', 'ohmylms-all-courses', 'ohmylms-all-membership', 'ohmylms-checkout', 'my-profile' );
		if ( isset( $post->post_name ) && in_array( $post->post_name, $slugs, true ) ) {
			$title = '';
		}
		return $title;
	}

	/**
	 * Fix the browser tab title on the default Course/Membership pages.
	 *
	 * TemplateLoader::pre_get_posts() rewrites the main query for these pages
	 * into a CPT-archive query so it can loop over courses/memberships. That
	 * makes WordPress build document_title from the CPT's archive label
	 * instead of the page's own post_title. Restore the real page title here.
	 *
	 * @param array $title_parts Title parts.
	 * @return array
	 * @since 1.0.0
	 */
	public function fix_archive_page_document_title( $title_parts ) {
		if ( is_admin() ) {
			return $title_parts;
		}

		$page_id = 0;
		if ( is_page( ohmylms_get_page_id( 'course' ) ) || is_post_type_archive( OHMYLMS_COURSE_CPT ) ) {
			$page_id = ohmylms_get_page_id( 'course' );
		} elseif ( is_page( ohmylms_get_page_id( 'membership' ) ) || is_post_type_archive( OHMYLMS_MEMBERSHIP_CPT ) ) {
			$page_id = ohmylms_get_page_id( 'membership' );
		}

		if ( $page_id && get_post( $page_id ) ) {
			$title_parts['title'] = get_the_title( $page_id );
		}

		return $title_parts;
	}

	/**
	 * SEO plugins (RankMath, Yoast) build the tab title, meta description,
	 * canonical URL and schema from $wp_query's public flags and queried
	 * object — not from document_title_parts/pre_get_document_title, which
	 * fix_archive_page_document_title() above targets. Since
	 * TemplateLoader::pre_get_posts() rewrote the main query into a
	 * CPT-archive query, is_post_type_archive() reads true and is_page()
	 * reads false, so every SEO plugin treats the request as the CPT's
	 * archive (using its archive label/template) instead of the real page.
	 *
	 * Runs once, right after the main query resolves and before wp_head /
	 * any template output, and restores the query's identity to the real
	 * page: is_page()/is_singular() true, is_post_type_archive() false,
	 * queried object set to the real page. The Loop's already-fetched
	 * posts/pagination are untouched (only flags/queried object change), so
	 * the course/membership grid still renders exactly as before — only
	 * what SEO plugins and the theme see as "what page is this" changes,
	 * to match wp-admin's own page title/meta the user actually edits.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function fix_archive_page_query_context() {
		global $wp_query, $post;

		if ( is_admin() ) {
			return;
		}

		$page_id = 0;
		if ( is_post_type_archive( OHMYLMS_COURSE_CPT ) ) {
			$page_id = ohmylms_get_page_id( 'course' );
		} elseif ( is_post_type_archive( OHMYLMS_MEMBERSHIP_CPT ) ) {
			$page_id = ohmylms_get_page_id( 'membership' );
		}

		$page = $page_id ? get_post( $page_id ) : null;
		if ( ! $page ) {
			return;
		}

		$wp_query->is_post_type_archive = false;
		$wp_query->is_archive           = false;
		$wp_query->is_singular          = true;
		$wp_query->is_page              = true;
		$wp_query->queried_object       = $page;
		$wp_query->queried_object_id    = $page_id;

		$post = $page; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Deliberately points the global post at the archive's page, matching the query context above.
		setup_postdata( $post );
	}

	/**
	 * Outputs CSS variables for use in the plugin's internal styles.
	 *
	 * This function is attached to the `wp_print_styles` action hook, which is
	 * called by WordPress when printing styles in the page header.
	 *
	 * The styles are output inside a `<style>` block with the class
	 * `ohmylms-internal-styles`.
	 *
	 * @since 1.0.0
	 */
	public function ohmylms_add_internal_styles() {
		// Course pages, lesson checks, practice and the school portal all read these variables.
		echo '<style class="ohmylms-internal-styles">' . \OhMyLMS\Design\Tokens::frontend_css() . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- values are validated hex colors and an allow-listed font.
	}

	/**
	 * Session single url for Common hook.
	 *
	 * @param mixed $url Url.
	 * @param mixed $id Id.
	 */
	public function session_single_url( $url, $id ) {
		$post = get_post( $id );
		if ( is_object( $post ) && 'ohmylms-session' === $post->post_type ) {
			$course_id          = $post->post_parent;
			$course             = get_post( $course_id );
			$permalink_settings = ohmylms_get_permalink_structure();
			$course_base        = $permalink_settings['course_base'];
			if ( is_object( $course ) ) {
				return home_url( "/{$course_base}/{$course->post_name}/sessions/" . $post->post_name . '/' );
			} else {
				return home_url( "/{$course_base}/sample-course/sessions/" . $post->post_name . '/' );
			}
		}

		return $url;
	}
}
