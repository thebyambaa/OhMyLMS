<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Set-up checks of the site for administrators: payments, e-mail, certificates, cron and privacy.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard;

use OhMyLMS\Emails\Emails;
use OhMyLMS\Settings\PaymentGatewaySettings;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only checks over existing settings (docs/api/dashboard.md, "Site health"). Each check is
 * `{ id, status: ok|warning|error, label, detail, action: { label, route|null, href|null } | null }`;
 * `route` is an app route, `href` a wp-admin URL. Nothing here writes or calls out.
 */
final class SiteHealthChecks {

	/**
	 * Minutes a due cron event may wait before cron counts as not running.
	 */
	const CRON_GRACE_MINUTES = 15;

	/**
	 * Every check, in display order.
	 *
	 * @param int|null $now Current timestamp (tests).
	 * @return array[]
	 */
	public static function all( $now = null ) {
		return array(
			self::payments(),
			self::email(),
			self::certificates(),
			self::cron( null === $now ? time() : (int) $now, _get_cron_array() ),
			self::privacy(),
		);
	}

	/**
	 * At least one payment gateway is enabled with every credential of its current mode.
	 *
	 * @return array
	 */
	public static function payments() {
		$connected  = array();
		$incomplete = array();
		foreach ( PaymentGatewaySettings::gateways() as $gateway ) {
			$state = PaymentGatewaySettings::read( PaymentGatewaySettings::definition( $gateway ) );
			if ( ! $state['enabled'] ) {
				continue;
			}
			$ready = true;
			foreach ( $state['fields'] as $field ) {
				$secret = (array) $state['secrets'];
				if ( 'secret' === $field['type'] && ( null === $field['mode'] || $field['mode'] === $state['mode'] ) && empty( $secret[ $field['name'] ]['has_value'] ) ) {
					$ready = false;
				}
			}
			$label = null === $state['mode'] ? $state['title'] : sprintf( '%s (%s)', $state['title'], 'test' === $state['mode'] ? __( 'test mode', 'ohmylms' ) : __( 'live', 'ohmylms' ) );
			if ( $ready ) {
				$connected[] = $label;
			} else {
				$incomplete[] = $state['title'];
			}
		}
		if ( $connected ) {
			/* translators: %s: gateway names. */
			return self::check( 'payment_gateway', 'ok', __( 'Payment gateway connected', 'ohmylms' ), sprintf( __( 'Connected: %s.', 'ohmylms' ), implode( ', ', $connected ) ), null, null, null );
		}
		$detail = $incomplete
			/* translators: %s: gateway names. */
			? sprintf( __( 'Enabled but missing credentials: %s.', 'ohmylms' ), implode( ', ', $incomplete ) )
			: __( 'No payment gateway is enabled, so paid courses cannot be bought.', 'ohmylms' );
		return self::check( 'payment_gateway', 'warning', __( 'Payment gateway connected', 'ohmylms' ), $detail, __( 'Set up payments', 'ohmylms' ), '/settings?tab=payment-gateways', null );
	}

	/**
	 * A sender name and a valid sender address are set for OhMyLMS e-mails.
	 *
	 * @return array
	 */
	public static function email() {
		$settings = Emails::get_email_settings();
		$name     = (string) $settings['ohmylms_email_sender_name'];
		$address  = (string) $settings['ohmylms_email_sender_email_address'];
		if ( '' !== $name && is_email( $address ) ) {
			/* translators: 1: sender name, 2: sender address. */
			return self::check( 'email_sender', 'ok', __( 'Email sender configured', 'ohmylms' ), sprintf( __( 'E-mails are sent as %1$s <%2$s>.', 'ohmylms' ), $name, $address ), null, null, null );
		}
		return self::check( 'email_sender', 'warning', __( 'Email sender configured', 'ohmylms' ), __( 'No sender name and address are set; e-mails go out with the WordPress default sender and may land in spam.', 'ohmylms' ), __( 'Set the sender', 'ohmylms' ), '/emails', null );
	}

	/**
	 * At least one published certificate template, and how many courses award one.
	 *
	 * @return array
	 */
	public static function certificates() {
		global $wpdb;
		$templates = (int) wp_count_posts( OHMYLMS_CERTIFICATE_CPT )->publish;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Cheap count for an administrator check.
		$courses = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT( DISTINCT r.course_id ) FROM %i r INNER JOIN %i c ON c.ID = r.course_id AND c.post_status = 'publish' INNER JOIN %i t ON t.ID = r.certificate_id AND t.post_status = 'publish'",
				$wpdb->prefix . 'ohmylms_certificate_relationship',
				$wpdb->posts,
				$wpdb->posts
			)
		);
		if ( $templates > 0 ) {
			/* translators: 1: number of templates, 2: number of courses. */
			return self::check( 'certificate_template', 'ok', __( 'Certificate template set', 'ohmylms' ), sprintf( _n( '%1$s published template; %2$s course awards a certificate.', '%1$s published templates; %2$s courses award a certificate.', $courses, 'ohmylms' ), number_format_i18n( $templates ), number_format_i18n( $courses ) ), null, null, null );
		}
		return self::check( 'certificate_template', 'warning', __( 'Certificate template set', 'ohmylms' ), __( 'No certificate template is published, so learners get no certificate when they finish a course.', 'ohmylms' ), __( 'Create a template', 'ohmylms' ), '/certificates', null );
	}

	/**
	 * Scheduled tasks run: no event is more than 15 minutes overdue. Pure apart from the constant.
	 *
	 * @param int        $now  Current timestamp.
	 * @param array|bool $cron Cron array (`_get_cron_array()`).
	 * @return array
	 */
	public static function cron( $now, $cron ) {
		$due = null;
		foreach ( is_array( $cron ) ? $cron : array() as $timestamp => $hooks ) {
			if ( is_numeric( $timestamp ) && $hooks ) {
				$due = null === $due ? (int) $timestamp : min( $due, (int) $timestamp );
			}
		}
		$disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$late     = null === $due ? 0 : (int) floor( ( $now - $due ) / MINUTE_IN_SECONDS );
		if ( null === $due ) {
			return self::check( 'cron', 'warning', __( 'Cron running', 'ohmylms' ), __( 'No scheduled task is queued, so reminders and clean-ups never run.', 'ohmylms' ), __( 'Check site health', 'ohmylms' ), null, admin_url( 'site-health.php' ) );
		}
		if ( $late > self::CRON_GRACE_MINUTES ) {
			$detail = sprintf(
				/* translators: %s: number of minutes. */
				_n( 'The oldest scheduled task is %s minute overdue.', 'The oldest scheduled task is %s minutes overdue.', $late, 'ohmylms' ),
				number_format_i18n( $late )
			);
			if ( $disabled ) {
				$detail .= ' ' . __( 'WP-Cron is disabled (DISABLE_WP_CRON); make sure a server cron job calls wp-cron.php.', 'ohmylms' );
			}
			return self::check( 'cron', 'error', __( 'Cron running', 'ohmylms' ), $detail, __( 'Check site health', 'ohmylms' ), null, admin_url( 'site-health.php' ) );
		}
		$detail = $disabled ? __( 'Scheduled tasks run on time (server cron).', 'ohmylms' ) : __( 'Scheduled tasks run on time.', 'ohmylms' );
		return self::check( 'cron', 'ok', __( 'Cron running', 'ohmylms' ), $detail, null, null, null );
	}

	/**
	 * A published privacy policy page is set (Settings › Privacy).
	 *
	 * @return array
	 */
	public static function privacy() {
		$page = (int) get_option( 'wp_page_for_privacy_policy', 0 );
		if ( $page > 0 && 'publish' === get_post_status( $page ) ) {
			/* translators: %s: page title. */
			return self::check( 'privacy_policy', 'ok', __( 'Privacy policy page linked', 'ohmylms' ), sprintf( __( 'Linked: %s.', 'ohmylms' ), html_entity_decode( wp_strip_all_tags( get_the_title( $page ) ), ENT_QUOTES, 'UTF-8' ) ), null, null, null );
		}
		$detail = $page > 0 ? __( 'The privacy policy page is not published.', 'ohmylms' ) : __( 'No privacy policy page is set, so sign-up and checkout forms cannot link to it.', 'ohmylms' );
		return self::check( 'privacy_policy', 'warning', __( 'Privacy policy page linked', 'ohmylms' ), $detail, __( 'Choose the page', 'ohmylms' ), null, admin_url( 'options-privacy.php' ) );
	}

	/**
	 * Build a check.
	 *
	 * @param string      $id     Check id.
	 * @param string      $status ok, warning or error.
	 * @param string      $label  Name of the check.
	 * @param string      $detail Sentence with the finding.
	 * @param string|null $action Action label; null for none.
	 * @param string|null $route  App route of the fix.
	 * @param string|null $href   wp-admin URL of the fix.
	 * @return array
	 */
	private static function check( $id, $status, $label, $detail, $action, $route, $href ) {
		return array(
			'id'     => $id,
			'status' => $status,
			'label'  => $label,
			'detail' => $detail,
			'action' => null === $action ? null : array(
				'label' => $action,
				'route' => $route,
				'href'  => null === $href ? null : esc_url_raw( $href ),
			),
		);
	}
}
