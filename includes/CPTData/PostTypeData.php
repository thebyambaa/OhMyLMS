<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class PostTypeData.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\CPTData;

use OhMyLMS\Abstracts\Data;

/**
 * Class PostTypeData
 *
 * This class represents a post type in the OhMyLMS system. It extends the base Data class and provides
 * methods for managing post type data, including getting and setting properties, saving, and deleting post types.
 *
 * @package OhMyLMS\CPTData
 * @since 1.0.0
 */
class PostTypeData extends Data {

	/**
	 * Get the post type name.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_name(): string {
		return $this->get_prop( 'name' ) ?? '';
	}

	/**
	 * Get the post type description.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_description(): string {
		return $this->get_prop( 'description' ) ?? '';
	}

	/**
	 * Get name
	 *
	 * @return mixed
	 * @since 1.0.0
	 */
	public function get_slug() {
		return $this->get_prop( 'slug' ) ?? '';
	}

	/**
	 * Get short description
	 *
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_short_description() {
		return $this->get_prop( 'short_description' ) ?? '';
	}

	/**
	 * Get status of the post type
	 *
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_status() {
		return $this->get_prop( 'status' ) ?? 'draft';
	}

	/**
	 * Get permalink of the post type
	 *
	 * @return false|string
	 * @since 1.0.0
	 */
	public function get_permalink(): string {
		return get_permalink( $this->get_id() );
	}


	/**
	 * Get post type created date.
	 *
	 * @param string $context Context.
	 * @return mixed|null
	 *
	 * @since 1.0.0
	 */
	public function get_date_created( $context = 'view' ) {
		return $this->get_prop( 'date_created', $context );
	}


	/**
	 * Get post type featured image id
	 *
	 * @param string $context Context.
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_thumbnail_id( $context = 'view' ) {
		return $this->get_prop( 'thumbnail_id', $context ) ?? '';
	}
	/**
	 * Get post type featured image id
	 *
	 * @param string $context Context.
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_video_id( $context = 'view' ) {
		return $this->get_prop( 'video_id', $context ) ?? 0;
	}
	/**
	 * Get post type featured image id
	 *
	 * @param string $context Context.
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_cover_image_id( $context = 'view' ) {
		return $this->get_prop( 'cover_image_id', $context ) ?? 0;
	}

	/*
	 * ************************************
	 * Setters
	 * ************************************
	 */


	/**
	 * Set status
	 *
	 * @param mixed $status Status.
	 * @since 1.0.0
	 */
	public function set_status( $status ) {
		$this->set_prop( 'status', $status );
	}


	/**
	 * Set name
	 *
	 * @param mixed $name Name.
	 * @since 1.0.0
	 */
	public function set_name( $name ) {
		$this->set_prop( 'name', $name );
	}


	/**
	 * Set short description
	 *
	 * @param mixed $short_description Short description.
	 * @since 1.0.0
	 */
	public function set_short_description( $short_description ) {
		$this->set_prop( 'short_description', $short_description );
	}

	/**
	 * Set description
	 *
	 * @param mixed $description Description.
	 * @since 1.0.0
	 */
	public function set_description( $description ) {
		$this->set_prop( 'description', $description );
	}


	/**
	 * Set featured image id
	 *
	 * @param mixed $image_id Image identifier.
	 * @return void
	 * @since 1.0.0
	 */
	public function set_thumbnail_id( $image_id ) {
		$this->set_prop( 'thumbnail_id', $image_id );
	}
	/**
	 * Set featured image id
	 *
	 * @param mixed $image_id Image identifier.
	 * @return void
	 * @since 1.0.0
	 */
	public function set_video_id( $image_id ) {
		$this->set_prop( 'video_id', $image_id );
	}
	/**
	 * Set featured image id
	 *
	 * @param mixed $image_id Image identifier.
	 * @return void
	 * @since 1.0.0
	 */
	public function set_cover_image_id( $image_id ) {
		$this->set_prop( 'cover_image_id', $image_id );
	}

	/**
	 * Set slug
	 *
	 * @param mixed $slug Slug.
	 * @since 1.0.0
	 */
	public function set_slug( $slug ) {
		$this->set_prop( 'slug', $slug );
	}


	/**
	 * Set the date when the post type was created.
	 *
	 * @param string|null $date The date when the post type was created.
	 * @since 1.0.0
	 */
	public function set_date_created( $date = null ) {
		$this->set_date_prop( 'date_created', $date );
	}
}
