<?php
/**
 * Profile streak template.
 *
 * @package OhMyLMS
 */
defined( 'ABSPATH' ) || exit;
if ( ! is_user_logged_in() || ! \OhMyLMS\Engagement\StreakSettings::enabled() ) {
	return; }
$streak = \OhMyLMS\Engagement\Streak::snapshot( get_current_user_id() );
if ( ! $streak || is_wp_error( $streak ) ) {
	return; }
wp_enqueue_style( 'ohmylms-streak', plugins_url( 'assets/css/streak.css', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/css/streak.css' ) );
wp_enqueue_script( 'ohmylms-streak', plugins_url( 'assets/js/streak.js', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/js/streak.js' ), true );
$today        = new DateTimeImmutable( $streak['today'], new DateTimeZone( $streak['timezone'] ) );
$week         = $today->modify( '-' . ( (int) $today->format( 'N' ) - 1 ) . ' days' );
$history      = array_column( $streak['history'], 'status', 'local_date' );
$labels       = array(
	'practiced' => __( 'Practiced', 'ohmylms' ),
	'protected' => __( 'Protected by a freeze', 'ohmylms' ),
	'missed'    => __( 'Missed', 'ohmylms' ),
	'pending'   => __( 'Learning due today', 'ohmylms' ),
	'future'    => __( 'Upcoming', 'ohmylms' ),
);
$first_course = ! empty( $in_progress_courses ) ? reset( $in_progress_courses ) : null;
$continue     = $first_course ? ( new \OhMyLMS\Data\Student( get_current_user_id() ) )->get_course_resume_url( $first_course->get_id() ) : get_permalink( ohmylms_get_page_id( 'course' ) );
if ( ! $continue ) {
	$continue = home_url( '/' ); }
?>
<section class="ohmylms-streak-card" aria-labelledby="ohmylms-streak-heading">
	<h2 id="ohmylms-streak-heading"><?php esc_html_e( 'Your learning streak', 'ohmylms' ); ?></h2>
	<dl class="ohmylms-streak-totals">
		<div><dt><?php esc_html_e( 'Current streak', 'ohmylms' ); ?></dt><dd><?php echo esc_html( sprintf( /* translators: %d: number. */
		_n( '%d day', '%d days', $streak['current_streak'], 'ohmylms' ), $streak['current_streak'] ) ); ?></dd></div>
		<div><dt><?php esc_html_e( 'Longest streak', 'ohmylms' ); ?></dt><dd><?php echo esc_html( sprintf( /* translators: %d: number. */
		_n( '%d day', '%d days', $streak['longest_streak'], 'ohmylms' ), $streak['longest_streak'] ) ); ?></dd></div>
		<div><dt><?php esc_html_e( 'Freezes available', 'ohmylms' ); ?></dt><dd><?php echo esc_html( $streak['freezes'] ); ?></dd></div>
	</dl>
	<p role="status"><?php echo esc_html( $streak['today_complete'] ? __( 'Today is complete. Keep learning at your own pace.', 'ohmylms' ) : __( 'Complete a lesson, submit a quiz with answers, or finish qualifying practice today.', 'ohmylms' ) ); ?></p>
	<ol class="ohmylms-streak-week" aria-label="<?php esc_attr_e( 'This week’s learning', 'ohmylms' ); ?>">
		<?php
		for ( $offset = 0; $offset < 7; $offset++ ) {
			$day    = $week->modify( "+$offset days" );
			$date   = $day->format( 'Y-m-d' );
			$status = $history[ $date ] ?? ( $date > $streak['today'] ? 'future' : ( $date === $streak['today'] ? 'pending' : 'missed' ) );
			?>
			<li class="ohmylms-streak-day is-<?php echo esc_attr( $status ); ?>" 
			<?php
			if ( $date === $streak['today'] ) {
				echo 'aria-current="date"'; }
			?>
			>
				<time datetime="<?php echo esc_attr( $date ); ?>"><?php echo esc_html( wp_date( 'D j', $day->getTimestamp(), $day->getTimezone() ) ); ?></time>
				<span aria-hidden="true">
				<?php
				echo esc_html(
					array(
						'practiced' => '✓',
						'protected' => '❄',
						'missed'    => '—',
						'pending'   => '○',
						'future'    => '·',
					)[ $status ]
				);
				?>
											</span>
				<small><?php echo esc_html( $labels[ $status ] ); ?></small>
			</li>
		<?php } ?>
	</ol>
	<a class="ohmylms-button" href="<?php echo esc_url( $continue ); ?>"><?php esc_html_e( 'Continue learning', 'ohmylms' ); ?></a>
	<details>
		<summary><?php echo esc_html( sprintf( /* translators: %s: text value. */
		__( 'Streak timezone: %s', 'ohmylms' ), $streak['timezone'] ) ); ?></summary>
		<p><?php esc_html_e( 'Your timezone stays fixed during an active streak. You can change it after the streak ends and 24 hours after your last activity.', 'ohmylms' ); ?></p>
		<form data-ohmylms-streak-timezone data-endpoint="<?php echo esc_url( rest_url( 'ohmylms/v1/engagement/streak' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>" data-success="<?php esc_attr_e( 'Timezone saved. Refresh this page to update the calendar.', 'ohmylms' ); ?>" data-error="<?php esc_attr_e( 'Could not save timezone. Please retry.', 'ohmylms' ); ?>">
			<label for="ohmylms-learning-timezone"><?php esc_html_e( 'Learning timezone', 'ohmylms' ); ?></label>
			<select id="ohmylms-learning-timezone" name="timezone" required>
				<?php
				if ( ! in_array( $streak['timezone'], DateTimeZone::listIdentifiers(), true ) ) {
					if ( \OhMyLMS\Engagement\Streak::timezone( $streak['timezone'] ) ) {
						?>
						<option selected value="<?php echo esc_attr( $streak['timezone'] ); ?>"><?php echo esc_html( $streak['timezone'] ); ?></option>
					<?php } else { ?>
						<option value="" selected disabled><?php esc_html_e( 'Choose a timezone identifier', 'ohmylms' ); ?></option>
						<?php
					}
				}
				?>
				<?php
				foreach ( DateTimeZone::listIdentifiers() as $zone ) {
					?>
					<option value="<?php echo esc_attr( $zone ); ?>" <?php selected( $zone, $streak['timezone'] ); ?>><?php echo esc_html( $zone ); ?></option><?php } ?>
			</select>
			<button type="submit"><?php esc_html_e( 'Save timezone', 'ohmylms' ); ?></button>
			<p role="status" data-streak-message></p>
		</form>
	</details>
</section>
