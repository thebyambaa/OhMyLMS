<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class Chapter.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Data;

use OhMyLMS\Abstracts\Data;
use OhMyLMS\DataStores\DataStores;

defined( 'ABSPATH' ) || exit;

/**
 * Class Chapter
 *
 * This class represents a chapter within the OhMyLMS system. It extends the base `Data` class
 * and provides methods to manage chapter properties such as name, description, slug, status, and timestamps.
 * The class interacts with the data store to load, save, update, or delete chapter data.
 *
 * @package OhMyLMS\Data
 * @since 1.0.0
 */
class Chapter extends Data {

	/**
	 * The name of the data store where chapter data is stored.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected string $data_store_name = 'chapter';

	/**
	 * The object type for the class.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public string $object_type = 'chapter';

	/**
	 * The data array representing the chapter's properties.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected array $data = array(
		'name'          => '',
		'description'   => '',
		'slug'          => '',
		'status'        => '',
		'date_created'  => null,
		'date_modified' => null,
		'parent_id'     => null,
	);

	/**
	 * Chapter constructor.
	 *
	 * Accepts a chapter ID or an instance of a Chapter object to load the chapter data. If the chapter ID
	 * is provided and valid, the chapter data is fetched from the data store. Otherwise, a new chapter object is initialized.
	 *
	 * @param mixed $chapter Either a chapter ID, an instance of Chapter, or an object with a valid ID property.
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	public function __construct( $chapter = '' ) {
		if ( is_numeric( $chapter ) && $chapter > 0 ) {
			$this->set_id( $chapter );
		} elseif ( $chapter instanceof self ) {
			$this->set_id( absint( $chapter->get_id() ) );
		} elseif ( ! empty( $chapter->ID ) ) {
			$this->set_id( absint( $chapter->ID ) );
		}

		// Load the data store for chapters.
		$this->data_store = DataStores::load( $this->data_store_name );

		// If the chapter ID is valid, read the chapter data from the data store.
		if ( $this->get_id() > 0 ) {
			$this->data_store->read( $this );
		}
	}

	/**
	 * Get the chapter's name.
	 *
	 * @return string The name of the chapter.
	 */
	public function get_name() {
		return $this->get_prop( 'name' );
	}

	/**
	 * Get the chapter's slug (URL-friendly identifier).
	 *
	 * @return string The slug of the chapter.
	 * @since 1.0.0
	 */
	public function get_slug() {
		return $this->get_prop( 'slug' );
	}

	/**
	 * Get the chapter's description.
	 *
	 * @return string|null The description of the chapter, or null if not set.
	 * @since 1.0.0
	 */
	public function get_description() {
		return $this->get_prop( 'description' );
	}

	/**
	 * Get the status of the chapter.
	 *
	 * @return string|null The status of the chapter (e.g., draft, published), or null if not set.
	 * @since 1.0.0
	 */
	public function get_status() {
		return $this->get_prop( 'status' );
	}

	/**
	 * Get the permalink (URL) of the chapter.
	 *
	 * @return string The permalink of the chapter.
	 * @since 1.0.0
	 */
	public function get_permalink(): string {
		return get_permalink( $this->get_id() );
	}

	/**
	 * Get the date the chapter was created.
	 *
	 * @param string $context The context for retrieving the date ('view' or 'edit').
	 * @return string|null The creation date of the chapter, or null if not set.
	 * @since 1.0.0
	 */
	public function get_date_created( $context = 'view' ) {
		return $this->get_prop( 'date_created', $context );
	}

	/**
	 * Check if the chapter exists in the data store.
	 *
	 * @return bool True if the chapter exists, false otherwise.
	 * @since 1.0.0
	 */
	public function exists(): bool {
		return true;
	}


	/*
	 * ************************************
	 * Setters
	 * ************************************
	 */

	/**
	 * Set the chapter's status.
	 *
	 * @param string $status The status of the chapter (e.g., 'draft', 'published').
	 * @since 1.0.0
	 */
	public function set_status( $status ) {
		$this->set_prop( 'status', $status );
	}

	/**
	 * Set the chapter's name.
	 *
	 * @param string $name The name of the chapter.
	 * @since 1.0.0
	 */
	public function set_name( $name ) {
		$this->set_prop( 'name', $name );
	}

	/**
	 * Set the chapter's description.
	 *
	 * @param string $description The description of the chapter.
	 * @since 1.0.0
	 */
	public function set_description( $description ) {
		$this->set_prop( 'description', $description );
	}

	/**
	 * Set the chapter's slug.
	 *
	 * @param string $slug The URL-friendly identifier of the chapter.
	 * @since 1.0.0
	 */
	public function set_slug( $slug ) {
		$this->set_prop( 'slug', $slug );
	}

	/**
	 * Set the date the chapter was created.
	 *
	 * @param string|null $date The creation date, or null to set the current date.
	 * @since 1.0.0
	 */
	public function set_date_created( $date = null ) {
		$this->set_date_prop( 'date_created', $date );
	}

	/**
	 * Delete the chapter data from the data store.
	 *
	 * @param array $args Additional arguments to customize the deletion process.
	 * @since 1.0.0
	 */
	public function delete( $args = array() ) {
		$this->data_store->delete( $this, $args );
	}


	/**
	 * Get the lessons of the chapter.
	 *
	 * @return array The lessons of the chapter.
	 * @since 1.0.0
	 * @param string $return_type Return type.
	 */
	public function get_lessons( $return_type = 'array' ) {
		return $this->data_store->get_lessons( $this, $return_type );
	}

	/**
	 * Get lesson count for Chapter.
	 */
	public function get_lesson_count() {
		return $this->data_store->get_lesson_count( $this );
	}

	/**
	 * Get the lessons of the chapter.
	 *
	 * @return array The lessons of the chapter.
	 * @since 1.0.0
	 */
	public function get_all_contents() {
		return $this->data_store->get_all_contents( $this );
	}
	/**
	 * Get the lessons of the chapter.
	 *
	 * @return array The lessons of the chapter.
	 * @since 1.0.0
	 */
	public function get_all_content_count() {
		return $this->data_store->get_all_content_count( $this );
	}
	/**
	 * Get the lessons of the chapter.
	 *
	 * @return array The lessons of the chapter.
	 * @since 1.0.0
	 * @param mixed $order Order.
	 */
	public function get_content_by_order( $order ) {
		return $this->data_store->get_content_by_order( $this, $order );
	}
	/**
	 * Get the lessons of the chapter.
	 *
	 * @return array The lessons of the chapter.
	 * @since 1.0.0
	 * @param mixed $student_id Student identifier.
	 */
	public function completed_content_count( $student_id ) {
		return $this->data_store->completed_content_count( $this, $student_id );
	}
	/**
	 * Get the lessons of the chapter.
	 *
	 * @return array The lessons of the chapter.
	 * @since 1.0.0
	 * @param mixed $student_id Student identifier.
	 */
	public function total_completion_rate( $student_id ) {
		return $this->data_store->total_completion_rate( $this, $student_id );
	}
	/**
	 * Get the lessons of the chapter.
	 *
	 * @return array The lessons of the chapter.
	 * @since 1.0.0
	 * @param mixed $student_id Student identifier.
	 */
	public function maybe_all_content_is_completed( $student_id ) {
		return $this->data_store->maybe_content_all_completed( $this, $student_id );
	}
	/**
	 * Set the availability of the chapter.
	 *
	 * @param mixed $availability The availability of the chapter.
	 *
	 * @since 1.0.0
	 */
	public function set_availability( $availability ) {
		$this->set_prop( 'availability', $availability );
	}

	/**
	 * Get parent id for Chapter.
	 *
	 * @param string $context Context.
	 */
	public function get_parent_id( $context = 'view' ) {
		return $this->get_prop( 'parent_id', $context );
	}

	/**
	 * Set parent id for Chapter.
	 *
	 * @param mixed $value Value to process.
	 */
	public function set_parent_id( $value ) {
		$this->set_prop( 'parent_id', absint( $value ) );
	}

	/**
	 * Set the contents of the chapter.
	 *
	 * @param array $contents The chapters of the chapter.
	 *
	 * @since 1.0.0
	 */
	public function set_contents( $contents ) {
		return $this->data_store->set_contents( $this, $contents );
	}

	/**
	 * Set the available date of the chapter.
	 *
	 * @param mixed $available_date The available date of the chapter.
	 *
	 * @since 1.0.0
	 */
	public function set_available_date( $available_date ) {
		$this->set_prop( 'available_date', $available_date );
	}

	/**
	 * Set the accessibility date of the chapter.
	 *
	 * @param mixed $accessibility The limit date of the chapter.
	 *
	 * @since 1.0.0
	 */
	public function set_accessibility( $accessibility ) {
		$this->set_prop( 'accessibility', $accessibility );
	}

	/**
	 * Search lessons within the chapter based on a search term.
	 *
	 * @param string $term The search term to filter lessons by.
	 * @return array An array of lessons that match the search term.
	 *
	 * @since 1.0.0
	 */
	public function search_lessons_in_chapter( $term ) {
		return $this->data_store->search_lessons_in_chapter( $this, $term );
	}
}
