<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * The learner's bundle dashboard: followed bundles with per-course and per-syllabus progress,
 * skill strengths and gaps, next practice and combined skill views, plus suggested bundles to
 * add. Rendered on the server (all output escaped); an Interactivity API module (ohmylms/bundles) only calls the
 * follow and unfollow endpoints and swaps in the section they return.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Bundles;

use OhMyLMS\Curriculum\Schema;
use OhMyLMS\Skills\Evidence;

defined( 'ABSPATH' ) || exit;

/**
 * The learner's bundle dashboard: followed bundles with per-course and per-syllabus progress,
 * skill strengths and gaps, next practice and combined skill views, plus suggested bundles to
 * add. Rendered on the server (all output escaped); an Interactivity API module (ohmylms/bundles) only calls the
 * follow and unfollow endpoints and swaps in the section they return.
 */
final class Frontend {
	/**
	 * Printed.
	 *
	 * @var mixed
	 */
	private static $printed = false;

	/**
	 * Init for Frontend.
	 */
	public static function init() {
		add_shortcode( 'ohmylms_bundles', array( __CLASS__, 'shortcode' ) );
		// The [ohmylms_dashboard] shortcode fires the first hook; the profile dashboard template fires the second.
		// Either may print the section, but never twice on one page.
		foreach ( array( 'ohmylms_lms_student_dashboard_sections', 'ohmylms_lms_student_profile_after_dashboard_content' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'print_once' ), 5 );
		}
	}

	/**
	 * Print once for Frontend.
	 */
	public static function print_once() {
		if ( self::$printed ) {
			return; }
		self::$printed = true;
		echo self::section( get_current_user_id() ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside section().
	}

	/**
	 * Shortcode for Frontend.
	 */
	public static function shortcode() {
		return self::section( get_current_user_id() );
	}

	/**
	 * Enqueue for Frontend.
	 */
	public static function enqueue() {
		wp_enqueue_style( 'ohmylms-learning-bundles', plugins_url( 'assets/css/learning-bundles.css', OHMYLMS_FILE ), array(), OHMYLMS_VERSION );
		\OhMyLMS\Extensions\Interactivity::enqueue( 'ohmylms/bundles' );
		wp_interactivity_state(
			'ohmylms/bundles',
			array(
				'root'     => esc_url_raw( rest_url( 'ohmylms/v1/' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'messages' => array(
					'added'   => __( 'Added to your dashboard.', 'ohmylms' ),
					'removed' => __( 'Removed from your dashboard.', 'ohmylms' ),
					'error'   => __( 'Could not update your dashboard. Please try again.', 'ohmylms' ),
				),
			)
		);
	}

	/** The whole section, or an empty string for guests and sites without the storage.
	 *
	 * @param mixed $user_id User identifier.
	 */
	public static function section( $user_id ) {
		$user_id = (int) $user_id;
		if ( ! $user_id || ! Schema::ready() ) {
			return ''; }
		$followed  = Follows::followed( $user_id );
		$suggested = Follows::suggested( $user_id );
		if ( ! $followed && ! $suggested ) {
			return ''; }
		if ( class_exists( Evidence::class ) && \OhMyLMS\Assessment\Schema::ready() ) {
			Evidence::process( 50 ); }
		self::enqueue();
		ob_start();
		?>
		<div class="ohmylms-bundles-root" data-wp-interactive="ohmylms/bundles" data-wp-on--click="actions.click">
		<p class="ohmylms-bundles-status" role="status" aria-live="polite" data-wp-text="state.status"></p>
		<section class="ohmylms-bundles" id="ohmylms-bundles" aria-labelledby="ohmylms-bundles-title" tabindex="-1">
			<h2 id="ohmylms-bundles-title" class="my-courses-title"><?php esc_html_e( 'My course bundles', 'ohmylms' ); ?></h2>
			<p class="ohmylms-bundles-note"><?php esc_html_e( 'Adding a bundle puts it on this dashboard. It does not enrol you in any course or change what you can open. Course progress and skill levels are shown separately: a skill level never completes a course.', 'ohmylms' ); ?></p>
			<?php if ( ! $followed ) { ?>
				<p><?php esc_html_e( 'You have not added any course bundles yet.', 'ohmylms' ); ?></p>
			<?php } ?>
			<?php
			foreach ( $followed as $row ) {
				self::bundle( Progress::for_bundle( $row, $user_id ) ); }
			?>
			<?php if ( $suggested ) { ?>
				<h3 class="ohmylms-bundles-suggested-title my-courses-title"><?php esc_html_e( 'Suggested bundles', 'ohmylms' ); ?></h3>
				<ul class="ohmylms-bundles-suggested">
					<?php foreach ( $suggested as $row ) { ?>
						<li>
							<div>
								<strong><?php echo esc_html( $row['title'] ); ?></strong>
								<?php
								if ( ! empty( $row['description'] ) ) {
									?>
									<p><?php echo esc_html( $row['description'] ); ?></p><?php } ?>
								<?php
								if ( ! empty( $row['enrolled_overlap'] ) ) {
									?>
									<p class="ohmylms-bundles-reason"><?php echo esc_html( sprintf( /* translators: %d: number. */
									_n( 'Includes %d course you are enrolled in.', 'Includes %d courses you are enrolled in.', (int) $row['enrolled_overlap'], 'ohmylms' ), (int) $row['enrolled_overlap'] ) ); ?></p><?php } ?>
							</div>
							<button type="button" class="ohmylms-button" data-bundle-follow="<?php echo esc_attr( (int) $row['id'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: text value. */
							__( 'Add %s to my dashboard', 'ohmylms' ), $row['title'] ) ); ?>"><?php esc_html_e( 'Add to my dashboard', 'ohmylms' ); ?></button>
						</li>
					<?php } ?>
				</ul>
			<?php } ?>
		</section>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Summary line for Frontend.
	 *
	 * @param array $summary Summary.
	 */
	private static function summary_line( array $summary ) {
		if ( ! $summary['total'] ) {
			return ''; }
		return sprintf(
			/* translators: 1: skills, 2: strengths, 3: needs practice, 4: in progress, 5: not assessed */
			_n( '%1$d skill: %2$d strengths · %3$d need practice · %4$d in progress · %5$d not assessed', '%1$d skills: %2$d strengths · %3$d need practice · %4$d in progress · %5$d not assessed', $summary['total'], 'ohmylms' ),
			$summary['total'],
			$summary['strengths'],
			$summary['gaps'],
			$summary['developing'],
			$summary['not_assessed']
		);
	}

	/**
	 * Level badge for Frontend.
	 *
	 * @param mixed $level Level.
	 * @param mixed $label Label.
	 */
	private static function level_badge( $level, $label ) {
		return '<span class="ohmylms-level ohmylms-level-' . esc_attr( $level ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Progress meter for Frontend.
	 *
	 * @param mixed $label Label.
	 * @param mixed $met Met.
	 * @param mixed $total Total.
	 */
	private static function progress_meter( $label, $met, $total ) {
		?>
		<div class="ohmylms-bundle-measure">
			<span class="ohmylms-bundle-measure-label"><?php echo esc_html( $label ); ?></span>
			<progress max="<?php echo esc_attr( (int) $total ); ?>" value="<?php echo esc_attr( (int) $met ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %1$s: text value; %2$d: number; %3$d: number. */
			__( '%1$s: %2$d of %3$d', 'ohmylms' ), $label, $met, $total ) ); ?>"></progress>
			<span><?php echo esc_html( sprintf( /* translators: %1$d: number; %2$d: number. */
			__( '%1$d of %2$d', 'ohmylms' ), $met, $total ) ); ?></span>
		</div>
		<?php
	}

	/**
	 * Course for Frontend.
	 *
	 * @param array $course Course.
	 */
	private static function course( array $course ) {
		?>
		<li class="ohmylms-bundle-course">
			<div class="ohmylms-bundle-member-head">
				<a href="<?php echo esc_url( $course['url'] ); ?>"><?php echo esc_html( $course['title'] ); ?></a>
				<span class="ohmylms-bundle-badge"><?php echo esc_html( $course['enrolled'] ? __( 'Enrolled', 'ohmylms' ) : __( 'Not enrolled', 'ohmylms' ) ); ?></span>
				<span class="ohmylms-bundle-badge"><?php echo esc_html( Progress::mode_label( $course['mode'] ) ); ?></span>
				<?php
				if ( $course['completed'] ) {
					?>
					<span class="ohmylms-bundle-badge ohmylms-bundle-completed"><?php esc_html_e( 'Course completed', 'ohmylms' ); ?></span><?php } ?>
			</div>
			<?php if ( ! $course['enrolled'] ) { ?>
				<p class="ohmylms-bundle-muted"><?php esc_html_e( 'You are not enrolled in this course. Enrollment and access follow the course\'s normal rules.', 'ohmylms' ); ?></p>
			<?php } elseif ( null === $course['progress'] ) { ?>
				<p class="ohmylms-bundle-muted"><?php esc_html_e( 'Progress is not available for this course right now.', 'ohmylms' ); ?></p>
				<?php
			} elseif ( 'program' === $course['progress']['kind'] ) {
				$labels = array(
					'activities'  => __( 'Activities', 'ohmylms' ),
					'outcomes'    => __( 'Skill targets', 'ohmylms' ),
					'assessments' => __( 'Checkpoints', 'ohmylms' ),
				);
				foreach ( $course['progress']['dimensions'] as $key => $dimension ) {
					if ( $dimension['total'] ) {
						self::progress_meter( $labels[ $key ] ?? $key, $dimension['met'], $dimension['total'] ); }
				}
			} elseif ( $course['progress']['total'] ) {
				self::progress_meter( __( 'Activities completed', 'ohmylms' ), $course['progress']['met'], $course['progress']['total'] );
			} else {
				?>
				<p class="ohmylms-bundle-muted"><?php esc_html_e( 'This course has no activities yet.', 'ohmylms' ); ?></p>
			<?php } ?>
			<?php
			if ( $course['note'] ) {
				?>
				<p class="ohmylms-bundle-muted" role="note"><?php echo esc_html( $course['note'] ); ?></p><?php } ?>
		</li>
		<?php
	}

	/**
	 * Skill list for Frontend.
	 *
	 * @param array $skills Skills.
	 */
	private static function skill_list( array $skills ) {
		if ( ! $skills ) {
			return; }
		?>
		<details class="ohmylms-bundle-skills">
			<summary><?php esc_html_e( 'Show skills', 'ohmylms' ); ?></summary>
			<ul>
				<?php foreach ( $skills as $skill ) { ?>
					<li><?php echo esc_html( $skill['name'] ); ?> — <?php echo self::level_badge( $skill['classification'], $skill['level_label'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></li>
				<?php } ?>
			</ul>
		</details>
		<?php
	}

	/**
	 * Bundle for Frontend.
	 *
	 * @param array $view View.
	 */
	private static function bundle( array $view ) {
		$bundle  = $view['bundle'];
		$heading = 'ohmylms-bundle-' . (int) $bundle['id'] . '-title';
		?>
		<article class="ohmylms-bundle" aria-labelledby="<?php echo esc_attr( $heading ); ?>" data-bundle="<?php echo esc_attr( (int) $bundle['id'] ); ?>">
			<header class="ohmylms-bundle-header">
				<h3 id="<?php echo esc_attr( $heading ); ?>"><?php echo esc_html( $bundle['title'] ); ?></h3>
				<button type="button" class="ohmylms-button ohmylms-button-secondary" data-bundle-unfollow="<?php echo esc_attr( (int) $bundle['id'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: text value. */
				__( 'Remove %s from my dashboard', 'ohmylms' ), $bundle['title'] ) ); ?>"><?php esc_html_e( 'Remove from dashboard', 'ohmylms' ); ?></button>
			</header>
			<?php
			if ( '' !== $bundle['description'] ) {
				?>
				<p><?php echo esc_html( $bundle['description'] ); ?></p><?php } ?>
			<?php if ( ! $view['members'] ) { ?>
				<p class="ohmylms-bundle-muted"><?php esc_html_e( 'This bundle has no courses or syllabuses available to you yet.', 'ohmylms' ); ?></p>
			<?php } else { ?>
				<h4><?php esc_html_e( 'Courses and syllabuses', 'ohmylms' ); ?></h4>
				<ul class="ohmylms-bundle-members">
					<?php
					foreach ( $view['members'] as $member ) {
						if ( 'course' === $member['type'] ) {
							self::course( $member );
							continue;
						}
						?>
						<li class="ohmylms-bundle-syllabus">
							<div class="ohmylms-bundle-member-head">
								<strong><?php echo esc_html( $member['name'] ); ?></strong>
								<span class="ohmylms-bundle-badge"><?php echo esc_html( ucfirst( str_replace( array( '-', '_' ), ' ', $member['item_type'] ) ) ); ?></span>
								<?php
								if ( '' !== $member['code'] ) {
									?>
									<span class="ohmylms-bundle-badge"><?php echo esc_html( $member['code'] ); ?></span><?php } ?>
								<?php
								if ( '' !== $member['version'] ) {
									?>
									<span class="ohmylms-bundle-badge"><?php echo esc_html( sprintf( /* translators: %s: text value. */
									__( 'Version %s', 'ohmylms' ), $member['version'] ) ); ?></span><?php } ?>
							</div>
							<?php
							if ( $member['path'] ) {
								?>
								<p class="ohmylms-bundle-muted"><?php echo esc_html( implode( ' › ', $member['path'] ) ); ?></p><?php } ?>
							<?php if ( $member['courses'] ) { ?>
								<ul class="ohmylms-bundle-members ohmylms-bundle-nested">
								<?php
								foreach ( $member['courses'] as $course ) {
									self::course( $course ); }
								?>
									</ul>
							<?php } else { ?>
								<p class="ohmylms-bundle-muted"><?php esc_html_e( 'No courses are linked to this syllabus yet.', 'ohmylms' ); ?></p>
							<?php } ?>
							<?php
							if ( $member['skill_summary']['total'] ) {
								?>
								<p><?php echo esc_html( self::summary_line( $member['skill_summary'] ) ); ?></p><?php } ?>
							<?php self::skill_list( $member['skills'] ); ?>
						</li>
					<?php } ?>
				</ul>
			<?php } ?>
			<?php
			if ( $view['skills'] ) {
				self::skills( $view ); }
			?>
			<?php
			if ( $view['combined'] ) {
				self::combined( $view['combined'] ); }
			?>
		</article>
		<?php
	}

	/**
	 * Skills for Frontend.
	 *
	 * @param array $view View.
	 */
	private static function skills( array $view ) {
		?>
		<h4><?php esc_html_e( 'Skills in this bundle', 'ohmylms' ); ?></h4>
		<p><?php echo esc_html( self::summary_line( $view['skill_summary'] ) ); ?></p>
		<?php
		if ( $view['skills_truncated'] ) {
			?>
			<p class="ohmylms-bundle-muted"><?php esc_html_e( 'Only the first skills are shown.', 'ohmylms' ); ?></p><?php } ?>
		<div class="ohmylms-bundle-table-scroll">
			<table class="ohmylms-bundle-skill-table">
				<thead><tr><th scope="col"><?php esc_html_e( 'Skill', 'ohmylms' ); ?></th><th scope="col"><?php esc_html_e( 'Level', 'ohmylms' ); ?></th><th scope="col"><?php esc_html_e( 'Status', 'ohmylms' ); ?></th><th scope="col"><?php esc_html_e( 'Practice', 'ohmylms' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $view['skills'] as $skill ) { ?>
					<tr>
						<th scope="row"><?php echo esc_html( $skill['name'] ); ?>
						<?php
						if ( $skill['sources'] ) {
							?>
							<small><?php echo esc_html( sprintf( /* translators: %s: text value. */
							__( 'In: %s', 'ohmylms' ), implode( ', ', $skill['sources'] ) ) ); ?></small><?php } ?></th>
						<td data-label="<?php esc_attr_e( 'Level', 'ohmylms' ); ?>"><?php echo self::level_badge( $skill['level'], $skill['level_label'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<?php
						if ( $skill['review_due'] ) {
							?>
							<span class="ohmylms-bundle-badge"><?php esc_html_e( 'Review due', 'ohmylms' ); ?></span><?php } ?></td>
						<td data-label="<?php esc_attr_e( 'Status', 'ohmylms' ); ?>"><?php echo esc_html( $skill['classification_label'] ); ?></td>
						<td data-label="<?php esc_attr_e( 'Practice', 'ohmylms' ); ?>">
						<?php
						if ( $skill['practice_url'] ) {
							?>
							<a href="<?php echo esc_url( $skill['practice_url'] ); ?>"><?php esc_html_e( 'Practice', 'ohmylms' ); ?></a>
							<?php
						} elseif ( 'strength' === $skill['classification'] && ! $skill['review_due'] ) {
										esc_html_e( '—', 'ohmylms' );
						} else {
							esc_html_e( 'None available yet', 'ohmylms' ); }
						?>
							</td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
		<?php if ( $view['next_practice'] ) { ?>
			<h4><?php esc_html_e( 'Next practice', 'ohmylms' ); ?></h4>
			<ul class="ohmylms-bundle-practice">
				<?php foreach ( $view['next_practice'] as $skill ) { ?>
					<li><a href="<?php echo esc_url( $skill['practice_url'] ); ?>"><?php echo esc_html( sprintf( /* translators: %s: text value. */
					__( 'Practice %s', 'ohmylms' ), $skill['name'] ) ); ?></a> — <?php echo esc_html( $skill['review_due'] ? __( 'Review due', 'ohmylms' ) : $skill['classification_label'] ); ?></li>
				<?php } ?>
			</ul>
		<?php } ?>
		<?php
	}

	/**
	 * Combined for Frontend.
	 *
	 * @param array $groups Groups.
	 */
	private static function combined( array $groups ) {
		?>
		<h4><?php esc_html_e( 'Combined skills', 'ohmylms' ); ?></h4>
		<p class="ohmylms-bundle-muted"><?php esc_html_e( 'Your administrator linked these skills across curricula. The shared level combines your answers from every skill marked equivalent, counting each answer once; it can differ from the level shown for the same skill above, which uses only that skill\'s own answers. Requirements, difficulty and syllabus versions can still differ, so check the notes.', 'ohmylms' ); ?></p>
		<?php foreach ( $groups as $group ) { ?>
			<details class="ohmylms-bundle-combined">
				<summary><strong><?php echo esc_html( $group['shared']['name'] ); ?></strong> — <?php esc_html_e( 'Shared level:', 'ohmylms' ); ?> <?php echo self::level_badge( $group['level'], $group['level_label'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></summary>
				<p><?php echo esc_html( $group['evidence_count'] ? sprintf( /* translators: %d: number. */
				_n( 'Based on %d answer.', 'Based on %d answers.', $group['evidence_count'], 'ohmylms' ), $group['evidence_count'] ) : __( 'No answers recorded yet.', 'ohmylms' ) ); ?></p>
				<ul>
					<?php foreach ( $group['sources'] as $source ) { ?>
						<li>
							<?php echo esc_html( $source['name'] ); ?> — <?php echo self::level_badge( $source['level'], $source['level_label'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<span class="ohmylms-bundle-badge"><?php echo esc_html( 'equivalent' === $source['relation'] ? __( 'Counts toward shared level', 'ohmylms' ) : __( 'Related only', 'ohmylms' ) ); ?></span>
							<?php foreach ( $source['context'] as $context ) { ?>
								<small><?php echo esc_html( implode( ' › ', array_merge( $context['path'], array( $context['name'] ) ) ) . ( '' !== $context['code'] ? ' (' . $context['code'] . ( '' !== $context['version'] ? ', ' . $context['version'] : '' ) . ')' : ( '' !== $context['version'] ? ' (' . $context['version'] . ')' : '' ) ) ); ?></small>
							<?php } ?>
							<?php
							if ( '' !== $source['note'] ) {
								?>
								<small><?php echo esc_html( sprintf( /* translators: %s: text value. */
								__( 'Differences: %s', 'ohmylms' ), $source['note'] ) ); ?></small><?php } ?>
						</li>
					<?php } ?>
				</ul>
			</details>
			<?php
		}
	}
}
