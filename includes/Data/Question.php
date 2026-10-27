<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Question class to handle question data
 *
 * @package ohmylms
 * @since 1.0.0
 */

namespace OhMyLMS\Data;

use OhMyLMS\CPTData\PostTypeData;
use OhMyLMS\DataStores\DataStores;

defined( 'ABSPATH' ) || exit;

/**
 * Class Quiz
 *
 * @package OhMyLMS\Data
 * @since 1.0.0
 */
class Question extends PostTypeData {

	/**
	 * Name of the store
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected string $data_store_name = 'question';

	/**
	 * Object type
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public string $object_type = 'question';

	/**
	 * Quiz data
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected array $data = array(
		'name'          => '',
		'description'   => '',
		'questions'     => '',
		'slug'          => '',
		'settings'      => array(),
		'thumbnail_id'  => '',
		'video_id'      => '',
		'date_created'  => null,
		'date_modified' => null,
	);

	/**
	 * Quiz constructor.
	 *
	 * @param mixed $question Question object or ID.
	 * @throws \Exception When the data store cannot be loaded.
	 * @since 1.0.0
	 */
	public function __construct( $question = '' ) {
		if ( is_numeric( $question ) && $question > 0 ) {
			$this->set_id( $question );
		} elseif ( $question instanceof self ) {
			$this->set_id( absint( $question->get_id() ) );
		} elseif ( ! empty( $question->ID ) ) {
			$this->set_id( absint( $question->ID ) );
		}

		$this->data_store = DataStores::load( $this->data_store_name );

		if ( $this->get_id() > 0 ) {
			$this->data_store->read( $this );
		}
	}

	/**
	 * Save question
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
		 * @param Quiz $this The lesson object being saved.
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
	 * Delete question
	 *
	 * @param array $args Arguments for deleting the question.
	 * @since 1.0.0
	 */
	public function delete( $args = array() ) {
		$this->data_store->delete( $this, $args );
	}

	/**
	 * Get the question questions
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_questions(): array {
		return $this->data_store->get_questions( $this );
	}

	/**
	 * Get image id for Question.
	 */
	public function get_image_id() {
		return $this->get_thumbnail_id();
	}

	/**
	 * Set the question questions
	 *
	 * @param array $questions Questions array.
	 * @since 1.0.0
	 */
	public function set_questions( array $questions ) {
		$this->data['questions'] = $questions;
	}

	/**
	 * Get the question settings
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_settings(): array {
		return $this->data['settings'];
	}

	/**
	 * Set the question settings
	 *
	 * @param array $settings Settings array.
	 * @since 1.0.0
	 */
	public function set_settings( array $settings ) {
		$this->data['settings'] = $settings;
	}

	/**
	 * Get question data as an array.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_data(): array {
		return $this->data;
	}

	/**
	 * Set question data from an array.
	 *
	 * @param array $data Question data.
	 * @since 1.0.0
	 */
	public function set_data( array $data ) {
		$this->data = $data;
	}

	/**
	 * Set question data from an array.
	 *
	 * @param int   $student_id Student ID.
	 * @param array $data Question data.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function save_attempt_answer( $student_id, $data ) {
		return $this->data_store->save_attempt_answer( $this, $student_id, $data );
	}

	/**
	 * Get question data from an array.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_image_url() {
		$thumbnail_id = $this->get_thumbnail_id();
		if ( empty( $thumbnail_id ) ) {
			return '';
		}
		return wp_get_attachment_image_src( $this->get_thumbnail_id(), 'large' ) ? wp_get_attachment_image_src( $this->get_thumbnail_id(), 'large' )[0] : '';
	}


	/**
	 * Get question data from an array.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_video_url() {
		$video_id = $this->get_video_id();
		if ( empty( $video_id ) ) {
			return '';
		}
		return wp_get_attachment_url( $video_id );
	}

	/**
	 * Get question data from an array.
	 *
	 * @param array $data Question data.
	 * @param int   $question_id Question ID.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function save_quiz_answer( $data, $question_id ) {
		return $this->data_store->save_quiz_answer( $this, $data, $question_id );
	}
}
