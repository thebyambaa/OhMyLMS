<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * MCP tools that set up public subject browsing: the All subjects page and the header Explore item.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\MCP;

defined( 'ABSPATH' ) || exit;

/** Previewed, repeatable site setup; nothing is changed without `confirm: true`. */
final class SiteSetupTools {

	/**
	 * Tool definitions in the shape the MCP server and abilities layer share.
	 *
	 * @return array
	 */
	public static function definitions() {
		$confirm = array(
			'type'        => 'boolean',
			'description' => 'false or omitted only describes the change; true applies it.',
		);
		return array(
			'list_navigation_menus' => array(
				'method'      => 'GET',
				'route'       => '/site-setup/navigation-menus',
				'description' => 'List the site\'s block navigation menus with their top-level items and whether each already has the Explore item. Use a menu id with setup_explore_menu.',
				'properties'  => array(),
				'required'    => array(),
				'list'        => false,
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			),
			'setup_subjects_page'   => array(
				'method'      => 'POST',
				'route'       => '/site-setup/subjects-page',
				'description' => 'Make sure a published page with the Browse Subjects block exists, so visitors can browse subjects and sign up. Returns the existing page when there is one. Call once without confirm to preview, then with confirm true to create it. Needs published subjects and courses to show anything.',
				'properties'  => array(
					'title'   => array(
						'type'      => 'string',
						'maxLength' => 100,
					),
					'display' => array(
						'type' => 'string',
						'enum' => array( 'panel', 'dropdown' ),
					),
					'confirm' => $confirm,
				),
				'required'    => array(),
				'list'        => false,
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => true,
			),
			'setup_explore_menu'    => array(
				'method'      => 'POST',
				'route'       => '/site-setup/explore-menu',
				'description' => 'Add the full-width Explore item to the start of a navigation menu (the site header). Other items are kept as they are, and a menu that already has it is left alone. Changes what every visitor sees in the header: preview first without confirm, then apply with confirm true. Find menu_id with list_navigation_menus.',
				'properties'  => array(
					'menu_id' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'label'   => array(
						'type'      => 'string',
						'maxLength' => 40,
					),
					'confirm' => $confirm,
				),
				'required'    => array( 'menu_id' ),
				'list'        => false,
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => true,
			),
		);
	}
}
