<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Ms course id.
 *
 * @access public
 * @package OhMyLMS\Migrations
 *
 * @author OhMyLMS
 * @since 1.0.0
 */

namespace OhMyLMS\Migrations;

/**
 * Migration class for MasterStudy LMS.
 *
 * @since 1.0.0
 */
class MasterStudy {

	/**
	 * Source course id.
	 *
	 * @var int|null
	 */
	public $ms_course_id = null;

	/**
	 * Source course data.
	 *
	 * @var array|null
	 */
	public $ms_course = null;

	/**
	 * Migration source key used for idempotency.
	 *
	 * @var string
	 */
	private $source_key = 'masterStudy';

	/**
	 * Initializes the migration process for a specific MasterStudy course.
	 *
	 * @param int|null $ms_course_id The ID of the MasterStudy course to migrate.
	 * @return bool
	 */
	public function init( $ms_course_id = null ) {
		if ( ! $this->should_init() ) {
			return false;
		}

		$this->ms_course_id = $ms_course_id;

		return true;
	}

	/**
	 * Checks if MasterStudy LMS is installed/active.
	 *
	 * @return bool
	 */
	private function should_init() {
		if ( defined( 'STM_LMS_VERSION' ) || defined( 'STM_LMS_FILE' ) || defined( 'MASTERSTUDY_LMS_VERSION' ) || class_exists( 'STM_LMS' ) ) {
			return true;
		}

		return post_type_exists( 'stm-courses' );
	}

	/**
	 * Migrates course data from MasterStudy LMS to OhMyLMS.
	 *
	 * @return \WP_Error|int
	 */
	public function migrate_course() {
		$this->ms_course = $this->get_course_data();

		if ( is_wp_error( $this->ms_course ) ) {
			return $this->ms_course;
		}

		$existing_course_id = $this->find_existing_migrated_post( 'ohmylms-course', intval( $this->ms_course['ID'] ) );
		if ( $existing_course_id ) {
			$this->migrate_terms_and_taxonomies( $existing_course_id );
			$this->migrate_curriculum( $existing_course_id );
			$this->migrate_students( $existing_course_id );
			return $existing_course_id;
		}

		$post_data = array(
			'post_title'    => sanitize_text_field( $this->ms_course['post_title'] ),
			'post_content'  => wp_kses_post( $this->ms_course['post_content'] ),
			'post_excerpt'  => isset( $this->ms_course['post_excerpt'] ) ? sanitize_text_field( $this->ms_course['post_excerpt'] ) : '',
			'post_status'   => 'draft',
			'post_author'   => isset( $this->ms_course['post_author'] ) ? intval( $this->ms_course['post_author'] ) : get_current_user_id(),
			'post_type'     => 'ohmylms-course',
			'post_date'     => isset( $this->ms_course['post_date'], $this->ms_course['post_status'] ) && 'future' === $this->ms_course['post_status'] ? gmdate( 'Y-m-d H:i:s', strtotime( $this->ms_course['post_date'] ) ) : current_datetime()->format( 'Y-m-d H:i:s' ),
			'post_password' => isset( $this->ms_course['post_password'] ) ? $this->ms_course['post_password'] : '',
		);

		$new_course_id = wp_insert_post( $post_data );
		if ( is_wp_error( $new_course_id ) ) {
			return $new_course_id;
		}

		$this->save_source_mapping( $new_course_id, intval( $this->ms_course['ID'] ) );

		if ( ! empty( $post_data['post_password'] ) ) {
			update_post_meta( $new_course_id, '_access_type', 'password_protected' );
		} else {
			update_post_meta( $new_course_id, '_access_type', 'public' );
		}

		if ( ! empty( $this->ms_course['meta'] ) && is_array( $this->ms_course['meta'] ) ) {
			$this->migrate_course_metadata( $new_course_id, $this->ms_course['meta'] );
		}

		$this->migrate_terms_and_taxonomies( $new_course_id );
		$this->migrate_curriculum( $new_course_id );
		$this->migrate_students( $new_course_id );

		return $new_course_id;
	}

	/**
	 * Get course data.
	 *
	 * @return array|\WP_Error
	 */
	private function get_course_data() {
		if ( ! $this->ms_course_id ) {
			return new \WP_Error( 'no_course_id', 'No course ID provided.' );
		}

		$course = get_post( $this->ms_course_id );

		if ( ! $course || 'stm-courses' !== $course->post_type ) {
			return new \WP_Error( 'invalid_course', 'Invalid course ID or post type.' );
		}

		$course_meta = get_post_meta( $this->ms_course_id );

		return array(
			'ID'            => $course->ID,
			'post_title'    => $course->post_title,
			'post_content'  => $course->post_content,
			'post_status'   => $course->post_status,
			'post_excerpt'  => $course->post_excerpt,
			'post_author'   => $course->post_author,
			'post_date'     => $course->post_date,
			'post_password' => $course->post_password,
			'menu_order'    => $course->menu_order,
			'meta'          => $course_meta,
		);
	}

	/**
	 * Migrate course metadata.
	 *
	 * @param int   $new_course_id OhMyLMS course id.
	 * @param array $meta_data Source meta data.
	 */
	private function migrate_course_metadata( $new_course_id, $meta_data ) {
		$price_keys         = array( 'price', '_price', 'current_price', '_stm_price', 'sale_price' );
		$regular_price_keys = array( 'sale_price', '_regular_price', '_stm_regular_price' );

		$price         = $this->get_first_meta_value( $meta_data, $price_keys );
		$regular_price = $this->get_first_meta_value( $meta_data, $regular_price_keys );

		if ( '' !== $regular_price ) {
			update_post_meta( $new_course_id, '_regular_price', $regular_price );
		}

		if ( '' !== $price && floatval( $price ) > 0 ) {
			update_post_meta( $new_course_id, '_price', $price );
			update_post_meta( $new_course_id, '_price_type', 'paid' );
		} else {
			update_post_meta( $new_course_id, '_price_type', 'free' );
		}

		$thumbnail_id = $this->get_first_meta_value( $meta_data, array( '_thumbnail_id', 'thumbnail_id' ) );
		if ( '' !== $thumbnail_id ) {
			update_post_meta( $new_course_id, '_thumbnail_id', intval( $thumbnail_id ) );
		}

		$capacity = $this->get_first_meta_value( $meta_data, array( 'max_students', '_max_students', 'course_limit', 'students_limit' ) );
		if ( '' !== $capacity && intval( $capacity ) > 0 ) {
			update_post_meta( $new_course_id, '_has_capacity', true );
			update_post_meta( $new_course_id, '_capacity', intval( $capacity ) );
		}

		$duration = $this->extract_duration( $meta_data );
		if ( ! empty( $duration ) ) {
			update_post_meta( $new_course_id, '_duration', $duration );
		}

		$source_level = $this->get_first_meta_value( $meta_data, array( 'level', 'course_level', '_course_level' ) );
		$mapped_level = $this->normalize_level( $source_level );
		update_post_meta( $new_course_id, '_level', $mapped_level );
	}

	/**
	 * Migrate source taxonomies to OhMyLMS taxonomies.
	 *
	 * @param int $new_course_id Course id.
	 */
	private function migrate_terms_and_taxonomies( $new_course_id ) {
		// Category taxonomies become curriculum items (keeping their parent chain) and tags become
		// Course Bundles. Existing items and bundles with the same name are reused.
		$taxonomy_map = array(
			'stm_lms_course_taxonomy' => 'curriculum',
			'stm_course_category'     => 'curriculum',
			'stm-courses-category'    => 'curriculum',
			'post_tag'                => 'bundle',
		);

		foreach ( $taxonomy_map as $source_taxonomy => $target ) {
			if ( ! taxonomy_exists( $source_taxonomy ) ) {
				continue;
			}

			$terms = wp_get_post_terms( $this->ms_course_id, $source_taxonomy );
			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				continue;
			}

			if ( 'bundle' === $target ) {
				\OhMyLMS\Curriculum\Placement::import_tags( $new_course_id, wp_list_pluck( $terms, 'name' ) );
				continue;
			}

			$paths = array();
			foreach ( $terms as $term ) {
				$paths[] = \OhMyLMS\Curriculum\Placement::term_path( $term );
			}
			\OhMyLMS\Curriculum\Placement::import_categories( $new_course_id, $paths );
		}
	}
	/**
	 * Migrate course curriculum data.
	 *
	 * @param int $new_course_id New course ID.
	 */
	private function migrate_curriculum( $new_course_id ) {
		$curriculum = $this->get_normalized_curriculum();
		if ( empty( $curriculum ) ) {
			do_action( 'ohmylms_migration_skipped', $this->source_key, $this->ms_course_id, 'curriculum_not_found' );
			return;
		}

		foreach ( $curriculum as $chapter_index => $chapter_data ) {
			$chapter_title = isset( $chapter_data['section_title'] ) && '' !== $chapter_data['section_title'] ? $chapter_data['section_title'] : __( 'Section', 'ohmylms' );
			$section_key   = isset( $chapter_data['section_key'] ) ? $chapter_data['section_key'] : 'section-' . $chapter_index;
			$chapter_order = isset( $chapter_data['order'] ) ? intval( $chapter_data['order'] ) : $chapter_index;
			$chapter_id    = $this->upsert_chapter( $new_course_id, $chapter_title, $chapter_order, $section_key );
			if ( ! $chapter_id ) {
				continue;
			}

			$items = isset( $chapter_data['items'] ) && is_array( $chapter_data['items'] ) ? $chapter_data['items'] : array();
			foreach ( $items as $item_index => $item ) {
				$item_id    = isset( $item['id'] ) ? intval( $item['id'] ) : 0;
				$item_type  = isset( $item['type'] ) ? sanitize_key( $item['type'] ) : '';
				$item_order = isset( $item['order'] ) ? intval( $item['order'] ) : $item_index;

				if ( ! $item_id || '' === $item_type ) {
					continue;
				}

				if ( $this->is_lesson_type( $item_type ) ) {
					$this->migrate_lesson( $chapter_id, $item_id, $item_order );
				} elseif ( $this->is_quiz_type( $item_type ) ) {
					$this->migrate_quiz( $chapter_id, $item_id, $item_order );
				} else {
					do_action( 'ohmylms_migration_skipped', $this->source_key, $item_id, 'unsupported_curriculum_item_' . $item_type );
				}
			}
		}
	}

	/**
	 * Normalize MasterStudy curriculum structures.
	 *
	 * @return array
	 */
	private function get_normalized_curriculum() {
		$repository_curriculum = $this->get_repository_curriculum();
		if ( ! empty( $repository_curriculum ) ) {
			return $repository_curriculum;
		}

		$table_curriculum = $this->get_table_curriculum();
		if ( ! empty( $table_curriculum ) ) {
			return $table_curriculum;
		}

		$meta           = get_post_meta( $this->ms_course_id );
		$candidate_keys = array(
			'curriculum',
			'_curriculum',
			'stm_lms_curriculum',
			'_stm_lms_curriculum',
			'course_curriculum',
			'_course_curriculum',
		);

		$raw = null;
		foreach ( $candidate_keys as $key ) {
			if ( empty( $meta[ $key ] ) ) {
				continue;
			}
			$raw = $meta[ $key ][0];
			if ( '' !== $raw && null !== $raw ) {
				break;
			}
		}

		if ( null === $raw || '' === $raw ) {
			return array();
		}

		$decoded = maybe_unserialize( $raw );
		if ( is_string( $decoded ) ) {
			$json = json_decode( $decoded, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $json ) ) {
				$decoded = $json;
			}
		}

		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$normalized = $this->normalize_curriculum_sections( $decoded );
		if ( ! empty( $normalized ) ) {
			return $normalized;
		}

		foreach ( $decoded as $chapter_index => $chapter ) {
			if ( is_array( $chapter ) && isset( $chapter['items'] ) && is_array( $chapter['items'] ) ) {
				$title        = isset( $chapter['title'] ) ? sanitize_text_field( $chapter['title'] ) : '';
				$items        = $this->normalize_curriculum_items( $chapter['items'] );
				$section_key  = ! empty( $chapter['id'] ) ? sanitize_title( (string) $chapter['id'] ) : $this->generate_section_key( $title, $chapter_index, $items );
				$normalized[] = array(
					'section_key'   => $section_key,
					'section_title' => $title,
					'items'         => $items,
				);
				continue;
			}

			if ( is_array( $chapter ) && ! isset( $chapter['items'] ) ) {
				$maybe_section_title = is_string( $chapter_index ) ? sanitize_text_field( $chapter_index ) : '';
				$items               = $this->normalize_curriculum_items( $chapter );
				if ( ! empty( $items ) ) {
					$normalized[] = array(
						'section_key'   => $this->generate_section_key( $maybe_section_title, $chapter_index, $items ),
						'section_title' => $maybe_section_title,
						'items'         => $items,
					);
					continue;
				}
			}

			if ( is_array( $chapter ) && isset( $chapter[0] ) ) {
				$items        = $this->normalize_curriculum_items( $chapter );
				$normalized[] = array(
					'section_key'   => $this->generate_section_key( '', $chapter_index, $items ),
					'section_title' => '',
					'items'         => $items,
				);
				continue;
			}

			if ( is_string( $chapter ) ) {
				$parts = explode( '-', $chapter );
				if ( 2 === count( $parts ) ) {
					$type = sanitize_key( $parts[0] );
					$id   = intval( $parts[1] );
					if ( $id > 0 ) {
						$normalized[] = array(
							'section_key'   => $this->generate_section_key(
								'',
								$chapter_index,
								array(
									array(
										'id'   => $id,
										'type' => $type,
									),
								)
							),
							'section_title' => '',
							'items'         => array(
								array(
									'id'   => $id,
									'type' => $type,
								),
							),
						);
					}
				}
			}
		}

		return $normalized;
	}

	/**
	 * Get curriculum from the current MasterStudy repository API.
	 *
	 * @return array
	 */
	private function get_repository_curriculum() {
		if ( ! class_exists( '\MasterStudy\Lms\Repositories\CurriculumRepository' ) ) {
			return array();
		}

		try {
			$repository = new \MasterStudy\Lms\Repositories\CurriculumRepository();
			$sections   = $repository->get_curriculum( intval( $this->ms_course_id ), true );
		} catch ( \Throwable $throwable ) {
			do_action( 'ohmylms_migration_skipped', $this->source_key, $this->ms_course_id, 'curriculum_repository_unavailable' );
			return array();
		}

		return $this->normalize_repository_curriculum( $sections );
	}

	/**
	 * Get curriculum directly from MasterStudy curriculum tables when repositories are unavailable.
	 *
	 * @return array
	 */
	private function get_table_curriculum() {
		global $wpdb;

		$sections_table  = $wpdb->prefix . 'stm_lms_curriculum_sections';
		$materials_table = $wpdb->prefix . 'stm_lms_curriculum_materials';

		$sections_exists  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $sections_table ) );
		$materials_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $materials_table ) );

		if ( $sections_table !== $sections_exists || $materials_table !== $materials_exists ) {
			return array();
		}

		$sections = $wpdb->get_results(
			$wpdb->prepare( 'SELECT id, title, `order` FROM %i WHERE course_id = %d ORDER BY `order` ASC, id ASC', $sections_table, intval( $this->ms_course_id ) ),
			ARRAY_A
		);

		if ( empty( $sections ) ) {
			return array();
		}

		$section_ids  = array_map( 'intval', wp_list_pluck( $sections, 'id' ) );
		$placeholders = implode( ',', array_fill( 0, count( $section_ids ), '%d' ) );
		$materials    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, post_id, post_type, section_id, `order` FROM {$materials_table} WHERE section_id IN ({$placeholders}) ORDER BY section_id ASC, `order` ASC, id ASC",
				$section_ids
			),
			ARRAY_A
		);

		$materials_by_section = array();
		foreach ( (array) $materials as $material ) {
			$section_id = isset( $material['section_id'] ) ? intval( $material['section_id'] ) : 0;
			if ( ! isset( $materials_by_section[ $section_id ] ) ) {
				$materials_by_section[ $section_id ] = array();
			}
			$materials_by_section[ $section_id ][] = $material;
		}

		foreach ( $sections as &$section ) {
			$section_id           = isset( $section['id'] ) ? intval( $section['id'] ) : 0;
			$section['materials'] = isset( $materials_by_section[ $section_id ] ) ? $materials_by_section[ $section_id ] : array();
		}

		return $this->normalize_repository_curriculum( $sections );
	}

	/**
	 * Normalize MasterStudy repository sections/materials.
	 *
	 * @param array $sections Sections.
	 * @return array
	 */
	private function normalize_repository_curriculum( $sections ) {
		if ( empty( $sections ) || ! is_array( $sections ) ) {
			return array();
		}

		usort(
			$sections,
			function ( $a, $b ) {
				$a_order = isset( $a['order'] ) ? intval( $a['order'] ) : 0;
				$b_order = isset( $b['order'] ) ? intval( $b['order'] ) : 0;
				return $a_order <=> $b_order;
			}
		);

		$normalized = array();
		foreach ( $sections as $index => $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}

			$section_id = isset( $section['id'] ) ? intval( $section['id'] ) : 0;
			$title      = isset( $section['title'] ) ? sanitize_text_field( $section['title'] ) : '';
			$materials  = isset( $section['materials'] ) && is_array( $section['materials'] ) ? $section['materials'] : array();

			usort(
				$materials,
				function ( $a, $b ) {
					$a_order = isset( $a['order'] ) ? intval( $a['order'] ) : 0;
					$b_order = isset( $b['order'] ) ? intval( $b['order'] ) : 0;
					return $a_order <=> $b_order;
				}
			);

			$items = array();
			foreach ( $materials as $material_index => $material ) {
				if ( ! is_array( $material ) ) {
					continue;
				}

				$post_id   = isset( $material['post_id'] ) ? intval( $material['post_id'] ) : 0;
				$post_type = isset( $material['post_type'] ) ? sanitize_key( $material['post_type'] ) : '';

				if ( ! $post_id ) {
					continue;
				}

				if ( '' === $post_type ) {
					$post_type = $this->infer_curriculum_item_type( $post_id );
				}

				if ( '' === $post_type ) {
					continue;
				}

				$items[] = array(
					'id'    => $post_id,
					'type'  => $post_type,
					'order' => isset( $material['order'] ) ? intval( $material['order'] ) : $material_index,
				);
			}

			$normalized[] = array(
				'section_key'   => $section_id > 0 ? 'masterstudy_section_' . $section_id : $this->generate_section_key( $title, $index, $items ),
				'section_title' => $title,
				'order'         => isset( $section['order'] ) ? intval( $section['order'] ) : $index,
				'items'         => $items,
			);
		}

		return $normalized;
	}

	/**
	 * Normalize top-level section/chapter structures.
	 *
	 * @param array $decoded Decoded curriculum data.
	 * @return array
	 */
	private function normalize_curriculum_sections( $decoded ) {
		$normalized = array();

		// Flat curriculum structures: section/item rows in sequence.
		$flat_has_rows = false;
		foreach ( $decoded as $flat_item ) {
			if ( is_array( $flat_item ) || is_string( $flat_item ) || is_numeric( $flat_item ) ) {
				$flat_has_rows = true;
				break;
			}
		}

		if ( $flat_has_rows ) {
			$section_index   = 0;
			$current_section = array(
				'section_key'   => $this->generate_section_key( '', $section_index, array() ),
				'section_title' => '',
				'items'         => array(),
			);

			foreach ( $decoded as $index => $row ) {
				if ( $this->is_section_marker_row( $row ) ) {
					if ( ! empty( $current_section['items'] ) ) {
						$normalized[] = $current_section;
					}

					$section_title = $this->extract_section_title( $row );
					++$section_index;
					$current_section = array(
						'section_key'   => $this->generate_section_key( $section_title, $section_index, array( $row ) ),
						'section_title' => $section_title,
						'items'         => array(),
					);
					continue;
				}

				$items = $this->normalize_curriculum_items( array( $row ) );
				if ( ! empty( $items ) ) {
					$current_section['items'] = array_merge( $current_section['items'], $items );
				}
			}

			if ( ! empty( $current_section['items'] ) ) {
				$normalized[] = $current_section;
			}
		}

		return $normalized;
	}

	/**
	 * Normalize curriculum items.
	 *
	 * @param array $items Items.
	 * @return array
	 */
	private function normalize_curriculum_items( $items ) {
		$normalized_items = array();

		foreach ( $items as $item ) {
			if ( is_array( $item ) ) {
				$id   = 0;
				$type = '';
				if ( isset( $item['id'] ) ) {
					$id = intval( $item['id'] );
				}
				if ( isset( $item['type'] ) ) {
					$type = sanitize_key( $item['type'] );
				} elseif ( isset( $item['post_type'] ) ) {
					$type = sanitize_key( $item['post_type'] );
				}

				if ( ! $id && isset( $item['item_id'] ) ) {
					$id = intval( $item['item_id'] );
				}
				if ( '' === $type && isset( $item['item_type'] ) ) {
					$type = sanitize_key( $item['item_type'] );
				}

				if ( ! $id && isset( $item['lesson_id'] ) ) {
					$id = intval( $item['lesson_id'] );
				}

				if ( ! $id && isset( $item['quiz_id'] ) ) {
					$id = intval( $item['quiz_id'] );
				}

				if ( ! $id && isset( $item['post_id'] ) ) {
					$id = intval( $item['post_id'] );
				}

				if ( '' === $type && isset( $item['lesson_id'] ) && intval( $item['lesson_id'] ) > 0 ) {
					$type = 'stm-lessons';
				}

				if ( '' === $type && isset( $item['quiz_id'] ) && intval( $item['quiz_id'] ) > 0 ) {
					$type = 'stm-quizzes';
				}

				if ( $id > 0 && '' === $type ) {
					$type = $this->infer_curriculum_item_type( $id );
				}

				if ( $id > 0 && '' !== $type ) {
					$normalized_items[] = array(
						'id'   => $id,
						'type' => $type,
					);
				}
				continue;
			}

			if ( is_numeric( $item ) ) {
				$id = intval( $item );
				if ( $id > 0 ) {
					$type = $this->infer_curriculum_item_type( $id );
					if ( '' !== $type ) {
						$normalized_items[] = array(
							'id'   => $id,
							'type' => $type,
						);
					}
				}
				continue;
			}

			if ( is_string( $item ) ) {
				$parts = explode( '-', $item );
				if ( 2 === count( $parts ) ) {
					$type = sanitize_key( $parts[0] );
					$id   = intval( $parts[1] );
					if ( $id > 0 ) {
						if ( '' === $type || 'item' === $type || 'post' === $type ) {
							$type = $this->infer_curriculum_item_type( $id );
						}
						$normalized_items[] = array(
							'id'   => $id,
							'type' => $type,
						);
					}
				}
			}
		}

		return $normalized_items;
	}

	/**
	 * Check if a row is a section marker in flat curriculum structures.
	 *
	 * @param mixed $row Curriculum row.
	 * @return bool
	 */
	private function is_section_marker_row( $row ) {
		if ( is_string( $row ) ) {
			$row_lc = strtolower( trim( $row ) );
			return 0 === strpos( $row_lc, 'section-' ) || 0 === strpos( $row_lc, 'section_' );
		}

		if ( is_array( $row ) ) {
			$type = '';
			if ( isset( $row['type'] ) ) {
				$type = strtolower( sanitize_key( $row['type'] ) );
			} elseif ( isset( $row['item_type'] ) ) {
				$type = strtolower( sanitize_key( $row['item_type'] ) );
			} elseif ( isset( $row['post_type'] ) ) {
				$type = strtolower( sanitize_key( $row['post_type'] ) );
			}

			if ( in_array( $type, array( 'section', 'stm-section', 'stm-sections' ), true ) ) {
				return true;
			}

			if ( isset( $row['section_id'] ) || isset( $row['section_title'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Extract section title from marker row.
	 *
	 * @param mixed $row Curriculum row.
	 * @return string
	 */
	private function extract_section_title( $row ) {
		if ( is_array( $row ) ) {
			if ( ! empty( $row['section_title'] ) ) {
				return sanitize_text_field( $row['section_title'] );
			}
			if ( ! empty( $row['title'] ) ) {
				return sanitize_text_field( $row['title'] );
			}
			if ( ! empty( $row['label'] ) ) {
				return sanitize_text_field( $row['label'] );
			}
		}

		if ( is_string( $row ) ) {
			if ( false !== strpos( $row, '-' ) ) {
				$parts = explode( '-', $row, 2 );
				return isset( $parts[1] ) ? sanitize_text_field( $parts[1] ) : '';
			}
			if ( false !== strpos( $row, '_' ) ) {
				$parts = explode( '_', $row, 2 );
				return isset( $parts[1] ) ? sanitize_text_field( $parts[1] ) : '';
			}
		}

		return '';
	}

	/**
	 * Infer curriculum item type from source post type.
	 *
	 * @param int $source_id Source post id.
	 * @return string
	 */
	private function infer_curriculum_item_type( $source_id ) {
		$post_type = get_post_type( $source_id );

		if ( in_array( $post_type, array( 'stm-lessons', 'lesson', 'stm_lesson' ), true ) ) {
			return 'stm-lessons';
		}

		if ( in_array( $post_type, array( 'stm-quizzes', 'quiz', 'stm_quiz' ), true ) ) {
			return 'stm-quizzes';
		}

		return '';
	}

	/**
	 * Migrate lesson entity.
	 *
	 * @param int $new_chapter_id Chapter ID.
	 * @param int $old_lesson_id Source lesson ID.
	 * @param int $index Item order.
	 */
	private function migrate_lesson( $new_chapter_id, $old_lesson_id, $index = 0 ) {
		global $wpdb;

		$old_lesson = get_post( $old_lesson_id );
		if ( ! $old_lesson || 'stm-lessons' !== $old_lesson->post_type ) {
			do_action( 'ohmylms_migration_skipped', $this->source_key, $old_lesson_id, 'lesson_not_found' );
			return;
		}

		$source_meta = get_post_meta( $old_lesson_id );
		$lesson_type = $this->get_creator_lesson_type( $source_meta );
		$excerpt     = isset( $old_lesson->post_excerpt ) ? $old_lesson->post_excerpt : '';
		if ( '' === trim( (string) $excerpt ) ) {
			$excerpt = (string) $this->get_first_meta_value( $source_meta, array( 'lesson_excerpt', 'excerpt', '_lesson_excerpt', '_excerpt' ) );
		}
		$excerpt             = sanitize_textarea_field( (string) $excerpt );
		$description_content = isset( $old_lesson->post_content ) ? (string) $old_lesson->post_content : '';
		if ( '' === trim( wp_strip_all_tags( $description_content ) ) ) {
			$description_content = (string) $this->get_first_meta_value( $source_meta, array( 'lesson_excerpt', 'excerpt', '_lesson_excerpt', '_excerpt' ) );
		}
		$description_content = wp_kses_post( $description_content );
		$new_lesson_id       = $this->find_existing_migrated_post( 'ohmylms-lesson', $old_lesson_id );
		if ( ! $new_lesson_id ) {
			$new_lesson_data = array(
				'post_title'   => sanitize_text_field( $old_lesson->post_title ),
				'post_content' => $description_content,
				'post_excerpt' => $excerpt,
				'post_status'  => $old_lesson->post_status,
				'post_author'  => intval( $old_lesson->post_author ),
				'post_type'    => 'ohmylms-lesson',
				'menu_order'   => intval( $old_lesson->menu_order ) > 0 ? intval( $old_lesson->menu_order ) : intval( $index ),
			);

			$new_lesson_id = wp_insert_post( $new_lesson_data );
			if ( is_wp_error( $new_lesson_id ) ) {
				return;
			}

			$this->save_source_mapping( $new_lesson_id, $old_lesson_id );
		}

		wp_update_post(
			array(
				'ID'           => $new_lesson_id,
				'post_content' => $description_content,
				'post_excerpt' => $excerpt,
			)
		);

		$this->migrate_lesson_metadata( $new_lesson_id, $source_meta );
		$lesson_type = (string) get_post_meta( $new_lesson_id, '_type', true );
		if ( '' === $lesson_type ) {
			$lesson_type = $this->get_creator_lesson_type( $source_meta );
		}
		$this->insert_content_relationship( $new_chapter_id, $new_lesson_id, $lesson_type, $index );
	}

	/**
	 * Migrate lesson metadata.
	 *
	 * @param int   $new_lesson_id New lesson id.
	 * @param array $meta_data Source meta.
	 */
	private function migrate_lesson_metadata( $new_lesson_id, $meta_data ) {
		$lesson_type = $this->get_creator_lesson_type( $meta_data );
		update_post_meta( $new_lesson_id, '_type', $lesson_type );

		$thumbnail_id = $this->get_first_meta_value( $meta_data, array( '_thumbnail_id', 'thumbnail_id' ) );
		if ( '' !== $thumbnail_id ) {
			update_post_meta( $new_lesson_id, '_thumbnail_id', intval( $thumbnail_id ) );
		}

		$duration = $this->extract_duration( $meta_data );
		if ( ! empty( $duration ) ) {
			update_post_meta( $new_lesson_id, '_duration', $duration );
		}

		$preview = $this->get_first_meta_value( $meta_data, array( 'preview_lesson', '_preview', 'preview' ) );
		if ( in_array( $preview, array( 'yes', 'on', '1', 1, true ), true ) ) {
			update_post_meta( $new_lesson_id, '_preview_enable', true );
		}

		$external_url = $this->resolve_masterstudy_media_url( $meta_data );
		if ( '' !== $external_url ) {
			update_post_meta( $new_lesson_id, '_external_url', esc_url_raw( $external_url ) );
		}

		$video_id = intval( $this->get_first_meta_value( $meta_data, array( 'lesson_video' ) ) );
		if ( $video_id > 0 ) {
			update_post_meta( $new_lesson_id, '_video_id', $video_id );
		}

		$audio_id = intval( $this->get_first_meta_value( $meta_data, array( 'lesson_audio' ) ) );
		if ( $audio_id > 0 ) {
			update_post_meta( $new_lesson_id, '_audio_id', $audio_id );
		}

		$lesson_files = $this->get_first_meta_value( $meta_data, array( 'lesson_files' ) );
		if ( '' !== $lesson_files && null !== $lesson_files ) {
			update_post_meta( $new_lesson_id, '_download_resource', maybe_unserialize( $lesson_files ) );
		}
	}

	/**
	 * Get OhMyLMS lesson content type.
	 *
	 * @param array $meta_data Source meta.
	 * @return string
	 */
	private function get_creator_lesson_type( $meta_data ) {
		$source_type = $this->normalize_source_lesson_type( $this->get_first_meta_value( $meta_data, array( 'type', 'lesson_type', '_type', '_lesson_type' ) ) );
		if ( '' !== $source_type ) {
			return $source_type;
		}

		$video_type = strtolower( (string) $this->get_first_meta_value( $meta_data, array( 'video_type', '_video_type' ) ) );
		if ( '' !== $video_type && 'none' !== $video_type ) {
			return 'video';
		}

		$audio_type = strtolower( (string) $this->get_first_meta_value( $meta_data, array( 'audio_type', '_audio_type' ) ) );
		if ( '' !== $audio_type && 'none' !== $audio_type ) {
			return 'audio';
		}

		$explicit_video = $this->get_first_meta_value( $meta_data, array( 'lesson_ext_link_url', 'lesson_youtube_url', 'lesson_vimeo_url', 'lesson_shortcode', 'lesson_embed_ctx' ) );
		if ( '' !== (string) $explicit_video ) {
			return 'video';
		}

		$audio_url = $this->get_first_meta_value( $meta_data, array( 'audio', 'audio_url', '_audio_url', 'lesson_audio', '_lesson_audio' ) );
		if ( '' !== $audio_url ) {
			return 'audio';
		}

		$legacy_video_file = $this->get_first_meta_value( $meta_data, array( 'lesson_video', '_lesson_video' ) );
		if ( '' !== (string) $legacy_video_file ) {
			return 'video';
		}

		$video_candidate = $this->get_first_meta_value( $meta_data, array( 'video', 'video_url', '_video_url', 'external_url', '_external_url' ) );
		if ( '' !== (string) $video_candidate ) {
			return 'video';
		}

		return 'text';
	}

	/**
	 * Normalize MasterStudy lesson type labels/keys.
	 *
	 * @param mixed $raw_type Source lesson type.
	 * @return string
	 */
	private function normalize_source_lesson_type( $raw_type ) {
		if ( ! is_scalar( $raw_type ) ) {
			return '';
		}

		$value = strtolower( trim( sanitize_text_field( (string) $raw_type ) ) );
		if ( '' === $value ) {
			return '';
		}

		$value = str_replace( '_', '-', $value );

		if ( false !== strpos( $value, 'video' ) ) {
			return 'video';
		}

		if ( false !== strpos( $value, 'audio' ) ) {
			return 'audio';
		}

		if ( false !== strpos( $value, 'pdf' ) ) {
			return 'text';
		}

		if ( false !== strpos( $value, 'text' ) ) {
			return 'text';
		}

		return '';
	}

	/**
	 * Resolve MasterStudy video or external media URL from known meta keys.
	 *
	 * @param array $meta_data Source lesson meta.
	 * @return string
	 */
	private function resolve_masterstudy_media_url( $meta_data ) {
		$external_url = $this->get_first_meta_value(
			$meta_data,
			array(
				'lesson_ext_link_url',
				'lesson_youtube_url',
				'lesson_vimeo_url',
				'video_url',
				'_video_url',
				'video',
			)
		);
		if ( '' !== (string) $external_url ) {
			return (string) $external_url;
		}

		$embed = $this->get_first_meta_value( $meta_data, array( 'lesson_embed_ctx' ) );
		if ( is_string( $embed ) && false !== strpos( $embed, 'http' ) ) {
			if ( preg_match( '#https?://[^"\']+#', $embed, $matches ) ) {
				return $matches[0];
			}
		}

		$attachment_id = intval( $this->get_first_meta_value( $meta_data, array( 'lesson_video' ) ) );
		if ( $attachment_id > 0 ) {
			$attachment_url = wp_get_attachment_url( $attachment_id );
			if ( is_string( $attachment_url ) && '' !== $attachment_url ) {
				return $attachment_url;
			}
		}

		return '';
	}

	/**
	 * Migrate quiz entity and questions.
	 *
	 * @param int $new_chapter_id Chapter ID.
	 * @param int $old_quiz_id Source quiz ID.
	 * @param int $index Item order.
	 */
	private function migrate_quiz( $new_chapter_id, $old_quiz_id, $index = 0 ) {
		$old_quiz = get_post( $old_quiz_id );
		if ( ! $old_quiz || 'stm-quizzes' !== $old_quiz->post_type ) {
			do_action( 'ohmylms_migration_skipped', $this->source_key, $old_quiz_id, 'quiz_not_found' );
			return;
		}

		$new_quiz_id  = $this->find_existing_migrated_post( 'ohmylms-quiz', $old_quiz_id );
		$source_meta  = get_post_meta( $old_quiz_id );
		$quiz_excerpt = isset( $old_quiz->post_excerpt ) ? $old_quiz->post_excerpt : '';
		if ( '' === trim( (string) $quiz_excerpt ) ) {
			$quiz_excerpt = (string) $this->get_first_meta_value( $source_meta, array( 'lesson_excerpt', 'excerpt', '_lesson_excerpt', '_excerpt' ) );
		}
		if ( ! $new_quiz_id ) {
			$new_quiz_data = array(
				'post_title'   => sanitize_text_field( $old_quiz->post_title ),
				'post_content' => wp_kses_post( $old_quiz->post_content ),
				'post_excerpt' => sanitize_textarea_field( $quiz_excerpt ),
				'post_status'  => $old_quiz->post_status,
				'post_author'  => intval( $old_quiz->post_author ),
				'post_type'    => 'ohmylms-quiz',
				'menu_order'   => intval( $old_quiz->menu_order ) > 0 ? intval( $old_quiz->menu_order ) : intval( $index ),
			);

			$new_quiz_id = wp_insert_post( $new_quiz_data );
			if ( is_wp_error( $new_quiz_id ) ) {
				return;
			}

			$this->save_source_mapping( $new_quiz_id, $old_quiz_id );
		}

		wp_update_post(
			array(
				'ID'           => $new_quiz_id,
				'post_excerpt' => sanitize_textarea_field( $quiz_excerpt ),
			)
		);

		$this->migrate_quiz_metadata( $new_quiz_id, $source_meta );
		$this->migrate_quiz_questions( $new_quiz_id, $old_quiz_id );
		$this->insert_content_relationship( $new_chapter_id, $new_quiz_id, 'quiz', $index );
	}

	/**
	 * Migrate quiz metadata.
	 *
	 * @param int   $new_quiz_id New quiz id.
	 * @param array $meta_data Source quiz meta.
	 */
	private function migrate_quiz_metadata( $new_quiz_id, $meta_data ) {
		$attempts              = intval( $this->get_first_meta_value( $meta_data, array( 'attempts', 'attempts_allowed', '_attempts', 'quiz_attempts' ) ) );
		$passing               = intval( $this->get_first_meta_value( $meta_data, array( 'passing_grade', 'pass_mark', '_passing_grade' ) ) );
		$duration_raw          = $this->get_first_meta_value( $meta_data, array( 'duration', 'quiz_duration', '_duration' ) );
		$duration_measure      = strtolower( (string) $this->get_first_meta_value( $meta_data, array( 'duration_measure' ) ) );
		$random_questions      = strtolower( (string) $this->get_first_meta_value( $meta_data, array( 'random_questions' ) ) );
		$random_answers        = strtolower( (string) $this->get_first_meta_value( $meta_data, array( 'random_answers' ) ) );
		$retry_after_passing   = strtolower( (string) $this->get_first_meta_value( $meta_data, array( 'retry_after_passing' ) ) );
		$show_attempts_history = strtolower( (string) $this->get_first_meta_value( $meta_data, array( 'show_attempts_history' ) ) );

		$settings = array(
			'allow_attempts'       => $attempts > 0 ? $attempts : 1,
			'randomize_questions'  => in_array( $random_questions, array( 'on', '1', 'yes', 'true' ), true ),
			'hide_question_number' => false,
			'short_text_limit'     => 200,
			'long_text_limit'      => 600,
			'layout'               => 'single_page',
			'passing_grade'        => array(
				'enabled' => $passing > 0,
				'value'   => $passing > 0 ? $passing : 0,
			),
			'time_limit'           => array(
				'enabled' => false,
				'value'   => 0,
				'type'    => 'minutes',
			),
			'show_correct_review'  => in_array( $show_attempts_history, array( 'on', '1', 'yes', 'true' ), true ),
			'instant_check'        => false,
			'hide_answers'         => ! in_array( $random_answers, array( 'on', '1', 'yes', 'true' ), true ),
			'retry_after_passing'  => in_array( $retry_after_passing, array( 'on', '1', 'yes', 'true' ), true ),
		);

		if ( '' !== $duration_raw ) {
			$duration = intval( $duration_raw );
			if ( $duration > 0 ) {
				$settings['time_limit']['enabled'] = true;
				$settings['time_limit']['value']   = $duration;
				if ( in_array( $duration_measure, array( 'hour', 'hours' ), true ) ) {
					$settings['time_limit']['type'] = 'hours';
				}
				if ( in_array( $duration_measure, array( 'day', 'days' ), true ) ) {
					$settings['time_limit']['type'] = 'days';
				}
			}
		}

		update_post_meta( $new_quiz_id, '_quiz_settings', $settings );
	}

	/**
	 * Migrate quiz questions and answers.
	 *
	 * @param int $new_quiz_id New quiz ID.
	 * @param int $old_quiz_id Source quiz ID.
	 */
	private function migrate_quiz_questions( $new_quiz_id, $old_quiz_id ) {
		$question_ids = $this->extract_quiz_question_ids( $old_quiz_id );
		if ( empty( $question_ids ) ) {
			return;
		}

		global $wpdb;

		foreach ( $question_ids as $index => $old_question_id ) {
			$old_question = get_post( $old_question_id );
			if ( ! $old_question || ! in_array( $old_question->post_type, array( 'stm-questions', 'question', 'stm_question' ), true ) ) {
				do_action( 'ohmylms_migration_skipped', $this->source_key, $old_question_id, 'question_not_found' );
				continue;
			}

			$new_question_id = $this->find_existing_migrated_post( 'ohmylms-question', $old_question_id );
			if ( ! $new_question_id ) {
				$source_question_meta = get_post_meta( $old_question_id );
				$question_title       = sanitize_text_field( $old_question->post_title );
				if ( '' === trim( $question_title ) ) {
					$question_title = sanitize_text_field( wp_strip_all_tags( (string) $this->get_first_meta_value( $source_question_meta, array( 'question' ) ) ) );
				}
				$question_content = wp_kses_post( $old_question->post_content );
				if ( '' === trim( wp_strip_all_tags( $question_content ) ) ) {
					$question_content = wp_kses_post( (string) $this->get_first_meta_value( $source_question_meta, array( 'question', 'question_explanation' ) ) );
				}

				$new_question_data = array(
					'post_title'   => $question_title,
					'post_content' => $question_content,
					'post_excerpt' => sanitize_text_field( $old_question->post_excerpt ),
					'post_status'  => $old_question->post_status,
					'post_author'  => intval( $old_question->post_author ),
					'post_type'    => 'ohmylms-question',
					'menu_order'   => intval( $old_question->menu_order ),
				);

				$new_question_id = wp_insert_post( $new_question_data );
				if ( is_wp_error( $new_question_id ) ) {
					continue;
				}

				$this->save_source_mapping( $new_question_id, $old_question_id );
			}

			$this->migrate_question_metadata( $new_question_id, get_post_meta( $old_question_id ) );
			$this->migrate_question_answers( $new_question_id, get_post_meta( $old_question_id ) );

			$exists = $wpdb->get_var(
				$wpdb->prepare( 'SELECT id FROM %i WHERE quiz_id = %d AND question_id = %d', $wpdb->prefix . 'ohmylms_quiz_questions_relationship', $new_quiz_id, $new_question_id )
			);

			if ( ! $exists ) {
				$wpdb->insert(
					$wpdb->prefix . 'ohmylms_quiz_questions_relationship',
					array(
						'quiz_id'      => $new_quiz_id,
						'question_id'  => $new_question_id,
						'order_number' => $index,
					),
					array( '%d', '%d', '%d' )
				);
			} else {
				$wpdb->update(
					$wpdb->prefix . 'ohmylms_quiz_questions_relationship',
					array(
						'order_number' => $index,
					),
					array(
						'id' => intval( $exists ),
					),
					array( '%d' ),
					array( '%d' )
				);
			}
		}
	}

	/**
	 * Migrate question settings.
	 *
	 * @param int   $new_question_id Question id.
	 * @param array $meta_data Source meta.
	 */
	private function migrate_question_metadata( $new_question_id, $meta_data ) {
		$source_type = strtolower( (string) $this->get_first_meta_value( $meta_data, array( 'type', 'question_type', '_question_type' ) ) );
		$score       = intval( $this->get_first_meta_value( $meta_data, array( 'score', 'points', 'mark', 'question_mark' ) ) );

		$type_map = array(
			'single_choice'     => 'single-choice',
			'multi_choice'      => 'multiple-choice',
			'multiple_choice'   => 'multiple-choice',
			'true_false'        => 'true-false',
			'true_or_false'     => 'true-false',
			'fill_the_gap'      => 'fill-in-the-blank',
			'fill_in_the_gap'   => 'fill-in-the-blank',
			'fill_in_the_blank' => 'fill-in-the-blank',
			'keywords'          => 'short-text',
			'item_match'        => 'matching',
			'image_match'       => 'matching',
			'sortable'          => 'reorder',
			'question_bank'     => 'single-choice',
		);

		$mapped_type = isset( $type_map[ $source_type ] ) ? $type_map[ $source_type ] : 'single-choice';

		$settings = array(
			'type'      => $mapped_type,
			'randomize' => false,
			'required'  => true,
			'score'     => array(
				'enabled' => $score > 0,
				'value'   => $score > 0 ? $score : 0,
			),
		);

		update_post_meta( $new_question_id, '_question_settings', $settings );
	}

	/**
	 * Migrate question answers.
	 *
	 * @param int   $new_question_id New question id.
	 * @param array $meta_data Source question meta.
	 */
	private function migrate_question_answers( $new_question_id, $meta_data ) {
		$answer_sets = array(
			$this->get_first_meta_value( $meta_data, array( 'answers', '_answers', 'question_answers' ) ),
		);

		$parsed_answers = array();
		foreach ( $answer_sets as $answer_set ) {
			if ( empty( $answer_set ) ) {
				continue;
			}

			$decoded = maybe_unserialize( $answer_set );
			if ( is_string( $decoded ) ) {
				$json = json_decode( $decoded, true );
				if ( JSON_ERROR_NONE === json_last_error() ) {
					$decoded = $json;
				}
			}

			if ( is_array( $decoded ) ) {
				$parsed_answers = $decoded;
				break;
			}
		}

		if ( empty( $parsed_answers ) ) {
			do_action( 'ohmylms_migration_skipped', $this->source_key, $new_question_id, 'answers_not_found' );
			return;
		}

		global $wpdb;
		foreach ( $parsed_answers as $index => $answer ) {
			$answer_meta = array();

			if ( is_string( $answer ) ) {
				$answer_text = $answer;
				$is_correct  = false;
			} elseif ( is_array( $answer ) ) {
				$answer_text = isset( $answer['text'] ) ? $answer['text'] : ( $answer['title'] ?? '' );
				if ( '' === trim( (string) $answer_text ) && isset( $answer['question'] ) ) {
					$answer_text = (string) $answer['question'];
				}

				$is_correct = false;
				foreach ( array( 'is_correct', 'correct', 'isTrue', 'is_true', 'isRight', 'right' ) as $correct_key ) {
					if ( ! array_key_exists( $correct_key, $answer ) ) {
						continue;
					}
					$is_correct = in_array( $answer[ $correct_key ], array( true, 1, '1', 'on', 'yes', 'true' ), true );
					break;
				}

				$matching_payload = array(
					'label'     => isset( $answer['text'] ) ? (string) $answer['text'] : '',
					'image_url' => '',
				);

				if ( ! empty( $answer['question'] ) ) {
					$matching_payload['label'] = (string) $answer['question'];
				}

				if ( isset( $answer['question_image'] ) && is_array( $answer['question_image'] ) && ! empty( $answer['question_image']['url'] ) ) {
					$matching_payload['image_url'] = esc_url_raw( (string) $answer['question_image']['url'] );
					if ( ! empty( $answer['question_image']['id'] ) ) {
						$answer_meta['_thumbnail_id'] = intval( $answer['question_image']['id'] );
					}
				} elseif ( isset( $answer['text_image'] ) && is_array( $answer['text_image'] ) && ! empty( $answer['text_image']['url'] ) ) {
					$matching_payload['image_url'] = esc_url_raw( (string) $answer['text_image']['url'] );
					if ( ! empty( $answer['text_image']['id'] ) ) {
						$answer_meta['_thumbnail_id'] = intval( $answer['text_image']['id'] );
					}
				}

				if ( '' !== trim( (string) $matching_payload['label'] ) || '' !== trim( (string) $matching_payload['image_url'] ) ) {
					$answer_meta['_matching_data'] = $matching_payload;
				}
			} else {
				continue;
			}

			if ( '' === trim( (string) $answer_text ) ) {
				continue;
			}

			$exists = $wpdb->get_var(
				$wpdb->prepare( 'SELECT id FROM %i WHERE question_id = %d AND answer = %s', $wpdb->prefix . 'ohmylms_question_answers', $new_question_id, $answer_text )
			);

			if ( $exists ) {
				$wpdb->update(
					$wpdb->prefix . 'ohmylms_question_answers',
					array(
						'order_number' => $index,
						'is_correct'   => $is_correct ? 1 : 0,
					),
					array(
						'id' => intval( $exists ),
					),
					array( '%d', '%d' ),
					array( '%d' )
				);

				if ( ! empty( $answer_meta ) ) {
					$this->upsert_answer_meta( intval( $exists ), $answer_meta );
				}
				continue;
			}

			$wpdb->insert(
				$wpdb->prefix . 'ohmylms_question_answers',
				array(
					'question_id'  => $new_question_id,
					'answer'       => wp_kses_post( $answer_text ),
					'order_number' => $index,
					'is_correct'   => $is_correct ? 1 : 0,
				),
				array( '%d', '%s', '%d', '%d' )
			);

			if ( ! empty( $answer_meta ) && ! empty( $wpdb->insert_id ) ) {
				$this->upsert_answer_meta( intval( $wpdb->insert_id ), $answer_meta );
			}
		}
	}

	/**
	 * Upsert answer meta entries for a question answer.
	 *
	 * @param int   $answer_id Answer ID.
	 * @param array $meta_entries Meta entries.
	 * @return void
	 */
	private function upsert_answer_meta( $answer_id, $meta_entries ) {
		if ( $answer_id <= 0 || empty( $meta_entries ) || ! is_array( $meta_entries ) ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ohmylms_question_answermeta';

		foreach ( $meta_entries as $meta_key => $meta_value ) {
			$exists = $wpdb->get_var(
				$wpdb->prepare( 'SELECT id FROM %i WHERE answer_id = %d AND meta_key = %s LIMIT 1', $table, $answer_id, $meta_key )
			);

			$serialized_value = maybe_serialize( $meta_value );

			if ( $exists ) {
				$wpdb->update(
					$table,
					array(
						'meta_value' => $serialized_value,
					),
					array(
						'id' => intval( $exists ),
					),
					array( '%s' ),
					array( '%d' )
				);
				continue;
			}

			$wpdb->insert(
				$table,
				array(
					'answer_id'  => $answer_id,
					'meta_key'   => $meta_key,
					'meta_value' => $serialized_value,
				),
				array( '%d', '%s', '%s' )
			);
		}
	}

	/**
	 * Migrate enrolled students when data is reliably available.
	 *
	 * @param int $new_course_id New course ID.
	 */
	private function migrate_students( $new_course_id ) {
		$enrolled_user_ids = array();

		$user_meta = get_post_meta( $this->ms_course_id, 'current_students', true );
		if ( is_array( $user_meta ) ) {
			foreach ( $user_meta as $user_id ) {
				$user_id = intval( $user_id );
				if ( $user_id > 0 ) {
					$enrolled_user_ids[] = $user_id;
				}
			}
		}

		$enrolled_user_ids = array_values( array_unique( $enrolled_user_ids ) );
		if ( empty( $enrolled_user_ids ) ) {
			do_action( 'ohmylms_migration_skipped', $this->source_key, $this->ms_course_id, 'enrollments_not_found' );
			return;
		}

		global $wpdb;
		foreach ( $enrolled_user_ids as $user_id ) {
			$exists = $wpdb->get_var(
				$wpdb->prepare( 'SELECT id FROM %i WHERE user_id = %d AND course_id = %d', $wpdb->prefix . 'ohmylms_user_enrollment', $user_id, $new_course_id )
			);

			if ( $exists ) {
				continue;
			}

			$wpdb->insert(
				$wpdb->prefix . 'ohmylms_user_enrollment',
				array(
					'user_id'    => $user_id,
					'course_id'  => $new_course_id,
					'order_id'   => 0,
					'status'     => 'enrolled',
					'progress'   => '0',
					'start_date' => current_datetime()->format( 'Y-m-d H:i:s' ),
					'end_date'   => '0000-00-00 00:00:00',
				),
				array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
			);
		}
	}

	/**
	 * Insert chapter relation without duplicates.
	 *
	 * @param int $course_id Course ID.
	 * @param int $chapter_id Chapter ID.
	 * @param int $index Order index.
	 */
	private function insert_chapter_relationship( $course_id, $chapter_id, $index ) {
		global $wpdb;

		$exists = $wpdb->get_var(
			$wpdb->prepare( 'SELECT id FROM %i WHERE course_id = %d AND chapter_id = %d', $wpdb->prefix . 'ohmylms_chapter_relationship', $course_id, $chapter_id )
		);

		if ( $exists ) {
			return;
		}

		$wpdb->insert(
			$wpdb->prefix . 'ohmylms_chapter_relationship',
			array(
				'course_id'    => $course_id,
				'chapter_id'   => $chapter_id,
				'order_number' => $index,
			),
			array( '%d', '%d', '%d' )
		);
	}

	/**
	 * Insert content relation without duplicates.
	 *
	 * @param int    $chapter_id Chapter ID.
	 * @param int    $content_id Content ID.
	 * @param string $content_type Content type.
	 * @param int    $index Order index.
	 */
	private function insert_content_relationship( $chapter_id, $content_id, $content_type, $index ) {
		global $wpdb;

		$exact_match = $wpdb->get_var(
			$wpdb->prepare( 'SELECT id FROM %i WHERE chapter_id = %d AND content_id = %d AND content_type = %s LIMIT 1', $wpdb->prefix . 'ohmylms_content_relationship', $chapter_id, $content_id, $content_type )
		);

		if ( $exact_match ) {
			$wpdb->update(
				$wpdb->prefix . 'ohmylms_content_relationship',
				array(
					'order_number' => $index,
				),
				array(
					'id' => intval( $exact_match ),
				),
				array( '%d' ),
				array( '%d' )
			);
			$wpdb->query(
				$wpdb->prepare( 'DELETE FROM %i WHERE chapter_id = %d AND content_id = %d AND id <> %d', $wpdb->prefix . 'ohmylms_content_relationship', $chapter_id, $content_id, intval( $exact_match ) )
			);
			return;
		}

		$exists = $wpdb->get_var(
			$wpdb->prepare( 'SELECT id FROM %i WHERE chapter_id = %d AND content_id = %d LIMIT 1', $wpdb->prefix . 'ohmylms_content_relationship', $chapter_id, $content_id )
		);

		if ( $exists ) {
			$wpdb->update(
				$wpdb->prefix . 'ohmylms_content_relationship',
				array(
					'content_type' => $content_type,
					'order_number' => $index,
				),
				array(
					'id' => intval( $exists ),
				),
				array( '%s', '%d' ),
				array( '%d' )
			);
			return;
		}

		$wpdb->insert(
			$wpdb->prefix . 'ohmylms_content_relationship',
			array(
				'chapter_id'   => $chapter_id,
				'content_id'   => $content_id,
				'content_type' => $content_type,
				'order_number' => $index,
			),
			array( '%d', '%d', '%s', '%d' )
		);
	}

	/**
	 * Creates chapter or reuses mapped one.
	 *
	 * @param int    $new_course_id Course id.
	 * @param string $title Chapter title.
	 * @param int    $index Order index.
	 * @param string $section_key Source section key.
	 * @return int
	 */
	private function upsert_chapter( $new_course_id, $title, $index, $section_key ) {
		$mapped_chapter_id = $this->find_existing_migrated_post( 'ohmylms-chapter', $section_key );
		if ( $mapped_chapter_id ) {
			$this->insert_chapter_relationship( $new_course_id, $mapped_chapter_id, $index );
			return $mapped_chapter_id;
		}

		$chapter_slug = sanitize_title( $title . '-' . $index );

		$existing = get_posts(
			array(
				'post_type'      => 'ohmylms-chapter',
				'post_status'    => array( 'publish', 'draft', 'private', 'pending', 'future' ),
				'name'           => $chapter_slug,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $existing ) ) {
			$chapter_id = intval( $existing[0] );
			$this->insert_chapter_relationship( $new_course_id, $chapter_id, $index );
			return $chapter_id;
		}

		$new_chapter = array(
			'post_title'   => sanitize_text_field( $title ),
			'post_content' => '',
			'post_status'  => 'publish',
			'post_author'  => get_current_user_id(),
			'post_type'    => 'ohmylms-chapter',
			'post_name'    => $chapter_slug,
		);

		$chapter_id = wp_insert_post( $new_chapter );
		if ( is_wp_error( $chapter_id ) ) {
			return 0;
		}

		$this->save_source_mapping( $chapter_id, $section_key );
		$this->insert_chapter_relationship( $new_course_id, $chapter_id, $index );
		return intval( $chapter_id );
	}

	/**
	 * Normalize source level values to OhMyLMS level values.
	 *
	 * @param string $source_level Source level.
	 * @return string
	 */
	private function normalize_level( $source_level ) {
		$level = strtolower( trim( (string) $source_level ) );
		$level = str_replace( array( '-', ' ' ), '_', $level );

		$map = array(
			'beginner'     => 'beginner',
			'begineer'     => 'beginner',
			'intermediate' => 'experience',
			'advanced'     => 'expert',
			'advanded'     => 'expert',
			'all'          => 'all',
			'all_levels'   => 'all',
		);

		if ( isset( $map[ $level ] ) ) {
			return $map[ $level ];
		}

		if ( '' !== $level ) {
			do_action( 'ohmylms_migration_skipped', $this->source_key, $this->ms_course_id, 'unsupported_level_value' );
		}

		return 'beginner';
	}

	/**
	 * Generate stable section key.
	 *
	 * @param string $title Section title.
	 * @param int    $index Section index.
	 * @param array  $items Section items.
	 * @return string
	 */
	private function generate_section_key( $title, $index, $items ) {
		$item_fingerprint = wp_json_encode( $items );
		return 'section_' . md5( sanitize_text_field( (string) $title ) . '|' . intval( $index ) . '|' . (string) $item_fingerprint );
	}

	/**
	 * Find mapped OhMyLMS post by source id.
	 *
	 * @param string $post_type Post type.
	 * @param int    $source_id Source id.
	 * @return int
	 */
	private function find_existing_migrated_post( $post_type, $source_id ) {
		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => array( 'publish', 'draft', 'private', 'pending', 'future' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => '_ohmylms_migration_source',
						'value' => $this->source_key,
					),
					array(
						'key'   => '_ohmylms_migration_source_id',
						'value' => strval( $source_id ),
					),
				),
			)
		);

		return ! empty( $posts ) ? intval( $posts[0] ) : 0;
	}

	/**
	 * Save migration mapping meta.
	 *
	 * @param int $new_id New post id.
	 * @param int $source_id Source id.
	 */
	private function save_source_mapping( $new_id, $source_id ) {
		update_post_meta( $new_id, '_ohmylms_migration_source', $this->source_key );
		update_post_meta( $new_id, '_ohmylms_migration_source_id', strval( $source_id ) );
	}

	/**
	 * Extract first available meta value.
	 *
	 * @param array $meta_data Meta data array.
	 * @param array $keys Candidate keys.
	 * @return mixed|string
	 */
	private function get_first_meta_value( $meta_data, $keys ) {
		foreach ( $keys as $key ) {
			if ( ! isset( $meta_data[ $key ] ) ) {
				continue;
			}

			$value = $meta_data[ $key ];
			if ( is_array( $value ) ) {
				foreach ( $value as $single_value ) {
					if ( '' !== $single_value && null !== $single_value ) {
						return $single_value;
					}
				}
				continue;
			}

			if ( '' !== $value && null !== $value ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * Extract duration into CLMS structure.
	 *
	 * @param array $meta_data Meta data.
	 * @return array
	 */
	private function extract_duration( $meta_data ) {
		$duration = array();

		$hours   = intval( $this->get_first_meta_value( $meta_data, array( 'duration_hours', '_duration_hours' ) ) );
		$minutes = intval( $this->get_first_meta_value( $meta_data, array( 'duration_minutes', '_duration_minutes' ) ) );

		if ( $hours > 0 ) {
			$duration['hour'] = strval( $hours );
		}
		if ( $minutes > 0 ) {
			$duration['min'] = strval( $minutes );
		}

		if ( ! empty( $duration ) ) {
			return $duration;
		}

		$raw = $this->get_first_meta_value( $meta_data, array( 'duration', '_duration', 'course_duration' ) );
		if ( '' === $raw ) {
			return $duration;
		}

		if ( is_numeric( $raw ) ) {
			$duration['min'] = strval( intval( $raw ) );
			return $duration;
		}

		$parts = explode( ' ', trim( strtolower( (string) $raw ) ) );
		if ( count( $parts ) >= 2 ) {
			$value = intval( $parts[0] );
			$unit  = $parts[1];
			if ( $value > 0 ) {
				if ( false !== strpos( $unit, 'hour' ) ) {
					$duration['hour'] = strval( $value );
				} elseif ( false !== strpos( $unit, 'min' ) ) {
					$duration['min'] = strval( $value );
				} elseif ( false !== strpos( $unit, 'day' ) ) {
					$duration['day'] = strval( $value );
				} elseif ( false !== strpos( $unit, 'week' ) ) {
					$duration['week'] = strval( $value );
				}
			}
		}

		return $duration;
	}

	/**
	 * Extract source question ids from quiz meta.
	 *
	 * @param int $old_quiz_id Source quiz id.
	 * @return array
	 */
	private function extract_quiz_question_ids( $old_quiz_id ) {
		$repository_ids = $this->extract_quiz_question_ids_from_repository( $old_quiz_id );
		if ( ! empty( $repository_ids ) ) {
			return $repository_ids;
		}

		$meta           = get_post_meta( $old_quiz_id );
		$candidate_keys = array(
			'questions',
			'_questions',
			'quiz_questions',
			'_quiz_questions',
			'stm_lms_questions',
			'_stm_lms_questions',
		);

		foreach ( $candidate_keys as $key ) {
			if ( empty( $meta[ $key ] ) ) {
				continue;
			}

			$value = maybe_unserialize( $meta[ $key ][0] );
			if ( is_string( $value ) ) {
				$json = json_decode( $value, true );
				if ( JSON_ERROR_NONE === json_last_error() && is_array( $json ) ) {
					$value = $json;
				}
			}

			if ( ! is_array( $value ) ) {
				if ( is_string( $value ) ) {
					$parts = preg_split( '/[\s,;|]+/', $value );
					$value = is_array( $parts ) ? array_filter( array_map( 'trim', $parts ) ) : array();
				} else {
					continue;
				}
			}

			$ids = array();
			foreach ( $value as $item ) {
				if ( is_numeric( $item ) ) {
					$ids[] = intval( $item );
				} elseif ( is_array( $item ) ) {
					if ( isset( $item['id'] ) ) {
						$ids[] = intval( $item['id'] );
					} elseif ( isset( $item['question_id'] ) ) {
						$ids[] = intval( $item['question_id'] );
					}
				} elseif ( is_object( $item ) ) {
					if ( isset( $item->id ) ) {
						$ids[] = intval( $item->id );
					} elseif ( isset( $item->question_id ) ) {
						$ids[] = intval( $item->question_id );
					}
				} elseif ( is_string( $item ) && false !== strpos( $item, '-' ) ) {
					$parts = explode( '-', $item );
					foreach ( $parts as $part ) {
						if ( is_numeric( $part ) ) {
							$ids[] = intval( $part );
						}
					}
				}
			}

			$ids = array_filter( array_unique( array_map( 'intval', $ids ) ) );
			if ( ! empty( $ids ) ) {
				return array_values( $ids );
			}
		}

		return array();
	}

	/**
	 * Extract source question ids via MasterStudy repository API when available.
	 *
	 * @param int $old_quiz_id Source quiz ID.
	 * @return array
	 */
	private function extract_quiz_question_ids_from_repository( $old_quiz_id ) {
		if ( ! class_exists( '\MasterStudy\Lms\Repositories\QuizRepository' ) ) {
			return array();
		}

		try {
			$quiz_repository = new \MasterStudy\Lms\Repositories\QuizRepository();
			$quiz_data       = $quiz_repository->get( intval( $old_quiz_id ) );
		} catch ( \Throwable $throwable ) {
			return array();
		}

		if ( empty( $quiz_data ) || ! isset( $quiz_data['questions'] ) ) {
			return array();
		}

		$questions = $quiz_data['questions'];
		if ( is_string( $questions ) ) {
			$questions = preg_split( '/[\s,;|]+/', $questions );
		}

		if ( ! is_array( $questions ) ) {
			return array();
		}

		$ids = array_filter( array_map( 'intval', $questions ) );
		if ( empty( $ids ) ) {
			return array();
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Is lesson type identifier.
	 *
	 * @param string $item_type Item type.
	 * @return bool
	 */
	private function is_lesson_type( $item_type ) {
		return in_array( $item_type, array( 'stm-lessons', 'lesson', 'lessons', 'stm_lesson' ), true );
	}

	/**
	 * Is quiz type identifier.
	 *
	 * @param string $item_type Item type.
	 * @return bool
	 */
	private function is_quiz_type( $item_type ) {
		return in_array( $item_type, array( 'stm-quizzes', 'quiz', 'quizzes', 'stm_quiz' ), true );
	}
}
