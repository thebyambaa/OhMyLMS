<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class Certificate.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Data;

use OhMyLMS\Abstracts\Data;
use OhMyLMS\DataStores\DataStores;

defined( 'ABSPATH' ) || exit;


/**
 * Class Certificate
 *
 * This class represents a Certificate within the OhMyLMS system. It extends the base `Data` class
 * and provides methods to manage Certificate properties such as name, description, slug, status, and timestamps.
 * The class interacts with the data store to load, save, update, or delete Certificate data.
 *
 * @package OhMyLMS\Data
 * @since 1.0.0
 */
class Certificate extends Data {

	/**
	 * The name of the data store where Certificate data is stored.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected string $data_store_name = 'certificate';

	/**
	 * The object type for the class.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public string $object_type = 'certificate';

	/**
	 * The data array representing the certificate's properties.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected array $data = array(
		'name'          => '',
		'status'        => '',
		'thumbnail_id'  => '',
		'date_created'  => null,
		'date_modified' => null,
	);

	/**
	 * Certificate constructor.
	 *
	 * Accepts a certificate ID or an instance of a Certificate object to load the certificate data. If the certificate ID
	 * is provided and valid, the certificate data is fetched from the data store. Otherwise, a new certificate object is initialized.
	 *
	 * @param mixed $certificate Either a certificate ID, an instance of Certificate, or an object with a valid ID property.
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	public function __construct( $certificate = '' ) {
		if ( is_numeric( $certificate ) && $certificate > 0 ) {
			$this->set_id( $certificate );
		} elseif ( $certificate instanceof self ) {
			$this->set_id( absint( $certificate->get_id() ) );
		} elseif ( ! empty( $certificate->ID ) ) {
			$this->set_id( absint( $certificate->ID ) );
		}

		// Load the data store for chapters.
		$this->data_store = DataStores::load( $this->data_store_name );

		// If the certificate ID is valid, read the certificate data from the data store.
		if ( $this->get_id() > 0 ) {
			$this->data_store->read( $this );
		}
	}

	/**
	 * Get the certificate's name.
	 *
	 * @return string The name of the certificate.
	 */
	public function get_name() {
		return $this->get_prop( 'name' );
	}

	/**
	 * Get the certificate's slug (URL-friendly identifier).
	 *
	 * @return string The slug of the certificate.
	 * @since 1.0.0
	 */
	public function get_slug() {
		return $this->get_prop( 'slug' );
	}

	/**
	 * Get the status of the certificate.
	 *
	 * @return string|null The status of the certificate (e.g., draft, published), or null if not set.
	 * @since 1.0.0
	 */
	public function get_status() {
		return $this->get_prop( 'status' );
	}

	/**
	 * Get the attachment_id of the certificate.
	 *
	 * @return string|null The attachment_id of the certificate
	 * @since 1.0.0
	 */
	public function get_thumbnail_id() {
		return get_post_thumbnail_id( $this->get_id() );
	}

	/**
	 * Get the permalink (URL) of the certificate.
	 *
	 * @return string The permalink of the certificate.
	 * @since 1.0.0
	 */
	public function get_permalink(): string {
		return get_permalink( $this->get_id() );
	}

	/**
	 * Get the date the certificate was created.
	 *
	 * @param string $context The context for retrieving the date ('view' or 'edit').
	 * @return string|null The creation date of the certificate, or null if not set.
	 * @since 1.0.0
	 */
	public function get_date_created( $context = 'view' ) {
		return $this->get_prop( 'date_created', $context );
	}

	/**
	 * Check if the certificate exists in the data store.
	 *
	 * @return bool True if the certificate exists, false otherwise.
	 * @since 1.0.0
	 */
	public function exists(): bool {
		return true;
	}


	/**
	 * Save or update the course object in DB
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public function save() {
		if ( ! $this->data_store ) {
			return $this->get_id();
		}

		/**
		 * Fires before saving the course object.
		 *
		 * This action allows developers to perform custom actions before the course object is saved.
		 *
		 * @param Course $this The course object being saved.
		 * @param DataStores $data_store The data store object handling the course data.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_before_' . $this->object_type . '_object_save', $this, $this->data_store );

		if ( $this->get_id() ) {
			$this->data_store->update( $this );
		} else {
			$this->data_store->create( $this );
		}

		/**
		 * Fires after saving the course object.
		 *
		 * This action allows developers to perform custom actions after the course object is saved.
		 *
		 * @param Course $this The course object being saved.
		 * @param DataStores $data_store The data store object handling the course data.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_after_' . $this->object_type . '_object_save', $this, $this->data_store );

		return $this->get_id();
	}

	/*
	 * ************************************
	 * Setters
	 * ************************************
	 */

	/**
	 * Set the certificate's status.
	 *
	 * @param string $status The status of the certificate (e.g., 'draft', 'published').
	 * @since 1.0.0
	 */
	public function set_status( $status ) {
		$this->set_prop( 'status', $status );
	}

	/**
	 * Set the certificate's name.
	 *
	 * @param string $name The name of the certificate.
	 * @since 1.0.0
	 */
	public function set_name( $name ) {
		$this->set_prop( 'name', $name );
	}


	/**
	 * Set the certificate's slug.
	 *
	 * @param string $slug The URL-friendly identifier of the certificate.
	 * @since 1.0.0
	 */
	public function set_slug( $slug ) {
		$this->set_prop( 'slug', $slug );
	}

	/**
	 * Set the date the certificate was created.
	 *
	 * @param string|null $date The creation date, or null to set the current date.
	 * @since 1.0.0
	 */
	public function set_date_created( $date = null ) {
		$this->set_date_prop( 'date_created', $date );
	}

	/**
	 * Delete the certificate data from the data store.
	 *
	 * @param array $args Additional arguments to customize the deletion process.
	 * @since 1.0.0
	 */
	public function delete( $args = array() ) {
		$this->data_store->delete( $this, $args );
	}

	/**
	 * Get the lessons of the certificate.
	 *
	 * @return array The lessons of the certificate.
	 * @since 1.0.0
	 */
	public function get_contents() {
		return $this->data_store->get_contents( $this );
	}

	/**
	 * Get the lessons of the certificate.
	 *
	 * @return array The lessons of the certificate.
	 * @since 1.0.0
	 */
	public function get_html_contents() {
		return $this->data_store->get_html_contents( $this );
	}

	/**
	 * Set the contents of the certificate.
	 *
	 * @param array $contents The chapters of the certificate.
	 *
	 * @since 1.0.0
	 */
	public function set_contents( $contents ) {
		return $this->data_store->set_contents( $this, $contents );
	}

	/**
	 * Set the contents of the certificate.
	 *
	 * @param array $contents The chapters of the certificate.
	 *
	 * @since 1.0.0
	 */
	public function set_html_contents( $contents ) {
		return $this->data_store->set_html_contents( $this, $contents );
	}

	/**
	 * Set thumbnail image
	 *
	 * @param mixed $thumbnail_id Thumbnail identifier.
	 */
	public function set_thumbnail_image( $thumbnail_id ) {
		return $this->data_store->set_thumbnail_image( $this, $thumbnail_id );
	}

	/**
	 * Get courses list
	 */
	public function get_courses() {
		return $this->data_store->get_courses( $this );
	}


	/**
	 * Set courses
	 *
	 * @param mixed $courses Courses.
	 */
	public function set_courses( $courses ) {
		return $this->data_store->set_courses( $this, $courses );
	}
}
