<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Emails in the language of their recipient.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\I18n;

use WP_Locale_Switcher;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Composes an email in its recipient's locale, whatever the language of the request that sends it.
 *
 * A user's locale is their `locale` user meta (Users › Profile › Language, also written by the
 * learner switcher); without one, or for an address that belongs to no user, the site locale
 * (`get_locale()`). Never the cookie or the browser of the current request. A locale WordPress has no
 * language pack for falls back to the site locale too. While switched, `determine_locale()`, every
 * loaded text domain (including `ohmylms`) and `$wp_locale` (dates) follow the recipient; afterwards the
 * previous locale is restored, also when composing throws. Calls nest.
 * See "Interface languages" in docs/DEVELOPMENT.md.
 */
final class RecipientLocale {

	/**
	 * Locales WordPress can switch to: `en_US` and the installed core language packs.
	 *
	 * @var string[]|null
	 */
	private static $available = null;

	/**
	 * Run `$compose` in the locale of one recipient.
	 *
	 * @param int|WP_User|string $recipient User ID, user or email address.
	 * @param callable           $compose   Composes (and usually sends) the email; receives the locale.
	 * @return mixed What `$compose` returns.
	 */
	public static function run( $recipient, callable $compose ) {
		$user = self::user( $recipient );
		return self::within( self::user_locale( $user ), $user ? (int) $user->ID : 0, $compose );
	}

	/**
	 * Run `$compose` once per recipient language. When all recipients share one language it receives
	 * `$recipients` unchanged (so `wp_mail()` gets the same `$to`); otherwise the recipients of each
	 * language as a list, in their original order.
	 *
	 * @param int|WP_User|string|array $recipients Recipient, list of recipients or comma separated addresses.
	 * @param callable                 $compose    Receives the recipients of one language and the locale.
	 * @return array<string, mixed> Locale => what `$compose` returned.
	 */
	public static function each( $recipients, callable $compose ) {
		$groups  = self::group( $recipients );
		$results = array();
		foreach ( $groups as $locale => $group ) {
			$to                 = 1 === count( $groups ) ? $recipients : $group['recipients'];
			$results[ $locale ] = self::within(
				$locale,
				$group['user_id'],
				static function ( $active ) use ( $compose, $to ) {
					return $compose( $to, $active );
				}
			);
		}
		return $results;
	}

	/**
	 * Run `$compose` in a given locale, for example to render an email's defaults in one language. A
	 * locale WordPress cannot switch to leaves the current one active.
	 *
	 * @param string   $locale  Locale such as `mn`.
	 * @param callable $compose Receives the locale.
	 * @return mixed What `$compose` returns.
	 */
	public static function in_locale( $locale, callable $compose ) {
		return self::within( (string) $locale, 0, $compose );
	}

	/**
	 * Recipients grouped by locale, in order of first appearance.
	 *
	 * @param int|WP_User|string|array $recipients Recipient, list of recipients or comma separated addresses.
	 * @return array<string, array{recipients: array, user_id: int}> Locale => recipients, and the user
	 *         when the group is exactly one user (0 otherwise).
	 */
	public static function group( $recipients ) {
		if ( is_string( $recipients ) ) {
			$recipients = explode( ',', $recipients );
		} elseif ( ! is_array( $recipients ) ) {
			$recipients = array( $recipients );
		}
		$groups = array();
		foreach ( $recipients as $recipient ) {
			if ( is_string( $recipient ) ) {
				$recipient = trim( $recipient );
				if ( '' === $recipient ) {
					continue;
				}
			}
			$user   = self::user( $recipient );
			$locale = self::user_locale( $user );
			if ( ! isset( $groups[ $locale ] ) ) {
				$groups[ $locale ] = array(
					'recipients' => array(),
					'user_id'    => $user ? (int) $user->ID : 0,
				);
			} else {
				$groups[ $locale ]['user_id'] = 0;
			}
			$groups[ $locale ]['recipients'][] = $recipient;
		}
		return $groups;
	}

	/**
	 * The locale an email to this recipient is written in.
	 *
	 * @param int|WP_User|string $recipient User ID, user or email address.
	 * @return string
	 */
	public static function locale_of( $recipient ) {
		return self::user_locale( self::user( $recipient ) );
	}

	/**
	 * The site locale (Settings › General › Site Language), also while another locale is switched to.
	 *
	 * @return string
	 */
	public static function site_locale() {
		$switcher = self::switcher();
		if ( ! $switcher || ! $switcher->is_switched() ) {
			return get_locale();
		}
		$filter   = array( $switcher, 'filter_locale' );
		$priority = has_filter( 'locale', $filter );
		if ( false === $priority ) {
			return get_locale();
		}
		remove_filter( 'locale', $filter, $priority );
		try {
			return get_locale();
		} finally {
			add_filter( 'locale', $filter, $priority );
		}
	}

	/**
	 * Switch to a locale, run `$compose`, and switch back if the locale changed.
	 *
	 * @param string   $locale  Locale.
	 * @param int      $user_id User the email is for, 0 for none or several.
	 * @param callable $compose Receives the locale.
	 * @return mixed
	 */
	private static function within( $locale, $user_id, callable $compose ) {
		$switcher = self::switcher();
		// False when the locale is already active: nothing to restore then.
		$switched = $switcher && $switcher->switch_to_locale( $locale, $user_id ? (int) $user_id : false );
		try {
			return $compose( $locale );
		} finally {
			if ( $switched ) {
				restore_previous_locale();
			}
		}
	}

	/**
	 * The user behind a recipient.
	 *
	 * @param mixed $recipient User ID, user, or an address such as `a@b.c` or `Name <a@b.c>`.
	 * @return WP_User|null
	 */
	private static function user( $recipient ) {
		if ( $recipient instanceof WP_User ) {
			return $recipient->exists() ? $recipient : null;
		}
		if ( is_int( $recipient ) || ( is_string( $recipient ) && ctype_digit( $recipient ) ) ) {
			$user = get_userdata( (int) $recipient );
			return $user ? $user : null;
		}
		if ( ! is_string( $recipient ) ) {
			return null;
		}
		$address = trim( $recipient );
		if ( preg_match( '/<([^<>]+)>\s*$/', $address, $match ) ) {
			$address = trim( $match[1] );
		}
		$user = is_email( $address ) ? get_user_by( 'email', $address ) : false;
		return $user ? $user : null;
	}

	/**
	 * A user's profile language when WordPress can switch to it, otherwise the site locale.
	 *
	 * @param WP_User|null $user User.
	 * @return string
	 */
	private static function user_locale( $user ) {
		$locale = $user ? (string) get_user_meta( (int) $user->ID, InterfaceLanguage::USER_META, true ) : '';
		if ( '' !== $locale && in_array( $locale, self::available(), true ) ) {
			return $locale;
		}
		return self::site_locale();
	}

	/**
	 * Locales WordPress can switch to.
	 *
	 * @return string[]
	 */
	private static function available() {
		if ( null === self::$available ) {
			self::$available = array_merge( array( 'en_US' ), get_available_languages() );
		}
		return self::$available;
	}

	/**
	 * WordPress' locale switcher, once it exists (after `plugins_loaded`).
	 *
	 * @return WP_Locale_Switcher|null
	 */
	private static function switcher() {
		return isset( $GLOBALS['wp_locale_switcher'] ) && $GLOBALS['wp_locale_switcher'] instanceof WP_Locale_Switcher ? $GLOBALS['wp_locale_switcher'] : null;
	}
}
