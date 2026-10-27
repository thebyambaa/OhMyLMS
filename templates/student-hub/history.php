<?php
/**
 * History: the learner's own completed lessons, quiz and exam attempts and skill practice.
 * Override in yourtheme/ohmylms/student-hub/history.php.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Student\HistoryFeed;
use OhMyLMS\Student\Hub;

defined( 'ABSPATH' ) || exit;

$hub_periods = array(
	'week'  => array( __( 'This week', 'ohmylms' ), 7 ),
	'month' => array( __( 'This month', 'ohmylms' ), 30 ),
	'all'   => array( __( 'All time', 'ohmylms' ), 0 ),
);
$hub_types   = array(
	'all'      => __( 'All activities', 'ohmylms' ),
	'lesson'   => __( 'Lessons', 'ohmylms' ),
	'quiz'     => __( 'Quizzes', 'ohmylms' ),
	'practice' => __( 'Skill practice', 'ohmylms' ),
);
$hub_period  = get_query_var( 'hub_period', 'week' );
$hub_period  = is_string( $hub_period ) && isset( $hub_periods[ $hub_period ] ) ? $hub_period : 'week';
$hub_type    = get_query_var( 'hub_type', 'all' );
$hub_type    = is_string( $hub_type ) && isset( $hub_types[ $hub_type ] ) ? $hub_type : 'all';
$hub_page    = max( 1, absint( get_query_var( 'current_page', 1 ) ) );
$hub_feed    = HistoryFeed::entries( get_current_user_id(), $hub_periods[ $hub_period ][1], $hub_type, $hub_page, 20 );
$hub_icons   = array(
	'lesson'   => 'M6 3h9l4 4v14H6zM9 12h7M9 16h7',
	'quiz'     => 'M13 2L5 14h6l-1 8 8-12h-6z',
	'practice' => 'M4 20l4-1 11-11-3-3L5 16z',
);
/**
 * Link to this page with some filters changed.
 *
 * @param array $args Query arguments to set.
 * @return string
 */
$hub_link = static function ( array $args ) use ( $hub_period, $hub_type ) {
	return add_query_arg(
		array_merge(
			array(
				'hub_period' => $hub_period,
				'hub_type'   => $hub_type,
			),
			$args
		),
		Hub::url( 'history' )
	);
};
?>
<section class="oml-hub-banner">
	<h1><?php esc_html_e( 'History', 'ohmylms' ); ?></h1>
	<p><?php esc_html_e( 'What you have completed, practised and attempted.', 'ohmylms' ); ?></p>
</section>

<form class="oml-hub-filters" method="get" action="<?php echo esc_url( Hub::base_url() ); ?>">
	<?php if ( ! get_option( 'permalink_structure' ) ) : ?>
		<input type="hidden" name="page_id" value="<?php echo esc_attr( Hub::settings()['page_id'] ); ?>">
	<?php endif; ?>
	<input type="hidden" name="hub_section" value="history">
	<label class="screen-reader-text" for="hub-history-period"><?php esc_html_e( 'Period', 'ohmylms' ); ?></label>
	<select id="hub-history-period" name="hub_period">
		<?php foreach ( $hub_periods as $hub_key => $hub_def ) : ?>
			<option value="<?php echo esc_attr( $hub_key ); ?>" <?php selected( $hub_period, $hub_key ); ?>><?php echo esc_html( $hub_def[0] ); ?></option>
		<?php endforeach; ?>
	</select>
	<label class="screen-reader-text" for="hub-history-type"><?php esc_html_e( 'Activity type', 'ohmylms' ); ?></label>
	<select id="hub-history-type" name="hub_type">
		<?php foreach ( $hub_types as $hub_key => $hub_label ) : ?>
			<option value="<?php echo esc_attr( $hub_key ); ?>" <?php selected( $hub_type, $hub_key ); ?>><?php echo esc_html( $hub_label ); ?></option>
		<?php endforeach; ?>
	</select>
	<button type="submit" class="oml-hub-button"><?php esc_html_e( 'Show', 'ohmylms' ); ?></button>
	<dl class="oml-hub-totals">
		<div><dt><?php esc_html_e( 'activities', 'ohmylms' ); ?></dt><dd><?php echo esc_html( $hub_feed['summary']['activities'] ); ?></dd></div>
		<div><dt><?php esc_html_e( 'practice answers', 'ohmylms' ); ?></dt><dd><?php echo esc_html( $hub_feed['summary']['practice_answers'] ); ?></dd></div>
		<div><dt><?php esc_html_e( 'active days', 'ohmylms' ); ?></dt><dd><?php echo esc_html( $hub_feed['summary']['active_days'] ); ?></dd></div>
	</dl>
</form>

<?php if ( ! $hub_feed['items'] ) : ?>
	<div class="oml-hub-empty">
		<h2><?php esc_html_e( 'Nothing here yet', 'ohmylms' ); ?></h2>
		<p><?php esc_html_e( 'Finish a lesson, a quiz or a skill practice and it will appear here. Try a longer period if you have been away.', 'ohmylms' ); ?></p>
		<a class="oml-hub-button" href="<?php echo esc_url( Hub::url( 'library' ) ); ?>"><?php esc_html_e( 'Browse subjects', 'ohmylms' ); ?></a>
	</div>
<?php else : ?>
	<div class="oml-hub-table-wrap">
		<table class="oml-hub-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Activity', 'ohmylms' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Date', 'ohmylms' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Result', 'ohmylms' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $hub_feed['items'] as $hub_entry ) : ?>
					<tr>
						<td>
							<span class="oml-hub-activity">
								<svg class="oml-hub-kind" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="<?php echo esc_attr( $hub_icons[ $hub_entry['kind'] ] ); ?>"/></svg>
								<span>
									<strong><?php echo esc_html( $hub_entry['name'] ); ?></strong>
									<small><?php echo esc_html( trim( $hub_entry['label'] . ( $hub_entry['course'] ? ' · ' . $hub_entry['course'] : '' ), ' ·' ) ); ?></small>
								</span>
							</span>
						</td>
						<td><?php echo esc_html( $hub_entry['when'] ); ?></td>
						<td><?php echo esc_html( '' !== $hub_entry['result'] ? $hub_entry['result'] : '-' ); ?><?php echo $hub_entry['detail'] ? '<small>' . esc_html( $hub_entry['detail'] ) . '</small>' : ''; ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php if ( $hub_feed['pages'] > 1 ) : ?>
		<nav class="oml-hub-pager" aria-label="<?php esc_attr_e( 'History pages', 'ohmylms' ); ?>">
			<?php if ( $hub_page > 1 ) : ?>
				<a class="oml-hub-pill" href="<?php echo esc_url( $hub_link( array( 'current_page' => $hub_page - 1 ) ) ); ?>">&larr; <?php esc_html_e( 'Newer', 'ohmylms' ); ?></a>
			<?php endif; ?>
			<span><?php /* translators: 1: current page, 2: total pages. */ echo esc_html( sprintf( __( 'Page %1$d of %2$d', 'ohmylms' ), $hub_page, $hub_feed['pages'] ) ); ?></span>
			<?php if ( $hub_page < $hub_feed['pages'] ) : ?>
				<a class="oml-hub-pill" href="<?php echo esc_url( $hub_link( array( 'current_page' => $hub_page + 1 ) ) ); ?>"><?php esc_html_e( 'Older', 'ohmylms' ); ?> &rarr;</a>
			<?php endif; ?>
		</nav>
	<?php endif; ?>
<?php endif; ?>
