<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class file; see docs/CODING-STANDARDS.md.
/**
 * Login and sign-up on the Interactivity API (`ohmylms/forms`, batch L5): server state for the
 * forms, directives for theme overrides copied before L5, and the same forms posted without
 * JavaScript.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Extensions;

defined( 'ABSPATH' ) || exit;

/** Login and sign-up forms: state, decoration and the no-JavaScript post. */
final class AuthForms {

	const ACTIONS = array(
		'ohmylms_login'  => 'login',
		'ohmylms_signup' => 'signup',
	);

	const FORM = array(
		'data-wp-on--submit'      => 'actions.submit',
		'data-wp-class--is-busy'  => 'context.busy',
		'data-wp-bind--aria-busy' => 'context.busy',
		'data-wp-style--display'  => 'state.authDisplay',
	);

	/**
	 * Server-side errors of a form posted without JavaScript, by form kind.
	 *
	 * @var array<string,string>
	 */
	private static $errors = array();

	/**
	 * Form to show first after a post without JavaScript ('' keeps each form's default).
	 *
	 * @var string
	 */
	private static $view = '';

	/**
	 * Pending email verification message after a sign-up without JavaScript.
	 *
	 * @var string
	 */
	private static $pending = '';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'ohmylms_template_html', array( __CLASS__, 'template' ), 16, 2 );
		add_action( 'wp_loaded', array( __CLASS__, 'handle_post' ), 20 );
	}

	/**
	 * Run a login or sign-up form posted to a page (no JavaScript) through the same code as the
	 * `ohmylms_login` / `ohmylms_signup` actions. Success redirects; an error or a pending
	 * verification is rendered by the form on this request.
	 */
	public static function handle_post() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Ajax::*_response() verify the form nonces.
		if ( wp_doing_ajax() || is_admin() || ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] || ! isset( $_POST['action'] ) ) {
			return;
		}
		$action = sanitize_key( wp_unslash( $_POST['action'] ) );
		// phpcs:enable
		if ( ! isset( self::ACTIONS[ $action ] ) ) {
			return;
		}
		$kind     = self::ACTIONS[ $action ];
		$response = 'login' === $kind ? \OhMyLMS\Ajax::login_response() : \OhMyLMS\Ajax::signup_response();
		if ( null === $response ) {
			return;
		}
		$status = isset( $response['status'] ) ? $response['status'] : 'error';
		if ( 'success' === $status && ! empty( $response['redirect_url'] ) ) {
			if ( wp_safe_redirect( $response['redirect_url'] ) ) {
				exit;
			}
			return;
		}
		self::$view = $kind;
		$message    = self::plain_text( $response['message'] ?? '' );
		if ( 'pending_verification' === $status ) {
			self::$pending = '' !== $message ? $message : self::messages()['pending'];
			return;
		}
		self::$errors[ $kind ] = '' !== $message ? $message : self::messages()['error'];
	}

	/**
	 * Text of a server message that may hold notice markup and entities.
	 *
	 * @param mixed $message Message.
	 * @return string
	 */
	public static function plain_text( $message ) {
		$text = html_entity_decode( wp_strip_all_tags( (string) $message ), ENT_QUOTES, 'UTF-8' );
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Values a form template needs: whether it starts hidden, its inline style, its context and
	 * a server error to show.
	 *
	 * @param string $kind   `login` or `signup`.
	 * @param bool   $hidden The template's `hidden` argument.
	 * @return array{hidden:bool, style:string, context:string, error:string}
	 */
	public static function view( $kind, $hidden ) {
		$error  = self::$errors[ $kind ] ?? '';
		$hidden = (bool) $hidden;
		$shown  = false;
		if ( '' !== self::$view ) {
			$hidden = self::$view !== $kind || ( 'signup' === $kind && '' !== self::$pending );
			$shown  = ! $hidden;
		}
		$context = array(
			'authForm'   => $kind,
			'authHidden' => $hidden,
			'busy'       => false,
			'error'      => $error,
		);
		if ( 'signup' === $kind ) {
			$context = array( 'consent' => false ) + $context;
		}
		self::enqueue();
		return array(
			'hidden'  => $hidden,
			'style'   => $hidden ? 'display:none;' : ( $shown ? 'display:block;' : '' ),
			'context' => (string) wp_json_encode( $context ),
			'error'   => $error,
		);
	}

	/**
	 * Pending email verification message rendered by the sign-up template.
	 *
	 * @return string
	 */
	public static function pending() {
		return self::$pending;
	}

	/**
	 * Translated messages for `ohmylms/forms`.
	 *
	 * @return array<string,string>
	 */
	public static function messages() {
		return array(
			'error'            => __( 'Something went wrong. Please try again.', 'ohmylms' ),
			'usernameRequired' => __( 'Username is required.', 'ohmylms' ),
			'passwordShort'    => __( 'Password must be at least 8 characters long.', 'ohmylms' ),
			'consent'          => __( 'Please accept the Privacy Policy.', 'ohmylms' ),
			'pending'          => __( 'Account created! Please check your inbox and verify your email before accessing your courses.', 'ohmylms' ),
		);
	}

	/**
	 * Enqueue `ohmylms/forms` with the login and sign-up state.
	 */
	public static function enqueue() {
		Interactivity::enqueue( 'ohmylms/forms' );
		if ( Interactivity::once( 'auth-forms-state' ) ) {
			wp_interactivity_state(
				'ohmylms/forms',
				array(
					'authView'       => self::$view,
					'pendingMessage' => self::$pending,
					'messages'       => self::messages(),
				)
			);
		}
	}

	/**
	 * Decorate a rendered template.
	 *
	 * @param string $html     Template HTML.
	 * @param string $template Template name.
	 * @return string
	 */
	public static function template( $html, $template ) {
		if ( ! is_string( $html ) || '' === $html || 0 === strpos( (string) $template, 'emails/' ) ) {
			return $html;
		}
		return self::decorate( $html );
	}

	/**
	 * Add the L5 directives to login and sign-up forms that lack them (theme overrides): submit,
	 * busy state, the login/sign-up switch and the form context. Forms rendered by the plugin's
	 * templates already carry them and are left alone.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function decorate( $html ) {
		if ( false === strpos( $html, 'ohmylms-login-signup' ) && false === strpos( $html, 'ohmylms-show-' ) ) {
			return $html;
		}
		$tags    = new \WP_HTML_Tag_Processor( $html );
		$found   = false;
		$in_form = false;
		while ( $tags->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
			$tag = $tags->get_tag();
			if ( $tags->is_tag_closer() ) {
				if ( 'FORM' === $tag ) {
					$in_form = false;
				}
				continue;
			}
			if ( 'FORM' === $tag && $tags->has_class( 'ohmylms-login-signup' ) ) {
				$in_form = true;
				$found   = true;
				if ( null === $tags->get_attribute( 'data-wp-on--submit' ) ) {
					self::form( $tags );
				}
				continue;
			}
			$island = $in_form ? array() : array( 'data-wp-interactive' => 'ohmylms/forms' );
			if ( 'A' === $tag && ( $tags->has_class( 'ohmylms-show-signup-form' ) || $tags->has_class( 'ohmylms-show-login-form' ) ) ) {
				$found = true;
				if ( null === $tags->get_attribute( 'data-wp-on--click' ) ) {
					$action = $tags->has_class( 'ohmylms-show-signup-form' ) ? 'actions.showSignup' : 'actions.showLogin';
					foreach ( $island + array(
						'data-wp-on--click' => $action,
						'role'              => 'button',
					) as $name => $value ) {
						$tags->set_attribute( $name, $value );
					}
				}
			} elseif ( $in_form && 'BUTTON' === $tag && $tags->has_class( 'ohmylms-form-login-submit' ) && null === $tags->get_attribute( 'data-wp-bind--disabled' ) ) {
				$tags->set_attribute( 'data-wp-bind--disabled', 'state.submitDisabled' );
			}
		}
		if ( $found ) {
			self::enqueue();
		}
		return $tags->get_updated_html();
	}

	/**
	 * Decorate a login or sign-up `<form>` from a theme override.
	 *
	 * @param \WP_HTML_Tag_Processor $tags Processor on the form tag.
	 */
	private static function form( $tags ) {
		$kind    = $tags->has_class( 'ohmylms-form-signup' ) ? 'signup' : 'login';
		$style   = strtolower( str_replace( ' ', '', (string) $tags->get_attribute( 'style' ) ) );
		$context = json_decode( (string) $tags->get_attribute( 'data-wp-context' ), true );
		$context = ( is_array( $context ) ? $context : array() ) + array(
			'authForm'   => $kind,
			'authHidden' => false !== strpos( $style, 'display:none' ),
			'busy'       => false,
			'error'      => '',
		);
		if ( null === $tags->get_attribute( 'data-wp-interactive' ) ) {
			$tags->set_attribute( 'data-wp-interactive', 'ohmylms/forms' );
		}
		$tags->set_attribute( 'data-wp-context', (string) wp_json_encode( $context ) );
		foreach ( self::FORM as $name => $value ) {
			$tags->set_attribute( $name, $value );
		}
	}
}
