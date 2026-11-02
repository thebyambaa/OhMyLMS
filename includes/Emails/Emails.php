<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Plugin emails: registry, stored settings and merge tags.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Emails;

/**
 * Plugin emails implementation.
 *
 * The REST API in `includes/Rest/V1/EmailController.php` (see docs/api/emails.md) uses the same
 * helpers as the sending classes, so what the admin edits is exactly what is sent.
 */
class Emails {

	/**
	 * Option name prefix of one email's stored settings (`create_lms_email_<id>`).
	 */
	const OPTION_PREFIX = 'create_lms_email_';

	/**
	 * Defaults of the global email options. Keys are the stored option names (the historic
	 * `possition` spelling is kept because templates read it).
	 */
	const GLOBAL_DEFAULTS = array(
		'ohmylms_email_branding_image'        => '',
		'ohmylms_email_base_color'            => '#6E42D3',
		'ohmylms_email_background_color'      => '#F4F5F7',
		'ohmylms_email_body_background_color' => '#FFFFFF',
		'ohmylms_email_body_text_color'       => '#1F2328',
		'ohmylms_email_button_possition'      => 'left',
		'ohmylms_email_sender_email_address'  => '',
		'ohmylms_email_sender_name'           => '',
		'ohmylms_email_footer_text'           => '',
	);

	/**
	 * Get emails for Emails.
	 */
	public function get_emails() {
		$emails = array(
			\OhMyLMS\Emails\CreatorsEmail\NewOrder::class,
			\OhMyLMS\Emails\CreatorsEmail\CancelledOrder::class,
			\OhMyLMS\Emails\CreatorsEmail\AssignmentSubmitted::class,
			\OhMyLMS\Emails\CreatorsEmail\QuizSubmitted::class,
			\OhMyLMS\Emails\StudentsEmail\NewOrder::class,
			\OhMyLMS\Emails\StudentsEmail\CompleteCourse::class,
			\OhMyLMS\Emails\StudentsEmail\ConfirmEnrollment::class,
			\OhMyLMS\Emails\StudentsEmail\CancelEnrollment::class,
			\OhMyLMS\Emails\StudentsEmail\AssignmentGraded::class,
			\OhMyLMS\Emails\StudentsEmail\QuizGraded::class,
			\OhMyLMS\Emails\StudentsEmail\ManualEnrollment::class,
			\OhMyLMS\Emails\StudentsEmail\EmailVerification::class,
			\OhMyLMS\Emails\StudentsEmail\AssignmentComment::class,
			\OhMyLMS\Emails\CreatorsEmail\AssignmentComment::class,
		);
		return $emails;
	}

	/**
	 * Configurable emails: email id => class name. EmailVerification has fixed content and is not listed.
	 *
	 * @return array<string,string>
	 */
	public static function catalog() {
		return array(
			'creator_new_order'               => \OhMyLMS\Emails\CreatorsEmail\NewOrder::class,
			'creator_cancelled_order'         => \OhMyLMS\Emails\CreatorsEmail\CancelledOrder::class,
			'instructor_assignment_submitted' => \OhMyLMS\Emails\CreatorsEmail\AssignmentSubmitted::class,
			'instructor_quiz_submitted'       => \OhMyLMS\Emails\CreatorsEmail\QuizSubmitted::class,
			'student_new_order'               => \OhMyLMS\Emails\StudentsEmail\NewOrder::class,
			'student_confirm_enrollment'      => \OhMyLMS\Emails\StudentsEmail\ConfirmEnrollment::class,
			'student_manual_enrollment'       => \OhMyLMS\Emails\StudentsEmail\ManualEnrollment::class,
			'student_course_completed'        => \OhMyLMS\Emails\StudentsEmail\CompleteCourse::class,
			'student_cancel_enrollment'       => \OhMyLMS\Emails\StudentsEmail\CancelEnrollment::class,
			'student_assignment_graded'       => \OhMyLMS\Emails\StudentsEmail\AssignmentGraded::class,
			'student_quiz_graded'             => \OhMyLMS\Emails\StudentsEmail\QuizGraded::class,
			'student_exam_results_released'   => \OhMyLMS\Emails\StudentsEmail\ExamResultsReleased::class,
			'student_at_risk_nudge'           => \OhMyLMS\Emails\StudentsEmail\AtRiskNudge::class,
			'instructor_assignment_comment'   => \OhMyLMS\Emails\CreatorsEmail\AssignmentComment::class,
			'student_assignment_comment'      => \OhMyLMS\Emails\StudentsEmail\AssignmentComment::class,
		);
	}

	/**
	 * Register email for Emails.
	 */
	public function register_email() {
		$emails = $this->get_emails();
		foreach ( $emails as $email ) {
			new $email();
		}
		$this->register_digest_cron();
	}

	/**
	 * Register digest cron for Emails.
	 */
	public function register_digest_cron() {
		if ( ! wp_next_scheduled( 'ohmylms_send_assignment_submission_digest' ) ) {
			wp_schedule_event( time(), 'daily', 'ohmylms_send_assignment_submission_digest' );
		}
		if ( ! wp_next_scheduled( 'ohmylms_send_quiz_submission_digest' ) ) {
			wp_schedule_event( time(), 'daily', 'ohmylms_send_quiz_submission_digest' );
		}
	}

	/**
	 * Stored settings of one email merged over its defaults, so a partial or missing option never
	 * leaves a key undefined.
	 *
	 * @param string $email_id Email id such as `student_quiz_graded`.
	 * @param array  $defaults Default settings of the email class.
	 * @return array
	 */
	public static function get_template_settings( $email_id, array $defaults ) {
		$stored = get_option( self::OPTION_PREFIX . $email_id, array() );
		return array_merge( $defaults, is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Whether an email is switched on.
	 *
	 * @param array $settings Settings from {@see self::get_template_settings()}.
	 * @return bool
	 */
	public static function is_enabled( array $settings ) {
		return ! empty( $settings['enable'] );
	}

	/**
	 * Values of the merge tags that do not depend on an order, course or student.
	 *
	 * @return array<string,string>
	 */
	public static function site_tag_values() {
		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		return array(
			'site_name'                => $site_name,
			'store_name'               => $site_name,
			'admin_email'              => (string) get_option( 'admin_email' ),
			/**
			 * Filters the refund/payout processing time named by the `{expected_processing_time}` tag.
			 *
			 * @param string $time Human readable duration.
			 */
			'expected_processing_time' => (string) apply_filters( 'ohmylms_email_expected_processing_time', __( '24 hours', 'ohmylms' ) ),
		);
	}

	/**
	 * Replace merge tags in text. Both `{tag_name}` and `[tag_name]` are accepted, the name is
	 * case-insensitive, and unknown tags are left as they are. Values are HTML-escaped when the
	 * text contains markup, so a student name can never inject HTML into a body; plain-text
	 * subjects are not escaped.
	 *
	 * @param string              $text   Text containing tags.
	 * @param array<string,mixed> $values Tag name => value. Site tags are added when absent.
	 * @param bool|null           $escape Force escaping on or off; null decides from the text.
	 * @return string
	 */
	public static function render_tags( $text, array $values, $escape = null ) {
		$text = (string) $text;
		if ( '' === $text ) {
			return $text;
		}
		if ( null === $escape ) {
			$escape = false !== strpos( $text, '<' );
		}
		$values = array_change_key_case( array_merge( self::site_tag_values(), $values ), CASE_LOWER );

		return (string) preg_replace_callback(
			'/\{([A-Za-z][A-Za-z0-9_]*)\}|\[([A-Za-z][A-Za-z0-9_]*)\]/',
			static function ( $found ) use ( $values, $escape ) {
				$name = strtolower( '' !== $found[1] ? $found[1] : $found[2] );
				if ( ! array_key_exists( $name, $values ) ) {
					return $found[0];
				}
				$value = (string) $values[ $name ];
				return $escape ? esc_html( $value ) : $value;
			},
			$text
		);
	}

	/**
	 * Find merge tags in text that are not in the allowed list. `{name}` is always checked;
	 * `[name]` only when it contains an underscore, so literal text such as `[Important]` is left alone.
	 *
	 * @param string   $text    Text to scan.
	 * @param string[] $allowed Allowed tag names (lower case).
	 * @return string[] Unknown tags as written, without duplicates.
	 */
	public static function find_unknown_tags( $text, array $allowed ) {
		$found = array();
		if ( preg_match_all( '/\{([A-Za-z][A-Za-z0-9_]*)\}|\[([A-Za-z][A-Za-z0-9]*_[A-Za-z0-9_]*)\]/', (string) $text, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$name = strtolower( '' !== $match[1] ? $match[1] : $match[2] );
				if ( ! in_array( $name, $allowed, true ) ) {
					$found[ $match[0] ] = $match[0];
				}
			}
		}
		return array_values( $found );
	}

	/**
	 * Replace merge tags for Emails.
	 *
	 * @param mixed $email  Email text (HTML body or plain subject).
	 * @param mixed $order  Order.
	 * @param mixed $course Course.
	 * @return string|false Text with tags replaced, or false when the input is not a string.
	 */
	public static function replace_merge_tags( $email, $order, $course = null ) {
		if ( ! is_string( $email ) ) {
			return false;
		}

		return self::render_tags(
			$email,
			array(
				'student_name' => $order ? (string) $order->get_student_name() : '',
				'course_name'  => $course ? (string) $course->get_name() : '',
			)
		);
	}

	/**
	 * Global email settings, always usable: colors fall back to the defaults, the button position
	 * is an allowed value, and the sender name and address cannot carry header-breaking text.
	 *
	 * @return array<string,string> Option name => value.
	 */
	public static function get_email_settings() {
		$settings = array();
		foreach ( self::GLOBAL_DEFAULTS as $key => $default ) {
			$settings[ $key ] = get_option( $key, $default );
			if ( ! is_string( $settings[ $key ] ) ) {
				$settings[ $key ] = $default;
			}
		}

		foreach ( array( 'ohmylms_email_base_color', 'ohmylms_email_background_color', 'ohmylms_email_body_background_color', 'ohmylms_email_body_text_color' ) as $key ) {
			$color            = sanitize_hex_color( $settings[ $key ] );
			$settings[ $key ] = $color ? $color : self::GLOBAL_DEFAULTS[ $key ];
		}
		if ( ! in_array( $settings['ohmylms_email_button_possition'], array( 'left', 'center', 'right' ), true ) ) {
			$settings['ohmylms_email_button_possition'] = self::GLOBAL_DEFAULTS['ohmylms_email_button_possition'];
		}
		$settings['ohmylms_email_sender_name']          = self::clean_header_text( $settings['ohmylms_email_sender_name'] );
		$settings['ohmylms_email_sender_email_address'] = (string) sanitize_email( $settings['ohmylms_email_sender_email_address'] );

		return $settings;
	}

	/**
	 * Remove everything from a value that could change a mail header: control characters
	 * (so no extra header line) and the characters that delimit an address or quoted name.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function clean_header_text( $value ) {
		$value = (string) preg_replace( '/[\x00-\x1F\x7F<>"]/u', ' ', (string) $value );
		return trim( (string) preg_replace( '/\s+/u', ' ', $value ) );
	}
}
