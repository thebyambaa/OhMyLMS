<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Source PDFs and rendered page images for the review screen's side-by-side viewer.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\ExamImport;

use OhMyLMS\Utility\Lock;

defined( 'ABSPATH' ) || exit;

/**
 * Page images are cached as private `page-{document}-{page}-{contract}.png` files next to the sources,
 * never as media or public URLs; callers authorise the job owner first ({@see \OhMyLMS\Rest\V1\ExamImportSourceController}).
 */
final class SourcePages {

	/** Seconds a request waits for another request rendering the same page. */
	const WAIT = 25;

	/**
	 * Renderer status plus whether crops can be queued.
	 *
	 * @return array
	 */
	public static function renderer() {
		$status          = PageRenderer::status();
		$status['crops'] = $status['available'] && function_exists( 'as_schedule_single_action' ) && did_action( 'action_scheduler_init' ) > 0;
		return $status;
	}

	/**
	 * The validated question paper of a job.
	 *
	 * @param array $job Job state.
	 * @return array|\WP_Error Document metadata.
	 */
	private static function paper( array $job ) {
		if ( empty( $job['document']['id'] ) ) {
			return new \WP_Error( 'ohmylms_exam_import_no_source', __( 'This import has no PDF paper.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		if ( in_array( $job['state'] ?? '', array( 'queued', 'failed' ), true ) ) {
			return new \WP_Error( 'ohmylms_exam_import_source_unchecked', __( 'The PDF is still being checked. Try again when the import is ready.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		return $job['document'];
	}

	/**
	 * Pages the paper is known to have; 0 before extraction counted them.
	 *
	 * @param array $job Job state.
	 * @return int
	 */
	public static function page_count( array $job ) {
		$inventory = (array) ( $job['draft_inventory'] ?? ( $job['inventory'] ?? array() ) );
		return max( 0, (int) ( $inventory['page_count'] ?? 0 ) );
	}

	/**
	 * Verified path of the paper or the mark scheme.
	 *
	 * @param array  $job  Job state.
	 * @param string $part `paper` or `mark_scheme`.
	 * @return string|\WP_Error Local path, never returned to clients.
	 */
	public static function document( array $job, $part ) {
		$document = 'mark_scheme' === $part ? ( $job['mark_scheme'] ?? null ) : self::paper( $job );
		if ( is_wp_error( $document ) ) {
			return $document;
		}
		if ( ! is_array( $document ) || empty( $document['id'] ) ) {
			return new \WP_Error( 'ohmylms_exam_import_no_source', __( 'This import has no such document.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$path = Documents::path( $document['id'] );
		if ( is_wp_error( $path ) || empty( $document['sha256'] ) || filesize( $path ) !== (int) ( $document['bytes'] ?? -1 ) || ! hash_equals( (string) $document['sha256'], (string) hash_file( 'sha256', $path ) ) ) {
			return self::unavailable();
		}
		return $path;
	}

	/**
	 * Verified path of a page image, rendering and caching it once; concurrent requests for the same
	 * page wait for the first.
	 *
	 * @param array $job  Job state.
	 * @param int   $page One-based page.
	 * @return string|\WP_Error Local PNG path, never returned to clients.
	 */
	public static function page( array $job, $page ) {
		$document = self::paper( $job );
		if ( is_wp_error( $document ) ) {
			return $document;
		}
		$count = self::page_count( $job );
		if ( ! is_int( $page ) || $page < 1 || $page > 500 || ( $count && $page > $count ) ) {
			return new \WP_Error( 'ohmylms_exam_import_page_not_found', __( 'The paper has no such page.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$path = self::cache_path( $document, $page );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		if ( self::cached( $path ) ) {
			return $path;
		}
		return Lock::with(
			'oml_import_page_' . md5( $document['id'] . ':' . $page ),
			self::WAIT,
			static function () use ( $document, $page, $path ) {
				if ( self::cached( $path ) ) {
					return $path;
				}
				if ( ! PageRenderer::status()['available'] ) {
					return new \WP_Error( 'ohmylms_exam_import_renderer_unavailable', __( 'No PDF renderer is configured on this server. Use the PDF viewer instead.', 'ohmylms' ), array( 'status' => 503 ) );
				}
				$render = PageRenderer::page( $document, $page );
				if ( is_wp_error( $render ) ) {
					return 'exam_render_source' === $render->get_error_code() ? self::unavailable() : new \WP_Error( 'ohmylms_exam_import_render_failed', __( 'The page could not be rendered.', 'ohmylms' ), array( 'status' => 503 ) );
				}
				return self::store( $path, $render['bytes'] );
			},
			static function () {
				return new \WP_Error( 'exam_busy', __( 'The page is still rendering. Retry shortly.', 'ohmylms' ), array( 'status' => 409 ) );
			}
		);
	}

	/**
	 * Private cache path of a page image under the current renderer contract.
	 *
	 * @param array $document Document metadata.
	 * @param int   $page     Page.
	 * @return string|\WP_Error
	 */
	private static function cache_path( array $document, $page ) {
		$root = Documents::root();
		if ( is_wp_error( $root ) || ! wp_is_uuid( (string) $document['id'] ) ) {
			return is_wp_error( $root ) ? $root : self::unavailable();
		}
		$contract = substr( md5( PageRenderer::contract() . ':' . PageRenderer::MAX_SIDE . ':' . $document['sha256'] ), 0, 12 );
		return $root . DIRECTORY_SEPARATOR . 'page-' . $document['id'] . '-' . (int) $page . '-' . $contract . '.png';
	}

	/**
	 * Whether a cached page is a complete PNG inside private storage.
	 *
	 * @param string $path Cache path.
	 * @return bool
	 */
	private static function cached( $path ) {
		clearstatcache( true, $path );
		if ( is_link( $path ) || ! is_file( $path ) || filesize( $path ) > PageRenderer::MAX_BYTES ) {
			return false;
		}
		$bytes = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Bounded private raster.
		return PageRenderer::valid_png( $bytes );
	}

	/**
	 * Write a rendered page through a temporary file, so a cached page is never partial.
	 *
	 * @param string $path  Cache path.
	 * @param string $bytes Verified PNG.
	 * @return string|\WP_Error Path.
	 */
	private static function store( $path, $bytes ) {
		$temporary = $path . '.' . wp_generate_uuid4() . '.part';
		$handle    = fopen( $temporary, 'x+b' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Exclusive private creation.
		if ( false === $handle ) {
			return self::storage_error();
		}
		chmod( $temporary, 0600 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Private raster permissions.
		$written = fwrite( $handle, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Bounded verified raster bytes.
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close before the rename on Windows.
		if ( strlen( $bytes ) !== $written ) {
			wp_delete_file( $temporary );
			return self::storage_error();
		}
		if ( is_file( $path ) ) {
			wp_delete_file( $path );
		}
		if ( ! rename( $temporary, $path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Atomic move inside private storage.
			wp_delete_file( $temporary );
			return self::storage_error();
		}
		return $path;
	}

	/**
	 * Remove a job's cached pages with its sources.
	 *
	 * @param array $job Job state.
	 * @return void
	 */
	public static function forget( array $job ) {
		$root = Documents::root();
		$id   = (string) ( $job['document']['id'] ?? '' );
		if ( is_wp_error( $root ) || ! wp_is_uuid( $id ) ) {
			return;
		}
		foreach ( (array) glob( $root . DIRECTORY_SEPARATOR . 'page-' . $id . '-*.png' ) as $file ) {
			if ( is_string( $file ) && ! is_link( $file ) ) {
				wp_delete_file( $file );
			}
		}
	}

	/**
	 * Source missing or changed since upload.
	 *
	 * @return \WP_Error
	 */
	private static function unavailable() {
		return new \WP_Error( 'ohmylms_exam_import_source_unavailable', __( 'The source PDF is missing or changed since upload.', 'ohmylms' ), array( 'status' => 409 ) );
	}

	/**
	 * Private storage failure.
	 *
	 * @return \WP_Error
	 */
	private static function storage_error() {
		return new \WP_Error( 'ohmylms_exam_import_storage', __( 'The page image could not be stored.', 'ohmylms' ), array( 'status' => 503 ) );
	}
}
