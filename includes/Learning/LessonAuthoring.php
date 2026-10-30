<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Validated authoring boundary over WordPress lesson posts and lifecycle hooks.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Learning;

use OhMyLMS\Utility\Transaction;

defined( 'ABSPATH' ) || exit;

/** Preserve block content and settings outside the explicitly editable subset. */
final class LessonAuthoring {
	const TYPES = array( 'text', 'video', 'audio' );

	/**
	 * Shared lock for typed and native lesson writers.
	 *
	 * @param int      $lesson_id Lesson ID (or negative user ID for creation).
	 * @param callable $work Work under the lock.
	 * @return mixed
	 */
	public static function locked( $lesson_id, callable $work ) {
		global $wpdb;
		$key = 'ohmylms_lesson_' . (int) $lesson_id;
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 3)', $key ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh named lock, not cacheable data.
			return Catalog::error( __( 'Another author is saving this lesson. Try again.', 'ohmylms' ), 409 );
		}
		try {
			if ( $lesson_id > 0 ) {
				clean_post_cache( $lesson_id );
			}
			return $work();
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Release the named authoring lock.
		}
	}

	/**
	 * Read raw editable values without exposing private settings from other features.
	 *
	 * @param int $lesson_id Lesson ID.
	 * @return array
	 */
	public static function get( $lesson_id ) {
		$post      = get_post( $lesson_id );
		$settings  = NativeLessonEditor::read_settings( array( 'id' => $lesson_id ) );
		$resources = (array) $settings['download_resource'];
		return array(
			'id'                => $lesson_id,
			'title'             => $post->post_title,
			'content'           => $post->post_content,
			'status'            => $post->post_status,
			'revision'          => hash( 'sha256', wp_json_encode( array( $post->post_title, $post->post_content, $post->post_status, $post->post_author, get_post_meta( $lesson_id ) ) ) ),
			'type'              => $settings['type'],
			'external_url'      => $settings['external_url'],
			'preview_enable'    => $settings['preview_enable'],
			'enable_comments'   => $settings['enable_comments'],
			'resources'         => array_values(
				array_map(
					static function ( $resource_row ) {
						return array(
							'name' => (string) ( $resource_row['name'] ?? '' ),
							'url'  => (string) ( $resource_row['url'] ?? '' ),
							'size' => (string) ( $resource_row['size'] ?? '' ),
						);
					},
					is_array( $resources['file'] ?? null ) ? $resources['file'] : array()
				)
			),
			'native_editor_url' => NativeLessonEditor::edit_url( $lesson_id ),
		);
	}

	/**
	 * Delegate post persistence and lesson lifecycle notifications to the native REST writer.
	 *
	 * @param int   $lesson_id Existing ID or zero for creation.
	 * @param array $payload Core REST payload.
	 * @return array|\WP_Error
	 */
	private static function write( $lesson_id, array $payload ) {
		$request = new \WP_REST_Request( 'POST', '/wp/v2/ohmylms-lessons' . ( $lesson_id ? '/' . $lesson_id : '' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $payload ) );
		$response = rest_do_request( $request );
		if ( $response->get_status() >= 400 ) {
			return Catalog::error( $response->get_data()['message'] ?? __( 'The lesson could not be saved.', 'ohmylms' ), $response->get_status() );
		}
		return self::get( (int) $response->get_data()['id'] );
	}

	/**
	 * Roll back the whole post/settings mutation on a failed native write.
	 *
	 * @param int      $lesson_id Lesson ID.
	 * @param callable $work Mutation.
	 * @return mixed
	 */
	private static function atomic( $lesson_id, callable $work ) {
		$error = null;
		try {
			return Transaction::run(
				static function () use ( $work, &$error ) {
					$result = $work();
					if ( is_wp_error( $result ) ) {
						$error = $result;
						throw new \RuntimeException( 'Lesson write rejected.' );
					}
					return $result;
				}
			);
		} catch ( \Throwable $failure ) {
			clean_post_cache( $lesson_id );
			return $error ? $error : Catalog::error( __( 'The lesson could not be saved. No lesson changes were committed.', 'ohmylms' ), 500 );
		}
	}

	/**
	 * Idempotent, named draft creation; callers cannot submit arbitrary post fields.
	 *
	 * @param mixed $data JSON input.
	 * @return mixed
	 */
	public static function create( $data ) {
		if ( ! is_array( $data ) || array_diff( array_keys( $data ), array( 'title', 'request_id' ) ) || ! is_string( $data['title'] ?? null ) || '' === trim( sanitize_text_field( $data['title'] ) ) || mb_strlen( $data['title'] ) > 190 || ! is_string( $data['request_id'] ?? null ) || ! wp_is_uuid( $data['request_id'] ) ) {
			return Catalog::error( __( 'Send a nonblank lesson title of up to 190 characters and a creation identifier.', 'ohmylms' ) );
		}
		return self::locked(
			-get_current_user_id(),
			static function () use ( $data ) {
				$identity = get_current_user_id() . ':' . $data['request_id'];
				$existing = get_posts(
					array(
						'post_type'      => OHMYLMS_LESSON_CPT,
						'post_status'    => array( 'draft', 'publish', 'private', 'pending', 'trash' ),
						'posts_per_page' => 1,
						'meta_key'       => '_ohmylms_typed_lesson_request', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Exact one-result idempotency lookup by caller/UUID.
						'meta_value'     => $identity, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Exact one-result idempotency lookup by caller/UUID.
					)
				);
				if ( $existing ) {
					$post = $existing[0];
					return current_user_can( 'edit_post', $post->ID ) && 'trash' !== $post->post_status && sanitize_text_field( $data['title'] ) === $post->post_title ? self::get( (int) $post->ID ) : Catalog::error( __( 'This creation identifier was already used. Start a new lesson.', 'ohmylms' ), 409 );
				}
				return self::atomic(
					0,
					static function () use ( $data, $identity ) {
						$result = self::write(
							0,
							array(
								'title'                   => sanitize_text_field( $data['title'] ),
								'status'                  => 'draft',
								'ohmylms_lesson_settings' => array( 'type' => 'text' ),
							)
						);
						if ( is_wp_error( $result ) ) {
							return $result;
						}
						if ( ! add_post_meta( $result['id'], '_ohmylms_typed_lesson_request', $identity, true ) ) {
							return Catalog::error( __( 'The lesson creation identifier could not be saved.', 'ohmylms' ), 500 );
						}
						return self::get( $result['id'] );
					}
				);
			}
		);
	}

	/**
	 * Validate every field before the core writer can change the post.
	 *
	 * @param int   $lesson_id Lesson ID.
	 * @param mixed $data JSON input.
	 * @param bool  $publish Whether this is a reviewed publication command.
	 * @return mixed
	 */
	public static function save( $lesson_id, $data, $publish = false ) {
		return self::locked(
			$lesson_id,
			static function () use ( $lesson_id, $data, $publish ) {
				$current = self::get( $lesson_id );
				$fields  = $publish ? array( 'revision', 'confirmed' ) : array( 'revision', 'title', 'content', 'type', 'external_url', 'preview_enable', 'enable_comments', 'resources' );
				if ( ! is_array( $data ) || array_diff( array_keys( $data ), $fields ) || array_diff( $fields, array_keys( $data ) ) ) {
					return Catalog::error( __( 'Send exactly the expected lesson fields.', 'ohmylms' ) );
				}
				if ( ! is_string( $data['revision'] ) || ! hash_equals( $current['revision'], $data['revision'] ) ) {
					return Catalog::error( __( 'This lesson changed in another session. Your edits are kept. Reload the saved lesson before trying again.', 'ohmylms' ), 409 );
				}
				if ( ! in_array( $current['type'], self::TYPES, true ) || ! in_array( $current['status'], array( 'draft', 'pending', 'private', 'publish' ), true ) ) {
					return Catalog::error( __( 'Use the native or extension editor for this lesson.', 'ohmylms' ) );
				}
				if ( $publish ) {
					$type = get_post_type_object( OHMYLMS_LESSON_CPT );
					if ( true !== $data['confirmed'] || ! current_user_can( $type->cap->publish_posts ) ) {
						return Catalog::error( __( 'Explicit publication confirmation and publishing permission are required.', 'ohmylms' ), 403 );
					}
					return self::atomic(
						$lesson_id,
						static function () use ( $lesson_id ) {
							return self::write( $lesson_id, array( 'status' => 'publish' ) );
						}
					);
				}
				if ( ! is_string( $data['title'] ) || '' === trim( sanitize_text_field( $data['title'] ) ) || mb_strlen( $data['title'] ) > 190 || ! is_string( $data['content'] ) || strlen( $data['content'] ) > 1000000 || ! in_array( $data['type'], self::TYPES, true ) || ! is_bool( $data['preview_enable'] ) || ! is_bool( $data['enable_comments'] ) || ! is_array( $data['resources'] ) || count( $data['resources'] ) > 50 || ! is_string( $data['external_url'] ) ) {
					return Catalog::error( __( 'Check the lesson title, content, format and resource limits.', 'ohmylms' ) );
				}
				if ( '' !== $data['external_url'] && ! self::valid_url( $data['external_url'] ) ) {
					return Catalog::error( __( 'Media URLs must use HTTP or HTTPS.', 'ohmylms' ) );
				}
				$previous_resources = (array) NativeLessonEditor::read_settings( array( 'id' => $lesson_id ) )['download_resource'];
				$previous_files     = array_column( $previous_resources['file'] ?? array(), null, 'url' );
				$resources          = array();
				foreach ( $data['resources'] as $resource ) {
					if ( ! is_array( $resource ) || array_diff( array_keys( $resource ), array( 'name', 'url', 'size' ) ) || ! is_string( $resource['name'] ?? null ) || '' === trim( $resource['name'] ) || strlen( $resource['name'] ) > 200 || ! is_string( $resource['url'] ?? null ) || ! self::valid_url( $resource['url'] ) || ! is_string( $resource['size'] ?? null ) || strlen( $resource['size'] ) > 50 ) {
						return Catalog::error( __( 'Resources need a name, HTTP/HTTPS URL and size label.', 'ohmylms' ) );
					}
					$resources[] = array_merge(
						$previous_files[ $resource['url'] ] ?? array(),
						array(
							'name' => sanitize_text_field( $resource['name'] ),
							'url'  => esc_url_raw( $resource['url'] ),
							'size' => sanitize_text_field( $resource['size'] ),
						)
					);
				}
				$settings = array(
					'type'              => $data['type'],
					'external_url'      => esc_url_raw( $data['external_url'] ),
					'preview_enable'    => $data['preview_enable'],
					'enable_comments'   => $data['enable_comments'],
					'download_resource' => array_merge( $previous_resources, array( 'file' => $resources ) ),
				);
				return self::atomic(
					$lesson_id,
					static function () use ( $lesson_id, $data, $settings ) {
						return self::write(
							$lesson_id,
							array(
								'title'                   => trim( sanitize_text_field( $data['title'] ) ),
								'content'                 => $data['content'],
								'ohmylms_lesson_settings' => $settings,
							)
						);
					}
				);
			}
		);
	}

	/**
	 * Permit only ordinary absolute web resource URLs.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	private static function valid_url( $url ) {
		return strlen( $url ) <= 2048 && filter_var( $url, FILTER_VALIDATE_URL ) && in_array( wp_parse_url( $url, PHP_URL_SCHEME ), array( 'http', 'https' ), true );
	}
}
