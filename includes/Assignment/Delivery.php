<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Assignment delivery settings and the learner gate that enforces them.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Assignment;

use OhMyLMS\Learning\DripSchedule;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Resources, prerequisites, drip release and the comments switch of an assignment (see
 * docs/api/assignments.md, "Delivery settings"). Storage keeps the legacy meta keys and shapes the
 * learner templates read: `_download_resource` (`{file: [{type, id, name, url, size}]}`), `_prerequisites`
 * (`{enable, data: [{value, label}]}`), `_drip_settings` (`{enable, type, days, date, time}`) and
 * `_enable_comments` ('1'/'0').
 *
 * {@see self::check_submission()} is the server-side gate of a learner submission: enrolment, the drip
 * release and every prerequisite. Reading the assignment stays a matter of the course access rules and the
 * drip message; prerequisites block submitting, not reading.
 */
final class Delivery {

	const MAX_RESOURCES      = 20;
	const MAX_PREREQUISITES  = 50;
	const PREREQUISITE_TYPES = array(
		'ohmylms-lesson'     => 'lesson',
		'ohmylms-quiz'       => 'quiz',
		'ohmylms-assignment' => 'assignment',
	);

	/**
	 * A 400/422 validation error naming its field.
	 *
	 * @param string $code    Error code.
	 * @param string $message Message.
	 * @param string $field   Field.
	 * @param int    $status  HTTP status.
	 * @return WP_Error
	 */
	private static function invalid( $code, $message, $field, $status = 422 ) {
		return new WP_Error(
			$code,
			$message,
			array(
				'status' => $status,
				'field'  => $field,
			)
		);
	}

	/**
	 * Stored array meta, or an empty array.
	 *
	 * @param int    $id  Assignment ID.
	 * @param string $key Meta key.
	 * @return array
	 */
	private static function stored( $id, $key ) {
		$value = get_post_meta( (int) $id, $key, true );
		return is_array( $value ) ? $value : array();
	}

	/**
	 * Plain title of a post.
	 *
	 * @param int $id Post ID.
	 * @return string
	 */
	private static function title( $id ) {
		return html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Delivery settings as the editor reads them.
	 *
	 * @param int $id Assignment ID.
	 * @return array{resources:array,prerequisites:array,drip:array,comments_enabled:bool}
	 */
	public static function read( $id ) {
		return array(
			'resources'        => self::read_resources( $id ),
			'prerequisites'    => self::read_prerequisites( $id ),
			'drip'             => self::read_drip( self::stored( $id, '_drip_settings' ) ),
			'comments_enabled' => rest_sanitize_boolean( get_post_meta( (int) $id, '_enable_comments', true ) ),
		);
	}

	/**
	 * Resources as `{type, attachment_id, name, url, size}`. Rows written before types existed are files when
	 * they carry an attachment ID and links otherwise.
	 *
	 * @param int $id Assignment ID.
	 * @return array[]
	 */
	public static function read_resources( $id ) {
		$stored = self::stored( $id, '_download_resource' );
		$rows   = array();
		foreach ( is_array( $stored['file'] ?? null ) ? $stored['file'] : array() as $row ) {
			if ( ! is_array( $row ) || empty( $row['url'] ) ) {
				continue;
			}
			$attachment = absint( $row['id'] ?? 0 );
			$type       = in_array( $row['type'] ?? '', array( 'file', 'link' ), true ) ? $row['type'] : ( $attachment ? 'file' : 'link' );
			$rows[]     = array(
				'type'          => $type,
				'attachment_id' => 'file' === $type ? $attachment : 0,
				'name'          => (string) ( $row['name'] ?? '' ),
				'url'           => (string) $row['url'],
				'size'          => (string) ( $row['size'] ?? '' ),
			);
		}
		return $rows;
	}

	/**
	 * Prerequisites with live titles: `{enable, items: [{id, title, type, status}]}`. Removed content is kept
	 * with its stored label and the status `missing`, so the author can see and remove it.
	 *
	 * @param int $id Assignment ID.
	 * @return array
	 */
	public static function read_prerequisites( $id ) {
		$stored = self::stored( $id, '_prerequisites' );
		$items  = array();
		foreach ( is_array( $stored['data'] ?? null ) ? $stored['data'] : array() as $row ) {
			$content = absint( is_array( $row ) ? ( $row['value'] ?? 0 ) : 0 );
			if ( ! $content ) {
				continue;
			}
			$post    = get_post( $content );
			$items[] = array(
				'id'     => $content,
				'title'  => $post ? self::title( $content ) : (string) ( $row['label'] ?? '' ),
				'type'   => $post && isset( self::PREREQUISITE_TYPES[ $post->post_type ] ) ? self::PREREQUISITE_TYPES[ $post->post_type ] : 'other',
				'status' => $post && 'trash' !== $post->post_status ? (string) $post->post_status : 'missing',
			);
		}
		return array(
			'enable' => ! empty( $stored['enable'] ),
			'items'  => $items,
		);
	}

	/**
	 * Normalized drip settings, including the legacy editor's date formats.
	 *
	 * @param array $drip Stored settings.
	 * @return array{enable:bool,type:string,days:int,date:string,time:string}
	 */
	public static function read_drip( array $drip ) {
		list( $date, $time ) = DripSchedule::date_parts( $drip );
		$type                = (string) ( $drip['type'] ?? '' );
		$has_time            = '' !== (string) ( $drip['time'] ?? '' );
		return array(
			'enable' => ! empty( $drip['enable'] ),
			'type'   => in_array( $type, DripSchedule::TYPES, true ) ? $type : DripSchedule::TYPES[0],
			'days'   => min( 3650, absint( $drip['days'] ?? 0 ) ),
			'date'   => $date,
			'time'   => $has_time ? substr( $time, 0, 5 ) : '',
		);
	}

	/**
	 * Validate submitted resources. Files must be media library attachments; their URL and size come from
	 * the library, never from the request. A newly added file must be editable by the author; files already
	 * listed stay allowed, so saving never fails on someone else's upload.
	 *
	 * @param int   $id    Assignment ID.
	 * @param mixed $value Submitted rows.
	 * @return array|WP_Error Rows to store.
	 */
	public static function clean_resources( $id, $value ) {
		$message = __( 'Resources are up to 20 files or links, each with a name of 1 to 200 characters; links use HTTP or HTTPS.', 'ohmylms' );
		if ( ! is_array( $value ) || array_values( $value ) !== $value || count( $value ) > self::MAX_RESOURCES ) {
			return self::invalid( 'ohmylms_assignment_invalid_resources', $message, 'resources' );
		}
		$known = array_filter( array_column( self::read_resources( $id ), 'attachment_id' ) );
		$rows  = array();
		foreach ( $value as $row ) {
			if ( ! is_array( $row ) || array_diff( array_keys( $row ), array( 'type', 'attachment_id', 'name', 'url', 'size' ) ) || ! in_array( $row['type'] ?? '', array( 'file', 'link' ), true ) || ! is_string( $row['name'] ?? null ) ) {
				return self::invalid( 'ohmylms_assignment_invalid_resources', $message, 'resources' );
			}
			$name = trim( sanitize_text_field( $row['name'] ) );
			if ( '' === $name || mb_strlen( $name ) > 200 ) {
				return self::invalid( 'ohmylms_assignment_invalid_resources', $message, 'resources' );
			}
			if ( 'file' === $row['type'] ) {
				$attachment = $row['attachment_id'] ?? 0;
				$file       = is_int( $attachment ) && $attachment > 0 ? get_attached_file( $attachment ) : '';
				if ( ! $file || 'attachment' !== get_post_type( $attachment ) || ( ! in_array( $attachment, $known, true ) && ! current_user_can( 'edit_post', $attachment ) ) ) {
					return self::invalid( 'ohmylms_assignment_invalid_resource_file', __( 'Choose files from the media library that you can edit.', 'ohmylms' ), 'resources' );
				}
				$rows[] = array(
					'type' => 'file',
					'id'   => $attachment,
					'name' => $name,
					'url'  => (string) wp_get_attachment_url( $attachment ),
					'size' => is_file( $file ) ? (string) size_format( (int) filesize( $file ) ) : '',
				);
				continue;
			}
			$url  = is_string( $row['url'] ?? null ) ? trim( $row['url'] ) : '';
			$size = is_string( $row['size'] ?? null ) ? sanitize_text_field( $row['size'] ) : '';
			if ( strlen( $url ) > 2048 || ! filter_var( $url, FILTER_VALIDATE_URL ) || ! in_array( wp_parse_url( $url, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) || mb_strlen( $size ) > 50 ) {
				return self::invalid( 'ohmylms_assignment_invalid_resources', $message, 'resources' );
			}
			$rows[] = array(
				'type' => 'link',
				'id'   => 0,
				'name' => $name,
				'url'  => esc_url_raw( $url ),
				'size' => $size,
			);
		}
		return $rows;
	}

	/**
	 * Validate submitted prerequisites `{enable, ids}`: up to 50 distinct lessons, quizzes or assignments
	 * other than this one. Newly added content must be editable by the author; content already required
	 * stays allowed.
	 *
	 * @param int   $id    Assignment ID.
	 * @param mixed $value Submitted value.
	 * @return array|WP_Error Stored shape.
	 */
	public static function clean_prerequisites( $id, $value ) {
		if ( ! is_array( $value ) || array_diff( array_keys( $value ), array( 'enable', 'ids' ) ) || ! is_bool( $value['enable'] ?? null ) || ! is_array( $value['ids'] ?? null ) || count( $value['ids'] ) > self::MAX_PREREQUISITES ) {
			return self::invalid( 'ohmylms_assignment_invalid_prerequisites', __( 'Send prerequisites as an enabled flag and up to 50 content IDs.', 'ohmylms' ), 'prerequisites' );
		}
		$known = array_column( self::read_prerequisites( $id )['items'], 'id' );
		$rows  = array();
		foreach ( $value['ids'] as $content ) {
			$post = is_int( $content ) && $content > 0 ? get_post( $content ) : null;
			if ( ! $post || (int) $id === $content || isset( $rows[ $content ] ) ) {
				return self::invalid( 'ohmylms_assignment_invalid_prerequisites', __( 'Prerequisites must be other lessons, quizzes or assignments, each listed once.', 'ohmylms' ), 'prerequisites' );
			}
			$allowed = in_array( $content, $known, true ) || ( isset( self::PREREQUISITE_TYPES[ $post->post_type ] ) && ! in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) && current_user_can( 'edit_post', $content ) );
			if ( ! $allowed ) {
				return self::invalid( 'ohmylms_assignment_invalid_prerequisites', __( 'Prerequisites must be lessons, quizzes or assignments you can edit.', 'ohmylms' ), 'prerequisites' );
			}
			$rows[ $content ] = array(
				'value' => $content,
				'label' => self::title( $content ),
			);
		}
		return array(
			'enable' => $value['enable'],
			'data'   => array_values( $rows ),
		);
	}

	/**
	 * Validate submitted drip settings (exactly `{enable, type, days, date, time}`).
	 *
	 * @param mixed $drip Submitted value.
	 * @return array|WP_Error Stored shape (`time` as `HH:MM:SS`).
	 */
	public static function clean_drip( $drip ) {
		$keys = array( 'enable', 'type', 'days', 'date', 'time' );
		if ( ! is_array( $drip ) || array_diff( array_keys( $drip ), $keys ) || array_diff( $keys, array_keys( $drip ) ) || ! is_bool( $drip['enable'] ) || ! in_array( $drip['type'], DripSchedule::TYPES, true ) || ! is_int( $drip['days'] ) || $drip['days'] < 0 || $drip['days'] > 3650 || ! is_string( $drip['date'] ) || ! is_string( $drip['time'] ) ) {
			return self::invalid( 'ohmylms_assignment_invalid_drip', __( 'Send the release with a type, 0 to 3650 days, a date and a time.', 'ohmylms' ), 'drip' );
		}
		if ( '' !== $drip['date'] && ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/D', $drip['date'], $date ) || ! checkdate( (int) $date[2], (int) $date[3], (int) $date[1] ) ) ) {
			return self::invalid( 'ohmylms_assignment_invalid_drip', __( 'Use a YYYY-MM-DD release date.', 'ohmylms' ), 'drip' );
		}
		if ( '' !== $drip['time'] && ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/D', $drip['time'] ) ) {
			return self::invalid( 'ohmylms_assignment_invalid_drip', __( 'Use an HH:MM release time.', 'ohmylms' ), 'drip' );
		}
		if ( $drip['enable'] && 'specific-date' === $drip['type'] && '' === $drip['date'] ) {
			return self::invalid( 'ohmylms_assignment_invalid_drip', __( 'A release on a specific date needs that date.', 'ohmylms' ), 'drip' );
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
	 * Validate the delivery fields of a request. Only fields present are returned; nothing is written.
	 *
	 * @param int              $id      Assignment ID.
	 * @param \WP_REST_Request $request Request.
	 * @return array|WP_Error Meta key => value to store.
	 */
	public static function validated( $id, $request ) {
		$meta = array();
		if ( $request->has_param( 'resources' ) ) {
			$resources = self::clean_resources( $id, $request['resources'] );
			if ( is_wp_error( $resources ) ) {
				return $resources;
			}
			$meta['_download_resource'] = array_merge( self::stored( $id, '_download_resource' ), array( 'file' => $resources ) );
		}
		if ( $request->has_param( 'prerequisites' ) ) {
			$prerequisites = self::clean_prerequisites( $id, $request['prerequisites'] );
			if ( is_wp_error( $prerequisites ) ) {
				return $prerequisites;
			}
			$meta['_prerequisites'] = $prerequisites;
		}
		if ( $request->has_param( 'drip' ) ) {
			$drip = self::clean_drip( $request['drip'] );
			if ( is_wp_error( $drip ) ) {
				return $drip;
			}
			$meta['_drip_settings'] = array_merge( self::stored( $id, '_drip_settings' ), $drip );
		}
		if ( $request->has_param( 'comments_enabled' ) ) {
			if ( ! is_bool( $request['comments_enabled'] ) ) {
				return self::invalid( 'ohmylms_assignment_invalid_comments', __( 'Comments are switched on or off.', 'ohmylms' ), 'comments_enabled', 400 );
			}
			$meta['_enable_comments'] = $request['comments_enabled'] ? '1' : '0';
		}
		return $meta;
	}

	/**
	 * Store validated delivery meta.
	 *
	 * @param int   $id   Assignment ID.
	 * @param array $meta Meta key => value from {@see self::validated()}.
	 * @return void
	 */
	public static function write( $id, array $meta ) {
		foreach ( $meta as $key => $value ) {
			update_post_meta( (int) $id, $key, is_string( $value ) ? $value : wp_slash( $value ) );
		}
	}

	/**
	 * Whether a learner completed content, read fresh (no request cache).
	 *
	 * @param int $user_id    Learner.
	 * @param int $content_id Lesson, quiz or assignment.
	 * @return bool
	 */
	public static function completed( $user_id, $content_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The gate must see a completion recorded moments ago.
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT p.id FROM %i p INNER JOIN %i e ON e.id = p.enrollment_id WHERE e.user_id = %d AND p.content_id = %d AND p.status = %s LIMIT 1',
				$wpdb->prefix . 'ohmylms_user_progress',
				$wpdb->prefix . 'ohmylms_user_enrollment',
				(int) $user_id,
				(int) $content_id,
				'completed'
			)
		);
	}

	/**
	 * Whether a learner is enrolled in a course, read fresh.
	 *
	 * @param int $user_id   Learner.
	 * @param int $course_id Course.
	 * @return bool
	 */
	public static function enrolled( $user_id, $course_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh enrolment check for the submission gate.
		return $course_id > 0 && (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE user_id = %d AND course_id = %d AND status = %s LIMIT 1', $wpdb->prefix . 'ohmylms_user_enrollment', (int) $user_id, (int) $course_id, 'enrolled' ) );
	}

	/**
	 * What a learner may do with an assignment now.
	 *
	 * `can_submit` is false with a `reason` of `not_published`, `not_in_course`, `not_enrolled`, `not_open`
	 * (with `opens_at`) or `prerequisites` (with the incomplete items). People who can edit the assignment
	 * preview it without these gates, as they read it without enrolling.
	 *
	 * @param int $id      Assignment ID.
	 * @param int $user_id User.
	 * @return array{course_id:int,can_submit:bool,reason:string|null,message:string,opens_at:string|null,opens_label:string,prerequisites:array,comments_enabled:bool,preview:bool}
	 */
	public static function state( $id, $user_id ) {
		$id        = (int) $id;
		$user_id   = (int) $user_id;
		$post      = get_post( $id );
		$course_id = (int) ohmylms_get_course_by_content_id( $id, $user_id );
		$required  = self::read_prerequisites( $id );
		$preview   = $user_id > 0 && user_can( $user_id, 'edit_post', $id );
		$items     = array();
		if ( $required['enable'] ) {
			foreach ( $required['items'] as $item ) {
				$items[] = $item + array(
					'url'       => 'missing' === $item['status'] ? '' : (string) ohmylms_get_pretty_content_permalink( $item['id'] ),
					'completed' => $user_id > 0 && self::completed( $user_id, $item['id'] ),
				);
			}
		}
		$opens   = DripSchedule::opens_at( self::stored( $id, '_drip_settings' ), $course_id, $user_id );
		$closed  = $opens && new \DateTimeImmutable( 'now', DripSchedule::timezone() ) < $opens;
		$state   = array(
			'course_id'        => $course_id,
			'can_submit'       => true,
			'reason'           => null,
			'message'          => '',
			'opens_at'         => $opens ? gmdate( 'Y-m-d\TH:i:s\Z', $opens->getTimestamp() ) : null,
			'opens_label'      => $opens ? DripSchedule::format( $opens ) : '',
			'prerequisites'    => $items,
			'comments_enabled' => rest_sanitize_boolean( get_post_meta( $id, '_enable_comments', true ) ),
			'preview'          => $preview,
		);
		$missing = array_values(
			array_filter(
				$items,
				static function ( $item ) {
					return ! $item['completed'];
				}
			)
		);
		if ( ! $post || OHMYLMS_ASSIGNMENT_CPT !== $post->post_type || 'publish' !== $post->post_status ) {
			$block = array( 'not_published', __( 'This assignment is not published.', 'ohmylms' ) );
		} elseif ( $preview ) {
			$block = null;
		} elseif ( ! $course_id ) {
			$block = array( 'not_in_course', __( 'This assignment is not part of a course yet.', 'ohmylms' ) );
		} elseif ( ! self::enrolled( $user_id, $course_id ) ) {
			$block = array( 'not_enrolled', __( 'Enrol in the course to submit this assignment.', 'ohmylms' ) );
		} elseif ( $closed ) {
			/* translators: %s: release date and time. */
			$block = array( 'not_open', sprintf( __( 'This assignment opens on %s.', 'ohmylms' ), DripSchedule::format( $opens ) ) );
		} elseif ( $missing ) {
			/* translators: %s: comma-separated titles. */
			$block = array( 'prerequisites', sprintf( __( 'Complete %s before you submit this assignment.', 'ohmylms' ), implode( ', ', array_column( $missing, 'title' ) ) ) );
		} else {
			$block = null;
		}
		if ( $block ) {
			$state['can_submit'] = false;
			$state['reason']     = $block[0];
			$state['message']    = $block[1];
		}
		return $state;
	}

	/**
	 * Server-side gate of a learner submission.
	 *
	 * @param int $id      Assignment ID.
	 * @param int $user_id Learner.
	 * @return true|WP_Error 403 with `ohmylms_assignment_<reason>` and the learner-facing message.
	 */
	public static function check_submission( $id, $user_id ) {
		if ( $user_id < 1 ) {
			return new WP_Error( 'ohmylms_assignment_not_logged_in', __( 'You must be logged in to submit the assignment.', 'ohmylms' ), array( 'status' => 401 ) );
		}
		$state = self::state( $id, $user_id );
		if ( $state['can_submit'] ) {
			return true;
		}
		return new WP_Error(
			'ohmylms_assignment_' . $state['reason'],
			$state['message'],
			array(
				'status'   => 403,
				'opens_at' => $state['opens_at'],
				'missing'  => array_values(
					array_map(
						static function ( $item ) {
							return $item['id'];
						},
						array_filter(
							$state['prerequisites'],
							static function ( $item ) {
								return ! $item['completed'];
							}
						)
					)
				),
			)
		);
	}
}
