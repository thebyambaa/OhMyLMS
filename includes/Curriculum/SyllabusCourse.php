<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * A syllabus is also a course. Every syllabus has one real course (an ordinary `ohmylms-course`), so it is
 * listed in the Content Hub catalog, can be published, priced, enrolled in and put in a Course Bundle like
 * any other course, and its lessons, quizzes and assignments are attached the way the catalog already does.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Curriculum;

use OhMyLMS\Learning\Catalog;
use OhMyLMS\Learning\CourseProgram;

defined( 'ABSPATH' ) || exit;

/**
 * A syllabus is also a course. Every syllabus has one real course (an ordinary `ohmylms-course`), so it is
 * listed in the Content Hub catalog, can be published, priced, enrolled in and put in a Course Bundle like
 * any other course, and its lessons, quizzes and assignments are attached the way the catalog already does.
 *
 * The syllabus owns the structure and the course mirrors it, one way:
 *
 *   skill group  -> a chapter of the course (a group of skills, the way a chapter groups lessons)
 *   skill        -> an outcome of the course's learning-program draft, in its group's chapter
 *   syllabus name -> the course title
 *
 * Only the draft is written. Learners see nothing until the course is published from the catalog, which is
 * the course's own publish with its readiness checks. Anything the catalog added by hand (a chapter of its
 * own, a skill outside the syllabus, attachments, the learning mode, targets and "required" flags) is left
 * alone: a chapter mirrors a group when it carries that group's uuid, and only such chapters, and the
 * skills placed in them, are managed.
 */
final class SyllabusCourse {
	/** On a chapter post: the uuid of the skill group it mirrors. */
	const GROUP_META = '_ohmylms_syllabus_group';
	/** On the course post: the syllabus item it belongs to. */
	const ITEM_META   = '_ohmylms_syllabus_item';
	const TITLE_LIMIT = 200;

	// ---- Planning (pure; no WordPress needed) -------------------------------------------------------

	/** The chapter title for a skill group: "C1.1 · Types of number", or just the name.
	 *
	 * @param array $group Group.
	 */
	public static function chapter_title( array $group ) {
		$name  = trim( (string) ( $group['name'] ?? '' ) );
		$code  = trim( (string) ( $group['code'] ?? '' ) );
		$title = ( '' === $code || $code === $name ) ? $name : $code . ' · ' . $name;
		$title = trim( wp_strip_all_tags( $title ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $title, 0, self::TITLE_LIMIT ) : substr( $title, 0, self::TITLE_LIMIT );
	}

	/**
	 * What to do with the course's chapters so they mirror the groups.
	 *
	 * @param array[] $chapters Existing chapters in order: ['id' => int, 'name' => string, 'group' => uuid or ''].
	 * @param array[] $groups The syllabus's groups in outline order: ['uuid', 'name', 'code'].
	 * @return array{create:array,rename:array,keep:array,orphans:int[]}
	 *   create: group uuid => title; rename: chapter id => title; keep: group uuid => chapter id; orphans: chapter ids
	 */
	public static function plan_chapters( array $chapters, array $groups ) {
		$by_group = array();
		foreach ( $chapters as $chapter ) {
			if ( ( $chapter['group'] ?? '' ) !== '' && ! isset( $by_group[ $chapter['group'] ] ) ) {
				$by_group[ $chapter['group'] ] = $chapter; }
		}
		$plan   = array(
			'create'  => array(),
			'rename'  => array(),
			'keep'    => array(),
			'orphans' => array(),
		);
		$wanted = array();
		foreach ( $groups as $group ) {
			$wanted[ $group['uuid'] ] = true;
			$title                    = self::chapter_title( $group );
			if ( isset( $by_group[ $group['uuid'] ] ) ) {
				$chapter                        = $by_group[ $group['uuid'] ];
				$plan['keep'][ $group['uuid'] ] = (int) $chapter['id'];
				if ( trim( wp_strip_all_tags( (string) $chapter['name'] ) ) !== $title ) {
					$plan['rename'][ (int) $chapter['id'] ] = $title; }
			} else {
				$plan['create'][ $group['uuid'] ] = $title;
			}
		}
		foreach ( $chapters as $chapter ) {
			if ( ( $chapter['group'] ?? '' ) !== '' && ! isset( $wanted[ $chapter['group'] ] ) ) {
				$plan['orphans'][] = (int) $chapter['id']; }
		}
		return $plan;
	}

	/**
	 * The course's outcomes after mirroring: those the syllabus does not manage stay where they were, then every
	 * skill of every group follows in outline order, each in its group's chapter. A skill that is already an
	 * outcome keeps its target and "required" flag; a new one starts at Proficient and not required, so a big
	 * syllabus can be published before every skill has questions.
	 *
	 * @param array[] $current The draft's outcomes.
	 * @param array[] $groups Groups in outline order: ['uuid', 'skills' => [['term_id' => int]]].
	 * @param array   $chapter_of group uuid => chapter id.
	 * @param int[]   $managed Chapter ids that mirror a group (including ones about to be removed).
	 * @return array[]
	 */
	public static function plan_outcomes( array $current, array $groups, array $chapter_of, array $managed ) {
		$mine = array();
		foreach ( $groups as $group ) {
			foreach ( $group['skills'] as $skill ) {
				$mine[ (int) $skill['term_id'] ] = true; }
		}
		$previous = array();
		$next     = array();
		foreach ( $current as $outcome ) {
			$term         = (int) $outcome['term_id'];
			$managed_here = in_array( (int) ( $outcome['chapter_id'] ?? 0 ), $managed, true );
			if ( $managed_here || isset( $mine[ $term ] ) ) {
				$previous[ $term ] = $outcome; } else {
				$next[] = $outcome; }
		}
		$placed = array();
		foreach ( $groups as $group ) {
			foreach ( $group['skills'] as $skill ) {
				$term = (int) $skill['term_id'];
				if ( isset( $placed[ $term ] ) || ! isset( $chapter_of[ $group['uuid'] ] ) ) {
					continue; }
				$placed[ $term ] = true;
				$old             = $previous[ $term ] ?? null;
				$next[]          = array(
					'term_id'    => $term,
					'target'     => $old['target'] ?? 'proficient',
					'required'   => $old ? ! empty( $old['required'] ) : false,
					'chapter_id' => (int) $chapter_of[ $group['uuid'] ],
				);
			}
		}
		return $next;
	}

	/**
	 * Apply requirement changes to outcomes, leaving everything else as it is.
	 *
	 * @param array[] $outcomes The draft's outcomes.
	 * @param array[] $changes `['term_id' => n, 'required' => bool, 'target' => 'proficient'|'mastered']`; either of the last two may be left out.
	 * @return array[]
	 */
	public static function plan_requirements( array $outcomes, array $changes ) {
		$by_term = array();
		foreach ( $changes as $change ) {
			if ( is_array( $change ) && isset( $change['term_id'] ) ) {
				$by_term[ (int) $change['term_id'] ] = $change; }
		}
		$next = array();
		foreach ( $outcomes as $outcome ) {
			$change = $by_term[ (int) $outcome['term_id'] ] ?? null;
			if ( $change ) {
				if ( array_key_exists( 'required', $change ) ) {
					$outcome['required'] = (bool) $change['required']; }
				if ( isset( $change['target'] ) && in_array( $change['target'], array( 'proficient', 'mastered' ), true ) ) {
					$outcome['target'] = $change['target']; }
			}
			$next[] = $outcome;
		}
		return $next;
	}

	/** Do two outcome lists say the same thing, in the same order?
	 *
	 * @param array $left Left.
	 * @param array $right Right.
	 */
	public static function same_outcomes( array $left, array $right ) {
		$shape = static function ( $outcome ) {
			return array( (int) $outcome['term_id'], (string) $outcome['target'], ! empty( $outcome['required'] ), (int) ( $outcome['chapter_id'] ?? 0 ) );
		};
		return array_map( $shape, $left ) === array_map( $shape, $right );
	}

	/** Chapter ids in the order the course should show them: mirrored chapters as the groups run, then the rest as they were.
	 *
	 * @param array $current_ids Current ids.
	 * @param array $mirrored_in_order Mirrored in order.
	 */
	public static function chapter_order( array $current_ids, array $mirrored_in_order ) {
		$mirrored = array_values(
			array_filter(
				$mirrored_in_order,
				static function ( $id ) use ( $current_ids ) {
					return in_array( $id, $current_ids, true );
				}
			)
		);
		return array_merge( $mirrored, array_values( array_diff( $current_ids, $mirrored ) ) );
	}

	// ---- The link between a syllabus and its course -----------------------------------------------------

	/** The syllabus's course, or 0 when it has none (or the course was deleted).
	 *
	 * @param mixed $syllabus_id Syllabus identifier.
	 */
	public static function course_id( $syllabus_id ) {
		$item = Items::get( $syllabus_id );
		$id   = $item ? (int) ( $item['course_id'] ?? 0 ) : 0;
		return $id > 0 && self::is_course( $id ) ? $id : 0;
	}

	/**
	 * Is course for Syllabus course.
	 *
	 * @param mixed $id Id.
	 */
	private static function is_course( $id ) {
		$status = get_post_status( $id );
		return get_post_type( $id ) === OHMYLMS_COURSE_CPT && $status && ! in_array( $status, array( 'trash', 'auto-draft' ), true );
	}

	/** The syllabus a course belongs to (0 for an ordinary course).
	 *
	 * @param mixed $course_id Course identifier.
	 */
	public static function owner( $course_id ) {
		$course_id = (int) $course_id;
		if ( $course_id <= 0 || ! Schema::ready() ) {
			return 0; }
		$item = (int) get_post_meta( $course_id, self::ITEM_META, true );
		$row  = $item > 0 ? Items::get( $item ) : null;
		return $row && (int) ( $row['course_id'] ?? 0 ) === $course_id && ! empty( $row['is_syllabus'] ) ? $item : 0;
	}

	/** A light description of the course for the editor, or null when the syllabus has none yet.
	 *
	 * @param mixed $syllabus_id Syllabus identifier.
	 */
	public static function summary( $syllabus_id ) {
		$course_id = self::course_id( $syllabus_id );
		if ( ! $course_id ) {
			return null; }
		return Catalog::summary( $course_id ) + array(
			'edit'            => '#/content-hub/curriculum/syllabus/' . (int) $syllabus_id . '?view=settings',
			'directory'       => Directory::url( $syllabus_id ),
			'preview'         => Directory::url( $syllabus_id, true ),
			'directory_ready' => is_array( get_post_meta( $course_id, Directory::META, true ) ),
		);
	}

	/** The outline with each skill group told which chapter of the course it became (0 when there is no such chapter).
	 *
	 * @param array $outline Outline.
	 * @param mixed $syllabus_id Syllabus identifier.
	 */
	public static function decorate( array $outline, $syllabus_id ) {
		$course_id  = self::course_id( $syllabus_id );
		$chapter_of = array();
		if ( $course_id ) {
			foreach ( Catalog::chapters( $course_id ) as $chapter ) {
				$uuid = (string) get_post_meta( $chapter['id'], self::GROUP_META, true );
				if ( '' !== $uuid ) {
					$chapter_of[ $uuid ] = (int) $chapter['id']; }
			}
		}
		foreach ( $outline['contents'] as $at => $content ) {
			foreach ( $content['groups'] as $position => $group ) {
				$outline['contents'][ $at ]['groups'][ $position ]['chapter_id'] = $chapter_of[ $group['uuid'] ] ?? 0; }
		}
		return $outline;
	}

	/**
	 * Create course for Syllabus course.
	 *
	 * @param mixed $name Name.
	 */
	private static function create_course( $name ) {
		$request = new \WP_REST_Request( 'POST', '/ohmylms/v1/courses' );
		$request->set_body_params(
			array(
				'title'             => $name,
				'name'              => $name,
				'status'            => 'draft',
				'course_type'       => 'self-paced',
				'creation_method'   => '',
				'isCommunityEnable' => 'no',
			)
		);
		$response = rest_do_request( $request );
		if ( $response->is_error() ) {
			return $response->as_error(); }
		$data = (array) $response->get_data();
		$id   = (int) ( $data['id'] ?? 0 );
		return $id > 0 ? $id : Access::error( 'ohmylms_syllabus_course_failed', __( 'The course for this syllabus could not be created.', 'ohmylms' ), 500 );
	}

	/**
	 * Give a syllabus its course if it has none. Safe to repeat: a syllabus keeps the course it has.
	 * The course starts as a draft in skill-based mode and is placed under the syllabus in the curriculum.
	 *
	 * @return int|\WP_Error The course ID.
	 * @param mixed $syllabus_id Syllabus identifier.
	 */
	public static function ensure( $syllabus_id ) {
		global $wpdb;
		$item = Items::get( $syllabus_id );
		if ( ! $item ) {
			return Items::missing(); }
		if ( empty( $item['is_syllabus'] ) ) {
			return Access::error( 'ohmylms_syllabus_invalid', __( 'This item is not a syllabus. Turn on “This item is a syllabus” first.', 'ohmylms' ), 400 ); }
		$existing = self::course_id( $syllabus_id );
		if ( $existing ) {
			return $existing; }
		$created = self::create_course( Syllabus::text( $item['name'] ) );
		if ( is_wp_error( $created ) ) {
			return $created; }
		// Another request may have given the syllabus a course meanwhile; keep that one and drop ours.
		$kept = Items::exclusive(
			static function () use ( $wpdb, $syllabus_id, $created ) {
				$current = self::course_id( $syllabus_id );
				if ( $current ) {
					return $current; }
				if ( $wpdb->update(
					Items::table(),
					array(
						'course_id'  => (int) $created,
						'updated_at' => current_time( 'mysql', true ),
					),
					array( 'id' => (int) $syllabus_id )
				) === false ) {
					throw new \RuntimeException( 'Course link write failed' ); }
				return (int) $created;
			}
		);
		if ( is_wp_error( $kept ) ) {
			wp_delete_post( $created, true );
			return $kept; }
		if ( (int) $kept !== (int) $created ) {
			wp_delete_post( $created, true );
			return (int) $kept; }
		update_post_meta( $created, self::ITEM_META, (int) $syllabus_id );
		// A new course comes with one empty "Untitled" chapter; the chapters of a syllabus course are its skill groups.
		foreach ( Catalog::chapters( $created ) as $chapter ) {
			Catalog::delete_chapter( $created, $chapter['id'] ); }
		// The course sits under its syllabus, so placement and Course Bundles see it like any linked course.
		Links::add( $syllabus_id, 'course', $created );
		$draft = Catalog::save_draft( $created, array( 'mode' => 'skill-based' ) );
		if ( is_wp_error( $draft ) ) {
			return $draft; }
		return (int) $created;
	}

	// ---- Mirroring -------------------------------------------------------------------------------------

	/** The syllabus's groups in outline order, as the planner wants them.
	 *
	 * @param array $outline Outline.
	 */
	private static function groups_of( array $outline ) {
		$groups = array();
		foreach ( $outline['contents'] as $content ) {
			foreach ( $content['groups'] as $group ) {
				$groups[] = $group; }
		}
		return $groups;
	}

	/**
	 * Make the course mirror the syllabus: its title, one chapter per skill group and one outcome per skill.
	 * Nothing is written when nothing differs. @return true|\WP_Error
	 *
	 * @param mixed $syllabus_id Syllabus identifier.
	 */
	public static function sync( $syllabus_id ) {
		$course_id = self::course_id( $syllabus_id );
		if ( ! $course_id ) {
			return true; }
		return \OhMyLMS\Learning\CourseStructure::atomic(
			$course_id,
			Catalog::revision( $course_id ),
			static function () use ( $syllabus_id, $course_id ) {
				return self::sync_course( $syllabus_id, $course_id );
			}
		);
	}

	/**
	 * Mirror one syllabus while holding the common course lock and transaction.
	 *
	 * @param int $syllabus_id Syllabus ID.
	 * @param int $course_id Existing mirrored course ID.
	 * @return true|\WP_Error
	 */
	private static function sync_course( $syllabus_id, $course_id ) {
		$outline = Syllabus::outline( $syllabus_id );
		if ( is_wp_error( $outline ) ) {
			return $outline; }
		$groups = self::groups_of( $outline );

		$title = trim( wp_strip_all_tags( (string) $outline['syllabus']['name'] ) );
		if ( '' !== $title && trim( wp_strip_all_tags( get_the_title( $course_id ) ) ) !== $title ) {
			$updated = wp_update_post(
				wp_slash(
					array(
						'ID'         => $course_id,
						'post_title' => $title,
					)
				),
				true
			);
			if ( is_wp_error( $updated ) ) {
				return $updated; }
		}

		$chapters = array_map(
			static function ( $chapter ) {
				return $chapter + array( 'group' => (string) get_post_meta( $chapter['id'], self::GROUP_META, true ) );
			},
			Catalog::chapters( $course_id )
		);
		$plan     = self::plan_chapters( $chapters, $groups );
		foreach ( $plan['rename'] as $chapter_id => $name ) {
			$renamed = Catalog::rename_chapter( $course_id, $chapter_id, $name );
			if ( is_wp_error( $renamed ) ) {
				return $renamed; }
		}
		$chapter_of = $plan['keep'];
		foreach ( $plan['create'] as $uuid => $name ) {
			$made = Catalog::add_chapter( $course_id, $name );
			if ( is_wp_error( $made ) ) {
				return $made; }
			update_post_meta( $made['id'], self::GROUP_META, $uuid );
			$chapter_of[ $uuid ] = (int) $made['id'];
		}

		$draft   = CourseProgram::editor( $course_id )['draft'];
		$managed = array_merge( array_values( $chapter_of ), $plan['orphans'] );
		$next    = self::plan_outcomes( $draft['outcomes'], $groups, $chapter_of, $managed );
		if ( ! self::same_outcomes( $draft['outcomes'], $next ) ) {
			$saved = Catalog::save_draft( $course_id, array( 'outcomes' => $next ) );
			if ( is_wp_error( $saved ) ) {
				return $saved; }
		}

		// A chapter whose group is gone is removed unless the course still uses it (the catalog refuses then).
		foreach ( $plan['orphans'] as $chapter_id ) {
			Catalog::delete_chapter( $course_id, $chapter_id ); }

		$current  = array_column( Catalog::chapters( $course_id ), 'id' );
		$mirrored = array();
		foreach ( $groups as $group ) {
			if ( isset( $chapter_of[ $group['uuid'] ] ) ) {
				$mirrored[] = (int) $chapter_of[ $group['uuid'] ]; }
		}
		$order = self::chapter_order( $current, $mirrored );
		if ( $order !== $current ) {
			$ordered = Catalog::reorder_chapters( $course_id, $order );
			if ( is_wp_error( $ordered ) ) {
				return $ordered; }
		}
		return true;
	}

	/**
	 * Say which skills of the syllabus the course requires, and at what target. This is the one thing the syllabus
	 * does not decide: a course can only be published once a skill is required, and a required skill needs approved
	 * questions, so authors turn skills on as they become ready. Only the skills of this syllabus change, and only
	 * their requirement and target. @return true|\WP_Error
	 *
	 * @param mixed $syllabus_id Syllabus identifier.
	 * @param array $changes Changes.
	 */
	public static function set_requirements( $syllabus_id, array $changes ) {
		$course_id = self::course_id( $syllabus_id );
		if ( ! $course_id ) {
			return Access::error( 'ohmylms_syllabus_course_missing', __( 'This syllabus has no course yet.', 'ohmylms' ), 404 ); }
		$mine    = Syllabus::skill_ids( Items::with_descendants( $syllabus_id ) );
		$changes = array_values(
			array_filter(
				$changes,
				static function ( $change ) use ( $mine ) {
					return is_array( $change ) && in_array( (int) ( $change['term_id'] ?? 0 ), $mine, true );
				}
			)
		);
		if ( ! $changes ) {
			return true; }
		$current = CourseProgram::editor( $course_id )['draft']['outcomes'];
		$next    = self::plan_requirements( $current, $changes );
		if ( self::same_outcomes( $current, $next ) ) {
			return true; }
		$saved = Catalog::save_draft( $course_id, array( 'outcomes' => $next ) );
		return is_wp_error( $saved ) ? $saved : true;
	}

	/**
	 * Run a mirror step without ever failing the change that caused it: the syllabus is saved either way, and the
	 * editor offers "Update course" when the course could not follow. @return true|\WP_Error
	 *
	 * @param mixed $syllabus_id Syllabus identifier.
	 */
	public static function sync_quietly( $syllabus_id ) {
		try {
			$result = self::sync( $syllabus_id );
		} catch ( \Throwable $error ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'OhMyLMS syllabus course sync failed: ' . $error->getMessage() ); }
			return Access::error( 'ohmylms_syllabus_course_failed', __( 'The course could not be updated to match the syllabus.', 'ohmylms' ), 500 );
		}
		return $result;
	}

	/** After items change: bring the course of each syllabus they belong to up to date.
	 *
	 * @param array $syllabus_ids Syllabus ids.
	 */
	public static function after_items_changed( array $syllabus_ids ) {
		foreach ( array_unique( array_filter( array_map( 'intval', $syllabus_ids ) ) ) as $id ) {
			self::sync_quietly( $id ); }
	}
}
