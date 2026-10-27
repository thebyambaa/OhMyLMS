<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Browse Subjects block: the public subject and course browser for visitors.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Blocks;

use OhMyLMS\Student\Subjects;

defined( 'ABSPATH' ) || exit;

/** Server-rendered block over the same subject browser the `[ohmylms_subjects]` shortcode shows. */
final class BrowseSubjectsBlock {

	/** Register the block when its editor script has been built. */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/** Register the built editor entry and server-rendered block. */
	public static function register() {
		$asset_file = OHMYLMS_DIR . '/assets/dist/admin/browse-subjects-block.asset.php';
		if ( ! is_file( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;
		wp_register_script( 'ohmylms-browse-subjects-block', plugins_url( 'assets/dist/admin/browse-subjects-block.js', OHMYLMS_FILE ), $asset['dependencies'], $asset['version'], true );
		register_block_type( OHMYLMS_DIR . '/assets/src/blocks/browse-subjects', array( 'render_callback' => array( __CLASS__, 'render' ) ) );
		register_block_type( OHMYLMS_DIR . '/assets/src/blocks/explore-menu', array( 'render_callback' => array( __CLASS__, 'render_menu' ) ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor_data' ) );
	}

	/** Give the editor the published subject names for its picker. */
	public static function editor_data() {
		$subjects = array();
		foreach ( Subjects::all() as $subject ) {
			$subjects[] = array(
				'id'   => (int) $subject['id'],
				'name' => $subject['name'],
			);
		}
		wp_add_inline_script( 'ohmylms-browse-subjects-block', 'window.ohmylmsBrowseSubjects = ' . wp_json_encode( array( 'subjects' => $subjects ) ) . ';', 'before' );
	}

	/**
	 * Render the header navigation item: an Explore button that opens every subject across the full width.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Navigation item markup.
	 */
	public static function render_menu( $attributes ) {
		$label = isset( $attributes['label'] ) ? sanitize_text_field( $attributes['label'] ) : '';
		return '<li class="wp-block-navigation-item oml-explore-item">' . Subjects::explore(
			'',
			true,
			array(
				'full'  => true,
				'label' => $label,
			)
		) . '</li>';
	}

	/**
	 * Render every subject, or one, keeping subject links on the page that holds the block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Block markup.
	 */
	public static function render( $attributes ) {
		$pinned = absint( $attributes['subjectId'] ?? 0 );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation between public subjects.
		$chosen = isset( $_GET['oml_subject'] ) && is_scalar( $_GET['oml_subject'] ) ? absint( wp_unslash( $_GET['oml_subject'] ) ) : 0;
		$base   = get_permalink();
		$base   = $base ? $base : home_url( '/' );
		$menu   = 'dropdown' === ( $attributes['display'] ?? 'panel' );
		$shown  = $pinned ? $pinned : $chosen;
		$html   = Subjects::explore( $pinned ? '' : $base, $menu );
		// The open panel is the whole index; the dropdown keeps the subject list beneath its button.
		if ( $shown || $menu ) {
			$html .= Subjects::render( $shown, false, $pinned ? '' : $base );
		}
		return '<div ' . get_block_wrapper_attributes( array( 'class' => 'ohmylms-browse-subjects' ) ) . '>' . $html . '</div>';
	}
}
