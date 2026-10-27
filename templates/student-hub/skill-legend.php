<?php
/**
 * Legend for the Khan mastery levels.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Performance\Levels;
use OhMyLMS\Skills\UnitReport;

defined( 'ABSPATH' ) || exit;
?>
<ul class="ohmylms-mx-legend" aria-label="<?php esc_attr_e( 'Mastery levels', 'ohmylms' ); ?>">
	<?php foreach ( array_reverse( Levels::SCALE ) as $legend_key ) : ?>
		<li><span class="ohmylms-mx-cell ohmylms-mx-<?php echo esc_attr( $legend_key ); ?>" aria-hidden="true"><?php echo esc_html( UnitReport::short( $legend_key ) ); ?></span><?php echo esc_html( Levels::label( $legend_key ) ); ?></li>
	<?php endforeach; ?>
	<li><span class="ohmylms-mx-review" aria-hidden="true">&#8635;</span><?php esc_html_e( 'Review due', 'ohmylms' ); ?></li>
</ul>
