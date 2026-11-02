<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * REST routes for the question bulk import and the export of selected or filtered questions.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Assessment\AssessmentSettings;
use OhMyLMS\Extensions\Registry;
use OhMyLMS\QuestionBank\AccessPolicy;
use OhMyLMS\QuestionBank\BankAttributes;
use OhMyLMS\QuestionBank\Banks;
use OhMyLMS\QuestionBank\BulkImport;
use OhMyLMS\QuestionBank\RowExporter;
use OhMyLMS\QuestionBank\Search;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Check, review and import a batch of question rows; export questions as the same rows.
 * See docs/api/questions.md ("Import and export").
 */
class QuestionImportController extends RestController {

	/**
	 * Largest import text, in bytes.
	 */
	const MAX_TEXT_BYTES = 2000000;

	/** Register POST /question-bank/import and POST /questions/export. */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/question-bank/import',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'import' ),
					'permission_callback' => array( $this, 'author_permission' ),
					'args'                => array(
						'rows'            => array(
							'type'     => 'array',
							'maxItems' => BulkImport::MAX_ROWS,
							'items'    => array( 'type' => 'object' ),
						),
						'text'            => array(
							'type'      => 'string',
							'maxLength' => self::MAX_TEXT_BYTES,
						),
						'format'          => array(
							'type'    => 'string',
							'enum'    => array( 'auto', 'json', 'csv' ),
							'default' => 'auto',
						),
						'dry_run'         => array( 'type' => 'boolean' ),
						'approve'         => array( 'type' => 'boolean' ),
						'update_existing' => array( 'type' => 'boolean' ),
						'skip_invalid'    => array( 'type' => 'boolean' ),
						'bank_id'         => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'default_skills'  => array(
							'type'      => 'string',
							'maxLength' => 2000,
						),
						'review'          => array(
							'description' => __( 'Review token from the dry run. When sent, the import is refused if the reviewed result changed.', 'ohmylms' ),
							'type'        => 'string',
							'pattern'     => '^[a-f0-9]{64}$',
						),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/questions/export',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'export' ),
					'permission_callback' => array( $this, 'export_permission' ),
					'args'                => array(
						'format'  => array(
							'type'     => 'string',
							'enum'     => array( 'csv', 'json' ),
							'required' => true,
						),
						'ids'     => array(
							'description' => __( 'Selected question IDs, in file order.', 'ohmylms' ),
							'type'        => 'array',
							'minItems'    => 1,
							'maxItems'    => RowExporter::MAX_QUESTIONS,
							'uniqueItems' => true,
							'items'       => array(
								'type'    => 'integer',
								'minimum' => 1,
							),
						),
						'filters' => array(
							'description'          => __( 'Export every visible question matching the list filters instead of selected IDs.', 'ohmylms' ),
							'type'                 => 'object',
							'additionalProperties' => false,
							'properties'           => array(
								'search'     => array(
									'type'      => 'string',
									'maxLength' => 100,
								),
								'type'       => array(
									'type'      => 'string',
									'maxLength' => 100,
								),
								'difficulty' => array(
									'type' => 'string',
									'enum' => array_merge( array( '' ), BankAttributes::DIFFICULTIES ),
								),
								'status'     => array(
									'type' => 'string',
									'enum' => array( 'active', 'draft', 'approved', 'archived' ),
								),
								'bank'       => array(
									'type'    => 'integer',
									'minimum' => 0,
								),
								'skill'      => array(
									'type'    => 'integer',
									'minimum' => 1,
								),
							),
						),
						'quiz_id' => array(
							'description' => __( 'Export the questions of this quiz in quiz order, with their section titles, instead of selected IDs or filters.', 'ohmylms' ),
							'type'        => 'integer',
							'minimum'     => 1,
						),
						'label'   => array(
							'description' => __( 'Collection name written into every row; it identifies the questions when the file is imported again.', 'ohmylms' ),
							'type'        => 'string',
							'maxLength'   => 190,
						),
					),
				),
			)
		);
	}

	/**
	 * Authors only: 401 anonymous, 403 others.
	 *
	 * @return true|WP_Error
	 */
	public function author_permission() {
		if ( AccessPolicy::can_author() ) {
			return true;
		}

		return new WP_Error( 'ohmylms_question_cannot_import', __( 'Sorry, you are not allowed to import questions.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Authors, and a named bank filter must be a bank the user can see.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function export_permission( $request ) {
		if ( ! AccessPolicy::can_author() ) {
			return new WP_Error( 'ohmylms_question_cannot_export', __( 'Sorry, you are not allowed to export questions.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}
		$filters = is_array( $request['filters'] ) ? $request['filters'] : array();
		$bank    = (int) ( $filters['bank'] ?? 0 );
		if ( $bank > 0 && ! current_user_can( 'edit_others_posts' ) && ! in_array( $bank, array_map( 'intval', array_column( Banks::for_user(), 'id' ) ), true ) ) {
			return new WP_Error( 'ohmylms_question_bank_forbidden', __( 'Sorry, you are not allowed to view that question bank.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		$quiz_id = (int) $request['quiz_id'];
		if ( $quiz_id > 0 ) {
			if ( OHMYLMS_QUIZ_CPT !== get_post_type( $quiz_id ) || 'trash' === get_post_status( $quiz_id ) ) {
				return new WP_Error( 'ohmylms_quiz_not_found', __( 'Quiz not found.', 'ohmylms' ), array( 'status' => 404 ) );
			}
			if ( ! AccessPolicy::can_edit_quiz( $quiz_id ) ) {
				return new WP_Error( 'ohmylms_question_cannot_export', __( 'You are not allowed to export this quiz.', 'ohmylms' ), array( 'status' => 403 ) );
			}
		}

		return true;
	}

	/**
	 * Mark a response private: it contains answer keys.
	 *
	 * @param WP_REST_Response $response Response.
	 * @return WP_REST_Response
	 */
	private function no_store( WP_REST_Response $response ) {
		$response->header( 'Cache-Control', 'private, no-store' );

		return $response;
	}

	/**
	 * Import options from the request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param bool            $dry_run Whether to only check.
	 * @return array
	 */
	private function options( WP_REST_Request $request, $dry_run ) {
		return array(
			'dry_run'         => $dry_run,
			'bank_id'         => absint( $request->get_param( 'bank_id' ) ),
			'approve'         => rest_sanitize_boolean( $request->get_param( 'approve' ) ),
			'update_existing' => ! $request->has_param( 'update_existing' ) || rest_sanitize_boolean( $request->get_param( 'update_existing' ) ),
			'skip_invalid'    => rest_sanitize_boolean( $request->get_param( 'skip_invalid' ) ),
			'default_skills'  => \OhMyLMS\ExamImport\Jobs::skill_tokens( $request->get_param( 'default_skills' ) ),
		);
	}

	/**
	 * Review token binding the caller, the input, the options that change writes and every row's
	 * planned status, target and errors.
	 *
	 * @param array $rows    Parsed rows.
	 * @param array $options Options.
	 * @param array $report  Dry-run report.
	 * @return string
	 */
	private function review_token( array $rows, array $options, array $report ) {
		$plan = array_map(
			static function ( $row ) {
				return array( $row['row'], $row['status'], (int) $row['id'], $row['errors'] );
			},
			(array) ( $report['rows'] ?? array() )
		);

		return hash_hmac(
			'sha256',
			wp_json_encode( array( get_current_user_id(), $rows, $options['bank_id'], $options['update_existing'], $options['default_skills'], $plan ) ),
			wp_salt( 'nonce' )
		);
	}

	/**
	 * Check (default) or import rows. A dry run answers a `review` token; an import that sends it is
	 * refused with 409 `ohmylms_import_review_stale` when the checked result changed since.
	 *
	 * @param WP_REST_Request $request Request with either decoded rows or JSON/CSV text.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function import( WP_REST_Request $request ) {
		$input = $request->get_param( 'rows' );
		if ( null === $input ) {
			$input = (string) $request->get_param( 'text' );
		}
		$rows = BulkImport::parse( $input, (string) ( $request->get_param( 'format' ) ?? 'auto' ) );
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		// A real import must be asked for; the default only checks.
		$dry_run = ! $request->has_param( 'dry_run' ) || rest_sanitize_boolean( $request->get_param( 'dry_run' ) );
		$review  = (string) $request->get_param( 'review' );
		if ( ! $dry_run && '' !== $review ) {
			$check = BulkImport::run( $rows, $this->options( $request, true ) );
			if ( is_wp_error( $check ) ) {
				return $check;
			}
			if ( ! hash_equals( $this->review_token( $rows, $this->options( $request, true ), $check ), $review ) ) {
				return new WP_Error( 'ohmylms_import_review_stale', __( 'The questions or the file changed since the check. Check the file again before importing.', 'ohmylms' ), array( 'status' => 409 ) );
			}
		}
		$options = $this->options( $request, $dry_run );
		$report  = BulkImport::run( $rows, $options );
		if ( is_wp_error( $report ) ) {
			return $report;
		}
		if ( $dry_run ) {
			$report['review'] = $this->review_token( $rows, $options, $report );
		}

		return $this->no_store( rest_ensure_response( $report ) );
	}

	/**
	 * Export selected (`ids`), filtered (`filters`) or one quiz's (`quiz_id`) questions as import
	 * rows (CSV or JSON). Only questions the user may edit are written, because the file holds every
	 * answer key.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function export( WP_REST_Request $request ) {
		$ids     = $request->get_param( 'ids' );
		$filters = $request->get_param( 'filters' );
		$quiz_id = (int) $request->get_param( 'quiz_id' );
		if ( 1 !== count( array_filter( array( null !== $ids, null !== $filters, $quiz_id > 0 ) ) ) ) {
			return new WP_Error( 'ohmylms_question_export_scope', __( 'Choose either selected questions, list filters or a quiz to export.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$skipped  = 0;
		$sections = array();
		$label    = sanitize_text_field( (string) $request->get_param( 'label' ) );
		if ( $quiz_id > 0 ) {
			$ids = array_map( 'intval', array_column( ohmylms_get_quiz( $quiz_id )->get_questions(), 'id' ) );
			if ( ! $ids ) {
				return new WP_Error( 'ohmylms_question_export_empty', __( 'This quiz has no questions.', 'ohmylms' ), array( 'status' => 422 ) );
			}
			$label = '' !== $label ? $label : get_the_title( $quiz_id );
			foreach ( (array) AssessmentSettings::get( $quiz_id )['sections'] as $section ) {
				foreach ( (array) ( $section['questions'] ?? array() ) as $question_id ) {
					$sections[ (int) $question_id ] = (string) ( $section['title'] ?? '' );
				}
			}
		}
		if ( null !== $filters ) {
			$type = (string) ( $filters['type'] ?? '' );
			if ( '' !== $type && ! Registry::get( 'question', $type ) ) {
				return new WP_Error( 'ohmylms_question_invalid_type', __( 'Choose a registered question type.', 'ohmylms' ), array( 'status' => 400 ) );
			}
			$found = Search::query(
				array(
					'status'     => 'active' === ( $filters['status'] ?? 'active' ) ? '' : (string) $filters['status'],
					'type'       => $type,
					'difficulty' => (string) ( $filters['difficulty'] ?? '' ),
					'search'     => (string) ( $filters['search'] ?? '' ),
					'bank'       => isset( $filters['bank'] ) ? (int) $filters['bank'] : null,
					'skill'      => (int) ( $filters['skill'] ?? 0 ),
				),
				1,
				RowExporter::MAX_QUESTIONS + 1,
				'modified',
				'desc'
			);
			if ( is_wp_error( $found ) ) {
				return $found;
			}
			if ( ! $found['ids'] ) {
				return new WP_Error( 'ohmylms_question_export_empty', __( 'No questions match these filters.', 'ohmylms' ), array( 'status' => 422 ) );
			}
			if ( $found['total'] > RowExporter::MAX_QUESTIONS ) {
				/* translators: 1: matching questions, 2: maximum per file. */
				return new WP_Error( 'ohmylms_question_export_too_many', sprintf( __( '%1$d questions match. Narrow the filters to at most %2$d.', 'ohmylms' ), $found['total'], RowExporter::MAX_QUESTIONS ), array( 'status' => 422 ) );
			}
			$ids = $found['ids'];
		}
		$editable = array_values( array_filter( array_map( 'intval', (array) $ids ), array( AccessPolicy::class, 'can_edit_question' ) ) );
		$skipped  = count( (array) $ids ) - count( $editable );
		if ( null !== $request->get_param( 'ids' ) ) {
			foreach ( (array) $ids as $id ) {
				if ( OHMYLMS_QUESTION_CPT !== get_post_type( (int) $id ) || 'trash' === get_post_status( (int) $id ) ) {
					/* translators: %d: question ID. */
					return new WP_Error( 'ohmylms_question_not_found', sprintf( __( 'Question %d does not exist.', 'ohmylms' ), (int) $id ), array( 'status' => 404 ) );
				}
			}
			if ( $skipped ) {
				return new WP_Error( 'ohmylms_question_cannot_export', __( 'You can export only questions you may edit; the file holds their answer keys.', 'ohmylms' ), array( 'status' => 403 ) );
			}
		}
		if ( ! $editable ) {
			return new WP_Error( 'ohmylms_question_export_empty', __( 'None of the matching questions can be exported by you; the file holds their answer keys.', 'ohmylms' ), array( 'status' => 422 ) );
		}
		$result = RowExporter::export( $editable, (string) $request['format'], $label, $sections );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$result['skipped'] = $skipped;

		return $this->no_store( rest_ensure_response( $result ) );
	}
}
