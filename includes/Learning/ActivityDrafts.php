<?php // phpcs:ignore WordPress.Files.FileName -- Required Composer PSR-4 class/file mapping.
/**
 * New reusable resource drafts created from a course lesson.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Learning;

defined( 'ABSPATH' ) || exit;

/** Called within the authorized course structure lock and transaction. */
final class ActivityDrafts {
	/**
	 * Create through the established content writers; placements are saved separately.
	 *
	 * @param int   $course_id Course ID.
	 * @param array $data Resource input, optional quiz `style` and request UUID.
	 * @return array|\WP_Error
	 */
	public static function create( $course_id, array $data ) {
		$kind       = $data['kind'] ?? '';
		$title      = $data['title'] ?? null;
		$content    = $data['content'] ?? '';
		$url        = $data['url'] ?? '';
		$request_id = $data['request_id'] ?? '';
		$style      = $data['style'] ?? 'quiz';
		// A unit test or course challenge slot creates its quiz with the matching assessment style.
		if ( ! is_string( $style ) || ! in_array( $style, \OhMyLMS\Assessment\AssessmentSettings::STYLES, true ) || ( 'quiz' !== $style && ( 'quiz' !== $kind || ! \OhMyLMS\Assessment\Schema::ready() ) ) ) {
			return Catalog::error( __( 'Choose a quiz, unit test or course challenge style for a new quiz.', 'ohmylms' ) );
		}
		if ( ! in_array( $kind, array( 'reading', 'video', 'quiz' ), true ) || ! is_string( $title ) || '' === trim( wp_strip_all_tags( $title ) ) || mb_strlen( $title ) > 200 || ! is_string( $content ) || strlen( $content ) > 100000 || ! is_string( $url ) || ! is_string( $request_id ) || ! wp_is_uuid( $request_id ) ) {
			return Catalog::error( __( 'Choose a reading, video or quiz, a title within 200 characters, and a valid creation identifier.', 'ohmylms' ) );
		}
		if ( 'video' === $kind && ( ! filter_var( $url, FILTER_VALIDATE_URL ) || ! in_array( wp_parse_url( $url, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ) ) {
			return Catalog::error( __( 'Enter a valid HTTP or HTTPS video URL.', 'ohmylms' ) );
		}
		$title    = sanitize_text_field( $title );
		$hash     = hash( 'sha256', wp_json_encode( 'quiz' === $style ? array( $kind, $title, $content, $url ) : array( $kind, $title, $content, $url, $style ) ) );
		$identity = $course_id . ':' . $request_id;
		$matches  = get_posts(
			array(
				'post_type'      => array( OHMYLMS_LESSON_CPT, OHMYLMS_QUIZ_CPT ),
				'post_status'    => array( 'draft', 'publish', 'private', 'pending', 'trash' ),
				'posts_per_page' => 1,
				'meta_key'       => '_ohmylms_outline_draft_request', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Exact creation identity lookup required for idempotency; one result.
				'meta_value'     => $identity, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Exact course and UUID match, never an unbounded wildcard search.
			)
		);
		if ( $matches ) {
			$id = (int) $matches[0]->ID;
			if ( ! current_user_can( 'edit_post', $id ) || 'trash' === $matches[0]->post_status || get_post_meta( $id, '_ohmylms_outline_draft_input', true ) !== $hash ) {
				return Catalog::error( __( 'This creation request was already used. Start a new activity.', 'ohmylms' ), 409 );
			}
			return self::describe( $id, $kind );
		}
		if ( 'quiz' === $kind ) {
			$route   = '/ohmylms/v1/quiz';
			$payload = array(
				'name'        => $title,
				'status'      => 'draft',
				'description' => $content,
			);
		} else {
			$route   = '/wp/v2/ohmylms-lessons';
			$payload = array(
				'title'                   => $title,
				'status'                  => 'draft',
				'content'                 => $content,
				'ohmylms_lesson_settings' => array( 'type' => 'reading' === $kind ? 'text' : 'video' ),
			);
			if ( 'video' === $kind ) {
				$payload['ohmylms_lesson_settings']['external_url'] = esc_url_raw( $url );
			}
		}
		$request = new \WP_REST_Request( 'POST', $route );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $payload ) );
		$response = rest_do_request( $request );
		if ( $response->get_status() >= 400 ) {
			return Catalog::error( $response->get_data()['message'] ?? __( 'The resource draft could not be created.', 'ohmylms' ), $response->get_status() );
		}
		$id = (int) ( $response->get_data()['id'] ?? 0 );
		if ( ! $id || 'draft' !== get_post_status( $id ) ) {
			return Catalog::error( __( 'The resource writer did not create a draft.', 'ohmylms' ), 500 );
		}
		if ( ! add_post_meta( $id, '_ohmylms_outline_draft_request', $identity, true ) || ! add_post_meta( $id, '_ohmylms_outline_draft_input', $hash, true ) ) {
			return Catalog::error( __( 'The creation identifier could not be saved.', 'ohmylms' ), 500 );
		}
		if ( 'quiz' !== $style && is_wp_error( \OhMyLMS\Assessment\AssessmentSettings::save( $id, array( 'style' => $style ) ) ) ) {
			return Catalog::error( __( 'The assessment style could not be saved.', 'ohmylms' ), 500 );
		}
		return self::describe( $id, $kind );
	}

	/**
	 * Return the picker resource contract with an existing editor link.
	 *
	 * @param int    $id Resource ID.
	 * @param string $kind Requested format.
	 * @return array
	 */
	private static function describe( $id, $kind ) {
		return array(
			'id'       => $id,
			'title'    => get_the_title( $id ),
			'type'     => 'quiz' === $kind ? 'quiz' : 'lesson',
			'style'    => 'quiz' === $kind ? ( \OhMyLMS\Assessment\Schema::ready() ? \OhMyLMS\Assessment\AssessmentSettings::get( $id )['style'] : 'quiz' ) : 'lesson',
			'format'   => $kind,
			'status'   => get_post_status( $id ),
			'edit_url' => (string) get_edit_post_link( $id, 'raw' ),
		);
	}
}
