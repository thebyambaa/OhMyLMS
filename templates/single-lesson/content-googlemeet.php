<?php
/**
 * Template for displaying a Google Meet session inside a lesson
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/single-lesson/content-googlemeet.php.
 *
 * @package OhMyLMS\Templates
 * @version 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id    = get_the_ID();
$event_data = get_post_meta( $post_id, '_googlemeet_event_data', true );

// If no event data, show a fallback notice instead of rendering nothing.
if ( empty( $event_data ) || ! is_array( $event_data ) ) {
	?>
	<div class="creator-googlemeet-meeting-container">
		<div class="meeting-status not-created">
			<p class="meeting-error-notice"><?php esc_html_e( 'Meeting details are not available yet. Please contact the instructor.', 'ohmylms' ); ?></p>
		</div>
	</div>
	<?php
	return;
}

// Extract meeting details.
$meet_link  = $event_data['hangoutLink'] ?? '';
$meeting_id = $event_data['conferenceData']['conferenceId'] ?? '';
$topic      = $event_data['summary'] ?? get_the_title();
$timezone   = $event_data['start']['timeZone'] ?? wp_timezone_string();

$start_str = $event_data['start']['dateTime'] ?? '';
$end_str   = $event_data['end']['dateTime'] ?? '';

if ( ! $meet_link || ! $start_str || ! $end_str ) {
	?>
	<div class="creator-googlemeet-meeting-container">
		<div class="meeting-status not-created">
			<p class="meeting-error-notice"><?php esc_html_e( 'Meeting details are not available yet. Please contact the instructor.', 'ohmylms' ); ?></p>
		</div>
	</div>
	<?php
	return;
}

// Google Meet's own agenda field is plain text (Calendar API strips formatting),
// so for on-site display prefer the lesson's original rich post content and only
// fall back to the Calendar copy if that's empty.
$post_content_raw = get_post_field( 'post_content', $post_id );
$agenda_html      = trim( $post_content_raw )
	? apply_filters( 'the_content', $post_content_raw )
	: wp_kses_post( nl2br( $event_data['description'] ?? '' ) );

// Get start and join URLs (for Google Meet, they're the same link, but we
// separate them for clarity and parity with Zoom's start_url/join_url).
$start_url = get_post_meta( $post_id, '_start_url', true ) ?: $meet_link;
$join_url  = get_post_meta( $post_id, '_join_url', true ) ?: $meet_link;

// Check if current user is the instructor/admin — only they can "start" the
// meeting early; students see a disabled Join button until it's live.
$current_user_id = get_current_user_id();
$is_instructor   = false;
if ( $current_user_id ) {
	$course_id = ohmylms_get_course_by_content_id( $post_id );
	if ( $course_id ) {
		$course        = get_post( $course_id );
		$is_instructor = ( $course && $course->post_author == $current_user_id ) || current_user_can( 'manage_options' );
	}
}

// Create DateTime objects.
$start_dt = new \DateTime( $start_str, new \DateTimeZone( $timezone ) );
$end_dt   = new \DateTime( $end_str, new \DateTimeZone( $timezone ) );

$current_dt = new \DateTime( 'now', new \DateTimeZone( $timezone ) );

// Timestamps for comparison.
$start_ts   = $start_dt->getTimestamp();
$end_ts     = $end_dt->getTimestamp();
$current_ts = $current_dt->getTimestamp();

// Determine meeting state.
$meeting_state = 'not_started';
if ( $current_ts >= $start_ts && $current_ts <= $end_ts ) {
	$meeting_state = 'ongoing';
} elseif ( $current_ts > $end_ts ) {
	$meeting_state = 'ended';
}

// Calculate time ago for ongoing meetings.
$time_ago = '';
if ( 'ongoing' === $meeting_state ) {
	$time_ago = human_time_diff( $start_ts, $current_ts ) . ' ago';
}

// Duration.
$duration_minutes   = round( ( $end_ts - $start_ts ) / 60 );
$hours              = floor( $duration_minutes / 60 );
$minutes            = $duration_minutes % 60;
$duration_formatted = '';
if ( $hours > 0 ) {
	$hr_unit             = 1 === $hours ? 'hour' : 'hours';
	$duration_formatted .= $hours . ' ' . $hr_unit . ' ';
}
if ( $minutes > 0 ) {
	$min_unit            = 1 === $minutes ? 'minute' : 'minutes';
	$duration_formatted .= $minutes . ' ' . $min_unit;
}
$duration_formatted = trim( $duration_formatted );

// Format multiline description for the calendar link.
$details         = implode(
	PHP_EOL,
	array(
		"Topic: {$topic}",
		'Agenda: ' . wp_strip_all_tags( $post_content_raw ? $post_content_raw : ( $event_data['description'] ?? '' ) ),
		"Join URL: {$meet_link}",
		"Meeting ID: {$meeting_id}",
		"Duration: {$duration_formatted}",
	)
);
$encoded_details = rawurlencode( $details );

$google_calendar_url = sprintf(
	'https://www.google.com/calendar/render?action=TEMPLATE&text=%s&dates=%s/%s&details=%s',
	urlencode( $topic ),
	gmdate( 'Ymd\THis\Z', $start_ts ),
	gmdate( 'Ymd\THis\Z', $end_ts ),
	$encoded_details
);

// Attachments (uploaded via the Live Class settings panel).
$attachments = json_decode( get_post_meta( $post_id, '_attachments', true ), true );
if ( ! is_array( $attachments ) ) {
	$attachments = array();
}

// Replay, if the instructor attached one (manually, link or upload).
$recording_source = get_post_meta( $post_id, '_recording_source', true );
$recording_url    = get_post_meta( $post_id, '_recording_url', true );
$recording_state  = get_post_meta( $post_id, '_recording_state', true );
$has_recording    = ( 'attached' === $recording_state ) && ! empty( $recording_url );

// YouTube/Vimeo/Uploaded play through the site's custom video player
// (assets/src/frontend/js/lesson/video-player.js — same one lesson videos
// use), which does its own URL parsing from data-video-url. Drive isn't a
// real video file the player can load — that stays a plain iframe.
$recording_uses_custom_player = in_array( $recording_source, array( 'YouTube', 'Vimeo', 'Uploaded' ), true );

$recording_embed_src = '';
if ( $has_recording && ! $recording_uses_custom_player && 'Drive' === $recording_source ) {
	if ( preg_match( '/\/d\/([A-Za-z0-9_-]+)/', $recording_url, $matches ) ) {
		$recording_embed_src = 'https://drive.google.com/file/d/' . $matches[1] . '/preview';
	}
}

ob_start();
if ( $has_recording ) {
	?>
	<div class="creator-zoom-recording">
		<h2 class="creator-zoom-recording__title"><?php esc_html_e( 'Session Replay', 'ohmylms' ); ?></h2>
		<?php if ( $recording_uses_custom_player ) : ?>
			<div
				class="ohmylms-custom-video-player ohmylms-responsive-video-wrapper"
				style="aspect-ratio: 16/9;"
				data-video-url="<?php echo esc_attr( $recording_url ); ?>"
				data-autoplay="false"
				data-loop="false"
				data-muted="false"
			></div>
		<?php elseif ( $recording_embed_src ) : ?>
			<div class="creator-zoom-recording__embed">
				<iframe src="<?php echo esc_url( $recording_embed_src ); ?>" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen frameborder="0"></iframe>
			</div>
		<?php else : ?>
			<a href="<?php echo esc_url( $recording_url ); ?>" target="_blank" rel="noopener noreferrer" class="ohmylms-btn"><?php esc_html_e( 'Watch Recording', 'ohmylms' ); ?></a>
		<?php endif; ?>
	</div>
	<?php
}
$recording_html = ob_get_clean();

ob_start();
if ( ! empty( $attachments ) ) {
	?>
	<ul class="ohmylms-resources-list creator-zoom-attachments-list">
		<?php foreach ( $attachments as $attachment ) : ?>
			<?php
			if ( empty( $attachment['url'] ) ) {
				continue;}
			?>
			<li>
				<div class="ohmylms-single-resource-info">
					<span class="resource-icon">
						<?php include OHMYLMS_DIR . '/assets/images/icon/file-icon.php'; ?>
					</span>
					<span class="resource-name"><?php echo esc_html( $attachment['name'] ?? '' ); ?></span>
					<?php if ( ! empty( $attachment['size'] ) ) : ?>
						<span class="resource-size"><?php echo esc_html( $attachment['size'] ); ?></span>
					<?php endif; ?>
				</div>

				<a href="<?php echo esc_url( $attachment['url'] ); ?>" class="resource-action" download>
					<?php include OHMYLMS_DIR . '/assets/images/icon/download-icon.php'; ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php
}
$attachments_html = ob_get_clean();

$copy_icon = '<svg width="20" height="20" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" class="w-6 h-6" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>';
?>

<div class="creator-googlemeet-meeting-container" data-meeting-state="<?php echo esc_attr( $meeting_state ); ?>" data-start-ts="<?php echo esc_attr( $start_ts ); ?>">

	<?php if ( 'not_started' === $meeting_state ) : ?>
		<div class="meeting-status not-started">
			<h1 class="meeting-title"><?php echo esc_html( $topic ); ?></h1>
			<div class="meeting-agenda ohmylms-wysiwyg-content"><?php echo $agenda_html; ?></div>

			<div id="meet-countdown" class="meet-countdown">
				<div class="countdown-item"><span class="num" id="days">0</span><span class="label">Days</span></div>
				<div class="countdown-item"><span class="num" id="hours">0</span><span class="label">Hours</span></div>
				<div class="countdown-item"><span class="num" id="minutes">0</span><span class="label">Minutes</span></div>
				<div class="countdown-item"><span class="num" id="seconds">0</span><span class="label">Seconds</span></div>
			</div>

			<div class="meeting-info">
				<span><strong><?php esc_html_e( 'Date & Time:', 'ohmylms' ); ?></strong> <?php echo esc_html( $start_dt->format( 'M d, Y h:i A' ) ); ?> (<?php echo esc_html( $timezone ); ?>)</span>

				<span class="copy-btn" data-copy-target="meeting-link">
					<strong><?php esc_html_e( 'Meeting Link:', 'ohmylms' ); ?></strong>
					<a href="<?php echo esc_url( $meet_link ); ?>" target="_blank" id="meeting-link" class="meeting-link-text"><?php echo esc_html( $meet_link ); ?></a>
					<?php echo $copy_icon; ?>
					<span class="copied-message">Copied!</span>
				</span>

				<span class="copy-btn" data-copy-target="meeting-id">
					<strong><?php esc_html_e( 'Meeting ID:', 'ohmylms' ); ?></strong>
					<span id="meeting-id"><?php echo esc_html( $meeting_id ? $meeting_id : '—' ); ?></span>
					<?php echo $copy_icon; ?>
					<span class="copied-message">Copied!</span>
				</span>

				<span><strong><?php esc_html_e( 'Duration:', 'ohmylms' ); ?></strong> <?php echo esc_html( $duration_formatted ); ?></span>
			</div>

			<?php echo $attachments_html; ?>

			<div class="meeting-actions">
				<?php if ( $is_instructor ) : ?>
					<a href="<?php echo esc_url( $start_url ); ?>" target="_blank" class="ohmylms-btn"><?php esc_html_e( 'Start Meeting', 'ohmylms' ); ?></a>
				<?php else : ?>
					<a href="#" class="ohmylms-btn disabled-btn" disabled><?php esc_html_e( 'Join Meeting', 'ohmylms' ); ?></a>
				<?php endif; ?>

				<a href="<?php echo esc_url( $google_calendar_url ); ?>" target="_blank" class="ohmylms-btn-secondary add-to-calendar-btn">
					<?php esc_html_e( 'Add to Calendar', 'ohmylms' ); ?>
				</a>
			</div>
		</div>

	<?php elseif ( 'ongoing' === $meeting_state ) : ?>
		<div class="meeting-status ongoing">
			<h1 class="meeting-title"><?php echo esc_html( $topic ); ?></h1>
			<div class="meeting-agenda ohmylms-wysiwyg-content"><?php echo $agenda_html; ?></div>
			<p class="meeting-live-notice"><?php esc_html_e( 'Meeting is live now!', 'ohmylms' ); ?></p>

			<div class="meeting-info">
				<span><strong><?php esc_html_e( 'Started:', 'ohmylms' ); ?></strong> <?php echo esc_html( $time_ago ); ?></span>

				<span class="copy-btn" data-copy-target="meeting-link">
					<strong><?php esc_html_e( 'Meeting Link:', 'ohmylms' ); ?></strong>
					<a href="<?php echo esc_url( $join_url ); ?>" target="_blank" id="meeting-link" class="meeting-link-text"><?php echo esc_html( $join_url ); ?></a>
					<?php echo $copy_icon; ?>
					<span class="copied-message">Copied!</span>
				</span>

				<span class="copy-btn" data-copy-target="meeting-id">
					<strong><?php esc_html_e( 'Meeting ID:', 'ohmylms' ); ?></strong>
					<span id="meeting-id"><?php echo esc_html( $meeting_id ? $meeting_id : '—' ); ?></span>
					<?php echo $copy_icon; ?>
					<span class="copied-message">Copied!</span>
				</span>

				<span><strong><?php esc_html_e( 'Duration:', 'ohmylms' ); ?></strong> <?php echo esc_html( $duration_formatted ); ?></span>
			</div>

			<?php echo $attachments_html; ?>

			<div class="meeting-actions">
				<a href="<?php echo esc_url( $join_url ); ?>" target="_blank" class="ohmylms-btn join-btn"><?php esc_html_e( 'Join Meeting', 'ohmylms' ); ?></a>
			</div>
		</div>

	<?php else : ?>
		<div class="meeting-status ended">
			<h1 class="meeting-title"><?php echo esc_html( $topic ); ?></h1>
			<div class="meeting-agenda ohmylms-wysiwyg-content"><?php echo $agenda_html; ?></div>

			<?php echo $recording_html; ?>

			<div class="meeting-info">
				<span><strong><?php esc_html_e( 'Started on:', 'ohmylms' ); ?></strong> <?php echo esc_html( $start_dt->format( 'M d, Y h:i A' ) ); ?> (<?php echo esc_html( $timezone ); ?>)</span>

				<span class="copy-btn" data-copy-target="meeting-id">
					<strong><?php esc_html_e( 'Meeting ID:', 'ohmylms' ); ?></strong>
					<span id="meeting-id"><?php echo esc_html( $meeting_id ? $meeting_id : '—' ); ?></span>
					<?php echo $copy_icon; ?>
					<span class="copied-message">Copied!</span>
				</span>
			</div>

			<?php echo $attachments_html; ?>

			<p class="meeting-ended-notice"><?php esc_html_e( 'This meeting has ended.', 'ohmylms' ); ?></p>
			<?php do_action( 'ohmylms/after_meeting_end', $post_id ); ?>
		</div>
	<?php endif; ?>
</div>

<script>
	document.addEventListener('DOMContentLoaded', function () {
		const container = document.querySelector('.creator-googlemeet-meeting-container');
		if (!container) return;

		const startTimestamp = parseInt(container.getAttribute('data-start-ts'), 10) * 1000;

		const daysEl = document.getElementById('days');
		const hoursEl = document.getElementById('hours');
		const minutesEl = document.getElementById('minutes');
		const secondsEl = document.getElementById('seconds');

		if (!daysEl || !hoursEl || !minutesEl || !secondsEl) return;

		const updateCountdown = () => {
			const now = new Date().getTime();
			const distance = startTimestamp - now;

			if (distance < 0) {
				daysEl.innerText = '0';
				hoursEl.innerText = '0';
				minutesEl.innerText = '0';
				secondsEl.innerText = '0';
				clearInterval(interval);
				container.querySelector('#meet-countdown').innerHTML = 'The meeting is about to start!';
				setTimeout(() => window.location.reload(), 2000);
				return;
			}

			const days = Math.floor(distance / (1000 * 60 * 60 * 24));
			const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
			const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
			const seconds = Math.floor((distance % (1000 * 60)) / 1000);

			animateUpdate(daysEl, days);
			animateUpdate(hoursEl, hours);
			animateUpdate(minutesEl, minutes);
			animateUpdate(secondsEl, seconds);
		};

		const animateUpdate = (el, newValue) => {
			if (el.innerText !== newValue.toString()) {
				el.innerText = newValue;
				const parent = el.parentElement;
				parent.classList.add('animate');
				setTimeout(() => parent.classList.remove('animate'), 300);
			}
		};

		updateCountdown(); // show instantly
		const interval = setInterval(updateCountdown, 1000);
	});

	document.querySelectorAll('.copy-btn').forEach(button => {
		button.addEventListener('click', (e) => {
			if (e.target.closest('a')) return; // let the link click through, don't hijack navigation
			const targetId = button.getAttribute('data-copy-target');
			const text = document.getElementById(targetId)?.innerText;

			if (text) {
				navigator.clipboard.writeText(text).then(() => {
					const msg = button.querySelector('.copied-message');
					msg.style.display = 'inline';
					setTimeout(() => msg.style.display = 'none', 1500);
				}).catch(() => {
					const textarea = document.createElement('textarea');
					textarea.value = text;
					document.body.appendChild(textarea);
					textarea.select();
					document.execCommand('copy');
					document.body.removeChild(textarea);

					const msg = button.querySelector('.copied-message');
					msg.style.display = 'inline';
					setTimeout(() => msg.style.display = 'none', 1500);
				});
			}
		});
	});
</script>
