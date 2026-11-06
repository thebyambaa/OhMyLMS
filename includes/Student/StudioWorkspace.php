<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Studio workspace: the staff screens inside the hub for owners, school administrators and authors.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

use OhMyLMS\Dashboard\Front;

defined( 'ABSPATH' ) || exit;

/** Which staff sections a person has, and where they live in the hub. */
final class StudioWorkspace {

	/**
	 * Hub section => app section and label, in the order they are offered. A section appears only when
	 * the person's dashboard scope includes the app section it needs.
	 */
	private static function sections() {
		return array(
			'studio-home'     => array( 'home', __( 'Overview', 'ohmylms' ), 'home' ),
			'studio-schools'  => array( 'schools', __( 'Schools', 'ohmylms' ), 'schools' ),
			'studio-classes'  => array( 'classes', __( 'Classes', 'ohmylms' ), 'classes' ),
			'studio-people'   => array( 'people', __( 'People', 'ohmylms' ), 'people' ),
			'studio-courses'  => array( 'courses', __( 'Courses', 'ohmylms' ), 'content' ),
			'studio-quizzes'  => array( 'quizzes', __( 'Quizzes & exams', 'ohmylms' ), 'content' ),
			'studio-skills'   => array( 'skills', __( 'Skills', 'ohmylms' ), 'content' ),
			'studio-content'  => array( 'content', __( 'Lessons & content', 'ohmylms' ), 'content' ),
			'studio-settings' => array( 'settings', __( 'Branding & settings', 'ohmylms' ), 'settings' ),
			'studio-design'   => array( 'showcase', __( 'Design system', 'ohmylms' ), 'owner' ),
		);
	}

	/**
	 * Sections this person may open, keyed by hub section.
	 *
	 * @return array<string,array{0:string,1:string}> Hub section => app section, label.
	 */
	public static function tabs() {
		if ( ! is_user_logged_in() ) {
			return array();
		}
		$nav  = Front::scope()['nav'];
		$tabs = array();
		foreach ( self::sections() as $key => $row ) {
			// The design-system reference is for site owners only.
			$allowed = 'owner' === $row[2] ? current_user_can( 'manage_options' ) : in_array( $row[2], $nav, true );
			if ( $allowed ) {
				$tabs[ $key ] = array( $row[0], $row[1] );
			}
		}
		return $tabs;
	}

	/**
	 * Whether the person has any staff section.
	 *
	 * @return bool
	 */
	public static function available() {
		return (bool) self::tabs();
	}

	/**
	 * Bookmarkable destination.
	 *
	 * @param string $section Hub section; the first one the person has when empty.
	 * @param string $view    Unused; kept so every workspace has the same signature.
	 * @return string
	 */
	public static function url( $section = '', $view = '' ) {
		unset( $view );
		$tabs    = self::tabs();
		$section = isset( $tabs[ $section ] ) ? $section : (string) array_key_first( $tabs );
		return add_query_arg( 'hub_role', 'studio', Hub::url( $section ) );
	}
}
