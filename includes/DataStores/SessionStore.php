<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class SessionStore.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\DataStores;

use OhMyLMS\Abstracts\DataStore;
use OhMyLMS\Data\Session;

defined( 'ABSPATH' ) || exit;

/**
 * Class SessionStore
 *
 * @package OhMyLMS\DataStores
 */
class SessionStore extends DataStore {

	/**
	 * Create Session
	 *
	 * @param Session $session Session.
	 * @return void
	 */
	public function create( &$session ) {
		if ( ! $session->get_date_created( 'edit' ) ) {
			$session->set_date_created( time() );
		}
		$id = wp_insert_post(
			array(
				'post_type'     => 'ohmylms-session',
				'post_author'   => get_current_user_id(),
				'post_status'   => $session->get_status() ? $session->get_status() : 'publish',
				'post_title'    => $session->get_name() ? $session->get_name() : __( 'Untitled', 'ohmylms' ),
				'post_content'  => $session->get_description(),
				'post_date'     => gmdate( 'Y-m-d H:i:s', $session->get_date_created( 'edit' ) ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $session->get_date_created( 'edit' ) ),
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			$session->set_id( $id );
			$this->update_session_meta( $session );

			/**
			 * Action fired after a session is created.
			 *
			 * @since 1.0.0
			 */
			do_action( 'ohmylms_session_created', $session );
		}
	}

	/**
	 * Read data
	 *
	 * @param Session $session Session.
	 * @return void
	 */
	public function read( &$session ) {
		$post_object = get_post( $session->get_id() );
		if ( ! $session->get_id() || ! $post_object || 'ohmylms-session' !== $post_object->post_type ) {
			return;
		}
		$session->set_props(
			array(
				'name'          => $post_object->post_title,
				'status'        => $post_object->post_status,
				'date_created'  => $post_object->post_date_gmt,
				'date_modified' => $post_object->post_modified_gmt,
				'description'   => $post_object->post_content,
			)
		);
		$this->read_session_data( $session );
	}

	/**
	 * Update Session data
	 *
	 * @param Session $session Session.
	 * @return void
	 */
	public function update( &$session ) {
		$post_data = array(
			'post_content' => $session->get_description( 'edit' ),
			'post_title'   => $session->get_name( 'edit' ),
			'post_status'  => $session->get_status( 'edit' ) ? $session->get_status( 'edit' ) : 'publish',
			'post_type'    => 'ohmylms-session',
		);
		if ( $session->get_date_created( 'edit' ) ) {
			$post_data['post_date']     = gmdate( 'Y-m-d H:i:s', $session->get_date_created( 'edit' ) );
			$post_data['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $session->get_date_created( 'edit' ) );
		}
		$post_data['post_modified']     = current_time( 'mysql' );
		$post_data['post_modified_gmt'] = current_time( 'mysql', 1 );
		wp_update_post( array_merge( array( 'ID' => $session->get_id() ), $post_data ) );
		$this->update_session_meta( $session );
	}

	/**
	 * Delete a Session.
	 *
	 * @param Session $session Session.
	 * @param array   $args Operation arguments.
	 * @return void
	 */
	public function delete( &$session, $args = array() ) {
		if ( $session ) {
			$session_id = $session->get_id();
			if ( $session_id ) {
				wp_delete_post( $session_id, true );
			}
		}
	}

	/**
	 * Helper function that reads Session data
	 *
	 * @param Session $session Session.
	 * @return void
	 */
	protected function read_session_data( &$session ) {
		// Add meta reading here if needed in the future.
	}

	/**
	 * Helper function that updates Session meta
	 *
	 * @param Session $session Session.
	 * @return void
	 */
	protected function update_session_meta( &$session ) {
		// Add meta updating here if needed in the future.
	}
}
