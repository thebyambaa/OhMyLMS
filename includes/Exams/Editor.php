<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Typed exam editing for the new admin: paper settings, header, timing and composition.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Exams;

use OhMyLMS\Assessment\AssessmentSettings;
use OhMyLMS\Assessment\DeliveryPolicy;
use OhMyLMS\Assessment\ErrorException;
use OhMyLMS\ExamImport\Jobs;
use OhMyLMS\Learning\NativeQuizEditor;
use OhMyLMS\QuestionBank\AccessPolicy;
use OhMyLMS\QuestionBank\VersionPublisher;
use OhMyLMS\Utility\Transaction;

defined( 'ABSPATH' ) || exit;

/**
 * The backing quiz stays the source of truth for questions, time limit and sections; the exam record
 * keeps its header and instructions. Every write checks a revision token of both, under the exam lock.
 */
final class Editor {

	const HEADER_FIELDS    = array( 'board', 'qualification', 'subject_code', 'paper', 'variant', 'session', 'calculator' );
	const MAX_TITLE        = 190;
	const MAX_HEADER       = 190;
	const MAX_INSTRUCTIONS = 4000;
	const MAX_QUESTIONS    = 200;
	const MAX_SECTIONS     = 50;

	/**
	 * Exam meta, or null for another post type.
	 *
	 * @param int $id Exam ID.
	 * @return array|null
	 */
	public static function data( $id ) {
		if ( Library::TYPE !== get_post_type( $id ) ) {
			return null;
		}
		$data = get_post_meta( (int) $id, Library::META, true );
		return is_array( $data ) && ! empty( $data['quiz_id'] ) ? $data : null;
	}

	/**
	 * Ordered question IDs of the backing quiz, read straight from the placement table.
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return int[]
	 */
	public static function placements( $quiz_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Uncached read of the authoritative placement order for the revision token.
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT question_id FROM %i WHERE quiz_id=%d ORDER BY order_number ASC, id ASC', $wpdb->prefix . 'ohmylms_quiz_questions_relationship', (int) $quiz_id ) );
		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Token of everything an edit can change. It also changes when the Gutenberg editor or a legacy
	 * route edits the exam, so a stale client always gets 409.
	 *
	 * @param int $id Exam ID.
	 * @return string 40 hex characters.
	 */
	public static function revision( $id ) {
		clean_post_cache( (int) $id );
		$data    = (array) self::data( $id );
		$quiz_id = (int) ( $data['quiz_id'] ?? 0 );
		if ( $quiz_id ) {
			clean_post_cache( $quiz_id );
		}
		return sha1(
			(string) wp_json_encode(
				array(
					get_post_field( 'post_modified_gmt', $id ),
					get_post_status( $id ),
					get_post_field( 'post_title', $id ),
					$data,
					get_post_field( 'post_modified_gmt', $quiz_id ),
					get_post_field( 'post_title', $quiz_id ),
					get_post_meta( $quiz_id, '_quiz_settings', true ),
					AssessmentSettings::get( $quiz_id ),
					self::placements( $quiz_id ),
				)
			)
		);
	}

	/**
	 * List row: what the exam is, without composition or problems.
	 *
	 * @param int $id Exam ID.
	 * @return array
	 */
	public static function summary( $id ) {
		$data     = (array) self::data( $id );
		$quiz_id  = (int) $data['quiz_id'];
		$composed = ! empty( $data['composed'] );
		$ids      = self::placements( $quiz_id );
		$status   = 'publish' === get_post_status( $id ) ? 'publish' : 'draft';
		$live     = $composed && 'draft' === $status;
		$snapshot = $live ? Composer::snapshot( $id ) : null;
		return array(
			'id'               => (int) $id,
			'title'            => $live ? (string) $snapshot['title'] : (string) get_the_title( $id ),
			'status'           => $status,
			'origin'           => $composed ? 'composed' : 'imported',
			'job_id'           => (int) ( $data['job_id'] ?? 0 ),
			'quiz_id'          => $quiz_id,
			'duration_minutes' => $live ? (int) $snapshot['duration_minutes'] : (int) ( $data['duration_minutes'] ?? 0 ),
			'total_marks'      => (float) ( $live ? $snapshot['total_marks'] : ( $data['total_marks'] ?? 0 ) ),
			'question_count'   => count( $ids ),
			'released'         => (bool) AssessmentSettings::get( $quiz_id )['released'],
			'updated_at'       => self::time( get_post_field( 'post_modified_gmt', $id ) ),
			'revision'         => self::revision( $id ),
		);
	}

	/**
	 * Editor view: settings, composition, problems and the links the old screens offered.
	 *
	 * @param int $id Exam ID.
	 * @return array
	 */
	public static function read( $id ) {
		$data       = (array) self::data( $id );
		$quiz_id    = (int) $data['quiz_id'];
		$summary    = self::summary( $id );
		$assessment = AssessmentSettings::get( $quiz_id );
		$settings   = (array) get_post_meta( $quiz_id, '_quiz_settings', true );
		$header     = array();
		foreach ( self::HEADER_FIELDS as $field ) {
			$header[ $field ] = (string) ( $data[ $field ] ?? '' );
		}
		$draft = 'draft' === $summary['status'];
		return $summary + array(
			'header'       => $header,
			'instructions' => (string) ( $data['instructions'] ?? '' ),
			'timing'       => array(
				'duration_minutes' => $summary['duration_minutes'],
				'available_from'   => self::time( $assessment['available_from'] ),
				'available_until'  => self::time( $assessment['available_until'] ),
				'grace_seconds'    => (int) $assessment['grace_seconds'],
				'feedback_release' => (string) $assessment['feedback_release'],
			),
			'paper'        => PaperSettings::sanitize( $settings['paper'] ?? array() ),
			'composition'  => self::composition( $quiz_id, $data ),
			'problems'     => $draft && ! empty( $data['composed'] ) ? Composer::problems( $id ) : array(),
			'warnings'     => $draft && ! empty( $data['composed'] ) ? Composer::warnings( $id ) : array(),
			'links'        => array(
				'player'  => Player::url( $id ),
				'preview' => (string) \OhMyLMS\Assessment\PreviewPlayer::url( $quiz_id ),
				'editor'  => ! empty( $data['composed'] ) ? (string) get_edit_post_link( $quiz_id, 'raw' ) : '',
				'paper'   => AccessPolicy::can_edit_quiz( $quiz_id ) ? PrintPaper::url( $quiz_id, 'paper' ) : '',
				'key'     => AccessPolicy::can_edit_quiz( $quiz_id ) ? PrintPaper::url( $quiz_id, 'key' ) : '',
			),
			'permissions'  => array(
				'can_edit'    => $draft,
				'can_compose' => $draft && ! empty( $data['composed'] ),
				'can_publish' => $draft && current_user_can( 'publish_posts' ),
			),
		);
	}

	/**
	 * Placements as an ordered list of section markers and questions. Questions before the first
	 * marker are not in a section.
	 *
	 * @param int   $quiz_id Quiz ID.
	 * @param array $data    Exam meta.
	 * @return array[]
	 */
	private static function composition( $quiz_id, array $data ) {
		$sections = AssessmentSettings::get( $quiz_id )['sections'];
		$owner    = array();
		foreach ( $sections as $index => $section ) {
			foreach ( (array) $section['questions'] as $question_id ) {
				$owner[ (int) $question_id ] = $index;
			}
		}
		$links = array();
		foreach ( ohmylms_get_quiz( $quiz_id )->get_questions() as $link ) {
			$links[ (int) $link['id'] ] = $link;
		}
		$pins    = (array) ( $data['version_ids'] ?? array() );
		$items   = array();
		$emitted = array();
		$current = null;
		foreach ( self::placements( $quiz_id ) as $question_id ) {
			$section = $owner[ $question_id ] ?? null;
			if ( null !== $section && $section !== $current && ! isset( $emitted[ $section ] ) ) {
				$items[]             = self::section_item( $sections[ $section ] );
				$emitted[ $section ] = true;
				$current             = $section;
			}
			$link     = $links[ $question_id ] ?? array( 'settings' => array() );
			$identity = VersionPublisher::identity( $question_id, false );
			$score    = $link['settings']['score'] ?? array();
			$items[]  = array(
				'kind'        => 'question',
				'question_id' => $question_id,
				'name'        => (string) get_the_title( $question_id ),
				'type'        => (string) ( $link['settings']['type'] ?? '' ),
				'marks'       => ! empty( $score['enabled'] ) ? max( 0, (float) ( $score['value'] ?? 0 ) ) : 0,
				'approved'    => ! empty( $identity['approved_version_id'] ) && (int) ( $pins[ $question_id ] ?? $identity['current_version_id'] ) === (int) $identity['approved_version_id'],
				'can_edit'    => AccessPolicy::can_edit_question( $question_id ),
			);
		}
		foreach ( $sections as $index => $section ) {
			if ( ! isset( $emitted[ $index ] ) ) {
				$items[] = self::section_item( $section );
			}
		}
		return $items;
	}

	/**
	 * One section marker.
	 *
	 * @param array $section Stored section.
	 * @return array
	 */
	private static function section_item( array $section ) {
		return array(
			'kind'     => 'section',
			'title'    => (string) $section['title'],
			'new_page' => ! empty( $section['new_page'] ),
		);
	}

	/**
	 * Run a write under the exam lock and in one transaction, after the revision and draft checks.
	 *
	 * @param int      $id       Exam ID.
	 * @param string   $revision Revision the client started from.
	 * @param callable $work     Receives the exam meta; returns true or a WP_Error.
	 * @return array|\WP_Error Editor view.
	 */
	private static function write( $id, $revision, callable $work ) {
		return Jobs::locked(
			$id,
			static function () use ( $id, $revision, $work ) {
				$current = self::revision( $id );
				if ( ! is_string( $revision ) || ! hash_equals( $current, $revision ) ) {
					return new \WP_Error(
						'ohmylms_exam_conflict',
						__( 'This exam was changed somewhere else. Reload it before saving.', 'ohmylms' ),
						array(
							'status'   => 409,
							'revision' => $current,
						)
					);
				}
				if ( 'publish' === get_post_status( $id ) ) {
					return self::error( 'published', __( 'This exam is published and can no longer be edited.', 'ohmylms' ), 409 );
				}
				try {
					Transaction::run(
						static function () use ( $id, $work ) {
							ErrorException::raise( $work( (array) self::data( $id ) ) );
							// Touch the exam so its modified time follows edits stored on the quiz.
							ErrorException::raise( wp_update_post( array( 'ID' => $id ), true ) );
						}
					);
				} catch ( ErrorException $error ) {
					return $error->error;
				} catch ( \Throwable $error ) {
					return self::error( 'storage', __( 'The exam could not be saved. Nothing was changed.', 'ohmylms' ), 500 );
				}
				return self::read( $id );
			}
		);
	}

	/**
	 * Save settings. Only the keys present in `$input` change.
	 *
	 * @param int    $id       Exam ID.
	 * @param array  $input    title, duration_minutes, instructions, header, timing, paper.
	 * @param string $revision Revision the edit started from.
	 * @return array|\WP_Error Editor view.
	 */
	public static function update( $id, array $input, $revision ) {
		if ( isset( $input['title'] ) ) {
			$title = sanitize_text_field( (string) $input['title'] );
			if ( '' === trim( $title ) || mb_strlen( $title ) > self::MAX_TITLE ) {
				return self::field_error( 'title', __( 'Enter a title of 1 to 190 characters.', 'ohmylms' ) );
			}
			$input['title'] = $title;
		}
		$minutes = $input['duration_minutes'] ?? ( $input['timing']['duration_minutes'] ?? null );
		if ( null !== $minutes && ( ! is_int( $minutes ) || $minutes < 1 || $minutes > 1440 ) ) {
			return self::field_error( 'duration_minutes', __( 'Enter a duration from 1 to 1440 minutes.', 'ohmylms' ) );
		}
		return self::write(
			$id,
			$revision,
			static function ( array $data ) use ( $id, $input, $minutes ) {
				$quiz_id = (int) $data['quiz_id'];
				if ( isset( $input['title'] ) ) {
					foreach ( array( $id, $quiz_id, (int) ( $data['course_id'] ?? 0 ) ) as $post_id ) {
						if ( $post_id ) {
							$saved = wp_update_post(
								array(
									'ID'         => $post_id,
									'post_title' => $input['title'],
								),
								true
							);
							if ( is_wp_error( $saved ) ) {
								return $saved;
							}
						}
					}
				}
				$settings = (array) get_post_meta( $quiz_id, '_quiz_settings', true );
				if ( null !== $minutes ) {
					$settings['time_limit']   = array(
						'enabled' => true,
						'value'   => $minutes,
						'type'    => 'minutes',
					);
					$data['duration_minutes'] = $minutes;
				}
				if ( isset( $input['paper'] ) ) {
					$settings['paper'] = PaperSettings::sanitize( array_merge( PaperSettings::sanitize( $settings['paper'] ?? array() ), (array) $input['paper'] ) );
				}
				if ( null !== $minutes || isset( $input['paper'] ) ) {
					update_post_meta( $quiz_id, '_quiz_settings', $settings );
				}
				foreach ( (array) ( $input['header'] ?? array() ) as $field => $value ) {
					if ( in_array( $field, self::HEADER_FIELDS, true ) ) {
						$data[ $field ] = mb_substr( sanitize_text_field( (string) $value ), 0, self::MAX_HEADER );
					}
				}
				if ( isset( $input['instructions'] ) ) {
					$data['instructions'] = mb_substr( sanitize_textarea_field( (string) $input['instructions'] ), 0, self::MAX_INSTRUCTIONS );
				}
				update_post_meta( $id, Library::META, wp_slash( $data ) );
				$timing = array_intersect_key( (array) ( $input['timing'] ?? array() ), array_flip( array( 'available_from', 'available_until', 'grace_seconds', 'feedback_release' ) ) );
				if ( $timing ) {
					$saved = AssessmentSettings::save( $quiz_id, $timing );
					if ( is_wp_error( $saved ) ) {
						return new \WP_Error( 'ohmylms_exam_invalid_timing', $saved->get_error_message(), array( 'status' => 422 ) );
					}
				}
				return true;
			}
		);
	}

	/**
	 * Replace the composition: question placements in order, and sections around them.
	 *
	 * @param int    $id       Exam ID.
	 * @param array  $items    Ordered `{kind: section, title, new_page}` and `{kind: question, question_id}`.
	 * @param string $revision Revision the edit started from.
	 * @return array|\WP_Error Editor view.
	 */
	public static function compose( $id, array $items, $revision ) {
		$ids      = array();
		$sections = array();
		$open     = null;
		foreach ( array_values( $items ) as $index => $item ) {
			$kind = is_array( $item ) ? ( $item['kind'] ?? '' ) : '';
			if ( 'section' === $kind ) {
				$title = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
				if ( '' === trim( $title ) || mb_strlen( $title ) > self::MAX_TITLE ) {
					return self::field_error( "items[$index].title", __( 'Give every section a title of 1 to 190 characters.', 'ohmylms' ) );
				}
				$sections[] = array(
					'title'     => $title,
					'new_page'  => ! empty( $item['new_page'] ),
					'questions' => array(),
				);
				$open       = count( $sections ) - 1;
				continue;
			}
			$question_id = (int) ( $item['question_id'] ?? 0 );
			if ( 'question' !== $kind || $question_id < 1 ) {
				return self::field_error( "items[$index]", __( 'Each item must be a section or a question.', 'ohmylms' ) );
			}
			if ( in_array( $question_id, $ids, true ) ) {
				return self::field_error( "items[$index].question_id", __( 'A question can appear only once in an exam.', 'ohmylms' ) );
			}
			$ids[] = $question_id;
			if ( null !== $open ) {
				$sections[ $open ]['questions'][] = $question_id;
			}
		}
		if ( count( $ids ) > self::MAX_QUESTIONS || count( $sections ) > self::MAX_SECTIONS ) {
			return self::field_error( 'items', __( 'An exam holds at most 200 questions and 50 sections.', 'ohmylms' ) );
		}
		return self::write(
			$id,
			$revision,
			static function ( array $data ) use ( $ids, $sections ) {
				$quiz_id  = (int) $data['quiz_id'];
				$existing = self::placements( $quiz_id );
				if ( empty( $data['composed'] ) && $ids !== $existing ) {
					return self::error( 'questions_locked', __( 'The questions of an imported exam come from its reviewed paper. Only its sections can change.', 'ohmylms' ), 409 );
				}
				foreach ( array_diff( $ids, $existing ) as $question_id ) {
					if ( ! DeliveryPolicy::allows( $question_id, DeliveryPolicy::QUIZ ) || ! AccessPolicy::can_use_question( $question_id ) ) {
						/* translators: %d: question ID. */
						return self::error( 'question_unavailable', sprintf( __( 'Question %d is unavailable to you.', 'ohmylms' ), $question_id ), 403 );
					}
					$identity = VersionPublisher::identity( $question_id, false );
					if ( ! AccessPolicy::can_edit_question( $question_id ) && empty( $identity['approved_version_id'] ) ) {
						/* translators: %d: question ID. */
						return self::error( 'question_unapproved', sprintf( __( 'Question %d is shared but not approved yet, so it cannot be used.', 'ohmylms' ), $question_id ), 409 );
					}
				}
				if ( $ids !== $existing ) {
					NativeQuizEditor::sync( $quiz_id, $ids );
				}
				$marks = array();
				foreach ( AssessmentSettings::get( $quiz_id )['sections'] as $section ) {
					$marks += array_map( 'floatval', (array) ( $section['marks'] ?? array() ) );
				}
				foreach ( $sections as &$section ) {
					$section['marks'] = array_intersect_key( $marks, array_flip( $section['questions'] ) );
				}
				unset( $section );
				$saved = AssessmentSettings::save( $quiz_id, array( 'sections' => $sections ) );
				return is_wp_error( $saved ) ? new \WP_Error( 'ohmylms_exam_invalid_sections', $saved->get_error_message(), array( 'status' => 422 ) ) : true;
			}
		);
	}

	/**
	 * RFC 3339 UTC time, or an empty string.
	 *
	 * @param string $mysql UTC MySQL date time.
	 * @return string
	 */
	private static function time( $mysql ) {
		$mysql = (string) $mysql;
		return '' === $mysql || '0000-00-00 00:00:00' === $mysql ? '' : gmdate( 'Y-m-d\TH:i:s\Z', (int) strtotime( $mysql . ' UTC' ) );
	}

	/**
	 * Validation error about one field.
	 *
	 * @param string $field   Field path.
	 * @param string $message Message.
	 * @return \WP_Error 422.
	 */
	private static function field_error( $field, $message ) {
		return new \WP_Error(
			'ohmylms_exam_invalid_field',
			$message,
			array(
				'status' => 422,
				'field'  => $field,
			)
		);
	}

	/**
	 * Domain error.
	 *
	 * @param string $code    Code suffix.
	 * @param string $message Message.
	 * @param int    $status  HTTP status.
	 * @return \WP_Error
	 */
	private static function error( $code, $message, $status = 400 ) {
		return new \WP_Error( 'ohmylms_exam_' . $code, $message, array( 'status' => $status ) );
	}
}
