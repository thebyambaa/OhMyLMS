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
use OhMyLMS\Skills\SkillEditor;
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
		$term = SkillEditor::term( (int) $request['id'] );
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
		$term = SkillEditor::term( $id );
		if ( is_wp_error( $term ) ) {
			return $term;
		}
		$changes = $this->validate( $term, $data );
		if ( is_wp_error( $changes ) ) {
			return $changes;
		}
		return SkillEditor::update(
			$id,
			$changes,
			$data['expected_revision'],
			function ( $saved ) {
				return $this->snapshot( $saved );
			}
		);
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
		if ( isset( $data['public_practice'] ) && SkillEditor::public_practice( $id ) !== $data['public_practice'] ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return AccessPolicy::denied( __( 'Only an administrator can make a practice pool public.', 'ohmylms' ) );
			}
			$changes['public_practice'] = $data['public_practice'];
		}
		return $changes;
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
			'skill'       => SkillEditor::state( $term ) + array( 'effective_objective' => Objectives::get( $term ) ),
			'revision'    => SkillEditor::revision( $term ),
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
