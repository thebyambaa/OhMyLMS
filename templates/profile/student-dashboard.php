<?php
/**
 * Profile student dashboard template.
 *
 * @package OhMyLMS
 */
defined( 'ABSPATH' ) || exit;
if ( ! is_user_logged_in() ) {
	return; }
\OhMyLMS\Practice\Dashboard::$rendered = true;
\OhMyLMS\Practice\Dashboard::assets();
$user_id             = get_current_user_id();
$dashboard           = \OhMyLMS\Practice\Dashboard::data( $user_id );
$courses             = $student->get_enrolled_courses();
$in_progress_courses = array_values(
	array_filter(
		$courses,
		static function ( $item ) use ( $student ) {
			return ! $student->is_course_completed( $item->get_id() );
		}
	)
);
$name                = $student->get_first_name() ?: wp_get_current_user()->display_name;
$streak_enabled      = \OhMyLMS\Engagement\StreakSettings::enabled();
$badges              = ( \OhMyLMS\Engagement\Badge::maybe_enable() || $streak_enabled ) ? \OhMyLMS\Engagement\Badge::get_all_badges_of_a_user( $user_id ) : array();
if ( ! \OhMyLMS\Engagement\Badge::maybe_enable() ) {
	$streak_badges = array_column( \OhMyLMS\Engagement\StreakSettings::get()['milestones'], 'badge' );
	$badges        = array_values(
		array_filter(
			$badges,
			static function ( $badge ) use ( $streak_badges ) {
				return in_array( $badge['slug'], $streak_badges, true );
			}
		)
	);
}
$level      = \OhMyLMS\Engagement\Level::get_current_level_of_a_user( $user_id );
$next_level = \OhMyLMS\Engagement\Level::get_next_level_of_a_user( $user_id );
$browse     = get_permalink( ohmylms_get_page_id( 'course' ) ) ?: home_url( '/' );
?>
<?php ohmylms_get_template( 'global/ohmylms-celebration.php' ); ?>
<section class="oml-student-dashboard" data-dashboard-theme="<?php echo esc_attr( $dashboard['theme'] ); ?>" data-theme-endpoint="<?php echo esc_url( rest_url( 'ohmylms/v1/student/dashboard-theme' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>" data-save-error="<?php esc_attr_e( 'Could not save your theme. Please try again.', 'ohmylms' ); ?>" data-save-success="<?php esc_attr_e( 'Dashboard theme saved.', 'ohmylms' ); ?>">
	<header class="oml-dashboard-greeting">
		<div class="oml-dashboard-person"><?php echo get_avatar( $user_id, 56, '', '', array( 'class' => 'oml-dashboard-avatar' ) ); ?><div><p class="oml-dashboard-eyebrow"><?php esc_html_e( 'Your learning space', 'ohmylms' ); ?></p><h1><?php echo esc_html( sprintf( /* translators: %s: text value. */
		__( 'Hi %s, let’s keep learning!', 'ohmylms' ), $name ) ); ?></h1></div></div>
		<details class="oml-dashboard-customize"><summary><?php esc_html_e( 'Customize dashboard', 'ohmylms' ); ?></summary><div class="oml-dashboard-theme-picker"><p><?php esc_html_e( 'Choose your background', 'ohmylms' ); ?></p>
		<?php
		foreach ( array(
			'meadow' => __( 'Meadow', 'ohmylms' ),
			'ocean'  => __( 'Ocean', 'ohmylms' ),
			'sunset' => __( 'Sunset', 'ohmylms' ),
		) as $key => $label ) {
			?>
												<button type="button" data-select-theme="<?php echo esc_attr( $key ); ?>" aria-pressed="<?php echo $dashboard['theme'] === $key ? 'true' : 'false'; ?>"><span class="oml-theme-swatch oml-theme-<?php echo esc_attr( $key ); ?>" aria-hidden="true"></span><?php echo esc_html( $label ); ?></button><?php } ?><p role="status" data-theme-message></p></div></details>
	</header>
	<div class="oml-dashboard-main-grid">
		<section class="oml-dashboard-work" aria-labelledby="oml-work-heading">
			<h2 id="oml-work-heading"><?php esc_html_e( 'What should I work on?', 'ohmylms' ); ?></h2>
			<div class="oml-dashboard-card">
				<div class="oml-dashboard-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Learning activities', 'ohmylms' ); ?>">
					<?php
					foreach ( array(
						'continue'        => __( 'Continue learning', 'ohmylms' ),
						'recent'          => __( 'Recent skills', 'ohmylms' ),
						'recommendations' => __( 'Recommendations', 'ohmylms' ),
					) as $key => $label ) {
						?>
																				<button type="button" role="tab" id="oml-tab-<?php echo esc_attr( $key ); ?>" aria-controls="oml-panel-<?php echo esc_attr( $key ); ?>" aria-selected="<?php echo 'continue' === $key ? 'true' : 'false'; ?>" tabindex="<?php echo 'continue' === $key ? '0' : '-1'; ?>"><?php echo esc_html( $label ); ?></button><?php } ?>
				</div>
				<div role="tabpanel" id="oml-panel-continue" aria-labelledby="oml-tab-continue" tabindex="0">
					<?php
					if ( ! $in_progress_courses ) {
						?>
						<div class="oml-dashboard-empty"><span aria-hidden="true">✦</span><h3><?php esc_html_e( 'Your next chapter starts here', 'ohmylms' ); ?></h3><p><?php esc_html_e( 'Choose a course to begin your learning journey.', 'ohmylms' ); ?></p><a class="oml-dashboard-button" href="<?php echo esc_url( $browse ); ?>"><?php esc_html_e( 'Explore courses', 'ohmylms' ); ?></a></div><?php } ?>
					<div class="oml-dashboard-course-grid">
					<?php
					foreach ( array_slice( $in_progress_courses, 0, 6 ) as $learning_course ) {
						$percent = max( 0, min( 100, (float) $student->get_course_progress_percentage( $learning_course->get_id() ) ) );
						?>
						<article class="oml-dashboard-course"><img src="<?php echo esc_url( has_post_thumbnail( $learning_course->get_id() ) ? $learning_course->get_thumbnail_url() : plugins_url( 'assets/images/dashboard-course.svg', OHMYLMS_FILE ) ); ?>" alt="" loading="lazy"><div><h3><a href="<?php echo esc_url( $learning_course->get_permalink() ); ?>"><?php echo esc_html( $learning_course->get_name() ); ?></a></h3><p><?php echo esc_html( sprintf( /* translators: %s: text value. */
						__( '%s%% complete', 'ohmylms' ), $percent ) ); ?></p><progress max="100" value="<?php echo esc_attr( $percent ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: text value. */
						__( '%s course progress', 'ohmylms' ), $learning_course->get_name() ) ); ?>"></progress><a class="oml-dashboard-button" href="<?php echo esc_url( $student->get_course_resume_url( $learning_course->get_id() ) ); ?>"><?php echo esc_html( $percent > 0 ? __( 'Continue', 'ohmylms' ) : __( 'Start learning', 'ohmylms' ) ); ?></a></div></article><?php } ?></div>
					<?php
					if ( $courses ) {
						?>
						<a class="oml-dashboard-text-link" href="<?php echo esc_url( ohmylms_get_account_endpoint_url( 'my-courses' ) ); ?>"><?php esc_html_e( 'View all my courses →', 'ohmylms' ); ?></a><?php } ?>
				</div>
				<div role="tabpanel" id="oml-panel-recent" aria-labelledby="oml-tab-recent" tabindex="0" hidden>
					<?php
					if ( ! $dashboard['recent'] ) {
						?>
						<div class="oml-dashboard-empty"><h3><?php esc_html_e( 'Build your first skill', 'ohmylms' ); ?></h3><p><?php esc_html_e( 'Your recently practiced skills will appear here as you learn.', 'ohmylms' ); ?></p></div><?php } ?>
					<div class="oml-dashboard-skill-grid">
					<?php
					foreach ( $dashboard['recent'] as $skill ) {
						?>
						<article class="oml-dashboard-skill"><span class="oml-dashboard-pill"><?php echo esc_html( $skill['level_label'] ); ?></span><h3><?php echo esc_html( $skill['name'] ); ?></h3>
						<?php
						if ( ! empty( $skill['mastery'] ) ) {
							$mastery = $skill['mastery'];
							?>
						<p class="oml-mastery-score"><strong><?php echo esc_html( (int) round( $mastery['score'] ) ); ?></strong><span>/100</span>
							<?php
							if ( $mastery['medal'] ) :
								?>
								<span class="oml-mastery-medal is-<?php echo esc_attr( $mastery['medal'] ); ?>"><?php echo esc_html( ucfirst( $mastery['medal'] ) ); ?></span><?php endif; ?>
						</p>
						<progress max="100" value="<?php echo esc_attr( (int) round( $mastery['score'] ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: skill name */ __( '%s mastery score', 'ohmylms' ), $skill['name'] ) ); ?>"></progress>
							<?php
						}
						if ( ! empty( $skill['last_evidence_at'] ) ) {
							?>
						<p><?php echo esc_html( sprintf( /* translators: %s: text value. */
						__( 'Last practiced %s', 'ohmylms' ), wp_date( 'M j', strtotime( $skill['last_evidence_at'] . ' UTC' ), new DateTimeZone( $dashboard['timezone'] ) ) ) ); ?></p><?php } ?><p><?php echo esc_html( $skill['review_due'] ? __( 'Ready for a review', 'ohmylms' ) : sprintf( /* translators: %d: number. */
						__( '%d independent correct answers', 'ohmylms' ), $skill['independent_correct'] ) ); ?></p><a class="oml-dashboard-button" href="<?php echo esc_url( add_query_arg( 'ohmylms_practice', $skill['id'], home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Practice skill', 'ohmylms' ); ?></a></article><?php } ?></div>
				</div>
				<div role="tabpanel" id="oml-panel-recommendations" aria-labelledby="oml-tab-recommendations" tabindex="0" hidden>
					<?php
					if ( ! $dashboard['recommendations'] ) {
						?>
						<div class="oml-dashboard-empty"><h3><?php esc_html_e( 'Keep exploring', 'ohmylms' ); ?></h3><p><?php esc_html_e( 'As you practice, suggestions for review and your next skills will appear here.', 'ohmylms' ); ?></p></div><?php } ?>
					<?php
					foreach ( $dashboard['recommendations'] as $suggestion ) {
						if ( empty( $suggestion['skill'] ) ) {
							continue; }
						?>
						<article class="oml-dashboard-recommendation"><div><h3><?php echo esc_html( $suggestion['skill']['name'] ); ?></h3><p><?php echo esc_html( $suggestion['message'] ); ?></p>
						<?php
						foreach ( $suggestion['lessons'] as $lesson ) {
							?>
	<a class="oml-dashboard-text-link" href="<?php echo esc_url( $lesson['url'] ); ?>"><?php echo esc_html( $lesson['title'] ); ?></a><?php } ?></div><a class="oml-dashboard-button" href="<?php echo esc_url( add_query_arg( 'ohmylms_practice', $suggestion['skill']['id'], home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Practice', 'ohmylms' ); ?></a></article><?php } ?>
				</div>
			</div>
		</section>
		<aside class="oml-dashboard-aside" aria-label="<?php esc_attr_e( 'Learning goals', 'ohmylms' ); ?>">
			<?php
			ohmylms_get_template( 'profile/xp.php', array( 'user_id' => $user_id ) );
			if ( $streak_enabled ) {
				ohmylms_get_template( 'profile/streak.php', array( 'in_progress_courses' => $in_progress_courses ) );
				$snapshot = \OhMyLMS\Engagement\Streak::snapshot( $user_id );
				if ( $snapshot && ! is_wp_error( $snapshot ) ) {
					$milestones = \OhMyLMS\Engagement\StreakSettings::get()['milestones'];
					usort(
						$milestones,
						static function ( $a, $b ) {
							return $a['days'] <=> $b['days'];
						}
					);
					foreach ( $milestones as $milestone ) {
						global $wpdb;
						if ( $milestone['days'] <= $snapshot['current_streak'] || $wpdb->get_var( $wpdb->prepare( 'SELECT days FROM %i WHERE user_id=%d AND days=%d', \OhMyLMS\Engagement\StreakSchema::table( 'milestones' ), $user_id, $milestone['days'] ) ) ) {
							continue; }
						?>
					<div class="oml-dashboard-card oml-dashboard-goal"><span class="oml-dashboard-pill"><?php esc_html_e( 'Next streak milestone', 'ohmylms' ); ?></span><h3><?php echo esc_html( sprintf( /* translators: %d: number. */
					__( '%d learning days', 'ohmylms' ), $milestone['days'] ) ); ?></h3><p><?php echo esc_html( sprintf( /* translators: %d: number. */
					__( '%d more qualifying days to reach this milestone.', 'ohmylms' ), $milestone['days'] - $snapshot['current_streak'] ) ); ?></p><progress max="<?php echo esc_attr( $milestone['days'] ); ?>" value="<?php echo esc_attr( $snapshot['current_streak'] ); ?>" aria-label="<?php esc_attr_e( 'Next streak milestone progress', 'ohmylms' ); ?>"></progress></div>
						<?php
						break; }
				}
			}
			?>
			<div class="oml-dashboard-card oml-dashboard-goal"><span class="oml-dashboard-pill"><?php esc_html_e( 'My learning journey', 'ohmylms' ); ?></span><h3><?php esc_html_e( 'Every step counts', 'ohmylms' ); ?></h3><dl class="oml-dashboard-course-counts"><div><dt><?php esc_html_e( 'Enrolled courses', 'ohmylms' ); ?></dt><dd><?php echo esc_html( $student->get_enrolled_course_count() ); ?></dd></div><div><dt><?php esc_html_e( 'Completed courses', 'ohmylms' ); ?></dt><dd><?php echo esc_html( $student->get_completed_course_count() ); ?></dd></div></dl><a class="oml-dashboard-text-link" href="<?php echo esc_url( $browse ); ?>"><?php esc_html_e( 'Explore something new →', 'ohmylms' ); ?></a></div>
		</aside>
	</div>
	<section aria-labelledby="oml-progress-heading"><h2 id="oml-progress-heading"><?php esc_html_e( 'How am I doing?', 'ohmylms' ); ?></h2><div class="oml-dashboard-card oml-dashboard-week"><header><h3><?php esc_html_e( 'This week', 'ohmylms' ); ?></h3><p><?php echo esc_html( $dashboard['week_label'] ); ?></p></header><dl class="oml-dashboard-metrics">
	<?php
	foreach ( array(
		'answers' => __( 'Practice answers', 'ohmylms' ),
		'skills'  => __( 'Skills practiced', 'ohmylms' ),
		'days'    => __( 'Active practice days', 'ohmylms' ),
	) as $key => $label ) {
		?>
																									<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $dashboard['weekly'][ $key ] ); ?></dd></div><?php } ?></dl><p><?php esc_html_e( 'Small steps add up. This summary counts your skill practice activity.', 'ohmylms' ); ?></p></div></section>
	<div class="oml-dashboard-bottom-grid">
		<?php
		if ( \OhMyLMS\Engagement\Badge::maybe_enable() || $streak_enabled || \OhMyLMS\Engagement\Level::maybe_enable() ) {
			?>
			<section class="oml-dashboard-card oml-dashboard-achievements"><h2><?php esc_html_e( 'My achievements', 'ohmylms' ); ?></h2>
			<?php
			if ( \OhMyLMS\Engagement\Point::maybe_enable() ) {
				?>
			<p><?php echo esc_html( sprintf( /* translators: %d: number. */
			__( '%d bonus points', 'ohmylms' ), \OhMyLMS\Engagement\Point::get_total_points( $user_id ) ) ); ?></p><?php } ?>
			<?php
			if ( $level ) {
				?>
	<p class="oml-dashboard-pill"><?php echo esc_html( $level['level_name'] ); ?></p><?php } ?>
			<?php
			if ( $next_level ) {
				?>
	<p><?php echo esc_html( sprintf( /* translators: %s: text value. */
	__( 'Working toward %s', 'ohmylms' ), $next_level['level_data']['name'] ?? __( 'the next level', 'ohmylms' ) ) ); ?></p><progress max="100" value="<?php echo esc_attr( $next_level['progress_percentage'] ); ?>" aria-label="<?php esc_attr_e( 'Next level progress', 'ohmylms' ); ?>"></progress><?php } ?><ul class="oml-dashboard-badges">
			<?php
			foreach ( $badges as $badge ) {
				?>
	<li>
				<?php
				if ( ! empty( $badge['image'] ) ) {
					?>
	<img src="<?php echo esc_url( $badge['image'] ); ?>" alt="" loading="lazy">
					<?php
				} else {
					?>
	<span aria-hidden="true">★</span><?php } ?><strong><?php echo esc_html( $badge['name'] ); ?></strong></li><?php } ?></ul>
			<?php
			if ( ! $badges ) {
				?>
	<p><?php esc_html_e( 'Your earned badges will appear here. Keep learning toward your next achievement.', 'ohmylms' ); ?></p><?php } ?></section><?php } ?>
		<?php
		if ( \OhMyLMS\Engagement\Leaderboard::maybe_enable() ) {
			?>
			<section class="oml-dashboard-card"><h2><?php esc_html_e( 'My leaderboards', 'ohmylms' ); ?></h2>
			<?php
			$has_leaderboard = false;
			foreach ( $courses as $ranked_course ) {
				if ( ! \OhMyLMS\Engagement\Leaderboard::maybe_enable_for_course( $ranked_course->get_id() ) ) {
					continue;
				} $leaders = apply_filters( 'ohmylms_leaderboard_students', $ranked_course->get_students(), $ranked_course->get_id() );
				if ( ! $leaders || ! is_array( $leaders ) ) {
					continue;
				} $has_leaderboard = true;
				?>
	<h3><?php echo esc_html( $ranked_course->get_name() ); ?></h3><ol class="oml-dashboard-leaders">
				<?php
				foreach ( array_slice( $leaders, 0, 3 ) as $leader ) {
					?>
	<li><strong><?php echo esc_html( $leader['position_in_text'] ?? '' ); ?></strong><span><?php echo esc_html( $leader['name'] ); ?></span></li><?php } ?></ol><a class="oml-dashboard-text-link" href="<?php echo esc_url( $ranked_course->get_permalink() ); ?>"><?php esc_html_e( 'View course leaderboard →', 'ohmylms' ); ?></a>
				<?php
				break; } if ( ! $has_leaderboard ) {
				?>
	<p><?php esc_html_e( 'Course rankings will appear here when an enrolled course has a leaderboard.', 'ohmylms' ); ?></p><?php } ?></section><?php } ?>
	</div>
	<?php
	if ( $dashboard['skills'] ) {
		?>
		<details class="oml-dashboard-card oml-dashboard-skill-summary"><summary><?php esc_html_e( 'All my skills', 'ohmylms' ); ?></summary><ul>
		<?php
		foreach ( $dashboard['skills'] as $skill ) {
			?>
		<li><a href="<?php echo esc_url( add_query_arg( 'ohmylms_practice', $skill['id'], home_url( '/' ) ) ); ?>"><?php echo esc_html( $skill['name'] ); ?></a><span><?php echo esc_html( $skill['level_label'] ); ?></span></li><?php } ?></ul></details><?php } ?>
	<?php \OhMyLMS\Bundles\Frontend::print_once(); ?>
	<?php
	if ( ohmylms_is_pro() ) {
		$memberships = $student->get_enrolled_memberships(); if ( $memberships ) {
			?>
		<section class="oml-dashboard-card"><h2><?php esc_html_e( 'My memberships', 'ohmylms' ); ?></h2>
			<?php
			foreach ( $memberships as $membership ) {
				ohmylms_get_template( 'profile/loop/membership-card.php', array( 'memebership' => $membership ) ); }
			?>
	</section>
			<?php
		}
	}
	?>
</section>
