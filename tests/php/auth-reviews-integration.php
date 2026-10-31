<?php
/**
 * Login, sign-up and course reviews on the Interactivity API (batch L5): the forms and the review
 * form render `ohmylms/forms` / `ohmylms/reviews` directives and state, theme overrides with the
 * pre-L5 markup are decorated, `Ajax::login_response()` / `signup_response()` accept the fields the
 * module posts (the form's own fields through `request()`), the same forms work when posted to the
 * page without JavaScript, and a review posted with the radio stars stores its rating.
 *
 * Creates fixture users and one review and deletes them; no other data is changed.
 *
 * Usage (set OHMYLMS_AUTH_REVIEWS_TEST=1):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/auth-reviews-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.Security.NonceVerification

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_AUTH_REVIEWS_TEST' ) ) {
	exit( "Set OHMYLMS_AUTH_REVIEWS_TEST=1 to run the login, sign-up and review tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require dirname( __DIR__, 5 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

use OhMyLMS\Ajax;
use OhMyLMS\Extensions\AuthForms;
use OhMyLMS\Extensions\Chrome;
use OhMyLMS\Extensions\Interactivity;
use OhMyLMS\Extensions\Reviews;
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
 * Render a template to a string.
 *
 * @param string $name Template name.
 * @param array  $args Arguments.
 * @return string
 */
function render( $name, $args = array() ) {
	ob_start();
	ohmylms_get_template( $name, $args );
	return (string) ob_get_clean();
}

/**
 * Attributes of the first element matching a class, `#id` or `tag[name=value]`.
 *
 * @param string $html     HTML.
 * @param string $selector Selector.
 * @param int    $nth      Which match (0-based).
 * @return array|null Attribute map, null when missing.
 */
function attributes( $html, $selector, $nth = 0 ) {
	$tags = new WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag() ) {
		if ( preg_match( '/^(\w+)\[(\w+)=([^\]]*)\]$/', $selector, $m ) ) {
			$match = strtoupper( $m[1] ) === $tags->get_tag() && $m[3] === (string) $tags->get_attribute( $m[2] );
		} elseif ( 0 === strpos( $selector, '#' ) ) {
			$match = substr( $selector, 1 ) === $tags->get_attribute( 'id' );
		} else {
			$match = $tags->has_class( $selector );
		}
		if ( $match && 0 === $nth-- ) {
			$found = array();
			foreach ( (array) $tags->get_attribute_names_with_prefix( '' ) as $name ) {
				$found[ $name ] = $tags->get_attribute( $name );
			}
			return $found;
		}
	}
	return null;
}

/**
 * Assert attribute values (true for a boolean attribute).
 *
 * @param array|null $found    From attributes().
 * @param array      $expected Name => value.
 * @param string     $label    What is checked.
 */
function has( $found, array $expected, $label ) {
	ok( is_array( $found ), "$label: element missing" );
	foreach ( $expected as $name => $value ) {
		ok( array_key_exists( $name, $found ) && $found[ $name ] === $value, "$label: expected $name=" . var_export( $value, true ) . ', got ' . var_export( $found[ $name ] ?? null, true ) );
	}
}

/**
 * Reset AuthForms' per-request state and the once() guard of its state.
 */
function reset_auth() {
	foreach ( array(
		'errors'  => array(),
		'view'    => '',
		'pending' => '',
	) as $property => $value ) {
		( new ReflectionProperty( AuthForms::class, $property ) )->setValue( null, $value );
	}
	$rendered = new ReflectionProperty( Interactivity::class, 'rendered' );
	$list     = $rendered->getValue();
	unset( $list['auth-forms-state'] );
	$rendered->setValue( null, $list );
}

/**
 * Replace the request with a form post as `request()` sends it (the form's fields, `action` set).
 *
 * @param array $fields Fields.
 */
function post( array $fields ) {
	$_POST                     = wp_slash( $fields );
	$_REQUEST                  = $_POST;
	$_SERVER['REQUEST_METHOD'] = 'POST';
}

$exit     = 0;
$users    = array();
$comments = array();
$suffix   = strtolower( wp_generate_password( 8, false ) );
$password = 'Fixture-' . wp_generate_password( 12, false );
add_filter( 'pre_wp_mail', '__return_true' );

try {
	wp_set_current_user( 0 );
	unset( $_COOKIE['ohmylms_pending_course'] );

	// Modules.
	Interactivity::register();
	wp_enqueue_script_module( 'ohmylms/reviews' );
	ob_start();
	wp_script_modules()->print_enqueued_script_modules();
	wp_script_modules()->print_import_map();
	$modules = (string) ob_get_clean();
	ok( false !== strpos( $modules, 'assets/interactivity/reviews.js' ), 'ohmylms/reviews is registered and enqueued' );
	ok( false !== strpos( $modules, 'assets/interactivity/sdk.js' ), 'ohmylms/reviews imports the SDK' );

	// Login form.
	reset_auth();
	ob_start();
	ohmylms_login_form(
		array(
			'redirect_to' => home_url( '/learn/' ),
			'hidden'      => false,
		)
	);
	$login = (string) ob_get_clean();
	$form  = attributes( $login, 'ohmylms-form-login' );
	has(
		$form,
		array(
			'data-wp-interactive'     => 'ohmylms/forms',
			'data-wp-on--submit'      => 'actions.submit',
			'data-wp-class--is-busy'  => 'context.busy',
			'data-wp-bind--aria-busy' => 'context.busy',
			'data-wp-style--display'  => 'state.authDisplay',
			'data-wp-init--fold'      => 'callbacks.foldFilled',
			'method'                  => 'post',
		),
		'login form'
	);
	ok(
		array(
			'authForm'   => 'login',
			'authHidden' => false,
			'busy'       => false,
			'error'      => '',
		) === json_decode( $form['data-wp-context'], true ),
		'login context: ' . $form['data-wp-context']
	);
	ok( ! isset( $form['style'] ), 'a visible login form has no inline style' );
	has(
		attributes( $login, 'ohmylms-form-error' ),
		array(
			'role'                 => 'alert',
			'data-wp-bind--hidden' => 'state.errorHidden',
			'data-wp-text'         => 'context.error',
			'hidden'               => true,
		),
		'login error region'
	);
	has( attributes( $login, 'ohmylms-form-login-submit' ), array( 'data-wp-bind--disabled' => 'state.submitDisabled' ), 'login submit' );
	has( attributes( $login, 'ohmylms-loader' ), array( 'data-wp-style--display' => 'state.loaderDisplay' ), 'login loader' );
	has(
		attributes( $login, 'ohmylms-show-signup-form' ),
		array(
			'data-wp-on--click' => 'actions.showSignup',
			'role'              => 'button',
		),
		'show sign-up link'
	);
	has( attributes( $login, 'input[name=action]' ), array( 'value' => 'ohmylms_login' ), 'login action field' );
	ok( 1 === substr_count( $login, 'data-wp-interactive=' ), 'the login form is one island' );
	$state = wp_interactivity_state( 'ohmylms/forms' );
	ok( '' === $state['authView'] && '' === $state['pendingMessage'] && '' !== $state['messages']['passwordShort'], 'ohmylms/forms state: ' . wp_json_encode( $state ) );

	// Sign-up form, hidden as on checkout.
	ob_start();
	ohmylms_signup_form(
		array(
			'redirect_to' => home_url( '/' ),
			'hidden'      => true,
		)
	);
	$signup = (string) ob_get_clean();
	$form   = attributes( $signup, 'ohmylms-form-signup' );
	has(
		$form,
		array(
			'data-wp-on--submit'     => 'actions.submit',
			'data-wp-style--display' => 'state.authDisplay',
			'data-wp-init--consent'  => 'callbacks.syncConsent',
			'style'                  => 'display:none;',
		),
		'sign-up form'
	);
	ok(
		array(
			'consent'    => false,
			'authForm'   => 'signup',
			'authHidden' => true,
			'busy'       => false,
			'error'      => '',
		) === json_decode( $form['data-wp-context'], true ),
		'sign-up context: ' . $form['data-wp-context']
	);
	has(
		attributes( $signup, 'ohmylms-form-signup-submit' ),
		array(
			'data-wp-bind--disabled' => 'state.submitDisabled',
			'disabled'               => true,
		),
		'sign-up submit'
	);
	has( attributes( $signup, 'ohmylms-show-login-form' ), array( 'data-wp-on--click' => 'actions.showLogin' ), 'show login link' );
	has(
		attributes( $signup, 'ohmylms-email-pending-notice' ),
		array(
			'data-wp-interactive'  => 'ohmylms/forms',
			'data-wp-bind--hidden' => 'state.pendingHidden',
			'hidden'               => true,
		),
		'pending notice'
	);
	ok( false !== strpos( $signup, 'data-wp-text="state.pendingMessage"' ), 'pending message is bound' );

	// Theme overrides copied before L5 (L2 markup, then no directives at all).
	$old_login = '<form class="ohmylms-login-signup ohmylms-form-login login" method="post" style="display:none;"><input name="username"><button type="submit" class="ohmylms-button ohmylms-form-login-submit">Log in</button><p><a href="#" class="ohmylms-show-signup-form">Sign Up</a></p></form>';
	$decorated = apply_filters( 'ohmylms_template_html', $old_login, 'global/form-login.php', array() );
	$form      = attributes( $decorated, 'ohmylms-form-login' );
	has(
		$form,
		array(
			'data-wp-interactive' => 'ohmylms/forms',
			'data-wp-on--submit'  => 'actions.submit',
			'data-wp-init--fold'  => 'callbacks.foldFilled',
		),
		'override login form'
	);
	ok( true === json_decode( $form['data-wp-context'], true )['authHidden'], 'an override hidden with an inline style starts hidden' );
	has( attributes( $decorated, 'ohmylms-show-signup-form' ), array( 'data-wp-on--click' => 'actions.showSignup' ), 'override sign-up link' );
	has( attributes( $decorated, 'ohmylms-form-login-submit' ), array( 'data-wp-bind--disabled' => 'state.submitDisabled' ), 'override login submit' );
	ok( AuthForms::decorate( $decorated ) === $decorated, 'decorating twice changes nothing' );

	$old_signup = '<form class="ohmylms-login-signup ohmylms-form-signup signup" method="post"><input type="checkbox" name="tnc-accept"><button type="submit" class="ohmylms-form-signup-submit" disabled>Sign Up</button></form>';
	$decorated  = apply_filters( 'ohmylms_template_html', $old_signup, 'global/form-signup.php', array() );
	$context    = json_decode( attributes( $decorated, 'ohmylms-form-signup' )['data-wp-context'], true );
	ok( false === $context['consent'] && 'signup' === $context['authForm'] && false === $context['authHidden'], 'override sign-up context keeps the terms gate: ' . wp_json_encode( $context ) );

	$link = AuthForms::decorate( '<p><a href="#" class="ohmylms-show-login-form">Log in</a></p>' );
	has(
		attributes( $link, 'ohmylms-show-login-form' ),
		array(
			'data-wp-interactive' => 'ohmylms/forms',
			'data-wp-on--click'   => 'actions.showLogin',
		),
		'a switch link outside a form becomes an island'
	);
	$plain = '<form class="other"><a href="#">x</a></form>';
	ok( AuthForms::decorate( $plain ) === $plain && Reviews::decorate( $plain ) === $plain, 'unrelated markup is unchanged' );

	// Login endpoint with the module's request shape.
	$login_user = wp_insert_user(
		array(
			'user_login' => 'l5-login-' . $suffix,
			'user_email' => 'l5-login-' . $suffix . '@example.test',
			'user_pass'  => $password,
			'role'       => 'subscriber',
		)
	);
	ok( is_int( $login_user ), 'fixture user created' );
	$users[]     = $login_user;
	$login_nonce = wp_create_nonce( 'ohmylms-login' );
	$login_post  = static function ( $overrides = array() ) use ( $suffix, $password, $login_nonce ) {
		return array_merge(
			array(
				'username'            => 'l5-login-' . $suffix,
				'password'            => $password,
				'rememberme'          => 'forever',
				'ohmylms-login-nonce' => $login_nonce,
				'action'              => 'ohmylms_login',
				'redirect_to'         => home_url( '/learn/' ),
			),
			$overrides
		);
	};

	post( $login_post( array( 'password' => 'wrong-' . $password ) ) );
	$answer = Ajax::login_response();
	ok( 'error' === $answer['status'] && '' !== AuthForms::plain_text( $answer['message'] ), 'a wrong password is an error with a message: ' . wp_json_encode( $answer ) );
	ok( false === strpos( AuthForms::plain_text( $answer['message'] ), '<' ), 'the message reduces to plain text' );

	post( $login_post( array( 'ohmylms-login-nonce' => 'stale' ) ) );
	$answer = Ajax::login_response();
	ok( 'error' === $answer['status'] && false !== strpos( $answer['message'], 'expired' ), 'a stale nonce answers JSON instead of 0: ' . wp_json_encode( $answer ) );

	post( array( 'action' => 'ohmylms_login' ) );
	ok( null === Ajax::login_response(), 'a request without login fields is ignored' );

	post( $login_post() );
	$answer = Ajax::login_response();
	ok( 'success' === $answer['status'] && home_url( '/learn/' ) === $answer['redirect_url'], 'valid credentials sign in and keep a same-site redirect: ' . wp_json_encode( $answer ) );

	post( $login_post( array( 'redirect_to' => 'https://evil.example/phish' ) ) );
	$answer = Ajax::login_response();
	ok( 'success' === $answer['status'] && 0 === strpos( $answer['redirect_url'], home_url() ), 'an external redirect_to falls back to a site URL: ' . $answer['redirect_url'] );

	// Sign-up endpoint: success without verification, pending with it, duplicate email.
	$signup_nonce = wp_create_nonce( 'ohmylms-signup' );
	$signup_post  = static function ( $email ) use ( $password, $signup_nonce ) {
		return array(
			'first_name'           => 'Lfive',
			'last_name'            => 'Fixture',
			'email'                => $email,
			'password'             => $password,
			'phone'                => '+976 9911-2233',
			'country'              => 'MN',
			'tnc-accept'           => 'on',
			'ohmylms-signup-nonce' => $signup_nonce,
			'action'               => 'ohmylms_signup',
			'redirect_to'          => home_url( '/learn/' ),
		);
	};
	$verify       = static function ( $value ) {
		$filter = static function () use ( $value ) {
			return $value;
		};
		remove_all_filters( 'pre_option_ohmylms_require_email_verification' );
		add_filter( 'pre_option_ohmylms_require_email_verification', $filter );
	};

	$verify( 'no' );
	$email = 'l5-signup-' . $suffix . '@example.test';
	post( $signup_post( $email ) );
	$answer  = Ajax::signup_response();
	$created = get_user_by( 'email', $email );
	if ( $created ) {
		$users[] = $created->ID;
	}
	ok( 'success' === $answer['status'] && home_url( '/learn/' ) === $answer['redirect_url'], 'sign-up without verification signs in: ' . wp_json_encode( $answer ) );
	ok( $created && 'Lfive' === $created->first_name, 'the account is created with the posted names' );

	post( $signup_post( $email ) );
	$answer = Ajax::signup_response();
	ok( 'error' === $answer['status'] && '' !== $answer['message'], 'a duplicate email is an error: ' . wp_json_encode( $answer ) );

	$verify( 'yes' );
	$pending_email = 'l5-pending-' . $suffix . '@example.test';
	post( $signup_post( $pending_email ) );
	$answer  = Ajax::signup_response();
	$created = get_user_by( 'email', $pending_email );
	if ( $created ) {
		$users[] = $created->ID;
	}
	ok( 'pending_verification' === $answer['status'] && '' !== $answer['message'], 'sign-up with verification waits for the email: ' . wp_json_encode( $answer ) );
	ok( $created && home_url( '/learn/' ) === get_user_meta( $created->ID, '_ohmylms_post_verification_redirect', true ), 'the redirect is kept for after verification' );

	post( array_merge( $signup_post( 'l5-stale-' . $suffix . '@example.test' ), array( 'ohmylms-signup-nonce' => 'stale' ) ) );
	$answer = Ajax::signup_response();
	ok( 'error' === $answer['status'] && false === get_user_by( 'email', 'l5-stale-' . $suffix . '@example.test' ), 'a stale sign-up nonce creates nothing' );
	remove_all_filters( 'pre_option_ohmylms_require_email_verification' );

	// The same forms posted to a page without JavaScript.
	reset_auth();
	post( $login_post( array( 'password' => 'wrong-' . $password ) ) );
	AuthForms::handle_post();
	$view = AuthForms::view( 'login', true );
	ok( false === $view['hidden'] && '' !== $view['error'] && 'display:block;' === $view['style'], 'a failed login without JavaScript shows the form with its error: ' . wp_json_encode( $view ) );
	ob_start();
	ohmylms_login_form( array( 'hidden' => true ) );
	$login = (string) ob_get_clean();
	$error = attributes( $login, 'ohmylms-form-error' );
	ok( ! isset( $error['hidden'] ) && false !== strpos( $login, esc_html( $view['error'] ) ), 'the error is rendered in the live region' );
	ok( 'login' === wp_interactivity_state( 'ohmylms/forms' )['authView'], 'the store starts on the login view' );

	reset_auth();
	// Throw from the redirect filter so the location can be read instead of exiting.
	add_filter(
		'wp_redirect',
		static function ( $location ) {
			throw new UnexpectedValueException( $location );
		}
	);
	post( $login_post() );
	$location = '';
	try {
		AuthForms::handle_post();
	} catch ( UnexpectedValueException $redirect ) {
		$location = $redirect->getMessage();
	}
	ok( home_url( '/learn/' ) === $location, 'a login without JavaScript redirects: ' . $location );
	remove_all_filters( 'wp_redirect' );

	reset_auth();
	$verify( 'yes' );
	$nojs_email = 'l5-nojs-' . $suffix . '@example.test';
	post( $signup_post( $nojs_email ) );
	AuthForms::handle_post();
	$created = get_user_by( 'email', $nojs_email );
	if ( $created ) {
		$users[] = $created->ID;
	}
	remove_all_filters( 'pre_option_ohmylms_require_email_verification' );
	ob_start();
	ohmylms_signup_form( array( 'hidden' => false ) );
	$signup = (string) ob_get_clean();
	ok( ! isset( attributes( $signup, 'ohmylms-email-pending-notice' )['hidden'] ) && 'display:none;' === attributes( $signup, 'ohmylms-form-signup' )['style'], 'a pending sign-up without JavaScript shows the notice instead of the form' );

	reset_auth();
	$_POST                     = array();
	$_SERVER['REQUEST_METHOD'] = 'GET';

	// Course review form.
	$course_ids = get_posts(
		array(
			'post_type'   => 'ohmylms-course',
			'post_status' => 'publish',
			'numberposts' => 20,
			'fields'      => 'ids',
		)
	);
	$course_id  = 0;
	foreach ( $course_ids as $id ) {
		$candidate = ohmylms_get_course( $id );
		if ( $candidate && $candidate->get_enable_reviews() && comments_open( $id ) ) {
			$course_id = (int) $id;
			break;
		}
	}
	ok( $course_id > 0, 'the site has a published course with reviews' );
	$admins = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ID',
		)
	);
	wp_set_current_user( (int) $admins[0] );
	global $course, $post;
	$course = ohmylms_get_course( $course_id );
	$post   = get_post( $course_id ); // comment_form() renders for the global post.
	setup_postdata( $post );
	$reviews = render( 'single-course/tabs/reviews.php' );
	ok( false !== strpos( $reviews, 'ohmylms-course-reviews-form' ), 'the review form renders for a user with access' );
	has(
		attributes( $reviews, 'ohmylms-course-reviews-form' ),
		array(
			'data-wp-interactive'         => 'ohmylms/reviews',
			'data-wp-init'                => 'callbacks.restore',
			'data-wp-on--submit'          => 'actions.submit',
			'data-wp-on--change'          => 'actions.change',
			'data-wp-on-window--pageshow' => 'actions.reset',
		),
		'review form root'
	);
	ok(
		array(
			'rating'     => 0,
			'hasComment' => false,
			'attempted'  => false,
			'submitting' => false,
		) === json_decode( attributes( $reviews, 'ohmylms-course-reviews-form' )['data-wp-context'], true ),
		'review context'
	);
	has(
		attributes( $reviews, 'ohmylms-stars' ),
		array(
			'role'                    => 'radiogroup',
			'data-wp-class--selected' => 'state.rated',
		),
		'star group'
	);
	for ( $star = 1; $star <= 5; $star++ ) {
		has(
			attributes( $reviews, 'star-' . $star ),
			array(
				'data-wp-context'       => '{"star":' . $star . '}',
				'data-wp-class--active' => 'state.isStar',
			),
			"star $star"
		);
		has(
			attributes( $reviews, 'input[value=' . $star . ']' ),
			array(
				'type'     => 'radio',
				'name'     => 'rating',
				'required' => true,
			),
			"star $star radio"
		);
	}
	ok( false === strpos( $reviews, '<select name="rating"' ) && false === strpos( $reviews, 'href="#" data-rating' ), 'no link stars or hidden select remain' );
	has(
		attributes( $reviews, 'required-notice' ),
		array(
			'role'                   => 'alert',
			'data-wp-style--display' => 'state.noticeDisplay',
		),
		'required notice'
	);
	has(
		attributes( $reviews, '#submit' ),
		array(
			'data-wp-on--click'      => 'actions.validate',
			'data-wp-bind--disabled' => 'context.submitting',
		),
		'review submit'
	);

	// Override with the pre-L5 link stars.
	$old_review = '<div class="ohmylms-course-reviews-form"><form id="commentform" class="comment-form"><div class="comment-form-rating"><p class="ohmylms-stars"><a class="star-1" href="#" data-rating="1">*</a><a class="star-2" href="#" data-rating="2">*</a></p><select name="rating" id="rating"><option value="">-</option><option value="1">1</option></select></div><textarea id="comment" name="comment"></textarea><span class="required-notice">Required</span><input name="submit" type="submit" id="submit" class="submit" value="Submit"></form></div>';
	$decorated  = apply_filters( 'ohmylms_template_html', $old_review, 'single-course/tabs/reviews.php', array() );
	has( attributes( $decorated, 'ohmylms-course-reviews-form' ), array( 'data-wp-interactive' => 'ohmylms/reviews' ), 'override review root' );
	has(
		attributes( $decorated, 'star-2' ),
		array(
			'data-wp-on--click'     => 'actions.pick',
			'data-wp-context'       => '{"star":2}',
			'data-wp-class--active' => 'state.isStar',
			'aria-label'            => '2 stars',
		),
		'override link star'
	);
	has( attributes( $decorated, 'required-notice' ), array( 'data-wp-style--display' => 'state.noticeDisplay' ), 'override notice' );
	has( attributes( $decorated, '#submit' ), array( 'data-wp-on--click' => 'actions.validate' ), 'override submit' );
	ok( Reviews::decorate( $decorated ) === $decorated, 'decorating the review form twice changes nothing' );

	// A review posted with the radio stars (wp-comments-post.php's handler) stores the rating.
	$reviewer = wp_insert_user(
		array(
			'user_login' => 'l5-review-' . $suffix,
			'user_email' => 'l5-review-' . $suffix . '@example.test',
			'user_pass'  => $password,
			'role'       => 'subscriber',
		)
	);
	ok( is_int( $reviewer ), 'review fixture user created' );
	$users[] = $reviewer;
	update_user_meta( $reviewer, '_ohmylms_email_verified', 'yes' );
	wp_set_current_user( $reviewer );
	global $wp_filter;
	foreach ( (array) ( $wp_filter['comment_post']->callbacks ?? array() ) as $priority => $callbacks ) {
		foreach ( $callbacks as $callback ) {
			if ( is_array( $callback['function'] ) && is_object( $callback['function'][0] ) && 'after_comment' === $callback['function'][1] ) {
				remove_action( 'comment_post', $callback['function'], $priority );
			}
		}
	}
	$review_post = array(
		'comment_post_ID' => (string) $course_id,
		'comment'         => 'L5 fixture review ' . $suffix,
		'rating'          => '4',
		'submit'          => 'Submit',
	);
	post( $review_post );
	$comment = wp_handle_comment_submission( wp_unslash( $_POST ) );
	ok( $comment instanceof WP_Comment, 'the review is accepted: ' . ( is_wp_error( $comment ) ? $comment->get_error_message() : '' ) );
	$comments[] = (int) $comment->comment_ID;
	ok( '4' === (string) get_comment_meta( $comment->comment_ID, 'rating', true ), 'the radio rating is stored as comment meta' );

	ok( array() === $warnings, "no PHP warnings from the plugin:\n" . implode( "\n", $warnings ) );
	echo "OK: $checks login, sign-up and review checks passed.\n";
} catch ( Throwable $error ) {
	echo 'FAIL: ' . $error->getMessage() . ' (' . basename( $error->getFile() ) . ':' . $error->getLine() . ")\n";
	$exit = 1;
} finally {
	$_POST = array();
	foreach ( $comments as $comment_id ) {
		$post_id = (int) get_comment( $comment_id )->comment_post_ID;
		wp_delete_comment( $comment_id, true );
		\OhMyLMS\CourseComment::clear_transients( $post_id );
	}
	foreach ( array_unique( $users ) as $user_id ) {
		wp_delete_user( (int) $user_id );
	}
	$left = get_users(
		array(
			'search'         => 'l5-*' . $suffix . '*',
			'search_columns' => array( 'user_login', 'user_email' ),
			'fields'         => 'ID',
		)
	);
	if ( $left ) {
		echo 'FAIL: fixture users left behind: ' . implode( ',', $left ) . "\n";
		$exit = 1;
	}
}
exit( $exit );
