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

		if ( $video ) {
			// Get video settings with new aspect ratio approach.
			$aspect_ratio = ! empty( $video_settings['aspect_ratio'] ) ? $video_settings['aspect_ratio'] : '16/9';
			$autoplay     = ! empty( $video_settings['autoplay'] ) ? true : false;
			$loop         = ! empty( $video_settings['loop'] ) ? 1 : 0;
			$controls     = ! empty( $video_settings['controls'] ) ? 1 : 0;

			// Get global setting for custom video player.
			$use_custom_player = get_option( 'ohmylms_use_custom_video_player', 'yes' ) === 'yes' ? 1 : 0;
			$logo_bg_color     = get_option( 'ohmylms_video_player_logo_bg_color', '#6E42D3' );

			if ( $use_custom_player ) {
				// Use Custom Video Player for self-hosted videos.
				?>
				<style>.ohmylms-page img.ohmylms-player-logo { background-color: <?php echo esc_attr( $logo_bg_color ); ?> !important; }</style>
				<div
					class="ohmylms-custom-video-player ohmylms-responsive-video-wrapper"
					style="aspect-ratio: <?php echo esc_attr( $aspect_ratio ); ?>;"
					data-video-url="<?php echo esc_url( $video ); ?>"
					data-autoplay="<?php echo esc_attr( $autoplay ? 'true' : 'false' ); ?>"
					data-loop="<?php echo esc_attr( $loop ? 'true' : 'false' ); ?>"
					data-muted="<?php echo esc_attr( $autoplay ? 'true' : 'false' ); ?>"
					data-poster="<?php echo esc_attr( $poster ? esc_url( $poster ) : '' ); ?>"
					data-logo-url="<?php echo esc_url( get_option( 'ohmylms_video_player_logo', '' ) ); ?>"
				>
					<!-- Player will be initialized by video-player.js -->
				</div>
				<?php
			} else {
				// Use standard HTML5 video player.
				?>
				<div class="ohmylms-video-player ohmylms-responsive-video-wrapper" tabindex="1" style="max-width: 100%; margin: 0 auto; aspect-ratio: <?php echo esc_attr( $aspect_ratio ); ?>;">
					<video 
						class="the-video" 
						tabindex="2"
						style="aspect-ratio: <?php echo esc_attr( $aspect_ratio ); ?>;"
						<?php
						// need to add controls and loop here.
							$options = '';
						if ( $autoplay ) {
							$options .= 'autoplay playsinline muted';
						}
						if ( $controls ) {
							$options .= ' controls controlsList="nodownload nopictureinpicture"';
						}

						if ( $loop ) {
							$options .= ' loop';
						}
							echo $options;
						?>
					>
						<source src="<?php echo esc_url( $video ); ?>" type="video/mp4">
						Your browser does not support the video tag.
					</video>
					<?php if ( $poster && ! $autoplay ) { ?>
						<div class="ohmylms-video-player-cover" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;">
							<img src="<?php echo esc_url( $poster ); ?>" alt="<?php echo esc_attr( $cover_image_alt ? $cover_image_alt : $cover_image_title ); ?>" style="width: 100%; height: 100%; object-fit: cover;">

							<button type="button" title="Play" aria-label="Play video" class="ohmylms-video-player-play" tabindex="0">
								<svg width="14" height="14" fill="none" viewBox="0 0 14 14" xmlns="http://www.w3.org/2000/svg"><path fill="var(--ohmylms-primary-color)" d="M2.977.309C1.715-.415.69.178.69 1.632v10.735c0 1.456 1.024 2.048 2.286 1.325l9.382-5.381c1.263-.724 1.263-1.898 0-2.622L2.977.31z"/></svg>
							</button>
						</div>
					<?php } ?>
				</div>
				<?php
			}
			?>
			<?php

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
			// Get video settings with new aspect ratio approach.
			$aspect_ratio        = ! empty( $video_settings['aspect_ratio'] ) ? $video_settings['aspect_ratio'] : '16/9';
			$platform            = ! empty( $video_settings['platform'] ) ? $video_settings['platform'] : 'self-hosted';
			$autoplay            = ! empty( $video_settings['autoplay'] ) ? 1 : 0;
			$loop                = ! empty( $video_settings['loop'] ) ? 1 : 0;
			$controls            = ! empty( $video_settings['controls'] ) ? 1 : 0;
			$remove_branding     = ! empty( $video_settings['remove_branding'] ) ? 1 : 0;
			$hide_related_videos = ! empty( $video_settings['hide_related_videos'] ) ? 1 : 0;

			// Get global setting for custom video player (from Advanced settings).
			$use_custom_player = get_option( 'ohmylms_use_custom_video_player', 'yes' ) === 'yes' ? 1 : 0;

			// Check platform types.
			$is_youtube = strpos( $external_video, 'youtube.com' ) !== false || strpos( $external_video, 'youtu.be' ) !== false;
			$is_vimeo   = strpos( $external_video, 'vimeo.com' ) !== false;

			// Check if it's a direct video file (self-hosted).
			$is_direct_video = preg_match( '/\.(mp4|webm|ogg|mov|m4v|avi|flv|mkv)(\?.*)?$/i', $external_video );

			// If not YouTube/Vimeo and has a video-like URL structure, assume it's self-hosted.
			if ( ! $is_youtube && ! $is_vimeo ) {
				// Accept URLs from media library, CDN, or any URL starting with http/https.
				$is_direct_video = $is_direct_video || ( strpos( $external_video, 'http' ) === 0 );
			}


			// Only use custom player when explicitly enabled.
			if ( $use_custom_player && ( $is_youtube || $is_vimeo || $is_direct_video ) ) {
				// Use Custom Video Player.
				$logo_bg_color = get_option( 'ohmylms_video_player_logo_bg_color', '#6E42D3' );
				?>
				<style>.ohmylms-page img.ohmylms-player-logo { background-color: <?php echo esc_attr( $logo_bg_color ); ?> !important; }</style>
				<div
					class="ohmylms-custom-video-player ohmylms-responsive-video-wrapper"
					style="aspect-ratio: <?php echo esc_attr( $aspect_ratio ); ?>;"
					data-video-url="<?php echo esc_attr( $external_video ); ?>"
					data-autoplay="<?php echo esc_attr( $autoplay ? 'true' : 'false' ); ?>"
					data-loop="<?php echo esc_attr( $loop ? 'true' : 'false' ); ?>"
					data-muted="<?php echo esc_attr( $autoplay ? 'true' : 'false' ); ?>"
					data-poster="<?php echo esc_attr( $poster ? esc_url( $poster ) : '' ); ?>"
					data-logo-url="<?php echo esc_url( get_option( 'ohmylms_video_player_logo', '' ) ); ?>"
				>
					<!-- Player will be initialized by video-player.js -->
				</div>
				<?php
			} else {
				// Use standard iframe embed (previous player)
				// Modify URL based on platform and settings.
				$modified_url = $external_video;

				// Handle YouTube URLs.
				if ( $is_youtube ) {
					$params = array();

					// Extract YouTube video ID.
					preg_match( '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $external_video, $matches );
					if ( ! empty( $matches[1] ) ) {
						$youtube_id   = $matches[1];
						$modified_url = 'https://www.youtube.com/embed/' . $youtube_id;

						// Apply autoplay settings.
						if ( $autoplay ) {
							$params[] = 'autoplay=1';
							$params[] = 'mute=1'; // required for autoplay in modern browsers.
						}

						if ( $loop ) {
							$params[] = 'loop=1';
							$params[] = 'playlist=' . $youtube_id; // Required for loop to work.
						}

						// Apply branding removal settings.

						$params[] = 'modestbranding=1'; // Remove YouTube logo.
						$params[] = 'showinfo=0'; // Hide video title and uploader info.
						$params[] = 'rel=0'; // Hide related videos at the end.

						// Additional YouTube embed parameters for better control.
						$params[] = 'enablejsapi=1'; // Enable JavaScript API.

						// Apply controls settings if available.
						if ( $controls ) {
							$params[] = 'controls=1'; // Hide all controls.
						} else {
							$params[] = 'controls=0'; // Show controls (default).
						}

						// Build final URL with parameters.
						if ( ! empty( $params ) ) {
							$modified_url .= '?' . implode( '&', $params );
						}
					}
				}

				// Handle Vimeo URLs.
				elseif ( $is_vimeo ) {
					if ( preg_match( '/vimeo\.com\/(\d+)/', $external_video, $matches ) ) {
						$vimeo_id = $matches[1];
						$params   = array();

						// Apply autoplay settings.
						if ( $autoplay ) {
							$params['autoplay'] = '1';
							$params['muted']    = '1'; // required for autoplay in modern browsers.
						} else {
							$params['autoplay'] = '0';
							$params['muted']    = '0';
						}

						if ( $controls ) {
							$params['controls'] = '1'; // Show controls.
						} else {
							$params['controls'] = '0'; // Hide all controls.
						}

						// Apply branding/title settings.
						if ( $remove_branding ) {
							$params['title']    = '0'; // Hide video title.
							$params['byline']   = '0'; // Hide author byline.
							$params['portrait'] = '0'; // Hide author portrait.
						}

						// Apply loop setting.
						if ( ! empty( $video_settings['loop'] ) ) {
							$params['loop'] = '1';
						}

						// Build Vimeo embed URL.
						$modified_url = "https://player.vimeo.com/video/{$vimeo_id}";
						if ( ! empty( $params ) ) {
							$modified_url .= '?' . http_build_query( $params );
						}
					}
				}

				// Use iframe for other embed sources.
				?>
				<div class="ohmylms-lesson-external-video ohmylms-responsive-video-wrapper" style="aspect-ratio: <?php echo esc_attr( $aspect_ratio ); ?>;">
					<iframe
						src="<?php echo esc_url( $modified_url ); ?>" 
						frameborder="0" 
						title="Video player"
						allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
						referrerpolicy="strict-origin-when-cross-origin"
						allowfullscreen
						style="aspect-ratio: <?php echo esc_attr( $aspect_ratio ); ?>;"
					></iframe>
				</div>
				<?php
			}
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
