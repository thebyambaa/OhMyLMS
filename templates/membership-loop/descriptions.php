<?php
/**
 * Membership Loop Description
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/membership-loop/descriptions.php.
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

$description = $membership->get_description();
if ( empty( $description ) ) {
	return;
}

// Character limit for truncation.
$char_limit         = 200;
$description_length = mb_strlen( wp_strip_all_tags( $description ) );
$needs_truncation   = $description_length > $char_limit;
$unique_id          = 'membership-desc-' . $membership->get_id();

if ( $needs_truncation ) {
	$short_description = mb_substr( wp_strip_all_tags( $description ), 0, $char_limit );
	?>
	<div class="membership-description-wrapper" data-desc-id="<?php echo esc_attr( $unique_id ); ?>">
		<p class="membership-description membership-description-short">
			<?php echo esc_html( $short_description ); ?>...
			<button type="button" class="membership-see-more-btn" data-target="<?php echo esc_attr( $unique_id ); ?>">
				<?php echo esc_html__( 'See More', 'ohmylms' ); ?>
			</button>
		</p>
		<p class="membership-description membership-description-full" style="display: none;">
			<?php echo wp_kses_post( $description ); ?>
			<button type="button" class="membership-see-less-btn" data-target="<?php echo esc_attr( $unique_id ); ?>">
				<?php echo esc_html__( 'See Less', 'ohmylms' ); ?>
			</button>
		</p>
	</div>
	<?php
} else {
	?>
	<p class="membership-description">
		<?php echo wp_kses_post( $description ); ?>
	</p>
	<?php
}
?>

<script>
(function() {
	document.addEventListener('DOMContentLoaded', function() {
		// Handle See More buttons
		var seeMoreBtns = document.querySelectorAll('.membership-see-more-btn');
		seeMoreBtns.forEach(function(btn) {
			btn.addEventListener('click', function(e) {
				e.preventDefault();
				var targetId = this.getAttribute('data-target');
				var wrapper = document.querySelector('[data-desc-id="' + targetId + '"]');
				if (wrapper) {
					wrapper.querySelector('.membership-description-short').style.display = 'none';
					wrapper.querySelector('.membership-description-full').style.display = 'block';
				}
			});
		});

		// Handle See Less buttons
		var seeLessBtns = document.querySelectorAll('.membership-see-less-btn');
		seeLessBtns.forEach(function(btn) {
			btn.addEventListener('click', function(e) {
				e.preventDefault();
				var targetId = this.getAttribute('data-target');
				var wrapper = document.querySelector('[data-desc-id="' + targetId + '"]');
				if (wrapper) {
					wrapper.querySelector('.membership-description-full').style.display = 'none';
					wrapper.querySelector('.membership-description-short').style.display = 'block';
				}
			});
		});
	});
})();
</script>

<style>
.membership-description-wrapper {
	position: relative;
}

.membership-description {
	text-align: justify;
}
.membership-see-more-btn,
.membership-see-more-btn:hover,
.membership-see-less-btn:hover,
.membership-see-less-btn {
	background: none;
	border: none;
	color: #6e42d3;
	cursor: pointer;
	font-size: inherit;
	font-weight: 600;
	padding: 0;
	margin-left: 5px;
	text-decoration: none;
	display: inline;
	transition: color 0.2s ease;
}
</style>

