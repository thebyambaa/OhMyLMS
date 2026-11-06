<?php
/**
 * Mascot partial. Printed by Design\Mascot::render() when a placement is on.
 *
 * Override in yourtheme/ohmylms/mascot.php.
 *
 * @package OhMyLMS
 *
 * @var array  $mascot    Clean mascot (kind, attachment_id, name, greeting, placements).
 * @var string $placement Placement key.
 */

defined( 'ABSPATH' ) || exit;

$mascot    = isset( $mascot ) && is_array( $mascot ) ? $mascot : array();
$placement = isset( $placement ) ? sanitize_key( (string) $placement ) : '';
$name      = isset( $mascot['name'] ) ? (string) $mascot['name'] : '';
$greeting  = isset( $mascot['greeting'] ) ? (string) $mascot['greeting'] : '';
$kind      = isset( $mascot['kind'] ) ? (string) $mascot['kind'] : '';
$image     = \OhMyLMS\Design\Mascot::image_url( $mascot );
$label     = '' !== $name ? $name : __( 'Mascot', 'ohmylms' );
?>
<figure class="oml-mascot oml-mascot--<?php echo esc_attr( $placement ); ?>">
	<span class="oml-mascot-art" style="color:var(--oml-primary)">
		<?php if ( '' !== $image ) : ?>
			<img class="oml-mascot-img" src="<?php echo esc_url( $image ); ?>" alt="" width="64" height="64" decoding="async">
		<?php else : ?>
			<?php
			// Bundled drawings are an allowlisted SVG passed through wp_kses in Mascot::svg().
			echo \OhMyLMS\Design\Mascot::svg( $kind ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		<?php endif; ?>
	</span>
	<?php if ( '' !== $name || '' !== $greeting ) : ?>
		<figcaption class="oml-mascot-bubble">
			<?php if ( '' !== $name ) : ?>
				<strong class="screen-reader-text"><?php echo esc_html( $label ); ?>: </strong>
			<?php endif; ?>
			<?php
			if ( '' !== $name && '' !== $greeting ) {
				echo esc_html( $name . ': ' . $greeting );
			} else {
				echo esc_html( '' !== $greeting ? $greeting : $name );
			}
			?>
		</figcaption>
	<?php endif; ?>
</figure>
