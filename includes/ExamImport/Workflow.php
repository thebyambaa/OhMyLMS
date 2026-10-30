<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Inventory, extraction checkpoints, teacher review and explicit draft commits.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\ExamImport;

use OhMyLMS\QuestionBank\BulkImport;
use OhMyLMS\Utility\Transaction;
use OhMyLMS\Assessment\ErrorException;

defined( 'ABSPATH' ) || exit;

/** Each scheduler delivery runs one checkpoint, with stale-delivery protection. */
final class Workflow {
	const HOOK         = 'ohmylms_extract_exam_import';
	const MAX_REQUESTS = 125;

	/** Register stages and author-only review operations. */
	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'process' ), 10, 3 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/** Register discrete extraction, review and draft commit actions. */
	public static function routes() {
		register_rest_route(
			'ohmylms/v1',
			'/exam-import-skills',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( Jobs::class, 'permission' ),
				'callback'            => array( __CLASS__, 'skills' ),
			)
		);
		register_rest_route(
			'ohmylms/v1',
			'/exam-imports/rows',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( Jobs::class, 'permission' ),
				'callback'            => array( __CLASS__, 'rows' ),
			)
		);
		foreach ( array( 'extract', 'review', 'commit', 'quiz' ) as $operation ) {
			register_rest_route(
				'ohmylms/v1',
				'/exam-imports/(?P<id>\d+)/' . $operation,
				array(
					'methods'             => 'POST',
					'permission_callback' => array( Jobs::class, 'permission' ),
					'callback'            => array( __CLASS__, $operation ),
				)
			);
		}
	}

	/**
	 * Search the existing skill catalogue without leaving an unsaved PDF review.
	 *
	 * @param \WP_REST_Request $request Author search.
	 * @return array|\WP_Error Existing skill suggestions.
	 */
	public static function skills( $request ) {
		if ( ! Jobs::permission( $request ) || ! is_string( $request['search'] ) ) {
			return self::denied();
		}
		return \OhMyLMS\QuestionBank\SkillCatalogue::search( mb_substr( sanitize_text_field( $request['search'] ), 0, 1000 ), max( 1, (int) $request['page'] ), 10 );
	}

	/**
	 * Projection for the owner review page, without source storage handles.
	 *
	 * @param array $job Private state.
	 * @return array Review data.
	 */
	public static function detail( array $job ) {
		return array_intersect_key( $job, array_flip( array( 'inventory', 'draft_inventory', 'draft', 'draft_revision', 'suggestions', 'cursor', 'usage', 'question_ids', 'version_ids', 'exam_id', 'quiz_id' ) ) ) + array(
			'has_mark_scheme' => ! empty( $job['mark_scheme'] ),
			'draft_revision'  => 0,
			'draft'           => array(),
			'rejected'        => Review::rejected( $job ),
		);
	}

	/**
	 * Persist the checkpoint before scheduling another delivery.
	 *
	 * @param int   $id Job ID.
	 * @param array $job State.
	 * @throws \RuntimeException When checkpoint storage fails.
	 */
	private static function save( $id, array $job ) {
		$job['updated_at'] = gmdate( 'c' );
		if ( false === update_post_meta( $id, Jobs::META, wp_slash( $job ) ) && get_post_meta( $id, Jobs::META, true ) !== $job ) {
			throw new \RuntimeException( 'Import checkpoint could not be stored.' );
		}
	}

	/**
	 * Schedule precisely one stage token. Persisted cursor rejects stale actions.
	 *
	 * @param int   $id Job ID.
	 * @param array $job State.
	 * @return bool Queued.
	 */
	private static function schedule( $id, array $job ) {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return false;
		}
		$args = array( (int) $id, $job['generation'], $job['cursor'] );
		if ( as_has_scheduled_action( self::HOOK, $args, Jobs::GROUP ) ) {
			return true;
		}
		// Scheduler uniqueness is hook/group-wide, so it blocks the next cursor while
		// this stage is running. The job lock and exact token check serialize inserts.
		return (bool) as_schedule_single_action( time() + 1, self::HOOK, $args, Jobs::GROUP, false );
	}

	/**
	 * Explicitly send the uploaded documents to the configured AI provider.
	 *
	 * @param \WP_REST_Request $request Owner request.
	 * @return array|\WP_Error Job status.
	 */
	public static function extract( $request ) {
		if ( ! Jobs::permission( $request ) ) {
			return self::denied();
		}
		return Jobs::locked(
			$request['id'],
			static function () use ( $request ) {
				$id = (int) $request['id'];
				clean_post_cache( $id );
				$job = get_post_meta( $id, Jobs::META, true );
				if ( ! in_array( $job['state'], array( 'ready_for_extraction', 'ready_for_review', 'extraction_failed', 'extracting' ), true ) || ! empty( $job['question_ids'] ) || ( $job['usage']['requests'] ?? 0 ) >= self::MAX_REQUESTS ) {
					return new \WP_Error( 'exam_state', __( 'This import cannot start extraction.', 'ohmylms' ), array( 'status' => 409 ) );
				}
				if ( ! \OhMyLMS\AI\Settings::configured( 'exam_converter' ) ) {
					return new \WP_Error( 'exam_provider', __( 'Enable the PDF exam converter and configure its connection in Settings → AI tools.', 'ohmylms' ), array( 'status' => 503 ) );
				}
				if ( in_array( $job['state'], array( 'ready_for_extraction', 'ready_for_review' ), true ) ) {
					unset( $job['inventory'] );
					$job['generation']     = wp_generate_uuid4();
					$job['cursor']         = -1;
					$job['stage_attempts'] = 0;
					$job['suggestions']    = array();
				}
				if ( $job['stage_attempts'] >= 3 ) {
					return new \WP_Error( 'exam_retry', __( 'This stage exhausted its three attempts. Start a new import.', 'ohmylms' ), array( 'status' => 409 ) );
				}
				$job['state'] = 'extracting';
				$job['error'] = '';
				unset( $job['validation_error'] );
				self::save( $id, $job );
				if ( ! self::schedule( $id, $job ) ) {
					$job['state'] = 'extraction_failed';
					$job['error'] = 'scheduler';
					self::save( $id, $job );
				}
				return Jobs::status( $request );
			}
		);
	}

	/**
	 * One inventory or five-question extraction request, with a durable usage budget.
	 *
	 * @param int    $id Job ID.
	 * @param string $generation Extraction run token.
	 * @param int    $cursor Expected checkpoint.
	 * @return mixed Processing outcome.
	 */
	public static function process( $id, $generation, $cursor ) {
		return Jobs::locked(
			$id,
			static function () use ( $id, $generation, $cursor ) {
				clean_post_cache( $id );
				$job  = get_post_meta( $id, Jobs::META, true );
				$post = get_post( $id );
				if ( ! $post || Jobs::TYPE !== $post->post_type || ! is_array( $job ) || 'extracting' !== $job['state'] || $generation !== $job['generation'] || (int) $cursor !== (int) $job['cursor'] ) {
					return;
				}
				if ( ! user_can( $post->post_author, 'edit_posts' ) || ! user_can( $post->post_author, 'upload_files' ) || $job['stage_attempts'] >= 3 || ( $job['usage']['requests'] ?? 0 ) >= self::MAX_REQUESTS ) {
					$job['state'] = 'extraction_failed';
					$job['error'] = 'permission_or_budget';
					self::save( $id, $job );
					return;
				}
				if ( ! \OhMyLMS\AI\Settings::configured( 'exam_converter' ) ) {
					$job['state'] = 'extraction_failed';
					$job['error'] = 'exam_provider';
					self::save( $id, $job );
					return;
				}
				++$job['stage_attempts'];
				$job['usage']['requests'] = 1 + ( $job['usage']['requests'] ?? 0 );
				self::save( $id, $job );
				$reply = Extraction::complete( $job );
				if ( ! is_wp_error( $reply ) ) {
					foreach ( array( 'input_tokens', 'output_tokens' ) as $field ) {
						$job['usage'][ $field ] = ( $job['usage'][ $field ] ?? 0 ) + $reply[ $field ];
					}
					$valid = -1 === (int) $cursor ? Extraction::inventory( $reply['data'] ) : Extraction::questions( $reply['data']['questions'] ?? null, array_slice( $job['inventory']['question_numbers'], $cursor, Extraction::BATCH ), $job['inventory']['page_count'], true );
				} else {
					$valid = $reply;
				}
				if ( is_wp_error( $valid ) ) {
					$job['state'] = 'extraction_failed';
					$job['error'] = $valid->get_error_code();
					if ( in_array( $valid->get_error_code(), array( 'exam_parts', 'exam_questions', 'exam_inventory' ), true ) ) {
						$job['validation_error'] = mb_substr( sanitize_text_field( $valid->get_error_message() ), 0, 300 );
					}
					self::save( $id, $job );
					return;
				}
				if ( -1 === (int) $cursor ) {
					$job['inventory'] = $valid;
					$job['cursor']    = 0;
				} else {
					// AI output cannot mark its own work as reviewed.
					foreach ( $valid as &$question ) {
						$question['reviewed']         = false;
						$question['figures_complete'] = false;
						foreach ( $question['figures'] as &$figure ) {
							$figure['approved'] = false;
							$figure['selected'] = 'original';
							unset( $figure['spec'] );
							$asset              = Figures::enqueue_crop( $id, $figure['page'], $figure['bounds'], (int) $post->post_author );
							$figure['asset_id'] = is_wp_error( $asset ) ? '' : $asset['id'];
						}
						unset( $figure );
					}
					unset( $question );
					$job['suggestions'] = array_merge( $job['suggestions'], $valid );
					$job['cursor']     += count( $valid );
				}
				$job['stage_attempts'] = 0;
				if ( count( $job['inventory']['question_numbers'] ) === $job['cursor'] ) {
					$job['state'] = 'ready_for_review';
					if ( ! isset( $job['draft'] ) ) {
						$job['draft']           = $job['suggestions'];
						$job['draft_inventory'] = $job['inventory'];
						$job['draft_revision']  = 1;
					}
				}
				self::save( $id, $job );
				if ( 'extracting' === $job['state'] && ! self::schedule( $id, $job ) ) {
					$job['state'] = 'extraction_failed';
					$job['error'] = 'scheduler';
					self::save( $id, $job );
				}
			}
		);
	}

	/**
	 * Save reviewed question edits with optimistic concurrency; re-extraction preserves them.
	 *
	 * @param \WP_REST_Request $request Edited questions and revision.
	 * @return array|\WP_Error Status.
	 */
	public static function review( $request ) {
		if ( ! Jobs::permission( $request ) ) {
			return self::denied();
		}
		return Jobs::locked(
			$request['id'],
			static function () use ( $request ) {
				$id = (int) $request['id'];
				clean_post_cache( $id );
				$job = get_post_meta( $id, Jobs::META, true );
				if ( 'ready_for_review' !== $job['state'] || (int) ( $job['draft_revision'] ?? 0 ) !== (int) $request['revision'] ) {
					return new \WP_Error( 'exam_conflict', __( 'The review changed. Reload before saving.', 'ohmylms' ), array( 'status' => 409 ) );
				}
				$inventory = $job['draft_inventory'] ?? $job['inventory'];
				$rows      = Extraction::questions( $request['questions'], $inventory['question_numbers'], $inventory['page_count'] );
				if ( is_wp_error( $rows ) ) {
					return $rows;
				}
				$job['draft'] = $rows;
				++$job['draft_revision'];
				self::save( $id, $job );
				return Jobs::status( $request );
			}
		);
	}

	/**
	 * Explicitly create question-bank drafts without approving or publishing an exam.
	 *
	 * @param \WP_REST_Request $request Reviewed import.
	 * @return array|\WP_Error Job status and question IDs.
	 */
	public static function commit( $request ) {
		if ( ! Jobs::permission( $request ) ) {
			return self::denied();
		}
		return Jobs::locked(
			$request['id'],
			static function () use ( $request ) {
				$id = (int) $request['id'];
				clean_post_cache( $id );
				$job = get_post_meta( $id, Jobs::META, true );
				if ( ! empty( $job['question_ids'] ) ) {
					return Jobs::status( $request );
				}
				// Rows a teacher rejected in the review screen are left out of the commit.
				$rejected = Review::rejected( $job );
				$kept     = array_values(
					array_filter(
						(array) ( $job['draft'] ?? array() ),
						static function ( $row ) use ( $rejected ) {
							return ! in_array( (string) $row['number'], $rejected, true ); }
					)
				);
				if ( 'ready_for_review' !== $job['state'] || (int) $request['revision'] !== (int) $job['draft_revision'] || ! $kept || count(
					array_filter(
						$kept,
						static function ( $row ) {
							return ! empty( $row['reviewed'] ); }
					)
				) !== count( $kept ) ) {
					return new \WP_Error( 'exam_review', __( 'Save and confirm review of every question first.', 'ohmylms' ), array( 'status' => 409 ) );
				}
				$rows = array();
				foreach ( $kept as $row ) {
					$figure_settings = array(
						'figures'       => $row['figures'] ?? array(),
						'figure_review' => array(
							'complete' => ! empty( $row['figures_complete'] ),
							'required' => ! empty( $row['requires_source'] ),
						),
						'parts'         => $row['parts'],
					);
					$figure_settings = FigureContent::seal( $figure_settings );
					if ( is_wp_error( $figure_settings ) ) {
						return $figure_settings;
					}
					foreach ( $figure_settings['figures'] as $figure ) {
						$asset = Figures::asset( $figure['asset_id'] );
						if ( ! $asset || get_current_user_id() !== (int) $asset['owner'] ) {
							return self::denied();
						}
					}
					unset( $figure_settings['parts'] );
					foreach ( $row['parts'] as &$part ) {
						if ( '' === trim( $part['skills'] ?? '' ) ) {
							unset( $part['skills'] );
						}
					}
					unset( $part );
					$rows[] = array(
						'exam'     => 'PDF author ' . get_current_user_id(),
						'title'    => ( $job['draft_inventory']['title'] ?? $job['inventory']['title'] ) . ' · Q' . $row['number'],
						'paper'    => $job['document']['sha256'],
						'number'   => $row['number'],
						'type'     => 'structured',
						'question' => $row['question'],
						'skills'   => '' !== trim( (string) $row['skills'] ) ? $row['skills'] : (string) ( $job['skills'] ?? '' ),
						'parts'    => $row['parts'],
						'settings' => $figure_settings,
					);
				}
				$dry = BulkImport::run( $rows );
				if ( is_wp_error( $dry ) || $dry['counts']['error'] ) {
					return is_wp_error( $dry ) ? $dry : new \WP_Error(
						'exam_questions',
						__( 'Fix the question skills and answer settings before committing.', 'ohmylms' ),
						array(
							'status' => 400,
							'rows'   => $dry['rows'],
						)
					);
				}
				try {
					Transaction::run(
						static function () use ( &$job, $rows, $id ) {
							$saved               = ErrorException::raise(
								BulkImport::run(
									$rows,
									array(
										'dry_run' => false,
										'review'  => true,
									)
								)
							);
							$job['question_ids'] = array_map( 'intval', array_column( $saved['rows'], 'id' ) );
							$job['version_ids']  = array();
							foreach ( $job['question_ids'] as $question_id ) {
								if ( 'exam' === Jobs::target( $job['target'] ?? '' ) ) {
									\OhMyLMS\QuestionBank\BankAttributes::write( $question_id, array( 'secure' => 1 ) );
								}
								$job['version_ids'][ $question_id ] = (int) \OhMyLMS\QuestionBank\VersionPublisher::identity( $question_id )['current_version_id'];
							}
							if ( count( $job['question_ids'] ) !== count( $rows ) || in_array( 0, $job['question_ids'], true ) ) {
								throw new \RuntimeException( 'Incomplete import commit.' );
							}
							$job['state'] = 'committed';
							self::save( $id, $job );
						}
					);
				} catch ( \Throwable $error ) {
					return new \WP_Error( 'exam_commit', __( 'Drafts could not be committed. Retry after reloading.', 'ohmylms' ), array( 'status' => 500 ) );
				}
				return Jobs::status( $request );
			}
		);
	}

	/**
	 * Link committed question drafts into a new draft quiz, in paper order.
	 *
	 * @param \WP_REST_Request $request Job ID and quiz title.
	 * @return array|\WP_Error Job status, with the new quiz ID.
	 */
	public static function quiz( $request ) {
		if ( ! Jobs::permission( $request ) ) {
			return self::denied();
		}
		return Jobs::locked(
			$request['id'],
			static function () use ( $request ) {
				global $wpdb;
				$id = (int) $request['id'];
				clean_post_cache( $id );
				$job = get_post_meta( $id, Jobs::META, true );
				if ( ! empty( $job['quiz_id'] ) && get_post( $job['quiz_id'] ) ) {
					return Jobs::status( $request );
				}
				if ( ! is_array( $job ) || 'quiz' !== Jobs::target( $job['target'] ?? '' ) || 'committed' !== $job['state'] || empty( $job['question_ids'] ) ) {
					return new \WP_Error( 'exam_review', __( 'Commit all reviewed question drafts first.', 'ohmylms' ), array( 'status' => 409 ) );
				}
				$title = is_string( $request['title'] ) ? sanitize_text_field( $request['title'] ) : '';
				if ( '' === trim( $title ) ) {
					return new \WP_Error( 'exam_header', __( 'Provide a quiz title.', 'ohmylms' ), array( 'status' => 400 ) );
				}
				try {
					Transaction::run(
						static function () use ( $wpdb, &$job, $id, $title ) {
							$route = new \WP_REST_Request( 'POST', '/ohmylms/v1/quiz' );
							$route->set_body_params(
								array(
									'name'   => $title,
									'status' => 'draft',
								)
							);
							$response = rest_do_request( $route );
							$quiz     = $response->get_status() < 300 ? (int) ( $response->get_data()['id'] ?? 0 ) : 0;
							if ( ! $quiz ) {
								throw new \RuntimeException( 'Quiz creation failed.' );
							}
							foreach ( $job['question_ids'] as $index => $question_id ) {
								// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Atomic ordered links to existing question identities.
								if ( ! $wpdb->insert(
									$wpdb->prefix . 'ohmylms_quiz_questions_relationship',
									array(
										'quiz_id'      => $quiz,
										'question_id'  => $question_id,
										'order_number' => $index,
									)
								) ) {
									throw new \RuntimeException( 'Quiz question placement failed.' );
								}
							}
							update_post_meta( $quiz, '_ohmylms_version_pins', $job['version_ids'] );
							$job['quiz_id'] = $quiz;
							self::save( $id, $job );
						}
					);
				} catch ( \Throwable $error ) {
					return new \WP_Error( 'exam_quiz', __( 'The quiz could not be created. Retry after reloading.', 'ohmylms' ), array( 'status' => 500 ) );
				}
				return Jobs::status( $request );
			}
		);
	}

	/**
	 * Turn question rows (CSV, JSON or pasted text) into a committed import job, so an exam or quiz is
	 * made with the same details form and creation step as a reviewed PDF.
	 *
	 * @param \WP_REST_Request $request Rows or text, format, target and source name.
	 * @return \WP_REST_Response|\WP_Error Job status.
	 */
	public static function rows( $request ) {
		if ( ! Jobs::permission( $request ) ) {
			return self::denied();
		}
		$input = $request['rows'];
		if ( null === $input ) {
			$input = is_string( $request['text'] ) ? $request['text'] : '';
		}
		$rows = BulkImport::parse( $input, is_string( $request['format'] ) ? sanitize_key( $request['format'] ) : 'auto' );
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		$skip   = rest_sanitize_boolean( $request['skip_invalid'] );
		$skills = Jobs::skill_tokens( $request['default_skills'] ?? '' );
		$dry    = BulkImport::run( $rows, array( 'default_skills' => $skills ) );
		if ( is_wp_error( $dry ) ) {
			return $dry;
		}
		if ( $dry['counts']['error'] && ! $skip ) {
			return new \WP_Error(
				'exam_questions',
				__( 'Nothing was imported because some rows have errors. Fix them, or skip invalid rows.', 'ohmylms' ),
				array(
					'status' => 400,
					'rows'   => $dry['rows'],
				)
			);
		}
		$target = Jobs::target( $request['target'] );
		$name   = is_string( $request['name'] ) && '' !== trim( $request['name'] ) ? mb_substr( sanitize_text_field( $request['name'] ), 0, 190 ) : __( 'Imported questions', 'ohmylms' );
		$id     = 0;
		try {
			Transaction::run(
				static function () use ( $rows, $skip, $skills, $target, $name, &$id ) {
					$saved        = ErrorException::raise(
						BulkImport::run(
							$rows,
							array(
								'dry_run'        => false,
								'skip_invalid'   => $skip,
								'default_skills' => $skills,
							)
						)
					);
					$question_ids = array();
					foreach ( $saved['rows'] as $row ) {
						if ( 'error' !== $row['status'] && ! empty( $row['id'] ) ) {
							$question_ids[] = (int) $row['id'];
						}
					}
					if ( ! $question_ids ) {
						throw new \RuntimeException( 'No questions were imported.' );
					}
					$version_ids = array();
					foreach ( $question_ids as $question_id ) {
						if ( 'exam' === $target ) {
							\OhMyLMS\QuestionBank\BankAttributes::write( $question_id, array( 'secure' => 1 ) );
						}
						$version_ids[ $question_id ] = (int) \OhMyLMS\QuestionBank\VersionPublisher::identity( $question_id )['current_version_id'];
					}
					$id = ErrorException::raise(
						wp_insert_post(
							array(
								'post_type'   => Jobs::TYPE,
								'post_status' => 'private',
								'post_author' => get_current_user_id(),
								'post_title'  => $name,
							),
							true
						)
					);
					self::save(
						$id,
						array(
							'document'       => array(
								'id'    => '',
								'name'  => $name,
								'bytes' => 0,
							),
							'mark_scheme'    => null,
							'target'         => $target,
							'state'          => 'committed',
							'attempts'       => 0,
							'error'          => '',
							'inventory'      => array(
								'title'            => $name,
								'duration_minutes' => 60,
							),
							'draft'          => array(),
							'draft_revision' => 0,
							'question_ids'   => $question_ids,
							'version_ids'    => $version_ids,
						)
					);
				}
			);
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'exam_commit', __( 'The questions could not be imported. Nothing was changed.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		$status       = new \WP_REST_Request( 'GET', '/ohmylms/v1/exam-imports/' . $id );
		$status['id'] = $id;
		return new \WP_REST_Response( Jobs::status( $status ), 201 );
	}

	/**
	 * Cancel all stage tokens when a job is removed.
	 *
	 * @param int $id Job ID.
	 */
	public static function cancel( $id ) {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}
		$job = get_post_meta( $id, Jobs::META, true );
		if ( isset( $job['generation'], $job['cursor'] ) ) {
			as_unschedule_all_actions( self::HOOK, array( (int) $id, $job['generation'], $job['cursor'] ), Jobs::GROUP );
		}
	}

	/**
	 * Return an authorization error without disclosing job existence.
	 *
	 * @return \WP_Error Error.
	 */
	private static function denied() {
		return new \WP_Error( 'exam_forbidden', __( 'Import unavailable.', 'ohmylms' ), array( 'status' => 403 ) );
	}
}
