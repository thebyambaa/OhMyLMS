<?php
/**
 * One colour-coded mastery cell: a letter and hidden text label as well as the colour.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Performance\Levels;
use OhMyLMS\Skills\UnitReport;

defined( 'ABSPATH' ) || exit;

$cell_key    = isset( $cell_key ) && in_array( $cell_key, Levels::SCALE, true ) ? $cell_key : 'not-started';
$cell_review = ! empty( $cell_review );
$cell_title  = isset( $cell_title ) ? (string) $cell_title : '';
?>
<td class="ohmylms-mx-cell ohmylms-mx-<?php echo esc_attr( $cell_key ); ?>"<?php echo $cell_title ? ' title="' . esc_attr( $cell_title ) . '"' : ''; ?>><span aria-hidden="true"><?php echo esc_html( UnitReport::short( $cell_key ) ); ?></span><span class="ohmylms-mx-sr"><?php echo esc_html( Levels::label( $cell_key ) ); ?></span>
<?php
if ( $cell_review ) :
	?>
	<span class="ohmylms-mx-review" aria-hidden="true">&#8635;</span><span class="ohmylms-mx-sr"><?php echo esc_html( ', ' . __( 'review due', 'ohmylms' ) ); ?></span><?php endif; ?></td>
