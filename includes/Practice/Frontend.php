<?php // phpcs:ignore WordPress.Files.FileName -- Existing PSR-4 class/file mapping.
/**
 * Skill practice pages and entry presentation.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Practice;

use OhMyLMS\Assessment\Engine;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Skills\Evidence;
use OhMyLMS\Skills\Mastery;

defined( 'ABSPATH' ) || exit;

/**
 * Learner-facing pages: the skill progress dashboard ([ohmylms_skills], also shown on the
 * student dashboard) and the practice runner ([ohmylms_practice skill="ID"], or the
 * ?ohmylms_practice=ID page). Logged-in pages also load the guest-claim prompt.
 */
final class Frontend {
	/** Register practice pages, shortcodes and assets. */
	public static function init() {
		add_shortcode( 'ohmylms_skills', array( __CLASS__, 'skills' ) );
		add_shortcode( 'ohmylms_practice', array( __CLASS__, 'practice' ) );
		add_action(
			'ohmylms_lms_student_profile_after_dashboard_content',
			static function () {
				if ( ! Dashboard::$rendered ) {
					echo self::skills(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped markup from the skills renderer.
				}
			}
		);
		add_filter(
			'query_vars',
			static function ( $vars ) {
				$vars[] = 'ohmylms_practice';
				return $vars;
			}
		);
		add_action( 'template_redirect', array( __CLASS__, 'practice_page' ) );
		add_action( 'admin_post_ohmylms_exam_target', array( __CLASS__, 'save_exam_target' ) );
		\OhMyLMS\Placement\PlacementPage::init();
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'claim_assets' ) );
	}

	/**
	 * Save or clear the exam the signed-in learner is preparing for (form without JavaScript).
	 */
	public static function save_exam_target() {
		check_admin_referer( 'ohmylms_exam_target' );
		$student = get_current_user_id();
		if ( ! $student ) {
			wp_die( esc_html__( 'Sign in to set your exam.', 'ohmylms' ), '', array( 'response' => 403 ) );
		}
		$clear = isset( $_POST['clear'] );
		$saved = \OhMyLMS\Performance\Guidance::set_target(
			$student,
			$clear ? 0 : absint( wp_unslash( $_POST['syllabus_id'] ?? 0 ) ),
			$clear ? '' : sanitize_text_field( wp_unslash( $_POST['exam_date'] ?? '' ) )
		);
		$back  = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		if ( is_wp_error( $saved ) ) {
			$back = add_query_arg( 'ohmylms_exam_error', rawurlencode( $saved->get_error_message() ), $back );
		} else {
			$back = remove_query_arg( 'ohmylms_exam_error', $back );
		}
		wp_safe_redirect( $back . '#ohmylms-exam' );
		exit;
	}

	/**
	 * The "Preparing for" card: choose an exam and date, or see the plan toward it.
	 *
	 * @param array $guidance From Snapshot::guidance().
	 * @param int   $student  Learner.
	 * @return string Escaped HTML.
	 */
	private static function exam_card( array $guidance, $student ) {
		$exam    = $guidance['exam'] ?? null;
		$options = \OhMyLMS\Performance\Guidance::targets();
		$target  = \OhMyLMS\Performance\Guidance::target( $student );
		if ( ! $exam && ! $options ) {
			return '';
		}
		$date  = static function ( $value ) {
			return wp_date( get_option( 'date_format' ), strtotime( $value . ' 12:00:00 UTC' ) );
		};
		$error = isset( $_GET['ohmylms_exam_error'] ) ? sanitize_text_field( wp_unslash( $_GET['ohmylms_exam_error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Shows a message only.
		ob_start();
		?>
		<section id="ohmylms-exam" class="ohmylms-guidance-card ohmylms-exam" aria-labelledby="ohmylms-exam-title">
			<h3 id="ohmylms-exam-title"><?php esc_html_e( 'Preparing for', 'ohmylms' ); ?></h3>
			<?php if ( $error ) { ?>
				<p class="ohmylms-exam-error" role="alert"><?php echo esc_html( $error ); ?></p>
			<?php } ?>
			<?php if ( $exam ) { ?>
				<p class="ohmylms-exam-summary">
					<strong><?php echo esc_html( $exam['syllabus']['name'] ); ?></strong>
					<?php
					/* translators: 1: exam date, 2: days left. */
					echo esc_html( sprintf( _n( 'on %1$s, %2$d day left.', 'on %1$s, %2$d days left.', (int) $exam['days_left'], 'ohmylms' ), $date( $exam['date'] ), (int) $exam['days_left'] ) );
					?>
				</p>
				<p>
					<?php
					/* translators: 1: readiness percent, 2: skills ready, 3: skills in the exam. */
					echo esc_html( sprintf( __( 'Readiness %1$d%%: %2$d of %3$d skills proficient.', 'ohmylms' ), (int) $exam['readiness']['percent'], (int) $exam['readiness']['ready'], (int) $exam['readiness']['total'] ) );
					?>
					<?php
					if ( 'review' === $exam['phase'] ) {
						esc_html_e( 'The last week is for review only.', 'ohmylms' );
					} elseif ( $exam['on_track'] ) {
						/* translators: %d: skills per day. */
						echo esc_html( sprintf( _n( 'On track: %d new skill a day.', 'On track: %d new skills a day.', (int) $exam['per_day'], 'ohmylms' ), (int) $exam['per_day'] ) );
					} else {
						esc_html_e( 'Behind: more skills than can be learned at three a day. The plan covers the most useful first.', 'ohmylms' );
					}
					?>
				</p>
				<?php if ( $exam['schedule'] ) { ?>
					<h4><?php esc_html_e( 'This week', 'ohmylms' ); ?></h4>
					<ol class="ohmylms-exam-schedule">
						<?php foreach ( $exam['schedule'] as $day ) { ?>
							<li class="ohmylms-exam-day-<?php echo esc_attr( $day['phase'] ); ?>">
								<strong><?php echo esc_html( $date( $day['date'] ) ); ?></strong>
								<span><?php echo 'review' === $day['phase'] ? esc_html__( 'Review', 'ohmylms' ) : esc_html__( 'Learn', 'ohmylms' ); ?></span>
								<?php echo esc_html( wp_sprintf( '%l', wp_list_pluck( $day['skills'], 'name' ) ) ); ?>
							</li>
						<?php } ?>
					</ol>
				<?php } ?>
			<?php } elseif ( $target ) { ?>
				<p><?php esc_html_e( 'Your exam date has passed. Choose a new exam or remove it.', 'ohmylms' ); ?></p>
			<?php } else { ?>
				<p><?php esc_html_e( 'Choose an exam and its date to get a day-by-day plan.', 'ohmylms' ); ?></p>
			<?php } ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ohmylms-exam-form">
				<input type="hidden" name="action" value="ohmylms_exam_target">
				<?php wp_nonce_field( 'ohmylms_exam_target' ); ?>
				<label><?php esc_html_e( 'Exam', 'ohmylms' ); ?>
					<select name="syllabus_id" required>
						<?php foreach ( $options as $option ) { ?>
							<option value="<?php echo esc_attr( $option['id'] ); ?>" <?php selected( (int) ( $target['syllabus_id'] ?? 0 ), $option['id'] ); ?>><?php echo esc_html( $option['name'] ); ?></option>
						<?php } ?>
					</select>
				</label>
				<label><?php esc_html_e( 'Date', 'ohmylms' ); ?>
					<input type="date" name="exam_date" required min="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>" value="<?php echo esc_attr( $target['date'] ?? '' ); ?>">
				</label>
				<button type="submit"><?php echo $target ? esc_html__( 'Update', 'ohmylms' ) : esc_html__( 'Start plan', 'ohmylms' ); ?></button>
				<?php if ( $target ) { ?>
					<button type="submit" name="clear" value="1" formnovalidate><?php esc_html_e( 'Remove', 'ohmylms' ); ?></button>
				<?php } ?>
			</form>
		</section>
		<?php
		return ob_get_clean();
	}

	/**
	 * Check whether the practice engine is available.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return Schema::ready() && Engine::practice();
	}

	/** Logged-in visitors may have unclaimed guest answers in this browser. */
	public static function claim_assets() {
		if ( ! self::enabled() || ! is_user_logged_in() ) {
			return; }
		wp_enqueue_script( 'ohmylms-inline-check', plugins_url( 'assets/js/inline-check.js', OHMYLMS_FILE ), array(), OHMYLMS_VERSION, true );
		wp_localize_script( 'ohmylms-inline-check', 'ohmylmsInlineCheck', Inline::client_config() );
	}

	/**
	 * Render the skill dashboard.
	 *
	 * @return string Escaped HTML.
	 */
	public static function skills() {
		if ( ! self::enabled() || ! is_user_logged_in() ) {
			return ''; }
		Evidence::process( 50 );
		wp_enqueue_style( 'ohmylms-practice', plugins_url( 'assets/css/practice.css', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/css/practice.css' ) );
		$student = get_current_user_id();
		$skills  = Evidence::summary( $student );
		// One subject for the whole page; skill_map_subject is the older name of the same choice.
		$subject = '';
		foreach ( array( 'skills_subject', 'skill_map_subject' ) as $subject_key ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only choice of which subject to show.
			if ( '' === $subject && isset( $_GET[ $subject_key ] ) ) {
				$subject = sanitize_text_field( wp_unslash( $_GET[ $subject_key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only choice of which subject to show.
			}
		}
		$guidance  = \OhMyLMS\Performance\Snapshot::guidance( $student, 5, $subject );
		$skill_map = \OhMyLMS\Curriculum\SkillMap::for_student( $student, $subject );
		ob_start();
		?>
		<section class="ohmylms-skill-progress" aria-labelledby="ohmylms-skill-progress-title">
			<h2 id="ohmylms-skill-progress-title"><?php esc_html_e( 'My skills', 'ohmylms' ); ?></h2>
			<?php echo self::exam_card( $guidance, $student ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped exam markup. ?>
			<?php echo self::guidance( $guidance ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped guidance markup. ?>
			<?php echo self::skill_map( $skill_map ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped skill map markup. ?>
			<?php echo \OhMyLMS\Placement\PlacementPage::summary( $student ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped placement markup. ?>
			<?php if ( ! $skills ) { ?>
				<p><?php esc_html_e( 'Answer quiz questions, lesson checks or practice to see your skills here.', 'ohmylms' ); ?></p>
			<?php } else { ?>
				<h3><?php esc_html_e( 'All my skills', 'ohmylms' ); ?></h3>
				<div class="ohmylms-skill-table-wrap">
				<table class="ohmylms-skill-table">
					<thead><tr><th><?php esc_html_e( 'Skill', 'ohmylms' ); ?></th><th><?php esc_html_e( 'Level', 'ohmylms' ); ?></th>
					<?php
					if ( \OhMyLMS\Skills\Score::enabled() ) {
						?>
						<th><?php esc_html_e( 'Mastery', 'ohmylms' ); ?></th><?php } ?><th><?php esc_html_e( 'Evidence', 'ohmylms' ); ?></th><th></th></tr></thead>
					<tbody>
					<?php foreach ( $skills as $skill ) { ?>
						<tr>
							<td><?php echo esc_html( $skill['name'] ); ?></td>
							<td>
								<span class="ohmylms-skill-level ohmylms-skill-<?php echo esc_attr( $skill['level'] ); ?>"><?php echo esc_html( $skill['level_label'] ); ?></span>
								<?php
								if ( $skill['review_due'] ) {
									?>
									<span class="ohmylms-skill-review"><?php esc_html_e( 'Review due', 'ohmylms' ); ?></span><?php } ?>
							</td>
							<?php if ( \OhMyLMS\Skills\Score::enabled() ) { ?>
								<td><?php echo isset( $skill['mastery'] ) ? esc_html( (int) round( $skill['mastery']['score'] ) . '/100' . ( $skill['mastery']['medal'] ? ' · ' . ucfirst( $skill['mastery']['medal'] ) : '' ) ) : '—'; ?></td>
							<?php } ?>
							<td>
							<?php
							echo esc_html(
								sprintf(
								/* translators: 1: independent correct answers, 2: question families */
									__( '%1$d correct on your own · %2$d kinds of question', 'ohmylms' ),
									$skill['independent_correct'],
									$skill['families']
								)
							);
							?>
								</td>
							<td><a class="ohmylms-button" href="<?php echo esc_url( add_query_arg( 'ohmylms_practice', $skill['id'], home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Practice', 'ohmylms' ); ?></a></td>
						</tr>
					<?php } ?>
					</tbody>
				</table>
				</div>
			<?php } ?>
			<?php if ( $skills && \OhMyLMS\Performance\Insights::available() ) { ?>
				<div class="ohmylms-ai-note" data-ohmylms-note data-url="<?php echo esc_url( rest_url( 'ohmylms/v1/reports/skills/students/' . $student . '/insight' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
					<h3><?php esc_html_e( 'My study note', 'ohmylms' ); ?></h3>
					<p><?php esc_html_e( 'A short note written by AI about your skills. It explains your levels; it never changes them.', 'ohmylms' ); ?></p>
					<p class="ohmylms-ai-note-text" role="status" aria-live="polite"></p>
					<button type="button" class="ohmylms-button"><?php esc_html_e( 'Write my study note', 'ohmylms' ); ?></button>
				</div>
				<?php wp_enqueue_script( 'ohmylms-performance-note', plugins_url( 'assets/js/performance-note.js', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/js/performance-note.js' ), true ); ?>
			<?php } ?>
		</section>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the learner's skill map: a subject's shared skills by domain and grade, each marked with
	 * the learner's level.
	 *
	 * @param array $data From SkillMap::for_student().
	 * @return string Escaped HTML, empty when the learner has not started any mapped subject.
	 */
	private static function skill_map( array $data ) {
		$map = $data['map'];
		if ( ! $map || ! $map['domains'] || ! $map['grades'] ) {
			return '';
		}
		$levels = (array) $map['student']['levels'];
		$cells  = array();
		foreach ( $map['skills'] as $skill ) {
			if ( $skill['domain'] && $skill['grade_from'] ) {
				$cells[ $skill['domain'] ][ $skill['grade_from'] ][] = $skill;
			}
		}
		$areas = array_column( $map['student']['areas'], null, 'domain' );
		ob_start();
		?>
		<section class="ohmylms-skill-map" aria-labelledby="ohmylms-skill-map-title">
			<h3 id="ohmylms-skill-map-title"><?php esc_html_e( 'My skill map', 'ohmylms' ); ?></h3>
			<?php if ( count( $data['subjects'] ) > 1 ) { ?>
				<nav class="ohmylms-skill-map-subjects" aria-label="<?php esc_attr_e( 'Subjects', 'ohmylms' ); ?>">
					<?php foreach ( $data['subjects'] as $subject ) { ?>
						<a href="<?php echo esc_url( add_query_arg( 'skills_subject', rawurlencode( $subject['subject'] ) ) . '#ohmylms-skill-map-title' ); ?>" <?php echo $subject['subject'] === $map['subject'] ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $subject['subject'] ); ?></a>
					<?php } ?>
				</nav>
			<?php } ?>
			<p>
				<?php
				/* translators: %s: subject name. */
				echo esc_html( sprintf( __( 'Every %s skill by area and the grade it is usually learned in, marked with how far you have got.', 'ohmylms' ), $map['subject'] ) );
				?>
			</p>
			<ul class="ohmylms-skill-map-legend">
				<?php foreach ( \OhMyLMS\Performance\Levels::SCALE as $level ) { ?>
					<li><span class="ohmylms-map-chip ohmylms-map-<?php echo esc_attr( $level ); ?>" aria-hidden="true"></span><?php echo esc_html( \OhMyLMS\Performance\Levels::label( $level ) ); ?></li>
				<?php } ?>
			</ul>
			<div class="ohmylms-skill-map-scroll" tabindex="0" role="region" aria-labelledby="ohmylms-skill-map-title">
				<table class="ohmylms-skill-map-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Area', 'ohmylms' ); ?></th>
							<?php foreach ( $map['grades'] as $grade ) { ?>
								<th scope="col"><?php echo esc_html( $grade['label'] ); ?></th>
							<?php } ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $map['domains'] as $domain ) { ?>
							<tr>
								<th scope="row">
									<?php echo esc_html( $domain['label'] ); ?>
									<?php if ( isset( $areas[ $domain['id'] ] ) ) { ?>
										<span class="ohmylms-skill-map-strength"><?php echo esc_html( $areas[ $domain['id'] ]['strength'] . '/100' ); ?></span>
									<?php } ?>
								</th>
								<?php foreach ( $map['grades'] as $grade ) { ?>
									<td>
										<?php
										foreach ( $cells[ $domain['id'] ][ $grade['value'] ] ?? array() as $skill ) {
											$level = $levels[ $skill['id'] ] ?? 'not-started';
											?>
											<span class="ohmylms-map-chip ohmylms-map-<?php echo esc_attr( $level ); ?>"><?php echo esc_html( $skill['name'] ); ?><span class="screen-reader-text"> (<?php echo esc_html( \OhMyLMS\Performance\Levels::label( $level ) ); ?>)</span></span>
										<?php } ?>
									</td>
								<?php } ?>
							</tr>
						<?php } ?>
					</tbody>
				</table>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the learner's guidance: next steps, strength by area, readiness and covered reviews.
	 *
	 * @param array $guidance From Snapshot::guidance().
	 * @return string Escaped HTML, empty when there is nothing to suggest yet.
	 */
	private static function guidance( array $guidance ) {
		$nothing = ! $guidance['next_steps'] && ! $guidance['areas'] && ! $guidance['readiness'] && ! $guidance['reviews']['covered'];
		if ( $nothing && count( $guidance['subjects'] ) < 2 ) {
			return '';
		}
		$practice = static function ( $id ) {
			return add_query_arg( 'ohmylms_practice', (int) $id, home_url( '/' ) );
		};
		$date     = static function ( $value ) {
			return wp_date( get_option( 'date_format' ), strtotime( $value . ' UTC' ) );
		};
		$names    = static function ( array $skills ) {
			return wp_sprintf( '%l', wp_list_pluck( $skills, 'name' ) );
		};
		ob_start();
		if ( count( $guidance['subjects'] ) > 1 ) {
			?>
			<nav class="ohmylms-skill-map-subjects ohmylms-guidance-subjects" aria-label="<?php esc_attr_e( 'Show guidance for', 'ohmylms' ); ?>">
				<a href="<?php echo esc_url( remove_query_arg( array( 'skills_subject', 'skill_map_subject' ) ) ); ?>" <?php echo '' === $guidance['subject'] ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'All subjects', 'ohmylms' ); ?></a>
				<?php foreach ( $guidance['subjects'] as $name ) { ?>
					<a href="<?php echo esc_url( add_query_arg( 'skills_subject', rawurlencode( $name ), remove_query_arg( 'skill_map_subject' ) ) ); ?>" <?php echo $name === $guidance['subject'] ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $name ); ?></a>
				<?php } ?>
			</nav>
			<?php
		}
		if ( $nothing ) {
			echo '<p>' . esc_html__( 'Nothing to suggest in this subject yet.', 'ohmylms' ) . '</p>';
			return ob_get_clean();
		}
		?>
		<div class="ohmylms-guidance">
			<?php if ( $guidance['next_steps'] ) { ?>
				<section class="ohmylms-guidance-card ohmylms-guidance-next" aria-labelledby="ohmylms-guidance-next-title">
					<h3 id="ohmylms-guidance-next-title"><?php esc_html_e( 'Suggested next steps', 'ohmylms' ); ?></h3>
					<?php if ( ! empty( $guidance['session_minutes'] ) ) { ?>
						<p class="ohmylms-guidance-session">
							<?php
							/* translators: %d: minutes. */
							echo esc_html( sprintf( __( 'One sitting of about %d minutes.', 'ohmylms' ), (int) $guidance['session_minutes'] ) );
							?>
						</p>
					<?php } ?>
					<ul class="ohmylms-recommendations">
						<?php foreach ( $guidance['next_steps'] as $item ) { ?>
							<li class="ohmylms-recommendation ohmylms-recommendation-<?php echo esc_attr( $item['type'] ); ?>">
								<span class="ohmylms-recommendation-kind">
									<?php
									$kinds = array(
										'help'     => __( 'Needs help', 'ohmylms' ),
										'review'   => __( 'Review', 'ohmylms' ),
										'continue' => __( 'Keep going', 'ohmylms' ),
										'ready'    => __( 'Ready to learn', 'ohmylms' ),
									);
									echo esc_html( $kinds[ $item['type'] ] ?? '' );
									?>
								</span>
								<strong><?php echo esc_html( $item['skill']['name'] ); ?></strong>
								<p><?php echo esc_html( $item['message'] ); ?></p>
								<?php if ( 'review' === $item['type'] && $item['covered_by'] ) { ?>
									<p class="ohmylms-recommendation-note">
										<?php
										/* translators: 1: skill names, 2: percentage of a review. */
										echo esc_html( sprintf( __( 'Your work on %1$s already counted for %2$d%% of this review.', 'ohmylms' ), $names( $item['covered_by'] ), (int) round( $item['credit'] * 100 ) ) );
										?>
									</p>
								<?php } elseif ( 'ready' === $item['type'] && $item['prerequisites'] ) { ?>
									<p class="ohmylms-recommendation-note">
										<?php
										/* translators: %s: skill names. */
										echo esc_html( sprintf( __( 'Builds on %s.', 'ohmylms' ), $names( $item['prerequisites'] ) ) );
										?>
									</p>
								<?php } ?>
								<?php if ( ! empty( $item['also_reviews'] ) ) { ?>
									<p class="ohmylms-recommendation-note">
										<?php
										/* translators: %s: skill names. */
										echo esc_html( sprintf( __( 'Also reviews %s, so no separate review is needed.', 'ohmylms' ), $names( $item['also_reviews'] ) ) );
										?>
									</p>
								<?php } ?>
								<span class="ohmylms-recommendation-links">
									<?php if ( $item['practice_id'] ) { ?>
										<a class="ohmylms-button" href="<?php echo esc_url( $practice( $item['practice_id'] ) ); ?>"><?php esc_html_e( 'Practice', 'ohmylms' ); ?></a>
									<?php } ?>
									<?php foreach ( $item['lessons'] as $lesson ) { ?>
										<a href="<?php echo esc_url( $lesson['url'] ); ?>"><?php echo esc_html( $lesson['title'] ); ?></a>
									<?php } ?>
									<?php
									foreach ( 'help' === $item['type'] ? $item['prerequisites'] : array() as $prerequisite ) {
										if ( empty( $prerequisite['practice_id'] ) ) {
											continue;
										}
										?>
										<a href="<?php echo esc_url( $practice( $prerequisite['practice_id'] ) ); ?>"><?php /* translators: %s: prerequisite skill name. */ echo esc_html( sprintf( __( 'Practice %s', 'ohmylms' ), $prerequisite['name'] ) ); ?></a>
									<?php } ?>
								</span>
							</li>
						<?php } ?>
					</ul>
				</section>
			<?php } ?>
			<?php if ( $guidance['areas'] ) { ?>
				<section class="ohmylms-guidance-card" aria-labelledby="ohmylms-guidance-areas-title">
					<h3 id="ohmylms-guidance-areas-title"><?php esc_html_e( 'Strength by area', 'ohmylms' ); ?></h3>
					<ul class="ohmylms-guidance-bars">
						<?php foreach ( $guidance['areas'] as $area ) { ?>
							<li class="ohmylms-area-<?php echo esc_attr( $area['band'] ); ?>">
								<span class="ohmylms-guidance-bar-label">
									<strong><?php echo esc_html( $area['label'] ); ?></strong>
									<span><?php echo esc_html( $area['strength'] . '/100' ); ?></span>
								</span>
								<span class="ohmylms-guidance-meter" aria-hidden="true"><span style="width:<?php echo esc_attr( (string) (int) $area['strength'] ); ?>%"></span></span>
								<span class="ohmylms-guidance-bar-detail">
									<?php
									$detail = sprintf(
										/* translators: %d: number of skills. */
										_n( '%d skill started', '%d skills started', $area['started'], 'ohmylms' ),
										$area['started']
									);
									if ( $area['grade_label'] ) {
										/* translators: 1: skills started, 2: grade name such as "Grade 8". */
										$detail = sprintf( __( '%1$s · proficient up to %2$s', 'ohmylms' ), $detail, $area['grade_label'] );
									}
									echo esc_html( $detail );
									?>
								</span>
							</li>
						<?php } ?>
					</ul>
				</section>
			<?php } ?>
			<?php if ( $guidance['readiness'] ) { ?>
				<section class="ohmylms-guidance-card" aria-labelledby="ohmylms-guidance-ready-title">
					<h3 id="ohmylms-guidance-ready-title"><?php esc_html_e( 'Readiness', 'ohmylms' ); ?></h3>
					<ul class="ohmylms-guidance-bars">
						<?php foreach ( $guidance['readiness'] as $readiness ) { ?>
							<li>
								<span class="ohmylms-guidance-bar-label">
									<strong><?php echo esc_html( $readiness['syllabus']['name'] ); ?></strong>
									<span><?php /* translators: %d: percentage. */ echo esc_html( sprintf( __( '%d%% ready', 'ohmylms' ), $readiness['percent'] ) ); ?></span>
								</span>
								<span class="ohmylms-guidance-meter" aria-hidden="true"><span style="width:<?php echo esc_attr( (string) (int) $readiness['percent'] ); ?>%"></span></span>
								<span class="ohmylms-guidance-bar-detail">
									<?php
									/* translators: 1: objectives ready, 2: all objectives, 3: objectives in progress. */
									echo esc_html( sprintf( __( '%1$d of %2$d objectives ready, %3$d in progress', 'ohmylms' ), $readiness['ready'], $readiness['total'], $readiness['learning'] ) );
									if ( $readiness['gaps'] ) {
										echo '<br>';
										/* translators: %s: objective names. */
										echo esc_html( sprintf( __( 'Next to learn: %s', 'ohmylms' ), $names( $readiness['gaps'] ) ) );
									}
									?>
								</span>
							</li>
						<?php } ?>
					</ul>
				</section>
			<?php } ?>
			<?php if ( $guidance['reviews']['covered'] ) { ?>
				<section class="ohmylms-guidance-card" aria-labelledby="ohmylms-guidance-covered-title">
					<h3 id="ohmylms-guidance-covered-title"><?php esc_html_e( 'Reviews covered by your other work', 'ohmylms' ); ?></h3>
					<p><?php esc_html_e( 'Harder skills use these ones, so practising them kept these fresh.', 'ohmylms' ); ?></p>
					<ul class="ohmylms-guidance-covered">
						<?php foreach ( $guidance['reviews']['covered'] as $review ) { ?>
							<li>
								<strong><?php echo esc_html( $review['skill']['name'] ); ?></strong>
								<?php
								/* translators: 1: skill names, 2: date of the next review. */
								echo esc_html( sprintf( __( 'Kept fresh by %1$s. Next review: %2$s.', 'ohmylms' ), $names( $review['covered_by'] ), $date( $review['next_due_at'] ) ) );
								?>
							</li>
						<?php } ?>
					</ul>
				</section>
			<?php } ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * The practice runner's script, styles and words, shared by practice and placement tests.
	 *
	 * @param array $strings Words that replace the practice defaults.
	 */
	public static function runner_assets( array $strings = array() ) {
		\OhMyLMS\Design\Ui::enqueue();
		// The inline-check script provides the shared guest credential and local history.
		if ( ! wp_script_is( 'ohmylms-inline-check', 'enqueued' ) ) {
			wp_enqueue_script( 'ohmylms-inline-check', plugins_url( 'assets/js/inline-check.js', OHMYLMS_FILE ), array(), OHMYLMS_VERSION, true );
			wp_localize_script( 'ohmylms-inline-check', 'ohmylmsInlineCheck', Inline::client_config() );
		}
		\OhMyLMS\Assessment\Interactive::enqueue();
		wp_enqueue_script( 'ohmylms-practice', plugins_url( 'assets/js/practice.js', OHMYLMS_FILE ), array( 'ohmylms-inline-check', 'ohmylms-interactive-visual' ), (string) filemtime( OHMYLMS_DIR . '/assets/js/practice.js' ), true );
		wp_enqueue_style( 'ohmylms-quiz-a11y', plugins_url( 'assets/css/quiz-a11y.css', OHMYLMS_FILE ), array(), OHMYLMS_VERSION );
		wp_enqueue_style( 'ohmylms-practice', plugins_url( 'assets/css/practice.css', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/css/practice.css' ) );
		wp_localize_script(
			'ohmylms-practice',
			'ohmylmsPractice',
			Inline::client_config() + array(
				'studentId'     => get_current_user_id(),
				'buttonClasses' => array(
					'primary'   => \OhMyLMS\Design\Ui::button_class( array( 'variant' => 'primary' ) ),
					'secondary' => \OhMyLMS\Design\Ui::button_class(),
					'tertiary'  => \OhMyLMS\Design\Ui::button_class(
						array(
							'variant' => 'tertiary',
							'size'    => 'sm',
						)
					),
				),
				'practiceI18n'  => array_merge(
					array(
						'start'             => __( 'Start practice', 'ohmylms' ),
						'resume'            => __( 'Resume practice', 'ohmylms' ),
						'check'             => __( 'Check answer', 'ohmylms' ),
						'next'              => __( 'Next question', 'ohmylms' ),
						'hint'              => __( 'Show a hint', 'ohmylms' ),
						'skip'              => __( 'Skip', 'ohmylms' ),
						'tryAgain'          => __( 'Try again', 'ohmylms' ),
						'notQuite'          => __( 'Not quite! Give it another try!', 'ohmylms' ),
						'levelsTitle'       => __( 'Skill level', 'ohmylms' ),
						'levelUp'           => __( 'Leveled up', 'ohmylms' ),
						'levelDown'         => __( 'Leveled down', 'ohmylms' ),
						'levelSame'         => __( 'Stayed at', 'ohmylms' ),
						'level_not-started' => __( 'Not started', 'ohmylms' ),
						'level_attempted'   => __( 'Attempted', 'ohmylms' ),
						'level_familiar'    => __( 'Familiar', 'ohmylms' ),
						'level_proficient'  => __( 'Proficient', 'ohmylms' ),
						'level_mastered'    => __( 'Mastered', 'ohmylms' ),
						'restart'           => __( 'Start over', 'ohmylms' ),
						'restartAsk'        => __( 'Start over with a new set of questions? Your answers so far in this session stay recorded.', 'ohmylms' ),
						'greatWork'         => __( 'Great work! You got it. Onward!', 'ohmylms' ),
						'niceWork'          => __( 'Nice work! Great that you stuck with it. Onward!', 'ohmylms' ),
						/* translators: 1: hint number, 2: number of hints */
						'hintStep'          => __( 'Hint %1$d of %2$d', 'ohmylms' ),
						/* translators: 1: hints shown, 2: number of hints */
						'anotherHint'       => __( 'Get another hint (%1$d/%2$d)', 'ohmylms' ),
						/* translators: %d: number of problems in the session */
						'problems'          => __( 'Do %d problems', 'ohmylms' ),
						/* translators: 1: current problem, 2: number of problems */
						'problemOf'         => __( '%1$d of %2$d', 'ohmylms' ),
						'dotRight'          => __( 'Right first time', 'ohmylms' ),
						'dotHelp'           => __( 'Right with help', 'ohmylms' ),
						'dotWrong'          => __( 'Not right', 'ohmylms' ),
						'dotCurrent'        => __( 'Current question', 'ohmylms' ),
						'dotLeft'           => __( 'Not answered yet', 'ohmylms' ),
						/* translators: 1: answered, 2: total */
						'progress'          => __( 'Question %1$d · session limit %2$d', 'ohmylms' ),
						'checked'           => __( 'Answers checked', 'ohmylms' ),
						/* translators: 1: correct, 2: answered */
						'done'              => __( 'Practice complete: %1$d of %2$d correct.', 'ohmylms' ),
						'empty'             => __( 'There are no practice questions for this skill yet.', 'ohmylms' ),
						'again'             => __( 'Practice again', 'ohmylms' ),
						'continue'          => __( 'Continue', 'ohmylms' ),
						'tutorHint'         => __( 'Ask the tutor for a hint', 'ohmylms' ),
						'explain'           => __( 'Explain my answer', 'ohmylms' ),
						'thinking'          => __( 'The tutor is thinking…', 'ohmylms' ),
						'tutor'             => __( 'Tutor', 'ohmylms' ),
						'solution'          => __( 'Worked solution', 'ohmylms' ),
						'replay'            => __( 'Let\'s try that one again.', 'ohmylms' ),
						'recognize'         => __( 'Recognise', 'ohmylms' ),
						'guided'            => __( 'Build', 'ohmylms' ),
						'produce'           => __( 'Solve', 'ohmylms' ),
						'sound'             => __( 'Sound', 'ohmylms' ),
						/* translators: 1: right first time, 2: planned questions */
						'lessonDone'        => __( 'Lesson complete: %1$d of %2$d right first time.', 'ohmylms' ),
						'missedTitle'       => __( 'Questions to review', 'ohmylms' ),
						'fixed'             => __( 'put right on the second try', 'ohmylms' ),
						'notFixed'          => __( 'still tricky, worth another look', 'ohmylms' ),
						'perfect'           => __( 'No mistakes. Well done!', 'ohmylms' ),
						'rewardTitle'       => __( 'Your rewards', 'ohmylms' ),
						'xp_lesson'         => __( 'Lesson', 'ohmylms' ),
						'xp_perfect'        => __( 'No mistakes', 'ohmylms' ),
						'xp_challenge'      => __( 'Challenge zone', 'ohmylms' ),
						/* translators: 1: XP earned today, 2: the daily goal */
						'xpToday'           => __( '%1$d of %2$d XP today', 'ohmylms' ),
						'goalMet'           => __( 'Daily goal reached!', 'ohmylms' ),
						'xpCapped'          => __( 'You have reached today\'s XP limit. Keep practising; it counts toward your skills.', 'ohmylms' ),
						'mastery'           => __( 'Mastery', 'ohmylms' ),
						/* translators: 1: score before, 2: score after */
						'masteryChange'     => __( '%1$d → %2$d out of 100', 'ohmylms' ),
						'medal_80'          => __( 'Bronze medal: skill at 80', 'ohmylms' ),
						'medal_90'          => __( 'Silver medal: skill at 90', 'ohmylms' ),
						'medal_100'         => __( 'Gold medal: skill mastered!', 'ohmylms' ),
						'unlocked'          => __( 'Next skill unlocked', 'ohmylms' ),
						/* translators: %d: days in a row */
						'streakDays'        => __( '%d-day streak', 'ohmylms' ),
					),
					$strings
				),
			)
		);
	}

	/**
	 * Render an entry without creating an attempt or disclosing question data.
	 *
	 * @param array $attributes Shortcode attributes.
	 * @return string Escaped practice markup.
	 */
	public static function practice( $attributes ) {
		if ( ! self::enabled() ) {
			return ''; }
		$attributes = shortcode_atts(
			array(
				'skill'  => 0,
				'items'  => 10,
				'course' => 0,
				// standard, or lesson for fast feedback with staged questions and replay of misses.
				'style'  => get_option( 'ohmylms_practice_style', 'standard' ),
			),
			(array) $attributes,
			'ohmylms_practice'
		);
		$term       = get_term( (int) $attributes['skill'], \OhMyLMS\Skills\Taxonomy::NAME );
		if ( ! $term || is_wp_error( $term ) ) {
			return ''; }
		$guidance      = (int) $attributes['course'] ? \OhMyLMS\Student\PracticeGuidance::get( (int) $attributes['course'], '', (int) $term->term_id ) : null;
		$guidance_html = is_array( $guidance ) ? \OhMyLMS\Student\PracticeGuidance::render( $guidance ) : '';
		self::runner_assets();
		$practice_available = Presentation::available( $term->term_id, (int) $attributes['course'], (int) $attributes['items'] );
		ob_start();
		?>
		<?php \OhMyLMS\Family\Provenance::render( (int) $attributes['course'], $term->term_id, 'practice' ); ?>
		<section class="ohmylms-practice" data-style="<?php echo esc_attr( 'lesson' === $attributes['style'] ? 'lesson' : 'standard' ); ?>" data-skill="<?php echo esc_attr( $term->term_id ); ?>" data-course="<?php echo esc_attr( (int) $attributes['course'] ); ?>" data-items="<?php echo esc_attr( (int) $attributes['items'] ); ?>">
			<h2><?php /* translators: %s: skill name. */ echo esc_html( sprintf( __( 'Practice: %s', 'ohmylms' ), $term->name ) ); ?></h2>
			<p class="ohmylms-practice-entry"><?php /* translators: %d: maximum questions in a new session from the current authorized pool. */ echo esc_html( sprintf( _n( 'Up to %d question in a new session.', 'Up to %d questions in a new session.', $practice_available, 'ohmylms' ), $practice_available ) ); ?> <?php esc_html_e( 'Check each answer for feedback. You can leave and resume on this device; unanswered work is not submitted.', 'ohmylms' ); ?>
			<?php
			if ( 'lesson' === $attributes['style'] ) :
				?>
				<?php esc_html_e( 'Missed questions may return for review.', 'ohmylms' ); ?><?php endif; ?></p>
			<noscript><p><?php esc_html_e( 'Enable JavaScript to start or resume practice.', 'ohmylms' ); ?></p></noscript>
			<?php
			if ( ! is_user_logged_in() ) {
				?>
				<p class="ohmylms-practice-guest"><?php esc_html_e( 'You are practising as a guest. Your answers are kept on this device until you log in.', 'ohmylms' ); ?></p><?php } ?>
			<div class="ohmylms-practice-stage" aria-live="polite"></div>
		</section>
		<?php echo $guidance_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Context-escaped, owned guidance template. ?>
		<?php
		return ob_get_clean();
	}

	/** Minimal standalone practice page for ?ohmylms_practice=SKILL_ID links. */
	public static function practice_page() {
		$skill = (int) get_query_var( 'ohmylms_practice' );
		if ( ! $skill || ! self::enabled() ) {
			return; }
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Sanitized, read-only view choices; practice session writes require API authorization.
		$course = isset( $_GET['learning_course'] ) ? absint( $_GET['learning_course'] ) : 0;
		$args   = array(
			'skill'  => $skill,
			'course' => $course,
		);
		if ( isset( $_GET['style'] ) ) {
			$args['style'] = sanitize_key( wp_unslash( $_GET['style'] ) ); }
		if ( isset( $_GET['items'] ) ) {
			$args['items'] = absint( $_GET['items'] ); }
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$html = self::practice( $args );
		if ( '' === $html ) {
			return; }
		ob_start();
		$focused = \OhMyLMS\Student\PlayerContext::open_practice( $course, $skill );
		$shell   = ob_get_clean();
		status_header( 200 );
		if ( $focused ) {
			ohmylms_get_template(
				'student-hub/activity-page.php',
				array(
					'activity_shell' => $shell,
					'activity_html'  => $html,
				)
			);
			exit;
		}
		get_header();
		echo '<main class="ohmylms-container" style="max-width:780px;margin:40px auto;padding:0 16px">' . $html . '</main>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped practice renderer above.
		get_footer();
		exit;
	}
}
