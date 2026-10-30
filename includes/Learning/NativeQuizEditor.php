<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Native Gutenberg quiz documents backed by the existing question bank.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Learning;

use OhMyLMS\Assessment\ErrorException;
use OhMyLMS\QuestionBank\AccessPolicy;
use OhMyLMS\QuestionBank\DraftWriter;
use OhMyLMS\QuestionBank\Usage;
use OhMyLMS\QuestionBank\VersionPublisher;

defined( 'ABSPATH' ) || exit;

/** Keep native block documents and the existing quiz placements in agreement. */
final class NativeQuizEditor {
	const QUESTION_BLOCK  = 'ohmylms/shared-multiple-choice';
	const REFERENCE_BLOCK = 'ohmylms/quiz-question-reference';

	/** Register additive native authoring integration. */
	public static function init() {
		add_filter( 'ohmylms_rest_prepare_quiz', array( __CLASS__, 'editor_link' ), 10, 2 );
		add_filter( 'rest_prepare_post_type', array( __CLASS__, 'editor_viewability' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'register_reference' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_settings' ) );
		add_filter( 'allowed_block_types_all', array( __CLASS__, 'quiz_blocks' ), 10, 2 );
	}

	/**
	 * Keep the quiz editor's block inserter to what a quiz uses. Site blocks of the plugin (registration,
	 * sign in, dashboards, checkout, buy buttons, membership lists) do not belong in a quiz and buried the
	 * question block; text, media and question blocks stay.
	 *
	 * @param bool|string[]            $allowed Allowed block names, or true for all.
	 * @param \WP_Block_Editor_Context $context Editor context.
	 * @return bool|string[]
	 */
	public static function quiz_blocks( $allowed, $context ) {
		$post = isset( $context->post ) ? $context->post : null;
		if ( ! $post || 'ohmylms-quiz' !== $post->post_type || false === $allowed ) {
			return $allowed;
		}
		// Question blocks always; the question player (practice runs) only when the kind allows that delivery.
		$keep = array_merge( array( self::QUESTION_BLOCK, self::REFERENCE_BLOCK ), \OhMyLMS\Exams\PaperBlocks::names() );
		if ( \OhMyLMS\Assessment\Profiles::allows( $post->ID, \OhMyLMS\Assessment\DeliveryPolicy::PRACTICE ) ) {
			$keep[] = 'ohmylms/question-player';
		}
		$names = array();
		foreach ( \WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $type ) {
			if ( 0 === strpos( $name, 'ohmylms/' ) && ! in_array( $name, $keep, true ) && false === strpos( $name, 'math' ) ) {
				continue;
			}
			$names[] = $name;
		}
		return is_array( $allowed ) ? array_values( array_intersect( $allowed, $names ) ) : $names;
	}

	/** Register quiz configuration on the atomic native post save. */
	public static function register_settings() {
		register_rest_field(
			'ohmylms-quiz',
			'ohmylms_authoring_revision',
			array(
				'get_callback'    => static function ( $data ) {
					return (int) get_post_meta( $data['id'], '_native_authoring_revision', true ); },
				'update_callback' => static function ( $value, $post ) {
					if ( ! AccessPolicy::can_edit_quiz( $post->ID ) ) {
						return AccessPolicy::denied(); }
					update_post_meta( $post->ID, '_native_authoring_revision', absint( $value ) );
					return true;
				},
				'schema'          => array(
					'type'    => 'integer',
					'minimum' => 0,
					'context' => array( 'edit' ),
				),
			)
		);
		register_rest_field(
			'ohmylms-quiz',
			'ohmylms_quiz',
			array(
				'get_callback'    => array( __CLASS__, 'read_settings' ),
				'update_callback' => array( __CLASS__, 'save_settings' ),
				'schema'          => array(
					'type'                 => 'object',
					'context'              => array( 'edit' ),
					'properties'           => array(
						'assessment'    => array(
							'type'                 => 'object',
							'additionalProperties' => true,
						),
						'settings'      => array(
							'type'                 => 'object',
							'additionalProperties' => true,
						),
						'drip_settings' => array(
							'type'                 => 'object',
							'additionalProperties' => true,
						),
						'skill_ids'     => array(
							'type'  => 'array',
							'items' => array(
								'type'    => 'integer',
								'minimum' => 1,
							),
						),
					),
					'additionalProperties' => false,
				),
			)
		);
	}

	/**
	 * Read the existing storage without introducing a parallel settings model.
	 *
	 * @param array $data Core post data.
	 * @return array Quiz configuration.
	 */
	public static function read_settings( $data ) {
		$id = (int) $data['id'];
		return array(
			'assessment'    => \OhMyLMS\Assessment\AssessmentSettings::get( $id ),
			'settings'      => (object) (array) get_post_meta( $id, '_quiz_settings', true ),
			'drip_settings' => (object) (array) get_post_meta( $id, '_drip_settings', true ),
			'skill_ids'     => \OhMyLMS\Assessment\Schema::ready() ? \OhMyLMS\Quiz\QuizSkills::get( $id ) : array(),
		);
	}

	/**
	 * Save submitted configuration inside NativeQuizController's transaction.
	 * Unknown extension settings survive edits to known fields.
	 *
	 * @param array    $value Configuration.
	 * @param \WP_Post $post Quiz post.
	 * @return true|\WP_Error Save result.
	 */
	public static function save_settings( $value, $post ) {
		if ( ! AccessPolicy::can_edit_quiz( $post->ID ) ) {
			return AccessPolicy::denied();
		}
		if ( isset( $value['settings']['presentation'] ) && ! in_array( $value['settings']['presentation'], array( 'standard', 'paper' ), true ) ) {
			return new \WP_Error( 'ohmylms_presentation_invalid', __( 'Choose a valid student presentation.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$value = NativeLessonEditor::sanitize_setting( $value );
		if ( isset( $value['settings']['paper'] ) ) {
			$value['settings']['paper'] = \OhMyLMS\Exams\PaperSettings::sanitize( $value['settings']['paper'] );
		}
		foreach ( array(
			'settings'      => '_quiz_settings',
			'drip_settings' => '_drip_settings',
		) as $field => $key ) {
			if ( isset( $value[ $field ] ) ) {
				update_post_meta( $post->ID, $key, (array) $value[ $field ] );
			}
		}
		if ( isset( $value['skill_ids'] ) ) {
			$result = \OhMyLMS\Quiz\QuizSkills::set( $post->ID, $value['skill_ids'] );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		return true;
	}

	/**
	 * Save assessment rules after the document's question placements are synchronized.
	 *
	 * @param int   $quiz_id Quiz ID.
	 * @param array $value Native quiz configuration.
	 * @return true|\WP_Error Save result.
	 */
	public static function save_assessment( $quiz_id, array $value ) {
		if ( ! isset( $value['assessment'] ) ) {
			return true;
		}
		if ( ! AccessPolicy::can_edit_quiz( $quiz_id ) ) {
			return AccessPolicy::denied();
		}
		$assessment = $value['assessment'];
		if ( ! is_array( $assessment ) ) {
			return new \WP_Error( 'ohmylms_assessment_settings_invalid', __( 'Assessment settings must be an object.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		if ( \OhMyLMS\Exams\Composer::exam_for_quiz( $quiz_id ) && isset( $assessment['kind'] ) && 'exam' !== $assessment['kind'] ) {
			return new \WP_Error( 'ohmylms_exam_kind', __( 'This assessment belongs to an exam. Its assessment type must remain Exam.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		$result = \OhMyLMS\Assessment\AssessmentSettings::save( $quiz_id, $assessment );
		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * Omit standalone page preview actions from Gutenberg for quiz documents.
	 *
	 * Quiz previews use the existing author-only player. This REST metadata does
	 * not change the public routes used by course learners.
	 *
	 * @param \WP_REST_Response $response Post type metadata.
	 * @param \WP_Post_Type     $post_type Registered post type.
	 * @return \WP_REST_Response Native editor metadata.
	 */
	public static function editor_viewability( $response, $post_type ) {
		if ( 'ohmylms-quiz' === $post_type->name ) {
			$data = $response->get_data();
			if ( array_key_exists( 'viewable', $data ) ) {
				$data['viewable'] = false;
				$response->set_data( $data );
			}
		}
		return $response;
	}

	/** Register a movable reference for established question formats outside this prototype. */
	public static function register_reference() {
		register_block_type(
			self::REFERENCE_BLOCK,
			array(
				'api_version'     => 3,
				'attributes'      => array(
					'questionId'        => array( 'type' => 'integer' ),
					'authoringRevision' => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'delivery'          => array(
						'type'    => 'string',
						'default' => 'quiz',
					),
					'uuid'              => array( 'type' => 'string' ),
				),
				'render_callback' => '__return_empty_string',
			)
		);
	}

	/**
	 * The LMS quiz screen beside Gutenberg: the new app's when it replaces the legacy one.
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return string Authoring URL.
	 */
	public static function form_url( $quiz_id ) {
		return \OhMyLMS\Admin\LegacyRoutes::url( '/quiz-edit/' . absint( $quiz_id ) );
	}

	/**
	 * Expose native editing only to the quiz's editors.
	 *
	 * @param \WP_REST_Response $response Existing quiz response.
	 * @param \WP_Post          $post Quiz post.
	 * @return \WP_REST_Response Response with additive navigation metadata.
	 */
	public static function editor_link( $response, $post ) {
		if ( AccessPolicy::can_edit_quiz( $post->ID ) ) {
			$data                      = $response->get_data();
			$data['native_editor_url'] = (string) get_edit_post_link( $post->ID, 'raw' );
			$response->set_data( $data );
		}
		return $response;
	}

	/**
	 * Recognize only the registered question references, including compatibility blocks.
	 *
	 * @param array $block Parsed block.
	 * @return bool Whether this is a question placement.
	 */
	public static function is_question( $block ) {
		return in_array( $block['blockName'] ?? '', array( self::QUESTION_BLOCK, self::REFERENCE_BLOCK ), true );
	}

	/**
	 * Validate a block document and keep answer keys out of post content.
	 *
	 * @param string $content Submitted Gutenberg document.
	 * @return array|\WP_Error Normalized content and ordered question IDs.
	 */
	public static function prepare_document( $content ) {
		$ids = array();
		try {
			$blocks = self::normalize( parse_blocks( $content ), $ids );
			return array(
				'content' => serialize_blocks( $blocks ),
				'ids'     => $ids,
			);
		} catch ( ErrorException $error ) {
			return $error->error;
		}
	}

	/**
	 * Resolve references through existing bank permissions before any post is saved.
	 *
	 * @param array $blocks Parsed blocks.
	 * @param array $ids Ordered references, collected recursively.
	 * @return array Normalized blocks.
	 * @throws ErrorException When a reference is incomplete, duplicated or unauthorized.
	 */
	private static function normalize( $blocks, &$ids ) {
		foreach ( $blocks as &$block ) {
			if ( self::is_question( $block ) ) {
				$id = absint( $block['attrs']['questionId'] ?? 0 );
				if ( ! $id || in_array( $id, $ids, true ) ) {
					ErrorException::raise( new \WP_Error( 'ohmylms_quiz_block_reference', __( 'Save each question before saving the quiz. Each question can appear only once; add a new question block for another question.', 'ohmylms' ), array( 'status' => 400 ) ) );
				}
				if ( ! AccessPolicy::can_use_question( $id ) ) {
					ErrorException::raise( AccessPolicy::denied() );
				}
				$identity = VersionPublisher::identity( $id, false );
				if ( ! AccessPolicy::can_edit_question( $id ) && empty( $identity['approved_version_id'] ) ) {
					ErrorException::raise( new \WP_Error( 'quiz_question_unapproved', __( 'Shared questions must be approved before they can be used in a quiz.', 'ohmylms' ), array( 'status' => 409 ) ) );
				}
				$authoring_revision = absint( $block['attrs']['authoringRevision'] ?? 0 );
				$ids[]              = $id;
				$span               = ! empty( $block['attrs'][ \OhMyLMS\Exams\PaperBlocks::SPAN ] );
				$block['attrs']     = array(
					'questionId' => $id,
					'uuid'       => (string) ( $identity['uuid'] ?? '' ),
				);
				if ( $span ) {
					// Printable paper layout: this question spans every column of its section.
					$block['attrs'][ \OhMyLMS\Exams\PaperBlocks::SPAN ] = true;
				}
				$block['innerBlocks']  = array();
				$block['innerHTML']    = '';
				$block['innerContent'] = array();
				if ( self::is_question( $block ) ) {
					$block['attrs']['delivery']          = 'quiz';
					$block['attrs']['authoringRevision'] = $authoring_revision;
				}
			} else {
				$block['innerBlocks'] = self::normalize( $block['innerBlocks'] ?? array(), $ids );
			}
		}
		unset( $block );
		return $blocks;
	}

	/**
	 * Collect reference IDs without grading or creating learner records.
	 *
	 * @param array $blocks Parsed blocks.
	 * @return int[] Ordered question IDs.
	 */
	private static function reference_ids( $blocks ) {
		$ids = array();
		foreach ( $blocks as $block ) {
			if ( self::is_question( $block ) ) {
				$ids[] = absint( $block['attrs']['questionId'] ?? 0 );
			} else {
				$ids = array_merge( $ids, self::reference_ids( $block['innerBlocks'] ?? array() ) );
			}
		}
		return $ids;
	}

	/**
	 * Reconcile placements in their existing layout slots, preserving surrounding core blocks.
	 *
	 * @param array $blocks Parsed document.
	 * @param array $questions Authoritative ordered placements.
	 * @param int   $position Next placement to insert.
	 * @return array Reconciled document.
	 */
	private static function reconcile_blocks( $blocks, $questions, &$position ) {
		$result = array();
		foreach ( $blocks as $block ) {
			if ( self::is_question( $block ) ) {
				if ( isset( $questions[ $position ] ) ) {
					$result[] = self::question_block( $questions[ $position++ ] );
				}
				continue;
			}
			$children      = $block['innerBlocks'] ?? array();
			$next_children = array();
			$next_content  = array();
			$child_index   = 0;
			foreach ( $block['innerContent'] ?? array() as $chunk ) {
				if ( null !== $chunk ) {
					$next_content[] = $chunk;
					continue;
				}
				$child = $children[ $child_index++ ] ?? null;
				if ( ! $child ) {
					continue;
				}
				foreach ( self::reconcile_blocks( array( $child ), $questions, $position ) as $next ) {
					$next_children[] = $next;
					$next_content[]  = null;
				}
			}
			$block['innerBlocks']  = $next_children;
			$block['innerContent'] = $next_content;
			$result[]              = $block;
		}
		return $result;
	}

	/**
	 * Create a reference-only block for an existing question.
	 *
	 * @param array $question Existing question.
	 * @return array Parsed block.
	 */
	private static function question_block( $question ) {
		$available = (bool) \OhMyLMS\Extensions\Registry::get( 'question', $question['settings']['type'] ?? '' );
		return array(
			'blockName'    => $available ? self::QUESTION_BLOCK : self::REFERENCE_BLOCK,
			'attrs'        => array(
				'questionId' => (int) $question['id'],
				'uuid'       => (string) ( $question['uuid'] ?? '' ),
				'delivery'   => 'quiz',
			),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);
	}

	/**
	 * Open old quizzes in Gutenberg without losing existing question formats or placements.
	 *
	 * @param \WP_Post $post Quiz post.
	 * @return string Editor document, synthesized without changing the stored post.
	 */
	public static function editor_content( $post ) {
		$questions = ohmylms_get_quiz( $post->ID )->get_questions();
		$ids       = array_map( 'intval', array_column( $questions, 'id' ) );
		$blocks    = parse_blocks( $post->post_content );
		if ( self::reference_ids( $blocks ) === $ids ) {
			return $post->post_content;
		}
		$position = 0;
		$blocks   = self::reconcile_blocks( $blocks, $questions, $position );
		while ( isset( $questions[ $position ] ) ) {
			$blocks[] = self::question_block( $questions[ $position++ ] );
		}
		$content = serialize_blocks( $blocks );
		return $content;
	}

	/**
	 * Sync only quiz placements, using the existing writer and removal policy.
	 *
	 * @param int   $quiz_id Quiz ID.
	 * @param int[] $ids Ordered question IDs.
	 * @throws ErrorException When an existing placement cannot be removed.
	 */
	public static function sync( $quiz_id, $ids ) {
		$existing = array_map( 'intval', array_column( ohmylms_get_quiz( $quiz_id )->get_questions(), 'id' ) );
		foreach ( array_diff( $existing, $ids ) as $id ) {
			ErrorException::raise( Usage::remove_from_quiz( $quiz_id, $id ) );
		}
		foreach ( $ids as $index => $id ) {
			DraftWriter::link( $quiz_id, $id, $index + 1 );
		}
	}
}
