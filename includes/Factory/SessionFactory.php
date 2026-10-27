<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Factory;

use OhMyLMS\Data\Session;

/**
 * Plugin session factory implementation.
 */
class SessionFactory {

	/**
	 * Get session object
	 *
	 * @param bool $session_id Session identifier.
	 * @return bool|Session
	 * @throws \Exception When the operation cannot be completed.*/
	public function get_session( $session_id = false ) {
		$session_id = $this->get_session_id( $session_id );
		if ( ! $session_id ) {
			return false;
		}
		return new Session( $session_id );
	}

	/**
	 * Get session id
	 *
	 * @param mixed $session Session.
	 * @return bool|int
	 */
	private function get_session_id( $session ) {
		global $post;
		if ( false === $session && isset( $post, $post->ID ) && 'ohmylms-session' === get_post_type( $post->ID ) ) {
			return absint( $post->ID );
		} elseif ( is_numeric( $session ) ) {
			return $this->is_session_exist( $session ) ? $session : false;
		} elseif ( $session instanceof Session ) {
			$id = $session->get_id();
			return $this->is_session_exist( $id ) ? $id : false;
		} elseif ( ! empty( $session->ID ) ) {
			return $this->is_session_exist( $session->ID ) ? $session->ID : false;
		} else {
			return false;
		}
	}

	/**
	 * Checks whether a session with the given ID exists.
	 *
	 * @param int $session_id The ID of the session to check.
	 * @return bool Returns true if the session exists, otherwise false.
	 */
	public function is_session_exist( $session_id ) {
		if ( ! $session_id ) {
			return false;
		}
		$session = get_post( $session_id );
		if ( $session && 'ohmylms-session' === get_post_type( $session_id ) ) {
			return true;
		} else {
			return false;
		}
	}
}
