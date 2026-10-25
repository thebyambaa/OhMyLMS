<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Durable, owner-scoped import jobs with bounded Action Scheduler validation.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\ExamImport;

defined( 'ABSPATH' ) || exit;

/** No stage in this foundation publishes questions or transmits PDFs to AI. */
final class Jobs {

	const TYPE  = 'ohmylms-import';
	const META  = '_ohmylms_import_job';
	const HOOK  = 'ohmylms_validate_exam_import';
	const GROUP = 'ohmylms-exam-import';

	/** Register only private storage and authenticated authoring routes. */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( self::HOOK, array( __CLASS__, 'process' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'cleanup' ) );
		Workflow::init();
		Figures::init();
	}

	/** Register the nonpublic persistence record, separate from the future Exam type. */
	public static function register() {
		register_post_type(
			self::TYPE,
			array(
				'public'       => false,
				'show_ui'      => false,
				'show_in_rest' => false,
				'rewrite'      => false,
				'query_var'    => false,
				'supports'     => array(),
			)
		);
	}

	/** Register upload, status, retry and removal endpoints. */
	public static function routes() {
		register_rest_route(
			'ohmylms/v1',
			'/exam-imports',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( __CLASS__, 'permission' ),
				'callback'            => array( __CLASS__, 'create' ),
			)
		);
		register_rest_route(
			'ohmylms/v1',
			'/exam-imports',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( __CLASS__, 'permission' ),
				'callback'            => array( __CLASS__, 'listing' ),
			)
		);
		foreach ( array(
			'GET'    => 'status',
			'POST'   => 'retry',
			'DELETE' => 'delete',
		) as $method => $callback ) {
			register_rest_route(
				'ohmylms/v1',
				'/exam-imports/(?P<id>\d+)',
				array(
					'methods'             => $method,
					'permission_callback' => array( __CLASS__, 'permission' ),
					'callback'            => array( __CLASS__, $callback ),
				)
			);
		}
	}

	/**
	 * Recover the current owner's jobs after leaving the import page.
	 *
	 * @param \WP_REST_Request $request Paginated request.
	 * @return array|\WP_Error Owner-scoped job summaries.
	 */
	public static function listing( $request ) {
		if ( ! self::permission( $request ) ) {
			return new \WP_Error( 'exam_forbidden', __( 'Import unavailable.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		$query = new \WP_Query(
			array(
				'post_type'      => self::TYPE,
				'post_status'    => 'private',
				'author'         => get_current_user_id(),
				'posts_per_page' => 20,
				'paged'          => max( 1, (int) ( $request['page'] ?? 1 ) ),
				'orderby'        => 'ID',
				'order'          => 'DESC',
			)
		);
		$items = array();
		foreach ( $query->posts as $post ) {
			$job = get_post_meta( $post->ID, self::META, true );
			if ( is_array( $job ) ) {
				$items[] = self::view( $post->ID, $job );
			}
		}
		return array(
			'items' => $items,
			'total' => (int) $query->found_posts,
		);
	}

	/**
	 * Verify capabilities and ownership on every request, including administrators.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool Authorization.
	 */
	public static function permission( $request ) {
		if ( ! current_user_can( 'edit_posts' ) || ! current_user_can( 'upload_files' ) ) {
			return false;
		}
		if ( ! isset( $request['id'] ) ) {
			return true;
		}
		$post = get_post( (int) $request['id'] );
		return $post && self::TYPE === $post->post_type && get_current_user_id() === (int) $post->post_author;
	}

	/**
	 * Store one uploaded source and enqueue validation; scheduler must be available.
	 *
	 * @param \WP_REST_Request $request Multipart upload request.
	 * @return \WP_REST_Response|\WP_Error Created job.
	 */
	public static function create( $request ) {
		if ( ! self::permission( $request ) ) {
			return new \WP_Error( 'exam_forbidden', __( 'Import unavailable.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		if ( ! function_exists( 'as_enqueue_async_action' ) || ! did_action( 'action_scheduler_init' ) ) {
			return new \WP_Error( 'exam_scheduler', __( 'The import scheduler is unavailable.', 'ohmylms' ), array( 'status' => 503 ) );
		}
		$files = $request->get_file_params();
		$file  = $files['file'] ?? null;
		$doc   = is_array( $file ) ? Documents::upload( $file ) : new \WP_Error( 'exam_upload', __( 'Choose one source PDF.', 'ohmylms' ), array( 'status' => 400 ) );
		if ( is_wp_error( $doc ) ) {
			return $doc;
		}
		$mark_scheme = null;
		if ( isset( $files['mark_scheme'] ) && UPLOAD_ERR_NO_FILE !== ( $files['mark_scheme']['error'] ?? null ) ) {
			$mark_scheme = Documents::upload( (array) $files['mark_scheme'] );
			if ( is_wp_error( $mark_scheme ) ) {
				Documents::remove( $doc['id'] );
				return $mark_scheme;
			}
		}
		$id = wp_insert_post(
			array(
				'post_type'   => self::TYPE,
				'post_status' => 'private',
				'post_author' => get_current_user_id(),
				'post_title'  => $doc['name'],
			),
			true
		);
		if ( is_wp_error( $id ) || ! $id ) {
			Documents::remove( $doc['id'] );
			if ( $mark_scheme ) {
				Documents::remove( $mark_scheme['id'] );
			}
			return new \WP_Error( 'exam_job', __( 'Could not create the import job.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		$job = array(
			'document'    => $doc,
			'mark_scheme' => $mark_scheme,
			'target'      => self::target( $request['target'] ?? '' ),
			'skills'      => self::skill_tokens( $request['default_skills'] ?? '' ),
			'state'       => 'queued',
			'attempts'    => 0,
			'error'       => '',
			'updated_at'  => gmdate( 'c' ),
		);
		if ( ! update_post_meta( $id, self::META, $job ) || ! as_enqueue_async_action( self::HOOK, array( $id ), self::GROUP, true ) ) {
			wp_delete_post( $id, true );
			Documents::remove( $doc['id'] );
			if ( $mark_scheme ) {
				Documents::remove( $mark_scheme['id'] );
			}
			return new \WP_Error( 'exam_queue', __( 'Could not queue the import. Upload it again.', 'ohmylms' ), array( 'status' => 503 ) );
		}
		return new \WP_REST_Response( self::view( $id, $job ), 201 );
	}

	/**
	 * Skill references (#ID) from a list of skill IDs, at most 20.
	 *
	 * @param mixed $value IDs or #IDs separated by commas, semicolons or spaces.
	 * @return string Tokens such as "#12;#40", or an empty string.
	 */
	public static function skill_tokens( $value ) {
		preg_match_all( '/\d+/', is_scalar( $value ) ? (string) $value : '', $found );
		$ids = array_slice( array_unique( array_filter( array_map( 'absint', $found[0] ) ) ), 0, 20 );
		return $ids ? '#' . implode( ';#', $ids ) : '';
	}

	/**
	 * What a finished import becomes: an exam (the default), a quiz, or question bank drafts only.
	 *
	 * @param mixed $target Requested target.
	 * @return string exam|quiz|questions.
	 */
	public static function target( $target ) {
		return is_string( $target ) && in_array( $target, array( 'quiz', 'questions' ), true ) ? $target : 'exam';
	}

	/**
	 * Safe job metadata, excluding document handles, hashes and filesystem paths.
	 *
	 * @param int   $id Job ID.
	 * @param array $job Private state.
	 * @return array Public owner-scoped status.
	 */
	private static function view( $id, array $job ) {
		return array(
			'id'         => (int) $id,
			'state'      => $job['state'],
			'target'     => self::target( $job['target'] ?? '' ),
			'attempts'   => $job['attempts'],
			'error'      => $job['error'],
			'updated_at' => $job['updated_at'],
			'source'     => array_intersect_key( $job['document'], array_flip( array( 'name', 'bytes' ) ) ),
		);
	}

	/**
	 * Read job status for its current owner.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error Status.
	 */
	public static function status( $request ) {
		if ( ! self::permission( $request ) ) {
			return new \WP_Error( 'exam_forbidden', __( 'Import unavailable.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		$job = (array) get_post_meta( $request['id'], self::META, true );
		return array_merge( self::view( $request['id'], $job ), Workflow::detail( $job ) );
	}

	/**
	 * Serialize worker, retry and delete across requests without expiring leases.
	 *
	 * @param int      $id Job ID.
	 * @param callable $work Operation.
	 * @return mixed Result.
	 */
	public static function locked( $id, callable $work ) {
		global $wpdb;
		$key = 'oml_import_' . md5( $wpdb->prefix . ':' . (int) $id );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $key ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Connection-scoped worker serialization, not cacheable.
			return new \WP_Error( 'exam_busy', __( 'The import is busy. Retry shortly.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		try {
			return $work();
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Release the connection-scoped lock.
		}
	}

	/**
	 * Run one bounded validation stage; duplicate deliveries are harmless.
	 *
	 * @param int $id Job ID.
	 * @return mixed Processing result.
	 */
	public static function process( $id ) {
		return self::locked(
			$id,
			static function () use ( $id ) {
				clean_post_cache( $id );
				$post = get_post( $id );
				$job  = get_post_meta( $id, self::META, true );
				if ( ! $post || self::TYPE !== $post->post_type || ! is_array( $job ) || 'queued' !== $job['state'] || $job['attempts'] >= 3 ) {
					return;
				}
				++$job['attempts'];
				$path              = Documents::path( $job['document']['id'] );
				$valid             = user_can( $post->post_author, 'edit_posts' ) && user_can( $post->post_author, 'upload_files' ) && ! is_wp_error( $path ) && filesize( $path ) === $job['document']['bytes'] && filesize( $path ) <= Documents::MAX_BYTES && hash_equals( $job['document']['sha256'], (string) hash_file( 'sha256', $path ) );
				$job['state']      = $valid ? 'ready_for_extraction' : 'failed';
				$job['error']      = $valid ? '' : 'source_validation';
				$job['updated_at'] = gmdate( 'c' );
				update_post_meta( $id, self::META, $job );
			}
		);
	}

	/**
	 * Retry failed validation within its three-attempt budget.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error Status.
	 */
	public static function retry( $request ) {
		if ( ! self::permission( $request ) ) {
			return new \WP_Error( 'exam_forbidden', __( 'Import unavailable.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		return self::locked(
			$request['id'],
			static function () use ( $request ) {
				$id = (int) $request['id'];
				clean_post_cache( $id );
				$job = get_post_meta( $id, self::META, true );
				if ( ! is_array( $job ) || ! in_array( $job['state'], array( 'failed', 'queued' ), true ) || $job['attempts'] >= 3 ) {
					return new \WP_Error( 'exam_retry', __( 'This import cannot be retried.', 'ohmylms' ), array( 'status' => 409 ) );
				}
				if ( ! function_exists( 'as_enqueue_async_action' ) || ! as_enqueue_async_action( self::HOOK, array( $id ), self::GROUP, true ) ) {
					return new \WP_Error( 'exam_queue', __( 'Could not queue the import.', 'ohmylms' ), array( 'status' => 503 ) );
				}
				$job['state'] = 'queued';
				$job['error'] = '';
				update_post_meta( $id, self::META, $job );
				return self::view( $id, $job );
			}
		);
	}

	/**
	 * Delete only an owned job, with worker serialization.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error Result.
	 */
	public static function delete( $request ) {
		if ( ! self::permission( $request ) ) {
			return new \WP_Error( 'exam_forbidden', __( 'Import unavailable.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		return self::locked(
			$request['id'],
			static function () use ( $request ) {
				$job = get_post_meta( $request['id'], self::META, true );
				if ( ! empty( $job['exam_id'] ) && get_post( $job['exam_id'] ) ) {
					return new \WP_Error( 'exam_source_in_use', __( 'This source belongs to an exam and must be retained.', 'ohmylms' ), array( 'status' => 409 ) );
				}
				return array( 'deleted' => (bool) wp_delete_post( $request['id'], true ) );
			}
		);
	}

	/**
	 * Remove private source and pending actions with the job.
	 *
	 * @param int $id Deleted post ID.
	 */
	public static function cleanup( $id ) {
		if ( self::TYPE !== get_post_type( $id ) ) {
			return;
		}
		$job = get_post_meta( $id, self::META, true );
		if ( is_array( $job ) && isset( $job['document']['id'] ) ) {
			Documents::remove( $job['document']['id'] );
		}
		if ( ! empty( $job['mark_scheme']['id'] ) ) {
			Documents::remove( $job['mark_scheme']['id'] );
		}
		Workflow::cancel( $id );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK, array( (int) $id ), self::GROUP );
		}
	}
}
