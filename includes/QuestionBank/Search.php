<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Question bank search: the visibility-scoped list query and row summaries shared by
 * the legacy `/question-bank` route and the typed `/questions` API.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\QuestionBank;

use OhMyLMS\Assessment\Schema;
use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * Question bank search.
 *
 * Users without `edit_others_posts` only ever match their own questions and questions in
 * banks they can use ({@see Banks::for_user()}), so totals never count hidden questions.
 */
final class Search {

	/**
	 * Supported filters and their "not set" values.
	 */
	const DEFAULTS = array(
		'status'       => '',
		'type'         => '',
		'difficulty'   => '',
		'family'       => '',
		'bank'         => null,
		'secure'       => null,
		'search'       => '',
		'skill'        => 0,
		'exclude_quiz' => 0,
		'organisation' => null,
		'scope'        => null,
	);

	/**
	 * Sortable columns.
	 */
	const ORDERBY = array(
		'modified' => 'p.post_modified_gmt',
		'title'    => 'p.post_title',
		'id'       => 'p.ID',
	);

	/**
	 * Find the IDs of one page of visible questions.
	 *
	 * Filters: status (archived|draft|approved; anything else means "not archived"), type,
	 * difficulty, family, bank (0 = personal), secure, search (title or content), skill (term ID),
	 * exclude_quiz, organisation and scope ({@see Organisation::condition()}).
	 *
	 * @param array  $filters  Filters; unknown keys are ignored.
	 * @param int    $page     Page number (1-based).
	 * @param int    $per_page Page size.
	 * @param string $orderby  modified|title|id.
	 * @param string $order    asc|desc.
	 * @return array{ids:int[],total:int}|\WP_Error
	 */
	public static function query( array $filters, $page, $per_page, $orderby = 'modified', $order = 'desc' ) {
		global $wpdb;

		$filters = array_merge( self::DEFAULTS, array_intersect_key( $filters, self::DEFAULTS ) );
		$text    = static function ( $value ) {
			return is_scalar( $value ) ? (string) $value : '';
		};
		$where   = array( 'p.post_type=%s', "p.post_status NOT IN ('auto-draft','trash')" );
		$args    = array( $wpdb->posts, Schema::table( 'qb_questions' ), OHMYLMS_QUESTION_CPT );

		$organisation = Organisation::condition( $filters );
		if ( is_wp_error( $organisation ) ) {
			return $organisation;
		}
		if ( $organisation['where'] ) {
			$where[] = $organisation['where'];
			$args    = array_merge( $args, $organisation['args'] );
		}

		$status = $text( $filters['status'] );
		if ( 'archived' === $status ) {
			$where[] = 'p.post_status=%s';
			$args[]  = Usage::ARCHIVED;
		} else {
			$where[] = 'p.post_status<>%s';
			$args[]  = Usage::ARCHIVED;
			if ( 'approved' === $status ) {
				$where[] = 'q.approved_version_id>0';
			} elseif ( 'draft' === $status ) {
				$where[] = '(q.approved_version_id IS NULL OR q.approved_version_id=0)';
			}
		}
		foreach ( array(
			'type'       => 'q.type',
			'difficulty' => 'q.difficulty',
			'family'     => 'q.family_id',
		) as $filter => $column ) {
			if ( '' !== $text( $filters[ $filter ] ) ) {
				$where[] = "$column=%s";
				$args[]  = sanitize_text_field( $text( $filters[ $filter ] ) );
			}
		}
		if ( null !== $filters['bank'] && '' !== $filters['bank'] ) {
			$where[] = 'COALESCE(q.bank_id,0)=%d';
			$args[]  = (int) $filters['bank'];
		}
		if ( null !== $filters['secure'] && '' !== $filters['secure'] ) {
			$where[] = 'COALESCE(q.secure,0)=%d';
			$args[]  = rest_sanitize_boolean( $filters['secure'] ) ? 1 : 0;
		}
		if ( '' !== $text( $filters['search'] ) ) {
			$like    = '%' . $wpdb->esc_like( sanitize_text_field( $text( $filters['search'] ) ) ) . '%';
			$where[] = '(p.post_title LIKE %s OR p.post_content LIKE %s)';
			$args[]  = $like;
			$args[]  = $like;
		}
		if ( (int) $filters['skill'] ) {
			$where[] = 'EXISTS (SELECT 1 FROM %i tr JOIN %i tt ON tt.term_taxonomy_id=tr.term_taxonomy_id WHERE tr.object_id=p.ID AND tt.taxonomy=%s AND tt.term_id=%d)';
			$args    = array_merge( $args, array( $wpdb->term_relationships, $wpdb->term_taxonomy, Taxonomy::NAME, (int) $filters['skill'] ) );
		}
		if ( (int) $filters['exclude_quiz'] ) {
			$where[] = 'p.ID NOT IN (SELECT question_id FROM %i WHERE quiz_id=%d)';
			$args[]  = $wpdb->prefix . 'ohmylms_quiz_questions_relationship';
			$args[]  = (int) $filters['exclude_quiz'];
		}
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			$visible  = array( 'p.post_author=%d' );
			$args[]   = get_current_user_id();
			$bank_ids = array_values( array_filter( array_map( 'intval', array_column( Banks::for_user(), 'id' ) ) ) );
			if ( $bank_ids ) {
				$visible[] = 'q.bank_id IN (' . implode( ',', array_fill( 0, count( $bank_ids ), '%d' ) ) . ')';
				$args      = array_merge( $args, $bank_ids );
			}
			$where[] = '(' . implode( ' OR ', $visible ) . ')';
		}

		$column    = self::ORDERBY[ $orderby ] ?? self::ORDERBY['modified'];
		$direction = 'asc' === strtolower( (string) $order ) ? 'ASC' : 'DESC';
		$sql_where = implode( ' AND ', $where );
		$per_page  = max( 1, (int) $per_page );
		$page      = max( 1, (int) $page );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery -- Every fragment above is built from fixed SQL with placeholders; the sort column and direction come from allow-lists.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i p LEFT JOIN %i q ON q.question_id=p.ID WHERE $sql_where", $args ) );
		$ids   = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM %i p LEFT JOIN %i q ON q.question_id=p.ID WHERE $sql_where ORDER BY $column $direction, p.ID $direction LIMIT %d OFFSET %d",
				array_merge( $args, array( $per_page, ( $page - 1 ) * $per_page ) )
			)
		);
		// phpcs:enable

		return array(
			'ids'   => array_map( 'intval', (array) $ids ),
			'total' => $total,
		);
	}

	/**
	 * Row summary used by the legacy `/question-bank` routes.
	 *
	 * @param int $question_id Question ID.
	 * @return array
	 */
	public static function summary( $question_id ) {
		$question_id = (int) $question_id;
		$attributes  = BankAttributes::get( $question_id );
		$settings    = (array) get_post_meta( $question_id, '_question_settings', true );
		$skills      = wp_get_object_terms( $question_id, Taxonomy::NAME, array( 'fields' => 'ids' ) );

		return array(
			'id'                  => $question_id,
			'uuid'                => $attributes['uuid'] ?? '',
			'name'                => get_the_title( $question_id ),
			'type'                => (string) ( $settings['type'] ?? '' ),
			'marks'               => ! empty( $settings['score']['enabled'] ) ? (float) ( $settings['score']['value'] ?? 0 ) : 0,
			'post_status'         => get_post_status( $question_id ),
			'status'              => ! empty( $attributes['approved_version_id'] ) ? 'approved' : ( get_post_status( $question_id ) === Usage::ARCHIVED ? 'archived' : 'draft' ),
			'bank_id'             => (int) ( $attributes['bank_id'] ?? 0 ),
			'difficulty'          => $attributes['difficulty'] ?? 'standard',
			'source'              => $attributes['source'] ?? '',
			'family_id'           => $attributes['family_id'] ?? '',
			'secure'              => (bool) ( $attributes['secure'] ?? 0 ),
			'version'             => (int) ( $attributes['latest_version_no'] ?? 0 ),
			'current_version_id'  => (int) ( $attributes['current_version_id'] ?? 0 ),
			'approved_version_id' => (int) ( $attributes['approved_version_id'] ?? 0 ),
			'approved_is_current' => ! empty( $attributes['approved_version_id'] ) && (int) $attributes['approved_version_id'] === (int) $attributes['current_version_id'],
			'skills'              => is_array( $skills ) ? array_map( 'intval', $skills ) : array(),
			'usage'               => Usage::summary( $question_id ),
			'author'              => (int) get_post_field( 'post_author', $question_id ),
			'modified'            => get_post_field( 'post_modified_gmt', $question_id ),
			'native_editor_url'   => AccessPolicy::can_edit_question( $question_id ) ? (string) get_edit_post_link( $question_id, 'raw' ) : '',
			'can_edit'            => AccessPolicy::can_edit_question( $question_id ),
			'can_approve'         => AccessPolicy::can_approve_question( $question_id ),
		);
	}

	/**
	 * Identity rows (qb_questions) for several questions with one query. Never creates rows.
	 *
	 * @param int[] $ids Question IDs.
	 * @return array<int,array> Rows keyed by question ID; questions without a row are absent.
	 */
	public static function identities( array $ids ) {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( ! $ids ) {
			return array();
		}
		$holders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery -- Placeholder list for bound IDs.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE question_id IN ($holders)", array_merge( array( Schema::table( 'qb_questions' ) ), $ids ) ), ARRAY_A );

		return array_column( (array) $rows, null, 'question_id' );
	}

	/**
	 * Usage counts for several questions: quizzes that contain each question and learner records
	 * that reference it (the same sources and filter as {@see Usage::responses()}).
	 *
	 * @param int[] $ids Question IDs.
	 * @return array<int,array{quizzes:int,responses:int}>
	 */
	public static function usage_counts( array $ids ) {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( ! $ids ) {
			return array();
		}
		$holders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$counts  = array_fill_keys(
			$ids,
			array(
				'quizzes'   => 0,
				'responses' => 0,
			)
		);
		$sources = array( 'quizzes' => array( $wpdb->prefix . OHMYLMS_QUIZ_QUESTION_RELATIONSHIP ) );
		$records = array( $wpdb->prefix . 'ohmylms_quiz_attempts_answers' );
		if ( Schema::ready() ) {
			$records[] = Schema::table( 'attempt_items' );
			$records[] = Schema::table( 'practice_items' );
		}
		$sources['responses'] = $records;

		foreach ( $sources as $key => $tables ) {
			foreach ( $tables as $table ) {
				$expression = 'quizzes' === $key ? 'COUNT(DISTINCT quiz_id)' : 'COUNT(*)';
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery -- Fixed aggregate expression and placeholder list for bound IDs.
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT question_id, $expression AS total FROM %i WHERE question_id IN ($holders) GROUP BY question_id", array_merge( array( $table ), $ids ) ) );
				foreach ( (array) $rows as $row ) {
					$counts[ (int) $row->question_id ][ $key ] += (int) $row->total;
				}
			}
		}
		foreach ( $counts as $question_id => $count ) {
			/** This filter is documented in includes/QuestionBank/Usage.php (Usage::responses()). */
			$counts[ $question_id ]['responses'] = (int) apply_filters( 'ohmylms_question_response_count', $count['responses'], $question_id );
		}

		return $counts;
	}
}
