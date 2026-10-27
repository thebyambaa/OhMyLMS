<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * One-step public subject browsing setup: the All subjects page and the header Explore item.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

defined( 'ABSPATH' ) || exit;

/**
 * Creates only the two things a site needs to offer subject browsing to visitors. Every change is
 * previewed first, repeating a call changes nothing, and existing page and menu content is never replaced.
 */
final class SiteSetup {

	const PAGE_BLOCK = 'ohmylms/browse-subjects';
	const MENU_BLOCK = 'ohmylms/explore-menu';

	/**
	 * Whether the current user may read the site's navigation menus and pages.
	 *
	 * @return true|\WP_Error
	 */
	public static function can_read() {
		return current_user_can( 'manage_options' ) && current_user_can( 'edit_theme_options' ) ? true : self::forbidden();
	}

	/**
	 * Whether the current user may create the page and edit navigation menus.
	 *
	 * @return true|\WP_Error
	 */
	public static function can_write() {
		return current_user_can( 'manage_options' ) && current_user_can( 'edit_theme_options' ) && current_user_can( 'publish_pages' ) ? true : self::forbidden();
	}

	/**
	 * Error for a user who may not change the site's pages and menus.
	 *
	 * @return \WP_Error
	 */
	private static function forbidden() {
		return new \WP_Error( 'ohmylms_site_setup_forbidden', __( 'Only administrators who can edit the theme and publish pages can set up subject browsing.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Block navigation menus with their top-level items, so a caller can pick one to change.
	 *
	 * @return array[]
	 */
	public static function menus() {
		$menus = array();
		$posts = get_posts(
			array(
				'post_type'      => 'wp_navigation',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 50,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);
		foreach ( $posts as $post ) {
			$items = array();
			foreach ( parse_blocks( $post->post_content ) as $block ) {
				if ( $block['blockName'] ) {
					$items[] = $block['attrs']['label'] ?? $block['blockName'];
				}
			}
			$menus[] = array(
				'id'          => (int) $post->ID,
				'title'       => $post->post_title,
				'status'      => $post->post_status,
				'items'       => $items,
				'has_explore' => has_block( self::MENU_BLOCK, $post ),
			);
		}
		return $menus;
	}

	/**
	 * Find or create the page that holds the Browse Subjects block.
	 *
	 * @param string $title   Page title when one is created.
	 * @param string $display panel or dropdown.
	 * @param bool   $confirm Apply the change; without it the result only describes it.
	 * @return array|\WP_Error
	 */
	public static function subjects_page( $title, $display, $confirm ) {
		if ( ! \WP_Block_Type_Registry::get_instance()->is_registered( self::PAGE_BLOCK ) ) {
			return self::block_missing();
		}
		$existing = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => 50,
				's'              => 'ohmylms/browse-subjects',
			)
		);
		foreach ( $existing as $page ) {
			if ( has_block( self::PAGE_BLOCK, $page ) ) {
				return array(
					'status'  => 'exists',
					'changed' => false,
					'page_id' => (int) $page->ID,
					'url'     => get_permalink( $page ),
					'state'   => $page->post_status,
				);
			}
		}
		$title   = '' === trim( $title ) ? __( 'All subjects', 'ohmylms' ) : $title;
		$display = 'dropdown' === $display ? 'dropdown' : 'panel';
		if ( ! $confirm ) {
			return array(
				'status'  => 'preview',
				'changed' => false,
				'would'   => 'Create the published page "' . $title . '" containing the Browse Subjects block (' . $display . ' layout).',
			);
		}
		$page_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $title,
					'post_content' => serialize_block(
						array(
							'blockName'    => self::PAGE_BLOCK,
							'attrs'        => 'panel' === $display ? array() : array( 'display' => $display ),
							'innerBlocks'  => array(),
							'innerHTML'    => '',
							'innerContent' => array(),
						)
					),
				)
			),
			true
		);
		if ( is_wp_error( $page_id ) ) {
			return $page_id;
		}
		return array(
			'status'  => 'created',
			'changed' => true,
			'page_id' => (int) $page_id,
			'url'     => get_permalink( $page_id ),
		);
	}

	/**
	 * Put the Explore item first in a navigation menu, leaving its other items untouched.
	 *
	 * @param int    $menu_id Navigation menu post ID.
	 * @param string $label   Button label.
	 * @param bool   $confirm Apply the change; without it the result only describes it.
	 * @return array|\WP_Error
	 */
	public static function explore_menu( $menu_id, $label, $confirm ) {
		if ( ! \WP_Block_Type_Registry::get_instance()->is_registered( self::MENU_BLOCK ) ) {
			return self::block_missing();
		}
		$menu = get_post( $menu_id );
		if ( ! $menu || 'wp_navigation' !== $menu->post_type ) {
			return new \WP_Error( 'ohmylms_navigation_missing', __( 'That navigation menu does not exist. List the menus and use one of their IDs.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		if ( ! current_user_can( 'edit_post', $menu->ID ) ) {
			return self::forbidden();
		}
		if ( has_block( self::MENU_BLOCK, $menu ) ) {
			return array(
				'status'  => 'exists',
				'changed' => false,
				'menu_id' => (int) $menu->ID,
			);
		}
		$label = trim( $label );
		if ( ! $confirm ) {
			return array(
				'status'  => 'preview',
				'changed' => false,
				'would'   => 'Add an "' . ( '' === $label ? __( 'Explore', 'ohmylms' ) : $label ) . '" item at the start of the "' . $menu->post_title . '" menu. Its other items are not changed.',
			);
		}
		$block = serialize_block(
			array(
				'blockName'    => self::MENU_BLOCK,
				'attrs'        => '' === $label ? array() : array( 'label' => $label ),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
		$saved = wp_update_post(
			wp_slash(
				array(
					'ID'           => $menu->ID,
					'post_content' => $block . "\n" . $menu->post_content,
				)
			),
			true
		);
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		return array(
			'status'  => 'updated',
			'changed' => true,
			'menu_id' => (int) $menu->ID,
		);
	}

	/**
	 * Error for a site whose plugin files do not include the blocks yet.
	 *
	 * @return \WP_Error
	 */
	private static function block_missing() {
		return new \WP_Error( 'ohmylms_block_missing', __( 'The subject browsing blocks are not available. Update the plugin so its built block files are present.', 'ohmylms' ), array( 'status' => 409 ) );
	}
}
