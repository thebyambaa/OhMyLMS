<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class ScormImporter.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Importers;

use OhMyLMS\Utility\ScormHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Class ScormImporter
 *
 * Imports SCORM 1.2 and SCORM 2004 packages and converts them to OhMyLMS courses.
 * Handles manifest parsing, content extraction, media import, and course structure creation.
 */
class ScormImporter {

	/**
	 * Uploaded file data
	 *
	 * @var array
	 */
	private $file;

	/**
	 * Temporary directory for extracting SCORM package
	 *
	 * @var string
	 */
	private $temp_dir;

	/**
	 * Parsed manifest data
	 *
	 * @var array
	 */
	private $manifest_data;

	/**
	 * SCORM version detected
	 *
	 * @var string
	 */
	private $scorm_version;

	/**
	 * Import summary report
	 *
	 * @var array
	 */
	private $import_report;

	/**
	 * Media mapping (SCORM file path => WordPress attachment ID)
	 *
	 * @var array
	 */
	private $media_map;

	/**
	 * Default chapter ID for flat structures
	 *
	 * @var int|null
	 */
	private $default_chapter_id = null;

	/**
	 * ScormImporter constructor.
	 *
	 * @param array $file Uploaded file data from $_FILES.
	 * @throws \Exception When the operation cannot be completed.*/
	public function __construct( $file ) {
		if ( ! isset( $file['tmp_name'] ) || empty( $file['tmp_name'] ) ) {
			throw new \Exception( __( 'No file uploaded.', 'ohmylms' ) );
		}

		$this->file          = $file;
		$this->media_map     = array();
		$this->import_report = array(
			'success'             => true,
			'course_ids'          => array(),
			'courses_created'     => 0,
			'chapters_created'    => 0,
			'lessons_created'     => 0,
			'quizzes_created'     => 0,
			'assignments_created' => 0,
			'media_imported'      => 0,
			'skipped_items'       => array(),
			'errors'              => array(),
			'warnings'            => array(),
		);

		// Create temporary directory.
		$upload_dir     = wp_upload_dir();
		$this->temp_dir = trailingslashit( $upload_dir['basedir'] ) . 'scorm-import-' . time() . '-' . wp_rand();

		if ( ! wp_mkdir_p( $this->temp_dir ) ) {
			throw new \Exception( __( 'Failed to create temporary directory for SCORM import.', 'ohmylms' ) );
		}

		ScormHelper::log(
			'SCORM import initialized',
			'info',
			array(
				'filename' => $file['name'],
				'size'     => size_format( $file['size'] ),
			)
		);
	}

	/**
	 * Import SCORM package and create course.
	 *
	 * @return array Import report with course ID and statistics.
	 * @throws \Exception When the operation cannot be completed.*/
	public function import_scorm_package() {
		try {
			ScormHelper::log( 'Starting SCORM import', 'info' );

			// Set time limit for large packages.
			if ( ! ini_get( 'safe_mode' ) ) {
				set_time_limit( 300 );
			}

			$this->validate_file();
			$this->extract_package();
			$this->parse_manifest();
			$this->validate_scorm_structure();

			if ( empty( $this->manifest_data['organizations'] ) ) {
				throw new \Exception( __( 'No organizations found in SCORM package.', 'ohmylms' ) );
			}

			foreach ( $this->manifest_data['organizations'] as $organization ) {
				$course_id                           = $this->create_course( $organization );
				$this->import_report['course_ids'][] = $course_id;
				++$this->import_report['courses_created'];

				$this->import_course_structure( $course_id, $organization );
				$this->default_chapter_id = null;
			}

			$this->cleanup();

			ScormHelper::log( 'SCORM import completed successfully', 'info', $this->import_report );

			return $this->import_report;

		} catch ( \Exception $e ) {
			ScormHelper::log( 'SCORM import failed', 'error', array( 'error' => $e->getMessage() ) );
			$this->cleanup();
			$this->import_report['success']  = false;
			$this->import_report['errors'][] = $e->getMessage();
			throw $e;
		}
	}

	/**
	 * Validate uploaded file.
	 *
	 * @throws \Exception When the operation cannot be completed.*/
	private function validate_file() {
		$max_size = wp_max_upload_size();
		if ( $this->file['size'] > $max_size ) {
			throw new \Exception(
				sprintf(
					/* translators: %s: text value. */
					__( 'File size exceeds maximum upload size of %s.', 'ohmylms' ),
					size_format( $max_size )
				)
			);
		}

		$allowed_types = array( 'application/zip', 'application/x-zip-compressed', 'multipart/x-zip' );
		$file_type     = $this->file['type'];
		$file_ext      = strtolower( pathinfo( $this->file['name'], PATHINFO_EXTENSION ) );

		if ( ! in_array( $file_type, $allowed_types ) && 'zip' !== $file_ext ) {
			throw new \Exception( __( 'Invalid file type. Please upload a ZIP file containing a SCORM package.', 'ohmylms' ) );
		}

		if ( UPLOAD_ERR_OK !== $this->file['error'] ) {
			throw new \Exception( __( 'File upload error. Please try again.', 'ohmylms' ) );
		}
	}

	/**
	 * Extract SCORM ZIP package.
	 *
	 * @throws \Exception When the operation cannot be completed.*/
	private function extract_package() {
		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new \Exception( __( 'ZipArchive class is not available. Please enable the ZIP extension in PHP.', 'ohmylms' ) );
		}

		$zip    = new \ZipArchive();
		$result = $zip->open( $this->file['tmp_name'] );

		if ( true !== $result ) {
			throw new \Exception( __( 'Failed to open SCORM package. The ZIP file may be corrupted.', 'ohmylms' ) );
		}

		$zip->extractTo( $this->temp_dir );
		$zip->close();

		ScormHelper::log( 'SCORM package extracted', 'info', array( 'temp_dir' => $this->temp_dir ) );
	}

	/**
	 * Parse imsmanifest.xml file.
	 *
	 * @throws \Exception When the operation cannot be completed.*/
	private function parse_manifest() {
		$manifest_path = $this->find_manifest_file();

		if ( ! $manifest_path ) {
			throw new \Exception( __( 'imsmanifest.xml not found in SCORM package.', 'ohmylms' ) );
		}

		libxml_use_internal_errors( true );
		$xml = simplexml_load_file( $manifest_path );

		if ( false === $xml ) {
			libxml_clear_errors();
			throw new \Exception( __( 'Failed to parse imsmanifest.xml. The file may be corrupted or invalid.', 'ohmylms' ) );
		}

		$namespaces = $xml->getNamespaces( true );
		foreach ( $namespaces as $prefix => $namespace ) {
			if ( '' === $prefix ) {
				$xml->registerXPathNamespace( 'default', $namespace );
			}
		}

		$this->detect_scorm_version( $xml );
		$this->manifest_data = $this->parse_manifest_data( $xml );

		ScormHelper::log(
			'Manifest parsed',
			'info',
			array(
				'version' => $this->scorm_version,
				'title'   => $this->manifest_data['title'],
			)
		);
	}

	/**
	 * Find imsmanifest.xml file in extracted package.
	 *
	 * @return string|false Path to manifest file or false if not found.
	 */
	private function find_manifest_file() {
		$manifest_path = $this->temp_dir . '/imsmanifest.xml';
		if ( file_exists( $manifest_path ) ) {
			return $manifest_path;
		}

		$files = glob( $this->temp_dir . '/*/imsmanifest.xml' );
		if ( ! empty( $files ) ) {
			$this->temp_dir = dirname( $files[0] );
			return $files[0];
		}

		return false;
	}

	/**
	 * Detect SCORM version from manifest.
	 *
	 * @param \SimpleXMLElement $xml Manifest XML.
	 */
	private function detect_scorm_version( $xml ) {
		$schema_version = (string) $xml->metadata->schemaversion;

		if ( strpos( $schema_version, '1.2' ) !== false ) {
			$this->scorm_version = '1.2';
		} elseif ( strpos( $schema_version, '2004' ) !== false || strpos( $schema_version, 'CAM' ) !== false ) {
			$this->scorm_version = '2004';
		} else {
			$namespaces = $xml->getNamespaces( true );
			foreach ( $namespaces as $namespace ) {
				if ( strpos( $namespace, 'adlcp_rootv1p2' ) !== false ) {
					$this->scorm_version = '1.2';
					break;
				} elseif ( strpos( $namespace, 'adlcp_v1p3' ) !== false ) {
					$this->scorm_version = '2004';
					break;
				}
			}
		}

		if ( ! $this->scorm_version ) {
			$this->scorm_version               = '1.2';
			$this->import_report['warnings'][] = __( 'SCORM version could not be detected. Assuming SCORM 1.2.', 'ohmylms' );
		}
	}

	/**
	 * Parse manifest data into structured array.
	 *
	 * @param \SimpleXMLElement $xml Manifest XML.
	 * @return array Parsed manifest data.
	 */
	private function parse_manifest_data( $xml ) {
		$data = array(
			'title'         => '',
			'description'   => '',
			'metadata'      => array(),
			'organizations' => array(),
		);

		$data['metadata']    = $this->parse_metadata( $xml );
		$data['title']       = $data['metadata']['title'] ?? __( 'Imported SCORM Course', 'ohmylms' );
		$data['description'] = $data['metadata']['description'] ?? '';

		$organizations = $xml->organizations->organization;
		if ( $organizations ) {
			foreach ( $organizations as $org ) {
				$data['organizations'][] = $this->parse_organization( $org );
			}
		}

		$data['resources'] = $this->parse_resources( $xml );

		return $data;
	}

	/**
	 * Parse metadata from manifest.
	 *
	 * @param \SimpleXMLElement $xml Manifest XML.
	 * @return array Metadata.
	 */
	private function parse_metadata( $xml ) {
		$metadata = array();

		if ( isset( $xml->metadata ) ) {
			$lom = $xml->metadata->children( 'http://www.imsglobal.org/xsd/imsmd_v1p2' );

			if ( $lom->general ) {
				$metadata['title']       = (string) $lom->general->title->langstring;
				$metadata['description'] = (string) $lom->general->description->langstring;
				$metadata['language']    = (string) $lom->general->language;
				$metadata['keywords']    = array();

				if ( $lom->general->keyword ) {
					foreach ( $lom->general->keyword as $keyword ) {
						$metadata['keywords'][] = (string) $keyword->langstring;
					}
				}
			}
		}

		return $metadata;
	}

	/**
	 * Parse organization structure.
	 *
	 * @param \SimpleXMLElement $org Organization element.
	 * @return array Organization data.
	 */
	private function parse_organization( $org ) {
		$organization = array(
			'identifier'  => (string) $org['identifier'],
			'title'       => (string) $org->title,
			'description' => '',
			'items'       => array(),
		);

		if ( isset( $org->metadata ) ) {
			$lom = $org->metadata->children( 'http://www.imsglobal.org/xsd/imsmd_v1p2' );
			if ( $lom->general && $lom->general->description ) {
				$organization['description'] = (string) $lom->general->description->langstring;
			}
		}

		if ( $org->item ) {
			foreach ( $org->item as $item ) {
				$organization['items'][] = $this->parse_item( $item );
			}
		}

		return $organization;
	}

	/**
	 * Parse item element (chapter/lesson).
	 *
	 * @param \SimpleXMLElement $item Item element.
	 * @param int               $depth Nesting depth.
	 * @return array Item data.
	 */
	private function parse_item( $item, $depth = 0 ) {
		$item_data = array(
			'identifier'    => (string) $item['identifier'],
			'identifierref' => (string) $item['identifierref'],
			'title'         => (string) $item->title,
			'description'   => '',
			'content_type'  => '',
			'type'          => $this->determine_item_type( $item, $depth ),
			'children'      => array(),
		);

		if ( isset( $item->metadata ) ) {
			$lom = $item->metadata->children( 'http://www.imsglobal.org/xsd/imsmd_v1p2' );
			if ( $lom->general && $lom->general->description ) {
				$item_data['description'] = (string) $lom->general->description->langstring;
			}

			if ( $lom->general && $lom->general->keyword ) {
				foreach ( $lom->general->keyword as $keyword ) {
					$keyword_text = (string) $keyword->langstring;
					if ( strpos( $keyword_text, 'content-type:' ) === 0 ) {
						$item_data['content_type'] = str_replace( 'content-type:', '', $keyword_text );

						if ( 'ohmylms-assignment' === $item_data['content_type'] ) {
							$item_data['type'] = 'assignment';
						} elseif ( 'ohmylms-quiz' === $item_data['content_type'] ) {
							$item_data['type'] = 'quiz';
						} elseif ( 'ohmylms-lesson' === $item_data['content_type'] ) {
							$item_data['type'] = 'lesson';
						}
						break;
					}
				}
			}
		}

		if ( $item->item ) {
			foreach ( $item->item as $child_item ) {
				$item_data['children'][] = $this->parse_item( $child_item, $depth + 1 );
			}
		}

		return $item_data;
	}

	/**
	 * Determine item type (chapter, lesson, quiz, etc.).
	 *
	 * @param \SimpleXMLElement $item Item element.
	 * @param int               $depth Nesting depth.
	 * @return string Item type.
	 */
	private function determine_item_type( $item, $depth ) {
		if ( count( $item->item ) > 0 ) {
			return 'chapter';
		}

		$identifierref = (string) $item['identifierref'];
		if ( empty( $identifierref ) ) {
			return 'chapter';
		}

		$title = strtolower( (string) $item->title );

		$quiz_patterns = array( 'quiz', 'test', 'exam', 'assessment', 'evaluation' );
		foreach ( $quiz_patterns as $pattern ) {
			if ( strpos( $title, $pattern ) !== false ) {
				return 'quiz';
			}
		}

		$assignment_patterns = array( 'assignment', 'homework', 'task', 'exercise', 'project' );
		foreach ( $assignment_patterns as $pattern ) {
			if ( strpos( $title, $pattern ) !== false ) {
				return 'assignment';
			}
		}

		if ( $identifierref && isset( $this->manifest_data['resources'][ $identifierref ] ) ) {
			$resource = $this->manifest_data['resources'][ $identifierref ];

			if ( isset( $resource['adlcp:scormtype'] ) && 'assessment' === $resource['adlcp:scormtype'] ) {
				return 'quiz';
			}

			if ( isset( $resource['href'] ) ) {
				$html_file = $this->temp_dir . '/' . $resource['href'];
				if ( file_exists( $html_file ) ) {
					$content = file_get_contents( $html_file );

					if ( preg_match( '/<form[^>]*>/i', $content ) &&
						( preg_match( '/<input[^>]*type=["\']radio["\'][^>]*>/i', $content ) ||
							preg_match( '/<input[^>]*type=["\']checkbox["\'][^>]*>/i', $content ) ) ) {
						return 'quiz';
					}
				}
			}
		}

		return 'lesson';
	}

	/**
	 * Parse resources from manifest.
	 *
	 * @param \SimpleXMLElement $xml Manifest XML.
	 * @return array Resources indexed by identifier.
	 */
	private function parse_resources( $xml ) {
		$resources = array();

		if ( $xml->resources->resource ) {
			foreach ( $xml->resources->resource as $resource ) {
				$identifier               = (string) $resource['identifier'];
				$resources[ $identifier ] = array(
					'identifier'      => $identifier,
					'type'            => (string) $resource['type'],
					'href'            => (string) $resource['href'],
					'adlcp:scormtype' => (string) $resource['adlcp:scormtype'],
					'files'           => array(),
				);

				if ( $resource->file ) {
					foreach ( $resource->file as $file ) {
						$resources[ $identifier ]['files'][] = (string) $file['href'];
					}
				}
			}
		}

		return $resources;
	}

	/**
	 * Validate SCORM structure.
	 *
	 * @throws \Exception When the operation cannot be completed.*/
	private function validate_scorm_structure() {
		if ( empty( $this->manifest_data['organizations'] ) ) {
			throw new \Exception( __( 'No content organization found in SCORM package.', 'ohmylms' ) );
		}

		if ( empty( $this->manifest_data['title'] ) ) {
			$this->import_report['warnings'][] = __( 'No course title found in manifest. Using default title.', 'ohmylms' );
		}
	}

	/**
	 * Create a course from organization data.
	 *
	 * @param array $organization Organization data from manifest.
	 * @return int Course ID.
	 * @throws \Exception When the operation cannot be completed.*/
	private function create_course( $organization ) {
		$title       = ! empty( $organization['title'] ) ? $organization['title'] : $this->manifest_data['title'];
		$description = ! empty( $organization['description'] ) ? $organization['description'] : $this->manifest_data['description'];

		$course_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_content' => $description,
				'post_status'  => 'draft',
				'post_type'    => OHMYLMS_COURSE_CPT,
			)
		);

		if ( is_wp_error( $course_id ) ) {
			throw new \Exception( __( 'Failed to create course: ', 'ohmylms' ) . $course_id->get_error_message() );
		}

		update_post_meta( $course_id, '_imported_from_scorm', true );
		update_post_meta( $course_id, '_scorm_version', $this->scorm_version );
		update_post_meta( $course_id, '_scorm_organization_id', $organization['identifier'] );
		update_post_meta( $course_id, '_import_date', current_time( 'mysql' ) );

		if ( ! empty( $this->manifest_data['metadata']['keywords'] ) ) {
			wp_set_object_terms( $course_id, $this->manifest_data['metadata']['keywords'], 'course-tag' );
		}

		ScormHelper::log(
			'Course created',
			'info',
			array(
				'course_id'      => $course_id,
				'title'          => $title,
				'org_identifier' => $organization['identifier'],
			)
		);

		return $course_id;
	}

	/**
	 * Import course structure (chapters, lessons, quizzes).
	 *
	 * @param int   $course_id Course ID.
	 * @param array $organization Organization data.
	 */
	private function import_course_structure( $course_id, $organization ) {
		$has_chapters = false;
		foreach ( $organization['items'] as $item ) {
			if ( 'chapter' === $item['type'] || ! empty( $item['children'] ) ) {
				$has_chapters = true;
				break;
			}
		}

		if ( ! $has_chapters ) {
			$default_chapter_id = $this->create_default_chapter( $course_id );
			$content_order      = 1;
			foreach ( $organization['items'] as $item ) {
				$this->import_content( $item, $course_id, $default_chapter_id, $content_order );
				++$content_order;
			}
		} else {
			$chapter_order = 1;
			foreach ( $organization['items'] as $item ) {
				$this->import_item( $item, $course_id, null, $chapter_order );
				++$chapter_order;
			}
		}
	}

	/**
	 * Create a default chapter for flat SCORM structures.
	 *
	 * @param int $course_id Course ID.
	 * @return int Chapter ID.
	 * @throws \Exception When the operation cannot be completed.*/
	private function create_default_chapter( $course_id ) {
		if ( $this->default_chapter_id ) {
			return $this->default_chapter_id;
		}

		$chapter_id = wp_insert_post(
			array(
				'post_title'  => __( 'Course Content', 'ohmylms' ),
				'post_status' => 'publish',
				'post_type'   => OHMYLMS_CHAPTER_CPT,
			)
		);

		if ( is_wp_error( $chapter_id ) ) {
			throw new \Exception( __( 'Failed to create default chapter.', 'ohmylms' ) );
		}

		global $wpdb;
		$wpdb->insert(
			"{$wpdb->prefix}ohmylms_chapter_relationship",
			array(
				'course_id'    => $course_id,
				'chapter_id'   => $chapter_id,
				'order_number' => 1,
			)
		);

		++$this->import_report['chapters_created'];
		$this->default_chapter_id = $chapter_id;

		ScormHelper::log( 'Default chapter created', 'info', array( 'chapter_id' => $chapter_id ) );

		return $chapter_id;
	}

	/**
	 * Import a single item (chapter or content).
	 *
	 * @param array    $item Item data.
	 * @param int      $course_id Course ID.
	 * @param int|null $parent_chapter_id Parent chapter ID.
	 * @param int      $order Order number.
	 */
	private function import_item( $item, $course_id, $parent_chapter_id, $order ) {
		if ( 'chapter' === $item['type'] ) {
			$this->import_chapter( $item, $course_id, $order );
		} else {
			if ( ! $parent_chapter_id ) {
				$parent_chapter_id = $this->create_default_chapter( $course_id );
			}
			$this->import_content( $item, $course_id, $parent_chapter_id, $order );
		}
	}

	/**
	 * Import chapter with contents.
	 *
	 * @param array $item Chapter data.
	 * @param int   $course_id Course ID.
	 * @param int   $order Order number.
	 */
	private function import_chapter( $item, $course_id, $order ) {
		$chapter_id = wp_insert_post(
			array(
				'post_title'   => $item['title'],
				'post_content' => $item['description'] ?? '',
				'post_status'  => 'publish',
				'post_type'    => OHMYLMS_CHAPTER_CPT,
			)
		);

		if ( is_wp_error( $chapter_id ) ) {
			$this->import_report['errors'][] = sprintf(
				/* translators: %s: text value. */
				__( 'Failed to create chapter: %s', 'ohmylms' ),
				$item['title']
			);
			return;
		}

		global $wpdb;
		$wpdb->insert(
			"{$wpdb->prefix}ohmylms_chapter_relationship",
			array(
				'course_id'    => $course_id,
				'chapter_id'   => $chapter_id,
				'order_number' => $order,
			)
		);

		++$this->import_report['chapters_created'];

		$content_order = 1;
		foreach ( $item['children'] as $child_item ) {
			$this->import_content( $child_item, $course_id, $chapter_id, $content_order );
			++$content_order;
		}
	}

	/**
	 * Import content (lesson, quiz, or assignment).
	 *
	 * @param array $item Content data.
	 * @param int   $course_id Course ID.
	 * @param int   $chapter_id Chapter ID.
	 * @param int   $order Order number.
	 */
	private function import_content( $item, $course_id, $chapter_id, $order ) {
		$resource = null;
		if ( $item['identifierref'] && isset( $this->manifest_data['resources'][ $item['identifierref'] ] ) ) {
			$resource = $this->manifest_data['resources'][ $item['identifierref'] ];
		}

		if ( ! $resource ) {
			$this->import_report['skipped_items'][] = sprintf(
				/* translators: %s: text value. */
				__( 'No resource found for item: %s', 'ohmylms' ),
				$item['title']
			);
			return;
		}

		if ( 'quiz' === $item['type'] ) {
			$this->import_quiz( $item, $resource, $course_id, $chapter_id, $order );
		} elseif ( 'assignment' === $item['type'] ) {
			$this->import_assignment( $item, $resource, $course_id, $chapter_id, $order );
		} else {
			$this->import_lesson( $item, $resource, $course_id, $chapter_id, $order );
		}
	}

	/**
	 * Import lesson from SCORM content.
	 *
	 * @param array $item Item data.
	 * @param array $resource Resource data.
	 * @param int   $course_id Course ID.
	 * @param int   $chapter_id Chapter ID.
	 * @param int   $order Order number.
	 */
	private function import_lesson( $item, $resource, $course_id, $chapter_id, $order ) {
		$html_file   = $this->temp_dir . '/' . $resource['href'];
		$content     = '';
		$raw_content = '';

		if ( file_exists( $html_file ) ) {
			$raw_content      = file_get_contents( $html_file );
			$lesson_type_info = $this->detect_lesson_type_from_content( $raw_content, $resource );
			$content          = $this->process_scorm_html( $raw_content, $item['title'] );
		} else {
			$this->import_report['warnings'][] = sprintf(
				/* translators: %s: text value. */
				__( 'Content file not found for lesson: %s', 'ohmylms' ),
				$item['title']
			);
			$lesson_type_info                  = array( 'type' => 'text' );
		}

		$lesson_id = wp_insert_post(
			array(
				'post_title'   => $item['title'],
				'post_content' => $content,
				'post_status'  => 'publish',
				'post_type'    => 'ohmylms-lesson',
			)
		);

		if ( is_wp_error( $lesson_id ) ) {
			$this->import_report['errors'][] = sprintf(
				/* translators: %1$s: text value; %2$s: text value. */
				__( 'Failed to create lesson: %1$s - %2$s', 'ohmylms' ),
				$item['title'],
				$lesson_id->get_error_message()
			);
			ScormHelper::log(
				'Failed to create lesson',
				'error',
				array(
					'title' => $item['title'],
					'error' => $lesson_id->get_error_message(),
				)
			);
			return;
		}

		update_post_meta( $lesson_id, '_type', $lesson_type_info['type'] );

		if ( 'video' === $lesson_type_info['type'] && ! empty( $lesson_type_info['video_id'] ) ) {
			update_post_meta( $lesson_id, '_video_id', $lesson_type_info['video_id'] );
		} elseif ( 'video' === $lesson_type_info['type'] && ! empty( $lesson_type_info['external_url'] ) ) {
			update_post_meta( $lesson_id, '_external_url', $lesson_type_info['external_url'] );
		} elseif ( 'audio' === $lesson_type_info['type'] && ! empty( $lesson_type_info['audio_id'] ) ) {
			update_post_meta( $lesson_id, '_audio_id', $lesson_type_info['audio_id'] );
		}

		global $wpdb;
		$result = $wpdb->insert(
			"{$wpdb->prefix}ohmylms_content_relationship",
			array(
				'chapter_id'   => $chapter_id,
				'content_id'   => $lesson_id,
				'content_type' => $lesson_type_info['type'],
				'order_number' => $order,
			),
			array( '%d', '%d', '%s', '%d' )
		);

		if ( false === $result ) {
			$error_msg                       = $wpdb->last_error ? $wpdb->last_error : 'Unknown database error';
			$this->import_report['errors'][] = sprintf(
				/* translators: %1$s: text value; %2$s: text value. */
				__( 'Failed to link lesson to chapter: %1$s - %2$s', 'ohmylms' ),
				$item['title'],
				$error_msg
			);
			ScormHelper::log(
				'Failed to link lesson to chapter',
				'error',
				array(
					'lesson_id'  => $lesson_id,
					'chapter_id' => $chapter_id,
					'error'      => $error_msg,
				)
			);
		} else {
			++$this->import_report['lessons_created'];
			ScormHelper::log(
				'Lesson imported',
				'info',
				array(
					'lesson_id'       => $lesson_id,
					'title'           => $item['title'],
					'type'            => $lesson_type_info['type'],
					'chapter_id'      => $chapter_id,
					'relationship_id' => $wpdb->insert_id,
				)
			);
		}
	}

	/**
	 * Detect lesson type from HTML content and resources.
	 *
	 * @param string $html HTML content.
	 * @param array  $resource Resource data.
	 * @return array Lesson type information.
	 */
	private function detect_lesson_type_from_content( $html, $resource ) {
		$result = array( 'type' => 'text' );

		$video_found = $this->detect_video_in_content( $html, $resource );
		if ( $video_found ) {
			$result['type'] = 'video';
			if ( ! empty( $video_found['video_id'] ) ) {
				$result['video_id'] = $video_found['video_id'];
			}
			if ( ! empty( $video_found['external_url'] ) ) {
				$result['external_url'] = $video_found['external_url'];
			}
			return $result;
		}

		$audio_found = $this->detect_audio_in_content( $html, $resource );
		if ( $audio_found ) {
			$result['type'] = 'audio';
			if ( ! empty( $audio_found['audio_id'] ) ) {
				$result['audio_id'] = $audio_found['audio_id'];
			}
			return $result;
		}

		return $result;
	}

	/**
	 * Detect video content in HTML.
	 *
	 * @param string $html HTML content.
	 * @param array  $resource Resource data.
	 * @return array|false Video information or false.
	 */
	private function detect_video_in_content( $html, $resource ) {
		if ( preg_match( '/<video[^>]*>/i', $html ) ) {
			if ( preg_match( '/<source[^>]+src=["\']([^"\']+\.(?:mp4|webm|ogg))["\'][^>]*>/i', $html, $matches ) ) {
				$video_id = $this->import_media_file( $matches[1] );
				if ( $video_id ) {
					return array( 'video_id' => $video_id );
				}
			}
		}

		if ( preg_match( '/<iframe[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $matches ) ) {
			$iframe_url = $matches[1];
			if ( preg_match( '/(?:youtube\.com|youtu\.be|vimeo\.com|dailymotion\.com)/i', $iframe_url ) ) {
				return array( 'external_url' => $iframe_url );
			}
		}

		if ( ! empty( $resource['files'] ) ) {
			foreach ( $resource['files'] as $file ) {
				if ( preg_match( '/\.(?:mp4|webm|ogg|avi|mov)$/i', $file ) ) {
					$video_id = $this->import_media_file( $file );
					if ( $video_id ) {
						return array( 'video_id' => $video_id );
					}
				}
			}
		}

		return false;
	}

	/**
	 * Detect audio content in HTML.
	 *
	 * @param string $html HTML content.
	 * @param array  $resource Resource data.
	 * @return array|false Audio information or false.
	 */
	private function detect_audio_in_content( $html, $resource ) {
		if ( preg_match( '/<audio[^>]*>/i', $html ) ) {
			if ( preg_match( '/<source[^>]+src=["\']([^"\']+\.(?:mp3|wav|ogg|m4a))["\'][^>]*>/i', $html, $matches ) ) {
				$audio_id = $this->import_media_file( $matches[1] );
				if ( $audio_id ) {
					return array( 'audio_id' => $audio_id );
				}
			}
		}

		if ( ! empty( $resource['files'] ) ) {
			foreach ( $resource['files'] as $file ) {
				if ( preg_match( '/\.(?:mp3|wav|ogg|m4a|aac|flac)$/i', $file ) ) {
					$audio_id = $this->import_media_file( $file );
					if ( $audio_id ) {
						return array( 'audio_id' => $audio_id );
					}
				}
			}
		}

		return false;
	}

	/**
	 * Import quiz from SCORM assessment.
	 *
	 * @param array $item Item data.
	 * @param array $resource Resource data.
	 * @param int   $course_id Course ID.
	 * @param int   $chapter_id Chapter ID.
	 * @param int   $order Order number.
	 */
	private function import_quiz( $item, $resource, $course_id, $chapter_id, $order ) {
		$html_file = $this->temp_dir . '/' . $resource['href'];

		if ( ! file_exists( $html_file ) ) {
			$this->import_report['skipped_items'][] = sprintf(
				/* translators: %s: text value. */
				__( 'Quiz content file not found: %s', 'ohmylms' ),
				$item['title']
			);
			return;
		}

		$html_content = file_get_contents( $html_file );
		$questions    = $this->parse_quiz_questions( $html_content );

		if ( empty( $questions ) ) {
			$this->import_report['warnings'][] = sprintf(
				/* translators: %s: text value. */
				__( 'No questions found in quiz: %s. Creating as lesson instead.', 'ohmylms' ),
				$item['title']
			);
			$this->import_lesson( $item, $resource, $course_id, $chapter_id, $order );
			return;
		}

		$quiz_description = $this->extract_quiz_description( $html_content, $item['title'] );

		$quiz_id = wp_insert_post(
			array(
				'post_title'   => $item['title'],
				'post_content' => $quiz_description,
				'post_status'  => 'publish',
				'post_type'    => 'ohmylms-quiz',
			)
		);

		if ( is_wp_error( $quiz_id ) ) {
			$this->import_report['errors'][] = sprintf(
				/* translators: %s: text value. */
				__( 'Failed to create quiz: %s', 'ohmylms' ),
				$item['title']
			);
			return;
		}

		update_post_meta(
			$quiz_id,
			'_quiz_settings',
			array(
				'layout'        => 'one_question_per_page',
				'passing_grade' => array(
					'enabled' => false,
					'value'   => 70,
				),
			)
		);

		$this->import_quiz_questions_to_db( $quiz_id, $questions );

		global $wpdb;
		$result = $wpdb->insert(
			"{$wpdb->prefix}ohmylms_content_relationship",
			array(
				'chapter_id'   => $chapter_id,
				'content_id'   => $quiz_id,
				'content_type' => 'quiz',
				'order_number' => $order,
			),
			array( '%d', '%d', '%s', '%d' )
		);

		if ( false === $result ) {
			$error_msg                       = $wpdb->last_error ? $wpdb->last_error : 'Unknown database error';
			$this->import_report['errors'][] = sprintf(
				/* translators: %1$s: text value; %2$s: text value. */
				__( 'Failed to link quiz to chapter: %1$s - %2$s', 'ohmylms' ),
				$item['title'],
				$error_msg
			);
			ScormHelper::log(
				'Failed to link quiz to chapter',
				'error',
				array(
					'quiz_id'    => $quiz_id,
					'chapter_id' => $chapter_id,
					'error'      => $error_msg,
				)
			);
		} else {
			++$this->import_report['quizzes_created'];
			ScormHelper::log(
				'Quiz imported',
				'info',
				array(
					'quiz_id'         => $quiz_id,
					'title'           => $item['title'],
					'questions_count' => count( $questions ),
					'chapter_id'      => $chapter_id,
					'relationship_id' => $wpdb->insert_id,
				)
			);
		}
	}

	/**
	 * Import assignment from SCORM content.
	 *
	 * @param array $item Item data.
	 * @param array $resource Resource data.
	 * @param int   $course_id Course ID.
	 * @param int   $chapter_id Chapter ID.
	 * @param int   $order Order number.
	 */
	private function import_assignment( $item, $resource, $course_id, $chapter_id, $order ) {
		$html_file = $this->temp_dir . '/' . $resource['href'];
		$content   = '';

		if ( file_exists( $html_file ) ) {
			$content = file_get_contents( $html_file );
			$content = $this->process_scorm_html( $content, $item['title'] );
		} else {
			$this->import_report['warnings'][] = sprintf(
				/* translators: %s: text value. */
				__( 'Content file not found for assignment: %s', 'ohmylms' ),
				$item['title']
			);
		}

		$assignment_id = wp_insert_post(
			array(
				'post_title'   => $item['title'],
				'post_content' => $content,
				'post_status'  => 'publish',
				'post_type'    => 'ohmylms-assignment',
			)
		);

		if ( is_wp_error( $assignment_id ) ) {
			$this->import_report['errors'][] = sprintf(
				/* translators: %s: text value. */
				__( 'Failed to create assignment: %s', 'ohmylms' ),
				$item['title']
			);
			return;
		}

		update_post_meta(
			$assignment_id,
			'_assignment_settings',
			array(
				'upload_limit'       => 1,
				'attachment_limit'   => 10,
				'allowed_extensions' => array( 'pdf', 'doc', 'docx', 'txt', 'zip' ),
				'auto_evaluate'      => false,
				'passing_grade'      => 70,
			)
		);

		global $wpdb;
		$wpdb->insert(
			"{$wpdb->prefix}ohmylms_content_relationship",
			array(
				'chapter_id'   => $chapter_id,
				'content_id'   => $assignment_id,
				'content_type' => 'assignment',
				'order_number' => $order,
			)
		);

		++$this->import_report['assignments_created'];

		ScormHelper::log(
			'Assignment imported',
			'info',
			array(
				'assignment_id' => $assignment_id,
				'title'         => $item['title'],
			)
		);
	}

	/**
	 * Extract quiz description from HTML content.
	 *
	 * @param string $html HTML content.
	 * @param string $title Quiz title to exclude from description.
	 * @return string Quiz description.
	 */
	private function extract_quiz_description( $html, $title = '' ) {
		libxml_use_internal_errors( true );
		$dom = new \DOMDocument();
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $html );
		libxml_clear_errors();

		$xpath = new \DOMXPath( $dom );

		$description_elements = $xpath->query( '//div[contains(@class, "description")] | //div[contains(@class, "introduction")] | //div[contains(@class, "intro")] | //p[1]' );

		if ( 0 < $description_elements->length ) {
			$description_text = '';
			foreach ( $description_elements as $elem ) {
				if ( 'form' === $elem->nodeName ||
					strpos( $elem->getAttribute( 'class' ), 'question' ) !== false ) {
					break;
				}

				$elem_text = $elem->textContent;

				if ( ! empty( $title ) && stripos( $elem_text, $title ) !== false ) {
					continue;
				}

				$description_text .= $elem_text . "\n\n";
			}

			$description_text = trim( $description_text );
			if ( ! empty( $description_text ) && strlen( $description_text ) < 1000 ) {
				return $description_text;
			}
		}

		if ( preg_match( '/<p[^>]*>(.*?)<\/p>/is', $html, $matches ) ) {
			$first_para = wp_strip_all_tags( $matches[1] );

			if ( ! empty( $title ) && stripos( $first_para, $title ) !== false && strlen( $first_para ) < strlen( $title ) + 50 ) {
				return '';
			}

			if ( strlen( $first_para ) < 500 && ! preg_match( '/<(?:form|input|select|textarea)/i', $matches[1] ) ) {
				return trim( $first_para );
			}
		}

		return '';
	}

	/**
	 * Parse quiz questions from HTML content.
	 *
	 * @param string $html HTML content.
	 * @return array Array of questions.
	 */
	private function parse_quiz_questions( $html ) {
		$questions             = array();
		$processed_identifiers = array();

		libxml_use_internal_errors( true );
		$dom = new \DOMDocument();
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $html );
		libxml_clear_errors();

		$xpath = new \DOMXPath( $dom );

		$question_containers = $xpath->query(
			'//div[contains(@class, "question")] | ' .
			'//div[contains(@id, "question")] | ' .
			'//li[contains(@class, "question")] | ' .
			'//fieldset | ' .
			'//form[.//input[@type="radio" or @type="checkbox"]]'
		);

		foreach ( $question_containers as $container ) {
			$selects = $xpath->query( './/select', $container );
			if ( 2 <= $selects->length ) {
				$parsed_matching = $this->parse_matching_question( $container, $xpath );
				if ( $parsed_matching ) {
					$questions[]             = $parsed_matching;
					$processed_identifiers[] = 'matching_' . count( $questions );
					continue;
				}
			}

			$container_html = $container->ownerDocument->saveHTML( $container );
			if ( stripos( $container_html, 'reorder' ) !== false ||
				stripos( $container_html, 'order' ) !== false ||
				stripos( $container_html, 'sequence' ) !== false ||
				stripos( $container_html, 'arrange' ) !== false ) {

				$parsed_reorder = $this->parse_reorder_question( $container, $xpath );
				if ( $parsed_reorder ) {
					$questions[]             = $parsed_reorder;
					$processed_identifiers[] = 'reorder_' . count( $questions );
					continue;
				}
			}

			$inputs = $xpath->query( './/input[@type="radio" or @type="checkbox"]', $container );

			if ( 0 < $inputs->length ) {
				$first_input = $inputs->item( 0 );
				$input_name  = $first_input->getAttribute( 'name' );

				if ( in_array( $input_name, $processed_identifiers ) ) {
					continue;
				}

				$parsed_question = $this->parse_html_question( $container, $xpath, $inputs );
				if ( $parsed_question ) {
					$questions[]             = $parsed_question;
					$processed_identifiers[] = $input_name;
				}
				continue;
			}

			$text_inputs = $xpath->query( './/input[@type="text"] | .//textarea', $container );
			if ( 0 < $text_inputs->length ) {
				$first_text_input = $text_inputs->item( 0 );
				$input_name       = $first_text_input->getAttribute( 'name' );

				if ( in_array( $input_name, $processed_identifiers ) ) {
					continue;
				}

				$parsed_question = $this->parse_text_question( $container, $xpath );
				if ( $parsed_question ) {
					$questions[]             = $parsed_question;
					$processed_identifiers[] = $input_name;
				}
				continue;
			}

			if ( 0 === $text_inputs->length && 0 === $inputs->length ) {
				$parsed_statement = $this->parse_text_question( $container, $xpath );
				if ( $parsed_statement && 'statement' === $parsed_statement['type'] ) {
					$questions[]             = $parsed_statement;
					$processed_identifiers[] = 'statement_' . count( $questions );
				}
			}
		}

		if ( empty( $questions ) ) {
			$all_radios      = $xpath->query( '//input[@type="radio"]' );
			$grouped_by_name = array();

			foreach ( $all_radios as $radio ) {
				$name = $radio->getAttribute( 'name' );
				if ( ! isset( $grouped_by_name[ $name ] ) ) {
					$grouped_by_name[ $name ] = array();
				}
				$grouped_by_name[ $name ][] = $radio;
			}

			foreach ( $grouped_by_name as $name => $radios ) {
				if ( count( $radios ) > 0 ) {
					$parent = $radios[0]->parentNode;
					while ( $parent && 'form' !== $parent->nodeName && 'div' !== $parent->nodeName ) {
						$parent = $parent->parentNode;
					}

					if ( $parent ) {
						$parsed_question = $this->parse_html_question( $parent, $xpath, null );
						if ( $parsed_question && ! in_array( $name, $processed_identifiers ) ) {
							$questions[]             = $parsed_question;
							$processed_identifiers[] = $name;
						}
					}
				}
			}
		}

		return $questions;
	}

	/**
	 * Parse a single question from HTML element (radio/checkbox types).
	 *
	 * @param \DOMElement       $element Question element.
	 * @param \DOMXPath         $xpath XPath object.
	 * @param \DOMNodeList|null $inputs Optional pre-fetched input elements.
	 * @return array|null Question data or null.
	 */
	private function parse_html_question( $element, $xpath, $inputs = null ) {
		$question_text = '';
		$labels        = $xpath->query( './/legend | .//h1 | .//h2 | .//h3 | .//h4 | .//p[not(.//input)]', $element );

		if ( 0 < $labels->length ) {
			foreach ( $labels as $label ) {
				$text = trim( $label->textContent );
				if ( strlen( $text ) > 3 && ! preg_match( '/^\d+\.?$/', $text ) ) {
					$question_text = $text;
					break;
				}
			}
		}

		if ( empty( $question_text ) ) {
			return null;
		}

		if ( null === $inputs ) {
			$inputs = $xpath->query( './/input[@type="radio"] | .//input[@type="checkbox"]', $element );
		}

		if ( 0 === $inputs->length ) {
			return null;
		}

		$input_groups = array();
		foreach ( $inputs as $input ) {
			$name = $input->getAttribute( 'name' );
			if ( ! isset( $input_groups[ $name ] ) ) {
				$input_groups[ $name ] = array();
			}
			$input_groups[ $name ][] = $input;
		}

		$main_group = array_reduce(
			$input_groups,
			function ( $carry, $item ) {
				return ( count( $item ) > count( $carry ) ) ? $item : $carry;
			},
			array()
		);

		if ( empty( $main_group ) ) {
			return null;
		}

		$first_input   = $main_group[0];
		$input_type    = $first_input->getAttribute( 'type' );
		$is_true_false = false;

		if ( count( $main_group ) === 2 && 'radio' === $input_type ) {
			$answers_text = array();
			foreach ( $main_group as $input ) {
				$answers_text[] = strtolower( trim( $this->get_input_label( $input, $xpath ) ) );
			}

			if ( in_array( 'true', $answers_text ) && in_array( 'false', $answers_text ) ) {
				$is_true_false = true;
			} elseif ( in_array( 'yes', $answers_text ) && in_array( 'no', $answers_text ) ) {
				$is_true_false = true;
			}
		}

		if ( $is_true_false ) {
			$question_type = 'true-false';
		} elseif ( 'checkbox' === $input_type ) {
			$question_type = 'multiple-choice';
		} else {
			$question_type = 'single-choice';
		}

		$answers = array();
		foreach ( $main_group as $input ) {
			$label_text = $this->get_input_label( $input, $xpath );

			if ( $label_text ) {
				$is_correct = $this->is_answer_correct( $input, $xpath );
				$answers[]  = array(
					'answer'     => $label_text,
					'is_correct' => $is_correct ? 1 : 0,
				);
			}
		}

		if ( empty( $answers ) ) {
			return null;
		}

		foreach ( $answers as $answer ) {
			$question_text = str_replace( $answer['answer'], '', $question_text );
		}
		$question_text = trim( preg_replace( '/\s+/', ' ', $question_text ) );

		return array(
			'title'   => $question_text,
			'type'    => $question_type,
			'answers' => $answers,
		);
	}

	/**
	 * Parse text-based question (short-text, long-text, fill-in-the-blank, statement).
	 *
	 * @param \DOMElement $element Question element.
	 * @param \DOMXPath   $xpath XPath object.
	 * @return array|null Question data or null.
	 */
	private function parse_text_question( $element, $xpath ) {
		$question_text = '';
		$labels        = $xpath->query( './/h1 | .//h2 | .//h3 | .//h4 | .//p[1] | .//label[1] | .//legend', $element );

		if ( 0 < $labels->length ) {
			$question_text = trim( $labels->item( 0 )->textContent );
		}

		if ( empty( $question_text ) ) {
			return null;
		}

		$text_inputs = $xpath->query( './/input[@type="text"]', $element );
		$textareas   = $xpath->query( './/textarea', $element );

		if ( 0 === $text_inputs->length && 0 === $textareas->length ) {
			$lower_text = strtolower( $question_text );
			if ( strpos( $lower_text, 'note:' ) !== false ||
				strpos( $lower_text, 'information:' ) !== false ||
				strpos( $lower_text, 'remember:' ) !== false ||
				strpos( $lower_text, 'important:' ) !== false ||
				strlen( $question_text ) > 200 ) {

				return array(
					'title'   => $question_text,
					'type'    => 'statement',
					'answers' => array(
						array(
							'answer'     => '',
							'is_correct' => 1,
						),
					),
				);
			}
			return null;
		}

		$question_type = 'short-text';
		$answer_text   = '';

		if ( 0 < $textareas->length ) {
			$question_type  = 'long-text';
			$first_textarea = $textareas->item( 0 );
			$answer_text    = $first_textarea->getAttribute( 'value' );
			if ( empty( $answer_text ) ) {
				$answer_text = $first_textarea->getAttribute( 'placeholder' );
			}
			if ( empty( $answer_text ) ) {
				$answer_text = trim( $first_textarea->textContent );
			}
		} elseif ( 0 < $text_inputs->length ) {
			$is_fill_blank = strpos( $question_text, '___' ) !== false ||
				strpos( $question_text, '[blank]' ) !== false ||
				strpos( $question_text, '____' ) !== false;

			if ( $is_fill_blank ) {
				$question_type = 'fill-in-the-blank';
			}

			$first_input = $text_inputs->item( 0 );
			$answer_text = $first_input->getAttribute( 'value' );

			if ( empty( $answer_text ) ) {
				$answer_text = $first_input->getAttribute( 'data-answer' );
			}
			if ( empty( $answer_text ) ) {
				$answer_text = $first_input->getAttribute( 'data-correct' );
			}

			if ( empty( $answer_text ) ) {
				$placeholder = $first_input->getAttribute( 'placeholder' );
				if ( ! empty( $placeholder ) &&
					stripos( $placeholder, 'enter' ) === false &&
					stripos( $placeholder, 'type' ) === false &&
					stripos( $placeholder, 'answer' ) === false ) {
					$answer_text = $placeholder;
				}
			}

			if ( empty( $answer_text ) ) {
				$input_name    = $first_input->getAttribute( 'name' );
				$input_id      = $first_input->getAttribute( 'id' );
				$hidden_inputs = $xpath->query( "//input[@type='hidden']", $element );
				foreach ( $hidden_inputs as $hidden ) {
					$hidden_name  = $hidden->getAttribute( 'name' );
					$hidden_value = $hidden->getAttribute( 'value' );

					if ( ! empty( $hidden_value ) && (
						stripos( $hidden_name, 'answer' ) !== false ||
						stripos( $hidden_name, 'correct' ) !== false ||
						stripos( $hidden_name, $input_name ) !== false ||
						stripos( $hidden_name, $input_id ) !== false
					) ) {
						$answer_text = $hidden_value;
						break;
					}
				}
			}

			if ( empty( $answer_text ) ) {
				$answer_labels = $xpath->query( './/*[contains(text(), "Correct answer") or contains(text(), "Answer:")]', $element );
				if ( 0 < $answer_labels->length ) {
					$full_text = $answer_labels->item( 0 )->textContent;
					if ( preg_match( '/(?:Correct answer|Answer)\s*:\s*(.+)/i', $full_text, $matches ) ) {
						$answer_text = trim( $matches[1] );
					}
				}
			}
		}

		return array(
			'title'   => $question_text,
			'type'    => $question_type,
			'answers' => array(
				array(
					'answer'     => $answer_text,
					'is_correct' => 1,
				),
			),
		);
	}

	/**
	 * Parse matching question.
	 *
	 * @param \DOMElement $element Question element.
	 * @param \DOMXPath   $xpath XPath object.
	 * @return array|null Question data or null.
	 */
	private function parse_matching_question( $element, $xpath ) {
		$question_text = '';
		$labels        = $xpath->query( './/h1 | .//h2 | .//h3 | .//h4 | .//p[1] | .//legend', $element );

		if ( 0 < $labels->length ) {
			$question_text = trim( $labels->item( 0 )->textContent );
		}

		if ( empty( $question_text ) ) {
			$question_text = 'Match the following items';
		}

		$selects = $xpath->query( './/select', $element );
		if ( 2 > $selects->length ) {
			return null;
		}

		$answers = array();

		foreach ( $selects as $select ) {
			$select_label = '';
			$select_id    = $select->getAttribute( 'id' );

			if ( $select_id ) {
				$label_elements = $xpath->query( "//label[@for='{$select_id}']" );
				if ( 0 < $label_elements->length ) {
					$select_label = trim( $label_elements->item( 0 )->textContent );
				}
			}

			if ( empty( $select_label ) ) {
				$prev = $select->previousSibling;
				while ( $prev ) {
					if ( XML_TEXT_NODE === $prev->nodeType ) {
						$text = trim( $prev->textContent );
						if ( ! empty( $text ) ) {
							$select_label = $text;
							break;
						}
					} elseif ( XML_ELEMENT_NODE === $prev->nodeType ) {
						$select_label = trim( $prev->textContent );
						break;
					}
					$prev = $prev->previousSibling;
				}
			}

			$options        = $xpath->query( './/option', $select );
			$correct_option = '';

			foreach ( $options as $option ) {
				if ( $option->hasAttribute( 'selected' ) ||
					$option->getAttribute( 'data-correct' ) === 'true' ) {
					$correct_option = trim( $option->textContent );
					break;
				}
			}

			if ( ! empty( $select_label ) ) {
				$answers[] = array(
					'answer'     => $select_label . ' → ' . $correct_option,
					'is_correct' => 1,
				);
			}
		}

		if ( count( $answers ) < 2 ) {
			return null;
		}

		return array(
			'title'   => $question_text,
			'type'    => 'matching',
			'answers' => $answers,
		);
	}

	/**
	 * Parse reorder/sequence question.
	 *
	 * @param \DOMElement $element Question element.
	 * @param \DOMXPath   $xpath XPath object.
	 * @return array|null Question data or null.
	 */
	private function parse_reorder_question( $element, $xpath ) {
		$question_text = '';
		$labels        = $xpath->query( './/h1 | .//h2 | .//h3 | .//h4 | .//p[1] | .//legend', $element );

		if ( 0 < $labels->length ) {
			$question_text = trim( $labels->item( 0 )->textContent );
		}

		if ( empty( $question_text ) ) {
			$question_text = 'Arrange the following in correct order';
		}

		$items = $xpath->query( './/li | .//*[@data-order] | .//*[@data-position]', $element );

		if ( 2 > $items->length ) {
			return null;
		}

		$answers = array();

		foreach ( $items as $index => $item ) {
			$item_text = trim( $item->textContent );

			if ( empty( $item_text ) || strlen( $item_text ) < 2 ) {
				continue;
			}

			$order = $item->getAttribute( 'data-order' );
			if ( empty( $order ) ) {
				$order = $item->getAttribute( 'data-position' );
			}
			if ( empty( $order ) ) {
				$order = $index + 1;
			}

			$answers[] = array(
				'answer'     => $item_text,
				'is_correct' => 1,
				'order'      => (int) $order,
			);
		}

		usort(
			$answers,
			function ( $a, $b ) {
				return ( $a['order'] ?? 0 ) - ( $b['order'] ?? 0 );
			}
		);

		foreach ( $answers as &$answer ) {
			unset( $answer['order'] );
		}

		if ( count( $answers ) < 2 ) {
			return null;
		}

		return array(
			'title'   => $question_text,
			'type'    => 'reorder',
			'answers' => $answers,
		);
	}

	/**
	 * Check if an answer is marked as correct.
	 *
	 * @param \DOMElement $input Input element.
	 * @param \DOMXPath   $xpath XPath object.
	 * @return bool True if correct.
	 */
	private function is_answer_correct( $input, $xpath ) {
		$input_id       = $input->getAttribute( 'id' );
		$raw_label_text = '';

		if ( $input_id ) {
			$labels = $xpath->query( "//label[@for='{$input_id}']" );
			if ( 0 < $labels->length ) {
				$raw_label_text = $labels->item( 0 )->textContent;
			}
		}

		if ( empty( $raw_label_text ) ) {
			$parent = $input->parentNode;
			if ( $parent && 'label' === $parent->nodeName ) {
				$raw_label_text = $parent->textContent;
			}
		}

		if ( ! empty( $raw_label_text ) ) {
			if ( preg_match( '/✓\s*\(?\s*correct\s*\)?/i', $raw_label_text ) ||
				preg_match( '/✔\s*\(?\s*correct\s*\)?/i', $raw_label_text ) ||
				preg_match( '/\(correct\)/i', $raw_label_text ) ||
				preg_match( '/\[correct\]/i', $raw_label_text ) ||
				preg_match( '/✓/u', $raw_label_text ) ||
				preg_match( '/✔/u', $raw_label_text ) ) {
				return true;
			}
		}

		if ( $input->hasAttribute( 'checked' ) || $input->hasAttribute( 'selected' ) ) {
			return true;
		}

		$class = $input->getAttribute( 'class' );
		if ( stripos( $class, 'correct' ) !== false ) {
			return true;
		}

		$parent = $input->parentNode;
		if ( $parent ) {
			if ( stripos( $parent->getAttribute( 'class' ), 'correct' ) !== false ) {
				return true;
			}

			$grandparent = $parent->parentNode;
			if ( $grandparent && stripos( $grandparent->getAttribute( 'class' ), 'correct' ) !== false ) {
				return true;
			}
		}

		$data_correct = $input->getAttribute( 'data-correct' );
		if ( in_array( strtolower( $data_correct ), array( 'true', '1', 'yes', 'correct' ) ) ) {
			return true;
		}

		$data_answer = $input->getAttribute( 'data-answer' );
		if ( in_array( strtolower( $data_answer ), array( 'true', '1', 'yes', 'correct' ) ) ) {
			return true;
		}

		$value = $input->getAttribute( 'value' );
		$name  = $input->getAttribute( 'name' );
		if ( stripos( $name, 'correct' ) !== false && in_array( strtolower( $value ), array( '1', 'true', 'yes' ) ) ) {
			return true;
		}

		$input_name = $input->getAttribute( 'name' );
		if ( $input_name ) {
			$hidden_inputs = $xpath->query( "//input[@type='hidden' and (contains(@name, 'correct') or contains(@name, 'answer'))]" );
			foreach ( $hidden_inputs as $hidden ) {
				$hidden_value = $hidden->getAttribute( 'value' );
				$hidden_name  = $hidden->getAttribute( 'name' );

				if ( $hidden_value === $value || $hidden_value === $input_id ) {
					return true;
				}

				if ( stripos( $hidden_name, $input_name ) !== false ) {
					if ( in_array( strtolower( $hidden_value ), array( '1', 'true', 'yes', $value ) ) ) {
						return true;
					}
				}
			}
		}

		if ( $input_id ) {
			$labels = $xpath->query( "//label[@for='{$input_id}']" );
			if ( 0 < $labels->length ) {
				$label_class = $labels->item( 0 )->getAttribute( 'class' );
				if ( stripos( $label_class, 'correct' ) !== false ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get label text for an input element.
	 *
	 * @param \DOMElement $input Input element.
	 * @param \DOMXPath   $xpath XPath object.
	 * @return string Label text.
	 */
	private function get_input_label( $input, $xpath ) {
		$input_id = $input->getAttribute( 'id' );

		if ( $input_id ) {
			$labels = $xpath->query( "//label[@for='{$input_id}']" );
			if ( 0 < $labels->length ) {
				return trim( $this->get_text_without_input( $labels->item( 0 ) ) );
			}
		}

		$parent = $input->parentNode;
		if ( $parent && 'label' === $parent->nodeName ) {
			return trim( $this->get_text_without_input( $parent ) );
		}

		$next = $input->nextSibling;
		while ( $next ) {
			if ( XML_TEXT_NODE === $next->nodeType && trim( $next->textContent ) ) {
				return trim( $next->textContent );
			}
			if ( XML_ELEMENT_NODE === $next->nodeType && 'input' !== $next->nodeName ) {
				return trim( $next->textContent );
			}
			$next = $next->nextSibling;
		}

		$prev = $input->previousSibling;
		while ( $prev ) {
			if ( XML_TEXT_NODE === $prev->nodeType && trim( $prev->textContent ) ) {
				return trim( $prev->textContent );
			}
			if ( XML_ELEMENT_NODE === $prev->nodeType && 'input' !== $prev->nodeName ) {
				return trim( $prev->textContent );
			}
			$prev = $prev->previousSibling;
		}

		return '';
	}

	/**
	 * Get text content from element excluding input elements.
	 *
	 * @param \DOMElement $element Element to extract text from.
	 * @return string Text content.
	 */
	private function get_text_without_input( $element ) {
		$text = '';
		foreach ( $element->childNodes as $node ) {
			if ( XML_TEXT_NODE === $node->nodeType ) {
				$text .= $node->textContent;
			} elseif ( XML_ELEMENT_NODE === $node->nodeType && 'input' !== $node->nodeName ) {
				$text .= $node->textContent;
			}
		}

		$text = trim( $text );
		$text = preg_replace( '/\s*✓\s*\(?\s*correct\s*\)?/i', '', $text );
		$text = preg_replace( '/\s*\(?\s*correct\s*\)?/i', '', $text );
		$text = preg_replace( '/\s*✓\s*/', '', $text );
		$text = preg_replace( '/\s*✔\s*/', '', $text );
		$text = preg_replace( '/\s*\[correct\]/i', '', $text );

		return trim( $text );
	}

	/**
	 * Import questions to database.
	 *
	 * @param int   $quiz_id Quiz ID.
	 * @param array $questions Questions array.
	 */
	private function import_quiz_questions_to_db( $quiz_id, $questions ) {
		global $wpdb;

		ScormHelper::log(
			'Starting quiz questions import',
			'info',
			array(
				'quiz_id'         => $quiz_id,
				'total_questions' => count( $questions ),
			)
		);

		foreach ( $questions as $index => $question ) {
			$question_title = preg_replace( '/^Question\s+\d+\s*:\s*/i', '', $question['title'] );
			$question_title = trim( $question_title );

			$question_id = wp_insert_post(
				array(
					'post_title'  => $question_title,
					'post_status' => 'publish',
					'post_type'   => 'ohmylms-question',
				)
			);

			if ( is_wp_error( $question_id ) ) {
				ScormHelper::log(
					'Failed to create question',
					'error',
					array(
						'title' => $question_title,
						'error' => $question_id->get_error_message(),
					)
				);
				continue;
			}

			$question_settings = array(
				'type'     => $question['type'],
				'required' => true,
				'score'    => array(
					'enabled' => true,
					'value'   => 1,
				),
			);

			$randomizable_types = array( 'single-choice', 'multiple-choice', 'true-false' );
			if ( in_array( $question['type'], $randomizable_types ) ) {
				$question_settings['randomize'] = false;
			}

			update_post_meta( $question_id, '_question_settings', $question_settings );

			$answer_order     = 0;
			$answers_inserted = 0;
			$correct_answers  = 0;

			foreach ( $question['answers'] as $answer ) {
				$insert_result = $wpdb->insert(
					"{$wpdb->prefix}ohmylms_question_answers",
					array(
						'question_id'  => $question_id,
						'answer'       => $answer['answer'],
						'is_correct'   => $answer['is_correct'],
						'order_number' => $answer_order++,
					)
				);

				if ( $insert_result ) {
					++$answers_inserted;
					if ( $answer['is_correct'] ) {
						++$correct_answers;
					}
				} else {
					ScormHelper::log(
						'Failed to insert answer',
						'error',
						array(
							'question_id' => $question_id,
							'answer'      => $answer['answer'],
							'error'       => $wpdb->last_error,
						)
					);
				}
			}

			$link_result = $wpdb->insert(
				"{$wpdb->prefix}ohmylms_quiz_questions_relationship",
				array(
					'quiz_id'      => $quiz_id,
					'question_id'  => $question_id,
					'order_number' => $index + 1,
				)
			);

			if ( ! $link_result ) {
				ScormHelper::log(
					'Failed to link question to quiz',
					'error',
					array(
						'quiz_id'     => $quiz_id,
						'question_id' => $question_id,
						'error'       => $wpdb->last_error,
					)
				);
			}

			ScormHelper::log(
				'Question imported',
				'info',
				array(
					'question_id'     => $question_id,
					'question_number' => $index + 1,
					'title'           => $question_title,
					'type'            => $question['type'],
					'total_answers'   => $answers_inserted,
					'correct_answers' => $correct_answers,
				)
			);
		}
	}

	/**
	 * Process SCORM HTML content.
	 *
	 * @param string $html HTML content.
	 * @param string $title Optional title to remove from content.
	 * @return string Processed HTML.
	 */
	private function process_scorm_html( $html, $title = '' ) {
		$html = preg_replace( '/<script[^>]*>.*?<\/script>/is', '', $html );

		if ( preg_match( '/<body[^>]*>(.*?)<\/body>/is', $html, $matches ) ) {
			$html = $matches[1];
		}

		if ( ! empty( $title ) ) {
			$html = preg_replace( '/<h[1-4][^>]*>\s*' . preg_quote( $title, '/' ) . '\s*<\/h[1-4]>/i', '', $html, 1 );
			$html = preg_replace( '/<p[^>]*>\s*<strong[^>]*>\s*' . preg_quote( $title, '/' ) . '\s*<\/strong>\s*<\/p>/i', '', $html, 1 );
			$html = preg_replace( '/<p[^>]*>\s*<b[^>]*>\s*' . preg_quote( $title, '/' ) . '\s*<\/b>\s*<\/p>/i', '', $html, 1 );
			$html = preg_replace( '/^\s*' . preg_quote( $title, '/' ) . '\s*(<br\s*\/?>|<\/p>)/i', '', $html, 1 );
		}

		$html = $this->import_media_from_html( $html );

		$html = preg_replace( '/<form[^>]*>|<\/form>/i', '', $html );
		$html = preg_replace( '/<input[^>]*type=["\'](?!range|volume)[^"\']*["\'][^>]*>/i', '', $html );
		$html = preg_replace( '/<input(?![^>]*type=)[^>]*>/i', '', $html );

		return trim( $html );
	}

	/**
	 * Import media files referenced in HTML.
	 *
	 * @param string $html HTML content.
	 * @return string HTML with updated media URLs.
	 */
	private function import_media_from_html( $html ) {
		preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $img_matches );

		foreach ( $img_matches[1] as $img_src ) {
			$attachment_id = $this->import_media_file( $img_src );
			if ( $attachment_id ) {
				$new_url = wp_get_attachment_url( $attachment_id );
				$html    = str_replace( $img_src, $new_url, $html );
			}
		}

		preg_match_all( '/<source[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $source_matches );

		foreach ( $source_matches[1] as $source_src ) {
			$attachment_id = $this->import_media_file( $source_src );
			if ( $attachment_id ) {
				$new_url = wp_get_attachment_url( $attachment_id );
				$html    = str_replace( $source_src, $new_url, $html );
			}
		}

		return $html;
	}

	/**
	 * Import a media file to WordPress media library.
	 *
	 * @param string $file_path Relative file path in SCORM package.
	 * @return int|false Attachment ID or false on failure.
	 */
	private function import_media_file( $file_path ) {
		$file_path = ltrim( $file_path, './' );

		if ( isset( $this->media_map[ $file_path ] ) ) {
			return $this->media_map[ $file_path ];
		}

		$full_path = $this->temp_dir . '/' . $file_path;

		if ( ! file_exists( $full_path ) ) {
			return false;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$filename    = basename( $file_path );
		$upload_dir  = wp_upload_dir();
		$target_file = $upload_dir['path'] . '/' . $filename;

		copy( $full_path, $target_file );

		$attachment = array(
			'guid'           => $upload_dir['url'] . '/' . $filename,
			'post_mime_type' => mime_content_type( $target_file ),
			'post_title'     => preg_replace( '/\.[^.]+$/', '', $filename ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $target_file );

		if ( ! is_wp_error( $attachment_id ) ) {
			$attach_data = wp_generate_attachment_metadata( $attachment_id, $target_file );
			wp_update_attachment_metadata( $attachment_id, $attach_data );

			$this->media_map[ $file_path ] = $attachment_id;
			++$this->import_report['media_imported'];

			return $attachment_id;
		}

		return false;
	}

	/**
	 * Clean up temporary files.
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
