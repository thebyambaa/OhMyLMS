<?php
/**
 * Template for displaying membership of student profile
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/profile/membership-details.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

defined( 'ABSPATH' ) || exit();

?>

<div class="ohmylms-student-profile-tab-content student-membership">
	<div class="ohmylms-membership-invoice">
		<nav class="invoice-breadcrumb">
			<ul>
				<li>
					<a href="#">
						<svg width="16" height="16" fill="none" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg"><path stroke="#A1A1AA" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10" d="M13.507 4.678a2.969 2.969 0 011.157 2.365v4.99A2.966 2.966 0 0111.698 15H3.63a2.966 2.966 0 01-2.966-2.966V7.043c0-.925.432-1.798 1.167-2.359l4.034-3.076a2.966 2.966 0 013.598 0l2.235 1.703V1.356"/><path fill="#A1A1AA" d="M6.478 7.26a.742.742 0 100 1.482.742.742 0 000-1.483zm2.372 0a.742.742 0 100 1.482.742.742 0 000-1.483zM6.478 9.63a.742.742 0 100 1.484.742.742 0 000-1.484zm2.372 0a.742.742 0 100 1.484.742.742 0 000-1.484z"/></svg>
						Invoices
					</a>
				</li>

				<li>Invoice #13062</li>
			</ul>
		</nav>

		<div class="invoice-header">
			<div class="invoice-left">
				<h2 class="invoice-title">
					Invoice #13062 on December 28, 2024
				</h2>
				<ul class="invoice-tags">
					<li class="invoice-student-name">John Doe ( student@gmail.com)</li>
					<li class="invoice-plan-name">Silver Plan</li>
					<li class="invoice-payment-status">Paid</li>
				</ul>
			</div>

			<div class="invoice-right">
				<span role="button" class="print-button">
					<svg width="18" height="17" fill="none" viewBox="0 0 18 17" xmlns="http://www.w3.org/2000/svg"><path stroke="#A1A1AA" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10" stroke-width="1.2" d="M13.75 12.469h.906c1.036 0 1.875-.84 1.875-1.875v-3.75c0-1.036-.84-1.875-1.875-1.875H3.344c-1.036 0-1.875.84-1.875 1.875v3.75c0 1.035.84 1.875 1.875 1.875h.906m10.25-2.5h-11"/><path stroke="#A1A1AA" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10" stroke-width="1.2" d="M5.188 16h7.625c.517 0 .937-.42.937-.938V9.97h-9.5v5.094c0 .517.42.937.938.937zm5.062-4.031h-2.5m2.5 2h-2.5m-2.75-7H3.5M6.125.938h5.75c1.036 0 1.875.839 1.875 1.875v2.156h-9.5V2.812c0-1.035.84-1.874 1.875-1.874z"/></svg>
				
					<?php echo esc_html__( 'Print', 'ohmylms' ); ?>
				</span>
			</div>
		</div>

		<div class="ohmylms-dashboard-table membership-invoice-table">
			<div class="dashboard-table-head">
				<div class="dashboard-table-tr">
					<div class="dashboard-table-td membership-item">
						<?php echo esc_html__( 'Item', 'ohmylms' ); ?>
					</div>

					<div class="dashboard-table-td membership-price">
						<?php echo esc_html__( 'Price', 'ohmylms' ); ?>
					</div>

					<div class="dashboard-table-td quantity">
						<?php echo esc_html__( 'Quantity', 'ohmylms' ); ?>
					</div>

					<div class="dashboard-table-td total">
						<?php echo esc_html__( 'Total', 'ohmylms' ); ?>
					</div>
				</div>
			</div>

			<div class="dashboard-table-body">
				<div class="dashboard-table-tr">
					<div class="table-accordion-handler"></div>

					<div class="dashboard-table-td bold-td membership-item">
						The Complete Graphic Design Theory
					</div>

					<div class="dashboard-table-td membership-price">
						$147.00
					</div>

					<div class="dashboard-table-td quantity">
						<svg width="10" height="10" fill="none" viewBox="0 0 10 10" xmlns="http://www.w3.org/2000/svg"><path stroke="#A1A1AA" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 1L1 9m0-8l8 8"/></svg>
						1
					</div>

					<div class="dashboard-table-td total">
						$102.90
					</div>

					<div class="dashboard-table-mobile-td">
						<div class="dashboard-table-td membership-price" data-title="Price: ">
							$345
						</div>

						<div class="dashboard-table-td quantity" data-title="Quantity: ">
							<svg width="10" height="10" fill="none" viewBox="0 0 10 10" xmlns="http://www.w3.org/2000/svg"><path stroke="#A1A1AA" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 1L1 9m0-8l8 8"/></svg>
							1
						</div>
					</div>

				</div>
				
				<div class="dashboard-table-tr">
					<div class="table-accordion-handler"></div>

					<div class="dashboard-table-td bold-td membership-item">
						The Complete Graphic Design Theory
					</div>

					<div class="dashboard-table-td bold-td membership-price">
						$147.00
					</div>

					<div class="dashboard-table-td quantity">
						<svg width="10" height="10" fill="none" viewBox="0 0 10 10" xmlns="http://www.w3.org/2000/svg"><path stroke="#A1A1AA" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 1L1 9m0-8l8 8"/></svg>
						1
					</div>

					<div class="dashboard-table-td total">
						$102.90
					</div>

					<div class="dashboard-table-mobile-td">
						<div class="dashboard-table-td membership-price" data-title="Price: ">
							$345
						</div>

						<div class="dashboard-table-td quantity" data-title="Quantity: ">
							<svg width="10" height="10" fill="none" viewBox="0 0 10 10" xmlns="http://www.w3.org/2000/svg"><path stroke="#A1A1AA" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 1L1 9m0-8l8 8"/></svg>
							1
						</div>
					</div>

				</div>

				<div class="dashboard-table-tr dashboard-table-foot-tr">
					<div class="dashboard-table-td item-subtotal">
						<span class="td-label">
							<?php echo esc_html__( 'Item subtotal:', 'ohmylms' ); ?>
						</span>

						<span class="td-value">
							$4555
						</span>
					</div>

					<div class="dashboard-table-td item-coupon">
						<span class="td-label">
							<?php echo esc_html__( 'Coupons:', 'ohmylms' ); ?>
						</span>

						<span class="td-value">
							-$455
						</span>
					</div>
				</div>

				<div class="dashboard-table-tr dashboard-table-foot-tr">
					<div class="dashboard-table-td intotal">
						<span class="td-label">
							<?php echo esc_html__( 'Paid:', 'ohmylms' ); ?>
						</span>

						<span class="td-value">
							$102.90
						</span>
					</div>
				</div>

			</div>
		</div>
	</div>
</div>
