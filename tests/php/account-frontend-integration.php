<?php
/**
 * Learner account pages (batch L4): the profile edit, my-courses, notifications, membership,
 * dashboard membership card and invoice templates render `ohmylms/account` / `ohmylms/tabs`
 * directives and state, pre-L4 theme overrides are decorated the same way, and the admin-ajax
 * handlers accept exactly the request the module sends: cancelling a membership with only the
 * subscription (and order) works for its owner and is refused for another learner.
 *
 * Runs against the Local site. It creates two tagged learners, a membership plan, an offline order
 * and a subscription, and removes them (posts, order items, notes, membership and enrolment rows,
 * scheduled renewals, users) in `finally`. No email is sent (wp_mail is short-circuited).
 *
 * Usage (set OHMYLMS_ACCOUNT_FRONTEND_TEST=1):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/account-frontend-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.Security.NonceVerification, WordPress.DB.DirectDatabaseQuery, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_ACCOUNT_FRONTEND_TEST' ) ) {
	exit( "Set OHMYLMS_ACCOUNT_FRONTEND_TEST=1 to run the learner account tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Commerce\Data\Order;
use OhMyLMS\Commerce\Data\OrderItemCourse;
use OhMyLMS\Commerce\SubscriptionManager;
use OhMyLMS\Extensions\Account;
use OhMyLMS\Extensions\Interactivity;

global $wpdb;

$checks    = 0;
$warnings  = array();
$post_ids  = array();
$sub_ids   = array();
$user_ids  = array();
$tag       = 'actest' . strtolower( wp_generate_password( 6, false, false ) );
$exit_code = 0;

add_filter( 'pre_wp_mail', '__return_true' );
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
 * @param string $selector Class name, `#id`, or `name=value` for an attribute.
 * @return array|null Attribute map, null when missing.
 */
function attributes_of( $html, $selector ) {
	$tags = new WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag() ) {
		if ( 0 === strpos( $selector, '#' ) ) {
			$match = substr( $selector, 1 ) === $tags->get_attribute( 'id' );
		} elseif ( false !== strpos( $selector, '=' ) ) {
			list( $name, $value ) = explode( '=', $selector, 2 );
			$match                = $value === $tags->get_attribute( $name );
		} else {
			$match = $tags->has_class( $selector );
		}
		if ( $match ) {
			$attributes = array();
			foreach ( (array) $tags->get_attribute_names_with_prefix( '' ) as $name ) {
				$attributes[ $name ] = $tags->get_attribute( $name );
			}
			return $attributes;
		}
	}
	return null;
}

/**
 * Assert that an element carries every attribute.
 *
 * @param string $html       HTML.
 * @param string $selector   See attributes_of().
 * @param array  $attributes Name => value (true for a boolean attribute).
 * @param string $label      What is checked.
 */
function has( $html, $selector, array $attributes, $label ) {
	$found = attributes_of( $html, $selector );
	ok( null !== $found, "$label: no element matches $selector" );
	foreach ( $attributes as $name => $value ) {
		ok( array_key_exists( $name, $found ) && $value === $found[ $name ], "$label: expected $name=" . wp_json_encode( $value ) . ', got ' . wp_json_encode( $found ) );
	}
}

/**
 * Decoded `data-wp-context` of the first element matching a selector.
 *
 * @param string $html     HTML.
 * @param string $selector See attributes_of().
 * @return array
 */
function context_of( $html, $selector ) {
	$found = attributes_of( $html, $selector );
	return isset( $found['data-wp-context'] ) ? (array) json_decode( $found['data-wp-context'], true ) : array();
}

/**
 * Call an admin-ajax handler like admin-ajax.php does and decode its answer.
 *
 * @param string $method `Ajax` method.
 * @param int    $who    Current user.
 * @param array  $post   POST fields.
 * @return mixed Decoded JSON, or the raw body (`-1` for a refused nonce).
 * @throws RuntimeException When the handler fails other than by ending the request.
 */
function call_ajax( $method, $who, array $post ) {
	wp_set_current_user( $who );
	$_POST    = $post;
	$_REQUEST = $post;
	$die      = static function () {
		return static function ( $message = '' ) {
			// wp_die( -1 ) from check_ajax_referer(): admin-ajax.php prints the message.
			echo is_scalar( $message ) ? (string) $message : '';
			throw new RuntimeException( 'ajax-done' );
		};
	};
	add_filter( 'wp_doing_ajax', '__return_true' );
	add_filter( 'wp_die_ajax_handler', $die );
	ob_start();
	try {
		call_user_func( array( \OhMyLMS\Ajax::class, $method ) );
	} catch ( RuntimeException $done ) {
		if ( 'ajax-done' !== $done->getMessage() ) {
			throw $done;
		}
	} finally {
		$body = (string) ob_get_clean();
		remove_filter( 'wp_doing_ajax', '__return_true' );
		remove_filter( 'wp_die_ajax_handler', $die );
		$_POST    = array();
		$_REQUEST = array();
	}
	$decoded = json_decode( $body, true );
	return null === $decoded ? $body : $decoded;
}

/**
 * Subscription status without the prefix, read from the database.
 *
 * @param int $id Subscription ID.
 * @return string
 */
function status_of( $id ) {
	clean_post_cache( $id );
	return substr( (string) get_post_status( $id ), 8 );
}

try {
	ok( ohmylms_is_pro(), 'memberships are available' );

	// Module: registered with the runtime and the SDK as dependencies.
	Interactivity::register();
	wp_enqueue_script_module( 'ohmylms/account' );
	ob_start();
	wp_script_modules()->print_enqueued_script_modules();
	wp_script_modules()->print_import_map();
	$modules = (string) ob_get_clean();
	ok( false !== strpos( $modules, 'assets/interactivity/account.js' ), 'ohmylms/account is registered and enqueued' );
	ok( false !== strpos( $modules, 'assets/interactivity/sdk.js' ), 'ohmylms/account imports the SDK' );

	// Fixtures: two learners, a monthly plan, an order and an active subscription for the first.
	foreach ( array( 'owner', 'other' ) as $who ) {
		$id = wp_insert_user(
			array(
				'user_login' => $tag . '_' . $who,
				'user_pass'  => wp_generate_password( 24 ),
				'user_email' => $tag . '-' . $who . '@example.invalid',
				'first_name' => 'Ada',
				'last_name'  => ucfirst( $who ),
				'role'       => 'subscriber',
			)
		);
		ok( ! is_wp_error( $id ), "temporary $who learner created" );
		$user_ids[ $who ] = $id;
	}
	$owner = $user_ids['owner'];
	$other = $user_ids['other'];

	$plan = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_MEMBERSHIP_CPT,
			'post_status' => 'publish',
			'post_title'  => 'Plan ' . $tag,
		)
	);
	ok( $plan > 0, 'membership plan created' );
	$post_ids[] = $plan;
	update_post_meta( $plan, '_regular_price', '25' );
	update_post_meta( $plan, '_subscription_period', 'month' );
	update_post_meta( $plan, '_subscription_period_interval', 1 );
	update_post_meta( $plan, '_subscription_length', '0' );

	$order = new Order();
	$order->set_status( 'completed' );
	$order->set_first_name( 'Ada' );
	$order->set_last_name( 'Owner ' . $tag );
	$order->set_email( $tag . '-owner@example.invalid' );
	$order->set_student_id( $owner );
	$order->set_currency( 'USD' );
	$order->set_payment_method( 'offline' );
	$order->set_payment_method_title( 'Offline' );
	$order->set_total( 25 );
	$item = new OrderItemCourse();
	$item->set_props(
		array(
			'name'      => 'Plan ' . $tag,
			'course_id' => $plan,
			'quantity'  => 1,
			'subtotal'  => 25,
			'total'     => 25,
		)
	);
	$order->add_item( $item );
	$post_ids[] = (int) $order->save();
	$sub        = SubscriptionManager::create_subscription( $order, $plan );
	ok( is_int( $sub ) && $sub > 0, 'subscription created' );
	$sub_ids[] = $sub;
	ok( SubscriptionManager::update_subscription_status( $sub, 'active' ), 'subscription active' );
	ok( (int) get_post_meta( $sub, '_original_order_id', true ) === (int) $order->get_id(), 'subscription points at its order' );

	wp_set_current_user( $owner );
	$student = new \OhMyLMS\Data\Student( $owner );

	// Profile edit: images, rows, state.
	$edit = render( 'profile/profile-edit.php', array( 'student' => $student ) );
	has(
		$edit,
		'student-profile-edit',
		array(
			'data-wp-interactive'    => 'ohmylms/account',
			'data-wp-class--is-busy' => 'state.isBusy',
		),
		'profile edit root'
	);
	$context = context_of( $edit, 'student-profile-edit' );
	ok( $student->get_cover_image() === $context['cover'] && false === $context['hasCover'] && '' === $context['avatar'] && '' === $context['busy'], 'profile context: ' . wp_json_encode( $context ) );
	has( $edit, '#student-cover-image', array( 'data-wp-bind--src' => 'context.cover' ), 'cover image' );
	has( $edit, '#cover_image_input', array( 'data-wp-on--change' => 'actions.uploadCover' ), 'cover input' );
	has( $edit, '#profile_photo_input', array( 'data-wp-on--change' => 'actions.uploadAvatar' ), 'photo input' );
	has(
		$edit,
		'#cover-image-delete',
		array(
			'data-wp-on--click'      => 'actions.deleteCover',
			'data-wp-style--display' => 'state.coverDeleteDisplay',
			'style'                  => 'display: none;',
		),
		'cover delete'
	);
	has( $edit, 'cover-edit', array( 'data-wp-on--keydown' => 'actions.pickKey' ), 'cover label' );
	has( $edit, 'edit-avatar', array( 'data-wp-on--keydown' => 'actions.pickKey' ), 'photo label' );
	has(
		$edit,
		'#student-profile-photo',
		array(
			'data-wp-bind--hidden' => 'state.avatarHidden',
			'hidden'               => true,
		),
		'photo without a picture'
	);
	$initials = attributes_of( $edit, 'ohmylms-profile-initials' );
	ok( is_array( $initials ) && ! array_key_exists( 'hidden', $initials ) && 'state.initialsHidden' === $initials['data-wp-bind--hidden'], 'initials shown without a picture' );
	has( $edit, '#skills-container', array( 'data-wp-on--click' => 'actions.removeRow' ), 'skills rows' );
	has( $edit, 'data-template=links-template', array( 'data-wp-on--click' => 'actions.addRow' ), 'add link row' );
	ok( false !== strpos( $edit, '<template id="skills-template">' ) && false === strpos( $edit, 'text/template' ), 'row templates are <template> elements' );
	ok( false === strpos( $edit, 'DOMContentLoaded' ), 'the inline rows script is gone' );
	has(
		$edit,
		'value=save_account_details',
		array(
			'type' => 'hidden',
			'name' => 'action',
		),
		'the form still posts save_account_details without JavaScript'
	);
	ok( false !== strpos( $edit, 'save-account-details-nonce' ), 'the form keeps its nonce' );

	$state = wp_interactivity_state( 'ohmylms/account' );
	ok( 1 === wp_verify_nonce( $state['nonces']['coverUpload'], 'student_profile_cover_image' ), 'state cover upload nonce' );
	ok( 1 === wp_verify_nonce( $state['nonces']['coverDelete'], 'student_profile_cover_image_delete' ), 'state cover delete nonce' );
	ok( 1 === wp_verify_nonce( $state['nonces']['avatarUpload'], 'student_profile_image_upload' ), 'state photo nonce' );
	ok( $state['maxUpload'] > 0 && '' !== $state['messages']['imageType'] && '' !== $state['messages']['cancelTitle'], 'state limit and messages' );
	ok( false !== strpos( wp_json_encode( wp_interactivity_state( 'ohmylms/ui' ) ), 'toast' ), 'the toast store is prepared' );
	$frontend = ( new \OhMyLMS\Assets\FrontendAssets() )->get_script_data( 'ohmylms-frontend' );
	foreach ( array( 'student_profile_cover_upload_nonce', 'cancel_membership_nonce', 'course_drop_nonce', 'lesson_access_nonce' ) as $gone ) {
		ok( ! array_key_exists( $gone, $frontend ), "ohmylms_frontend_params no longer carries $gone" );
	}
	ok( ! has_action( 'wp_ajax_ohmylms_drop_course' ) && ! has_action( 'wp_ajax_nopriv_ohmylms_lesson_access_nonce' ), 'the dead drop-course and lesson-access actions are gone' );

	// The module's upload and delete requests: the state nonces are accepted, a wrong one is refused.
	$no_file = call_ajax( 'student_cover_image_upload', $owner, array( 'nonce' => $state['nonces']['coverUpload'] ) );
	ok( is_array( $no_file ) && false === $no_file['success'] && 'No file uploaded' === $no_file['data']['message'], 'cover upload without a file: ' . wp_json_encode( $no_file ) );
	ok( '-1' === trim( (string) call_ajax( 'student_profile_image_upload', $owner, array( 'nonce' => $state['nonces']['coverUpload'] ) ) ), 'the photo upload refuses the cover nonce' );
	$owner_student = new \OhMyLMS\Data\Student( $owner );
	$owner_student->set_cover_image( 'https://example.invalid/' . $tag . '.png' );
	$owner_student->save();
	ok( ( new \OhMyLMS\Data\Student( $owner ) )->has_cover_image(), 'fixture cover stored' );
	$deleted = call_ajax( 'student_cover_image_delete', $owner, array( 'nonce' => $state['nonces']['coverDelete'] ) );
	ok( is_array( $deleted ) && true === $deleted['success'] && '' !== $deleted['data']['url'], 'cover delete answers the default image: ' . wp_json_encode( $deleted ) );
	ok( ! ( new \OhMyLMS\Data\Student( $owner ) )->has_cover_image(), 'cover removed' );

	// My courses: ohmylms/tabs.
	$courses = render( 'profile/my-courses.php', array( 'user' => wp_get_current_user() ) );
	has( $courses, 'ohmylms-my-courses-tab-nav', array( 'data-wp-interactive' => 'ohmylms/tabs' ), 'my courses tabs root' );
	ok( array( 'activeTab' => 'course-enrolled-courses' ) === context_of( $courses, 'ohmylms-my-courses-tab-nav' ), 'first tab active' );
	has(
		$courses,
		'#tab-course-completed-courses',
		array(
			'role'                        => 'tab',
			'data-wp-on--click'           => 'actions.select',
			'data-wp-on--keydown'         => 'actions.key',
			'data-wp-bind--aria-selected' => 'state.selected',
			'data-wp-bind--tabindex'      => 'state.tabIndex',
			'aria-selected'               => 'false',
		),
		'tab button'
	);
	has(
		$courses,
		'#course-completed-courses',
		array(
			'role'                  => 'tabpanel',
			'aria-labelledby'       => 'tab-course-completed-courses',
			'data-wp-class--active' => 'state.selected',
		),
		'tab panel'
	);
	ok( array( 'tabKey' => 'course-completed-courses' ) === context_of( $courses, '#course-completed-courses' ), 'panel key matches the button target' );

	// Notifications: a checked switch posts "on" without JavaScript.
	$notifications = render( 'profile/notifications.php', array( 'student' => $student ) );
	foreach ( Account::NOTIFICATION_SWITCHES as $switch ) {
		has( $notifications, 'name=' . $switch, array( 'value' => 'on' ), $switch );
	}
	$old_switch = '<div class="single-notification-settings"><label class="ohmylms-switcher"><input type="checkbox" name="notification_chapter" value="off" checked></label></div>';
	has( Account::template( $old_switch, 'profile/notifications.php' ), 'name=notification_chapter', array( 'value' => 'on' ), 'pre-L4 switch value' );
	$hub_switch = '<label class="oml-hub-check"><input type="checkbox" name="notification_chapter" value="1"></label>';
	ok( Account::template( $hub_switch, 'student-hub/settings.php' ) === $hub_switch, 'hub settings switches are left alone' );

	// Membership table: the cancel link carries the subscription and its order, nothing else.
	$table = render(
		'profile/membership.php',
		array(
			'current_page'   => 1,
			'student_orders' => (object) array( 'orders' => array( $order->get_id() ) ),
			'has_orders'     => true,
		)
	);
	has(
		$table,
		'do-membership-cancel',
		array(
			'data-wp-interactive' => 'ohmylms/account',
			'data-wp-on--click'   => 'actions.cancelMembership',
			'role'                => 'button',
		),
		'cancel link'
	);
	$cancel = context_of( $table, 'do-membership-cancel' );
	ok(
		array(
			'subscriptionId' => $sub,
			'orderId'        => (int) $order->get_id(),
			'busy'           => false,
		) === $cancel,
		'cancel context: ' . wp_json_encode( $cancel )
	);
	ok( false === strpos( $table, 'ohmylms-alert' ) && false === strpos( $table, 'data-student-id' ), 'the inline confirmation (and its student id) is gone' );
	$cancel_nonce = wp_interactivity_state( 'ohmylms/account' )['nonces']['cancelMembership'];
	ok( 1 === wp_verify_nonce( $cancel_nonce, 'cancel_membership_nonce' ), 'state cancel nonce' );

	// Dashboard membership card: the "View plan" dialog (its old selector never matched this markup).
	$membership               = ohmylms_get_membership( $plan );
	$membership->subscription = ohmylms_ecommerce_get_subscription( $sub );
	$membership->order        = ohmylms_ecommerce_get_order( $order->get_id() );
	$card                     = render( 'profile/loop/membership-card.php', array( 'memebership' => $membership ) );
	has(
		$card,
		'ohmylms-dashboard-single-membership',
		array(
			'data-wp-interactive' => 'ohmylms/account',
			'data-wp-context'     => '{"planOpen":false}',
		),
		'membership card'
	);
	$trigger = attributes_of( $card, 'view-plan' );
	$modal   = attributes_of( $card, 'ohmylms-membership-modal' );
	ok( $trigger && $modal && '#' . $modal['id'] === $trigger['href'] && $modal['id'] === $trigger['aria-controls'], 'View plan links to and controls its dialog: ' . wp_json_encode( array( $trigger, $modal ) ) );
	has(
		$card,
		'view-plan',
		array(
			'data-wp-on--click'           => 'actions.openPlan',
			'data-wp-bind--aria-expanded' => 'context.planOpen',
			'aria-haspopup'               => 'dialog',
		),
		'View plan'
	);
	has(
		$card,
		'ohmylms-membership-modal',
		array(
			'data-wp-style--display'    => 'state.planDisplay',
			'data-wp-bind--aria-hidden' => '!context.planOpen',
			'data-wp-on--click'         => 'actions.planBackdrop',
			'data-wp-watch'             => 'callbacks.planFocus',
		),
		'plan dialog overlay'
	);
	has(
		$card,
		'ohmylms-membership-modal-inner',
		array(
			'role'            => 'dialog',
			'aria-modal'      => 'true',
			'aria-labelledby' => $modal['id'] . '-title',
		),
		'plan dialog'
	);
	has( $card, 'close-modal', array( 'data-wp-on--click' => 'actions.closePlan' ), 'plan close' );
	$second = render( 'profile/loop/membership-card.php', array( 'memebership' => $membership ) );
	ok( attributes_of( $second, 'ohmylms-membership-modal' )['id'] !== $modal['id'], 'each card has its own dialog id' );

	// Invoice print.
	$invoice = render(
		'profile/invoice-details.php',
		array(
			'order'        => ohmylms_ecommerce_get_order( $order->get_id() ),
			'current_page' => 1,
		)
	);
	has(
		$invoice,
		'print-button',
		array(
			'data-wp-interactive' => 'ohmylms/account',
			'data-wp-on--click'   => 'actions.printInvoice',
		),
		'invoice print'
	);

	// Pre-L4 theme overrides get the same behaviour.
	$old_edit  = '<div class="ohmylms-student-profile-tab-content student-profile-edit"><div class="ohmylms-profile-cover-area"><img src="c.png" id="student-cover-image"><label for="cover_image_input" class="cover-edit"><input type="file" id="cover_image_input"></label><button id="cover-image-delete" style="display: none;">x</button><div class="ohmylms-profile-avatar"><figure>AO</figure><label for="profile_photo_input" class="edit-avatar"><input type="file" id="profile_photo_input"></label></div></div></div>';
	$decorated = Account::template( $old_edit, 'profile/profile-edit.php' );
	has( $decorated, 'student-profile-edit', array( 'data-wp-interactive' => 'ohmylms/account' ), 'override profile root' );
	ok( isset( context_of( $decorated, 'student-profile-edit' )['cover'] ), 'override profile context' );
	has( $decorated, '#cover_image_input', array( 'data-wp-on--change' => 'actions.uploadCover' ), 'override cover input' );
	has( $decorated, '#profile_photo_input', array( 'data-wp-on--change' => 'actions.uploadAvatar' ), 'override photo input' );
	has( $decorated, '#cover-image-delete', array( 'data-wp-style--display' => 'state.coverDeleteDisplay' ), 'override delete' );
	has( $decorated, 'edit-avatar', array( 'data-wp-on--keydown' => 'actions.pickKey' ), 'override photo label' );
	ok( 1 === substr_count( Account::template( $edit, 'profile/profile-edit.php' ), 'data-wp-on--change="actions.uploadCover"' ), 'current templates are not decorated twice' );

	$old_card  = '<div class="ohmylms-dashboard-single-membership"><a href="#" class="view-plan" role="button">View</a><div class="ohmylms-membership-modal" aria-hidden="true"><div class="ohmylms-membership-modal-wrapper"><div class="ohmylms-membership-modal-inner"><h4 class="membership-modal-title">Plan <a href="#" class="close-modal">x</a></h4></div></div></div></div>';
	$decorated = Account::template( $old_card, 'profile/loop/membership-card.php' );
	$trigger   = attributes_of( $decorated, 'view-plan' );
	$modal     = attributes_of( $decorated, 'ohmylms-membership-modal' );
	ok( 'actions.openPlan' === $trigger['data-wp-on--click'] && '#' . $modal['id'] === $trigger['href'] && 'callbacks.planFocus' === $modal['data-wp-watch'], 'override plan dialog: ' . wp_json_encode( array( $trigger, $modal ) ) );
	has( $decorated, 'ohmylms-membership-modal-inner', array( 'role' => 'dialog' ), 'override plan dialog role' );
	has( $decorated, 'membership-modal-title', array( 'id' => $modal['id'] . '-title' ), 'override plan title' );
	has( $decorated, 'close-modal', array( 'aria-label' => 'Close' ), 'override plan close' );

	$old_cancel = '<div class="dashboard-table-td action"><a href="#" class="do-membership-cancel">Cancel</a><div class="ohmylms-alert"><button class="ohmylms-cancel-membership" data-subscription-id="5" data-student-id="1">Confirm</button></div></div>';
	$decorated  = Account::template( $old_cancel, 'profile/membership.php' );
	has(
		$decorated,
		'do-membership-cancel',
		array(
			'data-wp-interactive' => 'ohmylms/account',
			'data-wp-on--click'   => 'actions.cancelMembership',
			'data-wp-context'     => '{"busy":false}',
		),
		'override cancel link'
	);

	$old_tabs  = '<div class="ohmylms-my-courses-tab-nav"><div class="ohmylms-my-courses-tab"><ul role="tablist"><li class="active" role="presentation"><button type="button" role="tab" aria-selected="true" data-target="#course-a">A</button></li><li role="presentation"><button type="button" role="tab" aria-selected="false" data-target="#course-b">B</button></li></ul></div><div class="ohmylms-my-courses-tab-content"><div class="ohmylms-single-tab-content active" id="course-a" role="tabpanel"></div><div class="ohmylms-single-tab-content" id="course-b" role="tabpanel"></div></div></div>';
	$decorated = Account::template( $old_tabs, 'profile/my-courses.php' );
	ok( array( 'activeTab' => 'course-a' ) === context_of( $decorated, 'ohmylms-my-courses-tab-nav' ), 'override tabs root' );
	ok( array( 'tabKey' => 'course-b' ) === context_of( $decorated, 'role=presentation' ) || array( 'tabKey' => 'course-a' ) === context_of( $decorated, 'role=presentation' ), 'override tab item key' );
	ok( 2 === substr_count( $decorated, 'data-wp-on--keydown="actions.key"' ) && 2 === substr_count( $decorated, 'data-wp-class--active="state.selected"', strpos( $decorated, 'ohmylms-my-courses-tab-content' ) ), 'override tab buttons and panels' );

	$plain = '<p class="profile-bio">Nothing to decorate</p>';
	ok( Account::template( $plain, 'profile/profile.php' ) === $plain, 'unrelated markup is unchanged' );
	ok( false === strpos( Account::template( $old_card, 'emails/receipt.php' ), 'data-wp-' ), 'email templates are not decorated' );

	// Cancel through the module's request shape: only the subscription and order, plus the nonce.
	$fields = array(
		'subscription_id' => $sub,
		'order_id'        => (int) $order->get_id(),
	);
	wp_set_current_user( $other );
	$forged = call_ajax( 'cancel_membership', $other, $fields + array( 'nonce' => wp_create_nonce( 'cancel_membership_nonce' ) ) );
	ok( is_array( $forged ) && false === $forged['success'] && 'You cannot cancel this membership.' === $forged['data']['message'], 'another learner is refused: ' . wp_json_encode( $forged ) );
	ok( 'active' === status_of( $sub ), 'the refused request changed nothing' );
	ok( '-1' === trim( (string) call_ajax( 'cancel_membership', $other, $fields + array( 'nonce' => $cancel_nonce ) ) ), "the owner's nonce does not work for another learner" );

	$own = call_ajax( 'cancel_membership', $owner, $fields + array( 'nonce' => $cancel_nonce ) );
	ok( is_array( $own ) && true === $own['success'], 'the owner can cancel with the state nonce: ' . wp_json_encode( $own ) );
	ok( 'cancelled' === status_of( $sub ), 'owner cancel stored' );

	ok( array() === $warnings, "no PHP warnings from the plugin:\n" . implode( "\n", $warnings ) );
	fwrite( STDOUT, "OK account-frontend-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, 'FAIL: ' . $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	wp_set_current_user( 0 );
	foreach ( array_unique( array_map( 'intval', $sub_ids ) ) as $leftover ) {
		SubscriptionManager::clear_scheduled_renewal_actions( $leftover );
		delete_option( 'ohmylms_lock_subscription-' . $leftover );
		$wpdb->delete( $wpdb->comments, array( 'comment_post_ID' => $leftover ), array( '%d' ) );
		wp_delete_post( $leftover, true );
	}
	foreach ( array_unique( array_filter( array_map( 'intval', $post_ids ) ) ) as $leftover ) {
		$order_items = $wpdb->get_col( $wpdb->prepare( "SELECT order_item_id FROM {$wpdb->prefix}ohmylms_order_items WHERE order_id = %d", $leftover ) );
		foreach ( $order_items as $item_id ) {
			$wpdb->delete( $wpdb->prefix . 'ohmylms_order_itemmeta', array( 'order_item_id' => $item_id ), array( '%d' ) );
		}
		$wpdb->delete( $wpdb->prefix . 'ohmylms_order_items', array( 'order_id' => $leftover ), array( '%d' ) );
		$wpdb->delete( $wpdb->comments, array( 'comment_post_ID' => $leftover ), array( '%d' ) );
		wp_delete_post( $leftover, true );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $user_ids as $user_id ) {
		foreach ( array( 'ohmylms_user_membership', 'ohmylms_user_enrollment' ) as $table ) {
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . $table ) ) ) {
				$wpdb->delete( $wpdb->prefix . $table, array( 'user_id' => $user_id ), array( '%d' ) );
			}
		}
		wp_delete_user( $user_id );
	}
}

exit( $exit_code );
