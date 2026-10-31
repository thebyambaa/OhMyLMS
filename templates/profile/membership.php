<?php
/**
 * Template for displaying membership of student profile
 *
 * "Cancel" asks for confirmation and posts the subscription to `ohmylms_cancel_membership`
 * through the `ohmylms/account` store.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/profile/membership.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.1.0
 */

defined( 'ABSPATH' ) || exit();
use function OhMyLMS\Commerce\ecommerce;

?>

<div class="ohmylms-student-profile-tab-content student-membership">
	<h4 class="profile-tab-title">
		<?php echo esc_html__( 'My Membership', 'ohmylms' ); ?>
	</h4>

	<div class="ohmylms-dashboard-table my-membership-table">
		<div class="dashboard-table-head">
			<div class="dashboard-table-tr">
				<div class="dashboard-table-td membership-id">
					<?php echo esc_html__( 'ID', 'ohmylms' ); ?>
				</div>

				<div class="dashboard-table-td membership-plan">
					<?php echo esc_html__( 'Membership Plan', 'ohmylms' ); ?>
				</div>

				<div class="dashboard-table-td expiration">
					<?php echo esc_html__( 'Next Payment', 'ohmylms' ); ?>
				</div>

				<div class="dashboard-table-td billing">
					<?php echo esc_html__( 'Billing', 'ohmylms' ); ?>
				</div>

				<div class="dashboard-table-td action">
					<?php echo esc_html__( 'Action', 'ohmylms' ); ?>
				</div>
			</div>
		</div>

		<div class="dashboard-table-body">
			<?php
				global $wpdb;
			if ( count( $student_orders->orders ) > 0 ) {
				$membership_count       = 0;
				$enrolled_membership    = 0;
				$showing_membership_ids = array();
				foreach ( $student_orders->orders as $student_order ) {
					$order = ohmylms_ecommerce_get_order( $student_order );

					$item_count = $order->get_item_count() - $order->get_item_count_refunded();
					$items      = $order->get_items();

					// First, handle recurring subscriptions.
					$post_ids = $wpdb->get_col(
						$wpdb->prepare(
							"SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s",
							'_original_order_id',
							$order->get_id()
						)
					);
					$post_ids = ! empty( $post_ids ) ? (array) $post_ids : array();
					if ( ! empty( $post_ids ) ) {
						foreach ( $post_ids as $subscription_id ) {
							$subscription = ohmylms_ecommerce_get_subscription( $subscription_id );
							if ( ! $subscription ) {
								continue;
							}
							$membership_id = $subscription->get_membership_id();
							$membership    = function_exists( 'ohmylms_get_membership' ) ? ohmylms_get_membership( $membership_id ) : null;

							if ( $membership ) {
								$subscription_length = $membership->get_billing_period();
								$is_one_time         = 'one_time' === $subscription_length;
								$products            = $membership->get_products();
								++$membership_count;
							}
							if ( $membership && $membership_count ) {
								++$enrolled_membership;
								if ( in_array( $membership->get_id(), $showing_membership_ids ) ) {
									continue;
								}
								$showing_membership_ids[] = $membership->get_id();

								$price          = $membership->get_regular_price();
								$billing_period = $membership->get_subscription_period();
								if ( 'one_time' !== $billing_period ) {
									$billing_interval = $membership->get_subscription_period_interval();

									$stored_totals = ecommerce()->session->get( 'cart_totals' );
									if ( ! empty( $stored_totals ) && isset( $stored_totals['tax_rate'] ) && $stored_totals['tax_rate'] > 0 ) {
										$tax_rate = $stored_totals['tax_rate'];
										// Now calculate tax based on the discounted total.
										$tax_data = \OhMyLMS_Tax_Calculator::get_instance()->calculate_tax(
											$tax_rate,
											array(
												'total' => $price,
											)
										);
										$price    = isset( $tax_data['total_with_tax'] ) ? $tax_data['total_with_tax'] : $price;
									}
									$price_html = ohmylms_price( $price ) . ' / ' . $billing_period;
									$now        = current_time( 'timestamp' );

									switch ( $billing_period ) {
										case 'month':
											$renewal = strtotime( "+$billing_interval month", $now );
											break;
										case 'week':
											$renewal = strtotime( "+$billing_interval week", $now );
											break;
										case 'day':
											$renewal = strtotime( "+$billing_interval day", $now );
											break;
										case 'year':
										default:
											$renewal = strtotime( "+$billing_interval year", $now );
											break;
									}
									$renewal_date = date_i18n( get_option( 'date_format' ), $renewal );
								} else {
									$renewal_date = '';
								}

								?>
								<div class="dashboard-table-tr">
									<div class="table-accordion-handler"></div>

									<div class="dashboard-table-td membership-id">
										#<?php echo esc_html( $subscription->get_id() ); ?>
									</div>

									<div class="dashboard-table-td bold-td membership-plan">
									<?php echo esc_html( $membership->get_name() ); ?>
									</div>

								<?php if ( $is_one_time ) { ?>
										<div class="dashboard-table-td expiration">
											<?php echo esc_html__( 'N/A', 'ohmylms' ); ?>
										</div>
									<?php } elseif ( $renewal_date ) { ?>
										<div class="dashboard-table-td expiration">
											<?php echo esc_html( $renewal_date ); ?>
										</div>
									<?php } ?>

									<div class="dashboard-table-td billing">
									<?php
									if ( $is_one_time ) {
										// For one-time payments, show the total amount with tax.
										$items = $order->get_items();
										foreach ( $items as $item ) {
											if ( $item->get_course_id() == $membership_id ) {
												$item_total     = $item->get_total();
												$tax_amount     = \OhMyLMS_Tax_Calculator::get_instance()->calculate_tax( $order->get_tax_rate(), array( 'total' => $item_total ) );
												$total_with_tax = is_array( $tax_amount ) && isset( $tax_amount['total_with_tax'] ) ? $tax_amount['total_with_tax'] : $item_total;
												echo wp_kses_post( ohmylms_price( $total_with_tax ) ) . '/' . esc_html__( 'One Time', 'ohmylms' );
												break;
											}
										}
									} else {
										// For recurring payments, show recurring amount with period.
										$tax_amount       = \OhMyLMS_Tax_Calculator::get_instance()->calculate_tax( $order->get_tax_rate(), array( 'total' => $subscription->get_recurring_amount() ) );
										$recurring_amount = is_array( $tax_amount ) && isset( $tax_amount['total_with_tax'] ) ? $tax_amount['total_with_tax'] : $subscription->get_recurring_amount();
										echo wp_kses_post( ohmylms_price( $recurring_amount ) ) . '/' . esc_html( $subscription->get_billing_period() );
									}
									?>
									</div>

									<div class="dashboard-table-td action">
									<?php
									$cancellable_statuses = array( 'active', 'trialing' );
									// Disable cancel button for one-time payments.
									if ( ! $is_one_time && in_array( $subscription->get_status(), $cancellable_statuses ) ) :
										?>
											<a href="#" class="do-membership-cancel ohmylms-table-action-btn" role="button" title="<?php esc_attr_e( 'Cancel', 'ohmylms' ); ?>"
												data-wp-interactive="ohmylms/account"
												<?php
												echo wp_interactivity_data_wp_context( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core helper escapes the attribute.
													array(
														'subscriptionId' => (int) $subscription->get_id(),
														'orderId'        => (int) $order->get_id(),
														'busy'           => false,
													)
												);
												?>
												data-wp-on--click="actions.cancelMembership"
												data-wp-bind--aria-disabled="context.busy">
											<?php echo esc_html__( 'Cancel', 'ohmylms' ); ?>
											</a>
										<?php elseif ( $is_one_time ) : ?>
											<button type="button" class="ohmylms-table-action-btn" disabled style="opacity: 0.5; cursor: not-allowed;">
												<?php echo esc_html__( 'Cancel', 'ohmylms' ); ?>
											</button>
										<?php else : ?>
											<span class="ohmylms-status-label" style="text-transform: capitalize">
												<?php
												$status_name = esc_html( $subscription->get_status() );
												printf( /* translators: %s: text value. */
												'%s', $status_name );
												?>
											</span>
										<?php endif; ?>
									</div>

									<div class="dashboard-table-mobile-td">
										<div class="dashboard-table-td membership-id" data-title="ID: ">
											#<?php echo esc_html( $order->get_id() ); ?>
										</div>

										<div class="dashboard-table-td expiration" data-title="Expiration: ">
											<?php
											if ( $membership ) {
												echo wp_kses_post( ohmylms_price( $order->get_formatted_order_total() ) );
											}
											?>
										</div>

										<div class="dashboard-table-td billing" data-title="Billing: ">
											<time datetime="<?php echo esc_attr( $order->get_date_created()->date( 'c' ) ); ?>"><?php echo esc_html( ohmylms_format_datetime( $order->get_date_created() ) ); ?></time>
										</div>
									</div>

								</div>
								<?php
							}
						}
					}

					// Handle one-time payment memberships (no subscription created).
					if ( empty( $post_ids ) && ! empty( $items ) ) {
						foreach ( $items as $item ) {
							$membership_id = $item->get_course_id();
							$membership    = function_exists( 'ohmylms_get_membership' ) ? ohmylms_get_membership( $membership_id ) : null;

							if ( ! $membership ) {
								continue;
							}

							$subscription_length = $membership->get_billing_period();
							$is_one_time         = 'one_time' === $subscription_length;

							// Only process if it's a one-time payment and order is completed.
							if ( ! $is_one_time || ! in_array( $order->get_status(), array( 'completed', 'processing' ) ) ) {
								continue;
							}

							if ( in_array( $membership->get_id(), $showing_membership_ids ) ) {
								continue;
							}

							$showing_membership_ids[] = $membership->get_id();
							$products                 = $membership->get_products();
							++$membership_count;
							++$enrolled_membership;

							?>
								<div class="dashboard-table-tr">
									<div class="table-accordion-handler"></div>

									<div class="dashboard-table-td membership-id">
										#<?php echo esc_html( $order->get_id() ); ?>
									</div>

									<div class="dashboard-table-td bold-td membership-plan">
									<?php echo esc_html( $membership->get_name() ); ?>
									</div>

									<div class="dashboard-table-td expiration">
									<?php echo esc_html__( 'N/A', 'ohmylms' ); ?>
									</div>

									<div class="dashboard-table-td billing">
									<?php
									// For one-time payments, show the total amount with tax.
									$item_total     = $item->get_total();
									$tax_amount     = \OhMyLMS_Tax_Calculator::get_instance()->calculate_tax( $order->get_tax_rate(), array( 'total' => $item_total ) );
									$total_with_tax = is_array( $tax_amount ) && isset( $tax_amount['total_with_tax'] ) ? $tax_amount['total_with_tax'] : $item_total;
									echo wp_kses_post( ohmylms_price( $total_with_tax ) ) . '/' . esc_html__( 'One Time', 'ohmylms' );
									?>
									</div>

									<div class="dashboard-table-td action">
										<button type="button" class="ohmylms-table-action-btn" disabled style="opacity: 0.5; cursor: not-allowed;">
										<?php echo esc_html__( 'Cancel', 'ohmylms' ); ?>
										</button>
									</div>

									<div class="dashboard-table-mobile-td">
										<div class="dashboard-table-td membership-id" data-title="ID: ">
											#<?php echo esc_html( $order->get_id() ); ?>
										</div>

										<div class="dashboard-table-td expiration" data-title="Expiration: ">
										<?php
										if ( $membership ) {
											echo wp_kses_post( ohmylms_price( $order->get_formatted_order_total() ) );
										}
										?>
										</div>

										<div class="dashboard-table-td billing" data-title="Billing: ">
											<time datetime="<?php echo esc_attr( $order->get_date_created()->date( 'c' ) ); ?>"><?php echo esc_html( ohmylms_format_datetime( $order->get_date_created() ) ); ?></time>
										</div>
									</div>

								</div>
								<?php
						}
					}
				}

				if ( ! $membership_count || ! $enrolled_membership ) {
					?>
						<div class="no-course-data">
						<?php include OHMYLMS_DIR . '/assets/images/icon/no-course-found-image.php'; ?>
							<p>
							<?php echo esc_html__( 'No Membership Found.', 'ohmylms' ); ?>
							</p>
						</div>
						<?php
				}
			} else {
				?>
					<div class="no-course-data">
					<?php include OHMYLMS_DIR . '/assets/images/icon/no-course-found-image.php'; ?>
						<p>
						<?php echo esc_html__( 'No Membership Found.', 'ohmylms' ); ?>
						</p>
					</div>
					<?php
			}
			?>
		</div>

	</div>

	<h5 class="past-invoice-title">
		<?php echo esc_html__( 'Past Invoices', 'ohmylms' ); ?>
	</h5>

	<div class="ohmylms-dashboard-table past-invoice-table">
		<div class="dashboard-table-head">
			<div class="dashboard-table-tr">
				<div class="dashboard-table-td date">
					<?php echo esc_html__( 'Date', 'ohmylms' ); ?>
				</div>

				<div class="dashboard-table-td method">
					<?php echo esc_html__( 'Payment Method', 'ohmylms' ); ?>
				</div>

				<div class="dashboard-table-td price">
					<?php echo esc_html__( 'Price', 'ohmylms' ); ?>
				</div>

				<div class="dashboard-table-td status">
					<?php echo esc_html__( 'Status', 'ohmylms' ); ?>
				</div>

				<div class="dashboard-table-td action">
					<?php echo esc_html__( 'Action', 'ohmylms' ); ?>
				</div>
			</div>
		</div>

		<div class="dashboard-table-body">
			<?php
			if ( count( $student_orders->orders ) > 0 ) {
				$membership_count = 0;

				foreach ( $student_orders->orders as $student_order ) {
					$order      = ohmylms_ecommerce_get_order( $student_order );
					$item_count = $order->get_item_count() - $order->get_item_count_refunded();
					$items      = $order->get_items();
					foreach ( $items as $item ) {

						$products = array();

						if ( $item ) {
							$membership_id = $item->get_course_id();
							$membership    = function_exists( 'ohmylms_get_membership' ) ? ohmylms_get_membership( $membership_id ) : null;

							if ( $membership ) {
								$subscription_length = $membership->get_subscription_length();
								$products            = $membership->get_products();

								++$membership_count;

							}
						}
						?>
						<?php
						if ( $membership_count && $membership ) :
							?>
									<div class="dashboard-table-tr">
										<div class="table-accordion-handler"></div>

										<div class="dashboard-table-td date">
									<?php
										$date = $order->get_date_completed() && null !== $order->get_date_completed() ? new \DateTime( $order->get_date_completed() ) : '';
										echo esc_html( $date ? $date->format( 'F d, Y' ) : '' );
									?>
										</div>

										<div class="dashboard-table-td bold-td method">
									<?php echo esc_html( $order->get_payment_method_title() ); ?>
										</div>

										<div class="dashboard-table-td price">
										<?php
										$negative        = $order->get_total() < 0;
										$formatted_price = ( $negative ? '-' : '' ) . sprintf( ohmylms_get_price_format(), '<span class="ohmylms-price-currency-symbol">' . get_ohmylms_currency_symbol( get_ohmylms_currency() ) . '</span>', $order->get_total() );

										echo wp_kses_post( $formatted_price );
										?>
										</div>

										<div class="dashboard-table-td status">
											<span class="status-tag <?php echo esc_attr( $order->get_status() ); ?>">
											<?php echo esc_html( ohmylms_ecommerce_get_order_status_name( $order->get_status() ) ); ?>
											</span>
										</div>

										<div class="dashboard-table-td action">
											<a href="<?php echo esc_url( ohmylms_get_account_endpoint_url( 'invoice-details', array( 'id' => $order->get_id() ) ) ); ?>">
											<?php include OHMYLMS_DIR . '/assets/images/icon/eye-icon.php'; ?>
											</a>
										</div>

										<div class="dashboard-table-mobile-td">
											<div class="dashboard-table-td date" data-title="Date: ">
											<?php
											if ( $membership ) {
												echo wp_kses_post( ohmylms_price( $order->get_formatted_order_total() ) );
											}
											?>
											</div>

											<div class="dashboard-table-td price" data-title="Price: ">
												<time datetime="<?php echo esc_attr( $order->get_date_created()->date( 'c' ) ); ?>"><?php echo esc_html( ohmylms_format_datetime( $order->get_date_created() ) ); ?></time>
											</div>

											<div class="dashboard-table-td status" data-title="Status: ">
												<span class="status-tag <?php echo esc_attr( $order->get_status() ); ?>">
												<?php echo esc_html( ohmylms_ecommerce_get_order_status_name( $order->get_status() ) ); ?>
												</span>
											</div>
										</div>

									</div>
							<?php
							break;
							endif;
						?>
						<?php } ?>
					<?php
				}

				if ( ! $membership_count ) {
					?>
						<div class="no-course-data">
						<?php include OHMYLMS_DIR . '/assets/images/icon/no-course-found-image.php'; ?>
							<p>
							<?php echo esc_html__( 'No Invoice Found.', 'ohmylms' ); ?>
							</p>
						</div>
						<?php
				}
			} else {
				?>
					<div class="no-course-data">
					<?php include OHMYLMS_DIR . '/assets/images/icon/no-course-found-image.php'; ?>
						<p>
						<?php echo esc_html__( 'No Invoice Found.', 'ohmylms' ); ?>
						</p>
					</div>
					<?php
			}
			?>
		</div>

	</div>

</div>
