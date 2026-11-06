<?php
/**
 * Learner profile home: full-width Khan-style band, stats, courses and assignments.
 *
 * Override in yourtheme/ohmylms/student-hub/home.php.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Student\Hub;
use OhMyLMS\Student\ParentWorkspace;
use OhMyLMS\Student\ProfileHome;
use OhMyLMS\Student\SchoolWorkspace;
use OhMyLMS\Student\TeacherWorkspace;

defined( 'ABSPATH' ) || exit;

$profile      = ProfileHome::gather( $user->ID );
$profile_tab  = ProfileHome::tab();
$joined       = $user->user_registered ? date_i18n( 'M Y', strtotime( $user->user_registered ) ) : '';
$profile_tabs = array(
	'progress'    => __( 'Progress', 'ohmylms' ),
	'courses'     => __( 'Courses', 'ohmylms' ),
	'assignments' => __( 'Assignments', 'ohmylms' ),
	'badges'      => __( 'Badges', 'ohmylms' ),
	'settings'    => __( 'Settings', 'ohmylms' ),
);
$roles        = array(
	array( 'learner', __( 'Learner', 'ohmylms' ), Hub::url() ),
);
if ( TeacherWorkspace::available() ) {
	$roles[] = array( 'teacher', __( 'Teacher', 'ohmylms' ), TeacherWorkspace::url() );
}
if ( ParentWorkspace::available() ) {
	$roles[] = array( 'parent', __( 'Parent', 'ohmylms' ), ParentWorkspace::url() );
}
if ( SchoolWorkspace::available() ) {
	$roles[] = array( 'school', __( 'School', 'ohmylms' ), SchoolWorkspace::url() );
}
$levels = array(
	'attempted'  => __( 'Attempted', 'ohmylms' ),
	'familiar'   => __( 'Familiar', 'ohmylms' ),
	'proficient' => __( 'Proficient', 'ohmylms' ),
	'mastered'   => __( 'Mastered', 'ohmylms' ),
);

/**
 * One course card.
 *
 * @param array $course Course row from ProfileHome.
 */
$course_card = static function ( array $course ) use ( $levels ) {
	?>
	<article class="oml-profile-card oml-profile-course">
		<h3><?php echo esc_html( $course['name'] ); ?></h3>
		<?php if ( ! empty( $course['segments'] ) ) : ?>
			<div class="oml-profile-mbar" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: attempted percent, 2: familiar percent, 3: proficient percent, 4: mastered percent. */ __( 'Attempted %1$s%%, Familiar %2$s%%, Proficient %3$s%%, Mastered %4$s%%', 'ohmylms' ), $course['segments']['attempted'], $course['segments']['familiar'], $course['segments']['proficient'], $course['segments']['mastered'] ) ); ?>">
				<?php foreach ( $levels as $key => $label ) : ?>
					<span class="is-<?php echo esc_attr( $key ); ?>" style="width:<?php echo esc_attr( $course['segments'][ $key ] ); ?>%"></span>
				<?php endforeach; ?>
			</div>
			<ul class="oml-profile-legend">
				<?php foreach ( $levels as $key => $label ) : ?>
					<li><i class="is-<?php echo esc_attr( $key ); ?>" aria-hidden="true"></i><?php echo esc_html( $label ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<div class="oml-profile-mbar" role="progressbar" aria-valuenow="<?php echo esc_attr( (int) $course['percent'] ); ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?php echo esc_attr( $course['name'] ); ?>">
				<span class="is-proficient" style="width:<?php echo esc_attr( (int) $course['percent'] ); ?>%"></span>
			</div>
			<p class="oml-profile-note"><?php esc_html_e( 'Skill levels appear when this course has skills. The bar shows course progress.', 'ohmylms' ); ?></p>
		<?php endif; ?>
		<div class="oml-profile-course-row">
			<strong>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: completion percentage. */
						__( '%s%% complete', 'ohmylms' ),
						(int) $course['percent']
					)
				);
				?>
			</strong>
			<a class="oml-hub-button oml-profile-continue" href="<?php echo esc_url( $course['url'] ); ?>"><?php esc_html_e( 'Continue', 'ohmylms' ); ?></a>
		</div>
	</article>
	<?php
};

/**
 * Assignment rows.
 *
 * @param array $items Assignment rows.
 * @param string $empty_message Empty-state sentence.
 */
$assignment_list = static function ( array $items, $empty_message ) {
	if ( ! $items ) {
		echo '<div class="oml-profile-empty">';
		\OhMyLMS\Design\Mascot::render( 'empty' );
		echo '<p>' . esc_html( $empty_message ) . '</p></div>';
		return;
	}
	echo '<ul class="oml-profile-next">';
	foreach ( $items as $item ) {
		echo '<li><div>';
		if ( ! empty( $item['url'] ) ) {
			echo '<a href="' . esc_url( $item['url'] ) . '"><strong>' . esc_html( $item['title'] ) . '</strong></a>';
		} else {
			echo '<strong>' . esc_html( $item['title'] ) . '</strong>';
		}
		if ( ! empty( $item['context'] ) ) {
			echo '<div class="oml-profile-note">' . esc_html( $item['context'] ) . '</div>';
		}
		echo '</div>';
		if ( ! empty( $item['due'] ) ) {
			echo '<span class="oml-profile-due' . ( ! empty( $item['soon'] ) ? ' is-soon' : '' ) . '">' . esc_html( $item['due'] ) . '</span>';
		}
		echo '</li>';
	}
	echo '</ul>';
};
?>
<div class="oml-profile">
	<header class="oml-profile-band">
		<div class="oml-profile-wrap oml-profile-band-row">
			<div class="oml-profile-avatar"><?php echo get_avatar( $user->ID, 88, '', $user->display_name, array( 'class' => 'oml-profile-avatar-img' ) ); ?></div>
			<div class="oml-profile-who">
				<h1><?php echo esc_html( $user->display_name ); ?></h1>
				<p>
					<?php
					echo esc_html( '@' . $user->user_login );
					if ( $joined ) {
						echo ' · ' . esc_html(
							sprintf(
								/* translators: %s: month and year the account was created. */
								__( 'Joined %s', 'ohmylms' ),
								$joined
							)
						);
					}
					?>
				</p>
			</div>
			<?php \OhMyLMS\Design\Mascot::render( 'profile' ); ?>
			<div class="oml-profile-actions">
				<?php if ( count( $roles ) > 1 ) : ?>
					<div class="oml-profile-roles" role="group" aria-label="<?php esc_attr_e( 'View as', 'ohmylms' ); ?>">
						<?php foreach ( $roles as $profile_role ) : ?>
							<a href="<?php echo esc_url( $profile_role[2] ); ?>" <?php echo 'learner' === $profile_role[0] ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $profile_role[1] ); ?></a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<a class="oml-profile-edit" href="<?php echo esc_url( Hub::url( 'account', 'account' ) ); ?>"><?php esc_html_e( 'Edit profile', 'ohmylms' ); ?></a>
			</div>
		</div>
		<div class="oml-profile-wrap">
			<div class="oml-profile-pills">
				<?php if ( $profile['streak']['available'] ) : ?>
					<span class="oml-profile-pill"><?php echo esc_html( sprintf( /* translators: %d: days in the current learning streak. */ _n( '%d day streak', '%d days streak', (int) $profile['streak']['days'], 'ohmylms' ), (int) $profile['streak']['days'] ) ); ?></span>
				<?php endif; ?>
				<?php if ( $profile['points']['available'] ) : ?>
					<span class="oml-profile-pill">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: point total. */
								__( '%s energy points', 'ohmylms' ),
								number_format_i18n( (int) $profile['points']['total'] )
							)
						);
						?>
					</span>
				<?php endif; ?>
			</div>
			<?php if ( $profile['level']['available'] ) : ?>
				<div class="oml-profile-level">
					<div class="oml-profile-level-label">
						<span><?php echo esc_html( $profile['level']['name'] ); ?></span>
						<span>
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: current points or XP, 2: points or XP required for the next step. */
									__( '%1$s / %2$s', 'ohmylms' ),
									number_format_i18n( (int) $profile['level']['current'] ),
									number_format_i18n( (int) $profile['level']['goal'] )
								)
							);
							echo 'xp' === $profile['level']['mode'] ? ' ' . esc_html__( 'XP', 'ohmylms' ) : '';
							?>
						</span>
					</div>
					<div class="oml-profile-mbar" role="progressbar" aria-valuenow="<?php echo esc_attr( (int) $profile['level']['percent'] ); ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?php echo esc_attr( $profile['level']['name'] ); ?>">
						<span class="is-level" style="width:<?php echo esc_attr( (int) $profile['level']['percent'] ); ?>%"></span>
					</div>
					<?php if ( 'xp' === $profile['level']['mode'] && isset( $profile['level']['total'] ) ) : ?>
						<p class="oml-profile-note">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: lifetime XP. */
									__( '%s XP in total. Levels are turned off, so this bar is today’s XP against your goal.', 'ohmylms' ),
									number_format_i18n( (int) $profile['level']['total'] )
								)
							);
							?>
						</p>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<p class="oml-profile-note"><?php echo esc_html( $profile['level']['note'] ); ?></p>
			<?php endif; ?>
		</div>
	</header>

	<div class="oml-profile-tabs oml-profile-wrap" role="tablist" aria-label="<?php esc_attr_e( 'Profile', 'ohmylms' ); ?>">
		<?php foreach ( $profile_tabs as $key => $label ) : ?>
			<a role="tab" href="<?php echo esc_url( add_query_arg( 'profile_tab', $key, Hub::url() ) ); ?>" aria-selected="<?php echo $profile_tab === $key ? 'true' : 'false'; ?>"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
	</div>

	<div class="oml-profile-wrap">
		<?php if ( 'courses' === $profile_tab ) : ?>
			<h2><?php esc_html_e( 'My courses', 'ohmylms' ); ?></h2>
			<?php if ( ! $profile['courses'] ) : ?>
				<div class="oml-profile-empty">
					<?php \OhMyLMS\Design\Mascot::render( 'empty' ); ?>
					<p><?php esc_html_e( 'You have not enrolled in any courses yet.', 'ohmylms' ); ?></p>
					<a class="oml-hub-button" href="<?php echo esc_url( Hub::catalog_url() ); ?>"><?php esc_html_e( 'Explore courses', 'ohmylms' ); ?></a>
				</div>
			<?php else : ?>
				<div class="oml-profile-courses">
					<?php foreach ( $profile['courses'] as $course ) : ?>
						<?php $course_card( $course ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		<?php elseif ( 'assignments' === $profile_tab ) : ?>
			<section class="oml-profile-card" aria-labelledby="oml-profile-assignments">
				<h2 id="oml-profile-assignments"><?php esc_html_e( 'Assignments', 'ohmylms' ); ?></h2>
				<?php $assignment_list( $profile['assignments']['items'], $profile['assignments']['note'] ); ?>
			</section>
		<?php elseif ( 'badges' === $profile_tab ) : ?>
			<section class="oml-profile-card" aria-labelledby="oml-profile-badges">
				<h2 id="oml-profile-badges"><?php esc_html_e( 'Badges', 'ohmylms' ); ?></h2>
				<?php if ( ! $profile['badges']['available'] || ! $profile['badges']['items'] ) : ?>
					<div class="oml-profile-empty">
						<?php \OhMyLMS\Design\Mascot::render( 'empty' ); ?>
						<p><?php echo esc_html( $profile['badges']['note'] ); ?></p>
					</div>
				<?php else : ?>
					<p class="oml-profile-note"><?php esc_html_e( 'Pick up to 5 badges to show on your profile.', 'ohmylms' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="ohmylms_badge_showcase">
						<?php wp_nonce_field( 'ohmylms_badge_showcase' ); ?>
						<ul class="oml-profile-badge-grid">
							<?php
							$shown = array_column( $profile['badges']['showcase'], 'slug' );
							foreach ( $profile['badges']['items'] as $badge ) :
								?>
								<li>
									<label class="oml-profile-badge<?php echo $badge['earned'] ? '' : ' is-locked'; ?>">
										<input type="checkbox" name="badges[]" value="<?php echo esc_attr( $badge['slug'] ); ?>" <?php checked( in_array( $badge['slug'], $shown, true ) ); ?> <?php disabled( ! $badge['earned'] ); ?>>
										<span class="oml-profile-badge-icon">
											<?php if ( $badge['earned'] && '' !== $badge['image'] ) : ?>
												<img src="<?php echo esc_url( $badge['image'] ); ?>" alt="" width="48" height="48">
											<?php else : ?>
												<span aria-hidden="true"><?php echo $badge['earned'] ? '★' : '·'; ?></span>
											<?php endif; ?>
										</span>
										<?php echo esc_html( $badge['name'] ); ?>
										<?php if ( ! $badge['earned'] ) : ?>
											<span class="oml-profile-note"><?php esc_html_e( 'Locked', 'ohmylms' ); ?></span>
										<?php endif; ?>
									</label>
								</li>
							<?php endforeach; ?>
						</ul>
						<button type="submit" class="oml-hub-button"><?php esc_html_e( 'Save showcase', 'ohmylms' ); ?></button>
					</form>
				<?php endif; ?>
			</section>
		<?php elseif ( 'settings' === $profile_tab ) : ?>
			<?php
			$view         = 'account';
			$teacher_mode = false;
			$parent_mode  = false;
			$school_mode  = false;
			ohmylms_get_template( 'student-hub/settings.php', compact( 'view', 'teacher_mode', 'parent_mode', 'school_mode' ) );
			?>
		<?php else : ?>
			<div class="oml-profile-stats">
				<section class="oml-profile-card">
					<p class="oml-profile-note"><?php esc_html_e( 'Minutes learned this week', 'ohmylms' ); ?></p>
					<p><?php echo esc_html( $profile['minutes']['note'] ); ?></p>
				</section>
				<section class="oml-profile-card">
					<p class="oml-profile-note"><?php esc_html_e( 'Skills proficient / mastered', 'ohmylms' ); ?></p>
					<?php if ( empty( $profile['skills']['available'] ) ) : ?>
						<p><?php echo esc_html( $profile['skills']['note'] ); ?></p>
					<?php else : ?>
						<p class="oml-profile-stat"><?php echo esc_html( (int) $profile['skills']['proficient'] . ' / ' . (int) $profile['skills']['mastered'] ); ?></p>
						<?php if ( ! $profile['skills']['total'] ) : ?>
							<p class="oml-profile-note"><?php esc_html_e( 'No skills are proficient or mastered yet.', 'ohmylms' ); ?></p>
						<?php else : ?>
							<p class="oml-profile-note">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %d: skills practised at proficient or mastered this week. */
										_n( '%d this week', '%d this week', (int) $profile['skills']['week'], 'ohmylms' ),
										(int) $profile['skills']['week']
									)
								);
								?>
							</p>
						<?php endif; ?>
					<?php endif; ?>
				</section>
				<section class="oml-profile-card">
					<p class="oml-profile-note"><?php esc_html_e( 'Exercises done', 'ohmylms' ); ?></p>
					<?php if ( empty( $profile['exercises']['available'] ) ) : ?>
						<p><?php echo esc_html( $profile['exercises']['note'] ); ?></p>
					<?php else : ?>
						<p class="oml-profile-stat"><?php echo esc_html( number_format_i18n( (int) $profile['exercises']['total'] ) ); ?></p>
						<p class="oml-profile-note">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: finished quizzes and practice sessions this week. */
									_n( '%d this week', '%d this week', (int) $profile['exercises']['week'], 'ohmylms' ),
									(int) $profile['exercises']['week']
								)
							);
							?>
						</p>
					<?php endif; ?>
				</section>
			</div>
			<div class="oml-profile-cols">
				<div>
					<section class="oml-profile-card">
						<div class="oml-profile-card-head">
							<h2><?php esc_html_e( 'Badge showcase', 'ohmylms' ); ?></h2>
							<a class="oml-profile-text-button" href="<?php echo esc_url( add_query_arg( 'profile_tab', 'badges', Hub::url() ) ); ?>"><?php esc_html_e( 'See all badges', 'ohmylms' ); ?></a>
						</div>
						<?php if ( ! $profile['badges']['showcase'] ) : ?>
							<div class="oml-profile-empty">
								<?php \OhMyLMS\Design\Mascot::render( 'empty' ); ?>
								<p><?php echo esc_html( $profile['badges']['note'] ? $profile['badges']['note'] : __( 'No badges to show yet.', 'ohmylms' ) ); ?></p>
							</div>
						<?php else : ?>
							<ul class="oml-profile-showcase">
								<?php foreach ( $profile['badges']['showcase'] as $badge ) : ?>
									<li>
										<span class="oml-profile-badge-icon">
											<?php if ( '' !== $badge['image'] ) : ?>
												<img src="<?php echo esc_url( $badge['image'] ); ?>" alt="" width="48" height="48">
											<?php endif; ?>
										</span>
										<?php echo esc_html( $badge['name'] ); ?>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</section>
					<section>
						<h2><?php esc_html_e( 'My courses', 'ohmylms' ); ?></h2>
						<?php if ( ! $profile['courses'] ) : ?>
							<div class="oml-profile-empty">
								<?php \OhMyLMS\Design\Mascot::render( 'empty' ); ?>
								<p><?php esc_html_e( 'You have not enrolled in any courses yet.', 'ohmylms' ); ?></p>
								<a class="oml-hub-button" href="<?php echo esc_url( Hub::catalog_url() ); ?>"><?php esc_html_e( 'Explore courses', 'ohmylms' ); ?></a>
							</div>
						<?php else : ?>
							<div class="oml-profile-courses">
								<?php foreach ( array_slice( $profile['courses'], 0, 4 ) as $course ) : ?>
									<?php $course_card( $course ); ?>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</section>
				</div>
				<aside>
					<section class="oml-profile-card" aria-labelledby="oml-profile-up-next">
						<h2 id="oml-profile-up-next"><?php esc_html_e( 'Up next', 'ohmylms' ); ?></h2>
						<?php $assignment_list( array_slice( $profile['assignments']['items'], 0, 6 ), $profile['assignments']['note'] ); ?>
					</section>
				</aside>
			</div>
		<?php endif; ?>
	</div>
</div>
