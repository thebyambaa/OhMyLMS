<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class CustomEndpoints.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS;

/**
 * Class CustomEndpoints
 *
 * Handles custom endpoints for the e-commerce package.
 */
class OhMyLmsEndpoint {

	/**
	 * Query variables for custom endpoints.
	 *
	 * @var array $query_vars Query variables for custom endpoints.
	 */
	public $query_vars = array();

	/**
	 * CustomEndpoints constructor.
	 *
	 * Initializes query variables and adds endpoints.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->init_query_vars();
		$this->add_endpoints();
		add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );
		add_action( 'parse_request', array( $this, 'parse_request' ), 0 );
	}

	/**
	 * Initializes query variables.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function init_query_vars() {
		$this->query_vars = array(
			'profile'                => get_option( 'ohmylms_myprofile_profile_endpoint', 'profile' ),
			'profile-edit'           => get_option( 'ohmylms_myprofile_profile_edit_endpoint', 'profile-edit' ),
			'my-courses'             => get_option( 'ohmylms_myprofile_course_endpoint', 'my-courses' ),
			'settings'               => get_option( 'ohmylms_myprofile_settings_endpoint', 'settings' ),
			'notification'           => get_option( 'ohmylms_myprofile_notification_endpoint', 'notification' ),
			'transactions-history'   => get_option( 'ohmylms_myprofile_transactions_history_endpoint', 'transactions-history' ),
			'membership'             => get_option( 'ohmylms_myprofile_membership_endpoint', 'membership' ),
			'invoice-details'        => get_option( 'ohmylms_myprofile_invoice_details_endpoint', 'invoice-details' ),
			'billing-information'    => get_option( 'ohmylms_myprofile_edit_account_endpoint', 'billing-information' ),
			'customer-logout'        => get_option( 'ohmylms_logout_endpoint', 'customer-logout' ),
			'ohmylms-order-received' => 'ohmylms-order-received',
		);
	}

	/**
	 * Adds custom endpoints.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function add_endpoints() {
		foreach ( $this->get_query_vars() as $key => $var ) {
			if ( ! empty( $var ) ) {
				add_rewrite_endpoint( $var, EP_PAGES );
			}
		}
	}

	/**
	 * Retrieves query variables.
	 *
	 * @return array Query variables.
	 * @since 1.0.0
	 */
	public function get_query_vars() {
		return $this->query_vars;
	}

	/**
	 * Adds query variables to the list of public query variables.
	 *
	 * @param array $vars Existing query variables.
	 * @return array Modified query variables.
	 *
	 * @since 1.0.0
	 */
	public function add_query_vars( $vars ) {
		foreach ( $this->get_query_vars() as $key => $var ) {
			$vars[] = $key;
		}
		return $vars;
	}

	/**
	 * Gets the current endpoint.
	 *
	 * @global \WP $wp WordPress environment object.
	 * @return string Current endpoint key.
	 *
	 * @since 1.0.0
	 */
	public function get_current_endpoint() {
		global $wp;
		foreach ( $this->get_query_vars() as $key => $value ) {
			if ( isset( $wp->query_vars[ $key ] ) ) {
				return $key;
			}
		}
		return '';
	}

	/**
	 * Gets the title for a specific endpoint.
	 *
	 * @global \WP $wp WordPress environment object.
	 * @param string $endpoint Endpoint key.
	 * @param string $action Optional. Action associated with the endpoint.
	 * @return string Endpoint title.
	 *
	 * @since 1.0.0
	 */
	public function get_endpoint_title( $endpoint, $action = '' ) {
		global $wp;

		switch ( $endpoint ) {
			case 'ohmylms-order-received':
				// $title = __( 'Order received', 'ohmylms' );
				break;
			default:
				$title = '';
				break;
		}
		return $title;
	}

	/**
	 * Parse request for Oh my lms endpoint.
	 */
	public function parse_request() {
		global $wp;

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		// Map query vars to their keys, or get them if endpoints are not supported.
		foreach ( $this->get_query_vars() as $key => $var ) {
			if ( isset( $_GET[ $var ] ) ) {
				$wp->query_vars[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $var ] ) );
			} elseif ( isset( $wp->query_vars[ $var ] ) ) {
				$wp->query_vars[ $key ] = $wp->query_vars[ $var ];
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}
}
