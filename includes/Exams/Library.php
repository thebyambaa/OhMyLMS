<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Exam records backed by the existing versioned quiz engine and a private wrapper course.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Exams;

use OhMyLMS\ExamImport\Jobs;
use OhMyLMS\QuestionBank\VersionPublisher;
use OhMyLMS\Assessment\AssessmentSettings;
use OhMyLMS\Assessment\RevisionPublisher;
use OhMyLMS\Assessment\ErrorException;
use OhMyLMS\Utility\Transaction;

defined( 'ABSPATH' ) || exit;

/** Publication requires approved pinned versions; import never publishes automatically. */
final class Library {
	const TYPE = 'ohmylms-exam';
	const META = '_ohmylms_exam';

	/** Register the exam type, authoring API and separate player. */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		Profile::init();
		Player::init();
		Composer::init();
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'styles' ) );
	}

	/** Load the figure runtime of imported exam questions only in the admin app. */
	public static function styles() {
		$screen = get_current_screen();
		if ( $screen && \OhMyLMS\Admin\AppPage::is_app_screen( $screen->id ) ) {
			\OhMyLMS\ExamImport\FigureContent::enqueue();
		}
	}

	/** Register a nonpublic content record with explicit API visibility. */
	public static function register() {
		register_post_type(
			self::TYPE,
			array(
				'public'       => false,
				'show_ui'      => false,
				'show_in_rest' => false,
				'rewrite'      => false,
				'supports'     => array( 'title', 'author' ),
			)
		);
	}

	/** Register creation, listing, publication and release separately. */
	public static function routes() {
		foreach ( array(
			'GET'  => 'listing',
			'POST' => 'create',
		) as $method => $callback ) {
			register_rest_route(
				'ohmylms/v1',
				'/exams',
				array(
					'methods'             => $method,
					'permission_callback' => array( __CLASS__, 'permission' ),
					'callback'            => array( __CLASS__, $callback ),
				)
			);
		}
		foreach ( array( 'publish', 'release' ) as $operation ) {
			register_rest_route(
				'ohmylms/v1',
				'/exams/(?P<id>\d+)/' . $operation,
				array(
					'methods'             => 'POST',
					'permission_callback' => array( __CLASS__, 'permission' ),
					'callback'            => array( __CLASS__, $operation ),
				)
			);
		}
	}

	/**
	 * Require current authoring access and ownership for existing exams.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool Allowed.
	 */
	public static function permission( $request ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return false;
		}
		return ! isset( $request['id'] ) || ( self::TYPE === get_post_type( $request['id'] ) && get_current_user_id() === (int) get_post_field( 'post_author', $request['id'] ) );
	}

	/**
	 * Safe owner-facing metadata.
	 *
	 * @param int $id Exam ID.
	 * @return array Exam summary.
	 */
	public static function view( $id ) {
		$data = (array) get_post_meta( $id, self::META, true );
		$live = ! empty( $data['composed'] ) && 'publish' !== get_post_status( $id );
		if ( $live ) {
			// A draft written in OhMyLMS reads its questions, time limit and title from the Gutenberg quiz.
			$snapshot = Composer::snapshot( $id );
			$data     = array_merge( $data, $snapshot );
		}
		return array(
			'id'               => (int) $id,
			'title'            => $live ? $snapshot['title'] : get_the_title( $id ),
			'status'           => get_post_status( $id ),
			'url'              => Player::url( $id ),
			'preview_url'      => \OhMyLMS\Assessment\PreviewPlayer::url( $data['quiz_id'] ),
			'duration_minutes' => $data['duration_minutes'],
			'total_marks'      => $data['total_marks'],
			'quiz_id'          => $data['quiz_id'],
			'question_ids'     => $data['question_ids'],
			'job_id'           => (int) $data['job_id'],
			'version_ids'      => $data['version_ids'],
			'source_pages'     => $data['source_pages'],
			'released'         => AssessmentSettings::get( $data['quiz_id'] )['released'],
			'composed'         => ! empty( $data['composed'] ),
			'editor_url'       => ! empty( $data['composed'] ) ? (string) get_edit_post_link( (int) $data['quiz_id'], 'raw' ) : '',
			'instructions'     => (string) ( $data['instructions'] ?? '' ),
			// The printable paper and its answer key, for people who may edit the exam.
			'print_urls'       => \OhMyLMS\QuestionBank\AccessPolicy::can_edit_quiz( (int) $data['quiz_id'] ) ? array(
				'paper' => PrintPaper::url( (int) $data['quiz_id'], 'paper' ),
				'key'   => PrintPaper::url( (int) $data['quiz_id'], 'key' ),
			) : null,
			// What still blocks publication, for a draft written in OhMyLMS.
			'problems'         => $live ? Composer::problems( $id ) : array(),
			// Practice sets and inline checks that publishing would break; publishing asks for confirmation.
			'warnings'         => $live ? Composer::warnings( $id ) : array(),
		);
	}

	/**
	 * List only this author's exams.
	 *
	 * @param \WP_REST_Request $request Pagination.
	 * @return array|\WP_Error Results.
	 */
	public static function listing( $request ) {
		if ( ! self::permission( $request ) ) {
			return self::error( 'permission', __( 'You cannot manage exams.', 'ohmylms' ), 403 );
		}
		$query = new \WP_Query(
			array(
				'post_type'      => self::TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'author'         => get_current_user_id(),
				'posts_per_page' => 20,
				'paged'          => max( 1, (int) ( $request['page'] ?? 1 ) ),
				'orderby'        => 'ID',
				'order'          => 'DESC',
			)
		);
		return array(
			'items' => array_map(
				static function ( $post ) {
					return self::view( $post->ID );
				},
				$query->posts
			),
			'total' => (int) $query->found_posts,
		);
	}

	/**
	 * Use the existing REST writers for backing course/quiz defaults and validation.
	 *
	 * @param string $route Existing route.
	 * @param array  $data Parameters.
	 * @return int New content ID.
	 * @throws \RuntimeException When the backing content writer fails.
	 */
	private static function create_content( $route, array $data ) {
		$request = new \WP_REST_Request( 'POST', '/ohmylms/v1/' . $route );
		$request->set_body_params( $data );
		$response = rest_do_request( $request );
		if ( $response->get_status() >= 300 || empty( $response->get_data()['id'] ) ) {
			throw new \RuntimeException( 'Backing assessment creation failed.' );
		}
		return (int) $response->get_data()['id'];
	}

	/**
	 * Create the private wrapper course, the backing quiz and their placement for a new exam.
	 * Call inside a transaction.
	 *
	 * @param string $title   Exam title.
	 * @param int    $minutes Duration in minutes.
	 * @return int[] Course, quiz and chapter IDs.
	 * @throws \RuntimeException When placement fails.
	 */
	public static function scaffold( $title, $minutes ) {
		global $wpdb;
		$course = self::create_content(
			'courses',
			array(
				'title'  => $title,
				'status' => 'draft',
			)
		);
		ErrorException::raise(
			wp_update_post(
				array(
					'ID'          => $course,
					'post_status' => 'private',
				),
				true
			)
		);
		update_post_meta( $course, '_ohmylms_exam_wrapper', true );
		$quiz    = self::create_content(
			'quiz',
			array(
				'name'     => $title,
				'status'   => 'draft',
				'settings' => array(
					'allow_attempts' => 1,
					'time_limit'     => array(
						'enabled' => true,
						'value'   => (int) $minutes,
						'type'    => 'minutes',
					),
					'layout'         => 'one_question_per_page',
				),
			)
		);
		$chapter = ErrorException::raise(
			wp_insert_post(
				array(
					'post_type'   => 'ohmylms-chapter',
					'post_title'  => $title,
					'post_status' => 'private',
				),
				true
			)
		);
		foreach ( array(
			'ohmylms_chapter_relationship' => array(
				'course_id'    => $course,
				'chapter_id'   => $chapter,
				'order_number' => 0,
			),
			'ohmylms_content_relationship' => array(
				'chapter_id'   => $chapter,
				'content_id'   => $quiz,
				'content_type' => 'quiz',
				'order_number' => 0,
			),
		) as $table => $row ) {
			if ( ! $wpdb->insert( $wpdb->prefix . $table, $row ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Atomic engine relationship creation.
				throw new \RuntimeException( 'Exam placement failed.' );
			}
		}
		return array( $course, $quiz, $chapter );
	}

	/**
	 * Create one draft Exam containing every committed question in paper order.
	 *
	 * @param \WP_REST_Request $request Job and reviewed exam header.
	 * @return array|\WP_Error Exam summary.
	 */
	public static function create( $request ) {
		$job_request       = new \WP_REST_Request();
		$job_request['id'] = absint( $request['job_id'] );
		if ( ! self::permission( $request ) || ! Jobs::permission( $job_request ) ) {
			return self::error( 'permission', __( 'You cannot create this exam.', 'ohmylms' ), 403 );
		}
		return Jobs::locked(
			$job_request['id'],
			static function () use ( $request, $job_request ) {
				global $wpdb;
				$job_id = $job_request['id'];
				clean_post_cache( $job_id );
				$job = get_post_meta( $job_id, Jobs::META, true );
				if ( ! empty( $job['exam_id'] ) ) {
					return self::view( $job['exam_id'] );
				}
				if ( 'committed' !== $job['state'] || empty( $job['question_ids'] ) ) {
					return self::error( 'review', __( 'Commit all reviewed question drafts first.', 'ohmylms' ), 409 );
				}
				$title   = is_string( $request['title'] ) ? sanitize_text_field( $request['title'] ) : '';
				$minutes = $request['duration_minutes'];
				if ( '' === trim( $title ) || ! is_numeric( $minutes ) || $minutes < 1 || $minutes > 1440 ) {
					return self::error( 'header', __( 'Provide a title and a duration from 1 to 1440 minutes.', 'ohmylms' ) );
				}
				$total = 0.0;
				foreach ( $job['version_ids'] as $question_id => $version_id ) {
					$version = VersionPublisher::version( $version_id );
					if ( ! $version || (int) $question_id !== (int) $version['question_id'] ) {
						return self::error( 'version', __( 'A committed question version is unavailable.', 'ohmylms' ), 409 );
					}
					$total += (float) ( json_decode( $version['settings'], true )['score']['value'] ?? 0 );
				}
				try {
					$id = Transaction::run(
						static function () use ( $wpdb, $job, $job_id, $title, $minutes, $total, $request ) {
							list( $course, $quiz, $chapter ) = self::scaffold( $title, (int) $minutes );
							foreach ( $job['question_ids'] as $index => $question_id ) {
								// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Atomic ordered links to existing question identities.
								if ( ! $wpdb->insert(
									$wpdb->prefix . 'ohmylms_quiz_questions_relationship',
									array(
										'quiz_id'      => $quiz,
										'question_id'  => $question_id,
										'order_number' => $index,
									)
								) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Preserve the reviewed paper order atomically.
									throw new \RuntimeException( 'Exam question placement failed.' );
								}
							}
							update_post_meta( $quiz, '_ohmylms_version_pins', $job['version_ids'] );
							ErrorException::raise(
								AssessmentSettings::save(
									$quiz,
									array(
										'preset'           => 'exam',
										'feedback_release' => 'manual',
									)
								)
							);
							$exam_id = ErrorException::raise(
								wp_insert_post(
									array(
										'post_type'   => self::TYPE,
										'post_status' => 'draft',
										'post_title'  => $title,
										'post_author' => get_current_user_id(),
									),
									true
								)
							);
							$header  = array();
							foreach ( array( 'board', 'qualification', 'subject_code', 'paper', 'variant', 'session', 'calculator', 'instructions' ) as $field ) {
								$header[ $field ] = is_scalar( $request[ $field ] ) ? mb_substr( sanitize_textarea_field( (string) $request[ $field ] ), 0, 4000 ) : '';
							}
							update_post_meta(
								$exam_id,
								self::META,
								wp_slash(
									$header + array(
										'job_id'           => $job_id,
										'course_id'        => $course,
										'chapter_id'       => $chapter,
										'quiz_id'          => $quiz,
										'question_ids'     => $job['question_ids'],
										'version_ids'      => $job['version_ids'],
										'duration_minutes' => (int) $minutes,
										'total_marks'      => $total,
										'source_pages'     => array_column( $job['draft'], 'page', 'number' ),
									)
								)
							);
							update_post_meta( $quiz, '_ohmylms_exam_id', $exam_id );
							$job['exam_id'] = $exam_id;
							update_post_meta( $job_id, Jobs::META, wp_slash( $job ) );
							return $exam_id;
						}
					);
				} catch ( \Throwable $error ) {
					return self::error( 'storage', __( 'The draft exam could not be created. Retry after reloading.', 'ohmylms' ), 500 );
				}
				return self::view( $id );
			}
		);
	}

	/**
	 * Publish an exam only after its exact question versions have human approval.
	 *
	 * @param \WP_REST_Request $request Exam ID.
	 * @return array|\WP_Error Exam summary.
	 */
	public static function publish( $request ) {
		if ( ! self::permission( $request ) || ! current_user_can( 'publish_posts' ) ) {
			return self::error( 'permission', __( 'You cannot publish this exam.', 'ohmylms' ), 403 );
		}
		$id      = (int) $request['id'];
		$confirm = rest_sanitize_boolean( $request['confirm_overlap'] );
		return Jobs::locked(
			$id,
			static function () use ( $id, $confirm ) {
				$data = get_post_meta( $id, self::META, true );
				if ( ! empty( $data['composed'] ) ) {
					// The author of a builder exam is its reviewer: check completeness, then approve the pinned versions.
					$problems = Composer::problems( $id );
					if ( $problems ) {
						return self::error( 'incomplete', implode( ' ', $problems ), 409 );
					}
					$warnings = $confirm ? array() : Composer::warnings( $id );
					if ( $warnings ) {
						return new \WP_Error(
							'exam_overlap',
							implode( ' ', $warnings ),
							array(
								'status'   => 409,
								'warnings' => $warnings,
							)
						);
					}
					Composer::refresh( $id );
					$approved = Composer::approve( $id );
					if ( is_wp_error( $approved ) ) {
						return $approved;
					}
					$data = get_post_meta( $id, self::META, true );
				}
				foreach ( $data['version_ids'] as $question_id => $version_id ) {
					$identity = VersionPublisher::identity( $question_id, false );
					if ( ! $identity || (int) $version_id !== (int) $identity['approved_version_id'] ) {
						return self::error( 'approval', __( 'Approve every committed question version in the Question Bank before publishing.', 'ohmylms' ), 409 );
					}
				}
				try {
					Transaction::run(
						static function () use ( $data, $id ) {
							ErrorException::raise( RevisionPublisher::publish( $data['quiz_id'] ) );
							ErrorException::raise(
								wp_update_post(
									array(
										'ID'          => $data['quiz_id'],
										'post_status' => 'publish',
									),
									true
								)
							);
							ErrorException::raise(
								wp_update_post(
									array(
										'ID'          => $id,
										'post_status' => 'publish',
									),
									true
								)
							);
						}
					);
				} catch ( \Throwable $error ) {
					return self::error( 'publish', __( 'Exam publication failed.', 'ohmylms' ), 500 );
				}
				return self::view( $id );
			}
		);
	}

	/**
	 * Explicitly release results and the private mark scheme using engine policy.
	 *
	 * @param \WP_REST_Request $request Exam ID.
	 * @return array|\WP_Error Exam summary.
	 */
	public static function release( $request ) {
		if ( ! self::permission( $request ) || ! current_user_can( 'publish_posts' ) ) {
			return self::error( 'permission', __( 'You cannot release these results.', 'ohmylms' ), 403 );
		}
		$data   = get_post_meta( $request['id'], self::META, true );
		$result = AssessmentSettings::save( $data['quiz_id'], array( 'released' => true ) );
		return is_wp_error( $result ) ? $result : self::view( $request['id'] );
	}

	/**
	 * Safe domain error.
	 *
	 * @param string $code Code.
	 * @param string $message Message.
	 * @param int    $status HTTP status.
	 * @return \WP_Error Error.
	 */
	private static function error( $code, $message, $status = 400 ) {
		return new \WP_Error( 'exam_' . $code, $message, array( 'status' => $status ) );
	}
}
