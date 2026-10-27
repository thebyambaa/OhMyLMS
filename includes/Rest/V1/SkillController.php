<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Learning-skill catalogue: skills, prerequisites (acyclic), lesson links and
 * question skill maps. Profile "skills" on student accounts are unrelated.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\QuestionBank\AccessPolicy;
use OhMyLMS\QuestionBank\DraftWriter;
use OhMyLMS\QuestionBank\SkillMap;
use OhMyLMS\Skills\Taxonomy;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Learning-skill catalogue: skills, prerequisites (acyclic), lesson links and
 * question skill maps. Profile "skills" on student accounts are unrelated.
 */
class SkillController extends RestController {
	/**
	 * Register routes for Skill controller.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/skills/(?P<id>[\d]+)/translate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'translate' ),
				'permission_callback' => array( $this, 'author_permission' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/skills',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( $this, 'author_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create' ),
					'permission_callback' => array( $this, 'author_permission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/skills/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'show' ),
					'permission_callback' => array( $this, 'author_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update' ),
					'permission_callback' => array( $this, 'author_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete' ),
					'permission_callback' => array( $this, 'admin_permission' ),
				),
			)
		);
		foreach ( array(
			'review'  => 'review',
			'archive' => 'archive',
			'restore' => 'restore',
		) as $action => $callback ) {
			register_rest_route(
				$this->namespace,
				'/skills/(?P<id>[\d]+)/' . $action,
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, $callback ),
					'permission_callback' => array( $this, 'admin_permission' ),
				)
			);
		}
		register_rest_route(
			$this->namespace,
			'/skill-sources',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => static function () {
					return rest_ensure_response( \OhMyLMS\Skills\SkillSources::describe() );
				},
				'permission_callback' => array( $this, 'author_permission' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/skills/(?P<id>[\d]+)/(?P<kind>lessons|courses)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'link_posts' ),
					'permission_callback' => array( $this, 'author_permission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/skills/link-targets',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'link_targets' ),
					'permission_callback' => array( $this, 'author_permission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/question/(?P<id>[\d]+)/skills',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'question_skills' ),
					'permission_callback' => array( $this, 'question_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_question_skills' ),
					'permission_callback' => array( $this, 'question_permission' ),
				),
			)
		);
	}

	/**
	 * Return a draft using the configured provider, without writing skill data.
	 *
	 * @param WP_REST_Request $request Translation source and target locale.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function translate( WP_REST_Request $request ) {
		if ( ! term_exists( (int) $request['id'], Taxonomy::NAME ) ) {
			return new WP_Error( 'ohmylms_skill_missing', __( 'Skill not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( \OhMyLMS\Skills\Translations::preview( $request['locale'], $request['source'] ) );
	}

	/**
	 * Author permission for Skill controller.
	 *
	 * @param mixed $request REST request.
	 */
	public function author_permission( $request = null ) {
		if ( ! Schema::ready() ) {
			return new WP_Error( 'ohmylms_skills_unavailable', __( 'Skills are not installed yet.', 'ohmylms' ), array( 'status' => 503 ) ); }
		return AccessPolicy::check( AccessPolicy::can_author() );
	}
	/**
	 * Admin permission for Skill controller.
	 */
	public function admin_permission() {
		return AccessPolicy::check( current_user_can( 'manage_options' ) );
	}
	/**
	 * Question permission for Skill controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function question_permission( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( get_post_type( $id ) !== OHMYLMS_QUESTION_CPT ) {
			return new WP_Error( 'ohmylms_rest_invalid_question_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) ); }
		return AccessPolicy::check( $request->get_method() === 'GET' ? AccessPolicy::can_use_question( $id ) : AccessPolicy::can_edit_question( $id ) );
	}

	/**
	 * Index for Skill controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function index( WP_REST_Request $request ) {
		$terms  = get_terms(
			array(
				'taxonomy'   => Taxonomy::NAME,
				'hide_empty' => false,
				'orderby'    => 'name',
				'search'     => (string) $request['search'],
			)
		);
		$skills = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$skill              = Taxonomy::describe( $term );
			$skill['questions'] = (int) $term->count;
			$skills[]           = $skill;
		}
		return rest_ensure_response( array( 'skills' => $skills ) );
	}

	/**
	 * Show for Skill controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function show( WP_REST_Request $request ) {
		$skill = Taxonomy::describe( (int) $request['id'] );
		return $skill ? rest_ensure_response( $skill ) : new WP_Error( 'ohmylms_skill_missing', __( 'Skill not found.', 'ohmylms' ), array( 'status' => 404 ) );
	}

	/**
	 * Create for Skill controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function create( WP_REST_Request $request ) {
		$name = sanitize_text_field( (string) $request['name'] );
		if ( '' === $name ) {
			return new WP_Error( 'ohmylms_skill_invalid', __( 'A skill name is required.', 'ohmylms' ), array( 'status' => 400 ) ); }
		$parent = (int) $request['parent'];
		if ( $parent && ! term_exists( $parent, Taxonomy::NAME ) ) {
			return new WP_Error( 'ohmylms_skill_missing', __( 'Parent skill not found.', 'ohmylms' ), array( 'status' => 400 ) ); }
		$term = wp_insert_term(
			$name,
			Taxonomy::NAME,
			array(
				'description' => sanitize_textarea_field( (string) $request['description'] ),
				'parent'      => $parent,
			)
		);
		if ( is_wp_error( $term ) ) {
			$term->add_data( array( 'status' => 400 ) );
			return $term; }
		$id = (int) $term['term_id'];
		Taxonomy::uuid( $id );
		$result = $this->apply_meta( $id, $request );
		if ( is_wp_error( $result ) ) {
			wp_delete_term( $id, Taxonomy::NAME );
			return $result; }
		$response = rest_ensure_response( Taxonomy::describe( $id ) );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * Update for Skill controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function update( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( ! term_exists( $id, Taxonomy::NAME ) ) {
			return new WP_Error( 'ohmylms_skill_missing', __( 'Skill not found.', 'ohmylms' ), array( 'status' => 404 ) ); }
		$args = array();
		if ( isset( $request['name'] ) ) {
			$args['name'] = sanitize_text_field( (string) $request['name'] ); }
		if ( isset( $request['description'] ) ) {
			$args['description'] = sanitize_textarea_field( (string) $request['description'] ); }
		if ( isset( $request['parent'] ) ) {
			$parent = (int) $request['parent'];
			if ( $parent === $id || ( $parent && term_is_ancestor_of( $id, $parent, Taxonomy::NAME ) ) ) {
				return new WP_Error( 'ohmylms_skill_cycle', __( 'A skill cannot be nested under itself.', 'ohmylms' ), array( 'status' => 400 ) ); }
			$args['parent'] = $parent;
		}
		$result = $this->apply_meta( $id, $request );
		if ( is_wp_error( $result ) ) {
			return $result; }
		if ( $args ) {
			$updated = wp_update_term( $id, Taxonomy::NAME, $args );
			if ( is_wp_error( $updated ) ) {
				$updated->add_data( array( 'status' => 400 ) );
				return $updated; }
		}
		return rest_ensure_response( Taxonomy::describe( $id ) );
	}

	/**
	 * Apply meta for Skill controller.
	 *
	 * @param mixed           $id Id.
	 * @param WP_REST_Request $request REST request.
	 */
	private function apply_meta( $id, WP_REST_Request $request ) {
		if ( isset( $request['objective'] ) ) {
			$objective = \OhMyLMS\Skills\Objectives::normalize( $request['objective'] );
			if ( is_wp_error( $objective ) ) {
				return $objective;
			}
			$saved = \OhMyLMS\Skills\Objectives::save( $id, $objective );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}
		if ( isset( $request['translations'] ) ) {
			$translations = \OhMyLMS\Skills\Translations::normalize( $request['translations'] );
			if ( is_wp_error( $translations ) ) {
				return $translations;
			}
			$saved = \OhMyLMS\Skills\Translations::save( $id, $translations );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}
		if ( isset( $request['public_practice'] ) && (bool) get_term_meta( $id, '_ohmylms_public_practice', true ) !== rest_sanitize_boolean( $request['public_practice'] ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return AccessPolicy::denied( __( 'Only an administrator can make a practice pool public.', 'ohmylms' ) ); }
			update_term_meta( $id, '_ohmylms_public_practice', rest_sanitize_boolean( $request['public_practice'] ) );
		}
		if ( isset( $request['code'] ) ) {
			update_term_meta( $id, '_ohmylms_skill_code', mb_substr( sanitize_text_field( (string) $request['code'] ), 0, 40 ) ); }
		if ( isset( $request['prerequisites'] ) ) {
			$result = Taxonomy::set_prerequisites( $id, (array) $request['prerequisites'] );
			if ( is_wp_error( $result ) ) {
				return $result; }
		}
		return true;
	}

	/** Skills with recorded evidence are kept; a skill in use can only be removed by an administrator with force.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function delete( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( ! term_exists( $id, Taxonomy::NAME ) ) {
			return new WP_Error( 'ohmylms_skill_missing', __( 'Skill not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		// A skill a course outline uses is archived, never deleted, so courses keep working. Learner
		// evidence and frozen question mappings archive it too, unless force asks for a real delete.
		$usage = \OhMyLMS\Skills\SkillUsage::of( $id );
		if ( \OhMyLMS\Skills\SkillUsage::in_courses( $usage ) || ( \OhMyLMS\Skills\SkillUsage::used( $usage ) && ! rest_sanitize_boolean( $request['force'] ) ) ) {
			return rest_ensure_response( \OhMyLMS\Skills\SkillWriter::archive( $id ) );
		}
		foreach ( get_terms(
			array(
				'taxonomy'   => Taxonomy::NAME,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		) as $other ) {
			if ( in_array( $id, Taxonomy::prerequisites( $other ), true ) ) {
				update_term_meta( $other, '_ohmylms_prerequisites', array_values( array_diff( Taxonomy::prerequisites( $other ), array( $id ) ) ) ); }
		}
		$deleted = wp_delete_term( $id, Taxonomy::NAME );
		return is_wp_error( $deleted ) ? $deleted : rest_ensure_response(
			array(
				'id'      => $id,
				'deleted' => true,
			)
		);
	}

	/**
	 * Approve or reject a skill proposed by a skill source.
	 *
	 * @param WP_REST_Request $request id and review (approved or rejected).
	 * @return mixed
	 */
	public function review( WP_REST_Request $request ) {
		return rest_ensure_response( \OhMyLMS\Skills\Provenance::review( (int) $request['id'], (string) $request['review'] ) );
	}

	/**
	 * Archive a skill: it stays for every course and record that uses it, but is not offered for new use.
	 *
	 * @param WP_REST_Request $request id.
	 * @return mixed
	 */
	public function archive( WP_REST_Request $request ) {
		return rest_ensure_response( \OhMyLMS\Skills\SkillWriter::archive( (int) $request['id'] ) );
	}

	/**
	 * Return an archived skill to normal use.
	 *
	 * @param WP_REST_Request $request id.
	 * @return mixed
	 */
	public function restore( WP_REST_Request $request ) {
		return rest_ensure_response( \OhMyLMS\Skills\SkillWriter::restore( (int) $request['id'] ) );
	}

	/** Replace the set of lessons or courses linked to a skill (only posts the user may edit change).
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function link_posts( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( ! term_exists( $id, Taxonomy::NAME ) ) {
			return new WP_Error( 'ohmylms_skill_missing', __( 'Skill not found.', 'ohmylms' ), array( 'status' => 404 ) ); }
		$courses   = 'courses' === $request['kind'];
		$post_type = $courses ? OHMYLMS_COURSE_CPT : OHMYLMS_LESSON_CPT;
		$wanted    = array_values( array_unique( array_map( 'intval', (array) $request[ $courses ? 'course_ids' : 'lesson_ids' ] ) ) );
		foreach ( $wanted as $post_id ) {
			if ( get_post_type( $post_id ) !== $post_type || ! current_user_can( 'edit_post', $post_id ) ) {
				return AccessPolicy::denied( $courses ? __( 'You cannot link one of these courses.', 'ohmylms' ) : __( 'You cannot link one of these lessons.', 'ohmylms' ) );
			}
		}
		foreach ( Taxonomy::linked_posts( $id, $post_type ) as $post_id ) {
			if ( ! in_array( $post_id, $wanted, true ) && current_user_can( 'edit_post', $post_id ) ) {
				wp_remove_object_terms( $post_id, $id, Taxonomy::NAME ); }
		}
		foreach ( $wanted as $post_id ) {
			wp_add_object_terms( $post_id, $id, Taxonomy::NAME ); }
		return rest_ensure_response( Taxonomy::describe( $id ) );
	}

	/** Lessons or courses the author may link: search results, or titles for known IDs.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function link_targets( WP_REST_Request $request ) {
		$post_type = 'course' === $request['type'] ? OHMYLMS_COURSE_CPT : OHMYLMS_LESSON_CPT;
		$ids       = array_filter( array_map( 'intval', explode( ',', (string) $request['include'] ) ) );
		$args      = array(
			'post_type'   => $post_type,
			'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'numberposts' => 20,
			'orderby'     => 'title',
			'order'       => 'ASC',
		);
		if ( $ids ) {
			$args['post__in']    = $ids;
			$args['numberposts'] = count( $ids ); } else {
			$args['s'] = sanitize_text_field( (string) $request['search'] ); }
			$rows = array();
			foreach ( get_posts( $args ) as $post ) {
				if ( current_user_can( 'edit_post', $post->ID ) ) {
					$rows[] = array(
						'id'                => (int) $post->ID,
						'title'             => get_the_title( $post ) ?: '#' . $post->ID,
						'native_editor_url' => \OhMyLMS\Learning\NativeLessonEditor::edit_url( $post->ID ),
					); }
			}
			return rest_ensure_response( $rows );
	}

	/**
	 * Question skills for Skill controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function question_skills( WP_REST_Request $request ) {
		return rest_ensure_response( array( 'skill_map' => SkillMap::current( (int) $request['id'] ) ) );
	}

	/** Save the question's part-level skill map through the versioning writer.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function save_question_skills( WP_REST_Request $request ) {
		$saved = DraftWriter::save(
			array(
				'id'     => (int) $request['id'],
				'skills' => (array) $request['skill_map'],
			)
		);
		return is_wp_error( $saved ) ? $saved : rest_ensure_response(
			array(
				'skill_map'  => SkillMap::current( (int) $request['id'] ),
				'version_id' => $saved['version_id'],
			)
		);
	}
}
