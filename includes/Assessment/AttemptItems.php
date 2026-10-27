<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Creates and reads the frozen delivery of a versioned attempt: which question
 * versions were issued, in which order, with which option order and marks.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Assessment;

use OhMyLMS\QuestionBank\VersionPublisher;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and reads the frozen delivery of a versioned attempt: which question
 * versions were issued, in which order, with which option order and marks.
 *
 * Selection and ordering happen once, at attempt start, from a stored seed;
 * refreshing the page never reshuffles.
 */
final class AttemptItems {
	/**
	 * Create the attempt context and items for an attempt row inside the caller's transaction.
	 *
	 * @param mixed $attempt_id Attempt identifier.
	 * @param array $revision Published revision with slots.
	 * @param mixed $started_ts Started ts.
	 * @throws \RuntimeException When the operation cannot be completed.
	 * @throws ErrorException When the operation cannot be completed.
	 */
	public static function create( $attempt_id, array $revision, $started_ts = null ) {
		global $wpdb;
		$attempt_id               = (int) $attempt_id;
		$started_ts               = $started_ts ? $started_ts : time();
		$seed                     = random_int( 1, PHP_INT_MAX );
		$settings                 = $revision['settings'];
		$minutes                  = (float) ( $settings['timer_minutes'] ?? 0 );
		$context                  = array(
			'attempt_id'    => $attempt_id,
			'revision_id'   => (int) $revision['id'],
			'engine'        => 'versioned',
			'scoring'       => Scoring::policy_for_new_attempts(),
			'started_at'    => gmdate( 'Y-m-d H:i:s', $started_ts ),
			'deadline_at'   => $minutes > 0 ? gmdate( 'Y-m-d H:i:s', $started_ts + (int) round( $minutes * 60 ) ) : null,
			'grace_seconds' => max( 0, (int) ( $settings['grace_seconds'] ?? Deadlines::DEFAULT_GRACE ) ),
			'extra_seconds' => 0,
			'seed'          => $seed,
		);
		$context['extra_seconds'] = (int) apply_filters( 'ohmylms_attempt_extra_seconds', 0, $attempt_id, $revision );
		if ( $context['extra_seconds'] > 0 && $context['deadline_at'] ) {
			$context['deadline_at'] = gmdate( 'Y-m-d H:i:s', strtotime( $context['deadline_at'] . ' UTC' ) + $context['extra_seconds'] );
		}
		// The closing time ends every attempt, timed or not, even one started too late for its full time limit.
		$closes_ts = AssessmentSettings::closes_ts( (int) ( $revision['quiz_id'] ?? 0 ) );
		if ( $closes_ts && ( ! $context['deadline_at'] || $closes_ts < strtotime( $context['deadline_at'] . ' UTC' ) ) ) {
			$context['deadline_at'] = gmdate( 'Y-m-d H:i:s', $closes_ts );
		}
		if ( ! $wpdb->insert( Schema::table( 'attempt_context' ), $context ) ) {
			throw new \RuntimeException( 'Attempt context write failed' ); }

		$slots = $revision['slots'];
		if ( ! empty( $settings['randomize_questions'] ) ) {
			$slots = self::seeded_order( $slots, $seed, 'slots', 'slot_no' ); }
		$used = array();
		foreach ( $slots as $slot ) {
			if ( ! empty( $slot['question_id'] ) ) {
				$used[] = (int) $slot['question_id']; }
		}
		$position = 0;
		foreach ( $slots as $slot ) {
			$version_id = (int) $slot['version_id'];
			if ( ! $version_id && ! empty( $slot['pool'] ) ) {
				$version_id = Pools::draw( $slot['pool'], $seed, $used, $attempt_id );
				if ( ! $version_id ) {
					throw new ErrorException( new \WP_Error( 'quiz_pool_shortage', __( 'This assessment cannot be started: a random question pool has run out of eligible questions. Please tell your teacher.', 'ohmylms' ), array( 'status' => 409 ) ) );
				}
			}
			$snapshot = VersionPublisher::snapshot( $version_id );
			if ( ! $snapshot ) {
				throw new \RuntimeException( 'Version missing' ); }
			$used[]     = $snapshot->get_id();
			$option_ids = array_map(
				static function ( $option ) {
					return (int) $option['id'];
				},
				$snapshot->get_questions()
			);
			$display    = array();
			if ( ! empty( $slot['shuffle_options'] ) || in_array( $snapshot->get_type(), array( 'reorder', 'matching' ), true ) ) {
				$option_ids = self::seeded_ids( $option_ids, $seed, 'options:' . $slot['slot_no'] );
			}
			if ( $snapshot->get_type() === 'matching' ) {
				$display['definitions'] = self::seeded_ids( $option_ids, $seed, 'definitions:' . $slot['slot_no'] );
			}
			// A template question gets its own numbers for this attempt, fixed from here on.
			if ( $snapshot->is_template() ) {
				$display['instance_seed'] = Template::seed_from( $seed . ':instance:' . $slot['slot_no'] );
			}
			$row = array(
				'attempt_id'    => $attempt_id,
				'position'      => ++$position,
				'slot_no'       => (int) $slot['slot_no'],
				'question_id'   => $snapshot->get_id(),
				'question_uuid' => $snapshot->get_uuid(),
				'version_id'    => $version_id,
				'marks'         => (float) $slot['marks'],
				'option_order'  => wp_json_encode( $option_ids ),
				'display'       => wp_json_encode(
					$display + array(
						'section'  => (string) ( $slot['section'] ?? '' ),
						'page'     => (int) ( $slot['page'] ?? 0 ),
						'required' => (int) ( $slot['required'] ?? 0 ),
					)
				),
				'status'        => 'unanswered',
			);
			if ( ! $wpdb->insert( Schema::table( 'attempt_items' ), $row ) ) {
				throw new \RuntimeException( 'Attempt item write failed' ); }
		}
		return $context;
	}

	/**
	 * Seeded order for Attempt items.
	 *
	 * @param array $rows Rows.
	 * @param mixed $seed Seed.
	 * @param mixed $salt Salt.
	 * @param mixed $key Lookup key.
	 */
	private static function seeded_order( array $rows, $seed, $salt, $key ) {
		usort(
			$rows,
			static function ( $left, $right ) use ( $seed, $salt, $key ) {
				return strcmp( hash( 'sha256', $seed . ':' . $salt . ':' . $left[ $key ] ), hash( 'sha256', $seed . ':' . $salt . ':' . $right[ $key ] ) );
			}
		);
		return $rows;
	}

	/**
	 * Seeded ids for Attempt items.
	 *
	 * @param array $ids Ids.
	 * @param mixed $seed Seed.
	 * @param mixed $salt Salt.
	 */
	private static function seeded_ids( array $ids, $seed, $salt ) {
		usort(
			$ids,
			static function ( $left, $right ) use ( $seed, $salt ) {
				return strcmp( hash( 'sha256', $seed . ':' . $salt . ':' . $left ), hash( 'sha256', $seed . ':' . $salt . ':' . $right ) );
			}
		);
		return $ids;
	}

	/**
	 * Context for Attempt items.
	 *
	 * @param mixed $attempt_id Attempt identifier.
	 */
	public static function context( $attempt_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE attempt_id=%d', Schema::table( 'attempt_context' ), (int) $attempt_id ), ARRAY_A ) ?: null;
	}

	/**
	 * Is versioned for Attempt items.
	 *
	 * @param mixed $attempt_id Attempt identifier.
	 */
	public static function is_versioned( $attempt_id ) {
		return (bool) self::context( $attempt_id );
	}

	/**
	 * Items for Attempt items.
	 *
	 * @return array[] decoded items in delivery order.
	 * @param mixed $attempt_id Attempt identifier.
	 */
	public static function items( $attempt_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE attempt_id=%d ORDER BY position', Schema::table( 'attempt_items' ), (int) $attempt_id ), ARRAY_A );
		foreach ( $rows as &$row ) {
			$row['option_order'] = json_decode( $row['option_order'], true ) ?: array();
			$row['display']      = json_decode( $row['display'], true ) ?: array();
			$row['response']     = null === $row['response'] ? null : json_decode( $row['response'], true );
		}
		return $rows;
	}

	/**
	 * Opaque per-attempt token; reveals neither the option ID nor ordering. Matching
	 * definitions use a separate kind so a definition never shares its answer's token.
	 *
	 * @param mixed  $scope Scope.
	 * @param mixed  $option_id Option identifier.
	 * @param string $kind Kind.
	 */
	public static function token( $scope, $option_id, $kind = 'o' ) {
		// $scope is the attempt ID, or a practice/inline scope string such as "p42".
		$kind = 'd' === $kind ? 'd' : 'o';
		return $kind . substr( hash_hmac( 'sha256', $kind . ':' . (string) $scope . ':' . (int) $option_id, wp_salt( 'nonce' ) ), 0, 15 );
	}

	/**
	 * Tokens for Attempt items.
	 *
	 * @param mixed  $attempt_id Attempt identifier.
	 * @param array  $option_ids Option ids.
	 * @param string $kind Kind.
	 */
	public static function tokens( $attempt_id, array $option_ids, $kind = 'o' ) {
		$tokens = array();
		foreach ( $option_ids as $option_id ) {
			$tokens[ (int) $option_id ] = self::token( $attempt_id, $option_id, $kind ); }
		return $tokens;
	}

	/** The seed that fixes this item's numbers (0 for a question without a template).
	 *
	 * @param array $item Item.
	 */
	public static function instance_seed( array $item ) {
		return (int) ( $item['display']['instance_seed'] ?? 0 );
	}

	/** The frozen version as this item issued it: a template question gets its own numbers.
	 *
	 * @param array $item Item.
	 */
	public static function snapshot( array $item ) {
		$snapshot = VersionPublisher::snapshot( $item['version_id'] );
		return $snapshot ? $snapshot->instantiate( self::instance_seed( $item ) ) : null;
	}

	/** Learner-safe questions for rendering, in delivery order.
	 *
	 * @param mixed $attempt_id Attempt identifier.
	 */
	public static function delivery( $attempt_id ) {
		$questions = array();
		foreach ( self::items( $attempt_id ) as $item ) {
			$snapshot = self::snapshot( $item );
			if ( ! $snapshot ) {
				continue; }
			$ids                          = array_map(
				static function ( $option ) {
					return (int) $option['id'];
				},
				$snapshot->get_questions()
			);
			$view                         = $snapshot->student_view( $item['option_order'], self::tokens( $attempt_id, $ids ), $item['display'], self::tokens( $attempt_id, $ids, 'd' ), $attempt_id );
			$view['item_id']              = (int) $item['id'];
			$view['position']             = (int) $item['position'];
			$view['marks']                = (float) $item['marks'];
			$view['section']              = (string) ( $item['display']['section'] ?? '' );
			$view['page']                 = (int) ( $item['display']['page'] ?? 0 );
			$view['settings']['required'] = ! empty( $item['display']['required'] );
			$view['settings']['score']    = array(
				'enabled' => true,
				'value'   => (float) $item['marks'],
			);
			$questions[]                  = $view;
		}
		return $questions;
	}

	/** Inverse of untokenize(): a stored response expressed in this delivery's tokens (for resume).
	 *
	 * @param mixed $attempt_id Attempt identifier.
	 * @param array $item Item.
	 * @param mixed $answer Answer.
	 */
	public static function tokenize( $attempt_id, array $item, $answer ) {
		$snapshot = self::snapshot( $item );
		if ( ! $snapshot || ! is_array( $answer ) ) {
			return $answer; }
		if ( 'structured' === $snapshot->get_type() ) {
			return Structured::map_response( $snapshot, $answer, $attempt_id, false );
		}

		$ids         = array_map(
			static function ( $option ) {
				return (int) $option['id'];
			},
			$snapshot->get_questions()
		);
		$tokens      = self::tokens( $attempt_id, $ids );
		$definitions = self::tokens( $attempt_id, $ids, 'd' );
		$matching    = $snapshot->get_type() === 'matching';
		$result      = array();
		foreach ( $answer as $key => $value ) {
			if ( $matching ) {
				$key = isset( $definitions[ (int) $key ] ) ? $definitions[ (int) $key ] : $key; }
			$result[ $key ] = is_scalar( $value ) && ctype_digit( (string) $value ) && isset( $tokens[ (int) $value ] ) ? $tokens[ (int) $value ] : $value;
		}
		return $result;
	}

	/**
	 * Map submitted tokens back to option IDs for one item.
	 *
	 * For option-referencing types every value (and matching key) must be a token of this
	 * delivery; anything else, including a raw option ID, is neutralized so it cannot match.
	 * Text answers pass through unchanged.
	 *
	 * @param mixed $attempt_id Attempt identifier.
	 * @param array $item Item.
	 * @param mixed $answer Answer.
	 */
	public static function untokenize( $attempt_id, array $item, $answer ) {
		$snapshot = self::snapshot( $item );
		if ( ! $snapshot || ! is_array( $answer ) ) {
			return $answer; }
		if ( 'structured' === $snapshot->get_type() ) {
			return Structured::map_response( $snapshot, $answer, $attempt_id, true );
		}

		$ids            = array_map(
			static function ( $option ) {
				return (int) $option['id'];
			},
			$snapshot->get_questions()
		);
		$map            = array_flip( self::tokens( $attempt_id, $ids ) );
		$definition_map = array_flip( self::tokens( $attempt_id, $ids, 'd' ) );
		$definition     = \OhMyLMS\Extensions\Registry::get( 'question', $snapshot->get_type() );
		$strict         = ( $definition['answers'] ?? 'text' ) === 'options';
		$resolve        = static function ( $value ) use ( $map, $strict ) {
			if ( ! is_scalar( $value ) ) {
				return $value; }
			if ( isset( $map[ (string) $value ] ) ) {
				return (string) $map[ (string) $value ]; }
			return $strict && '' !== (string) $value ? 'invalid:' . (string) $value : $value;
		};
		$result         = array();
		foreach ( $answer as $key => $value ) {
			// Matching answers are keyed by definition; other types are plain lists.
			if ( $snapshot->get_type() === 'matching' ) {
				$key = is_string( $key ) && isset( $definition_map[ $key ] ) ? (string) $definition_map[ $key ] : 'invalid:' . $key; }
			$result[ $key ] = $resolve( $value );
		}
		return $result;
	}
}
