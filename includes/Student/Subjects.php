<?php // phpcs:ignore WordPress.Files.FileName -- Required Composer PSR-4 class/file mapping.
/**
 * Public subject pages over curriculum placement and published course outlines.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\SubjectCourses;

defined( 'ABSPATH' ) || exit;

/** Subjects organize browsing; course access and enrollment policies stay with each course. */
final class Subjects {

	/** Register public routes and a shortcode, without creating WordPress pages. */
	public static function init() {
		add_action( 'ohmylms_curriculum_items_deleted', array( SubjectCourses::class, 'deleted' ) );
		add_filter(
			'query_vars',
			static function ( $vars ) {
				$vars[] = 'ohmylms_subject';
				$vars[] = 'hub_subject';
				return $vars;
			}
		);
		add_action( 'template_redirect', array( __CLASS__, 'page' ), 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_shortcode( 'ohmylms_subjects', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Subject URL for visitors, or the current learner workspace.
	 *
	 * @param int  $subject_id Subject ID, zero for the index.
	 * @param bool $workspace Use the learner workspace.
	 * @return string
	 */
	public static function url( $subject_id = 0, $workspace = false ) {
		return $workspace && Hub::base_url() ? add_query_arg( 'hub_subject', $subject_id, Hub::url( 'library' ) ) : add_query_arg( 'ohmylms_subject', $subject_id ? $subject_id : 'all', home_url( '/' ) );
	}

	/**
	 * Published subjects only, preserving existing curriculum hierarchy.
	 *
	 * @return array
	 */
	public static function all() {
		return LearnerCatalog::subjects( '', array( 'subject' ) );
	}

	/**
	 * Find a subject among public nodes, including a nested subject.
	 *
	 * @param int   $id Subject ID.
	 * @param array $nodes Public nodes.
	 * @return array|null
	 */
	private static function find( $id, array $nodes ) {
		foreach ( $nodes as $node ) {
			if ( (int) $id === $node['id'] ) {
				return $node;
			}
			$found = self::find( $id, $node['children'] );
			if ( $found ) {
				return $found;
			}
		}
		return null;
	}

	/**
	 * Public subject and its ordered courses. No drafts/private/roadmap records are returned.
	 *
	 * @param int $subject_id Subject ID.
	 * @return array|\WP_Error
	 */
	public static function get( $subject_id ) {
		$item = Items::get( $subject_id );
		$node = $item && 'subject' === $item['item_type'] ? self::find( $subject_id, self::all() ) : null;
		if ( ! $node ) {
			return new \WP_Error( 'ohmylms_subject_missing', __( 'Subject not available.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$node['description'] = wp_strip_all_tags( (string) $item['description'] );
		$node['course_ids']  = SubjectCourses::ordered( $subject_id, LearnerCatalog::course_ids( $node ) );
		return $node;
	}

	/** Load scoped styles on subject pages and learner subject detail views. */
	public static function assets() {
		if ( '' !== get_query_var( 'ohmylms_subject', '' ) || absint( get_query_var( 'hub_subject', 0 ) ) ) {
			self::enqueue();
		}
	}

	/** Enqueue the reusable subject card styles. */
	private static function enqueue() {
		wp_enqueue_style( 'ohmylms-subjects', plugins_url( 'assets/css/subjects.css', OHMYLMS_FILE ), array(), OHMYLMS_VERSION . '.' . filemtime( OHMYLMS_DIR . '/assets/css/subjects.css' ) );
	}

	/**
	 * Render the index or one subject, reusing the same template in the learner hub.
	 *
	 * @param int    $subject_id Subject ID, zero for index.
	 * @param bool   $workspace Keep subject links inside the hub.
	 * @param string $base_url Page that embeds the browser; subject links then stay on it with `oml_subject`.
	 * @return string
	 */
	public static function render( $subject_id = 0, $workspace = false, $base_url = '' ) {
		self::enqueue();
		$subject           = $subject_id ? self::get( $subject_id ) : null;
		$subjects          = self::all();
		$subject_index_url = $base_url ? remove_query_arg( 'oml_subject', $base_url ) : self::url( 0, $workspace );
		$subject_link      = static function ( $id ) use ( $workspace, $base_url, $subject_index_url ) {
			return $base_url ? add_query_arg( 'oml_subject', absint( $id ), $subject_index_url ) : self::url( $id, $workspace );
		};
		ob_start();
		include OHMYLMS_DIR . '/templates/subjects/browser.php';
		return ob_get_clean();
	}

	/**
	 * Render the Explore menu: one column per subject, listing its nested subjects (or first courses).
	 *
	 * @param string $base_url Page that embeds the menu; subject links stay on it with `oml_subject`.
	 * @param bool   $dropdown Put the columns behind an Explore button.
	 * @param array  $options  `full` opens the dropdown across the whole browser width; `label` is the button text.
	 * @return string
	 */
	public static function explore( $base_url = '', $dropdown = false, array $options = array() ) {
		self::enqueue();
		$full  = $dropdown && ! empty( $options['full'] );
		$label = isset( $options['label'] ) && '' !== $options['label'] ? $options['label'] : __( 'Explore', 'ohmylms' );
		if ( $dropdown ) {
			wp_enqueue_script( 'ohmylms-explore-menu', plugins_url( 'assets/js/explore-menu.js', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/js/explore-menu.js' ), true );
		}
		$index_url    = $base_url ? remove_query_arg( 'oml_subject', $base_url ) : self::url();
		$subject_link = static function ( $id ) use ( $base_url, $index_url ) {
			return $base_url ? add_query_arg( 'oml_subject', absint( $id ), $index_url ) : self::url( $id );
		};
		$columns      = array();
		foreach ( self::all() as $node ) {
			$links = array();
			foreach ( $node['children'] as $child ) {
				$links[] = array(
					'label' => $child['name'],
					'url'   => $subject_link( $child['id'] ),
				);
			}
			if ( ! $links ) {
				foreach ( $node['courses'] as $course ) {
					$links[] = array(
						'label' => $course['title'],
						'url'   => add_query_arg( 'hub_subject', $node['id'], get_permalink( $course['id'] ) ),
					);
				}
			}
			$columns[] = array(
				'id'    => $node['id'],
				'name'  => $node['name'],
				'url'   => $subject_link( $node['id'] ),
				'links' => array_slice( $links, 0, 8 ),
				'total' => (int) $node['total'],
			);
		}
		ob_start();
		include OHMYLMS_DIR . '/templates/subjects/explore.php';
		return ob_get_clean();
	}

	/**
	 * Render shortcode attributes as subject navigation.
	 *
	 * @param array $attributes Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( $attributes ) {
		$attributes = shortcode_atts( array( 'id' => 0 ), $attributes, 'ohmylms_subjects' );
		return self::render( absint( $attributes['id'] ) );
	}

	/** Render an ordinary public page with the WordPress head/footer integrations. */
	public static function page() {
		$value = get_query_var( 'ohmylms_subject', '' );
		if ( '' === $value ) {
			return;
		}
		$valid   = is_scalar( $value ) && ( 'all' === $value || preg_match( '/^[1-9][0-9]*$/D', (string) $value ) );
		$id      = $valid && 'all' !== $value ? absint( $value ) : 0;
		$invalid = ! $valid;
		$subject = $id ? self::get( $id ) : null;
		$title   = $invalid || is_wp_error( $subject ) ? __( 'Subject not available', 'ohmylms' ) : ( $subject ? $subject['name'] : __( 'Browse subjects', 'ohmylms' ) );
		add_filter(
			'pre_get_document_title',
			static function () use ( $title ) {
				return $title . ' — ' . get_bloginfo( 'name' );
			}
		);
		status_header( $invalid || is_wp_error( $subject ) ? 404 : 200 );
		nocache_headers();
		$content = $invalid ? self::render( -1 ) : self::render( $id );
		include OHMYLMS_DIR . '/templates/subjects/page.php';
		exit;
	}
}
