<?php
/**
 * The template for displaying lesson's Audio, Video, Text content
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/single-lesson/content-lesson.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( \OhMyLMS\Extensions\Layouts::render( 'lesson', get_the_ID(), ohmylms_get_course_by_content_id( get_the_ID() ) ) ) {
	return;
}
?>

<?php while ( have_posts() ) : ?>
	<?php the_post(); ?>

	<?php
	$video_conf = \OhMyLMS\Learning\VideoCompletion::settings( get_the_ID() );
	$video_rule = \OhMyLMS\Learning\VideoCompletion::rule( get_the_ID() );
	?>
	<div class="ohmylms-lesson-content-body ohmylms-wysiwyg-content" 
		data-lesson-id="<?php echo esc_attr( get_the_ID() ); ?>"
		data-course-id="<?php echo esc_attr( ohmylms_get_course_by_content_id( get_the_ID() ) ); ?>"
		<?php if ( $video_rule['required'] ) : ?>
		data-ohmylms-watch-percent="<?php echo esc_attr( $video_rule['percent'] ); ?>"
		<?php endif; ?>
		<?php if ( $video_conf['duration'] ) : ?>
		data-ohmylms-video-duration="<?php echo esc_attr( $video_conf['duration'] ); ?>"
		<?php endif; ?>>
		<h1><?php echo esc_html( get_the_title() ); ?></h1>
		<?php

		$lesson         = ohmylms_get_lesson( get_the_ID() );
		$lesson_id      = $lesson->get_id();
		$video_settings = method_exists( $lesson, 'get_video_settings' ) ? $lesson->get_video_settings() : array();
		$video_settings = is_array( $video_settings ) ? $video_settings : array();
		// The normalized values: controls are on unless switched off, and the aspect ratio is a safe `n/n`.
		$video_settings['controls']     = $video_conf['controls'];
		$video_settings['aspect_ratio'] = $video_conf['aspect_ratio'];

		$cover_image = wp_get_attachment_image_src( $lesson->get_cover_image_id(), 'large' ) ? wp_get_attachment_image_src( $lesson->get_cover_image_id(), 'large' )[0] : '';
		$poster_src  = $video_conf['poster_id'] ? wp_get_attachment_image_src( $video_conf['poster_id'], 'large' ) : false;
		$poster      = $poster_src ? $poster_src[0] : $cover_image;
		$video       = wp_get_attachment_url( $lesson->get_video_id() );

		if ( $video_conf['duration'] && ( $video || ( $lesson->get_external_url() && 'audio' !== $lesson->get_type() ) ) ) {
			$length = $video_conf['duration'] >= HOUR_IN_SECONDS ? gmdate( 'G:i:s', $video_conf['duration'] ) : gmdate( 'i:s', $video_conf['duration'] );
			?>
			<p class="ohmylms-lesson-video-duration">
				<?php
				/* translators: %s: video length such as 12:30. */
				echo esc_html( sprintf( __( 'Video length: %s', 'ohmylms' ), $length ) );
				?>
			</p>
			<?php
		}

		$cover_image_title = get_the_title( $lesson->get_cover_image_id() );
		$cover_image_alt   = get_post_meta( $lesson->get_cover_image_id(), '_wp_attachment_image_alt', true );

		$player_args = array(
			'lesson_id'       => $lesson_id,
			'course_id'       => (int) ohmylms_get_course_by_content_id( $lesson_id ),
			'track'           => true,
			'autoplay'        => ! empty( $video_settings['autoplay'] ),
			'loop'            => ! empty( $video_settings['loop'] ),
			'controls'        => ! empty( $video_settings['controls'] ),
			'poster'          => $poster,
			'poster_alt'      => $cover_image_alt ? $cover_image_alt : $cover_image_title,
			'aspect_ratio'    => ! empty( $video_settings['aspect_ratio'] ) ? $video_settings['aspect_ratio'] : '16/9',
			'title'           => get_the_title(),
			'remove_branding' => ! empty( $video_settings['remove_branding'] ),
		);

		if ( $video ) {
			\OhMyLMS\Extensions\Video::render( array( 'url' => $video ) + $player_args );
		} elseif ( ! $video && $cover_image ) {
			echo '<figure>';
				echo wp_get_attachment_image( $lesson->get_cover_image_id(), 'full' );
			echo '</figure>';

		}

		$external_url = $lesson->get_external_url();
		$lesson_type  = method_exists( $lesson, 'get_type' ) ? $lesson->get_type() : '';

		// Audio lesson with external URL — render as audio embed, not video.
		if ( $external_url && 'audio' === $lesson_type ) {
			$is_spotify    = strpos( $external_url, 'spotify.com' ) !== false;
			$is_soundcloud = strpos( $external_url, 'soundcloud.com' ) !== false;
			$is_audio_file = (bool) preg_match( '/\.(mp3|wav|m4a|aac|ogg|flac)(\?.*)?$/i', $external_url );

			if ( $is_spotify ) {
				preg_match( '/track\/([a-zA-Z0-9]+)/', $external_url, $spotify_matches );
				$spotify_id    = ! empty( $spotify_matches[1] ) ? $spotify_matches[1] : '';
				$spotify_embed = $spotify_id ? 'https://open.spotify.com/embed/track/' . $spotify_id : $external_url;
				?>
				<div class="ohmylms-lesson-audio ohmylms-lesson-external-audio">
					<iframe
						src="<?php echo esc_url( $spotify_embed ); ?>"
						width="100%"
						height="152"
						frameborder="0"
						allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"
						loading="lazy"
						title="<?php esc_attr_e( 'Spotify Audio Player', 'ohmylms' ); ?>"
					></iframe>
				</div>
				<?php
			} elseif ( $is_soundcloud ) {
				?>
				<div class="ohmylms-lesson-audio ohmylms-lesson-external-audio">
					<iframe
						src="<?php echo esc_url( 'https://w.soundcloud.com/player/?url=' . rawurlencode( $external_url ) . '&auto_play=false&color=%23ff5500&hide_related=true&show_comments=false&show_user=true&show_reposts=false' ); ?>"
						width="100%"
						height="166"
						frameborder="0"
						scrolling="no"
						allow="autoplay"
						title="<?php esc_attr_e( 'SoundCloud Audio Player', 'ohmylms' ); ?>"
					></iframe>
				</div>
				<?php
			} elseif ( $is_audio_file ) {
				?>
				<div class="ohmylms-lesson-audio">
					<audio controls controlsList="nodownload" style="width:100%;">
						<source src="<?php echo esc_url( $external_url ); ?>">
						<?php esc_html_e( 'Your browser does not support the audio element.', 'ohmylms' ); ?>
					</audio>
				</div>
				<?php
			} else {
				// Unknown audio external URL — try generic iframe.
				?>
				<div class="ohmylms-lesson-audio ohmylms-lesson-external-audio">
					<iframe
						src="<?php echo esc_url( $external_url ); ?>"
						width="100%"
						height="152"
						frameborder="0"
						allow="autoplay; encrypted-media"
						title="<?php esc_attr_e( 'Audio Player', 'ohmylms' ); ?>"
					></iframe>
				</div>
				<?php
			}
		}

		$external_video = $external_url;
		if ( $external_video && 'audio' !== $lesson_type ) {
			\OhMyLMS\Extensions\Video::render( array( 'url' => $external_video ) + $player_args );
		}
		?>
		<?php
		$audio = wp_get_attachment_url( $lesson->get_audio_id() );
		if ( $audio ) {
			?>
			<div class="ohmylms-lesson-audio">
				<audio id="audio" controls controlsList="nodownload">
					<source src="<?php echo esc_url( $audio ); ?>" type="audio/mpeg">
					Your browser does not support the audio element.
				</audio>
			</div>
			<?php
		}
		?>

		<?php \OhMyLMS\Extensions\Bootstrap::lesson( $lesson ); ?>

	</div>

<?php endwhile; // end of the loop. ?>


<?php
$lesson           = ohmylms_get_lesson( get_the_ID() );
$lesson_id        = $lesson->get_id();
$lesson_resources = method_exists( $lesson, 'get_download_resource' ) ? $lesson->get_download_resource() : array();
if ( ! empty( $lesson_resources['file'] ) ) {
	?>
	<ul class="ohmylms-resources-list">
		<?php
		foreach ( $lesson_resources['file'] as $resource ) {
			?>
			<li>
				<div class="ohmylms-single-resource-info">
					<span class="resource-icon">
						<?php include OHMYLMS_DIR . '/assets/images/icon/file-icon.php'; ?>
					</span>
					<span class="resource-name"><?php echo esc_html( $resource['name'] ); ?></span>
					<span class="resource-size"><?php echo esc_html( $resource['size'] ); ?></span>
				</div>
				
				<a href="<?php echo esc_url( $resource['url'] ); ?>" class="resource-action" download>
					<?php include OHMYLMS_DIR . '/assets/images/icon/download-icon.php'; ?>
				</a>
			</li>
			<?php
		}
		?>
	</ul>
	<?php
}
?>
