<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Bounded local PDF rendering into private raster crops.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\ExamImport;

defined( 'ABSPATH' ) || exit;

/** Executes only an administrator-configured Poppler binary, without a shell. */
final class PageRenderer {

	const VERSION   = 'poppler-crop-1';
	const MAX_SIDE  = 2400;
	const MAX_BYTES = 10485760;
	const TIMEOUT   = 20;

	/**
	 * Validate normalized bounds on the rotated visible PDF page.
	 *
	 * @param mixed $bounds Left, top, right, bottom, each between zero and one.
	 * @return array|\WP_Error Canonical bounds.
	 */
	public static function bounds( $bounds ) {
		if ( ! is_array( $bounds ) || array_keys( $bounds ) !== array( 0, 1, 2, 3 ) ) {
			return self::error( 'bounds' );
		}
		foreach ( $bounds as &$value ) {
			if ( ! ( is_int( $value ) || is_float( $value ) ) || ! is_finite( (float) $value ) || $value < 0 || $value > 1 ) {
				return self::error( 'bounds' );
			}
			$value = round( (float) $value, 6 );
		}
		unset( $value );
		return $bounds[0] < $bounds[2] && $bounds[1] < $bounds[3] ? $bounds : self::error( 'bounds' );
	}

	/**
	 * Resolve the configured binary without searching a request-controlled PATH.
	 *
	 * @return string|\WP_Error Executable path.
	 */
	private static function executable() {
		$configured = defined( 'OHMYLMS_PDFTOPPM_BINARY' ) ? OHMYLMS_PDFTOPPM_BINARY : '';
		$disabled   = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );
		foreach ( array( 'proc_open', 'proc_get_status', 'proc_terminate', 'proc_close' ) as $function ) {
			if ( ! function_exists( $function ) || in_array( $function, $disabled, true ) ) {
				return self::error( 'unavailable' );
			}
		}
		if ( ! is_string( $configured ) || ! preg_match( '~^(?:[a-zA-Z]:[\\\\/]|/)~', $configured ) ) {
			return self::error( 'unavailable' );
		}
		$binary = realpath( $configured );
		return $binary && is_file( $binary ) && is_executable( $binary ) ? $binary : self::error( 'unavailable' );
	}

	/**
	 * Exercise the renderer using the calling PHP service account.
	 *
	 * @return array|\WP_Error Capability without paths or raw process output.
	 */
	public static function capability() {
		$result = self::run( array( '-v' ), microtime( true ) + self::TIMEOUT );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! preg_match( '/pdftoppm version ([0-9]+\.[0-9]+(?:\.[0-9]+)?)/', $result['output'] . $result['error'], $matches ) ) {
			return self::error( 'unavailable' );
		}
		return array(
			'available'        => true,
			'engine'           => self::VERSION,
			'poppler_version'  => $matches[1],
			'max_side'         => self::MAX_SIDE,
			'timeout_seconds'  => self::TIMEOUT,
			'coordinate_space' => 'rotated-cropbox-normalized-top-left',
		);
	}

	/**
	 * Render one page and crop using pixel bounds derived from its actual raster.
	 *
	 * @param array $document Server-owned source metadata.
	 * @param int   $page One-based PDF page.
	 * @param array $bounds Normalized left, top, right, bottom.
	 * @return array|\WP_Error PNG bytes and rendering provenance.
	 */
	public static function crop( array $document, $page, array $bounds ) {
		$bounds = self::bounds( $bounds );
		if ( is_wp_error( $bounds ) || ! is_int( $page ) || $page < 1 || $page > 500 ) {
			return self::error( 'bounds' );
		}
		$source = Documents::path( $document['id'] ?? '' );
		if ( is_wp_error( $source ) || empty( $document['sha256'] ) || filesize( $source ) > Documents::MAX_BYTES || ! hash_equals( $document['sha256'], (string) hash_file( 'sha256', $source ) ) ) {
			return self::error( 'source' );
		}
		$deadline = microtime( true ) + self::TIMEOUT;
		$args     = array( '-png', '-singlefile', '-cropbox', '-scale-to', (string) self::MAX_SIDE, '-f', (string) $page, '-l', (string) $page );
		$full     = self::run( array_merge( $args, array( $source ) ), $deadline );
		if ( is_wp_error( $full ) ) {
			return $full;
		}
		$size = self::png_size( $full['output'] );
		if ( ! $size ) {
			return self::error( 'output' );
		}
		$left   = (int) floor( $bounds[0] * $size[0] );
		$top    = (int) floor( $bounds[1] * $size[1] );
		$width  = (int) ceil( $bounds[2] * $size[0] ) - $left;
		$height = (int) ceil( $bounds[3] * $size[1] ) - $top;
		unset( $full );
		$result = self::run( array_merge( $args, array( '-x', (string) $left, '-y', (string) $top, '-W', (string) $width, '-H', (string) $height, $source ) ), $deadline );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$crop_size = self::png_size( $result['output'] );
		if ( ! $crop_size || $width !== $crop_size[0] || $height !== $crop_size[1] ) {
			return self::error( 'output' );
		}
		return array(
			'bytes'        => $result['output'],
			'width'        => $width,
			'height'       => $height,
			'page_width'   => $size[0],
			'page_height'  => $size[1],
			'pixel_bounds' => array( $left, $top, $left + $width, $top + $height ),
			'bounds'       => $bounds,
			'engine'       => self::VERSION,
		);
	}

	/**
	 * Inspect bounded PNG headers without requiring GD or Imagick.
	 *
	 * @param string $bytes Renderer output.
	 * @return array|false Image dimensions.
	 */
	private static function png_size( $bytes ) {
		if ( strlen( $bytes ) < 33 || strlen( $bytes ) > self::MAX_BYTES || "\x89PNG\r\n\x1a\n" !== substr( $bytes, 0, 8 ) || 'IHDR' !== substr( $bytes, 12, 4 ) ) {
			return false;
		}
		$size = getimagesizefromstring( $bytes );
		return $size && IMAGETYPE_PNG === $size[2] && $size[0] > 0 && $size[1] > 0 && $size[0] <= self::MAX_SIDE && $size[1] <= self::MAX_SIDE ? $size : false;
	}

	/**
	 * Run bounded argv with private output files, avoiding blocking Windows pipes.
	 *
	 * @param array $args Trusted options and server-resolved paths.
	 * @param float $deadline Absolute operation deadline.
	 * @return array|\WP_Error Bounded stdout and stderr.
	 */
	private static function run( array $args, $deadline ) {
		$binary = self::executable();
		$root   = Documents::root();
		if ( is_wp_error( $binary ) || is_wp_error( $root ) ) {
			return is_wp_error( $binary ) ? $binary : $root;
		}
		$directory = $root . DIRECTORY_SEPARATOR . 'render-' . wp_generate_uuid4();
		if ( ! mkdir( $directory, 0700 ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Exclusive private process workspace.
			return self::error( 'storage' );
		}
		$output  = $directory . '/output';
		$error   = $directory . '/error';
		$process = false;
		$handles = array();
		try {
			foreach ( array( $output, $error ) as $path ) {
				$handle = fopen( $path, 'x+b' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Exclusive creation inside private workspace.
				if ( false === $handle ) {
					return self::error( 'storage' );
				}
				$handles[] = $handle;
				chmod( $path, 0600 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Restrict raster and diagnostic files.
			}
			$descriptors = array(
				0 => array( 'pipe', 'r' ),
				1 => $handles[0],
				2 => $handles[1],
			);
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- Availability is checked; explicit administrator-configured argv bypasses the shell.
			$process = proc_open(
				array_merge( array( $binary ), $args ),
				$descriptors,
				$pipes,
				$directory,
				null,
				array(
					'bypass_shell'         => true,
					'create_process_group' => true,
				)
			);
			if ( ! is_resource( $process ) ) {
				return self::error( 'unavailable' );
			}
			fclose( $pipes[0] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Renderer accepts no stdin.
			do {
				clearstatcache( true, $output );
				clearstatcache( true, $error );
				if ( microtime( true ) >= $deadline || filesize( $output ) > self::MAX_BYTES || filesize( $error ) > 65536 ) {
					return self::error( 'limit' );
				}
				$status = proc_get_status( $process );
				if ( $status['running'] ) {
					usleep( 20000 );
				}
			} while ( $status['running'] );
			if ( 0 !== $status['exitcode'] ) {
				return self::error( 'failed' );
			}
			return array(
				'output' => file_get_contents( $output ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Bounded local process output.
				'error'  => file_get_contents( $error ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Bounded local diagnostics, never exposed to clients.
			);
		} finally {
			if ( is_resource( $process ) ) {
				proc_terminate( $process, 9 );
				proc_close( $process );
			}
			foreach ( $handles as $handle ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close private output before removal on Windows.
			}
			wp_delete_file( $output );
			wp_delete_file( $error );
			rmdir( $directory ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Remove only the empty server-created workspace.
		}
	}

	/**
	 * Return a safe error without paths, process output, or PDF content.
	 *
	 * @param string $code Failure category.
	 * @return \WP_Error Safe failure.
	 */
	private static function error( $code ) {
		return new \WP_Error( 'exam_render_' . $code, __( 'PDF rendering is unavailable or failed validation. Check the renderer configuration, page and crop bounds.', 'ohmylms' ), array( 'status' => 'bounds' === $code ? 400 : 503 ) );
	}
}
