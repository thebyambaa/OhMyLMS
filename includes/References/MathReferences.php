<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping.
/**
 * Shared mathematics references and inline previews for published content.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\References;

use OhMyLMS\Assessment\MathLive;

defined( 'ABSPATH' ) || exit;

/** Public reference entries for formulas, definitions and mathematicians. */
final class MathReferences {

	const POST_TYPE = 'ohmylms-reference';
	const META_KEY  = '_ohmylms_reference';

	/** Register the WordPress hooks. */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor_assets' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_frontend_assets' ) );
		add_filter( 'the_content', array( __CLASS__, 'add_previews' ), 25 );
	}

	/** Register a shared reference catalog with stable, ID-based WordPress permalinks. */
	public static function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Math References', 'ohmylms' ),
					'singular_name' => __( 'Math Reference', 'ohmylms' ),
					'add_new_item'  => __( 'Add Math Reference', 'ohmylms' ),
					'edit_item'     => __( 'Edit Math Reference', 'ohmylms' ),
				),
				'public'          => true,
				'show_in_rest'    => true,
				'show_in_menu'    => OHMYLMS_SLUG,
				'capability_type' => 'page',
				'map_meta_cap'    => true,
				'rewrite'         => false,
				'supports'        => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
			)
		);
	}

	/** Register the settings form next to the editor. */
	public static function add_meta_box(): void {
		add_meta_box( 'ohmylms-reference-settings', __( 'Reference details', 'ohmylms' ), array( __CLASS__, 'meta_box' ), self::POST_TYPE, 'side' );
	}

	/**
	 * Render the metadata form.
	 *
	 * @param \WP_Post $post Edited entry.
	 */
	public static function meta_box( \WP_Post $post ): void {
		$meta = self::metadata( $post->ID );
		wp_nonce_field( self::META_KEY . $post->ID, '_ohmylms_reference_nonce' );
		?>
		<p><label for="ohmylms-reference-kind"><?php esc_html_e( 'Reference type', 'ohmylms' ); ?></label><br>
			<select id="ohmylms-reference-kind" name="ohmylms_reference_kind">
				<?php foreach ( self::kinds() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $meta['kind'], $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p><label for="ohmylms-reference-equation"><?php esc_html_e( 'Equation in LaTeX (formulas)', 'ohmylms' ); ?></label><br>
			<textarea class="widefat" id="ohmylms-reference-equation" name="ohmylms_reference_equation" rows="3" maxlength="2000"><?php echo esc_textarea( $meta['equation'] ); ?></textarea>
		</p>
		<p><label for="ohmylms-reference-source"><?php esc_html_e( 'Source URL', 'ohmylms' ); ?></label><br>
			<input class="widefat" id="ohmylms-reference-source" name="ohmylms_reference_source" type="url" value="<?php echo esc_attr( $meta['source'] ); ?>">
		</p>
		<p><?php esc_html_e( 'Use the Excerpt for the short preview. Add a featured image to show a portrait for a mathematician.', 'ohmylms' ); ?></p>
		<?php
	}

	/**
	 * Save the entry settings after the authenticated WordPress post save.
	 *
	 * @param int $post_id Reference ID.
	 */
	public static function save( $post_id ): void {
		$nonce = self::posted_string( '_ohmylms_reference_nonce' );
		if ( ! wp_verify_nonce( $nonce, self::META_KEY . $post_id ) || ! current_user_can( 'edit_post', $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$kind     = sanitize_key( self::posted_string( 'ohmylms_reference_kind' ) );
		$equation = sanitize_textarea_field( self::posted_string( 'ohmylms_reference_equation' ) );
		update_post_meta(
			$post_id,
			self::META_KEY,
			wp_slash(
				array(
					'kind'     => isset( self::kinds()[ $kind ] ) ? $kind : 'definition',
					'equation' => strlen( $equation ) <= 2000 ? $equation : '',
					'source'   => esc_url_raw( self::posted_string( 'ohmylms_reference_source' ), array( 'http', 'https' ) ),
				)
			)
		);
	}

	/** Provide a toolbar reference picker in Gutenberg. */
	public static function editor_assets(): void {
		wp_enqueue_script(
			'ohmylms-reference-editor',
			plugins_url( 'assets/src/blocks/math-reference/editor.js', OHMYLMS_FILE ),
			array( 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-rich-text' ),
			OHMYLMS_VERSION,
			true
		);
		wp_set_script_translations( 'ohmylms-reference-editor', 'ohmylms' );

		$options = array();
		foreach ( get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'has_password' => false ) ) as $post ) {
			$meta      = self::metadata( $post->ID );
			$options[] = array(
				'value' => (string) $post->ID,
				'label' => $post->post_title . ' · ' . self::kinds()[ $meta['kind'] ],
				'url'   => get_permalink( $post ),
			);
		}

		wp_localize_script( 'ohmylms-reference-editor', 'ohmylmsMathReferences', array( 'options' => $options ) );
	}

	/** Register the interaction without loading it on unrelated pages. */
	public static function register_frontend_assets(): void {
		wp_register_script( 'ohmylms-reference-preview', plugins_url( 'assets/src/blocks/math-reference/preview.js', OHMYLMS_FILE ), array(), OHMYLMS_VERSION, true );
		wp_register_style( 'ohmylms-reference-preview', plugins_url( 'assets/css/reference-preview.css', OHMYLMS_FILE ), array(), OHMYLMS_VERSION );
	}

	/**
	 * Add reference previews to rendered content and reference details to the single entry.
	 *
	 * @param string $html Rendered post content.
	 * @return string Content with validated links and inert preview templates.
	 */
	public static function add_previews( string $html ): string {
		if ( is_admin() || is_feed() ) {
			return $html;
		}
		if ( is_singular( self::POST_TYPE ) && is_main_query() && in_the_loop() && get_the_ID() === get_queried_object_id() ) {
			$post = get_post();
			if ( self::available( $post ) ) {
				$html = self::reference_details( $post ) . $html;
			}
		}
		if ( false === strpos( $html, 'data-ohmylms-reference' ) ) {
			return $html;
		}

		$links = new \WP_HTML_Tag_Processor( $html );
		$cards = array();
		$group = '';
		while ( $links->next_tag( array( 'tag_name' => 'A', 'class_name' => 'ohmylms-reference-link' ) ) ) {
			$id = absint( $links->get_attribute( 'data-ohmylms-reference' ) );
			if ( ! $id ) {
				continue;
			}
			$post = get_post( $id );
			if ( ! self::available( $post ) ) {
				$links->remove_attribute( 'data-ohmylms-reference' );
				$links->remove_attribute( 'href' );
				continue;
			}

			$group = $group ?: wp_unique_id( 'ohmylms-reference-' );
			$key   = $group . '-' . $id;
			$url   = get_permalink( $post );
			$links->set_attribute( 'href', esc_url_raw( $url ) );
			$links->set_attribute( 'data-ohmylms-preview', $key );
			if ( ! isset( $cards[ $id ] ) ) {
				$cards[ $id ] = self::card( $post, $url, $key );
			}
		}
		if ( $cards ) {
			wp_enqueue_script( 'ohmylms-reference-preview' );
			wp_enqueue_style( 'ohmylms-reference-preview' );
		}

		return $links->get_updated_html() . implode( '', $cards );
	}

	/**
	 * Compose the preview from public entry fields.
	 *
	 * @param \WP_Post $post Reference entry.
	 * @param string   $url  Full entry URL.
	 * @param string   $key  Document-unique template key.
	 * @return string Inert preview template.
	 */
	private static function card( \WP_Post $post, string $url, string $key ): string {
		$meta     = self::metadata( $post->ID );
		$summary  = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_excerpt ?: $post->post_content ), true ), 36 );
		$portrait = 'mathematician' === $meta['kind'] ? get_the_post_thumbnail( $post, 'thumbnail', array( 'loading' => 'lazy' ) ) : '';
		return '<template id="' . esc_attr( $key ) . '"><div class="ohmylms-reference-card" role="dialog" aria-labelledby="' . esc_attr( $key . '-title' ) . '" popover="auto">'
			. '<div class="ohmylms-reference-card-header"><span>' . esc_html( self::kinds()[ $meta['kind'] ] ) . '</span>'
			. '<button type="button" class="ohmylms-reference-close" aria-label="' . esc_attr__( 'Close preview', 'ohmylms' ) . '">&times;</button></div>'
			. '<div id="' . esc_attr( $key . '-title' ) . '" class="ohmylms-reference-card-title">' . esc_html( $post->post_title ) . '</div>'
			. $portrait . self::equation( $meta ) . ( $summary ? '<p>' . esc_html( $summary ) . '</p>' : '' )
			. '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Read full reference', 'ohmylms' ) . '</a></div></template>';
	}

	/**
	 * Include the equation and citation on the full reference page.
	 *
	 * @param \WP_Post $post Reference entry.
	 * @return string Details markup.
	 */
	private static function reference_details( \WP_Post $post ): string {
		$meta = self::metadata( $post->ID );
		return '<p>' . esc_html( self::kinds()[ $meta['kind'] ] ) . '</p>' . self::equation( $meta )
			. ( $meta['source'] ? '<p><a href="' . esc_url( $meta['source'] ) . '">' . esc_html__( 'Reference source', 'ohmylms' ) . '</a></p>' : '' );
	}

	/**
	 * Reuse the LMS equation renderer for the optional formula.
	 *
	 * @param array $meta Reference metadata.
	 * @return string Equation markup with a readable LaTeX fallback.
	 */
	private static function equation( array $meta ): string {
		if ( 'formula' !== $meta['kind'] || '' === $meta['equation'] ) {
			return '';
		}
		if ( ! MathLive::enabled() ) {
			return '<pre>' . esc_html( $meta['equation'] ) . '</pre>';
		}
		MathLive::enqueue();
		return '<div data-ohmylms-latex>[[ohmylms-math:latex:display]]' . esc_html( $meta['equation'] ) . '[[/ohmylms-math]]</div>';
	}

	/**
	 * Return a value submitted by the WordPress editor for contextual sanitization.
	 *
	 * @param string $key Form input name.
	 * @return string Submitted value.
	 */
	private static function posted_string( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- The caller verifies the nonce and sanitizes each value for its context.
		return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
	}

	/**
	 * Read metadata with safe defaults for entries saved from imports or Quick Edit.
	 *
	 * @param int $id Reference ID.
	 * @return array Reference metadata.
	 */
	private static function metadata( int $id ): array {
		$meta = wp_parse_args( (array) get_post_meta( $id, self::META_KEY, true ), array( 'kind' => 'definition', 'equation' => '', 'source' => '' ) );
		$meta['kind'] = isset( self::kinds()[ $meta['kind'] ] ) ? $meta['kind'] : 'definition';
		return $meta;
	}

	/**
	 * Check whether an entry can be displayed publicly without cookies.
	 *
	 * @param \WP_Post|null $post Entry candidate.
	 * @return bool Publicly available reference.
	 */
	private static function available( $post ): bool {
		return $post && self::POST_TYPE === $post->post_type && 'publish' === $post->post_status && '' === $post->post_password;
	}

	/**
	 * Return supported reference types.
	 *
	 * @return array<string,string> Type labels.
	 */
	private static function kinds(): array {
		return array(
			'formula'       => __( 'Formula', 'ohmylms' ),
			'definition'    => __( 'Definition', 'ohmylms' ),
			'mathematician' => __( 'Mathematician', 'ohmylms' ),
		);
	}
}
