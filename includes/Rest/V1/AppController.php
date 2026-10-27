<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use WP_REST_Server;
use WP_REST_Response;
use WP_Error;

/**
 * Plugin app controller implementation.
 */
class AppController extends RestController {

	/**
	 * Base.
	 *
	 * @var mixed
	 */
	protected $base = 'app';

	/**
	 * Register API routes
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->base . '/data',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_app_data' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'type' => array(
							'description' => 'Type of data to fetch (categories, integrations, currency_settings, all)',
							'type'        => 'string',
							'required'    => false,
						),
					),
				),
			)
		);
	}

	/**
	 * Permission check (same as AnalyticsController)
	 *
	 * @param mixed $request REST request.
	 */
	public function get_items_permissions_check( $request ) {
		return \current_user_can( 'edit_posts' );
	}

	/**
	 * Main handler for fetching app data
	 *
	 * @param mixed $request REST request.
	 */
	public function get_app_data( $request ) {
		$type   = $request->get_param( 'type' );
		$result = array();

		if ( empty( $type ) || 'all' === $type ) {
			$result['categories']          = $this->get_categories();
			$result['integrations']        = $this->get_integrations();
			$result['currency_settings']   = $this->get_currency_settings();
			$result['engagement_settings'] = $this->get_engagement_settings();        } else {
			switch ( $type ) {
				case 'categories':
					$result['categories'] = $this->get_categories();
					break;
				case 'integrations':
					$result['integrations'] = $this->get_integrations();
					break;
				case 'currency_settings':
					$result['currency_settings'] = $this->get_currency_settings();
					break;
				case 'engagement_settings':
					$result['engagement_settings'] = $this->get_engagement_settings();
					break;
				default:
					return new \WP_Error( 'invalid_type', \__( 'Invalid type parameter.', 'ohmylms' ), array( 'status' => 400 ) );
			}
			}

			return \rest_ensure_response( $result );
	}

	/**
	 * Get the additional resources for a course.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_engagement_settings() {
		if ( ! ohmylms_is_pro() ) {
			return array();
		}
		$point_settings  = \OhMyLMS\Engagement\Point::get_rules();
		$badge_settings  = \OhMyLMS\Engagement\Badge::get_rules();
		$reward_settings = \OhMyLMS\Engagement\Reward::get_rules();

		return array(
			'point_settings'  => $point_settings,
			'badge_settings'  => $badge_settings,
			'reward_settings' => $reward_settings,
		);
	}


	/**
	 * The curriculum, shaped like the course categories the admin app was built around. Course categories
	 * were replaced by curriculum items: the app's course-list filter still reads categories, so each
	 * item appears with its ID, name, parent and a filter slug.
	 */
	private function get_categories() {
		$categories = array();
		foreach ( \OhMyLMS\Curriculum\Items::all() as $row ) {
			$categories[] = array(
				'id'     => (int) $row['id'],
				'name'   => $row['name'],
				'slug'   => \OhMyLMS\Curriculum\Placement::item_slug( (int) $row['id'] ),
				'parent' => (int) $row['parent_id'],
			);
		}
		return $categories;
	}

	/**
	 * Get integrations from wp_options
	 */
	private function get_integrations() {
		$is_community_active = defined( 'OHMYLMS_COMMUNITY_VERSION' );

		if ( ! ohmylms_is_pro() && ! $is_community_active ) {
			return array();
		} elseif ( $is_community_active && ! ohmylms_is_pro() ) {
			$integrations = array(
				'community' => array(
					'is_enable' => 1,
				),
			);
			return $integrations;
		}

		$integrations = get_option( 'ohmylms_integrations', array() );
		unset( $integrations['ai_model'], $integrations['question_bank'] );
		if ( $is_community_active ) {
			$integrations['community']['is_enable'] = 1;
		} else {
			$integrations['community']['is_enable'] = 0;
		}

		return $integrations;
	}

	/**
	 * Get currency settings from wp_options
	 */
	private function get_currency_settings() {
		$keys     = array(
			'ohmylms_currency',
			'ohmylms_currency_pos',
			'ohmylms_price_thousand_sep',
			'ohmylms_price_decimal_sep',
			'ohmylms_price_num_decimals',
		);
		$settings = array();
		foreach ( $keys as $key ) {
			$settings[ $key ] = get_option( $key, '' );
		}
		return $settings;
	}
}
