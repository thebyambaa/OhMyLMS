<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class ScormHelper.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Utility;

defined( 'ABSPATH' ) || exit;

/**
 * Class ScormHelper
 *
 * Utility helpers shared between SCORM import/export features.
 */
class ScormHelper {

	/**
	 * Log SCORM activity.
	 *
	 * @param string $message Log message.
	 * @param string $level Log level (info, warning, error, debug).
	 * @param array  $context Additional context.
	 */
	public static function log( $message, $level = 'info', $context = array() ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$log_message = sprintf( '[SCORM] [%s] %s', strtoupper( $level ), $message );

			if ( ! empty( $context ) ) {
				$log_message .= ' | Context: ' . wp_json_encode( $context );
			}

			error_log( $log_message );
		}
	}

	/**
	 * Validate course for export for Scorm helper.
	 *
	 * @param mixed $course_id Course identifier.
	 */
	public static function validate_course_for_export( $course_id ) {
		$course = get_post( $course_id );

		// Check if course exists.
		if ( ! $course || OHMYLMS_COURSE_CPT !== $course->post_type ) {
			return array(
				'valid'   => false,
				'message' => sprintf( /* translators: %d: number. */
				__( 'Course ID %d does not exist or is not a valid course.', 'ohmylms' ), $course_id ),
			);
		}

		// Check if course is published or draft.
		if ( ! in_array( $course->post_status, array( 'publish', 'draft', 'pending' ) ) ) {
			return array(
				'valid'   => false,
				'message' => sprintf( /* translators: %1$s: text value; %2$s: text value. */
				__( 'Course "%1$s" has invalid status: %2$s', 'ohmylms' ), $course->post_title, $course->post_status ),
			);
		}

		// Check if course has content.
		global $wpdb;
		$chapter_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ohmylms_chapter_relationship WHERE course_id = %d",
				$course_id
			)
		);

		if ( 0 == $chapter_count ) {
			return array(
				'valid'   => false,
				'message' => sprintf( /* translators: %s: text value. */
				__( 'Course "%s" has no chapters. Please add content before exporting.', 'ohmylms' ), $course->post_title ),
			);
		}

		return array(
			'valid'   => true,
			'message' => __( 'Course is valid for export.', 'ohmylms' ),
		);
	}

	/**
	 * Check system requirements for Scorm helper.
	 *
	 * @param bool $strict_mode Strict mode.
	 */
	public static function check_system_requirements( $strict_mode = false ) {
		$results = array(
			'all_passed' => true,
			'checks'     => array(),
		);

		// Check ZIP extension (CRITICAL - always required).
		$results['checks']['zip'] = array(
			'name'     => __( 'PHP ZIP Extension', 'ohmylms' ),
			'passed'   => class_exists( 'ZipArchive' ),
			'message'  => class_exists( 'ZipArchive' )
				? __( 'Available', 'ohmylms' )
				: __( 'Not available. Please enable the ZIP extension.', 'ohmylms' ),
			'critical' => true,
		);

		// Check DOM extension (CRITICAL - always required).
		$results['checks']['dom'] = array(
			'name'     => __( 'PHP DOM Extension', 'ohmylms' ),
			'passed'   => class_exists( 'DOMDocument' ),
			'message'  => class_exists( 'DOMDocument' )
				? __( 'Available', 'ohmylms' )
				: __( 'Not available. Please enable the DOM extension.', 'ohmylms' ),
			'critical' => true,
		);

		// Check memory limit (WARNING - can be increased at runtime).
		$memory_limit       = ini_get( 'memory_limit' );
		$memory_limit_bytes = self::convert_to_bytes( $memory_limit );
		$memory_ok          = $memory_limit_bytes >= 256 * 1024 * 1024 || '-1' == $memory_limit;

		$results['checks']['memory'] = array(
			'name'     => __( 'PHP Memory Limit', 'ohmylms' ),
			'passed'   => $memory_ok,
			'message'  => $memory_ok
				? sprintf( /* translators: %s: text value. */
				__( 'Sufficient: %s', 'ohmylms' ), $memory_limit )
				: sprintf( /* translators: %s: text value. */
				__( 'Low: %s (recommended: 256M or higher)', 'ohmylms' ), $memory_limit ),
			'critical' => false, // Can be increased at runtime.
		);

		// Check max execution time (WARNING - can be increased at runtime).
		$max_execution = ini_get( 'max_execution_time' );
		$time_ok       = 300 <= $max_execution || 0 == $max_execution;

		$results['checks']['execution_time'] = array(
			'name'     => __( 'Max Execution Time', 'ohmylms' ),
			'passed'   => $time_ok,
			'message'  => $time_ok
				? sprintf( /* translators: %s: text value. */
				__( 'Sufficient: %s seconds', 'ohmylms' ), 0 == $max_execution ? 'unlimited' : $max_execution )
				: sprintf( /* translators: %s: text value. */
				__( 'Low: %s seconds (recommended: 300 or higher)', 'ohmylms' ), $max_execution ),
			'critical' => false, // Can be increased at runtime.
		);

		// Check upload directory writable (CRITICAL - always required).
		$upload_dir = wp_upload_dir();
		$writable   = wp_is_writable( $upload_dir['basedir'] );

		$results['checks']['writable'] = array(
			'name'     => __( 'Upload Directory Writable', 'ohmylms' ),
			'passed'   => $writable,
			'message'  => $writable
				? __( 'Writable', 'ohmylms' )
				: __( 'Not writable. Please check directory permissions.', 'ohmylms' ),
			'critical' => true,
		);

		// Check disk space (WARNING - should have some space but not critical).
		$free_space = @\disk_free_space( $upload_dir['basedir'] );

		// Handle case where disk_free_space() returns false.
		if ( false === $free_space ) {
			$results['checks']['disk_space'] = array(
				'name'     => __( 'Available Disk Space', 'ohmylms' ),
				'passed'   => true, // Don't block if we can't determine.
				'message'  => __( 'Unable to determine disk space (this is common on some hosting environments)', 'ohmylms' ),
				'critical' => false,
			);
		} else {
			$space_ok = $free_space > 100 * 1024 * 1024; // 100MB minimum

			$results['checks']['disk_space'] = array(
				'name'     => __( 'Available Disk Space', 'ohmylms' ),
				'passed'   => $space_ok,
				'message'  => sprintf( /* translators: %s: text value. */
				__( 'Available: %s', 'ohmylms' ), size_format( $free_space ) ) .
					( $space_ok ? '' : ' ' . __( '(low - recommended: 100MB or higher)', 'ohmylms' ) ),
				'critical' => false, // Warning only.
			);
		}

		// Update overall status based on strict mode.
		if ( $strict_mode ) {
			// In strict mode, only critical checks must pass.
			foreach ( $results['checks'] as $check ) {
				if ( $check['critical'] && ! $check['passed'] ) {
					$results['all_passed'] = false;
					break;
				}
			}
		} else {
			// In normal mode, all checks must pass.
			foreach ( $results['checks'] as $check ) {
				if ( ! $check['passed'] ) {
					$results['all_passed'] = false;
					break;
				}
			}
		}

		return $results;
	}

	/**
	 * Convert to bytes for Scorm helper.
	 *
	 * @param mixed $size Size.
	 */
	private static function convert_to_bytes( $size ) {
		$size = trim( $size );
		$last = strtolower( $size[ strlen( $size ) - 1 ] );
		$size = (int) $size;

		switch ( $last ) {
			case 'g':
				$size *= 1024;
			case 'm':
				$size *= 1024;
			case 'k':
				$size *= 1024;
		}

		return $size;
	}

	/**
	 * Sanitize scorm filename for Scorm helper.
	 *
	 * @param mixed $filename Filename.
	 */
	public static function sanitize_scorm_filename( $filename ) {
		// Remove special characters.
		$filename = preg_replace( '/[^A-Za-z0-9\-_]/', '-', $filename );

		// Remove multiple consecutive dashes.
		$filename = preg_replace( '/-+/', '-', $filename );

		// Trim dashes from ends.
		$filename = trim( $filename, '-' );

		// Ensure not empty.
		if ( empty( $filename ) ) {
			$filename = 'course';
		}

		return $filename;
	}

	/**
	 * Estimate export size for Scorm helper.
	 *
	 * @param mixed $course_id Course identifier.
	 */
	public static function estimate_export_size( $course_id ) {
		global $wpdb;

		$total_size = 0;
		$file_count = 0;

		// Get course thumbnail.
		$thumbnail_id = get_post_thumbnail_id( $course_id );
		if ( $thumbnail_id ) {
			$file_path = get_attached_file( $thumbnail_id );
			if ( $file_path && file_exists( $file_path ) ) {
				$total_size += filesize( $file_path );
				++$file_count;
			}
		}

		// Get chapters and contents.
		$chapters = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT chapter_id FROM {$wpdb->prefix}ohmylms_chapter_relationship WHERE course_id = %d",
				$course_id
			),
			ARRAY_A
		);

		foreach ( $chapters as $chapter ) {
			// Chapter thumbnail.
			$chapter_thumb = get_post_thumbnail_id( $chapter['chapter_id'] );
			if ( $chapter_thumb ) {
				$file_path = get_attached_file( $chapter_thumb );
				if ( $file_path && file_exists( $file_path ) ) {
					$total_size += filesize( $file_path );
					++$file_count;
				}
			}

			// Get contents.
			$contents = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT content_id FROM {$wpdb->prefix}ohmylms_content_relationship WHERE chapter_id = %d",
					$chapter['chapter_id']
				),
				ARRAY_A
			);

			foreach ( $contents as $content ) {
				// Content media.
				$content_thumb = get_post_thumbnail_id( $content['content_id'] );
				if ( $content_thumb ) {
					$file_path = get_attached_file( $content_thumb );
					if ( $file_path && file_exists( $file_path ) ) {
						$total_size += filesize( $file_path );
						++$file_count;
					}
				}

				// Video files.
				$video_id = get_post_meta( $content['content_id'], '_video_id', true );
				if ( $video_id ) {
					$file_path = get_attached_file( $video_id );
					if ( $file_path && file_exists( $file_path ) ) {
						$total_size += filesize( $file_path );
						++$file_count;
					}
				}
			}
		}

		return array(
			'size_bytes'     => $total_size,
			'size_formatted' => size_format( $total_size ),
			'file_count'     => $file_count,
		);
	}

	/**
	 * Clean old temp files for Scorm helper.
	 *
	 * @param int $days Days.
	 */
	public static function clean_old_temp_files( $days = 1 ) {
		$upload_dir = wp_upload_dir();
		$base_dir   = $upload_dir['basedir'];

		$deleted     = 0;
		$cutoff_time = time() - ( $days * DAY_IN_SECONDS );

		// Clean temp directories.
		$pattern   = $base_dir . '/scorm-temp-*';
		$temp_dirs = glob( $pattern );

		foreach ( $temp_dirs as $dir ) {
			if ( is_dir( $dir ) && filemtime( $dir ) < $cutoff_time ) {
				self::delete_directory( $dir );
				++$deleted;
			}
		}

		// Clean ZIP files.
		$pattern   = $base_dir . '/scorm-package-*.zip';
		$zip_files = glob( $pattern );

		foreach ( $zip_files as $file ) {
			if ( is_file( $file ) && filemtime( $file ) < $cutoff_time ) {
				@unlink( $file );
				++$deleted;
			}
		}

		return $deleted;
	}

	/**
	 * Delete directory for Scorm helper.
	 *
	 * @param mixed $dir Dir.
	 */
	private static function delete_directory( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return false;
		}

		$files = array_diff( scandir( $dir ), array( '.', '..' ) );

		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;
			is_dir( $path ) ? self::delete_directory( $path ) : @unlink( $path );
		}

		return @rmdir( $dir );
	}
}
