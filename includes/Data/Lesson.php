<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class Lesson.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Data;

use OhMyLMS\CPTData\PostTypeData;
use OhMyLMS\DataStores\DataStores;

defined( 'ABSPATH' ) || exit;

/**
 * Class Lesson
 *
 * This class represents a lesson in the OhMyLMS system. It extends the base Data class and provides
 * methods for managing lesson data, including getting and setting properties, saving, and deleting lessons.
 *
 * @package OhMyLMS\Data
 * @since 1.0.0
 */
class Lesson extends PostTypeData {

	/**
	 * Name of the store
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected string $data_store_name = 'lesson';


	/**
	 * Object type
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public string $object_type = 'lesson';


	/**
	 * Lesson data array
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected array $data = array(
		'name'              => '',
		'description'       => '',
		'thumbnail_id'      => '',
		'slug'              => '',
		'status'            => '',
		'type'              => '',
		'featured'          => false,
		'date_created'      => null,
		'date_modified'     => null,
		'content'           => '',
		'enable_comments'   => '',
		'cover_image_id'    => '',
		'video_id'          => '',
		'audio_id'          => '',
		'external_url'      => '',
		'drip_settings'     => '',
		'prerequisites'     => '',
		'download_resource' => '',
		'preview_enable'    => '',
		'video_settings'    => '',
	);


	/**
	 * Lesson constructor.
	 *
	 * @param string $lesson Lesson.
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	public function __construct( $lesson = '' ) {
		if ( is_numeric( $lesson ) && $lesson > 0 ) {
			$this->set_id( $lesson );
		} elseif ( $lesson instanceof self ) {
			$this->set_id( absint( $lesson->get_id() ) );
		} elseif ( ! empty( $lesson->ID ) ) {
			$this->set_id( absint( $lesson->ID ) );
		}

		// load the data store.
		$this->data_store = DataStores::load( $this->data_store_name );
		if ( $this->get_id() > 0 ) {
			$this->data_store->read( $this );
		}
	}


	/**
	 * Get the lesson type.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_type(): string {
		return $this->get_prop( 'type' ) ?? '';
	}


	/**
	 * Set the lesson type.
	 *
	 * @return void
	 * @since 1.0.0
	 * @param mixed $type Type.
	 */
	public function set_type( $type ) {
		$this->set_prop( 'type', $type );
	}


	/**
	 * Get the lesson type.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_audio_id(): string {
		return $this->get_prop( 'audio_id' ) ?? '';
	}

	/**
	 * Get the lesson type.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_external_url(): string {
		return $this->get_prop( 'external_url' ) ?? '';
	}


	/**
	 * Set the lesson type.
	 *
	 * @return void
	 * @since 1.0.0
	 * @param mixed $type Type.
	 */
	public function set_audio_id( $type ) {
		$this->set_prop( 'audio_id', $type );
	}


	/**
	 * Set the lesson type.
	 *
	 * @return void
	 * @since 1.0.0
	 * @param mixed $link Link.
	 */
	public function set_external_url( $link ) {
		$this->set_prop( 'external_url', $link );
	}


	/**
	 * Get permalink of the lesson
	 *
	 * @return false|string
	 * @since 1.0.0
	 */
	public function get_permalink(): string {
		return ohmylms_get_pretty_content_permalink( $this->get_id() ) ?? '';
	}


	/**
	 * Get average rating
	 *
	 * @param string $context Context.
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_average_rating( $context = 'view' ) {
		return $this->get_prop( 'average_rating', $context ) ?? 0;
	}


	/**
	 * Get review count
	 *
	 * @param string $context Context.
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_review_count( $context = 'view' ) {
		return $this->get_prop( 'review_count', $context ) ?? 0;
	}

	/**
	 * Get rating counts
	 *
	 * @param string $context Context.
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_rating_counts( $context = 'view' ) {
		return $this->get_prop( 'rating_counts', $context ) ?? array();
	}
	/**
	 * Get Modified counts
	 *
	 * @param string $context Context.
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_date_modified( $context = 'view' ) {
		return $this->get_prop( 'date_modified', $context ) ?? '';
	}


	/**
	 * Get Content counts
	 *
	 * @param string $context Context.
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_content( $context = 'view' ) {
		return $this->get_prop( 'content', $context ) ?? '';
	}


	/**
	 * Get Content counts
	 *
	 * @param string $context Context.
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_enable_comments( $context = 'view' ) {
		return $this->get_prop( 'enable_comments', $context ) ?? false;
	}

	/**
	 * Retrieves the prerequisites for the lesson.
	 *
	 * @param string $context The context in which the prerequisites are retrieved. Default is 'view'.
	 * @return array The prerequisites for the lesson.
	 */
	public function get_prerequisites( $context = 'view' ) {
		return $this->get_prop( 'prerequisites', $context ) ?? array();
	}

	/**
	 * Get preview enable for the lesson.
	 *
	 * @param string $context The context in which the preview enable is retrieved. Default is 'view'.
	 * @return bool Whether preview is enabled for the lesson.
	 */
	public function get_preview_enable( $context = 'view' ) {
		return $this->get_prop( 'preview_enable', $context ) ?? false;
	}


	/*
	 * ************************************
	 * Setters
	 * ************************************
	 */

	/**
	 * Set the prerequisites for the lesson.
	 *
	 * @param array $prerequisites An array of lesson IDs that are prerequisites for this lesson.
	 * @return void
	 */
	public function set_prerequisites( $prerequisites ) {
		$this->set_prop( 'prerequisites', $prerequisites );
	}

	/**
	 * Set preview enable for the lesson.
	 *
	 * @param bool $preview_enable Whether to enable preview for the lesson.
	 * @return void
	 */
	public function set_preview_enable( $preview_enable ) {
		$this->set_prop( 'preview_enable', $preview_enable );
	}


	/**
	 * Set order number of the lesson
	 *
	 * @param int $number The order number to set for the lesson.
	 * .
	 * @return void
	 * @since 1.0.0
	 */
	public function set_order_number( $number ) {
		$this->set_prop( 'order_number', $number );
	}


	/**
	 * Set prerequisites of the Lesson
	 *
	 * @param mixed $comments_enable Comments enable.
	 * @return void
	 * @since 1.0.0
	 */
	public function set_enable_comments( $comments_enable ) {
		$this->set_prop( 'enable_comments', $comments_enable );
	}

	/**
	 * Set prerequisites of the Lesson
	 *
	 * @param mixed $price Price.
	 * @return void
	 * @since 1.0.0
	 */
	public function set_content( $price ) {
		$this->set_prop( 'content', $price );
	}



	/**
	 * Check if lesson exists or not
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function exists(): bool {
		return true;
	}


	/**
	 * Check if lesson is purchasable
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function is_purchasable(): bool {
		return apply_filters( 'ohmylms_lesson_is_purchasable', true, $this );
	}


	/**
	 * Check if lesson is available for enrolment
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function is_in_stock(): bool {
		return apply_filters( 'ohmylms_lesson_is_in_stock', true, $this );
	}

	/**
	 * Get video settings for the lesson.
	 *
	 * @param string $context The context in which the settings are being retrieved (default: 'view').
	 * @return array The video settings.
	 */
	public function get_video_settings( $context = 'view' ) {
		$settings = $this->get_prop( 'video_settings', $context );

		// Return default settings if none are set.
		if ( empty( $settings ) || ! is_array( $settings ) ) {
			return array(
				'platform'            => 'self-hosted',
				'aspect_ratio'        => '16/9',
				'autoplay'            => false,
				'loop'                => false,
				'controls'            => true,
				'remove_branding'     => false,
				'hide_related_videos' => false,
				'width_unit'          => '%',
				'height_unit'         => 'px',
			);
		}

		return $settings;
	}

	/**
	 * Set video settings for the lesson.
	 *
	 * @param array $settings The video settings to be set.
	 * @return void
	 */
	public function set_video_settings( $settings ) {
		$this->set_prop( 'video_settings', $settings );
	}

	/**
	 * Save or update the lesson object in DB
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public function save() {
		if ( ! $this->data_store ) {
			return $this->get_id();
		}

		/**
		 * Fires before saving the lesson object.
		 *
		 * This action allows developers to perform custom actions before the lesson object is saved.
		 *
		 * @param Lesson $this The lesson object being saved.
		 * @param DataStores $data_store The data store object handling the lesson data.
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
		 * Fires after saving the lesson object.
		 *
		 * This action allows developers to perform custom actions after the lesson object is saved.
		 *
		 * @param Lesson $this The lesson object being saved.
		 * @param DataStores $data_store The data store object handling the lesson data.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_after_' . $this->object_type . '_object_save', $this, $this->data_store );

		return $this->get_id();
	}


	/**
	 * Delete lesson data
	 *
	 * @param array $args Operation arguments.
	 * @since 1.0.0
	 */
	public function delete( $args = array() ) {
		$this->data_store->delete( $this, $args );
	}

	/**
	 * Get order number for Lesson.
	 */
	public function get_order_number() {
		return $this->data_store->get_order_number( $this );
	}

	/**
	 * Get drip settings for Lesson.
	 *
	 * @param string $context Context.
	 */
	public function get_drip_settings( $context = 'view' ) {
		return $this->get_prop( 'drip_settings', $context ) ?? array();
	}

	/**
	 * Set drip settings for Lesson.
	 *
	 * @param mixed $drip_feed Drip feed.
	 */
	public function set_drip_settings( $drip_feed ) {
		$this->set_prop( 'drip_settings', $drip_feed );
	}

	/**
	 * Get download resource for Lesson.
	 *
	 * @param string $context Context.
	 */
	public function get_download_resource( $context = 'view' ) {
		return $this->get_prop( 'download_resource', $context ) ?? array();
	}

	/**
	 * Set download resource for Lesson.
	 *
	 * @param mixed $resources Resources.
	 */
	public function set_download_resource( $resources ) {
		$this->set_prop( 'download_resource', $resources );
	}
}
