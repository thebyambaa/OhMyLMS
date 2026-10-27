<?php
/**
 * Home-context activity for an explicitly linked child; proposed report composition.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Family\Reports;
use OhMyLMS\Student\ParentWorkspace;

defined( 'ABSPATH' ) || exit;

$home_child = absint( get_query_var( 'hub_child', 0 ) );
$home_base  = add_query_arg(
	array(
		'hub_scope' => 'home_learning',
		'hub_child' => $home_child,
	),
	ParentWorkspace::url()
);
try {
	$home_report = Reports::read( $home_child, absint( get_query_var( 'hub_period', 30 ) ), max( 1, absint( get_query_var( 'current_page', 1 ) ) ) );
} catch ( RuntimeException $home_error ) {
	echo '<div class="oml-hub-empty"><h1>' . esc_html__( 'This learner is not available', 'ohmylms' ) . '</h1><p>' . esc_html__( 'An active, approved home-learning link is required.', 'ohmylms' ) . '</p></div>';
	return;
}
$home_labels = array(
	'lesson'     => __( 'Lesson completed', 'ohmylms' ),
	'quiz'       => __( 'Quiz submitted', 'ohmylms' ),
	'assignment' => __( 'Assignment submitted', 'ohmylms' ),
	'practice'   => __( 'Practice completed', 'ohmylms' ),
);
?>
<section class="oml-hub-card oml-hub-settings-card oml-hub-family-report">
	<a class="oml-hub-pill" href="<?php echo esc_url( ParentWorkspace::url() ); ?>">&larr; <?php esc_html_e( 'My children', 'ohmylms' ); ?></a>
	<h1><?php echo esc_html( $home_report['child']['display_name'] ); ?></h1>
	<h2><?php esc_html_e( 'Home-learning activity', 'ohmylms' ); ?></h2>
	<p><?php esc_html_e( 'Shows work explicitly started as home learning during your current approved link. Older activity without a recorded context is unavailable here. School work and overall mastery are not included.', 'ohmylms' ); ?></p>
	<nav class="oml-hub-subnav" aria-label="<?php esc_attr_e( 'Report period', 'ohmylms' ); ?>">
		<?php foreach ( array( 7, 30, 90 ) as $home_days ) : ?>
			<a class="oml-hub-pill" href="<?php echo esc_url( add_query_arg( 'hub_period', $home_days, $home_base ) ); ?>" <?php echo $home_days === $home_report['days'] ? 'aria-current="page"' : ''; ?>><?php echo esc_html( sprintf( /* translators: %d: lookback days. */ __( 'Last %d days', 'ohmylms' ), $home_days ) ); ?></a>
		<?php endforeach; ?>
	</nav>
	<p>
	<?php
		/* translators: %d: number of recorded activities. */
		$home_activity_count = sprintf( _n( '%d activity', '%d activities', (int) $home_report['summary']['activities'], 'ohmylms' ), (int) $home_report['summary']['activities'] );
		/* translators: %d: number of UTC dates with recorded activity. */
		$home_day_count = sprintf( _n( '%d active day (UTC)', '%d active days (UTC)', (int) $home_report['summary']['active_days'], 'ohmylms' ), (int) $home_report['summary']['active_days'] );
		echo esc_html( $home_activity_count . ' · ' . $home_day_count );
	?>
	</p>
	<?php if ( $home_report['types'] ) : ?>
		<h2><?php esc_html_e( 'Learning by activity', 'ohmylms' ); ?></h2>
		<ul class="oml-hub-commerce-list">
			<?php foreach ( $home_report['types'] as $home_type ) : ?>
				<li><strong><?php echo esc_html( $home_labels[ $home_type['source_kind'] ] ?? __( 'Activity completed', 'ohmylms' ) ); ?></strong><span><?php echo esc_html( number_format_i18n( (int) $home_type['activities'] ) ); ?></span></li>
			<?php endforeach; ?>
		</ul>
		<details>
			<summary><?php esc_html_e( 'Activity by day (UTC)', 'ohmylms' ); ?></summary>
			<ul class="oml-hub-commerce-list">
				<?php foreach ( $home_report['daily'] as $home_day ) : ?>
					<li><time datetime="<?php echo esc_attr( $home_day['activity_date'] ); ?>"><?php echo esc_html( $home_day['activity_date'] ); ?></time><span><?php echo esc_html( number_format_i18n( (int) $home_day['activities'] ) ); ?></span></li>
				<?php endforeach; ?>
			</ul>
		</details>
		<details>
			<summary><?php esc_html_e( 'Most active courses (up to 10)', 'ohmylms' ); ?></summary>
			<ul class="oml-hub-commerce-list">
				<?php foreach ( $home_report['courses'] as $home_course ) : ?>
					<?php $home_course_title = $home_course['course_id'] ? get_the_title( $home_course['course_id'] ) : __( 'Practice outside a course', 'ohmylms' ); ?>
					<li><strong><?php echo esc_html( $home_course_title ? $home_course_title : __( 'Course no longer available', 'ohmylms' ) ); ?></strong><span><?php echo esc_html( number_format_i18n( (int) $home_course['activities'] ) ); ?></span></li>
				<?php endforeach; ?>
			</ul>
		</details>
	<?php endif; ?>
	<h2><?php esc_html_e( 'Recent activity', 'ohmylms' ); ?></h2>
	<?php if ( ! $home_report['items'] ) : ?>
		<div class="oml-hub-empty"><h3><?php esc_html_e( 'No recorded home learning yet', 'ohmylms' ); ?></h3><p><?php esc_html_e( 'Your child can choose Home learning on an activity page before starting.', 'ohmylms' ); ?></p></div>
	<?php else : ?>
		<ul class="oml-hub-commerce-list">
			<?php foreach ( $home_report['items'] as $home_item ) : ?>
				<?php
				$home_title = 'practice' === $home_item['source_kind'] ? __( 'Skill practice', 'ohmylms' ) : get_the_title( $home_item['content_id'] );
				$home_title = $home_title ? $home_title : __( 'Activity no longer available', 'ohmylms' );
				?>
				<li><div><strong><?php echo esc_html( $home_title ); ?></strong><p><?php echo esc_html( $home_labels[ $home_item['source_kind'] ] ?? __( 'Activity completed', 'ohmylms' ) ); ?></p></div><span><?php echo esc_html( get_date_from_gmt( $home_item['completed_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></span></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<nav class="oml-hub-subnav" aria-label="<?php esc_attr_e( 'Home-learning activity pages', 'ohmylms' ); ?>">
		<?php if ( $home_report['page'] > 1 ) : ?>
			<a class="oml-hub-pill" href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'hub_period'   => $home_report['days'],
						'current_page' => $home_report['page'] - 1,
					),
					$home_base
				)
			);
			?>
											"><?php esc_html_e( 'Previous', 'ohmylms' ); ?></a>
		<?php endif; ?>
		<?php if ( $home_report['page'] < $home_report['pages'] ) : ?>
			<a class="oml-hub-pill" href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'hub_period'   => $home_report['days'],
						'current_page' => $home_report['page'] + 1,
					),
					$home_base
				)
			);
			?>
											"><?php esc_html_e( 'Next', 'ohmylms' ); ?></a>
		<?php endif; ?>
	</nav>
</section>
