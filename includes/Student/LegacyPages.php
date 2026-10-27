<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the Composer PSR-4 class/file mapping.
/**
 * Retire redundant generated pages while preserving their bookmarked routes.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

defined( 'ABSPATH' ) || exit;

/** Migration is explicit; redirect resolution does not depend on a published page. */
final class LegacyPages {

	const OPTION = 'ohmylms_hub_retired_pages';

	/** Register reversible redirects and prevent re-provisioning retired pages. */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'redirect' ), 4 );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_portal' ), 3 );
		add_filter( 'ohmylms_default_pages', array( __CLASS__, 'default_pages' ) );
	}

	/**
	 * Retire only configured, unmodified, standalone generated pages.
	 *
	 * @return array Page IDs mapped to retirement results.
	 */
	public static function retire() {
		if ( ! current_user_can( 'manage_options' ) || ! Hub::base_url() ) {
			return array();
		}
		$definitions = array(
			'student_dashboard' => array( 'ohmylms_dashboard', 'dashboard' ),
			'student_profile'   => array( 'ohmylms_profile', 'profile' ),
			'student_courses'   => array( 'ohmylms_my_courses', 'my-courses' ),
		);
		$ledger      = get_option( self::OPTION, array() );
		$results     = array();
		foreach ( $definitions as $key => $definition ) {
			$page_id = absint( get_option( 'ohmylms_' . $key . '_page_id' ) );
			$page    = get_post( $page_id );
			if ( ! $page || Hub::settings()['page_id'] === $page_id ) {
				continue;
			}
			$content  = trim( preg_replace( '/<!--.*?-->/s', '', $page->post_content ) );
			$children = get_pages(
				array(
					'parent'      => $page_id,
					'number'      => 1,
					'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				)
			);

			$custom_meta = array_diff( array_keys( get_post_meta( $page_id ) ), array( '_wp_page_template', '_edit_lock', '_edit_last' ) );
			if ( 'page' !== $page->post_type || 'publish' !== $page->post_status || '[' . $definition[0] . ']' !== $content || $page->post_password || wp_get_associated_nav_menu_items( $page_id ) || $children || $custom_meta ) {
				$results[ $page_id ] = 'retained: custom content/metadata, menu, child page or publication state';
				continue;
			}
			$ledger[ $page_id ] = array(
				'url'      => get_permalink( $page_id ),
				'endpoint' => $definition[1],
				'option'   => 'ohmylms_' . $key . '_page_id',
			);
			update_option( self::OPTION, $ledger, false );
			if ( ! wp_trash_post( $page_id ) ) {
				unset( $ledger[ $page_id ] );
				update_option( self::OPTION, $ledger, false );
				$results[ $page_id ] = 'retained: trash operation failed';
				continue;
			}
			update_option( 'ohmylms_' . $key . '_page_id', Hub::settings()['page_id'] );
			$results[ $page_id ] = 'trashed; redirect retained';
		}
		return $results;
	}

	/**
	 * Compatibility output for old tags embedded in mixed-content pages.
	 *
	 * @param string $endpoint Legacy destination.
	 * @return string Canonical workspace link, or empty when the hub is disabled.
	 */
	public static function link( $endpoint ) {
		$url = Hub::endpoint_url( $endpoint );
		return $url ? '<p><a href="' . esc_url( $url ) . '">' . esc_html__( 'Open your learning workspace', 'ohmylms' ) . '</a></p>' : '';
	}

	/** Restore recoverable pages and their original configured IDs when disabling the hub. */
	public static function restore() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$ledger = get_option( self::OPTION, array() );
		foreach ( $ledger as $page_id => $entry ) {
			if ( 'trash' === get_post_status( $page_id ) ) {
				if ( ! wp_untrash_post( $page_id ) ) {
					continue;
				}
				wp_update_post(
					array(
						'ID'          => $page_id,
						'post_status' => 'publish',
					)
				);
			}
			if ( 'publish' === get_post_status( $page_id ) ) {
				update_option( $entry['option'], $page_id );
				unset( $ledger[ $page_id ] );
			}
		}
		update_option( self::OPTION, $ledger, false );
	}

	/**
	 * Resolve a retired route, forwarding only supported presentation parameters.
	 *
	 * @param string $request_uri Request URI, relative to the current site origin.
	 * @return string Canonical destination or empty when this is not a retired route.
	 */
	public static function target( $request_uri ) {
		if ( ! Hub::base_url() ) {
			return '';
		}
		$request = wp_parse_url( $request_uri );
		$query   = array();
		parse_str( $request['query'] ?? '', $query );
		$hub_path = trailingslashit( (string) wp_parse_url( Hub::base_url(), PHP_URL_PATH ) );
		foreach ( get_option( self::OPTION, array() ) as $page_id => $entry ) {
			$original = wp_parse_url( $entry['url'] );
			$path     = trailingslashit( $original['path'] ?? '/' );
			$current  = trailingslashit( $request['path'] ?? '/' );
			$by_id    = isset( $query['page_id'] ) && is_scalar( $query['page_id'] ) && absint( $query['page_id'] ) === (int) $page_id;
			$by_path  = empty( $original['query'] ) && ( $current === $path || str_starts_with( $current, $path ) );
			if ( ! $by_id && ! $by_path ) {
				continue;
			}
			if ( $by_path && ! $by_id && $path === $hub_path && $current === $hub_path ) {
				// The retired page used the address the hub now owns (for example /profile/): the hub itself is not a legacy route.
				continue;
			}
			$endpoint = $entry['endpoint'];
			$suffix   = $by_path ? trim( substr( $current, strlen( $path ) ), '/' ) : '';
			$segments = $suffix ? explode( '/', $suffix ) : array();
			if ( $segments ) {
				$endpoint = $segments[0];
				foreach ( \OhMyLMS\Commerce\ecommerce()->query->get_query_vars() as $name => $slug ) {
					if ( $endpoint === $slug ) {
						$endpoint = $name;
						break;
					}
				}
			} elseif ( isset( $query['view'] ) && is_string( $query['view'] ) ) {
				$endpoint = sanitize_key( $query['view'] );
			} elseif ( 'true' === ( $query['edit'] ?? '' ) ) {
				$endpoint = 'profile-edit';
			}
			if ( 'customer-logout' === $endpoint ) {
				return wp_logout_url( Hub::base_url() );
			}
			$args = array();
			foreach ( array( 'id', 'current_page' ) as $key ) {
				if ( isset( $query[ $key ] ) && is_scalar( $query[ $key ] ) ) {
					$args[ $key ] = absint( $query[ $key ] );
				}
			}
			if ( 'invoice-details' === $endpoint && isset( $segments[1] ) && ctype_digit( $segments[1] ) ) {
				$args['id'] = absint( $segments[1] );
			}
			return Hub::endpoint_url( $endpoint, $args );
		}
		return '';
	}

	/**
	 * Where the hub lived before its slug changed, with the full query string kept.
	 *
	 * Invitation emails and bookmarks (?ohmylms_invite=, ?hub_section=, ?hub_role=) keep working.
	 *
	 * @param string $request_uri Request URI, relative to the current site origin.
	 * @return string New hub URL, or empty when this is not the old hub address.
	 */
	public static function renamed_hub_target( $request_uri ) {
		$record = get_option( Hub::SLUG_OPTION );
		$base   = Hub::base_url();
		if ( ! is_array( $record ) || 'renamed' !== ( $record['result'] ?? '' ) || empty( $record['old_path'] ) || ! $base ) {
			return '';
		}
		$request = wp_parse_url( $request_uri );
		$old     = trailingslashit( (string) $record['old_path'] );
		$current = trailingslashit( $request['path'] ?? '/' );
		$new     = trailingslashit( (string) wp_parse_url( $base, PHP_URL_PATH ) );
		if ( $old !== $current || $new === $old ) {
			return '';
		}
		$query = (string) ( $request['query'] ?? '' );
		return '' === $query ? $base : $base . ( false === strpos( $base, '?' ) ? '?' : '&' ) . $query;
	}

	/**
	 * Whether a redirect would send the visitor to the address they asked for.
	 *
	 * @param string $target Destination URL.
	 * @param string $request_uri Request URI.
	 * @return bool
	 */
	public static function same_location( $target, $request_uri ) {
		$to   = wp_parse_url( $target );
		$from = wp_parse_url( $request_uri );
		return trailingslashit( $to['path'] ?? '/' ) === trailingslashit( $from['path'] ?? '/' ) && ( $to['query'] ?? '' ) === ( $from['query'] ?? '' );
	}

	/** Redirect GET bookmarks; form submissions stay with their existing handlers. */
	public static function redirect() {
		if ( 'GET' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			return;
		}
		$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
		if ( is_404() ) {
			$renamed = self::renamed_hub_target( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
			if ( $renamed ) {
				wp_safe_redirect( $renamed, 301 );
				exit;
			}
		}
		$target = self::target( $request_uri );
		if ( $target && ! self::same_location( $target, $request_uri ) ) {
			wp_safe_redirect( $target );
			exit;
		}
	}

	/**
	 * Canonical hub destination for an old school-portal view, or empty when it must stay.
	 *
	 * @param string $view Portal view name.
	 * @param array  $query Request query parameters.
	 * @return string
	 */
	public static function portal_target( $view, array $query ) {
		foreach ( array( 'ohmylms_invite', 'ohmylms_school_file' ) as $keep ) {
			if ( isset( $query[ $keep ] ) ) {
				return '';
			}
		}
		if ( ! Hub::base_url() ) {
			return '';
		}
		if ( in_array( $view, array( 'parent-dashboard', 'teacher-dashboard', 'student-assignments' ), true ) ) {
			$target = \OhMyLMS\Schools\Views::workspace_url( $view );
			return ! empty( $query['class'] ) ? add_query_arg( 'hub_class', absint( $query['class'] ), $target ) : $target;
		}
		return '';
	}

	/**
	 * Admin destination for the front-end school administration view, or empty when it must stay.
	 *
	 * Platform administration stays in wp-admin; school staff use the hub.
	 *
	 * @param string $view Portal view name.
	 * @param array  $query Request query parameters (flags and scalar values).
	 * @return string
	 */
	public static function admin_target( $view, array $query ) {
		if ( ! in_array( $view, array( '', 'school-dashboard' ), true ) || ! is_user_logged_in() || \OhMyLMS\Schools\ViewAs::session() ) {
			return '';
		}
		foreach ( array( 'ohmylms_invite', 'ohmylms_school_file' ) as $keep ) {
			if ( isset( $query[ $keep ] ) ) {
				return '';
			}
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			// School staff use the school workspace, never the WordPress dashboard.
			return SchoolWorkspace::available() ? SchoolWorkspace::url( 'school-overview', '', ! empty( $query['school'] ) ? absint( $query['school'] ) : 0 ) : '';
		}
		$args = array( 'page' => 'ohmylms-schools' );
		foreach ( array( 'school', 'class' ) as $key ) {
			if ( ! empty( $query[ $key ] ) ) {
				$args[ $key ] = absint( $query[ $key ] );
			}
		}
		if ( ! empty( $query['tab'] ) && in_array( $query['tab'], array( 'students', 'teachers', 'parents', 'users', 'classes', 'schools' ), true ) ) {
			$args['tab'] = $query['tab'];
		}
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/** Redirect the retired parent, student and school portal views for eligible signed-in users; GET only. */
	public static function redirect_portal() {
		if ( 'GET' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) || ! isset( $_GET['ohmylms_portal'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only legacy navigation.
			return;
		}
		$query = array();
		$keys  = array( 'ohmylms_invite', 'ohmylms_school_file', 'school' );
		foreach ( $keys as $key ) {
			if ( isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presence check only.
				$query[ $key ] = 1;
			}
		}
		$view = isset( $_GET['view'] ) && is_string( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only legacy navigation.
		if ( isset( $query['ohmylms_school_file'] ) ) {
			return; // Authorized file downloads are not a portal screen.
		}
		$invite = isset( $_GET['ohmylms_invite'] ) && is_string( $_GET['ohmylms_invite'] ) ? sanitize_text_field( wp_unslash( $_GET['ohmylms_invite'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Opaque single-use token forwarded to the hub.
		if ( Hub::base_url() && preg_match( '/^[a-f0-9]{64}$/', $invite ) ) {
			wp_safe_redirect( add_query_arg( 'ohmylms_invite', $invite, Hub::base_url() ) );
			exit;
		}
		$class_id = isset( $_GET['class'] ) && is_scalar( $_GET['class'] ) ? absint( $_GET['class'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation only; the workspace reauthorizes this class.
		$target   = self::portal_target( $view, array_merge( $query, array( 'class' => $class_id ) ) );
		if ( ! $target ) {
			$values = array();
			foreach ( array( 'school', 'class', 'tab' ) as $key ) {
				if ( isset( $_GET[ $key ] ) && is_scalar( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only legacy navigation.
					$values[ $key ] = sanitize_key( wp_unslash( (string) $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only legacy navigation.
				}
			}
			$target = self::admin_target( $view, array_merge( $query, $values ) );
		}
		if ( ! $target && ! is_user_logged_in() && Hub::base_url() ) {
			$target = Hub::base_url();
		}
		if ( $target ) {
			wp_safe_redirect( $target );
			exit;
		}
	}

	/**
	 * Keep explicit provisioning from recreating pages already migrated to the hub.
	 *
	 * @param array $pages Default page definitions.
	 * @return array
	 */
	public static function default_pages( $pages ) {
		if ( Hub::base_url() ) {
			foreach ( get_option( self::OPTION, array() ) as $entry ) {
				$key = str_replace( array( 'ohmylms_', '_page_id' ), '', $entry['option'] );
				unset( $pages[ $key ] );
			}
		}
		return $pages;
	}
}
