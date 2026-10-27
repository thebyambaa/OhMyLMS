<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Content Hub catalog. A grade, exam or subject (MATH0580, Digital SAT) is an ordinary course whose
 * learning program says which chapters it has, which skills sit in each chapter, and which lessons,
 * quizzes and assessments are attached to a chapter or to chosen skills.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Learning;

use OhMyLMS\Curriculum\UnitNumber;
use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * Content Hub catalog. A grade, exam or subject (MATH0580, Digital SAT) is an ordinary course whose
 * learning program says which chapters it has, which skills sit in each chapter, and which lessons,
 * quizzes and assessments are attached to a chapter or to chosen skills.
 *
 * Nothing here stores a new kind of record: skills stay shared library terms, lessons stay reusable
 * posts, and the structure is the program draft. Learners only see it after an explicit publish, which
 * creates an immutable program version exactly as the course's Learning tab does.
 */
final class Catalog {
	const MODES = CourseProgram::MODES;

	/**
	 * Error for Catalog.
	 *
	 * @param mixed  $message Message.
	 * @param int    $status Status.
	 * @param string $code Code.
	 */
	public static function error( $message, $status = 400, $code = 'ohmylms_catalog_invalid' ) {
		return new \WP_Error( $code, $message, array( 'status' => $status ) );
	}

	/**
	 * Can edit for Catalog.
	 *
	 * @param mixed $course_id Course identifier.
	 */
	public static function can_edit( $course_id ) {
		return get_post_type( (int) $course_id ) === OHMYLMS_COURSE_CPT && current_user_can( 'edit_post', (int) $course_id );
	}

	/**
	 * Post type for Catalog.
	 *
	 * @param mixed $type Type.
	 */
	public static function post_type( $type ) {
		return array(
			'lesson'     => OHMYLMS_LESSON_CPT,
			'quiz'       => OHMYLMS_QUIZ_CPT,
			'assignment' => 'ohmylms-assignment',
		)[ $type ] ?? '';
	}

	/**
	 * Title for Catalog.
	 *
	 * @param mixed $id Id.
	 */
	private static function title( $id ) {
		if ( OHMYLMS_CHAPTER_CPT === get_post_type( $id ) ) {
			return html_entity_decode( (string) get_post_field( 'post_title', $id ), ENT_QUOTES, 'UTF-8' );
		}
		return html_entity_decode( (string) get_the_title( (int) $id ), ENT_QUOTES, 'UTF-8' );
	}

	/** The structure the editor works on: the draft, or the published program, or the course's defaults.
	 *
	 * @param mixed $course_id Course identifier.
	 */
	private static function draft( $course_id ) {
		return CourseProgram::editor( (int) $course_id )['draft'];
	}

	// ---- Chapters (units) -------------------------------------------------------------------------------

	/**
	 * Chapters for Catalog.
	 *
	 * @return array<int, array{id:int,name:string,number:string}> `number` is the author's unit label, '' for automatic numbering.
	 * @param mixed $course_id Course identifier.
	 */
	public static function chapters( $course_id ) {
		global $wpdb;
		$ids      = $wpdb->get_col( $wpdb->prepare( 'SELECT chapter_id FROM %i WHERE course_id=%d ORDER BY order_number ASC, id ASC', $wpdb->prefix . 'ohmylms_chapter_relationship', (int) $course_id ) );
		$chapters = array();
		foreach ( $ids as $id ) {
			$post = get_post( (int) $id );
			if ( $post && OHMYLMS_CHAPTER_CPT === $post->post_type && 'trash' !== $post->post_status ) {
				$chapters[] = array(
					'id'     => (int) $id,
					'name'   => self::title( $id ),
					'number' => (string) get_post_meta( $id, UnitNumber::META, true ),
				); }
		}
		return $chapters;
	}

	/**
	 * Belongs for Catalog.
	 *
	 * @param mixed $course_id Course identifier.
	 * @param mixed $chapter_id Chapter identifier.
	 */
	private static function belongs( $course_id, $chapter_id ) {
		return in_array( (int) $chapter_id, array_column( self::chapters( $course_id ), 'id' ), true );
	}

	/**
	 * Add chapter for Catalog.
	 *
	 * @param mixed $course_id Course identifier.
	 * @param mixed $name Name.
	 */
	public static function add_chapter( $course_id, $name ) {
		global $wpdb;
		$name = trim( wp_strip_all_tags( (string) $name ) );
		if ( '' === $name || mb_strlen( $name ) > 200 ) {
			return self::error( __( 'Give the chapter a name of up to 200 characters.', 'ohmylms' ) ); }
		$id = wp_insert_post(
			array(
				'post_type'   => OHMYLMS_CHAPTER_CPT,
				'post_title'  => $name,
				'post_status' => 'publish',
				'post_author' => get_current_user_id(),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id; }
		$order = 1 + (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(order_number) FROM %i WHERE course_id=%d', $wpdb->prefix . 'ohmylms_chapter_relationship', (int) $course_id ) );
		if ( ! $wpdb->insert(
			$wpdb->prefix . 'ohmylms_chapter_relationship',
			array(
				'course_id'    => (int) $course_id,
				'chapter_id'   => (int) $id,
				'order_number' => $order,
			)
		) ) {
			wp_delete_post( $id, true );
			return self::error( __( 'The chapter could not be added.', 'ohmylms' ), 500 );
		}
		return array(
			'id'   => (int) $id,
			'name' => $name,
		);
	}

	/**
	 * Rename chapter for Catalog.
	 *
	 * @param mixed $course_id Course identifier.
	 * @param mixed $chapter_id Chapter identifier.
	 * @param mixed $name Name.
	 */
	public static function rename_chapter( $course_id, $chapter_id, $name ) {
		if ( ! self::belongs( $course_id, $chapter_id ) ) {
			return self::error( __( 'The chapter does not belong to this course.', 'ohmylms' ), 404 ); }
		$name = trim( wp_strip_all_tags( (string) $name ) );
		if ( '' === $name || mb_strlen( $name ) > 200 ) {
			return self::error( __( 'Give the chapter a name of up to 200 characters.', 'ohmylms' ) ); }
		$result = wp_update_post(
			array(
				'ID'         => (int) $chapter_id,
				'post_title' => $name,
			),
			true
		);
		return is_wp_error( $result ) ? $result : array(
			'id'   => (int) $chapter_id,
			'name' => $name,
		);
	}

	/**
	 * Change a chapter's name and/or unit number. Everything is checked before anything is written.
	 *
	 * @param int   $course_id Course ID.
	 * @param int   $chapter_id Chapter ID.
	 * @param array $fields Any of `name` and `number`.
	 * @return array|\WP_Error
	 */
	public static function update_chapter( $course_id, $chapter_id, array $fields ) {
		if ( ! self::belongs( $course_id, $chapter_id ) ) {
			return self::error( __( 'The chapter does not belong to this course.', 'ohmylms' ), 404 ); }
		$has_number = array_key_exists( 'number', $fields );
		if ( $has_number && null === UnitNumber::normalize( $fields['number'] ) ) {
			return self::error( __( 'A unit number is up to 10 letters, digits, dots or dashes, for example 3 or 2A.', 'ohmylms' ) ); }
		if ( array_key_exists( 'name', $fields ) || ! $has_number ) {
			$renamed = self::rename_chapter( $course_id, $chapter_id, (string) ( $fields['name'] ?? '' ) );
			if ( is_wp_error( $renamed ) ) {
				return $renamed; }
		}
		if ( $has_number ) {
			UnitNumber::set( $chapter_id, $fields['number'] ); }
		return array(
			'id'     => (int) $chapter_id,
			'name'   => self::title( $chapter_id ),
			'number' => UnitNumber::get( $chapter_id ),
		);
	}

	/** Chapters must be given in full, so a partial list can never silently drop one.
	 *
	 * @param mixed $course_id Course identifier.
	 * @param array $ids Ids.
	 */
	public static function reorder_chapters( $course_id, array $ids ) {
		global $wpdb;
		$ids     = array_values( array_map( 'intval', $ids ) );
		$current = array_column( self::chapters( $course_id ), 'id' );
		if ( count( $ids ) !== count( array_unique( $ids ) ) || count( $ids ) !== count( $current ) || array_diff( $ids, $current ) || array_diff( $current, $ids ) ) {
			return self::error( __( 'List every chapter of this course exactly once.', 'ohmylms' ) ); }
		foreach ( $ids as $position => $id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Native ordering; the shared structure boundary manages transactions and cache invalidation.
			$changed = $wpdb->update(
				$wpdb->prefix . 'ohmylms_chapter_relationship',
				array( 'order_number' => $position + 1 ),
				array(
					'course_id'  => (int) $course_id,
					'chapter_id' => $id,
				)
			);
			if ( false === $changed ) {
				return self::error( __( 'The unit order could not be saved.', 'ohmylms' ), 500 );
			}
		}
		return true;
	}

	/**
	 * Delete a chapter. A chapter that still holds lessons or quizzes in the course editor, or that the
	 * published program refers to, is kept: removing it would orphan content or break enrolled learners.
	 *
	 * @param mixed $course_id Course identifier.
	 * @param mixed $chapter_id Chapter identifier.
	 */
	public static function delete_chapter( $course_id, $chapter_id ) {
		global $wpdb;
		$chapter_id = (int) $chapter_id;
		if ( ! self::belongs( $course_id, $chapter_id ) ) {
			return self::error( __( 'The chapter does not belong to this course.', 'ohmylms' ), 404 ); }
		if ( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE chapter_id=%d', $wpdb->prefix . 'ohmylms_content_relationship', $chapter_id ) ) ) {
			return self::error( __( 'This chapter still holds lessons or quizzes in the course editor. Move or remove them there first.', 'ohmylms' ), 409, 'ohmylms_chapter_in_use' );
		}
		$published = CourseProgram::current( (int) $course_id );
		if ( $published && ( in_array( $chapter_id, array_map( 'intval', array_column( $published['outcomes'], 'chapter_id' ) ), true ) || in_array( $chapter_id, array_map( 'intval', array_column( $published['items'], 'chapter_id' ) ), true ) ) ) {
			return self::error( __( 'This chapter is part of the published program. Move its skills and content elsewhere, publish, then delete it.', 'ohmylms' ), 409, 'ohmylms_chapter_published' );
		}
		// Skills and attachments that sat in it stay in the course, unplaced.
		$draft = self::draft( $course_id );
		foreach ( $draft['outcomes'] as &$outcome ) {
			if ( (int) ( $outcome['chapter_id'] ?? 0 ) === $chapter_id ) {
				unset( $outcome['chapter_id'], $outcome['group_id'] ); }
		}
		unset( $outcome );
		foreach ( $draft['items'] as &$item ) {
			if ( (int) ( $item['chapter_id'] ?? 0 ) === $chapter_id ) {
				$item['chapter_id'] = 0;
				unset( $item['group_id'] ); }
		}
		unset( $item );
		// Lesson groups of the deleted unit go with it.
		if ( ! empty( $draft['groups'] ) ) {
			$draft['groups'] = array_values(
				array_filter(
					$draft['groups'],
					static function ( $group ) use ( $chapter_id ) {
						return (int) $group['chapter_id'] !== $chapter_id;
					}
				)
			);
			if ( ! $draft['groups'] ) {
				unset( $draft['groups'] ); }
		}
		update_post_meta( (int) $course_id, CourseProgram::DRAFT, $draft );
		$wpdb->delete(
			$wpdb->prefix . 'ohmylms_chapter_relationship',
			array(
				'course_id'  => (int) $course_id,
				'chapter_id' => $chapter_id,
			)
		);
		wp_delete_post( $chapter_id, true );
		return true;
	}

	// ---- Structure ---------------------------------------------------------------------------------------

	/**
	 * Fingerprint for Catalog.
	 *
	 * @param mixed $program Program.
	 */
	private static function fingerprint( $program ) {
		if ( ! is_array( $program ) ) {
			return ''; }
		$keep = array( 'mode', 'recognize_prior', 'evidence_days', 'bank_ids', 'outcomes', 'items', 'groups', 'units' );
		return md5( wp_json_encode( array_intersect_key( $program, array_flip( $keep ) ) ) );
	}

	/** Courses that place this content other than `$course_id`, as `[{id,title}]`.
	 *
	 * @param mixed $content_id Content identifier.
	 * @param mixed $course_id Course identifier.
	 */
	private static function also_in( $content_id, $course_id ) {
		$others = array_diff( Placements::courses_for( $content_id ), array( (int) $course_id ) );
		$home   = (int) ohmylms_get_course_id_by_chapter_id( (int) ohmylms_get_chapter_id_by_content_id( $content_id ) );
		if ( $home && $home !== (int) $course_id ) {
			$others[] = $home; }
		return array_map(
			static function ( $id ) {
				return array(
					'id'    => (int) $id,
					'title' => self::title( $id ),
				);
			},
			array_slice( array_values( array_unique( $others ) ), 0, 5 )
		);
	}

	/**
	 * Summary for Catalog.
	 *
	 * @param mixed $course_id Course identifier.
	 */
	public static function summary( $course_id ) {
		$draft       = self::draft( $course_id );
		$attachments = array_filter(
			$draft['items'],
			static function ( $item ) {
				return 'practice' !== $item['type'];
			}
		);
		return array(
			'id'          => (int) $course_id,
			'title'       => self::title( $course_id ),
			'status'      => get_post_status( $course_id ),
			'mode'        => $draft['mode'],
			'published'   => (bool) get_post_meta( $course_id, CourseProgram::CURRENT, true ),
			'chapters'    => count( self::chapters( $course_id ) ),
			'skills'      => count( $draft['outcomes'] ),
			'attachments' => count( $attachments ),
		);
	}

	/** The catalog tree for one grade, exam or subject.
	 *
	 * @param mixed $course_id Course identifier.
	 */
	public static function payload( $course_id ) {
		$editor = CourseProgram::editor( (int) $course_id );
		$draft  = $editor['draft'];
		$skills = array();
		foreach ( $draft['outcomes'] as $outcome ) {
			$term     = get_term( (int) $outcome['term_id'], Taxonomy::NAME );
			$valid    = $term && ! is_wp_error( $term );
			$skills[] = array(
				'term_id'    => (int) $outcome['term_id'],
				'name'       => $valid ? $term->name : __( 'Unavailable skill', 'ohmylms' ),
				'code'       => $valid ? (string) get_term_meta( $term->term_id, '_ohmylms_skill_code', true ) : '',
				'questions'  => $valid ? (int) $term->count : 0,
				'target'     => $outcome['target'],
				'required'   => ! empty( $outcome['required'] ),
				'chapter_id' => (int) ( $outcome['chapter_id'] ?? 0 ),
				'group_id'   => (string) ( $outcome['group_id'] ?? '' ),
				'missing'    => ! $valid,
			);
		}
		$attachments = array();
		foreach ( $draft['items'] as $item ) {
			if ( 'practice' === $item['type'] ) {
				continue; }
			$post          = get_post( (int) $item['content_id'] );
			$valid         = $post && $post->post_type === self::post_type( $item['type'] ) && 'trash' !== $post->post_status;
			$attachments[] = array(
				'id'                => $item['id'],
				'type'              => $item['type'],
				'content_id'        => (int) $item['content_id'],
				'native_editor_url' => $valid && 'lesson' === $item['type'] ? NativeLessonEditor::edit_url( $post->ID ) : '',
				'title'             => $item['title'] ?? ( $valid ? self::title( $post->ID ) : __( 'Unavailable content', 'ohmylms' ) ),
				'status'            => $valid ? $post->post_status : 'missing',
				'required'          => ! empty( $item['required'] ),
				'pass_percent'      => isset( $item['pass_percent'] ) ? (float) $item['pass_percent'] : null,
				'chapter_id'        => (int) ( $item['chapter_id'] ?? 0 ),
				'group_id'          => (string) ( $item['group_id'] ?? '' ),
				'skill_ids'         => array_map( 'intval', (array) ( $item['skill_ids'] ?? array() ) ),
				'also_in'           => $valid ? self::also_in( $post->ID, $course_id ) : array(),
			);
		}
		$published = $editor['published'];
		return array(
			'course'              => self::summary( $course_id ) + array( 'url' => $editor['url'] ),
			'mode'                => $draft['mode'],
			'published_version'   => $published ? (int) $published['version'] : 0,
			'unpublished_changes' => self::fingerprint( $draft ) !== self::fingerprint( $published ),
			'chapters'            => self::chapters( $course_id ),
			'skills'              => $skills,
			'attachments'         => $attachments,
			'readiness'           => CourseProgram::readiness_report( $draft, (int) $course_id ),
		);
	}

	/**
	 * Store the structure in the draft. Only outcomes, attachments and the mode are replaced; learning
	 * settings, practice steps and everything else in the draft are kept. Validation is the program's own.
	 *
	 * @param int                                                                  $course_id Course ID.
	 * @param array{mode?:string,outcomes?:array,attachments?:array,groups?:array} $data Parts of the structure to replace.
	 * @return true|\WP_Error
	 */
	public static function save_draft( $course_id, array $data ) {
		$program = self::check_draft( $course_id, $data );
		if ( is_wp_error( $program ) ) {
			return $program; }
		update_post_meta( (int) $course_id, CourseProgram::DRAFT, $program );
		return true;
	}

	/**
	 * Validate a structure change exactly as save_draft would, without storing it.
	 *
	 * @param int   $course_id Course ID.
	 * @param array $data Any of mode, outcomes, attachments and groups (lesson groups, replaced as a whole).
	 * @return array|\WP_Error The program that would be stored.
	 */
	public static function check_draft( $course_id, array $data ) {
		if ( ! Schema::ready() ) {
			return self::error( __( 'Learning storage is unavailable.', 'ohmylms' ), 503 ); }
		$draft = self::draft( $course_id );
		unset( $draft['id'], $draft['version'], $draft['course_id'] );
		$existing = $draft;
		if ( isset( $data['mode'] ) ) {
			if ( ! in_array( $data['mode'], self::MODES, true ) ) {
				return self::error( __( 'Choose a valid learning mode.', 'ohmylms' ) ); }
			$draft['mode'] = $data['mode'];
		}
		if ( array_key_exists( 'outcomes', $data ) ) {
			if ( ! is_array( $data['outcomes'] ) ) {
				return self::error( __( 'Skills must be a list.', 'ohmylms' ) ); }
			$draft['outcomes'] = ProgramGroups::carry( array_values( array_filter( $data['outcomes'], 'is_array' ) ), $existing['outcomes'], 'term_id' );
		}
		if ( array_key_exists( 'groups', $data ) ) {
			if ( ! is_array( $data['groups'] ) ) {
				return self::error( __( 'Lesson groups must be a list.', 'ohmylms' ) ); }
			$draft['groups'] = ProgramGroups::carry_skills( array_values( $data['groups'] ), $existing['groups'] ?? array() );
		}
		$kept     = array_column( $draft['outcomes'], 'term_id' );
		$kept     = array_map( 'intval', $kept );
		$practice = array_values(
			array_filter(
				$draft['items'],
				static function ( $item ) use ( $kept ) {
					return 'practice' === $item['type'] && in_array( (int) $item['content_id'], $kept, true );
				}
			)
		);
		if ( array_key_exists( 'attachments', $data ) ) {
			if ( ! is_array( $data['attachments'] ) ) {
				return self::error( __( 'Attachments must be a list.', 'ohmylms' ) ); }
			$attachments = ProgramGroups::carry( array_values( array_filter( $data['attachments'], 'is_array' ) ), $existing['items'], 'id' );
		} else {
			$attachments = array_values(
				array_filter(
					$draft['items'],
					static function ( $item ) {
						return 'practice' !== $item['type'];
					}
				)
			);
		}
		foreach ( $attachments as &$attachment ) {
			$attachment['skill_ids'] = array_values( array_intersect( array_map( 'intval', (array) ( $attachment['skill_ids'] ?? array() ) ), $kept ) );
			if ( empty( $attachment['id'] ) || ! is_string( $attachment['id'] ) ) {
				$attachment['id'] = wp_generate_uuid4(); }
		}
		unset( $attachment );
		$draft['items'] = array_merge( $attachments, $practice );
		$program        = CourseProgram::normalize( (int) $course_id, $draft );
		if ( is_wp_error( $program ) ) {
			return $program; }
		$program['author_id'] = get_current_user_id();
		return $program;
	}

	/** Publish the stored draft. This is the program's own publish: readiness checks, versioning, enrolled learners.
	 *
	 * @param mixed $course_id Course identifier.
	 * @param bool  $apply_existing Apply existing.
	 */
	public static function publish( $course_id, $apply_existing = false ) {
		$editor                       = CourseProgram::editor( (int) $course_id );
		$draft                        = $editor['draft'];
		$draft['expected_program_id'] = $editor['published'] ? (int) $editor['published']['id'] : 0;
		return CourseProgram::save( (int) $course_id, $draft, true, (bool) $apply_existing );
	}

	// ---- Structure for assistants (MCP) ------------------------------------------------------------------

	/** Units of a course with their position and the label printed on the hub.
	 *
	 * @param mixed $course_id Course identifier.
	 */
	public static function units( $course_id ) {
		$units = array();
		foreach ( self::chapters( $course_id ) as $position => $chapter ) {
			$units[] = array(
				'id'       => $chapter['id'],
				'title'    => $chapter['name'],
				'number'   => $chapter['number'],
				'label'    => UnitNumber::label( $chapter['number'], $position ),
				'position' => $position + 1,
			);
		}
		return $units;
	}

	/** A token for the stored draft; a write that quotes an older one is refused instead of overwriting newer work.
	 *
	 * @param mixed $course_id Course identifier.
	 */
	public static function revision( $course_id ) {
		$stored = get_post_meta( (int) $course_id, CourseProgram::DRAFT, true );
		$source = is_array( $stored ) ? $stored : CourseProgram::current( (int) $course_id );
		return md5( wp_json_encode( array( is_array( $source ) ? self::fingerprint( $source ) : 'empty', CourseStructure::native_version( $course_id ) ) ) );
	}

	/**
	 * The structure in a compact, read-only form: units, lesson groups, skills and attachments.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	public static function program_view( $course_id ) {
		$payload     = self::payload( $course_id );
		$draft       = self::draft( $course_id );
		$attachments = array();
		foreach ( $payload['attachments'] as $row ) {
			unset( $row['native_editor_url'], $row['also_in'] );
			$attachments[] = $row;
		}
		$skills = array();
		foreach ( $payload['skills'] as $row ) {
			unset( $row['questions'] );
			$skills[] = $row;
		}
		return array(
			'course'              => array_diff_key( $payload['course'], array( 'url' => 1 ) ),
			'revision'            => self::revision( $course_id ),
			'mode'                => $payload['mode'],
			'published_version'   => $payload['published_version'],
			'unpublished_changes' => $payload['unpublished_changes'],
			'units'               => self::units( $course_id ),
			'groups'              => array_values( (array) ( $draft['groups'] ?? array() ) ),
			'outcomes'            => $skills,
			'attachments'         => $attachments,
		);
	}

	/**
	 * Run a draft change only when the caller saw the current draft, holding the same lock the publish uses.
	 *
	 * @param int      $course_id Course ID.
	 * @param string   $revision Revision from program_view().
	 * @param callable $change Performs the change.
	 * @return mixed|\WP_Error
	 */
	public static function with_revision( $course_id, $revision, callable $change ) {
		global $wpdb;
		$key = 'ohmylms_program_' . (int) $course_id;
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 3)', $key ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- A named lock, not table data.
			return self::error( __( 'Another author is saving this course. Try again.', 'ohmylms' ), 409, 'ohmylms_program_busy' ); }
		try {
			wp_cache_delete( (int) $course_id, 'post_meta' );
			CourseStructure::flush( $course_id );
			if ( ! is_string( $revision ) || ! hash_equals( self::revision( $course_id ), $revision ) ) {
				return self::error( __( 'The course structure changed since you read it. Read it again and reapply your change.', 'ohmylms' ), 409, 'ohmylms_program_stale' ); }
			return $change();
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- A named lock, not table data.
		}
	}

	/**
	 * Replace the lesson groups of one unit and assign its items; see ProgramGroups::replace_unit().
	 *
	 * @param int   $course_id Course ID.
	 * @param int   $chapter_id Unit (chapter) ID.
	 * @param array $spec New groups in order.
	 * @param bool  $dry_run Validate only.
	 * @return array|\WP_Error The program that was (or would be) stored.
	 */
	public static function save_unit_groups( $course_id, $chapter_id, array $spec, $dry_run = false ) {
		if ( ! Schema::ready() ) {
			return self::error( __( 'Learning storage is unavailable.', 'ohmylms' ), 503 ); }
		if ( ! self::belongs( $course_id, $chapter_id ) ) {
			return self::error( __( 'The chapter does not belong to this course.', 'ohmylms' ), 404 ); }
		$draft = self::draft( $course_id );
		unset( $draft['id'], $draft['version'], $draft['course_id'] );
		$changed = ProgramGroups::replace_unit( $draft, (int) $chapter_id, $spec, 'wp_generate_uuid4' );
		if ( is_string( $changed ) ) {
			$messages = array(
				'item'          => __( 'A lesson group can only hold items and skills placed in this unit.', 'ohmylms' ),
				'duplicate'     => __( 'Put each item in only one lesson group.', 'ohmylms' ),
				'id'            => __( 'Lesson group identifiers must be unique.', 'ohmylms' ),
				'title'         => __( 'Name each lesson group.', 'ohmylms' ),
				'group'         => __( 'The lesson group does not exist in this unit.', 'ohmylms' ),
				'group_chapter' => __( 'That lesson group belongs to another unit.', 'ohmylms' ),
			);
			return self::error( $messages[ $changed ] ?? __( 'Invalid lesson group.', 'ohmylms' ) ); }
		$program = CourseProgram::normalize( (int) $course_id, $changed );
		if ( is_wp_error( $program ) ) {
			return $program; }
		$program['author_id'] = get_current_user_id();
		if ( ! $dry_run ) {
			update_post_meta( (int) $course_id, CourseProgram::DRAFT, $program ); }
		return $program;
	}

	// ---- Pickers and lists -------------------------------------------------------------------------------

	/**
	 * Author scope for Catalog.
	 */
	private static function author_scope() {
		return current_user_can( 'edit_others_posts' ) ? '' : get_current_user_id();
	}

	/** Grades, exams and subjects (courses) the current user can edit, with their catalog counts.
	 *
	 * @param string $search Search.
	 * @param int    $page Page.
	 * @param int    $per_page Per page.
	 */
	public static function courses( $search = '', $page = 1, $per_page = 24 ) {
		$query = new \WP_Query(
			array(
				'post_type'      => OHMYLMS_COURSE_CPT,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				's'              => $search,
				'posts_per_page' => max( 1, min( 100, (int) $per_page ) ),
				'paged'          => max( 1, (int) $page ),
				'orderby'        => 'title',
				'order'          => 'ASC',
				'author'         => self::author_scope(),
				'no_found_rows'  => false,
			)
		);
		$items = array();
		foreach ( $query->posts as $post ) {
			if ( current_user_can( 'edit_post', $post->ID ) ) {
				$items[] = self::summary( $post->ID ); }
		}
		return array(
			'items' => $items,
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
		);
	}

	/** Search content (or skills) to attach, leaving out what the course already has.
	 *
	 * @param mixed $type Type.
	 * @param mixed $search Search.
	 * @param int   $course_id Course identifier.
	 * @param int   $page Page.
	 */
	public static function targets( $type, $search, $course_id = 0, $page = 1 ) {
		$placed = array();
		if ( $course_id ) {
			$draft  = self::draft( $course_id );
			$placed = 'skill' === $type ? array_map( 'intval', array_column( $draft['outcomes'], 'term_id' ) ) : array_map( 'intval', array_column( $draft['items'], 'content_id' ) );
		}
		if ( 'skill' === $type ) {
			$per   = 40;
			$page  = max( 1, (int) $page );
			$query = array(
				'taxonomy'   => Taxonomy::NAME,
				'hide_empty' => false,
				'search'     => (string) $search,
				'exclude'    => $placed,
			);
			$total = (int) get_terms( $query + array( 'fields' => 'count' ) );
			$terms = get_terms(
				$query + array(
					'orderby' => 'name',
					'number'  => $per,
					'offset'  => ( $page - 1 ) * $per,
				)
			);
			return array(
				'items' => array_map(
					static function ( $term ) {
						return array(
							'id'        => (int) $term->term_id,
							'title'     => $term->name,
							'code'      => (string) get_term_meta( $term->term_id, '_ohmylms_skill_code', true ),
							'questions' => (int) $term->count,
						);
					},
					is_array( $terms ) ? $terms : array()
				),
				'pages' => max( 1, (int) ceil( $total / $per ) ),
				'total' => $total,
			);
		}
		$post_type = self::post_type( $type );
		if ( ! $post_type ) {
			return self::error( __( 'Choose lesson, quiz, assignment or skill.', 'ohmylms' ) ); }
		$query = new \WP_Query(
			array(
				'post_type'      => $post_type,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				's'              => (string) $search,
				'post__not_in'   => $placed,
				'posts_per_page' => 20,
				'paged'          => max( 1, (int) $page ),
				'orderby'        => 'title',
				'order'          => 'ASC',
				'author'         => self::author_scope(),
			)
		);
		$items = array();
		foreach ( $query->posts as $post ) {
			if ( current_user_can( 'edit_post', $post->ID ) ) {
				$items[] = array(
					'id'     => $post->ID,
					'title'  => self::title( $post->ID ),
					'status' => $post->post_status,
				); }
		}
		return array(
			'items' => $items,
			'pages' => (int) $query->max_num_pages,
		);
	}

	/**
	 * Courses that use each lesson, quiz or assignment: its original chapter course plus every course whose
	 * published program or draft places it.
	 *
	 * @param int[] $content_ids Content ids.
	 * @return array<int, int[]>
	 */
	public static function used_in( array $content_ids ) {
		global $wpdb;
		$content_ids = array_values( array_unique( array_filter( array_map( 'intval', $content_ids ) ) ) );
		if ( ! $content_ids ) {
			return array(); }
		$used = array();
		$list = implode( ',', $content_ids );
		foreach ( (array) $wpdb->get_results( "SELECT c.content_id, r.course_id FROM {$wpdb->prefix}ohmylms_content_relationship c JOIN {$wpdb->prefix}ohmylms_chapter_relationship r ON r.chapter_id=c.chapter_id WHERE c.content_id IN ($list)", ARRAY_A ) as $row ) {
			$used[ (int) $row['content_id'] ][ (int) $row['course_id'] ] = (int) $row['course_id'];
		}
		$published = Placements::index();
		foreach ( $content_ids as $id ) {
			foreach ( $published[ $id ] ?? array() as $course ) {
				$used[ $id ][ $course ] = $course; }
		}
		$drafts = $wpdb->get_results( $wpdb->prepare( 'SELECT post_id, meta_value FROM %i WHERE meta_key=%s', $wpdb->postmeta, CourseProgram::DRAFT ), ARRAY_N );
		$rows   = array();
		foreach ( (array) $drafts as $draft ) {
			$rows[] = array( (int) $draft[0], maybe_unserialize( $draft[1] ) ); }
		foreach ( Placements::index_from( $rows ) as $content => $courses ) {
			if ( ! in_array( $content, $content_ids, true ) ) {
				continue; }
			foreach ( $courses as $course ) {
				$used[ $content ][ $course ] = $course; }
		}
		return array_map( 'array_values', $used );
	}

	/**
	 * The lesson library: every lesson the user can edit, whether or not it sits in a course.
	 *
	 * @return array{items:array,total:int,pages:int}
	 * @param array $args Operation arguments.
	 */
	public static function lessons( array $args ) {
		$status   = (string) ( $args['status'] ?? '' );
		$statuses = array( 'publish', 'draft', 'pending', 'private', 'future' );
		$query    = new \WP_Query(
			array(
				'post_type'      => OHMYLMS_LESSON_CPT,
				'post_status'    => in_array( $status, $statuses, true ) ? $status : $statuses,
				's'              => (string) ( $args['search'] ?? '' ),
				'posts_per_page' => max( 1, min( 50, (int) ( $args['per_page'] ?? 20 ) ) ),
				'paged'          => max( 1, (int) ( $args['page'] ?? 1 ) ),
				'orderby'        => in_array( $args['orderby'] ?? '', array( 'title', 'modified', 'date' ), true ) ? $args['orderby'] : 'modified',
				'order'          => strtoupper( (string) ( $args['order'] ?? 'DESC' ) ) === 'ASC' ? 'ASC' : 'DESC',
				'author'         => self::author_scope(),
			)
		);
		$ids      = array();
		foreach ( $query->posts as $post ) {
			if ( current_user_can( 'edit_post', $post->ID ) ) {
				$ids[] = $post->ID; }
		}
		$used   = self::used_in( $ids );
		$skills = array();
		if ( $ids ) {
			foreach ( (array) wp_get_object_terms( $ids, Taxonomy::NAME, array( 'fields' => 'all_with_object_id' ) ) as $term ) {
				if ( is_wp_error( $term ) ) {
					continue; }
				$skills[ (int) $term->object_id ][] = array(
					'id'   => (int) $term->term_id,
					'name' => $term->name,
					'code' => (string) get_term_meta( $term->term_id, '_ohmylms_skill_code', true ),
				);
			}
		}
		$items = array();
		foreach ( $ids as $id ) {
			$post    = get_post( $id );
			$lesson  = ohmylms_get_lesson( $id );
			$items[] = array(
				'id'                => $id,
				'title'             => self::title( $id ),
				'status'            => $post->post_status,
				'type'              => $lesson ? $lesson->get_type() : 'text',
				'native_editor_url' => NativeLessonEditor::edit_url( $id ),
				'modified'          => get_post_modified_time( 'c', true, $post ),
				'skills'            => $skills[ $id ] ?? array(),
				'courses'           => array_map(
					static function ( $course ) {
						return array(
							'id'    => (int) $course,
							'title' => self::title( $course ),
						); },
					$used[ $id ] ?? array()
				),
			);
		}
		return array(
			'items' => $items,
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
		);
	}

	/** Replace the skills a lesson is linked to. Skills are shared; this links, it never copies.
	 *
	 * @param mixed $lesson_id Lesson identifier.
	 * @param array $skill_ids Skill ids.
	 */
	public static function set_lesson_skills( $lesson_id, array $skill_ids ) {
		if ( get_post_type( (int) $lesson_id ) !== OHMYLMS_LESSON_CPT || ! current_user_can( 'edit_post', (int) $lesson_id ) ) {
			return self::error( __( 'You cannot change this lesson.', 'ohmylms' ), 403, 'ohmylms_rest_forbidden' ); }
		$skill_ids = array_values( array_unique( array_filter( array_map( 'intval', $skill_ids ) ) ) );
		foreach ( $skill_ids as $id ) {
			if ( ! term_exists( $id, Taxonomy::NAME ) ) {
				return self::error( __( 'A skill does not exist.', 'ohmylms' ) ); }
		}
		$result = wp_set_object_terms( (int) $lesson_id, $skill_ids, Taxonomy::NAME );
		return is_wp_error( $result ) ? $result : $skill_ids;
	}

	/** Move lessons to the trash one by one, so a lesson the user may not delete never blocks the rest.
	 *
	 * @param array $ids Ids.
	 */
	public static function trash_lessons( array $ids ) {
		$trashed = array();
		$skipped = array();
		foreach ( array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) ) as $id ) {
			if ( get_post_type( $id ) !== OHMYLMS_LESSON_CPT ) {
				$skipped[] = array(
					'id'     => $id,
					'reason' => 'invalid',
				);
				continue; }
			if ( ! current_user_can( 'delete_post', $id ) ) {
				$skipped[] = array(
					'id'     => $id,
					'reason' => 'forbidden',
				);
				continue; }
			if ( ! wp_trash_post( $id ) ) {
				$skipped[] = array(
					'id'     => $id,
					'reason' => 'failed',
				);
				continue; }
			do_action( 'ohmylms_rest_delete_lesson', $id );
			$trashed[] = $id;
		}
		return array(
			'trashed' => $trashed,
			'skipped' => $skipped,
		);
	}

	/** A draft copy of a lesson: same content, settings and skills, but in no course.
	 *
	 * @param mixed $lesson_id Lesson identifier.
	 */
	public static function duplicate_lesson( $lesson_id ) {
		$post = get_post( (int) $lesson_id );
		if ( ! $post || OHMYLMS_LESSON_CPT !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
			return self::error( __( 'You cannot copy this lesson.', 'ohmylms' ), 403, 'ohmylms_rest_forbidden' ); }
		$copy = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => $post->post_type,
					'post_status'  => 'draft',
					'post_author'  => get_current_user_id(),
					'post_title'   => sprintf( /* translators: %s: text value. */
					__( '%s (copy)', 'ohmylms' ), $post->post_title ),
					'post_content' => $post->post_content,
					'post_excerpt' => $post->post_excerpt,
				)
			),
			true
		);
		if ( is_wp_error( $copy ) ) {
			return $copy; }
		foreach ( (array) get_post_meta( $post->ID ) as $key => $values ) {
			if ( in_array( $key, array( '_edit_lock', '_edit_last', '_wp_old_slug' ), true ) || strpos( $key, '_wp_trash_meta' ) === 0 ) {
				continue; }
			foreach ( $values as $value ) {
				add_post_meta( $copy, $key, wp_slash( maybe_unserialize( $value ) ) ); }
		}
		wp_set_object_terms( $copy, wp_get_object_terms( $post->ID, Taxonomy::NAME, array( 'fields' => 'ids' ) ), Taxonomy::NAME );
		return array(
			'id'    => (int) $copy,
			'title' => self::title( $copy ),
		);
	}
}
