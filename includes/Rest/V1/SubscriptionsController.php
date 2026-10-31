<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * SubscriptionsController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Commerce\SubscriptionManager;
use WP_Error;
use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * SubscriptionsController class.
 *
 * Read-mostly REST API for membership subscriptions under `ohmylms/v1/subscriptions`. See
 * docs/api/commerce.md ("Subscriptions") for the contract. Subscriptions are created by checkout and
 * renewed by the Action Scheduler job in SubscriptionManager; this controller only lists them,
 * shows one, and runs the status changes the manager implements (pause, resume, cancel).
 *
 * Authentication is WordPress core's: cookie authentication requires the `X-WP-Nonce` header
 * (verified by rest_cookie_check_errors()); application passwords also work. Every route requires
 * the CAPABILITY below.
 *
 * @since 1.0.0
 */
class SubscriptionsController extends RestController {

	/**
	 * Capability required by every subscription route.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Subscription post type.
	 */
	const POST_TYPE = 'ohmylms-subscription';

	/**
	 * Subscription statuses (post status without the `ohmylms-` prefix).
	 */
	const STATUSES = array( 'pending', 'active', 'on-hold', 'pending-cancel', 'cancelled', 'expired' );

	/**
	 * Statuses each action may start from. The target is fixed per action.
	 */
	const TRANSITIONS = array(
		'pause'  => array( 'active' ),
		'resume' => array( 'on-hold', 'pending-cancel' ),
		'cancel' => array( 'pending', 'active', 'on-hold', 'pending-cancel' ),
	);

	/**
	 * Most related orders and notes returned with one subscription.
	 */
	const MAX_RELATED = 50;

	/**
	 * Seconds after which an action lock is treated as abandoned.
	 */
	const LOCK_TTL = 60;

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ohmylms/v1';

	/**
	 * The base route for subscription endpoints.
	 *
	 * @var string
	 */
	protected $rest_base = 'subscriptions';

	/**
	 * Register the routes. Nothing is registered when the commerce package is not loaded.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		if ( ! class_exists( SubscriptionManager::class ) ) {
			return;
		}

		$base = '/' . $this->rest_base;
		$item = $base . '/(?P<id>[\d]+)';
		$id   = array(
			'description' => __( 'Unique identifier for the subscription.', 'ohmylms' ),
			'type'        => 'integer',
			'minimum'     => 1,
		);

		register_rest_route(
			$this->namespace,
			$base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			$item,
			array(
				'args'   => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			$item . '/(?P<action>' . implode( '|', array_keys( self::TRANSITIONS ) ) . ')',
			array(
				'args' => array(
					'id'     => $id,
					'action' => array(
						'description' => __( 'Status change to run.', 'ohmylms' ),
						'type'        => 'string',
						'enum'        => array_keys( self::TRANSITIONS ),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'run_action' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => array(
						'confirm' => array(
							'description' => __( 'Must be true: the caller confirmed the change with a person.', 'ohmylms' ),
							'type'        => 'boolean',
							'default'     => false,
						),
					),
				),
			)
		);
	}

	/**
	 * Check the capability shared by every route.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message used when access is denied.
	 * @return true|WP_Error
	 */
	private function require_capability( $code, $message ) {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return new WP_Error( $code, $message, array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Check if the current user can list subscriptions.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return $this->require_capability( 'ohmylms_subscription_cannot_view', __( 'Sorry, you are not allowed to view subscriptions.', 'ohmylms' ) );
	}

	/**
	 * Check if the current user can read one subscription.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		return $this->require_capability( 'ohmylms_subscription_cannot_view', __( 'Sorry, you are not allowed to view subscriptions.', 'ohmylms' ) );
	}

	/**
	 * Check if the current user can change the status of a subscription.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		return $this->require_capability( 'ohmylms_subscription_cannot_edit', __( 'Sorry, you are not allowed to change subscriptions.', 'ohmylms' ) );
	}

	/**
	 * Load a subscription post or return a 404 error.
	 *
	 * @param int $id Subscription post ID.
	 * @return WP_Post|WP_Error
	 */
	private function get_subscription_post( $id ) {
		$post = get_post( absint( $id ) );
		if ( ! $post || self::POST_TYPE !== $post->post_type || ! in_array( $this->status_of( $post ), self::STATUSES, true ) ) {
			return new WP_Error( 'ohmylms_subscription_not_found', __( 'Subscription not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		return $post;
	}

	/**
	 * Subscription status without the `ohmylms-` prefix.
	 *
	 * @param WP_Post $post Subscription post.
	 * @return string
	 */
	private function status_of( WP_Post $post ) {
		return 0 === strpos( $post->post_status, 'ohmylms-' ) ? substr( $post->post_status, 8 ) : (string) $post->post_status;
	}

	/**
	 * Actions the subscription can run now.
	 *
	 * @param string $status Current status.
	 * @return string[]
	 */
	private function allowed_actions( $status ) {
		$allowed = array();
		foreach ( self::TRANSITIONS as $action => $from ) {
			if ( in_array( $status, $from, true ) ) {
				$allowed[] = $action;
			}
		}

		return $allowed;
	}

	/**
	 * Get subscriptions.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		$page     = max( 1, (int) $request['page'] );
		$per_page = min( 100, max( 1, (int) $request['per_page'] ) );
		$status   = (string) $request['status'];
		$search   = trim( (string) $request['search'] );
		$args     = array(
			'post_type'              => self::POST_TYPE,
			'post_status'            => array_map(
				static function ( $value ) {
					return 'ohmylms-' . $value;
				},
				'any' === $status ? self::STATUSES : array( $status )
			),
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'orderby'                => 'id' === $request['orderby'] ? 'ID' : 'date',
			'order'                  => 'asc' === $request['order'] ? 'ASC' : 'DESC',
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
		);
		if ( '' !== $search ) {
			$ids              = $this->search_ids( $search );
			$args['post__in'] = $ids ? $ids : array( 0 );
		}

		$query = new WP_Query( $args );
		$total = (int) $query->found_posts;
		if ( 0 === $total && $page > 1 ) {
			$count = new WP_Query(
				array_merge(
					$args,
					array(
						'paged'          => 1,
						'posts_per_page' => 1,
						'fields'         => 'ids',
					)
				)
			);
			$total = (int) $count->found_posts;
		}
		$pages = (int) ceil( $total / $per_page );
		if ( $page > $pages && $total > 0 ) {
			return new WP_Error( 'ohmylms_subscription_invalid_page', __( 'The page number requested is larger than the number of pages available.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$this->prime( $query->posts );
		$data = array();
		foreach ( $query->posts as $post ) {
			$data[] = $this->summary( $post );
		}

		$response = rest_ensure_response( $data );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $pages );

		$base = add_query_arg( urlencode_deep( $request->get_query_params() ), rest_url( $this->namespace . '/' . $this->rest_base ) );
		if ( $page > 1 ) {
			$response->link_header( 'prev', add_query_arg( 'page', $page - 1, $base ) );
		}
		if ( $page < $pages ) {
			$response->link_header( 'next', add_query_arg( 'page', $page + 1, $base ) );
		}

		return $response;
	}

	/**
	 * Get one subscription.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$post = $this->get_subscription_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		return $this->prepare_item_for_response( $post, $request );
	}

	/**
	 * Pause, resume or cancel a subscription through SubscriptionManager.
	 *
	 * The request must carry `confirm: true`. The current status is checked against TRANSITIONS
	 * under a per-subscription lock, so an invalid or parallel request changes nothing.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function run_action( $request ) {
		$post = $this->get_subscription_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( true !== $request['confirm'] ) {
			return new WP_Error( 'ohmylms_subscription_confirm_required', __( 'Confirm the change first: send confirm=true after a person agreed to it.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$action = (string) $request['action'];
		$lock   = 'subscription-' . $post->ID;
		if ( ! $this->acquire_lock( $lock ) ) {
			return new WP_Error( 'ohmylms_subscription_busy', __( 'Another change to this subscription is running. Try again in a moment.', 'ohmylms' ), array( 'status' => 409 ) );
		}

		try {
			clean_post_cache( $post->ID );
			$post   = get_post( $post->ID );
			$status = $this->status_of( $post );
			if ( ! in_array( $status, self::TRANSITIONS[ $action ], true ) ) {
				return new WP_Error(
					'ohmylms_subscription_invalid_transition',
					/* translators: 1: action (pause, resume or cancel), 2: subscription status. */
					sprintf( __( 'A subscription cannot %1$s while it is %2$s.', 'ohmylms' ), $action, $status ),
					array(
						'status'          => 409,
						'current_status'  => $status,
						'allowed_actions' => $this->allowed_actions( $status ),
					)
				);
			}
			if ( 'resume' === $action && $this->has_ended( $post->ID ) ) {
				return new WP_Error( 'ohmylms_subscription_ended', __( 'This subscription has passed its end date and cannot be resumed.', 'ohmylms' ), array( 'status' => 409 ) );
			}

			if ( 'pause' === $action ) {
				$done = SubscriptionManager::mark_subscription_on_hold( $post->ID );
			} elseif ( 'resume' === $action ) {
				$done = SubscriptionManager::mark_subscription_active( $post->ID );
			} else {
				$done = SubscriptionManager::mark_subscription_cancelled( $post->ID, get_current_user_id() );
			}
			if ( ! $done ) {
				return new WP_Error( 'ohmylms_subscription_update_failed', __( 'The subscription could not be changed.', 'ohmylms' ), array( 'status' => 500 ) );
			}

			$subscription = function_exists( 'ohmylms_ecommerce_get_subscription' ) ? ohmylms_ecommerce_get_subscription( $post->ID ) : null;
			if ( 'resume' === $action ) {
				/**
				 * Fires after an administrator reactivated a subscription.
				 *
				 * @param \OhMyLMS\Commerce\Data\Subscription|null $subscription Subscription.
				 */
				do_action( 'ohmylms_update_subscription_status_to_active', $subscription );
			} elseif ( 'cancel' === $action ) {
				/**
				 * Fires after an administrator cancelled a subscription.
				 *
				 * @param \OhMyLMS\Commerce\Data\Subscription|null $subscription Subscription.
				 */
				do_action( 'ohmylms_update_subscription_status_to_cancelled', $subscription );
			}

			/**
			 * Fires after a subscription status was changed through the REST API.
			 *
			 * @param int    $subscription_id Subscription ID.
			 * @param string $action          pause, resume or cancel.
			 * @param string $previous        Status before the change.
			 */
			do_action( 'ohmylms_rest_subscription_action', $post->ID, $action, $status );
		} finally {
			$this->release_lock( $lock );
		}

		clean_post_cache( $post->ID );
		return $this->prepare_item_for_response( get_post( $post->ID ), $request );
	}

	/**
	 * Whether a fixed-term subscription is past its end date.
	 *
	 * @param int $id Subscription ID.
	 * @return bool
	 */
	private function has_ended( $id ) {
		$end = $this->date( get_post_meta( $id, '_schedule_end_date', true ) );

		return null !== $end && strtotime( $end ) <= current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Schedule dates are stored in site time.
	}

	/**
	 * Take a short, atomic lock (add_option() inserts on the unique option_name).
	 *
	 * @param string $name Lock name.
	 * @return bool True when the lock was taken.
	 */
	private function acquire_lock( $name ) {
		$key = 'ohmylms_lock_' . sanitize_key( $name );
		if ( add_option( $key, time(), '', 'no' ) ) {
			return true;
		}
		$taken = (int) get_option( $key );
		if ( $taken && ( time() - $taken ) > self::LOCK_TTL ) {
			update_option( $key, time(), 'no' );
			return true;
		}

		return false;
	}

	/**
	 * Release a lock taken with acquire_lock().
	 *
	 * @param string $name Lock name.
	 * @return void
	 */
	private function release_lock( $name ) {
		delete_option( 'ohmylms_lock_' . sanitize_key( $name ) );
	}

	/**
	 * Subscription IDs matching a search text: subscription or order number, customer name or
	 * email, or plan name.
	 *
	 * @param string $term Search text.
	 * @return int[] At most 1000 IDs.
	 */
	private function search_ids( $term ) {
		global $wpdb;

		$like   = '%' . $wpdb->esc_like( $term ) . '%';
		$number = ctype_digit( $term ) ? ' OR p.ID = %d OR o.meta_value = %s' : '';
		$params = array( $wpdb->posts, $wpdb->postmeta, $wpdb->users, $wpdb->postmeta, $wpdb->posts, $wpdb->postmeta, self::POST_TYPE, $like, $like, $like );
		if ( $number ) {
			$params[] = (int) $term;
			$params[] = $term;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery -- Prepared; $number is one of two fixed fragments whose placeholders are added to $params.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM %i p
				LEFT JOIN %i s ON s.post_id = p.ID AND s.meta_key = '_student_id'
				LEFT JOIN %i u ON u.ID = s.meta_value
				LEFT JOIN %i m ON m.post_id = p.ID AND m.meta_key = '_membership_id'
				LEFT JOIN %i plan ON plan.ID = m.meta_value
				LEFT JOIN %i o ON o.post_id = p.ID AND o.meta_key = '_original_order_id'
				WHERE p.post_type = %s AND ( u.display_name LIKE %s OR u.user_email LIKE %s OR plan.post_title LIKE %s{$number} )
				LIMIT 1000",
				$params
			)
		);
		// phpcs:enable

		return array_values( array_unique( array_map( 'intval', (array) $ids ) ) );
	}

	/**
	 * Warm the user and plan caches for a page of subscriptions (post meta is primed by WP_Query).
	 *
	 * @param WP_Post[] $posts Subscription posts.
	 * @return void
	 */
	private function prime( array $posts ) {
		$users = array();
		$plans = array();
		foreach ( $posts as $post ) {
			$users[] = absint( get_post_meta( $post->ID, '_student_id', true ) );
			$plans[] = absint( get_post_meta( $post->ID, '_membership_id', true ) );
		}
		$users = array_values( array_unique( array_filter( $users ) ) );
		$plans = array_values( array_unique( array_filter( $plans ) ) );
		if ( $users ) {
			cache_users( $users );
		}
		if ( $plans ) {
			_prime_post_caches( $plans, false, false );
		}
	}

	/**
	 * Format money as a decimal string with the shop's number of decimals.
	 *
	 * @param mixed $value Amount.
	 * @return string
	 */
	private function money( $value ) {
		return number_format( (float) $value, (int) ohmylms_get_price_decimals(), '.', '' );
	}

	/**
	 * Convert a stored schedule date (site time, `Y-m-d H:i:s`) to ISO 8601 without offset.
	 * Empty values and the `0` the manager stores for "no next payment" become null.
	 *
	 * @param mixed $value Stored value.
	 * @return string|null
	 */
	private function date( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value ) || 0 === strpos( $value, '0000-00-00' ) ) {
			return null;
		}

		return str_replace( ' ', 'T', $value );
	}

	/**
	 * Customer name as the legacy screens show it: first and last name, else the display name.
	 *
	 * @param \WP_User|false $user Customer.
	 * @return string
	 */
	private function customer_name( $user ) {
		if ( ! $user ) {
			return '';
		}
		$name = trim( $user->first_name . ' ' . $user->last_name );

		return '' !== $name ? $name : (string) $user->display_name;
	}

	/**
	 * Recurring amount with the subscription's tax rate applied, as the legacy detail shows it.
	 *
	 * @param int $id Subscription ID.
	 * @return string
	 */
	private function recurring_amount( $id ) {
		$amount = (float) get_post_meta( $id, '_recurring_amount', true );
		$rate   = (float) get_post_meta( $id, '_tax_rate', true );
		if ( $rate > 0 && class_exists( '\OhMyLMS_Tax_Calculator' ) ) {
			$taxed = \OhMyLMS_Tax_Calculator::get_instance()->calculate_tax( $rate, array( 'total' => $amount ) );
			if ( is_array( $taxed ) && isset( $taxed['total_with_tax'] ) ) {
				$amount = (float) $taxed['total_with_tax'];
			}
		}

		return $this->money( $amount );
	}

	/**
	 * A list row: no email, no gateway tokens.
	 *
	 * @param WP_Post $post Subscription post.
	 * @return array
	 */
	private function summary( WP_Post $post ) {
		$id       = (int) $post->ID;
		$status   = $this->status_of( $post );
		$user     = get_userdata( absint( get_post_meta( $id, '_student_id', true ) ) );
		$plan_id  = absint( get_post_meta( $id, '_membership_id', true ) );
		$plan     = $plan_id ? get_post( $plan_id ) : null;
		$period   = (string) get_post_meta( $id, '_billing_period', true );
		$currency = (string) get_post_meta( $id, '_order_currency', true );

		return array(
			'id'                => $id,
			'status'            => $status,
			'plan_id'           => $plan ? $plan_id : 0,
			'plan_name'         => $plan ? html_entity_decode( get_the_title( $plan ), ENT_QUOTES, 'UTF-8' ) : '',
			'customer_id'       => $user ? (int) $user->ID : 0,
			'customer_name'     => $this->customer_name( $user ),
			'start_date'        => $this->date( get_post_meta( $id, '_schedule_start_date', true ) ),
			'next_payment_date' => $this->date( get_post_meta( $id, '_schedule_next_payment_date', true ) ),
			'end_date'          => $this->date( get_post_meta( $id, '_schedule_end_date', true ) ),
			'billing_period'    => in_array( $period, array( 'day', 'week', 'month', 'year' ), true ) ? $period : '',
			'billing_interval'  => max( 1, (int) get_post_meta( $id, '_billing_interval', true ) ),
			'recurring_amount'  => $this->recurring_amount( $id ),
			'currency'          => '' !== $currency ? $currency : (string) get_ohmylms_currency(),
			'allowed_actions'   => $this->allowed_actions( $status ),
		);
	}

	/**
	 * Parent and renewal orders of a subscription, newest first, at most MAX_RELATED.
	 *
	 * @param WP_Post $post Subscription post.
	 * @return array[]
	 */
	private function related_orders( WP_Post $post ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Prepared and bounded.
		$renewals = $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM %i p INNER JOIN %i m ON m.post_id = p.ID AND m.meta_key = '_subscription_renewal_id' WHERE m.meta_value = %s AND p.post_type = %s ORDER BY p.post_date DESC, p.ID DESC LIMIT %d", $wpdb->posts, $wpdb->postmeta, (string) $post->ID, 'ohmylms-order', self::MAX_RELATED ) );
		$ids      = array();
		foreach ( (array) $renewals as $renewal ) {
			$ids[ (int) $renewal ] = 'renewal';
		}
		$parent = absint( get_post_meta( $post->ID, '_original_order_id', true ) );
		$parent = $parent ? $parent : (int) $post->post_parent;
		if ( $parent && ! isset( $ids[ $parent ] ) && count( $ids ) < self::MAX_RELATED ) {
			$ids[ $parent ] = 'parent';
		}
		if ( ! $ids ) {
			return array();
		}
		_prime_post_caches( array_keys( $ids ), false, true );

		$related = array();
		foreach ( $ids as $order_id => $relationship ) {
			$order = get_post( $order_id );
			if ( ! $order || 'ohmylms-order' !== $order->post_type ) {
				continue;
			}
			$currency  = (string) get_post_meta( $order_id, '_order_currency', true );
			$related[] = array(
				'id'           => (int) $order_id,
				'relationship' => $relationship,
				'status'       => 0 === strpos( $order->post_status, 'ohmylms-' ) ? substr( $order->post_status, 8 ) : (string) $order->post_status,
				'total'        => $this->money( get_post_meta( $order_id, '_order_total', true ) ),
				'currency'     => '' !== $currency ? $currency : (string) get_ohmylms_currency(),
				'date_created' => $this->date( $order->post_date ),
			);
		}

		return $related;
	}

	/**
	 * Notes of a subscription as plain text, newest first, at most MAX_RELATED.
	 *
	 * @param WP_Post $post Subscription post.
	 * @return array[]
	 */
	private function notes( WP_Post $post ) {
		$comments = get_comments(
			array(
				'post_id' => $post->ID,
				'type'    => 'subscription_note',
				'status'  => 'approve',
				'orderby' => 'comment_ID',
				'order'   => 'DESC',
				'number'  => self::MAX_RELATED,
			)
		);
		$notes    = array();
		foreach ( (array) $comments as $comment ) {
			$notes[] = array(
				'id'           => (int) $comment->comment_ID,
				'content'      => html_entity_decode( wp_strip_all_tags( (string) $comment->comment_content ), ENT_QUOTES, 'UTF-8' ),
				'added_by'     => (string) $comment->comment_author,
				'date_created' => $this->date( $comment->comment_date ),
			);
		}

		return $notes;
	}

	/**
	 * Prepare one subscription (the full detail) for the response.
	 *
	 * @param WP_Post         $item    Subscription post.
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {
		$post    = $item;
		$id      = (int) $post->ID;
		$data    = $this->summary( $post );
		$user    = get_userdata( $data['customer_id'] );
		$gateway = (string) get_post_meta( $id, '_payment_gateway_id', true );
		$title   = (string) get_post_meta( $id, '_payment_method_title', true );

		$data = array_merge(
			$data,
			array(
				'customer_email'       => $user ? (string) $user->user_email : '',
				'original_order_id'    => absint( get_post_meta( $id, '_original_order_id', true ) ),
				'payment_method'       => $gateway,
				'payment_method_title' => ( '' !== $title && 'N/A' !== $title ) ? $title : $gateway,
				'last_payment_date'    => $this->date( get_post_meta( $id, '_last_payment_date', true ) ),
				'cancelled_date'       => 'cancelled' === $data['status'] ? $this->date( get_post_meta( $id, '_end_date', true ) ) : null,
				'date_created'         => $this->date( $post->post_date ),
				'related_orders'       => $this->related_orders( $post ),
				'notes'                => $this->notes( $post ),
			)
		);

		$response = rest_ensure_response( $data );
		$response->add_links(
			array(
				'self'       => array( 'href' => rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $id ) ) ),
				'collection' => array( 'href' => rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ) ),
			)
		);

		/**
		 * Filters the subscription response.
		 *
		 * @param WP_REST_Response $response Response.
		 * @param WP_Post          $post     Subscription post.
		 * @param WP_REST_Request  $request  Request.
		 */
		return apply_filters( 'ohmylms_rest_prepare_subscription', $response, $post, $request );
	}

	/**
	 * Get the subscription schema (JSON Schema draft 4). Every field is read-only.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$field = static function ( $description, $type, $extra = array() ) {
			return array_merge(
				array(
					'description' => $description,
					'type'        => $type,
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				$extra
			);
		};
		$date  = static function ( $description ) use ( $field ) {
			return $field( $description, array( 'string', 'null' ), array( 'format' => 'date-time' ) );
		};
		$money = __( 'Decimal string with the shop\'s number of decimals.', 'ohmylms' );

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'subscription',
			'type'       => 'object',
			'properties' => array(
				'id'                   => $field( __( 'Unique identifier for the subscription.', 'ohmylms' ), 'integer' ),
				'status'               => $field( __( 'Subscription status.', 'ohmylms' ), 'string', array( 'enum' => self::STATUSES ) ),
				'plan_id'              => $field( __( 'Membership plan ID, 0 when the plan was deleted.', 'ohmylms' ), 'integer' ),
				'plan_name'            => $field( __( 'Membership plan name.', 'ohmylms' ), 'string' ),
				'customer_id'          => $field( __( 'Customer user ID, 0 when the user was deleted.', 'ohmylms' ), 'integer' ),
				'customer_name'        => $field( __( 'Customer first and last name, else the display name.', 'ohmylms' ), 'string' ),
				'start_date'           => $date( __( 'Start (site time).', 'ohmylms' ) ),
				'next_payment_date'    => $date( __( 'Next renewal (site time), null when none is due.', 'ohmylms' ) ),
				'end_date'             => $date( __( 'Scheduled end of a fixed-term subscription (site time).', 'ohmylms' ) ),
				'billing_period'       => $field( __( 'Billing period, empty when unknown.', 'ohmylms' ), 'string', array( 'enum' => array( '', 'day', 'week', 'month', 'year' ) ) ),
				'billing_interval'     => $field( __( 'Billing periods between renewals.', 'ohmylms' ), 'integer' ),
				'recurring_amount'     => $field( __( 'Renewal amount including tax.', 'ohmylms' ) . ' ' . $money, 'string' ),
				'currency'             => $field( __( 'ISO 4217 currency code.', 'ohmylms' ), 'string' ),
				'allowed_actions'      => $field(
					__( 'Status changes the subscription allows now.', 'ohmylms' ),
					'array',
					array(
						'items' => array(
							'type' => 'string',
							'enum' => array_keys( self::TRANSITIONS ),
						),
					)
				),
				'customer_email'       => $field( __( 'Customer email (detail only).', 'ohmylms' ), 'string' ),
				'original_order_id'    => $field( __( 'Order that started the subscription (detail only).', 'ohmylms' ), 'integer' ),
				'payment_method'       => $field( __( 'Gateway ID (detail only).', 'ohmylms' ), 'string' ),
				'payment_method_title' => $field( __( 'Gateway title (detail only).', 'ohmylms' ), 'string' ),
				'last_payment_date'    => $date( __( 'Last successful payment (detail only).', 'ohmylms' ) ),
				'cancelled_date'       => $date( __( 'When it was cancelled (detail only).', 'ohmylms' ) ),
				'date_created'         => $date( __( 'Creation time (detail only).', 'ohmylms' ) ),
				'related_orders'       => $field(
					__( 'Parent and renewal orders, newest first, at most 50 (detail only).', 'ohmylms' ),
					'array',
					array(
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'id'           => array( 'type' => 'integer' ),
								'relationship' => array(
									'type' => 'string',
									'enum' => array( 'parent', 'renewal' ),
								),
								'status'       => array( 'type' => 'string' ),
								'total'        => array( 'type' => 'string' ),
								'currency'     => array( 'type' => 'string' ),
								'date_created' => array( 'type' => array( 'string', 'null' ) ),
							),
						),
					)
				),
				'notes'                => $field(
					__( 'Plain text notes, newest first, at most 50 (detail only).', 'ohmylms' ),
					'array',
					array(
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'id'           => array( 'type' => 'integer' ),
								'content'      => array( 'type' => 'string' ),
								'added_by'     => array( 'type' => 'string' ),
								'date_created' => array( 'type' => array( 'string', 'null' ) ),
							),
						),
					)
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Get collection parameters.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params = array_intersect_key( parent::get_collection_params(), array_flip( array( 'page', 'per_page', 'search' ) ) );

		$params['per_page']['default']   = 20;
		$params['per_page']['maximum']   = 100;
		$params['search']['description'] = __( 'Subscription or order number, customer name or email, or plan name (at most 1000 matches).', 'ohmylms' );
		$params['search']['maxLength']   = 100;

		$params['status']  = array(
			'description' => __( 'Limit result set to subscriptions with a status.', 'ohmylms' ),
			'type'        => 'string',
			'default'     => 'any',
			'enum'        => array_merge( array( 'any' ), self::STATUSES ),
		);
		$params['orderby'] = array(
			'description' => __( 'Sort collection by attribute.', 'ohmylms' ),
			'type'        => 'string',
			'default'     => 'date',
			'enum'        => array( 'date', 'id' ),
		);
		$params['order']   = array(
			'description' => __( 'Order sort attribute ascending or descending.', 'ohmylms' ),
			'type'        => 'string',
			'default'     => 'desc',
			'enum'        => array( 'asc', 'desc' ),
		);

		return $params;
	}
}
