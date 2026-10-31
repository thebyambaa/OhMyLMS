<?php
/**
 * Emails in the recipient's language ({@see \OhMyLMS\I18n\RecipientLocale}): which locale a user ID, user
 * or address gets, the switch and its restore (also when composing throws, and nested), the `ohmylms`
 * text domain following the switch, grouping of several recipients, and two real emails captured with
 * `pre_wp_mail`: an at-risk nudge (learner and guardian copies) sent during an English request and an
 * "assignment submitted" notice (two instructors) sent during a learner's Mongolian request.
 *
 * Then saved settings: a Mongolian administrator saves the nudge through REST changing only its subject;
 * only that subject is stored, an English learner gets it with the English defaults, and a reset (and
 * the one-off clean-up) restores the defaults. Finally the Zoom, cohort and Google Meet reminders are
 * written in each recipient's language, with the English texts unchanged. A `gettext_ohmylms` marker
 * shows the locale of every string, so none of this depends on translation coverage.
 *
 * Creates five temporary users and three draft posts and deletes them at the end. Email settings are
 * replaced through `pre_option_*` filters; the one email option written is restored at the end.
 *
 * Usage (set OHMYLMS_RECIPIENT_LOCALE_TEST=1):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/recipient-locale-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.Security.NonceVerification

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_RECIPIENT_LOCALE_TEST' ) ) {
	exit( "Set OHMYLMS_RECIPIENT_LOCALE_TEST=1 to run the recipient language tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
unset( $_SERVER['HTTP_ACCEPT_LANGUAGE'], $_SERVER['HTTP_REFERER'] );
require dirname( __DIR__, 5 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

use OhMyLMS\Emails\CreatorsEmail\AssignmentSubmitted;
use OhMyLMS\Emails\EmailManager;
use OhMyLMS\Emails\Emails;
use OhMyLMS\Emails\StudentsEmail\AtRiskNudge;
use OhMyLMS\I18n\InterfaceLanguage;
use OhMyLMS\I18n\RecipientLocale;

$checks   = 0;
$warnings = array();
set_error_handler(
	static function ( $severity, $message, $file, $line ) use ( &$warnings ) {
		if ( false !== strpos( wp_normalize_path( $file ), wp_normalize_path( OHMYLMS_DIR ) ) ) {
			$warnings[] = "$message in $file:$line";
		}
		return false;
	}
);

/**
 * Record one check.
 *
 * @param bool   $condition Passes.
 * @param string $message   Failure message.
 * @throws RuntimeException When the check fails.
 */
function ok( $condition, $message ) {
	global $checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	++$checks;
}

/**
 * Start a new front-end request as WordPress does: the user and cookie decide the locale, then the
 * locale switcher is built (it remembers that locale to restore to) and translations load in it.
 *
 * @param int    $user_id Signed-in user, 0 for a visitor.
 * @param string $cookie  `ohmylms_locale` cookie, '' for none.
 * @return string The request locale.
 */
function begin_request( $user_id, $cookie ) {
	wp_set_current_user( $user_id );
	unset( $_COOKIE[ InterfaceLanguage::COOKIE ], $_SERVER['HTTP_ACCEPT_LANGUAGE'], $_SERVER['HTTP_REFERER'] );
	if ( '' !== $cookie ) {
		$_COOKIE[ InterfaceLanguage::COOKIE ] = $cookie;
	}
	$old = $GLOBALS['wp_locale_switcher'];
	remove_filter( 'locale', array( $old, 'filter_locale' ) );
	remove_filter( 'determine_locale', array( $old, 'filter_locale' ) );
	$GLOBALS['wp_locale_switcher'] = new WP_Locale_Switcher();
	$GLOBALS['wp_locale_switcher']->init();
	$locale = determine_locale();
	load_default_textdomain( $locale );
	unload_textdomain( 'ohmylms' );
	load_plugin_textdomain( 'ohmylms', false, dirname( plugin_basename( OHMYLMS_FILE ) ) . '/languages/' );
	$GLOBALS['wp_locale'] = new WP_Locale();
	return $locale;
}

/**
 * Create a temporary user.
 *
 * @param string $role   Role.
 * @param string $locale Profile language, '' for the site default.
 * @return WP_User
 */
function temp_user( $role, $locale ) {
	$id = wp_insert_user(
		array(
			'user_login'   => 'oml_rl_' . wp_generate_password( 8, false ),
			'user_pass'    => wp_generate_password( 20 ),
			'user_email'   => 'oml-rl-' . strtolower( wp_generate_password( 8, false ) ) . '@example.test',
			'display_name' => 'RL ' . $role . ' ' . ( '' !== $locale ? $locale : 'site' ),
			'role'         => $role,
		)
	);
	ok( is_int( $id ) && $id > 0, "temporary $role created" );
	if ( '' !== $locale ) {
		update_user_meta( $id, InterfaceLanguage::USER_META, $locale );
	}
	return get_userdata( $id );
}

$users    = array();
$posts    = array();
$mails    = array();
$filters  = array();
$cookies  = $_COOKIE;
$original = '';
$missing  = new stdClass();
$options  = array();
try {
	$site = get_locale();
	ok( in_array( 'mn', get_available_languages(), true ), 'the mn core language pack is installed (WordPress cannot switch to mn without it)' );
	ok( 'en_US' === $site, 'the site language is English (site: ' . $site . ')' );

	$learner    = temp_user( 'subscriber', 'mn' );
	$learner2   = temp_user( 'subscriber', 'mn' );
	$instructor = temp_user( 'administrator', 'en_US' );
	$plain      = temp_user( 'subscriber', '' );
	$users      = array( $learner->ID, $learner2->ID, $instructor->ID, $plain->ID );
	$unknown    = 'nobody-' . strtolower( wp_generate_password( 8, false ) ) . '@example.test';

	// Which locale an email is written in.
	ok( 'mn' === RecipientLocale::locale_of( $learner->ID ), 'a user ID gets the profile language' );
	ok( 'mn' === RecipientLocale::locale_of( $learner ), 'a WP_User gets the profile language' );
	ok( 'mn' === RecipientLocale::locale_of( strtoupper( $learner->user_email ) ), 'an address is looked up as a user' );
	ok( 'mn' === RecipientLocale::locale_of( 'Learner <' . $learner->user_email . '>' ), 'a "Name <address>" recipient is looked up too' );
	ok( 'en_US' === RecipientLocale::locale_of( $instructor->user_email ), 'English profile language' );
	ok( $site === RecipientLocale::locale_of( $plain ), 'without a profile language: the site locale' );
	ok( $site === RecipientLocale::locale_of( $unknown ), 'an address of no user: the site locale' );
	update_user_meta( $plain->ID, InterfaceLanguage::USER_META, 'de_DE' );
	ok( $site === RecipientLocale::locale_of( $plain ), 'a profile language WordPress has no pack for: the site locale' );
	delete_user_meta( $plain->ID, InterfaceLanguage::USER_META );

	// An English request (an administrator with an English profile) sends to a Mongolian learner.
	$original = begin_request( $instructor->ID, '' );
	ok( 'en_US' === $original && 'General' === __( 'General', 'ohmylms' ), 'the request is English' );
	$inside = RecipientLocale::run(
		$learner->ID,
		static function ( $locale ) {
			return array( $locale, determine_locale(), __( 'General', 'ohmylms' ), date_i18n( 'F', 0 ) );
		}
	);
	ok( array( 'mn', 'mn', 'Ерөнхий' ) === array_slice( $inside, 0, 3 ), 'composing for the learner: mn, with the ohmylms domain reloaded in Mongolian: ' . wp_json_encode( $inside ) );
	ok( 'January' !== $inside[3], 'dates follow the recipient too: ' . $inside[3] );
	ok( 'en_US' === determine_locale() && 'General' === __( 'General', 'ohmylms' ) && 'January' === date_i18n( 'F', 0 ), 'afterwards the request is English again' );
	ok( ! is_locale_switched(), 'nothing is left switched' );

	// Nesting, same-locale calls, unknown addresses and exceptions.
	$trail = array();
	RecipientLocale::run(
		$learner,
		static function () use ( &$trail, $instructor, $learner2, $unknown ) {
			$trail[] = determine_locale() . ':' . __( 'General', 'ohmylms' );
			RecipientLocale::run(
				$instructor,
				static function () use ( &$trail, $learner2 ) {
					$trail[] = determine_locale() . ':' . __( 'General', 'ohmylms' );
					RecipientLocale::run(
						$learner2,
						static function () use ( &$trail ) {
							$trail[] = determine_locale();
						}
					);
					$trail[] = determine_locale();
				}
			);
			$trail[] = determine_locale() . ':' . __( 'General', 'ohmylms' );
			RecipientLocale::run(
				$learner2,
				static function () use ( &$trail ) {
					$trail[] = determine_locale();
				}
			);
			$trail[] = determine_locale();
			RecipientLocale::run(
				$unknown,
				static function () use ( &$trail ) {
					$trail[] = determine_locale();
				}
			);
			$trail[] = determine_locale();
		}
	);
	ok(
		array( 'mn:Ерөнхий', 'en_US:General', 'mn', 'en_US', 'mn:Ерөнхий', 'mn', 'mn', $site, 'mn' ) === $trail,
		'nested calls switch and restore in order; an address of no user gets the site locale inside a Mongolian switch: ' . wp_json_encode( $trail )
	);
	ok( 'en_US' === determine_locale() && ! is_locale_switched(), 'after nesting the request locale is back' );
	try {
		RecipientLocale::run(
			$learner,
			static function () {
				throw new LogicException( 'composing failed' );
			}
		);
		ok( false, 'the exception reaches the caller' );
	} catch ( LogicException $e ) {
		ok( 'composing failed' === $e->getMessage(), 'the exception reaches the caller' );
	}
	ok( 'en_US' === determine_locale() && 'General' === __( 'General', 'ohmylms' ) && ! is_locale_switched(), 'the locale is restored when composing throws' );
	ok(
		'done' === RecipientLocale::run(
			$unknown,
			static function () {
				return 'done';
			}
		),
		'run() returns what the callable returns'
	);

	// Several recipients: one call per language.
	ok(
		array(
			'en_US' => array( $instructor->user_email, $unknown ),
			'mn'    => array( $learner->user_email, $learner2->ID ),
		) === array_map(
			static function ( $group ) {
				return $group['recipients'];
			},
			RecipientLocale::group( array( $instructor->user_email, $learner->user_email, $unknown, $learner2->ID ) )
		),
		'recipients are grouped by locale in order'
	);
	ok( array( 'en_US' => $instructor->ID ) === array_map( 'intval', wp_list_pluck( RecipientLocale::group( $instructor->user_email . ', ' ), 'user_id' ) ), 'a comma separated string is split; a one-user group keeps the user' );
	$seen = array();
	RecipientLocale::each(
		$instructor->user_email . ',' . $unknown,
		static function ( $to, $locale ) use ( &$seen ) {
			$seen[] = array( $to, $locale, determine_locale() );
		}
	);
	ok( array( array( $instructor->user_email . ',' . $unknown, 'en_US', 'en_US' ) ) === $seen, 'one language: the recipients are passed through unchanged' );
	$seen = array();
	RecipientLocale::each(
		array( $learner->user_email, $instructor->user_email, $learner2->user_email ),
		static function ( $to, $locale ) use ( &$seen ) {
			$seen[] = array( $to, $locale, determine_locale() );
		}
	);
	ok(
		array(
			array( array( $learner->user_email, $learner2->user_email ), 'mn', 'mn' ),
			array( array( $instructor->user_email ), 'en_US', 'en_US' ),
		) === $seen,
		'two languages: composed once per language with its recipients'
	);

	// Real emails. A marker shows the locale every ohmylms string was translated in.
	$marker = static function ( $translation ) {
		return '⟦' . determine_locale() . '⟧' . $translation;
	};
	$filters['gettext_ohmylms'] = $marker;
	$capture                    = static function ( $short, $atts ) use ( &$mails ) {
		$atts['locale'] = determine_locale();
		$mails[]        = $atts;
		return true;
	};
	$filters['pre_wp_mail']     = $capture;
	add_filter( 'pre_wp_mail', $capture, 10, 2 );
	add_filter( 'gettext_ohmylms', $marker );
	$no_saved = static function () {
		return array();
	};
	$filters['pre_option_create_lms_email_student_at_risk_nudge'] = $no_saved;
	add_filter( 'pre_option_create_lms_email_student_at_risk_nudge', $no_saved );

	// Learner-facing, during the English request: the learner in Mongolian, each guardian copy in theirs.
	$reasons_in = array();
	$result     = ( new AtRiskNudge() )->send(
		$learner,
		'Ms Rivera',
		static function () use ( &$reasons_in ) {
			$reasons_in[] = determine_locale();
			return 'Inactive ' . determine_locale();
		},
		array( $instructor->user_email, $unknown, $learner2->user_email )
	);
	ok( $result['sent'] && 3 === $result['guardians'], 'the nudge and three guardian copies were sent: ' . wp_json_encode( $result ) );
	ok( 4 === count( $mails ), 'four emails captured' );
	$by_to = array();
	foreach ( $mails as $mail ) {
		$by_to[ is_array( $mail['to'] ) ? implode( ',', $mail['to'] ) : $mail['to'] ] = $mail;
	}
	$to_learner = $by_to[ $learner->user_email ];
	ok( 'mn' === $to_learner['locale'] && 0 === strpos( $to_learner['subject'], '⟦mn⟧' ), 'the learner\'s subject is composed in Mongolian: ' . $to_learner['subject'] );
	ok( false !== strpos( $to_learner['message'], 'Үргэлжлүүлэн суралцах' ), 'the learner\'s button is the Mongolian translation' );
	ok( false !== strpos( $to_learner['message'], 'Inactive mn' ) && false === strpos( $to_learner['message'], '⟦en_US⟧' ), 'the learner\'s body has no English string, and the reasons are written in mn' );
	foreach ( array( $instructor->user_email, $unknown ) as $address ) {
		ok( 'en_US' === $by_to[ $address ]['locale'] && 0 === strpos( $by_to[ $address ]['subject'], '⟦en_US⟧' ) && false !== strpos( $by_to[ $address ]['message'], 'Continue learning' ) && false === strpos( $by_to[ $address ]['message'], '⟦mn⟧' ), "the copy to $address is English" );
	}
	ok( 'mn' === $by_to[ $learner2->user_email ]['locale'] && 0 === strpos( $by_to[ $learner2->user_email ]['subject'], '⟦mn⟧' ), 'the copy to a Mongolian guardian is Mongolian' );
	ok( array( 'mn', 'en_US', 'mn' ) === $reasons_in, 'the reasons are written for the learner, then once per guardian language: ' . wp_json_encode( $reasons_in ) );
	ok( $to_learner['headers'] === $by_to[ $unknown ]['headers'], 'headers do not change with the language' );
	ok( 'en_US' === determine_locale() && ! is_locale_switched(), 'the English request locale is restored after sending' );

	// A saved template is used as typed; only the defaults it leaves out are translated.
	remove_filter( 'pre_option_create_lms_email_student_at_risk_nudge', $no_saved );
	$saved = static function () {
		return array( 'subject' => 'Saved subject for {student_name}' );
	};
	$filters['pre_option_create_lms_email_student_at_risk_nudge'] = $saved;
	add_filter( 'pre_option_create_lms_email_student_at_risk_nudge', $saved );
	$mails = array();
	( new AtRiskNudge() )->send( $learner, 'Ms Rivera', 'Inactive', array() );
	ok( 1 === count( $mails ) && 'Saved subject for ' . $learner->display_name === $mails[0]['subject'], 'the saved subject is sent as typed: ' . $mails[0]['subject'] );
	ok( false !== strpos( $mails[0]['message'], 'Үргэлжлүүлэн суралцах' ), 'the default button text is still translated' );

	// Instructor-facing, during the learner's Mongolian request: each instructor in their language.
	remove_filter( 'gettext_ohmylms', $marker );
	ok( 'mn' === begin_request( $learner->ID, '' ), 'the learner\'s request is Mongolian' );
	ok( 'Ерөнхий' === __( 'General', 'ohmylms' ), 'and its strings are Mongolian' );
	add_filter( 'gettext_ohmylms', $marker );
	$course = wp_insert_post(
		array(
			'post_type'   => 'ohmylms-course',
			'post_title'  => 'RL course',
			'post_status' => 'draft',
			'post_author' => $instructor->ID,
		),
		true
	);
	ok( is_int( $course ) && $course > 0, 'temporary course created' );
	$posts[]    = $course;
	$assignment = wp_insert_post(
		array(
			'post_title'  => 'RL essay',
			'post_status' => 'draft',
		),
		true
	);
	ok( is_int( $assignment ) && $assignment > 0, 'temporary assignment post created' );
	$posts[]   = $assignment;
	$instant   = static function () use ( $instructor, $learner2 ) {
		return array(
			'enable'          => true,
			'delivery_type'   => 'instant',
			'recipient_email' => array( $instructor->user_email, $learner2->user_email ),
		);
	};
	$filters['pre_option_create_lms_email_instructor_assignment_submitted'] = $instant;
	add_filter( 'pre_option_create_lms_email_instructor_assignment_submitted', $instant );
	$mails = array();
	( new ReflectionClass( AssignmentSubmitted::class ) )->newInstanceWithoutConstructor()->trigger( $assignment, $course, $learner->ID );
	ok( 2 === count( $mails ), 'one email per instructor language: ' . count( $mails ) );
	ok( array( $instructor->user_email ) === $mails[0]['to'] && 'en_US' === $mails[0]['locale'], 'the English instructor gets their own email' );
	ok( 0 === strpos( $mails[0]['subject'], '⟦en_US⟧New submission: RL essay by ' . $learner->display_name ), 'its subject is the English default with the tags filled in: ' . $mails[0]['subject'] );
	ok( false === strpos( $mails[0]['message'], '⟦mn⟧' ) && false !== strpos( $mails[0]['message'], '⟦en_US⟧' ), 'its body has no Mongolian string' );
	ok( array( $learner2->user_email ) === $mails[1]['to'] && 'mn' === $mails[1]['locale'] && 0 === strpos( $mails[1]['subject'], '⟦mn⟧' ) && false === strpos( $mails[1]['message'], '⟦en_US⟧' ), 'the Mongolian recipient gets a Mongolian email' );
	ok( $mails[0]['headers'] === $mails[1]['headers'], 'both have the same headers' );
	remove_filter( 'gettext_ohmylms', $marker );
	ok( 'mn' === determine_locale() && 'Ерөнхий' === __( 'General', 'ohmylms' ) && ! is_locale_switched(), 'the Mongolian request locale and strings are restored afterwards' );

	// One recipient language keeps the original `$to` and a single email.
	$single = static function () use ( $instructor ) {
		return array(
			'enable'          => true,
			'delivery_type'   => 'instant',
			'recipient_email' => array( $instructor->user_email, 'Ops <' . strtoupper( $instructor->user_email ) . '>' ),
		);
	};
	remove_filter( 'pre_option_create_lms_email_instructor_assignment_submitted', $instant );
	$filters['pre_option_create_lms_email_instructor_assignment_submitted'] = $single;
	add_filter( 'pre_option_create_lms_email_instructor_assignment_submitted', $single );
	$mails = array();
	( new ReflectionClass( AssignmentSubmitted::class ) )->newInstanceWithoutConstructor()->trigger( $assignment, $course, $learner->ID );
	ok( 1 === count( $mails ) && array( $instructor->user_email, 'Ops <' . strtoupper( $instructor->user_email ) . '>' ) === $mails[0]['to'] && 'en_US' === $mails[0]['locale'], 'recipients of one language share one email with the original recipient list' );
	ok( 'mn' === determine_locale() && ! is_locale_switched(), 'the request is still Mongolian' );

	// Saved settings keep only what the administrator changed. A Mongolian administrator saves the at-risk
	// nudge from a form that sends every field, having changed only the subject.
	remove_filter( 'pre_option_create_lms_email_student_at_risk_nudge', $saved );
	unset( $filters['pre_option_create_lms_email_student_at_risk_nudge'] );
	$option             = Emails::OPTION_PREFIX . 'student_at_risk_nudge';
	$options[ $option ] = get_option( $option, $missing );
	delete_option( $option );
	$mn_admin = temp_user( 'administrator', 'mn' );
	$english  = temp_user( 'subscriber', 'en_US' );
	$users[]  = $mn_admin->ID;
	$users[]  = $english->ID;
	ok( 'mn' === begin_request( $mn_admin->ID, '' ), 'the administrator\'s request is Mongolian' );
	add_filter( 'gettext_ohmylms', $marker );
	$shown = rest_do_request( new WP_REST_Request( 'GET', '/ohmylms/v1/emails/student_at_risk_nudge' ) )->get_data();
	ok( 0 === strpos( $shown['heading'], '⟦mn⟧' ) && $shown['heading'] === $shown['defaults']['heading'] && false !== strpos( $shown['additional_content'], '⟦mn⟧' ), 'the administrator sees the defaults in Mongolian' );
	$form = array_intersect_key( $shown, array_flip( array( 'subject', 'heading', 'additional_content', 'button_text', 'button_link', 'footer_text' ) ) );
	ok( 6 === count( $form ), 'the form has six text fields' );
	$form['subject'] = 'Custom check-in from {sender_name}';
	$request         = new WP_REST_Request( 'PUT', '/ohmylms/v1/emails/student_at_risk_nudge' );
	$request->set_header( 'Content-Type', 'application/json' );
	$request->set_body( wp_json_encode( $form ) );
	$response = rest_do_request( $request );
	ok( 200 === $response->get_status() && array( 'subject' => 'Custom check-in from {sender_name}' ) === get_option( $option ), 'only the changed subject is stored: ' . wp_json_encode( get_option( $option ) ) );
	ok( true === $response->get_data()['customized']['subject'] && false === $response->get_data()['customized']['heading'], 'the subject is customized, the heading is not' );

	// An English learner nudged during the Mongolian request: the custom subject, the English defaults.
	$mails = array();
	( new AtRiskNudge() )->send( $english, 'Ms Rivera', 'Inactive', array() );
	ok( 1 === count( $mails ) && 'Custom check-in from Ms Rivera' === $mails[0]['subject'], 'the English learner gets the custom subject: ' . ( $mails ? $mails[0]['subject'] : '' ) );
	ok( 'en_US' === $mails[0]['locale'] && false !== strpos( $mails[0]['message'], '⟦en_US⟧' ) && false === strpos( $mails[0]['message'], '⟦mn⟧' ), 'and every default in the body in English' );
	ok( false !== strpos( $mails[0]['message'], 'noticed you could use a little boost' ) && false !== strpos( $mails[0]['message'], 'Continue learning' ), 'the English default body and button' );
	$mails = array();
	( new AtRiskNudge() )->send( $learner, 'Ms Rivera', 'Inactive', array() );
	ok( 1 === count( $mails ) && 'Custom check-in from Ms Rivera' === $mails[0]['subject'] && false !== strpos( $mails[0]['message'], '⟦mn⟧' ) && false === strpos( $mails[0]['message'], '⟦en_US⟧' ), 'a Mongolian learner gets the same subject and Mongolian defaults' );

	// Reset: the subject is translated per recipient again.
	$request = new WP_REST_Request( 'PUT', '/ohmylms/v1/emails/student_at_risk_nudge' );
	$request->set_header( 'Content-Type', 'application/json' );
	$request->set_body( wp_json_encode( array( 'subject' => null ) ) );
	$response = rest_do_request( $request );
	ok( 200 === $response->get_status() && false === get_option( $option, false ) && $response->get_data()['subject'] === $response->get_data()['defaults']['subject'], 'resetting the subject removes the stored settings' );
	$mails = array();
	( new AtRiskNudge() )->send( $english, 'Ms Rivera', 'Inactive', array() );
	ok( 1 === count( $mails ) && '⟦en_US⟧Ms Rivera checked in on your learning' === $mails[0]['subject'], 'after the reset the English learner gets the English default subject: ' . ( $mails ? $mails[0]['subject'] : '' ) );

	// The one-off clean-up forgets saved texts equal to an English or Mongolian default.
	$en_defaults = RecipientLocale::in_locale(
		'en_US',
		static function () {
			return ( new AtRiskNudge() )->default_settings();
		}
	);
	update_option(
		$option,
		array(
			'enable'      => true,
			'heading'     => $shown['defaults']['heading'],
			'subject'     => $en_defaults['subject'],
			'footer_text' => 'Kept footer',
		)
	);
	$forgotten = EmailManager::forget_defaults( 'student_at_risk_nudge', array( 'en_US', 'mn' ) );
	sort( $forgotten );
	ok( array( 'heading', 'subject' ) === $forgotten, 'the Mongolian heading and English subject are forgotten: ' . wp_json_encode( $forgotten ) );
	ok(
		array(
			'enable'      => true,
			'footer_text' => 'Kept footer',
		) === get_option( $option ),
		'the toggle and the customized footer are kept'
	);
	ok( array() === EmailManager::forget_defaults( 'student_at_risk_nudge', array( 'en_US', 'mn' ) ), 'the clean-up is idempotent' );
	delete_option( $option );

	// Reminders of live sessions and cohorts are written in the recipient's language.
	$site_name = get_bloginfo( 'name' );
	$strip     = static function ( $text ) {
		return (string) preg_replace( '/⟦[^⟧]*⟧/u', '', $text );
	};
	$start     = time() + DAY_IN_SECONDS;
	$en_date   = static function ( $format, $timestamp ) {
		return RecipientLocale::in_locale(
			'en_US',
			static function () use ( $format, $timestamp ) {
				return date_i18n( $format, $timestamp );
			}
		);
	};
	$zoom      = new ReflectionMethod( \OhMyLMS\Integrations\Zoom\Includes\SessionReminderScheduler::class, 'send_email' );
	$session   = array(
		'start_time'   => $start,
		'zoom_link'    => 'https://zoom.example/j/1',
		'session_id'   => 0,
		'course_title' => 'RL course',
		'course_link'  => 'https://example.test/course',
	);
	foreach ( array( $english, $learner ) as $recipient ) {
		$mails = array();
		$zoom->invoke( null, $recipient->ID, $course, $session );
		$locale = RecipientLocale::locale_of( $recipient );
		$other  = 'mn' === $locale ? 'en_US' : 'mn';
		ok( 1 === count( $mails ) && $locale === $mails[0]['locale'] && 0 === strpos( $mails[0]['subject'], "⟦{$locale}⟧" ) && false !== strpos( $mails[0]['message'], "⟦{$locale}⟧" ) && false === strpos( $mails[0]['subject'] . $mails[0]['message'], "⟦{$other}⟧" ), "the Zoom reminder is written in $locale" );
	}
	$mails = array();
	$zoom->invoke( null, $english->ID, $course, $session );
	ok( 'Reminder: Zoom Session for "RL course" Starts in 24 Hours!' === $strip( $mails[0]['subject'] ), 'the English Zoom subject is unchanged: ' . $mails[0]['subject'] );
	ok(
		sprintf(
			"Hi %s,\n\nJust a heads-up that your Zoom session for \"%s\" starts in 24 hours!\n\n🗓 Start Time: %s\n🔗 Zoom Link: %s\n📘 Course Page: %s\n\nMake sure to join on time. Looking forward to your participation!\n\n— %s Team",
			$english->display_name,
			'RL course',
			$en_date( 'M j, g:i A', $start ),
			'https://zoom.example/j/1',
			'https://example.test/course',
			$site_name
		) === $strip( $mails[0]['message'] ),
		'the English Zoom body is unchanged, with the real start time: ' . $strip( $mails[0]['message'] )
	);

	$cohort_mail = new ReflectionMethod( \OhMyLMS\Integrations\Cohorts\Includes\CohortReminderScheduler::class, 'send_email' );
	$cohort      = (object) array(
		'id'         => 0,
		'start_date' => gmdate( 'Y-m-d H:i:s', $start ),
	);
	foreach ( array( $english, $learner ) as $recipient ) {
		$mails = array();
		$cohort_mail->invoke( null, $recipient->ID, $course, $cohort );
		$locale = RecipientLocale::locale_of( $recipient );
		$other  = 'mn' === $locale ? 'en_US' : 'mn';
		ok( 1 === count( $mails ) && $locale === $mails[0]['locale'] && 0 === strpos( $mails[0]['subject'], "⟦{$locale}⟧" ) && false === strpos( $mails[0]['subject'] . $mails[0]['message'], "⟦{$other}⟧" ), "the cohort reminder is written in $locale" );
	}
	$mails = array();
	$cohort_mail->invoke( null, $english->ID, $course, $cohort );
	ok( 'Reminder: Your Course "RL course" Starts in 24 Hours!' === $strip( $mails[0]['subject'] ), 'the English cohort subject is unchanged' );
	ok(
		sprintf(
			"Hi %s,\n\nJust a quick reminder that your course \"%s\" starts in 24 hours!\n\n🗓 Start Date: %s\n📍 Course Link: %s\n\nWe're excited to have you on board. Make sure to log in on time and make the most of your learning journey.\n\nSee you in class!\n\n— %s Team",
			$english->display_name,
			'RL course',
			$en_date( 'M j, g:i A', strtotime( $cohort->start_date ) ),
			get_permalink( $course ),
			$site_name
		) === $strip( $mails[0]['message'] ),
		'the English cohort body is unchanged: ' . $strip( $mails[0]['message'] )
	);

	$meet = wp_insert_post(
		array(
			'post_title'  => 'RL live class',
			'post_status' => 'draft',
		),
		true
	);
	ok( is_int( $meet ) && $meet > 0, 'temporary Google Meet session created' );
	$posts[] = $meet;
	update_post_meta( $meet, '_lesson_course_id', $course );
	update_post_meta( $meet, '_googlemeet_link', 'https://meet.example/abc' );
	update_post_meta( $meet, '_session_start_time', $start );
	update_user_meta( $english->ID, '_enrolled_course_' . $course, '1' );
	update_user_meta( $learner->ID, '_enrolled_course_' . $course, '1' );
	$mails = array();
	\OhMyLMS\Integrations\GoogleMeet\Includes\SessionReminderScheduler::send_session_reminder( $meet );
	ok( 2 === count( $mails ), 'one Google Meet reminder per enrolled learner: ' . count( $mails ) );
	foreach ( $mails as $mail ) {
		$to     = is_array( $mail['to'] ) ? implode( ',', $mail['to'] ) : $mail['to'];
		$locale = $to === $english->user_email ? 'en_US' : 'mn';
		$other  = 'mn' === $locale ? 'en_US' : 'mn';
		ok( $locale === $mail['locale'] && 0 === strpos( $mail['subject'], "⟦{$locale}⟧" ) && 0 === strpos( $mail['message'], "⟦{$locale}⟧" ) && false === strpos( $mail['subject'] . $mail['message'], "⟦{$other}⟧" ), "the Google Meet reminder to $to is written in $locale" );
	}
	remove_filter( 'gettext_ohmylms', $marker );

	ok( ! $warnings, implode( "\n", $warnings ) );
} finally {
	foreach ( $filters as $hook => $callback ) {
		remove_filter( $hook, $callback, 10 );
	}
	while ( is_locale_switched() ) {
		restore_previous_locale();
	}
	foreach ( $posts as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	foreach ( $options as $name => $value ) {
		if ( $value === $missing ) {
			delete_option( $name );
		} else {
			update_option( $name, $value );
		}
	}
	foreach ( $users as $user_id ) {
		wp_delete_user( $user_id );
	}
	$_COOKIE = $cookies;
	begin_request( 0, '' );
}

echo "Recipient language checks passed: $checks\n";
