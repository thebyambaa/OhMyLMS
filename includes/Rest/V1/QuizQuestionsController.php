<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Controller for the questions placed in a quiz.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Assessment\AssessmentSettings;
use OhMyLMS\Assessment\ErrorException;
use OhMyLMS\Assessment\Pools;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Extensions\Registry;
use OhMyLMS\QuestionBank\AccessPolicy;
use OhMyLMS\QuestionBank\BankAttributes;
use OhMyLMS\QuestionBank\Banks;
use OhMyLMS\QuestionBank\DraftWriter;
use OhMyLMS\QuestionBank\Search;
use OhMyLMS\QuestionBank\Usage;
use OhMyLMS\QuestionBank\VersionPublisher;
use OhMyLMS\Skills\Taxonomy;
use OhMyLMS\Utility\Transaction;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Questions of a quiz under `ohmylms/v1/quizzes/{id}/questions` (see docs/api/quizzes.md).
 *
 * Every route needs `edit_post` on the quiz; adding a bank question also needs `use` on it, and a
 * question the user may only use must have an approved version. Writes carry the `revision` of the
 * list the editor loaded and answer 409 when the stored list differs. Each write locks the quiz row
 * and runs in one transaction, so a rejected request changes nothing.
 *
 * @since 1.0.0
 */
class QuizQuestionsController extends RestController {

	/**
	 * Quiz meta holding the pinned version of each question.
	 */
	const PINS_META = '_ohmylms_version_pins';

	/**
	 * Largest number of questions in one quiz.
	 */
	const MAX_QUESTIONS = 500;

	/**
	 * Largest number of bank questions added in one request.
	 */
	const MAX_ADD = 50;

	/**
	 * Largest accepted request body, in bytes.
	 */
	const MAX_BODY_BYTES = 262144;

	/**
	 * Most marks one question or pool question can be worth in a quiz.
	 */
	const MAX_MARKS = 10000;

	/**
	 * Most sections per quiz.
	 */
	const MAX_SECTIONS = 50;

	/**
	 * Most random pools per quiz.
	 */
	const MAX_POOLS = 20;

	/**
	 * Quiz meta of the legacy settings blob, which holds `slot_marks`.
	 */
	const SETTINGS_META = '_quiz_settings';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'quizzes';

	/**
	 * Register the routes.
	 */
	public function register_routes() {
		add_filter( 'rest_pre_dispatch', array( $this, 'limit_body_size' ), 10, 3 );

		$base     = '/' . $this->rest_base . '/(?P<id>[\d]+)/questions';
		$slot     = $base . '/(?P<question_id>[\d]+)';
		$id       = array(
			'description' => __( 'Unique identifier for the quiz.', 'ohmylms' ),
			'type'        => 'integer',
			'minimum'     => 1,
		);
		$question = array(
			'description' => __( 'Unique identifier for the question.', 'ohmylms' ),
			'type'        => 'integer',
			'minimum'     => 1,
		);
		$revision = array(
			'description' => __( 'The revision of the question list the editor loaded; a different stored list answers 409.', 'ohmylms' ),
			'type'        => 'string',
			'required'    => true,
			'pattern'     => '^[0-9a-f]{16}$',
		);
		$ids      = static function ( $max ) {
			return array(
				'type'        => 'array',
				'required'    => true,
				'minItems'    => 1,
				'maxItems'    => $max,
				'uniqueItems' => true,
				'items'       => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
			);
		};

		register_rest_route(
			$this->namespace,
			$base,
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'question_ids' => $ids( self::MAX_ADD ),
						'pin'          => array(
							'description' => __( 'Pin each added question to the version the quiz would use now.', 'ohmylms' ),
							'type'        => 'boolean',
							'default'     => false,
						),
						'revision'     => $revision,
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$base . '/new',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'question' => array(
							'description' => __( 'The new question, exactly as POST /questions accepts it.', 'ohmylms' ),
							'type'        => 'object',
							'required'    => true,
						),
						'revision' => $revision,
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$base . '/order',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'reorder_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'question_ids' => $ids( self::MAX_QUESTIONS ),
						'revision'     => $revision,
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$slot,
			array(
				'args' => array(
					'id'          => $id,
					'question_id' => $question,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array( 'revision' => $revision ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$slot . '/pin',
			array(
				'args' => array(
					'id'          => $id,
					'question_id' => $question,
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'pin_item' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'version_id' => array(
							'description' => __( 'Version to pin, or null to unpin.', 'ohmylms' ),
							'type'        => array( 'integer', 'null' ),
							'minimum'     => 1,
						),
						'current'    => array(
							'description' => __( 'Pin the version the quiz would use now.', 'ohmylms' ),
							'type'        => 'boolean',
							'default'     => false,
						),
						'revision'   => $revision,
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$slot . '/marks',
			array(
				'args' => array(
					'id'          => $id,
					'question_id' => $question,
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'marks_item' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'marks'    => array(
							'description' => __( 'Marks of the question in this quiz, or null to use the points of the question.', 'ohmylms' ),
							'type'        => array( 'number', 'null' ),
							'minimum'     => 0,
							'maximum'     => self::MAX_MARKS,
							'required'    => true,
						),
						'revision' => $revision,
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$base . '/sections',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'sections_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'sections' => array(
							'description' => __( 'Every section in paper order; replaces the sections of the quiz.', 'ohmylms' ),
							'type'        => 'array',
							'required'    => true,
							'maxItems'    => self::MAX_SECTIONS,
							'items'       => array(
								'type'                 => 'object',
								'additionalProperties' => false,
								'properties'           => array(
									'title'        => array(
										'type'      => 'string',
										'maxLength' => 190,
										'required'  => true,
									),
									'question_ids' => array(
										'type'        => 'array',
										'maxItems'    => self::MAX_QUESTIONS,
										'uniqueItems' => true,
										'items'       => array(
											'type'    => 'integer',
											'minimum' => 1,
										),
										'required'    => true,
									),
									'new_page'     => array( 'type' => 'boolean' ),
								),
							),
						),
						'revision' => $revision,
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$base . '/pools',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'pools_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'pools'    => array(
							'description' => __( 'Every random pool of the quiz; replaces its pools.', 'ohmylms' ),
							'type'        => 'array',
							'required'    => true,
							'maxItems'    => self::MAX_POOLS,
							'items'       => array(
								'type'                 => 'object',
								'additionalProperties' => false,
								'properties'           => array(
									'title'          => array(
										'type'      => 'string',
										'maxLength' => 190,
									),
									'skill_id'       => array(
										'type'     => 'integer',
										'minimum'  => 1,
										'required' => true,
									),
									'count'          => array(
										'type'     => 'integer',
										'minimum'  => 1,
										'maximum'  => 50,
										'required' => true,
									),
									'marks'          => array(
										'type'    => 'number',
										'minimum' => 0,
										'maximum' => self::MAX_MARKS,
									),
									'difficulty'     => array(
										'type' => 'string',
										'enum' => array_merge( array( '' ), BankAttributes::DIFFICULTIES ),
									),
									'bank_id'        => array(
										'type'    => 'integer',
										'minimum' => 0,
									),
									'include_secure' => array( 'type' => 'boolean' ),
								),
							),
						),
						'revision' => $revision,
					),
				),
			)
		);
	}

	/**
	 * Refuse oversized bodies on these routes before they are validated or dispatched.
	 *
	 * @param mixed           $result  Response replacing the dispatch, or null.
	 * @param WP_REST_Server  $server  Server.
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function limit_body_size( $result, $server, $request ) {
		if ( null !== $result || ! preg_match( '#^/' . preg_quote( $this->namespace . '/' . $this->rest_base, '#' ) . '/\d+/questions(/|$)#', (string) $request->get_route() ) ) {
			return $result;
		}
		if ( strlen( (string) $request->get_body() ) > self::MAX_BODY_BYTES ) {
			return new WP_Error(
				'ohmylms_quiz_questions_too_large',
				__( 'The request is too large.', 'ohmylms' ),
				array(
					'status'    => 413,
					'max_bytes' => self::MAX_BODY_BYTES,
				)
			);
		}

		return $result;
	}

	// Permissions.

	/**
	 * Permission for every route: the question bank is installed and the user may edit the quiz.
	 * An unknown id answers 404 before the capability check.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! Schema::ready() ) {
			return new WP_Error( 'ohmylms_question_bank_unavailable', __( 'The question bank is not installed yet.', 'ohmylms' ), array( 'status' => 503 ) );
		}
		$post = get_post( absint( $request['id'] ) );
		if ( ! $post || OHMYLMS_QUIZ_CPT !== $post->post_type || 'auto-draft' === $post->post_status ) {
			return new WP_Error( 'ohmylms_quiz_not_found', __( 'Quiz not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'ohmylms_quiz_cannot_edit', __( 'Sorry, you are not allowed to edit this quiz.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	// Routes.

	/**
	 * Read the questions of a quiz in order, with their marks, versions and pins, and its random pools.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		return $this->respond( (int) $request['id'], $request );
	}

	/**
	 * Place existing bank questions at the end of the quiz, by reference.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function add_items( $request ) {
		$quiz_id = (int) $request['id'];
		$add     = array_map( 'intval', (array) $request['question_ids'] );
		$pin     = rest_sanitize_boolean( $request['pin'] );

		return $this->write(
			$request,
			array( 'question_ids', 'pin', 'revision' ),
			'add',
			function ( array $ids, array $pins ) use ( $quiz_id, $add, $pin ) {
				foreach ( $add as $question_id ) {
					if ( in_array( $question_id, $ids, true ) ) {
						ErrorException::raise(
							new WP_Error(
								'ohmylms_quiz_question_exists',
								__( 'This question is already in the quiz.', 'ohmylms' ),
								array(
									'status'      => 409,
									'question_id' => $question_id,
								)
							)
						);
					}
					ErrorException::raise( $this->check_usable( $question_id ) );
				}
				ErrorException::raise( $this->check_limit( count( $ids ) + count( $add ) ) );
				foreach ( $add as $question_id ) {
					DraftWriter::link( $quiz_id, $question_id );
					if ( $pin ) {
						$version = $this->unpinned_version( $quiz_id, $question_id );
						if ( is_wp_error( $version ) ) {
							ErrorException::raise( $version );
						}
						$pins[ $question_id ] = (int) $version['id'];
					}
				}
				if ( $pin ) {
					$this->save_pins( $quiz_id, $pins );
				}
			}
		);
	}

	/**
	 * Create a question with the body and validation of POST /questions and place it at the end of the
	 * quiz, in one transaction: a failed placement removes the question again.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$quiz_id     = (int) $request['id'];
		$body        = $request['question'];
		$question_id = 0;

		$response = $this->write(
			$request,
			array( 'question', 'revision' ),
			'create',
			function ( array $ids ) use ( $quiz_id, $body ) {
				ErrorException::raise( $this->check_limit( count( $ids ) + 1 ) );
				$create = new WP_REST_Request( 'POST', '/' . $this->namespace . '/questions' );
				$create->set_header( 'Content-Type', 'application/json' );
				$create->set_body( (string) wp_json_encode( is_array( $body ) && $body ? $body : new \stdClass() ) );
				$created = rest_do_request( $create );
				if ( $created->is_error() ) {
					ErrorException::raise( $created->as_error() );
				}
				$data        = $created->get_data();
				$question_id = is_array( $data ) ? (int) ( $data['id'] ?? 0 ) : 0;
				if ( ! $question_id || OHMYLMS_QUESTION_CPT !== get_post_type( $question_id ) ) {
					throw new \RuntimeException( 'Question create failed' );
				}
				DraftWriter::link( $quiz_id, $question_id );

				return $question_id;
			},
			$question_id
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$response->set_data( array_merge( (array) $response->get_data(), array( 'created_id' => (int) $question_id ) ) );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '%s/questions/%d', $this->namespace, $question_id ) ) );

		return $response;
	}

	/**
	 * Store a new order. The list must name every question of the quiz exactly once.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reorder_items( $request ) {
		global $wpdb;

		$quiz_id = (int) $request['id'];
		$order   = array_map( 'intval', (array) $request['question_ids'] );

		return $this->write(
			$request,
			array( 'question_ids', 'revision' ),
			'reorder',
			static function ( array $ids ) use ( $quiz_id, $order, $wpdb ) {
				$sorted  = $order;
				$current = $ids;
				sort( $sorted );
				sort( $current );
				if ( $sorted !== $current ) {
					ErrorException::raise(
						new WP_Error(
							'ohmylms_quiz_questions_mismatch',
							__( 'The new order must list every question of the quiz exactly once. Reload the questions and try again.', 'ohmylms' ),
							array(
								'status'   => 409,
								'expected' => count( $ids ),
							)
						)
					);
				}
				foreach ( $order as $index => $question_id ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Placement table has no API; written inside the locked transaction.
					$updated = $wpdb->update(
						$wpdb->prefix . OHMYLMS_QUIZ_QUESTION_RELATIONSHIP,
						array( 'order_number' => $index + 1 ),
						array(
							'quiz_id'     => $quiz_id,
							'question_id' => $question_id,
						),
						array( '%d' ),
						array( '%d', '%d' )
					);
					if ( false === $updated ) {
						throw new \RuntimeException( 'Order update failed' );
					}
				}
			}
		);
	}

	/**
	 * Remove a question from this quiz only (`Usage::remove_from_quiz`); the question stays in the bank.
	 * Its pin in this quiz is dropped too.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$quiz_id     = (int) $request['id'];
		$question_id = (int) $request['question_id'];

		return $this->write(
			$request,
			array( 'revision' ),
			'remove',
			function ( array $ids, array $pins ) use ( $quiz_id, $question_id ) {
				ErrorException::raise( $this->check_in_quiz( $question_id, $ids ) );
				ErrorException::raise( Usage::remove_from_quiz( $quiz_id, $question_id ) );
				if ( isset( $pins[ $question_id ] ) ) {
					unset( $pins[ $question_id ] );
					$this->save_pins( $quiz_id, $pins );
				}
				$this->save_slot_marks( $quiz_id, $question_id, null );
				$sections = $this->stored_sections( $quiz_id );
				foreach ( $sections as &$section ) {
					$section['questions'] = array_values( array_diff( $section['questions'], array( $question_id ) ) );
					unset( $section['marks'][ $question_id ] );
				}
				unset( $section );
				$this->save_sections( $quiz_id, $sections );
			}
		);
	}

	/**
	 * Set the marks of one question in this quiz (`marks`), or go back to the points of the question
	 * (`marks: null`). The override wins over the question's points and over section marks.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function marks_item( $request ) {
		$quiz_id     = (int) $request['id'];
		$question_id = (int) $request['question_id'];
		$marks       = null === $request['marks'] ? null : (float) $request['marks'];

		return $this->write(
			$request,
			array( 'marks', 'revision' ),
			'marks',
			function ( array $ids ) use ( $quiz_id, $question_id, $marks ) {
				ErrorException::raise( $this->check_in_quiz( $question_id, $ids ) );
				$this->save_slot_marks( $quiz_id, $question_id, $marks );
				$sections = $this->stored_sections( $quiz_id );
				foreach ( $sections as &$section ) {
					unset( $section['marks'][ $question_id ] );
				}
				unset( $section );
				$this->save_sections( $quiz_id, $sections );
			}
		);
	}

	/**
	 * Replace the sections of the quiz: titles, which questions each holds (in paper order, a question
	 * in at most one section) and whether a section starts a new page. Section marks of questions that
	 * stay in their section are kept.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function sections_items( $request ) {
		$quiz_id = (int) $request['id'];
		$input   = (array) $request['sections'];

		return $this->write(
			$request,
			array( 'sections', 'revision' ),
			'sections',
			function ( array $ids ) use ( $quiz_id, $input ) {
				$old  = array();
				$seen = array();
				foreach ( $this->stored_sections( $quiz_id ) as $section ) {
					foreach ( $section['marks'] as $question_id => $value ) {
						$old[ $section['title'] ][ (int) $question_id ] = $value;
					}
				}
				$sections = array();
				foreach ( array_values( $input ) as $index => $section ) {
					$title = trim( sanitize_text_field( (string) $section['title'] ) );
					if ( '' === $title ) {
						ErrorException::raise( $this->invalid_section( __( 'Give every section a title.', 'ohmylms' ), "sections[$index].title" ) );
					}
					$questions = array_map( 'intval', (array) $section['question_ids'] );
					foreach ( $questions as $question_id ) {
						if ( ! in_array( $question_id, $ids, true ) ) {
							ErrorException::raise( $this->invalid_section( __( 'A section contains a question that is not in this quiz.', 'ohmylms' ), "sections[$index].question_ids", $question_id ) );
						}
						if ( isset( $seen[ $question_id ] ) ) {
							ErrorException::raise( $this->invalid_section( __( 'A question can belong to only one section.', 'ohmylms' ), "sections[$index].question_ids", $question_id ) );
						}
						$seen[ $question_id ] = true;
					}
					$sections[] = array(
						'title'     => $title,
						'questions' => $questions,
						'marks'     => array_intersect_key( $old[ $title ] ?? array(), array_flip( $questions ) ),
						'new_page'  => ! empty( $section['new_page'] ),
					);
				}
				$this->save_sections( $quiz_id, $sections );
			}
		);
	}

	/**
	 * Replace the random pools of the quiz. Each pool draws approved questions of a skill (and its
	 * sub-skills); a named bank must be one the user may use. The pools must be able to draw their
	 * questions from distinct families, as publishing requires.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function pools_items( $request ) {
		$quiz_id = (int) $request['id'];
		$input   = (array) $request['pools'];

		return $this->write(
			$request,
			array( 'pools', 'revision' ),
			'pools',
			function () use ( $quiz_id, $input ) {
				$rules = array();
				foreach ( array_values( $input ) as $index => $pool ) {
					$skill = (int) $pool['skill_id'];
					if ( ! term_exists( $skill, Taxonomy::NAME ) ) {
						ErrorException::raise(
							new WP_Error(
								'ohmylms_quiz_pool_invalid',
								__( 'Choose an existing skill for every pool.', 'ohmylms' ),
								array(
									'status' => 422,
									'field'  => "pools[$index].skill_id",
								)
							)
						);
					}
					$bank = (int) ( $pool['bank_id'] ?? 0 );
					if ( $bank && ! Banks::can( $bank, 'use' ) ) {
						ErrorException::raise(
							new WP_Error(
								'ohmylms_quiz_pool_bank_forbidden',
								__( 'You are not allowed to draw questions from this bank.', 'ohmylms' ),
								array(
									'status' => rest_authorization_required_code(),
									'field'  => "pools[$index].bank_id",
								)
							)
						);
					}
					$rules[] = array(
						'title'          => (string) ( $pool['title'] ?? '' ),
						'term_id'        => $skill,
						'count'          => (int) $pool['count'],
						'marks'          => (float) ( $pool['marks'] ?? 1 ),
						'difficulty'     => (string) ( $pool['difficulty'] ?? '' ),
						'bank_id'        => $bank,
						'include_secure' => ! isset( $pool['include_secure'] ) || ! empty( $pool['include_secure'] ),
					);
				}
				if ( ! $rules ) {
					delete_post_meta( $quiz_id, Pools::META );
					return;
				}
				$saved = Pools::save( $quiz_id, $rules );
				if ( is_wp_error( $saved ) ) {
					$data = (array) $saved->get_error_data();
					if ( 'ohmylms_pool_invalid' === $saved->get_error_code() ) {
						$data['status'] = 422;
					}
					ErrorException::raise( new WP_Error( $saved->get_error_code(), $saved->get_error_message(), $data ) );
				}
			}
		);
	}

	/**
	 * Pin a question of this quiz to a version (`version_id`), to the version the quiz would use now
	 * (`current: true`), or unpin it (`version_id: null`). A user who may only use the question can pin
	 * only its approved version.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function pin_item( $request ) {
		$quiz_id     = (int) $request['id'];
		$question_id = (int) $request['question_id'];
		$current     = rest_sanitize_boolean( $request['current'] );
		$has_version = $this->was_sent( $request, 'version_id' );
		if ( $current === $has_version ) {
			return new WP_Error( 'ohmylms_quiz_pin_invalid', __( 'Send either a version_id (null to unpin) or current: true.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$version_id = $has_version && null !== $request['version_id'] ? (int) $request['version_id'] : 0;

		return $this->write(
			$request,
			array( 'version_id', 'current', 'revision' ),
			'pin',
			function ( array $ids, array $pins ) use ( $quiz_id, $question_id, $current, $version_id ) {
				ErrorException::raise( $this->check_in_quiz( $question_id, $ids ) );
				if ( ! $current && ! $version_id ) {
					unset( $pins[ $question_id ] );
					$this->save_pins( $quiz_id, $pins );
					return;
				}
				$can_edit = AccessPolicy::can_edit_question( $question_id );
				if ( ! $can_edit && ! AccessPolicy::can_use_question( $question_id ) ) {
					ErrorException::raise( $this->question_forbidden( $question_id ) );
				}
				$version = $current ? $this->unpinned_version( $quiz_id, $question_id ) : VersionPublisher::version( $version_id );
				if ( is_wp_error( $version ) ) {
					ErrorException::raise( $version );
				}
				if ( ! $version || (int) $version['question_id'] !== $question_id ) {
					ErrorException::raise( new WP_Error( 'ohmylms_version_missing', __( 'Version not found for this question.', 'ohmylms' ), array( 'status' => 404 ) ) );
				}
				if ( ! $can_edit ) {
					$identity = VersionPublisher::identity( $question_id, false );
					if ( ! $identity || (int) $identity['approved_version_id'] !== (int) $version['id'] ) {
						ErrorException::raise(
							new WP_Error(
								'ohmylms_quiz_pin_forbidden',
								__( 'You can only pin the approved version of a shared question.', 'ohmylms' ),
								array(
									'status'      => rest_authorization_required_code(),
									'question_id' => $question_id,
								)
							)
						);
					}
				}
				$pins[ $question_id ] = (int) $version['id'];
				$this->save_pins( $quiz_id, $pins );
			}
		);
	}

	// Writes.

	/**
	 * Run one write: refuse unknown fields and trashed quizzes, then lock the quiz, compare the
	 * revision and run the work in one transaction.
	 *
	 * @param WP_REST_Request $request   Request.
	 * @param string[]        $fields    Body fields the route accepts.
	 * @param string          $operation Operation name for the hook.
	 * @param callable        $work      Receives the current question ids and pins.
	 * @param mixed           $result    Receives what the work returned.
	 * @return WP_REST_Response|WP_Error
	 */
	private function write( WP_REST_Request $request, array $fields, $operation, callable $work, &$result = null ) {
		global $wpdb;

		$known = $this->reject_unknown_fields( $request, $fields );
		if ( is_wp_error( $known ) ) {
			return $known;
		}
		$quiz_id = (int) $request['id'];
		if ( 'trash' === get_post_status( $quiz_id ) ) {
			return new WP_Error( 'ohmylms_quiz_trashed', __( 'Restore this quiz from the trash before changing its questions.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		$base = (string) $request['revision'];
		try {
			$result = Transaction::run(
				function () use ( $wpdb, $quiz_id, $base, $work ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Row lock that serialises writers of this quiz; must not be cached.
					$wpdb->query( $wpdb->prepare( 'SELECT ID FROM %i WHERE ID = %d FOR UPDATE', $wpdb->posts, $quiz_id ) );
					$ids     = $this->question_ids( $quiz_id );
					$pins    = $this->pins( $quiz_id );
					$current = $this->revision( $ids, $pins, $this->layout( $quiz_id ) );
					if ( ! hash_equals( $current, $base ) ) {
						ErrorException::raise(
							new WP_Error(
								'ohmylms_quiz_questions_conflict',
								__( 'The questions of this quiz were changed by someone else. Reload them before making more changes.', 'ohmylms' ),
								array(
									'status'   => 409,
									'revision' => $current,
								)
							)
						);
					}

					return $work( $ids, $pins );
				}
			);
		} catch ( ErrorException $error ) {
			return $error->error;
		} catch ( \Throwable $error ) {
			return new WP_Error( 'ohmylms_quiz_questions_storage', __( 'The quiz questions could not be saved. Nothing was changed.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		wp_cache_delete( $quiz_id, 'post_meta' );

		/**
		 * Fires after the questions of a quiz were changed through `ohmylms/v1/quizzes/{id}/questions`.
		 *
		 * @param int             $quiz_id   Quiz ID.
		 * @param string          $operation `add`, `create`, `reorder`, `remove`, `pin`, `marks`, `sections` or `pools`.
		 * @param WP_REST_Request $request   The request.
		 */
		do_action( 'ohmylms_rest_quiz_questions_changed', $quiz_id, $operation, $request );

		return $this->respond( $quiz_id, $request );
	}

	/**
	 * A bank question the current user may add to a quiz.
	 *
	 * @param int $question_id Question ID.
	 * @return true|WP_Error
	 */
	private function check_usable( $question_id ) {
		$post = get_post( $question_id );
		if ( ! $post || OHMYLMS_QUESTION_CPT !== $post->post_type || in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
			return new WP_Error(
				'ohmylms_question_not_found',
				__( 'Question not found.', 'ohmylms' ),
				array(
					'status'      => 404,
					'question_id' => $question_id,
				)
			);
		}
		if ( Usage::ARCHIVED === $post->post_status ) {
			return new WP_Error(
				'ohmylms_question_archived',
				__( 'Archived questions cannot be added to a quiz. Restore the question first.', 'ohmylms' ),
				array(
					'status'      => 409,
					'question_id' => $question_id,
				)
			);
		}
		if ( AccessPolicy::can_edit_question( $question_id ) ) {
			return true;
		}
		if ( ! AccessPolicy::can_use_question( $question_id ) ) {
			return $this->question_forbidden( $question_id );
		}
		$identity = VersionPublisher::identity( $question_id, false );
		if ( ! $identity || ! (int) $identity['approved_version_id'] ) {
			return new WP_Error(
				'quiz_question_unapproved',
				sprintf(
					/* translators: %s: question name. */
					__( '"%s" has no approved version yet.', 'ohmylms' ),
					$this->plain( (string) $post->post_title )
				),
				array(
					'status'      => 409,
					'question_id' => $question_id,
				)
			);
		}

		return true;
	}

	/**
	 * The question must be placed in the quiz.
	 *
	 * @param int   $question_id Question ID.
	 * @param int[] $ids         Question IDs of the quiz.
	 * @return true|WP_Error
	 */
	private function check_in_quiz( $question_id, array $ids ) {
		return in_array( $question_id, $ids, true ) ? true : new WP_Error( 'ohmylms_question_not_in_quiz', __( 'The question is not part of this quiz.', 'ohmylms' ), array( 'status' => 404 ) );
	}

	/**
	 * The quiz may not grow past the question limit.
	 *
	 * @param int $total Number of questions after the change.
	 * @return true|WP_Error
	 */
	private function check_limit( $total ) {
		if ( $total <= self::MAX_QUESTIONS ) {
			return true;
		}

		return new WP_Error(
			'ohmylms_quiz_questions_limit',
			sprintf(
				/* translators: %d: maximum number of questions. */
				__( 'A quiz can hold at most %d questions.', 'ohmylms' ),
				self::MAX_QUESTIONS
			),
			array(
				'status' => 422,
				'max'    => self::MAX_QUESTIONS,
			)
		);
	}

	/**
	 * Build the error for a question the user may neither edit nor use.
	 *
	 * @param int $question_id Question ID.
	 * @return WP_Error
	 */
	private function question_forbidden( $question_id ) {
		return new WP_Error(
			'ohmylms_quiz_question_forbidden',
			__( 'You are not allowed to use this question.', 'ohmylms' ),
			array(
				'status'      => rest_authorization_required_code(),
				'question_id' => $question_id,
			)
		);
	}

	/**
	 * The version the quiz would use for a question without a pin (as `RevisionPublisher::slot_version()`,
	 * judged for the quiz owner): the current version when the owner may edit the question, otherwise the
	 * approved version. Captures the current content as a version when it changed.
	 *
	 * @param int $quiz_id     Quiz ID.
	 * @param int $question_id Question ID.
	 * @return array|WP_Error Version row.
	 */
	private function unpinned_version( $quiz_id, $question_id ) {
		$identity = VersionPublisher::identity( $question_id, false );
		if ( $this->owner_edits( $quiz_id, $question_id, $identity ? $identity : array() ) ) {
			$version = VersionPublisher::capture( $question_id );
			return $version ? $version : new WP_Error( 'quiz_version_failed', __( 'A question version could not be captured.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		$approved = $identity ? VersionPublisher::version( (int) $identity['approved_version_id'] ) : null;

		return $approved ? $approved : new WP_Error(
			'quiz_question_unapproved',
			__( 'This shared question has no approved version yet.', 'ohmylms' ),
			array(
				'status'      => 409,
				'question_id' => $question_id,
			)
		);
	}

	/**
	 * Whether the quiz owner may edit a question (then the quiz uses its current version).
	 *
	 * @param int   $quiz_id     Quiz ID.
	 * @param int   $question_id Question ID.
	 * @param array $identity    Identity row of the question (may be empty).
	 * @return bool
	 */
	private function owner_edits( $quiz_id, $question_id, array $identity ) {
		$owner = (int) get_post_field( 'post_author', $quiz_id );
		$bank  = (int) ( $identity['bank_id'] ?? 0 );

		return $owner > 0 && ( user_can( $owner, 'edit_post', $question_id ) || ( $bank && Banks::can( $bank, 'edit', $owner ) ) );
	}

	/**
	 * Store the pins of a quiz; no pins removes the meta.
	 *
	 * @param int   $quiz_id Quiz ID.
	 * @param array $pins    Version ID by question ID.
	 */
	private function save_pins( $quiz_id, array $pins ) {
		$pins = array_filter( array_map( 'intval', $pins ) );
		ksort( $pins );
		if ( $pins ) {
			update_post_meta( $quiz_id, self::PINS_META, $pins );
		} else {
			delete_post_meta( $quiz_id, self::PINS_META );
		}
	}

	/**
	 * Refuse body fields the route does not accept.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string[]        $allowed Accepted field names.
	 * @return true|WP_Error
	 */
	private function reject_unknown_fields( WP_REST_Request $request, array $allowed ) {
		$json    = $request->get_json_params();
		$body    = array_merge( (array) $request->get_body_params(), is_array( $json ) ? $json : array() );
		$unknown = array_values( array_diff( array_map( 'strval', array_keys( $body ) ), $allowed ) );
		if ( $unknown ) {
			return new WP_Error(
				'ohmylms_quiz_questions_unknown_field',
				__( 'Unknown field(s) in the request.', 'ohmylms' ),
				array(
					'status' => 400,
					'fields' => $unknown,
				)
			);
		}

		return true;
	}

	/**
	 * Whether a field was sent in the body or query, including an explicit null.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $key     Field name.
	 * @return bool
	 */
	private function was_sent( WP_REST_Request $request, $key ) {
		foreach ( array( $request->get_json_params(), $request->get_body_params(), $request->get_query_params() ) as $source ) {
			if ( is_array( $source ) && array_key_exists( $key, $source ) ) {
				return true;
			}
		}

		return false;
	}

	// Reads.

	/**
	 * Question IDs of a quiz in order, read from the database (each question once).
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return int[]
	 */
	private function question_ids( $quiz_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Placement table has no API; read fresh for the revision check.
		$rows = $wpdb->get_col( $wpdb->prepare( 'SELECT question_id FROM %i WHERE quiz_id = %d GROUP BY question_id ORDER BY MIN(order_number) ASC, MIN(id) ASC', $wpdb->prefix . OHMYLMS_QUIZ_QUESTION_RELATIONSHIP, $quiz_id ) );

		return array_map( 'intval', (array) $rows );
	}

	/**
	 * Pins of a quiz read from the database, so a cached copy cannot hide a concurrent change.
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return array<int,int> Version ID by question ID.
	 */
	private function pins( $quiz_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Deliberately bypasses the meta cache (see the method description).
		$raw  = $wpdb->get_var( $wpdb->prepare( 'SELECT meta_value FROM %i WHERE post_id = %d AND meta_key = %s ORDER BY meta_id ASC LIMIT 1', $wpdb->postmeta, $quiz_id, self::PINS_META ) );
		$raw  = null === $raw ? array() : maybe_unserialize( $raw );
		$pins = array();
		foreach ( is_array( $raw ) ? $raw : array() as $question_id => $version_id ) {
			if ( (int) $question_id > 0 && (int) $version_id > 0 ) {
				$pins[ (int) $question_id ] = (int) $version_id;
			}
		}
		ksort( $pins );

		return $pins;
	}

	/**
	 * Revision token of a question list: changes whenever the order, the members, the pins, the
	 * per-question marks, the sections or the random pools change, whichever route or writer changed them.
	 *
	 * @param int[]          $ids    Question IDs in order.
	 * @param array<int,int> $pins   Pins.
	 * @param array          $layout Marks, sections and pools ({@see layout()}).
	 * @return string 16 hexadecimal characters.
	 */
	private function revision( array $ids, array $pins, array $layout ) {
		$pairs = array();
		foreach ( $pins as $question_id => $version_id ) {
			$pairs[] = array( (int) $question_id, (int) $version_id );
		}
		$marks = array();
		foreach ( $layout['marks'] as $question_id => $value ) {
			$marks[] = array( (int) $question_id, (float) $value );
		}

		return substr( hash( 'sha256', (string) wp_json_encode( array( array_map( 'intval', $ids ), $pairs, $marks, $layout['sections'], $layout['pools'] ) ) ), 0, 16 );
	}

	/**
	 * Per-question marks, sections and pools of a quiz, read from the database (not the meta cache).
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return array{marks:array<int,float>,sections:array[],pools:array}
	 */
	private function layout( $quiz_id ) {
		$pools = $this->fresh_meta( $quiz_id, Pools::META );

		return array(
			'marks'    => $this->slot_marks( $quiz_id ),
			'sections' => $this->stored_sections( $quiz_id ),
			'pools'    => is_array( $pools ) ? array_values( $pools ) : array(),
		);
	}

	/**
	 * A quiz meta value read from the database, so a cached copy cannot hide a concurrent change.
	 *
	 * @param int    $quiz_id Quiz ID.
	 * @param string $key     Meta key.
	 * @return mixed Unserialized value, or null when unset.
	 */
	private function fresh_meta( $quiz_id, $key ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Deliberately bypasses the meta cache (see the method description).
		$raw = $wpdb->get_var( $wpdb->prepare( 'SELECT meta_value FROM %i WHERE post_id = %d AND meta_key = %s ORDER BY meta_id ASC LIMIT 1', $wpdb->postmeta, $quiz_id, $key ) );

		return null === $raw ? null : maybe_unserialize( $raw );
	}

	/**
	 * Marks overrides by question (`slot_marks` of the quiz settings).
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return array<int,float>
	 */
	private function slot_marks( $quiz_id ) {
		$settings = $this->fresh_meta( $quiz_id, self::SETTINGS_META );
		$marks    = array();
		foreach ( (array) ( is_array( $settings ) ? ( $settings['slot_marks'] ?? array() ) : array() ) as $question_id => $value ) {
			if ( (int) $question_id > 0 && is_numeric( $value ) ) {
				$marks[ (int) $question_id ] = round( max( 0, (float) $value ), 4 );
			}
		}
		ksort( $marks );

		return $marks;
	}

	/**
	 * Store or clear the marks override of one question.
	 *
	 * @param int        $quiz_id     Quiz ID.
	 * @param int        $question_id Question ID.
	 * @param float|null $marks       Marks, or null to clear.
	 */
	private function save_slot_marks( $quiz_id, $question_id, $marks ) {
		$settings = $this->fresh_meta( $quiz_id, self::SETTINGS_META );
		$settings = is_array( $settings ) ? $settings : array();
		$current  = is_array( $settings['slot_marks'] ?? null ) ? $settings['slot_marks'] : array();
		if ( null === $marks && ! array_key_exists( $question_id, $current ) ) {
			return;
		}
		if ( null === $marks ) {
			unset( $current[ $question_id ] );
		} else {
			$current[ $question_id ] = round( $marks, 4 );
		}
		if ( $current ) {
			$settings['slot_marks'] = $current;
		} else {
			unset( $settings['slot_marks'] );
		}
		update_post_meta( $quiz_id, self::SETTINGS_META, $settings );
	}

	/**
	 * Sections of the quiz as stored in the assessment settings, normalized.
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return array[] {title, questions: int[], marks: array<int,float>, new_page: bool}
	 */
	private function stored_sections( $quiz_id ) {
		$settings = $this->fresh_meta( $quiz_id, AssessmentSettings::META );
		$sections = array();
		foreach ( (array) ( is_array( $settings ) ? ( $settings['sections'] ?? array() ) : array() ) as $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}
			$marks = array();
			foreach ( (array) ( $section['marks'] ?? array() ) as $question_id => $value ) {
				if ( is_numeric( $value ) ) {
					$marks[ (int) $question_id ] = (float) $value;
				}
			}
			$sections[] = array(
				'title'     => (string) ( $section['title'] ?? '' ),
				'questions' => array_values( array_map( 'intval', (array) ( $section['questions'] ?? array() ) ) ),
				'marks'     => $marks,
				'new_page'  => ! empty( $section['new_page'] ),
			);
		}

		return $sections;
	}

	/**
	 * Store the sections through the assessment settings, which validate them against the quiz.
	 *
	 * @param int     $quiz_id  Quiz ID.
	 * @param array[] $sections Sections ({@see stored_sections()} shape).
	 */
	private function save_sections( $quiz_id, array $sections ) {
		if ( $sections === $this->stored_sections( $quiz_id ) ) {
			return;
		}
		$saved = AssessmentSettings::save( $quiz_id, array( 'sections' => $sections ) );
		if ( is_wp_error( $saved ) ) {
			ErrorException::raise( new WP_Error( 'ohmylms_quiz_sections_invalid', $saved->get_error_message(), array( 'status' => 422 ) ) );
		}
	}

	/**
	 * Build a section validation error.
	 *
	 * @param string $message     Message.
	 * @param string $field       Field path.
	 * @param int    $question_id Question concerned, if any.
	 * @return WP_Error
	 */
	private function invalid_section( $message, $field, $question_id = 0 ) {
		$data = array(
			'status' => 422,
			'field'  => $field,
		);
		if ( $question_id ) {
			$data['question_id'] = $question_id;
		}

		return new WP_Error( 'ohmylms_quiz_sections_invalid', $message, $data );
	}

	/**
	 * Build the response with the current list.
	 *
	 * @param int             $quiz_id Quiz ID.
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	private function respond( $quiz_id, $request ) {
		$ids    = $this->question_ids( $quiz_id );
		$pins   = $this->pins( $quiz_id );
		$layout = $this->layout( $quiz_id );
		$slots  = $this->slots( $quiz_id, $ids, $pins );
		$pools  = $this->pools( $quiz_id );
		$fixed  = 0.0;
		foreach ( $slots as $slot ) {
			$fixed += $slot['marks'];
		}
		$pool_questions = 0;
		$pool_marks     = 0.0;
		foreach ( $pools as $pool ) {
			$pool_questions += $pool['count'];
			$pool_marks     += $pool['count'] * $pool['marks'];
		}
		$data = array(
			'quiz_id'   => $quiz_id,
			'revision'  => $this->revision( $ids, $pins, $layout ),
			'questions' => $slots,
			'sections'  => array_map(
				static function ( array $section ) {
					return array(
						'title'        => $section['title'],
						'question_ids' => $section['questions'],
						'new_page'     => $section['new_page'],
					);
				},
				$layout['sections']
			),
			'pools'     => $pools,
			'totals'    => array(
				'questions'      => count( $slots ),
				'fixed_marks'    => round( $fixed, 4 ),
				'pool_questions' => $pool_questions,
				'pool_marks'     => round( $pool_marks, 4 ),
				'total_marks'    => round( $fixed + $pool_marks, 4 ),
			),
		);

		/**
		 * Filters the question list of a quiz. Slots must not carry settings or answer options.
		 *
		 * @param array           $data    Response data.
		 * @param int             $quiz_id Quiz ID.
		 * @param WP_REST_Request $request Request.
		 */
		$data     = apply_filters( 'ohmylms_rest_prepare_quiz_questions', $data, $quiz_id, $request );
		$response = rest_ensure_response( $data );
		$response->header( 'Cache-Control', 'private, no-store' );

		return $response;
	}

	/**
	 * Slots of the quiz: one summary per question, without settings, options or answer keys. Questions
	 * the user may neither edit nor use show no name or type.
	 *
	 * @param int            $quiz_id Quiz ID.
	 * @param int[]          $ids     Question IDs in order.
	 * @param array<int,int> $pins    Pins.
	 * @return array[]
	 */
	private function slots( $quiz_id, array $ids, array $pins ) {
		if ( ! $ids ) {
			return array();
		}
		_prime_post_caches( $ids, false, true );
		$identities = Search::identities( $ids );
		$usage      = Search::usage_counts( $ids );
		$sections   = $this->sections( $quiz_id );
		$slots      = array();
		foreach ( $ids as $index => $question_id ) {
			$post     = get_post( $question_id );
			$exists   = $post && OHMYLMS_QUESTION_CPT === $post->post_type;
			$can_edit = $exists && AccessPolicy::can_edit_question( $question_id );
			$can_use  = $can_edit || ( $exists && AccessPolicy::can_use_question( $question_id ) );
			$identity = $identities[ $question_id ] ?? array();
			$settings = $exists ? get_post_meta( $question_id, '_question_settings', true ) : array();
			$settings = is_array( $settings ) ? $settings : array();
			$pinned   = (int) ( $pins[ $question_id ] ?? 0 );
			$delivery = $this->delivery( $quiz_id, $question_id, $identity, $pinned, $settings );
			$points   = ! empty( $delivery['settings']['score']['enabled'] ) ? max( 0, (float) ( $delivery['settings']['score']['value'] ?? 0 ) ) : 0.0;
			$override = isset( $sections['marks'][ $question_id ] ) ? round( max( 0, $sections['marks'][ $question_id ] ), 4 ) : null;
			$marks    = null === $override ? $points : $override;
			$type     = (string) ( $settings['type'] ?? '' );
			if ( ! $exists || 'trash' === $post->post_status ) {
				$status = 'unavailable';
			} elseif ( Usage::ARCHIVED === $post->post_status ) {
				$status = 'archived';
			} elseif ( ! empty( $identity['approved_version_id'] ) ) {
				$status = 'approved';
			} else {
				$status = 'draft';
			}
			$pinned_version = $pinned ? VersionPublisher::version( $pinned ) : null;

			$slots[] = array(
				'question_id'         => $question_id,
				'position'            => $index + 1,
				'name'                => $can_use ? $this->plain( (string) $post->post_title ) : '',
				'type'                => $can_use ? $type : '',
				'type_label'          => $can_use ? $this->type_label( $type ) : '',
				'marks'               => round( $marks, 4 ),
				'question_marks'      => round( $points, 4 ),
				'marks_override'      => $override,
				'section'             => (string) ( $sections['by_question'][ $question_id ] ?? '' ),
				'status'              => $status,
				'version'             => (int) ( $identity['latest_version_no'] ?? 0 ),
				'current_version_id'  => (int) ( $identity['current_version_id'] ?? 0 ),
				'approved_version_id' => (int) ( $identity['approved_version_id'] ?? 0 ),
				'pinned_version_id'   => $pinned,
				'pinned_version_no'   => $pinned_version && (int) $pinned_version['question_id'] === $question_id ? (int) $pinned_version['version_no'] : 0,
				'delivery'            => array(
					'source'     => $delivery['source'],
					'version_id' => $delivery['version_id'],
					'version_no' => $delivery['version_no'],
				),
				'usage'               => $usage[ $question_id ] ?? array(
					'quizzes'   => 0,
					'responses' => 0,
				),
				'permissions'         => array(
					'can_edit' => $can_edit,
					'can_use'  => $can_use,
				),
				'modified'            => $exists ? $this->iso( $post->post_modified_gmt ) : '',
			);
		}

		return $slots;
	}

	/**
	 * Which version learners of the quiz get, read-only (never captures): the pin; else the current
	 * content when the quiz owner may edit the question; else the approved version; else none.
	 *
	 * @param int   $quiz_id     Quiz ID.
	 * @param int   $question_id Question ID.
	 * @param array $identity    Identity row (may be empty).
	 * @param int   $pinned      Pinned version ID, or 0.
	 * @param array $settings    Current question settings.
	 * @return array{source:string,version_id:int,version_no:int,settings:array}
	 */
	private function delivery( $quiz_id, $question_id, array $identity, $pinned, array $settings ) {
		$decode = static function ( array $version ) {
			$decoded = json_decode( (string) $version['settings'], true );
			return is_array( $decoded ) ? $decoded : array();
		};
		if ( $pinned ) {
			$version = VersionPublisher::version( $pinned );
			if ( $version && (int) $version['question_id'] === $question_id ) {
				return array(
					'source'     => 'pinned',
					'version_id' => $pinned,
					'version_no' => (int) $version['version_no'],
					'settings'   => $decode( $version ),
				);
			}
		}
		if ( $this->owner_edits( $quiz_id, $question_id, $identity ) ) {
			return array(
				'source'     => 'current',
				'version_id' => (int) ( $identity['current_version_id'] ?? 0 ),
				'version_no' => (int) ( $identity['latest_version_no'] ?? 0 ),
				'settings'   => $settings,
			);
		}
		$approved = (int) ( $identity['approved_version_id'] ?? 0 );
		$version  = $approved ? VersionPublisher::version( $approved ) : null;
		if ( $version ) {
			return array(
				'source'     => 'approved',
				'version_id' => $approved,
				'version_no' => (int) $version['version_no'],
				'settings'   => $decode( $version ),
			);
		}

		return array(
			'source'     => 'unavailable',
			'version_id' => 0,
			'version_no' => 0,
			'settings'   => $settings,
		);
	}

	/**
	 * Section titles and section marks by question, read as `RevisionPublisher` reads them.
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return array{by_question:array<int,string>,marks:array<int,float>}
	 */
	private function sections( $quiz_id ) {
		$quiz        = ohmylms_get_quiz( $quiz_id );
		$raw         = array_merge( $quiz ? (array) $quiz->get_settings() : array(), AssessmentSettings::frozen( $quiz_id ) );
		$by_question = array();
		$marks       = array();
		foreach ( (array) ( $raw['sections'] ?? array() ) as $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}
			foreach ( (array) ( $section['questions'] ?? array() ) as $question_id ) {
				$by_question[ (int) $question_id ] = sanitize_text_field( (string) ( $section['title'] ?? '' ) );
			}
			foreach ( (array) ( $section['marks'] ?? array() ) as $question_id => $value ) {
				if ( is_numeric( $value ) ) {
					$marks[ (int) $question_id ] = (float) $value;
				}
			}
		}
		foreach ( (array) ( $raw['slot_marks'] ?? array() ) as $question_id => $value ) {
			if ( is_numeric( $value ) ) {
				$marks[ (int) $question_id ] = (float) $value;
			}
		}

		return array(
			'by_question' => $by_question,
			'marks'       => $marks,
		);
	}

	/**
	 * Random pools of the quiz (edited through `PUT /quizzes/{id}/questions/pools`).
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return array[]
	 */
	private function pools( $quiz_id ) {
		$pools = array();
		foreach ( Pools::rules( $quiz_id ) as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$term    = get_term( (int) ( $rule['term_id'] ?? 0 ), Taxonomy::NAME );
			$pools[] = array(
				'id'             => (string) ( $rule['id'] ?? '' ),
				'title'          => (string) ( $rule['title'] ?? '' ),
				'skill'          => array(
					'id'   => (int) ( $rule['term_id'] ?? 0 ),
					'name' => $term && ! is_wp_error( $term ) ? html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ) : '',
				),
				'count'          => max( 0, (int) ( $rule['count'] ?? 0 ) ),
				'marks'          => round( max( 0, (float) ( $rule['marks'] ?? 0 ) ), 4 ),
				'difficulty'     => (string) ( $rule['difficulty'] ?? '' ),
				'bank_id'        => (int) ( $rule['bank_id'] ?? 0 ),
				'include_secure' => ! empty( $rule['include_secure'] ),
			);
		}

		return $pools;
	}

	/**
	 * Label of a question type (its ID when the type is not registered).
	 *
	 * @param string $type Type ID.
	 * @return string
	 */
	private function type_label( $type ) {
		$definition = '' !== $type ? Registry::get( 'question', $type ) : null;

		return $definition ? wp_strip_all_tags( (string) $definition['label'] ) : $type;
	}

	/**
	 * Plain, bounded text.
	 *
	 * @param string $html HTML or text.
	 * @return string
	 */
	private function plain( $html ) {
		return mb_substr( trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) ), 0, 500 );
	}

	/**
	 * RFC 3339 UTC time from a GMT MySQL datetime.
	 *
	 * @param string $gmt GMT datetime.
	 * @return string Empty when unset.
	 */
	private function iso( $gmt ) {
		$time = ( $gmt && '0000-00-00 00:00:00' !== $gmt ) ? strtotime( $gmt . ' UTC' ) : 0;

		return $time ? gmdate( 'Y-m-d\TH:i:s\Z', $time ) : '';
	}
}
