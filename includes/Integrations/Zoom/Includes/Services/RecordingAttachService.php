<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * RecordingAttachService class.
 *
 * Attaches the cloud recording of a finished Zoom meeting to the session lesson that stores the meeting,
 * for the `recording.completed` webhook (`Rest\ZoomWebhookController`, which verifies the signature first).
 *
 * @package ohmylms-pro
 * @since 1.0.0
 */

namespace OhMyLMS\Integrations\Zoom\Includes\Services;

/**
 * Class RecordingAttachService
 *
 * @package OhMyLMS\Integrations\Zoom\Services
 * @since 1.0.0
 */
class RecordingAttachService {

	/**
	 * Find the matching session lesson and attach the cloud recording.
	 *
	 * @since 1.0.0
	 *
	 * @param array $meeting_object The webhook `payload.object` (the Zoom meeting).
	 *
	 * @return void
	 */
	public static function attach( $meeting_object ) {
		$meeting_id = $meeting_object['id'] ?? null;
		$files      = $meeting_object['recording_files'] ?? array();

		if ( ! $meeting_id || empty( $files ) ) {
			return;
		}

		$play_url = self::pick_replay_url( $files );
		if ( ! $play_url ) {
			return;
		}

		$session_id = self::find_session_by_meeting_id( $meeting_id );
		if ( ! $session_id ) {
			return;
		}

		// Don't clobber a recording the instructor already attached by hand.
		if ( 'attached' === get_post_meta( $session_id, '_recording_state', true ) ) {
			return;
		}

		update_post_meta( $session_id, '_recording_source', 'Zoom' );
		update_post_meta( $session_id, '_recording_url', esc_url_raw( $play_url ) );
		update_post_meta( $session_id, '_recording_state', 'attached' );
	}

	/**
	 * Pick the best playable URL out of a meeting's recording files, preferring
	 * the composite gallery/speaker-view video over screen-share-only or audio-only tracks.
	 *
	 * @since 1.0.0
	 *
	 * @param array $files The `recording_files` array from the webhook payload.
	 *
	 * @return string
	 */
	private static function pick_replay_url( $files ) {
		$preferred = null;
		$fallback  = null;

		foreach ( $files as $file ) {
			if ( 'MP4' !== ( $file['file_type'] ?? '' ) ) {
				continue;
			}
			$url = $file['play_url'] ?? ( $file['download_url'] ?? '' );
			if ( ! $url ) {
				continue;
			}
			if ( 'shared_screen_with_speaker_view' === ( $file['recording_type'] ?? '' ) ) {
				$preferred = $url;
				break;
			}
			if ( ! $fallback ) {
				$fallback = $url;
			}
		}

		return $preferred ? $preferred : $fallback;
	}

	/**
	 * Find the session post whose stored Zoom meeting matches the given ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int|string $meeting_id The Zoom meeting ID from the webhook payload.
	 *
	 * @return int|null
	 */
	private static function find_session_by_meeting_id( $meeting_id ) {
		global $wpdb;

		$like = '%"id":' . (int) $meeting_id . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One LIKE lookup on the meeting meta per webhook event; a cached answer could miss a new session.
		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_zoom_meeting_data' AND meta_value LIKE %s LIMIT 1",
				$like
			)
		);

		return $post_id ? (int) $post_id : null;
	}
}
