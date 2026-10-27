<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * The single writer for editable (draft) question content: post fields, settings,
 * media and answer options, plus quiz placement.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\QuestionBank;

use OhMyLMS\Data\Question;
use OhMyLMS\DataException;
use OhMyLMS\Extensions\Authoring;
use OhMyLMS\Extensions\Registry;
use OhMyLMS\Utility\Transaction;

defined( 'ABSPATH' ) || exit;

/**
 * The single writer for editable (draft) question content: post fields, settings,
 * media and answer options, plus quiz placement.
 *
 * Every payload is validated and authorized before anything is written, and each
 * save runs in one transaction. A batch either saves completely or reports every
 * failing child without writing.
 */
final class DraftWriter {
	const OPTION_META = array(
		'thumbnail_id'  => '_thumbnail_id',
		'image_url'     => '_image_url',
		'matching_data' => '_matching_data',
	);

	/**
	 * Validate and save one question.
	 *
	 * @param array|\WP_REST_Request $data Question payload.
	 * @param int                    $quiz_id Quiz to place the question in (0 keeps placement unchanged).
	 * @return array|\WP_Error ['id'=>int,'created'=>bool]
	 */
	public static function save( $data, $quiz_id = 0 ) {
		$plan = self::prepare( self::payload( $data ), (int) $quiz_id );
		if ( is_wp_error( $plan ) ) {
			return $plan; }
		try {
			return Transaction::run(
				static function () use ( $plan ) {
					return self::write( $plan );
				}
			);
		} catch ( DataException $error ) {
			return new \WP_Error( $error->getErrorCode(), $error->getMessage(), array( 'status' => $error->getCode() ?: 400 ) );
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'ohmylms_question_storage', __( 'The question could not be saved. Nothing was changed.', 'ohmylms' ), array( 'status' => 500 ) );
		}
	}

	/**
	 * Validate every question first, then save all of them in one transaction.
	 *
	 * @return array|\WP_Error List of ['id','created'] in input order, or an error whose
	 *                         data.errors lists each failing child by index.
	 * @param array $items Items.
	 * @param mixed $quiz_id Quiz identifier.
	 */
	public static function save_many( array $items, $quiz_id ) {
		$quiz_id = (int) $quiz_id;
		$plans   = self::prepare_many( $items, $quiz_id );
		if ( is_wp_error( $plans ) ) {
			return $plans; }
		try {
			return Transaction::run(
				static function () use ( $plans ) {
					return array_map( array( __CLASS__, 'write' ), $plans );
				}
			);
		} catch ( DataException $error ) {
			return new \WP_Error( $error->getErrorCode(), $error->getMessage(), array( 'status' => $error->getCode() ?: 400 ) );
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'ohmylms_question_storage', __( 'The questions could not be saved. Nothing was changed.', 'ohmylms' ), array( 'status' => 500 ) );
		}
	}

	/** Validate a batch without writing; returns plans or an aggregate WP_Error.
	 *
	 * @param array $items Items.
	 * @param mixed $quiz_id Quiz identifier.
	 */
	public static function prepare_many( array $items, $quiz_id ) {
		$plans  = array();
		$errors = array();
		$seen   = array();
		foreach ( array_values( $items ) as $index => $item ) {
			$payload = self::payload( $item );
			$plan    = self::prepare( $payload, (int) $quiz_id );
			if ( ! is_wp_error( $plan ) && $plan['id'] && isset( $seen[ $plan['id'] ] ) ) {
				$plan = new \WP_Error( 'ohmylms_question_duplicate', __( 'The same question appears twice in this save.', 'ohmylms' ), array( 'status' => 400 ) );
			}
			if ( is_wp_error( $plan ) ) {
				$data     = (array) $plan->get_error_data();
				$errors[] = array(
					'index'   => $index,
					'id'      => (int) ( $payload['id'] ?? 0 ),
					'code'    => $plan->get_error_code(),
					'message' => $plan->get_error_message(),
					'status'  => (int) ( $data['status'] ?? 400 ),
				);
				continue;
			}
			if ( $plan['id'] ) {
				$seen[ $plan['id'] ] = true; }
			$plans[] = $plan;
		}
		if ( $errors ) {
			$status = max( array_column( $errors, 'status' ) );
			$first  = $errors[0];
			return new \WP_Error(
				'ohmylms_question_batch_invalid',
				sprintf(
				/* translators: 1: number of failing questions, 2: first error message */
					_n( '%1$d question could not be saved: %2$s', '%1$d questions could not be saved. First problem: %2$s', count( $errors ), 'ohmylms' ),
					count( $errors ),
					$first['message']
				),
				array(
					'status' => $status,
					'errors' => $errors,
				)
			);
		}
		return $plans;
	}

	/** Normalize, authorize and validate one payload. No writes.
	 *
	 * @param array $data Input data.
	 * @param int   $quiz_id Quiz identifier.
	 */
	public static function prepare( array $data, $quiz_id = 0 ) {
		$id = isset( $data['id'] ) && '' !== $data['id'] ? (int) $data['id'] : 0;
		if ( $id && get_post_type( $id ) !== OHMYLMS_QUESTION_CPT ) {
			return new \WP_Error( 'ohmylms_rest_question_invalid_id', __( 'Question ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		if ( $quiz_id && ! AccessPolicy::can_edit_quiz( $quiz_id ) ) {
			return AccessPolicy::denied( __( 'You are not allowed to edit this quiz.', 'ohmylms' ) );
		}
		if ( ! AccessPolicy::can_edit_question( $id ) ) {
			// A shared question may be placed in the user's quiz unchanged, but never edited.
			if ( $id && $quiz_id && AccessPolicy::can_use_question( $id ) && ! self::changes_content( $data, $id ) ) {
				return array(
					'id'           => $id,
					'quiz_id'      => (int) $quiz_id,
					'fields'       => array(),
					'options'      => null,
					'order_number' => isset( $data['order_number'] ) ? (int) $data['order_number'] : null,
					'skills'       => null,
					'bank'         => null,
					'readonly'     => true,
				);
			}
			return AccessPolicy::denied(
				$id && AccessPolicy::can_use_question( $id )
				? __( 'This shared question is read-only for you. Duplicate it to make changes.', 'ohmylms' )
				: __( 'You are not allowed to edit this question.', 'ohmylms' )
			);
		}
		if ( $id && ! empty( $data['base_modified'] ) ) {
			$current = get_post_field( 'post_modified_gmt', $id );
			if ( $current && strtotime( $current . ' UTC' ) > strtotime( (string) $data['base_modified'] . ' UTC' ) ) {
				return new \WP_Error(
					'ohmylms_question_conflict',
					__( 'This question was changed by someone else. Reload it before saving.', 'ohmylms' ),
					array(
						'status'   => 409,
						'id'       => $id,
						'modified' => $current,
					)
				);
			}
		}
		$plan = array(
			'id'           => $id,
			'quiz_id'      => (int) $quiz_id,
			'fields'       => array(),
			'options'      => null,
			'order_number' => null,
		);
		// REST data is unslashed: filter with the unslashed kses API so LaTeX backslashes survive.
		$unfiltered = current_user_can( 'unfiltered_html' );
		foreach ( array( 'name', 'description', 'slug' ) as $field ) {
			if ( isset( $data[ $field ] ) ) {
				if ( ! is_scalar( $data[ $field ] ) ) {
					return self::invalid( sprintf( /* translators: %s: text value. */
					__( '%s must be text.', 'ohmylms' ), $field ) ); }
				$value                    = (string) $data[ $field ];
				$plan['fields'][ $field ] = 'slug' === $field ? sanitize_title( $value ) : ( $unfiltered ? $value : wp_kses_post( $value ) );
			}
		}
		if ( isset( $data['status'] ) ) {
			$plan['fields']['status'] = is_string( $data['status'] ) && get_post_status_object( $data['status'] ) ? $data['status'] : 'draft';
		}
		if ( isset( $data['settings'] ) ) {
			if ( ! is_array( $data['settings'] ) ) {
				return self::invalid( __( 'Question settings must be an object.', 'ohmylms' ) ); }
			$type = (string) ( $data['settings']['type'] ?? '' );
			if ( '' !== $type && ! Registry::get( 'question', $type ) ) {
				return new \WP_Error( 'ohmylms_question_type_missing', sprintf( /* translators: %s: text value. */
				__( 'Question type "%s" is not available. Enable its extension before saving.', 'ohmylms' ), $type ), array( 'status' => 400 ) );
			}
			$probe = new Question();
			$probe->set_settings( $data['settings'] );
			try {
				Authoring::validate_question( $probe ); } catch ( DataException $error ) {
				return new \WP_Error( $error->getErrorCode(), $error->getMessage(), array( 'status' => 400 ) );
				}
				$plan['fields']['settings'] = $data['settings'];
		}
		foreach ( array( 'thumbnail_id', 'video_id' ) as $field ) {
			if ( isset( $data[ $field ] ) ) {
				if ( '' !== $data[ $field ] && ! is_numeric( $data[ $field ] ) ) {
					return self::invalid( sprintf( /* translators: %s: text value. */
					__( '%s must be a media ID.', 'ohmylms' ), $field ) ); }
				$plan['fields'][ $field ] = '' === $data[ $field ] ? '' : (int) $data[ $field ];
			}
		}
		if ( isset( $data['order_number'] ) ) {
			$plan['order_number'] = (int) $data['order_number']; }
		if ( array_key_exists( 'questions', $data ) ) {
			$options = self::prepare_options( $data['questions'], $id );
			if ( is_wp_error( $options ) ) {
				return $options; }
			$plan['options'] = $options;
		}
		$plan['skills'] = null;
		if ( isset( $data['skills'] ) ) {
			$skills = SkillMap::validate( $data['skills'] );
			if ( is_wp_error( $skills ) ) {
				return $skills; }
			$plan['skills'] = $skills;
		}
		$plan['bank'] = null;
		if ( isset( $data['bank'] ) ) {
			$bank = BankAttributes::prepare( $data['bank'], $id );
			if ( is_wp_error( $bank ) ) {
				return $bank; }
			$plan['bank'] = $bank;
		}
		return $plan;
	}

	/** Option rows must belong to the question they are saved with.
	 *
	 * @param mixed $options Operation options.
	 * @param mixed $question_id Question identifier.
	 */
	private static function prepare_options( $options, $question_id ) {
		if ( null === $options || '' === $options ) {
			return null; }
		if ( ! is_array( $options ) ) {
			return self::invalid( __( 'Answer options must be a list.', 'ohmylms' ) ); }
		// Legacy semantics: an empty list leaves existing options untouched.
		if ( ! $options ) {
			return null; }
		global $wpdb;
		$owned      = $question_id ? array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE question_id=%d', $wpdb->prefix . 'ohmylms_question_answers', $question_id ) ) ) : array();
		$owned      = array_flip( $owned );
		$clean      = array();
		$seen       = array();
		$unfiltered = current_user_can( 'unfiltered_html' );
		foreach ( array_values( $options ) as $position => $option ) {
			if ( ! is_array( $option ) ) {
				return self::invalid( __( 'Each answer option must be an object.', 'ohmylms' ) ); }
			$option_id = isset( $option['id'] ) && '' !== $option['id'] && empty( $option['temp'] ) ? $option['id'] : 0;
			if ( $option_id && ( ! is_numeric( $option_id ) || ! isset( $owned[ (int) $option_id ] ) ) ) {
				return new \WP_Error(
					'ohmylms_option_foreign',
					__( 'An answer option does not belong to this question.', 'ohmylms' ),
					array(
						'status'    => 403,
						'option_id' => $option_id,
					)
				);
			}
			$option_id = (int) $option_id;
			if ( $option_id && isset( $seen[ $option_id ] ) ) {
				return self::invalid( __( 'An answer option appears twice.', 'ohmylms' ) ); }
			$seen[ $option_id ] = true;
			$answer             = $option['answer'] ?? '';
			if ( ! is_scalar( $answer ) && null !== $answer ) {
				return self::invalid( __( 'Answer text must be text.', 'ohmylms' ) ); }
			$answer = (string) $answer;
			$row    = array(
				'id'           => $option_id,
				'answer'       => $unfiltered ? $answer : wp_kses_post( $answer ),
				'order_number' => isset( $option['order_number'] ) ? (int) $option['order_number'] : $position + 1,
				'is_correct'   => ! empty( $option['is_correct'] ) && 'false' !== $option['is_correct'] ? 1 : 0,
				'meta'         => array(),
			);
			foreach ( self::OPTION_META as $key => $meta_key ) {
				if ( array_key_exists( $key, $option ) ) {
					$row['meta'][ $meta_key ] = $option[ $key ]; }
			}
			$clean[] = $row;
		}
		return $clean;
	}

	/** Would this payload change the stored question? Used for read-only (shared) reuse.
	 *
	 * @param array $data Input data.
	 * @param mixed $question_id Question identifier.
	 */
	public static function changes_content( array $data, $question_id ) {
		$question = ohmylms_get_question( (int) $question_id );
		if ( ! $question ) {
			return true; }
		$unfiltered = current_user_can( 'unfiltered_html' );
		$text       = static function ( $value ) use ( $unfiltered ) {
			return $unfiltered ? (string) $value : wp_kses_post( (string) $value );
		};
		if ( isset( $data['name'] ) && $text( $data['name'] ) !== (string) $question->get_name() ) {
			return true; }
		if ( isset( $data['description'] ) && $text( $data['description'] ) !== (string) $question->get_description() ) {
			return true; }
		if ( isset( $data['settings'] ) && self::canonical( (array) $data['settings'] ) != self::canonical( $question->get_settings() ) ) {
			return true; }
		if ( ! empty( $data['questions'] ) && is_array( $data['questions'] ) ) {
			$current = array();
			foreach ( $question->get_questions() as $option ) {
				$current[ (int) $option['id'] ] = $option; }
			if ( count( $data['questions'] ) !== count( $current ) ) {
				return true; }
			foreach ( $data['questions'] as $option ) {
				$option_id = (int) ( $option['id'] ?? 0 );
				if ( ! $option_id || ! isset( $current[ $option_id ] ) ) {
					return true; }
				if ( $text( $option['answer'] ?? '' ) !== (string) $current[ $option_id ]['answer'] || (int) ! empty( $option['is_correct'] ) !== (int) ! empty( $current[ $option_id ]['is_correct'] ) ) {
					return true; }
			}
		}
		if ( isset( $data['skills'] ) && SkillMap::normalize( (array) $data['skills'] ) != SkillMap::current( $question_id ) ) {
			return true; }
		return isset( $data['bank'] ) && ! empty( $data['bank'] );
	}

	/**
	 * Canonical for Draft writer.
	 *
	 * @param array $value Value to process.
	 */
	private static function canonical( array $value ) {
		ksort( $value );
		foreach ( $value as $key => $item ) {
			if ( is_array( $item ) ) {
				$value[ $key ] = self::canonical( $item ); } elseif ( is_bool( $item ) ) {
				$value[ $key ] = $item ? '1' : ''; } elseif ( null === $item ) {
					$value[ $key ] = ''; } else {
					$value[ $key ] = (string) $item; }
		}
		return $value;
	}

	/** Apply a validated plan. Throws on any storage failure so the transaction rolls back.
	 *
	 * @param array $plan Plan.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	private static function write( array $plan ) {
		if ( ! empty( $plan['readonly'] ) ) {
			self::link( $plan['quiz_id'], $plan['id'], $plan['order_number'] );
			return array(
				'id'         => $plan['id'],
				'created'    => false,
				'version_id' => 0,
				'readonly'   => true,
			);
		}
		$created  = ! $plan['id'];
		$question = $created ? new Question() : ohmylms_get_question( $plan['id'] );
		if ( ! $question instanceof Question ) {
			throw new \RuntimeException( 'Question unavailable' ); }
		$fields = $plan['fields'];
		if ( $created && ! isset( $fields['name'] ) ) {
			$fields['name'] = __( 'Untitled', 'ohmylms' ); }
		foreach ( $fields as $field => $value ) {
			$question->{"set_$field"}( $value ); }
		$id = (int) $question->save();
		if ( ! $id || get_post_type( $id ) !== OHMYLMS_QUESTION_CPT ) {
			throw new \RuntimeException( 'Question write failed' ); }
		if ( null !== $plan['options'] ) {
			self::write_options( $id, $plan['options'] ); }
		if ( null !== $plan['skills'] ) {
			SkillMap::save( $id, $plan['skills'] ); }
		if ( $plan['quiz_id'] ) {
			self::link( $plan['quiz_id'], $id, $plan['order_number'] ); }
		$version = null;
		if ( \OhMyLMS\Assessment\Schema::ready() ) {
			VersionPublisher::identity( $id );
			if ( null !== $plan['bank'] ) {
				BankAttributes::write( $id, $plan['bank'] ); }
			// Every saved state is an immutable version; unchanged content reuses the latest one.
			$version = VersionPublisher::capture( $id );
		}
		do_action( 'ohmylms_question_draft_saved', $id, $created, $plan );
		return array(
			'id'         => $id,
			'created'    => $created,
			'version_id' => $version ? (int) $version['id'] : 0,
		);
	}

	/** Synchronize a question's option rows; every update is scoped to the owning question.
	 *
	 * @param mixed $question_id Question identifier.
	 * @param array $options Operation options.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	public static function write_options( $question_id, array $options ) {
		global $wpdb;
		$answers  = $wpdb->prefix . 'ohmylms_question_answers';
		$meta     = $wpdb->prefix . 'ohmylms_question_answermeta';
		$existing = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE question_id=%d', $answers, $question_id ) ) );
		$kept     = array();
		foreach ( $options as $option ) {
			$row = array(
				'question_id'  => $question_id,
				'answer'       => $option['answer'],
				'order_number' => $option['order_number'],
				'is_correct'   => $option['is_correct'],
			);
			if ( $option['id'] ) {
				if ( ! in_array( $option['id'], $existing, true ) ) {
					throw new \RuntimeException( 'Foreign answer option' ); }
				if ( $wpdb->update(
					$answers,
					$row,
					array(
						'id'          => $option['id'],
						'question_id' => $question_id,
					),
					array( '%d', '%s', '%d', '%d' ),
					array( '%d', '%d' )
				) === false ) {
					throw new \RuntimeException( 'Option update failed' ); }
				$option_id = $option['id'];
			} else {
				if ( ! $wpdb->insert( $answers, $row, array( '%d', '%s', '%d', '%d' ) ) ) {
					throw new \RuntimeException( 'Option insert failed' ); }
				$option_id = (int) $wpdb->insert_id;
			}
			$kept[] = $option_id;
			foreach ( $option['meta'] as $meta_key => $value ) {
				$stored = maybe_serialize( $value );
				$found  = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE answer_id=%d AND meta_key=%s', $meta, $option_id, $meta_key ) );
				$ok     = $found
					? $wpdb->update( $meta, array( 'meta_value' => $stored ), array( 'id' => (int) $found ), array( '%s' ), array( '%d' ) )
					: $wpdb->insert(
						$meta,
						array(
							'answer_id'  => $option_id,
							'meta_key'   => $meta_key,
							'meta_value' => $stored,
						),
						array( '%d', '%s', '%s' )
					);
				if ( false === $ok ) {
					throw new \RuntimeException( 'Option meta write failed' ); }
			}
		}
		foreach ( array_diff( $existing, $kept ) as $removed ) {
			if ( $wpdb->delete(
				$answers,
				array(
					'id'          => $removed,
					'question_id' => $question_id,
				),
				array( '%d', '%d' )
			) === false ) {
				throw new \RuntimeException( 'Option delete failed' ); }
			$wpdb->delete( $meta, array( 'answer_id' => $removed ), array( '%d' ) );
		}
	}

	/** Place a question in a quiz (or update its position).
	 *
	 * @param mixed $quiz_id Quiz identifier.
	 * @param mixed $question_id Question identifier.
	 * @param mixed $order_number Order number.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	public static function link( $quiz_id, $question_id, $order_number = null ) {
		global $wpdb;
		$table    = $wpdb->prefix . OHMYLMS_QUIZ_QUESTION_RELATIONSHIP;
		$existing = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE quiz_id=%d AND question_id=%d LIMIT 1', $table, $quiz_id, $question_id ) );
		if ( $existing ) {
			if ( null !== $order_number && $wpdb->update( $table, array( 'order_number' => (int) $order_number ), array( 'id' => (int) $existing ), array( '%d' ), array( '%d' ) ) === false ) {
				throw new \RuntimeException( 'Link update failed' ); }
			return;
		}
		if ( null === $order_number ) {
			$order_number = 1 + (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(order_number) FROM %i WHERE quiz_id=%d', $table, $quiz_id ) );
		}
		if ( ! $wpdb->insert(
			$table,
			array(
				'quiz_id'      => $quiz_id,
				'question_id'  => $question_id,
				'order_number' => (int) $order_number,
			),
			array( '%d', '%d', '%d' )
		) ) {
			throw new \RuntimeException( 'Link insert failed' ); }
		do_action( 'ohmylms_quiz_question_relationship_created', $quiz_id, $question_id );
	}

	/**
	 * Payload for Draft writer.
	 *
	 * @param mixed $data Input data.
	 */
	private static function payload( $data ) {
		if ( $data instanceof \WP_REST_Request ) {
			return $data->get_params(); }
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Invalid for Draft writer.
	 *
	 * @param mixed $message Message.
	 */
	private static function invalid( $message ) {
		return new \WP_Error( 'ohmylms_question_invalid', $message, array( 'status' => 400 ) );
	}
}
