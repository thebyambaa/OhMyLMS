<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Create Contact to MailMint and Appsero.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\SetupWizard;

/**
 * Create Contact to MailMint and Appsero
 *
 * @since 3.3.2
 */
class CreateContact {

	/**
	 * WebHookUrl.
	 *
	 * @var mixed
	 */
	protected $webHookUrl = array(
		'https://useraccount.getwpfunnels.com/?mailmint=1&route=webhook&topic=contact&hash=f9fcd11c-fde8-4c7f-9810-0ebb02d2740e',
	);

	/**
	 * Email
	 *
	 * @var string
	 * @since 3.3.2
	 */
	protected $email = '';

	/**
	 * Name
	 *
	 * @var string
	 * @since 3.3.2
	 */
	protected $name = '';

	/**
	 * Appsero URL
	 *
	 * @var string
	 * @since 3.3.2
	 */
	protected $appsero_url = 'https://api.appsero.com/';

	/**
	 * Appsero API Key
	 *
	 * @var string
	 * @since 3.3.2
	 */
	protected $appsero_api_key = '6fb1e340-8276-4337-bca6-28a7cd186f06';

	/**
	 * Plugin Name
	 *
	 * @var string
	 * @since 3.3.1
	 */
	protected $plugin_name = 'OhMyLMS';

	/**
	 * Plugin Slug
	 *
	 * @var string
	 * @since 3.3.2
	 */
	protected $plugin_slug = 'ohmylms';

	/**
	 * Plugin File
	 *
	 * @var string
	 * @since 3.3.2
	 */
	protected $plugin_file = __FILE__;

	/**
	 * Source
	 *
	 * @var string
	 * @since 3.3.2
	 */
	protected $source = 'setup-wizard'; // always it will be 'setup-wizard'.

	/**
	 * Setup data
	 *
	 * @var array
	 * @since 3.3.2
	 */
	protected $setup_data = array();

	/**
	 * Constructor
	 *
	 * @param string $email Email.
	 * @param string $name Name.
	 * @param array  $setup_data Setup data.
	 * @since 3.3.2
	 */
	public function __construct( $email, $name, $setup_data = array() ) {
		$this->email      = $email;
		$this->name       = $name;
		$this->setup_data = $setup_data;

		if ( 'setup-wizard' == $this->source ) {
			add_filter( $this->plugin_slug . '_tracker_data', array( $this, 'modify_contact_data' ), 10 );
		}
	}

	/**
	 * Create contact to MailMint via webhook
	 *
	 * @return array
	 * @since 3.3.2
	 */
	public function create_contact_via_webhook() {
		if ( ! $this->email ) {
			return array(
				'suceess' => false,
			);
		}

		$response = array(
			'suceess' => true,
		);

		$data = array(
			'email'               => $this->email,
			'first_name'          => $this->name,
			'level'               => $this->setup_data['level'] ?? '',
			'archive_page_layout' => $this->setup_data['archive_page_layout'] ?? '',
			'courses_per_row'     => $this->setup_data['courses_per_row'] ?? '',
			'currency'            => $this->setup_data['currency'] ?? '',
			'language'            => $this->setup_data['language'] ?? '',
			'certificate'         => $this->setup_data['certificate'] ?? '',
			'selectedPlatform'    => $this->setup_data['selectedPlatform'] ?? '',
			'niche'               => isset( $this->setup_data['niche'] )
				? ( is_array( $this->setup_data['niche'] )
					? implode( ',', $this->setup_data['niche'] )
					: $this->setup_data['niche'] )
				: '',
		);

		$json_body_data = wp_json_encode( $data );
		try {
			if ( ! empty( $this->webHookUrl ) ) {
				foreach ( $this->webHookUrl as $url ) {
					$response = wp_remote_request(
						$url,
						array(
							'method'  => 'POST',
							'headers' => array(
								'Content-Type' => 'application/json',
							),
							'body'    => $json_body_data,
						)
					);
				}
			}
		} catch ( \Exception $e ) {
			$response = array(
				'suceess' => false,
			);
		}

		return $response;
	}

	/**
	 * Send contact to Appsero
	 *
	 * @return void
	 * @since 3.3.2
	 */
	public function send_contact_to_appsero() {
		$client = new \Appsero\Client( $this->api_key, $this->plugin_name, $this->plugin_file );
		$client->insights()->send_tracking_data( true );
		update_option( $this->plugin_slug . '_allow_tracking', 'yes' );
		update_option( $this->plugin_slug . '_tracking_notice', ' hide' );
	}

	/**
	 * Modify contact data before sending to appsero
	 *
	 * @param array $data Input data.
	 * @return array
	 * @since 3.3.2
	 */
	public function modify_contact_data( $data ) {
		$data['admin_email'] = $this->email;
		$data['first_name']  = $this->name;
		return $data;
	}
}
