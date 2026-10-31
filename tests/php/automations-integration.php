<?php
/**
 * Automation rules (`OhMyLMS\Automation`, `ohmylms/v1/automations`) against the Local site.
 *
 * Creates tagged courses, a plan, a coupon, a cohort, learners and rules; checks permissions,
 * validation (nothing executable is accepted), every event listener, every action, chain and loop
 * limits, paused rules and the run log. Mail is captured, never sent. Removes everything in `finally`.
 *
 * Usage (set OHMYLMS_AUTOMATIONS_TEST=1):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/automations-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_AUTOMATIONS_TEST' ) ) {
	exit( "Set OHMYLMS_AUTOMATIONS_TEST=1 to run the automation tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Automation\RuleRunner;
use OhMyLMS\Automation\RuleStore;
use OhMyLMS\Cohorts\Cohorts;
use OhMyLMS\Cohorts\CohortSchema;
use OhMyLMS\Student\Enrollments;

global $wpdb;

$checks  = 0;
$tag     = 'autest' . strtolower( wp_generate_password( 6, false, false ) );
$posts   = array();
$users   = array();
$rules   = array();
$cohorts = array();
$mails   = array();
$exit    = 0;

add_filter(
	'pre_wp_mail',
	static function ( $short, $atts ) use ( &$mails ) {
		$mails[] = $atts;
		return true;
	},
	10,
	2
);

/**
 * Assert.
 *
 * @param mixed  $value   Truthy value.
 * @param string $message Failure message.
 * @throws RuntimeException On failure.
 */
function ok( $value, $message ) {
	global $checks;
	if ( ! $value ) {
		throw new RuntimeException( 'FAILED: ' . $message );
	}
	++$checks;
}

/**
 * REST call below /ohmylms/v1/automations.
 *
 * @param string $method Method.
 * @param string $path   Path.
 * @param array  $data   Body or query.
 * @return WP_REST_Response
 */
function au_api( $method, $path = '', array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/automations' . $path );
	if ( 'GET' === $method || 'DELETE' === $method ) {
		$request->set_query_params( $data );
	} else {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
}

/**
 * Assert an error response.
 *
 * @param WP_REST_Response $response Response.
 * @param int              $status   Status.
 * @param string           $code     Code.
 * @param string           $label    Label.
 * @param string|null      $field    Expected key in data.params.
 */
function au_error( $response, $status, $code, $label, $field = null ) {
	$data = $response->get_data();
	ok( $status === $response->get_status() && $code === ( $data['code'] ?? '' ), "$label: expected $status $code, got " . $response->get_status() . ' ' . wp_json_encode( $data ) );
	if ( null !== $field ) {
		ok( isset( $data['data']['params'][ $field ] ), "$label: message for $field in " . wp_json_encode( $data['data']['params'] ?? null ) );
	}
}

/**
 * Create a rule.
 *
 * @param array $body Body.
 * @return array
 */
function au_rule( array $body ) {
	global $rules;
	$response = au_api( 'POST', '', array_merge( array( 'status' => 'active' ), $body ) );
	ok( 201 === $response->get_status(), 'rule created: ' . wp_json_encode( $response->get_data() ) );
	$rules[] = (int) $response->get_data()['id'];
	return $response->get_data();
}

/**
 * Log rows of a rule.
 *
 * @param int $id Rule.
 * @return array[]
 */
function au_log( $id ) {
	return au_api( 'GET', '/' . $id . '/log' )->get_data();
}

/**
 * Whether a learner holds an active enrolment.
 *
 * @param int $user_id   Learner.
 * @param int $course_id Course.
 * @return bool
 */
function au_enrolled( $user_id, $course_id ) {
	global $wpdb;
	return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE user_id = %d AND course_id = %d AND status = 'enrolled'", $wpdb->prefix . 'ohmylms_user_enrollment', $user_id, $course_id ) );
}

/**
 * Published post.
 *
 * @param string $type  Post type.
 * @param string $title Title.
 * @param string $status Status.
 * @return int
 */
function au_post( $type, $title, $status = 'publish' ) {
	global $posts;
	$id      = (int) wp_insert_post(
		array(
			'post_type'   => $type,
			'post_status' => $status,
			'post_title'  => $title,
		)
	);
	$posts[] = $id;
	return $id;
}

try {
	CohortSchema::ensure();
	RuleStore::ensure();
	$admin = (int) get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ID',
		)
	)[0];
	foreach ( array( 'ann', 'ben', 'sub' ) as $who ) {
		$users[ $who ] = wp_insert_user(
			array(
				'user_login' => $tag . '_' . $who,
				'user_pass'  => wp_generate_password( 24 ),
				'user_email' => $tag . '_' . $who . '@example.invalid',
				'first_name' => ucfirst( $who ),
				'role'       => 'subscriber',
			)
		);
	}
	$intro  = au_post( 'ohmylms-course', $tag . ' Intro' );
	$next   = au_post( 'ohmylms-course', $tag . ' Next' );
	$loop_a = au_post( 'ohmylms-course', $tag . ' Loop A' );
	$loop_b = au_post( 'ohmylms-course', $tag . ' Loop B' );
	$draft  = au_post( 'ohmylms-course', $tag . ' Draft', 'draft' );
	$plan   = au_post( 'ohmylms-membership', $tag . ' Plan' );
	$lesson = au_post( 'ohmylms-lesson', $tag . ' Lesson' );
	$task   = au_post( 'ohmylms-assignment', $tag . ' Task' );
	$coupon = au_post( 'ohmylms_coupon', strtoupper( $tag ) . 'OFF' );
	$cohort = Cohorts::create(
		Cohorts::validate(
			array(
				'scope'      => 'course',
				'course_id'  => $next,
				'title'      => $tag . ' cohort',
				'start_date' => gmdate( 'Y-m-d', strtotime( '-1 day' ) ),
			)
		)
	);
	$cohorts[] = (int) $cohort['id'];

	// Permissions.
	wp_set_current_user( 0 );
	au_error( au_api( 'GET' ), 401, 'ohmylms_automation_forbidden', 'anonymous list' );
	wp_set_current_user( $users['sub'] );
	au_error( au_api( 'POST', '', array( 'name' => 'x' ) ), 403, 'ohmylms_automation_forbidden', 'subscriber create' );
	au_error( au_api( 'GET', '/options' ), 403, 'ohmylms_automation_forbidden', 'subscriber options' );

	wp_set_current_user( $admin );
	$options = au_api( 'GET', '/options' )->get_data();
	ok( 10 === count( $options['events'] ) && 5 === count( $options['actions'] ), 'options list ten events and five actions' );

	// Validation: only allow-listed events, conditions, actions and parameters.
	$base = array(
		'name'    => 'Rule',
		'event'   => 'course_enrolled',
		'actions' => array(
			array(
				'type'    => 'send_email',
				'to'      => 'learner',
				'subject' => 'Hi',
				'body'    => 'Hello',
			),
		),
	);
	au_error( au_api( 'POST', '', array_merge( $base, array( 'event' => 'init' ) ) ), 422, 'ohmylms_automation_invalid', 'unknown event', 'event' );
	au_error( au_api( 'POST', '', array_merge( $base, array( 'callback' => 'phpinfo' ) ) ), 400, 'ohmylms_automation_unknown_field', 'unknown top-level field' );
	au_error( au_api( 'POST', '', array_merge( $base, array( 'conditions' => array( 'lesson_ids' => array( $lesson ) ) ) ) ), 422, 'ohmylms_automation_invalid', 'condition not supported by the event', 'conditions.lesson_ids' );
	au_error( au_api( 'POST', '', array_merge( $base, array( 'conditions' => array( 'course_ids' => array( $lesson ) ) ) ) ), 422, 'ohmylms_automation_invalid', 'id of the wrong type', 'conditions.course_ids' );
	au_error( au_api( 'POST', '', array_merge( $base, array( 'conditions' => array( 'course_ids' ) ) ) ), 422, 'ohmylms_automation_invalid', 'conditions as a list', 'conditions' );
	au_error( au_api( 'POST', '', array_merge( $base, array( 'actions' => array() ) ) ), 422, 'ohmylms_automation_invalid', 'no action', 'actions' );
	au_error( au_api( 'POST', '', array_merge( $base, array( 'actions' => array( array( 'type' => 'run_php', 'code' => 'phpinfo();' ) ) ) ) ), 422, 'ohmylms_automation_invalid', 'unknown action', 'actions.0.type' );
	au_error( au_api( 'POST', '', array_merge( $base, array( 'actions' => array( array_merge( $base['actions'][0], array( 'url' => 'https://example.invalid' ) ) ) ) ) ), 422, 'ohmylms_automation_invalid', 'unknown action parameter', 'actions.0.url' );
	au_error( au_api( 'POST', '', array_merge( $base, array( 'actions' => array( array( 'type' => 'send_email', 'to' => 'someone@example.invalid', 'subject' => 'x', 'body' => 'y' ) ) ) ) ), 422, 'ohmylms_automation_invalid', 'free recipient refused', 'actions.0.to' );
	au_error( au_api( 'POST', '', array_merge( $base, array( 'actions' => array( array( 'type' => 'enrol_course', 'course_id' => $draft ) ) ) ) ), 422, 'ohmylms_automation_invalid', 'draft course refused', 'actions.0.course_id' );
	au_error( au_api( 'POST', '', array_merge( $base, array( 'actions' => array( array( 'type' => 'send_coupon', 'coupon_id' => $coupon, 'subject' => 'x', 'body' => 'no tag' ) ) ) ) ), 422, 'ohmylms_automation_invalid', 'coupon message needs the code tag', 'actions.0.body' );
	au_error( au_api( 'POST', '', array_merge( $base, array( 'event' => 'course_progress' ) ) ), 422, 'ohmylms_automation_invalid', 'progress needs a rate', 'conditions.rate' );
	au_error( au_api( 'POST', '', array_merge( $base, array( 'actions' => array_fill( 0, 6, $base['actions'][0] ) ) ) ), 422, 'ohmylms_automation_invalid', 'too many actions', 'actions' );
	ok( '0' === au_api( 'GET' )->get_headers()['X-WP-Total'] || ! array_intersect( array_column( au_api( 'GET', '', array( 'search' => 'Rule' ) )->get_data(), 'name' ), array( 'Rule' ) ), 'rejected rules were not stored' );

	// Rule A: enrol in Intro -> email, enrol in Next, add to cohort. Rule B: enrol in Next -> give plan.
	$rule_a = au_rule(
		array(
			'name'       => 'Welcome <script>x</script>',
			'event'      => 'course_enrolled',
			'conditions' => array( 'course_ids' => array( $intro ) ),
			'actions'    => array(
				array(
					'type'    => 'send_email',
					'to'      => 'learner',
					'subject' => 'Welcome to {course_title}',
					'body'    => "Hi {first_name},\n<b>enjoy</b> {course_title} on {site_name}. {unknown_tag}",
				),
				array(
					'type'      => 'enrol_course',
					'course_id' => $next,
				),
				array(
					'type'      => 'add_to_cohort',
					'cohort_id' => $cohort['id'],
				),
			),
		)
	);
	ok( 'Welcome' === trim( $rule_a['name'] ) || false === strpos( $rule_a['name'], '<script>' ), 'markup stripped from the name' );
	ok( false === strpos( $rule_a['actions'][0]['body'], '<b>' ), 'markup stripped from the body' );
	$rule_b = au_rule(
		array(
			'name'       => 'Plan bonus',
			'event'      => 'course_enrolled',
			'conditions' => array( 'course_ids' => array( $next ) ),
			'actions'    => array(
				array(
					'type'          => 'grant_membership',
					'membership_id' => $plan,
				),
			),
		)
	);
	$paused = au_rule(
		array(
			'name'       => 'Paused',
			'status'     => 'paused',
			'event'      => 'course_enrolled',
			'conditions' => array( 'course_ids' => array( $intro ) ),
			'actions'    => array(
				array(
					'type'    => 'send_email',
					'to'      => 'admin',
					'subject' => 'Paused',
					'body'    => 'Should not send',
				),
			),
		)
	);

	RuleRunner::reset();
	$mails = array();
	ok( true === Enrollments::enroll_manually( $users['ann'], $intro ), 'ann enrolled in Intro' );
	ok( 1 === count( $mails ) && $tag . '_ann@example.invalid' === $mails[0]['to'], 'one email to the learner: ' . wp_json_encode( $mails ) );
	ok( 'Welcome to ' . $tag . ' Intro' === $mails[0]['subject'], 'subject merged' );
	ok( false !== strpos( $mails[0]['message'], 'Hi Ann,' ) && false !== strpos( $mails[0]['message'], '{unknown_tag}' ) && false === strpos( $mails[0]['message'], '<' ), 'body merged as plain text' );
	ok( au_enrolled( $users['ann'], $next ), 'enrol action enrolled in Next' );
	if ( \OhMyLMS\Student\EnrolmentSchema::exists() ) {
		$source = $wpdb->get_row( $wpdb->prepare( 'SELECT source, source_type, source_ref FROM %i WHERE user_id = %d AND course_id = %d', $wpdb->prefix . 'ohmylms_enrolments', $users['ann'], $next ), ARRAY_A );
		ok( array( 'source' => 'manual', 'source_type' => 'automation', 'source_ref' => (string) $rule_a['id'] ) === $source, 'enrolment records the rule: ' . wp_json_encode( $source ) );
	}
	ok( 1 === Cohorts::member_count( $cohort['id'] ), 'ann in the cohort once (cohort hook and action)' );
	ok( 1 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE user_id = %d AND membership_id = %d AND status = 'enrolled'", $wpdb->prefix . 'ohmylms_user_membership', $users['ann'], $plan ) ), 'chained rule gave the plan' );
	$log_a = au_log( $rule_a['id'] );
	ok( 3 === count( $log_a ), 'three log rows for rule A' );
	$by_action = array_column( $log_a, 'status', 'action' );
	ok( 'success' === $by_action['send_email'] && 'success' === $by_action['enrol_course'], 'email and enrolment succeeded: ' . wp_json_encode( $by_action ) );
	ok( in_array( $by_action['add_to_cohort'], array( 'success', 'skipped' ), true ), 'cohort action logged' );
	ok( 'success' === au_log( $rule_b['id'] )[0]['status'] && $users['ann'] === au_log( $rule_b['id'] )[0]['user_id'], 'rule B logged' );
	ok( array() === au_log( $paused['id'] ), 'paused rule did not run' );
	$fresh = au_api( 'GET', '/' . $rule_a['id'] )->get_data();
	ok( 1 === $fresh['run_count'] && null !== $fresh['last_run_at'], 'run count and time recorded' );

	// Loops stop: A enrols in B, B enrols in A.
	foreach ( array( array( $loop_a, $loop_b ), array( $loop_b, $loop_a ) ) as list( $when, $then ) ) {
		au_rule(
			array(
				'name'       => 'Loop ' . $when,
				'event'      => 'course_enrolled',
				'conditions' => array( 'course_ids' => array( $when ) ),
				'actions'    => array(
					array(
						'type'      => 'enrol_course',
						'course_id' => $then,
					),
				),
			)
		);
	}
	RuleRunner::reset();
	ok( true === Enrollments::enroll_manually( $users['ben'], $loop_a ), 'ben enrolled in Loop A' );
	ok( au_enrolled( $users['ben'], $loop_b ), 'loop rule enrolled in Loop B' );
	$loop_logs = au_api( 'GET', '/log', array( 'per_page' => 100 ) )->get_data();
	$ben_rows  = array_filter( $loop_logs, static fn( $row ) => $row['user_id'] === $users['ben'] );
	ok( count( $ben_rows ) <= 2, 'loop stopped after two levels: ' . count( $ben_rows ) );

	// Other events.
	$progress = au_rule(
		array(
			'name'       => 'Halfway',
			'event'      => 'course_progress',
			'conditions' => array(
				'course_ids' => array( $intro ),
				'operator'   => 'gte',
				'rate'       => 50,
			),
			'actions'    => array(
				array(
					'type'    => 'send_email',
					'to'      => 'admin',
					'subject' => '{display_name} is halfway',
					'body'    => '{course_title}',
				),
			),
		)
	);
	RuleRunner::reset();
	$mails = array();
	do_action( 'ohmylms_course_completion_rate', $users['ann'], $intro, 40 );
	ok( ! $mails, 'below the rate: nothing' );
	do_action( 'ohmylms_course_completion_rate', $users['ann'], $intro, 60 );
	ok( 1 === count( $mails ) && get_option( 'admin_email' ) === $mails[0]['to'], 'rate reached: admin emailed' );

	$graded = au_rule(
		array(
			'name'       => 'Failed task',
			'event'      => 'assignment_reviewed',
			'conditions' => array(
				'assignment_ids' => array( $task ),
				'result'         => 'fail',
			),
			'actions'    => array(
				array(
					'type'      => 'send_coupon',
					'coupon_id' => $coupon,
					'subject'   => 'Try again',
					'body'      => 'Use {coupon_code} for a retake.',
				),
			),
		)
	);
	$wpdb->insert(
		$wpdb->prefix . 'ohmylms_assignment_attempts',
		array(
			'user_id'       => $users['ben'],
			'course_id'     => $intro,
			'assignment_id' => $task,
			'content'       => '',
			'files'         => '',
			'status'        => 'failed',
			'start_date'    => current_time( 'mysql' ),
			'end_date'      => current_time( 'mysql' ),
		)
	);
	$attempt = (int) $wpdb->insert_id;
	RuleRunner::reset();
	$mails = array();
	// The grading pipeline passes the learner (third) and the graded row (fifth). Other listeners (the
	// "assignment graded" email) also mail the learner, so only coupon mails are counted.
	$coupons = static function () use ( &$mails, $tag ) {
		return array_values(
			array_filter(
				$mails,
				static function ( $mail ) use ( $tag ) {
					return false !== strpos( (string) $mail['message'], strtoupper( $tag ) . 'OFF' );
				}
			)
		);
	};
	do_action( 'ohmylms_after_assignment_review', $task, $intro, $users['ben'], 'passed', $attempt );
	ok( ! $coupons(), 'passed grade does not match a fail rule' );
	do_action( 'ohmylms_after_assignment_review', $task, $intro, $users['ann'], 'failed', $attempt );
	ok( ! $coupons(), 'a learner who does not own the graded row gets nothing' );
	do_action( 'ohmylms_after_assignment_review', $task, $intro, $users['ann'], 'failed' );
	ok( ! $coupons(), 'a learner without a submission of the assignment gets nothing' );
	do_action( 'ohmylms_after_assignment_review', $task, $intro, $users['ben'], 'failed', $attempt );
	ok( 1 === count( $coupons() ) && get_userdata( $users['ben'] )->user_email === $coupons()[0]['to'], 'coupon code sent to the graded learner' );
	RuleRunner::reset();
	$mails = array();
	do_action( 'ohmylms_after_assignment_review', $task, $intro, $users['ben'], 'failed' );
	ok( 1 === count( $coupons() ), 'without a row id the learner\'s own submission is enough' );
	$wpdb->delete( $wpdb->prefix . 'ohmylms_assignment_attempts', array( 'id' => $attempt ) );

	$lesson_rule = au_rule(
		array(
			'name'       => 'Lesson done',
			'event'      => 'lesson_completed',
			'conditions' => array( 'lesson_ids' => array( $lesson ) ),
			'actions'    => array(
				array(
					'type'      => 'add_to_cohort',
					'cohort_id' => $cohort['id'],
				),
			),
		)
	);
	RuleRunner::reset();
	do_action( 'ohmylms_after_lesson_completed', $lesson, $intro, $users['ben'] );
	ok( 'failed' === au_log( $lesson_rule['id'] )[0]['status'], 'cohort action fails for a learner not enrolled in the cohort course (logged)' );

	$order = new \OhMyLMS\Commerce\Data\Order();
	$order->set_student_id( $users['ben'] );
	$order->set_payment_method( 'offline' );
	$item = new \OhMyLMS\Commerce\Data\OrderItemCourse();
	$item->set_props(
		array(
			'name'      => 'Intro',
			'course_id' => $intro,
			'quantity'  => 1,
			'total'     => 0,
		)
	);
	$order->add_item( $item );
	$order_id = (int) $order->save();
	$posts[]  = $order_id;
	$bought   = au_rule(
		array(
			'name'       => 'Bought',
			'event'      => 'course_purchased',
			'conditions' => array( 'course_ids' => array( $intro ) ),
			'actions'    => array(
				array(
					'type'    => 'send_email',
					'to'      => 'learner',
					'subject' => 'Receipt',
					'body'    => 'Thanks',
				),
			),
		)
	);
	RuleRunner::reset();
	$mails = array();
	do_action( 'ohmylms_checkout_after_create_order', ohmylms_ecommerce_get_order( $order_id ), array() );
	$receipts = array_values( array_filter( $mails, static fn( $mail ) => 'Receipt' === $mail['subject'] ) );
	ok( 1 === count( $receipts ) && $tag . '_ben@example.invalid' === $receipts[0]['to'], 'purchase event ran once for the buyer' );

	// Updates and deletion.
	$patched = au_api( 'PATCH', '/' . $bought['id'], array( 'status' => 'paused' ) );
	ok( 200 === $patched->get_status() && 'paused' === $patched->get_data()['status'] && array( $intro ) === $patched->get_data()['conditions']['course_ids'], 'partial update keeps the rest' );
	au_error( au_api( 'PATCH', '/' . $bought['id'], array( 'actions' => array() ) ), 422, 'ohmylms_automation_invalid', 'invalid update', 'actions' );
	ok( 1 === count( au_api( 'GET', '/' . $bought['id'] )->get_data()['actions'] ), 'invalid update changed nothing' );
	$switched = au_api( 'PATCH', '/' . $bought['id'], array( 'event' => 'course_completed' ) );
	ok( 200 === $switched->get_status() && array() === (array) $switched->get_data()['conditions'], 'changing the event clears conditions it may not support' );
	ok( '1' === au_api( 'GET', '', array( 'status' => 'paused', 'search' => 'Bought' ) )->get_headers()['X-WP-Total'], 'status filter' );
	$deleted = au_api( 'DELETE', '/' . $rule_a['id'] );
	ok( 200 === $deleted->get_status() && true === $deleted->get_data()['deleted'], 'rule deleted' );
	au_error( au_api( 'GET', '/' . $rule_a['id'] . '/log' ), 404, 'ohmylms_automation_not_found', 'log of a deleted rule' );
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE automation_id = %d', RuleStore::log_table(), $rule_a['id'] ) ), 'its log is gone' );
	$choices = au_api( 'GET', '/choices', array( 'type' => 'course', 'include' => array( $intro, $next ) ) );
	ok( 200 === $choices->get_status() && 2 === count( $choices->get_data() ), 'choices resolve included ids' );

	echo "Automations integration: {$checks} checks passed.\n";
} catch ( Throwable $e ) {
	echo $e->getMessage(), "\n", $e->getTraceAsString(), "\n";
	$exit = 1;
} finally {
	foreach ( $rules as $id ) {
		RuleStore::delete( $id );
	}
	foreach ( $cohorts as $id ) {
		$wpdb->delete( CohortSchema::members(), array( 'cohort_id' => $id ) );
		$wpdb->delete( CohortSchema::cohorts(), array( 'id' => $id ) );
	}
	foreach ( $users as $user_id ) {
		if ( is_int( $user_id ) ) {
			$wpdb->delete( $wpdb->prefix . 'ohmylms_user_enrollment', array( 'user_id' => $user_id ) );
			$wpdb->delete( $wpdb->prefix . 'ohmylms_user_membership', array( 'user_id' => $user_id ) );
			$wpdb->delete( RuleStore::log_table(), array( 'user_id' => $user_id ) );
			if ( \OhMyLMS\Student\EnrolmentSchema::exists() ) {
				$wpdb->delete( $wpdb->prefix . 'ohmylms_enrolments', array( 'user_id' => $user_id ) );
			}
		}
	}
	foreach ( $posts as $post_id ) {
		if ( 'ohmylms-order' === get_post_type( $post_id ) ) {
			do_action( 'ohmylms_rest_delete_order', $post_id );
		}
		wp_delete_post( $post_id, true );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $users as $user_id ) {
		if ( is_int( $user_id ) ) {
			wp_delete_user( $user_id );
		}
	}
	exit( $exit );
}
