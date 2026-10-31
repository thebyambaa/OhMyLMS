<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class file; see docs/CODING-STANDARDS.md.
/**
 * Learner page chrome on the Interactivity API: the toast region, the header account menu, the
 * profile drawer and scroll to top (`ohmylms/ui`), the shared form helpers (`ohmylms/forms`),
 * and the server configuration of the `ohmylms/interactivity` SDK.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Extensions;

defined( 'ABSPATH' ) || exit;

/** Decorates trusted templates with `ohmylms/ui` and `ohmylms/forms` directives. */
final class Chrome {

	const USER_MENU_ID     = 'ohmylms-user-dropdown';
	const PROFILE_PANEL_ID = 'ohmylms-profile-sidebar';

	const TOAST = array(
		'data-wp-interactive'          => 'ohmylms/ui',
		'data-wp-init'                 => 'callbacks.toastRegion',
		'data-wp-class--active'        => 'state.toast.visible',
		'data-wp-class--toast-success' => 'state.toastSuccess',
		'data-wp-class--toast-warning' => 'state.toastWarning',
		'data-wp-class--toast-danger'  => 'state.toastDanger',
		'data-wp-on--mouseenter'       => 'actions.pauseToast',
		'data-wp-on--mouseleave'       => 'actions.resumeToast',
		'data-wp-on--focusin'          => 'actions.pauseToast',
		'data-wp-on--focusout'         => 'actions.resumeToast',
		'data-wp-on--keydown'          => 'actions.toastKeydown',
		'role'                         => 'status',
		'aria-live'                    => 'polite',
		'aria-atomic'                  => 'true',
	);

	const USER_MENU = array(
		'data-wp-interactive'          => 'ohmylms/ui',
		'data-wp-context'              => '{"open":false}',
		'data-wp-class--show-dropdown' => 'context.open',
		'data-wp-on-document--click'   => 'actions.dropdownOutside',
		'data-wp-on-document--keydown' => 'actions.dropdownEscape',
		'data-wp-on--focusout'         => 'actions.dropdownFocusout',
	);

	const USER_MENU_TRIGGER = array(
		'role'                        => 'button',
		'aria-expanded'               => 'false',
		'aria-controls'               => self::USER_MENU_ID,
		'data-wp-bind--aria-expanded' => 'context.open',
		'data-wp-on--click'           => 'actions.dropdownToggle',
		'data-wp-on--keydown'         => 'actions.dropdownKeydown',
	);

	const DRAWER = array(
		'data-wp-interactive'          => 'ohmylms/ui',
		'data-wp-context'              => '{"drawerOpen":false,"drawerPanel":".ohmylms-student-profile-sidebar","drawerInside":".ohmylms-student-profile-sidebar,.ohmylms-hamburger"}',
		'data-wp-class--open-sidebar'  => 'context.drawerOpen',
		'data-wp-on-document--click'   => 'actions.drawerOutside',
		'data-wp-on-document--keydown' => 'actions.drawerEscape',
	);

	const DRAWER_TOGGLE = array(
		'role'                        => 'button',
		'tabindex'                    => '0',
		'aria-controls'               => self::PROFILE_PANEL_ID,
		'aria-expanded'               => 'false',
		'data-wp-bind--aria-expanded' => 'context.drawerOpen',
		'data-wp-on--click'           => 'actions.drawerToggle',
		'data-wp-on--keydown'         => 'actions.drawerKey',
	);

	const SCROLL_TOP = array(
		'data-wp-interactive'             => 'ohmylms/ui',
		'data-wp-context'                 => '{"scrolled":false,"threshold":600}',
		'data-wp-class--visible'          => 'context.scrolled',
		'data-wp-init'                    => 'callbacks.trackScroll',
		'data-wp-on-window--scroll' => 'callbacks.trackScroll',
	);

	const AUTH_FORM = array(
		'data-wp-interactive'  => 'ohmylms/forms',
		'data-wp-init---fold'   => 'callbacks.foldFilled',
		'data-wp-on--focusin'  => 'actions.fieldFocus',
		'data-wp-on--focusout' => 'actions.fieldBlur',
		'data-wp-on--input'    => 'actions.fieldInput',
	);

	const PASSWORD_TOGGLE = array(
		'role'                       => 'button',
		'tabindex'                   => '0',
		'aria-pressed'               => 'false',
		'data-wp-bind--aria-pressed' => 'context.revealed',
		'data-wp-on--click'          => 'actions.togglePassword',
		'data-wp-on--keydown'        => 'actions.passwordKey',
	);

	/**
	 * Whether `global/toast.php` has been rendered in this request.
	 *
	 * @var bool
	 */
	private static $toast_rendered = false;

	/**
	 * Whether the footer must print a toast region.
	 *
	 * @var bool
	 */
	private static $needs_region = false;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'ohmylms_template_html', array( __CLASS__, 'template' ), 15, 2 );
		add_filter( 'script_module_data_ohmylms/interactivity', array( __CLASS__, 'sdk_data' ) );
		add_action( 'wp_footer', array( __CLASS__, 'footer' ), 1 );
	}

	/**
	 * Server configuration read by `getConfig()` in assets/interactivity/sdk.js.
	 *
	 * @param array $data Module data.
	 * @return array
	 */
	public static function sdk_data( $data ) {
		return array_merge(
			(array) $data,
			array(
				'restRoot'  => esc_url_raw( rest_url() ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'messages'  => array(
					'error'        => __( 'Something went wrong. Please try again.', 'ohmylms' ),
					'network'      => __( 'Could not reach the server. Check your connection and try again.', 'ohmylms' ),
					'invalidJson'  => __( 'The server answered with invalid JSON.', 'ohmylms' ),
					'invalidNonce' => __( 'Your session has expired. Please reload the page and try again.', 'ohmylms' ),
					'aborted'      => __( 'The request was cancelled.', 'ohmylms' ),
					'timeout'      => __( 'The server took too long to answer. Please try again.', 'ohmylms' ),
					'confirm'      => __( 'Confirm', 'ohmylms' ),
					'cancel'       => __( 'Cancel', 'ohmylms' ),
					'dismiss'      => __( 'Dismiss notification', 'ohmylms' ),
				),
			)
		);
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
		if ( 'global/toast.php' === $template && false !== strpos( $html, 'ohmylms-toast' ) ) {
			// One live region per page: the first rendered region is the one `ohmylms/ui` drives.
			if ( self::$toast_rendered ) {
				return '';
			}
			self::$toast_rendered = true;
		}
		return self::forms( self::ui( $html ) );
	}

	/**
	 * Enqueue `ohmylms/ui` with its initial state, and make sure the page gets a toast region.
	 * Call it from PHP that renders a store which calls `toast()`.
	 */
	public static function enqueue_ui() {
		Interactivity::enqueue( 'ohmylms/ui' );
		self::$needs_region = true;
		if ( Interactivity::once( 'chrome-ui-state' ) ) {
			wp_interactivity_state( 'ohmylms/ui', self::ui_state() );
		}
	}

	/**
	 * Initial `ohmylms/ui` state for the chrome.
	 *
	 * @return array
	 */
	public static function ui_state() {
		return array(
			'toast'    => array(
				'id'      => '',
				'message' => '',
				'type'    => 'success',
				'visible' => false,
				'shown'   => false,
			),
			'messages' => array(
				'error'         => __( 'Something went wrong. Please try again.', 'ohmylms' ),
				'emailVerified' => __( 'Email verified! Your account is now fully active.', 'ohmylms' ),
				'verifySent'    => __( 'Verification email sent. Check your inbox!', 'ohmylms' ),
				'verifyExpired' => __( 'Verification link expired. Please request a new one.', 'ohmylms' ),
				'verifyInvalid' => __( 'Invalid verification link.', 'ohmylms' ),
			),
		);
	}

	/**
	 * Pages that load the legacy learner script (its toasts, the verification notices and the
	 * resend link now run in `ohmylms/ui`) get the chrome module and a toast region. The region is
	 * styled by the learner stylesheet, which those pages load.
	 */
	public static function footer() {
		if ( ! self::$needs_region && ! wp_script_is( 'ohmylms-frontend', 'enqueued' ) && ! wp_script_is( 'ohmylms-frontend', 'done' ) ) {
			return;
		}
		self::enqueue_ui();
		if ( ! self::$toast_rendered && function_exists( 'ohmylms_get_template' ) ) {
			ohmylms_get_template( 'global/toast.php' );
		}
	}

	/**
	 * Add `ohmylms/ui` directives to the toast region, the header account menu, the profile
	 * drawer and the scroll-to-top button. Elements that already carry directives are kept.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function ui( $html ) {
		$drawer = false !== strpos( $html, 'ohmylms-hamburger' );
		$menu   = false !== strpos( $html, 'ohmylms-user-avatar' );
		if ( ! $drawer && ! $menu && false === strpos( $html, 'ohmylms-toast' ) && false === strpos( $html, 'ohmylms-scroll-to-top' ) ) {
			return $html;
		}
		$tags  = new \WP_HTML_Tag_Processor( $html );
		$found = false;
		$scope = null;
		while ( $tags->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
			$tag = $tags->get_tag();
			if ( $scope && $tag === $scope['tag'] ) {
				$scope['depth'] += $tags->is_tag_closer() ? -1 : 1;
				if ( 0 === $scope['depth'] ) {
					$scope = null;
					continue;
				}
			}
			if ( $tags->is_tag_closer() ) {
				continue;
			}
			if ( $scope ) {
				if ( 'toast' === $scope['name'] ) {
					self::toast_part( $tags );
				} elseif ( 'BUTTON' === $tag ) {
					self::add( $tags, array( 'data-wp-on--click' => 'actions.scrollToTop' ) );
				}
				continue;
			}
			if ( 'ohmylms-toast' === $tags->get_attribute( 'id' ) ) {
				self::root( $tags, self::TOAST );
				$scope = array(
					'name'  => 'toast',
					'tag'   => $tag,
					'depth' => 1,
				);
				$found = true;
			} elseif ( $tags->has_class( 'ohmylms-scroll-to-top' ) ) {
				self::root( $tags, self::SCROLL_TOP );
				$scope = array(
					'name'  => 'scroll',
					'tag'   => $tag,
					'depth' => 1,
				);
				$found = true;
			} elseif ( $menu && $tags->has_class( 'ohmylms-user' ) ) {
				self::root( $tags, self::USER_MENU );
				$found = true;
			} elseif ( $menu && $tags->has_class( 'ohmylms-user-avatar' ) ) {
				if ( self::add( $tags, self::USER_MENU_TRIGGER ) && null === $tags->get_attribute( 'aria-label' ) ) {
					$tags->set_attribute( 'aria-label', __( 'Account menu', 'ohmylms' ) );
				}
			} elseif ( $tags->has_class( 'ohmylms-user-dropdown' ) && null === $tags->get_attribute( 'id' ) ) {
				$tags->set_attribute( 'id', self::USER_MENU_ID );
			} elseif ( $drawer && $tags->has_class( 'ohmylms-student-profile' ) ) {
				self::root( $tags, self::DRAWER );
				$found = true;
			} elseif ( $drawer && $tags->has_class( 'ohmylms-hamburger' ) ) {
				self::add( $tags, self::DRAWER_TOGGLE );
			} elseif ( $tags->has_class( 'ohmylms-student-profile-sidebar' ) && null === $tags->get_attribute( 'id' ) ) {
				$tags->set_attribute( 'id', self::PROFILE_PANEL_ID );
			}
		}
		if ( $found ) {
			self::enqueue_ui();
		}
		return $tags->get_updated_html();
	}

	/**
	 * Children of the toast region.
	 *
	 * @param \WP_HTML_Tag_Processor $tags Processor on an opening tag inside the region.
	 */
	private static function toast_part( $tags ) {
		if ( $tags->has_class( 'ohmylms-toast-content' ) && null === $tags->get_attribute( 'data-wp-bind--hidden' ) ) {
			$tags->set_attribute( 'data-wp-bind--hidden', 'state.toastHidden' );
			$tags->set_attribute( 'hidden', true );
		} elseif ( $tags->has_class( 'ohmylms-message' ) && null === $tags->get_attribute( 'data-wp-text' ) ) {
			$tags->set_attribute( 'data-wp-text', 'state.toast.message' );
		} elseif ( ( 'ohmylms-close-toast' === $tags->get_attribute( 'id' ) || $tags->has_class( 'ohmylms-close' ) ) && null === $tags->get_attribute( 'data-wp-on--click' ) ) {
			$tags->set_attribute( 'data-wp-on--click', 'actions.dismissToast' );
			if ( 'BUTTON' !== $tags->get_tag() ) {
				$tags->set_attribute( 'role', 'button' );
				$tags->set_attribute( 'tabindex', '0' );
				$tags->set_attribute( 'data-wp-on--keydown', 'actions.dismissToastKey' );
			}
			if ( null === $tags->get_attribute( 'aria-label' ) ) {
				$tags->set_attribute( 'aria-label', __( 'Dismiss notification', 'ohmylms' ) );
			}
		}
	}

	/**
	 * Add `ohmylms/forms` directives: floating labels and the terms gate on the login and sign-up
	 * forms, password visibility toggles, keyboard toggling of checkbox labels, and the phone
	 * filter. Fields outside a decorated form become their own `ohmylms/forms` island.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function forms( $html ) {
		$needles = array( 'ohmylms-login-signup', 'ohmylms-password-show', 'ohmylms-form-login-rememberme', 'ohmylms-tnc-label', 'phone', 'whatsapp' );
		$match   = false;
		foreach ( $needles as $needle ) {
			$match = $match || false !== strpos( $html, $needle );
		}
		if ( ! $match ) {
			return $html;
		}
		$tags    = new \WP_HTML_Tag_Processor( $html );
		$found   = false;
		$in_form = false;
		$scope   = null;
		while ( $tags->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
			$tag = $tags->get_tag();
			if ( $scope && $tag === $scope['tag'] ) {
				$scope['depth'] += $tags->is_tag_closer() ? -1 : 1;
				if ( 0 === $scope['depth'] ) {
					$scope = null;
					continue;
				}
			}
			if ( $tags->is_tag_closer() ) {
				if ( 'FORM' === $tag ) {
					$in_form = false;
				}
				continue;
			}
			$island = $in_form ? array() : array( 'data-wp-interactive' => 'ohmylms/forms' );
			if ( $scope ) {
				self::password_part( $tags );
				continue;
			}
			if ( 'FORM' === $tag && $tags->has_class( 'ohmylms-login-signup' ) ) {
				$in_form = true;
				$found   = true;
				if ( null === $tags->get_attribute( 'data-wp-interactive' ) ) {
					self::set( $tags, self::AUTH_FORM );
					if ( $tags->has_class( 'ohmylms-form-signup' ) ) {
						$tags->set_attribute( 'data-wp-context', '{"consent":false}' );
						$tags->set_attribute( 'data-wp-init---consent', 'callbacks.syncConsent' );
					}
				}
			} elseif ( $tags->has_class( 'ohmylms-password-show' ) ) {
				$found = true;
				$scope = array(
					'tag'   => $tag,
					'depth' => 1,
				);
				if ( null === $tags->get_attribute( 'data-wp-context' ) ) {
					self::set( $tags, $island + array( 'data-wp-context' => '{"revealed":false}' ) );
				}
			} elseif ( 'LABEL' === $tag && ( $tags->has_class( 'ohmylms-form-login-rememberme' ) || $tags->has_class( 'ohmylms-tnc-label' ) ) ) {
				$found = true;
				if ( self::add( $tags, $island + array( 'data-wp-on--keydown' => 'actions.checkboxKey' ) ) && null === $tags->get_attribute( 'tabindex' ) ) {
					$tags->set_attribute( 'tabindex', '0' );
				}
			} elseif ( 'INPUT' === $tag && $in_form && 'tnc-accept' === $tags->get_attribute( 'name' ) ) {
				self::add( $tags, array( 'data-wp-on--change' => 'actions.consentChange' ) );
			} elseif ( 'INPUT' === $tag && in_array( $tags->get_attribute( 'name' ), array( 'phone', 'whatsapp' ), true ) ) {
				$found = true;
				if ( self::add( $tags, $island + array( 'data-wp-on--input' => 'actions.filterPhone' ) ) && null === $tags->get_attribute( 'inputmode' ) ) {
					$tags->set_attribute( 'inputmode', 'tel' );
				}
			} elseif ( 'BUTTON' === $tag && $in_form && $tags->has_class( 'ohmylms-form-signup-submit' ) ) {
				self::add( $tags, array( 'data-wp-bind--disabled' => 'state.consentMissing' ) );
			}
		}
		if ( $found ) {
			Interactivity::enqueue( 'ohmylms/forms' );
		}
		return $tags->get_updated_html();
	}

	/**
	 * Children of a `.ohmylms-password-show` wrapper.
	 *
	 * @param \WP_HTML_Tag_Processor $tags Processor on an opening tag inside the wrapper.
	 */
	private static function password_part( $tags ) {
		$tag = $tags->get_tag();
		if ( 'INPUT' === $tag && $tags->has_class( 'show-password-checkbox' ) ) {
			self::add(
				$tags,
				array(
					'data-wp-bind--checked' => 'context.revealed',
					'tabindex'              => '-1',
				)
			);
		} elseif ( 'INPUT' === $tag && 'password' === strtolower( (string) $tags->get_attribute( 'type' ) ) ) {
			self::add( $tags, array( 'data-wp-bind--type' => 'state.passwordType' ) );
		} elseif ( 'LABEL' === $tag && $tags->has_class( 'show-password-icon' ) ) {
			if ( self::add( $tags, self::PASSWORD_TOGGLE ) && null === $tags->get_attribute( 'aria-label' ) ) {
				$tags->set_attribute( 'aria-label', __( 'Show password', 'ohmylms' ) );
			}
		} elseif ( $tags->has_class( 'eye-on' ) ) {
			self::add( $tags, array( 'data-wp-style--display' => 'state.eyeOnDisplay' ) );
		} elseif ( $tags->has_class( 'eye-off' ) ) {
			self::add( $tags, array( 'data-wp-style--display' => 'state.eyeOffDisplay' ) );
		}
	}

	/**
	 * Decorate a store root unless it already belongs to a store.
	 *
	 * @param \WP_HTML_Tag_Processor $tags       Processor.
	 * @param array                  $attributes Attributes.
	 */
	private static function root( $tags, array $attributes ) {
		if ( null === $tags->get_attribute( 'data-wp-interactive' ) ) {
			self::set( $tags, $attributes );
		}
	}

	/**
	 * Add attributes unless the element already has the first directive of the set.
	 *
	 * @param \WP_HTML_Tag_Processor $tags       Processor.
	 * @param array                  $attributes Attributes; the first `data-wp-*` key is the marker.
	 * @return bool Whether the attributes were added.
	 */
	private static function add( $tags, array $attributes ) {
		foreach ( array_keys( $attributes ) as $name ) {
			if ( 0 === strpos( $name, 'data-wp-' ) && 'data-wp-interactive' !== $name ) {
				if ( null !== $tags->get_attribute( $name ) ) {
					return false;
				}
				break;
			}
		}
		self::set( $tags, $attributes );
		return true;
	}

	/**
	 * Set attributes.
	 *
	 * @param \WP_HTML_Tag_Processor $tags       Processor.
	 * @param array                  $attributes Attributes.
	 */
	private static function set( $tags, array $attributes ) {
		foreach ( $attributes as $name => $value ) {
			$tags->set_attribute( $name, $value );
		}
	}
}
