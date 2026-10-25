<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Owner-scoped, asynchronous crop assets independent of source job deletion.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\ExamImport;

defined( 'ABSPATH' ) || exit;

/** Original crops are private assets, never WordPress media attachments. */
final class Figures {

	const TYPE       = 'ohmylms-figure';
	const META       = '_ohmylms_figure_asset';
	const HOOK       = 'ohmylms_render_exam_figure';
	const MAX_ASSETS = 100;

	/** Register isolated persistence, worker, author API and protected streaming. */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( self::HOOK, array( __CLASS__, 'process' ) );
		add_action( 'template_redirect', array( __CLASS__, 'document' ), 5 );
	}

	/** Register assets outside public queries and generic authoring APIs. */
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

	/** Register nonce-authenticated author operations through core REST auth. */
	public static function routes() {
		Reconstruction::routes();
		register_rest_route(
			'ohmylms/v1',
			'/exam-imports/(?P<id>\d+)/figures',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => array( Jobs::class, 'permission' ),
					'callback'            => array( __CLASS__, 'listing' ),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => array( Jobs::class, 'permission' ),
					'callback'            => array( __CLASS__, 'create' ),
				),
			)
		);
		register_rest_route(
			'ohmylms/v1',
			'/exam-import-renderer',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( Jobs::class, 'permission' ),
				'callback'            => array( PageRenderer::class, 'capability' ),
			)
		);
	}

	/**
	 * List all retained crops for an owned source job.
	 *
	 * @param \WP_REST_Request $request Author request.
	 * @return array|\WP_Error Safe asset views.
	 */
	public static function listing( $request ) {
		if ( ! Jobs::permission( $request ) ) {
			return self::denied();
		}
		return array_map( array( __CLASS__, 'view' ), self::records( (int) $request['id'] ) );
	}

	/**
	 * Find a bounded set of asset records belonging to a job.
	 *
	 * @param int $job_id Source job.
	 * @return \WP_Post[] Private asset records.
	 */
	private static function records( $job_id ) {
		return get_posts(
			array(
				'post_type'   => self::TYPE,
				'post_status' => 'private',
				'post_parent' => $job_id,
				'numberposts' => self::MAX_ASSETS,
				'orderby'     => 'ID',
				'order'       => 'ASC',
			)
		);
	}

	/**
	 * Queue a manual crop. Identical retries reuse a record, never mutate a ready crop.
	 *
	 * @param \WP_REST_Request $request Job, page, normalized bounds.
	 * @return array|\WP_Error Safe asset view.
	 */
	public static function create( $request ) {
		if ( ! Jobs::permission( $request ) ) {
			return self::denied();
		}
		$bounds = PageRenderer::bounds( $request['bounds'] );
		$page   = $request['page'];
		if ( is_wp_error( $bounds ) || ! is_int( $page ) || $page < 1 || $page > 500 ) {
			return new \WP_Error( 'exam_crop_bounds', __( 'Choose a page from 1 to 500 and normalized left, top, right, bottom bounds.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		if ( ! function_exists( 'as_schedule_single_action' ) || ! did_action( 'action_scheduler_init' ) ) {
			return new \WP_Error( 'exam_scheduler', __( 'The import scheduler is unavailable.', 'ohmylms' ), array( 'status' => 503 ) );
		}
		return Jobs::locked(
			$request['id'],
			static function () use ( $request, $page, $bounds ) {
				return self::enqueue_crop( (int) $request['id'], $page, $bounds, get_current_user_id() );
			}
		);
	}

	/**
	 * Queue a crop for the already authorized source owner, also used by extraction workers.
	 *
	 * Caller holds the source-job lock.
	 *
	 * @param int   $job_id Source job.
	 * @param int   $page Source page.
	 * @param array $bounds Validated normalized bounds.
	 * @param int   $owner Source owner.
	 * @return array|\WP_Error Safe asset view.
	 */
	public static function enqueue_crop( $job_id, $page, array $bounds, $owner ) {
		$post = get_post( $job_id );
		if ( ! $post || Jobs::TYPE !== $post->post_type || (int) $post->post_author !== (int) $owner || ! user_can( $owner, 'edit_posts' ) || ! user_can( $owner, 'upload_files' ) || is_wp_error( PageRenderer::bounds( $bounds ) ) || ! is_int( $page ) || $page < 1 || $page > 500 ) {
			return self::denied();
		}
		$job = get_post_meta( $job_id, Jobs::META, true );
		if ( ! is_array( $job ) || empty( $job['document']['sha256'] ) || in_array( $job['state'], array( 'queued', 'failed' ), true ) ) {
			return new \WP_Error( 'exam_crop_source', __( 'Validate the source PDF before creating crops.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		$key     = hash( 'sha256', wp_json_encode( array( $job['document']['sha256'], $page, $bounds, PageRenderer::VERSION, PageRenderer::MAX_SIDE ) ) );
		$records = self::records( $job_id );
		foreach ( $records as $post ) {
			$data = get_post_meta( $post->ID, self::META, true );
			if ( ( $data['request_key'] ?? '' ) === $key ) {
				return self::queue( $post->ID, $data );
			}
		}
		if ( count( $records ) >= self::MAX_ASSETS ) {
			return new \WP_Error( 'exam_crop_limit', __( 'This import has reached its crop limit.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		$data = array(
			'uuid'          => wp_generate_uuid4(),
			'request_key'   => $key,
			'document'      => $job['document'],
			'document_role' => 'question',
			'page'          => $page,
			'bounds'        => $bounds,
			'state'         => 'queued',
			'attempts'      => 0,
			'error'         => '',
		);
		$id   = wp_insert_post(
			array(
				'post_type'   => self::TYPE,
				'post_status' => 'private',
				'post_author' => $owner,
				'post_parent' => $job_id,
				'post_name'   => $data['uuid'],
				'post_title'  => 'Private exam figure',
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		if ( ! add_post_meta( $id, self::META, wp_slash( $data ), true ) ) {
			wp_delete_post( $id, true );
			return self::storage_error();
		}
		return self::queue( $id, $data );
	}

	/**
	 * Schedule or resume a crop within its bounded attempt budget.
	 *
	 * @param int   $id Asset record.
	 * @param array $data Stored metadata.
	 * @return array|\WP_Error Safe asset view.
	 */
	private static function queue( $id, array $data ) {
		if ( 'ready' !== $data['state'] && $data['attempts'] >= 3 ) {
			$data['state'] = 'failed';
			$data['error'] = $data['error'] ? $data['error'] : 'exam_render_attempts';
			if ( ! self::save( $id, $data ) ) {
				return self::storage_error();
			}
		}
		if ( 'ready' !== $data['state'] && $data['attempts'] < 3 ) {
			$data['state'] = 'queued';
			if ( ! self::save( $id, $data ) ) {
				return self::storage_error();
			}
			if ( ! as_has_scheduled_action( self::HOOK, array( $id ), Jobs::GROUP ) && ! as_schedule_single_action( time() + 1, self::HOOK, array( $id ), Jobs::GROUP, false ) ) {
				return new \WP_Error( 'exam_scheduler', __( 'Could not queue the crop. Retry the same request.', 'ohmylms' ), array( 'status' => 503 ) );
			}
		}
		return self::view( get_post( $id ) );
	}

	/**
	 * Render a single crop under the existing source-job lock.
	 *
	 * @param int $id Asset record.
	 * @return mixed Worker outcome.
	 */
	public static function process( $id ) {
		$post = get_post( $id );
		if ( ! $post || self::TYPE !== $post->post_type ) {
			return;
		}
		return Jobs::locked(
			$post->post_parent,
			static function () use ( $id ) {
				clean_post_cache( $id );
				$post = get_post( $id );
				$data = get_post_meta( $id, self::META, true );
				if ( ! $post || ! is_array( $data ) || 'queued' !== $data['state'] || $data['attempts'] >= 3 ) {
					return;
				}
				++$data['attempts'];
				$data['state'] = 'rendering';
				// Persist the attempt before invoking an external process; interrupted work is bounded.
				if ( ! self::save( $id, $data ) ) {
					return self::storage_error();
				}
				$result = user_can( $post->post_author, 'edit_posts' ) && user_can( $post->post_author, 'upload_files' ) ? PageRenderer::crop( $data['document'], $data['page'], $data['bounds'] ) : self::denied();
				if ( ! is_wp_error( $result ) ) {
					$result = self::store( $data, $result );
				}
				if ( is_wp_error( $result ) ) {
					$data['state'] = 'failed';
					$data['error'] = $result->get_error_code();
				} else {
					$data          = array_merge( $data, $result );
					$data['state'] = 'ready';
					$data['error'] = '';
				}
				if ( ! self::save( $id, $data ) ) {
					return self::storage_error();
				}
			}
		);
	}

	/**
	 * Persist immutable PNG bytes using exclusive creation and checksum recovery.
	 *
	 * @param array $data Asset metadata.
	 * @param array $render Render result.
	 * @return array|\WP_Error Metadata without binary bytes.
	 */
	private static function store( array $data, array $render ) {
		$root = Documents::root();
		if ( is_wp_error( $root ) ) {
			return $root;
		}
		$path   = $root . DIRECTORY_SEPARATOR . 'figure-' . $data['uuid'] . '.png';
		$bytes  = $render['bytes'];
		$digest = hash( 'sha256', $bytes );
		unset( $render['bytes'] );
		if ( is_link( $path ) ) {
			return self::storage_error();
		}
		if ( is_file( $path ) ) {
			// Recover only a complete identical write after a worker interruption.
			if ( ! hash_equals( $digest, (string) hash_file( 'sha256', $path ) ) ) {
				return self::storage_error();
			}
		} else {
			$handle = fopen( $path, 'x+b' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Immutable exclusive private asset creation.
			if ( false === $handle ) {
				return self::storage_error();
			}
			chmod( $path, 0600 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Private crop permissions.
			$written = fwrite( $handle, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Bounded verified raster bytes.
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close exclusive asset handle.
			if ( strlen( $bytes ) !== $written ) {
				wp_delete_file( $path );
				return self::storage_error();
			}
		}
		return $render + array(
			'sha256'     => $digest,
			'byte_count' => strlen( $bytes ),
			'mime'       => 'image/png',
		);
	}

	/**
	 * Safe author projection, never exposing document handles or local paths.
	 *
	 * @param \WP_Post $post Asset record.
	 * @return array Safe metadata. Creation never implies teacher approval.
	 */
	public static function view( $post ) {
		$data = get_post_meta( $post->ID, self::META, true );
		return array_intersect_key( $data, array_flip( array( 'state', 'attempts', 'error', 'page', 'bounds', 'width', 'height', 'page_width', 'page_height', 'pixel_bounds', 'engine' ) ) ) + array(
			'id'  => $data['uuid'],
			'url' => 'ready' === $data['state'] ? add_query_arg( 'ohmylms_figure', $data['uuid'], home_url( '/' ) ) : '',
		);
	}

	/**
	 * Resolve an asset only for its author; learner version bindings come later.
	 *
	 * @param string $uuid Opaque asset ID.
	 * @return string|\WP_Error Authorized private PNG path.
	 */
	public static function author_path( $uuid ) {
		if ( ! is_string( $uuid ) || ! wp_is_uuid( $uuid ) || ! current_user_can( 'edit_posts' ) || ! current_user_can( 'upload_files' ) ) {
			return self::denied();
		}
		$data = self::asset( $uuid );
		return $data && get_current_user_id() === (int) $data['owner'] ? self::verified_path( $uuid ) : self::denied();
	}

	/**
	 * Read private metadata by a validated opaque identifier.
	 *
	 * @param string $uuid Asset ID.
	 * @return array|null Metadata, server-side only.
	 */
	public static function asset( $uuid ) {
		if ( ! is_string( $uuid ) || ! wp_is_uuid( $uuid ) ) {
			return null;
		}
		$posts = get_posts(
			array(
				'post_type'   => self::TYPE,
				'post_status' => 'private',
				'name'        => $uuid,
				'numberposts' => 1,
			)
		);
		$data  = $posts ? get_post_meta( $posts[0]->ID, self::META, true ) : array();
		return $posts && $data ? $data + array(
			'owner'  => (int) $posts[0]->post_author,
			'job_id' => (int) $posts[0]->post_parent,
		) : null;
	}

	/**
	 * Verify immutable bytes after a caller has independently authorized their delivery.
	 *
	 * @param string $uuid Asset ID.
	 * @return string|\WP_Error Private path; never expose through REST.
	 */
	public static function verified_path( $uuid ) {
		$data = self::asset( $uuid );
		$root = Documents::root();
		if ( is_wp_error( $root ) || 'ready' !== ( $data['state'] ?? '' ) || ( $data['uuid'] ?? '' ) !== $uuid ) {
			return self::denied();
		}
		$path = $root . DIRECTORY_SEPARATOR . 'figure-' . $uuid . '.png';
		if ( is_link( $path ) || ! is_file( $path ) || ! is_readable( $path ) || realpath( dirname( $path ) ) !== $root || filesize( $path ) !== $data['byte_count'] || ! hash_equals( $data['sha256'], (string) hash_file( 'sha256', $path ) ) ) {
			return self::denied();
		}
		return $path;
	}

	/** Stream binary bytes only after authorization; no public attachment exists. */
	public static function document() {
		if ( ! isset( $_GET['ohmylms_figure'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only protected asset navigation.
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		header( 'Cache-Control: private, no-store, max-age=0' );
		$uuid    = is_string( $_GET['ohmylms_figure'] ) ? sanitize_text_field( wp_unslash( $_GET['ohmylms_figure'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only, independently owner-authorized stream.
		$version = isset( $_GET['figure_version'] ) && is_scalar( $_GET['figure_version'] ) ? absint( $_GET['figure_version'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only version navigation.
		$scope   = isset( $_GET['figure_scope'] ) && is_string( $_GET['figure_scope'] ) ? sanitize_text_field( wp_unslash( $_GET['figure_scope'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Live attempt authorization follows.
		$path    = FigureContent::path( $uuid, $version, $scope );
		if ( is_wp_error( $path ) ) {
			wp_die( esc_html__( 'Figure unavailable.', 'ohmylms' ), '', array( 'response' => 403 ) );
		}
		header( 'Content-Type: image/png' );
		header( 'Content-Disposition: inline; filename="exam-figure.png"' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cross-Origin-Resource-Policy: same-origin' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile, WordPress.Security.EscapeOutput.OutputNotEscaped -- Authorized checksum-verified PNG bytes; HTML escaping corrupts the image.
		exit;
	}

	/**
	 * Report access denial without disclosing asset existence.
	 *
	 * @return \WP_Error Safe error.
	 */
	private static function denied() {
		return new \WP_Error( 'exam_figure_forbidden', __( 'Figure unavailable.', 'ohmylms' ), array( 'status' => 403 ) );
	}

	/**
	 * Report private persistence failure.
	 *
	 * @return \WP_Error Safe error.
	 */
	private static function storage_error() {
		return new \WP_Error( 'exam_figure_storage', __( 'Could not preserve the figure.', 'ohmylms' ), array( 'status' => 503 ) );
	}

	/**
	 * Persist state and verify no-op writes rather than ignoring database errors.
	 *
	 * @param int   $id Asset record.
	 * @param array $data Private metadata.
	 * @return bool Stored exactly.
	 */
	private static function save( $id, array $data ) {
		return false !== update_post_meta( $id, self::META, wp_slash( $data ) ) || get_post_meta( $id, self::META, true ) === $data;
	}
}
