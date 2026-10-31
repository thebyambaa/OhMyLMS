<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Student class.
 *
 * @package OhMyLMS
 * @subpackage Student
 * @since 1.0.0
 */

namespace OhMyLMS\Data;

use OhMyLMS\Abstracts\Data;
use OhMyLMS\DataStores\DataStores;

/**
 * Student class.
 *
 * @since 1.0.0
 */

class Student extends Data {

	/**
	 * The password of the student.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $password;

	/**
	 * The data store for the student.
	 *
	 * @var DataStores\Student
	 * @since 1.0.0
	 */
	protected string $data_store_name = 'student';

	/**
	 * The object type for the student.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected string $object_type = 'student';

	/**
	 * The default properties of the student.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected array $data = array(
		'date_created'                    => null,
		'date_modified'                   => null,
		'email'                           => '',
		'first_name'                      => '',
		'last_name'                       => '',
		'display_name'                    => '',
		'role'                            => 'subscriber',
		'username'                        => '',
		'bio'                             => '',
		'interest'                        => '',
		'skills'                          => '',
		'social_links'                    => '',
		'address'                         => '',
		'country'                         => '',
		'city'                            => '',
		'postcode'                        => '',
		'state'                           => '',
		'phone'                           => '',
		'whatsapp'                        => '',
		'timezone'                        => '',
		'cover_image'                     => '',
		'profile_image'                   => '',
		'notification_chapter'            => 'off',
		'notification_direct_messages'    => 'off',
		'notification_new_course_content' => 'off',
		'notification_comments_replies'   => 'off',
	);

	/**
	 * Constructor for the Student class.
	 *
	 * @param int|Student $data Student data or ID.
	 * @since 1.0.0
	 */
	public function __construct( $data = 0 ) {
		if ( $data instanceof Student ) {
			$this->set_id( absint( $data->get_id() ) );
		} elseif ( is_numeric( $data ) ) {
			$this->set_id( $data );
		}

		$this->data_store = DataStores::load( 'student' );

		if ( $this->get_id() ) {
			try {
				$this->data_store->read( $this );
			} catch ( \Exception $e ) {
				$this->set_id( 0 );
			}
		}
	}


	/**
	 * Set the email of the student.
	 *
	 * @param string $email The email to set.
	 * @since 1.0.0
	 */
	public function set_email( $email ) {
		$this->set_prop( 'email', sanitize_email( $email ) );
	}

	/**
	 * Set the username of the student.
	 *
	 * @param string $username The username to set.
	 * @since 1.0.0
	 */
	public function set_username( $username ) {
		$this->set_prop( 'username', sanitize_text_field( $username ) );
	}

	/**
	 * Set the password of the student.
	 *
	 * @param string $password The password to set.
	 * @since 1.0.0
	 */
	public function set_password( $password ) {
		$this->password = $password;
	}

	/**
	 * Set the first name of the student.
	 *
	 * @param string $first_name The first name to set.
	 * @since 1.0.0
	 */
	public function set_first_name( $first_name ) {
		$this->set_prop( 'first_name', sanitize_text_field( $first_name ) );
	}

	/**
	 * Set the last name of the student.
	 *
	 * @param string $last_name The last name to set.
	 * @since 1.0.0
	 */
	public function set_last_name( $last_name ) {
		$this->set_prop( 'last_name', sanitize_text_field( $last_name ) );
	}

	/**
	 * Set the address of the student.
	 *
	 * @param string $address The address to set.
	 * @since 1.0.0
	 */
	public function set_address( $address ) {
		$this->set_prop( 'address', sanitize_text_field( $address ) );
	}


	/**
	 * Set the country of the student.
	 *
	 * @param string $country The address to set.
	 * @since 1.0.0
	 */
	public function set_country( $country ) {
		$this->set_prop( 'country', sanitize_text_field( $country ) );
	}

	/**
	 * Set the city of the student.
	 *
	 * @param string $city The city to set.
	 * @since 1.0.0
	 */
	public function set_city( $city ) {
		$this->set_prop( 'city', sanitize_text_field( $city ) );
	}

	/**
	 * Set the postcode of the student.
	 *
	 * @param string $postcode The postcode to set.
	 * @since 1.0.0
	 */
	public function set_postcode( $postcode ) {
		$this->set_prop( 'postcode', sanitize_text_field( $postcode ) );
	}

	/**
	 * Set the state of the student.
	 *
	 * @param string $state The state to set.
	 * @since 1.0.0
	 */
	public function set_state( $state ) {
		$this->set_prop( 'state', sanitize_text_field( $state ) );
	}

	/**
	 * Set the phone number of the student.
	 *
	 * @param string $phone The phone number to set.
	 * @since 1.0.0
	 */
	public function set_phone( $phone ) {
		$this->set_prop( 'phone', sanitize_text_field( $phone ) );
	}

	/**
	 * Set the WhatsApp number of the student.
	 *
	 * @param string $whatsapp The WhatsApp number to set.
	 * @since 1.0.0
	 */
	public function set_whatsapp( $whatsapp ) {
		$this->set_prop( 'whatsapp', sanitize_text_field( $whatsapp ) );
	}

	/**
	 * Set the timezone of the student.
	 *
	 * @param string $timezone The timezone to set.
	 * @since 1.0.0
	 */
	public function set_timezone( $timezone ) {
		$this->set_prop( 'timezone', sanitize_text_field( $timezone ) );
	}

	/**
	 * Set the display name of the student.
	 *
	 * @param string $display_name The display name to set.
	 * @since 1.0.0
	 */
	public function set_display_name( $display_name ) {
		// Translators: %1$s is the first name, %2$s is the last name.
		$this->set_prop( 'display_name', is_email( $display_name ) ? sprintf( _x( '%1$s %2$s', 'display name', 'ohmylms' ), $this->get_first_name(), $this->get_last_name() ) : $display_name );
	}

	/**
	 * Set the bio of the student.
	 *
	 * @param string $value The bio to set.
	 * @since 1.0.0
	 */
	public function set_bio( $value ) {
		$this->set_prop( 'bio', sanitize_text_field( $value ) );
	}

	/**
	 * Set the interests of the student.
	 *
	 * @param string $value The interests to set.
	 * @since 1.0.0
	 */
	public function set_interest( $value ) {
		$this->set_prop( 'interest', sanitize_text_field( $value ) );
	}

	/**
	 * Set the skills of the student.
	 *
	 * @param array $value The skills to set.
	 * @since 1.0.0
	 */
	public function set_skills( $value ) {
		if ( is_array( $value ) ) {
			// Sanitize each skill and remove empty ones.
			$sanitized_skills = array_filter( array_map( 'sanitize_text_field', $value ) );
			$this->set_prop( 'skills', wp_json_encode( array_values( $sanitized_skills ) ) );
		} else {
			$this->set_prop( 'skills', '' );
		}
	}

	/**
	 * Set the social links of the student.
	 *
	 * @param array|string $social_links Array of social links or labels array (for backward compatibility).
	 * @param array        $urls Array of link URLs (optional, for backward compatibility).
	 * @since 1.0.0
	 */
	public function set_social_links( $social_links, $urls = null ) {
		$links = array();

		// Handle empty string or null (set empty).
		if ( empty( $social_links ) || '' === $social_links ) {
			$this->set_prop( 'social_links', '' );
			return;
		}

		// Handle new format: array of objects with 'label' and 'url' keys.
		if ( is_array( $social_links ) && null === $urls ) {
			foreach ( $social_links as $link ) {
				if ( is_array( $link ) && isset( $link['label'] ) && isset( $link['url'] ) ) {
					$label = sanitize_text_field( $link['label'] );
					$url   = esc_url_raw( $link['url'] );

					if ( ! empty( $label ) && ! empty( $url ) ) {
						$links[] = array(
							'label' => $label,
							'url'   => $url,
						);
					}
				}
			}
		}
		// Handle old format: separate arrays for labels and URLs.
		elseif ( is_array( $social_links ) && is_array( $urls ) ) {
			$count = min( count( $social_links ), count( $urls ) );

			for ( $i = 0; $i < $count; $i++ ) {
				$label = sanitize_text_field( $social_links[ $i ] );
				$url   = esc_url_raw( $urls[ $i ] );

				// Only add if both label and URL are not empty.
				if ( ! empty( $label ) && ! empty( $url ) ) {
					$links[] = array(
						'label' => $label,
						'url'   => $url,
					);
				}
			}
		}

		$this->set_prop( 'social_links', wp_json_encode( $links ) );
	}

	/**
	 * Set the cover image of the student.
	 *
	 * @param string $value The URL of the cover image to set.
	 * @since 1.0.0
	 */
	public function set_cover_image( $value ) {
		$this->set_prop( 'cover_image', sanitize_url( $value ) );
	}

	/**
	 * Set the profile image of the student.
	 *
	 * @param string $value The URL of the profile image to set.
	 * @since 1.0.0
	 */
	public function set_profile_image( $value ) {
		$this->set_prop( 'profile_image', sanitize_url( $value ) );
	}


	/**
	 * Set notification chapter for Student.
	 *
	 * @param mixed $value Value to process.
	 */
	public function set_notification_chapter( $value ) {
		$this->set_prop( 'notification_chapter', sanitize_text_field( $value ) );
	}

	/**
	 * Get notification chapter for Student.
	 *
	 * @param string $context Context.
	 */
	public function get_notification_chapter( $context = 'view' ) {
		return $this->get_prop( 'notification_chapter', $context );
	}

	/**
	 * Set notification direct messages for Student.
	 *
	 * @param mixed $value Value to process.
	 */
	public function set_notification_direct_messages( $value ) {
		$this->set_prop( 'notification_direct_messages', sanitize_text_field( $value ) );
	}


	/**
	 * Get notification direct messages for Student.
	 *
	 * @param string $context Context.
	 */
	public function get_notification_direct_messages( $context = 'view' ) {
		return $this->get_prop( 'notification_direct_messages', $context );
	}


	/**
	 * Set notification new course content for Student.
	 *
	 * @param mixed $value Value to process.
	 */
	public function set_notification_new_course_content( $value ) {
		$this->set_prop( 'notification_new_course_content', sanitize_text_field( $value ) );
	}

	/**
	 * Get notification new course content for Student.
	 *
	 * @param string $context Context.
	 */
	public function get_notification_new_course_content( $context = 'view' ) {
		return $this->get_prop( 'notification_new_course_content', $context );
	}

	/**
	 * Set notification comments replies for Student.
	 *
	 * @param mixed $value Value to process.
	 */
	public function set_notification_comments_replies( $value ) {
		$this->set_prop( 'notification_comments_replies', sanitize_text_field( $value ) );
	}

	/**
	 * Get notification comments replies for Student.
	 *
	 * @param string $context Context.
	 */
	public function get_notification_comments_replies( $context = 'view' ) {
		return $this->get_prop( 'notification_comments_replies', $context );
	}


	/**
	 * Get the email of the student.
	 *
	 * @return string The email of the student.
	 * @since 1.0.0
	 * @param string $context Context.
	 */
	public function get_email( $context = 'view' ) {
		return $this->get_prop( 'email', $context );
	}

	/**
	 * Get the username of the student.
	 *
	 * @param string $context The context for the username retrieval.
	 * @return string The username of the student.
	 * @since 1.0.0
	 */
	public function get_username( $context = 'view' ) {
		return $this->get_prop( 'username', $context );
	}

	/**
	 * Get the password of the student.
	 *
	 * @return string The password of the student.
	 * @since 1.0.0
	 */
	public function get_password() {
		return $this->password;
	}


	/**
	 * Get the first name of the student.
	 *
	 * @return string The first name of the student.
	 * @since 1.0.0
	 * @param string $context Context.
	 */
	public function get_first_name( $context = 'view' ) {
		return $this->get_prop( 'first_name', $context );
	}

	/**
	 * Get the last name of the student.
	 *
	 * @return string The last name of the student.
	 * @since 1.0.0
	 * @param string $context Context.
	 */
	public function get_last_name( $context = 'view' ) {
		return $this->get_prop( 'last_name', $context );
	}

	/**
	 * Get the address of the student.
	 *
	 * @return string The address of the student.
	 * @since 1.0.0
	 * @param string $context Context.
	 */
	public function get_address( $context = 'view' ) {
		return $this->get_prop( 'address', $context );
	}


	/**
	 * Get the country of the student.
	 *
	 * @return string The country of the student.
	 * @since 1.0.0
	 * @param string $context Context.
	 */
	public function get_country( $context = 'view' ) {
		return $this->get_prop( 'country', $context );
	}

	/**
	 * Get the city of the student.
	 *
	 * @return string The city of the student.
	 * @since 1.0.0
	 * @param string $context Context.
	 */
	public function get_city( $context = 'view' ) {
		return $this->get_prop( 'city', $context );
	}

	/**
	 * Get the postcode of the student.
	 *
	 * @return string The postcode of the student.
	 * @since 1.0.0
	 * @param string $context Context.
	 */
	public function get_postcode( $context = 'view' ) {
		return $this->get_prop( 'postcode', $context );
	}

	/**
	 * Get the state of the student.
	 *
	 * @return string The state of the student.
	 * @since 1.0.0
	 * @param string $context Context.
	 */
	public function get_state( $context = 'view' ) {
		return $this->get_prop( 'state', $context );
	}

	/**
	 * Get the phone number of the student.
	 *
	 * @return string The phone number of the student.
	 * @since 1.0.0
	 * @param string $context Context.
	 */
	public function get_phone( $context = 'view' ) {
		return $this->get_prop( 'phone', $context );
	}

	/**
	 * Get the WhatsApp number of the student.
	 *
	 * @return string The WhatsApp number of the student.
	 * @since 1.0.0
	 * @param string $context Context.
	 */
	public function get_whatsapp( $context = 'view' ) {
		return $this->get_prop( 'whatsapp', $context );
	}

	/**
	 * Get the timezone of the student.
	 *
	 * @return string The timezone of the student.
	 * @since 1.0.0
	 * @param string $context Context.
	 */
	public function get_timezone( $context = 'view' ) {
		return $this->get_prop( 'timezone', $context );
	}

	/**
	 * Get the display name of the student.
	 *
	 * @param string $context The context for the display name retrieval.
	 * @return string The display name of the student.
	 *
	 * @since 1.0.0
	 */
	public function get_display_name( $context = 'view' ) {
		return $this->get_prop( 'display_name', $context );
	}


	/**
	 * Get course count for Student.
	 */
	public function get_course_count() {
		return $this->data_store->get_course_count( $this );
	}

	/**
	 * Get the bio of the student.
	 *
	 * @param string $context The context for the bio retrieval.
	 * @return string The bio of the student.
	 * @since 1.0.0
	 */
	public function get_bio( $context = 'view' ) {
		return $this->get_prop( 'bio', $context );
	}

	/**
	 * Get the interests of the student.
	 *
	 * @param string $context The context for the interests retrieval.
	 * @return string The interests of the student.
	 * @since 1.0.0
	 */
	public function get_interest( $context = 'view' ) {
		return $this->get_prop( 'interest', $context );
	}

	/**
	 * Get the skills of the student.
	 *
	 * @param string $context The context for the skills retrieval.
	 * @return array The skills of the student.
	 * @since 1.0.0
	 */
	public function get_skills( $context = 'view' ) {
		$skills = $this->get_prop( 'skills', $context );
		if ( is_string( $skills ) ) {
			$decoded = json_decode( $skills, true );
			return is_array( $decoded ) ? $decoded : array();
		}
		return is_array( $skills ) ? $skills : array();
	}

	/**
	 * Get the social links of the student.
	 *
	 * @param string $context The context for the social links retrieval.
	 * @return array The social links of the student.
	 * @since 1.0.0
	 */
	public function get_social_links( $context = 'view' ) {
		$links = $this->get_prop( 'social_links', $context );
		if ( is_string( $links ) ) {
			$decoded = json_decode( $links, true );
			return is_array( $decoded ) ? $decoded : array();
		}
		return is_array( $links ) ? $links : array();
	}

	/**
	 * Get the course count by status.
	 *
	 * @since 1.0.0
	 * @return mixed
	 */
	public function get_enrolled_course_count() {
		return $this->data_store->get_enrolled_course_count( $this );
	}


	/**
	 * Get the membership count.
	 *
	 * @since 1.0.0
	 * @return mixed
	 */
	public function get_memebership_count() {
		return $this->data_store->get_memebership_count( $this );
	}


	/**
	 * Get the course count by status.
	 *
	 * @since 1.0.0
	 * @return mixed
	 */
	public function get_completed_course_count() {
		return $this->data_store->get_completed_course_count( $this );
	}

	/**
	 * Get the course count by status.
	 *
	 * @since 1.0.0
	 * @return mixed
	 */
	public function get_progress_course_count() {
		return $this->data_store->get_progress_course_count( $this );
	}

	/**
	 * Get the course count by status.
	 *
	 * @since 1.0.0
	 * @return mixed
	 */
	public function get_courses() {
		return $this->data_store->get_courses( $this );
	}

	/**
	 * Get the course count by status.
	 *
	 * @since 1.0.0
	 * @return mixed
	 */
	public function get_enrolled_courses() {
		return $this->data_store->get_enrolled_courses( $this );
	}

	/**
	 * Get the course count by status.
	 *
	 * @since 1.0.0
	 * @return mixed
	 */
	public function get_progress_course() {
		return $this->data_store->get_progress_course( $this );
	}

	/**
	 * Get the course count by status.
	 *
	 * @since 1.0.0
	 * @return mixed
	 */
	public function get_completed_course() {
		return $this->data_store->get_completed_course( $this );
	}

	/**
	 * Get the course count by status.
	 *
	 * @since 1.0.0
	 * @return mixed
	 * @param mixed $course_id Course identifier.
	 */
	public function get_course_progress_percentage( $course_id ) {
		return $this->data_store->get_course_progress_percentage( $this, $course_id );
	}

	/**
	 * Get the course count by status.
	 *
	 * @since 1.0.0
	 * @return mixed
	 * @param mixed $course_id Course identifier.
	 */
	public function get_course_total_points( $course_id ) {
		return $this->data_store->get_course_total_points( $this, $course_id );
	}
	/**
	 * Get the course count by status.
	 *
	 * @since 1.0.0
	 * @return mixed
	 * @param mixed $course_id Course identifier.
	 */
	public function get_course_completed_points( $course_id ) {
		return $this->data_store->get_course_completed_points( $this, $course_id );
	}
	/**
	 * Get the course count by status.
	 *
	 * @since 1.0.0
	 * @return mixed
	 * @param mixed $course_id Course identifier.
	 */
	public function get_course_time_remaining( $course_id ) {
		return $this->data_store->get_course_time_remaining( $this, $course_id );
	}
	/**
	 * Get the course count by status.
	 *
	 * @since 1.0.0
	 * @return mixed
	 * @param mixed $course_id Course identifier.
	 */
	public function get_course_resume_url( $course_id ) {
		return $this->data_store->get_course_resume_url( $this, $course_id );
	}
	/**
	 * Get the course count by status.
	 *
	 * @since 1.0.0
	 * @return mixed
	 * @param mixed $course_id Course identifier.
	 */
	public function get_course_certificate_url( $course_id ) {
		return $this->data_store->get_course_certificate_url( $this, $course_id );
	}
	/**
	 * Get the course count by status.
	 *
	 * @since 1.0.0
	 * @return mixed
	 * @param mixed $course_id Course identifier.
	 */
	public function is_course_in_progress( $course_id ) {
		return $this->data_store->is_course_in_progress( $this, $course_id );
	}
	/**
	 * Get the course count by status.
	 *
	 * @since 1.0.0
	 * @return mixed
	 * @param mixed $course_id Course identifier.
	 */
	public function is_course_completed( $course_id ) {
		return $this->data_store->is_course_completed( $this, $course_id );
	}

	/**
	 * Check if the student is maybe enrolled in a course.
	 *
	 * @param int $course_id The ID of the course.
	 * @return bool True if the student is maybe enrolled, false otherwise.
	 *
	 * @since 1.0.0
	 */
	public function maybe_enrolled( $course_id ) {
		return $this->data_store->maybe_enrolled( $this, $course_id );
	}
	/**
	 * Check if the student is maybe enrolled in a course.
	 *
	 * @param int $course_id The ID of the course.
	 * @return bool True if the student is maybe enrolled, false otherwise.
	 *
	 * @since 1.0.0
	 */
	public function get_course_completed_date( $course_id ) {
		return $this->data_store->get_course_completed_date( $this, $course_id );
	}

	/**
	 * Get the cover image of the student.
	 *
	 * @param string $context The context for the cover image retrieval.
	 * @return string The URL of the cover image.
	 *
	 * @since 1.0.0
	 */
	public function get_cover_image( $context = 'view' ) {
		$cover_image = $this->get_prop( 'cover_image', $context );
		// The store saves the `edit` value: storing the placeholder would make has_cover_image() true.
		if ( empty( $cover_image ) && 'edit' !== $context ) {
			$cover_image = OHMYLMS_URL . '/assets/images/student-cover-image-placeholder.jpeg';
		}
		return $cover_image;
	}

	/**
	 * Check if the student has a cover image.
	 *
	 * @return bool True if the student has a cover image, false otherwise.
	 * @since 1.0.0
	 */
	public function has_cover_image() {
		return $this->data_store->has_cover_image( $this );
	}

	/**
	 * Get the profile image of the student.
	 *
	 * @param string $context The context for the profile image retrieval.
	 * @return string The URL of the profile image.
	 *
	 * @since 1.0.0
	 */
	public function get_profile_image( $context = 'view' ) {
		return $this->get_prop( 'profile_image', $context );
	}

	/**
	 * Get the full name of the student.
	 *
	 * @return string The full name of the student.
	 * @since 1.0.0
	 */
	public function get_name() {

		$name = trim( $this->get_first_name() . ' ' . $this->get_last_name() );

		if ( ! $name ) {
			$name = $this->get_display_name();
		}

		return $name;
	}

	/**
	 * Complete lesson for Student.
	 *
	 * @param mixed $lesson_id Lesson identifier.
	 * @param mixed $course_id Course identifier.
	 */
	public function complete_lesson( $lesson_id, $course_id ) {
		return $this->data_store->complete_lesson( $this, $lesson_id, $course_id );
	}

	/**
	 * Maybe completed for Student.
	 *
	 * @param mixed $lesson_id Lesson identifier.
	 */
	public function maybe_completed( $lesson_id ) {
		return $this->data_store->maybe_completed( $this, $lesson_id );
	}

	/**
	 * Get the count of assignment in the course.
	 *
	 * @return int The number of lessons.
	 * @since 1.0.0
	 * @param mixed $course_id Course identifier.
	 */
	public function get_completed_content_count( $course_id ) {
		return $this->data_store->get_completed_content_count( $this, $course_id ) ?? 0;
	}
	/**
	 * Get the count of assignment in the course.
	 *
	 * @return int The number of lessons.
	 * @since 1.0.0
	 * @param mixed $course_id Course identifier.
	 * @param mixed $content_type Content type.
	 */
	public function get_completed_content_count_by_type( $course_id, $content_type ) {
		return $this->data_store->get_completed_content_count_by_type( $this, $course_id, $content_type ) ?? 0;
	}
	/**
	 * Get the count of assignment in the course.
	 *
	 * @return int The number of lessons.
	 * @since 1.0.0
	 * @param mixed $course_id Course identifier.
	 */
	public function get_over_all_completion_rate( $course_id ) {
		return $this->data_store->get_over_all_completion_rate( $this, $course_id ) ?? 0;
	}
	/**
	 * Get the count of assignment in the course.
	 *
	 * @return int The number of lessons.
	 * @since 1.0.0
	 * @param mixed $course_id Course identifier.
	 */
	public function get_quiz_completion_rate( $course_id ) {
		return $this->data_store->get_quiz_completion_rate( $this, $course_id ) ?? 0;
	}
	/**
	 * Get the count of assignment in the course.
	 *
	 * @return int The number of lessons.
	 * @since 1.0.0
	 * @param mixed $course_id Course identifier.
	 */
	public function get_assignment_completion_rate( $course_id ) {
		return $this->data_store->get_assignment_completion_rate( $this, $course_id ) ?? 0;
	}

	/**
	 * Get memberships for Student.
	 */
	public function get_memberships() {
		return $this->data_store->get_memberships( $this ) ?? 0;
	}

	/**
	 * Get enrolled memberships for Student.
	 */
	public function get_enrolled_memberships() {
		return $this->data_store->get_enrolled_memberships( $this ) ?? 0;
	}

	/**
	 * Get all assignment attempts for Student.
	 *
	 * @param mixed $course_id Course identifier.
	 */
	public function get_all_assignment_attempts( $course_id ) {
		return $this->data_store->get_all_assignment_attempts( $this, $course_id ) ?? array();
	}

	/**
	 * Get assignment remaining time for Student.
	 *
	 * @param mixed $content_id Content identifier.
	 */
	public function get_assignment_remaining_time( $content_id ) {
		return $this->data_store->get_assignment_remaining_time( $this, $content_id ) ?? 0;
	}


	/**
	 * Check student is banned or not
	 *
	 * @return bool True if the student is banned, false otherwise.
	 *
	 * @since 1.0.0
	 */
	public function maybe_banned() {
		return $this->data_store->maybe_banned( $this );
	}

	/**
	 * Check if reminder already sent to student for specific course.
	 *
	 * @param int $course_id Course ID.
	 * @return bool True if reminder sent, false otherwise.
	 */
	public function maybe_reminder_sent( $course_id ) {
		return $this->data_store->maybe_reminder_sent( $this, $course_id );
	}


	/**
	 * Get last reminder sent to student for specific course.
	 *
	 * @param int $course_id Course ID.
	 * @return array|false Reminder data or false if no reminder found.
	 *
	 * @since 1.0.0
	 */
	public function get_last_reminder( $course_id ) {
		return $this->data_store->get_last_reminder( $this, $course_id );
	}


	/**
	 * Get the total number of completed orders, optionally for a specific student.
	 *
	 * @return int
	 *
	 * @since 1.0.0
	 */
	public function get_total_orders() {
		return $this->data_store->get_total_orders( $this );
	}

	/**
	 * Get the total revenue from completed orders, optionally for a specific student.
	 *
	 * @return float
	 */
	public function get_total_revenue() {
		return $this->data_store->get_total_revenue( $this );
	}

	/**
	 * Get the average order value (AOV) from completed orders, optionally for a specific student.
	 *
	 * @return float
	 */
	public function get_aov() {
		return $this->data_store->get_aov( $this );
	}
}
