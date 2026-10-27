<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Learner pages for the adaptive placement test.
 *
 * @package OhMyLMS\Placement
 */

namespace OhMyLMS\Placement;

use OhMyLMS\Curriculum\Comparison;
use OhMyLMS\Practice\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * The test page ([ohmylms_placement subject="…"], or ?ohmylms_placement=SUBJECT), drawn by the
 * practice runner, and the placement summary on My skills.
 */
final class PlacementPage {

	/** Register the shortcode and the standalone page. */
	public static function init() {
		add_shortcode( 'ohmylms_placement', array( __CLASS__, 'render' ) );
		add_filter(
			'query_vars',
			static function ( $vars ) {
				$vars[] = 'ohmylms_placement';
				return $vars;
			}
		);
		add_action( 'template_redirect', array( __CLASS__, 'page' ) );
	}

	/**
	 * The test: a short introduction, the grade question and the runner.
	 *
	 * @param array $attributes subject, items.
	 * @return string Escaped HTML.
	 */
	public static function render( $attributes ) {
		if ( ! Frontend::enabled() ) {
			return '';
		}
		$attributes = shortcode_atts(
			array(
				'subject' => '',
				'items'   => PlacementModel::DEFAULT_LIMIT,
			),
			(array) $attributes,
			'ohmylms_placement'
		);
		$subject    = sanitize_text_field( (string) $attributes['subject'] );
		if ( '' === $subject || ! in_array( $subject, Placement::subjects(), true ) ) {
			return '<p class="ohmylms-practice-notice">' . esc_html__( 'There is no placement test for this subject.', 'ohmylms' ) . '</p>';
		}
		$limit = PlacementModel::limit( $attributes['items'] );
		Frontend::runner_assets( self::strings() );
		ob_start();
		?>
		<section class="ohmylms-practice ohmylms-placement" data-placement="<?php echo esc_attr( $subject ); ?>" data-skill="<?php echo esc_attr( 'placement-' . md5( $subject ) ); ?>" data-course="0" data-items="<?php echo esc_attr( (string) $limit ); ?>">
			<h2>
				<?php
				/* translators: %s: subject name. */
				echo esc_html( sprintf( __( 'Placement test: %s', 'ohmylms' ), $subject ) );
				?>
			</h2>
			<div class="ohmylms-placement-intro">
				<p>
					<?php
					/* translators: %d: most questions. */
					echo esc_html( sprintf( __( 'Up to %d questions. Each one depends on your earlier answers, so the test finds what you already know without asking about everything.', 'ohmylms' ), $limit ) );
					?>
					<?php esc_html_e( 'There is no help and no feedback until the end. If you have not learned something yet, say so: that is useful too.', 'ohmylms' ); ?>
				</p>
				<p>
					<label for="ohmylms-placement-grade"><?php esc_html_e( 'Which level are you at?', 'ohmylms' ); ?></label>
					<select id="ohmylms-placement-grade" class="ohmylms-placement-grade">
						<option value="0"><?php esc_html_e( 'I am not sure', 'ohmylms' ); ?></option>
						<?php foreach ( Comparison::levels( $subject ) as $level ) { ?>
							<option value="<?php echo esc_attr( (string) $level['value'] ); ?>"><?php echo esc_html( $level['label'] ); ?></option>
						<?php } ?>
					</select>
				</p>
			</div>
			<noscript><p><?php esc_html_e( 'Enable JavaScript to take the placement test.', 'ohmylms' ); ?></p></noscript>
			<?php if ( ! is_user_logged_in() ) { ?>
				<p class="ohmylms-practice-guest">
					<?php esc_html_e( 'You are taking the test as a guest. Log in on this device afterwards to keep your placement and the answers that count toward your skills.', 'ohmylms' ); ?>
					<a href="<?php echo esc_url( wp_login_url( Placement::url( $subject ) ) ); ?>"><?php esc_html_e( 'Log in', 'ohmylms' ); ?></a>
				</p>
			<?php } ?>
			<div class="ohmylms-practice-stage" aria-live="polite"></div>
		</section>
		<?php
		return ob_get_clean();
	}

	/** Standalone page for ?ohmylms_placement=SUBJECT links. */
	public static function page() {
		$subject = sanitize_text_field( (string) get_query_var( 'ohmylms_placement' ) );
		if ( '' === $subject || ! Frontend::enabled() ) {
			return;
		}
		$html = self::render( array( 'subject' => $subject ) );
		$back = wp_get_referer();
		status_header( 200 );
		// A focused document, like a course activity: the test needs no site navigation.
		ohmylms_get_template(
			'student-hub/activity-page.php',
			array(
				'activity_shell' => '',
				'activity_html'  => '<main class="ohmylms-placement-page">' . ( $back ? '<p><a href="' . esc_url( $back ) . '">' . esc_html__( '← Back', 'ohmylms' ) . '</a></p>' : '' ) . $html . '</main>',
			)
		);
		exit;
	}

	/**
	 * The placement section of My skills: the latest result in each subject, and tests not taken yet.
	 *
	 * @param int $student_id Learner.
	 * @return string Escaped HTML, empty when no subject has a test.
	 */
	public static function summary( $student_id ) {
		$subjects = Placement::subjects();
		if ( ! $subjects ) {
			return '';
		}
		$results = array_column( Placement::results( (int) $student_id ), null, 'subject' );
		ob_start();
		?>
		<section class="ohmylms-placement-summary" aria-labelledby="ohmylms-placement-summary-title">
			<h3 id="ohmylms-placement-summary-title"><?php esc_html_e( 'Placement tests', 'ohmylms' ); ?></h3>
			<ul>
				<?php foreach ( $subjects as $subject ) { ?>
					<li>
						<strong><?php echo esc_html( $subject ); ?></strong>
						<?php
						if ( isset( $results[ $subject ] ) ) {
							$result = $results[ $subject ];
							$grades = array();
							foreach ( $result['domains'] as $domain ) {
								$grades[] = $domain['label'] . ': ' . ( $domain['grade_label'] ? $domain['grade_label'] : __( 'from the start', 'ohmylms' ) );
							}
							?>
							<span class="ohmylms-placement-when">
								<?php
								/* translators: %s: date. */
								echo esc_html( sprintf( __( 'taken %s', 'ohmylms' ), wp_date( get_option( 'date_format' ), strtotime( $result['completed_at'] . ' UTC' ) ) ) );
								?>
							</span>
							<?php if ( $grades ) { ?>
								<span class="ohmylms-placement-grades"><?php echo esc_html( implode( ' · ', $grades ) ); ?></span>
							<?php } ?>
							<?php if ( $result['start'] ) { ?>
								<span class="ohmylms-placement-first">
									<?php esc_html_e( 'Start with:', 'ohmylms' ); ?>
									<?php
									$links = array();
									foreach ( $result['start'] as $skill ) {
										$links[] = $skill['practice_url'] ? '<a href="' . esc_url( $skill['practice_url'] ) . '">' . esc_html( $skill['name'] ) . '</a>' : esc_html( $skill['name'] );
									}
									echo implode( ', ', $links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each part escaped above.
									?>
								</span>
							<?php } ?>
							<a class="ohmylms-placement-link" href="<?php echo esc_url( Placement::url( $subject ) ); ?>"><?php esc_html_e( 'Take it again', 'ohmylms' ); ?></a>
						<?php } else { ?>
							<a class="ohmylms-placement-link" href="<?php echo esc_url( Placement::url( $subject ) ); ?>"><?php esc_html_e( 'Take the placement test', 'ohmylms' ); ?></a>
						<?php } ?>
					</li>
				<?php } ?>
			</ul>
		</section>
		<?php
		return ob_get_clean();
	}

	/**
	 * Runner words for a placement test.
	 *
	 * @return array<string,string>
	 */
	private static function strings() {
		return array(
			'start'                => __( 'Start the placement test', 'ohmylms' ),
			'resume'               => __( 'Resume the placement test', 'ohmylms' ),
			'check'                => __( 'Submit answer', 'ohmylms' ),
			'dontKnow'             => __( 'I have not learned this yet', 'ohmylms' ),
			'dotDone'              => __( 'Answered', 'ohmylms' ),
			/* translators: 1: question number, 2: most questions */
			'progress'             => __( 'Question %1$d · up to %2$d', 'ohmylms' ),
			'empty'                => __( 'There are no placement questions for this subject yet.', 'ohmylms' ),
			/* translators: %s: subject name. */
			'placementTitle'       => __( 'Your placement in %s', 'ohmylms' ),
			/* translators: 1: skills known, 2: all skills */
			'placementSummary'     => __( 'You already know about %1$d of the %2$d skills.', 'ohmylms' ),
			/* translators: %s: grade name such as "Grade 7". */
			'placementGrade'       => __( 'working at %s', 'ohmylms' ),
			'placementBeginning'   => __( 'start from the beginning', 'ohmylms' ),
			/* translators: 1: skills known, 2: skills in the area */
			'placementCount'       => __( '(%1$d of %2$d skills)', 'ohmylms' ),
			'placementStartTitle'  => __( 'Where to start', 'ohmylms' ),
			'placementNothingLeft' => __( 'You seem to know everything the test could ask about. Keep it fresh with practice.', 'ohmylms' ),
			'placementNote'        => __( 'The questions you answered count toward your skills. Skills the test only estimated are not marked as learned: practise them to show it.', 'ohmylms' ),
			'placementAgain'       => __( 'Take the test again', 'ohmylms' ),
			'practice'             => __( 'Practice', 'ohmylms' ),
		);
	}
}
