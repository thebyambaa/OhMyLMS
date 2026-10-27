<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Where each skill came from, and whether a person has approved it.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Skills;

defined( 'ABSPATH' ) || exit;

/**
 * Every skill term records its origin when it is created: the source (manual, syllabus, import, ai,
 * or a registered skill source), a reference to what produced it, and the details an AI source adds
 * (provider, model, prompt version, confidence). Creators declare their origin with with(); a skill
 * created without one, for example by other plugins through wp_insert_term(), is recorded as manual.
 *
 * Review states: approved (the default for anything a person created or accepted), pending (proposed
 * by a source and usable in course drafts, but blocking publication until approved) and rejected.
 */
final class Provenance {

	const SOURCE_META = '_ohmylms_skill_source';
	const REF_META    = '_ohmylms_skill_source_ref';
	const REVIEW_META = '_ohmylms_skill_review';
	const ORIGIN_META = '_ohmylms_skill_origin';
	const STATUS_META = '_ohmylms_skill_status';
	const BACKFILL    = 'ohmylms_skill_provenance_backfill';
	const REVIEWS     = array( 'approved', 'pending', 'rejected' );
	const BUILT_IN    = array( 'manual', 'syllabus', 'import', 'ai' );

	/**
	 * Origins declared by the creators currently running, innermost last.
	 *
	 * @var array[]
	 */
	private static $context = array();

	/** Record the origin of every new skill. */
	public static function init() {
		add_action( 'created_' . Taxonomy::NAME, array( __CLASS__, 'created' ) );
		add_action( 'init', array( __CLASS__, 'maybe_backfill' ), 40 );
	}

	/**
	 * Run a creator with a declared origin; skills it creates record that origin.
	 *
	 * @param array    $origin source, ref, review, provider, model, prompt, confidence.
	 * @param callable $work   Creator.
	 * @return mixed What the creator returns.
	 */
	public static function with( array $origin, callable $work ) {
		self::$context[] = $origin;
		try {
			return $work();
		} finally {
			array_pop( self::$context );
		}
	}

	/**
	 * Store the origin of a skill that was just created.
	 *
	 * @param int $term_id Skill term ID.
	 */
	public static function created( $term_id ) {
		if ( '' !== (string) get_term_meta( (int) $term_id, self::SOURCE_META, true ) ) {
			return;
		}
		self::record( (int) $term_id, self::$context ? end( self::$context ) : array( 'source' => 'manual' ) );
	}

	/**
	 * Store an origin on a skill.
	 *
	 * @param int   $term_id Skill term ID.
	 * @param array $origin  source, ref, review, provider, model, prompt, confidence.
	 */
	public static function record( $term_id, array $origin ) {
		$source = self::source( $origin['source'] ?? 'manual' );
		$review = in_array( $origin['review'] ?? '', self::REVIEWS, true ) ? $origin['review'] : 'approved';
		update_term_meta( $term_id, self::SOURCE_META, $source );
		update_term_meta( $term_id, self::REF_META, sanitize_text_field( (string) ( $origin['ref'] ?? '' ) ) );
		update_term_meta( $term_id, self::REVIEW_META, $review );
		update_term_meta(
			$term_id,
			self::ORIGIN_META,
			array(
				'provider'    => sanitize_text_field( (string) ( $origin['provider'] ?? '' ) ),
				'model'       => sanitize_text_field( (string) ( $origin['model'] ?? '' ) ),
				'prompt'      => sanitize_text_field( (string) ( $origin['prompt'] ?? '' ) ),
				'confidence'  => isset( $origin['confidence'] ) ? round( max( 0.0, min( 1.0, (float) $origin['confidence'] ) ), 3 ) : null,
				'created_by'  => get_current_user_id(),
				'created_at'  => current_time( 'mysql', true ),
				'reviewed_by' => 'approved' === $review && ( $origin['reviewed'] ?? false ) ? get_current_user_id() : 0,
			)
		);
	}

	/**
	 * A valid source id: a built-in source or a registered skill source.
	 *
	 * @param mixed $source Source id.
	 * @return string
	 */
	public static function source( $source ) {
		$source = sanitize_key( (string) $source );
		return in_array( $source, self::BUILT_IN, true ) || isset( SkillSources::all()[ $source ] ) ? $source : 'manual';
	}

	/**
	 * Origin and review state of a skill, with defaults for skills created before provenance existed.
	 *
	 * @param int $term_id Skill term ID.
	 * @return array source, ref, review, status, provider, model, prompt, confidence, created_by, created_at, reviewed_by.
	 */
	public static function read( $term_id ) {
		$origin = get_term_meta( (int) $term_id, self::ORIGIN_META, true );
		$origin = is_array( $origin ) ? $origin : array();
		return array(
			'source' => (string) get_term_meta( (int) $term_id, self::SOURCE_META, true ) ?: 'manual', // phpcs:ignore Universal.Operators.DisallowShortTernary.Found -- Empty meta means an unrecorded, manual skill.
			'ref'    => (string) get_term_meta( (int) $term_id, self::REF_META, true ),
			'review' => (string) get_term_meta( (int) $term_id, self::REVIEW_META, true ) ?: 'approved', // phpcs:ignore Universal.Operators.DisallowShortTernary.Found -- Skills from before provenance are approved.
			'status' => self::archived( $term_id ) ? 'archived' : 'active',
		) + $origin + array(
			'provider'    => '',
			'model'       => '',
			'prompt'      => '',
			'confidence'  => null,
			'created_by'  => 0,
			'created_at'  => '',
			'reviewed_by' => 0,
		);
	}

	/**
	 * Review states of several skills, keyed by term ID.
	 *
	 * @param int[] $term_ids Skill term IDs.
	 * @return array<int,string>
	 */
	public static function reviews( array $term_ids ) {
		$result = array();
		foreach ( $term_ids as $id ) {
			$review        = (string) get_term_meta( (int) $id, self::REVIEW_META, true );
			$result[ $id ] = '' !== $review ? $review : 'approved';
		}
		return $result;
	}

	/**
	 * Approve or reject a skill.
	 *
	 * @param int    $term_id Skill term ID.
	 * @param string $review  approved or rejected.
	 * @return array|\WP_Error The skill's provenance.
	 */
	public static function review( $term_id, $review ) {
		if ( ! term_exists( (int) $term_id, Taxonomy::NAME ) ) {
			return new \WP_Error( 'ohmylms_skill_missing', __( 'Skill not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		if ( ! in_array( $review, array( 'approved', 'rejected' ), true ) ) {
			return new \WP_Error( 'ohmylms_skill_review_invalid', __( 'Approve or reject the skill.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		update_term_meta( (int) $term_id, self::REVIEW_META, $review );
		$origin                = get_term_meta( (int) $term_id, self::ORIGIN_META, true );
		$origin                = is_array( $origin ) ? $origin : array();
		$origin['reviewed_by'] = get_current_user_id();
		$origin['reviewed_at'] = current_time( 'mysql', true );
		update_term_meta( (int) $term_id, self::ORIGIN_META, $origin );
		do_action( 'ohmylms_skill_reviewed', (int) $term_id, $review );
		return self::read( (int) $term_id );
	}

	/**
	 * Whether a skill is archived: kept for courses and learner history, but not offered for new use.
	 *
	 * @param int $term_id Skill term ID.
	 * @return bool
	 */
	public static function archived( $term_id ) {
		return 'archived' === get_term_meta( (int) $term_id, self::STATUS_META, true );
	}

	/**
	 * Record the source of skills created before provenance existed, once. Existing skills are
	 * approved; their source is inferred from what the plugin already stores about them.
	 */
	public static function maybe_backfill() {
		if ( get_option( self::BACKFILL ) ) {
			return;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time read of skill terms without a recorded source.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT tt.term_id, tt.parent FROM %i tt LEFT JOIN %i m ON m.term_id=tt.term_id AND m.meta_key=%s WHERE tt.taxonomy=%s AND m.meta_id IS NULL', $wpdb->term_taxonomy, $wpdb->termmeta, self::SOURCE_META, Taxonomy::NAME ), ARRAY_A );
		$ai   = array();
		if ( \OhMyLMS\Curriculum\Schema::ready() ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time read of skills created by accepted AI suggestions.
			$ai = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT result_term_id FROM %i WHERE kind=%s AND status=%s AND result_term_id>0', \OhMyLMS\Curriculum\Schema::table( 'ai_suggestions' ), 'new_skill', 'accepted' ) ) );
		}
		foreach ( $rows as $row ) {
			$id = (int) $row['term_id'];
			// Shared skills carry a subject; syllabus objectives sit under a syllabus root skill, which marks itself.
			$shared = metadata_exists( 'term', $id, \OhMyLMS\Curriculum\Comparison::SUBJECT_META );
			if ( in_array( $id, $ai, true ) ) {
				$source = 'ai';
			} elseif ( ! $shared && ( (int) $row['parent'] > 0 || metadata_exists( 'term', $id, \OhMyLMS\Curriculum\Syllabus::ROOT_META ) ) ) {
				$source = 'syllabus';
			} else {
				$source = 'manual';
			}
			update_term_meta( $id, self::SOURCE_META, $source );
			update_term_meta( $id, self::REVIEW_META, 'approved' );
		}
		update_option( self::BACKFILL, gmdate( 'c' ), false );
	}
}
