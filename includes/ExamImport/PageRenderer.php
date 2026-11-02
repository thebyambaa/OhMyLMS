<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Bounded local PDF rendering into private raster crops.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\ExamImport;

defined( 'ABSPATH' ) || exit;

/**
 * Renders with an administrator-configured Poppler binary, without a shell, or, when Poppler is not
 * usable, with Imagick reading PDF through Ghostscript. Both produce the same raster: the rotated
 * CropBox, its longer side {@see self::MAX_SIDE} pixels. The `ohmylms_exam_import_renderers` filter
 * orders or removes the engines (`poppler`, `imagick`).
 */
final class PageRenderer {

	const VERSION         = 'poppler-crop-1';
	const IMAGICK_VERSION = 'imagick-crop-1';
	const MAX_SIDE        = 2400;
	const MAX_BYTES       = 10485760;
	const TIMEOUT         = 20;

	/** Smallest side of a crop, as a fraction of the page. */
	const MIN_SIDE = 0.005;

	/**
	 * Renderer status per engine list, probed once per request.
	 *
	 * @var array<string, array>
	 */
	private static $statuses = array();

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
	 * Validate the bounds of a crop: {@see self::bounds()}, each side at least {@see self::MIN_SIDE}.
	 *
	 * @param mixed $bounds Left, top, right, bottom.
	 * @return array|\WP_Error Canonical bounds.
	 */
	public static function crop_bounds( $bounds ) {
		$bounds = self::bounds( $bounds );
		if ( is_wp_error( $bounds ) ) {
			return $bounds;
		}
		return round( $bounds[2] - $bounds[0], 6 ) >= self::MIN_SIDE && round( $bounds[3] - $bounds[1], 6 ) >= self::MIN_SIDE ? $bounds : self::error( 'bounds' );
	}

	/**
	 * Pixel rectangle of normalized bounds on a page raster, inside it and at least a pixel each way.
	 *
	 * @param array $bounds Canonical bounds.
	 * @param array $size   Raster width and height.
	 * @return array|\WP_Error Left, top, width, height.
	 */
	private static function pixels( array $bounds, array $size ) {
		$left   = (int) floor( $bounds[0] * $size[0] );
		$top    = (int) floor( $bounds[1] * $size[1] );
		$width  = (int) ceil( $bounds[2] * $size[0] ) - $left;
		$height = (int) ceil( $bounds[3] * $size[1] ) - $top;
		$inside = $left >= 0 && $top >= 0 && $width > 0 && $height > 0 && $left + $width <= $size[0] && $top + $height <= $size[1];
		return $inside ? array( $left, $top, $width, $height ) : self::error( 'bounds' );
	}

	/**
	 * Whether stored pixel bounds lie inside the page raster they were cut from.
	 *
	 * @param mixed $pixel_bounds Left, top, right, bottom in pixels.
	 * @param mixed $width        Page raster width.
	 * @param mixed $height       Page raster height.
	 * @return bool
	 */
	public static function inside( $pixel_bounds, $width, $height ) {
		if ( ! is_array( $pixel_bounds ) || 4 !== count( $pixel_bounds ) || ! is_int( $width ) || ! is_int( $height ) ) {
			return false;
		}
		list( $left, $top, $right, $bottom ) = array_map( 'intval', array_values( $pixel_bounds ) );
		return $left >= 0 && $top >= 0 && $left < $right && $top < $bottom && $right <= $width && $bottom <= $height;
	}

	/**
	 * Resolve the configured binary without searching a request-controlled PATH.
	 *
	 * @return string|\WP_Error Executable path.
	 */
	private static function executable() {
		$configured = defined( 'OHMYLMS_PDFTOPPM_BINARY' ) ? OHMYLMS_PDFTOPPM_BINARY : '';
		if ( ! self::processes_enabled() ) {
			return self::error( 'unavailable' );
		}
		if ( ! is_string( $configured ) || ! preg_match( '~^(?:[a-zA-Z]:[\\\\/]|/)~', $configured ) ) {
			return self::error( 'unavailable' );
		}
		$binary = realpath( $configured );
		return $binary && is_file( $binary ) && is_executable( $binary ) ? $binary : self::error( 'unavailable' );
	}

	/**
	 * Whether PHP may start, watch and stop a renderer process.
	 *
	 * @return bool
	 */
	private static function processes_enabled() {
		$disabled = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );
		foreach ( array( 'proc_open', 'proc_get_status', 'proc_terminate', 'proc_close' ) as $function ) {
			if ( ! function_exists( $function ) || in_array( $function, $disabled, true ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Renderer status for authors, never an error: the engine in use, or what is missing when pages
	 * cannot be rendered. Probed once per request and engine list.
	 *
	 * `engine` is `poppler` or `imagick` (the fallback). `reason`, when nothing renders, and
	 * `poppler_reason`, why Poppler is not the engine, are `not_configured` (no OHMYLMS_PDFTOPPM_BINARY),
	 * `process_disabled` (proc_* functions disabled), `binary_unusable` (not an absolute path to an
	 * executable file), `storage` (no private import directory) or `failed` (the program did not answer
	 * as pdftoppm). `imagick` reports an installed Imagick that lists PDF; it renders only when it also
	 * reads a test page through Ghostscript.
	 *
	 * @return array Status without paths or process output.
	 */
	public static function status() {
		$engines = (array) apply_filters( 'ohmylms_exam_import_renderers', array( 'poppler', 'imagick' ) );
		$engines = array_values( array_intersect( $engines, array( 'poppler', 'imagick' ) ) );
		$key     = implode( ',', $engines );
		if ( ! isset( self::$statuses[ $key ] ) ) {
			self::$statuses[ $key ] = self::probe( $engines );
		}
		return self::$statuses[ $key ];
	}

	/**
	 * Choose the first usable engine.
	 *
	 * @param string[] $engines Engines in order of preference.
	 * @return array Status.
	 */
	private static function probe( array $engines ) {
		$status  = array(
			'available'        => false,
			'engine'           => '',
			'version'          => '',
			'reason'           => '',
			'poppler_reason'   => '',
			'contract'         => self::VERSION,
			'max_side'         => self::MAX_SIDE,
			'timeout_seconds'  => self::TIMEOUT,
			'coordinate_space' => 'rotated-cropbox-normalized-top-left',
			'imagick'          => self::imagick_pdf(),
		);
		$poppler = in_array( 'poppler', $engines, true ) ? self::poppler() : array( 'reason' => 'not_configured' );

		$status['poppler_reason'] = $poppler['reason'];
		foreach ( $engines as $engine ) {
			$found = 'poppler' === $engine ? $poppler : self::imagick();
			if ( '' === $found['reason'] ) {
				return array_merge(
					$status,
					array(
						'available' => true,
						'engine'    => $engine,
						'version'   => $found['version'],
						'contract'  => 'poppler' === $engine ? self::VERSION : self::IMAGICK_VERSION,
					)
				);
			}
		}
		$status['reason'] = $poppler['reason'];
		return $status;
	}

	/**
	 * Whether the configured Poppler renders.
	 *
	 * @return array `reason` ('' when usable) and `version`.
	 */
	private static function poppler() {
		$configured = defined( 'OHMYLMS_PDFTOPPM_BINARY' ) ? OHMYLMS_PDFTOPPM_BINARY : '';
		if ( ! is_string( $configured ) || '' === trim( $configured ) ) {
			return array( 'reason' => 'not_configured' );
		}
		if ( ! self::processes_enabled() ) {
			return array( 'reason' => 'process_disabled' );
		}
		if ( is_wp_error( self::executable() ) ) {
			return array( 'reason' => 'binary_unusable' );
		}
		if ( is_wp_error( Documents::root() ) ) {
			return array( 'reason' => 'storage' );
		}
		$capability = self::capability();
		return is_wp_error( $capability ) ? array( 'reason' => 'failed' ) : array(
			'reason'  => '',
			'version' => $capability['poppler_version'],
		);
	}

	/**
	 * Whether Imagick renders: it lists PDF, private storage exists and a test page is read through
	 * Ghostscript (an ImageMagick security policy may still forbid PDF).
	 *
	 * @return array `reason` ('' when usable) and `version`.
	 */
	private static function imagick() {
		if ( ! self::imagick_pdf() ) {
			return array( 'reason' => 'not_configured' );
		}
		if ( is_wp_error( Documents::root() ) ) {
			return array( 'reason' => 'storage' );
		}
		try {
			$image = new \Imagick();
			$image->setResolution( 72, 72 );
			$image->readImageBlob( self::test_pdf() );
			$read = 10 === $image->getImageWidth() && 10 === $image->getImageHeight();
			$image->clear();
		} catch ( \Exception $exception ) {
			$read = false;
		}
		$version = \Imagick::getVersion();
		return $read && preg_match( '/ImageMagick ([0-9][0-9.\-]*)/', (string) ( $version['versionString'] ?? '' ), $matches ) ? array(
			'reason'  => '',
			'version' => $matches[1],
		) : array( 'reason' => 'failed' );
	}

	/**
	 * A blank one-page PDF of 10 by 10 points.
	 *
	 * @return string PDF bytes.
	 */
	private static function test_pdf() {
		$objects = array(
			'<< /Type /Catalog /Pages 2 0 R >>',
			'<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
			'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 10 10] >>',
		);
		$pdf     = "%PDF-1.4\n";
		$offsets = '';
		foreach ( $objects as $index => $object ) {
			$offsets .= sprintf( "%010d 00000 n \n", strlen( $pdf ) );
			$pdf     .= ( $index + 1 ) . " 0 obj\n" . $object . "\nendobj\n";
		}
		return $pdf . "xref\n0 4\n0000000000 65535 f \n" . $offsets . "trailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n" . strlen( $pdf ) . "\n%%EOF\n";
	}

	/**
	 * Raster contract of the engine in use: cached pages and crop requests are keyed on it.
	 *
	 * @return string
	 */
	public static function contract() {
		return self::status()['contract'];
	}

	/**
	 * Whether Imagick is loaded and can read PDF (through Ghostscript).
	 *
	 * @return bool
	 */
	private static function imagick_pdf() {
		if ( ! extension_loaded( 'imagick' ) || ! class_exists( '\Imagick' ) ) {
			return false;
		}
		try {
			return in_array( 'PDF', \Imagick::queryFormats( 'PDF' ), true );
		} catch ( \Exception $exception ) {
			return false;
		}
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
		$source = self::source( $document );
		if ( is_wp_error( $source ) ) {
			return $source;
		}
		$deadline = microtime( true ) + self::TIMEOUT;
		if ( 'imagick' === self::status()['engine'] ) {
			return self::imagick_crop( $source, $page, $bounds, $deadline );
		}
		$args = array( '-png', '-singlefile', '-cropbox', '-scale-to', (string) self::MAX_SIDE, '-f', (string) $page, '-l', (string) $page );
		$full = self::run( array_merge( $args, array( $source ) ), $deadline );
		if ( is_wp_error( $full ) ) {
			return $full;
		}
		$size = self::png_size( $full['output'] );
		if ( ! $size ) {
			return self::error( 'output' );
		}
		unset( $full );
		$box = self::pixels( $bounds, $size );
		if ( is_wp_error( $box ) ) {
			return $box;
		}
		$result = self::run( array_merge( $args, array( '-x', (string) $box[0], '-y', (string) $box[1], '-W', (string) $box[2], '-H', (string) $box[3], $source ) ), $deadline );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return self::cropped( $result['output'], $size, $box, $bounds, self::VERSION );
	}

	/**
	 * Check a cropped PNG against the rectangle it was cut to.
	 *
	 * @param string $bytes    Crop PNG.
	 * @param array  $size     Page raster width and height.
	 * @param array  $box      Left, top, width, height.
	 * @param array  $bounds   Normalized bounds.
	 * @param string $contract Engine contract.
	 * @return array|\WP_Error Crop and its provenance.
	 */
	private static function cropped( $bytes, array $size, array $box, array $bounds, $contract ) {
		$crop_size = self::png_size( $bytes );
		if ( ! $crop_size || $box[2] !== $crop_size[0] || $box[3] !== $crop_size[1] ) {
			return self::error( 'output' );
		}
		return array(
			'bytes'        => $bytes,
			'width'        => $box[2],
			'height'       => $box[3],
			'page_width'   => $size[0],
			'page_height'  => $size[1],
			'pixel_bounds' => array( $box[0], $box[1], $box[0] + $box[2], $box[1] + $box[3] ),
			'bounds'       => $bounds,
			'engine'       => $contract,
		) + self::physical_size( $bytes, $box[2], $box[3] );
	}

	/**
	 * Crop with Imagick from the same page raster {@see self::imagick_page()} shows.
	 *
	 * @param string $source   Verified PDF path.
	 * @param int    $page     One-based page.
	 * @param array  $bounds   Canonical bounds.
	 * @param float  $deadline Absolute deadline.
	 * @return array|\WP_Error Crop and its provenance.
	 */
	private static function imagick_crop( $source, $page, array $bounds, $deadline ) {
		$raster = self::imagick_raster( $source, $page, $deadline );
		if ( is_wp_error( $raster ) ) {
			return $raster;
		}
		$image = $raster['image'];
		$size  = array( $image->getImageWidth(), $image->getImageHeight() );
		$box   = self::pixels( $bounds, $size );
		if ( is_wp_error( $box ) ) {
			$image->clear();
			return $box;
		}
		try {
			$image->cropImage( $box[2], $box[3], $box[0], $box[1] );
			$image->setImagePage( 0, 0, 0, 0 );
			$bytes = self::imagick_png( $image, $raster['density'] );
		} catch ( \Exception $exception ) {
			return self::error( 'failed' );
		}
		return self::cropped( $bytes, $size, $box, $bounds, self::IMAGICK_VERSION );
	}

	/**
	 * Render a page with Imagick (Ghostscript) to Poppler's `-cropbox -scale-to` raster: the rotated
	 * CropBox, its longer side {@see self::MAX_SIDE} pixels. Imagick runs inside PHP, so the deadline is
	 * checked between steps and ImageMagick's own time limit bounds each read.
	 *
	 * @param string $source   Verified PDF path.
	 * @param int    $page     One-based page.
	 * @param float  $deadline Absolute deadline.
	 * @return array|\WP_Error `image` (flattened on white) and `density` (pixels per inch).
	 */
	private static function imagick_raster( $source, $page, $deadline ) {
		if ( ! self::imagick_pdf() ) {
			return self::error( 'unavailable' );
		}
		try {
			\Imagick::setResourceLimit( \Imagick::RESOURCETYPE_TIME, self::TIMEOUT );
			$probe  = self::imagick_read( $source, $page, 72 );
			$points = array( $probe->getImageWidth(), $probe->getImageHeight() );
			$probe->clear();
			if ( microtime( true ) >= $deadline || min( $points ) < 1 ) {
				return self::error( 'limit' );
			}
			$image  = self::imagick_read( $source, $page, 72 * self::MAX_SIDE / max( $points ) );
			$scale  = self::MAX_SIDE / max( $image->getImageWidth(), $image->getImageHeight() );
			$width  = max( 1, (int) round( $image->getImageWidth() * $scale ) );
			$height = max( 1, (int) round( $image->getImageHeight() * $scale ) );
			if ( $width !== $image->getImageWidth() || $height !== $image->getImageHeight() ) {
				$image->resizeImage( $width, $height, \Imagick::FILTER_LANCZOS, 1 );
			}
		} catch ( \Exception $exception ) {
			return self::error( 'failed' );
		}
		if ( microtime( true ) >= $deadline ) {
			$image->clear();
			return self::error( 'limit' );
		}
		return array(
			'image'   => $image,
			'density' => 72 * $width / $points[0],
		);
	}

	/**
	 * Read one page of a PDF at a resolution, flattened on white.
	 *
	 * @param string $source  Verified PDF path.
	 * @param int    $page    One-based page.
	 * @param float  $density Pixels per inch.
	 * @return \Imagick
	 * @throws \ImagickException When Ghostscript cannot read the page.
	 */
	private static function imagick_read( $source, $page, $density ) {
		$image = new \Imagick();
		$image->setResolution( $density, $density );
		$image->setOption( 'pdf:use-cropbox', 'true' );
		$image->readImage( 'pdf:' . $source . '[' . ( $page - 1 ) . ']' );
		$image->setImageBackgroundColor( 'white' );
		$flat = $image->mergeImageLayers( \Imagick::LAYERMETHOD_FLATTEN );
		$image->clear();
		return $flat;
	}

	/**
	 * Encode a raster as PNG with its physical resolution and without timestamps, so a repeated render
	 * gives the same bytes.
	 *
	 * @param \Imagick $image   Raster; cleared afterwards.
	 * @param float    $density Pixels per inch.
	 * @return string PNG bytes.
	 */
	private static function imagick_png( \Imagick $image, $density ) {
		$image->setImageFormat( 'png' );
		$image->setImageUnits( \Imagick::RESOLUTION_PIXELSPERINCH );
		$image->setImageResolution( $density, $density );
		$image->setOption( 'png:exclude-chunks', 'date,time' );
		$bytes = $image->getImageBlob();
		$image->clear();
		return $bytes;
	}

	/**
	 * Render one whole page with the same arguments as a crop, so normalized bounds drawn on it are
	 * the bounds {@see self::crop()} cuts.
	 *
	 * @param array $document Server-owned source metadata.
	 * @param int   $page One-based PDF page.
	 * @return array|\WP_Error PNG bytes, raster size and engine.
	 */
	public static function page( array $document, $page ) {
		if ( ! is_int( $page ) || $page < 1 || $page > 500 ) {
			return self::error( 'bounds' );
		}
		$source = self::source( $document );
		if ( is_wp_error( $source ) ) {
			return $source;
		}
		if ( 'imagick' === self::status()['engine'] ) {
			return self::imagick_page( $source, $page );
		}
		$args   = array( '-png', '-singlefile', '-cropbox', '-scale-to', (string) self::MAX_SIDE, '-f', (string) $page, '-l', (string) $page, $source );
		$result = self::run( $args, microtime( true ) + self::TIMEOUT );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$size = self::png_size( $result['output'] );
		if ( ! $size ) {
			return self::error( 'output' );
		}
		return array(
			'bytes'  => $result['output'],
			'width'  => $size[0],
			'height' => $size[1],
			'engine' => self::VERSION,
		);
	}

	/**
	 * One whole page rendered with Imagick.
	 *
	 * @param string $source Verified PDF path.
	 * @param int    $page   One-based page.
	 * @return array|\WP_Error PNG bytes, raster size and engine.
	 */
	private static function imagick_page( $source, $page ) {
		$raster = self::imagick_raster( $source, $page, microtime( true ) + self::TIMEOUT );
		if ( is_wp_error( $raster ) ) {
			return $raster;
		}
		try {
			$bytes = self::imagick_png( $raster['image'], $raster['density'] );
		} catch ( \Exception $exception ) {
			return self::error( 'failed' );
		}
		$size = self::png_size( $bytes );
		return $size ? array(
			'bytes'  => $bytes,
			'width'  => $size[0],
			'height' => $size[1],
			'engine' => self::IMAGICK_VERSION,
		) : self::error( 'output' );
	}

	/**
	 * Verify a private source against its recorded checksum.
	 *
	 * @param array $document Server-owned source metadata.
	 * @return string|\WP_Error Local path, never returned to clients.
	 */
	private static function source( array $document ) {
		$source = Documents::path( $document['id'] ?? '' );
		if ( is_wp_error( $source ) || empty( $document['sha256'] ) || filesize( $source ) > Documents::MAX_BYTES || ! hash_equals( $document['sha256'], (string) hash_file( 'sha256', $source ) ) ) {
			return self::error( 'source' );
		}
		return $source;
	}

	/**
	 * Inspect a stored raster the same way as renderer output.
	 *
	 * @param string $bytes PNG bytes.
	 * @return bool Bounded, well-formed PNG.
	 */
	public static function valid_png( $bytes ) {
		return is_string( $bytes ) && false !== self::png_size( $bytes );
	}

	/**
	 * Preserve Poppler's PNG physical resolution for scale-sensitive printed crops.
	 *
	 * @param string $bytes Bounded verified PNG.
	 * @param int    $width Raster width.
	 * @param int    $height Raster height.
	 * @return array Physical crop dimensions when the renderer supplies them.
	 */
	private static function physical_size( $bytes, $width, $height ) {
		$offset = 8;
		$length = strlen( $bytes );
		while ( $offset + 12 <= $length ) {
			$size = unpack( 'Nsize', substr( $bytes, $offset, 4 ) )['size'];
			if ( $size > $length - $offset - 12 ) {
				break;
			}
			if ( 9 === $size && 'pHYs' === substr( $bytes, $offset + 4, 4 ) ) {
				$density = unpack( 'Nx/Ny/Cunit', substr( $bytes, $offset + 8, 9 ) );
				if ( 1 === $density['unit'] && $density['x'] > 0 && $density['y'] > 0 ) {
					return array(
						'width_mm'  => round( 1000 * $width / $density['x'], 3 ),
						'height_mm' => round( 1000 * $height / $density['y'], 3 ),
					);
				}
			}
			$offset += $size + 12;
		}
		return array();
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
