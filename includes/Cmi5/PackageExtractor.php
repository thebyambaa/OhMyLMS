<?php
/**
 * Safe extraction of a cmi5 package ZIP.
 *
 * @package OhMyLMS\Cmi5
 */

namespace OhMyLMS\Cmi5;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- The DOMDocument and ZipArchive classes define these property names.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- InvalidPackage messages are plain text; whatever displays them escapes them.

/**
 * Unpacks a package into a folder that will be served to learners, so every entry is checked first.
 *
 * Unlike the SCORM importer, which deletes what it extracts, cmi5 keeps the files web-accessible, so
 * a hostile ZIP is a real risk. Entries are rejected, not silently skipped, when their path leaves
 * the folder (zip slip), when they are symlinks or special files, when their type is not on an
 * allow-list (a .php file must never land in a served folder), or when the archive is larger than
 * the limits allow. Files are copied through a counted stream rather than extractTo(), so a ZIP that
 * lies about an entry's size cannot write more than the limit.
 */
final class PackageExtractor {

	/**
	 * Parts of a file name that must never appear in a served package, even before the final extension
	 * (some servers run "x.php.png").
	 */
	const EXECUTABLE_PARTS = array( 'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht', 'phps', 'pl', 'py', 'cgi', 'asp', 'aspx', 'jsp', 'jspx', 'sh', 'bash', 'exe', 'dll', 'bat', 'cmd', 'htaccess', 'htpasswd' );

	/**
	 * Junk that archivers add; skipped without complaint.
	 */
	const IGNORABLE = array( '.ds_store', 'thumbs.db', 'desktop.ini' );

	/**
	 * File types a package may contain.
	 *
	 * @return string[]
	 */
	public static function allowed_extensions() {
		$extensions = array(
			'html',
			'htm',
			'xhtml',
			'js',
			'mjs',
			'css',
			'json',
			'xml',
			'txt',
			'csv',
			'map',
			'png',
			'jpg',
			'jpeg',
			'gif',
			'webp',
			'avif',
			'svg',
			'ico',
			'bmp',
			'mp3',
			'wav',
			'ogg',
			'oga',
			'm4a',
			'aac',
			'mp4',
			'm4v',
			'webm',
			'ogv',
			'vtt',
			'srt',
			'woff',
			'woff2',
			'ttf',
			'otf',
			'eot',
			'wasm',
			'pdf',
		);

		return function_exists( 'apply_filters' ) ? (array) apply_filters( 'ohmylms_cmi5_allowed_extensions', $extensions ) : $extensions;
	}

	/**
	 * Size and count limits.
	 *
	 * @return array files, file_bytes, total_bytes, ratio.
	 */
	public static function limits() {
		$limits = array(
			'files'       => 20000,
			'file_bytes'  => 256 * 1048576,
			'total_bytes' => 1024 * 1048576,
			'ratio'       => 200,
		);

		return function_exists( 'apply_filters' ) ? array_merge( $limits, (array) apply_filters( 'ohmylms_cmi5_package_limits', $limits ) ) : $limits;
	}

	/**
	 * Clean an entry name, or refuse it.
	 *
	 * @param string $name Name as stored in the ZIP.
	 * @return string|false A relative path with forward slashes, or false when it is unsafe.
	 */
	public static function normalize_entry( $name ) {
		$name = str_replace( '\\', '/', (string) $name );
		if ( '' === $name || strlen( $name ) > 240 || preg_match( '/[\x00-\x1f\x7f:]/', $name ) ) {
			return false;
		}
		if ( 0 === strpos( $name, '/' ) || 1 === preg_match( '~^[A-Za-z]:~', $name ) ) {
			return false;
		}
		$clean = array();
		foreach ( explode( '/', $name ) as $segment ) {
			if ( '' === $segment || '.' === $segment ) {
				continue;
			}
			// "..", dotfiles, and names Windows quietly rewrites ("evil.php." or "evil.php ") are all refused.
			$dotfile = '.' === $segment[0] && ! self::is_ignorable_name( $segment );
			if ( '..' === $segment || $dotfile || '.' === substr( $segment, -1 ) || ' ' === substr( $segment, -1 ) ) {
				return false;
			}
			$clean[] = $segment;
		}
		if ( ! $clean || count( $clean ) > 30 ) {
			return false;
		}

		return implode( '/', $clean );
	}

	/**
	 * Whether an entry is archiver junk that can be skipped.
	 *
	 * @param string $path Normalized path.
	 * @return bool
	 */
	public static function is_ignorable( $path ) {
		$parts = explode( '/', $path );

		return '__MACOSX' === $parts[0] || self::is_ignorable_name( end( $parts ) );
	}

	/**
	 * Whether a single file name is archiver junk.
	 *
	 * @param string $name File name.
	 * @return bool
	 */
	private static function is_ignorable_name( $name ) {
		return in_array( strtolower( $name ), self::IGNORABLE, true ) || 0 === strpos( $name, '._' );
	}

	/**
	 * Whether a path may be stored: an allowed final extension and nothing executable before it.
	 *
	 * @param string $path Normalized path.
	 * @return bool
	 */
	public static function is_allowed_type( $path ) {
		$file  = strtolower( (string) basename( $path ) );
		$parts = explode( '.', $file );
		if ( count( $parts ) < 2 ) {
			return false;
		}
		$extension = array_pop( $parts );
		foreach ( $parts as $part ) {
			if ( in_array( $part, self::EXECUTABLE_PARTS, true ) ) {
				return false;
			}
		}

		return in_array( $extension, self::allowed_extensions(), true );
	}

	/**
	 * Check a ZIP and unpack it.
	 *
	 * @param string $zip_path Uploaded ZIP file.
	 * @param string $dest_dir Folder to create and fill. It must not exist yet.
	 * @param array  $limits   Overrides for limits(), mainly for tests.
	 * @return array files (relative path => bytes), bytes (total), structure (contents of cmi5.xml).
	 * @throws InvalidPackage When the ZIP is unsafe, too large or has no cmi5.xml at its root.
	 */
	public static function extract( $zip_path, $dest_dir, array $limits = array() ) {
		if ( ! class_exists( '\ZipArchive' ) ) {
			throw new InvalidPackage( array( 'The PHP ZIP extension is not available on this server.' ) );
		}
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $zip_path ) ) {
			throw new InvalidPackage( array( 'The file could not be opened as a ZIP archive.' ) );
		}
		$limits  = array_merge( self::limits(), $limits );
		$plan    = array();
		$errors  = array();
		$declare = 0;
		if ( $zip->numFiles > $limits['files'] ) {
			$zip->close();
			throw new InvalidPackage( array( 'The ZIP has more than ' . $limits['files'] . ' entries.' ) );
		}
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$stat = $zip->statIndex( $i );
			if ( ! $stat ) {
				$errors[] = 'An entry in the ZIP could not be read.';
				continue;
			}
			$raw = (string) $stat['name'];
			if ( '/' === substr( $raw, -1 ) || '\\' === substr( $raw, -1 ) ) {
				continue; // A folder entry; folders are created from file paths.
			}
			$path = self::normalize_entry( $raw );
			if ( false === $path ) {
				$errors[] = 'The ZIP entry "' . self::printable( $raw ) . '" has an unsafe path.';
				continue;
			}
			if ( self::is_ignorable( $path ) ) {
				continue;
			}
			if ( $zip->getExternalAttributesIndex( $i, $opsys, $attributes ) && \ZipArchive::OPSYS_UNIX === $opsys ) {
				$type = ( $attributes >> 16 ) & 0xF000;
				if ( 0 !== $type && 0x8000 !== $type ) {
					$errors[] = 'The ZIP entry "' . $path . '" is a link or special file.';
					continue;
				}
			}
			if ( ! self::is_allowed_type( $path ) ) {
				$errors[] = 'The file "' . $path . '" has a type that packages may not contain.';
				continue;
			}
			if ( isset( $plan[ $path ] ) ) {
				$errors[] = 'The ZIP contains "' . $path . '" more than once.';
				continue;
			}
			$size = (int) $stat['size'];
			if ( $size > $limits['file_bytes'] ) {
				$errors[] = 'The file "' . $path . '" is larger than ' . (int) ( $limits['file_bytes'] / 1048576 ) . ' MB.';
				continue;
			}
			if ( $size > 1048576 && (int) $stat['comp_size'] > 0 && $size / (int) $stat['comp_size'] > $limits['ratio'] ) {
				$errors[] = 'The file "' . $path . '" is compressed suspiciously well and was refused.';
				continue;
			}
			$declare += $size;

			$plan[ $path ] = array(
				'index' => $i,
				'size'  => $size,
			);
		}
		if ( $declare > $limits['total_bytes'] ) {
			$errors[] = 'The package is larger than ' . (int) ( $limits['total_bytes'] / 1048576 ) . ' MB uncompressed.';
		}
		if ( ! isset( $plan['cmi5.xml'] ) ) {
			$errors[] = 'cmi5.xml was not found at the root of the ZIP.';
		}
		if ( $errors ) {
			$zip->close();
			throw new InvalidPackage( $errors );
		}

		// Refuse an existing folder before anything can clean up: cleanup must only ever remove a folder created here.
		if ( file_exists( $dest_dir ) ) {
			$zip->close();
			throw new InvalidPackage( array( 'The package folder already exists.' ) );
		}
		if ( ! mkdir( $dest_dir, 0755, true ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- A fresh, unguessable package folder must be created or the import stops.
			$zip->close();
			throw new InvalidPackage( array( 'The package folder could not be created.' ) );
		}
		$files = array();
		$total = 0;
		try {
			foreach ( $plan as $path => $entry ) {
				$written        = self::write_entry( $zip, $entry['index'], $dest_dir . '/' . $path, $limits['file_bytes'], $limits['total_bytes'] - $total );
				$total         += $written;
				$files[ $path ] = $written;
			}
		} catch ( \Throwable $error ) {
			$zip->close();
			self::remove( $dest_dir );
			throw new InvalidPackage( $error instanceof InvalidPackage ? $error->errors() : array( 'The package could not be unpacked.' ) );
		}
		$zip->close();

		return array(
			'files'     => $files,
			'bytes'     => $total,
			'structure' => (string) file_get_contents( $dest_dir . '/cmi5.xml' ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local file the extractor just wrote.
		);
	}

	/**
	 * Copy one entry to disk through a counted stream.
	 *
	 * @param \ZipArchive $zip       Open archive.
	 * @param int         $index     Entry index.
	 * @param string      $target    Destination file.
	 * @param int         $max_file  Largest allowed file, in bytes.
	 * @param int         $max_total Bytes still allowed for the whole package.
	 * @return int Bytes written.
	 * @throws InvalidPackage When the entry is larger than it claimed or than the limits allow.
	 */
	private static function write_entry( \ZipArchive $zip, $index, $target, $max_file, $max_total ) {
		$dir = dirname( $target );
		if ( ! is_dir( $dir ) && ! mkdir( $dir, 0755, true ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Creating package subfolders.
			throw new InvalidPackage( array( 'A package folder could not be created.' ) );
		}
		$in = $zip->getStreamIndex( $index );
		if ( ! $in ) {
			throw new InvalidPackage( array( 'The ZIP entry "' . self::printable( (string) $zip->getNameIndex( $index ) ) . '" could not be read (is it encrypted?).' ) );
		}
		$out     = fopen( $target, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Writing an extracted package file.
		$written = 0;
		$limit   = min( $max_file, $max_total );
		while ( ! feof( $in ) ) {
			$chunk = fread( $in, 65536 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Counted copy from the ZIP stream.
			if ( false === $chunk ) {
				break;
			}
			$written += strlen( $chunk );
			if ( $written > $limit ) {
				fclose( $in ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing the ZIP stream.
				fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing the output file.
				throw new InvalidPackage( array( 'The package is larger than its limits allow.' ) );
			}
			fwrite( $out, $chunk ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Counted copy to disk.
		}
		fclose( $in ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing the ZIP stream.
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing the output file.

		return $written;
	}

	/**
	 * Delete a folder and everything in it. Only ever called on a folder this class created.
	 *
	 * @param string $dir Folder.
	 */
	public static function remove( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $iterator as $item ) {
			if ( $item->isDir() && ! $item->isLink() ) {
				rmdir( $item->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Removing a half-extracted package.
			} else {
				unlink( $item->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Removing a half-extracted package.
			}
		}
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Removing the package folder.
	}

	/**
	 * An entry name safe to show in a message.
	 *
	 * @param string $name Raw name.
	 * @return string
	 */
	private static function printable( $name ) {
		return substr( preg_replace( '/[^\x20-\x7e]/', '?', $name ), 0, 80 );
	}
}
