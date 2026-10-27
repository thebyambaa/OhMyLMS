<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Abstracts;

defined( 'ABSPATH' ) || exit();

/**
 * Plugin post type implementation.
 */
abstract class PostType {

	/**
	 * Post type
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public string $post_type = '';


	/**
	 * CPT arguments
	 *
	 * @var array
	 * @since 1.0.0
	 */
	public array $args = array();


	/**
	 * CPT labels
	 *
	 * @var array
	 * @since 1.0.0
	 */
	public array $labels = array();


	/**
	 * Initialize Post type.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
	}


	/**
	 * Get arguments of post types
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_args() {
		return $this->args;
	}


	/**
	 * Register post type for Post type.
	 */
	public function register_post_type() {
		if ( ! $this->post_type ) {
			return;
		}
		$args = $this->get_args();
		register_post_type( $this->post_type, $args );
		do_action( 'ohmylms_after_register_post_type_' . $this->post_type );
	}
}
