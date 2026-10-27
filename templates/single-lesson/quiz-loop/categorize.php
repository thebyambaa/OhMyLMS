<?php
/**
 * Sort items into groups. Every item has a menu (works without JavaScript);
 * interactive-controls.js upgrades it to tap-to-place tiles. Group assignments are never output.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/single-lesson/quiz-loop/categorize.php.
 *
 * @package OhMyLMS\Templates
 * @version 1.0.0
 */

use OhMyLMS\Assessment\Interactive;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$settings = Interactive::learner_settings( 'categorize', $question );
$buckets  = (array) ( $settings['buckets'] ?? array() );
Interactive::enqueue();
?>
<div class="ohmylms-interactive ohmylms-categorize" data-ohmylms-interactive="categorize">
	<ul class="ohmylms-cat-items">
		<?php foreach ( (array) ( $settings['items'] ?? array() ) as $item ) { ?>
			<li class="ohmylms-cat-item" data-item="<?php echo esc_attr( $item['id'] ); ?>">
				<?php if ( ! empty( $item['image_url'] ) ) { ?>
					<img src="<?php echo esc_url( $item['image_url'] ); ?>" alt="<?php echo esc_attr( $item['text'] ); ?>">
				<?php } ?>
				<label>
					<span class="ohmylms-cat-label"><?php echo esc_html( $item['text'] ); ?></span>
					<select class="ohmylms-cat-select" data-answer-key="<?php echo esc_attr( $item['id'] ); ?>" data-question-id="<?php echo esc_attr( $question['id'] ); ?>" name="<?php echo esc_attr( Interactive::field_name( $attempt, $question, $item['id'] ) ); ?>">
						<option value=""><?php esc_html_e( 'Choose a group…', 'ohmylms' ); ?></option>
						<?php foreach ( $buckets as $bucket ) { ?>
							<option value="<?php echo esc_attr( $bucket['id'] ); ?>"><?php echo esc_html( $bucket['label'] ); ?></option>
						<?php } ?>
					</select>
				</label>
			</li>
		<?php } ?>
	</ul>
	<script type="application/json" class="ohmylms-cat-buckets"><?php echo wp_json_encode( $buckets ); ?></script>
</div>
