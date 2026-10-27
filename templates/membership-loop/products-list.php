<?php
/**
 * Membership Loop Products List
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/membership-loop/products-list.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

global $membership;
if ( null == $membership ) {
	return;
}
$courses = $membership->get_products();
if ( empty( $courses ) && ! is_array( $courses ) ) {
	return;
}
?>

<ul class="membership-courses" >
	<?php
	foreach ( $courses as $single_course ) {
		$course = ohmylms_get_course( $single_course['id'] );
		if ( empty( $course ) ) {
			continue;
		}
		?>
		<li>
			<a href="<?php echo esc_url( $course->get_permalink() ); ?>">
				<?php echo esc_html( $course->get_name() ); ?>
			</a>
		</li>
		<?php
	}
	?>
</ul>



