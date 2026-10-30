<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Post counts and recently changed posts of the Content Hub overview.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\ContentHub;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only post queries behind `GET ohmylms/v1/content-hub/overview` (see docs/api/content-hub.md).
 *
 * The caller decides the scope: `$all_types` count every author's posts, `$own_types` only the posts of
 * `$author_id`. Trash and auto-drafts never count because only `$statuses` are read.
 */
final class ContentCounts {

	/**
	 * WHERE condition and arguments of the scope.
	 *
	 * @param string[] $all_types Post types counted for every author.
	 * @param string[] $own_types Post types counted for one author.
	 * @param int      $author_id Author of the own types.
	 * @param string[] $statuses  Post statuses.
	 * @return array{0:string,1:array}|null Null when there is no type to read.
	 */
	private static function scope( array $all_types, array $own_types, $author_id, array $statuses ) {
		$parts = array();
		$args  = array();
		if ( $all_types ) {
			$parts[] = 'post_type IN (' . implode( ',', array_fill( 0, count( $all_types ), '%s' ) ) . ')';
			$args    = array_merge( $args, $all_types );
		}
		if ( $own_types ) {
			$parts[] = '( post_type IN (' . implode( ',', array_fill( 0, count( $own_types ), '%s' ) ) . ') AND post_author = %d )';
			$args    = array_merge( $args, $own_types, array( (int) $author_id ) );
		}
		if ( ! $parts || ! $statuses ) {
			return null;
		}
		$holders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		return array( '( ' . implode( ' OR ', $parts ) . " ) AND post_status IN ({$holders})", array_merge( $args, $statuses ) );
	}

	/**
	 * Number of posts by type and status.
	 *
	 * @param string[] $all_types Post types counted for every author.
	 * @param string[] $own_types Post types counted for one author.
	 * @param int      $author_id Author of the own types.
	 * @param string[] $statuses  Post statuses.
	 * @return array<string,array<string,int>> Post type => status => count.
	 */
	public static function by_status( array $all_types, array $own_types, $author_id, array $statuses ) {
		global $wpdb;

		$scope = self::scope( $all_types, $own_types, $author_id, $statuses );
		if ( null === $scope ) {
			return array();
		}
		list( $where, $args ) = $scope;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery -- Placeholders are generated from the type and status counts.
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT post_type, post_status, COUNT(*) AS total FROM %i WHERE {$where} GROUP BY post_type, post_status", array_merge( array( $wpdb->posts ), $args ) ) );
		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ (string) $row->post_type ][ (string) $row->post_status ] = (int) $row->total;
		}

		return $counts;
	}

	/**
	 * Most recently changed posts, newest first.
	 *
	 * @param string[] $all_types Post types read for every author.
	 * @param string[] $own_types Post types read for one author.
	 * @param int      $author_id Author of the own types.
	 * @param string[] $statuses  Post statuses.
	 * @param int      $limit     Largest number of posts.
	 * @return object[] Rows with ID, post_type, post_status, post_title, post_modified and post_modified_gmt.
	 */
	public static function recent( array $all_types, array $own_types, $author_id, array $statuses, $limit ) {
		global $wpdb;

		$scope = self::scope( $all_types, $own_types, $author_id, $statuses );
		if ( null === $scope || (int) $limit < 1 ) {
			return array();
		}
		list( $where, $args ) = $scope;
		// New drafts keep a zero post_modified_gmt, so the site-local time orders them.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery -- Placeholders are generated from the type and status counts.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_type, post_status, post_title, post_modified, post_modified_gmt FROM %i WHERE {$where} ORDER BY post_modified DESC, ID DESC LIMIT %d", array_merge( array( $wpdb->posts ), $args, array( (int) $limit ) ) ) );

		return is_array( $rows ) ? $rows : array();
	}
}
