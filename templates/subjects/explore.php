<?php
/**
 * Explore menu: a column per subject with its topics, optionally behind an Explore button.
 *
 * @package OhMyLMS
 */

defined( 'ABSPATH' ) || exit;

$explore_panel = static function () use ( $columns ) {
	?>
	<div class="oml-explore-panel">
		<?php foreach ( $columns as $explore_column ) : ?>
			<section class="oml-explore-column">
				<h2><a href="<?php echo esc_url( $explore_column['url'] ); ?>"><?php echo esc_html( $explore_column['name'] ); ?></a></h2>
				<ul>
					<?php foreach ( $explore_column['links'] as $explore_link ) : ?>
						<li><a href="<?php echo esc_url( $explore_link['url'] ); ?>"><?php echo esc_html( $explore_link['label'] ); ?></a></li>
					<?php endforeach; ?>
					<li class="oml-explore-all"><a href="<?php echo esc_url( $explore_column['url'] ); ?>"><?php /* translators: %s: subject name. */ echo esc_html( sprintf( __( 'See all %s', 'ohmylms' ), $explore_column['name'] ) ); ?></a></li>
				</ul>
			</section>
		<?php endforeach; ?>
	</div>
	<?php
};
?>
<nav class="oml-explore<?php echo $dropdown ? ' is-dropdown' : ''; ?><?php echo $full ? ' is-full' : ''; ?>" aria-label="<?php esc_attr_e( 'Explore subjects', 'ohmylms' ); ?>">
	<?php if ( ! $columns ) : ?>
		<p><?php esc_html_e( 'No subjects yet. Subjects appear when courses are published and assigned to them.', 'ohmylms' ); ?></p>
	<?php elseif ( $dropdown ) : ?>
		<details class="oml-explore-menu">
			<summary><?php echo esc_html( $label ); ?></summary>
			<?php $explore_panel(); ?>
		</details>
	<?php else : ?>
		<?php $explore_panel(); ?>
	<?php endif; ?>
</nav>
