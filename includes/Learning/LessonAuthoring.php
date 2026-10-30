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

	const DRIP_TYPES = array( 'enrollment-from-x-days', 'specific-date', 'cohort-start', 'cohort-from-x-days' );

	const PREREQUISITE_TYPES = array( 'ohmylms-lesson', 'ohmylms-quiz', 'ohmylms-assignment' );

	/** Attachment field => required MIME type prefix. */
	const MEDIA = array(
		'cover_image_id' => 'image/',
		'video_id'       => 'video/',
		'audio_id'       => 'audio/',
	);

	/** Optional fields; when omitted their stored values are preserved. */
	const OPTIONAL = array( 'drip', 'prerequisites', 'cover_image_id', 'video_id', 'audio_id' );

	/**
	 * Whether a lesson type is an extension type edited through its registered lesson editor.
	 *
	 * @param string $type Stored lesson type.
	 * @return bool
	 */
	public static function is_custom_type( $type ) {
		return ! in_array( $type, self::TYPES, true ) && null !== \OhMyLMS\Extensions\Registry::get( 'lesson', $type );
	}

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
			'custom_type'       => self::is_custom_type( $settings['type'] ),
			'drip'              => self::read_drip( $settings['drip_settings'] ),
			'prerequisites'     => self::read_prerequisites( $settings['prerequisites'] ),
			'cover_image_id'    => $settings['cover_image_id'],
			'video_id'          => $settings['video_id'],
			'audio_id'          => $settings['audio_id'],
			'media_urls'        => array(
				'cover_image_id' => self::media_url( $settings['cover_image_id'] ),
				'video_id'       => self::media_url( $settings['video_id'] ),
				'audio_id'       => self::media_url( $settings['audio_id'] ),
			),
		);
	}

	/**
	 * Attachment URL for display, or empty.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	private static function media_url( $attachment_id ) {
		return $attachment_id && 'attachment' === get_post_type( $attachment_id ) ? (string) wp_get_attachment_url( $attachment_id ) : '';
	}

	/**
	 * Normalize stored drip settings, including the native editor's `date` + `dateTtime` time format.
	 *
	 * @param mixed $value Stored drip settings.
	 * @return array{enable:bool,type:string,days:int,date:string,time:string}
	 */
	private static function read_drip( $value ) {
		$value = (array) $value;
		$type  = (string) ( $value['type'] ?? '' );
		$date  = (string) ( $value['date'] ?? '' );
		$time  = (string) ( $value['time'] ?? '' );
		if ( preg_match( '/T(\d{2}:\d{2})/', $time, $match ) ) {
			$time = $match[1];
		}
		return array(
			'enable' => ! empty( $value['enable'] ),
			'type'   => in_array( $type, self::DRIP_TYPES, true ) ? $type : self::DRIP_TYPES[0],
			'days'   => absint( $value['days'] ?? 0 ),
			'date'   => preg_match( '/^\d{4}-\d{2}-\d{2}/', $date ) ? substr( $date, 0, 10 ) : '',
			'time'   => preg_match( '/^\d{2}:\d{2}/', $time ) ? substr( $time, 0, 5 ) : '',
		);
	}

	/**
	 * Stored prerequisites as `{enable, items:[{id,title}]}`; titles are read fresh when the post exists.
	 *
	 * @param mixed $value Stored prerequisite settings.
	 * @return array
	 */
	private static function read_prerequisites( $value ) {
		$value = (array) $value;
		$items = array();
		foreach ( (array) ( $value['data'] ?? array() ) as $row ) {
			$row = (array) $row;
			$id  = absint( $row['value'] ?? 0 );
			if ( $id ) {
				$items[] = array(
					'id'    => $id,
					'title' => get_post( $id ) ? html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ) : (string) ( $row['label'] ?? '' ),
				);
			}
		}
		return array(
			'enable' => ! empty( $value['enable'] ),
			'items'  => $items,
		);
	}

	/**
	 * Validate submitted drip settings.
	 *
	 * @param mixed $drip Submitted value.
	 * @return array|\WP_Error Stored shape.
	 */
	private static function clean_drip( $drip ) {
		$keys = array( 'enable', 'type', 'days', 'date', 'time' );
		if ( ! is_array( $drip ) || array_diff( array_keys( $drip ), $keys ) || array_diff( $keys, array_keys( $drip ) ) || ! is_bool( $drip['enable'] ) || ! in_array( $drip['type'], self::DRIP_TYPES, true ) || ! is_int( $drip['days'] ) || $drip['days'] < 0 || $drip['days'] > 3650 || ! is_string( $drip['date'] ) || ! is_string( $drip['time'] ) ) {
			return Catalog::error( __( 'Send drip settings with a release type, 0 to 3650 days, a date and a time.', 'ohmylms' ) );
		}
		if ( '' !== $drip['date'] && ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/D', $drip['date'], $date ) || ! checkdate( (int) $date[2], (int) $date[3], (int) $date[1] ) ) ) {
			return Catalog::error( __( 'Use a YYYY-MM-DD release date.', 'ohmylms' ) );
		}
		if ( '' !== $drip['time'] && ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/D', $drip['time'] ) ) {
			return Catalog::error( __( 'Use an HH:MM release time.', 'ohmylms' ) );
		}
		if ( $drip['enable'] && 'specific-date' === $drip['type'] && '' === $drip['date'] ) {
			return Catalog::error( __( 'A release on a specific date needs that date.', 'ohmylms' ) );
		}
		return array(
			'enable' => $drip['enable'],
			'type'   => $drip['type'],
			'days'   => $drip['days'],
			'date'   => $drip['date'],
			'time'   => '' === $drip['time'] ? '' : $drip['time'] . ':00',
		);
	}

	/**
	 * Validate submitted prerequisites. Newly added content must be editable by the author; content that is
	 * already a prerequisite stays allowed so an unrelated save never fails on someone else's resource.
	 *
	 * @param int   $lesson_id Lesson ID.
	 * @param mixed $data Submitted `{enable, ids}`.
	 * @param array $stored Stored `{enable, items}`.
	 * @return array|\WP_Error Stored shape.
	 */
	private static function clean_prerequisites( $lesson_id, $data, array $stored ) {
		if ( ! is_array( $data ) || array_diff( array_keys( $data ), array( 'enable', 'ids' ) ) || ! is_bool( $data['enable'] ?? null ) || ! is_array( $data['ids'] ?? null ) || count( $data['ids'] ) > 50 ) {
			return Catalog::error( __( 'Send prerequisites as an enabled flag and up to 50 content IDs.', 'ohmylms' ) );
		}
		$known = array_column( $stored['items'], 'id' );
		$rows  = array();
		foreach ( $data['ids'] as $id ) {
			$post = is_int( $id ) && $id > 0 ? get_post( $id ) : null;
			if ( ! $post || (int) $lesson_id === $id || isset( $rows[ $id ] ) || ! in_array( $post->post_type, self::PREREQUISITE_TYPES, true ) || in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) || ( ! in_array( $id, $known, true ) && ! current_user_can( 'edit_post', $id ) ) ) {
				return Catalog::error( __( 'Prerequisites must be other lessons, quizzes or assignments you can edit, each listed once.', 'ohmylms' ) );
			}
			$rows[ $id ] = array(
				'value' => $id,
				'label' => html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ),
			);
		}
		return array(
			'enable' => $data['enable'],
			'data'   => array_values( $rows ),
		);
	}

	/**
	 * Validate one media attachment reference; an unchanged stored reference stays allowed.
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Submitted attachment ID.
	 * @param int    $stored Stored attachment ID.
	 * @return int|\WP_Error
	 */
	private static function clean_media( $field, $value, $stored ) {
		if ( ! is_int( $value ) || $value < 0 ) {
			return Catalog::error( __( 'Lesson media must be an attachment ID or 0.', 'ohmylms' ) );
		}
		if ( 0 === $value || $value === (int) $stored ) {
			return $value;
		}
		if ( 'attachment' !== get_post_type( $value ) || 0 !== strpos( (string) get_post_mime_type( $value ), self::MEDIA[ $field ] ) || ! current_user_can( 'edit_post', $value ) ) {
			return Catalog::error( __( 'Choose a media library file of the matching type that you can edit.', 'ohmylms' ) );
		}
		return $value;
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
				$current  = self::get( $lesson_id );
				$fields   = $publish ? array( 'revision', 'confirmed' ) : array( 'revision', 'title', 'content', 'type', 'external_url', 'preview_enable', 'enable_comments', 'resources' );
				$optional = $publish ? array() : self::OPTIONAL;
				if ( ! is_array( $data ) || array_diff( array_keys( $data ), $fields, $optional ) || array_diff( $fields, array_keys( $data ) ) ) {
					return Catalog::error( __( 'Send exactly the expected lesson fields.', 'ohmylms' ) );
				}
				if ( ! is_string( $data['revision'] ) || ! hash_equals( $current['revision'], $data['revision'] ) ) {
					return Catalog::error( __( 'This lesson changed in another session. Your edits are kept. Reload the saved lesson before trying again.', 'ohmylms' ), 409 );
				}
				if ( ( ! in_array( $current['type'], self::TYPES, true ) && ! $current['custom_type'] ) || ! in_array( $current['status'], array( 'draft', 'pending', 'private', 'publish' ), true ) ) {
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
				// Extension lesson types keep their type; built-in lessons switch only between built-in formats.
				$valid_type = $current['custom_type'] ? $current['type'] === $data['type'] : in_array( $data['type'], self::TYPES, true );
				if ( ! is_string( $data['title'] ) || '' === trim( sanitize_text_field( $data['title'] ) ) || mb_strlen( $data['title'] ) > 190 || ! is_string( $data['content'] ) || strlen( $data['content'] ) > 1000000 || ! $valid_type || ! is_bool( $data['preview_enable'] ) || ! is_bool( $data['enable_comments'] ) || ! is_array( $data['resources'] ) || count( $data['resources'] ) > 50 || ! is_string( $data['external_url'] ) ) {
					return Catalog::error( __( 'Check the lesson title, content, format and resource limits.', 'ohmylms' ) );
				}
				$stored   = NativeLessonEditor::read_settings( array( 'id' => $lesson_id ) );
				$advanced = array();
				if ( array_key_exists( 'drip', $data ) ) {
					$drip = self::clean_drip( $data['drip'] );
					if ( is_wp_error( $drip ) ) {
						return $drip;
					}
					$advanced['drip_settings'] = array_merge( (array) $stored['drip_settings'], $drip );
				}
				if ( array_key_exists( 'prerequisites', $data ) ) {
					$prerequisites = self::clean_prerequisites( $lesson_id, $data['prerequisites'], $current['prerequisites'] );
					if ( is_wp_error( $prerequisites ) ) {
						return $prerequisites;
					}
					$advanced['prerequisites'] = array_merge( (array) $stored['prerequisites'], $prerequisites );
				}
				foreach ( array_keys( self::MEDIA ) as $field ) {
					if ( array_key_exists( $field, $data ) ) {
						$media = self::clean_media( $field, $data[ $field ], $current[ $field ] );
						if ( is_wp_error( $media ) ) {
							return $media;
						}
						$advanced[ $field ] = $media;
					}
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
				) + $advanced;
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
