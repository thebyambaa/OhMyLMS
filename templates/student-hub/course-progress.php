<?php
/**
 * Separate learner completion and skill-proficiency meters.
 *
 * @package OhMyLMS
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="oml-journey-progress">
	<div>
		<h2><?php esc_html_e( 'Activity completion', 'ohmylms' ); ?></h2>
		<p><?php /* translators: 1: complete activities, 2: total activities. */ echo esc_html( sprintf( __( '%1$d of %2$d activities complete', 'ohmylms' ), $hub_summary['activity_done'], $hub_summary['activity_total'] ) ); ?></p>
		<progress max="100" value="<?php echo esc_attr( $hub_summary['activity_percent'] ); ?>" aria-label="<?php esc_attr_e( 'Activity completion', 'ohmylms' ); ?>"></progress>
		<p><?php esc_html_e( 'Includes optional activities. Course completion follows the required activities and skill targets.', 'ohmylms' ); ?></p>
	</div>
	<?php if ( $hub_summary['skills'] ) : ?>
		<div>
			<h2><?php esc_html_e( 'Skill proficiency', 'ohmylms' ); ?></h2>
			<p><?php /* translators: 1: proficient skills, 2: total skills. */ echo esc_html( sprintf( __( '%1$d of %2$d skills at Proficient or above', 'ohmylms' ), $hub_summary['proficient'], $hub_summary['skills'] ) ); ?></p>
			<progress max="100" value="<?php echo esc_attr( $hub_summary['mastery_percent'] ); ?>" aria-label="<?php esc_attr_e( 'Skill proficiency', 'ohmylms' ); ?>"></progress>
			<p><?php esc_html_e( 'Skill progress comes from evaluated practice and assessments.', 'ohmylms' ); ?></p>
		</div>
	<?php endif; ?>
</div>
