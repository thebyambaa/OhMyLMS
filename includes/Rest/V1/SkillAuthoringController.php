<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Revision-checked boundary for the authored skill editor.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Assessment\Schema;
use OhMyLMS\QuestionBank\AccessPolicy;
use OhMyLMS\Skills\Objectives;
use OhMyLMS\Skills\Provenance;
use OhMyLMS\Skills\Taxonomy;
use OhMyLMS\Skills\Translations;

defined( 'ABSPATH' ) || exit;

/** Reads and writes one shared skill's editable fields against the revision they were read at. */
class SkillAuthoringController extends \WP_REST_Controller {

	/** Register the bounded read and write endpoints. */
	public function register_routes() {
		register_rest_route(
			'ohmylms/v1',
			'/authoring/skills/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => array( $this, 'permission' ),
					'callback'            => array( $this, 'get_item' ),
				),
				array(
					'methods'             => 'PUT',
					'permission_callback' => array( $this, 'permission' ),
					'callback'            => array( $this, 'update_item' ),
				),
			)
		);
	}

	/**
	 * Skill authors (edit_posts) may edit; making practice public needs manage_options and is checked on write.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function permission( $request = null ) {
		if ( ! Schema::ready() ) {
			return new \WP_Error( 'ohmylms_skills_unavailable', __( 'Skills are not installed yet.', 'ohmylms' ), array( 'status' => 503 ) );
		}
		return AccessPolicy::check( AccessPolicy::can_author() );
	}

	/**
	 * Read the editor state without loading linked posts.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public function get_item( $request ) {
		$term = $this->term( (int) $request['id'] );
		return is_wp_error( $term ) ? $term : $this->snapshot( $term );
	}

	/**
	 * Body schema. Unknown fields, wrong types and oversized text are rejected before any write.
	 *
	 * @return array
	 */
	private function schema() {
		$text   = static function ( $max ) {
			return array(
				'type'      => 'string',
				'maxLength' => $max,
			);
		};
		$locale = array(
			'type'                 => 'object',
			'properties'           => array(
				'name'        => $text( 190 ),
				'description' => $text( 2000 ),
				'objective'   => $text( 2000 ),
			),
			'additionalProperties' => false,
		);
		return array(
			'type'                 => 'object',
			'required'             => array( 'expected_revision' ),
			'additionalProperties' => false,
			'properties'           => array(
				'expected_revision' => array(
					'type'      => 'string',
					'minLength' => 64,
					'maxLength' => 64,
				),
				'name'              => array(
					'type'      => 'string',
					'minLength' => 1,
					'maxLength' => 200,
				),
				'code'              => $text( 40 ),
				'description'       => $text( 10000 ),
				'objective'         => $text( 2000 ),
				'parent'            => array(
					'type'    => 'integer',
					'minimum' => 0,
				),
				'prerequisites'     => array(
					'type'        => 'array',
					'maxItems'    => 100,
					'uniqueItems' => true,
					'items'       => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
				'translations'      => array(
					'type'                 => 'object',
					'properties'           => array(
						'en' => $locale,
						'mn' => $locale,
					),
					'additionalProperties' => false,
				),
				'public_practice'   => array( 'type' => 'boolean' ),
			),
		);
	}

	/**
	 * Validate everything, then write under the skill's advisory lock if the revision still matches.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public function update_item( $request ) {
		$data = $request->get_json_params();
		if ( ! is_array( $data ) || strlen( (string) wp_json_encode( $data ) ) > 65536 ) {
			return $this->error( 'ohmylms_skill_invalid', __( 'Send a JSON skill update of at most 64 KB.', 'ohmylms' ) );
		}
		$valid = rest_validate_value_from_schema( $data, $this->schema(), 'skill' );
		if ( is_wp_error( $valid ) ) {
			return $this->error( 'ohmylms_skill_invalid', $valid->get_error_message() );
		}
		$id   = (int) $request['id'];
		$term = $this->term( $id );
		if ( is_wp_error( $term ) ) {
			return $term;
		}
		$changes = $this->validate( $term, $data );
		if ( is_wp_error( $changes ) ) {
			return $changes;
		}
		global $wpdb;
		$lock = 'ohmylms-skill-' . $id . '-' . md5( $wpdb->prefix );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Non-cacheable advisory lock.
			return $this->error( 'ohmylms_skill_busy', __( 'Another author is saving this skill. Try again.', 'ohmylms' ), 409 );
		}
		try {
			clean_term_cache( $id, Taxonomy::NAME );
			$term = $this->term( $id );
			if ( is_wp_error( $term ) ) {
				return $term;
			}
			if ( ! hash_equals( $this->revision( $term ), $data['expected_revision'] ) ) {
				return $this->error( 'ohmylms_skill_stale', __( 'This skill changed since you opened it. Reload it and apply your edits again.', 'ohmylms' ), 409 );
			}
			$written = $this->write( $id, $changes );
			if ( is_wp_error( $written ) ) {
				return $written;
			}
			clean_term_cache( $id, Taxonomy::NAME );
			return $this->snapshot( get_term( $id, Taxonomy::NAME ) );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Release the advisory lock.
		}
	}

	/**
	 * Domain validation; returns normalized changes or the first problem. Nothing is written here.
	 *
	 * @param \WP_Term $term Current skill.
	 * @param array    $data Schema-valid body.
	 * @return array|\WP_Error
	 */
	private function validate( \WP_Term $term, array $data ) {
		$id      = (int) $term->term_id;
		$changes = array();
		if ( isset( $data['name'] ) ) {
			$changes['name'] = sanitize_text_field( $data['name'] );
			if ( '' === trim( $changes['name'] ) ) {
				return $this->error( 'ohmylms_skill_invalid', __( 'A skill name is required.', 'ohmylms' ) );
			}
		}
		if ( isset( $data['code'] ) ) {
			$changes['code'] = sanitize_text_field( $data['code'] );
		}
		if ( isset( $data['description'] ) ) {
			$changes['description'] = sanitize_textarea_field( $data['description'] );
		}
		if ( isset( $data['parent'] ) ) {
			$parent = (int) $data['parent'];
			if ( $parent && ! term_exists( $parent, Taxonomy::NAME ) ) {
				return $this->error( 'ohmylms_skill_invalid', __( 'Choose an existing parent skill.', 'ohmylms' ) );
			}
			if ( $parent === $id || ( $parent && term_is_ancestor_of( $id, $parent, Taxonomy::NAME ) ) ) {
				return $this->error( 'ohmylms_skill_cycle', __( 'A skill cannot be nested under itself.', 'ohmylms' ) );
			}
			$changes['parent'] = $parent;
		}
		if ( isset( $data['prerequisites'] ) ) {
			$prerequisites = Taxonomy::validate_prerequisites( $id, $data['prerequisites'] );
			if ( is_wp_error( $prerequisites ) ) {
				return $prerequisites;
			}
			$changes['prerequisites'] = $prerequisites;
		}
		if ( isset( $data['objective'] ) ) {
			if ( '' === trim( $data['objective'] ) ) {
				$changes['objective'] = '';
			} else {
				$objective = Objectives::normalize( $data['objective'] );
				if ( is_wp_error( $objective ) ) {
					return $objective;
				}
				$changes['objective'] = $objective;
			}
		}
		if ( ! empty( $data['translations'] ) ) {
			$translations = Translations::normalize( $data['translations'] );
			if ( is_wp_error( $translations ) ) {
				return $translations;
			}
			$changes['translations'] = $translations;
		}
		if ( isset( $data['public_practice'] ) && $data['public_practice'] !== $this->public_practice( $id ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return AccessPolicy::denied( __( 'Only an administrator can make a practice pool public.', 'ohmylms' ) );
			}
			$changes['public_practice'] = $data['public_practice'];
		}
		return $changes;
	}

	/**
	 * Apply validated changes through the existing term and skill services.
	 *
	 * @param int   $id Skill ID.
	 * @param array $changes Validated changes.
	 * @return true|\WP_Error
	 */
	private function write( $id, array $changes ) {
		$args = array_intersect_key( $changes, array_flip( array( 'name', 'description', 'parent' ) ) );
		if ( $args ) {
			$updated = wp_update_term( $id, Taxonomy::NAME, $args );
			if ( is_wp_error( $updated ) ) {
				$updated->add_data( array( 'status' => 400 ) );
				return $updated;
			}
		}
		if ( isset( $changes['code'] ) ) {
			update_term_meta( $id, '_ohmylms_skill_code', $changes['code'] );
		}
		if ( isset( $changes['prerequisites'] ) ) {
			update_term_meta( $id, '_ohmylms_prerequisites', $changes['prerequisites'] );
		}
		if ( isset( $changes['objective'] ) ) {
			if ( '' === $changes['objective'] ) {
				delete_term_meta( $id, Objectives::META_KEY );
			} else {
				$saved = Objectives::save( $id, $changes['objective'] );
				if ( is_wp_error( $saved ) ) {
					return $saved;
				}
			}
		}
		if ( isset( $changes['translations'] ) ) {
			$saved = Translations::save( $id, $changes['translations'] );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}
		if ( isset( $changes['public_practice'] ) ) {
			update_term_meta( $id, '_ohmylms_public_practice', $changes['public_practice'] );
		}
		return true;
	}

	/**
	 * Load an existing skill term.
	 *
	 * @param int $id Skill ID.
	 * @return \WP_Term|\WP_Error
	 */
	private function term( $id ) {
		$term = get_term( $id, Taxonomy::NAME );
		return $term instanceof \WP_Term ? $term : new \WP_Error( 'ohmylms_skill_missing', __( 'Skill not found.', 'ohmylms' ), array( 'status' => 404 ) );
	}

	/**
	 * The stored editable state; the revision is its hash.
	 *
	 * @param \WP_Term $term Skill.
	 * @return array
	 */
	private function state( \WP_Term $term ) {
		$id        = (int) $term->term_id;
		$objective = get_term_meta( $id, Objectives::META_KEY, true );
		return array(
			'id'              => $id,
			'name'            => Taxonomy::plain( $term->name ),
			'code'            => (string) get_term_meta( $id, '_ohmylms_skill_code', true ),
			'description'     => Taxonomy::plain( $term->description ),
			'parent'          => (int) $term->parent,
			'prerequisites'   => Taxonomy::prerequisites( $id ),
			'objective'       => is_string( $objective ) ? Taxonomy::plain( $objective ) : '',
			'translations'    => (object) Translations::get( $id ),
			'public_practice' => $this->public_practice( $id ),
			'archived'        => Provenance::archived( $id ),
		);
	}

	/**
	 * Revision of the editable state.
	 *
	 * @param \WP_Term $term Skill.
	 * @return string
	 */
	private function revision( \WP_Term $term ) {
		return hash( 'sha256', (string) wp_json_encode( $this->state( $term ) ) );
	}

	/**
	 * Editor payload: state, revision, effective objective, resource counts and permissions.
	 *
	 * @param \WP_Term $term Skill.
	 * @return array
	 */
	private function snapshot( \WP_Term $term ) {
		$id = (int) $term->term_id;
		return array(
			'skill'       => $this->state( $term ) + array( 'effective_objective' => Objectives::get( $term ) ),
			'revision'    => $this->revision( $term ),
			'resources'   => array(
				'lessons'   => $this->count( $id, OHMYLMS_LESSON_CPT ),
				'courses'   => $this->count( $id, OHMYLMS_COURSE_CPT ),
				'questions' => $this->count( $id, OHMYLMS_QUESTION_CPT ),
			),
			'permissions' => array( 'public_practice' => current_user_can( 'manage_options' ) ),
		);
	}

	/**
	 * Count posts of one type linked directly to the skill, without loading them.
	 *
	 * @param int    $id Skill ID.
	 * @param string $post_type Post type.
	 * @return int
	 */
	private function count( $id, $post_type ) {
		$query = new \WP_Query(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => false,
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Indexed term relationship count.
					array(
						'taxonomy'         => Taxonomy::NAME,
						'terms'            => array( $id ),
						'include_children' => false,
					),
				),
			)
		);
		return (int) $query->found_posts;
	}

	/**
	 * Whether the skill's practice pool is public.
	 *
	 * @param int $id Skill ID.
	 * @return bool
	 */
	private function public_practice( $id ) {
		return (bool) get_term_meta( $id, '_ohmylms_public_practice', true );
	}

	/**
	 * REST error with a status.
	 *
	 * @param string $code Code.
	 * @param string $message Message.
	 * @param int    $status HTTP status.
	 * @return \WP_Error
	 */
	private function error( $code, $message, $status = 400 ) {
		return new \WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
