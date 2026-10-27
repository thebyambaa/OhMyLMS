<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
namespace OhMyLMS\Learning;

use OhMyLMS\Skills\Evidence;
use OhMyLMS\Skills\Mastery;
use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin frontend implementation.
 */
final class Frontend {
	/**
	 * Init for Frontend.
	 */
	public static function init() {
		UnitSnapshots::init();
		add_shortcode( 'ohmylms_learning', array( __CLASS__, 'shortcode' ) );
		add_filter(
			'query_vars',
			static function ( $vars ) {
				$vars[] = 'ohmylms_learning';
				$vars[] = 'learning_item';
				return $vars;
			}
		);
		add_action( 'template_redirect', array( __CLASS__, 'page' ) );
		add_filter( 'ohmylms_course_tabs', array( __CLASS__, 'course_tab' ), 30 );
	}

	/**
	 * Url for Frontend.
	 *
	 * @param mixed $course Course.
	 */
	public static function url( $course ) {
		return add_query_arg( 'ohmylms_learning', (int) $course, home_url( '/' ) ); }

	/**
	 * Assets for Frontend.
	 */
	public static function assets() {
		wp_enqueue_style( 'ohmylms-learning', plugins_url( 'assets/css/learning-program.css', OHMYLMS_FILE ), array(), OHMYLMS_VERSION );
		wp_enqueue_script( 'ohmylms-learning', plugins_url( 'assets/js/learning-program.js', OHMYLMS_FILE ), array(), OHMYLMS_VERSION, true );
		wp_localize_script(
			'ohmylms-learning',
			'ohmylmsLearning',
			array(
				'root'   => rest_url( 'ohmylms/v1/' ),
				'nonce'  => wp_create_nonce( 'wp_rest' ),
				'saving' => __( 'Saving...', 'ohmylms' ),
				'error'  => __( 'Could not save completion. Please retry.', 'ohmylms' ),
			)
		);
	}

	/**
	 * Course tab for Frontend.
	 *
	 * @param mixed $tabs Tabs.
	 */
	public static function course_tab( $tabs ) {
		global $course;
		if ( ! $course || ! CourseProgram::current( $course->get_id() ) ) {
			return $tabs; }
		$tabs['learning'] = array(
			'title'    => __( 'Learning', 'ohmylms' ),
			'priority' => 5,
			'callback' => static function () use ( $course ) {
						echo self::render( $course->get_id() );
			},
		);
		return $tabs;
	}

	/**
	 * Shortcode for Frontend.
	 *
	 * @param mixed $attributes Attributes.
	 */
	public static function shortcode( $attributes ) {
		$attributes = shortcode_atts( array( 'course' => 0 ), (array) $attributes, 'ohmylms_learning' );
		return self::render( (int) $attributes['course'] );
	}

	/**
	 * Render for Frontend.
	 *
	 * @param mixed  $course_id Course identifier.
	 * @param string $placement Placement.
	 * @param bool   $focused Focused.
	 */
	public static function render( $course_id, $placement = '', $focused = false ) {
		if ( get_post_type( $course_id ) !== OHMYLMS_COURSE_CPT || get_post_status( $course_id ) !== 'publish' ) {
			return '<p>' . esc_html__( 'This course is not available.', 'ohmylms' ) . '</p>'; }
		if ( ! is_user_logged_in() ) {
			return '<p><a href="' . esc_url( wp_login_url( self::url( $course_id ) ) ) . '">' . esc_html__( 'Log in to open your learning path.', 'ohmylms' ) . '</a></p>'; }
		if ( ! Schema::ready() ) {
			return ''; }
		Evidence::process( 50 );
		$state = CompletionPolicy::award( get_current_user_id(), $course_id );
		if ( ! $state ) {
			$state = CompletionPolicy::status( get_current_user_id(), $course_id ); }
		if ( is_wp_error( $state ) ) {
			return '<p>' . esc_html( $state->get_error_message() ) . '</p><a href="' . esc_url( get_permalink( $course_id ) ) . '">' . esc_html__( 'Course overview', 'ohmylms' ) . '</a>'; }
		self::assets();
		$program    = $state['program'];
		$unit_items = array();
		foreach ( $program['items'] as $item ) {
			$unit_items[ (int) $item['chapter_id'] ][] = $item;
		}
		$ordered_items = array();
		foreach ( StructurePlan::order_units( $unit_items, $program['units'] ) as $items ) {
			$ordered_items = array_merge( $ordered_items, $items );
		}
		$program['items'] = $ordered_items;
		$student          = get_current_user_id();
		ob_start();
		?>
		<section class="ohmylms-learning-program" data-course="<?php echo esc_attr( $course_id ); ?>">
			<?php if ( ! $focused ) : ?>
			<a href="<?php echo esc_url( get_permalink( $course_id ) ); ?>"><?php esc_html_e( 'Course overview', 'ohmylms' ); ?></a>
			<h1><?php echo esc_html( get_the_title( $course_id ) ); ?></h1>
			<p>
				<?php
				echo esc_html(
					array(
						'traditional' => __( 'Traditional', 'ohmylms' ),
						'skill-based' => __( 'Skill-based', 'ohmylms' ),
						'blended'     => __( 'Blended', 'ohmylms' ),
					)[ $program['mode'] ]
				);
				?>
				· <?php echo esc_html( sprintf( /* translators: %d: number. */
				__( 'Program version %d', 'ohmylms' ), $program['version'] ) ); ?></p>
			<?php endif; ?>
			<?php
			if ( $state['completed'] ) {
				?>
				<p class="ohmylms-learning-completed"><?php esc_html_e( 'Course completed', 'ohmylms' ); ?></p><?php } ?>
			<?php
			if ( $state['blocked'] ) {
				?>
				<p role="status"><?php esc_html_e( 'Skill practice is currently unavailable. Contact your teacher.', 'ohmylms' ); ?></p>
				<?php
			} elseif ( $state['pending'] ) {
				?>
				<p role="status"><?php esc_html_e( 'Skill evidence is being evaluated. Your progress will update when evaluation finishes.', 'ohmylms' ); ?></p><?php } ?>
			<?php if ( ! $focused ) : ?>
			<dl class="ohmylms-learning-dimensions">
				<?php
				foreach ( array(
					'activities'  => __( 'Activities', 'ohmylms' ),
					'outcomes'    => __( 'Skill targets', 'ohmylms' ),
					'assessments' => __( 'Checkpoints', 'ohmylms' ),
				) as $key => $label ) {
					$count = $state['dimensions'][ $key ];
					?>
					<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( sprintf( /* translators: %1$d: number; %2$d: number. */
					__( '%1$d of %2$d required', 'ohmylms' ), $count['met'], $count['total'] ) ); ?></dd></div>
				<?php } ?>
			</dl>
			<?php endif; ?>
			<?php
			if ( $placement ) {
				$found = false;
				foreach ( $program['items'] as $item ) {
					if ( $item['id'] !== $placement ) {
						continue; }
					$found = true;
					if ( ! $focused ) {
						echo '<p><a href="' . esc_url( self::url( $course_id ) ) . '">' . esc_html__( 'Back to learning path', 'ohmylms' ) . '</a></p>';
					}
					if ( ! LearningPath::available( $item, $student, $course_id ) ) {
						echo '<p>' . esc_html__( 'This activity is currently unavailable.', 'ohmylms' ) . '</p>';
						break; }
					if ( 'practice' === $item['type'] ) {
						echo \OhMyLMS\Practice\Frontend::practice(
							array(
								'skill'  => $item['content_id'],
								'course' => $course_id,
							)
						);
					} elseif ( 'lesson' === $item['type'] ) {
						echo '<article class="ohmylms-learning-lesson"><h2>' . esc_html( $item['name'] ) . '</h2>';
						$lesson = ohmylms_get_lesson( $item['content_id'] );
						$media  = $lesson->get_type() === 'video' ? $lesson->get_video_id() : ( $lesson->get_type() === 'audio' ? $lesson->get_audio_id() : 0 );
						$url    = $media ? wp_get_attachment_url( $media ) : $lesson->get_external_url();
						if ( $url && $lesson->get_type() === 'video' ) {
							echo wp_video_shortcode( array( 'src' => $url ) ); }
						if ( $url && $lesson->get_type() === 'audio' ) {
							echo wp_audio_shortcode( array( 'src' => $url ) ); }
						$old_post        = $GLOBALS['post'] ?? null;
						$GLOBALS['post'] = get_post( $item['content_id'] );
						setup_postdata( $GLOBALS['post'] );
						echo apply_filters( 'the_content', get_post_field( 'post_content', $item['content_id'] ) );
						$GLOBALS['post'] = $old_post;
						if ( $old_post ) {
							setup_postdata( $old_post ); }
						echo '</article>';
						if ( ! $item['complete'] ) {
							?>
						<button type="button" class="ohmylms-button" data-complete-placement="<?php echo esc_attr( $item['id'] ); ?>"><?php esc_html_e( 'Mark activity complete', 'ohmylms' ); ?></button><p class="ohmylms-learning-message" role="status"></p>
							<?php
						} else {
							echo '<p>' . esc_html__( 'Activity completed in this course', 'ohmylms' ) . '</p>'; }
					}
					break;
				}
				if ( ! $found ) {
					echo '<p>' . esc_html__( 'Learning activity not found.', 'ohmylms' ) . '</p>'; }
			} else {
				$next = LearningPath::next( $state );
				if ( $next ) {
					?>
				<p><a class="ohmylms-button" href="<?php echo esc_url( $next['url'] ); ?>"><?php echo esc_html( sprintf( /* translators: %s: text value. */
				__( 'Continue with %s', 'ohmylms' ), $next['name'] ) ); ?></a></p>
					<?php
				}
				if ( 'skill-based' === $program['mode'] ) {
					self::outcomes( $program, $state['blocked'] ); }
				?>
				<h2><?php echo esc_html( 'blended' === $program['mode'] ? __( 'Learning path', 'ohmylms' ) : __( 'Curriculum', 'ohmylms' ) ); ?></h2>
				<ol class="ohmylms-learning-path">
				<?php
				$chapter = null; foreach ( $program['items'] as $item ) {
					if ( $chapter !== $item['chapter_id'] ) {
						$chapter = $item['chapter_id']; if ( $chapter ) {
							?>
					<li class="ohmylms-learning-unit"><h3><?php echo esc_html( get_the_title( $chapter ) ); ?></h3></li>
							<?php
						}
					}
					$available = LearningPath::available( $item, $student, $course_id );
					?>
					<li>
						<span><?php echo esc_html( ucfirst( $item['type'] ) ); ?></span>
						<?php
						if ( $available ) {
							?>
							<a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['name'] ); ?></a>
							<?php
						} else {
							?>
							<strong><?php echo esc_html( $item['name'] ); ?></strong><?php } ?>
						<span><?php echo esc_html( $item['complete'] ? __( 'Complete', 'ohmylms' ) : ( ! $available ? __( 'Unavailable', 'ohmylms' ) : ( 'practice' === $item['type'] ? __( 'Uses skill target', 'ohmylms' ) : ( $item['required'] ? __( 'Required', 'ohmylms' ) : __( 'Optional', 'ohmylms' ) ) ) ) ); ?></span>
						<?php
						if ( 'quiz' === $item['type'] ) {
							$assessment = $state['assessments'][ $item['content_id'] ];
							?>
							<small><?php echo esc_html( sprintf( /* translators: %1$s: text value; %2$s: text value. */
							__( 'Score %1$s · Pass %2$s%%', 'ohmylms' ), null === $assessment['score'] ? __( 'Not graded', 'ohmylms' ) : $assessment['score'] . '%', $assessment['pass_percent'] ) ); ?></small><?php } ?>
					</li>
				<?php } ?>
				</ol>
				<?php
				if ( 'skill-based' !== $program['mode'] ) {
					self::outcomes( $program, $state['blocked'] ); }
			}
			?>
		</section>
		<?php
		return ob_get_clean();
	}

	/**
	 * Outcomes for Frontend.
	 *
	 * @param array $program Program.
	 * @param mixed $blocked Blocked.
	 */
	private static function outcomes( array $program, $blocked ) {
		if ( ! $program['outcomes'] ) {
			return; }
		?>
		<h2><?php esc_html_e( 'Skills', 'ohmylms' ); ?></h2>
		<div class="ohmylms-learning-table-scroll"><table class="ohmylms-learning-skills"><thead><tr><th><?php esc_html_e( 'Skill', 'ohmylms' ); ?></th><th><?php esc_html_e( 'Current level', 'ohmylms' ); ?></th><th><?php esc_html_e( 'Target', 'ohmylms' ); ?></th><th><?php esc_html_e( 'Status', 'ohmylms' ); ?></th><th></th></tr></thead><tbody>
		<?php
		foreach ( $program['outcomes'] as $outcome ) {
			?>
			<tr>
			<th scope="row"><?php echo esc_html( $outcome['name'] ); ?></th>
			<td data-label="<?php esc_attr_e( 'Current level', 'ohmylms' ); ?>"><?php echo esc_html( Mastery::label( $outcome['state']['level'] ) ); ?>
			<?php
			if ( ! empty( $outcome['credit'] ) ) {
				echo '<br><span class="ohmylms-learning-credit">';
				/* translators: %s: skill level. */
				echo esc_html( sprintf( __( '%s from other syllabuses', 'ohmylms' ), Mastery::label( $outcome['credit']['level'] ) ) );
				echo $outcome['credit']['counts'] ? '' : ' ' . esc_html__( '(counts once proficient)', 'ohmylms' );
				echo '</span>';
			}
			?>
			<?php
			if ( ! empty( $outcome['state']['review_due'] ) ) {
				echo '<br>' . esc_html__( 'Review due', 'ohmylms' ); }
			?>
			</td>
			<td data-label="<?php esc_attr_e( 'Target', 'ohmylms' ); ?>"><?php echo esc_html( Mastery::label( $outcome['target'] ) ); ?></td>
			<td data-label="<?php esc_attr_e( 'Status', 'ohmylms' ); ?>"><?php echo esc_html( $outcome['met'] ? __( 'Target met', 'ohmylms' ) : ( $outcome['required'] ? __( 'Required', 'ohmylms' ) : __( 'Optional', 'ohmylms' ) ) ); ?></td>
			<td>
			<?php
			if ( ! $blocked ) {
				?>
				<a href="<?php echo esc_url( $outcome['practice_url'] ); ?>"><?php esc_html_e( 'Practice', 'ohmylms' ); ?></a><?php } ?></td>
		</tr><?php } ?>
		</tbody></table></div>
		<?php
	}

	/**
	 * Page for Frontend.
	 */
	public static function page() {
		$course = (int) get_query_var( 'ohmylms_learning' );
		if ( ! $course ) {
			return; }
		if ( get_post_type( $course ) !== OHMYLMS_COURSE_CPT || get_post_status( $course ) !== 'publish' ) {
			return; }
		nocache_headers();
		$placement = (string) get_query_var( 'learning_item' );
		ob_start();
		$focused = \OhMyLMS\Student\PlayerContext::open_learning( $course, $placement );
		$shell   = ob_get_clean();
		$html    = self::render( $course, $placement, $focused );
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
		echo '<main class="ohmylms-learning-main">' . $html . '</main>';
		get_footer();
		exit;
	}
}
