<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Read-only question object built from an immutable version row.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Assessment;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only question object built from an immutable version row.
 *
 * It exposes the same accessors graders and renderers use on the editable
 * OhMyLMS\Data\Question, so a version-aware question type grades exactly what
 * the learner saw, never the current draft.
 */
class QuestionSnapshot {
	/**
	 * Version.
	 *
	 * @var mixed
	 */
	private $version;
	/**
	 * seed and numbers of a template instance.
	 *
	 * @var array|null seed and numbers of a template instance */
	private $instance = null;

	/**
	 * Initialize Question snapshot.
	 *
	 * @param array $version Version.
	 */
	public function __construct( array $version ) {
		$this->version = $version;
		foreach ( array( 'image_url', 'video_url' ) as $setting ) {
			if ( ! empty( $this->version['settings'][ $setting ] ) ) {
				$this->version['settings'][ $setting ] = $this->frozen_url( 'settings:' . $setting, (string) $this->version['settings'][ $setting ] );
			}
		}
		// Option images resolve to the copies frozen with this version.
		foreach ( (array) ( $version['options'] ?? array() ) as $index => $option ) {
			$id = (int) ( $option['id'] ?? 0 );
			if ( ! empty( $option['image_url'] ) ) {
				$this->version['options'][ $index ]['image_url'] = $this->frozen_url( 'option:' . $id, (string) $option['image_url'] ); }
			if ( ! empty( $option['matching_data']['image_url'] ) ) {
				$this->version['options'][ $index ]['matching_data']['image_url'] = $this->frozen_url( 'match:' . $id, (string) $option['matching_data']['image_url'] ); }
		}
		$parts = in_array( $this->get_type(), array( 'structured', 'passage' ), true ) ? (array) ( $this->version['settings']['parts'] ?? array() ) : array();
		foreach ( $parts as $index => $part ) {
			if ( ! is_array( $part ) ) {
				continue;
			}
			$prefix = 'part:' . ( $part['id'] ?? '' ) . ':';
			$frozen = array();
			foreach ( $this->frozen() as $key => $record ) {
				if ( 0 === strpos( $key, $prefix ) ) {
					$frozen[ substr( $key, strlen( $prefix ) ) ] = $record;
				}
			}
			$this->version['settings']['parts'][ $index ]['prompt'] = \OhMyLMS\QuestionBank\MediaFreezer::body( (string) ( $part['prompt'] ?? '' ), $frozen );
			if ( 'question' === ( $part['kind'] ?? '' ) && in_array( $part['question']['settings']['type'] ?? '', StructuredPart::TYPES, true ) ) {
				$child_data                    = StructuredPart::snapshot( $part )->to_array();
				$child_data['media']['frozen'] = $frozen;
				$child                         = new self( $child_data );
				$this->version['settings']['parts'][ $index ]['question']['settings']  = $child->get_settings();
				$this->version['settings']['parts'][ $index ]['question']['questions'] = $child->get_questions();
			}
		}
	}

	/** Decode a qb_question_versions row (JSON columns) into a snapshot.
	 *
	 * @param mixed $row Row.
	 */
	public static function from_row( $row ) {
		if ( ! $row ) {
			return null; }
		$row = (array) $row;
		foreach ( array( 'settings', 'options', 'media', 'extension', 'parts' ) as $column ) {
			$value          = isset( $row[ $column ] ) && is_string( $row[ $column ] ) ? json_decode( $row[ $column ], true ) : ( $row[ $column ] ?? array() );
			$row[ $column ] = is_array( $value ) ? $value : array();
		}
		return new self( $row );
	}

	/** Does this version carry a randomisation template? */
	public function is_template() {
		return Template::has( $this->version['settings'] );
	}

	/**
	 * The concrete question for one instance seed. A version without a template is returned
	 * unchanged, so callers can instantiate every item without checking.
	 *
	 * @param mixed $seed Seed.
	 */
	public function instantiate( $seed ) {
		if ( ! $this->is_template() ) {
			return $this; }
		$applied                    = Template::apply( $this->version['title'], $this->version['body'], $this->version['settings'], $this->version['options'], (int) $seed );
		$clone                      = clone $this;
		$clone->version['title']    = $applied['title'];
		$clone->version['body']     = $applied['body'];
		$clone->version['settings'] = $applied['settings'];
		$clone->version['options']  = $applied['options'];
		$clone->instance            = array(
			'seed'   => (int) $seed,
			'params' => $applied['params'],
		);
		return $clone;
	}

	/** Seed and numbers of this instance, or null for a plain question. */
	public function get_instance() {
		return $this->instance;
	}

	/**
	 * Get id for Question snapshot.
	 */
	public function get_id() {
		return (int) $this->version['question_id']; }
	/**
	 * Get version id for Question snapshot.
	 */
	public function get_version_id() {
		return (int) $this->version['id']; }
	/**
	 * Get version no for Question snapshot.
	 */
	public function get_version_no() {
		return (int) $this->version['version_no']; }
	/**
	 * Get uuid for Question snapshot.
	 */
	public function get_uuid() {
		return (string) $this->version['question_uuid']; }
	/**
	 * Get type for Question snapshot.
	 */
	public function get_type() {
		return (string) $this->version['type']; }
	/**
	 * Get name for Question snapshot.
	 */
	public function get_name() {
		return (string) $this->version['title']; }
	/**
	 * Get description for Question snapshot.
	 */
	public function get_description() {
		return \OhMyLMS\QuestionBank\MediaFreezer::body( (string) $this->version['body'], $this->frozen() ); }
	/**
	 * Get settings for Question snapshot.
	 */
	public function get_settings(): array {
		return $this->version['settings']; }
	/** Full option rows, including correctness. Server-side use only. */
	public function get_questions(): array {
		return $this->version['options']; }
	/**
	 * Get correct options for Question snapshot.
	 */
	public function get_correct_options() {
		return array_values(
			array_filter(
				$this->version['options'],
				static function ( $option ) {
					return ! empty( $option['is_correct'] );
				}
			)
		);
	}
	/**
	 * Get parts for Question snapshot.
	 */
	public function get_parts() {
		return $this->version['parts'] ?: array(
			array(
				'id'       => 'p1',
				'fraction' => 1,
			),
		); }
	/**
	 * Get extension settings for Question snapshot.
	 */
	public function get_extension_settings() {
		return $this->version['extension']; }
	/**
	 * Get thumbnail id for Question snapshot.
	 */
	public function get_thumbnail_id() {
		return (int) ( $this->version['media']['thumbnail_id'] ?? 0 ); }
	/**
	 * Get image id for Question snapshot.
	 */
	public function get_image_id() {
		return (int) ( $this->version['media']['image_id'] ?? 0 ); }
	/**
	 * Get video id for Question snapshot.
	 */
	public function get_video_id() {
		return (int) ( $this->version['media']['video_id'] ?? 0 ); }
	/**
	 * Get image url for Question snapshot.
	 */
	public function get_image_url() {
		return $this->frozen_url( 'image', (string) ( $this->version['media']['image_url'] ?? '' ) ); }
	/**
	 * Get video url for Question snapshot.
	 */
	public function get_video_url() {
		return $this->frozen_url( 'video', (string) ( $this->version['media']['video_url'] ?? '' ) ); }
	/** Frozen media copies captured with this version (see QuestionBank\MediaFreezer). */
	public function frozen() {
		return (array) ( $this->version['media']['frozen'] ?? array() ); }
	/**
	 * Frozen url for Question snapshot.
	 *
	 * @param mixed $key Lookup key.
	 * @param mixed $live Live.
	 */
	private function frozen_url( $key, $live ) {
		$frozen = $this->frozen();
		return isset( $frozen[ $key ]['url'] ) ? (string) $frozen[ $key ]['url'] : $live;
	}
	/**
	 * Is migration snapshot for Question snapshot.
	 */
	public function is_migration_snapshot() {
		return ! empty( $this->version['is_migration_snapshot'] ); }
	/**
	 * To array for Question snapshot.
	 */
	public function to_array() {
		return $this->version; }

	/** Types whose option text is the expected answer and must never reach the learner.
	 *
	 * @param mixed $type Type.
	 */
	public static function hides_option_text( $type ) {
		return in_array( $type, array( 'statement', 'fill-in-the-blank', 'short-text', 'long-text' ), true );
	}

	/**
	 * Learner-safe question in the array shape the quiz templates render.
	 *
	 * Correctness, expected text and teacher-only settings are removed. Option IDs are
	 * replaced by per-delivery tokens so markup cannot reveal matches or ordering.
	 *
	 * @param array  $option_order Option IDs in delivery order.
	 * @param array  $tokens option id => token.
	 * @param array  $display Extra frozen presentation data (e.g. matching definition order).
	 * @param array  $definition_tokens Matching definition tokens.
	 * @param string $scope Delivery token scope for nested parts.
	 */
	public function student_view( array $option_order = array(), array $tokens = array(), array $display = array(), array $definition_tokens = array(), $scope = '' ) {
		$type  = $this->get_type();
		$by_id = array();
		foreach ( $this->version['options'] as $option ) {
			$by_id[ (int) $option['id'] ] = $option; }
		$ordered = array();
		foreach ( $option_order ? $option_order : array_keys( $by_id ) as $option_id ) {
			if ( ! isset( $by_id[ (int) $option_id ] ) ) {
				continue; }
			$ordered[] = $this->safe_option( $by_id[ (int) $option_id ], $tokens, $type );
		}
		$definitions = array();
		foreach ( (array) ( $display['definitions'] ?? array() ) as $option_id ) {
			if ( isset( $by_id[ (int) $option_id ] ) ) {
				$definitions[] = $this->safe_option( $by_id[ (int) $option_id ], $definition_tokens ? $definition_tokens : $tokens, $type ); }
		}
		// Only settings a renderer needs are public; a type may name more via 'public_settings'.
		$definition = \OhMyLMS\Extensions\Registry::get( 'question', $type );
		$public     = array_merge( array( 'type', 'required', 'score', 'randomize' ), (array) ( $definition['public_settings'] ?? array() ) );
		$settings   = array_intersect_key( $this->get_settings(), array_flip( $public ) );
		if ( 'structured' === $type ) {
			$settings['parts'] = Structured::public_parts( $this->get_settings(), $this->get_id() . '|' . $this->get_version_id(), $scope ); }
		// A type with private keys inside public structures builds its own learner view.
		if ( is_callable( $definition['public_view'] ?? null ) ) {
			$settings = array_merge( $settings, (array) call_user_func( $definition['public_view'], $this->get_settings(), $this->get_id() . '|' . $this->get_version_id() ) ); }
		$body = $this->get_description();
		if ( 'fill-in-the-blank' === $type && ! empty( $this->get_settings()['question_code'] ) ) {
			$body = ''; }
		if ( function_exists( 'has_blocks' ) && has_blocks( $body ) ) {
			$body = apply_filters( 'the_content', $body ); }
		$body .= \OhMyLMS\ExamImport\FigureContent::html( $this, (string) $scope );
		foreach ( $settings['parts'] ?? array() as $index => $part ) {
			$settings['parts'][ $index ]['prompt'] .= \OhMyLMS\ExamImport\FigureContent::html( $this, (string) $scope, $part['id'] );
		}
		return InlineBlanks::public_view(
			array(
				'id'          => $this->get_id(),
				'uuid'        => $this->get_uuid(),
				'version_id'  => $this->get_version_id(),
				'name'        => $this->get_name(),
				'description' => $body,
				'settings'    => $settings,
				'questions'   => $ordered,
				'definitions' => $definitions,
				'image_src'   => $this->get_image_url(),
				'video_src'   => $this->get_video_url(),
				'frozen'      => true,
			)
		);
	}

	/**
	 * Safe option for Question snapshot.
	 *
	 * @param array $option Option.
	 * @param array $tokens Tokens.
	 * @param mixed $type Type.
	 */
	private function safe_option( array $option, array $tokens, $type ) {
		$id       = (int) $option['id'];
		$matching = is_array( $option['matching_data'] ?? null ) ? $option['matching_data'] : array();
		return array(
			'id'            => $tokens[ $id ] ?? (string) $id,
			'question_id'   => $this->get_id(),
			'answer'        => self::hides_option_text( $type ) ? '' : (string) ( $option['answer'] ?? '' ),
			'order_number'  => 0,
			'image_url'     => (string) ( $option['image_url'] ?? '' ),
			'thumbnail_id'  => (int) ( $option['thumbnail_id'] ?? 0 ),
			'matching_data' => array_intersect_key( $matching, array_flip( array( 'label', 'image_url' ) ) ),
		);
	}
}
