<?php
/**
 * Disposable WordPress integration checks for learner sign-up and profile details.
 *
 * Covers province, school name, grade and phone number at registration and in Settings.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Schools\Controller;
use OhMyLMS\Student\LearnerProfile;
use OhMyLMS\Student\ParentActions;
use OhMyLMS\Student\SettingsSave;

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );

$lp_credentials_path = getenv( 'OHMYLMS_TEST_CREDENTIALS' );
if ( ! $lp_credentials_path || ! is_file( $lp_credentials_path ) ) {
	throw new RuntimeException( 'Disposable test credentials required.' );
}
$lp_credentials = json_decode( file_get_contents( $lp_credentials_path ), true );
require $lp_credentials['site'] . '/wp-load.php';
if ( ! defined( 'OHMYLMS_TEST_SITE' ) || ! OHMYLMS_TEST_SITE || 'ohmylms_source_test' !== DB_NAME ) {
	throw new RuntimeException( 'Disposable database required.' );
}

$lp_checks = 0;
add_filter(
	'wp_die_handler',
	static function () {
		return static function () {
			throw new RuntimeException( 'lp_die' );
		};
	}
);
$lp_users   = array();
$lp_suffix  = wp_generate_password( 8, false );
$lp_limit   = 'ohmylms_school_signup_' . hash_hmac( 'sha256', isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'local', wp_salt( 'nonce' ) );
$lp_limited = get_transient( $lp_limit );

/**
 * Fail immediately when an invariant is broken.
 *
 * @param bool   $condition Verified condition.
 * @param string $message   Failure description.
 * @throws RuntimeException When a check fails.
 */
function ohmylms_lp_check( $condition, $message ) {
	global $lp_checks;
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
	++$lp_checks;
}

/**
 * Submit the hub sign-up form as a signed-out visitor and return the redirect target.
 *
 * @param array $fields Posted fields.
 * @return string Redirect URL.
 * @throws RuntimeException When the handler fails in an unexpected way.
 */
function ohmylms_lp_signup( array $fields ) {
	global $lp_limit;
	delete_transient( $lp_limit );
	wp_set_current_user( 0 );
	$destination               = '';
	$capture                   = static function ( $location ) use ( &$destination ) {
		$destination = $location;
		throw new RuntimeException( 'lp_redirect_captured' );
	};
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_POST                     = array_merge(
		array(
			'action'   => ParentActions::ACTION,
			'_wpnonce' => wp_create_nonce( ParentActions::ACTION ),
			'role'     => 'student',
		),
		$fields
	);
	$_REQUEST                  = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Test harness copies its own fixture.
	add_filter( 'wp_redirect', $capture );
	try {
		ParentActions::handle();
	} catch ( RuntimeException $error ) {
		if ( 'lp_redirect_captured' !== $error->getMessage() ) {
			throw $error;
		}
	} finally {
		remove_filter( 'wp_redirect', $capture );
		$_POST    = array();
		$_REQUEST = array();
	}
	return $destination;
}

/**
 * Render a hub template to a string.
 *
 * @param string $template Template name below templates/student-hub.
 * @param array  $vars     Variables exposed to the template.
 * @return string
 */
function ohmylms_lp_render( $template, array $vars = array() ) {
	// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Test harness only.
	extract( $vars );
	ob_start();
	include OHMYLMS_DIR . '/templates/student-hub/' . $template . '.php';
	return ob_get_clean();
}

try {
	// Options: all of Mongolia, the grades, and the expected values.
	$lp_provinces = LearnerProfile::provinces();
	ohmylms_lp_check( 23 === count( $lp_provinces ), 'Expected Ulaanbaatar, 21 aimags and an outside-Mongolia option.' );
	ohmylms_lp_check( isset( $lp_provinces['ulaanbaatar'], $lp_provinces['khuvsgul'], $lp_provinces[ LearnerProfile::OUTSIDE ] ), 'Province list is missing expected entries.' );
	$lp_grades = LearnerProfile::grades();
	ohmylms_lp_check( isset( $lp_grades['1'], $lp_grades['12'], $lp_grades[ LearnerProfile::GRADE_OTHER ] ) && 13 === count( $lp_grades ), 'Grade list is not grades 1-12 plus other.' );

	// Phone numbers: separators removed, international prefix kept, junk rejected.
	ohmylms_lp_check( '+97699112233' === LearnerProfile::normalize_phone( '+976 9911-2233' ), 'International phone not normalised.' );
	ohmylms_lp_check( '99112233' === LearnerProfile::normalize_phone( '(99) 11 22 33' ), 'Local phone not normalised.' );
	foreach ( array( '12345', 'call me', '+', '99112233x', '1234567890123456', '+976-abc' ) as $lp_bad ) {
		ohmylms_lp_check( '' === LearnerProfile::normalize_phone( $lp_bad ), "Phone '$lp_bad' was accepted." );
	}

	// Validation: every field is optional; only keys that were sent are checked.
	ohmylms_lp_check( array() === LearnerProfile::validate( array() )['values'] && array() === LearnerProfile::validate( array() )['errors'], 'Empty validation invented values or errors.' );
	$lp_blank = LearnerProfile::validate(
		array(
			'phone'       => '',
			'province'    => '',
			'school_name' => '',
			'grade'       => '',
		)
	);
	ohmylms_lp_check( ! $lp_blank['errors'], 'Blank optional fields were rejected.' );
	$lp_bad_values = LearnerProfile::validate(
		array(
			'province' => 'atlantis',
			'grade'    => '99',
			'phone'    => 'abc',
		)
	);
	ohmylms_lp_check( 3 === count( $lp_bad_values['errors'] ), 'Invalid province, grade and phone should each be rejected.' );
	$lp_clean = LearnerProfile::validate(
		array(
			'school_name' => '  <b>School</b> 5 <script>alert(1)</script>' . str_repeat( 'x', 300 ),
			'phone'       => array( 'not', 'scalar' ),
		)
	);
	ohmylms_lp_check( ! str_contains( $lp_clean['values']['school_name'], '<' ) && mb_strlen( $lp_clean['values']['school_name'] ) <= LearnerProfile::MAX_SCHOOL, 'School name was not sanitised or bounded.' );
	ohmylms_lp_check( ! isset( $lp_clean['values']['phone'] ), 'A non-scalar phone value was processed.' );

	// Registration is unchanged: the learner form stays short and still works without any of the profile fields.
	wp_set_current_user( 0 );
	$lp_form = ohmylms_lp_render( 'signin', array( 'signin_role' => '' ) );
	foreach ( array( 'first_name', 'last_name', 'email', 'password' ) as $lp_field ) {
		ohmylms_lp_check( str_contains( $lp_form, 'name="' . $lp_field . '"' ), "Learner sign-up lost the $lp_field field." );
	}
	foreach ( array( 'phone', 'province', 'school_name', 'grade' ) as $lp_field ) {
		ohmylms_lp_check( ! str_contains( $lp_form, 'name="' . $lp_field . '"' ), "Learner sign-up now asks for $lp_field." );
	}
	$lp_email    = "lp-learner-$lp_suffix@example.invalid";
	$lp_redirect = ohmylms_lp_signup(
		array(
			'first_name' => 'Bat',
			'last_name'  => 'Erdene',
			'email'      => $lp_email,
			'password'   => 'Learner-profile-test-password-42',
			// Extra values a stale or tampered form might send are ignored at registration.
			'province'   => 'khuvsgul',
			'grade'      => '9',
		)
	);
	ohmylms_lp_check( str_contains( $lp_redirect, 'signup=created' ), 'Unchanged learner sign-up did not succeed: ' . $lp_redirect );
	$lp_user = get_user_by( 'email', $lp_email );
	ohmylms_lp_check( (bool) $lp_user, 'Learner account was not created.' );
	$lp_users[] = $lp_user->ID;
	ohmylms_lp_check( 'Bat' === $lp_user->first_name && 'Erdene' === $lp_user->last_name, 'Names were not saved at registration.' );
	ohmylms_lp_check( array( '', '', '' ) === array_values( LearnerProfile::get( $lp_user->ID ) ), 'Registration stored profile details.' );

	// Registration is not stricter: a missing last name still works for a learner.
	$lp_redirect = ohmylms_lp_signup(
		array(
			'first_name' => 'Solo',
			'email'      => "lp-solo-$lp_suffix@example.invalid",
			'password'   => 'Learner-profile-test-password-42',
		)
	);
	ohmylms_lp_check( str_contains( $lp_redirect, 'signup=created' ), 'Registration with only a first name no longer works.' );
	$lp_solo    = get_user_by( 'email', "lp-solo-$lp_suffix@example.invalid" );
	$lp_users[] = $lp_solo->ID;

	// Parent and teacher sign-up are unchanged too.
	foreach ( array( 'parent', 'teacher' ) as $lp_role ) {
		$lp_role_email = "lp-$lp_role-$lp_suffix@example.invalid";
		$lp_redirect   = ohmylms_lp_signup(
			array(
				'role'       => $lp_role,
				'first_name' => 'Pat',
				'email'      => $lp_role_email,
				'password'   => 'Learner-profile-test-password-42',
			)
		);
		ohmylms_lp_check( str_contains( $lp_redirect, 'signup=created' ), "Existing $lp_role sign-up no longer works: $lp_redirect" );
		$lp_role_user = get_user_by( 'email', $lp_role_email );
		ohmylms_lp_check( (bool) $lp_role_user, "The $lp_role account was not created." );
		$lp_users[] = $lp_role_user->ID;
	}

	// The REST/portal registration ignores the profile fields as before.
	delete_transient( $lp_limit );
	$lp_rest = Controller::signup(
		array(
			'role'     => 'student',
			'email'    => "lp-rest-$lp_suffix@example.invalid",
			'password' => 'Learner-profile-test-password-42',
			'grade'    => '77',
		)
	);
	ohmylms_lp_check( ! empty( $lp_rest['success'] ), 'REST sign-up behaviour changed.' );
	$lp_users[] = get_user_by( 'email', "lp-rest-$lp_suffix@example.invalid" )->ID;

	// The new learner opens Settings for the first time with nothing filled in: a normal profile.
	wp_set_current_user( $lp_user->ID );
	$lp_first = ohmylms_lp_render( 'settings', array( 'view' => 'account' ) );
	ohmylms_lp_check( str_contains( $lp_first, 'name="school_name"' ) && ! str_contains( $lp_first, ' selected' ) && ! str_contains( $lp_first, 'name="school_name" type="text" required' ), 'A fresh learner does not get an empty, optional profile form.' );

	// Profile details are added later through Settings.
	$lp_later = LearnerProfile::validate(
		array(
			'province'    => 'khuvsgul',
			'school_name' => 'School No. 5',
			'grade'       => '9',
			'phone'       => '+976 9911 2233',
		)
	);
	LearnerProfile::save( $lp_user->ID, $lp_later['values'] );
	ohmylms_lp_check( '+97699112233' === ( new OhMyLMS\Data\Student( $lp_user->ID ) )->get_phone(), 'Phone was not saved to the student phone field.' );

	// Settings: the stored values are prefilled and editable.
	wp_set_current_user( $lp_user->ID );
	$lp_settings = ohmylms_lp_render( 'settings', array( 'view' => 'account' ) );
	ohmylms_lp_check( str_contains( $lp_settings, 'value="Bat"' ) && str_contains( $lp_settings, 'value="Erdene"' ), 'Settings does not show the names.' );
	ohmylms_lp_check( str_contains( $lp_settings, 'value="+97699112233"' ), 'Settings does not show the phone number.' );
	ohmylms_lp_check( str_contains( $lp_settings, 'value="khuvsgul" selected' ) && str_contains( $lp_settings, 'value="9" selected' ) && str_contains( $lp_settings, 'value="School No. 5"' ), 'Settings does not prefill province, grade and school.' );
	ohmylms_lp_check( str_contains( $lp_settings, $lp_email ), 'Settings does not show the email address.' );
	ohmylms_lp_check( 1 === substr_count( $lp_settings, 'name="phone"' ), 'The phone number is shown more than once in Settings.' );
	$lp_teacher_settings = ohmylms_lp_render(
		'settings',
		array(
			'view'         => 'account',
			'teacher_mode' => true,
		)
	);
	ohmylms_lp_check( ! str_contains( $lp_teacher_settings, 'name="school_name"' ) && str_contains( $lp_teacher_settings, 'name="phone"' ), 'Teacher Settings shows learner school fields or lost the phone number.' );

	$lp_result = SettingsSave::apply(
		$lp_user->ID,
		'account',
		array(
			'first_name'   => 'Batbayar',
			'last_name'    => 'Erdene',
			'display_name' => 'Batbayar E.',
			'phone'        => '8811 4455',
			'province'     => 'ulaanbaatar',
			'school_name'  => 'School No. 7 <i>x</i>',
			'grade'        => '10',
		)
	);
	ohmylms_lp_check( $lp_result['saved'], 'Settings did not save: ' . implode( ' ', $lp_result['errors'] ) );
	$lp_saved = LearnerProfile::get( $lp_user->ID );
	ohmylms_lp_check( 'ulaanbaatar' === $lp_saved['province'] && 'School No. 7 x' === $lp_saved['school_name'] && '10' === $lp_saved['grade'], 'Edited details were not stored (or school name not sanitised).' );
	ohmylms_lp_check( '88114455' === ( new OhMyLMS\Data\Student( $lp_user->ID ) )->get_phone() && 'Batbayar' === get_userdata( $lp_user->ID )->first_name, 'Edited phone or name not stored.' );

	// Invalid edits are rejected as a whole and change nothing.
	foreach ( array(
		'province' => 'atlantis',
		'grade'    => '0',
		'phone'    => 'abc',
	) as $lp_key => $lp_value ) {
		$lp_result = SettingsSave::apply(
			$lp_user->ID,
			'account',
			array(
				'first_name'   => 'Changed',
				'display_name' => 'Changed',
				$lp_key        => $lp_value,
			)
		);
		ohmylms_lp_check( ! $lp_result['saved'] && $lp_result['errors'], "Invalid $lp_key was accepted in Settings." );
	}
	ohmylms_lp_check( 'Batbayar' === get_userdata( $lp_user->ID )->first_name && '10' === LearnerProfile::get( $lp_user->ID )['grade'], 'A rejected Settings save changed stored data.' );

	// A form that does not send the school fields (older theme override, teacher view) leaves them alone.
	$lp_result = SettingsSave::apply(
		$lp_user->ID,
		'account',
		array(
			'first_name'   => 'Batbayar',
			'display_name' => 'Batbayar E.',
		)
	);
	ohmylms_lp_check( $lp_result['saved'] && '10' === LearnerProfile::get( $lp_user->ID )['grade'] && '88114455' === ( new OhMyLMS\Data\Student( $lp_user->ID ) )->get_phone(), 'Saving without the school fields cleared them.' );

	// The About card no longer owns the phone number: saving it keeps the phone, but a phone it receives is validated.
	$lp_result = SettingsSave::apply( $lp_user->ID, 'about', array( 'bio' => 'Hello' ) );
	ohmylms_lp_check( $lp_result['saved'] && '88114455' === ( new OhMyLMS\Data\Student( $lp_user->ID ) )->get_phone(), 'Saving About cleared the phone number.' );
	$lp_result = SettingsSave::apply( $lp_user->ID, 'about', array( 'phone' => 'zzz' ) );
	ohmylms_lp_check( ! $lp_result['saved'], 'About accepted an invalid phone number.' );

	// A learner can clear the optional school details.
	$lp_result = SettingsSave::apply(
		$lp_user->ID,
		'account',
		array(
			'display_name' => 'Batbayar E.',
			'province'     => '',
			'school_name'  => '',
			'grade'        => '',
			'phone'        => '',
		)
	);
	ohmylms_lp_check( $lp_result['saved'] && array( '', '', '' ) === array_values( LearnerProfile::get( $lp_user->ID ) ) && '' === ( new OhMyLMS\Data\Student( $lp_user->ID ) )->get_phone(), 'Clearing the details did not work.' );

	// Existing users with no details at all see an empty, working form.
	$lp_legacy_id = wp_insert_user(
		array(
			'user_login' => "lp-legacy-$lp_suffix",
			'user_email' => "lp-legacy-$lp_suffix@example.invalid",
			'user_pass'  => wp_generate_password( 24 ),
			'role'       => 'subscriber',
		)
	);
	$lp_users[]   = $lp_legacy_id;
	wp_set_current_user( $lp_legacy_id );
	$lp_legacy_settings = ohmylms_lp_render( 'settings', array( 'view' => 'account' ) );
	ohmylms_lp_check( str_contains( $lp_legacy_settings, 'name="school_name"' ) && ! str_contains( $lp_legacy_settings, ' selected' ), 'A user without details does not get an empty form.' );
	$lp_result = SettingsSave::apply( $lp_legacy_id, 'account', array( 'display_name' => 'Legacy user' ) );
	ohmylms_lp_check( $lp_result['saved'], 'A user without details cannot save Settings.' );

	// Settings submissions still require a nonce and a signed-in user.
	wp_set_current_user( 0 );
	$_POST      = array(
		'card'     => 'account',
		'_wpnonce' => wp_create_nonce( SettingsSave::ACTION . '_account' ),
		'province' => 'tuv',
	);
	$lp_blocked = false;
	try {
		SettingsSave::handle();
	} catch ( RuntimeException $error ) {
		$lp_blocked = 'lp_die' === $error->getMessage();
	}
	$_POST = array();
	ohmylms_lp_check( $lp_blocked, 'A signed-out Settings submission was not refused.' );

	echo esc_html( $lp_checks . ' learner profile integration checks passed.' ) . "\n";
} finally {
	if ( false === $lp_limited ) {
		delete_transient( $lp_limit );
	} else {
		set_transient( $lp_limit, $lp_limited, 15 * MINUTE_IN_SECONDS );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $lp_users as $lp_user_id ) {
		wp_delete_user( $lp_user_id );
	}
	delete_transient( 'ohmylms_parent_signup_' . ParentActions::client_key() );
}
