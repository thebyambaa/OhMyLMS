<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Graded items of a course and their gradebook settings.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Gradebook;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * A graded item is a published quiz, exam (a quiz backing an exam) or assignment placed in the course.
 * Each has a kind, a category (default: assignment → `homework`, quiz → `quiz`, exam → `test`; editable),
 * a maximum (assignment `_total_points`, the quiz's published revision or live total; an optional
 * override), an audience (`all`, `classes` = members of the listed course classes, `individuals` = learners
 * in no class of the course) and an optional due date (UTC).
 */
final class Items {

	const AUDIENCES = array( 'all', 'classes', 'individuals' );

	/**
	 * Default category per kind.
	 */
	const DEFAULT_CATEGORY = array(
		'assignment' => 'homework',
		'quiz'       => 'quiz',
		'exam'       => 'test',
	);

	/**
	 * Items of a course with their settings, in course order.
	 *
	 * @param int $course_id Course.
	 * @return array[] Each: id, title, kind, category, module_id, module, max, audience, class_ids, due_at.
	 */
	public static function for_course( $course_id ) {
		$placed   = Repository::placed_items( $course_id );
		$quizzes  = array();
		$settings = Repository::item_settings( $course_id );
		foreach ( $placed as $item ) {
			if ( OHMYLMS_QUIZ_CPT === $item['post_type'] ) {
				$quizzes[] = $item['id'];
			}
		}
		$exams = array_flip( Repository::exam_quiz_ids( $quizzes ) );
		$items = array();
		foreach ( $placed as $item ) {
			$kind    = OHMYLMS_ASSIGNMENT_CPT === $item['post_type'] ? 'assignment' : ( isset( $exams[ $item['id'] ] ) ? 'exam' : 'quiz' );
			$setting = $settings[ $item['id'] ] ?? array();
			$max     = 'assignment' === $kind ? (float) get_post_meta( $item['id'], '_total_points', true ) : self::quiz_max( $item['id'] );
			if ( isset( $setting['max_marks'] ) && null !== $setting['max_marks'] && (float) $setting['max_marks'] > 0 ) {
				$max = (float) $setting['max_marks'];
			}
			$items[] = array(
				'id'        => $item['id'],
				'title'     => $item['title'],
				'kind'      => $kind,
				'category'  => '' !== (string) ( $setting['category'] ?? '' ) ? (string) $setting['category'] : self::DEFAULT_CATEGORY[ $kind ],
				'module_id' => $item['module_id'],
				'module'    => $item['module'],
				'max'       => max( 0.0, $max ),
				'audience'  => in_array( $setting['audience'] ?? 'all', self::AUDIENCES, true ) ? (string) ( $setting['audience'] ?? 'all' ) : 'all',
				'class_ids' => self::ids( (string) ( $setting['class_ids'] ?? '' ) ),
				'due_at'    => empty( $setting['due_at'] ) ? null : (string) $setting['due_at'],
			);
		}
		return $items;
	}

	/**
	 * One item of a course, or null.
	 *
	 * @param int $course_id  Course.
	 * @param int $content_id Item.
	 * @return array|null
	 */
	public static function find( $course_id, $content_id ) {
		foreach ( self::for_course( $course_id ) as $item ) {
			if ( (int) $content_id === $item['id'] ) {
				return $item;
			}
		}
		return null;
	}

	/**
	 * Whether a learner is in an item's audience.
	 *
	 * @param array $item    Item.
	 * @param int[] $rosters The learner's classes in this course.
	 * @return bool
	 */
	public static function assigned( array $item, array $rosters ) {
		if ( 'individuals' === $item['audience'] ) {
			return ! $rosters;
		}
		if ( 'classes' === $item['audience'] ) {
			return (bool) array_intersect( $item['class_ids'], $rosters );
		}
		return true;
	}

	/**
	 * Maximum of a quiz: the published revision's total when the versioned engine delivers it, else the
	 * live total.
	 *
	 * @param int $quiz_id Quiz.
	 * @return float
	 */
	public static function quiz_max( $quiz_id ) {
		return \OhMyLMS\Schools\Gradebook::quiz_max( $quiz_id );
	}

	/**
	 * Comma-separated ids to a list.
	 *
	 * @param string $value Value.
	 * @return int[]
	 */
	private static function ids( $value ) {
		return array_values( array_filter( array_map( 'intval', explode( ',', $value ) ) ) );
	}

	/**
	 * Save an item's settings (all fields; missing ones reset to their defaults).
	 *
	 * @param int   $course_id  Course.
	 * @param int   $content_id Item.
	 * @param array $data       `category`, `max_marks` (null or > 0), `audience`, `class_ids`, `due_at` (ISO 8601 or null).
	 * @param int   $actor      Acting user.
	 * @return array|WP_Error The saved item.
	 */
	public static function save( $course_id, $content_id, array $data, $actor ) {
		global $wpdb;
		if ( ! Schema::ensure() ) {
			return new WP_Error( 'ohmylms_gradebook_storage', __( 'The gradebook could not be saved.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		$item = self::find( $course_id, $content_id );
		if ( ! $item ) {
			return new WP_Error( 'ohmylms_gradebook_item_not_found', __( 'This item is not a graded item of the course.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$problems = array();
		$category = isset( $data['category'] ) ? (string) $data['category'] : '';
		if ( '' !== $category && ! preg_match( '/^[a-z0-9][a-z0-9_-]{0,39}$/', $category ) ) {
			$problems['category'] = __( 'Use lowercase letters, digits, hyphens and underscores (at most 40).', 'ohmylms' );
		}
		$max = $data['max_marks'] ?? null;
		if ( null !== $max && ( ! is_numeric( $max ) || (float) $max <= 0 || (float) $max > 100000 ) ) {
			$problems['max_marks'] = __( 'Enter a maximum above 0 and at most 100000, or leave it empty.', 'ohmylms' );
		}
		$audience = isset( $data['audience'] ) ? (string) $data['audience'] : 'all';
		if ( ! in_array( $audience, self::AUDIENCES, true ) ) {
			$problems['audience'] = __( 'Choose all learners, some classes or individual learners.', 'ohmylms' );
		}
		$class_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) ( $data['class_ids'] ?? array() ) ) ) ) );
		if ( 'classes' === $audience ) {
			$linked = Repository::linked_classes( $course_id );
			if ( ! $class_ids || array_diff( $class_ids, $linked ) ) {
				$problems['class_ids'] = __( 'Choose one or more classes linked to this course.', 'ohmylms' );
			}
		} else {
			$class_ids = array();
		}
		$due = null;
		if ( ! empty( $data['due_at'] ) ) {
			$due = self::utc( (string) $data['due_at'] );
			if ( null === $due ) {
				$problems['due_at'] = __( 'Enter a valid date and time.', 'ohmylms' );
			}
		}
		if ( $problems ) {
			return new WP_Error(
				'ohmylms_gradebook_invalid_item',
				__( 'Some item settings are not valid.', 'ohmylms' ),
				array(
					'status' => 422,
					'params' => $problems,
				)
			);
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Gradebook write; caches are versioned.
		$saved = $wpdb->replace(
			Schema::table( 'gradebook_items' ),
			array(
				'course_id'  => (int) $course_id,
				'content_id' => (int) $content_id,
				'category'   => $category,
				'max_marks'  => null === $max ? null : round( (float) $max, 4 ),
				'audience'   => $audience,
				'class_ids'  => implode( ',', $class_ids ),
				'due_at'     => $due,
				'updated_by' => (int) $actor,
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		if ( false === $saved ) {
			return new WP_Error( 'ohmylms_gradebook_storage', __( 'The gradebook could not be saved.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		Version::bump();
		return self::find( $course_id, $content_id );
	}

	/**
	 * Parse an ISO 8601 date (site time zone when it has none) to UTC `Y-m-d H:i:s`.
	 *
	 * @param string $value Value.
	 * @return string|null
	 */
	public static function utc( $value ) {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2})?)?(Z|[+-]\d{2}:?\d{2})?$/', $value ) ) {
			return null;
		}
		try {
			$date = new \DateTimeImmutable( $value, wp_timezone() );
		} catch ( \Exception $exception ) {
			return null;
		}
		return $date->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * UTC `Y-m-d H:i:s` to ISO 8601 with the site offset.
	 *
	 * @param string|null $value Value.
	 * @return string|null
	 */
	public static function iso( $value ) {
		if ( ! $value || '0000-00-00 00:00:00' === $value ) {
			return null;
		}
		try {
			return ( new \DateTimeImmutable( $value, new \DateTimeZone( 'UTC' ) ) )->setTimezone( wp_timezone() )->format( DATE_ATOM );
		} catch ( \Exception $exception ) {
			return null;
		}
	}
}
