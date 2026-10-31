<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Email administration: stored values, validation, preview and test sending.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Emails;

use WP_Error;
use WP_User;

/**
 * Everything the REST controller needs that is not HTTP: it reads and writes the same options the
 * sending classes read (see {@see Emails::get_template_settings()}), sanitizes with an explicit
 * allow-list, and renders the real templates with sample data.
 *
 * Field rules live here so the controller stays thin and the rules can be tested directly.
 */
class EmailManager {

	/**
	 * Limits of the editable fields (characters).
	 */
	const MAX_SUBJECT = 255;
	const MAX_HEADING = 200;
	const MAX_BUTTON  = 60;
	const MAX_LINK    = 2048;
	const MAX_HTML    = 20000;
	const MAX_FOOTER  = 5000;

	/**
	 * Most addresses one email may be sent to.
	 */
	const MAX_RECIPIENTS = 10;

	/**
	 * Seconds between two test emails from the same administrator.
	 */
	const TEST_INTERVAL = 30;

	/**
	 * Merge tags offered by every email.
	 *
	 * @return string[]
	 */
	private static function common_tags() {
		return array( 'site_name', 'store_name', 'admin_email' );
	}

	/**
	 * Merge tags each email provides in addition to the common ones. The sending classes and
	 * {@see Emails::render_tags()} replace exactly these.
	 *
	 * @return array<string,string[]>
	 */
	private static function email_tags() {
		return array(
			'creator_new_order'               => array( 'student_name' ),
			'creator_cancelled_order'         => array( 'student_name', 'course_name', 'expected_processing_time' ),
			'instructor_assignment_submitted' => array( 'student_name', 'course_name', 'assignment_name' ),
			'instructor_quiz_submitted'       => array( 'student_name', 'course_name', 'quiz_name' ),
			'student_new_order'               => array( 'student_name' ),
			'student_confirm_enrollment'      => array( 'student_name', 'course_name' ),
			'student_manual_enrollment'       => array( 'student_name', 'course_name' ),
			'student_course_completed'        => array( 'student_name', 'course_name' ),
			'student_cancel_enrollment'       => array( 'student_name', 'course_name', 'expected_processing_time' ),
			'student_assignment_graded'       => array( 'student_name', 'course_name', 'assignment_name', 'status' ),
			'student_quiz_graded'             => array( 'student_name', 'course_name', 'quiz_name' ),
			'student_at_risk_nudge'           => array( 'student_name', 'sender_name', 'reasons' ),
		);
	}

	/**
	 * Labels and descriptions of every merge tag.
	 *
	 * @return array<string,array{label:string,description:string}>
	 */
	public static function tag_catalog() {
		return array(
			'student_name'             => array(
				'label'       => __( 'Student name', 'ohmylms' ),
				'description' => __( 'Display name of the student the email is about.', 'ohmylms' ),
			),
			'course_name'              => array(
				'label'       => __( 'Course name', 'ohmylms' ),
				'description' => __( 'Title of the course.', 'ohmylms' ),
			),
			'quiz_name'                => array(
				'label'       => __( 'Quiz name', 'ohmylms' ),
				'description' => __( 'Title of the quiz.', 'ohmylms' ),
			),
			'assignment_name'          => array(
				'label'       => __( 'Assignment name', 'ohmylms' ),
				'description' => __( 'Title of the assignment.', 'ohmylms' ),
			),
			'status'                   => array(
				'label'       => __( 'Result', 'ohmylms' ),
				'description' => __( 'Passed or Failed.', 'ohmylms' ),
			),
			'sender_name'              => array(
				'label'       => __( 'Sender name', 'ohmylms' ),
				'description' => __( 'Display name of the teacher or administrator who sent the nudge.', 'ohmylms' ),
			),
			'reasons'                  => array(
				'label'       => __( 'Reasons', 'ohmylms' ),
				'description' => __( 'Why the learner was flagged, for example "Inactive 9 days · 2 overdue items".', 'ohmylms' ),
			),
			'expected_processing_time' => array(
				'label'       => __( 'Processing time', 'ohmylms' ),
				'description' => __( 'How long a refund or payout takes (filterable, 24 hours by default).', 'ohmylms' ),
			),
			'site_name'                => array(
				'label'       => __( 'Site name', 'ohmylms' ),
				'description' => __( 'Name of this website.', 'ohmylms' ),
			),
			'store_name'               => array(
				'label'       => __( 'Store name', 'ohmylms' ),
				'description' => __( 'Same as the site name.', 'ohmylms' ),
			),
			'admin_email'              => array(
				'label'       => __( 'Administrator email', 'ohmylms' ),
				'description' => __( 'Email address of the site administrator.', 'ohmylms' ),
			),
		);
	}

	/**
	 * Sample values shown in previews and test emails.
	 *
	 * @return array<string,string>
	 */
	private static function sample_values() {
		return array(
			'student_name'    => __( 'Alex Learner', 'ohmylms' ),
			'course_name'     => __( 'Introduction to Algebra', 'ohmylms' ),
			'quiz_name'       => __( 'Chapter 1 Quiz', 'ohmylms' ),
			'assignment_name' => __( 'Essay on Linear Equations', 'ohmylms' ),
			'status'          => __( 'Passed', 'ohmylms' ),
			'sender_name'     => __( 'Ms. Rivera', 'ohmylms' ),
			'reasons'         => __( 'Inactive 9 days · 2 overdue items', 'ohmylms' ),
		);
	}

	/**
	 * Ids of all configurable emails, in display order.
	 *
	 * @return string[]
	 */
	public static function ids() {
		return array_keys( Emails::catalog() );
	}

	/**
	 * Whether an email id exists.
	 *
	 * @param string $email_id Email id.
	 * @return bool
	 */
	public static function exists( $email_id ) {
		return is_string( $email_id ) && array_key_exists( $email_id, Emails::catalog() );
	}

	/**
	 * Merge tags (names) valid for an email.
	 *
	 * @param string $email_id Email id.
	 * @return string[]
	 */
	public static function allowed_tags( $email_id ) {
		$own = self::email_tags();
		return array_merge( isset( $own[ $email_id ] ) ? $own[ $email_id ] : array(), self::common_tags() );
	}

	/**
	 * The sending class of an email.
	 *
	 * @param string $email_id Email id.
	 * @return object|null
	 */
	private static function instance( $email_id ) {
		$catalog = Emails::catalog();
		if ( ! isset( $catalog[ $email_id ] ) || ! class_exists( $catalog[ $email_id ] ) ) {
			return null;
		}
		$class = $catalog[ $email_id ];
		// The constructors only add action hooks; instances are created without side effects on stored data.
		return ( new \ReflectionClass( $class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * Current values of an email as the API presents them.
	 *
	 * @param string $email_id Email id.
	 * @return array|null Null for an unknown id.
	 */
	public static function get( $email_id ) {
		$email = self::instance( $email_id );
		if ( null === $email ) {
			return null;
		}

		$defaults = $email->default_settings();
		$basic    = $email->basic_settings();
		$stored   = Emails::get_template_settings( $email_id, $defaults );
		$tags     = array();
		$catalog  = self::tag_catalog();
		foreach ( self::allowed_tags( $email_id ) as $tag ) {
			$tags[] = array(
				'tag'         => $tag,
				'label'       => $catalog[ $tag ]['label'],
				'description' => $catalog[ $tag ]['description'],
			);
		}

		$text = static function ( $key ) use ( $stored ) {
			return isset( $stored[ $key ] ) && is_scalar( $stored[ $key ] ) ? (string) $stored[ $key ] : '';
		};

		return array(
			'id'                     => $email_id,
			'title'                  => (string) $basic['title'],
			'description'            => (string) ( isset( $basic['description'] ) ? $basic['description'] : ( isset( $basic['tooltip'] ) ? $basic['tooltip'] : '' ) ),
			'recipient_type'         => 'student' === $basic['recipient_type'] ? 'student' : 'creator',
			'enabled'                => Emails::is_enabled( $stored ),
			'subject'                => $text( 'subject' ),
			'heading'                => $text( 'heading' ),
			'additional_content'     => $text( 'additional_content' ),
			'button_text'            => $text( 'button_text' ),
			'button_link'            => $text( 'button_link' ),
			'footer_text'            => $text( 'footer_text' ),
			'course_suggestion_text' => array_key_exists( 'course_suggestion_text', $defaults ) ? $text( 'course_suggestion_text' ) : null,
			'recipient_email'        => self::normalize_recipients( isset( $stored['recipient_email'] ) ? $stored['recipient_email'] : array() ),
			'tags'                   => $tags,
		);
	}

	/**
	 * Every email, in display order.
	 *
	 * @return array[]
	 */
	public static function all() {
		$items = array();
		foreach ( self::ids() as $email_id ) {
			$item = self::get( $email_id );
			if ( null !== $item ) {
				$items[] = $item;
			}
		}
		return $items;
	}

	/**
	 * Turn a stored recipient value (list, comma separated text or empty) into a list of valid addresses.
	 *
	 * @param mixed $value Stored value.
	 * @return string[]
	 */
	private static function normalize_recipients( $value ) {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}
		$emails = array();
		foreach ( (array) $value as $address ) {
			$address = is_string( $address ) ? sanitize_email( trim( $address ) ) : '';
			if ( '' !== $address && is_email( $address ) && ! in_array( $address, $emails, true ) ) {
				$emails[] = $address;
			}
		}
		return $emails;
	}

	/**
	 * The HTML an email body, footer or the global footer may contain: formatting, links, images,
	 * lists and tables with inline styles. No scripts, forms, iframes, styles sheets or event attributes.
	 *
	 * @return array<string,array<string,bool>>
	 */
	public static function allowed_html() {
		$common = array(
			'style' => true,
			'class' => true,
			'align' => true,
			'dir'   => true,
		);
		$tags   = array(
			'a'          => array(
				'href'   => true,
				'title'  => true,
				'target' => true,
				'rel'    => true,
			),
			'img'        => array(
				'src'    => true,
				'alt'    => true,
				'title'  => true,
				'width'  => true,
				'height' => true,
			),
			'ol'         => array(
				'type'  => true,
				'start' => true,
			),
			'table'      => array(
				'width'       => true,
				'border'      => true,
				'cellpadding' => true,
				'cellspacing' => true,
				'role'        => true,
				'bgcolor'     => true,
			),
			'td'         => array(
				'colspan' => true,
				'rowspan' => true,
				'valign'  => true,
				'width'   => true,
				'height'  => true,
				'bgcolor' => true,
			),
			'th'         => array(
				'colspan' => true,
				'rowspan' => true,
				'valign'  => true,
				'width'   => true,
				'scope'   => true,
				'bgcolor' => true,
			),
			'tr'         => array(),
			'thead'      => array(),
			'tbody'      => array(),
			'tfoot'      => array(),
			'caption'    => array(),
			'p'          => array(),
			'div'        => array(),
			'span'       => array(),
			'br'         => array(),
			'hr'         => array(),
			'strong'     => array(),
			'b'          => array(),
			'em'         => array(),
			'i'          => array(),
			'u'          => array(),
			's'          => array(),
			'small'      => array(),
			'sub'        => array(),
			'sup'        => array(),
			'ul'         => array(),
			'li'         => array(),
			'blockquote' => array(),
			'pre'        => array(),
			'code'       => array(),
			'h1'         => array(),
			'h2'         => array(),
			'h3'         => array(),
			'h4'         => array(),
			'h5'         => array(),
			'h6'         => array(),
		);
		foreach ( $tags as $tag => $attributes ) {
			$tags[ $tag ] = array_merge( $common, $attributes );
		}

		/**
		 * Filters the HTML allowed in email content. Keep it to what mail clients render.
		 *
		 * @param array $tags Tag => attributes for wp_kses().
		 */
		return apply_filters( 'ohmylms_email_allowed_html', $tags );
	}

	/**
	 * Protocols allowed in links of email content.
	 *
	 * @return string[]
	 */
	private static function allowed_protocols() {
		return array( 'http', 'https', 'mailto', 'tel' );
	}

	/**
	 * Sanitize HTML for email content with {@see self::allowed_html()}.
	 *
	 * @param string $html Untrusted HTML.
	 * @return string
	 */
	public static function sanitize_html( $html ) {
		// wp_kses() removes a disallowed tag but keeps its text; drop whole script-like elements first.
		$html = (string) preg_replace( '#<(script|style|iframe|object|embed|noscript|template|textarea)\b[^>]*>.*?</\1\s*>#is', '', (string) $html );
		return wp_kses( $html, self::allowed_html(), self::allowed_protocols() );
	}

	/**
	 * Whether a value is a complete http or https URL that needs no cleaning.
	 *
	 * @param string $url Candidate URL.
	 * @return bool
	 */
	private static function is_http_url( $url ) {
		return 1 === preg_match( '#^https?://\S+$#i', $url ) && esc_url_raw( $url, array( 'http', 'https' ) ) === $url;
	}

	/**
	 * Sanitize a single-line text value (no markup, no line breaks).
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function sanitize_line( $value ) {
		return trim( sanitize_text_field( (string) $value ) );
	}

	/**
	 * Build the validation error used for all semantic problems.
	 *
	 * @param string $code    Error code.
	 * @param string $message Message.
	 * @param array  $data    Extra data (`field`, `tags`).
	 * @return WP_Error
	 */
	private static function error( $code, $message, array $data = array() ) {
		return new WP_Error( $code, $message, array_merge( array( 'status' => 422 ), $data ) );
	}

	/**
	 * Validate and sanitize the fields of a partial update. Only keys present in `$input` are
	 * returned, in stored form (`enable`, a list of recipients).
	 *
	 * @param string $email_id Email id.
	 * @param array  $input    Request values (already type-checked against the schema).
	 * @return array|WP_Error
	 */
	public static function sanitize_patch( $email_id, array $input ) {
		$item = self::get( $email_id );
		if ( null === $item ) {
			return new WP_Error( 'ohmylms_email_not_found', __( 'Email not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		$patch = array();

		if ( array_key_exists( 'enabled', $input ) ) {
			$patch['enable'] = (bool) $input['enabled'];
		}

		$text_fields = array(
			'subject'     => array( self::MAX_SUBJECT, true ),
			'heading'     => array( self::MAX_HEADING, true ),
			'button_text' => array( self::MAX_BUTTON, false ),
		);
		foreach ( $text_fields as $field => $rule ) {
			if ( ! array_key_exists( $field, $input ) ) {
				continue;
			}
			$value = self::sanitize_line( $input[ $field ] );
			if ( $rule[1] && '' === $value ) {
				return self::error(
					'ohmylms_email_invalid_field',
					/* translators: %s: field name. */
					sprintf( __( 'The %s cannot be empty.', 'ohmylms' ), $field ),
					array( 'field' => $field )
				);
			}
			if ( mb_strlen( $value ) > $rule[0] ) {
				return self::error(
					'ohmylms_email_invalid_field',
					/* translators: %s: field name. */
					sprintf( __( 'The %s is too long.', 'ohmylms' ), $field ),
					array( 'field' => $field )
				);
			}
			$patch[ $field ] = $value;
		}

		$html_fields = array(
			'additional_content'     => array( self::MAX_HTML, true ),
			'footer_text'            => array( self::MAX_FOOTER, false ),
			'course_suggestion_text' => array( self::MAX_FOOTER, false ),
		);
		foreach ( $html_fields as $field => $rule ) {
			if ( ! array_key_exists( $field, $input ) ) {
				continue;
			}
			if ( 'course_suggestion_text' === $field && null === $item['course_suggestion_text'] ) {
				return self::error(
					'ohmylms_email_unsupported_field',
					__( 'This email has no course suggestion text.', 'ohmylms' ),
					array( 'field' => $field )
				);
			}
			$value = trim( self::sanitize_html( (string) $input[ $field ] ) );
			if ( $rule[1] && '' === trim( wp_strip_all_tags( $value ) ) && false === stripos( $value, '<img' ) ) {
				return self::error(
					'ohmylms_email_invalid_field',
					__( 'The message cannot be empty.', 'ohmylms' ),
					array( 'field' => $field )
				);
			}
			if ( mb_strlen( $value ) > $rule[0] ) {
				return self::error(
					'ohmylms_email_invalid_field',
					/* translators: %s: field name. */
					sprintf( __( 'The %s is too long.', 'ohmylms' ), $field ),
					array( 'field' => $field )
				);
			}
			$patch[ $field ] = $value;
		}

		if ( array_key_exists( 'button_link', $input ) ) {
			$link = trim( (string) $input['button_link'] );
			if ( '' !== $link && ( mb_strlen( $link ) > self::MAX_LINK || ! self::is_http_url( $link ) ) ) {
				return self::error(
					'ohmylms_email_invalid_field',
					__( 'The button link must be a full http or https address.', 'ohmylms' ),
					array( 'field' => 'button_link' )
				);
			}
			$patch['button_link'] = $link;
		}

		if ( array_key_exists( 'recipient_email', $input ) ) {
			$addresses = array();
			foreach ( (array) $input['recipient_email'] as $address ) {
				$clean = is_string( $address ) ? sanitize_email( trim( $address ) ) : '';
				if ( '' === $clean || trim( $address ) !== $clean || ! is_email( $clean ) ) {
					return self::error(
						'ohmylms_email_invalid_field',
						__( 'Every recipient must be a valid email address.', 'ohmylms' ),
						array( 'field' => 'recipient_email' )
					);
				}
				$addresses[ strtolower( $clean ) ] = $clean;
			}
			if ( count( $addresses ) > self::MAX_RECIPIENTS ) {
				return self::error(
					'ohmylms_email_invalid_field',
					__( 'Use no more than 10 recipients.', 'ohmylms' ),
					array( 'field' => 'recipient_email' )
				);
			}
			if ( $addresses && 'student' === $item['recipient_type'] ) {
				return self::error(
					'ohmylms_email_unsupported_field',
					__( 'Student emails are always sent to the student; they have no custom recipients.', 'ohmylms' ),
					array( 'field' => 'recipient_email' )
				);
			}
			$patch['recipient_email'] = array_values( $addresses );
		}

		$unknown = array();
		foreach ( array( 'subject', 'heading', 'additional_content', 'button_text', 'footer_text', 'course_suggestion_text' ) as $field ) {
			if ( isset( $patch[ $field ] ) ) {
				$unknown = array_merge( $unknown, Emails::find_unknown_tags( $patch[ $field ], self::allowed_tags( $email_id ) ) );
			}
		}
		if ( $unknown ) {
			$unknown = array_values( array_unique( $unknown ) );
			return self::error(
				'ohmylms_email_unknown_tags',
				/* translators: %s: comma separated list of tags. */
				sprintf( __( 'This email does not support these merge tags: %s', 'ohmylms' ), implode( ', ', $unknown ) ),
				array( 'tags' => $unknown )
			);
		}

		return $patch;
	}

	/**
	 * Save a validated patch over the stored settings.
	 *
	 * @param string $email_id Email id.
	 * @param array  $patch    Result of {@see self::sanitize_patch()}.
	 * @return array|null The email as presented by {@see self::get()}.
	 */
	public static function save( $email_id, array $patch ) {
		$email = self::instance( $email_id );
		if ( null === $email ) {
			return null;
		}
		$stored = Emails::get_template_settings( $email_id, $email->default_settings() );
		update_option( Emails::OPTION_PREFIX . $email_id, array_merge( $stored, $patch ), false );
		return self::get( $email_id );
	}

	/**
	 * Values of the global email settings for the API.
	 *
	 * @return array<string,string>
	 */
	public static function get_global() {
		$stored = Emails::get_email_settings();
		return array(
			'branding_image'        => $stored['ohmylms_email_branding_image'],
			'base_color'            => $stored['ohmylms_email_base_color'],
			'background_color'      => $stored['ohmylms_email_background_color'],
			'body_background_color' => $stored['ohmylms_email_body_background_color'],
			'body_text_color'       => $stored['ohmylms_email_body_text_color'],
			'button_position'       => $stored['ohmylms_email_button_possition'],
			'sender_name'           => $stored['ohmylms_email_sender_name'],
			'sender_email'          => $stored['ohmylms_email_sender_email_address'],
			'footer_text'           => wp_kses( $stored['ohmylms_email_footer_text'], self::allowed_html(), self::allowed_protocols() ),
		);
	}

	/**
	 * Validate a partial update of the global settings and return it as option name => value.
	 *
	 * @param array $input Request values.
	 * @return array<string,string>|WP_Error
	 */
	public static function sanitize_global( array $input ) {
		$options = array();

		foreach ( array(
			'base_color'            => 'ohmylms_email_base_color',
			'background_color'      => 'ohmylms_email_background_color',
			'body_background_color' => 'ohmylms_email_body_background_color',
			'body_text_color'       => 'ohmylms_email_body_text_color',
		) as $field => $option ) {
			if ( ! array_key_exists( $field, $input ) ) {
				continue;
			}
			if ( ! is_string( $input[ $field ] ) || ! preg_match( '/^#[0-9A-Fa-f]{6}$/', $input[ $field ] ) ) {
				return self::error( 'ohmylms_email_invalid_field', __( 'Colors must look like #6E42D3.', 'ohmylms' ), array( 'field' => $field ) );
			}
			$options[ $option ] = strtoupper( $input[ $field ] );
		}

		if ( array_key_exists( 'button_position', $input ) ) {
			if ( ! in_array( $input['button_position'], array( 'left', 'center', 'right' ), true ) ) {
				return self::error( 'ohmylms_email_invalid_field', __( 'The button position must be left, center or right.', 'ohmylms' ), array( 'field' => 'button_position' ) );
			}
			$options['ohmylms_email_button_possition'] = $input['button_position'];
		}

		if ( array_key_exists( 'branding_image', $input ) ) {
			$url = trim( (string) $input['branding_image'] );
			if ( '' !== $url && ( mb_strlen( $url ) > self::MAX_LINK || ! self::is_http_url( $url ) ) ) {
				return self::error( 'ohmylms_email_invalid_field', __( 'The logo must be a full http or https image address.', 'ohmylms' ), array( 'field' => 'branding_image' ) );
			}
			$options['ohmylms_email_branding_image'] = $url;
		}

		if ( array_key_exists( 'sender_name', $input ) ) {
			$name = trim( (string) $input['sender_name'] );
			if ( mb_strlen( $name ) > 100 || Emails::clean_header_text( $name ) !== $name ) {
				return self::error(
					'ohmylms_email_invalid_field',
					__( 'The sender name can have up to 100 characters and no line breaks, quotes or angle brackets.', 'ohmylms' ),
					array( 'field' => 'sender_name' )
				);
			}
			$options['ohmylms_email_sender_name'] = $name;
		}

		if ( array_key_exists( 'sender_email', $input ) ) {
			$address = trim( (string) $input['sender_email'] );
			if ( '' !== $address && ( sanitize_email( $address ) !== $address || ! is_email( $address ) ) ) {
				return self::error( 'ohmylms_email_invalid_field', __( 'The sender address must be a valid email address.', 'ohmylms' ), array( 'field' => 'sender_email' ) );
			}
			$options['ohmylms_email_sender_email_address'] = $address;
		}

		if ( array_key_exists( 'footer_text', $input ) ) {
			$footer = trim( self::sanitize_html( (string) $input['footer_text'] ) );
			if ( mb_strlen( $footer ) > 1000 ) {
				return self::error( 'ohmylms_email_invalid_field', __( 'The footer text is too long.', 'ohmylms' ), array( 'field' => 'footer_text' ) );
			}
			$options['ohmylms_email_footer_text'] = $footer;
		}

		return $options;
	}

	/**
	 * Save validated global options.
	 *
	 * @param array<string,string> $options Result of {@see self::sanitize_global()}.
	 * @return array<string,string> The global settings as presented by {@see self::get_global()}.
	 */
	public static function save_global( array $options ) {
		foreach ( $options as $name => $value ) {
			if ( array_key_exists( $name, Emails::GLOBAL_DEFAULTS ) ) {
				update_option( $name, $value );
			}
		}
		return self::get_global();
	}

	/**
	 * Email values for rendering: the stored email with an unsaved draft applied on top.
	 *
	 * @param string $email_id Email id.
	 * @param array  $draft    Draft fields from the request.
	 * @return array|WP_Error The settings array the templates expect, or the validation error.
	 */
	private static function settings_with_draft( $email_id, array $draft ) {
		$email = self::instance( $email_id );
		if ( null === $email ) {
			return new WP_Error( 'ohmylms_email_not_found', __( 'Email not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$settings = Emails::get_template_settings( $email_id, $email->default_settings() );
		$patch    = self::sanitize_patch( $email_id, $draft );
		if ( is_wp_error( $patch ) ) {
			return $patch;
		}
		return array_merge( $settings, $patch );
	}

	/**
	 * Template name and variables for rendering an email with sample data.
	 *
	 * @param string $email_id       Email id.
	 * @param array  $settings       Email settings.
	 * @param array  $email_settings Global settings.
	 * @return array{0:string,1:array}
	 */
	private static function template_for( $email_id, array $settings, array $email_settings ) {
		$samples = self::sample_values();
		$course  = new SampleRecord(
			array(
				'get_id'          => 0,
				'get_name'        => $samples['course_name'],
				'get_certificate' => false,
			)
		);
		$student = new SampleRecord(
			array(
				'get_id'       => 0,
				'display_name' => $samples['student_name'],
			)
		);
		$order   = new SampleRecord(
			array(
				'get_id'                   => 1001,
				'get_student_id'           => 0,
				'get_student_name'         => $samples['student_name'],
				'get_date_created'         => new \DateTimeImmutable( 'now', wp_timezone() ),
				'get_currency'             => 'USD',
				'get_cart_subtotal'        => 49,
				'get_cart_discount'        => 5,
				'get_tax_amount'           => 4.4,
				'get_tax_rate'             => '10',
				'get_total'                => 48.4,
				'get_payment_method_title' => __( 'Sample payment', 'ohmylms' ),
				'get_items'                => array(
					new SampleRecord(
						array(
							'get_name'     => $samples['course_name'],
							'get_quantity' => 1,
							'get_total'    => 49,
						)
					),
				),
			)
		);

		$common = array(
			'settings'       => $settings,
			'email_settings' => $email_settings,
		);
		$enroll = array( 'start_date' => wp_date( 'Y-m-d H:i:s' ) );

		switch ( $email_id ) {
			case 'creator_new_order':
			case 'student_new_order':
				return array(
					'emails/new-order',
					array_merge(
						$common,
						array(
							'order' => $order,
							'data'  => array(),
						)
					),
				);
			case 'creator_cancelled_order':
			case 'student_cancel_enrollment':
				return array(
					'emails/cancel-enrollment',
					array_merge(
						$common,
						array(
							'order'        => $order,
							'student_name' => $samples['student_name'],
							'enroll_data'  => $enroll,
							'course'       => $course,
						)
					),
				);
			case 'student_confirm_enrollment':
				return array(
					'emails/confirm-enrollment',
					array_merge(
						$common,
						array(
							'order'        => $order,
							'student_name' => $samples['student_name'],
							'enroll_data'  => $enroll,
							'course'       => $course,
						)
					),
				);
			case 'student_course_completed':
				return array(
					'emails/complete-course',
					array_merge(
						$common,
						array(
							'course'         => $course,
							'enroll_data'    => $enroll,
							'student_name'   => $samples['student_name'],
							'student'        => $student,
							'certificate_id' => '',
						)
					),
				);
			case 'student_manual_enrollment':
				return array(
					'emails/manual-enrollment',
					array_merge(
						$common,
						array(
							'student_name'   => $samples['student_name'],
							'course'         => $course,
							'username'       => 'alex.learner',
							'plain_password' => '********',
							'is_new_user'    => true,
							'login_url'      => home_url( '/' ),
							'start_date'     => wp_date( 'Y-m-d H:i:s' ),
						)
					),
				);
			case 'instructor_assignment_submitted':
				return array(
					'emails/instructor-assignment-submitted',
					array_merge(
						$common,
						array(
							'student'          => $student,
							'course'           => $course,
							'assignment_title' => $samples['assignment_name'],
							'is_digest'        => false,
							'digest_items'     => array(),
						)
					),
				);
			case 'instructor_quiz_submitted':
				return array(
					'emails/instructor-quiz-submitted',
					array_merge(
						$common,
						array(
							'student'      => $student,
							'course'       => $course,
							'quiz_title'   => $samples['quiz_name'],
							'is_digest'    => false,
							'digest_items' => array(),
						)
					),
				);
			case 'student_at_risk_nudge':
				return array(
					'emails/at-risk-nudge',
					array_merge( $common, array( 'button_url' => StudentsEmail\AtRiskNudge::button_url( $settings ) ) ),
				);
			case 'student_assignment_graded':
				return array(
					'emails/student-assignment-graded',
					array_merge(
						$common,
						array(
							'student'          => $student,
							'course'           => $course,
							'assignment_title' => $samples['assignment_name'],
							'status'           => 'passed',
							'score'            => 18,
							'total_marks'      => 20,
						)
					),
				);
			default:
				return array(
					'emails/student-quiz-graded',
					array_merge(
						$common,
						array(
							'student'      => $student,
							'course'       => $course,
							'quiz_title'   => $samples['quiz_name'],
							'score'        => 8,
							'passing_mark' => 5,
							'total_marks'  => 10,
							'status'       => 'passed',
						)
					),
				);
		}
	}

	/**
	 * Add the CSS properties email markup needs to the safe list while the preview is filtered.
	 *
	 * @param string[] $properties Safe CSS properties.
	 * @return string[]
	 */
	public static function allow_email_css( $properties ) {
		return array_merge(
			$properties,
			array(
				'border-radius',
				'border-spacing',
				'border-collapse',
				'display',
				'text-transform',
				'max-width',
				'min-width',
				'overflow',
				'vertical-align',
				'text-decoration',
				'margin-left',
				'margin-right',
			)
		);
	}

	/**
	 * Render an email with the real template and sample data.
	 *
	 * @param string $email_id Email id.
	 * @param array  $draft    Unsaved fields (same names as the update route) applied over the stored email.
	 * @return array{subject:string,html:string,unknown_tags:string[]}|WP_Error
	 */
	public static function preview( $email_id, array $draft = array() ) {
		$settings = self::settings_with_draft( $email_id, $draft );
		if ( is_wp_error( $settings ) ) {
			// Unknown tags are reported, not fatal, in a preview.
			if ( 'ohmylms_email_unknown_tags' !== $settings->get_error_code() ) {
				return $settings;
			}
			$settings = self::settings_with_draft( $email_id, array_diff_key( $draft, array_flip( array( 'subject', 'heading', 'additional_content', 'button_text', 'footer_text', 'course_suggestion_text' ) ) ) );
			if ( is_wp_error( $settings ) ) {
				return $settings;
			}
			$settings = array_merge( $settings, self::loose_text_fields( $draft ) );
		}

		$email_settings = Emails::get_email_settings();
		if ( empty( $settings['button_link'] ) ) {
			$settings['button_link'] = home_url( '/' );
		}
		list( $template, $args ) = self::template_for( $email_id, $settings, $email_settings );

		$values = array_merge( self::sample_values(), array( 'expected_processing_time' => Emails::site_tag_values()['expected_processing_time'] ) );

		ob_start();
		ohmylms_get_template( $template, $args );
		$document = (string) ob_get_clean();

		if ( preg_match( '#<body[^>]*>(.*)</body>#is', $document, $match ) ) {
			$document = $match[1];
		}
		$document = Emails::render_tags( $document, $values, true );

		add_filter( 'safe_style_css', array( __CLASS__, 'allow_email_css' ) );
		$html = self::sanitize_html( $document );
		remove_filter( 'safe_style_css', array( __CLASS__, 'allow_email_css' ) );

		$unknown = array();
		foreach ( array( 'subject', 'heading', 'additional_content', 'button_text', 'footer_text', 'course_suggestion_text' ) as $field ) {
			if ( isset( $settings[ $field ] ) && is_string( $settings[ $field ] ) ) {
				$unknown = array_merge( $unknown, Emails::find_unknown_tags( $settings[ $field ], self::allowed_tags( $email_id ) ) );
			}
		}

		return array(
			'subject'      => self::subject_line( $settings, $values ),
			'html'         => $html,
			'unknown_tags' => array_values( array_unique( $unknown ) ),
		);
	}

	/**
	 * Sanitize the text fields of a draft without validating their tags (used when a preview reports unknown tags).
	 *
	 * @param array $draft Draft fields.
	 * @return array
	 */
	private static function loose_text_fields( array $draft ) {
		$fields = array();
		foreach ( array( 'subject', 'heading', 'button_text' ) as $field ) {
			if ( isset( $draft[ $field ] ) ) {
				$fields[ $field ] = self::sanitize_line( $draft[ $field ] );
			}
		}
		foreach ( array( 'additional_content', 'footer_text', 'course_suggestion_text' ) as $field ) {
			if ( isset( $draft[ $field ] ) ) {
				$fields[ $field ] = self::sanitize_html( (string) $draft[ $field ] );
			}
		}
		return $fields;
	}

	/**
	 * The subject with tags replaced, as one safe line.
	 *
	 * @param array $settings Email settings.
	 * @param array $values   Tag values.
	 * @return string
	 */
	private static function subject_line( array $settings, array $values ) {
		$subject = Emails::render_tags( isset( $settings['subject'] ) ? (string) $settings['subject'] : '', $values, false );
		return self::sanitize_line( $subject );
	}

	/**
	 * Headers for an HTML email with the configured sender. The sender values are cleaned again here
	 * so no stored value can add a header line.
	 *
	 * @param array $email_settings Global settings from {@see Emails::get_email_settings()}.
	 * @return string[]
	 */
	public static function mail_headers( array $email_settings ) {
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$name    = Emails::clean_header_text( $email_settings['ohmylms_email_sender_name'] );
		$address = sanitize_email( $email_settings['ohmylms_email_sender_email_address'] );
		if ( '' !== $name && is_email( $address ) ) {
			$headers[] = 'From: ' . $name . ' <' . $address . '>';
		}
		return $headers;
	}

	/**
	 * Send the email, with sample data, to the given administrator only.
	 *
	 * @param string  $email_id Email id.
	 * @param WP_User $user     Current administrator; the only recipient.
	 * @param array   $draft    Unsaved fields to test instead of the stored ones.
	 * @return array{sent:bool,to:string,subject:string}|WP_Error
	 */
	public static function send_test( $email_id, WP_User $user, array $draft = array() ) {
		if ( ! is_email( $user->user_email ) ) {
			return self::error( 'ohmylms_email_no_recipient', __( 'Your account has no valid email address to send the test to.', 'ohmylms' ) );
		}

		$key  = 'ohmylms_email_test_' . (int) $user->ID;
		$wait = (int) get_transient( $key );
		if ( $wait > time() ) {
			return new WP_Error(
				'ohmylms_email_test_rate_limited',
				__( 'A test email was sent a moment ago. Wait before sending another.', 'ohmylms' ),
				array(
					'status'      => 429,
					'retry_after' => $wait - time(),
				)
			);
		}

		$rendered = self::preview( $email_id, $draft );
		if ( is_wp_error( $rendered ) ) {
			return $rendered;
		}
		if ( $rendered['unknown_tags'] ) {
			return self::error(
				'ohmylms_email_unknown_tags',
				/* translators: %s: comma separated list of tags. */
				sprintf( __( 'This email does not support these merge tags: %s', 'ohmylms' ), implode( ', ', $rendered['unknown_tags'] ) ),
				array( 'tags' => $rendered['unknown_tags'] )
			);
		}

		set_transient( $key, time() + self::TEST_INTERVAL, self::TEST_INTERVAL );

		/* translators: %s: email subject. */
		$subject = sprintf( __( '[Test] %s', 'ohmylms' ), $rendered['subject'] );
		$body    = '<html><body>' . $rendered['html'] . '</body></html>';
		$sent    = wp_mail( $user->user_email, $subject, $body, self::mail_headers( Emails::get_email_settings() ) );
		if ( ! $sent ) {
			delete_transient( $key );
			return new WP_Error( 'ohmylms_email_send_failed', __( 'WordPress could not send the test email. Check the site mail configuration.', 'ohmylms' ), array( 'status' => 500 ) );
		}

		return array(
			'sent'    => true,
			'to'      => $user->user_email,
			'subject' => $subject,
		);
	}
}
