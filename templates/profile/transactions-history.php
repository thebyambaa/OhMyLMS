<?php
/**
 * Template for displaying transactions history of student profile
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/profile/transactions-history.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

defined( 'ABSPATH' ) || exit();


?>

<div class="ohmylms-student-profile-tab-content student-transaction-history">
	<h4 class="profile-tab-title">
		<?php echo esc_html__( 'Your Orders', 'ohmylms' ); ?>
	</h4>

	<div class="ohmylms-dashboard-table">
		<div class="dashboard-table-head">
			<div class="dashboard-table-tr">
				<div class="dashboard-table-td order-id">
					<?php echo esc_html__( 'Order ID', 'ohmylms' ); ?>
				</div>

				<div class="dashboard-table-td bold-td course-name">
					<?php echo esc_html__( 'Item Name', 'ohmylms' ); ?>
				</div>

				<div class="dashboard-table-td purchase-method">
					<?php echo esc_html__( 'Purchase Method', 'ohmylms' ); ?>
				</div>

				<div class="dashboard-table-td price">
					<?php echo esc_html__( 'Total', 'ohmylms' ); ?>
				</div>

				<div class="dashboard-table-td date">
					<?php echo esc_html__( 'Date', 'ohmylms' ); ?>
				</div>

				<div class="dashboard-table-td status">
					<?php echo esc_html__( 'Status', 'ohmylms' ); ?>
				</div>
			</div>
		</div>

		<div class="dashboard-table-body">
			<?php
			if ( count( $student_orders->orders ) > 0 ) {
				foreach ( $student_orders->orders as $student_order ) {
					$order        = ohmylms_ecommerce_get_order( $student_order );
					$item_count   = $order->get_item_count() - $order->get_item_count_refunded();
					$items        = $order->get_items();
					$purchased_by = get_post_meta( $order->get_id(), '_purchased_by', true );
					$item         = $items[0] ?? null;
					if ( $item ) {
						$course_id = $item->get_course_id();
						$course    = ohmylms_get_course( $course_id );
						if ( ! $course ) {
							$course = ohmylms_is_pro() ? ohmylms_get_membership( $course_id ) : '';

							if ( ! $course ) {
								continue;
							}
							// $subscription_length = $membership->get_subscription_length();
							// $products = $membership->get_products();
						}
					}
					?>
						<div class="dashboard-table-tr">
							<div class="table-accordion-handler"></div>

							<div class="dashboard-table-td order-id">
								#<?php echo esc_html( $order->get_id() ); ?>
							</div>

							<div class="dashboard-table-td course-name">
							<?php
							if ( $course ) {
								echo esc_html( $course->get_name() );
							}
							?>
							</div>

							<div class="dashboard-table-td purchase-method">
								<?php
								echo esc_html( $purchased_by ? ucfirst( $purchased_by ) : __( 'Currency', 'ohmylms' ) );
								?>
							</div>

							<div class="dashboard-table-td price">
								<?php
								if ( $course ) {
									echo wp_kses_post( ohmylms_price( $order->get_total() ) );
								}
								?>
							</div>

							<div class="dashboard-table-td date">
								<time datetime="<?php echo esc_attr( $order->get_date_created()->date( 'c' ) ); ?>"><?php echo esc_html( ohmylms_format_datetime( $order->get_date_created() ) ); ?></time>
							</div>

							<div class="dashboard-table-td status">
								<span class="status-tag <?php echo esc_attr( $order->get_status() ); ?>">
								<?php echo esc_html( ohmylms_ecommerce_get_order_status_name( $order->get_status() ) ); ?>
								</span>
							</div>

							<div class="dashboard-table-mobile-td">
								<div class="dashboard-table-td course-name" data-title="Course Name: ">
								<?php
								if ( $course ) {
									echo esc_html( $course->get_name() );
								}
								?>
								</div>

								<div class="dashboard-table-td price" data-title="Purchase Method: ">
									<?php
									echo esc_html( $purchased_by ? ucfirst( $purchased_by ) : __( 'Currency', 'ohmylms' ) );
									?>
								</div>

								<div class="dashboard-table-td price" data-title="Price: ">
									<?php
									if ( $course ) {
										echo wp_kses_post( ohmylms_price( $order->get_formatted_order_total() ) );
									}
									?>
								</div>

								<div class="dashboard-table-td date" data-title="Date: ">
									<time datetime="<?php echo esc_attr( $order->get_date_created()->date( 'c' ) ); ?>"><?php echo esc_html( ohmylms_format_datetime( $order->get_date_created() ) ); ?></time>
								</div>
							</div>

						</div>
					<?php
				}
			} else {
				?>
					<div class="no-course-data">
					<?php include OHMYLMS_DIR . '/assets/images/icon/no-course-found-image.php'; ?>
						<p>
						<?php echo esc_html__( 'No Order Found.', 'ohmylms' ); ?>
						</p>
					</div>
					<?php
			}
			?>
		</div>

		<!-- <?php if ( $student_orders->total > 0 ) { ?>
			<div class="dashboard-table-foot">
				<div class="ohmylms-table-pagination">
					<strong>
						<?php echo esc_html( $student_orders->total ); ?>
					</strong> items

					<a class="previous-page" aria-label="Previous page" title="Previous Page" href="<?php echo esc_url( ohmylms_get_endpoint_url( 'transactions-history', $current_page - 1 ) ); ?>">
						<?php include OHMYLMS_DIR . '/assets/images/icon/arrow-left-icon.php'; ?>
					</a>

					<input type="number" name="current-page-number" id="current-page-number" min="1" max="<?php echo esc_attr( $student_orders->max_num_pages ); ?>" value="1" class="current-page-number">

					<a class="next-page" aria-label="Next page" title="Next Page" href="<?php echo esc_url( ohmylms_get_endpoint_url( 'transactions-history', $current_page + 1 ) ); ?>">
						<?php include OHMYLMS_DIR . '/assets/images/icon/arrow-right-icon.php'; ?>
					</a>

					of <strong>
						<?php echo esc_html( $student_orders->max_num_pages ); ?>
					</strong>
				</div>
			</div>
		<?php } ?> -->
	</div>
</div>
