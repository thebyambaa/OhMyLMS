<?php
/**
 * Achievements: mastery summary, points and streak when enabled, badges and certificates.
 * Override in yourtheme/ohmylms/student-hub/achievements.php.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Certificates\Download;
use OhMyLMS\Certificates\Issuer;
use OhMyLMS\Certificates\PdfRenderer;
use OhMyLMS\Engagement\Badge;
use OhMyLMS\Engagement\Xp;
use OhMyLMS\Performance\Snapshot;
use OhMyLMS\Student\Hub;

defined( 'ABSPATH' ) || exit;

$hub_user      = get_current_user_id();
$hub_mastery   = Snapshot::counts( $hub_user );
$hub_levels    = array(
	'attempted'  => __( 'Attempted', 'ohmylms' ),
	'familiar'   => __( 'Familiar', 'ohmylms' ),
	'proficient' => __( 'Proficient', 'ohmylms' ),
	'mastered'   => __( 'Mastered', 'ohmylms' ),
);
$hub_badges    = Badge::maybe_enable() ? Badge::get_all_badges_of_a_user( $hub_user ) : array();
$hub_xp        = Xp::enabled() ? Xp::summary( $hub_user, 7 ) : null;
$hub_completed = $student->get_completed_course();
?>
<section class="oml-hub-banner">
	<h1><?php esc_html_e( 'Achievements', 'ohmylms' ); ?></h1>
	<p><?php esc_html_e( 'Your skills, rewards and certificates.', 'ohmylms' ); ?></p>
</section>

<section class="oml-hub-section" aria-labelledby="oml-mastery-heading">
	<h2 id="oml-mastery-heading"><?php esc_html_e( 'Mastery learning', 'ohmylms' ); ?></h2>
	<?php if ( ! $hub_mastery['total'] ) : ?>
		<div class="oml-hub-empty">
			<h2><?php esc_html_e( 'No skills practised yet', 'ohmylms' ); ?></h2>
			<p><?php esc_html_e( 'Practise a skill and it will show here as Attempted, Familiar, Proficient and finally Mastered.', 'ohmylms' ); ?></p>
			<a class="oml-hub-button" href="<?php echo esc_url( Hub::url( 'library' ) ); ?>"><?php esc_html_e( 'Browse subjects', 'ohmylms' ); ?></a>
		</div>
	<?php else : ?>
		<ul class="oml-hub-tiles oml-hub-mastery">
			<?php foreach ( $hub_levels as $hub_key => $hub_label ) : ?>
				<li class="oml-hub-tile is-<?php echo esc_attr( $hub_key ); ?>">
					<span class="oml-hub-tile-count"><?php echo esc_html( $hub_mastery[ $hub_key ] ); ?></span>
					<span class="oml-hub-tile-label"><?php echo esc_html( $hub_label ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
		<p class="oml-hub-muted">
			<?php /* translators: %d: number of skills. */ echo esc_html( sprintf( _n( '%d skill so far.', '%d skills so far.', $hub_mastery['total'], 'ohmylms' ), $hub_mastery['total'] ) ); ?>
		</p>
	<?php endif; ?>
</section>

<?php if ( $hub_xp ) : ?>
	<section class="oml-hub-section" aria-labelledby="oml-points-heading">
		<h2 id="oml-points-heading"><?php esc_html_e( 'Points', 'ohmylms' ); ?></h2>
		<ul class="oml-hub-tiles">
			<li class="oml-hub-tile"><span class="oml-hub-tile-count"><?php echo esc_html( number_format_i18n( (int) $hub_xp['total'] ) ); ?></span><span class="oml-hub-tile-label"><?php esc_html_e( 'XP in total', 'ohmylms' ); ?></span></li>
			<li class="oml-hub-tile"><span class="oml-hub-tile-count"><?php echo esc_html( number_format_i18n( (int) $hub_xp['week'] ) ); ?></span><span class="oml-hub-tile-label"><?php esc_html_e( 'XP this week', 'ohmylms' ); ?></span></li>
			<li class="oml-hub-tile"><span class="oml-hub-tile-count"><?php echo esc_html( (int) $hub_xp['today'] . ' / ' . (int) $hub_xp['goal'] ); ?></span><span class="oml-hub-tile-label"><?php esc_html_e( 'XP today against your goal', 'ohmylms' ); ?></span></li>
		</ul>
	</section>
<?php endif; ?>

<?php if ( \OhMyLMS\Engagement\StreakSettings::enabled() ) : ?>
	<section class="oml-hub-section oml-hub-legacy" aria-label="<?php esc_attr_e( 'Streak', 'ohmylms' ); ?>">
		<?php ohmylms_get_template( 'profile/streak.php', array( 'in_progress_courses' => $student->get_progress_course() ) ); ?>
	</section>
<?php endif; ?>

<?php if ( $hub_badges ) : ?>
	<section class="oml-hub-section" aria-labelledby="oml-badges-heading">
		<h2 id="oml-badges-heading"><?php esc_html_e( 'Badges', 'ohmylms' ); ?></h2>
		<ul class="oml-hub-tiles">
			<?php foreach ( $hub_badges as $hub_badge ) : ?>
				<li class="oml-hub-tile is-badge"><span class="oml-hub-tile-label"><?php echo esc_html( $hub_badge['name'] ?? $hub_badge['slug'] ?? '' ); ?></span></li>
			<?php endforeach; ?>
		</ul>
	</section>
<?php endif; ?>

<?php
Issuer::sync_for_user( $hub_user );
$hub_certificates = Issuer::for_user( $hub_user );
$hub_awarded      = array_column( $hub_certificates, 'course_id' );
$hub_uncertified  = array_filter(
	(array) $hub_completed,
	static function ( $hub_course ) use ( $hub_awarded ) {
		return ! in_array( (int) $hub_course->get_id(), $hub_awarded, true );
	}
);
?>
<section class="oml-hub-section" aria-labelledby="oml-certificates-heading">
	<h2 id="oml-certificates-heading"><?php esc_html_e( 'Completed courses and certificates', 'ohmylms' ); ?></h2>
	<?php if ( ! $hub_certificates && ! $hub_uncertified ) : ?>
		<p class="oml-hub-muted"><?php esc_html_e( 'Courses you complete, and any certificates, will appear here.', 'ohmylms' ); ?></p>
	<?php else : ?>
		<ul class="oml-hub-rows oml-hub-cert-list">
			<?php foreach ( $hub_certificates as $hub_certificate ) : ?>
				<li class="oml-hub-row">
					<span class="oml-hub-row-name">
						<?php echo esc_html( $hub_certificate['course_title'] ); ?>
						<span class="oml-hub-muted">
							<?php
							/* translators: 1: date, 2: verification code. */
							echo esc_html( sprintf( __( 'Certificate of %1$s · code %2$s', 'ohmylms' ), Issuer::local_date( $hub_certificate['completed_at'] ? $hub_certificate['completed_at'] : $hub_certificate['issued_at'] ), $hub_certificate['code'] ) );
							?>
						</span>
					</span>
					<?php if ( PdfRenderer::available() ) : ?>
						<a class="oml-hub-button" href="<?php echo esc_url( Download::url( $hub_certificate['code'], 'pdf' ) ); ?>"><?php esc_html_e( 'Download PDF', 'ohmylms' ); ?></a>
					<?php endif; ?>
					<a href="<?php echo esc_url( Download::url( $hub_certificate['code'], 'print' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Print', 'ohmylms' ); ?></a>
					<a href="<?php echo esc_url( Download::verify_url( $hub_certificate['code'] ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Verification page', 'ohmylms' ); ?></a>
				</li>
			<?php endforeach; ?>
			<?php foreach ( $hub_uncertified as $hub_course ) : ?>
				<li class="oml-hub-row">
					<span class="oml-hub-row-name"><?php echo esc_html( $hub_course->get_name() ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
