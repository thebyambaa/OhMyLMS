<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.
/**
 * Orders REST controller.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Commerce\Rest\V1;

use OhMyLMS\Commerce\Abstracts\RestController;
use WP_Error;
use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Orders under `ohmylms-commerce/v1/orders`. See docs/api/commerce.md for the contract.
 *
 * Orders are created by checkout only; this API lists, reads, changes the status of, annotates and
 * trashes them. Lists carry no email or address. Money is a decimal string in the order currency.
 *
 * @since 1.0.0
 */
class OrdersController extends RestController {

	/**
	 * Post type of orders.
	 */
	const POST_TYPE = 'ohmylms-order';

	/**
	 * Order statuses (without the `ohmylms-` post status prefix).
	 */
	const STATUSES = array( 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'failed', 'refunded' );

	/**
	 * Statuses an administrator may set by hand. `refunded` is reached only through a refund.
	 */
	const SETTABLE_STATUSES = array( 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'failed' );

	/**
	 * Allowed manual status changes: from => to[].
	 */
	const TRANSITIONS = array(
		'pending'    => array( 'processing', 'on-hold', 'completed', 'cancelled', 'failed' ),
		'processing' => array( 'on-hold', 'completed', 'cancelled', 'failed' ),
		'on-hold'    => array( 'pending', 'processing', 'completed', 'cancelled', 'failed' ),
		'failed'     => array( 'pending', 'processing', 'cancelled' ),
		'completed'  => array( 'cancelled' ),
		'cancelled'  => array( 'pending' ),
		'refunded'   => array(),
	);

	/**
	 * Maximum characters of an order note.
	 */
	const NOTE_MAX_LENGTH = 2000;

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'orders';

	/**
	 * Register the routes.
	 */
	public function register_routes() {
		$id_args = array(
			'id' => array(
				'description' => __( 'Unique identifier for the order.', 'ohmylms' ),
				'type'        => 'integer',
				'minimum'     => 1,
			),
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
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
			'/' . $this->rest_base . '/status-counts',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_status_counts' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'args'   => $id_args,
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'context' => $this->get_context_param( array( 'default' => 'view' ) ),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => array(
						'status' => array(
							'description' => __( 'The new order status.', 'ohmylms' ),
							'type'        => 'string',
							'enum'        => self::SETTABLE_STATUSES,
							'required'    => true,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/notes',
			array(
				'args' => $id_args,
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_notes' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_note' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => array(
						'note' => array(
							'description' => __( 'Plain text note. Markup is removed.', 'ohmylms' ),
							'type'        => 'string',
							'required'    => true,
							'minLength'   => 1,
							'maxLength'   => self::NOTE_MAX_LENGTH,
						),
					),
				),
			)
		);
	}

	/**
	 * Permission: list or read orders.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return $this->authorize( 'ohmylms_order_cannot_view', __( 'Sorry, you are not allowed to view orders.', 'ohmylms' ) );
	}

	/**
	 * Permission: read one order.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		return $this->authorize( 'ohmylms_order_cannot_view', __( 'Sorry, you are not allowed to view orders.', 'ohmylms' ) );
	}

	/**
	 * Permission: change an order.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		return $this->authorize( 'ohmylms_order_cannot_edit', __( 'Sorry, you are not allowed to edit orders.', 'ohmylms' ) );
	}

	/**
	 * Permission: trash an order.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		return $this->authorize( 'ohmylms_order_cannot_delete', __( 'Sorry, you are not allowed to delete orders.', 'ohmylms' ) );
	}

	/**
	 * Manual status changes allowed from a status.
	 *
	 * @param string $from Current status.
	 * @return string[]
	 */
	public static function allowed_transitions( $from ) {
		$map = apply_filters( 'ohmylms_order_allowed_status_transitions', self::TRANSITIONS );
		return isset( $map[ $from ] ) ? array_values( array_intersect( (array) $map[ $from ], self::SETTABLE_STATUSES ) ) : array();
	}

	/**
	 * Load an order post.
	 *
	 * @param int $id Order ID.
	 * @return WP_Post|WP_Error
	 */
	private function get_post( $id ) {
		$post = $id ? get_post( (int) $id ) : null;
		if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type || 'trash' === $post->post_status ) {
			return new WP_Error( 'ohmylms_order_not_found', __( 'Order not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		return $post;
	}

	/**
	 * Format money as a decimal string with the shop's number of decimals.
	 *
	 * @param mixed $value Amount.
	 * @return string
	 */
	private function money( $value ) {
		return number_format( (float) $value, ohmylms_get_price_decimals(), '.', '' );
	}

	/**
	 * Convert a post date (site time) to ISO 8601 without offset.
	 *
	 * @param string $value MySQL date.
	 * @return string|null
	 */
	private function date( $value ) {
		return ( $value && '0000-00-00 00:00:00' !== $value ) ? str_replace( ' ', 'T', $value ) : null;
	}

	/**
	 * Order IDs matching a search text (student name, email or order number).
	 *
	 * @param string $term Search text.
	 * @return int[] At most 1000 IDs.
	 */
	private function search_ids( $term ) {
		global $wpdb;

		$like = '%' . $wpdb->esc_like( $term ) . '%';
		$id   = ctype_digit( $term ) ? (int) $term : 0;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Prepared; joins the three meta rows once instead of a meta_query per field.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} f ON f.post_id = p.ID AND f.meta_key = '_first_name'
				LEFT JOIN {$wpdb->postmeta} l ON l.post_id = p.ID AND l.meta_key = '_last_name'
				LEFT JOIN {$wpdb->postmeta} e ON e.post_id = p.ID AND e.meta_key = '_email'
				WHERE p.post_type = %s AND ( CONCAT_WS( ' ', f.meta_value, l.meta_value ) LIKE %s OR e.meta_value LIKE %s OR p.ID = %d )
				LIMIT 1000",
				self::POST_TYPE,
				$like,
				$like,
				$id
			)
		);
		return array_map( 'intval', $ids );
	}

	/**
	 * Get orders.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$page     = max( 1, (int) $request['page'] );
		$per_page = min( 100, max( 1, (int) $request['per_page'] ) );
		$orderby  = array(
			'date' => 'date',
			'id'   => 'ID',
		);
		$args     = array(
			'post_type'              => self::POST_TYPE,
			'post_status'            => 'any' === $request['status'] ? array_map( array( $this, 'post_status' ), self::STATUSES ) : array( $this->post_status( $request['status'] ) ),
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
		);

		if ( 'total' === $request['orderby'] ) {
			$args['meta_key'] = '_order_total'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Sorting by a single key.
			$args['orderby']  = array(
				'meta_value_num' => strtoupper( $request['order'] ),
				'ID'             => 'DESC',
			);
		} else {
			$args['orderby'] = array(
				$orderby[ $request['orderby'] ] => strtoupper( $request['order'] ),
				'ID'                            => strtoupper( $request['order'] ),
			);
		}

		if ( '' !== (string) $request['payment_method'] ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Exact match on one key.
				array(
					'key'   => '_payment_method',
					'value' => $request['payment_method'],
				),
			);
		}

		$range = array();
		foreach ( array(
			'after'  => $request['after'],
			'before' => $request['before'],
		) as $key => $day ) {
			if ( '' === (string) $day ) {
				continue;
			}
			$parts = array_map( 'intval', explode( '-', $day ) );
			if ( 3 !== count( $parts ) || ! checkdate( $parts[1], $parts[2], $parts[0] ) ) {
				return new WP_Error(
					'ohmylms_order_invalid_date',
					__( 'Dates must be real calendar days in the form YYYY-MM-DD.', 'ohmylms' ),
					array(
						'status' => 400,
						'field'  => $key,
					)
				);
			}
			$range[ $key ] = array(
				'year'  => $parts[0],
				'month' => $parts[1],
				'day'   => $parts[2],
			);
		}
		if ( $range ) {
			if ( isset( $range['after'], $range['before'] ) && $request['after'] > $request['before'] ) {
				return new WP_Error(
					'ohmylms_order_invalid_date',
					__( 'The start date must not be after the end date.', 'ohmylms' ),
					array(
						'status' => 400,
						'field'  => 'before',
					)
				);
			}
			$args['date_query'] = array_merge( $range, array( 'inclusive' => true ) );
		}

		if ( '' !== (string) $request['search'] ) {
			$ids              = $this->search_ids( (string) $request['search'] );
			$args['post__in'] = $ids ? $ids : array( 0 );
		}

		$query = new WP_Query( $args );
		$total = (int) $query->found_posts;
		if ( $total < 1 && $page > 1 ) {
			// Past the last page WP_Query reports no total; count again without paging.
			$count_args = array_merge(
				$args,
				array(
					'paged'          => 1,
					'posts_per_page' => 1,
				)
			);
			$total      = (int) ( new WP_Query( $count_args ) )->found_posts;
		}
		$total_pages = (int) ceil( $total / $per_page );
		if ( $total > 0 && $page > $total_pages ) {
			return new WP_Error( 'ohmylms_order_invalid_page', __( 'The page number requested is larger than the number of pages available.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		// Post meta is primed by WP_Query, so these reads cost no extra queries.
		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = $this->prepare_response_for_collection( $this->prepare_item_for_response( $post, $request ) );
		}
		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', $total_pages );
		return $response;
	}

	/**
	 * Post status name of an order status.
	 *
	 * @param string $status Order status.
	 * @return string
	 */
	public function post_status( $status ) {
		return 'ohmylms-' . $status;
	}

	/**
	 * Order counts per status.
	 *
	 * @return WP_REST_Response
	 */
	public function get_status_counts() {
		global $wpdb;

		$counts = array_fill_keys( self::STATUSES, 0 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Prepared aggregate; WP_Query cannot group.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_status, COUNT(*) AS total FROM {$wpdb->posts} WHERE post_type = %s GROUP BY post_status", self::POST_TYPE ), ARRAY_A );
		foreach ( (array) $rows as $row ) {
			$status = preg_replace( '/^ohmylms-/', '', $row['post_status'] );
			if ( isset( $counts[ $status ] ) ) {
				$counts[ $status ] = (int) $row['total'];
			}
		}
		return rest_ensure_response( $counts );
	}

	/**
	 * Get one order.
	 *
	 * Opening an order marks it as seen (the `_is_open` flag drives the new-orders badge).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$post = $this->get_post( (int) $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( 'yes' !== get_post_meta( $post->ID, '_is_open', true ) ) {
			update_post_meta( $post->ID, '_is_open', 'yes' );
		}
		return $this->prepare_item_for_response( $post, $request );
	}

	/**
	 * Change the status of an order.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$post = $this->get_post( (int) $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$lock = 'order-' . $post->ID;
		if ( ! $this->acquire_lock( $lock ) ) {
			return new WP_Error( 'ohmylms_order_busy', __( 'This order is being changed by another request. Try again.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		try {
			clean_post_cache( $post->ID );
			$order    = ohmylms_ecommerce_get_order( $post->ID );
			$previous = $order->get_status( 'edit' );
			$target   = $request['status'];

			if ( $previous !== $target ) {
				if ( ! in_array( $target, self::allowed_transitions( $previous ), true ) ) {
					return new WP_Error(
						'ohmylms_order_invalid_transition',
						sprintf(
							/* translators: 1: current status, 2: requested status. */
							__( 'An order cannot change from %1$s to %2$s.', 'ohmylms' ),
							$previous,
							$target
						),
						array( 'status' => 409 )
					);
				}
				if ( ! $order->update_status( $target, '', true ) ) {
					return new WP_Error( 'ohmylms_order_not_saved', __( 'The order status could not be saved.', 'ohmylms' ), array( 'status' => 500 ) );
				}
				$order = ohmylms_ecommerce_get_order( $post->ID );

				if ( 'completed' === $target ) {
					/** Fires when an order is set to completed through the API (enrols the student). */
					do_action( 'ohmylms_update_order_status_to_completed', $order );
				}
				if ( 'cancelled' === $target ) {
					/** Fires when an order is set to cancelled through the API. */
					do_action( 'ohmylms_update_order_status_to_cancelled', $order );
					$students = new \OhMyLMS\DataStores\StudentStore();
					$students->delete_enrollment( $order->get_student_id(), $order->get_id() );
				}
			}
			clean_post_cache( $post->ID );
			return $this->prepare_item_for_response( get_post( $post->ID ), $request );
		} finally {
			$this->release_lock( $lock );
		}
	}

	/**
	 * Move an order to the trash. Enrolments created by the order are removed.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$post = $this->get_post( (int) $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$previous = $this->prepare_item_for_response( $post, $request )->get_data();
		do_action( 'ohmylms_rest_before_delete_order', $post->ID );
		if ( ! wp_trash_post( $post->ID ) ) {
			return new WP_Error( 'ohmylms_order_not_deleted', __( 'The order could not be deleted.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		do_action( 'ohmylms_rest_delete_order', $post->ID );
		return rest_ensure_response(
			array(
				'deleted'  => true,
				'previous' => $previous,
			)
		);
	}

	/**
	 * Get the notes of an order, newest first.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_notes( $request ) {
		$post = $this->get_post( (int) $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		return rest_ensure_response( $this->notes_of( $post->ID ) );
	}

	/**
	 * Notes of an order.
	 *
	 * @param int $order_id Order ID.
	 * @return array[]
	 */
	private function notes_of( $order_id ) {
		$notes = ohmylms_ecommerce_get_order_notes(
			array(
				'order_id' => $order_id,
				'limit'    => 100,
			)
		);
		return array_values( array_map( array( $this, 'prepare_note' ), $notes ) );
	}

	/**
	 * Shape one note.
	 *
	 * @param object $note Note from ohmylms_ecommerce_get_order_note().
	 * @return array
	 */
	private function prepare_note( $note ) {
		return array(
			'id'           => (int) $note->id,
			'content'      => wp_strip_all_tags( (string) $note->content ),
			'added_by'     => (string) $note->added_by,
			'date_created' => wp_date( 'Y-m-d\TH:i:s', $note->date_created->getTimestamp() ),
		);
	}

	/**
	 * Add a plain text note.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_note( $request ) {
		$post = $this->get_post( (int) $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$text = trim( sanitize_textarea_field( (string) $request['note'] ) );
		if ( '' === $text ) {
			return new WP_Error(
				'ohmylms_order_note_empty',
				__( 'The note is empty.', 'ohmylms' ),
				array(
					'status' => 422,
					'field'  => 'note',
				)
			);
		}
		$order   = ohmylms_ecommerce_get_order( $post->ID );
		$note_id = $order->add_order_note( $text, 0, true );
		$note    = $note_id ? ohmylms_ecommerce_get_order_note( $note_id ) : null;
		if ( ! $note ) {
			return new WP_Error( 'ohmylms_order_note_failed', __( 'The note could not be saved.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		$response = rest_ensure_response( $this->prepare_note( $note ) );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * Prepare an order for the response: a light row for lists, the full order for `context=edit`
	 * and single requests.
	 *
	 * @param WP_Post         $item    Order post.
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {
		$id     = (int) $item->ID;
		$status = preg_replace( '/^ohmylms-/', '', $item->post_status );
		$meta   = static function ( $key ) use ( $id ) {
			return (string) get_post_meta( $id, $key, true );
		};
		$name   = trim( $meta( '_first_name' ) . ' ' . $meta( '_last_name' ) );
		$data   = array(
			'id'                   => $id,
			'number'               => (string) $id,
			'status'               => $status,
			'currency'             => $meta( '_order_currency' ) ? $meta( '_order_currency' ) : get_ohmylms_currency(),
			'total'                => $this->money( $meta( '_order_total' ) ),
			'date_created'         => $this->date( $item->post_date ),
			'payment_method'       => $meta( '_payment_method' ),
			'payment_method_title' => $meta( '_payment_method_title' ),
			'student_id'           => (int) $meta( '_student_id' ),
			'student_name'         => $name,
		);

		if ( ! $this->is_detail( $request ) ) {
			return $this->wrap( $data, $item, $request );
		}

		$order  = ohmylms_ecommerce_get_order( $item );
		$refund = $this->refunds_of( $id );

		$lines = array();
		foreach ( $order->get_items() as $line ) {
			$lines[] = array(
				'id'        => (int) $line->get_id(),
				'course_id' => (int) $line->get_course_id(),
				'name'      => (string) $line->get_name(),
				'quantity'  => (int) $line->get_quantity(),
				'subtotal'  => $this->money( $line->get_subtotal() ),
				'total'     => $this->money( $line->get_total() ),
			);
		}
		$coupons = array();
		foreach ( $order->get_items( 'coupon' ) as $line ) {
			$coupons[] = array(
				'code'     => (string) $line->get_code(),
				'discount' => $this->money( $line->get_discount() ),
			);
		}

		$data = array_merge(
			$data,
			array(
				'parent_id'            => (int) $item->post_parent,
				'subtotal'             => $this->money( $order->get_cart_subtotal() ),
				'discount_total'       => $this->money( $order->get_cart_discount() ),
				'tax_amount'           => $this->money( $order->get_tax_amount() ),
				'date_paid'            => $this->timestamp_date( $order->get_date_paid( 'edit' ) ),
				'date_completed'       => $this->timestamp_date( $order->get_date_completed( 'edit' ) ),
				'transaction_id'       => $meta( '_transaction_id' ),
				'billing'              => array(
					'first_name' => $meta( '_first_name' ),
					'last_name'  => $meta( '_last_name' ),
					'email'      => $meta( '_email' ),
					'phone'      => $meta( '_phone' ),
					'address'    => $meta( '_address' ),
					'city'       => $meta( '_city' ),
					'state'      => $meta( '_state' ),
					'postcode'   => $meta( '_postcode' ),
					'country'    => $meta( '_country' ),
					'vat_number' => $meta( '_vat_number' ),
				),
				'line_items'           => $lines,
				'coupon_lines'         => $coupons,
				'refunds'              => $refund['items'],
				'total_refunded'       => $this->money( $refund['total'] ),
				'remaining_refundable' => $this->money( max( 0, (float) $meta( '_order_total' ) - $refund['total'] ) ),
				'related_orders'       => $this->related_of( $order ),
				'allowed_statuses'     => self::allowed_transitions( $status ),
				'points'               => class_exists( '\OhMyLMS\Engagement\PointPurchase' ) ? \OhMyLMS\Engagement\PointPurchase::summary( $item->ID ) : null,
			)
		);
		return $this->wrap( $data, $item, $request );
	}

	/**
	 * Whether the request wants the full order.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	private function is_detail( $request ) {
		return ! empty( $request['id'] );
	}

	/**
	 * Format a stored timestamp.
	 *
	 * @param mixed $value Date object or empty.
	 * @return string|null
	 */
	private function timestamp_date( $value ) {
		return ( is_object( $value ) && method_exists( $value, 'getTimestamp' ) ) ? wp_date( 'Y-m-d\TH:i:s', $value->getTimestamp() ) : null;
	}

	/**
	 * Attach links and the extension filter.
	 *
	 * @param array           $data Response data.
	 * @param WP_Post         $item Order post.
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	private function wrap( array $data, $item, $request ) {
		$response = rest_ensure_response( $data );
		$response->add_links(
			array(
				'self'       => array( 'href' => rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $item->ID ) ) ),
				'collection' => array( 'href' => rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ) ),
			)
		);
		return apply_filters( 'ohmylms_rest_prepare_order', $response, $item, $request );
	}

	/**
	 * Refunds recorded for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return array{items:array[],total:float}
	 */
	private function refunds_of( $order_id ) {
		$posts = get_posts(
			array(
				'post_type'      => 'ohmylms_order_refund',
				'post_status'    => 'any',
				'post_parent'    => $order_id,
				'posts_per_page' => 100,
				'orderby'        => 'ID',
				'order'          => 'DESC',
			)
		);
		$items = array();
		$total = 0.0;
		foreach ( $posts as $post ) {
			$amount  = (float) get_post_meta( $post->ID, '_refund_amount', true );
			$total  += $amount;
			$items[] = array(
				'id'           => (int) $post->ID,
				'amount'       => $this->money( $amount ),
				'reason'       => (string) get_post_meta( $post->ID, '_refund_reason', true ),
				'date_created' => $this->date( $post->post_date ),
			);
		}
		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Related orders and subscription of an order.
	 *
	 * @param object $order Order.
	 * @return array[]
	 */
	private function related_of( $order ) {
		$out = array();
		foreach ( (array) $order->get_related_orders() as $row ) {
			$type  = get_post_type( (int) $row['id'] );
			$out[] = array(
				'id'           => (int) $row['id'],
				'type'         => self::POST_TYPE === $type ? 'order' : 'subscription',
				'relationship' => (string) $row['relationship'],
				'status'       => strtolower( (string) $row['status'] ),
				'total'        => $this->money( $row['total'] ),
				'date_created' => $this->date( (string) $row['date'] ),
			);
		}
		return $out;
	}

	/**
	 * Collection parameters.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params = array(
			'context'        => $this->get_context_param( array( 'default' => 'view' ) ),
			'page'           => array(
				'description' => __( 'Current page of the collection.', 'ohmylms' ),
				'type'        => 'integer',
				'default'     => 1,
				'minimum'     => 1,
			),
			'per_page'       => array(
				'description' => __( 'Maximum number of items per page.', 'ohmylms' ),
				'type'        => 'integer',
				'default'     => 20,
				'minimum'     => 1,
				'maximum'     => 100,
			),
			'search'         => array(
				'description'       => __( 'Order number, student name or email (at most 1000 matches are considered).', 'ohmylms' ),
				'type'              => 'string',
				'maxLength'         => 100,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'status'         => array(
				'description' => __( 'Limit results to one order status.', 'ohmylms' ),
				'type'        => 'string',
				'default'     => 'any',
				'enum'        => array_merge( array( 'any' ), self::STATUSES ),
			),
			'payment_method' => array(
				'description'       => __( 'Limit results to one payment method id.', 'ohmylms' ),
				'type'              => 'string',
				'maxLength'         => 50,
				'sanitize_callback' => 'sanitize_key',
			),
			'after'          => array(
				'description' => __( 'First day, YYYY-MM-DD (inclusive, site time).', 'ohmylms' ),
				'type'        => 'string',
				'pattern'     => '^\d{4}-\d{2}-\d{2}$',
			),
			'before'         => array(
				'description' => __( 'Last day, YYYY-MM-DD (inclusive, site time).', 'ohmylms' ),
				'type'        => 'string',
				'pattern'     => '^\d{4}-\d{2}-\d{2}$',
			),
			'orderby'        => array(
				'description' => __( 'Sort the collection by this field.', 'ohmylms' ),
				'type'        => 'string',
				'default'     => 'date',
				'enum'        => array( 'date', 'id', 'total' ),
			),
			'order'          => array(
				'description' => __( 'Sort direction.', 'ohmylms' ),
				'type'        => 'string',
				'default'     => 'desc',
				'enum'        => array( 'asc', 'desc' ),
			),
		);
		return $params;
	}

	/**
	 * Order schema (the single order; lists carry the first group of properties).
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}
		$string = static function ( $description, $extra = array() ) {
			return array_merge(
				array(
					'description' => $description,
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				$extra
			);
		};
		$money  = static function ( $description ) {
			return array(
				'description' => $description,
				'type'        => 'string',
				'pattern'     => '^-?\d+(\.\d+)?$',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			);
		};
		$date   = static function ( $description ) {
			return array(
				'description' => $description,
				'type'        => array( 'string', 'null' ),
				'format'      => 'date-time',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			);
		};

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'order',
			'type'       => 'object',
			'properties' => array(
				'id'                   => array(
					'description' => __( 'Unique identifier for the order.', 'ohmylms' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'number'               => $string( __( 'Order number.', 'ohmylms' ) ),
				'status'               => $string( __( 'Order status.', 'ohmylms' ), array( 'enum' => self::STATUSES ) ),
				'currency'             => $string( __( 'ISO 4217 currency code of every amount.', 'ohmylms' ) ),
				'total'                => $money( __( 'Order total.', 'ohmylms' ) ),
				'date_created'         => $date( __( 'When the order was placed, in site time.', 'ohmylms' ) ),
				'payment_method'       => $string( __( 'Payment gateway id.', 'ohmylms' ) ),
				'payment_method_title' => $string( __( 'Payment gateway label.', 'ohmylms' ) ),
				'student_id'           => array(
					'description' => __( 'User ID of the student, 0 when unknown.', 'ohmylms' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'student_name'         => $string( __( 'Billing name.', 'ohmylms' ) ),
				'parent_id'            => array(
					'description' => __( 'Parent order (detail only).', 'ohmylms' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'subtotal'             => $money( __( 'Sum of line subtotals (detail only).', 'ohmylms' ) ),
				'discount_total'       => $money( __( 'Coupon discount (detail only).', 'ohmylms' ) ),
				'tax_amount'           => $money( __( 'Tax (detail only).', 'ohmylms' ) ),
				'date_paid'            => $date( __( 'When payment was received (detail only).', 'ohmylms' ) ),
				'date_completed'       => $date( __( 'When the order completed (detail only).', 'ohmylms' ) ),
				'transaction_id'       => $string( __( 'Gateway transaction id (detail only).', 'ohmylms' ) ),
				'billing'              => array(
					'description' => __( 'Billing details (detail only).', 'ohmylms' ),
					'type'        => 'object',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'line_items'           => array(
					'description' => __( 'Purchased courses (detail only).', 'ohmylms' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'coupon_lines'         => array(
					'description' => __( 'Coupons used (detail only).', 'ohmylms' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'refunds'              => array(
					'description' => __( 'Refunds recorded (detail only).', 'ohmylms' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'total_refunded'       => $money( __( 'Sum of refunds (detail only).', 'ohmylms' ) ),
				'remaining_refundable' => $money( __( 'Total minus refunds (detail only).', 'ohmylms' ) ),
				'related_orders'       => array(
					'description' => __( 'Renewal orders and subscription (detail only).', 'ohmylms' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'allowed_statuses'     => array(
					'description' => __( 'Statuses this order may be changed to (detail only).', 'ohmylms' ),
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'points'               => array(
					'description' => __( 'Bonus points spent on the order and whether they were refunded; null for orders not paid with points (detail only).', 'ohmylms' ),
					'type'        => array( 'object', 'null' ),
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
			),
		);
		return $this->add_additional_fields_schema( $this->schema );
	}
}
