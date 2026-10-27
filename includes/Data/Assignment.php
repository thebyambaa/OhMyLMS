<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class Assignment.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Data;

use OhMyLMS\CPTData\PostTypeData;
use OhMyLMS\DataStores\DataStores;

defined( 'ABSPATH' ) || exit;

/**
 * Class Assignment
 *
 * This class represents a Assignment in the OhMyLMS system. It extends the base Data class and provides
 * methods for managing Assignment data, including getting and setting properties, saving, and deleting assignments.
 *
 * @package OhMyLMS\Data
 * @since 1.0.0
 */
class Assignment extends PostTypeData {

	/**
	 * Name of the store
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected string $data_store_name = 'assignment';


	/**
	 * Object type
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public string $object_type = 'assignment';


	/**
	 * Assignment data array
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected array $data = array(
		'name'                   => '',
		'description'            => '',
		'thumbnail_id'           => '',
		'slug'                   => '',
		'status'                 => '',
		'type'                   => '',
		'featured'               => false,
		'date_created'           => null,
		'date_modified'          => null,
		'content'                => '',
		'enable_comments'        => '',
		'download_resource'      => '',
		'prerequisites'          => '',
		'drip_settings'          => '',
		'enable_time_limit'      => false,
		'time_limit'             => '10',
		'time_limit_type'        => 'week',
		'total_points'           => 50,
		'maximum_pass_points'    => 25,
		'allow_upload_files'     => true,
		'number_of_files'        => 5,
		'enable_file_size_limit' => true,
		'max_file_size_limit'    => 5,
	);


	/**
	 * Assignment constructor.
	 *
	 * @param string $assignment Assignment.
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	public function __construct( $assignment = '' ) {
		if ( is_numeric( $assignment ) && $assignment > 0 ) {
			$this->set_id( $assignment );
		} elseif ( $assignment instanceof self ) {
			$this->set_id( absint( $assignment->get_id() ) );
		} elseif ( ! empty( $assignment->ID ) ) {
			$this->set_id( absint( $assignment->ID ) );
		}

		// load the data store.
		$this->data_store = DataStores::load( $this->data_store_name );

		if ( $this->get_id() > 0 ) {
			$this->data_store->read( $this );
		}
	}


	/**
	 * Get the assignment type.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_type(): string {
		return $this->get_prop( 'type' ) ?? '';
	}


	/**
	 * Set the assignment type.
	 *
	 * @return void
	 * @since 1.0.0
	 * @param mixed $type Type.
	 */
	public function set_type( $type ) {
		$this->set_prop( 'type', $type );
	}
	/**
	 * Get the assignment type.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_audio_id(): string {
		return $this->get_prop( 'audio_id' ) ?? '';
	}

	/**
	 * Get permalink of the assignment
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_permalink(): string {
		return ohmylms_get_pretty_content_permalink( $this->get_id() ) ?? '';
	}

	/**
	 * Get the assignment type.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_external_url(): string {
		return $this->get_prop( 'external_url' ) ?? '';
	}


	/**
	 * Set the assignment type.
	 *
	 * @return void
	 * @since 1.0.0
	 * @param mixed $type Type.
	 */
	public function set_audio_id( $type ) {
		$this->set_prop( 'audio_id', $type );
	}


	/**
	 * Set the assignment type.
	 *
	 * @return void
	 * @since 1.0.0
	 * @param mixed $link Link.
	 */
	public function set_external_url( $link ) {
		$this->set_prop( 'external_url', $link );
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
	 * Get assignment title for Assignment.
	 */
	public function get_assignment_title() {
		return $this->get_prop( 'name' ) ?? '';
	}

	/**
	 * Get submission for Assignment.
	 *
	 * @param mixed $student_id Student identifier.
	 */
	public function get_submission( $student_id ) {
		return $this->data_store->get_submission( $this, $student_id );
	}

	/**
	 * Submit file submission for Assignment.
	 *
	 * @param mixed $student_id Student identifier.
	 * @param mixed $course_id Course identifier.
	 * @param mixed $data Input data.
	 */
	public function submit_file_submission( $student_id, $course_id, $data ) {
		return $this->data_store->submit_file_submission( $this, $student_id, $course_id, $data );
	}

	/**
	 * Get Content counts
	 *
	 * @param string $context Context.
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_drip_feed( $context = 'view' ) {
		return $this->get_prop( 'drip_feed', $context ) ?? array();
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
	 * Get Content counts
	 *
	 * @param string $context Context.
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_download_resource( $context = 'view' ) {
		return $this->get_prop( 'download_resource', $context ) ?? array();
	}

	/**
	 * Get Content counts
	 *
	 * @param string $context Context.
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_prerequisites( $context = 'view' ) {
		return $this->get_prop( 'prerequisites', $context ) ?? array();
	}

	/**
	 * Get drip settings
	 *
	 * @param string $context Context.
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_drip_settings( $context = 'view' ) {
		return $this->get_prop( 'drip_settings', $context ) ?? array();
	}



	/*
	 * ************************************
	 * Setters
	 * ************************************
	 */



	/**
	 * Set prerequisites of the Assignment
	 *
	 * @param mixed $price Price.
	 * @return void
	 * @since 1.0.0
	 */
	public function set_prerequisites( $price ) {
		$this->set_prop( 'prerequisites', $price );
	}

	/**
	 * Set prerequisites of the Assignment
	 *
	 * @param mixed $resource Resource.
	 * @return void
	 * @since 1.0.0
	 */
	public function set_download_resource( $resource ) {
		$this->set_prop( 'download_resource', $resource );
	}

	/**
	 * Set prerequisites of the Assignment
	 *
	 * @param mixed $comments_enable Comments enable.
	 * @return void
	 * @since 1.0.0
	 */
	public function set_enable_comments( $comments_enable ) {
		$this->set_prop( 'enable_comments', $comments_enable );
	}
	/**
	 * Set drip settings of the Assignment
	 *
	 * @param mixed $drip_settings Drip settings.
	 * @return void
	 * @since 1.0.0
	 */
	public function set_drip_settings( $drip_settings ) {
		$this->set_prop( 'drip_settings', $drip_settings );
	}
	/**
	 * Set prerequisites of the Assignment
	 *
	 * @param mixed $price Price.
	 * @return void
	 * @since 1.0.0
	 */
	public function set_content( $price ) {
		$this->set_prop( 'content', $price );
	}

	/**
	 * Get the time limit.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_time_limit(): string {
		return $this->get_prop( 'time_limit' ) ?? '';
	}

	/**
	 * Set the time limit.
	 *
	 * @param string $time_limit Time limit.
	 * @since 1.0.0
	 */
	public function set_time_limit( string $time_limit ) {
		$exiting_limit = get_post_meta( $this->get_id(), '_time_limit', true );

		if ( $this->get_id() && (string) $exiting_limit !== $time_limit ) {
			$this->delete_assignment_deadline();
		}
		$this->set_prop( 'time_limit', $time_limit );
	}
	/**
	 * Get the time limit.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_enable_time_limit(): string {
		return $this->get_prop( 'enable_time_limit' ) ?? '';
	}

	/**
	 * Set the time limit.
	 *
	 * @param string $time_limit Time limit.
	 * @since 1.0.0
	 */
	public function set_enable_time_limit( string $time_limit ) {
		$this->set_prop( 'enable_time_limit', $time_limit );
	}
	/**
	 * Get the time limit.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_time_limit_type(): string {
		return $this->get_prop( 'time_limit_type' ) ?? '';
	}

	/**
	 * Set the time limit.
	 *
	 * @param string $time_limit_type Time limit type.
	 * @since 1.0.0
	 */
	public function set_time_limit_type( string $time_limit_type ) {
		$exiting_limit_type = get_post_meta( $this->get_id(), '_time_limit_type', true );
		if ( $this->get_id() && (string) $exiting_limit_type !== $time_limit_type ) {
			$this->delete_assignment_deadline();
		}
		$this->set_prop( 'time_limit_type', $time_limit_type );
	}

	/**
	 * Get the total points.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_total_points(): string {
		return $this->get_prop( 'total_points' ) ?? '';
	}

	/**
	 * Set the total points.
	 *
	 * @param string $total_points Total points.
	 * @since 1.0.0
	 */
	public function set_total_points( string $total_points ) {
		$this->set_prop( 'total_points', $total_points );
	}

	/**
	 * Get the maximum pass points.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_maximum_pass_points(): string {
		return $this->get_prop( 'maximum_pass_points' ) ?? '';
	}

	/**
	 * Set the maximum pass points.
	 *
	 * @param string $maximum_pass_points Maximum pass points.
	 * @since 1.0.0
	 */
	public function set_maximum_pass_points( string $maximum_pass_points ) {
		$this->set_prop( 'maximum_pass_points', $maximum_pass_points );
	}

	/**
	 * Get the allow upload files status.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function get_allow_upload_files(): bool {
		return $this->get_prop( 'allow_upload_files' ) ?? true;
	}

	/**
	 * Set the allow upload files status.
	 *
	 * @param bool $allow_upload_files Allow upload files.
	 * @since 1.0.0
	 */
	public function set_allow_upload_files( bool $allow_upload_files ) {
		$this->set_prop( 'allow_upload_files', $allow_upload_files );
	}

	/**
	 * Get the number of files.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_number_of_files(): string {
		return $this->get_prop( 'number_of_files' ) ?? '';
	}

	/**
	 * Set the number of files.
	 *
	 * @param string $number_of_files Number of files.
	 * @since 1.0.0
	 */
	public function set_number_of_files( string $number_of_files ) {
		$this->set_prop( 'number_of_files', $number_of_files );
	}

	/**
	 * Get the enable file size limit status.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_enable_file_size_limit(): string {
		return $this->get_prop( 'enable_file_size_limit' ) ?? '';
	}

	/**
	 * Set the enable file size limit status.
	 *
	 * @param string $enable_file_size_limit Enable file size limit.
	 * @since 1.0.0
	 */
	public function set_enable_file_size_limit( string $enable_file_size_limit ) {
		$this->set_prop( 'enable_file_size_limit', $enable_file_size_limit );
	}

	/**
	 * Get the max file size limit.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_max_file_size_limit(): string {
		return $this->get_prop( 'max_file_size_limit' ) ?? '';
	}

	/**
	 * Set the max file size limit.
	 *
	 * @param string $max_file_size_limit Max file size limit.
	 * @since 1.0.0
	 */
	public function set_max_file_size_limit( string $max_file_size_limit ) {
		$this->set_prop( 'max_file_size_limit', $max_file_size_limit );
	}



	/**
	 * Check if assignment exists or not
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function exists(): bool {
		return true;
	}


	/**
	 * Check if assignment is purchasable
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function is_purchasable(): bool {
		return apply_filters( 'ohmylms_lesson_is_purchasable', true, $this );
	}


	/**
	 * Check if assignment is available for enrolment
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function is_in_stock(): bool {
		return apply_filters( 'ohmylms_lesson_is_in_stock', true, $this );
	}

	/**
	 * Save or update the assignment object in DB
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public function save() {
		if ( ! $this->data_store ) {
			return $this->get_id();
		}

		/**
		 * Fires before saving the assignment object.
		 *
		 * This action allows developers to perform custom actions before the assignment object is saved.
		 *
		 * @param Assignment $this The assignment object being saved.
		 * @param DataStores $data_store The data store object handling the assignment data.
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
		 * Fires after saving the assignment object.
		 *
		 * This action allows developers to perform custom actions after the assignment object is saved.
		 *
		 * @param Assignment $this The assignment object being saved.
		 * @param DataStores $data_store The data store object handling the assignment data.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_after_' . $this->object_type . '_object_save', $this, $this->data_store );

		return $this->get_id();
	}


	/**
	 * Delete assignment data
	 *
	 * @param array $args Operation arguments.
	 * @since 1.0.0
	 */
	public function delete( $args = array() ) {
		$this->data_store->delete( $this, $args );
	}

	/**
	 * Get report for Assignment.
	 */
	public function get_report() {
		return $this->data_store->get_report( $this );
	}

	/**
	 * Get assignment attempts for Assignment.
	 *
	 * @param mixed $student_id Student identifier.
	 */
	public function get_assignment_attempts( $student_id ) {
		return $this->data_store->get_assignment_attempts( $this, $student_id );
	}

	/**
	 * Update assignment attempts for Assignment.
	 *
	 * @param mixed $attempt_id Attempt identifier.
	 * @param mixed $get_data Get data.
	 */
	public function update_assignment_attempts( $attempt_id, $get_data ) {
		return $this->data_store->update_assignment_attempts( $this, $attempt_id, $get_data );
	}

	/**
	 * Delete assignment deadline for Assignment.
	 */
	private function delete_assignment_deadline() {

		global $wpdb;
		$meta_key_pattern = '_ohmylms_deadline_' . get_current_user_id() . '_%';
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$meta_key_pattern
			)
		);
	}
}
