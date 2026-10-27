<?php
/**
 * Template for displaying Google Meet lesson content.
 *
 * @package ohmylms-pro
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $post;

$meet_link      = get_post_meta( $post->ID, '_googlemeet_link', true );
$meeting_id     = get_post_meta( $post->ID, '_googlemeet_meeting_id', true );
$start_time     = get_post_meta( $post->ID, '_session_start_time', true );
$end_time       = get_post_meta( $post->ID, '_session_end_time', true );
$timezone       = get_post_meta( $post->ID, '_session_timezone', true );
$meeting_topic  = get_post_meta( $post->ID, '_session_topic', true );
$meeting_agenda = get_post_meta( $post->ID, '_session_agenda', true );

$now            = current_time( 'timestamp' );
$meeting_status = 'upcoming';

if ( $now >= $start_time && $now <= $end_time ) {
	$meeting_status = 'live';
} elseif ( $now > $end_time ) {
	$meeting_status = 'ended';
}
?>

<div class="ohmylms-googlemeet-content" data-meeting-id="<?php echo esc_attr( $meeting_id ); ?>">
	<div class="googlemeet-header">
		<div class="googlemeet-icon">
			<svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
				<path d="M20 8.5V15.5C20 16.3284 19.3284 17 18.5 17H5.5C4.67157 17 4 16.3284 4 15.5V8.5C4 7.67157 4.67157 7 5.5 7H18.5C19.3284 7 20 7.67157 20 8.5Z" fill="#00832D"/>
				<path d="M20 10L23 8V16L20 14V10Z" fill="#0066DA"/>
				<path d="M4 9H6V15H4V9Z" fill="#E94235"/>
				<path d="M18 9H20V15H18V9Z" fill="#2684FC"/>
				<circle cx="12" cy="12" r="3" fill="white"/>
			</svg>
		</div>
		<div class="googlemeet-info">
			<h2 class="googlemeet-title"><?php echo esc_html( $meeting_topic ? $meeting_topic : get_the_title() ); ?></h2>
			<div class="googlemeet-status <?php echo esc_attr( $meeting_status ); ?>">
				<?php if ( 'live' === $meeting_status ) : ?>
					<span class="status-badge live"><?php esc_html_e( 'Live Now', 'ohmylms' ); ?></span>
				<?php elseif ( 'upcoming' === $meeting_status ) : ?>
					<span class="status-badge upcoming"><?php esc_html_e( 'Upcoming', 'ohmylms' ); ?></span>
				<?php else : ?>
					<span class="status-badge ended"><?php esc_html_e( 'Ended', 'ohmylms' ); ?></span>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div class="googlemeet-details">
		<?php if ( $meeting_agenda ) : ?>
			<div class="meeting-agenda">
				<h3><?php esc_html_e( 'Agenda', 'ohmylms' ); ?></h3>
				<p><?php echo esc_html( $meeting_agenda ); ?></p>
			</div>
		<?php endif; ?>

		<div class="meeting-schedule">
			<div class="schedule-item">
				<strong><?php esc_html_e( 'Start Time:', 'ohmylms' ); ?></strong>
				<span><?php echo esc_html( wp_date( 'F j, Y g:i A', $start_time ) ); ?></span>
			</div>
			<div class="schedule-item">
				<strong><?php esc_html_e( 'End Time:', 'ohmylms' ); ?></strong>
				<span><?php echo esc_html( wp_date( 'F j, Y g:i A', $end_time ) ); ?></span>
			</div>
			<?php if ( $timezone ) : ?>
				<div class="schedule-item">
					<strong><?php esc_html_e( 'Timezone:', 'ohmylms' ); ?></strong>
					<span><?php echo esc_html( $timezone ); ?></span>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( $meet_link ) : ?>
			<div class="googlemeet-actions">
				<?php if ( 'live' === $meeting_status ) : ?>
					<a href="<?php echo esc_url( $meet_link ); ?>" 
						target="_blank" 
						rel="noopener noreferrer" 
						class="button googlemeet-join-button primary">
						<?php esc_html_e( 'Join Meeting Now', 'ohmylms' ); ?>
					</a>
				<?php elseif ( 'upcoming' === $meeting_status ) : ?>
					<a href="<?php echo esc_url( $meet_link ); ?>" 
						target="_blank" 
						rel="noopener noreferrer" 
						class="button googlemeet-join-button">
						<?php esc_html_e( 'View Meeting Link', 'ohmylms' ); ?>
					</a>
					<p class="meeting-info">
						<?php esc_html_e( 'The meeting will be available at the scheduled time', 'ohmylms' ); ?>
					</p>
				<?php else : ?>
					<p class="meeting-ended">
						<?php esc_html_e( 'This meeting has ended', 'ohmylms' ); ?>
					</p>
				<?php endif; ?>
				<a href="
				<?php
				echo esc_url(
					add_query_arg(
						array(
							'action'     => 'ohmylms_googlemeet_add_to_calendar',
							'meeting_id' => $meeting_id,
						),
						admin_url( 'admin-ajax.php' )
					)
				);
				?>
				"
					class="button googlemeet-calendar-button"
					target="_blank"
					rel="noopener noreferrer">
					<?php esc_html_e( 'Add to Calendar', 'ohmylms' ); ?>
				</a>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( 'upcoming' === $meeting_status ) : ?>
		<div class="googlemeet-countdown" data-start-time="<?php echo esc_attr( $start_time ); ?>">
			<h3><?php esc_html_e( 'Starts in', 'ohmylms' ); ?></h3>
			<div class="countdown-timer">
				<div class="time-unit">
					<span class="time-value days">00</span>
					<span class="time-label"><?php esc_html_e( 'Days', 'ohmylms' ); ?></span>
				</div>
				<div class="time-unit">
					<span class="time-value hours">00</span>
					<span class="time-label"><?php esc_html_e( 'Hours', 'ohmylms' ); ?></span>
				</div>
				<div class="time-unit">
					<span class="time-value minutes">00</span>
					<span class="time-label"><?php esc_html_e( 'Minutes', 'ohmylms' ); ?></span>
				</div>
				<div class="time-unit">
					<span class="time-value seconds">00</span>
					<span class="time-label"><?php esc_html_e( 'Seconds', 'ohmylms' ); ?></span>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>

<style>
.ohmylms-googlemeet-content {
	padding: 20px;
	background: #fff;
	border-radius: 8px;
	box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.googlemeet-header {
	display: flex;
	align-items: center;
	gap: 20px;
	margin-bottom: 30px;
	padding-bottom: 20px;
	border-bottom: 1px solid #eee;
}

.googlemeet-icon {
	flex-shrink: 0;
}

.googlemeet-info {
	flex: 1;
}

.googlemeet-title {
	margin: 0 0 10px 0;
	font-size: 24px;
	color: #333;
}

.status-badge {
	display: inline-block;
	padding: 4px 12px;
	border-radius: 4px;
	font-size: 12px;
	font-weight: 600;
	text-transform: uppercase;
}

.status-badge.live {
	background: #e53935;
	color: white;
	animation: pulse 2s infinite;
}

.status-badge.upcoming {
	background: #1e88e5;
	color: white;
}

.status-badge.ended {
	background: #757575;
	color: white;
}

@keyframes pulse {
	0%, 100% { opacity: 1; }
	50% { opacity: 0.7; }
}

.googlemeet-details {
	margin-bottom: 30px;
}

.meeting-agenda {
	margin-bottom: 20px;
	padding: 15px;
	background: #f5f5f5;
	border-radius: 4px;
}

.meeting-agenda h3 {
	margin-top: 0;
	font-size: 16px;
	color: #555;
}

.meeting-schedule {
	margin-bottom: 20px;
}

.schedule-item {
	display: flex;
	gap: 10px;
	padding: 8px 0;
	border-bottom: 1px solid #eee;
}

.schedule-item strong {
	min-width: 100px;
	color: #555;
}

.googlemeet-actions {
	text-align: center;
	padding: 20px;
}

.googlemeet-join-button {
	display: inline-block;
	padding: 12px 32px;
	background: #00832d;
	color: white !important;
	text-decoration: none;
	border-radius: 4px;
	font-weight: 600;
	transition: background 0.3s;
}

.googlemeet-join-button:hover {
	background: #00691f;
}

.googlemeet-join-button.primary {
	background: #1e88e5;
	animation: pulse-button 2s infinite;
}

.googlemeet-join-button.primary:hover {
	background: #1565c0;
}

@keyframes pulse-button {
	0%, 100% { transform: scale(1); }
	50% { transform: scale(1.05); }
}

.meeting-info {
	margin-top: 10px;
	color: #666;
	font-size: 14px;
}

.meeting-ended {
	color: #757575;
	font-size: 16px;
}

.googlemeet-countdown {
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	padding: 30px;
	border-radius: 8px;
	text-align: center;
	color: white;
}

.googlemeet-countdown h3 {
	margin-top: 0;
	font-size: 20px;
}

.countdown-timer {
	display: flex;
	justify-content: center;
	gap: 20px;
	margin-top: 20px;
}

.time-unit {
	display: flex;
	flex-direction: column;
	align-items: center;
}

.time-value {
	display: block;
	font-size: 36px;
	font-weight: bold;
	min-width: 60px;
	padding: 10px;
	background: rgba(255,255,255,0.2);
	border-radius: 8px;
	margin-bottom: 8px;
}

.time-label {
	font-size: 12px;
	text-transform: uppercase;
	opacity: 0.9;
}
</style>
