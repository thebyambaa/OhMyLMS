<?php
/**
 * Template for displaying thankyou.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/checkout/thankyou.php
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 * @global \OhMyLMS\Commerce\Checkout $checkout
 *
 * @var \OhMyLMS\Commerce\Data\Order $order
 * @var \OhMyLMS\Data\Student $student
 */

defined( 'ABSPATH' ) || exit();
do_action( 'ohmylms_account_header' );
$dashboard_url = ohmylms_get_page_permalink( 'student_dashboard' );
$status        = $order ? $order->get_status() : '';
?>

<?php ohmylms_get_template( 'global/ohmylms-celebration.php' ); ?>

<div class="ohmylms-order">
	<?php
	if ( $order ) :
		$items     = $order->get_items();
		$is_course = true;
		foreach ( $items as $item ) {
			$course    = $item->get_course();
			$is_course = true;
			if ( ! $course ) {
				$id     = $item->get_course_id();
				$course = ohmylms_get_membership( $id );
				if ( ! $course ) {
					continue;
				}
				$is_course = false;
			}
		}
		$item_object_name = $is_course ? 'Course' : 'Membership';

		do_action( 'ohmylms_before_thankyou', $order->get_id() );
		?>
		<section class="ohmylms-thankyou">
			<div class="ohmylms-container">
				<div class="ohmylms-thankyou-content">
					<div class="ohmylms-thankyou-content-left">
						<div class="ohmylms-thankyou-head">
							<?php if ( $order->has_status( 'failed' ) ) : ?>
								<span class="thakyou-icon">
									<svg width="72" height="71" fill="none" viewBox="0 0 72 71" xmlns="http://www.w3.org/2000/svg"><rect width="71" height="71" x=".5" fill="#FF4955" rx="35.5"/><path fill="#fff" d="M36 18c-9.94 0-18 8.06-18 18s8.06 18 18 18 18-8.06 18-18-8.06-18-18-18zm0 32.4c-7.942 0-14.4-6.458-14.4-14.4 0-7.942 6.458-14.4 14.4-14.4 7.942 0 14.4 6.458 14.4 14.4 0 7.942-6.458 14.4-14.4 14.4zm-1.8-23.4h3.6v10.8h-3.6V27zm0 14.4h3.6V45h-3.6v-3.6z"/></svg>
								</span>

								<h1 class="ohmylms-thankyou-title">
									<?php echo esc_html__( 'Payment Failed', 'ohmylms' ); ?>
								</h1>

								<div class="ohmylms-thankyou-text">
									<?php
									$failed_text = __( '<strong>Unfortunately, your payment could not be processed.</strong> Please try again or contact support if the issue persists.', 'ohmylms' );
									echo apply_filters( 'ohmylms_thankyou_failed_text', $failed_text, $order );
									?>
								</div>

								<div class="ohmylms-btn-area">
									<a href="<?php echo esc_url( ohmylms_get_page_permalink( 'checkout' ) ); ?>" class="ohmylms-button">
										<?php echo esc_html__( 'Return To Checkout', 'ohmylms' ); ?>
									</a>
								</div>
							<?php else : ?>
								<span class="thakyou-icon">
									<svg width="72" height="71" fill="none" viewBox="0 0 72 71" xmlns="http://www.w3.org/2000/svg"><rect width="71" height="71" x=".5" fill="#039814" rx="35.5"/><path fill="#fff" fill-rule="evenodd" d="M54.381 23.682a2.07 2.07 0 010 2.928l-17.78 17.78a6.213 6.213 0 01-8.785 0l-7.426-7.426a2.07 2.07 0 012.929-2.928l7.425 7.425a2.07 2.07 0 002.929 0l17.78-17.78a2.07 2.07 0 012.928 0z" clip-rule="evenodd"/></svg>
								</span>

								<h1 class="ohmylms-thankyou-title">
									<?php
										printf( /* translators: %s: text value. */
											esc_html__( 'Thank You %s', 'ohmylms' ),
											esc_html( $student->get_name() )
										);
									?>
								</h1>

								<div class="ohmylms-thankyou-text">
									<?php
									if ( 'completed' !== $status ) {
										$thankyou_text = sprintf(
										/* translators: %s: order status. */
											__( '<strong>Your Order is %s!</strong>', 'ohmylms' ),
											esc_html( ucfirst( $status ) )
										);
									} else {
										$thankyou_text = sprintf(
										/* translators: 1: order status, 2: customer email. */
											__( '<strong>Your Order is %1$s!</strong> A confirmation mail has been sent to <br/> <span>%2$s</span>.', 'ohmylms' ),
											esc_html( ucfirst( $status ) ),
											esc_html( $order->get_email() )
										);
									}


									/**
									 * Filter the thank you message on the order confirmation page.
									 *
									 * @param string $thankyou_text The thank you message.
									 * @param WC_Order $order The order object.
									 */
									echo wp_kses_post( apply_filters( 'ohmylms_thankyou_text', $thankyou_text, $order, $is_course ) );
									?>
								</div>
								<?php if ( 'completed' === $status ) : ?>
								<div class="ohmylms-btn-area">
									<a href="<?php echo esc_url( $dashboard_url ); ?>" class="ohmylms-button">
										<?php
										printf( /* translators: %s: text value. */
											__( 'Access To Your %s', 'ohmylms' ),
											$item_object_name
										);
										?>
									</a>
								</div>
								<?php endif; ?>
							<?php endif; ?>
							</div>

							<div class="ohmylms-customer-details">
								<h2 class="ohmylms-address-title">
									<?php echo esc_html__( 'Student Details', 'ohmylms' ); ?>
								</h2>

								<p class="single-address email">
									<strong><?php echo esc_html__( 'Email' ); ?></strong>
									<?php echo esc_html( $student->get_email() ); ?>
								</p>

								<p class="single-address country">
									<strong><?php echo esc_html__( 'Country' ); ?></strong>
									<?php echo esc_html( ohmylms_get_country_name_by_code( $student->get_country() ) ); ?>
								</p>
							</div>

							<?php
							/**
							 * Hook: ohmylms_after_thankyou.
							 *
							 * @param $order
							 */
							do_action( 'ohmylms_after_thankyou', $order );
							?>

						</div>

						<div class="ohmylms-thankyou-content-right">
							<div class="ohmylms-thankyou-table-wrapper order-summary">
								<h2 class="ohmylms-order-summary-title">
									<?php echo esc_html__( 'Order Summary', 'ohmylms' ); ?>
								</h2>
								<?php
									ohmylms_get_template(
										'order/order-details.php',
										array(
											'order' => $order,
										)
									);
								?>
							</div>
							<?php
							do_action( 'ohmylms_after_thankyou_table', $order, $is_course );
							?>
						</div>

					</div>


				</div>
			</section>

		<?php do_action( 'ohmylms_thankyou_' . $order->get_payment_method(), $order->get_id() ); ?>
		<?php do_action( 'ohmylms_thankyou', $order->get_id() ); ?>

		<?php
	endif;
	?>
</div>
