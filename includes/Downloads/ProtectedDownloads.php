<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping.
/**
 * Protected download catalog, Gutenberg block and file delivery.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Downloads;

use OhMyLMS\Membership\Members;
use OhMyLMS\Utility\Lock;

defined( 'ABSPATH' ) || exit;

/** Membership-restricted PDFs for lessons and WordPress content. */
final class ProtectedDownloads {

	const POST_TYPE = 'ohmylms-download';
	const META_KEY  = '_ohmylms_protected_download';
	const ACTION    = 'ohmylms_protected_download';

	/** Register hooks. */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'deliver' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( __CLASS__, 'deliver' ) );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( __CLASS__, 'add_meta_box' ) );
		add_action( 'post_edit_form_tag', array( __CLASS__, 'form_tag' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'schedule_cleanup' ), 10, 2 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor_data' ) );
	}

	/** Register the catalog and server-rendered block. */
	public static function register(): void {
		$capabilities = array_fill_keys(
			array( 'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'delete_posts', 'publish_posts', 'read_private_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts', 'edit_private_posts', 'edit_published_posts', 'create_posts' ),
			'manage_options'
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Downloads', 'ohmylms' ),
					'singular_name' => __( 'Protected Download', 'ohmylms' ),
					'add_new_item'  => __( 'Add Protected Download', 'ohmylms' ),
					'edit_item'     => __( 'Edit Protected Download', 'ohmylms' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => OHMYLMS_SLUG,
				'supports'        => array( 'title' ),
				'capabilities'    => $capabilities,
				'map_meta_cap'    => false,
				'capability_type' => 'post',
			)
		);

		$script = 'assets/src/blocks/protected-download/editor.js';
		wp_register_script(
			'ohmylms-protected-download-editor',
			plugins_url( $script, OHMYLMS_FILE ),
			array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n' ),
			OHMYLMS_VERSION,
			true
		);
		wp_set_script_translations( 'ohmylms-protected-download-editor', 'ohmylms' );

		register_block_type(
			'ohmylms/protected-download',
			array(
				'api_version'     => 3,
				'attributes'      => array(
					'downloadId' => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
				'editor_script'   => 'ohmylms-protected-download-editor',
				'render_callback' => array( __CLASS__, 'render' ),
			)
		);
	}

	/** Make published titles available to the editor without exposing private file metadata. */
	public static function editor_data(): void {
		$options = array();
		foreach ( self::published_posts( self::POST_TYPE ) as $post ) {
			$record = self::record( $post->ID );
			if ( ! empty( $record['file']['key'] ) && ! empty( $record['plans'] ) ) {
				$options[] = array(
					'label' => $post->post_title,
					'value' => (string) $post->ID,
				);
			}
		}

		wp_localize_script(
			'ohmylms-protected-download-editor',
			'ohmylmsProtectedDownloads',
			array(
				'options'    => $options,
				'catalogUrl' => current_user_can( 'manage_options' ) ? admin_url( 'edit.php?post_type=' . self::POST_TYPE ) : '',
			)
		);
	}

	/** Register the PDF and membership form. */
	public static function add_meta_box(): void {
		add_meta_box( 'ohmylms-download-settings', __( 'File and membership access', 'ohmylms' ), array( __CLASS__, 'meta_box' ), self::POST_TYPE, 'normal', 'high' );
	}

	/**
	 * Enable uploads only on the download editor.
	 *
	 * @param \WP_Post $post Edited post.
	 */
	public static function form_tag( \WP_Post $post ): void {
		if ( self::POST_TYPE === $post->post_type ) {
			echo ' enctype="multipart/form-data"';
		}
	}

	/**
	 * Display the currently attached file and published membership plans.
	 *
	 * @param \WP_Post $post Edited download.
	 */
	public static function meta_box( \WP_Post $post ): void {
		$record = self::record( $post->ID );
		wp_nonce_field( self::ACTION . $post->ID, '_ohmylms_download_nonce' );

		$notice_key = 'ohmylms_download_error_' . get_current_user_id() . '_' . $post->ID;
		$notice     = get_transient( $notice_key );
		if ( is_string( $notice ) ) {
			delete_transient( $notice_key );
			echo '<p role="alert"><strong>' . esc_html( $notice ) . '</strong></p>';
		}

		$directory = PrivateFiles::directory();
		if ( ! $directory || ! is_writable( $directory ) ) {
			echo '<p>' . esc_html__( 'Set OHMYLMS_PRIVATE_DOWNLOAD_DIR in wp-config.php to a writable folder outside the web root to enable PDF uploads.', 'ohmylms' ) . '</p>';
		}

		if ( ! empty( $record['file']['name'] ) ) {
			/* translators: %s: Filename of the current PDF. */
			echo '<p>' . esc_html( sprintf( __( 'Current file: %s', 'ohmylms' ), $record['file']['name'] ) ) . '</p>';
		}
		?>
		<p><label for="ohmylms-download-file"><?php esc_html_e( 'Upload or replace PDF', 'ohmylms' ); ?></label>
			<input id="ohmylms-download-file" type="file" name="ohmylms_download_file" accept=".pdf,application/pdf"></p>
		<fieldset>
			<legend><strong><?php esc_html_e( 'Eligible membership plans', 'ohmylms' ); ?></strong></legend>
			<p><?php esc_html_e( 'Members need any one of the selected plans. Leave all plans unchecked to disable downloads.', 'ohmylms' ); ?></p>
			<?php foreach ( self::published_posts( 'ohmylms-membership' ) as $plan ) : ?>
				<div><label><input type="checkbox" name="ohmylms_download_plans[]" value="<?php echo esc_attr( $plan->ID ); ?>" <?php checked( in_array( $plan->ID, $record['plans'] ?? array(), true ) ); ?>> <?php echo esc_html( $plan->post_title ); ?></label></div>
			<?php endforeach; ?>
		</fieldset>
		<?php
		if ( 'publish' === $post->post_status && ! empty( $record['file']['key'] ) && ! empty( $record['plans'] ) ) {
			?>
			<p><label for="ohmylms-download-link"><?php esc_html_e( 'Download link', 'ohmylms' ); ?></label>
			<input id="ohmylms-download-link" class="large-text" readonly value="<?php echo esc_url( self::url( $post->ID ) ); ?>"></p>
			<?php
		}
	}

	/**
	 * Save policy and file together. A failed replacement retains the previous file and policy.
	 *
	 * @param int $post_id Download ID.
	 */
	public static function save( $post_id ): void {
		$nonce = isset( $_POST['_ohmylms_download_nonce'] ) && is_string( $_POST['_ohmylms_download_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_ohmylms_download_nonce'] ) ) : '';
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $nonce, self::ACTION . $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each scalar plan ID is normalized and checked against the published plans below.
		$requested = isset( $_POST['ohmylms_download_plans'] ) && is_array( $_POST['ohmylms_download_plans'] ) ? wp_unslash( $_POST['ohmylms_download_plans'] ) : array();
		$plans     = array_values( array_map( 'intval', array_intersect( wp_list_pluck( self::published_posts( 'ohmylms-membership' ), 'ID' ), array_map( 'absint', array_filter( $requested, 'is_scalar' ) ) ) ) );
		$lock      = 'protected-download:' . $post_id;
		if ( ! Lock::acquire( $lock, 3 ) ) {
			self::save_error( $post_id, __( 'Another request is updating this file. Try saving again.', 'ohmylms' ) );
			return;
		}

		try {
			$record        = self::record( $post_id );
			$previous_file = $record['file'] ?? array();
			$file          = $previous_file;
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- PrivateFiles validates the upload and sanitizes its filename.
			$upload = $_FILES['ohmylms_download_file'] ?? array();
			if ( UPLOAD_ERR_NO_FILE !== ( $upload['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
				$file = PrivateFiles::store( $upload );
				if ( is_wp_error( $file ) ) {
					self::save_error( $post_id, $file->get_error_message() );
					return;
				}
			}

			$next = array(
				'plans' => $plans,
				'file'  => $file,
			);
			if ( $next === $record ) {
				return;
			}
			if ( self::POST_TYPE !== get_post_type( $post_id ) || ! update_post_meta( $post_id, self::META_KEY, $next ) ) {
				if ( $file !== $previous_file ) {
					PrivateFiles::delete( $file['key'] );
				}
				self::save_error( $post_id, __( 'The download settings could not be saved.', 'ohmylms' ) );
				return;
			}
			if ( ! empty( $record['file']['key'] ) && $file !== $record['file'] ) {
				PrivateFiles::delete( $record['file']['key'] );
			}
		} finally {
			Lock::release( $lock );
		}
	}

	/**
	 * Schedule file removal after WordPress confirms deletion.
	 *
	 * @param int      $post_id Deleted post ID.
	 * @param \WP_Post $post    Post being deleted.
	 */
	public static function schedule_cleanup( $post_id, \WP_Post $post ): void {
		if ( self::POST_TYPE !== $post->post_type ) {
			return;
		}

		$lock = 'protected-download:' . $post_id;
		if ( ! Lock::acquire( $lock, 3 ) ) {
			wp_die( esc_html__( 'Another request is updating this file. Try deleting again.', 'ohmylms' ), '', array( 'response' => 409 ) );
		}

		$key = self::record( $post_id )['file']['key'] ?? '';
		add_action(
			'deleted_post',
			static function ( $deleted_id ) use ( $post_id, $key, $lock ) {
				if ( $deleted_id === $post_id ) {
					PrivateFiles::delete( $key );
					Lock::release( $lock );
				}
			}
		);
	}

	/**
	 * Render a cache-safe, public link to a privately authorized request.
	 *
	 * @param array $attributes Gutenberg attributes.
	 * @return string Download markup.
	 */
	public static function render( array $attributes ): string {
		$id   = absint( $attributes['downloadId'] ?? 0 );
		$post = $id ? get_post( $id ) : null;
		if ( ! self::is_published( $post ) ) {
			return '';
		}

		$record = self::record( $id );
		if ( empty( $record['file']['key'] ) || empty( $record['plans'] ) ) {
			return '';
		}

		/* translators: %s: Title of the protected PDF. */
		$label = sprintf( __( 'Download %s (PDF)', 'ohmylms' ), $post->post_title );
		return '<div ' . get_block_wrapper_attributes() . '><a href="' . esc_url( self::url( $id ) ) . '">' . esc_html( $label ) . '</a></div>';
	}

	/** Authorize the current request and stream the file as an attachment. */
	public static function deliver(): void {
		nocache_headers();
		header( 'Cache-Control: private, no-store, max-age=0', true );
		header( 'X-Robots-Tag: noindex', true );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request authorized against the current user's membership.
		$id = isset( $_GET['download'] ) && is_scalar( $_GET['download'] ) ? absint( wp_unslash( $_GET['download'] ) ) : 0;
		if ( ! $id || ! self::is_published( get_post( $id ) ) ) {
			self::unavailable();
		}

		$record = self::record( $id );
		$plans  = $record['plans'] ?? array();
		if ( ! $plans || empty( $record['file']['key'] ) ) {
			self::unavailable();
		}

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( self::url( $id ) ) );
			exit;
		}

		if ( ! Members::has_active_plan( get_current_user_id(), $plans ) ) {
			$links = array();
			foreach ( $plans as $plan_id ) {
				if ( 'ohmylms-membership' === get_post_type( $plan_id ) && 'publish' === get_post_status( $plan_id ) ) {
					$links[] = '<li><a href="' . esc_url( get_permalink( $plan_id ) ) . '">' . esc_html( get_the_title( $plan_id ) ) . '</a></li>';
				}
			}
			$message = esc_html__( 'This download requires an active membership.', 'ohmylms' );
			wp_die( $message . ( $links ? '<ul>' . implode( '', $links ) . '</ul>' : '' ), '', array( 'response' => 403 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each string and link was escaped above.
		}

		$path = PrivateFiles::path( $record['file']['key'] );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Stream the original private file without loading it into memory.
		$file = $path ? fopen( $path, 'rb' ) : false;
		if ( ! $file ) {
			self::unavailable();
		}

		$name = sanitize_file_name( $record['file']['name'] ?? 'download.pdf' );
		header( 'Content-Type: application/pdf' );
		header( 'X-Content-Type-Options: nosniff' );
		header( "Content-Disposition: attachment; filename=\"download.pdf\"; filename*=UTF-8''" . rawurlencode( $name ) );
		if ( 'HEAD' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fpassthru -- Stream the authorized binary response directly.
			fpassthru( $file );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the streaming handle.
		fclose( $file );
		exit;
	}

	/**
	 * Check if a post is an available download.
	 *
	 * @param \WP_Post|null $post Candidate post.
	 * @return bool Whether the post is a published download.
	 */
	private static function is_published( $post ): bool {
		return $post && self::POST_TYPE === $post->post_type && 'publish' === $post->post_status && '' === $post->post_password;
	}

	/**
	 * Read a download record.
	 *
	 * @param int $id Download ID.
	 * @return array File and access metadata.
	 */
	private static function record( int $id ): array {
		return (array) get_post_meta( $id, self::META_KEY, true );
	}

	/**
	 * Fetch catalog records for an editor picker.
	 *
	 * @param string $type Post type.
	 * @return \WP_Post[] Published entries.
	 */
	private static function published_posts( string $type ): array {
		return get_posts(
			array(
				'post_type'      => $type,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'has_password'   => false,
			)
		);
	}

	/**
	 * Build the shareable link that checks membership on request.
	 *
	 * @param int $id Download ID.
	 * @return string Download URL.
	 */
	private static function url( int $id ): string {
		return add_query_arg(
			array(
				'action'   => self::ACTION,
				'download' => $id,
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Carry a save error across WordPress's editor redirect.
	 *
	 * @param int    $id      Download ID.
	 * @param string $message Error to display.
	 */
	private static function save_error( int $id, string $message ): void {
		set_transient( 'ohmylms_download_error_' . get_current_user_id() . '_' . $id, $message, MINUTE_IN_SECONDS );
	}

	/** End a request for an unpublished or missing file. */
	private static function unavailable(): void {
		wp_die( esc_html__( 'This download is unavailable.', 'ohmylms' ), '', array( 'response' => 404 ) );
	}
}
