<?php
/**
 * My learning: a continue card, the learner's courses with progress, and followed collections.
 * Override in yourtheme/ohmylms/student-hub/my-learning.php.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Student\Hub;
use OhMyLMS\Student\MyLearning;

defined( 'ABSPATH' ) || exit;

$hub_user   = get_current_user_id();
$hub_page   = max( 1, absint( get_query_var( 'current_page', 1 ) ) );
$hub_list   = MyLearning::courses( $hub_user, $hub_page, 12 );
$hub_follow = MyLearning::collections( $hub_user );
$hub_resume = null;
foreach ( $hub_list['items'] as $hub_card ) {
	if ( ! $hub_card['completed'] && 1 === $hub_page ) {
		$hub_resume = $hub_card;
		break;
	}
}
/**
 * Progress line for a course card, from real data only.
 *
 * @param array $card Course card.
 * @return string
 */
$hub_progress = static function ( array $card ) {
	$summary = $card['summary'];
	if ( ! $summary ) {
		return '';
	}
	if ( $summary['skills'] && $summary['tracked'] ) {
		/* translators: 1: skills at Proficient or better, 2: total skills. */
		$line = sprintf( __( '%1$d of %2$d skills to Proficient', 'ohmylms' ), $summary['proficient'], $summary['skills'] );
		if ( ! empty( $summary['credited'] ) ) {
			/* translators: %d: skills counted from work under other syllabuses. */
			$line .= ' ' . sprintf( __( '(%d from other syllabuses)', 'ohmylms' ), (int) $summary['credited'] );
		}
		return $line;
	}
	/* translators: 1: completed activities, 2: total activities. */
	return sprintf( __( '%1$d of %2$d activities complete', 'ohmylms' ), $summary['done'], $summary['total'] );
};
?>
<section class="oml-hub-banner">
	<h1><?php esc_html_e( 'My learning', 'ohmylms' ); ?></h1>
	<p><?php esc_html_e( 'Pick up where you left off.', 'ohmylms' ); ?></p>
</section>

<?php if ( ! $hub_list['total'] ) : ?>
	<div class="oml-hub-empty">
		<h2><?php esc_html_e( 'You are not enrolled in any courses yet', 'ohmylms' ); ?></h2>
		<p><?php esc_html_e( 'Browse the subjects to find a course and start learning.', 'ohmylms' ); ?></p>
		<a class="oml-hub-button" href="<?php echo esc_url( Hub::url( 'library' ) ); ?>"><?php esc_html_e( 'Go to library', 'ohmylms' ); ?></a>
	</div>
<?php else : ?>
	<?php if ( $hub_resume ) : ?>
		<section class="oml-hub-hero oml-hub-resume" aria-labelledby="oml-resume-heading">
			<p class="oml-hub-meta"><?php esc_html_e( 'Continue learning', 'ohmylms' ); ?></p>
			<h2 id="oml-resume-heading"><?php echo esc_html( $hub_resume['title'] ); ?></h2>
			<?php if ( $hub_progress( $hub_resume ) ) : ?>
				<p><?php echo esc_html( $hub_progress( $hub_resume ) ); ?></p>
				<progress max="100" value="<?php echo esc_attr( $hub_resume['summary']['percent'] ); ?>" aria-label="<?php echo esc_attr( $hub_resume['title'] ); ?>"></progress>
			<?php endif; ?>
			<p>
				<a class="oml-hub-button" href="<?php echo esc_url( $hub_resume['continue']['url'] ); ?>"><?php echo esc_html( $hub_resume['continue']['label'] ); ?></a>
				<a class="oml-hub-textlink" href="<?php echo esc_url( $hub_resume['overview'] ); ?>"><?php esc_html_e( 'Course overview', 'ohmylms' ); ?></a>
			</p>
		</section>
	<?php endif; ?>

	<section class="oml-hub-section" aria-labelledby="oml-courses-heading">
		<h2 id="oml-courses-heading"><?php esc_html_e( 'Your courses', 'ohmylms' ); ?></h2>
		<ul class="oml-hub-coursecards">
			<?php foreach ( $hub_list['items'] as $hub_card ) : ?>
				<li class="oml-hub-coursecard <?php echo $hub_card['completed'] ? 'is-complete' : ''; ?>">
					<h3><a class="oml-hub-coursecard-title" href="<?php echo esc_url( $hub_card['overview'] ); ?>"><?php echo esc_html( $hub_card['title'] ); ?></a></h3>
					<?php if ( $hub_card['summary'] ) : ?>
						<p class="oml-hub-meta">
							<?php /* translators: %d: number of units. */ echo esc_html( sprintf( _n( '%d unit', '%d units', $hub_card['summary']['units'], 'ohmylms' ), $hub_card['summary']['units'] ) ); ?>
							<?php if ( $hub_card['summary']['skills'] ) : ?>
								<span aria-hidden="true">&bull;</span> <?php /* translators: %d: number of skills. */ echo esc_html( sprintf( _n( '%d skill', '%d skills', $hub_card['summary']['skills'], 'ohmylms' ), $hub_card['summary']['skills'] ) ); ?>
							<?php endif; ?>
						</p>
						<progress max="100" value="<?php echo esc_attr( $hub_card['completed'] ? 100 : $hub_card['summary']['percent'] ); ?>" aria-label="<?php echo esc_attr( $hub_card['title'] ); ?>"></progress>
						<p class="oml-hub-meta"><?php echo esc_html( $hub_card['completed'] ? __( 'Completed', 'ohmylms' ) : $hub_progress( $hub_card ) ); ?></p>
					<?php endif; ?>
					<p><a class="oml-hub-button" href="<?php echo esc_url( $hub_card['completed'] ? $hub_card['overview'] : $hub_card['continue']['url'] ); ?>"><?php echo esc_html( $hub_card['completed'] ? __( 'Review course', 'ohmylms' ) : $hub_card['continue']['label'] ); ?></a></p>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php if ( $hub_list['pages'] > 1 ) : ?>
			<nav class="oml-hub-pager" aria-label="<?php esc_attr_e( 'Course pages', 'ohmylms' ); ?>">
				<?php if ( $hub_page > 1 ) : ?>
					<a class="oml-hub-pill" href="<?php echo esc_url( add_query_arg( 'current_page', $hub_page - 1, Hub::url( 'learn' ) ) ); ?>">&larr; <?php esc_html_e( 'Previous', 'ohmylms' ); ?></a>
				<?php endif; ?>
				<span><?php /* translators: 1: current page, 2: total pages. */ echo esc_html( sprintf( __( 'Page %1$d of %2$d', 'ohmylms' ), $hub_page, $hub_list['pages'] ) ); ?></span>
				<?php if ( $hub_page < $hub_list['pages'] ) : ?>
					<a class="oml-hub-pill" href="<?php echo esc_url( add_query_arg( 'current_page', $hub_page + 1, Hub::url( 'learn' ) ) ); ?>"><?php esc_html_e( 'Next', 'ohmylms' ); ?> &rarr;</a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	</section>
<?php endif; ?>

<?php if ( $hub_follow ) : ?>
	<section class="oml-hub-section" aria-labelledby="oml-collections-heading">
		<h2 id="oml-collections-heading"><?php esc_html_e( 'Collections you follow', 'ohmylms' ); ?></h2>
		<div class="oml-hub-units">
			<?php foreach ( $hub_follow as $hub_bundle ) : ?>
				<details class="oml-hub-unit">
					<summary class="oml-hub-disclosure">
						<span class="oml-hub-unit-title"><?php echo esc_html( $hub_bundle['title'] ); ?></span>
						<span class="oml-hub-unit-count"><?php /* translators: %d: number of courses. */ echo esc_html( sprintf( _n( '%d course', '%d courses', count( $hub_bundle['courses'] ), 'ohmylms' ), count( $hub_bundle['courses'] ) ) ); ?></span>
					</summary>
					<ul class="oml-hub-rows">
						<?php foreach ( $hub_bundle['courses'] as $hub_course ) : ?>
							<li><a class="oml-hub-row" href="<?php echo esc_url( $hub_course['url'] ); ?>"><span class="oml-hub-row-name"><?php echo esc_html( $hub_course['title'] ); ?></span></a></li>
						<?php endforeach; ?>
					</ul>
				</details>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>
