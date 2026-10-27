<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * MembershipListBlock.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Blocks\Blocks;

use OhMyLMS\Shortcodes\ShortCodeMembershipPlan;

defined( 'ABSPATH' ) || exit;

/**
 * MembershipListBlock
 *
 * Registers the OhMyLMS Membership List Gutenberg block. Wraps
 * [ohmylms_membership_plan]. Available with all OhMyLMS blocks.
 *
 * @since 1.0.0
 */
class MembershipListBlock {

	const BLOCK_NAME = 'ohmylms/membership-list';

	/**
	 * Initialize Membership list block.
	 */
	public function __construct() {
		$this->register_block();
	}

	/**
	 * Register block for Membership list block.
	 */
	private function register_block() {
		register_block_type(
			self::BLOCK_NAME,
			array(
				'attributes'      => $this->get_block_attributes(),
				'render_callback' => array( $this, 'render_block' ),
				'editor_script'   => 'ohmylms-blocks-editor',
				'editor_style'    => 'ohmylms-blocks-editor',
				'style'           => 'ohmylms-blocks-frontend',
			)
		);
	}

	/**
	 * Get block attributes for Membership list block.
	 */
	private function get_block_attributes() {
		return array(
			'title' => array(
				'type'    => 'string',
				'default' => '',
			),
			'align' => array(
				'type'    => 'string',
				'default' => 'full',
			),
		);
	}

	/**
	 * Render block for Membership list block.
	 *
	 * @param mixed  $attributes Attributes.
	 * @param string $content Content.
	 */
	public function render_block( $attributes, $content = '' ) {
		wp_enqueue_style( 'ohmylms-frontend' );
		wp_enqueue_style( 'ohmylms-general' );

		// All plugin CSS is scoped under `.ohmylms-page` on <body>. That class is
		// normally added by ohmylms_body_class() via ohmylms_is_membership_plan_shortcode(),
		// but force it here too so the block renders styled even if that detection
		// path is bypassed when block attributes supply the display settings.
		add_filter(
			'body_class',
			function ( $classes ) {
				$classes[] = 'ohmylms-page';
				return $classes;
			}
		);

		$shortcode_atts = array(
			'title' => isset( $attributes['title'] ) ? $attributes['title'] : '',
		);

		ob_start();
		ShortCodeMembershipPlan::output( $shortcode_atts );
		return ob_get_clean();
	}
}
