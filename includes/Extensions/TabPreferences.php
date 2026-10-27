<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Personal navigation layouts, stored with the current WordPress account.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Extensions;

/** Personal navigation layouts, stored with the current WordPress account. */
final class TabPreferences {
	private const META_KEY = '_ohmylms_tab_preferences';
	private const SCOPES   = array( 'content-hub', 'memberships', 'account-hub', 'gamification', 'gamification-settings', 'settings', 'emails' );

	/**
	 * Register routes for Tab preferences.
	 */
	public static function register_routes() {
		register_rest_route(
			'ohmylms/v1',
			'/tab-preferences/(?P<scope>[a-z-]+)',
			array(
				'methods'             => 'PUT',
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' ) || current_user_can( 'manage_ohmylms' ); },
				'callback'            => array( __CLASS__, 'save' ),
			)
		);
	}

	/**
	 * Read for Tab preferences.
	 */
	public static function read() {
		$saved = get_user_meta( get_current_user_id(), self::META_KEY, true );
		return is_array( $saved ) ? array_intersect_key( $saved, array_flip( self::SCOPES ) ) : array();
	}

	/**
	 * Prepare for Tab preferences.
	 *
	 * @param mixed $data Input data.
	 */
	public static function prepare( $data ) {
		if ( ! is_array( $data ) || ! isset( $data['order'], $data['groups'], $data['assignments'] ) || ! is_array( $data['order'] ) || ! is_array( $data['groups'] ) || ! is_array( $data['assignments'] ) || count( $data['order'] ) > 60 || count( $data['groups'] ) > 30 ) {
			return new \WP_Error( 'ohmylms_invalid_tabs', 'Invalid tab layout.', array( 'status' => 400 ) );
		}
		$valid_id = static function ( $id ) {
			return is_string( $id ) && preg_match( '/^[a-zA-Z0-9_-]{1,80}$/D', $id );
		};
		$order    = array();
		foreach ( $data['order'] as $id ) {
			if ( ! $valid_id( $id ) ) {
				return new \WP_Error( 'ohmylms_invalid_tabs', 'Invalid tab ID.', array( 'status' => 400 ) );
			}
			if ( ! in_array( $id, $order, true ) ) {
				$order[] = $id;
			}
		}
		$groups    = array();
		$group_ids = array();
		foreach ( $data['groups'] as $group ) {
			if ( ! is_array( $group ) || ! $valid_id( $group['id'] ?? null ) || in_array( $group['id'], $group_ids, true ) || ! is_string( $group['name'] ?? null ) || strlen( $group['name'] ) > 320 || ! is_string( $group['background'] ?? null ) || ! is_string( $group['text'] ?? null ) || ! preg_match( '/^#[0-9a-f]{6}$/iD', $group['background'] ) || ! preg_match( '/^#[0-9a-f]{6}$/iD', $group['text'] ) ) {
				return new \WP_Error( 'ohmylms_invalid_tabs', 'Invalid tab group.', array( 'status' => 400 ) );
			}
			$group_ids[] = $group['id'];
			$groups[]    = array(
				'id'         => $group['id'],
				'name'       => sanitize_text_field( $group['name'] ),
				'background' => $group['background'],
				'text'       => $group['text'],
				'collapsed'  => ! empty( $group['collapsed'] ),
			);
		}
		$assignments = array();
		foreach ( $order as $id ) {
			$group = $data['assignments'][ $id ] ?? null;
			if ( is_string( $group ) && in_array( $group, $group_ids, true ) ) {
				$assignments[ $id ] = $group;
			}
		}
		return array(
			'order'       => $order,
			'groups'      => $groups,
			'assignments' => $assignments,
		);
	}

	/**
	 * Save for Tab preferences.
	 *
	 * @param mixed $request REST request.
	 */
	public static function save( $request ) {
		if ( ! in_array( $request['scope'], self::SCOPES, true ) ) {
			return new \WP_Error( 'ohmylms_invalid_tab_scope', 'Unknown tab section.', array( 'status' => 400 ) );
		}
		$layout = self::prepare( $request->get_json_params() );
		if ( is_wp_error( $layout ) ) {
			return $layout;
		}
		$saved                      = self::read();
		$saved[ $request['scope'] ] = $layout;
		// update_user_meta returns false for unchanged data as well as failures.
		if ( ! update_user_meta( get_current_user_id(), self::META_KEY, wp_slash( $saved ) ) && self::read() !== $saved ) {
			return new \WP_Error( 'ohmylms_tab_save_failed', 'Tab layout could not be saved.', array( 'status' => 500 ) );
		}
		return rest_ensure_response( $layout );
	}
}
