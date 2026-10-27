<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the Composer PSR-4 class/file mapping.
/**
 * Read-only home-learning reports backed exclusively by recorded launch provenance.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Family;

defined( 'ABSPATH' ) || exit;

/** Never reads global mastery, school work, grades or unclassified historical evidence. */
final class Reports {

	/**
	 * Current guardian's report for one child. An ID selects a candidate only.
	 *
	 * @param int $learner Learner ID.
	 * @param int $days Allowlisted lookback window.
	 * @param int $page One-based page.
	 * @return array
	 * @throws \RuntimeException When the exact active home link is unavailable.
	 */
	public static function read( $learner, $days = 30, $page = 1 ) {
		$child = Service::child( $learner );
		if ( ! $child ) {
			throw new \RuntimeException( 'This home-learning report is not available.', 403 ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Escaped by the template.
		}
		$days  = in_array( (int) $days, array( 7, 30, 90 ), true ) ? (int) $days : 30;
		$page  = min( 10000, max( 1, (int) $page ) );
		$since = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		global $wpdb;
		// Both queries reauthorize the exact link in SQL, including revocation between reads.
		$sql  = "FROM %i e JOIN %i l ON l.learner_user_id=e.learner_user_id AND l.guardian_user_id=%d AND l.id=%d AND l.scope=%s AND l.status='active' WHERE e.learner_user_id=%d AND e.origin=%s AND e.created_at>=l.updated_at AND e.completed_at>=%s";
		$args = array( Schema::table( 'activity' ), Schema::table( 'links' ), get_current_user_id(), $child['link_id'], Schema::SCOPE, (int) $learner, Schema::SCOPE, $since );
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- The shared fixed SQL fragment above contains placeholders only; all values are passed to prepare().
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Exact current guardian/link scope, never cached.
		$summary = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS activities,COUNT(DISTINCT DATE(e.completed_at)) AS active_days $sql", $args ), ARRAY_A );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded page with fresh exact-link authorization.
		$items = (array) $wpdb->get_results( $wpdb->prepare( "SELECT e.source_kind,e.content_id,e.course_id,e.completed_at $sql ORDER BY e.completed_at DESC,e.id DESC LIMIT 20 OFFSET %d", array_merge( $args, array( ( $page - 1 ) * 20 ) ) ), ARRAY_A );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregate only the same currently approved home link.
		$types = (array) $wpdb->get_results( $wpdb->prepare( "SELECT e.source_kind,COUNT(*) AS activities $sql GROUP BY e.source_kind ORDER BY e.source_kind", $args ), ARRAY_A );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- At most 91 UTC dates in the allowlisted window.
		$daily = (array) $wpdb->get_results( $wpdb->prepare( "SELECT DATE(e.completed_at) AS activity_date,COUNT(*) AS activities $sql GROUP BY DATE(e.completed_at) ORDER BY activity_date DESC LIMIT 91", $args ), ARRAY_A );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded course totals, with the same exact-link authorization.
		$courses = (array) $wpdb->get_results( $wpdb->prepare( "SELECT e.course_id,COUNT(*) AS activities $sql GROUP BY e.course_id ORDER BY activities DESC,e.course_id LIMIT 10", $args ), ARRAY_A );
		// phpcs:enable
		return array(
			'child'   => $child,
			'days'    => $days,
			'page'    => $page,
			'pages'   => max( 1, (int) ceil( (int) $summary['activities'] / 20 ) ),
			'summary' => $summary,
			'items'   => $items,
			'types'   => $types,
			'daily'   => $daily,
			'courses' => $courses,
		);
	}
}
