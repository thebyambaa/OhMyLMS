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
		$write_args = array(
			'name'            => array(
				'type'      => 'string',
				'minLength' => 1,
				'maxLength' => 200,
			),
			'code'            => array(
				'type'      => 'string',
				'maxLength' => 40,
			),
			'description'     => array(
				'type'      => 'string',
				'maxLength' => 10000,
			),
			'parent'          => array(
				'type'    => 'integer',
				'minimum' => 0,
			),
			'prerequisites'   => array(
				'type'     => 'array',
				'maxItems' => 100,
				'items'    => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
			),
			'public_practice' => array( 'type' => 'boolean' ),
		);
		register_rest_route(
			$this->namespace,
			'/skills/(?P<id>[\d]+)/relationships/(?P<kind>lessons|courses)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'relationship_targets' ),
				'permission_callback' => array( $this, 'author_permission' ),
				'args'                => array(
					'page'        => array(
						'type'    => 'integer',
						'minimum' => 1,
						'default' => 1,
					),
					'search'      => array(
						'type'      => 'string',
						'maxLength' => 200,
						'default'   => '',
					),
					'linked_only' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/skills/(?P<id>[\d]+)/relationships/(?P<kind>lessons|courses)/(?P<post_id>[\d]+)',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'change_relationship' ),
				'permission_callback' => array( $this, 'author_permission' ),
				'args'                => array(
					'linked' => array(
						'type'     => 'boolean',
						'required' => true,
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/skills/catalog',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'catalog' ),
				'permission_callback' => array( $this, 'author_permission' ),
				'args'                => array(
					'page'     => array(
						'type'    => 'integer',
						'minimum' => 1,
						'default' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 50,
						'default' => 20,
					),
					'search'   => array(
						'type'      => 'string',
						'maxLength' => 200,
						'default'   => '',
					),
					'include'  => array(
						'type'     => 'array',
						'items'    => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'maxItems' => 50,
						'default'  => array(),
					),
				),
			)
		);
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
					'args'                => $write_args,
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
					'args'                => $write_args,
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
	 * Search editable posts, optionally limited to this skill's current links.
	 *
	 * @param WP_REST_Request $request Skill, post kind and bounded page.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function relationship_targets( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( ! term_exists( $id, Taxonomy::NAME ) ) {
			return new WP_Error( 'ohmylms_skill_missing', __( 'Skill not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$post_type = 'courses' === $request['kind'] ? OHMYLMS_COURSE_CPT : OHMYLMS_LESSON_CPT;
		$args      = array(
			'post_type'      => $post_type,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => 20,
			'paged'          => $request['page'],
			's'              => (string) $request['search'],
			'orderby'        => array(
				'title' => 'ASC',
				'ID'    => 'ASC',
			),
		);
		$type      = get_post_type_object( $post_type );
		if ( ! $type || ! current_user_can( $type->cap->edit_others_posts ) ) {
			$args['author'] = get_current_user_id();
		}
		if ( $request['linked_only'] ) {
			$args['tax_query'] = array(
				array(
					'taxonomy'         => Taxonomy::NAME,
					'terms'            => array( $id ),
					'include_children' => false,
				),
			);
		}
		$query = new \WP_Query( $args );
		$rows  = array();
		foreach ( $query->posts as $post ) {
			if ( current_user_can( 'edit_post', $post->ID ) ) {
				$rows[] = array(
					'id'     => (int) $post->ID,
					'title'  => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
					'linked' => has_term( $id, Taxonomy::NAME, $post->ID ),
				);
			}
		}
		$response = rest_ensure_response( $rows );
		$response->header( 'X-WP-Total', (int) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (int) $query->max_num_pages );
		return $response;
	}

	/**
	 * Change one relationship without replacing unseen links.
	 *
	 * @param WP_REST_Request $request Skill, post and desired linked state.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function change_relationship( WP_REST_Request $request ) {
		$id      = (int) $request['id'];
		$post_id = (int) $request['post_id'];
		if ( ! term_exists( $id, Taxonomy::NAME ) ) {
			return new WP_Error( 'ohmylms_skill_missing', __( 'Skill not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$post_type = 'courses' === $request['kind'] ? OHMYLMS_COURSE_CPT : OHMYLMS_LESSON_CPT;
		if ( get_post_type( $post_id ) !== $post_type || ! current_user_can( 'edit_post', $post_id ) ) {
			return AccessPolicy::denied( __( 'You cannot change links for this post.', 'ohmylms' ) );
		}
		$result = $request['linked'] ? wp_add_object_terms( $post_id, $id, Taxonomy::NAME ) : wp_remove_object_terms( $post_id, $id, Taxonomy::NAME );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response(
			array(
				'id'     => $post_id,
				'linked' => has_term( $id, Taxonomy::NAME, $post_id ),
			)
		);
	}

	/**
	 * Bounded summaries without loading every skill's linked posts.
	 *
	 * @param WP_REST_Request $request Pagination, search and optional identifiers.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function catalog( WP_REST_Request $request ) {
		$args  = array(
			'taxonomy'     => Taxonomy::NAME,
			'hide_empty'   => false,
			'hierarchical' => false,
			'orderby'      => 'name',
			'order'        => 'ASC',
			'search'       => (string) $request['search'],
			'include'      => $request['include'],
		);
		$total = wp_count_terms( $args );
		if ( is_wp_error( $total ) ) {
			return $total;
		}
		$pages = (int) ceil( (int) $total / $request['per_page'] );
		if ( 1 < $request['page'] && $request['page'] > $pages ) {
			return new WP_Error( 'ohmylms_skill_invalid_page', __( 'This skill page does not exist.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$args['number'] = $request['per_page'];
		$args['offset'] = ( $request['page'] - 1 ) * $request['per_page'];
		$terms          = get_terms( $args );
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}
		$rows = array();
		foreach ( $terms as $term ) {
			$rows[] = array(
				'id'            => (int) $term->term_id,
				'name'          => Taxonomy::plain( $term->name ),
				'code'          => (string) get_term_meta( $term->term_id, '_ohmylms_skill_code', true ),
				'description'   => Taxonomy::plain( $term->description ),
				'parent'        => (int) $term->parent,
				'prerequisites' => Taxonomy::prerequisites( $term->term_id ),
				'archived'      => \OhMyLMS\Skills\Provenance::archived( $term->term_id ),
			);
		}
		$response = rest_ensure_response( $rows );
		$response->header( 'X-WP-Total', (int) $total );
		$response->header( 'X-WP-TotalPages', $pages );
		return $response;
	}

	/**
	 * Reject invalid edits before writing any metadata or term fields.
	 *
	 * @param int             $id Existing identifier, or zero for creation.
	 * @param WP_REST_Request $request Requested fields.
	 * @return true|WP_Error
	 */
	private function validate_edit( $id, WP_REST_Request $request ) {
		foreach ( array( 'name', 'code', 'description' ) as $field ) {
			if ( $request->has_param( $field ) && ! is_string( $request[ $field ] ) ) {
				return new WP_Error( 'ohmylms_skill_invalid', __( 'Skill text fields must be strings.', 'ohmylms' ), array( 'status' => 400 ) );
			}
		}
		if ( $request->has_param( 'name' ) && '' === trim( sanitize_text_field( $request['name'] ) ) ) {
			return new WP_Error( 'ohmylms_skill_invalid', __( 'A skill name is required.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		if ( isset( $request['parent'] ) ) {
			$parent = filter_var( $request['parent'], FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) );
			if ( false === $parent || ( $parent && ! term_exists( $parent, Taxonomy::NAME ) ) ) {
				return new WP_Error( 'ohmylms_skill_invalid', __( 'Choose an existing parent skill.', 'ohmylms' ), array( 'status' => 400 ) );
			}
			if ( $id && ( $parent === $id || ( $parent && term_is_ancestor_of( $id, $parent, Taxonomy::NAME ) ) ) ) {
				return new WP_Error( 'ohmylms_skill_cycle', __( 'A skill cannot be nested under itself.', 'ohmylms' ), array( 'status' => 400 ) );
			}
		}
		if ( isset( $request['public_practice'] ) && (bool) get_term_meta( $id, '_ohmylms_public_practice', true ) !== rest_sanitize_boolean( $request['public_practice'] ) && ! current_user_can( 'manage_options' ) ) {
			return AccessPolicy::denied( __( 'Only an administrator can make a practice pool public.', 'ohmylms' ) );
		}
		foreach ( array(
			'objective'    => 'Objectives',
			'translations' => 'Translations',
		) as $field => $class_name ) {
			if ( isset( $request[ $field ] ) ) {
				$class  = '\\OhMyLMS\\Skills\\' . $class_name;
				$result = $class::normalize( $request[ $field ] );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}
		}
		if ( isset( $request['prerequisites'] ) ) {
			if ( ! is_array( $request['prerequisites'] ) || 100 < count( $request['prerequisites'] ) ) {
				return new WP_Error( 'ohmylms_skill_invalid', __( 'Supply at most 100 prerequisite identifiers.', 'ohmylms' ), array( 'status' => 400 ) );
			}
			foreach ( $request['prerequisites'] as $prerequisite ) {
				if ( false === filter_var( $prerequisite, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ) ) {
					return new WP_Error( 'ohmylms_skill_invalid', __( 'Invalid prerequisite identifier.', 'ohmylms' ), array( 'status' => 400 ) );
				}
			}
			$result = Taxonomy::validate_prerequisites( $id, $request['prerequisites'] );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		return true;
	}

	/**
	 * Create for Skill controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function create( WP_REST_Request $request ) {
		$valid = $this->validate_edit( 0, $request );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
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
		$valid = $this->validate_edit( $id, $request );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
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
		if ( $args ) {
			$updated = wp_update_term( $id, Taxonomy::NAME, $args );
			if ( is_wp_error( $updated ) ) {
				$updated->add_data( array( 'status' => 400 ) );
				return $updated; }
		}
		$result = $this->apply_meta( $id, $request );
		if ( is_wp_error( $result ) ) {
			return $result;
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
						'title'             => ( '' !== get_the_title( $post ) ? get_the_title( $post ) : '#' . $post->ID ),
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
