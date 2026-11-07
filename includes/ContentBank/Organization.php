<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Folders and tags for the reusable content in the Content Bank.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\ContentBank;

use OhMyLMS\Exams\Library;
use WP_Query;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * One folder tree and one flat tag list shared by lessons, quizzes, exams, assignments and questions.
 *
 * Folders and tags only organize authoring screens; learners never see them. Every list endpoint of the
 * plugin can be narrowed with `bank_folder` (a folder and its sub-folders) and `bank_tag`, because the
 * filter is applied to the post query itself rather than to each controller.
 */
final class Organization {

	const FOLDER = 'ohmylms_bank_folder';
	const TAG    = 'ohmylms_bank_tag';

	/**
	 * Filter taken from the REST request being served.
	 *
	 * @var array{folder:int,tag:int}|null
	 */
	private static $scope = null;

	/**
	 * Content types that can be organized, keyed by the name the REST API uses.
	 *
	 * @return array<string,string>
	 */
	public static function types() {
		return array(
			'lesson'     => OHMYLMS_LESSON_CPT,
			'quiz'       => OHMYLMS_QUIZ_CPT,
			'exam'       => Library::TYPE,
			'assignment' => OHMYLMS_ASSIGNMENT_CPT,
			'question'   => OHMYLMS_QUESTION_CPT,
		);
	}

	/**
	 * Hook registration and list filtering.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 12 );
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'capture' ), 10, 3 );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'release' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'narrow' ) );
	}

	/**
	 * Register the folder and tag taxonomies on every organizable type.
	 *
	 * @return void
	 */
	public static function register() {
		$capabilities = array(
			'manage_terms' => 'edit_posts',
			'edit_terms'   => 'edit_posts',
			'delete_terms' => 'manage_options',
			'assign_terms' => 'edit_posts',
		);
		$shared       = array(
			'public'            => false,
			'show_ui'           => false,
			'show_in_rest'      => false,
			'show_admin_column' => false,
			'rewrite'           => false,
			'query_var'         => false,
			'capabilities'      => $capabilities,
		);
		register_taxonomy(
			self::FOLDER,
			array_values( self::types() ),
			$shared + array(
				'hierarchical' => true,
				'labels'       => array(
					'name'          => __( 'Content folders', 'ohmylms' ),
					'singular_name' => __( 'Content folder', 'ohmylms' ),
				),
			)
		);
		register_taxonomy(
			self::TAG,
			array_values( self::types() ),
			$shared + array(
				'hierarchical' => false,
				'labels'       => array(
					'name'          => __( 'Content tags', 'ohmylms' ),
					'singular_name' => __( 'Content tag', 'ohmylms' ),
				),
			)
		);
	}

	/**
	 * Remember `bank_folder` and `bank_tag` of a plugin REST request.
	 *
	 * @param mixed           $response Response so far, passed through.
	 * @param array           $handler  Matched route handler.
	 * @param WP_REST_Request $request  Request.
	 * @return mixed
	 */
	public static function capture( $response, $handler, $request ) {
		unset( $handler );
		self::$scope = null;
		if ( 0 !== strpos( $request->get_route(), '/ohmylms/v1/' ) || 'GET' !== $request->get_method() ) {
			return $response;
		}
		$folder = absint( $request->get_param( 'bank_folder' ) );
		$tag    = absint( $request->get_param( 'bank_tag' ) );
		if ( $folder || $tag ) {
			self::$scope = array(
				'folder' => $folder,
				'tag'    => $tag,
			);
		}
		return $response;
	}

	/**
	 * Forget the filter once the request is answered, so batched requests do not share it.
	 *
	 * @param mixed $response Response, passed through.
	 * @return mixed
	 */
	public static function release( $response ) {
		self::$scope = null;
		return $response;
	}

	/**
	 * Limit a query for organizable content to the requested folder and tag.
	 *
	 * @param WP_Query $query Query about to run.
	 * @return void
	 */
	public static function narrow( $query ) {
		if ( null === self::$scope ) {
			return;
		}
		$types = (array) $query->get( 'post_type' );
		if ( ! $types || array_diff( $types, array_values( self::types() ) ) ) {
			return;
		}
		$clauses = (array) $query->get( 'tax_query' );
		foreach ( array(
			'folder' => self::FOLDER,
			'tag'    => self::TAG,
		) as $key => $taxonomy ) {
			if ( self::$scope[ $key ] ) {
				$clauses[] = array(
					'taxonomy'         => $taxonomy,
					'field'            => 'term_id',
					'terms'            => array( self::$scope[ $key ] ),
					'include_children' => self::FOLDER === $taxonomy,
				);
			}
		}
		$query->set( 'tax_query', $clauses );
	}
}
