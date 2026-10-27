<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Resolves live membership rules and keeps membership-owned enrollments in sync.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Membership;

defined( 'ABSPATH' ) || exit;

/** Resolves live membership rules and keeps membership-owned enrollments in sync. */
final class CourseSelection {
	/**
	 * Queued.
	 *
	 * @var mixed
	 */
	private static $queued = false;

	/**
	 * Ids for Course selection.
	 *
	 * @param mixed $values Values.
	 */
	public static function ids( $values ) {
		return array_values( array_unique( array_filter( array_map( 'absint', (array) $values ) ) ) );
	}

	/**
	 * Courses a membership plan includes. Curriculum items (with everything beneath them) and
	 * Course Bundles are the rules to choose from. Legacy category and tag rules, saved before they
	 * were replaced, still resolve so existing members keep the access they were granted until an
	 * administrator removes those rules.
	 *
	 * @param mixed $products Products.
	 * @param mixed $categories Categories.
	 * @param mixed $tags Tags.
	 * @param mixed $excluded Excluded.
	 * @param array $curriculum Curriculum.
	 * @param array $bundles Bundles.
	 */
	public static function resolve( $products, $categories, $tags, $excluded, $curriculum = array(), $bundles = array() ) {
		$ids = self::ids( array_column( (array) $products, 'id' ) );
		$tax = array( 'relation' => 'OR' );
		foreach ( array(
			'course_category' => $categories,
			'course_tag'      => $tags,
		) as $taxonomy => $terms ) {
			$terms = self::ids( $terms );
			if ( $terms ) {
				$tax[] = array(
					'taxonomy'         => $taxonomy,
					'field'            => 'term_id',
					'terms'            => $terms,
					'include_children' => 'course_category' === $taxonomy,
				);
			}
		}
		if ( count( $tax ) > 1 ) {
			$ids = array_merge(
				$ids,
				get_posts(
					array(
						'post_type'      => 'ohmylms-course',
						'post_status'    => 'publish',
						'posts_per_page' => -1,
						'fields'         => 'ids',
						'tax_query'      => $tax,
					)
				)
			);
		}
		$curriculum = self::ids( $curriculum );
		if ( $curriculum ) {
			$ids = array_merge( $ids, \OhMyLMS\Curriculum\Placement::course_ids_for_items( $curriculum ) );
		}
		$bundles = self::ids( $bundles );
		if ( $bundles ) {
			$ids = array_merge( $ids, \OhMyLMS\Curriculum\Placement::course_ids_for_bundles( $bundles ) );
		}
		$ids    = array_diff( self::ids( $ids ), self::ids( $excluded ) );
		$result = array();
		foreach ( $ids as $id ) {
			if ( get_post_type( $id ) !== 'ohmylms-course' || get_post_status( $id ) !== 'publish' ) {
				continue;
			}
			$result[] = array(
				'id'    => $id,
				'value' => $id,
				'name'  => get_the_title( $id ),
				'label' => get_the_title( $id ),
			);
		}
		return $result;
	}

	/**
	 * Init for Course selection.
	 */
	public static function init() {
		add_action( 'save_post', array( __CLASS__, 'post_changed' ), 20, 2 );
		add_action( 'before_delete_post', array( __CLASS__, 'post_changed' ), 20, 2 );
		add_action( 'set_object_terms', array( __CLASS__, 'terms_changed' ), 20, 4 );
		foreach ( array( 'created_course_category', 'edited_course_category', 'delete_course_category', 'delete_course_tag', 'ohmylms_curriculum_changed', 'ohmylms_curriculum_items_deleted', 'ohmylms_after_enrolled_student', 'ohmylms_after_enrollment_processed', 'ohmylms_membership_status_updated' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'queue' ) );
		}
		add_action( 'shutdown', array( __CLASS__, 'flush' ) );
	}
	/**
	 * Queue for Course selection.
	 */
	public static function queue() {
		self::$queued = true; }
	/**
	 * Post changed for Course selection.
	 *
	 * @param mixed $id Id.
	 * @param mixed $post Post.
	 */
	public static function post_changed( $id, $post ) {
		if ( in_array( $post->post_type, array( 'ohmylms-course', 'ohmylms-membership' ), true ) ) {
			self::queue();
		}
	}
	/**
	 * Terms changed for Course selection.
	 *
	 * @param mixed $id Id.
	 * @param mixed $terms Terms.
	 * @param mixed $tt_ids Tt ids.
	 * @param mixed $taxonomy Taxonomy.
	 */
	public static function terms_changed( $id, $terms, $tt_ids, $taxonomy ) {
		if ( in_array( $taxonomy, array( 'course_category', 'course_tag' ), true ) ) {
			self::queue();
		}
	}
	/**
	 * Flush for Course selection.
	 */
	public static function flush() {
		if ( ! self::$queued ) {
			return;
		}
		self::$queued = false;
		foreach ( get_posts(
			array(
				'post_type'      => 'ohmylms-membership',
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private', 'trash' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		) as $id ) {
			self::sync( $id );
		}
	}

	/**
	 * Sync for Course selection.
	 *
	 * @param mixed $membership_id Membership identifier.
	 */
	public static function sync( $membership_id ) {
		global $wpdb;
		$plan = ohmylms_get_membership( $membership_id );
		if ( ! $plan ) {
			return;
		}
		$courses = $plan->get_status() === 'publish' ? array_column( $plan->get_products(), 'id' ) : array();
		$members = $wpdb->get_results( $wpdb->prepare( 'SELECT user_id, order_id, status FROM %i WHERE membership_id=%d AND status IN (\'enrolled\',\'pending\')', $wpdb->prefix . 'ohmylms_user_membership', $membership_id ), ARRAY_A );
		$table   = $wpdb->prefix . 'ohmylms_user_enrollment';
		// Adopt old checkout rows only when their order identifies this membership.
		foreach ( $members as $member ) {
			if ( (int) $member['order_id'] > 0 ) {
				$wpdb->query( $wpdb->prepare( 'UPDATE %i SET membership_id=%d WHERE user_id=%d AND order_id=%d AND (membership_id IS NULL OR membership_id=0)', $table, $membership_id, $member['user_id'], $member['order_id'] ) );
			}
		}
		$rows    = $wpdb->get_results( $wpdb->prepare( 'SELECT id,user_id,order_id,course_id,status FROM %i WHERE membership_id=%d', $table, $membership_id ), ARRAY_A );
		$desired = array();
		foreach ( $members as $member ) {
			foreach ( $courses as $course ) {
				$key = $member['user_id'] . ':' . $course;
				if ( ! isset( $desired[ $key ] ) || 'enrolled' === $member['status'] ) {
					$desired[ $key ] = array( $member, $course );
				}
			}
		}
		foreach ( $rows as $row ) {
			$key    = $row['user_id'] . ':' . $row['course_id'];
			$status = isset( $desired[ $key ] ) ? $desired[ $key ][0]['status'] : 'cancelled';
			$order  = isset( $desired[ $key ] ) ? (int) $desired[ $key ][0]['order_id'] : (int) $row['order_id'];
			if ( $row['status'] !== $status || (int) $row['order_id'] !== $order ) {
				$wpdb->update(
					$table,
					array(
						'status'   => $status,
						'order_id' => $order,
					),
					array( 'id' => $row['id'] )
				);
			}
			unset( $desired[ $key ] );
		}
		foreach ( $desired as [$member, $course] ) {
			$wpdb->insert(
				$table,
				array(
					'user_id'       => (int) $member['user_id'],
					'order_id'      => (int) $member['order_id'],
					'membership_id' => (int) $membership_id,
					'course_id'     => (int) $course,
					'status'        => $member['status'],
					'progress'      => 'running',
					'start_date'    => current_time( 'mysql' ),
				)
			);
		}
	}
}
