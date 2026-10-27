<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
namespace OhMyLMS\Learning;

use OhMyLMS\Skills\Taxonomy;
use OhMyLMS\Skills\Mastery;
use OhMyLMS\Practice\Selector;
use OhMyLMS\QuestionBank\AccessPolicy;
use OhMyLMS\QuestionBank\Banks;
use OhMyLMS\Utility\Transaction;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin course program implementation.
 */
final class CourseProgram {
	const MODES   = array( 'traditional', 'skill-based', 'blended' );
	const CURRENT = '_ohmylms_learning_program';
	const DRAFT   = '_ohmylms_learning_draft';

	/**
	 * Error for Course program.
	 *
	 * @param mixed $message Message.
	 * @param int   $status Status.
	 */
	public static function error( $message, $status = 400 ) {
		return new \WP_Error( 'ohmylms_learning_invalid', $message, array( 'status' => $status ) );
	}

	/**
	 * Get for Course program.
	 *
	 * @param mixed $id Id.
	 */
	public static function get( $id ) {
		global $wpdb;
		if ( ! $id || ! Schema::ready() ) {
			return null; }
		$row     = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', Schema::table( 'programs' ), $id ), ARRAY_A );
		$program = $row ? array(
			'id'        => (int) $row['id'],
			'version'   => (int) $row['version'],
			'course_id' => (int) $row['course_id'],
		) + ( json_decode( $row['program'], true ) ?: array() ) : null;
		if ( $program && ! isset( $program['units'] ) ) {
			$program['units'] = UnitSnapshots::legacy_units( $program['course_id'] );
		}
		return $program;
	}

	/**
	 * Current for Course program.
	 *
	 * @param mixed $course_id Course identifier.
	 */
	public static function current( $course_id ) {
		return self::get( (int) get_post_meta( $course_id, self::CURRENT, true ) ); }

	/**
	 * Defaults for Course program.
	 *
	 * @param mixed $course_id Course identifier.
	 */
	public static function defaults( $course_id ) {
		global $wpdb;
		$rows  = $wpdb->get_results(
			$wpdb->prepare( 'SELECT c.content_id, c.content_type, r.chapter_id FROM %i r JOIN %i c ON c.chapter_id=r.chapter_id WHERE r.course_id=%d ORDER BY r.order_number,c.order_number', $wpdb->prefix . 'ohmylms_chapter_relationship', $wpdb->prefix . 'ohmylms_content_relationship', $course_id ),
			ARRAY_A
		);
		$items = array();
		foreach ( $rows as $row ) {
			$type = 'quiz' === $row['content_type'] ? 'quiz' : ( 'assignment' === $row['content_type'] ? 'assignment' : 'lesson' );
			// Native placements have stable read identities before a program has been saved.
			$hash    = hash( 'sha256', $course_id . ':' . $row['chapter_id'] . ':' . $type . ':' . $row['content_id'] );
			$uuid    = substr( $hash, 0, 8 ) . '-' . substr( $hash, 8, 4 ) . '-4' . substr( $hash, 13, 3 ) . '-8' . substr( $hash, 17, 3 ) . '-' . substr( $hash, 20, 12 );
			$items[] = array(
				'id'         => $uuid,
				'type'       => $type,
				'content_id' => (int) $row['content_id'],
				'chapter_id' => (int) $row['chapter_id'],
				'required'   => true,
			);
		}
		return array(
			'mode'            => 'traditional',
			'recognize_prior' => true,
			'evidence_days'   => 0,
			'bank_ids'        => array(),
			'outcomes'        => array(),
			'items'           => $items,
		);
	}

	/**
	 * Editor for Course program.
	 *
	 * @param mixed $course_id Course identifier.
	 */
	public static function editor( $course_id ) {
		$current = self::current( $course_id );
		$draft   = get_post_meta( $course_id, self::DRAFT, true );
		if ( ! is_array( $draft ) ) {
			$draft = $current ? $current : self::defaults( $course_id );
		}
		$draft['units'] = UnitSnapshots::native( $course_id );
		return array(
			'draft'     => $draft,
			'published' => $current,
			'url'       => Frontend::url( $course_id ),
		);
	}

	/**
	 * Enrollment for Course program.
	 *
	 * @param mixed $student_id Student identifier.
	 * @param mixed $course_id Course identifier.
	 */
	public static function enrollment( $student_id, $course_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id=%d AND course_id=%d AND status=\'enrolled\' ORDER BY id DESC LIMIT 1', $wpdb->prefix . 'ohmylms_user_enrollment', $student_id, $course_id ), ARRAY_A );
	}

	/**
	 * For enrollment for Course program.
	 *
	 * @param array $enrollment Enrollment.
	 */
	public static function for_enrollment( array $enrollment ) {
		global $wpdb;
		if ( ! Schema::ready() ) {
			return null; }
		$bindings = Schema::table( 'enrollments' );
		$bound    = $wpdb->get_row( $wpdb->prepare( 'SELECT program_id FROM %i WHERE enrollment_id=%d', $bindings, $enrollment['id'] ), ARRAY_A );
		if ( ! $bound ) {
			$id = (int) get_post_meta( $enrollment['course_id'], self::CURRENT, true );
			$wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (enrollment_id,program_id,bound_at) VALUES (%d,%d,%s)', $bindings, $enrollment['id'], $id, current_time( 'mysql', true ) ) );
			$bound = $wpdb->get_row( $wpdb->prepare( 'SELECT program_id FROM %i WHERE enrollment_id=%d', $bindings, $enrollment['id'] ), ARRAY_A );
		}
		return $bound ? self::get( (int) $bound['program_id'] ) : null;
	}

	/**
	 * Managed for Course program.
	 *
	 * @param mixed $student_id Student identifier.
	 * @param mixed $course_id Course identifier.
	 */
	public static function managed( $student_id, $course_id ) {
		if ( ! Schema::ready() || ! get_post_meta( $course_id, self::CURRENT, true ) ) {
			return false; }
		$enrollment = self::enrollment( $student_id, $course_id );
		return $enrollment && self::for_enrollment( $enrollment ) !== null;
	}

	/**
	 * Needs skills for Course program.
	 *
	 * @param array $program Program.
	 */
	public static function needs_skills( array $program ) {
		return 'traditional' !== $program['mode'] || ! empty( $program['outcomes'] ) || (bool) array_filter(
			$program['items'],
			static function ( $item ) {
				return 'practice' === $item['type'];
			}
		);
	}

	const MAX_OUTCOMES = 200;

	/** A program lists at most 200 skills, except the course of a syllabus, which can hold every skill of its syllabus.
	 *
	 * @param mixed $course_id Course identifier.
	 * @param mixed $count Count.
	 */
	public static function too_many_outcomes( $course_id, $count ) {
		if ( $count <= self::MAX_OUTCOMES ) {
			return false; }
		return $count > ( \OhMyLMS\Curriculum\SyllabusCourse::owner( (int) $course_id ) ? \OhMyLMS\Curriculum\Syllabus::MAX_SKILLS : self::MAX_OUTCOMES );
	}

	/** The IDs among these that are skills, as a set. A short list is checked one by one, a long one (a syllabus's course) in one query.
	 *
	 * @param array $outcomes Outcomes.
	 */
	private static function known_skills( array $outcomes ) {
		$ids = array();
		foreach ( $outcomes as $outcome ) {
			if ( is_array( $outcome ) ) {
				$ids[] = (int) ( $outcome['term_id'] ?? 0 ); }
		}
		$ids   = array_values( array_unique( array_filter( $ids ) ) );
		$known = array();
		if ( count( $ids ) < 25 ) {
			foreach ( $ids as $id ) {
				if ( term_exists( $id, Taxonomy::NAME ) ) {
					$known[ $id ] = true; }
			}
			return $known;
		}
		$found = get_terms(
			array(
				'taxonomy'               => Taxonomy::NAME,
				'include'                => $ids,
				'fields'                 => 'ids',
				'hide_empty'             => false,
				'number'                 => 0,
				'update_term_meta_cache' => false,
			)
		);
		return is_array( $found ) ? array_flip( array_map( 'intval', $found ) ) : array();
	}

	/**
	 * Normalize for Course program.
	 *
	 * @param mixed $course_id Course identifier.
	 * @param array $data Input data.
	 */
	public static function normalize( $course_id, array $data ) {
		global $wpdb;
		if ( ! in_array( $data['mode'] ?? '', self::MODES, true ) ) {
			return self::error( __( 'Choose a valid learning mode.', 'ohmylms' ) ); }
		if ( ! is_array( $data['items'] ?? null ) || ! is_array( $data['outcomes'] ?? null ) || count( $data['items'] ) > 500 || self::too_many_outcomes( $course_id, count( $data['outcomes'] ) ) ) {
			return self::error( __( 'Invalid or oversized learning program.', 'ohmylms' ) ); }
		$program = array(
			'mode'            => $data['mode'],
			'recognize_prior' => ! empty( $data['recognize_prior'] ),
			'evidence_days'   => max( 0, min( 3650, (int) ( $data['evidence_days'] ?? 0 ) ) ),
			'bank_ids'        => array(),
			'outcomes'        => array(),
			'items'           => array(),
		);
		foreach ( (array) ( $data['bank_ids'] ?? array() ) as $bank ) {
			if ( ! Banks::can( (int) $bank, 'use' ) ) {
				return AccessPolicy::denied( __( 'You cannot use this question bank.', 'ohmylms' ) ); }
			$program['bank_ids'][] = (int) $bank;
		}
		$program['bank_ids'] = array_values( array_unique( $program['bank_ids'] ) );
		$skills              = array();
		$chapters            = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT chapter_id FROM %i WHERE course_id=%d ORDER BY order_number,id', $wpdb->prefix . 'ohmylms_chapter_relationship', $course_id ) ) );
		$chapter_set         = array_flip( $chapters );
		$known               = self::known_skills( $data['outcomes'] );
		$groups              = ProgramGroups::normalize( $data['groups'] ?? null, $chapters );
		if ( is_string( $groups ) ) {
			return self::group_error( $groups ); }
		foreach ( $data['outcomes'] as $outcome ) {
			if ( ! is_array( $outcome ) ) {
				return self::error( __( 'Invalid outcome.', 'ohmylms' ) ); }
			$id = (int) ( $outcome['term_id'] ?? 0 );
			if ( ! $id || ! isset( $known[ $id ] ) || isset( $skills[ $id ] ) || ! in_array( $outcome['target'] ?? '', array( 'proficient', 'mastered' ), true ) ) {
				return self::error( __( 'Outcomes need distinct existing skills and a valid target.', 'ohmylms' ) ); }
			$skills[ $id ] = true;
			$entry         = array(
				'term_id'  => $id,
				'target'   => $outcome['target'],
				'required' => ! empty( $outcome['required'] ),
			);
			// The chapter (unit) a skill sits in, like a strand in a skill catalogue. Order is array order.
			$chapter = (int) ( $outcome['chapter_id'] ?? 0 );
			if ( $chapter && ! isset( $chapter_set[ $chapter ] ) ) {
				return self::error( __( 'The unit does not belong to this course.', 'ohmylms' ) ); }
			if ( $chapter ) {
				$entry['chapter_id'] = $chapter; }
			$member = ProgramGroups::check_member( $groups, $outcome['group_id'] ?? null, $chapter );
			if ( $member ) {
				return self::group_error( $member ); }
			if ( ! empty( $outcome['group_id'] ) ) {
				$entry['group_id'] = $outcome['group_id']; }
			$program['outcomes'][] = $entry;
		}
		foreach ( $groups as $group ) {
			if ( array_diff( $group['skill_ids'] ?? array(), array_keys( $skills ) ) ) {
				return self::error( __( 'Lessons can only link skills selected for this course.', 'ohmylms' ) );
			}
		}
		$ids       = array();
		$resources = array();
		foreach ( $data['items'] as $item ) {
			if ( ! is_array( $item ) || ! in_array( $item['type'] ?? '', array( 'lesson', 'practice', 'quiz', 'assignment' ), true ) ) {
				return self::error( __( 'Choose a valid curriculum item.', 'ohmylms' ) ); }
			$uuid = $item['id'] ?? wp_generate_uuid4();
			if ( ! is_string( $uuid ) || ! preg_match( '/^[a-f0-9-]{36}$/D', $uuid ) || isset( $ids[ $uuid ] ) ) {
				return self::error( __( 'Curriculum placement identifiers must be unique.', 'ohmylms' ) ); }
			$ids[ $uuid ] = true;
			$chapter      = (int) ( $item['chapter_id'] ?? 0 );
			if ( $chapter && ! in_array( $chapter, $chapters, true ) ) {
				return self::error( __( 'The unit does not belong to this course.', 'ohmylms' ) ); }
			$content = (int) ( $item['content_id'] ?? 0 );
			$entry   = array(
				'id'         => $uuid,
				'type'       => $item['type'],
				'chapter_id' => $chapter,
				'content_id' => $content,
				'required'   => ! empty( $item['required'] ),
			);
			$member  = ProgramGroups::check_member( $groups, $item['group_id'] ?? null, $chapter );
			if ( array_key_exists( 'title', $item ) ) {
				if ( ! is_string( $item['title'] ) || mb_strlen( $item['title'] ) > 200 ) {
					return self::error( __( 'Use an activity name of up to 200 characters.', 'ohmylms' ) );
				}
				$title = trim( wp_strip_all_tags( $item['title'] ) );
				if ( '' !== $title ) {
					$entry['title'] = $title;
				}
			}
			if ( $member ) {
				return self::group_error( $member ); }
			if ( ! empty( $item['group_id'] ) ) {
				$entry['group_id'] = $item['group_id']; }
			// Content attaches to a whole chapter (no skills) or to chosen skills of this program.
			$scope = array_values( array_unique( array_map( 'intval', (array) ( $item['skill_ids'] ?? array() ) ) ) );
			if ( $scope && array_diff( $scope, array_keys( $skills ) ) ) {
				return self::error( __( 'Content can only be attached to skills selected for this course.', 'ohmylms' ) ); }
			if ( $scope && 'practice' !== $item['type'] ) {
				$entry['skill_ids'] = $scope; }
			if ( 'practice' === $item['type'] ) {
				if ( ! isset( $skills[ $content ] ) ) {
					return self::error( __( 'A practice item must reference a selected course outcome.', 'ohmylms' ) ); }
				// Practice completion is measured by its outcome, never by session length.
				$entry['required'] = false;
			} else {
				$post_type = array(
					'lesson'     => OHMYLMS_LESSON_CPT,
					'quiz'       => OHMYLMS_QUIZ_CPT,
					'assignment' => 'ohmylms-assignment',
				)[ $item['type'] ];
				if ( ! $content || get_post_type( $content ) !== $post_type || ! current_user_can( 'edit_post', $content ) ) {
					return AccessPolicy::denied( __( 'You cannot place this content in the course.', 'ohmylms' ) ); }
				if ( isset( $resources[ $content ] ) ) {
					return self::error( __( 'Place each learning resource only once in a course.', 'ohmylms' ) ); }
				$resources[ $content ] = true;
				if ( 'quiz' === $item['type'] ) {
					$pass = $item['pass_percent'] ?? 80;
					if ( ! is_numeric( $pass ) || 0 > $pass || 100 < $pass ) {
						return self::error( __( 'Checkpoint pass percentage must be between 0 and 100.', 'ohmylms' ) ); }
					$entry['pass_percent'] = (float) $pass;
				}
			}
			$program['items'][] = $entry;
		}
		// Only stored when used, so a course without groups keeps the exact program it had before.
		$placement_error = AssessmentPlacement::validate( $program['items'] );
		if ( $placement_error ) {
			return self::error( $placement_error );
		}
		$program['items'] = StructurePlan::order_activities( $program['items'], $groups, $chapters );
		if ( $groups ) {
			$program['groups'] = $groups; }
		return $program;
	}

	/**
	 * The stored draft of a course in a form that can move to another course: the draft if there is one,
	 * else the published program, without its row identifiers. Null when the course never had a program.
	 *
	 * @param int $course_id Course post ID.
	 * @return array|null
	 */
	public static function portable( $course_id ) {
		$draft = get_post_meta( (int) $course_id, self::DRAFT, true );
		if ( ! is_array( $draft ) ) {
			$draft = self::current( (int) $course_id );
		}
		if ( ! is_array( $draft ) ) {
			return null;
		}
		unset( $draft['id'], $draft['version'], $draft['course_id'] );
		return $draft;
	}

	/**
	 * Re-point a program at a copy of its course: units and course content get their new IDs, lesson groups
	 * follow their unit. Content the copy shares with the original (placements) keeps its ID; a row whose
	 * unit was not copied becomes unplaced and ungrouped. Pure, so it needs no database.
	 *
	 * @param array $program Program as returned by portable().
	 * @param array $chapters Old chapter ID => new chapter ID.
	 * @param array $contents Old content ID => new content ID.
	 * @return array
	 */
	public static function remap( array $program, array $chapters, array $contents ) {
		unset( $program['id'], $program['version'], $program['course_id'] );
		if ( isset( $program['units'] ) ) {
			$copied_units = array();
			foreach ( $program['units'] as $unit ) {
				if ( isset( $chapters[ $unit['id'] ] ) ) {
					$unit['id']     = (int) $chapters[ $unit['id'] ];
					$copied_units[] = $unit;
				}
			}
			$program['units'] = $copied_units;
		}
		$groups = array();
		foreach ( (array) ( $program['groups'] ?? array() ) as $group ) {
			$old = (int) ( $group['chapter_id'] ?? 0 );
			if ( isset( $chapters[ $old ] ) ) {
				$group['chapter_id']    = (int) $chapters[ $old ];
				$groups[ $group['id'] ] = $group;
			}
		}
		$place               = static function ( array $row ) use ( $chapters, $groups ) {
			$old = (int) ( $row['chapter_id'] ?? 0 );
			if ( isset( $chapters[ $old ] ) ) {
				$row['chapter_id'] = (int) $chapters[ $old ];
			} else {
				unset( $row['chapter_id'] );
				if ( isset( $row['type'] ) ) {
					$row['chapter_id'] = 0; }
			}
			if ( isset( $row['group_id'] ) && ( ! isset( $groups[ $row['group_id'] ] ) || (int) $groups[ $row['group_id'] ]['chapter_id'] !== (int) ( $row['chapter_id'] ?? 0 ) ) ) {
				unset( $row['group_id'] );
			}
			return $row;
		};
		$program['outcomes'] = array_map( $place, (array) ( $program['outcomes'] ?? array() ) );
		$items               = array();
		foreach ( (array) ( $program['items'] ?? array() ) as $item ) {
			$item = $place( $item );
			if ( 'practice' !== ( $item['type'] ?? '' ) && isset( $contents[ (int) ( $item['content_id'] ?? 0 ) ] ) ) {
				$item['content_id'] = (int) $contents[ (int) $item['content_id'] ];
			}
			$items[] = $item;
		}
		$program['items'] = $items;
		if ( $groups ) {
			$program['groups'] = array_values( $groups );
		} else {
			unset( $program['groups'] );
		}
		return $program;
	}

	/**
	 * Store a program copied from another course as the new course's draft (not published).
	 *
	 * @param int   $course_id New course post ID.
	 * @param array $program Program from portable().
	 * @param array $chapters Old chapter ID => new chapter ID.
	 * @param array $contents Old content ID => new content ID.
	 */
	public static function store_copy( $course_id, array $program, array $chapters, array $contents ) {
		$program              = self::remap( $program, $chapters, $contents );
		$program['author_id'] = get_current_user_id();
		update_post_meta( (int) $course_id, self::DRAFT, $program );
	}

	/** The error for a rejected group list or membership (codes come from ProgramGroups).
	 *
	 * @param mixed $code Code.
	 */
	private static function group_error( $code ) {
		$messages = array(
			'oversized'     => __( 'Too many lesson groups.', 'ohmylms' ),
			'invalid'       => __( 'Invalid lesson group.', 'ohmylms' ),
			'skills'        => __( 'Lesson skills must be a list of up to 200 positive skill identifiers.', 'ohmylms' ),
			'id'            => __( 'Lesson group identifiers must be unique.', 'ohmylms' ),
			'chapter'       => __( 'The unit does not belong to this course.', 'ohmylms' ),
			'title'         => __( 'Name each lesson group.', 'ohmylms' ),
			'group'         => __( 'The lesson group does not exist.', 'ohmylms' ),
			'group_chapter' => __( 'A lesson group can only hold content from its own unit.', 'ohmylms' ),
		);
		return self::error( $messages[ $code ] ?? $messages['invalid'] );
	}

	/**
	 * Pool for Course program.
	 *
	 * @param array $program Program.
	 * @param mixed $skill Skill.
	 */
	public static function pool( array $program, $skill ) {
		$author = (int) ( $program['author_id'] ?? get_current_user_id() );
		return array_values(
			array_filter(
				Selector::pool( $skill ),
				static function ( $row ) use ( $program, $author, $skill ) {
					if ( (int) $row['primary_term_id'] !== (int) $skill ) {
						return false; }
					if ( $program['bank_ids'] ) {
						return in_array( (int) $row['bank_id'], $program['bank_ids'], true ); }
					return 0 === (int) $row['bank_id'] ? (int) $row['author_id'] === $author : Banks::can( (int) $row['bank_id'], 'use', $author );
				}
			)
		);
	}

	/**
	 * Completion readiness for Course program.
	 *
	 * @param array $program Program.
	 * @param bool  $syllabus Syllabus.
	 */
	public static function completion_readiness( array $program, $syllabus = false ) {
		$errors            = array();
		$required          = array_filter(
			$program['items'],
			static function ( $item ) {
				return ! empty( $item['required'] );
			}
		);
		$required_outcomes = array_filter(
			$program['outcomes'],
			static function ( $outcome ) {
				return ! empty( $outcome['required'] );
			}
		);
		if ( $syllabus && ! $program['outcomes'] ) {
			$errors[] = __( 'Add at least one skill before publishing the syllabus.', 'ohmylms' ); }
		if ( ! $syllabus && ! $required && ! $required_outcomes ) {
			$errors[] = __( 'Select at least one completion requirement.', 'ohmylms' ); }
		if ( ! $syllabus && 'traditional' !== $program['mode'] && ! $required_outcomes ) {
			$errors[] = __( 'Skill-based and blended courses need a required skill outcome.', 'ohmylms' ); }
		return $errors;
	}

	/**
	 * Readiness for Course program.
	 *
	 * @param array $program Program.
	 * @param int   $course_id Course identifier.
	 */
	public static function readiness( array $program, $course_id = 0 ) {
		$syllabus          = 0 < $course_id && \OhMyLMS\Curriculum\SyllabusCourse::owner( $course_id ) > 0;
		$errors            = array_merge( self::completion_readiness( $program, $syllabus ), self::review_readiness( $program ) );
		$rules             = Mastery::rules();
		$required_outcomes = $syllabus ? array() : array_filter(
			$program['outcomes'],
			static function ( $outcome ) {
				return ! empty( $outcome['required'] );
			}
		);
		foreach ( $program['items'] as $item ) {
			if ( 'practice' !== $item['type'] && get_post_status( $item['content_id'] ) !== 'publish' ) {
				$errors[] = sprintf( /* translators: %s: text value. */
					__( 'Publish the learning resource %s first.', 'ohmylms' ),
					get_the_title( $item['content_id'] )
				); }
		}
		foreach ( $required_outcomes as $outcome ) {
			$pool     = self::pool( $program, $outcome['term_id'] );
			$minimum  = $rules[ $outcome['target'] . '_min' ];
			$families = array_unique(
				array_map(
					static function ( $row ) {
						return $row['family_id'] ? $row['family_id'] : 'q:' . $row['question_id'];
					},
					$pool
				)
			);
			if ( count( array_unique( array_column( $pool, 'question_id' ) ) ) < $minimum || count( $families ) < $rules[ $outcome['target'] . '_families' ] ) {
				$term     = get_term( $outcome['term_id'], Taxonomy::NAME );
				$errors[] = sprintf( /* translators: %1$s: text value; %2$d: number; %3$d: number. */
					__( 'Skill %1$s needs at least %2$d approved questions and %3$d question families in its allowed pool.', 'ohmylms' ),
					$term->name,
					$minimum,
					$rules[ $outcome['target'] . '_families' ]
				);
			}
		}
		return $errors;
	}

	/**
	 * Skills the outline uses that a person has not approved: proposed skills still waiting for review
	 * block publishing, and rejected ones must be replaced.
	 *
	 * @param array $program Program.
	 * @return string[]
	 */
	public static function review_readiness( array $program ) {
		$errors  = array();
		$reviews = \OhMyLMS\Skills\Provenance::reviews( array_map( 'intval', array_column( (array) $program['outcomes'], 'term_id' ) ) );
		foreach ( $reviews as $term_id => $review ) {
			if ( 'approved' === $review ) {
				continue;
			}
			$term = get_term( $term_id, Taxonomy::NAME );
			$name = $term && ! is_wp_error( $term ) ? $term->name : (string) $term_id;
			/* translators: %s: skill name. */
			$errors[] = sprintf( 'pending' === $review ? __( 'Approve the proposed skill %s in the skill library first.', 'ohmylms' ) : __( 'Replace the rejected skill %s.', 'ohmylms' ), $name );
		}
		return $errors;
	}

	/**
	 * The same checks as readiness(), grouped for a person: what is missing per skill and which resources
	 * are still unpublished, so an author can see the gap before trying to publish.
	 *
	 * @param array $program Draft program.
	 * @param int   $course_id Course ID.
	 * @return array{ready:bool,completion:string[],resources:array,skills:array,practice_skills:array}
	 */
	public static function readiness_report( array $program, $course_id = 0 ) {
		$syllabus   = 0 < $course_id && \OhMyLMS\Curriculum\SyllabusCourse::owner( $course_id ) > 0;
		$rules      = Mastery::rules();
		$completion = array_merge( self::completion_readiness( $program, $syllabus ), self::review_readiness( $program ) );
		$resources  = array();
		foreach ( $program['items'] as $item ) {
			if ( 'practice' !== $item['type'] && get_post_status( $item['content_id'] ) !== 'publish' ) {
				$resources[] = array(
					'content_id' => (int) $item['content_id'],
					'type'       => (string) $item['type'],
					'title'      => get_the_title( $item['content_id'] ),
					'status'     => (string) get_post_status( $item['content_id'] ),
				);
			}
		}
		$practice_skills = array();
		foreach ( $syllabus ? array() : $program['outcomes'] as $outcome ) {
			$pool              = self::pool( $program, $outcome['term_id'] );
			$families          = array_unique(
				array_map(
					static function ( $row ) {
						return ! empty( $row['family_id'] ) ? $row['family_id'] : 'q:' . $row['question_id'];
					},
					$pool
				)
			);
			$questions         = count( array_unique( array_column( $pool, 'question_id' ) ) );
			$term              = get_term( $outcome['term_id'], Taxonomy::NAME );
			$valid             = $term && ! is_wp_error( $term );
			$practice_skills[] = array(
				'term_id'        => (int) $outcome['term_id'],
				'name'           => $valid ? $term->name : __( 'Unavailable skill', 'ohmylms' ),
				'code'           => $valid ? (string) get_term_meta( $term->term_id, '_ohmylms_skill_code', true ) : '',
				'target'         => (string) $outcome['target'],
				'required'       => ! empty( $outcome['required'] ),
				'group_id'       => (string) ( $outcome['group_id'] ?? '' ),
				'chapter_id'     => (int) ( $outcome['chapter_id'] ?? 0 ),
				'questions'      => $questions,
				'questions_need' => (int) $rules[ $outcome['target'] . '_min' ],
				'families'       => count( $families ),
				'families_need'  => (int) $rules[ $outcome['target'] . '_families' ],
				'ready'          => $questions >= $rules[ $outcome['target'] . '_min' ] && count( $families ) >= $rules[ $outcome['target'] . '_families' ],
			);
		}
		$skills = array_values(
			array_filter(
				$practice_skills,
				static function ( $row ) {
					return $row['required'];
				}
			)
		);
		return array(
			'ready'           => ! $completion && ! $resources && ! array_filter(
				$skills,
				static function ( $row ) {
					return ! $row['ready'];
				}
			),
			'completion'      => $completion,
			'resources'       => $resources,
			'skills'          => $skills,
			'practice_skills' => $practice_skills,
		);
	}

	/**
	 * Save for Course program.
	 *
	 * @param mixed $course_id Course identifier.
	 * @param array $data Input data.
	 * @param bool  $publish Publish.
	 * @param bool  $apply_existing Apply existing.
	 */
	public static function save( $course_id, array $data, $publish = false, $apply_existing = false ) {
		global $wpdb;
		if ( ! Schema::ready() ) {
			return self::error( __( 'Learning storage is unavailable.', 'ohmylms' ), 503 ); }
		if ( get_post_type( $course_id ) !== OHMYLMS_COURSE_CPT || ! current_user_can( 'edit_post', $course_id ) ) {
			return AccessPolicy::denied(); }
		if ( is_array( $data['groups'] ?? null ) ) {
			$stored         = self::editor( $course_id )['draft'];
			$data['groups'] = ProgramGroups::carry_skills( $data['groups'], $stored['groups'] ?? array() );
		}
		$program = self::normalize( $course_id, $data );
		if ( is_wp_error( $program ) ) {
			return $program; }
		$program['units']     = UnitSnapshots::native( $course_id );
		$program['author_id'] = get_current_user_id();
		$errors               = self::readiness( $program, $course_id );
		if ( $publish && $errors ) {
			return new \WP_Error(
				'ohmylms_learning_not_ready',
				__( 'The learning program is not ready to publish.', 'ohmylms' ),
				array(
					'status' => 409,
					'errors' => $errors,
					'report' => self::readiness_report( $program, $course_id ),
				)
			); }
		if ( ! $publish ) {
			update_post_meta( $course_id, self::DRAFT, $program );
			return self::editor( $course_id ) + array( 'readiness' => $errors ); }
		$key = 'ohmylms_program_' . (int) $course_id;
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 3)', $key ) ) ) {
			return self::error( __( 'Another author is publishing this course. Try again.', 'ohmylms' ), 409 ); }
		try {
			wp_cache_delete( $course_id, 'post_meta' );
			$old = (int) get_post_meta( $course_id, self::CURRENT, true );
			if ( isset( $data['expected_program_id'] ) && (int) $data['expected_program_id'] !== $old ) {
				return self::error( __( 'Another teacher published a newer program. Reload this page before publishing.', 'ohmylms' ), 409 ); }
			$id = Transaction::run(
				static function () use ( $wpdb, $course_id, $program, $old, $apply_existing ) {
					UnitSnapshots::capture( $course_id );
					$bindings = Schema::table( 'enrollments' );
					$programs = Schema::table( 'programs' );
					// Pin every existing enrollment before moving the published pointer.
					if ( $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (enrollment_id,program_id,bound_at) SELECT id,%d,%s FROM %i WHERE course_id=%d', $bindings, $old, current_time( 'mysql', true ), $wpdb->prefix . 'ohmylms_user_enrollment', $course_id ) ) === false ) {
						throw new \RuntimeException( 'Binding failed' ); }
					$version = 1 + (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(version) FROM %i WHERE course_id=%d', $programs, $course_id ) );
					if ( ! $wpdb->insert(
						$programs,
						array(
							'course_id'  => $course_id,
							'version'    => $version,
							'mode'       => $program['mode'],
							'program'    => wp_json_encode( $program ),
							'created_by' => get_current_user_id(),
							'created_at' => current_time( 'mysql', true ),
						)
					) ) {
						throw new \RuntimeException( 'Program write failed' ); }
					$id = (int) $wpdb->insert_id;
					foreach ( $program['outcomes'] as $outcome ) {
						// The chapter placement lives in the program JSON; the table keeps only what completion reads.
						if ( ! $wpdb->insert(
							Schema::table( 'outcomes' ),
							array(
								'program_id' => $id,
								'term_id'    => $outcome['term_id'],
								'target'     => $outcome['target'],
								'required'   => (int) ! empty( $outcome['required'] ),
							)
						) ) {
							throw new \RuntimeException( 'Outcome write failed' ); }
					}
					if ( $apply_existing && $wpdb->query( $wpdb->prepare( 'UPDATE %i b JOIN %i e ON e.id=b.enrollment_id SET b.program_id=%d,b.bound_at=%s WHERE e.course_id=%d AND e.status=\'enrolled\' AND e.progress<>\'completed\'', $bindings, $wpdb->prefix . 'ohmylms_user_enrollment', $id, current_time( 'mysql', true ), $course_id ) ) === false ) {
						throw new \RuntimeException( 'Upgrade failed' ); }
					if ( ! \OhMyLMS\Curriculum\Directory::capture( $course_id, $program ) ) {
						throw new \RuntimeException( 'Directory publish failed' ); }
					if ( ! update_post_meta( $course_id, self::CURRENT, $id ) ) {
						throw new \RuntimeException( 'Publish failed' ); }
					return $id;
				}
			);
			update_post_meta( $course_id, self::DRAFT, $program );
			Placements::flush();
			UnitSnapshots::flush();
			return self::editor( $course_id ) + array(
				'readiness'    => array(),
				'published_id' => $id,
			);
		} catch ( \Throwable $error ) {
			wp_cache_delete( $course_id, 'post_meta' );
			return self::error( __( 'Could not publish the learning program. Try again.', 'ohmylms' ), 500 );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $key ) ); }
	}
}
