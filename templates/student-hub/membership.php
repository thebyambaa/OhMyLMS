<?php
/**
 * Membership, plans, purchases and owned invoices in the learner shell.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Student\Hub;
use OhMyLMS\Student\MembershipView;

defined( 'ABSPATH' ) || exit;

if ( ! Hub::settings()['commerce'] || ! is_user_logged_in() ) {
	return;
}
$member_page  = min( 10000, max( 1, absint( get_query_var( 'current_page', 1 ) ) ) );
$member_query = null;
?>
<section class="oml-hub-banner">
	<h1><?php esc_html_e( 'Membership', 'ohmylms' ); ?></h1>
	<p><?php esc_html_e( 'Your plans and purchases, all in one place.', 'ohmylms' ); ?></p>
</section>
<?php if ( 'invoice' === $view ) : ?>
	<?php $member_order = MembershipView::invoice( absint( get_query_var( 'id', 0 ) ) ); ?>
	<section class="oml-hub-card oml-hub-settings-card">
		<?php if ( ! $member_order ) : ?>
			<p><?php esc_html_e( 'Choose an invoice from your purchases.', 'ohmylms' ); ?></p>
		<?php else : ?>
			<h2>
			<?php
			/* translators: %s: order number. */
			echo esc_html( sprintf( __( 'Invoice #%s', 'ohmylms' ), $member_order->get_order_number() ) );
			?>
			</h2>
			<p><?php echo esc_html( MembershipView::date( $member_order->get_date_created() ) ); ?> · <?php echo esc_html( ohmylms_ecommerce_get_order_status_name( $member_order->get_status() ) ); ?></p>
			<ul class="oml-hub-commerce-list">
				<?php foreach ( $member_order->get_items() as $member_item ) : ?>
					<li><span><?php echo esc_html( $member_item->get_name() ); ?> × <?php echo esc_html( $member_item->get_quantity() ); ?></span><strong><?php echo wp_kses_post( ohmylms_price( $member_item->get_total(), array( 'currency' => $member_order->get_currency() ) ) ); ?></strong></li>
				<?php endforeach; ?>
			</ul>
			<p><strong><?php esc_html_e( 'Total', 'ohmylms' ); ?>: <?php echo wp_kses_post( ohmylms_price( $member_order->get_total(), array( 'currency' => $member_order->get_currency() ) ) ); ?></strong></p>
		<?php endif; ?>
		<a class="oml-hub-pill" href="<?php echo esc_url( Hub::url( 'membership', 'orders' ) ); ?>"><?php esc_html_e( 'Back to purchases', 'ohmylms' ); ?></a>
	</section>
<?php elseif ( 'plans' === $view ) : ?>
	<?php
	$member_query = new WP_Query(
		array(
			'post_type'      => 'ohmylms-membership',
			'post_status'    => 'publish',
			'has_password'   => false,
			'posts_per_page' => 12,
			'paged'          => $member_page,
		)
	);
	?>
	<div class="oml-hub-commerce-grid">
		<?php foreach ( $member_query->posts as $member_post ) : ?>
			<?php $member_plan = ohmylms_get_membership( $member_post->ID ); ?>
			<?php
			if ( ! $member_plan ) {
				continue; }
			?>
			<section class="oml-hub-card oml-hub-settings-card">
				<h2><?php echo esc_html( $member_plan->get_name() ); ?></h2>
				<p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $member_plan->get_short_description() ), 35 ) ); ?></p>
				<p><?php echo wp_kses_post( $member_plan->get_price_html() ); ?></p>
				<?php if ( \OhMyLMS\Membership\FreeSale::active( $member_plan ) ) : ?>
					<?php \OhMyLMS\Membership\FreeSale::form( $member_plan ); ?>
				<?php else : ?>
					<a class="oml-hub-button" href="<?php echo esc_url( $member_plan->get_permalink() ); ?>"><?php esc_html_e( 'View plan', 'ohmylms' ); ?></a>
				<?php endif; ?>
			</section>
		<?php endforeach; ?>
	</div>
	<?php
	if ( ! $member_query->posts ) :
		?>
		<div class="oml-hub-empty"><?php esc_html_e( 'No plans are available right now.', 'ohmylms' ); ?></div><?php endif; ?>
<?php elseif ( 'orders' === $view ) : ?>
	<?php $member_query = MembershipView::owned( 'ohmylms-order', $member_page ); ?>
	<section class="oml-hub-card oml-hub-settings-card">
		<h2><?php esc_html_e( 'Your purchases', 'ohmylms' ); ?></h2>
		<ul class="oml-hub-commerce-list">
			<?php foreach ( $member_query->posts as $member_post ) : ?>
				<?php $member_order = MembershipView::invoice( $member_post->ID ); ?>
				<?php
				if ( ! $member_order ) {
					continue; }
				?>
				<li>
					<div><strong>#<?php echo esc_html( $member_order->get_order_number() ); ?></strong><p><?php echo esc_html( MembershipView::date( $member_order->get_date_created() ) ); ?> · <?php echo esc_html( ohmylms_ecommerce_get_order_status_name( $member_order->get_status() ) ); ?></p></div>
					<strong><?php echo wp_kses_post( ohmylms_price( $member_order->get_total(), array( 'currency' => $member_order->get_currency() ) ) ); ?></strong>
					<a class="oml-hub-pill" href="<?php echo esc_url( add_query_arg( 'id', $member_order->get_id(), Hub::url( 'membership', 'invoice' ) ) ); ?>"><?php esc_html_e( 'View invoice', 'ohmylms' ); ?></a>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
		if ( ! $member_query->posts ) :
			?>
			<p><?php esc_html_e( 'You have no purchases yet.', 'ohmylms' ); ?></p><?php endif; ?>
	</section>
<?php else : ?>
	<?php $member_query = MembershipView::owned( 'ohmylms-subscription', $member_page ); ?>
	<section class="oml-hub-card oml-hub-settings-card">
		<h2><?php esc_html_e( 'Your subscriptions', 'ohmylms' ); ?></h2>
		<ul class="oml-hub-commerce-list">
			<?php foreach ( $member_query->posts as $member_post ) : ?>
				<?php
				$member_subscription = ohmylms_ecommerce_get_subscription( $member_post->ID );
				if ( ! $member_subscription || get_current_user_id() !== (int) $member_subscription->get_student_id() ) {
					continue; }
				$member_plan = ohmylms_get_membership( $member_subscription->get_membership_id() );
				?>
				<li><div><strong><?php echo esc_html( $member_plan ? $member_plan->get_name() : __( 'Membership', 'ohmylms' ) ); ?></strong><p><?php echo esc_html( ohmylms_ecommerce_get_order_status_name( $member_subscription->get_status() ) ); ?></p></div><div><?php esc_html_e( 'Next payment', 'ohmylms' ); ?><p><?php echo esc_html( MembershipView::date( $member_subscription->get_schedule_next_payment_date() ) ); ?></p></div></li>
			<?php endforeach; ?>
		</ul>
		<?php
		if ( ! $member_query->posts ) :
			?>
			<p><?php esc_html_e( 'You have no subscriptions. One-time purchases are listed in Purchases.', 'ohmylms' ); ?></p><?php endif; ?>
		<a class="oml-hub-button" href="<?php echo esc_url( Hub::url( 'membership', 'plans' ) ); ?>"><?php esc_html_e( 'Explore plans', 'ohmylms' ); ?></a>
	</section>
<?php endif; ?>
<?php if ( $member_query && 1 < $member_query->max_num_pages ) : ?>
	<nav class="oml-hub-subnav" aria-label="<?php esc_attr_e( 'Membership pages', 'ohmylms' ); ?>">
		<?php
		if ( 1 < $member_page ) :
			?>
			<a class="oml-hub-pill" href="<?php echo esc_url( add_query_arg( 'current_page', $member_page - 1, Hub::url( 'membership', $view ) ) ); ?>"><?php esc_html_e( 'Previous', 'ohmylms' ); ?></a><?php endif; ?>
		<?php
		if ( $member_page < $member_query->max_num_pages ) :
			?>
			<a class="oml-hub-pill" href="<?php echo esc_url( add_query_arg( 'current_page', $member_page + 1, Hub::url( 'membership', $view ) ) ); ?>"><?php esc_html_e( 'Next', 'ohmylms' ); ?></a><?php endif; ?>
	</nav>
<?php endif; ?>
