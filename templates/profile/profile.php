<?php
/**
 * Template for displaying profile of student profile
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/profile/profile.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 * @global \OhMyLMS\Data\Student $student
 */

defined( 'ABSPATH' ) || exit();
$integrations       = get_option( 'ohmylms_integrations', array() );
$current_level      = '';
$next_level_name    = '';
$engagement_visible = ! empty( $integrations['gamification']['is_enable'] ) || \OhMyLMS\Engagement\StreakSettings::enabled();
if ( $engagement_visible ) {
	$current_point            = \OhMyLMS\Engagement\Point::get_total_points( get_current_user_id() );
	$current_level_data       = \OhMyLMS\Engagement\Level::get_current_level_of_a_user();
	$next_level_data          = \OhMyLMS\Engagement\Level::get_next_level_of_a_user();
	$current_level            = isset( $current_level_data['level_name'] ) ? $current_level_data['level_name'] : '';
	$next_level_name          = isset( $next_level_data['level_data']['name'] ) ? $next_level_data['level_data']['name'] : '';
	$next_level_point         = isset( $next_level_data['points_needed'], $next_level_data['current_points'] ) ? (int) $next_level_data['points_needed'] + (int) $current_point : 0;
	$current_level_color      = isset( $current_level_data['level_color'] ) ? $current_level_data['level_color'] : '#e3d8fc';
	$current_level_text_color = isset( $current_level_data['level_text_color'] ) ? $current_level_data['level_text_color'] : '#000000';
	$progress_percent         = $next_level_data['progress_percentage'] ?? 0;
	$point_needed             = $next_level_point - $current_point;
	$badges                   = \OhMyLMS\Engagement\Badge::maybe_enable() ? \OhMyLMS\Engagement\Badge::get_badges() : ( \OhMyLMS\Engagement\StreakSettings::enabled() ? \OhMyLMS\Engagement\StreakSettings::badges() : array() );
	$earned_badges            = \OhMyLMS\Engagement\Badge::get_all_badges_of_a_user( get_current_user_id() );
	$earned_badges            = array_filter(
		$earned_badges,
		static function ( $badge ) use ( $badges ) {
			return in_array( $badge['slug'], array_column( $badges, 'slug' ), true );
		}
	);
}

?>


<div class="ohmylms-student-profile-tab-content student-profile">
	<h4 class="profile-tab-title">
		<?php echo esc_html__( 'Profile', 'ohmylms' ); ?>
	</h4>

	<div class="ohmylms-profile-cover-area">
		<div class="ohmylms-profile-cover">
			<figure>
				<img src="<?php echo esc_url( $student->get_cover_image() ); ?>" alt="creator lms profile cover">
			</figure>

			<div class="ohmylms-profile-avatar">
				<figure>
					<?php
					$student_profile_photo = $student->get_profile_image();
					if ( $student_profile_photo ) {
						echo '<img src="' . esc_url( $student_profile_photo ) . '" alt="profile photo" id="student-profile-photo">';
					} else {
						echo esc_html( ohmylms_get_initials( $student->get_first_name(), $student->get_last_name() ) );
					}
					?>
				</figure>
			</div>
		</div>

		<div class="ohmylms-profile-actions">
			<a href="<?php echo esc_url( ohmylms_get_account_endpoint_url( 'profile-edit' ) ); ?>" class="profile-edit">
				<?php require OHMYLMS_DIR . '/assets/images/icon/edit-icon.php'; ?>
				<?php echo esc_html__( 'Edit', 'ohmylms' ); ?>
			</a>
		</div>

		<div class="ohmylms-profile-info">
			
			<div class="ohmylms-profile-name">
				<div class="ohmylms-user-details">
					<?php echo esc_html( $student->get_first_name() . ' ' . $student->get_last_name() ); ?>
					<?php if ( $current_level ) : ?>
						<span class="value learning-level" style="background: <?php echo esc_attr( $current_level_color ); ?>; color: <?php echo esc_attr( $current_level_text_color ); ?>;">
							<?php echo esc_html( $current_level ); ?>
						</span>
					<?php endif; ?>
				</div>
				<?php if ( ohmylms_is_pro() && isset( $integrations['gamification']['is_enable'] ) && $integrations['gamification']['is_enable'] ) : ?>
					<div class="ohmylms-user-xp-display">
						<span class="ohmylms-xp-value">
							<?php echo esc_html( $current_point ); ?>
						</span>
						<span class="ohmylms-xp-label"><?php echo esc_html__( 'Total Bonus Points', 'ohmylms' ); ?></span>
					</div>
				<?php endif; ?>
			</div>
		

			<p class="profile-bio">
				<?php echo wp_kses_post( $student->get_bio() ); ?>
			</p>

			<?php if ( ! empty( $student->get_address() ) ) : ?>
				<div class="profile-contact-info">
					<address>
						<?php include OHMYLMS_DIR . '/assets/images/icon/map-marker.php'; ?>
						<span class="profile-address">
							<?php echo esc_html( $student->get_address() ); ?>
						</span>
					</address>
				</div>
			<?php endif ?>

			<?php
			$learner_details = \OhMyLMS\Student\LearnerProfile::get( $student->get_id() );
			$learner_places  = \OhMyLMS\Student\LearnerProfile::provinces();
			$learner_grades  = \OhMyLMS\Student\LearnerProfile::grades();
			?>
			<?php if ( ! empty( $student->get_phone() ) || ! empty( $student->get_whatsapp() ) || ! empty( $student->get_country() ) || array_filter( $learner_details ) ) : ?>
				<div class="profile-contact-details">
					<?php if ( '' !== $learner_details['province'] ) : ?>
						<span class="profile-contact-item">
							<strong><?php echo esc_html__( 'Province:', 'ohmylms' ); ?></strong>
							<?php echo esc_html( $learner_places[ $learner_details['province'] ] ?? $learner_details['province'] ); ?>
						</span>
					<?php endif; ?>
					<?php if ( '' !== $learner_details['school_name'] ) : ?>
						<span class="profile-contact-item">
							<strong><?php echo esc_html__( 'School:', 'ohmylms' ); ?></strong>
							<?php echo esc_html( $learner_details['school_name'] ); ?>
						</span>
					<?php endif; ?>
					<?php if ( '' !== $learner_details['grade'] ) : ?>
						<span class="profile-contact-item">
							<strong><?php echo esc_html__( 'Grade:', 'ohmylms' ); ?></strong>
							<?php echo esc_html( $learner_grades[ $learner_details['grade'] ] ?? $learner_details['grade'] ); ?>
						</span>
					<?php endif; ?>
					<?php if ( ! empty( $student->get_phone() ) ) : ?>
						<span class="profile-contact-item">
							<strong><?php echo esc_html__( 'Phone:', 'ohmylms' ); ?></strong>
							<?php echo esc_html( $student->get_phone() ); ?>
						</span>
					<?php endif; ?>
					<?php if ( ! empty( $student->get_whatsapp() ) ) : ?>
						<span class="profile-contact-item">
							<strong><?php echo esc_html__( 'WhatsApp:', 'ohmylms' ); ?></strong>
							<?php echo esc_html( $student->get_whatsapp() ); ?>
						</span>
					<?php endif; ?>
					<?php if ( ! empty( $student->get_country() ) ) : ?>
						<span class="profile-contact-item">
							<strong><?php echo esc_html__( 'Country:', 'ohmylms' ); ?></strong>
							<?php echo esc_html( $student->get_country() ); ?>
						</span>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php
			$skills = $student->get_skills();
			if ( ! empty( $skills ) && is_array( $skills ) ) :
				?>
				<div class="profile-skills">
					<h5 class="profile-section-title"><?php echo esc_html__( 'Skills', 'ohmylms' ); ?></h5>
					<div class="skills-list">
						<?php foreach ( $skills as $skill ) : ?>
							<?php if ( ! empty( $skill ) ) : ?>
								<span class="skill-tag"><?php echo esc_html( $skill ); ?></span>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php
			$social_links = $student->get_social_links();
			if ( ! empty( $social_links ) && is_array( $social_links ) ) :
				?>
				<div class="profile-social-links">
					<h5 class="profile-section-title"><?php echo esc_html__( 'Social Links', 'ohmylms' ); ?></h5>
					<div class="social-links-list">
						<?php foreach ( $social_links as $link ) : ?>
							<?php if ( ! empty( $link['label'] ) && ! empty( $link['url'] ) ) : ?>
								<a href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener noreferrer" class="social-link">
									<?php echo esc_html( $link['label'] ); ?>
								</a>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( $next_level_name ) : ?>
			<div class="ohmylms-progress-section">
				<div class="ohmylms-progress-header">
					<span class="ohmylms-progress-label"><?php echo esc_html( sprintf( /* translators: %s: text value. */
					__( 'Progress to %s', 'ohmylms' ), $next_level_name ) ); ?></span>
				</div>
				<ul class="ohmylms-level-conditions">
					<?php
					foreach ( $next_level_data['rules'] ?? array() as $rule ) {
						$labels = array(
							'points'            => __( 'Bonus points', 'ohmylms' ),
							'completed_lesson'  => __( 'Completed lessons', 'ohmylms' ),
							'completed_courses' => __( 'Completed courses', 'ohmylms' ),
						);
						?>
						<li><?php echo esc_html( sprintf( /* translators: %1$s: text value; %2$s: text value; %3$s: text value; %4$s: text value. */
						__( '%1$s: %2$s · requirement %3$s %4$s', 'ohmylms' ), $labels[ $rule['type'] ] ?? '', $rule['current'], $rule['operator'], $rule['required'] ) ); ?></li>
					<?php } ?>
				</ul>
				<div class="ohmylms-progress-bar">
					<div class="ohmylms-progress-fill" style="--fill-width: <?php echo esc_html( $progress_percent ); ?>%"></div>
				</div>
				<?php if ( $point_needed > 0 ) : ?>
					<div class="ohmylms-progress-remaining"><?php echo esc_html( $point_needed ); ?> <?php echo esc_html__( 'bonus points until next level', 'ohmylms' ); ?></div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php
		if ( $engagement_visible ) :
			$earned_slugs = array();
			foreach ( $earned_badges as $badge ) {
				$earned_slugs[] = $badge['slug'];
			}
			$total_badges     = is_array( $badges ) ? count( $badges ) : 0;
			$total_earned     = count( $earned_slugs );
			$progress_percent = $total_badges > 0 ? round( ( $total_earned / $total_badges ) * 100, 2 ) : 0;
			if ( $total_badges > 0 ) :
				?>
		<div class="ohmylms-badges-section">
			<h3 class="ohmylms-section-title">
				<svg class="ohmylms-trophy-icon" viewBox="0 0 24 24" fill="currentColor">
					<path d="M7 4V2C7 1.45 7.45 1 8 1H16C16.55 1 17 1.45 17 2V4H20C20.55 4 21 4.45 21 5S20.55 6 20 6H19V7C19 10.31 16.31 13 13 13H11C7.69 13 5 10.31 5 7V6H4C3.45 6 3 5.55 3 5S3.45 4 4 4H7Z"></path>
				</svg>
				<?php echo esc_html__( 'Badges', 'ohmylms' ); ?>
			</h3>

			<div class="ohmylms-badges-grid">
				<?php
				foreach ( $badges as $badge ) :
					$is_earned    = in_array( $badge['slug'], $earned_slugs, true );
					$color        = esc_attr( isset( $badge['color'] ) ? $badge['color'] : '' );
					$name         = esc_html( isset( $badge['name'] ) ? $badge['name'] : '' );
					$tooltip      = esc_attr( isset( $badge['description'] ) ? $badge['description'] : '' );
					$image_url    = esc_url( isset( $badge['image'] ) ? $badge['image'] : '' );
					$status_class = $is_earned ? 'earned' : 'locked';
					?>
					<div class="ohmylms-badge <?php echo esc_attr( $status_class ); ?>" style="--badge-color: <?php echo esc_attr( $color ); ?>;"
					<?php
					if ( $tooltip ) :
						?>
						data-tooltip="<?php echo esc_attr( $tooltip ); ?>" <?php endif; ?>>
						<?php if ( ! $is_earned ) : ?>
							<div class="ohmylms-lock-overlay">
								<svg class="ohmylms-lock-icon" viewBox="0 0 24 24" fill="currentColor">
									<path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"></path>
								</svg>
							</div>
						<?php endif; ?>
						<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $name ); ?>" class="ohmylms-badge-icon" />
						<div class="ohmylms-badge-name"><?php echo esc_html( $name ); ?></div>
					</div>
				<?php endforeach; ?>
			</div>

			<!-- Progress Bar -->
			<div class="ohmylms-badge-progress">
				<div class="ohmylms-progress-text"><?php echo esc_html( "$total_earned of $total_badges badges earned" ); ?></div>
				<div class="ohmylms-progress-bar">
					<div class="ohmylms-progress-fill" style="--fill-width: <?php echo esc_attr( $progress_percent ); ?>%"></div>
				</div>
			</div>
		</div>
				<?php
			endif;
		endif;
		?>
	</div>
</div>
