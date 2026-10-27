<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
namespace OhMyLMS\Data;

use OhMyLMS\Abstracts\Data;
use OhMyLMS\DataStores\DataStores;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin attempt implementation.
 */
class Attempt extends Data {
	/**
	 * Data store name.
	 *
	 * @var mixed
	 */
	protected string $data_store_name = 'attempt';
	/**
	 * Object type.
	 *
	 * @var mixed
	 */
	public string $object_type = 'attampt';
	/**
	 * Data.
	 *
	 * @var mixed
	 */
	protected array $data = array(
		'id'         => 0,
		'quiz_id'    => 0,
		'student_id' => 0,
		'course_id'  => 0,
		'total'      => 0,
		'start_date' => '',
		'end_date'   => '',
	);

	/**
	 * Attempt constructor.
	 *
	 * @param mixed $attempt Attempt object or ID.
	 */
	public function __construct( $attempt = '' ) {
		if ( is_numeric( $attempt ) && $attempt > 0 ) {
			$this->set_id( $attempt );
		} elseif ( $attempt instanceof self ) {
			$this->set_id( \absint( $attempt->get_id() ) );
		} elseif ( ! empty( $attempt->ID ) ) {
			$this->set_id( \absint( $attempt->ID ) );
		}

		$this->data_store = DataStores::load( $this->data_store_name );

		if ( $this->get_id() > 0 ) {
			$this->data_store->read( $this );
		}
	}

	/**
	 * Get the attempt ID.
	 *
	 * @return int
	 */
	public function get_id() {
		return $this->get_prop( 'id' );
	}

	/**
	 * Set the attempt ID.
	 *
	 * @param int $id Id.
	 */
	public function set_id( $id ) {
		$this->set_prop( 'id', $id );
	}

	/**
	 * Get the quiz ID.
	 *
	 * @return int
	 */
	public function get_quiz_id() {
		return $this->get_prop( 'quiz_id' );
	}

	/**
	 * Set the quiz ID.
	 *
	 * @param int $quiz_id Quiz identifier.
	 */
	public function set_quiz_id( $quiz_id ) {
		$this->set_prop( 'quiz_id', $quiz_id );
	}

	/**
	 * Get the student ID.
	 *
	 * @return int
	 */
	public function get_student_id() {
		return $this->get_prop( 'student_id' );
	}

	/**
	 * Set the student ID.
	 *
	 * @param int $student_id Student identifier.
	 */
	public function set_student_id( $student_id ) {
		$this->set_prop( 'student_id', $student_id );
	}

	/**
	 * Get the course ID.
	 *
	 * @return int
	 */
	public function get_course_id() {
		return $this->get_prop( 'course_id' );
	}

	/**
	 * Set the course ID.
	 *
	 * @param int $course_id Course identifier.
	 */
	public function set_course_id( $course_id ) {
		$this->set_prop( 'course_id', $course_id );
	}

	/**
	 * Get the total.
	 *
	 * @return int
	 */
	public function get_total() {
		return $this->get_prop( 'total' );
	}

	/**
	 * Set the total.
	 *
	 * @param int $total Total.
	 */
	public function set_total( $total ) {
		$this->set_prop( 'total', $total );
	}

	/**
	 * Get the start date.
	 *
	 * @return string
	 */
	public function get_start_date() {
		return $this->get_prop( 'start_date' );
	}

	/**
	 * Set the start date.
	 *
	 * @param string $start_date Start date.
	 */
	public function set_start_date( $start_date ) {
		$this->set_date_prop( 'start_date', $start_date );
	}

	/**
	 * Get the end date.
	 *
	 * @return string
	 */
	public function get_end_date() {
		return $this->get_prop( 'end_date' );
	}

	/**
	 * Set the end date.
	 *
	 * @param string $end_date End date.
	 */
	public function set_end_date( $end_date ) {
		$this->set_date_prop( 'end_date', $end_date );
	}

	/**
	 * Get the ID of the student.
	 *
	 * @return int The ID of the attempt.
	 *
	 * @since 1.0.0
	 */
	public function get_student() {
		return $this->data_store->get_student( $this );
	}

	/**
	 * Get the total score for the attempt.
	 *
	 * @return int The total score for the attempt.
	 * @since 1.0.0
	 */
	public function get_total_score() {
		return $this->data_store->get_total_score( $this );
	}
}
