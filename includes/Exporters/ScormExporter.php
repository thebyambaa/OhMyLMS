<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class ScormExporter.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Exporters;

use OhMyLMS\Utility\ScormHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Class ScormExporter
 *
 * Exports one or multiple courses as SCORM-compliant packages.
 * Supports SCORM 1.2 and SCORM 2004 standards.
 */
class ScormExporter {

	/**
	 * Course IDs to export
	 *
	 * @var array
	 */
	private $course_ids;

	/**
	 * SCORM version (1.2 or 2004)
	 *
	 * @var string
	 */
	private $scorm_version;

	/**
	 * Temporary directory for building SCORM package
	 *
	 * @var string
	 */
	private $temp_dir;

	/**
	 * ScormExporter constructor.
	 *
	 * @param array  $course_ids Array of course IDs to export.
	 * @param string $scorm_version SCORM version (default: '1.2').
	 * @throws \Exception When the operation cannot be completed.*/
	public function __construct( $course_ids, $scorm_version = '1.2' ) {
		if ( empty( $course_ids ) || ! is_array( $course_ids ) ) {
			throw new \Exception( __( 'Invalid course IDs. Provide an array of course IDs.', 'ohmylms' ) );
		}

		// Validate courses before export.
		foreach ( $course_ids as $course_id ) {
			$validation = ScormHelper::validate_course_for_export( $course_id );
			if ( ! $validation['valid'] ) {
				throw new \Exception( $validation['message'] );
			}
		}

		// Check system requirements (strict mode - only critical requirements block export).
		$system_check = ScormHelper::check_system_requirements( true );
		if ( ! $system_check['all_passed'] ) {
			$errors = array();
			foreach ( $system_check['checks'] as $check ) {
				if ( ! $check['passed'] && isset( $check['critical'] ) && $check['critical'] ) {
					$errors[] = $check['name'] . ': ' . $check['message'];
				}
			}
			if ( ! empty( $errors ) ) {
				throw new \Exception(
					__( 'Critical system requirements not met:', 'ohmylms' ) . "\n" . implode( "\n", $errors )
				);
			}
		}

		// Log warnings for non-critical checks.
		$warnings = array();
		foreach ( $system_check['checks'] as $check ) {
			if ( ! $check['passed'] && isset( $check['critical'] ) && ! $check['critical'] ) {
				$warnings[] = $check['name'] . ': ' . $check['message'];
			}
		}
		if ( ! empty( $warnings ) ) {
			ScormHelper::log( 'System warnings (non-critical)', 'warning', array( 'warnings' => $warnings ) );
		}

		$this->course_ids    = $course_ids;
		$this->scorm_version = in_array( $scorm_version, array( '1.2', '2004' ) ) ? $scorm_version : '1.2';

		// Create temporary directory.
		$upload_dir     = wp_upload_dir();
		$this->temp_dir = trailingslashit( $upload_dir['basedir'] ) . 'scorm-temp-' . time() . '-' . wp_rand();

		if ( ! wp_mkdir_p( $this->temp_dir ) ) {
			throw new \Exception( __( 'Failed to create temporary directory for SCORM export.', 'ohmylms' ) );
		}

		ScormHelper::log(
			'SCORM export initialized',
			'info',
			array(
				'course_ids' => $course_ids,
				'version'    => $scorm_version,
			)
		);
	}

	/**
	 * Attempt to increase system limits for export process.
	 *
	 * @return void
	 */
	private function increase_system_limits() {
		// Increase time limit.
		if ( ! ini_get( 'safe_mode' ) ) {
			$current_limit = ini_get( 'max_execution_time' );
			if ( 0 < $current_limit && 300 > $current_limit ) {
				@set_time_limit( 300 ); // 5 minutes
				ScormHelper::log(
					'Attempted to increase execution time to 300 seconds',
					'info',
					array(
						'original'  => $current_limit,
						'requested' => 300,
					)
				);
			}
		}

		// Increase memory limit if needed.
		$current_memory        = ini_get( 'memory_limit' );
		$current_memory_bytes  = $this->convert_to_bytes( $current_memory );
		$required_memory_bytes = 256 * 1024 * 1024; // 256MB

		if ( '-1' != $current_memory && $current_memory_bytes < $required_memory_bytes ) {
			wp_raise_memory_limit( 'admin' );
			ScormHelper::log(
				'Requested the WordPress admin memory limit',
				'info',
				array(
					'original'  => $current_memory,
					'requested' => '256M',
				)
			);
		}
	}

	/**
	 * Convert PHP memory limit to bytes.
	 *
	 * @param string $size Size string (e.g., "256M", "1G").
	 * @return int Size in bytes.
	 */
	private function convert_to_bytes( $size ) {
		$size = trim( $size );
		if ( empty( $size ) ) {
			return 0;
		}

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
	 * Export courses as SCORM package.
	 *
	 * @return void
	 */
	public function export_courses_as_scorm() {
		try {
			ScormHelper::log( 'Starting SCORM export', 'info' );

			// Attempt to increase system limits for export.
			$this->increase_system_limits();

			// Build SCORM package structure.
			$this->create_scorm_structure();

			// Generate manifest file.
			$this->generate_manifest();

			// Process each course.
			foreach ( $this->course_ids as $course_id ) {
				ScormHelper::log( 'Processing course', 'info', array( 'course_id' => $course_id ) );
				$this->process_course( $course_id );
			}

			// Create ZIP archive.
			$zip_path = $this->create_zip_package();

			ScormHelper::log( 'SCORM export completed successfully', 'info', array( 'zip_path' => $zip_path ) );

			// Send ZIP file for download.
			$this->send_download( $zip_path );

		} catch ( \Exception $e ) {
			ScormHelper::log( 'SCORM export failed', 'error', array( 'error' => $e->getMessage() ) );
			$this->cleanup();
			wp_die( esc_html__( 'SCORM Export Error: ', 'ohmylms' ) . $e->getMessage() );
		}
	}

	/**
	 * Create basic SCORM package structure.
	 *
	 * @return void
	 */
	private function create_scorm_structure() {
		// Create standard SCORM directories.
		wp_mkdir_p( $this->temp_dir . '/content' );
		wp_mkdir_p( $this->temp_dir . '/resources' );
	}

	/**
	 * Generate imsmanifest.xml file.
	 *
	 * @return void
	 * @throws \Exception When the operation cannot be completed.*/
	private function generate_manifest() {
		$xml               = new \DOMDocument( '1.0', 'UTF-8' );
		$xml->formatOutput = true;

		if ( '1.2' === $this->scorm_version ) {
			$manifest = $this->generate_scorm_12_manifest( $xml );
		} else {
			$manifest = $this->generate_scorm_2004_manifest( $xml );
		}

		$xml->appendChild( $manifest );

		$manifest_path = $this->temp_dir . '/imsmanifest.xml';
		if ( ! $xml->save( $manifest_path ) ) {
			throw new \Exception( __( 'Failed to create manifest file.', 'ohmylms' ) );
		}
	}

	/**
	 * Generate SCORM 1.2 manifest.
	 *
	 * @param \DOMDocument $xml XML document.
	 * @return \DOMElement
	 */
	private function generate_scorm_12_manifest( $xml ) {
		$manifest = $xml->createElement( 'manifest' );
		$manifest->setAttribute( 'identifier', 'MANIFEST-' . time() );
		$manifest->setAttribute( 'version', '1.0' );
		$manifest->setAttribute( 'xmlns', 'http://www.imsproject.org/xsd/imscp_rootv1p1p2' );
		$manifest->setAttribute( 'xmlns:adlcp', 'http://www.adlnet.org/xsd/adlcp_rootv1p2' );
		$manifest->setAttribute( 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance' );
		$manifest->setAttribute( 'xsi:schemaLocation', 'http://www.imsproject.org/xsd/imscp_rootv1p1p2 imscp_rootv1p1p2.xsd http://www.imsglobal.org/xsd/imsmd_rootv1p2p1 imsmd_rootv1p2p1.xsd http://www.adlnet.org/xsd/adlcp_rootv1p2 adlcp_rootv1p2.xsd' );

		// Metadata.
		$metadata      = $xml->createElement( 'metadata' );
		$schema        = $xml->createElement( 'schema', 'ADL SCORM' );
		$schemaversion = $xml->createElement( 'schemaversion', '1.2' );
		$metadata->appendChild( $schema );
		$metadata->appendChild( $schemaversion );
		$manifest->appendChild( $metadata );

		// Organizations.
		$organizations = $xml->createElement( 'organizations' );
		$organizations->setAttribute( 'default', 'ORG-' . time() );

		foreach ( $this->course_ids as $index => $course_id ) {
			$course_post = get_post( $course_id );
			if ( ! $course_post || OHMYLMS_COURSE_CPT !== $course_post->post_type ) {
				continue;
			}

			$org = $xml->createElement( 'organization' );
			$org->setAttribute( 'identifier', 'ORG-' . $course_id );

			$title = $xml->createElement( 'title', htmlspecialchars( $course_post->post_title ) );
			$org->appendChild( $title );

			// Add course description as metadata if available.
			if ( ! empty( $course_post->post_content ) ) {
				// Strip HTML tags and limit to 500 characters for metadata.
				$course_description = wp_strip_all_tags( $course_post->post_content );
				$course_description = mb_substr( $course_description, 0, 500 );

				if ( ! empty( $course_description ) ) {
					$org_metadata = $xml->createElement( 'metadata' );
					$lom          = $xml->createElement( 'lom' );
					$general      = $xml->createElement( 'general' );
					$description  = $xml->createElement( 'description' );
					$langstring   = $xml->createElement( 'langstring', htmlspecialchars( $course_description ) );
					$langstring->setAttribute( 'xml:lang', 'en' );

					$description->appendChild( $langstring );
					$general->appendChild( $description );
					$lom->appendChild( $general );
					$org_metadata->appendChild( $lom );
					$org->appendChild( $org_metadata );
				}
			}

			// Get course chapters and contents.
			$chapters = $this->get_course_chapters( $course_id );

			foreach ( $chapters as $chapter_index => $chapter ) {
				$item = $this->create_manifest_item( $xml, $chapter, $course_id, $chapter_index );
				$org->appendChild( $item );
			}

			$organizations->appendChild( $org );
		}

		$manifest->appendChild( $organizations );

		// Resources.
		$resources = $xml->createElement( 'resources' );

		foreach ( $this->course_ids as $course_id ) {
			$chapters = $this->get_course_chapters( $course_id );

			foreach ( $chapters as $chapter_index => $chapter ) {
				$this->add_manifest_resources( $xml, $resources, $chapter, $course_id, $chapter_index );
			}
		}

		$manifest->appendChild( $resources );

		return $manifest;
	}

	/**
	 * Generate SCORM 2004 manifest.
	 *
	 * @param \DOMDocument $xml XML document.
	 * @return \DOMElement
	 */
	private function generate_scorm_2004_manifest( $xml ) {
		$manifest = $xml->createElement( 'manifest' );
		$manifest->setAttribute( 'identifier', 'MANIFEST-' . time() );
		$manifest->setAttribute( 'version', '1.0' );
		$manifest->setAttribute( 'xmlns', 'http://www.imsglobal.org/xsd/imscp_v1p1' );
		$manifest->setAttribute( 'xmlns:adlcp', 'http://www.adlnet.org/xsd/adlcp_v1p3' );
		$manifest->setAttribute( 'xmlns:adlseq', 'http://www.adlnet.org/xsd/adlseq_v1p3' );
		$manifest->setAttribute( 'xmlns:adlnav', 'http://www.adlnet.org/xsd/adlnav_v1p3' );
		$manifest->setAttribute( 'xmlns:imsss', 'http://www.imsglobal.org/xsd/imsss' );
		$manifest->setAttribute( 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance' );

		// Metadata.
		$metadata      = $xml->createElement( 'metadata' );
		$schema        = $xml->createElement( 'schema', 'ADL SCORM' );
		$schemaversion = $xml->createElement( 'schemaversion', '2004 4th Edition' );
		$metadata->appendChild( $schema );
		$metadata->appendChild( $schemaversion );
		$manifest->appendChild( $metadata );

		// Organizations (similar to SCORM 1.2 but with sequencing).
		$organizations = $xml->createElement( 'organizations' );
		$organizations->setAttribute( 'default', 'ORG-' . time() );

		foreach ( $this->course_ids as $course_id ) {
			$course_post = get_post( $course_id );
			if ( ! $course_post || OHMYLMS_COURSE_CPT !== $course_post->post_type ) {
				continue;
			}

			$org = $xml->createElement( 'organization' );
			$org->setAttribute( 'identifier', 'ORG-' . $course_id );

			$title = $xml->createElement( 'title', htmlspecialchars( $course_post->post_title ) );
			$org->appendChild( $title );

			// Add course description as metadata if available.
			if ( ! empty( $course_post->post_content ) ) {
				// Strip HTML tags and limit to 500 characters for metadata.
				$course_description = wp_strip_all_tags( $course_post->post_content );
				$course_description = mb_substr( $course_description, 0, 500 );

				if ( ! empty( $course_description ) ) {
					$org_metadata = $xml->createElement( 'metadata' );
					$lom          = $xml->createElement( 'lom' );
					$general      = $xml->createElement( 'general' );
					$description  = $xml->createElement( 'description' );
					$langstring   = $xml->createElement( 'langstring', htmlspecialchars( $course_description ) );
					$langstring->setAttribute( 'xml:lang', 'en' );

					$description->appendChild( $langstring );
					$general->appendChild( $description );
					$lom->appendChild( $general );
					$org_metadata->appendChild( $lom );
					$org->appendChild( $org_metadata );
				}
			}

			// Get course chapters.
			$chapters = $this->get_course_chapters( $course_id );

			foreach ( $chapters as $chapter_index => $chapter ) {
				$item = $this->create_manifest_item( $xml, $chapter, $course_id, $chapter_index );
				$org->appendChild( $item );
			}

			$organizations->appendChild( $org );
		}

		$manifest->appendChild( $organizations );

		// Resources.
		$resources = $xml->createElement( 'resources' );

		foreach ( $this->course_ids as $course_id ) {
			$chapters = $this->get_course_chapters( $course_id );

			foreach ( $chapters as $chapter_index => $chapter ) {
				$this->add_manifest_resources( $xml, $resources, $chapter, $course_id, $chapter_index );
			}
		}

		$manifest->appendChild( $resources );

		return $manifest;
	}

	/**
	 * Create manifest item for chapter.
	 *
	 * @param \DOMDocument $xml XML document.
	 * @param array        $chapter Chapter data.
	 * @param int          $course_id Course ID.
	 * @param int          $chapter_index Chapter index.
	 * @return \DOMElement
	 */
	private function create_manifest_item( $xml, $chapter, $course_id, $chapter_index ) {
		$chapter_post = get_post( $chapter['chapter_id'] );

		$item = $xml->createElement( 'item' );
		$item->setAttribute( 'identifier', 'ITEM-' . $course_id . '-' . $chapter['chapter_id'] );

		// Chapters should NOT have identifierref - they are container items
		// Only leaf items (lessons) should have identifierref pointing to resources
		// This ensures chapters remain chapters on import, not converted to lessons.

		$title = $xml->createElement( 'title', htmlspecialchars( $chapter_post->post_title ) );
		$item->appendChild( $title );

		// Add chapter description as metadata if available.
		if ( ! empty( $chapter_post->post_content ) ) {
			// Strip HTML tags and limit to 255 characters for metadata.
			$description_text = wp_strip_all_tags( $chapter_post->post_content );
			$description_text = mb_substr( $description_text, 0, 255 );

			if ( ! empty( $description_text ) ) {
				$metadata    = $xml->createElement( 'metadata' );
				$lom         = $xml->createElement( 'lom' );
				$general     = $xml->createElement( 'general' );
				$description = $xml->createElement( 'description' );
				$langstring  = $xml->createElement( 'langstring', htmlspecialchars( $description_text ) );
				$langstring->setAttribute( 'xml:lang', 'en' );

				$description->appendChild( $langstring );
				$general->appendChild( $description );
				$lom->appendChild( $general );
				$metadata->appendChild( $lom );
				$item->appendChild( $metadata );
			}
		}

		// Add contents as sub-items.
		if ( ! empty( $chapter['contents'] ) ) {
			foreach ( $chapter['contents'] as $content_index => $content ) {
				$content_post = get_post( $content['content_id'] );

				$subitem = $xml->createElement( 'item' );
				$subitem->setAttribute( 'identifier', 'ITEM-' . $course_id . '-' . $content['content_id'] );
				$subitem->setAttribute( 'identifierref', 'RES-' . $course_id . '-' . $content['content_id'] );

				$subtitle = $xml->createElement( 'title', htmlspecialchars( $content_post->post_title ) );
				$subitem->appendChild( $subtitle );

				// Add content type metadata to preserve post_type during export/import.
				$content_metadata = $xml->createElement( 'metadata' );
				$content_lom      = $xml->createElement( 'lom' );
				$content_general  = $xml->createElement( 'general' );

				// Add content type as keyword metadata.
				$keyword            = $xml->createElement( 'keyword' );
				$keyword_langstring = $xml->createElement( 'langstring', 'content-type:' . $content_post->post_type );
				$keyword_langstring->setAttribute( 'xml:lang', 'en' );
				$keyword->appendChild( $keyword_langstring );
				$content_general->appendChild( $keyword );

				$content_lom->appendChild( $content_general );
				$content_metadata->appendChild( $content_lom );
				$subitem->appendChild( $content_metadata );

				$item->appendChild( $subitem );
			}
		}

		return $item;
	}

	/**
	 * Add resources to manifest.
	 *
	 * @param \DOMDocument $xml XML document.
	 * @param \DOMElement  $resources Resources element.
	 * @param array        $chapter Chapter data.
	 * @param int          $course_id Course ID.
	 * @param int          $chapter_index Chapter index.
	 * @return void
	 */
	private function add_manifest_resources( $xml, $resources, $chapter, $course_id, $chapter_index ) {
		// Chapters are container items and should NOT have resources
		// Only add resources for actual content (lessons, quizzes, etc.).

		// Add content resources.
		if ( ! empty( $chapter['contents'] ) ) {
			foreach ( $chapter['contents'] as $content ) {
				$content_resource = $xml->createElement( 'resource' );
				$content_resource->setAttribute( 'identifier', 'RES-' . $course_id . '-' . $content['content_id'] );
				$content_resource->setAttribute( 'type', 'webcontent' );
				$content_resource->setAttribute( 'adlcp:scormtype', 'sco' );
				$content_resource->setAttribute( 'href', 'content/course_' . $course_id . '/content_' . $content['content_id'] . '/index.html' );

				$content_file = $xml->createElement( 'file' );
				$content_file->setAttribute( 'href', 'content/course_' . $course_id . '/content_' . $content['content_id'] . '/index.html' );
				$content_resource->appendChild( $content_file );

				$resources->appendChild( $content_resource );
			}
		}
	}

	/**
	 * Process a single course and create content files.
	 *
	 * @param int $course_id Course ID.
	 * @return void
	 * @throws \Exception When the operation cannot be completed.*/
	private function process_course( $course_id ) {
		$course_post = get_post( $course_id );

		if ( ! $course_post || OHMYLMS_COURSE_CPT !== $course_post->post_type ) {
			throw new \Exception( sprintf( /* translators: %d: number. */
			__( 'Invalid course ID: %d', 'ohmylms' ), $course_id ) );
		}

		// Create course directory.
		$course_dir = $this->temp_dir . '/content/course_' . $course_id;
		wp_mkdir_p( $course_dir );

		// Create course overview/introduction page with description.
		if ( ! empty( $course_post->post_content ) ) {
			$overview_html = $this->generate_html_content(
				$course_post->post_title,
				$course_post->post_content
			);
			file_put_contents( $course_dir . '/overview.html', $overview_html );
		}

		// Get course chapters.
		$chapters = $this->get_course_chapters( $course_id );

		foreach ( $chapters as $chapter ) {
			$this->process_chapter( $chapter, $course_id );
		}
	}

	/**
	 * Process a chapter and create HTML content for its lessons.
	 *
	 * @param array $chapter Chapter data.
	 * @param int   $course_id Course ID.
	 * @return void
	 */
	private function process_chapter( $chapter, $course_id ) {
		// Chapters are organizational containers - no need to create HTML files
		// Chapter title and description are stored in manifest metadata
		// Only process actual content (lessons, quizzes, etc.).

		// Process chapter contents.
		if ( ! empty( $chapter['contents'] ) ) {
			foreach ( $chapter['contents'] as $content ) {
				$this->process_content( $content, $course_id );
			}
		}
	}

	/**
	 * Process content (lesson, quiz, etc.) and create HTML.
	 *
	 * @param array $content Content data.
	 * @param int   $course_id Course ID.
	 * @return void
	 */
	private function process_content( $content, $course_id ) {
		$content_post = get_post( $content['content_id'] );

		// Create content directory.
		$content_dir = $this->temp_dir . '/content/course_' . $course_id . '/content_' . $content['content_id'];
		wp_mkdir_p( $content_dir );

		// Generate content based on type.
		$html_content = $this->generate_content_html( $content_post, $content );

		// Create content index.html.
		file_put_contents( $content_dir . '/index.html', $html_content );

		// Download and save media files.
		$this->process_media_files( $content_post, $content_dir );
	}

	/**
	 * Generate HTML content for a lesson/content.
	 *
	 * @param \WP_Post $post Post object.
	 * @param array    $content Content data.
	 * @return string HTML content.
	 */
	private function generate_content_html( $post, $content ) {
		$title = $post->post_title;
		$body  = $post->post_content;

		// Handle video lessons.
		if ( 'ohmylms-lesson' === $post->post_type ) {
			$lesson_type = get_post_meta( $post->ID, '_type', true );

			if ( 'video' === $lesson_type ) {
				$video_id     = get_post_meta( $post->ID, '_video_id', true );
				$external_url = get_post_meta( $post->ID, '_external_url', true );

				$video_html = '<div class="lesson-video-container" style="margin: 20px 0;">';

				if ( $video_id ) {
					// Internal video.
					$video_url = wp_get_attachment_url( $video_id );
					if ( $video_url ) {
						$video_html .= '<video controls style="width: 100%; max-width: 800px; border-radius: 8px;">';
						$video_html .= '<source src="media/' . basename( $video_url ) . '" type="video/mp4">';
						$video_html .= __( 'Your browser does not support the video tag.', 'ohmylms' );
						$video_html .= '</video>';
					}
				} elseif ( $external_url ) {
					// External video (YouTube, Vimeo, etc.).
					if ( preg_match( '/youtube\.com|youtu\.be/i', $external_url ) ) {
						// YouTube embed.
						$video_id_match = array();
						if ( preg_match( '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/i', $external_url, $video_id_match ) ) {
							$video_html .= '<iframe width="100%" height="450" src="https://www.youtube.com/embed/' . $video_id_match[1] . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen style="border-radius: 8px;"></iframe>';
						}
					} elseif ( preg_match( '/vimeo\.com/i', $external_url ) ) {
						// Vimeo embed.
						$video_id_match = array();
						if ( preg_match( '/vimeo\.com\/(\d+)/i', $external_url, $video_id_match ) ) {
							$video_html .= '<iframe src="https://player.vimeo.com/video/' . $video_id_match[1] . '" width="100%" height="450" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen style="border-radius: 8px;"></iframe>';
						}
					} else {
						// Generic video link.
						$video_html .= '<video controls style="width: 100%; max-width: 800px; border-radius: 8px;">';
						$video_html .= '<source src="' . esc_url( $external_url ) . '">';
						$video_html .= __( 'Your browser does not support the video tag.', 'ohmylms' );
						$video_html .= '</video>';
					}
				}

				$video_html .= '</div>';
				$body        = $video_html . $body;
			} elseif ( 'audio' === $lesson_type ) {
				// Handle audio lessons.
				$audio_id     = get_post_meta( $post->ID, '_audio_id', true );
				$external_url = get_post_meta( $post->ID, '_external_url', true );

				$audio_html = '<div class="lesson-audio-container" style="margin: 20px 0;">';

				if ( $audio_id ) {
					$audio_url = wp_get_attachment_url( $audio_id );
					if ( $audio_url ) {
						$audio_html .= '<audio controls style="width: 100%; max-width: 600px;">';
						$audio_html .= '<source src="media/' . basename( $audio_url ) . '" type="audio/mpeg">';
						$audio_html .= __( 'Your browser does not support the audio tag.', 'ohmylms' );
						$audio_html .= '</audio>';
					}
				} elseif ( $external_url ) {
					$audio_html .= '<audio controls style="width: 100%; max-width: 600px;">';
					$audio_html .= '<source src="' . esc_url( $external_url ) . '">';
					$audio_html .= __( 'Your browser does not support the audio tag.', 'ohmylms' );
					$audio_html .= '</audio>';
				}

				$audio_html .= '</div>';
				$body        = $audio_html . $body;
			}
		}

		// Handle quiz content.
		if ( 'ohmylms-quiz' === $post->post_type ) {
			$body .= $this->generate_quiz_html( $post->ID );
		}

		// Handle assignment content.
		if ( 'ohmylms-assignment' === $post->post_type ) {
			$assignment_settings = get_post_meta( $post->ID, '_assignment_settings', true );

			if ( ! empty( $assignment_settings ) ) {
				$assignment_html  = '<div class="scorm-assignment-info" style="background: #f5f5f5; padding: 20px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #0073aa;">';
				$assignment_html .= '<h3 style="margin-top: 0;">' . __( 'Assignment Requirements', 'ohmylms' ) . '</h3>';

				if ( isset( $assignment_settings['upload_limit'] ) ) {
					$assignment_html .= '<p><strong>' . __( 'Maximum Files:', 'ohmylms' ) . '</strong> ' . $assignment_settings['upload_limit'] . '</p>';
				}

				if ( isset( $assignment_settings['attachment_limit'] ) ) {
					$assignment_html .= '<p><strong>' . __( 'File Size Limit:', 'ohmylms' ) . '</strong> ' . $assignment_settings['attachment_limit'] . ' MB</p>';
				}

				if ( isset( $assignment_settings['allowed_extensions'] ) && is_array( $assignment_settings['allowed_extensions'] ) ) {
					$assignment_html .= '<p><strong>' . __( 'Allowed File Types:', 'ohmylms' ) . '</strong> ' . implode( ', ', $assignment_settings['allowed_extensions'] ) . '</p>';
				}

				if ( isset( $assignment_settings['passing_grade'] ) ) {
					$assignment_html .= '<p><strong>' . __( 'Passing Grade:', 'ohmylms' ) . '</strong> ' . $assignment_settings['passing_grade'] . '%</p>';
				}

				$assignment_html .= '<p style="margin-bottom: 0;"><em>' . __( 'Note: This is a SCORM export. Assignment submission functionality is not available in SCORM format.', 'ohmylms' ) . '</em></p>';
				$assignment_html .= '</div>';

				$body .= $assignment_html;
			}
		}

		return $this->generate_html_content( $title, $body );
	}

	/**
	 * Generate quiz HTML.
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return string Quiz HTML.
	 */
	private function generate_quiz_html( $quiz_id ) {
		global $wpdb;

		$html = '<div class="scorm-quiz">';

		// Fetch questions.
		$questions_query = $wpdb->prepare(
			'
            SELECT * 
            FROM %i
            WHERE quiz_id = %d 
            ORDER BY order_number ASC
        ',
			$wpdb->prefix . 'ohmylms_quiz_questions_relationship',
			$quiz_id
		);

		$questions = $wpdb->get_results( $questions_query, ARRAY_A );

		foreach ( $questions as $index => $question ) {
			$question_post = get_post( $question['question_id'] );
			if ( ! $question_post ) {
				continue;
			}

			// Get question settings.
			$question_settings = get_post_meta( $question['question_id'], '_question_settings', true );
			$question_type     = isset( $question_settings['type'] ) ? $question_settings['type'] : 'single-choice';

			$html .= '<div class="quiz-question" data-question-id="' . $question['question_id'] . '" data-question-type="' . esc_attr( $question_type ) . '">';
			$html .= '<h3>Question ' . ( $index + 1 ) . ': ' . esc_html( $question_post->post_title ) . '</h3>';

			if ( ! empty( $question_post->post_content ) ) {
				$html .= '<div class="question-content">' . wpautop( $question_post->post_content ) . '</div>';
			}

			// Generate question type specific HTML.
			$html .= $this->generate_question_type_html( $question['question_id'], $question_type );

			$html .= '</div>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate HTML for specific question type.
	 *
	 * @param int    $question_id Question ID.
	 * @param string $question_type Question type.
	 * @return string Question HTML.
	 */
	private function generate_question_type_html( $question_id, $question_type ) {
		global $wpdb;

		// Fetch answers.
		$answers_query = $wpdb->prepare(
			'
            SELECT * 
            FROM %i
            WHERE question_id = %d 
            ORDER BY order_number ASC
        ',
			$wpdb->prefix . 'ohmylms_question_answers',
			$question_id
		);

		$answers = $wpdb->get_results( $answers_query, ARRAY_A );

		if ( empty( $answers ) ) {
			return '<p class="no-answers"><em>' . __( 'No answers available', 'ohmylms' ) . '</em></p>';
		}

		$html = '<div class="question-answers" data-type="' . esc_attr( $question_type ) . '">';

		switch ( $question_type ) {
			case 'multiple-choice':
				$html .= $this->generate_multiple_choice_html( $question_id, $answers );
				break;

			case 'single-choice':
				$html .= $this->generate_single_choice_html( $question_id, $answers );
				break;

			case 'true-false':
				$html .= $this->generate_true_false_html( $question_id, $answers );
				break;

			case 'short-text':
			case 'long-text':
				$html .= $this->generate_text_input_html( $question_id, $question_type );
				break;

			case 'fill-in-the-blank':
				$html .= $this->generate_fill_blank_html( $question_id, $answers );
				break;

			case 'matching':
				$html .= $this->generate_matching_html( $question_id, $answers );
				break;

			case 'reorder':
				$html .= $this->generate_reorder_html( $question_id, $answers );
				break;

			case 'statement':
				$html .= $this->generate_statement_html( $question_id, $answers );
				break;

			default:
				$html .= $this->generate_single_choice_html( $question_id, $answers );
				break;
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate multiple choice HTML.
	 *
	 * @param int   $question_id Question ID.
	 * @param array $answers Answers array.
	 * @return string HTML.
	 */
	private function generate_multiple_choice_html( $question_id, $answers ) {
		$html = '<div class="multiple-choice-options">';

		foreach ( $answers as $answer ) {
			$is_correct  = $answer['is_correct'] ? ' ✓ (Correct)' : '';
			$answer_meta = $this->get_answer_meta( $answer['id'] );
			$image_url   = isset( $answer_meta['image_url'] ) ? $answer_meta['image_url'] : '';

			$html .= '<label class="answer-option" style="display: block; margin: 10px 0; padding: 10px; background: #f9f9f9; border-radius: 5px; cursor: pointer;">';
			$html .= '<input type="checkbox" name="question_' . $question_id . '[]" value="' . $answer['id'] . '" style="margin-right: 8px;" />';

			if ( $image_url ) {
				$html .= '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $answer['answer'] ) . '" style="max-width: 100px; height: auto; margin-right: 10px; vertical-align: middle;" />';
			}

			$html .= '<span>' . esc_html( $answer['answer'] ) . '<strong style="color: #28a745;">' . $is_correct . '</strong></span>';
			$html .= '</label>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate single choice HTML.
	 *
	 * @param int   $question_id Question ID.
	 * @param array $answers Answers array.
	 * @return string HTML.
	 */
	private function generate_single_choice_html( $question_id, $answers ) {
		$html = '<div class="single-choice-options">';

		foreach ( $answers as $answer ) {
			$is_correct  = $answer['is_correct'] ? ' ✓ (Correct)' : '';
			$answer_meta = $this->get_answer_meta( $answer['id'] );
			$image_url   = isset( $answer_meta['image_url'] ) ? $answer_meta['image_url'] : '';

			$html .= '<label class="answer-option" style="display: block; margin: 10px 0; padding: 10px; background: #f9f9f9; border-radius: 5px; cursor: pointer;">';
			$html .= '<input type="radio" name="question_' . $question_id . '" value="' . $answer['id'] . '" style="margin-right: 8px;" />';

			if ( $image_url ) {
				$html .= '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $answer['answer'] ) . '" style="max-width: 100px; height: auto; margin-right: 10px; vertical-align: middle;" />';
			}

			$html .= '<span>' . esc_html( $answer['answer'] ) . '<strong style="color: #28a745;">' . $is_correct . '</strong></span>';
			$html .= '</label>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate true/false HTML.
	 *
	 * @param int   $question_id Question ID.
	 * @param array $answers Answers array.
	 * @return string HTML.
	 */
	private function generate_true_false_html( $question_id, $answers ) {
		return $this->generate_single_choice_html( $question_id, $answers );
	}

	/**
	 * Generate text input HTML.
	 *
	 * @param int    $question_id Question ID.
	 * @param string $question_type Question type (short-text or long-text).
	 * @return string HTML.
	 */
	private function generate_text_input_html( $question_id, $question_type ) {
		$html = '<div class="text-input-answer">';

		if ( 'long-text' === $question_type ) {
			$html .= '<textarea name="question_' . $question_id . '" rows="6" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-family: inherit;" placeholder="' . esc_attr__( 'Type your answer here...', 'ohmylms' ) . '"></textarea>';
		} else {
			$html .= '<input type="text" name="question_' . $question_id . '" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;" placeholder="' . esc_attr__( 'Type your answer here...', 'ohmylms' ) . '" />';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate fill in the blank HTML.
	 *
	 * @param int   $question_id Question ID.
	 * @param array $answers Answers array.
	 * @return string HTML.
	 */
	private function generate_fill_blank_html( $question_id, $answers ) {
		$html  = '<div class="fill-blank-answer">';
		$html .= '<input type="text" name="question_' . $question_id . '" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;" placeholder="' . esc_attr__( 'Fill in the blank...', 'ohmylms' ) . '" />';

		if ( ! empty( $answers ) ) {
			$correct_answers = array_filter(
				$answers,
				function ( $answer ) {
					return $answer['is_correct'];
				}
			);

			if ( ! empty( $correct_answers ) ) {
				$html        .= '<p style="margin-top: 10px; color: #28a745; font-size: 14px;"><strong>' . __( 'Correct answer(s):', 'ohmylms' ) . '</strong> ';
				$answer_texts = array_map(
					function ( $answer ) {
						return esc_html( $answer['answer'] );
					},
					$correct_answers
				);
				$html        .= implode( ', ', $answer_texts );
				$html        .= '</p>';
			}
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate matching question HTML.
	 *
	 * @param int   $question_id Question ID.
	 * @param array $answers Answers array.
	 * @return string HTML.
	 */
	private function generate_matching_html( $question_id, $answers ) {
		$html = '<div class="matching-question" style="display: flex; flex-wrap: wrap; gap: 20px;">';

		$html .= '<div class="matching-left" style="flex: 1; min-width: 250px;">';
		$html .= '<h4>' . __( 'Items to Match', 'ohmylms' ) . '</h4>';

		foreach ( $answers as $answer ) {
			$answer_meta = $this->get_answer_meta( $answer['id'] );
			$image_url   = isset( $answer_meta['image_url'] ) ? $answer_meta['image_url'] : '';

			$html .= '<div class="matching-item" style="padding: 10px; margin: 10px 0; background: #f4f5f7; border-radius: 8px;">';

			if ( $image_url ) {
				$html .= '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $answer['answer'] ) . '" style="max-width: 100px; height: auto; display: block; margin-bottom: 8px;" />';
			}

			$html .= '<p style="margin: 0;"><strong>' . esc_html( $answer['answer'] ) . '</strong></p>';
			$html .= '</div>';
		}

		$html .= '</div>';

		$html .= '<div class="matching-right" style="flex: 1; min-width: 250px;">';
		$html .= '<h4>' . __( 'Definitions/Matches', 'ohmylms' ) . '</h4>';

		foreach ( $answers as $answer ) {
			$answer_meta    = $this->get_answer_meta( $answer['id'] );
			$matching_label = isset( $answer_meta['matching_label'] ) ? $answer_meta['matching_label'] : '';
			$matching_image = isset( $answer_meta['matching_image_url'] ) ? $answer_meta['matching_image_url'] : '';

			$html .= '<div class="matching-definition" style="padding: 10px; margin: 10px 0; background: #e6f7e9; border-radius: 8px;">';

			if ( $matching_image ) {
				$html .= '<img src="' . esc_url( $matching_image ) . '" alt="' . esc_attr( $matching_label ) . '" style="max-width: 100px; height: auto; display: block; margin-bottom: 8px;" />';
			}

			$html .= '<p style="margin: 0;">' . esc_html( $matching_label ) . '</p>';
			$html .= '</div>';
		}

		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate reorder question HTML.
	 *
	 * @param int   $question_id Question ID.
	 * @param array $answers Answers array.
	 * @return string HTML.
	 */
	private function generate_reorder_html( $question_id, $answers ) {
		$html  = '<div class="reorder-question">';
		$html .= '<p style="font-style: italic; color: #666;">' . __( 'The correct order is shown below:', 'ohmylms' ) . '</p>';

		// Sort by order number to show correct sequence.
		usort(
			$answers,
			function ( $a, $b ) {
				return $a['order_number'] - $b['order_number'];
			}
		);

		foreach ( $answers as $index => $answer ) {
			$answer_meta = $this->get_answer_meta( $answer['id'] );
			$image_url   = isset( $answer_meta['image_url'] ) ? $answer_meta['image_url'] : '';

			$html .= '<div class="reorder-item" style="padding: 12px; margin: 10px 0; background: #f4f5f7; border-radius: 8px; border-left: 4px solid #28a745;">';
			$html .= '<span style="font-weight: bold; margin-right: 10px;">' . ( $index + 1 ) . '.</span>';

			if ( $image_url ) {
				$html .= '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $answer['answer'] ) . '" style="max-width: 100px; height: auto; margin-right: 10px; vertical-align: middle;" />';
			}

			$html .= '<span>' . esc_html( $answer['answer'] ) . '</span>';
			$html .= '</div>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate statement HTML.
	 *
	 * @param int   $question_id Question ID.
	 * @param array $answers Answers array.
	 * @return string HTML.
	 */
	private function generate_statement_html( $question_id, $answers ) {
		$html  = '<div class="statement-question" style="padding: 15px; background: #f0f8ff; border-left: 4px solid #007bff; border-radius: 5px;">';
		$html .= '<p style="margin: 0; font-style: italic;">' . __( 'This is an informational statement. No answer required.', 'ohmylms' ) . '</p>';

		if ( ! empty( $answers[0] ) && ! empty( $answers[0]['answer'] ) ) {
			$html .= '<p style="margin: 10px 0 0 0;">' . esc_html( $answers[0]['answer'] ) . '</p>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Get answer metadata.
	 *
	 * @param int $answer_id Answer ID.
	 * @return array Answer metadata.
	 */
	private function get_answer_meta( $answer_id ) {
		global $wpdb;

		$meta_query = $wpdb->prepare(
			'
            SELECT meta_key, meta_value 
            FROM %i
            WHERE answer_id = %d
        ',
			$wpdb->prefix . 'ohmylms_question_answermeta',
			$answer_id
		);

		$meta_results = $wpdb->get_results( $meta_query, ARRAY_A );

		$meta = array();
		foreach ( $meta_results as $meta_entry ) {
			$meta[ $meta_entry['meta_key'] ] = maybe_unserialize( $meta_entry['meta_value'] );
		}

		return $meta;
	}

	/**
	 * Generate standard HTML template.
	 *
	 * @param string $title Page title.
	 * @param string $body Page body content.
	 * @return string Complete HTML.
	 */
	private function generate_html_content( $title, $body ) {
		$scorm_api = '1.2' === $this->scorm_version ? 'API' : 'API_1484_11';

		return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . esc_html( $title ) . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
            color: #333;
        }
        h1, h2, h3 { color: #2c3e50; }
        img { max-width: 100%; height: auto; }
        .scorm-quiz { margin: 20px 0; }
        .quiz-question { 
            margin: 20px 0; 
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .question-answers { margin: 10px 0; }
        .answer { 
            margin: 8px 0;
            padding: 8px;
            background: white;
            border-radius: 3px;
        }
        .answer input { margin-right: 8px; }
    </style>
    <script>
        var scormAPI = null;
        
        function findAPI(win) {
            var findAPITries = 0;
            while ((win.' . $scorm_api . ' == null) && (win.parent != null) && (win.parent != win)) {
                findAPITries++;
                if (findAPITries > 7) return null;
                win = win.parent;
            }
            return win.' . $scorm_api . ';
        }
        
        function initializeSCORM() {
            scormAPI = findAPI(window);
            if (scormAPI != null) {
                scormAPI.LMSInitialize("");
                scormAPI.LMSSetValue("cmi.core.lesson_status", "incomplete");
            }
        }
        
        function finishSCORM() {
            if (scormAPI != null) {
                scormAPI.LMSSetValue("cmi.core.lesson_status", "completed");
                scormAPI.LMSFinish("");
            }
        }
        
        window.onload = function() {
            initializeSCORM();
        };
        
        window.onbeforeunload = function() {
            finishSCORM();
        };
    </script>
</head>
<body>
    <h1>' . esc_html( $title ) . '</h1>
    <div class="content">
        ' . $body . '
    </div>
</body>
</html>';
	}

	/**
	 * Process and download media files.
	 *
	 * @param \WP_Post $post Post object.
	 * @param string   $content_dir Content directory.
	 * @return void
	 */
	private function process_media_files( $post, $content_dir ) {
		// Create media directory.
		$media_dir = $content_dir . '/media';
		wp_mkdir_p( $media_dir );

		// Get featured image.
		$thumbnail_id = get_post_thumbnail_id( $post->ID );
		if ( $thumbnail_id ) {
			$this->copy_attachment( $thumbnail_id, $media_dir );
		}

		// Get video attachment.
		$video_id = get_post_meta( $post->ID, '_video_id', true );
		if ( $video_id ) {
			$this->copy_attachment( $video_id, $media_dir );
		}

		// Extract and download images from content.
		preg_match_all( '/<img[^>]+src=[\'"]([^\'"]+)[\'"]/', $post->post_content, $matches );
		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $img_url ) {
				$this->download_remote_file( $img_url, $media_dir );
			}
		}
	}

	/**
	 * Copy attachment to directory.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $target_dir Target directory.
	 * @return void
	 */
	private function copy_attachment( $attachment_id, $target_dir ) {
		$file_path = get_attached_file( $attachment_id );

		if ( $file_path && file_exists( $file_path ) ) {
			$filename    = basename( $file_path );
			$target_path = $target_dir . '/' . $filename;
			copy( $file_path, $target_path );
		}
	}

	/**
	 * Download remote file to directory.
	 *
	 * @param string $url File URL.
	 * @param string $target_dir Target directory.
	 * @return void
	 */
	private function download_remote_file( $url, $target_dir ) {
		$filename = basename( wp_parse_url( $url, PHP_URL_PATH ) );

		if ( '' === $filename || '.' === $filename || '..' === $filename ) {
			return;
		}

		$target_path = $target_dir . '/' . $filename;

		// Only copy if it's a local WordPress upload.
		$upload_dir = wp_upload_dir();
		if ( strpos( $url, $upload_dir['baseurl'] ) !== 0 ) {
			return;
		}

		$local_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $url );

		// The URL comes from course content, so a traversal sequence could
		// otherwise walk out of the uploads directory and pull an arbitrary
		// file into the exported package.
		$local_path = preg_replace( '/[?#].*$/', '', $local_path );
		$real_path  = realpath( rawurldecode( $local_path ) );
		$base_dir   = realpath( $upload_dir['basedir'] );

		if ( false === $real_path || false === $base_dir || ! is_file( $real_path ) ) {
			return;
		}

		if ( 0 !== strpos( $real_path, rtrim( $base_dir, '/' ) . '/' ) ) {
			return;
		}

		copy( $real_path, $target_path );
	}

	/**
	 * Get course chapters with contents.
	 *
	 * @param int $course_id Course ID.
	 * @return array Chapters data.
	 */
	private function get_course_chapters( $course_id ) {
		global $wpdb;

		$chapters = array();

		// Fetch chapters.
		$chapter_query = $wpdb->prepare(
			'
            SELECT * 
            FROM %i
            WHERE course_id = %d 
            ORDER BY order_number ASC
        ',
			$wpdb->prefix . 'ohmylms_chapter_relationship',
			$course_id
		);

		$chapter_results = $wpdb->get_results( $chapter_query, ARRAY_A );

		foreach ( $chapter_results as $chapter ) {
			$chapter_id = $chapter['chapter_id'];

			// Fetch contents for each chapter.
			$content_query = $wpdb->prepare(
				'
                SELECT * 
                FROM %i
                WHERE chapter_id = %d 
                ORDER BY order_number ASC
            ',
				$wpdb->prefix . 'ohmylms_content_relationship',
				$chapter_id
			);

			$content_results = $wpdb->get_results( $content_query, ARRAY_A );

			$chapters[] = array(
				'chapter_id' => $chapter_id,
				'contents'   => $content_results,
			);
		}

		return $chapters;
	}

	/**
	 * Create ZIP archive of SCORM package.
	 *
	 * @return string Path to ZIP file.
	 * @throws \Exception When the operation cannot be completed.*/
	private function create_zip_package() {
		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new \Exception( __( 'ZipArchive class is not available. Please enable the ZIP extension in PHP.', 'ohmylms' ) );
		}

		$upload_dir = wp_upload_dir();

		// Generate a clean filename.
		$course_titles = array();
		foreach ( $this->course_ids as $course_id ) {
			$course = get_post( $course_id );
			if ( $course ) {
				$course_titles[] = $course->post_title;
			}
		}

		$base_filename = count( $course_titles ) === 1
			? ScormHelper::sanitize_scorm_filename( $course_titles[0] )
			: 'multiple-courses';

		$zip_filename = $base_filename . '-scorm-' . $this->scorm_version . '-' . time() . '.zip';
		$zip_path     = trailingslashit( $upload_dir['basedir'] ) . $zip_filename;

		$zip = new \ZipArchive();
		if ( $zip->open( $zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) !== true ) {
			throw new \Exception( __( 'Failed to create ZIP archive.', 'ohmylms' ) );
		}

		// Add all files from temp directory to ZIP.
		$this->add_directory_to_zip( $zip, $this->temp_dir, '' );

		$zip->close();

		ScormHelper::log(
			'ZIP package created',
			'info',
			array(
				'filename' => $zip_filename,
				'size'     => size_format( filesize( $zip_path ) ),
			)
		);

		return $zip_path;
	}

	/**
	 * Recursively add directory to ZIP archive.
	 *
	 * @param \ZipArchive $zip ZIP archive object.
	 * @param string      $source_dir Source directory.
	 * @param string      $zip_path Path within ZIP.
	 * @return void
	 */
	private function add_directory_to_zip( $zip, $source_dir, $zip_path ) {
		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $source_dir ),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ( $files as $file ) {
			if ( ! $file->isDir() ) {
				$file_path     = $file->getRealPath();
				$relative_path = substr( $file_path, strlen( $source_dir ) + 1 );

				$zip->addFile( $file_path, $zip_path . $relative_path );
			}
		}
	}

	/**
	 * Send ZIP file for download.
	 *
	 * @param string $zip_path Path to ZIP file.
	 * @return void
	 */
	private function send_download( $zip_path ) {
		if ( ! file_exists( $zip_path ) ) {
			wp_die( esc_html__( 'SCORM package file not found.', 'ohmylms' ) );
		}

		// Clean output buffer.
		if ( ob_get_level() ) {
			ob_end_clean();
		}

		// Set headers for download.
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . basename( $zip_path ) . '"' );
		header( 'Content-Length: ' . filesize( $zip_path ) );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Pragma: no-cache' );

		// Output file.
		readfile( $zip_path );

		// Cleanup.
		$this->cleanup();
		@unlink( $zip_path );

		exit;
	}

	/**
	 * Clean up temporary files.
	 *
	 * @return void
	 */
	private function cleanup() {
		if ( is_dir( $this->temp_dir ) ) {
			$this->delete_directory( $this->temp_dir );
		}
	}

	/**
	 * Recursively delete directory.
	 *
	 * @param string $dir Directory path.
	 * @return void
	 */
	private function delete_directory( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files = array_diff( scandir( $dir ), array( '.', '..' ) );

		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;
			is_dir( $path ) ? $this->delete_directory( $path ) : unlink( $path );
		}

		rmdir( $dir );
	}

	/**
	 * Destructor - ensure cleanup.
	 */
	public function __destruct() {
		$this->cleanup();
	}
}
