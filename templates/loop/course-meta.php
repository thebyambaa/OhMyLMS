<?php
/**
 * OhMyLMS Loop Course Meta
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/loop/course-meta.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

global $course;
if ( ! $course ) {
	return;
}
$layout_style = get_option( 'ohmylms_archive_page_layout_style', 'grid-style1' );
$level        = $course->get_level();

?>

<ul class="course-meta">
	<?php if ( $course->get_total_enrolled_users() > 0 ) { ?>
		<li class="total-enrolled-user">
			<span class="icon-text-align">
				<svg width="13" height="13" fill="none" viewBox="0 0 13 13" xmlns="http://www.w3.org/2000/svg"><path fill="#A1A1AA" d="M6.5 6.333c.606 0 1.197-.185 1.7-.533a3.146 3.146 0 001.127-1.421 3.27 3.27 0 00.174-1.83A3.203 3.203 0 008.664.927 3.028 3.028 0 007.098.061 2.963 2.963 0 005.33.24a3.084 3.084 0 00-1.373 1.166 3.247 3.247 0 00-.515 1.76c0 .84.323 1.644.897 2.238a3.01 3.01 0 002.162.928zM6.5 1c.415 0 .82.127 1.164.365s.612.577.77.973c.159.395.2.831.12 1.251-.081.42-.28.807-.573 1.11a2.035 2.035 0 01-2.282.469 2.11 2.11 0 01-.938-.798 2.221 2.221 0 01.26-2.735A2.06 2.06 0 016.502 1zm5.454 9.047l-.084-.195a3.99 3.99 0 00-1.392-1.807 3.786 3.786 0 00-2.124-.712H4.653a3.784 3.784 0 00-2.125.712A3.988 3.988 0 001.136 9.85l-.09.208a2.286 2.286 0 00.158 2.082c.156.261.373.477.631.627.259.15.55.23.846.232h7.633a1.71 1.71 0 00.848-.233 1.78 1.78 0 00.633-.628 2.297 2.297 0 00.158-2.092zm-.966 1.546a.824.824 0 01-.286.293.79.79 0 01-.387.114H2.681a.782.782 0 01-.384-.114.817.817 0 01-.284-.29 1.229 1.229 0 01-.083-1.134l.09-.208a3.011 3.011 0 011.038-1.37 2.856 2.856 0 011.595-.55h3.7c.573.017 1.128.21 1.596.551a3.01 3.01 0 011.037 1.371l.083.195a1.253 1.253 0 01-.083 1.142h.002z"/></svg>
				<?php
					$total = $course->get_total_enrolled_users();
					printf(
						esc_html( /* translators: %d: number. */
						_n( '%d Student', '%d Students', $total, 'ohmylms' ) ),
						$total
					);
				?>
			</span>
		</li>
	<?php } ?>

	<?php if ( $course->get_level() && 'all' !== $course->get_level() ) { ?>
		
		<li class="course-level <?php echo esc_attr( $course->get_level() ); ?>">
			<span class="icon-text-align">
				<?php
				if ( 'beginner' === $level ) {
					include OHMYLMS_DIR . '/assets/images/icon/level-beginner-icon.php';
					echo esc_html__( ' Beginner', 'ohmylms' );

				} elseif ( 'experience' === $level ) {
					include OHMYLMS_DIR . '/assets/images/icon/level-experience-icon.php';
					echo esc_html__( ' Experience', 'ohmylms' );

				} elseif ( 'expert' === $level ) {
					include OHMYLMS_DIR . '/assets/images/icon/level-expert-icon.php';
					echo esc_html__( 'Expert', 'ohmylms' );
				}
				?>
			</span>
		</li>
	<?php } ?>

	<?php
		$rating_count = $course->get_rating_count();
		$average      = $course->get_average_rating();
		$icon_percent = round( ( $average / 5 ) * 100 );

	if ( $rating_count > 0 ) {
		?>
		<li class="course-rating">
			<span class="icon-text-align">
			<?php include OHMYLMS_DIR . '/assets/images/icon/star-color-icon.php'; ?>
				<span class="rating">
					<span class="average-rating"><?php echo esc_html( $average ); ?></span>
				<?php
					printf(
						'(%s)',
						sprintf(
							esc_html( /* translators: %d: number. */
							_n( '%d', '%d', $rating_count, 'ohmylms' ) ),
							$rating_count
						)
					);
				?>
				</span>
			</span>

			<span class="course-review-rating" role="img">
				<span class="given-rate" style="width: <?php echo esc_attr( $icon_percent ); ?>%;"></span>
			</span>

			<span class="total-ratings">
				<?php
				echo '(' . $rating_count . ( 1 == $rating_count ? esc_html__( ' Rating', 'ohmylms' ) : esc_html__( ' Ratings', 'ohmylms' ) ) . ')';
				?>
			</span>            
		</li>
	<?php } ?>
</ul>

