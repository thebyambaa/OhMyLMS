<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Syllabus comparison reads and shared skill editing.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Curriculum;

use OhMyLMS\Skills\SubjectProfileModel;
use OhMyLMS\Skills\SubjectProfiles;
use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/** Keeps shared abilities separate from syllabus requirements. */
final class Comparison {

	const SUBJECT_META = '_ohmylms_shared_subject';
	const TOPIC_META   = '_ohmylms_shared_topic';
	const ROADMAP_META = '_ohmylms_shared_created_for_roadmap';
	const OPTION       = 'ohmylms_subject_syllabuses';
	// Where a shared skill sits in the subject: its domain and the grades it is usually learned in.
	const DOMAIN_META     = '_ohmylms_shared_domain';
	const GRADE_FROM_META = '_ohmylms_shared_grade_from';
	const GRADE_TO_META   = '_ohmylms_shared_grade_to';
	// Highest level of the built-in Mathematics scale (grades 1 to 12, then 13 for beyond grade 12).
	// Other subjects' scales come from their profiles: use max_level().
	const MAX_GRADE = 13;

	/**
	 * Domains a skill of a subject can belong to, keyed by id, from the subject's profile.
	 * Extend with `ohmylms_skill_domains`, which also receives the subject.
	 *
	 * @param string $subject Subject, or empty for the default profile.
	 * @return array<string,string>
	 */
	public static function domains( $subject = '' ) {
		$domains  = SubjectProfileModel::domain_map( SubjectProfiles::get( $subject ) );
		$filtered = apply_filters( 'ohmylms_skill_domains', $domains, (string) $subject );
		$result   = array();
		foreach ( is_array( $filtered ) ? $filtered : $domains as $id => $label ) {
			if ( is_string( $id ) && preg_match( '/^[a-z][a-z0-9-]{0,39}$/', $id ) ) {
				$result[ $id ] = (string) $label;
			}
		}
		return $result;
	}

	/**
	 * Levels a skill of a subject can be placed at, as ordinals with labels.
	 *
	 * @param string $subject Subject, or empty for the default profile.
	 * @return array[] value, label.
	 */
	public static function levels( $subject = '' ) {
		return SubjectProfileModel::levels( SubjectProfiles::get( $subject ) );
	}

	/**
	 * Highest level ordinal of a subject's scale.
	 *
	 * @param string $subject Subject, or empty for the default profile.
	 * @return int
	 */
	public static function max_level( $subject = '' ) {
		return count( SubjectProfiles::get( $subject )['levels'] );
	}

	/**
	 * Subject a syllabus is configured under for comparisons, or empty.
	 *
	 * @param int $syllabus_id Syllabus ID.
	 * @return string
	 */
	public static function subject_of_syllabus( $syllabus_id ) {
		$configured = get_option( self::OPTION, array() );
		foreach ( is_array( $configured ) ? $configured : array() as $subject => $ids ) {
			if ( in_array( (int) $syllabus_id, array_map( 'intval', (array) $ids ), true ) ) {
				return (string) $subject;
			}
		}
		return '';
	}

	/**
	 * Name of a level on a subject's scale.
	 *
	 * @param int    $grade   Level ordinal.
	 * @param string $subject Subject, or empty for the default profile.
	 * @return string
	 */
	public static function grade_label( $grade, $subject = '' ) {
		return SubjectProfileModel::level_label( SubjectProfiles::get( $subject ), $grade );
	}

	/**
	 * Check a domain and level band against a subject's profile. Empty values mean "not set yet".
	 *
	 * @param mixed  $domain     Domain id or empty.
	 * @param mixed  $grade_from First level or 0.
	 * @param mixed  $grade_to   Last level or 0.
	 * @param string $subject    Subject, or empty for the default profile.
	 * @return array|\WP_Error domain, grade_from, grade_to.
	 */
	public static function placement( $domain, $grade_from, $grade_to, $subject = '' ) {
		$domain = sanitize_key( (string) $domain );
		$from   = (int) $grade_from;
		$to     = (int) $grade_to;
		$max    = self::max_level( $subject );
		if ( '' !== $domain && ! isset( self::domains( $subject )[ $domain ] ) ) {
			return Access::error( 'ohmylms_shared_invalid', __( 'Choose one of the listed domains.', 'ohmylms' ) );
		}
		if ( $from && ! $to ) {
			$to = $from;
		}
		if ( $from < 0 || $to < 0 || $from > $max || $to > $max || ( $from && $to < $from ) || ( ! $from && $to ) ) {
			return Access::error(
				'ohmylms_shared_invalid',
				sprintf(
					/* translators: 1: first level name, 2: last level name. */
					__( 'Choose levels from %1$s to %2$s, and the last level cannot come before the first.', 'ohmylms' ),
					self::grade_label( 1, $subject ),
					self::grade_label( $max, $subject )
				)
			);
		}
		return array(
			'domain'     => $domain,
			'grade_from' => $from,
			'grade_to'   => $to,
		);
	}

	/**
	 * Return syllabus selectors with their stored version and curriculum path.
	 *
	 * @return array
	 */
	public static function syllabuses() {
		$rows = array();
		foreach ( Items::all() as $item ) {
			if ( ! empty( $item['is_syllabus'] ) ) {
				$rows[] = array(
					'id'      => (int) $item['id'],
					'name'    => $item['name'],
					'code'    => $item['code'],
					'version' => $item['version'],
					'path'    => Items::path_names( (int) $item['id'], false ),
				);
			}
		}
		return $rows;
	}

	/**
	 * Flatten the selected syllabus only, retaining source order and memberships.
	 *
	 * @param int $syllabus_id Syllabus ID.
	 * @return array|\WP_Error
	 */
	public static function objectives( $syllabus_id ) {
		$outline = Syllabus::outline( (int) $syllabus_id );
		if ( is_wp_error( $outline ) ) {
			return $outline;
		}
		$rows = array();
		foreach ( $outline['contents'] as $content ) {
			foreach ( $content['groups'] as $group ) {
				foreach ( $group['skills'] as $skill ) {
					$id = $skill['term_id'];
					if ( ! isset( $rows[ $id ] ) ) {
						$rows[ $id ] = $skill + array( 'memberships' => array() );
					}
					$rows[ $id ]['memberships'][] = array(
						'group_id' => (int) $group['id'],
						'topic_id' => $content['id'],
						'topic'    => $content['name'],
						'group'    => $group['name'],
						'code'     => $group['code'],
					);
				}
			}
		}
		return array_values( $rows );
	}

	/**
	 * A content revision independent of timestamp precision.
	 *
	 * @param mixed $value Current content.
	 * @return string
	 */
	public static function revision( $value ) {
		return hash( 'sha256', wp_json_encode( $value ) );
	}

	/**
	 * Describe an explicitly designated shared skill, including impact.
	 *
	 * @param int $id Skill ID.
	 * @return array|null
	 */
	public static function shared_skill( $id ) {
		$skill = Taxonomy::describe( (int) $id );
		if ( ! $skill || ! metadata_exists( 'term', $id, self::SUBJECT_META ) ) {
			return null;
		}
		$skill['topic']      = (string) get_term_meta( $id, self::TOPIC_META, true );
		$skill['subject']    = (string) get_term_meta( $id, self::SUBJECT_META, true );
		$skill['domain']     = (string) get_term_meta( $id, self::DOMAIN_META, true );
		$skill['grade_from'] = (int) get_term_meta( $id, self::GRADE_FROM_META, true );
		$skill['grade_to']   = (int) get_term_meta( $id, self::GRADE_TO_META, true );
		$skill['mappings']   = SkillMappings::for_skill( $id );
		$skill['revision']   = self::revision( array( $skill['name'], $skill['description'], $skill['topic'], $skill['subject'], $skill['domain'], $skill['grade_from'], $skill['grade_to'], $skill['mappings'] ) );
		$skill['provenance'] = \OhMyLMS\Skills\Provenance::read( $id );
		return $skill;
	}

	/**
	 * Search explicitly designated mathematics skills, with bounded paging.
	 *
	 * @param array $params Search and paging.
	 * @return array
	 */
	public static function shared_skills( array $params ) {
		$args = array(
			'taxonomy'   => Taxonomy::NAME,
			'hide_empty' => false,
			'meta_key'   => self::SUBJECT_META,
			'meta_value' => sanitize_text_field( $params['subject'] ?? '' ),
			'search'     => sanitize_text_field( $params['search'] ?? '' ),
			'orderby'    => 'name',
		);
		if ( empty( $params['subject'] ) ) {
			unset( $args['meta_value'] );
		}
		// Narrow by domain, and by a grade the skill's band includes.
		$meta = array();
		if ( ! empty( $params['domain'] ) ) {
			$meta[] = array(
				'key'   => self::DOMAIN_META,
				'value' => sanitize_key( (string) $params['domain'] ),
			);
		}
		if ( ! empty( $params['grade'] ) ) {
			$grade  = (int) $params['grade'];
			$meta[] = array(
				'key'     => self::GRADE_FROM_META,
				'value'   => $grade,
				'compare' => '<=',
				'type'    => 'NUMERIC',
			);
			$meta[] = array(
				'key'     => self::GRADE_TO_META,
				'value'   => $grade,
				'compare' => '>=',
				'type'    => 'NUMERIC',
			);
		}
		if ( $meta ) {
			$args['meta_query'] = array_merge( array( 'relation' => 'AND' ), $meta ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded admin search.
		}
		if ( ! empty( $params['roadmap_id'] ) ) {
			$roadmap = Roadmaps::get( absint( $params['roadmap_id'] ) );
			if ( is_wp_error( $roadmap ) ) {
				return array(
					'items' => array(),
					'total' => 0,
				);
			}
			$preview = Roadmaps::preview( $roadmap['syllabus_ids'] );
			$ids     = array_column( $roadmap['skills'], 'id' );
			$created = get_terms(
				array(
					'taxonomy'   => Taxonomy::NAME,
					'hide_empty' => false,
					'fields'     => 'ids',
					'meta_key'   => self::ROADMAP_META,
					'meta_value' => $roadmap['id'],
				)
			);
			if ( is_array( $created ) ) {
				$ids = array_merge( $ids, array_map( 'intval', $created ) );
			}
			if ( ! is_wp_error( $preview ) ) {
				$ids = array_merge( $ids, array_column( $preview['skills'], 'id' ) );
			}
			if ( ! $ids ) {
				return array(
					'items' => array(),
					'total' => 0,
				);
			}
			$args['include'] = array_values( array_unique( $ids ) );
		}
		$total = wp_count_terms( $args );
		$terms = get_terms(
			$args + array(
				'number' => 30,
				'offset' => ( max( 1, (int) ( $params['page'] ?? 1 ) ) - 1 ) * 30,
			)
		);
		return array(
			'items' => is_array( $terms ) ? array_map(
				static function ( $term ) {
					return self::shared_skill( $term->term_id ); },
				$terms
			) : array(),
			'total' => is_wp_error( $total ) ? 0 : (int) $total,
		);
	}

	/**
	 * Create or edit a shared ability while retaining its taxonomy identity.
	 *
	 * @param int   $id Existing ID, or zero for creation.
	 * @param array $data Name, description, topic and expected revision.
	 * @return array|\WP_Error
	 */
	public static function save_shared( $id, array $data ) {
		global $wpdb;
		if ( isset( $data['roadmap_id'] ) && ! is_int( $data['roadmap_id'] ) ) {
			return Access::error( 'ohmylms_shared_invalid', __( 'Roadmap IDs must be integers.', 'ohmylms' ) );
		}
		$roadmap_id = absint( $data['roadmap_id'] ?? 0 );
		if ( ! $id && $roadmap_id ) {
			$roadmap = Roadmaps::get( $roadmap_id );
			if ( is_wp_error( $roadmap ) ) {
				return $roadmap;
			}
		}
		$creating    = ! $id;
		$name        = sanitize_text_field( $data['name'] ?? '' );
		$description = sanitize_textarea_field( $data['description'] ?? '' );
		$topic       = sanitize_text_field( $data['topic'] ?? '' );
		$subject     = sanitize_text_field( $data['subject'] ?? '' );
		$current     = $id ? self::shared_skill( $id ) : null;
		$placement   = self::placement(
			array_key_exists( 'domain', $data ) ? $data['domain'] : ( $current['domain'] ?? '' ),
			array_key_exists( 'grade_from', $data ) ? $data['grade_from'] : ( $current['grade_from'] ?? 0 ),
			array_key_exists( 'grade_to', $data ) ? $data['grade_to'] : ( $current['grade_to'] ?? 0 ),
			'' !== $subject ? $subject : ( $current['subject'] ?? '' )
		);
		if ( is_wp_error( $placement ) ) {
			return $placement;
		}
		if ( '' === $name || mb_strlen( $subject ) > 100 || mb_strlen( $name ) > 190 || mb_strlen( $description ) > 2000 || mb_strlen( $topic ) > 100 ) {
			return Access::error( 'ohmylms_shared_invalid', __( 'Enter a name up to 190 characters, a scope up to 2000 characters and a topic up to 100 characters.', 'ohmylms' ) );
		}
		$lock = SkillMappings::LOCK . '-' . md5( $wpdb->prefix );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) {
			return Access::error( 'ohmylms_mapping_busy', __( 'Another mapping change is being saved. Try again.', 'ohmylms' ), 409 );
		}
		try {
			if ( $id ) {
				$previous = self::shared_skill( $id );
				if ( ! $previous ) {
					return Access::error( 'ohmylms_shared_missing', __( 'Shared skill not found.', 'ohmylms' ), 404 );
				}
				if ( ( $data['expected_revision'] ?? '' ) !== $previous['revision'] ) {
					return Access::error( 'ohmylms_shared_stale', __( 'This skill or its mappings changed. Reload it before saving.', 'ohmylms' ), 409 );
				}
				$result = wp_update_term(
					$id,
					Taxonomy::NAME,
					array(
						'name'        => $name,
						'description' => $description,
					)
				);
			} else {
				$result = wp_insert_term(
					$name,
					Taxonomy::NAME,
					array(
						'description' => $description,
						'slug'        => 'shared-skill-' . wp_generate_uuid4(),
					)
				);
			}
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$id = (int) $result['term_id'];
			update_term_meta( $id, self::SUBJECT_META, $subject );
			update_term_meta( $id, self::TOPIC_META, $topic );
			self::store_placement( $id, $placement );
			if ( $creating && $roadmap_id ) {
				update_term_meta( $id, self::ROADMAP_META, $roadmap_id );
			}
			return self::shared_skill( $id );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	/**
	 * Store a checked domain and grade band on a skill.
	 *
	 * @param int   $id        Skill ID.
	 * @param array $placement From placement().
	 */
	public static function store_placement( $id, array $placement ) {
		update_term_meta( $id, self::DOMAIN_META, $placement['domain'] );
		update_term_meta( $id, self::GRADE_FROM_META, $placement['grade_from'] );
		update_term_meta( $id, self::GRADE_TO_META, $placement['grade_to'] );
	}

	/**
	 * Mapping coverage counts objectives, rather than mapping rows.
	 *
	 * @param array $rows Objectives with maps_to.
	 * @return array
	 */
	public static function coverage( array $rows ) {
		$counts = array(
			'total'        => count( $rows ),
			'equivalent'   => 0,
			'related_only' => 0,
			'unmapped'     => 0,
			'shared'       => 0,
		);
		foreach ( $rows as $row ) {
			if ( metadata_exists( 'term', $row['term_id'], self::SUBJECT_META ) ) {
				++$counts['shared'];
				continue;
			}
			$relations = array_column( $row['maps_to'], 'relation' );
			$key       = in_array( 'equivalent', $relations, true ) ? 'equivalent' : ( $relations ? 'related_only' : 'unmapped' );
			++$counts[ $key ];
		}
		return $counts;
	}

	/**
	 * Attach directional mappings in one read for selected source IDs.
	 *
	 * @param array $rows Source objectives.
	 * @return array
	 */
	public static function attach_mappings( array $rows ) {
		$by_id = array();
		foreach ( $rows as $row ) {
			$row['maps_to']           = array();
			$row['is_shared']         = metadata_exists( 'term', $row['term_id'], self::SUBJECT_META );
			$by_id[ $row['term_id'] ] = $row;
		}
		if ( ! $by_id ) {
			return array();
		}
		foreach ( SkillMappings::all( array_keys( $by_id ) ) as $mapping ) {
			$id = (int) $mapping['specific_term_id'];
			if ( ! isset( $by_id[ $id ] ) ) {
				continue;
			}
			$shared_id                 = (int) $mapping['shared_term_id'];
			$term                      = get_term( $shared_id, Taxonomy::NAME );
			$by_id[ $id ]['maps_to'][] = array(
				'id'          => (int) $mapping['id'],
				'specific_id' => $id,
				'shared_id'   => $shared_id,
				'shared'      => $term && ! is_wp_error( $term ) ? array(
					'id'   => $shared_id,
					'name' => Taxonomy::plain( $term->name ),
					'code' => (string) get_term_meta( $shared_id, Syllabus::CODE_META, true ),
				) : null,
				'relation'    => $mapping['relation'],
				'group_uuid'  => $mapping['group_uuid'] ?? '',
				'note'        => (string) $mapping['note'],
				'revision'    => SkillMappings::revision( $mapping ),
			);
		}
		return array_values( $by_id );
	}

	/**
	 * Scope and filter a comparison to one syllabus, returning a bounded page.
	 *
	 * @param int   $id Syllabus ID.
	 * @param array $params Search, topic, category, status, shared_id and page.
	 * @return array|\WP_Error
	 */
	public static function page( $id, array $params ) {
		$rows = self::objectives( $id );
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		$topics = array();
		$groups = array();
		$rows   = self::attach_mappings( $rows );
		foreach ( $rows as &$row ) {
			foreach ( $row['memberships'] as $membership ) {
				$topics[ $membership['topic_id'] ] = $membership['topic'];
				$groups[ $membership['group_id'] ] = $membership['group'];
			}
		}
		unset( $row );
		$coverage = self::coverage( $rows );
		$search   = mb_strtolower( sanitize_text_field( $params['search'] ?? '' ) );
		$filtered = array_values(
			array_filter(
				$rows,
				static function ( $row ) use ( $params, $search ) {
					$relations = array_column( $row['maps_to'], 'relation' );
					$status    = $row['is_shared'] ? 'shared' : ( in_array( 'equivalent', $relations, true ) ? 'equivalent' : ( $relations ? 'related_only' : 'unmapped' ) );
					return ( '' === $search || false !== mb_strpos( mb_strtolower( implode( ' ', array( $row['name'], $row['code'], $row['objective'], $row['description'] ) ) ), $search ) )
					&& ( empty( $params['topic'] ) || in_array( (int) $params['topic'], array_column( $row['memberships'], 'topic_id' ), true ) )
						&& ( empty( $params['group'] ) || in_array( (int) $params['group'], array_column( $row['memberships'], 'group_id' ), true ) )
					&& ( empty( $params['category'] ) || $params['category'] === $row['category'] )
					&& ( empty( $params['status'] ) || $params['status'] === $status )
					&& ( empty( $params['shared_id'] ) || in_array( (int) $params['shared_id'], array_column( $row['maps_to'], 'shared_id' ), true ) );
				}
			)
		);
		return array(
			'syllabus' => Items::describe( Items::get( $id ) ),
			'settings' => SyllabusSettings::get( $id ),
			'path'     => Items::path_names( $id, false ),
			'topics'   => $topics,
			'groups'   => $groups,
			'coverage' => $coverage,
			'total'    => count( $filtered ),
			'items'    => array_slice( $filtered, ( max( 1, (int) ( $params['page'] ?? 1 ) ) - 1 ) * 30, 30 ),
		);
	}
}
