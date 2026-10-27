<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Course Bundles: administrator-curated groupings of selected courses and curriculum items
 * (typically syllabuses) that may span curricula. A course or curriculum item can sit in any
 * number of bundles without being copied. A bundle never grants access: following it only adds
 * it to a learner's dashboard.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Bundles;

use OhMyLMS\Curriculum\Access;
use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\Links;
use OhMyLMS\Curriculum\Schema;
use OhMyLMS\Utility\Transaction;

defined( 'ABSPATH' ) || exit;

/**
 * Course Bundles: administrator-curated groupings of selected courses and curriculum items
 * (typically syllabuses) that may span curricula. A course or curriculum item can sit in any
 * number of bundles without being copied. A bundle never grants access: following it only adds
 * it to a learner's dashboard.
 */
final class Bundles {
	const STATUSES     = array( 'draft', 'published' );
	const MEMBER_TYPES = array( 'course', 'curriculum' );
	const MAX_MEMBERS  = 100;
	const MAX_BUNDLES  = 200;

	/**
	 * Table for Bundles.
	 */
	public static function table() {
		return Schema::table( 'bundles' ); }
	/**
	 * Members table for Bundles.
	 */
	public static function members_table() {
		return Schema::table( 'bundle_items' ); }

	/**
	 * Get for Bundles.
	 *
	 * @param mixed $id Id.
	 */
	public static function get( $id ) {
		global $wpdb;
		$id = (int) $id;
		return $id > 0 ? ( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id=%d', $id ), ARRAY_A ) ?: null ) : null;
	}

	/**
	 * Missing for Bundles.
	 */
	public static function missing() {
		return Access::error( 'ohmylms_bundle_missing', __( 'Course bundle not found.', 'ohmylms' ), 404 ); }

	/**
	 * All for Bundles.
	 *
	 * @return array[] Bundles in display order, optionally only one status.
	 * @param mixed $status Status.
	 */
	public static function all( $status = null ) {
		global $wpdb;
		if ( null !== $status && in_array( $status, self::STATUSES, true ) ) {
			return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE status=%s ORDER BY position, id', $status ), ARRAY_A ) ?: array();
		}
		return $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY position, id', ARRAY_A ) ?: array();
	}

	/**
	 * Clean for Bundles.
	 *
	 * @param array $data Input data.
	 * @param bool  $partial Partial.
	 */
	public static function clean( array $data, $partial = false ) {
		$fields = array();
		if ( ! $partial || array_key_exists( 'title', $data ) ) {
			$title = trim( sanitize_text_field( (string) ( $data['title'] ?? '' ) ) );
			if ( '' === $title ) {
				return Access::error( 'ohmylms_bundle_invalid', __( 'A bundle needs a name.', 'ohmylms' ) ); }
			if ( mb_strlen( $title ) > 190 ) {
				return Access::error( 'ohmylms_bundle_invalid', __( 'Bundle names can have at most 190 characters.', 'ohmylms' ) ); }
			$fields['title'] = $title;
		}
		if ( ! $partial || array_key_exists( 'description', $data ) ) {
			$description = sanitize_textarea_field( (string) ( $data['description'] ?? '' ) );
			if ( mb_strlen( $description ) > 2000 ) {
				return Access::error( 'ohmylms_bundle_invalid', __( 'Descriptions can have at most 2000 characters.', 'ohmylms' ) ); }
			$fields['description'] = $description;
		}
		return $fields;
	}

	/**
	 * Create for Bundles.
	 *
	 * @param array $data Input data.
	 */
	public static function create( array $data ) {
		global $wpdb;
		$fields = self::clean( $data );
		if ( is_wp_error( $fields ) ) {
			return $fields; }
		if ( (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() ) >= self::MAX_BUNDLES ) {
			return Access::error( 'ohmylms_bundle_limit', sprintf( /* translators: %d: number. */
			__( 'You can have at most %d course bundles.', 'ohmylms' ), self::MAX_BUNDLES ), 409 ); }
		$now      = current_time( 'mysql', true );
		$position = 1 + (int) $wpdb->get_var( 'SELECT MAX(position) FROM ' . self::table() );
		$row      = $fields + array(
			'uuid'       => wp_generate_uuid4(),
			'status'     => 'draft',
			'position'   => $position,
			'created_by' => get_current_user_id(),
			'created_at' => $now,
			'updated_at' => $now,
		);
		if ( ! $wpdb->insert( self::table(), $row ) ) {
			return Access::error( 'ohmylms_bundle_failed', __( 'The bundle could not be saved.', 'ohmylms' ), 500 ); }
		return self::get( (int) $wpdb->insert_id );
	}

	/**
	 * Update for Bundles.
	 *
	 * @param mixed $id Id.
	 * @param array $data Input data.
	 * @param mixed $expected_updated_at Expected updated at.
	 */
	public static function update( $id, array $data, $expected_updated_at = null ) {
		global $wpdb;
		$bundle = self::get( $id );
		if ( ! $bundle ) {
			return self::missing(); }
		$fields = self::clean( $data, true );
		if ( is_wp_error( $fields ) ) {
			return $fields; }
		if ( null !== $expected_updated_at && '' !== $expected_updated_at && (string) $expected_updated_at !== $bundle['updated_at'] ) {
			return self::stale(); }
		if ( $fields ) {
			$fields['updated_at'] = current_time( 'mysql', true );
			if ( $wpdb->update( self::table(), $fields, array( 'id' => (int) $id ) ) === false ) {
				return Access::error( 'ohmylms_bundle_failed', __( 'The bundle could not be saved.', 'ohmylms' ), 500 ); }
		}
		return self::get( $id );
	}

	/**
	 * Stale for Bundles.
	 */
	private static function stale() {
		return Access::error( 'ohmylms_bundle_stale', __( 'Someone else changed this bundle. Reload and try again.', 'ohmylms' ), 409 );
	}

	/**
	 * Members for Bundles.
	 *
	 * @return array[] Ordered members: type, id, position.
	 * @param mixed $bundle_id Bundle identifier.
	 */
	public static function members( $bundle_id ) {
		global $wpdb;
		return array_map(
			static function ( $row ) {
				return array(
					'type'     => $row['item_type'],
					'id'       => (int) $row['item_id'],
					'position' => (int) $row['position'],
				); },
			$wpdb->get_results( $wpdb->prepare( 'SELECT item_type, item_id, position FROM ' . self::members_table() . ' WHERE bundle_id=%d ORDER BY position, id', (int) $bundle_id ), ARRAY_A ) ?: array()
		);
	}

	/** Is this a valid member to add? Courses may be unpublished while a bundle is being prepared.
	 *
	 * @param mixed $type Type.
	 * @param mixed $id Id.
	 */
	private static function valid_member( $type, $id ) {
		if ( 'course' === $type ) {
			return Links::target_exists( 'course', $id ); }
		return 'curriculum' === $type && Items::get( $id ) !== null;
	}

	/**
	 * Replace the ordered member list. Each entry is `['type' => 'course'|'curriculum', 'id' => n]`.
	 * Duplicates are rejected rather than silently merged so the editor stays truthful.
	 *
	 * @param mixed $bundle_id Bundle identifier.
	 * @param array $members Members.
	 * @param mixed $expected_updated_at Expected updated at.
	 */
	public static function set_members( $bundle_id, array $members, $expected_updated_at = null ) {
		global $wpdb;
		$bundle = self::get( $bundle_id );
		if ( ! $bundle ) {
			return self::missing(); }
		if ( null !== $expected_updated_at && '' !== $expected_updated_at && (string) $expected_updated_at !== $bundle['updated_at'] ) {
			return self::stale(); }
		if ( count( $members ) > self::MAX_MEMBERS ) {
			return Access::error( 'ohmylms_bundle_limit', sprintf( /* translators: %d: number. */
			__( 'A bundle can have at most %d members.', 'ohmylms' ), self::MAX_MEMBERS ), 409 ); }
		$clean = array();
		$seen  = array();
		foreach ( $members as $member ) {
			$type = is_array( $member ) ? (string) ( $member['type'] ?? '' ) : '';
			$id   = is_array( $member ) ? (int) ( $member['id'] ?? 0 ) : 0;
			if ( ! in_array( $type, self::MEMBER_TYPES, true ) || ! self::valid_member( $type, $id ) ) {
				return Access::error( 'ohmylms_bundle_invalid', __( 'Every member must be an existing course or curriculum item.', 'ohmylms' ) ); }
			if ( isset( $seen[ $type . ':' . $id ] ) ) {
				return Access::error( 'ohmylms_bundle_invalid', __( 'A course or curriculum item can appear only once in a bundle.', 'ohmylms' ) ); }
			$seen[ $type . ':' . $id ] = true;
			$clean[]                   = array(
				'type' => $type,
				'id'   => $id,
			);
		}
		if ( 'published' === $bundle['status'] && ! $clean ) {
			return Access::error( 'ohmylms_bundle_empty', __( 'A published bundle needs at least one member. Unpublish it first to empty it.', 'ohmylms' ), 409 ); }
		try {
			Transaction::run(
				static function () use ( $wpdb, $bundle_id, $clean ) {
					if ( $wpdb->delete( self::members_table(), array( 'bundle_id' => (int) $bundle_id ) ) === false ) {
						throw new \RuntimeException( 'Member reset failed' ); }
					$now = current_time( 'mysql', true );
					foreach ( $clean as $position => $member ) {
						if ( ! $wpdb->insert(
							self::members_table(),
							array(
								'bundle_id'  => (int) $bundle_id,
								'item_type'  => $member['type'],
								'item_id'    => $member['id'],
								'position'   => $position,
								'created_at' => $now,
							)
						) ) {
							throw new \RuntimeException( 'Member write failed' ); }
					}
					if ( $wpdb->update( self::table(), array( 'updated_at' => $now ), array( 'id' => (int) $bundle_id ) ) === false ) {
						throw new \RuntimeException( 'Bundle touch failed' ); }
				}
			);
		} catch ( \Throwable $error ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'OhMyLMS bundle members failed: ' . $error->getMessage() ); }
			return Access::error( 'ohmylms_bundle_failed', __( 'The members could not be saved. Nothing was changed.', 'ohmylms' ), 500 );
		}
		\OhMyLMS\Curriculum\Placement::changed();
		return self::get( $bundle_id );
	}

	/** Publish or unpublish. Followers keep following an unpublished bundle; it simply stops showing.
	 *
	 * @param mixed $bundle_id Bundle identifier.
	 * @param mixed $published Published.
	 */
	public static function set_status( $bundle_id, $published ) {
		global $wpdb;
		$bundle = self::get( $bundle_id );
		if ( ! $bundle ) {
			return self::missing(); }
		$status = $published ? 'published' : 'draft';
		if ( $published ) {
			if ( ! self::members( $bundle_id ) ) {
				return Access::error( 'ohmylms_bundle_empty', __( 'Add at least one course or curriculum item before publishing.', 'ohmylms' ), 409 ); }
			if ( trim( $bundle['title'] ) === '' ) {
				return Access::error( 'ohmylms_bundle_invalid', __( 'A bundle needs a name.', 'ohmylms' ) ); }
		}
		$now    = current_time( 'mysql', true );
		$fields = array(
			'status'     => $status,
			'updated_at' => $now,
		) + ( $published && 'published' !== $bundle['status'] ? array( 'published_at' => $now ) : array() );
		if ( $wpdb->update( self::table(), $fields, array( 'id' => (int) $bundle_id ) ) === false ) {
			return Access::error( 'ohmylms_bundle_failed', __( 'The bundle could not be saved.', 'ohmylms' ), 500 ); }
		return self::get( $bundle_id );
	}

	/**
	 * Find by title for Bundles.
	 *
	 * @param mixed $title Title.
	 */
	public static function find_by_title( $title ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE title=%s ORDER BY id LIMIT 1', (string) $title ), ARRAY_A ) ?: null;
	}

	/**
	 * Touch for Bundles.
	 *
	 * @param mixed $bundle_id Bundle identifier.
	 */
	private static function touch( $bundle_id ) {
		global $wpdb;
		$wpdb->update( self::table(), array( 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $bundle_id ) );
	}

	/** Append a course to a bundle. Repeating the call is harmless. @return true|\WP_Error
	 *
	 * @param mixed $bundle_id Bundle identifier.
	 * @param mixed $course_id Course identifier.
	 */
	public static function add_course( $bundle_id, $course_id ) {
		global $wpdb;
		if ( ! self::get( $bundle_id ) ) {
			return self::missing(); }
		if ( ! self::valid_member( 'course', $course_id ) ) {
			return Access::error( 'ohmylms_bundle_invalid', __( 'Every member must be an existing course or curriculum item.', 'ohmylms' ) ); }
		$count  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::members_table() . ' WHERE bundle_id=%d', (int) $bundle_id ) );
		$exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::members_table() . " WHERE bundle_id=%d AND item_type='course' AND item_id=%d", (int) $bundle_id, (int) $course_id ) );
		if ( $exists ) {
			return true; }
		if ( $count >= self::MAX_MEMBERS ) {
			return Access::error( 'ohmylms_bundle_limit', sprintf( /* translators: %d: number. */
			__( 'A bundle can have at most %d members.', 'ohmylms' ), self::MAX_MEMBERS ), 409 ); }
		$position = 1 + (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(position) FROM ' . self::members_table() . ' WHERE bundle_id=%d', (int) $bundle_id ) );
		if ( $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO ' . self::members_table() . " (bundle_id, item_type, item_id, position, created_at) VALUES (%d, 'course', %d, %d, %s)", (int) $bundle_id, (int) $course_id, $position, current_time( 'mysql', true ) ) ) === false ) {
			return Access::error( 'ohmylms_bundle_failed', __( 'The course could not be added to the bundle.', 'ohmylms' ), 500 );
		}
		self::touch( $bundle_id );
		\OhMyLMS\Curriculum\Placement::changed();
		return true;
	}

	/** Remove a course from a bundle. A published bundle keeps at least one member. @return true|\WP_Error
	 *
	 * @param mixed $bundle_id Bundle identifier.
	 * @param mixed $course_id Course identifier.
	 */
	public static function remove_course( $bundle_id, $course_id ) {
		global $wpdb;
		$bundle = self::get( $bundle_id );
		if ( ! $bundle ) {
			return self::missing(); }
		$is_member = (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::members_table() . " WHERE bundle_id=%d AND item_type='course' AND item_id=%d", (int) $bundle_id, (int) $course_id ) );
		if ( ! $is_member ) {
			return true; }
		if ( 'published' === $bundle['status'] && count( self::members( $bundle_id ) ) <= 1 ) {
			return Access::error( 'ohmylms_bundle_empty', sprintf( /* translators: %s: text value. */
			__( '“%s” is published and this is its only member. Unpublish the bundle or add another member first.', 'ohmylms' ), $bundle['title'] ), 409 );
		}
		$wpdb->delete(
			self::members_table(),
			array(
				'bundle_id' => (int) $bundle_id,
				'item_type' => 'course',
				'item_id'   => (int) $course_id,
			)
		);
		self::touch( $bundle_id );
		\OhMyLMS\Curriculum\Placement::changed();
		return true;
	}

	/**
	 * Follower count for Bundles.
	 *
	 * @param mixed $bundle_id Bundle identifier.
	 */
	public static function follower_count( $bundle_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE bundle_id=%d', Schema::table( 'bundle_follows' ), (int) $bundle_id ) );
	}

	/** Delete a bundle. Followers lose it from their dashboards, so that needs `$force`.
	 *
	 * @param mixed $id Id.
	 * @param bool  $force Force.
	 */
	public static function delete( $id, $force = false ) {
		global $wpdb;
		$bundle = self::get( $id );
		if ( ! $bundle ) {
			return self::missing(); }
		$followers = self::follower_count( $id );
		if ( $followers && ! $force ) {
			return Access::error( 'ohmylms_bundle_has_followers', sprintf( /* translators: %d: number. */
			_n( '%d learner follows this bundle. Deleting it removes it from their dashboard.', '%d learners follow this bundle. Deleting it removes it from their dashboards.', $followers, 'ohmylms' ), $followers ), 409, array( 'followers' => $followers ) );
		}
		try {
			Transaction::run(
				static function () use ( $wpdb, $id ) {
					if ( $wpdb->delete( Schema::table( 'bundle_follows' ), array( 'bundle_id' => (int) $id ) ) === false || $wpdb->delete( self::members_table(), array( 'bundle_id' => (int) $id ) ) === false || $wpdb->delete( self::table(), array( 'id' => (int) $id ) ) === false ) {
						throw new \RuntimeException( 'Bundle delete failed' ); }
				}
			);
		} catch ( \Throwable $error ) {
			return Access::error( 'ohmylms_bundle_failed', __( 'The bundle could not be deleted. Nothing was changed.', 'ohmylms' ), 500 );
		}
		\OhMyLMS\Curriculum\Placement::changed();
		return array(
			'id'                => (int) $id,
			'deleted'           => true,
			'followers_removed' => $followers,
		);
	}

	/** Remove a deleted course or curriculum item from every bundle.
	 *
	 * @param mixed $type Type.
	 * @param mixed $item_id Item identifier.
	 */
	public static function remove_member( $type, $item_id ) {
		global $wpdb;
		if ( ! Schema::ready() ) {
			return; }
		$wpdb->delete(
			self::members_table(),
			array(
				'item_type' => (string) $type,
				'item_id'   => (int) $item_id,
			)
		);
		\OhMyLMS\Curriculum\Placement::changed();
	}

	/** Public, JSON-safe description of a bundle.
	 *
	 * @param array $row Row.
	 */
	public static function describe( array $row ) {
		$members = self::members( (int) $row['id'] );
		return array(
			'id'             => (int) $row['id'],
			'uuid'           => $row['uuid'],
			'title'          => $row['title'],
			'description'    => (string) $row['description'],
			'status'         => $row['status'],
			'member_count'   => count( $members ),
			'follower_count' => self::follower_count( (int) $row['id'] ),
			'created_at'     => $row['created_at'],
			'updated_at'     => $row['updated_at'],
			'published_at'   => $row['published_at'],
		);
	}

	/** Members with titles for the administrator editor.
	 *
	 * @param mixed $bundle_id Bundle identifier.
	 */
	public static function describe_members( $bundle_id ) {
		$members = self::members( $bundle_id );
		$courses = Links::labels(
			'course',
			array_column(
				array_filter(
					$members,
					static function ( $member ) {
						return 'course' === $member['type'];
					}
				),
				'id'
			)
		);
		return array_map(
			static function ( $member ) use ( $courses ) {
				if ( 'course' === $member['type'] ) {
					$label = $courses[ $member['id'] ] ?? array(
						'title'  => '',
						'status' => 'missing',
					);
					return $member + array(
						'title'     => $label['title'],
						'status'    => $label['status'],
						'available' => 'missing' !== $label['status'],
					);
				}
				$item = Items::get( $member['id'] );
				return $member + array(
					'title'     => $item ? $item['name'] : '',
					'status'    => $item ? 'active' : 'missing',
					'available' => (bool) $item,
					'item_type' => $item ? $item['item_type'] : '',
					'code'      => $item ? $item['code'] : '',
					'version'   => $item ? $item['version'] : '',
					'path'      => $item ? Items::path_names( $member['id'], false ) : array(),
				);
			},
			$members
		);
	}
}
