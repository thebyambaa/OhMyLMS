<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Turns normalized import rows into an ordered list of operations against what a syllabus already
 * holds, and a report of what would change. Pure PHP (no WordPress), so a dry run and the real
 * import share one set of rules and both can be unit tested.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Curriculum;

/**
 * Turns normalized import rows into an ordered list of operations against what a syllabus already
 * holds, and a report of what would change. Pure PHP (no WordPress), so a dry run and the real
 * import share one set of rules and both can be unit tested.
 *
 * Matching makes a repeated import safe instead of duplicating things:
 *  - a content item, skill group or skill matches an existing one by its code when both have a
 *    code, and by its name (ignoring case and spacing) otherwise;
 *  - a skill with a code is found anywhere in the syllabus, so a code that moved to another group
 *    moves the skill rather than creating a second one;
 *  - nothing is ever deleted by an import: what the file omits stays.
 *
 * State, as read from the database:
 *   root      the syllabus item ID
 *   contents  every content item under the syllabus (the root included): id, parent_id, name, code
 *   groups    every skill group: id, item_id, name, code and its skills (term_id, name, code, description)
 *
 * Operations refer to things created earlier in the same plan with "@c1", "@g2", "@s3" style
 * references; existing things are referred to by their numeric ID.
 */
final class SyllabusPlan {
	const MAX_GROUPS = 2000;
	const MAX_SKILLS = 5000;

	/**
	 * Root.
	 *
	 * @var mixed
	 */
	private $root;
	/**
	 * Options.
	 *
	 * @var mixed
	 */
	private $options;
	/**
	 * Contents.
	 *
	 * @var mixed
	 */
	private $contents = array();
	/**
	 * Groups.
	 *
	 * @var mixed
	 */
	private $groups = array();
	/**
	 * Skills.
	 *
	 * @var mixed
	 */
	private $skills = array();
	/**
	 * By code.
	 *
	 * @var mixed
	 */
	private $by_code = array();
	/**
	 * In group.
	 *
	 * @var mixed
	 */
	private $in_group = array();
	/**
	 * Ops.
	 *
	 * @var mixed
	 */
	private $ops = array();
	/**
	 * Op index.
	 *
	 * @var mixed
	 */
	private $op_index = array();
	/**
	 * Updates.
	 *
	 * @var mixed
	 */
	private $updates = array(
		'content' => array(),
		'group'   => array(),
		'skill'   => array(),
	);
	/**
	 * Matched.
	 *
	 * @var mixed
	 */
	private $matched = array(
		'content' => array(),
		'group'   => array(),
		'skill'   => array(),
	);
	/**
	 * Moved.
	 *
	 * @var mixed
	 */
	private $moved = array();
	/**
	 * Created.
	 *
	 * @var mixed
	 */
	private $created = array(
		'content' => 0,
		'group'   => 0,
		'skill'   => 0,
	);
	/**
	 * Errors.
	 *
	 * @var mixed
	 */
	private $errors = array();
	/**
	 * Warnings.
	 *
	 * @var mixed
	 */
	private $warnings = array();
	/**
	 * Sequence.
	 *
	 * @var mixed
	 */
	private $sequence = 0;
	/**
	 * Defaulted.
	 *
	 * @var mixed
	 */
	private $defaulted = array();

	/**
	 * Key for Syllabus plan.
	 *
	 * @param mixed $text Text.
	 */
	public static function key( $text ) {
		$text = trim( (string) preg_replace( '/\s+/u', ' ', (string) $text ) );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	}

	/**
	 * Same for Syllabus plan.
	 *
	 * @param mixed $code_a Code a.
	 * @param mixed $name_a Name a.
	 * @param mixed $code_b Code b.
	 * @param mixed $name_b Name b.
	 */
	private static function same( $code_a, $name_a, $code_b, $name_b ) {
		if ( '' !== $code_a && '' !== $code_b ) {
			return self::key( $code_a ) === self::key( $code_b ); }
		return self::key( $name_a ) === self::key( $name_b );
	}

	/**
	 * Build.
	 *
	 * @param array   $state See the class description.
	 * @param array[] $rows Output of SyllabusRows::normalize()['rows'].
	 * @param array   $options default_group (name for skills with no group), max_groups, max_skills.
	 * @return array{ops: array[], report: array}
	 */
	public static function build( array $state, array $rows, array $options = array() ) {
		$plan = new self(
			(int) ( $state['root'] ?? 0 ),
			$options + array(
				'default_group' => 'Skills',
				'max_groups'    => self::MAX_GROUPS,
				'max_skills'    => self::MAX_SKILLS,
			)
		);
		$plan->load( $state );
		foreach ( $rows as $row ) {
			$plan->row( $row ); }
		return $plan->finish();
	}

	/**
	 * Initialize Syllabus plan.
	 *
	 * @param mixed $root Root.
	 * @param array $options Operation options.
	 */
	private function __construct( $root, array $options ) {
		$this->root    = $root;
		$this->options = $options;
	}

	/**
	 * Load for Syllabus plan.
	 *
	 * @param array $state State.
	 */
	private function load( array $state ) {
		foreach ( $state['contents'] ?? array() as $content ) {
			$id = (int) $content['id'];
			if ( $id === $this->root ) {
				continue; }
			$this->contents[ $id ] = array(
				'id'     => $id,
				'parent' => (int) $content['parent_id'],
				'name'   => (string) $content['name'],
				'code'   => (string) ( $content['code'] ?? '' ),
			);
		}
		foreach ( $state['groups'] ?? array() as $group ) {
			$id                  = (int) $group['id'];
			$this->groups[ $id ] = array(
				'id'   => $id,
				'item' => (int) $group['item_id'],
				'name' => (string) $group['name'],
				'code' => (string) ( $group['code'] ?? '' ),
			);
			foreach ( $group['skills'] ?? array() as $skill ) {
				$term                    = (int) $skill['term_id'];
				$this->skills[ $term ]   = array(
					'id'          => $term,
					'group'       => $id,
					'name'        => (string) $skill['name'],
					'code'        => (string) ( $skill['code'] ?? '' ),
					'description' => (string) ( $skill['description'] ?? '' ),
					'category'    => (string) ( $skill['category'] ?? '' ),
					'touched'     => false,
				);
				$this->in_group[ $id ][] = $term;
				if ( ( $skill['code'] ?? '' ) !== '' && ! isset( $this->by_code[ self::key( $skill['code'] ) ] ) ) {
					$this->by_code[ self::key( $skill['code'] ) ] = $term; }
			}
		}
	}

	/**
	 * Ref for Syllabus plan.
	 *
	 * @param mixed $letter Letter.
	 */
	private function ref( $letter ) {
		return '@' . $letter . ( ++$this->sequence ); }

	/**
	 * Push for Syllabus plan.
	 *
	 * @param array $op Op.
	 */
	private function push( array $op ) {
		$this->ops[] = $op;
		if ( isset( $op['ref'] ) ) {
			$this->op_index[ $op['ref'] ] = count( $this->ops ) - 1; }
	}

	/** Set fields on a thing: created in this plan (edit its operation) or existing (one update operation).
	 *
	 * @param mixed $kind Kind.
	 * @param mixed $id Id.
	 * @param array $fields Fields.
	 */
	private function set( $kind, $id, array $fields ) {
		if ( ! $fields ) {
			return; }
		if ( is_string( $id ) ) {
			$this->ops[ $this->op_index[ $id ] ] = array_merge( $this->ops[ $this->op_index[ $id ] ], $fields );
			return;
		}
		if ( ! isset( $this->updates[ $kind ][ $id ] ) ) {
			$this->push(
				array(
					'op'     => $kind . '_update',
					'id'     => $id,
					'fields' => array(),
				)
			);
			$this->updates[ $kind ][ $id ] = count( $this->ops ) - 1;
		}
		$index                         = $this->updates[ $kind ][ $id ];
		$this->ops[ $index ]['fields'] = array_merge( $this->ops[ $index ]['fields'], $fields );
		$current                       = &$this->{'content' === $kind ? 'contents' : ( 'group' === $kind ? 'groups' : 'skills' )}[ $id ];
		foreach ( $fields as $key => $value ) {
			$current[ $key ] = $value; }
	}

	/**
	 * Content for Syllabus plan.
	 *
	 * @param mixed $parent Parent.
	 * @param mixed $name Name.
	 * @param mixed $code Code.
	 */
	private function content( $parent, $name, $code ) {
		foreach ( $this->contents as $id => $content ) {
			if ( $content['parent'] === $parent && self::same( $code, $name, $content['code'], $content['name'] ) ) {
				if ( is_int( $id ) ) {
					$this->matched['content'][ $id ] = true; }
				$fields = array();
				if ( $name !== $content['name'] ) {
					$fields['name'] = $name; }
				if ( '' !== $code && $code !== $content['code'] ) {
					$fields['code'] = $code; }
				$this->set( 'content', $id, $fields );
				return $id;
			}
		}
		$ref                    = $this->ref( 'c' );
		$this->contents[ $ref ] = array(
			'id'     => $ref,
			'parent' => $parent,
			'name'   => $name,
			'code'   => $code,
		);
		$this->push(
			array(
				'op'     => 'content',
				'ref'    => $ref,
				'parent' => $parent,
				'name'   => $name,
				'code'   => $code,
			)
		);
		++$this->created['content'];
		return $ref;
	}

	/**
	 * Group for Syllabus plan.
	 *
	 * @param mixed $item Item.
	 * @param mixed $name Name.
	 * @param mixed $code Code.
	 */
	private function group( $item, $name, $code ) {
		foreach ( $this->groups as $id => $group ) {
			if ( $group['item'] === $item && self::same( $code, $name, $group['code'], $group['name'] ) ) {
				if ( is_int( $id ) ) {
					$this->matched['group'][ $id ] = true; }
				$fields = array();
				if ( $name !== $group['name'] ) {
					$fields['name'] = $name; }
				if ( '' !== $code && $code !== $group['code'] ) {
					$fields['code'] = $code; }
				$this->set( 'group', $id, $fields );
				return $id;
			}
		}
		$ref                  = $this->ref( 'g' );
		$this->groups[ $ref ] = array(
			'id'   => $ref,
			'item' => $item,
			'name' => $name,
			'code' => $code,
		);
		$this->push(
			array(
				'op'   => 'group',
				'ref'  => $ref,
				'item' => $item,
				'name' => $name,
				'code' => $code,
			)
		);
		++$this->created['group'];
		return $ref;
	}

	/**
	 * Find skill for Syllabus plan.
	 *
	 * @param mixed $group Group.
	 * @param mixed $name Name.
	 * @param mixed $code Code.
	 */
	private function find_skill( $group, $name, $code ) {
		if ( '' !== $code && isset( $this->by_code[ self::key( $code ) ] ) ) {
			return $this->by_code[ self::key( $code ) ]; }
		foreach ( $this->in_group[ $group ] ?? array() as $term ) {
			$skill = $this->skills[ $term ];
			if ( self::same( $code, $name, $skill['code'], $skill['name'] ) ) {
				return $term; }
		}
		return null;
	}

	/**
	 * Skill for Syllabus plan.
	 *
	 * @param mixed $group Group.
	 * @param array $row Row.
	 */
	private function skill( $group, array $row ) {
		$name        = $row['skill'];
		$code        = $row['skill_code'];
		$description = $row['description'];
		$category    = $row['category'] ?? '';
		$found       = $this->find_skill( $group, $name, $code );
		if ( null === $found ) {
			$ref                        = $this->ref( 's' );
			$this->skills[ $ref ]       = array(
				'id'          => $ref,
				'group'       => $group,
				'name'        => $name,
				'code'        => $code,
				'description' => $description,
				'category'    => $category,
				'touched'     => true,
			);
			$this->in_group[ $group ][] = $ref;
			if ( '' !== $code ) {
				$this->by_code[ self::key( $code ) ] = $ref; }
			$this->push(
				array(
					'op'          => 'skill',
					'ref'         => $ref,
					'group'       => $group,
					'name'        => $name,
					'code'        => $code,
					'description' => $description,
					'category'    => $category,
				)
			);
			++$this->created['skill'];
			return;
		}
		$skill = $this->skills[ $found ];
		if ( $skill['touched'] ) {
			if ( $skill['group'] === $group ) {
				$this->warnings[] = array(
					'line'    => $row['line'],
					'message' => sprintf( /* translators: %s: text value. */
					__( '“%s” appears more than once in the file; the repeated row was ignored.', 'ohmylms' ), $name ),
				);
			} else {
				$this->errors[] = array(
					'line'    => $row['line'],
					'message' => sprintf( /* translators: %s: text value. */
					__( 'Skill code “%s” is used under more than one skill group.', 'ohmylms' ), $code ),
				);
			}
			return;
		}
		$this->skills[ $found ]['touched'] = true;
		if ( is_int( $found ) ) {
			$this->matched['skill'][ $found ] = true; }
		if ( $skill['group'] !== $group && is_int( $found ) ) {
			$this->push(
				array(
					'op'    => 'skill_move',
					'term'  => $found,
					'group' => $group,
				)
			);
			$this->moved[ $found ]             = true;
			$this->in_group[ $skill['group'] ] = array_values( array_diff( $this->in_group[ $skill['group'] ] ?? array(), array( $found ) ) );
			$this->in_group[ $group ][]        = $found;
			$this->skills[ $found ]['group']   = $group;
		}
		$fields = array();
		if ( $name !== $skill['name'] ) {
			$fields['name'] = $name; }
		if ( '' !== $code && $code !== $skill['code'] ) {
			$fields['code'] = $code;
			if ( isset( $this->by_code[ self::key( $code ) ] ) === false ) {
				$this->by_code[ self::key( $code ) ] = $found; }
		}
		if ( '' !== $description && $description !== $skill['description'] ) {
			$fields['description'] = $description; }
		if ( '' !== $category && $category !== ( $skill['category'] ?? '' ) ) {
			$fields['category'] = $category; }
		$this->set( 'skill', $found, $fields );
	}

	/**
	 * Row for Syllabus plan.
	 *
	 * @param array $row Row.
	 */
	private function row( array $row ) {
		$parent = $this->root;
		$last   = count( $row['content_path'] ) - 1;
		foreach ( $row['content_path'] as $i => $name ) {
			$parent = $this->content( $parent, $name, $i === $last ? $row['content_code'] : '' );
		}
		$has_skill = '' !== $row['skill'];
		$name      = $row['group'];
		if ( '' === $name && ! $has_skill ) {
			return; }
		if ( '' === $name ) {
			$name = (string) $this->options['default_group'];
			if ( ! isset( $this->defaulted[ (string) $parent ] ) ) {
				$this->defaulted[ (string) $parent ] = true;
				$this->warnings[]                    = array(
					'line'    => $row['line'],
					'message' => sprintf( /* translators: %s: text value. */
					__( 'Skills with no skill group were placed in a group called “%s”.', 'ohmylms' ), $name ),
				);
			}
		}
		$group = $this->group( $parent, $name, $row['group_code'] );
		if ( $has_skill ) {
			$this->skill( $group, $row ); }
	}

	/**
	 * Finish for Syllabus plan.
	 */
	private function finish() {
		$groups_total = count( $this->groups );
		$skills_total = count( $this->skills );
		if ( $groups_total > $this->options['max_groups'] ) {
			$this->errors[] = array(
				'line'    => 0,
				'message' => sprintf( /* translators: %d: number. */
				__( 'A syllabus can have at most %d skill groups.', 'ohmylms' ), $this->options['max_groups'] ),
			);
		}
		if ( $skills_total > $this->options['max_skills'] ) {
			$this->errors[] = array(
				'line'    => 0,
				'message' => sprintf( /* translators: %d: number. */
				__( 'A syllabus can have at most %d skills.', 'ohmylms' ), $this->options['max_skills'] ),
			);
		}
		$counted = static function ( array $updates, array $ops ) {
			$n = 0;
			foreach ( $updates as $index ) {
				if ( $ops[ $index ]['fields'] ) {
					++$n; }
			}
			return $n;
		};
		$report  = array();
		foreach ( array( 'content', 'group', 'skill' ) as $kind ) {
			$updated = $counted( $this->updates[ $kind ], $this->ops );
			$report[ 'content' === $kind ? 'contents' : $kind . 's' ] = array(
				'create' => $this->created[ $kind ],
				'update' => $updated,
			);
		}
		$changed = array();
		foreach ( $this->updates['skill'] as $id => $index ) {
			if ( $this->ops[ $index ]['fields'] ) {
				$changed[ $id ] = true; }
		}
		foreach ( array_keys( $this->moved ) as $id ) {
			$changed[ $id ] = true; }
		$report['skills']['move']        = count( $this->moved );
		$report['skills']['unchanged']   = count( array_diff_key( $this->matched['skill'], $changed ) );
		$report['contents']['unchanged'] = count(
			array_diff_key(
				$this->matched['content'],
				array_filter(
					array_map(
						function ( $index ) {
							return $this->ops[ $index ]['fields'] ? true : null;
						},
						$this->updates['content']
					)
				)
			)
		);
		$report['groups']['unchanged']   = count(
			array_diff_key(
				$this->matched['group'],
				array_filter(
					array_map(
						function ( $index ) {
							return $this->ops[ $index ]['fields'] ? true : null;
						},
						$this->updates['group']
					)
				)
			)
		);
		$report['errors']                = $this->errors;
		$report['warnings']              = $this->warnings;
		$report['valid']                 = ! $this->errors;
		// Updates that ended with no field changes are noise; drop them.
		$ops = array_values(
			array_filter(
				$this->ops,
				static function ( $op ) {
					return ! preg_match( '/_update$/', $op['op'] ) || $op['fields'];
				}
			)
		);
		return array(
			'ops'    => $ops,
			'report' => $report,
		);
	}
}
