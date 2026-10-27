<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Saves one Settings card at a time without touching the learner's other details.
 *
 * Each card changes only its own fields. A learner's bio, phone, links and other profile data
 * are never reset because a different card was saved. Changing the email address or the password
 * requires the current password.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

use OhMyLMS\Data\Student;

defined( 'ABSPATH' ) || exit;

/** Validates and applies Settings card submissions. */
final class SettingsSave {

	const ACTION    = 'ohmylms_hub_settings';
	const MIN_PASS  = 12;
	const MAX_PASS  = 128;
	const FLASH_KEY = 'ohmylms_hub_settings_flash_';

	/** Cards and the Settings tab each returns to. */
	const CARDS = array(
		'account'       => 'account',
		'email'         => 'account',
		'password'      => 'account',
		'about'         => 'account',
		'notifications' => 'notifications',
	);

	/** Register the form handler for signed-in learners only. */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	/**
	 * Apply one card for one learner.
	 *
	 * @param int    $user_id Learner ID.
	 * @param string $card One of CARDS.
	 * @param array  $input Submitted values, already unslashed.
	 * @return array{saved:bool,errors:string[]}
	 */
	public static function apply( $user_id, $card, array $input ) {
		$user_id = (int) $user_id;
		$user    = $user_id ? get_userdata( $user_id ) : false;
		if ( ! $user || ! isset( self::CARDS[ $card ] ) ) {
			return array(
				'saved'  => false,
				'errors' => array( __( 'That change could not be saved.', 'ohmylms' ) ),
			);
		}
		$errors  = array();
		$student = new Student( $user_id );
		switch ( $card ) {
			case 'account':
				$first   = self::text( $input['first_name'] ?? '', 60 );
				$last    = self::text( $input['last_name'] ?? '', 60 );
				$display = self::text( $input['display_name'] ?? '', 60 );
				if ( '' === $display ) {
					$errors[] = __( 'Display name is required.', 'ohmylms' );
				} elseif ( is_email( $display ) ) {
					$errors[] = __( 'Display name cannot be an email address, for privacy.', 'ohmylms' );
				}
				// Phone, province, school and grade are saved only when the form sent them, so other roles are untouched.
				$details = LearnerProfile::validate( array_intersect_key( $input, array_flip( array( 'phone', 'province', 'school_name', 'grade' ) ) ) );
				$errors  = array_merge( $errors, $details['errors'] );
				if ( ! $errors ) {
					wp_update_user(
						array(
							'ID'           => $user_id,
							'first_name'   => $first,
							'last_name'    => $last,
							'display_name' => $display,
						)
					);
					$student->set_first_name( $first );
					$student->set_last_name( $last );
					$student->set_display_name( $display );
					$student->save();
					LearnerProfile::save( $user_id, $details['values'] );
				}
				break;
			case 'email':
				$email = sanitize_email( (string) ( $input['email'] ?? '' ) );
				if ( ! is_email( $email ) ) {
					$errors[] = __( 'Enter a valid email address.', 'ohmylms' );
				} elseif ( 0 === strcasecmp( $email, $user->user_email ) ) {
					$errors[] = __( 'That is already your email address.', 'ohmylms' );
				} elseif ( email_exists( $email ) ) {
					$errors[] = __( 'This email address is already registered.', 'ohmylms' );
				}
				if ( ! self::password_ok( $user, (string) ( $input['password_current'] ?? '' ) ) ) {
					$errors[] = __( 'Enter your current password to change your email address.', 'ohmylms' );
				}
				if ( ! $errors ) {
					wp_update_user(
						array(
							'ID'         => $user_id,
							'user_email' => $email,
						)
					);
				}
				break;
			case 'password':
				$current = (string) ( $input['password_current'] ?? '' );
				$new     = (string) ( $input['password1'] ?? '' );
				$confirm = (string) ( $input['password2'] ?? '' );
				if ( ! self::password_ok( $user, $current ) ) {
					$errors[] = __( 'Your current password is incorrect.', 'ohmylms' );
				}
				if ( strlen( $new ) < self::MIN_PASS || strlen( $new ) > self::MAX_PASS ) {
					/* translators: 1: minimum, 2: maximum password length. */
					$errors[] = sprintf( __( 'Choose a new password between %1$d and %2$d characters.', 'ohmylms' ), self::MIN_PASS, self::MAX_PASS );
				} elseif ( $new !== $confirm ) {
					$errors[] = __( 'The new passwords do not match.', 'ohmylms' );
				} elseif ( $new === $current ) {
					$errors[] = __( 'Choose a password different from your current one.', 'ohmylms' );
				}
				if ( ! $errors ) {
					wp_update_user(
						array(
							'ID'        => $user_id,
							'user_pass' => $new,
						)
					);
				}
				break;
			case 'about':
				// The phone number now lives in the Basics card; older forms that still send it are validated the same way.
				$phone  = LearnerProfile::validate( array_intersect_key( $input, array( 'phone' => true ) ) );
				$errors = array_merge( $errors, $phone['errors'] );
				if ( ! $errors ) {
					$student->set_bio( self::text( $input['bio'] ?? '', 1000, true ) );
					$student->set_interest( self::text( $input['interests'] ?? '', 300 ) );
					$student->set_country( self::text( $input['country'] ?? '', 80 ) );
					if ( isset( $phone['values']['phone'] ) ) {
						$student->set_phone( $phone['values']['phone'] );
					}
					$student->set_whatsapp( self::text( $input['whatsapp'] ?? '', 40 ) );
					$student->set_address( self::text( $input['address'] ?? '', 200 ) );
					$student->save();
				}
				break;
			default:
				foreach ( array( 'chapter', 'direct_messages', 'new_course_content', 'comments_replies' ) as $key ) {
					$value = ! empty( $input[ 'notification_' . $key ] ) ? 'on' : 'off';
					$student->{ 'set_notification_' . $key }( $value );
				}
				$student->save();
		}
		return array(
			'saved'  => ! $errors,
			'errors' => $errors,
		);
	}

	/**
	 * Form handler: nonce, signed-in learner, then apply, remember the result and go back.
	 */
	public static function handle() {
		$card = isset( $_POST['card'] ) ? sanitize_key( wp_unslash( $_POST['card'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified on the next line.
		if ( ! is_user_logged_in() || ! isset( self::CARDS[ $card ] ) || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::ACTION . '_' . $card ) ) {
			wp_die( esc_html__( 'This form has expired. Go back, reload the page and try again.', 'ohmylms' ), '', array( 'response' => 403 ) );
		}
		$fields = array( 'first_name', 'last_name', 'display_name', 'email', 'password_current', 'password1', 'password2', 'bio', 'interests', 'country', 'phone', 'province', 'school_name', 'grade', 'whatsapp', 'address', 'notification_chapter', 'notification_direct_messages', 'notification_new_course_content', 'notification_comments_replies' );
		$input  = array();
		foreach ( $fields as $field ) {
			if ( isset( $_POST[ $field ] ) && is_scalar( $_POST[ $field ] ) ) {
				$input[ $field ] = wp_unslash( $_POST[ $field ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- Validated and sanitized per card in apply(); passwords must not be altered.
			}
		}
		$result  = self::apply( get_current_user_id(), $card, $input );
		$user_id = get_current_user_id();
		if ( $result['saved'] && 'password' === $card ) {
			// Changing the password signs other sessions out; keep this one signed in.
			wp_set_auth_cookie( $user_id, true );
		}
		set_transient(
			self::FLASH_KEY . $user_id,
			array(
				'card'   => $card,
				'saved'  => $result['saved'],
				'errors' => $result['errors'],
			),
			MINUTE_IN_SECONDS
		);
		$role = isset( $_POST['hub_role'] ) && is_string( $_POST['hub_role'] ) ? sanitize_key( wp_unslash( $_POST['hub_role'] ) ) : '';
		$url  = Hub::url( 'account', self::CARDS[ $card ] );
		if ( 'teacher' === $role && TeacherWorkspace::available() ) {
			$url = TeacherWorkspace::url( 'account', self::CARDS[ $card ] );
		} elseif ( 'parent' === $role && ParentWorkspace::available() ) {
			$url = ParentWorkspace::url( 'account', self::CARDS[ $card ] );
		} elseif ( 'school' === $role && SchoolWorkspace::available() ) {
			$url = SchoolWorkspace::url( 'account', self::CARDS[ $card ] );
		}
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * The result of the last save for this learner, shown once.
	 *
	 * @param int $user_id Learner ID.
	 * @return array|null
	 */
	public static function flash( $user_id ) {
		$data = get_transient( self::FLASH_KEY . (int) $user_id );
		if ( false === $data ) {
			return null;
		}
		delete_transient( self::FLASH_KEY . (int) $user_id );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Whether a plain password matches this account.
	 *
	 * @param \WP_User $user Account.
	 * @param string   $password Password entered.
	 * @return bool
	 */
	private static function password_ok( $user, $password ) {
		return '' !== $password && wp_check_password( $password, $user->user_pass, $user->ID );
	}

	/**
	 * Plain bounded text.
	 *
	 * @param mixed $value Submitted value.
	 * @param int   $max Maximum characters.
	 * @param bool  $multiline Keep line breaks.
	 * @return string
	 */
	private static function text( $value, $max, $multiline = false ) {
		$value = $multiline ? sanitize_textarea_field( (string) $value ) : sanitize_text_field( (string) $value );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
	}
}
