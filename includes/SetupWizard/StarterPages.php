<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Starter pages of the setup wizard.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\SetupWizard;

use OhMyLMS\Settings\SettingsRegistry;
use OhMyLMS\Settings\SettingsService;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Finds, adopts and creates the LMS pages, and points the `general` settings at them.
 *
 * Idempotent and additive: a page the settings already point at (published) is kept; a published
 * page with the expected slug is adopted without touching its content; otherwise a new page is
 * created. Existing pages are never overwritten, republished or deleted. Page ids are written
 * through the settings registry, all together or not at all.
 *
 * @since 1.0.0
 */
final class StarterPages {

	/**
	 * Page definitions. The `ohmylms_default_pages` filter still extends the list, but only keys that are
	 * `<key>_page_id` fields of the `general` settings group can be stored, so other keys are ignored.
	 *
	 * @return array<string, array{title: string, slug: string, content: string, template: string, field: string}>
	 */
	public static function definitions() {
		$defaults = array(
			'course'            => array(
				'name'    => 'ohmylms-all-courses',
				'title'   => _x( 'All Course', 'Page title', 'ohmylms' ),
				'content' => '',
			),
			'checkout'          => array(
				'name'     => 'ohmylms-checkout',
				'title'    => _x( 'OhMy Checkout', 'Page title', 'ohmylms' ),
				'content'  => '<!-- wp:shortcode -->[ohmylms_checkout]<!-- /wp:shortcode -->',
				'template' => 'ohmylms-checkout',
			),
			'student_dashboard' => array(
				'name'     => 'my-dashboard',
				'title'    => _x( 'My Dashboard', 'Page title', 'ohmylms' ),
				'content'  => '<!-- wp:shortcode -->[ohmylms_dashboard]<!-- /wp:shortcode -->',
				'template' => 'ohmylms-dashboard',
			),
			'student_profile'   => array(
				'name'     => 'my-profile',
				'title'    => _x( 'My Profile', 'Page title', 'ohmylms' ),
				'content'  => '<!-- wp:shortcode -->[ohmylms_profile]<!-- /wp:shortcode -->',
				'template' => 'ohmylms-profile',
			),
			'student_courses'   => array(
				'name'     => 'my-courses',
				'title'    => _x( 'My Courses', 'Page title', 'ohmylms' ),
				'content'  => '<!-- wp:shortcode -->[ohmylms_my_courses]<!-- /wp:shortcode -->',
				'template' => 'ohmylms-my-courses',
			),
		);

		/**
		 * Filters the default LMS pages (shared with the settings "restore pages" action).
		 *
		 * @param array $defaults Pages keyed by setting key, each with `name`, `title`, `content` and
		 *                        optionally `template`.
		 */
		$pages  = apply_filters( 'ohmylms_default_pages', $defaults );
		$fields = SettingsRegistry::group( 'general' )['fields'];
		$found  = array();
		foreach ( is_array( $pages ) ? $pages : array() as $key => $page ) {
			$field = $key . '_page_id';
			if ( ! is_string( $key ) || ! isset( $fields[ $field ] ) || ! is_array( $page ) || empty( $page['title'] ) || empty( $page['name'] ) ) {
				continue;
			}
			$found[ $key ] = array(
				'title'    => (string) $page['title'],
				'slug'     => sanitize_title( (string) $page['name'] ),
				'content'  => isset( $page['content'] ) ? (string) $page['content'] : '',
				'template' => isset( $page['template'] ) ? sanitize_text_field( (string) $page['template'] ) : '',
				'field'    => $field,
			);
		}

		return $found;
	}

	/**
	 * Describe each starter page without changing anything.
	 *
	 * @return array<int, array{key: string, title: string, slug: string, page_id: int, status: string}>
	 *         Status is `ready` (a published page is configured), `adoptable` (a published page with the
	 *         slug exists and would be used) or `missing` (a new page would be created).
	 */
	public static function describe() {
		$current = SettingsService::read( 'general' );
		$rows    = array();
		foreach ( self::definitions() as $key => $page ) {
			$configured = isset( $current[ $page['field'] ] ) ? (int) $current[ $page['field'] ] : 0;
			$status     = 'missing';
			$page_id    = 0;
			if ( self::is_published_page( $configured ) ) {
				$status  = 'ready';
				$page_id = $configured;
			} else {
				$existing = self::published_by_slug( $page['slug'] );
				if ( $existing ) {
					$status  = 'adoptable';
					$page_id = $existing;
				}
			}
			$rows[] = array(
				'key'     => $key,
				'title'   => $page['title'],
				'slug'    => $page['slug'],
				'page_id' => $page_id,
				'status'  => $status,
			);
		}

		return $rows;
	}

	/**
	 * Make sure every starter page exists and is configured.
	 *
	 * @return array|WP_Error List of `{key, page_id, action}` where action is `kept`, `adopted` or
	 *                        `created`; or an error after which nothing created in this call remains.
	 */
	public static function ensure() {
		$current = SettingsService::read( 'general' );
		$results = array();
		$created = array();
		$values  = array();

		foreach ( self::definitions() as $key => $page ) {
			$configured = isset( $current[ $page['field'] ] ) ? (int) $current[ $page['field'] ] : 0;
			if ( self::is_published_page( $configured ) ) {
				$results[] = self::result( $key, $configured, 'kept' );
				continue;
			}

			$existing = self::published_by_slug( $page['slug'] );
			if ( $existing ) {
				$values[ $page['field'] ] = $existing;
				$results[]                = self::result( $key, $existing, 'adopted' );
				continue;
			}

			$page_id = wp_insert_post(
				wp_slash(
					array(
						'post_title'     => $page['title'],
						'post_name'      => $page['slug'],
						'post_status'    => 'publish',
						'post_type'      => 'page',
						'post_content'   => $page['content'],
						'post_author'    => get_current_user_id(),
						'comment_status' => 'closed',
					)
				),
				true
			);
			if ( is_wp_error( $page_id ) || ! $page_id ) {
				self::discard( $created );

				return new WP_Error( 'ohmylms_setup_pages_failed', __( 'A starter page could not be created. No pages were added.', 'ohmylms' ), array( 'status' => 500 ) );
			}
			$created[] = (int) $page_id;
			if ( '' !== $page['template'] ) {
				update_post_meta( $page_id, '_wp_page_template', $page['template'] );
			}
			$values[ $page['field'] ] = (int) $page_id;
			$results[]                = self::result( $key, (int) $page_id, 'created' );
		}

		if ( $values ) {
			$clean = SettingsService::validate( 'general', $values );
			if ( ! is_wp_error( $clean ) ) {
				$written = SettingsService::write( 'general', $clean );
				if ( is_wp_error( $written ) ) {
					$clean = $written;
				}
			}
			if ( is_wp_error( $clean ) ) {
				self::discard( $created );
				$details = $clean->get_error_data();

				return new WP_Error(
					'ohmylms_setup_pages_failed',
					__( 'The starter pages could not be saved in the settings. No pages were added.', 'ohmylms' ),
					array(
						'status' => 500,
						'params' => is_array( $details ) && isset( $details['params'] ) ? $details['params'] : array(),
					)
				);
			}
		}

		return $results;
	}

	/**
	 * Build one result row.
	 *
	 * @param string $key     Page key.
	 * @param int    $page_id Page id.
	 * @param string $action  `kept`, `adopted` or `created`.
	 * @return array
	 */
	private static function result( $key, $page_id, $action ) {
		return array(
			'key'     => $key,
			'page_id' => (int) $page_id,
			'action'  => $action,
		);
	}

	/**
	 * Delete pages this request created (they cannot be referenced yet).
	 *
	 * @param int[] $page_ids Page ids.
	 */
	private static function discard( array $page_ids ) {
		foreach ( $page_ids as $page_id ) {
			wp_delete_post( $page_id, true );
		}
	}

	/**
	 * Whether the id is a published page.
	 *
	 * @param int $page_id Post id.
	 * @return bool
	 */
	private static function is_published_page( $page_id ) {
		if ( $page_id < 1 ) {
			return false;
		}
		$post = get_post( $page_id );

		return $post && 'page' === $post->post_type && 'publish' === $post->post_status;
	}

	/**
	 * The id of the published top-level page with a slug, or 0.
	 *
	 * @param string $slug Page slug.
	 * @return int
	 */
	private static function published_by_slug( $slug ) {
		$page = get_page_by_path( $slug, OBJECT, 'page' );

		return $page && 'publish' === $page->post_status ? (int) $page->ID : 0;
	}
}
